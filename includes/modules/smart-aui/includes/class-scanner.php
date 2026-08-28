<?php
/**
 * Smart AUI — External Media Scanner & Batch Grabber Service
 *
 * Scans posts for external media URLs using cursor pagination,
 * filters against site URLs and exclusion rules, and leverages
 * the existing Smart AUI image processing engine to download
 * and replace external media in batches.
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_SmartAUI_Scanner_Service
 */
class W2P_SmartAUI_Scanner_Service {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_SmartAUIModule
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param W2P_SmartAUIModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * Scan posts for external media URLs using cursor pagination
	 *
	 * @param int   $limit Maximum number of posts to inspect per batch.
	 * @param int   $last_id Starting post ID cursor (exclusive).
	 * @param array $options Additional options.
	 * @return array
	 */
	public function scan_posts( int $limit = 100, int $last_id = 0, array $options = array() ): array {
		global $wpdb;

		$limit   = max( 10, min( 1000, $limit ) );
		$last_id = max( 0, $last_id );

		$settings = $this->module->get_settings();

		// Get all public post types excluding attachments, revisions, etc.
		$all_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $all_types['attachment'], $all_types['revision'], $all_types['nav_menu_item'] );

		// Filter by excluded post types from settings
		$excluded_types = isset( $settings['smart_aui_exclude_post_types'] ) ? (array) $settings['smart_aui_exclude_post_types'] : array();
		$target_types   = array_values( array_diff( array_keys( $all_types ), $excluded_types ) );

		if ( empty( $target_types ) ) {
			return array(
				'posts'         => array(),
				'last_id'       => $last_id,
				'scanned_count' => 0,
				'has_more'      => false,
			);
		}

		$capture_videos    = ! empty( $settings['smart_aui_capture_videos'] );
		$type_placeholders = implode( ', ', array_fill( 0, count( $target_types ), '%s' ) );

		$content_clause = $capture_videos
			? "AND (post_content LIKE '%<img%' OR post_content LIKE '%<video%' OR post_content LIKE '%[video%')"
			: "AND post_content LIKE '%<img%'";

		// Build SQL with cursor pagination
		$query_args = array_merge( array( $last_id ), $target_types, array( $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT ID, post_title, post_type, post_status, post_date, post_content
				FROM {$wpdb->posts}
				WHERE ID > %d
				  AND post_status IN ('publish', 'draft', 'pending', 'future', 'private')
				  AND post_type IN ({$type_placeholders})
				  {$content_clause}
				ORDER BY ID ASC
				LIMIT %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) );

		if ( empty( $rows ) ) {
			return array(
				'posts'         => array(),
				'last_id'       => $last_id,
				'scanned_count' => 0,
				'has_more'      => false,
			);
		}

		$new_last_id   = $last_id;
		$pending_posts = array();

		// Get site and base URL information for domain checking
		$base_url    = ! empty( $settings['smart_aui_base_url'] ) ? $settings['smart_aui_base_url'] : site_url();
		$site_url    = site_url();
		$base_host   = strtolower( (string) wp_parse_url( $base_url, PHP_URL_HOST ) );
		$site_host   = strtolower( (string) wp_parse_url( $site_url, PHP_URL_HOST ) );

		$validator = \SmartAutoUploadImages\get_container()->get( 'image_validator' );

		foreach ( $rows as $row ) {
			$post_id     = (int) $row->ID;
			$new_last_id = max( $new_last_id, $post_id );

			$content = (string) $row->post_content;
			if ( empty( $content ) ) {
				continue;
			}

			$external_images = array();
			$seen_urls       = array();
			$post_data       = array(
				'ID'        => $post_id,
				'post_type' => $row->post_type,
			);

			// Fast parse images with WP_HTML_Tag_Processor
			if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
				$processor = new \WP_HTML_Tag_Processor( $content );
				while ( $processor->next_tag( 'img' ) ) {
					$src = (string) $processor->get_attribute( 'src' );
					if ( empty( $src ) || strpos( $src, 'data:' ) === 0 ) {
						continue;
					}

					// Protocol-relative URL normalize
					if ( strpos( $src, '//' ) === 0 ) {
						$src = 'https:' . $src;
					}

					// Fast host check
					$src_host = strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) );
					if ( empty( $src_host ) || $src_host === $site_host || $src_host === $base_host ) {
						continue;
					}

					// Check if already in our base_url / site_url
					if ( strpos( $src, $base_url ) === 0 || strpos( $src, $site_url ) === 0 ) {
						continue;
					}

					// Check exclusion rules via ImageValidator
					$validation = $validator->validate_image_url( $src, $post_data );
					if ( is_wp_error( $validation ) ) {
						$err_code = $validation->get_error_code();
						if ( in_array( $err_code, array( 'excluded_domain', 'internal_url', 'invalid_url' ), true ) ) {
							continue;
						}
					}

					if ( ! isset( $seen_urls[ $src ] ) ) {
						$seen_urls[ $src ]  = true;
						$external_images[] = $src;
					}
				}

				// Check videos if video capturing is active
				if ( $capture_videos ) {
					$video_processor = new \WP_HTML_Tag_Processor( $content );
					while ( $video_processor->next_tag( array( 'tag_name' => 'video' ) ) ) {
						$src = (string) $video_processor->get_attribute( 'src' );
						if ( ! empty( $src ) && strpos( $src, 'data:' ) !== 0 ) {
							if ( strpos( $src, '//' ) === 0 ) {
								$src = 'https:' . $src;
							}
							$src_host = strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) );
							if ( ! empty( $src_host ) && $src_host !== $site_host && $src_host !== $base_host && ! isset( $seen_urls[ $src ] ) ) {
								$seen_urls[ $src ]  = true;
								$external_images[] = $src;
							}
						}
					}
				}
			}

			if ( ! empty( $external_images ) ) {
				$edit_url = get_edit_post_link( $post_id, 'raw' );
				if ( empty( $edit_url ) ) {
					$edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
				}

				$title = ! empty( $row->post_title ) ? $row->post_title : sprintf( __( '(No title, ID: %d)', 'wp-genius' ), $post_id );

				$pending_posts[] = array(
					'id'             => $post_id,
					'title'          => $title,
					'type'           => $row->post_type,
					'status'         => $row->post_status,
					'date'           => $row->post_date,
					'external_count' => count( $external_images ),
					'sample_urls'    => array_slice( $external_images, 0, 3 ),
					'edit_url'       => $edit_url,
				);
			}
		}

		$has_more = count( $rows ) >= $limit;

		return array(
			'posts'         => $pending_posts,
			'last_id'       => $new_last_id,
			'scanned_count' => count( $rows ),
			'has_more'      => $has_more,
		);
	}

	/**
	 * Batch process external images for a list of post IDs
	 *
	 * @param array $post_ids Array of post IDs to process.
	 * @return array
	 */
	public function process_posts_batch( array $post_ids ): array {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		$post_ids = array_filter( array_map( 'absint', $post_ids ) );
		if ( empty( $post_ids ) ) {
			return array(
				'modified_posts'  => 0,
				'total_requested' => 0,
				'details'         => array(),
			);
		}

		$container = \SmartAutoUploadImages\get_container();
		$processor = $container->get( 'image_processor' );

		$modified_posts = 0;
		$details        = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				$details[] = array(
					'id'      => $post_id,
					'status'  => 'skipped',
					'message' => __( 'Post not found', 'wp-genius' ),
				);
				continue;
			}

			$post_data = array(
				'ID'           => $post->ID,
				'post_content' => $post->post_content,
				'post_title'   => $post->post_title,
				'post_status'  => $post->post_status,
				'post_date'    => $post->post_date,
			);

			$original_content  = $post->post_content;
			$processed_content = $processor->process_post_content( $original_content, $post_data );

			if ( false !== $processed_content && $processed_content !== $original_content ) {
				// Update post content
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $processed_content,
					)
				);

				// Clean post cache
				clean_post_cache( $post_id );

				// Auto set featured image if applicable
				$this->module->auto_set_featured_image( $post_id, $post );

				$modified_posts++;
				$details[] = array(
					'id'      => $post_id,
					'status'  => 'success',
					'message' => __( 'External media grabbed and replaced successfully', 'wp-genius' ),
				);
			} else {
				$details[] = array(
					'id'      => $post_id,
					'status'  => 'unchanged',
					'message' => __( 'No external media needed replacement', 'wp-genius' ),
				);
			}
		}

		return array(
			'modified_posts'  => $modified_posts,
			'total_requested' => count( $post_ids ),
			'details'         => $details,
		);
	}
}

