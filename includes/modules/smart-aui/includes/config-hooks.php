<?php
/**
 * Smart Auto Upload Images Configuration Hooks
 *
 * Modifies the original plugin's behavior to fix issues
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// HTTP request settings are encapsulated directly inside ImageDownloader to prevent polluting global requests.

// Increase WordPress max execution time
add_filter(
	'wp_php_timeout',
	function ( $timeout ) {
		// Increase the timeout when saving posts
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- only reads the action name to adjust timeouts, never changes status.
		if ( isset( $_POST['action'] ) && in_array( $_POST['action'], array( 'editpost', 'inline-save' ), true ) ) {
			return 300; // 5 minutes
		}
		return $timeout;
	}
);

// Disable the WordPress heartbeat to avoid interruption while processing images
add_action(
	'admin_enqueue_scripts',
	function () {
		global $pagenow;
		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			// Extend the heartbeat interval
			add_filter(
				'heartbeat_settings',
				function ( $settings ) {
					$settings['interval'] = 60; // 60 seconds
					return $settings;
				}
			);
		}
	}
);
