<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto Upload Images Module - Settings Handler
 * 处理模块设置的保存
 */
class AutoUploadImages_Settings_Handler {
    
    /**
     * 处理设置保存
     */
    public static function handle_settings() {
        if (!isset($_POST['submit']) && !isset($_POST['reset'])) {
            return;
        }
        
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-genius'));
        }
        
        if (!check_admin_referer('w2p_auto_upload_settings')) {
            wp_die(__('Security check failed. Please try again.', 'wp-genius'));
        }
        
        if (isset($_POST['submit'])) {
            self::save_settings();
        } elseif (isset($_POST['reset'])) {
            self::reset_settings();
        }
    }
    
    /**
     * 保存设置
     */
    private static function save_settings() {
        $options = array();
        
        // 处理文本字段
        $text_fields = array('base_url', 'image_name', 'alt_name', 'max_width', 'max_height', 'concurrent_threads', 'max_retries');
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                if ($field === 'image_name' || $field === 'alt_name') {
                    $_POST[$field] = self::replace_deprecated_patterns($_POST[$field]);
                }
                
                // 处理数字字段
                if ($field === 'concurrent_threads' || $field === 'max_retries' || $field === 'max_width' || $field === 'max_height') {
                    $options[$field] = intval($_POST[$field]);
                } else {
                    $options[$field] = sanitize_text_field($_POST[$field]);
                }
            }
        }
        
        // 处理排除URLs
        if (isset($_POST['exclude_urls'])) {
            $options['exclude_urls'] = sanitize_textarea_field($_POST['exclude_urls']);
        }
        
        // 处理排除的文章类型
        if (isset($_POST['exclude_post_types']) && is_array($_POST['exclude_post_types'])) {
            $options['exclude_post_types'] = array_map('sanitize_text_field', $_POST['exclude_post_types']);
        }

        // Handle boolean fields
        $options['set_featured_image'] = isset($_POST['set_featured_image']) ? (bool) $_POST['set_featured_image'] : false;
        
        // 保存选项
        update_option(AutoUploadImagesModuleModule::WP_OPTIONS_KEY, $options);
        
        // 设置成功消息
        set_transient('w2p_auto_upload_settings_message', __('Settings saved.', 'wp-genius'), 45);
        
        // 重定向回设置页面
        wp_redirect(admin_url('admin.php?page=wp-genius&tab=modules&module=auto-upload-images'));
        exit;
    }
    
    /**
     * 重置设置为默认值
     */
    private static function reset_settings() {
        AutoUploadImagesModuleModule::resetOptionsToDefaults();
        
        // 设置重置消息
        set_transient('w2p_auto_upload_settings_message', __('Settings reset to defaults.', 'wp-genius'), 45);
        
        // 重定向回设置页面
        wp_redirect(admin_url('admin.php?page=wp-genius&tab=modules&module=auto-upload-images'));
        exit;
    }
    
    /**
     * 替换已弃用的模式
     * @param $pattern
     * @return string
     */
    private static function replace_deprecated_patterns($pattern) {
        preg_match_all('/%(date|day)%/', $pattern, $rules);
        
        $patterns = array(
            '%date%' => '%today_date%',
            '%day%' => '%today_day%',
        );
        
        if ($rules[0]) {
            foreach ($rules[0] as $rule) {
                $pattern = preg_replace("/$rule/", array_key_exists($rule, $patterns) ? $patterns[$rule] : $rule, $pattern);
            }
        }
        
        return $pattern;
    }
    
    /**
     * 获取设置消息
     */
    public static function get_settings_message() {
        $message = get_transient('w2p_auto_upload_settings_message');
        if ($message) {
            delete_transient('w2p_auto_upload_settings_message');
            return $message;
        }
        return false;
    }
}

// 处理设置保存
if (is_admin() && isset($_POST['submit']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'w2p_auto_upload_settings')) {
    AutoUploadImages_Settings_Handler::handle_settings();
}
?>