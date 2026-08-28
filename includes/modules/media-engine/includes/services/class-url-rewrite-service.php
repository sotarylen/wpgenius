<?php
/**
 * Media Engine URL Rewrite Service
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Media_Url_Rewrite_Service {
	private $logger;

	public function __construct() {
		if ( ! class_exists( 'W2P_Media_Conversion_Logger' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-logger-service.php';
		}
		if ( class_exists( 'W2P_Media_Conversion_Logger' ) ) {
			$this->logger = new W2P_Media_Conversion_Logger();
		} elseif ( class_exists( 'MediaEngineConversionLogger' ) ) {
			$this->logger = new MediaEngineConversionLogger();
		}
	}

	public function rewrite_content( $attachment_id, $old_url, $new_url ) {
		global $wpdb;

		// ── Pre-compute regex parts ──────────────────────────────────────────
		$rel_old  = wp_make_link_relative( $old_url );
		$rel_new  = wp_make_link_relative( $new_url );
		$old_info = pathinfo( $rel_old );
		$new_info = pathinfo( $rel_new );

		$old_dir  = trailingslashit( isset( $old_info['dirname'] ) ? $old_info['dirname'] : '' );
		$new_dir  = trailingslashit( isset( $new_info['dirname'] ) ? $new_info['dirname'] : '' );
		$filename = isset( $old_info['filename'] ) ? $old_info['filename'] : '';
		$old_ext  = isset( $old_info['extension'] ) ? $old_info['extension'] : '';
		$new_ext  = isset( $new_info['extension'] ) ? $new_info['extension'] : '';

		if ( empty( $filename ) || empty( $old_ext ) || empty( $new_ext ) ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'invalid_url_structure',
			);
		}

		$pattern = '/'
			. '(https?:\/\/[^\/]+)?'
			. preg_quote( $old_dir, '/' )
			. preg_quote( $filename, '/' )
			. '((?:-\d+x\d+)?(?:-scaled)?)'
			. '\.' . preg_quote( $old_ext, '/' )
			. '/i';

		$replacement = '$1' . $new_dir . $filename . '$2.' . $new_ext;

		// ── Find and update the parent post ──────────────────────────────────
		$post_parent = wp_get_post_parent_id( $attachment_id );

		if ( ! $post_parent ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_parent',
			);
		}

		$post = get_post( $post_parent );
		if ( ! $post ) {
			return array(
				'success'     => false,
				'error'       => 'Parent post not found',
				'post_parent' => $post_parent,
			);
		}

		$count       = 0;
		$new_content = preg_replace( $pattern, $replacement, $post->post_content, -1, $count );

		if ( $count === 0 || $new_content === $post->post_content ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_match',
			);
		}

		// ── Persist the update and flush cache ───────────────────────────────
		$updated = $wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $new_content ),
			array( 'ID' => $post_parent )
		);
		// Targeted cache invalidation
		wp_cache_delete( $post_parent, 'posts' );
		wp_cache_delete( $post_parent, 'post_meta' );

		$result = array(
			'success'     => true,
			'replaced'    => true,
			'count'       => $count,
			'post_parent' => $post_parent,
		);

		if ( false === $updated ) {
			$result['success'] = false;
			$result['error']   = 'DB update failed';
		}

		// STEP4 result line: attachment ID | parent ID | new URL | OK/NG
		if ( $this->logger ) {
			$this->logger->log_rewrite_result( $attachment_id, (int) $post_parent, $new_url, $result['success'] );
		}

		return $result;
	}
}

// Backward compatibility alias.
if ( ! class_exists( 'MediaEngineUrlRewriteService', false ) ) {
	class_alias( 'W2P_Media_Url_Rewrite_Service', 'MediaEngineUrlRewriteService' );
}
