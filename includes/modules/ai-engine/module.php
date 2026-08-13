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
	 * Provider Manager Instance
	 *
	 * @var W2P_AI_Provider_Manager|null
	 */
	private $provider_manager = null;

	/**
	 * Prompt Engine Instance
	 *
	 * @var W2P_AI_Prompt_Engine|null
	 */
	private $prompt_engine = null;

	/**
	 * Content Queue Instance
	 *
	 * @var W2P_AI_Content_Queue|null
	 */
	private $content_queue = null;

	/**
	 * Scheduler Instance
	 *
	 * @var W2P_AI_Scheduler|null
	 */
	private $scheduler = null;

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
		add_action( 'wp_ajax_w2p_ai_generate', array( $this, 'ajax_generate_content' ) );
		add_action( 'wp_ajax_w2p_ai_validate_key', array( $this, 'ajax_validate_api_key' ) );
		add_action( 'wp_ajax_w2p_ai_get_models', array( $this, 'ajax_get_models' ) );
		add_action( 'wp_ajax_w2p_ai_get_prompt', array( $this, 'ajax_get_prompt' ) );
		add_action( 'wp_ajax_w2p_ai_save_prompt', array( $this, 'ajax_save_prompt' ) );
		add_action( 'wp_ajax_w2p_ai_delete_prompt', array( $this, 'ajax_delete_prompt' ) );
		add_action( 'wp_ajax_w2p_ai_get_queue', array( $this, 'ajax_get_queue' ) );
		add_action( 'wp_ajax_w2p_ai_process_queue', array( $this, 'ajax_process_queue' ) );
		add_action( 'wp_ajax_w2p_ai_save_schedule', array( $this, 'ajax_save_schedule' ) );
		add_action( 'wp_ajax_w2p_ai_delete_schedule', array( $this, 'ajax_delete_schedule' ) );
		add_action( 'wp_ajax_w2p_ai_toggle_schedule', array( $this, 'ajax_toggle_schedule' ) );
		add_action( 'wp_ajax_w2p_ai_get_usage', array( $this, 'ajax_get_usage' ) );
		add_action( 'wp_ajax_w2p_ai_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_w2p_ai_create_draft', array( $this, 'ajax_create_draft' ) );

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
			'1.2.0'
		);

		// JS
		wp_enqueue_script(
			'w2p-ai-engine',
			$plugin_url . 'includes/modules/ai-engine/assets/js/ai-engine.js',
			array( 'jquery', 'wp-util' ),
			'1.2.0',
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
	 * AJAX: Generate Content
	 *
	 * @return void
	 */
	public function ajax_generate_content() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$prompt_id = isset( $_POST['prompt_id'] ) ? absint( $_POST['prompt_id'] ) : 0;
		$provider  = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$model     = isset( $_POST['model'] ) ? sanitize_text_field( $_POST['model'] ) : '';
		$variables = isset( $_POST['variables'] ) ? map_deep( wp_unslash( $_POST['variables'] ), 'sanitize_text_field' ) : array();
		$quantity  = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

		if ( empty( $provider ) || empty( $model ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a provider and model.', 'wp-genius' ) ) );
		}

		// Get provider instance
		$provider_instance = $this->provider_manager->get_provider( $provider );
		if ( ! $provider_instance ) {
			wp_send_json_error( array( 'message' => __( 'Invalid AI provider.', 'wp-genius' ) ) );
		}

		// Validate API key
		if ( ! $provider_instance->validate_key() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid API key.', 'wp-genius' ) ) );
		}

		// Get prompt template
		$prompt = $this->prompt_engine->get_prompt( $prompt_id );
		if ( ! $prompt ) {
			wp_send_json_error( array( 'message' => __( 'Invalid prompt template.', 'wp-genius' ) ) );
		}

		// Process variables in template
		$processed_prompt = $this->prompt_engine->process_template( $prompt['template'], $variables );

		// Generate content
		$results = array();
		for ( $i = 0; $i < $quantity; $i++ ) {
			$result = $provider_instance->generate(
				array(
					'prompt'      => $processed_prompt,
					'model'       => $model,
					'temperature' => $prompt['temperature'] ?? 0.7,
					'max_tokens'  => $prompt['max_tokens'] ?? 2000,
				)
			);

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			$results[] = $result;
		}

		wp_send_json_success(
			array(
				'content' => $results,
				'usage'   => $provider_instance->get_last_usage(),
			)
		);
	}

	/**
	 * AJAX: Validate API Key
	 *
	 * @return void
	 */
	public function ajax_validate_api_key() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';

		if ( empty( $provider ) || empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide provider and API key.', 'wp-genius' ) ) );
		}

		$provider_instance = $this->provider_manager->get_provider( $provider );
		if ( ! $provider_instance ) {
			wp_send_json_error( array( 'message' => __( 'Invalid AI provider.', 'wp-genius' ) ) );
		}

		$provider_instance->set_api_key( $api_key );
		$is_valid = $provider_instance->validate_key();

		if ( $is_valid ) {
			wp_send_json_success( array( 'message' => __( 'API key is valid.', 'wp-genius' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'API key is invalid.', 'wp-genius' ) ) );
		}
	}

	/**
	 * AJAX: Get Available Models
	 *
	 * @return void
	 */
	public function ajax_get_models() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';

		$provider_instance = $this->provider_manager->get_provider( $provider );
		if ( ! $provider_instance ) {
			wp_send_json_error( array( 'message' => __( 'Invalid AI provider.', 'wp-genius' ) ) );
		}

		$models = $provider_instance->get_models();
		wp_send_json_success( array( 'models' => $models ) );
	}

	/**
	 * AJAX: Save Prompt
	 *
	 * @return void
	 */
	public function ajax_save_prompt() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$name        = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$template    = isset( $_POST['template'] ) ? wp_kses_post( $_POST['template'] ) : '';
		$variables   = isset( $_POST['variables'] ) ? map_deep( wp_unslash( $_POST['variables'] ), 'sanitize_text_field' ) : array();
		$temperature = isset( $_POST['temperature'] ) ? floatval( $_POST['temperature'] ) : 0.7;
		$max_tokens  = isset( $_POST['max_tokens'] ) ? absint( $_POST['max_tokens'] ) : 2000;

		if ( empty( $name ) || empty( $template ) ) {
			wp_send_json_error( array( 'message' => __( 'Name and template are required.', 'wp-genius' ) ) );
		}

		$prompt_id = $this->prompt_engine->save_prompt(
			array(
				'name'        => $name,
				'template'    => $template,
				'variables'   => $variables,
				'temperature' => $temperature,
				'max_tokens'  => $max_tokens,
			)
		);

		if ( $prompt_id ) {
			wp_send_json_success(
				array(
					'id'      => $prompt_id,
					'message' => __( 'Prompt saved.', 'wp-genius' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save prompt.', 'wp-genius' ) ) );
		}
	}

	/**
	 * AJAX: Delete Prompt
	 *
	 * @return void
	 */
	public function ajax_delete_prompt() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$prompt_id = isset( $_POST['prompt_id'] ) ? absint( $_POST['prompt_id'] ) : 0;

		if ( ! $prompt_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid prompt ID.', 'wp-genius' ) ) );
		}

		$deleted = $this->prompt_engine->delete_prompt( $prompt_id );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'Prompt deleted.', 'wp-genius' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete prompt.', 'wp-genius' ) ) );
		}
	}

	/**
	 * AJAX: Get Queue
	 *
	 * @return void
	 */
	public function ajax_get_queue() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$page     = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		$per_page = 20;

		$queue = $this->content_queue->get_queue( $page, $per_page );
		wp_send_json_success( $queue );
	}

	/**
	 * AJAX: Process Queue
	 *
	 * @return void
	 */
	public function ajax_process_queue() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		// 手动触发可处理更大批量（浏览器端 AJAX 生命周期内）。
		$result = $this->content_queue->process_queue( 5 );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Queue processed.', 'wp-genius' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to process queue.', 'wp-genius' ) ) );
		}
	}

	/**
	 * AJAX: Save Schedule
	 *
	 * @return void
	 */
	public function ajax_save_schedule() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$schedule_data = array(
			'name'       => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
			'provider'   => isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '',
			'model'      => isset( $_POST['model'] ) ? sanitize_text_field( $_POST['model'] ) : '',
			'prompt_id'  => isset( $_POST['prompt_id'] ) ? absint( $_POST['prompt_id'] ) : 0,
			'frequency'  => isset( $_POST['frequency'] ) ? sanitize_text_field( $_POST['frequency'] ) : 'daily',
			'time'       => isset( $_POST['time'] ) ? sanitize_text_field( $_POST['time'] ) : '09:00',
			'quantity'   => isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1,
			'status'     => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'draft',
			'categories' => isset( $_POST['categories'] ) ? array_map( 'absint', (array) $_POST['categories'] ) : array(),
			'tags'       => isset( $_POST['tags'] ) ? array_map( 'sanitize_text_field', (array) $_POST['tags'] ) : array(),
			'featured'   => isset( $_POST['featured'] ) ? (bool) $_POST['featured'] : false,
			'variables'  => isset( $_POST['variables'] ) ? map_deep( wp_unslash( $_POST['variables'] ), 'sanitize_text_field' ) : array(),
		);

		$schedule_id = $this->scheduler->save_schedule( $schedule_data );

		if ( $schedule_id ) {
			wp_send_json_success(
				array(
					'id'      => $schedule_id,
					'message' => __( 'Schedule saved.', 'wp-genius' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save schedule.', 'wp-genius' ) ) );
		}
	}

	/**
	 * AJAX: Delete Schedule
	 *
	 * @return void
	 */
	public function ajax_delete_schedule() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$schedule_id = isset( $_POST['schedule_id'] ) ? absint( $_POST['schedule_id'] ) : 0;

		if ( ! $schedule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid schedule ID.', 'wp-genius' ) ) );
		}

		$deleted = $this->scheduler->delete_schedule( $schedule_id );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'Schedule deleted.', 'wp-genius' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete schedule.', 'wp-genius' ) ) );
		}
	}

	/**
	 * AJAX: Toggle Schedule
	 *
	 * @return void
	 */
	public function ajax_toggle_schedule() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$schedule_id = isset( $_POST['schedule_id'] ) ? absint( $_POST['schedule_id'] ) : 0;

		if ( ! $schedule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid schedule ID.', 'wp-genius' ) ) );
		}

		$new_status = $this->scheduler->toggle_schedule( $schedule_id );

		wp_send_json_success(
			array(
				'status'  => $new_status,
				'message' => $new_status ? __( 'Schedule enabled.', 'wp-genius' ) : __( 'Schedule disabled.', 'wp-genius' ),
			)
		);
	}

	/**
	 * AJAX: Get Usage Stats
	 *
	 * @return void
	 */
	public function ajax_get_usage() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$usage = $this->provider_manager->get_all_usage();
		wp_send_json_success( array( 'usage' => $usage ) );
	}

	/**
	 * AJAX: Get Single Prompt
	 *
	 * @return void
	 */
	public function ajax_get_prompt() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$prompt_id = isset( $_POST['prompt_id'] ) ? absint( $_POST['prompt_id'] ) : 0;

		if ( ! $prompt_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid prompt ID.', 'wp-genius' ) ) );
		}

		$prompt = $this->prompt_engine->get_prompt( $prompt_id );

		if ( ! $prompt ) {
			wp_send_json_error( array( 'message' => __( 'Prompt not found.', 'wp-genius' ) ) );
		}

		wp_send_json_success( array( 'prompt' => $prompt ) );
	}

	/**
	 * AJAX: Save API Settings
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$api_keys = isset( $_POST['api_keys'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['api_keys'] ) ) : array();

		$providers = array( 'openai', 'anthropic', 'gemini', 'deepseek' );

		foreach ( $providers as $provider_slug ) {
			if ( ! empty( $api_keys[ $provider_slug ] ) ) {
				$provider = $this->provider_manager->get_provider( $provider_slug );
				if ( $provider ) {
					$provider->set_api_key( $api_keys[ $provider_slug ] );
				}
			}
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'wp-genius' ) ) );
	}

	/**
	 * AJAX: Create Draft Post from AI Content
	 *
	 * @return void
	 */
	public function ajax_create_draft() {
		check_ajax_referer( 'w2p_ai_engine_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ) );
		}

		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

		if ( empty( $title ) || empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Title and content are required.', 'wp-genius' ) ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_type'    => 'post',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		// Mark as AI generated.
		update_post_meta( $post_id, '_w2p_ai_generated', true );

		wp_send_json_success(
			array(
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link( $post_id, 'raw' ),
				'message'  => __( 'Post created.', 'wp-genius' ),
			)
		);
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
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'AiEngineModule', false ) ) {
	class_alias( 'W2P_AiEngineModule', 'AiEngineModule' );
}
