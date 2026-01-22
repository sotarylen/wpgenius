/**
 * Media Processing UI - Real-time Progress Tracking
 */
(function ($) {
    'use strict';

    const MediaProcessingUI = {
        queue: [],
        processing: false,
        currentIndex: 0,
        stats: {
            total: 0,
            pending: 0,
            processing: 0,
            completed: 0,
            failed: 0
        },

        /**
         * Initialize the UI
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            const self = this;

            // Recheck Environment button (in Environment Check tab)
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
                    } else {
                        alert('Please scan for attachments first.');
                    }
                    return;
                }

                // Use native confirm for simplicity
                if (confirm('Start processing ' + self.queue.length + ' attachments? This may take a while.')) {
                    self.startProcessing();
                }
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
                        // Reload page to show updated environment status
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast('Environment check failed', 'error');
                        }
                        $button.prop('disabled', false);
                        $button.find('i').removeClass().addClass('fa-solid fa-rotate');
                    }
                },
                error: function () {
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('Request failed', 'error');
                    }
                    $button.prop('disabled', false);
                    $button.find('i').removeClass().addClass('fa-solid fa-rotate');
                }
            });
        },

        /**
         * Scan for pending attachments
         */
        scanAttachments: function () {
            const self = this;
            const $button = $('#w2p-get-stats');

            // Disable button
            $button.prop('disabled', true).find('i').removeClass().addClass('fa-solid fa-spinner fa-spin');

            // Show output
            $('#w2p-processing-output').show();
            $('#w2p-output-content').html('<div style="color: #fbbf24;">Scanning for pending attachments...</div>');

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

                        // Initialize stats
                        self.stats = {
                            total: self.queue.length,
                            pending: self.queue.length,
                            processing: 0,
                            completed: 0,
                            failed: 0
                        };

                        // Display the queue immediately
                        self.displayQueue();

                        $('#w2p-output-content').html(
                            '<div style="color: #10b981;">✓ Scan complete</div>' +
                            '<div style="margin-top: 8px;">Found ' + self.queue.length + ' attachments to process</div>'
                        );

                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast('Found ' + self.queue.length + ' attachments', 'success');
                        }
                    } else {
                        $('#w2p-output-content').html('<div style="color: #ef4444;">Error: ' + (response.data || 'Unknown error') + '</div>');
                    }
                },
                error: function (xhr, status, error) {
                    $('#w2p-output-content').html('<div style="color: #ef4444;">AJAX Error: ' + error + '</div>');
                },
                complete: function () {
                    $button.prop('disabled', false).find('i').removeClass().addClass('fa-solid fa-chart-bar');
                }
            });
        },

        /**
         * Display the attachment queue
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
         * Create a row for an attachment
         */
        createAttachmentRow: function (attachment, index) {
            const thumb = attachment.thumb_url ?
                '<img src="' + attachment.thumb_url + '" style="width: 48px; height: 48px; object-fit: cover; border-radius: 4px;">' :
                '<div style="width: 48px; height: 48px; background: #e5e7eb; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #6b7280;">No Img</div>';

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
                    '<td style="padding: 12px;" class="progress-cell">' +
                    '<div style="font-size: 12px; color: #6b7280;">Waiting...</div>' +
                    '</td>'
                );
        },

        /**
         * Get status badge HTML
         */
        getStatusBadge: function (status) {
            const badges = {
                'PENDING': '<span style="display: inline-block; padding: 4px 12px; background: #e5e7eb; color: #374151; border-radius: 12px; font-size: 12px; font-weight: 500;">PENDING</span>',
                'PROCESSING': '<span style="display: inline-block; padding: 4px 12px; background: #dbeafe; color: #1e40af; border-radius: 12px; font-size: 12px; font-weight: 500;"><i class="fa-solid fa-spinner fa-spin"></i> PROCESSING</span>',
                'OFFLOADING': '<span style="display: inline-block; padding: 4px 12px; background: #fef3c7; color: #92400e; border-radius: 12px; font-size: 12px; font-weight: 500;"><i class="fa-solid fa-cloud-arrow-up"></i> OFFLOADING</span>',
                'DONE': '<span style="display: inline-block; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px; font-weight: 500;"><i class="fa-solid fa-check"></i> DONE</span>',
                'FAILED': '<span style="display: inline-block; padding: 4px 12px; background: #fee2e2; color: #991b1b; border-radius: 12px; font-size: 12px; font-weight: 500;"><i class="fa-solid fa-xmark"></i> FAILED</span>'
            };

            return badges[status] || badges['PENDING'];
        },

        /**
         * Update queue statistics
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
         * Start processing the queue
         */
        startProcessing: function () {
            const self = this;

            if (self.processing) {
                return;
            }

            self.processing = true;
            self.currentIndex = 0;
            self.stats = {
                total: self.queue.length,
                pending: self.queue.length,
                processing: 0,
                completed: 0,
                failed: 0
            };

            self.updateQueueStats();
            self.processNext();
        },

        /**
         * Process the next attachment in the queue
         */
        processNext: function () {
            const self = this;

            if (self.currentIndex >= self.queue.length) {
                self.processing = false;
                self.showCompletionMessage();
                return;
            }

            const attachment = self.queue[self.currentIndex];
            const $row = $('#attachment-row-' + self.currentIndex);

            // Update status to PROCESSING
            self.stats.pending--;
            self.stats.processing++;
            self.updateRowStatus($row, 'PROCESSING', 'Converting to WebP...');
            self.updateQueueStats();

            // Process the attachment
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'w2p_process_attachment',
                    nonce: w2pMediaTurbo.nonce,
                    attachment_id: attachment.id
                },
                success: function (response) {
                    if (response.success) {
                        const result = response.data;

                        // Update based on result
                        if (result.step === 'offloading') {
                            self.updateRowStatus($row, 'OFFLOADING', 'Uploading to Minio...');
                        } else if (result.step === 'done') {
                            self.stats.processing--;
                            self.stats.completed++;
                            self.updateRowStatus($row, 'DONE', result.message || 'Completed successfully');
                            self.updateQueueStats();

                            // Move to next
                            self.currentIndex++;
                            setTimeout(function () {
                                self.processNext();
                            }, 500);
                        }
                    } else {
                        self.stats.processing--;
                        self.stats.failed++;
                        self.updateRowStatus($row, 'FAILED', response.data || 'Unknown error');
                        self.updateQueueStats();

                        // Move to next
                        self.currentIndex++;
                        setTimeout(function () {
                            self.processNext();
                        }, 500);
                    }
                },
                error: function (xhr, status, error) {
                    self.stats.processing--;
                    self.stats.failed++;
                    self.updateRowStatus($row, 'FAILED', 'AJAX error: ' + error);
                    self.updateQueueStats();

                    // Move to next
                    self.currentIndex++;
                    setTimeout(function () {
                        self.processNext();
                    }, 500);
                }
            });
        },

        /**
         * Update row status
         */
        updateRowStatus: function ($row, status, message) {
            $row.find('.status-cell').html(this.getStatusBadge(status));
            $row.find('.progress-cell').html('<div style="font-size: 12px; color: #6b7280;">' + message + '</div>');
        },

        /**
         * Show completion message
         */
        showCompletionMessage: function () {
            const self = this;

            $('#w2p-output-content').html(
                '<div style="color: #10b981;">Processing complete!</div>' +
                '<div style="margin-top: 8px;">Completed: ' + self.stats.completed + ' | Failed: ' + self.stats.failed + '</div>'
            );

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast('Processing complete! Completed: ' + self.stats.completed + ', Failed: ' + self.stats.failed, 'success');
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        if (typeof w2pMediaTurbo !== 'undefined') {
            MediaProcessingUI.init();
        }
    });

})(jQuery);
