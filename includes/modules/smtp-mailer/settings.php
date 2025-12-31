<?php
/**
 * SMTP Mailer Module Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'w2p_smtp_settings', [] );
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
$settings = wp_parse_args( $settings, $defaults );
?>

<div class="w2p-settings-panel w2p-smtp-settings">
    <div class="w2p-flex w2p-gap-xl">
        <!-- Main Configuration Section -->
        <div class="w2p-flex-6">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="w2p-smtp-form">
                <?php wp_nonce_field('word2posts_save_module_settings', 'w2p_smtp_mailer_nonce'); ?>
                <input type="hidden" name="action" value="word2posts_save_module_settings" />
                <input type="hidden" name="module_id" value="smtp-mailer" />

                <div class="w2p-section">
                    <div class="w2p-section-header">
                        <h4><?php esc_html_e('SMTP Mail Configuration', 'wp-genius'); ?></h4>
                    </div>
                    <div class="w2p-section-body">
                        <!-- SMTP Host -->
                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label for="smtp_host"><?php esc_html_e('SMTP Host', 'wp-genius'); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <input type="text" id="smtp_host" name="w2p_smtp_settings[smtp_host]" value="<?php echo esc_attr($settings['smtp_host']); ?>" placeholder="smtp.gmail.com" class="w2p-input-large" />
                                <p class="description"><?php esc_html_e('SMTP server hostname (e.g., smtp.gmail.com, smtp.sendgrid.net)', 'wp-genius'); ?></p>
                            </div>
                        </div>

                        <!-- SMTP Port -->
                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label for="smtp_port"><?php esc_html_e('SMTP Port', 'wp-genius'); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <input type="number" id="smtp_port" name="w2p_smtp_settings[smtp_port]" value="<?php echo esc_attr($settings['smtp_port']); ?>" placeholder="465" min="1" max="65535" class="w2p-input-small" />
                                <p class="description"><?php esc_html_e('Typical ports: 465 (SSL), 587 (TLS), 25 (unencrypted)', 'wp-genius'); ?></p>
                            </div>
                        </div>

                        <!-- SMTP Security -->
                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label for="smtp_secure"><?php esc_html_e('Security', 'wp-genius'); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <select id="smtp_secure" name="w2p_smtp_settings[smtp_secure]" class="w2p-input-medium">
                                    <option value="ssl" <?php selected($settings['smtp_secure'], 'ssl'); ?>><?php esc_html_e('SSL (port 465)', 'wp-genius'); ?></option>
                                    <option value="tls" <?php selected($settings['smtp_secure'], 'tls'); ?>><?php esc_html_e('TLS (port 587)', 'wp-genius'); ?></option>
                                    <option value="" <?php selected($settings['smtp_secure'], ''); ?>><?php esc_html_e('None (port 25)', 'wp-genius'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Encryption method for SMTP connection', 'wp-genius'); ?></p>
                            </div>
                        </div>

                        <!-- SMTP Authentication -->
                        <div class="w2p-form-row">
                            <div class="w2p-form-label"><?php esc_html_e('Authentication Needed', 'wp-genius'); ?></div>
                            <div class="w2p-form-control">
                                <label class="w2p-switch">
                                    <input type="checkbox" name="w2p_smtp_settings[smtp_auth]" value="1" <?php checked($settings['smtp_auth'], true); ?> />
                                    <span class="w2p-slider"></span>
                                </label>
                                <p class="description"><?php esc_html_e('Most SMTP servers require authentication to send mail.', 'wp-genius'); ?></p>
                            </div>
                        </div>

                        <!-- SMTP Username -->
                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label for="smtp_username"><?php esc_html_e('SMTP Username', 'wp-genius'); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <input type="email" id="smtp_username" name="w2p_smtp_settings[smtp_username]" value="<?php echo esc_attr($settings['smtp_username']); ?>" placeholder="your-email@gmail.com" class="w2p-input-large" />
                                <p class="description"><?php esc_html_e('SMTP account username (usually email address)', 'wp-genius'); ?></p>
                            </div>
                        </div>

                        <!-- SMTP Password -->
                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label for="smtp_password"><?php esc_html_e('SMTP Password', 'wp-genius'); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <input type="password" id="smtp_password" name="w2p_smtp_settings[smtp_password]" value="<?php echo esc_attr($settings['smtp_password']); ?>" placeholder="••••••••••••••••" class="w2p-input-large" />
                                <p class="description"><?php esc_html_e('SMTP account password or app-specific password', 'wp-genius'); ?></p>
                            </div>
                        </div>

                        <!-- From Email -->
                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label for="smtp_from_email"><?php esc_html_e('From Email', 'wp-genius'); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <input type="email" id="smtp_from_email" name="w2p_smtp_settings[smtp_from_email]" value="<?php echo esc_attr($settings['smtp_from_email']); ?>" class="w2p-input-large" />
                                <p class="description"><?php esc_html_e('Email address that appears as sender', 'wp-genius'); ?></p>
                            </div>
                        </div>

                        <!-- From Name -->
                        <div class="w2p-form-row border-none">
                            <div class="w2p-form-label">
                                <label for="smtp_from_name"><?php esc_html_e('From Name', 'wp-genius'); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <input type="text" id="smtp_from_name" name="w2p_smtp_settings[smtp_from_name]" value="<?php echo esc_attr($settings['smtp_from_name']); ?>" class="w2p-input-large" />
                                <p class="description"><?php esc_html_e('Name that appears as sender', 'wp-genius'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w2p-settings-actions">
                    <button type="submit" class="button button-primary" id="w2p-save-smtp">
                        <?php esc_html_e('Save SMTP Settings', 'wp-genius'); ?>
                    </button>
                    <span class="w2p-save-status" id="w2p-save-status"></span>
                    
                    <button type="button" class="button button-secondary" id="w2p-test-smtp">
                        <?php esc_html_e('Test Connection', 'wp-genius'); ?>
                    </button>
                    <span class="w2p-test-result" id="smtp-test-result"></span>
                </div>
            </form>
        </div>

        <!-- Sidebar / Provider Info Section -->
        <div class="w2p-flex-4">
            <div class="w2p-section">
                <div class="w2p-section-header">
                    <h4><?php esc_html_e('Common Providers', 'wp-genius'); ?></h4>
                </div>
                <div class="w2p-section-body">
                    <div class="w2p-flex-col w2p-gap-md">
                        <div class="w2p-provider-card" style="background: var(--w2p-bg-surface-secondary); padding: var(--w2p-spacing-md); border-radius: var(--w2p-radius-lg); border: 1px solid var(--w2p-border-color-light);">
                            <strong style="display: block; margin-bottom: 5px; color: var(--w2p-color-primary);">Gmail / Google Workspace</strong>
                            <p style="margin: 0; font-size: 12px; opacity: 0.8;">
                                Host: <code>smtp.gmail.com</code><br>
                                Port: <code>465 (SSL) / 587 (TLS)</code><br>
                                Note: Use App Password if 2FA enabled.
                            </p>
                        </div>
                        <div class="w2p-provider-card" style="background: var(--w2p-bg-surface-secondary); padding: var(--w2p-spacing-md); border-radius: var(--w2p-radius-lg); border: 1px solid var(--w2p-border-color-light);">
                            <strong style="display: block; margin-bottom: 5px; color: var(--w2p-color-primary);">SendGrid</strong>
                            <p style="margin: 0; font-size: 12px; opacity: 0.8;">
                                Host: <code>smtp.sendgrid.net</code><br>
                                Port: <code>465 (SSL) / 587 (TLS)</code><br>
                                User: <code>apikey</code>
                            </p>
                        </div>
                        <div class="w2p-provider-card" style="background: var(--w2p-bg-surface-secondary); padding: var(--w2p-spacing-md); border-radius: var(--w2p-radius-lg); border: 1px solid var(--w2p-border-color-light);">
                            <strong style="display: block; margin-bottom: 5px; color: var(--w2p-color-primary);">AWS SES</strong>
                            <p style="margin: 0; font-size: 12px; opacity: 0.8;">
                                Host: <code>email-smtp.{region}.amazonaws.com</code><br>
                                Port: <code>465 / 587</code>
                            </p>
                        </div>
                        <div class="w2p-provider-card" style="background: var(--w2p-bg-surface-secondary); padding: var(--w2p-spacing-md); border-radius: var(--w2p-radius-lg); border: 1px solid var(--w2p-border-color-light);">
                            <strong style="display: block; margin-bottom: 5px; color: var(--w2p-color-primary);">Mailgun</strong>
                            <p style="margin: 0; font-size: 12px; opacity: 0.8;">
                                Host: <code>smtp.mailgun.org</code><br>
                                Port: <code>465 / 587</code>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
document.addEventListener( 'DOMContentLoaded', function() {
	const form = document.getElementById( 'w2p-smtp-form' );
	const submitBtn = document.getElementById( 'w2p-save-smtp' );
	const saveStatus = document.getElementById( 'w2p-save-status' );
	const testBtn = document.getElementById( 'w2p-test-smtp' );
	const testResult = document.getElementById( 'smtp-test-result' );
	
	if ( ! form ) return;

	// Handle form submission with AJAX
	form.addEventListener( 'submit', function( e ) {
		e.preventDefault();

		if ( saveStatus ) {
			saveStatus.textContent = '<?php esc_html_e( 'Saving...', 'wp-genius' ); ?>';
			saveStatus.className = 'w2p-save-status saving';
		}

		const formData = new FormData( form );

		fetch( form.action, {
			method: 'POST',
			body: formData,
		} )
		.then( response => response.text() )
		.then( data => {
			if ( saveStatus ) {
				saveStatus.textContent = '✓ <?php esc_html_e( 'Saved', 'wp-genius' ); ?>';
				saveStatus.className = 'w2p-save-status success';

				// 清除状态文本（3秒后）
				setTimeout( function() {
					saveStatus.textContent = '';
					saveStatus.className = 'w2p-save-status';
				}, 3000 );
			}
		} )
		.catch( error => {
			console.error( 'Save error:', error );
			if ( saveStatus ) {
				saveStatus.textContent = '✗ <?php esc_html_e( 'Save failed', 'wp-genius' ); ?>';
				saveStatus.className = 'w2p-save-status error';
			}
		} );
	} );

	// Handle test connection
	if ( testBtn ) {
		testBtn.addEventListener( 'click', function( e ) {
			e.preventDefault();

			if ( testResult ) {
				testResult.textContent = '<?php esc_html_e( 'Testing...', 'wp-genius' ); ?>';
				testResult.className = 'w2p-test-result testing';
			}

			// Build test URL
			const testUrl = new URL( window.location.href );
			testUrl.searchParams.set( 'smtp_test', '1' );

			// Get form data for test
			const formData = new FormData( form );

			fetch( testUrl.toString(), {
				method: 'POST',
				body: formData,
			} )
			.then( response => response.json() )
			.then( data => {
				if ( testResult ) {
					if ( data.success ) {
						testResult.textContent = '✓ <?php esc_html_e( 'Connection successful', 'wp-genius' ); ?>';
						testResult.className = 'w2p-test-result success';
					} else {
						const errorMsg = data.data ? String( data.data ).substring( 0, 100 ) : '<?php esc_html_e( 'Connection failed', 'wp-genius' ); ?>';
						testResult.textContent = '✗ ' + errorMsg;
						testResult.className = 'w2p-test-result error';
						testResult.title = data.data;
					}
				}
			} )
			.catch( error => {
				console.error( 'Test connection error:', error );
				if ( testResult ) {
					testResult.textContent = '✗ <?php esc_html_e( 'Test error', 'wp-genius' ); ?>';
					testResult.className = 'w2p-test-result error';
				}
			} );
		} );
	}
} );
</script>
