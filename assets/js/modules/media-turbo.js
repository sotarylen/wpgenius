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
        isAssociateMode: false,
        stopRequested: false,
        totalAutoProcessed: 0,
        currentXHR: null,
        stats: { success: 0, skipped: 0, error: 0, affected: 0, deleted: 0 },
        itemStatus: {}, // Store status for each ID: { id: { status: 'pending', text: 'Pending', class: 'pending' } }

        init: function () {
            $('#w2p-scan-media').on('click', this.handleScan.bind(this));
            $('#w2p-start-auto-batch').on('click', this.handleAutoBatch.bind(this));
            $('#w2p-start-bulk').on('click', this.startBulkConversion.bind(this));
            $('#w2p-start-associate').on('click', this.startAssociate.bind(this));
            $('#w2p-auto-associate').on('click', this.handleAutoAssociate.bind(this));
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

            var limit = scanLimit;
            var scanParams = {
                action: 'w2p_media_turbo_get_stats',
                nonce: w2pMediaTurbo.nonce
            };

            if (this.isAutoMode) {
                limit = this.isAssociateMode ? 500 : 100; // Auto Associate grabs 500
                if (this.isAssociateMode) {
                    scanParams.is_auto_associate = 1; // Flag to force hardcoded settings on backend
                }
            }
            scanParams.limit = limit;

            $.post(w2pMediaTurbo.ajax_url, scanParams, this.handleScanResponse.bind(this))
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

        handleAutoAssociate: function (e) {
            if (this.isRunning) return;

            w2p.confirm('Auto Associate will scan 500 items at a time and process them in batches of 100. Continue?', () => {
                this.isAutoMode = true;
                this.isAssociateMode = true;
                this.totalAutoProcessed = 0;
                this.stats = { success: 0, skipped: 0, error: 0, affected: 0, deleted: 0 };
                this.itemStatus = {};

                $('#w2p-auto-associate').prop('disabled', true).addClass('w2p-btn-loading');
                $('#w2p-scan-media').prop('disabled', true);
                $('#w2p-reset-processed').prop('disabled', true);
                $('#w2p-start-associate').prop('disabled', true);

                this.handleScan(e);
            });
        },

        handleScanResponse: function (response) {
            var $btn = $('#w2p-scan-media');

            if (response.success) {
                this.allIds = response.data.allIds || [];
                this.previewItems = response.data.preview || [];

                if (this.allIds.length > 0) {
                    // Initialize status for all new items
                    this.allIds.forEach(id => {
                        this.itemStatus[id] = { status: 'pending', text: 'Pending', class: 'pending', msg: '' };
                    });

                    if (this.isAutoMode) {
                        $('#w2p-bulk-status-detailed').html('<strong style="color:var(--w2p-color-info);">Starting next auto-batch of ' + this.allIds.length + '...</strong>');
                        this.renderScanResults();
                        $('#w2p-scan-results-wrapper').fadeIn();
                        // Start processing immediately
                        this.renderScanResults();
                        $('#w2p-scan-results-wrapper').fadeIn();
                        // Start processing immediately
                        setTimeout(() => {
                            if (this.isAssociateMode) {
                                this.startAssociate({ currentTarget: $('#w2p-start-associate') });
                            } else {
                                this.startBulkConversion({ currentTarget: $('#w2p-start-bulk') });
                            }
                        }, 500);
                    } else {
                        this.renderScanResults();
                        var statusText = 'Found ' + this.allIds.length + ' images ready for conversion.';
                        $('#w2p-bulk-status-detailed').html('<strong style="color:#10a754;">' + statusText + '</strong>');
                        $('#w2p-start-bulk').fadeIn();
                        $('#w2p-start-associate').fadeIn();
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
            // Initial render - just the first batch + buffer if in auto mode
            if (this.isAutoMode) {
                this.renderSlidingWindow(0, 100);
            } else {
                // Manual mode - render everything (limited to 100 by slice)
                this.renderWindow(0, 100);
            }
        },

        renderWindow: function (start, end) {
            var $container = $('#w2p-scan-items');
            $container.empty();

            // Safety check
            start = Math.max(0, start);
            end = Math.min(this.previewItems.length, end);

            // Find items in range by matching ID (previewItems might be subset or full set?)
            // Actually previewItems corresponds to IDs order usually.
            // But let's map by ID to be safe if order differs

            // Map preview items for fast lookup
            var previewMap = {};
            this.previewItems.forEach(item => previewMap[item.id] = item);

            var idsToRender = this.allIds.slice(start, end);

            idsToRender.forEach(id => {
                var item = previewMap[id];
                if (item && item.html) {
                    // We need to inject the current status!
                    var $html = $(item.html);
                    var status = this.itemStatus[id];
                    if (status) {
                        var badgeHtml = '<span class="w2p-status-badge w2p-status-' + status.class + '">' + (status.text || 'Pending') + '</span>';
                        if (status.msg) {
                            badgeHtml += ' <small>(' + status.msg + ')</small>';
                        }
                        $html.find('.w2p-item-status').html(badgeHtml);
                    }
                    $container.append($html);
                }
            });
        },

        renderSlidingWindow: function (currentIndex, batchSize) {
            // Show current batch + 10 before and 10 after
            var start = Math.max(0, currentIndex - 10);
            var end = Math.min(this.allIds.length, currentIndex + batchSize + 10);
            this.renderWindow(start, end);
        },

        startAssociate: function (e) {
            this.isAssociateMode = true;
            this.startBulkConversion(e);
        },

        startBulkConversion: function (e) {
            // Fix: Check if e.target exists (it might be a manual call)
            if (e && e.target && e.target.id !== 'w2p-start-associate') {
                this.isAssociateMode = false;
            }
            if (this.isRunning && !this.isAutoMode) return;

            this.isRunning = true;
            this.stopRequested = false;

            if (!this.isAutoMode) {
                $(e.currentTarget).prop('disabled', true).hide();
                // Hide the other button too
                if (this.isAssociateMode) {
                    $('#w2p-start-bulk').hide();
                } else {
                    $('#w2p-start-associate').hide();
                }
                $('#w2p-scan-media').prop('disabled', true);
                $('#w2p-start-auto-batch').prop('disabled', true);
            }

            $('#w2p-stop-bulk').fadeIn().prop('disabled', false).html('<i class="fa-solid fa-xmark"></i> Stop');
            $('#w2p-bulk-progress-wrapper').fadeIn();

            this.currentIndex = 0;
            this.itemStatus = {};
            this.allIds.forEach(id => {
                this.itemStatus[id] = { status: 'pending', text: 'Pending', class: 'pending', msg: '' };
            });

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
            if (this.isAutoMode && this.isAssociateMode) {
                batchSize = 100; // Restore to 100
            }

            // Render sliding window BEFORE processing
            this.renderSlidingWindow(this.currentIndex, batchSize);

            var chunk = this.allIds.slice(this.currentIndex, this.currentIndex + batchSize);

            $('#w2p-bulk-status-detailed').html(
                'Processing: ' + (this.currentIndex + 1) + ' to ' + Math.min(this.currentIndex + batchSize, this.allIds.length) + ' of ' + this.allIds.length + ' | ' +
                'Success: ' + this.stats.success + ' | ' +
                'Posts Updated: ' + this.stats.affected + ' | ' +
                'Files Deleted: ' + this.stats.deleted
            );

            chunk.forEach((id, index) => {
                // Update internal status
                this.itemStatus[id] = { status: 'processing', text: 'Processing...', class: 'processing' };

                var $row = $('#w2p-item-' + id);
                if ($row.length > 0) {
                    $row.find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-processing">Processing...</span>');
                    if (index === 0) {
                        try {
                            $row[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); // Use center to keep context
                        } catch (e) { }
                    }
                }
            });

            this.currentXHR = $.post(w2pMediaTurbo.ajax_url, {
                action: this.isAssociateMode ? 'w2p_media_turbo_associate' : 'w2p_media_turbo_batch_convert',
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

                        this.itemStatus[res.id] = { status: 'success', text: statusMsg, class: 'success' };
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-success">' + statusMsg + '</span>');
                    } else if (res.status === 'skipped') {
                        this.stats.skipped++;
                        this.itemStatus[res.id] = { status: 'skipped', text: 'Skipped', class: 'skipped' };
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-skipped">Skipped</span>');
                    } else {
                        this.stats.error++;
                        var resultMsg = res.message || 'Fail';
                        this.itemStatus[res.id] = { status: 'error', text: 'Fail', class: 'error', msg: resultMsg };
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-error" title="' + resultMsg + '">Fail</span>');
                    }
                });
            } else {
                this.stats.error += chunk.length;
                chunk.forEach(id => {
                    this.itemStatus[id] = { status: 'batch-error', text: 'Batch Error', class: 'error' };
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
                this.itemStatus[id] = { status: 'network-error', text: 'Net Error', class: 'error' };
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
            // No longer needed here as renderSlidingWindow handles it

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
            $('#w2p-stop-bulk').hide();
            $('#w2p-start-auto-batch').prop('disabled', false).removeClass('w2p-btn-loading');
            $('#w2p-auto-associate').prop('disabled', false).removeClass('w2p-btn-loading');
            $('#w2p-start-bulk').show().html('<i class="fa-solid fa-arrow-right"></i> Optimize Again').prop('disabled', false);
            $('#w2p-start-associate').show().prop('disabled', false);
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
