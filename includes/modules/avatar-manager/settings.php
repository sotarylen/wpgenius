<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="w2p-module-settings-panel">
    <div class="w2p-module-header">
        <h2><?php _e('Local Avatar Manager Settings', 'wp-genius'); ?></h2>
    </div>

    <table class="form-table w2p-module-settings-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label><?php _e('Overview', 'wp-genius'); ?></label>
                </th>
                <td>
                    <p class="description">
                        <?php _e('This module replaces WordPress Gravatar with a local avatar system. Each user can upload and manage their own avatar image stored directly in your media library.', 'wp-genius'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Features', 'wp-genius'); ?></label>
                </th>
                <td>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><?php _e('Upload/select avatar via media library', 'wp-genius'); ?></li>
                        <li><?php _e('96×96px circular avatar display', 'wp-genius'); ?></li>
                        <li><?php _e('Disable Gravatar completely', 'wp-genius'); ?></li>
                        <li><?php _e('Use local avatars across frontend and admin', 'wp-genius'); ?></li>
                        <li><?php _e('Remove avatar option', 'wp-genius'); ?></li>
                    </ul>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Usage', 'wp-genius'); ?></label>
                </th>
                <td>
                    <p class="description">
                        <?php _e('Users can manage their avatars on their profile page (User > Your Profile). Simply click "Upload / Select Avatar" to choose an image from your media library, or "Remove Avatar" to delete it.', 'wp-genius'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
</div>
