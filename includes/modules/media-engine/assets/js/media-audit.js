/**
 * Media Engine - Residual Media Audit UI
 */
(function ($) {
    'use strict';

    const AuditUI = {
        scanning: false,
        stopRequested: false,
        subdir: '2026/07',
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
                self.subdir = $('#w2p-audit-subdir').val().trim().replace(/^\/+|\/+$/g, '');
                if (!self.subdir) {
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('请输入扫描目录', 'warning');
                    }
                    return;
                }
                self.startScan();
            });

            $('#w2p-audit-stop').on('click', function () {
                self.stopRequested = true;
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
            $('#w2p-audit-progress').removeClass('w2p-hidden').show();
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

            $('#w2p-audit-progress-text').text('扫描中... 已处理 ' + self.offset + ' 个文件' +
                (self.total ? ' / ' + self.total : '') +
                (self.offset === 0 ? '（首次扫描需构建索引，约 30 秒）' : ''));

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
                        self.finishScan(false, response.data || '扫描失败');
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
                    self.finishScan(false, 'AJAX 请求失败');
                }
            });
        },

        finishScan: function (stopped, errorMsg) {
            const self = this;

            self.scanning = false;

            $('#w2p-audit-scan').prop('disabled', false);
            $('#w2p-audit-stop').addClass('w2p-hidden').hide();
            $('#w2p-audit-progress').addClass('w2p-hidden').hide();

            if (errorMsg) {
                $('#w2p-audit-progress-text').text(errorMsg);
                $('#w2p-audit-progress').removeClass('w2p-hidden').show();
                return;
            }

            // Summary
            const summary = {
                cleanable: self.cleanableFiles.length,
                notOffloaded: self.results.filter(function (i) { return i.status === 'not_offloaded'; }).length,
                orphan: self.results.filter(function (i) { return i.status === 'orphan'; }).length,
                total: self.results.length
            };

            $('#w2p-audit-summary').removeClass('w2p-hidden').show().html(
                '<div class="w2p-smart-aui-stats-row">' +
                '<span class="stat-item total"><span class="label">扫描文件</span><span class="value">' + summary.total + '</span></span>' +
                '<span class="stat-item success"><span class="label">可清理(A)</span><span class="value">' + summary.cleanable + '</span></span>' +
                '<span class="stat-item threads"><span class="label">未offload(B)</span><span class="value">' + summary.notOffloaded + '</span></span>' +
                '<span class="stat-item failed"><span class="label">孤儿(C)</span><span class="value">' + summary.orphan + '</span></span>' +
                '</div>' +
                (stopped ? '<div style="margin-top:8px;color:#f59e0b;">已停止扫描</div>' : '')
            );

            $('#w2p-audit-results').removeClass('w2p-hidden').show();
            self.updateActionButtons();

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast('扫描完成：' + summary.total + ' 个文件', 'success');
            }
        },

        renderRows: function (items) {
            const self = this;
            const $tbody = $('#w2p-audit-tbody');

            items.forEach(function (item) {
                const statusMap = {
                    'cleanable': { label: '可清理', cls: 'success' },
                    'not_offloaded': { label: '未offload', cls: 'warning' },
                    'orphan': { label: '孤儿', cls: 'error' }
                };
                const st = statusMap[item.status] || { label: item.status, cls: '' };

                let desc = $('<div>').text(item.reason || '').html();
                if (item.parent && item.parent.title) {
                    const parentHtml = item.parent.url ?
                        '<a href="' + item.parent.url + '" target="_blank">' + item.parent.title + '</a>' :
                        item.parent.title;
                    desc += '<div class="w2p-parent-info">父级: ' + parentHtml + '</div>';
                }

                $tbody.append(
                    '<tr data-status="' + item.status + '">' +
                    '<td><input type="checkbox" class="w2p-audit-row-check" ' +
                    (item.status === 'cleanable' ? 'data-clean="1"' : '') +
                    (item.status === 'not_offloaded' && item.attachment_id ? 'data-enqueue="' + item.attachment_id + '"' : '') +
                    ' /></td>' +
                    '<td>' + $('<div>').text(item.basename).html() +
                    '<div class="w2p-file-meta">' + $('<div>').text(item.file).html() + '</div></td>' +
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
            const hasClean = $('.w2p-audit-row-check:checked[data-clean]').length > 0;
            const hasEnqueue = $('.w2p-audit-row-check:checked[data-enqueue]').length > 0;

            $('#w2p-audit-clean-all').toggleClass('w2p-hidden', !hasClean).toggle(hasClean);
            $('#w2p-audit-enqueue-all').toggleClass('w2p-hidden', !hasEnqueue).toggle(hasEnqueue);
        },

        cleanSelected: function () {
            const self = this;
            const files = $('.w2p-audit-row-check:checked[data-clean]').closest('tr')
                .find('.w2p-file-meta').text().trim();

            if (!confirm('确认删除 ' + $('.w2p-audit-row-check:checked[data-clean]').length +
                ' 个本地文件？\n\n这些文件已在存储桶中存在对应 webp，删除后不可恢复。\n\n' +
                '示例：' + files)) {
                return;
            }

            const fileList = [];
            $('.w2p-audit-row-check:checked[data-clean]').closest('tr').each(function () {
                fileList.push($(this).find('.w2p-file-meta').text().trim());
            });

            if (!fileList.length) return;

            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_media_audit_clean',
                    nonce: w2pMediaEngine.nonce,
                    files: JSON.stringify(fileList)
                },
                success: function (response) {
                    if (response.success && response.data) {
                        const d = response.data;
                        let msg = '清理完成：成功 ' + d.cleaned + ' 个';
                        if (d.skipped && d.skipped.length) {
                            msg += '，跳过 ' + d.skipped.length + ' 个';
                        }
                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast(msg, d.skipped && d.skipped.length ? 'warning' : 'success');
                        }
                        alert(msg);
                        location.reload();
                    } else {
                        alert('清理失败：' + (response.data || '未知错误'));
                    }
                },
                error: function () {
                    alert('AJAX 请求失败');
                }
            });
        },

        enqueueSelected: function () {
            const self = this;
            const ids = [];
            $('.w2p-audit-row-check:checked[data-enqueue]').each(function () {
                ids.push(parseInt($(this).attr('data-enqueue'), 10));
            });

            if (!ids.length) return;

            if (!confirm('将 ' + ids.length + ' 个附件加入批量处理队列？\n\n这些文件将被重新转换/offload。')) {
                return;
            }

            // 复用现有批量处理：直接调用 process_batch 并跳转到批量处理 Tab
            if (typeof w2pMediaEngine !== 'undefined' && w2pMediaEngine.nonce) {
                $.ajax({
                    url: w2pMediaEngine.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_process_batch',
                        nonce: w2pMediaEngine.nonce,
                        attachment_ids: ids
                    },
                    success: function (response) {
                        if (response.success) {
                            alert('已加入处理队列并开始处理，请前往「批量处理」Tab 查看进度');
                        } else {
                            alert('处理失败：' + (response.data || '未知错误'));
                        }
                    },
                    error: function () {
                        alert('AJAX 请求失败');
                    }
                });
            }
        },

        formatSize: function (bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + 'MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(1) + 'KB';
            return bytes + 'B';
        }
    };

    // Initialize
    $(document).ready(function () {
        if (typeof w2pMediaEngine !== 'undefined') {
            AuditUI.init();
        }
    });

})(jQuery);
