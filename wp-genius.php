<?php
/**
 * Plugin Name: WP Genius
 * Description: A comprehensive toolkit for WordPress content management, optimization, and automation (Auto-Publish, Media Engine, System Health, and more).
 * Version: 1.1.0
 * Author: Sotary
 * Text Domain: wp-genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin file constant
define( 'WP_GENIUS_FILE', __FILE__ );

// Include module framework (abstracts, loader, admin settings)
require_once plugin_dir_path( __FILE__ ) . 'includes/csf/codestar-framework.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-abstract-module.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-module-loader.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-task-queue.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-logger.php';

/**
 * Initialize the plugin (runs on init hook after translations are loaded)
 */
function w2p_core_init() {
	// Load plugin textdomain first
	load_plugin_textdomain( 'wp-genius', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	try {
		// Initialize module loader
		$module_loader = new W2P_Module_Loader( plugin_dir_path( __FILE__ ) . 'includes/modules/' );

		// Initialize admin settings manager
		$admin_settings = new W2P_Admin_Settings( $module_loader );

		// Initialize and load enabled modules
		$module_loader->init();
	} catch ( Exception $e ) {
		W2P_Logger::error( 'WP Genius Init Error: ' . $e->getMessage(), 'core' );
		if ( is_admin() ) {
			add_action(
				'admin_notices',
				function () use ( $e ) {
					// translators: %s: Error message.
					echo '<div class="error"><p>' . sprintf( esc_html__( 'WP Genius Error: %s', 'wp-genius' ), esc_html( $e->getMessage() ) ) . '</p></div>';
				}
			);
		}
	}
}
add_action( 'init', 'w2p_core_init', 5 ); // Priority 5 to run after core init but before most other plugins

/**
 * Register and enqueue core admin assets
 */
function w2p_core_enqueue_scripts() {
	try {
		$is_plugin_page = false;

		if ( is_admin() ) {
			$screen = get_current_screen();
			if ( $screen ) {
				$is_plugin_page = (
					strpos( $screen->id, 'wp-genius' ) !== false ||
					in_array( $screen->id, array( 'post', 'edit-post', 'tools', 'edit-page', 'page' ), true ) ||
					in_array( $screen->base, array( 'post', 'edit', 'upload' ), true )
				);
			}
		} else {
			// Frontend: Only enqueue if explicitly enabled via filter (default false to improve performance)
			$is_plugin_page = apply_filters( 'w2p_load_frontend_assets', false );
		}

			// Register Assets Globally
			wp_register_style( 'w2p-core-css', plugin_dir_url( __FILE__ ) . 'assets/css/core.css', array(), '1.1.0' );
			wp_register_script( 'w2p-admin-ui', plugin_dir_url( __FILE__ ) . 'assets/js/w2p-admin-ui.js', array( 'jquery' ), '1.0.0', true );
			wp_localize_script(
				'w2p-admin-ui',
				'w2p_ui_i18n',
				array(
					'confirm'        => __( 'Confirm', 'wp-genius' ),
					'cancel'         => __( 'Cancel', 'wp-genius' ),
					'confirm_title'  => __( 'Confirmation', 'wp-genius' ),
					'settings_saved' => __( 'Settings saved successfully!', 'wp-genius' ),
				)
			);
			wp_register_script( 'w2p-range-slider', plugin_dir_url( __FILE__ ) . 'assets/js/range-slider.js', array( 'jquery' ), '1.0.0', true );
			wp_register_script( 'w2p-core-js', plugin_dir_url( __FILE__ ) . 'assets/js/w2p-admin-core.js', array( 'jquery', 'w2p-admin-ui' ), '1.0.1', true );

		if ( $is_plugin_page ) {
			// External dependencies
			wp_enqueue_style( 'jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css', array(), '1.12.1' );
			wp_enqueue_script( 'jquery-ui-tabs' );
			wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0' );

			// Enqueue Core
			wp_enqueue_style( 'w2p-core-css' );
			wp_enqueue_script( 'w2p-admin-ui' );
			wp_enqueue_script( 'w2p-core-js' );
		}
	} catch ( Exception $e ) {
		W2P_Logger::error( 'WP Genius Enqueue Error: ' . $e->getMessage(), 'core' );
	}
}
add_action( 'admin_enqueue_scripts', 'w2p_core_enqueue_scripts' );
add_action( 'wp_enqueue_scripts', 'w2p_core_enqueue_scripts' );
