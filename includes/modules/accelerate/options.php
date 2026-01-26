<?php
/**
 * Accelerate Module - CSF Options
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if (!defined('ABSPATH')) {
    exit;
}

$legacy = get_option('w2p_accelerate_settings', []);
$defaults = [
    // Admin Bar
    'accelerate_remove_admin_bar_wp_logo'         => true,
    'accelerate_remove_admin_bar_about'           => true,
    'accelerate_remove_admin_bar_comments'        => true,
    'accelerate_remove_admin_bar_new_content'     => true,
    'accelerate_remove_admin_bar_search'          => true,
    'accelerate_remove_admin_bar_updates'         => true,
    'accelerate_remove_admin_bar_appearance'      => true,
    'accelerate_remove_admin_bar_customize'       => true,
    'accelerate_remove_admin_bar_wporg'           => true,
    'accelerate_remove_admin_bar_documentation'   => true,
    'accelerate_remove_admin_bar_support_forums'  => true,
    'accelerate_remove_admin_bar_feedback'        => true,
    'accelerate_remove_admin_bar_view_site'       => true,
    
    // Dashboard Widgets
    'accelerate_remove_dashboard_activity'        => true,
    'accelerate_remove_dashboard_primary'         => true,
    'accelerate_remove_dashboard_secondary'       => true,
    'accelerate_remove_dashboard_site_health'     => false,
    'accelerate_remove_dashboard_right_now'       => true,
    'accelerate_remove_dashboard_quick_draft'     => true,
    
    // General
    'accelerate_disable_months_dropdown'          => true,
    'accelerate_enable_local_avatar'              => true,
    'accelerate_enable_upload_rename'             => true,
    'accelerate_upload_rename_pattern'            => '{timestamp}_{sanitized}',
    'accelerate_enable_delete_with_images'        => true,
    
    // Update Behaviors
    'accelerate_disable_auto_update_plugin'       => true,
    'accelerate_disable_auto_update_theme'        => true,
    'accelerate_remove_wp_update_plugins'         => true,
    'accelerate_remove_wp_update_themes'          => true,
    'accelerate_remove_maybe_update_core'         => true,
    'accelerate_remove_maybe_update_plugins'      => true,
    'accelerate_remove_maybe_update_themes'       => true,
    'accelerate_hide_plugin_notices'              => true,
    'accelerate_block_acf_updates'                => true,
    'accelerate_block_external_http'              => true,
];

// Ensure keys match new prefixed format if legacy data uses old keys?
// CSF usually handles default values if the key is missing in DB.
// Since we are using a unified w2p_settings array in the future, 
// for now this file returns the SECTION definition.
// The actual values are stored in 'w2p_settings' (if unified) or 'w2p_accelerate_settings' (legacy mode module.php currently uses).
// Checking module.php: `settings_key` returns `w2p_accelerate_settings`.
// So we should stick to that scope.

return [
    'module_id' => 'accelerate',
    'id'     => 'accelerate',
    'title'  => __('Accelerate', 'wp-genius'),
    'icon'   => 'fa-solid fa-gauge-high',
    'fields' => [
        // [
        //     'id'      => '_subheading_optimization',
        //     'type'    => 'subheading',
        //     'content' => __('Optimize WordPress admin interface and performance', 'wp-genius'),
        // ],
        
        // Admin Bar Section
        [
            'id'      => '_subheading_admin_bar',
            'type'    => 'subheading',
            'content' => __('Admin Bar Items', 'wp-genius'),
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_wp_logo',
            'type'    => 'switcher',
            'title'   => __('WordPress Logo', 'wp-genius'),
            'label'   => __('Removes the WP logo from the top left.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_about',
            'type'    => 'switcher',
            'title'   => __('About WordPress', 'wp-genius'),
            'label'    => __('Removes the "About WordPress" link.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_comments',
            'type'    => 'switcher',
            'title'   => __('Comments', 'wp-genius'),
            'label'    => __('Removes the comments moderation icon.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_new_content',
            'type'    => 'switcher',
            'title'   => __('New Content Menu', 'wp-genius'),
            'label'    => __('Removes the "+ New" menu.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_search',
            'type'    => 'switcher',
            'title'   => __('Search', 'wp-genius'),
            'label'    => __('Removes the search bar from admin bar.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_updates',
            'type'    => 'switcher',
            'title'   => __('Updates Notification', 'wp-genius'),
            'label'    => __('Removes the updates notification icon.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_appearance',
            'type'    => 'switcher',
            'title'   => __('Appearance', 'wp-genius'),
            'label'    => __('Removes the Appearance menu.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_customize',
            'type'    => 'switcher',
            'title'   => __('Customize', 'wp-genius'),
            'label'    => __('Removes the Customize menu.', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_wporg',
            'type'    => 'switcher',
            'title'   => __('WordPress.org', 'wp-genius'),
            'label'    => __('Removes WordPress.org external links.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_documentation',
            'type'    => 'switcher',
            'title'   => __('Documentation', 'wp-genius'),
            'label'    => __('Removes documentation links.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_support_forums',
            'type'    => 'switcher',
            'title'   => __('Support Forums', 'wp-genius'),
            'label'    => __('Removes support forum links.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_feedback',
            'type'    => 'switcher',
            'title'   => __('Feedback', 'wp-genius'),
            'label'    => __('Removes the feedback link.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_admin_bar_view_site',
            'type'    => 'switcher',
            'title'   => __('View Site', 'wp-genius'),
            'label'    => __('Removes the "View Site" link.', 'wp-genius'),
            'default' => true,
        ],
        
        // Dashboard Widgets
        [
            'id'      => '_subheading_dashboard_widgets',
            'type'    => 'subheading',
            'content' => __('Dashboard Widgets', 'wp-genius'),
        ],
        [
            'id'      => 'accelerate_remove_dashboard_activity',
            'type'    => 'switcher',
            'title'   => __('Activity Widget', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_dashboard_primary',
            'type'    => 'switcher',
            'title'   => __('Primary Sidebar (Events)', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'      => 'accelerate_remove_dashboard_secondary',
            'type'    => 'switcher',
            'title'   => __('Secondary Sidebar (News)', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'      => 'accelerate_remove_dashboard_site_health',
            'type'    => 'switcher',
            'title'   => __('Site Health Widget', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'      => 'accelerate_remove_dashboard_right_now',
            'type'    => 'switcher',
            'title'   => __('At a Glance Widget', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'      => 'accelerate_remove_dashboard_quick_draft',
            'type'    => 'switcher',
            'title'   => __('Quick Draft Widget', 'wp-genius'),
            'default' => true,
        ],
        
        // General Interface
        [
            'id'      => '_subheading_general_interface',
            'type'    => 'subheading',
            'content' => __('General Interface', 'wp-genius'),
        ],
        [
            'id'      => 'accelerate_disable_months_dropdown',
            'type'    => 'switcher',
            'title'   => __('Disable Months Dropdown', 'wp-genius'),
            'label'    => __('Block slow "All dates" queries in post list.', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'      => 'accelerate_enable_local_avatar',
            'type'    => 'switcher',
            'title'   => __('Local Avatar Manager', 'wp-genius'),
            'label'    => __('Replace Gravatar with local avatar management.', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'      => 'accelerate_enable_upload_rename',
            'type'    => 'switcher',
            'title'   => __('Auto Rename Uploads', 'wp-genius'),
            'label'    => __('Automatically normalize uploaded filenames.', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'         => 'accelerate_upload_rename_pattern',
            'type'       => 'text',
            'title'      => __('Rename Pattern', 'wp-genius'),
            'label'       => __('Pattern: {timestamp}, {sanitized}, {rand}, {date}, etc.', 'wp-genius'),
            'default'    => '{timestamp}_{sanitized}',
            'dependency' => ['accelerate_enable_upload_rename', '==', 'true'],
        ],
        [
            'id'      => 'accelerate_enable_delete_with_images',
            'type'    => 'switcher',
            'title'   => __('Delete with Images', 'wp-genius'),
            'label'    => __('Adds a "Delete w/ Images" action to the post list.', 'wp-genius'),
            'default' => false,
        ],
        
        // Update Behaviors
        [
            'id'      => '_subheading_update_behaviors',
            'type'    => 'subheading',
            'content' => __('Update Behaviors', 'wp-genius'),
        ],
        [
            'id'      => 'accelerate_disable_auto_update_plugin',
            'type'    => 'switcher',
            'title'   => __('Disable Plugin Auto-Update', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_disable_auto_update_theme',
            'type'    => 'switcher',
            'title'   => __('Disable Theme Auto-Update', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_wp_update_plugins',
            'type'    => 'switcher',
            'title'   => __('Disable Plugin Update Checks', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_wp_update_themes',
            'type'    => 'switcher',
            'title'   => __('Disable Theme Update Checks', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_maybe_update_core',
            'type'    => 'switcher',
            'title'   => __('Disable Core Update Checks', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_maybe_update_plugins',
            'type'    => 'switcher',
            'title'   => __('Disable Plugin Checks (Admin Init)', 'wp-genius'),
            'label'    => __('Blocks explicit update checks on admin page load.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_remove_maybe_update_themes',
            'type'    => 'switcher',
            'title'   => __('Disable Theme Checks (Admin Init)', 'wp-genius'),
            'label'    => __('Blocks explicit update checks on admin page load.', 'wp-genius'),
            'default' => true,
        ],
        [
            'id'      => 'accelerate_hide_plugin_notices',
            'type'    => 'switcher',
            'title'   => __('Hide Plugin Notices', 'wp-genius'),
            'label'    => __('Hides annoying update notices and registration prompts.', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'      => 'accelerate_block_acf_updates',
            'type'    => 'switcher',
            'title'   => __('Block ACF Updates', 'wp-genius'),
            'label'    => __('Blocks outgoing requests to ACF update servers.', 'wp-genius'),
            'default' => false,
        ],
        [
            'id'      => 'accelerate_block_external_http',
            'type'    => 'switcher',
            'title'   => __('Block External HTTP (DANGER)', 'wp-genius'),
            'label'    => __('⚠️ Use only for local development! Blocks all external update checks.', 'wp-genius'),
            'default' => false,
        ],
    ],
];
