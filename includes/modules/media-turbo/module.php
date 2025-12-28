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

        // Admin scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'wp-genius-settings' ) === false ) {
            return;
        }

        wp_enqueue_script( 'w2p-media-turbo', plugin_dir_url( __FILE__ ) . 'assets/media-turbo.js', [ 'jquery' ], '1.0.0', true );
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
        
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        $limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : ( isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 100 );
        
        $service = new MediaTurboConverterService();
        $total = $service->get_total_candidate_count();
        $preview_limit = min( 50, $limit );
        $preview = $service->get_conversion_candidates( $preview_limit, 0 );
        $allIds = $service->get_conversion_candidates( $limit, 0, true );

        wp_send_json_success( [ 
            'total' => $total,
            'preview' => $preview,
            'allIds' => $allIds
        ] );
    }

    public function ajax_batch_convert() {
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            session_write_close();
        }
        error_log( 'WP Genius [Media Turbo]: Batch processing started. IDs: ' . ( isset( $_POST['ids'] ) ? count( (array) $_POST['ids'] ) : 0 ) );
        check_ajax_referer( 'w2p_media_turbo_nonce', 'nonce' );
        
        $item_ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
        if ( empty( $item_ids ) ) {
            error_log( 'WP Genius [Media Turbo]: No IDs provided.' );
            wp_send_json_error( 'No IDs provided' );
        }
        
        $service = new MediaTurboConverterService();
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        $quality = isset( $settings['webp_quality'] ) ? intval( $settings['webp_quality'] ) : 80;

        $results = [];

        foreach ( $item_ids as $item_id ) {
            error_log( 'WP Genius [Media Turbo]: Processing image ID: ' . $item_id );
            $mime = get_post_mime_type( $item_id );
            
            if ( $mime === 'image/webp' || $mime === 'image/gif' ) {
                error_log( 'WP Genius [Media Turbo]: Skipping image ID ' . $item_id . ' (MIME: ' . $mime . ')' );
                $results[] = [ 'id' => $item_id, 'status' => 'skipped', 'message' => 'Format skipped' ];
                continue;
            }

            error_log( 'WP Genius [Media Turbo]: Full conversion start for ID ' . $item_id );
            $conversion_result = $service->convert_attachment( $item_id, $quality );

            if ( $conversion_result && ! empty( $conversion_result['success'] ) ) {
                error_log( 'WP Genius [Media Turbo]: Conversion & Replacement complete for ID ' . $item_id . '. Affected: ' . $conversion_result['affected'] );
                $results[] = [ 
                    'id'       => $item_id,
                    'status'   => 'success', 
                    'affected' => $conversion_result['affected'],
                    'newUrl'   => $conversion_result['new_url']
                ];
            } else {
                error_log( 'WP Genius [Media Turbo]: Full conversion FAILED for ID ' . $item_id );
                $results[] = [ 'id' => $item_id, 'status' => 'error', 'message' => 'Conversion failed' ];
            }
        }

        error_log( 'WP Genius [Media Turbo]: Batch processing complete. Results sent.' );
        wp_send_json_success( $results );
    }

    public function register_settings() {
        register_setting( 'w2p_media_turbo_settings', 'w2p_media_turbo_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => [
                'webp_enabled'  => true,
                'webp_quality'  => 80,
                'keep_original' => true,
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
            error_log( 'WP Genius: Skipping synchronous WebP conversion for large file (' . round( $file_size / 1024 / 1024, 2 ) . 'MB). Use Bulk Optimization instead.' );
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
        include __DIR__ . '/settings.php';
    }

    public function activate() {}
    public function deactivate() {}
}
