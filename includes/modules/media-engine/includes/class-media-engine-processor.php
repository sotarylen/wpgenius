<?php
/**
 * Media Engine Processor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineProcessor {
	private $scanner;
	private $converter;
	private $thumbnail;
	private $url_rewrite;
	private $minio;
	private $metadata;
	private $logger;
	private $settings;
	private $minio_available = null;

	public function __construct() {
		$this->settings = W2P_Settings::tab_with_legacy( 'media_engine_tabs', 'w2p_media_turbo_settings', array() );
		$services_dir = plugin_dir_path( __FILE__ ) . 'services/';
		
		// Load Services
		require_once $services_dir . 'class-scanner-service.php';
		require_once $services_dir . 'class-converter-service.php';
		require_once $services_dir . 'class-thumbnail-service.php';
		require_once $services_dir . 'class-url-rewrite-service.php';
		require_once $services_dir . 'class-metadata-service.php';
		require_once $services_dir . 'class-environment-service.php'; // Renamed/Moved
		
		if ( file_exists( $services_dir . 'class-minio-service.php' ) ) {
			require_once $services_dir . 'class-minio-service.php';
		}
		// Logger is now a service
		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once $services_dir . 'class-logger-service.php';
		}
		
		$this->scanner = new MediaEngineScannerService();
		$this->converter = new MediaEngineConverterService();
		$this->thumbnail = new MediaEngineThumbnailService();
		$this->url_rewrite = new MediaEngineUrlRewriteService();
		if ( class_exists( 'MediaEngineMinioService' ) ) {
			$this->minio = new MediaEngineMinioService();
		}
		$this->metadata = new MediaEngineMetadataService();
		
		// Use existing logger class name (from moved file)
		if ( class_exists( 'MediaEngineConversionLogger' ) ) {
			$this->logger = new MediaEngineConversionLogger();
		}
	}

	public function process_attachment( $attachment_id ) {
		$results = [ 'success' => true, 'steps' => [] ];
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return [ 'success' => false, 'error' => 'File not found' ];
		}
		$original_url = wp_get_attachment_url( $attachment_id );

		// 单文件流程：转换前输出 STEP1 标题行（与批量流程保持一致）
		$this->logger->log_step_header( 'STEP1: Convert Media Format to WebP' );
		$convert_result = $this->converter->convert_to_webp( $attachment_id );
		$results['steps']['convert'] = $convert_result;
		if ( ! $convert_result['success'] ) {
			$results['success'] = false;
			$results['error'] = $convert_result['error'];
			return $results;
		}
		if ( ! empty( $convert_result['skipped'] ) ) {
			return $results;
		}
		$this->metadata->update( $attachment_id, $file_path, $convert_result['output_path'] );
		$this->metadata->save_original_info( $attachment_id, basename( $file_path ) );
		
		// Regenerate thumbnails (Always enabled)
		$results['steps']['thumbnails'] = $this->thumbnail->regenerate( $attachment_id );
		
		// Offload to Minio BEFORE URL rewrite so wp_get_attachment_url() returns the final Minio URL
		if ( $this->is_minio_available() ) {
			$results['steps']['minio'] = $this->minio->upload( $attachment_id );
		}
		$new_url = wp_get_attachment_url( $attachment_id );
		$results['steps']['rewrite'] = $this->url_rewrite->rewrite_content( $attachment_id, $original_url, $new_url );
		if ( $this->should_cleanup( $attachment_id ) ) {
			$results['steps']['cleanup'] = $this->metadata->cleanup_original( $attachment_id );
		}
		return $results;
	}

	public function process_batch( $attachment_ids ) {
		$start_time = microtime( true );
		$total = count( $attachment_ids );
		if ( $total === 0 ) {
			return [ 'success' => true, 'stats' => [ 'convert' => [] ] ];
		}
		$this->logger->log_batch_start( $total, $total );
		$results = [];
		
		// 步骤1: 批量转换所有图片（STEP1 日志）
		$this->logger->log_step_header( 'STEP1: Convert Media Format to WebP' );
		foreach ( $attachment_ids as $id ) {
			$file_path = get_attached_file( $id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$results[ $id ] = [ 'success' => false, 'error' => 'File not found' ];
				continue;
			}
			$original_url = wp_get_attachment_url( $id );
			$convert_result = $this->converter->convert_to_webp( $id );
			if ( ! $convert_result['success'] ) {
				// Store original_url for failed conversions too — needed for path prefix
				// rewrite if the original file is later offloaded to Minio.
				$results[ $id ] = array_merge( $convert_result, [ 'original_url' => $original_url ] );
				continue;
			}
			// 如果是WebP文件,标记为跳过转换,但仍然执行后续步骤
			if ( ! empty( $convert_result['skipped'] ) ) {
				$results[ $id ] = array_merge( $convert_result, [ 'original_url' => $original_url, 'conversion_skipped' => true ] );
			} else {
				$this->metadata->update( $id, $file_path, $convert_result['output_path'] );
				$this->metadata->save_original_info( $id, basename( $file_path ) );
				$results[ $id ] = array_merge( $convert_result, [ 'original_url' => $original_url ] );
			}
		}
		
		// Step 2: Thumbnail Generation
		$ids_to_regenerate = [];
		foreach ( $attachment_ids as $id ) {
			if ( isset( $results[ $id ] ) && $results[ $id ]['success'] ) {
				$ids_to_regenerate[] = $id;
			}
		}
		if ( ! empty( $ids_to_regenerate ) ) {
			$this->thumbnail->regenerate_batch( $ids_to_regenerate );
		}
		
		// 步骤3: 批量Minio上传（放在URL重写之前，确保wp_get_attachment_url返回最终Minio路径）
		// 包含转换成功的文件和转换失败但原始文件仍可offload的文件
		if ( $this->is_minio_available() ) {
			$ids_to_upload = [];
			foreach ( $attachment_ids as $id ) {
				// Offload any attachment that has original_url stored (successful or failed conversion)
				if ( isset( $results[ $id ]['original_url'] ) ) {
					$ids_to_upload[] = $id;
				}
			}
			if ( ! empty( $ids_to_upload ) ) {
				// STEP3 标题行已由 log_offload_result 输出，此处不再记录冗余 debug
				$this->minio->upload_batch( $ids_to_upload );
			}
		}
		
		// 步骤4: 批量URL重写（此时wp_get_attachment_url已返回最终URL，STEP4 日志）
		$this->logger->log_step_header( 'STEP4: WP Rewrite Content URL' );
		$rewrite_count = 0;
		$skip_count    = 0;
		foreach ( $attachment_ids as $id ) {
			if ( ! isset( $results[ $id ]['original_url'] ) ) {
				++$skip_count;
				continue;
			}
			$original_url = $results[ $id ]['original_url'];
			$new_url      = wp_get_attachment_url( $id );
			if ( ! $new_url ) {
				++$skip_count;
				continue;
			}
			if ( $original_url === $new_url ) {
				++$skip_count;
				continue;
			}
			$result = $this->url_rewrite->rewrite_content( $id, $original_url, $new_url );
			++$rewrite_count;
		}
		
		// 步骤5: 批量清理（STEP5 日志，标题由 log_cleanup_result 内部输出）
		$cleanup_success = 0;
		$cleanup_skip    = 0;
		$cleanup_failed  = 0;
		foreach ( $attachment_ids as $id ) {
			// 转换失败/跳过转换：无清理必要 → skip
			if ( ! isset( $results[ $id ] ) || ! $results[ $id ]['success'] || ! empty( $results[ $id ]['conversion_skipped'] ) ) {
				$cleanup_skip++;
				continue;
			}
			// 不满足清理条件（keep_original / 未 offload）：skip
			if ( ! $this->should_cleanup( $id ) ) {
				$cleanup_skip++;
				continue;
			}
			$cleanup_result = $this->metadata->cleanup_original( $id );
			if ( ! empty( $cleanup_result['success'] ) && ! empty( $cleanup_result['count'] ) ) {
				$cleanup_success++;
			} else {
				$cleanup_skip++; // 无原文件可清理（count=0）
			}
		}
		$this->logger->log_cleanup_result( $attachment_ids, $cleanup_success, $cleanup_skip, $cleanup_failed );
		
		$succeeded = count( array_filter( $results, function( $r ) { return $r['success']; } ) );
		$failed = $total - $succeeded;
		$duration = microtime( true ) - $start_time;
		$this->logger->log_batch_complete( $total, $succeeded, $failed, $duration );
		return [ 'success' => true, 'stats' => [ 'convert' => $results ] ];
	}

	public function scan( $limit = 100, $offset = 0 ) {
		return $this->scanner->get_pending_attachments( $limit, $offset );
	}

	public function get_pending_count() {
		return $this->scanner->get_pending_count();
	}

	private function should_cleanup( $attachment_id ) {
		// 如果设置了保留原文件,不清理
		if ( ! empty( $this->settings['keep_original'] ) && $this->settings['keep_original'] === '1' ) {
			return false;
		}
		// 如果Minio不可用,也清理(因为文件已经转换成功)
		if ( ! $this->is_minio_available() ) {
			return true;
		}
		// 如果Minio可用,检查是否已上传
		$is_offloaded = get_post_meta( $attachment_id, '_is_minio_offloaded', true );
		return (bool) $is_offloaded;
	}

	private function is_minio_available() {
		if ( $this->minio_available === null && $this->minio ) {
			$this->minio_available = $this->minio->is_available();
		}
		return $this->minio_available;
	}
}
