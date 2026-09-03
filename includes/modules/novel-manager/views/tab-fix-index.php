<?php
/**
 * Novel Manager — Tab Fix Chapter Index View
 *
 * 章节顺序重构与分卷识别视图 (工作台双模式切换：全自动全量扫描 + 单书精准检查修复)
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$finished_books = get_option( 'w2p_fix_index_finished_books', array() );
$finished_count = is_array( $finished_books ) ? count( $finished_books ) : 0;
?>

<div id="w2p-tab-fix-index" class="w2p-wrapper">

	<!-- 工作流步骤卡片导航 (复用 Media Engine 经典卡片设计系统) -->
	<div class="w2p-workflow-steps-nav w2p-fix-steps-nav">
		<button type="button" class="w2p-workflow-step-btn active" data-mode="auto">
			<span class="w2p-step-num">1</span>
			<span class="w2p-step-info">
				<span class="w2p-step-title"><?php esc_html_e( 'Auto Rebuild All', 'wp-genius' ); ?></span>
				<span class="w2p-step-desc"><?php esc_html_e( 'Batch scan and rebuild chapter indexes automatically', 'wp-genius' ); ?></span>
			</span>
		</button>
		<button type="button" class="w2p-workflow-step-btn" data-mode="single">
			<span class="w2p-step-num">2</span>
			<span class="w2p-step-info">
				<span class="w2p-step-title"><?php esc_html_e( 'Inspect Single Novel', 'wp-genius' ); ?></span>
				<span class="w2p-step-desc"><?php esc_html_e( 'Search novel, preview and rebuild chapter indexes manually', 'wp-genius' ); ?></span>
			</span>
		</button>
	</div>

	<!-- ===================================================================== -->
	<!-- 模式 1: 全自动全量扫描 (Auto Rebuild All) -->
	<!-- ===================================================================== -->
	<div id="w2p-fix-pane-auto" class="w2p-section w2p-fix-mode-pane">
		<div class="w2p-section-body">
			<!-- 统计指示与操作工具栏（清理与全自动处理按钮置于同一行） -->
			<div class="w2p-fix-stats-bar">
				<div class="w2p-fix-stat-item">
					<span class="w2p-fix-stat-label"><?php esc_html_e( 'Processed Novels:', 'wp-genius' ); ?></span>
					<strong id="w2p-fix-stat-finished"><?php echo absint( $finished_count ); ?></strong>
				</div>
				<div class="w2p-fix-stat-item">
					<span class="w2p-fix-stat-label"><?php esc_html_e( 'Remaining Pending:', 'wp-genius' ); ?></span>
					<strong id="w2p-fix-stat-unfixed">-</strong>
				</div>
				<div class="w2p-fix-stat-action">
					<button type="button" id="w2p-fix-clear-progress-btn" class="w2p-btn w2p-btn-secondary">
						<i class="fa-solid fa-trash-can"></i> <?php esc_html_e( 'Clear Processed', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-fix-auto-start-btn" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-play"></i> <?php esc_html_e( 'Start Auto Rebuild', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-fix-auto-stop-btn" class="w2p-btn w2p-btn-danger w2p-hidden">
						<i class="fa-solid fa-stop"></i> <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
					</button>
				</div>
			</div>

			<!-- 全自动执行进度条 (初始化隐藏) -->
			<div id="w2p-fix-auto-progress" class="w2p-progress-container w2p-fix-progress-box w2p-hidden">
				<div class="w2p-progress-info">
					<span id="w2p-fix-auto-status"><?php esc_html_e( 'Ready for auto rebuild', 'wp-genius' ); ?></span>
					<span id="w2p-fix-auto-count">0 / 0</span>
				</div>
				<div class="w2p-progress-bar-bg">
					<div id="w2p-fix-auto-bar" class="w2p-progress-bar-fill"></div>
				</div>
			</div>

			<!-- 全自动执行日志表格 (初始化隐藏，复用单体搜索结果表格样式，无 Action 列) -->
			<div id="w2p-fix-auto-log-box" class="w2p-fix-results-table-wrap w2p-hidden">
				<table class="w2p-results-table widefat striped">
					<thead>
						<tr>
							<th width="90"><?php esc_html_e( 'Novel ID', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Novel Title', 'wp-genius' ); ?></th>
							<th width="130"><?php esc_html_e( 'Chapters', 'wp-genius' ); ?></th>
							<th width="120"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
						</tr>
					</thead>
					<tbody id="w2p-fix-auto-log-tbody">
						<!-- 运行时动态追加各书籍处理结果 -->
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ===================================================================== -->
	<!-- 模式 2: 单体小说章节排序与分卷修复 (Inspect Single Novel) -->
	<!-- ===================================================================== -->
	<div id="w2p-fix-pane-single" class="w2p-section w2p-fix-mode-pane w2p-hidden">
		<div class="w2p-section-body">
			<!-- 1. 搜索与定位输入栏 (输入即触发，无搜索按钮，保留清空小叉号) -->
			<div class="w2p-fix-search-bar">
				<div class="w2p-fix-search-input-wrap">
					<i class="fa-solid fa-magnifying-glass w2p-fix-search-icon"></i>
					<input type="text" id="w2p-fix-search-input" class="w2p-input-full" placeholder="<?php esc_attr_e( 'Enter novel title keyword or exact Novel ID (e.g. 102)...', 'wp-genius' ); ?>" autocomplete="off">
					<button type="button" id="w2p-fix-search-clear-btn" class="w2p-fix-clear-btn" title="<?php esc_attr_e( 'Clear', 'wp-genius' ); ?>">&times;</button>
				</div>
			</div>

			<!-- 2. 搜索结果列表 (即使只有一条记录也显示为标准列表，初始状态隐藏) -->
			<div id="w2p-fix-search-results-box" class="w2p-log-container w2p-fix-results-table-wrap w2p-hidden">
				<table class="w2p-preview-table">
					<thead>
						<tr>
							<th width="90"><?php esc_html_e( 'Novel ID', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Novel Title', 'wp-genius' ); ?></th>
							<th width="140"><?php esc_html_e( 'Chapters', 'wp-genius' ); ?></th>
							<th width="130"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
							<th width="110"><?php esc_html_e( 'Action', 'wp-genius' ); ?></th>
						</tr>
					</thead>
					<tbody id="w2p-fix-search-results-tbody">
						<!-- 动态渲染搜索匹配的小说 -->
					</tbody>
				</table>
			</div>

			<!-- 3. 待处理章节列表与工作台 (点击 Rebuild 后展开，初始状态隐藏) -->
			<div id="w2p-fix-novel-workbench" class="w2p-fix-workbench-container w2p-hidden">
				<!-- 表头可操作按钮 -->
				<div class="w2p-preview-toolbar w2p-fix-chapter-toolbar">
					<div class="w2p-toolbar-left">
						<button type="button" id="w2p-fix-batch-vol-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
							<i class="fa-solid fa-pen-to-square"></i> <?php esc_html_e( 'Batch Set Volume', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-fix-regen-index-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
							<i class="fa-solid fa-list-ol"></i> <?php esc_html_e( 'Regenerate Chapter Index', 'wp-genius' ); ?>
						</button>
					</div>
					<div class="w2p-toolbar-right">
						<button type="button" id="w2p-fix-save-novel-btn" class="w2p-btn w2p-btn-primary">
							<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save', 'wp-genius' ); ?>
						</button>
					</div>
				</div>

				<!-- 待处理章节表格：复选框，序号，旧index，推荐的index，旧分卷，推荐的分卷，章节标题，字符数 -->
				<div class="w2p-log-container w2p-preview-table-wrapper w2p-fix-table-wrapper">
					<table class="w2p-preview-table">
						<thead>
							<tr>
								<th width="40"><input type="checkbox" id="w2p-check-all-fix-chapters"></th>
								<th width="50"><?php esc_html_e( '#', 'wp-genius' ); ?></th>
								<th width="120"><?php esc_html_e( 'Old Index', 'wp-genius' ); ?></th>
								<th width="140"><?php esc_html_e( 'Recommended Index', 'wp-genius' ); ?></th>
								<th width="130"><?php esc_html_e( 'Old Volume', 'wp-genius' ); ?></th>
								<th width="150"><?php esc_html_e( 'Recommended Volume', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Chapter Title', 'wp-genius' ); ?></th>
								<th width="90"><?php esc_html_e( 'Words', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="w2p-fix-chapters-tbody">
							<!-- 动态渲染待处理章节 -->
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

</div>
