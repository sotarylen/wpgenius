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

class W2P_Media_Audit_Service {

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
	 * In-memory cache mapping image file paths relative to uploads root -> local absolute paths
	 *
	 * @var array|null
	 */
	private $file_list_cache = null;

	/**
	 * Constructor: cleans up any legacy index directory if left over from older versions
	 */
	public function __construct() {
		$this->cleanup_legacy_index_dir();
	}

	/**
	 * Quietly clean up any historical w2p-audit-index directory in uploads to keep the directory clean
	 */
	private function cleanup_legacy_index_dir() {
		$upload_dir = wp_upload_dir();
		$legacy_dir = $upload_dir['basedir'] . '/w2p-audit-index';
		if ( is_dir( $legacy_dir ) ) {
			$files = @scandir( $legacy_dir );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( '.' !== $file && '..' !== $file ) {
						@unlink( $legacy_dir . '/' . $file );
					}
				}
			}
			@rmdir( $legacy_dir );
		}
	}

	/**
	 * Scan a batch of image files across the uploads directory (or a specific subdirectory)
	 *
	 * @param string $subdir Subpath relative to the uploads root (empty for global YYYY/MM scan)
	 * @param int    $offset Offset (ordered by relative path)
	 * @param int    $limit  Count
	 * @return array { total:int, scanned:int, files:array }
	 */
	public function scan_batch( $subdir = '', $offset = 0, $limit = self::BATCH_SIZE ) {
		$subdir = trim( (string) $subdir, '/\\' );

		// Prevent directory traversal
		if ( strpos( $subdir, '..' ) !== false ) {
			return array( 'error' => __( 'Invalid directory', 'wp-genius' ) );
		}

		$base_dir = wp_upload_dir()['basedir'];

		if ( '' !== $subdir ) {
			$dir_abs = $base_dir . '/' . $subdir;
			if ( ! is_dir( $dir_abs ) ) {
				return array( 'error' => __( 'Directory not found', 'wp-genius' ) );
			}
			$files = $this->get_image_files_in_dir( $dir_abs, $subdir );
		} else {
			$files = $this->get_all_image_files( $base_dir );
		}

		$total = count( $files );
		$slice = array_slice( $files, $offset, $limit, true );

		if ( empty( $slice ) ) {
			return array(
				'total'   => $total,
				'scanned' => 0,
				'files'   => array(),
			);
		}

		// Batch match attachment records for the 50 files in this slice (direct indexed query, zero disk index)
		list( $att_map, $stem_map ) = $this->batch_match_attachments( array_keys( $slice ) );

		$items = array();
		foreach ( $slice as $rel_path => $abs_path ) {
			$items[] = $this->classify_file( $rel_path, $abs_path, $att_map, $stem_map );
		}

		return array(
			'total'   => $total,
			'scanned' => count( $slice ),
			'files'   => $items,
		);
	}

	/**
	 * Get all image files in standard YYYY/MM directories and uploads root without recursing into deeper subfolders
	 *
	 * @param string $base_dir Uploads root directory
	 * @return array rel_path => abs_path
	 */
	private function get_all_image_files( $base_dir ) {
		if ( null !== $this->file_list_cache ) {
			return $this->file_list_cache;
		}

		$files = array();

		// 1. Root uploads directory files
		$root_files = $this->get_image_files_in_dir( $base_dir, '' );
		foreach ( $root_files as $rel => $abs ) {
			$files[ $rel ] = $abs;
		}

		// 2. Year directories (YYYY)
		$dh = @opendir( $base_dir );
		if ( $dh ) {
			while ( false !== ( $year_entry = readdir( $dh ) ) ) {
				if ( '.' === $year_entry || '..' === $year_entry ) {
					continue;
				}
				$year_path = $base_dir . '/' . $year_entry;
				if ( is_dir( $year_path ) && preg_match( '/^\d{4}$/', $year_entry ) ) {
					// 3. Month directories (MM)
					$year_dh = @opendir( $year_path );
					if ( $year_dh ) {
						while ( false !== ( $month_entry = readdir( $year_dh ) ) ) {
							if ( '.' === $month_entry || '..' === $month_entry ) {
								continue;
							}
							$month_path = $year_path . '/' . $month_entry;
							if ( is_dir( $month_path ) && preg_match( '/^\d{2}$/', $month_entry ) ) {
								$sub_rel = $year_entry . '/' . $month_entry;
								$m_files = $this->get_image_files_in_dir( $month_path, $sub_rel );
								foreach ( $m_files as $rel => $abs ) {
									$files[ $rel ] = $abs;
								}
							}
						}
						closedir( $year_dh );
					}
				}
			}
			closedir( $dh );
		}

		// Keep order deterministic across batches
		ksort( $files );
		$this->file_list_cache = $files;
		return $files;
	}

	/**
	 * Get image files directly in a directory (does not recurse into subdirectories)
	 *
	 * @param string $dir_abs Directory absolute path
	 * @param string $sub_rel Subpath relative to uploads (e.g. 2026/08 or empty string)
	 * @return array rel_path => abs_path
	 */
	private function get_image_files_in_dir( $dir_abs, $sub_rel ) {
		$files = array();
		$dh    = @opendir( $dir_abs );
		if ( ! $dh ) {
			return $files;
		}

		while ( false !== ( $entry = readdir( $dh ) ) ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$full_path = $dir_abs . '/' . $entry;
			// Skip subdirectories (e.g. source/ or nested folders)
			if ( is_dir( $full_path ) ) {
				continue;
			}
			$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, self::SCAN_EXTS, true ) ) {
				continue;
			}
			$rel_path           = '' !== $sub_rel ? $sub_rel . '/' . $entry : $entry;
			$files[ $rel_path ] = $full_path;
		}
		closedir( $dh );

		return $files;
	}

	/**
	 * Direct indexed database query for the current slice of files (zero disk cache, ~1ms execution)
	 *
	 * @param array $rel_paths List of relative paths (e.g. 50 items)
	 * @return array [ attached_map, stem_map ]
	 */
	private function batch_match_attachments( array $rel_paths ) {
		global $wpdb;

		if ( empty( $rel_paths ) ) {
			return array( array(), array() );
		}

		$attached = array();
		$stems    = array();
		$stem_arr = array();

		foreach ( $rel_paths as $rp ) {
			$stem_arr[] = pathinfo( $rp, PATHINFO_FILENAME );
		}

		// 1. Query exact relative paths in _wp_attached_file
		$placeholders = implode( ',', array_fill( 0, count( $rel_paths ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attached_file' AND meta_value IN ($placeholders)",
				$rel_paths
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$att_id             = (int) $row->post_id;
				$val                = (string) $row->meta_value;
				$attached[ $val ]   = $att_id;
				$stem_key           = pathinfo( $val, PATHINFO_FILENAME );
				$stems[ $stem_key ] = $att_id;
			}
		}

		// 2. Query bare filenames / stems for any unmatched files (covers advmo bare-filename rewrite)
		$unmatched_stems = array_diff( array_unique( $stem_arr ), array_keys( $stems ) );
		if ( ! empty( $unmatched_stems ) ) {
			$stem_placeholders = implode( ',', array_fill( 0, count( $unmatched_stems ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
			$stem_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta}
					WHERE meta_key = '_wp_attached_file' AND meta_value IN ($stem_placeholders)",
					$unmatched_stems
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( is_array( $stem_rows ) ) {
				foreach ( $stem_rows as $row ) {
					$att_id             = (int) $row->post_id;
					$stem_key           = pathinfo( $row->meta_value, PATHINFO_FILENAME );
					$stems[ $stem_key ] = $att_id;
				}
			}
		}

		return array( $attached, $stems );
	}

	/**
	 * Classify a single file
	 *
	 * @param string $rel_path Path relative to uploads (e.g. 2026/08/name.jpg)
	 * @param string $abs_path Local absolute path
	 * @param array  $att_map  rel_path => attachment_id index
	 * @param array  $stem_map stem => attachment_id index
	 * @return array
	 */
	private function classify_file( $rel_path, $abs_path, $att_map = array(), $stem_map = array() ) {
		$dir_rel  = dirname( $rel_path );
		if ( '.' === $dir_rel ) {
			$dir_rel = '';
		}
		$basename = basename( $rel_path );
		$ext      = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
		$stem     = pathinfo( $basename, PATHINFO_FILENAME );

		// 1. Match the attachment record in the database (exact rel_path, fall back to stem)
		$attachment_id = isset( $att_map[ $rel_path ] ) ? $att_map[ $rel_path ] : 0;
		if ( ! $attachment_id ) {
			$attachment_id = isset( $stem_map[ $stem ] ) ? $stem_map[ $stem ] : 0;
		}
		$attachment_id = (int) $attachment_id;

		// 2. HEAD-probe the bucket: try same-name .webp first, then -static.webp
		$webp_urls = array();
		if ( 'webp' !== $ext ) {
			$webp_urls[] = $this->build_webp_url( $dir_rel, $stem . '.webp' );
			$webp_urls[] = $this->build_webp_url( $dir_rel, $stem . '-static.webp' );
		} else {
			$webp_urls[] = $this->build_webp_url( $dir_rel, $basename );
		}
		$in_bucket = $this->head_exists_any( $webp_urls );

		// Compute thumbnail URL
		$upload_info = wp_upload_dir();
		$thumb_url   = '';
		if ( file_exists( $abs_path ) ) {
			$thumb_url = rtrim( $upload_info['baseurl'], '/' ) . '/' . ltrim( $rel_path, '/' );
		} elseif ( $in_bucket ) {
			$thumb_url = $webp_urls[0];
		}

		$item = array(
			'file'          => $rel_path,
			'basename'      => $basename,
			'ext'           => $ext,
			'size'          => file_exists( $abs_path ) ? filesize( $abs_path ) : 0,
			'local_exists'  => file_exists( $abs_path ),
			'attachment_id' => $attachment_id ? (int) $attachment_id : 0,
			'in_bucket'     => $in_bucket,
			'checked_url'   => $in_bucket ? $webp_urls[0] : '',
			'thumb_url'     => $thumb_url,
		);

		// 3. Classify
		if ( $in_bucket ) {
			// Class A: bucket has the file; local copy can be cleaned
			$item['status'] = 'cleanable';
			$item['reason'] = __( 'Corresponding file already exists in the bucket; local copy can be cleaned', 'wp-genius' );
		} elseif ( $attachment_id ) {
			// Class B: not offloaded, but media library has a record
			$item['status']      = 'not_offloaded';
			$item['reason']      = $this->analyze_reason( $attachment_id, $rel_path, $abs_path );
			$item['parent']      = $this->get_parent_info( $attachment_id );
			$item['can_enqueue'] = $this->can_enqueue( $attachment_id );
		} else {
			// Class C: orphan file (no record in database)
			$item['status'] = 'orphan';
			$item['reason'] = __( 'No matching record in the media library (orphan file)', 'wp-genius' );
		}

		return $item;
	}

	/**
	 * Build a /wp-media/ URL
	 *
	 * @param string $dir_rel Relative directory (e.g. 2026/08 or empty string)
	 * @param string $file    File name (name.webp)
	 * @return string
	 */
	private function build_webp_url( $dir_rel, $file ) {
		$home     = home_url();
		$path_rel = '' !== $dir_rel ? ltrim( $dir_rel, '/' ) . '/' . $file : $file;
		return rtrim( $home, '/' ) . '/wp-media/' . $path_rel;
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
		$attached_rel = str_replace( wp_upload_dir()['basedir'] . '/', '', (string) $attached );
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

		if ( ! class_exists( 'W2P_Media_Conversion_Logger' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-logger-service.php';
		}
		$logger = new W2P_Media_Conversion_Logger();

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
			if ( '.' === $dir_rel ) {
				$dir_rel = '';
			}
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

// Backward compatibility alias.
if ( ! class_exists( 'MediaEngineAuditService', false ) ) {
	class_alias( 'W2P_Media_Audit_Service', 'MediaEngineAuditService' );
}
