<?php
/**
 * Main Image Processor Service
 *
 * @package SmartAutoUploadImages\Services
 */

namespace SmartAutoUploadImages\Services;

use SmartAutoUploadImages\Utils\Logger;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Processor Class
 */
class ImageProcessor {

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Image downloader
	 *
	 * @var ImageDownloader
	 */
	private ImageDownloader $downloader;

	/**
	 * Image validator
	 *
	 * @var ImageValidator
	 */
	private ImageValidator $validator;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger     = new Logger();
		$this->downloader = new ImageDownloader();
		$this->validator  = new ImageValidator();
	}

	/**
	 * Process post content for images
	 *
	 * @param string $content Post content.
	 * @param array  $post_data Post data.
	 * @return string|false Processed content or false on no changes.
	 */
	public function process_post_content( string $content, array $post_data ) {
		$processed_content = $this->decode_json_content( $content );
		$processor         = new \WP_HTML_Tag_Processor( $processed_content );
		$processed_count   = 0;
		$changes_made      = false;

		while ( $processor->next_tag( 'img' ) ) {
			$src    = $processor->get_attribute( 'src' );
			$srcset = $processor->get_attribute( 'srcset' );
			$sizes  = $processor->get_attribute( 'sizes' );
			$fallback = $processor->get_attribute( 'data-smush-webp-fallback' );

			if ( $src && ! empty( trim( $src ) ) ) {
				$image_data = [
					'url'   => trim( $src ),
					'alt'   => $processor->get_attribute( 'alt' ) ?? '',
					'title' => $processor->get_attribute( 'title' ) ?? '',
				];

				$result = $this->downloader->download_image( $image_data, $post_data );

				if ( ! is_wp_error( $result ) ) {
					$settings = \SmartAutoUploadImages\Plugin::get_settings();
					$base_url = trim( $settings['base_url'], '/' );
					$new_url_parts = wp_parse_url( $result['url'] );
					$new_url       = $base_url . $new_url_parts['path'];

					$processor->set_attribute( 'src', $new_url );
					
					// Add WordPress standard classes for better identification
					if ( ! empty( $result['attachment_id'] ) ) {
						$existing_classes = $processor->get_attribute( 'class' ) ?? '';
						$new_classes = 'wp-image-' . $result['attachment_id'] . ' size-full';
						if ( ! empty( $existing_classes ) ) {
							// Avoid duplicates
							if ( strpos( $existing_classes, 'wp-image-' ) === false ) {
								$new_classes = trim( $existing_classes ) . ' ' . $new_classes;
							} else {
								$new_classes = preg_replace( '/wp-image-\d+/', 'wp-image-' . $result['attachment_id'], $existing_classes );
							}
						}
						$processor->set_attribute( 'class', $new_classes );
					}

					if ( ! empty( $result['alt_text'] ) ) {
						$processor->set_attribute( 'alt', $result['alt_text'] );
					}

					$processed_count++;
					$changes_made = true;
				}
			}

			// Clean up redundant/external multi-size attributes
			if ( $srcset ) {
				$processor->remove_attribute( 'srcset' );
				$changes_made = true;
			}
			if ( $sizes ) {
				$processor->remove_attribute( 'sizes' );
				$changes_made = true;
			}
			if ( $fallback ) {
				$processor->remove_attribute( 'data-smush-webp-fallback' );
				$changes_made = true;
			}
		}

		if ( $changes_made ) {
			$this->logger->info(
				'Processed images for post',
				[
					'post_id'         => $post_data['ID'] ?? 0,
					'processed_count' => $processed_count,
				]
			);
			return $processor->get_updated_html();
		}

		return false;
	}

	/**
	 * Find all images in content using WP_HTML_Tag_Processor
	 *
	 * @param string $content Content to search.
	 * @return array Array of image data.
	 */
	private function find_images_in_content( string $content ): array {
		$images    = [];
		$seen_urls = [];

		$processed_content = $this->decode_json_content( $content );

		$processor = new \WP_HTML_Tag_Processor( $processed_content );

		while ( $processor->next_tag( 'img' ) ) {
			$src = $processor->get_attribute( 'src' );

			if ( $src && ! empty( trim( $src ) ) ) {
				$src = trim( $src );
				if ( ! in_array( $src, $seen_urls, true ) ) {
					$images[]    = [
						'url'      => $src,
						'alt'      => $processor->get_attribute( 'alt' ) ?? '',
						'title'    => $processor->get_attribute( 'title' ) ?? '',
						'full_tag' => '', // No longer needed as we use processor to replace
					];
					$seen_urls[] = $src;
				}
			}
		}

		return $images;
	}

	/**
	 * Decode JSON-encoded content if necessary
	 *
	 * @param string $content Content that might be JSON-encoded.
	 * @return string Decoded content.
	 */
	private function decode_json_content( string $content ): string {
		// Check if content looks like JSON-encoded HTML.
		if ( $this->is_json_encoded_html( $content ) ) {
			$decoded = json_decode( $content );
			if ( json_last_error() === JSON_ERROR_NONE && is_string( $decoded ) ) {
				$this->logger->debug( 'Decoded JSON-encoded content' );
				return $decoded;
			}
		}

		// Also handle escaped quotes in HTML content.
		if ( str_contains( $content, '\\"' ) || str_contains( $content, "\\'" ) ) {
			$this->logger->debug( 'Unescaping quotes in content' );
			return stripslashes( $content );
		}

		return $content;
	}

	/**
	 * Check if content appears to be JSON-encoded HTML
	 *
	 * @param string $content Content to check.
	 * @return bool Whether content appears to be JSON-encoded HTML.
	 */
	private function is_json_encoded_html( string $content ): bool {
		if ( empty( $content ) ) {
			return false;
		}

		// Quick checks for JSON-encoded HTML patterns.
		$patterns = [
			'/^".*<.*>.*"$/',           // Quoted string containing HTML tags.
			'/\\"[^"]*<[^>]+>[^"]*\\"/', // Escaped quotes around HTML.
			'/\\"https?:\\//',          // Escaped quotes around URLs.
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, trim( $content ) ) ) {
				return true;
			}
		}

		return false;
	}
}
