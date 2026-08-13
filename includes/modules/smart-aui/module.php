<?php
/**
 * Smart Auto Upload Images Module
 *
 * 集成 Smart Auto Upload Images 插件作为 WP Genius 模块
 *
 * 本文件为模块门面：负责元数据、库加载与钩子装配，
 * 具体逻辑委托给 includes/ 下的职责类（Settings/ContentProcessor/Ajax/UI）。
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Smart AUI Module Class (Memory-Optimized)
 */
class W2P_SmartAUIModule extends W2P_Abstract_Module {

	/**
	 * Settings handler instance.
	 *
	 * @var W2P_SmartAUI_Settings|null
	 */
	private $settings;

	/**
	 * Content processor instance.
	 *
	 * @var W2P_SmartAUI_Content_Processor|null
	 */
	private $processor;

	/**
	 * AJAX handler instance.
	 *
	 * @var W2P_SmartAUI_Ajax|null
	 */
	private $ajax;

	/**
	 * UI handler instance.
	 *
	 * @var W2P_SmartAUI_UI|null
	 */
	private $ui;

	/**
	 * Module ID
	 *
	 * @return string
	 */
	public static function id() {
		return 'smart-aui';
	}

	/**
	 * Module Name
	 *
	 * @return string
	 */
	public static function name() {
		return __( 'Smart AUI Lite', 'wp-genius' );
	}

	/**
	 * Module Description
	 *
	 * @return string
	 */
	public static function description() {
		return __( 'Memory-optimized version for large images (>10MB). Skips thumbnail generation to prevent OOM errors. Ideal for MinIO/S3 storage.', 'wp-genius' );
	}

	public static function icon() {
		return 'fa-solid fa-cloud-arrow-down';
	}

	/**
	 * Check if module is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$settings = get_option( 'w2p_settings', array() );
		return ! empty( $settings[ 'module_' . $this->id() ] );
	}

	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
		// Load delegated handler classes.
		require_once __DIR__ . '/includes/class-settings.php';
		require_once __DIR__ . '/includes/class-content-processor.php';
		require_once __DIR__ . '/includes/class-ajax.php';
		require_once __DIR__ . '/includes/class-ui.php';

		// 加载 Smart Auto Upload Images 插件（库容器等）。
		$this->load_smart_aui_plugin();

		// 职责类装配。
		$this->settings  = new W2P_SmartAUI_Settings( $this );
		$this->processor = new W2P_SmartAUI_Content_Processor( $this );
		$this->ajax      = new W2P_SmartAUI_Ajax( $this );
		$this->ui        = new W2P_SmartAUI_UI( $this );

		// 同步设置 (CSF -> Legacy Option)
		add_action( 'csf_w2p_settings_saved', array( $this->settings, 'sync_settings' ) );

		// 添加进度可视化
		add_action( 'admin_enqueue_scripts', array( $this->ui, 'enqueue_progress_ui_scripts' ) );
		add_action( 'admin_footer', array( $this->ui, 'render_progress_ui_template' ) );

		// 移除原生菜单
		add_action( 'admin_menu', array( $this->ui, 'remove_native_admin_menu' ), 999 );

		// 添加自动设置封面功能
		add_action( 'save_post', array( $this->processor, 'auto_set_featured_image' ), 20, 2 );

		// 添加AJAX处理
		add_action( 'wp_ajax_w2p_smart_aui_get_progress', array( $this->ajax, 'ajax_get_progress' ) );
		add_action( 'wp_ajax_w2p_smart_aui_process_content', array( $this->ajax, 'ajax_process_content' ) );
		add_action( 'wp_ajax_w2p_smart_aui_process_all', array( $this->ajax, 'ajax_process_all' ) );
		add_action( 'wp_ajax_w2p_smart_aui_get_settings', array( $this->ajax, 'ajax_get_settings' ) );
		add_action( 'wp_ajax_w2p_smart_aui_bulk_process', array( $this->ajax, 'ajax_bulk_process' ) );
		// 单图多线程抓取接口（仅负责下载与附件创建，不直接修改文章内容）
		add_action( 'wp_ajax_w2p_smart_aui_download_image', array( $this->ajax, 'ajax_download_image' ) );
		// 视频下载接口
		add_action( 'wp_ajax_w2p_smart_aui_download_video', array( $this->ajax, 'ajax_download_video' ) );

		// 批量处理辅助接口
		add_action( 'wp_ajax_w2p_smart_aui_get_post_details', array( $this->ajax, 'ajax_get_post_details' ) );
		add_action( 'wp_ajax_w2p_smart_aui_save_post_content', array( $this->ajax, 'ajax_save_post_content' ) );
		add_action( 'wp_ajax_w2p_smart_aui_clear_failed_logs', array( $this->ajax, 'ajax_clear_failed_logs' ) );
		add_action( 'wp_ajax_w2p_smart_aui_get_failed_logs', array( $this->ajax, 'ajax_get_failed_logs' ) );
		add_action( 'wp_ajax_w2p_smart_aui_get_attachment_id', array( $this->ajax, 'ajax_get_attachment_id' ) );
	}

	/**
	 * 加载 Smart Auto Upload Images 插件
	 *
	 * @return void
	 */
	private function load_smart_aui_plugin() {
		$plugin_file = __DIR__ . '/library/smart-auto-upload-images.php';

		if ( ! file_exists( $plugin_file ) ) {
			return; // 插件文件不存在，跳过加载
		}

		// 定义常量（如果还没定义）
		if ( ! defined( 'SMART_AUI_VERSION' ) ) {
			define( 'SMART_AUI_VERSION', '1.2.1' );
			define( 'SMART_AUI_PLUGIN_FILE', $plugin_file );
			define( 'SMART_AUI_PLUGIN_DIR', dirname( $plugin_file ) . '/' );
			define( 'SMART_AUI_PLUGIN_URL', plugins_url( '/', $plugin_file ) );
			define( 'SMART_AUI_PLUGIN_BASENAME', plugin_basename( $plugin_file ) );
		}

		// 加载插件
		$autoload_file = SMART_AUI_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $autoload_file ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Smart Auto Upload Images: 请在 smart-auto-upload-images 目录运行 composer install', 'wp-genius' );
					echo '</p></div>';
				}
			);
			return;
		}

		require_once SMART_AUI_PLUGIN_DIR . 'vendor-prefixed/autoload.php';
		require_once SMART_AUI_PLUGIN_DIR . 'vendor/autoload.php';
		require_once SMART_AUI_PLUGIN_DIR . 'src/utils.php';

		// 加载容器辅助函数
		require_once __DIR__ . '/container-helper.php';

		// 加载配置钩子
		require_once __DIR__ . '/config-hooks.php';

		// 加载进度跟踪器
		require_once __DIR__ . '/progress-tracker.php';

		// 加载扩展的 ImageProcessor
		require_once __DIR__ . '/ImageProcessorExtended.php';

		// 加载视频下载器
		require_once __DIR__ . '/VideoDownloader.php';

		// 初始化插件组件
		$container = \SmartAutoUploadImages\get_container();
		$container->set( 'plugin', new \SmartAutoUploadImages\Plugin() );
		$container->set( 'logger', new \SmartAutoUploadImages\Utils\Logger() );
		$container->set( 'settings_manager', new \SmartAutoUploadImages\Admin\SettingsManager() );

		$container->set( 'failed_images_manager', new \SmartAutoUploadImages\Utils\FailedImagesManager() );

		// 使用扩展的 ImageProcessor 替代原版
		$container->set( 'image_processor', new \SmartAutoUploadImages\Services\ImageProcessorExtended() );

		$container->set( 'image_downloader', new \SmartAutoUploadImages\Services\ImageDownloader() );
	}

	/**
	 * Module Activation Hook
	 *
	 * @return void
	 */
	public function activate() {
		// 禁用旧的auto-upload-images-module
		$modules = get_option( 'word2posts_modules', array() );
		if ( isset( $modules['auto-upload-images-module'] ) ) {
			$modules['auto-upload-images-module'] = false;
		}
		update_option( 'word2posts_modules', $modules );

		do_action( 'w2p_smart_aui_activated' );
	}

	/**
	 * Module Deactivation Hook
	 *
	 * @return void
	 */
	public function deactivate() {
		do_action( 'w2p_smart_aui_deactivated' );
	}

	// ---------------------------------------------------------------------
	// 兼容委托：保持公共 API 签名，逻辑转发到职责类。
	// ---------------------------------------------------------------------

	/**
	 * Get module settings (flattened).
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( $this->settings ) {
			return $this->settings->get_settings();
		}
		return parent::get_settings();
	}

	/**
	 * Register module settings.
	 *
	 * @return array
	 */
	public function register_settings() {
		if ( $this->settings ) {
			return $this->settings->register_settings();
		}
		return include plugin_dir_path( __FILE__ ) . 'options.php';
	}

	/**
	 * Sync CSF settings to legacy option.
	 *
	 * @param array $w2p_settings Global settings.
	 * @return void
	 */
	public function sync_settings( $w2p_settings ) {
		if ( $this->settings ) {
			$this->settings->sync_settings( $w2p_settings );
		}
	}

	/**
	 * Auto set featured image.
	 *
	 * @param int           $post_id Post ID.
	 * @param WP_Post|null  $post    Post object.
	 * @return void
	 */
	public function auto_set_featured_image( $post_id, $post = null ) {
		if ( $this->processor ) {
			$this->processor->auto_set_featured_image( $post_id, $post );
		}
	}

	/**
	 * Get attachment ID from URL.
	 *
	 * @param string $image_url Image URL.
	 * @return int|false
	 */
	public function get_attachment_id_from_url( $image_url ) {
		if ( $this->processor ) {
			return $this->processor->get_attachment_id_from_url( $image_url );
		}
		return false;
	}

	/**
	 * AJAX: Get Progress.
	 *
	 * @return void
	 */
	public function ajax_get_progress() {
		$this->ajax->ajax_get_progress();
	}

	/**
	 * AJAX: Process Content.
	 *
	 * @return void
	 */
	public function ajax_process_content() {
		$this->ajax->ajax_process_content();
	}

	/**
	 * AJAX: Download Single Image.
	 *
	 * @return void
	 */
	public function ajax_download_image() {
		$this->ajax->ajax_download_image();
	}

	/**
	 * AJAX: Bulk Process.
	 *
	 * @return void
	 */
	public function ajax_bulk_process() {
		$this->ajax->ajax_bulk_process();
	}

	/**
	 * AJAX: Process All.
	 *
	 * @return void
	 */
	public function ajax_process_all() {
		$this->ajax->ajax_process_all();
	}

	/**
	 * AJAX: Get Settings.
	 *
	 * @return void
	 */
	public function ajax_get_settings() {
		$this->ajax->ajax_get_settings();
	}

	/**
	 * AJAX: Get Post Details.
	 *
	 * @return void
	 */
	public function ajax_get_post_details() {
		$this->ajax->ajax_get_post_details();
	}

	/**
	 * AJAX: Save Post Content.
	 *
	 * @return void
	 */
	public function ajax_save_post_content() {
		$this->ajax->ajax_save_post_content();
	}

	/**
	 * AJAX: Get Failed Logs.
	 *
	 * @return void
	 */
	public function ajax_get_failed_logs() {
		$this->ajax->ajax_get_failed_logs();
	}

	/**
	 * AJAX: Clear Failed Logs.
	 *
	 * @return void
	 */
	public function ajax_clear_failed_logs() {
		$this->ajax->ajax_clear_failed_logs();
	}

	/**
	 * AJAX: Download Video.
	 *
	 * @return void
	 */
	public function ajax_download_video() {
		$this->ajax->ajax_download_video();
	}

	/**
	 * AJAX: Get Attachment ID.
	 *
	 * @return void
	 */
	public function ajax_get_attachment_id() {
		$this->ajax->ajax_get_attachment_id();
	}
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'SmartAUIModule', false ) ) {
	class_alias( 'W2P_SmartAUIModule', 'SmartAUIModule' );
}
