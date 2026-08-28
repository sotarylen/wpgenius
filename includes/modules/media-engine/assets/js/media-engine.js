/**
 * Media Engine - Batch Processing UI
 * Handles the frontend logic for the Media Processing Center
 */
(function ($) {
    'use strict';

    const { sprintf } = wp.i18n;

    const MediaProcessingUI = {
        queue: [],
        processing: false,
        stopped: false,
        batchSize: (window.w2pMediaConfig && window.w2pMediaConfig.batchSize) || 5,
        currentBatchIndex: 0,
        batches: [],
        stats: {
            total: 0,
            pending: 0,
            processing: 0,
            completed: 0,
            failed: 0,
            failedTotal: 0          // Cumulative failure count (display scope: failures accumulated across rounds)
        },
        autoMode: 'idle',          // 'idle' | 'running' | 'paused' | 'completed' | 'stopped'
        pauseRequested: false,     // Set true when Pause is clicked; consumed at the end of the current batch AJAX callback
        stopRequested: false,      // Set true when Stop is clicked in auto mode; consumed at the end of the current batch callback (graceful stop)
        strikeCounts: {},          // attachment id -> consecutive rounds it stayed in the pending queue
        abandonedIds: [],          // attachment ids given up on after too many rounds (never leave the queue)
        maxStrikes: 5,             // rounds after which a stuck attachment is abandoned instead of retried forever
        scanRetries: 0,            // consecutive scan-request failures in the current round
        batchRetries: 0,           // consecutive batch-request failures for the current batch (max 2, then marked failed)
        autoRound: 0,              // Current round counter

        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
            this.initLogViewer();
            // Ensure initial button state matches the idle state matrix (on page load)
            this.setAutoUI('idle');
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            const self = this;

            $('#w2p-recheck-environment').on('click', function () {
                self.recheckEnvironment($(this));
            });

            $('#w2p-get-stats').on('click', function () {
                self.scanAttachments();
            });

            $('#w2p-start-conversion').on('click', function () {
                if (self.queue.length === 0) {
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('Please scan for attachments first.', 'warning');
                    }
                    return;
                }

                if (typeof w2p !== 'undefined' && w2p.confirm) {
                    w2p.confirm(
                        'Start batch processing ' + self.queue.length + ' attachments (batch size: ' + self.batchSize + ')?',
                        function () {
                            self.startBatchProcessing();
                        }
                    );
                } else if (confirm('Start batch processing ' + self.queue.length + ' attachments (batch size: ' + self.batchSize + ')?')) {
                    self.startBatchProcessing();
                }
            });

            $('#w2p-stop-conversion').on('click', function () {
                self.stopProcessing();
            });

            // Full auto processing
            $('#w2p-start-auto').on('click', function () {
                self.startAutoProcessing();
            });

            // Pause / Resume
            $('#w2p-pause-auto').on('click', function () {
                if (self.autoMode === 'running' && !self.pauseRequested) {
                    self.pauseAutoProcessing();
                } else if (self.autoMode === 'paused') {
                    self.resumeAutoProcessing();
                }
            });

            // Retry failed items (clears the _w2p_media_failed marker so the scanner picks them up again)
            $('#w2p-retry-failed').on('click', function () {
                self.retryFailed($(this));
            });

            // Workflow Step Navigation Switcher
            $('.w2p-workflow-step-btn').on('click', function () {
                const step = parseInt($(this).data('step'), 10);
                $('.w2p-workflow-step-btn').removeClass('active');
                $(this).addClass('active');

                // Toggle Step Action Panels
                $('#w2p-step-1-panel').toggleClass('w2p-hidden', step !== 1).toggle(step === 1);
                $('#w2p-step-2-panel').toggleClass('w2p-hidden', step !== 2).toggle(step === 2);
                $('#w2p-step-3-panel').toggleClass('w2p-hidden', step !== 3).toggle(step === 3);

                // Toggle Result Tables
                $('#w2p-attachment-list').toggleClass('w2p-hidden', step !== 1).toggle(step === 1);
                $('#w2p-audit-results').toggleClass('w2p-hidden', step !== 2).toggle(step === 2);
                $('#w2p-fixer-results').toggleClass('w2p-hidden', step !== 3).toggle(step === 3);
            });
        },

        /**
         * Re-enable attachments that failed conversion (clears the _w2p_media_failed marker)
         */
        retryFailed: function ($button) {
            const self = this;

            const doRetry = function () {
                $button.prop('disabled', true).find('i').removeClass().addClass('fa-solid fa-spinner fa-spin');
                $.ajax({
                    url: w2pMediaEngine.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_media_retry_failed',
                        nonce: w2pMediaEngine.nonce,
                        attachment_ids: '[]'
                    },
                    success: function (response) {
                        if (response.success) {
                            const cleared = response.data.cleared || 0;
                            if (typeof w2p !== 'undefined' && w2p.toast) {
                                w2p.toast(cleared > 0
                                    ? sprintf(w2pMediaEngine.i18n.retryFailedDone, cleared)
                                    : w2pMediaEngine.i18n.retryFailedNone, cleared > 0 ? 'success' : 'info');
                            }
                            // Re-scan so the re-enabled items appear in the queue
                            self.scanAttachments();
                        } else {
                            if (typeof w2p !== 'undefined' && w2p.toast) {
                                w2p.toast(response.data && response.data.message ? response.data.message : w2pMediaEngine.i18n.unknownError, 'error');
                            }
                        }
                    },
                    error: function () {
                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast(w2pMediaEngine.i18n.ajaxFailed, 'error');
                        }
                    },
                    complete: function () {
                        $button.prop('disabled', false).find('i').removeClass().addClass('fa-solid fa-rotate-left');
                    }
                });
            };

            if (typeof w2p !== 'undefined' && w2p.confirm) {
                w2p.confirm(w2pMediaEngine.i18n.retryFailedConfirm.replace('%1$d', (self.stats && self.stats.failedCount) ? self.stats.failedCount : '0'), doRetry);
            } else {
                doRetry();
            }
        },

        /**
         * Recheck environment
         */
        recheckEnvironment: function ($button) {
            $button.prop('disabled', true);
            $button.find('i').removeClass().addClass('fa-solid fa-spinner fa-spin');

            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_execute_wpcli',
                    nonce: w2pMediaEngine.nonce,
                    command: 'wp media-engine check_env'
                },
                success: function (response) {
                    if (response.success) {
                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast('Environment check completed. Reloading...', 'success');
                        }
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        $button.prop('disabled', false);
                        $button.find('i').removeClass().addClass('fa-solid fa-rotate');
                    }
                }
            });
        },

        /**
         * Scan attachments (manual flow — behavior unchanged)
         */
        scanAttachments: function () {
            const self = this;
            const $button = $('#w2p-get-stats');

            $button.prop('disabled', true).find('i').removeClass().addClass('fa-solid fa-spinner fa-spin');
            $('#w2p-processing-output').show();
            $('#w2p-output-content').html('<div style="color: #fbbf24;">Scanning...</div>');

            self.fetchPendingAttachments(function (response) {
                if (response.success) {
                    self.displayQueue();

                    let html = '<div style="color: #10b981;">✓ Found ' + self.queue.length + ' attachments</div>';
                    if (response.data.failed_count > 0) {
                        html += '<div style="color: #f59e0b; margin-top: 6px;">⚠️ ' +
                            sprintf(w2pMediaEngine.i18n.failedSkippedInfo, response.data.failed_count) + '</div>';
                    }
                    $('#w2p-output-content').html(html);

                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('Found ' + self.queue.length + ' attachments', 'success');
                    }
                }
            }).always(function () {
                $button.prop('disabled', false).find('i').removeClass().addClass('fa-solid fa-chart-bar');
            });
        },

        /**
         * Display queue
         */
        displayQueue: function () {
            const self = this;
            const $tbody = $('#w2p-attachment-tbody');
            const $list = $('#w2p-attachment-list');

            $tbody.empty();

            if (self.queue.length === 0) {
                $list.hide();
                return;
            }

            self.queue.forEach(function (attachment, index) {
                const row = self.createAttachmentRow(attachment, index);
                $tbody.append(row);
            });

            // Fix: Remove hidden class explicitly
            $list.removeClass('w2p-hidden').show();
            self.updateQueueStats();
        },

        /**
         * Create row
         */
        createAttachmentRow: function (attachment, index) {
            const thumb = attachment.thumb_url ?
                '<img src="' + attachment.thumb_url + '" class="w2p-thumb-img">' :
                '<div class="w2p-thumb-placeholder"></div>';

            const fileName = attachment.file_name || 'ID: ' + attachment.id;
            const fileSize = attachment.file_size ? '<span class="w2p-file-size">' + attachment.file_size + '</span>' : '';

            let parentPost = '<div class="w2p-parent-info">Unattached</div>';
            if (attachment.parent_post) {
                const url = attachment.parent_url || '#';
                parentPost = '<div class="w2p-parent-info">' +
                    '<i class="fa-regular fa-file-lines"></i> ' +
                    '<a href="' + url + '" class="w2p-post-link" target="_blank" title="' + attachment.parent_post + '">' +
                    attachment.parent_post +
                    '</a></div>';
            }

            return $('<tr>')
                .attr('id', 'attachment-row-' + index)
                .attr('data-id', attachment.id)
                .html(
                    '<td class="w2p-cell-thumb">' + thumb + '</td>' +
                    '<td class="w2p-cell-name">' +
                    '<div class="w2p-filename w2p-file-meta">' + fileName + fileSize + '</div>' +
                    parentPost +
                    '</td>' +
                    '<td class="status-cell">' + this.getStatusBadge('PENDING') + '</td>'
                );
        },

        /**
         * Get status badge
         */
        getStatusBadge: function (status, message) {
            const badges = {
                'PENDING': '<span class="w2p-status-badge w2p-status-pending">PENDING</span>',
                'PROCESSING': '<span class="w2p-status-badge w2p-status-processing"><i class="fa-solid fa-spinner fa-spin"></i> PROCESSING</span>',
                'DONE': '<span class="w2p-status-badge w2p-status-success"><i class="fa-solid fa-check"></i> DONE</span>',
                'FAILED': '<span class="w2p-status-badge w2p-status-error"><i class="fa-solid fa-xmark"></i> FAILED</span>'
            };

            let html = badges[status] || badges['PENDING'];

            // Append error message if exists
            if (status === 'FAILED' && message) {
                html += '<div style="font-size: 11px; color: #ef4444; margin-top: 4px;">' + message + '</div>';
            }

            return html;
        },

        /**
         * Update stats
         */
        updateQueueStats: function () {
            const self = this;

            // Check if wp.template is available
            if (typeof wp !== 'undefined' && wp.template) {
                try {
                    const template = wp.template('w2p-media-queue-stats');
                    $('#w2p-queue-stats').html(template(self.stats));
                    return;
                } catch (e) {
                    console.error('Template rendering failed:', e);
                }
            }

            // Fallback to simple string if template fails or is missing
            $('#w2p-queue-stats').html(
                'Total: ' + self.stats.total + ' | ' +
                'Pending: ' + self.stats.pending + ' | ' +
                'Processing: ' + self.stats.processing + ' | ' +
                'Completed: ' + self.stats.completed + ' | ' +
                'Failed: ' + self.stats.failed
            );
        },

        /**
         * Start batch processing
         */
        startBatchProcessing: function () {
            const self = this;

            if (self.processing) return;

            // Reset auto-mode state when manual batch processing starts, so leftover completed/stopped
            // does not make stopProcessing take the auto branch (which sets neither stopRequested nor autoStop) and hard-stop fails
            self.autoMode = 'idle';
            self.processing = true;
            self.stopped = false;
            self.currentBatchIndex = 0;

            // Split into batches
            self.batches = [];
            for (let i = 0; i < self.queue.length; i += self.batchSize) {
                self.batches.push(self.queue.slice(i, i + self.batchSize));
            }

            // Show stop button
            $('#w2p-start-conversion').hide();
            $('#w2p-start-parallel').hide();
            $('#w2p-stop-conversion').removeClass('w2p-hidden').show();

            self.processNextBatch();
        },

        /**
         * Process next batch
         */
        processNextBatch: function () {
            const self = this;

            if (self.stopped || self.currentBatchIndex >= self.batches.length) {
                self.processing = false;
                self.showCompletionMessage();
                return;
            }

            const batch = self.batches[self.currentBatchIndex];
            const batchIds = batch.map(item => item.id);
            const startIndex = self.currentBatchIndex * self.batchSize;

            // Update output
            $('#w2p-output-content').html(
                '<div style="color: #3b82f6;">Processing batch ' + (self.currentBatchIndex + 1) + '/' + self.batches.length + '</div>' +
                '<div style="margin-top: 4px; font-size: 12px;">' + batchIds.length + ' images in this batch</div>'
            );

            // Update all rows in batch to PROCESSING
            batch.forEach((item, idx) => {
                const $row = $('#attachment-row-' + (startIndex + idx));
                self.stats.pending--;
                self.stats.processing++;
                self.updateRowStatus($row, 'PROCESSING', 'Batch processing...');
            });
            self.updateQueueStats();

            // Bring the first PROCESSING row into view at the top of the list.
            self.scrollToFirstProcessing();

            // Call batch API
            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_process_batch',
                    nonce: w2pMediaEngine.nonce,
                    attachment_ids: batchIds
                },
                success: function (response) {
                    if (response.success) {
                        const stats = response.data.stats;

                        // Update each row based on results
                        batch.forEach((item, idx) => {
                            const $row = $('#attachment-row-' + (startIndex + idx));
                            const convertResult = stats.convert[item.id];

                            self.stats.processing--;

                            if (convertResult && convertResult.success) {
                                self.stats.completed++;
                                self.updateRowStatus($row, 'DONE', 'Completed');
                            } else {
                                self.stats.failed++;
                                self.stats.failedTotal++;
                                self.updateRowStatus($row, 'FAILED', convertResult?.error || 'Unknown error');
                            }
                        });

                        self.updateQueueStats();

                        // Move to next batch (chained on the AJAX completion event; no setTimeout so
                        // background-tab timer throttling cannot stall the run)
                        self.currentBatchIndex++;
                        if (!self.stopped) {
                            self.processNextBatch();
                        }
                    } else {
                        // Batch failed, mark all as failed
                        batch.forEach((item, idx) => {
                            const $row = $('#attachment-row-' + (startIndex + idx));
                            self.stats.processing--;
                            self.stats.failed++;
                            self.stats.failedTotal++;
                            self.updateRowStatus($row, 'FAILED', 'Batch failed');
                        });
                        self.updateQueueStats();

                        // Continue anyway (chained on the AJAX completion event)
                        self.currentBatchIndex++;
                        if (!self.stopped) {
                            self.processNextBatch();
                        }
                    }
                },
                error: function () {
                    // Mark all as failed
                    batch.forEach((item, idx) => {
                        const $row = $('#attachment-row-' + (startIndex + idx));
                        self.stats.processing--;
                        self.stats.failed++;
                        self.stats.failedTotal++;
                        self.updateRowStatus($row, 'FAILED', 'Request failed');
                    });
                    self.updateQueueStats();

                    // Continue anyway (chained on the AJAX completion event)
                    self.currentBatchIndex++;
                    if (!self.stopped) {
                        self.processNextBatch();
                    }
                }
            });
        },

        /**
         * Stop processing
         * Manual mode (autoMode==='idle'): keep the existing hard-stop behavior
         * Auto mode (running/paused): graceful stop - set stopRequested, the whole flow ends after the current batch completes
         */
        stopProcessing: function () {
            const self = this;

            // Auto mode: set stopRequested; consumed at the end of the batch callback
            if (self.autoMode !== 'idle') {
                if (self.autoMode === 'running' || self.autoMode === 'paused') {
                    self.stopRequested = true;

                    $('#w2p-output-content').append(
                        '<div style="color: #f59e0b;">⛔ ' + w2pMediaEngine.i18n.stopRequested + '</div>'
                    );

                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast(w2pMediaEngine.i18n.stopRequested, 'warning');
                    }
                }

                // No batch in progress while paused; end directly
                if (self.autoMode === 'paused') {
                    self.autoStop(w2pMediaEngine.i18n.userStopped);
                }
                return;
            }

            // Manual mode: keep the existing hard-stop behavior
            self.stopped = true;
            self.processing = false;

            $('#w2p-stop-conversion').hide();
            $('#w2p-start-conversion').show();
            $('#w2p-start-parallel').show();

            $('#w2p-output-content').html(
                '<div style="color: #f59e0b;">⏸ Stopped by user</div>' +
                '<div style="margin-top: 8px;">Completed: ' + self.stats.completed + ' | Failed: ' + self.stats.failed + '</div>'
            );

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast('Processing stopped', 'warning');
            }
        },

        /**
         * Update row status
         */
        updateRowStatus: function ($row, status, message) {
            $row.attr('data-status', status);
            $row.find('.status-cell').html(this.getStatusBadge(status, message));
        },

        /**
         * Scroll the batch list so the first PROCESSING row sits at the top of the visible area
         * (just below the sticky table header). Called when a new batch starts.
         */
        scrollToFirstProcessing: function () {
            const $container = $('#w2p-attachment-list .w2p-log-container');
            const $first = $('#w2p-attachment-tbody tr[data-status="PROCESSING"]').first();
            if (!$container.length || !$first.length) {
                return;
            }

            const containerTop = $container.offset().top;
            const headerHeight = $container.find('thead').outerHeight() || 0;
            const target = Math.max(0, $first.offset().top - containerTop - headerHeight + $container.scrollTop());

            // Smooth scroll; stop(true) clears any in-flight animation so rapid batch switches don't pile up.
            $container.stop(true).animate({ scrollTop: target }, 300);
        },

        /**
         * Show completion
         */
        showCompletionMessage: function () {
            const self = this;

            $('#w2p-stop-conversion').addClass('w2p-hidden').hide();
            $('#w2p-start-conversion').show();
            $('#w2p-start-parallel').show();

            const message = self.stopped ? 'Stopped by user' : 'Processing complete!';

            $('#w2p-output-content').html(
                '<div style="color: ' + (self.stopped ? '#f59e0b' : '#10b981') + ';">' + (self.stopped ? '⏸' : '✓') + ' ' + message + '</div>' +
                '<div style="margin-top: 8px;">Completed: ' + self.stats.completed + ' | Failed: ' + self.stats.failedTotal + '</div>'
            );

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(message, self.stopped ? 'warning' : 'success');
            }
        },

        /* ======================================================================
         * Full auto processing mode (MediaEngine automation)
         * Auto loop: scan → batch convert → scan again → convert again, until everything is done.
         * Failure retry relies on re-scanning each round: images that failed to convert (mime unchanged) or Minio
         * (offload meta not written) naturally come back on the next scan; no need for the frontend to track failed IDs.
         * ====================================================================== */

        /**
         * Start full auto processing (entry point)
         */
        startAutoProcessing: function () {
            const self = this;

            if (self.autoMode === 'running' || self.autoMode === 'paused') return;

            // Auto mode cannot start while manual batch processing is running
            if (self.autoMode === 'idle' && self.processing) {
                if (typeof w2p !== 'undefined' && w2p.toast) {
                    w2p.toast(w2pMediaEngine.i18n.stopBatchFirst, 'warning');
                }
                return;
            }

            // Reset auto mode state
            self.autoMode = 'running';
            self.pauseRequested = false;
            self.stopRequested = false;
            self.strikeCounts = {};
            self.abandonedIds = [];
            self.scanRetries = 0;
            self.batchRetries = 0;
            self.autoRound = 0;
            self.currentBatchIndex = 0;
            self.batches = [];
            self.stopped = false;
            self.processing = true;

            self.setAutoUI('running');

            $('#w2p-processing-output').show();
            $('#w2p-output-content').html('<div style="color: #10b981;">▶ ' + w2pMediaEngine.i18n.autoStarted + '</div>');

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(w2pMediaEngine.i18n.autoStarted, 'success');
            }

            self.autoScanAndProcess();
        },

        /**
         * Fetch pending attachments (scan AJAX wrapper, shared by manual / auto modes)
         * Auto mode: completed and cumulative failed counts persist across rounds (for the "Completed X / Failed Y" display),
         * while this round's failed count resets each round (for no-progress detection)
         */
        fetchPendingAttachments: function (callback) {
            const self = this;
            const prevCompleted = self.stats.completed || 0;
            const prevFailedTotal = self.stats.failedTotal || 0;

            return $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_scan_attachments',
                    nonce: w2pMediaEngine.nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.queue = response.data.attachments || [];
                        self.stats = {
                            total: self.queue.length,
                            pending: self.queue.length,
                            processing: 0,
                            completed: 0,
                            failed: 0,
                            failedTotal: 0,
                            failedCount: response.data.failed_count || 0
                        };

                        // Auto mode: completed and cumulative failed counts persist across rounds; this round's failed count resets
                        if (self.autoMode === 'running') {
                            self.stats.completed = prevCompleted;
                            self.stats.failedTotal = prevFailedTotal;
                        }

                        if (typeof callback === 'function') {
                            callback(response);
                        }
                    }
                }
            });
        },

        /**
         * Auto-scan and process (entry point for each round)
         */
        autoScanAndProcess: function () {
            const self = this;

            if (self.autoMode !== 'running') return;

            self.autoRound++;

            $('#w2p-output-content').append(
                '<div style="color: #3b82f6;">🔄 ' + sprintf(w2pMediaEngine.i18n.roundScanning, self.autoRound) + '</div>'
            );

            self.fetchPendingAttachments(function (response) {
                if (!response.success) {
                    // Transient scan failures (network/DB hiccups) are retried; only give up after repeated failures.
                    if (self.scanRetries < 3) {
                        self.scanRetries++;
                        $('#w2p-output-content').append(
                            '<div style="color: #f59e0b;">⚠️ ' + sprintf(w2pMediaEngine.i18n.scanRetrying, self.scanRetries, 3) + '</div>'
                        );
                        setTimeout(function () { self.autoScanAndProcess(); }, 1000 * self.scanRetries);
                    } else {
                        self.autoStop(w2pMediaEngine.i18n.scanFailed);
                    }
                    return;
                }
                self.scanRetries = 0;

                // Abandon attachments that never leave the pending queue.
                // An attachment leaves the queue only when the backend marks it offloaded; anything that
                // stays for too many rounds (conversion or offload keeps failing) is dropped so the
                // overall run can finish instead of looping forever - but the run itself is never
                // stopped automatically.
                const present = {};
                self.queue.forEach(function (a) { present[a.id] = true; });
                Object.keys(self.strikeCounts).forEach(function (id) {
                    if (!present[id]) {
                        delete self.strikeCounts[id]; // Left the queue → reset its strike count.
                    }
                });
                self.queue = self.queue.filter(function (a) {
                    if (self.abandonedIds.indexOf(a.id) !== -1) {
                        return false;
                    }
                    const strikes = (self.strikeCounts[a.id] || 0) + 1;
                    self.strikeCounts[a.id] = strikes;
                    if (strikes >= self.maxStrikes) {
                        self.abandonedIds.push(a.id);
                        return false;
                    }
                    return true;
                });

                if (self.queue.length === 0) {
                    self.autoComplete();
                    return;
                }

                self.displayQueue();

                // Split into batches by batchSize
                self.batches = [];
                for (let i = 0; i < self.queue.length; i += self.batchSize) {
                    self.batches.push(self.queue.slice(i, i + self.batchSize));
                }
                self.currentBatchIndex = 0;

                $('#w2p-output-content').append(
                    '<div style="color: #10b981;">✓ ' + sprintf(w2pMediaEngine.i18n.roundFound, self.autoRound, self.queue.length, self.batches.length) + '</div>'
                );

                self.processNextAutoBatch();
            });
        },

        /**
         * Auto mode: process the next batch (parallel to the manual processNextBatch flow)
         */
        processNextAutoBatch: function () {
            const self = this;

            if (self.autoMode !== 'running') return;

            if (self.stopRequested) {
                self.autoStop(w2pMediaEngine.i18n.userStopped);
                return;
            }

            if (self.pauseRequested) {
                self.enterPaused();
                return;
            }

            if (self.currentBatchIndex >= self.batches.length) {
                self.onAutoRoundFinished();
                return;
            }

            const batch = self.batches[self.currentBatchIndex];
            const batchIds = batch.map(item => item.id);
            const startIndex = self.currentBatchIndex * self.batchSize;

            $('#w2p-output-content').append(
                '<div style="color: #3b82f6;">⚙️ ' + sprintf(w2pMediaEngine.i18n.roundBatch, self.autoRound, (self.currentBatchIndex + 1), self.batches.length, batchIds.length) + '</div>'
            );

            // Update the current batch row status to PROCESSING
            batch.forEach((item, idx) => {
                const $row = $('#attachment-row-' + (startIndex + idx));
                self.stats.pending--;
                self.stats.processing++;
                self.updateRowStatus($row, 'PROCESSING', 'Batch processing...');
            });
            self.updateQueueStats();

            // Bring the first PROCESSING row into view at the top of the list.
            self.scrollToFirstProcessing();

            // Call the batch processing API
            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_process_batch',
                    nonce: w2pMediaEngine.nonce,
                    attachment_ids: batchIds
                },
                success: function (response) {
                    // Any successful response (even a whole-batch business failure) proves the request
                    // reached the server, so the retry counter for this batch is no longer needed.
                    self.batchRetries = 0;

                    if (response.success) {
                        const stats = response.data.stats;

                        // Update status item by item and collect failed ids
                        batch.forEach((item, idx) => {
                            const $row = $('#attachment-row-' + (startIndex + idx));
                            const convertResult = stats.convert[item.id];

                            self.stats.processing--;

                            if (convertResult && convertResult.success) {
                                self.stats.completed++;
                                self.updateRowStatus($row, 'DONE', 'Completed');
                            } else {
                                self.stats.failed++;
                                self.stats.failedTotal++;
                                self.updateRowStatus($row, 'FAILED', convertResult?.error || 'Unknown error');
                            }
                        });
                    } else {
                        // Whole batch failed
                        batch.forEach((item, idx) => {
                            const $row = $('#attachment-row-' + (startIndex + idx));
                            self.stats.processing--;
                            self.stats.failed++;
                            self.stats.failedTotal++;
                            self.updateRowStatus($row, 'FAILED', 'Batch failed');
                        });
                    }
                    self.updateQueueStats();
                    self.afterAutoBatch();
                },
                error: function () {
                    // Network/gateway error (e.g. 502): the backend may have actually succeeded, so do
                    // not mark the batch failed immediately — retry the same batch instead.
                    if (self.batchRetries < 2) {
                        self.batchRetries++;
                        // Undo this attempt's batch-start stats (pending--/processing++ were applied
                        // once per item at the top of processNextAutoBatch). The retry re-enters
                        // processNextAutoBatch with the same currentBatchIndex, whose batch-start
                        // section re-applies them; without this restore, pending/processing would be
                        // double-counted and currentBatchIndex would be untouched → same batch retried.
                        batch.forEach(() => {
                            self.stats.pending++;
                            self.stats.processing--;
                        });
                        $('#w2p-output-content').append(
                            '<div style="color: #f59e0b;">⚠️ Batch request failed (retry ' + self.batchRetries + '/2)...</div>'
                        );
                        self.updateQueueStats();
                        setTimeout(() => self.processNextAutoBatch(), 10000);  // 10s 后重试当前批次，不推进 currentBatchIndex
                        return;
                    }
                    // Retries exhausted: mark failed and continue.
                    self.batchRetries = 0;
                    batch.forEach((item, idx) => {
                        const $row = $('#attachment-row-' + (startIndex + idx));
                        self.stats.processing--;
                        self.stats.failed++;
                        self.stats.failedTotal++;
                        self.updateRowStatus($row, 'FAILED', 'Request failed');
                    });
                    self.updateQueueStats();
                    self.afterAutoBatch();
                }
            });
        },

        /**
         * Common logic at the end of a batch callback (shared by success / error)
         * Replaces the manual flow of currentBatchIndex++ then continue directly
         */
        afterAutoBatch: function () {
            const self = this;

            self.currentBatchIndex++;
            self.updateQueueStats();

            if (self.autoMode !== 'running') return;      // Already stopped / completed

            if (self.stopRequested) {
                self.autoStop(w2pMediaEngine.i18n.userStopped);
                return;
            }

            if (self.pauseRequested) {
                self.enterPaused();                        // Current batch finished → pause
                return;
            }

            if (self.currentBatchIndex < self.batches.length) {
                // Chain directly on the AJAX completion event (no setTimeout):
                // browser throttling of timers on background tabs would otherwise stall the run.
                self.processNextAutoBatch();
            } else {
                self.onAutoRoundFinished();                // This round finished
            }
        },

        /**
         * Wrap-up after all batches of a round complete (no-progress detection / pause / next round)
         */
        onAutoRoundFinished: function () {
            const self = this;

            if (self.autoMode !== 'running') return;

            // The run continues until the queue is empty, the user stops it, or the server fails.
            // Attachments that can never leave the queue are abandoned by the strike mechanism in
            // autoScanAndProcess, so an endless loop is impossible - but an automatic stop is.
            if (self.stopRequested) {
                self.autoStop(w2pMediaEngine.i18n.userStopped);
                return;
            }

            if (self.pauseRequested) {
                self.enterPaused();
                return;
            }

            // Chain the next scan directly on the AJAX completion event (see afterAutoBatch).
            self.autoScanAndProcess();
        },

        /**
         * Request a pause (does not interrupt the current AJAX; takes effect after the current batch completes)
         */
        pauseAutoProcessing: function () {
            const self = this;

            if (self.autoMode !== 'running' || self.pauseRequested) return;

            self.pauseRequested = true;

            $('#w2p-output-content').append(
                '<div style="color: #f59e0b;">⏸ ' + w2pMediaEngine.i18n.pauseRequested + '</div>'
            );

            self.setPauseButtonLabel('resume');

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(w2pMediaEngine.i18n.pauseRequested, 'warning');
            }
        },

        /**
         * Enter the paused state
         */
        enterPaused: function () {
            const self = this;

            self.autoMode = 'paused';
            self.processing = false;
            self.setAutoUI('paused');

            $('#w2p-output-content').append(
                '<div style="color: #f59e0b;">⏸ ' + sprintf(w2pMediaEngine.i18n.paused, self.stats.completed, self.stats.failedTotal) + '</div>'
            );
        },

        /**
         * Resume processing (continue with the next batch / next round)
         */
        resumeAutoProcessing: function () {
            const self = this;

            if (self.autoMode !== 'paused') return;

            self.autoMode = 'running';
            self.pauseRequested = false;
            self.processing = true;
            self.setAutoUI('running');

            $('#w2p-output-content').append(
                '<div style="color: #10b981;">▶ ' + w2pMediaEngine.i18n.continuing + '</div>'
            );

            if (self.currentBatchIndex < self.batches.length) {
                setTimeout(() => self.processNextAutoBatch(), 300);
            } else {
                setTimeout(() => self.autoScanAndProcess(), 300);
            }
        },

        /**
         * Auto processing complete
         */
        autoComplete: function () {
            const self = this;

            self.autoMode = 'completed';
            self.processing = false;
            self.setAutoUI('idle');

            $('#w2p-output-content').append(
                '<div style="color: #10b981;">✓ ' + sprintf(w2pMediaEngine.i18n.autoComplete, self.stats.completed, self.stats.failedTotal) + '</div>'
            );

            if (self.stats.failedTotal > 0) {
                $('#w2p-output-content').append(
                    '<div style="color: #ef4444;">⚠️ ' + sprintf(w2pMediaEngine.i18n.filesFailed, self.stats.failedTotal) + '</div>'
                );
            }

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(sprintf(w2pMediaEngine.i18n.autoComplete, self.stats.completed, self.stats.failedTotal), self.stats.failedTotal > 0 ? 'warning' : 'success');
            }
        },

        /**
         * Stop auto processing
         */
        autoStop: function (reason) {
            const self = this;

            self.autoMode = 'stopped';
            self.processing = false;
            self.pauseRequested = false;
            self.stopRequested = false;
            self.setAutoUI('idle');

            $('#w2p-output-content').append(
                '<div style="color: #ef4444;">⛔ ' + sprintf(w2pMediaEngine.i18n.autoStopped, reason, self.stats.completed, self.stats.failedTotal) + '</div>'
            );

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(sprintf(w2pMediaEngine.i18n.autoStoppedToast, reason), 'warning');
            }
        },

        /**
         * Unify button states (control show/hide/disable/label of the 5 buttons by the state matrix)
         */
        setAutoUI: function (mode) {
            const self = this;
            const $startAuto = $('#w2p-start-auto');
            const $pauseAuto = $('#w2p-pause-auto');
            const $stop = $('#w2p-stop-conversion');
            const $start = $('#w2p-start-conversion');
            const $getStats = $('#w2p-get-stats');

            const isActive = (mode === 'running' || mode === 'paused');

            // #w2p-start-auto: greyed out with "Processing…" when running/paused; otherwise usable as "Full auto processing"
            if (isActive) {
                $startAuto.prop('disabled', true);
                self.setStartAutoLabel('running');
            } else {
                $startAuto.prop('disabled', false);
                self.setStartAutoLabel('idle');
            }
            $startAuto.show();

            // #w2p-pause-auto: shown when running/paused (Pause/Resume), hidden otherwise
            if (isActive) {
                $pauseAuto.removeClass('w2p-hidden').show();
                self.setPauseButtonLabel(mode === 'paused' ? 'resume' : 'pause');
            } else {
                $pauseAuto.addClass('w2p-hidden').hide();
            }

            // #w2p-stop-conversion: shown when running/paused (reused as the auto-mode stop button), hidden otherwise
            if (isActive) {
                $stop.removeClass('w2p-hidden').show();
            } else {
                $stop.addClass('w2p-hidden').hide();
            }

            // #w2p-start-conversion: hidden when running/paused, shown otherwise
            if (isActive) {
                $start.hide();
            } else {
                $start.show();
            }

            // #w2p-get-stats: disabled when running/paused
            $getStats.prop('disabled', isActive);
        },

        /**
         * Set the "Full Auto Processing" button label (keeps the <i> icon structure, only resets the text node)
         */
        setStartAutoLabel: function (mode) {
            const $btn = $('#w2p-start-auto');
            const label = (mode === 'running' || mode === 'paused') ? w2pMediaEngine.i18n.processingLabel : w2pMediaEngine.i18n.fullAutoLabel;
            const $icon = $btn.find('i');
            $btn.empty().append($icon).append(document.createTextNode(' ' + label));
        },

        /**
         * Set the "Pause/Resume" button icon and label (fa-pause↔fa-play, Pause↔Resume)
         */
        setPauseButtonLabel: function (state) {
            const $btn = $('#w2p-pause-auto');
            const isResume = (state === 'resume');
            const $icon = $btn.find('i');
            $icon.removeClass().addClass('fa-solid ' + (isResume ? 'fa-play' : 'fa-pause'));
            $btn.empty().append($icon).append(document.createTextNode(' ' + (isResume ? w2pMediaEngine.i18n.resumeLabel : w2pMediaEngine.i18n.pauseLabel)));
        },

        /* ======================================================================
         * Log Viewer Modal
         * ====================================================================== */

        logPollingTimer: null,

        /**
         * Initialize log viewer event bindings
         */
        initLogViewer: function () {
            const self = this;

            $('#w2p-view-log, .w2p-shared-view-log-btn').on('click', function () {
                self.openLogModal();
            });

            $('#w2p-close-log, #w2p-modal-close-log').on('click', function () {
                self.closeLogModal();
            });

            $('#w2p-log-modal').on('click', function (e) {
                if ($(e.target).is('#w2p-log-modal')) {
                    self.closeLogModal();
                }
            });

            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $('#w2p-log-modal').hasClass('active')) {
                    self.closeLogModal();
                }
            });

            $('#w2p-refresh-log').on('click', function () {
                self.fetchLog();
            });

            $('#w2p-clear-log').on('click', function () {
                self.clearLog();
            });
        },

        /**
         * Open the log modal
         */
        openLogModal: function () {
            const self = this;

            $('#w2p-log-modal').addClass('active');
            self.fetchLog();
            self.logPollingTimer = setInterval(function () {
                self.fetchLog();
            }, 3000);
        },

        /**
         * Close the log modal
         */
        closeLogModal: function () {
            if (this.logPollingTimer) {
                clearInterval(this.logPollingTimer);
                this.logPollingTimer = null;
            }
            $('#w2p-log-modal').removeClass('active');
        },

        /**
         * Fetch the log tail content
         */
        fetchLog: function () {
            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_get_conversion_log',
                    nonce: w2pMediaEngine.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const $content = $('#w2p-log-content');
                        const wasAtBottom = $content[0].scrollHeight - $content.scrollTop() - $content.outerHeight() < 60;

                        $content.text(response.data.lines || w2pMediaEngine.i18n.log_empty);

                        $('#w2p-log-size').text(
                            response.data.size_display + ' / ' + (response.data.max_bytes / 1048576) + ' MB'
                        );

                        if (wasAtBottom) {
                            $content.scrollTop($content[0].scrollHeight);
                        }
                    }
                }
            });
        },

        /**
         * Clear the log (with confirmation)
         */
        clearLog: function () {
            const doClear = function () {
                $.ajax({
                    url: w2pMediaEngine.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_clear_conversion_log',
                        nonce: w2pMediaEngine.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            $('#w2p-log-content').text(w2pMediaEngine.i18n.log_cleared);
                            $('#w2p-log-size').text(
                                response.data.size_display + ' / 5.00 MB'
                            );
                            if (typeof w2p !== 'undefined' && w2p.toast) {
                                w2p.toast(w2pMediaEngine.i18n.log_cleared, 'success');
                            }
                        }
                    }
                });
            };

            if (typeof w2p !== 'undefined' && w2p.confirm) {
                w2p.confirm(w2pMediaEngine.i18n.clear_confirm, doClear);
            } else if (confirm(w2pMediaEngine.i18n.clear_confirm)) {
                doClear();
            }
        }
    };

    // Initialize
    $(document).ready(function () {
        if (typeof w2pMediaEngine !== 'undefined') {
            MediaProcessingUI.init();
        } else {
            console.error('w2pMediaEngine is undefined');
        }
    });

})(jQuery);
