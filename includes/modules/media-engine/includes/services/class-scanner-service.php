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
		$this->settings = W2P_Settings::tab_with_legacy( 'media_engine_tabs', 'w2p_media_turbo_settings', array() );
	}

	public function get_pending_attachments( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$mime_types = $this->get_supported_mime_types();
		if ( empty( $mime_types ) ) {
			return array();
		}
		$mime_types[]      = 'image/webp';
		$mime_types        = array_unique( $mime_types );
		$mime_placeholders = implode( ',', array_fill( 0, count( $mime_types ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN 占位符仅由 %s 组成（array_fill 生成），参数经 prepare 绑定，无注入面。
		$query = $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = 'advmo_offloaded' AND pm.meta_value = '1')
			WHERE p.post_type = 'attachment' AND p.post_mime_type IN ($mime_placeholders) AND pm.post_id IS NULL
			ORDER BY p.ID DESC LIMIT %d OFFSET %d",
			array_merge( $mime_types, array( $limit, $offset ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query 为上方 $wpdb->prepare() 返回值（LIMIT/OFFSET 以 %d 绑定）。
		return array_map( 'intval', $wpdb->get_col( $query ) );
	}

	public function get_pending_count() {
		return count( $this->get_pending_attachments( 1000, 0 ) );
	}

	private function get_supported_mime_types() {
		// Hardcoded support for all target types
		return array( 'image/jpeg', 'image/png', 'image/gif' );
	}
}
