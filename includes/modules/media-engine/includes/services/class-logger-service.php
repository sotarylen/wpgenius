<?php
/**
 * Media Engine Conversion Logger
 *
 * Records detailed logs of all conversion operations
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Media_Conversion_Logger {

	/**
	 * Maximum log file size (5MB)
	 *
	 * @var int
	 */
	const MAX_LOG_SIZE = 5242880; // 5 * 1024 * 1024

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Constructor
	 */
	public function __construct() {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/wpgenius';

		// Ensure the log directory exists and is not directly accessible via the web.
		$this->ensure_log_dir( $log_dir );

		$this->log_file = $log_dir . '/conversion_optimized.log';

		// Migrate old log (previously in the uploads root directory, web-readable).
		$legacy_file = $upload_dir['basedir'] . '/conversion_optimized.log';
		if ( file_exists( $legacy_file ) ) {
			if ( ! file_exists( $this->log_file ) ) {
				@rename( $legacy_file, $this->log_file );
			} else {
				@unlink( $legacy_file );
			}
		}

		// Ensure the log file exists
		if ( ! file_exists( $this->log_file ) ) {
			touch( $this->log_file );
		}
	}

	/**
	 * Create a protected log directory (prevents directory listing + direct access).
	 *
	 * @param string $log_dir Absolute path of the log directory.
	 * @return void
	 */
	private function ensure_log_dir( $log_dir ) {
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Directory-listing sentinel file (prevents directory listing under Nginx/Apache).
		if ( ! file_exists( $log_dir . '/index.php' ) ) {
			@file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden.' );
		}

		// Reject direct access under Apache (compatible with 2.2 and 2.4).
		if ( ! file_exists( $log_dir . '/.htaccess' ) ) {
			@file_put_contents(
				$log_dir . '/.htaccess',
				"# Deny direct access to log files\n" .
				"<IfModule mod_authz_core.c>\n" .
				"Require all denied\n" .
				"</IfModule>\n" .
				"<IfModule !mod_authz_core.c>\n" .
				"Order deny,allow\n" .
				"Deny from all\n" .
				"</IfModule>\n"
			);
		}
	}


	/**
	 * Log conversion start
	 *
	 * @param int $attachment_id Attachment ID
	 * @param string $file_path File path
	 * @param int $file_size File size (bytes)
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
	 * Log conversion success
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $original_file Original file name
	 * @param string $webp_file WebP file name
	 * @param int    $original_size Original file size
	 * @param int    $webp_size WebP file size
	 * @param string $engine Engine used
	 * @param int    $quality Quality parameter
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
	 * Log command execution
	 *
	 * @param string $command Executed command
	 * @param string $output Command output
	 * @param int    $return_code Return code
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
	 * Log formatted conversion result (structured pipe format)
	 *
	 * Format: [timestamp] {engine} | {attachment_id} | {original filename} | {original size} | {new filename} | {new size} | OK|NG
	 *
	 * @param string $engine        Conversion engine (vips/gif2webp/cwebp)
	 * @param int    $attachment_id Attachment ID
	 * @param string $original_file Full path of the original file (basename shown)
	 * @param int    $original_size Original file size (bytes)
	 * @param string $new_file      Full path of the new file (basename shown)
	 * @param int    $new_size      New file size (bytes)
	 * @param bool   $success       Whether it succeeded
	 */
	public function log_conversion_result( $engine, $attachment_id, $original_file, $original_size, $new_file, $new_size, $success ) {
		$status  = $success ? 'OK' : 'NG';
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
	 * Log conversion failure
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $file_path File path
	 * @param string $error Error message
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
	 * Log skipped files
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $file_path File path
	 * @param string $reason Skip reason
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
	 * Log task chain step
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param string $step Step name
	 * @param bool   $success Whether it succeeded
	 * @param string $message Message
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
	 * Log batch processing start
	 *
	 * @param int $total Total count
	 * @param int $limit Batch size
	 */
	public function log_batch_start( $total, $limit ) {
		$separator = str_repeat( '=', 80 );
		$this->write_log( '' );
		$this->write_log( $separator );
		$this->write_log(
			sprintf(
				'[%s] >>> Batch Processing Started | Total Items: %d | Batch Size: %d',
				$this->get_timestamp(),
				$total,
				$limit
			)
		);
		$this->write_log( $separator );
	}

	/**
	 * Log batch processing completion
	 *
	 * @param int $processed Processed count
	 * @param int $success Success count
	 * @param int $failed Failed count
	 * @param float $duration Duration (seconds)
	 */
	public function log_batch_complete( $processed, $success, $failed, $duration ) {
		$separator = str_repeat( '=', 80 );
		$this->write_log( $separator );
		$this->write_log(
			sprintf(
				'[%s] <<< Batch Processing Completed | Processed: %d | Success: %d | Failed: %d | Duration: %.2fs',
				$this->get_timestamp(),
				$processed,
				$success,
				$failed,
				$duration
			)
		);
		$this->write_log( $separator );
		$this->write_log( '' );
	}

	/**
	 * Log thumbnail generation result (STEP2)
	 *
	 * Format:
	 * ===== STEP2: WP Media Regenerate =====
	 * [timestamp] WP Media Regenerate {id1 id2 ...}
	 * [timestamp] Success: X/total | Skip: Y/total | Failed: Z/total
	 *
	 * @param array $attachment_ids Attachment ID list (shown space-separated)
	 * @param int   $success Success count
	 * @param int   $skip    Skipped count
	 * @param int   $failed  Failed count
	 */
	public function log_thumbnail_result( $attachment_ids, $success, $skip, $failed ) {
		$total = count( $attachment_ids );
		$this->write_log( '===== STEP2: WP Media Regenerate =====' );
		$this->write_log(
			sprintf(
				'[%s] WP Media Regenerate %s',
				$this->get_timestamp(),
				implode( ' ', $attachment_ids )
			)
		);
		$this->write_log(
			sprintf(
				'[%s] Success: %d/%d | Skip: %d/%d | Failed: %d/%d',
				$this->get_timestamp(),
				$success,
				$total,
				$skip,
				$total,
				$failed,
				$total
			)
		);
	}

	/**
	 * Log Minio offload result (STEP3)
	 *
	 * Format:
	 * ===== STEP3: WP Offload to Minio =====
	 * [timestamp] WP Offload to Minio {id1 id2 ...}
	 * [timestamp] Success: X/total | Skip: Y/total | Failed: Z/total
	 *
	 * @param array $attachment_ids Attachment ID list (shown space-separated)
	 * @param int   $success Success count
	 * @param int   $skip    Skipped count
	 * @param int   $failed  Failed count
	 */
	public function log_offload_result( $attachment_ids, $success, $skip, $failed ) {
		$total = count( $attachment_ids );
		$this->write_log( '===== STEP3: WP Offload to Minio =====' );
		$this->write_log(
			sprintf(
				'[%s] WP Offload to Minio %s',
				$this->get_timestamp(),
				implode( ' ', $attachment_ids )
			)
		);
		$this->write_log(
			sprintf(
				'[%s] Success: %d/%d | Skip: %d/%d | Failed: %d/%d',
				$this->get_timestamp(),
				$success,
				$total,
				$skip,
				$total,
				$failed,
				$total
			)
		);
	}

	/**
	 * Log step header separator line
	 *
	 * Format: ===== {title} =====
	 *
	 * @param string $title Step title
	 */
	public function log_step_header( $title ) {
		$this->write_log( '===== ' . $title . ' =====' );
	}

	/**
	 * Log URL rewrite result (STEP4)
	 *
	 * Format: [timestamp] WP Rewrite Content URL | {attachment ID} | {parent ID} | {new URL} | OK|NG
	 *
	 * @param int    $attachment_id Attachment ID
	 * @param int    $post_parent   Parent post ID (0 if no parent)
	 * @param string $new_url       New URL after rewrite
	 * @param bool   $success       Whether it succeeded
	 */
	public function log_rewrite_result( $attachment_id, $post_parent, $new_url, $success ) {
		$status = $success ? 'OK' : 'NG';
		$this->write_log(
			sprintf(
				'[%s] WP Rewrite Content URL | %d | %d | %s | %s',
				$this->get_timestamp(),
				(int) $attachment_id,
				(int) $post_parent,
				$new_url,
				$status
			)
		);
	}

	/**
	 * Log source file cleanup result (STEP5)
	 *
	 * Format:
	 * ===== STEP5: Clean Origin Media =====
	 * [timestamp] Success: X/total | Skip: Y/total | Failed: Z/total
	 *
	 * @param array $attachment_ids Attachment ID list
	 * @param int   $success Success count
	 * @param int   $skip    Skipped count
	 * @param int   $failed  Failed count
	 */
	public function log_cleanup_result( $attachment_ids, $success, $skip, $failed ) {
		$total = count( $attachment_ids );
		$this->write_log( '===== STEP5: Clean Origin Media =====' );
		$this->write_log(
			sprintf(
				'[%s] Success: %d/%d | Skip: %d/%d | Failed: %d/%d',
				$this->get_timestamp(),
				$success,
				$total,
				$skip,
				$total,
				$failed,
				$total
			)
		);
	}

	/**
	 * Log generic debug/status information (plain timestamp style, consistent with log_command etc.)
	 *
	 * @param string $message Log message
	 */
	public function log_debug( $message ) {
		$this->write_log( '[' . $this->get_timestamp() . '] ' . $message );
	}

	/**
	 * Write log
	 *
	 * @param string $message Log message
	 */
	private function write_log( $message ) {
		$this->enforce_size_limit();
		// Log-write errors are suppressed so a logging failure never corrupts the AJAX JSON payload.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $this->log_file, $message . PHP_EOL, FILE_APPEND );
	}

	/**
	 * Limit log file size: truncate when exceeding MAX_LOG_SIZE, keeping roughly the most recent half
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
	 * Truncate the log: keep the tail of about MAX_LOG_SIZE/2 and write a truncation marker
	 *
	 * @param int $size Current file size (bytes)
	 */
	private function truncate_log( $size ) {
		$keep    = (int) ( self::MAX_LOG_SIZE / 2 );
		$content = '';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fseek needed to precisely read the file tail
		$handle = fopen( $this->log_file, 'rb' );
		if ( $handle ) {
			fseek( $handle, max( 0, $size - $keep ) );
			$content = fread( $handle, $keep );
			fclose( $handle );

			// Discard the first partial line, ensuring retained content starts on a complete line
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
	 * Adaptive file size formatting: show KB below 1MB, MB at >=1MB (1 decimal place)
	 *
	 * Note: does not use WP's size_format (its output has spaces like "800 KB"),
	 * Laolei requires a compact format like "800KB" / "1.5MB".
	 *
	 * @param int $bytes Byte count
	 * @return string Formatted size (e.g. 800KB / 1.5MB)
	 */
	private function format_size( $bytes ) {
		if ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576, 1 ) . 'MB';
		}
		return round( $bytes / 1024, 1 ) . 'KB';
	}

	/**
	 * Get timestamp
	 *
	 * @return string Formatted timestamp
	 */
	private function get_timestamp() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Get log file path
	 *
	 * @return string Log file path
	 */
	public function get_log_file() {
		return $this->log_file;
	}

	/**
	 * Clear the log file
	 */
	public function clear_log() {
		file_put_contents( $this->log_file, '' );
		$this->write_log(
			sprintf(
				'[%s] Log file cleared',
				$this->get_timestamp()
			)
		);
	}

	/**
	 * Get the most recent log lines
	 *
	 * @param int $lines Number of lines
	 * @return array Log line array
	 */
	public function get_recent_logs( $lines = 100 ) {
		if ( ! file_exists( $this->log_file ) ) {
			return array();
		}

		$file  = file( $this->log_file );
		$total = count( $file );

		if ( $total <= $lines ) {
			return $file;
		}

		return array_slice( $file, -$lines );
	}

	/**
	 * Efficiently read the tail of the log file (for the log viewer overlay polling, avoiding reading the whole file)
	 *
	 * @param int $bytes Number of bytes to read (default 64KB)
	 * @return string Log tail text (starting from a complete line)
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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fseek needed to precisely read the file tail
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

		// When reading from the middle of the file, discard the first partial line
		if ( $offset > 0 ) {
			$first_newline = strpos( $content, "\n" );
			if ( false !== $first_newline ) {
				$content = substr( $content, $first_newline + 1 );
			}
		}

		return $content;
	}

	/**
	 * Get log file size
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

		return array(
			'size_bytes'     => $size,
			'size_formatted' => size_format( $size, 2 ),
		);
	}
}

// Backward compatibility aliases.
if ( ! class_exists( 'MediaEngineConversionLogger', false ) ) {
	class_alias( 'W2P_Media_Conversion_Logger', 'MediaEngineConversionLogger' );
}
if ( ! class_exists( 'MediaEngineLoggerService', false ) ) {
	class_alias( 'W2P_Media_Conversion_Logger', 'MediaEngineLoggerService' );
}
