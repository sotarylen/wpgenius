<?php
/**
 * Watermark Module
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Watermark Module Class
 */
class WatermarkModule extends W2P_Abstract_Module {

	private $watermark_instance;

	/**
	 * Module ID
	 */
	public static function id() {
		return 'watermark';
	}

	/**
	 * Module Name
	 */
	public static function name() {
		return __( 'Image Watermark', 'wp-genius' );
	}

	/**
	 * Module Description
	 */
	public static function description() {
		return __( 'Secure and brand your images with automatic watermarks.', 'wp-genius' );
	}

	public static function icon() {
		return 'fa-solid fa-droplet';
	}

	/**
	 * Initialize module
	 */
	public function init() {
		require_once plugin_dir_path( __FILE__ ) . 'class-image-watermark.php';
		$this->watermark_instance = W2P_Image_Watermark::instance();
        
        // Asset loading
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts( $hook ) {
        // Only load on settings page or post/media pages
        $screen = get_current_screen();
        if ( ! $screen || ( strpos( $screen->id, 'wp-genius-settings' ) === false && ! in_array( $screen->base, [ 'post', 'edit', 'upload' ] ) ) ) {
            return;
        }

        $plugin_url = plugin_dir_url( WP_GENIUS_FILE );
        
        wp_register_script( 'w2p-image-watermark', $plugin_url . "assets/js/modules/image-watermark.js", array( 'w2p-core-js' ), '1.0.0', true );

        wp_enqueue_script( 'w2p-image-watermark' );
    }



	/**
	 * Get module settings
	 */
	public function get_settings() {
		return [];
	}

	/**
	 * Render settings for WP Genius Settings tab
	 */
	public function render_settings() {
		$this->render_legacy_settings();
	}

	public function render_legacy_settings() {
		// We will need to instantiate the settings class of the legacy code if it's not already doing so.
		// The legacy code adds a submenu page. We want to output that content here.
		// Actually, the legacy code uses add_options_page. We removed that.
		// So we need to manually call the render method of the settings class.
		
		// Allow the legacy settings class to render its form
		if ( class_exists( 'W2P_Image_Watermark_Settings' ) ) {
			$settings = new W2P_Image_Watermark_Settings( $this->watermark_instance );
			$settings->output(); 
		} else {
			echo '<div class="wrap"><h2>Image Watermark Settings</h2><p>Settings class not found.</p></div>';
		}
	}
}
