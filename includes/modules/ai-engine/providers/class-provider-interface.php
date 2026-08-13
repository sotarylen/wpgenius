<?php
/**
 * AI Provider Interface
 *
 * 所有 AI 供应商必须实现此接口
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface W2P_AI_Provider_Interface
 */
interface W2P_AI_Provider_Interface {

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Set API key.
	 *
	 * @param string $api_key API key.
	 * @return void
	 */
	public function set_api_key( string $api_key ): void;

	/**
	 * Get API key.
	 *
	 * @return string
	 */
	public function get_api_key(): string;

	/**
	 * Validate API key.
	 *
	 * @return bool
	 */
	public function validate_key(): bool;

	/**
	 * Get available models.
	 *
	 * @return array
	 */
	public function get_models(): array;

	/**
	 * Generate content.
	 *
	 * @param array $params Generation parameters.
	 * @return array|WP_Error Generated content or error.
	 */
	public function generate( array $params );

	/**
	 * Get last usage stats.
	 *
	 * @return array
	 */
	public function get_last_usage(): array;

	/**
	 * Get total usage stats.
	 *
	 * @return array
	 */
	public function get_total_usage(): array;
}

// Legacy alias for backward compatibility (pre-1.2.0 class name).
if ( ! interface_exists( 'AI_Provider_Interface', false ) ) {
	class_alias( 'W2P_AI_Provider_Interface', 'AI_Provider_Interface' );
}
