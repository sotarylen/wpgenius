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
    <h3><?php esc_html_e( 'AI Content Assistant Settings', 'wp-genius' ); ?></h3>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
        <input type="hidden" name="action" value="word2posts_save_module_settings" />
        <input type="hidden" name="module_id" value="ai-assistant" />

        <div class="w2p-setting-row">
            <label for="ai_api_key"><strong><?php esc_html_e( 'OpenAI API Key', 'wp-genius' ); ?></strong></label>
            <input type="password" id="ai_api_key" name="w2p_ai_assistant_settings[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text" autocomplete="off" />
            <p class="description"><?php esc_html_e( 'Enter your OpenAI API key here.', 'wp-genius' ); ?></p>
        </div>

        <div class="w2p-setting-row">
            <label for="ai_api_base"><strong><?php esc_html_e( 'API Base URL', 'wp-genius' ); ?></strong></label>
            <input type="text" id="ai_api_base" name="w2p_ai_assistant_settings[api_base]" value="<?php echo esc_attr( $settings['api_base'] ); ?>" class="regular-text" />
            <p class="description"><?php esc_html_e( 'Default: https://api.openai.com/v1/chat/completions', 'wp-genius' ); ?></p>
        </div>

        <div class="w2p-setting-row">
            <label for="ai_model"><strong><?php esc_html_e( 'Model', 'wp-genius' ); ?></strong></label>
            <select id="ai_model" name="w2p_ai_assistant_settings[model]">
                <option value="gpt-3.5-turbo" <?php selected( $settings['model'], 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo</option>
                <option value="gpt-4" <?php selected( $settings['model'], 'gpt-4' ); ?>>GPT-4</option>
                <option value="gpt-4o" <?php selected( $settings['model'], 'gpt-4o' ); ?>>GPT-4o</option>
            </select>
        </div>

        <div class="w2p-setting-row w2p-flex-row">
            <div class="w2p-setting-label">
                <strong><?php esc_html_e( 'Auto Excerpt', 'wp-genius' ); ?></strong>
                <p class="description"><?php esc_html_e( 'Automatically generate AI excerpt when saving a post if the excerpt is empty.', 'wp-genius' ); ?></p>
            </div>
            <div class="w2p-setting-control">
                <label class="switch">
                    <input type="checkbox" name="w2p_ai_assistant_settings[auto_excerpt]" value="1" <?php checked( ! empty( $settings['auto_excerpt'] ) ); ?> />
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="w2p-setting-row w2p-flex-row">
            <div class="w2p-setting-label">
                <strong><?php esc_html_e( 'Auto Suggest Tags', 'wp-genius' ); ?></strong>
                <p class="description"><?php esc_html_e( 'Automatically suggest AI tags when saving a post if no tags are set.', 'wp-genius' ); ?></p>
            </div>
            <div class="w2p-setting-control">
                <label class="switch">
                    <input type="checkbox" name="w2p_ai_assistant_settings[auto_tags]" value="1" <?php checked( ! empty( $settings['auto_tags'] ) ); ?> />
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="w2p-cleanup-actions">
            <?php submit_button( __( 'Save AI Settings', 'wp-genius' ), 'primary' ); ?>
        </div>
    </form>
</div>

<style>
.w2p-setting-row {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}
.w2p-setting-row:last-of-type {
    border-bottom: none;
}
.w2p-flex-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}
.w2p-setting-label {
    flex: 1;
}
.w2p-setting-label strong {
    display: block;
    font-size: 14px;
    margin-bottom: 5px;
}
.w2p-setting-control {
    flex: 0 0 auto;
}
</style>
