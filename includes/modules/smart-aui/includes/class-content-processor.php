<?php
/**
 * Smart AUI — Content Processor
 *
 * Handles post content: auto-setting the featured image and resolving local attachment IDs from URLs.
 * Split from module.php (refactored from the original God class).
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_SmartAUI_Content_Processor
 */
class W2P_SmartAUI_Content_Processor {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_SmartAUIModule
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param W2P_SmartAUIModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * Auto Set Featured Image
	 *
	 * Parses the first local image from the post content and sets it as the featured image.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post|null $post   Post object.
	 * @return void
	 */
	public function auto_set_featured_image( $post_id, $post = null ) {
		// [OPTIMIZATION] Immediate Bypassing for Deletion Actions
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- save_post hook; WP core has already verified the nonce.
		if ( isset( $_REQUEST['action'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Only reads the action to detect deletion operations.
			$action = $_REQUEST['action'];
			if ( in_array( $action, array( 'trash', 'delete', 'untrash' ), true ) ) {
				return;
			}
		}

		// Check if enabled (default true)
		$settings = $this->module->get_settings();
		$auto_set = isset( $settings['smart_aui_auto_set_featured_image'] ) ? $settings['smart_aui_auto_set_featured_image'] : true;

		if ( ! $auto_set ) {
			return;
		}

		// If no post object was passed, fetch it
		if ( ! $post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post ) {
			return;
		}

		// Check whether the post type supports featured images
		if ( ! post_type_supports( $post->post_type, 'thumbnail' ) ) {
			return;
		}

		// Check REST API settings: skip if this is a REST API request and REST API support is disabled
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && empty( $settings['smart_aui_process_images_on_rest_api'] ) ) {
			return;
		}

		// If the post is being moved to the trash, skip processing
		if ( isset( $post->post_status ) && 'trash' === $post->post_status ) {
			return;
		}

		// Use WP_HTML_Tag_Processor to precisely parse image tags
		$processor            = new \WP_HTML_Tag_Processor( $post->post_content );
		$current_thumbnail_id = get_post_thumbnail_id( $post_id );
		$found_intended_id    = false;

		while ( $processor->next_tag( 'img' ) ) {
			$attachment_id = false;
			$src           = $processor->get_attribute( 'src' );

			// 1. First try to extract the ID from the class attribute (wp-image-{id})
			$classes = $processor->get_attribute( 'class' ) ?? '';
			if ( preg_match( '/wp-image-(\d+)/', $classes, $class_matches ) ) {
				$attachment_id = intval( $class_matches[1] );
			}

			// 2. If not found, try to match the attachment by URL
			if ( ! $attachment_id && $src ) {
				$attachment_id = $this->get_attachment_id_from_url( $src );
			}

			if ( $attachment_id ) {
				$found_intended_id = $attachment_id;
				break; // Exit the loop after finding the first usable local image
			}
		}

		if ( $found_intended_id ) {
			// If the found image ID differs from the current featured image ID, update it
			if ( intval( $found_intended_id ) !== intval( $current_thumbnail_id ) ) {
				set_post_thumbnail( $post_id, $found_intended_id );
			}
		}
	}

	/**
	 * Get Attachment ID from URL
	 *
	 * Resolves a local attachment ID from a URL (handles OSS/custom base_url and scaled/resized suffixes).
	 *
	 * @param string $image_url Image URL.
	 * @return int|false
	 */
	public function get_attachment_id_from_url( $image_url ) {
		// Get the local domain (from site_url)
		$site_url     = site_url();
		$site_domain  = wp_parse_url( $site_url, PHP_URL_HOST );
		$image_domain = wp_parse_url( $image_url, PHP_URL_HOST );

		// Check whether it is on the same domain (supports OSS or other custom directories).
		// If there is no domain (relative path), it is also considered local.
		$is_local = false;
		if ( ! $image_domain || $image_domain === $site_domain ) {
			$is_local = true;
		} else {
			// Check whether it matches the configured base_url
			$settings    = $this->module->get_settings();
			$base_url    = ! empty( $settings['smart_aui_base_url'] ) ? $settings['smart_aui_base_url'] : $site_url;
			$base_domain = wp_parse_url( $base_url, PHP_URL_HOST );
			if ( $image_domain === $base_domain ) {
				$is_local = true;
			}
		}

		if ( ! $is_local ) {
			return false;
		}

		// Find the attachment ID by URL
		$attachment_id = attachment_url_to_postid( $image_url );

		if ( ! $attachment_id ) {
			// Try matching with the protocol stripped from the URL
			$clean_url     = preg_replace( '/^https?:/i', '', $image_url );
			$attachment_id = attachment_url_to_postid( $clean_url );
		}

		if ( ! $attachment_id ) {
			// Try looking up by filename (handles scaled or resized images)
			global $wpdb;
			$url_path = wp_parse_url( $image_url, PHP_URL_PATH );
			$filename = basename( $url_path ? $url_path : $image_url );

			// If pathinfo is available, extract the filename
			$path_info = pathinfo( $filename );
			if ( ! empty( $path_info['filename'] ) && ! empty( $path_info['extension'] ) ) {
				$base_name_only = preg_replace( '/(-\d+x\d+|-scaled)$/i', '', $path_info['filename'] );

				// Aliyun OSS may append style processing to the filename, e.g. !style
				$base_name_only = explode( '!', $base_name_only )[0];

				// [NEW] Try to get the path context (YYYY/MM)
				$path_prefix = '';
				if ( preg_match( '/(\d{4}\/\d{2})\//', $url_path ? $url_path : $image_url, $path_matches ) ) {
					$path_prefix = $path_matches[1] . '/';
				}

				// Precise search: exact match or with a -scaled suffix (avoids matching unrelated files like _gallery)
				$attachment_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND (meta_value = %s OR meta_value = %s) LIMIT 1",
						$path_prefix . $base_name_only . '.' . $path_info['extension'],
						$path_prefix . $base_name_only . '-scaled.' . $path_info['extension']
					)
				);

				// If still not found, and the filename contains -scaled, try searching without it
				if ( ! $attachment_id && strpos( $path_info['filename'], '-scaled' ) !== false ) {
					$unscaled_name = str_replace( '-scaled', '', $path_info['filename'] );
					$attachment_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
							$path_prefix . $unscaled_name . '.' . $path_info['extension']
						)
					);
				}
			}
		}

		return $attachment_id ? intval( $attachment_id ) : false;
	}
}
