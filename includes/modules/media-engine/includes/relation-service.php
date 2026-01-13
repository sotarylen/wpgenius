<?php
/**
 * Media Turbo Relation Service
 *
 * Handles the association of existing WebP images to attachments.
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaTurboRelationService {

	/**
	 * Associate existing WebP files with an attachment
	 * 
	 * @param int $attachment_id The attachment ID to process.
	 * @return array Result of the association {'success': bool, 'new_url': string, 'affected': int}
	 */
	public function associate_webp( $attachment_id ) {
		$start_time = microtime( true );
		W2P_Logger::info( ">>> Starting WebP association for attachment ID: $attachment_id", 'media-turbo' );
		
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			W2P_Logger::error( "Cannot get file path for attachment ID: $attachment_id", 'media-turbo' );
			return [ 'success' => false, 'message' => 'No file path found' ];
		}

		// Calculate WebP path early
		$webp_path = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_path );
		$original_exists = file_exists( $file_path );
		$webp_exists = file_exists( $webp_path );

		// If neither exists, we can't do anything
		if ( ! $original_exists && ! $webp_exists ) {
			W2P_Logger::error( "Both original and WebP files missing for ID: $attachment_id", 'media-turbo' );
			return [ 'success' => false, 'message' => 'Files missing' ];
		}

		// If WebP is missing, we can't associate
		if ( ! $webp_exists ) {
			return [ 'success' => false, 'message' => 'WebP file missing' ];
		}

		// If original allows us to get info, do it, otherwise use WebP for basic info or skip
		$info = $original_exists ? @getimagesize( $file_path ) : @getimagesize( $webp_path ); 
		
		// It exists! Let's update metadata

		// It exists! Let's update metadata
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$base_dir = dirname( $file_path );
		$settings = get_option( 'w2p_media_turbo_settings', [] );
		$keep_original = ! empty( $settings['keep_original'] ); // We respect this setting for deletion if needed

		$old_url = wp_get_attachment_url( $attachment_id );
		$new_url = str_replace( basename( $file_path ), basename( $webp_path ), $old_url );
		
		W2P_Logger::info( "Found existing WebP: " . basename( $webp_path ), 'media-turbo' );

		// 1. Process Thumbnails
		$thumb_count = 0;
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size => $info ) {
				$thumb_path = $base_dir . '/' . $info['file'];
				// The thumbnail WebP usually has the same structure: name-size.webp
				// But sometimes plugins do name.webp-size.webp or other weird things.
				// Standard WP behavior when we might have generated it: name-size.webp.
				// The regex replacement is the safest bet if standard naming is used.
				$thumb_webp_path = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $thumb_path );
				
				if ( file_exists( $thumb_webp_path ) ) {
					$metadata['sizes'][$size]['file'] = basename( $thumb_webp_path );
					$metadata['sizes'][$size]['mime-type'] = 'image/webp';
					$thumb_count++;
					
					// Delete original thumbnail if configured
					if ( ! $keep_original && file_exists( $thumb_path ) ) {
						@unlink( $thumb_path );
					}
				}
			}
			W2P_Logger::info( "Associated $thumb_count thumbnails", 'media-turbo' );
		}

		// 2. Update Metadata and DB
		$metadata['file'] = str_replace( basename( $file_path ), basename( $webp_path ), $metadata['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		update_attached_file( $attachment_id, $webp_path ); // Point to the WebP file now

		global $wpdb;
		$wpdb->update( 
			$wpdb->posts, 
			[ 'post_mime_type' => 'image/webp', 'guid' => $new_url ], 
			[ 'ID' => $attachment_id ] 
		);

		// 3. Delete Original Main File if needed
		if ( ! $keep_original ) {
			@unlink( $file_path );
		}

		// 4. Replace content URLs
		require_once plugin_dir_path( __FILE__ ) . 'converter-service.php';
		$converter = new MediaTurboConverterService();
		$affected = $converter->replace_url_in_content( $old_url, $new_url, $attachment_id );
		
		$total_time = microtime( true ) - $start_time;
		W2P_Logger::info( sprintf( "<<< Association complete for ID %d in %.2fs (thumbs: %d, replaced: %d)", 
			$attachment_id, $total_time, $thumb_count, $affected ), 'media-turbo' );

		return [
			'success' => true,
			'new_url' => $new_url,
			'affected' => $affected,
			'deleted' => ! $keep_original ? 1 : 0
		];
	}
}
