<?php
/**
 * Media Engine Processor
 *
 * 核心处理器:任务筛选、分批处理、多进程调度
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaEngineProcessor {

	/**
	 * 任务链执行器
	 *
	 * @var MediaEngineTaskChainExecutor
	 */
	private $executor;

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
		if ( ! class_exists( 'MediaEngineTaskChainExecutor' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-task-chain-executor.php';
		}
		if ( ! class_exists( 'MediaEngineConversionLogger' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-conversion-logger.php';
		}

		$this->executor = new MediaEngineTaskChainExecutor();
		$this->logger   = new MediaEngineConversionLogger();
		$this->settings = get_option( 'w2p_media_turbo_settings', [] );
	}

	/**
	 * 获取待处理的附件
	 *
	 * 查询未包含 _is_minio_offloaded 元数据的图片附件
	 *
	 * @param int $limit 限制数量
	 * @param int $offset 偏移量
	 * @return array 附件 ID 数组
	 */
	public function get_pending_attachments( $limit = 100, $offset = 0 ) {
		global $wpdb;

		// 获取支持的 MIME 类型
		$mime_types = $this->get_supported_mime_types();

		if ( empty( $mime_types ) ) {
			return [];
		}

		// 重要:包含 webp,因为图片可能已转换但未完成后续步骤(URL替换、offload等)
		$mime_types[] = 'image/webp';
		$mime_types = array_unique( $mime_types );

		$mime_placeholders = implode( ',', array_fill( 0, count( $mime_types ), '%s' ) );

		// 查询未处理的附件 (排除已 offload 到 Minio 的)
		// Advanced MiniO Offloader 使用 advmo_offloaded = 1 标记已 offload 的附件
		$query = $wpdb->prepare(
			"SELECT p.ID 
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = 'advmo_offloaded' AND pm.meta_value = '1')
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type IN ($mime_placeholders)
			AND pm.post_id IS NULL
			ORDER BY p.ID DESC
			LIMIT %d OFFSET %d",
			array_merge( $mime_types, [ $limit, $offset ] )
		);

		$results = $wpdb->get_col( $query );

		return array_map( 'intval', $results );
	}

	/**
	 * 获取支持的 MIME 类型
	 *
	 * @return array MIME 类型数组
	 */
	private function get_supported_mime_types() {
		$mime_types = [];

		// 检查设置中启用的格式
		if ( ! empty( $this->settings['convert_static'] ) || ( $this->settings['convert_static'] ?? '1' ) === '1' ) {
			$mime_types[] = 'image/jpeg';
			$mime_types[] = 'image/png';
		}

		if ( ! empty( $this->settings['convert_animated'] ) ) {
			$mime_types[] = 'image/gif';
		}

		return $mime_types;
	}

	/**
	 * 处理单个附件
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array 处理结果
	 */
	public function process_attachment( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return [
				'success' => false,
				'error'   => __( 'File not found', 'wp-genius' ),
			];
		}

		// 检查文件大小限制
		$min_size = ( $this->settings['min_file_size'] ?? 0 ) * 1024; // KB to bytes
		if ( filesize( $file_path ) < $min_size ) {
			$this->logger->log_skipped(
				$attachment_id,
				$file_path,
				sprintf(
					/* translators: %d: minimum file size in KB */
					__( 'File size below minimum (%d KB)', 'wp-genius' ),
					$min_size / 1024
				)
			);

			return [
				'success' => false,
				'error'   => __( 'File size below minimum', 'wp-genius' ),
			];
		}

		// 执行任务链
		return $this->executor->execute_chain( $attachment_id );
	}

	/**
	 * 批量处理附件
	 *
	 * @param int $limit 批次大小
	 * @param int $offset 偏移量
	 * @return array 处理结果统计
	 */
	public function batch_process( $limit = 100, $offset = 0 ) {
		$start_time = microtime( true );

		$attachments = $this->get_pending_attachments( $limit, $offset );
		$total       = count( $attachments );

		if ( $total === 0 ) {
			return [
				'success'   => true,
				'processed' => 0,
				'succeeded' => 0,
				'failed'    => 0,
				'message'   => __( 'No pending attachments found', 'wp-genius' ),
			];
		}

		$this->logger->log_batch_start( $total, $limit );

		$succeeded = 0;
		$failed    = 0;

		foreach ( $attachments as $attachment_id ) {
			$result = $this->process_attachment( $attachment_id );

			if ( $result['success'] ) {
				$succeeded++;
			} else {
				$failed++;
			}
		}

		$duration = microtime( true ) - $start_time;
		$this->logger->log_batch_complete( $total, $succeeded, $failed, $duration );

		return [
			'success'   => true,
			'processed' => $total,
			'succeeded' => $succeeded,
			'failed'    => $failed,
			'duration'  => $duration,
		];
	}

	/**
	 * 并行处理附件
	 *
	 * 使用多进程调度方案
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 * @param int   $max_workers 最大并发数
	 * @return array 处理结果
	 */
	public function parallel_process( $attachment_ids, $max_workers = null ) {
		if ( $max_workers === null ) {
			$max_workers = $this->get_recommended_workers();
		}

		$total      = count( $attachment_ids );
		$start_time = microtime( true );

		$this->logger->log_batch_start( $total, count( $attachment_ids ) );

		$workers   = [];
		$results   = [];
		$succeeded = 0;
		$failed    = 0;

		foreach ( $attachment_ids as $attachment_id ) {
			// 等待空闲 worker
			while ( count( $workers ) >= $max_workers ) {
				$this->wait_for_worker( $workers, $results, $succeeded, $failed );
			}

			// 启动新 worker
			$workers[ $attachment_id ] = $this->spawn_worker( $attachment_id );
		}

		// 等待所有 worker 完成
		while ( ! empty( $workers ) ) {
			$this->wait_for_worker( $workers, $results, $succeeded, $failed );
		}

		$duration = microtime( true ) - $start_time;
		$this->logger->log_batch_complete( $total, $succeeded, $failed, $duration );

		return [
			'success'   => true,
			'processed' => $total,
			'succeeded' => $succeeded,
			'failed'    => $failed,
			'duration'  => $duration,
			'results'   => $results,
		];
	}

	/**
	 * 启动 worker 进程
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array Worker 信息
	 */
	private function spawn_worker( $attachment_id ) {
		$command = sprintf(
			'wp eval "do_action(\'w2p_process_attachment\', %d);" 2>&1',
			$attachment_id
		);

		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

		$pipes   = [];
		$process = proc_open( $command, $descriptors, $pipes );

		if ( is_resource( $process ) ) {
			// 设置为非阻塞模式
			stream_set_blocking( $pipes[1], false );
			stream_set_blocking( $pipes[2], false );

			return [
				'process'       => $process,
				'pipes'         => $pipes,
				'attachment_id' => $attachment_id,
				'start_time'    => microtime( true ),
			];
		}

		return null;
	}

	/**
	 * 等待 worker 完成
	 *
	 * @param array $workers Worker 数组(引用)
	 * @param array $results 结果数组(引用)
	 * @param int   $succeeded 成功计数(引用)
	 * @param int   $failed 失败计数(引用)
	 */
	private function wait_for_worker( &$workers, &$results, &$succeeded, &$failed ) {
		foreach ( $workers as $attachment_id => $worker ) {
			if ( $worker === null ) {
				unset( $workers[ $attachment_id ] );
				continue;
			}

			$status = proc_get_status( $worker['process'] );

			if ( ! $status['running'] ) {
				// 读取输出
				$output = stream_get_contents( $worker['pipes'][1] );
				$error  = stream_get_contents( $worker['pipes'][2] );

				// 关闭管道和进程
				fclose( $worker['pipes'][0] );
				fclose( $worker['pipes'][1] );
				fclose( $worker['pipes'][2] );
				proc_close( $worker['process'] );

				// 记录结果
				$success = $status['exitcode'] === 0;
				$results[ $attachment_id ] = [
					'success' => $success,
					'output'  => $output,
					'error'   => $error,
				];

				if ( $success ) {
					$succeeded++;
				} else {
					$failed++;
				}

				unset( $workers[ $attachment_id ] );
				return;
			}
		}

		// 短暂休眠,避免 CPU 占用过高
		usleep( 100000 ); // 100ms
	}

	/**
	 * 获取推荐的并发数
	 *
	 * @return int 推荐的并发数
	 */
	private function get_recommended_workers() {
		if ( ! class_exists( 'MediaEngineEnvironmentChecker' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-environment-checker.php';
		}

		return MediaEngineEnvironmentChecker::get_recommended_workers();
	}

	/**
	 * 获取待处理附件总数
	 *
	 * 支持两种扫描模式:
	 * 1. 按文章扫描(posts): 查询最近 N 篇文章的附件 + 孤立附件
	 * 2. 按附件扫描(media): 查询最近 N 个附件
	 *
	 * @return int 待处理附件数量
	 */
	public function get_pending_count() {
		global $wpdb;

		$settings = get_option( 'w2p_media_turbo_settings', [] );
		$scan_mode = $settings['scan_mode'] ?? 'media';

		// 构建 MIME 类型列表
		$mimes = [];
		if ( ( $settings['convert_static'] ?? '1' ) === '1' ) {
			$mimes[] = 'image/jpeg';
			$mimes[] = 'image/png';
		}
		if ( ! empty( $settings['convert_animated'] ) ) {
			$mimes[] = 'image/gif';
		}

		if ( empty( $mimes ) ) {
			return 0;
		}

		$mime_placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );

		if ( $scan_mode === 'posts' ) {
			// 按文章扫描模式
			return $this->get_pending_count_by_posts( $mimes, $mime_placeholders );
		} else {
			// 按附件扫描模式
			return $this->get_pending_count_by_media( $mimes, $mime_placeholders );
		}
	}

	/**
	 * 按文章扫描模式获取待处理附件数
	 *
	 * @param array  $mimes MIME 类型数组
	 * @param string $mime_placeholders MIME 占位符
	 * @return int 待处理附件数量
	 */
	private function get_pending_count_by_posts( $mimes, $mime_placeholders ) {
		global $wpdb;

		$settings = get_option( 'w2p_media_turbo_settings', [] );
		$posts_limit = isset( $settings['posts_limit'] ) ? absint( $settings['posts_limit'] ) : 10;

		// 包含 webp,因为可能已转换但未完成后续步骤
		$mimes[] = 'image/webp';
		$mimes = array_unique( $mimes );
		$mime_placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );

		// 1. 获取最近 N 篇文章的 ID
		$recent_posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
				 WHERE post_type = 'post' 
				 AND post_status = 'publish'
				 ORDER BY ID DESC 
				 LIMIT %d",
				$posts_limit
			)
		);

		$attachment_ids = [];

		// 2. 获取这些文章的附件
		if ( ! empty( $recent_posts ) ) {
			$post_placeholders = implode( ',', array_fill( 0, count( $recent_posts ), '%d' ) );
			$query = "SELECT ID FROM {$wpdb->posts}
			          WHERE post_type = 'attachment'
			          AND post_mime_type IN ($mime_placeholders)
			          AND post_parent IN ($post_placeholders)";

			$args = array_merge( $mimes, $recent_posts );
			$post_attachments = $wpdb->get_col( $wpdb->prepare( $query, $args ) );
			$attachment_ids = array_merge( $attachment_ids, $post_attachments );
		}

		// 3. 额外加入最近 10 个孤立附件 (post_parent = 0)
		// 作为兜底,通常由手动上传的 PDF/ZIP/MP4 等文件
		$query = "SELECT ID FROM {$wpdb->posts}
		          WHERE post_type = 'attachment'
		          AND post_mime_type IN ($mime_placeholders)
		          AND post_parent = 0
		          ORDER BY ID DESC
		          LIMIT 10";

		$orphaned = $wpdb->get_col( $wpdb->prepare( $query, $mimes ) );
		$attachment_ids = array_merge( $attachment_ids, $orphaned );

		// 去重
		$attachment_ids = array_unique( $attachment_ids );

		if ( empty( $attachment_ids ) ) {
			return 0;
		}

		// 4. 检查这些附件中有多少未 offload
		$ids_placeholders = implode( ',', array_fill( 0, count( $attachment_ids ), '%d' ) );
		$query = "SELECT COUNT(p.ID)
		          FROM {$wpdb->posts} p
		          WHERE p.ID IN ($ids_placeholders)
		          AND NOT EXISTS (
		              SELECT 1 FROM {$wpdb->postmeta} pm 
		              WHERE pm.post_id = p.ID 
		              AND pm.meta_key = 'advmo_offloaded' 
		              AND pm.meta_value = '1'
		          )";

		return (int) $wpdb->get_var( $wpdb->prepare( $query, $attachment_ids ) );
	}

	/**
	 * 按附件扫描模式获取待处理附件数
	 *
	 * @param array  $mimes MIME 类型数组
	 * @param string $mime_placeholders MIME 占位符
	 * @return int 待处理附件数量
	 */
	private function get_pending_count_by_media( $mimes, $mime_placeholders ) {
		global $wpdb;

		$settings = get_option( 'w2p_media_turbo_settings', [] );
		$scan_limit = isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 1000;

		// 包含 webp,因为可能已转换但未完成后续步骤
		$mimes[] = 'image/webp';
		$mimes = array_unique( $mimes );
		$mime_placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );

		// 查询最近 N 个附件中未 offload 的数量
		$query = "SELECT COUNT(p.ID)
		          FROM (
		              SELECT ID 
		              FROM {$wpdb->posts}
		              WHERE post_type = 'attachment'
		              AND post_mime_type IN ($mime_placeholders)
		              ORDER BY ID DESC
		              LIMIT %d
		          ) p
		          LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = 'advmo_offloaded' AND pm.meta_value = '1')
		          WHERE pm.post_id IS NULL";

		$args = array_merge( $mimes, [ $scan_limit ] );
		return (int) $wpdb->get_var( $wpdb->prepare( $query, $args ) );
	}
}
