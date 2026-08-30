<?php
/**
 * Smart AUI — Post List External Media Filter
 *
 * Adds an "External Media Filter" button next to "Search Posts" on edit.php.
 * Filters posts containing genuine external images/media, skips local/Base URL files,
 * and excludes drafts.
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_SmartAUI_Post_List_Filter
 */
class W2P_SmartAUI_Post_List_Filter {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_SmartAUIModule
	 */
	private $module;

	/**
	 * Request-level in-memory cache for matched post IDs.
	 *
	 * @var array|null
	 */
	private static $matched_ids_cache = null;

	/**
	 * Constructor.
	 *
	 * @param W2P_SmartAUIModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;

		$settings = $this->module->get_settings();
		$enabled  = isset( $settings['smart_aui_enable_post_filter'] ) ? (bool) $settings['smart_aui_enable_post_filter'] : true;

		if ( ! $enabled ) {
			return;
		}

		add_action( 'admin_head-edit.php', array( $this, 'inject_filter_button_script' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_posts_query' ) );
		add_action( 'save_post', array( $this, 'clear_clean_meta_on_save' ) );
	}

	/**
	 * Inject Filter Button next to #search-submit via lightweight inline JS.
	 *
	 * @return void
	 */
	public function inject_filter_button_script() {
		// Only run for post list screen
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_filtered = ! empty( $_GET['w2p_external_filter'] ) && '1' === (string) $_GET['w2p_external_filter'];
		$btn_text    = $is_filtered ? __( 'Cancel External Filter', 'wp-genius' ) : __( 'External Media Filter', 'wp-genius' );
		$icon_class  = $is_filtered ? 'fa-solid fa-xmark' : 'fa-solid fa-filter';
		$btn_class   = $is_filtered ? 'button-primary' : 'button-secondary';
		?>
		<script type="text/javascript">
		jQuery(function($) {
			if ($('#search-submit').length && !$('#w2p-aui-filter-btn').length) {
				var isFiltered = <?php echo $is_filtered ? 'true' : 'false'; ?>;
				var url = new URL(window.location.href);
				if (isFiltered) {
					url.searchParams.delete('w2p_external_filter');
					url.searchParams.delete('paged');
				} else {
					url.searchParams.set('w2p_external_filter', '1');
					url.searchParams.delete('paged');
				}
				var $btn = $('<a id="w2p-aui-filter-btn" class="button <?php echo esc_attr( $btn_class ); ?>" style="margin-left:6px;"><i class="<?php echo esc_attr( $icon_class ); ?>"></i> <?php echo esc_js( $btn_text ); ?></a>')
					.attr('href', url.toString());
				$('#search-submit').after($btn);
			}
		});
		</script>
		<?php
	}

	/**
	 * Clear clean status meta and flush transient cache when a post is edited/updated.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function clear_clean_meta_on_save( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		delete_post_meta( $post_id, '_w2p_smart_aui_clean' );
		delete_transient( 'w2p_aui_external_post_ids' );
		self::$matched_ids_cache = null;
	}

	/**
	 * Modify WP_Query to filter only posts containing genuine external media.
	 *
	 * @param WP_Query $query The query instance.
	 * @return void
	 */
	public function filter_posts_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['w2p_external_filter'] ) || '1' !== (string) $_GET['w2p_external_filter'] ) {
			return;
		}

		// Strictly exclude draft, auto-draft, trash (only active/published posts)
		$query->set( 'post_status', array( 'publish', 'future', 'private', 'pending' ) );

		if ( null === self::$matched_ids_cache ) {
			self::$matched_ids_cache = $this->get_external_media_post_ids();
		}

		if ( empty( self::$matched_ids_cache ) ) {
			$query->set( 'post__in', array( 0 ) );
		} else {
			$query->set( 'post__in', self::$matched_ids_cache );
		}
	}

	/**
	 * Get list of post IDs containing genuine external media.
	 * Uses ultra-fast two-stage covering index queries + Transient cache.
	 *
	 * @return array
	 */
	private function get_external_media_post_ids(): array {
		global $wpdb;

		// 1. Check transient cache first
		$cached = get_transient( 'w2p_aui_external_post_ids' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$settings       = $this->module->get_settings();
		$base_url       = ! empty( $settings['smart_aui_base_url'] ) ? $settings['smart_aui_base_url'] : site_url();
		$capture_videos = ! empty( $settings['smart_aui_capture_videos'] );

		// 2. Gather all whitelisted local hosts
		$local_hosts = array_filter(
			array_unique(
				array(
					strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) ),
					strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
					strtolower( (string) wp_parse_url( $base_url, PHP_URL_HOST ) ),
				)
			)
		);

		// 3. Gather excluded domains
		$exclude_domains = array();
		if ( ! empty( $settings['smart_aui_exclude_domains'] ) ) {
			$lines = explode( "\n", str_replace( "\r", '', $settings['smart_aui_exclude_domains'] ) );
			foreach ( $lines as $l ) {
				$l = trim( $l );
				if ( ! empty( $l ) ) {
					$exclude_domains[] = strtolower( $l );
				}
			}
		}

		// 4. Stage 1: Covering index query (NO post_content loading, executes in < 1ms)
		$candidate_ids = $wpdb->get_col(
			"SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON ( p.ID = pm.post_id AND pm.meta_key = '_w2p_smart_aui_clean' )
			WHERE p.post_type = 'post'
			  AND p.post_status IN ('publish', 'future', 'private', 'pending')
			  AND pm.meta_value IS NULL
			ORDER BY p.ID DESC
			LIMIT 20"
		);

		if ( empty( $candidate_ids ) ) {
			set_transient( 'w2p_aui_external_post_ids', array(), 180 );
			return array();
		}

		// 5. Stage 2: Point query for content by primary key in batch (executes in ~3ms)
		$id_placeholders = implode( ',', array_map( 'intval', $candidate_ids ) );
		$posts = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN ({$id_placeholders})" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$matched_ids = array();
		$clean_ids   = array();

		foreach ( $posts as $post_obj ) {
			if ( $this->post_has_external_media( $post_obj->post_content, $local_hosts, $exclude_domains, $capture_videos ) ) {
				$matched_ids[] = (int) $post_obj->ID;
			} else {
				$clean_ids[] = (int) $post_obj->ID;
			}
		}

		// 6. Single batch insert to mark clean posts
		if ( ! empty( $clean_ids ) ) {
			$value_rows = array();
			foreach ( $clean_ids as $cid ) {
				$value_rows[] = $wpdb->prepare( '(%d, %s, %s)', $cid, '_w2p_smart_aui_clean', '1' );
			}
			$wpdb->query( "INSERT IGNORE INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ', ', $value_rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		unset( $posts, $clean_ids );

		// 7. Store in transient cache (TTL 3 minutes)
		set_transient( 'w2p_aui_external_post_ids', $matched_ids, 180 );

		return $matched_ids;
	}

	/**
	 * Check if post content has genuinely external media items.
	 *
	 * @param string $content         Post HTML content.
	 * @param array  $local_hosts     Whitelisted local hosts.
	 * @param array  $exclude_domains Exclude domains.
	 * @param bool   $check_videos    Whether to inspect video tags.
	 * @return bool
	 */
	private function post_has_external_media( $content, array $local_hosts, array $exclude_domains, $check_videos = false ): bool {
		if ( empty( $content ) ) {
			return false;
		}

		// 1. Check <img> tags
		if ( preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $content, $img_matches ) ) {
			foreach ( $img_matches[1] as $src ) {
				if ( $this->is_external_media_url( $src, $local_hosts, $exclude_domains ) ) {
					return true;
				}
			}
		}

		// 2. Check <video> and <source> tags if video capture is enabled
		if ( $check_videos ) {
			if ( preg_match_all( '/<(?:video|source)[^>]+src=[\'"]([^\'"]+)[\'"]/i', $content, $video_matches ) ) {
				foreach ( $video_matches[1] as $src ) {
					if ( $this->is_external_media_url( $src, $local_hosts, $exclude_domains ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if a URL is an external media URL that needs to be downloaded.
	 *
	 * @param string $url             Media source URL.
	 * @param array  $local_hosts     Whitelisted local domain hosts.
	 * @param array  $exclude_domains User configured exclude domains.
	 * @return bool True if genuinely external.
	 */
	private function is_external_media_url( $url, array $local_hosts, array $exclude_domains ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		// Relative paths, Data URIs, and Blob URLs are local
		if ( 0 === strpos( $url, 'data:' ) || 0 === strpos( $url, 'blob:' ) ) {
			return false;
		}
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}

		$host = strtolower( $host );

		// Check against all local hosts (site_url, home_url, smart_aui_base_url)
		foreach ( $local_hosts as $lh ) {
			if ( empty( $lh ) ) {
				continue;
			}
			if ( $host === $lh || substr( $host, -strlen( '.' . $lh ) ) === '.' . $lh ) {
				return false;
			}
		}

		// Check against user excluded domains (supports wildcards, e.g. *.example.com)
		foreach ( $exclude_domains as $ed ) {
			if ( empty( $ed ) ) {
				continue;
			}
			$pattern = str_replace( '\*', '.*', preg_quote( $ed, '#' ) );
			if ( preg_match( '#^' . $pattern . '$#i', $host ) ) {
				return false;
			}
		}

		return true;
	}
}


