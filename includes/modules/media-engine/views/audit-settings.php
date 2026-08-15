<?php
/**
 * Media Engine - Residual Media Audit Panel
 *
 * Scan the uploads directory for residual media and detect whether it has been offloaded via Minio bucket HEAD probes.
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
			<div class="w2p-audit-header-title">
				<h4><?php esc_html_e( 'Residual Media Audit', 'wp-genius' ); ?></h4>
				<p class="description" style="margin:4px 0 0;">
					<?php esc_html_e( 'Scan the uploads directory for residual media files, detect whether they have been offloaded via storage-bucket probing, and analyze why any files were not processed.', 'wp-genius' ); ?>
				</p>
			</div>
			<div class="w2p-header-actions w2p-audit-controls">
				<input type="text" id="w2p-audit-subdir" value="2026/07"
					placeholder="2026/07" class="w2p-audit-input" />
				<button type="button" id="w2p-audit-scan" class="w2p-btn w2p-btn-primary">
					<i class="fa-solid fa-magnifying-glass"></i>
					<?php esc_html_e( 'Start Scan', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-audit-stop" class="w2p-btn w2p-btn-stop w2p-hidden">
					<i class="fa-solid fa-stop"></i>
					<?php esc_html_e( 'Stop', 'wp-genius' ); ?>
				</button>
			</div>
		</div>

		<div class="w2p-section-body">

			<!-- Progress -->
			<div id="w2p-audit-progress" class="w2p-hidden w2p-audit-progress">
				<span id="w2p-audit-progress-text"></span>
			</div>

			<!-- Summary Stats -- 4 clickable cards -->
			<div id="w2p-audit-summary" class="w2p-hidden w2p-audit-stats-grid">
				<div class="w2p-audit-stat-card w2p-audit-stat-total" data-filter="total" role="button" tabindex="0" title="<?php esc_attr_e( 'Select all scanned files', 'wp-genius' ); ?>">
					<span class="label"><?php esc_html_e( 'Scanned Files', 'wp-genius' ); ?></span>
					<span class="value" data-stat="total">0</span>
				</div>
				<div class="w2p-audit-stat-card w2p-audit-stat-cleanable" data-filter="cleanable" role="button" tabindex="0" title="<?php esc_attr_e( 'Select cleanable (A) files', 'wp-genius' ); ?>">
					<span class="label"><?php esc_html_e( 'Cleanable (A)', 'wp-genius' ); ?></span>
					<span class="value" data-stat="cleanable">0</span>
				</div>
				<div class="w2p-audit-stat-card w2p-audit-stat-not_offloaded" data-filter="not_offloaded" role="button" tabindex="0" title="<?php esc_attr_e( 'Select not-offloaded (B) files', 'wp-genius' ); ?>">
					<span class="label"><?php esc_html_e( 'Not Offloaded (B)', 'wp-genius' ); ?></span>
					<span class="value" data-stat="not_offloaded">0</span>
				</div>
				<div class="w2p-audit-stat-card w2p-audit-stat-orphan" data-filter="orphan" role="button" tabindex="0" title="<?php esc_attr_e( 'Select orphan (C) files', 'wp-genius' ); ?>">
					<span class="label"><?php esc_html_e( 'Orphan (C)', 'wp-genius' ); ?></span>
					<span class="value" data-stat="orphan">0</span>
				</div>
			</div>

			<!-- Results Table -->
			<div id="w2p-audit-results" class="w2p-hidden">
				<div class="w2p-audit-actions w2p-flex w2p-gap-sm w2p-items-center">
					<button type="button" id="w2p-audit-clean-all" class="w2p-btn w2p-btn-stop w2p-hidden">
						<i class="fa-solid fa-trash"></i>
						<?php esc_html_e( 'Clean deletable files (Class A)', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-audit-enqueue-all" class="w2p-btn w2p-btn-secondary w2p-hidden">
						<i class="fa-solid fa-plus"></i>
						<?php esc_html_e( 'Enqueue eligible files into batch queue (Class B)', 'wp-genius' ); ?>
					</button>
				</div>
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

		</div>
	</div>
</div>

<style>
/* Residual Media Audit -- 4 clickable stat cards (Batch Processing style) */
.w2p-audit-stats-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: var(--w2p-spacing-md);
	margin-bottom: var(--w2p-spacing-lg);
}

.w2p-audit-stat-card {
	background: var(--w2p-bg-surface-secondary);
	border: 1px solid var(--w2p-border-color-light);
	border-radius: var(--w2p-radius-lg);
	padding: var(--w2p-spacing-lg);
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: var(--w2p-spacing-sm);
	cursor: pointer;
	transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
	user-select: none;
}

.w2p-audit-stat-card:hover {
	border-color: var(--w2p-color-primary);
	box-shadow: 0 0 0 1px var(--w2p-color-primary);
}

.w2p-audit-stat-card:active {
	transform: translateY(1px);
}

.w2p-audit-stat-card .label {
	font-size: var(--w2p-font-size-xs);
	font-weight: var(--w2p-font-weight-bold);
	color: var(--w2p-text-muted);
	text-transform: uppercase;
	text-align: center;
}

.w2p-audit-stat-card .value {
	font-family: var(--w2p-font-family-value);
	font-size: var(--w2p-font-size-2xl);
	line-height: 1;
}

.w2p-audit-stat-cleanable .value { color: var(--w2p-color-success); }
.w2p-audit-stat-not_offloaded .value { color: var(--w2p-color-warning); }
.w2p-audit-stat-orphan .value { color: var(--w2p-color-error); }
.w2p-audit-stat-total .value { color: var(--w2p-color-primary); }

/* Selected state */
.w2p-audit-stat-card.w2p-is-selected {
	border-color: var(--w2p-color-primary);
	box-shadow: 0 0 0 2px var(--w2p-color-primary);
	background: var(--w2p-color-primary-bg, var(--w2p-bg-surface-secondary));
}

/* Gap between action buttons and the file list below */
.w2p-audit-actions {
	margin-bottom: var(--w2p-spacing-md);
}

/* Scan controls inside the header */
.w2p-audit-controls {
	flex-wrap: nowrap;
}
.w2p-audit-input {
	width: 210px;
}

/* File column -- single line, path+filename, no wrapping */
.w2p-audit-file-cell {
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 360px;
}
.w2p-audit-file-cell .w2p-audit-filename {
	display: inline;
	overflow: hidden;
	text-overflow: ellipsis;
}
</style>
