<?php
/**
 * Media Engine Task Chain Executor
 *
 * 管理 5 步处理流程的严格顺序执行
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineTaskChainExecutor {

	/**
	 * 图片转换器
	 *
	 * @var MediaEngineImageConverter
	 */
	private $converter;

	/**
	 * 日志记录器
	 *
	 * @var MediaEngineConversionLogger
	 */
	private $logger;

	/**
	 * 设置选项
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * 构造函数
	 */
	public function __construct() {
		if ( ! class_exists( 'MediaEngineImageConverter' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-image-converter.php';
		}
		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-conversion-logger.php';
		}

		$this->converter = new MediaEngineImageConverter();
		$this->logger    = $this->converter->get_logger();
		$this->settings  = get_option( 'w2p_media_turbo_settings', [] );
	}

	/**
	 * 执行完整任务链
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array 执行结果 ['success' => bool, 'steps' => array, 'error' => string]
	 */
	public function execute_chain( $attachment_id ) {
		$steps = [
			'convert'    => [ $this, 'step_convert' ],
			'thumbnails' => [ $this, 'step_regenerate_thumbnails' ],
			'rewrite'    => [ $this, 'step_content_rewrite' ],
			'offload'    => [ $this, 'step_offload_minio' ],
			'cleanup'    => [ $this, 'step_cleanup' ],
		];

		$results = [
			'success' => true,
			'steps'   => [],
			'error'   => '',
		];

		// 保存原始文件路径,用于 URL 替换
		$original_file = get_attached_file( $attachment_id );
		$original_url = wp_get_attachment_url( $attachment_id );

		foreach ( $steps as $step_name => $callback ) {
			// 传递原始 URL 给 rewrite 步骤
			if ( $step_name === 'rewrite' ) {
				$step_result = call_user_func( $callback, $attachment_id, $original_url );
			} else {
				$step_result = call_user_func( $callback, $attachment_id );
			}

			$results['steps'][ $step_name ] = $step_result;

			if ( ! $step_result['success'] ) {
				$results['success'] = false;
				$results['error']   = sprintf(
					/* translators: %1$s: step name, %2$s: error message */
					__( 'Failed at step "%1$s": %2$s', 'wp-genius' ),
					$step_name,
					$step_result['error']
				);

				$this->logger->log_chain_step(
					$attachment_id,
					$step_name,
					false,
					$step_result['error']
				);

				// 停止执行后续步骤
				break;
			}

			$this->logger->log_chain_step(
				$attachment_id,
				$step_name,
				true,
				$step_result['message'] ?? ''
			);
		}

		return $results;
	}

	/**
	 * 步骤 1: 本地转换
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array 步骤结果
	 */
	private function step_convert( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return [
				'success' => false,
				'error'   => __( 'File not found', 'wp-genius' ),
			];
		}

		// 检查文件是否已经是 WebP 格式
		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( $ext === 'webp' ) {
			return [
				'success' => true,
				'message' => __( 'File is already in WebP format, skipping conversion', 'wp-genius' ),
				'output_path' => $file_path,
				'engine' => 'skip',
				'quality' => 0,
			];
		}

		$file_size = filesize( $file_path );
		$this->logger->log_conversion_start( $attachment_id, $file_path, $file_size );

		// 执行转换
		$result = $this->converter->convert_to_webp( $file_path );

		if ( ! $result['success'] ) {
			$this->logger->log_conversion_error( $attachment_id, $file_path, $result['error'] );
			return $result;
		}

		// 记录成功
		$webp_size = filesize( $result['output_path'] );
		$this->logger->log_conversion_success(
			$attachment_id,
			$file_path,
			$result['output_path'],
			$file_size,
			$webp_size,
			$result['engine'],
			$result['quality']
		);

		// 更新附件元数据
		$this->update_attachment_metadata( $attachment_id, $file_path, $result['output_path'] );

		return [
			'success'     => true,
			'message'     => sprintf(
				/* translators: %s: output file path */
				__( 'Converted to %s', 'wp-genius' ),
				basename( $result['output_path'] )
			),
			'output_path' => $result['output_path'],
			'engine'      => $result['engine'],
			'quality'     => $result['quality'],
		];
	}

	/**
	 * 步骤 2: 重新生成缩略图
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array 步骤结果
	 */
	private function step_regenerate_thumbnails( $attachment_id ) {
		// 检查是否启用缩略图生成
		if ( empty( $this->settings['generate_thumbnails'] ) || $this->settings['generate_thumbnails'] !== '1' ) {
			return [
				'success' => true,
				'message' => __( 'Thumbnail generation disabled', 'wp-genius' ),
			];
		}

		// 使用 WP-CLI 重新生成缩略图
		$command = sprintf(
			'wp media regenerate %d --only-missing --yes 2>&1',
			$attachment_id
		);

		$output      = [];
		$return_code = 0;
		exec( $command, $output, $return_code );

		$output_str = implode( "\n", $output );
		$this->logger->log_command( $command, $output_str, $return_code );

		if ( $return_code === 0 ) {
			return [
				'success' => true,
				'message' => __( 'Thumbnails regenerated', 'wp-genius' ),
			];
		}

		return [
			'success' => false,
			'error'   => $output_str ?: __( 'Failed to regenerate thumbnails', 'wp-genius' ),
		];
	}

	/**
	 * 步骤 3: 内容重写
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param string $original_url 原始 URL(转换前的 URL)
	 * @return array 步骤结果
	 */
	private function step_content_rewrite( $attachment_id, $original_url = null ) {
		// 如果没有传入原始 URL,尝试从当前 URL 推断
		if ( $original_url === null ) {
			$original_url = wp_get_attachment_url( $attachment_id );
		}

		// 构造新 URL (将原始格式替换为 webp)
		$new_url = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $original_url );

		// 如果 URL 中没有图片扩展名,再检查是否已经是 webp
		if ( $original_url === $new_url ) {
			// 已经是 webp 或无需替换
			return [
				'success' => true,
				'message' => __( 'No URL rewrite needed (already WebP or not an image)', 'wp-genius' ),
			];
		}

		// 使用 WP-CLI 进行搜索替换
		$command = sprintf(
			'wp search-replace %s %s --precise --recurse-objects --skip-columns=guid --yes 2>&1',
			escapeshellarg( $original_url ),
			escapeshellarg( $new_url )
		);

		$output      = [];
		$return_code = 0;
		exec( $command, $output, $return_code );

		$output_str = implode( "\n", $output );
		$this->logger->log_command( $command, $output_str, $return_code );

		// 更新附件的 guid
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			[ 'guid' => $new_url ],
			[ 'ID' => $attachment_id ]
		);

		return [
			'success' => true,
			'message' => sprintf(
				/* translators: %s: number of replacements */
				__( 'Content rewritten: %s → %s (%s)', 'wp-genius' ),
				basename( $original_url ),
				basename( $new_url ),
				$output_str
			),
		];
	}

	/**
	 * 步骤 4: 搬运到 Minio
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array 步骤结果
	 */
	private function step_offload_minio( $attachment_id ) {
		// 检查是否安装了 advmo 插件
		if ( ! $this->is_advmo_available() ) {
			return [
				'success' => true,
				'message' => __( 'Minio offload not available (advmo plugin not found)', 'wp-genius' ),
			];
		}

		// 使用 WP-CLI 搬运到 Minio
		// Advanced MiniO Offloader 的正确命令格式: wp advmo offload <attachment_id>
		$command = sprintf(
			'wp advmo offload %d 2>&1',
			$attachment_id
		);

		$output      = [];
		$return_code = 0;
		exec( $command, $output, $return_code );

		$output_str = implode( "\n", $output );
		$this->logger->log_command( $command, $output_str, $return_code );

		if ( $return_code === 0 ) {
			// 标记为已搬运
			update_post_meta( $attachment_id, '_is_minio_offloaded', 1 );

			return [
				'success' => true,
				'message' => __( 'Offloaded to Minio', 'wp-genius' ),
			];
		}

		return [
			'success' => false,
			'error'   => $output_str ?: __( 'Failed to offload to Minio', 'wp-genius' ),
		];
	}

	/**
	 * 步骤 5: 清理本地文件
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array 步骤结果
	 */
	private function step_cleanup( $attachment_id ) {
		// 检查是否保留原文件
		if ( ! empty( $this->settings['keep_original'] ) ) {
			return [
				'success' => true,
				'message' => __( 'Original file kept (as configured)', 'wp-genius' ),
			];
		}

		// 检查是否已搬运到 Minio
		$is_offloaded = get_post_meta( $attachment_id, '_is_minio_offloaded', true );
		if ( ! $is_offloaded ) {
			return [
				'success' => true,
				'message' => __( 'Cleanup skipped (not offloaded to Minio)', 'wp-genius' ),
			];
		}

		// 获取原文件路径
		$webp_path     = get_attached_file( $attachment_id );
		$original_path = preg_replace( '/\.webp$/i', '', $webp_path );

		// 尝试检测原始扩展名
		$possible_extensions = [ 'jpg', 'jpeg', 'png', 'gif' ];
		$deleted_files       = [];

		foreach ( $possible_extensions as $ext ) {
			$test_path = $original_path . '.' . $ext;
			if ( file_exists( $test_path ) ) {
				// 移动到 source 子目录
				$source_dir = dirname( $test_path ) . '/source';
				if ( ! is_dir( $source_dir ) ) {
					wp_mkdir_p( $source_dir );
				}

				$dest_path = $source_dir . '/' . basename( $test_path );
				if ( rename( $test_path, $dest_path ) ) {
					$deleted_files[] = basename( $test_path );
				}
			}
		}

		if ( ! empty( $deleted_files ) ) {
			return [
				'success' => true,
				'message' => sprintf(
					/* translators: %s: list of moved files */
					__( 'Moved to source/: %s', 'wp-genius' ),
					implode( ', ', $deleted_files )
				),
			];
		}

		return [
			'success' => true,
			'message' => __( 'No original files to clean up', 'wp-genius' ),
		];
	}

	/**
	 * 更新附件元数据
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param string $old_path 原文件路径
	 * @param string $new_path 新文件路径
	 */
	private function update_attachment_metadata( $attachment_id, $old_path, $new_path ) {
		// 更新 _wp_attached_file
		update_attached_file( $attachment_id, $new_path );

		// 更新 post_mime_type
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			[ 'post_mime_type' => 'image/webp' ],
			[ 'ID' => $attachment_id ]
		);

		// 更新元数据
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( $metadata ) {
			$metadata['file'] = str_replace( basename( $old_path ), basename( $new_path ), $metadata['file'] );
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
	}

	/**
	 * 检查 advmo 插件是否可用
	 *
	 * @return bool 是否可用
	 */
	private function is_advmo_available() {
		exec( 'wp advmo --help 2>&1', $output, $return_code );
		return $return_code === 0;
	}
}
