<?php
/**
 * Update Behavior Module Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'w2p_update_behavior_settings', array() );
$defaults = array(
    'disable_auto_update_plugin' => true,
    'disable_auto_update_theme'  => true,
    'remove_wp_update_plugins'   => true,
    'remove_wp_update_themes'    => true,
    'remove_maybe_update_core'   => true,
    'remove_maybe_update_plugins'=> true,
    'remove_maybe_update_themes' => true,
    'block_external_http'      => false,  // 危险级开关
    'hide_plugin_notices'      => false,  // 隐藏插件通知
    'block_acf_updates'        => false,  // 阻止ACF更新
);
$settings = wp_parse_args( $settings, $defaults );
?>

<div class="w2p-settings-panel w2p-update-behavior">
    <h3><?php esc_html_e( 'Update Behavior', 'wp-genius' ); ?></h3>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
        <input type="hidden" name="action" value="word2posts_save_module_settings" />
        <input type="hidden" name="module_id" value="update-behavior" />

        <div class="w2p-cleanup-options">
            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[disable_auto_update_plugin]" value="1" <?php checked( $settings['disable_auto_update_plugin'], 1 ); ?> />
                <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Disable automatic plugin updates', 'wp-genius' ); ?></strong></span>
            </label>

            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[disable_auto_update_theme]" value="1" <?php checked( $settings['disable_auto_update_theme'], 1 ); ?> />
                <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Disable automatic theme updates', 'wp-genius' ); ?></strong></span>
            </label>

            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[remove_wp_update_plugins]" value="1" <?php checked( $settings['remove_wp_update_plugins'], 1 ); ?> />
                <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove wp_update_plugins action', 'wp-genius' ); ?></strong></span>
            </label>

            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[remove_wp_update_themes]" value="1" <?php checked( $settings['remove_wp_update_themes'], 1 ); ?> />
                <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove wp_update_themes action', 'wp-genius' ); ?></strong></span>
            </label>

            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[remove_maybe_update_core]" value="1" <?php checked( $settings['remove_maybe_update_core'], 1 ); ?> />
                <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove _maybe_update_core on admin_init', 'wp-genius' ); ?></strong></span>
            </label>

            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[remove_maybe_update_plugins]" value="1" <?php checked( $settings['remove_maybe_update_plugins'], 1 ); ?> />
                <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove _maybe_update_plugins on admin_init', 'wp-genius' ); ?></strong></span>
            </label>

            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[remove_maybe_update_themes]" value="1" <?php checked( $settings['remove_maybe_update_themes'], 1 ); ?> />
                <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove _maybe_update_themes on admin_init', 'wp-genius' ); ?></strong></span>
            </label>
        </div>

        <!-- 危险级开关 -->
        <div class="w2p-danger-setting">
            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[block_external_http]" value="1" <?php checked( $settings['block_external_http'], 1 ); ?> class="w2p-danger-toggle" />
                <span class="w2p-cleanup-label">
                    <strong class="w2p-danger-title"><?php esc_html_e( 'Block all external HTTP requests (DANGER!)', 'wp-genius' ); ?></strong>
                    <span class="w2p-danger-desc"><?php esc_html_e( 'This will block all plugins and themes from checking for updates, dramatically speeding up the backend load times. Use only for local development!', 'wp-genius' ); ?></span>
                </span>
            </label>
        </div>

        <div class="w2p-cleanup-options">
            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[hide_plugin_notices]" value="1" <?php checked( $settings['hide_plugin_notices'], 1 ); ?> />
                <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Hide plugin notices', 'wp-genius' ); ?></strong></span>
            </label>

            <label class="w2p-cleanup-item">
                <input type="checkbox" name="w2p_update_behavior_settings[block_acf_updates]" value="1" <?php checked( $settings['block_acf_updates'], 1 ); ?> />
                <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Block ACF update requests', 'wp-genius' ); ?></strong></span>
            </label>
        </div>

        <div class="w2p-cleanup-actions">
            <?php submit_button( __( 'Save Update Behavior', 'wp-genius' ), 'primary' ); ?>
        </div>
    </form>
</div>
