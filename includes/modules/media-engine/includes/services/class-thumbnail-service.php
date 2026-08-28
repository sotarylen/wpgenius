<?php
/**
 * Media Engine Thumbnail Service
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Media_Thumbnail_Service {
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

	public function regenerate( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$cmd           = sprintf( 'wp media regenerate %d --only-missing --yes 2>&1', $attachment_id );
		if ( $this->has_timeout_command() ) {
			$cmd = 'timeout 300 ' . $cmd;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- wp CLI invocation; attachment ID is cast via absint.
		exec( $cmd, $output, $return_code );
		$stats = $this->parse_thumbnail_stats( $output, 1 );
		if ( $this->logger ) {
			$this->logger->log_thumbnail_result( array( $attachment_id ), $stats['success'], $stats['skip'], $stats['failed'] );
		}
		return array(
			'success' => $return_code === 0,
			'message' => implode( "\n", $output ),
		);
	}

	public function regenerate_batch( $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return array(
				'success' => true,
				'count'   => 0,
			);
		}
		$clean_ids = array_map( 'absint', $attachment_ids );
		$ids_str   = implode( ' ', $clean_ids );
		$cmd       = sprintf( 'wp media regenerate %s --only-missing --yes 2>&1', $ids_str );
		if ( $this->has_timeout_command() ) {
			$cmd = 'timeout 300 ' . $cmd;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- wp CLI invocation; attachment ID is cast via absint.
		exec( $cmd, $output, $return_code );
		$stats = $this->parse_thumbnail_stats( $output, count( $clean_ids ) );
		if ( $this->logger ) {
			$this->logger->log_thumbnail_result( $clean_ids, $stats['success'], $stats['skip'], $stats['failed'] );
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
	 * Parse statistics from the wp media regenerate output
	 *
	 * Supported format: Success: Regenerated 20 of 20 images. / Skipped: N
	 * Falls back when parsing fails: success=0, skip=0, failed=total (fallback_total)
	 *
	 * @param array $output         Command output lines
	 * @param int   $fallback_total Total used when parsing fails (attachment count)
	 * @return array { success:int, skip:int, failed:int }
	 */
	private function parse_thumbnail_stats( $output, $fallback_total = 0 ) {
		$output_text = implode( "\n", $output );
		$success     = 0;
		$skip        = 0;
		$total       = $fallback_total;

		if ( preg_match( '/Success: Regenerated (\d+) of (\d+) images/i', $output_text, $m ) ) {
			$success = (int) $m[1];
			$total   = (int) $m[2];
		}
		if ( preg_match( '/Skipped:? (\d+)/i', $output_text, $m ) ) {
			$skip = (int) $m[1];
		}

		$failed = max( 0, $total - $success - $skip );

		return array(
			'success' => $success,
			'skip'    => $skip,
			'failed'  => $failed,
		);
	}
}

// Backward compatibility alias.
if ( ! class_exists( 'MediaEngineThumbnailService', false ) ) {
	class_alias( 'W2P_Media_Thumbnail_Service', 'MediaEngineThumbnailService' );
}
