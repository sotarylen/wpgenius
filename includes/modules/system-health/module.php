<?php
/**
 * System Health Module
 *
 * Provides tools for database cleanup and system optimization.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_SystemHealthModule extends W2P_Abstract_Module {

	public static function id() {
		return 'system-health';
	}

	public static function name() {
		return __( 'System Health', 'wp-genius' );
	}

	public static function description() {
		return __( 'Optimize WordPress by cleaning up the database and scanning for unused media.', 'wp-genius' );
	}

	public static function icon() {
		return 'fa-solid fa-heart-pulse';
	}

	public function init() {
		// Load cleanup service
		require_once __DIR__ . '/cleanup-service.php';

				// AJAX handlers
		add_action( 'wp_ajax_w2p_system_health_clean', array( $this, 'ajax_cleanup_handler' ) );
		add_action( 'wp_ajax_w2p_system_health_get_stats', array( $this, 'ajax_get_stats_handler' ) );
		add_action( 'wp_ajax_w2p_system_health_get_info', array( $this, 'ajax_get_info_handler' ) );
		add_action( 'wp_ajax_w2p_system_health_scan_links', array( $this, 'ajax_scan_links_handler' ) );
		add_action( 'wp_ajax_w2p_system_health_remove_links', array( $this, 'ajax_remove_links_handler' ) );
		add_action( 'wp_ajax_w2p_system_health_scan_duplicates', array( $this, 'ajax_scan_duplicates_handler' ) );
		add_action( 'wp_ajax_w2p_system_health_trash_duplicates', array( $this, 'ajax_trash_duplicates_handler' ) );
		add_action( 'wp_ajax_w2p_system_health_clean_custom_field', array( $this, 'ajax_clean_custom_field_handler' ) );

		// Enhanced duplicate handlers for improved performance
		add_action( 'wp_ajax_w2p_system_health_scan_duplicates_improved', array( $this, 'ajax_scan_duplicates_improved_handler' ) );
		add_action( 'wp_ajax_w2p_system_health_get_duplicate_stats', array( $this, 'ajax_get_duplicate_stats_handler' ) );

		// Settings page assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts( $hook ) {
		// Only load on plugin settings page
		if ( strpos( $hook, 'wp-genius' ) === false ) {
			return;
		}

		$plugin_url = plugin_dir_url( WP_GENIUS_FILE );

		$js_path = plugin_dir_path( __FILE__ ) . 'assets/js/system-health.js';
		$version = file_exists( $js_path ) ? filemtime( $js_path ) : W2P_VERSION;
		$js_url  = plugin_dir_url( __FILE__ ) . 'assets/js/system-health.js';

		wp_enqueue_script( 'w2p-system-health', $js_url, array( 'jquery', 'w2p-core-js' ), $version, true );

		$service       = new SystemHealthCleanupService();
		$sh_categories = $service->get_categories();

		wp_localize_script(
			'w2p-system-health',
			'w2pSystemHealth',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'w2p_system_health_nonce' ),
				'confirm'    => __( 'Are you sure you want to perform this cleanup?', 'wp-genius' ),
				'cleaning'   => __( 'Cleaning...', 'wp-genius' ),
				'categories' => $sh_categories,
				'scanning'   => __( 'Scanning...', 'wp-genius' ),
				'executing'  => __( 'Executing...', 'wp-genius' ),
			)
		);
	}

	/**
	 * 遗留渲染入口（已由 CSF tabbed 替代，保留仅为兼容旧调用）。
	 *
	 * @return void
	 */
	public function render_settings() {
		// 视图已拆分为 CSF tabbed 片段（views/tab-*.php），此方法不再渲染独立页面。
		$section = array(
			'title' => __( 'System Health', 'wp-genius' ),
			'body'  => __( 'Use the System Health tab inside WP Genius Settings.', 'wp-genius' ),
		);
		echo '<h2>' . esc_html( $section['title'] ) . '</h2><p>' . esc_html( $section['body'] ) . '</p>';
	}

	/**
	 * AJAX Handler for Cleanups
	 */
	public function ajax_cleanup_handler() {
		check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$type    = isset( $_POST['cleanup_type'] ) ? sanitize_text_field( $_POST['cleanup_type'] ) : '';
		$service = new SystemHealthCleanupService();
		$count   = 0;

		switch ( $type ) {
			case 'revisions':
				$count = $service->clean_revisions();
				break;
			case 'auto_drafts':
				$count = $service->clean_auto_drafts();
				break;
			case 'orphaned_meta':
				$count = $service->clean_orphaned_meta();
				break;
			case 'transients':
				$count = $service->clean_transients();
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Invalid cleanup type.', 'wp-genius' ) ) );
		}

		wp_send_json_success(
			array(
				// translators: %1: placeholder。
				'message' => sprintf( __( 'Cleaned up %d items.', 'wp-genius' ), $count ),
				'count'   => $count,
			)
		);
	}

	/**
	 * AJAX Handler for System Info
	 */
	public function ajax_get_info_handler() {
		check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$service = new SystemHealthCleanupService();
		$info    = $service->get_system_info();

		wp_send_json_success( $info );
	}

	/**
	 * AJAX Handler for Statistics
	 */
	public function ajax_get_stats_handler() {
		check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$service = new SystemHealthCleanupService();
		$stats   = $service->get_stats();

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX Handler for Scanning Image Links
	 */
	public function ajax_scan_links_handler() {
		check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
		$service     = new SystemHealthCleanupService();
		$results     = $service->scan_posts_with_linked_images( $category_id );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX Handler for Removing Image Links
	 */
	public function ajax_remove_links_handler() {
		check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : array();
		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post IDs.', 'wp-genius' ) ) );
		}

		$service = new SystemHealthCleanupService();
		$results = array();
		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = $service->remove_image_links_from_post( $post_id );
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX Handler for Scanning Duplicate Posts
	 */
	public function ajax_scan_duplicates_handler() {
		// Increase time limit for potentially long scans
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}
		check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;

		try {
			$service = new SystemHealthCleanupService();
			$results = $service->scan_duplicate_posts( $category_id );

			// Ensure we always return an array
			if ( ! is_array( $results ) ) {
				$results = array();
			}

			wp_send_json_success( $results );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX Handler for Trashing Duplicate Posts
	 */
	public function ajax_trash_duplicates_handler() {
		check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : array();
		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post IDs.', 'wp-genius' ) ) );
		}

		$service = new SystemHealthCleanupService();
		$count   = $service->trash_duplicate_posts( $post_ids );

		wp_send_json_success(
			array(
				// translators: %1: placeholder。
				'message' => sprintf( __( 'Moved %d posts to trash.', 'wp-genius' ), $count ),
				'count'   => $count,
			)
		);
	}

	/**
	 * AJAX Handler for Custom Field Cleanup
	 */
	public function ajax_clean_custom_field_handler() {
		check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$meta_key = isset( $_POST['meta_key'] ) ? sanitize_text_field( $_POST['meta_key'] ) : '';
		if ( empty( $meta_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Please specify a custom field name.', 'wp-genius' ) ) );
		}

		$service = new SystemHealthCleanupService();
		$count   = $service->clean_custom_field( $meta_key );

		wp_send_json_success(
			array(
				// translators: %1: placeholder, %2: placeholder。
				'message' => sprintf( __( 'Cleaned up %1$d entries for meta key "%2$s".', 'wp-genius' ), $count, $meta_key ),
				'count'   => $count,
			)
		);
	}

	public function activate() {
		// Optional initialization on activation
	}

	public function deactivate() {
		// Optional cleanup on deactivation
	}
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'SystemHealthModule', false ) ) {
	class_alias( 'W2P_SystemHealthModule', 'SystemHealthModule' );
}
