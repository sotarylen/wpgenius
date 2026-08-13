<?php
/**
 * AI Engine — AJAX Handlers
 *
 * 全部 AJAX 端点处理（生成/验证/模型/提示词/队列/调度/用量/草稿）。
 * 从 module.php 拆分（God class 重构）。
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_AI_Engine_Ajax
 */
class W2P_AI_Engine_Ajax {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_AiEngineModule
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param W2P_AiEngineModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
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
		$provider_instance = $this->module->get_provider_manager()->get_provider( $provider );
		if ( ! $provider_instance ) {
			wp_send_json_error( array( 'message' => __( 'Invalid AI provider.', 'wp-genius' ) ) );
		}

		// Validate API key
		if ( ! $provider_instance->validate_key() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid API key.', 'wp-genius' ) ) );
		}

		// Get prompt template
		$prompt = $this->module->get_prompt_engine()->get_prompt( $prompt_id );
		if ( ! $prompt ) {
			wp_send_json_error( array( 'message' => __( 'Invalid prompt template.', 'wp-genius' ) ) );
		}

		// Process variables in template
		$processed_prompt = $this->module->get_prompt_engine()->process_template( $prompt['template'], $variables );

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

		$provider_instance = $this->module->get_provider_manager()->get_provider( $provider );
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

		$provider_instance = $this->module->get_provider_manager()->get_provider( $provider );
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

		$prompt_id = $this->module->get_prompt_engine()->save_prompt(
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

		$deleted = $this->module->get_prompt_engine()->delete_prompt( $prompt_id );

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

		$queue = $this->module->get_content_queue()->get_queue( $page, $per_page );
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
		$result = $this->module->get_content_queue()->process_queue( 5 );

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

		$schedule_id = $this->module->get_scheduler()->save_schedule( $schedule_data );

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

		$deleted = $this->module->get_scheduler()->delete_schedule( $schedule_id );

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

		$new_status = $this->module->get_scheduler()->toggle_schedule( $schedule_id );

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

		$usage = $this->module->get_provider_manager()->get_all_usage();
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

		$prompt = $this->module->get_prompt_engine()->get_prompt( $prompt_id );

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
				$provider = $this->module->get_provider_manager()->get_provider( $provider_slug );
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
}
