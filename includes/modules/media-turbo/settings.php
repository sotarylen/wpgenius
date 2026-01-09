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
    <?php if ( ! $webp_supported ) : ?>
        <div class="notice notice-error inline" style="margin-bottom: var(--w2p-spacing-lg);">
            <p><?php esc_html_e( 'WebP is not supported by your server\'s GD library. Please contact your hosting provider to enable it.', 'wp-genius' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="w2p-flex w2p-gap-xl">
        <!-- Left Column: Configuration -->
        <div class="w2p-flex-1">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_media_turbo_nonce' ); ?>
                <input type="hidden" name="action" value="word2posts_save_module_settings" />
                <input type="hidden" name="module_id" value="media-turbo" />

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
                                    <input type="checkbox" name="w2p_media_turbo_settings[webp_enabled]" value="1" <?php checked( ! empty( $settings['webp_enabled'] ) ); ?> <?php disabled( ! $webp_supported ); ?> />
                                    <span class="w2p-slider"></span>
                                </label>
                                <p class="description"><?php esc_html_e( 'Automatically convert newly uploaded images to WebP.', 'wp-genius' ); ?></p>
                            </div>
                        </div>

                        <div class="w2p-form-row">
                            <div class="w2p-form-label">
                                <label for="webp_quality"><?php esc_html_e( 'WebP Quality', 'wp-genius' ); ?></label>
                            </div>
                            <div class="w2p-form-control">
                                <div class="w2p-range-group">
                                    <div class="w2p-range-header">
                                        <span class="w2p-range-label"><?php esc_html_e( 'Quality Level', 'wp-genius' ); ?></span>
                                        <span class="w2p-range-value"><?php echo esc_attr( $settings['webp_quality'] ); ?>%</span>
                                    </div>
                                    <input type="range" 
                                           class="w2p-range-slider" 
                                           id="webp_quality"
                                           name="w2p_media_turbo_settings[webp_quality]" 
                                           min="1" 
                                           max="100" 
                                           step="1"
                                           value="<?php echo esc_attr( $settings['webp_quality'] ); ?>"
                                           data-suffix="%">
                                </div>
                                <p class="description"><?php esc_html_e( 'Target quality level for WebP images.', 'wp-genius' ); ?></p>
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
                                <p class="description"><?php esc_html_e( 'If disabled, original JPG/PNG files will be deleted after conversion.', 'wp-genius' ); ?></p>
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
                                           min="1" 
                                           max="1000" 
                                           step="10"
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
                                        <span><?php esc_html_e( 'Scan Media Library', 'wp-genius' ); ?></span>
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
                                           max="50" 
                                           step="1"
                                           value="<?php echo esc_attr( $settings['batch_size'] ?? 10 ); ?>">
                                </div>
                                <p class="description"><?php esc_html_e( 'Items to process per AJAX request.', 'wp-genius' ); ?></p>
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

        <!-- Right Column: Execution Center -->
        <div class="w2p-flex-1">
            <div class="w2p-section">
                <div class="w2p-section-header">
                    <h4><?php esc_html_e( 'Bulk Optimization Center', 'wp-genius' ); ?></h4>
                </div>
                
                <div class="w2p-section-body">
                    <div class="w2p-bulk-actions w2p-flex w2p-flex-wrap w2p-gap-sm">
                        <button type="button" id="w2p-scan-media" class="w2p-btn w2p-btn-primary">
                            <i class="fa-solid fa-images"></i>
                            <?php esc_html_e( 'Scan Media Library', 'wp-genius' ); ?>
                        </button>
                        <button type="button" id="w2p-start-bulk" class="w2p-btn w2p-btn-success" style="display:none;">
                            <i class="fa-solid fa-play"></i>
                            <?php esc_html_e( 'Start Bulk Conversion', 'wp-genius' ); ?>
                        </button>
                        <button type="button" id="w2p-stop-bulk" class="w2p-btn w2p-btn-stop" style="display:none;">
                            <i class="fa-solid fa-pause"></i>
                            <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
                        </button>
                        <button type="button" id="w2p-reset-processed" class="w2p-btn w2p-btn-secondary">
                            <i class="fa-solid fa-rotate"></i>
                            <?php esc_html_e( 'Reset Processed Posts', 'wp-genius' ); ?>
                        </button>
                    </div>

                    <?php 
                    $processed_posts = get_option( 'w2p_media_turbo_processed_posts', [] );
                    if ( ! empty( $processed_posts ) ) : 
                    ?>
                        <div class="w2p-info-box" style="margin-top: var(--w2p-spacing-md); background: var(--w2p-bg-surface-secondary); padding: var(--w2p-spacing-md); border-radius: var(--w2p-radius-md);">
                            <p style="margin:0;"><?php printf( esc_html__( 'Processed posts: %d', 'wp-genius' ), count( $processed_posts ) ); ?></p>
                        </div>
                    <?php endif; ?>

                    <div id="w2p-bulk-progress-wrapper" class="w2p-progress-wrapper" style="display:none; margin-top:var(--w2p-spacing-lg);">
                        <div class="w2p-progress-container" style="background: var(--w2p-bg-surface-tertiary); height: 12px; border-radius: var(--w2p-radius-full); overflow: hidden;">
                            <div id="w2p-bulk-progress-bar" class="w2p-progress-fill" style="width:0%; height:100%; background: var(--w2p-gradient-primary); transition: width 0.3s ease;"></div>
                        </div>
                        <div id="w2p-bulk-status-detailed" class="w2p-status-stats" style="margin-top: 10px; font-weight:var(--w2p-font-weight-medium);"></div>
                    </div>

                    <div id="w2p-scan-results-wrapper" class="w2p-scan-results" style="display:none; margin-top: var(--w2p-spacing-lg);">
                        <table class="w2p-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 60px;"><?php esc_html_e( 'Img', 'wp-genius' ); ?></th>
                                    <th><?php esc_html_e( 'File & Association', 'wp-genius' ); ?></th>
                                    <th style="width: 120px;"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
                                    <th style="width: 100px;"><?php esc_html_e( 'Time', 'wp-genius' ); ?></th>
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
</div>



