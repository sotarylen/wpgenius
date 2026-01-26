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
		return __( 'SMTP Mail', 'wp-genius' );
	}

	/**
	 * Module Description
	 *
	 * @return string
	 */
	public static function description() {
		return __( 'Configure SMTP email settings for reliable email delivery.', 'wp-genius' );
	}

	public static function icon() {
		return 'fa-solid fa-envelope';
	}

	/**
	 * Check if module is enabled
	 */
	public function is_enabled() {
		$settings = get_option('w2p_settings', []);
		return !empty($settings['module_' . $this->id()]);
	}

	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
        // AJAX Handler for testing
        add_action( 'wp_ajax_w2p_smtp_test', [ $this, 'handle_ajax_test' ] );

		// Hook into phpmailer_init to apply SMTP configuration
		add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );

        // Asset loading
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'wp-genius-settings' ) === false ) {
            return;
        }

        // Only enqueue if this module section is active (optional optimization)
        // For now, load on settings page if the module is enabled.

        $module_url = plugin_dir_url( __FILE__ );
        
        wp_enqueue_script(
            'w2p-smtp-settings',
            $module_url . 'assets/js/smtp-settings.js',
            [ 'jquery', 'w2p-admin-ui' ], // Depend on core admin UI if available
            '1.0.0',
            true
        );

        wp_localize_script( 'w2p-smtp-settings', 'w2p_smtp_data', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'w2p_smtp_test_nonce' ),
            'strings'  => [
                'success'       => __( 'SMTP Connected Successfully!', 'wp-genius' ),
                'unknown_error' => __( 'Unknown Error', 'wp-genius' ),
                'fail_prefix'   => __( 'Connection Failed: ', 'wp-genius' ),
                'network_error' => __( 'Network Error', 'wp-genius' ),
            ]
        ] );
    }



	/**
	 * Configure SMTP Settings
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance
	 * @return void
	 */
	public function configure_smtp( $phpmailer ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$settings = $this->get_settings();

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
	}



    /**
     * Handle AJAX SMTP Test
     */
    public function handle_ajax_test() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'No permission', 'wp-genius' ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'w2p_smtp_test_nonce' ) ) {
            wp_send_json_error( __( 'Invalid security token', 'wp-genius' ) );
        }

        $posted_settings = isset( $_POST['w2p_smtp_settings'] ) ? $_POST['w2p_smtp_settings'] : [];
        $saved_settings = $this->get_settings();
        $settings = array_merge( $saved_settings, $posted_settings );

        if ( empty( $settings['smtp_host'] ) ) {
             wp_send_json_error( __( 'Missing SMTP configuration', 'wp-genius' ) );
        }

        $result = $this->test_smtp_connection( $settings );

        if ( $result['success'] ) {
            wp_send_json_success( $result['message'] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

	/**
	 * Test SMTP Connection
	 *
	 * @param array $settings SMTP settings
	 * @return array ['success' => bool, 'message' => string]
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
            // Increase timeout for testing
            $phpmailer->Timeout    = 10;

			// Attempt connection
			if ( $phpmailer->smtpConnect( [
				'ssl' => [
					'verify_peer'       => false,
					'verify_peer_name'  => false,
				],
			] ) ) {
				$phpmailer->smtpClose();
                return [ 'success' => true, 'message' => __( 'SMTP connection successful!', 'wp-genius' ) ];
			}
            
            return [ 'success' => false, 'message' => __( 'Connection refused by server.', 'wp-genius' ) ];
            
		} catch ( \Exception $e ) {
            return [ 'success' => false, 'message' => $e->getMessage() ];
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
}
