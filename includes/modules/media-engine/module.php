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
		
		// Load sub-module handlers
		$this->load_handlers();
		
		// Register settings
		$this->register_settings();
		
		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Migrate settings and enabled status from old modules
	 */
	private function migrate_from_old_modules() {
		// Check if migration has already been done
		if ( get_option( 'w2p_media_engine_migrated' ) ) {
			return;
		}

		// Get enabled modules
		$enabled_modules = get_option( 'word2posts_modules', [] );
		
		// Check if any of the old modules were enabled
		$old_modules = [ 'image-watermark', 'media-turbo', 'clipboard-image-upload' ];
		$should_enable = false;
		
		foreach ( $old_modules as $old_module_id ) {
			if ( ! empty( $enabled_modules[ $old_module_id ] ) ) {
				$should_enable = true;
				// Disable the old module
				$enabled_modules[ $old_module_id ] = false;
			}
		}
		
		// Enable media-engine if any old module was enabled
		if ( $should_enable ) {
			$enabled_modules['media-engine'] = true;
			update_option( 'word2posts_modules', $enabled_modules );
		}
		
		// Mark migration as complete
		update_option( 'w2p_media_engine_migrated', true );
	}

	/**
	 * Load sub-module handlers
	 */
	private function load_handlers() {
		// Load Media Turbo handler
		$turbo_handler_path = plugin_dir_path( __FILE__ ) . 'includes/class-media-turbo-handler.php';
		if ( file_exists( $turbo_handler_path ) ) {
			require_once $turbo_handler_path;
			$this->turbo_handler = new W2P_Media_Turbo_Handler();
		}

		// Load Clipboard Upload handler
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
		// Settings are managed by individual handlers
		// Each handler maintains its own settings option
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
			plugin_dir_url( WP_GENIUS_FILE ) . 'assets/js/modules/media-engine.js',
			[ 'jquery', 'w2p-admin-ui' ],
			'1.0.0',
			true
		);

        // Register sub-module assets for on-demand use
        wp_register_script( 'w2p-media-turbo', plugin_dir_url( WP_GENIUS_FILE ) . "assets/js/modules/media-turbo.js", array( 'w2p-core-js' ), '1.0.0', true );
        wp_register_script( 'w2p-clipboard-upload', plugin_dir_url( WP_GENIUS_FILE ) . "assets/js/modules/clipboard-upload.js", array( 'w2p-core-js' ), '1.0.0', true );

		// Localize script for AJAX
		wp_localize_script( 'w2p-media-engine', 'w2pMediaEngine', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'w2p_media_engine_nonce' ),
		] );
	}

	/**
	 * Render settings page
	 */
	public function render_settings() {
		$this->render_view( 'settings' );
	}

	/**
	 * Settings key (not used, as each sub-module has its own)
	 */
	public function settings_key() {
		return 'w2p_media_engine_settings';
	}
}
