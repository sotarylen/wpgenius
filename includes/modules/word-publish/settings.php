<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="w2p-module-settings-panel">
    <div class="w2p-module-header">
        <h2><?php _e('WP Genius Settings', 'wp-genius'); ?></h2>
    </div>

    <table class="form-table w2p-module-settings-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label><?php _e('Import Settings', 'wp-genius'); ?></label>
                </th>
                <td>
                    <p class="description">
                        <?php _e('This module imports Word documents (.docx) and converts them into WordPress posts. Configure your import options in the main import form.', 'wp-genius'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Supported Features', 'wp-genius'); ?></label>
                </th>
                <td>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><?php _e('Multi-chapter document splitting', 'wp-genius'); ?></li>
                        <li><?php _e('Custom category assignment', 'wp-genius'); ?></li>
                        <li><?php _e('Author and tag management', 'wp-genius'); ?></li>
                        <li><?php _e('Media file handling', 'wp-genius'); ?></li>
                        <li><?php _e('Upload directory cleanup', 'wp-genius'); ?></li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>

    <p>
        <button type="button" class="button button-primary" onclick="alert('<?php esc_attr_e('Configure options in the main WP Genius page.', 'wp-genius'); ?>')">
            <?php _e('Open Import Form', 'wp-genius'); ?>
        </button>
    </p>
</div>
