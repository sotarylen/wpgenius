<?php
/**
 * Enhanced Image Processor Wrapper with Better Error Handling
 *
 * Fixes:
 * 1. Better URL replacement with encoding handling
 * 2. Proper status for existing images
 * 3. Timeout and memory management
 * 4. Image attachment association (Post ID fix)
 * 5. Robust retry mechanism
 */

namespace SmartAutoUploadImages\Services;

use SmartAutoUploadImages\Utils\Logger;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhanced Image Processor Class
 */
class ImageProcessorExtended {

	/**
	 * Original ImageProcessor instance
	 *
	 * @var \SmartAutoUploadImages\Services\ImageProcessor
	 */
	private $processor;

	/**
	 * Image validator
	 *
	 * @var ImageValidator
	 */
	private $validator;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->processor = new ImageProcessor();

		@ini_set( 'max_execution_time', '300' ); // 5 minutes
		@ini_set( 'memory_limit', '512M' );

		$this->validator = new ImageValidator();
	}

	/**
	 * Process post content for images with progress tracking
	 *
	 * @param string $content Post content.
	 * @param array  $post_data Post data.
	 * @param string $target_url Optional. Only process this specific URL.
	 * @return string|false Processed content or false on no changes.
	 */
	public function process_post_content( string $content, array $post_data, string $target_url = '' ) {
		// [FIX 1] Ensure the Post ID exists to fix unattached attachments
		if ( empty( $post_data['ID'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- save_post content processing hook; WP core has already verified the nonce.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Same as above.
			if ( isset( $_POST['post_ID'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- save_post hook; WP core has already verified the nonce.
				$post_data['ID'] = intval( $_POST['post_ID'] );
			} elseif ( isset( $GLOBALS['post']->ID ) ) {
				$post_data['ID'] = $GLOBALS['post']->ID;
			}
		}

		// Use reflection to access private methods
		$reflection         = new \ReflectionClass( $this->processor );
		$find_images_method = $reflection->getMethod( 'find_images_in_content' );
		$find_images_method->setAccessible( true );

		$images = $find_images_method->invoke( $this->processor, $content );

		// [FIX 6] Single Image Processing Support
		if ( ! empty( $target_url ) ) {
			$images = array_values(
				array_filter(
					$images,
					function ( $img ) use ( $target_url ) {
						return $img['url'] === $target_url;
					}
				)
			);
		}

		if ( empty( $images ) ) {
			return false;
		}

		// Fire action before processing
		do_action( 'smart_aui_before_process_images', $images, $post_data );

		// Get private properties
		$downloader_property = $reflection->getProperty( 'downloader' );
		$downloader_property->setAccessible( true );
		$downloader = $downloader_property->getValue( $this->processor );

		$logger_property = $reflection->getProperty( 'logger' );
		$logger_property->setAccessible( true );
		$logger = $logger_property->getValue( $this->processor );

		$processed_content = $content;
		$processed_count   = 0;
		$success_count     = 0;
		$failed_count      = 0;
		$max_retries       = 3; // Maximum retry count
		$url_to_id_mapping = array(); // [NEW] Used to inject class in the final pass

		// Get local domain configuration
		$smart_aui_settings = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url           = ! empty( $smart_aui_settings['base_url'] ) ? $smart_aui_settings['base_url'] : site_url();
		$site_domain        = parse_url( $base_url, PHP_URL_HOST );
		$site_url           = site_url();

		foreach ( $images as $index => $image ) {
			// [FIX 3] Check if it is already a local image; skip if so
			if ( strpos( $image['url'], $base_url ) === 0 || strpos( $image['url'], $site_url ) === 0 ) {
				$logger->info( 'Skipped local image', array( 'url' => $image['url'] ) );
				// Mark as success (skipped), fire the event so the progress bar updates
				do_action( 'smart_aui_image_processed', $image, array( 'skipped' => true ), $index );
				++$success_count; // Count as success
				++$processed_count; // Count into the total processed
				continue;
			}

			// [FIX 7] Check whether the domain is excluded or the URL is an internal link
			$validation = $this->validator->validate_image_url( $image['url'], $post_data );
			if ( is_wp_error( $validation ) ) {
				$error_code = $validation->get_error_code();
				if ( 'excluded_domain' === $error_code || 'internal_url' === $error_code || 'invalid_url' === $error_code ) {
					$logger->info(
						'Skipped image validation',
						array(
							'url'    => $image['url'],
							'reason' => $error_code,
						)
					);
					do_action(
						'smart_aui_image_processed',
						$image,
						array(
							'skipped' => true,
							'reason'  => $error_code,
						),
						$index
					);
					++$success_count;
					++$processed_count;
					continue;
				}
			}

			// Check whether the domain matches
			$image_host = parse_url( $image['url'], PHP_URL_HOST );
			if ( $image_host === $site_domain ) {
				$logger->info( 'Skipped image with local domain', array( 'url' => $image['url'] ) );
				do_action( 'smart_aui_image_processed', $image, array( 'skipped' => true ), $index );
				++$success_count;
				++$processed_count;
				continue;
			}

			// Flush the output buffer every 5 images to prevent timeouts
			if ( $index % 5 === 0 ) {
				if ( function_exists( 'wp_ob_end_flush_all' ) ) {
					wp_ob_end_flush_all();
				}
				flush();
			}

			// [FIX 2] Built-in retry mechanism
			$retry_count = 0;
			$result      = null;

			while ( $retry_count < $max_retries ) {
				$result = $downloader->download_image( $image, $post_data );

				if ( ! is_wp_error( $result ) ) {
					// Success, break out of the retry loop
					break;
				}

				// If the image has previously failed, don't retry, just skip it according to objective
				if ( $result->get_error_code() === 'previously_failed' ) {
					break;
				}

				// Failure: increment the retry count and wait
				++$retry_count;
				if ( $retry_count < $max_retries ) {
					// Simple exponential backoff: 1s, 2s
					sleep( $retry_count );
				}
			}

			if ( is_wp_error( $result ) ) {
				if ( $result->get_error_code() === 'previously_failed' ) {
					$logger->info( 'Skipped previously failed image', array( 'url' => $image['url'] ) );
					do_action( 'smart_aui_image_processed', $image, array( 'skipped' => true ), $index );
					++$success_count;
					++$processed_count;
					continue;
				}

				$logger->error(
					'Failed to process image after retries',
					array(
						'url'     => $image['url'],
						'error'   => $result->get_error_message(),
						'retries' => $retry_count,
					)
				);

				// Failed images also count as success; keep the original URL, no replacement needed
				++$success_count;
				++$processed_count;

				// Fire action for failed image (but marked as skipped)
				do_action(
					'smart_aui_image_processed',
					$image,
					array(
						'skipped' => true,
						'error'   => $result->get_error_message(),
					),
					$index
				);
				continue;
			}

			// Use the improved URL replacement method
			$processed_content = $this->replace_image_url_enhanced( $processed_content, $image, $result );
			++$processed_count;
			++$success_count;

			// Fire action for successful image (including pre-existing images)
			do_action( 'smart_aui_image_processed', $image, $result, $index );

			// [NEW] Record the ID mapping
			if ( ! empty( $result['attachment_id'] ) ) {
				$new_url_parts                       = wp_parse_url( $result['url'] );
				$final_new_url                       = $base_url . $new_url_parts['path'];
				$url_to_id_mapping[ $final_new_url ] = $result['attachment_id'];
			}
		}

		// [NEW] Final pass to clean up attributes using WP_HTML_Tag_Processor
		$final_processor = new \WP_HTML_Tag_Processor( $processed_content );
		while ( $final_processor->next_tag( 'img' ) ) {
			// If the image points to our base_url, we should definitely clean up external attributes
			$img_src = $final_processor->get_attribute( 'src' );
			if ( $img_src && strpos( $img_src, $base_url ) === 0 ) {
				$final_processor->remove_attribute( 'srcset' );
				$final_processor->remove_attribute( 'sizes' );
				$final_processor->remove_attribute( 'data-smush-webp-fallback' );

				// [NEW] Inject the WordPress standard ID class name
				if ( ! empty( $url_to_id_mapping[ $img_src ] ) ) {
					$attachment_id    = $url_to_id_mapping[ $img_src ];
					$existing_classes = $final_processor->get_attribute( 'class' ) ?? '';
					$new_classes      = 'wp-image-' . $attachment_id . ' size-full';

					if ( ! empty( $existing_classes ) ) {
						if ( strpos( $existing_classes, 'wp-image-' ) === false ) {
							$new_classes = trim( $existing_classes ) . ' ' . $new_classes;
						} else {
							$new_classes = preg_replace( '/wp-image-\d+/', 'wp-image-' . $attachment_id, $existing_classes );
						}
					}
					$final_processor->set_attribute( 'class', $new_classes );
				}
			}
		}
		$processed_content = $final_processor->get_updated_html();

		// Fire action after processing
		do_action( 'smart_aui_after_process_images', $processed_count, $post_data );

		if ( $processed_count > 0 ) {
			$logger->info(
				'Processed images for post',
				array(
					'post_id'         => $post_data['ID'] ?? 0,
					'processed_count' => $processed_count,
					'success_count'   => $success_count,
					'failed_count'    => $failed_count,
				)
			);
			return $processed_content;
		}

		return false;
	}

	/**
	 * Enhanced URL replacement with better encoding handling
	 *
	 * @param string $content Content to modify.
	 * @param array  $image Original image data.
	 * @param array  $result Download result.
	 * @return string Modified content.
	 */
	private function replace_image_url_enhanced( string $content, array $image, array $result ): string {
		$settings = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url = trim( $settings['base_url'], '/' );

		$new_url_parts = wp_parse_url( $result['url'] );
		$new_url       = $base_url . $new_url_parts['path'];

		$old_url = $image['url'];

		// Try multiple URL variants for replacement to handle encoding issues
		$url_variants = array(
			$old_url,
			html_entity_decode( $old_url ),
			urldecode( $old_url ),
			str_replace( '&amp;', '&', $old_url ),
			str_replace( '&', '&amp;', $old_url ),
		);

		// Deduplicate
		$url_variants = array_unique( $url_variants );

		// Replace all variants
		foreach ( $url_variants as $variant ) {
			if ( strpos( $content, $variant ) !== false ) {
				$content = str_replace( $variant, $new_url, $content );
			}
		}

		// Handle Alt text
		if ( ! empty( $image['alt'] ) && ! empty( $result['alt_text'] ) ) {
			$old_alt_pattern = 'alt=["\']' . preg_quote( $image['alt'], '/' ) . '["\']';
			$new_alt         = $result['alt_text'];
			$new_alt_pattern = 'alt="' . esc_attr( $new_alt ) . '"';

			$content = preg_replace( '/' . $old_alt_pattern . '/i', $new_alt_pattern, $content );
		}

		return $content;
	}
}
