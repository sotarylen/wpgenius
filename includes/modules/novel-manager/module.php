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
require_once __DIR__ . '/includes/class-novel-stats.php';

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
	 * 获取模组详细依赖项状态（用于环境检测页面展示）
	 *
	 * @return array 依赖检测结构化信息
	 */
	public static function get_requirements_status() {
		$acf_active = function_exists( 'acf' ) || class_exists( 'ACF' );
		$has_novel  = post_type_exists( 'novel' );
		$has_chap   = post_type_exists( 'chapter' );

		return array(
			'module_id'   => 'novel-manager',
			'module_name' => __( 'Novel Manager', 'wp-genius' ),
			'all_passed'  => $acf_active && $has_novel && $has_chap,
			'items'       => array(
				array(
					'name'        => __( 'Advanced Custom Fields (ACF)', 'wp-genius' ),
					'required'    => true,
					'status'      => $acf_active,
					'description' => __( 'Required for storing chapter indexes and relationships.', 'wp-genius' ),
					'action_url'  => $acf_active ? '' : admin_url( 'plugin-install.php?s=Advanced+Custom+Fields&tab=search&type=term' ),
					'action_text' => $acf_active ? '' : __( 'Install ACF', 'wp-genius' ),
				),
				array(
					/* translators: %s: post type slug. */
					'name'        => sprintf( __( 'Custom Post Type: "%s"', 'wp-genius' ), 'novel' ),
					'required'    => true,
					'status'      => $has_novel,
					'description' => __( 'Custom post type for novel entities.', 'wp-genius' ),
					'action_url'  => $has_novel ? '' : ( $acf_active ? admin_url( 'edit.php?post_type=acf-post-type' ) : '' ),
					'action_text' => $has_novel ? '' : __( 'Create Post Type', 'wp-genius' ),
				),
				array(
					/* translators: %s: post type slug. */
					'name'        => sprintf( __( 'Custom Post Type: "%s"', 'wp-genius' ), 'chapter' ),
					'required'    => true,
					'status'      => $has_chap,
					'description' => __( 'Custom post type for chapter entities.', 'wp-genius' ),
					'action_url'  => $has_chap ? '' : ( $acf_active ? admin_url( 'edit.php?post_type=acf-post-type' ) : '' ),
					'action_text' => $has_chap ? '' : __( 'Create Post Type', 'wp-genius' ),
				),
			),
		);
	}

	/**
	 * 模块初始化
	 */
	public function init() {
		// 注册 Ajax 动作：文档导入与预览
		add_action( 'wp_ajax_w2p_novel_inspect_header', array( $this, 'ajax_inspect_header' ) );
		add_action( 'wp_ajax_w2p_novel_parse_file', array( $this, 'ajax_parse_file' ) );
		add_action( 'wp_ajax_w2p_novel_create_novel', array( $this, 'ajax_create_novel' ) );
		add_action( 'wp_ajax_w2p_novel_import_batch', array( $this, 'ajax_import_batch' ) );
		add_action( 'wp_ajax_w2p_novel_get_active_task', array( $this, 'ajax_get_active_task' ) );
		add_action( 'wp_ajax_w2p_novel_discard_active_task', array( $this, 'ajax_discard_active_task' ) );
		add_action( 'wp_ajax_w2p_novel_regen_indexes', array( $this, 'ajax_regenerate_indexes' ) );
		add_action( 'wp_ajax_w2p_novel_get_target_novel_info', array( $this, 'ajax_get_target_novel_info' ) );
		add_action( 'wp_ajax_w2p_novel_truncate_chapters', array( $this, 'ajax_truncate_chapters' ) );

		// 注册 Ajax 动作：章节顺序重构与分卷识别
		add_action( 'wp_ajax_w2p_novel_fix_search', array( $this, 'ajax_fix_search' ) );
		add_action( 'wp_ajax_w2p_novel_fix_get_chapters', array( $this, 'ajax_fix_get_chapters' ) );
		add_action( 'wp_ajax_w2p_novel_fix_apply_single', array( $this, 'ajax_fix_apply_single' ) );

		// 注册 Ajax 动作：章节字数统计与小说汇总回写
		add_action( 'wp_ajax_w2p_novel_stats_single', array( $this, 'ajax_stats_single' ) );
		add_action( 'wp_ajax_w2p_novel_stats_overview', array( $this, 'ajax_stats_overview' ) );
		add_action( 'wp_ajax_w2p_novel_stats_scan', array( $this, 'ajax_stats_scan_chapters' ) );
		add_action( 'wp_ajax_w2p_novel_stats_sync_batch', array( $this, 'ajax_stats_sync_novels' ) );

		// 静态资源加载
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		$this->init_stats_tracking();

		// 级联删除与行快捷操作 (受选项开关控制，默认开启)
		$settings      = W2P_Settings::tab( 'novel_manager_tabs' );
		$enable_delete = isset( $settings['novel_delete_with_chapters'] ) ? (bool) $settings['novel_delete_with_chapters'] : true;

		if ( $enable_delete ) {
			add_action( 'trashed_post', array( $this, 'cascade_trash_chapters' ) );
			add_action( 'before_delete_post', array( $this, 'cascade_delete_chapters' ) );
			add_filter( 'post_row_actions', array( $this, 'add_novel_delete_row_action' ), 10, 2 );
			add_action( 'post_submitbox_start', array( $this, 'add_novel_edit_page_delete_action' ) );
			add_action( 'wp_ajax_w2p_novel_delete_batch', array( $this, 'ajax_novel_delete_batch' ) );
			add_action( 'wp_ajax_w2p_novel_delete_final', array( $this, 'ajax_novel_delete_final' ) );
		}
	}

	/**
	 * 章节字数与书籍统计的自动维护
	 *
	 * 设计要点：
	 *  - chapter 保存时只刷新自身的字数字段（轻量）；
	 *  - 受影响的小说只进「脏队列」，在请求结束 (shutdown) 时统一汇总，
	 *    避免批量导入每章都重算整本书；
	 *  - 计划任务做兜底，确保异常中断残留的脏队列能被补跑。
	 *
	 * @return void
	 */
	private function init_stats_tracking() {
		// 注册 chapter 的只读字数字段（ACF 5/6 均兼容）
		add_action( 'acf/init', array( 'W2P_Novel_Stats', 'register_acf_fields' ) );
		add_action( 'acf/include_fields', array( 'W2P_Novel_Stats', 'register_acf_fields' ) );

		// 章节正文变更 -> 刷新该章字数，并标记所属小说待汇总
		// 优先级 99：必须晚于 ACF 的表单写库，否则后台保存时会被表单里的空值覆盖
		add_action( 'save_post_chapter', array( $this, 'on_chapter_saved' ), 99, 3 );

		// 小说被保存 -> 立即重算这一本（单篇触发，代价是一次 JOIN 聚合）
		// 同样排在 ACF 之后：编辑页若带着空的 count-chapters / count-words 提交，
		// 先让 ACF 写空，再由这里用真实统计值覆盖回来。
		add_action( 'save_post_novel', array( $this, 'on_novel_saved' ), 99, 3 );

		// 小说编辑页发布框内的「重算统计」入口
		add_action( 'post_submitbox_start', array( $this, 'render_novel_stats_button' ) );

		// 章节状态变更（回收/删除/还原）-> 所属小说的可见章节数会变
		add_action( 'trashed_post', array( $this, 'on_chapter_trashed' ) );
		add_action( 'untrashed_post', array( $this, 'on_chapter_trashed' ) );
		add_action( 'before_delete_post', array( $this, 'on_chapter_trashed' ) );

		// 章节被改挂到别的小说 -> 新旧两本书都要重新汇总
		add_filter( 'update_post_metadata', array( $this, 'track_related_novel_change' ), 10, 5 );

		// 请求结束统一刷新脏队列
		add_action( 'shutdown', array( 'W2P_Novel_Stats', 'flush_dirty' ), 20 );

		// 计划任务兜底
		add_action( 'w2p_novel_stats_flush_cron', array( 'W2P_Novel_Stats', 'flush_dirty' ) );
		if ( ! wp_next_scheduled( 'w2p_novel_stats_flush_cron' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'w2p_novel_stats_flush_cron' );
		}
	}

	/**
	 * 模组停用：收尾并清理统计相关调度
	 *
	 * @return void
	 */
	public function deactivate() {
		// 先把残留的脏队列跑完，避免停用后数据卡在半路
		W2P_Novel_Stats::flush_dirty();

		$timestamp = wp_next_scheduled( 'w2p_novel_stats_flush_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'w2p_novel_stats_flush_cron' );
		}

		delete_option( W2P_Novel_Stats::DIRTY_OPTION );
	}

	/**
	 * 章节保存：刷新自身字数 + 标记所属小说
	 *
	 * @param int      $post_id
	 * @param WP_Post  $post
	 * @param bool     $update
	 * @return void
	 */
	public function on_chapter_saved( $post_id, $post, $update ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post || 'chapter' !== $post->post_type ) {
			return;
		}

		W2P_Novel_Stats::refresh_chapter_words( $post_id );

		$novel_id = W2P_Novel_Stats::get_chapter_novel_id( $post_id );
		if ( $novel_id ) {
			W2P_Novel_Stats::mark_dirty( $novel_id );
		}
	}

	/**
	 * 小说保存：立即重算该本的章节数与字数
	 *
	 * 这是「单篇触发」的主入口——只要在小说编辑页点一次「更新」，
	 * count-chapters / count-words 就会被真实统计值填上，不依赖全站回填。
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @param bool    $update
	 * @return void
	 */
	public function on_novel_saved( $post_id, $post, $update ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post || 'novel' !== $post->post_type ) {
			return;
		}

		// 回收站里的书不统计
		if ( 'trash' === $post->post_status ) {
			return;
		}

		W2P_Novel_Stats::sync_novel( $post_id );
	}

	/**
	 * 小说编辑页发布框：输出「重算统计」按钮与状态位
	 *
	 * 按钮走 ajax_stats_single，成功后由 JS 直接把数值写回
	 * ACF 的 count-chapters / count-words 输入框，无需刷新页面。
	 *
	 * @return void
	 */
	public function render_novel_stats_button() {
		global $post;

		if ( ! $post || 'novel' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		// 新建未落库的书没有 ID，统计无意义
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$stored_chapters = get_post_meta( $post->ID, W2P_Novel_Stats::NOVEL_CHAPTERS_KEY, true );
		$stored_words    = get_post_meta( $post->ID, W2P_Novel_Stats::NOVEL_WORDS_KEY, true );

		$has_stats = ( '' !== $stored_chapters && '' !== $stored_words );
		?>
		<div id="w2p-novel-stats-wrap" class="w2p-novel-stats-wrap">
			<span id="w2p-novel-stats-status" class="w2p-novel-stats-status">
				<?php
				if ( $has_stats ) {
					printf(
						/* translators: 1: chapter count, 2: word count */
						esc_html__( 'Stored: %1$s chapters / %2$s words', 'wp-genius' ),
						esc_html( number_format_i18n( (int) $stored_chapters ) ),
						esc_html( number_format_i18n( (int) $stored_words ) )
					);
				} else {
					esc_html_e( 'Statistics not calculated yet.', 'wp-genius' );
				}
				?>
			</span>
			<a href="#" id="w2p-novel-stats-recalc" class="w2p-novel-stats-recalc-btn"
				data-novel-id="<?php echo esc_attr( $post->ID ); ?>"
				data-needs-sync="<?php echo $has_stats ? '0' : '1'; ?>">
				<?php esc_html_e( 'Recalculate Stats', 'wp-genius' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * 章节回收/删除/还原：标记所属小说
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function on_chapter_trashed( $post_id ) {
		if ( 'chapter' !== get_post_type( $post_id ) ) {
			return;
		}

		$novel_id = W2P_Novel_Stats::get_chapter_novel_id( $post_id );
		if ( $novel_id ) {
			W2P_Novel_Stats::mark_dirty( $novel_id );
		}
	}

	/**
	 * 所属书籍变更前的嗅探：把新旧两本书都标记为待汇总
	 *
	 * 必须走前置 filter（而非 updated_post_meta）——后置钩子触发时
	 * get_post_meta 读到的已经是新值，旧书记不住。
	 *
	 * @param null|bool $check      拦截标记，返回 null 表示放行正常流程
	 * @param int       $post_id
	 * @param string    $meta_key
	 * @param mixed     $meta_value 即将写入的新值
	 * @param mixed     $prev_value
	 * @return null|bool
	 */
	public function track_related_novel_change( $check, $post_id, $meta_key, $meta_value, $prev_value ) {
		if ( W2P_Novel_Stats::RELATED_KEY !== $meta_key || 'chapter' !== get_post_type( $post_id ) ) {
			return $check;
		}

		$old = (string) get_post_meta( $post_id, $meta_key, true );

		if ( $old !== (string) $meta_value ) {
			W2P_Novel_Stats::mark_dirty( absint( $meta_value ) );
			W2P_Novel_Stats::mark_dirty( absint( $old ) );
		}

		return $check;
	}

	/**
	 * AJAX: 单本小说的统计重算
	 */
	/**
	 * 统计概览（懒加载端点）
	 *
	 * 概览是 30 万章级别的聚合查询，不允许随页面渲染执行——
	 * 只在用户真正打开统计 Tab 时由 JS 调一次。
	 */
	public function ajax_stats_overview() {
		check_ajax_referer( 'w2p_novel_stats_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$overview = W2P_Novel_Stats::get_overview();

		wp_send_json_success(
			array(
				'chapter_total'  => (int) $overview['chapter_total'],
				'chapter_synced' => (int) $overview['chapter_synced'],
				'novel_total'    => (int) $overview['novel_total'],
				'novel_synced'   => (int) $overview['novel_synced'],
				'dirty'          => count( W2P_Novel_Stats::get_dirty_ids() ),
			)
		);
	}

	public function ajax_stats_single() {
		check_ajax_referer( 'w2p_novel_stats_nonce', 'nonce' );

		$novel_id = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) ) {
			wp_send_json_error( __( 'Invalid novel ID.', 'wp-genius' ) );
		}

		// 设置页里的批量工具要求 manage_options；编辑页的单本按钮只要有权编辑这本书
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_post', $novel_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$res = W2P_Novel_Stats::recount_novel( $novel_id );
		if ( false === $res ) {
			wp_send_json_error( __( 'Failed to calculate statistics.', 'wp-genius' ) );
		}

		wp_send_json_success(
			array(
				'novel_id'  => $novel_id,
				'chapters'  => $res['chapters'],
				'words'     => $res['words'],
				'changed'   => $res['changed'],
				'scanned'   => $res['scanned'],
				'refreshed' => $res['refreshed'],
				'message'   => sprintf(
					/* translators: 1: chapter count, 2: word count */
					__( 'Updated: %1$s chapters, %2$s words.', 'wp-genius' ),
					number_format( $res['chapters'] ),
					number_format( $res['words'] )
				),
			)
		);
	}

	/**
	 * AJAX: 分批缓存章节字数
	 */
	public function ajax_stats_scan_chapters() {
		check_ajax_referer( 'w2p_novel_stats_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$cursor = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;
		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 0;
		$limit  = $limit > 0 ? min( $limit, 2000 ) : W2P_Novel_Stats::DEFAULT_BATCH;

		$res = W2P_Novel_Stats::scan_chapters_batch( $cursor, $limit );
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: 分批汇总小说统计
	 */
	public function ajax_stats_sync_novels() {
		check_ajax_referer( 'w2p_novel_stats_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$cursor = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;
		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 0;
		$limit  = $limit > 0 ? min( $limit, 1000 ) : 100;

		$res = W2P_Novel_Stats::sync_novels_batch( $cursor, $limit );
		wp_send_json_success( $res );
	}

	/**
	 * 静态资产注册与参数注入
	 */
	public function enqueue_admin_scripts( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_settings     = false !== strpos( $screen->id, 'wp-genius-settings' );
		$is_edit_novel   = 'edit-novel' === $screen->id;
		$is_single_novel = ( 'novel' === $screen->post_type && 'post' === $screen->base );

		if ( ! $is_settings && ! $is_edit_novel && ! $is_single_novel ) {
			return;
		}

		$settings      = W2P_Settings::tab( 'novel_manager_tabs' );
		$enable_delete = isset( $settings['novel_delete_with_chapters'] ) ? (bool) $settings['novel_delete_with_chapters'] : true;

		// 说明：不再因「级联删除」开关关闭而跳过加载 ——
		// 小说编辑页还需要「重算统计」按钮的脚本。

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
			array( 'jquery', 'w2p-admin-ui' ),
			file_exists( $js_file ) ? filemtime( $js_file ) : W2P_VERSION,
			true
		);

		wp_localize_script(
			'w2p-novel-manager-js',
			'w2pNovelParams',
			array(
				'ajaxUrl'                  => admin_url( 'admin-ajax.php' ),
				'importNonce'              => wp_create_nonce( 'w2p_novel_import_nonce' ),
				'fixNonce'                 => wp_create_nonce( 'w2p_novel_fix_nonce' ),
				'deleteNonce'              => wp_create_nonce( 'w2p_novel_manager_delete' ),
				'statsNonce'               => wp_create_nonce( 'w2p_novel_stats_nonce' ),
				'enableDeleteWithChapters' => $enable_delete,
				'i18n'                     => array(
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
					'stop'                => __( 'Stop', 'wp-genius' ),
					'stopping'            => __( 'Stopping...', 'wp-genius' ),
					'regenIndex'          => __( 'Regenerate Chapter Index', 'wp-genius' ),
					'save'                => __( 'Save', 'wp-genius' ),
					'deleteNovel'         => __( 'Delete Novel & Chapters', 'wp-genius' ),
					'deletingNovel'       => __( 'Deleting Novel', 'wp-genius' ),
					'totalChapters'       => __( 'Total Chapters', 'wp-genius' ),
					'deletedChapters'     => __( 'Deleted', 'wp-genius' ),
					'remainingChapters'   => __( 'Remaining', 'wp-genius' ),
					'chaptersToDelete'    => __( 'Chapters to delete', 'wp-genius' ),
					'countingChapters'    => __( 'Counting related chapters...', 'wp-genius' ),
					'deletingChapters'    => __( 'Deleting chapters', 'wp-genius' ),
					'deletingNovelItself' => __( 'Deleting the novel itself...', 'wp-genius' ),
					'confirmDeleteNovel'  => __( 'Delete this novel AND all of its chapters? This cannot be undone.', 'wp-genius' ),
					'confirmCancel'       => __( 'Deletion is in progress. Are you sure you want to stop?', 'wp-genius' ),
					'done'                => __( 'Done', 'wp-genius' ),
					'cancel'              => __( 'Cancel', 'wp-genius' ),
					'keepWindowOpen'      => __( 'Please do not close this window until cleanup completes.', 'wp-genius' ),
					'deleteSuccess'       => __( 'Novel and chapters deleted successfully.', 'wp-genius' ),
					'docChangeDetected'   => __( 'Document change detected, please re-select and upload the document.', 'wp-genius' ),
					/* translators: %d: number of chapters. */
					'volumeUpdated'       => __( 'Volume updated for %d chapters.', 'wp-genius' ),
					// Statistics tab
					'statsSelectNovel'    => __( 'Search and select a novel first.', 'wp-genius' ),
					'statsSearching'      => __( 'Searching novels...', 'wp-genius' ),
					'statsNoResult'       => __( 'No matching novel found.', 'wp-genius' ),
					'statsCalculating'    => __( 'Calculating...', 'wp-genius' ),
					'statsScanningPhase'  => __( 'Phase 1: caching chapter word counts...', 'wp-genius' ),
					'statsSyncingPhase'   => __( 'Phase 2: aggregating novels...', 'wp-genius' ),
					/* translators: 1: processed count, 2: written count */
					'statsScanBatch'      => __( 'Chapters scanned: %1$s (updated %2$s)', 'wp-genius' ),
					/* translators: 1: processed count, 2: written count */
					'statsSyncBatch'      => __( 'Novels aggregated: %1$s (updated %2$s)', 'wp-genius' ),
					'statsDone'           => __( 'All statistics are up to date.', 'wp-genius' ),
					'statsStopped'        => __( 'Stopped. You can resume anytime.', 'wp-genius' ),
					'statsConfirmRun'     => __( 'This will recalculate statistics for the whole site. It may take a while, continue?', 'wp-genius' ),
					'statsFailed'         => __( 'Failed to calculate statistics. Please try again.', 'wp-genius' ),
					'statsSaved'          => __( 'Statistics updated.', 'wp-genius' ),
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
	 * AJAX: 预览检查文档头部信息（即时提取书名、作者、简介与分类）
	 */
	public function ajax_inspect_header() {
		check_ajax_referer( 'w2p_novel_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$header_text = '';

		// 1. 优先从 Base64 二进制切片解码（与全书导入保持权威一致的编码探测）
		if ( ! empty( $_POST['header_base64'] ) ) {
			$raw_bytes = base64_decode( wp_unslash( $_POST['header_base64'] ) );
			if ( false !== $raw_bytes && '' !== $raw_bytes ) {
				// 按换行符裁掉切片末尾可能残缺的多字节字符，杜绝末尾字节截断误判
				$last_nl = max( strrpos( $raw_bytes, "\n" ), strrpos( $raw_bytes, "\r" ) );
				if ( false !== $last_nl && $last_nl > 0 ) {
					$raw_bytes = substr( $raw_bytes, 0, $last_nl );
				}

				$encoding = mb_detect_encoding( $raw_bytes, array( 'UTF-8', 'GB18030', 'GBK', 'BIG5', 'ASCII' ), true );
				if ( $encoding && 'UTF-8' !== $encoding ) {
					$header_text = mb_convert_encoding( $raw_bytes, 'UTF-8', $encoding );
				} else {
					$header_text = $raw_bytes;
				}
			}
		}

		// 2. 兼容纯文本回退
		if ( empty( $header_text ) && isset( $_POST['header_text'] ) ) {
			$header_text = wp_unslash( $_POST['header_text'] );
		}

		// 去除 UTF-8 BOM（若有）
		if ( substr( $header_text, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$header_text = substr( $header_text, 3 );
		}

		$filename = isset( $_POST['filename'] ) ? sanitize_text_field( wp_unslash( $_POST['filename'] ) ) : '';

		$extracted = W2P_Novel_Helper::extract_author_and_intro( $header_text, $filename );

		$category_id = 0;
		if ( ! empty( $extracted['category'] ) ) {
			$term = get_term_by( 'name', $extracted['category'], 'category' );
			if ( ! $term ) {
				$term = get_term_by( 'slug', sanitize_title( $extracted['category'] ), 'category' );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$category_id = $term->term_id;
			}
		}

		wp_send_json_success(
			array(
				'title'       => $extracted['title'],
				'author'      => $extracted['author'],
				'intro'       => $extracted['intro'],
				'category'    => isset( $extracted['category'] ) ? $extracted['category'] : '',
				'category_id' => $category_id,
			)
		);
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
			'title'           => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'content'         => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '',
			'category_ids'    => isset( $_POST['category_ids'] ) ? array_map( 'absint', (array) $_POST['category_ids'] ) : array(),
			'tag_ids'         => isset( $_POST['tag_ids'] ) ? array_map( 'absint', (array) $_POST['tag_ids'] ) : array(),
			'tags'            => $tags,
			'author_name'     => isset( $_POST['author_name'] ) ? sanitize_text_field( wp_unslash( $_POST['author_name'] ) ) : '',
			'cover_id'        => isset( $_POST['cover_id'] ) ? absint( $_POST['cover_id'] ) : 0,
			'author_id'       => isset( $_POST['author_id'] ) ? absint( $_POST['author_id'] ) : get_current_user_id(),
			'novel_id'        => ! empty( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : ( ! empty( $_POST['existing_id'] ) ? absint( $_POST['existing_id'] ) : 0 ),
			'novel_status'    => isset( $_POST['novel_status'] ) ? sanitize_text_field( wp_unslash( $_POST['novel_status'] ) ) : '已完结',
			'import_strategy' => isset( $_POST['import_strategy'] ) ? sanitize_key( wp_unslash( $_POST['import_strategy'] ) ) : 'append',
			'task_id'         => isset( $_POST['task_id'] ) ? sanitize_file_name( wp_unslash( $_POST['task_id'] ) ) : '',
			'total_chapters'  => isset( $_POST['total_chapters'] ) ? absint( $_POST['total_chapters'] ) : 0,
		);

		$importer = new W2P_Novel_Importer();
		$novel_id = $importer->create_or_update_novel( $novel_data );

		if ( is_wp_error( $novel_id ) ) {
			wp_send_json_error( $novel_id->get_error_message() );
		}

		wp_send_json_success(
			array(
				'novel_id'  => $novel_id,
				'permalink' => get_permalink( $novel_id ),
			)
		);
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
	 * AJAX: 搜索小说（支持按 ID 或标题模糊查找，同时兼容修复模块与导入模块）
	 */
	public function ajax_fix_search() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'w2p_novel_fix_nonce' ) && ! wp_verify_nonce( $nonce, 'w2p_novel_import_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'wp-genius' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';
		$fixer = new W2P_Novel_Fixer();
		$list  = $fixer->search_novels( $query );

		wp_send_json_success( array( 'novels' => $list ) );
	}

	/**
	 * AJAX: 获取目标小说的现有信息与末章信息（供现有书籍导入、追加与重导使用）
	 */
	public function ajax_get_target_novel_info() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'w2p_novel_import_nonce' ) && ! wp_verify_nonce( $nonce, 'w2p_novel_fix_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'wp-genius' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$novel_id = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) ) {
			wp_send_json_error( __( 'Invalid novel ID.', 'wp-genius' ) );
		}

		$novel = get_post( $novel_id );
		$info  = W2P_Novel_Importer::get_novel_last_chapter_info( $novel_id );

		// 封面与作者
		$cover_url   = get_the_post_thumbnail_url( $novel_id, 'thumbnail' );
		$human_terms = wp_get_post_terms( $novel_id, 'humans' );
		$author_name = '';
		if ( ! empty( $human_terms ) && ! is_wp_error( $human_terms ) ) {
			$names       = wp_list_pluck( $human_terms, 'name' );
			$author_name = implode( ', ', $names );
		}

		wp_send_json_success(
			array(
				'id'             => $novel_id,
				'title'          => $novel->post_title,
				'cover_url'      => $cover_url ? $cover_url : '',
				'author_name'    => $author_name,
				'total_chapters' => $info['total_chapters'],
				'max_order'      => $info['max_order'],
				'last_index'     => $info['last_index'],
				'last_volume'    => $info['last_volume'],
				'last_title'     => $info['last_title'],
				'edit_link'      => get_edit_post_link( $novel_id ),
			)
		);
	}

	/**
	 * AJAX: 彻底清空指定小说的所有章节（清空重导高危操作，严格鉴权）
	 */
	public function ajax_truncate_chapters() {
		check_ajax_referer( 'w2p_novel_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$novel_id = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) ) {
			wp_send_json_error( __( 'Invalid novel ID.', 'wp-genius' ) );
		}

		$count = W2P_Novel_Importer::truncate_novel_chapters( $novel_id );

		wp_send_json_success(
			array(
				'novel_id'      => $novel_id,
				'deleted_count' => $count,
				'message'       => sprintf(
					/* translators: %d: number of deleted chapters */
					__( 'Successfully cleared %d existing chapters.', 'wp-genius' ),
					$count
				),
			)
		);
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
							'new_title'  => isset( $c['new_title'] ) ? sanitize_text_field( $c['new_title'] ) : '',
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
			'<a href="%s" class="delete w2p-delete-novel-with-chapters-btn">%s</a>',
			esc_url( $url ),
			esc_html__( 'Delete w/ Chapters', 'wp-genius' )
		);

		return $actions;
	}

	/**
	 * 在小说编辑页的发布框（Publish Box）中输出“Delete w/ Chapters”操作
	 */
	public function add_novel_edit_page_delete_action() {
		global $post;
		if ( ! $post || 'novel' !== $post->post_type || ! current_user_can( 'delete_post', $post->ID ) || 'trash' === $post->post_status ) {
			return;
		}
		?>
		<div id="w2p-novel-edit-delete-wrap" class="w2p-novel-edit-delete-wrap">
			<a href="#" class="submitdelete w2p-delete-novel-with-chapters-btn" data-novel-id="<?php echo esc_attr( $post->ID ); ?>" data-novel-title="<?php echo esc_attr( get_the_title( $post->ID ) ); ?>" data-is-single="1">
				<?php esc_html_e( 'Delete w/ Chapters', 'wp-genius' ); ?>
			</a>
		</div>
		<?php
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

		// %s 而非 %d：meta_value 是 LONGTEXT，整型比较会触发隐式转换令索引失效（实测 1.6s -> 1.6ms）
		$sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'related_novel_id' AND meta_value = %s";
		if ( $limit > 0 ) {
			$sql .= ' LIMIT ' . (int) $limit;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, (string) $novel_id ) );
		return array_map( 'absint', $ids );
	}

	private function count_novel_chapters( $novel_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'related_novel_id' AND meta_value = %s",
				(string) $novel_id
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
