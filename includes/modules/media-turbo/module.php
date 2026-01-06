<?php
/**
 * Media Turbo Module
 *
 * Automatically converts images to modern formats like WebP.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MediaTurboModule extends W2P_Abstract_Module {
    
    public static function id() {
        return 'media-turbo';
    }

    public static function name() {
        return __( 'Media Turbo', 'wp-genius' );
    }

    public static function description() {
        return __( 'Automatically convert uploaded images to WebP format for faster loading.', 'wp-genius' );
    }

    public function init() {
        // Load converter service
        require_once __DIR__ . '/converter-service.php';
        
        // Settings registration
        $this->register_settings();

        // Hook into upload process
        add_filter( 'wp_handle_upload', [ $this, 'process_uploaded_image' ] );

        // AJAX handlers for bulk conversion
        add_action( 'wp_ajax_w2p_media_turbo_get_stats', [ $this, 'ajax_get_bulk_stats' ] );
        add_action( 'wp_ajax_w2p_media_turbo_batch_convert', [ $this, 'ajax_batch_convert' ] );
        add_action( 'wp_ajax_w2p_media_turbo_reset_processed', [ $this, 'ajax_reset_processed' ] );

        // Admin scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'wp-genius-settings' ) === false ) {
            return;
        }

        wp_enqueue_script( 'w2p-media-turbo' );
        wp_localize_script( 'w2p-media-turbo', 'w2pMediaTurbo', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'w2p_media_turbo_nonce' ),
        ] );
    }

    public function ajax_get_bulk_stats() {
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            session_write_close();
        }
        check_ajax_referer( 'w2p_media_turbo_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        $limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : ( isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 100 );
        
        $service = new MediaTurboConverterService();
        $total = $service->get_total_candidate_count();
        // 不限制preview数量，返回所有图片信息
        $allIds = $service->get_conversion_candidates( $limit, 0, true );
        $allDetails = $service->get_conversion_candidates( $limit, 0, false );

        wp_send_json_success( [ 
            'total' => $total,
            'allIds' => $allIds,
            'preview' => $allDetails  // 返回所有详细信息，不限制50条
        ] );
    }

    public function ajax_batch_convert() {
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            session_write_close();
        }
        check_ajax_referer( 'w2p_media_turbo_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $item_ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
        if ( empty( $item_ids ) ) {
            wp_send_json_error( 'No IDs provided' );
        }
        
        $service = new MediaTurboConverterService();
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        $quality = isset( $settings['webp_quality'] ) ? intval( $settings['webp_quality'] ) : 80;

        $results = [];

        foreach ( $item_ids as $item_id ) {
            $mime = get_post_mime_type( $item_id );
            
            if ( $mime === 'image/webp' || $mime === 'image/gif' ) {
                $results[] = [ 'id' => $item_id, 'status' => 'skipped', 'message' => 'Format skipped' ];
                continue;
            }

            $conversion_result = $service->convert_attachment( $item_id, $quality );

            if ( $conversion_result && ! empty( $conversion_result['success'] ) ) {
                // 仅记录成功的消息
                $file_name = basename( get_attached_file( $item_id ) );
                $post_parent = get_post_field( 'post_parent', $item_id );
                W2P_Logger::info( sprintf(
                    '[Media Turbo] Success: %s (ID: %d, Post: %d, Affected: %d)',
                    $file_name,
                    $item_id,
                    $post_parent,
                    $conversion_result['affected']
                ), 'media-turbo' );
                
                // Mark parent post as processed if in posts scan mode
                if ( $post_parent ) {
                    $service->mark_post_as_processed( $post_parent );
                }
                
                $results[] = [ 
                    'id'       => $item_id,
                    'status'   => 'success', 
                    'affected' => $conversion_result['affected'],
                    'deleted'  => $conversion_result['deleted'] ?? 0,
                    'newUrl'   => $conversion_result['new_url']
                ];
            } else {
                $results[] = [ 'id' => $item_id, 'status' => 'error', 'message' => 'Conversion failed' ];
            }
        }

        wp_send_json_success( $results );
    }
    
    public function ajax_reset_processed() {
        check_ajax_referer( 'w2p_media_turbo_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        delete_option( 'w2p_media_turbo_processed_posts' );
        wp_send_json_success( [ 'message' => 'Processed posts list has been reset' ] );
    }

    public function register_settings() {
        register_setting( 'w2p_media_turbo_settings', 'w2p_media_turbo_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => [
                'webp_enabled'  => true,
                'webp_quality'  => 80,
                'keep_original' => true,
                'min_file_size' => 1024,
                'scan_mode'     => 'media',
                'posts_limit'   => 10,
                'scan_limit'    => 100,
                'batch_size'    => 10,
            ]
        ] );
    }

    public function sanitize_settings( $input ) {
        $output = [];
        $output['webp_enabled']  = ! empty( $input['webp_enabled'] );
        $output['webp_quality']  = isset( $input['webp_quality'] ) ? absint( $input['webp_quality'] ) : 80;
        $output['keep_original'] = ! empty( $input['keep_original'] );
        $output['min_file_size'] = isset( $input['min_file_size'] ) ? absint( $input['min_file_size'] ) : 1024;
        $output['scan_mode']     = isset( $input['scan_mode'] ) && in_array( $input['scan_mode'], [ 'media', 'posts' ] ) ? $input['scan_mode'] : 'media';
        $output['posts_limit']   = isset( $input['posts_limit'] ) ? absint( $input['posts_limit'] ) : 10;
        $output['scan_limit']    = isset( $input['scan_limit'] ) ? absint( $input['scan_limit'] ) : 100;
        $output['batch_size']    = isset( $input['batch_size'] ) ? absint( $input['batch_size'] ) : 10;
        return $output;
    }

    /**
     * Process Uploaded Image
     */
    public function process_uploaded_image( $upload ) {
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        if ( empty( $settings['webp_enabled'] ) ) {
            return $upload;
        }

        $file_path = $upload['file'];
        $type = $upload['type'];

        // Only process JPEGs and PNGs
        if ( ! in_array( $type, [ 'image/jpeg', 'image/png' ] ) ) {
            return $upload;
        }

        // Performance: Skip synchronous conversion for large files (e.g., > 5MB)
        $file_size = filesize( $file_path );
        if ( $file_size > 5 * 1024 * 1024 ) {
            W2P_Logger::info( 'Skipping synchronous WebP conversion for large file (' . round( $file_size / 1024 / 1024, 2 ) . 'MB). Use Bulk Optimization instead.', 'media-turbo' );
            return $upload;
        }

        $service = new MediaTurboConverterService();
        $webp_path = $service->convert_to_webp( $file_path, intval( $settings['webp_quality'] ) );

        if ( $webp_path ) {
            // Replace upload data if original is not kept
            if ( empty( $settings['keep_original'] ) ) {
                @unlink( $file_path );
                $upload['file'] = $webp_path;
                $upload['url'] = str_replace( basename( $file_path ), basename( $webp_path ), $upload['url'] );
                $upload['type'] = 'image/webp';
            }
        }

        return $upload;
    }

    public function render_settings() {
        $this->render_view( 'settings' );
    }

    public function activate() {}
    public function deactivate() {}
}
