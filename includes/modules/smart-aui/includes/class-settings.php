<?php
/**
 * Smart AUI — Settings Handler
 *
 * 负责 CSF 设置与 legacy smart_aui_settings 选项之间的同步、读取与注册。
 * 从 module.php 拆分（原 God class 重构）。
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_SmartAUI_Settings
 */
class W2P_SmartAUI_Settings {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_SmartAUIModule
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param W2P_SmartAUIModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * Sync CSF Settings to Legacy Option
	 *
	 * 该模块集成的 smart-auto-upload-images 库依赖 `smart_aui_settings` 选项。
	 * 此方法将 WP Genius 的 CSF 设置同步到该选项，确保库能正常工作。
	 *
	 * @param array $w2p_settings 全局设置数组。
	 * @return void
	 */
	public function sync_settings( $w2p_settings ) {
		if ( empty( $w2p_settings ) ) {
			return;
		}

		// Flatten smart_aui_tabs if present (CSF nested tabs behavior)
		if ( isset( $w2p_settings['smart_aui_tabs'] ) && is_array( $w2p_settings['smart_aui_tabs'] ) ) {
			$w2p_settings = array_merge( $w2p_settings, $w2p_settings['smart_aui_tabs'] );
		}

		$legacy_settings = get_option( 'smart_aui_settings', array() );
		$has_changes     = false;

		// 映射 CSF 字段 ID 到 Legacy 字段 ID
		$map = array(
			'smart_aui_base_url'                   => 'base_url',
			'smart_aui_image_name_pattern'         => 'image_name_pattern',
			'smart_aui_alt_text_pattern'           => 'alt_text_pattern',
			'smart_aui_min_width'                  => 'min_width',
			'smart_aui_min_height'                 => 'min_height',
			'smart_aui_exclude_domains'            => 'exclude_domains',
			'smart_aui_exclude_post_types'         => 'exclude_post_types',
			'smart_aui_auto_set_featured_image'    => 'auto_set_featured_image',
			'smart_aui_show_progress_ui'           => 'show_progress_ui',
			'smart_aui_process_images_on_rest_api' => 'process_images_on_rest_api',
			'smart_aui_concurrent_threads'         => 'concurrent_threads',
			'smart_aui_max_retries'                => 'max_retries',
			'smart_aui_skip_duplicates'            => 'skip_duplicates',
			'smart_aui_capture_videos'             => 'capture_videos',
		);

		foreach ( $map as $csf_key => $legacy_key ) {
			if ( isset( $w2p_settings[ $csf_key ] ) ) {
				// CSF 返回 true/false 或 1/0，确保格式一致
				$value = $w2p_settings[ $csf_key ];

				// 针对 exclude_post_types 特殊处理，确保是数组
				if ( 'exclude_post_types' === $legacy_key && ! is_array( $value ) ) {
					$value = array();
				}

				if ( ! isset( $legacy_settings[ $legacy_key ] ) || $legacy_settings[ $legacy_key ] !== $value ) {
					$legacy_settings[ $legacy_key ] = $value;
					$has_changes                    = true;
				}
			}
		}

		if ( $has_changes ) {
			update_option( 'smart_aui_settings', $legacy_settings );
		}
	}

	/**
	 * Register module settings (CSF options file).
	 *
	 * @return array
	 */
	public function register_settings() {
		return include plugin_dir_path( __DIR__ ) . 'options.php';
	}

	/**
	 * Get module settings from the global store, flattening the CSF nested tab.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( 'w2p_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Flatten smart_aui_tabs if present (CSF nested tabs behavior)
		if ( isset( $settings['smart_aui_tabs'] ) && is_array( $settings['smart_aui_tabs'] ) ) {
			$settings = array_merge( $settings, $settings['smart_aui_tabs'] );
		}

		return $settings;
	}
}
