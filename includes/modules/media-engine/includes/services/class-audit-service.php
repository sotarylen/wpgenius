<?php
/**
 * Media Engine Audit Service
 *
 * 扫描 uploads 目录中的残留媒体文件，通过 Minio 存储桶 HEAD 探测判断
 * 文件是否已成功 offload，并对未 offload 文件做原因分析。
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineAuditService {

	/**
	 * 扫描的图片扩展名（含不支持转换的 bmp/svg 等，用于报告残留）
	 */
	const SCAN_EXTS = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'ico', 'avif' ];

	/**
	 * 转换支持的 MIME（对应 scanner 的 get_supported_mime_types + webp）
	 */
	const SUPPORTED_MIMES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];

	/**
	 * 每批扫描数量（AJAX 分批用）
	 */
	const BATCH_SIZE = 50;

	/**
	 * 目录中图片文件相对 uploads 根目录的路径 -> 本地绝对路径 的映射缓存
	 *
	 * @var array|null
	 */
	private $file_list_cache = null;

	/**
	 * 目录索引缓存目录（uploads/w2p-audit-index/）
	 *
	 * @var string
	 */
	private $index_dir;

	/**
	 * 索引缓存有效期（秒）
	 *
	 * @var int
	 */
	private $index_ttl;

	/**
	 * 构造函数：初始化索引缓存目录
	 */
	public function __construct() {
		$upload_dir      = wp_upload_dir();
		$this->index_dir = $upload_dir['basedir'] . '/w2p-audit-index/';
		$this->index_ttl = 600; // 10 分钟
	}

	/**
	 * 扫描 uploads 子目录中的一批图片文件
	 *
	 * @param string $subdir 相对 uploads 根目录的子路径（如 2026/07）
	 * @param int    $offset 偏移（按文件名排序）
	 * @param int    $limit  数量
	 * @return array { total:int, scanned:int, files:array, index_built:bool }
	 */
	public function scan_batch( $subdir, $offset = 0, $limit = self::BATCH_SIZE ) {
		$subdir = trim( $subdir, '/\\' );

		// 防目录穿越：只允许相对路径且不能包含 ..
		if ( $subdir === '' || strpos( $subdir, '..' ) !== false ) {
			return [ 'error' => __( 'Invalid directory', 'wp-genius' ) ];
		}

		$base_dir = wp_upload_dir()['basedir'];
		$dir_abs  = $base_dir . '/' . $subdir;
		if ( ! is_dir( $dir_abs ) ) {
			return [ 'error' => __( 'Directory not found', 'wp-genius' ) ];
		}

		$files = $this->get_image_files( $dir_abs, $subdir );
		$total = count( $files );

		// 构建/读取目录索引（首次构建可能耗时 ~25s，之后走文件缓存）
		$index      = $this->get_directory_index( $subdir );
		$index_built = ! empty( $index['fresh'] );
		$att_map    = $index['attached'] ?? [];   // rel_path => att_id
		$stem_map   = $index['stems'] ?? [];      // stem => att_id

		$slice = array_slice( $files, $offset, $limit );

		$items = [];
		foreach ( $slice as $rel_path => $abs_path ) {
			$items[] = $this->classify_file( $rel_path, $abs_path, $att_map, $stem_map );
		}

		return [
			'total'       => $total,
			'scanned'     => count( $slice ),
			'index_built' => $index_built,
			'files'       => $items,
		];
	}

	/**
	 * 获取目录下所有图片文件（排除 source/ 子目录）
	 *
	 * @param string $dir_abs 目录绝对路径
	 * @param string $subdir  相对 uploads 的子路径
	 * @return array rel_path => abs_path
	 */
	private function get_image_files( $dir_abs, $subdir ) {
		if ( null !== $this->file_list_cache ) {
			return $this->file_list_cache;
		}

		$files = [];
		$dh    = @opendir( $dir_abs );
		if ( ! $dh ) {
			$this->file_list_cache = $files;
			return $files;
		}

		while ( false !== ( $entry = readdir( $dh ) ) ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			// 跳过 source/ 等子目录
			if ( is_dir( $dir_abs . '/' . $entry ) ) {
				continue;
			}
			$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, self::SCAN_EXTS, true ) ) {
				continue;
			}
			$files[ $subdir . '/' . $entry ] = $dir_abs . '/' . $entry;
		}
		closedir( $dh );

		// 按文件名排序保证分批顺序稳定
		ksort( $files );
		$this->file_list_cache = $files;
		return $files;
	}

	/**
	 * 获取目录索引（文件缓存 600s）：rel_path => att_id、stem => att_id
	 *
	 * 背景：postmeta 表 95 万级且 meta_value 无索引，全表 LIKE 约 25s。
	 * 这里一次性构建并缓存到文件，后续批次秒回。
	 *
	 * @param string $subdir 相对 uploads 子目录
	 * @return array { attached:array, stems:array, fresh:bool }
	 */
	private function get_directory_index( $subdir ) {
		$cache_file = $this->index_dir . md5( $subdir ) . '.json';

		// 命中缓存
		if ( file_exists( $cache_file ) && ( time() - filemtime( $cache_file ) ) < $this->index_ttl ) {
			$data = json_decode( (string) file_get_contents( $cache_file ), true );
			if ( is_array( $data ) && isset( $data['attached'] ) ) {
				$data['fresh'] = false;
				return $data;
			}
		}

		global $wpdb;

		// 1. 按上传月份从 posts 表取附件 ID（走 post_date 索引，~1.8s）
		$att_ids = [];
		if ( preg_match( '#^(\d{4})/(\d{2})$#', $subdir, $m ) ) {
			$start = $m[1] . '-' . $m[2] . '-01';
			$end   = gmdate( 'Y-m-d', strtotime( $start . ' +1 month' ) );
			$att_ids = array_map( 'intval', $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'attachment' AND post_date >= %s AND post_date < %s",
					$start, $end
				)
			) );
		}

		if ( empty( $att_ids ) ) {
			return [ 'attached' => [], 'stems' => [], 'fresh' => true ];
		}

		// 2. 按 post_id 索引批量查 _wp_attached_file（覆盖裸文件名形态，走 post_id 索引）
		$attached = [];
		$stems    = [];
		$dir_prefix = rtrim( $subdir, '/' ) . '/';

		foreach ( array_chunk( $att_ids, 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN 占位符仅由 %d 组成（array_fill 生成），参数经 prepare 绑定，无注入面。
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta}
					WHERE meta_key = '_wp_attached_file' AND post_id IN ($placeholders)",
					$chunk
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $rows as $row ) {
				$att_id = (int) $row->post_id;
				$val    = $row->meta_value;

				// 精确路径映射：相对路径形态
				if ( strpos( $val, $dir_prefix ) === 0 ) {
					$attached[ $val ] = $att_id;
				}

				// stem 映射：所有非 URL 形态（含裸文件名 —— advmo 会改写 _wp_attached_file 为裸名）
				if ( strpos( $val, '://' ) === false ) {
					$stems[ pathinfo( $val, PATHINFO_FILENAME ) ] = $att_id;
				}
			}
		}

		// 写入文件缓存
		if ( ! is_dir( $this->index_dir ) ) {
			wp_mkdir_p( $this->index_dir );
		}
		file_put_contents( $cache_file, wp_json_encode( [
			'attached' => $attached,
			'stems'    => $stems,
		] ), LOCK_EX );

		return [
			'attached' => $attached,
			'stems'    => $stems,
			'fresh'    => true,
		];
	}

	/**
	 * 分类单个文件
	 *
	 * @param string $rel_path   相对 uploads 路径（2026/07/name.jpg）
	 * @param string $abs_path   本地绝对路径
	 * @param array  $att_map    rel_path => attachment_id 索引
	 * @param array  $stem_map   stem => attachment_id 索引
	 * @return array
	 */
	private function classify_file( $rel_path, $abs_path, $att_map = [], $stem_map = [] ) {
		$dir_rel  = dirname( $rel_path );
		$basename = basename( $rel_path );
		$ext      = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );

		// 1. 匹配数据库 attachment 记录（精确 rel_path，兜底 stem）
		$attachment_id = isset( $att_map[ $rel_path ] ) ? $att_map[ $rel_path ] : 0;
		if ( ! $attachment_id ) {
			$attachment_id = isset( $stem_map[ pathinfo( $basename, PATHINFO_FILENAME ) ] )
				? $stem_map[ pathinfo( $basename, PATHINFO_FILENAME ) ]
				: 0;
		}
		$attachment_id = (int) $attachment_id;

		// 2. HEAD 探测存储桶：先试同名 .webp，再试 -static.webp（GIF 冲突变体）
		$stem = pathinfo( $basename, PATHINFO_FILENAME );
		$webp_urls = [];
		if ( 'webp' !== $ext ) {
			$webp_urls[] = $this->build_webp_url( $dir_rel, $stem . '.webp' );
			$webp_urls[] = $this->build_webp_url( $dir_rel, $stem . '-static.webp' );
		} else {
			// 已是 webp，探测自身
			$webp_urls[] = $this->build_webp_url( $dir_rel, $basename );
		}
		$in_bucket = $this->head_exists_any( $webp_urls );

		$item = [
			'file'          => $rel_path,
			'basename'      => $basename,
			'ext'           => $ext,
			'size'          => file_exists( $abs_path ) ? filesize( $abs_path ) : 0,
			'local_exists'  => file_exists( $abs_path ),
			'attachment_id' => $attachment_id ? (int) $attachment_id : 0,
			'in_bucket'     => $in_bucket,
			'checked_url'   => $in_bucket ? $webp_urls[0] : '',
		];

		// 3. 分类
		if ( $in_bucket ) {
			// A 类：桶里已有对应文件，可清理本地
			$item['status'] = 'cleanable';
			$item['reason'] = __( '桶中已存在对应文件，可清理本地', 'wp-genius' );
		} elseif ( $attachment_id ) {
			// B 类：未 offload，但媒体库有记录
			$item['status'] = 'not_offloaded';
			$item['reason'] = $this->analyze_reason( $attachment_id, $rel_path, $abs_path );
			$item['parent'] = $this->get_parent_info( $attachment_id );
			// 是否可重新入队（mime 在支持列表内）
			$item['can_enqueue'] = $this->can_enqueue( $attachment_id );
		} else {
			// C 类：孤儿文件（数据库无记录）
			$item['status'] = 'orphan';
			$item['reason'] = __( '媒体库中无对应记录（孤儿文件）', 'wp-genius' );
		}

		return $item;
	}

	/**
	 * 构造 /wp-media/ URL
	 *
	 * @param string $dir_rel 相对目录（2026/07）
	 * @param string $file    文件名（name.webp）
	 * @return string
	 */
	private function build_webp_url( $dir_rel, $file ) {
		$home = home_url();
		return rtrim( $home, '/' ) . '/wp-media/' . ltrim( $dir_rel, '/' ) . '/' . $file;
	}

	/**
	 * HEAD 探测多个 URL，任一 200 即返回 true
	 *
	 * 使用 curl 直连（本地 OrbStack 环境，域名走 hosts 解析，不依赖 WP HTTP API）
	 *
	 * @param array $urls 候选 URL 列表
	 * @return bool
	 */
	private function head_exists_any( $urls ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return false;
		}

		$mh     = curl_multi_init();
		$chans  = [];

		foreach ( $urls as $url ) {
			$ch = curl_init( $url );
			curl_setopt_array( $ch, [
				CURLOPT_NOBODY          => true,
				CURLOPT_RETURNTRANSFER  => true,
				CURLOPT_TIMEOUT         => 5,
				CURLOPT_CONNECTTIMEOUT  => 5,
				CURLOPT_FOLLOWLOCATION  => true,
				CURLOPT_SSL_VERIFYPEER  => false,
				CURLOPT_SSL_VERIFYHOST  => 0,
				CURLOPT_USERAGENT       => 'WPGenius-MediaAudit/1.0',
			] );
			curl_multi_add_handle( $mh, $ch );
			$chans[] = $ch;
		}

		// 执行并发探测
		$running = null;
		do {
			$status = curl_multi_exec( $mh, $running );
			if ( $running ) {
				curl_multi_select( $mh, 0.5 );
			}
		} while ( $running && CURLM_OK === $status );

		$found = false;
		foreach ( $chans as $ch ) {
			$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
			if ( 200 === $code ) {
				$found = true;
			}
			curl_multi_remove_handle( $mh, $ch );
			curl_close( $ch );
			if ( $found ) {
				break;
			}
		}
		curl_multi_close( $mh );

		return $found;
	}

	/**
	 * 分析未 offload 的原因
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param string $rel_path      相对路径
	 * @param string $abs_path      绝对路径
	 * @return string
	 */
	private function analyze_reason( $attachment_id, $rel_path, $abs_path ) {
		$mime      = get_post_mime_type( $attachment_id );
		$offloaded = get_post_meta( $attachment_id, 'advmo_offloaded', true );
		$attached  = get_attached_file( $attachment_id );

		// 1. mime 不在转换支持列表（scanner 不返回）
		if ( ! in_array( $mime, self::SUPPORTED_MIMES, true ) ) {
			return sprintf(
				__( '媒体类型 %s 不在转换支持列表，scanner 不会将其纳入队列', 'wp-genius' ),
				$mime ? $mime : '(空)'
			);
		}

		// 2. 已标记 offload 但桶里找不到 → 数据不一致
		if ( '1' === (string) $offloaded ) {
			return __( '数据库已标记 offload（advmo_offloaded=1）但桶中未找到，可能 offload 记录残留或存储桶被清理', 'wp-genius' );
		}

		// 3. 附件的 _wp_attached_file 与实际文件不一致
		$attached_rel = str_replace( wp_upload_dir()['basedir'] . '/', '', $attached );
		if ( $attached_rel !== $rel_path ) {
			return sprintf(
				__( '数据库记录的附件路径（%s）与实际文件不一致', 'wp-genius' ),
				$attached_rel
			);
		}

		// 4. 其余：未 offload，scanner 应能扫到
		return __( '未标记 offload，scanner 应可扫描到（可重新入队处理）', 'wp-genius' );
	}

	/**
	 * 判断附件是否可重新入队（mime 在支持列表内）
	 *
	 * @param int $attachment_id 附件 ID
	 * @return bool
	 */
	private function can_enqueue( $attachment_id ) {
		$mime = get_post_mime_type( $attachment_id );
		return in_array( $mime, self::SUPPORTED_MIMES, true );
	}

	/**
	 * 获取父级文章信息
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array { id:int, title:string, url:string }
	 */
	private function get_parent_info( $attachment_id ) {
		$parent_id = wp_get_post_parent_id( $attachment_id );
		if ( ! $parent_id ) {
			return [ 'id' => 0, 'title' => __( '无父级文章（Unattached）', 'wp-genius' ), 'url' => '' ];
		}
		return [
			'id'    => (int) $parent_id,
			'title' => get_the_title( $parent_id ),
			'url'   => get_edit_post_link( $parent_id ),
		];
	}

	/**
	 * 清理指定文件（A 类：桶中已确认存在）
	 *
	 * 二次 HEAD 确认后 unlink，写日志。
	 *
	 * @param array $rel_paths 相对 uploads 路径列表
	 * @return array { cleaned:int, skipped:array }
	 */
	public function clean_files( $rel_paths ) {
		$base_dir = wp_upload_dir()['basedir'];
		$cleaned  = 0;
		$skipped  = [];

		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-logger-service.php';
		}
		$logger = new MediaEngineConversionLogger();

		foreach ( $rel_paths as $rel_path ) {
			$rel_path = trim( $rel_path, '/\\' );
			if ( $rel_path === '' || strpos( $rel_path, '..' ) !== false ) {
				$skipped[] = [ 'file' => $rel_path, 'reason' => 'invalid_path' ];
				continue;
			}
			$abs_path = $base_dir . '/' . $rel_path;
			if ( ! file_exists( $abs_path ) || ! is_file( $abs_path ) ) {
				$skipped[] = [ 'file' => $rel_path, 'reason' => 'not_exists' ];
				continue;
			}

			// 二次确认：桶里必须存在对应 webp 才删
			$dir_rel  = dirname( $rel_path );
			$basename = basename( $rel_path );
			$ext      = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
			$stem     = pathinfo( $basename, PATHINFO_FILENAME );

			$urls = [];
			if ( 'webp' !== $ext ) {
				$urls[] = $this->build_webp_url( $dir_rel, $stem . '.webp' );
				$urls[] = $this->build_webp_url( $dir_rel, $stem . '-static.webp' );
			} else {
				$urls[] = $this->build_webp_url( $dir_rel, $basename );
			}

			if ( ! $this->head_exists_any( $urls ) ) {
				$skipped[] = [ 'file' => $rel_path, 'reason' => 'not_in_bucket' ];
				continue;
			}

			if ( @unlink( $abs_path ) ) {
				$cleaned++;
				$logger->log_debug( sprintf( 'Audit Clean: 已清理本地文件 %s (桶中存在 %s)', $rel_path, $urls[0] ) );
			} else {
				$skipped[] = [ 'file' => $rel_path, 'reason' => 'unlink_failed' ];
			}
		}

		return [ 'cleaned' => $cleaned, 'skipped' => $skipped ];
	}
}
