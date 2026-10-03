<?php
/**
 * Smart AUI — Media Attach Filter (find_posts Dialog Enhancement)
 *
 * Enhances the WordPress core find_posts modal in the media library:
 *  - pre_get_posts modifies the wp_ajax_find_posts query: post_type whitelist multi-selection / ID range / post_status filtering;
 *  - Search query matches post_title only (via one-time posts_where LIKE injection), posts_per_page adjusted to 25;
 *  - admin_print_footer_scripts injects filter controls (Type / ID Range / Status) and intercepts the Search action.
 *
 * Coexistence strategy: If child theme legacy functions (w2p_filter_find_posts_query /
 * w2p_force_title_only / w2p_inject_find_posts_ui) exist, plugin yields and lets child theme execute;
 * JS idempotency flag window.w2pFindPostsInjected ensures UI renders only once.
 *
 * Only mounted when the Smart AUI toggle `smart_aui_media_find_posts_filter` is enabled.
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_SmartAUI_Media_FindPosts_Filter
 *
 * Media Attach Filter: find_posts AJAX query enhancement + Attach modal UI injection.
 */
class W2P_SmartAUI_Media_FindPosts_Filter {

	/**
	 * Whitelisted post types for attachment search.
	 *
	 * get_post_types( array( 'public' => true ) ) would miss non-public CPTs (like novel/albums)
	 * and include block theme internal types (like wp_template/wp_block);
	 * chapter is always excluded; attachment is the item being attached and must not be queried.
	 *
	 * @var array
	 */
	private $allowed_types = array( 'post', 'page', 'novel', 'albums' );

	/**
	 * Whitelisted post statuses (publish|draft|any|all; default: any).
	 *
	 * @var array
	 */
	private $allowed_status = array( 'publish', 'draft', 'any', 'all' );

	/**
	 * Items per page for find_posts modal (reduced from 50 to 25).
	 *
	 * @var int
	 */
	private $per_page = 25;

	/**
	 * Max post ID count limit (safety guardrail: aborts condition if range() expands beyond this limit).
	 *
	 * @var int
	 */
	private $max_post_ids = 5000;

	/**
	 * Constructor: Mount hooks.
	 *
	 * posts_where filter is not registered in the constructor, but attached on demand
	 * inside filter_find_posts_query and self-removes after single execution.
	 */
	public function __construct() {
		// Backend query modification: wp_ajax_find_posts requests.
		add_action( 'pre_get_posts', array( $this, 'filter_find_posts_query' ), 10 );

		// Modal filter UI injection: output on upload.php when media scripts are loaded.
		add_action( 'admin_print_footer_scripts', array( $this, 'inject_find_posts_ui' ), 10 );
	}

	/**
	 * Modify query parameters in wp_ajax_find_posts requests.
	 *
	 * Target conditions: is_admin() + DOING_AJAX + action === 'find_posts'.
	 * (Note: wp_ajax_find_posts uses get_posts() which creates a non-main query, so is_main_query() is false).
	 *
	 * @param WP_Query $query Current query object.
	 * @return void
	 */
	public function filter_find_posts_query( $query ) {
		// Every request parameter read below arrives inside core's wp_ajax_find_posts handler, whose
		// first statement is check_ajax_referer( 'find-posts' ). The values only shape a read-only
		// search query: nothing here writes state.
		// phpcs:disable WordPress.Security.NonceVerification -- Downstream of core's verified find-posts nonce.
		// Coexistence guard: If child theme legacy callback exists, yield execution to it.
		if ( function_exists( 'w2p_filter_find_posts_query' ) ) {
			return;
		}

		if ( ! is_admin() || ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return;
		}
		if ( ( $_REQUEST['action'] ?? '' ) !== 'find_posts' ) {
			return;
		}

		$allowed_types = $this->allowed_types;

		// ----- post_type whitelist selection -----
		if ( isset( $_POST['ac_post_type'] ) ) {
			$requested = sanitize_key( wp_unslash( $_POST['ac_post_type'] ) );
			if ( 'all' === $requested ) {
				$post_types = $allowed_types;
			} elseif ( in_array( $requested, $allowed_types, true ) ) {
				$post_types = array( $requested );
			} else {
				$post_types = $allowed_types;
			}
		} else {
			$post_types = $allowed_types;
		}

		// Always exclude 'chapter' post type.
		$post_types = array_values( array_diff( $post_types, array( 'chapter' ) ) );
		if ( empty( $post_types ) ) {
			$post_types = $allowed_types;
		}
		$query->set( 'post_type', $post_types );

		// ----- ID range (e.g. 100-500 or 12,34; ignored if invalid) -----
		$post_ids = array();
		if ( ! empty( $_POST['ac_post_id_range'] ) ) {
			$range = (string) wp_unslash( $_POST['ac_post_id_range'] );
			if ( preg_match( '/^\s*(\d+)\s*-\s*(\d+)\s*$/', $range, $m ) ) {
				$start = (int) $m[1];
				$end   = (int) $m[2];
				if ( $start <= $end ) {
					// Safety guardrail: Check span before range expansion to prevent memory exhaustion.
					if ( $end <= $start + $this->max_post_ids - 1 ) {
						$post_ids = range( $start, $end );
					}
				}
			} elseif ( preg_match( '/^[\d,\s]+$/', $range ) ) {
				$post_ids = array_filter(
					array_map( 'absint', explode( ',', $range ) ),
					function ( $x ) {
						return $x > 0;
					}
				);
				$post_ids = array_values( $post_ids );
			}
		}

		// Apply post__in if within limit.
		if ( ! empty( $post_ids ) && count( $post_ids ) <= $this->max_post_ids ) {
			$query->set( 'post__in', $post_ids );
		}

		// ----- post_status (publish|draft|any|all; default: any) -----
		$post_status = 'any';
		if ( ! empty( $_POST['ac_post_status'] ) ) {
			$s = strtolower( sanitize_key( wp_unslash( $_POST['ac_post_status'] ) ) );
			if ( in_array( $s, $this->allowed_status, true ) ) {
				$post_status = $s;
			}
		}
		$query->set( 'post_status', $post_status );

		// ----- Pagination: 25 items per page -----
		$query->set( 'posts_per_page', $this->per_page );

		// ----- Search term matching post_title only -----
		if ( ! empty( $_POST['ps'] ) ) {
			// Clear default s to prevent full-text search on content, then inject title-only in posts_where.
			$query->query_vars['s'] = '';
			// get_posts sets suppress_filters=true by default; must explicitly enable filters.
			$query->set( 'suppress_filters', false );
			$query->set( '_w2p_smart_aui_title_only', true );
			add_filter( 'posts_where', array( $this, 'force_title_only' ), 10, 2 );
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * One-time posts_where filter injection to restrict search to post_title LIKE.
	 *
	 * Only applies to queries flagged with _w2p_smart_aui_title_only and unhooks itself immediately.
	 *
	 * @param string   $where WHERE clause.
	 * @param WP_Query $query Current query object.
	 * @return string
	 */
	public function force_title_only( $where, $query ) {
		if ( ! $query->get( '_w2p_smart_aui_title_only' ) ) {
			return $where;
		}

		// One-time: unhook immediately to prevent side-effects on subsequent queries.
		remove_filter( 'posts_where', array( $this, 'force_title_only' ), 10 );

		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same verified find_posts request; only filters the WHERE clause.
		$term = wp_unslash( $_POST['ps'] ?? '' );
		if ( '' !== $term ) {
			$like   = '%' . $wpdb->esc_like( $term ) . '%';
			$where .= $wpdb->prepare( ' AND (post_title LIKE %s) ', $like );
		}

		return $where;
	}

	/**
	 * Inject additional filter UI into WP native Attach modal (find-posts):
	 * Post Type / ID Range / Status.
	 *
	 * Scope: Only wp-admin/upload.php when the core 'media' script is loaded.
	 *
	 * @return void
	 */
	public function inject_find_posts_ui() {
		// Coexistence guard: Yield to child theme if callback is already defined.
		if ( function_exists( 'w2p_inject_find_posts_ui' ) ) {
			return;
		}

		// Scope check 1: Must be upload.php.
		global $pagenow;
		if ( ! isset( $pagenow ) || 'upload.php' !== $pagenow ) {
			return;
		}

		// Scope check 2: Must have core media script enqueued.
		if ( ! function_exists( 'wp_script_is' ) || ! wp_script_is( 'media' ) ) {
			return;
		}

		// Whitelisted post type options.
		$options = sprintf(
			'<option value="all" selected>%1$s</option>' .
			'<option value="post">%2$s</option>' .
			'<option value="albums">%3$s</option>' .
			'<option value="novel">%4$s</option>',
			esc_html__( 'All', 'wp-genius' ),
			esc_html__( 'Posts', 'wp-genius' ),
			esc_html__( 'Albums', 'wp-genius' ),
			esc_html__( 'Novels', 'wp-genius' )
		);

		// HTML markup for filter injection.
		$html = '<div class="w2p-find-posts-extras">'
			. '<label>' . esc_html__( 'Type', 'wp-genius' )
			. '<select name="ac_post_type">' . $options . '</select></label>'
			. '<label>' . esc_html__( 'ID', 'wp-genius' )
			. '<input type="text" name="ac_post_id_range" placeholder="'
			. esc_attr__( '100-500 or 12,34', 'wp-genius' ) . '"></label>'
			. '<label>' . esc_html__( 'Status', 'wp-genius' )
			. '<select name="ac_post_status">'
			. '<option value="any">' . esc_html__( 'Default', 'wp-genius' ) . '</option>'
			. '<option value="publish">' . esc_html__( 'Published', 'wp-genius' ) . '</option>'
			. '<option value="draft">' . esc_html__( 'Draft', 'wp-genius' ) . '</option>'
			. '<option value="all">' . esc_html__( 'All', 'wp-genius' ) . '</option>'
			. '</select></label>'
			. '<input type="hidden" name="ac_exclude_chapter" value="1">'
			. '</div>';

		// Localized UI strings.
		$i18n = array(
			'searching' => __( 'Searching...', 'wp-genius' ),
			'no_items'  => __( 'No items found.', 'wp-genius' ),
			'error'     => __( 'Request failed. Please try again.', 'wp-genius' ),
		);

		$plugin_url = plugin_dir_url( __DIR__ );
		$css_path   = dirname( __DIR__ ) . '/assets/css/smart-aui-admin.css';
		$css_ver    = file_exists( $css_path ) ? filemtime( $css_path ) : W2P_VERSION;
		$js_path    = dirname( __DIR__ ) . '/assets/js/smart-aui-find-posts.js';
		$js_ver     = file_exists( $js_path ) ? filemtime( $js_path ) : W2P_VERSION;

		wp_enqueue_style(
			'w2p-smart-aui-admin',
			$plugin_url . 'assets/css/smart-aui-admin.css',
			array( 'w2p-core-css' ),
			$css_ver
		);

		wp_enqueue_script(
			'w2p-smart-aui-find-posts',
			$plugin_url . 'assets/js/smart-aui-find-posts.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_localize_script(
			'w2p-smart-aui-find-posts',
			'w2pFindPostsParams',
			array(
				'html' => $html,
				'i18n' => $i18n,
			)
		);
	}
}
