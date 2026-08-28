<?php
/**
 * Smart Auto Upload Images Configuration Hooks
 *
 * Modifies the original plugin's behavior to fix issues
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Increase HTTP request timeout
add_filter(
	'http_request_timeout',
	function ( $timeout, $url ) {
		// Only increase the timeout for image requests
		if ( preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url ) ) {
			return 30; // 30 seconds
		}
		return $timeout;
	},
	10,
	2
);

// Modify wp_remote_get parameters
add_filter(
	'http_request_args',
	function ( $args, $url ) {
		// Only modify image requests
		if ( preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url ) ) {
			$args['timeout']   = 30;
			$args['sslverify'] = false;

			if ( ! isset( $args['headers'] ) ) {
				$args['headers'] = array();
			}

			// Add a User-Agent to avoid being rejected
			if ( ! isset( $args['headers']['User-Agent'] ) ) {
				$args['headers']['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
			}
		}

		return $args;
	},
	10,
	2
);

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
