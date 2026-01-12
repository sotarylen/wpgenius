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
        isAutoMode: false,
        stopRequested: false,
        totalAutoProcessed: 0,
        currentXHR: null,
        stats: { success: 0, skipped: 0, error: 0, affected: 0, deleted: 0 },

        init: function () {
            $('#w2p-scan-media').on('click', this.handleScan.bind(this));
            $('#w2p-start-auto-batch').on('click', this.handleAutoBatch.bind(this));
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

            if (!this.isAutoMode) {
                w2p.loading($btn, true);
            }

            $.post(w2pMediaTurbo.ajax_url, {
                action: 'w2p_media_turbo_get_stats',
                nonce: w2pMediaTurbo.nonce,
                limit: this.isAutoMode ? 100 : scanLimit // Auto mode always pulls 100 at a time
            }, this.handleScanResponse.bind(this))
                .fail(this.handleScanError.bind(this, $btn));
        },

        handleAutoBatch: function (e) {
            if (this.isRunning) return;

            w2p.confirm('Full Auto Optimization will process your entire library in batches of 100 until finished. Continue?', () => {
                this.isAutoMode = true;
                this.totalAutoProcessed = 0;
                this.stats = { success: 0, skipped: 0, error: 0, affected: 0, deleted: 0 };

                $('#w2p-start-auto-batch').prop('disabled', true).addClass('w2p-btn-loading');
                $('#w2p-scan-media').prop('disabled', true);
                $('#w2p-reset-processed').prop('disabled', true);

                this.handleScan(e);
            });
        },

        handleScanResponse: function (response) {
            var $btn = $('#w2p-scan-media');

            if (response.success) {
                this.allIds = response.data.allIds || [];
                this.previewItems = response.data.preview || [];

                if (this.allIds.length > 0) {
                    if (this.isAutoMode) {
                        $('#w2p-bulk-status-detailed').html('<strong style="color:var(--w2p-color-info);">Starting next auto-batch of ' + this.allIds.length + '...</strong>');
                        this.renderScanResults();
                        $('#w2p-scan-results-wrapper').fadeIn();
                        // Start processing immediately
                        setTimeout(() => {
                            this.startBulkConversion({ currentTarget: $('#w2p-start-bulk') });
                        }, 500);
                    } else {
                        this.renderScanResults();
                        var statusText = 'Found ' + this.allIds.length + ' images ready for conversion.';
                        $('#w2p-bulk-status-detailed').html('<strong style="color:#10a754;">' + statusText + '</strong>');
                        $('#w2p-start-bulk').fadeIn();
                        $('#w2p-scan-results-wrapper').fadeIn();
                        if (window.WPGenius.UI) {
                            WPGenius.UI.showFeedback($btn, 'Scan Complete', 'success');
                        }
                    }
                } else {
                    if (this.isAutoMode) {
                        this.finishConversion('Full Auto Optimization Finished! No more images found.');
                    } else {
                        if (window.WPGenius.UI) {
                            WPGenius.UI.showFeedback($btn, 'No Images', 'warning');
                        } else {
                            w2p.toast('No images found that need conversion.', 'warning');
                        }
                    }
                }
            } else {
                this.isAutoMode = false; // Stop auto mode on error
                if (window.WPGenius.UI) {
                    WPGenius.UI.showFeedback($btn, 'Scan Failed', 'error');
                } else {
                    w2p.toast('Scan failed: ' + (response.data || 'Unknown error'), 'error');
                }
            }
            if (!this.isAutoMode) {
                w2p.loading($btn, false);
            }
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

            if (!this.isAutoMode) {
                $container.empty();
            }

            this.previewItems.forEach((item) => {
                if (item.html) {
                    if (this.isAutoMode) {
                        // Prepend for auto-mode log feel
                        if ($('#w2p-item-' + item.id).length === 0) {
                            $container.prepend(item.html);
                        }
                    } else {
                        $container.append(item.html);
                    }
                }
            });

            // Limit to 100 rows immediately
            var $rows = $container.find('tr');
            if ($rows.length > 100) {
                $rows.slice(100).remove();
            }
        },

        startBulkConversion: function (e) {
            if (this.isRunning && !this.isAutoMode) return;

            this.isRunning = true;
            this.stopRequested = false;

            if (!this.isAutoMode) {
                $(e.currentTarget).prop('disabled', true).hide();
                $('#w2p-scan-media').prop('disabled', true);
                $('#w2p-start-auto-batch').prop('disabled', true);
            }

            $('#w2p-stop-bulk').fadeIn().prop('disabled', false).html('<i class="fa-solid fa-xmark"></i> Stop');
            $('#w2p-bulk-progress-wrapper').fadeIn();

            this.currentIndex = 0;
            if (!this.isAutoMode) {
                this.stats = { success: 0, skipped: 0, error: 0, affected: 0, deleted: 0 };
            }
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
                'Success: ' + this.stats.success + ' | ' +
                'Posts Updated: ' + this.stats.affected + ' | ' +
                'Files Deleted: ' + this.stats.deleted
            );

            chunk.forEach((id, index) => {
                var $row = $('#w2p-item-' + id);
                if ($row.length > 0) {
                    $row.find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-processing">Processing...</span>');
                    if (index === 0) {
                        $row[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
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
                    } else if (res.status === 'skipped') {
                        this.stats.skipped++;
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-skipped">Skipped</span>');
                    } else {
                        this.stats.error++;
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-error">Fail</span>');
                    }
                });
            } else {
                this.stats.error += chunk.length;
                chunk.forEach(id => {
                    $('#w2p-item-' + id).find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-error">Batch Error</span>');
                });
            }

            this.currentIndex += chunk.length;
            this.updateOverallProgress();

            if (!this.stopRequested) {
                if (this.currentIndex >= this.allIds.length) {
                    setTimeout(() => {
                        if (this.isAutoMode) {
                            // Continue to next scan
                            $('#w2p-bulk-status-detailed').html('<strong style="color:var(--w2p-color-info);">Batch done. Scanning for more...</strong>');
                            this.handleScan({ currentTarget: $('#w2p-scan-media') });
                        } else {
                            this.finishConversion('Bulk Optimization Complete!');
                        }
                    }, 800);
                } else {
                    setTimeout(this.processNextBatch.bind(this), 200);
                }
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
                if (this.currentIndex >= this.allIds.length) {
                    setTimeout(() => {
                        if (this.isAutoMode) {
                            // Continue to next scan
                            $('#w2p-bulk-status-detailed').html('<strong style="color:var(--w2p-color-info);">Batch done (with errors). Scanning for more...</strong>');
                            this.handleScan({ currentTarget: $('#w2p-scan-media') });
                        } else {
                            this.finishConversion('Bulk Optimization Complete!');
                        }
                    }, 800);
                } else {
                    setTimeout(this.processNextBatch.bind(this), 200);
                }
            } else {
                this.finishConversion('Stopped by user.');
            }
        },

        updateOverallProgress: function () {
            var processed = Math.min(this.currentIndex, this.allIds.length);
            var progress = (processed / this.allIds.length) * 100;

            // In auto mode, the progress bar reflects the current batch
            $('#w2p-bulk-progress-bar').css('width', progress + '%');

            var statusHtml = 'Processed: ' + processed + ' / ' + this.allIds.length;
            if (this.isAutoMode) {
                statusHtml = 'Auto-Mode | ' + statusHtml;
            }

            $('#w2p-bulk-status-detailed').html(
                statusHtml + ' | ' +
                'Success: ' + this.stats.success + ' | ' +
                'Posts Updated: ' + this.stats.affected + ' | ' +
                'Files Deleted: ' + this.stats.deleted
            );

            // Manage log rows limit (100)
            var $rows = $('#w2p-scan-items tr');
            if ($rows.length > 100) {
                $rows.slice(100).remove(); // Keep only the top 100
            }
        },

        finishConversion: function (message) {
            this.isRunning = false;
            this.isAutoMode = false;
            this.currentXHR = null;
            // Ensure progress bar is full if we reached the end
            if (this.currentIndex >= this.allIds.length) {
                $('#w2p-bulk-progress-bar').css('width', '100%');
            }
            $('#w2p-stop-bulk').hide();
            $('#w2p-start-auto-batch').prop('disabled', false).removeClass('w2p-btn-loading');
            $('#w2p-start-bulk').show().html('<i class="fa-solid fa-arrow-right"></i> Optimize Again').prop('disabled', false);
            $('#w2p-scan-media').prop('disabled', false);
            $('#w2p-reset-processed').prop('disabled', false);
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
