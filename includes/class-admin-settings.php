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
        <div class="wrap">
            <h1><?php _e('WP Genius — Module Settings', 'wp-genius'); ?></h1>
            <p><?php _e('Manage your WP Genius modules below. Enable or disable features as needed.', 'wp-genius'); ?></p>
            
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
                                            <div class="w2p-module-card-header">
                                                <div class="w2p-module-info">
                                                    <h3><?php echo esc_html($name); ?></h3>
                                                    <p class="description"><?php echo esc_html($desc); ?></p>
                                                    <?php if ($has_settings && $is): ?>
                                                        <p class="w2p-module-has-settings">
                                                            <a href="#w2p-tab-<?php echo esc_attr($id); ?>"
                                                               class="w2p-settings-link"
                                                               data-module="<?php echo esc_attr($id); ?>"
                                                               title="<?php echo esc_attr(sprintf(__('Go to %s settings', 'wp-genius'), $name)); ?>">
                                                                <?php _e('Configure Settings', 'wp-genius'); ?>
                                                            </a>
                                                        </p>
                                                    <?php elseif ($has_settings && !$is): ?>
                                                        <p class="w2p-module-has-settings-disabled">
                                                            <span class="w2p-settings-disabled"
                                                                  title="<?php echo esc_attr(sprintf(__('%s is disabled. Enable the module to access settings.', 'wp-genius'), $name)); ?>">
                                                                <?php _e('Module Disabled', 'wp-genius'); ?>
                                                            </span>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="w2p-module-toggle-switch">
                                                    <label class="switch">
                                                        <input type="checkbox" name="modules[<?php echo esc_attr($id); ?>]" value="1" <?php checked($is, true); ?> />
                                                        <span class="slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php submit_button(__('Save Module Preferences', 'wp-genius'), 'primary', 'submit', true, array('id' => 'w2p-save-modules')); ?>
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
                console.log('=== W2P UNIFIED TABS JS START ===');
                
                // 检查元素
                console.log('Tab nav items found:', $('.w2p-tab-nav-item').length);
                console.log('Tab content panels found:', $('.w2p-tab-content').length);
                
                // 标签页切换逻辑
                $(document).on('click', '.w2p-tab-nav-link, .w2p-settings-link', function(e){
                    e.preventDefault();
                    console.log('Tab clicked:', $(this).data('module'));
                    
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
                    
                    console.log('Tab switched to:', targetId);
                });
                
                // 保存时显示成功消息
                $('#w2p-save-modules').on('click', function(){
                    console.log('Module preferences saved');
                    // WordPress会自动处理表单提交和重定向
                });
                
                console.log('=== W2P UNIFIED TABS JS END ===');
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

        if (!isset($_POST['word2posts_module_nonce']) || !wp_verify_nonce($_POST['word2posts_module_nonce'], 'word2posts_save_module_settings')) {
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

        // 清理 WordPress 模块
        if ($module_id === 'cleanup-wordpress') {
            $settings = isset($_POST['w2p_cleanup_settings']) ? (array) $_POST['w2p_cleanup_settings'] : [];
            
            // 清理和验证所有复选框选项
            $clean_settings = [
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
                'remove_dashboard_activity'        => !empty($settings['remove_dashboard_node_activity']),
                'remove_dashboard_primary'         => !empty($settings['remove_dashboard_primary']),
                'remove_dashboard_secondary'       => !empty($settings['remove_dashboard_secondary']),
                'remove_dashboard_site_health'     => !empty($settings['remove_dashboard_site_health']),
                'remove_dashboard_right_now'       => !empty($settings['remove_dashboard_right_now']),
            ];
            
            update_option('w2p_cleanup_settings', $clean_settings);
        }

        // 更新行为模块
        if ($module_id === 'update-behavior') {
            $settings = isset($_POST['w2p_update_behavior_settings']) ? (array) $_POST['w2p_update_behavior_settings'] : [];

            $clean_settings = [
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
            ];

            update_option('w2p_update_behavior_settings', $clean_settings);
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
