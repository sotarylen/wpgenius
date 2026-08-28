<?php
/**
 * Media Engine - Content URL & Format Fixer Panel
 *
 * Scans post contents for leftover /wp-content/uploads/ references,
 * ignores external host URLs (e.g. https://weadown.com/wp-content/uploads/...),
 * probes the MinIO bucket (/wp-media/) via concurrent HEAD requests to determine
 * the correct path and file extension (e.g. converting .jpg/.png/.gif to .webp),
 * and performs safe, accurate batch replacements.
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="w2p-settings-panel w2p-media-url-fixer-settings">
	<div class="w2p-section">

		<div class="w2p-section-header">
			<div class="w2p-fixer-header-title">
				<h4><?php esc_html_e( 'Content URL & Format Fixer', 'wp-genius' ); ?></h4>
				<p class="description">
					<?php esc_html_e( 'Scan post contents for leftover /wp-content/uploads/ URLs, safely ignore external domain URLs, and replace local URLs with /wp-media/ MinIO storage paths while correcting converted WebP extensions via concurrent bucket probing.', 'wp-genius' ); ?>
				</p>
			</div>
			<div class="w2p-header-actions w2p-fixer-controls">
				<button type="button" id="w2p-fixer-get-stats" class="w2p-btn w2p-btn-secondary">
					<i class="fa-solid fa-chart-pie"></i>
					<?php esc_html_e( 'Get Stats', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-fixer-scan" class="w2p-btn w2p-btn-primary">
					<i class="fa-solid fa-magnifying-glass"></i>
					<?php esc_html_e( 'Scan Posts', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-fixer-start" class="w2p-btn w2p-btn-secondary w2p-hidden">
					<i class="fa-solid fa-play"></i>
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
			</div>
		</div>

		<div class="w2p-section-body">

			<!-- Progress Bar & Status Line -->
			<div id="w2p-fixer-progress" class="w2p-hidden w2p-fixer-progress">
				<span id="w2p-fixer-progress-text"></span>
			</div>

			<!-- Summary Stats -- Clickable cards -->
			<div id="w2p-fixer-summary" class="w2p-fixer-stats-grid">
				<div class="w2p-fixer-stat-card w2p-fixer-stat-pending" data-filter="pending" role="button" tabindex="0">
					<span class="label"><?php esc_html_e( 'Pending Posts', 'wp-genius' ); ?></span>
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

			<!-- Terminal / Log Output -->
			<div id="w2p-fixer-terminal" class="w2p-hidden w2p-terminal">
				<div id="w2p-fixer-output-content"></div>
			</div>

			<!-- Results Table -->
			<div id="w2p-fixer-results" class="w2p-hidden">
				<div class="w2p-fixer-actions w2p-flex w2p-gap-sm w2p-items-center">
					<button type="button" id="w2p-fixer-fix-checked" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-wand-magic-sparkles"></i>
						<?php esc_html_e( 'Fix Selected Posts', 'wp-genius' ); ?>
					</button>
				</div>
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

<style>
/* URL Fixer Grid & Cards */
.w2p-fixer-stats-grid {
	display: grid;
	grid-template-columns: repeat(5, 1fr);
	gap: var(--w2p-spacing-md);
	margin-bottom: var(--w2p-spacing-lg);
}

.w2p-fixer-stat-card {
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

.w2p-fixer-stat-card:hover {
	border-color: var(--w2p-color-primary);
	box-shadow: 0 0 0 1px var(--w2p-color-primary);
}

.w2p-fixer-stat-card .label {
	font-size: var(--w2p-font-size-xs);
	font-weight: var(--w2p-font-weight-bold);
	color: var(--w2p-text-muted);
	text-transform: uppercase;
	text-align: center;
}

.w2p-fixer-stat-card .value {
	font-family: var(--w2p-font-family-value);
	font-size: var(--w2p-font-size-2xl);
	line-height: 1;
}

.w2p-fixer-stat-pending .value { color: var(--w2p-color-primary); }
.w2p-fixer-stat-fixable .value { color: var(--w2p-color-warning); }
.w2p-fixer-stat-ext .value { color: var(--w2p-color-success); }
.w2p-fixer-stat-path .value { color: var(--w2p-color-info, #0284c7); }
.w2p-fixer-stat-external .value { color: var(--w2p-text-muted); }

.w2p-fixer-stat-card.w2p-is-selected {
	border-color: var(--w2p-color-primary);
	box-shadow: 0 0 0 2px var(--w2p-color-primary);
	background: var(--w2p-color-primary-bg, var(--w2p-bg-surface-secondary));
}

.w2p-fixer-controls {
	flex-wrap: nowrap;
}

.w2p-fixer-actions {
	margin-bottom: var(--w2p-spacing-md);
}

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

