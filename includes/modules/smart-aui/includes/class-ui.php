<?php
/**
 * Smart AUI — UI Handler
 *
 * Manages admin resource loading, progress UI template rendering, and native menu removal.
 * Split from module.php (refactored from the original God class).
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_SmartAUI_UI
 */
class W2P_SmartAUI_UI {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_SmartAUIModule
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param W2P_SmartAUIModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * Enqueue Progress UI Scripts
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_progress_ui_scripts( $hook ) {
		// Monitor hook for specific pages
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- enqueue hook only reads page parameters.
		$page             = isset( $_GET['page'] ) ? $_GET['page'] : '';
		$is_settings_page = ( $page === 'wp-genius-settings' || strpos( $page, 'wp-genius' ) !== false );

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) && ! $is_settings_page ) {
			return;
		}

		// Read module settings directly
		$global_settings = $this->module->get_settings();

		// Use the WP_GENIUS_FILE constant to compute the plugin root URL
		$plugin_url = plugin_dir_url( WP_GENIUS_FILE );

		// If on settings page, load the settings manager JS, scanner JS & styles
		if ( $is_settings_page ) {
			$admin_css_path = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules/smart-aui/assets/css/smart-aui-admin.css';
			$admin_css_ver  = file_exists( $admin_css_path ) ? filemtime( $admin_css_path ) : W2P_VERSION;
			wp_enqueue_style( 'w2p-smart-aui-admin', $plugin_url . 'includes/modules/smart-aui/assets/css/smart-aui-admin.css', array( 'w2p-core-css' ), $admin_css_ver );

			wp_enqueue_script( 'w2p-smart-aui-settings', $plugin_url . 'includes/modules/smart-aui/assets/js/smart-aui-settings.js', array( 'jquery' ), W2P_VERSION, true );

			wp_localize_script(
				'w2p-smart-aui-settings',
				'w2pSmartAuiSettings',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'w2p_smart_aui_progress' ),
					'strings'  => array(
						'loading'       => __( 'Loading logs...', 'wp-genius' ),
						'no_logs'       => __( 'No capture failures recorded.', 'wp-genius' ),
						'confirm_clear' => __( 'Are you sure?', 'wp-genius' ),
						'logs_cleared'  => __( 'Logs Cleared', 'wp-genius' ),
						'error_prefix'  => __( 'Error: ', 'wp-genius' ),
						'error_loading' => __( 'Error loading logs.', 'wp-genius' ),
						'unknown_error' => __( 'Unknown Error', 'wp-genius' ),
						'network_error' => __( 'Network Error', 'wp-genius' ),
					),
				)
			);

			$default_limit     = isset( $global_settings['smart_aui_scan_limit'] ) ? absint( $global_settings['smart_aui_scan_limit'] ) : 100;
			$scanner_js_path   = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules/smart-aui/assets/js/smart-aui-scanner.js';
			$scanner_js_ver    = file_exists( $scanner_js_path ) ? filemtime( $scanner_js_path ) : W2P_VERSION;

			wp_enqueue_script( 'w2p-smart-aui-scanner', $plugin_url . 'includes/modules/smart-aui/assets/js/smart-aui-scanner.js', array( 'jquery', 'wp-i18n' ), $scanner_js_ver, true );

			wp_localize_script(
				'w2p-smart-aui-scanner',
				'w2pSmartAuiScanner',
				array(
					'ajax_url'     => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'w2p_smart_aui_progress' ),
					'defaultLimit' => $default_limit,
					'i18n'         => array(
						'scanStarted'         => __( 'Starting scan...', 'wp-genius' ),
						'scanning'            => __( 'Scanning posts...', 'wp-genius' ),
						'scanningBatch'       => __( 'Scanning posts batch...', 'wp-genius' ),
						'postsLabel'          => __( 'posts', 'wp-genius' ),
						'itemsLabel'          => __( 'items', 'wp-genius' ),
						'moreLabel'           => __( 'more...', 'wp-genius' ),
						'withExternalLabel'   => __( 'containing external media', 'wp-genius' ),
						'scannedBatchMsg'     => __( 'Batch scanned', 'wp-genius' ),
						'scanFoundMsg'        => __( 'Scan complete: found %d post(s) containing %u external URL(s).', 'wp-genius' ),
						'scanFailed'          => __( 'Scan failed.', 'wp-genius' ),
						'networkError'        => __( 'Network error occurred.', 'wp-genius' ),
						'pendingLabel'        => __( 'Pending', 'wp-genius' ),
						'processingLabel'     => __( 'Processing...', 'wp-genius' ),
						'completedLabel'      => __( 'Completed', 'wp-genius' ),
						'processingSelected'  => __( 'Starting grab for %d post(s)...', 'wp-genius' ),
						'confirmGrabSelected' => __( 'Are you sure you want to grab external media for %d post(s)?', 'wp-genius' ),
						'batchDoneMsg'        => __( 'Batch complete: %d post(s) updated.', 'wp-genius' ),
						'autoStarted'         => __( 'Full Auto Scan & Grab Mode Started', 'wp-genius' ),
						'autoComplete'        => __( 'All posts scanned and processed successfully!', 'wp-genius' ),
						'userStopped'         => __( 'Processing stopped by user.', 'wp-genius' ),
						'paused'              => __( 'Paused', 'wp-genius' ),
						'pauseLabel'          => __( 'Pause', 'wp-genius' ),
						'resumeLabel'         => __( 'Resume', 'wp-genius' ),
						'continuing'          => __( 'Resuming...', 'wp-genius' ),
						'pauseRequested'      => __( 'Pause requested, will pause after current batch...', 'wp-genius' ),
						'stopRequested'       => __( 'Stop requested, stopping...', 'wp-genius' ),
						'loading'             => __( 'Loading logs...', 'wp-genius' ),
						'noLogs'              => __( 'No failed logs found.', 'wp-genius' ),
						'logsCleared'         => __( 'Logs Cleared', 'wp-genius' ),
						'confirmClearLogs'    => __( 'Are you sure you want to clear all failed logs?', 'wp-genius' ),
					),
				)
			);
		}

		$settings = array(
			'show_progress_ui'   => isset( $global_settings['smart_aui_show_progress_ui'] ) ? (bool) $global_settings['smart_aui_show_progress_ui'] : true,
			'concurrent_threads' => ! empty( $global_settings['smart_aui_concurrent_threads'] ) ? (int) $global_settings['smart_aui_concurrent_threads'] : 4,
			'max_retries'        => isset( $global_settings['smart_aui_max_retries'] ) ? (int) $global_settings['smart_aui_max_retries'] : 3,
			'skip_duplicates'    => isset( $global_settings['smart_aui_skip_duplicates'] ) ? (bool) $global_settings['smart_aui_skip_duplicates'] : true,
			'base_url'           => ! empty( $global_settings['smart_aui_base_url'] ) ? $global_settings['smart_aui_base_url'] : site_url(),
			'domain_exclusions'  => isset( $global_settings['smart_aui_exclude_domains'] ) ? $global_settings['smart_aui_exclude_domains'] : '',
			'capture_videos'     => isset( $global_settings['smart_aui_capture_videos'] ) ? (bool) $global_settings['smart_aui_capture_videos'] : false,
			'min_width'          => isset( $global_settings['smart_aui_min_width'] ) ? (int) $global_settings['smart_aui_min_width'] : 300,
			'min_height'         => isset( $global_settings['smart_aui_min_height'] ) ? (int) $global_settings['smart_aui_min_height'] : 200,
			'auto_set_featured'  => isset( $global_settings['smart_aui_auto_set_featured_image'] ) ? (bool) $global_settings['smart_aui_auto_set_featured_image'] : true,
			'image_name_pattern' => ! empty( $global_settings['smart_aui_image_name_pattern'] ) ? $global_settings['smart_aui_image_name_pattern'] : '%filename%',
			'alt_text_pattern'   => ! empty( $global_settings['smart_aui_alt_text_pattern'] ) ? $global_settings['smart_aui_alt_text_pattern'] : '%image_alt%',
		);

		// Cache-bust: use file mtime so JS edits (e.g. batch local-image ID update) are picked up immediately.
		$smart_aui_ui_path = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules/smart-aui/assets/js/smart-aui-ui.js';
		$smart_aui_ui_ver  = file_exists( $smart_aui_ui_path ) ? filemtime( $smart_aui_ui_path ) : W2P_VERSION;

		wp_register_script( 'w2p-smart-auto-upload', $plugin_url . 'includes/modules/smart-aui/assets/js/smart-aui-ui.js', array( 'w2p-core-js' ), $smart_aui_ui_ver, true );

		wp_enqueue_script( 'w2p-smart-auto-upload' );

		wp_localize_script(
			'w2p-smart-auto-upload',
			'w2pSmartAuiParams',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'w2p_smart_aui_progress' ),
				'debug'    => WP_DEBUG,
				'settings' => $settings,
				'i18n'     => array(
					'confirmCancel'           => __( 'Are you sure you want to cancel the image upload?', 'wp-genius' ),
					'confirmSkip'             => __( 'Are you sure you want to stop the current image capture and publish the article directly?\n\nNote: Successfully captured images will be replaced, failed images will keep their original URLs.', 'wp-genius' ),
					'statusStopped'           => __( 'Capture stopped, preparing to publish...', 'wp-genius' ),
					'statusPreparing'         => __( 'Preparing to process batch posts...', 'wp-genius' ),
					'statusPreparingPublish'  => __( 'Preparing to process and publish batch posts...', 'wp-genius' ),
					'statusProcessing'        => __( 'Processing', 'wp-genius' ),
					'statusProcessAndPublish' => __( 'Process and Publish', 'wp-genius' ),
					'statusProcessAndDraft'   => __( 'Process and Set as Draft', 'wp-genius' ),
					'statusProcessAndPending' => __( 'Process and Set as Pending', 'wp-genius' ),
					'statusProcessAndPrivate' => __( 'Process and Set as Private', 'wp-genius' ),
					'completeAll'             => __( 'All batch processing completed!', 'wp-genius' ),
					'completePublished'       => __( 'All posts have been processed and published!', 'wp-genius' ),
					'completeDraft'           => __( 'All posts have been processed and set as drafts!', 'wp-genius' ),
					'completePending'         => __( 'All posts have been processed and set as pending!', 'wp-genius' ),
					'completePrivate'         => __( 'All posts have been processed and set as private!', 'wp-genius' ),
					'processingImages'        => __( 'Processing external images in parallel...', 'wp-genius' ),
					'processingMedia'         => __( 'Processing external media (images + videos) in parallel...', 'wp-genius' ),
					'allComplete'             => __( '✅ Image processing complete! Saving...', 'wp-genius' ),
					'failedGetPostId'         => __( 'Unable to get post ID', 'wp-genius' ),
				),
			)
		);
	}

	/**
	 * Remove Native Admin Menu
	 *
	 * @return void
	 */
	public function remove_native_admin_menu() {
		remove_submenu_page( 'options-general.php', 'smart-auto-upload-images' );
	}

	/**
	 * Render Progress UI Template
	 *
	 * @return void
	 */
	public function render_progress_ui_template() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Allow loading on the post editor, post list, and plugin settings pages
		$allowed_bases = array( 'post', 'edit', 'toplevel_page_wp-genius', 'wp-genius_page_wp-genius-settings' );
		if ( ! in_array( $screen->base, $allowed_bases, true ) && strpos( $screen->id, 'wp-genius' ) === false ) {
			return;
		}

		$template_path = dirname( __DIR__ ) . '/views/progress-template.php';
}
}