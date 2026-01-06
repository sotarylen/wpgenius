/**
 * WP Genius - Media Turbo Module
 */

(function ($) {
    'use strict';

    window.WPGenius = window.WPGenius || {};

    WPGenius.MediaTurbo = {
        allIds: [],
        previewItems: [],
        currentIndex: 0,
        isRunning: false,
        stopRequested: false,
        currentXHR: null,
        stats: { success: 0, skipped: 0, error: 0, affected: 0, deleted: 0 },

        init: function () {
            $('#w2p-scan-media').on('click', this.handleScan.bind(this));
            $('#w2p-start-bulk').on('click', this.startBulkConversion.bind(this));
            $('#w2p-stop-bulk').on('click', this.stopProcessing.bind(this));
            $('#w2p-reset-processed').on('click', this.resetProcessed.bind(this));

            // Handle scan mode toggle
            $('input[name="w2p_media_turbo_settings[scan_mode]"]').on('change', function () {
                if ($(this).val() === 'posts') {
                    $('#w2p-posts-scan-options').fadeIn();
                } else {
                    $('#w2p-posts-scan-options').fadeOut();
                }
            });
        },

        handleScan: function (e) {
            var $btn = $(e.currentTarget);
            var scanLimit = parseInt($('#w2p-scan-limit').val()) || 100;

            w2p.loading($btn, true);

            $.post(w2pMediaTurbo.ajax_url, {
                action: 'w2p_media_turbo_get_stats',
                nonce: w2pMediaTurbo.nonce,
                limit: scanLimit
            }, this.handleScanResponse.bind(this))
                .fail(this.handleScanError.bind(this, $btn));
        },

        handleScanResponse: function (response) {
            var $btn = $('#w2p-scan-media');

            if (response.success) {
                this.allIds = response.data.allIds || [];
                this.previewItems = response.data.allIds || []; // 显示所有找到的图片，不限制50条

                this.renderScanResults();

                // 在顶部显示当前捕获到的文件数量
                var statusText = 'Found ' + this.allIds.length + ' images ready for conversion.';
                $('#w2p-bulk-status-detailed').html('<strong style="color:#10a754;">' + statusText + '</strong>');

                if (this.allIds.length > 0) {
                    $('#w2p-start-bulk').fadeIn();
                    $('#w2p-scan-results-wrapper').fadeIn();
                    if (window.WPGenius.UI) {
                        WPGenius.UI.showFeedback($btn, 'Scan Complete', 'success');
                    }
                } else {
                    if (window.WPGenius.UI) {
                        WPGenius.UI.showFeedback($btn, 'No Images', 'warning');
                    } else {
                        WPGenius.UI.toast('No images found that need conversion.', 'warning');
                    }
                }
            } else {
                if (window.WPGenius.UI) {
                    WPGenius.UI.showFeedback($btn, 'Scan Failed', 'error');
                } else {
                    WPGenius.UI.toast('Scan failed: ' + (response.data || 'Unknown error'), 'error');
                }
            }
            w2p.loading($btn, false);
        },

        handleScanError: function ($btn) {
            if (window.WPGenius.UI) {
                WPGenius.UI.showFeedback($btn, 'Network Error', 'error');
            } else {
                w2p.toast('Network error or server-side failure during scan.', 'error');
                w2p.loading($btn, false);
            }
        },

        renderScanResults: function () {
            var $container = $('#w2p-scan-items');
            $container.empty();

            this.previewItems.forEach((item) => {
                $container.append(this.createRowHtml(item));
            });
        },

        createRowHtml: function (item, statusText = 'Pending', statusClass = 'pending', timeText = '') {
            var thumb = item.thumbUrl ?
                '<img src="' + item.thumbUrl + '" class="w2p-item-thumb" />' :
                '<div class="w2p-item-thumb" style="display:flex;align-items:center;justify-content:center;background:#eee;color:#999;font-size:10px;">No Img</div>';
            var fileName = item.fileName || 'ID: ' + item.id;
            var fileSize = item.fileSize ? ' <small>(' + item.fileSize + ' KB)</small>' : '';
            var association = item.parentUrl ?
                '<small>Post: <a href="' + item.parentUrl + '" target="_blank">' + item.parentTitle + '</a></small>' :
                '<small>Orphaned image</small>';

            return '<tr id="w2p-item-' + item.id + '">' +
                '<td>' + thumb + '</td>' +
                '<td><div class="w2p-item-info"><strong>' + fileName + fileSize + '</strong>' + association + '</div></td>' +
                '<td class="w2p-item-status"><span class="w2p-status-badge w2p-status-' + statusClass + '">' + statusText + '</span></td>' +
                '<td class="w2p-item-time"><small>' + timeText + '</small></td>' +
                '</tr>';
        },

        startBulkConversion: function (e) {
            if (this.isRunning) return;

            this.isRunning = true;
            this.stopRequested = false;

            $(e.currentTarget).prop('disabled', true).hide();
            $('#w2p-stop-bulk').fadeIn().prop('disabled', false).html('<span class="dashicons dashicons-no-alt" style="margin-top: 4px; margin-right: 4px;"></span> Stop');
            $('#w2p-scan-media').prop('disabled', true);
            $('#w2p-bulk-progress-wrapper').fadeIn();

            this.currentIndex = 0;
            this.stats = { success: 0, skipped: 0, error: 0, affected: 0, deleted: 0 };
            this.processNextBatch();
        },

        stopProcessing: function (e) {
            if (!this.isRunning) return;

            this.stopRequested = true;
            $(e.currentTarget).prop('disabled', true).text('Stopping...');

            if (this.currentXHR) {
                this.currentXHR.abort();
            }

            setTimeout(() => {
                if (this.isRunning) this.finishConversion('Stopped by user (Forced).');
            }, 2000);
        },

        processNextBatch: function () {
            if (this.stopRequested) {
                this.finishConversion('Stopped by user.');
                return;
            }

            if (this.currentIndex >= this.allIds.length) {
                this.finishConversion('Bulk Optimization Complete!');
                return;
            }

            var batchSize = parseInt($('#w2p-batch-size').val()) || 10;
            var chunk = this.allIds.slice(this.currentIndex, this.currentIndex + batchSize);

            $('#w2p-bulk-status-detailed').html(
                'Processing: ' + (this.currentIndex + 1) + ' to ' + Math.min(this.currentIndex + batchSize, this.allIds.length) + ' of ' + this.allIds.length + ' | ' +
                '<span style="color:#10a754;">Success: ' + this.stats.success + '</span> | ' +
                '<span style="color:#d94f1a;">Posts Updated: ' + this.stats.affected + '</span> | ' +
                '<span style="color:#0073aa;">Files Deleted: ' + this.stats.deleted + '</span>'
            );

            chunk.forEach((id, index) => {
                var $row = $('#w2p-item-' + id);
                if ($row.length === 0) {
                    var newItem = { id: id, fileName: 'Image ' + id };
                    $('#w2p-scan-items').prepend(this.createRowHtml(newItem, 'Processing...', 'processing'));
                    $row = $('#w2p-item-' + id);
                } else {
                    $row.find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-processing">Processing...</span>');
                }
                if (index === 0) {
                    $row[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });

            this.currentXHR = $.post(w2pMediaTurbo.ajax_url, {
                action: 'w2p_media_turbo_batch_convert',
                nonce: w2pMediaTurbo.nonce,
                ids: chunk
            }, this.handleBatchResponse.bind(this, chunk))
                .fail(this.handleBatchError.bind(this, chunk));
        },

        handleBatchResponse: function (chunk, response) {
            this.currentXHR = null;

            if (response.success && Array.isArray(response.data)) {
                response.data.forEach(res => {
                    var $itemRow = $('#w2p-item-' + res.id);
                    var $itemStatus = $itemRow.find('.w2p-item-status');
                    var $itemTime = $itemRow.find('.w2p-item-time');
                    var now = new Date();
                    var timeStr = now.getHours().toString().padStart(2, '0') + ':' +
                        now.getMinutes().toString().padStart(2, '0') + ':' +
                        now.getSeconds().toString().padStart(2, '0');

                    if (res.status === 'success') {
                        this.stats.success++;
                        this.stats.affected += res.affected || 0;
                        this.stats.deleted += res.deleted || 0;
                        var statusMsg = 'OK';
                        if (res.affected > 0) {
                            statusMsg += ' (' + res.affected + ' posts)';
                        }
                        if (res.deleted > 0) {
                            statusMsg += ' [' + res.deleted + ' files deleted]';
                        }
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-success">' + statusMsg + '</span>');
                        $itemTime.html('<small>' + timeStr + '</small>');

                        // 实时更新统计信息
                        this.updateOverallProgress();
                    } else if (res.status === 'skipped') {
                        this.stats.skipped++;
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-skipped">Skipped</span>');
                        $itemTime.html('<small>' + timeStr + '</small>');
                        this.updateOverallProgress();
                    } else {
                        this.stats.error++;
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-error">Fail</span>');
                        $itemTime.html('<small>' + timeStr + '</small>');
                        this.updateOverallProgress();
                    }
                });
            } else {
                this.stats.error += chunk.length;
                var now = new Date();
                var timeStr = now.getHours().toString().padStart(2, '0') + ':' +
                    now.getMinutes().toString().padStart(2, '0') + ':' +
                    now.getSeconds().toString().padStart(2, '0');
                chunk.forEach(id => {
                    $('#w2p-item-' + id).find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-error">Batch Error</span>');
                    $('#w2p-item-' + id).find('.w2p-item-time').html('<small>' + timeStr + '</small>');
                });
                this.updateOverallProgress();
            }

            this.currentIndex += chunk.length;

            if (!this.stopRequested) {
                setTimeout(this.processNextBatch.bind(this), 200);
            } else {
                this.finishConversion('Stopped by user.');
            }
        },

        handleBatchError: function (chunk, xhr, textStatus) {
            this.currentXHR = null;

            if (textStatus === 'abort') {
                this.finishConversion('Stopped by user.');
                return;
            }

            this.stats.error += chunk.length;
            chunk.forEach(id => {
                $('#w2p-item-' + id).find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-error">Batch Fail</span>');
            });

            this.currentIndex += chunk.length;
            this.updateOverallProgress();

            if (!this.stopRequested) {
                setTimeout(this.processNextBatch.bind(this), 200);
            } else {
                this.finishConversion('Stopped by user.');
            }
        },

        updateOverallProgress: function () {
            var processed = Math.min(this.currentIndex, this.allIds.length);
            var progress = (processed / this.allIds.length) * 100;
            $('#w2p-bulk-progress-bar').css('width', progress + '%');
            $('#w2p-bulk-status-detailed').html(
                'Processed: ' + processed + ' / ' + this.allIds.length + ' | ' +
                '<span style="color:#10a754;">Success: ' + this.stats.success + '</span> | ' +
                '<span style="color:#d94f1a;">Posts Updated: ' + this.stats.affected + '</span> | ' +
                '<span style="color:#0073aa;">Files Deleted: ' + this.stats.deleted + '</span>'
            );
        },

        finishConversion: function (message) {
            this.isRunning = false;
            this.currentXHR = null;
            $('#w2p-stop-bulk').hide();
            $('#w2p-start-bulk').show().html('<span class="dashicons dashicons-update" style="margin-top: 4px; margin-right: 4px;"></span> Optimize Again').prop('disabled', false); // Assuming icon for optimize again
            $('#w2p-scan-media').prop('disabled', false);
            // 去除弹窗提示，仅在状态栏显示
            $('#w2p-bulk-status-detailed').html('<strong style="color:#10a754;">' + message + '</strong>');
            if (window.WPGenius.UI) {
                WPGenius.UI.showFeedback($('#w2p-start-bulk'), 'Finished!', 'success');
            }
        },

        resetProcessed: function () {
            w2p.confirm('Are you sure you want to reset the processed posts list? This will allow you to re-process all posts.', () => {
                var $btn = $('#w2p-reset-processed');
                w2p.loading($btn, true);

                $.post(w2pMediaTurbo.ajax_url, {
                    action: 'w2p_media_turbo_reset_processed',
                    nonce: w2pMediaTurbo.nonce
                }, function (response) {
                    w2p.loading($btn, false);
                    if (response.success) {
                        w2p.toast('Processed posts list has been reset!', 'success');
                        setTimeout(() => { location.reload(); }, 1000);
                    } else {
                        w2p.toast('Failed to reset: ' + (response.data || 'Unknown error'), 'error');
                    }
                }).fail(function () {
                    w2p.loading($btn, false);
                    w2p.toast('Network error occurred', 'error');
                });
            });
        }
    };

    $(document).ready(function () {
        if (window.w2pMediaTurbo) WPGenius.MediaTurbo.init();
    });

})(jQuery);
