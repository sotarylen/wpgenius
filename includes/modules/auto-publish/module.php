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

	public static function icon() {
		return 'fa-solid fa-clock';
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

		// Pseudo-Cron / Page Load Trigger
		add_action( 'init', [ $this, 'maybe_trigger_pseudo_cron' ] );

		// Enqueue Assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Progress Panel (Admin Notice)
		add_action( 'admin_notices', [ $this, 'render_progress_panel' ] );
	}

	/**
	 * Enqueue Assets
	 */
	public function enqueue_assets( $hook ) {
		// Only load on Post List and Settings page
		if ( 'edit.php' !== $hook && strpos( $hook, 'wp-genius' ) === false && strpos( $hook, 'word2posts' ) === false ) {
			return;
		}

		wp_enqueue_style( 'w2p-auto-publish' );

		wp_enqueue_script( 'w2p-auto-publish' );

		wp_localize_script(
			'w2p-auto-publish',
			'w2pAutoPublishParams',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'w2p_auto_publish_nonce' ),
				'l10n'     => [
					'processing' => __( 'Currently processing Post ID:', 'wp-genius' ),
				]
			]
		);
	}

	/**
	 * Render Progress Panel
	 */
	public function render_progress_panel() {
		$screen = get_current_screen();
		
		// Only show on post list or our settings page
		if ( $screen->id !== 'edit-post' && strpos( $screen->id, 'wp-genius' ) === false && strpos( $screen->id, 'word2posts' ) === false ) {
			return;
		}

		?>
		<div id="w2p-scheduled-task-status" class="w2p-status-box" style="display:none; margin-top: 20px;">
			<div class="status-header">
				<span class="pulse-icon"></span>
				<strong><?php _e( 'Scheduled Publishing in Progress...', 'wp-genius' ); ?></strong>
			</div>
			<p class="status-detail"></p>
		</div>
		<?php
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

		// Prevent concurrent executions (Check both lock and manual lock)
		if ( get_transient( 'w2p_auto_publish_active_lock' ) ) {
			return;
		}

		// Set scheduled lock
		set_transient( 'w2p_auto_publish_active_lock', 'scheduled', 300 ); // 5 min safety lock

		$batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 5;
		
		$drafts = get_posts( [
			'post_status'    => 'draft',
			'posts_per_page' => $batch_size,
			'orderby'        => 'date',
			'order'          => 'ASC',
		] );

		foreach ( $drafts as $post ) {
			// Update status for UI visibility
			set_transient( 'w2p_auto_publish_scheduled_status', [
				'post_id' => $post->ID,
				'title'   => $post->post_title,
				'time'    => current_time( 'mysql' ),
			], 300 );

			$this->publish_post( $post->ID, 'scheduled' );
		}

		// Update last run time
		update_option( 'w2p_auto_publish_last_run', time() );
		delete_transient( 'w2p_auto_publish_scheduled_status' );
		delete_transient( 'w2p_auto_publish_active_lock' );
	}

	/**
	 * Maybe trigger pseudo-cron on page load
	 */
	public function maybe_trigger_pseudo_cron() {
		// Never run pseudo-cron during AJAX or Upload requests to prevent bottlenecks
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( is_admin() && ( basename( $_SERVER['PHP_SELF'] ) === 'async-upload.php' || basename( $_SERVER['PHP_SELF'] ) === 'media-new.php' ) ) {
			return;
		}

		// Only run in admin or periodically on front-end
		if ( is_admin() || ( ! is_admin() && mt_rand( 1, 100 ) <= 5 ) ) {
			$settings = get_option( 'w2p_auto_publish_settings', [] );
			if ( empty( $settings['cron_enabled'] ) ) {
				return;
			}

			$last_run = get_option( 'w2p_auto_publish_last_run', 0 );
			$interval_name = isset( $settings['interval'] ) ? $settings['interval'] : 'hourly';
			
			// Map interval names to seconds
			$intervals = [
				'w2p_every_5_minutes'  => 300,
				'w2p_every_15_minutes' => 900,
				'w2p_every_30_minutes' => 1800,
				'hourly'               => 3600,
				'twicedaily'           => 43200,
				'daily'                => 86400,
			];
			
			$seconds = isset( $intervals[ $interval_name ] ) ? $intervals[ $interval_name ] : 3600;

			if ( ( time() - $last_run ) >= $seconds ) {
				$this->run_auto_publish_batch();
			}
		}
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
		$skip_processing = isset( $_POST['skip_image_processing'] ) && $_POST['skip_image_processing'] == '1';

		if ( ! $skip_processing && class_exists( 'SmartAutoUploadImages\Services\ImageProcessorExtended' ) ) {
			// Hook for progress updates
			$progress_callback = function( $image, $result, $index ) use ( $post_id, $post ) {
				// Get current status to preserve title/time
				$current_status = get_transient( 'w2p_auto_publish_scheduled_status' );
				if ( ! is_array( $current_status ) ) {
					$current_status = [
						'post_id' => $post_id,
						'title'   => $post->post_title,
						'time'    => current_time( 'mysql' ),
					];
				}
				
				$current_status['image_progress'] = sprintf( __( 'Processing image %d...', 'wp-genius' ), $index + 1 );
				set_transient( 'w2p_auto_publish_scheduled_status', $current_status, 300 );
			};
			
			add_action( 'smart_aui_image_processed', $progress_callback, 10, 3 );
			
			// Process!
			$processor = new \SmartAutoUploadImages\Services\ImageProcessorExtended();
			// We pass $post explicitly to ensure it uses the latest object
			$processed_content = $processor->process_post_content( $post->post_content, [ 'ID' => $post_id ] );
			
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
		$args = [
			'ID'            => $post_id,
			'post_content'  => $post->post_content, // Use the processed content from memory
			'post_status'   => 'publish',
			'post_date'     => $current_time,
			'post_date_gmt' => get_gmt_from_date( $current_time ),
			'edit_date'     => true,
		];
		
		// 设置文章级别的标记，告诉 wp_insert_post_data 钩子不要再次处理图片
		// 使用 $_POST 而不是全局常量，避免影响后续请求
		$_POST['w2p_smart_aui_processed'] = true;
		
		// 监控 wp_insert_post_data 钩子的返回值
		$monitor_hook = function( $data ) use ( $post_id ) {
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

		// Check for scheduled lock
		$lock = get_transient( 'w2p_auto_publish_active_lock' );
		if ( 'scheduled' === $lock ) {
			wp_send_json_error( 'A scheduled publish task is currently running. Please wait for it to finish.' );
		}

		// Set/Extend manual lock
		set_transient( 'w2p_auto_publish_active_lock', 'manual', 60 ); // 1 min heart-beat lock

		$exclude = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) $_POST['exclude'] ) : [];

		$drafts = get_posts( [
			'post_status'    => 'draft',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'post__not_in'   => $exclude,
		] );

		if ( empty( $drafts ) ) {
			wp_send_json_success( [ 'finished' => true ] );
		}

		$post_id = $drafts[0];
		$custom_content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : null;
		
		if ( $this->publish_post( $post_id, 'manual', $custom_content ) ) {
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
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'No permission' );
		}

		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}
		$exclude = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) $_POST['exclude'] ) : [];
		
		global $wpdb;
		$draft_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status = 'draft' AND post_type = 'post'" );

		$next_draft = get_posts( [
			'post_status'    => 'draft',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'post__not_in'   => $exclude,
		] );

		wp_send_json_success( [
			'draft_count'      => $draft_count,
			'next_post'        => ! empty( $next_draft ) ? [
				'id'    => $next_draft[0]->ID,
				'title' => $next_draft[0]->post_title,
			] : null,
			'scheduled_status' => get_transient( 'w2p_auto_publish_scheduled_status' ),
			'active_lock'      => get_transient( 'w2p_auto_publish_active_lock' ),
			'logs'             => get_option( 'w2p_auto_publish_logs', [] ),
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

	/**
	 * Render settings page
	 */
	public function render_settings() {
		$this->render_view( 'settings' );
	}
}
