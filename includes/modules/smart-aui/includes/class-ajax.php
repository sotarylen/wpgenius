<?php
/**
 * Smart AUI — AJAX Handlers
 *
 * Handles all AJAX endpoints (progress/content processing/download/bulk/logs/video).
 * Split from module.php (refactored from the original God class).
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_SmartAUI_Ajax
 */
class W2P_SmartAUI_Ajax {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_SmartAUIModule
	 */
	private $module;

	/**
	 * Content processor (shared helper).
	 *
	 * @var W2P_SmartAUI_Content_Processor
	 */
	private $processor;

	/**
	 * Constructor.
	 *
	 * @param W2P_SmartAUIModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module    = $module;
		$this->processor = new W2P_SmartAUI_Content_Processor( $module );
	}

	/**
	 * AJAX Get Progress
	 */
	public function ajax_get_progress() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( $_POST['process_id'] ) : '';

		// Get progress information
		$progress = W2P_Smart_AUI_Progress_Tracker::get_progress( null, $process_id );

		if ( ! $progress ) {
			$progress = array(
				'status'      => 'idle',
				'total'       => 0,
				'processed'   => 0,
				'success'     => 0,
				'failed'      => 0,
				'current_url' => '',
			);
		}

		wp_send_json_success( $progress );
	}
	/**
	 * AJAX Process Content (Async)
	 */
	public function ajax_process_content() {
		// Close session write to allow concurrent requests (fixes progress bar freezing)
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$content    = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( $_POST['process_id'] ) : '';

		if ( empty( $content ) ) {
			wp_send_json_error( 'No content to process' );
		}

		// Create mock post data
		$post_data = array(
			'ID'           => $post_id,
			'post_content' => $content,
			'post_title'   => get_the_title( $post_id ),
		);

		// Set process ID for tracker
		if ( ! empty( $process_id ) ) {
			$tracker = W2P_Smart_AUI_Progress_Tracker::get_instance();
			$tracker->set_process_id( $process_id );
		}

		// Get processor
		$container = \SmartAutoUploadImages\get_container();
		$processor = $container->get( 'image_processor' );

		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( $_POST['target_url'] ) : '';

		// Process content
		// Note: Actions hooked in ImageProcessorExtended will handle progress updates
		$processed_content = $processor->process_post_content( $content, $post_data, $target_url );

		// Get final progress status
		$progress = W2P_Smart_AUI_Progress_Tracker::get_progress( null, $process_id );

		$response = array(
			'processed_content' => $processed_content ? $processed_content : $content,
			'stats'             => $progress,
		);

		wp_send_json_success( $response );
	}
	/**
	 * AJAX Download Single Image (Multi-thread friendly)
	 *
	 * Only downloads remote images and creates media library attachments; does not modify post content directly.
	 * The frontend replaces the URL in the editor content after receiving the response, avoiding race conditions from concurrent content modification.
	 */
	public function ajax_download_image() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$raw_url    = isset( $_POST['image_url'] ) ? wp_unslash( $_POST['image_url'] ) : '';
		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( wp_unslash( $_POST['process_id'] ) ) : '';

		// [FIX] Strict check for relative paths
		// esc_url_raw converts "attachment/..." to "http://attachment/...", causing is_external_url to fail.
		// We only want to process absolute URLs or protocol-relative URLs.
		if ( ! empty( $raw_url ) && ! preg_match( '/^(https?:)?\/\//i', $raw_url ) && substr( $raw_url, 0, 5 ) !== 'data:' ) {
			wp_send_json_success(
				array(
					'source_url'     => $raw_url,
					'downloaded_url' => $raw_url,
					'skipped'        => true,
					'process_id'     => $process_id,
					'message'        => 'Skipped: Relative path',
				)
			);
		}

		$image_url = esc_url_raw( $raw_url );

		if ( ! $post_id || empty( $image_url ) ) {
			wp_send_json_error(
				array(
					'message' => 'Invalid request',
				)
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error(
				array(
					'message' => 'Post not found',
				)
			);
		}

		// Check post status - do not process if in trash or auto-draft (likely being deleted or just created)
		if ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			wp_send_json_error( 'Post is in trash or auto-draft, skipping image download.' );
		}

		$post_data = array(
			'ID'           => $post->ID,
			'post_content' => $post->post_content,
			'post_title'   => $post->post_title,
			'post_status'  => $post->post_status,
		);

		// Skip local images directly
		$settings = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url = ! empty( $settings['base_url'] ) ? $settings['base_url'] : site_url();
		$site_url = site_url();

		if ( strpos( $image_url, $base_url ) === 0 || strpos( $image_url, $site_url ) === 0 ) {
			// If it is a local image, try to find its ID
			$attachment_id = $this->processor->get_attachment_id_from_url( $image_url );

			if ( $attachment_id ) {
				// Found the ID, return success status so the frontend can fill in the class
				wp_send_json_success(
					array(
						'source_url'     => $image_url,
						'downloaded_url' => $image_url,
						'attachment_id'  => $attachment_id,
						'skipped'        => false, // Set to false so the frontend enters the success branch
						'process_id'     => $process_id,
						'message'        => 'Local image ID resolved',
					)
				);
			}

			// No ID found and it is a local image, skip
			wp_send_json_success(
				array(
					'source_url'     => $image_url,
					'downloaded_url' => $image_url,
					'skipped'        => true,
					'process_id'     => $process_id,
					'message'        => 'Skipped: Local image without ID',
				)
			);
		}

		// [FIX 8] Check whether the domain is excluded or the URL is an internal link
		$container  = \SmartAutoUploadImages\get_container();
		$validator  = new \SmartAutoUploadImages\Services\ImageValidator();
		$validation = $validator->validate_image_url( $image_url, $post_data );

		if ( is_wp_error( $validation ) ) {
			$error_code = $validation->get_error_code();
			// Treat these as skips, not failures
			if ( 'excluded_domain' === $error_code || 'internal_url' === $error_code || 'invalid_url' === $error_code ) {
				wp_send_json_success(
					array(
						'source_url'     => $image_url,
						'downloaded_url' => $image_url,
						'skipped'        => true,
						'process_id'     => $process_id,
						'message'        => 'Skipped: ' . $error_code,
					)
				);
			}
		}

		$downloader = $container->get( 'image_downloader' );

		// Read the retry count setting and clamp it to avoid an infinite loop
		$settings    = \SmartAutoUploadImages\Plugin::get_settings();
		$max_retries = isset( $settings['max_retries'] ) ? max( 0, min( 10, (int) $settings['max_retries'] ) ) : 3;
		$attempt     = 0;
		$result      = null;

		do {
			++$attempt;

			try {
				$result = $downloader->download_image(
					array(
						'url'   => $image_url,
						'alt'   => '',
						'title' => '',
					),
					$post_data
				);
			} catch ( \Throwable $e ) {
				$result = new \WP_Error( 'smart_aui_download_exception', $e->getMessage() );
			}

			if ( ! is_wp_error( $result ) ) {
				break;
			}

			$err_code = $result->get_error_code();

			// If the error is non-retryable (size, mime, excluded, etc.), break immediately
			if ( in_array( $err_code, array( 'previously_failed', 'image_too_small', 'invalid_file_type', 'corrupted_image', 'excluded_domain', 'internal_url', 'invalid_url' ), true ) ) {
				break;
			}

			if ( $attempt > $max_retries ) {
				break;
			}

			$delay = min( $attempt, 3 );
			sleep( $delay );
		} while ( $attempt <= $max_retries );

		if ( is_wp_error( $result ) ) {
			$error_code = $result->get_error_code();
			$error_msg  = $result->get_error_message();

			// If it was skipped because it previously failed or image is too small, return SUCCESS with skipped=true
			if ( 'previously_failed' === $error_code || 'image_too_small' === $error_code ) {
				wp_send_json_success(
					array(
						'source_url'     => $image_url,
						'downloaded_url' => $image_url,
						'skipped'        => true,
						'error_code'     => $error_code,
						'process_id'     => $process_id,
						'message'        => $error_msg,
					)
				);
			}

			// Only add to failed list for actual download/network/corruption failures after all retries are exhausted
			$failed_manager = $container->get( 'failed_images_manager' );
			if ( $failed_manager && in_array( $error_code, array( 'network_error', 'http_error', 'corrupted_image', 'invalid_file_type' ), true ) ) {
				$failed_manager->add_failed_url( $image_url );
			}

			// Return a failed status (not using wp_send_json_error, so the frontend does not treat it as an AJAX fatal error)
			wp_send_json_success(
				array(
					'source_url'     => $image_url,
					'downloaded_url' => $image_url,
					'failed'         => true,
					'error_code'     => $error_code,
					'process_id'     => $process_id,
					'message'        => $error_msg,
				)
			);
		}

		// Use the same domain mapping rules as the main processing flow
		$settings = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url = trim( $settings['base_url'], '/' );
		$new_url  = $result['url'];

		if ( ! empty( $new_url ) && ! empty( $base_url ) ) {
			$new_url_parts = wp_parse_url( $new_url );
			if ( ! empty( $new_url_parts['path'] ) ) {
				$new_url = $base_url . $new_url_parts['path'];
			}
		}

		$response = array(
			'source_url'     => $image_url,
			'downloaded_url' => $new_url,
			'attachment_id'  => isset( $result['attachment_id'] ) ? (int) $result['attachment_id'] : 0,
			'alt_text'       => isset( $result['alt_text'] ) ? $result['alt_text'] : '',
			'process_id'     => $process_id,
		);

		// Try to auto-set the featured image
		if ( ! empty( $response['attachment_id'] ) ) {
			$this->processor->auto_set_featured_image( $post_id );
		}

		// [MEMORY CLEANUP] Free memory after each image download (critical for concurrent processing)
		unset( $result, $downloader, $validator, $container, $post_data );
		gc_collect_cycles();

		wp_send_json_success( $response );
	}
	/**
	 * AJAX Bulk Process (Server-side)
	 */
	public function ajax_bulk_process() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( $_POST['process_id'] ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid Post ID' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( 'Post not found' );
		}

		// Set process ID for tracker
		if ( ! empty( $process_id ) ) {
			$tracker = W2P_Smart_AUI_Progress_Tracker::get_instance();
			$tracker->set_process_id( $process_id );
		}

		$container = \SmartAutoUploadImages\get_container();
		$processor = $container->get( 'image_processor' );

		$post_data = array(
			'ID'           => $post->ID,
			'post_content' => $post->post_content,
			'post_title'   => $post->post_title,
			'post_status'  => $post->post_status,
		);

		$processed_content = $processor->process_post_content( $post->post_content, $post_data );

		if ( $processed_content !== false && $processed_content !== $post->post_content ) {
			// Use wp_update_post to ensure hooks are triggered
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $processed_content,
				)
			);

			// Clean post cache
			clean_post_cache( $post_id );

			// Try to auto-set the featured image
			$this->processor->auto_set_featured_image( $post_id );
		}

		$progress = W2P_Smart_AUI_Progress_Tracker::get_progress( null, $process_id );
		if ( ! is_array( $progress ) ) {
			$progress = array();
		}
		$progress['content'] = get_post_field( 'post_content', $post_id );

		wp_send_json_success( array( 'stats' => $progress ) );
	}
	/**
	 * AJAX Process All Content (No Progress UI)
	 */
	public function ajax_process_all() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$images  = isset( $_POST['images'] ) ? array_map( 'esc_url_raw', (array) $_POST['images'] ) : array();

		if ( empty( $content ) ) {
			wp_send_json_error( 'No content to process' );
		}

		// Create mock post data
		$post_data = array(
			'ID'           => $post_id,
			'post_content' => $content,
			'post_title'   => get_the_title( $post_id ),
		);

		// Get processor
		$container = \SmartAutoUploadImages\get_container();
		$processor = $container->get( 'image_processor' );

		// Process all images in one request
		$processed_content = $processor->process_post_content( $content, $post_data );

		wp_send_json_success(
			array(
				'processed_content' => $processed_content ? $processed_content : $content,
				'message'           => 'All images processed successfully',
			)
		);
	}
	/**
	 * AJAX Get Settings
	 */
	public function ajax_get_settings() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$settings = get_option( 'smart_aui_settings', array() );

		// Return settings but without sensitive information
		$safe_settings = array(
			'auto_set_featured_image'    => isset( $settings['auto_set_featured_image'] ) ? (bool) $settings['auto_set_featured_image'] : true,
			'show_progress_ui'           => isset( $settings['show_progress_ui'] ) ? (bool) $settings['show_progress_ui'] : true,
			'process_images_on_rest_api' => isset( $settings['process_images_on_rest_api'] ) ? (bool) $settings['process_images_on_rest_api'] : true,
			'domain_exclusions'          => isset( $settings['domain_exclusions'] ) ? $settings['domain_exclusions'] : array(),
			'featured_image_pattern'     => isset( $settings['featured_image_pattern'] ) ? $settings['featured_image_pattern'] : '',
			'smart_filename_pattern'     => isset( $settings['smart_filename_pattern'] ) ? $settings['smart_filename_pattern'] : '',
			'overwrite_existing_files'   => isset( $settings['overwrite_existing_files'] ) ? (bool) $settings['overwrite_existing_files'] : false,
			'download_timeout'           => isset( $settings['download_timeout'] ) ? intval( $settings['download_timeout'] ) : 30,
			'max_file_size'              => isset( $settings['max_file_size'] ) ? intval( $settings['max_file_size'] ) : 5,
			'allowed_extensions'         => isset( $settings['allowed_extensions'] ) ? $settings['allowed_extensions'] : array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ),
			'concurrent_threads'         => isset( $settings['concurrent_threads'] ) ? intval( $settings['concurrent_threads'] ) : 4,
			'max_retries'                => isset( $settings['max_retries'] ) ? intval( $settings['max_retries'] ) : 3,
		);

		wp_send_json_success( $safe_settings );
	}
	/**
	 * AJAX Get Post Details
	 */
	public function ajax_get_post_details() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid Post ID' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( 'Post not found' );
		}

		wp_send_json_success(
			array(
				'ID'           => $post->ID,
				'post_title'   => $post->post_title,
				'post_content' => $post->post_content,
			)
		);
	}
	/**
	 * AJAX Save Post Content
	 */
	public function ajax_save_post_content() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id     = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$content     = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$post_status = isset( $_POST['post_status'] ) ? sanitize_text_field( $_POST['post_status'] ) : null;

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid Post ID' );
		}

		// Prepare the update data
		$update_data = array(
			'ID'           => $post_id,
			'post_content' => $content,
		);

		// If a status is specified, update the status too
		if ( $post_status && in_array( $post_status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
			$update_data['post_status'] = $post_status;

			// If publishing, need to update the publication time
			if ( $post_status === 'publish' ) {
				$post = get_post( $post_id );
				if ( $post && in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
					// It was a draft and is now being published, so set the publication time
					$update_data['post_date']     = current_time( 'mysql' );
					$update_data['post_date_gmt'] = current_time( 'mysql', 1 );
				}
			}
		}

		// Set a flag to tell the wp_insert_post_data hook not to process images again,
		// because the images have already been processed on the frontend.
		$_POST['w2p_smart_aui_processed'] = true;

		// Use wp_update_post() to trigger all related hooks (including auto_set_featured_image),
		// which is more compliant with WordPress conventions than calling $wpdb->update() directly.
		$result = wp_update_post( $update_data, true );

		// Clear the flag
		unset( $_POST['w2p_smart_aui_processed'] );

		// Check for errors
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'post_id' => $post_id,
				)
			);
		}

		// Verify the status was actually updated
		$updated_post = get_post( $post_id );

		// Try to set the featured image
		$this->processor->auto_set_featured_image( $post_id );

		wp_send_json_success(
			array(
				'success' => true,
				'message' => 'Post content saved successfully',
			)
		);
	}
	/**
	 * AJAX Get Failed Logs
	 */
	public function ajax_get_failed_logs() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$container = \SmartAutoUploadImages\get_container();
		$manager   = $container->get( 'failed_images_manager' );

		if ( ! $manager ) {
			wp_send_json_success( array() );
		}

		$logs = $manager->get_failed_urls();

		// Format for display
		$formatted_logs = array();
		if ( ! empty( $logs ) && is_array( $logs ) ) {
			foreach ( $logs as $url => $timestamp ) {
				$formatted_logs[] = array(
					'url'  => $url,
					'time' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ),
				);
			}
			// Reverse to show newest first
			$formatted_logs = array_reverse( $formatted_logs );
		}

		wp_send_json_success( $formatted_logs );
	}
	/**
	 * AJAX Clear Failed Logs
	 */
	public function ajax_clear_failed_logs() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$manager = \SmartAutoUploadImages\get_container()->get( 'failed_images_manager' );
		$manager->clear_logs();

		wp_send_json_success( array( 'message' => 'Logs cleared' ) );
	}
	/**
	 * AJAX Download Video
	 *
	 * Downloads remote video and adds it to media library.
	 */
	public function ajax_download_video() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$video_url = isset( $_POST['video_url'] ) ? esc_url_raw( wp_unslash( $_POST['video_url'] ) ) : '';

		if ( ! $post_id || empty( $video_url ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request' ) );
		}

		// Check if video capture is enabled
		$settings = \SmartAutoUploadImages\Plugin::get_settings();
		if ( empty( $settings['capture_videos'] ) ) {
			wp_send_json_success(
				array(
					'source_url'     => $video_url,
					'downloaded_url' => $video_url,
					'skipped'        => true,
					'message'        => 'Video capture is disabled',
				)
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'Post not found' ) );
		}

		$post_data = array(
			'ID'           => $post->ID,
			'post_content' => $post->post_content,
			'post_title'   => $post->post_title,
			'post_date'    => $post->post_date,
		);

		// Use VideoDownloader to download video
		$downloader = new W2P_Video_Downloader();
		$result     = $downloader->download_video( $video_url, $post_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_success(
				array(
					'source_url'     => $video_url,
					'downloaded_url' => $video_url,
					'failed'         => true,
					'message'        => $result->get_error_message(),
				)
			);
		}

		// Get base URL for mapping
		$base_url = ! empty( $settings['base_url'] ) ? trim( $settings['base_url'], '/' ) : site_url();
		$new_url  = $result['url'];

		// Map to base URL if configured
		if ( ! empty( $new_url ) && ! empty( $base_url ) ) {
			$new_url_parts = wp_parse_url( $new_url );
			if ( ! empty( $new_url_parts['path'] ) ) {
				$new_url = $base_url . $new_url_parts['path'];
			}
		}

		wp_send_json_success(
			array(
				'source_url'     => $video_url,
				'downloaded_url' => $new_url,
				'attachment_id'  => $result['attachment_id'],
				'mime_type'      => $result['mime_type'],
				'message'        => 'Video downloaded successfully',
			)
		);
	}
	public function ajax_get_attachment_id() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- nonce already verified by check_ajax_referer() above.
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( empty( $image_url ) ) {
			wp_send_json_error( array( 'message' => 'Invalid image URL' ) );
		}

		// Use existing method to get attachment ID
		$attachment_id = $this->processor->get_attachment_id_from_url( $image_url );

		if ( $attachment_id ) {
			// If the attachment is an orphan in the media library, set this post as its parent.
			$this->maybe_attach_orphan_image( $attachment_id, $post_id );

			wp_send_json_success(
				array(
					'attachment_id' => $attachment_id,
					'image_url'     => $image_url,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Attachment not found' ) );
		}
	}

	/**
	 * Attach an orphan attachment to a post.
	 *
	 * When the module resolves/assigns an ID to an image in post content, if that
	 * attachment is currently an orphan (not attached to any post or page) and the
	 * toggle is enabled, set this post as its parent.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $post_id        Post ID being edited.
	 * @return void
	 */
	private function maybe_attach_orphan_image( int $attachment_id, int $post_id ): void {
		if ( $attachment_id <= 0 || $post_id <= 0 ) {
			return;
		}

		// Respect the toggle (read from the synced legacy settings; default on).
		$settings      = \SmartAutoUploadImages\Plugin::get_settings();
		$attach_orphan = isset( $settings['attach_orphan_images'] ) ? (bool) $settings['attach_orphan_images'] : true;
		if ( ! $attach_orphan ) {
			return;
		}

		// Only attach if the attachment is currently an orphan (no parent).
		$parent_id = (int) get_post_field( 'post_parent', $attachment_id );
		if ( 0 !== $parent_id ) {
			return;
		}

		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_parent' => $post_id,
			)
		);
	}
}

