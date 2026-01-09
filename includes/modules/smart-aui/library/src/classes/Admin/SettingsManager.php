<?php
/**
 * Settings Manager
 *
 * @package SmartAutoUploadImages\Admin
 */

namespace SmartAutoUploadImages\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager Class
 */
class SettingsManager {

	/**
	 * Settings option key
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'smart_aui_settings';

	/**
	 * Cached settings
	 *
	 * @var array|null
	 */
	private ?array $settings = null;

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public function get_settings(): array {
		if ( null === $this->settings ) {
			$stored_settings = get_option( self::OPTION_KEY, [] );

			$this->settings = wp_parse_args( $stored_settings, $this->get_default_settings() );
		}

		if ( empty( $this->settings['base_url'] ) ) {
			$this->settings['base_url'] = get_site_url();
		}

		return $this->settings;
	}

	/**
	 * Get specific setting
	 *
	 * @param string $key The setting key.
	 * @param mixed  $default_setting Default value if not found.
	 * @return mixed
	 */
	public function get_setting( string $key, $default_setting = null ) {
		$settings = $this->get_settings();

		$setting = $settings[ $key ] ?? $default_setting;

		/**
		 * Filter the setting value.
		 *
		 * @since 1.2.0
		 * @hook smart_aui_get_setting
		 *
		 * @param mixed  $setting The setting value.
		 * @param string $key The setting key.
		 * @param mixed  $default_setting Default value if not found.
		 * @return mixed
		 */
		return apply_filters( 'smart_aui_get_setting', $setting, $key, $default_setting );
	}

	/**
	 * Update settings
	 *
	 * @param array $new_settings New settings.
	 * @return bool
	 */
	public function update_settings( array $new_settings ): bool {
		$current_settings = $this->get_settings();
		$updated_settings = wp_parse_args( $new_settings, $current_settings );

		$result = update_option( self::OPTION_KEY, $updated_settings );

		if ( $result ) {
			$this->settings = $updated_settings;
		}

		return true;
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $settings ): array {
		$sanitized = [];

		if ( isset( $settings['base_url'] ) ) {
			$sanitized['base_url'] = esc_url_raw( $settings['base_url'] );
		}

		if ( isset( $settings['image_name_pattern'] ) ) {
			$sanitized['image_name_pattern'] = sanitize_text_field( $settings['image_name_pattern'] );
		}

		if ( isset( $settings['alt_text_pattern'] ) ) {
			$sanitized['alt_text_pattern'] = sanitize_text_field( $settings['alt_text_pattern'] );
		}

		if ( isset( $settings['max_width'] ) ) {
			$sanitized['max_width'] = absint( $settings['max_width'] );
		}

		if ( isset( $settings['max_height'] ) ) {
			$sanitized['max_height'] = absint( $settings['max_height'] );
		}

		if ( isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ) {
			$sanitized['exclude_post_types'] = array_map( 'sanitize_text_field', $settings['exclude_post_types'] );
		}

		if ( isset( $settings['exclude_domains'] ) ) {
			$sanitized['exclude_domains'] = sanitize_textarea_field( $settings['exclude_domains'] );
		}

		if ( isset( $settings['auto_set_featured_image'] ) ) {
			$sanitized['auto_set_featured_image'] = (bool) $settings['auto_set_featured_image'];
		}

		if ( isset( $settings['show_progress_ui'] ) ) {
			$sanitized['show_progress_ui'] = (bool) $settings['show_progress_ui'];
		}

		if ( isset( $settings['skip_duplicates'] ) ) {
			$sanitized['skip_duplicates'] = (bool) $settings['skip_duplicates'];
		}

		if ( isset( $settings['process_images_on_rest_api'] ) ) {
			$sanitized['process_images_on_rest_api'] = (bool) $settings['process_images_on_rest_api'];
		}

		if ( isset( $settings['concurrent_threads'] ) ) {
			$sanitized['concurrent_threads'] = max( 1, min( 16, absint( $settings['concurrent_threads'] ) ) );
		}

		if ( isset( $settings['max_retries'] ) ) {
			$sanitized['max_retries'] = min( 10, absint( $settings['max_retries'] ) );
		}

		return $sanitized;
	}

	/**
	 * Validate settings
	 *
	 * @param array $settings Settings to validate.
	 * @return array Validation errors.
	 */
	public function validate_settings( array $settings ): array {
		$errors = [];

		if ( ! empty( $settings['base_url'] ) && ! wp_http_validate_url( $settings['base_url'] ) ) {
			$errors['base_url'] = __( 'Please enter a valid URL for the base URL.', 'smart-auto-upload-images' );
		}

		if ( empty( $settings['image_name_pattern'] ) ) {
			$errors['image_name_pattern'] = __( 'Image name pattern cannot be empty.', 'smart-auto-upload-images' );
		}

		if ( empty( $settings['alt_text_pattern'] ) ) {
			$errors['alt_text_pattern'] = __( 'Alt text pattern cannot be empty.', 'smart-auto-upload-images' );
		}

		if ( isset( $settings['max_width'] ) && $settings['max_width'] < 0 ) {
			$errors['max_width'] = __( 'Max width must be a positive number.', 'smart-auto-upload-images' );
		}

		if ( isset( $settings['max_height'] ) && $settings['max_height'] < 0 ) {
			$errors['max_height'] = __( 'Max height must be a positive number.', 'smart-auto-upload-images' );
		}

		return $errors;
	}

	/**
	 * Reset settings to defaults
	 *
	 * @return bool
	 */
	public function reset_settings(): bool {
		$result = update_option( self::OPTION_KEY, $this->get_default_settings() );

		if ( $result ) {
			$this->settings = $this->get_default_settings();
		}

		return true;
	}

	/**
	 * Get default settings
	 *
	 * @return array
	 */
	public function get_default_settings(): array {
		$settings = [
			'base_url'           => get_site_url(),
			'image_name_pattern' => '%filename%',
			'alt_text_pattern'   => '%image_alt%',
			'max_width'          => 0,
			'max_height'         => 0,
			'exclude_post_types' => [],
			'exclude_domains'    => '',
			'auto_set_featured_image' => true,
			'show_progress_ui' => true,
			'skip_duplicates' => true,
			'process_images_on_rest_api' => true,
			'concurrent_threads' => 4,
			'max_retries'        => 3,
		];

		return $settings;
	}
}
