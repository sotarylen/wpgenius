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

		// Load Audit Service（残留媒体审计）
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

		wp_send_json_success(
			array(
				'attachments' => $data,
				'count'       => count( $data ),
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

		$ids = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', $_POST['attachment_ids'] ) : array();
		if ( empty( $ids ) ) {
			wp_send_json_error( 'No IDs' );
		}

		$processor = new MediaEngineProcessor();
		$result    = $processor->process_batch( $ids );
		wp_send_json_success( $result );
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
	 * AJAX: 扫描 uploads 目录残留媒体（分批）
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
	 * AJAX: 清理已确认在桶中的本地文件（A 类）
	 */
	public function ajax_audit_clean() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$files = isset( $_POST['files'] ) ? (array) json_decode( wp_unslash( $_POST['files'] ), true ) : array();
		$files = array_map( 'sanitize_text_field', $files );
		if ( empty( $files ) ) {
			wp_send_json_error( 'No files' );
		}

		$audit  = new MediaEngineAuditService();
		$result = $audit->clean_files( $files );

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
			array( 'jquery' ),
			'1.2.0',
			true
		);

		// Enqueue audit script (残留媒体审计)
		wp_enqueue_script(
			'w2p-media-audit',
			plugin_dir_url( __FILE__ ) . 'assets/js/media-audit.js',
			array( 'jquery' ),
			'1.2.0',
			true
		);

		// Register sub-module assets for on-demand use
		wp_register_script( 'w2p-clipboard-upload', plugin_dir_url( WP_GENIUS_FILE ) . 'assets/js/modules/clipboard-upload.js', array( 'w2p-core-js' ), '1.2.0', true );

		// Localize script for AJAX
		wp_localize_script(
			'w2p-media-engine',
			'w2pMediaEngine',
			array(
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'nonce'                  => wp_create_nonce( 'w2p_media_engine_nonce' ),
				'max_no_progress_rounds' => 3, // 全自动处理防死循环阈值（连续 N 轮无进展即停止）
				'i18n'                   => array(
					'log_empty'     => __( 'Log is empty', 'wp-genius' ),
					'log_cleared'   => __( 'Log cleared', 'wp-genius' ),
					'clear_confirm' => __( 'Are you sure you want to clear all logs? This cannot be undone.', 'wp-genius' ),
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
