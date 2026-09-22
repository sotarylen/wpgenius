<?php
/**
 * Novel Manager — Tab Statistics View
 *
 * 章节字数与书籍统计的校准视图：单本重算 + 全站分批回填。
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 本视图随 options.php 在每个后台请求（含 Dashboard）被 include 注册菜单，
// 而概览是重查询（30 万章聚合）。只在设置页渲染，其余页面一律输出空字符串。
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 仅读取页面标识，不处理任何数据
if ( empty( $_GET['page'] ) || 'wp-genius-settings' !== $_GET['page'] ) {
	return;
}

// 概览数字不再随页面渲染实时计算（同样的重查询），改为占位骨架，
// 由 JS 在本 Tab 可见时通过 w2p_novel_stats_overview 懒加载填充。
?>
<div id="w2p-tab-stats" class="w2p-wrapper">

	<!-- 概览指标（懒加载：JS 在 Tab 可见时通过 AJAX 填充） -->
	<div class="w2p-section">
		<div class="w2p-section-body">
			<div class="w2p-stats-overview" data-w2p-stats-overview="pending">
				<div class="w2p-stats-kpi">
					<span class="w2p-stats-kpi-label"><?php esc_html_e( 'Published Chapters', 'wp-genius' ); ?></span>
					<span class="w2p-stats-kpi-value" id="w2p-stats-kpi-chapter-total">—</span>
					<span class="w2p-stats-kpi-sub">
						<?php esc_html_e( 'Words calculated:', 'wp-genius' ); ?>
						<strong id="w2p-stats-kpi-chapter-synced">—</strong>
					</span>
				</div>
				<div class="w2p-stats-kpi">
					<span class="w2p-stats-kpi-label"><?php esc_html_e( 'Novels', 'wp-genius' ); ?></span>
					<span class="w2p-stats-kpi-value" id="w2p-stats-kpi-novel-total">—</span>
					<span class="w2p-stats-kpi-sub">
						<?php esc_html_e( 'Stats written:', 'wp-genius' ); ?>
						<strong id="w2p-stats-kpi-novel-synced">—</strong>
					</span>
				</div>
				<div class="w2p-stats-kpi">
					<span class="w2p-stats-kpi-label"><?php esc_html_e( 'Pending Chapters', 'wp-genius' ); ?></span>
					<span class="w2p-stats-kpi-value" id="w2p-stats-kpi-chapter-pending">—</span>
					<span class="w2p-stats-kpi-sub"><?php esc_html_e( 'Chapters without cached word count', 'wp-genius' ); ?></span>
				</div>
				<div class="w2p-stats-kpi">
					<span class="w2p-stats-kpi-label"><?php esc_html_e( 'Pending Novels', 'wp-genius' ); ?></span>
					<span class="w2p-stats-kpi-value" id="w2p-stats-kpi-novel-pending">—</span>
					<span class="w2p-stats-kpi-sub">
						<?php esc_html_e( 'Deferred:', 'wp-genius' ); ?>
						<strong id="w2p-stats-dirty-count">—</strong>
					</span>
				</div>
			</div>
		</div>
	</div>

	<!-- 单本重算 -->
	<div class="w2p-section">
		<div class="w2p-section-body">
			<h4 class="w2p-stats-section-title">
				<i class="fa-solid fa-calculator"></i> <?php esc_html_e( 'Recalculate a Single Novel', 'wp-genius' ); ?>
			</h4>
			<p class="w2p-stats-hint">
				<?php esc_html_e( 'Search by novel title keyword or exact ID, then recalculate its chapter count and total word count immediately.', 'wp-genius' ); ?>
			</p>

			<div class="w2p-stats-inline-form">
				<input type="text" id="w2p-stats-search-input" class="w2p-input-half" placeholder="<?php esc_attr_e( 'Enter novel title keyword or exact Novel ID...', 'wp-genius' ); ?>" autocomplete="off">
				<button type="button" id="w2p-stats-search-btn" class="w2p-btn w2p-btn-secondary w2p-btn-sm">
					<i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e( 'Search', 'wp-genius' ); ?>
				</button>
			</div>

			<div id="w2p-stats-search-results" class="w2p-stats-search-results" style="display:none;">
				<ul class="w2p-stats-result-list"></ul>
			</div>

			<div id="w2p-stats-single-card" class="w2p-stats-single-card" style="display:none;">
				<div class="w2p-stats-single-head">
					<strong id="w2p-stats-single-title">-</strong>
					<span class="w2p-stats-single-id">#<span id="w2p-stats-single-id">0</span></span>
				</div>
				<div class="w2p-stats-single-metrics">
					<div>
						<span><?php esc_html_e( 'Chapters', 'wp-genius' ); ?></span>
						<strong id="w2p-stats-single-chapters">0</strong>
					</div>
					<div>
						<span><?php esc_html_e( 'Words', 'wp-genius' ); ?></span>
						<strong id="w2p-stats-single-words">0</strong>
					</div>
				</div>
				<button type="button" id="w2p-stats-single-run-btn" class="w2p-btn w2p-btn-primary w2p-btn-sm">
					<i class="fa-solid fa-rotate"></i> <?php esc_html_e( 'Recalculate Now', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- 全站分批回填 -->
	<div class="w2p-section">
		<div class="w2p-section-body">
			<h4 class="w2p-stats-section-title">
				<i class="fa-solid fa-arrows-rotate"></i> <?php esc_html_e( 'Full Site Rebuild', 'wp-genius' ); ?>
			</h4>
			<p class="w2p-stats-hint">
				<?php esc_html_e( 'Two phases: first cache the word count of every published chapter, then aggregate all novels. Runs in batches and can be stopped at any time — progress is kept, so you can resume later.', 'wp-genius' ); ?>
			</p>

			<div class="w2p-stats-actions"
				data-chapter-total=""
				data-novel-total="">
				<button type="button" id="w2p-stats-run-all-btn" class="w2p-btn w2p-btn-primary">
					<i class="fa-solid fa-play"></i> <?php esc_html_e( 'Run Both Phases', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-stats-scan-btn" class="w2p-btn w2p-btn-secondary">
					<i class="fa-solid fa-1"></i> <?php esc_html_e( 'Cache Chapter Words Only', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-stats-sync-btn" class="w2p-btn w2p-btn-secondary">
					<i class="fa-solid fa-2"></i> <?php esc_html_e( 'Aggregate Novels Only', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-stats-stop-btn" class="w2p-btn w2p-btn-danger" style="display:none;">
					<i class="fa-solid fa-stop"></i> <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
				</button>
			</div>

			<div id="w2p-stats-progress-container" class="w2p-progress-container" style="display:none;">
				<div class="w2p-progress-info">
					<span id="w2p-stats-progress-status">-</span>
					<span id="w2p-stats-progress-count">0 / 0</span>
				</div>
				<div class="w2p-progress-bar-bg">
					<div id="w2p-stats-progress-bar" class="w2p-progress-bar-fill" style="width: 0%;"></div>
				</div>
			</div>

			<div id="w2p-stats-log" class="w2p-log-container" style="display:none;"></div>
		</div>
	</div>
</div>
