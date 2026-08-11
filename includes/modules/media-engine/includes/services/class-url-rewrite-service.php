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

		// ── Pre-compute regex parts ──────────────────────────────────────────
		$rel_old     = wp_make_link_relative( $old_url );
		$rel_new     = wp_make_link_relative( $new_url );
		$old_info    = pathinfo( $rel_old );
		$new_info    = pathinfo( $rel_new );

		$old_dir       = trailingslashit( $old_info['dirname'] );
		$new_dir       = trailingslashit( $new_info['dirname'] );
		$filename      = $old_info['filename'];
		$old_ext       = $old_info['extension'];
		$new_ext       = $new_info['extension'];

		$pattern = '/'
			. '(https?:\/\/[^\/]+)?'
			. preg_quote( $old_dir, '/' )
			. preg_quote( $filename, '/' )
			. '((?:-\d+x\d+)?(?:-scaled)?)'
			. '\.' . preg_quote( $old_ext, '/' )
			. '/i';

		$replacement = '$1' . $new_dir . $filename . '$2.' . $new_ext;

		// ── Always update the attachment GUID ────────────────────────────────
		$wpdb->update( $wpdb->posts, [ 'guid' => $new_url ], [ 'ID' => $attachment_id ] );

		// ── Find and update the parent post ──────────────────────────────────
		$post_parent = wp_get_post_parent_id( $attachment_id );

		$this->logger->log_debug(
			sprintf( 'rewrite_content: attachment=%d, old=%s, new=%s, parent=%d',
				$attachment_id, $old_url, $new_url, (int) $post_parent )
		);

		if ( ! $post_parent ) {
			return [
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_parent',
			];
		}

		$post = get_post( $post_parent );
		if ( ! $post ) {
			return [ 'success' => false, 'error' => 'Parent post not found', 'post_parent' => $post_parent ];
		}

		$count       = 0;
		$new_content = preg_replace( $pattern, $replacement, $post->post_content, -1, $count );

		if ( $count === 0 || $new_content === $post->post_content ) {
			return [ 'success' => true, 'replaced' => false, 'reason' => 'no_match' ];
		}

		// ── Persist the update and flush cache ───────────────────────────────
		$updated = $wpdb->update(
			$wpdb->posts,
			[ 'post_content' => $new_content ],
			[ 'ID'           => $post_parent ]
		);
		clean_post_cache( $post_parent );

		$result = [ 'success' => true, 'replaced' => true, 'count' => $count, 'post_parent' => $post_parent ];

		if ( false === $updated ) {
			$result['success']   = false;
			$result['error']     = 'DB update failed';
		}

		$log_msg = sprintf(
			'rewrite_content: post=%d, replaced=%d, ok=%s',
			$post_parent, $count, $result['success'] ? 'true' : 'false'
		);
		$this->logger->log_debug( $log_msg );

		return $result;
	}
}
