<?php
/**
 * WP Genius Accelerate — 后台清理
 *
 * 从 module.php 拆分（God class 重构）。
 *
 * @package WP_Genius
 * @subpackage Modules/Accelerate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_Accelerate_AdminCleanup
 */
class W2P_Accelerate_AdminCleanup {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_AccelerateModule
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param W2P_AccelerateModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * Add admin body classes for styling hooks
	 */
	public function add_body_classes( $classes ) {
		$settings = $this->module->get_settings();

		if ( ! empty( $settings['accelerate_hide_plugin_notices'] ) ) {
			$classes .= ' w2p-acc-hide-notices';
		}

		if ( ! empty( $settings['accelerate_enable_local_avatar'] ) ) {
			$classes .= ' w2p-acc-local-avatar';
		}

		return $classes;
	}
	/**
	 * Clean Admin Bar
	 */
	public function clean_admin_bar() {
		global $wp_admin_bar;
		$settings = $this->module->get_settings();

		$items_to_remove = array(
			'accelerate_remove_admin_bar_wp_logo'        => 'wp-logo',
			'accelerate_remove_admin_bar_about'          => 'about',
			'accelerate_remove_admin_bar_comments'       => 'comments',
			'accelerate_remove_admin_bar_new_content'    => 'new-content',
			'accelerate_remove_admin_bar_search'         => 'search',
			'accelerate_remove_admin_bar_updates'        => 'updates',
			'accelerate_remove_admin_bar_appearance'     => 'appearance',
			'accelerate_remove_admin_bar_customize'      => 'customize',
			'accelerate_remove_admin_bar_wporg'          => 'wporg',
			'accelerate_remove_admin_bar_documentation'  => 'documentation',
			'accelerate_remove_admin_bar_support_forums' => 'support-forums',
			'accelerate_remove_admin_bar_feedback'       => 'feedback',
			'accelerate_remove_admin_bar_view_site'      => 'view-site',
		);

		foreach ( $items_to_remove as $setting => $menu_item ) {
			if ( ! empty( $settings[ $setting ] ) ) {
				$wp_admin_bar->remove_menu( $menu_item );
			}
		}
	}
	/**
	 * Clean Dashboard Widgets
	 */
	public function clean_dashboard_widgets() {
		global $wp_meta_boxes;
		$settings = $this->module->get_settings();

		$widgets_to_remove = array(
			'accelerate_remove_dashboard_primary'     => array( 'dashboard', 'side', 'core', 'dashboard_primary' ),
			'accelerate_remove_dashboard_secondary'   => array( 'dashboard', 'side', 'core', 'dashboard_secondary' ),
			'accelerate_remove_dashboard_site_health' => array( 'dashboard', 'normal', 'core', 'dashboard_site_health' ),
			'accelerate_remove_dashboard_right_now'   => array( 'dashboard', 'normal', 'core', 'dashboard_right_now' ),
			'accelerate_remove_dashboard_quick_draft' => array( 'dashboard', 'side', 'core', 'dashboard_quick_press' ),
			'accelerate_remove_dashboard_activity'    => array( 'dashboard', 'normal', 'core', 'dashboard_activity' ),
		);

		foreach ( $widgets_to_remove as $setting => $path ) {
			if ( ! empty( $settings[ $setting ] ) ) {
				if ( isset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] ) ) {
					unset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] );
				}
			}
		}
	}
	/**
	 * Should Disable Months Dropdown (Post List)
	 */
	public function should_disable_months_dropdown( $disable, $post_type ) {
		$settings = $this->module->get_settings();
		if ( ! empty( $settings['accelerate_disable_months_dropdown'] ) ) {
			return true;
		}
		return $disable;
	}
	/**
	 * Disable Media Months UI
	 */
	public function disable_media_months( $months ) {
		$settings = $this->module->get_settings();
		if ( ! empty( $settings['accelerate_disable_months_dropdown'] ) ) {
			return array();
		}
		return $months;
	}
	/**
	 * Intercept and block date-based SELECT DISTINCT queries
	 */
	public function intercept_date_query( $query ) {
		if ( ! is_admin() ) {
			return $query;
		}

		// Target the specific slow queries for years/months
		if ( strpos( $query, 'SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month' ) !== false ) {
			$settings = $this->module->get_settings();
			if ( ! empty( $settings['accelerate_disable_months_dropdown'] ) ) {
				return 'SELECT 1 FROM wp_posts WHERE 1=0';
			}
		}

		return $query;
	}
}
