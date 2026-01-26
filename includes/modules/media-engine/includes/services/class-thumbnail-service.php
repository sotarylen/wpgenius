<?php
/**
 * Media Engine Thumbnail Service
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineThumbnailService {
	private $logger;

	public function __construct() {
		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-conversion-logger.php';
		}
		$this->logger = new MediaEngineConversionLogger();
	}

	public function regenerate( $attachment_id ) {
		$cmd = sprintf( 'wp media regenerate %d --only-missing --yes 2>&1', $attachment_id );
		exec( $cmd, $output, $return_code );
		$this->logger->log_command( $cmd, implode( "\n", $output ), $return_code );
		return [ 'success' => $return_code === 0, 'message' => implode( "\n", $output ) ];
	}

	public function regenerate_batch( $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return [ 'success' => true, 'count' => 0 ];
		}
		$ids_str = implode( ' ', $attachment_ids );
		$cmd = sprintf( 'wp media regenerate %s --only-missing --yes 2>&1', $ids_str );
		exec( $cmd, $output, $return_code );
		$this->logger->log_command( $cmd, implode( "\n", $output ), $return_code );
		return [ 'success' => $return_code === 0, 'count' => count( $attachment_ids ) ];
	}
}
