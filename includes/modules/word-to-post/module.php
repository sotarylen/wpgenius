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

        // Register AJAX handlers for import operations
        add_action('admin_post_handle_upload', array($this, 'handle_upload'));
        add_action('admin_post_scan_uploads', array($this, 'handle_scan'));
        add_action('admin_post_clean_uploads', array($this, 'handle_clean'));
        
        // Asset loading
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
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
        
        wp_localize_script('word-to-posts-js', 'word_to_posts_params', [
            'starting_import' => __('Starting to import and publish chapters...', 'wp-genius'),
            'cleaning' => __('Cleaning uploads folder...', 'wp-genius'),
            'scanning' => __('Scanning uploads folder...', 'wp-genius'),
            'error' => __('Tips', 'wp-genius')
        ]);
    }
}

?>
