<?php
/**
 * WP Genius Accelerate — Admin Cleanup
 *
 * Split from module.php (refactored from the God class).
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
	 *
	 * Only returns true when the current post type is within the disabled scope (applies per post type).
	 *
	 * @param bool   $disable   Current value.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function should_disable_months_dropdown( $disable, $post_type ) {
		if ( in_array( $post_type, $this->get_disabled_post_types(), true ) ) {
			return true;
		}
		return $disable;
	}

	/**
	 * Disable Media Months UI
	 *
	 * Only returns an empty array when "attachment" (media library) is within the disabled scope;
	 * otherwise it is left as-is and the media library's months filter works normally.
	 *
	 * @param array $months Months.
	 * @return array
	 */
	public function disable_media_months( $months ) {
		if ( in_array( 'attachment', $this->get_disabled_post_types(), true ) ) {
			return array();
		}
		return $months;
	}

	/**
	 * Intercept and block date-based SELECT DISTINCT queries
	 *
	 * Parses the target post type from the SQL and only short-circuits queries that hit the disabled scope;
	 * no longer affects the same months queries for other post types (e.g. the media library attachment).
	 *
	 * @param string $query Query.
	 * @return string
	 */
	public function intercept_date_query( $query ) {
		if ( ! is_admin() ) {
			return $query;
		}

		// Target the specific slow queries for years/months
		if ( strpos( $query, 'SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month' ) === false ) {
			return $query;
		}

		// Parse the query's target post type; only short-circuit if it is within the selected scope.
		if ( preg_match( "/post_type\s*=\s*'([^']+)'/", $query, $matches ) ) {
			if ( in_array( $matches[1], $this->get_disabled_post_types(), true ) ) {
				global $wpdb;
				return "SELECT 1 FROM {$wpdb->posts} WHERE 1=0";
			}
		}

		return $query;
	}

	/**
	 * Normalizes the "Disable Months Dropdown" setting value into an array of post type slugs.
	 *
	 * Compatible with both storage formats:
	 * - New format: an array of post type slugs (checkbox multi-select);
	 * - Old format: switcher true/'1' (applied globally) — migrated to "all selectable post
	 *   types except the media library", consistent with the result of migrate_months_dropdown_setting().
	 *
	 * @return array
	 */
	private function get_disabled_post_types() {
		$settings = $this->module->get_settings();
		$raw      = isset( $settings['accelerate_disable_months_dropdown'] ) ? $settings['accelerate_disable_months_dropdown'] : array();

		$valid = W2P_AccelerateModule::get_months_dropdown_post_type_slugs();

		if ( is_array( $raw ) ) {
			$selected = array();
			foreach ( $raw as $slug ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' !== $slug && in_array( $slug, $valid, true ) ) {
					$selected[ $slug ] = true;
				}
			}
			return array_keys( $selected );
		}

		// Legacy global switch: all post types except the media library.
		if ( ! empty( $raw ) ) {
			return array_values( array_diff( $valid, array( 'attachment' ) ) );
		}

		return array();
	}
}
