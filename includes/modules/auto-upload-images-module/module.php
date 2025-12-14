<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once 'settings-handler.php';

/**
 * Auto Upload Images Module - 基于极简设计
 * @author WP Genius Plugin
 */
class AutoUploadImagesModuleModule extends W2P_Abstract_Module {
    const WP_OPTIONS_KEY = 'w2p_auto_upload_setting';
    private static $_options;

    public static function id() {
        return 'auto-upload-images-module';
    }

    public static function name() {
        return __('Auto Upload Images', 'wp-genius');
    }

    public static function description() {
        return __('Automatically upload and import external images of a post to WordPress upload directory and media management.', 'wp-genius');
    }

    public function init() {
        // 初始化文本域
        add_action('plugins_loaded', array($this, 'initTextdomain'));

        // 核心功能：基于极简设计的save_post钩子
        add_action('save_post', array($this, 'autoProcessImages'), 20, 3);

        // 注册AJAX动作
        add_action('wp_ajax_w2p_auto_upload_image', array($this, 'handleAjaxUpload'));
        add_action('wp_ajax_w2p_aui_load_progress_template', array($this, 'loadProgressTemplate'));
        
        // 加载脚本
        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
    }

    /**
     * 核心功能：自动处理文章中的图片
     * 修复：完全禁用自动处理，避免与前端JavaScript冲突
     * 前端JavaScript会接管所有图片处理逻辑
     */
    public function autoProcessImages($post_ID, $post, $update) {
        // 完全禁用自动处理，让前端JavaScript接管
        // 这样可以避免重复处理和逻辑冲突
        
        // 只保留特色图片设置功能（独立可选）
        $setFeaturedEnabled = self::getOption('set_featured_image', false);
        if ($setFeaturedEnabled && !has_post_thumbnail($post_ID)) {
            // 检查是否有本地图片可以设置为特色图片
            preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $first_img = $matches[1];
                
                // 检查是否为本地图片
                $site_url = get_site_url();
                if (strpos($first_img, $site_url) === 0 || strpos($first_img, '/wp-content') === 0) {
                    // 尝试把 URL 转成附件 ID
                    $attach_id = attachment_url_to_postid($first_img);
                    if ($attach_id && !is_wp_error($attach_id)) {
                        set_post_thumbnail($post_ID, $attach_id);
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('Auto Upload Images: Set featured image for post ' . $post_ID);
                        }
                    }
                }
            }
        }
    }

    /**
     * 把外链图片拉进本地媒体库并返回结果数组
     * 修复：移除随机字符串，添加重复检测
     */
    private function sideloadImage($image_url, $post_ID) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // 验证图片URL
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid image URL');
        }
        
        // 提取原始文件名（不添加随机字符串）
        $file_info = pathinfo(parse_url($image_url, PHP_URL_PATH));
        $filename = isset($file_info['filename']) ? sanitize_file_name($file_info['filename']) : 'image';
        $extension = isset($file_info['extension']) ? strtolower($file_info['extension']) : 'jpg';
        
        // 检查是否已存在同名文件
        $existing_attachment = $this->findExistingAttachment($filename, $extension);
        if ($existing_attachment) {
            return array(
                'attachment_id' => $existing_attachment,
                'is_existing' => true
            ); // 返回已存在的附件ID
        }
        
        // 下载图片
        $tmp = download_url($image_url, 30); // 30秒超时
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        $file_array = array(
            'name'     => $filename . '.' . $extension,
            'tmp_name' => $tmp,
        );

        $attach_id = media_handle_sideload($file_array, $post_ID);
        @unlink($tmp); // 清理临时文件

        if (is_wp_error($attach_id)) {
            return $attach_id;
        }
        
        return array(
            'attachment_id' => $attach_id,
            'is_existing' => false
        );
    }
    
    /**
     * 查找已存在的附件
     * 通过文件名和扩展名查找
     */
    private function findExistingAttachment($filename, $extension) {
        global $wpdb;
        
        // 构建文件名（包含扩展名）
        $full_filename = $filename . '.' . $extension;
        
        // 查询媒体库中是否已存在此文件
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_title = %s
             AND post_mime_type LIKE %s
             LIMIT 1",
            $filename,
            '%' . $extension . '%'
        ));
        
        if ($attachment_id) {
            return intval($attachment_id);
        }
        
        // 额外检查：通过guid查询（兼容性）
        $upload_dir = wp_upload_dir();
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND guid LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like($full_filename) . '%'
        ));
        
        if ($attachment_id) {
            return intval($attachment_id);
        }
        
        return false;
    }

    /**
     * AJAX处理图片上传（用于前端多线程处理）
     */
    public function handleAjaxUpload() {
        // 验证nonce
        if (!check_ajax_referer('w2p_aui_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // 检查权限
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (empty($image_url) || empty($post_id)) {
            wp_send_json_error('Missing parameters');
            return;
        }

        $uploaded_image = $this->sideloadImage($image_url, $post_id);

        if ($uploaded_image && !is_wp_error($uploaded_image)) {
            // 处理返回的数组格式
            if (is_array($uploaded_image)) {
                $attachment_id = $uploaded_image['attachment_id'];
                $is_existing = $uploaded_image['is_existing'];
            } else {
                // 兼容旧格式
                $attachment_id = $uploaded_image;
                $is_existing = false;
            }
            
            $new_url = wp_get_attachment_url($attachment_id);
            
            wp_send_json_success(array(
                'new_url' => $new_url,
                'old_url' => $image_url,
                'attachment_id' => $attachment_id,
                'is_existing' => $is_existing // 正确标记是否为已存在的文件
            ));
        } else {
            $error_message = is_wp_error($uploaded_image) ? $uploaded_image->get_error_message() : 'Upload failed';
            wp_send_json_error($error_message);
        }
    }

    /**
     * 加载进度模板
     */
    public function loadProgressTemplate() {
        check_ajax_referer('w2p_aui_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }
        
        // 修正模板路径计算
        $plugin_root = plugin_dir_path(dirname(dirname(dirname(__FILE__))));
        $template_path = $plugin_root . 'includes/templates/auto-upload-progress.php';
        
        error_log('Auto Upload Images: Looking for template at: ' . $template_path);
        error_log('Auto Upload Images: File exists: ' . (file_exists($template_path) ? 'YES' : 'NO'));
        
        if (file_exists($template_path)) {
            $template = file_get_contents($template_path);
            wp_send_json_success(array('template' => $template));
        } else {
            wp_send_json_error('Template file not found: ' . $template_path);
        }
    }

    /**
     * 初始化文本域
     */
    public function initTextdomain() {
        load_plugin_textdomain('auto-upload-images', false, basename(dirname(dirname(__FILE__))) . '/modules/auto-upload-images-module/lang');
    }

    /**
     * 加载脚本
     */
    public function enqueueScripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'w2p-auto-upload-images',
            plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'assets/js/auto-upload-images.js',
            array('jquery'),
            filemtime(dirname(dirname(dirname(dirname(__FILE__)))) . '/assets/js/auto-upload-images.js'),
            true
        );

        wp_enqueue_style(
            'w2p-admin-modules',
            plugin_dir_url(dirname(dirname(dirname(__FILE__)))) . 'assets/css/style.css',
            array(),
            filemtime(dirname(dirname(dirname(dirname(__FILE__)))) . '/assets/css/style.css')
        );

        wp_localize_script('w2p-auto-upload-images', 'w2p_aui_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('w2p_aui_nonce'),
            'concurrent_threads' => self::getOption('concurrent_threads', 5),
            'max_retries' => self::getOption('max_retries', 3),
            'messages' => array(
                'title' => __('Auto Upload Images', 'wp-genius'),
                'stop' => __('Stop', 'wp-genius'),
                'minimize' => __('Minimize', 'wp-genius'),
                'close' => __('Close', 'wp-genius'),
                'active_threads' => __('active threads', 'wp-genius'),
                'total_images' => __('Total', 'wp-genius'),
                'images' => __('images', 'wp-genius'),
                'preparing' => __('Preparing to process images...', 'wp-genius'),
                'start' => __('Starting image upload...', 'wp-genius'),
                'success' => __('Success', 'wp-genius'),
                'failed' => __('Failed', 'wp-genius'),
                'finished' => __('All images processed.', 'wp-genius'),
            ),
        ));
    }

    /**
     * 获取选项
     */
    public static function getOptions() {
        if (static::$_options) {
            return static::$_options;
        }
        $defaults = array(
            'set_featured_image' => false,
            'concurrent_threads' => 5,
            'max_retries' => 3,
        );
        return static::$_options = wp_parse_args(get_option(self::WP_OPTIONS_KEY), $defaults);
    }

    /**
     * 获取特定选项
     */
    public static function getOption($key, $default = null) {
        $options = static::getOptions();
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public function register_settings() {
        include_once dirname(__FILE__) . '/settings.php';
    }

    public function activate() {}
    public function deactivate() {}
}
?>
