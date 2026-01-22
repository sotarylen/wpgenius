/**
 * Media Processing UI - Batch Processing Mode
 * 按步骤批量处理,避免并发压力
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

                if (confirm('Start batch processing ' + self.queue.length + ' attachments?')) {
                    self.startBatchProcessing();
                }
            });

            $('#w2p-start-parallel').on('click', function () {
                if (self.queue.length === 0) {
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('Please scan for attachments first.', 'warning');
                    }
                    return;
                }

                if (confirm('Start batch processing ' + self.queue.length + ' attachments with batch size ' + self.batchSize + '?')) {
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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'w2p_execute_wpcli',
                    nonce: w2pMediaTurbo.nonce,
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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'w2p_scan_attachments',
                    nonce: w2pMediaTurbo.nonce
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

            $list.show();
            self.updateQueueStats();
        },

        /**
         * Create row
         */
        createAttachmentRow: function (attachment, index) {
            const thumb = attachment.thumb_url ?
                '<img src="' + attachment.thumb_url + '" style="width: 48px; height: 48px; object-fit: cover; border-radius: 4px;">' :
                '<div style="width: 48px; height: 48px; background: #e5e7eb; border-radius: 4px;"></div>';

            const fileName = attachment.file_name || 'ID: ' + attachment.id;
            const fileSize = attachment.file_size ? '(' + attachment.file_size + ' KB)' : '';

            return $('<tr>')
                .attr('id', 'attachment-row-' + index)
                .attr('data-id', attachment.id)
                .html(
                    '<td style="padding: 12px;">' + thumb + '</td>' +
                    '<td style="padding: 12px;">' +
                    '<div style="font-weight: 500;">' + fileName + '</div>' +
                    '<div style="font-size: 12px; color: #6b7280;">' + fileSize + '</div>' +
                    '</td>' +
                    '<td style="padding: 12px;" class="status-cell">' + this.getStatusBadge('PENDING') + '</td>' +
                    '<td style="padding: 12px;" class="progress-cell">Waiting...</td>'
                );
        },

        /**
         * Get status badge
         */
        getStatusBadge: function (status) {
            const badges = {
                'PENDING': '<span style="padding: 4px 12px; background: #e5e7eb; color: #374151; border-radius: 12px; font-size: 12px;">PENDING</span>',
                'PROCESSING': '<span style="padding: 4px 12px; background: #dbeafe; color: #1e40af; border-radius: 12px; font-size: 12px;"><i class="fa-solid fa-spinner fa-spin"></i> PROCESSING</span>',
                'DONE': '<span style="padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px;"><i class="fa-solid fa-check"></i> DONE</span>',
                'FAILED': '<span style="padding: 4px 12px; background: #fee2e2; color: #991b1b; border-radius: 12px; font-size: 12px;"><i class="fa-solid fa-xmark"></i> FAILED</span>'
            };
            return badges[status] || badges['PENDING'];
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
            $('#w2p-stop-conversion').show();

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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'w2p_process_batch',
                    nonce: w2pMediaTurbo.nonce,
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
            $row.find('.status-cell').html(this.getStatusBadge(status));
            $row.find('.progress-cell').html('<div style="font-size: 12px; color: #6b7280;">' + message + '</div>');
        },

        /**
         * Show completion
         */
        showCompletionMessage: function () {
            const self = this;

            $('#w2p-stop-conversion').hide();
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
        if (typeof w2pMediaTurbo !== 'undefined') {
            MediaProcessingUI.init();
        }
    });

})(jQuery);
