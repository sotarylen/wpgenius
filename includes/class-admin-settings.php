<?php
if (!defined('ABSPATH')) {
    exit;
}

class W2P_Admin_Settings {
    protected $loader;

    public function __construct($loader) {
        $this->loader = $loader;
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_post_word2posts_toggle_module', array($this, 'handle_toggle'));
        add_action('admin_post_word2posts_save_module_settings', array($this, 'handle_save_module_settings'));
    }

    public function register_menu() {
        add_submenu_page(
            'tools.php',
            __('WP Genius Settings', 'wp-genius'),
            __('WP Genius Modules', 'wp-genius'),
            'manage_options',
            'wp-genius-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No permission', 'wp-genius'));
        }

        $modules = $this->loader->get_available_modules();
        $enabled = get_option('word2posts_modules', array());

        settings_errors('word_to_posts');
        ?>
        <div class="w2p-wrap">
            <h1><?php _e('WP Genius — Module Settings', 'wp-genius'); ?></h1>
            
            <div class="w2p-settings-tabs-layout">
                <!-- 标签页导航 -->
                <div class="w2p-settings-tabs-nav">
                    <ul class="w2p-tab-nav-list">
                        <li class="w2p-tab-nav-item active">
                            <a href="#w2p-tab-module-management"
                               class="w2p-tab-nav-link active"
                               data-module="module-management">
                                <?php _e('Module Management', 'wp-genius'); ?>
                            </a>
                        </li>
                        <?php
                        // 生成启用的模块设置标签
                        $enabled_modules_with_settings = array();
                        
                        foreach ($modules as $id => $module) {
                            $is = !empty($enabled[$id]);
                            $settings_path = plugin_dir_path(__FILE__) . 'modules/' . $id . '/settings.php';
                            if ($is && file_exists($settings_path)) {
                                $enabled_modules_with_settings[$id] = array(
                                    'module' => $module,
                                    'name' => method_exists($module, 'name') ? call_user_func(array($module, 'name')) : $id,
                                    'description' => method_exists($module, 'description') ? call_user_func(array($module, 'description')) : ''
                                );
                            }
                        }
                        
                        foreach ($enabled_modules_with_settings as $module_id => $module_info): ?>
                            <li class="w2p-tab-nav-item">
                                <a href="#w2p-tab-<?php echo esc_attr($module_id); ?>"
                                   class="w2p-tab-nav-link"
                                   data-module="<?php echo esc_attr($module_id); ?>">
                                    <?php echo esc_html($module_info['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- 标签页内容区域 -->
                <div class="w2p-settings-tabs-content">
                    <!-- 模组管理标签页 -->
                    <div id="w2p-tab-module-management"
                         class="w2p-tab-content active"
                         data-module="module-management">
                        <div class="w2p-tab-content-header">
                            <h3><?php _e('Module Management', 'wp-genius'); ?></h3>
                            <p class="w2p-tab-content-description"><?php _e('Enable or disable WP Genius modules and manage their basic preferences.', 'wp-genius'); ?></p>
                        </div>
                        <div class="w2p-tab-content-body">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="w2p-modules-form">
                                <?php wp_nonce_field('word2posts_toggle_modules', 'word2posts_toggle_nonce'); ?>
                                <input type="hidden" name="action" value="word2posts_toggle_module" />
                                
                                <div class="w2p-modules-list">
                                    <?php foreach ($modules as $id => $module):
                                        $is = !empty($enabled[$id]);
                                        $name = method_exists($module, 'name') ? call_user_func(array($module, 'name')) : $id;
                                        $desc = method_exists($module, 'description') ? call_user_func(array($module, 'description')) : '';
                                        $has_settings = file_exists(plugin_dir_path(__FILE__) . 'modules/' . $id . '/settings.php');
                                    ?>
                                        <div class="w2p-module-card <?php echo $is ? 'enabled' : 'disabled'; ?>">
                                            <div class="w2p-module-card-content">
                                                <div class="w2p-module-info">
                                                    <div class="w2p-module-title-row">
                                                        <h3><?php echo esc_html($name); ?></h3>
                                                        <div class="w2p-module-status-badge">
                                                            <span class="w2p-badge <?php echo $is ? 'active' : 'inactive'; ?>">
                                                                <?php echo $is ? __('Active', 'wp-genius') : __('Disabled', 'wp-genius'); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <p class="description"><?php echo esc_html($desc); ?></p>
                                                </div>
                                                
                                                <div class="w2p-module-footer">
                                                    <div class="w2p-module-actions">
                                                        <?php if ($has_settings && $is): ?>
                                                            <a href="#w2p-tab-<?php echo esc_attr($id); ?>"
                                                               class="w2p-settings-btn w2p-settings-link"
                                                               data-module="<?php echo esc_attr($id); ?>">
                                                                <span class="dashicons dashicons-admin-generic"></span>
                                                                <?php _e('Configure', 'wp-genius'); ?>
                                                            </a>
                                                        <?php elseif ($has_settings && !$is): ?>
                                                            <span class="w2p-settings-disabled-text">
                                                                <?php _e('Enable to configure', 'wp-genius'); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="w2p-module-toggle">
                                                        <label class="w2p-switch">
                                                            <input type="checkbox" name="modules[<?php echo esc_attr($id); ?>]" value="1" <?php checked($is, true); ?> />
                                                            <span class="w2p-slider"></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="w2p-form-actions">
                                    <?php submit_button(__('Save Module Preferences', 'wp-genius'), 'primary', 'submit', false, array('id' => 'w2p-save-modules')); ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php foreach ($enabled_modules_with_settings as $module_id => $module_info): ?>
                        <div id="w2p-tab-<?php echo esc_attr($module_id); ?>"
                             class="w2p-tab-content"
                             data-module="<?php echo esc_attr($module_id); ?>">
                            <div class="w2p-tab-content-header">
                                <h3><?php echo esc_html($module_info['name']); ?></h3>
                                <p class="w2p-tab-content-description"><?php echo esc_html($module_info['description']); ?></p>
                            </div>
                            <div class="w2p-tab-content-body">
                                <?php
                                $settings_path = plugin_dir_path(__FILE__) . 'modules/' . $module_id . '/settings.php';
                                if (file_exists($settings_path)) {
                                    include $settings_path;
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 内联JavaScript - 确保加载 -->
        <script>
        (function($){
            $(document).ready(function(){
                
                // 标签页切换逻辑
                $(document).on('click', '.w2p-tab-nav-link, .w2p-settings-link', function(e){
                    e.preventDefault();
                    // console.log('Tab clicked:', $(this).data('module'));
                    
                    var $link = $(this);
                    var targetId = $link.attr('href');
                    var targetModule = $link.data('module');
                    var $targetContent = $(targetId);
                    
                    // 移除所有active状态
                    $('.w2p-tab-nav-item').removeClass('active');
                    $('.w2p-tab-nav-link').removeClass('active');
                    $('.w2p-tab-content').removeClass('active');
                    
                    // 查找对应的导航标签
                    var $navLink;
                    if ($link.hasClass('w2p-settings-link')) {
                        // 如果是设置链接，找到对应的导航标签
                        $navLink = $('.w2p-tab-nav-link[data-module="' + targetModule + '"]');
                    } else {
                        // 如果是导航链接，直接使用
                        $navLink = $link;
                    }
                    
                    // 添加active状态
                    $navLink.closest('.w2p-tab-nav-item').addClass('active');
                    $navLink.addClass('active');
                    $targetContent.addClass('active');
                    
                    // 滚动到标签页顶部
                    $('html, body').animate({
                        scrollTop: $('.w2p-settings-tabs-nav').offset().top - 50
                    }, 300);
                    
                    // console.log('Tab switched to:', targetId);
                });
                
                // 保存时显示成功消息
                $('#w2p-save-modules').on('click', function(){
                    // console.log('Module preferences saved');
                    // WordPress会自动处理表单提交和重定向
                });
                
                // console.log('=== W2P UNIFIED TABS JS END ===');
            });
        })(jQuery);
        </script>
        <?php
    }

    // 处理模块设置保存
    public function handle_save_module_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No permission', 'wp-genius'));
        }

        // 验证 nonce - 检查可能的字段名以避免ID冲突
        $nonce_valid = false;
        $possible_nonce_fields = [
            'word2posts_module_nonce',
            'w2p_media_turbo_nonce',
            'w2p_ai_assistant_nonce',
            'w2p_auto_publish_nonce',
            'w2p_clipboard_upload_nonce',
            'w2p_seo_linker_nonce',
            'w2p_accelerate_nonce',
            'w2p_upload_rename_nonce',
            'w2p_smtp_mailer_nonce',
            'w2p_smart_aui_nonce',
            'w2p_image_watermark_nonce',
            'w2p_post_duplicator_nonce',
            'w2p_frontend_enhancement_nonce',
        ];
        
        foreach ($possible_nonce_fields as $nonce_field) {
            if (isset($_POST[$nonce_field]) && wp_verify_nonce($_POST[$nonce_field], 'word2posts_save_module_settings')) {
                $nonce_valid = true;
                break;
            }
        }
        
        if (!$nonce_valid) {
            wp_die(__('Nonce verification failed', 'wp-genius'));
        }

        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        if (empty($module_id)) {
            wp_die(__('Invalid module', 'wp-genius'));
        }

        // 根据模块处理已知的模块设置字段（示例：upload-rename）
        if ($module_id === 'upload-rename') {
            $pattern = isset($_POST['w2p_upload_rename_pattern']) ? sanitize_text_field($_POST['w2p_upload_rename_pattern']) : '{timestamp}_{sanitized}';
            update_option('w2p_upload_rename_pattern', $pattern);
        }

        // SMTP 邮件配置模块
        if ($module_id === 'smtp-mailer') {
            $settings = isset($_POST['w2p_smtp_settings']) ? (array) $_POST['w2p_smtp_settings'] : [];
            
            // 清理和验证设置
            $clean_settings = [
                'smtp_host'       => sanitize_text_field($settings['smtp_host'] ?? 'smtp.gmail.com'),
                'smtp_port'       => absint($settings['smtp_port'] ?? 465),
                'smtp_secure'     => sanitize_text_field($settings['smtp_secure'] ?? 'ssl'),
                'smtp_auth'       => !empty($settings['smtp_auth']),
                'smtp_username'   => sanitize_text_field($settings['smtp_username'] ?? ''),
                'smtp_password'   => $settings['smtp_password'] ?? '', // 不清理密码
                'smtp_from_email' => sanitize_email($settings['smtp_from_email'] ?? get_option('admin_email')),
                'smtp_from_name'  => sanitize_text_field($settings['smtp_from_name'] ?? get_option('blogname')),
            ];
            
            update_option('w2p_smtp_settings', $clean_settings);
        }


        // Accelerate Module
        if ($module_id === 'accelerate') {
            $settings = isset($_POST['w2p_accelerate_settings']) ? (array) $_POST['w2p_accelerate_settings'] : [];
            
            $clean_settings = [
                // Cleanup
                'remove_admin_bar_wp_logo'         => !empty($settings['remove_admin_bar_wp_logo']),
                'remove_admin_bar_about'           => !empty($settings['remove_admin_bar_about']),
                'remove_admin_bar_comments'        => !empty($settings['remove_admin_bar_comments']),
                'remove_admin_bar_new_content'     => !empty($settings['remove_admin_bar_new_content']),
                'remove_admin_bar_search'          => !empty($settings['remove_admin_bar_search']),
                'remove_admin_bar_updates'         => !empty($settings['remove_admin_bar_updates']),
                'remove_admin_bar_appearance'      => !empty($settings['remove_admin_bar_appearance']),
                'remove_admin_bar_wporg'           => !empty($settings['remove_admin_bar_wporg']),
                'remove_admin_bar_documentation'   => !empty($settings['remove_admin_bar_documentation']),
                'remove_admin_bar_support_forums'  => !empty($settings['remove_admin_bar_support_forums']),
                'remove_admin_bar_feedback'        => !empty($settings['remove_admin_bar_feedback']),
                'remove_admin_bar_view_site'       => !empty($settings['remove_admin_bar_view_site']),
                'remove_dashboard_activity'        => !empty($settings['remove_dashboard_activity']),
                'remove_dashboard_primary'         => !empty($settings['remove_dashboard_primary']),
                'remove_dashboard_secondary'       => !empty($settings['remove_dashboard_secondary']),
                'remove_dashboard_site_health'     => !empty($settings['remove_dashboard_site_health']),
                'remove_dashboard_right_now'       => !empty($settings['remove_dashboard_right_now']),
                'remove_dashboard_quick_draft'     => !empty($settings['remove_dashboard_quick_draft']),
                'disable_months_dropdown'          => !empty($settings['disable_months_dropdown']),
                
                // Update Behavior
                'disable_auto_update_plugin' => !empty($settings['disable_auto_update_plugin']),
                'disable_auto_update_theme'  => !empty($settings['disable_auto_update_theme']),
                'remove_wp_update_plugins'   => !empty($settings['remove_wp_update_plugins']),
                'remove_wp_update_themes'    => !empty($settings['remove_wp_update_themes']),
                'remove_maybe_update_core'   => !empty($settings['remove_maybe_update_core']),
                'remove_maybe_update_plugins'=> !empty($settings['remove_maybe_update_plugins']),
                'remove_maybe_update_themes' => !empty($settings['remove_maybe_update_themes']),
                'block_external_http'      => !empty($settings['block_external_http']),
                'hide_plugin_notices'      => !empty($settings['hide_plugin_notices']),
                'block_acf_updates'        => !empty($settings['block_acf_updates']),
                
                // General Interface
                'enable_local_avatar'      => !empty($settings['enable_local_avatar']),
                'enable_upload_rename'     => !empty($settings['enable_upload_rename']),
                'upload_rename_pattern'    => isset($settings['upload_rename_pattern']) ? sanitize_text_field($settings['upload_rename_pattern']) : '{timestamp}_{sanitized}',
            ];
            
            update_option('w2p_accelerate_settings', $clean_settings);
        }

        // Media Turbo Module
        if ($module_id === 'media-turbo') {
            $settings = isset($_POST['w2p_media_turbo_settings']) ? (array) $_POST['w2p_media_turbo_settings'] : [];
            
            $clean_settings = [
                'webp_enabled'  => !empty($settings['webp_enabled']),
                'webp_quality'  => isset($settings['webp_quality']) ? absint($settings['webp_quality']) : 80,
                'keep_original' => !empty($settings['keep_original']),
                'min_file_size' => isset($settings['min_file_size']) ? absint($settings['min_file_size']) : 1024,
                'scan_mode'     => isset($settings['scan_mode']) && in_array($settings['scan_mode'], ['media', 'posts']) ? $settings['scan_mode'] : 'media',
                'posts_limit'   => isset($settings['posts_limit']) ? absint($settings['posts_limit']) : 10,
                'scan_limit'    => isset($settings['scan_limit']) ? absint($settings['scan_limit']) : 100,
                'batch_size'    => isset($settings['batch_size']) ? absint($settings['batch_size']) : 10,
            ];
            
            update_option('w2p_media_turbo_settings', $clean_settings);
        }

        // Auto Upload Images 模块
        if ($module_id === 'auto-upload-images-module') {
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
            update_option('w2p_auto_upload_setting', $options);
        }

        // Smart Auto Upload Images 模块 - 包含所有设置
        if ($module_id === 'smart-auto-upload-images') {
            $settings = isset($_POST['smart_aui_settings']) ? (array) $_POST['smart_aui_settings'] : [];
            
            // 获取现有的核心设置
            $core_settings = get_option('smart_aui_settings', []);
            
            // 更新核心设置
            if (isset($settings['base_url'])) {
                $core_settings['base_url'] = esc_url_raw($settings['base_url']);
            }
            if (isset($settings['image_name_pattern'])) {
                $core_settings['image_name_pattern'] = sanitize_text_field($settings['image_name_pattern']);
            }
            if (isset($settings['alt_text_pattern'])) {
                $core_settings['alt_text_pattern'] = sanitize_text_field($settings['alt_text_pattern']);
            }
            if (isset($settings['min_width'])) {
                $core_settings['min_width'] = absint($settings['min_width']);
            }
            if (isset($settings['min_height'])) {
                $core_settings['min_height'] = absint($settings['min_height']);
            }
            if (isset($settings['exclude_domains'])) {
                $core_settings['exclude_domains'] = sanitize_textarea_field($settings['exclude_domains']);
            }
            if (isset($settings['exclude_post_types']) && is_array($settings['exclude_post_types'])) {
                $core_settings['exclude_post_types'] = array_map('sanitize_text_field', $settings['exclude_post_types']);
            }
            
            // 更新 WP Genius 增强功能设置
            $core_settings['auto_set_featured_image'] = !empty($settings['auto_set_featured_image']);
            $core_settings['show_progress_ui'] = !empty($settings['show_progress_ui']);
            $core_settings['process_images_on_rest_api'] = !empty($settings['process_images_on_rest_api']);
            
            // 更新并发线程数和重试次数
            if (isset($settings['concurrent_threads'])) {
                $core_settings['concurrent_threads'] = max(1, min(16, absint($settings['concurrent_threads'])));
            }
            if (isset($settings['max_retries'])) {
                $core_settings['max_retries'] = min(10, absint($settings['max_retries']));
            }
            
            // 保存合并后的设置
            update_option('smart_aui_settings', $core_settings);
        }

        // Smart AUI Module (New)
        if ($module_id === 'smart-aui') {
            $settings = isset($_POST['smart_aui_settings']) ? (array) $_POST['smart_aui_settings'] : [];
            
            // Handle checkboxes
            $settings['show_progress_ui'] = isset($settings['show_progress_ui']) ? true : false;
            $settings['auto_set_featured'] = isset($settings['auto_set_featured']) ? true : false;
            $settings['enable_rest_api'] = isset($settings['enable_rest_api']) ? true : false;
            
            $clean_settings = [
                'base_url'           => isset($settings['base_url']) ? esc_url_raw($settings['base_url']) : site_url(),
                'rename_pattern'     => isset($settings['rename_pattern']) ? sanitize_text_field($settings['rename_pattern']) : '{original}',
                'alt_pattern'        => isset($settings['alt_pattern']) ? sanitize_text_field($settings['alt_pattern']) : '{title}',
                'max_image_size'     => isset($settings['max_image_size']) ? max(100, min(10000, absint($settings['max_image_size']))) : 2048,
                'min_image_size'     => isset($settings['min_image_size']) ? max(10, min(1000, absint($settings['min_image_size']))) : 100,
                'max_file_size'      => isset($settings['max_file_size']) ? max(1, min(50, absint($settings['max_file_size']))) : 5,
                'concurrent_threads' => isset($settings['concurrent_threads']) ? max(1, min(5, absint($settings['concurrent_threads']))) : 2,
                'max_retries'        => isset($settings['max_retries']) ? max(0, min(5, absint($settings['max_retries']))) : 3,
                'download_timeout'   => isset($settings['download_timeout']) ? max(5, min(60, absint($settings['download_timeout']))) : 15,
                'show_progress_ui'   => (bool) $settings['show_progress_ui'],
                'auto_set_featured'  => (bool) $settings['auto_set_featured'],
                'enable_rest_api'    => (bool) $settings['enable_rest_api'],
                'allowed_post_types' => ['post', 'page'],
                'excluded_domains'   => [],
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            ];
            
            update_option('smart_aui_settings', $clean_settings);
        }

        // AI Assistant Module
        if ($module_id === 'ai-assistant') {
            $settings = isset($_POST['w2p_ai_assistant_settings']) ? (array) $_POST['w2p_ai_assistant_settings'] : [];
            
            $clean_settings = [
                'api_key'      => sanitize_text_field($settings['api_key'] ?? ''),
                'api_base'     => esc_url_raw($settings['api_base'] ?? 'https://api.openai.com/v1/chat/completions'),
                'model'        => sanitize_text_field($settings['model'] ?? 'gpt-3.5-turbo'),
                'auto_excerpt' => !empty($settings['auto_excerpt']),
                'auto_tags'    => !empty($settings['auto_tags']),
            ];
            
            update_option('w2p_ai_assistant_settings', $clean_settings);
        }

        // SEO & Internal Linker Module
        if ($module_id === 'seo-linker') {
            $settings = isset($_POST['w2p_seo_linker_settings']) ? (array) $_POST['w2p_seo_linker_settings'] : [];
            
            $clean_keywords = [];
            if ( ! empty( $settings['keywords'] ) ) {
                foreach ( $settings['keywords'] as $item ) {
                    if ( empty( $item['keyword'] ) || empty( $item['url'] ) ) continue;
                    $clean_keywords[] = [
                        'keyword' => sanitize_text_field( $item['keyword'] ),
                        'url'     => esc_url_raw( $item['url'] ),
                        'title'   => sanitize_text_field( $item['title'] ?? '' )
                    ];
                }
            }

            $clean_settings = [
                'linker_enabled' => !empty($settings['linker_enabled']),
                'keywords'       => $clean_keywords,
                'toc_enabled'    => !empty($settings['toc_enabled']),
                'toc_threshold'  => isset($settings['toc_threshold']) ? absint($settings['toc_threshold']) : 3,
                'toc_depth'      => isset($settings['toc_depth']) ? absint($settings['toc_depth']) : 3,
                'toc_auto_insert'=> !empty($settings['toc_auto_insert']),
            ];
            
            update_option('w2p_seo_linker_settings', $clean_settings);
        }

        // Auto Publish 模块
        if ($module_id === 'auto-publish') {
            $settings = isset($_POST['w2p_auto_publish_settings']) ? (array) $_POST['w2p_auto_publish_settings'] : [];
            
            $clean_settings = [
                'cron_enabled' => !empty($settings['cron_enabled']),
                'interval'     => isset($settings['interval']) ? sanitize_text_field($settings['interval']) : 'hourly',
                'batch_size'   => isset($settings['batch_size']) ? absint($settings['batch_size']) : 5,
            ];
            
            update_option('w2p_auto_publish_settings', $clean_settings);
            
            // 如果启用了定时发布，重新调度任务
            $module_loader = $this->loader;
            $modules = $module_loader->get_available_modules();
            if (isset($modules['auto-publish'])) {
                $modules['auto-publish']->enable();
            }
        }

        // Post Duplicator 模块
        if ($module_id === 'post-duplicator') {
            $settings = isset($_POST['w2p_post_duplicator_settings']) ? (array) $_POST['w2p_post_duplicator_settings'] : [];
            
            $clean_settings = [
                'mode'                                   => sanitize_text_field($settings['mode'] ?? 'advanced'),
                'single_after_duplication_action'        => sanitize_text_field($settings['single_after_duplication_action'] ?? 'notice'),
                'list_single_after_duplication_action'   => sanitize_text_field($settings['list_single_after_duplication_action'] ?? 'notice'),
                'list_multiple_after_duplication_action' => sanitize_text_field($settings['list_multiple_after_duplication_action'] ?? 'notice'),
                'status'                                 => sanitize_text_field($settings['status'] ?? 'draft'),
                'type'                                   => sanitize_text_field($settings['type'] ?? 'same'),
                'post_author'                            => sanitize_text_field($settings['post_author'] ?? 'current_user'),
                'timestamp'                              => sanitize_text_field($settings['timestamp'] ?? 'current'),
                'title'                                  => sanitize_text_field($settings['title'] ?? ''),
                'slug'                                   => sanitize_text_field($settings['slug'] ?? ''),
                'time_offset'                            => !empty($settings['time_offset']),
                'time_offset_days'                       => intval($settings['time_offset_days'] ?? 0),
                'time_offset_hours'                      => intval($settings['time_offset_hours'] ?? 0),
                'time_offset_minutes'                    => intval($settings['time_offset_minutes'] ?? 0),
                'time_offset_seconds'                    => intval($settings['time_offset_seconds'] ?? 0),
                'time_offset_direction'                  => sanitize_text_field($settings['time_offset_direction'] ?? 'newer'),
                'duplicate_other_draft'                  => sanitize_text_field($settings['duplicate_other_draft'] ?? 'enabled'),
                'duplicate_other_pending'                => sanitize_text_field($settings['duplicate_other_pending'] ?? 'enabled'),
                'duplicate_other_private'                => sanitize_text_field($settings['duplicate_other_private'] ?? 'enabled'),
                'duplicate_other_password'               => sanitize_text_field($settings['duplicate_other_password'] ?? 'enabled'),
                'duplicate_other_future'                 => sanitize_text_field($settings['duplicate_other_future'] ?? 'enabled'),
            ];
            
            update_option('w2p_post_duplicator_settings', $clean_settings);
        }

        // Frontend Enhancement Module
        if ($module_id === 'frontend-enhancement') {
            $settings = isset($_POST['w2p_frontend_enhancement_settings']) ? (array) $_POST['w2p_frontend_enhancement_settings'] : [];
            
            $clean_settings = [
                // Lightbox settings
                'lightbox_enabled'              => !empty($settings['lightbox_enabled']),
                'lightbox_animation'            => in_array($settings['lightbox_animation'] ?? '', ['fade', 'slide', 'zoom']) ? $settings['lightbox_animation'] : 'fade',
                'lightbox_close_on_backdrop'    => !empty($settings['lightbox_close_on_backdrop']),
                'lightbox_keyboard_nav'         => !empty($settings['lightbox_keyboard_nav']),
                'lightbox_show_counter'         => !empty($settings['lightbox_show_counter']),
                'lightbox_allow_set_featured'   => !empty($settings['lightbox_allow_set_featured']),
                'lightbox_autoplay_enabled'     => !empty($settings['lightbox_autoplay_enabled']),
                'lightbox_autoplay_interval'    => max(2, min(5, absint($settings['lightbox_autoplay_interval'] ?? 3))),
                'lightbox_zoom_enabled'         => !empty($settings['lightbox_zoom_enabled']),
                'lightbox_zoom_step'            => floatval($settings['lightbox_zoom_step'] ?? 0.2),
                'lightbox_max_zoom'             => max(1, min(5, floatval($settings['lightbox_max_zoom'] ?? 3))),
                
                // Video optimization settings
                'video_enabled'                 => !empty($settings['video_enabled']),
                'video_extract_poster'          => !empty($settings['video_extract_poster']),
                'video_exclusive_playback'      => !empty($settings['video_exclusive_playback']),
                'video_lightbox_button'         => !empty($settings['video_lightbox_button']),
                'video_lightbox_on_click'       => !empty($settings['video_lightbox_on_click']),
                'video_autoplay_prevention'     => !empty($settings['video_autoplay_prevention']),
                'video_supported_formats'       => isset($settings['video_supported_formats']) ? sanitize_text_field($settings['video_supported_formats']) : 'mp4,webm,ogg,ogv,mkv,mov,avi,m4v,3gp,flv',
                
                // Audio player settings (reserved)
                'audio_enabled'                 => !empty($settings['audio_enabled']),
                'audio_custom_player'           => !empty($settings['audio_custom_player']),

                // Reader settings
                'reader_enabled'                => !empty($settings['reader_enabled']),
                'reader_font_size'              => isset($settings['reader_font_size']) ? absint($settings['reader_font_size']) : 18,
                'reader_font_family'            => isset($settings['reader_font_family']) ? sanitize_text_field($settings['reader_font_family']) : 'sans',
                'reader_theme'                  => isset($settings['reader_theme']) ? sanitize_text_field($settings['reader_theme']) : 'light',
            ];
            
            update_option('w2p_frontend_enhancement_settings', $clean_settings);
        }

        wp_redirect(admin_url('tools.php?page=wp-genius-settings&updated=1'));
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

    public function handle_toggle() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No permission', 'wp-genius'));
        }

        if (!isset($_POST['word2posts_toggle_nonce']) || !wp_verify_nonce($_POST['word2posts_toggle_nonce'], 'word2posts_toggle_modules')) {
            wp_die(__('Nonce verification failed', 'wp-genius'));
        }

        $posted = isset($_POST['modules']) ? (array) $_POST['modules'] : array();
        $modules = $this->loader->get_available_modules();
        $new = array();
        foreach ($modules as $id => $m) {
            $new[$id] = isset($posted[$id]) ? true : false;
        }

        update_option('word2posts_modules', $new);

        wp_redirect(admin_url('tools.php?page=wp-genius-settings&updated=1'));
        exit;
    }
}

?>
