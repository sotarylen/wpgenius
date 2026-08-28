<?php
/**
 * Smart AUI — Post List External Media Filter
 *
 * Adds an "External Media Filter" button next to "Search Posts" on edit.php.
 * Filters posts containing external images/media and excludes drafts.
 * Controlled by module switcher setting.
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
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ), 10, 2 );
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
	 * Modify WP_Query to exclude drafts and prepare external media filtering.
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
	}

	/**
	 * Add SQL clauses to filter posts containing external media.
	 *
	 * @param array    $clauses Array of query clauses.
	 * @param WP_Query $query   Query instance.
	 * @return array
	 */
	public function filter_posts_clauses( array $clauses, $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return $clauses;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['w2p_external_filter'] ) || '1' !== (string) $_GET['w2p_external_filter'] ) {
			return $clauses;
		}

		global $wpdb;

		$settings       = $this->module->get_settings();
		$capture_videos = ! empty( $settings['smart_aui_capture_videos'] );

		// Match posts with img or video tags
		$content_clause = $capture_videos
			? "( {$wpdb->posts}.post_content LIKE '%<img%' OR {$wpdb->posts}.post_content LIKE '%<video%' OR {$wpdb->posts}.post_content LIKE '%[video%' )"
			: "{$wpdb->posts}.post_content LIKE '%<img%'";

		// Exclude already scanned clean posts
		$clauses['join']  .= " LEFT JOIN {$wpdb->postmeta} pm_clean ON ( {$wpdb->posts}.ID = pm_clean.post_id AND pm_clean.meta_key = '_w2p_smart_aui_clean' ) ";
		$clauses['where'] .= " AND {$content_clause} AND pm_clean.meta_value IS NULL ";

		return $clauses;
	}
}
