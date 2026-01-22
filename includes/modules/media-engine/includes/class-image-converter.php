<?php
/**
 * Media Engine Image Converter
 *
 * 封装所有外部命令调用,负责图片转换
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineImageConverter {

	/**
	 * 日志记录器
	 *
	 * @var MediaEngineConversionLogger
	 */
	private $logger;

	/**
	 * 可用的引擎
	 *
	 * @var array
	 */
	private $available_engines = [];

	/**
	 * 构造函数
	 */
	public function __construct() {
		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-conversion-logger.php';
		}
		$this->logger = new MediaEngineConversionLogger();
		$this->detect_engines();
	}

	/**
	 * 检测可用的转换引擎
	 */
	private function detect_engines() {
		// 检测 vips
		exec( 'vips --version 2>&1', $output, $return_code );
		if ( $return_code === 0 ) {
			$this->available_engines['vips'] = true;
		}

		// 检测 cwebp
		exec( 'cwebp -version 2>&1', $output, $return_code );
		if ( $return_code === 0 ) {
			$this->available_engines['cwebp'] = true;
		}

		// 检测 gif2webp
		exec( 'gif2webp -version 2>&1', $output, $return_code );
		if ( $return_code === 0 ) {
			$this->available_engines['gif2webp'] = true;
		}
	}

	/**
	 * 转换图片到 WebP
	 *
	 * @param string $file_path 源文件路径
	 * @param string $output_path 输出文件路径(可选)
	 * @return array 转换结果 ['success' => bool, 'output_path' => string, 'engine' => string, 'quality' => int, 'error' => string]
	 */
	public function convert_to_webp( $file_path, $output_path = null ) {
		if ( ! file_exists( $file_path ) ) {
			return [
				'success' => false,
				'error'   => __( 'Source file does not exist', 'wp-genius' ),
			];
		}

		// 确定输出路径
		if ( $output_path === null ) {
			$output_path = $this->get_webp_path( $file_path );
		}

		// 获取文件信息
		$mime_type = mime_content_type( $file_path );
		$file_size = filesize( $file_path );

		// 计算质量参数
		$quality = $this->calculate_quality( $file_path, $mime_type );

		// 选择引擎并执行转换
		if ( strpos( $mime_type, 'gif' ) !== false ) {
			$result = $this->convert_gif( $file_path, $output_path, $quality );
		} else {
			$result = $this->convert_static( $file_path, $output_path, $quality );
		}

		return $result;
	}

	/**
	 * 转换 GIF 到 WebP
	 *
	 * @param string $file_path 源文件路径
	 * @param string $output_path 输出路径
	 * @param int    $quality 质量参数
	 * @return array 转换结果
	 */
	private function convert_gif( $file_path, $output_path, $quality ) {
		if ( ! isset( $this->available_engines['gif2webp'] ) ) {
			return [
				'success' => false,
				'error'   => __( 'gif2webp is not available', 'wp-genius' ),
			];
		}

		$file_size_mb = filesize( $file_path ) / 1024 / 1024;

		// 根据文件大小选择压缩方法和混合模式
		if ( $file_size_mb <= 1 ) {
			$compression_method = 4;
			$mixed_mode         = '';
		} elseif ( $file_size_mb <= 5 ) {
			$compression_method = 5;
			$mixed_mode         = '-mixed';
		} elseif ( $file_size_mb <= 10 ) {
			$compression_method = 6;
			$mixed_mode         = '-mixed';
		} else {
			$compression_method = 6;
			$mixed_mode         = '-mixed';
		}

		// 构建命令
		$command = sprintf(
			'gif2webp -q %d -m %d %s -kmin 150 -kmax 200 %s -o %s 2>&1',
			$quality,
			$compression_method,
			$mixed_mode,
			escapeshellarg( $file_path ),
			escapeshellarg( $output_path )
		);

		return $this->execute_command( $command, $output_path, 'gif2webp', $quality );
	}

	/**
	 * 转换静态图到 WebP
	 *
	 * @param string $file_path 源文件路径
	 * @param string $output_path 输出路径
	 * @param int    $quality 质量参数
	 * @return array 转换结果
	 */
	private function convert_static( $file_path, $output_path, $quality ) {
		// 优先使用 vips
		if ( isset( $this->available_engines['vips'] ) ) {
			$command = sprintf(
				'vips copy %s %s 2>&1',
				escapeshellarg( $file_path ),
				escapeshellarg( $output_path . '[Q=' . $quality . ',lossless=false]' )
			);

			$result = $this->execute_command( $command, $output_path, 'vips', $quality );
			if ( $result['success'] ) {
				return $result;
			}
		}

		// 回退到 cwebp
		if ( isset( $this->available_engines['cwebp'] ) ) {
			$command = sprintf(
				'cwebp -q %d -m 4 %s -o %s 2>&1',
				$quality,
				escapeshellarg( $file_path ),
				escapeshellarg( $output_path )
			);

			return $this->execute_command( $command, $output_path, 'cwebp', $quality );
		}

		return [
			'success' => false,
			'error'   => __( 'No conversion engine available', 'wp-genius' ),
		];
	}

	/**
	 * 执行命令
	 *
	 * @param string $command 命令
	 * @param string $output_path 输出路径
	 * @param string $engine 引擎名称
	 * @param int    $quality 质量参数
	 * @return array 执行结果
	 */
	private function execute_command( $command, $output_path, $engine, $quality ) {
		$output      = [];
		$return_code = 0;

		exec( $command, $output, $return_code );

		$output_str = implode( "\n", $output );
		$this->logger->log_command( $command, $output_str, $return_code );

		if ( $return_code === 0 && file_exists( $output_path ) ) {
			return [
				'success'     => true,
				'output_path' => $output_path,
				'engine'      => $engine,
				'quality'     => $quality,
			];
		}

		return [
			'success' => false,
			'error'   => ! empty( $output_str ) ? $output_str : __( 'Conversion failed', 'wp-genius' ),
			'engine'  => $engine,
		];
	}

	/**
	 * 计算质量参数
	 *
	 * 根据文件大小和类型动态计算压缩质量
	 *
	 * @param string $file_path 文件路径
	 * @param string $mime_type MIME 类型
	 * @return int 质量参数 (1-100)
	 */
	private function calculate_quality( $file_path, $mime_type ) {
		$size_mb = filesize( $file_path ) / 1024 / 1024;

		if ( strpos( $mime_type, 'gif' ) !== false ) {
			// GIF 动态图质量分级
			if ( $size_mb > 10 ) {
				return 20;
			} elseif ( $size_mb > 5 ) {
				return 30;
			} else {
				return 50;
			}
		} else {
			// 静态图 (JPG/PNG) 质量分级
			if ( $size_mb > 10 ) {
				return 50;
			} elseif ( $size_mb > 5 ) {
				return 60;
			} else {
				return 75;
			}
		}
	}

	/**
	 * 获取 WebP 输出路径
	 *
	 * 处理命名冲突:如果同目录下存在同名 GIF,静态图将重命名为 -static.webp
	 *
	 * @param string $file_path 源文件路径
	 * @return string WebP 文件路径
	 */
	private function get_webp_path( $file_path ) {
		$dir      = dirname( $file_path );
		$filename = pathinfo( $file_path, PATHINFO_FILENAME );
		$ext      = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		// 默认 WebP 路径
		$webp_path = $dir . '/' . $filename . '.webp';

		// 如果当前文件不是 GIF,检查是否存在同名 GIF
		if ( $ext !== 'gif' ) {
			$gif_path = $dir . '/' . $filename . '.gif';
			if ( file_exists( $gif_path ) ) {
				// 存在同名 GIF,静态图需要重命名
				$webp_path = $dir . '/' . $filename . '-static.webp';
			}
		}

		return $webp_path;
	}

	/**
	 * 检查引擎是否可用
	 *
	 * @param string $engine 引擎名称
	 * @return bool 是否可用
	 */
	public function is_engine_available( $engine ) {
		return isset( $this->available_engines[ $engine ] );
	}

	/**
	 * 获取所有可用引擎
	 *
	 * @return array 可用引擎列表
	 */
	public function get_available_engines() {
		return array_keys( $this->available_engines );
	}

	/**
	 * 获取日志记录器
	 *
	 * @return MediaEngineConversionLogger 日志记录器
	 */
	public function get_logger() {
		return $this->logger;
	}
}
