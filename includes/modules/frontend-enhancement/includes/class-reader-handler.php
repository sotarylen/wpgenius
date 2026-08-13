<?php
/**
 * Reader Handler Class
 *
 * Backend handler for Book Chapter Reader functionality.
 * Handles server-side configuration and container rendering.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reader Handler
 */
class WPG_Reader_Handler {

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor
	 */
	public function __construct( $settings = array() ) {
		$this->settings = $settings;
		$this->init();
	}

	/**
	 * Initialize
	 */
	public function init() {
		// Only render if enabled
		if ( empty( $this->settings['reader_enabled'] ) ) {
			return;
		}

		// Hook into footer to render configuration
		add_action( 'wp_footer', array( $this, 'render_reader_config' ) );
	}

	/**
	 * Render Reader Configuration
	 * This provides configuration data to JavaScript but doesn't render UI elements
	 */
	public function render_reader_config() {
		// Only render on singular posts/pages
		if ( ! is_singular() ) {
			return;
		}

		// Defaults
		$font_size   = isset( $this->settings['reader_font_size'] ) ? intval( $this->settings['reader_font_size'] ) : 18;
		$font_family = isset( $this->settings['reader_font_family'] ) ? $this->settings['reader_font_family'] : 'sans';
		$theme       = isset( $this->settings['reader_theme'] ) ? $this->settings['reader_theme'] : 'light';

		// Navigation Links Calculation
		$nav_links = array(
			'prev' => '',
			'next' => '',
			'toc'  => '',
		);

		$post_id       = get_the_ID();
		$novel_id      = get_post_meta( $post_id, 'related_novel_id', true );
		$current_index = get_post_meta( $post_id, 'chapter_index', true );

		if ( $novel_id ) {
			// 1. Table of Contents (Novel Home)
			$nav_links['toc'] = get_permalink( $novel_id );

			// 2. Previous Chapter
			if ( $current_index !== '' ) {
				$prev_chapters = get_posts(
					array(
						'post_type'      => 'chapter',
						'posts_per_page' => 1,
						'meta_query'     => array(
							'relation' => 'AND',
							array(
								'key'   => 'related_novel_id',
								'value' => $novel_id,
							),
							array(
								'key'     => 'chapter_index',
								'value'   => $current_index,
								'compare' => '<',
								// 'type' => 'CHAR' // Default string comparison works for "01-00001" format
							),
						),
						'orderby'        => 'meta_value', // String order
						'meta_key'       => 'chapter_index',
						'order'          => 'DESC',
					)
				);

				if ( ! empty( $prev_chapters ) ) {
					$nav_links['prev'] = get_permalink( $prev_chapters[0]->ID );
				}

				// 3. Next Chapter
				$next_chapters = get_posts(
					array(
						'post_type'      => 'chapter',
						'posts_per_page' => 1,
						'meta_query'     => array(
							'relation' => 'AND',
							array(
								'key'   => 'related_novel_id',
								'value' => $novel_id,
							),
							array(
								'key'     => 'chapter_index',
								'value'   => $current_index,
								'compare' => '>',
							),
						),
						'orderby'        => 'meta_value', // String order
						'meta_key'       => 'chapter_index',
						'order'          => 'ASC',
					)
				);

				if ( ! empty( $next_chapters ) ) {
					$nav_links['next'] = get_permalink( $next_chapters[0]->ID );
				}
			}
		}

		?>
		<!-- WP Genius Reader Configuration -->
		<script type="text/javascript">
			// Store default configuration for reader
			if (typeof window.wpgReaderDefaults === 'undefined') {
				window.wpgReaderDefaults = {
					fontSize: <?php echo wp_json_encode( $font_size ); ?>,
					fontFamily: <?php echo wp_json_encode( $font_family ); ?>,
					theme: <?php echo wp_json_encode( $theme ); ?>,
					links: <?php echo wp_json_encode( $nav_links ); ?>
				};
			}
		</script>
		<!-- End WP Genius Reader Configuration -->
		<?php
	}
}
