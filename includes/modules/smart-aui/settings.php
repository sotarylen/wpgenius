<?php
/**
 * Smart Auto Upload Images Module Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'smart_aui_settings', [] );
$defaults = [
	'base_url' => get_site_url(),
	'image_name_pattern' => '%filename%',
	'alt_text_pattern' => '%image_alt%',
	'min_width' => 300,
	'min_height' => 200,
	'exclude_post_types' => [],
	'exclude_domains' => '',
	'auto_set_featured_image' => true,
	'show_progress_ui' => true,
	'process_images_on_rest_api' => true,
	'concurrent_threads' => 4,
	'concurrent_threads' => 4,
	'max_retries' => 3,
	'skip_duplicates' => true,
	'capture_videos' => false,
];
$settings = wp_parse_args( $settings, $defaults );

// 获取所有文章类型用于排除选项
$post_types = get_post_types( [ 'public' => true ], 'objects' );
?>

<div class="w2p-settings-panel w2p-smart-aui-settings" id="w2p-smart-aui-module">
    <div class="w2p-sub-tabs">
        <div class="w2p-sub-tab-nav">
            <a class="w2p-sub-tab-link active" data-tab="settings"><i class="fa-solid fa-gear"></i> <?php _e('Settings', 'wp-genius'); ?></a>
            <a class="w2p-sub-tab-link" data-tab="logs"><i class="fa-solid fa-calendar-days"></i> <?php _e('Capture Logs', 'wp-genius'); ?></a>
        </div>

        <div class="w2p-sub-tab-content active" id="w2p-tab-settings">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('word2posts_save_module_settings', 'w2p_smart_aui_nonce'); ?>
            <input type="hidden" name="action" value="word2posts_save_module_settings" />
            <input type="hidden" name="module_id" value="smart-auto-upload-images" />

            <!-- Core Configuration -->
            <div class="w2p-section">
                <!-- ... existing content ... -->
                <div class="w2p-section-header">
                    <h4><?php _e('Core Configuration', 'wp-genius'); ?></h4>
                </div>
                <!-- ... remains same ... -->
                <div class="w2p-section-body">
                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="base_url"><?php _e('Base URL', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <input type="url" id="base_url" name="smart_aui_settings[base_url]" value="<?php echo esc_attr($settings['base_url']); ?>" class="w2p-input-large" />
                            <p class="description"><?php _e('The base URL to use for uploaded images. Defaults to your site URL.', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="image_name_pattern"><?php _e('Image Name Pattern', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <input type="text" id="image_name_pattern" name="smart_aui_settings[image_name_pattern]" value="<?php echo esc_attr($settings['image_name_pattern']); ?>" class="w2p-input-large" />
                            <p class="description"><?php _e('Pattern for naming uploaded images. Available variables: %filename%, %post_title%, %post_date%, %random%. Default: %filename%', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="alt_text_pattern"><?php _e('Alt Text Pattern', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <input type="text" id="alt_text_pattern" name="smart_aui_settings[alt_text_pattern]" value="<?php echo esc_attr($settings['alt_text_pattern']); ?>" class="w2p-input-large" />
                            <p class="description"><?php _e('Pattern for alt text of uploaded images. Available variables: %image_alt%, %filename%, %post_title%. Default: %image_alt%', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label"><?php _e('Image Size Rules', 'wp-genius'); ?></div>
                        <div class="w2p-form-control">
                            <div class="w2p-flex w2p-gap-md">
                                <div class="w2p-flex w2p-items-center w2p-gap-xs">
                                    <label for="min_width"><?php _e('Min Width', 'wp-genius'); ?>:</label>
                                    <input type="number" id="min_width" name="smart_aui_settings[min_width]" value="<?php echo esc_attr($settings['min_width']); ?>" min="0" class="w2p-input-small" /> <span>px</span>
                                </div>
                                <div class="w2p-flex w2p-items-center w2p-gap-xs">
                                    <label for="min_height"><?php _e('Min Height', 'wp-genius'); ?>:</label>
                                    <input type="number" id="min_height" name="smart_aui_settings[min_height]" value="<?php echo esc_attr($settings['min_height']); ?>" min="0" class="w2p-input-small" /> <span>px</span>
                                </div>
                            </div>
                            <p class="description"><?php _e('Skip images with dimensions smaller than these values (useful for filtering out icons/avatars). Set to 0 to process all images.', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row border-none">
                        <div class="w2p-form-label">
                            <label for="exclude_domains"><?php _e('Exclude Domains', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <textarea id="exclude_domains" name="smart_aui_settings[exclude_domains]" class="w2p-input-large w2p-textarea-large" placeholder="example.com"><?php echo esc_textarea($settings['exclude_domains']); ?></textarea>
                            <p class="description"><?php _e('Enter domains to exclude from processing, one per line. Supports wildcards (e.g., *.xuite.net).', 'wp-genius'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Automation Behavior -->
            <div class="w2p-section">
                <div class="w2p-section-header">
                    <h4><?php _e('Automation Behavior', 'wp-genius'); ?></h4>
                </div>
                <div class="w2p-section-body">
                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label><?php _e('Auto Set Featured Image', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <label class="w2p-switch">
                                <input type="checkbox" id="auto_set_featured_image" name="smart_aui_settings[auto_set_featured_image]" value="1" <?php checked($settings['auto_set_featured_image'], 1); ?> />
                                <span class="w2p-slider"></span>
                            </label>
                            <p class="description"><?php _e('Automatically set the first uploaded image as featured image if none exists.', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label><?php _e('Show Upload Progress', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <label class="w2p-switch">
                                <input type="checkbox" id="show_progress_ui" name="smart_aui_settings[show_progress_ui]" value="1" <?php checked($settings['show_progress_ui'], 1); ?> />
                                <span class="w2p-slider"></span>
                            </label>
                            <p class="description"><?php _e('Display a progress bar when saving posts with external images.', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label><?php _e('Skip Duplicate Images', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <label class="w2p-switch">
                                <input type="checkbox" id="skip_duplicates" name="smart_aui_settings[skip_duplicates]" value="1" <?php checked($settings['skip_duplicates'] ?? true, 1); ?> />
                                <span class="w2p-slider"></span>
                            </label>
                            <p class="description"><?php _e('If enabled, existing images (matched by source URL or content) will be reused. If disabled, all images will be redownloaded as new files.', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label><?php _e('REST API Support', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <label class="w2p-switch">
                                <input type="checkbox" id="process_images_on_rest_api" name="smart_aui_settings[process_images_on_rest_api]" value="1" <?php checked($settings['process_images_on_rest_api'] ?? true, 1); ?> />
                                <span class="w2p-slider"></span>
                            </label>
                            <p class="description"><?php _e('Automatically upload remote images when content is created via REST API.', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row border-none">
                        <div class="w2p-form-label">
                            <label><?php _e('Capture Videos', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <label class="w2p-switch">
                                <input type="checkbox" id="capture_videos" name="smart_aui_settings[capture_videos]" value="1" <?php checked($settings['capture_videos'] ?? false, 1); ?> />
                                <span class="w2p-slider"></span>
                            </label>
                            <p class="description"><?php _e('Automatically download and import remote videos from &lt;video&gt; tags to media library.', 'wp-genius'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance & Limits -->
            <div class="w2p-section">
                <div class="w2p-section-header">
                    <h4><?php _e('Performance & Rules', 'wp-genius'); ?></h4>
                </div>
                <div class="w2p-section-body">
                    <div class="w2p-form-row">
                        <div class="w2p-form-label"><?php _e('Exclude Post Types', 'wp-genius'); ?></div>
                        <div class="w2p-form-control">
                            <div class="w2p-flex-wrap w2p-gap-sm">
                                <?php foreach ($post_types as $post_type): ?>
                                    <?php if (in_array($post_type->name, ['attachment', 'revision', 'nav_menu_item'])) continue; ?>
                                    <label class="w2p-flex w2p-items-center w2p-gap-xs" style="min-width: 140px; background: var(--w2p-bg-surface-secondary); padding: 5px 10px; border-radius: var(--w2p-radius-sm); margin-bottom: 5px;">
                                        <input type="checkbox" name="smart_aui_settings[exclude_post_types][]" value="<?php echo esc_attr($post_type->name); ?>" class="w2p-checkbox" <?php checked(in_array($post_type->name, $settings['exclude_post_types'])); ?> />
                                        <span><?php echo esc_html($post_type->labels->name); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php _e('Post types that should skip automatic image processing.', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="concurrent_threads"><?php _e('Concurrent Threads', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <div class="w2p-range-group">
                                <div class="w2p-range-header">
                                    <span class="w2p-range-label"><?php _e('Thread Count', 'wp-genius'); ?></span>
                                    <span class="w2p-range-value"><?php echo esc_attr((int) $settings['concurrent_threads']); ?></span>
                                </div>
                                <input type="range" 
                                       class="w2p-range-slider" 
                                       id="concurrent_threads"
                                       name="smart_aui_settings[concurrent_threads]" 
                                       min="1" 
                                       max="16" 
                                       step="1"
                                       value="<?php echo esc_attr((int) $settings['concurrent_threads']); ?>">
                            </div>
                            <p class="description"><?php _e('Maximum number of concurrent image downloads per post.', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-row border-none">
                        <div class="w2p-form-label">
                            <label for="max_retries"><?php _e('Max Retries', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <div class="w2p-range-group">
                                <div class="w2p-range-header">
                                    <span class="w2p-range-label"><?php _e('Retry Attempts', 'wp-genius'); ?></span>
                                    <span class="w2p-range-value"><?php echo esc_attr((int) $settings['max_retries']); ?></span>
                                </div>
                                <input type="range" 
                                       class="w2p-range-slider" 
                                       id="max_retries"
                                       name="smart_aui_settings[max_retries]" 
                                       min="0" 
                                       max="10" 
                                       step="1"
                                       value="<?php echo esc_attr((int) $settings['max_retries']); ?>">
                            </div>
                            <p class="description"><?php _e('Maximum retry attempts when an image download fails.', 'wp-genius'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w2p-settings-actions">
                <button type="submit" name="submit" id="w2p-smart-aui-submit" class="w2p-btn w2p-btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?php esc_attr_e('Save All Settings', 'wp-genius'); ?>
                </button>
            </div>
        </form>
        </div>

        <!-- Logs Tab Content -->
        <div class="w2p-sub-tab-content" id="w2p-tab-logs">
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e('Capture Failure Logs', 'wp-genius'); ?></h4>
                <button type="button" id="w2p-smart-aui-clear-logs" class="w2p-btn w2p-btn-secondary">
                    <i class="fa-solid fa-trash"></i>
                    <?php _e('Clear All Logs', 'wp-genius'); ?>
                </button>
            </div>
            <div class="w2p-section-body">
                <p class="description" style="margin-bottom: 20px;">
                    <?php _e('The following image URLs failed to download and will be skipped in future attempts to avoid infinite retry loops.', 'wp-genius'); ?>
                </p>
                
                <div class="w2p-log-container">
                    <?php
                    $manager = \SmartAutoUploadImages\get_container()->get('failed_images_manager');
                    $failed_urls = $manager->get_failed_urls();
                    
                    if (empty($failed_urls)): ?>
                        <div class="w2p-empty-state">
                            <i class="fa-solid fa-calendar-days"></i>
                            <p><?php _e('No capture failures recorded.', 'wp-genius'); ?></p>
                        </div>
                    <?php else: 
                        // 按时间倒序排序
                        arsort($failed_urls);
                        ?>
                        <table class="w2p-log-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Image URL', 'wp-genius'); ?></th>
                                    <th><?php _e('Time', 'wp-genius'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($failed_urls as $url => $timestamp): ?>
                                    <tr>
                                        <td class="w2p-log-url"><code><?php echo esc_html($url); ?></code></td>
                                        <td class="w2p-log-time"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
    
    <!-- Native React App Container -->
    <div id="smart-aui-admin-root"></div>
</div>

<script>
jQuery(document).ready(function($) {
    // Check for settings saved param and show feedback on Save button
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('settings-updated') === 'true') {
        const $saveBtn = $('#w2p-smart-aui-submit');
        if ($saveBtn.length) {
            w2p.toast('<?php _e('Settings Saved', 'wp-genius'); ?>', 'success');
        }
    }

    // Clear logs
    $('#w2p-smart-aui-clear-logs').on('click', function() {
        var $btn = $(this);
        w2p.confirm('<?php _e('Are you sure you want to clear all capture logs?', 'wp-genius'); ?>', () => {
             w2p.loading($btn, true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'w2p_smart_aui_clear_failed_logs',
                    nonce: '<?php echo wp_create_nonce("w2p_smart_aui_progress"); ?>'
                },
                success: function(response) {
                    w2p.loading($btn, false);
                    if (response.success) {
                        w2p.toast('<?php _e('Logs Cleared', 'wp-genius'); ?>', 'success');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        w2p.toast('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    w2p.loading($btn, false);
                    w2p.toast('Connection Error', 'error');
                }
            });
        });
    });
});
</script>

