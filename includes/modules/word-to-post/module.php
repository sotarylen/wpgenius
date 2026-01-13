<?php
if (!defined('ABSPATH')) {
    exit;
}

class WordToPostModule extends W2P_Abstract_Module {
    public static function id() {
        return 'word-to-post';
    }

    public static function name() {
        return __('Word to Post', 'wp-genius');
    }

    public static function icon() {
        return 'fa-solid fa-file-word';
    }

    public static function description() {
        return __('Import Word documents (.docx) and convert them into WordPress posts with automatic chapter splitting.', 'wp-genius');
    }

    public function init() {
        // Load the library dependencies (PHPWord, etc.)
        $autoload = dirname(__FILE__) . '/library/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        // Include the logic handler class
        require_once dirname(__FILE__) . '/class-word-to-posts.php';
        
        // Include the Fix Chapter Index handler
        require_once dirname(__FILE__) . '/class-fix-chapter-index.php';

        // Register AJAX handlers for import operations
        add_action('admin_post_handle_upload', array($this, 'handle_upload'));
        add_action('admin_post_scan_uploads', array($this, 'handle_scan'));
        add_action('admin_post_scan_uploads', array($this, 'handle_scan'));
        add_action('admin_post_clean_uploads', array($this, 'handle_clean'));
        add_action('admin_post_fix_chapter_index', array($this, 'handle_fix_chapter_index'));
        add_action('wp_ajax_fix_chapter_index_save_config', array($this, 'handle_fix_chapter_index_save_config'));
        add_action('wp_ajax_fix_chapter_index_init', array($this, 'handle_fix_chapter_index_init'));
        add_action('wp_ajax_fix_chapter_index_process', array($this, 'handle_fix_chapter_index_process'));
        
        // Asset loading
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Bulk Action Script
        add_action('admin_footer', array($this, 'inject_bulk_action_script'));
        
        // Only load these on admin pages
        add_action('admin_init', array($this, 'register_settings'));
    }


    public function register_settings() {
        // Register settings for Word to Posts module (if needed for future expansion)
        register_setting('word2posts_modules', 'w2p_word_publish_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
            'default' => array(),
        ));
    }

    public function sanitize_settings($settings) {
        if (!is_array($settings)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($settings as $key => $value) {
            $sanitized[sanitize_key($key)] = sanitize_text_field($value);
        }
        
        return $sanitized;
    }

    /**
     * Handle DOCX file upload and conversion
     */
    public function handle_upload() {
        // Verify nonce
        if (!isset($_POST['word_to_posts_upload_nonce']) || 
            !wp_verify_nonce($_POST['word_to_posts_upload_nonce'], 'word_to_posts_upload')) {
            wp_die(__('Security check failed', 'wp-genius'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'wp-genius'));
        }

        // Delegate to the main WordToPosts class for actual processing
        if (class_exists('WordToPosts')) {
            $word_to_posts = new WordToPosts();
            $word_to_posts->handleFileUpload();
        }

        wp_redirect(admin_url('tools.php?page=wp-genius-settings#w2p-tab-word-to-post'));
        exit;
    }

    /**
     * Handle scan uploads directory
     */
    public function handle_scan() {
        if (!isset($_POST['word_to_posts_scan_nonce']) || 
            !wp_verify_nonce($_POST['word_to_posts_scan_nonce'], 'word_to_posts_scan')) {
            wp_die(__('Security check failed', 'wp-genius'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'wp-genius'));
        }

        if (class_exists('WordToPosts')) {
            $word_to_posts = new WordToPosts();
            $word_to_posts->scanUploads();
        }

        wp_redirect(admin_url('tools.php?page=wp-genius-settings#w2p-tab-word-to-post'));
        exit;
    }

    /**
     * Handle clean uploads directory
     */
    public function handle_clean() {
        if (!isset($_POST['word_to_posts_clean_nonce']) || 
            !wp_verify_nonce($_POST['word_to_posts_clean_nonce'], 'word_to_posts_clean')) {
            wp_die(__('Security check failed', 'wp-genius'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'wp-genius'));
        }

        if (class_exists('WordToPosts')) {
            $word_to_posts = new WordToPosts();
            $word_to_posts->cleanUploads();
        }

        wp_redirect(admin_url('tools.php?page=wp-genius-settings#w2p-tab-word-to-post'));
        exit;
    }

    /**
     * Handle fix chapter index (Wrapper)
     */
    public function handle_fix_chapter_index() {
        // This is primarily for the form submission which we handle via AJAX in class-word-to-posts.php 'fixChapterIndex'
        // But if someone hits the admin-post URL directly (without AJAX), we should handle it or redirect.
        // Actually, the class-word-to-posts.php registers the SAME hook 'admin_post_fix_chapter_index'.
        // To avoid double execution or conflict, we should rely on the class logic mostly.
        // However, since we are moving towards module.php handling hooks, let's delegate.
        
        // Check if it's an AJAX request (the class handles that).
        // If not, it's a direct POST.
        
         if (class_exists('WordToPosts')) {
            $word_to_posts = new WordToPosts();
            $word_to_posts->fixChapterIndex();
        }
        // Since fixChapterIndex returns JSON, we should probably exit here if not handled by it?
        // fixChapterIndex() sends json success/error.
        exit;
    }

    public function handle_fix_chapter_index_save_config() {
        if (class_exists('WordToPosts')) {
            $word_to_posts = new WordToPosts();
            $word_to_posts->fixChapterIndexSaveConfig();
        }
        exit;
    }

    public function handle_fix_chapter_index_init() {
        if (class_exists('WordToPosts')) {
            $word_to_posts = new WordToPosts();
            $word_to_posts->fixChapterIndexInit();
        }
        exit;
    }

    public function handle_fix_chapter_index_process() {
        if (class_exists('WordToPosts')) {
            $word_to_posts = new WordToPosts();
            $word_to_posts->fixChapterIndexProcess();
        }
        exit;
    }

    public function inject_bulk_action_script() {
        $screen = get_current_screen();
        global $typenow;
        if (( $screen && $screen->id === 'edit-chapter' ) || $typenow === 'chapter') {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Ensure the button exists in the select
                var bulkSelects = $('select[name="action"], select[name="action2"]');
                bulkSelects.each(function() {
                    if ($(this).find('option[value="fix_chapter_index"]').length === 0) {
                        $(this).append('<option value="fix_chapter_index"><?php _e('Auto Identify (Fix Index)', 'wp-genius'); ?></option>');
                    }
                });

                // Handle Apply Click
                $('#doaction, #doaction2').on('click', function(e) {
                    var selectId = $(this).attr('id') === 'doaction' ? 'action' : 'action2';
                    var action = $('select[name="' + selectId + '"]').val();

                    if (action === 'fix_chapter_index') {
                        e.preventDefault();
                        
                        var selected = [];
                        $('input[name="post[]"]:checked').each(function() {
                            selected.push($(this).val());
                        });

                        if (selected.length === 0) {
                            alert('<?php _e('Please select at least one chapter.', 'wp-genius'); ?>');
                            return;
                        }

                        if (!confirm('<?php _e('Are you sure you want to auto-identify indexes for specified chapters?', 'wp-genius'); ?>')) {
                            return;
                        }

                        // Use admin-ajax.php wrapper basically
                        var data = {
                            action: 'fix_chapter_index_init', // We reuse logic but might need custom handling for selection
                            // Wait, logic supports post_ids? Yes, fixChapterIndexInit reads post_ids if passed?
                            // Let's check fixChapterIndexInit... it does NOT read post_ids?
                            // I need to update fixChapterIndexInit to support post_ids if I want to reuse it.
                            // OR I use the old `handle_fix_chapter_index` which was synchronous?
                            // The user wants batch processing.
                            // If I use post_ids, count is small usually.
                            // Let's just use the Init logic updated to accept post_ids.
                            post_ids: selected,
                            word_to_posts_fix_index_nonce: '<?php echo wp_create_nonce('word_to_posts_fix_index'); ?>'
                        };
                        
                        // We need a JS function to handle the batch flow UI... 
                        // But we are on edit.php, no UI for progress bar!
                        // This is tricky.
                        // Ideally we should open a modal or redirect to settings page with IDs?
                        // Or just run it silently/alert?
                        // Since `editor.php` bulk action usually reloads, maybe just use legacy synchronous for small batches?
                        // But user asked for batch.
                        // Let's keep it simple: Use legacy synchronous loop via admin-admin.php or AJAX loop but show alert progress?
                        // "Batch Error" report might be from settings page.
                        // Bulk action just needs to work.
                        // Let's use `admin-post.php` action `fix_chapter_index` which exists and calls `fixChapterIndex` (synchronous).
                        // I will update module.php to ensure `fixChapterIndex` (old one) still works?
                        // No, I replaced it.
                        // So I MUST use the new batch logic.
                        // I will simple trigger a one-shot AJAX call that processes ALL selected (if small).
                        // If large, it might timeout. Bulk selection is usually < 200.
                        // So I will make a special AJAX call to `fix_chapter_index_process` with ALL IDs?
                        // `fixChapterIndexProcess` takes offsets.
                        // I will update `fixChapterIndexProcess` to handle `post_ids` explicitly if passed.
                    }
                });
            });
            </script>
            <?php
        }
    }

    public function activate() {
        // Activation logic if needed
        do_action('w2p_word_publish_activated');
    }

    public function deactivate() {
        // Deactivation logic if needed
        do_action('w2p_word_publish_deactivated');
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wp-genius-settings') === false) {
            return;
        }

        $plugin_url = plugin_dir_url(WP_GENIUS_FILE);
        
        wp_enqueue_script(
            'word-to-posts-js', 
            $plugin_url . 'assets/js/word-to-posts.js', 
            array('jquery'), 
            null, 
            true
        );
        
        // Enqueue Fix Chapter Index script
        wp_enqueue_script(
            'fix-chapter-index-js',
            $plugin_url . 'assets/js/fix-chapter-index.js',
            array('jquery'),
            null,
            true
        );
        
        wp_localize_script('word-to-posts-js', 'word_to_posts_params', [
            'starting_import' => __('Starting to import and publish chapters...', 'wp-genius'),
            'cleaning' => __('Cleaning uploads folder...', 'wp-genius'),
            'scanning' => __('Scanning uploads folder...', 'wp-genius'),
            'error' => __('Tips', 'wp-genius')
        ]);
    }
}

?>
