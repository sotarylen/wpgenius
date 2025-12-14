<?php
if (!defined('ABSPATH')) {
    exit;
}

class WordPublishModule extends W2P_Abstract_Module {
    public static function id() {
        return 'word-publish';
    }

    public static function name() {
        return __('WP Genius Publishing', 'wp-genius');
    }

    public static function description() {
        return __('Import Word documents (.docx) and convert them into WordPress posts with automatic chapter splitting.', 'wp-genius');
    }

    public function init() {
        // Register the module's admin menu and functionality
        add_action('admin_menu', array($this, 'register_menu'));
        
        // Register AJAX handlers for import operations
        add_action('admin_post_handle_upload', array($this, 'handle_upload'));
        add_action('admin_post_scan_uploads', array($this, 'handle_scan'));
        add_action('admin_post_clean_uploads', array($this, 'handle_clean'));
        
        // Only load these on admin pages
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_menu() {
        add_management_page(
            __('WP Genius', 'wp-genius'),
            __('WP Genius', 'wp-genius'),
            'manage_options',
            'wp-genius',
            array($this, 'render_page')
        );
    }

    public function render_page() {
        // Load the upload form template
        $template_path = dirname(__FILE__) . '/../../templates/upload-form.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
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

        wp_redirect(admin_url('tools.php?page=wp-genius'));
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

        wp_redirect(admin_url('tools.php?page=wp-genius'));
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

        wp_redirect(admin_url('tools.php?page=wp-genius'));
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
}

?>
