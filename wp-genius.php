<?php
/**
 * Plugin Name: WP Genius
 * Description: Import Word documents and publish their chapters as WordPress posts (WP Genius).
 * Version: 1.0.1
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
require_once plugin_dir_path(__FILE__) . 'includes/class-logger.php';

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
    $core_modules = array('word-publish', 'image-watermark', 'smart-aui', 'clipboard-image-upload', 'system-health', 'media-turbo', 'seo-linker', 'ai-assistant');
    
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
            strpos($screen->id, 'wp-genius-settings') !== false ||
            strpos($screen->id, 'wp-genius') !== false ||
            in_array($screen->id, ['post', 'edit-post', 'tools', 'edit-page', 'page']) ||
            in_array($screen->base, ['post', 'edit', 'upload'])
        )) {
            $should_load = true;
        }
        
        if ($should_load) {
            wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
            wp_enqueue_script('jquery-ui-tabs');
            
            // Enqueue FontAwesome 6 Free from CDN
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');

            $suffix = ''; // Force uncompressed resources for auditing

            // 注册核心样式表
            wp_register_style('w2p-core-css', plugin_dir_url(__FILE__) . "assets/css/modules/core.css");

            // 注册模块化 CSS
            wp_register_style('w2p-admin-dashboard', plugin_dir_url(__FILE__) . "assets/css/modules/admin-dashboard.css", array('w2p-core-css'));
            wp_register_style('w2p-smart-auto-upload', plugin_dir_url(__FILE__) . "assets/css/modules/smart-auto-upload.css", array('w2p-core-css'));
            wp_register_style('w2p-watermark-style', plugin_dir_url(WP_GENIUS_FILE) . 'assets/css/modules/core.css', array('w2p-core-css'));
            wp_register_style('w2p-system-health', plugin_dir_url(__FILE__) . "assets/css/modules/system-health.css", array('w2p-core-css'));
            wp_register_style('w2p-auto-publish', plugin_dir_url(__FILE__) . "assets/css/modules/auto-publish.css", array('w2p-core-css'));
            wp_register_style('w2p-frontend-enhancements', plugin_dir_url(__FILE__) . "assets/css/modules/frontend-enhancements.css", array('w2p-core-css'));
            wp_register_style('w2p-smtp-mailer', plugin_dir_url(__FILE__) . "assets/css/modules/smtp-mailer.css", array('w2p-core-css'));

            // 统一加载核心样式和仪表盘样式
            wp_enqueue_style('w2p-core-css');
            wp_enqueue_style('w2p-admin-dashboard');
            
            // Enqueue range slider script for settings pages
            wp_enqueue_script('w2p-range-slider');

            // 如果是设置页面，加载所有模块样式
            if (strpos($screen->id, 'wp-genius-settings') !== false) {
                wp_enqueue_style('w2p-smart-auto-upload');
                wp_enqueue_style('w2p-system-health');
                wp_enqueue_style('w2p-auto-publish');
                wp_enqueue_style('w2p-frontend-enhancements');
                wp_enqueue_style('w2p-smtp-mailer');
            }

            // 向后兼容：保留 word-to-posts-css 句柄，指向 core.css
            wp_register_style('word-to-posts-css', plugin_dir_url(__FILE__) . "assets/css/modules/core.css");

            // 注册 Admin UI (Global Notifications)
            wp_register_style('w2p-admin-ui', plugin_dir_url(__FILE__) . 'assets/css/w2p-admin-ui.css', array(), '1.0.0');
            wp_register_script('w2p-admin-ui', plugin_dir_url(__FILE__) . 'assets/js/w2p-admin-ui.js', array('jquery'), '1.0.0', true);
            
            wp_localize_script('w2p-admin-ui', 'w2p_ui_i18n', array(
                'confirm'       => __('Confirm', 'wp-genius'),
                'cancel'        => __('Cancel', 'wp-genius'),
                'confirm_title' => __('Confirmation', 'wp-genius'),
                'settings_saved'=> __('Settings saved successfully!', 'wp-genius'),
            ));

            wp_enqueue_style('w2p-admin-ui');
            wp_enqueue_script('w2p-admin-ui');

            // Register FontAwesome Icons Data Script
            wp_register_script('w2p-fa-icons', plugin_dir_url(__FILE__) . "assets/js/w2p-fa-icons.js", array('jquery'), '1.0.0', true);
            wp_register_script('w2p-range-slider', plugin_dir_url(__FILE__) . "assets/js/range-slider.js", array('jquery'), '1.0.0', true);

            // 注册核心 JS 和模块化 JS (Depend on w2p-admin-ui)
            wp_register_script('w2p-core-js', plugin_dir_url(__FILE__) . "assets/js/modules/core.js", array('jquery', 'w2p-admin-ui'), '1.0.0', true);
            wp_register_script('w2p-ai-assistant', plugin_dir_url(__FILE__) . "assets/js/modules/ai-assistant.js", array('w2p-core-js'), '1.0.0', true);
            wp_register_script('w2p-auto-publish', plugin_dir_url(__FILE__) . "assets/js/modules/auto-publish.js", array('w2p-core-js'), '1.0.0', true);
            wp_register_script('w2p-clipboard-upload', plugin_dir_url(__FILE__) . "assets/js/modules/clipboard-upload.js", array('w2p-core-js'), '1.0.0', true);
            wp_register_script('w2p-media-turbo', plugin_dir_url(__FILE__) . "assets/js/modules/media-turbo.js", array('w2p-core-js'), '1.0.0', true);
            wp_register_script('w2p-system-health', plugin_dir_url(__FILE__) . "assets/js/modules/system-health.js", array('w2p-core-js', 'w2p-fa-icons'), '1.0.0', true); // Added w2p-fa-icons dependency
            wp_register_script('w2p-smart-auto-upload', plugin_dir_url(__FILE__) . "assets/js/smart-auto-upload-progress-ui.js", array('w2p-core-js'), '1.0.0', true);
            wp_register_script('w2p-image-watermark', plugin_dir_url(__FILE__) . "assets/js/modules/image-watermark.js", array('w2p-core-js'), '1.0.0', true);
            // 向后兼容：保留 w2p-modules-unified 句柄
            wp_register_script('w2p-modules-unified', plugin_dir_url(__FILE__) . "assets/js/modules/core.js", array('jquery', 'w2p-admin-ui'), '1.0.0', true);

            wp_enqueue_script('word-to-posts-js', plugin_dir_url(__FILE__) . 'assets/js/word-to-posts.js', array('jquery'), null, true);
            wp_enqueue_script('w2p-admin-modules-js', plugin_dir_url(__FILE__) . 'assets/js/admin-modules.js', array('jquery', 'w2p-admin-ui'), null, true);
            
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