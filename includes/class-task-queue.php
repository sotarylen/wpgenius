<?php
/**
 * Lightweight task queue wrapper
 *
 * Provides a simplified interface for scheduling one-time and recurring background tasks based on WP Cron.
 * Additionally includes async request dispatch (non-blocking HTTP request) for triggering background processes that need not be awaited.
 *
 * @package WP_Genius
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
	public static function schedule_single( $hook, $args = array(), $delay = 0 ) {
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
	public static function schedule_recurring( $hook, $args = array(), $interval = 'hourly' ) {
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
	public static function unschedule( $hook, $args = array() ) {
		$timestamp = wp_next_scheduled( $hook, $args );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook, $args );
		}
	}
}
