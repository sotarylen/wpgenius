<?php
/**
 * Smart Auto Upload Images Module
 * 
 * 集成 Smart Auto Upload Images 插件作为 WP Genius 模块
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Smart Auto Upload Images Module Class
 */
class SmartAutoUploadImagesModule extends W2P_Abstract_Module {

	/**
	 * Module ID
	 *
	 * @return string
	 */
	public static function id() {
		return 'smart-auto-upload-images';
	}

	/**
	 * Module Name
	 *
	 * @return string
	 */
	public static function name() {
		return __( 'Smart Auto Upload Images', 'wp-genius' );
	}

	/**
	 * Module Description
	 *
	 * @return string
	 */
	public static function description() {
		return __( 'Automatically import external images to media library with progress visualization and auto-set featured image.', 'wp-genius' );
	}

	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
		// 加载 Smart Auto Upload Images 插件
		$this->load_smart_aui_plugin();
		
		// 注册设置
		$this->register_settings();
		
		// 添加进度可视化
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_progress_ui_scripts' ] );
		add_action( 'admin_footer', [ $this, 'render_progress_ui_template' ] );

        // 移除原生菜单并加载原生设置资源
        add_action( 'admin_menu', [ $this, 'remove_native_admin_menu' ], 999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_native_settings_assets' ] );
		
		// 添加自动设置封面功能
		add_action( 'save_post', [ $this, 'auto_set_featured_image' ], 20, 2 );
		
		// 添加AJAX处理
		add_action( 'wp_ajax_w2p_smart_aui_get_progress', [ $this, 'ajax_get_progress' ] );
		add_action( 'wp_ajax_w2p_smart_aui_process_content', [ $this, 'ajax_process_content' ] );
		add_action( 'wp_ajax_w2p_smart_aui_bulk_process', [ $this, 'ajax_bulk_process' ] );
	}

	/**
	 * 加载 Smart Auto Upload Images 插件
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
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Smart Auto Upload Images: 请在 smart-auto-upload-images 目录运行 composer install', 'wp-genius' );
				echo '</p></div>';
			});
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
		
		// 加载扩展的 ImageDownloader
		require_once __DIR__ . '/ImageDownloaderExtended.php';
		
		// 初始化插件组件
		$container = \SmartAutoUploadImages\get_container();
		$container->set( 'plugin', new \SmartAutoUploadImages\Plugin() );
		$container->set( 'logger', new \SmartAutoUploadImages\Utils\Logger() );
		$container->set( 'settings_manager', new \SmartAutoUploadImages\Admin\SettingsManager() );
		
		// 使用扩展的 ImageProcessor 替代原版
		$container->set( 'image_processor', new \SmartAutoUploadImages\Services\ImageProcessorExtended() );
		
		$container->set( 'image_downloader', new \SmartAutoUploadImages\Services\ImageDownloader() );
	}

	/**
	 * Register Module Settings
	 *
	 * @return void
	 */
	public function register_settings() {
		// Sync WP Genius settings with core Smart AUI settings
		$core_settings = get_option( 'smart_aui_settings', [] );
		
		// Set defaults for WP Genius features if not already set
		if ( ! isset( $core_settings['auto_set_featured_image'] ) ) {
			$core_settings['auto_set_featured_image'] = true;
		}
		if ( ! isset( $core_settings['show_progress_ui'] ) ) {
			$core_settings['show_progress_ui'] = true;
		}
		if ( ! isset( $core_settings['process_images_on_rest_api'] ) ) {
			$core_settings['process_images_on_rest_api'] = true;
		}
		
		update_option( 'smart_aui_settings', $core_settings );
	}

	/**
	 * Enqueue Progress UI Scripts
	 */
	public function enqueue_progress_ui_scripts( $hook ) {
		// 只在编辑页面加载
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php', 'edit.php' ] ) ) {
			return;
		}

		$settings = get_option( 'smart_aui_settings', [] );
		if ( empty( $settings['show_progress_ui'] ) ) {
			return;
		}

		wp_enqueue_script(
			'w2p-smart-aui-progress',
			plugins_url( 'progress-ui.js', __FILE__ ),
			[ 'jquery' ],
			'1.0.0',
			true
		);

		wp_localize_script(
			'w2p-smart-aui-progress',
			'w2pSmartAuiParams',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'w2p_smart_aui_progress' ),
			]
		);

		wp_enqueue_style(
			'w2p-smart-aui-progress',
			plugins_url( 'progress-ui.css', __FILE__ ),
			[],
			'1.0.0'
		);
	}

    /**
     * Remove Native Admin Menu
     */
    public function remove_native_admin_menu() {
        remove_submenu_page( 'options-general.php', 'smart-auto-upload-images' );
    }

    /**
     * Enqueue Native Settings Assets
     */
    public function enqueue_native_settings_assets( $hook ) {
        // 只在 WP Genius 设置页面加载
        // 检查 screen id 是否包含 wp-genius-settings
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'wp-genius-settings' ) === false ) {
            return;
        }

        // 确保插件已加载且常量定义
        if ( ! defined( 'SMART_AUI_PLUGIN_DIR' ) || ! defined( 'SMART_AUI_PLUGIN_URL' ) ) {
            return;
        }

        $asset_file_path = SMART_AUI_PLUGIN_DIR . 'dist/js/admin-settings.asset.php';
        if ( ! file_exists( $asset_file_path ) ) {
            return;
        }

        $asset_file = include $asset_file_path;
        
        wp_enqueue_script(
            'smart-aui-admin-settings',
            SMART_AUI_PLUGIN_URL . 'dist/js/admin-settings.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        // 确保 wp-components 样式已加载
        wp_enqueue_style( 'wp-components' );

        $css_asset_path = SMART_AUI_PLUGIN_DIR . 'dist/css/admin-settings-style.asset.php';
        if ( file_exists( $css_asset_path ) ) {
            $css_asset = include $css_asset_path;
            wp_enqueue_style(
                'smart-aui-admin-settings-style',
                SMART_AUI_PLUGIN_URL . 'dist/css/admin-settings-style.css',
                [ 'wp-components' ],
                $css_asset['version']
            );
        } else {
             // Fallback if asset file missing but css exists
             if (file_exists(SMART_AUI_PLUGIN_DIR . 'dist/css/admin-settings-style.css')) {
                wp_enqueue_style(
                    'smart-aui-admin-settings-style',
                    SMART_AUI_PLUGIN_URL . 'dist/css/admin-settings-style.css',
                    [ 'wp-components' ],
                    SMART_AUI_VERSION
                );
             }
        }
    }

	/**
	 * Render Progress UI Template
	 */
	public function render_progress_ui_template() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, [ 'post', 'edit' ] ) ) {
			return;
		}

		$settings = get_option( 'smart_aui_settings', [] );
		if ( empty( $settings['show_progress_ui'] ) ) {
			return;
		}

		include __DIR__ . '/progress-template.php';
	}

	/**
	 * Auto Set Featured Image
	 */
	public function auto_set_featured_image( $post_id, $post ) {
		// 检查是否启用
		$settings = get_option( 'smart_aui_settings', [] );
		if ( empty( $settings['auto_set_featured_image'] ) ) {
			return;
		}

		// 如果已有封面图，跳过
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		// 获取文章中所有的图片标签
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches );
		
		if ( empty( $matches[1] ) ) {
			return;
		}

		// 依次尝试设置每一张图片为封面，直到成功
		foreach ( $matches[1] as $image_url ) {
			$attachment_id = $this->get_attachment_id_from_url( $image_url );
			
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
				return; // 成功设置后退出循环
			}
		}
	}

	/**
	 * Get Attachment ID from URL
	 */
	private function get_attachment_id_from_url( $image_url ) {
		// 检查是否是本地图片
		$upload_dir = wp_upload_dir();
		if ( strpos( $image_url, $upload_dir['baseurl'] ) === false ) {
			return false;
		}

		// 通过URL查找附件ID
		$attachment_id = attachment_url_to_postid( $image_url );
		
		if ( ! $attachment_id ) {
			// 尝试通过文件名查找（处理 scaled 或 resized 图片）
			global $wpdb;
			$filename = basename( $image_url );
			
			// 如果 pathinfo 可用，提取文件名
			$path_info = pathinfo( $filename );
			$base_name_only = preg_replace( '/(-\d+x\d+|-scaled)$/i', '', $path_info['filename'] );
			
			$attachment_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( $base_name_only ) . '%'
			) );
		}

		return $attachment_id ? intval( $attachment_id ) : false;
	}

	/**
	 * AJAX Get Progress
	 */
	public function ajax_get_progress() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( $_POST['process_id'] ) : '';

		// 获取进度信息
		$progress = W2P_Smart_AUI_Progress_Tracker::get_progress( null, $process_id );

		if ( ! $progress ) {
			$progress = [
				'status' => 'idle',
				'total' => 0,
				'processed' => 0,
				'success' => 0,
				'failed' => 0,
				'current_url' => '',
			];
		}

		wp_send_json_success( $progress );
	}
	
	/**
	 * AJAX Process Content (Async)
	 */
	public function ajax_process_content() {
		// 关闭 session 写入，允许并发请求（解决进度条卡死问题）
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}
		
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( $_POST['process_id'] ) : '';
		
		if ( empty( $content ) ) {
			wp_send_json_error( 'No content to process' );
		}
		
		// Create mock post data
		$post_data = [
			'ID' => $post_id,
			'post_content' => $content,
			'post_title' => get_the_title( $post_id ),
		];
		
		// Set process ID for tracker
		if ( ! empty( $process_id ) ) {
			$tracker = W2P_Smart_AUI_Progress_Tracker::get_instance();
			$tracker->set_process_id( $process_id );
		}
		
		// Get processor
		$container = \SmartAutoUploadImages\get_container();
		$processor = $container->get( 'image_processor' );
		
		// Process content
		// Note: Actions hooked in ImageProcessorExtended will handle progress updates
		$processed_content = $processor->process_post_content( $content, $post_data );
		
		// Get final progress status
		$progress = W2P_Smart_AUI_Progress_Tracker::get_progress( null, $process_id );
		
		$response = [
			'processed_content' => $processed_content ? $processed_content : $content,
			'stats' => $progress
		];
		
		wp_send_json_success( $response );
	}
	
	/**
	 * AJAX Bulk Process (Server-side)
	 */
	public function ajax_bulk_process() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}
		
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( $_POST['process_id'] ) : '';
		
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid Post ID' );
		}
		
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( 'Post not found' );
		}
		
		// Set process ID for tracker
		if ( ! empty( $process_id ) ) {
			$tracker = W2P_Smart_AUI_Progress_Tracker::get_instance();
			$tracker->set_process_id( $process_id );
		}
		
		$container = \SmartAutoUploadImages\get_container();
		$processor = $container->get( 'image_processor' );
		
		$post_data = [
			'ID' => $post->ID,
			'post_content' => $post->post_content,
			'post_title' => $post->post_title,
			'post_status' => $post->post_status
		];
		
		$processed_content = $processor->process_post_content( $post->post_content, $post_data );
		
		if ( $processed_content !== false && $processed_content !== $post->post_content ) {
			// Update DB directly
			// Use wp_update_post with caution to avoid infinite loops, but here we just update content
			// And we must ensure we don't trigger our own save_post hook loop if possible
			// Actually our hook checks for DOING_AJAX so it might return early, which is GOOD.
			// But wait, our hook returns early on AJAX?
			// Plugin.php: 79: if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) { return $data; }
			// So wp_update_post won't trigger image processing again. Perfect.
			
			global $wpdb;
			$wpdb->update( 
				$wpdb->posts, 
				[ 'post_content' => $processed_content ], 
				[ 'ID' => $post_id ] 
			);
			
			// Clean post cache
			clean_post_cache( $post_id );
		}
		
		$progress = W2P_Smart_AUI_Progress_Tracker::get_progress( null, $process_id );
		
		wp_send_json_success( [ 'stats' => $progress ] );
	}

	/**
	 * Module Activation Hook
	 *
	 * @return void
	 */
	public function activate() {
		// 禁用旧的auto-upload-images-module
		$modules = get_option( 'word2posts_modules', [] );
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
}
