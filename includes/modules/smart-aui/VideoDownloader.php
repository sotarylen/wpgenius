<?php
/**
 * Video Downloader Service
 *
 * Handles downloading remote videos and adding them to the WordPress media library.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Video Downloader Class
 */
class W2P_Video_Downloader {

	/**
	 * Allowed video MIME types
	 *
	 * @var array
	 */
	private $allowed_mime_types = array(
		'video/mp4'       => 'mp4',
		'video/webm'      => 'webm',
		'video/ogg'       => 'ogv',
		'video/quicktime' => 'mov',
		'video/x-msvideo' => 'avi',
		'video/x-ms-wmv'  => 'wmv',
	);

	/**
	 * Download video from URL and add to media library
	 *
	 * @param string $video_url The URL of the video to download.
	 * @param array  $post_data Post data context.
	 * @return array|WP_Error Result array with video info or WP_Error on failure.
	 */
	public function download_video( $video_url, $post_data = array() ) {
		// Validate URL
		if ( empty( $video_url ) ) {
			return new WP_Error( 'invalid_url', __( 'Video URL is empty.', 'wp-genius' ) );
		}

		// Check if URL is external
		if ( ! $this->is_external_url( $video_url ) ) {
			return new WP_Error( 'internal_url', __( 'Video is already hosted locally.', 'wp-genius' ) );
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Stream download video directly to temp file (prevents OOM)
		$temp_file = download_url( $video_url, 300 );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		// Get file info
		$file_size = @filesize( $temp_file );

		// Check file size (limit to 100MB by default)
		$max_size = 100 * 1024 * 1024; // 100MB
		if ( $file_size > $max_size ) {
			@unlink( $temp_file );
			return new WP_Error( 'file_too_large', __( 'Video file is too large (max 100MB).', 'wp-genius' ) );
		}

		// Get MIME type
		$mime_type = '';
		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $temp_file );
			finfo_close( $finfo );
		}
		if ( empty( $mime_type ) && function_exists( 'mime_content_type' ) ) {
			$mime_type = mime_content_type( $temp_file );
		}

		if ( ! isset( $this->allowed_mime_types[ $mime_type ] ) ) {
			@unlink( $temp_file );
			return new WP_Error( 'invalid_mime_type', __( 'Video file type is not supported.', 'wp-genius' ) );
		}

		// Generate filename
		$extension = $this->allowed_mime_types[ $mime_type ];
		$filename  = $this->generate_filename( $video_url, $post_data, $extension );

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $temp_file,
		);

		// Sideload file directly into uploads folder without memory overhead
		$upload = wp_handle_sideload(
			$file_array,
			array( 'test_form' => false )
		);

		if ( ! empty( $upload['error'] ) ) {
			@unlink( $temp_file );
			return new WP_Error( 'upload_error', $upload['error'] );
		}

		$post_id = ! empty( $post_data['ID'] ) ? absint( $post_data['ID'] ) : 0;

		// Create attachment
		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}

		// Generate attachment metadata
		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		if ( ! empty( $attach_data ) ) {
			wp_update_attachment_metadata( $attach_id, $attach_data );
		}

		// Get video URL
		$video_url_local = wp_get_attachment_url( $attach_id );

		return array(
			'attachment_id' => $attach_id,
			'url'           => $video_url_local,
			'file'          => $upload['file'],
			'mime_type'     => $mime_type,
			'filename'      => $filename,
		);
	}

	/**
	 * Check if URL is external
	 *
	 * @param string $url The URL to check.
	 * @return bool True if external, false if internal.
	 */
	private function is_external_url( $url ) {
		$site_url  = site_url();
		$site_host = wp_parse_url( $site_url, PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );

		// Relative URLs are considered internal
		if ( empty( $url_host ) ) {
			return false;
		}

		// Same domain is internal
		if ( $url_host === $site_host ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate filename for video
	 *
	 * @param string $video_url The original video URL.
	 * @param array  $post_data Post data context.
	 * @param string $extension File extension.
	 * @return string Generated filename.
	 */
	private function generate_filename( $video_url, $post_data, $extension ) {
		$settings = \SmartAutoUploadImages\Plugin::get_settings();
		$pattern  = isset( $settings['image_name_pattern'] ) ? $settings['image_name_pattern'] : '%filename%';

		// Get original filename
		$original_filename = basename( parse_url( $video_url, PHP_URL_PATH ) );
		$filename          = pathinfo( $original_filename, PATHINFO_FILENAME );
		$filename          = sanitize_file_name( $filename );

		// Apply pattern
		$replacements = array(
			'%filename%'   => $filename,
			'%post_title%' => isset( $post_data['post_title'] ) ? sanitize_file_name( $post_data['post_title'] ) : '',
			'%post_date%'  => isset( $post_data['post_date'] ) ? date( 'Y-m-d', strtotime( $post_data['post_date'] ) ) : date( 'Y-m-d' ),
			'%random%'     => substr( md5( uniqid() ), 0, 8 ),
		);

		$filename = str_replace( array_keys( $replacements ), $replacements, $pattern );
		$filename = sanitize_file_name( $filename );

		// Ensure filename is not empty
		if ( empty( $filename ) ) {
			$filename = 'video-' . date( 'YmdHis' );
		}

		return $filename . '.' . $extension;
	}
}
