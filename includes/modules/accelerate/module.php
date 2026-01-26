<?php
/**
 * Accelerate Module
 *
 * Merges functionality from Cleanup WordPress and Update Behavior modules.
 * Now also includes functionality from removed sub-modules (Cleanup Images) for a flatter structure.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccelerateModule extends W2P_Abstract_Module {
    
    protected static $original_titles = array();

    public function settings_key() {
        return 'w2p_settings';
    }

    public static function id() {
        return 'accelerate';
    }

    public static function name() {
        return __( 'Accelerate', 'wp-genius' );
    }

    public static function description() {
        return __( 'Optimize WordPress performance by cleaning up admin interface and controlling update behaviors.', 'wp-genius' );
    }

    public static function icon() {
        return 'fa-solid fa-gauge-high';
    }

    public function init() {
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

        // Delete with Images Hooks (Merged from AccelerateCleanupImages)
        $this->init_cleanup_images();
        
        // Admin Body Classes for conditional styles
        add_filter( 'admin_body_class', [ $this, 'add_body_classes' ] );
    }
    
    /**
     * Add admin body classes for styling hooks
     */
    public function add_body_classes( $classes ) {
        $settings = $this->get_settings();
        
        if ( ! empty( $settings['accelerate_hide_plugin_notices'] ) ) {
            $classes .= ' w2p-acc-hide-notices';
        }
        
        if ( ! empty( $settings['accelerate_enable_local_avatar'] ) ) {
            $classes .= ' w2p-acc-local-avatar';
        }
        
        return $classes;
    }

    /**
     * Clean Admin Bar
     */
    public function clean_admin_bar() {
        global $wp_admin_bar;
        $settings = $this->get_settings();

        $items_to_remove = [
            'accelerate_remove_admin_bar_wp_logo'       => 'wp-logo',
            'accelerate_remove_admin_bar_about'         => 'about',
            'accelerate_remove_admin_bar_comments'      => 'comments',
            'accelerate_remove_admin_bar_new_content'   => 'new-content',
            'accelerate_remove_admin_bar_search'        => 'search',
            'accelerate_remove_admin_bar_updates'       => 'updates',
            'accelerate_remove_admin_bar_appearance'    => 'appearance',
            'accelerate_remove_admin_bar_customize'     => 'customize',
            'accelerate_remove_admin_bar_wporg'         => 'wporg',
            'accelerate_remove_admin_bar_documentation' => 'documentation',
            'accelerate_remove_admin_bar_support_forums' => 'support-forums',
            'accelerate_remove_admin_bar_feedback'      => 'feedback',
            'accelerate_remove_admin_bar_view_site'     => 'view-site',
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
        global $wp_meta_boxes;
        $settings = $this->get_settings();

        $widgets_to_remove = [
            'accelerate_remove_dashboard_primary'      => [ 'dashboard', 'side', 'core', 'dashboard_primary' ],
            'accelerate_remove_dashboard_secondary'    => [ 'dashboard', 'side', 'core', 'dashboard_secondary' ],
            'accelerate_remove_dashboard_site_health'  => [ 'dashboard', 'normal', 'core', 'dashboard_site_health' ],
            'accelerate_remove_dashboard_right_now'    => [ 'dashboard', 'normal', 'core', 'dashboard_right_now' ],
            'accelerate_remove_dashboard_quick_draft'  => [ 'dashboard', 'side', 'core', 'dashboard_quick_press' ],
            'accelerate_remove_dashboard_activity'     => [ 'dashboard', 'normal', 'core', 'dashboard_activity' ],
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
     */
    public function should_disable_months_dropdown( $disable, $post_type ) {
        $settings = $this->get_settings();
        if ( ! empty( $settings['accelerate_disable_months_dropdown'] ) ) {
            return true;
        }
        return $disable;
    }

    /**
     * Disable Media Months UI
     */
    public function disable_media_months( $months ) {
        $settings = $this->get_settings();
        if ( ! empty( $settings['accelerate_disable_months_dropdown'] ) ) {
            return [];
        }
        return $months;
    }

    /**
     * Intercept and block date-based SELECT DISTINCT queries
     */
    public function intercept_date_query( $query ) {
        if ( ! is_admin() ) {
            return $query;
        }

        // Target the specific slow queries for years/months
        if ( strpos( $query, 'SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month' ) !== false ) {
            $settings = $this->get_settings();
            if ( ! empty( $settings['accelerate_disable_months_dropdown'] ) ) {
                return "SELECT 1 FROM wp_posts WHERE 1=0";
            }
        }

        return $query;
    }

    /**
     * Apply Update Behaviors
     */
    public function apply_update_behavior() {
        $s = $this->get_settings();

        if ( ! empty( $s['accelerate_disable_auto_update_plugin'] ) ) {
            add_filter( 'auto_update_plugin', '__return_false' );
        }

        if ( ! empty( $s['accelerate_disable_auto_update_theme'] ) ) {
            add_filter( 'auto_update_theme', '__return_false' );
        }

        if ( ! empty( $s['accelerate_remove_wp_update_plugins'] ) ) {
            remove_action( 'wp_update_plugins', 'wp_update_plugins' );
        }

        if ( ! empty( $s['accelerate_remove_wp_update_themes'] ) ) {
            remove_action( 'wp_update_themes', 'wp_update_themes' );
        }

        if ( ! empty( $s['accelerate_remove_maybe_update_core'] ) ) {
            remove_action( 'admin_init', '_maybe_update_core' );
        }

        if ( ! empty( $s['accelerate_remove_maybe_update_plugins'] ) ) {
            remove_action( 'admin_init', '_maybe_update_plugins' );
        }

        if ( ! empty( $s['accelerate_remove_maybe_update_themes'] ) ) {
            remove_action( 'admin_init', '_maybe_update_themes' );
        }

        if ( ! empty( $s['accelerate_block_external_http'] ) ) {
            if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
                define( 'WP_HTTP_BLOCK_EXTERNAL', true );
            }
        }

        if ( ! empty( $s['accelerate_block_acf_updates'] ) ) {
            add_filter('http_request_args', array($this, 'block_acf_update_requests'), 10, 2);
        }
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

    /**
     * ============================================
     * Local Avatar Management Integration
     * ============================================
     */
    protected function init_local_avatar() {
        $settings = $this->get_settings();
        if ( empty( $settings['accelerate_enable_local_avatar'] ) ) {
            return;
        }

        // Styles are handled by admin_styles()

        // Load media library scripts on profile pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_avatar_scripts'));

        // Show custom avatar field
        add_action('show_user_profile', array($this, 'render_avatar_field'));
        add_action('edit_user_profile', array($this, 'render_avatar_field'));

        // Print inline JS for upload/remove functionality
        // [Refactor] Should extract to JS file, but keeping inline for now to prioritize logic fix 
        add_action('admin_print_footer_scripts', array($this, 'print_avatar_js'));

        // Save avatar metadata
        add_action('personal_options_update', array($this, 'save_avatar'));
        add_action('edit_user_profile_update', array($this, 'save_avatar'));

        // Override get_avatar to use local avatars
        add_filter('get_avatar', array($this, 'get_local_avatar'), 10, 5);
    }

    public function enqueue_avatar_scripts() {
        $screen = get_current_screen();
        if (!$screen || ($screen->base !== 'profile' && $screen->base !== 'user-edit')) {
            return;
        }
        wp_enqueue_media();
        // Inline styles moved to admin_styles()
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

        $avatar_id = get_user_meta($user->ID, 'st_local_avatar', true);
        if ($avatar_id) {
            return wp_get_attachment_image($avatar_id, array($size, $size), false, array(
                'class' => "avatar avatar-{$size}",
                'alt'   => $alt,
            ));
        }

        $blank_img = esc_url(includes_url('images/blank.gif'));
        return '<img src="' . $blank_img . '" class="avatar avatar-' . $size . '" width="' . $size . '" height="' . $size . '" alt="' . esc_attr($alt) . '" />';
    }

    /**
     * ============================================
     * Upload Rename Integration
     * ============================================
     */
    protected function init_upload_rename() {
        $settings = $this->get_settings();
        if ( empty( $settings['accelerate_enable_upload_rename'] ) ) {
            return;
        }

        add_filter('wp_handle_upload_prefilter', array($this, 'handle_upload_prefilter'));
        add_filter('wp_insert_attachment_data', array($this, 'maybe_replace_attachment_title'), 10, 2);
    }

    public function handle_upload_prefilter($file) {
        if (empty($file['name'])) {
            return $file;
        }

        $settings = $this->get_settings();
        $pattern = isset($settings['accelerate_upload_rename_pattern']) ? $settings['accelerate_upload_rename_pattern'] : '{timestamp}_{sanitized}';
        
        $pattern_template = $pattern;
        $pattern = preg_replace_callback('/\{date(?::([^}]+))?\}/', function($m) {
            $fmt = isset($m[1]) && $m[1] ? $m[1] : 'Y-m-d';
            return date($fmt);
        }, $pattern);
        
        $original_base = pathinfo($file['name'], PATHINFO_FILENAME);
        $original_base = sanitize_file_name( $original_base );
        $sanitized = $original_base;
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $timestamp = time();
        $random = wp_rand(1000, 9999);
        
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

        $new_name = strtr( $pattern, $replacements );

        if ( false === strpos( $pattern_template, '{ext}' ) ) {
            $new_name = $new_name . ( $ext ? '.' . $ext : '' );
        }

        $new_sanitized = sanitize_file_name($new_name);
        $file['name'] = $new_sanitized;

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
            $data['post_title'] = self::$original_titles[ $current_base ];
        }

        return $data;
    }

    /**
     * ============================================
     * Delete with Images Integration
     * ============================================
     */
    protected function init_cleanup_images() {
        $settings = $this->get_settings();
        if ( empty( $settings['accelerate_enable_delete_with_images'] ) ) {
            return;
        }

        // Add row action link
        add_filter( 'post_row_actions', [ $this, 'cleanup_images_add_row_action' ], 10, 2 );
        add_filter( 'page_row_actions', [ $this, 'cleanup_images_add_row_action' ], 10, 2 );
        
        // Handle deletion action
        add_action( 'admin_post_w2p_delete_post_with_images', [ $this, 'cleanup_images_handle_delete_action' ] );

        // Add link to Edit Post screen
        add_action( 'post_submitbox_start', [ $this, 'cleanup_images_add_edit_post_action' ] );

        // Enqueue scripts (use core admin ui, merged CSS in admin_styles)
        add_action( 'admin_enqueue_scripts', [ $this, 'cleanup_images_enqueue_scripts' ] );
        add_action( 'admin_footer', [ $this, 'cleanup_images_print_footer_scripts' ] );

        // Bulk Actions
        add_filter( 'bulk_actions-edit-post', [ $this, 'cleanup_images_register_bulk_actions' ] );
        add_filter( 'bulk_actions-edit-page', [ $this, 'cleanup_images_register_bulk_actions' ] );
        // Note: 'handle_bulk_actions-{screen}' hooks need precise screen IDs.
        add_filter( 'handle_bulk_actions-edit-post', [ $this, 'cleanup_images_handle_bulk_actions' ], 10, 3 );
        add_filter( 'handle_bulk_actions-edit-page', [ $this, 'cleanup_images_handle_bulk_actions' ], 10, 3 );
    }

    /**
     * Register Bulk Action
     */
    public function cleanup_images_register_bulk_actions( $bulk_actions ) {
        $bulk_actions['w2p_delete_with_images_bulk'] = __( 'Delete w/ Images', 'wp-genius' );
        return $bulk_actions;
    }

    /**
     * Handle Bulk Actions
     */
    public function cleanup_images_handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
        if ( $doaction !== 'w2p_delete_with_images_bulk' ) {
            return $redirect_to;
        }

        $deleted_images = 0;
        $deleted_posts = 0;

        foreach ( $post_ids as $post_id ) {
            if ( ! current_user_can( 'delete_post', $post_id ) ) {
                continue;
            }
            
            // Reuse the processing logic
            $result = $this->cleanup_images_process_single_post_deletion($post_id);
            if ($result) {
                $deleted_posts++;
                $deleted_images += $result['deleted_images'];
            }
        }

        $redirect_to = add_query_arg( [
            'w2p_bulk_deleted_posts' => $deleted_posts,
            'w2p_bulk_deleted_images' => $deleted_images,
        ], $redirect_to );

        return $redirect_to;
    }

    /**
     * Add "Delete w/ Images" link to row actions
     */
    public function cleanup_images_add_row_action( $actions, $post ) {
        // Only for posts with permission
        if ( ! current_user_can( 'delete_post', $post->ID ) ) {
            return $actions;
        }

        // Only if not in trash
        if ( 'trash' === $post->post_status ) {
            return $actions;
        }

        // Capture current URL for safe redirect
        $redirect_to = urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) );

        // Build the deletion URL
        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=w2p_delete_post_with_images&post_id=' . $post->ID . '&redirect_to=' . $redirect_to ),
            'w2p_delete_with_images_' . $post->ID
        );

        // Add the action
        $actions['w2p_delete_with_images'] = sprintf(
            '<a href="%s" class="delete w2p-delete-with-images-btn">%s</a>',
            esc_url( $url ),
            esc_html__( 'Delete w/ Images', 'wp-genius' )
        );

        return $actions;
    }

    /**
     * Add "Delete w/ Images" link to Edit Post screen (Publish Meta Box)
     */
    public function cleanup_images_add_edit_post_action() {
        global $post;
        
        if ( ! $post || ! current_user_can( 'delete_post', $post->ID ) ) {
            return;
        }

        if ( 'trash' === $post->post_status ) {
            return;
        }

        // Redirect to post list after deletion from single edit screen
        $redirect_to = urlencode( admin_url( 'edit.php?post_type=' . $post->post_type ) );

        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=w2p_delete_post_with_images&post_id=' . $post->ID . '&redirect_to=' . $redirect_to ),
            'w2p_delete_with_images_' . $post->ID
        );
        
        // Render View
        ?>
        <div id="w2p-delete-with-images-wrap">
            <a href="<?php echo esc_url( $url ); ?>" class="submitdelete w2p-delete-with-images-btn">
                <?php esc_html_e( 'Delete w/ Images', 'wp-genius' ); ?>
            </a>
        </div>
        <script>
        // Move to the bottom next to Move to Trash if possible, or keep at top of submit box
        jQuery(document).ready(function($) {
            var $link = $('#w2p-delete-with-images-wrap');
            var $trashLink = $('#delete-action');
            
            if ($trashLink.length) {
                $link.css('margin-left', '10px'); // JS styling required for dynamic positioning
                $link.contents().appendTo($trashLink);
                $link.remove();
            }
        });
        </script>
        <?php
    }

    public function cleanup_images_enqueue_scripts() {
        wp_enqueue_script( 'w2p-admin-ui' );
        wp_enqueue_style( 'w2p-admin-ui' );
        
        // Module specific admin styles are already injected via admin_styles()
    }

    public function cleanup_images_print_footer_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Single Action Confirm
            $(document).on('click', '.w2p-delete-with-images-btn', function(e) {
                e.preventDefault();
                var href = $(this).attr('href');
                
                if (typeof w2p !== 'undefined' && typeof w2p.confirm === 'function') {
                    w2p.confirm(
                        '<?php echo esc_js( __( 'Are you sure you want to delete this post AND all its associated local images? This cannot be undone.', 'wp-genius' ) ); ?>',
                        function() {
                            window.location.href = href;
                        }
                    );
                } else {
                    if (confirm('<?php echo esc_js( __( 'Are you sure you want to delete this post AND all its associated local images? This cannot be undone.', 'wp-genius' ) ); ?>')) {
                        window.location.href = href;
                    }
                }
            });

            // Bulk Action Confirm
            $('#doaction, #doaction2').on('click', function(e) {
                var action = $(this).prev('select').val();
                
                if (action === 'w2p_delete_with_images_bulk') {
                    e.preventDefault();
                    var $form = $(this).closest('form');
                    
                    // Check if any items selected
                    if ($form.find('input[name="post[]"]:checked').length === 0) {
                        return;
                    }

                    var message = '<?php echo esc_js( __( 'Are you sure you want to delete the selected posts AND all their associated local images? This cannot be undone.', 'wp-genius' ) ); ?>';

                    if (typeof w2p !== 'undefined' && typeof w2p.confirm === 'function') {
                        w2p.confirm(
                            message,
                            function() {
                                // We need to submit the form. 
                                // Since we prevented default, we need to re-trigger or submit manually.
                                $form.submit();
                            }
                        );
                    } else {
                        if (confirm(message)) {
                            $form.submit();
                        }
                    }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Handle the deletion action
     */
    public function cleanup_images_handle_delete_action() {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_die( __( 'Invalid post ID.', 'wp-genius' ) );
        }

        check_admin_referer( 'w2p_delete_with_images_' . $post_id );

        if ( ! current_user_can( 'delete_post', $post_id ) ) {
            wp_die( __( 'You do not have permission to delete this post.', 'wp-genius' ) );
        }

        $result = $this->cleanup_images_process_single_post_deletion($post_id);

        // Default redirect
        $redirect_url = admin_url( 'edit.php?post_type=' . get_post_type( $post_id ) );

        // Check for custom redirect_to
        if ( ! empty( $_GET['redirect_to'] ) ) {
            $redirect_url = urldecode( $_GET['redirect_to'] );
        }

        if ( $result ) {
            $redirect_url = add_query_arg( [
                'w2p_deleted_images' => $result['deleted_images'],
                'w2p_deleted_post' => $post_id,
            ], $redirect_url );
        }
        
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Process deletion for a single post
     */
    private function cleanup_images_process_single_post_deletion($post_id) {
        // 1. Collect Images
        $image_ids = $this->cleanup_images_collect_post_images( $post_id );
        $deleted_images = 0;

        // 2. Delete Images
        foreach ( $image_ids as $attachment_id ) {
            if ( wp_delete_attachment( $attachment_id, true ) ) {
                $deleted_images++;
            }
        }

        // 3. Delete Post
        $result = wp_delete_post( $post_id, true ); // Force delete

        if ($result) {
            return ['deleted_images' => $deleted_images];
        }
        
        return false;
    }

    /**
     * Collect all local attachment IDs from post content and thumbnails
     */
    private function cleanup_images_collect_post_images( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [];
        }
        
        $image_ids = [];

        // A. Featured Image
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            $image_ids[] = $thumbnail_id;
        }

        // B. Content Images
        $content = $post->post_content;
        
        // 1. Try to match by class wp-image-{id} (Most reliable)
        if ( preg_match_all( '/class="[^"]*wp-image-(\d+)[^"]*"/', $content, $matches ) ) {
            if ( ! empty( $matches[1] ) ) {
                foreach ( $matches[1] as $id ) {
                    $image_ids[] = absint( $id );
                }
            }
        }

        // 2. Scan for img src URLs for images not caught by class match (e.g. pasted directly)
        if ( preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/', $content, $matches ) ) {
            if ( ! empty( $matches[1] ) ) {
                $site_url = home_url();
                $site_domain = parse_url( $site_url, PHP_URL_HOST );

                foreach ( $matches[1] as $url ) {
                    // Check if it's a local URL
                    $img_domain = parse_url( $url, PHP_URL_HOST );
                    if ( $img_domain !== $site_domain ) {
                        continue; // Skip external images
                    }

                    // Try to resolve to ID using built-in WP function
                    $id = attachment_url_to_postid( $url );
                    
                    // If failed, try to handle scaled images (remove -150x150 suffix etc)
                    if ( ! $id ) {
                        // Simple regex to strip dimensions: file-name-100x100.jpg -> file-name.jpg
                        $clean_url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif|webp)$)/i', '', $url );
                        if ( $clean_url !== $url ) {
                            $id = attachment_url_to_postid( $clean_url );
                        }
                    }

                    if ( $id ) {
                        $image_ids[] = $id;
                    }
                }
            }
        }

        // Unique and valid check
        $image_ids = array_unique( $image_ids );
        
        // Filter to ensure they are actually attachments
        $valid_ids = [];
        foreach ( $image_ids as $id ) {
            if ( 'attachment' === get_post_type( $id ) ) {
                $valid_ids[] = $id;
            }
        }

        return $valid_ids;
    }
}
