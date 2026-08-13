<?php
/**
 * Google Gemini Provider
 *
 * Google Gemini API 适配器
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Provider_Gemini
 */
class AI_Provider_Gemini implements AI_Provider_Interface {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base = 'https://generativelanguage.googleapis.com/v1beta';

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
			'id'          => 'gemini-1.5-pro',
			'name'        => 'Gemini 1.5 Pro',
			'desc'        => 'Best for complex reasoning and long context',
			'max_tokens'  => 2097152,
		],
		[
			'id'          => 'gemini-1.5-flash',
			'name'        => 'Gemini 1.5 Flash',
			'desc'        => 'Fast and cost-effective',
			'max_tokens'  => 1048576,
		],
		[
			'id'          => 'gemini-1.0-pro',
			'name'        => 'Gemini 1.0 Pro',
			'desc'        => 'Previous generation',
			'max_tokens'  => 32768,
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
		return 'Google Gemini';
	}

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'gemini';
	}

	/**
	 * Set API key.
	 *
	 * @param string $api_key API key.
	 * @return void
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
		update_option( 'w2p_ai_gemini_key', base64_encode( $api_key ) );
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
		$stored = get_option( 'w2p_ai_gemini_key', '' );
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

		$response = wp_remote_get(
			$this->api_base . '/models?key=' . $this->api_key,
			[ 'timeout' => 15 ]
		);

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
		$model       = $params['model'] ?? 'gemini-1.5-flash';
		$temperature = $params['temperature'] ?? 0.7;
		$max_tokens  = $params['max_tokens'] ?? 2000;

		$body = [
			'contents' => [
				[
					'parts' => [
						[
							'text' => $prompt,
						],
					],
				],
			],
			'generationConfig' => [
				'temperature'     => $temperature,
				'maxOutputTokens' => $max_tokens,
			],
			'systemInstruction' => [
				'parts' => [
					[
						'text' => 'You are a professional content writer. Write high-quality, engaging content in the requested format.',
					],
				],
			],
		];

		$response = wp_remote_post(
			$this->api_base . '/models/' . $model . ':generateContent?key=' . $this->api_key,
			[
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			$error_message = $response_body['error']['message'] ?? __( 'Unknown error', 'wp-genius' );
			return new WP_Error( 'gemini_error', $error_message );
		}

		// Track usage
		$this->last_usage = $response_body['usageMetadata'] ?? [];
		$this->track_usage( $model, $this->last_usage );

		return [
			'content' => $response_body['candidates'][0]['content']['parts'][0]['text'] ?? '',
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
		return get_option( 'w2p_ai_gemini_usage', [
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

		$totals['total_prompt_tokens']     += $usage['promptTokenCount'] ?? 0;
		$totals['total_completion_tokens'] += $usage['candidatesTokenCount'] ?? 0;
		$totals['total_requests']          += 1;

		// Track per-model usage
		if ( ! isset( $totals['models'][ $model ] ) ) {
			$totals['models'][ $model ] = [
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'requests'          => 0,
			];
		}

		$totals['models'][ $model ]['prompt_tokens']     += $usage['promptTokenCount'] ?? 0;
		$totals['models'][ $model ]['completion_tokens'] += $usage['candidatesTokenCount'] ?? 0;
		$totals['models'][ $model ]['requests']          += 1;

		update_option( 'w2p_ai_gemini_usage', $totals );
	}
}
