<?php
/**
 * Logger Utility
 *
 * @package SmartAutoUploadImages\Utils
 */

namespace SmartAutoUploadImages\Utils;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger Class
 */
class Logger {

	/**
	 * Log levels.
	 */
	const ERROR   = 'error';
	const WARNING = 'warning';
	const INFO    = 'info';
	const DEBUG   = 'debug';

	/**
	 * Log a message.
	 *
	 * @param string $message Log message.
	 * @param string $level Log level.
	 * @param array  $context Additional context.
	 */
	public function log( string $message, string $level = self::INFO, array $context = [] ): void {
		// Only log if WP_DEBUG_LOG is enabled.
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		$log_message = sprintf(
			'[Smart Auto Upload Images] [%s] %s',
			strtoupper( $level ),
			$message
		);

		if ( ! empty( $context ) ) {
			$log_message .= ' Context: ' . wp_json_encode( $context );
		}

		error_log( $log_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context.
	 */
	public function error( string $message, array $context = [] ): void {
		$this->log( $message, self::ERROR, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @param string $message Warning message.
	 * @param array  $context Additional context.
	 */
	public function warning( string $message, array $context = [] ): void {
		$this->log( $message, self::WARNING, $context );
	}

	/**
	 * Log info message.
	 *
	 * @param string $message Info message.
	 * @param array  $context Additional context.
	 */
	public function info( string $message, array $context = [] ): void {
		$this->log( $message, self::INFO, $context );
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Debug message.
	 * @param array  $context Additional context.
	 */
	public function debug( string $message, array $context = [] ): void {
		$this->log( $message, self::DEBUG, $context );
	}
}
