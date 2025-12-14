<?php
if (!defined('ABSPATH')) {
    exit;
}

class ImageWatermarkModule extends W2P_Abstract_Module {
    /**
     * @var W2P_Image_Watermark 插件实例
     */
    protected $plugin;
    
    /**
     * 返回模块唯一 ID
     */
    public static function id() {
        return 'image-watermark';
    }

    /**
     * 返回模块显示名称
     */
    public static function name() {
        return __('Image Watermark', 'wp-genius');
    }

    /**
     * 返回模块简短描述
     */
    public static function description() {
        return __('Automatically watermark images uploaded to WordPress Media Library and bulk watermark previously uploaded images.', 'wp-genius');
    }

    /**
     * 模块初始化
     */
    public function init() {
        // 定义插件常量
        $this->define_constants();

        // 加载必要的类文件
        $this->load_classes();

        // 初始化插件
        $this->initialize_plugin();

        // 注册钩子
        $this->register_hooks();
    }

    /**
     * 定义插件常量
     */
    private function define_constants() {
        define('W2P_IMAGE_WATERMARK_URL', plugin_dir_url(WP_GENIUS_FILE) . 'assets/modules/image-watermark/');
        define('W2P_IMAGE_WATERMARK_PATH', plugin_dir_path(WP_GENIUS_FILE) . 'assets/modules/image-watermark/');
        define('W2P_IMAGE_WATERMARK_BASENAME', plugin_basename(WP_GENIUS_FILE) . '/assets/modules/image-watermark/module.php');
        define('W2P_IMAGE_WATERMARK_REL_PATH', dirname(W2P_IMAGE_WATERMARK_BASENAME));
    }

    /**
     * 加载必要的类文件
     */
    private function load_classes() {
        require_once W2P_IMAGE_WATERMARK_PATH . 'includes/class-image-watermark.php';
        require_once W2P_IMAGE_WATERMARK_PATH . 'includes/class-update.php';
        require_once W2P_IMAGE_WATERMARK_PATH . 'includes/class-settings.php';
        require_once W2P_IMAGE_WATERMARK_PATH . 'includes/class-upload-handler.php';
        require_once W2P_IMAGE_WATERMARK_PATH . 'includes/class-actions-controller.php';
    }

    /**
     * 初始化插件
     */
    private function initialize_plugin() {
        // 实例化主类
        $this->plugin = W2P_Image_Watermark::instance();
    }

    /**
     * 注册钩子
     */
    private function register_hooks() {
        // 加载文本域
        add_action('init', [$this, 'load_textdomain']);
        
        // 注册设置
        add_action('admin_init', [$this, 'register_settings']);
        
        // 处理设置保存
        add_action('admin_post_word2posts_save_module_settings', [$this, 'save_settings']);
        
        // 加载管理员脚本和样式
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        
        // 加载媒体库
        add_action('wp_enqueue_media', [$this, 'wp_enqueue_media']);
    }

    /**
     * 加载文本域
     */
    public function load_textdomain() {
        load_plugin_textdomain('image-watermark', false, W2P_IMAGE_WATERMARK_REL_PATH . '/languages/');
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        // 设置将在 settings.php 中处理
    }

    /**
     * 启用模块时的操作
     */
    public function enable() {
        $this->plugin->activate_watermark();
    }

    /**
     * 禁用模块时的操作
     */
    public function disable() {
        $this->plugin->deactivate_watermark();
    }
    
    /**
     * 加载管理员脚本和样式
     */
    public function admin_enqueue_scripts($page) {
        // 模块设置页面的脚本和样式由主类处理
        // 这里不需要重复加载
    }
    
    /**
     * 加载媒体库
     */
    public function wp_enqueue_media() {
        // 模块设置页面的媒体库由主类处理
        // 这里不需要重复加载
    }
    
    /**
     * 保存模块设置
     */
    public function save_settings() {
        // 验证 nonce
        if (!isset($_POST['word2posts_module_nonce']) || !wp_verify_nonce($_POST['word2posts_module_nonce'], 'word2posts_save_module_settings')) {
            wp_die(__('Security check failed. Please try again.', 'wp-genius'));
        }
        
        // 验证用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-genius'));
        }
        
        // 验证模块 ID
        if (!isset($_POST['module_id']) || $_POST['module_id'] !== 'image-watermark') {
            wp_die(__('Invalid module ID.', 'wp-genius'));
        }
        
        // 获取设置验证类
        $settings_class = new W2P_Image_Watermark_Settings();
        
        // 获取当前选项
        $options = get_option('w2p_image_watermark_options', $this->plugin->defaults['options']);
        
        // 验证并保存选项
        $validated_options = $settings_class->validate_options($options);
        
        // 更新选项
        update_option('w2p_image_watermark_options', $validated_options);
        
        // 重定向回设置页面
        wp_redirect(admin_url('admin.php?page=wp-genius-modules'));
        exit;
    }
}