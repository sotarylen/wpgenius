<?php
/**
 * Auto Publish Manual Control UI
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="w2p-auto-publish-logs">
	<div class="w2p-section-header">
		<h4><?php esc_html_e( 'Manual Bulk Publish', 'wp-genius' ); ?></h4>
		<div class="w2p-header-actions">
			<button type="button" id="w2p-start-publish" class="w2p-btn w2p-btn-primary">
				<i class="fa-solid fa-play"></i>
				<?php esc_html_e( 'Start Now', 'wp-genius' ); ?>
			</button>
			<button type="button" id="w2p-stop-publish" class="w2p-btn w2p-btn-stop w2p-hidden">
				<i class="fa-solid fa-pause"></i>
				<?php esc_html_e( 'Stop', 'wp-genius' ); ?>
			</button>
		</div>
	</div>
	
	<div class="w2p-manual-publish-controls">
		<div class="w2p-manual-publish-info">
			<div class="w2p-progress-text">
				<strong class="w2p-progress-counter">0/0</strong>
			</div>
			<div class="progress-text"><?php esc_html_e( 'Ready to start...', 'wp-genius' ); ?></div>
		</div>
		
		<div id="w2p-publish-progress" class="w2p-hidden mt-15">
			<div class="progress-bar-container">
				<div class="progress-bar-inner" style="width: 0%;"></div>
			</div>
			<div id="w2p-smart-aui-preview-area" class="w2p-smart-aui-preview-area mt-15"></div>
		</div>
	</div>
</div>

<div class="w2p-auto-publish-logs w2p-mt-lg">
	<div class="w2p-section-header">
		<h4><?php esc_html_e( 'Publish Logs', 'wp-genius' ); ?></h4>
		<button type="button" id="w2p-clean-logs" class="w2p-btn w2p-btn-secondary">
			<i class="fa-solid fa-trash"></i>
			<?php esc_html_e( 'Clear Logs', 'wp-genius' ); ?>
		</button>
	</div>
	
	<div class="w2p-log-container">
		<table class="wp-list-table fixed striped">
			<thead>
				<tr>
					<th width="15%"><?php esc_html_e( 'Time', 'wp-genius' ); ?></th>
					<th><?php esc_html_e( 'Post', 'wp-genius' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Source', 'wp-genius' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
				</tr>
			</thead>
			<tbody id="w2p-publish-logs-body">
				<tr><td colspan="4"><?php esc_html_e( 'Loading logs...', 'wp-genius' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
