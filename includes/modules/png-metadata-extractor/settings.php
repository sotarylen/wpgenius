<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="w2p-module-settings-panel">
    <div class="w2p-module-header">
        <h2><?php _e('PNG Metadata Extractor Settings', 'wp-genius'); ?></h2>
    </div>

    <table class="form-table w2p-module-settings-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label><?php _e('Overview', 'wp-genius'); ?></label>
                </th>
                <td>
                    <p class="description">
                        <?php _e('This module extracts Stable Diffusion parameters from PNG images and allows you to view them in the media library and on the frontend.', 'wp-genius'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Features', 'wp-genius'); ?></label>
                </th>
                <td>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><?php _e('Batch extract PNG metadata from media library', 'wp-genius'); ?></li>
                        <li><?php _e('Display Stable Diffusion parameters in media library', 'wp-genius'); ?></li>
                        <li><?php _e('Show metadata icon on images in posts/pages', 'wp-genius'); ?></li>
                        <li><?php _e('No automatic metadata extraction on upload (reduces server load)', 'wp-genius'); ?></li>
                        <li><?php _e('Store metadata as custom fields for easy access', 'wp-genius'); ?></li>
                    </ul>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Usage Instructions', 'wp-genius'); ?></label>
                </th>
                <td>
                    <p class="description">
                        <strong><?php _e('Extracting Metadata:', 'wp-genius'); ?></strong><br>
                        <?php _e('Go to Media > Library, select PNG images, choose "Extract PNG Metadata" from the bulk actions dropdown, and click Apply.', 'wp-genius'); ?>
                    </p>
                    <p class="description">
                        <strong><?php _e('Viewing Metadata:', 'wp-genius'); ?></strong><br>
                        <?php _e('In the media library, click on an image to view its metadata in the attachment details. On the frontend, click the 📊 icon that appears on images with Stable Diffusion metadata.', 'wp-genius'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Supported Parameters', 'wp-genius'); ?></label>
                </th>
                <td>
                    <p class="description">
                        <?php _e('This module extracts the following Stable Diffusion parameters from PNG files:', 'wp-genius'); ?>
                    </p>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><?php _e('Prompt text', 'wp-genius'); ?></li>
                        <li><?php _e('Negative prompt', 'wp-genius'); ?></li>
                        <li><?php _e('Sampling steps', 'wp-genius'); ?></li>
                        <li><?php _e('CFG scale', 'wp-genius'); ?></li>
                        <li><?php _e('Seed', 'wp-genius'); ?></li>
                        <li><?php _e('Model hash', 'wp-genius'); ?></li>
                        <li><?php _e('Other generation parameters', 'wp-genius'); ?></li>
                    </ul>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Performance Considerations', 'wp-genius'); ?></label>
                </th>
                <td>
                    <p class="description">
                        <?php _e('To minimize server load, this module does not automatically extract metadata when images are uploaded. Metadata extraction is performed only when manually triggered through bulk actions.', 'wp-genius'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
</div>