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
	<h3><?php esc_html_e( 'SMTP Mail Configuration', 'wp-genius' ); ?></h3>
	
	<div class="w2p-smtp-container">
		<!-- Main form section -->
		<div class="w2p-smtp-form">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="w2p-smtp-form-wrapper" id="w2p-smtp-form">
				<?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
				<input type="hidden" name="action" value="word2posts_save_module_settings" />
				<input type="hidden" name="module_id" value="smtp-mailer" />

				<table class="form-table">
					<tbody>
						<!-- SMTP Host -->
						<tr>
							<th scope="row">
								<label for="smtp_host"><?php esc_html_e( 'SMTP Host', 'wp-genius' ); ?></label>
							</th>
							<td>
								<input 
									type="text" 
									id="smtp_host" 
									name="w2p_smtp_settings[smtp_host]" 
									value="<?php echo esc_attr( $settings['smtp_host'] ); ?>"
									placeholder="smtp.gmail.com"
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'SMTP server hostname (e.g., smtp.gmail.com, smtp.sendgrid.net)', 'wp-genius' ); ?>
								</p>
							</td>
						</tr>

						<!-- SMTP Port -->
						<tr>
							<th scope="row">
								<label for="smtp_port"><?php esc_html_e( 'SMTP Port', 'wp-genius' ); ?></label>
							</th>
							<td>
								<input 
									type="number" 
									id="smtp_port" 
									name="w2p_smtp_settings[smtp_port]" 
									value="<?php echo esc_attr( $settings['smtp_port'] ); ?>"
									placeholder="465"
									min="1"
									max="65535"
									class="small-text"
								/>
								<p class="description">
									<?php esc_html_e( 'Typical ports: 465 (SSL), 587 (TLS), 25 (unencrypted)', 'wp-genius' ); ?>
								</p>
							</td>
						</tr>

						<!-- SMTP Security -->
						<tr>
							<th scope="row">
								<label for="smtp_secure"><?php esc_html_e( 'Security', 'wp-genius' ); ?></label>
							</th>
							<td>
								<select id="smtp_secure" name="w2p_smtp_settings[smtp_secure]" class="regular-text">
									<option value="ssl" <?php selected( $settings['smtp_secure'], 'ssl' ); ?>>
										<?php esc_html_e( 'SSL (port 465)', 'wp-genius' ); ?>
									</option>
									<option value="tls" <?php selected( $settings['smtp_secure'], 'tls' ); ?>>
										<?php esc_html_e( 'TLS (port 587)', 'wp-genius' ); ?>
									</option>
									<option value="" <?php selected( $settings['smtp_secure'], '' ); ?>>
										<?php esc_html_e( 'None (port 25)', 'wp-genius' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Encryption method for SMTP connection', 'wp-genius' ); ?>
								</p>
							</td>
						</tr>

						<!-- SMTP Authentication -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Authentication Required', 'wp-genius' ); ?>
							</th>
							<td>
								<label>
									<input 
										type="checkbox" 
										name="w2p_smtp_settings[smtp_auth]" 
										value="1"
										<?php checked( $settings['smtp_auth'], true ); ?>
									/>
									<?php esc_html_e( 'Enable SMTP authentication', 'wp-genius' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Most SMTP servers require authentication', 'wp-genius' ); ?>
								</p>
							</td>
						</tr>

						<!-- SMTP Username -->
						<tr>
							<th scope="row">
								<label for="smtp_username"><?php esc_html_e( 'SMTP Username', 'wp-genius' ); ?></label>
							</th>
							<td>
								<input 
									type="email" 
									id="smtp_username" 
									name="w2p_smtp_settings[smtp_username]" 
									value="<?php echo esc_attr( $settings['smtp_username'] ); ?>"
									placeholder="your-email@gmail.com"
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'SMTP account username (usually email address)', 'wp-genius' ); ?>
								</p>
							</td>
						</tr>

						<!-- SMTP Password -->
						<tr>
							<th scope="row">
								<label for="smtp_password"><?php esc_html_e( 'SMTP Password', 'wp-genius' ); ?></label>
							</th>
							<td>
								<input 
									type="password" 
									id="smtp_password" 
									name="w2p_smtp_settings[smtp_password]" 
									value="<?php echo esc_attr( $settings['smtp_password'] ); ?>"
									placeholder="••••••••••••••••"
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'SMTP account password (for Gmail: use app-specific password)', 'wp-genius' ); ?>
								</p>
							</td>
						</tr>

						<!-- From Email -->
						<tr>
							<th scope="row">
								<label for="smtp_from_email"><?php esc_html_e( 'From Email Address', 'wp-genius' ); ?></label>
							</th>
							<td>
								<input 
									type="email" 
									id="smtp_from_email" 
									name="w2p_smtp_settings[smtp_from_email]" 
									value="<?php echo esc_attr( $settings['smtp_from_email'] ); ?>"
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'Email address that appears as sender', 'wp-genius' ); ?>
								</p>
							</td>
						</tr>

						<!-- From Name -->
						<tr>
							<th scope="row">
								<label for="smtp_from_name"><?php esc_html_e( 'From Name', 'wp-genius' ); ?></label>
							</th>
							<td>
								<input 
									type="text" 
									id="smtp_from_name" 
									name="w2p_smtp_settings[smtp_from_name]" 
									value="<?php echo esc_attr( $settings['smtp_from_name'] ); ?>"
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'Name that appears as sender', 'wp-genius' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="w2p-settings-actions">
					<button type="submit" class="button button-primary w2p-action-btn" id="w2p-save-smtp">
						<?php esc_html_e( 'Save SMTP Settings', 'wp-genius' ); ?>
					</button>
					<span class="w2p-save-status" id="w2p-save-status"></span>
					<button type="button" class="button w2p-action-btn w2p-test-btn" id="w2p-test-smtp">
						<?php esc_html_e( 'Test Connection', 'wp-genius' ); ?>
					</button>
					<span class="w2p-test-result" id="smtp-test-result"></span>
				</div>
			</form>
		</div>

		<!-- Sidebar with provider info -->
		<div class="w2p-smtp-sidebar">
			<div class="w2p-settings-info">
				<h4><?php esc_html_e( 'Common SMTP Providers', 'wp-genius' ); ?></h4>
				<div class="w2p-providers-list">
					<div class="w2p-provider-item">
						<strong>Gmail</strong>
						<p>Host: <code>smtp.gmail.com</code><br>Port: <code>465/587</code><br>Security: <code>SSL/TLS</code></p>
					</div>
					<div class="w2p-provider-item">
						<strong>SendGrid</strong>
						<p>Host: <code>smtp.sendgrid.net</code><br>Port: <code>465/587</code><br>Username: <code>apikey</code></p>
					</div>
					<div class="w2p-provider-item">
						<strong>AWS SES</strong>
						<p>Host: <code>email-smtp.{region}.amazonaws.com</code><br>Port: <code>465/587</code></p>
					</div>
					<div class="w2p-provider-item">
						<strong>Mailgun</strong>
						<p>Host: <code>smtp.mailgun.org</code><br>Port: <code>465/587</code><br>Security: <code>TLS</code></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.w2p-smtp-settings {
	display: flex;
	flex-direction: column;
}

.w2p-smtp-container {
	display: flex;
	gap: 30px;
	margin-top: 20px;
}

.w2p-smtp-form {
	flex: 0 0 65%;
	min-width: 0;
}

.w2p-smtp-form-wrapper {
	background: #ffffff;
	padding: 20px;
	border: 1px solid #ddd;
	border-radius: 3px;
}

.w2p-smtp-sidebar {
	flex: 0 0 35%;
	flex-shrink: 0;
}

.w2p-settings-info {
	padding: 15px;
	background: #f0f0f1;
	border-left: 4px solid #0073aa;
	border-radius: 3px;
}

.w2p-settings-info h4 {
	margin: 0 0 15px 0;
	font-size: 13px;
	font-weight: 600;
	color: #1d2327;
}

.w2p-providers-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.w2p-provider-item {
	font-size: 12px;
	line-height: 1.6;
}

.w2p-provider-item strong {
	display: block;
	margin-bottom: 4px;
	color: #1d2327;
}

.w2p-provider-item code {
	background: #ffffff;
	padding: 1px 4px;
	border-radius: 3px;
	font-family: monospace;
	font-size: 11px;
	color: #0073aa;
}

.w2p-provider-item p {
	margin: 0;
	color: #50575e;
}

.w2p-settings-actions {
	margin-top: 20px;
	display: flex;
	gap: 10px;
	align-items: center;
	flex-wrap: wrap;
}

.w2p-action-btn {
	min-height: 44px !important;
	padding: 8px 16px !important;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.w2p-save-status {
	font-size: 13px;
	line-height: 44px;
	margin-left: 10px;
	min-height: 44px;
	display: inline-flex;
	align-items: center;
	font-weight: 600;
}

.w2p-save-status.success {
	color: #00a32a;
}

.w2p-save-status.saving {
	color: #0073aa;
}

.w2p-test-result {
	font-size: 13px;
	line-height: 44px;
	margin-left: 10px;
	min-height: 44px;
	display: inline-flex;
	align-items: center;
}

.w2p-test-result.success {
	color: #00a32a;
	font-weight: 600;
}

.w2p-test-result.error {
	color: #d63638;
	font-weight: 600;
	cursor: help;
	border-bottom: 1px dotted #d63638;
	padding-bottom: 2px;
}

.w2p-test-result.testing {
	color: #0073aa;
}

@media (max-width: 1024px) {
	.w2p-smtp-container {
		flex-direction: column;
		gap: 20px;
	}

	.w2p-smtp-form {
		flex: 1;
	}

	.w2p-smtp-sidebar {
		flex: 1;
		width: 100%;
	}
}
</style>

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
