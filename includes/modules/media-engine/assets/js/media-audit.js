/**
 * Media Engine - Residual Media Audit UI
 */
(function ($) {
    'use strict';

    const { sprintf } = wp.i18n;

    const AuditUI = {
        scanning: false,
        stopRequested: false,
        subdir: '',
        batchSize: 50,
        offset: 0,
        total: 0,
        results: [],
        cleanableFiles: [],
        enqueueableIds: [],

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            const self = this;

            $('#w2p-audit-scan').on('click', function () {
                self.subdir = '';
                self.startScan();
            });

            $('#w2p-audit-stop').on('click', function () {
                self.stopRequested = true;
            });

            // Stat cards act as filters: clicking selects the matching rows.
            $('#w2p-audit-summary .w2p-audit-stat-card').on('click', function () {
                self.toggleStatus($(this).data('filter'));
            });
            $('#w2p-audit-summary .w2p-audit-stat-card').on('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    self.toggleStatus($(this).data('filter'));
                }
            });

            $('#w2p-audit-check-all').on('change', function () {
                $('.w2p-audit-row-check').prop('checked', $(this).is(':checked'));
                self.updateActionButtons();
            });

            $('#w2p-audit-clean-all').on('click', function () {
                self.cleanSelected();
            });

            $('#w2p-audit-enqueue-all').on('click', function () {
                self.enqueueSelected();
            });

            // Row checkbox change -> update action buttons
            $(document).on('change', '.w2p-audit-row-check', function () {
                self.updateActionButtons();
            });
        },

        startScan: function () {
            const self = this;
            if (self.scanning) return;

            self.scanning = true;
            self.stopRequested = false;
            self.offset = 0;
            self.total = 0;
            self.results = [];
            self.cleanableFiles = [];
            self.enqueueableIds = [];

            $('#w2p-audit-scan').prop('disabled', true);
            $('#w2p-audit-stop').removeClass('w2p-hidden').show();
            $('#w2p-unified-progress, #w2p-audit-progress').removeClass('w2p-hidden').show();
            $('#w2p-audit-summary').addClass('w2p-hidden').hide();
            $('#w2p-audit-results').addClass('w2p-hidden').hide();
            $('#w2p-audit-tbody').empty();
            $('#w2p-audit-clean-all').addClass('w2p-hidden').hide();
            $('#w2p-audit-enqueue-all').addClass('w2p-hidden').hide();

            self.scanNextBatch();
        },

        scanNextBatch: function () {
            const self = this;

            if (self.stopRequested) {
                self.finishScan(true);
                return;
            }

            const progressMsg = sprintf(w2pMediaEngine.i18n.scanning, self.offset) +
                (self.total ? ' / ' + self.total : '') +
                (self.offset === 0 ? ' ' + w2pMediaEngine.i18n.firstScanIndex : '');
            $('#w2p-unified-progress-text, #w2p-audit-progress-text').text(progressMsg);

            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_media_audit_scan',
                    nonce: w2pMediaEngine.nonce,
                    subdir: self.subdir,
                    offset: self.offset,
                    limit: self.batchSize
                },
                success: function (response) {
                    if (!response.success) {
                        self.finishScan(false, response.data || w2pMediaEngine.i18n.scanFailedMsg);
                        return;
                    }

                    const data = response.data;
                    if (data.error) {
                        self.finishScan(false, data.error);
                        return;
                    }

                    self.total = data.total;
                    self.results = self.results.concat(data.files || []);

                    // Collect cleanable / enqueueable
                    (data.files || []).forEach(function (item) {
                        if (item.status === 'cleanable') {
                            self.cleanableFiles.push(item.file);
                        } else if (item.status === 'not_offloaded' && item.can_enqueue && item.attachment_id) {
                            self.enqueueableIds.push(item.attachment_id);
                        }
                    });

                    self.offset += (data.files || []).length;

                    // Render incremental table
                    self.renderRows(data.files || []);

                    if (self.offset < self.total) {
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

        finishScan: function (stopped, errorMsg) {
            const self = this;

            self.scanning = false;

            $('#w2p-audit-scan').prop('disabled', false);
            $('#w2p-audit-stop').addClass('w2p-hidden').hide();
            $('#w2p-unified-progress, #w2p-audit-progress').addClass('w2p-hidden').hide();

            if (errorMsg) {
                $('#w2p-unified-progress-text, #w2p-audit-progress-text').text(errorMsg);
                $('#w2p-unified-progress, #w2p-audit-progress').removeClass('w2p-hidden').show();
                return;
            }

            // Summary: populate the 4 clickable stat cards.
            const summary = {
                total: self.results.length,
                cleanable: self.cleanableFiles.length,
                'not_offloaded': self.results.filter(function (i) { return i.status === 'not_offloaded'; }).length,
                orphan: self.results.filter(function (i) { return i.status === 'orphan'; }).length
            };

            $('#w2p-audit-summary .w2p-audit-stat-card').removeClass('w2p-is-selected');
            $('#w2p-audit-summary [data-stat]').each(function () {
                const key = $(this).data('stat');
                if (typeof summary[key] !== 'undefined') {
                    $(this).text(summary[key]);
                }
            });

            $('#w2p-audit-summary').removeClass('w2p-hidden').show();
            $('#w2p-audit-results').removeClass('w2p-hidden').show();
            self.updateActionButtons();

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(sprintf(w2pMediaEngine.i18n.scanComplete, summary.total), 'success');
            }
        },

        renderRows: function (items) {
            const self = this;
            const $tbody = $('#w2p-audit-tbody');

            items.forEach(function (item) {
                const statusMap = {
                    'cleanable': { label: w2pMediaEngine.i18n.cleanableLabel, cls: 'success' },
                    'not_offloaded': { label: w2pMediaEngine.i18n.notOffloadedLabel, cls: 'warning' },
                    'orphan': { label: w2pMediaEngine.i18n.orphanLabel, cls: 'error' }
                };
                const st = statusMap[item.status] || { label: item.status, cls: '' };

                let desc = $('<div>').text(item.reason || '').html();
                if (item.parent && item.parent.title) {
                    const parentHtml = item.parent.url ?
                        '<a href="' + item.parent.url + '" target="_blank">' + item.parent.title + '</a>' :
                        item.parent.title;
                    desc += '<div class="w2p-parent-info">' + w2pMediaEngine.i18n.parentLabel + parentHtml + '</div>';
                }

                const thumb = item.thumb_url ?
                    '<img src="' + item.thumb_url + '" class="w2p-audit-thumb" loading="lazy" />' :
                    '<span class="w2p-audit-thumb-placeholder"><i class="fa-regular fa-image"></i></span>';

                $tbody.append(
                    '<tr data-status="' + item.status + '">' +
                    '<td><input type="checkbox" class="w2p-audit-row-check" ' +
                    'data-file="' + $('<div>').text(item.file).html() + '" ' +
                    (item.status === 'cleanable' ? 'data-clean="1"' : '') +
                    (item.status === 'orphan' ? 'data-orphan="1"' : '') +
                    (item.status === 'not_offloaded' && item.attachment_id ? 'data-enqueue="' + item.attachment_id + '"' : '') +
                    ' /></td>' +
                    '<td class="w2p-audit-file-cell">' +
                    '<div class="w2p-audit-file-wrap">' +
                    thumb +
                    '<span class="w2p-audit-filename" title="' + $('<div>').text(item.file).html() + '">' + $('<div>').text(item.file).html() + '</span>' +
                    '</div>' +
                    '</td>' +
                    '<td>' + item.ext + '</td>' +
                    '<td><span class="w2p-status-badge w2p-status-' + st.cls + '">' + st.label + '</span></td>' +
                    '<td>' + (item.size ? self.formatSize(item.size) : '-') + '</td>' +
                    '<td>' + desc + '</td>' +
                    '</tr>'
                );
            });
        },

        updateActionButtons: function () {
            const self = this;
            // Clean button appears when either cleanable (Class A) or orphan (Class C) files are selected.
            const hasClean = $('.w2p-audit-row-check:checked[data-clean], .w2p-audit-row-check:checked[data-orphan]').length > 0;
            const hasEnqueue = $('.w2p-audit-row-check:checked[data-enqueue]').length > 0;

            $('#w2p-audit-clean-all').toggleClass('w2p-hidden', !hasClean).toggle(hasClean);
            $('#w2p-audit-enqueue-all').toggleClass('w2p-hidden', !hasEnqueue).toggle(hasEnqueue);
        },

        // Toggle selection of rows matching a status filter ('total' selects all). Returning true if any were selected.
        toggleStatus: function (filter) {
            const self = this;
            let $rows;

            if (filter === 'total') {
                $rows = $('#w2p-audit-tbody tr');
            } else {
                $rows = $('#w2p-audit-tbody tr[data-status="' + filter + '"]');
            }

            if (!$rows.length) {
                return;
            }

            const $checks = $rows.find('.w2p-audit-row-check');
            const allChecked = $checks.length > 0 && $checks.filter(':checked').length === $checks.length;
            $checks.prop('checked', !allChecked);

            // Reflect the selection state on the clicked card.
            const $card = $('#w2p-audit-summary .w2p-audit-stat-card[data-filter="' + filter + '"]');
            $card.toggleClass('w2p-is-selected', !allChecked);

            self.updateActionButtons();
        },

        cleanSelected: function () {
            const self = this;

            const $cleanable = $('.w2p-audit-row-check:checked[data-clean]');
            const $orphans = $('.w2p-audit-row-check:checked[data-orphan]');
            const total = $cleanable.length + $orphans.length;
            if (!total) return;

            const example = ($cleanable.first().data('file') || $orphans.first().data('file') || '');

            const doClean = function () {
                const fileList = [];
                const orphanList = [];
                $cleanable.each(function () { const p = $(this).data('file'); if (p) fileList.push(p); });
                $orphans.each(function () { const p = $(this).data('file'); if (p) orphanList.push(p); });

                if (!fileList.length && !orphanList.length) return;

                $.ajax({
                    url: w2pMediaEngine.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_media_audit_clean',
                        nonce: w2pMediaEngine.nonce,
                        files: JSON.stringify(fileList),
                        orphans: JSON.stringify(orphanList)
                    },
                    success: function (response) {
                        const cleaned = response && response.success && response.data ? (response.data.cleaned || 0) : 0;
                        const skipped = response && response.data ? (response.data.skipped || []) : [];
                        let msg = sprintf(w2pMediaEngine.i18n.cleanupComplete, cleaned);
                        if (skipped.length) {
                            msg += ' ' + sprintf(w2pMediaEngine.i18n.skippedCount, skipped.length);
                        }
                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast(msg, skipped.length ? 'warning' : 'success');
                        }
                        // Reload so the table reflects the files that were actually removed.
                        location.reload();
                    },
                    error: function (xhr, status, error) {
                        // Files may still have been deleted server-side even if the response was malformed.
                        // Refresh so the table reflects reality; surface a soft notice rather than a hard failure.
                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast(w2pMediaEngine.i18n.unknownError, 'warning');
                        }
                        location.reload();
                    }
                });
            };

            if (typeof w2p !== 'undefined' && w2p.confirm) {
                w2p.confirm(sprintf(w2pMediaEngine.i18n.deleteConfirm, total, example), doClean);
            } else {
                if (confirm(sprintf(w2pMediaEngine.i18n.deleteConfirm, total, example))) {
                    doClean();
                }
            }
        },

        enqueueSelected: function () {
            const self = this;
            const ids = [];
            $('.w2p-audit-row-check:checked[data-enqueue]').each(function () {
                ids.push(parseInt($(this).attr('data-enqueue'), 10));
            });

            if (!ids.length) return;

            const doEnqueue = function () {
                // Reuse the existing batch processing: call process_batch directly and jump to the Batch Processing tab
                if (typeof w2pMediaEngine === 'undefined' || !w2pMediaEngine.nonce) return;
                $.ajax({
                    url: w2pMediaEngine.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_process_batch',
                        nonce: w2pMediaEngine.nonce,
                        attachment_ids: ids
                    },
                    success: function (response) {
                        const ok = response && response.success;
                        const msg = ok ? w2pMediaEngine.i18n.addedToQueue : (w2pMediaEngine.i18n.processingFailed + (response && response.data || w2pMediaEngine.i18n.unknownError));
                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast(msg, ok ? 'success' : 'error');
                        }
                    },
                    error: function () {
                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast(w2pMediaEngine.i18n.ajaxFailed, 'error');
                        }
                    }
                });
            };

            if (typeof w2p !== 'undefined' && w2p.confirm) {
                w2p.confirm(sprintf(w2pMediaEngine.i18n.enqueueConfirm, ids.length), doEnqueue);
            } else if (confirm(sprintf(w2pMediaEngine.i18n.enqueueConfirm, ids.length))) {
                doEnqueue();
            }
        },

        formatSize: function (bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + 'MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(1) + 'KB';
            return bytes + 'B';
        }
    };

    window.AuditUI = AuditUI;

    // Initialize
    $(document).ready(function () {
        if (typeof w2pMediaEngine !== 'undefined') {
            AuditUI.init();
        }
    });

})(jQuery);
