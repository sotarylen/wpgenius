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
		// [FIX] Prevent download when trashing/deleting
		if ( isset( $_REQUEST['action'] ) ) {
			$action = $_REQUEST['action'];
			if ( in_array( $action, [ 'trash', 'delete', 'untrash' ], true ) ) {
				return $image_data;
			}
		}

		if ( isset( $post_data['post_status'] ) && 'trash' === $post_data['post_status'] ) {
			return $image_data;
		}
		
		// Double check DB status if ID exists
		if ( ! empty( $post_data['ID'] ) ) {
			$current_status = get_post_status( $post_data['ID'] );
			if ( 'trash' === $current_status ) {
				return $image_data;
			}
		}

		// Check if the URL has failed before
		if ( $this->failed_manager && $this->failed_manager->is_failed( $image_data['url'] ) ) {
			$this->logger->warning( 'Skipping previously failed image', [ 'url' => $image_data['url'] ] );
			return new WP_Error( 'previously_failed', 'This image has previously failed to download' );
		}


		// Check config: Should we skip duplicates?
		$skip_duplicates = $this->settings_manager->get_setting( 'skip_duplicates', true );

		if ( $skip_duplicates ) {
			// 1. Check Source URL Index (Strict Recalibration)
			// As requested: The path of the remote picture serves as the index.
			// If the index (Source URL) is consistent with an existing image, reuse it.
			// If inconsistent, we proceed to download (and save as new).
			$source_match_id = $this->find_existing_by_source( $image_data['url'] );
			
			if ( $source_match_id ) {
				$this->logger->info( 'Found existing image by source URL index', [ 'url' => $image_data['url'], 'id' => $source_match_id ] );
				$file_url = wp_get_attachment_url( $source_match_id );
				return [
					'file'          => get_attached_file( $source_match_id ),
					'url'           => $file_url,
					'type'          => get_post_mime_type( $source_match_id ),
					'attachment_id' => $source_match_id,
					'alt_text'      => get_post_meta( $source_match_id, '_wp_attachment_image_alt', true ),
				];
			}
		}

		$validation_result = $this->validator->validate_image_url( $image_data['url'], $post_data );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		$fetch_result = $this->fetch_image( $image_data['url'] );
		if ( is_wp_error( $fetch_result ) ) {
			// Don't add to failed list here - let the retry mechanism in module.php handle it
			// Only after max_retries are exhausted should it be marked as failed
			return $fetch_result;
		}

		$temp_file = $fetch_result['file'];

		if ( ! $this->validator->validate_image_file( $temp_file, $image_data ) ) {
			wp_delete_file( $temp_file );
			return new WP_Error( 'invalid_image', 'Downloaded file is not a valid image' );
		}

		$image_data = $this->prepare_image_data( $image_data, $post_data );

		// 2. Check File Content (SHA1) - Only if skipping duplicates is enabled
		if ( $skip_duplicates ) {
			$existing_image_result = $this->handle_existing_image( $image_data, $temp_file, $post_data );
			if ( $existing_image_result ) {
				wp_delete_file( $temp_file );
				$this->logger->info( 'Found existing image by content hash (SHA1)', [ 'url' => $image_data['url'], 'file' => $existing_image_result['file'] ] );
				return $existing_image_result;
			}
		}

		$save_result = $this->save_image_file( $temp_file, $image_data );
		
		// Clean up temp file after saving (save_image_file might have moved it, but let's be sure)
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}

		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		$attachment_id = $this->add_to_media_library( $save_result, $image_data, $post_data );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
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

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp_file = wp_tempnam( $url );

		$args = [
			'timeout'  => $this->settings_manager->get_setting( 'download_timeout', 30 ),
			'stream'   => true,
			'filename' => $temp_file,
			'headers'  => [
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
			],
		];

		$parsed_url = wp_parse_url( $url );
		if ( isset( $parsed_url['host'] ) ) {
			$args['headers']['Host'] = $parsed_url['host'];
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $temp_file );
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
			wp_delete_file( $temp_file );
			$error_msg = sprintf( 'HTTP %d: Failed to download image', $response_code );
			$this->logger->error( $error_msg, [ 'url' => $url ] );
			return new WP_Error( 'http_error', $error_msg );
		}

		return [
			'file' => $temp_file,
			'headers' => wp_remote_retrieve_headers( $response ),
		];
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
	private function save_image_file( string $temp_file, array $image_data ) {
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

		// Ensure the directory exists
		if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
			return new WP_Error( 'save_failed', 'Failed to create upload directory' );
		}

		// Move temp file to final destination
		if ( ! copy( $temp_file, $file_path ) ) {
			return new WP_Error( 'save_failed', 'Failed to save image file (copy failed)' );
		}
		
		unlink( $temp_file );

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

		// [MEMORY OPTIMIZATION] Skip thumbnail generation to prevent OOM on large images
		// Instead of calling wp_generate_attachment_metadata(), create minimal metadata manually
		// This is safe for MinIO/S3 workflows where thumbnails are generated on-demand
		
		// Get basic image dimensions without loading into memory
		$image_size = @getimagesize( $file_info['file'] );
		
		$metadata = [
			'width'  => $image_size[0] ?? 0,
			'height' => $image_size[1] ?? 0,
			'file'   => _wp_relative_upload_path( $file_info['file'] ),
		];
		
		// Update minimal metadata (no thumbnail sizes)
		wp_update_attachment_metadata( $attachment_id, $metadata );
		
		$this->logger->info(
			'Skipped thumbnail generation for memory efficiency',
			[
				'attachment_id' => $attachment_id,
				'file'          => $file_info['file'],
				'width'         => $metadata['width'],
				'height'        => $metadata['height'],
			]
		);

		if ( ! empty( $image_data['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image_data['alt_text'] );
		}

		// Store original source for breakpoint persistence
		update_post_meta( $attachment_id, '_w2p_original_source', $image_data['url'] );
		
		// [MEMORY CLEANUP] Explicitly free memory after processing
		unset( $metadata, $image_size, $attachment_data, $file_info );
		gc_collect_cycles();

		return $attachment_id;
	}

	/**
	 * Find existing attachment by original source URL
	 */
	private function find_existing_by_source( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		global $wpdb;
		// Check global index (_w2p_original_source)
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_w2p_original_source' AND meta_value = %s LIMIT 1",
			$url
		) );
		return $attachment_id ? intval( $attachment_id ) : false;
	}

	/**
	 * Handle existing image reuse - checks if image exists and processes it
	 *
	 * @param array  $image_data Image data.
	 * @param string $temp_file Temp file path.
	 * @param array  $post_data Post data.
	 * @return array|false Processed image data if exists, false otherwise.
	 */
	private function handle_existing_image( array $image_data, string $temp_file, array $post_data ) {
		$upload_dir = wp_upload_dir();
		$upload_url = $upload_dir['url'];

		$filename  = $image_data['filename'] . '.' . $image_data['extension'];
		$file_path = $upload_dir['path'] . '/' . $filename;

		// Check if image exists with same content
		$has_exist = file_exists( $file_path ) && sha1_file( $temp_file ) === sha1_file( $file_path );

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
