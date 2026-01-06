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
                <input type="hidden" name="w2p_smtp_test_nonce" value="<?php echo wp_create_nonce('w2p_smtp_test_nonce'); ?>" id="w2p-smtp-test-nonce" />

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
                    <button type="submit" class="w2p-btn w2p-btn-primary" id="w2p-save-smtp">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save SMTP Settings', 'wp-genius'); ?>
                    </button>
                    <!-- Status span kept for fallback or specific messages if needed, but primary feedback will be on button -->
                    <span class="w2p-save-status" id="w2p-save-status"></span>
                    
                    <button type="button" class="w2p-btn w2p-btn-secondary" id="w2p-test-smtp">
                         <span class="dashicons dashicons-admin-plugins"></span>
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
                        <div class="w2p-provider-card">
                            <strong>Gmail / Google Workspace</strong>
                            <p>
                                Host: <code>smtp.gmail.com</code><br>
                                Port: <code>465 (SSL) / 587 (TLS)</code><br>
                                Note: Use App Password if 2FA enabled.
                            </p>
                        </div>
                        <div class="w2p-provider-card">
                            <strong>SendGrid</strong>
                            <p>
                                Host: <code>smtp.sendgrid.net</code><br>
                                Port: <code>465 (SSL) / 587 (TLS)</code><br>
                                User: <code>apikey</code>
                            </p>
                        </div>
                        <div class="w2p-provider-card">
                            <strong>AWS SES</strong>
                            <p>
                                Host: <code>email-smtp.{region}.amazonaws.com</code><br>
                                Port: <code>465 / 587</code>
                            </p>
                        </div>
                        <div class="w2p-provider-card">
                            <strong>Mailgun</strong>
                            <p>
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
jQuery(document).ready(function($) {
    const $form = $('#w2p-smtp-form');
    const $submitBtn = $('#w2p-save-smtp');
    const $testBtn = $('#w2p-test-smtp');
    const $saveStatus = $('#w2p-save-status');
    const $testResult = $('#smtp-test-result');

    if (!$form.length) return;

    // Handle form submission with AJAX
    $form.on('submit', function(e) {
        e.preventDefault();

        // Clear old status just in case
        $saveStatus.empty();

        w2p.loading($submitBtn, true);

        const formData = new FormData(this);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                w2p.loading($submitBtn, false);
                w2p.toast('<?php esc_js( __( 'Settings saved successfully', 'wp-genius' ) ); ?>', 'success');
            },
            error: function(xhr, status, error) {
                console.error('Save error:', error);
                w2p.loading($submitBtn, false);
                w2p.toast('<?php esc_js( __( 'Error saving settings', 'wp-genius' ) ); ?>', 'error');
            }
        });
    });

    // Handle test connection
    $testBtn.on('click', function(e) {
        e.preventDefault();

        // Clear old status
        $testResult.empty();

        w2p.loading($testBtn, true);

        // Build test URL
        const testUrl = new URL(window.location.href);
        testUrl.searchParams.set('smtp_test', '1');

        const formData = new FormData($form[0]);
        formData.append('nonce', $('#w2p-smtp-test-nonce').val());

        $.ajax({
            url: testUrl.toString(),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(data) {
                w2p.loading($testBtn, false);

                const isSuccess = data.success || (data.data && data.data.success);
                
                if (isSuccess) {
                    w2p.toast('<?php esc_js( __( 'Connection successful', 'wp-genius' ) ); ?>', 'success');
                } else {
                    const errorMsg = data.data ? String(data.data).substring(0, 100) : '<?php esc_html_e( 'Connection failed', 'wp-genius' ); ?>';
                    w2p.toast('<?php esc_js( __( 'Connection failed: ', 'wp-genius' ) ); ?>' + errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Test connection error:', error);
                w2p.loading($testBtn, false);
                w2p.toast('<?php esc_js( __( 'Network error during test', 'wp-genius' ) ); ?>', 'error');
            }
        });
    });
});
</script>
