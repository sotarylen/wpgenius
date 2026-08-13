<?php
/**
 * WP Genius — Accelerate Module
 *
 * 后台清理、更新控制、本地头像、上传重命名与"删除文章及图片"工具。
 *
 * 本文件为模块门面：负责元数据与钩子装配，
 * 具体逻辑委托给 includes/ 下的职责类（AdminCleanup/UpdateControl/LocalAvatar/UploadRename/ImageCleanup）。
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Accelerate Module Class
 */
class W2P_AccelerateModule extends W2P_Abstract_Module {

	/**
	 * Admin cleanup handler.
	 *
	 * @var W2P_Accelerate_AdminCleanup|null
	 */
	private $admin_cleanup;

	/**
	 * Update control handler.
	 *
	 * @var W2P_Accelerate_UpdateControl|null
	 */
	private $update_control;

	/**
	 * Local avatar handler.
	 *
	 * @var W2P_Accelerate_LocalAvatar|null
	 */
	private $local_avatar;

	/**
	 * Upload rename handler.
	 *
	 * @var W2P_Accelerate_UploadRename|null
	 */
	private $upload_rename;

	/**
	 * Image cleanup handler.
	 *
	 * @var W2P_Accelerate_ImageCleanup|null
	 */
	private $image_cleanup;

	public function settings_key() {
		return 'w2p_settings';
	}
	public static function id() {
		return 'accelerate';
	}
	public static function name() {
		return __( 'Accelerate', 'wp-genius' );
	}
	public static function description() {
		return __( 'Optimize WordPress performance by cleaning up admin interface and controlling update behaviors.', 'wp-genius' );
	}
	public static function icon() {
		return 'fa-solid fa-gauge-high';
	}
	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
		// Load delegated handler classes.
		require_once __DIR__ . '/includes/class-admin-cleanup.php';
		require_once __DIR__ . '/includes/class-update-control.php';
		require_once __DIR__ . '/includes/class-local-avatar.php';
		require_once __DIR__ . '/includes/class-upload-rename.php';
		require_once __DIR__ . '/includes/class-image-cleanup.php';

		// 职责类装配。
		$this->admin_cleanup  = new W2P_Accelerate_AdminCleanup( $this );
		$this->update_control = new W2P_Accelerate_UpdateControl( $this );
		$this->local_avatar   = new W2P_Accelerate_LocalAvatar( $this );
		$this->upload_rename  = new W2P_Accelerate_UploadRename( $this );
		$this->image_cleanup  = new W2P_Accelerate_ImageCleanup( $this );

		// Cleanup Functionality Hooks
		add_action( 'wp_before_admin_bar_render', array( $this->admin_cleanup, 'clean_admin_bar' ) );
		add_action( 'wp_dashboard_setup', array( $this->admin_cleanup, 'clean_dashboard_widgets' ), 999 );
		add_filter( 'disable_months_dropdown', array( $this->admin_cleanup, 'should_disable_months_dropdown' ), 10, 2 );
		add_filter( 'media_library_months_with_files', array( $this->admin_cleanup, 'disable_media_months' ) );
		add_filter( 'query', array( $this->admin_cleanup, 'intercept_date_query' ) );

		// Update Behavior Hooks
		add_action( 'init', array( $this->update_control, 'apply_update_behavior' ), 1 );

		// Local Avatar Management Hooks
		$this->local_avatar->init_local_avatar();

		// Upload Rename Hooks
		$this->upload_rename->init_upload_rename();

		// Delete with Images Hooks
		$this->image_cleanup->init_cleanup_images();

		// Admin Body Classes for conditional styles
		add_filter( 'admin_body_class', array( $this->admin_cleanup, 'add_body_classes' ) );
	}
	// ---------------------------------------------------------------------
	// 兼容委托：保持公共 API 签名，逻辑转发到职责类。
	// ---------------------------------------------------------------------

	/**
	 * Clean admin bar.
	 *
	 * @return void
	 */
	public function clean_admin_bar() {
		$this->admin_cleanup->clean_admin_bar();
	}

	/**
	 * Clean dashboard widgets.
	 *
	 * @return void
	 */
	public function clean_dashboard_widgets() {
		$this->admin_cleanup->clean_dashboard_widgets();
	}

	/**
	 * Should disable months dropdown.
	 *
	 * @param bool   $disable   Current value.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function should_disable_months_dropdown( $disable, $post_type ) {
		return $this->admin_cleanup->should_disable_months_dropdown( $disable, $post_type );
	}

	/**
	 * Disable media months.
	 *
	 * @param array $months Months.
	 * @return array
	 */
	public function disable_media_months( $months ) {
		return $this->admin_cleanup->disable_media_months( $months );
	}

	/**
	 * Intercept date query.
	 *
	 * @param string $query Query.
	 * @return string
	 */
	public function intercept_date_query( $query ) {
		return $this->admin_cleanup->intercept_date_query( $query );
	}

	/**
	 * Apply update behavior.
	 *
	 * @return void
	 */
	public function apply_update_behavior() {
		$this->update_control->apply_update_behavior();
	}

	/**
	 * Save avatar.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function save_avatar( $user_id ) {
		$this->local_avatar->save_avatar( $user_id );
	}

	/**
	 * Get local avatar.
	 *
	 * @param mixed        $avatar      Avatar.
	 * @param int|string   $id_or_email User ID or email.
	 * @param int          $size        Size.
	 * @param string       $default     Default.
	 * @param string       $alt         Alt text.
	 * @return mixed
	 */
	public function get_local_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
		return $this->local_avatar->get_local_avatar( $avatar, $id_or_email, $size, $default, $alt );
	}

	/**
	 * Handle upload prefilter.
	 *
	 * @param array $file File data.
	 * @return array
	 */
	public function handle_upload_prefilter( $file ) {
		return $this->upload_rename->handle_upload_prefilter( $file );
	}

	/**
	 * Maybe replace attachment title.
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Post array.
	 * @return array
	 */
	public function maybe_replace_attachment_title( $data, $postarr ) {
		return $this->upload_rename->maybe_replace_attachment_title( $data, $postarr );
	}

	/**
	 * Cleanup images — handle bulk actions.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction    Action.
	 * @param array  $post_ids    Post IDs.
	 * @return string
	 */
	public function cleanup_images_handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		return $this->image_cleanup->cleanup_images_handle_bulk_actions( $redirect_to, $doaction, $post_ids );
	}

	/**
	 * Cleanup images — handle delete action.
	 *
	 * @return void
	 */
	public function cleanup_images_handle_delete_action() {
		$this->image_cleanup->cleanup_images_handle_delete_action();
	}
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'AccelerateModule', false ) ) {
	class_alias( 'W2P_AccelerateModule', 'AccelerateModule' );
}
