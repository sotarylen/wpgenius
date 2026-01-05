<?php
/**
 * Post Duplicator Module Settings
 *
 * @package WP_Genius
 * @subpackage Modules/PostDuplicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get helper functions
require_once __DIR__ . '/includes/helpers.php';
use function Mtphr\PostDuplicator\duplicator_post_types;

// Get current settings
$settings = get_option( 'w2p_post_duplicator_settings', [] );
$defaults = [
    'mode' => 'advanced',
    'single_after_duplication_action' => 'notice',
    'list_single_after_duplication_action' => 'notice',
    'list_multiple_after_duplication_action' => 'notice',
    'status' => 'draft',
    'type' => 'same',
    'post_author' => 'current_user',
    'timestamp' => 'current',
    'title' => esc_html__( 'Copy', 'wp-genius' ),
    'slug' => esc_html__( 'copy', 'wp-genius' ),
    'time_offset' => false,
    'time_offset_days' => 0,
    'time_offset_hours' => 0,
    'time_offset_minutes' => 0,
    'time_offset_seconds' => 0,
    'time_offset_direction' => 'newer',
    'duplicate_other_draft' => 'enabled',
    'duplicate_other_pending' => 'enabled',
    'duplicate_other_private' => 'enabled',
    'duplicate_other_password' => 'enabled',
    'duplicate_other_future' => 'enabled',
];
$settings = wp_parse_args( $settings, $defaults );

?>
<div class="wrap w2p-post-duplicator-settings">
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_post_duplicator_nonce' ); ?>
        <input type="hidden" name="action" value="word2posts_save_module_settings" />
        <input type="hidden" name="module_id" value="post-duplicator" />

        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e( 'General Settings', 'wp-genius' ); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <label for="w2p_pd_mode"><?php _e( 'Mode', 'wp-genius' ); ?></label>
                    </div>
                    <div class="w2p-form-control">
                        <select name="w2p_post_duplicator_settings[mode]" id="w2p_pd_mode">
                            <option value="basic" <?php selected( $settings['mode'], 'basic' ); ?>><?php _e( 'Basic (Quick Duplicate)', 'wp-genius' ); ?></option>
                            <option value="advanced" <?php selected( $settings['mode'], 'advanced' ); ?>><?php _e( 'Advanced (Popup Modal)', 'wp-genius' ); ?></option>
                        </select>
                        <p class="description"><?php _e( 'Advanced mode opens a modal to adjust settings before duplication.', 'wp-genius' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e( 'After Duplication', 'wp-genius' ); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <?php _e( 'Post List Screen', 'wp-genius' ); ?>
                    </div>
                    <div class="w2p-form-control">
                        <select name="w2p_post_duplicator_settings[list_single_after_duplication_action]">
                            <option value="notice" <?php selected( $settings['list_single_after_duplication_action'], 'notice' ); ?>><?php _e( 'Display Notice', 'wp-genius' ); ?></option>
                            <option value="refresh" <?php selected( $settings['list_single_after_duplication_action'], 'refresh' ); ?>><?php _e( 'Refresh Page', 'wp-genius' ); ?></option>
                            <option value="new_tab" <?php selected( $settings['list_single_after_duplication_action'], 'new_tab' ); ?>><?php _e( 'Open in New Tab', 'wp-genius' ); ?></option>
                            <option value="same_tab" <?php selected( $settings['list_single_after_duplication_action'], 'same_tab' ); ?>><?php _e( 'Open in Same Tab', 'wp-genius' ); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e( 'Default Values', 'wp-genius' ); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <?php _e( 'Duplicate Title Suffix', 'wp-genius' ); ?>
                    </div>
                    <div class="w2p-form-control">
                        <input type="text" name="w2p_post_duplicator_settings[title]" value="<?php echo esc_attr( $settings['title'] ); ?>" class="w2p-input-medium" />
                        <p class="description"><?php _e( 'Appended to the duplicate post title.', 'wp-genius' ); ?></p>
                    </div>
                </div>
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <?php _e( 'Duplicate Slug Suffix', 'wp-genius' ); ?>
                    </div>
                    <div class="w2p-form-control">
                        <input type="text" name="w2p_post_duplicator_settings[slug]" value="<?php echo esc_attr( $settings['slug'] ); ?>" class="w2p-input-medium" />
                        <p class="description"><?php _e( 'Appended to the duplicate post slug.', 'wp-genius' ); ?></p>
                    </div>
                </div>
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <?php _e( 'Post Status', 'wp-genius' ); ?>
                    </div>
                    <div class="w2p-form-control">
                        <select name="w2p_post_duplicator_settings[status]">
                            <option value="same" <?php selected( $settings['status'], 'same' ); ?>><?php _e( 'Same as original', 'wp-genius' ); ?></option>
                            <option value="draft" <?php selected( $settings['status'], 'draft' ); ?>><?php _e( 'Draft', 'wp-genius' ); ?></option>
                            <option value="publish" <?php selected( $settings['status'], 'publish' ); ?>><?php _e( 'Published', 'wp-genius' ); ?></option>
                            <option value="pending" <?php selected( $settings['status'], 'pending' ); ?>><?php _e( 'Pending', 'wp-genius' ); ?></option>
                        </select>
                    </div>
                </div>
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <?php _e( 'Post Date', 'wp-genius' ); ?>
                    </div>
                    <div class="w2p-form-control">
                        <select name="w2p_post_duplicator_settings[timestamp]">
                            <option value="current" <?php selected( $settings['timestamp'], 'current' ); ?>><?php _e( 'Current Time', 'wp-genius' ); ?></option>
                            <option value="duplicate" <?php selected( $settings['timestamp'], 'duplicate' ); ?>><?php _e( 'Duplicate Timestamp', 'wp-genius' ); ?></option>
                        </select>
                    </div>
                </div>
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <?php _e( 'Post Author', 'wp-genius' ); ?>
                    </div>
                    <div class="w2p-form-control">
                        <select name="w2p_post_duplicator_settings[post_author]">
                            <option value="current_user" <?php selected( $settings['post_author'], 'current_user' ); ?>><?php _e( 'Current User', 'wp-genius' ); ?></option>
                            <option value="original_user" <?php selected( $settings['post_author'], 'original_user' ); ?>><?php _e( 'Original Post Author', 'wp-genius' ); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e( 'Date Offset', 'wp-genius' ); ?></h4>
            </div>
            <div class="w2p-section-body">
                 <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <?php _e( 'Enable Offset', 'wp-genius' ); ?>
                    </div>
                    <div class="w2p-form-control">
                         <label class="w2p-switch">
                            <input type="checkbox" name="w2p_post_duplicator_settings[time_offset]" value="1" <?php checked( $settings['time_offset'] ); ?> />
                            <span class="w2p-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <?php _e( 'Offset Amount', 'wp-genius' ); ?>
                    </div>
                    <div class="w2p-form-control">
                        <div class="w2p-flex w2p-flex-wrap w2p-gap-sm">
                            <div class="w2p-flex w2p-items-center w2p-gap-xs">
                                <input type="number" name="w2p_post_duplicator_settings[time_offset_days]" value="<?php echo esc_attr( $settings['time_offset_days'] ); ?>" class="w2p-input-small" style="width: 70px;" />
                                <span><?php _e( 'Days', 'wp-genius' ); ?></span>
                            </div>
                            <div class="w2p-flex w2p-items-center w2p-gap-xs">
                                <input type="number" name="w2p_post_duplicator_settings[time_offset_hours]" value="<?php echo esc_attr( $settings['time_offset_hours'] ); ?>" class="w2p-input-small" style="width: 70px;" />
                                <span><?php _e( 'Hours', 'wp-genius' ); ?></span>
                            </div>
                            <div class="w2p-flex w2p-items-center w2p-gap-xs">
                                <input type="number" name="w2p_post_duplicator_settings[time_offset_minutes]" value="<?php echo esc_attr( $settings['time_offset_minutes'] ); ?>" class="w2p-input-small" style="width: 70px;" />
                                <span><?php _e( 'Minutes', 'wp-genius' ); ?></span>
                            </div>
                            <div class="w2p-flex w2p-items-center w2p-gap-xs">
                                <input type="number" name="w2p_post_duplicator_settings[time_offset_seconds]" value="<?php echo esc_attr( $settings['time_offset_seconds'] ); ?>" class="w2p-input-small" style="width: 70px;" />
                                <span><?php _e( 'Seconds', 'wp-genius' ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="w2p-form-row border-none">
                    <div class="w2p-form-label">
                        <?php _e( 'Direction', 'wp-genius' ); ?>
                    </div>
                    <div class="w2p-form-control">
                         <select name="w2p_post_duplicator_settings[time_offset_direction]">
                            <option value="newer" <?php selected( $settings['time_offset_direction'], 'newer' ); ?>><?php _e( 'Newer', 'wp-genius' ); ?></option>
                            <option value="older" <?php selected( $settings['time_offset_direction'], 'older' ); ?>><?php _e( 'Older', 'wp-genius' ); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="w2p-settings-actions">
            <button type="submit" name="submit" id="w2p-post-duplicator-submit" class="w2p-btn w2p-btn-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Save Duplicator Settings', 'wp-genius' ); ?>
            </button>
        </div>
    </form>
    <script>
        jQuery(document).ready(function($) {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('settings-updated') === 'true') {
                const $btn = $('#w2p-post-duplicator-submit');
                if (window.WPGenius && WPGenius.UI) {
                    WPGenius.UI.showFeedback($btn, '<?php esc_js( __( 'Settings Saved', 'wp-genius' ) ); ?>', 'success');
                }
            }
        });
    </script>
</div>
