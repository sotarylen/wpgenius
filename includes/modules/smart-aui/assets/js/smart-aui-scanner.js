/**
 * Smart AUI — External Media Scanner & Batch Grabber UI
 *
 * Handles AJAX interactions for scanning posts containing external media,
 * manual batch grabbing with native multi-threaded progress UI,
 * and full-auto continuous scanning and downloading.
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */
(function ($) {
    'use strict';

    var SmartAuiScannerUI = {
        scanning: false,
        processing: false,
        autoMode: 'idle', // 'idle' | 'running' | 'paused' | 'stopped' | 'completed'
        pauseRequested: false,
        stopRequested: false,
        batchSize: 100,
        scanLastId: 0,
        autoLastId: 0,
        posts: [],
        runtimeLogs: [],
        stats: {
            scannedPosts: 0,
            pendingPosts: 0,
            externalUrls: 0,
            modifiedPosts: 0,
            failedCount: 0
        },

        /**
         * Initialize Scanner UI
         */
        init: function () {
            var self = this;
            if (typeof w2pSmartAuiScanner !== 'undefined' && w2pSmartAuiScanner.defaultLimit) {
                self.batchSize = parseInt(w2pSmartAuiScanner.defaultLimit, 10) || 100;
            }
            this.bindEvents();
        },

        /**
         * Bind UI Events using event delegation
         */
        bindEvents: function () {
            var self = this;

            // Single Scan button
            $(document).on('click', '#w2p-aui-scanner-scan', function (e) {
                e.preventDefault();
                self.startScan();
            });

            // Grab selected button (triggers native Progress Modal)
            $(document).on('click', '#w2p-aui-scanner-process-selected', function (e) {
                e.preventDefault();
                self.processSelected();
            });

            // Full auto scan & grab button
            $(document).on('click', '#w2p-aui-scanner-start-auto', function (e) {
                e.preventDefault();
                self.startAutoProcess();
            });

            // Pause auto button
            $(document).on('click', '#w2p-aui-scanner-pause-auto', function (e) {
                e.preventDefault();
                if (self.autoMode === 'running' && !self.pauseRequested) {
                    self.pauseRequested = true;
                    $('#w2p-aui-scanner-pause-auto').prop('disabled', true);
                    self.showToast(w2pSmartAuiScanner.i18n.pauseRequested, 'info');
                } else if (self.autoMode === 'paused') {
                    self.resumeAutoProcess();
                }
            });

            // Stop button
            $(document).on('click', '#w2p-aui-scanner-stop', function (e) {
                e.preventDefault();
                self.stopRequested = true;
                if (window.W2P_SmartAUI_Progress && window.W2P_SmartAUI_Progress.isProcessing) {
                    window.W2P_SmartAUI_Progress.cancel();
                }
                self.showToast(w2pSmartAuiScanner.i18n.stopRequested, 'warning');
            });

            // Table check-all
            $(document).on('change', '#w2p-aui-check-all', function () {
                $('.w2p-aui-row-check').prop('checked', $(this).is(':checked'));
                self.updateActionButtons();
            });

            $(document).on('change', '.w2p-aui-row-check', function () {
                self.updateActionButtons();
            });

            // Log modal interactions
            $(document).on('click', '#w2p-aui-scanner-view-log', function (e) {
                e.preventDefault();
                self.openLogModal();
            });

            $(document).on('click', '#w2p-aui-close-log, #w2p-aui-modal-close-log', function (e) {
                e.preventDefault();
                self.closeLogModal();
            });

            $(document).on('click', '#w2p-aui-log-modal', function (e) {
                if ($(e.target).is('#w2p-aui-log-modal')) {
                    self.closeLogModal();
                }
            });

            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $('#w2p-aui-log-modal').hasClass('active')) {
                    self.closeLogModal();
                }
            });

            $(document).on('click', '#w2p-aui-refresh-log', function (e) {
                e.preventDefault();
                self.loadLogs();
            });

            $(document).on('click', '#w2p-aui-clear-log', function (e) {
                e.preventDefault();
                self.clearLogs();
            });
        },

        /**
         * Update stats cards in UI
         */
        updateStatsDisplay: function () {
            var self = this;
            $('#w2p-aui-scanner-summary [data-stat="scanned_posts"]').text(self.stats.scannedPosts);
            $('#w2p-aui-scanner-summary [data-stat="pending_posts"]').text(self.stats.pendingPosts);
            $('#w2p-aui-scanner-summary [data-stat="external_urls"]').text(self.stats.externalUrls);
            $('#w2p-aui-scanner-summary [data-stat="modified_posts"]').text(self.stats.modifiedPosts);
            $('#w2p-aui-scanner-summary [data-stat="failed_count"]').text(self.stats.failedCount);
        },

        /**
         * Update Action Buttons state
         */
        updateActionButtons: function () {
            var checkedCount = $('.w2p-aui-row-check:checked').length;
            var hasChecked = checkedCount > 0;
            $('#w2p-aui-scanner-process-selected').toggleClass('w2p-hidden', !hasChecked).toggle(hasChecked);
        },

        /**
         * Start Single Manual Scan
         */
        startScan: function () {
            var self = this;
            if (self.scanning || self.processing || self.autoMode === 'running') return;

            self.scanning = true;
            self.stopRequested = false;
            self.scanLastId = 0;
            self.posts = [];
            self.stats.scannedPosts = 0;
            self.stats.pendingPosts = 0;
            self.stats.externalUrls = 0;
            self.stats.modifiedPosts = 0;
            self.stats.failedCount = 0;

            $('#w2p-aui-scanner-scan').prop('disabled', true);
            $('#w2p-aui-scanner-stop').removeClass('w2p-hidden').show();
            $('#w2p-aui-scanner-progress').removeClass('w2p-hidden').show();
            $('#w2p-aui-scanner-summary').removeClass('w2p-hidden').show();
            $('#w2p-aui-scanner-terminal').removeClass('w2p-hidden').show();
            $('#w2p-aui-scanner-results').addClass('w2p-hidden').hide();
            $('#w2p-aui-scanner-tbody').empty();
            self.updateStatsDisplay();

            self.logTerminal(w2pSmartAuiScanner.i18n.scanStarted || 'Starting scan...');
            self.scanBatch();
        },

        /**
         * Execute one scan request
         */
        scanBatch: function () {
            var self = this;

            if (self.stopRequested) {
                self.finishScan(true);
                return;
            }

            $('#w2p-aui-scanner-progress-text').text(
                (w2pSmartAuiScanner.i18n.scanning || 'Scanning...') + ' (' + self.stats.scannedPosts + ' ' + (w2pSmartAuiScanner.i18n.postsLabel || 'posts') + ')'
            );

            $.ajax({
                url: w2pSmartAuiScanner.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_smart_aui_scanner_scan',
                    nonce: w2pSmartAuiScanner.nonce,
                    limit: self.batchSize,
                    last_id: self.scanLastId
                },
                success: function (response) {
                    if (!response || !response.success) {
                        self.finishScan(false, response && response.data ? response.data : w2pSmartAuiScanner.i18n.scanFailed);
                        return;
                    }

                    var data = response.data || {};
                    var items = data.posts || [];
                    self.scanLastId = data.last_id || 0;
                    self.stats.scannedPosts += (data.scanned_count || 0);

                    if (items.length > 0) {
                        self.posts = self.posts.concat(items);
                        self.stats.pendingPosts += items.length;
                        items.forEach(function (p) {
                            self.stats.externalUrls += (p.external_count || 0);
                        });
                        self.renderRows(items);
                    }

                    self.updateStatsDisplay();

                    self.logTerminal(
                        '✓ ' + (w2pSmartAuiScanner.i18n.scannedBatchMsg || 'Batch scanned') + ': ' +
                        (data.scanned_count || 0) + ' ' + (w2pSmartAuiScanner.i18n.postsLabel || 'posts') +
                        ', ' + items.length + ' ' + (w2pSmartAuiScanner.i18n.withExternalLabel || 'with external media') + '.'
                    );

                    self.finishScan(false);
                },
                error: function () {
                    self.finishScan(false, w2pSmartAuiScanner.i18n.networkError);
                }
            });
        },

        /**
         * Finish manual scan
         */
        finishScan: function (stopped, errorMsg) {
            var self = this;
            self.scanning = false;

            $('#w2p-aui-scanner-scan').prop('disabled', false);
            $('#w2p-aui-scanner-stop').addClass('w2p-hidden').hide();
            $('#w2p-aui-scanner-progress').addClass('w2p-hidden').hide();

            if (errorMsg) {
                self.showToast(errorMsg, 'error');
                self.logTerminal('✗ ' + errorMsg);
                return;
            }

            $('#w2p-aui-scanner-summary').removeClass('w2p-hidden').show();
            self.updateStatsDisplay();

            if (self.posts.length > 0) {
                $('#w2p-aui-scanner-results').removeClass('w2p-hidden').show();
            } else {
                $('#w2p-aui-scanner-results').addClass('w2p-hidden').hide();
            }

            self.updateActionButtons();

            var summaryMsg = (w2pSmartAuiScanner.i18n.scanFoundMsg || 'Scan complete: found %d posts with external media')
                .replace('%d', self.posts.length)
                .replace('%u', self.stats.externalUrls);

            self.logTerminal('=== ' + summaryMsg + ' ===');
            self.showToast(summaryMsg, 'success');
        },

        /**
         * Render rows in the scan results table
         */
        renderRows: function (items) {
            var $tbody = $('#w2p-aui-scanner-tbody');

            items.forEach(function (item) {
                // If row already exists, skip
                if ($('tr[data-post-id="' + item.id + '"]').length) {
                    return;
                }

                var $tr = $('<tr>').attr('data-post-id', item.id);

                // 1. Checkbox
                var $cbTd = $('<td>');
                $('<input type="checkbox">')
                    .addClass('w2p-aui-row-check')
                    .attr('data-id', item.id)
                    .prop('checked', true)
                    .appendTo($cbTd);
                $tr.append($cbTd);

                // 2. Post ID
                $tr.append($('<td>').text(item.id));

                // 3. Title + Edit Link + Post Type Badge
                var $titleTd = $('<td>');
                $('<a>')
                    .attr('href', item.edit_url)
                    .attr('target', '_blank')
                    .text(item.title)
                    .appendTo($titleTd);

                if (item.type) {
                    $('<span>').addClass('w2p-badge w2p-ml-xs').text(item.type).appendTo($titleTd);
                }
                $tr.append($titleTd);

                // 4. External media count
                $tr.append($('<td>').text(item.external_count + ' ' + (w2pSmartAuiScanner.i18n.itemsLabel || 'item(s)')));

                // 5. Sample URLs preview
                var $prevTd = $('<td>');
                var $urlList = $('<div>').addClass('w2p-scanner-url-list');
                (item.sample_urls || []).forEach(function (u) {
                    $('<div>').addClass('w2p-scanner-preview-item').text(u).appendTo($urlList);
                });
                if (item.external_count > 3) {
                    $('<div>').addClass('w2p-text-muted w2p-text-xs w2p-mt-xs')
                        .text('+ ' + (item.external_count - 3) + ' ' + (w2pSmartAuiScanner.i18n.moreLabel || 'more...'))
                        .appendTo($urlList);
                }
                $urlList.appendTo($prevTd);
                $tr.append($prevTd);

                // 6. Status badge
                var $statusTd = $('<td>').attr('data-status-td', item.id);
                $('<span>').addClass('w2p-status-badge w2p-status-warning')
                    .text(w2pSmartAuiScanner.i18n.pendingLabel || 'Pending')
                    .appendTo($statusTd);
                $tr.append($statusTd);

                $tbody.append($tr);
            });
        },

        /**
         * Process Selected Posts from Table using native multi-threaded progress modal
         */
        processSelected: function () {
            var self = this;
            var postIds = [];
            $('.w2p-aui-row-check:checked').each(function () {
                postIds.push(parseInt($(this).attr('data-id'), 10));
            });

            if (postIds.length === 0) return;

            var doProcess = function () {
                self.processing = true;
                $('#w2p-aui-scanner-process-selected').prop('disabled', true);
                $('#w2p-aui-scanner-terminal').removeClass('w2p-hidden').show();
                self.logTerminal((w2pSmartAuiScanner.i18n.processingSelected || 'Starting grab for %d post(s)...').replace('%d', postIds.length));

                // Mark selected rows as processing
                postIds.forEach(function (id) {
                    var $statusTd = $('[data-status-td="' + id + '"]');
                    $statusTd.empty().append(
                        $('<span>').addClass('w2p-status-badge w2p-status-info')
                            .text(w2pSmartAuiScanner.i18n.processingLabel || 'Processing...')
                    );
                });

                // Call Smart AUI's native multi-threaded Progress UI Modal
                if (window.W2P_SmartAUI_Progress && typeof window.W2P_SmartAUI_Progress.startBulkProcessing === 'function') {
                    window.W2P_SmartAUI_Progress.startBulkProcessing(
                        postIds,
                        function (completedSuccessfully) {
                            // All complete callback
                            self.processing = false;
                            $('#w2p-aui-scanner-process-selected').prop('disabled', false);
                            self.updateActionButtons();
                            self.updateStatsDisplay();

                            var msg = (w2pSmartAuiScanner.i18n.batchDoneMsg || 'Batch complete: %d post(s) updated.')
                                .replace('%d', self.stats.modifiedPosts);

                            self.logTerminal('✓ ' + msg);
                            self.showToast(msg, 'success');
                        },
                        function (postId, success) {
                            // Single post complete callback
                            var $statusTd = $('[data-status-td="' + postId + '"]');
                            var $row = $('tr[data-post-id="' + postId + '"]');
                            $row.find('.w2p-aui-row-check').prop('checked', false);

                            if (success) {
                                self.stats.modifiedPosts++;
                                $statusTd.empty().append(
                                    $('<span>').addClass('w2p-status-badge w2p-status-success')
                                        .text(w2pSmartAuiScanner.i18n.completedLabel || 'Completed')
                                );
                            } else {
                                $statusTd.empty().append(
                                    $('<span>').addClass('w2p-status-badge w2p-status-secondary')
                                        .text(w2pSmartAuiScanner.i18n.completedLabel || 'Completed')
                                );
                            }
                            self.updateStatsDisplay();
                        }
                    );
                } else {
                    // Fallback to server-side batch if modal UI is not available
                    $.ajax({
                        url: w2pSmartAuiScanner.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'w2p_smart_aui_scanner_process_batch',
                            nonce: w2pSmartAuiScanner.nonce,
                            post_ids: JSON.stringify(postIds)
                        },
                        success: function (response) {
                            self.processing = false;
                            $('#w2p-aui-scanner-process-selected').prop('disabled', false);

                            if (response && response.success && response.data) {
                                var d = response.data;
                                self.stats.modifiedPosts += (d.modified_posts || 0);
                                self.updateStatsDisplay();

                                (d.details || []).forEach(function (item) {
                                    var $statusTd = $('[data-status-td="' + item.id + '"]');
                                    var $row = $('tr[data-post-id="' + item.id + '"]');
                                    $row.find('.w2p-aui-row-check').prop('checked', false);

                                    if (item.status === 'success') {
                                        $statusTd.empty().append(
                                            $('<span>').addClass('w2p-status-badge w2p-status-success')
                                                .text(w2pSmartAuiScanner.i18n.completedLabel || 'Completed')
                                        );
                                    } else {
                                        $statusTd.empty().append(
                                            $('<span>').addClass('w2p-status-badge w2p-status-secondary')
                                                .text(item.message || item.status)
                                        );
                                    }
                                });

                                self.updateActionButtons();
                                var msg = (w2pSmartAuiScanner.i18n.batchDoneMsg || 'Batch complete: %d posts modified.').replace('%d', d.modified_posts);
                                self.logTerminal('✓ ' + msg);
                                self.showToast(msg, 'success');
                            }
                        },
                        error: function () {
                            self.processing = false;
                            $('#w2p-aui-scanner-process-selected').prop('disabled', false);
                            self.logTerminal('✗ ' + w2pSmartAuiScanner.i18n.networkError);
                        }
                    });
                }
            };

            var confirmMsg = (w2pSmartAuiScanner.i18n.confirmGrabSelected || 'Are you sure you want to grab external media for %d post(s)?')
                .replace('%d', postIds.length);

            if (window.w2p && w2p.confirm) {
                w2p.confirm(confirmMsg, doProcess);
            } else if (confirm(confirmMsg)) {
                doProcess();
            }
        },

        /**
         * Start Full Auto Scan & Grab Loop
         */
        startAutoProcess: function () {
            var self = this;
            if (self.autoMode === 'running' || self.scanning || self.processing) return;

            self.autoMode = 'running';
            self.pauseRequested = false;
            self.stopRequested = false;
            self.autoLastId = 0;
            self.stats.scannedPosts = 0;
            self.stats.pendingPosts = 0;
            self.stats.externalUrls = 0;
            self.stats.modifiedPosts = 0;
            self.stats.failedCount = 0;

            $('#w2p-aui-scanner-summary').removeClass('w2p-hidden').show();
            self.updateStatsDisplay();

            $('#w2p-aui-scanner-start-auto').prop('disabled', true);
            $('#w2p-aui-scanner-pause-auto').removeClass('w2p-hidden').show().text(w2pSmartAuiScanner.i18n.pauseLabel || 'Pause');
            $('#w2p-aui-scanner-stop').removeClass('w2p-hidden').show();
            $('#w2p-aui-scanner-terminal').removeClass('w2p-hidden').show();
            $('#w2p-aui-scanner-progress').removeClass('w2p-hidden').show();
            $('#w2p-aui-scanner-results').addClass('w2p-hidden').hide();
            $('#w2p-aui-scanner-tbody').empty();

            self.logTerminal('=== ' + (w2pSmartAuiScanner.i18n.autoStarted || 'Full Auto Mode Started') + ' ===');
            self.autoProcessRound(1);
        },

        /**
         * Execute one round of auto scan + grab
         */
        autoProcessRound: function (round) {
            var self = this;

            if (self.stopRequested) {
                self.endAutoProcess('stopped', w2pSmartAuiScanner.i18n.userStopped || 'Processing stopped by user.');
                return;
            }

            if (self.pauseRequested) {
                self.autoMode = 'paused';
                $('#w2p-aui-scanner-pause-auto').prop('disabled', false).text(w2pSmartAuiScanner.i18n.resumeLabel || 'Resume');
                self.logTerminal('⏸ ' + (w2pSmartAuiScanner.i18n.paused || 'Paused'));
                return;
            }

            self.logTerminal('Round ' + round + ': ' + (w2pSmartAuiScanner.i18n.scanningBatch || 'Scanning posts...') + ' (Cursor ID: ' + self.autoLastId + ')');
            $('#w2p-aui-scanner-progress-text').text('Round ' + round + ' - ' + (w2pSmartAuiScanner.i18n.scanningBatch || 'Scanning posts...'));

            $.ajax({
                url: w2pSmartAuiScanner.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_smart_aui_scanner_scan',
                    nonce: w2pSmartAuiScanner.nonce,
                    limit: self.batchSize,
                    last_id: self.autoLastId
                },
                success: function (response) {
                    if (!response || !response.success) {
                        self.endAutoProcess('error', w2pSmartAuiScanner.i18n.scanFailed || 'Scan failed.');
                        return;
                    }

                    var data = response.data || {};
                    var posts = data.posts || [];
                    self.autoLastId = data.last_id || 0;
                    self.stats.scannedPosts += (data.scanned_count || 0);

                    // End if no more posts scanned in DB
                    if ((data.scanned_count || 0) === 0 || (!data.has_more && posts.length === 0)) {
                        self.endAutoProcess('completed', w2pSmartAuiScanner.i18n.autoComplete || 'All posts scanned and processed successfully!');
                        return;
                    }

                    if (posts.length === 0) {
                        self.logTerminal('Round ' + round + ': ' + (data.scanned_count || 0) + ' posts scanned, no external media found. Advancing...');
                        self.updateStatsDisplay();
                        setTimeout(function () {
                            self.autoProcessRound(round + 1);
                        }, 100);
                        return;
                    }

                    // Found posts with external media
                    self.stats.pendingPosts += posts.length;
                    var postIds = [];
                    posts.forEach(function (p) {
                        postIds.push(p.id);
                        self.stats.externalUrls += (p.external_count || 0);
                    });

                    self.updateStatsDisplay();
                    $('#w2p-aui-scanner-results').removeClass('w2p-hidden').show();
                    self.renderRows(posts);

                    self.logTerminal('Round ' + round + ': Launching grab for ' + postIds.length + ' post(s)...');
                    $('#w2p-aui-scanner-progress-text').text('Round ' + round + ' - Grabbing media for ' + postIds.length + ' post(s)...');

                    // Trigger Native Progress Modal
                    if (window.W2P_SmartAUI_Progress && typeof window.W2P_SmartAUI_Progress.startBulkProcessing === 'function') {
                        window.W2P_SmartAUI_Progress.startBulkProcessing(
                            postIds,
                            function (completedSuccessfully) {
                                self.logTerminal('✓ Round ' + round + ' complete: batch processed.');
                                self.updateStatsDisplay();

                                if (self.stopRequested) {
                                    self.endAutoProcess('stopped', w2pSmartAuiScanner.i18n.userStopped || 'Processing stopped by user.');
                                    return;
                                }

                                setTimeout(function () {
                                    self.autoProcessRound(round + 1);
                                }, 300);
                            },
                            function (postId, success) {
                                var $statusTd = $('[data-status-td="' + postId + '"]');
                                var $row = $('tr[data-post-id="' + postId + '"]');
                                $row.find('.w2p-aui-row-check').prop('checked', false);

                                if (success) {
                                    self.stats.modifiedPosts++;
                                    $statusTd.empty().append(
                                        $('<span>').addClass('w2p-status-badge w2p-status-success')
                                            .text(w2pSmartAuiScanner.i18n.completedLabel || 'Completed')
                                    );
                                } else {
                                    $statusTd.empty().append(
                                        $('<span>').addClass('w2p-status-badge w2p-status-secondary')
                                            .text(w2pSmartAuiScanner.i18n.completedLabel || 'Completed')
                                    );
                                }
                                self.updateStatsDisplay();
                            }
                        );
                    } else {
                        // Fallback AJAX
                        $.ajax({
                            url: w2pSmartAuiScanner.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'w2p_smart_aui_scanner_process_batch',
                                nonce: w2pSmartAuiScanner.nonce,
                                post_ids: JSON.stringify(postIds)
                            },
                            success: function (res) {
                                if (res && res.success && res.data) {
                                    var d = res.data;
                                    self.stats.modifiedPosts += (d.modified_posts || 0);
                                    self.updateStatsDisplay();
                                    self.logTerminal('✓ Round ' + round + ' complete: ' + (d.modified_posts || 0) + ' posts modified.');
                                    setTimeout(function () {
                                        self.autoProcessRound(round + 1);
                                    }, 200);
                                } else {
                                    self.endAutoProcess('error', 'Batch processing failed in round ' + round);
                                }
                            },
                            error: function () {
                                self.endAutoProcess('error', w2pSmartAuiScanner.i18n.networkError);
                            }
                        });
                    }
                },
                error: function () {
                    self.endAutoProcess('error', w2pSmartAuiScanner.i18n.networkError);
                }
            });
        },

        /**
         * Resume Auto Process after Pause
         */
        resumeAutoProcess: function () {
            var self = this;
            self.autoMode = 'running';
            self.pauseRequested = false;
            $('#w2p-aui-scanner-pause-auto').text(w2pSmartAuiScanner.i18n.pauseLabel || 'Pause');
            self.logTerminal('▶ ' + (w2pSmartAuiScanner.i18n.continuing || 'Resuming...'));
            self.autoProcessRound(1);
        },

        /**
         * Terminate Auto Process
         */
        endAutoProcess: function (status, message) {
            var self = this;
            self.autoMode = 'idle';
            self.pauseRequested = false;
            self.stopRequested = false;

            $('#w2p-aui-scanner-start-auto').prop('disabled', false);
            $('#w2p-aui-scanner-pause-auto').addClass('w2p-hidden').hide();
            $('#w2p-aui-scanner-stop').addClass('w2p-hidden').hide();
            $('#w2p-aui-scanner-progress').addClass('w2p-hidden').hide();

            self.logTerminal('=== ' + message + ' ===');
            self.showToast(message, status === 'completed' ? 'success' : (status === 'error' ? 'error' : 'info'));
        },

        /**
         * Log to terminal / log modal buffer
         */
         logTerminal: function (msg) {
            var now = new Date();
            var timeStr = '[' + ('0' + now.getHours()).slice(-2) + ':' +
                ('0' + now.getMinutes()).slice(-2) + ':' +
                ('0' + now.getSeconds()).slice(-2) + '] ';

            var line = timeStr + msg;
            this.runtimeLogs.push(line);

            // Limit buffer size to 1000 lines
            if (this.runtimeLogs.length > 1000) {
                this.runtimeLogs.shift();
            }

            var $logContent = $('#w2p-aui-log-content');
            if ($logContent.length) {
                $logContent.text(this.runtimeLogs.join('\n'));
                $logContent.scrollTop($logContent[0].scrollHeight);
            }

            this.updateLogSizeLabel();
        },

        /**
         * Update log size label in modal header
         */
        updateLogSizeLabel: function () {
            var $sizeLabel = $('#w2p-aui-log-size');
            if ($sizeLabel.length) {
                var totalChars = this.runtimeLogs.join('\n').length;
                var sizeKb = (totalChars / 1024).toFixed(1);
                $sizeLabel.text('(' + this.runtimeLogs.length + ' lines, ' + sizeKb + ' KB)');
            }
        },

        /**
         * Helper Toast
         */
        showToast: function (msg, type) {
            if (window.w2p && w2p.toast) {
                w2p.toast(msg, type || 'info');
            } else {
                console.log('[Smart AUI Scanner]', type, msg);
            }
        },

        /**
         * Open log modal
         */
        openLogModal: function () {
            var $modal = $('#w2p-aui-log-modal');
            var $logContent = $('#w2p-aui-log-content');
            
            if (this.runtimeLogs.length > 0) {
                $logContent.text(this.runtimeLogs.join('\n'));
            } else {
                $logContent.text('(No logs recorded yet. Start scanning or grabbing to generate logs.)');
            }

            this.updateLogSizeLabel();
            $modal.addClass('active');

            setTimeout(function () {
                if ($logContent.length && $logContent[0]) {
                    $logContent.scrollTop($logContent[0].scrollHeight);
                }
            }, 100);
        },

        /**
         * Close log modal
         */
        closeLogModal: function () {
            $('#w2p-aui-log-modal').removeClass('active');
        },

        /**
         * Clear runtime logs
         */
        clearLogs: function () {
            var self = this;
            var doClear = function () {
                self.runtimeLogs = [];
                $('#w2p-aui-log-content').text('(No logs recorded yet.)');
                self.updateLogSizeLabel();
                self.showToast(w2pSmartAuiScanner.i18n.logsCleared || 'Logs cleared', 'success');
            };

            var confirmMsg = w2pSmartAuiScanner.i18n.confirmClearLogs || 'Are you sure you want to clear all logs?';
            if (window.w2p && w2p.confirm) {
                w2p.confirm(confirmMsg, doClear);
            } else if (confirm(confirmMsg)) {
                doClear();
            }
        }
    };

    $(document).ready(function () {
        SmartAuiScannerUI.init();
    });

    window.W2P_SmartAuiScannerUI = SmartAuiScannerUI;

})(jQuery);
