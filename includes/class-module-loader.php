<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * 模组加载器
 *
 * 核心负责自动扫描、发现并实例化 modules/ 目录下的所有模组。
 * 管理模组的生命周期，包括根据配置启用/禁用模组，以及触发相应的钩子函数，是插件模块化架构的核心驱动。
 *
 * @package WP_Genius
 */
class W2P_Module_Loader {
	protected $modules = array();
	protected $modules_dir;

	public function __construct( $modules_dir = '' ) {
		$this->modules_dir = $modules_dir ? $modules_dir : plugin_dir_path( __FILE__ ) . 'modules/';
	}

	// 发现并包含 modules 目录下每个模块的 main 文件（约定为 module.php）
	// 按需加载：默认仅加载已启用模块；设置页需展示全部模块开关，传 $include_all=true。
	public function discover( $include_all = false ) {
		// 从 includes/modules 目录加载模块
		if ( is_dir( $this->modules_dir ) ) {
			$this->load_modules_from_directory( $this->modules_dir, $include_all );
		}
	}

	// 从指定目录加载模块
	protected function load_modules_from_directory( $directory, $include_all = false ) {
		// Use simpler glob which is faster than scandir + custom filtering often
		$dirs = glob( $directory . '*', GLOB_ONLYDIR );
		if ( ! $dirs ) {
			return;
		}

		$settings = get_option( 'w2p_settings', array() );

		foreach ( $dirs as $path ) {
			$dirname = basename( $path );

			// 已实例化（如 init() 已加载启用模块，设置页再次 discover(true) 时跳过）。
			if ( isset( $this->modules[ $dirname ] ) ) {
				continue;
			}

			// 按需加载：非管理场景（前台/Cron/REST）只加载启用模块，避免解析未启用模块代码。
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

	// 初始化已启用的模块
	public function init() {
		$this->discover();
		$settings = get_option( 'w2p_settings', array() );

		foreach ( $this->modules as $id => $module ) {
			$module_key = 'module_' . $id;
			// [Fix] Relaxed check for boolean/integer/string '1'
			$is_enabled = ! empty( $settings[ $module_key ] );

			if ( $is_enabled && method_exists( $module, 'init' ) ) {
				try {
					$module->init();
				} catch ( Exception $e ) {
					W2P_Logger::error( 'Module init error (' . $id . '): ' . $e->getMessage(), 'module-loader' );
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
	}
}
