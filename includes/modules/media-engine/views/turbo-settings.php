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

$all_settings = get_option( 'w2p_settings', [] );
$settings = isset( $all_settings['media_engine_tabs'] ) ? $all_settings['media_engine_tabs'] : [];
?>

<div class="w2p-settings-panel w2p-media-turbo-settings">
    
    <!-- Media Processing Center -->
    <div class="w2p-section">
        <div class="w2p-section-header">
            <h4><?php esc_html_e( 'Media Processing Center', 'wp-genius' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Process images using external command-line tools.', 'wp-genius' ); ?></p>
        </div>
        <div class="w2p-section-body">
            
            <!-- Processing Actions -->
            <div class="w2p-processing-actions">
                <div class="w2p-flex w2p-gap-sm w2p-items-center">
                    <button type="button" id="w2p-get-stats" class="w2p-btn w2p-btn-primary">
                        <i class="fa-solid fa-chart-bar"></i>
                        <?php esc_html_e( 'Get Pending Stats', 'wp-genius' ); ?>
                    </button>
                    <?php
                    // Retrieve settings from option since we removed the form
                    // This ensures JS gets the correct values from the database
                    $scan_limit = isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 500;
                    $batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 10;
                    ?>
                    <button type="button" id="w2p-start-conversion" class="w2p-btn w2p-btn-secondary">
                        <i class="fa-solid fa-play"></i>
                        <?php echo sprintf( esc_html__( 'Batch Conversion (%d items, batch %d)', 'wp-genius' ), $scan_limit, $batch_size ); ?>
                    </button>
                    <button type="button" id="w2p-stop-conversion" class="w2p-btn w2p-btn-stop w2p-hidden">
                        <i class="fa-solid fa-stop"></i>
                        <?php esc_html_e( 'Stop Processing', 'wp-genius' ); ?>
                    </button>
                </div>
            </div>

            <!-- Processing Output -->
            <div id="w2p-processing-output" class="w2p-hidden w2p-terminal">
                <div id="w2p-output-content"></div>
            </div>

            <!-- Attachment List -->
            <div id="w2p-attachment-list" class="w2p-hidden w2p-attachment-list">
                <div class="w2p-flex-between w2p-mb-sm">
                    <h5 class="w2p-mb-0">
                        <?php esc_html_e( 'Processing Queue', 'wp-genius' ); ?>
                    </h5>
                    <div id="w2p-queue-stats" class="w2p-queue-stats"></div>
                </div>
                <div class="w2p-scrollable">
                    <table class="w2p-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Thumb', 'wp-genius' ); ?></th>
                                <th><?php esc_html_e( 'File', 'wp-genius' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="w2p-attachment-tbody">
                            <!-- Attachments will be inserted here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Processing Stats -->
            <div id="w2p-processing-stats" class="w2p-hidden w2p-processing-stats">
                <h5 class="w2p-mb-sm">
                    <?php esc_html_e( 'Processing Statistics', 'wp-genius' ); ?>
                </h5>
                <div id="w2p-stats-content"></div>
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
    
    // Pass configuration to MediaProcessingUI
    if (typeof MediaProcessingUI !== 'undefined') {
        MediaProcessingUI.concurrentWorkers = batchSize;
    }
    
    // 设置全局配置供 media-processing-ui.js 使用
    window.w2pMediaConfig = {
        scanLimit: scanLimit,
        batchSize: batchSize
    };
    
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
        $outputContent.html('<div class="w2p-terminal-command">$ ' + command + '</div><div class="w2p-terminal-output">Processing...</div>');
        
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
                    let html = '<div class="w2p-terminal-command">$ ' + command + '</div>';
                    lines.forEach(line => {
                        if (line.trim()) {
                            html += '<div>' + escapeHtml(line) + '</div>';
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
                        '<div class="w2p-terminal-command">$ ' + command + '</div>' +
                        '<div class="w2p-terminal-error">Error: ' + escapeHtml(response.data || 'Unknown error') + '</div>'
                    );
                    
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('<?php esc_html_e( 'Command failed!', 'wp-genius' ); ?>', 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                $outputContent.html(
                    '<div class="w2p-terminal-command">$ ' + command + '</div>' +
                    '<div class="w2p-terminal-error">AJAX Error: ' + escapeHtml(error) + '</div>'
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
        let html = '<div class="w2p-stats-grid">';
        for (const [key, value] of Object.entries(stats)) {
            html += '<div>';
            html += '<div class="w2p-stat-label">' + escapeHtml(key) + '</div>';
            html += '<div class="w2p-stat-value">' + escapeHtml(value) + '</div>';
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
