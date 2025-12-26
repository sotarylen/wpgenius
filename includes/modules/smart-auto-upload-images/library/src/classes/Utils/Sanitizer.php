<?php
/**
 * Sanitizer Utility
 *
 * @package SmartAutoUploadImages\Utils
 */

namespace SmartAutoUploadImages\Utils;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizer Class
 */
class Sanitizer {

	/**
	 * Sanitize URL
	 *
	 * @param string $url URL to sanitize.
	 * @return string Sanitized URL.
	 */
	public static function sanitize_url( string $url ): string {
		// Handle protocol-relative URLs.
		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		return esc_url_raw( $url );
	}

	/**
	 * Sanitize domain list
	 *
	 * @param string $domains Domain list to sanitize.
	 * @return string Sanitized domain list.
	 */
	public static function sanitize_domain_list( string $domains ): string {
		$lines           = explode( "\n", $domains );
		$sanitized_lines = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) ) {
				$sanitized_lines[] = self::sanitize_url( $line );
			}
		}

		return implode( "\n", $sanitized_lines );
	}

	/**
	 * Sanitize post type array
	 *
	 * @param array $post_types Post types to sanitize.
	 * @return array Sanitized post types.
	 */
	public static function sanitize_post_types( array $post_types ): array {
		$all_post_types = get_post_types();
		$sanitized      = [];

		foreach ( $post_types as $post_type ) {
			$post_type = sanitize_text_field( $post_type );
			if ( in_array( $post_type, $all_post_types, true ) ) {
				$sanitized[] = $post_type;
			}
		}

		return $sanitized;
	}
}
