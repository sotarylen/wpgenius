<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module loader
 *
 * The core automatically scans, discovers, and instantiates all modules in the modules/ directory.
 * It manages the module lifecycle, including enabling/disabling modules per configuration and triggering the corresponding hooks; it is the core driver of the plugin's modular architecture.
 *
 * @package WP_Genius
 */
class W2P_Module_Loader {
	protected $modules = array();
	protected $modules_dir;

	public function __construct( $modules_dir = '' ) {
		$this->modules_dir = $modules_dir ? $modules_dir : plugin_dir_path( __FILE__ ) . 'modules/';
	}

	// Discover and include each module's main file in the modules directory (conventionally module.php)
	// Lazy loading: by default only enabled modules are loaded; the settings page needs to show all module toggles, pass $include_all=true.
	public function discover( $include_all = false ) {
		// Load modules from the includes/modules directory
		if ( is_dir( $this->modules_dir ) ) {
			$this->load_modules_from_directory( $this->modules_dir, $include_all );
		}
	}

	// Load modules from the specified directory
	protected function load_modules_from_directory( $directory, $include_all = false ) {
		// Use simpler glob which is faster than scandir + custom filtering often
		$dirs = glob( $directory . '*', GLOB_ONLYDIR );
		if ( ! $dirs ) {
			return;
		}

		$settings = get_option( 'w2p_settings', array() );

		foreach ( $dirs as $path ) {
			$dirname = basename( $path );

			// Already instantiated (e.g. init() already loaded enabled modules; skip when the settings page calls discover(true) again).
			if ( isset( $this->modules[ $dirname ] ) ) {
				continue;
			}

			// Lazy loading: non-admin contexts (front-end/Cron/REST) only load enabled modules to avoid parsing disabled module code.
			if ( ! $include_all ) {
				$module_key = 'module_' . $dirname;
				$is_enabled = ! empty( $settings[ $module_key ] );
				if ( ! $is_enabled ) {
					continue;
				}
			}

			$class = $this->class_name_from_dir( $dirname );
			$found = false;

			// 1. Check if class is already loaded or can be autoloaded (Composer)
			if ( class_exists( $class ) ) {
				$found = true;
			} else {
				// 2. Fallback to manual file check
				$main = $path . '/module.php';
				if ( file_exists( $main ) ) {
					include_once $main;
					if ( class_exists( $class ) ) {
						$found = true;
					}
				}
			}

			if ( $found ) {
				try {
					$this->modules[ $dirname ] = new $class();
				} catch ( Exception $e ) {
					W2P_Logger::error( 'Module instantiation failed: ' . $e->getMessage(), 'module-loader' );
				}
			}
		}
	}

	protected function class_name_from_dir( $dir ) {
		$parts = preg_split( '/[-_]/', $dir );
		$parts = array_map( 'ucfirst', $parts );
		return 'W2P_' . implode( '', $parts ) . 'Module';
	}

	// Initialize enabled modules
	public function init() {
		$this->discover();
		$settings = get_option( 'w2p_settings', array() );

		foreach ( $this->modules as $id => $module ) {
			$module_key = 'module_' . $id;
			// [Fix] Relaxed check for boolean/integer/string '1'
			$is_enabled = ! empty( $settings[ $module_key ] );

			if ( $is_enabled ) {
				if ( method_exists( $module, 'check_requirements' ) ) {
					$req = $module->check_requirements();
					if ( is_wp_error( $req ) ) {
						W2P_Logger::warning( 'Module requirement check failed (' . $id . '): ' . $req->get_error_message(), 'module-loader' );
						continue;
					}
				}

				if ( method_exists( $module, 'init' ) ) {
					try {
						$module->init();
					} catch ( Exception $e ) {
						W2P_Logger::error( 'Module init error (' . $id . '): ' . $e->getMessage(), 'module-loader' );
					}
				}
			}
		}
	}

	public function get_available_modules() {
		return $this->modules;
	}

	// Check if module is enabled (unified method)
	public function is_enabled( $id ) {
		$settings   = get_option( 'w2p_settings', array() );
		$module_key = 'module_' . $id;
		return ! empty( $settings[ $module_key ] );
	}

	// Set module enabled state (unified method)
	public function set_enabled( $id, $state ) {
		$settings   = get_option( 'w2p_settings', array() );
		$module_key = 'module_' . $id;

		$old_state = isset( $settings[ $module_key ] ) ? (bool) $settings[ $module_key ] : false;
		$new_state = (bool) $state;

		if ( $new_state && isset( $this->modules[ $id ] ) && method_exists( $this->modules[ $id ], 'check_requirements' ) ) {
			$req = $this->modules[ $id ]->check_requirements();
			if ( is_wp_error( $req ) ) {
				return $req;
			}
		}

		if ( $old_state !== $new_state ) {
			$settings[ $module_key ] = $new_state;
			update_option( 'w2p_settings', $settings );

			// Trigger module hooks
			if ( isset( $this->modules[ $id ] ) ) {
				if ( $new_state && method_exists( $this->modules[ $id ], 'enable' ) ) {
					$this->modules[ $id ]->enable();
				} elseif ( ! $new_state && method_exists( $this->modules[ $id ], 'disable' ) ) {
					$this->modules[ $id ]->disable();
				}
			}
		}

		return true;
	}
}
