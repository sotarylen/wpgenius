<?php
/**
 * AI Assistant Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'w2p_ai_assistant_settings', [] );
?>

<div class="w2p-settings-panel w2p-ai-assistant-settings">
<div class="w2p-section">
    <div class="w2p-section-header">
        <h4><?php esc_html_e( 'AI Content Assistant Settings', 'wp-genius' ); ?></h4>
    </div>

    <div class="w2p-section-body">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_ai_assistant_nonce' ); ?>
            <input type="hidden" name="action" value="word2posts_save_module_settings" />
            <input type="hidden" name="module_id" value="ai-assistant" />

            <div class="w2p-form-row">
                <div class="w2p-form-label">
                    <label for="ai_api_key"><?php esc_html_e( 'OpenAI API Key', 'wp-genius' ); ?></label>
                    <p class="description"><?php esc_html_e( 'Enter your OpenAI API key here.', 'wp-genius' ); ?></p>
                </div>
                <div class="w2p-form-control">
                    <input type="password" id="ai_api_key" name="w2p_ai_assistant_settings[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="w2p-input-large" autocomplete="off" />
                </div>
            </div>

            <div class="w2p-form-row">
                <div class="w2p-form-label">
                    <label for="ai_api_base"><?php esc_html_e( 'API Base URL', 'wp-genius' ); ?></label>
                    <p class="description"><?php esc_html_e( 'Default: https://api.openai.com/v1/chat/completions', 'wp-genius' ); ?></p>
                </div>
                <div class="w2p-form-control">
                    <input type="text" id="ai_api_base" name="w2p_ai_assistant_settings[api_base]" value="<?php echo esc_attr( $settings['api_base'] ); ?>" class="w2p-input-large" />
                </div>
            </div>

            <div class="w2p-form-row">
                <div class="w2p-form-label">
                    <label for="ai_model"><?php esc_html_e( 'Model', 'wp-genius' ); ?></label>
                    <p class="description"><?php esc_html_e( 'Select the AI model to use for content generation.', 'wp-genius' ); ?></p>
                </div>
                <div class="w2p-form-control">
                    <select id="ai_model" name="w2p_ai_assistant_settings[model]" class="w2p-input-medium">
                        <option value="gpt-3.5-turbo" <?php selected( $settings['model'], 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo</option>
                        <option value="gpt-4" <?php selected( $settings['model'], 'gpt-4' ); ?>>GPT-4</option>
                        <option value="gpt-4o" <?php selected( $settings['model'], 'gpt-4o' ); ?>>GPT-4o</option>
                    </select>
                </div>
            </div>

            <div class="w2p-form-row">
                <div class="w2p-form-label">
                    <label><?php esc_html_e( 'Auto Excerpt', 'wp-genius' ); ?></label>
                    <p class="description"><?php esc_html_e( 'Automatically generate AI excerpt when saving a post if the excerpt is empty.', 'wp-genius' ); ?></p>
                </div>
                <div class="w2p-form-control">
                    <label class="w2p-switch">
                        <input type="checkbox" name="w2p_ai_assistant_settings[auto_excerpt]" value="1" <?php checked( ! empty( $settings['auto_excerpt'] ) ); ?> />
                        <span class="w2p-slider"></span>
                    </label>
                </div>
            </div>

            <div class="w2p-form-row border-none">
                <div class="w2p-form-label">
                    <label><?php esc_html_e( 'Auto Suggest Tags', 'wp-genius' ); ?></label>
                    <p class="description"><?php esc_html_e( 'Automatically suggest AI tags when saving a post if no tags are set.', 'wp-genius' ); ?></p>
                </div>
                <div class="w2p-form-control">
                    <label class="w2p-switch">
                        <input type="checkbox" name="w2p_ai_assistant_settings[auto_tags]" value="1" <?php checked( ! empty( $settings['auto_tags'] ) ); ?> />
                        <span class="w2p-slider"></span>
                    </label>
                </div>
            </div>

            <div class="w2p-settings-actions">
                <button type="submit" name="submit" id="w2p-ai-assistant-submit" class="w2p-btn w2p-btn-primary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save AI Settings', 'wp-genius' ); ?>
                </button>
            </div>
        </form>
        <script>
            jQuery(document).ready(function($) {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('settings-updated') === 'true') {
                    const $btn = $('#w2p-ai-assistant-submit');
                    if (window.WPGenius && WPGenius.UI) {
                        WPGenius.UI.showFeedback($btn, '<?php esc_js( __( 'Settings Saved', 'wp-genius' ) ); ?>', 'success');
                    }
                }
            });
        </script>
    </div>
</div>
</div>
