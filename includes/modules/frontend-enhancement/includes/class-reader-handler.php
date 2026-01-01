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
	public function __construct( $settings = [] ) {
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
		add_action( 'wp_footer', [ $this, 'render_reader_config' ] );
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
		$font_size = isset( $this->settings['reader_font_size'] ) ? intval( $this->settings['reader_font_size'] ) : 18;
		$font_family = isset( $this->settings['reader_font_family'] ) ? $this->settings['reader_font_family'] : 'sans';
		$theme = isset( $this->settings['reader_theme'] ) ? $this->settings['reader_theme'] : 'light';

		?>
		<!-- WP Genius Reader Configuration -->
		<script type="text/javascript">
			// Store default configuration for reader
			if (typeof window.wpgReaderDefaults === 'undefined') {
				window.wpgReaderDefaults = {
					fontSize: <?php echo json_encode( $font_size ); ?>,
					fontFamily: <?php echo json_encode( $font_family ); ?>,
					theme: <?php echo json_encode( $theme ); ?>
				};
			}
		</script>
		<!-- End WP Genius Reader Configuration -->
		<?php
	}
}
