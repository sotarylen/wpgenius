<?php
/**
 * Clean Up WordPress Module
 * 
 * Provides options to customize and clean up WordPress admin interface.
 * Removes unwanted menu items from admin bar and dashboard widgets.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean Up WordPress Module Class
 */
class CleanupWordpressModule extends W2P_Abstract_Module {

	/**
	 * Module ID
	 *
	 * @return string
	 */
	public static function id() {
		return 'cleanup-wordpress';
	}

	/**
	 * Module Name
	 *
	 * @return string
	 */
	public static function name() {
		return __( 'Clean Up WordPress', 'wp-genius' );
	}

	/**
	 * Module Description
	 *
	 * @return string
	 */
	public static function description() {
		return __( 'Customize WordPress admin interface by removing unwanted menu items and dashboard widgets.', 'wp-genius' );
	}

	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
		// Register settings
		$this->register_settings();

		// Hook into admin bar rendering
		add_action( 'wp_before_admin_bar_render', [ $this, 'clean_admin_bar' ] );

		// Hook into dashboard setup
		add_action( 'wp_dashboard_setup', [ $this, 'clean_dashboard_widgets' ], 999 );
	}

	/**
	 * Register Module Settings
	 *
	 * @return void
	 */
	public function register_settings() {
		$defaults = [
			'remove_admin_bar_wp_logo'         => true,
			'remove_admin_bar_about'           => true,
			'remove_admin_bar_comments'        => true,
			'remove_admin_bar_new_content'     => true,
			'remove_admin_bar_search'          => true,
			'remove_admin_bar_updates'         => true,
			'remove_admin_bar_appearance'      => true,
			'remove_admin_bar_wporg'           => true,
			'remove_admin_bar_documentation'   => true,
			'remove_admin_bar_support_forums'  => true,
			'remove_admin_bar_feedback'        => true,
			'remove_admin_bar_view_site'       => true,
			'remove_dashboard_activity'        => true,
			'remove_dashboard_primary'         => false,
			'remove_dashboard_secondary'       => false,
			'remove_dashboard_site_health'     => false,
			'remove_dashboard_right_now'       => false,
		];

		$settings = get_option( 'w2p_cleanup_settings', [] );
		$settings = wp_parse_args( $settings, $defaults );
		update_option( 'w2p_cleanup_settings', $settings );
	}

	/**
	 * Clean Admin Bar
	 *
	 * @return void
	 */
	public function clean_admin_bar() {
		if ( ! $this->is_module_enabled() ) {
			return;
		}

		global $wp_admin_bar;
		$settings = get_option( 'w2p_cleanup_settings', [] );

		// Map of settings to admin bar menu items
		$items_to_remove = [
			'remove_admin_bar_wp_logo'       => 'wp-logo',
			'remove_admin_bar_about'         => 'about',
			'remove_admin_bar_comments'      => 'comments',
			'remove_admin_bar_new_content'   => 'new-content',
			'remove_admin_bar_search'        => 'search',
			'remove_admin_bar_updates'       => 'updates',
			'remove_admin_bar_appearance'    => 'appearance',
			'remove_admin_bar_wporg'         => 'wporg',
			'remove_admin_bar_documentation' => 'documentation',
			'remove_admin_bar_support_forums' => 'support-forums',
			'remove_admin_bar_feedback'      => 'feedback',
			'remove_admin_bar_view_site'     => 'view-site',
		];

		foreach ( $items_to_remove as $setting => $menu_item ) {
			if ( ! empty( $settings[ $setting ] ) ) {
				$wp_admin_bar->remove_menu( $menu_item );
			}
		}
	}

	/**
	 * Clean Dashboard Widgets
	 *
	 * @return void
	 */
	public function clean_dashboard_widgets() {
		if ( ! $this->is_module_enabled() ) {
			return;
		}

		global $wp_meta_boxes;
		$settings = get_option( 'w2p_cleanup_settings', [] );

		// Map of settings to dashboard widgets
		$widgets_to_remove = [
			'remove_dashboard_primary'      => [ 'dashboard', 'side', 'core', 'dashboard_primary' ],
			'remove_dashboard_secondary'    => [ 'dashboard', 'side', 'core', 'dashboard_secondary' ],
			'remove_dashboard_site_health'  => [ 'dashboard', 'normal', 'core', 'dashboard_site_health' ],
			'remove_dashboard_right_now'    => [ 'dashboard', 'normal', 'core', 'dashboard_right_now' ],
			'remove_dashboard_activity'     => [ 'dashboard', 'normal', 'core', 'dashboard_activity' ],
		];

		foreach ( $widgets_to_remove as $setting => $path ) {
			if ( ! empty( $settings[ $setting ] ) ) {
				if ( isset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] ) ) {
					unset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] );
				}
			}
		}
	}

	/**
	 * Check if module is enabled
	 *
	 * @return bool
	 */
	private function is_module_enabled() {
		$modules = get_option( 'word2posts_modules', [] );
		return isset( $modules[ $this::id() ] ) && $modules[ $this::id() ];
	}

	/**
	 * Module Activation Hook
	 *
	 * @return void
	 */
	public function activate() {
		do_action( 'w2p_cleanup_activated' );
	}

	/**
	 * Module Deactivation Hook
	 *
	 * @return void
	 */
	public function deactivate() {
		do_action( 'w2p_cleanup_deactivated' );
	}
}
