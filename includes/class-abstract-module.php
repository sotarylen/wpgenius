<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * 模组抽象基类
 *
 * 定义了所有模组必须继承的标准结构和通用方法，包括初始化、设置注册、视图渲染和配置获取等功能。
 * 强制子类实现特定接口，保证了模组架构的一致性。
 *
 * @package WP_Genius
 */

abstract class W2P_Abstract_Module {
	// 模块唯一 ID（目录名为默认）
	public static function id() {
		return '';
	}

	public static function name() {
		return '';
	}

	public static function description() {
		return '';
	}

	public static function icon() {
		return 'fa-solid fa-puzzle-piece'; // Default icon
	}

	// 在插件初始化时调用
	public function init() {}

	// 在设置页中注册模块设置片段（可选）
	public function register_settings() {}

	/**
	 * Render a view template for the module.
	 *
	 * @param string $view_name Name of the template (without .php).
	 * @param array  $args      Variables to pass to the template.
	 */
	public function render_view( $view_name, $args = array() ) {
		$file = plugin_dir_path( ( new ReflectionClass( get_called_class() ) )->getFileName() ) . $view_name . '.php';

		if ( file_exists( $file ) ) {
			// Extract variables to local scope for template rendering.
			if ( ! empty( $args ) ) {
				// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Intentional: passing variables to view templates.
				extract( $args );
			}

			// Standard data available to all views
			$module_id = static::id();
			$settings  = $this->get_settings();
			$nonce     = wp_create_nonce( 'w2p_' . str_replace( '-', '_', $module_id ) . '_nonce' );

			include $file;
		} else {
			W2P_Logger::error( "View template not found: {$file}", 'core' );
		}
	}

	/**
	 * Get module-specific settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		// [Refactor] Unify settings storage.
		// All settings are now stored in 'w2p_settings' by CSF.
		$all_settings = get_option( 'w2p_settings', array() );

		// If the module overrides settings_key() to point to something else (legacy support), use that.
		// But default behavior is now to use the global store.
		if ( $this->settings_key() !== 'w2p_settings' ) {
			return get_option( $this->settings_key(), array() );
		}

		return $all_settings;
	}

	/**
	 * Get the settings key for the module.
	 *
	 * @return string
	 */
	public function settings_key() {
		// [Refactor] Default to global settings key
		return 'w2p_settings';
	}

	// 激活/停用钩子（可选）
	public function activate() {}
	public function deactivate() {}
}
