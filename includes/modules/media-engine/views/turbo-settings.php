<?php
/**
 * Media Engine - Unified Batch Processing & Maintenance Panel
 *
 * Combines 3 Workflow Steps:
 * 1. Batch Conversion & Offload (Media Turbo)
 * 2. Residual Media Audit & Clean (Storage Bucket HEAD Probes)
 * 3. Content URL & Format Fixer (WebP Path & Extension Rewriting)
 *
 * Shares a unified processing console and a single log viewer modal.
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings   = W2P_Settings::tab_with_legacy( 'media_engine_tabs', 'w2p_media_turbo_settings', array() );
$scan_limit = isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 500;
$batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 10;
?>

<div class="w2p-settings-panel w2p-media-turbo-settings w2p-unified-workflow-panel">

	<!-- Workflow Step Navigation Bar -->
	<div class="w2p-workflow-steps-nav">
		<button type="button" class="w2p-workflow-step-btn active" data-step="1">
			<span class="w2p-step-num">1</span>
			<span class="w2p-step-info">
				<span class="w2p-step-title"><?php esc_html_e( 'Batch Conversion & Offload', 'wp-genius' ); ?></span>
				<span class="w2p-step-desc"><?php esc_html_e( 'Convert images to WebP and offload to MinIO', 'wp-genius' ); ?></span>
			</span>
		</button>
		<button type="button" class="w2p-workflow-step-btn" data-step="2">
			<span class="w2p-step-num">2</span>
			<span class="w2p-step-info">
				<span class="w2p-step-title"><?php esc_html_e( 'Residual Media Audit', 'wp-genius' ); ?></span>
				<span class="w2p-step-desc"><?php esc_html_e( 'Audit & clean residual local uploads', 'wp-genius' ); ?></span>
			</span>
		</button>
		<button type="button" class="w2p-workflow-step-btn" data-step="3">
			<span class="w2p-step-num">3</span>
			<span class="w2p-step-info">
				<span class="w2p-step-title"><?php esc_html_e( 'Content URL & Format Fixer', 'wp-genius' ); ?></span>
				<span class="w2p-step-desc"><?php esc_html_e( 'Fix leftover URLs to /wp-media/ & WebP', 'wp-genius' ); ?></span>
			</span>
		</button>
	</div>

	<div class="w2p-section">
		<div class="w2p-section-body">

			<!-- Step 1: Conversion & Offload Action Bar -->
			<div class="w2p-step-panel" id="w2p-step-1-panel">
				<div class="w2p-processing-actions">
					<div class="w2p-flex w2p-gap-sm w2p-items-center w2p-flex-wrap">
						<button type="button" id="w2p-get-stats" class="w2p-btn w2p-btn-primary">
							<i class="fa-solid fa-chart-bar"></i>
							<?php esc_html_e( 'Get Pending Stats', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-start-conversion" class="w2p-btn w2p-btn-secondary">
							<i class="fa-solid fa-play"></i>
							<?php printf( esc_html__( 'Batch Conversion (%1$d items, batch %2$d)', 'wp-genius' ), absint( $scan_limit ), absint( $batch_size ) ); ?>
						</button>
						<button type="button" id="w2p-start-auto" class="w2p-btn w2p-btn-primary">
							<i class="fa-solid fa-rotate"></i>
							<?php esc_html_e( 'Full Auto Processing', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-pause-auto" class="w2p-btn w2p-btn-warning w2p-hidden">
							<i class="fa-solid fa-pause"></i>
							<?php esc_html_e( 'Pause', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-stop-conversion" class="w2p-btn w2p-btn-stop w2p-hidden">
							<i class="fa-solid fa-stop"></i>
							<?php esc_html_e( 'Stop Processing', 'wp-genius' ); ?>
						</button>
						<span class="w2p-action-divider"></span>
						<button type="button" id="w2p-retry-failed" class="w2p-btn w2p-btn-secondary">
							<i class="fa-solid fa-rotate-left"></i>
							<?php esc_html_e( 'Retry Failed Items', 'wp-genius' ); ?>
						</button>
						<button type="button" class="w2p-btn w2p-btn-secondary w2p-shared-view-log-btn">
							<i class="fa-solid fa-file-lines"></i>
							<?php esc_html_e( 'View Log', 'wp-genius' ); ?>
						</button>
					</div>
				</div>

				<!-- Step 1 Stat Cards (Hidden before task/scan execution) -->
				<div id="w2p-queue-summary" class="w2p-queue-stats-grid w2p-hidden">
					<div class="w2p-queue-stat-card w2p-queue-stat-total" data-filter="total">
						<span class="label"><?php esc_html_e( 'Total', 'wp-genius' ); ?></span>
						<span class="value" data-stat="total">0</span>
					</div>
					<div class="w2p-queue-stat-card w2p-queue-stat-pending" data-filter="pending">
						<span class="label"><?php esc_html_e( 'Pending', 'wp-genius' ); ?></span>
						<span class="value" data-stat="pending">0</span>
					</div>
					<div class="w2p-queue-stat-card w2p-queue-stat-processing" data-filter="processing">
						<span class="label"><?php esc_html_e( 'Processing', 'wp-genius' ); ?></span>
						<span class="value" data-stat="processing">0</span>
					</div>
					<div class="w2p-queue-stat-card w2p-queue-stat-completed" data-filter="completed">
						<span class="label"><?php esc_html_e( 'Completed', 'wp-genius' ); ?></span>
						<span class="value" data-stat="completed">0</span>
					</div>
					<div class="w2p-queue-stat-card w2p-queue-stat-failed" data-filter="failed">
						<span class="label"><?php esc_html_e( 'Failed', 'wp-genius' ); ?></span>
						<span class="value" data-stat="failed">0</span>
					</div>
				</div>
			</div>

			<!-- Step 2: Residual Media Audit Action Bar & Stats -->
			<div class="w2p-step-panel w2p-hidden" id="w2p-step-2-panel">
				<div class="w2p-processing-actions">
					<div class="w2p-flex w2p-gap-sm w2p-items-center w2p-flex-wrap">
						<button type="button" id="w2p-audit-scan" class="w2p-btn w2p-btn-primary">
							<i class="fa-solid fa-magnifying-glass"></i>
							<?php esc_html_e( 'Start Scan', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-audit-stop" class="w2p-btn w2p-btn-stop w2p-hidden">
							<i class="fa-solid fa-stop"></i>
							<?php esc_html_e( 'Stop', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-audit-clean-all" class="w2p-btn w2p-btn-stop w2p-hidden">
							<i class="fa-solid fa-trash"></i>
							<?php esc_html_e( 'Clean deletable files (Class A)', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-audit-enqueue-all" class="w2p-btn w2p-btn-secondary w2p-hidden">
							<i class="fa-solid fa-plus"></i>
							<?php esc_html_e( 'Enqueue eligible files into batch queue (Class B)', 'wp-genius' ); ?>
						</button>
						<span class="w2p-action-divider"></span>
						<button type="button" class="w2p-btn w2p-btn-secondary w2p-shared-view-log-btn">
							<i class="fa-solid fa-file-lines"></i>
							<?php esc_html_e( 'View Log', 'wp-genius' ); ?>
						</button>
					</div>
				</div>

				<!-- Step 2 Stat Cards (Hidden before scan execution) -->
				<div id="w2p-audit-summary" class="w2p-audit-stats-grid w2p-hidden">
					<div class="w2p-audit-stat-card w2p-audit-stat-total" data-filter="total" role="button" tabindex="0">
						<span class="label"><?php esc_html_e( 'Scanned Files', 'wp-genius' ); ?></span>
						<span class="value" data-stat="total">0</span>
					</div>
					<div class="w2p-audit-stat-card w2p-audit-stat-cleanable" data-filter="cleanable" role="button" tabindex="0">
						<span class="label"><?php esc_html_e( 'Cleanable (A)', 'wp-genius' ); ?></span>
						<span class="value" data-stat="cleanable">0</span>
					</div>
					<div class="w2p-audit-stat-card w2p-audit-stat-not_offloaded" data-filter="not_offloaded" role="button" tabindex="0">
						<span class="label"><?php esc_html_e( 'Not Offloaded (B)', 'wp-genius' ); ?></span>
						<span class="value" data-stat="not_offloaded">0</span>
					</div>
					<div class="w2p-audit-stat-card w2p-audit-stat-orphan" data-filter="orphan" role="button" tabindex="0">
						<span class="label"><?php esc_html_e( 'Orphan (C)', 'wp-genius' ); ?></span>
						<span class="value" data-stat="orphan">0</span>
					</div>
				</div>
			</div>

			<!-- Step 3: Content URL & Format Fixer Action Bar & Stats -->
			<div class="w2p-step-panel w2p-hidden" id="w2p-step-3-panel">
				<div class="w2p-processing-actions">
					<div class="w2p-flex w2p-gap-sm w2p-items-center w2p-flex-wrap">
						<button type="button" id="w2p-fixer-scan" class="w2p-btn w2p-btn-primary">
							<i class="fa-solid fa-magnifying-glass"></i>
							<?php esc_html_e( 'Scan Posts', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-fixer-start" class="w2p-btn w2p-btn-secondary w2p-hidden">
							<i class="fa-solid fa-wand-magic-sparkles"></i>
							<?php esc_html_e( 'Fix Selected', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-fixer-start-auto" class="w2p-btn w2p-btn-primary">
							<i class="fa-solid fa-rotate"></i>
							<?php esc_html_e( 'Full Auto Fix', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-fixer-pause-auto" class="w2p-btn w2p-btn-warning w2p-hidden">
							<i class="fa-solid fa-pause"></i>
							<?php esc_html_e( 'Pause', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-fixer-stop" class="w2p-btn w2p-btn-stop w2p-hidden">
							<i class="fa-solid fa-stop"></i>
							<?php esc_html_e( 'Stop', 'wp-genius' ); ?>
						</button>
						<span class="w2p-action-divider"></span>
						<button type="button" class="w2p-btn w2p-btn-secondary w2p-shared-view-log-btn">
							<i class="fa-solid fa-file-lines"></i>
							<?php esc_html_e( 'View Log', 'wp-genius' ); ?>
						</button>
					</div>
				</div>

				<!-- Step 3 Stat Cards (Hidden before scan/fix execution) -->
				<div id="w2p-fixer-summary" class="w2p-fixer-stats-grid w2p-hidden">
					<div class="w2p-fixer-stat-card w2p-fixer-stat-pending" data-filter="pending" role="button" tabindex="0">
						<span class="label"><?php esc_html_e( 'Processed / Pending', 'wp-genius' ); ?></span>
						<span class="value" data-stat="pending_posts">0</span>
					</div>
					<div class="w2p-fixer-stat-card w2p-fixer-stat-fixable" data-filter="fixable" role="button" tabindex="0">
						<span class="label"><?php esc_html_e( 'Fixable URLs', 'wp-genius' ); ?></span>
						<span class="value" data-stat="fixable_urls">0</span>
					</div>
					<div class="w2p-fixer-stat-card w2p-fixer-stat-ext" data-filter="ext_fix" role="button" tabindex="0">
						<span class="label"><?php esc_html_e( 'Path + WebP Ext Fix', 'wp-genius' ); ?></span>
						<span class="value" data-stat="ext_fixes">0</span>
					</div>
					<div class="w2p-fixer-stat-card w2p-fixer-stat-path" data-filter="path_only" role="button" tabindex="0">
						<span class="label"><?php esc_html_e( 'Path Only Fix', 'wp-genius' ); ?></span>
						<span class="value" data-stat="path_only_fixes">0</span>
					</div>
					<div class="w2p-fixer-stat-card w2p-fixer-stat-external" data-filter="external" role="button" tabindex="0">
						<span class="label"><?php esc_html_e( 'External Skipped', 'wp-genius' ); ?></span>
						<span class="value" data-stat="external_skipped">0</span>
					</div>
				</div>
			</div>

			<!-- Shared Processing Output Terminal (Shared across all 3 steps) -->
			<div id="w2p-processing-output" class="w2p-hidden w2p-terminal">
				<div id="w2p-output-content"></div>
			</div>

			<!-- Shared Progress Indicator -->
			<div id="w2p-unified-progress" class="w2p-hidden w2p-unified-progress">
				<span id="w2p-unified-progress-text"></span>
			</div>

			<!-- Step 1 Result View: Attachment List -->
			<div id="w2p-attachment-list" class="w2p-hidden w2p-attachment-list">
				<div class="w2p-log-container">
					<table class="w2p-list-table fixed striped">
						<thead>
							<tr>
								<th width="100px"><?php esc_html_e( 'Thumb', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'File', 'wp-genius' ); ?></th>
								<th width="200px"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="w2p-attachment-tbody"></tbody>
					</table>
				</div>
			</div>

			<!-- Step 2 Result View: Residual Media Audit Table -->
			<div id="w2p-audit-results" class="w2p-hidden">
				<div class="w2p-log-container">
					<table class="w2p-list-table fixed striped w2p-audit-table">
						<thead>
							<tr>
								<th width="30px"><input type="checkbox" id="w2p-audit-check-all" /></th>
								<th><?php esc_html_e( 'File', 'wp-genius' ); ?></th>
								<th width="100px"><?php esc_html_e( 'Type', 'wp-genius' ); ?></th>
								<th width="90px"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
								<th width="90px"><?php esc_html_e( 'Size', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Description / Parent Post', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="w2p-audit-tbody"></tbody>
					</table>
				</div>
			</div>

			<!-- Step 3 Result View: URL Fixer Results Table -->
			<div id="w2p-fixer-results" class="w2p-hidden">
				<div class="w2p-log-container">
					<table class="w2p-list-table fixed striped w2p-fixer-table">
						<thead>
							<tr>
								<th width="30px"><input type="checkbox" id="w2p-fixer-check-all" /></th>
								<th width="80px"><?php esc_html_e( 'Post ID', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Title / Post', 'wp-genius' ); ?></th>
								<th width="110px"><?php esc_html_e( 'Fixable URLs', 'wp-genius' ); ?></th>
								<th width="150px"><?php esc_html_e( 'Fix Type', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Replacement Preview', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="w2p-fixer-tbody"></tbody>
					</table>
				</div>
			</div>

		</div>
	</div>

</div>

<!-- Shared Log Viewer Modal (Used by all 3 steps) -->
<div id="w2p-log-modal" class="w2p-log-overlay">
	<div class="w2p-confirm-modal w2p-log-viewer">
		<div class="w2p-modal-header">
			<h4>
				<i class="fa-solid fa-file-lines"></i>
				<?php esc_html_e( 'Media Engine Processing Log', 'wp-genius' ); ?>
				<span id="w2p-log-size" class="w2p-log-size-label"></span>
			</h4>
			<button type="button" id="w2p-close-log" class="w2p-modal-close">&times;</button>
		</div>
		<div class="w2p-modal-body">
			<pre id="w2p-log-content" class="w2p-terminal"></pre>
		</div>
		<div class="w2p-modal-footer">
			<button type="button" id="w2p-refresh-log" class="w2p-btn w2p-btn-secondary">
				<i class="fa-solid fa-rotate"></i> <?php esc_html_e( 'Refresh', 'wp-genius' ); ?>
			</button>
			<button type="button" id="w2p-clear-log" class="w2p-btn w2p-btn-secondary w2p-btn-error-outline">
				<i class="fa-solid fa-trash"></i> <?php esc_html_e( 'Clear Log', 'wp-genius' ); ?>
			</button>
			<button type="button" id="w2p-modal-close-log" class="w2p-btn w2p-btn-primary">
				<?php esc_html_e( 'Close', 'wp-genius' ); ?>
			</button>
		</div>
	</div>
</div>
