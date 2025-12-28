<?php
/**
 * Media Turbo Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'w2p_media_turbo_settings', [] );
$webp_supported = MediaTurboConverterService::is_webp_supported();
?>

<div class="w2p-settings-panel w2p-media-turbo-settings">
    <h3><?php esc_html_e( 'Media Turbo Settings', 'wp-genius' ); ?></h3>
    
    <?php if ( ! $webp_supported ) : ?>
        <div class="notice notice-error inline">
            <p><?php esc_html_e( 'WebP is not supported by your server\'s GD library. Please contact your hosting provider to enable it.', 'wp-genius' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="w2p-bulk-redesign-container">
        <!-- Left Column: Configuration -->
        <div class="w2p-bulk-config-col">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
                <input type="hidden" name="action" value="word2posts_save_module_settings" />
                <input type="hidden" name="module_id" value="media-turbo" />

                <div class="w2p-config-card">
                    <h4><?php esc_html_e( 'Conversion Settings', 'wp-genius' ); ?></h4>
                    
                    <div class="w2p-setting-row w2p-flex-row">
                        <div class="w2p-setting-label">
                            <strong><?php esc_html_e( 'Auto Conversion', 'wp-genius' ); ?></strong>
                        </div>
                        <div class="w2p-setting-control">
                            <label class="switch">
                                <input type="checkbox" name="w2p_media_turbo_settings[webp_enabled]" value="1" <?php checked( ! empty( $settings['webp_enabled'] ) ); ?> <?php disabled( ! $webp_supported ); ?> />
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="w2p-setting-row">
                        <label for="webp_quality"><strong><?php esc_html_e( 'WebP Quality (0-100)', 'wp-genius' ); ?></strong></label>
                        <input type="number" id="webp_quality" name="w2p_media_turbo_settings[webp_quality]" value="<?php echo esc_attr( $settings['webp_quality'] ); ?>" min="1" max="100" class="small-text" />
                    </div>

                    <div class="w2p-setting-row w2p-flex-row">
                        <div class="w2p-setting-label">
                            <strong><?php esc_html_e( 'Keep Original', 'wp-genius' ); ?></strong>
                        </div>
                        <div class="w2p-setting-control">
                            <label class="switch">
                                <input type="checkbox" name="w2p_media_turbo_settings[keep_original]" value="1" <?php checked( ! empty( $settings['keep_original'] ) ); ?> />
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="w2p-setting-row">
                        <label for="w2p-scan-limit"><strong><?php esc_html_e( 'Scan Limit', 'wp-genius' ); ?></strong></label>
                        <p class="description"><?php esc_html_e( 'Number of items to fetch from media library.', 'wp-genius' ); ?></p>
                        <input type="number" id="w2p-scan-limit" name="w2p_media_turbo_settings[scan_limit]" value="<?php echo esc_attr( $settings['scan_limit'] ?? 100 ); ?>" min="1" max="1000" class="small-text" />
                    </div>

                    <div class="w2p-setting-row">
                        <label for="w2p-batch-size"><strong><?php esc_html_e( 'Batch Size', 'wp-genius' ); ?></strong></label>
                        <p class="description"><?php esc_html_e( 'Items to process per AJAX request.', 'wp-genius' ); ?></p>
                        <input type="number" id="w2p-batch-size" name="w2p_media_turbo_settings[batch_size]" value="<?php echo esc_attr( $settings['batch_size'] ?? 10 ); ?>" min="1" max="50" class="small-text" />
                    </div>

                    <div class="w2p-cleanup-actions">
                        <?php submit_button( __( 'Save Settings', 'wp-genius' ), 'secondary' ); ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Right Column: Execution Center -->
        <div class="w2p-bulk-execution-col">
            <div class="w2p-execution-card">
                <div class="w2p-execution-header">
                    <h4><?php esc_html_e( 'Bulk Optimization Center', 'wp-genius' ); ?></h4>
                    <div class="w2p-bulk-actions">
                        <button type="button" id="w2p-scan-media" class="button button-primary">
                            <?php esc_html_e( 'Scan Media Library', 'wp-genius' ); ?>
                        </button>
                        <button type="button" id="w2p-start-bulk" class="button button-primary" style="display:none; background:#10a754; border-color:#10a754;">
                            <?php esc_html_e( 'Start Bulk Conversion', 'wp-genius' ); ?>
                        </button>
                        <button type="button" id="w2p-stop-bulk" class="button button-secondary" style="display:none; color:#d63638; border-color:#d63638;">
                            <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
                        </button>
                    </div>
                </div>

                <div id="w2p-bulk-progress-wrapper" style="display:none; margin-top:20px;">
                    <div class="w2p-progress-container">
                        <div id="w2p-bulk-progress-bar" class="w2p-progress-fill" style="width:0%;"></div>
                    </div>
                    <div id="w2p-bulk-status-detailed" class="w2p-status-stats"></div>
                </div>

                <div id="w2p-scan-results-wrapper" class="w2p-scan-results" style="display:none;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php esc_html_e( 'Img', 'wp-genius' ); ?></th>
                                <th><?php esc_html_e( 'File & Association', 'wp-genius' ); ?></th>
                                <th style="width: 120px;"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="w2p-scan-items">
                            <!-- Items will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.w2p-bulk-redesign-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 24px;
    margin-top: 20px;
    align-items: start;
}
.w2p-config-card, .w2p-execution-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}
.w2p-execution-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}
.w2p-execution-header h4 { margin: 0; }
.w2p-setting-row {
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.w2p-setting-row:last-child { border-bottom: none; }
.w2p-flex-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.w2p-status-stats {
    margin-top: 10px;
    font-size: 13px;
    color: #646970;
}
.w2p-scan-results {
    margin-top: 20px;
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #eee;
}
.w2p-scan-results table { margin: 0; border: none; }
.w2p-item-thumb {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 3px;
    background: #f0f0f0;
}
.w2p-item-info strong { display: block; font-size: 13px; }
.w2p-item-info small { color: #888; }
.w2p-status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}
.w2p-status-pending { background: #f0f0f1; color: #50575e; }
.w2p-status-processing { background: #d94f1a; color: #fff; }
.w2p-status-success { background: #edfaef; color: #10a754; }
.w2p-status-error { background: #fcf0f1; color: #d63638; }
.w2p-status-skipped { background: #f6f7f7; color: #646970; }

.w2p-progress-container {
    height: 10px;
    background: #f0f0f1;
    border-radius: 5px;
    overflow: hidden;
}
.w2p-progress-fill {
    height: 100%;
    background: #10a754;
    transition: width 0.3s ease;
}
</style>
