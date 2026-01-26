<?php
/**
 * Media Engine Minio Service
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineMinioService {
	private $logger;

	public function __construct() {
		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-conversion-logger.php';
		}
		$this->logger = new MediaEngineConversionLogger();
	}

	public function upload( $attachment_id ) {
		$cmd = sprintf( 'wp advmo offload %d 2>&1', $attachment_id );
		exec( $cmd, $output, $return_code );
		$this->logger->log_command( $cmd, implode( "\n", $output ), $return_code );
		if ( $return_code === 0 ) {
			update_post_meta( $attachment_id, '_is_minio_offloaded', 1 );
			return [ 'success' => true, 'message' => 'Offloaded to Minio' ];
		}
		return [ 'success' => false, 'error' => implode( "\n", $output ) ];
	}

	public function upload_batch( $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return [ 'success' => true, 'count' => 0 ];
		}
		$ids_str = implode( ',', $attachment_ids );
		$cmd = sprintf( 'wp advmo offload %s 2>&1', $ids_str );
		exec( $cmd, $output, $return_code );
		$this->logger->log_command( $cmd, implode( "\n", $output ), $return_code );
		if ( $return_code === 0 ) {
			foreach ( $attachment_ids as $id ) {
				update_post_meta( $id, '_is_minio_offloaded', 1 );
			}
		}
		return [ 'success' => $return_code === 0, 'count' => count( $attachment_ids ) ];
	}

	public function is_available() {
		exec( 'wp advmo --help 2>&1', $output, $return_code );
		return $return_code === 0;
	}
}
