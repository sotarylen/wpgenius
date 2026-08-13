<?php
/**
 * AI Engine - Settings Tab (Inline for CSF)
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nonce = wp_create_nonce( 'w2p_ai_engine_nonce' );

// Get stored API keys (masked).
$ai_api_keys_stored = array(
	'openai'    => get_option( 'w2p_ai_openai_key', '' ),
	'anthropic' => get_option( 'w2p_ai_anthropic_key', '' ),
	'gemini'    => get_option( 'w2p_ai_gemini_key', '' ),
	'deepseek'  => get_option( 'w2p_ai_deepseek_key', '' ),
);
?>
<div class="w2p-ai-engine-wrap">
	<h3><?php esc_html_e( 'API Settings', 'wp-genius' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row"><label for="ai-key-openai"><?php esc_html_e( 'OpenAI API Key', 'wp-genius' ); ?></label></th>
			<td>
				<input type="password" id="ai-key-openai" class="regular-text" placeholder="<?php echo ! empty( $ai_api_keys_stored['openai'] ) ? esc_attr__( '••••••••••••', 'wp-genius' ) : esc_attr__( 'Enter OpenAI API key', 'wp-genius' ); ?>">
				<button type="button" class="button ai-validate-key" data-provider="openai"><?php esc_html_e( 'Validate', 'wp-genius' ); ?></button>
				<span class="ai-key-status" id="ai-key-status-openai">
					<?php if ( ! empty( $ai_api_keys_stored['openai'] ) ) : ?>
						<span style="color: #00a32a;"><?php esc_html_e( 'Key saved', 'wp-genius' ); ?></span>
					<?php endif; ?>
				</span>
				<p class="description"><?php /* translators: %s: provider API key page URL. */ printf( esc_html__( 'Get your API key from %s', 'wp-genius' ), '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ai-key-anthropic"><?php esc_html_e( 'Anthropic API Key', 'wp-genius' ); ?></label></th>
			<td>
				<input type="password" id="ai-key-anthropic" class="regular-text" placeholder="<?php echo ! empty( $ai_api_keys_stored['anthropic'] ) ? esc_attr__( '••••••••••••', 'wp-genius' ) : esc_attr__( 'Enter Anthropic API key', 'wp-genius' ); ?>">
				<button type="button" class="button ai-validate-key" data-provider="anthropic"><?php esc_html_e( 'Validate', 'wp-genius' ); ?></button>
				<span class="ai-key-status" id="ai-key-status-anthropic">
					<?php if ( ! empty( $ai_api_keys_stored['anthropic'] ) ) : ?>
						<span style="color: #00a32a;"><?php esc_html_e( 'Key saved', 'wp-genius' ); ?></span>
					<?php endif; ?>
				</span>
				<p class="description"><?php /* translators: %s: provider API key page URL. */ printf( esc_html__( 'Get your API key from %s', 'wp-genius' ), '<a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic</a>' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ai-key-gemini"><?php esc_html_e( 'Google Gemini API Key', 'wp-genius' ); ?></label></th>
			<td>
				<input type="password" id="ai-key-gemini" class="regular-text" placeholder="<?php echo ! empty( $ai_api_keys_stored['gemini'] ) ? esc_attr__( '••••••••••••', 'wp-genius' ) : esc_attr__( 'Enter Gemini API key', 'wp-genius' ); ?>">
				<button type="button" class="button ai-validate-key" data-provider="gemini"><?php esc_html_e( 'Validate', 'wp-genius' ); ?></button>
				<span class="ai-key-status" id="ai-key-status-gemini">
					<?php if ( ! empty( $ai_api_keys_stored['gemini'] ) ) : ?>
						<span style="color: #00a32a;"><?php esc_html_e( 'Key saved', 'wp-genius' ); ?></span>
					<?php endif; ?>
				</span>
				<p class="description"><?php /* translators: %s: provider API key page URL. */ printf( esc_html__( 'Get your API key from %s', 'wp-genius' ), '<a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ai-key-deepseek"><?php esc_html_e( 'DeepSeek API Key', 'wp-genius' ); ?></label></th>
			<td>
				<input type="password" id="ai-key-deepseek" class="regular-text" placeholder="<?php echo ! empty( $ai_api_keys_stored['deepseek'] ) ? esc_attr__( '••••••••••••', 'wp-genius' ) : esc_attr__( 'Enter DeepSeek API key', 'wp-genius' ); ?>">
				<button type="button" class="button ai-validate-key" data-provider="deepseek"><?php esc_html_e( 'Validate', 'wp-genius' ); ?></button>
				<span class="ai-key-status" id="ai-key-status-deepseek">
					<?php if ( ! empty( $ai_api_keys_stored['deepseek'] ) ) : ?>
						<span style="color: #00a32a;"><?php esc_html_e( 'Key saved', 'wp-genius' ); ?></span>
					<?php endif; ?>
				</span>
				<p class="description"><?php /* translators: %s: provider API key page URL. */ printf( esc_html__( 'Get your API key from %s', 'wp-genius' ), '<a href="https://platform.deepseek.com/api_keys" target="_blank">DeepSeek</a>' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="button" id="ai-save-settings-btn" class="button button-primary"><?php esc_html_e( 'Save API Keys', 'wp-genius' ); ?></button>
	</p>

	<h3><?php esc_html_e( 'Usage Statistics', 'wp-genius' ); ?></h3>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Provider', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Total Requests', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Prompt Tokens', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Completion Tokens', 'wp-genius' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$usage_data     = array(
				'openai'    => get_option( 'w2p_ai_openai_usage', array() ),
				'anthropic' => get_option( 'w2p_ai_anthropic_usage', array() ),
				'gemini'    => get_option( 'w2p_ai_gemini_usage', array() ),
				'deepseek'  => get_option( 'w2p_ai_deepseek_usage', array() ),
			);
			$provider_names = array(
				'openai'    => 'OpenAI',
				'anthropic' => 'Anthropic',
				'gemini'    => 'Google Gemini',
				'deepseek'  => 'DeepSeek',
			);
			foreach ( $provider_names as $key => $name ) :
				$usage = $usage_data[ $key ] ?? array();
				?>
				<tr>
					<td><?php echo esc_html( $name ); ?></td>
					<td><?php echo esc_html( $usage['total_requests'] ?? 0 ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $usage['total_prompt_tokens'] ?? $usage['total_input_tokens'] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $usage['total_completion_tokens'] ?? $usage['total_output_tokens'] ?? 0 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var nonce = '<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部值/自转义输出嵌入 JS/模板，非用户输入。 ?>';

	$('.ai-validate-key').on('click', function() {
		var $btn = $(this);
		var provider = $btn.data('provider');
		var apiKey = $('#ai-key-' + provider).val();
		if (!apiKey) { alert('Please enter an API key.'); return; }
		$btn.prop('disabled', true).text('Validating...');
		$.post(ajaxurl, {
			action: 'w2p_ai_validate_key',
			nonce: nonce,
			provider: provider,
			api_key: apiKey
		}, function(res) {
			$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Validate', 'wp-genius' ) ); ?>');
			if (res.success) {
				$('#ai-key-status-' + provider).html('<span style="color:#00a32a;"><?php echo esc_js( __( 'Valid', 'wp-genius' ) ); ?></span>');
			} else {
				$('#ai-key-status-' + provider).html('<span style="color:#d63638;"><?php echo esc_js( __( 'Invalid', 'wp-genius' ) ); ?></span>');
			}
		});
	});

	$('#ai-save-settings-btn').on('click', function() {
		var keys = {};
		$('.ai-validate-key').each(function() {
			var provider = $(this).data('provider');
			var val = $('#ai-key-' + provider).val();
			if (val) keys[provider] = val;
		});
		$.post(ajaxurl, {
			action: 'w2p_ai_save_settings',
			nonce: nonce,
			api_keys: keys
		}, function(res) {
			if (res.success) {
				alert('<?php echo esc_js( __( 'Settings saved!', 'wp-genius' ) ); ?>');
			} else {
				alert(res.data.message || '<?php echo esc_js( __( 'Error occurred.', 'wp-genius' ) ); ?>');
			}
		});
	});
});
</script>
