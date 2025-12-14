<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="w2p-module-settings-panel">
    <div class="w2p-module-header">
        <h2><?php _e('Auto Upload Images Settings', 'wp-genius'); ?></h2>
    </div>

    <?php if ($message = AutoUploadImages_Settings_Handler::get_settings_message()) : ?>
    <div id="setting-error-settings_updated" class="updated settings-error">
        <p><strong><?php echo esc_html($message); ?></strong></p>
    </div>
    <?php endif; ?>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content" style="position: relative">
                <div class="stuffbox" style="padding: 0 20px">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('word2posts_save_module_settings', 'word2posts_module_nonce'); ?>
                        <input type="hidden" name="action" value="word2posts_save_module_settings" />
                        <input type="hidden" name="module_id" value="auto-upload-images-module" />
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">
                                    <label for="base_url">
                                        <?php _e('Base URL:', 'wp-genius'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="base_url" value="<?php echo esc_attr(AutoUploadImagesModuleModule::getOption('base_url')); ?>" class="regular-text" dir="ltr" />
                                    <p class="description"><?php _e('If you need to choose a new base URL for images that will be automatically uploaded. Ex:', 'wp-genius'); ?> <code>https://example.com</code>, <code>https://cdn.example.com</code>, <code>/</code></p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="image_name">
                                        <?php _e('Image Name:', 'wp-genius'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="image_name" value="<?php echo esc_attr(AutoUploadImagesModuleModule::getOption('image_name')); ?>" class="regular-text" dir="ltr" />
                                    <p class="description">
                                        <?php printf(__('Choose a custom filename for new images that will be uploaded. You can also use these shortcodes: %s.', 'wp-genius'), '<code dir="ltr">%filename%</code>, <code dir="ltr">%image_alt%</code>, <code dir="ltr">%url%</code>, <code dir="ltr">%today_date%</code>, <code dir="ltr">%year%</code>, <code dir="ltr">%month%</code>, <code dir="ltr">%today_day%</code>, <code dir="ltr">%post_date%</code>, <code dir="ltr">%post_year%</code>, <code dir="ltr">%post_month%</code>, <code dir="ltr">%post_day%</code>, <code dir="ltr">%random%</code>, <code dir="ltr">%timestamp%</code>, <code dir="ltr">%postname%</code>, <code dir="ltr">%post_id%</code>') ?>
                                    </p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="alt_name">
                                        <?php _e('Alt Name:', 'wp-genius'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="alt_name" value="<?php echo esc_attr(AutoUploadImagesModuleModule::getOption('alt_name')); ?>" class="regular-text" dir="ltr" />
                                    <p class="description">
                                        <?php printf(__('Choose a custom alt name for new images that will be uploaded. You can also use these shortcodes: %s.', 'wp-genius'), '<code dir="ltr">%filename%</code>, <code dir="ltr">%image_alt%</code>, <code dir="ltr">%url%</code>, <code dir="ltr">%today_date%</code>, <code dir="ltr">%year%</code>, <code dir="ltr">%month%</code>, <code dir="ltr">%today_day%</code>, <code dir="ltr">%post_date%</code>, <code dir="ltr">%post_year%</code>, <code dir="ltr">%post_month%</code>, <code dir="ltr">%post_day%</code>, <code dir="ltr">%random%</code>, <code dir="ltr">%timestamp%</code>, <code dir="ltr">%postname%</code>, <code dir="ltr">%post_id%</code>') ?>
                                    </p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="set_featured_image">
                                        <?php _e('Set First Image as Featured:', 'wp-genius'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="set_featured_image" value="1" <?php checked(AutoUploadImagesModuleModule::getOption('set_featured_image'), true); ?>>
                                        <?php _e('Automatically set the first uploaded image as the featured image if one is not set.', 'wp-genius'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="concurrent_threads">
                                        <?php _e('Concurrent Threads:', 'wp-genius'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" name="concurrent_threads" min="1" max="10" value="<?php echo esc_attr(AutoUploadImagesModuleModule::getOption('concurrent_threads', 5)); ?>" class="small-text">
                                    <p class="description"><?php _e('Number of concurrent threads for image upload (1-10). Default: 5', 'wp-genius'); ?></p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="max_retries">
                                        <?php _e('Max Retry Count:', 'wp-genius'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" name="max_retries" min="0" max="10" value="<?php echo esc_attr(AutoUploadImagesModuleModule::getOption('max_retries', 3)); ?>" class="small-text">
                                    <p class="description"><?php _e('Maximum number of retries for failed image uploads. Set to 0 to disable retries. Default: 3', 'wp-genius'); ?></p>
                                </td>
                            </tr>
                            <?php if (function_exists('image_make_intermediate_size')) : ?>
                                <?php $editor_supports = wp_image_editor_supports(); ?>
                                <tr valign="top" <?php echo !$editor_supports ? 'style="background-color:#dedede;color:#6d6d6d;opacity:.8;"' : '' ?>>
                                    <th scope="row">
                                        <label <?php echo !$editor_supports ? 'style="color:#6d6d6d;"' : '' ?>><?php _e('Image Size:', 'wp-genius'); ?></label>
                                        <?php if (!$editor_supports) : ?>
                                        <small style="color:#6d6d6d;"><?php _e('(Inactive)', 'wp-genius') ?></small>
                                        <?php endif; ?>
                                    </th>
                                    <td>
                                        <label for="max_width"><?php _e('Max Width', 'wp-genius'); ?></label>
                                        <input name="max_width" type="number" step="5" min="0" id="max_width" placeholder="600" class="small-text" value="<?php echo esc_attr(AutoUploadImagesModuleModule::getOption('max_width')); ?>" <?php echo !$editor_supports ? 'disabled' : '' ?>>
                                        <label for="max_height"><?php _e('Max Height', 'wp-genius'); ?></label>
                                        <input name="max_height" type="number" step="5" min="0" id="max_height" placeholder="400" class="small-text" value="<?php echo esc_attr(AutoUploadImagesModuleModule::getOption('max_height')); ?>" <?php echo !$editor_supports ? 'disabled' : '' ?>>
                                        <p class="description"><?php _e('You can choose max width and height for images uploaded by this module on your site. If you leave empty each one of fields by default use the original size of the image.', 'wp-genius'); ?></p>
                                        <?php if (!$editor_supports) : ?>
                                        <p style="color:#535353;font-weight: bold;"><?php _e('To activate this feature please enable Gd or Imagick extensions of PHP.', 'wp-genius') ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="exclude_post_types">
                                        <?php _e('Exclude Post Types:', 'wp-genius'); ?>
                                    </label>
                                </th>
                                <td>
                                    <p>
                                        <?php $excludePostTypes = AutoUploadImagesModuleModule::getOption('exclude_post_types'); ?>
                                        <?php foreach (get_post_types() as $post_type): ?>
                                            <label>
                                                <input type="checkbox" name="exclude_post_types[]" value="<?php echo esc_attr($post_type) ?>" <?php echo is_array($excludePostTypes) && in_array($post_type, $excludePostTypes, true) ? 'checked' : ''; ?>> <?php echo esc_attr($post_type) ?>
                                                <br>
                                            </label>
                                        <?php endforeach; ?>
                                    </p>
                                    <p class="description"><?php _e('Select Post Types that you want to exclude from automatic uploading.', 'wp-genius'); ?></p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="exclude_urls">
                                        <?php _e('Exclude Domains:', 'wp-genius'); ?>
                                    </label>
                                </th>
                                <td>
                                    <p><?php _e('Enter domains you wish to be excluded from uploading images: (One domain per line)', 'wp-genius'); ?></p>
                                    <p><textarea name="exclude_urls" rows="10" cols="50" id="exclude_urls" class="large-text code" placeholder="https://example.com"><?php echo esc_textarea(AutoUploadImagesModuleModule::getOption('exclude_urls')); ?></textarea></p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <?php submit_button(null, 'primary', 'submit', false); ?>
                            <?php submit_button(__('Reset Options', 'wp-genius'), 'small', 'reset', false, array(
                                'onclick' => 'return confirm("'. __('Are you sure to reset all options to defaults?', 'wp-genius') .'");'
                            )) ?>
                        </p>
                    </form>
                </div>
            </div>
            <div id="postbox-container-1" class="postbox-container">
                <div class="postbox">
                    <h2 class="hndle ui-sortable-handle"><strong><?php _e('Information', 'wp-genius'); ?></strong></h2>
                    <div class="inside">
                        <div class="main">
                            <ul>
                                <li class="dashicons-before dashicons-admin-plugins" style="color: #82878c">
                                    <?php _e('This module automatically uploads external images to your WordPress media library when saving posts.', 'wp-genius'); ?>
                                </li>
                                <li class="dashicons-before dashicons-images-alt2" style="color: #82878c">
                                    <?php _e('Images are downloaded and attached to the post, then the image URLs in the content are replaced with the new local URLs.', 'wp-genius'); ?>
                                </li>
                                <li class="dashicons-before dashicons-download" style="color: #82878c">
                                    <?php _e('You can customize the filename and alt text patterns using the available shortcodes.', 'wp-genius'); ?>
                                </li>
                                <li class="dashicons-before dashicons-format-image" style="color: #82878c">
                                    <?php _e('Optionally resize images during upload to save space and improve loading times.', 'wp-genius'); ?>
                                </li>
                            </ul>
                            <hr>
                            <p><?php _e('This module is based on the "Auto Upload Images" plugin by Ali Irani, integrated into the W2P module system.', 'wp-genius'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <br class="clear">
    </div>
</div>