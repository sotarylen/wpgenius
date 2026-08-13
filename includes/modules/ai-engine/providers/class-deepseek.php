<?php
/**
 * DeepSeek Provider
 *
 * DeepSeek API 适配器
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_AI_Provider_DeepSeek
 */
class W2P_AI_Provider_DeepSeek implements W2P_AI_Provider_Interface {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base = 'https://api.deepseek.com/v1';

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
			'id'          => 'deepseek-chat',
			'name'        => 'DeepSeek V3',
			'desc'        => 'General-purpose chat model',
			'max_tokens'  => 64000,
		],
		[
			'id'          => 'deepseek-reasoner',
			'name'        => 'DeepSeek R1',
			'desc'        => 'Reasoning model for complex tasks',
			'max_tokens'  => 64000,
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
		return 'DeepSeek';
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'deepseek';
	}

	/**
	 * Set API key.
	 *
	 * @param string $api_key API key.
	 * @return void
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
		update_option( 'w2p_ai_deepseek_key', W2P_Crypto::encrypt( $api_key ) );
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
		$stored = get_option( 'w2p_ai_deepseek_key', '' );
		if ( empty( $stored ) ) {
			return '';
		}

		$key = W2P_Crypto::decrypt( $stored );

		// Legacy base64-encoded value: decode and re-encrypt on read (lazy migration).
		if ( null === $key ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required to migrate legacy plaintext-key storage.
			$key = base64_decode( $stored );
			if ( is_string( $key ) && '' !== $key ) {
				update_option( 'w2p_ai_deepseek_key', W2P_Crypto::encrypt( $key ) );
			}
		}

		return is_string( $key ) ? $key : '';
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

		// DeepSeek uses OpenAI-compatible API
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
		$model       = $params['model'] ?? 'deepseek-chat';
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
			return new WP_Error( 'deepseek_error', $error_message );
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
		return get_option( 'w2p_ai_deepseek_usage', [
			'total_prompt_tokens'     => 0,
			'total_completion_tokens' => 0,
			'total_requests'          => 0,
		] );
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

		update_option( 'w2p_ai_deepseek_usage', $totals );
	}
}

// Legacy alias for backward compatibility (pre-1.2.0 class name).
if ( ! class_exists( 'AI_Provider_DeepSeek', false ) ) {
	class_alias( 'W2P_AI_Provider_Deepseek', 'AI_Provider_DeepSeek' );
}
