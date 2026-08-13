<?php
/**
 * WP Genius Accelerate — 更新控制
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
 * Class W2P_Accelerate_UpdateControl
 */
class W2P_Accelerate_UpdateControl {

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
	 * Apply Update Behaviors
	 */
	public function apply_update_behavior() {
		$s = $this->module->get_settings();

		// 1. Auto-Updates (Unified)
		if ( ! empty( $s['accelerate_disable_auto_updates'] ) ) {
			add_filter( 'auto_update_plugin', '__return_false' );
			add_filter( 'auto_update_theme', '__return_false' );
		}

		// 2. Plugin Update Checks (Unified Cron + Init)
		if ( ! empty( $s['accelerate_disable_plugin_updates'] ) ) {
			// Remove Cron Hooks
			remove_action( 'load-update-core.php', 'wp_update_plugins' );
			remove_action( 'load-plugins.php', 'wp_update_plugins' );
			remove_action( 'load-update.php', 'wp_update_plugins' );
			remove_action( 'wp_update_plugins', 'wp_update_plugins' );
			// Remove Init Hooks
			remove_action( 'admin_init', '_maybe_update_plugins' );
			remove_action( 'admin_init', 'wp_plugin_update_rows' );

			// Force hide updates by filtering transient
			add_filter( 'pre_site_transient_update_plugins', array( $this, 'force_no_plugin_updates' ) );
		}

		// 3. Theme Update Checks (Unified Cron + Init)
		if ( ! empty( $s['accelerate_disable_theme_updates'] ) ) {
			// Remove Cron Hooks
			remove_action( 'load-themes.php', 'wp_update_themes' );
			remove_action( 'load-update.php', 'wp_update_themes' );
			remove_action( 'load-update-core.php', 'wp_update_themes' );
			remove_action( 'wp_update_themes', 'wp_update_themes' );
			// Remove Init Hooks
			remove_action( 'admin_init', '_maybe_update_themes' );
			remove_action( 'admin_init', 'wp_theme_update_rows' );

			// Force hide updates by filtering transient
			add_filter( 'pre_site_transient_update_themes', array( $this, 'force_no_theme_updates' ) );
		}

		// 4. Core Update Checks
		if ( ! empty( $s['accelerate_disable_core_updates'] ) ) {
			remove_action( 'admin_init', '_maybe_update_core' );
			remove_action( 'wp_version_check', 'wp_version_check' );
			add_filter( 'pre_site_transient_update_core', array( $this, 'force_no_core_updates' ) );
		}

		// 5. Global HTTP Block
		if ( ! empty( $s['accelerate_block_external_http'] ) ) {
			if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
				define( 'WP_HTTP_BLOCK_EXTERNAL', true );
			}
		}

		// 6. Custom HTTP Blocker
		if ( ! empty( $s['accelerate_blind_http_requests'] ) ) {
			add_filter( 'http_request_args', array( $this, 'block_custom_http_requests' ), 10, 2 );
		}
	}
	/**
	 * Force No Plugin Updates
	 */
	public function force_no_plugin_updates() {
		$current               = new stdClass();
		$current->last_checked = time();
		$current->response     = array();
		$current->translations = array();
		$current->no_update    = array();
		return $current;
	}
	/**
	 * Force No Theme Updates
	 */
	public function force_no_theme_updates() {
		$current               = new stdClass();
		$current->last_checked = time();
		$current->response     = array();
		$current->translations = array();
		$current->checked      = array();
		return $current;
	}
	/**
	 * Force No Core Updates
	 */
	public function force_no_core_updates() {
		$current                  = new stdClass();
		$current->last_checked    = time();
		$current->updates         = array();
		$current->version_checked = get_bloginfo( 'version' );
		return $current;
	}
	/**
	 * Block Custom HTTP Requests
	 */
	public function block_custom_http_requests( $r, $url ) {
		$settings = $this->module->get_settings();
		$patterns = ! empty( $settings['accelerate_blind_http_requests'] ) ? $settings['accelerate_blind_http_requests'] : array();

		$url_string = is_array( $url ) ? ( isset( $url['url'] ) ? $url['url'] : '' ) : $url;

		foreach ( $patterns as $item ) {
			if ( empty( $item['url_pattern'] ) ) {
				continue;
			}

			// Case-insensitive sub-string match
			if ( stripos( $url_string, $item['url_pattern'] ) !== false ) {
				$r['blocked'] = true;
				break;
			}
		}

		return $r;
	}
}
