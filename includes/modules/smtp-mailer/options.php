<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

return [
    'module_id' => 'smtp-mailer', // Critical: Must match directory name
    'title'  => __( 'SMTP Mailer', 'wp-genius' ),
    'icon'   => 'fa fa-envelope',
    'fields' => [
        [
            'id'      => '_subheading_smtp_server',
            'type'    => 'subheading',
            'content' => __( 'SMTP Server Configuration', 'wp-genius' ),
        ],
        [
            'id'      => 'smtp_host',
            'type'    => 'text',
            'title'   => __( 'SMTP Host', 'wp-genius' ),
            'desc'    => __( 'SMTP server hostname (e.g., smtp.gmail.com, smtp.sendgrid.net)', 'wp-genius' ),
            'default' => 'smtp.gmail.com',
        ],
        [
            'id'      => 'smtp_port',
            'type'    => 'number',
            'title'   => __( 'SMTP Port', 'wp-genius' ),
            'desc'    => __( 'Typical ports: 465 (SSL), 587 (TLS), 25 (unencrypted)', 'wp-genius' ),
            'default' => '465',
        ],
        [
            'id'      => 'smtp_secure',
            'type'    => 'select',
            'title'   => __( 'Security', 'wp-genius' ),
            'desc'    => __( 'Encryption method for SMTP connection', 'wp-genius' ),
            'options' => [
                'ssl' => __( 'SSL (port 465)', 'wp-genius' ),
                'tls' => __( 'TLS (port 587)', 'wp-genius' ),
                ''    => __( 'None (port 25)', 'wp-genius' ),
            ],
            'default' => 'ssl',
        ],
        [
            'id'    => 'smtp_auth',
            'type'  => 'switcher',
            'title' => __( 'Authentication Needed', 'wp-genius' ),
            'label' => __( 'Most SMTP servers require authentication to send mail.', 'wp-genius' ),
            'default' => true,
        ],
        [
            'id'      => 'smtp_username',
            'type'    => 'text',
            'title'   => __( 'SMTP Username', 'wp-genius' ),
            'desc'    => __( 'SMTP account username (usually email address)', 'wp-genius' ),
        ],
        [
            'id'      => 'smtp_password',
            'type'    => 'text',
            'title'   => __( 'SMTP Password', 'wp-genius' ),
            'desc'    => __( 'SMTP account password or app-specific password', 'wp-genius' ),
            'attributes' => [
                'type' => 'password',
            ],
        ],
        [
            'id'      => '_subheading_email_sender',
            'type'    => 'subheading',
            'content' => __( 'Email Sender Settings', 'wp-genius' ),
        ],
        [
            'id'      => 'smtp_from_email',
            'type'    => 'text',
            'title'   => __( 'From Email', 'wp-genius' ),
            'desc'    => __( 'Email address that appears as sender', 'wp-genius' ),
            'default' => get_option( 'admin_email' ),
        ],
        [
            'id'      => 'smtp_from_name',
            'type'    => 'text',
            'title'   => __( 'From Name', 'wp-genius' ),
            'desc'    => __( 'Name that appears as sender', 'wp-genius' ),
            'default' => get_option( 'blogname' ),
        ],
        [
            'type'    => 'subheading',
            'content' => __( 'Test Connection', 'wp-genius' ),
        ],
        [
            'type'    => 'content',
            'content' => '
                <button type="button" class="w2p-btn w2p-btn-primary" id="w2p-test-smtp">
                    <i class="fa-solid fa-paper-plane"></i> ' . __( 'Send Test Email', 'wp-genius' ) . '
                </button>
            ',
        ],
    ],
];