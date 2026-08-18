<?php
/**
 * Media Engine - Residual Media Audit Panel
 *
 * 扫描 uploads 目录中的残留媒体，通过 Minio 桶 HEAD 探测判断是否已 offload。
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$uploads = wp_upload_dir();
?>

<div class="w2p-settings-panel w2p-media-audit-settings">
	<div class="w2p-section">
		<div class="w2p-section-header">
			<h4><?php esc_html_e( '残留媒体审计', 'wp-genius' ); ?></h4>
			<p class="description">
				<?php esc_html_e( '扫描 uploads 目录中的残留媒体文件，通过存储桶探测判断是否已成功 offload，并分析未处理文件的原因。', 'wp-genius' ); ?>
			</p>
		</div>
		<div class="w2p-section-body">

			<!-- 扫描控制 -->
			<div class="w2p-audit-controls">
				<label for="w2p-audit-subdir" class="w2p-audit-label">
					<?php esc_html_e( '扫描目录（相对 uploads，如 2026/07）', 'wp-genius' ); ?>
				</label>
				<div class="w2p-flex w2p-gap-sm w2p-items-center">
					<input type="text" id="w2p-audit-subdir" value="2026/07"
						placeholder="2026/07" class="w2p-audit-input" />
					<button type="button" id="w2p-audit-scan" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-magnifying-glass"></i>
						<?php esc_html_e( '开始扫描', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-audit-stop" class="w2p-btn w2p-btn-stop w2p-hidden">
						<i class="fa-solid fa-stop"></i>
						<?php esc_html_e( '停止', 'wp-genius' ); ?>
					</button>
				</div>
			</div>

			<!-- 进度 -->
			<div id="w2p-audit-progress" class="w2p-hidden w2p-audit-progress">
				<span id="w2p-audit-progress-text"></span>
			</div>

			<!-- 汇总统计 -->
			<div id="w2p-audit-summary" class="w2p-hidden w2p-audit-summary"></div>

			<!-- 结果表格 -->
			<div id="w2p-audit-results" class="w2p-hidden">
				<div class="w2p-audit-actions w2p-flex w2p-gap-sm w2p-items-center">
					<button type="button" id="w2p-audit-clean-all" class="w2p-btn w2p-btn-stop w2p-hidden">
						<i class="fa-solid fa-trash"></i>
						<?php esc_html_e( '清理可删文件（A类）', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-audit-enqueue-all" class="w2p-btn w2p-btn-secondary w2p-hidden">
						<i class="fa-solid fa-plus"></i>
						<?php esc_html_e( '将可入队文件加入批量队列（B类）', 'wp-genius' ); ?>
					</button>
				</div>
				<div class="w2p-log-container">
					<table class="w2p-list-table fixed striped w2p-audit-table">
						<thead>
							<tr>
								<th width="30px"><input type="checkbox" id="w2p-audit-check-all" /></th>
								<th><?php esc_html_e( '文件', 'wp-genius' ); ?></th>
								<th width="100px"><?php esc_html_e( '类型', 'wp-genius' ); ?></th>
								<th width="90px"><?php esc_html_e( '状态', 'wp-genius' ); ?></th>
								<th width="90px"><?php esc_html_e( '大小', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( '说明 / 父级文章', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="w2p-audit-tbody"></tbody>
					</table>
				</div>
			</div>

		</div>
	</div>
</div>
