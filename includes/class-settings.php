<?php
/**
 * Unified settings facade
 *
 * 统一的设置访问门面：收口各模块对 option 的直接读写，提供一致的读取入口。
 *
 * 存储通道约定（2026-02 统一后）：
 * - 主通道：w2p_settings（CSF 驱动），模块配置按 tab key 存放，
 *   例如 media_engine_tabs / ai_engine_tabs / smart_aui_tabs 等；
 * - 独立通道（按需保留）：w2p_ai_*_key（已加密）、w2p_cms_migrator_settings（已加密）、
 *   w2p_ai_schedule_*（P1-3 迁入自定义表后废弃）等；
 * - 废弃通道：w2p_media_turbo_settings（早期 register_setting 遗留，读取时作为
 *   media_engine_tabs 的兼容回退，不再写入）。
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
	 * 读取 CSF 主通道中的模块配置段（w2p_settings[ $tab ]）。
	 *
	 * @param string $tab     配置段 key。
	 * @param array  $default 缺省值。
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
	 * 读取模块配置段，带旧通道兼容回退。
	 *
	 * 用于从旧存储（如 w2p_media_turbo_settings）迁移期间的双通道读取，
	 * 新值写入主通道后旧通道自然不再被读取。
	 *
	 * @param string $tab            主通道配置段 key。
	 * @param string $legacy_option  旧 option key（'' 表示无回退）。
	 * @param array  $default        缺省值。
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
	 * 读取任意 option 值。
	 *
	 * @param string $key     Option key。
	 * @param mixed  $default 缺省值。
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		return get_option( $key, $default );
	}

	/**
	 * 写入任意 option 值。
	 *
	 * @param string $key   Option key。
	 * @param mixed  $value 值。
	 * @return bool
	 */
	public static function set( $key, $value ) {
		return update_option( $key, $value );
	}
}
