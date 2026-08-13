<?php
/**
 * CMS Media Importer
 *
 * @package WP_Genius
 * @subpackage Modules/CMSMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CMS_Media_Importer
 */
class CMS_Media_Importer {

	/**
	 * Check if URL is safe (not pointing to internal networks)
	 *
	 * @param string $url URL to check.
	 * @return bool True if safe.
	 */
	private function is_safe_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}

		$host = strtolower( $host );

		// Block localhost, private IPs, link-local, metadata endpoints.
		$blocked = array(
			'localhost',
			'127.0.0.1',
			'0.0.0.0',
			'::1',
			'169.254.169.254', // AWS metadata
			'metadata.google.internal', // GCP metadata
			'169.254.169.254.nip.io',
		);

		if ( in_array( $host, $blocked, true ) ) {
			return false;
		}

		// Block private IP ranges.
		$ip = gethostbyname( $host );
		if ( $ip !== $host ) {
			if ( preg_match( '/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $ip ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Import image from URL
	 *
	 * @param string $url Image URL.
	 * @param int    $post_id Parent post ID.
	 * @return int|WP_Error Attachment ID or error.
	 */
	public function import_image( $url, $post_id = 0 ) {
		if ( empty( $url ) ) {
			return new WP_Error( 'empty_url', __( 'Image URL is empty.', 'wp-genius' ) );
		}

		// Check if URL is valid.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', __( 'Invalid image URL.', 'wp-genius' ) );
		}

		// Block internal/private URLs.
		if ( ! $this->is_safe_url( $url ) ) {
			return new WP_Error( 'unsafe_url', __( 'URL points to an internal or private network.', 'wp-genius' ) );
		}

		// Check if image already exists by URL.
		$existing = $this->find_existing_attachment( $url );
		if ( $existing ) {
			return $existing;
		}

		// Download and import.
		$image_id = media_sideload_image( $url, $post_id, '', 'id' );

		if ( is_wp_error( $image_id ) ) {
			// Try alternative method.
			$image_id = $this->import_via_fopen( $url, $post_id );
		}

		return $image_id;
	}

	/**
	 * Find existing attachment by URL
	 *
	 * @param string $url Image URL.
	 * @return int|null Attachment ID or null.
	 */
	private function find_existing_attachment( $url ) {
		global $wpdb;

		$attachment = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
				$url
			)
		);

		return $attachment ? (int) $attachment : null;
	}

	/**
	 * Import image via file operations (fallback)
	 *
	 * @param string $url Image URL.
	 * @param int    $post_id Parent post ID.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function import_via_fopen( $url, $post_id ) {
		$tmp_file = download_url( $url );

		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		$file_array = array(
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp_file,
		);

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp_file );
			return $attachment_id;
		}

		return $attachment_id;
	}

	/**
	 * Import multiple images
	 *
	 * @param array  $urls Array of image URLs.
	 * @param int    $post_id Parent post ID.
	 * @return array Array of attachment IDs.
	 */
	public function import_images( $urls, $post_id = 0 ) {
		$attachment_ids = array();

		foreach ( $urls as $url ) {
			$attachment_id = $this->import_image( $url, $post_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				$attachment_ids[] = $attachment_id;
			}
		}

		return $attachment_ids;
	}

	/**
	 * Get attachment URL by ID
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false URL or false.
	 */
	public function get_attachment_url( $attachment_id ) {
		return wp_get_attachment_url( $attachment_id );
	}

	/**
	 * Delete imported attachments
	 *
	 * @param array $attachment_ids Array of attachment IDs to delete.
	 * @return void
	 */
	public function delete_attachments( $attachment_ids ) {
		foreach ( $attachment_ids as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}
