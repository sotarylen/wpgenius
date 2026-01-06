<?php
/**
 * Simple Task Queue for WP Genius
 * A lightweight alternative to Action Scheduler for background processing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class W2P_Task_Queue {

    /**
     * Schedule a single event.
     *
     * @param string $hook  Action hook to execute.
     * @param array  $args  Arguments to pass to the hook.
     * @param int    $delay Delay in seconds (default 0).
     */
    public static function schedule_single( $hook, $args = [], $delay = 0 ) {
        if ( ! wp_next_scheduled( $hook, $args ) ) {
            wp_schedule_single_event( time() + $delay, $hook, $args );
        }
    }

    /**
     * Schedule a recurring event.
     *
     * @param string $hook     Action hook to execute.
     * @param array  $args     Arguments.
     * @param string $interval Interval name (e.g. 'hourly', 'daily').
     */
    public static function schedule_recurring( $hook, $args = [], $interval = 'hourly' ) {
        if ( ! wp_next_scheduled( $hook, $args ) ) {
            wp_schedule_event( time(), $interval, $hook, $args );
        }
    }

    /**
     * Unschedule an event.
     * 
     * @param string $hook Action hook.
     * @param array  $args Arguments.
     */
    public static function unschedule( $hook, $args = [] ) {
        $timestamp = wp_next_scheduled( $hook, $args );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $hook, $args );
        }
    }

    /**
     * Async Request Helper (Non-blocking HTTP request)
     * Useful for triggering background processing immediately without waiting for Cron.
     *
     * @param string $action AJAX action name
     * @param array  $data   Post data
     */
    public static function dispatch_async( $action, $data = [] ) {
        $args = [
            'method'    => 'POST',
            'timeout'   => 0.01,
            'blocking'  => false,
            'body'      => array_merge( $data, [ 'action' => $action ] ),
            'cookies'   => $_COOKIE,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
        ];

        wp_remote_post( admin_url( 'admin-ajax.php' ), $args );
    }
}
