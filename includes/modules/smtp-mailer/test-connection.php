<?php
/**
 * SMTP Mailer Test Connection Handler
 * 
 * This file handles AJAX SMTP connection testing.
 * Must be loaded before WordPress outputs any HTML.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

// Only run if this is an AJAX SMTP test request
if ( ! isset( $_GET['smtp_test'] ) || '1' !== $_GET['smtp_test'] || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
	return;
}

// Check permissions and nonce
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	wp_send_json_error( __( 'No permission', 'wp-genius' ) );
}

if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'w2p_smtp_test_nonce' ) ) {
    wp_send_json_error( __( 'Invalid security token', 'wp-genius' ) );
}

// Get SMTP settings from POST
$settings = isset( $_POST['w2p_smtp_settings'] ) ? (array) $_POST['w2p_smtp_settings'] : [];

if ( empty( $settings['smtp_host'] ) || empty( $settings['smtp_username'] ) ) {
	wp_send_json_error( __( 'Please enter SMTP host and username', 'wp-genius' ) );
}

// Load PHPMailer
require_once ABSPATH . WPINC . '/class-phpmailer.php';
require_once ABSPATH . WPINC . '/class-smtp.php';

$phpmailer = new PHPMailer\PHPMailer\PHPMailer( true );

try {
	// Get configuration
	$host   = sanitize_text_field( $settings['smtp_host'] );
	$port   = absint( $settings['smtp_port'] ?? 465 );
	$secure = sanitize_text_field( $settings['smtp_secure'] ?? 'ssl' );
	$user   = sanitize_text_field( $settings['smtp_username'] );
	$pass   = $settings['smtp_password'] ?? '';
	$auth   = ! empty( $settings['smtp_auth'] );

	// Validate port/encryption combination
	if ( ( $port === 465 && $secure !== 'ssl' ) || ( $port === 587 && $secure !== 'tls' ) ) {
		$suggested = ( $port === 465 ) ? 'SSL' : 'TLS';
		wp_send_json_error( 
			sprintf(
				__( 'Port %d typically uses %s. Please verify your settings.', 'wp-genius' ),
				$port,
				$suggested
			)
		);
	}

	// Configure PHPMailer
	$phpmailer->isSMTP();
	$phpmailer->Host       = $host;
	$phpmailer->SMTPAuth   = $auth;
	$phpmailer->Port       = $port;
	$phpmailer->SMTPSecure = $secure;
	$phpmailer->Username   = $user;
	$phpmailer->Password   = $pass;
	$phpmailer->Timeout    = 10;

	// Attempt connection
	$connected = false;
	try {
		$connected = @$phpmailer->smtpConnect( [
			'ssl' => [
				'verify_peer'       => false,
				'verify_peer_name'  => false,
			],
		] );
	} catch ( Exception $e ) {
		wp_send_json_error( $e->getMessage() );
	}

	if ( $connected ) {
		$phpmailer->smtpClose();
		wp_send_json_success( __( 'SMTP connection successful!', 'wp-genius' ) );
	} else {
		$error = $phpmailer->ErrorInfo ?: __( 'Connection failed. Please check your settings.', 'wp-genius' );
		wp_send_json_error( $error );
	}

} catch ( Exception $e ) {
	wp_send_json_error( 'Error: ' . $e->getMessage() );
}
