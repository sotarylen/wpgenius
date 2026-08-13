<?php
/**
 * AI Engine - Generate Tab (Inline for CSF)
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables from options.php: $ai_providers, $ai_prompts.
$ai_providers = $ai_providers ?? array();
$ai_prompts   = $ai_prompts ?? array();

$nonce = wp_create_nonce( 'w2p_ai_engine_nonce' );
?>
<div class="w2p-ai-engine-wrap">
	<div class="w2p-ai-generate-section">
		<h3><?php esc_html_e( 'AI Content Generator', 'wp-genius' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Select a provider, model, and prompt template to generate content.', 'wp-genius' ); ?></p>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="ai-provider"><?php esc_html_e( 'AI Provider', 'wp-genius' ); ?></label></th>
				<td>
					<select id="ai-provider" class="regular-text">
						<option value=""><?php esc_html_e( 'Select Provider', 'wp-genius' ); ?></option>
						<?php foreach ( $ai_providers as $provider ) : ?>
							<option value="<?php echo esc_attr( $provider['slug'] ); ?>">
								<?php echo esc_html( $provider['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ai-model"><?php esc_html_e( 'Model', 'wp-genius' ); ?></label></th>
				<td>
					<select id="ai-model" class="regular-text" disabled>
						<option value=""><?php esc_html_e( 'Select provider first', 'wp-genius' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ai-prompt"><?php esc_html_e( 'Prompt Template', 'wp-genius' ); ?></label></th>
				<td>
					<select id="ai-prompt" class="regular-text">
						<option value=""><?php esc_html_e( 'Select Prompt', 'wp-genius' ); ?></option>
						<?php foreach ( $ai_prompts as $prompt ) : ?>
							<option value="<?php echo esc_attr( $prompt['id'] ); ?>">
								<?php echo esc_html( $prompt['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr id="ai-variables-row" style="display: none;">
				<th scope="row"><label><?php esc_html_e( 'Template Variables', 'wp-genius' ); ?></label></th>
				<td>
					<div id="ai-variables-container"></div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ai-quantity"><?php esc_html_e( 'Quantity', 'wp-genius' ); ?></label></th>
				<td>
					<input type="number" id="ai-quantity" class="small-text" value="1" min="1" max="10">
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="button" id="ai-generate-btn" class="button button-primary">
				<?php esc_html_e( 'Generate Content', 'wp-genius' ); ?>
			</button>
			<span id="ai-generating" class="spinner" style="display: none;"></span>
		</p>
	</div>

	<div id="ai-result-section" style="display: none;">
		<h3><?php esc_html_e( 'Generated Content', 'wp-genius' ); ?></h3>
		<div class="w2p-ai-result-meta">
			<span id="ai-usage-info"></span>
		</div>
		<div id="ai-result-content" style="background: #fff; padding: 15px; border: 1px solid #ccc; border-radius: 4px; margin: 10px 0; white-space: pre-wrap;"></div>
		<p class="submit">
			<button type="button" id="ai-copy-btn" class="button"><?php esc_html_e( 'Copy to Clipboard', 'wp-genius' ); ?></button>
			<button type="button" id="ai-create-post-btn" class="button button-primary"><?php esc_html_e( 'Create as Draft', 'wp-genius' ); ?></button>
		</p>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	window._aiGeneratedContent = '';

	$('#ai-provider').on('change', function() {
		var provider = $(this).val();
		var $model = $('#ai-model');
		if (!provider) {
			$model.prop('disabled', true).html('<option value=""><?php echo esc_js( __( 'Select provider first', 'wp-genius' ) ); ?></option>');
			return;
		}
		$.post(ajaxurl, {
			action: 'w2p_ai_get_models',
			nonce: '<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部值/自转义输出嵌入 JS/模板，非用户输入。 ?>',
			provider: provider
		}, function(res) {
			if (res.success) {
				var opts = '<option value=""><?php echo esc_js( __( 'Select Model', 'wp-genius' ) ); ?></option>';
				$.each(res.data.models, function(i, m) {
					opts += '<option value="' + m.id + '">' + m.name + ' - ' + m.desc + '</option>';
				});
				$model.prop('disabled', false).html(opts);
			}
		});
	});

	$('#ai-generate-btn').on('click', function() {
		var $btn = $(this);
		var provider = $('#ai-provider').val();
		var model = $('#ai-model').val();
		var promptId = $('#ai-prompt').val();
		var quantity = parseInt($('#ai-quantity').val()) || 1;
		if (!provider || !model || !promptId) {
			alert('<?php echo esc_js( __( 'Please select provider, model, and prompt.', 'wp-genius' ) ); ?>');
			return;
		}
		$btn.prop('disabled', true);
		$('#ai-generating').show();
		$.post(ajaxurl, {
			action: 'w2p_ai_generate',
			nonce: '<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部值/自转义输出嵌入 JS/模板，非用户输入。 ?>',
			provider: provider,
			model: model,
			prompt_id: promptId,
			quantity: quantity,
			variables: {}
		}, function(res) {
			$btn.prop('disabled', false);
			$('#ai-generating').hide();
			if (res.success) {
				var content = res.data.content.map(function(c) { return c.content; }).join('\n\n---\n\n');
				$('#ai-result-content').text(content);
				$('#ai-result-section').show();
				window._aiGeneratedContent = content;
			} else {
				alert(res.data.message || '<?php echo esc_js( __( 'Error occurred.', 'wp-genius' ) ); ?>');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$('#ai-generating').hide();
			alert('<?php echo esc_js( __( 'Request failed.', 'wp-genius' ) ); ?>');
		});
	});

	$('#ai-copy-btn').on('click', function() {
		if (window._aiGeneratedContent) {
			navigator.clipboard.writeText(window._aiGeneratedContent);
			$(this).text('<?php echo esc_js( __( 'Copied!', 'wp-genius' ) ); ?>');
			var $btn = $(this);
			setTimeout(function() { $btn.text('<?php echo esc_js( __( 'Copy to Clipboard', 'wp-genius' ) ); ?>'); }, 2000);
		}
	});

	$('#ai-create-post-btn').on('click', function() {
		if (!window._aiGeneratedContent) return;
		var lines = window._aiGeneratedContent.split('\n');
		var title = '', body = window._aiGeneratedContent;
		for (var i = 0; i < lines.length; i++) {
			var m = lines[i].match(/^#+\s+(.+)$/);
			if (m) { title = m[1]; body = lines.slice(i+1).join('\n').trim(); break; }
		}
		if (!title) title = window._aiGeneratedContent.substring(0, 100);
		$.post(ajaxurl, {
			action: 'w2p_ai_create_draft',
			nonce: '<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部值/自转义输出嵌入 JS/模板，非用户输入。 ?>',
			title: title,
			content: body
		}, function(res) {
			if (res.success) {
				window.location.href = res.data.edit_url;
			} else {
				alert(res.data.message || '<?php echo esc_js( __( 'Error occurred.', 'wp-genius' ) ); ?>');
			}
		});
	});
});
</script>
