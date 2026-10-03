<?php
/**
 * Actor Scanner Module Main Controller
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_ActorScannerModule extends W2P_Abstract_Module {

	public static function id() {
		return 'actor-scanner';
	}

	public static function name() {
		return __( 'Actor Scanner', 'wp-genius' );
	}

	public static function icon() {
		return 'fa-solid fa-user-tag';
	}

	public static function description() {
		return __( 'Scan posts for actress mentions from the first line, auto-create and enrich Humans taxonomy entries with Gfriends data.', 'wp-genius' );
	}

	/**
	 * Check if module requirements are met.
	 *
	 * 1. ACF (Advanced Custom Fields) plugin must be active.
	 * 2. Custom taxonomy 'humans' must be registered.
	 *
	 * @return true|WP_Error
	 */
	public function check_requirements() {
		if ( ! class_exists( 'ACF' ) && ! function_exists( 'acf' ) ) {
			return new WP_Error(
				'missing_acf',
				__( 'Cannot enable Actor Scanner: Advanced Custom Fields (ACF) plugin is not active. Please install and activate ACF first.', 'wp-genius' )
			);
		}

		if ( ! taxonomy_exists( 'humans' ) ) {
			return new WP_Error(
				'missing_humans_taxonomy',
				__( 'Cannot enable Actor Scanner: Custom taxonomy "humans" is not registered. Please create the "humans" taxonomy in ACF first.', 'wp-genius' )
			);
		}

		return true;
	}

	public function init() {
		$req = $this->check_requirements();
		if ( is_wp_error( $req ) ) {
			return;
		}

		require_once __DIR__ . '/includes/class-gfriends-client.php';
		require_once __DIR__ . '/includes/class-actor-matcher.php';
		require_once __DIR__ . '/includes/class-actor-sync.php';
		require_once __DIR__ . '/includes/class-actor-scanner-cli.php'; // WP-CLI

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Whitelist Gfriends CDN / raw GitHub hosts in container environments.
		add_filter( 'http_request_host_is_external', array( $this, 'allow_gfriends_hosts' ), 10, 3 );

		// AJAX Endpoints: Gfriends index synchronization.
		add_action( 'wp_ajax_w2p_actor_prepare_index', array( $this, 'ajax_prepare_index' ) );

		// Manual actor detection on post editor and post list screens.
		$settings      = W2P_Settings::tab( 'actor_scanner_tabs' );
		$manual_detect = isset( $settings['actor_manual_detect'] ) ? (bool) $settings['actor_manual_detect'] : true;

		if ( $manual_detect ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_post_editor_scripts' ) );
			add_action( 'admin_footer', array( $this, 'render_bulk_detect_modal' ) );
			add_action( 'wp_ajax_w2p_actor_detect_post', array( $this, 'ajax_detect_post' ) );

			// Register bulk actions for post types supporting 'humans'.
			foreach ( array( 'post', 'novel', 'albums' ) as $pt ) {
				add_filter( "bulk_actions-edit-{$pt}", array( $this, 'register_bulk_actions' ) );
			}
		}
	}

	/**
	 * Whitelist external Gfriends hosts.
	 *
	 * @param bool   $external External flag.
	 * @param string $host     Host.
	 * @param string $url      URL.
	 * @return bool
	 */
	public function allow_gfriends_hosts( $external, $host, $url ) {
		$allowed = array( 'cdn.jsdelivr.net', 'raw.githubusercontent.com' );
		foreach ( $allowed as $h ) {
			if ( $h === strtolower( $host ) || false !== strpos( strtolower( $url ), '//' . $h . '/' ) ) {
				return true;
			}
		}
		return $external;
	}

	/**
	 * Common AJAX guard.
	 *
	 * @return void
	 */
	protected function ajax_guard() {
		check_ajax_referer( 'w2p_actor_scanner_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ), 403 );
		}
	}

	/**
	 * Enqueue admin scripts for settings page.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( false === strpos( $hook, 'wp-genius-settings' ) ) {
			return;
		}

		wp_enqueue_style( 'w2p-core-css' );

		wp_enqueue_script(
			'w2p-actor-scanner',
			plugin_dir_url( __FILE__ ) . 'assets/js/actor-scanner.js',
			array( 'jquery' ),
			W2P_VERSION,
			true
		);

		wp_localize_script(
			'w2p-actor-scanner',
			'w2pActorScanner',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'w2p_actor_scanner_nonce' ),
				'i18n'    => array(
					'preparingIndex' => __( 'Syncing Gfriends official index...', 'wp-genius' ),
					'indexReady'     => __( 'Gfriends index sync complete!', 'wp-genius' ),
				),
			)
		);
	}

	/**
	 * AJAX: Prepare / Refresh Gfriends Index.
	 *
	 * @return void
	 */
	public function ajax_prepare_index() {
		$this->ajax_guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce + capability are verified by $this->ajax_guard() above.
		$force   = isset( $_POST['force'] ) ? (bool) $_POST['force'] : false;
		$gf      = new W2P_Gfriends_Client();
		$actors  = $gf->get_actor_index( $force );
		$content = $gf->get_filetree( $force );

		wp_send_json_success(
			array(
				'actors'      => count( $actors ),
				'information' => isset( $content['Information'] ) ? $content['Information'] : array(),
			)
		);
	}

	/**
	 * Register "Identify Actor" in bulk actions dropdown.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['w2p_actor_detect'] = __( 'Identify Actor (Selected Only)', 'wp-genius' );
		return $bulk_actions;
	}

	/**
	 * Render bulk progress modal on edit.php.
	 *
	 * @return void
	 */
	public function render_bulk_detect_modal() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		$post_type = $screen->post_type;
		if ( empty( $post_type ) || ! is_object_in_taxonomy( $post_type, 'humans' ) ) {
			return;
		}

		$view_file = __DIR__ . '/views/modal-bulk-detect.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
	}

	/**
	 * Enqueue post editor and list assets.
	 *
	 * @param string $hook Page hook.
	 * @return void
	 */
	public function enqueue_post_editor_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}

		$screen    = get_current_screen();
		$post_type = $screen ? $screen->post_type : '';
		if ( empty( $post_type ) || ! is_object_in_taxonomy( $post_type, 'humans' ) ) {
			return;
		}

		$css_path = __DIR__ . '/assets/css/actor-editor.css';
		$js_path  = __DIR__ . '/assets/js/actor-editor.js';

		$css_ver = file_exists( $css_path ) ? filemtime( $css_path ) : W2P_VERSION;
		$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : W2P_VERSION;

		wp_enqueue_style(
			'w2p-actor-editor',
			plugin_dir_url( __FILE__ ) . 'assets/css/actor-editor.css',
			array( 'w2p-core-css' ),
			$css_ver
		);

		wp_enqueue_script(
			'w2p-actor-editor',
			plugin_dir_url( __FILE__ ) . 'assets/js/actor-editor.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_localize_script(
			'w2p-actor-editor',
			'w2pActorEditor',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'w2p_actor_editor_nonce' ),
				'pageType' => ( 'edit.php' === $hook ) ? 'list' : 'editor',
				'postType' => $post_type,
				'i18n'     => array(
					'buttonText'    => __( 'Identify Actor', 'wp-genius' ),
					'loading'       => __( 'Identifying...', 'wp-genius' ),
					'success'       => __( 'Identified:', 'wp-genius' ),
					'error'         => __( 'Failed to identify actor', 'wp-genius' ),
					'noSelection'   => __( 'Please select posts to identify actors!', 'wp-genius' ),
					'processing'    => __( 'Identifying actors...', 'wp-genius' ),
					'completed'     => __( 'Actor identification completed!', 'wp-genius' ),
					'confirmCancel' => __( 'Are you sure you want to stop? Processed posts will be kept.', 'wp-genius' ),
				),
			)
		);
	}

	/**
	 * AJAX: detect actor from post's first line and assign/enrich Humans taxonomy.
	 *
	 * @return void
	 */
	public function ajax_detect_post() {
		check_ajax_referer( 'w2p_actor_editor_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ), 403 );
		}

		$post_id          = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$content_override = isset( $_POST['content'] ) ? (string) wp_unslash( $_POST['content'] ) : '';

		$result = $this->detect_post( $post_id, $content_override );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Detect and assign actor from a post's first line into the Humans taxonomy.
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $content_override Optional HTML or text content override.
	 * @return array Standard result structure: [ 'success' => bool, 'message' => string, 'terms' => array ]
	 */
	protected function detect_post( $post_id, $content_override = '' ) {
		$gf      = new W2P_Gfriends_Client();
		$matcher = new W2P_Actor_Matcher( $gf );
		$sync    = new W2P_Actor_Sync( $gf, $matcher );

		return $sync->detect_and_assign_post( $post_id, $content_override );
	}
}
