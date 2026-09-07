<?php
/**
 * Novel Manager — Tab Maintenance & Health Audit View
 *
 * 全库维护中心：孤儿章节诊断与清理、章节完整性与断号体检、全库批量重建索引。
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

	<!-- 板块 1：孤儿章节诊断与清理 (Orphan Chapters Diagnosis & Cleanup) -->
	<div id="w2p-pane-orphan-cleanup" class="w2p-section">
		<div class="w2p-section-header">
			<h3 class="w2p-section-title">
				<i class="fa-solid fa-broom"></i>
				<?php esc_html_e( 'Orphan Chapters Diagnosis & Cleanup', 'wp-genius' ); ?>
			</h3>
			<p class="w2p-section-desc">
				<?php esc_html_e( 'Scan and safely purge stranded chapters that lack related novel metadata or belong to deleted novels.', 'wp-genius' ); ?>
			</p>
		</div>
		<div class="w2p-section-body">
			<div class="w2p-fix-stats-bar">
				<div class="w2p-fix-stat-item">
					<span class="w2p-fix-stat-label"><?php esc_html_e( 'Orphans Found:', 'wp-genius' ); ?></span>
					<strong id="w2p-orphan-stat-count">-</strong>
				</div>
				<div class="w2p-fix-stat-action">
					<button type="button" id="w2p-orphan-scan-btn" class="w2p-btn w2p-btn-secondary">
						<i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e( 'Scan Orphan Chapters', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-orphan-clean-btn" class="w2p-btn w2p-btn-danger" disabled>
						<i class="fa-solid fa-trash-can"></i> <?php esc_html_e( 'Clean Selected Orphans', 'wp-genius' ); ?>
					</button>
				</div>
			</div>

			<!-- 孤儿章节提示与状态 (初始化隐藏) -->
			<div id="w2p-orphan-status-box" class="w2p-status-box w2p-hidden">
				<span id="w2p-orphan-status-text"></span>
			</div>

			<!-- 孤儿章节结果表格 (初始化隐藏) -->
			<div id="w2p-orphan-results-wrap" class="w2p-fix-results-table-wrap w2p-hidden">
				<table class="w2p-results-table widefat striped">
					<thead>
						<tr>
							<th width="40" class="check-column">
								<input type="checkbox" id="w2p-orphan-check-all" />
							</th>
							<th width="90"><?php esc_html_e( 'Chapter ID', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Chapter Title', 'wp-genius' ); ?></th>
							<th width="160"><?php esc_html_e( 'Created Date', 'wp-genius' ); ?></th>
							<th width="240"><?php esc_html_e( 'Diagnosis / Reason', 'wp-genius' ); ?></th>
							<th width="90"><?php esc_html_e( 'Action', 'wp-genius' ); ?></th>
						</tr>
					</thead>
					<tbody id="w2p-orphan-table-tbody">
						<!-- 动态渲染孤儿章节列表 -->
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- 板块 2：章节完整性与断号体检 (Chapter Integrity & Continuity Health Audit) -->
	<div id="w2p-pane-integrity-audit" class="w2p-section">
		<div class="w2p-section-header">
			<h3 class="w2p-section-title">
				<i class="fa-solid fa-heart-pulse"></i>
				<?php esc_html_e( 'Chapter Integrity & Continuity Health Audit', 'wp-genius' ); ?>
			</h3>
			<p class="w2p-section-desc">
				<?php esc_html_e( 'Perform a database-wide audit across novels to inspect chapter sequence gaps, duplicate order numbers, and missing sequences.', 'wp-genius' ); ?>
			</p>
		</div>
		<div class="w2p-section-body">
			<div class="w2p-fix-stats-bar">
				<div class="w2p-fix-stat-item">
					<span class="w2p-fix-stat-label"><?php esc_html_e( 'Total Audited:', 'wp-genius' ); ?></span>
					<strong id="w2p-audit-stat-total">0</strong>
				</div>
				<div class="w2p-fix-stat-item">
					<span class="w2p-fix-stat-label"><?php esc_html_e( 'Healthy Novels:', 'wp-genius' ); ?></span>
					<strong id="w2p-audit-stat-healthy" class="w2p-text-success">0</strong>
				</div>
				<div class="w2p-fix-stat-item">
					<span class="w2p-fix-stat-label"><?php esc_html_e( 'Issues Detected:', 'wp-genius' ); ?></span>
					<strong id="w2p-audit-stat-issues" class="w2p-text-danger">0</strong>
				</div>
				<div class="w2p-fix-stat-action">
					<button type="button" id="w2p-audit-start-btn" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-play"></i> <?php esc_html_e( 'Start Health Audit', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-audit-stop-btn" class="w2p-btn w2p-btn-danger w2p-hidden">
						<i class="fa-solid fa-stop"></i> <?php esc_html_e( 'Stop Audit', 'wp-genius' ); ?>
					</button>
				</div>
			</div>

			<!-- 体检进度条 (初始化隐藏) -->
			<div id="w2p-audit-progress" class="w2p-progress-container w2p-fix-progress-box w2p-hidden">
				<div class="w2p-progress-info">
					<span id="w2p-audit-status"><?php esc_html_e( 'Ready for health audit...', 'wp-genius' ); ?></span>
					<span id="w2p-audit-count">0 / 0</span>
				</div>
				<div class="w2p-progress-bar-bg">
					<div id="w2p-audit-bar" class="w2p-progress-bar-fill"></div>
				</div>
			</div>

			<!-- 异常问题列表表格 (初始化隐藏) -->
			<div id="w2p-audit-issues-wrap" class="w2p-fix-results-table-wrap w2p-hidden">
				<table class="w2p-results-table widefat striped">
					<thead>
						<tr>
							<th width="90"><?php esc_html_e( 'Novel ID', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Novel Title', 'wp-genius' ); ?></th>
							<th width="120"><?php esc_html_e( 'Chapters', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Sequence Gaps (Missing Orders)', 'wp-genius' ); ?></th>
							<th width="180"><?php esc_html_e( 'Duplicate Orders', 'wp-genius' ); ?></th>
							<th width="130"><?php esc_html_e( 'Action', 'wp-genius' ); ?></th>
						</tr>
					</thead>
					<tbody id="w2p-audit-issues-tbody">
						<!-- 动态渲染异常小说行 -->
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- 板块 3：全库批量重建索引 (Batch Rebuild Chapter Indexes) -->
	<div id="w2p-fix-pane-auto" class="w2p-section">
		<div class="w2p-section-header">
			<h3 class="w2p-section-title">
				<i class="fa-solid fa-list-ol"></i>
				<?php esc_html_e( 'Batch Rebuild Chapter Indexes', 'wp-genius' ); ?>
			</h3>
			<p class="w2p-section-desc">
				<?php esc_html_e( 'Automatically iterate through all published novels to rebuild chapter indexes and volume structures.', 'wp-genius' ); ?>
			</p>
		</div>
		<div class="w2p-section-body">
			<!-- 统计指示与操作工具栏 -->
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

			<!-- 全自动执行日志表格 (初始化隐藏) -->
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

</div>
