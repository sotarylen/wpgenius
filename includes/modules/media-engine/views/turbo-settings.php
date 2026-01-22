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
?>

<div class="w2p-settings-panel w2p-media-turbo-settings">
    <div class="w2p-flex w2p-gap-xl">
        <!-- Left Column: Configuration -->
        <div class="w2p-flex-1">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_media_engine_nonce' ); ?>
                <input type="hidden" name="action" value="word2posts_save_module_settings" />
                <input type="hidden" name="module_id" value="media-engine" />

                <div class="w2p-section">
                    <div class="w2p-section-header">
                        <h4><?php esc_html_e( 'Conversion Settings', 'wp-genius' ); ?></h4>
                    </div>
                    
                    <div class="w2p-section-body">
                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label><?php esc_html_e( 'Auto Conversion', 'wp-genius' ); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <label class="w2p-switch">
                                    <input type="checkbox" name="w2p_media_turbo_settings[webp_enabled]" value="1" <?php checked( ! empty( $settings['webp_enabled'] ) ); ?> />
                                    <span class="w2p-slider"></span>
                                </label>
                                <p class="description"><?php esc_html_e( 'Automatically convert newly uploaded images to WebP.', 'wp-genius' ); ?></p>
                            </div>
                        </div>

                        <div class="w2p-form-row">
                            <div class="w2p-form-label"><?php esc_html_e( 'Target Formats', 'wp-genius' ); ?></div>
                            <div class="w2p-form-control">
                                <div class="w2p-flex-col w2p-gap-xs">
                                    <label class="w2p-flex w2p-items-center w2p-gap-xs">
                                        <input type="checkbox" name="w2p_media_turbo_settings[convert_static]" value="1" <?php checked( ( $settings['convert_static'] ?? '1' ) === '1' ); ?> />
                                        <span><?php esc_html_e( 'Static Images (JPG, PNG)', 'wp-genius' ); ?></span>
                                    </label>
                                    <label class="w2p-flex w2p-items-center w2p-gap-xs">
                                        <input type="checkbox" name="w2p_media_turbo_settings[convert_animated]" value="1" <?php checked( ! empty( $settings['convert_animated'] ) ); ?> />
                                        <span><?php esc_html_e( 'Animated Images (GIF)', 'wp-genius' ); ?></span>
                                    </label>
                                </div>
                                <p class="description"><?php esc_html_e( 'Select which image types to scan and convert. Quality is automatically optimized based on file size.', 'wp-genius' ); ?></p>
                            </div>
                        </div>

                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label><?php esc_html_e( 'Keep Original', 'wp-genius' ); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <label class="w2p-switch">
                                    <input type="checkbox" name="w2p_media_turbo_settings[keep_original]" value="1" <?php checked( ! empty( $settings['keep_original'] ) ); ?> />
                                    <span class="w2p-slider"></span>
                                </label>
                                <p class="description"><?php esc_html_e( 'If disabled, original JPG/PNG/GIF files will be deleted after conversion.', 'wp-genius' ); ?></p>
                            </div>
                        </div>

                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label for="w2p-min-file-size"><?php esc_html_e( 'Min File Size', 'wp-genius' ); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <div class="w2p-range-group">
                                    <div class="w2p-range-header">
                                        <span class="w2p-range-label"><?php esc_html_e( 'Minimum Size', 'wp-genius' ); ?></span>
                                        <span class="w2p-range-value"><?php echo esc_attr( $settings['min_file_size'] ?? 1024 ); ?> KB</span>
                                    </div>
                                    <input type="range" 
                                           class="w2p-range-slider" 
                                           id="w2p-min-file-size"
                                           name="w2p_media_turbo_settings[min_file_size]" 
                                           min="0" 
                                           max="10240" 
                                           step="256"
                                           value="<?php echo esc_attr( $settings['min_file_size'] ?? 1024 ); ?>"
                                           data-suffix=" KB">
                                </div>
                                <p class="description"><?php esc_html_e( 'Only process images larger than this size.', 'wp-genius' ); ?></p>
                            </div>
                        </div>

                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label for="w2p-scan-limit"><?php esc_html_e( 'Scan Limit', 'wp-genius' ); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <div class="w2p-range-group">
                                    <div class="w2p-range-header">
                                        <span class="w2p-range-label"><?php esc_html_e( 'Items to Scan', 'wp-genius' ); ?></span>
                                        <span class="w2p-range-value"><?php echo esc_attr( $settings['scan_limit'] ?? 100 ); ?></span>
                                    </div>
                                    <input type="range" 
                                           class="w2p-range-slider" 
                                           id="w2p-scan-limit"
                                           name="w2p_media_turbo_settings[scan_limit]" 
                                           min="10" 
                                           max="1000" 
                                           step="1"
                                           value="<?php echo esc_attr( $settings['scan_limit'] ?? 100 ); ?>">
                                </div>
                                <p class="description"><?php esc_html_e( 'Number of items to fetch from media library.', 'wp-genius' ); ?></p>
                            </div>
                        </div>

                        <div class="w2p-form-row">
                            <div class="w2p-form-label"><?php esc_html_e( 'Scan Mode', 'wp-genius' ); ?></div>
                            <div class="w2p-form-control">
                                <div class="w2p-flex-col w2p-gap-sm">
                                    <label class="w2p-flex w2p-items-center w2p-gap-xs">
                                        <input type="radio" name="w2p_media_turbo_settings[scan_mode]" value="media" <?php checked( ( $settings['scan_mode'] ?? 'media' ) === 'media' ); ?> />
                                        <span><?php esc_html_e( 'Scan', 'wp-genius' ); ?></span>
                                    </label>
                                    <label class="w2p-flex w2p-items-center w2p-gap-xs">
                                        <input type="radio" name="w2p_media_turbo_settings[scan_mode]" value="posts" <?php checked( ( $settings['scan_mode'] ?? 'media' ) === 'posts' ); ?> />
                                        <span><?php esc_html_e( 'Scan by Posts (ignore file size limit)', 'wp-genius' ); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="w2p-form-row" id="w2p-posts-scan-options" style="<?php echo ( ( $settings['scan_mode'] ?? 'media' ) === 'posts' ) ? '' : 'display:none;'; ?>">
                            <div class="w2p-form-label">
                                <label for="w2p-posts-limit"><?php esc_html_e( 'Recent Posts', 'wp-genius' ); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <div class="w2p-range-group">
                                    <div class="w2p-range-header">
                                        <span class="w2p-range-label"><?php esc_html_e( 'Posts Count', 'wp-genius' ); ?></span>
                                        <span class="w2p-range-value"><?php echo esc_attr( $settings['posts_limit'] ?? 10 ); ?></span>
                                    </div>
                                    <input type="range" 
                                           class="w2p-range-slider" 
                                           id="w2p-posts-limit"
                                           name="w2p_media_turbo_settings[posts_limit]" 
                                           min="1" 
                                           max="100" 
                                           step="1"
                                           value="<?php echo esc_attr( $settings['posts_limit'] ?? 10 ); ?>">
                                </div>
                                <p class="description"><?php esc_html_e( 'How many recent posts to scan for images.', 'wp-genius' ); ?></p>
                            </div>
                        </div>

                        <div class="w2p-form-row border-none">
                            <div class="w2p-form-label">
                                <label for="w2p-batch-size"><?php esc_html_e( 'Batch Size', 'wp-genius' ); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <div class="w2p-range-group">
                                    <div class="w2p-range-header">
                                        <span class="w2p-range-label"><?php esc_html_e( 'Items per Request', 'wp-genius' ); ?></span>
                                        <span class="w2p-range-value"><?php echo esc_attr( $settings['batch_size'] ?? 10 ); ?></span>
                                    </div>
                                    <input type="range" 
                                           class="w2p-range-slider" 
                                           id="w2p-batch-size"
                                           name="w2p_media_turbo_settings[batch_size]" 
                                           min="1" 
                                           max="100" 
                                           step="1"
                                           value="<?php echo esc_attr( $settings['batch_size'] ?? 10 ); ?>">
                                </div>
                                <p class="description"><?php esc_html_e( 'Items to process per AJAX request.', 'wp-genius' ); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w2p-section" style="margin-top: var(--w2p-spacing-lg);">
                    <div class="w2p-section-header">
                        <h4><?php esc_html_e( 'Thumbnail Generation', 'wp-genius' ); ?></h4>
                    </div>
                    
                    <div class="w2p-section-body">
                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label><?php esc_html_e( 'Generate Thumbnails', 'wp-genius' ); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <label class="w2p-switch">
                                    <input type="checkbox" 
                                           name="w2p_media_turbo_settings[generate_thumbnails]" 
                                           value="1" 
                                           <?php checked( ( $settings['generate_thumbnails'] ?? '1' ) === '1' ); ?>
                                           id="w2p-generate-thumbnails" />
                                    <span class="w2p-slider"></span>
                                </label>
                                <p class="description"><?php esc_html_e( 'After converting original image to WebP, automatically generate thumbnails based on WordPress registered image sizes.', 'wp-genius' ); ?></p>
                            </div>
                        </div>

                        <div class="w2p-form-row" id="w2p-thumbnail-options" style="<?php echo ( ( $settings['generate_thumbnails'] ?? '1' ) === '1' ) ? '' : 'display:none;'; ?>">
                            <div class="w2p-form-label">
                                <label for="w2p-thumbnail-batch-delay"><?php esc_html_e( 'Batch Delay', 'wp-genius' ); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <div class="w2p-range-group">
                                    <div class="w2p-range-header">
                                        <span class="w2p-range-label"><?php esc_html_e( 'Delay (ms)', 'wp-genius' ); ?></span>
                                        <span class="w2p-range-value"><?php echo esc_attr( $settings['thumbnail_batch_delay'] ?? 0 ); ?> ms</span>
                                    </div>
                                    <input type="range" 
                                           class="w2p-range-slider" 
                                           id="w2p-thumbnail-batch-delay"
                                           name="w2p_media_turbo_settings[thumbnail_batch_delay]" 
                                           min="0" 
                                           max="5000" 
                                           step="100"
                                           value="<?php echo esc_attr( $settings['thumbnail_batch_delay'] ?? 0 ); ?>"
                                           data-suffix=" ms">
                                </div>
                                <p class="description"><?php esc_html_e( 'Add delay between thumbnail generation for each batch to reduce server load.', 'wp-genius' ); ?></p>
                            </div>
                        </div>

                        <div class="w2p-form-row border-none" id="w2p-thumbnail-info" style="<?php echo ( ( $settings['generate_thumbnails'] ?? '1' ) === '1' ) ? '' : 'display:none;'; ?>">
                            <div class="w2p-form-label"></div>
                            <div class="w2p-form-control">
                                <div class="w2p-info-box" style="background: var(--w2p-bg-info-light, rgba(59, 130, 246, 0.1)); padding: var(--w2p-spacing-md); border-left: 3px solid var(--w2p-color-info, #3b82f6); border-radius: var(--w2p-radius-sm);">
                                    <p style="margin:0; color: var(--w2p-color-info-dark, #1e40af); font-size: 0.875rem;">
                                        <i class="fa-solid fa-circle-info"></i>
                                        <?php esc_html_e( 'Thumbnails are generated from the WebP version of original image, skipping already existing sizes.', 'wp-genius' ); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="w2p-settings-actions">
                    <button type="submit" name="w2p_media_turbo_save" id="w2p-media-turbo-submit" class="w2p-btn w2p-btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <?php esc_attr_e( 'Save All Settings', 'wp-genius' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: Processing Center -->
        <div class="w2p-flex-1">
            
            <!-- Media Processing Center -->
            <div class="w2p-section">
                <div class="w2p-section-header">
                    <h4><?php esc_html_e( 'Media Processing Center', 'wp-genius' ); ?></h4>
                    <p class="description" style="margin: 5px 0 0;"><?php esc_html_e( 'Process images using external command-line tools.', 'wp-genius' ); ?></p>
                </div>
                <div class="w2p-section-body">
                    
                    <!-- Processing Actions -->
                    <div class="w2p-processing-actions" style="margin-bottom: 20px;">
                        <div class="w2p-flex w2p-flex-col w2p-gap-sm">
                            <button type="button" id="w2p-get-stats" class="w2p-btn w2p-btn-primary" style="justify-content: flex-start;">
                                <i class="fa-solid fa-chart-bar"></i>
                                <?php esc_html_e( 'Get Pending Stats', 'wp-genius' ); ?>
                            </button>
                            <?php
                            $batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 10;
                            $scan_limit = isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 1000;
                            ?>
                            <button type="button" id="w2p-start-conversion" class="w2p-btn w2p-btn-success" style="justify-content: flex-start;">
                                <i class="fa-solid fa-play"></i>
                                <?php echo sprintf( esc_html__( 'Start Conversion (%d items, batch %d)', 'wp-genius' ), $scan_limit, $batch_size ); ?>
                            </button>
                            <button type="button" id="w2p-start-parallel" class="w2p-btn w2p-btn-info" style="justify-content: flex-start;">
                                <i class="fa-solid fa-rocket"></i>
                                <?php esc_html_e( 'Start Parallel Conversion', 'wp-genius' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Processing Output -->
                    <div id="w2p-processing-output" style="display: none; background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 13px; max-height: 400px; overflow-y: auto; margin-bottom: 20px;">
                        <div id="w2p-output-content"></div>
                    </div>

                    <!-- Attachment List -->
                    <div id="w2p-attachment-list" style="display: none; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h5 style="margin: 0; font-size: 14px; font-weight: 600;">
                                <?php esc_html_e( 'Processing Queue', 'wp-genius' ); ?>
                            </h5>
                            <div id="w2p-queue-stats" style="font-size: 13px; color: #6b7280;"></div>
                        </div>
                        <div style="max-height: 500px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
                            <table class="w2p-table" style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f9fafb; position: sticky; top: 0;">
                                    <tr>
                                        <th style="padding: 12px; text-align: left; width: 60px;"><?php esc_html_e( 'Thumb', 'wp-genius' ); ?></th>
                                        <th style="padding: 12px; text-align: left;"><?php esc_html_e( 'File', 'wp-genius' ); ?></th>
                                        <th style="padding: 12px; text-align: left; width: 150px;"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
                                        <th style="padding: 12px; text-align: left; width: 200px;"><?php esc_html_e( 'Progress', 'wp-genius' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="w2p-attachment-tbody">
                                    <!-- Attachments will be inserted here -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Processing Stats -->
                    <div id="w2p-processing-stats" style="display: none; background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                        <h5 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600;">
                            <?php esc_html_e( 'Processing Statistics', 'wp-genius' ); ?>
                        </h5>
                        <div id="w2p-stats-content"></div>
                    </div>

                    <!-- Info Alert -->
                    <div class="w2p-alert w2p-alert-info" style="background: #dbeafe; color: #1e40af; padding: 12px 16px; border-radius: 6px; border-left: 4px solid #3b82f6;">
                        <i class="fa-solid fa-circle-info"></i>
                        <div style="margin-left: 8px;">
                            <strong><?php esc_html_e( 'Processing Architecture', 'wp-genius' ); ?></strong>
                            <p style="margin: 4px 0 0; font-size: 13px;">
                                <?php esc_html_e( 'All processing uses external command-line tools (vips, cwebp, gif2webp) via WP-CLI for better performance and reliability.', 'wp-genius' ); ?>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';
    
    // 从 PHP 传入的配置
    const scanLimit = <?php echo absint( $settings['scan_limit'] ?? 1000 ); ?>;
    const batchSize = <?php echo absint( $settings['batch_size'] ?? 10 ); ?>;
    
    // Execute WP-CLI command via AJAX
    function executeWPCLI(command, buttonId) {
        const $button = $('#' + buttonId);
        const $output = $('#w2p-processing-output');
        const $outputContent = $('#w2p-output-content');
        const $stats = $('#w2p-processing-stats');
        const $statsContent = $('#w2p-stats-content');
        
        // Disable button and show loading
        $button.prop('disabled', true);
        $button.find('i').removeClass().addClass('fa-solid fa-spinner fa-spin');
        
        // Show output area
        $output.show();
        $outputContent.html('<div style="color: #94a3b8;">$ ' + command + '</div><div style="margin-top: 8px; color: #fbbf24;">Processing...</div>');
        
        // Execute command
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'w2p_execute_wpcli',
                nonce: w2pMediaTurbo.nonce,
                command: command
            },
            success: function(response) {
                if (response.success) {
                    // Show output
                    const output = response.data.output || '';
                    const lines = output.split('\n');
                    let html = '<div style="color: #94a3b8;">$ ' + command + '</div>';
                    lines.forEach(line => {
                        if (line.trim()) {
                            html += '<div style="margin-top: 4px;">' + escapeHtml(line) + '</div>';
                        }
                    });
                    $outputContent.html(html);
                    
                    // Show stats if available
                    if (response.data.stats) {
                        $stats.show();
                        $statsContent.html(formatStats(response.data.stats));
                    }
                    
                    // Show success toast
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('<?php esc_html_e( 'Command executed successfully!', 'wp-genius' ); ?>', 'success');
                    }
                } else {
                    $outputContent.html(
                        '<div style="color: #94a3b8;">$ ' + command + '</div>' +
                        '<div style="color: #ef4444; margin-top: 8px;">Error: ' + escapeHtml(response.data || 'Unknown error') + '</div>'
                    );
                    
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('<?php esc_html_e( 'Command failed!', 'wp-genius' ); ?>', 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                $outputContent.html(
                    '<div style="color: #94a3b8;">$ ' + command + '</div>' +
                    '<div style="color: #ef4444; margin-top: 8px;">AJAX Error: ' + escapeHtml(error) + '</div>'
                );
                
                if (typeof w2p !== 'undefined' && w2p.toast) {
                    w2p.toast('<?php esc_html_e( 'Request failed!', 'wp-genius' ); ?>', 'error');
                }
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false);
                resetButtonIcon(buttonId);
            }
        });
    }
    
    // Reset button icon
    function resetButtonIcon(buttonId) {
        const icons = {
            'w2p-check-environment': 'fa-stethoscope',
            'w2p-get-stats': 'fa-chart-bar',
            'w2p-start-conversion': 'fa-play',
            'w2p-start-parallel': 'fa-rocket'
        };
        $('#' + buttonId).find('i').removeClass().addClass('fa-solid ' + icons[buttonId]);
    }
    
    // Format stats
    function formatStats(stats) {
        let html = '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">';
        for (const [key, value] of Object.entries(stats)) {
            html += '<div>';
            html += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">' + escapeHtml(key) + '</div>';
            html += '<div style="font-size: 18px; font-weight: 600; color: #1f2937;">' + escapeHtml(value) + '</div>';
            html += '</div>';
        }
        html += '</div>';
        return html;
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    
    // Note: Button click handlers have been moved to media-processing-ui.js
    // The MediaProcessingUI module now handles all button interactions
    
})(jQuery);
</script>
