<?php
/**
 * Auto Publish — AJAX Handling
 *
 * Split from module.php (refactored from the God class).
 *
 * @package WP_Genius
 * @subpackage Modules/AutoPublish
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_AutoPublish_Ajax
 */
class W2P_AutoPublish_Ajax {

	/**
	 * @var mixed
	 */
	private $module;

	/**
	 * @var mixed
	 */
	private $publisher;

	/**
	 * Constructor.
	 *

	 * @param mixed $module module instance.
	 * @param mixed $publisher publisher instance.
	 */
	public function __construct( $module, $publisher ) {
		$this->module = $module;
		$this->publisher = $publisher;
	}

	/**
	 * AJAX Process Publish (Manual)
	 */
	public function ajax_process_publish() {
		check_ajax_referer( 'w2p_auto_publish_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'No permission' );
		}

		// Check for scheduled lock
		$lock = get_transient( 'w2p_auto_publish_active_lock' );
		if ( 'scheduled' === $lock ) {
			wp_send_json_error( 'A scheduled publish task is currently running. Please wait for it to finish.' );
		}

		// Set/Extend manual lock
		set_transient( 'w2p_auto_publish_active_lock', 'manual', 60 ); // 1 min heart-beat lock

		$exclude = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) $_POST['exclude'] ) : array();

		$drafts = get_posts(
			array(
				'post_status'    => 'draft',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'post__not_in'   => $exclude,
			)
		);

		if ( empty( $drafts ) ) {
			wp_send_json_success( array( 'finished' => true ) );
		}

		$post_id        = $drafts[0];
		$custom_content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : null;

		if ( $this->publisher->publish_post( $post_id, 'manual', $custom_content ) ) {
			wp_send_json_success(
				array(
					'finished' => false,
					'post_id'  => $post_id,
					'title'    => get_the_title( $post_id ),
				)
			);
		} else {
			wp_send_json_error( 'Failed to publish' );
		}
	}
	/**
	 * AJAX Get Stats
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'w2p_auto_publish_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'No permission' );
		}

		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}
		$exclude = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) $_POST['exclude'] ) : array();

		global $wpdb;
		$draft_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status = 'draft' AND post_type = 'post'" );

		$next_draft = get_posts(
			array(
				'post_status'    => 'draft',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'post__not_in'   => $exclude,
			)
		);

		wp_send_json_success(
			array(
				'draft_count'      => $draft_count,
				'next_post'        => ! empty( $next_draft ) ? array(
					'id'    => $next_draft[0]->ID,
					'title' => $next_draft[0]->post_title,
				) : null,
				'scheduled_status' => get_transient( 'w2p_auto_publish_scheduled_status' ),
				'active_lock'      => get_transient( 'w2p_auto_publish_active_lock' ),
				'logs'             => get_option( 'w2p_auto_publish_logs', array() ),
			)
		);
	}
	/**
	 * AJAX Clean Logs
	 */
	public function ajax_clean_logs() {
		check_ajax_referer( 'w2p_auto_publish_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'No permission' );
		}

		delete_option( 'w2p_auto_publish_logs' );
		wp_send_json_success();
	}
}
