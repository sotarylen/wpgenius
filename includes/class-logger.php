<?php
/**
 * WPGenius Unified Logger
 *
 * Handles error and debug logging across the plugin.
 *
 * @package WP_Genius
 * @author WPGenius Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class W2P_Logger {

    /**
     * Log level constants
     */
    const ERROR   = 'error';
    const WARNING = 'warning';
    const INFO    = 'info';
    const DEBUG   = 'debug';

    /**
     * Log an error message
     *
     * @param string $message
     * @param string $context Module or feature name
     */
    public static function error( $message, $context = 'general' ) {
        self::log( self::ERROR, $message, $context );
    }

    /**
     * Log a warning message
     *
     * @param string $message
     * @param string $context
     */
    public static function warning( $message, $context = 'general' ) {
        self::log( self::WARNING, $message, $context );
    }

    /**
     * Log an info message
     *
     * @param string $message
     * @param string $context
     */
    public static function info( $message, $context = 'general' ) {
        self::log( self::INFO, $message, $context );
    }

    /**
     * Log a debug message
     *
     * @param string $message
     * @param string $context
     */
    public static function debug( $message, $context = 'general' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            self::log( self::DEBUG, $message, $context );
        }
    }

    /**
     * Core logging function
     *
     * @param string $level
     * @param string $message
     * @param string $context
     */
    private static function log( $level, $message, $context ) {
        $timestamp = current_time( 'mysql' );
        $formatted_message = sprintf(
            '[WPGenius][%s][%s][%s] %s',
            $timestamp,
            strtoupper( $level ),
            strtoupper( $context ),
            $message
        );

        // For now, use PHP's error_log
        error_log( $formatted_message );
        
        // In the future, this could write to a custom file or database table
    }
}
