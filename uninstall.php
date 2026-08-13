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
);

foreach ( $options as $option ) {
	delete_option( $option );
}

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
);

foreach ( $hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

/**
 * Clean up user meta.
 */
global $wpdb;

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
