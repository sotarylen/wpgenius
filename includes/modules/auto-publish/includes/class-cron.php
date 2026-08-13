<?php
/**
 * Auto Publish — 定时任务
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
 * Class W2P_AutoPublish_Cron
 */
class W2P_AutoPublish_Cron {

	/**
	 * @var mixed
	 */
	private $module;

	/**
	 * Publisher instance.
	 *
	 * @var W2P_AutoPublish_Publisher
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
	 * Add custom cron schedules
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['w2p_every_5_minutes']  = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'wp-genius' ),
		);
		$schedules['w2p_every_15_minutes'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes', 'wp-genius' ),
		);
		$schedules['w2p_every_30_minutes'] = array(
			'interval' => 1800,
			'display'  => __( 'Every 30 Minutes', 'wp-genius' ),
		);
		return $schedules;
	}
	/**
	 * Check and Update Cron Schedule
	 *
	 * @param array $settings
	 */
	public function check_cron_schedule( $settings ) {
		// Handle nested keys from CSF tabbed field
		$cron_enabled = false;
		$interval     = 'hourly';

		if ( ! empty( $settings['auto_publish_tabs']['auto_publish_cron_enabled'] ) ) {
			$cron_enabled = $settings['auto_publish_tabs']['auto_publish_cron_enabled'];
		} elseif ( ! empty( $settings['auto_publish_cron_enabled'] ) ) {
			$cron_enabled = $settings['auto_publish_cron_enabled'];
		}

		if ( ! empty( $settings['auto_publish_tabs']['auto_publish_interval'] ) ) {
			$interval = $settings['auto_publish_tabs']['auto_publish_interval'];
		} elseif ( ! empty( $settings['auto_publish_interval'] ) ) {
			$interval = $settings['auto_publish_interval'];
		}
		// error_log( 'Auto Publish Cron Check: ' . ( $cron_enabled ? 'Enabled' : 'Disabled' ) . ', Interval: ' . $interval );

		// Clear existing hook first to ensure cleanliness
		W2P_Task_Queue::unschedule( 'w2p_auto_publish_cron' );

		if ( $cron_enabled ) {
			// Schedule using Task Queue wrapper
			// Note: The wrapper handles the check for existing schedule internally
			W2P_Task_Queue::schedule_recurring( 'w2p_auto_publish_cron', array(), $interval );
			//
			//  error_log( 'Auto Publish Cron Scheduled via Task Queue' );
		}
	}
	/**
	 * Run Auto Publish Batch (Cron)
	 */
	public function run_auto_publish_batch() {
		$global_settings = $this->module->get_settings();

		// Use new keys
		if ( empty( $global_settings['auto_publish_cron_enabled'] ) ) {
			return;
		}

		// Prevent concurrent executions (Check both lock and manual lock)
		if ( get_transient( 'w2p_auto_publish_active_lock' ) ) {
			return;
		}

		// Set scheduled lock
		set_transient( 'w2p_auto_publish_active_lock', 'scheduled', 300 ); // 5 min safety lock

		$batch_size = isset( $global_settings['auto_publish_batch_size'] ) ? absint( $global_settings['auto_publish_batch_size'] ) : 5;

		$drafts = get_posts(
			array(
				'post_status'    => 'draft',
				'posts_per_page' => $batch_size,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		foreach ( $drafts as $post ) {
			// Update status for UI visibility
			set_transient(
				'w2p_auto_publish_scheduled_status',
				array(
					'post_id' => $post->ID,
					'title'   => $post->post_title,
					'time'    => current_time( 'mysql' ),
				),
				300
			);

			$this->publisher->publish_post( $post->ID, 'scheduled' );
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
			$global_settings = $this->module->get_settings();
			if ( empty( $global_settings['auto_publish_cron_enabled'] ) ) {
				return;
			}

			$last_run      = get_option( 'w2p_auto_publish_last_run', 0 );
			$interval_name = isset( $global_settings['auto_publish_interval'] ) ? $global_settings['auto_publish_interval'] : 'hourly';

			// Map interval names to seconds
			$intervals = array(
				'w2p_every_5_minutes'  => 300,
				'w2p_every_15_minutes' => 900,
				'w2p_every_30_minutes' => 1800,
				'hourly'               => 3600,
				'twicedaily'           => 43200,
				'daily'                => 86400,
			);

			$seconds = isset( $intervals[ $interval_name ] ) ? $intervals[ $interval_name ] : 3600;

			if ( ( time() - $last_run ) >= $seconds ) {
				$this->run_auto_publish_batch();
			}
		}
	}
}
