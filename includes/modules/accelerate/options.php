<?php
/**
 * Accelerate Module - CSF Options
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$legacy   = get_option( 'w2p_accelerate_settings', array() );
$defaults = array(
	// Admin Bar
	'accelerate_remove_admin_bar_wp_logo'        => true,
	'accelerate_remove_admin_bar_about'          => true,
	'accelerate_remove_admin_bar_comments'       => true,
	'accelerate_remove_admin_bar_new_content'    => true,
	'accelerate_remove_admin_bar_search'         => true,
	'accelerate_remove_admin_bar_updates'        => true,
	'accelerate_remove_admin_bar_appearance'     => true,
	'accelerate_remove_admin_bar_customize'      => true,
	'accelerate_remove_admin_bar_wporg'          => true,
	'accelerate_remove_admin_bar_documentation'  => true,
	'accelerate_remove_admin_bar_support_forums' => true,
	'accelerate_remove_admin_bar_feedback'       => true,
	'accelerate_remove_admin_bar_view_site'      => true,

	// Dashboard Widgets
	'accelerate_remove_dashboard_activity'       => true,
	'accelerate_remove_dashboard_primary'        => true,
	'accelerate_remove_dashboard_secondary'      => true,
	'accelerate_remove_dashboard_site_health'    => false,
	'accelerate_remove_dashboard_right_now'      => true,
	'accelerate_remove_dashboard_quick_draft'    => true,

	// General
	// Note: this $defaults array is a legacy leftover (unreferenced), kept only for semantic consistency.
	'accelerate_disable_months_dropdown'         => array(),
	'accelerate_enable_local_avatar'             => true,
	'accelerate_enable_upload_rename'            => true,
	'accelerate_upload_rename_pattern'           => '{timestamp}_{sanitized}',
	'accelerate_enable_delete_with_images'       => true,

	// Update Behaviors
	'accelerate_disable_auto_update_plugin'      => true,
	'accelerate_disable_auto_update_theme'       => true,
	'accelerate_remove_wp_update_plugins'        => true,
	'accelerate_remove_wp_update_themes'         => true,
	'accelerate_remove_maybe_update_core'        => true,
	'accelerate_remove_maybe_update_plugins'     => true,
	'accelerate_remove_maybe_update_themes'      => true,
	'accelerate_hide_plugin_notices'             => true,
	'accelerate_block_acf_updates'               => true,
	'accelerate_block_external_http'             => true,
);

// Return the module definition
// Build dynamic options for the SQL interception checkbox from rules exposed by the mu-plugin.
$sql_rules = apply_filters( 'w2p_accel_sql_rules', array() );
if ( empty( $sql_rules ) && function_exists( 'w2p_skip_sql_get_rules' ) ) {
	$sql_rules = w2p_skip_sql_get_rules();
}
$sql_skip_options = array();
foreach ( $sql_rules as $rid => $r ) {
	$sql_skip_options[ $rid ] = isset( $r['label'] ) ? $r['label'] : $rid;
}

// The canonical rename-token list/meaning lives in the UploadRename class.
// options.php is parsed before the module's init() wires that class in, so
// load it on demand and reuse it here instead of redefining the tokens.
if ( ! class_exists( 'W2P_Accelerate_UploadRename', false ) ) {
	require_once __DIR__ . '/includes/class-upload-rename.php';
}
$rename_token_hint = '';
if ( class_exists( 'W2P_Accelerate_UploadRename', false ) ) {
	$token_parts = array();
	foreach ( W2P_Accelerate_UploadRename::get_token_descriptions() as $token => $meaning ) {
		$token_parts[] = $token . ' = ' . $meaning;
	}
	$rename_token_hint = esc_html__( 'Available tokens (use in the pattern above):', 'wp-genius' ) . ' ' . implode( ';<br /> ', $token_parts );
}

return array(
	'module_id' => 'accelerate',
	'id'        => 'accelerate',
	'title'     => __( 'Accelerate', 'wp-genius' ),
	'icon'      => 'fa-solid fa-gauge-high',
	'fields'    => array(
		// Admin Bar Section
		array(
			'id'      => '_subheading_admin_bar',
			'type'    => 'subheading',
			'content' => __( 'Admin Bar Items', 'wp-genius' ),
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_wp_logo',
			'type'    => 'switcher',
			'title'   => __( 'WordPress Logo', 'wp-genius' ),
			'label'   => __( 'Removes the WP logo from the top left.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_about',
			'type'    => 'switcher',
			'title'   => __( 'About WordPress', 'wp-genius' ),
			'label'   => __( 'Removes the "About WordPress" link.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_comments',
			'type'    => 'switcher',
			'title'   => __( 'Comments', 'wp-genius' ),
			'label'   => __( 'Removes the comments moderation icon.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_new_content',
			'type'    => 'switcher',
			'title'   => __( 'New Content Menu', 'wp-genius' ),
			'label'   => __( 'Removes the "+ New" menu.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_search',
			'type'    => 'switcher',
			'title'   => __( 'Search', 'wp-genius' ),
			'label'   => __( 'Removes the search bar from admin bar.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_updates',
			'type'    => 'switcher',
			'title'   => __( 'Updates Notification', 'wp-genius' ),
			'label'   => __( 'Removes the updates notification icon.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_appearance',
			'type'    => 'switcher',
			'title'   => __( 'Appearance', 'wp-genius' ),
			'label'   => __( 'Removes the Appearance menu.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_customize',
			'type'    => 'switcher',
			'title'   => __( 'Customize', 'wp-genius' ),
			'label'   => __( 'Removes the Customize menu.', 'wp-genius' ),
			'default' => false,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_wporg',
			'type'    => 'switcher',
			'title'   => __( 'WordPress.org', 'wp-genius' ),
			'label'   => __( 'Removes WordPress.org external links.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_documentation',
			'type'    => 'switcher',
			'title'   => __( 'Documentation', 'wp-genius' ),
			'label'   => __( 'Removes documentation links.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_support_forums',
			'type'    => 'switcher',
			'title'   => __( 'Support Forums', 'wp-genius' ),
			'label'   => __( 'Removes support forum links.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_feedback',
			'type'    => 'switcher',
			'title'   => __( 'Feedback', 'wp-genius' ),
			'label'   => __( 'Removes the feedback link.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_admin_bar_view_site',
			'type'    => 'switcher',
			'title'   => __( 'View Site', 'wp-genius' ),
			'label'   => __( 'Removes the "View Site" link.', 'wp-genius' ),
			'default' => true,
		),

		// Dashboard Widgets
		array(
			'id'      => '_subheading_dashboard_widgets',
			'type'    => 'subheading',
			'content' => __( 'Dashboard Widgets', 'wp-genius' ),
		),
		array(
			'id'      => 'accelerate_remove_dashboard_activity',
			'type'    => 'switcher',
			'title'   => __( 'Activity Widget', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_remove_dashboard_primary',
			'type'    => 'switcher',
			'title'   => __( 'Primary Sidebar (Events)', 'wp-genius' ),
			'default' => false,
		),
		array(
			'id'      => 'accelerate_remove_dashboard_secondary',
			'type'    => 'switcher',
			'title'   => __( 'Secondary Sidebar (News)', 'wp-genius' ),
			'default' => false,
		),
		array(
			'id'      => 'accelerate_remove_dashboard_site_health',
			'type'    => 'switcher',
			'title'   => __( 'Site Health Widget', 'wp-genius' ),
			'default' => false,
		),
		array(
			'id'      => 'accelerate_remove_dashboard_right_now',
			'type'    => 'switcher',
			'title'   => __( 'At a Glance Widget', 'wp-genius' ),
			'default' => false,
		),
		array(
			'id'      => 'accelerate_remove_dashboard_quick_draft',
			'type'    => 'switcher',
			'title'   => __( 'Quick Draft Widget', 'wp-genius' ),
			'default' => true,
		),

		// General Interface
		array(
			'id'      => '_subheading_general_interface',
			'type'    => 'subheading',
			'content' => __( 'General Interface', 'wp-genius' ),
		),
		array(
			'id'        => 'accelerate_disable_months_dropdown',
			'type'      => 'checkbox',
			'title'     => __( 'Disable Months Dropdown', 'wp-genius' ),
			'desc'      => esc_html__( 'Disable the \u201cFilter by month\u201d dropdown in the admin list for the selected post types (avoids slow queries on large datasets). The Media Library maps to the attachment type; keep it unchecked if you need month filtering in the library.', 'wp-genius' ),
			'options'   => array(), // Options are injected dynamically by the csf_w2p_settings_sections filter at init:10 (all CPTs are registered by then).
			'check_all' => true,
			'inline'    => true,
			'default'   => array(),
		),
		array(
			'id'      => 'accelerate_enable_delete_with_images',
			'type'    => 'switcher',
			'title'   => __( 'Delete with Images', 'wp-genius' ),
			'label'   => __( 'Adds a "Delete w/ Images" action to the post list.', 'wp-genius' ),
			'default' => false,
		),
		array(
			'id'         => 'accelerate_delete_with_images_post_types',
			'type'       => 'checkbox',
			'title'      => __( 'Delete with Images — Post Types', 'wp-genius' ),
			'desc'       => esc_html__( 'Select the post types where the "Delete w/ Images" action is available. Defaults to Posts and Albums.', 'wp-genius' ),
			'options'    => array(), // Options are injected dynamically by the csf_w2p_settings_sections filter at init:10 (all CPTs are registered by then).
			'check_all'  => true,
			'inline'     => true,
			'default'    => array( 'post', 'albums' ),
			'dependency' => array( 'accelerate_enable_delete_with_images', '==', 'true' ),
		),
		array(
			'id'      => 'accelerate_enable_change_post_type',
			'type'    => 'switcher',
			'title'   => __( 'Change PostType', 'wp-genius' ),
			'label'   => __( 'Adds a "Change PostType" select to the Publish panel and a "PostType" select to the Bulk Edit panel for moving posts between post types (e.g. post ↔ albums).', 'wp-genius' ),
			'default' => false,
		),
		array(
			'id'      => 'accelerate_enable_local_avatar',
			'type'    => 'switcher',
			'title'   => __( 'Local Avatar Manager', 'wp-genius' ),
			'label'   => __( 'Replace Gravatar with local avatar management.', 'wp-genius' ),
			'default' => false,
		),
		array(
			'id'      => 'accelerate_enable_upload_rename',
			'type'    => 'switcher',
			'title'   => __( 'Auto Rename Uploads', 'wp-genius' ),
			'label'   => __( 'Automatically normalize uploaded filenames.', 'wp-genius' ),
			'default' => false,
		),
		array(
			'id'         => 'accelerate_upload_rename_pattern',
			'type'       => 'text',
			'title'      => __( 'Rename Pattern', 'wp-genius' ),
			'default'    => '{timestamp}_{sanitized}',
			'desc'       => $rename_token_hint,
			'dependency' => array( 'accelerate_enable_upload_rename', '==', 'true' ),
		),
		

		// Update Behaviors
		array(
			'id'      => '_subheading_update_behaviors',
			'type'    => 'subheading',
			'content' => __( 'Update Control', 'wp-genius' ),
		),
		array(
			'id'      => 'accelerate_disable_auto_updates',
			'type'    => 'switcher',
			'title'   => __( 'Disable All Auto-Updates', 'wp-genius' ),
			'label'   => __( 'Prevents WordPress from automatically updating plugins and themes.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_disable_plugin_updates',
			'type'    => 'switcher',
			'title'   => __( 'Disable Plugin Update Checks', 'wp-genius' ),
			'label'   => __( 'Prevents WordPress from checking for plugin updates (both scheduled and manual).', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_disable_theme_updates',
			'type'    => 'switcher',
			'title'   => __( 'Disable Theme Update Checks', 'wp-genius' ),
			'label'   => __( 'Prevents WordPress from checking for theme updates.', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_disable_core_updates',
			'type'    => 'switcher',
			'title'   => __( 'Disable Core Update Checks', 'wp-genius' ),
			'default' => true,
		),
		array(
			'id'      => 'accelerate_hide_plugin_notices',
			'type'    => 'switcher',
			'title'   => __( 'Hide Plugin Notices', 'wp-genius' ),
			'label'   => __( 'Hides annoying update notices and registration prompts.', 'wp-genius' ),
			'default' => false,
		),

		array(
			'id'      => '_subheading_sql_interception',
			'type'    => 'subheading',
			'content' => __( 'Redundant SQL Interception', 'wp-genius' ),
		),
		array(
			'id'      => 'sql_skip_enabled',
			'type'    => 'checkbox',
			'title'   => __( 'Enable SQL Interception', 'wp-genius' ),
			'desc'    => __( 'Check = Block SQL; Uncheck = Allow (restore plugin original behavior). Default = Block all and new rules add to Blocklist', 'wp-genius' ),
			'options' => $sql_skip_options,
			'inline'  => true,
			'default' => array_keys( $sql_rules ),
		),
		// HTTP Blocking
		array(
			'id'      => '_subheading_http_blocking',
			'type'    => 'subheading',
			'content' => __( 'HTTP Blocking', 'wp-genius' ),
		),
		array(
			'id'           => 'accelerate_blind_http_requests',
			'type'         => 'repeater',
			'title'        => __( 'Blocked HTTP Patterns', 'wp-genius' ),
			'button_title' => __( 'Add Pattern', 'wp-genius' ),
			'fields'       => array(
				array(
					'id'          => 'url_pattern',
					'type'        => 'text',
					'title'       => __( 'URL Pattern', 'wp-genius' ),
					'placeholder' => 'e.g., connect.advancedcustomfields.com',
				),
			),
			'help'         => __( 'Requests containing these strings in the URL will be blocked.', 'wp-genius' ),
			'default'      => array(),
		),
		array(
			'id'         => 'accelerate_block_external_http',
			'type'       => 'switcher',
			'title'      => __( 'Block All External HTTP (DANGER)', 'wp-genius' ),
			'label'      => __( '⚠️ Use only for local development! Blocks all external update checks.', 'wp-genius' ),
			'default'    => false,
			'class'      => 'w2p-danger-toggle',
			'attributes' => array(
				'data-confirm' => __( 'Warning: Enabling this feature will block all external HTTP requests, including plugin and theme update checks. This will significantly speed up the backend but may cause some features to fail. Are you sure you want to continue?', 'wp-genius' ),
			),
		),
	),
);
