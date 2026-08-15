<?php
/**
 * Anthropic Provider
 *
 * Anthropic Claude API adapter
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_AI_Provider_Anthropic
 */
class W2P_AI_Provider_Anthropic implements W2P_AI_Provider_Interface {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base = 'https://api.anthropic.com/v1';

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
	private $last_usage = array();

	/**
	 * Available models
	 *
	 * @var array
	 */
	private $models = array(
		array(
			'id'         => 'claude-sonnet-4-20250514',
			'name'       => 'Claude Sonnet 4',
			'desc'       => 'Best balance of intelligence and speed',
			'max_tokens' => 200000,
		),
		array(
			'id'         => 'claude-3-5-sonnet-20241022',
			'name'       => 'Claude 3.5 Sonnet',
			'desc'       => 'High capability, previous generation',
			'max_tokens' => 200000,
		),
		array(
			'id'         => 'claude-3-5-haiku-20241022',
			'name'       => 'Claude 3.5 Haiku',
			'desc'       => 'Fast and cost-effective',
			'max_tokens' => 200000,
		),
		array(
			'id'         => 'claude-3-opus-20240229',
			'name'       => 'Claude 3 Opus',
			'desc'       => 'Most capable, for complex tasks',
			'max_tokens' => 200000,
		),
	);

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
		return 'Anthropic';
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'anthropic';
	}

	/**
	 * Set API key.
	 *
	 * @param string $api_key API key.
	 * @return void
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
		update_option( 'w2p_ai_anthropic_key', W2P_Crypto::encrypt( $api_key ) );
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
		$stored = get_option( 'w2p_ai_anthropic_key', '' );
		if ( empty( $stored ) ) {
			return '';
		}

		$key = W2P_Crypto::decrypt( $stored );

		// Legacy base64-encoded value: decode and re-encrypt on read (lazy migration).
		if ( null === $key ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required to migrate legacy plaintext-key storage.
			$key = base64_decode( $stored );
			if ( is_string( $key ) && '' !== $key ) {
				update_option( 'w2p_ai_anthropic_key', W2P_Crypto::encrypt( $key ) );
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

		// Anthropic doesn't have a simple validation endpoint, so we try a minimal request
		$response = wp_remote_post(
			$this->api_base . '/messages',
			array(
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'claude-3-5-haiku-20241022',
						'max_tokens' => 10,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'Hi',
							),
						),
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		// 200 = valid key, 401 = invalid key
		return 200 === $response_code || 400 === $response_code;
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
		$model       = $params['model'] ?? 'claude-sonnet-4-20250514';
		$temperature = $params['temperature'] ?? 0.7;
		$max_tokens  = $params['max_tokens'] ?? 2000;

		$body = array(
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'system'      => 'You are a professional content writer. Write high-quality, engaging content in the requested format.',
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'temperature' => $temperature,
		);

		$response = wp_remote_post(
			$this->api_base . '/messages',
			array(
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			$error_message = $response_body['error']['message'] ?? __( 'Unknown error', 'wp-genius' );
			return new WP_Error( 'anthropic_error', $error_message );
		}

		// Track usage
		$this->last_usage = $response_body['usage'] ?? array();
		$this->track_usage( $model, $this->last_usage );

		return array(
			'content' => $response_body['content'][0]['text'] ?? '',
			'model'   => $model,
			'usage'   => $this->last_usage,
		);
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
		return get_option(
			'w2p_ai_anthropic_usage',
			array(
				'total_input_tokens'  => 0,
				'total_output_tokens' => 0,
				'total_requests'      => 0,
			)
		);
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

		$totals['total_input_tokens']  += $usage['input_tokens'] ?? 0;
		$totals['total_output_tokens'] += $usage['output_tokens'] ?? 0;
		$totals['total_requests']      += 1;

		// Track per-model usage
		if ( ! isset( $totals['models'][ $model ] ) ) {
			$totals['models'][ $model ] = array(
				'input_tokens'  => 0,
				'output_tokens' => 0,
				'requests'      => 0,
			);
		}

		$totals['models'][ $model ]['input_tokens']  += $usage['input_tokens'] ?? 0;
		$totals['models'][ $model ]['output_tokens'] += $usage['output_tokens'] ?? 0;
		$totals['models'][ $model ]['requests']      += 1;

		update_option( 'w2p_ai_anthropic_usage', $totals );
	}
}

// Legacy alias for backward compatibility (pre-1.2.0 class name).
if ( ! class_exists( 'AI_Provider_Anthropic', false ) ) {
	class_alias( 'W2P_AI_Provider_Anthropic', 'AI_Provider_Anthropic' );
}
