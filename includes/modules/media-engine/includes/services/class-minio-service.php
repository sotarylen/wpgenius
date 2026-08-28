<?php
/**
 * Media Engine Minio Service
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Media_Minio_Service {
	private $logger;

	public function __construct() {
		if ( ! class_exists( 'W2P_Media_Conversion_Logger' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-logger-service.php';
		}
		if ( class_exists( 'W2P_Media_Conversion_Logger' ) ) {
			$this->logger = new W2P_Media_Conversion_Logger();
		} elseif ( class_exists( 'MediaEngineConversionLogger' ) ) {
			$this->logger = new MediaEngineConversionLogger();
		}
	}

	public function upload( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$cmd           = sprintf( 'wp advmo offload %d 2>&1', $attachment_id );
		if ( $this->has_timeout_command() ) {
			$cmd = 'timeout 600 ' . $cmd;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- wp CLI invocation; attachment IDs are bound via absint/%d.
		exec( $cmd, $output, $return_code );
		$stats = $this->parse_offload_stats( $output, 1 );
		if ( $this->logger ) {
			$this->logger->log_offload_result( array( $attachment_id ), $stats['success'], $stats['skip'], $stats['failed'] );
		}
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
		$clean_ids = array_map( 'absint', $attachment_ids );
		$ids_str   = implode( ',', $clean_ids );
		$cmd       = sprintf( 'wp advmo offload %s 2>&1', $ids_str );
		if ( $this->has_timeout_command() ) {
			$cmd = 'timeout 600 ' . $cmd;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- wp CLI invocation; attachment IDs are bound via absint/%d.
		exec( $cmd, $output, $return_code );
		$stats = $this->parse_offload_stats( $output, count( $clean_ids ) );
		if ( $this->logger ) {
			$this->logger->log_offload_result( $clean_ids, $stats['success'], $stats['skip'], $stats['failed'] );
		}
		if ( $return_code === 0 ) {
			foreach ( $clean_ids as $id ) {
				update_post_meta( $id, '_is_minio_offloaded', 1 );
			}
		}
		return array(
			'success' => $return_code === 0,
			'count'   => count( $clean_ids ),
		);
	}

	private function has_timeout_command() {
		static $has_timeout = null;
		if ( null === $has_timeout ) {
			$check_output = array();
			$return_code  = 0;
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( 'which timeout 2>&1', $check_output, $return_code );
			$has_timeout = ( 0 === $return_code && ! empty( $check_output[0] ) );
		}
		return $has_timeout;
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
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'advanced-media-offloader/advanced-media-offloader.php' );
	}
}

// Backward compatibility alias.
if ( ! class_exists( 'MediaEngineMinioService', false ) ) {
	class_alias( 'W2P_Media_Minio_Service', 'MediaEngineMinioService' );
}
