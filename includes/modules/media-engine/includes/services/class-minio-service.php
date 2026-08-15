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
			require_once plugin_dir_path( __DIR__ ) . 'class-logger-service.php';
		}
		$this->logger = new MediaEngineConversionLogger();
	}

	public function upload( $attachment_id ) {
		$cmd = sprintf( 'wp advmo offload %d 2>&1', $attachment_id );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- wp CLI invocation; attachment IDs are bound via absint/%d.
		exec( $cmd, $output, $return_code );
		$stats = $this->parse_offload_stats( $output, 1 );
		$this->logger->log_offload_result( array( $attachment_id ), $stats['success'], $stats['skip'], $stats['failed'] );
		if ( $return_code === 0 ) {
			update_post_meta( $attachment_id, '_is_minio_offloaded', 1 );
			return array(
				'success' => true,
				'message' => 'Offloaded to Minio',
			);
		}
		return array(
			'success' => false,
			'error'   => implode( "\n", $output ),
		);
	}

	public function upload_batch( $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return array(
				'success' => true,
				'count'   => 0,
			);
		}
		$ids_str = implode( ',', $attachment_ids );
		$cmd     = sprintf( 'wp advmo offload %s 2>&1', $ids_str );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- wp CLI invocation; attachment IDs are bound via absint/%d.
		exec( $cmd, $output, $return_code );
		$stats = $this->parse_offload_stats( $output, count( $attachment_ids ) );
		$this->logger->log_offload_result( $attachment_ids, $stats['success'], $stats['skip'], $stats['failed'] );
		if ( $return_code === 0 ) {
			foreach ( $attachment_ids as $id ) {
				update_post_meta( $id, '_is_minio_offloaded', 1 );
			}
		}
		return array(
			'success' => $return_code === 0,
			'count'   => count( $attachment_ids ),
		);
	}

	/**
	 * Parse statistics from the wp advmo offload output
	 *
	 * Supported format: Summary: 0 successful, 0 failed, 20 skipped out of 20 total.
	 * Falls back when parsing fails: success=0, skip=0, failed=total (fallback_total)
	 *
	 * @param array $output         Command output lines
	 * @param int   $fallback_total Total used when parsing fails (attachment count)
	 * @return array { success:int, skip:int, failed:int }
	 */
	private function parse_offload_stats( $output, $fallback_total = 0 ) {
		$output_text = implode( "\n", $output );
		$success     = 0;
		$skip        = 0;
		$failed      = $fallback_total;

		if ( preg_match( '/Summary:\s*(\d+) successful,\s*(\d+) failed,\s*(\d+) skipped/i', $output_text, $m ) ) {
			$success = (int) $m[1];
			$failed  = (int) $m[2];
			$skip    = (int) $m[3];
		}

		return array(
			'success' => $success,
			'skip'    => $skip,
			'failed'  => $failed,
		);
	}

	public function is_available() {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- wp CLI invocation; attachment IDs are bound via absint/%d.
		exec( 'wp advmo --help 2>&1', $output, $return_code );
		return $return_code === 0;
	}
}
