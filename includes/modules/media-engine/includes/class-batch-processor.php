<?php
/**
 * Media Engine Batch Processor
 *
 * 批处理方法:按步骤批量处理附件
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineBatchProcessor {

	/**
	 * 图片转换器
	 */
	private $converter;

	/**
	 * 日志记录器
	 */
	private $logger;

	/**
	 * 设置
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
	 * 批量处理附件
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 * @return array 批处理结果
	 */
	public function process_batch( $attachment_ids ) {
		$results = [
			'success' => true,
			'stats'   => [
				'total'      => count( $attachment_ids ),
				'convert'    => [],
				'thumbnails' => [],
				'rewrite'    => [],
				'offload'    => [],
				'cleanup'    => [],
			],
			'errors'  => [],
		];

		// 保存原始 URL 映射
		$original_urls = [];
		foreach ( $attachment_ids as $id ) {
			$original_urls[ $id ] = wp_get_attachment_url( $id );
		}

		// 步骤 1: 批量转换
		$convert_results = $this->batch_convert( $attachment_ids );
		$results['stats']['convert'] = $convert_results;

		// 获取成功转换的 ID
		$successful_ids = [];
		foreach ( $convert_results as $id => $result ) {
			if ( $result['success'] ) {
				$successful_ids[] = $id;
			}
		}

		if ( empty( $successful_ids ) ) {
			$results['success'] = false;
			$results['errors'][] = __( 'No attachments were successfully converted', 'wp-genius' );
			return $results;
		}

		// 步骤 2: 批量生成缩略图
		if ( ! empty( $this->settings['generate_thumbnails'] ) && $this->settings['generate_thumbnails'] === '1' ) {
			$results['stats']['thumbnails'] = $this->batch_regenerate_thumbnails( $successful_ids );
		}

		// 步骤 3: 批量 URL 重写
		$results['stats']['rewrite'] = $this->batch_rewrite_urls( $successful_ids, $original_urls );

		// 步骤 4: 批量 Offload 到 Minio
		$offload_result = $this->batch_offload_minio( $successful_ids );
		$results['stats']['offload'] = $offload_result;

		// 步骤 5: 批量清理(如果已 offload)
		if ( $offload_result['success'] ) {
			$results['stats']['cleanup'] = $this->batch_cleanup( $successful_ids );
		}

		return $results;
	}

	/**
	 * 批量转换图片到 WebP
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 * @return array 转换结果 [id => result]
	 */
	private function batch_convert( $attachment_ids ) {
		$results = [];

		foreach ( $attachment_ids as $id ) {
			$file_path = get_attached_file( $id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$results[ $id ] = [
					'success' => false,
					'error'   => __( 'File not found', 'wp-genius' ),
				];
				continue;
			}

			// 检查是否已经是 WebP
			$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
			if ( $ext === 'webp' ) {
				$results[ $id ] = [
					'success' => true,
					'message' => __( 'Already WebP', 'wp-genius' ),
					'skipped' => true,
				];
				continue;
			}

			// 执行转换
			$result = $this->converter->convert_to_webp( $file_path );

			if ( $result['success'] ) {
				// 更新附件元数据
				$this->update_attachment_metadata( $id, $file_path, $result['output_path'] );
			}

			$results[ $id ] = $result;
		}

		return $results;
	}

	/**
	 * 批量重新生成缩略图
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 * @return array 结果
	 */
	private function batch_regenerate_thumbnails( $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return [ 'success' => true, 'message' => __( 'No thumbnails to regenerate', 'wp-genius' ) ];
		}

		// 使用 wp media regenerate 批量命令
		$ids_str = implode( ' ', $attachment_ids );
		$command = sprintf( 'wp media regenerate %s --only-missing --yes 2>&1', $ids_str );

		$output      = [];
		$return_code = 0;
		exec( $command, $output, $return_code );

		$output_str = implode( "\n", $output );
		$this->logger->log_command( $command, $output_str, $return_code );

		return [
			'success' => $return_code === 0,
			'message' => $output_str,
			'count'   => count( $attachment_ids ),
		];
	}

	/**
	 * 批量 URL 重写
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 * @param array $original_urls 原始 URL 映射 [id => url]
	 * @return array 结果
	 */
	private function batch_rewrite_urls( $attachment_ids, $original_urls ) {
		global $wpdb;
		$results = [];

		foreach ( $attachment_ids as $id ) {
			$original_url = $original_urls[ $id ] ?? '';
			if ( empty( $original_url ) ) {
				continue;
			}

			$new_url = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $original_url );

			// 跳过已经是 webp 的
			if ( $original_url === $new_url ) {
				continue;
			}

			// 执行 URL 替换
			$command = sprintf(
				'wp search-replace %s %s --precise --recurse-objects --skip-columns=guid --yes 2>&1',
				escapeshellarg( $original_url ),
				escapeshellarg( $new_url )
			);

			$output      = [];
			$return_code = 0;
			exec( $command, $output, $return_code );

			// 更新 guid
			$wpdb->update(
				$wpdb->posts,
				[ 'guid' => $new_url ],
				[ 'ID' => $id ]
			);

			$results[ $id ] = [
				'old_url' => $original_url,
				'new_url' => $new_url,
				'output'  => implode( "\n", $output ),
			];
		}

		return [
			'success' => true,
			'count'   => count( $results ),
			'details' => $results,
		];
	}

	/**
	 * 批量 Offload 到 Minio
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 * @return array 结果
	 */
	private function batch_offload_minio( $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return [ 'success' => true, 'message' => __( 'No attachments to offload', 'wp-genius' ) ];
		}

		// 检查 advmo 是否可用
		exec( 'wp advmo --help 2>&1', $output, $return_code );
		if ( $return_code !== 0 ) {
			return [
				'success' => true,
				'message' => __( 'Advanced MiniO Offloader not available', 'wp-genius' ),
				'skipped' => true,
			];
		}

		// 批量 offload: wp advmo offload 123,45,789
		$ids_str = implode( ',', $attachment_ids );
		$command = sprintf( 'wp advmo offload %s 2>&1', $ids_str );

		$output      = [];
		$return_code = 0;
		exec( $command, $output, $return_code );

		$output_str = implode( "\n", $output );
		$this->logger->log_command( $command, $output_str, $return_code );

		// 标记为已 offload
		if ( $return_code === 0 ) {
			foreach ( $attachment_ids as $id ) {
				update_post_meta( $id, '_is_minio_offloaded', 1 );
			}
		}

		return [
			'success' => $return_code === 0,
			'message' => $output_str,
			'count'   => count( $attachment_ids ),
		];
	}

	/**
	 * 批量清理本地文件
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 * @return array 结果
	 */
	private function batch_cleanup( $attachment_ids ) {
		// 检查是否保留原文件
		if ( ! empty( $this->settings['keep_original'] ) ) {
			return [
				'success' => true,
				'message' => __( 'Original files kept as configured', 'wp-genius' ),
			];
		}

		$cleaned = 0;

		foreach ( $attachment_ids as $id ) {
			// 检查是否已 offload
			if ( ! get_post_meta( $id, '_is_minio_offloaded', true ) ) {
				continue;
			}

			$webp_path = get_attached_file( $id );
			$dir       = dirname( $webp_path );
			$filename  = pathinfo( $webp_path, PATHINFO_FILENAME );

			// 清理原始文件
			$possible_extensions = [ 'jpg', 'jpeg', 'png', 'gif' ];
			foreach ( $possible_extensions as $ext ) {
				$original_file = $dir . '/' . $filename . '.' . $ext;
				if ( file_exists( $original_file ) ) {
					// 移动到 source 子目录
					$source_dir = $dir . '/source';
					if ( ! is_dir( $source_dir ) ) {
						wp_mkdir_p( $source_dir );
					}

					$dest_file = $source_dir . '/' . basename( $original_file );
					if ( rename( $original_file, $dest_file ) ) {
						$cleaned++;
					}
				}
			}
		}

		return [
			'success' => true,
			'count'   => $cleaned,
			'message' => sprintf( __( 'Cleaned up %d original files', 'wp-genius' ), $cleaned ),
		];
	}

	/**
	 * 更新附件元数据
	 */
	private function update_attachment_metadata( $attachment_id, $old_path, $new_path ) {
		global $wpdb;

		// 更新 _wp_attached_file
		update_attached_file( $attachment_id, $new_path );

		// 更新 post_mime_type
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
}
