<?php
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
		return __( 'Scan posts and albums for actress mentions, auto-create / enrich Humans taxonomy entries, and fetch avatars from the Gfriends repository.', 'wp-genius' );
	}

	public function init() {
		require_once __DIR__ . '/includes/class-gfriends-client.php';
		require_once __DIR__ . '/includes/class-actor-matcher.php';
		require_once __DIR__ . '/includes/class-actor-sync.php';
		require_once __DIR__ . '/includes/class-actor-scanner-cli.php'; // WP-CLI (no-op on web)

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Allow outbound requests to Gfriends CDN / raw GitHub even when the
		// container DNS resolves to a reserved (198.18.x.x) benchmark range that
		// wp_http_validate_url() would otherwise block.
		add_filter( 'http_request_host_is_external', array( $this, 'allow_gfriends_hosts' ), 10, 3 );

		// AJAX endpoints for the scan tool.
		add_action( 'wp_ajax_w2p_actor_prepare', array( $this, 'ajax_prepare' ) );
		add_action( 'wp_ajax_w2p_actor_get_total', array( $this, 'ajax_get_total' ) );
		add_action( 'wp_ajax_w2p_actor_scan_batch', array( $this, 'ajax_scan_batch' ) );
		add_action( 'wp_ajax_w2p_actor_reset', array( $this, 'ajax_reset' ) );
		add_action( 'wp_ajax_w2p_actor_stats', array( $this, 'ajax_stats' ) );
	}

	/**
	 * Allow outbound HTTP requests to Gfriends data sources.
	 *
	 * Gfriends Filetree / avatars are fetched from jsDelivr CDN and
	 * raw.githubusercontent.com. In containerized dev environments these hosts
	 * may resolve to the 198.18.0.0/15 benchmarking range, which WordPress
	 * treats as internal; whitelist them so download_url() works.
	 *
	 * @param bool   $external Whether the host is considered external.
	 * @param string $host     Host name.
	 * @param string $url      Request URL.
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
	 * Enqueue admin assets on the module settings page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		// CSF options page for this module lives under tools.php?page=wp-genius-settings.
		if ( false === strpos( $hook, 'wp-genius-settings' ) ) {
			return;
		}

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
			)
		);
	}

	/**
	 * Common AJAX guard: nonce + capability.
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
	 * AJAX: prepare the Gfriends index (refresh + cache) and report actor counts.
	 *
	 * @return void
	 */
	public function ajax_prepare() {
		$this->ajax_guard();

		$force = isset( $_POST['force'] ) ? (bool) $_POST['force'] : false;

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
	 * AJAX: count posts to scan.
	 *
	 * @return void
	 */
	public function ajax_get_total() {
		$this->ajax_guard();

		$post_types = $this->requested_post_types();
		$total      = $this->count_scan_targets( $post_types );

		wp_send_json_success(
			array(
				'total' => $total,
			)
		);
	}

	/**
	 * AJAX: scan one batch of posts, assign actor terms.
	 *
	 * @return void
	 */
	public function ajax_scan_batch() {
		$this->ajax_guard();

		$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? min( absint( $_POST['batch_size'] ), 200 ) : 20;
		$post_types = $this->requested_post_types();
		$dry_run    = isset( $_POST['dry_run'] ) ? (bool) $_POST['dry_run'] : false;
		$create_new = isset( $_POST['create_new'] ) ? (bool) $_POST['create_new'] : true;

		// Whether to assign only when the post has NO humans term yet, or always append.
		$only_unassigned = isset( $_POST['only_unassigned'] ) ? (bool) $_POST['only_unassigned'] : false;

		$gf      = new W2P_Gfriends_Client();
		$matcher = new W2P_Actor_Matcher( $gf );
		$sync    = new W2P_Actor_Sync( $gf, $matcher );

		$posts = $this->get_scan_batch( $post_types, $offset, $batch_size, $only_unassigned );

		$log = array();

		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;
			$text    = $post->post_title . "\n" . $post->post_content;

			$candidates = $matcher->extract_candidates( $text );
			if ( empty( $candidates ) ) {
				continue;
			}

			$resolved = $matcher->resolve_all( $candidates );
			if ( empty( $resolved ) ) {
				continue;
			}

			$term_ids    = array();
			$found_names = array();

			foreach ( $resolved as $actor ) {
				$term_id = 0;

				if ( ! empty( $actor['term_id'] ) ) {
					$term_id = (int) $actor['term_id'];
					// Enrich existing terms (role, nickname, avatar) unless dry run.
					if ( ! $dry_run ) {
						$sync->enrich_existing( $term_id, $actor );
					}
				} elseif ( $create_new && ! $dry_run ) {
					$term_id = $sync->ensure_term( $actor );
				}

				if ( $term_id > 0 ) {
					$term_ids[]    = $term_id;
					$found_names[] = $actor['name'];
				}
			}

			$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );

			if ( empty( $term_ids ) ) {
				continue;
			}

			if ( ! $dry_run ) {
				$sync->assign_to_post( $post_id, $term_ids );
			}

			$log[] = array(
				'id'    => $post_id,
				'title' => mb_substr( $post->post_title, 0, 60 ),
				'names' => $found_names,
				'terms' => $term_ids,
			);
		}

		wp_send_json_success(
			array(
				'count'  => count( $posts ),
				'log'    => $log,
				'offset' => $offset,
			)
		);
	}

	/**
	 * AJAX: reset module progress options.
	 *
	 * @return void
	 */
	public function ajax_reset() {
		$this->ajax_guard();
		delete_option( 'w2p_actor_scan_progress' );
		wp_send_json_success( array( 'message' => 'reset' ) );
	}

	/**
	 * AJAX: current stats (terms created, avatars synced, matched posts).
	 *
	 * @return void
	 */
	public function ajax_stats() {
		$this->ajax_guard();

		$progress = get_option( 'w2p_actor_scan_progress', array() );

		wp_send_json_success(
			array(
				'progress' => $progress,
			)
		);
	}

	/**
	 * Parse requested post types from POST.
	 *
	 * @return array
	 */
	protected function requested_post_types() {
		$types = isset( $_POST['post_types'] ) ? (array) wp_unslash( $_POST['post_types'] ) : array( 'post' );
		$types = array_map( 'sanitize_key', $types );
		$types = array_filter( $types );
		return array_values( $types );
	}

	/**
	 * Count scan targets.
	 *
	 * @param array $post_types Post types.
	 * @return int
	 */
	protected function count_scan_targets( $post_types ) {
		global $wpdb;

		if ( empty( $post_types ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders})",
			$post_types
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get one batch of posts to scan.
	 *
	 * @param array $post_types      Post types.
	 * @param int   $offset          Offset.
	 * @param int   $limit           Batch size.
	 * @param bool  $only_unassigned Only posts without any humans term.
	 * @return array
	 */
	protected function get_scan_batch( $post_types, $offset, $limit, $only_unassigned ) {
		global $wpdb;

		if ( empty( $post_types ) ) {
			return array();
		}

		$offset = absint( $offset );
		$limit  = max( 1, absint( $limit ) );

		if ( $only_unassigned ) {
			$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$sql          = $wpdb->prepare(
				"SELECT p.* FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				 LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'humans'
				 WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders}) AND tt.term_taxonomy_id IS NULL
				 GROUP BY p.ID ORDER BY p.ID ASC LIMIT %d OFFSET %d",
				array_merge( $post_types, array( $limit, $offset ) )
			);
		} else {
			$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$sql          = $wpdb->prepare(
				"SELECT p.* FROM {$wpdb->posts} p
				 WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
				 ORDER BY p.ID ASC LIMIT %d OFFSET %d",
				array_merge( $post_types, array( $limit, $offset ) )
			);
		}

		return $wpdb->get_results( $sql );
	}
}
