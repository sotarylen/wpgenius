<?php
/**
 * Smart AUI — External Media Scanner & Batch Grabber View Panel
 *
 * Provides the user interface for scanning posts with external media,
 * viewing stats, manual/batch processing, and full-auto scanning loops.
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings   = get_option( 'w2p_settings', array() );
$scan_limit = isset( $settings['smart_aui_scan_limit'] ) ? absint( $settings['smart_aui_scan_limit'] ) : 100;
?>

<div class="w2p-settings-panel w2p-smart-aui-scanner-panel">

	<div class="w2p-section">
		<div class="w2p-section-body">

			<!-- Action Toolbar -->
			<div class="w2p-processing-actions">
				<div class="w2p-flex w2p-gap-sm w2p-items-center w2p-flex-wrap">
					<button type="button" id="w2p-aui-scanner-scan" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-magnifying-glass"></i>
						<?php esc_html_e( 'Scan Posts', 'wp-genius' ); ?>
					</button>

					<button type="button" id="w2p-aui-scanner-process-selected" class="w2p-btn w2p-btn-secondary w2p-hidden">
						<i class="fa-solid fa-cloud-arrow-down"></i>
						<?php esc_html_e( 'Grab Selected', 'wp-genius' ); ?>
					</button>

					<button type="button" id="w2p-aui-scanner-start-auto" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-rotate"></i>
						<?php esc_html_e( 'Full Auto Scan & Grab', 'wp-genius' ); ?>
					</button>

					<button type="button" id="w2p-aui-scanner-pause-auto" class="w2p-btn w2p-btn-warning w2p-hidden">
						<i class="fa-solid fa-pause"></i>
						<?php esc_html_e( 'Pause', 'wp-genius' ); ?>
					</button>

					<button type="button" id="w2p-aui-scanner-stop" class="w2p-btn w2p-btn-stop w2p-hidden">
						<i class="fa-solid fa-stop"></i>
						<?php esc_html_e( 'Stop', 'wp-genius' ); ?>
					</button>

					<span class="w2p-action-divider"></span>

					<button type="button" id="w2p-aui-scanner-view-log" class="w2p-btn w2p-btn-secondary">
						<i class="fa-solid fa-file-lines"></i>
						<?php esc_html_e( 'View Log', 'wp-genius' ); ?>
					</button>
				</div>
			</div>

			<!-- Stat Cards Grid (Media Engine Style) -->
			<div id="w2p-aui-scanner-summary" class="w2p-queue-stats-grid w2p-hidden">
				<div class="w2p-queue-stat-card w2p-queue-stat-total" data-filter="scanned">
					<span class="label"><?php esc_html_e( 'Scanned Posts', 'wp-genius' ); ?></span>
					<span class="value" data-stat="scanned_posts">0</span>
				</div>
				<div class="w2p-queue-stat-card w2p-queue-stat-pending" data-filter="pending">
					<span class="label"><?php esc_html_e( 'Posts with External Media', 'wp-genius' ); ?></span>
					<span class="value" data-stat="pending_posts">0</span>
				</div>
				<div class="w2p-queue-stat-card w2p-queue-stat-processing" data-filter="urls">
					<span class="label"><?php esc_html_e( 'External URLs Found', 'wp-genius' ); ?></span>
					<span class="value" data-stat="external_urls">0</span>
				</div>
				<div class="w2p-queue-stat-card w2p-queue-stat-completed" data-filter="modified">
					<span class="label"><?php esc_html_e( 'Grabbed & Replaced', 'wp-genius' ); ?></span>
					<span class="value" data-stat="modified_posts">0</span>
				</div>
				<div class="w2p-queue-stat-card w2p-queue-stat-failed" data-filter="failed">
					<span class="label"><?php esc_html_e( 'Failed / Skipped', 'wp-genius' ); ?></span>
					<span class="value" data-stat="failed_count">0</span>
				</div>
			</div>

			<!-- Progress Indicator -->
			<div id="w2p-aui-scanner-progress" class="w2p-hidden w2p-unified-progress">
				<span id="w2p-aui-scanner-progress-text"></span>
			</div>

			<!-- Scan Results Table -->
			<div id="w2p-aui-scanner-results" class="w2p-hidden">
				<div class="w2p-log-container">
					<table class="w2p-list-table fixed striped w2p-scanner-table" id="w2p-aui-scanner-table">
						<thead>
							<tr>
								<th width="35px"><input type="checkbox" id="w2p-aui-check-all" /></th>
								<th width="80px"><?php esc_html_e( 'Post ID', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Title / Post', 'wp-genius' ); ?></th>
								<th width="120px"><?php esc_html_e( 'External Media', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Sample URLs', 'wp-genius' ); ?></th>
								<th width="140px"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="w2p-aui-scanner-tbody"></tbody>
					</table>
				</div>
			</div>

		</div>
	</div>

</div>

<!-- Shared Log Viewer Modal (Media Engine Style) -->
<div id="w2p-aui-log-modal" class="w2p-log-overlay">
	<div class="w2p-confirm-modal w2p-log-viewer">
		<div class="w2p-modal-header">
			<h4>
				<i class="fa-solid fa-file-lines"></i>
				<?php esc_html_e( 'Smart AUI Scanner Log', 'wp-genius' ); ?>
				<span id="w2p-aui-log-size" class="w2p-log-size-label"></span>
			</h4>
			<button type="button" id="w2p-aui-close-log" class="w2p-modal-close">&times;</button>
		</div>
		<div class="w2p-modal-body">
			<pre id="w2p-aui-log-content" class="w2p-terminal"></pre>
		</div>
		<div class="w2p-modal-footer">
			<button type="button" id="w2p-aui-clear-log" class="w2p-btn w2p-btn-secondary w2p-btn-error-outline">
				<i class="fa-solid fa-trash"></i> <?php esc_html_e( 'Clear Log', 'wp-genius' ); ?>
			</button>
			<button type="button" id="w2p-aui-modal-close-log" class="w2p-btn w2p-btn-primary">
				<?php esc_html_e( 'Close', 'wp-genius' ); ?>
			</button>
		</div>
	</div>
</div>

