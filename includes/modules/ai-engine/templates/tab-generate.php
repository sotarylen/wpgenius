<?php
/**
 * AI Engine - Generate Tab
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$providers = $this->provider_manager->get_providers_list();
$prompts   = $this->prompt_engine->get_prompts();
?>
<div class="w2p-ai-tab-content">
	<div class="w2p-ai-generate-section">
		<h2><?php esc_html_e( 'Generate Content', 'wp-genius' ); ?></h2>

		<div class="w2p-ai-form-row">
			<label for="ai-provider"><?php esc_html_e( 'AI Provider', 'wp-genius' ); ?></label>
			<select id="ai-provider" class="w2p-ai-select">
				<option value=""><?php esc_html_e( 'Select Provider', 'wp-genius' ); ?></option>
				<?php foreach ( $providers as $provider ) : ?>
					<option value="<?php echo esc_attr( $provider['slug'] ); ?>"
						<?php echo ! $provider['key'] ? 'disabled' : ''; ?>>
						<?php echo esc_html( $provider['name'] ); ?>
						<?php echo ! $provider['key'] ? '(' . esc_html__( 'API key not set', 'wp-genius' ) . ')' : ''; ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="w2p-ai-form-row">
			<label for="ai-model"><?php esc_html_e( 'Model', 'wp-genius' ); ?></label>
			<select id="ai-model" class="w2p-ai-select" disabled>
				<option value=""><?php esc_html_e( 'Select provider first', 'wp-genius' ); ?></option>
			</select>
		</div>

		<div class="w2p-ai-form-row">
			<label for="ai-prompt"><?php esc_html_e( 'Prompt Template', 'wp-genius' ); ?></label>
			<select id="ai-prompt" class="w2p-ai-select">
				<option value=""><?php esc_html_e( 'Select Prompt', 'wp-genius' ); ?></option>
				<?php foreach ( $prompts as $prompt ) : ?>
					<option value="<?php echo esc_attr( $prompt['id'] ); ?>">
						<?php echo esc_html( $prompt['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div id="ai-variables-container" class="w2p-ai-variables" style="display: none;">
			<h3><?php esc_html_e( 'Template Variables', 'wp-genius' ); ?></h3>
			<div id="ai-variables"></div>
		</div>

		<div class="w2p-ai-form-row">
			<label for="ai-quantity"><?php esc_html_e( 'Quantity', 'wp-genius' ); ?></label>
			<input type="number" id="ai-quantity" class="small-text" value="1" min="1" max="10">
		</div>

		<div class="w2p-ai-form-actions">
			<button type="button" id="ai-generate-btn" class="button button-primary">
				<span class="dashicons dashicons-update" style="display: none;"></span>
				<?php esc_html_e( 'Generate Content', 'wp-genius' ); ?>
			</button>
			<button type="button" id="ai-save-as-draft-btn" class="button" style="display: none;">
				<?php esc_html_e( 'Save as Draft', 'wp-genius' ); ?>
			</button>
		</div>
	</div>

	<div id="ai-result-section" class="w2p-ai-result-section" style="display: none;">
		<h2><?php esc_html_e( 'Generated Content', 'wp-genius' ); ?></h2>

		<div class="w2p-ai-result-header">
			<div class="w2p-ai-usage-info">
				<span class="w2p-ai-usage-label"><?php esc_html_e( 'Usage:', 'wp-genius' ); ?></span>
				<span id="ai-usage-tokens">0</span> <?php esc_html_e( 'tokens', 'wp-genius' ); ?>
			</div>
			<div class="w2p-ai-result-actions">
				<button type="button" id="ai-copy-btn" class="button">
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'Copy', 'wp-genius' ); ?>
				</button>
				<button type="button" id="ai-create-post-btn" class="button button-primary">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Create Post', 'wp-genius' ); ?>
				</button>
			</div>
		</div>

		<div id="ai-result-content" class="w2p-ai-result-content"></div>
	</div>
</div>
