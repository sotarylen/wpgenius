(function ($) {
    'use strict';

    // Helper to get field value from CSF inputs
    const getVal = (fieldId) => {
        // We use a "contains" selector to be robust against prefix variations
        // Matches inputs where name contains "[fieldId]"
        // e.g. w2p_settings[fix_novel_id]
        let $el = $(`[name*="[${fieldId}]"]`);

        if ($el.length === 0) {
            // Fallback: Try ID match (CSF customizer style just in case)
            $el = $('#' + fieldId);
        }

        if ($el.length === 0) {
            console.error(`FixIndex: Field ${fieldId} not found in DOM.`);
            return '';
        }

        // Handle multiple elements (e.g. radio buttons), though these are mostly selects/inputs
        if ($el.length > 1) {
            // If radio/checkbox group, filter checked
            const $checked = $el.filter(':checked');
            if ($el.is(':radio') || $el.is(':checkbox')) {
                return $checked.length ? $checked.val() : 0;
            }
            // If duplicates found but not radio/checkbox, usually check first visible?
            console.warn(`FixIndex: Multiple inputs found for ${fieldId}, taking first.`);
            $el = $el.first();
        }

        if ($el.is(':checkbox')) {
            return $el.is(':checked') ? 1 : 0;
        }

        return $el.val();
    };

    const FixChapterIndex = {
        isScanning: false,
        isAutoRunning: false,
        total: 0,
        totalAtStart: 0,
        totalProcessed: 0,
        scanned: 0,
        scanResults: [],
        context: {},
        autoRunStats: {
            totalProcessed: 0,
            currentBatch: 0
        },

        init: function () {
            this.bindEvents();
            // Initial count might be delayed until settings loaded? 
            // CSF loads settings on page load.
            this.updateTotalCount();
        },

        bindEvents: function () {
            $('#fix-index-scan-btn').on('click', this.startScan.bind(this));
            $('#fix-index-execute-btn').on('click', this.executeUpdate.bind(this));
            $('#fix-index-auto-btn').on('click', this.startAutoRun.bind(this));
            $('#fix-index-reset-btn').on('click', this.resetScan.bind(this));
            $('#fix-index-stop-btn').on('click', this.stopProcess.bind(this));

            // Bind to input changes to update total count dynamically
            // Use delegation or specific CSF classes?
            // CSF fields usually trigger change.
            $(document).on('change', '[name^="w2p_settings[fix_"]', () => {
                this.updateTotalCount();
            });

            // Toggle rows handled by CSF dependency now

            // Clear progress link
            $(document).on('click', '#fix-index-clear-progress', (e) => {
                e.preventDefault();
                this.clearFinishedProgress(e);
            });
        },

        getSettings: function () {
            return {
                target_post_type: getVal('fix_target_post_type') || 'chapter',
                scan_mode: getVal('fix_scan_mode'),
                novel_id: getVal('fix_novel_id'),
                scan_limit: getVal('fix_scan_limit') || 5,
                index_format: getVal('fix_index_format'),
                index_connector: getVal('fix_index_connector'),
                auto_volume: getVal('fix_auto_volume'),
                batch_size: getVal('fix_batch_size') || 20
            };
        },

        updateTotalCount: function () {
            const settings = this.getSettings();
            const data = {
                action: 'fix_index_get_total',
                nonce: $('#fix_index_nonce').val(),
                ...settings
            };

            $.post(ajaxurl, data, (response) => {
                if (response.success) {
                    this.total = response.data.total;
                    const finishedCount = response.data.finished_count || 0;

                    $('#fix-progress-text').text(`0 / ${this.total.toLocaleString()}`);

                    if (finishedCount > 0) {
                        $('#finished-count-text').text(`Finished Books: ${finishedCount}`).show();
                        $('#finished-progress-row').show();
                    } else {
                        $('#finished-count-text').hide();
                    }
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

            // Refresh total before start
            const settings = this.getSettings();
            const totalData = {
                action: 'fix_index_get_total',
                nonce: $('#fix_index_nonce').val(),
                ...settings
            };

            $.post(ajaxurl, totalData, (response) => {
                if (response.success) {
                    this.total = response.data.total;
                    this.totalAtStart = response.data.total;
                    this.totalProcessed = 0;
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

            const settings = this.getSettings();
            const data = {
                action: 'fix_index_scan',
                nonce: $('#fix_index_nonce').val(),
                offset: this.scanned,
                context: JSON.stringify(this.context),
                ...settings
            };

            $.post(ajaxurl, data, (response) => {
                if (response.success) {
                    this.scanned += response.data.count;
                    this.context = response.data.context;
                    this.scanResults = this.scanResults.concat(response.data.logs);
                    this.appendLogs(response.data.logs);
                    this.updateUI();

                    if (response.data.finished_novel_id) {
                        this.markBookFinished(response.data.finished_novel_id);
                        if (settings.scan_mode === 'all') {
                            this.totalProcessed += this.scanned;
                            this.scanned = 0;
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

                    // Adding debug info
                    if (data.debug) {
                        console.log('Execute Debug Info:', data.debug);
                        if (!data.debug.acf_available) {
                            message += '\n\n⚠️ ACF not available, using native post_meta';
                        }
                    }

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

            const settings = this.getSettings();
            const totalData = {
                action: 'fix_index_get_total',
                nonce: $('#fix_index_nonce').val(),
                ...settings
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

            const settings = this.getSettings();
            // Step 1: Scan
            const scanData = {
                action: 'fix_index_scan',
                nonce: $('#fix_index_nonce').val(),
                offset: this.scanned,
                context: JSON.stringify(this.context),
                ...settings
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

                        if (scanResponse.data.finished_novel_id) {
                            this.markBookFinished(scanResponse.data.finished_novel_id);
                            if (settings.scan_mode === 'all') {
                                this.scanned = 0;
                            }
                        }

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

        markBookFinished: function (novelId) {
            const data = {
                action: 'fix_index_mark_finished',
                nonce: $('#fix_index_nonce').val(),
                novel_id: novelId
            };
            $.post(ajaxurl, data, (response) => {
                if (response.success) {
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
