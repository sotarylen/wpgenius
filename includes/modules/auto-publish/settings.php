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
$processed_count = 0;
?>

<div class="w2p-settings-panel w2p-auto-publish-settings">
	<div class="w2p-auto-publish-split">
		<!-- Configuration Form -->
		<div class="w2p-auto-publish-config">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_auto_publish_nonce' ); ?>
				<input type="hidden" name="action" value="word2posts_save_module_settings" />
				<input type="hidden" name="module_id" value="auto-publish" />

				<div class="w2p-section">
					<div class="w2p-section-header">
						<h4><?php _e( 'Automation Settings', 'wp-genius' ); ?></h4>
					</div>
					<div class="w2p-section-body">
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label><?php _e( 'Enable Auto Publish', 'wp-genius' ); ?></label>
							</div>
							<div class="w2p-form-control">
								<label class="w2p-switch">
									<input type="checkbox" name="w2p_auto_publish_settings[cron_enabled]" value="1" <?php checked( $settings['cron_enabled'] ); ?> />
									<span class="w2p-slider"></span>
								</label>
								<p class="description"><?php _e( 'Enable background scheduled publishing using WP-Cron.', 'wp-genius' ); ?></p>
							</div>
						</div>

						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label><?php _e( 'Execution Interval', 'wp-genius' ); ?></label>
							</div>
							<div class="w2p-form-control">
								<select name="w2p_auto_publish_settings[interval]" class="w2p-input-medium">
									<option value="w2p_every_5_minutes" <?php selected( $settings['interval'], 'w2p_every_5_minutes' ); ?>><?php _e( 'Every 5 Minutes', 'wp-genius' ); ?></option>
									<option value="w2p_every_15_minutes" <?php selected( $settings['interval'], 'w2p_every_15_minutes' ); ?>><?php _e( 'Every 15 Minutes', 'wp-genius' ); ?></option>
									<option value="w2p_every_30_minutes" <?php selected( $settings['interval'], 'w2p_every_30_minutes' ); ?>><?php _e( 'Every 30 Minutes', 'wp-genius' ); ?></option>
									<option value="hourly" <?php selected( $settings['interval'], 'hourly' ); ?>><?php _e( 'Hourly', 'wp-genius' ); ?></option>
									<option value="twicedaily" <?php selected( $settings['interval'], 'twicedaily' ); ?>><?php _e( 'Twice Daily', 'wp-genius' ); ?></option>
									<option value="daily" <?php selected( $settings['interval'], 'daily' ); ?>><?php _e( 'Daily', 'wp-genius' ); ?></option>
								</select>
							</div>
						</div>

						<div class="w2p-form-row border-none">
					<div class="w2p-form-label">
						<label><?php _e( 'Batch Size', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<div class="w2p-range-group">
							<div class="w2p-range-header">
								<span class="w2p-range-label"><?php _e( 'Posts per Batch', 'wp-genius' ); ?></span>
								<span class="w2p-range-value"><?php echo esc_attr( $settings['batch_size'] ); ?></span>
							</div>
							<input type="range" 
							       class="w2p-range-slider" 
							       name="w2p_auto_publish_settings[batch_size]" 
							       min="10" 
							       max="100" 
							       step="5"
							       value="<?php echo esc_attr( $settings['batch_size'] ); ?>">
						</div>
						<p class="description"><?php _e( 'Number of drafts to publish in each execution.', 'wp-genius' ); ?></p>
					</div>
				</div>
					</div>
				</div>

				<div class="w2p-settings-actions">
					<button type="submit" name="submit" id="w2p-auto-publish-submit" class="w2p-btn w2p-btn-primary">
						<i class="fa-solid fa-floppy-disk"></i>
						<?php esc_attr_e( 'Save Automation Settings', 'wp-genius' ); ?>
					</button>
				</div>
			</form>
			<!-- Progress Panel is now rendered via admin_notices hook in module.php -->
		</div>

		<!-- Logs Area -->
		<div class="w2p-auto-publish-logs">

			<!-- Manual Processing -->
			<div class="w2p-section-header">
				<h4><?php _e( 'Manual Bulk Publish', 'wp-genius' ); ?></h4>
				<div class="w2p-header-actions">
					<button type="button" id="w2p-start-publish" class="w2p-btn w2p-btn-primary" <?php disabled( $draft_count == 0 ); ?>>
						<i class="fa-solid fa-play"></i>
						<?php _e( 'Start Now', 'wp-genius' ); ?>
					</button>
					<button type="button" id="w2p-stop-publish" class="w2p-btn w2p-btn-stop" style="display:none;">
						<i class="fa-solid fa-pause"></i>
						<?php _e( 'Stop', 'wp-genius' ); ?>
					</button>
				</div>
			</div>
			
			<div class="w2p-manual-publish-controls">
				<div class="w2p-manual-publish-info">
					<div class="w2p-progress-text">
						<?php printf( __( '<strong class="w2p-progress-text">%d/%d</strong>', 'wp-genius' ), $processed_count, $draft_count ); ?>
					</div>
					<div class="progress-text"><?php _e( 'Initializing...', 'wp-genius' ); ?></div>
				</div>
				
				<div id="w2p-publish-progress" style="display:none; margin-top:15px;">
					<div class="progress-bar-container">
						<div class="progress-bar-inner" style="width: 0%;"></div>
					</div>
                    
                    <!-- Smart AUI Preview Container -->
                    <div id="w2p-smart-aui-preview-area" class="w2p-smart-aui-preview-area" style="margin-top: 15px;"></div>
				</div>
			</div>

			<div class="w2p-section-header w2p-section-spacing">
				<h4><?php _e( 'Publish Logs', 'wp-genius' ); ?></h4>
				<button type="button" id="w2p-clean-logs" class="w2p-btn w2p-btn-secondary w2p-btn-small">
					<i class="fa-solid fa-trash"></i>
					<?php _e( 'Clear Logs', 'wp-genius' ); ?>
				</button>
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
    // Reuse Smart AUI nonce if available, otherwise we assume it's passed or available
    let smartAuiNonce = (typeof w2pSmartAuiParams !== 'undefined') ? w2pSmartAuiParams.nonce : ''; 
	let totalToProcess = <?php echo $draft_count; ?>;
	let processedCount = 0;
    let failedPostIds = []; // Global list of failed IDs to skip

    // Page exit protection
    $(window).on('beforeunload', function(e) {
        if (isRunning) {
            e.preventDefault();
            return 'Publishing is in progress. Are you sure you want to leave?';
        }
    });

	$('#w2p-start-publish').on('click', function() {
		if (isRunning) return;
		
		let btn = $(this);
		
		// Check for scheduled lock before starting
		refreshStats().done(function(response) {
			if (response.success && response.data.active_lock === 'scheduled') {
				w2p.toast('<?php _e("A scheduled publishing task is currently running. Please wait for it to finish.", "wp-genius"); ?>', 'warning');
				return;
			}

			isRunning = true;
			btn.hide();
			$('#w2p-stop-publish').show();
			$('#w2p-publish-progress').show();
			processedCount = 0;
            failedPostIds = []; // Reset on start
			updateProgress(0);
			processNext();
		});
	});

	$('#w2p-stop-publish').on('click', function() {
		isRunning = false;
        // Also stop Smart AUI if running
        if (window.W2P_SmartAUI_Progress && window.W2P_SmartAUI_Progress.isProcessing) {
             window.W2P_SmartAUI_Progress.isProcessing = false;
        }
		$(this).hide();
		$('#w2p-start-publish').show();
		$('.progress-text').text('Stopping after current post...');
	});

	$('#w2p-clean-logs').on('click', function() {
        let btn = $(this);
		w2p.confirm('<?php _e("Are you sure you want to clear all publish logs?", "wp-genius"); ?>', () => {
             w2p.loading(btn, true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'w2p_auto_publish_clean_logs',
                    nonce: nonce
                },
                success: function(response) {
                    w2p.loading(btn, false);
                    if (response.success) {
                        refreshStats();
                        w2p.toast('Logs Cleared', 'success');
                    } else {
                         w2p.toast('Error Clearing Logs', 'error');
                    }
                },
                error: function() {
                    w2p.loading(btn, false);
                    w2p.toast('Network Error', 'error');
                }
            });
        });
	});

	function processNext() {
		if (!isRunning) {
			$('.progress-text').text('Stopped.');
			return;
		}

        // 1. Get stats to find next post ID, excluding failed ones
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'w2p_auto_publish_get_stats',
                nonce: nonce,
                exclude: failedPostIds
            },
            success: function(response) {
                if (response.success && response.data.next_post) {
                    let nextPost = response.data.next_post;
                    let postId = nextPost.id;
                    let title = nextPost.title;

                    $('.progress-text').html('<strong>Preparing Post ' + postId + ':</strong> ' + title);

                    // 2. Decide if we use Smart AUI
                    if (window.W2P_SmartAUI_Progress && smartAuiNonce) {
                        fetchAndProcessImages(postId, title);
                    } else {
                        // Fallback to legacy
                        publishPost(postId, title);
                    }
                } else if (response.success && (response.data.draft_count === 0 || !response.data.next_post)) {
                     // If draft_count > 0 but no next_post, it means all remaining were excluded (failed)
                     finishAll();
                } else {
                     // Maybe finished or error
                     finishAll();
                }
            },
            error: function() {
                 $('.progress-text').text('Error fetching stats. Retrying...');
                 setTimeout(processNext, 2000);
            }
        });
	}

    function fetchAndProcessImages(postId, title) {
        // Fetch content
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'w2p_smart_aui_get_post_details',
                nonce: smartAuiNonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success && response.data) {
                    let content = response.data.post_content;
                    let images = W2P_SmartAUI_Progress.findExternalImages(content);

                    if (images.length > 0) {
                        $('.progress-text').html('<strong>Processing ' + postId + ':</strong> ' + title + ' (' + images.length + ' images)');
                        
                        // Force process ID regen
                        W2P_SmartAUI_Progress.processId = 'auto_pub_' + postId + '_' + Date.now();
                        W2P_SmartAUI_Progress.isProcessing = true; // Manually enable processing flag
                        
                        // Use the exposed method to show progress, but don't save
                        // Backend will handle both image processing and publishing
                        W2P_SmartAUI_Progress.processPostImages(postId, content, images, function(processedContent) {
                            // Send processed content to backend to skip redundant processing
                            publishPost(postId, title, processedContent);
                        });
                    } else {
                        publishPost(postId, title);
                    }
                } else {
                    publishPost(postId, title);
                }
            },
            error: function() {
                 publishPost(postId, title);
            }
        });
    }

    function publishPost(postId, title, processedContent) {
        if (!isRunning) return;

        $('.progress-text').html('<strong>Publishing Post ' + postId + ':</strong> ' + title);
        
        let postData = {
            action: 'w2p_auto_publish_process',
            nonce: nonce,
            exclude: failedPostIds
        };

        if (processedContent) {
            postData.post_content = processedContent;
            postData.skip_image_processing = 1;
        }
        
        $.ajax({
			url: ajaxurl,
			type: 'POST',
			data: postData,
			success: function(response) {
                if (response.success) {
                    if (response.data && response.data.finished) {
                        finishAll();
                        return;
                    }
                    processedCount++;
					let progress = (processedCount / totalToProcess) * 100;
					updateProgress(progress);
                    refreshStats();
                    // Clear preview area for next
                    $('#w2p-smart-aui-preview-area').empty().removeClass('grid-mode');
                    
                    processNext();
                } else {
                    $('.progress-text').html('<span style="color:red;">Error publishing ' + postId + ': ' + response.data + '. Skipping...</span>');
                    failedPostIds.push(postId);
                    processedCount++; // Still count as processed (or at least attempted) to update progress
                    
                    setTimeout(processNext, 1000);
                }
            },
            error: function() {
                $('.progress-text').html('<span style="color:red;">Connection error during publish of ' + postId + '. Skipping...</span>');
                failedPostIds.push(postId);
                processedCount++;
                
                setTimeout(processNext, 1000);
            }
        });
    }

    function finishAll() {
        $('.progress-text').text('All finished!');
        $('#w2p-stop-publish').hide();
        $('#w2p-start-publish').show().prop('disabled', true);
        if (window.w2p) {
             w2p.toast('All Finished!', 'success');
        }
        updateProgress(100);
        isRunning = false;
    }

	function updateProgress(percent) {
		$('.progress-bar-inner').css('width', percent + '%');
		$('.w2p-manual-publish-controls .w2p-progress-text').text(processedCount + '/' + totalToProcess);
	}

	// Handle stats update from external shared script
	$(document).on('w2p_auto_publish_stats_refreshed', function(e, data) {
		totalToProcess = data.draft_count + processedCount;
		$('.w2p-manual-publish-controls .w2p-progress-text').text(processedCount + '/' + totalToProcess);
		
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
			$('.progress-text').html('<strong>[ID: ' + data.next_post.id + ']</strong> ' + data.next_post.title);
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
