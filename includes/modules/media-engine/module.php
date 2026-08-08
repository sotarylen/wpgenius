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
class MediaEngineModule extends W2P_Abstract_Module {

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
		add_action( 'wp_ajax_w2p_scan_attachments', [ $this, 'ajax_scan_attachments' ] );
		add_action( 'wp_ajax_w2p_process_batch', [ $this, 'ajax_process_batch' ] );
		add_action( 'wp_ajax_w2p_media_engine_reset', [ $this, 'ajax_reset_processed' ] );
		
		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Migrate settings and enabled status from old modules
	 */
	private function migrate_from_old_modules() {
		if ( get_option( 'w2p_media_engine_migrated' ) ) {
			return;
		}

		$enabled_modules = get_option( 'word2posts_modules', [] );
		$old_modules = [ 'image-watermark', 'media-turbo', 'clipboard-image-upload' ];
		$should_enable = false;
		
		foreach ( $old_modules as $old_module_id ) {
			if ( ! empty( $enabled_modules[ $old_module_id ] ) ) {
				$should_enable = true;
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
	}

	/**
	 * Register default settings
	 */
	public function register_settings() {
		register_setting( 'w2p_media_turbo_settings', 'w2p_media_turbo_settings', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
			'default'           => [
				'keep_original'         => true,
				'scan_limit'            => 100,
				'batch_size'            => 10,
			]
		] );
	}
	
	/**
	 * Sanitize settings
	 */
	public function sanitize_settings( $input ) {
		$output = [];
		$output['keep_original']         = ! empty( $input['keep_original'] );
		$output['scan_limit']            = isset( $input['scan_limit'] ) ? absint( $input['scan_limit'] ) : 100;
		$output['batch_size']            = isset( $input['batch_size'] ) ? absint( $input['batch_size'] ) : 10;
		return $output;
	}

	/**
	 * AJAX: Scan for pending attachments
	 */
	public function ajax_scan_attachments() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

		$processor = new MediaEngineProcessor();
		$all_settings = get_option( 'w2p_settings', [] );
		$settings = isset( $all_settings['media_engine_tabs'] ) ? $all_settings['media_engine_tabs'] : [];
		
		$limit = isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 100;
		
		$attachments = $processor->scan( $limit, 0 );
		$data = [];

		foreach ( $attachments as $id ) {
			$path = get_attached_file( $id );
			$thumb = wp_get_attachment_image_src( $id, 'thumbnail' );
			
			// Get file size
			$size = file_exists( $path ) ? size_format( filesize( $path ), 2 ) : '';
			
			// Get parent post info
			$parent_title = '';
			$parent_url = '';
			$parent_id = wp_get_post_parent_id( $id );
			if ( $parent_id ) {
				$parent_title = get_the_title( $parent_id );
				$parent_url = get_edit_post_link( $parent_id );
			}

			$data[] = [
				'id' => $id,
				'file_name' => basename( $path ),
				'thumb_url' => $thumb ? $thumb[0] : '',
				'file_size' => $size,
				'parent_post' => $parent_title,
				'parent_url' => $parent_url,
			];
		}

		wp_send_json_success( [ 'attachments' => $data, 'count' => count( $data ) ] );
	}

	/**
	 * AJAX: Process Batch
	 */
	public function ajax_process_batch() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

		$ids = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', $_POST['attachment_ids'] ) : [];
		if ( empty( $ids ) ) wp_send_json_error( 'No IDs' );

		$processor = new MediaEngineProcessor();
		$result = $processor->process_batch( $ids );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Reset
	 */
	public function ajax_reset_processed() {
		check_ajax_referer( 'w2p_media_engine_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );
		
		delete_option( 'w2p_media_turbo_processed_posts' );
		wp_send_json_success( 'Reset successful' );
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
			[ 'jquery' ],
			'1.0.0',
			true
		);

        // Register sub-module assets for on-demand use
        wp_register_script( 'w2p-clipboard-upload', plugin_dir_url( WP_GENIUS_FILE ) . "assets/js/modules/clipboard-upload.js", array( 'w2p-core-js' ), '1.0.0', true );

		// Localize script for AJAX
		wp_localize_script( 'w2p-media-engine', 'w2pMediaEngine', [
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'nonce'                 => wp_create_nonce( 'w2p_media_engine_nonce' ),
			'max_no_progress_rounds'=> 3, // 全自动处理防死循环阈值（连续 N 轮无进展即停止）
		] );
	}

	/**
	 * Render settings page
	 */
	public function render_settings() {
		$this->render_view( 'settings' );
	}

}
