<?php
/**
 * REST API Handler
 *
 * @package SmartAutoUploadImages\Admin
 */

namespace SmartAutoUploadImages\Admin;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Handler Class
 */
class RestApi {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private string $namespace = 'smart-aui/v1';

	/**
	 * Settings manager.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_manager = \SmartAutoUploadImages\get_container()->get( 'settings_manager' );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/settings',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => $this->get_settings_schema(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/settings/reset',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'reset_settings' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/preview-pattern',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'preview_pattern' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'pattern' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Get settings callback.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		$settings = $this->settings_manager->get_settings();
		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Update settings callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$new_settings = $this->settings_manager->sanitize_settings( $request->get_json_params() );

		// Validate settings.
		$errors = $this->settings_manager->validate_settings( $new_settings );
		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', 'Settings validation failed', $errors );
		}

		// Update settings.
		$result = $this->settings_manager->update_settings( $new_settings );

		if ( $result ) {
			$updated_settings = $this->settings_manager->get_settings();
			return new WP_REST_Response( $updated_settings, 200 );
		}

		return new WP_Error( 'update_failed', 'Failed to update settings', [ 'status' => 500 ] );
	}

	/**
	 * Reset settings callback.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_settings() {
		$result = $this->settings_manager->reset_settings();

		if ( $result ) {
			$settings = $this->settings_manager->get_settings();
			return new WP_REST_Response( $settings, 200 );
		}

		return new WP_Error( 'reset_failed', 'Failed to reset settings', [ 'status' => 500 ] );
	}

	/**
	 * Preview pattern callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function preview_pattern( WP_REST_Request $request ): WP_REST_Response {
		$pattern = $request->get_param( 'pattern' );

		// Create mock data for preview.
		$mock_data = [
			'post_title'  => 'Sample Blog Post',
			'post_id'     => 123,
			'post_name'   => 'sample-blog-post',
			'post_date'   => current_time( 'mysql' ),
			'image_alt'   => 'Sample Alt Text',
			'image_title' => 'Sample Image Title',
			'filename'    => 'sample-image',
		];

		$pattern_resolver = new \SmartAutoUploadImages\Services\PatternResolver();
		$preview          = $pattern_resolver->resolve_pattern( $pattern, $mock_data );

		return new WP_REST_Response( [ 'preview' => $preview ], 200 );
	}

	/**
	 * Check permissions.
	 *
	 * @return bool
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get settings schema.
	 *
	 * @return array
	 */
	private function get_settings_schema(): array {
		return [
			'base_url'           => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'image_name_pattern' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'alt_text_pattern'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'max_width'          => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'max_height'         => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'exclude_post_types' => [
				'type'  => 'array',
				'items' => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
			'exclude_domains'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			],
			'auto_set_featured_image' => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'show_progress_ui' => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'process_images_on_rest_api' => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
		];
	}
}
