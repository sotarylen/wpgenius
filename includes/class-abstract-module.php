<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Abstract base class for modules
 *
 * Defines the standard structure and common methods all modules must inherit, including initialization, settings registration, view rendering, and configuration retrieval.
 * Subclasses are required to implement specific interfaces, ensuring consistency of the module architecture.
 *
 * @package WP_Genius
 */

abstract class W2P_Abstract_Module {
	// Unique module ID (defaults to the directory name)
	public static function id() {
		return '';
	}

	public static function name() {
		return '';
	}

	public static function description() {
		return '';
	}

	public static function icon() {
		return 'fa-solid fa-puzzle-piece'; // Default icon
	}

	// Called during plugin initialization
	public function init() {}

	// Register module settings section on the settings page (optional)
	public function register_settings() {}

	/**
	 * Render a view template for the module.
	 *
	 * @param string $view_name Name of the template (without .php).
	 * @param array  $args      Variables to pass to the template.
	 */
	public function render_view( $view_name, $args = array() ) {
		$file = plugin_dir_path( ( new ReflectionClass( get_called_class() ) )->getFileName() ) . $view_name . '.php';

		if ( file_exists( $file ) ) {
			// Extract variables to local scope for template rendering.
			if ( ! empty( $args ) ) {
				// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Intentional: passing variables to view templates.
				extract( $args );
			}

			// Standard data available to all views
			$module_id = static::id();
			$settings  = $this->get_settings();
			$nonce     = wp_create_nonce( 'w2p_' . str_replace( '-', '_', $module_id ) . '_nonce' );

			include $file;
		} else {
			W2P_Logger::error( "View template not found: {$file}", 'core' );
		}
	}

	/**
	 * Get module-specific settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		// [Refactor] Unify settings storage.
		// All settings are now stored in 'w2p_settings' by CSF.
		$all_settings = get_option( 'w2p_settings', array() );

		// If the module overrides settings_key() to point to something else (legacy support), use that.
		// But default behavior is now to use the global store.
		if ( $this->settings_key() !== 'w2p_settings' ) {
			return get_option( $this->settings_key(), array() );
		}

		return $all_settings;
	}

	/**
	 * Get the settings key for the module.
	 *
	 * @return string
	 */
	public function settings_key() {
		// [Refactor] Default to global settings key
		return 'w2p_settings';
	}

	/**
	 * Check if module requirements/dependencies are satisfied.
	 *
	 * @return true|WP_Error Returns true if satisfied, or WP_Error with user-facing message.
	 */
	public function check_requirements() {
		return true;
	}

	// Activation/deactivation hooks (optional)
	public function activate() {}
	public function deactivate() {}
}
