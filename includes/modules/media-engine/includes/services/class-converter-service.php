<?php
/**
 * Media Engine Converter Service
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineConverterService {
	/**
	 * Logger instance
	 *
	 * @var MediaEngineLoggerService
	 */
	private $logger;

	/**
	 * Available engines
	 *
	 * @var array
	 */
	private $available_engines = array();

	public function __construct() {
		// Initialize Logger
		if ( ! class_exists( 'MediaEngineLoggerService' ) ) {
			// Try loading from services/
			$logger_path = plugin_dir_path( __FILE__ ) . 'class-logger-service.php';
			if ( file_exists( $logger_path ) ) {
				require_once $logger_path;
			}
		}

		if ( class_exists( 'MediaEngineConversionLogger' ) ) {
			$this->logger = new MediaEngineConversionLogger();
		} elseif ( class_exists( 'MediaEngineLoggerService' ) ) {
			// Handle renamed class if applicable, or keep original name in new file
			// Based on previous move, class name inside file might still be MediaEngineConversionLogger
			$this->logger = new MediaEngineConversionLogger();
		}

		$this->detect_engines();
	}

	/**
	 * Detect available conversion engines
	 */
	private function detect_engines() {
		// Detect vips
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- System tool invocation (vips/cwebp); file paths pass through escapeshellarg.
		exec( 'vips --version 2>&1', $output, $return_code );
		if ( $return_code === 0 ) {
			$this->available_engines['vips'] = true;
		}

		// Detect cwebp
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- System tool invocation (vips/cwebp); file paths pass through escapeshellarg.
		exec( 'cwebp -version 2>&1', $output, $return_code );
		if ( $return_code === 0 ) {
			$this->available_engines['cwebp'] = true;
		}

		// Detect gif2webp
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- System tool invocation (vips/cwebp); file paths pass through escapeshellarg.
		exec( 'gif2webp -version 2>&1', $output, $return_code );
		if ( $return_code === 0 ) {
			$this->available_engines['gif2webp'] = true;
		}
	}

	/**
	 * Convert attachment to WebP
	 *
	 * @param int $attachment_id Attachment ID
	 * @return array Result
	 */
	public function convert_to_webp( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'error'   => 'File not found',
			);
		}

		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( $ext === 'webp' ) {
			return array(
				'success'     => true,
				'message'     => 'Already WebP',
				'output_path' => $file_path,
				'skipped'     => true,
			);
		}

		return $this->convert_file_to_webp( $file_path, null, $attachment_id );
	}

	/**
	 * Convert file to WebP (Core Logic)
	 *
	 * @param string      $file_path Source file path
	 * @param string|null $output_path Output path (optional)
	 * @param int         $attachment_id Attachment ID (used to format the conversion result log)
	 * @return array Result
	 */
	public function convert_file_to_webp( $file_path, $output_path = null, $attachment_id = 0 ) {
		if ( ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Source file does not exist', 'wp-genius' ),
			);
		}

		if ( $output_path === null ) {
			$output_path = $this->get_webp_path( $file_path );
		}

		$mime_type = mime_content_type( $file_path );
		$quality   = $this->calculate_quality( $file_path, $mime_type );

		if ( strpos( $mime_type, 'gif' ) !== false ) {
			return $this->convert_gif( $file_path, $output_path, $quality, $attachment_id );
		} else {
			return $this->convert_static( $file_path, $output_path, $quality, $attachment_id );
		}
	}

	/**
	 * Convert GIF to WebP
	 *
	 * @param string $file_path Source file path
	 * @param string $output_path Output path
	 * @param int    $quality Quality
	 * @param int    $attachment_id Attachment ID (used to format the conversion result log)
	 */
	private function convert_gif( $file_path, $output_path, $quality, $attachment_id = 0 ) {
		if ( ! isset( $this->available_engines['gif2webp'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'gif2webp is not available', 'wp-genius' ),
			);
		}

		$file_size_mb = filesize( $file_path ) / 1024 / 1024;
		if ( $file_size_mb <= 1 ) {
			$compression_method = 4;
			$mixed_mode         = '';
		} elseif ( $file_size_mb <= 5 ) {
			$compression_method = 5;
			$mixed_mode         = '-mixed';
		} else {
			$compression_method = 6;
			$mixed_mode         = '-mixed';
		}

		$command = sprintf(
			'gif2webp -q %d -m %d %s -kmin 150 -kmax 200 %s -o %s 2>&1',
			$quality,
			$compression_method,
			$mixed_mode,
			escapeshellarg( $file_path ),
			escapeshellarg( $output_path )
		);

		return $this->execute_command( $command, $output_path, 'gif2webp', $quality, $attachment_id, $file_path );
	}

	/**
	 * Convert Static Image to WebP
	 *
	 * @param string $file_path Source file path
	 * @param string $output_path Output path
	 * @param int    $quality Quality
	 * @param int    $attachment_id Attachment ID (used to format the conversion result log)
	 */
	private function convert_static( $file_path, $output_path, $quality, $attachment_id = 0 ) {
		// Try vips first
		if ( isset( $this->available_engines['vips'] ) ) {
			$command = sprintf(
				'vips copy %s %s 2>&1',
				escapeshellarg( $file_path ),
				escapeshellarg( $output_path . '[Q=' . $quality . ',lossless=false]' )
			);
			$result  = $this->execute_command( $command, $output_path, 'vips', $quality, $attachment_id, $file_path );
			if ( $result['success'] ) {
				return $result;
			}
		}

		// Fallback to cwebp
		if ( isset( $this->available_engines['cwebp'] ) ) {
			$command = sprintf(
				'cwebp -q %d -m 4 %s -o %s 2>&1',
				$quality,
				escapeshellarg( $file_path ),
				escapeshellarg( $output_path )
			);
			return $this->execute_command( $command, $output_path, 'cwebp', $quality, $attachment_id, $file_path );
		}

		return array(
			'success' => false,
			'error'   => __( 'No conversion engine available', 'wp-genius' ),
		);
	}

	/**
	 * Execute command wrapper
	 *
	 * @param string $command Executed command
	 * @param string $output_path Output file path
	 * @param string $engine Conversion engine
	 * @param int    $quality Quality parameter
	 * @param int    $attachment_id Attachment ID (used to format the conversion result log)
	 * @param string $original_file Full path of the original file (used to calculate the original size and extract the file name)
	 * @return array Result
	 */
	private function execute_command( $command, $output_path, $engine, $quality, $attachment_id = 0, $original_file = '' ) {
		$output      = array();
		$return_code = 0;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- System tool invocation (vips/cwebp); file paths pass through escapeshellarg.
		exec( $command, $output, $return_code );

		$output_str = implode( "\n", $output );
		if ( $this->logger ) {
			$this->logger->log_conversion_result(
				$engine,
				(int) $attachment_id,
				$original_file,
				(int) @filesize( $original_file ),
				$output_path,
				(int) @filesize( $output_path ),
				( 0 === $return_code && file_exists( $output_path ) )
			);
		}

		if ( $return_code === 0 && file_exists( $output_path ) ) {
			return array(
				'success'     => true,
				'output_path' => $output_path,
				'engine'      => $engine,
				'quality'     => $quality,
			);
		}

		return array(
			'success' => false,
			'error'   => ! empty( $output_str ) ? $output_str : __( 'Conversion failed', 'wp-genius' ),
			'engine'  => $engine,
		);
	}

	/**
	 * Calculate quality based on file size
	 */
	private function calculate_quality( $file_path, $mime_type ) {
		$size_mb = filesize( $file_path ) / 1024 / 1024;
		if ( strpos( $mime_type, 'gif' ) !== false ) {
			if ( $size_mb > 10 ) {
				return 20;
			} elseif ( $size_mb > 5 ) {
				return 30;
			} else {
				return 50;
			}
		} elseif ( $size_mb > 10 ) {
				return 50;
		} elseif ( $size_mb > 5 ) {
			return 60;
		} else {
			return 75;
		}
	}

	/**
	 * Get WebP output path
	 */
	private function get_webp_path( $file_path ) {
		$dir       = dirname( $file_path );
		$filename  = pathinfo( $file_path, PATHINFO_FILENAME );
		$ext       = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$webp_path = $dir . '/' . $filename . '.webp';

		// Handle naming conflicts if not GIF
		if ( $ext !== 'gif' ) {
			$gif_path      = $dir . '/' . $filename . '.gif';
			$gif_webp_path = $dir . '/' . $filename . '.webp';
			if ( file_exists( $gif_path ) || file_exists( $gif_webp_path ) ) {
				$webp_path = $dir . '/' . $filename . '-static.webp';
			}
		}
		return $webp_path;
	}

	public function get_logger() {
		return $this->logger;
	}
}
