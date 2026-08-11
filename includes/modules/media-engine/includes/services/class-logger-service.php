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
	 * 日志文件最大尺寸（5MB）
	 *
	 * @var int
	 */
	const MAX_LOG_SIZE = 5242880; // 5 * 1024 * 1024

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
	 * 记录格式化转换结果（结构化管道格式）
	 *
	 * 格式: [时间戳] {engine} | {attachment_id} | {原文件名} | {原大小} | {新文件名} | {新大小} | OK|NG
	 *
	 * @param string $engine        转换引擎（vips/gif2webp/cwebp）
	 * @param int    $attachment_id 附件 ID
	 * @param string $original_file 原文件完整路径（取 basename 展示）
	 * @param int    $original_size 原文件大小（字节）
	 * @param string $new_file      新文件完整路径（取 basename 展示）
	 * @param int    $new_size      新文件大小（字节）
	 * @param bool   $success       是否成功
	 */
	public function log_conversion_result( $engine, $attachment_id, $original_file, $original_size, $new_file, $new_size, $success ) {
		$status = $success ? 'OK' : 'NG';
		$message = sprintf(
			'[%s] %s | %d | %s | %s | %s | %s | %s',
			$this->get_timestamp(),
			$engine,
			(int) $attachment_id,
			basename( $original_file ),
			$this->format_size( $original_size ),
			basename( $new_file ),
			$this->format_size( $new_size ),
			$status
		);
		$this->write_log( $message );
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
	 * 记录缩略图生成结果（STEP2）
	 *
	 * 格式:
	 * ===== STEP2: WP Media Regenerate =====
	 * [时间戳] WP Media Regenerate {id1 id2 ...}
	 * [时间戳] Success: X/total | Skip: Y/total | Failed: Z/total
	 *
	 * @param array $attachment_ids 附件 ID 列表（空格分隔展示）
	 * @param int   $success 成功数量
	 * @param int   $skip    跳过数量
	 * @param int   $failed  失败数量
	 */
	public function log_thumbnail_result( $attachment_ids, $success, $skip, $failed ) {
		$total = count( $attachment_ids );
		$this->write_log( '===== STEP2: WP Media Regenerate =====' );
		$this->write_log( sprintf(
			'[%s] WP Media Regenerate %s',
			$this->get_timestamp(),
			implode( ' ', $attachment_ids )
		) );
		$this->write_log( sprintf(
			'[%s] Success: %d/%d | Skip: %d/%d | Failed: %d/%d',
			$this->get_timestamp(),
			$success, $total, $skip, $total, $failed, $total
		) );
	}

	/**
	 * 记录 Minio Offload 结果（STEP3）
	 *
	 * 格式:
	 * ===== STEP3: WP Offload to Minio =====
	 * [时间戳] WP Offload to Minio {id1 id2 ...}
	 * [时间戳] Success: X/total | Skip: Y/total | Failed: Z/total
	 *
	 * @param array $attachment_ids 附件 ID 列表（空格分隔展示）
	 * @param int   $success 成功数量
	 * @param int   $skip    跳过数量
	 * @param int   $failed  失败数量
	 */
	public function log_offload_result( $attachment_ids, $success, $skip, $failed ) {
		$total = count( $attachment_ids );
		$this->write_log( '===== STEP3: WP Offload to Minio =====' );
		$this->write_log( sprintf(
			'[%s] WP Offload to Minio %s',
			$this->get_timestamp(),
			implode( ' ', $attachment_ids )
		) );
		$this->write_log( sprintf(
			'[%s] Success: %d/%d | Skip: %d/%d | Failed: %d/%d',
			$this->get_timestamp(),
			$success, $total, $skip, $total, $failed, $total
		) );
	}

	/**
	 * 记录步骤标题分隔行
	 *
	 * 格式: ===== {title} =====
	 *
	 * @param string $title 步骤标题
	 */
	public function log_step_header( $title ) {
		$this->write_log( '===== ' . $title . ' =====' );
	}

	/**
	 * 记录 URL 重写结果（STEP4）
	 *
	 * 格式: [时间戳] WP Rewrite Content URL | {附件ID} | {父级ID} | {新地址} | OK|NG
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param int    $post_parent   父级文章 ID（无父级为 0）
	 * @param string $new_url       重写后的新地址
	 * @param bool   $success       是否成功
	 */
	public function log_rewrite_result( $attachment_id, $post_parent, $new_url, $success ) {
		$status = $success ? 'OK' : 'NG';
		$this->write_log( sprintf(
			'[%s] WP Rewrite Content URL | %d | %d | %s | %s',
			$this->get_timestamp(),
			(int) $attachment_id,
			(int) $post_parent,
			$new_url,
			$status
		) );
	}

	/**
	 * 记录源文件清理结果（STEP5）
	 *
	 * 格式:
	 * ===== STEP5: Clean Origin Media =====
	 * [时间戳] Success: X/total | Skip: Y/total | Failed: Z/total
	 *
	 * @param array $attachment_ids 附件 ID 列表
	 * @param int   $success 成功数量
	 * @param int   $skip    跳过数量
	 * @param int   $failed  失败数量
	 */
	public function log_cleanup_result( $attachment_ids, $success, $skip, $failed ) {
		$total = count( $attachment_ids );
		$this->write_log( '===== STEP5: Clean Origin Media =====' );
		$this->write_log( sprintf(
			'[%s] Success: %d/%d | Skip: %d/%d | Failed: %d/%d',
			$this->get_timestamp(),
			$success, $total, $skip, $total, $failed, $total
		) );
	}

	/**
	 * 记录通用调试/状态信息（纯时间戳风格，与 log_command 等一致）
	 *
	 * @param string $message 日志消息
	 */
	public function log_debug( $message ) {
		$this->write_log( '[' . $this->get_timestamp() . '] ' . $message );
	}

	/**
	 * 写入日志
	 *
	 * @param string $message 日志消息
	 */
	private function write_log( $message ) {
		$this->enforce_size_limit();
		file_put_contents( $this->log_file, $message . PHP_EOL, FILE_APPEND );
	}

	/**
	 * 限制日志文件尺寸：超过 MAX_LOG_SIZE 时截断，保留最新约一半内容
	 */
	private function enforce_size_limit() {
		if ( ! file_exists( $this->log_file ) ) {
			return;
		}

		clearstatcache( true, $this->log_file );
		$size = filesize( $this->log_file );

		if ( false === $size || $size < self::MAX_LOG_SIZE ) {
			return;
		}

		$this->truncate_log( $size );
	}

	/**
	 * 截断日志：保留文件尾部约 MAX_LOG_SIZE/2 的内容，并写入截断标记
	 *
	 * @param int $size 当前文件尺寸（字节）
	 */
	private function truncate_log( $size ) {
		$keep    = (int) ( self::MAX_LOG_SIZE / 2 );
		$content = '';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- 需要 fseek 精确读取文件尾部
		$handle = fopen( $this->log_file, 'rb' );
		if ( $handle ) {
			fseek( $handle, max( 0, $size - $keep ) );
			$content = fread( $handle, $keep );
			fclose( $handle );

			// 丢弃首行残行，保证保留内容从完整行开始
			if ( false !== $content ) {
				$first_newline = strpos( $content, "\n" );
				if ( false !== $first_newline ) {
					$content = substr( $content, $first_newline + 1 );
				}
			} else {
				$content = '';
			}
		}

		$marker = sprintf(
			'[%s] --- Log truncated: exceeded %s, oldest entries removed, keeping most recent ---',
			$this->get_timestamp(),
			size_format( self::MAX_LOG_SIZE )
		);

		file_put_contents( $this->log_file, $marker . PHP_EOL . $content, LOCK_EX );
	}

	/**
	 * 文件大小自适应格式化：<1MB 显示 KB，>=1MB 显示 MB（保留 1 位小数）
	 *
	 * 注意：不使用 WP 的 size_format（其输出带空格如 "800 KB"），
	 * 老雷要求紧凑格式 "800KB" / "1.5MB"。
	 *
	 * @param int $bytes 字节数
	 * @return string 格式化后的大小（如 800KB / 1.5MB）
	 */
	private function format_size( $bytes ) {
		if ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576, 1 ) . 'MB';
		}
		return round( $bytes / 1024, 1 ) . 'KB';
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

	/**
	 * 高效读取日志文件尾部内容（用于日志查看浮层的轮询，避免读取整个文件）
	 *
	 * @param int $bytes 读取的字节数（默认 64KB）
	 * @return string 日志尾部文本（从完整行开始）
	 */
	public function get_log_tail( $bytes = 65536 ) {
		if ( ! file_exists( $this->log_file ) ) {
			return '';
		}

		clearstatcache( true, $this->log_file );
		$size = filesize( $this->log_file );

		if ( false === $size || 0 === $size ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- 需要 fseek 精确读取文件尾部
		$handle = fopen( $this->log_file, 'rb' );
		if ( ! $handle ) {
			return '';
		}

		$offset = max( 0, $size - $bytes );
		fseek( $handle, $offset );
		$content = fread( $handle, $size - $offset );
		fclose( $handle );

		if ( false === $content ) {
			return '';
		}

		// 从文件中部开始读取时，丢弃首行残行
		if ( $offset > 0 ) {
			$first_newline = strpos( $content, "\n" );
			if ( false !== $first_newline ) {
				$content = substr( $content, $first_newline + 1 );
			}
		}

		return $content;
	}

	/**
	 * 获取日志文件尺寸
	 *
	 * @return array { size_bytes: int, size_formatted: string }
	 */
	public function get_log_size() {
		$size = 0;
		if ( file_exists( $this->log_file ) ) {
			clearstatcache( true, $this->log_file );
			$filesize = filesize( $this->log_file );
			if ( false !== $filesize ) {
				$size = $filesize;
			}
		}

		return [
			'size_bytes'     => $size,
			'size_formatted' => size_format( $size, 2 ),
		];
	}
}
