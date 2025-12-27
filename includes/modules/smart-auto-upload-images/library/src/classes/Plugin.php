<?php
/**
 * Main Plugin Class
 *
 * @package SmartAutoUploadImages
 */

namespace SmartAutoUploadImages;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class
 */
class Plugin {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin
	 */
	private function init(): void {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks
	 */
	private function setup_hooks(): void {
		// Post processing hooks.
		add_filter( 'wp_insert_post_data', [ $this, 'process_post_images' ], 10, 2 );
		add_action( 'init', [ $this, 'register_custom_post_fields' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_scripts' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_filter( 'plugin_action_links_' . SMART_AUI_PLUGIN_BASENAME, [ $this, 'add_settings_link' ] );
	}

	/**
	 * Get plugin settings with defaults
	 *
	 * @return array Plugin settings with defaults applied
	 */
	public static function get_settings(): array {
		$defaults = [
			'base_url'           => site_url(),
			'image_name_pattern' => '%filename%',
			'alt_text_pattern'   => '%image_alt%',
			'max_width'          => 0,
			'max_height'         => 0,
			'exclude_post_types' => [],
			'exclude_domains'    => '',
			'version'            => SMART_AUI_VERSION,
		];

		$stored_settings = get_option( 'smart_aui_settings', [] );
		return wp_parse_args( $stored_settings, $defaults );
	}

	/**
	 * Process post images
	 *
	 * @param array $data Post data.
	 * @param array $postarr Post array.
	 * @return array Modified post data.
	 */
	public function process_post_images( array $data, array $postarr ): array {
		// If forced processing is requested, we skip AJAX and Revision checks
		$is_forced = ( defined( 'W2P_FORCE_IMAGE_PROCESS' ) && W2P_FORCE_IMAGE_PROCESS );

		if ( ! $is_forced ) {
			if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
				return $data;
			}

			if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
				return $data;
			}

			if ( wp_is_post_autosave( $postarr['ID'] ) ) {
				return $data;
			}

			if ( wp_is_post_revision( $postarr['ID'] ) ) {
				return $data;
			}
		}

		// Don't process when trashing or untrashing a post.
		if ( isset( $data['post_status'] ) && 'trash' === $data['post_status'] ) {
			return $data;
		}

		if ( ! empty( $postarr['ID'] ) && 'trash' === get_post_status( $postarr['ID'] ) ) {
			return $data;
		}

		// Check for REST API request and settings.
		if ( ! $is_forced && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$settings_manager = \SmartAutoUploadImages\get_container()->get( 'settings_manager' );
			$settings         = $settings_manager->get_settings();

			if ( empty( $settings['process_images_on_rest_api'] ) ) {
				return $data;
			}
		}

		// Process images.
		$image_processor   = \SmartAutoUploadImages\get_container()->get( 'image_processor' );
		$processed_content = $image_processor->process_post_content( $data['post_content'], $postarr );

		if ( false !== $processed_content ) {
			$data['post_content'] = $processed_content;
		}

		return $data;
	}

	/**
	 * Register custom post fields
	 */
	public function register_custom_post_fields(): void {
		register_rest_field(
			[ 'post', 'page' ],
			'smart_aui_featured_image_url',
			[
				'update_callback' => [ $this, 'update_featured_image_url_field' ],
			]
		);
	}

	/**
	 * Update featured image URL field value
	 *
	 * @param string $value New value.
	 * @param object $post  Post object.
	 * @return bool True on success.
	 */
	public function update_featured_image_url_field( string $value, $post ): bool {
		$image_downloader = \SmartAutoUploadImages\get_container()->get( 'image_downloader' );
		$result           = $image_downloader->download_image( [ 'url' => $value ], [ 'ID' => $post->ID ] );
		if ( is_wp_error( $result ) ) {
			return false;
		}

		$attachment_id = $result['attachment_id'];
		set_post_thumbnail( $post->ID, $attachment_id );
		return true;
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_admin_scripts( string $hook_suffix ): void {
		// Only load on our settings page.
		if ( 'settings_page_smart-auto-upload-images' !== $hook_suffix ) {
			return;
		}

		$asset_file = include SMART_AUI_PLUGIN_DIR . 'dist/js/admin-settings.asset.php';
		wp_enqueue_script(
			'smart-aui-admin-settings',
			SMART_AUI_PLUGIN_URL . 'dist/js/admin-settings.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		$asset_file = include SMART_AUI_PLUGIN_DIR . 'dist/css/admin-settings-style.asset.php';
		wp_enqueue_style(
			'smart-aui-admin-settings-style',
			SMART_AUI_PLUGIN_URL . 'dist/css/admin-settings-style.css',
			[ 'wp-components' ],
			$asset_file['version'],
		);
	}

	/**
	 * Enqueue editor scripts
	 */
	public function enqueue_editor_scripts(): void {
		wp_enqueue_script(
			'smart-aui-editor',
			SMART_AUI_PLUGIN_URL . 'dist/js/admin-editor.js',
			\SmartAutoUploadImages\Utils\get_asset_info( 'admin-editor', 'dependencies' ),
			\SmartAutoUploadImages\Utils\get_asset_info( 'admin-editor', 'version' ),
			true
		);
	}

	/**
	 * Register admin menu
	 */
	public function register_admin_menu(): void {
		add_options_page(
			__( 'Smart Auto Upload Images Settings', 'smart-auto-upload-images' ),
			__( 'Smart Auto Upload Images', 'smart-auto-upload-images' ),
			'manage_options',
			'smart-auto-upload-images',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page(): void {
		echo '<div id="smart-aui-admin-root"></div>';
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes(): void {
		$rest_api = new \SmartAutoUploadImages\Admin\RestApi();
		$rest_api->register_routes();
	}

	/**
	 * Add settings link to plugin actions
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=smart-auto-upload-images' ),
			esc_html__( 'Settings', 'smart-auto-upload-images' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Get formatted post types for frontend
	 *
	 * @return array Formatted post types array
	 */
	public function get_formatted_post_types(): array {
		$post_types           = get_post_types( [ 'public' => true ], 'objects' );
		$formatted_post_types = [];

		foreach ( $post_types as $post_type ) {
			$formatted_post_types[] = [
				'value' => $post_type->name,
				'label' => $post_type->label,
			];
		}

		return $formatted_post_types;
	}
}
