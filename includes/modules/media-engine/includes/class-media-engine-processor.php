<?php
/**
 * Media Engine Processor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Media_Engine_Processor {
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
		$services_dir   = plugin_dir_path( __FILE__ ) . 'services/';

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

		$this->scanner     = new MediaEngineScannerService();
		$this->converter   = new MediaEngineConverterService();
		$this->thumbnail   = new MediaEngineThumbnailService();
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
		$results   = array(
			'success' => true,
			'steps'   => array(),
		);
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'error'   => 'File not found',
			);
		}
		$original_url = wp_get_attachment_url( $attachment_id );

		// Single-file flow: output the STEP1 header line before conversion (consistent with the batch flow)
		$this->logger->log_step_header( 'STEP1: Convert Media Format to WebP' );
		$convert_result              = $this->converter->convert_to_webp( $attachment_id );
		$results['steps']['convert'] = $convert_result;
		if ( ! $convert_result['success'] ) {
			$results['success'] = false;
			$results['error']   = $convert_result['error'];
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
		$new_url                     = wp_get_attachment_url( $attachment_id );
		$results['steps']['rewrite'] = $this->url_rewrite->rewrite_content( $attachment_id, $original_url, $new_url );
		if ( $this->should_cleanup( $attachment_id ) ) {
			$results['steps']['cleanup'] = $this->metadata->cleanup_original( $attachment_id );
		}
		return $results;
	}

	public function process_batch( $attachment_ids ) {
		$start_time = microtime( true );
		$total      = count( $attachment_ids );
		if ( $total === 0 ) {
			return array(
				'success' => true,
				'stats'   => array( 'convert' => array() ),
			);
		}
		$this->logger->log_batch_start( $total, $total );
		$results = array();

		// 补偿机制：拆出上一批回写失败的附件（_w2p_rewrite_pending）。
		// 这类附件跳过 STEP1/2/3（已转换/已生成缩略图），只补做 STEP4 回写 + STEP5 清理。
		$pending_ids = array();
		foreach ( $attachment_ids as $id ) {
			if ( get_post_meta( $id, '_w2p_rewrite_pending', true ) ) {
				$pending_ids[] = $id;
			}
		}
		$normal_ids = array_diff( $attachment_ids, $pending_ids );

		// Step 1: Batch convert all images (STEP1 log)
		$this->logger->log_step_header( 'STEP1: Convert Media Format to WebP' );
		foreach ( $normal_ids as $id ) {
			try {
				$file_path = get_attached_file( $id );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					// Mark as failed so the scanner stops picking it up on every round
					// (dead attachments — DB record exists but local file is gone — would
					// otherwise be re-returned forever and inflate the failed counter).
					update_post_meta( $id, '_w2p_media_failed', time() );
					$results[ $id ] = array(
						'success' => false,
						'error'   => 'File not found',
					);
					continue;
				}
				$original_url   = wp_get_attachment_url( $id );
				$convert_result = $this->converter->convert_to_webp( $id );
				if ( ! $convert_result['success'] ) {
					// Mark the attachment as failed so the scanner stops picking it up on
					// every round (avoids an endless retry loop). The failed state can be
					// cleared via the "Retry failed" action once the underlying problem is fixed.
					update_post_meta( $id, '_w2p_media_failed', time() );
					// Failed conversions do NOT proceed to offload/URL rewrite: uploading the
					// unconverted original and rewriting content URLs would serve visitors a
					// heavy/unsupported format (e.g. BMP) from the bucket instead of WebP.
					$results[ $id ] = $convert_result;
					continue;
				}
				delete_post_meta( $id, '_w2p_media_failed' );
				// If it is a WebP file, mark conversion as skipped, but still run the subsequent steps
				if ( ! empty( $convert_result['skipped'] ) ) {
					$results[ $id ] = array_merge(
						$convert_result,
						array(
							'original_url'       => $original_url,
							'conversion_skipped' => true,
						)
					);
				} else {
					$this->metadata->update( $id, $file_path, $convert_result['output_path'] );
					$this->metadata->save_original_info( $id, basename( $file_path ) );
					$results[ $id ] = array_merge( $convert_result, array( 'original_url' => $original_url ) );
				}
			} catch ( \Throwable $e ) {
				// 单附件异常不中断整个批次：记录失败状态并继续下一个
				update_post_meta( $id, '_w2p_media_failed', time() );
				$results[ $id ] = array(
					'success' => false,
					'error'   => 'Exception: ' . $e->getMessage(),
				);
			}
		}

		// Step 2: Thumbnail Generation
		$ids_to_regenerate = array();
		foreach ( $normal_ids as $id ) {
			if ( isset( $results[ $id ] ) && $results[ $id ]['success'] ) {
				$ids_to_regenerate[] = $id;
			}
		}
		if ( ! empty( $ids_to_regenerate ) ) {
			$this->thumbnail->regenerate_batch( $ids_to_regenerate );
		}

		// Step 3: Batch upload to Minio (before URL rewrite, so wp_get_attachment_url returns the final Minio path)
		// Includes files that converted successfully and files that failed conversion but whose original file can still be offloaded
		if ( $this->is_minio_available() ) {
			$ids_to_upload = array();
			foreach ( $normal_ids as $id ) {
				// Offload any attachment that has original_url stored (successful or failed conversion)
				if ( isset( $results[ $id ]['original_url'] ) ) {
					$ids_to_upload[] = $id;
				}
			}
			if ( ! empty( $ids_to_upload ) ) {
				// The STEP3 header line is already output by log_offload_result; no redundant debug is logged here
				$this->minio->upload_batch( $ids_to_upload );
			}
		}

		// 补偿分支：上一批回写失败的附件，本批只补做回写 + 清理（跳过 STEP1/2/3）
		if ( ! empty( $pending_ids ) ) {
			$this->logger->log_step_header( 'STEP4-RETRY: WP Rewrite Content URL (compensation)' );
			foreach ( $pending_ids as $id ) {
				try {
					$results[ $id ] = $this->compensate_pending( $id );
				} catch ( \Throwable $e ) {
					// 补偿异常：保留标记，下批再试（不中断批次）
					$results[ $id ] = array(
						'success' => false,
						'error'   => 'Exception: ' . $e->getMessage(),
					);
				}
			}
		}

		// Step 4: Batch URL rewrite (wp_get_attachment_url now returns the final URL; STEP4 log)
		$this->logger->log_step_header( 'STEP4: WP Rewrite Content URL' );
		$rewrite_count = 0;
		$skip_count    = 0;
		foreach ( $normal_ids as $id ) {
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
			try {
				$result = $this->url_rewrite->rewrite_content( $id, $original_url, $new_url );
			} catch ( \Throwable $e ) {
				// 单附件回写异常不中断批次：标记待补偿，下批走补偿分支
				update_post_meta( $id, '_w2p_rewrite_pending', time() );
				$results[ $id ]['rewrite_error'] = 'Exception: ' . $e->getMessage();
				continue;
			}
			// 回写未成功或未发生任何替换（no_parent/no_match/无变化/DB 更新失败）→ 标记待补偿；成功替换 → 清除标记
			if ( empty( $result['success'] ) || empty( $result['replaced'] ) ) {
				update_post_meta( $id, '_w2p_rewrite_pending', time() );
			} else {
				delete_post_meta( $id, '_w2p_rewrite_pending' );
			}
			++$rewrite_count;
		}

		// Step 5: Batch cleanup (STEP5 log; the header is output internally by log_cleanup_result)
		$cleanup_success = 0;
		$cleanup_skip    = 0;
		$cleanup_failed  = 0;
		foreach ( $normal_ids as $id ) {
			// Conversion failed / skipped: no cleanup needed → skip
			if ( ! isset( $results[ $id ] ) || ! $results[ $id ]['success'] || ! empty( $results[ $id ]['conversion_skipped'] ) ) {
				++$cleanup_skip;
				continue;
			}
			// Cleanup conditions not met (keep_original / not offloaded): skip
			if ( ! $this->should_cleanup( $id ) ) {
				++$cleanup_skip;
				continue;
			}
			$cleanup_result = $this->metadata->cleanup_original( $id );
			if ( ! empty( $cleanup_result['success'] ) && ! empty( $cleanup_result['count'] ) ) {
				++$cleanup_success;
			} else {
				++$cleanup_skip; // No original file to clean up (count=0)
			}
		}
		$this->logger->log_cleanup_result( $attachment_ids, $cleanup_success, $cleanup_skip, $cleanup_failed );

		$succeeded = count(
			array_filter(
				$results,
				function ( $r ) {
					return $r['success'];
				}
			)
		);
		$failed    = $total - $succeeded;
		$duration  = microtime( true ) - $start_time;
		$this->logger->log_batch_complete( $total, $succeeded, $failed, $duration );
		return array(
			'success' => true,
			'stats'   => array( 'convert' => $results ),
		);
	}

	public function scan( $limit = 100, $offset = 0 ) {
		return $this->scanner->get_pending_attachments( $limit, $offset );
	}

	public function get_pending_count() {
		return $this->scanner->get_pending_count();
	}

	private function should_cleanup( $attachment_id ) {
		// keep_original 开关打开 → 永不清理
		if ( ! empty( $this->settings['keep_original'] ) && $this->settings['keep_original'] === '1' ) {
			return false;
		}
		// 安全检查：只有确认已 offload（advmo 或 minio meta 任一为 '1'）才允许删除本地文件。
		// 不再有 "Minio 不可用 → 返回 true" 的逻辑：那会在未 offload 的情况下删除本地文件导致图片 404。
		$is_offloaded = '1' === get_post_meta( $attachment_id, 'advmo_offloaded', true )
			|| '1' === get_post_meta( $attachment_id, '_is_minio_offloaded', true );
		return (bool) $is_offloaded;
	}

	/**
	 * 补偿回写：处理上一批回写失败（_w2p_rewrite_pending）的附件。
	 *
	 * 跳过转换/缩略图/上传（STEP1/2/3），只补做内容回写 + 本地原文件清理。
	 * 文章内无旧引用（no_parent/no_match）视为"确认无引用"，同样清除标记，避免无限重试。
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	private function compensate_pending( $attachment_id ) {
		$new_url = wp_get_attachment_url( $attachment_id );
		if ( ! $new_url ) {
			delete_post_meta( $attachment_id, '_w2p_rewrite_pending' );
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_url',
			);
		}

		$result = $this->rewrite_content_fallback( $attachment_id, $new_url );

		// 仅在回写成功（含 no_parent/no_match 等"确认无引用"场景，success 均为 true）时清除
		// 待补偿标记；DB 更新失败（success=false）时保留标记，下批再试，避免内容 URL 永久残留。
		if ( ! empty( $result['success'] ) ) {
			delete_post_meta( $attachment_id, '_w2p_rewrite_pending' );
		}

		// 补清理：仅当确认已 offload 且满足清理条件时才允许删除本地原文件
		if ( $this->should_cleanup( $attachment_id ) ) {
			$this->metadata->cleanup_original( $attachment_id );
		}

		return $result;
	}

	/**
	 * 基于原文件名（stem）对父文章做正则替换，把残留的本地旧引用替换为 $new_url。
	 *
	 * 旧引用目录取自 attached_file 的相对路径；旧扩展名用常见格式（jpg/jpeg/png/gif）
	 * 匹配——转换后原始扩展名已丢失，只能用 stem + 常见后缀做宽松匹配。
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $new_url       New URL.
	 * @return array
	 */
	private function rewrite_content_fallback( $attachment_id, $new_url ) {
		global $wpdb;

		$post_parent = wp_get_post_parent_id( $attachment_id );
		if ( ! $post_parent ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_parent',
			);
		}

		$post = get_post( $post_parent );
		if ( ! $post || empty( $post->post_content ) ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_content',
			);
		}

		$original_name = get_post_meta( $attachment_id, '_w2p_original_file', true );
		if ( empty( $original_name ) ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_original_name',
			);
		}

		$stem = pathinfo( $original_name, PATHINFO_FILENAME );
		if ( $stem === '' ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_stem',
			);
		}

		$upload_dir = wp_get_upload_dir();
		$file_rel   = get_attached_file( $attachment_id );
		$rel_dir    = '';
		if ( $file_rel ) {
			$rel_dir = dirname( ltrim( str_replace( $upload_dir['basedir'], '', $file_rel ), '/\\' ) );
			if ( '.' === $rel_dir ) {
				$rel_dir = '';
			}
		}
		// 相对路径形式（与 rewrite_content 的 pattern 结构对齐）：域名前缀可选，
		// 使残留的绝对 URL 引用与相对路径引用（/wp-content/uploads/...）都能被补偿匹配
		$rel_old_dir = wp_make_link_relative( trailingslashit( $upload_dir['baseurl'] ) . ( $rel_dir ? trailingslashit( $rel_dir ) : '' ) );

		$pattern = '/'
			. '(?:https?:\/\/[^\/]+)?'
			. preg_quote( $rel_old_dir, '/' )
			. preg_quote( $stem, '/' )
			. '(?:(?:-\d+x\d+)?(?:-scaled)?)?'
			. '\.(?:jpe?g|png|gif)'
			. '/i';

		// 替换串中的 $ 和 \ 需要转义，避免被当作 preg 反向引用
		$replacement = str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $new_url );

		$count       = 0;
		$new_content = preg_replace( $pattern, $replacement, $post->post_content, -1, $count );

		if ( $count === 0 || $new_content === $post->post_content ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_match',
			);
		}

		$updated = $wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $new_content ),
			array( 'ID' => $post_parent )
		);
		// Targeted cache invalidation (see metadata-service update(): clean_post_cache()
		// fires the supercache hook → PHP Warning → X-QM-php_errors headers overflow
		// nginx fastcgi_buffer_size → 502).
		wp_cache_delete( $post_parent, 'posts' );
		wp_cache_delete( $post_parent, 'post_meta' );

		$result = array(
			'success'     => true,
			'replaced'    => true,
			'count'       => $count,
			'post_parent' => $post_parent,
		);

		if ( false === $updated ) {
			$result['success'] = false;
			$result['error']   = 'DB update failed';
		}

		return $result;
	}

	private function is_minio_available() {
		if ( $this->minio_available === null && $this->minio ) {
			$this->minio_available = $this->minio->is_available();
		}
		return $this->minio_available;
	}
}

// Backward compatibility alias.
if ( ! class_exists( 'MediaEngineProcessor', false ) ) {
	class_alias( 'W2P_Media_Engine_Processor', 'MediaEngineProcessor' );
}
