<?php
/**
 * 统一日志记录器
 *
 * 提供标准化的静态方法（error, warning, info, debug）用于全插件范围内的日志记录。
 * 支持上下文标记和时间戳自动添加，目前通过 error_log 输出，便于开发者调试和追踪错误。
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
        // Disable debug logging for media-turbo to prevent debug.log bloat
        if ( $context === 'media-turbo' ) {
            return;
        }
        
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
