<?php
/**
 * System Health Module - CSF Options
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_dir = plugin_dir_path(__FILE__);
$settings_file = $module_dir . 'settings.php';

return [
    'module_id' => 'system-health',
    'id'     => 'system_health',
    'title'  => __('System Health', 'wp-genius'),
    'icon'   => 'fa-solid fa-heartbeat',
    'fields' => [
        [
            'type'    => 'content',
            'content' => (function() use ($settings_file) {
                // 确保 SystemHealthCleanupService 类被加载
                $service_file = plugin_dir_path(__FILE__) . 'cleanup-service.php';
                if (file_exists($service_file)) {
                    require_once $service_file;
                }
                
                if (file_exists($settings_file)) {
                    ob_start();
                    include $settings_file;
                    return ob_get_clean();
                }
                return '<div class="w2p-info-box"><p>' . __('System Health tools not available.', 'wp-genius') . '</p></div>';
            })(),
        ],
    ],
];
