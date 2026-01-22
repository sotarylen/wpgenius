<?php
/**
 * Media Turbo Handler
 * 
 * Handles media format conversion functionality for the Media Engine module.
 *
 * @package WP_Genius
 * @subpackage Modules\MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Media_Turbo_Handler {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Load converter service
		require_once plugin_dir_path( __FILE__ ) . 'converter-service.php';
		require_once plugin_dir_path( __FILE__ ) . 'relation-service.php';
		
		// Register settings
		$this->register_settings();
		
		// Hook into upload process
		add_filter( 'wp_handle_upload', [ $this, 'process_uploaded_image' ] );
		
		// AJAX handlers for bulk conversion
		add_action( 'wp_ajax_w2p_media_turbo_get_stats', [ $this, 'ajax_get_bulk_stats' ] );
		add_action( 'wp_ajax_w2p_media_turbo_batch_convert', [ $this, 'ajax_batch_convert' ] );
		add_action( 'wp_ajax_w2p_media_turbo_associate', [ $this, 'ajax_associate_webp' ] );
		add_action( 'wp_ajax_w2p_media_turbo_reset_processed', [ $this, 'ajax_reset_processed' ] );
		add_action( 'wp_ajax_w2p_execute_wpcli', [ $this, 'ajax_execute_wpcli' ] );
		add_action( 'wp_ajax_w2p_scan_attachments', [ $this, 'ajax_scan_attachments' ] );
		add_action( 'wp_ajax_w2p_process_attachment', [ $this, 'ajax_process_attachment' ] );
		
		// Admin scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}
	
	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Check for both old and new page hooks
		if ( strpos( $hook, 'wp-genius-settings' ) === false && strpos( $hook, 'word-to-posts' ) === false ) {
			return;
		}

		wp_register_script( 
			'w2p-media-turbo', 
			plugins_url( '/assets/js/modules/media-turbo.js', WP_GENIUS_FILE ), 
			[ 'jquery', 'w2p-core-js' ], 
			'1.0.0', 
			true 
		);

		wp_register_script( 
			'w2p-media-processing-ui', 
			plugins_url( '/assets/js/modules/media-processing-ui.js', WP_GENIUS_FILE ), 
			[ 'jquery', 'w2p-core-js' ], 
			'1.0.0', 
			true 
		);

		wp_enqueue_script( 'w2p-media-turbo' );
		wp_enqueue_script( 'w2p-media-processing-ui' );
		wp_localize_script( 'w2p-media-turbo', 'w2pMediaTurbo', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'w2p_media_turbo_nonce' ),
		] );
	}
	
	/**
	 * AJAX: Get bulk conversion stats
	 */
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
		$exclude_ids = isset( $_POST['exclude_ids'] ) ? array_map( 'absint', (array) $_POST['exclude_ids'] ) : [];
		$scan_type = isset( $_POST['scan_type'] ) ? sanitize_text_field( $_POST['scan_type'] ) : 'convert';
		
		$is_auto_associate = isset( $_POST['is_auto_associate'] ) && $_POST['is_auto_associate'] === '1';
		$settings_overrides = [];
		
		if ( $is_auto_associate ) {
			$settings_overrides = [
				'scan_mode'        => 'media',
				'min_file_size'    => 0,   // No size limit (0 KB)
				'convert_static'   => '1', // Include static images
				'convert_animated' => '1', // Include animated images
				'scan_type'        => 'associate',
			];
		} else {
			// Ensure scan_type is properly set
			$settings_overrides['scan_type'] = $scan_type;
		}
		
		// Add exclude_ids to settings overrides so it propagates
		if ( ! empty( $exclude_ids ) ) {
			$settings_overrides['exclude_ids'] = $exclude_ids;
		}
		
		$service = new MediaTurboConverterService();
		$total = $service->get_total_candidate_count( $settings_overrides );
		
		W2P_Logger::info( "ajax_get_bulk_stats: scan_type=$scan_type, total=$total", 'media-turbo' );
		
		// Optimization: If total is 0, don't bother fetching candidates
		if ( $total === 0 ) {
			W2P_Logger::info( "No candidates found for scan_type=$scan_type", 'media-turbo' );
			wp_send_json_success( [ 
				'total' => 0,
				'allIds' => [],
				'preview' => []
			] );
			return;
		}

		// Fetch candidates with details in one go for the preview
		$allDetails = $service->get_conversion_candidates( $limit, 0, false, $settings_overrides );
		$allIds = array_column( $allDetails, 'id' );

        // Add HTML for each preview item
        foreach ( $allDetails as &$item ) {
            $item['html'] = $this->render_row_html( $item );
        }

		wp_send_json_success( [ 
			'total' => $total,
			'allIds' => $allIds,
			'preview' => $allDetails
		] );
	}

    /**
     * Render a single row for the scan result table
     */
    public function render_row_html( $item, $statusText = 'Pending', $statusClass = 'pending' ) {
        $thumb = $item['thumbUrl'] ? 
            '<img src="' . esc_url( $item['thumbUrl'] ) . '" class="w2p-item-thumb" />' : 
            '<div class="w2p-item-thumb" style="display:flex;align-items:center;justify-content:center;background:#eee;color:#999;font-size:10px;">' . esc_html__( 'No Img', 'wp-genius' ) . '</div>';

        $fileName = ! empty( $item['fileName'] ) ? $item['fileName'] : 'ID: ' . $item['id'];
        $fileSize = ! empty( $item['fileSize'] ) ? ' <small>(' . esc_html( $item['fileSize'] ) . ' KB)</small>' : '';
        
        $association = ! empty( $item['parentUrl'] ) ? 
            '<small>' . esc_html__( 'Post: ', 'wp-genius' ) . '<a href="' . esc_url( $item['parentUrl'] ) . '" target="_blank">' . esc_html( $item['parentTitle'] ) . '</a></small>' : 
            '<small>' . esc_html__( 'Orphaned image', 'wp-genius' ) . '</small>';

        $html = '<tr id="w2p-item-' . esc_attr( $item['id'] ) . '">';
        $html .= '<td>' . $thumb . '</td>';
        $html .= '<td><div class="w2p-item-info"><strong>' . $fileName . $fileSize . '</strong>' . $association . '</div></td>';
        $html .= '<td class="w2p-item-status"><span class="w2p-status-badge w2p-status-pending">' . esc_html( $statusText ) . '</span></td>';
        $html .= '</tr>';

        return $html;
    }
	
	/**
	 * AJAX: Batch convert images
	 */
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
		$generate_thumbnails = ! empty( $settings['generate_thumbnails'] );

		$results = [];

		foreach ( $item_ids as $item_id ) {
			$mime = get_post_mime_type( $item_id );
			
			if ( $mime === 'image/webp' ) {
				$results[] = [ 'id' => $item_id, 'status' => 'skipped', 'message' => 'Format skipped' ];
				continue;
			}

			$conversion_result = $service->convert_attachment( $item_id, $quality, $generate_thumbnails );

			if ( $conversion_result && ! empty( $conversion_result['success'] ) ) {
				$file_name = basename( get_attached_file( $item_id ) );
				$post_parent = get_post_field( 'post_parent', $item_id );
				W2P_Logger::info( sprintf(
					'[Media Turbo] Success: %s (ID: %d, Post: %d, Affected: %d, Generated Thumbnails: %d)',
					$file_name,
					$item_id,
					$post_parent,
					$conversion_result['affected'],
					$conversion_result['generated_thumbnails'] ?? 0
				), 'media-turbo' );
				$results[] = [
					'id' => $item_id,
					'status' => 'success',
					'affected' => $conversion_result['affected'] ?? 0,
					'deleted' => $conversion_result['deleted'] ?? 0,
					'generatedThumbnails' => $conversion_result['generated_thumbnails'] ?? 0
				];
			} else {
                // Failure Handling
                $post_parent = get_post_field( 'post_parent', $item_id );
                if ( $post_parent ) {
                    $service->mark_post_as_processed( $post_parent );
                    W2P_Logger::warning( "Marking Post $post_parent as processed due to conversion error on Item $item_id", 'media-turbo' );
                }
                
                $msg = isset( $conversion_result['message'] ) ? $conversion_result['message'] : 'Conversion failed';
                update_post_meta( $item_id, '_w2p_media_turbo_failed', 1 );
                $results[] = [ 'id' => $item_id, 'status' => 'error', 'message' => $msg ];
            }
		}
        
        wp_send_json_success( $results );
	}
	
	/**
	 * AJAX: Batch Associate WebP
	 */
	public function ajax_associate_webp() {
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
		
		$service = new MediaTurboRelationService();
		$converter_service = new MediaTurboConverterService();
		
		$results = [];
		$replacements_by_post = [];

		foreach ( $item_ids as $item_id ) {
			// Skip content update for checking individually
			$res = $service->associate_webp( $item_id, true );

			if ( $res && ! empty( $res['success'] ) ) {
				$post_parent = get_post_field( 'post_parent', $item_id );
				$old_url = isset( $res['old_url'] ) ? $res['old_url'] : '';
                
				// Queue for batch update if it has a parent and we have the old URL
				if ( $post_parent && $old_url ) {
					if ( ! isset( $replacements_by_post[$post_parent] ) ) {
						$replacements_by_post[$post_parent] = [];
					}
					$replacements_by_post[$post_parent][] = [
						'old_url' => $old_url,
						'new_url' => $res['new_url'],
						'attachment_id' => $item_id
					];
				}
				
				$results[] = [ 
					'id'       => $item_id,
					'status'   => 'success', 
					'affected' => 0, // Will update this after batch processing
					'deleted'  => $res['deleted'] ?? 0,
					'newUrl'   => $res['new_url']
				];
			} else {
                // If association failed, mark the specific attachment as failed (not the entire post)
                // This allows us to skip this attachment in future scans but continue processing the post
				$msg = isset( $res['message'] ) ? $res['message'] : 'Association failed';
				update_post_meta( $item_id, '_w2p_media_turbo_failed', 1 );
				W2P_Logger::debug( "Marked attachment $item_id as failed: $msg", 'media-turbo' );
				$results[] = [ 'id' => $item_id, 'status' => 'error', 'message' => $msg ];
			}
		}

		// Process batch content updates
		if ( ! empty( $replacements_by_post ) ) {
			foreach ( $replacements_by_post as $post_id => $replacements ) {
				if ( empty( $replacements ) ) continue;
				
				$affected = $converter_service->batch_replace_urls_in_content( $post_id, $replacements );
				
				// Update results with affected count
				// We need to map back to the item IDs that were part of this post
				if ( $affected > 0 ) {
					foreach ( $replacements as $rep ) {
						$att_id = $rep['attachment_id'];
						// Find this ID in results and update 'affected'
						foreach ( $results as &$result ) {
							if ( $result['id'] === $att_id ) {
								$result['affected'] = 1; // It was part of an affected post
								break;
							}
						}
					}
				}
			}
		}

		wp_send_json_success( $results );
	}
	
	/**
	 * AJAX: Reset processed posts
	 */
	public function ajax_reset_processed() {
		check_ajax_referer( 'w2p_media_turbo_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}
		
		delete_option( 'w2p_media_turbo_processed_posts' );
		wp_send_json_success( [ 'message' => 'Processed posts list has been reset' ] );
	}
	
	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'w2p_media_turbo_settings', 'w2p_media_turbo_settings', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
			'default'           => [
				'webp_enabled'          => true,
				'webp_quality'          => 80,
				'keep_original'         => true,
				'min_file_size'         => 1024,
				'scan_mode'             => 'media',
				'posts_limit'           => 10,
				'scan_limit'            => 100,
				'batch_size'            => 10,
				'convert_static'        => '1',
				'convert_animated'      => '1',
				'generate_thumbnails'   => '1',
				'thumbnail_batch_delay' => 0,
			]
		] );
	}
	
	/**
	 * Sanitize settings
	 */
	public function sanitize_settings( $input ) {
		$output = [];
		$output['webp_enabled']          = ! empty( $input['webp_enabled'] );
		$output['webp_quality']          = isset( $input['webp_quality'] ) ? absint( $input['webp_quality'] ) : 80;
		$output['keep_original']         = ! empty( $input['keep_original'] );
		$output['min_file_size']         = isset( $input['min_file_size'] ) ? absint( $input['min_file_size'] ) : 1024;
		$output['scan_mode']             = isset( $input['scan_mode'] ) && in_array( $input['scan_mode'], [ 'media', 'posts' ] ) ? $input['scan_mode'] : 'media';
		$output['posts_limit']           = isset( $input['posts_limit'] ) ? absint( $input['posts_limit'] ) : 10;
		$output['scan_limit']            = isset( $input['scan_limit'] ) ? absint( $input['scan_limit'] ) : 100;
		$output['batch_size']            = isset( $input['batch_size'] ) ? absint( $input['batch_size'] ) : 10;
		$output['convert_static']        = ! empty( $input['convert_static'] ) ? '1' : '0';
		$output['convert_animated']      = ! empty( $input['convert_animated'] ) ? '1' : '0';
		$output['generate_thumbnails']   = ! empty( $input['generate_thumbnails'] ) ? '1' : '0';
		$output['thumbnail_batch_delay'] = isset( $input['thumbnail_batch_delay'] ) ? absint( $input['thumbnail_batch_delay'] ) : 0;
		return $output;
	}
	
	/**
	 * Process uploaded image
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

		// Skip large files
		$file_size = filesize( $file_path );
		if ( $file_size > 5 * 1024 * 1024 ) {
			W2P_Logger::info( 'Skipping synchronous WebP conversion for large file (' . round( $file_size / 1024 / 1024, 2 ) . 'MB). Use Bulk Optimization instead.', 'media-turbo' );
			return $upload;
		}

		$service = new MediaTurboConverterService();
		$webp_path = $service->convert_to_webp( $file_path, intval( $settings['webp_quality'] ) );

		if ( $webp_path ) {
			if ( empty( $settings['keep_original'] ) ) {
				@unlink( $file_path );
				$upload['file'] = $webp_path;
				$upload['url'] = str_replace( basename( $file_path ), basename( $webp_path ), $upload['url'] );
				$upload['type'] = 'image/webp';
			}
		}

		return $upload;
	}

	/**
	 * AJAX: Execute WP-CLI command
	 */
	public function ajax_execute_wpcli() {
		check_ajax_referer( 'w2p_media_turbo_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$command = isset( $_POST['command'] ) ? sanitize_text_field( $_POST['command'] ) : '';
		
		if ( empty( $command ) ) {
			wp_send_json_error( 'No command provided' );
		}

		// Security: Only allow specific WP-CLI commands
		$allowed_commands = [
			'wp media-engine check_env',
			'wp media-engine stats',
			'wp media-engine convert --limit=100',
			'wp media-engine convert --limit=100 --parallel --workers=4',
		];

		if ( ! in_array( $command, $allowed_commands, true ) ) {
			wp_send_json_error( 'Command not allowed' );
		}

		// Execute command
		$output = [];
		$return_code = 0;
		exec( $command . ' 2>&1', $output, $return_code );

		if ( $return_code === 0 ) {
			wp_send_json_success( [
				'output' => implode( "\n", $output ),
				'return_code' => $return_code,
			] );
		} else {
			wp_send_json_error( implode( "\n", $output ) );
		}
	}

	/**
	 * AJAX: Scan for pending attachments
	 */
	public function ajax_scan_attachments() {
		check_ajax_referer( 'w2p_media_turbo_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		if ( ! class_exists( 'MediaEngineProcessor' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-media-engine-processor.php';
		}

		$processor = new MediaEngineProcessor();
		$settings = get_option( 'w2p_media_turbo_settings', [] );
		$scan_limit = isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 1000;

		// Get pending attachments
		$attachments = $processor->get_pending_attachments( $scan_limit, 0 );
		$attachment_data = [];

		foreach ( $attachments as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			$file_size = 0;
			
			if ( $file_path && file_exists( $file_path ) ) {
				$file_size = round( filesize( $file_path ) / 1024, 2 );
			}

			$thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
			
			$attachment_data[] = [
				'id' => $attachment_id,
				'file_name' => basename( $file_path ),
				'file_size' => $file_size,
				'thumb_url' => $thumb ? $thumb[0] : '',
			];
		}

		wp_send_json_success( [
			'attachments' => $attachment_data,
			'count' => count( $attachment_data ),
		] );
	}

	/**
	 * AJAX: Process single attachment
	 */
	public function ajax_process_attachment() {
		check_ajax_referer( 'w2p_media_turbo_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( 'No attachment ID provided' );
		}

		if ( ! class_exists( 'MediaEngineProcessor' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-media-engine-processor.php';
		}

		$processor = new MediaEngineProcessor();
		$result = $processor->process_attachment( $attachment_id );

		if ( $result['success'] ) {
			wp_send_json_success( [
				'step' => 'done',
				'message' => 'Conversion completed successfully',
			] );
		} else {
			wp_send_json_error( $result['error'] ?? 'Unknown error' );
		}
	}
}
