<?php
/**
 * SMTP Mailer Module
 * 
 * Provides SMTP email configuration for WordPress.
 * Replaces hardcoded wp-config.php settings with configurable module.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SMTP Mailer Module Class
 */
class SMTPMailerModule extends W2P_Abstract_Module {

	/**
	 * Module ID
	 *
	 * @return string
	 */
	public static function id() {
		return 'smtp-mailer';
	}

	/**
	 * Module Name
	 *
	 * @return string
	 */
	public static function name() {
		return __( 'SMTP Mail Configuration', 'wp-genius' );
	}

	/**
	 * Module Description
	 *
	 * @return string
	 */
	public static function description() {
		return __( 'Configure SMTP email settings for reliable email delivery.', 'wp-genius' );
	}

	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
		// Load test connection handler early (before any HTML output)
		if ( isset( $_GET['smtp_test'] ) && '1' === $_GET['smtp_test'] ) {
			require_once __DIR__ . '/test-connection.php';
		}

		// Register settings
		$this->register_settings();

		// Hook into phpmailer_init to apply SMTP configuration
		add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );

		// Admin notices for SMTP status
		add_action( 'admin_notices', [ $this, 'display_smtp_status' ] );
	}

	/**
	 * Register Module Settings
	 *
	 * @return void
	 */
	public function register_settings() {
		$defaults = [
			'smtp_host'       => 'smtp.gmail.com',
			'smtp_port'       => '465',
			'smtp_secure'     => 'ssl',
			'smtp_auth'       => true,
			'smtp_username'   => '',
			'smtp_password'   => '',
			'smtp_from_email' => get_option( 'admin_email' ),
			'smtp_from_name'  => get_option( 'blogname' ),
		];

		$settings = get_option( 'w2p_smtp_settings', [] );
		$settings = wp_parse_args( $settings, $defaults );
		update_option( 'w2p_smtp_settings', $settings );
	}

	/**
	 * Configure SMTP Settings
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance
	 * @return void
	 */
	public function configure_smtp( $phpmailer ) {
		if ( ! $this->is_module_enabled() ) {
			return;
		}

		$settings = get_option( 'w2p_smtp_settings', [] );

		// Skip if no SMTP host configured
		if ( empty( $settings['smtp_host'] ) || empty( $settings['smtp_username'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = sanitize_text_field( $settings['smtp_host'] );
		$phpmailer->SMTPAuth   = (bool) $settings['smtp_auth'];
		$phpmailer->Port       = (int) $settings['smtp_port'];
		$phpmailer->SMTPSecure = sanitize_text_field( $settings['smtp_secure'] );
		$phpmailer->Username   = sanitize_text_field( $settings['smtp_username'] );
		$phpmailer->Password   = $settings['smtp_password']; // Password is not sanitized as per WordPress conventions
		$phpmailer->From       = sanitize_email( $settings['smtp_from_email'] );
		$phpmailer->FromName   = sanitize_text_field( $settings['smtp_from_name'] );

		// Optional: Uncomment to disable SSL verification for development
		// $phpmailer->SMTPOptions = [
		//     'ssl' => [
		//         'verify_peer'       => false,
		//         'verify_peer_name'  => false,
		//     ],
		// ];
	}

	/**
	 * Check if module is enabled
	 *
	 * @return bool
	 */
	private function is_module_enabled() {
		$modules = get_option( 'w2p_active_modules', [] );
		return isset( $modules[ $this::id() ] ) && $modules[ $this::id() ];
	}

	/**
	 * Display SMTP Connection Status Notice
	 *
	 * @return void
	 */
	public function display_smtp_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'wp-genius-settings' !== $_GET['page'] ) {
			return;
		}

		if ( ! $this->is_module_enabled() ) {
			return;
		}

		$settings = get_option( 'w2p_smtp_settings', [] );

		if ( empty( $settings['smtp_host'] ) || empty( $settings['smtp_username'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo esc_html__( 'SMTP Mail Configuration is enabled but not fully configured. Please enter host and username.', 'wp-genius' );
			echo '</p></div>';
			return;
		}

		// Test SMTP connection (optional, on-demand)
		if ( isset( $_GET['smtp_test'] ) && '1' === $_GET['smtp_test'] ) {
			// Verify nonce if needed (optional security check)
			$this->test_smtp_connection( $settings );
		}
	}

	/**
	 * Test SMTP Connection
	 *
	 * @param array $settings SMTP settings
	 * @return void
	 */
	private function test_smtp_connection( $settings ) {
		require_once ABSPATH . WPINC . '/class-phpmailer.php';
		require_once ABSPATH . WPINC . '/class-smtp.php';

		$phpmailer = new PHPMailer\PHPMailer\PHPMailer( true );

		try {
			$phpmailer->isSMTP();
			$phpmailer->Host       = $settings['smtp_host'];
			$phpmailer->SMTPAuth   = (bool) $settings['smtp_auth'];
			$phpmailer->Port       = (int) $settings['smtp_port'];
			$phpmailer->SMTPSecure = $settings['smtp_secure'];
			$phpmailer->Username   = $settings['smtp_username'];
			$phpmailer->Password   = $settings['smtp_password'];

			// Attempt connection
			if ( $phpmailer->smtpConnect( [
				'ssl' => [
					'verify_peer'       => false,
					'verify_peer_name'  => false,
				],
			] ) ) {
				$phpmailer->smtpClose();
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html__( 'SMTP connection successful!', 'wp-genius' );
				echo '</p></div>';
			}
		} catch ( \Exception $e ) {
			echo '<div class="notice notice-error is-dismissible"><p>';
			echo esc_html__( 'SMTP connection failed: ', 'wp-genius' ) . esc_html( $e->getMessage() );
			echo '</p></div>';
		}
	}

	/**
	 * Module Activation Hook
	 *
	 * @return void
	 */
	public function activate() {
		do_action( 'w2p_smtp_activated' );
	}

	/**
	 * Module Deactivation Hook
	 *
	 * @return void
	 */
	public function deactivate() {
		do_action( 'w2p_smtp_deactivated' );
	}

	public function render_settings() {
		$this->render_view( 'settings' );
	}

	public function settings_key() {
		return 'w2p_smtp_settings';
	}
}
