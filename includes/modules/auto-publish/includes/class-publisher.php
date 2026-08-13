<?php
/**
 * Auto Publish — 发布服务
 *
 * 从 module.php 拆分（God class 重构）。
 *
 * @package WP_Genius
 * @subpackage Modules/AutoPublish
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_AutoPublish_Publisher
 */
class W2P_AutoPublish_Publisher {

	/**
	 * @var mixed
	 */
	private $module;

	/**
	 * Constructor.
	 *

	 * @param mixed $module module instance.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * Publish a single post and log it
	 */
	public function publish_post( $post_id, $source = 'manual', $custom_content = null ) {
		$post = get_post( $post_id );
		if ( ! $post || 'draft' !== $post->post_status ) {
			return false;
		}

		// Use custom content if provided (from frontend JS)
		if ( ! empty( $custom_content ) ) {
			$post->post_content = $custom_content;
		}

		// Allow image processing even during AJAX/Cron for auto-publish
		// Unless explicitly skipped by frontend
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- publish_post 被 AJAX（上层已验 nonce）与 Cron 调用；此处仅读取标志。
		$skip_processing = isset( $_POST['skip_image_processing'] ) && $_POST['skip_image_processing'] === '1';

		if ( ! $skip_processing && class_exists( 'SmartAutoUploadImages\Services\ImageProcessorExtended' ) ) {
			// Hook for progress updates
			$progress_callback = function ( $image, $result, $index ) use ( $post_id, $post ) {
				// Get current status to preserve title/time
				$current_status = get_transient( 'w2p_auto_publish_scheduled_status' );
				if ( ! is_array( $current_status ) ) {
					$current_status = array(
						'post_id' => $post_id,
						'title'   => $post->post_title,
						'time'    => current_time( 'mysql' ),
					);
				}

				// translators: %1: placeholder。
				$current_status['image_progress'] = sprintf( __( 'Processing image %d...', 'wp-genius' ), $index + 1 );
				set_transient( 'w2p_auto_publish_scheduled_status', $current_status, 300 );
			};

			add_action( 'smart_aui_image_processed', $progress_callback, 10, 3 );

			// Process!
			$processor = new \SmartAutoUploadImages\Services\ImageProcessorExtended();
			// We pass $post explicitly to ensure it uses the latest object
			$processed_content = $processor->process_post_content( $post->post_content, array( 'ID' => $post_id ) );

			remove_action( 'smart_aui_image_processed', $progress_callback );

			if ( $processed_content && $processed_content !== $post->post_content ) {
				// Update post content
				$post->post_content = $processed_content;
			}
		}

		// Clear post cache before reading content to ensure we get the latest version
		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'posts' );

		// Update post status and set current date as publish date
		$current_time = current_time( 'mysql' );
		$args         = array(
			'ID'            => $post_id,
			'post_content'  => $post->post_content, // Use the processed content from memory
			'post_status'   => 'publish',
			'post_date'     => $current_time,
			'post_date_gmt' => get_gmt_from_date( $current_time ),
			'edit_date'     => true,
		);

		// 设置文章级别的标记，告诉 wp_insert_post_data 钩子不要再次处理图片
		$_POST['w2p_smart_aui_processed'] = true;

		// 监控 wp_insert_post_data 钩子的返回值
		$monitor_hook = function ( $data ) use ( $post_id ) {
			return $data;
		};
		add_filter( 'wp_insert_post_data', $monitor_hook, 999, 1 );

		$result = wp_update_post( $args, true );

		// 移除监控钩子
		remove_filter( 'wp_insert_post_data', $monitor_hook, 999 );

		// 清除标记，以便下一篇文章可以正常处理
		unset( $_POST['w2p_smart_aui_processed'] );

		if ( is_wp_error( $result ) ) {
			$this->log_activity( $post_id, 'error', $result->get_error_message(), $source );
			return false;
		}

		// 验证状态是否真的更新了
		$updated_post = get_post( $post_id );

		if ( $updated_post->post_status !== 'publish' ) {
			$this->log_activity( $post_id, 'error', 'Status not updated to publish', $source );
			return false;
		}

		$this->log_activity( $post_id, 'success', '', $source );
		return true;
	}
	/**
	 * Log Activity
	 */
	private function log_activity( $post_id, $status, $message = '', $source = 'manual' ) {
		$logs = get_option( 'w2p_auto_publish_logs', array() );
		$post = get_post( $post_id );

		array_unshift(
			$logs,
			array(
				'time'    => current_time( 'mysql' ),
				'post_id' => $post_id,
				'title'   => $post ? $post->post_title : 'Unknown',
				'status'  => $status,
				'source'  => $source,
				'message' => $message,
			)
		);

		// Keep only last 100 logs
		$logs = array_slice( $logs, 0, 100 );
		update_option( 'w2p_auto_publish_logs', $logs );
	}
}
