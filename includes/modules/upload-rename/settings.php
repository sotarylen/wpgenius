<?php
if (!defined('ABSPATH')) {
    exit;
}

$current = get_option('w2p_upload_rename_pattern', '{timestamp}_{sanitized}');
?>
<div class="w2p-module-settings" id="w2p-module-settings-upload-rename">
    <div class="w2p-module-header">
        <h2><?php _e('Upload Rename Settings', 'wp-genius'); ?></h2>
        <a href="#" class="w2p-module-toggle w2p-module-toggle-button" data-target="w2p-module-settings-upload-rename"><?php _e('Toggle', 'wp-genius'); ?></a>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('word2posts_save_module_settings', 'word2posts_module_nonce'); ?>
        <input type="hidden" name="action" value="word2posts_save_module_settings" />
        <input type="hidden" name="module_id" value="upload-rename" />
        <table class="form-table">
            <tr>
                <th scope="row"><label for="w2p_upload_rename_pattern"><?php _e('Rename Pattern', 'wp-genius'); ?></label></th>
                <td>
                    <input name="w2p_upload_rename_pattern" type="text" id="w2p_upload_rename_pattern" value="<?php echo esc_attr($current); ?>" class="regular-text" />
                    <p class="description"><?php _e('Use {timestamp}, {sanitized}, {rand} in the pattern. Example: {timestamp}_{sanitized}', 'wp-genius'); ?></p>

                    <p class="description">
                        <?php _e('
                            已有：{timestamp}（Unix 时间戳），{sanitized}（已清理的原始基名），{rand}（随机数）
                            新增：{date} 或 {date:FORMAT}（例：{date} → 2025-11-29，{date:Ymd} → 20251129）
                            新增：{datetime}（等同于 date("YmdHis")）
                            新增：{year} {month} {day} {hour} {minute} {second}
                            新增：{user_id} {user_login}（上传时当前登录用户信息；匿名上传时为空/0）
                            新增：{orig}（上传前的原始基名，已做最小 sanitize）
                            新增：{ext}（文件扩展名，不含点）
                            新增：{uniqid}（PHP 的 uniqid）', 'wp-genius'); 
                        ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Save Module Settings', 'wp-genius')); ?>
    </form>
</div>
