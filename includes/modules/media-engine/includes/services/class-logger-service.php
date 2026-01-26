<?php
/**
 * Media Engine Conversion Logger
 *
 * 记录所有转换操作的详细日志
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineConversionLogger {

	/**
	 * 日志文件路径
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * 构造函数
	 */
	public function __construct() {
		$upload_dir     = wp_upload_dir();
		$this->log_file = $upload_dir['basedir'] . '/conversion_optimized.log';

		// 确保日志文件存在
		if ( ! file_exists( $this->log_file ) ) {
			touch( $this->log_file );
		}
	}

	/**
	 * 记录转换开始
	 *
	 * @param int $attachment_id 附件 ID
	 * @param string $file_path 文件路径
	 * @param int $file_size 文件大小(字节)
	 */
	public function log_conversion_start( $attachment_id, $file_path, $file_size ) {
		$size_mb  = round( $file_size / 1024 / 1024, 2 );
		$filename = basename( $file_path );
		
		$message = sprintf(
			'[%s] >>> Starting conversion for ID: %d | File: %s | Size: %.2fMB (%d bytes)',
			$this->get_timestamp(),
			$attachment_id,
			$filename,
			$size_mb,
			$file_size
		);

		$this->write_log( $message );
	}

	/**
	 * 记录转换成功
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param string $original_file 原文件名
	 * @param string $webp_file WebP 文件名
	 * @param int    $original_size 原文件大小
	 * @param int    $webp_size WebP 文件大小
	 * @param string $engine 使用的引擎
	 * @param int    $quality 质量参数
	 */
	public function log_conversion_success( $attachment_id, $original_file, $webp_file, $original_size, $webp_size, $engine, $quality ) {
		$ratio = 0;
		if ( $original_size > 0 ) {
			$ratio = round( ( ( $original_size - $webp_size ) / $original_size ) * 100, 2 );
		}

		$message = sprintf(
			'[%s] ✓ SUCCESS: ID: %d | Original: %.2fMB (%s) | Converted: %.2fMB (%s) | Ratio: %s%% | Engine: %s | Quality: %d%%',
			$this->get_timestamp(),
			$attachment_id,
			$original_size / 1024 / 1024,
			basename( $original_file ),
			$webp_size / 1024 / 1024,
			basename( $webp_file ),
			$ratio,
			$engine,
			$quality
		);

		$this->write_log( $message );
	}

	/**
	 * 记录命令执行
	 *
	 * @param string $command 执行的命令
	 * @param string $output 命令输出
	 * @param int    $return_code 返回码
	 */
	public function log_command( $command, $output = '', $return_code = 0 ) {
		$message = sprintf(
			'[%s] Command: %s | Return Code: %d',
			$this->get_timestamp(),
			$command,
			$return_code
		);

		$this->write_log( $message );

		if ( ! empty( $output ) ) {
			$this->write_log( '[' . $this->get_timestamp() . '] Output: ' . $output );
		}
	}

	/**
	 * 记录转换失败
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param string $file_path 文件路径
	 * @param string $error 错误信息
	 */
	public function log_conversion_error( $attachment_id, $file_path, $error ) {
		$message = sprintf(
			'[%s] ✗ FAILED: ID: %d | File: %s | Error: %s',
			$this->get_timestamp(),
			$attachment_id,
			basename( $file_path ),
			$error
		);

		$this->write_log( $message );
	}

	/**
	 * 记录跳过的文件
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param string $file_path 文件路径
	 * @param string $reason 跳过原因
	 */
	public function log_skipped( $attachment_id, $file_path, $reason ) {
		$message = sprintf(
			'[%s] ⊘ SKIPPED: ID: %d | File: %s | Reason: %s',
			$this->get_timestamp(),
			$attachment_id,
			basename( $file_path ),
			$reason
		);

		$this->write_log( $message );
	}

	/**
	 * 记录任务链步骤
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param string $step 步骤名称
	 * @param bool   $success 是否成功
	 * @param string $message 消息
	 */
	public function log_chain_step( $attachment_id, $step, $success, $message = '' ) {
		$status = $success ? '✓' : '✗';
		$log    = sprintf(
			'[%s] %s Step [%s]: ID: %d | %s',
			$this->get_timestamp(),
			$status,
			$step,
			$attachment_id,
			$message
		);

		$this->write_log( $log );
	}

	/**
	 * 记录批处理开始
	 *
	 * @param int $total 总数
	 * @param int $limit 批次大小
	 */
	public function log_batch_start( $total, $limit ) {
		$separator = str_repeat( '=', 80 );
		$this->write_log( '' );
		$this->write_log( $separator );
		$this->write_log( sprintf(
			'[%s] >>> Batch Processing Started | Total Items: %d | Batch Size: %d',
			$this->get_timestamp(),
			$total,
			$limit
		) );
		$this->write_log( $separator );
	}

	/**
	 * 记录批处理完成
	 *
	 * @param int $processed 已处理数量
	 * @param int $success 成功数量
	 * @param int $failed 失败数量
	 * @param float $duration 耗时(秒)
	 */
	public function log_batch_complete( $processed, $success, $failed, $duration ) {
		$separator = str_repeat( '=', 80 );
		$this->write_log( $separator );
		$this->write_log( sprintf(
			'[%s] <<< Batch Processing Completed | Processed: %d | Success: %d | Failed: %d | Duration: %.2fs',
			$this->get_timestamp(),
			$processed,
			$success,
			$failed,
			$duration
		) );
		$this->write_log( $separator );
		$this->write_log( '' );
	}

	/**
	 * 写入日志
	 *
	 * @param string $message 日志消息
	 */
	private function write_log( $message ) {
		file_put_contents( $this->log_file, $message . PHP_EOL, FILE_APPEND );
	}

	/**
	 * 获取时间戳
	 *
	 * @return string 格式化的时间戳
	 */
	private function get_timestamp() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * 获取日志文件路径
	 *
	 * @return string 日志文件路径
	 */
	public function get_log_file() {
		return $this->log_file;
	}

	/**
	 * 清空日志文件
	 */
	public function clear_log() {
		file_put_contents( $this->log_file, '' );
		$this->write_log( sprintf(
			'[%s] Log file cleared',
			$this->get_timestamp()
		) );
	}

	/**
	 * 获取最近的日志行
	 *
	 * @param int $lines 行数
	 * @return array 日志行数组
	 */
	public function get_recent_logs( $lines = 100 ) {
		if ( ! file_exists( $this->log_file ) ) {
			return [];
		}

		$file  = file( $this->log_file );
		$total = count( $file );

		if ( $total <= $lines ) {
			return $file;
		}

		return array_slice( $file, -$lines );
	}
}
