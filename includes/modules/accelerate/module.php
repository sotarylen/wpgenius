<?php
/**
 * Accelerate Module
 *
 * Merges functionality from Cleanup WordPress and Update Behavior modules.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccelerateModule extends W2P_Abstract_Module {
    
    public static function id() {
        return 'accelerate';
    }

    public static function name() {
        return __( 'Accelerate', 'wp-genius' );
    }

    public static function description() {
        return __( 'Optimize WordPress performance by cleaning up admin interface and controlling update behaviors.', 'wp-genius' );
    }

    public function __construct() {
        $this->migrate_settings();
    }

    private function migrate_settings() {
        $transient_key = 'w2p_accelerate_migration_completed';
        if ( get_transient( $transient_key ) ) {
            return;
        }

        // 1. Migrate Module Activation Status
        $modules_enabled = get_option( 'word2posts_modules', [] );
        $old_modules = ['cleanup-wordpress', 'update-behavior', 'avatar-manager', 'upload-rename'];
        $should_enable = false;

        foreach ($old_modules as $old_id) {
            if ( !empty($modules_enabled[$old_id]) ) {
                $should_enable = true;
                unset($modules_enabled[$old_id]); // Clean up old entry
            }
        }

        if ( $should_enable && empty($modules_enabled[self::id()]) ) {
            $modules_enabled[self::id()] = true;
            update_option( 'word2posts_modules', $modules_enabled );
        }

        // 2. Migrate Options
        $new_settings_key = 'w2p_accelerate_settings';
        $current_settings = get_option( $new_settings_key, [] );
        
        if ( empty($current_settings) ) {
            $cleanup_settings = get_option('w2p_cleanup_settings', []);
            $update_settings = get_option('w2p_update_behavior_settings', []);
            
            // Merge defaults just in case, but usually we just merge what we have
            $merged_settings = array_merge( $cleanup_settings, $update_settings );
            
            if ( !empty($merged_settings) ) {
                update_option( $new_settings_key, $merged_settings );
            }
        }
        
        // 3. Migrate Avatar Manager Settings
        $avatar_was_enabled = !empty($modules_enabled['avatar-manager']);
        if ($avatar_was_enabled && empty($current_settings['enable_local_avatar'])) {
            $current_settings['enable_local_avatar'] = true;
            update_option($new_settings_key, $current_settings);
        }
        
        // 4. Migrate Upload Rename Settings
        $rename_was_enabled = !empty($modules_enabled['upload-rename']);
        $old_pattern = get_option('w2p_upload_rename_pattern', '');
        if ($rename_was_enabled && empty($current_settings['enable_upload_rename'])) {
            $current_settings['enable_upload_rename'] = true;
            if ($old_pattern) {
                $current_settings['upload_rename_pattern'] = $old_pattern;
            }
            update_option($new_settings_key, $current_settings);
        }
        
        set_transient( $transient_key, true, MONTH_IN_SECONDS );
    }

    public function init() {
        $this->register_settings();

        // Cleanup Functionality Hooks
        add_action( 'wp_before_admin_bar_render', [ $this, 'clean_admin_bar' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'clean_dashboard_widgets' ], 999 );
        
        // Date Dropdown Optimization
        add_filter( 'disable_months_dropdown', [ $this, 'should_disable_months_dropdown' ], 10, 2 );
        add_filter( 'media_library_months_with_files', [ $this, 'disable_media_months' ] );
        add_filter( 'query', [ $this, 'intercept_date_query' ] );

        // Update Behavior Hooks
        add_action( 'init', array( $this, 'apply_update_behavior' ), 1 );

        // Local Avatar Management Hooks
        $this->init_local_avatar();

        // Upload Rename Hooks
        $this->init_upload_rename();
    }

    public function register_settings() {
        register_setting( 'w2p_accelerate_settings', 'w2p_accelerate_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => [
                'remove_admin_bar_wp_logo'         => true,
                'remove_admin_bar_about'           => true,
                'remove_admin_bar_comments'        => true,
                'remove_admin_bar_new_content'     => true,
                'remove_admin_bar_search'          => true,
                'remove_admin_bar_updates'         => true,
                'remove_admin_bar_appearance'      => true,
                'remove_admin_bar_wporg'           => true,
                'remove_admin_bar_documentation'   => true,
                'remove_admin_bar_support_forums'  => true,
                'remove_admin_bar_feedback'        => true,
                'remove_admin_bar_view_site'       => true,
                'remove_dashboard_activity'        => true,
                'remove_dashboard_primary'         => false,
                'remove_dashboard_secondary'       => false,
                'remove_dashboard_site_health'     => false,
                'remove_dashboard_right_now'       => false,
                'remove_dashboard_quick_draft'     => true,
                'disable_months_dropdown'          => false,
                'disable_auto_update_plugin'       => true,
                'disable_auto_update_theme'        => true,
                'remove_wp_update_plugins'         => true,
                'remove_wp_update_themes'          => true,
                'remove_maybe_update_core'         => true,
                'remove_maybe_update_plugins'      => true,
                'remove_maybe_update_themes'       => true,
                'block_external_http'              => false,
                'hide_plugin_notices'              => false,
                'block_acf_updates'                => false,
                'enable_local_avatar'              => false,
                'enable_upload_rename'             => false,
                'upload_rename_pattern'            => '{timestamp}_{sanitized}',
            ]
        ] );
    }

    public function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) return [];
        
        $sanitized = [];
        
        // Handle string fields separately
        if ( isset( $input['upload_rename_pattern'] ) ) {
            $sanitized['upload_rename_pattern'] = sanitize_text_field( $input['upload_rename_pattern'] );
        }
        
        // Convert all other fields to boolean
        foreach ( $input as $key => $value ) {
            if ( $key === 'upload_rename_pattern' ) {
                continue; // Already handled
            }
            $sanitized[$key] = (bool) $value;
        }
        
        return $sanitized;
    }

    /**
     * Clean Admin Bar
     */
    public function clean_admin_bar() {
        if ( ! $this->is_module_enabled() ) {
            return;
        }

        global $wp_admin_bar;
        $settings = get_option( 'w2p_accelerate_settings', [] );

        $items_to_remove = [
            'remove_admin_bar_wp_logo'       => 'wp-logo',
            'remove_admin_bar_about'         => 'about',
            'remove_admin_bar_comments'      => 'comments',
            'remove_admin_bar_new_content'   => 'new-content',
            'remove_admin_bar_search'        => 'search',
            'remove_admin_bar_updates'       => 'updates',
            'remove_admin_bar_appearance'    => 'appearance',
            'remove_admin_bar_wporg'         => 'wporg',
            'remove_admin_bar_documentation' => 'documentation',
            'remove_admin_bar_support_forums' => 'support-forums',
            'remove_admin_bar_feedback'      => 'feedback',
            'remove_admin_bar_view_site'     => 'view-site',
        ];

        foreach ( $items_to_remove as $setting => $menu_item ) {
            if ( ! empty( $settings[ $setting ] ) ) {
                $wp_admin_bar->remove_menu( $menu_item );
            }
        }
    }

    /**
     * Clean Dashboard Widgets
     */
    public function clean_dashboard_widgets() {
        if ( ! $this->is_module_enabled() ) {
            return;
        }

        global $wp_meta_boxes;
        $settings = get_option( 'w2p_accelerate_settings', [] );

        $widgets_to_remove = [
            'remove_dashboard_primary'      => [ 'dashboard', 'side', 'core', 'dashboard_primary' ],
            'remove_dashboard_secondary'    => [ 'dashboard', 'side', 'core', 'dashboard_secondary' ],
            'remove_dashboard_site_health'  => [ 'dashboard', 'normal', 'core', 'dashboard_site_health' ],
            'remove_dashboard_right_now'    => [ 'dashboard', 'normal', 'core', 'dashboard_right_now' ],
            'remove_dashboard_quick_draft'  => [ 'dashboard', 'side', 'core', 'dashboard_quick_press' ],
            'remove_dashboard_activity'     => [ 'dashboard', 'normal', 'core', 'dashboard_activity' ],
        ];

        foreach ( $widgets_to_remove as $setting => $path ) {
            if ( ! empty( $settings[ $setting ] ) ) {
                if ( isset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] ) ) {
                    unset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] );
                }
            }
        }
    }

    /**
     * Should Disable Months Dropdown (Post List)
     * 
     * Returning true here prevents the SQL query entirely.
     */
    public function should_disable_months_dropdown( $disable, $post_type ) {
        if ( ! $this->is_module_enabled() ) {
            return $disable;
        }

        $settings = get_option( 'w2p_accelerate_settings', [] );
        if ( ! empty( $settings['disable_months_dropdown'] ) ) {
            return true;
        }

        return $disable;
    }

    /**
     * Disable Media Months UI
     */
    public function disable_media_months( $months ) {
        if ( ! $this->is_module_enabled() ) {
            return $months;
        }

        $settings = get_option( 'w2p_accelerate_settings', [] );
        if ( ! empty( $settings['disable_months_dropdown'] ) ) {
            return [];
        }

        return $months;
    }

    /**
     * Intercept and block date-based SELECT DISTINCT queries
     * 
     * This is a fallback for cases where there is no "disable" filter (like Grid Media Library).
     */
    public function intercept_date_query( $query ) {
        if ( ! is_admin() || ! $this->is_module_enabled() ) {
            return $query;
        }

        // Target the specific slow queries for years/months
        if ( strpos( $query, 'SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month' ) !== false ) {
            $settings = get_option( 'w2p_accelerate_settings', [] );
            if ( ! empty( $settings['disable_months_dropdown'] ) ) {
                // Return a valid query that result in 0 rows to satisfy the get_results call without hitting table indexes
                return "SELECT 1 FROM wp_posts WHERE 1=0";
            }
        }

        return $query;
    }

    /**
     * Apply Update Behaviors
     */
    public function apply_update_behavior() {
        if ( ! $this->is_module_enabled() ) {
            return;
        }

        $s = get_option( 'w2p_accelerate_settings', array() );

        if ( ! empty( $s['disable_auto_update_plugin'] ) ) {
            add_filter( 'auto_update_plugin', '__return_false' );
        }

        if ( ! empty( $s['disable_auto_update_theme'] ) ) {
            add_filter( 'auto_update_theme', '__return_false' );
        }

        if ( ! empty( $s['remove_wp_update_plugins'] ) ) {
            remove_action( 'wp_update_plugins', 'wp_update_plugins' );
        }

        if ( ! empty( $s['remove_wp_update_themes'] ) ) {
            remove_action( 'wp_update_themes', 'wp_update_themes' );
        }

        if ( ! empty( $s['remove_maybe_update_core'] ) ) {
            remove_action( 'admin_init', '_maybe_update_core' );
        }

        if ( ! empty( $s['remove_maybe_update_plugins'] ) ) {
            remove_action( 'admin_init', '_maybe_update_plugins' );
        }

        if ( ! empty( $s['remove_maybe_update_themes'] ) ) {
            remove_action( 'admin_init', '_maybe_update_themes' );
        }

        if ( ! empty( $s['block_external_http'] ) ) {
            if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
                define( 'WP_HTTP_BLOCK_EXTERNAL', true );
            }
        }

        if ( ! empty( $s['hide_plugin_notices'] ) ) {
            add_action('admin_head', array($this, 'hide_plugin_notices'));
        }

        if ( ! empty( $s['block_acf_updates'] ) ) {
            add_filter('http_request_args', array($this, 'block_acf_update_requests'), 10, 2);
        }
    }

    /**
     * Hide Plugin Notices
     *
     * @return void
     */
    public function hide_plugin_notices() {
        echo '<style>
            .otgs-is-not-registered, .otgs-notice, .update-message {
                display: none !important;
            }
        </style>';
    }

    /**
     * Block ACF Update Requests
     */
    public function block_acf_update_requests($r, $url) {
        $url_string = is_array($url) ? (isset($url['url']) ? $url['url'] : '') : $url;
        
        if (strpos($url_string, 'https://connect.advancedcustomfields.com/v2/plugins/update-check') !== false) {
            $r['blocked'] = true;
        }
        return $r;
    }

    private function is_module_enabled() {
        $modules = get_option( 'word2posts_modules', array() );
        return ! empty( $modules[ self::id() ] );
    }

    public function activate() {
        do_action( 'w2p_accelerate_activated' );
    }

    public function deactivate() {
        do_action( 'w2p_accelerate_deactivated' );
    }

    /**
     * ============================================
     * Local Avatar Management Integration
     * ============================================
     */

    protected static $original_titles = array();

    protected function init_local_avatar() {
        $settings = get_option( 'w2p_accelerate_settings', [] );
        if ( empty( $settings['enable_local_avatar'] ) ) {
            return;
        }

        // Hide WP default avatar section
        add_action('admin_head', array($this, 'hide_default_avatar_ui'));

        // Load media library scripts on profile pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_avatar_scripts'));

        // Show custom avatar field
        add_action('show_user_profile', array($this, 'render_avatar_field'));
        add_action('edit_user_profile', array($this, 'render_avatar_field'));

        // Print inline JS for upload/remove functionality
        add_action('admin_print_footer_scripts', array($this, 'print_avatar_js'));

        // Save avatar metadata
        add_action('personal_options_update', array($this, 'save_avatar'));
        add_action('edit_user_profile_update', array($this, 'save_avatar'));

        // Override get_avatar to use local avatars
        add_filter('get_avatar', array($this, 'get_local_avatar'), 10, 5);
    }

    public function hide_default_avatar_ui() {
        echo '<style>
            .user-profile-picture { display:none; }
        </style>';
    }

    public function enqueue_avatar_scripts() {
        $screen = get_current_screen();
        if (!$screen || ($screen->base !== 'profile' && $screen->base !== 'user-edit')) {
            return;
        }

        wp_enqueue_media();
        wp_add_inline_style('wp-admin', '
            /* Local avatar preview styling */
            #st-avatar-preview img {
                width: 96px !important;
                height: 96px !important;
                border-radius: 50%;
                object-fit: cover;
            }
        ');
    }

    public function render_avatar_field($user) {
        $avatar_id = get_user_meta($user->ID, 'st_local_avatar', true);
        $blank_img = includes_url('images/blank.gif');
        ?>
        <h3><?php _e('Local Avatar', 'wp-genius'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label><?php _e('Current Avatar', 'wp-genius'); ?></label>
                </th>
                <td>
                    <input type="hidden" name="st_local_avatar" id="st_local_avatar" value="<?php echo esc_attr($avatar_id); ?>">
                    <div id="st-avatar-preview">
                        <?php
                        if ($avatar_id) {
                            echo wp_get_attachment_image($avatar_id, 96);
                        } else {
                            echo '<img src="' . esc_url($blank_img) . '" width="96" height="96" style="background:#f1f1f1;border-radius:50%;" />';
                        }
                        ?>
                    </div>
                    <p>
                        <button type="button" class="button" id="st-upload-avatar">
                            <?php _e('Upload / Select Avatar', 'wp-genius'); ?>
                        </button>
                        <button type="button" class="button" id="st-remove-avatar">
                            <?php _e('Remove Avatar', 'wp-genius'); ?>
                        </button>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function print_avatar_js() {
        $screen = get_current_screen();
        if (!$screen || ($screen->base !== 'profile' && $screen->base !== 'user-edit')) {
            return;
        }

        $blank_img = esc_url(includes_url('images/blank.gif'));
        ?>
        <script>
        (function($) {
            $('#st-upload-avatar').on('click', function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: '<?php _e('Select Avatar', 'wp-genius'); ?>',
                    library: { type: 'image' },
                    multiple: false
                }).on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#st_local_avatar').val(attachment.id);
                    $('#st-avatar-preview').html('<img src="' + attachment.url + '" width="96" height="96" style="border-radius:50%;" />');
                }).open();
            });

            $('#st-remove-avatar').on('click', function(e) {
                e.preventDefault();
                $('#st_local_avatar').val('');
                $('#st-avatar-preview').html('<img src="<?php echo $blank_img; ?>" width="96" height="96" style="background:#f1f1f1;border-radius:50%;" />');
            });
        })(jQuery);
        </script>
        <?php
    }

    public function save_avatar($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        $avatar_id = isset($_POST['st_local_avatar']) ? absint($_POST['st_local_avatar']) : 0;
        update_user_meta($user_id, 'st_local_avatar', $avatar_id);
    }

    public function get_local_avatar($avatar, $id_or_email, $size, $default, $alt) {
        // Resolve user object
        if (is_numeric($id_or_email)) {
            $user = get_user_by('id', $id_or_email);
        } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
            $user = get_user_by('id', $id_or_email->user_id);
        } else {
            $user = get_user_by('email', $id_or_email);
        }

        if (!$user) {
            return $avatar;
        }

        // Get local avatar ID
        $avatar_id = get_user_meta($user->ID, 'st_local_avatar', true);
        if ($avatar_id) {
            return wp_get_attachment_image($avatar_id, array($size, $size), false, array(
                'class' => "avatar avatar-{$size}",
                'alt'   => $alt,
            ));
        }

        // Fallback to blank image
        $blank_img = esc_url(includes_url('images/blank.gif'));
        return '<img src="' . $blank_img . '" class="avatar avatar-' . $size . '" width="' . $size . '" height="' . $size . '" alt="' . esc_attr($alt) . '" />';
    }

    /**
     * ============================================
     * Upload Rename Integration
     * ============================================
     */

    protected function init_upload_rename() {
        $settings = get_option( 'w2p_accelerate_settings', [] );
        if ( empty( $settings['enable_upload_rename'] ) ) {
            return;
        }

        // 在上传预处理阶段重命名文件名
        add_filter('wp_handle_upload_prefilter', array($this, 'handle_upload_prefilter'));

        // 在插入附件数据前，允许替换 post_title 为原始文件名
        add_filter('wp_insert_attachment_data', array($this, 'maybe_replace_attachment_title'), 10, 2);
    }

    public function handle_upload_prefilter($file) {
        if (empty($file['name'])) {
            return $file;
        }

        $settings = get_option( 'w2p_accelerate_settings', [] );
        $pattern = isset($settings['upload_rename_pattern']) ? $settings['upload_rename_pattern'] : '{timestamp}_{sanitized}';
        
        // 保留原始模板以便判断是否包含 {ext}
        $pattern_template = $pattern;
        // 支持 {date} 或 {date:FORMAT}，默认格式 Y-m-d
        $pattern = preg_replace_callback('/\{date(?::([^}]+))?\}/', function($m) {
            $fmt = isset($m[1]) && $m[1] ? $m[1] : 'Y-m-d';
            return date($fmt);
        }, $pattern);
        // 原始基名（不含扩展名），作为媒体标题使用（做最小的文件名清理）
        $original_base = pathinfo($file['name'], PATHINFO_FILENAME);
        $original_base = sanitize_file_name( $original_base );
        $sanitized = $original_base;
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $timestamp = time();
        $random = wp_rand(1000, 9999);

        // 当前用户信息（若已登录）
        $current_user = wp_get_current_user();
        $user_login = !empty($current_user->user_login) ? $current_user->user_login : '';
        $user_id = !empty($current_user->ID) ? $current_user->ID : 0;

        $replacements = array(
            '{timestamp}' => $timestamp,
            '{sanitized}' => $sanitized,
            '{rand}'      => $random,
            '{datetime}' => date('YmdHis'),
            '{year}'     => date('Y'),
            '{month}'    => date('m'),
            '{day}'      => date('d'),
            '{hour}'     => date('H'),
            '{minute}'   => date('i'),
            '{second}'   => date('s'),
            '{user_id}'  => $user_id,
            '{user_login}' => $user_login,
            '{orig}'     => $original_base,
            '{ext}'      => $ext,
            '{uniqid}'   => uniqid(),
        );

        // 执行替换
        $new_name = strtr( $pattern, $replacements );

        // 如果模板没有包含 {ext}，则自动追加扩展名
        if ( false === strpos( $pattern_template, '{ext}' ) ) {
            $new_name = $new_name . ( $ext ? '.' . $ext : '' );
        }

        // 确保文件名不包含非法字符
        $new_sanitized = sanitize_file_name($new_name);
        $file['name'] = $new_sanitized;

        // 记录映射：新基名 => 原始基名（未含扩展）
        $new_base = pathinfo( $new_sanitized, PATHINFO_FILENAME );
        self::$original_titles[ $new_base ] = $original_base;

        return $file;
    }

    public function maybe_replace_attachment_title( $data, $postarr ) {
        if ( empty( $data['post_title'] ) ) {
            return $data;
        }

        $current_base = sanitize_file_name( $data['post_title'] );
        if ( isset( self::$original_titles[ $current_base ] ) ) {
            // 使用原始基名（未含扩展）作为标题
            $data['post_title'] = self::$original_titles[ $current_base ];
        }

        return $data;
    }
}
