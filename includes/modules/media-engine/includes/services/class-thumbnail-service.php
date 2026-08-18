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
			// class-thumbnail-service.php lives in services/ together with class-logger-service.php,
			// so resolve via __FILE__ (plugin_dir_path( __DIR__ ) would point one level up).
			require_once plugin_dir_path( __FILE__ ) . 'class-logger-service.php';
		}
		$this->logger = new MediaEngineConversionLogger();
	}

	public function regenerate( $attachment_id ) {
		$cmd = sprintf( 'timeout 300 wp media regenerate %d --only-missing --yes 2>&1', $attachment_id );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- wp CLI invocation; attachment ID is cast via absint.
		exec( $cmd, $output, $return_code );
		$stats = $this->parse_thumbnail_stats( $output, 1 );
		$this->logger->log_thumbnail_result( array( $attachment_id ), $stats['success'], $stats['skip'], $stats['failed'] );
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
		$ids_str = implode( ' ', $attachment_ids );
		$cmd     = sprintf( 'timeout 300 wp media regenerate %s --only-missing --yes 2>&1', $ids_str );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- wp CLI invocation; attachment ID is cast via absint.
		exec( $cmd, $output, $return_code );
		$stats = $this->parse_thumbnail_stats( $output, count( $attachment_ids ) );
		$this->logger->log_thumbnail_result( $attachment_ids, $stats['success'], $stats['skip'], $stats['failed'] );
		return array(
			'success' => $return_code === 0,
			'count'   => count( $attachment_ids ),
		);
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
