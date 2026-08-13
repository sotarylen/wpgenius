<?php
/**
 * Provider Manager
 *
 * AI 供应商管理器
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Provider_Manager
 */
class AI_Provider_Manager {

	/**
	 * Registered providers
	 *
	 * @var array
	 */
	private $providers = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_default_providers();
	}

	/**
	 * Register default providers
	 *
	 * @return void
	 */
	private function register_default_providers() {
		$this->register_provider( new AI_Provider_OpenAI() );
		$this->register_provider( new AI_Provider_Anthropic() );
		$this->register_provider( new AI_Provider_Gemini() );
		$this->register_provider( new AI_Provider_DeepSeek() );
	}

	/**
	 * Register a provider
	 *
	 * @param AI_Provider_Interface $provider Provider instance.
	 * @return void
	 */
	public function register_provider( AI_Provider_Interface $provider ): void {
		$this->providers[ $provider->get_slug() ] = $provider;
	}

	/**
	 * Get a provider by slug
	 *
	 * @param string $slug Provider slug.
	 * @return AI_Provider_Interface|null
	 */
	public function get_provider( string $slug ): ?AI_Provider_Interface {
		return $this->providers[ $slug ] ?? null;
	}

	/**
	 * Get all registered providers
	 *
	 * @return array
	 */
	public function get_all_providers(): array {
		return $this->providers;
	}

	/**
	 * Get provider list for UI
	 *
	 * @return array
	 */
	public function get_providers_list(): array {
		$list = [];

		foreach ( $this->providers as $slug => $provider ) {
			$list[] = [
				'slug'  => $slug,
				'name'  => $provider->get_name(),
				'key'   => get_option( 'w2p_ai_' . $slug . '_key', '' ) ? true : false,
			];
		}

		return $list;
	}

	/**
	 * Get all usage stats
	 *
	 * @return array
	 */
	public function get_all_usage(): array {
		$usage = [];

		foreach ( $this->providers as $slug => $provider ) {
			$usage[ $slug ] = [
				'name'  => $provider->get_name(),
				'usage' => $provider->get_total_usage(),
			];
		}

		return $usage;
	}
}
