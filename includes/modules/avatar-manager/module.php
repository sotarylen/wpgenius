<?php
if (!defined('ABSPATH')) {
    exit;
}

class AvatarManagerModule extends W2P_Abstract_Module {
    public static function id() {
        return 'avatar-manager';
    }

    public static function name() {
        return __('Local Avatar Manager', 'wp-genius');
    }

    public static function description() {
        return __('Replace WordPress Gravatar with local user avatar management. Upload and store avatars directly in your media library.', 'wp-genius');
    }

    public function init() {
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

    /**
     * Hide WP's default avatar section
     */
    public function hide_default_avatar_ui() {
        echo '<style>
            .user-profile-picture { display:none; }
        </style>';
    }

    /**
     * Enqueue media library and custom styles
     */
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

    /**
     * Render avatar upload field in user profile
     */
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

    /**
     * Print inline JavaScript for media library integration
     */
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

    /**
     * Save avatar ID to user meta
     */
    public function save_avatar($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        $avatar_id = isset($_POST['st_local_avatar']) ? absint($_POST['st_local_avatar']) : 0;
        update_user_meta($user_id, 'st_local_avatar', $avatar_id);
    }

    /**
     * Override get_avatar filter to use local avatars
     */
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

    public function register_settings() {
        // No additional settings needed for this module
    }

    public function activate() {
        do_action('w2p_avatar_manager_activated');
    }

    public function deactivate() {
        do_action('w2p_avatar_manager_deactivated');
    }
}
?>
