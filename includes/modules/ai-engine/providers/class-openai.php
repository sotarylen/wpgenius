<?php
/**
 * OpenAI Provider
 *
 * OpenAI API 适配器
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Provider_OpenAI
 */
class AI_Provider_OpenAI implements AI_Provider_Interface {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base = 'https://api.openai.com/v1';

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * Last usage stats
	 *
	 * @var array
	 */
	private $last_usage = [];

	/**
	 * Available models
	 *
	 * @var array
	 */
	private $models = [
		[
			'id'    => 'gpt-4o',
			'name'  => 'GPT-4o',
			'desc'  => 'Most capable model, best for complex tasks',
			'max_tokens' => 128000,
		],
		[
			'id'    => 'gpt-4o-mini',
			'name'  => 'GPT-4o Mini',
			'desc'  => 'Fast and cost-effective',
			'max_tokens' => 128000,
		],
		[
			'id'    => 'gpt-4-turbo',
			'name'  => 'GPT-4 Turbo',
			'desc'  => 'Previous generation, high capability',
			'max_tokens' => 128000,
		],
		[
			'id'    => 'gpt-3.5-turbo',
			'name'  => 'GPT-3.5 Turbo',
			'desc'  => 'Fastest and cheapest',
			'max_tokens' => 16384,
		],
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_key = $this->get_stored_api_key();
	}

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'OpenAI';
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'openai';
	}

	/**
	 * Set API key.
	 *
	 * @param string $api_key API key.
	 * @return void
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
		update_option( 'w2p_ai_openai_key', base64_encode( $api_key ) );
	}

	/**
	 * Get API key.
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		return $this->api_key;
	}

	/**
	 * Get stored API key from database.
	 *
	 * @return string
	 */
	private function get_stored_api_key(): string {
		$stored = get_option( 'w2p_ai_openai_key', '' );
		return $stored ? base64_decode( $stored ) : '';
	}

	/**
	 * Validate API key.
	 *
	 * @return bool
	 */
	public function validate_key(): bool {
		if ( empty( $this->api_key ) ) {
			return false;
		}

		$response = wp_remote_get( $this->api_base . '/models', [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'timeout' => 15,
		] );

		return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Get available models.
	 *
	 * @return array
	 */
	public function get_models(): array {
		return $this->models;
	}

	/**
	 * Generate content.
	 *
	 * @param array $params Generation parameters.
	 * @return array|WP_Error Generated content or error.
	 */
	public function generate( array $params ) {
		$prompt      = $params['prompt'] ?? '';
		$model       = $params['model'] ?? 'gpt-4o-mini';
		$temperature = $params['temperature'] ?? 0.7;
		$max_tokens  = $params['max_tokens'] ?? 2000;

		$body = [
			'model'       => $model,
			'messages'    => [
				[
					'role'    => 'system',
					'content' => 'You are a professional content writer. Write high-quality, engaging content in the requested format.',
				],
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			],
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens,
		];

		$response = wp_remote_post( $this->api_base . '/chat/completions', [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			$error_message = $response_body['error']['message'] ?? __( 'Unknown error', 'wp-genius' );
			return new WP_Error( 'openai_error', $error_message );
		}

		// Track usage
		$this->last_usage = $response_body['usage'] ?? [];
		$this->track_usage( $model, $this->last_usage );

		return [
			'content' => $response_body['choices'][0]['message']['content'] ?? '',
			'model'   => $model,
			'usage'   => $this->last_usage,
		];
	}

	/**
	 * Get last usage stats.
	 *
	 * @return array
	 */
	public function get_last_usage(): array {
		return $this->last_usage;
	}

	/**
	 * Get total usage stats.
	 *
	 * @return array
	 */
	public function get_total_usage(): array {
		$usage = get_option( 'w2p_ai_openai_usage', [
			'total_prompt_tokens'     => 0,
			'total_completion_tokens' => 0,
			'total_requests'          => 0,
		] );

		return $usage;
	}

	/**
	 * Track usage for billing.
	 *
	 * @param string $model Model name.
	 * @param array  $usage Usage data.
	 * @return void
	 */
	private function track_usage( string $model, array $usage ): void {
		$totals = $this->get_total_usage();

		$totals['total_prompt_tokens']     += $usage['prompt_tokens'] ?? 0;
		$totals['total_completion_tokens'] += $usage['completion_tokens'] ?? 0;
		$totals['total_requests']          += 1;

		// Track per-model usage
		if ( ! isset( $totals['models'][ $model ] ) ) {
			$totals['models'][ $model ] = [
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'requests'          => 0,
			];
		}

		$totals['models'][ $model ]['prompt_tokens']     += $usage['prompt_tokens'] ?? 0;
		$totals['models'][ $model ]['completion_tokens'] += $usage['completion_tokens'] ?? 0;
		$totals['models'][ $model ]['requests']          += 1;

		update_option( 'w2p_ai_openai_usage', $totals );
	}
}
