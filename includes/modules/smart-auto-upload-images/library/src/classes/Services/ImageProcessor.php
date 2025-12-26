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
		$images = $this->find_images_in_content( $content );

		if ( empty( $images ) ) {
			return false;
		}

		$processed_content = $content;
		$processed_count   = 0;

		foreach ( $images as $image ) {

			$result = $this->downloader->download_image( $image, $post_data );

			if ( is_wp_error( $result ) ) {
				$this->logger->error(
					'Failed to process image',
					[
						'url'   => $image['url'],
						'error' => $result->get_error_message(),
					]
				);
				continue;
			}

			$processed_content = $this->replace_image_url( $processed_content, $image, $result );
			++$processed_count;
		}

		if ( $processed_count > 0 ) {
			$this->logger->info(
				'Processed images for post',
				[
					'post_id'         => $post_data['ID'] ?? 0,
					'processed_count' => $processed_count,
				]
			);
			return $processed_content;
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
			$src    = $processor->get_attribute( 'src' );
			$srcset = $processor->get_attribute( 'srcset' );
			$alt    = $processor->get_attribute( 'alt' ) ?? '';
			$title  = $processor->get_attribute( 'title' ) ?? '';

			if ( $src && ! empty( trim( $src ) ) ) {
				$src = trim( $src );
				if ( ! in_array( $src, $seen_urls, true ) ) {
					$images[]    = [
						'url'      => $src,
						'alt'      => $alt,
						'title'    => $title,
						'full_tag' => $this->get_full_img_tag( $processor ),
					];
					$seen_urls[] = $src;
				}
			}

			if ( $srcset && ! empty( trim( $srcset ) ) ) {
				$srcset_urls = $this->extract_urls_from_srcset( $srcset );

				foreach ( $srcset_urls as $srcset_url ) {
					if ( ! in_array( $srcset_url, $seen_urls, true ) ) {
						$images[]    = [
							'url'      => $srcset_url,
							'alt'      => $alt,
							'title'    => $title,
							'full_tag' => $this->get_full_img_tag( $processor ),
						];
						$seen_urls[] = $srcset_url;
					}
				}
			}
		}

		return $images;
	}

	/**
	 * Extract URLs from srcset attribute
	 *
	 * @param string $srcset Srcset attribute value.
	 * @return array Array of URLs.
	 */
	private function extract_urls_from_srcset( string $srcset ): array {
		$urls    = [];
		$entries = preg_split( '/\s*,\s*/', trim( $srcset ) );

		foreach ( $entries as $entry ) {
			// Extract URL (everything before the first space).
			if ( preg_match( '/^([^\s]+)/', trim( $entry ), $matches ) ) {
				$url = $matches[1];
				// Handle protocol-relative URLs.
				if ( str_starts_with( $url, '//' ) ) {
					$url = 'https:' . $url;
				}
				$urls[] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Get the full img tag from the HTML processor
	 *
	 * @param \WP_HTML_Tag_Processor $processor HTML processor.
	 * @return string Full img tag.
	 */
	private function get_full_img_tag( \WP_HTML_Tag_Processor $processor ): string {
		$tag = '<img';

		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$value = $processor->get_attribute( $name );
			if ( null !== $value ) {
				$tag .= sprintf( ' %s="%s"', $name, esc_attr( $value ) );
			} else {
				$tag .= ' ' . $name;
			}
		}

		$tag .= '>';

		return $tag;
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

	/**
	 * Replace image URL in content
	 *
	 * @param string $content Content to modify.
	 * @param array  $image Original image data.
	 * @param array  $result Download result.
	 * @return string Modified content.
	 */
	private function replace_image_url( string $content, array $image, array $result ): string {
		$settings = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url = trim( $settings['base_url'], '/' );

		$new_url_parts = wp_parse_url( $result['url'] );
		$new_url       = $base_url . $new_url_parts['path'];

		$content = str_replace( $image['url'], $new_url, $content );

		if ( ! empty( $image['alt'] ) ) {
			$old_alt_pattern = 'alt=["\']' . preg_quote( $image['alt'], '/' ) . '["\']';
			$new_alt         = $result['alt_text'] ?? $image['alt'];
			$new_alt_pattern = 'alt="' . esc_attr( $new_alt ) . '"';

			$content = preg_replace( '/' . $old_alt_pattern . '/i', $new_alt_pattern, $content );
		}

		return $content;
	}
}
