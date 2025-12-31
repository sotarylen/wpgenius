<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="w2p-module-settings-panel">

<div class="w2p-section">
    <div class="w2p-section-header">
        <h4><?php _e('PNG Metadata Extractor Overview', 'wp-genius'); ?></h4>
    </div>

    <div class="w2p-section-body">
        <div class="w2p-info-box">
            <p><?php _e('This module extracts Stable Diffusion parameters from PNG images and allows you to view them in the media library and on the frontend.', 'wp-genius'); ?></p>
        </div>

        <div class="w2p-form-row">
            <div class="w2p-form-label">
                <?php _e('Key Features', 'wp-genius'); ?>
            </div>
            <div class="w2p-form-control">
                <ul class="w2p-features-list" style="margin: 0; padding-left: 20px;">
                    <li><?php _e('Batch extract PNG metadata from media library', 'wp-genius'); ?></li>
                    <li><?php _e('Display Stable Diffusion parameters in media library', 'wp-genius'); ?></li>
                    <li><?php _e('Show metadata icon on images in posts/pages', 'wp-genius'); ?></li>
                    <li><?php _e('Store metadata as custom fields for easy access', 'wp-genius'); ?></li>
                </ul>
            </div>
        </div>

        <div class="w2p-form-row">
            <div class="w2p-form-label">
                <?php _e('How to Use', 'wp-genius'); ?>
            </div>
            <div class="w2p-form-control">
                <p><strong><?php _e('Extracting Metadata:', 'wp-genius'); ?></strong></p>
                <p class="description">
                    <?php _e('Go to Media > Library, select PNG images, choose "Extract PNG Metadata" from the bulk actions dropdown, and click Apply.', 'wp-genius'); ?>
                </p>
                <p style="margin-top: 15px;"><strong><?php _e('Viewing Metadata:', 'wp-genius'); ?></strong></p>
                <p class="description">
                    <?php _e('In the media library, click on an image to view its metadata in the attachment details. On the frontend, click the 📊 icon that appears on images with Stable Diffusion metadata.', 'wp-genius'); ?>
                </p>
            </div>
        </div>

        <div class="w2p-form-row">
            <div class="w2p-form-label">
                <?php _e('Supported Parameters', 'wp-genius'); ?>
            </div>
            <div class="w2p-form-control">
                <p class="description">
                    <?php _e('This module extracts the following Stable Diffusion parameters from PNG files:', 'wp-genius'); ?>
                </p>
                <ul class="w2p-features-list" style="margin: 0; padding-left: 20px; column-count: 2;">
                    <li><?php _e('Prompt text', 'wp-genius'); ?></li>
                    <li><?php _e('Negative prompt', 'wp-genius'); ?></li>
                    <li><?php _e('Sampling steps', 'wp-genius'); ?></li>
                    <li><?php _e('CFG scale', 'wp-genius'); ?></li>
                    <li><?php _e('Seed', 'wp-genius'); ?></li>
                    <li><?php _e('Model hash', 'wp-genius'); ?></li>
                </ul>
            </div>
        </div>

        <div class="w2p-form-row border-none">
            <div class="w2p-form-label">
                <?php _e('Performance', 'wp-genius'); ?>
            </div>
            <div class="w2p-form-control">
                <p class="description">
                    <?php _e('To minimize server load, this module does not automatically extract metadata when images are uploaded. Metadata extraction is performed only when manually triggered through bulk actions.', 'wp-genius'); ?>
                </p>
            </div>
        </div>
    </div>
</div>
</div>