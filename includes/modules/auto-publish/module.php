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

class W2P_AutoPublishModule extends W2P_Abstract_Module {

	/**
	 * Publisher instance.
	 *
	 * @var W2P_AutoPublish_Publisher|null
	 */
	private $publisher;

	/**
	 * Cron handler instance.
	 *
	 * @var W2P_AutoPublish_Cron|null
	 */
	private $cron;

	/**
	 * AJAX handler instance.
	 *
	 * @var W2P_AutoPublish_Ajax|null
	 */
	private $ajax;

	/**
	 * Check if module is enabled
	 */
	public function is_enabled() {
		$settings = get_option( 'w2p_settings', array() );
		return ! empty( $settings[ 'module_' . $this->id() ] );
	}
	/**
	 * Get Settings (Flattened)
	 */
	public function get_settings() {
		$settings = parent::get_settings();

		if ( ! empty( $settings['auto_publish_tabs'] ) && is_array( $settings['auto_publish_tabs'] ) ) {
			$settings = array_merge( $settings, $settings['auto_publish_tabs'] );
		}

		return $settings;
	}
	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
		// 装配职责类（God class 拆分）。
		require_once __DIR__ . '/includes/class-publisher.php';
		require_once __DIR__ . '/includes/class-cron.php';
		require_once __DIR__ . '/includes/class-ajax.php';
		$this->publisher = new W2P_AutoPublish_Publisher( $this );
		$this->cron      = new W2P_AutoPublish_Cron( $this, $this->publisher );
		$this->ajax      = new W2P_AutoPublish_Ajax( $this, $this->publisher );

		// Cron schedules
		add_filter( 'cron_schedules', array( $this->cron, 'add_cron_schedules' ) );

		// Auto publish cron
		add_action( 'w2p_auto_publish_cron', array( $this->cron, 'run_auto_publish_batch' ) );
		add_action( 'init', array( $this->cron, 'maybe_trigger_pseudo_cron' ) );

		// Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_progress_panel' ) );

		// AJAX
		add_action( 'wp_ajax_w2p_auto_publish_process', array( $this->ajax, 'ajax_process_publish' ) );
		add_action( 'wp_ajax_w2p_auto_publish_stats', array( $this->ajax, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_w2p_auto_publish_clean_logs', array( $this->ajax, 'ajax_clean_logs' ) );
	}
	/**
	 * Enqueue Assets
	 */
	public function enqueue_assets( $hook ) {
		// Load on:
		// 1. Post List (edit.php)
		// 2. Main WP Genius Settings (wp-genius-settings)

		$is_settings_page = ( strpos( $hook, 'wp-genius-settings' ) !== false );

		if ( 'edit.php' !== $hook && ! $is_settings_page ) {
			return;
		}

		$plugin_url = plugin_dir_url( WP_GENIUS_FILE );

		wp_register_script( 'w2p-auto-publish', plugin_dir_url( __FILE__ ) . 'assets/js/auto-publish.js', array( 'w2p-core-js', 'jquery' ), W2P_VERSION, true );

		if ( $is_settings_page || 'edit.php' === $hook ) {
			wp_enqueue_script( 'w2p-auto-publish' );

			global $wpdb;
			$draft_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status = 'draft' AND post_type = 'post'" );

			wp_localize_script(
				'w2p-auto-publish',
				'w2p_auto_publish_config',
				array(
					'ajax_url'    => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'w2p_auto_publish_nonce' ),
					'draft_count' => $draft_count,
					'i18n'        => array(
						'processing'          => __( 'Processing', 'wp-genius' ),
						'publishing'          => __( 'Publishing', 'wp-genius' ),
						'preparing'           => __( 'Preparing', 'wp-genius' ),
						'scheduled_running'   => __( 'A scheduled task is running.', 'wp-genius' ),
						'stopping'            => __( 'Stopping...', 'wp-genius' ),
						'stopped'             => __( 'Stopped.', 'wp-genius' ),
						'all_finished'        => __( 'All Finished!', 'wp-genius' ),
						'confirm_clear_logs'  => __( 'Are you sure you want to clear logs?', 'wp-genius' ),
						'logs_cleared'        => __( 'Logs Cleared', 'wp-genius' ),
						'error_clearing_logs' => __( 'Error Clearing Logs', 'wp-genius' ),
						'network_error'       => __( 'Network Error', 'wp-genius' ),
						'retry_stats'         => __( 'Retrying stats...', 'wp-genius' ),
						'no_activity'         => __( 'No activity.', 'wp-genius' ),
						'scheduled'           => __( 'Scheduled', 'wp-genius' ),
						'manual'              => __( 'Manual', 'wp-genius' ),
						'connection_error'    => __( 'Connection Error', 'wp-genius' ),
						'error_prefix'        => __( 'Error', 'wp-genius' ),
						'confirm_nav'         => __( 'Publishing in progress. Leave?', 'wp-genius' ),
					),
				)
			);
		}
	}
	/**
	 * Render Progress Panel
	 */
	public function render_progress_panel() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( $screen->id !== 'edit-post' && strpos( $screen->id, 'wp-genius-settings' ) === false && strpos( $screen->id, 'word2posts' ) === false ) {
			return;
		}

		?>
		<div id="w2p-scheduled-task-status" class="w2p-status-box w2p-hidden">
			<div class="status-header">
				<span class="pulse-icon"></span>
				<strong><?php esc_html_e( 'Scheduled Publishing in Progress...', 'wp-genius' ); ?></strong>
			</div>
			<p class="status-detail"></p>
		</div>
		<?php
	}
	/**
	 * Render settings page
	 *
	 * @note Refactored to use CSF
	 */
	public function render_settings() {
		?>
		<div class="wrap w2p-legacy-redirect">
			<div class="w2p-info-box">
				<i class="fa-solid fa-magic w2p-magic-icon"></i>
				<h2 class="w2p-upgrade-title"><?php esc_html_e( 'Settings Upgrade', 'wp-genius' ); ?></h2>
				<p class="w2p-upgrade-desc">
					<?php esc_html_e( 'This module has been upgraded to use the new Codestar Framework for better stability and user experience.', 'wp-genius' ); ?>
				</p>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=w2p-auto-publish-settings' ) ); ?>" class="button button-primary button-hero">
					<?php esc_html_e( 'Configure Auto Publish Settings', 'wp-genius' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
	/**
	 * Activation Hook: Schedule Cron
	 */
	public function enable() {
		// We generally rely on the module loader, but if this method is called,
		// we check config to set schedule.
		$settings = $this->get_settings();
		$interval = isset( $settings['auto_publish_interval'] ) ? $settings['auto_publish_interval'] : 'hourly';

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
	// ---------------------------------------------------------------------
	// 兼容委托：保持公共 API 签名，逻辑转发到职责类。
	// ---------------------------------------------------------------------

	/**
	 * Publish a single post (delegate).
	 *
	 * @param int         $post_id        Post ID.
	 * @param string      $source         Source.
	 * @param string|null $custom_content Custom content.
	 * @return bool
	 */
	public function publish_post( $post_id, $source = 'manual', $custom_content = null ) {
		return $this->publisher->publish_post( $post_id, $source, $custom_content );
	}

	/**
	 * Run auto publish batch (delegate).
	 *
	 * @return void
	 */
	public function run_auto_publish_batch() {
		$this->cron->run_auto_publish_batch();
	}

	/**
	 * Check cron schedule (delegate).
	 *
	 * @param array $settings Settings.
	 * @return bool
	 */
	public function check_cron_schedule( $settings ) {
		return $this->cron->check_cron_schedule( $settings );
	}
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'AutoPublishModule', false ) ) {
	class_alias( 'W2P_AutoPublishModule', 'AutoPublishModule' );
}
