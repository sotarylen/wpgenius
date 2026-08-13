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

return [
    'module_id' => 'system-health',
    'id'     => 'system_health',
    'title'  => __('System Health', 'wp-genius'),
    'icon'   => 'fa-solid fa-heartbeat',
    'fields' => [
        [
            'type'    => 'content',
            'content' => (function() {
                $module_dir = plugin_dir_path(__FILE__);
                
                // Ensure Service is loaded
                if (file_exists($module_dir . 'cleanup-service.php')) {
                    require_once $module_dir . 'cleanup-service.php';
                }
                
                // Initialize Data
                $service = new SystemHealthCleanupService();
                $stats   = [
                    'revisions'     => '-',
                    'auto_drafts'   => '-',
                    'orphaned_meta' => '-',
                    'transients'    => '-',
                ];
                $categories = $service->get_categories();
                
                ob_start();

                // Load Views
                if (file_exists($module_dir . 'views/settings-page.php')) {
                    include $module_dir . 'views/settings-page.php';
                } else {
                     echo '<div class="w2p-notice w2p-notice-error"><p>' . esc_html__('Error: View file not found.', 'wp-genius') . '</p></div>';
                }

                if (file_exists($module_dir . 'views/js-templates.php')) {
                    include $module_dir . 'views/js-templates.php';
                }

                return ob_get_clean();
            })(),
        ],
    ],
];
