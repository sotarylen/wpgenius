<?php
/**
 * Media Engine Audit Service
 *
 * Scans for leftover media files in the uploads directory, probing the Minio bucket with HEAD requests to determine
 * whether a file was successfully offloaded, and analyzes the reasons for files that were not offloaded.
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineAuditService {

	/**
	 * Image extensions to scan (including bmp/svg etc. that cannot be converted, used for reporting leftovers)
	 */
	const SCAN_EXTS = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'ico', 'avif' );

	/**
	 * MIME types supported for conversion (corresponding to the scanner's get_supported_mime_types + webp)
	 */
	const SUPPORTED_MIMES = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

	/**
	 * Number of files scanned per batch (used for AJAX batching)
	 */
	const BATCH_SIZE = 50;

	/**
	 * Cache mapping image file paths relative to the uploads root -> local absolute paths
	 *
	 * @var array|null
	 */
	private $file_list_cache = null;

	/**
	 * Directory index cache directory (uploads/w2p-audit-index/)
	 *
	 * @var string
	 */
	private $index_dir;

	/**
	 * Index cache lifetime (seconds)
	 *
	 * @var int
	 */
	private $index_ttl;

	/**
	 * Constructor: initializes the index cache directory
	 */
	public function __construct() {
		$upload_dir      = wp_upload_dir();
		$this->index_dir = $upload_dir['basedir'] . '/w2p-audit-index/';
		$this->index_ttl = 600; // 10 minutes
	}

	/**
	 * Scan a batch of image files in an uploads subdirectory
	 *
	 * @param string $subdir Subpath relative to the uploads root (e.g. 2026/07)
	 * @param int    $offset Offset (ordered by file name)
	 * @param int    $limit  Count
	 * @return array { total:int, scanned:int, files:array, index_built:bool }
	 */
	public function scan_batch( $subdir, $offset = 0, $limit = self::BATCH_SIZE ) {
		$subdir = trim( $subdir, '/\\' );

		// Prevent directory traversal: only allow relative paths that do not contain ..
		if ( $subdir === '' || strpos( $subdir, '..' ) !== false ) {
			return array( 'error' => __( 'Invalid directory', 'wp-genius' ) );
		}

		$base_dir = wp_upload_dir()['basedir'];
		$dir_abs  = $base_dir . '/' . $subdir;
		if ( ! is_dir( $dir_abs ) ) {
			return array( 'error' => __( 'Directory not found', 'wp-genius' ) );
		}

		$files = $this->get_image_files( $dir_abs, $subdir );
		$total = count( $files );

		// Build/read the directory index (first build may take ~25s, later reads use the file cache)
		$index       = $this->get_directory_index( $subdir );
		$index_built = ! empty( $index['fresh'] );
		$att_map     = $index['attached'] ?? array();   // rel_path => att_id
		$stem_map    = $index['stems'] ?? array();      // stem => att_id

		$slice = array_slice( $files, $offset, $limit );

		$items = array();
		foreach ( $slice as $rel_path => $abs_path ) {
			$items[] = $this->classify_file( $rel_path, $abs_path, $att_map, $stem_map );
		}

		return array(
			'total'       => $total,
			'scanned'     => count( $slice ),
			'index_built' => $index_built,
			'files'       => $items,
		);
	}

	/**
	 * Get all image files in a directory (excluding source/ subdirectories)
	 *
	 * @param string $dir_abs Directory absolute path
	 * @param string $subdir  Subpath relative to uploads
	 * @return array rel_path => abs_path
	 */
	private function get_image_files( $dir_abs, $subdir ) {
		if ( null !== $this->file_list_cache ) {
			return $this->file_list_cache;
		}

		$files = array();
		$dh    = @opendir( $dir_abs );
		if ( ! $dh ) {
			$this->file_list_cache = $files;
			return $files;
		}

		while ( false !== ( $entry = readdir( $dh ) ) ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			// Skip subdirectories such as source/
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

		// Sort by file name to keep batch order stable
		ksort( $files );
		$this->file_list_cache = $files;
		return $files;
	}

	/**
	 * Get the directory index (file cache 600s): rel_path => att_id, stem => att_id
	 *
	 * Background: the postmeta table has ~950k rows and meta_value has no index; a full-table LIKE takes ~25s.
	 * The index is built once here and cached to a file, so subsequent batches return in seconds.
	 *
	 * @param string $subdir Subpath relative to uploads
	 * @return array { attached:array, stems:array, fresh:bool }
	 */
	private function get_directory_index( $subdir ) {
		$cache_file = $this->index_dir . md5( $subdir ) . '.json';

		// Cache hit
		if ( file_exists( $cache_file ) && ( time() - filemtime( $cache_file ) ) < $this->index_ttl ) {
			$data = json_decode( (string) file_get_contents( $cache_file ), true );
			if ( is_array( $data ) && isset( $data['attached'] ) ) {
				$data['fresh'] = false;
				return $data;
			}
		}

		global $wpdb;

		// 1. Fetch attachment IDs from the posts table by upload month (uses the post_date index, ~1.8s)
		$att_ids = array();
		if ( preg_match( '#^(\d{4})/(\d{2})$#', $subdir, $m ) ) {
			$start   = $m[1] . '-' . $m[2] . '-01';
			$end     = gmdate( 'Y-m-d', strtotime( $start . ' +1 month' ) );
			$att_ids = array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'attachment' AND post_date >= %s AND post_date < %s",
						$start,
						$end
					)
				)
			);
		}

		if ( empty( $att_ids ) ) {
			return array(
				'attached' => array(),
				'stems'    => array(),
				'fresh'    => true,
			);
		}

		// 2. Batch query _wp_attached_file by the post_id index (covers bare file-name forms, uses the post_id index)
		$attached   = array();
		$stems      = array();
		$dir_prefix = rtrim( $subdir, '/' ) . '/';

		foreach ( array_chunk( $att_ids, 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- IN placeholders consist of %d (generated by array_fill), and parameters are bound via prepare.
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

				// Exact path mapping: relative path form
				if ( strpos( $val, $dir_prefix ) === 0 ) {
					$attached[ $val ] = $att_id;
				}

				// stem mapping: all non-URL forms (including bare file names -- advmo rewrites _wp_attached_file to the bare name)
				if ( strpos( $val, '://' ) === false ) {
					$stems[ pathinfo( $val, PATHINFO_FILENAME ) ] = $att_id;
				}
			}
		}

		// Write to the file cache
		if ( ! is_dir( $this->index_dir ) ) {
			wp_mkdir_p( $this->index_dir );
		}
		file_put_contents(
			$cache_file,
			wp_json_encode(
				array(
					'attached' => $attached,
					'stems'    => $stems,
				)
			),
			LOCK_EX
		);

		return array(
			'attached' => $attached,
			'stems'    => $stems,
			'fresh'    => true,
		);
	}

	/**
	 * Classify a single file
	 *
	 * @param string $rel_path   Path relative to uploads (2026/07/name.jpg)
	 * @param string $abs_path   Local absolute path
	 * @param array  $att_map    rel_path => attachment_id index
	 * @param array  $stem_map   stem => attachment_id index
	 * @return array
	 */
	private function classify_file( $rel_path, $abs_path, $att_map = array(), $stem_map = array() ) {
		$dir_rel  = dirname( $rel_path );
		$basename = basename( $rel_path );
		$ext      = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );

		// 1. Match the attachment record in the database (exact rel_path, fall back to stem)
		$attachment_id = isset( $att_map[ $rel_path ] ) ? $att_map[ $rel_path ] : 0;
		if ( ! $attachment_id ) {
			$attachment_id = isset( $stem_map[ pathinfo( $basename, PATHINFO_FILENAME ) ] )
				? $stem_map[ pathinfo( $basename, PATHINFO_FILENAME ) ]
				: 0;
		}
		$attachment_id = (int) $attachment_id;

		// 2. HEAD-probe the bucket: try the same-name .webp first, then -static.webp (GIF conflict variant)
		$stem      = pathinfo( $basename, PATHINFO_FILENAME );
		$webp_urls = array();
		if ( 'webp' !== $ext ) {
			$webp_urls[] = $this->build_webp_url( $dir_rel, $stem . '.webp' );
			$webp_urls[] = $this->build_webp_url( $dir_rel, $stem . '-static.webp' );
		} else {
			// Already webp, probe itself
			$webp_urls[] = $this->build_webp_url( $dir_rel, $basename );
		}
		$in_bucket = $this->head_exists_any( $webp_urls );

		$item = array(
			'file'          => $rel_path,
			'basename'      => $basename,
			'ext'           => $ext,
			'size'          => file_exists( $abs_path ) ? filesize( $abs_path ) : 0,
			'local_exists'  => file_exists( $abs_path ),
			'attachment_id' => $attachment_id ? (int) $attachment_id : 0,
			'in_bucket'     => $in_bucket,
			'checked_url'   => $in_bucket ? $webp_urls[0] : '',
		);

		// 3. Classify
		if ( $in_bucket ) {
			// Class A: the bucket already has the corresponding file; the local copy can be cleaned
			$item['status'] = 'cleanable';
			$item['reason'] = __( 'Corresponding file already exists in the bucket; local copy can be cleaned', 'wp-genius' );
		} elseif ( $attachment_id ) {
			// Class B: not offloaded, but the media library has a record
			$item['status'] = 'not_offloaded';
			$item['reason'] = $this->analyze_reason( $attachment_id, $rel_path, $abs_path );
			$item['parent'] = $this->get_parent_info( $attachment_id );
			// Whether it can be re-queued (mime is in the supported list)
			$item['can_enqueue'] = $this->can_enqueue( $attachment_id );
		} else {
			// Class C: orphan file (no record in the database)
			$item['status'] = 'orphan';
			$item['reason'] = __( 'No matching record in the media library (orphan file)', 'wp-genius' );
		}

		return $item;
	}

	/**
	 * Build a /wp-media/ URL
	 *
	 * @param string $dir_rel Relative directory (2026/07)
	 * @param string $file    File name (name.webp)
	 * @return string
	 */
	private function build_webp_url( $dir_rel, $file ) {
		$home = home_url();
		return rtrim( $home, '/' ) . '/wp-media/' . ltrim( $dir_rel, '/' ) . '/' . $file;
	}

	/**
	 * HEAD-probe multiple URLs; returns true if any returns 200
	 *
	 * Uses curl directly (local OrbStack environment; domains resolve via hosts, does not rely on the WP HTTP API)
	 *
	 * @param array $urls List of candidate URLs
	 * @return bool
	 */
	private function head_exists_any( $urls ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return false;
		}

		$mh    = curl_multi_init();
		$chans = array();

		foreach ( $urls as $url ) {
			$ch = curl_init( $url );
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_NOBODY         => true,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => 5,
					CURLOPT_CONNECTTIMEOUT => 5,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => 0,
					CURLOPT_USERAGENT      => 'WPGenius-MediaAudit/1.0',
				)
			);
			curl_multi_add_handle( $mh, $ch );
			$chans[] = $ch;
		}

		// Run the probes concurrently
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
	 * Analyze why a file was not offloaded
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $rel_path      Relative path
	 * @param string $abs_path      Absolute path
	 * @return string
	 */
	private function analyze_reason( $attachment_id, $rel_path, $abs_path ) {
		$mime      = get_post_mime_type( $attachment_id );
		$offloaded = get_post_meta( $attachment_id, 'advmo_offloaded', true );
		$attached  = get_attached_file( $attachment_id );

		// 1. mime is not in the supported conversion list (the scanner does not return it)
		if ( ! in_array( $mime, self::SUPPORTED_MIMES, true ) ) {
			return sprintf(
				// translators: %1: placeholder.
				__( 'Media type %s is not in the supported conversion list; the scanner will not queue it', 'wp-genius' ),
				$mime ? $mime : '(empty)'
			);
		}

		// 2. Marked as offloaded but not found in the bucket -> data inconsistency
		if ( '1' === (string) $offloaded ) {
			return __( 'Database marks this file as offloaded (advmo_offloaded=1) but it was not found in the bucket; the offload record may be stale or the bucket was cleaned', 'wp-genius' );
		}

		// 3. The attachment's _wp_attached_file does not match the actual file
		$attached_rel = str_replace( wp_upload_dir()['basedir'] . '/', '', $attached );
		if ( $attached_rel !== $rel_path ) {
			return sprintf(
				// translators: %1: placeholder.
				__( 'The attachment path in the database (%s) does not match the actual file', 'wp-genius' ),
				$attached_rel
			);
		}

		// 4. Everything else: not offloaded, the scanner should be able to pick it up
		return __( 'Not marked as offloaded; the scanner should be able to pick it up (can be re-queued)', 'wp-genius' );
	}

	/**
	 * Determine whether an attachment can be re-queued (mime is in the supported list)
	 *
	 * @param int $attachment_id Attachment ID
	 * @return bool
	 */
	private function can_enqueue( $attachment_id ) {
		$mime = get_post_mime_type( $attachment_id );
		return in_array( $mime, self::SUPPORTED_MIMES, true );
	}

	/**
	 * Get the parent post information
	 *
	 * @param int $attachment_id Attachment ID
	 * @return array { id:int, title:string, url:string }
	 */
	private function get_parent_info( $attachment_id ) {
		$parent_id = wp_get_post_parent_id( $attachment_id );
		if ( ! $parent_id ) {
			return array(
				'id'    => 0,
				'title' => __( 'No parent post (Unattached)', 'wp-genius' ),
				'url'   => '',
			);
		}
		return array(
			'id'    => (int) $parent_id,
			'title' => get_the_title( $parent_id ),
			'url'   => get_edit_post_link( $parent_id ),
		);
	}

	/**
	 * Clean up the specified files (Class A: already confirmed to exist in the bucket)
	 *
	 * Unlinks after a second HEAD confirmation and writes a log entry.
	 *
	 * @param array $rel_paths List of paths relative to uploads
	 * @param bool  $force     Skip the bucket-containment check (used for orphan files with no DB record).
	 * @return array { cleaned:int, skipped:array }
	 */
	public function clean_files( $rel_paths, $force = false ) {
		$base_dir = wp_upload_dir()['basedir'];
		$cleaned  = 0;
		$skipped  = array();

		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-logger-service.php';
		}
		$logger = new MediaEngineConversionLogger();

		foreach ( $rel_paths as $rel_path ) {
			$rel_path = trim( $rel_path, '/\\' );
			if ( $rel_path === '' || strpos( $rel_path, '..' ) !== false ) {
				$skipped[] = array(
					'file'   => $rel_path,
					'reason' => 'invalid_path',
				);
				continue;
			}
			$abs_path = $base_dir . '/' . $rel_path;
			if ( ! file_exists( $abs_path ) || ! is_file( $abs_path ) ) {
				$skipped[] = array(
					'file'   => $rel_path,
					'reason' => 'not_exists',
				);
				continue;
			}

			// Second confirmation: only delete if the corresponding webp exists in the bucket
			$dir_rel  = dirname( $rel_path );
			$basename = basename( $rel_path );
			$ext      = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
			$stem     = pathinfo( $basename, PATHINFO_FILENAME );

			$urls = array();
			if ( 'webp' !== $ext ) {
				$urls[] = $this->build_webp_url( $dir_rel, $stem . '.webp' );
				$urls[] = $this->build_webp_url( $dir_rel, $stem . '-static.webp' );
			} else {
				$urls[] = $this->build_webp_url( $dir_rel, $basename );
			}

			// Orphan (force) files have no DB record, so there is nothing to break by deleting them;
			// normal (cleanables) still require the corresponding webp to exist in the bucket.
			if ( ! $force && ! $this->head_exists_any( $urls ) ) {
				$skipped[] = array(
					'file'   => $rel_path,
					'reason' => 'not_in_bucket',
				);
				continue;
			}

			if ( @unlink( $abs_path ) ) {
				++$cleaned;
				$logger->log_debug( sprintf( 'Audit Clean: removed local file %s (bucket has %s)', $rel_path, $urls[0] ) );
			} else {
				$skipped[] = array(
					'file'   => $rel_path,
					'reason' => 'unlink_failed',
				);
			}
		}

		return array(
			'cleaned' => $cleaned,
			'skipped' => $skipped,
		);
	}
}
