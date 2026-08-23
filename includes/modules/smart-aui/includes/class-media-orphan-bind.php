<?php
/**
 * Smart AUI — 孤儿媒体实时绑定（save_post 反向关联 + URL 回写 + CLI 分批绑定）
 *
 * 迁移自子主题 Impreza-child functions.php [孤儿绑定] 段（行 142-262）
 * 与 inc/media-perf/media-bind.php（wp media-bind-orphans 命令）。
 *
 * 原实现依赖已随 media-engine 重构移除的 MediaEngineOrphanService 与
 * MediaEngineUrlRewriteService::rewrite_content_for_posts，本类将 adopt_orphans
 * 与「复数文章 URL 回写」逻辑自包含移植，不再依赖已删除的类（零外部依赖）。
 *
 * 功能：
 *   1. save_post 实时绑定：保存文章时从正文提取 wp-image-{ID} / data-id /
 *      data-attachment-id 引用的媒体 ID，把仍为孤儿（post_parent=0）的附件
 *      反向绑定到当前文章（先引用先占有），并顺带把正文里本地
 *      /wp-content/uploads/ 路径回写为 bucket /wp-media/ 路径。
 *   2. wp media-bind-orphans：按 offset/limit 分批遍历文章执行同样的绑定
 *      与回写（适合全站批量执行，--dry-run 预览，--post-type 限定类型）。
 *
 * 并存策略：回调运行时检测子主题同名函数（w2p_realtime_bind_orphans /
 * w2p_extract_media_ids_for_bind / w2p_ensure_orphan_service）存在即让位；
 * CLI 注册以 function_exists + method_exists('WP_CLI','has_command') 双保险。
 *
 * 相对原实现的修正：
 *   - 回写目标 URL 用 wp_get_attachment_url()（附件真实 URL），不再硬编码
 *     强制转 .webp——本站 bucket 保留原扩展名（如 .gif），旧逻辑会产出 404；
 *   - CLI / save_post 共用同一 adopt 逻辑，去重（原 media-bind.php 重复定义
 *     了两个辅助函数）；无全局函数、无全局常量。
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 孤儿媒体实时绑定类。
 */
class W2P_SmartAUI_Media_Orphan_Bind {

	/**
	 * save_post 回调优先级（与子主题一致）。
	 *
	 * @var int
	 */
	const SAVE_POST_PRIORITY = 20;

	/**
	 * CLI 分批上限（与子主题一致，防超大批次拖垮内存/Redis 连接）。
	 *
	 * @var int
	 */
	const CLI_BATCH_MAX = 2000;

	/**
	 * 猜测文章内旧路径时尝试的候选扩展名（含转 webp 前/后的原始扩展）。
	 *
	 * @var string[]
	 */
	const OLD_EXT_CANDIDATES = array( 'webp', 'jpg', 'jpeg', 'png', 'gif' );

	/**
	 * 防重入标志：adopt 内部 wp_update_post 触发 save_post 时阻止递归。
	 *
	 * @var bool
	 */
	private $processing = false;

	/**
	 * 构造器：挂载 save_post 实时绑定与 CLI 注册钩子。
	 */
	public function __construct() {
		add_action( 'save_post', array( $this, 'realtime_bind_orphans' ), self::SAVE_POST_PRIORITY, 2 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'cli_init', array( $this, 'register_cli_commands' ) );
		}
	}

	/**
	 * 文章保存时，把正文引用的孤儿媒体反绑到当前文章（先引用先占有），
	 * 并触发 URL 回写（本地 /wp-content/uploads/ → bucket /wp-media/）。
	 *
	 * 并存让位：子主题 w2p_realtime_bind_orphans 已声明时由子主题接管。
	 *
	 * @param int      $post_id 文章 ID。
	 * @param \WP_Post $post    文章对象。
	 * @return void
	 */
	public function realtime_bind_orphans( $post_id, $post ) {
		// 并存期让位：子主题同名回调存在时由子主题生效。
		if ( function_exists( 'w2p_realtime_bind_orphans' ) ) {
			return;
		}

		// 跳过自动保存 / 修订 / 媒体自身保存 / 草稿态，避免无意义处理。
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'attachment' === $post->post_type ) {
			return;
		}
		if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
			return;
		}

		// 防重入：adopt 内部 wp_update_post 会触发其他文章的 save_post。
		if ( $this->processing ) {
			return;
		}

		$content = isset( $post->post_content ) ? $post->post_content : '';
		if ( '' === $content ) {
			return;
		}

		$ids = $this->extract_media_ids( $content );
		if ( empty( $ids ) ) {
			return;
		}

		// 只收集「当前是孤儿」的媒体（post_parent=0），先引用先占有。
		$pairs = array();
		foreach ( $ids as $mid ) {
			$att = get_post( $mid );
			if ( ! $att || 'attachment' !== $att->post_type ) {
				continue;
			}
			if ( 0 !== (int) $att->post_parent ) {
				continue; // 已被其他文章占有的跳过。
			}
			$pairs[] = array( 'orphan_id' => $mid, 'post_id' => $post_id );
		}
		if ( empty( $pairs ) ) {
			return;
		}

		$this->processing = true;
		try {
			$this->adopt_orphans( $pairs );
		} finally {
			$this->processing = false;
		}
	}

	/**
	 * 注册 wp media-bind-orphans 命令。
	 *
	 * 并存守卫：子主题函数存在（其 media-bind.php 已注册同名命令）或
	 * WP_CLI::has_command 命中时跳过自身注册（has_command 非所有版本都有，
	 * 以 method_exists 守卫），避免 add_command 同名抛异常中断所有 wp 命令。
	 *
	 * @return void
	 */
	public function register_cli_commands() {
		if ( function_exists( 'w2p_extract_media_ids_for_bind' )
			|| function_exists( 'w2p_ensure_orphan_service' )
			|| ( method_exists( 'WP_CLI', 'has_command' ) && WP_CLI::has_command( 'media-bind-orphans' ) )
		) {
			WP_CLI::warning( __( '检测到子主题同名 CLI 命令（media-bind-orphans），插件已跳过自身注册。请删除子主题 functions.php 的 [孤儿绑定] 段及对 inc/media-perf/media-bind.php 的引用，随后插件将自动接管。', 'wp-genius' ) );
			return;
		}

		WP_CLI::add_command( 'media-bind-orphans', array( $this, 'cli_bind_orphans' ) );
	}

	/**
	 * wp media-bind-orphans —— 分批遍历文章侧，把正文引用的孤儿媒体双向绑定。
	 *
	 * 按 ID 升序分页，保证「先引用先占有」确定性（小 ID 文章先处理）。
	 *
	 * @param array $args       位置参数。
	 * @param array $assoc_args 关联参数：--limit / --offset / --post-type / --dry-run。
	 * @return void
	 */
	public function cli_bind_orphans( $args, $assoc_args ) {
		$limit     = isset( $assoc_args['limit'] ) ? max( 1, min( self::CLI_BATCH_MAX, (int) $assoc_args['limit'] ) ) : 500;
		$offset    = isset( $assoc_args['offset'] ) ? max( 0, (int) $assoc_args['offset'] ) : 0;
		$post_type = isset( $assoc_args['post-type'] ) ? sanitize_key( $assoc_args['post-type'] ) : '';
		$dry_run   = isset( $assoc_args['dry-run'] );

		global $wpdb;
		if ( '' !== $post_type ) {
			$pt_sql = $wpdb->prepare( ' AND post_type = %s', $post_type );
		} else {
			$pt_sql = " AND post_type NOT IN ('attachment','revision')";
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status != 'trash' {$pt_sql} ORDER BY ID ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		if ( empty( $ids ) ) {
			/* translators: %d: 偏移量。 */
			WP_CLI::success( sprintf( __( '没有更多文章（offset=%d）', 'wp-genius' ), $offset ) );
			return;
		}

		$pairs = array();
		foreach ( $ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post || empty( $post->post_content ) ) {
				continue;
			}
			$mids = $this->extract_media_ids( $post->post_content );
			foreach ( $mids as $mid ) {
				$att = get_post( $mid );
				if ( ! $att || 'attachment' !== $att->post_type ) {
					continue;
				}
				if ( 0 !== (int) $att->post_parent ) {
					continue; // 先引用先占有。
				}
				$pairs[] = array( 'orphan_id' => $mid, 'post_id' => $pid );
			}
		}

		if ( empty( $pairs ) ) {
			/* translators: 1: 本批文章数 2: 偏移量。 */
			WP_CLI::log( sprintf( __( '本批 %1$d 篇文章，无新孤儿可绑定（offset=%2$d）', 'wp-genius' ), count( $ids ), $offset ) );
			return;
		}

		if ( $dry_run ) {
			/* translators: 1: 待绑定孤儿数 2: 本批文章数 3: 偏移量。 */
			WP_CLI::log( sprintf( __( 'DRY-RUN：本批将绑定 %1$d 个孤儿（来自 %2$d 篇文章，offset=%3$d）', 'wp-genius' ), count( $pairs ), count( $ids ), $offset ) );
			foreach ( $pairs as $p ) {
				/* translators: 1: 孤儿媒体 ID 2: 文章 ID。 */
				WP_CLI::log( sprintf( __( '  orphan %1$d -> post %2$d', 'wp-genius' ), $p['orphan_id'], $p['post_id'] ) );
			}
			return;
		}

		$this->processing = true;
		try {
			$result = $this->adopt_orphans( $pairs );
		} finally {
			$this->processing = false;
		}

		/* translators: 1: 成功数 2: 失败数 3: 偏移量。 */
		WP_CLI::log( sprintf( __( 'adopted=%1$d failed=%2$d (batch offset=%3$d)', 'wp-genius' ), $result['adopted'], $result['failed'], $offset ) );
	}

	/**
	 * 收养孤儿附件：分配父级文章并在所有引用文章中回写 uploads → bucket URL。
	 *
	 * 自包含移植自旧 MediaEngineOrphanService::adopt_orphans()（该服务已随
	 * media-engine 重构移除）。每条记录 [orphan_id => int, post_id => int]；
	 * 同一孤儿可被多篇文章引用：post_parent 取首条引用，URL 回写作用于全部引用。
	 *
	 * @param array $pairs 孤儿绑定对列表。
	 * @return array { adopted:int, failed:int, results:array }
	 */
	public function adopt_orphans( $pairs ) {
		$pairs = is_array( $pairs ) ? $pairs : array();

		// 按 orphan_id 分组，保持插入顺序。
		$grouped = array();
		foreach ( $pairs as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}
			$orphan_id = absint( isset( $pair['orphan_id'] ) ? $pair['orphan_id'] : 0 );
			$post_id   = absint( isset( $pair['post_id'] ) ? $pair['post_id'] : 0 );
			if ( ! $orphan_id || ! $post_id ) {
				continue;
			}
			if ( ! isset( $grouped[ $orphan_id ] ) ) {
				$grouped[ $orphan_id ] = array();
			}
			if ( ! in_array( $post_id, $grouped[ $orphan_id ], true ) ) {
				$grouped[ $orphan_id ][] = $post_id;
			}
		}

		if ( empty( $grouped ) ) {
			return array(
				'adopted' => 0,
				'failed'  => 0,
				'results' => array(),
			);
		}

		$adopted = 0;
		$failed  = 0;
		$results = array();

		foreach ( $grouped as $orphan_id => $post_ids ) {
			$result = array(
				'orphan_id' => $orphan_id,
				'post_ids'  => $post_ids,
				'success'   => false,
				'error'     => '',
				'parent'    => 0,
				'rewrite'   => null,
			);

			$att = get_post( $orphan_id );
			if ( ! $att || 'attachment' !== $att->post_type ) {
				$result['error'] = 'not_attachment';
				$results[]       = $result;
				++$failed;
				continue;
			}

			// 安全：只收养仍为孤儿的附件。
			if ( 0 !== (int) $att->post_parent ) {
				$result['error'] = 'parent_changed';
				$results[]       = $result;
				++$failed;
				continue;
			}

			// 1. 建立父子关系（首篇引用文章获胜）。
			$parent_id = $post_ids[0];
			wp_update_post(
				array(
					'ID'          => $orphan_id,
					'post_parent' => $parent_id,
				)
			);
			wp_cache_delete( $orphan_id, 'posts' );
			wp_cache_delete( $orphan_id, 'post_meta' );
			$result['parent'] = $parent_id;

			// 2. 在全部引用文章中回写 uploads → bucket URL。
			$result['rewrite'] = $this->rewrite_orphan_urls( $orphan_id, $post_ids );

			// 3. 清除扫描重试标记。
			delete_post_meta( $orphan_id, '_w2p_rewrite_pending' );
			delete_post_meta( $orphan_id, '_w2p_media_failed' );

			$result['success'] = true;
			$results[]         = $result;
			++$adopted;
		}

		return array(
			'adopted' => $adopted,
			'failed'  => $failed,
			'results' => $results,
		);
	}

	/**
	 * 把孤儿的本地 uploads URL 回写为 bucket URL（作用于给定文章列表）。
	 *
	 * 旧 URL 从附件自身的 _wp_attached_file 推导（stem + 候选扩展名），
	 * 新 URL 取 wp_get_attachment_url()（附件真实 URL，本站 bucket 保留原扩展名；
	 * 旧实现硬编码转 .webp 会产出 404，此处修正）。每个候选扩展名都尝试
	 * 直到至少命中一篇文章，覆盖转码前（.jpg）与当前（.webp）两类引用。
	 *
	 * @param int   $attachment_id 孤儿附件 ID。
	 * @param int[] $post_ids      需要回写的文章 ID 列表。
	 * @return array
	 */
	private function rewrite_orphan_urls( $attachment_id, $post_ids ) {
		$attached = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( '' === $attached || false !== strpos( $attached, '://' ) ) {
			return array(
				'success'  => false,
				'reason'   => 'no_rel_path',
				'attempts' => 0,
			);
		}

		$dir  = dirname( $attached );
		$stem = pathinfo( $attached, PATHINFO_FILENAME );
		if ( '.' === $dir || '' === $stem ) {
			return array(
				'success'  => false,
				'reason'   => 'invalid_path',
				'attempts' => 0,
			);
		}

		// 真实 bucket URL：附件当前实际地址（含 bucket 路径与真实扩展名）。
		$new_url = wp_get_attachment_url( $attachment_id );
		if ( ! $new_url || false !== strpos( $new_url, '/wp-content/uploads/' ) ) {
			// 未离线上传（本地路径）→ 无 bucket 目标可回写。
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'not_offloaded',
				'attempts' => 0,
			);
		}

		// 候选旧扩展名：原始转换前扩展名优先，再补当前扩展名与通用候选集。
		$candidates = array();
		$metadata   = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && ! empty( $metadata['original_image'] ) ) {
			$orig_ext = strtolower( pathinfo( $metadata['original_image'], PATHINFO_EXTENSION ) );
			if ( '' !== $orig_ext ) {
				$candidates[] = $orig_ext;
			}
		}
		$current_ext = strtolower( pathinfo( $attached, PATHINFO_EXTENSION ) );
		if ( '' !== $current_ext && ! in_array( $current_ext, $candidates, true ) ) {
			$candidates[] = $current_ext;
		}
		foreach ( self::OLD_EXT_CANDIDATES as $ext ) {
			if ( ! in_array( $ext, $candidates, true ) ) {
				$candidates[] = $ext;
			}
		}

		$home     = home_url();
		$base_old = rtrim( $home, '/' ) . '/wp-content/uploads/' . ltrim( $dir, '/\\' ) . '/';

		$attempts   = 0;
		$total_hits = 0;
		$best       = null;

		foreach ( $candidates as $old_ext ) {
			$old_url = $base_old . $stem . '.' . $old_ext;

			$result = $this->rewrite_content_for_posts( $post_ids, $old_url, $new_url );
			++$attempts;
			$total_hits += $this->count_hits( $result );

			if ( null === $best || $total_hits > $this->count_hits( $best ) ) {
				$best = $result;
			}

			// 首个产生替换的候选即停止：后续候选是同 stem 不同扩展名，只会加噪音。
			if ( $this->count_hits( $result ) > 0 ) {
				return array(
					'success'  => true,
					'replaced' => true,
					'attempts' => $attempts,
					'old_url'  => $old_url,
					'new_url'  => $new_url,
					'posts'    => isset( $result['posts'] ) ? $result['posts'] : array(),
				);
			}
		}

		return array(
			'success'  => true,
			'replaced' => false,
			'reason'   => 'no_match',
			'attempts' => $attempts,
		);
	}

	/**
	 * 在给定文章中替换旧 URL 为 bucket URL（复数文章版，直接 SQL 更新）。
	 *
	 * 自包含移植自旧 MediaEngineUrlRewriteService::rewrite_content_for_posts()
	 * （该方法已随重构移除）；与当前单数版 rewrite_content() 同构：
	 * 域前缀可选、文件名尺寸后缀（-150x150 / -scaled）兼容。
	 *
	 * @param int[]  $post_ids 文章 ID 列表。
	 * @param string $old_url  旧本地 URL（如 https://.../wp-content/uploads/2026/08/name.jpg）。
	 * @param string $new_url  新 bucket URL（如 https://.../wp-media/2026/08/name.webp）。
	 * @return array { success:bool, replaced:bool, posts:array }
	 */
	private function rewrite_content_for_posts( $post_ids, $old_url, $new_url ) {
		global $wpdb;

		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) ) );
		if ( empty( $post_ids ) ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_posts',
				'posts'    => array(),
			);
		}

		$parts       = $this->get_pattern_parts( $old_url, $new_url );
		$pattern     = $parts[0];
		$replacement = $parts[1];

		$any_replaced = false;
		$posts_result = array();

		foreach ( $post_ids as $post_id ) {
			// 只回写真实文章（跳过修订/附件）。
			$post = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID = %d AND post_type NOT IN ('revision','attachment')",
					$post_id
				)
			);

			if ( ! $post ) {
				$posts_result[ $post_id ] = array(
					'replaced' => false,
					'reason'   => 'not_found',
				);
				continue;
			}

			$count       = 0;
			$new_content = preg_replace( $pattern, $replacement, $post->post_content, -1, $count );

			if ( 0 === $count || $new_content === $post->post_content ) {
				$posts_result[ $post_id ] = array(
					'replaced' => false,
					'reason'   => 'no_match',
				);
				continue;
			}

			$updated = $wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $new_content ),
				array( 'ID' => $post_id )
			);
			// 精准缓存失效（避免 clean_post_cache 触发 supercache 钩子 → 502）。
			wp_cache_delete( $post_id, 'posts' );
			wp_cache_delete( $post_id, 'post_meta' );

			$posts_result[ $post_id ] = array(
				'replaced' => true,
				'count'    => $count,
				'updated'  => ( false !== $updated ),
			);
			$any_replaced             = true;
		}

		return array(
			'success'  => true,
			'replaced' => $any_replaced,
			'posts'    => $posts_result,
		);
	}

	/**
	 * 由 old/new URL 构建正则模式与替换串。
	 *
	 * 与当前 media-engine 单数版 rewrite_content() 语义一致。
	 *
	 * @param string $old_url 旧 URL。
	 * @param string $new_url 新 URL。
	 * @return array [pattern:string, replacement:string]
	 */
	private function get_pattern_parts( $old_url, $new_url ) {
		$rel_old  = wp_make_link_relative( $old_url );
		$rel_new  = wp_make_link_relative( $new_url );
		$old_info = pathinfo( $rel_old );
		$new_info = pathinfo( $rel_new );

		$old_dir  = trailingslashit( $old_info['dirname'] );
		$new_dir  = trailingslashit( $new_info['dirname'] );
		$filename = $old_info['filename'];
		$old_ext  = $old_info['extension'];
		$new_ext  = $new_info['extension'];

		$pattern = '/'
			. '(https?:\/\/[^\/]+)?'
			. preg_quote( $old_dir, '/' )
			. preg_quote( $filename, '/' )
			. '((?:-\d+x\d+)?(?:-scaled)?)'
			. '\.' . preg_quote( $old_ext, '/' )
			. '/i';

		$replacement = '$1' . $new_dir . $filename . '$2.' . $new_ext;

		return array( $pattern, $replacement );
	}

	/**
	 * 从文章正文提取被引用的媒体 ID（wp-image-{ID} / data-id / data-attachment-id）。
	 *
	 * @param string $content 文章正文。
	 * @return int[]
	 */
	private function extract_media_ids( $content ) {
		$ids = array();
		if ( preg_match_all( '/wp-image-(\d+)/', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		if ( preg_match_all( '/data-id=["\']?(\d+)/i', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		if ( preg_match_all( '/data-attachment-id=["\']?(\d+)/i', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		$ids = array_unique( array_map( 'absint', $ids ) );
		return array_filter(
			$ids,
			function ( $x ) {
				return $x > 0;
			}
		);
	}

	/**
	 * 统计回写结果中有多少篇文章发生了至少一次替换。
	 *
	 * @param array $result rewrite_content_for_posts() 的返回。
	 * @return int
	 */
	private function count_hits( $result ) {
		$count = 0;
		if ( isset( $result['posts'] ) && is_array( $result['posts'] ) ) {
			foreach ( $result['posts'] as $post_result ) {
				if ( ! empty( $post_result['replaced'] ) ) {
					++$count;
				}
			}
		}
		return $count;
	}
}
