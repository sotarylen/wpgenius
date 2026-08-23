<?php
/**
 * Smart AUI — Settings Handler
 *
 * Handles syncing, reading, and registering between the CSF settings and the legacy smart_aui_settings option.
 * Split from module.php (refactored from the original God class).
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
	 * The smart-auto-upload-images library integrated by this module depends on the `smart_aui_settings` option.
	 * This method syncs WP Genius's CSF settings into that option so the library works correctly.
	 *
	 * @param array $w2p_settings Global settings array.
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

		// Map CSF field IDs to Legacy field IDs
		$map = array(
			'smart_aui_base_url'                   => 'base_url',
			'smart_aui_image_name_pattern'         => 'image_name_pattern',
			'smart_aui_alt_text_pattern'           => 'alt_text_pattern',
			'smart_aui_min_width'                  => 'min_width',
			'smart_aui_min_height'                 => 'min_height',
			'smart_aui_exclude_domains'            => 'exclude_domains',
			'smart_aui_exclude_post_types'         => 'exclude_post_types',
			'smart_aui_auto_set_featured_image'    => 'auto_set_featured_image',
			'smart_aui_attach_orphan_images'      => 'attach_orphan_images',
			'smart_aui_enhance_attach'            => 'enhance_attach',
			'smart_aui_show_progress_ui'           => 'show_progress_ui',
			'smart_aui_process_images_on_rest_api' => 'process_images_on_rest_api',
			'smart_aui_concurrent_threads'         => 'concurrent_threads',
			'smart_aui_max_retries'                => 'max_retries',
			'smart_aui_skip_duplicates'            => 'skip_duplicates',
			'smart_aui_capture_videos'             => 'capture_videos',
		);

		foreach ( $map as $csf_key => $legacy_key ) {
			if ( isset( $w2p_settings[ $csf_key ] ) ) {
				// CSF returns true/false or 1/0; ensure a consistent format
				$value = $w2p_settings[ $csf_key ];

				// Special-case exclude_post_types to ensure it is an array
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
