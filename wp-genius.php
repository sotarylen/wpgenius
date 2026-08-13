<?php
/**
 * Plugin Name: WP Genius
 * Description: A comprehensive toolkit for WordPress content management, optimization, and automation (Auto-Publish, Media Engine, System Health, and more).
 * Version: 1.2.0
 * Author: Sotary
 * Text Domain: wp-genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin constants
define( 'WP_GENIUS_FILE', __FILE__ );
define( 'W2P_VERSION', '1.2.0' );
define( 'W2P_DB_VERSION', '1.0' );

// Include module framework (abstracts, loader, admin settings)
require_once plugin_dir_path( __FILE__ ) . 'includes/class-abstract-module.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-module-loader.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-task-queue.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-security.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-settings.php';

// ---------- Activation / Deactivation lifecycle (G2/G3) ----------

/**
 * 创建/升级自定义表（schema 版本控制）。
 *
 * 由激活钩子与 w2p_core_init 调用：当记录的 w2p_db_version 落后于当前版本时，
 * 重新触发各表 dbDelta（幂等），保证升级迁移。
 *
 * @return void
 */
function w2p_maybe_create_tables() {
	if ( get_option( 'w2p_db_version' ) === W2P_DB_VERSION ) {
		return;
	}

	// 触发 AI 引擎表创建（类构造时执行 dbDelta，幂等）。
	$ai_base = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules/ai-engine/';
	if ( file_exists( $ai_base . 'providers/class-provider-interface.php' ) ) {
		require_once $ai_base . 'providers/class-provider-interface.php';
		require_once $ai_base . 'providers/class-openai.php';
		require_once $ai_base . 'providers/class-anthropic.php';
		require_once $ai_base . 'providers/class-gemini.php';
		require_once $ai_base . 'providers/class-deepseek.php';
		require_once $ai_base . 'classes/class-provider-manager.php';
		require_once $ai_base . 'classes/class-prompt-engine.php';
		require_once $ai_base . 'classes/class-content-queue.php';
		require_once $ai_base . 'classes/class-scheduler.php';

		// 构造即触发 prompts/queue/schedules 三张表创建。
		new W2P_AI_Prompt_Engine();
		$provider_manager = new W2P_AI_Provider_Manager();
		new W2P_AI_Content_Queue( $provider_manager, new W2P_AI_Prompt_Engine() );
		new W2P_AI_Scheduler( $provider_manager );
	}

	update_option( 'w2p_db_version', W2P_DB_VERSION );
}

/**
 * 插件激活：建表 + 触发各模块 activate()。
 *
 * @return void
 */
function w2p_activate() {
	w2p_maybe_create_tables();

	$loader = new W2P_Module_Loader( plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules/' );
	$loader->discover( true );
	foreach ( $loader->get_available_modules() as $module ) {
		if ( method_exists( $module, 'activate' ) ) {
			$module->activate();
		}
	}
}

/**
 * 插件停用：清除 cron，触发各模块 deactivate()。不删除任何用户数据。
 *
 * @return void
 */
function w2p_deactivate() {
	// 清除插件注册的定时任务。
	$hooks = array(
		'w2p_ai_content_generation',
		'w2p_ai_queue_processor',
		'w2p_auto_publish_cron',
		'w2p_media_turbo_cron',
		'w2p_smart_aui_cleanup',
	);
	foreach ( $hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	$loader = new W2P_Module_Loader( plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules/' );
	$loader->discover( true );
	foreach ( $loader->get_available_modules() as $module ) {
		if ( method_exists( $module, 'deactivate' ) ) {
			$module->deactivate();
		}
	}
}

register_activation_hook( WP_GENIUS_FILE, 'w2p_activate' );
register_deactivation_hook( WP_GENIUS_FILE, 'w2p_deactivate' );

// CSF (codestar-framework) 仅在 admin 请求加载，且必须在 init 钩子触发前完成 require：
// CSF 在文件加载时注册 init 钩子（setup），setup 于 init priority 10 执行时才实例化 CSF_Options
// 并注册 admin_menu 菜单。若延迟到 admin_init 才加载，init 已错过，设置菜单将永不注册。
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/csf/codestar-framework.php';
}

/**
 * Initialize the plugin (runs on init hook after translations are loaded)
 */
function w2p_core_init() {
	// Load plugin textdomain first
	load_plugin_textdomain( 'wp-genius', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	try {
		// 升级时（w2p_db_version 落后）触发自定义表创建/迁移（幂等）。
		w2p_maybe_create_tables();

		// Initialize module loader
		$module_loader = new W2P_Module_Loader( plugin_dir_path( __FILE__ ) . 'includes/modules/' );

		// 注册设置页（仅 admin）。必须在 init priority 5 执行（早于 CSF setup 的 priority 10）：
		// CSF::createOptions 收集配置，CSF::setup 在 init 10 时读取配置并实例化 CSF_Options，
		// 进而注册 admin_menu 菜单。延迟到 admin_init 会导致配置收集晚于 setup 而菜单不出现。
		// 前台/Cron/REST 因 is_admin() 守卫不执行，仍然保持轻量。
		if ( is_admin() ) {
			new W2P_Admin_Settings( $module_loader );
		}

		// Initialize and load enabled modules
		$module_loader->init();
	} catch ( Exception $e ) {
		W2P_Logger::error( 'WP Genius Init Error: ' . $e->getMessage(), 'core' );
		if ( is_admin() ) {
			add_action(
				'admin_notices',
				function () use ( $e ) {
					// translators: %s: Error message.
					echo '<div class="error"><p>' . sprintf( esc_html__( 'WP Genius Error: %s', 'wp-genius' ), esc_html( $e->getMessage() ) ) . '</p></div>';
				}
			);
		}
	}
}
add_action( 'init', 'w2p_core_init', 5 ); // Priority 5 to run after core init but before most other plugins

/**
 * Register and enqueue core admin assets
 */
function w2p_core_enqueue_scripts() {
	try {
		$is_plugin_page = false;

		if ( is_admin() ) {
			$screen = get_current_screen();
			if ( $screen ) {
				$is_plugin_page = (
					strpos( $screen->id, 'wp-genius' ) !== false ||
					in_array( $screen->id, array( 'post', 'edit-post', 'tools', 'edit-page', 'page' ), true ) ||
					in_array( $screen->base, array( 'post', 'edit', 'upload' ), true )
				);
			}
		} else {
			// Frontend: Only enqueue if explicitly enabled via filter (default false to improve performance)
			$is_plugin_page = apply_filters( 'w2p_load_frontend_assets', false );
		}

			// Register Assets Globally
			wp_register_style( 'w2p-core-css', plugin_dir_url( __FILE__ ) . 'assets/css/core.css', array(), W2P_VERSION );
			wp_register_script( 'w2p-admin-ui', plugin_dir_url( __FILE__ ) . 'assets/js/w2p-admin-ui.js', array( 'jquery' ), '1.0.0', true );
			wp_localize_script(
				'w2p-admin-ui',
				'w2p_ui_i18n',
				array(
					'confirm'        => __( 'Confirm', 'wp-genius' ),
					'cancel'         => __( 'Cancel', 'wp-genius' ),
					'confirm_title'  => __( 'Confirmation', 'wp-genius' ),
					'settings_saved' => __( 'Settings saved successfully!', 'wp-genius' ),
				)
			);
			wp_register_script( 'w2p-range-slider', plugin_dir_url( __FILE__ ) . 'assets/js/range-slider.js', array( 'jquery' ), '1.0.0', true );
			wp_register_script( 'w2p-core-js', plugin_dir_url( __FILE__ ) . 'assets/js/w2p-admin-core.js', array( 'jquery', 'w2p-admin-ui' ), '1.0.1', true );

		if ( $is_plugin_page ) {
			// External dependencies
			wp_enqueue_style( 'jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css', array(), '1.12.1' );
			wp_enqueue_script( 'jquery-ui-tabs' );
			wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0' );

			// Enqueue Core
			wp_enqueue_style( 'w2p-core-css' );
			wp_enqueue_script( 'w2p-admin-ui' );
			wp_enqueue_script( 'w2p-core-js' );
		}
	} catch ( Exception $e ) {
		W2P_Logger::error( 'WP Genius Enqueue Error: ' . $e->getMessage(), 'core' );
	}
}
add_action( 'admin_enqueue_scripts', 'w2p_core_enqueue_scripts' );
add_action( 'wp_enqueue_scripts', 'w2p_core_enqueue_scripts' );
