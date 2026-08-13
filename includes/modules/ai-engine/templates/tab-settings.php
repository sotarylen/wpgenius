<?php
/**
 * AI Engine - Settings Tab
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$providers = $this->provider_manager->get_providers_list();
$usage     = $this->provider_manager->get_all_usage();
?>
<div class="w2p-ai-tab-content">
	<div class="w2p-ai-settings-section">
		<h2><?php esc_html_e( 'API Settings', 'wp-genius' ); ?></h2>

		<form id="ai-settings-form" method="post">
			<?php wp_nonce_field( 'w2p_ai_engine_nonce', 'nonce' ); ?>

			<table class="form-table">
				<?php foreach ( $providers as $provider ) : ?>
					<tr>
						<th scope="row">
							<label for="ai-key-<?php echo esc_attr( $provider['slug'] ); ?>">
								<?php echo esc_html( $provider['name'] ); ?> <?php esc_html_e( 'API Key', 'wp-genius' ); ?>
							</label>
						</th>
						<td>
							<div class="w2p-ai-api-key-row">
								<input type="password"
									id="ai-key-<?php echo esc_attr( $provider['slug'] ); ?>"
									name="api_keys[<?php echo esc_attr( $provider['slug'] ); ?>]"
									class="regular-text"
									value=""
									placeholder="<?php echo $provider['key'] ? esc_attr__( '••••••••••••', 'wp-genius' ) : esc_attr__( 'Enter API key', 'wp-genius' ); ?>">
								<button type="button"
									class="button ai-validate-key"
									data-provider="<?php echo esc_attr( $provider['slug'] ); ?>">
									<?php esc_html_e( 'Validate', 'wp-genius' ); ?>
								</button>
								<span class="w2p-ai-key-status" id="ai-key-status-<?php echo esc_attr( $provider['slug'] ); ?>">
									<?php if ( $provider['key'] ) : ?>
										<span class="w2p-ai-status-active"><?php esc_html_e( 'Key saved', 'wp-genius' ); ?></span>
									<?php endif; ?>
								</span>
							</div>
							<p class="description">
								<?php
								$docs_urls = array(
									'openai'    => 'https://platform.openai.com/api-keys',
									'anthropic' => 'https://console.anthropic.com/settings/keys',
									'gemini'    => 'https://makersuite.google.com/app/apikey',
									'deepseek'  => 'https://platform.deepseek.com/api_keys',
								);
								$url       = $docs_urls[ $provider['slug'] ] ?? '#';
								printf(
									/* translators: %s: API key documentation URL */
									esc_html__( 'Get your API key from %s', 'wp-genius' ),
									'<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $provider['name'] ) . '</a>'
								);
								?>
							</p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button( __( 'Save API Keys', 'wp-genius' ), 'primary', 'ai-save-settings' ); ?>
		</form>

		<hr>

		<h2><?php esc_html_e( 'Usage Statistics', 'wp-genius' ); ?></h2>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'wp-genius' ); ?></th>
					<th><?php esc_html_e( 'Total Requests', 'wp-genius' ); ?></th>
					<th><?php esc_html_e( 'Input Tokens', 'wp-genius' ); ?></th>
					<th><?php esc_html_e( 'Output Tokens', 'wp-genius' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $usage as $slug => $data ) : ?>
					<tr>
						<td><?php echo esc_html( $data['name'] ); ?></td>
						<td><?php echo esc_html( $data['usage']['total_requests'] ?? 0 ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $data['usage']['total_input_tokens'] ?? $data['usage']['total_prompt_tokens'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $data['usage']['total_output_tokens'] ?? $data['usage']['total_completion_tokens'] ?? 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
