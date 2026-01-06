<?php
/**
 * Post Duplicator Module
 *
 * @package WP_Genius
 * @subpackage Modules/PostDuplicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants used by legacy code if we haven't refactored them all yet
// But ideally we should refactor them. For now, let's define them to minimize breakage.
if ( ! defined( 'MTPHR_POST_DUPLICATOR_DIR' ) ) {
	define( 'MTPHR_POST_DUPLICATOR_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'MTPHR_POST_DUPLICATOR_URL' ) ) {
	define( 'MTPHR_POST_DUPLICATOR_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MTPHR_POST_DUPLICATOR_BASENAME' ) ) {
	define( 'MTPHR_POST_DUPLICATOR_BASENAME', plugin_basename( __FILE__ ) );
}

class PostDuplicatorModule extends W2P_Abstract_Module {

	/**
	 * Get Module ID
	 */
	public static function id() {
		return 'post-duplicator';
	}

	/**
	 * Get Module Name
	 */
	public static function name() {
		return __( 'Post Duplicator', 'wp-genius' );
	}

	/**
	 * Get Module Description
	 */
	public static function description() {
		return __( 'Duplicate any post type including custom fields and taxonomies.', 'wp-genius' );
	}

	/**
	 * Initialize Module
	 */
	public function init() {
		// Include legacy core files
		// We skipping settings.php and mtphr-settings/index.php as we are replacing them
		require_once MTPHR_POST_DUPLICATOR_DIR . 'includes/helpers.php';
		require_once MTPHR_POST_DUPLICATOR_DIR . 'includes/api.php';
		require_once MTPHR_POST_DUPLICATOR_DIR . 'includes/hooks.php';
		require_once MTPHR_POST_DUPLICATOR_DIR . 'includes/scripts.php';
		require_once MTPHR_POST_DUPLICATOR_DIR . 'includes/edit.php';
		// require_once MTPHR_POST_DUPLICATOR_DIR . 'includes/notices.php'; // Probably not needed if we remove the settings framework notices

        // Register settings (handled by framework via Settings UI, but we need to ensure defaults are available if not set)
	}

    /**
     * Enqueue Assets
     */
    public function enqueue_assets( $hook ) {
        // This module's assets are largely handled by includes/scripts.php
        // We can hook into admin_enqueue_scripts here if we moved logic from scripts.php to here
        // But for now, letting scripts.php handle it via its own hook in init() is fine
    }

    public function render_settings() {
        $this->render_view( 'settings' );
    }

    public function settings_key() {
        return 'w2p_post_duplicator_settings';
    }
}
