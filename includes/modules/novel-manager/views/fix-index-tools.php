<?php
/**
 * Fix Chapter Index - Tools View
 *
 * Provides the control buttons and log container for the Fix Chapter Index tool.
 * Configuration is handled by the settings tab.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="w2p-section">
	<div class="w2p-section-header">
		<h3><?php esc_html_e( 'Index Fixer Tools', 'wp-genius' ); ?></h3>
		<div class="w2p-section-actions">
			<!-- Global Nonce for AJAX -->
			<input type="hidden" id="fix_index_nonce" value="<?php echo esc_attr( wp_create_nonce( 'fix_chapter_index' ) ); ?>">
			
			<button type="button" id="fix-index-scan-btn" class="button button-secondary">
				<i class="fa fa-search"></i> <?php esc_html_e( 'Scan Issues', 'wp-genius' ); ?>
			</button>
			
			<button type="button" id="fix-index-auto-btn" class="button button-primary">
				<i class="fa fa-magic"></i> <?php esc_html_e( 'Auto Fix All', 'wp-genius' ); ?>
			</button>

			<button type="button" id="fix-index-execute-btn" class="button button-primary" style="display:none;">
				<i class="fa fa-play"></i> <?php esc_html_e( 'Apply Fixes', 'wp-genius' ); ?>
			</button>

			<button type="button" id="fix-index-stop-btn" class="button button-secondary" style="display:none;">
				<i class="fa fa-stop"></i> <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
			</button>

			<button type="button" id="fix-index-reset-btn" class="button button-secondary" style="display:none;">
				<i class="fa fa-undo"></i> <?php esc_html_e( 'Reset', 'wp-genius' ); ?>
			</button>
		</div>
	</div>

	<div class="w2p-section-body">
		<!-- Progress Bar -->
		<div class="w2p-progress-wrapper" style="margin: 15px 0;">
			<div class="w2p-progress-bar">
				<div id="fix-progress-bar" class="w2p-progress-fill" style="width: 0%;"></div>
			</div>
			<div class="w2p-progress-status">
				<span id="fix-progress-text">0 / 0</span>
				<span id="finished-count-text" style="display:none; margin-left: 10px; color: #666;"></span>
				<a href="#" id="fix-index-clear-progress" style="float:right; text-decoration:none; font-size:12px;">
					<?php esc_html_e( 'Clear History', 'wp-genius' ); ?>
				</a>
			</div>
		</div>

		<!-- Logs -->
		<div class="w2p-log-container">
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width: 20%;"><?php esc_html_e( 'New Index', 'wp-genius' ); ?></th>
						<th style="width: 20%;"><?php esc_html_e( 'Volume', 'wp-genius' ); ?></th>
						<th><?php esc_html_e( 'Title', 'wp-genius' ); ?></th>
					</tr>
				</thead>
				<tbody id="fix-logs-tbody">
					<tr>
						<td colspan="3" style="text-align:center; color:#999;">
							<?php esc_html_e( 'Ready to scan. Please configure settings in the "Fix Index - Settings" tab first.', 'wp-genius' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<style>
.w2p-progress-wrapper .w2p-progress-bar {
	background: #f1f1f1;
	height: 20px;
	border-radius: 3px;
	overflow: hidden;
	box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
}
.w2p-progress-fill {
	background: #2271b1;
	height: 100%;
	transition: width 0.3s ease;
}
.w2p-progress-status {
	margin-top: 5px;
	font-size: 13px;
	color: #444;
}
.w2p-log-container {
	max-height: 400px;
	overflow-y: auto;
	border: 1px solid #ccd0d4;
	margin-top: 15px;
}
</style>
