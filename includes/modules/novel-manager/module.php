<?php
/**
 * Novel Manager Module
 *
 * 小说与章节综合管理模组：文档批量导入、两阶段预览微调、章节顺序重构、分卷识别与级联维护。
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 提前引入模块业务类（确保 options.php 和 view 渲染时可用）
require_once __DIR__ . '/includes/class-novel-helper.php';
require_once __DIR__ . '/includes/class-novel-importer.php';
require_once __DIR__ . '/includes/class-novel-fixer.php';

class W2P_NovelManagerModule extends W2P_Abstract_Module {

	/**
	 * 模块唯一标识
	 */
	public static function id() {
		return 'novel-manager';
	}

	/**
	 * 模块名称
	 */
	public static function name() {
		return __( 'Novel Manager', 'wp-genius' );
	}

	/**
	 * 模块图标
	 */
	public static function icon() {
		return 'fa-solid fa-book';
	}

	/**
	 * 模块描述
	 */
	public static function description() {
		return __( 'Manage novel and chapter content types: import DOCX/TXT documents with preview, auto-identify volumes and chapter indexes, rebuild indexes, and manage related chapters.', 'wp-genius' );
	}

	/**
	 * 防御性依赖检查（核心规则）：
	 * 必须安装并启用 ACF 插件，且必须已注册 novel 和 chapter 的 post_type，否则禁止启用。
	 *
	 * @return true|WP_Error
	 */
	public function check_requirements() {
		$missing = array();

		// 1. 检查 ACF 插件是否启用
		if ( ! function_exists( 'acf' ) && ! class_exists( 'ACF' ) ) {
			$missing[] = __( 'Advanced Custom Fields (ACF) plugin is not active.', 'wp-genius' );
		}

		// 2. 检查 novel 与 chapter 自定义文章类型是否已注册
		if ( ! post_type_exists( 'novel' ) ) {
			/* translators: %s: post type name */
			$missing[] = sprintf( __( 'Custom post type "%s" is not registered.', 'wp-genius' ), 'novel' );
		}

		if ( ! post_type_exists( 'chapter' ) ) {
			/* translators: %s: post type name */
			$missing[] = sprintf( __( 'Custom post type "%s" is not registered.', 'wp-genius' ), 'chapter' );
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'w2p_novel_manager_requirements_failed',
				__( 'Novel Manager requires:', 'wp-genius' ) . ' ' . implode( ' ', $missing )
			);
		}

		return true;
	}

	/**
	 * 模块初始化
	 */
	public function init() {
		// 注册 Ajax 动作：文档导入与预览
		add_action( 'wp_ajax_w2p_novel_parse_file', array( $this, 'ajax_parse_file' ) );
		add_action( 'wp_ajax_w2p_novel_create_novel', array( $this, 'ajax_create_novel' ) );
		add_action( 'wp_ajax_w2p_novel_import_batch', array( $this, 'ajax_import_batch' ) );
		add_action( 'wp_ajax_w2p_novel_get_active_task', array( $this, 'ajax_get_active_task' ) );
		add_action( 'wp_ajax_w2p_novel_discard_active_task', array( $this, 'ajax_discard_active_task' ) );
		add_action( 'wp_ajax_w2p_novel_regen_indexes', array( $this, 'ajax_regenerate_indexes' ) );

		// 注册 Ajax 动作：章节顺序重构与分卷识别
		add_action( 'wp_ajax_w2p_novel_fix_search', array( $this, 'ajax_fix_search' ) );
		add_action( 'wp_ajax_w2p_novel_fix_get_chapters', array( $this, 'ajax_fix_get_chapters' ) );
		add_action( 'wp_ajax_w2p_novel_fix_apply_single', array( $this, 'ajax_fix_apply_single' ) );
		add_action( 'wp_ajax_w2p_novel_fix_get_unfixed_novels', array( $this, 'ajax_fix_get_unfixed_novels' ) );
		add_action( 'wp_ajax_w2p_novel_fix_auto_step', array( $this, 'ajax_fix_auto_step' ) );
		add_action( 'wp_ajax_w2p_novel_fix_clear_progress', array( $this, 'ajax_fix_clear_progress' ) );

		// 静态资源加载
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// 级联删除与行快捷操作
		add_action( 'trashed_post', array( $this, 'cascade_trash_chapters' ) );
		add_action( 'before_delete_post', array( $this, 'cascade_delete_chapters' ) );
		add_filter( 'post_row_actions', array( $this, 'add_novel_delete_row_action' ), 10, 2 );
		add_action( 'wp_ajax_w2p_novel_delete_batch', array( $this, 'ajax_novel_delete_batch' ) );
		add_action( 'wp_ajax_w2p_novel_delete_final', array( $this, 'ajax_novel_delete_final' ) );
	}

	/**
	 * 静态资产注册与参数注入
	 */
	public function enqueue_admin_scripts( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_settings   = false !== strpos( $screen->id, 'wp-genius-settings' );
		$is_edit_novel = 'edit-novel' === $screen->id;

		if ( ! $is_settings && ! $is_edit_novel ) {
			return;
		}

		// 启用 WP 原生媒体库弹窗（用于封面上传选择）
		wp_enqueue_media();

		$module_url = plugin_dir_url( __FILE__ );

		$css_file = plugin_dir_path( __FILE__ ) . 'assets/css/admin.css';
		$js_file  = plugin_dir_path( __FILE__ ) . 'assets/js/novel-manager.js';

		wp_enqueue_style(
			'w2p-novel-manager-css',
			$module_url . 'assets/css/admin.css',
			array(),
			file_exists( $css_file ) ? filemtime( $css_file ) : W2P_VERSION
		);

		wp_enqueue_script(
			'w2p-novel-manager-js',
			$module_url . 'assets/js/novel-manager.js',
			array( 'jquery' ),
			file_exists( $js_file ) ? filemtime( $js_file ) : W2P_VERSION,
			true
		);

		wp_localize_script(
			'w2p-novel-manager-js',
			'w2pNovelParams',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'importNonce' => wp_create_nonce( 'w2p_novel_import_nonce' ),
				'fixNonce'    => wp_create_nonce( 'w2p_novel_fix_nonce' ),
				'deleteNonce' => wp_create_nonce( 'w2p_novel_manager_delete' ),
				'i18n'        => array(
					'parsing'             => __( 'Parsing document, please wait...', 'wp-genius' ),
					'parseSuccess'        => __( 'File parsed successfully! You can review and adjust the chapter details below before importing.', 'wp-genius' ),
					'importingNovel'      => __( 'Creating Novel record...', 'wp-genius' ),
					/* translators: %1$s: current count, %2$s: total count */
					'importingChapter'    => __( 'Importing chapters: %1$s / %2$s', 'wp-genius' ),
					'importSuccess'       => __( 'Import complete! Successfully published novel and all chapters.', 'wp-genius' ),
					'selectCover'         => __( 'Select Novel Cover', 'wp-genius' ),
					'useImage'            => __( 'Use as Cover', 'wp-genius' ),
					'confirmDelete'       => __( 'Are you sure you want to remove this chapter from the import list?', 'wp-genius' ),
					'indexRegenerated'    => __( 'Chapter indexes regenerated successfully.', 'wp-genius' ),
					'confirmAutoFix'      => __( 'Start automatic processing? This will scan and execute in batches until all chapters are processed.', 'wp-genius' ),
					'confirmClear'        => __( 'Clear all processed book records? Next scan will start from the beginning.', 'wp-genius' ),
					'stop'                => __( 'Stop', 'wp-genius' ),
					'stopping'            => __( 'Stopping...', 'wp-genius' ),
					'regenIndex'          => __( 'Regenerate Chapter Index', 'wp-genius' ),
					'save'                => __( 'Save', 'wp-genius' ),
					/* translators: %d: record count */
					'scanComplete'        => __( 'Scan complete: %d records found.', 'wp-genius' ),
					'deletingNovel'       => __( 'Deleting Novel', 'wp-genius' ),
					'chaptersToDelete'    => __( 'Chapters to delete', 'wp-genius' ),
					'countingChapters'    => __( 'Counting related chapters...', 'wp-genius' ),
					'deletingChapters'    => __( 'Deleting chapters', 'wp-genius' ),
					'deletingNovelItself' => __( 'Deleting the novel itself...', 'wp-genius' ),
					'confirmDeleteNovel'  => __( 'Delete this novel AND all of its chapters? This cannot be undone.', 'wp-genius' ),
				),
			)
		);
	}

	/**
	 * AJAX: 解析上传的文档（Phase 1: Parse Only）
	 */
	public function ajax_parse_file() {
		check_ajax_referer( 'w2p_novel_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		if ( ! isset( $_FILES['novel_file'] ) ) {
			wp_send_json_error( __( 'No file was uploaded.', 'wp-genius' ) );
		}

		$importer = new W2P_Novel_Importer();
		$result   = $importer->parse_uploaded_file( $_FILES['novel_file'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: 创建或更新 Novel 主文章（Phase 2 Step 1）
	 */
	public function ajax_create_novel() {
		check_ajax_referer( 'w2p_novel_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$tags = array();
		if ( isset( $_POST['tags'] ) ) {
			$raw_tags = (array) $_POST['tags'];
			$tags     = array_filter( array_map( 'sanitize_text_field', wp_unslash( $raw_tags ) ) );
		}

		$novel_data = array(
			'title'          => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'content'        => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '',
			'category_ids'   => isset( $_POST['category_ids'] ) ? array_map( 'absint', (array) $_POST['category_ids'] ) : array(),
			'tag_ids'        => isset( $_POST['tag_ids'] ) ? array_map( 'absint', (array) $_POST['tag_ids'] ) : array(),
			'tags'           => $tags,
			'author_name'    => isset( $_POST['author_name'] ) ? sanitize_text_field( wp_unslash( $_POST['author_name'] ) ) : '',
			'cover_id'       => isset( $_POST['cover_id'] ) ? absint( $_POST['cover_id'] ) : 0,
			'author_id'      => isset( $_POST['author_id'] ) ? absint( $_POST['author_id'] ) : get_current_user_id(),
			'novel_id'       => isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0,
			'novel_status'   => isset( $_POST['novel_status'] ) ? sanitize_text_field( wp_unslash( $_POST['novel_status'] ) ) : '已完结',
			'task_id'        => isset( $_POST['task_id'] ) ? sanitize_file_name( wp_unslash( $_POST['task_id'] ) ) : '',
			'total_chapters' => isset( $_POST['total_chapters'] ) ? absint( $_POST['total_chapters'] ) : 0,
		);

		$importer = new W2P_Novel_Importer();
		$novel_id = $importer->create_or_update_novel( $novel_data );

		if ( is_wp_error( $novel_id ) ) {
			wp_send_json_error( $novel_id->get_error_message() );
		}

		wp_send_json_success( array( 'novel_id' => $novel_id ) );
	}

	/**
	 * AJAX: 批量导入章节（Phase 2 Step 2）
	 */
	public function ajax_import_batch() {
		check_ajax_referer( 'w2p_novel_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$novel_id  = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		$author_id = isset( $_POST['author_id'] ) ? absint( $_POST['author_id'] ) : get_current_user_id();
		$task_id   = isset( $_POST['task_id'] ) ? sanitize_file_name( wp_unslash( $_POST['task_id'] ) ) : '';

		if ( ! $novel_id || get_post_type( $novel_id ) !== 'novel' ) {
			wp_send_json_error( __( 'Invalid novel ID.', 'wp-genius' ) );
		}

		$raw_chapters = isset( $_POST['chapters'] ) ? wp_unslash( $_POST['chapters'] ) : '';
		$chapters     = json_decode( $raw_chapters, true );

		if ( empty( $chapters ) || ! is_array( $chapters ) ) {
			wp_send_json_error( __( 'No chapters to import.', 'wp-genius' ) );
		}

		$importer = new W2P_Novel_Importer();
		$res      = $importer->import_chapters_batch( $novel_id, $chapters, $author_id, $task_id );

		if ( empty( $res['success'] ) ) {
			wp_send_json_error( isset( $res['message'] ) ? $res['message'] : __( 'Import failed.', 'wp-genius' ) );
		}

		wp_send_json_success( $res );
	}

	/**
	 * AJAX: 获取未完成的断点续传任务
	 */
	public function ajax_get_active_task() {
		check_ajax_referer( 'w2p_novel_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$task = W2P_Novel_Importer::get_active_task();
		if ( empty( $task ) ) {
			wp_send_json_error( __( 'No active task found.', 'wp-genius' ) );
		}

		$temp_dir  = W2P_Novel_Importer::get_temp_dir();
		$json_file = $temp_dir . '/task_' . sanitize_file_name( $task['task_id'] ) . '.json';
		$chapters  = array();

		if ( file_exists( $json_file ) ) {
			$raw_data = file_get_contents( $json_file );
			$data     = json_decode( $raw_data, true );
			if ( ! empty( $data['chapters'] ) ) {
				$chapters = $data['chapters'];
			}
		}

		wp_send_json_success(
			array(
				'task'     => $task,
				'chapters' => $chapters,
			)
		);
	}

	/**
	 * AJAX: 放弃并清除未完成的断点续传任务
	 */
	public function ajax_discard_active_task() {
		check_ajax_referer( 'w2p_novel_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$task_id = isset( $_POST['task_id'] ) ? sanitize_file_name( wp_unslash( $_POST['task_id'] ) ) : '';
		W2P_Novel_Importer::discard_active_task( $task_id );
		wp_send_json_success( array( 'message' => __( 'Task discarded successfully.', 'wp-genius' ) ) );
	}

	/**
	 * AJAX: 重新计算并生成章节索引号 (00-00000)
	 */
	public function ajax_regenerate_indexes() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'w2p_novel_import_nonce' ) && ! wp_verify_nonce( $nonce, 'w2p_novel_fix_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'wp-genius' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$raw_chapters = isset( $_POST['chapters'] ) ? wp_unslash( $_POST['chapters'] ) : '';
		$chapters     = is_string( $raw_chapters ) ? json_decode( $raw_chapters, true ) : (array) $raw_chapters;

		if ( empty( $chapters ) || ! is_array( $chapters ) ) {
			wp_send_json_error( __( 'No chapters provided or invalid format.', 'wp-genius' ) );
		}

		$updated = W2P_Novel_Helper::recalculate_chapter_indexes( $chapters );
		wp_send_json_success( array( 'items' => $updated ) );
	}

	/**
	 * AJAX: 搜索小说（支持按 ID 或标题模糊查找）
	 */
	public function ajax_fix_search() {
		check_ajax_referer( 'w2p_novel_fix_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';
		$fixer = new W2P_Novel_Fixer();
		$list  = $fixer->search_novels( $query );

		wp_send_json_success( array( 'novels' => $list ) );
	}

	/**
	 * AJAX: 获取指定小说的章节并生成新旧索引对比预览
	 */
	public function ajax_fix_get_chapters() {
		check_ajax_referer( 'w2p_novel_fix_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$novel_id = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		$fixer    = new W2P_Novel_Fixer();
		$preview  = $fixer->get_novel_chapters_preview( $novel_id );

		wp_send_json_success( $preview );
	}

	/**
	 * AJAX: 单书重建并保存所有章节索引与分卷 (支持自定义微调后的章节列表)
	 */
	public function ajax_fix_apply_single() {
		check_ajax_referer( 'w2p_novel_fix_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$novel_id        = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		$custom_chapters = array();

		if ( ! empty( $_POST['chapters'] ) ) {
			$raw_chapters = json_decode( wp_unslash( $_POST['chapters'] ), true );
			if ( is_array( $raw_chapters ) ) {
				foreach ( $raw_chapters as $c ) {
					if ( isset( $c['id'] ) ) {
						$custom_chapters[] = array(
							'id'         => absint( $c['id'] ),
							'new_index'  => isset( $c['new_index'] ) ? sanitize_text_field( $c['new_index'] ) : '',
							'new_volume' => isset( $c['new_volume'] ) ? sanitize_text_field( $c['new_volume'] ) : '',
						);
					}
				}
			}
		}

		$fixer  = new W2P_Novel_Fixer();
		$result = $fixer->save_novel_chapters_custom( $novel_id, $custom_chapters );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: 获取全量自动扫描待处理的小说列表
	 */
	public function ajax_fix_get_unfixed_novels() {
		check_ajax_referer( 'w2p_novel_fix_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$fixer  = new W2P_Novel_Fixer();
		$novels = $fixer->get_unfixed_novels();

		$finished_ids   = get_option( 'w2p_fix_index_finished_books', array() );
		$finished_count = is_array( $finished_ids ) ? count( $finished_ids ) : 0;

		wp_send_json_success(
			array(
				'unfixed'        => $novels,
				'unfixed_count'  => count( $novels ),
				'finished_count' => $finished_count,
			)
		);
	}

	/**
	 * AJAX: 全自动扫描步进执行单本小说重建
	 */
	public function ajax_fix_auto_step() {
		check_ajax_referer( 'w2p_novel_fix_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$novel_id = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		$fixer    = new W2P_Novel_Fixer();
		$result   = $fixer->fix_single_novel( $novel_id );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: 清除已完成小说记录
	 */
	public function ajax_fix_clear_progress() {
		check_ajax_referer( 'w2p_novel_fix_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$fixer = new W2P_Novel_Fixer();
		$fixer->clear_progress();
		wp_send_json_success( array( 'message' => __( 'Progress records cleared.', 'wp-genius' ) ) );
	}

	// =========================================================================
	// 级联删除与辅助方法
	// =========================================================================

	public function add_novel_delete_row_action( $actions, $post ) {
		if ( 'novel' !== $post->post_type || ! current_user_can( 'delete_post', $post->ID ) || 'trash' === $post->post_status ) {
			return $actions;
		}

		$redirect_to = urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$url         = wp_nonce_url(
			admin_url( 'admin-post.php?action=w2p_delete_novel_with_chapters&post_id=' . $post->ID . '&redirect_to=' . $redirect_to ),
			'w2p_delete_novel_with_chapters_' . $post->ID
		);

		$actions['w2p_delete_novel_with_chapters'] = sprintf(
			'<a href="%s" class="delete w2p-delete-novel-with-chapters-btn" style="color:#b32d2e;">%s</a>',
			esc_url( $url ),
			esc_html__( 'Delete w/ Chapters', 'wp-genius' )
		);

		return $actions;
	}

	public function cascade_trash_chapters( $post_id ) {
		if ( 'novel' !== get_post_type( $post_id ) ) {
			return;
		}
		set_time_limit( 0 );
		while ( true ) {
			$ids = $this->get_novel_chapter_ids( $post_id, 100 );
			if ( empty( $ids ) ) {
				break;
			}
			foreach ( $ids as $chapter_id ) {
				wp_trash_post( $chapter_id );
			}
		}
	}

	public function cascade_delete_chapters( $post_id ) {
		if ( 'novel' !== get_post_type( $post_id ) ) {
			return;
		}
		set_time_limit( 0 );
		while ( true ) {
			$ids = $this->get_novel_chapter_ids( $post_id, 100 );
			if ( empty( $ids ) ) {
				break;
			}
			foreach ( $ids as $chapter_id ) {
				wp_delete_post( $chapter_id, true );
			}
		}
	}

	private function get_novel_chapter_ids( $novel_id, $limit = 0 ) {
		global $wpdb;
		$sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'related_novel_id' AND meta_value = %d";
		if ( $limit > 0 ) {
			$sql .= ' LIMIT ' . (int) $limit;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $novel_id ) );
		return array_map( 'absint', $ids );
	}

	private function count_novel_chapters( $novel_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'related_novel_id' AND meta_value = %d",
				$novel_id
			)
		);
	}

	public function ajax_novel_delete_batch() {
		set_time_limit( 0 );
		check_ajax_referer( 'w2p_novel_manager_delete', 'nonce' );

		$novel_id = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) || ! current_user_can( 'delete_post', $novel_id ) ) {
			wp_send_json_error( __( 'Invalid novel or permission denied.', 'wp-genius' ) );
		}

		$total         = $this->count_novel_chapters( $novel_id );
		$batch         = 50;
		$chapter_ids   = $this->get_novel_chapter_ids( $novel_id, $batch );
		$batch_deleted = 0;

		foreach ( $chapter_ids as $chapter_id ) {
			if ( wp_delete_post( $chapter_id, true ) ) {
				++$batch_deleted;
			}
		}

		wp_send_json_success(
			array(
				'total'         => $total,
				'batch_deleted' => $batch_deleted,
				'done'          => $batch_deleted < $batch,
			)
		);
	}

	public function ajax_novel_delete_final() {
		set_time_limit( 0 );
		check_ajax_referer( 'w2p_novel_manager_delete', 'nonce' );

		$novel_id = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) || ! current_user_can( 'delete_post', $novel_id ) ) {
			wp_send_json_error( __( 'Invalid novel or permission denied.', 'wp-genius' ) );
		}

		while ( true ) {
			$ids = $this->get_novel_chapter_ids( $novel_id, 50 );
			if ( empty( $ids ) ) {
				break;
			}
			foreach ( $ids as $chapter_id ) {
				wp_delete_post( $chapter_id, true );
			}
		}

		$deleted = wp_delete_post( $novel_id, true );
		if ( ! $deleted ) {
			wp_send_json_error( __( 'Failed to delete the novel.', 'wp-genius' ) );
		}

		wp_send_json_success();
	}
}
