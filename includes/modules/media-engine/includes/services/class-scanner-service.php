<?php
/**
 * Media Engine Scanner Service
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineScannerService {
	private $settings;

	public function __construct() {
		$this->settings = get_option( 'w2p_media_turbo_settings', [] );
	}

	public function get_pending_attachments( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$mime_types = $this->get_supported_mime_types();
		if ( empty( $mime_types ) ) {
			return [];
		}
		$mime_types[] = 'image/webp';
		$mime_types = array_unique( $mime_types );
		$mime_placeholders = implode( ',', array_fill( 0, count( $mime_types ), '%s' ) );
		$query = $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = 'advmo_offloaded' AND pm.meta_value = '1')
			WHERE p.post_type = 'attachment' AND p.post_mime_type IN ($mime_placeholders) AND pm.post_id IS NULL
			ORDER BY p.ID DESC LIMIT %d OFFSET %d",
			array_merge( $mime_types, [ $limit, $offset ] )
		);
		return array_map( 'intval', $wpdb->get_col( $query ) );
	}

	public function get_pending_count() {
		return count( $this->get_pending_attachments( 1000, 0 ) );
	}

	private function get_supported_mime_types() {
		// Hardcoded support for all target types
		return [ 'image/jpeg', 'image/png', 'image/gif' ];
	}
}
