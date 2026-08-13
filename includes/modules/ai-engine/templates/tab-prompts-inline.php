<?php
/**
 * AI Engine - Prompts Tab (Inline for CSF)
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables from options.php: $ai_prompts.
$ai_prompts = $ai_prompts ?? array();

$nonce = wp_create_nonce( 'w2p_ai_engine_nonce' );
?>
<div class="w2p-ai-engine-wrap">
	<h3><?php esc_html_e( 'Prompt Templates', 'wp-genius' ); ?></h3>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Type', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Variables', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-genius' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $ai_prompts ) ) : ?>
				<?php foreach ( $ai_prompts as $prompt ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $prompt['name'] ); ?></strong></td>
						<td><?php echo esc_html( ucfirst( $prompt['type'] ?? 'custom' ) ); ?></td>
						<td>
							<?php
							$vars = ! empty( $prompt['variables'] ) ? json_decode( $prompt['variables'], true ) : array();
							echo ! empty( $vars ) ? count( $vars ) . ' variables' : 'None';
							?>
						</td>
						<td>
							<a href="#" class="ai-edit-prompt" data-id="<?php echo esc_attr( $prompt['id'] ); ?>"><?php esc_html_e( 'Edit', 'wp-genius' ); ?></a> |
							<a href="#" class="ai-delete-prompt" data-id="<?php echo esc_attr( $prompt['id'] ); ?>" style="color: #d63638;"><?php esc_html_e( 'Delete', 'wp-genius' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No prompts found. Click "Add New Prompt" to create one.', 'wp-genius' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<p>
		<button type="button" id="ai-add-prompt-btn" class="button button-primary"><?php esc_html_e( 'Add New Prompt', 'wp-genius' ); ?></button>
	</p>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var nonce = '<?php echo $nonce; ?>';

	$('#ai-add-prompt-btn').on('click', function(e) {
		e.preventDefault();
		showPromptModal({id: '', name: '', template: '', type: 'custom', temperature: 0.7, max_tokens: 2000});
	});

	$('.ai-edit-prompt').on('click', function(e) {
		e.preventDefault();
		var id = $(this).data('id');
		$.post(ajaxurl, {
			action: 'w2p_ai_get_prompt',
			nonce: nonce,
			prompt_id: id
		}, function(res) {
			if (res.success) {
				showPromptModal(res.data.prompt);
			}
		});
	});

	function showPromptModal(prompt) {
		var html = '<div id="ai-prompt-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;">';
		html += '<div style="background:#fff;padding:20px;border-radius:4px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto;">';
		html += '<h3>' + (prompt.id ? 'Edit Prompt' : 'Add New Prompt') + '</h3>';
		html += '<input type="hidden" id="modal-prompt-id" value="' + (prompt.id || '') + '">';
		html += '<p><label>Name</label><br><input type="text" id="modal-prompt-name" class="regular-text" value="' + (prompt.name || '') + '"></p>';
		html += '<p><label>Type</label><br><select id="modal-prompt-type" class="regular-text"><option value="custom">Custom</option><option value="blog_post">Blog Post</option><option value="product">Product</option><option value="social">Social Media</option><option value="seo">SEO Article</option></select></p>';
		html += '<p><label>Template</label><br><textarea id="modal-prompt-template" class="large-text" rows="8">' + (prompt.template || '') + '</textarea><br><small>Use {{variable_name}} for dynamic values</small></p>';
		html += '<p><label>Temperature (0-2)</label><br><input type="number" id="modal-prompt-temperature" class="small-text" value="' + (prompt.temperature || 0.7) + '" min="0" max="2" step="0.1"></p>';
		html += '<p><label>Max Tokens</label><br><input type="number" id="modal-prompt-max-tokens" class="small-text" value="' + (prompt.max_tokens || 2000) + '" min="100" max="100000"></p>';
		html += '<p><button type="button" class="button button-primary" id="modal-save-prompt">Save</button> <button type="button" class="button" id="modal-close-prompt">Cancel</button></p>';
		html += '</div></div>';
		$('body').append(html);

		if (prompt.type) {
			$('#modal-prompt-type').val(prompt.type);
		}

		$('#modal-close-prompt').on('click', function() { $('#ai-prompt-modal').remove(); });
		$('#modal-save-prompt').on('click', function() {
			$.post(ajaxurl, {
				action: 'w2p_ai_save_prompt',
				nonce: nonce,
				id: $('#modal-prompt-id').val(),
				name: $('#modal-prompt-name').val(),
				template: $('#modal-prompt-template').val(),
				type: $('#modal-prompt-type').val(),
				temperature: $('#modal-prompt-temperature').val(),
				max_tokens: $('#modal-prompt-max-tokens').val()
			}, function(res) {
				if (res.success) location.reload();
				else alert(res.data.message || 'Error');
			});
		});
	}

	$('.ai-delete-prompt').on('click', function(e) {
		e.preventDefault();
		if (!confirm('Are you sure you want to delete this prompt?')) return;
		$.post(ajaxurl, {
			action: 'w2p_ai_delete_prompt',
			nonce: nonce,
			prompt_id: $(this).data('id')
		}, function(res) {
			if (res.success) location.reload();
			else alert(res.data.message || 'Error');
		});
	});
});
</script>
