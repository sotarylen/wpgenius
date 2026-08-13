<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * 管理后台设置类
 *
 * 负责初始化基于 CSF 框架的插件设置页面，加载全局配置选项。
 * 特别处理了隐藏/禁用模组的设置数据保护逻辑，防止在模组禁用时丢失原有的配置数据。
 *
 * @package WP_Genius
 */
class W2P_Admin_Settings {
	protected $loader;

	public function __construct( $loader ) {
		$this->loader = $loader;
		// No more manual menu registration. CSF handles it.
		$this->register_settings();
	}

	/**
	 * 注册全局设置 (原 includes/admin/options.php 逻辑合并至此)
	 */
	public function register_settings() {
		// Global Settings Prefix
		$prefix = 'w2p_settings';

		// 1. Initialize Framework
		// CSF 已在插件文件加载阶段（is_admin 守卫）require，此处仅防御性检查。
		// 注意：不能在此时才 require —— CSF 在文件加载时注册 init 钩子（setup），
		// 延迟 require 会让 setup 错过 init 而无法注册设置菜单。
		if ( ! class_exists( 'CSF' ) ) {
			$csf_path = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/csf/codestar-framework.php';
			if ( file_exists( $csf_path ) ) {
				require_once $csf_path;
			}
		}

		if ( class_exists( 'CSF' ) ) {
			CSF::createOptions(
				$prefix,
				array(
					'menu_title'      => 'WP Genius Settings',
					'menu_slug'       => 'wp-genius-settings',
					'menu_type'       => 'submenu',
					'menu_parent'     => 'tools.php',
					'framework_title' => 'WP Genius Settings',
					'show_sub_menu'   => false,
					'theme'           => 'light',
				)
			);

			// 2. Section 1: Module Management
			$module_fields = array();

			// Use the injected loader instance instead of creating a new one
			// Note: The loader has likely already run discover() in w2p_core_init() before this constructor is called.
			// However, to be safe and ensure we have modules, we can check.
			$modules = $this->loader->get_available_modules();

			// If modules empty, maybe discovery hasn't run or failed?
			// But w2p_core_init passes a NEW loader. The loader constructor sets dir, but doesn't auto-discover.
			// w2p_core_init calls $loader->init() AFTER $settings = new Settings($loader).
			// So at this point (Costructor time), $loader->discover() might NOT have run if it's called inside $loader->init().
			// Check loader code: init() calls discover().
			// So we should call discover() here to be sure, or purely rely on access.
			// Since we want to show ALL modules (even disabled ones), we need discovery.
			$this->loader->discover( true );
			$modules = $this->loader->get_available_modules();

			if ( ! empty( $modules ) ) {
				foreach ( $modules as $id => $module ) {
					$name = method_exists( $module, 'name' ) ? $module->name() : ucfirst( $id );
					$desc = method_exists( $module, 'description' ) ? $module->description() : '';

					$module_fields[] = array(
						'id'       => 'module_' . $id,
						'type'     => 'switcher',
						'title'    => $name,
						'subtitle' => $desc,
						'default'  => false,
					);
				}
			} else {
				$module_fields[] = array(
					'type'    => 'content',
					'content' => __( 'No modules found or Loader not ready.', 'wp-genius' ),
				);
			}

			CSF::createSection(
				$prefix,
				array(
					'title'  => __( 'Module Management', 'wp-genius' ),
					'icon'   => 'fa fa-th-large',
					'fields' => $module_fields,
				)
			);

			// 3. Auto-load module-specific options
			// Accessing module folder via loader's dir property would be cleanest if public, but we can reconstruct or simple use the modules array.
			// BUT, the modules array contains INSTANCES. We need to find their options.php.
			// Best way: iterate instances, check their directory.
			// However, Abstract Module doesn't strictly store its path.
			// Fallback: Use standard pathing as before, but cleaner.

			$modules_dir = plugin_dir_path( __DIR__ ) . 'includes/modules';
			// dirname(__FILE__) is 'includes'. So path is includes/includes/modules? NO.
			// __FILE__ is includes/class-admin-settings.php. dirname is includes.
			// So plugin_dir_path(...) is undefined behavior with strict path.
			// better: use define constant or relative.
			// Let's use the modules_dir from loader if possible? Loader is protected.
			// Let's stick to standard path: WP_GENIUS_FILE must be defined.

			if ( defined( 'WP_GENIUS_FILE' ) ) {
				$modules_root = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules';

				if ( is_dir( $modules_root ) ) {
					// We iterate the REGISTERED modules to ensure we only load options for valid modules
					// AND check if they are enabled.
					// Note: The previous logic globbed the directory.
					// Using $modules list is safer and more consistent with the loader.

					foreach ( $modules as $id => $module_instance ) {
						// Check if enabled using logic consistent with Loader
						if ( $this->loader->is_enabled( $id ) ) {
							// Determine path. Assuming folder name == ID is the convention maintained by Loader.
							$options_file = $modules_root . '/' . $id . '/options.php';

							if ( file_exists( $options_file ) ) {
								$section_config = include $options_file;

								if ( is_array( $section_config ) && isset( $section_config['module_id'] ) ) {
									CSF::createSection( $prefix, $section_config );
								}
							}
						}
					}
				}
			}
		}

		// [FIX] Pre-update hook registration moved here or kept in init?
		// We can keep it in constructor or here.
		add_filter( 'pre_update_option_w2p_settings', array( $this, 'preserve_hidden_module_settings' ), 10, 2 );
	}

	/**
	 * Preserve Hidden Module Settings
	 *
	 * Compares new settings with old settings. If a key existing in old settings is missing
	 * in new settings (and wasn't explicitly unset), we assume it's from a disabled (hidden) module.
	 *
	 * @param array $new_value New value.
	 * @param array $old_value Old value.
	 * @return array Merged value.
	 */
	public function preserve_hidden_module_settings( $new_value, $old_value ) {
		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return $new_value;
		}

		foreach ( $old_value as $key => $value ) {
			// If key existed in old but missing in new
			if ( ! array_key_exists( $key, $new_value ) ) {
				// We preserve it.
				// Note: CSF validates known fields. If a field IS visible (module enabled) and set to empty,
				// CSF usually sends it as empty string/array/0, so the key EXISTS in $new_value.
				// A MISSING key implies the field was not rendered/processed at all.
				$new_value[ $key ] = $value;
			}
		}

		return $new_value;
	}
}
