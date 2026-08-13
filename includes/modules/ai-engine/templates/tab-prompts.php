<?php
/**
 * AI Engine - Prompts Tab
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prompts = $this->prompt_engine->get_prompts();
$default_prompts = $this->prompt_engine->get_default_prompts();
?>
<div class="w2p-ai-tab-content">
	<div class="w2p-ai-prompts-section">
		<h2><?php esc_html_e( 'Prompt Templates', 'wp-genius' ); ?></h2>

		<div class="w2p-ai-prompts-header">
			<button type="button" id="ai-add-prompt-btn" class="button button-primary">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add New Prompt', 'wp-genius' ); ?>
			</button>
		</div>

		<table class="wp-list-table widefat fixed striped w2p-ai-prompts-table">
			<thead>
				<tr>
					<th class="column-name"><?php esc_html_e( 'Name', 'wp-genius' ); ?></th>
					<th class="column-type"><?php esc_html_e( 'Type', 'wp-genius' ); ?></th>
					<th class="column-variables"><?php esc_html_e( 'Variables', 'wp-genius' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'wp-genius' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $prompts ) ) : ?>
					<?php foreach ( $prompts as $prompt ) : ?>
						<tr data-id="<?php echo esc_attr( $prompt['id'] ); ?>">
							<td class="column-name">
								<strong><?php echo esc_html( $prompt['name'] ); ?></strong>
							</td>
							<td class="column-type">
								<?php echo esc_html( ucfirst( $prompt['type'] ) ); ?>
							</td>
							<td class="column-variables">
								<?php
								$variables = $prompt['variables'] ?? [];
								if ( ! empty( $variables ) ) {
									echo esc_html( count( $variables ) . ' ' . __( 'variables', 'wp-genius' ) );
								} else {
									echo '<span class="w2p-ai-muted">' . esc_html__( 'None', 'wp-genius' ) . '</span>';
								}
								?>
							</td>
							<td class="column-actions">
								<a href="#" class="ai-edit-prompt" data-id="<?php echo esc_attr( $prompt['id'] ); ?>">
									<?php esc_html_e( 'Edit', 'wp-genius' ); ?>
								</a>
								<a href="#" class="ai-delete-prompt" data-id="<?php echo esc_attr( $prompt['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'wp-genius' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'No prompts found. Add a new prompt or use a default template.', 'wp-genius' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( empty( $prompts ) ) : ?>
			<div class="w2p-ai-default-prompts">
				<h3><?php esc_html_e( 'Default Templates', 'wp-genius' ); ?></h3>
				<p><?php esc_html_e( 'Click "Use Template" to add a default prompt to your collection.', 'wp-genius' ); ?></p>
				<div class="w2p-ai-default-list">
					<?php foreach ( $default_prompts as $default ) : ?>
						<div class="w2p-ai-default-item">
							<strong><?php echo esc_html( $default['name'] ); ?></strong>
							<button type="button" class="button ai-use-template"
								data-name="<?php echo esc_attr( $default['name'] ); ?>"
								data-template="<?php echo esc_attr( $default['template'] ); ?>"
								data-variables="<?php echo esc_attr( wp_json_encode( $default['variables'] ) ); ?>">
								<?php esc_html_e( 'Use Template', 'wp-genius' ); ?>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Prompt Editor Modal -->
	<div id="ai-prompt-modal" class="w2p-ai-modal" style="display: none;">
		<div class="w2p-ai-modal-content">
			<div class="w2p-ai-modal-header">
				<h2 id="ai-prompt-modal-title"><?php esc_html_e( 'Add New Prompt', 'wp-genius' ); ?></h2>
				<button type="button" class="w2p-ai-modal-close">&times;</button>
			</div>
			<div class="w2p-ai-modal-body">
				<input type="hidden" id="ai-prompt-id" value="">

				<div class="w2p-ai-form-row">
					<label for="ai-prompt-name"><?php esc_html_e( 'Name', 'wp-genius' ); ?></label>
					<input type="text" id="ai-prompt-name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Blog Post Writer', 'wp-genius' ); ?>">
				</div>

				<div class="w2p-ai-form-row">
					<label for="ai-prompt-type"><?php esc_html_e( 'Type', 'wp-genius' ); ?></label>
					<select id="ai-prompt-type" class="w2p-ai-select">
						<option value="custom"><?php esc_html_e( 'Custom', 'wp-genius' ); ?></option>
						<option value="blog_post"><?php esc_html_e( 'Blog Post', 'wp-genius' ); ?></option>
						<option value="product"><?php esc_html_e( 'Product', 'wp-genius' ); ?></option>
						<option value="social"><?php esc_html_e( 'Social Media', 'wp-genius' ); ?></option>
						<option value="seo"><?php esc_html_e( 'SEO Article', 'wp-genius' ); ?></option>
					</select>
				</div>

				<div class="w2p-ai-form-row">
					<label for="ai-prompt-template"><?php esc_html_e( 'Template', 'wp-genius' ); ?></label>
					<textarea id="ai-prompt-template" class="large-text" rows="10"
						placeholder="<?php esc_attr_e( 'Write your prompt template here. Use {{variable_name}} for dynamic values.', 'wp-genius' ); ?>"></textarea>
					<p class="description">
						<?php esc_html_e( 'Use {{variable_name}} syntax for dynamic values. These will be shown as input fields when generating content.', 'wp-genius' ); ?>
					</p>
				</div>

				<div class="w2p-ai-form-row">
					<label for="ai-prompt-temperature"><?php esc_html_e( 'Temperature', 'wp-genius' ); ?></label>
					<input type="number" id="ai-prompt-temperature" class="small-text" value="0.7" min="0" max="2" step="0.1">
					<p class="description">
						<?php esc_html_e( 'Higher values make output more random, lower values make it more focused.', 'wp-genius' ); ?>
					</p>
				</div>

				<div class="w2p-ai-form-row">
					<label for="ai-prompt-max-tokens"><?php esc_html_e( 'Max Tokens', 'wp-genius' ); ?></label>
					<input type="number" id="ai-prompt-max-tokens" class="small-text" value="2000" min="100" max="100000">
				</div>

				<div id="ai-prompt-variables-section" class="w2p-ai-form-row" style="display: none;">
					<label><?php esc_html_e( 'Detected Variables', 'wp-genius' ); ?></label>
					<div id="ai-prompt-variables-list"></div>
					<p class="description">
						<?php esc_html_e( 'These variables were detected in your template. They will be available as input fields.', 'wp-genius' ); ?>
					</p>
				</div>
			</div>
			<div class="w2p-ai-modal-footer">
				<button type="button" class="button w2p-ai-modal-close"><?php esc_html_e( 'Cancel', 'wp-genius' ); ?></button>
				<button type="button" id="ai-save-prompt-btn" class="button button-primary">
					<?php esc_html_e( 'Save Prompt', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
