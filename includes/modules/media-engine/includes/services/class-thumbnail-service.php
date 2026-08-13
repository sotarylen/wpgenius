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
			require_once plugin_dir_path( __DIR__ ) . 'class-logger-service.php';
		}
		$this->logger = new MediaEngineConversionLogger();
	}

	public function regenerate( $attachment_id ) {
		$cmd = sprintf( 'wp media regenerate %d --only-missing --yes 2>&1', $attachment_id );
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
		$cmd     = sprintf( 'wp media regenerate %s --only-missing --yes 2>&1', $ids_str );
		exec( $cmd, $output, $return_code );
		$stats = $this->parse_thumbnail_stats( $output, count( $attachment_ids ) );
		$this->logger->log_thumbnail_result( $attachment_ids, $stats['success'], $stats['skip'], $stats['failed'] );
		return array(
			'success' => $return_code === 0,
			'count'   => count( $attachment_ids ),
		);
	}

	/**
	 * 解析 wp media regenerate 输出中的统计信息
	 *
	 * 支持格式：Success: Regenerated 20 of 20 images. / Skipped: N
	 * 无法解析时降级：success=0、skip=0、failed=总数（fallback_total）
	 *
	 * @param array $output         命令输出行
	 * @param int   $fallback_total 无法解析时使用的总数（附件数）
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
