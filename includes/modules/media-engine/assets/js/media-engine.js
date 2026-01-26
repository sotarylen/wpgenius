/**
 * Media Engine - Batch Processing UI
 * Handles the frontend logic for the Media Processing Center
 */
(function ($) {
    'use strict';

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
            failed: 0
        },

        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
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
         * Scan attachments
         */
        scanAttachments: function () {
            const self = this;
            const $button = $('#w2p-get-stats');

            $button.prop('disabled', true).find('i').removeClass().addClass('fa-solid fa-spinner fa-spin');
            $('#w2p-processing-output').show();
            $('#w2p-output-content').html('<div style="color: #fbbf24;">Scanning...</div>');

            $.ajax({
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
                            failed: 0
                        };

                        self.displayQueue();

                        $('#w2p-output-content').html(
                            '<div style="color: #10b981;">✓ Found ' + self.queue.length + ' attachments</div>'
                        );

                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast('Found ' + self.queue.length + ' attachments', 'success');
                        }
                    }
                },
                complete: function () {
                    $button.prop('disabled', false).find('i').removeClass().addClass('fa-solid fa-chart-bar');
                }
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
                                self.updateRowStatus($row, 'FAILED', convertResult?.error || 'Unknown error');
                            }
                        });

                        self.updateQueueStats();

                        // Move to next batch
                        self.currentBatchIndex++;
                        if (!self.stopped) {
                            setTimeout(() => self.processNextBatch(), 500);
                        }
                    } else {
                        // Batch failed, mark all as failed
                        batch.forEach((item, idx) => {
                            const $row = $('#attachment-row-' + (startIndex + idx));
                            self.stats.processing--;
                            self.stats.failed++;
                            self.updateRowStatus($row, 'FAILED', 'Batch failed');
                        });
                        self.updateQueueStats();

                        // Continue anyway
                        self.currentBatchIndex++;
                        if (!self.stopped) {
                            setTimeout(() => self.processNextBatch(), 500);
                        }
                    }
                },
                error: function () {
                    // Mark all as failed
                    batch.forEach((item, idx) => {
                        const $row = $('#attachment-row-' + (startIndex + idx));
                        self.stats.processing--;
                        self.stats.failed++;
                        self.updateRowStatus($row, 'FAILED', 'Request failed');
                    });
                    self.updateQueueStats();

                    // Continue anyway
                    self.currentBatchIndex++;
                    if (!self.stopped) {
                        setTimeout(() => self.processNextBatch(), 1000);
                    }
                }
            });
        },

        /**
         * Stop processing
         */
        stopProcessing: function () {
            const self = this;
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
            $row.find('.status-cell').html(this.getStatusBadge(status, message));
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
                '<div style="margin-top: 8px;">Completed: ' + self.stats.completed + ' | Failed: ' + self.stats.failed + '</div>'
            );

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(message, self.stopped ? 'warning' : 'success');
            }
        }
    };

    // Initialize
    $(document).ready(function () {
        console.log('WP Genius Media Engine Loaded', { config: window.w2pMediaConfig, localized: typeof w2pMediaEngine });
        if (typeof w2pMediaEngine !== 'undefined') {
            MediaProcessingUI.init();
        } else {
            console.error('w2pMediaEngine is undefined');
        }
    });

})(jQuery);
