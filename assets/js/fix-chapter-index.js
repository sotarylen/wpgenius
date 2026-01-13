(function ($) {
    'use strict';

    const FixChapterIndex = {
        isScanning: false,
        isAutoRunning: false,
        total: 0,
        totalAtStart: 0,  // 开始时的总数
        totalProcessed: 0,  // 已处理的总数
        scanned: 0,
        scanResults: [],
        context: {},
        autoRunStats: {
            totalProcessed: 0,
            currentBatch: 0
        },

        init: function () {
            this.bindEvents();
            this.loadConfig();
            this.updateTotalCount();
        },

        bindEvents: function () {
            $('#fix-index-save-btn').on('click', this.saveConfig.bind(this));
            $('#fix-index-scan-btn').on('click', this.startScan.bind(this));
            $('#fix-index-execute-btn').on('click', this.executeUpdate.bind(this));
            $('#fix-index-auto-btn').on('click', this.startAutoRun.bind(this));
            $('#fix-index-reset-btn').on('click', this.resetScan.bind(this));
            $('#fix-index-stop-btn').on('click', this.stopProcess.bind(this));

            // 扫描模式切换
            $('#scan_mode').on('change', this.onScanModeChange.bind(this));

            // 清除进度（链接形式）
            $(document).on('click', '#fix-index-clear-progress', (e) => {
                e.preventDefault();
                this.clearFinishedProgress(e);
            });
        },

        loadConfig: function () {
            const settings = window.fixIndexSettings || {};
            if (settings.target_post_type) $('#target_post_type').val(settings.target_post_type);
            if (settings.index_format) $('#index_format').val(settings.index_format);
            if (settings.index_connector) $('#index_connector').val(settings.index_connector);
            if (settings.auto_volume) $('#auto_volume').prop('checked', true);
            if (settings.batch_size) $('#batch_size').val(settings.batch_size);
        },

        onScanModeChange: function () {
            const mode = $('#scan_mode').val();
            if (mode === 'by_novel') {
                $('#novel_id_row').show();
                $('#scan_limit_row').show();
            } else {
                $('#novel_id_row').hide();
                $('#scan_limit_row').hide();
            }
        },

        saveConfig: function (e) {
            e.preventDefault();

            const btn = $(e.currentTarget);
            btn.prop('disabled', true).addClass('w2p-btn-loading');

            const data = {
                action: 'fix_index_save_config',
                nonce: $('#fix_index_nonce').val(),
                target_post_type: 'chapter',
                scan_mode: $('#scan_mode').val(),
                novel_id: $('#novel_id').val() || 0,
                scan_limit: $('#scan_limit').val() || 5,
                index_format: $('#index_format').val(),
                index_connector: $('#index_connector').val(),
                auto_volume: $('#auto_volume').is(':checked') ? 1 : 0,
                batch_size: $('#batch_size').val()
            };

            $.post(ajaxurl, data, (response) => {
                btn.prop('disabled', false).removeClass('w2p-btn-loading');
                if (response.success) {
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast(response.data.message, 'success');
                    } else {
                        alert(response.data.message);
                    }
                    // 保存后更新总数显示
                    this.updateTotalCount();
                } else {
                    alert(response.data || 'Save failed');
                }
            }).fail(() => {
                btn.prop('disabled', false).removeClass('w2p-btn-loading');
                alert('Network error');
            });
        },

        updateTotalCount: function () {
            const data = {
                action: 'fix_index_get_total',
                nonce: $('#fix_index_nonce').val(),
                scan_mode: $('#scan_mode').val(),
                novel_id: $('#novel_id').val() || 0,
                scan_limit: $('#scan_limit').val() || 5
            };

            $.post(ajaxurl, data, (response) => {
                if (response.success) {
                    this.total = response.data.total;
                    $('#fix-progress-text').text(`0 / ${this.total.toLocaleString()}`);
                }
            });
        },

        resetScan: function (e) {
            e.preventDefault();

            this.scanned = 0;
            this.scanResults = [];
            this.context = {};

            $('#fix-logs-tbody').html('<tr><td colspan="3" style="text-align:center;color:#999;">Ready</td></tr>');
            $('#fix-progress-bar').css('width', '0%');
            $('#fix-progress-text').text(`0 / ${this.total}`);

            $('#fix-index-scan-btn').show();
            $('#fix-index-execute-btn').hide();
            $('#fix-index-reset-btn').hide();
        },

        startScan: function (e) {
            e.preventDefault();

            if (this.isScanning) return;

            this.isScanning = true;
            this.scanned = 0;
            this.scanResults = [];
            this.context = {};

            $('#fix-index-scan-btn').hide();
            $('#fix-index-execute-btn').hide();
            $('#fix-index-auto-btn').hide();
            $('#fix-index-stop-btn').show();
            $('#fix-logs-tbody').empty();

            // 先获取总数
            const totalData = {
                action: 'fix_index_get_total',
                nonce: $('#fix_index_nonce').val(),
                scan_mode: $('#scan_mode').val(),
                novel_id: $('#novel_id').val() || 0,
                scan_limit: $('#scan_limit').val() || 5
            };

            $.post(ajaxurl, totalData, (response) => {
                if (response.success) {
                    this.total = response.data.total;
                    this.totalAtStart = response.data.total;  // 记录开始时的总数
                    this.totalProcessed = 0;  // 重置已处理数
                    $('#fix-progress-text').text(`0 / ${this.total.toLocaleString()} / ${this.totalAtStart.toLocaleString()}`);
                    this.scanBatch();
                } else {
                    alert('Failed to get total count');
                    this.isScanning = false;
                }
            }).fail(() => {
                alert('Network error');
                this.isScanning = false;
            });
        },

        scanBatch: function () {
            if (!this.isScanning || this.scanned >= this.total) {
                this.finishScan();
                return;
            }

            const data = {
                action: 'fix_index_scan',
                nonce: $('#fix_index_nonce').val(),
                target_post_type: 'chapter',
                scan_mode: $('#scan_mode').val(),
                novel_id: $('#novel_id').val(),
                scan_limit: $('#scan_limit').val(),
                index_format: $('#index_format').val(),
                index_connector: $('#index_connector').val(),
                auto_volume: $('#auto_volume').is(':checked') ? 1 : 0,
                batch_size: $('#batch_size').val(),
                offset: this.scanned,
                context: JSON.stringify(this.context)
            };

            $.post(ajaxurl, data, (response) => {
                if (response.success) {
                    this.scanned += response.data.count;
                    this.context = response.data.context;
                    this.scanResults = this.scanResults.concat(response.data.logs);
                    this.appendLogs(response.data.logs);
                    this.updateUI();

                    // 如果该批次完成了一本书
                    if (response.data.finished_novel_id) {
                        this.markBookFinished(response.data.finished_novel_id);
                        if ($('#scan_mode').val() === 'all') {
                            // 全量模式下，累加已处理数，重置当前批次计数
                            this.totalProcessed += this.scanned;
                            this.scanned = 0; // 重置本环节 offset
                        }
                    }

                    setTimeout(() => this.scanBatch(), 100);
                } else {
                    alert(response.data || 'Scan failed');
                    this.finishScan();
                }
            }).fail(() => {
                alert('Network error');
                this.finishScan();
            });
        },

        executeUpdate: function (e) {
            e.preventDefault();

            if (this.scanResults.length === 0) {
                alert('Please scan first');
                return;
            }

            if (!confirm(`Confirm update ${this.scanResults.length} chapters?`)) {
                return;
            }

            const btn = $(e.currentTarget);
            btn.prop('disabled', true).text('Executing...');

            const data = {
                action: 'fix_index_execute',
                nonce: $('#fix_index_nonce').val(),
                scan_results: JSON.stringify(this.scanResults)
            };

            $.post(ajaxurl, data, (response) => {
                btn.prop('disabled', false).text('Update');
                if (response.success) {
                    const data = response.data;
                    let message = data.message;

                    // 添加调试信息
                    if (data.debug) {
                        console.log('Execute Debug Info:', data.debug);
                        if (!data.debug.acf_available) {
                            message += '\n\n⚠️ ACF not available, using native post_meta';
                        }
                    }

                    // 显示错误详情
                    if (data.errors && data.errors.length > 0) {
                        console.error('Execute Errors:', data.errors);
                        message += '\n\nErrors: ' + data.errors.slice(0, 3).join(', ');
                    }

                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast(message, data.failed > 0 ? 'warning' : 'success');
                    } else {
                        alert(message);
                    }

                    this.scanResults = [];
                    $('#fix-index-execute-btn').hide();
                    $('#fix-index-reset-btn').show();
                } else {
                    alert(response.data || 'Execute failed');
                }
            }).fail(() => {
                btn.prop('disabled', false).text('Update');
                alert('Network error');
            });
        },

        startAutoRun: function (e) {
            e.preventDefault();

            if (!confirm('Start automatic processing? This will scan and execute in batches until all chapters are processed.')) {
                return;
            }

            this.isAutoRunning = true;
            this.scanned = 0;
            this.scanResults = [];
            this.context = {};
            this.autoRunStats.totalProcessed = 0;
            this.autoRunStats.currentBatch = 0;

            $('#fix-index-scan-btn').hide();
            $('#fix-index-execute-btn').hide();
            $('#fix-index-auto-btn').hide();
            $('#fix-index-reset-btn').hide();
            $('#fix-index-stop-btn').show();
            $('#fix-logs-tbody').empty();

            // 先获取总数
            const totalData = {
                action: 'fix_index_get_total',
                nonce: $('#fix_index_nonce').val(),
                scan_mode: $('#scan_mode').val(),
                novel_id: $('#novel_id').val() || 0,
                scan_limit: $('#scan_limit').val() || 5
            };

            $.post(ajaxurl, totalData, (response) => {
                if (response.success) {
                    this.total = response.data.total;
                    $('#fix-progress-text').text(`0 / ${this.total.toLocaleString()}`);
                    this.autoRunLoop();
                } else {
                    alert('Failed to get total count');
                    this.isAutoRunning = false;
                }
            }).fail(() => {
                alert('Network error');
                this.isAutoRunning = false;
            });
        },

        autoRunLoop: function () {
            if (!this.isAutoRunning || this.scanned >= this.total) {
                this.finishAutoRun();
                return;
            }

            // Step 1: Scan
            const scanData = {
                action: 'fix_index_scan',
                nonce: $('#fix_index_nonce').val(),
                target_post_type: 'chapter',
                scan_mode: $('#scan_mode').val(),
                novel_id: $('#novel_id').val(),
                scan_limit: $('#scan_limit').val(),
                index_format: $('#index_format').val(),
                index_connector: $('#index_connector').val(),
                auto_volume: $('#auto_volume').is(':checked') ? 1 : 0,
                batch_size: $('#batch_size').val(),
                offset: this.scanned,
                context: JSON.stringify(this.context)
            };

            $.post(ajaxurl, scanData, (scanResponse) => {
                if (!scanResponse.success || !this.isAutoRunning) {
                    this.finishAutoRun();
                    return;
                }

                const batchResults = scanResponse.data.logs;
                this.context = scanResponse.data.context;

                if (batchResults.length === 0) {
                    this.finishAutoRun();
                    return;
                }

                // Step 2: Execute
                const executeData = {
                    action: 'fix_index_execute',
                    nonce: $('#fix_index_nonce').val(),
                    scan_results: JSON.stringify(batchResults)
                };

                $.post(ajaxurl, executeData, (execResponse) => {
                    if (execResponse.success && this.isAutoRunning) {
                        this.scanned += batchResults.length;
                        this.autoRunStats.totalProcessed += execResponse.data.updated;
                        this.autoRunStats.currentBatch++;
                        this.appendLogs(batchResults);
                        this.updateUI();

                        // 如果该书籍已完成
                        if (scanResponse.data.finished_novel_id) {
                            this.markBookFinished(scanResponse.data.finished_novel_id);
                            if ($('#scan_mode').val() === 'all') {
                                this.scanned = 0; // 书籍处理完，偏移量归零
                            }
                        }

                        // Continue to next batch
                        setTimeout(() => this.autoRunLoop(), 500);
                    } else {
                        this.finishAutoRun();
                    }
                }).fail(() => {
                    alert('Execute failed');
                    this.finishAutoRun();
                });

            }).fail(() => {
                alert('Scan failed');
                this.finishAutoRun();
            });
        },

        stopProcess: function (e) {
            e.preventDefault();
            this.isScanning = false;
            this.isAutoRunning = false;
        },

        finishScan: function () {
            this.isScanning = false;
            $('#fix-index-stop-btn').hide();
            $('#fix-index-scan-btn').show();
            $('#fix-index-auto-btn').show();

            if (this.scanResults.length > 0) {
                $('#fix-index-execute-btn').show();
                $('#fix-index-reset-btn').show();
                if (typeof w2p !== 'undefined' && w2p.toast) {
                    w2p.toast(`Scan complete: ${this.scanResults.length} records`, 'success');
                } else {
                    alert(`Scan complete: ${this.scanResults.length} records`);
                }
            }
        },

        finishAutoRun: function () {
            this.isAutoRunning = false;
            $('#fix-index-stop-btn').hide();
            $('#fix-index-auto-btn').show();
            $('#fix-index-reset-btn').show();

            const msg = `Auto run complete! Processed ${this.autoRunStats.totalProcessed} chapters in ${this.autoRunStats.currentBatch} batches.`;
            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(msg, 'success');
            } else {
                alert(msg);
            }
        },

        updateUI: function () {
            const actualProcessed = this.totalProcessed + this.scanned;
            const percent = this.totalAtStart > 0 ? Math.round((actualProcessed / this.totalAtStart) * 100) : 0;
            $('#fix-progress-bar').css('width', percent + '%');
            $('#fix-progress-text').text(`${this.scanned} / ${this.total.toLocaleString()} / ${this.totalAtStart.toLocaleString()}`);
        },

        updateTotalCount: function () {
            const data = {
                action: 'fix_index_get_total',
                nonce: $('#fix_index_nonce').val(),
                scan_mode: $('#scan_mode').val(),
                novel_id: $('#novel_id').val() || 0,
                scan_limit: $('#scan_limit').val() || 5
            };
            $.post(ajaxurl, data, (response) => {
                if (response.success) {
                    this.total = response.data.total;
                    const finishedCount = response.data.finished_count || 0;

                    // 更新大数值显示
                    $('#fix-progress-text').text(`0 / ${this.total.toLocaleString()}`);

                    // 更新进度提示行
                    if (finishedCount > 0) {
                        $('#finished-count-text').text(`已处理书籍: ${finishedCount}`);
                        $('#finished-progress-row').show();
                    } else {
                        $('#finished-progress-row').hide();
                    }
                }
            });
        },

        markBookFinished: function (novelId) {
            const data = {
                action: 'fix_index_mark_finished',
                nonce: $('#fix_index_nonce').val(),
                novel_id: novelId
            };
            $.post(ajaxurl, data, (response) => {
                if (response.success) {
                    // 更新总数显示，因为现在少了一本书
                    this.updateTotalCount();
                }
            });
        },

        clearFinishedProgress: function (e) {
            e.preventDefault();
            if (!confirm('Clear all processed book records? Next scan will start from the very beginning.')) {
                return;
            }

            const data = {
                action: 'fix_index_clear_progress',
                nonce: $('#fix_index_nonce').val()
            };

            $.post(ajaxurl, data, (response) => {
                if (response.success) {
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast(response.data.message, 'success');
                    } else {
                        alert(response.data.message);
                    }
                    this.updateTotalCount();
                }
            });
        },

        appendLogs: function (logs) {
            if (!logs || logs.length === 0) return;

            logs.forEach(log => {
                const row = $('<tr></tr>');
                row.append($('<td></td>').text(log.index));
                row.append($('<td></td>').text(log.volume));
                row.append($('<td></td>').html(`<a href="${log.edit_link}" target="_blank">${log.title}</a>`));
                $('#fix-logs-tbody').append(row);
            });

            const container = $('#fix-logs-tbody').closest('.w2p-log-container');
            if (container.length) {
                container.scrollTop(container[0].scrollHeight);
            }
        }
    };

    $(document).ready(() => FixChapterIndex.init());

})(jQuery);
