<?php
/**
 * Unified settings facade
 *
 * Unified settings access facade: centralizes direct option reads/writes from all modules and provides a consistent read entry point.
 *
 * Storage channel conventions (after the 2026-02 unification):
 * - Primary channel: w2p_settings (CSF-driven); module configs are stored under tab keys,
 *   e.g. media_engine_tabs / ai_engine_tabs / smart_aui_tabs, etc.;
 * - Standalone channel (kept on demand): w2p_ai_*_key (encrypted),
 *   w2p_ai_schedule_* (deprecated once P1-3 moves into custom tables), etc.;
 * - Deprecated channel: w2p_media_turbo_settings (leftover from early register_setting; read as a
 *   compatibility fallback for media_engine_tabs, no longer written).
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_Settings
 */
class W2P_Settings {

	/**
	 * Read a module config section from the CSF primary channel (w2p_settings[ $tab ]).
	 *
	 * @param string $tab     Config section key.
	 * @param array  $default Default value.
	 * @return array
	 */
	public static function tab( $tab, $default = array() ) {
		$all = get_option( 'w2p_settings', array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		return isset( $all[ $tab ] ) && is_array( $all[ $tab ] ) ? $all[ $tab ] : $default;
	}

	/**
	 * Read a module config section with legacy-channel compatibility fallback.
	 *
	 * Used for dual-channel reads during migration from legacy storage (e.g. w2p_media_turbo_settings);
	 * once the new value is written to the primary channel, the legacy channel is naturally no longer read.
	 *
	 * @param string $tab            Primary channel config section key.
	 * @param string $legacy_option  Legacy option key ('' means no fallback).
	 * @param array  $default        Default value.
	 * @return array
	 */
	public static function tab_with_legacy( $tab, $legacy_option = '', $default = array() ) {
		$value = self::tab( $tab );

		if ( empty( $value ) && ! empty( $legacy_option ) ) {
			$legacy = get_option( $legacy_option, array() );
			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				$value = $legacy;
			}
		}

		return is_array( $value ) ? $value : $default;
	}

	/**
	 * Read an arbitrary option value.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		return get_option( $key, $default );
	}

	/**
	 * Write an arbitrary option value.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value The value.
	 * @return bool
	 */
	public static function set( $key, $value ) {
		return update_option( $key, $value );
	}
}
