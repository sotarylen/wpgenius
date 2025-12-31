<?php
/**
 * Image Downloader Service
 *
 * @package SmartAutoUploadImages\Services
 */

namespace SmartAutoUploadImages\Services;

use SmartAutoUploadImages\Admin\SettingsManager;
use SmartAutoUploadImages\Utils\Logger;
use SmartAutoUploadImages\Utils\Sanitizer;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Downloader Class
 */
class ImageDownloader {

	/**
	 * Settings manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Logger
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Image validator
	 *
	 * @var ImageValidator
	 */
	private ImageValidator $validator;

	/**
	 * Pattern resolver
	 *
	 * @var PatternResolver
	 */
	private PatternResolver $pattern_resolver;

	/**
	 * Failed image manager
	 *
	 * @var \SmartAutoUploadImages\Utils\FailedImagesManager|null
	 */
	private $failed_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = \SmartAutoUploadImages\get_container()->get( 'settings_manager' );
		$this->logger           = \SmartAutoUploadImages\get_container()->get( 'logger' );
		$this->validator        = new ImageValidator();
		$this->pattern_resolver = new PatternResolver();
		try {
			$this->failed_manager = \SmartAutoUploadImages\get_container()->get( 'failed_images_manager' );
		} catch ( \Exception $e ) {
			// Container entry might not be set in all contexts (e.g. standalone mode without module.php init)
			// We can gracefully handle this by checking for null usage locally or registering it if possible.
			// For now, logging and null is safe as we will check isset/empty before usage.
			$this->failed_manager = null;
		}
	}

	/**
	 * Download and save image
	 *
	 * @param array $image_data Image data.
	 * @param array $post_data Post data.
	 * @return array|WP_Error Download result or error.
	 */
	public function download_image( array $image_data, array $post_data ) {
		// Check if the URL has failed before
		if ( $this->failed_manager && $this->failed_manager->is_failed( $image_data['url'] ) ) {
			$this->logger->warning( 'Skipping previously failed image', [ 'url' => $image_data['url'] ] );
			return new WP_Error( 'previously_failed', 'This image has previously failed to download' );
		}

		// Check if we already have this image from a previous process
		$existing_id = $this->find_existing_by_source( $image_data['url'] );
		if ( $existing_id ) {
			$this->logger->info( 'Found existing image by source URL', [ 'url' => $image_data['url'], 'id' => $existing_id ] );
			$file_url = wp_get_attachment_url( $existing_id );
			return [
				'file'          => get_attached_file( $existing_id ),
				'url'           => $file_url,
				'type'          => get_post_mime_type( $existing_id ),
				'attachment_id' => $existing_id,
				'alt_text'      => get_post_meta( $existing_id, '_wp_attachment_image_alt', true ),
			];
		}

		$validation_result = $this->validator->validate_image_url( $image_data['url'], $post_data );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		$response = $this->fetch_image( $image_data['url'] );
		if ( is_wp_error( $response ) ) {
			// Don't add to failed list here - let the retry mechanism in module.php handle it
			// Only after max_retries are exhausted should it be marked as failed
			return $response;
		}

		if ( ! $this->validator->validate_image_content( $response['body'], $image_data ) ) {
			return new WP_Error( 'invalid_image', 'Downloaded file is not a valid image' );
		}

		$image_data = $this->prepare_image_data( $image_data, $post_data );

		$existing_image_result = $this->handle_existing_image( $image_data, $response['body'], $post_data );
		if ( $existing_image_result ) {
			return $existing_image_result;
		}

		$save_result = $this->save_image_file( $response['body'], $image_data );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		$attachment_id = $this->add_to_media_library( $save_result, $image_data, $post_data );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$resized_image = $this->handle_image_resize( $save_result );
		if ( $resized_image ) {
			$save_result = $resized_image;
		}

		// Ensure we use the actual attachment URL (handles WP-scaled images like -scaled.jpg)
		$actual_url = wp_get_attachment_url( $attachment_id );
		if ( $actual_url ) {
			$save_result['url'] = $actual_url;
		}

		$this->logger->info(
			'Image downloaded successfully',
			[
				'url'           => $image_data['url'],
				'final_url'     => $actual_url,
				'post_id'       => $post_data['ID'] ?? 0,
				'attachment_id' => $attachment_id,
			]
		);

		return [
			...$save_result,
			'attachment_id' => $attachment_id,
		];
	}

	/**
	 * Fetch image from URL
	 *
	 * @param string $url Image URL.
	 * @return array|WP_Error HTTP response or error.
	 */
	private function fetch_image( string $url ) {
		$url = Sanitizer::sanitize_url( $url );

		$args = [
			'timeout' => 5,
			'headers' => [],
		];

		$parsed_url = wp_parse_url( $url );
		if ( isset( $parsed_url['host'] ) ) {
			$args['headers']['host'] = $parsed_url['host'];
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'Failed to fetch image',
				[
					'url'   => $url,
					'error' => $response->get_error_message(),
				]
			);
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$error_msg = sprintf( 'HTTP %d: Failed to download image', $response_code );
			$this->logger->error( $error_msg, [ 'url' => $url ] );
			return new WP_Error( 'http_error', $error_msg );
		}

		return $response;
	}

	/**
	 * Prepare image data
	 *
	 * @param array $image_data Image data.
	 * @param array $post_data Post data.
	 * @return array Image data.
	 */
	private function prepare_image_data( array $image_data, array $post_data ): array {
		$url_parts         = pathinfo( wp_parse_url( $image_data['url'], PHP_URL_PATH ) );
		$original_filename = $url_parts['filename'] ?? 'image';
		$extension         = $url_parts['extension'] ?? 'jpg';

		$pattern_data = [
			'filename'    => $original_filename,
			'image_alt'   => $image_data['alt'] ?? '',
			'image_title' => $image_data['title'] ?? '',
			'post_title'  => $post_data['post_title'] ?? '',
			'post_id'     => $post_data['ID'] ?? 0,
			'post_name'   => $post_data['post_name'] ?? '',
			'post_date'   => $post_data['post_date'] ?? current_time( 'mysql' ),
		];

		$filename_pattern = $this->settings_manager->get_setting( 'image_name_pattern', '%filename%' );
		$filename         = $this->pattern_resolver->resolve_pattern( $filename_pattern, $pattern_data );
		$filename         = $filename ? $filename : 'image_' . time();

		$alt_pattern  = $this->settings_manager->get_setting( 'alt_text_pattern', '%image_alt%' );
		$resolved_alt = $this->pattern_resolver->resolve_pattern( $alt_pattern, $pattern_data );

		$filename     = sanitize_file_name( $filename );
		$resolved_alt = sanitize_text_field( $resolved_alt );

		$prepared_data = [
			'filename'  => $filename,
			'extension' => $extension,
			'alt_text'  => $resolved_alt,
			'url'       => $image_data['url'],
		];

		return apply_filters( 'smart_aui_prepared_image_data', $prepared_data, $image_data, $post_data );
	}

	/**
	 * Save image file to uploads directory
	 *
	 * @param string $file_content File content.
	 * @param array  $image_data Image data.
	 * @return array|WP_Error File info or error.
	 */
	private function save_image_file( string $file_content, array $image_data ) {
		$upload_dir = wp_upload_dir();

		$filename  = $image_data['filename'] . '.' . $image_data['extension'];
		$file_path = $upload_dir['path'] . '/' . $filename;
		$file_url  = $upload_dir['url'] . '/' . $filename;

		// Handle filename conflicts by appending counter.
		$counter = 1;
		while ( file_exists( $file_path ) ) {
			$filename  = $image_data['filename'] . '_' . $counter . '.' . $image_data['extension'];
			$file_path = $upload_dir['path'] . '/' . $filename;
			$file_url  = $upload_dir['url'] . '/' . $filename;
			++$counter;
		}

		$saved = file_put_contents( $file_path, $file_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $saved ) {
			return new WP_Error( 'save_failed', 'Failed to save image file' );
		}

		$file_type = wp_check_filetype( $filename );

		return [
			'file' => $file_path,
			'url'  => $file_url,
			'type' => $file_type['type'],
		];
	}

	/**
	 * Add image to media library
	 *
	 * @param array $file_info File information.
	 * @param array $image_data Image data.
	 * @param array $post_data Post data.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function add_to_media_library( array $file_info, array $image_data, array $post_data ) {
		$attachment_data = [
			'guid'           => $file_info['url'],
			'post_mime_type' => $file_info['type'],
			'post_title'     => ! empty( $image_data['alt_text'] ) ? $image_data['alt_text'] : $image_data['filename'],
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment_data, $file_info['file'], $post_data['ID'] ?? 0 );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_info['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		if ( ! empty( $image_data['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image_data['alt_text'] );
		}

		// Store original source for breakpoint persistence
		update_post_meta( $attachment_id, '_w2p_original_source', $image_data['url'] );

		return $attachment_id;
	}

	/**
	 * Find existing attachment by original source URL
	 */
	private function find_existing_by_source( $url ) {
		global $wpdb;
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_w2p_original_source' AND meta_value = %s LIMIT 1",
			$url
		) );
		return $attachment_id ? intval( $attachment_id ) : false;
	}

	/**
	 * Handle image resizing if needed
	 *
	 * @param array $file_info File information.
	 * @return array|false Resized file info or false.
	 */
	private function handle_image_resize( array $file_info ) {
		$max_width  = $this->settings_manager->get_setting( 'max_width', 0 );
		$max_height = $this->settings_manager->get_setting( 'max_height', 0 );

		if ( 0 === $max_width && 0 === $max_height ) {
			return false;
		}

		if ( ! function_exists( 'image_make_intermediate_size' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$resized = image_make_intermediate_size( $file_info['file'], $max_width, $max_height );

		if ( ! $resized ) {
			return false;
		}

		$upload_dir        = wp_upload_dir();
		$file_info['file'] = $upload_dir['path'] . '/' . $resized['file'];
		$file_info['url']  = $upload_dir['url'] . '/' . $resized['file'];

		return $file_info;
	}

	/**
	 * Handle existing image reuse - checks if image exists and processes it
	 *
	 * @param array  $image_data Image data.
	 * @param string $file_content File content.
	 * @param array  $post_data Post data.
	 * @return array|false Processed image data if exists, false otherwise.
	 */
	private function handle_existing_image( array $image_data, string $file_content, array $post_data ) {
		$upload_dir = wp_upload_dir();
		$upload_url = $upload_dir['url'];

		$filename  = $image_data['filename'] . '.' . $image_data['extension'];
		$file_path = $upload_dir['path'] . '/' . $filename;

		// Check if image exists with same content
		$has_exist = file_exists( $file_path ) && sha1( $file_content ) === sha1_file( $file_path );

		if ( ! $has_exist ) {
			return false;
		}

		// Image exists, prepare data
		$file_url      = $upload_url . '/' . $filename;
		$attachment_id = attachment_url_to_postid( $file_url );

		$existing_image = [
			'file'          => $file_path,
			'url'           => $file_url,
			'type'          => wp_check_filetype( $filename )['type'],
			'attachment_id' => $attachment_id ? $attachment_id : 0,
		];

		// Create attachment record if missing
		if ( 0 === $attachment_id ) {
			$attachment_id = $this->add_to_media_library( $existing_image, $image_data, $post_data );
			if ( is_wp_error( $attachment_id ) ) {
				$attachment_id = 0;
			}
		}

		return [
			'file'          => $file_path,
			'url'           => $file_url,
			'type'          => $existing_image['type'],
			'attachment_id' => $attachment_id,
			'alt_text'      => $image_data['alt_text'] ?? '',
		];
	}
}
