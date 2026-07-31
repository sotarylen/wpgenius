<?php
/**
 * Media Engine URL Rewrite Service
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineUrlRewriteService {
	private $logger;

	public function __construct() {
		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-conversion-logger.php';
		}
		$this->logger = new MediaEngineConversionLogger();
	}

	public function rewrite_content( $attachment_id, $old_url, $new_url ) {
		global $wpdb;

		$post_parent = wp_get_post_parent_id( $attachment_id );
		if ( class_exists( 'W2P_Logger' ) ) {
			W2P_Logger::debug( sprintf( 'rewrite_content start: attachment=%d, old=%s, new=%s, post_parent=%d', $attachment_id, $old_url, $new_url, (int) $post_parent ), 'media-engine' );
		}
		if ( ! $post_parent ) {
			// Update GUID only if unattached
			$wpdb->update( $wpdb->posts, [ 'guid' => $new_url ], [ 'ID' => $attachment_id ] );
			if ( class_exists( 'W2P_Logger' ) ) {
				W2P_Logger::warning( sprintf( 'rewrite_content skipped — attachment %d has no post_parent', $attachment_id ), 'media-engine' );
			}
			return [ 'success' => true, 'replaced' => false, 'reason' => 'no_parent' ];
		}

		$post = get_post( $post_parent );
		if ( ! $post ) {
			if ( class_exists( 'W2P_Logger' ) ) {
				W2P_Logger::warning( sprintf( 'rewrite_content skipped — parent post %d not found for attachment %d', $post_parent, $attachment_id ), 'media-engine' );
			}
			return [ 'success' => false, 'error' => 'Parent post not found' ];
		}

		// Parse URLs to get paths
		$old_path_info = pathinfo( $old_url );
		$new_path_info = pathinfo( $new_url );
		
		$old_dir = $old_path_info['dirname'];
		$new_dir = $new_path_info['dirname'];
		
		// Convert to relative paths with trailing slash
		// We use wp_make_link_relative to get /wp-content/uploads/202x/xx
		$rel_old_dir = trailingslashit( dirname( wp_make_link_relative( $old_url ) ) );
		$rel_new_dir = trailingslashit( dirname( wp_make_link_relative( $new_url ) ) );
		
		$filename_no_ext = $old_path_info['filename']; // filename without ext
		$old_ext = $old_path_info['extension'];
		$new_ext = $new_path_info['extension'];
		
		// Regex to match:
		// 1. Optional Domain (http://... or https://...) - Capture Group 1
		// 2. Old Directory Path - Exact Match
		// 3. Filename - Exact Match
		// 4. Suffix (e.g. -300x300, -scaled) - Capture Group 2
		// 5. Old Extension - Exact Match
		
		// Handle potential protocol variations in domain matching
		// We lazily match up to the relative directory start
		$domain_pattern = '(https?:\/\/[^\/]+)?'; 
		
		$pattern = '/' . 
			$domain_pattern . 
			preg_quote( $rel_old_dir, '/' ) . 
			preg_quote( $filename_no_ext, '/' ) . 
			'((?:-\d+x\d+)?(?:-scaled)?)' . 
			'\.' . preg_quote( $old_ext, '/' ) . 
			'/i';

		$content = $post->post_content;
		$count = 0;
		
		$new_content = preg_replace(
			$pattern, 
			'$1' . $rel_new_dir . $filename_no_ext . '$2.' . $new_ext, // $1 is domain (if any), $2 is suffix
			$content,
			-1,
			$count
		);

		if ( $count > 0 && $new_content !== $content ) {
			$wpdb->update( $wpdb->posts, [ 'post_content' => $new_content ], [ 'ID' => $post_parent ] );
			$wpdb->update( $wpdb->posts, [ 'guid' => $new_url ], [ 'ID' => $attachment_id ] );
			if ( class_exists( 'W2P_Logger' ) ) {
				W2P_Logger::debug( sprintf( 'rewrite_content replaced %d occurrence(s) in post %d for attachment %d', $count, $post_parent, $attachment_id ), 'media-engine' );
			}
			return [ 'success' => true, 'replaced' => true, 'count' => $count ];
		}

		if ( class_exists( 'W2P_Logger' ) ) {
			W2P_Logger::warning( sprintf( 'rewrite_content pattern matched but no replacement: attachment=%d, post=%d, old=%s, new=%s', $attachment_id, $post_parent, $old_url, $new_url ), 'media-engine' );
		}
		return [ 'success' => true, 'replaced' => false ];
	}
}
