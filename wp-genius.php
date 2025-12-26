<?php
/**
 * Plugin Name: WP Genius
 * Description: Import Word documents and publish their chapters as WordPress posts (WP Genius).
 * Version: 1.0
 * Author: Sotary
 * Text Domain: wp-genius
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin file constant
define('WP_GENIUS_FILE', __FILE__);

// Include the autoload file from Composer
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Include the main class
require_once plugin_dir_path(__FILE__) . 'includes/class-word-to-posts.php';

// Include module framework (abstracts, loader, admin settings)
require_once plugin_dir_path(__FILE__) . 'includes/abstract-module.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-module-loader.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-admin-settings.php';

// Include Languages 加载插件文本域，以便在插件中使用多语言
function word_to_posts_init() {
    try{
        load_plugin_textdomain('wp-genius', false, dirname(plugin_basename(__FILE__)) . '/languages');
        // 初始化模块加载器与设置管理
        $module_loader = new W2P_Module_Loader(plugin_dir_path(__FILE__) . 'includes/modules/');
        
        // 确保核心的Word发布模块默认启用
        word_to_posts_ensure_core_modules_enabled($module_loader);
        
        // 先实例化设置管理（它会在 admin_menu 时显示）
        $admin_settings = new W2P_Admin_Settings($module_loader);

        // 初始化插件主功能（向后兼容）
        $plugin = new WordToPosts();
        $plugin->run();

        // 初始化并加载启用的模块
        $module_loader->init();
    } catch (Exception $e) {
        error_log($e->getMessage());
        // 在WordPress管理后台显示错误信息
        error_log($e->getMessage());
        wp_die(__('An error occurred while initializing the plugin: ') . $e->getMessage());
    }
}
add_action('plugins_loaded', 'word_to_posts_init');

/**
 * 确保核心模块（Word发布）被启用
 */
function word_to_posts_ensure_core_modules_enabled($module_loader) {
    $enabled = get_option('word2posts_modules', array());
    
    // 核心模块列表 - 这些应该默认启用
    $core_modules = array('word-publish', 'image-watermark', 'smart-auto-upload-images');
    
    $changed = false;
    foreach ($core_modules as $module_id) {
        if (!isset($enabled[$module_id])) {
            $enabled[$module_id] = true;
            $changed = true;
        }
    }
    
    if ($changed) {
        update_option('word2posts_modules', $enabled);
    }
}

// 注册插件所需的CSS和JavaScript文件
function word_to_posts_enqueue_scripts() {
    try{
        $screen = get_current_screen();
        
        // 在插件相关页面加载脚本和样式
        $should_load = false;
        
        // 检查是否是插件设置页面
        if ($screen && (
            strpos($screen->id, 'word-to-posts') !== false ||
            strpos($screen->id, 'tools_page_word-to-posts-settings') !== false ||
            strpos($screen->id, 'wp-genius') !== false ||
            $screen->id === 'tools'
        )) {
            $should_load = true;
        }
        
        if ($should_load) {
            wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
            wp_enqueue_script('jquery-ui-tabs');
            
            // 统一加载CSS和JS - 所有样式已合并到style.css中
            wp_enqueue_style('word-to-posts-css', plugin_dir_url(__FILE__) . 'assets/css/style.css');
            wp_enqueue_script('word-to-posts-js', plugin_dir_url(__FILE__) . 'assets/js/word-to-posts.js', array('jquery'), null, true);
            wp_enqueue_script('w2p-admin-modules-js', plugin_dir_url(__FILE__) . 'assets/js/admin-modules.js', array('jquery'), null, true);
            
            // 将插件所需的参数传递给JavaScript文件
            wp_localize_script('word-to-posts-js', 'word_to_posts_params', [
                'starting_import' => __('Starting to import and publish chapters...', 'wp-genius'),
                'cleaning' => __('Cleaning uploads folder...', 'wp-genius'),
                'scanning' => __('Scanning uploads folder...', 'wp-genius'),
                'error' => __('Tips', 'wp-genius')
            ]);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        wp_die(__('An error occurred while enqueuing scripts: ') . $e->getMessage());
    }
}
add_action('admin_enqueue_scripts', 'word_to_posts_enqueue_scripts');

// 初始化阅读器
add_action('init', 'register_reader_shortcode');
function register_reader_shortcode() {
	add_shortcode('reader', 'reader_shortcode_handler');
    reader_enqueue_scripts();
}
function reader_shortcode_handler($atts) {
    ob_start();
	include(plugin_dir_path(__FILE__) . 'includes/templates/reader.php');
    return ob_get_clean();
 }

 function reader_enqueue_scripts() {
    // Enqueue any scripts or styles here
    wp_enqueue_style('reader-style', plugins_url('assets/css/style.css', __FILE__));
    wp_enqueue_script('reader-script', plugins_url('assets/js/reader.js', __FILE__), array('jquery'), null, true);
}
