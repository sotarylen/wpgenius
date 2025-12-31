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
		add_action( 'wp_ajax_w2p_smart_aui_process_all', [ $this, 'ajax_process_all' ] );
		add_action( 'wp_ajax_w2p_smart_aui_get_settings', [ $this, 'ajax_get_settings' ] );
		add_action( 'wp_ajax_w2p_smart_aui_bulk_process', [ $this, 'ajax_bulk_process' ] );
		// 单图多线程抓取接口（仅负责下载与附件创建，不直接修改文章内容）
		add_action( 'wp_ajax_w2p_smart_aui_download_image', [ $this, 'ajax_download_image' ] );
		
		// 批量处理辅助接口
		add_action( 'wp_ajax_w2p_smart_aui_get_post_details', [ $this, 'ajax_get_post_details' ] );
		add_action( 'wp_ajax_w2p_smart_aui_save_post_content', [ $this, 'ajax_save_post_content' ] );
		add_action( 'wp_ajax_w2p_smart_aui_clear_failed_logs', [ $this, 'ajax_clear_failed_logs' ] );
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
		if ( ! isset( $core_settings['concurrent_threads'] ) ) {
			$core_settings['concurrent_threads'] = 4;
		}
		if ( ! isset( $core_settings['max_retries'] ) ) {
			$core_settings['max_retries'] = 3;
		}
		
		update_option( 'smart_aui_settings', $core_settings );
	}

	/**
	 * Enqueue Progress UI Scripts
	 */
	public function enqueue_progress_ui_scripts( $hook ) {
        // Monitor hook for specific pages
        $is_settings_page = isset( $_GET['page'] ) && $_GET['page'] === 'wp-genius-settings';
        
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php', 'edit.php' ] ) && ! $is_settings_page ) {
			return;
		}

		$settings = get_option( 'smart_aui_settings', [] );
		
		// 确保设置有默认值
		if ( ! isset( $settings['show_progress_ui'] ) ) {
			$settings['show_progress_ui'] = true;
		}
		if ( ! isset( $settings['concurrent_threads'] ) ) {
			$settings['concurrent_threads'] = 4;
		}
		if ( ! isset( $settings['max_retries'] ) ) {
			$settings['max_retries'] = 3;
		}
		
		// 注释掉此检查，始终加载脚本，让JS内部决定是否显示UI
		// if ( empty( $settings['show_progress_ui'] ) ) {
		// 	return;
		// }

		// 使用WP_GENIUS_FILE常量计算插件根目录URL
		$plugin_url = plugin_dir_url( WP_GENIUS_FILE );
		
		wp_enqueue_script(
			'w2p-smart-aui-progress',
			$plugin_url . 'assets/js/smart-auto-upload-progress-ui.js',
			[ 'jquery' ],
			time(), // Force cache bust for debugging
			true
		);

		wp_localize_script(
			'w2p-smart-aui-progress',
			'w2pSmartAuiParams',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'w2p_smart_aui_progress' ),
				'debug' => WP_DEBUG,
				'settings' => $settings, // 直接传递设置，避免AJAX竞争
				'i18n' => [
					'confirmCancel' => __( 'Are you sure you want to cancel the image upload?', 'wp-genius' ),
					'confirmSkip' => __( 'Are you sure you want to stop the current image capture and publish the article directly?\n\nNote: Successfully captured images will be replaced, failed images will keep their original URLs.', 'wp-genius' ),
					'statusStopped' => __( 'Capture stopped, preparing to publish...', 'wp-genius' ),
					'statusPreparing' => __( 'Preparing to process batch posts...', 'wp-genius' ),
					'statusPreparingPublish' => __( 'Preparing to process and publish batch posts...', 'wp-genius' ),
					'statusProcessing' => __( '📝 Processing', 'wp-genius' ),
					'statusProcessAndPublish' => __( '📝 Process and Publish', 'wp-genius' ),
					'statusProcessAndDraft' => __( '📝 Process and Set as Draft', 'wp-genius' ),
					'statusProcessAndPending' => __( '📝 Process and Set as Pending', 'wp-genius' ),
					'statusProcessAndPrivate' => __( '📝 Process and Set as Private', 'wp-genius' ),
					'completeAll' => __( '🎉 All batch processing completed!', 'wp-genius' ),
					'completePublished' => __( '🎉 All posts have been processed and published!', 'wp-genius' ),
					'completeDraft' => __( '🎉 All posts have been processed and set as drafts!', 'wp-genius' ),
					'completePending' => __( '🎉 All posts have been processed and set as pending!', 'wp-genius' ),
					'completePrivate' => __( '🎉 All posts have been processed and set as private!', 'wp-genius' ),
					'processingImages' => __( 'Processing external images in parallel...', 'wp-genius' ),
					'allComplete' => __( '✅ Image processing complete! Saving...', 'wp-genius' ),
				],
			]
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
        
        // Only enqueue script if the actual JS file exists
        if (file_exists(SMART_AUI_PLUGIN_DIR . 'dist/js/admin-settings.js')) {
            wp_enqueue_script(
                'smart-aui-admin-settings',
                SMART_AUI_PLUGIN_URL . 'dist/js/admin-settings.js',
                $asset_file['dependencies'],
                $asset_file['version'],
                true
            );
        }

        // 确保 wp-components 样式已加载
        wp_enqueue_style( 'wp-components' );

        $css_asset_path = SMART_AUI_PLUGIN_DIR . 'dist/css/admin-settings-style.asset.php';
        if ( file_exists( $css_asset_path ) ) {
            $css_asset = include $css_asset_path;
            // Only enqueue style if the actual CSS file exists
            if (file_exists(SMART_AUI_PLUGIN_DIR . 'dist/css/admin-settings-style.css')) {
                wp_enqueue_style(
                    'smart-aui-admin-settings-style',
                    SMART_AUI_PLUGIN_URL . 'dist/css/admin-settings-style.css',
                    [ 'wp-components' ],
                    $css_asset['version']
                );
            }
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
		if ( ! $screen ) {
			return;
		}

		// 允许在文章编辑页面和文章列表页面加载
		$allowed_bases = [ 'post', 'edit' ];
		if ( ! in_array( $screen->base, $allowed_bases ) ) {
			return;
		}

		// 注释掉此检查，始终渲染模板，让JS决定是否显示
		// $settings = get_option( 'smart_aui_settings', [] );
		// if ( empty( $settings['show_progress_ui'] ) ) {
		// 	return;
		// }

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
	
		// 只处理 post 类型，跳过 attachment 和其他类型
		if ( ! $post || $post->post_type !== 'post' ) {
			return;
		}
	
		// 检查 REST API 设置：如果是 REST API 请求且禁用了 REST API 支持，则跳过
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && empty( $settings['process_images_on_rest_api'] ) ) {
			return;
		}
	
		// 如果文章正在被移动到回收站，跳过处理
		if ( isset( $post->post_status ) && 'trash' === $post->post_status ) {
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
		
		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( $_POST['target_url'] ) : '';
		
		// Process content
		// Note: Actions hooked in ImageProcessorExtended will handle progress updates
		$processed_content = $processor->process_post_content( $content, $post_data, $target_url );
		
		// Get final progress status
		$progress = W2P_Smart_AUI_Progress_Tracker::get_progress( null, $process_id );
		
		$response = [
			'processed_content' => $processed_content ? $processed_content : $content,
			'stats' => $progress
		];
		
		wp_send_json_success( $response );
	}
	
	/**
	 * AJAX Download Single Image (Multi-thread friendly)
	 *
	 * 仅负责下载远程图片并创建媒体库附件，不直接修改文章内容。
	 * 前端在收到返回数据后负责在编辑器内容中替换 URL，从而避免并发修改文章内容带来的竞态问题。
	 */
	public function ajax_download_image() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		$post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( wp_unslash( $_POST['process_id'] ) ) : '';

		if ( ! $post_id || empty( $image_url ) ) {
			wp_send_json_error(
				[
					'message' => 'Invalid request',
				]
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error(
				[
					'message' => 'Post not found',
				]
			);
		}

        // Check post status - do not process if in trash or auto-draft (likely being deleted or just created)
        if ( in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) ) {
            wp_send_json_error( 'Post is in trash or auto-draft, skipping image download.' );
        }

		$post_data = [
			'ID'           => $post->ID,
			'post_content' => $post->post_content,
			'post_title'   => $post->post_title,
			'post_status'  => $post->post_status,
		];

		// 本地图片直接跳过
		$settings  = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url  = ! empty( $settings['base_url'] ) ? $settings['base_url'] : site_url();
		$site_url  = site_url();

		if ( strpos( $image_url, $base_url ) === 0 || strpos( $image_url, $site_url ) === 0 ) {
			wp_send_json_success(
				[
					'source_url'     => $image_url,
					'downloaded_url' => $image_url,
					'skipped'        => true,
					'process_id'     => $process_id,
				]
			);
		}

		// [FIX 8] 检查域名是否被排除
		$container  = \SmartAutoUploadImages\get_container();
		$validator  = new \SmartAutoUploadImages\Services\ImageValidator();
		$validation = $validator->validate_image_url( $image_url, $post_data );
		
		if ( is_wp_error( $validation ) && $validation->get_error_code() === 'excluded_domain' ) {
			wp_send_json_success(
				[
					'source_url'     => $image_url,
					'downloaded_url' => $image_url,
					'skipped'        => true,
					'process_id'     => $process_id,
					'message'        => 'Domain excluded',
				]
			);
		}

		$downloader = $container->get( 'image_downloader' );

		// 读取重试次数配置并进行限制，避免死循环
		$settings    = \SmartAutoUploadImages\Plugin::get_settings();
		$max_retries = isset( $settings['max_retries'] ) ? max( 0, min( 10, (int) $settings['max_retries'] ) ) : 3;
		$attempt     = 0;
		$result      = null;

		do {
			$attempt++;

			try {
				$result = $downloader->download_image(
					[
						'url'   => $image_url,
						'alt'   => '',
						'title' => '',
					],
					$post_data
				);
			} catch ( \Throwable $e ) {
				$result = new \WP_Error( 'smart_aui_download_exception', $e->getMessage() );
			}

			if ( ! is_wp_error( $result ) ) {
				break;
			}

			// If the image has previously failed, don't retry, just skip it according to objective
			if ( $result->get_error_code() === 'previously_failed' ) {
				break;
			}

			if ( $attempt > $max_retries ) {
				break;
			}

			$delay = min( $attempt, 3 );
			sleep( $delay );
		} while ( $attempt <= $max_retries );

		if ( is_wp_error( $result ) ) {
		// If it was skipped because it previously failed, return SUCCESS with skipped=true
		if ( $result->get_error_code() === 'previously_failed' ) {
			wp_send_json_success(
				[
					'source_url'     => $image_url,
					'downloaded_url' => $image_url,
					'skipped'        => true,
					'process_id'     => $process_id,
					'message'        => 'Previously failed, skipped gracefully',
				]
			);
		}

		// Only add to failed list after all retries are exhausted
		// This ensures we don't mark URLs as failed on first network hiccup
		$failed_manager = $container->get( 'failed_images_manager' );
		if ( $failed_manager ) {
			$failed_manager->add_failed_url( $image_url );
		}

		// 返回失败状态（不使用 wp_send_json_error，以免前端认为是 AJAX 错误）
		wp_send_json_success(
			[
				'source_url'     => $image_url,
				'downloaded_url' => $image_url,
				'failed'         => true,
				'process_id'     => $process_id,
				'message'        => $result->get_error_message(),
			]
		);
	}

		// 使用与主处理流程一致的域名映射规则
		$settings = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url = trim( $settings['base_url'], '/' );
		$new_url  = $result['url'];

		if ( ! empty( $new_url ) && ! empty( $base_url ) ) {
			$new_url_parts = wp_parse_url( $new_url );
			if ( ! empty( $new_url_parts['path'] ) ) {
				$new_url = $base_url . $new_url_parts['path'];
			}
		}

		$response = [
			'source_url'     => $image_url,
			'downloaded_url' => $new_url,
			'attachment_id'  => isset( $result['attachment_id'] ) ? (int) $result['attachment_id'] : 0,
			'alt_text'       => isset( $result['alt_text'] ) ? $result['alt_text'] : '',
			'process_id'     => $process_id,
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
		if ( ! is_array( $progress ) ) {
			$progress = [];
		}
		$progress['content'] = get_post_field( 'post_content', $post_id );
		
		wp_send_json_success( [ 'stats' => $progress ] );
	}
	
	/**
	 * AJAX Process All Content (No Progress UI)
	 */
	public function ajax_process_all() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}
		
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$images = isset( $_POST['images'] ) ? $_POST['images'] : [];
		
		if ( empty( $content ) ) {
			wp_send_json_error( 'No content to process' );
		}
		
		// Create mock post data
		$post_data = [
			'ID' => $post_id,
			'post_content' => $content,
			'post_title' => get_the_title( $post_id ),
		];
		
		// Get processor
		$container = \SmartAutoUploadImages\get_container();
		$processor = $container->get( 'image_processor' );
		
		// Process all images in one request
		$processed_content = $processor->process_post_content( $content, $post_data );
		
		wp_send_json_success( [
			'processed_content' => $processed_content ? $processed_content : $content,
			'message' => 'All images processed successfully'
		] );
	}
	
	/**
	 * AJAX Get Settings
	 */
	public function ajax_get_settings() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );
		
		$settings = get_option( 'smart_aui_settings', [] );
		
		// 返回设置但不包含敏感信息
		$safe_settings = [
			'auto_set_featured_image' => isset( $settings['auto_set_featured_image'] ) ? (bool) $settings['auto_set_featured_image'] : true,
			'show_progress_ui' => isset( $settings['show_progress_ui'] ) ? (bool) $settings['show_progress_ui'] : true,
			'process_images_on_rest_api' => isset( $settings['process_images_on_rest_api'] ) ? (bool) $settings['process_images_on_rest_api'] : true,
			'domain_exclusions' => isset( $settings['domain_exclusions'] ) ? $settings['domain_exclusions'] : [],
			'featured_image_pattern' => isset( $settings['featured_image_pattern'] ) ? $settings['featured_image_pattern'] : '',
			'smart_filename_pattern' => isset( $settings['smart_filename_pattern'] ) ? $settings['smart_filename_pattern'] : '',
			'overwrite_existing_files' => isset( $settings['overwrite_existing_files'] ) ? (bool) $settings['overwrite_existing_files'] : false,
			'download_timeout' => isset( $settings['download_timeout'] ) ? intval( $settings['download_timeout'] ) : 30,
			'max_file_size' => isset( $settings['max_file_size'] ) ? intval( $settings['max_file_size'] ) : 5,
			'allowed_extensions' => isset( $settings['allowed_extensions'] ) ? $settings['allowed_extensions'] : [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ],
			'concurrent_threads' => isset( $settings['concurrent_threads'] ) ? intval( $settings['concurrent_threads'] ) : 4,
			'max_retries' => isset( $settings['max_retries'] ) ? intval( $settings['max_retries'] ) : 3,
		];
		
		wp_send_json_success( $safe_settings );
	}

	/**
	 * AJAX Get Post Details
	 */
	public function ajax_get_post_details() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid Post ID' );
		}
		
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( 'Post not found' );
		}
		
		wp_send_json_success( [
			'ID' => $post->ID,
			'post_title' => $post->post_title,
			'post_content' => $post->post_content
		] );
	}

	/**
	 * AJAX Save Post Content
	 */
	public function ajax_save_post_content() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_write_close();
		}

		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );
		
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$post_status = isset( $_POST['post_status'] ) ? sanitize_text_field( $_POST['post_status'] ) : null;
		
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid Post ID' );
		}
		
		// 准备更新数据
		$update_data = [
			'ID' => $post_id,
			'post_content' => $content,
		];
		
		// 如果指定了状态，同时更新状态
		if ( $post_status && in_array( $post_status, [ 'publish', 'draft', 'pending', 'private' ], true ) ) {
			$update_data['post_status'] = $post_status;
			
			// 如果是发布，需要更新发布时间
			if ( $post_status === 'publish' ) {
				$post = get_post( $post_id );
				if ( $post && in_array( $post->post_status, [ 'draft', 'pending', 'auto-draft' ], true ) ) {
					// 原来是草稿，现在发布，需要设置发布时间
					$update_data['post_date'] = current_time( 'mysql' );
					$update_data['post_date_gmt'] = current_time( 'mysql', 1 );
				}
			}
		}
		
		// 设置标记，告诉 wp_insert_post_data 钩子不要再次处理图片
		// 因为图片已经在前端处理完毕
		$_POST['w2p_smart_aui_processed'] = true;
		
		// 使用 wp_update_post() 以触发所有相关钩子（包括 auto_set_featured_image）
		// 这比直接用 $wpdb->update() 更符合 WordPress 规范
		$result = wp_update_post( $update_data, true );
		
		// 清除标记
		unset( $_POST['w2p_smart_aui_processed'] );
		
		// 检查错误
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'post_id' => $post_id
			] );
		}
		
		// 验证状态是否真的更新了
		$updated_post = get_post( $post_id );
		
		wp_send_json_success( [ 
			'updated' => true,
			'status' => $post_status,
			'actual_status' => $updated_post->post_status,
			'post_id' => $post_id
		] );
	}

	/**
	 * AJAX Clear Failed Logs
	 */
	public function ajax_clear_failed_logs() {
		check_ajax_referer( 'w2p_smart_aui_progress', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$manager = \SmartAutoUploadImages\get_container()->get( 'failed_images_manager' );
		$manager->clear_logs();

		wp_send_json_success( [ 'message' => 'Logs cleared' ] );
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
