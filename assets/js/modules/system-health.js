/**
 * WP Genius - System Health & Cleanup Modules
 * Includes System Health stats, Image Link Remover, and Duplicate Post Cleaner.
 */

(function ($) {
    'use strict';

    window.WPGenius = window.WPGenius || {};

    // ==============================
    // 系统健康模块 (System Health)
    // ==============================
    WPGenius.SystemHealth = {
        init: function () {
            $('.w2p-health-action').on('click', this.handleHealthAction.bind(this));
            $('#w2p-health-scan-btn').on('click', this.handleScanAction.bind(this));

            // Auto-trigger scan and system info fetch on load
            this.autoTrigger();
        },

        autoTrigger: function () {
            var self = this;
            // Wait slightly for tabs to initialize if needed or just fire
            $(document).ready(function () {
                // Only if we are on the system health page
                if ($('#w2p-health-scan-btn').length) {
                    self.handleScanAction({ preventDefault: function () { } });
                    self.fetchSystemInfo();
                }
            });
        },

        fetchSystemInfo: function () {
            var self = this;
            var $container = $('#w2p-health-info-container');

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_get_info',
                nonce: w2pSystemHealth.nonce
            }, function (response) {
                if (response.success) {
                    self.renderSystemInfo(response.data);
                } else {
                    $container.html('<div class="w2p-notice w2p-notice-error">' + (response.data.message || 'Error loading system info') + '</div>');
                }
            }).fail(function () {
                $container.html('<div class="w2p-notice w2p-notice-error">Network error while loading system info.</div>');
            });
        },

        renderSystemInfo: function (data) {
            var html = '<div class="w2p-flex w2p-gap-lg">';

            // Server Environment
            html += '<div class="w2p-flex-1"><div class="w2p-section"><div class="w2p-section-header"><h4>Server Environment</h4></div><div class="w2p-section-body"><div class="w2p-info-grid">';
            $.each(data.server, function (key, value) {
                var label = key.replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
                html += '<div class="w2p-info-row"><div class="w2p-info-label">' + label + '</div><div class="w2p-info-value">' + value + '</div></div>';
            });
            html += '</div></div></div></div>';

            // WordPress Configuration
            html += '<div class="w2p-flex-1"><div class="w2p-section"><div class="w2p-section-header"><h4>WordPress Configuration</h4></div><div class="w2p-section-body"><div class="w2p-info-grid">';
            $.each(data.wordpress, function (key, value) {
                var label = key.replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
                html += '<div class="w2p-info-row"><div class="w2p-info-label">' + label + '</div><div class="w2p-info-value">' + value + '</div></div>';
            });
            html += '</div></div></div></div>';

            html += '</div>';
            $('#w2p-tab-info').html(html);
        },

        handleScanAction: function (e) {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            var $btn = (e && e.currentTarget) ? $(e.currentTarget) : $('#w2p-health-scan-btn');
            var $counts = $('.w2p-health-count');

            if ($btn.length) {
                w2p.loading($btn, true);
            }
            $counts.text('...');

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_get_stats',
                nonce: w2pSystemHealth.nonce
            }, function (response) {
                w2p.loading($btn, false);
                if (response.success) {
                    $.each(response.data, function (key, count) {
                        $('.w2p-health-card[data-type="' + key + '"] .w2p-health-count').text(count);
                    });
                    w2p.toast('Scan Complete', 'success');
                } else {
                    w2p.toast('Scan failed: ' + (response.data || 'Unknown error'), 'error');
                    $counts.text('-');
                }
            }).fail(function () {
                w2p.loading($btn, false);
                w2p.toast('Network error during scan.', 'error');
                $counts.text('-');
            });
        },

        handleHealthAction: function (e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var action = $btn.data('action');
            var $card = $btn.closest('.w2p-health-card');
            var $count = $card.find('.w2p-health-count');

            if (!action) return;

            w2p.loading($btn, true);

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_clean',
                cleanup_type: action,
                nonce: w2pSystemHealth.nonce
            }, this.handleHealthResponse.bind(this, $btn, $count))
                .fail(this.handleHealthError.bind(this, $btn));
        },

        handleHealthResponse: function ($btn, $count, response) {
            w2p.loading($btn, false);

            if (response.success) {
                $count.text('0');
                w2p.toast('Cleaned successfully!', 'success');
            } else {
                w2p.toast(response.data.message || 'Error occurred', 'error');
            }
        },

        handleHealthError: function ($btn) {
            w2p.loading($btn, false);
            w2p.toast('Network error', 'error');
        },

        showMessage: function (msg, type) {
            var $msg = $('#w2p-health-message');
            $msg.removeClass('w2p-notice-success w2p-notice-error')
                .addClass('w2p-notice-' + type)
                .text(msg)
                .fadeIn();

            setTimeout(function () {
                $msg.fadeOut();
            }, 5000);
        }
    };

    // ==============================
    // 图片链接移除模块 (Image Link Remover)
    // ==============================
    WPGenius.ImageLinkRemover = {
        allPosts: [],
        currentIndex: 0,
        isRunning: false,
        stopRequested: false,
        stats: { processed: 0, affected: 0 },

        init: function () {
            $('#w2p-image-link-scan-btn').on('click', this.handleScan.bind(this));
            $('#w2p-image-link-execute-btn').on('click', this.startExecution.bind(this));
            $('#w2p-image-link-stop-btn').on('click', this.handleStop.bind(this));
        },

        handleScan: function (e) {
            var $btn = $(e.currentTarget);
            var categoryId = $('#w2p-image-link-category').val();

            w2p.loading($btn, true);
            $btn.find('.dashicons').addClass('w2p-spin'); // spin the icon if text preserved or just rely on w2p.loading overlay

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_scan_links',
                category_id: categoryId,
                nonce: w2pSystemHealth.nonce
            }, (response) => {
                w2p.loading($btn, false);
                if (response.success) {
                    this.allPosts = response.data || [];
                    this.renderResults();
                    $('#w2p-image-link-status').text('Found ' + this.allPosts.length + ' posts with linked images.');
                    $('#w2p-image-link-results-wrapper').fadeIn();
                    $('#w2p-image-link-execute-btn').prop('disabled', this.allPosts.length === 0);

                    w2p.toast('Scan Complete', 'success');
                } else {
                    w2p.toast('Scan failed: ' + (response.data.message || 'Unknown error'), 'error');
                }
            }).fail(() => {
                w2p.loading($btn, false);
                w2p.toast('Network error during scan.', 'error');
            });
        },

        renderResults: function () {
            var $container = $('#w2p-image-link-items');
            $container.empty();

            this.allPosts.forEach((post) => {
                var titleLink = post.edit_url ? '<a href="' + post.edit_url + '" target="_blank">' + post.title + '</a>' : post.title;
                $container.append(
                    '<tr id="w2p-post-' + post.id + '">' +
                    '<td>' + post.id + '</td>' +
                    '<td>' + titleLink + '</td>' +
                    '<td class="status-cell"><span class="status-badge pending">Pending</span></td>' +
                    '</tr>'
                );
            });
        },

        startExecution: function (e) {
            if (this.isRunning || this.allPosts.length === 0) return;

            w2p.confirm(
                'Are you sure you want to remove links from images in ' + this.allPosts.length + ' posts?',
                () => {
                    this.isRunning = true;
                    this.stopRequested = false;
                    this.currentIndex = 0;
                    this.stats = { processed: 0, affected: 0 };

                    $('#w2p-image-link-notice').fadeOut();
                    $('#w2p-image-link-execute-btn').hide();
                    $('#w2p-image-link-stop-btn').show();

                    $('#w2p-image-link-scan-btn').prop('disabled', true);
                    $('#w2p-image-link-progress-wrapper').fadeIn();
                    this.updateProgress();

                    this.processBatch();
                }
            );
        },

        handleStop: function () {
            this.stopRequested = true;
            $('#w2p-image-link-stop-btn').prop('disabled', true).text('Stopping...');
        },

        processBatch: function () {
            if (this.currentIndex >= this.allPosts.length) {
                this.finishExecution();
                return;
            }

            if (this.stopRequested) {
                this.finishExecution('Stopped by user.');
                return;
            }

            var batchSize = parseInt($('#w2p-image-link-batch-size').val()) || 10;
            var batchPosts = this.allPosts.slice(this.currentIndex, this.currentIndex + batchSize);
            var batchIds = batchPosts.map(p => p.id);

            batchPosts.forEach(post => {
                var $row = $('#w2p-post-' + post.id);
                $row.find('.status-cell').html('<span class="status-badge pending">In Batch...</span>');
            });

            var $firstRow = $('#w2p-post-' + batchPosts[0].id);
            if ($firstRow.length) {
                $firstRow[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_remove_links',
                post_ids: batchIds,
                nonce: w2pSystemHealth.nonce
            }, (response) => {
                if (response.success && response.data.results) {
                    $.each(response.data.results, (id, count) => {
                        this.stats.processed++;
                        this.stats.affected += count;
                        var $row = $('#w2p-post-' + id);
                        var statusClass = count > 0 ? 'success' : 'pending';
                        var statusText = count > 0 ? 'Success' : 'No Links';
                        $row.find('.status-cell').html('<span class="status-badge ' + statusClass + '">' + statusText + '</span>');
                    });
                } else {
                    batchPosts.forEach(post => {
                        $('#w2p-post-' + post.id).find('.status-cell').html('<span class="status-badge error">Failed</span>');
                    });
                }

                this.currentIndex += batchPosts.length;
                this.updateProgress();
                setTimeout(this.processBatch.bind(this), 100);
            }).fail(() => {
                batchPosts.forEach(post => {
                    $('#w2p-post-' + post.id).find('.status-cell').html('<span class="status-badge error">Error</span>');
                });
                this.currentIndex += batchPosts.length;
                this.updateProgress();
                setTimeout(this.processBatch.bind(this), 100);
            });
        },

        updateProgress: function () {
            var progress = (this.currentIndex / this.allPosts.length) * 100;
            $('#w2p-image-link-progress-fill').css('width', progress + '%');
            $('#w2p-image-link-progress-status').text(
                'Processed: ' + this.currentIndex + ' / ' + this.allPosts.length +
                ' | Links Removed: ' + this.stats.affected
            );
        },

        finishExecution: function (msg) {
            this.isRunning = false;
            $('#w2p-image-link-stop-btn').hide().prop('disabled', false).html('<span class="dashicons dashicons-no-alt" style="margin-top: 4px; margin-right: 4px;"></span> Stop');
            $('#w2p-image-link-execute-btn').show();
            $('#w2p-image-link-scan-btn').prop('disabled', false);

            var finalMsg = msg || 'Execution complete! Processed ' + this.stats.processed + ' posts. Total links removed: ' + this.stats.affected;
            this.showNotice(finalMsg, this.stopRequested ? 'error' : 'success');
        },

        showNotice: function (msg, type) {
            w2p.toast(msg, type);
        }
    };

    // ==============================
    // 重复文章清理模块 (Duplicate Post Cleaner)
    // ==============================
    WPGenius.DuplicateCleaner = {
        duplicateGroups: [],
        isScanning: false,
        isProcessing: false,
        currentScanOffset: 0,
        scanLimit: 1000,
        totalDuplicateGroups: 0,
        scanStats: {},

        init: function () {
            if (typeof w2pSystemHealth === 'undefined') return;

            $('#w2p-duplicate-scan-btn').on('click', this.handleScan.bind(this));
            $('#w2p-duplicate-clear-btn').on('click', this.handleClearSelection.bind(this));
            $('#w2p-duplicate-clean-btn').on('click', this.handleCleanAll.bind(this));
            $(document).on('click', '.w2p-clean-group-btn', this.handleCleanGroup.bind(this));
            $(document).on('change', '.w2p-duplicate-checkbox', this.handleCheckboxChange.bind(this));
        },

        truncateSlug: function (slug, maxLength) {
            maxLength = maxLength || 40;
            if (slug.length <= maxLength) return slug;
            var headLength = Math.floor(maxLength * 0.4);
            var tailLength = Math.floor(maxLength * 0.4);
            return slug.substring(0, headLength) + '...' + slug.substring(slug.length - tailLength);
        },

        getTotalDuplicates: function () {
            var total = 0;
            this.duplicateGroups.forEach((group) => {
                group.posts.forEach((post) => {
                    if (post.selected) total++;
                });
            });
            return total;
        },

        renderResults: function () {
            var $container = $('#w2p-duplicate-groups');
            $container.empty();

            if (this.duplicateGroups.length === 0) {
                var emptyTemplate = document.getElementById('w2p-duplicate-empty-template');
                $container.append($(emptyTemplate.content.cloneNode(true)));
                return;
            }

            var groupTemplate = document.getElementById('w2p-duplicate-group-template');
            var postTemplate = document.getElementById('w2p-duplicate-post-template');

            this.duplicateGroups.forEach((group, groupIndex) => {
                var $group = $(groupTemplate.content.cloneNode(true));
                $group.find('.group-title').text(group.group_title);
                var $tbody = $group.find('.duplicate-posts-body');

                group.posts.forEach((post, postIndex) => {
                    var $postRow = $(postTemplate.content.cloneNode(true));
                    var $row = $postRow.find('.duplicate-post-row');
                    $row.attr('data-group', groupIndex);
                    $row.attr('data-post', postIndex);

                    var $checkbox = $postRow.find('.w2p-duplicate-checkbox');
                    $checkbox.attr('data-post-id', post.id);
                    if (post.selected) $checkbox.prop('checked', true);

                    $postRow.find('.post-id').text(post.id);
                    var $titleCell = $postRow.find('.post-title');
                    if (post.edit_url) {
                        $titleCell.empty().append($('<a></a>').attr({ 'href': post.edit_url, 'target': '_blank' }).text(post.title));
                    } else {
                        $titleCell.text(post.title);
                    }

                    var truncatedSlug = this.truncateSlug(post.slug);
                    $postRow.find('.post-slug code').text(truncatedSlug).attr('title', post.slug);
                    $postRow.find('.post-date').text(post.date);

                    var $statusCell = $postRow.find('.post-status');
                    $statusCell.find('.status-keep').toggle(post.recommended_keep);
                    $statusCell.find('.status-delete').toggle(!post.recommended_keep);

                    $tbody.append($postRow);
                });
                $container.append($group);
            });
        },

        handleCheckboxChange: function (e) {
            var $checkbox = $(e.currentTarget);
            var $row = $checkbox.closest('tr');
            var groupIndex = $row.data('group');
            var postIndex = $row.data('post');

            if (this.duplicateGroups[groupIndex] && this.duplicateGroups[groupIndex].posts[postIndex]) {
                this.duplicateGroups[groupIndex].posts[postIndex].selected = $checkbox.is(':checked');
            }
            this.updateStatus();
        },

        updateStatus: function () {
            var count = $('.w2p-duplicate-checkbox:checked').length;
            $('#w2p-duplicate-status').text('Found ' + this.duplicateGroups.length + ' duplicate groups (' + count + ' posts selected for deletion).');
        },

        handleClearSelection: function () {
            $('.w2p-duplicate-checkbox:checked').prop('checked', false);
            this.updateStatus();
        },

        handleCleanAll: function (e) {
            var selectedIds = [];
            $('.w2p-duplicate-checkbox:checked').each(function () {
                selectedIds.push($(this).data('post-id'));
            });

            if (selectedIds.length === 0) return w2p.toast('Please select at least one post to delete.', 'warning');

            w2p.confirm(
                'Are you sure you want to move ' + selectedIds.length + ' duplicate posts to trash?',
                () => {
                    var $btn = $(e.currentTarget);
                    w2p.loading($btn, true);
                    $btn.find('.btn-text').text($btn.data('text-cleaning'));

                    this.cleanPosts(selectedIds, $btn, null);
                }
            );
        },

        handleCleanGroup: function (e) {
            var $btn = $(e.currentTarget);
            var $group = $btn.closest('.w2p-duplicate-group');
            var selectedIds = [];

            $group.find('.w2p-duplicate-checkbox:checked').each(function () {
                selectedIds.push($(this).data('post-id'));
            });

            if (selectedIds.length === 0) return w2p.toast('Please select at least one post to delete in this group.', 'warning');

            w2p.loading($btn, true);
            $btn.find('.btn-text').text($btn.data('text-cleaning'));

            this.cleanPosts(selectedIds, $btn, $group);
        },

        cleanPosts: function (postIds, $btn, $group) {
            if (this.isProcessing) return;
            this.isProcessing = true;

            var batchSize = 50;
            var batches = [];
            for (var i = 0; i < postIds.length; i += batchSize) {
                batches.push(postIds.slice(i, i + batchSize));
            }

            var self = this;
            var totalProcessed = 0;
            var failedBatches = 0;

            var processBatch = function (batchIndex) {
                if (batchIndex >= batches.length) {
                    self.isProcessing = false;
                    if (failedBatches === 0) {
                        if ($group) {
                            w2p.toast('Cleaned!', 'success');
                            setTimeout(() => {
                                $group.fadeOut(300, function () {
                                    $(this).remove();
                                    if ($('.w2p-duplicate-group').length === 0) $('#w2p-duplicate-results-wrapper').fadeOut().addClass('w2p-hidden');
                                });
                            }, 1000);
                        } else {
                            w2p.toast('Cleanup Complete!', 'success');
                            setTimeout(() => $('#w2p-duplicate-scan-btn').click(), 1500);
                        }
                    } else {
                        self.showNotice('Partially completed: ' + totalProcessed + ' succeeded', 'error');
                        w2p.loading($btn, false);
                    }
                    return;
                }

                $.ajax({
                    url: w2pSystemHealth.ajax_url,
                    type: 'POST',
                    timeout: 30000,
                    data: {
                        action: 'w2p_system_health_trash_duplicates',
                        post_ids: batches[batchIndex],
                        nonce: w2pSystemHealth.nonce
                    },
                    success: (response) => {
                        if (response && response.success) {
                            totalProcessed += (response.data && response.data.count ? response.data.count : 0);
                            self.showNotice('Processed batch ' + (batchIndex + 1) + ' of ' + batches.length, 'success');
                            setTimeout(() => processBatch(batchIndex + 1), 500);
                        } else {
                            failedBatches++;
                            self.showNotice('Batch ' + (batchIndex + 1) + ' failed', 'error');
                            setTimeout(() => processBatch(batchIndex + 1), 500);
                        }
                    },
                    error: () => {
                        failedBatches++;
                        self.showNotice('Batch ' + (batchIndex + 1) + ' network error', 'error');
                        setTimeout(() => processBatch(batchIndex + 1), 500);
                    }
                });
            };
            processBatch(0);
        },

        showNotice: function (msg, type) {
            w2p.toast(msg, type);
        },

        handleScan: function (e) {
            var $btn = $(e.currentTarget);
            var categoryId = $('#w2p-duplicate-category').val();

            w2p.loading($btn, true);

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_scan_duplicates',
                category_id: categoryId,
                nonce: w2pSystemHealth.nonce
            }, (response) => {
                w2p.loading($btn, false);
                if (response && response.success) {
                    this.duplicateGroups = Array.isArray(response.data) ? response.data : [];
                    this.renderResults();
                    this.updateStatus();
                    $('#w2p-duplicate-results-wrapper').removeClass('w2p-hidden').fadeIn();
                    $('#w2p-duplicate-clean-btn').prop('disabled', this.duplicateGroups.length === 0);
                    w2p.toast('Scan Complete', 'success');
                } else {
                    var errorMsg = (response && response.data) ? (response.data.message || response.data) : 'Unknown error';
                    w2p.toast('Scan failed: ' + errorMsg, 'error');
                }
            }, 'json').fail((xhr) => {
                var errorMsg = 'Network error';
                w2p.loading($btn, false);
                w2p.toast('Scan failed: ' + errorMsg, 'error');
            });
        }
    };

    $(document).ready(function () {
        if (window.w2pSystemHealth) {
            WPGenius.SystemHealth.init();
            WPGenius.ImageLinkRemover.init();
            WPGenius.DuplicateCleaner.init();
        }
    });

})(jQuery);
