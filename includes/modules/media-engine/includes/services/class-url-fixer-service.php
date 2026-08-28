<?php
/**
 * Media Engine URL Fixer Service
 *
 * Scans post contents for leftover /wp-content/uploads/ references,
 * ignores external host URLs (e.g., https://weadown.com/wp-content/uploads/...),
 * probes the MinIO bucket (/wp-media/) via concurrent HEAD requests to determine
 * the correct path and file extension (e.g. converting .jpg/.png/.gif to .webp),
 * and performs safe, accurate batch replacements using high-performance cursor pagination.
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Media_Url_Fixer_Service {

	/**
	 * Supported image extensions to inspect and potentially convert to webp
	 */
	const IMAGE_EXTS = array( 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'avif' );

	/**
	 * In-memory cache for MinIO HEAD probe results ($url => HTTP status code)
	 *
	 * @var array<string, int>
	 */
	private $probe_cache = array();

	/**
	 * Conversion logger instance
	 *
	 * @var MediaEngineConversionLogger|null
	 */
	private $logger = null;

	/**
	 * Cached site host
	 *
	 * @var string
	 */
	private $site_host;

	/**
	 * Cached home host
	 *
	 * @var string
	 */
	private $home_host;

	/**
	 * Constructor
	 */
	public function __construct() {
		$site_url        = site_url();
		$home_url        = home_url();
		$this->site_host = strtolower( (string) parse_url( $site_url, PHP_URL_HOST ) );
		$this->home_host = strtolower( (string) parse_url( $home_url, PHP_URL_HOST ) );

		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-logger-service.php';
		}
		if ( class_exists( 'MediaEngineConversionLogger' ) ) {
			$this->logger = new MediaEngineConversionLogger();
		}
	}

	/**
	 * Check prerequisites before running URL fixer:
	 * 1. Ensure all media library attachments have been offloaded to MinIO.
	 * 2. Ensure wp-content/uploads/ has no local media files remaining (cleaned by Residual Media Audit).
	 *
	 * @param bool $force_refresh Whether to bypass transient cache
	 * @return array { passed: bool, not_offloaded_count: int, local_files_found: int, sample_local_files: array, messages: array }
	 */
	public function check_prerequisites( $force_refresh = false ) {
		global $wpdb;

		$cache_key = 'w2p_fixer_prereq_check';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && isset( $cached['passed'] ) ) {
				return $cached;
			}
		}

		// 1. Fast sample probe of recent 30 image attachments
		$recent_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'
			ORDER BY ID DESC LIMIT 30"
		);

		$not_offloaded = 0;
		if ( ! empty( $recent_ids ) ) {
			$id_list         = implode( ',', array_map( 'intval', $recent_ids ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$offloaded_count = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
				WHERE post_id IN ({$id_list})
				AND meta_key IN ('advmo_offloaded', '_is_minio_offloaded')
				AND meta_value = '1'"
			);
			if ( $offloaded_count < count( $recent_ids ) ) {
				$not_offloaded = count( $recent_ids ) - $offloaded_count;
			}
		}

		// 2. Fast check for local media files in wp-content/uploads/ (focused on YYYY/MM media dirs)
		$upload_dir         = wp_upload_dir();
		$basedir            = $upload_dir['basedir'];
		$local_files_found  = 0;
		$sample_local_files = array();
		$ignore_folders     = array( 'fonts', 'smile_fonts', 'elementor', 'js_composer', 'w2p-audit-index', 'wc-logs', 'woocommerce_uploads', 'dynamic_avia' );

		if ( is_dir( $basedir ) ) {
			$exts       = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp' );
			$dir_handle = @opendir( $basedir );
			if ( $dir_handle ) {
				while ( false !== ( $entry = readdir( $dir_handle ) ) ) {
					if ( '.' === $entry || '..' === $entry || in_array( $entry, $ignore_folders, true ) ) {
						continue;
					}
					$full = $basedir . '/' . $entry;
					if ( is_file( $full ) ) {
						$ext = strtolower( (string) pathinfo( $full, PATHINFO_EXTENSION ) );
						if ( in_array( $ext, $exts, true ) ) {
							$local_files_found++;
							$sample_local_files[] = $entry;
							break;
						}
					} elseif ( is_dir( $full ) && preg_match( '/^\d{4}$/', $entry ) ) {
						// Match Year directory (e.g., 2024, 2025, 2026)
						$sub_handle = @opendir( $full );
						if ( $sub_handle ) {
							while ( false !== ( $sub_entry = readdir( $sub_handle ) ) ) {
								if ( '.' === $sub_entry || '..' === $sub_entry ) {
									continue;
								}
								$sub_full = $full . '/' . $sub_entry;
								if ( is_file( $sub_full ) ) {
									$ext = strtolower( (string) pathinfo( $sub_full, PATHINFO_EXTENSION ) );
									if ( in_array( $ext, $exts, true ) ) {
										$local_files_found++;
										$sample_local_files[] = $entry . '/' . $sub_entry;
										break 2;
									}
								} elseif ( is_dir( $sub_full ) ) {
									// Month directory (e.g. 01, 08, etc.)
									$sub2_handle = @opendir( $sub_full );
									if ( $sub2_handle ) {
										while ( false !== ( $sub2_entry = readdir( $sub2_handle ) ) ) {
											if ( '.' === $sub2_entry || '..' === $sub2_entry ) {
												continue;
											}
											$sub2_full = $sub_full . '/' . $sub2_entry;
											if ( is_file( $sub2_full ) ) {
												$ext = strtolower( (string) pathinfo( $sub2_full, PATHINFO_EXTENSION ) );
												if ( in_array( $ext, $exts, true ) ) {
													$local_files_found++;
													$sample_local_files[] = $entry . '/' . $sub_entry . '/' . $sub2_entry;
													break 3;
												}
											}
										}
										closedir( $sub2_handle );
									}
								}
							}
							closedir( $sub_handle );
						}
					}
				}
				closedir( $dir_handle );
			}
		}

		$messages = array();
		if ( $not_offloaded > 0 ) {
			$messages[] = __( 'Warning: Un-offloaded media attachments detected in library. Please complete Step 1 (Batch Conversion & Offload) first.', 'wp-genius' );
		}

		if ( $local_files_found > 0 ) {
			$messages[] = sprintf(
				/* translators: %s: sample residual local file */
				__( 'Warning: Local residual media file detected in wp-content/uploads/ (%s). Please complete Step 2 (Residual Media Audit) cleanup first.', 'wp-genius' ),
				implode( ', ', $sample_local_files )
			);
		}

		$passed = ( $not_offloaded === 0 && $local_files_found === 0 );

		$result = array(
			'passed'              => $passed,
			'not_offloaded_count' => $not_offloaded,
			'local_files_found'   => $local_files_found,
			'sample_local_files'  => $sample_local_files,
			'messages'            => $messages,
		);

		set_transient( $cache_key, $result, 600 );

		return $result;
	}

	/**
	 * Get statistics about posts needing URL fixes
	 *
	 * Uses cached count or fast estimation to prevent locking the database.
	 *
	 * @param bool $include_revisions Whether to include revisions
	 * @param bool $force_refresh     Whether to force a fresh count
	 * @return array
	 */
	public function get_stats( $include_revisions = false, $force_refresh = false ) {
		global $wpdb;

		$cache_key = 'w2p_fixer_stats_' . ( $include_revisions ? 'all' : 'main' );
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && isset( $cached['host_uploads_posts'] ) ) {
				return $cached;
			}
		}

		$type_clause = $include_revisions ? "post_status NOT IN ('trash', 'auto-draft')" : "post_type IN ('post', 'page') AND post_status NOT IN ('trash', 'auto-draft')";

		$host_param = '%' . $wpdb->esc_like( $this->site_host . '/wp-content/uploads/' ) . '%';
		$host_posts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s AND {$type_clause}",
				$host_param
			)
		);

		$stats = array(
			'total_uploads_posts' => $host_posts,
			'host_uploads_posts'  => $host_posts,
			'site_host'           => $this->site_host,
		);

		set_transient( $cache_key, $stats, 600 );

		return $stats;
	}

	/**
	 * Get pending post IDs that need inspection/fixing using cursor pagination
	 *
	 * @param int  $limit             Limit per batch (default 20)
	 * @param int  $last_id           Cursor ID (0 for starting from the latest post, or < last_id)
	 * @param bool $include_revisions Whether to include revisions
	 * @return array List of post IDs
	 */
	public function get_pending_post_ids( $limit = 20, $last_id = 0, $include_revisions = false ) {
		global $wpdb;

		$limit   = max( 1, min( 100, (int) $limit ) );
		$last_id = max( 0, (int) $last_id );

		$type_clause = $include_revisions ? "post_status NOT IN ('trash', 'auto-draft')" : "post_type IN ('post', 'page') AND post_status NOT IN ('trash', 'auto-draft')";

		if ( $last_id > 0 ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE ID < %d
				AND post_content LIKE '%%wp-content/uploads/%%'
				AND {$type_clause}
				ORDER BY ID DESC
				LIMIT %d",
				$last_id,
				$limit
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_content LIKE '%%wp-content/uploads/%%'
				AND {$type_clause}
				ORDER BY ID DESC
				LIMIT %d",
				$limit
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( 'intval', $wpdb->get_col( $query ) );
	}

	/**
	 * Scan posts containing wp-content/uploads using cursor
	 *
	 * @param int  $limit             Batch size
	 * @param int  $last_id           Cursor (last seen post ID)
	 * @param bool $include_revisions Whether to include revisions
	 * @return array { posts: array, last_id: int, count: int }
	 */
	public function scan_posts( $limit = 20, $last_id = 0, $include_revisions = false ) {
		$post_ids = $this->get_pending_post_ids( $limit, $last_id, $include_revisions );
		if ( empty( $post_ids ) ) {
			return array(
				'posts'   => array(),
				'last_id' => 0,
				'count'   => 0,
			);
		}

		$results     = array();
		$min_post_id = $last_id;

		foreach ( $post_ids as $id ) {
			$min_post_id = ( $min_post_id === 0 || $id < $min_post_id ) ? $id : $min_post_id;
			$post        = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$inspection = $this->inspect_post( $id );
			$results[]  = array(
				'id'              => (int) $id,
				'title'           => $post->post_title ? $post->post_title : sprintf( __( '(Post #%d)', 'wp-genius' ), $id ),
				'type'            => $post->post_type,
				'date'            => $post->post_date,
				'edit_url'        => get_edit_post_link( $id ),
				'local_url_count' => count( $inspection['local_urls'] ),
				'external_count'  => count( $inspection['external_urls'] ),
				'fixable_count'   => count( $inspection['replacements'] ),
				'has_ext_fix'     => $inspection['has_ext_fix'],
				'has_path_fix'    => $inspection['has_path_fix'],
				'sample_fixes'    => array_slice( $inspection['replacements'], 0, 5 ),
			);
		}

		return array(
			'posts'   => $results,
			'last_id' => $min_post_id,
			'count'   => count( $results ),
		);
	}

	/**
	 * Inspect a single post and analyze all its uploads URLs
	 *
	 * @param int $post_id Post ID
	 * @return array Detailed inspection report
	 */
	public function inspect_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return array(
				'post_id'       => $post_id,
				'local_urls'    => array(),
				'external_urls' => array(),
				'replacements'  => array(),
				'has_path_fix'  => false,
				'has_ext_fix'   => false,
			);
		}

		$content = $post->post_content;

		// Match all URLs containing wp-content/uploads (handles normal slashes and escaped \/ in JSON)
		$pattern = '#(?:https?:)?(?:\\\\/|/){2}[^\s"\'<>\)\'\",]+(?:\\\\/|/)wp-content(?:\\\\/|/)uploads(?:\\\\/|/)[^\s"\'<>\)\'\",]+|(?:\\\\/|/)wp-content(?:\\\\/|/)uploads(?:\\\\/|/)[^\s"\'<>\)\'\",]+#i';
		preg_match_all( $pattern, $content, $matches );

		$raw_urls = ! empty( $matches[0] ) ? array_unique( $matches[0] ) : array();

		$local_urls    = array();
		$external_urls = array();
		$probe_targets = array();

		foreach ( $raw_urls as $raw_url ) {
			// Normalize slashes for analysis
			$clean_url = str_replace( '\\/', '/', $raw_url );
			// Remove trailing punctuations or HTML entities if captured
			$clean_url = preg_replace( '/[;,.]*$/', '', $clean_url );

			if ( $this->is_local_url( $clean_url ) ) {
				$parsed = $this->parse_upload_url( $clean_url );
				if ( $parsed ) {
					$local_urls[ $raw_url ] = $parsed;
					// Prepare candidate probe URLs for MinIO
					foreach ( $parsed['probe_urls'] as $p_url ) {
						$probe_targets[] = $p_url;
					}
				}
			} else {
				$external_urls[] = $clean_url;
			}
		}

		// Perform batch concurrent HEAD probes for all targets
		if ( ! empty( $probe_targets ) ) {
			$this->probe_urls_concurrent( array_unique( $probe_targets ) );
		}

		// Resolve final replacements
		$replacements = array();
		$has_path_fix = false;
		$has_ext_fix  = false;

		foreach ( $local_urls as $raw_url => $parsed ) {
			$resolution = $this->resolve_replacement( $raw_url, $parsed );
			if ( $resolution && $resolution['new_url'] !== $resolution['old_url'] ) {
				$replacements[] = $resolution;
				if ( $resolution['ext_changed'] ) {
					$has_ext_fix = true;
				} else {
					$has_path_fix = true;
				}
			}
		}

		return array(
			'post_id'       => (int) $post_id,
			'local_urls'    => $local_urls,
			'external_urls' => $external_urls,
			'replacements'  => $replacements,
			'has_path_fix'  => $has_path_fix,
			'has_ext_fix'   => $has_ext_fix,
		);
	}

	/**
	 * Fix URLs in a single post
	 *
	 * @param int  $post_id Post ID
	 * @param bool $dry_run Whether to only simulate without modifying DB
	 * @return array Result of the fix operation
	 */
	public function fix_post( $post_id, $dry_run = false ) {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return array(
				'success'  => true,
				'post_id'  => $post_id,
				'replaced' => 0,
				'changes'  => array(),
				'reason'   => 'empty_content',
			);
		}

		$inspection = $this->inspect_post( $post_id );
		if ( empty( $inspection['replacements'] ) ) {
			return array(
				'success'  => true,
				'post_id'  => $post_id,
				'replaced' => 0,
				'changes'  => array(),
				'reason'   => 'no_local_replacements_needed',
			);
		}

		$content       = $post->post_content;
		$total_replace = 0;
		$changes_done  = array();

		foreach ( $inspection['replacements'] as $item ) {
			$old_raw = $item['old_raw'];
			$new_raw = $item['new_raw'];

			if ( $old_raw === $new_raw ) {
				continue;
			}

			// Direct exact string replacement
			$count   = 0;
			$content = str_replace( $old_raw, $new_raw, $content, $count );
			if ( $count > 0 ) {
				$total_replace += $count;
				$changes_done[] = array(
					'old'         => $item['old_url'],
					'new'         => $item['new_url'],
					'count'       => $count,
					'type'        => $item['type'],
					'ext_changed' => $item['ext_changed'],
				);
			}
		}

		if ( $total_replace === 0 || $content === $post->post_content ) {
			return array(
				'success'  => true,
				'post_id'  => $post_id,
				'replaced' => 0,
				'changes'  => array(),
				'reason'   => 'no_string_change',
			);
		}

		if ( ! $dry_run ) {
			// Persist to database
			$updated = $wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $content ),
				array( 'ID' => $post_id )
			);

			if ( false === $updated ) {
				return array(
					'success' => false,
					'post_id' => $post_id,
					'error'   => 'Database update failed: ' . $wpdb->last_error,
				);
			}

			// Clean cache without triggering heavy SuperCache flush warnings
			wp_cache_delete( $post_id, 'posts' );
			wp_cache_delete( $post_id, 'post_meta' );

			if ( $this->logger ) {
				$this->logger->log_debug(
					sprintf(
						'URL Fixer: Post #%d updated with %d replacement(s)',
						$post_id,
						$total_replace
					)
				);
			}
		}

		return array(
			'success'  => true,
			'post_id'  => $post_id,
			'dry_run'  => $dry_run,
			'replaced' => $total_replace,
			'changes'  => $changes_done,
		);
	}

	/**
	 * Batch fix an array of post IDs
	 *
	 * @param array $post_ids Array of Post IDs
	 * @param bool  $dry_run  Whether to perform dry-run
	 * @return array Batch execution summary
	 */
	public function fix_batch( $post_ids, $dry_run = false ) {
		$results = array(
			'success'         => true,
			'total_posts'     => count( $post_ids ),
			'modified_posts'  => 0,
			'total_replaced'  => 0,
			'ext_fixed'       => 0,
			'path_only_fixed' => 0,
			'details'         => array(),
		);

		foreach ( $post_ids as $id ) {
			$id  = (int) $id;
			$res = $this->fix_post( $id, $dry_run );
			if ( ! empty( $res['success'] ) && ! empty( $res['replaced'] ) ) {
				$results['modified_posts']++;
				$results['total_replaced'] += $res['replaced'];
				foreach ( $res['changes'] as $ch ) {
					if ( ! empty( $ch['ext_changed'] ) ) {
						$results['ext_fixed'] += $ch['count'];
					} else {
						$results['path_only_fixed'] += $ch['count'];
					}
				}
			}
			$results['details'][ $id ] = $res;
		}

		return $results;
	}

	/**
	 * Check if a URL belongs to the local site or is a relative URL
	 *
	 * @param string $url URL string
	 * @return bool
	 */
	private function is_local_url( $url ) {
		if ( strpos( $url, '://' ) === false && strpos( $url, '//' ) !== 0 ) {
			// Relative path like /wp-content/uploads/...
			return ( strpos( $url, '/wp-content/uploads/' ) === 0 || strpos( $url, 'wp-content/uploads/' ) === 0 );
		}

		// Absolute or protocol-relative URL
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		if ( empty( $host ) ) {
			return true;
		}

		return ( $host === $this->site_host || $host === $this->home_host || $host === 'localhost' || $host === '127.0.0.1' );
	}

	/**
	 * Parse an uploads URL into components and candidate MinIO probe paths
	 *
	 * @param string $clean_url Clean URL with normal slashes
	 * @return array|null Parsed data or null if invalid
	 */
	private function parse_upload_url( $clean_url ) {
		$pos = strpos( $clean_url, '/wp-content/uploads/' );
		if ( false === $pos ) {
			return null;
		}

		$rel_path = substr( $clean_url, $pos + strlen( '/wp-content/uploads/' ) );
		if ( empty( $rel_path ) ) {
			return null;
		}

		$ext      = strtolower( (string) pathinfo( $rel_path, PATHINFO_EXTENSION ) );
		$stem     = pathinfo( $rel_path, PATHINFO_FILENAME );
		$dir      = dirname( $rel_path );
		$dir_part = ( $dir && '.' !== $dir ) ? trailingslashit( $dir ) : '';

		$home = home_url();

		// Build MinIO candidate probe URLs
		$probe_urls = array();
		if ( 'webp' !== $ext ) {
			$probe_urls['webp']   = rtrim( $home, '/' ) . '/wp-media/' . $dir_part . $stem . '.webp';
			$probe_urls['static'] = rtrim( $home, '/' ) . '/wp-media/' . $dir_part . $stem . '-static.webp';
			$probe_urls['orig']   = rtrim( $home, '/' ) . '/wp-media/' . $rel_path;
		} else {
			$probe_urls['orig']   = rtrim( $home, '/' ) . '/wp-media/' . $rel_path;
		}

		return array(
			'clean_url'  => $clean_url,
			'rel_path'   => $rel_path,
			'dir_part'   => $dir_part,
			'stem'       => $stem,
			'ext'        => $ext,
			'probe_urls' => $probe_urls,
		);
	}

	/**
	 * Resolve the replacement for a matched upload URL
	 *
	 * @param string $raw_url Raw URL as found in content (may contain \/)
	 * @param array  $parsed  Parsed components
	 * @return array Resolution details
	 */
	private function resolve_replacement( $raw_url, $parsed ) {
		$is_escaped = ( strpos( $raw_url, '\\/' ) !== false );
		$slash      = $is_escaped ? '\\/' : '/';

		$dir_part = $parsed['dir_part'];
		if ( $is_escaped ) {
			$dir_part = str_replace( '/', '\\/', $dir_part );
		}

		$stem       = $parsed['stem'];
		$ext        = $parsed['ext'];
		$probe_urls = $parsed['probe_urls'];

		// Decision based on MinIO probe results
		$target_ext  = $ext;
		$target_stem = $stem;
		$ext_changed = false;
		$status_desc = '';

		if ( isset( $probe_urls['webp'] ) && isset( $this->probe_cache[ $probe_urls['webp'] ] ) && 200 === $this->probe_cache[ $probe_urls['webp'] ] ) {
			// WebP confirmed in MinIO bucket
			$target_ext  = 'webp';
			$ext_changed = true;
			$status_desc = 'bucket_webp_200';
		} elseif ( isset( $probe_urls['static'] ) && isset( $this->probe_cache[ $probe_urls['static'] ] ) && 200 === $this->probe_cache[ $probe_urls['static'] ] ) {
			// -static.webp confirmed in MinIO bucket
			$target_stem = $stem . '-static';
			$target_ext  = 'webp';
			$ext_changed = true;
			$status_desc = 'bucket_static_webp_200';
		} elseif ( isset( $probe_urls['orig'] ) && isset( $this->probe_cache[ $probe_urls['orig'] ] ) && 200 === $this->probe_cache[ $probe_urls['orig'] ] ) {
			// Original format confirmed in MinIO bucket
			$target_ext  = $ext;
			$ext_changed = false;
			$status_desc = 'bucket_orig_200';
		} else {
			// Default rule: offloaded non-webp images converted to .webp
			if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'bmp' ), true ) ) {
				$target_ext  = 'webp';
				$ext_changed = true;
				$status_desc = 'fallback_webp';
			} else {
				$target_ext  = $ext;
				$ext_changed = false;
				$status_desc = 'fallback_orig';
			}
		}

		$new_rel_file = ( $dir_part ? $dir_part : '' ) . $target_stem . '.' . $target_ext;

		// Build new raw URL
		if ( strpos( $raw_url, '://' ) !== false || strpos( $raw_url, '//' ) === 0 ) {
			// Has scheme / host
			$search_str  = $is_escaped ? 'wp-content\/uploads\/' . str_replace( '/', '\\/', $parsed['rel_path'] ) : 'wp-content/uploads/' . $parsed['rel_path'];
			$replace_str = $is_escaped ? 'wp-media\/' . $new_rel_file : 'wp-media/' . $new_rel_file;
			$new_raw     = str_replace( $search_str, $replace_str, $raw_url );
		} else {
			// Relative
			$new_raw = $slash . 'wp-media' . $slash . $new_rel_file;
		}

		$clean_old = str_replace( '\\/', '/', $raw_url );
		$clean_new = str_replace( '\\/', '/', $new_raw );

		return array(
			'old_raw'     => $raw_url,
			'new_raw'     => $new_raw,
			'old_url'     => $clean_old,
			'new_url'     => $clean_new,
			'ext_changed' => $ext_changed,
			'type'        => $ext_changed ? 'path_and_ext' : 'path_only',
			'status'      => $status_desc,
		);
	}

	/**
	 * Probe multiple URLs concurrently with cURL HEAD requests and cache the status codes
	 *
	 * @param array $urls Array of absolute URLs to probe
	 */
	private function probe_urls_concurrent( $urls ) {
		if ( empty( $urls ) || ! function_exists( 'curl_init' ) ) {
			return;
		}

		$to_probe = array();
		foreach ( $urls as $url ) {
			if ( ! isset( $this->probe_cache[ $url ] ) ) {
				$to_probe[] = $url;
			}
		}

		if ( empty( $to_probe ) ) {
			return;
		}

		$mh      = curl_multi_init();
		$handles = array();

		foreach ( $to_probe as $url ) {
			$ch = curl_init( $url );
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_NOBODY         => true,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => 2,
					CURLOPT_CONNECTTIMEOUT => 1,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => 0,
					CURLOPT_USERAGENT      => 'WPGenius-UrlFixer/1.0',
				)
			);
			curl_multi_add_handle( $mh, $ch );
			$handles[ $url ] = $ch;
		}

		// Run concurrent probes
		$running = null;
		do {
			$status = curl_multi_exec( $mh, $running );
			if ( $running ) {
				curl_multi_select( $mh, 0.1 );
			}
		} while ( $running && CURLM_OK === $status );

		foreach ( $handles as $url => $ch ) {
			$code                       = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
			$this->probe_cache[ $url ] = $code;
			curl_multi_remove_handle( $mh, $ch );
			curl_close( $ch );
		}

		curl_multi_close( $mh );
	}
}

// Backward compatibility alias.
if ( ! class_exists( 'MediaEngineUrlFixerService', false ) ) {
	class_alias( 'W2P_Media_Url_Fixer_Service', 'MediaEngineUrlFixerService' );
}
