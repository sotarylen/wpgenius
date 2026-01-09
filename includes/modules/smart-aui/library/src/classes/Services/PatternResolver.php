<?php
/**
 * Pattern Resolver Service
 *
 * @package SmartAutoUploadImages\Services
 */

namespace SmartAutoUploadImages\Services;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pattern Resolver Class
 */
class PatternResolver {

	/**
	 * Resolve pattern with actual values
	 *
	 * @param string $pattern Pattern to resolve.
	 * @param array  $data Data for replacement.
	 * @return string Resolved pattern.
	 */
	public function resolve_pattern( string $pattern, array $data ): string {
		// Find all pattern placeholders.
		preg_match_all( '/%[^%]*%/', $pattern, $matches );

		if ( empty( $matches[0] ) ) {
			return $pattern;
		}

		// Get replacement values.
		$replacements = $this->get_pattern_replacements( $data );

		// Replace patterns.
		foreach ( $matches[0] as $placeholder ) {
			if ( isset( $replacements[ $placeholder ] ) ) {
				$pattern = str_replace( $placeholder, $replacements[ $placeholder ], $pattern );
			}
		}

		return $pattern;
	}

	/**
	 * Get pattern replacements
	 *
	 * @param array $data Data for replacement.
	 * @return array Pattern replacements.
	 */
	private function get_pattern_replacements( array $data ): array {
		$post_date      = $data['post_date'] ?? current_time( 'mysql' );
		$post_timestamp = strtotime( $post_date );

		return [
			'%filename%'    => $data['filename'] ?? 'image',
			'%image_alt%'   => $data['image_alt'] ?? '',
			'%image_title%' => $data['image_title'] ?? '',
			'%post_title%'  => $data['post_title'] ?? '',
			'%post_name%'   => $data['post_name'] ?? '',

			'%year%'        => gmdate( 'Y' ),
			'%month%'       => gmdate( 'm' ),
			'%day%'         => gmdate( 'd' ),
			'%today_date%'  => gmdate( 'Y-m-d' ),
			'%today_day%'   => gmdate( 'd' ),

			'%post_date%'   => gmdate( 'Y-m-d', $post_timestamp ),
			'%post_year%'   => gmdate( 'Y', $post_timestamp ),
			'%post_month%'  => gmdate( 'm', $post_timestamp ),
			'%post_day%'    => gmdate( 'd', $post_timestamp ),

			'%url%'         => wp_parse_url( site_url(), PHP_URL_HOST ),
			'%random%'      => 'img_' . wp_generate_uuid4(),
			'%timestamp%'   => time(),

			'%date%'        => gmdate( 'Y-m-d' ),
		];
	}
}
