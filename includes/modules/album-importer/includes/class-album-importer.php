<?php
/**
 * Album Importer — Scanner & Importer
 *
 * 扫描挂载目录下的图集子目录、建 albums 文章、把图片写入媒体库。
 *
 * 设计要点：
 *  - 目录粒度严格按用户原话「选中的目录的直接子目录 = 一套图集」，不做层级推断；
 *  - 图片从只读挂载点**拷进** uploads 后再入库，挂载点绝不写回；
 *  - 入库走标准 WP 流程（wp_generate_attachment_metadata）：站上 92% 附件带缩略图，
 *    且 advanced-media-offloader 的自动卸载只挂在这个 filter 上，跳过它图片永远不会被卸载；
 *  - 不自写任何 Minio/offload 逻辑——交给既有基础设施（卸载后正文 URL 会自动变成 /wp-media/ 桶地址）。
 *
 * ponytail: 未做落库式断点续传（任务状态不落 option、无恢复 UI）。
 *   幂等靠 META_SRC_KEY（整篇）+ menu_order（单张）兜底，中断后重跑安全。
 *   若将来单套体量普遍破万或需要跨会话续传，再补任务队列。
 *
 * @package WP_Genius
 * @subpackage Modules/AlbumImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_Album_Importer
 */
class W2P_Album_Importer {

	/**
	 * 浏览起点，同时也是路径允许边界 —— 等于 php 容器里的只读挂载点。
	 *
	 * 刻意硬编码而非做成设置项：它必须与 docker-compose.yml 的挂载路径完全一致，
	 * 做成可配置就等于多出一个能配错的安全边界。换挂载点时改这一行 + compose 一行。
	 */
	const BROWSE_ROOT = '/mnt/pictures';

	const META_SRC_KEY = '_w2p_album_src_key';
	const META_STATE   = '_w2p_album_import_state';

	/** 记录源目录原始路径（用于日志与「导入后已被移动」的溯源）。 */
	const META_SRC_DIR = '_w2p_album_src_dir';

	/** 导入完成后源目录被移动到的新位置（未移动则不写）。 */
	const META_SRC_MOVED = '_w2p_album_src_dir_moved';

	/**
	 * 单批导入的进度游标（下次该从第几张开始）。
	 *
	 * **当前没有任何代码读它** —— 续跑实际靠「重扫源目录 + 源图幂等复用」实现（重跑代价极低）。
	 * 保留它纯粹是诊断面包屑：中断后想直观看某篇跑到第几张，查这个 meta 就行。
	 */
	const META_OFFSET = '_w2p_album_import_offset';

	/**
	 * 单张图的幂等键：源图**绝对路径**。
	 *
	 * 刻意用源路径而不是目标文件名。目标名来自 `wp_unique_filename()`，它读磁盘现状：
	 * 重跑时上一轮的文件还在 → 返回 `xxx-1.webp`，键就变了、查不中，于是重复入库。
	 * 源路径不受磁盘状态影响，重跑必然命中。
	 */
	const META_SRC_FILE = '_w2p_album_src_file';

	/**
	 * 单批处理的图片数（前端循环调用直到 done）。
	 *
	 * 单张约 0.97s（生成缩略图 + 原图与子尺寸上传 MinIO），批次只影响单个请求的时长、几乎不影响内存峰值。
	 * 取 10：单个请求约 10s，远低于 FPM 的 request_terminate_timeout(600s)，同时把 HTTP 往返次数砍半。
	 */
	const DEFAULT_BATCH = 5;

	/** 单套图片数超过此值时在列表里告警（不阻断）。 */
	const MAX_IMAGES_WARN = 8000;

	/** 一次收集图片的硬上限。触顶说明用户选错了层级（把整个图库当一个套），必须拒绝而不是慢慢跑。 */
	const MAX_COLLECT = 20000;

	/** 允许入库的图片扩展名。 */
	const IMAGE_EXT = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' );

	/**
	 * 已完整导入的源目录会被移动到同级这个子目录下。
	 *
	 * 浏览 / 扫描 / 层级判定一律跳过它——否则移动过的整套图会作为新候选重新冒出来。
	 * 这是一个**目录名**（数据标识），不是界面文案；界面提示用英文 + sprintf 拼它。
	 */
	const DONE_DIR_NAME = '_已导入';

	/**
	 * 浏览起点 / 允许边界。
	 *
	 * @return string
	 */
	public static function get_browse_root() {
		return self::BROWSE_ROOT;
	}

	/**
	 * 挂载点状态自检（只读，供界面与 Ajax 报错使用）。
	 *
	 * 刻意不放进 check_requirements：挂载是运行期环境状态，不是模块语义依赖。
	 * 放进 check_requirements 会导致挂载偶发不可读时整个模块被 loader 跳过、用户进不了设置页自救。
	 *
	 * @return array{readable:bool,root:string,error:string,samples:array}
	 */
	public static function get_mount_status() {
		$root = self::get_browse_root();

		if ( '' === $root || ! is_dir( $root ) ) {
			return array(
				'readable' => false,
				'root'     => $root,
				'error'    => __( 'Directory does not exist.', 'wp-genius' ),
				'samples'  => array(),
			);
		}

		if ( ! is_readable( $root ) ) {
			return array(
				'readable' => false,
				'root'     => $root,
				'error'    => __( 'Directory is not readable. Check the read-only mount in docker-compose.yml.', 'wp-genius' ),
				'samples'  => array(),
			);
		}

		$entries = self::list_subdirs( $root );
		$samples = array();
		foreach ( array_slice( $entries, 0, 5 ) as $entry ) {
			$samples[] = wp_basename( $entry );
		}

		return array(
			'readable' => true,
			'root'     => $root,
			'error'    => '',
			'samples'  => $samples,
		);
	}

	/**
	 * 扫描用户选中的目录，返回待导入候选列表。
	 *
	 * 层级自动判断（用户明确要求「选哪一层就按哪一层」）：
	 *  - 该目录的直接子目录里有 ≥2 个「看着像图集」（名字含日期或编号）→ 当作集合，每个子目录 = 一套；
	 *  - 否则 → 该目录自身就是一套。
	 *
	 * @param string $path 选中目录的绝对路径。
	 * @return array|WP_Error
	 */
	public static function scan( $path ) {
		$path = self::sanitize_abs_path( $path );

		if ( '' === $path || ! is_dir( $path ) ) {
			return new WP_Error( 'w2p_album_path_missing', __( 'The selected directory does not exist.', 'wp-genius' ) );
		}

		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'w2p_album_path_unreadable', __( 'The selected directory is not readable. Check the read-only mount in docker-compose.yml.', 'wp-genius' ) );
		}

		$scope = self::resolve_scope( $path );
		$rows  = array();

		foreach ( $scope['targets'] as $dir ) {
			$rows[] = self::build_candidate( $dir, $path );
		}

		return $rows;
	}

	/**
	 * 判断选中目录是「集合」还是「单套」。
	 *
	 * 判据：直接子目录里只要有**任意一个**名字带日期或编号，就认为这个目录在装图集（= 集合）。
	 *
	 * 为什么阈值取 1 而不是 2：真图集目录的子目录是 `webp/`、`p/`、`已分组/` 这类图片层，
	 * 名字里**不含**日期编号，所以 set_like 为 0 —— 不会误判。
	 * 而实测 `/mnt/pictures/albums`（装着 5 个单套）只有 1 个命中（`【微密圈】No.034...`），
	 * 取 2 就会把它当成「单套」，扫出一条上万图的怪物行。
	 *
	 * @param string $path 选中目录。
	 * @return array{mode:string,targets:array,set_like_subdirs:int,subdir_count:int}
	 */
	public static function resolve_scope( $path ) {
		$subdirs  = self::list_subdirs( $path );
		$set_like = 0;

		foreach ( $subdirs as $sub ) {
			$parsed = W2P_Album_Name_Parser::parse( wp_basename( $sub ) );
			if ( '' !== $parsed['date'] || '' !== $parsed['number'] ) {
				++$set_like;
			}
		}

		$is_collection = ( $set_like >= 1 );

		return array(
			'mode'             => $is_collection ? 'collection' : 'single',
			'targets'          => $is_collection ? $subdirs : array( $path ),
			'set_like_subdirs' => $set_like,
			'subdir_count'     => count( $subdirs ),
		);
	}

	/**
	 * 浏览：列出一层子目录，供界面逐级下钻。
	 *
	 * @param string $path 当前目录；空串表示回到浏览起点。
	 * @return array|WP_Error
	 */
	public static function browse( $path ) {
		$root = self::get_browse_root();

		if ( '' === trim( (string) $path ) ) {
			$path = $root;
		}

		$path = self::sanitize_abs_path( $path );

		if ( '' === $path || ! is_dir( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'w2p_album_browse_unreadable', __( 'Directory is not readable.', 'wp-genius' ) );
		}

		if ( ! self::is_within( $path, $root ) ) {
			return new WP_Error( 'w2p_album_browse_outside', __( 'Directory is outside the allowed browse root.', 'wp-genius' ) );
		}

		// 归档目录不给浏览：那里全是已导入的套图，再扫一遍只会生成一批重复专辑。
		if ( self::DONE_DIR_NAME === wp_basename( $path ) ) {
			return new WP_Error( 'w2p_album_browse_archived', __( 'This folder holds sets that were already imported.', 'wp-genius' ) );
		}

		$subdirs = array();
		foreach ( self::list_subdirs( $path ) as $sub ) {
			$subdirs[] = array(
				'name' => wp_basename( $sub ),
				'path' => $sub,
			);
		}

		// 上一级：只有仍落在边界内才给，否则留空（界面据此禁用按钮）。
		$parent    = '';
		$candidate = dirname( $path );
		if ( $candidate !== $path && self::is_within( $candidate, $root ) ) {
			$parent = $candidate;
		}

		return array(
			'path'    => $path,
			'parent'  => $parent,
			'root'    => $root,
			'at_root' => ( rtrim( $path, '/' ) === rtrim( $root, '/' ) ),
			'subdirs' => $subdirs,
		);
	}

	/**
	 * 组装单个候选图集的信息（解析名 + 数图 + 查重 + 词条匹配）。
	 *
	 * @param string $dir  候选目录绝对路径。
	 * @param string $root 扫描根。
	 * @return array
	 */
	public static function build_candidate( $dir, $root ) {
		$dirname = wp_basename( $dir );
		$parsed  = W2P_Album_Name_Parser::parse( $dirname );

		$images      = self::collect_images( $dir );
		$image_count = count( $images );

		// 触顶说明选错了层级（把整个图库当一套），直接判为不可默认导入。
		$too_large = ( $image_count >= self::MAX_COLLECT );

		// 层级自检：判断这个候选是「图集」还是「图集集合」、或层级含糊。
		// 层级已由 resolve_scope() 自动判定，这里只兜「一个目录里混着多个图片层」这种含糊情况。
		$structure = self::inspect_structure( $dir );

		// 工作室：photostudio 词条两级匹配。
		$studio_term = self::resolve_term( $parsed['studio'], 'photostudio' );

		// 模特：先拿 humans 既有词条库把 token 过一遍。
		$models_matched   = array();
		$models_unmatched = array();
		foreach ( $parsed['tokens'] as $token ) {
			$term = self::resolve_term( $token, 'humans' );
			if ( $term['id'] ) {
				$models_matched[] = $term['name'];
			} else {
				$models_unmatched[] = $token;
			}
		}
		$models_matched   = array_values( array_unique( $models_matched ) );
		$models_unmatched = array_values( array_unique( $models_unmatched ) );

		// 三种落法（实测 + 审查定的）：
		//  · 命中既有词条 → 直接关联；
		//  · **有命中**时，其余 token 判为「描述」——不建（描述碎片灌进 4332 条词条库是灾难）；
		//  · **一个都没命中**、且只剩一个 token、且它像人名 → 那是没入过库的新模特，默认新建。
		//    实测用户真实数据里 `Kely香香` / `Amrita贞贞` / `Yuky伊珍` 全属这一种，他明确要求建出来。
		$models_new  = array();
		$models_desc = array();

		if ( ! empty( $models_matched ) ) {
			$models_desc = $models_unmatched;
		} elseif ( 1 === count( $models_unmatched ) && self::looks_like_a_name( $models_unmatched[0] ) ) {
			$models_new = $models_unmatched;
		} else {
			$models_desc = $models_unmatched;
		}

		$rel_path = ltrim( substr( $dir, strlen( rtrim( $root, '/' ) ) ), '/' );
		$src_key  = md5( $dir );
		$existing = self::find_existing_album( $src_key );

		$warnings = $parsed['warnings'];
		if ( 0 === $image_count ) {
			$warnings[] = __( 'No image files found in this directory.', 'wp-genius' );
		}
		if ( $too_large ) {
			$warnings[] = sprintf(
				/* translators: %d: image cap. */
				__( 'More than %d images found; this directory is too broad to be one photo set. Select a deeper directory.', 'wp-genius' ),
				self::MAX_COLLECT
			);
		} elseif ( $image_count > self::MAX_IMAGES_WARN ) {
			$warnings[] = __( 'Unusually large set; import may take a long time.', 'wp-genius' );
		}
		if ( $structure['suspected_collection'] ) {
			$warnings[] = sprintf(
				/* translators: %d: number of subdirectories that look like photo sets. */
				__( 'Contains %d subdirectories that look like photo sets; this directory is probably one level too high.', 'wp-genius' ),
				$structure['set_like_subdirs']
			);
		}
		if ( $structure['ambiguous_layers'] ) {
			$warnings[] = sprintf(
				/* translators: %d: number of image-layer subdirectories. */
				__( 'Contains %d image-layer subdirectories; images from all of them would be merged.', 'wp-genius' ),
				$structure['layer_dirs']
			);
		}

		return array(
			'dirname'              => $dirname,
			'rel_path'             => $rel_path,
			'abs_path'             => $dir,
			'title'                => $parsed['title'],
			'studio'               => $parsed['studio'],
			'studio_term'          => $studio_term,
			'date'                 => $parsed['date'],
			'date_display'         => self::format_date_display( $parsed['date'] ),
			'number'               => $parsed['number'],
			'models'               => $models_matched,
			'models_new'           => $models_new,
			'models_desc'          => $models_desc,
			'remainder'            => $parsed['remainder'],
			'image_count'          => $image_count,
			'structural'           => $parsed['structural'],
			'too_large'            => $too_large,
			'suspected_collection' => $structure['suspected_collection'],
			'ambiguous_layers'     => $structure['ambiguous_layers'],
			'set_like_subdirs'     => $structure['set_like_subdirs'],
			'existing_id'          => $existing,
			'existing_state'       => $existing ? (string) get_post_meta( $existing, self::META_STATE, true ) : '',
			'existing_url'         => $existing ? get_edit_post_link( $existing, 'raw' ) : '',
			'warnings'             => $warnings,
		);
	}

	/**
	 * 结构自检：一眼判断候选目录是图集、图集集合，还是层级含糊。
	 *
	 * @param string $dir 候选目录。
	 * @return array{suspected_collection:bool,ambiguous_layers:bool,set_like_subdirs:int,layer_dirs:int}
	 */
	public static function inspect_structure( $dir ) {
		$subdirs    = self::list_subdirs( $dir );
		$set_like   = 0;
		$layer_dirs = 0;

		foreach ( $subdirs as $sub ) {
			$sub_name = wp_basename( $sub );

			// 名字里带日期或编号 → 看着像一套图集。
			$parsed = W2P_Album_Name_Parser::parse( $sub_name );
			if ( '' !== $parsed['date'] || '' !== $parsed['number'] ) {
				++$set_like;
			}

			// 名字是 webp / p / 原图 / 已分组 这类图片层。
			if ( W2P_Album_Name_Parser::is_structural_name( $sub_name ) ) {
				++$layer_dirs;
			}
		}

		return array(
			// ≥2 个像图集的子目录 → 这是集合层，扫描根指错了。
			'suspected_collection' => ( $set_like >= 2 ),
			// ≥2 个图片层目录（如 {webp, 已分组}）→ 收图时会混在一起，需人工定夺。
			'ambiguous_layers'     => ( $layer_dirs >= 2 ),
			'set_like_subdirs'     => $set_like,
			'layer_dirs'           => $layer_dirs,
		);
	}

	/**
	 * 递归收集目录下的图片，按自然序（-1, -2, … -10）返回绝对路径。
	 *
	 * 安全：跳过符号链接（防止逃出只读挂载根）、跳过隐藏文件与 .DS_Store、
	 * 每个命中文件做 realpath 前缀校验。
	 *
	 * @param string $dir 目录绝对路径。
	 * @param int    $max 收集上限（防止异常目录吃满内存）。
	 * @return array
	 */
	public static function collect_images( $dir, $max = self::MAX_COLLECT ) {
		$dir = self::sanitize_abs_path( $dir );
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return array();
		}

		$base = realpath( $dir );
		if ( false === $base ) {
			return array();
		}
		$base_prefix = rtrim( $base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		$found = array();
		$queue = array( $base );

		while ( ! empty( $queue ) ) {
			$current = array_shift( $queue );

			$handle = @opendir( $current ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- 权限不足时静默跳过更合适。
			if ( false === $handle ) {
				continue;
			}

			$subdirs = array();
			while ( false !== ( $entry = readdir( $handle ) ) ) {
				if ( '.' === $entry || '..' === $entry || '.DS_Store' === $entry ) {
					continue;
				}
				if ( '.' === $entry[0] ) {
					continue;
				}

				$path = $current . DIRECTORY_SEPARATOR . $entry;

				// 符号链接一律跳过——挂载点内的软链可能指向挂载之外。
				if ( is_link( $path ) ) {
					continue;
				}

				if ( is_dir( $path ) ) {
					$subdirs[] = $path;
					continue;
				}

				if ( ! is_file( $path ) ) {
					continue;
				}

				$ext = strtolower( (string) pathinfo( $entry, PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, self::IMAGE_EXT, true ) ) {
					continue;
				}

				$real = realpath( $path );
				if ( false === $real || strpos( $real, $base_prefix ) !== 0 ) {
					continue;
				}

				$found[] = $real;

				if ( count( $found ) >= $max ) {
					break 2;
				}
			}
			closedir( $handle );

			// 自然序入队，保证遍历顺序稳定。
			if ( ! empty( $subdirs ) ) {
				usort( $subdirs, array( __CLASS__, 'natural_compare' ) );
				$queue = array_merge( $subdirs, $queue );
			}
		}

		usort( $found, array( __CLASS__, 'natural_compare' ) );

		return $found;
	}

	/**
	 * 自然序比较：让 "-2" 排在 "-10" 前面。
	 *
	 * @param string $a 路径 A。
	 * @param string $b 路径 B。
	 * @return int
	 */
	public static function natural_compare( $a, $b ) {
		return strnatcasecmp( (string) $a, (string) $b );
	}

	/**
	 * 词条匹配（三级，严禁自动建）。
	 *
	 * ① 精确 name；
	 * ② sanitize_title() 后的 slug（WP 内置归一化，不自写归一化器）；
	 * ③ 归一化后双向前缀匹配，且**必须唯一命中**才采纳。
	 *
	 * 第③级是刚需而非镀金：实测目录名里的 `XIUREN秀人网` 对不上既有词条 `XiuRen 秀人`，
	 * 只做前两级会导致「工作室」字段 100% 落空。唯一性校验是它的安全阀——
	 * 命中 0 个或多个一律留空，交人工确认，绝不猜。
	 *
	 * 都不中即返回空 id，由上层标「待人工确认」。
	 *
	 * @param string $name     名称。
	 * @param string $taxonomy 分类法。
	 * @return array{id:int,name:string,taxonomy:string}
	 */
	public static function resolve_term( $name, $taxonomy ) {
		$name = trim( (string) $name );
		$miss = array(
			'id'       => 0,
			'name'     => $name,
			'taxonomy' => $taxonomy,
		);

		if ( '' === $name ) {
			return $miss;
		}

		$term = get_term_by( 'name', $name, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			// 仅对纯 ASCII 名做 slug 回退：sanitize_title() 会把中文整个削掉，
			// 留下退化 slug（"100套" → "100"）从而撞上不相干的词条。
			// 中文名靠精确匹配即可（实测"陆萱萱"就是精确命中的）。
			if ( ! preg_match( '/[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]/u', $name ) ) {
				$slug = sanitize_title( $name );
				if ( '' !== $slug ) {
					$term = get_term_by( 'slug', $slug, $taxonomy );
				}
			}
		}

		if ( $term && ! is_wp_error( $term ) ) {
			return array(
				'id'       => (int) $term->term_id,
				'name'     => (string) $term->name,
				'taxonomy' => $taxonomy,
			);
		}

		return self::fuzzy_resolve_term( $name, $taxonomy, $miss );
	}

	/**
	 * 第三级匹配：归一化后双向前缀，唯一命中才采纳。
	 *
	 * @param string $name     原始名称。
	 * @param string $taxonomy 分类法。
	 * @param array  $miss     未命中时的返回值。
	 * @return array
	 */
	private static function fuzzy_resolve_term( $name, $taxonomy, $miss ) {
		$needle = self::normalize_for_match( $name );

		// 太短的串（如 "p"、"webp"）做前缀匹配必然误伤，直接放弃。
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $needle ) < 4 : strlen( $needle ) < 4 ) {
			return $miss;
		}

		$hits = array();
		foreach ( self::get_term_index( $taxonomy ) as $item ) {
			$key = $item['norm'];
			if ( '' === $key ) {
				continue;
			}

			// 词条侧也要有最小长度。否则很长的 token 会和 2~3 字符的短词条相撞
			// （实测词条库里存在 AI / MM / EVE / SSE / Nao 这类短名，如 aiko 会被判成 AI）。
			if ( function_exists( 'mb_strlen' ) ? mb_strlen( $key ) < 4 : strlen( $key ) < 4 ) {
				continue;
			}

			if ( 0 === strpos( $key, $needle ) || 0 === strpos( $needle, $key ) ) {
				$hits[ $item['id'] ] = $item;
			}
		}

		// 唯一命中才算数；0 个或多个都交人工。
		if ( 1 !== count( $hits ) ) {
			return $miss;
		}

		$hit = reset( $hits );

		return array(
			'id'       => (int) $hit['id'],
			'name'     => (string) $hit['name'],
			'taxonomy' => $taxonomy,
		);
	}

	/**
	 * 按分类法构建「归一化名 → 词条」索引（请求内缓存，避免每个候选都全表扫）。
	 *
	 * @param string $taxonomy 分类法。
	 * @return array 形如 array( array( 'norm' => string, 'id' => int, 'name' => string ) )
	 */
	private static function get_term_index( $taxonomy ) {
		static $cache = array();

		if ( isset( $cache[ $taxonomy ] ) ) {
			return $cache[ $taxonomy ];
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'all',
			)
		);

		$index = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$index[] = array(
					'norm' => self::normalize_for_match( $term->name ),
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
				);
			}
		}

		$cache[ $taxonomy ] = $index;

		return $index;
	}

	/**
	 * 匹配用归一化：转小写、去掉一切非字母数字与中日韩字符（空格、标点、下划线、& 等全清）。
	 *
	 * 例：`XIUREN秀人网` → `xiuren秀人网`；`XiuRen 秀人` → `xiuren秀人`（前者以前者为前缀）。
	 *
	 * @param string $s 输入。
	 * @return string
	 */
	private static function normalize_for_match( $s ) {
		$s = strtolower( trim( (string) $s ) );
		$s = preg_replace( '/[^a-z0-9\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]+/u', '', $s );

		return (string) $s;
	}

	/**
	 * 按名称取词条；不存在则新建。
	 *
	 * 只用于**用户在界面上明确确认过**的值（工作室下拉选中、或点＋确认要新建的模特标签）——
	 * 「能命中就使用，不能命中就新建」是用户明确要求的规则，但前提是「人点过」。
	 * 未经确认的自动候选一律不要走这里，否则会批量污染 55 / 4332 条词条库。
	 *
	 * @param string $name     名称。
	 * @param string $taxonomy 分类法。
	 * @return int 词条 ID；空名或失败返回 0。
	 */
	public static function resolve_or_create_term( $name, $taxonomy ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return 0;
		}

		// 先幂等匹配，命中就不再新建。
		$term = self::resolve_term( $name, $taxonomy );
		if ( $term['id'] ) {
			return (int) $term['id'];
		}

		// 长度守卫：太长的多半是误切的描述碎片；单字符的也不是正经名字
		// （实测 humans 词条库已被 `1` 这类脏名污染过，别再往里加）。
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
		if ( $length < 2 || $length > 60 ) {
			return 0;
		}

		// 不传 slug，交给 WP 自己由 name 生成（中文名手造 slug 反而容易撞车）。
		$created = wp_insert_term( $name, $taxonomy );

		if ( is_wp_error( $created ) ) {
			// 同名（term_exists）或 slug 冲突：再按 name/slug 捞一次，捞到就复用。
			$existing = get_term_by( 'name', $name, $taxonomy );
			if ( ! $existing || is_wp_error( $existing ) ) {
				$existing = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
			}
			return ( $existing && ! is_wp_error( $existing ) ) ? (int) $existing->term_id : 0;
		}

		return isset( $created['term_id'] ) ? (int) $created['term_id'] : 0;
	}

	/**
	 * 归一化日期输入为 `Ymd`（ACF date_picker 的底库格式）。
	 *
	 * 实测：带点的 `2019.12.20` 直接交给 update_field() 会让 ACF 解析失败、回读变成**当天日期**；
	 * 底库必须是无点 `Ymd`（站上既有 8473 篇也都是这个形态）。
	 * 界面上显示/输入用 `yyyy.mm.dd`，提交后由这里统一收敛——归一化只放在服务端，前端只做美化显示。
	 *
	 * @param string $raw 原始输入（`2019.12.20` / `2019-12-20` / `20191220` 都接受）。
	 * @return string 合法返回 8 位 Ymd；非法返回空串（调用方据此跳过写入，绝不落脏值）。
	 */
	public static function normalize_date( $raw ) {
		$digits = preg_replace( '/[^0-9]/', '', (string) $raw );

		if ( 8 !== strlen( $digits ) ) {
			return '';
		}

		$year  = (int) substr( $digits, 0, 4 );
		$month = (int) substr( $digits, 4, 2 );
		$day   = (int) substr( $digits, 6, 2 );

		// checkdate 会挡掉 2019.13.45 / 2020.02.30 / 0000.00.00 这类非法组合。
		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return $digits;
	}

	/**
	 * `Ymd` → `Y.m.d`（界面显示格式，与 ACF 字段的 display_format 一致）。
	 *
	 * @param string $ymd 8 位日期串。
	 * @return string
	 */
	public static function format_date_display( $ymd ) {
		$digits = preg_replace( '/[^0-9]/', '', (string) $ymd );

		if ( 8 !== strlen( $digits ) ) {
			return '';
		}

		return substr( $digits, 0, 4 ) . '.' . substr( $digits, 4, 2 ) . '.' . substr( $digits, 6, 2 );
	}

	/**
	 * 粗略判断一个 token 像不像模特名。
	 *
	 * 只用于「是否**默认**勾选新建」。判错了用户点一下标签就能撤销，所以刻意用最朴素的规则：
	 * 长度合理 + 不含明显的描述类词。命中率不求高，只挡最扎眼的那些。
	 *
	 * @param string $token 候选词。
	 * @return bool
	 */
	private static function looks_like_a_name( $token ) {
		$token = trim( (string) $token );
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $token ) : strlen( $token );

		if ( $len < 2 || $len > 20 ) {
			return false;
		}

		$desc_words = array( '写真', '全集', '合集', '套图', '美图', '图片', '定妆', '美照', '预览', '无圣光', '模特', '泳装', '制服', '私拍' );
		foreach ( $desc_words as $word ) {
			if ( false !== mb_strpos( $token, $word ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * 按源目录指纹查已导入的图集。
	 *
	 * @param string $src_key md5 指纹。
	 * @return int 已存在的 post ID，无则 0。
	 */
	public static function find_existing_album( $src_key ) {
		if ( '' === $src_key ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'        => 'albums',
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => self::META_SRC_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $src_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => false,
			)
		);

		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * 建 albums 文章并写入元数据、词条、分类。
	 *
	 * @param array $payload 前端确认后的候选数据。
	 * @param int   $author_id 作者 ID。
	 * @return int|WP_Error post ID
	 */
	public static function create_album( $payload, $author_id = 0 ) {
		$src_dir = self::sanitize_abs_path( isset( $payload['abs_path'] ) ? $payload['abs_path'] : '' );

		if ( '' === $src_dir ) {
			return new WP_Error( 'w2p_album_src_invalid', __( 'Source directory is not valid.', 'wp-genius' ) );
		}

		$src_key = md5( $src_dir );

		// 原子互斥：add_option 落在 option_name 的唯一键上，插入失败即说明已有并发在建。
		// 只挡住「查重 + 建文章」这一小段（毫秒级），建完立刻释放，
		// 因此不会影响中断后续跑（resume 路径照常走）。
		// 没有它，两个标签页同时点导入会给同一目录各建一篇文章。
		$lock = 'w2p_album_lock_' . substr( $src_key, 0, 24 );
		if ( ! add_option( $lock, time(), '', 'no' ) ) {
			return new WP_Error( 'w2p_album_locked', __( 'Another import of this directory is starting. Please retry in a moment.', 'wp-genius' ) );
		}

		try {
			return self::create_album_persist( $src_dir, $src_key, $payload, $author_id );
		} finally {
			delete_option( $lock );
		}
	}

	/**
	 * create_album() 的临界区：查重、建文章、写元数据。
	 *
	 * @param string $src_dir   源目录绝对路径（已校验）。
	 * @param string $src_key   源目录指纹。
	 * @param array  $payload   前端确认后的候选数据。
	 * @param int    $author_id 作者 ID。
	 * @return int|WP_Error post ID
	 */
	private static function create_album_persist( $src_dir, $src_key, $payload, $author_id ) {
		$exist = self::find_existing_album( $src_key );
		if ( $exist ) {
			// 只有「已完成」才算重复；状态不是 done 说明上一次导入中断了，
			// 复用同一篇文章续跑剩余图片（import_image_batch 按 menu_order 跳过已入库的）。
			$state = (string) get_post_meta( $exist, self::META_STATE, true );

			if ( 'done' === $state ) {
				return new WP_Error(
					'w2p_album_duplicate',
					sprintf(
						/* translators: %d: existing album post ID. */
						__( 'This directory was already imported (album #%d).', 'wp-genius' ),
						$exist
					)
				);
			}

			// 续跑：把界面上可能改过的标题/编号/日期/工作室/模特一并写回去。
			// 不写的话这条分支会「早退」，用户在列表里改的东西被静默丢弃。
			$title = isset( $payload['title'] ) ? sanitize_text_field( (string) $payload['title'] ) : '';
			if ( '' !== $title && $title !== get_the_title( $exist ) ) {
				wp_update_post(
					array(
						'ID'         => $exist,
						'post_title' => $title,
					)
				);
			}

			self::apply_album_acf_fields( $exist, $payload );
			self::apply_album_terms( $exist, $payload );

			update_post_meta( $exist, self::META_STATE, 'importing' );
			return $exist;
		}

		// 目录存在性校验放在查重**之后**：导入成功后源图会被移进 `_已导入/`，
		// 原路径自然不存在 —— 那种情况该回「已经导入过了」，而不是含糊的「目录无效」。
		if ( ! is_dir( $src_dir ) ) {
			return new WP_Error( 'w2p_album_src_invalid', __( 'Source directory is not valid.', 'wp-genius' ) );
		}

		$images = self::collect_images( $src_dir );
		if ( empty( $images ) ) {
			return new WP_Error( 'w2p_album_no_images', __( 'No image files found in this directory.', 'wp-genius' ) );
		}

		// 触顶说明选错层级，拒绝建文章，避免生成一篇几万图的怪物。
		if ( count( $images ) >= self::MAX_COLLECT ) {
			return new WP_Error(
				'w2p_album_too_large',
				sprintf(
					/* translators: %d: image cap. */
					__( 'More than %d images found; this directory is too broad to be one photo set. Select a deeper directory.', 'wp-genius' ),
					self::MAX_COLLECT
				)
			);
		}

		$title = isset( $payload['title'] ) ? sanitize_text_field( (string) $payload['title'] ) : '';
		if ( '' === $title ) {
			$title = sanitize_text_field( wp_basename( $src_dir ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'albums',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $author_id ? (int) $author_id : get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_SRC_KEY, $src_key );
		update_post_meta( $post_id, self::META_STATE, 'importing' );
		// 记下原始源目录：收尾移动源图时用它兜底，也是「这条是从哪导进来的」的唯一线索。
		update_post_meta( $post_id, self::META_SRC_DIR, $src_dir );

		// ACF 字段 + 分类法关联（沿用站点既有的 ACF 存储形态：值 + _field_key）。
		self::apply_album_acf_fields( $post_id, $payload, count( $images ) );
		self::apply_album_terms( $post_id, $payload );

		return $post_id;
	}

	/**
	 * 写入图集的 ACF 字段（编号 / 发行日期 / 数量）。
	 *
	 * 抽出来供「新建」与「中断续跑」两条路径共用。日期归一化只在服务端做：
	 * 界面给的是 `yyyy.mm.dd`，底库必须是无点 `Ymd`（带点写进去 ACF 会解析失败、回读变成当天）；
	 * 非法日期直接跳过，绝不落脏值。
	 *
	 * @param int      $post_id     图集 post ID。
	 * @param array    $payload     界面确认后的数据。
	 * @param int|null $image_count 图片数；传 null 表示不覆盖已有的 album-count。
	 */
	private static function apply_album_acf_fields( $post_id, $payload, $image_count = null ) {
		$number = isset( $payload['number'] ) ? preg_replace( '/[^0-9]/', '', (string) $payload['number'] ) : '';
		$date   = self::normalize_date( isset( $payload['date'] ) ? $payload['date'] : '' );

		if ( '' !== $number ) {
			update_field( 'album-numb', (int) $number, $post_id );
		}
		if ( '' !== $date ) {
			update_field( 'album-pub', $date, $post_id );
		}
		if ( null !== $image_count ) {
			update_field( 'album-count', (int) $image_count, $post_id );
		}
	}

	/**
	 * 写入图集的分类法关联（工作室 / 模特 / 默认分类）。
	 *
	 * 单独抽出来是为了让「中断续跑」那条分支也能把界面上改过的值写回去——
	 * 否则续跑会在写完文章前早退，用户在列表里改的工作室与模特被静默丢弃。
	 *
	 * @param int   $post_id 图集 post ID。
	 * @param array $payload 界面确认后的数据。
	 */
	private static function apply_album_terms( $post_id, $payload ) {
		// 工作室（photostudio）——用户在界面上确认过的值：命中即用，不中即新建。
		$studio_name = isset( $payload['studio'] ) ? (string) $payload['studio'] : '';
		if ( '' !== trim( $studio_name ) ) {
			$studio_id = self::resolve_or_create_term( $studio_name, 'photostudio' );
			if ( $studio_id ) {
				wp_set_object_terms( $post_id, array( $studio_id ), 'photostudio', false );
			}
		}

		// 模特（humans）——只处理界面上「留用」的标签：
		// 命中既有词条就关联；未命中的**只有用户点标签确认过才会新建**（前端只把这些名字放进 human_names）。
		// 未确认的自动候选根本不会出现在这里，所以不会批量污染 4332 条 humans 词条库。
		$human_ids = array();
		if ( ! empty( $payload['human_names'] ) && is_array( $payload['human_names'] ) ) {
			foreach ( $payload['human_names'] as $human_name ) {
				$human_id = self::resolve_or_create_term( $human_name, 'humans' );
				if ( $human_id ) {
					$human_ids[] = $human_id;
				}
			}
			$human_ids = array_values( array_unique( array_filter( $human_ids ) ) );
		}

		// 默认分类（用户决策：默认挂 Adult Albums）。
		$default_cat = get_term_by( 'slug', 'adult-albums', 'category' );
		if ( ! $default_cat || is_wp_error( $default_cat ) ) {
			$default_cat = get_term_by( 'name', 'Adult Albums', 'category' );
		}
		$cat_ids = ( $default_cat && ! is_wp_error( $default_cat ) ) ? array( (int) $default_cat->term_id ) : array();

		wp_set_object_terms( $post_id, $cat_ids, 'category', false );
		if ( ! empty( $human_ids ) ) {
			wp_set_object_terms( $post_id, $human_ids, 'humans', false );
		}
	}

	/**
	 * 单批图片入库 —— 走 WP 标准入库路径（每张：拷进 uploads → wp_insert_attachment → 生成元数据）。
	 *
	 * 三条设计约束：
	 *  ① 走标准路径而非 smart-aui：后者在 `add_to_media_library()` 里**刻意跳过**
	 *     `wp_generate_attachment_metadata()`，而 advmo 的自动卸载只挂在这个 filter 上 ——
	 *     跳过它就永远没有缩略图、也永远不会卸载到 MinIO，与站上 8473 篇既有图集的形态不符。
	 *  ② 幂等键 = `META_SRC_FILE`（源图绝对路径），**不是**目标文件名。重跑时上一轮的文件还在磁盘上，
	 *     `wp_unique_filename()` 会返回 `xxx-1.webp`，按目标名查必然落空 → 重复入库。
	 *  ③ 正文用 `<img>` 去重兜底：客户端 retry / FPM 超时重发同一 offset 时，不再往正文追加重复标签。
	 *
	 * 有了 ②③，「重发同一批」最坏只是白跑一遍，绝不会产生重复 —— 这是前端敢于无脑重试的前提。
	 *
	 * @param int    $album_id 图集 post ID。
	 * @param string $src_dir  源目录。
	 * @param int    $offset   起始下标。
	 * @param int    $limit    本批张数。
	 * @return array|WP_Error
	 */
	public static function import_image_batch( $album_id, $src_dir, $offset, $limit ) {
		$batch_started = microtime( true );

		$album_id = (int) $album_id;
		if ( ! $album_id || 'albums' !== get_post_type( $album_id ) ) {
			return new WP_Error( 'w2p_album_invalid', __( 'Invalid album ID.', 'wp-genius' ) );
		}

		$src_dir = self::sanitize_abs_path( $src_dir );

		// 源目录没了（导完被移进 _已导入/、或用户手工删了）：没法再收图。
		// 若这一篇已经有附件，就当作「已经导完」直接收尾 —— 而不是把整行标成失败。
		if ( '' === $src_dir || ! is_dir( $src_dir ) ) {
			$existing = self::count_attachments( $album_id );

			if ( $existing > 0 ) {
				$finalized = self::finalize_album( $album_id );

				return array(
					'album_id'    => $album_id,
					'total'       => $existing,
					'processed'   => 0,
					'next_offset' => $existing,
					'done'        => true,
					'failed'      => array(),
					'finalized'   => is_array( $finalized ) ? $finalized : null,
				);
			}

			return new WP_Error( 'w2p_album_src_invalid', __( 'Source directory is not valid.', 'wp-genius' ) );
		}

		$images = self::collect_images( $src_dir );
		$total  = count( $images );
		$offset = max( 0, (int) $offset );
		$limit  = max( 1, min( 100, (int) $limit ) );

		$slice     = array_slice( $images, $offset, $limit );
		$failed    = array();
		$img_lines = array();

		// 读取正文一次，用于检测本批 attachment 是否已被 append（客户端 retry / FPM 超时重发同一批的去重）。
		// 只靠 import_one_image 的幂等还不够：那只能保证不重复入库，
		// 命中已存在的附件后若照样 append，正文仍会出现重复 `<img>`。
		$content = (string) get_post_field( 'post_content', $album_id );

		// 每张图独立处理：拷源图到 uploads/YYYY/MM/ → wp_insert_attachment → wp_generate_attachment_metadata。
		// 后者会触发 wp_generate_attachment_metadata filter → advmo 自动卸载到 MinIO。
		// $i 是**篇内绝对序号**（array_slice 会重排键，所以用 $offset + $i），写进 menu_order —— 站点约定 0 起递增。
		foreach ( $slice as $i => $source_path ) {
			$result = self::import_one_image( $source_path, $album_id, $offset + (int) $i );

			if ( is_wp_error( $result ) ) {
				$failed[] = array(
					'file'    => wp_basename( $source_path ),
					'message' => $result->get_error_message(),
				);
				continue;
			}

			$attachment_id = (int) $result['attachment_id'];

			// 幂等：attachment 已存在于正文（retry 时常见） → 不再 append。
			// 用 `wp-image-{id}` 类名 + 末尾空格，确保不误判 `wp-image-12` 撞 `wp-image-123`。
			if ( false !== strpos( $content, 'wp-image-' . $attachment_id . ' ' ) ) {
				continue;
			}

			$img_lines[] = sprintf(
				'<img class="wp-image-%d size-full" src="%s" />',
				$attachment_id,
				esc_url( $result['url'] )
			);
			// 同步更新本批检测用的 content 视图，下一次循环的 strpos 也能命中本张已写入的。
			$content .= "\n" . end( $img_lines );
		}

		// 追加正文（主动写 wp-image-<id> 类，finalize_album 的 wp-image-(\d+) 正则继续命中）。
		if ( ! empty( $img_lines ) ) {
			$content = (string) get_post_field( 'post_content', $album_id );
			wp_update_post(
				array(
					'ID'           => $album_id,
					'post_content' => trim( $content . "\n" . implode( "\n", $img_lines ) ),
				)
			);
		}

		$next   = $offset + count( $slice );
		$is_end = ( $next >= $total );

		update_post_meta( $album_id, self::META_OFFSET, $next );

		$finalized = null;
		if ( $is_end ) {
			$finalized = self::finalize_album( $album_id, $src_dir );
		}

		$elapsed_ms  = (int) round( ( microtime( true ) - $batch_started ) * 1000 );
		$batch_count = count( $slice );

		return array(
			'album_id'    => $album_id,
			'total'       => $total,
			'processed'   => count( $img_lines ),
			'next_offset' => $next,
			'done'        => $is_end,
			'failed'      => $failed,
			'finalized'   => is_array( $finalized ) ? $finalized : null,
			'timing'      => array(
				'elapsed_ms'   => $elapsed_ms,
				'per_image_ms' => $batch_count > 0 ? (int) round( $elapsed_ms / $batch_count ) : 0,
			),
		);
	}

	/**
	 * 按「父图集 + 源图绝对路径」查附件是否已入库 —— 单张图的幂等键。
	 *
	 * 为什么不用目标文件名：目标名要过 `wp_unique_filename()`，它读磁盘现状。
	 * 重跑时上一轮拷进去的文件还在 → 返回 `xxx-1.webp`，键变了、查不中 → 重复入库 + 正文重复 `<img>`。
	 * 源路径与磁盘状态无关，重跑必然命中。
	 *
	 * @param int    $album_id    图集 post ID。
	 * @param string $source_path 源图绝对路径。
	 * @return int 已存在则返回 attachment ID，否则 0。
	 */
	private static function find_attachment_by_source( $album_id, $source_path ) {
		global $wpdb;

		$album_id = (int) $album_id;
		if ( ! $album_id || '' === $source_path ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				 WHERE p.post_type = 'attachment'
				   AND p.post_parent = %d
				   AND pm.meta_key = %s
				   AND pm.meta_value = %s
				 LIMIT 1",
				$album_id,
				self::META_SRC_FILE,
				$source_path
			)
		);
	}

	/**
	 * 把一张源图拷进 uploads 并标准入库。
	 *
	 * 走 WP 标准入库路径（wp_insert_attachment + wp_generate_attachment_metadata）——
	 * advmo 的自动卸载**只**挂在这里，不走这条路径图片永远不会被卸载到 MinIO。
	 *
	 * ponytail: 文件名冲突直接走 WP 的 `wp_unique_filename`（不自己写重名计数器）。
	 * ponytail: 重跑幂等靠 `find_attachment_by_source()` —— 键是**源路径**，与磁盘状态无关。
	 *
	 * @param string $source_path 源图绝对路径。
	 * @param int    $album_id    目标图集 post ID（attachment 的 post_parent）。
	 * @param int    $order       篇内序号，写进 menu_order（站点约定 0 起递增）。
	 * @return array{attachment_id:int,url:string,reused:bool}|WP_Error
	 */
	private static function import_one_image( $source_path, $album_id, $order = 0 ) {
		if ( ! is_readable( $source_path ) ) {
			return new WP_Error( 'w2p_album_unreadable', __( 'Source file not readable.', 'wp-genius' ) );
		}

		$album_id = (int) $album_id;

		// 幂等第 1 步，早于任何磁盘/DB 写入：同一篇图集下源路径唯一 → 已导入就直接复用。
		// 这是「客户端重发同一批」安全的根据 —— 最坏白跑一遍，不会重复入库。
		$existing_id = self::find_attachment_by_source( $album_id, $source_path );
		if ( $existing_id ) {
			return array(
				'attachment_id' => $existing_id,
				'url'           => (string) wp_get_attachment_url( $existing_id ),
				'reused'        => true,
			);
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'w2p_album_upload_dir', $upload['error'] );
		}

		$pathinfo = pathinfo( $source_path );
		$ext      = strtolower( $pathinfo['extension'] ?? '' );
		if ( ! in_array( $ext, self::IMAGE_EXT, true ) ) {
			return new WP_Error( 'w2p_album_ext', __( 'Unsupported image extension.', 'wp-genius' ) );
		}

		// 文件命名：源 stem + -YYYY-MM-DD 后缀（站点既有形态），再交给 wp_unique_filename 解决冲突。
		$filename = sprintf(
			'%s-%s.%s',
			sanitize_file_name( $pathinfo['filename'] ),
			date( 'Y-m-d' ),
			$ext
		);

		$target_dir = trailingslashit( $upload['basedir'] ) . date( 'Y/m' );
		if ( ! is_dir( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$unique      = wp_unique_filename( $target_dir, $filename );
		$target_full = trailingslashit( $target_dir ) . $unique;

		if ( ! @copy( $source_path, $target_full ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- 拷贝失败时返回 false，下面构造 WP_Error。
			return new WP_Error( 'w2p_album_copy_failed', __( 'Failed to copy source file.', 'wp-genius' ) );
		}

		$filetype   = wp_check_filetype( $target_full );
		$attachment = array(
			'guid'           => trailingslashit( $upload['baseurl'] ) . date( 'Y/m' ) . '/' . $unique,
			'post_mime_type' => $filetype['type'] ?? 'image/' . $ext,
			'post_title'     => sanitize_file_name( $pathinfo['filename'] ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			// 站点既有 8473 篇图集的约定：attachment 的 menu_order 就是篇内序号（0 起递增）。
			// 不写会让 orderby=menu_order 退化成 MySQL 隐式顺序 —— 封面与正文顺序都变得不确定。
			'menu_order'     => (int) $order,
		);

		$attachment_id = wp_insert_attachment( $attachment, $target_full, $album_id );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $target_full );
			return $attachment_id;
		}

		// 幂等键**在生成缩略图之前**落库：即便下一步元数据生成失败/超时，
		// 重跑也会复用这张附件，不会留下第二个孤儿副本。
		update_post_meta( $attachment_id, self::META_SRC_FILE, $source_path );

		// 触发 wp_generate_attachment_metadata filter → advmo 在这里把图丢到 MinIO。
		$metadata = wp_generate_attachment_metadata( $attachment_id, $target_full );
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return array(
			'attachment_id' => (int) $attachment_id,
			'url'           => (string) wp_get_attachment_url( $attachment_id ),
			'reused'        => false,
		);
	}

	/**
	 * 统计某篇图集下的附件数（一条 SQL，不加载对象）。
	 *
	 * @param int $album_id 图集 post ID。
	 * @return int
	 */
	private static function count_attachments( $album_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'attachment'",
				(int) $album_id
			)
		);
	}

	/**
	 * 收尾：设封面 / 标记完成 / 移动源目录。
	 *
	 * 封面取**正文里第一张图**对应的附件 —— append 时主动写了 `<img class="wp-image-<id> size-full">`，
	 * 正则直接命中，与站点既有形态一致；兜底再按 `menu_order ASC` 取第一张
	 * （`import_one_image()` 现在会写篇内序号，所以这个兜底是确定的）。
	 *
	 * @param int    $album_id 图集 post ID。
	 * @param string $src_dir  源目录；读不到时回退到创建时记录的路径。
	 * @return array|WP_Error
	 */
	public static function finalize_album( $album_id, $src_dir = '' ) {
		$album_id = (int) $album_id;
		if ( ! $album_id || 'albums' !== get_post_type( $album_id ) ) {
			return new WP_Error( 'w2p_album_invalid', __( 'Invalid album ID.', 'wp-genius' ) );
		}

		$content  = (string) get_post_field( 'post_content', $album_id );
		$cover_id = 0;

		// append 时已经写了 `wp-image-<id>` 类，正则直接取。
		if ( preg_match( '/wp-image-(\d+)/', $content, $m ) ) {
			$cover_id = (int) $m[1];
		}

		if ( ! $cover_id ) {
			$first    = get_posts(
				array(
					'post_type'   => 'attachment',
					'post_parent' => $album_id,
					'post_status' => 'inherit',
					'numberposts' => 1,
					'fields'      => 'ids',
					'orderby'     => 'menu_order',
					'order'       => 'ASC',
				)
			);
			$cover_id = ! empty( $first ) ? (int) $first[0] : 0;
		}

		if ( ! $cover_id ) {
			update_post_meta( $album_id, self::META_STATE, 'failed' );

			return new WP_Error( 'w2p_album_no_attachment', __( 'No images were imported.', 'wp-genius' ) );
		}

		set_post_thumbnail( $album_id, $cover_id );
		update_post_meta( $album_id, self::META_STATE, 'done' );

		// 收尾成功**之后**才移动源图 —— 顺序是关键：移动失败绝不能把「已导入」这个事实回滚掉。
		// 移动是 fail-soft 的，失败只记一行日志 + 把 message 带回去，文章状态不受影响。
		$move_src = ( '' !== $src_dir ) ? $src_dir : (string) get_post_meta( $album_id, self::META_SRC_DIR, true );
		$move     = array(
			'moved'   => false,
			'path'    => '',
			'message' => '',
		);

		if ( '' !== $move_src ) {
			$move = self::move_set_to_done( $move_src );

			if ( $move['moved'] ) {
				update_post_meta( $album_id, self::META_SRC_MOVED, $move['path'] );
			} elseif ( class_exists( 'W2P_Logger' ) ) {
				W2P_Logger::warning( 'Album importer: could not move source folder ' . $move_src . ' — ' . $move['message'], 'album-importer' );
			}
		}

		return array(
			'album_id'    => $album_id,
			'attachments' => self::count_attachments( $album_id ),
			'cover_id'    => $cover_id,
			'edit_link'   => get_edit_post_link( $album_id, 'raw' ),
			'permalink'   => get_permalink( $album_id ),
			'move'        => $move,
		);
	}

	/**
	 * 列出目录下的一层子目录（跳过隐藏项与符号链接），自然序。
	 *
	 * @param string $dir 目录绝对路径。
	 * @return array 绝对路径数组。
	 */
	public static function list_subdirs( $dir ) {
		$dir = self::sanitize_abs_path( $dir );
		if ( '' === $dir ) {
			return array();
		}

		$handle = @opendir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- 权限不足时返回空列表。
		if ( false === $handle ) {
			return array();
		}

		$dirs = array();
		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( '.' === $entry || '..' === $entry || '.' === $entry[0] || '.DS_Store' === $entry ) {
				continue;
			}

			// 已导入的套图会被移进这个目录——在这里统一过滤，
			// 浏览 / 扫描 / 层级判定三条路都走本方法，漏一处就会把整套已导图当新候选重扫一遍。
			if ( self::DONE_DIR_NAME === $entry ) {
				continue;
			}

			$path = trailingslashit( $dir ) . $entry;

			if ( is_link( $path ) || ! is_dir( $path ) ) {
				continue;
			}

			if ( ! is_readable( $path ) ) {
				continue;
			}

			$dirs[] = $path;
		}
		closedir( $handle );

		usort( $dirs, array( __CLASS__, 'natural_compare' ) );

		return $dirs;
	}

	/**
	 * 丢弃一篇导入的图集：删掉文章 + 它的全部附件（连同 MinIO 对象一并清掉）+ 本模组写入的元数据。
	 *
	 * 源目录**一律不动** —— 用户之后可以重新扫描导入。
	 * 只允许丢弃本模组导入的（必须带 src_key），不会误删手工建的图集。
	 *
	 * @param int $album_id 图集 post ID。
	 * @return array|WP_Error {deleted_attachments:int}
	 */
	public static function discard_album( $album_id ) {
		$album_id = (int) $album_id;

		if ( ! $album_id || 'albums' !== get_post_type( $album_id ) ) {
			return new WP_Error( 'w2p_album_invalid', __( 'Invalid album ID.', 'wp-genius' ) );
		}

		if ( '' === (string) get_post_meta( $album_id, self::META_SRC_KEY, true ) ) {
			return new WP_Error( 'w2p_album_not_imported', __( 'This album was not created by the importer, so it will not be discarded here.', 'wp-genius' ) );
		}

		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_parent' => $album_id,
				'post_status' => 'inherit',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		$deleted = 0;
		foreach ( $attachments as $attachment_id ) {
			// force=true：同时清掉云上对象（advmo 的删除观察者会跟着走）。
			if ( wp_delete_attachment( (int) $attachment_id, true ) ) {
				++$deleted;
			}
		}

		wp_delete_post( $album_id, true );

		// L2 不再使用 staging 中转目录 —— 图直接拷进 uploads/YYYY/MM/，删附件时 wp_delete_attachment(..., true)
		// 已经把磁盘上的副本一并清掉，没有别的暂存残留要处理。

		return array( 'deleted_attachments' => $deleted );
	}

	/**
	 * 把已完整导入的源目录移到同级的 `_已导入/` 下。
	 *
	 * 三条硬约束（都来自实测 + 审查）：
	 *  ① 只在整套导入成功后调用，且必须在写入 state=done **之后** —— 移动失败绝不回滚导入状态；
	 *  ② 只做「移动」，从不删除；任何失败路径都让源目录原样留在原地；
	 *  ③ 移动是 fail-soft 的：失败只带一句 message 回去，不影响文章状态。
	 *
	 * 同一挂载内 rename 是原子操作；跨文件系统时 rename 会整体失败而不会留下半个目录。
	 *
	 * @param string $src_dir 源目录绝对路径。
	 * @return array{moved:bool,path:string,message:string}
	 */
	public static function move_set_to_done( $src_dir ) {
		$src_dir = self::sanitize_abs_path( $src_dir );

		if ( '' === $src_dir || ! is_dir( $src_dir ) ) {
			return array(
				'moved'   => false,
				'path'    => '',
				'message' => __( 'Source folder no longer exists; nothing to move.', 'wp-genius' ),
			);
		}

		$parent = dirname( $src_dir );

		// 已经在 _已导入 里了，别再套一层。
		if ( self::DONE_DIR_NAME === wp_basename( $parent ) ) {
			return array(
				'moved'   => true,
				'path'    => $src_dir,
				'message' => '',
			);
		}

		$done_dir = $parent . '/' . self::DONE_DIR_NAME;

		if ( ! is_dir( $done_dir ) && ! @mkdir( $done_dir, 0755, true ) && ! is_dir( $done_dir ) ) {
			return array(
				'moved'   => false,
				'path'    => '',
				'message' => __( 'Could not create the archive folder (permission denied?).', 'wp-genius' ),
			);
		}

		$name   = wp_basename( $src_dir );
		$target = $done_dir . '/' . $name;
		$suffix = 1;

		while ( file_exists( $target ) ) {
			if ( $suffix > 500 ) {
				return array(
					'moved'   => false,
					'path'    => '',
					'message' => __( 'Too many name conflicts in the archive folder.', 'wp-genius' ),
				);
			}
			$target = $done_dir . '/' . $name . ' (' . $suffix . ')';
			++$suffix;
		}

		if ( ! @rename( $src_dir, $target ) ) {
			return array(
				'moved'   => false,
				'path'    => '',
				'message' => __( 'Could not move the source folder (permission denied?).', 'wp-genius' ),
			);
		}

		return array(
			'moved'   => true,
			'path'    => $target,
			'message' => '',
		);
	}

	/**
	 * 规范化绝对路径：去 NUL/控制字符、去首尾空白、去末尾斜杠。
	 * **不做** realpath（目录可能尚不存在）。
	 *
	 * ⚠️ **不要改用 `sanitize_text_field()` 来净化路径。** 它会把连续空白折叠成一个空格
	 * （`_sanitize_text_fields()` 里的 `preg_replace( '/[\r\n\t ]+/', ' ', … )`），
	 * 而磁盘上的目录名真的可能含连续空格 —— 实测
	 * `[XIUREN秀人网] 2021 2022  陆萱萱 7800P 100套`（"2021" 与 "2022" 之间是**两个**空格）
	 * 被折叠后路径就对不上磁盘，`is_dir()` 直接为假，前端只看到「Directory is not readable.」，
	 * 而目录本身完全正常。
	 * 路径的安全由「必须是绝对路径 + 禁 `..`」加上下游 `is_within()` 的 realpath 前缀校验负责，
	 * **不靠改写路径内容**。
	 *
	 * @param string $path 输入路径。
	 * @return string
	 */
	public static function sanitize_abs_path( $path ) {
		// NUL 会让 PHP 8 的 is_dir() / realpath() 抛 ValueError；其余控制字符出现在路径里也无意义。
		$path = preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $path );
		$path = trim( (string) $path );
		if ( '' === $path ) {
			return '';
		}

		// 禁止相对路径与 ".." 穿越。
		if ( '/' !== substr( $path, 0, 1 ) ) {
			return '';
		}
		if ( false !== strpos( $path, '..' ) ) {
			return '';
		}

		return rtrim( $path, '/' );
	}

	/**
	 * 校验给定目录是否位于只读挂载根之内（防路径穿越逃逸）。
	 *
	 * @param string $dir      待校验目录。
	 * @param string $base_dir 允许的根目录。
	 * @return bool
	 */
	public static function is_within( $dir, $base_dir ) {
		$real_dir  = realpath( $dir );
		$real_base = realpath( $base_dir );

		if ( false === $real_dir || false === $real_base ) {
			return false;
		}

		$prefix = rtrim( $real_base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		return 0 === strpos( $real_dir . DIRECTORY_SEPARATOR, $prefix );
	}
}
