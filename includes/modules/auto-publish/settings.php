<?php
/**
 * Auto Publish Module Settings UI
 *
 * @package WP_Genius
 * @subpackage Modules/AutoPublish
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'w2p_auto_publish_settings', [] );
$defaults = [
	'cron_enabled' => false,
	'interval'     => 'hourly',
	'batch_size'   => 5,
];
$settings = wp_parse_args( $settings, $defaults );

$draft_count = count( get_posts( [
	'post_status'    => 'draft',
	'posts_per_page' => -1,
	'fields'         => 'ids',
] ) );
?>

<div class="w2p-settings-panel w2p-auto-publish-settings">
	<div class="w2p-auto-publish-split">
		<!-- Configuration Form -->
		<div class="w2p-auto-publish-config">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
				<input type="hidden" name="action" value="word2posts_save_module_settings" />
				<input type="hidden" name="module_id" value="auto-publish" />

				<h3><?php _e( 'Automation Settings', 'wp-genius' ); ?></h3>
				
				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( 'Enable Auto Publish', 'wp-genius' ); ?></th>
						<td>
							<label class="switch">
								<input type="checkbox" name="w2p_auto_publish_settings[cron_enabled]" value="1" <?php checked( $settings['cron_enabled'] ); ?> />
								<span class="slider"></span>
							</label>
							<p class="description"><?php _e( 'Enable background scheduled publishing using WP-Cron.', 'wp-genius' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Interval', 'wp-genius' ); ?></th>
						<td>
							<select name="w2p_auto_publish_settings[interval]">
								<option value="w2p_every_5_minutes" <?php selected( $settings['interval'], 'w2p_every_5_minutes' ); ?>><?php _e( 'Every 5 Minutes', 'wp-genius' ); ?></option>
								<option value="w2p_every_15_minutes" <?php selected( $settings['interval'], 'w2p_every_15_minutes' ); ?>><?php _e( 'Every 15 Minutes', 'wp-genius' ); ?></option>
								<option value="w2p_every_30_minutes" <?php selected( $settings['interval'], 'w2p_every_30_minutes' ); ?>><?php _e( 'Every 30 Minutes', 'wp-genius' ); ?></option>
								<option value="hourly" <?php selected( $settings['interval'], 'hourly' ); ?>><?php _e( 'Hourly', 'wp-genius' ); ?></option>
								<option value="twicedaily" <?php selected( $settings['interval'], 'twicedaily' ); ?>><?php _e( 'Twice Daily', 'wp-genius' ); ?></option>
								<option value="daily" <?php selected( $settings['interval'], 'daily' ); ?>><?php _e( 'Daily', 'wp-genius' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Batch Size', 'wp-genius' ); ?></th>
						<td>
							<input type="number" name="w2p_auto_publish_settings[batch_size]" value="<?php echo esc_attr( $settings['batch_size'] ); ?>" min="1" max="50" />
							<p class="description"><?php _e( 'Number of drafts to publish in each execution.', 'wp-genius' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<hr />

			<!-- Progress Panel is now rendered via admin_notices hook in module.php -->

			<!-- Manual Processing -->
			<h3><?php _e( 'Manual Bulk Publish', 'wp-genius' ); ?></h3>
			<div class="w2p-manual-publish-controls">
				<p><?php printf( __( 'Current drafts: <strong>%d</strong>', 'wp-genius' ), $draft_count ); ?></p>
				<button type="button" id="w2p-start-publish" class="button button-primary" <?php disabled( $draft_count == 0 ); ?>>
					<?php _e( 'Start Bulk Publish Now', 'wp-genius' ); ?>
				</button>
				<button type="button" id="w2p-stop-publish" class="button button-secondary" style="display:none;">
					<?php _e( 'Stop', 'wp-genius' ); ?>
				</button>
				
				<div id="w2p-publish-progress" style="display:none; margin-top:15px;">
					<div class="progress-bar-container">
						<div class="progress-bar-inner" style="width: 0%;"></div>
					</div>
					<p class="progress-text"><?php _e( 'Initializing...', 'wp-genius' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Logs Area -->
		<div class="w2p-auto-publish-logs">
			<div class="w2p-log-header-flex">
				<h3><?php _e( 'Publish Logs', 'wp-genius' ); ?></h3>
				<button type="button" id="w2p-clean-logs" class="button button-small"><?php _e( 'Clean Logs', 'wp-genius' ); ?></button>
			</div>
			<div class="w2p-log-container">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th width="20%"><?php _e( 'Time', 'wp-genius' ); ?></th>
							<th><?php _e( 'Post', 'wp-genius' ); ?></th>
							<th width="15%"><?php _e( 'Source', 'wp-genius' ); ?></th>
							<th width="15%"><?php _e( 'Status', 'wp-genius' ); ?></th>
						</tr>
					</thead>
					<tbody id="w2p-publish-logs-body">
						<?php
						$logs = get_option( 'w2p_auto_publish_logs', [] );
						if ( empty( $logs ) ): ?>
							<tr><td colspan="4"><?php _e( 'No activity logged yet.', 'wp-genius' ); ?></td></tr>
						<?php else:
							foreach ( $logs as $log ): 
								$source = isset($log['source']) ? $log['source'] : 'manual';
								$source_label = ($source === 'scheduled') ? __('Scheduled', 'wp-genius') : __('Manual', 'wp-genius');
								?>
								<tr>
									<td><?php echo esc_html( $log['time'] ); ?></td>
									<td><?php echo esc_html( $log['title'] ); ?> (ID: <?php echo esc_html( $log['post_id'] ); ?>)</td>
									<td><span class="source-badge <?php echo esc_attr( $source ); ?>"><?php echo esc_html( $source_label ); ?></span></td>
									<td><span class="status-badge <?php echo esc_attr( $log['status'] ); ?>"><?php echo esc_html( $log['status'] ); ?></span></td>
								</tr>
							<?php endforeach;
						endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	let isRunning = false;
	let nonce = '<?php echo wp_create_nonce("w2p_auto_publish_nonce"); ?>';
	let totalToProcess = <?php echo $draft_count; ?>;
	let processedCount = 0;

	$('#w2p-start-publish').on('click', function() {
		if (isRunning) return;
		
		let btn = $(this);
		
		// Check for scheduled lock before starting
		refreshStats().done(function(response) {
			if (response.success && response.data.active_lock === 'scheduled') {
				alert('<?php _e("A scheduled publishing task is currently running. Please wait for it to finish.", "wp-genius"); ?>');
				return;
			}

			isRunning = true;
			btn.hide();
			$('#w2p-stop-publish').show();
			$('#w2p-publish-progress').show();
			processedCount = 0;
			updateProgress(0);
			processNext();
		});
	});

	$('#w2p-stop-publish').on('click', function() {
		isRunning = false;
		$(this).hide();
		$('#w2p-start-publish').show();
		$('.progress-text').text('Stopping after current post...');
	});

	$('#w2p-clean-logs').on('click', function() {
		if (!confirm('<?php _e("Are you sure you want to clear all publish logs?", "wp-genius"); ?>')) return;
		
		let btn = $(this);
		btn.prop('disabled', true);
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'w2p_auto_publish_clean_logs',
				nonce: nonce
			},
			success: function(response) {
				btn.prop('disabled', false);
				if (response.success) {
					refreshStats();
				}
			}
		});
	});

	function processNext() {
		if (!isRunning) {
			$('.progress-text').text('Stopped.');
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'w2p_auto_publish_process',
				nonce: nonce
			},
			success: function(response) {
				if (!isRunning) {
					$('.progress-text').text('Stopped.');
					return;
				}

				if (response.success) {
					if (response.data.finished) {
						$('.progress-text').text('All finished!');
						$('#w2p-stop-publish').hide();
						$('#w2p-start-publish').show().prop('disabled', true);
						updateProgress(100);
						isRunning = false;
					} else {
						processedCount++;
						let progress = (processedCount / totalToProcess) * 100;
						updateProgress(progress);
						$('.progress-text').html('<strong>Publishing Post ' + response.data.post_id + ':</strong> ' + response.data.title + ' (' + processedCount + '/' + totalToProcess + ')');
						refreshStats();
						processNext();
					}
				} else {
					$('.progress-text').text('Error: ' + response.data);
					isRunning = false;
				}
			},
			error: function() {
				$('.progress-text').text('Connection error.');
				isRunning = false;
			}
		});
	}

	function updateProgress(percent) {
		$('.progress-bar-inner').css('width', percent + '%');
	}

	// Handle stats update from external shared script
	$(document).on('w2p_auto_publish_stats_refreshed', function(e, data) {
		totalToProcess = data.draft_count + processedCount;
		$('.w2p-manual-publish-controls strong').text(data.draft_count);
		
		// Button state based on lock
		if (data.active_lock === 'scheduled') {
			if (!isRunning) {
				$('#w2p-start-publish').prop('disabled', true).attr('title', '<?php _e("Scheduled task running", "wp-genius"); ?>');
			}
		} else {
			if (!isRunning) {
				$('#w2p-start-publish').prop('disabled', data.draft_count === 0);
			}
		}

		if (isRunning && data.next_post) {
			$('.progress-text').html('<strong>Next up (Post ' + data.next_post.id + '):</strong> ' + data.next_post.title + ' (' + processedCount + '/' + totalToProcess + ')');
		}

		// Update logs
		updateLogs(data.logs);
	});

	function updateLogs(logs) {
		let logHtml = '';
		if (!logs || logs.length === 0) {
			logHtml = '<tr><td colspan="4"><?php _e( "No activity logged yet.", "wp-genius" ); ?></td></tr>';
		} else {
			logs.forEach(function(log) {
				let source = log.source || 'manual';
				let sourceLabel = (source === 'scheduled') ? '<?php _e("Scheduled", "wp-genius"); ?>' : '<?php _e("Manual", "wp-genius"); ?>';
				logHtml += `<tr>
					<td>${log.time}</td>
					<td>${log.title} (ID: ${log.post_id})</td>
					<td><span class="source-badge ${source}">${sourceLabel}</span></td>
					<td><span class="status-badge ${log.status}">${log.status}</span></td>
				</tr>`;
			});
		}
		$('#w2p-publish-logs-body').html(logHtml);
	}

	function refreshStats() {
		return $.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'w2p_auto_publish_get_stats',
				nonce: nonce
			},
			success: function(response) {
				if (response.success) {
					// Trigger event so shared JS and this page both hear it
					$(document).trigger('w2p_auto_publish_stats_refreshed', [response.data]);
				}
			}
		});
	}
});
</script>
