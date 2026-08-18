<?php
/**
 * Media Turbo Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = W2P_Settings::tab_with_legacy( 'media_engine_tabs', 'w2p_media_turbo_settings', array() );
?>

<div class="w2p-settings-panel w2p-media-turbo-settings">
	
	<!-- Media Processing Center -->
	<div class="w2p-section">

		<!-- <div class="w2p-section-header">
			<h4><?php esc_html_e( 'Media Processing Center', 'wp-genius' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Process images using external command-line tools.', 'wp-genius' ); ?></p>
		</div> -->

		<div class="w2p-section-body">
			
			<!-- Processing Actions -->
			<div class="w2p-processing-actions">
				<div class="w2p-flex w2p-gap-sm w2p-items-center">
					<button type="button" id="w2p-get-stats" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-chart-bar"></i>
						<?php esc_html_e( 'Get Pending Stats', 'wp-genius' ); ?>
					</button>
					<?php
					// Retrieve settings from option since we removed the form
					// This ensures JS gets the correct values from the database
					$scan_limit = isset( $settings['scan_limit'] ) ? absint( $settings['scan_limit'] ) : 500;
					$batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 10;
					?>
					<button type="button" id="w2p-start-conversion" class="w2p-btn w2p-btn-secondary">
						<i class="fa-solid fa-play"></i>
						<?php /* translators: 1: scan limit, 2: batch size. */ printf( esc_html__( 'Batch Conversion (%1$d items, batch %2$d)', 'wp-genius' ), absint( $scan_limit ), absint( $batch_size ) ); ?>
					</button>
					<!-- 全自动处理（新）：自动循环 扫描 → 批次转换 → 再扫描 → 再转换 直到全部完成 -->
					<button type="button" id="w2p-start-auto" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-rotate"></i>
						<?php esc_html_e( '全自动处理', 'wp-genius' ); ?>
					</button>
					<!-- 暂停/恢复（新，初始隐藏） -->
					<button type="button" id="w2p-pause-auto" class="w2p-btn w2p-btn-warning w2p-hidden">
						<i class="fa-solid fa-pause"></i>
						<?php esc_html_e( '暂停', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-stop-conversion" class="w2p-btn w2p-btn-stop w2p-hidden">
						<i class="fa-solid fa-stop"></i>
						<?php esc_html_e( 'Stop Processing', 'wp-genius' ); ?>
					</button>
					<span style="border-left:1px solid var(--w2p-border-color);height:24px;margin:0 4px;"></span>
					<button type="button" id="w2p-view-log" class="w2p-btn w2p-btn-secondary">
						<i class="fa-solid fa-file-lines"></i>
						<?php esc_html_e( 'View Log', 'wp-genius' ); ?>
					</button>
				</div>
			</div>

			<!-- Processing Output -->
			<div id="w2p-processing-output" class="w2p-hidden w2p-terminal">
				<div id="w2p-output-content"></div>
			</div>

			<!-- Attachment List -->
			<div id="w2p-attachment-list" class="w2p-hidden w2p-attachment-list ">
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
						<tbody id="w2p-attachment-tbody">
							<!-- Attachments will be inserted here -->
						</tbody>
					</table>
				</div>
			</div>

			<!-- Processing Stats -->
			<div id="w2p-processing-stats" class="w2p-hidden w2p-processing-stats">
				<h5 class="w2p-mb-sm">
					<?php esc_html_e( 'Processing Statistics', 'wp-genius' ); ?>
				</h5>
				<div id="w2p-stats-content"></div>
			</div>
		</div>
	</div>

</div>

<!-- Log Viewer Modal -->
<div id="w2p-log-modal" class="w2p-log-overlay">
	<div class="w2p-confirm-modal w2p-log-viewer">
		<div class="w2p-modal-header">
			<h4>
				<i class="fa-solid fa-file-lines"></i>
				<?php esc_html_e( 'Conversion Log', 'wp-genius' ); ?>
				<span id="w2p-log-size" style="font-weight:normal;font-size:13px;margin-left:8px;opacity:0.7;"></span>
			</h4>
			<button type="button" id="w2p-close-log" class="w2p-modal-close" style="background:none;border:none;cursor:pointer;font-size:22px;color:inherit;padding:2px 6px;line-height:1;">&times;</button>
		</div>
		<div class="w2p-modal-body">
			<pre id="w2p-log-content" class="w2p-terminal" style="margin:0;height:420px;white-space:pre-wrap;word-break:break-all;"></pre>
		</div>
		<div class="w2p-modal-footer">
			<button type="button" id="w2p-refresh-log" class="w2p-btn w2p-btn-secondary">
				<i class="fa-solid fa-rotate"></i> <?php esc_html_e( 'Refresh', 'wp-genius' ); ?>
			</button>
			<button type="button" id="w2p-clear-log" class="w2p-btn w2p-btn-secondary" style="color:var(--w2p-color-error);border-color:var(--w2p-color-error);">
				<i class="fa-solid fa-trash"></i> <?php esc_html_e( 'Clear Log', 'wp-genius' ); ?>
			</button>
			<button type="button" id="w2p-modal-close-log" class="w2p-btn w2p-btn-primary">
				<?php esc_html_e( 'Close', 'wp-genius' ); ?>
			</button>
		</div>
	</div>
</div>

<style>
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
}
#w2p-log-modal .w2p-modal-footer .w2p-btn-secondary[style*="error"] {
	background: transparent;
}
#w2p-log-modal .w2p-modal-footer .w2p-btn-secondary[style*="error"]:hover {
	background: var(--w2p-color-error);
	color: #fff;
}
#w2p-log-modal .w2p-modal-header h4 {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
}
</style>

<script>
(function($) {
	'use strict';
	
	// 从 PHP 传入的配置
	const scanLimit = <?php echo absint( $settings['scan_limit'] ?? 1000 ); ?>;
	const batchSize = <?php echo absint( $settings['batch_size'] ?? 10 ); ?>;
	
	// Pass configuration to MediaProcessingUI
	if (typeof MediaProcessingUI !== 'undefined') {
		MediaProcessingUI.concurrentWorkers = batchSize;
	}
	
	// 设置全局配置供 media-processing-ui.js 使用
	window.w2pMediaConfig = {
		scanLimit: scanLimit,
		batchSize: batchSize
	};
	
	// Execute WP-CLI command via AJAX
	function executeWPCLI(command, buttonId) {
		const $button = $('#' + buttonId);
		const $output = $('#w2p-processing-output');
		const $outputContent = $('#w2p-output-content');
		const $stats = $('#w2p-processing-stats');
		const $statsContent = $('#w2p-stats-content');
		
		// Disable button and show loading
		$button.prop('disabled', true);
		$button.find('i').removeClass().addClass('fa-solid fa-spinner fa-spin');
		
		// Show output area
		$output.show();
		$outputContent.html('<div class="w2p-terminal-command">$ ' + command + '</div><div class="w2p-terminal-output">Processing...</div>');
		
		// Execute command
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'w2p_execute_wpcli',
				nonce: w2pMediaTurbo.nonce,
				command: command
			},
			success: function(response) {
				if (response.success) {
					// Show output
					const output = response.data.output || '';
					const lines = output.split('\n');
					let html = '<div class="w2p-terminal-command">$ ' + command + '</div>';
					lines.forEach(line => {
						if (line.trim()) {
							html += '<div>' + escapeHtml(line) + '</div>';
						}
					});
					$outputContent.html(html);
					
					// Show stats if available
					if (response.data.stats) {
						$stats.show();
						$statsContent.html(formatStats(response.data.stats));
					}
					
					// Show success toast
					if (typeof w2p !== 'undefined' && w2p.toast) {
						w2p.toast('<?php esc_html_e( 'Command executed successfully!', 'wp-genius' ); ?>', 'success');
					}
				} else {
					$outputContent.html(
						'<div class="w2p-terminal-command">$ ' + command + '</div>' +
						'<div class="w2p-terminal-error">Error: ' + escapeHtml(response.data || 'Unknown error') + '</div>'
					);
					
					if (typeof w2p !== 'undefined' && w2p.toast) {
						w2p.toast('<?php esc_html_e( 'Command failed!', 'wp-genius' ); ?>', 'error');
					}
				}
			},
			error: function(xhr, status, error) {
				$outputContent.html(
					'<div class="w2p-terminal-command">$ ' + command + '</div>' +
					'<div class="w2p-terminal-error">AJAX Error: ' + escapeHtml(error) + '</div>'
				);
				
				if (typeof w2p !== 'undefined' && w2p.toast) {
					w2p.toast('<?php esc_html_e( 'Request failed!', 'wp-genius' ); ?>', 'error');
				}
			},
			complete: function() {
				// Re-enable button
				$button.prop('disabled', false);
				resetButtonIcon(buttonId);
			}
		});
	}
	
	// Reset button icon
	function resetButtonIcon(buttonId) {
		const icons = {
			'w2p-check-environment': 'fa-stethoscope',
			'w2p-get-stats': 'fa-chart-bar',
			'w2p-start-conversion': 'fa-play',
			'w2p-start-parallel': 'fa-rocket'
		};
		$('#' + buttonId).find('i').removeClass().addClass('fa-solid ' + icons[buttonId]);
	}
	
	// Format stats
	function formatStats(stats) {
		let html = '<div class="w2p-stats-grid">';
		for (const [key, value] of Object.entries(stats)) {
			html += '<div>';
			html += '<div class="w2p-stat-label">' + escapeHtml(key) + '</div>';
			html += '<div class="w2p-stat-value">' + escapeHtml(value) + '</div>';
			html += '</div>';
		}
		html += '</div>';
		return html;
	}
	
	// Escape HTML
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}
	
	

	// Note: Button click handlers have been moved to media-processing-ui.js
	// The MediaProcessingUI module now handles all button interactions
	
})(jQuery);
</script>

<!-- Template: Queue Stats -->
<script type="text/html" id="tmpl-w2p-media-queue-stats">
	<div class="w2p-smart-aui-stats-row">
		<span class="stat-item total">
			<span class="label"><?php esc_html_e( 'Total', 'wp-genius' ); ?></span>
			<span class="value">{{ data.total }}</span>
		</span>
		<span class="stat-item skipped">
			<span class="label"><?php esc_html_e( 'Pending', 'wp-genius' ); ?></span>
			<span class="value">{{ data.pending }}</span>
		</span>
		<span class="stat-item threads">
			<span class="label"><?php esc_html_e( 'Processing', 'wp-genius' ); ?></span>
			<span class="value">{{ data.processing }}</span>
		</span>
		<span class="stat-item success">
			<span class="label"><?php esc_html_e( 'Completed', 'wp-genius' ); ?></span>
			<span class="value">{{ data.completed }}</span>
		</span>
		<span class="stat-item failed">
			<span class="label"><?php esc_html_e( 'Failed', 'wp-genius' ); ?></span>
			<span class="value">{{ data.failed }}</span>
		</span>
	</div>
</script>
