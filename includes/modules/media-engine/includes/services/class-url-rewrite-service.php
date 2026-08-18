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
			require_once plugin_dir_path( __DIR__ ) . 'class-logger-service.php';
		}
		$this->logger = new MediaEngineConversionLogger();
	}

	public function rewrite_content( $attachment_id, $old_url, $new_url ) {
		global $wpdb;

		// ── Pre-compute regex parts ──────────────────────────────────────────
		$rel_old  = wp_make_link_relative( $old_url );
		$rel_new  = wp_make_link_relative( $new_url );
		$old_info = pathinfo( $rel_old );
		$new_info = pathinfo( $rel_new );

		$old_dir  = trailingslashit( $old_info['dirname'] );
		$new_dir  = trailingslashit( $new_info['dirname'] );
		$filename = $old_info['filename'];
		$old_ext  = $old_info['extension'];
		$new_ext  = $new_info['extension'];

		$pattern = '/'
			. '(https?:\/\/[^\/]+)?'
			. preg_quote( $old_dir, '/' )
			. preg_quote( $filename, '/' )
			. '((?:-\d+x\d+)?(?:-scaled)?)'
			. '\.' . preg_quote( $old_ext, '/' )
			. '/i';

		$replacement = '$1' . $new_dir . $filename . '$2.' . $new_ext;

		// ── Always update the attachment GUID ────────────────────────────────
		$wpdb->update( $wpdb->posts, array( 'guid' => $new_url ), array( 'ID' => $attachment_id ) );

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
		clean_post_cache( $post_parent );

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

		// STEP4 结果行：附件ID | 父级ID | 新地址 | OK/NG
		$this->logger->log_rewrite_result( $attachment_id, (int) $post_parent, $new_url, $result['success'] );

		return $result;
	}
}
