<?php
/**
 * Auto Publish Module
 *
 * @package WP_Genius
 * @subpackage Modules/AutoPublish
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoPublishModule extends W2P_Abstract_Module {

	/**
	 * Get Module ID
	 */
	public static function id() {
		return 'auto-publish';
	}

	/**
	 * Get Module Name
	 */
	public static function name() {
		return __( 'Auto Publish', 'wp-genius' );
	}

	/**
	 * Get Module Description
	 */
	public static function description() {
		return __( 'Automatically publish drafts at scheduled intervals or manually in bulk.', 'wp-genius' );
	}

	/**
	 * Initialize Module
	 */
	public function init() {
		// Register Cron
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
		add_action( 'w2p_auto_publish_cron', [ $this, 'run_auto_publish_batch' ] );

		// AJAX Handlers for Manual Processing
		add_action( 'wp_ajax_w2p_auto_publish_process', [ $this, 'ajax_process_publish' ] );
		add_action( 'wp_ajax_w2p_auto_publish_get_stats', [ $this, 'ajax_get_stats' ] );
		add_action( 'wp_ajax_w2p_auto_publish_clean_logs', [ $this, 'ajax_clean_logs' ] );
	}

	/**
	 * Add custom cron schedules
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['w2p_every_5_minutes'] = [
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'wp-genius' ),
		];
		$schedules['w2p_every_15_minutes'] = [
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes', 'wp-genius' ),
		];
		$schedules['w2p_every_30_minutes'] = [
			'interval' => 1800,
			'display'  => __( 'Every 30 Minutes', 'wp-genius' ),
		];
		return $schedules;
	}

	/**
	 * Run Auto Publish Batch (Cron)
	 */
	public function run_auto_publish_batch() {
		$settings = get_option( 'w2p_auto_publish_settings', [] );
		if ( empty( $settings['cron_enabled'] ) ) {
			return;
		}

		$batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 5;
		
		$drafts = get_posts( [
			'post_status'    => 'draft',
			'posts_per_page' => $batch_size,
			'orderby'        => 'date',
			'order'          => 'ASC',
		] );

		foreach ( $drafts as $post ) {
			$this->publish_post( $post->ID, 'scheduled' );
		}
	}

	/**
	 * Publish a single post and log it
	 */
	public function publish_post( $post_id, $source = 'manual' ) {
		$post = get_post( $post_id );
		if ( ! $post || 'draft' !== $post->post_status ) {
			return false;
		}

		// Allow image processing even during AJAX/Cron for auto-publish
		if ( ! defined( 'W2P_FORCE_IMAGE_PROCESS' ) ) {
			define( 'W2P_FORCE_IMAGE_PROCESS', true );
		}

		// Update post status and set current date as publish date
		$current_time = current_time( 'mysql' );
		$args = [
			'ID'            => $post_id,
			'post_content'  => $post->post_content, // Explicitly pass content to ensure filter picks it up
			'post_status'   => 'publish',
			'post_date'     => $current_time,
			'post_date_gmt' => get_gmt_from_date( $current_time ),
			'edit_date'     => true,
		];

		wp_update_post( $args );

		$this->log_activity( $post_id, 'success', '', $source );
		return true;
	}

	/**
	 * Log Activity
	 */
	private function log_activity( $post_id, $status, $message = '', $source = 'manual' ) {
		$logs = get_option( 'w2p_auto_publish_logs', [] );
		$post = get_post( $post_id );
		
		array_unshift( $logs, [
			'time'    => current_time( 'mysql' ),
			'post_id' => $post_id,
			'title'   => $post ? $post->post_title : 'Unknown',
			'status'  => $status,
			'source'  => $source,
			'message' => $message,
		] );

		// Keep only last 100 logs
		$logs = array_slice( $logs, 0, 100 );
		update_option( 'w2p_auto_publish_logs', $logs );
	}

	/**
	 * AJAX Process Publish (Manual)
	 */
	public function ajax_process_publish() {
		check_ajax_referer( 'w2p_auto_publish_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'No permission' );
		}

		$drafts = get_posts( [
			'post_status'    => 'draft',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'fields'         => 'ids',
		] );

		if ( empty( $drafts ) ) {
			wp_send_json_success( [ 'finished' => true ] );
		}

		$post_id = $drafts[0];
		if ( $this->publish_post( $post_id ) ) {
			wp_send_json_success( [
				'finished' => false,
				'post_id'  => $post_id,
				'title'    => get_the_title( $post_id ),
			] );
		} else {
			wp_send_json_error( 'Failed to publish' );
		}
	}

	/**
	 * AJAX Get Stats
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'w2p_auto_publish_nonce', 'nonce' );
		
		$draft_count = (int) wp_count_posts( 'post' )->draft;

		$next_draft = get_posts( [
			'post_status'    => 'draft',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'ASC',
		] );

		wp_send_json_success( [
			'draft_count' => $draft_count,
			'next_post'   => ! empty( $next_draft ) ? [
				'id'    => $next_draft[0]->ID,
				'title' => $next_draft[0]->post_title,
			] : null,
			'logs'        => get_option( 'w2p_auto_publish_logs', [] ),
		] );
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

	/**
	 * Activation Hook: Schedule Cron
	 */
	public function enable() {
		$settings = get_option( 'w2p_auto_publish_settings', [] );
		$interval = isset( $settings['interval'] ) ? $settings['interval'] : 'hourly';
		
		if ( ! wp_next_scheduled( 'w2p_auto_publish_cron' ) ) {
			wp_schedule_event( time(), $interval, 'w2p_auto_publish_cron' );
		}
	}

	/**
	 * Deactivation Hook: Unschedule Cron
	 */
	public function disable() {
		wp_clear_scheduled_hook( 'w2p_auto_publish_cron' );
	}
}
