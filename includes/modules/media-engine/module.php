<?php
/**
 * Media Engine Module
 *
 * Unified media processing module combining Image Watermark, Media Turbo, and Clipboard Upload.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media Engine Module Class
 */
class W2P_MediaEngineModule extends W2P_Abstract_Module {

	/**
	 * Handler instances
	 */
	private $turbo_handler;
	private $clipboard_handler;

	/**
	 * Module ID
	 */
	public static function id() {
		return 'media-engine';
	}

	/**
	 * Module Name
	 */
	public static function name() {
		return __( 'Media Engine', 'wp-genius' );
	}

	/**
	 * Module Description
	 */
	public static function description() {
		return __( 'Unified media processing: watermarks, format conversion, and clipboard uploads', 'wp-genius' );
	}

	public static function icon() {
		return 'fa-solid fa-photo-film';
	}

	/**
	 * Initialize Module
	 */
	public function init() {
		// Perform one-time migration from old modules
		$this->migrate_from_old_modules();

		// Load dependencies
		$this->load_dependencies();

		// Register settings
		$this->register_settings();

		// Hook into upload process
		// Auto-conversion removed per user request
		// add_filter( 'wp_handle_upload', [ $this, 'process_uploaded_image' ] );

		// AJAX Handlers
		add_action( 'wp_ajax_w2p_scan_attachments', array( $this, 'ajax_scan_attachments' ) );
		add_action( 'wp_ajax_w2p_process_batch', array( $this, 'ajax_process_batch' ) );
		add_action( 'wp_ajax_w2p_media_engine_reset', array( $this, 'ajax_reset_processed' ) );
		add_action( 'wp_ajax_w2p_media_retry_failed', array( $this, 'ajax_retry_failed' ) );
		add_action( 'wp_ajax_w2p_get_conversion_log', array( $this, 'ajax_get_conversion_log' ) );
		add_action( 'wp_ajax_w2p_clear_conversion_log', array( $this, 'ajax_clear_conversion_log' ) );
		add_action( 'wp_ajax_w2p_media_audit_scan', array( $this, 'ajax_audit_scan' ) );
		add_action( 'wp_ajax_w2p_media_audit_clean', array( $this, 'ajax_audit_clean' ) );

		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Migrate settings and enabled status from old modules
	 */
	private function migrate_from_old_modules() {
		if ( get_option( 'w2p_media_engine_migrated' ) ) {
			return;
		}

		$enabled_modules = get_option( 'word2posts_modules', array() );
		$old_modules     = array( 'image-watermark', 'media-turbo', 'clipboard-image-upload' );
		$should_enable   = false;

		foreach ( $old_modules as $old_module_id ) {
			if ( ! empty( $enabled_modules[ $old_module_id ] ) ) {
				$should_enable                     = true;
				$enabled_modules[ $old_module_id ] = false;
			}
		}

		if ( $should_enable ) {
			$enabled_modules['media-engine'] = true;
			update_option( 'word2posts_modules', $enabled_modules );
		}

		update_option( 'w2p_media_engine_migrated', true );
	}

	/**
	 * Load dependencies
	 */
	private function load_dependencies() {
		// Load Media Engine Processor
		$processor_path = plugin_dir_path( __FILE__ ) . 'includes/class-media-engine-processor.php';
		if ( file_exists( $processor_path ) ) {
			require_once $processor_path;
		}

		// Load Clipboard Upload handler (keeping for now as separate feature)
		$clipboard_handler_path = plugin_dir_path( __FILE__ ) . 'includes/class-clipboard-handler.php';
		if ( file_exists( $clipboard_handler_path ) ) {
			require_once $clipboard_handler_path;
			$this->clipboard_handler = new W2P_Clipboard_Handler();
		}

		// Load Audit Service (residual media audit)
		$audit_service_path = plugin_dir_path( __FILE__ ) . 'includes/services/class-audit-service.php';
		if ( file_exists( $audit_service_path ) ) {
			require_once $audit_service_path;
		}
	}

	/**
	 * Register default settings
	 */
	public function register_settings() {
		register_setting(
			'w2p_media_turbo_settings',
			'w2p_media_turbo_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'keep_original' => true,
					'scan_limit'    => 100,
					'batch_size'    => 10,
				),
			)
		);
	}

	/**
	 * Sanitize settings
	 */
	public function sanitize_settings( $input ) {
		$output                  = array();
		$output['keep_original'] = ! empty( $input['keep_original'] );
		$output['scan_limit']    = isset( $input['scan_limit'] ) ? absint( $input['scan_limit'] ) : 100;
		$output['batch_size']    = isset( $input['batch_size'] ) ? absint( $input['batch_size'] ) : 10;
		return $output;
	}

	/**
	 * AJAX: Scan for pending attachments
	 */
	public function ajax_scan_attachments() {
		global $wpdb;
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$processor = new MediaEngineProcessor();
		$settings  = W2P_Settings::tab_with_legacy( 'media_engine_tabs', 'w2p_media_turbo_settings', array() );

		$limit = isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 100;

		$attachments = $processor->scan( $limit, 0 );
		$data        = array();

		foreach ( $attachments as $id ) {
			$path  = get_attached_file( $id );
			$thumb = wp_get_attachment_image_src( $id, 'thumbnail' );

			// Get file size
			$size = file_exists( $path ) ? size_format( filesize( $path ), 2 ) : '';

			// Get parent post info
			$parent_title = '';
			$parent_url   = '';
			$parent_id    = wp_get_post_parent_id( $id );
			if ( $parent_id ) {
				$parent_title = get_the_title( $parent_id );
				$parent_url   = get_edit_post_link( $parent_id );
			}

			$data[] = array(
				'id'          => $id,
				'file_name'   => basename( $path ),
				'thumb_url'   => $thumb ? $thumb[0] : '',
				'file_size'   => $size,
				'parent_post' => $parent_title,
				'parent_url'  => $parent_url,
			);
		}

		$failed_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_w2p_media_failed'" );

		wp_send_json_success(
			array(
				'attachments'  => $data,
				'count'        => count( $data ),
				'failed_count' => $failed_count,
			)
		);
	}

	/**
	 * AJAX: Process Batch
	 */
	public function ajax_process_batch() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$ids_raw = isset( $_POST['attachment_ids'] ) ? $_POST['attachment_ids'] : array();
		if ( is_string( $ids_raw ) ) {
			$decoded = json_decode( $ids_raw, true );
			$ids_raw = is_array( $decoded ) ? $decoded : array();
		}
		$ids = is_array( $ids_raw ) ? array_map( 'absint', $ids_raw ) : array();
		if ( empty( $ids ) ) {
			wp_send_json_error( 'No IDs' );
		}

		// Global batch lock: the frontend timeouts long before the backend finishes, so a
		// timed-out client would otherwise fire the next batch while the previous one is
		// still converting the same IDs — concurrent runs inflate batches and convert
		// attachments twice. Reject any overlapping request with a batch_in_progress code.
		// wp_cache_add() is atomic (SETNX semantics, only sets when absent) and cross-process
		// via the Redis-backed object cache, so two requests racing through this point can
		// never both acquire the lock. (add_transient() is unavailable in this core build.)
		$lock_key = 'w2p_media_batch_lock';
		$lock_group = 'w2p_media_batch';
		if ( ! wp_cache_add( $lock_key, time(), $lock_group, 1200 ) ) {
			wp_send_json_error(
				array(
					'code'    => 'batch_in_progress',
					'message' => __( 'Another batch is still running. Please wait.', 'wp-genius' ),
				)
			);
		}
		// TTL 20 minutes covers the longest observed batch (576s), with margin; Redis expires
		// the key automatically even if the process crashes.
		// NOTE: no set_time_limit() here — FPM applies it as a CPU-time budget, the heavy work
		// runs in exec() child processes, and a CPU-limit Fatal on the parent is not catchable.
		// The FPM max_execution_time still bounds batches.

		try {
			$processor = new MediaEngineProcessor();
			$result    = $processor->process_batch( $ids );
			wp_cache_delete( $lock_key, $lock_group );
			wp_send_json_success( $result );
		} catch ( \Throwable $e ) {
			// Release the lock on every failure path so a crashed batch never wedges the queue.
			wp_cache_delete( $lock_key, $lock_group );
			wp_send_json_error( 'Batch processing error: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX: Reset
	 */
	public function ajax_reset_processed() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		delete_option( 'w2p_media_turbo_processed_posts' );
		wp_send_json_success( 'Reset successful' );
	}

	/**
	 * AJAX: Retry failed attachments (clears the _w2p_media_failed marker)
	 */
	public function ajax_retry_failed() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		global $wpdb;
		$ids_raw = isset( $_POST['attachment_ids'] ) ? $_POST['attachment_ids'] : '';
		if ( is_string( $ids_raw ) ) {
			$decoded = json_decode( $ids_raw, true );
			$ids_raw = is_array( $decoded ) ? $decoded : array();
		}
		$ids = is_array( $ids_raw ) ? array_map( 'absint', $ids_raw ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			// No IDs given → clear all failed markers.
			$count = (int) $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_w2p_media_failed'" );
		} else {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- IN placeholders are composed of %d (generated by array_fill); parameters are bound via prepare.
			$query = $wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_w2p_media_failed' AND post_id IN ($placeholders)",
				$ids
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is the return value of the $wpdb->prepare() above.
			$count = (int) $wpdb->query( $query );
		}

		wp_send_json_success(
			array(
				'cleared' => $count,
			)
		);
	}

	/**
	 * AJAX: Get conversion log tail
	 */
	public function ajax_get_conversion_log() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$services_dir = plugin_dir_path( __FILE__ ) . 'includes/services/';
		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once $services_dir . 'class-logger-service.php';
		}

		$logger    = new MediaEngineConversionLogger();
		$tail      = $logger->get_log_tail( 65536 );
		$size_info = $logger->get_log_size();

		wp_send_json_success(
			array(
				'lines'        => $tail,
				'size_bytes'   => $size_info['size_bytes'],
				'size_display' => $size_info['size_formatted'],
				'max_bytes'    => MediaEngineConversionLogger::MAX_LOG_SIZE,
			)
		);
	}

	/**
	 * AJAX: Clear conversion log
	 */
	public function ajax_clear_conversion_log() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$services_dir = plugin_dir_path( __FILE__ ) . 'includes/services/';
		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once $services_dir . 'class-logger-service.php';
		}

		$logger = new MediaEngineConversionLogger();
		$logger->clear_log();
		$size_info = $logger->get_log_size();

		wp_send_json_success(
			array(
				'size_bytes'   => $size_info['size_bytes'],
				'size_display' => $size_info['size_formatted'],
			)
		);
	}

	/**
	 * AJAX: Scan the uploads directory for residual media (in batches)
	 */
	public function ajax_audit_scan() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$subdir = isset( $_POST['subdir'] ) ? sanitize_text_field( wp_unslash( $_POST['subdir'] ) ) : '';
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 50;
		$limit  = max( 1, min( 200, $limit ) );

		$audit  = new MediaEngineAuditService();
		$result = $audit->scan_batch( $subdir, $offset, $limit );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Clean up local files confirmed to be in the bucket (Type A)
	 */
	public function ajax_audit_clean() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$files   = isset( $_POST['files'] ) ? (array) json_decode( wp_unslash( $_POST['files'] ), true ) : array();
		$orphans = isset( $_POST['orphans'] ) ? (array) json_decode( wp_unslash( $_POST['orphans'] ), true ) : array();
		$files   = array_map( 'sanitize_text_field', $files );
		$orphans = array_map( 'sanitize_text_field', $orphans );
		if ( empty( $files ) && empty( $orphans ) ) {
			wp_send_json_error( 'No files' );
		}

		// Buffer and discard any stray output (e.g. PHP warnings from logging/FS calls)
		// so the JSON payload below is never corrupted.
		if ( ob_get_level() ) {
			ob_end_clean();
		}
		ob_start();

		$audit  = new MediaEngineAuditService();
		$result = $audit->clean_files( $files, false );   // Cleanables (bucket-confirmed).
		$orphan = $audit->clean_files( $orphans, true );  // Orphans (no DB record, force delete).

		ob_end_clean();

		$result['cleaned'] += $orphan['cleaned'];
		$result['skipped']  = array_merge( $result['skipped'], $orphan['skipped'] );

		wp_send_json_success( $result );
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on WP Genius settings page (check for various hook names)
		if ( strpos( $hook, 'wp-genius-settings' ) === false && strpos( $hook, 'word-to-posts' ) === false ) {
			return;
		}

		// Enqueue media engine script
		wp_enqueue_script(
			'w2p-media-engine',
			plugin_dir_url( __FILE__ ) . 'assets/js/media-engine.js',
			array( 'jquery', 'wp-i18n' ),
			W2P_VERSION,
			true
		);

		// Enqueue audit script (residual media audit)
		wp_enqueue_script(
			'w2p-media-audit',
			plugin_dir_url( __FILE__ ) . 'assets/js/media-audit.js',
			array( 'jquery', 'wp-i18n' ),
			W2P_VERSION,
			true
		);

		// Register sub-module assets for on-demand use
		wp_register_script( 'w2p-clipboard-upload', plugin_dir_url( WP_GENIUS_FILE ) . 'assets/js/modules/clipboard-upload.js', array( 'w2p-core-js' ), W2P_VERSION, true );

		// Localize script for AJAX
		wp_localize_script(
			'w2p-media-engine',
			'w2pMediaEngine',
			array(
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'nonce'                  => wp_create_nonce( 'w2p_media_engine_nonce' ),
				'max_no_progress_rounds' => 3, // Anti-infinite-loop threshold for fully automatic processing (stops after N consecutive rounds without progress)
				'i18n'                   => array(
					'log_empty'          => __( 'Log is empty', 'wp-genius' ),
					'log_cleared'        => __( 'Log cleared', 'wp-genius' ),
					'clear_confirm'      => __( 'Are you sure you want to clear all logs? This cannot be undone.', 'wp-genius' ),
					// Media Engine batch processing.
					'stopRequested'      => __( 'Stop requested; the current batch will finish before stopping…', 'wp-genius' ),
					'userStopped'        => __( 'User stopped', 'wp-genius' ),
					'stopBatchFirst'     => __( 'Stop the current batch first before starting full auto processing', 'wp-genius' ),
					'autoStarted'        => __( 'Full auto processing started', 'wp-genius' ),
					/* translators: %1$d: current scan round number. */
					'roundScanning'      => __( 'Round %1$d: scanning pending images…', 'wp-genius' ),
					'scanFailed'         => __( 'Scan request failed', 'wp-genius' ),
					/* translators: %1$d: retry attempt number (max 3). */
					'scanRetrying'       => __( 'Scan request failed - retrying (%1$d/3)...', 'wp-genius' ),
					/* translators: 1: round number. 2: pending image count. 3: batch count. */
					'roundFound'         => __( 'Round %1$d found %2$d pending images in %3$d batches', 'wp-genius' ),
					/* translators: 1: round number. 2: current batch index. 3: total batch count. 4: image count in the batch. */
					'roundBatch'         => __( 'Round %1$d · batch %2$d/%3$d (%4$d images)', 'wp-genius' ),
					/* translators: %1$d: number of consecutive rounds without progress. */
					'noProgress'         => __( 'No progress for %1$d consecutive rounds; please check the error log', 'wp-genius' ),
					'pauseRequested'     => __( 'Pause requested; will pause after the current batch completes…', 'wp-genius' ),
					/* translators: 1: completed count. 2: failed count. */
					'paused'             => __( 'Paused. Click [Resume] to continue. Completed %1$d | Failed %2$d', 'wp-genius' ),
					'continuing'         => __( 'Continuing…', 'wp-genius' ),
					/* translators: 1: completed count. 2: failed count. */
					'autoComplete'       => __( 'Auto processing complete! Completed %1$d | Failed %2$d', 'wp-genius' ),
					/* translators: %1$d: number of files that failed. */
					'filesFailed'        => __( '%1$d file(s) failed; please check the log', 'wp-genius' ),
					/* translators: 1: stop reason. 2: completed count. 3: failed count. */
					'autoStopped'        => __( 'Auto processing stopped: %1$s. Completed %2$d | Failed %3$d', 'wp-genius' ),
					/* translators: %1$s: stop reason. */
					'autoStoppedToast'   => __( 'Auto processing stopped: %1$s', 'wp-genius' ),
					'processingLabel'    => __( 'Processing…', 'wp-genius' ),
					'fullAutoLabel'      => __( 'Full Auto Processing', 'wp-genius' ),
					'resumeLabel'        => __( 'Resume', 'wp-genius' ),
					'pauseLabel'         => __( 'Pause', 'wp-genius' ),
					// Media audit.
					'enterScanDir'       => __( 'Please enter a scan directory', 'wp-genius' ),
					/* translators: %1$d: number of files scanned so far. */
					'scanning'           => __( 'Scanning… %1$d file(s) processed', 'wp-genius' ),
					'firstScanIndex'     => __( '(first scan builds an index, ~30 seconds)', 'wp-genius' ),
					'scanFailedMsg'      => __( 'Scan failed', 'wp-genius' ),
					'ajaxFailed'         => __( 'AJAX request failed', 'wp-genius' ),
					'scannedFiles'       => __( 'Scanned files', 'wp-genius' ),
					'cleanableA'         => __( 'Cleanable (A)', 'wp-genius' ),
					'notOffloadedB'      => __( 'Not offloaded (B)', 'wp-genius' ),
					'orphanC'            => __( 'Orphan (C)', 'wp-genius' ),
					'scanStopped'        => __( 'Scan stopped', 'wp-genius' ),
					/* translators: %1$d: total scanned file count. */
					'scanComplete'       => __( 'Scan complete: %1$d file(s)', 'wp-genius' ),
					'cleanableLabel'     => __( 'Cleanable', 'wp-genius' ),
					'notOffloadedLabel'  => __( 'Not offloaded', 'wp-genius' ),
					'orphanLabel'        => __( 'Orphan', 'wp-genius' ),
					'parentLabel'        => __( 'Parent: ', 'wp-genius' ),
					/* translators: 1: file count to delete. 2: example file path. */
					'deleteConfirm'      => __(
						'Delete %1$d local file(s)?

These residual files will be removed from your server and cannot be undone.

Example: %2$s',
						'wp-genius'
					),
					/* translators: %1$d: number of successfully cleaned files. */
					'cleanupComplete'    => __( 'Cleanup complete: %1$d succeeded', 'wp-genius' ),
					/* translators: %1$d: number of skipped files. */
					'skippedCount'       => __( '%1$d skipped', 'wp-genius' ),
					'cleanupFailed'      => __( 'Cleanup failed: ', 'wp-genius' ),
					'unknownError'       => __( 'Unknown error', 'wp-genius' ),
					/* translators: %1$d: number of attachments to add to the queue. */
					'enqueueConfirm'     => __(
						'Add %1$d attachment(s) to the batch processing queue?

These files will be re-converted/offloaded.',
						'wp-genius'
					),
					'addedToQueue'       => __( 'Added to the processing queue and started. Go to the Batch Processing tab to watch progress.', 'wp-genius' ),
					'processingFailed'   => __( 'Processing failed: ', 'wp-genius' ),
					// Failed-item retry.
					/* translators: %1$d: number of failed attachments being re-enabled. */
					'retryFailedConfirm' => __( 'Re-enable %1$d failed attachment(s)? They will be picked up by the next scan and conversion attempts.', 'wp-genius' ),
					/* translators: %1$d: number of failed attachments re-enabled. */
					'retryFailedDone'    => __( 'Re-enabled %1$d failed attachment(s)', 'wp-genius' ),
					'retryFailedNone'    => __( 'No failed attachments to re-enable', 'wp-genius' ),
					/* translators: %1$d: number of failed attachments paused by the scanner. */
					'failedSkippedInfo'  => __( '%1$d failed attachment(s) are paused and skipped by the scanner. Fix the cause, then use [Retry Failed Items].', 'wp-genius' ),
				),
			)
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings() {
		$this->render_view( 'settings' );
	}
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'MediaEngineModule', false ) ) {
	class_alias( 'W2P_MediaEngineModule', 'MediaEngineModule' );
}
