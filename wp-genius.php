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

// Include module framework (abstracts, loader, admin settings)
require_once plugin_dir_path(__FILE__) . 'includes/abstract-module.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-module-loader.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-logger.php';

/**
 * Initialize the plugin
 */
function w2p_core_init() {
    try {
        load_plugin_textdomain('wp-genius', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize module loader
        $module_loader = new W2P_Module_Loader(plugin_dir_path(__FILE__) . 'includes/modules/');
        
        // Ensure core modules are enabled by default
        w2p_core_ensure_modules_enabled($module_loader);
        
        // Initialize admin settings manager
        $admin_settings = new W2P_Admin_Settings($module_loader);

        // Initialize and load enabled modules
        $module_loader->init();
    } catch (Exception $e) {
        error_log('WP Genius Init Error: ' . $e->getMessage());
        if (is_admin()) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="error"><p>' . sprintf(__('WP Genius Error: %s', 'wp-genius'), $e->getMessage()) . '</p></div>';
            });
        }
    }
}
add_action('plugins_loaded', 'w2p_core_init');

/**
 * Ensure core modules are enabled
 */
function w2p_core_ensure_modules_enabled($module_loader) {
    $enabled = get_option('word2posts_modules', array());
    
    // Core modules list - these should be enabled by default
    $core_modules = array(
        'smart-aui', 
        'system-health'
    );
    
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

/**
 * Register and enqueue core admin assets
 */
function w2p_core_enqueue_scripts() {
    try {
        $is_plugin_page = false;

        if (is_admin()) {
            $screen = get_current_screen();
            if ($screen) {
                $is_plugin_page = (
                    strpos($screen->id, 'wp-genius') !== false ||
                    in_array($screen->id, ['post', 'edit-post', 'tools', 'edit-page', 'page']) ||
                    in_array($screen->base, ['post', 'edit', 'upload'])
                );
            }
        } else {
            // Frontend: Enqueue core styles globally
            $is_plugin_page = true;
        }

        if ($is_plugin_page) {
            // External dependencies
            wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');

            // Core Styles
            wp_register_style('w2p-core-css', plugin_dir_url(__FILE__) . "assets/css/core.css");
            wp_enqueue_style('w2p-core-css');

            // Core Scripts
            wp_register_script('w2p-admin-ui', plugin_dir_url(__FILE__) . 'assets/js/w2p-admin-ui.js', array('jquery'), '1.0.0', true);
            wp_localize_script('w2p-admin-ui', 'w2p_ui_i18n', array(
                'confirm'       => __('Confirm', 'wp-genius'),
                'cancel'        => __('Cancel', 'wp-genius'),
                'confirm_title' => __('Confirmation', 'wp-genius'),
                'settings_saved'=> __('Settings saved successfully!', 'wp-genius'),
            ));
            wp_enqueue_script('w2p-admin-ui');

            // Helper Scripts
            wp_register_script('w2p-fa-icons', plugin_dir_url(__FILE__) . "assets/js/w2p-fa-icons.js", array('jquery'), '1.0.0', true);
            wp_register_script('w2p-range-slider', plugin_dir_url(__FILE__) . "assets/js/range-slider.js", array('jquery'), '1.0.0', true);
            wp_enqueue_script('w2p-range-slider');

            // Core Module JS
            wp_register_script('w2p-core-js', plugin_dir_url(__FILE__) . "assets/js/modules/core.js", array('jquery', 'w2p-admin-ui'), '1.0.0', true);
            wp_enqueue_script('w2p-core-js');
        }
    } catch (Exception $e) {
        error_log('WP Genius Enqueue Error: ' . $e->getMessage());
    }
}
add_action('admin_enqueue_scripts', 'w2p_core_enqueue_scripts');
add_action('wp_enqueue_scripts', 'w2p_core_enqueue_scripts');