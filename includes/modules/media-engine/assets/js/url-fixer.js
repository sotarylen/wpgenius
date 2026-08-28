/**
 * Media Engine - Content URL & Format Fixer UI
 *
 * Handles AJAX interactions for scanning, previewing, and batch-fixing
 * /wp-content/uploads/ references in post contents with MinIO WebP paths.
 * Enforces prerequisite safety checks before execution.
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */
(function ($) {
    'use strict';

    const { sprintf } = wp.i18n;

    const UrlFixerUI = {
        scanning: false,
        processing: false,
        autoMode: 'idle', // 'idle' | 'running' | 'paused' | 'stopped' | 'completed'
        pauseRequested: false,
        stopRequested: false,
        batchSize: 50,
        scanLimit: 50,
        scanLastId: 0,
        autoLastId: 0,
        prereqPassed: false,
        posts: [],
        stats: {
            scannedPosts: 0,
            modifiedPosts: 0,
            fixableUrls: 0,
            extFixes: 0,
            pathOnlyFixes: 0,
            externalSkipped: 0
        },

        /**
         * Initialize module UI
         */
        init: function () {
            if (window.w2pMediaEngine && window.w2pMediaEngine.scanLimit) {
                this.batchSize = parseInt(window.w2pMediaEngine.scanLimit, 10) || 50;
                this.scanLimit = this.batchSize;
            }
            this.bindEvents();
        },

        /**
         * Bind UI events
         */
        bindEvents: function () {
            const self = this;

            $('#w2p-fixer-scan').on('click', function () {
                self.checkPrerequisites(function () {
                    self.startScan();
                });
            });

            $('#w2p-fixer-start, #w2p-fixer-fix-checked').on('click', function () {
                self.checkPrerequisites(function () {
                    self.fixSelected();
                });
            });

            $('#w2p-fixer-start-auto').on('click', function () {
                self.checkPrerequisites(function () {
                    self.startAutoFix();
                });
            });

            $('#w2p-fixer-pause-auto').on('click', function () {
                if (self.autoMode === 'running' && !self.pauseRequested) {
                    self.pauseRequested = true;
                    $('#w2p-fixer-pause-auto').prop('disabled', true);
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast(w2pMediaEngine.i18n.pauseRequested, 'info');
                    }
                } else if (self.autoMode === 'paused') {
                    self.resumeAutoFix();
                }
            });

            $('#w2p-fixer-stop').on('click', function () {
                self.stopRequested = true;
                if (typeof w2p !== 'undefined' && w2p.toast) {
                    w2p.toast(w2pMediaEngine.i18n.stopRequested, 'warning');
                }
            });

            // Table check-all
            $('#w2p-fixer-check-all').on('change', function () {
                $('.w2p-fixer-row-check').prop('checked', $(this).is(':checked'));
                self.updateActionButtons();
            });

            $(document).on('change', '.w2p-fixer-row-check', function () {
                self.updateActionButtons();
            });

            // Summary stat card filters
            $('#w2p-fixer-summary .w2p-fixer-stat-card').on('click', function () {
                const filter = $(this).data('filter');
                self.toggleFilter(filter);
            });
        },

        /**
         * Check prerequisites before performing scan or fixes
         *
         * @param {Function|null} onPassed Callback to execute if prerequisites pass
         */
        checkPrerequisites: function (onPassed) {
            const self = this;
            const $scanBtn = $('#w2p-fixer-scan');

            $scanBtn.prop('disabled', true);

            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_media_fixer_check_prerequisites',
                    nonce: w2pMediaEngine.nonce
                },
                success: function (response) {
                    $scanBtn.prop('disabled', false);
                    if (response && response.success && response.data) {
                        const d = response.data;
                        self.prereqPassed = !!d.passed;

                        if (d.passed) {
                            if (typeof onPassed === 'function') {
                                onPassed();
                            }
                        } else {
                            const msgs = d.messages || [w2pMediaEngine.i18n.fixerPrereqFailed || 'Prerequisites check failed'];
                            const msgStr = msgs.join('\n');

                            if (typeof w2p !== 'undefined' && w2p.toast) {
                                w2p.toast(msgs[0], 'warning');
                            } else {
                                alert('⚠️ ' + msgStr);
                            }
                            self.logTerminal('⚠️ ' + msgs.join(' | '));
                        }
                    } else {
                        if (typeof onPassed === 'function') {
                            onPassed();
                        }
                    }
                },
                error: function () {
                    $scanBtn.prop('disabled', false);
                    if (typeof onPassed === 'function') {
                        onPassed();
                    }
                }
            });
        },

        /**
         * Update stat cards display
         */
        updateStatsDisplay: function () {
            const self = this;
            $('#w2p-fixer-summary [data-stat="pending_posts"]').text(self.stats.modifiedPosts > 0 ? self.stats.modifiedPosts : self.stats.scannedPosts);
            $('#w2p-fixer-summary [data-stat="fixable_urls"]').text(self.stats.fixableUrls);
            $('#w2p-fixer-summary [data-stat="ext_fixes"]').text(self.stats.extFixes);
            $('#w2p-fixer-summary [data-stat="path_only_fixes"]').text(self.stats.pathOnlyFixes);
            $('#w2p-fixer-summary [data-stat="external_skipped"]').text(self.stats.externalSkipped);
        },

        hasScanned: false,

        /**
         * Start scanning posts
         */
        startScan: function () {
            const self = this;
            if (self.scanning || self.processing) return;

            self.hasScanned = true;
            self.scanning = true;
            self.stopRequested = false;
            self.scanLastId = 0;
            self.posts = [];
            self.stats.scannedPosts = 0;
            self.stats.fixableUrls = 0;
            self.stats.extFixes = 0;
            self.stats.pathOnlyFixes = 0;
            self.stats.externalSkipped = 0;

            $('#w2p-fixer-scan').prop('disabled', true);
            $('#w2p-fixer-stop').removeClass('w2p-hidden').show();
            $('#w2p-unified-progress').removeClass('w2p-hidden').show();
            $('#w2p-fixer-summary').removeClass('w2p-hidden').show();
            $('#w2p-fixer-results').addClass('w2p-hidden').hide();
            $('#w2p-fixer-tbody').empty();
            self.updateStatsDisplay();

            self.scanNextBatch();
        },

        /**
         * Scan next batch of posts using cursor
         */
        scanNextBatch: function () {
            const self = this;

            if (self.stopRequested) {
                self.finishScan(true);
                return;
            }

            $('#w2p-unified-progress-text').text(sprintf(w2pMediaEngine.i18n.scanning, self.posts.length));

            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_media_fixer_scan',
                    nonce: w2pMediaEngine.nonce,
                    limit: self.batchSize,
                    last_id: self.scanLastId
                },
                success: function (response) {
                    if (!response || !response.success) {
                        self.finishScan(false, response && response.data ? response.data : w2pMediaEngine.i18n.scanFailedMsg);
                        return;
                    }

                    const data = response.data;
                    const items = (data.posts || []).filter(function (p) {
                        return p.fixable_count > 0;
                    });

                    self.posts = self.posts.concat(items);
                    self.scanLastId = data.last_id || 0;

                    items.forEach(function (p) {
                        self.stats.scannedPosts++;
                        self.stats.fixableUrls += (p.fixable_count || 0);
                        self.stats.externalSkipped += (p.external_count || 0);
                        if (p.has_ext_fix) self.stats.extFixes += (p.fixable_count || 0);
                        else if (p.has_path_fix) self.stats.pathOnlyFixes += (p.fixable_count || 0);
                    });

                    self.updateStatsDisplay();
                    self.renderRows(items);

                    // Scan more batches if the database has more matching posts and limit not reached
                    if (data.posts && data.posts.length >= self.batchSize && self.scanLastId > 0 && self.posts.length < self.scanLimit) {
                        setTimeout(function () { self.scanNextBatch(); }, 100);
                    } else {
                        self.finishScan(false);
                    }
                },
                error: function () {
                    self.finishScan(false, w2pMediaEngine.i18n.ajaxFailed);
                }
            });
        },

        /**
         * Finish scanning
         */
        finishScan: function (stopped, errorMsg) {
            const self = this;
            self.scanning = false;

            $('#w2p-fixer-scan').prop('disabled', false);
            $('#w2p-fixer-stop').addClass('w2p-hidden').hide();
            $('#w2p-unified-progress').addClass('w2p-hidden').hide();

            if (errorMsg) {
                if (typeof w2p !== 'undefined' && w2p.toast) {
                    w2p.toast(errorMsg, 'error');
                }
                return;
            }

            // Always keep summary stats visible after a scan run
            $('#w2p-fixer-summary').removeClass('w2p-hidden').show();
            self.updateStatsDisplay();

            if (self.posts.length > 0) {
                $('#w2p-fixer-results').removeClass('w2p-hidden').show();
            } else {
                $('#w2p-fixer-results').addClass('w2p-hidden').hide();
            }

            self.updateActionButtons();

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(sprintf(w2pMediaEngine.i18n.fixerScanFound, self.posts.length, self.stats.fixableUrls), 'success');
            }
        },

        /**
         * Render rows in the table
         */
        renderRows: function (items) {
            const self = this;
            const $tbody = $('#w2p-fixer-tbody');

            items.forEach(function (item) {
                if (!item.fixable_count || item.fixable_count <= 0) {
                    return;
                }

                const $tr = $('<tr>').attr('data-post-id', item.id);
                if (item.has_ext_fix) $tr.attr('data-type', 'ext_fix');
                else if (item.has_path_fix) $tr.attr('data-type', 'path_only');
                else $tr.attr('data-type', 'external');

                // Checkbox
                const $cbTd = $('<td>');
                $('<input type="checkbox">')
                    .addClass('w2p-fixer-row-check')
                    .attr('data-id', item.id)
                    .prop('checked', true)
                    .appendTo($cbTd);
                $tr.append($cbTd);

                // Post ID
                $tr.append($('<td>').text(item.id));

                // Title / Edit Link (always render clickable link with fallback)
                const $titleTd = $('<td>');
                const editUrl = item.edit_url || (window.w2pMediaEngine && window.w2pMediaEngine.admin_url ? window.w2pMediaEngine.admin_url + 'post.php?post=' + item.id + '&action=edit' : 'post.php?post=' + item.id + '&action=edit');
                $('<a>')
                    .attr('href', editUrl)
                    .attr('target', '_blank')
                    .text(item.title)
                    .appendTo($titleTd);

                if (item.type) {
                    $('<span>').addClass('w2p-badge w2p-ml-xs').text(item.type).appendTo($titleTd);
                }
                $tr.append($titleTd);

                // Fixable URL count
                $tr.append($('<td>').text(item.fixable_count + ' URL(s)'));

                // Fix Type Badge
                const $badgeTd = $('<td>');
                if (item.has_ext_fix) {
                    $('<span>').addClass('w2p-status-badge w2p-status-success').text(w2pMediaEngine.i18n.fixerPathAndExt).appendTo($badgeTd);
                } else if (item.has_path_fix) {
                    $('<span>').addClass('w2p-status-badge w2p-status-info').text(w2pMediaEngine.i18n.fixerPathOnly).appendTo($badgeTd);
                }
                if (item.external_count > 0) {
                    $('<span>').addClass('w2p-status-badge w2p-status-secondary w2p-ml-xs').text(sprintf('%d Ext', item.external_count)).appendTo($badgeTd);
                }
                $tr.append($badgeTd);

                // Replacement preview (strictly show only 1 preview)
                const $prevTd = $('<td>');
                const sampleFixes = item.sample_fixes || [];
                if (sampleFixes.length > 0) {
                    const fix = sampleFixes[0];
                    const $p = $('<div>').addClass('w2p-fixer-preview-item');
                    $('<span>').addClass('old-url').text(fix.old_url).appendTo($p);
                    $p.append(' &rarr; ');
                    $('<span>').addClass('new-url').text(fix.new_url).appendTo($p);
                    $prevTd.append($p);

                    if (item.fixable_count > 1) {
                        $('<div>').addClass('w2p-text-muted w2p-text-xs w2p-mt-xs')
                            .text('+ ' + (item.fixable_count - 1) + ' more URL(s)...')
                            .appendTo($prevTd);
                    }
                }
                $tr.append($prevTd);

                $tbody.append($tr);
            });
        },

        /**
         * Update action buttons state
         */
        updateActionButtons: function () {
            const checkedCount = $('.w2p-fixer-row-check:checked').length;
            const hasChecked = checkedCount > 0;
            $('#w2p-fixer-start, #w2p-fixer-fix-checked').toggleClass('w2p-hidden', !hasChecked).toggle(hasChecked);
        },

        /**
         * Fix selected posts
         */
        fixSelected: function () {
            const self = this;
            const postIds = [];
            $('.w2p-fixer-row-check:checked').each(function () {
                postIds.push(parseInt($(this).attr('data-id'), 10));
            });

            if (postIds.length === 0) return;

            const doFix = function () {
                self.processing = true;
                $('#w2p-processing-output').removeClass('w2p-hidden').show();
                self.logTerminal(sprintf('Starting batch fix on %d selected post(s)...', postIds.length));

                $.ajax({
                    url: w2pMediaEngine.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_media_fixer_process_batch',
                        nonce: w2pMediaEngine.nonce,
                        post_ids: JSON.stringify(postIds)
                    },
                    success: function (response) {
                        self.processing = false;
                        if (response && response.success && response.data) {
                            const d = response.data;
                            self.stats.modifiedPosts += d.modified_posts;
                            self.stats.extFixes += d.ext_fixed;
                            self.stats.pathOnlyFixes += d.path_only_fixed;
                            self.updateStatsDisplay();

                            const msg = sprintf(w2pMediaEngine.i18n.fixerBatchDone, d.modified_posts, d.total_replaced, d.ext_fixed);
                            self.logTerminal('✓ ' + msg);
                            if (typeof w2p !== 'undefined' && w2p.toast) {
                                w2p.toast(msg, 'success');
                            }
                            setTimeout(function () { self.startScan(); }, 1200);
                        } else {
                            self.logTerminal('✗ ' + (response && response.data ? response.data : 'Batch failed'));
                        }
                    },
                    error: function () {
                        self.processing = false;
                        self.logTerminal('✗ ' + w2pMediaEngine.i18n.ajaxFailed);
                    }
                });
            };

            if (typeof w2p !== 'undefined' && w2p.confirm) {
                w2p.confirm(sprintf(w2pMediaEngine.i18n.fixerConfirmFix, postIds.length), doFix);
            } else if (confirm(sprintf(w2pMediaEngine.i18n.fixerConfirmFix, postIds.length))) {
                doFix();
            }
        },

        /**
         * Full auto batch processing mode
         */
        startAutoFix: function () {
            const self = this;
            if (self.autoMode === 'running') return;

            self.hasScanned = true;
            self.autoMode = 'running';
            self.pauseRequested = false;
            self.stopRequested = false;
            self.autoLastId = 0;

            $('#w2p-fixer-summary').removeClass('w2p-hidden').show();
            self.updateStatsDisplay();

            $('#w2p-fixer-start-auto').prop('disabled', true);
            $('#w2p-fixer-pause-auto').removeClass('w2p-hidden').show().text(w2pMediaEngine.i18n.pauseLabel);
            $('#w2p-fixer-stop').removeClass('w2p-hidden').show();
            $('#w2p-processing-output').removeClass('w2p-hidden').show();

            self.logTerminal('--- ' + w2pMediaEngine.i18n.autoStarted + ' ---');
            self.autoFixRound(1);
        },

        /**
         * One round of auto fixing
         */
        autoFixRound: function (round) {
            const self = this;

            if (self.stopRequested) {
                self.endAutoFix('stopped', w2pMediaEngine.i18n.userStopped);
                return;
            }

            if (self.pauseRequested) {
                self.autoMode = 'paused';
                $('#w2p-fixer-pause-auto').prop('disabled', false).text(w2pMediaEngine.i18n.resumeLabel);
                self.logTerminal('⏸ ' + w2pMediaEngine.i18n.paused);
                return;
            }

            self.logTerminal(sprintf('Round %d: Scanning pending posts (Cursor ID: %d)...', round, self.autoLastId));

            // Scan a batch of pending posts
            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_media_fixer_scan',
                    nonce: w2pMediaEngine.nonce,
                    limit: self.batchSize,
                    last_id: self.autoLastId
                },
                success: function (response) {
                    if (!response || !response.success) {
                        self.endAutoFix('error', w2pMediaEngine.i18n.scanFailed);
                        return;
                    }

                    const data = response.data;
                    const posts = data.posts || [];
                    self.autoLastId = data.last_id || 0;

                    if (posts.length === 0) {
                        self.endAutoFix('completed', w2pMediaEngine.i18n.fixerAutoComplete);
                        return;
                    }

                    const fixablePostIds = [];
                    posts.forEach(function (p) {
                        if (p.fixable_count > 0) fixablePostIds.push(p.id);
                        self.stats.externalSkipped += (p.external_count || 0);
                    });

                    if (fixablePostIds.length === 0) {
                        self.logTerminal(sprintf('Round %d: %d posts scanned, no local URLs needing fix. Advancing cursor...', round, posts.length));
                        self.updateStatsDisplay();
                        setTimeout(function () { self.autoFixRound(round + 1); }, 100);
                        return;
                    }

                    self.logTerminal(sprintf('Round %d: Fixing %d posts...', round, fixablePostIds.length));

                    // Fix batch
                    $.ajax({
                        url: w2pMediaEngine.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'w2p_media_fixer_process_batch',
                            nonce: w2pMediaEngine.nonce,
                            post_ids: JSON.stringify(fixablePostIds)
                        },
                        success: function (res) {
                            if (res && res.success && res.data) {
                                const d = res.data;
                                self.stats.modifiedPosts += d.modified_posts;
                                self.stats.fixableUrls += d.total_replaced;
                                self.stats.extFixes += d.ext_fixed;
                                self.stats.pathOnlyFixes += d.path_only_fixed;
                                self.updateStatsDisplay();

                                self.logTerminal(sprintf('  Round %d complete: %d posts modified | %d URLs replaced (%d WebP ext)', round, d.modified_posts, d.total_replaced, d.ext_fixed));
                                setTimeout(function () { self.autoFixRound(round + 1); }, 200);
                            } else {
                                self.endAutoFix('error', 'Batch processing failed');
                            }
                        },
                        error: function () {
                            self.endAutoFix('error', w2pMediaEngine.i18n.ajaxFailed);
                        }
                    });
                },
                error: function () {
                    self.endAutoFix('error', w2pMediaEngine.i18n.ajaxFailed);
                }
            });
        },

        /**
         * Resume auto mode
         */
        resumeAutoFix: function () {
            const self = this;
            self.autoMode = 'running';
            self.pauseRequested = false;
            $('#w2p-fixer-pause-auto').text(w2pMediaEngine.i18n.pauseLabel);
            self.logTerminal('▶ ' + w2pMediaEngine.i18n.continuing);
            self.autoFixRound(1);
        },

        /**
         * Terminate auto fix mode
         */
        endAutoFix: function (status, message) {
            const self = this;
            self.autoMode = 'idle';
            self.pauseRequested = false;
            self.stopRequested = false;

            $('#w2p-fixer-start-auto').prop('disabled', false);
            $('#w2p-fixer-pause-auto').addClass('w2p-hidden').hide();
            $('#w2p-fixer-stop').addClass('w2p-hidden').hide();

            self.logTerminal('=== ' + message + ' ===');
            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(message, status === 'completed' ? 'success' : (status === 'error' ? 'error' : 'info'));
            }
        },

        /**
         * Append line to terminal
         */
        logTerminal: function (text) {
            const $content = $('#w2p-output-content');
            $('#w2p-processing-output').removeClass('w2p-hidden').show();
            const $line = $('<div>').text('[' + new Date().toLocaleTimeString() + '] ' + text);
            $content.append($line);
            $content.parent().scrollTop($content.parent()[0].scrollHeight);
        },

        /**
         * Stat card toggle filter
         */
        toggleFilter: function (filter) {
            const self = this;
            const $rows = $('#w2p-fixer-tbody tr');
            if (!$rows.length) return;

            const $card = $('#w2p-fixer-summary .w2p-fixer-stat-card[data-filter="' + filter + '"]');
            const isSelected = $card.hasClass('w2p-is-selected');

            $('#w2p-fixer-summary .w2p-fixer-stat-card').removeClass('w2p-is-selected');

            if (isSelected || filter === 'pending') {
                $rows.show();
            } else {
                $card.addClass('w2p-is-selected');
                $rows.each(function () {
                    const rowType = $(this).attr('data-type');
                    if (filter === 'fixable') {
                        $(this).toggle(rowType === 'ext_fix' || rowType === 'path_only');
                    } else if (filter === 'ext_fix') {
                        $(this).toggle(rowType === 'ext_fix');
                    } else if (filter === 'path_only') {
                        $(this).toggle(rowType === 'path_only');
                    } else if (filter === 'external') {
                        $(this).toggle(rowType === 'external');
                    }
                });
            }
        }
    };

    window.UrlFixerUI = UrlFixerUI;

    $(document).ready(function () {
        if (typeof w2pMediaEngine !== 'undefined') {
            UrlFixerUI.init();
        }
    });

})(jQuery);
