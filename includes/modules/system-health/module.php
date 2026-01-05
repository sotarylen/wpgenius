<?php
/**
 * System Health Module
 *
 * Provides tools for database cleanup and system optimization.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SystemHealthModule extends W2P_Abstract_Module {
    
    public static function id() {
        return 'system-health';
    }

    public static function name() {
        return __( 'System Health', 'wp-genius' );
    }

    public static function description() {
        return __( 'Optimize WordPress by cleaning up the database and scanning for unused media.', 'wp-genius' );
    }

    public function init() {
        // Load cleanup service
        require_once __DIR__ . '/cleanup-service.php';
        
        // AJAX handlers
        add_action( 'wp_ajax_w2p_system_health_clean', [ $this, 'ajax_cleanup_handler' ] );
        add_action( 'wp_ajax_w2p_system_health_get_stats', [ $this, 'ajax_get_stats_handler' ] );
        add_action( 'wp_ajax_w2p_system_health_scan_links', [ $this, 'ajax_scan_links_handler' ] );
        add_action( 'wp_ajax_w2p_system_health_remove_links', [ $this, 'ajax_remove_links_handler' ] );
        add_action( 'wp_ajax_w2p_system_health_scan_duplicates', [ $this, 'ajax_scan_duplicates_handler' ] );
        add_action( 'wp_ajax_w2p_system_health_trash_duplicates', [ $this, 'ajax_trash_duplicates_handler' ] );
        
        // Enhanced duplicate handlers for improved performance
        add_action( 'wp_ajax_w2p_system_health_scan_duplicates_improved', [ $this, 'ajax_scan_duplicates_improved_handler' ] );
        add_action( 'wp_ajax_w2p_system_health_get_duplicate_stats', [ $this, 'ajax_get_duplicate_stats_handler' ] );
        

        
        // Settings page assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function enqueue_scripts( $hook ) {
        // Only load on plugin settings page
        if ( strpos( $hook, 'wp-genius' ) === false ) {
            return;
        }

        wp_enqueue_script( 'w2p-modules-unified', plugin_dir_url( WP_GENIUS_FILE ) . 'assets/js/modules-unified.js', [ 'jquery' ], '1.0.0', true );
        
        $service = new SystemHealthCleanupService();
        $sh_categories = $service->get_categories();

        wp_localize_script( 'w2p-modules-unified', 'w2pSystemHealth', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'w2p_system_health_nonce' ),
            'confirm'  => __( 'Are you sure you want to perform this cleanup?', 'wp-genius' ),
            'cleaning' => __( 'Cleaning...', 'wp-genius' ),
            'categories' => $sh_categories,
            'scanning' => __( 'Scanning...', 'wp-genius' ),
            'executing' => __( 'Executing...', 'wp-genius' ),
        ] );
    }

    public function render_settings() {
        include __DIR__ . '/settings.php';
    }

    /**
     * AJAX Handler for Cleanups
     */
    public function ajax_cleanup_handler() {
        check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ] );
        }

        $type = isset( $_POST['cleanup_type'] ) ? sanitize_text_field( $_POST['cleanup_type'] ) : '';
        $service = new SystemHealthCleanupService();
        $count = 0;

        switch ( $type ) {
            case 'revisions':
                $count = $service->clean_revisions();
                break;
            case 'auto_drafts':
                $count = $service->clean_auto_drafts();
                break;
            case 'orphaned_meta':
                $count = $service->clean_orphaned_meta();
                break;
            case 'transients':
                $count = $service->clean_transients();
                break;
            default:
                wp_send_json_error( [ 'message' => __( 'Invalid cleanup type.', 'wp-genius' ) ] );
        }

        wp_send_json_success( [ 
            'message' => sprintf( __( 'Cleaned up %d items.', 'wp-genius' ), $count ),
            'count'   => $count
        ] );
    }

    /**
     * AJAX Handler for Statistics
     */
    public function ajax_get_stats_handler() {
        check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

        $service = new SystemHealthCleanupService();
        $stats = $service->get_stats();

        wp_send_json_success( $stats );
    }

    /**
     * AJAX Handler for Scanning Image Links
     */
    public function ajax_scan_links_handler() {
        check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

        $category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
        $service = new SystemHealthCleanupService();
        $results = $service->scan_posts_with_linked_images( $category_id );

        wp_send_json_success( $results );
    }

    /**
     * AJAX Handler for Removing Image Links
     */
    public function ajax_remove_links_handler() {
        check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

        $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : [];
        if ( empty( $post_ids ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post IDs.', 'wp-genius' ) ] );
        }

        $service = new SystemHealthCleanupService();
        $results = [];
        foreach ( $post_ids as $post_id ) {
            $results[ $post_id ] = $service->remove_image_links_from_post( $post_id );
        }

        wp_send_json_success( [ 'results' => $results ] );
    }

    /**
     * AJAX Handler for Scanning Duplicate Posts
     */
    public function ajax_scan_duplicates_handler() {
        check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'wp-genius' ) ] );
        }

        $category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
        
        try {
            $service = new SystemHealthCleanupService();
            $results = $service->scan_duplicate_posts( $category_id );
            
            // Ensure we always return an array
            if ( ! is_array( $results ) ) {
                $results = [];
            }
            
            wp_send_json_success( $results );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * AJAX Handler for Trashing Duplicate Posts
     */
    public function ajax_trash_duplicates_handler() {
        check_ajax_referer( 'w2p_system_health_nonce', 'nonce' );

        $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : [];
        if ( empty( $post_ids ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid post IDs.', 'wp-genius' ) ] );
        }

        $service = new SystemHealthCleanupService();
        $count = $service->trash_duplicate_posts( $post_ids );

        wp_send_json_success( [ 
            'message' => sprintf( __( 'Moved %d posts to trash.', 'wp-genius' ), $count ),
            'count'   => $count
        ] );
    }



    public function activate() {
        // Optional initialization on activation
    }

    public function deactivate() {
        // Optional cleanup on deactivation
    }
}
