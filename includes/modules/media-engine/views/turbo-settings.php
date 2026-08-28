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
			</div>

			<!-- Step 2: Residual Media Audit Action Bar & Stats -->
			<div class="w2p-step-panel w2p-hidden" id="w2p-step-2-panel">
				<div class="w2p-processing-actions">
					<div class="w2p-flex w2p-gap-sm w2p-items-center w2p-flex-wrap">
						<input type="text" id="w2p-audit-subdir" value="2026/07" placeholder="2026/07" class="w2p-audit-input" />
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

				<!-- Step 2 Stat Cards -->
				<div id="w2p-audit-summary" class="w2p-audit-stats-grid">
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
				<!-- Prerequisite Notification Banner -->
				<div id="w2p-fixer-prereq-banner" class="w2p-notice-banner w2p-notice-info">
					<i class="fa-solid fa-shield-halved"></i>
					<span id="w2p-fixer-prereq-text">
						<?php esc_html_e( 'Prerequisite safety check: URL Fixer will verify that all media has been offloaded and local uploads are cleared before execution.', 'wp-genius' ); ?>
					</span>
				</div>

				<div class="w2p-processing-actions">
					<div class="w2p-flex w2p-gap-sm w2p-items-center w2p-flex-wrap">
						<button type="button" id="w2p-fixer-check-prereq" class="w2p-btn w2p-btn-secondary">
							<i class="fa-solid fa-clipboard-check"></i>
							<?php esc_html_e( 'Check Prerequisites', 'wp-genius' ); ?>
						</button>
						<button type="button" id="w2p-fixer-get-stats" class="w2p-btn w2p-btn-secondary">
							<i class="fa-solid fa-chart-pie"></i>
							<?php esc_html_e( 'Get Stats', 'wp-genius' ); ?>
						</button>
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

				<!-- Step 3 Stat Cards -->
				<div id="w2p-fixer-summary" class="w2p-fixer-stats-grid">
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
				<div id="w2p-queue-stats" class="w2p-smart-aui-body"></div>
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

<style>
/* Workflow Step Navigation */
.w2p-workflow-steps-nav {
	display: flex;
	gap: var(--w2p-spacing-md);
	margin-bottom: var(--w2p-spacing-lg);
	border-bottom: 1px solid var(--w2p-border-color-light);
	padding-bottom: var(--w2p-spacing-md);
}

.w2p-workflow-step-btn {
	flex: 1;
	display: flex;
	align-items: center;
	gap: var(--w2p-spacing-md);
	padding: var(--w2p-spacing-md) var(--w2p-spacing-lg);
	background: var(--w2p-bg-surface-secondary);
	border: 1px solid var(--w2p-border-color-light);
	border-radius: var(--w2p-radius-lg);
	cursor: pointer;
	text-align: left;
	transition: all 0.2s ease;
}

.w2p-workflow-step-btn:hover {
	border-color: var(--w2p-color-primary);
	background: var(--w2p-bg-surface);
}

.w2p-workflow-step-btn.active {
	border-color: var(--w2p-color-primary);
	background: var(--w2p-bg-surface);
	box-shadow: 0 0 0 2px var(--w2p-color-primary);
}

.w2p-workflow-step-btn .w2p-step-num {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: var(--w2p-border-color-light);
	color: var(--w2p-text-muted);
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: bold;
	font-size: 14px;
	flex-shrink: 0;
}

.w2p-workflow-step-btn.active .w2p-step-num {
	background: var(--w2p-color-primary);
	color: #fff;
}

.w2p-workflow-step-btn .w2p-step-info {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.w2p-workflow-step-btn .w2p-step-title {
	font-weight: var(--w2p-font-weight-bold);
	font-size: var(--w2p-font-size-sm);
	color: var(--w2p-text-main);
}

.w2p-workflow-step-btn .w2p-step-desc {
	font-size: var(--w2p-font-size-xs);
	color: var(--w2p-text-muted);
}

.w2p-action-divider {
	border-left: 1px solid var(--w2p-border-color);
	height: 24px;
	margin: 0 4px;
	display: inline-block;
}

/* Notice Banner */
.w2p-notice-banner {
	padding: var(--w2p-spacing-md) var(--w2p-spacing-lg);
	border-radius: var(--w2p-radius-md);
	margin-bottom: var(--w2p-spacing-md);
	display: flex;
	align-items: center;
	gap: var(--w2p-spacing-md);
	font-size: var(--w2p-font-size-sm);
}

.w2p-notice-banner.w2p-notice-info {
	background: rgba(2, 132, 199, 0.1);
	border: 1px solid rgba(2, 132, 199, 0.3);
	color: var(--w2p-color-info, #0284c7);
}

.w2p-notice-banner.w2p-notice-warning {
	background: rgba(245, 158, 11, 0.1);
	border: 1px solid rgba(245, 158, 11, 0.3);
	color: var(--w2p-color-warning, #d97706);
}

.w2p-notice-banner.w2p-notice-success {
	background: rgba(16, 185, 129, 0.1);
	border: 1px solid rgba(16, 185, 129, 0.3);
	color: var(--w2p-color-success, #059669);
}

/* Grid & Cards for Step 2 & Step 3 */
.w2p-audit-stats-grid,
.w2p-fixer-stats-grid {
	display: grid;
	gap: var(--w2p-spacing-md);
	margin-top: var(--w2p-spacing-md);
	margin-bottom: var(--w2p-spacing-lg);
}

.w2p-audit-stats-grid { grid-template-columns: repeat(4, 1fr); }
.w2p-fixer-stats-grid { grid-template-columns: repeat(5, 1fr); }

.w2p-audit-stat-card,
.w2p-fixer-stat-card {
	background: var(--w2p-bg-surface-secondary);
	border: 1px solid var(--w2p-border-color-light);
	border-radius: var(--w2p-radius-lg);
	padding: var(--w2p-spacing-md);
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: var(--w2p-spacing-xs);
	cursor: pointer;
	transition: border-color 0.15s ease, box-shadow 0.15s ease;
	user-select: none;
}

.w2p-audit-stat-card:hover,
.w2p-fixer-stat-card:hover {
	border-color: var(--w2p-color-primary);
	box-shadow: 0 0 0 1px var(--w2p-color-primary);
}

.w2p-audit-stat-card .label,
.w2p-fixer-stat-card .label {
	font-size: var(--w2p-font-size-xs);
	font-weight: var(--w2p-font-weight-bold);
	color: var(--w2p-text-muted);
	text-transform: uppercase;
	text-align: center;
}

.w2p-audit-stat-card .value,
.w2p-fixer-stat-card .value {
	font-family: var(--w2p-font-family-value);
	font-size: var(--w2p-font-size-xl);
	line-height: 1;
}

.w2p-audit-stat-cleanable .value { color: var(--w2p-color-success); }
.w2p-audit-stat-not_offloaded .value { color: var(--w2p-color-warning); }
.w2p-audit-stat-orphan .value { color: var(--w2p-color-error); }
.w2p-audit-stat-total .value { color: var(--w2p-color-primary); }

.w2p-fixer-stat-pending .value { color: var(--w2p-color-primary); }
.w2p-fixer-stat-fixable .value { color: var(--w2p-color-warning); }
.w2p-fixer-stat-ext .value { color: var(--w2p-color-success); }
.w2p-fixer-stat-path .value { color: var(--w2p-color-info, #0284c7); }
.w2p-fixer-stat-external .value { color: var(--w2p-text-muted); }

.w2p-audit-input { width: 180px; }

/* Log Modal Styling */
#w2p-log-modal {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.6);
	z-index: var(--w2p-z-index-overlay, 999999);
	display: flex;
	justify-content: center;
	align-items: center;
	opacity: 0;
	visibility: hidden;
	transition: opacity 0.2s ease;
}
#w2p-log-modal.active {
	opacity: 1;
	visibility: visible;
}
#w2p-log-modal .w2p-confirm-modal.w2p-log-viewer {
	width: 860px;
	max-width: 92%;
	max-height: 88vh;
	flex-direction: column;
	display: flex;
	transform: scale(0.1);
	transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
#w2p-log-modal.active .w2p-confirm-modal.w2p-log-viewer {
	transform: scale(1);
}
#w2p-log-modal .w2p-confirm-modal.w2p-log-viewer .w2p-modal-body {
	height: auto;
	max-height: none;
	overflow: hidden;
	padding: 0;
	flex: 1;
	min-height: 0;
}
#w2p-log-modal .w2p-confirm-modal.w2p-log-viewer .w2p-modal-body pre {
	height: 420px;
	overflow-y: auto;
	border-radius: 0;
	margin: 0;
	white-space: pre-wrap;
	word-break: break-all;
}
.w2p-log-size-label {
	font-weight: normal;
	font-size: 13px;
	margin-left: 8px;
	opacity: 0.7;
}
.w2p-btn-error-outline {
	color: var(--w2p-color-error);
	border-color: var(--w2p-color-error);
}
.w2p-btn-error-outline:hover {
	background: var(--w2p-color-error);
	color: #fff;
}
.w2p-modal-close {
	background: none;
	border: none;
	cursor: pointer;
	font-size: 22px;
	color: inherit;
	padding: 2px 6px;
	line-height: 1;
}

/* Fixer Previews */
.w2p-fixer-preview-item {
	font-family: monospace;
	font-size: 11px;
	line-height: 1.4;
	margin-bottom: 4px;
	word-break: break-all;
}
.w2p-fixer-preview-item .old-url {
	color: var(--w2p-color-error);
	text-decoration: line-through;
}
.w2p-fixer-preview-item .new-url {
	color: var(--w2p-color-success);
	font-weight: bold;
}
</style>
