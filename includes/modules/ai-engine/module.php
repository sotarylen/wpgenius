<?php
/**
 * AI Content Engine Module
 *
 * 基于多模型接入的 WordPress 内容自动创作系统
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Content Engine Module Class
 */
class W2P_AiEngineModule extends W2P_Abstract_Module {

	/**
	 * AJAX handler instance.
	 *
	 * @var W2P_AI_Engine_Ajax|null
	 */
	private $ajax;

	/**
	 * Module ID
	 *
	 * @return string
	 */
	public static function id() {
		return 'ai-engine';
	}
	/**
	 * Module Name
	 *
	 * @return string
	 */
	public static function name() {
		return __( 'AI Content Engine', 'wp-genius' );
	}
	/**
	 * Module Description
	 *
	 * @return string
	 */
	public static function description() {
		return __( 'AI-powered content creation engine with multi-model support and scheduled generation.', 'wp-genius' );
	}
	/**
	 * Module Icon
	 *
	 * @return string
	 */
	public static function icon() {
		return 'fa-solid fa-robot';
	}
	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
		// Load dependencies
		$this->load_dependencies();

		// Initialize components
		$this->provider_manager = new W2P_AI_Provider_Manager();
		$this->prompt_engine    = new W2P_AI_Prompt_Engine();
		$this->content_queue    = new W2P_AI_Content_Queue();
		$this->scheduler        = new W2P_AI_Scheduler();

		// 装配 AJAX 职责类（God class 拆分）。
		require_once __DIR__ . '/includes/class-ajax.php';
		$this->ajax = new W2P_AI_Engine_Ajax( $this );

		// Load CSF Options
		$this->load_options();

		// Register hooks
		$this->register_hooks();

		// Create database tables
		$this->maybe_create_tables();
	}
	/**
	 * Load Dependencies
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$classes_dir   = __DIR__ . '/classes/';
		$providers_dir = __DIR__ . '/providers/';

		// Load core classes
		require_once $classes_dir . 'class-provider-manager.php';
		require_once $classes_dir . 'class-prompt-engine.php';
		require_once $classes_dir . 'class-content-queue.php';
		require_once $classes_dir . 'class-scheduler.php';

		// Load providers
		require_once $providers_dir . 'class-provider-interface.php';
		require_once $providers_dir . 'class-openai.php';
		require_once $providers_dir . 'class-anthropic.php';
		require_once $providers_dir . 'class-gemini.php';
		require_once $providers_dir . 'class-deepseek.php';
	}
	/**
	 * Load CSF Options
	 *
	 * @return void
	 */
	private function load_options() {
		$options_path = __DIR__ . '/options.php';
		if ( file_exists( $options_path ) && class_exists( 'CSF' ) ) {
			require_once $options_path;
		}
	}
	/**
	 * Register Hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_w2p_ai_generate', array( $this->ajax, 'ajax_generate_content' ) );
		add_action( 'wp_ajax_w2p_ai_validate_key', array( $this->ajax, 'ajax_validate_api_key' ) );
		add_action( 'wp_ajax_w2p_ai_get_models', array( $this->ajax, 'ajax_get_models' ) );
		add_action( 'wp_ajax_w2p_ai_get_prompt', array( $this->ajax, 'ajax_get_prompt' ) );
		add_action( 'wp_ajax_w2p_ai_save_prompt', array( $this->ajax, 'ajax_save_prompt' ) );
		add_action( 'wp_ajax_w2p_ai_delete_prompt', array( $this->ajax, 'ajax_delete_prompt' ) );
		add_action( 'wp_ajax_w2p_ai_get_queue', array( $this->ajax, 'ajax_get_queue' ) );
		add_action( 'wp_ajax_w2p_ai_process_queue', array( $this->ajax, 'ajax_process_queue' ) );
		add_action( 'wp_ajax_w2p_ai_save_schedule', array( $this->ajax, 'ajax_save_schedule' ) );
		add_action( 'wp_ajax_w2p_ai_delete_schedule', array( $this->ajax, 'ajax_delete_schedule' ) );
		add_action( 'wp_ajax_w2p_ai_toggle_schedule', array( $this->ajax, 'ajax_toggle_schedule' ) );
		add_action( 'wp_ajax_w2p_ai_get_usage', array( $this->ajax, 'ajax_get_usage' ) );
		add_action( 'wp_ajax_w2p_ai_save_settings', array( $this->ajax, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_w2p_ai_create_draft', array( $this->ajax, 'ajax_create_draft' ) );

		// Cron hooks
		add_action( 'w2p_ai_content_generation', array( $this->scheduler, 'run_scheduled_tasks' ) );
		add_action( 'w2p_ai_queue_processor', array( $this->content_queue, 'process_queue' ) );

		// Settings saved hook
		add_action( 'csf_w2p_settings_saved', array( $this, 'on_settings_saved' ) );
	}
	/**
	 * Get Provider Manager instance
	 *
	 * @return W2P_AI_Provider_Manager
	 */
	public function get_provider_manager() {
		return $this->provider_manager;
	}
	/**
	 * Get Prompt Engine instance
	 *
	 * @return W2P_AI_Prompt_Engine
	 */
	public function get_prompt_engine() {
		return $this->prompt_engine;
	}
	/**
	 * Get Content Queue instance
	 *
	 * @return W2P_AI_Content_Queue
	 */
	public function get_content_queue() {
		return $this->content_queue;
	}
	/**
	 * Get Scheduler instance
	 *
	 * @return W2P_AI_Scheduler
	 */
	public function get_scheduler() {
		return $this->scheduler;
	}
	/**
	 * Enqueue Admin Assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'wp-genius-ai-engine' ) === false &&
			strpos( $hook, 'wp-genius-settings' ) === false ) {
			return;
		}

		$plugin_url = plugin_dir_url( WP_GENIUS_FILE );

		// CSS
		wp_enqueue_style(
			'w2p-ai-engine',
			$plugin_url . 'includes/modules/ai-engine/assets/css/ai-engine.css',
			array(),
			W2P_VERSION
		);

		// JS
		wp_enqueue_script(
			'w2p-ai-engine',
			$plugin_url . 'includes/modules/ai-engine/assets/js/ai-engine.js',
			array( 'jquery', 'wp-util' ),
			W2P_VERSION,
			true
		);

		// Localize data
		wp_localize_script(
			'w2p-ai-engine',
			'w2pAIEngine',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'w2p_ai_engine_nonce' ),
				'i18n'     => array(
					'generating'     => __( 'Generating content...', 'wp-genius' ),
					'generated'      => __( 'Content generated successfully!', 'wp-genius' ),
					'error'          => __( 'An error occurred.', 'wp-genius' ),
					'confirm_delete' => __( 'Are you sure you want to delete this?', 'wp-genius' ),
					'validating'     => __( 'Validating API key...', 'wp-genius' ),
					'valid'          => __( 'API key is valid!', 'wp-genius' ),
					'invalid'        => __( 'API key is invalid.', 'wp-genius' ),
				),
			)
		);
	}
	/**
	 * Render Admin Page
	 *
	 * @return void
	 */
	public function render_admin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading tab parameter for display only.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'generate';

		include __DIR__ . '/templates/admin-page.php';
	}
	/**
	 * Create Database Tables
	 *
	 * @return void
	 */
	private function maybe_create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'w2p_ai_prompts';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			type VARCHAR(50) NOT NULL DEFAULT 'custom',
			template TEXT NOT NULL,
			variables TEXT,
			provider VARCHAR(50) DEFAULT NULL,
			model VARCHAR(100) DEFAULT NULL,
			temperature FLOAT DEFAULT 0.7,
			max_tokens INT DEFAULT 2000,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
	/**
	 * On Settings Saved
	 *
	 * @return void
	 */
	public function on_settings_saved() {
		// Update cron schedule based on settings.
		$this->scheduler->update_cron_schedule();
	}
	/**
	 * AJAX: ajax_generate_content（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_generate_content() {
		$this->ajax->ajax_generate_content();
	}
	/**
	 * AJAX: ajax_validate_api_key（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_validate_api_key() {
		$this->ajax->ajax_validate_api_key();
	}
	/**
	 * AJAX: ajax_get_models（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_get_models() {
		$this->ajax->ajax_get_models();
	}
	/**
	 * AJAX: ajax_save_prompt（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_save_prompt() {
		$this->ajax->ajax_save_prompt();
	}
	/**
	 * AJAX: ajax_delete_prompt（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_delete_prompt() {
		$this->ajax->ajax_delete_prompt();
	}
	/**
	 * AJAX: ajax_get_queue（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_get_queue() {
		$this->ajax->ajax_get_queue();
	}
	/**
	 * AJAX: ajax_process_queue（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_process_queue() {
		$this->ajax->ajax_process_queue();
	}
	/**
	 * AJAX: ajax_save_schedule（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_save_schedule() {
		$this->ajax->ajax_save_schedule();
	}
	/**
	 * AJAX: ajax_delete_schedule（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_delete_schedule() {
		$this->ajax->ajax_delete_schedule();
	}
	/**
	 * AJAX: ajax_toggle_schedule（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_toggle_schedule() {
		$this->ajax->ajax_toggle_schedule();
	}
	/**
	 * AJAX: ajax_get_usage（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_get_usage() {
		$this->ajax->ajax_get_usage();
	}
	/**
	 * AJAX: ajax_get_prompt（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_get_prompt() {
		$this->ajax->ajax_get_prompt();
	}
	/**
	 * AJAX: ajax_save_settings（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		$this->ajax->ajax_save_settings();
	}
	/**
	 * AJAX: ajax_create_draft（委托至 W2P_AI_Engine_Ajax）。
	 *
	 * @return void
	 */
	public function ajax_create_draft() {
		$this->ajax->ajax_create_draft();
	}
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'AiEngineModule', false ) ) {
	class_alias( 'W2P_AiEngineModule', 'AiEngineModule' );
}
