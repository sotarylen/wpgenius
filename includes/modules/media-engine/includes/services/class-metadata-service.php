<?php
/**
 * Media Engine Metadata Service
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Media_Metadata_Service {

	public function update( $attachment_id, $old_path, $new_path ) {
		global $wpdb;
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];
		if ( strpos( $new_path, $base_dir ) === 0 ) {
			$relative_path = ltrim( substr( $new_path, strlen( $base_dir ) ), '/\\' );
		} else {
			$relative_path = $new_path;
		}
		update_attached_file( $attachment_id, $relative_path );
		$wpdb->update( $wpdb->posts, array( 'post_mime_type' => 'image/webp' ), array( 'ID' => $attachment_id ) );

		// Targeted cache invalidation without firing buggy supercache hooks
		wp_cache_delete( $attachment_id, 'posts' );
		wp_cache_delete( $attachment_id, 'post_meta' );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( $metadata ) {
			$metadata['file'] = $relative_path;
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
		return true;
	}

	public function save_original_info( $attachment_id, $original_file ) {
		update_post_meta( $attachment_id, '_w2p_original_file', $original_file );
	}

	public function cleanup_original( $attachment_id ) {
		$webp_path         = get_attached_file( $attachment_id );
		$dir               = dirname( $webp_path );
		$original_filename = get_post_meta( $attachment_id, '_w2p_original_file', true );
		if ( $original_filename && file_exists( $dir . '/' . $original_filename ) ) {
			$source_dir = $dir . '/source';
			if ( ! is_dir( $source_dir ) ) {
				wp_mkdir_p( $source_dir );
			}
			$dest_file = $source_dir . '/' . $original_filename;
			if ( file_exists( $dest_file ) ) {
				@unlink( $dest_file );
			}
			@rename( $dir . '/' . $original_filename, $dest_file );
			return array(
				'success' => true,
				'count'   => 1,
			);
		}
		return array(
			'success' => true,
			'count'   => 0,
		);
	}
}

// Backward compatibility alias.
if ( ! class_exists( 'MediaEngineMetadataService', false ) ) {
	class_alias( 'W2P_Media_Metadata_Service', 'MediaEngineMetadataService' );
}
