<?php
/**
 * Lightbox Handler Class
 * 
 * Backend handler for Lightbox image viewer functionality.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightbox Handler
 */
class WPG_Lightbox_Handler {

	/**
	 * Constructor
	 */
	public function __construct() {
		// No specific backend hooks needed for now
		// All functionality is handled via JavaScript and AJAX in the main module class
	}

	/**
	 * Get images from post content
	 * 
	 * @param int $post_id Post ID
	 * @return array Array of image data
	 */
	public static function get_post_images( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}

		$images = [];
		$content = $post->post_content;

		// Parse HTML to extract images
		if ( preg_match_all( '/<img[^>]+>/i', $content, $matches ) ) {
			foreach ( $matches[0] as $img_tag ) {
				// Extract src
				if ( preg_match( '/src=["\']([^"\']+)["\']/i', $img_tag, $src_match ) ) {
					$src = $src_match[1];
					
					// Extract alt text
					$alt = '';
					if ( preg_match( '/alt=["\']([^"\']+)["\']/i', $img_tag, $alt_match ) ) {
						$alt = $alt_match[1];
					}
					
					// Get attachment ID if it's a WordPress media
					$attachment_id = self::get_attachment_id_by_url( $src );
					
					$images[] = [
						'src'           => $src,
						'alt'           => $alt,
						'attachment_id' => $attachment_id,
					];
				}
			}
		}

		return $images;
	}

	/**
	 * Get attachment ID by URL
	 * 
	 * @param string $url Image URL
	 * @return int|false Attachment ID or false
	 */
	private static function get_attachment_id_by_url( $url ) {
		global $wpdb;

		// Remove query strings and fragments
		$url = preg_replace( '/[?#].*/', '', $url );

		// Try to find the attachment
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1",
				$url
			)
		);

		if ( $attachment_id ) {
			return absint( $attachment_id );
		}

		// Try alternative method: search by URL in post meta
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( basename( $url ) ) . '%'
			)
		);

		return $attachment_id ? absint( $attachment_id ) : false;
	}
}
