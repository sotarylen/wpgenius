<?php
/**
 * Image Validator Service
 *
 * @package SmartAutoUploadImages\Services
 */

namespace SmartAutoUploadImages\Services;

use SmartAutoUploadImages\Admin\SettingsManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Validator Class
 */
class ImageValidator {

	/**
	 * Settings manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = \SmartAutoUploadImages\get_container()->get( 'settings_manager' );
	}

	/**
	 * Validate if image URL should be processed
	 *
	 * @param string $url Image URL.
	 * @param array  $post_data Post data.
	 * @return bool|WP_Error True if valid, \WP_Error otherwise.
	 */
	public function validate_image_url( string $url, array $post_data ) {
		if ( ! $this->is_external_url( $url ) ) {
			return new \WP_Error( 'invalid_url', esc_html__( 'Image URL is not external', 'smart-auto-upload-images' ) );
		}

		if ( $this->is_domain_excluded( $url ) ) {
			return new \WP_Error( 'excluded_domain', esc_html__( 'Image URL is excluded', 'smart-auto-upload-images' ) );
		}

		if ( $this->is_post_type_excluded( $post_data['post_type'] ?? '' ) ) {
			return new \WP_Error( 'excluded_post_type', esc_html__( 'Image URL is excluded', 'smart-auto-upload-images' ) );
		}

		if ( ! $this->is_valid_url( $url ) ) {
			return new \WP_Error( 'invalid_url', esc_html__( 'Image URL is not valid', 'smart-auto-upload-images' ) );
		}

		/**
		 * Filter for custom image validation.
		 *
		 * @since 1.2.0
		 * @hook smart_aui_validate_image_url
		 *
		 * @param bool|WP_Error $validation_result The validation result.
		 *                                          - true: Image is valid and should be processed
		 *                                          - false: Image should be skipped (no error message)
		 *                                          - WP_Error: Image is invalid with specific error message
		 * @param string        $url The image URL being validated.
		 * @param array         $post_data The post data containing the image.
		 * @return bool|WP_Error True to allow processing, false to skip, WP_Error to block with message.
		 */
		$custom_validation = apply_filters( 'smart_aui_validate_image_url', true, $url, $post_data );
		if ( is_wp_error( $custom_validation ) ) {
			return $custom_validation;
		}

		if ( false === $custom_validation ) {
			return new \WP_Error( 'custom_validation_failed', esc_html__( 'Image validation failed', 'smart-auto-upload-images' ) );
		}

		return true;
	}

	/**
	 * Validate image file content
	 *
	 * @param string $file_content File content.
	 * @param array  $image_data Image data.
	 * @return bool True if valid image, false otherwise.
	 */
	public function validate_image_content( string $file_content, array $image_data ): bool {
		if ( empty( $file_content ) ) {
			return false;
		}

		// Extract extension from URL.
		$url_path  = wp_parse_url( $image_data['url'], PHP_URL_PATH );
		$extension = strtolower( pathinfo( $url_path, PATHINFO_EXTENSION ) );

		// Default to jpg if no extension found.
		if ( empty( $extension ) ) {
			$extension = 'jpg';
		}

		// Build test filename for validation.
		$filename = 'test.' . $extension;

		// Write content to temp file for validation.
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp_file = wp_tempnam();
		file_put_contents( $temp_file, $file_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$file_validation = wp_check_filetype_and_ext( $temp_file, $filename );

		$image_info = getimagesize( $temp_file );

		wp_delete_file( $temp_file );

		if ( false === $file_validation['type'] || empty( $file_validation['type'] ) ) {
			return false;
		}

		if ( false === $image_info ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if URL is external
	 *
	 * @param string $url URL to check.
	 * @return bool True if external, false otherwise.
	 */
	private function is_external_url( string $url ): bool {
		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );

		return $site_host !== $url_host;
	}

	/**
	 * Check if domain is excluded
	 *
	 * @param string $url URL to check.
	 * @return bool True if excluded, false otherwise.
	 */
	private function is_domain_excluded( string $url ): bool {
		$excluded_domains = $this->settings_manager->get_setting( 'exclude_domains', '' );

		if ( empty( $excluded_domains ) ) {
			return false;
		}

		$url_host      = wp_parse_url( $url, PHP_URL_HOST );
		$excluded_list = explode( "\n", $excluded_domains );

		foreach ( $excluded_list as $domain ) {
			$domain = trim( $domain );
			if ( empty( $domain ) ) {
				continue;
			}

			$domain_host = wp_parse_url( $domain, PHP_URL_HOST );
			if ( $url_host === $domain_host ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if post type is excluded
	 *
	 * @param string $post_type Post type to check.
	 * @return bool True if excluded, false otherwise.
	 */
	private function is_post_type_excluded( string $post_type ): bool {
		$excluded_post_types = $this->settings_manager->get_setting( 'exclude_post_types', [] );

		if ( empty( $excluded_post_types ) || ! is_array( $excluded_post_types ) ) {
			return false;
		}

		return in_array( $post_type, $excluded_post_types, true );
	}

	/**
	 * Check if URL is valid
	 *
	 * @param string $url URL to check.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_url( string $url ): bool {
		return wp_http_validate_url( $url ) !== false;
	}
}
