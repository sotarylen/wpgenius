<?php
/**
 * WP Genius Uninstall
 *
 * Fired when the plugin is deleted from the WordPress admin.
 *
 * @package WP_Genius
 */

// Abort if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options.
 */
$options = array(
	'w2p_settings',
	'w2p_auto_publish_logs',
	'w2p_auto_publish_last_run',
	'w2p_auto_publish_scheduled_status',
	'w2p_media_turbo_processed_posts',
	'w2p_media_engine_migrated',
	'w2p_smart_aui_settings',
	// AI Engine.
	'w2p_ai_openai_key',
	'w2p_ai_anthropic_key',
	'w2p_ai_gemini_key',
	'w2p_ai_deepseek_key',
	'w2p_ai_openai_usage',
	'w2p_ai_anthropic_usage',
	'w2p_ai_gemini_usage',
	'w2p_ai_deepseek_usage',
	'w2p_ai_schedules_migrated',
	'w2p_db_version',
	// CMS Migrator.
	'w2p_cms_migrator_settings',
	'w2p_cms_migration_progress',
	// Word to Post.
	'w2p_fix_index_finished_books',
	'w2p_word_publish_settings',
	'w2p_media_turbo_settings',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * Delete legacy per-schedule options (pre-P1-3 storage).
 */
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'w2p_ai_schedule_%'"
);

/**
 * Delete transients.
 */
$transients = array(
	'w2p_auto_publish_active_lock',
	'w2p_auto_publish_scheduled_status',
	'w2p_smart_aui_progress',
	'w2p_media_turbo_status',
);

foreach ( $transients as $transient ) {
	delete_transient( $transient );
}

/**
 * Clear scheduled cron events.
 */
$hooks = array(
	'w2p_auto_publish_cron',
	'w2p_media_turbo_cron',
	'w2p_smart_aui_cleanup',
	'w2p_ai_content_generation',
	'w2p_ai_queue_processor',
);

foreach ( $hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

/**
 * Drop module database tables.
 */
global $wpdb;
$tables = array(
	$wpdb->prefix . 'w2p_ai_prompts',
	$wpdb->prefix . 'w2p_ai_queue',
	$wpdb->prefix . 'w2p_ai_schedules',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall-time table cleanup; 表名由 $wpdb->prefix 常量拼接。
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

/**
 * Clean up user meta.
 */

// Local avatar meta.
$avatar_users = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
		'st_local_avatar'
	)
);

foreach ( $avatar_users as $user_id ) {
	delete_user_meta( $user_id, 'st_local_avatar' );
}

// Smart AUI processed posts meta.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_w2p_smart_aui_processed'"
);

// Word-to-post associated CPT meta.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_w2p_associated_cpt_%'"
);
