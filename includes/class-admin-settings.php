<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Admin settings class
 *
 * Responsible for initializing the plugin settings page built on the CSF framework and loading global configuration options.
 * Specifically handles settings-data protection for hidden/disabled modules, preventing loss of existing configuration data when a module is disabled.
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
	 * Register global settings (logic from the former includes/admin/options.php merged here)
	 */
	public function register_settings() {
		// Global Settings Prefix
		$prefix = 'w2p_settings';

		// 1. Initialize Framework
		// CSF is already required during plugin file loading (is_admin guard); this is only a defensive check.
		// Note: do not defer require to this point - CSF registers its init hook (setup) when the file is loaded,
		// a deferred require would make setup miss init and fail to register the settings menu.
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
					'menu_title'      => 'WP Genius',
					'menu_slug'       => 'wp-genius-settings',
					'menu_type'       => 'submenu',
					'menu_parent'     => 'tools.php',
					'framework_title' => 'WP Genius',
					'show_sub_menu'   => false,
					'theme'           => 'light',
				)
			);

			// 2. Section 1: Module Management
			$module_fields = array();

			// Use the injected loader instance instead of creating a new one
			$this->loader->discover( true );
			$modules = $this->loader->get_available_modules();

			if ( ! empty( $modules ) ) {
				foreach ( $modules as $id => $module ) {
					$name = method_exists( $module, 'name' ) ? $module->name() : ucfirst( $id );
					$desc = method_exists( $module, 'description' ) ? $module->description() : '';

					$req_error = null;
					if ( method_exists( $module, 'check_requirements' ) ) {
						$req = $module->check_requirements();
						if ( is_wp_error( $req ) ) {
							$req_error = $req->get_error_message();
						}
					}

					$field_config = array(
						'id'       => 'module_' . $id,
						'type'     => 'switcher',
						'title'    => $name,
						'subtitle' => $desc,
						'default'  => false,
					);

					if ( ! empty( $req_error ) ) {
						$field_config['subtitle']  .= '<div class="w2p-req-error" style="color:#d63638; margin-top:6px; font-weight:500;"><i class="fa-solid fa-triangle-exclamation"></i> ' . esc_html( $req_error ) . '</div>';
						$field_config['attributes'] = array( 'disabled' => 'disabled' );
					}

					$module_fields[] = $field_config;
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
			if ( defined( 'WP_GENIUS_FILE' ) ) {
				$modules_root = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules';

				if ( is_dir( $modules_root ) ) {
					foreach ( $modules as $id => $module_instance ) {
						// Check if enabled and requirements met
						if ( $this->loader->is_enabled( $id ) ) {
							if ( method_exists( $module_instance, 'check_requirements' ) && is_wp_error( $module_instance->check_requirements() ) ) {
								continue;
							}

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

		add_filter( 'pre_update_option_w2p_settings', array( $this, 'preserve_hidden_module_settings' ), 10, 2 );
	}

	/**
	 * Preserve Hidden Module Settings and Validate Requirements
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

		// Validate module requirements when enabling modules.
		$modules = $this->loader->get_available_modules();
		$errors  = array();

		foreach ( $modules as $id => $module ) {
			$module_key = 'module_' . $id;
			if ( ! empty( $new_value[ $module_key ] ) && method_exists( $module, 'check_requirements' ) ) {
				$req = $module->check_requirements();
				if ( is_wp_error( $req ) ) {
					// Disallow activation and revert to disabled
					$new_value[ $module_key ] = false;
					$errors[]                 = $req->get_error_message();
				}
			}
		}

		foreach ( $old_value as $key => $value ) {
			// If key existed in old but missing in new
			if ( ! array_key_exists( $key, $new_value ) ) {
				$new_value[ $key ] = $value;
			}
		}

		return $new_value;
	}
}
