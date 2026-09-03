<?php
/**
 * Novel Manager — Tab Fix Chapter Index View
 *
 * 章节顺序重构与分卷识别视图
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$novels = get_posts(
	array(
		'post_type'      => 'novel',
		'posts_per_page' => 50,
		'post_status'    => 'publish',
		'orderby'        => 'ID',
		'order'          => 'DESC',
	)
);
?>

<div id="w2p-tab-fix-index" class="w2p-wrapper">
	<div class="w2p-section">
		<div class="w2p-section-header w2p-flex-between">
			<h4><i class="fa-solid fa-list-ol"></i> <?php esc_html_e( 'Chapter Index & Volume Rebuilder', 'wp-genius' ); ?></h4>
			<div class="w2p-section-actions">
				<button type="button" id="w2p-fix-scan-btn" class="w2p-btn w2p-btn-primary">
					<i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e( 'Scan & Preview', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-fix-execute-btn" class="w2p-btn w2p-btn-danger" style="display:none;">
					<i class="fa-solid fa-bolt"></i> <?php esc_html_e( 'Apply Updates', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-fix-auto-btn" class="w2p-btn w2p-btn-success">
					<i class="fa-solid fa-wand-magic-sparkles"></i> <?php esc_html_e( 'Auto Rebuild All', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-fix-stop-btn" class="w2p-btn w2p-btn-secondary" style="display:none;">
					<i class="fa-solid fa-stop"></i> <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-fix-reset-btn" class="w2p-btn w2p-btn-secondary" style="display:none;">
					<i class="fa-solid fa-arrow-rotate-left"></i> <?php esc_html_e( 'Reset', 'wp-genius' ); ?>
				</button>
			</div>
		</div>

		<div class="w2p-section-body">
			<!-- 重构扫描配置区 -->
			<div class="w2p-grid w2p-grid-cols-3 w2p-gap-md" style="margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0;">
				<div>
					<label for="w2p_fix_scan_mode"><strong><?php esc_html_e( 'Scan Scope', 'wp-genius' ); ?></strong></label>
					<select id="w2p_fix_scan_mode" class="w2p-input-full" style="margin-top: 5px;">
						<option value="all"><?php esc_html_e( 'All Novels & Chapters', 'wp-genius' ); ?></option>
						<option value="by_novel"><?php esc_html_e( 'Specific Novel', 'wp-genius' ); ?></option>
					</select>
				</div>

				<div id="w2p_fix_novel_selector_box" style="display:none;">
					<label for="w2p_fix_novel_id"><strong><?php esc_html_e( 'Select Novel', 'wp-genius' ); ?></strong></label>
					<select id="w2p_fix_novel_id" class="w2p-input-full" style="margin-top: 5px;">
						<option value="0"><?php esc_html_e( '-- Choose Novel --', 'wp-genius' ); ?></option>
						<?php if ( ! empty( $novels ) ) : ?>
							<?php foreach ( $novels as $n ) : ?>
								<option value="<?php echo esc_attr( $n->ID ); ?>"><?php echo esc_html( $n->post_title ); ?> (ID: <?php echo esc_html( $n->ID ); ?>)</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>

				<div>
					<label for="w2p_fix_index_format"><strong><?php esc_html_e( 'Index Format Template', 'wp-genius' ); ?></strong></label>
					<input type="text" id="w2p_fix_index_format" class="w2p-input-full" value="01-00001" style="margin-top: 5px;" placeholder="01-00001">
				</div>

				<div>
					<label><strong><?php esc_html_e( 'Volume Auto-Identification', 'wp-genius' ); ?></strong></label>
					<div style="margin-top: 8px;">
						<label>
							<input type="checkbox" id="w2p_fix_auto_volume" value="1" checked> <?php esc_html_e( 'Auto identify volumes from chapter titles', 'wp-genius' ); ?>
						</label>
					</div>
				</div>
			</div>

			<!-- 进度指示区 -->
			<div class="w2p-progress-container">
				<div class="w2p-progress-info">
					<span id="w2p-fix-progress-status"><?php esc_html_e( 'Ready to scan', 'wp-genius' ); ?></span>
					<span id="w2p-fix-progress-text">0 / 0</span>
				</div>
				<div class="w2p-progress-bar-bg">
					<div id="w2p-fix-progress-bar" class="w2p-progress-bar-fill" style="width: 0%;"></div>
				</div>
				
				<?php
				$finished_books = get_option( 'w2p_fix_index_finished_books', array() );
				$finished_count = count( $finished_books );
				?>
				<div id="w2p-fix-finished-row" style="<?php echo ( $finished_count > 0 ) ? '' : 'display:none;'; ?> margin-top: 10px; font-size: 12px; color: #64748b;">
					<span id="w2p-fix-finished-text"><?php /* translators: %d: number of processed novels. */ printf( esc_html__( 'Processed Novels: %d', 'wp-genius' ), absint( $finished_count ) ); ?></span> 
					| <a href="#" id="w2p-fix-clear-history-btn" style="color: #ef4444; text-decoration: none;"><?php esc_html_e( 'Clear Progress History', 'wp-genius' ); ?></a>
				</div>
			</div>

			<!-- 扫描日志与差异对比表 -->
			<div class="w2p-log-section" style="margin-top: 20px;">
				<h5><?php esc_html_e( 'Scan & Fix Log Table', 'wp-genius' ); ?></h5>
				<div class="w2p-log-container" style="max-height: 450px; overflow-y: auto; border: 1px solid #ccd0d4; border-radius: 4px;">
					<table class="w2p-log-table widefat striped">
						<thead>
							<tr>
								<th width="140"><?php esc_html_e( 'Index (New / Old)', 'wp-genius' ); ?></th>
								<th width="160"><?php esc_html_e( 'Volume (New / Old)', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Chapter Title', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="w2p-fix-logs-tbody">
							<tr>
								<td colspan="3" style="text-align:center;color:#94a3b8;"><?php esc_html_e( 'Click "Scan & Preview" to start checking chapter indexes.', 'wp-genius' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
