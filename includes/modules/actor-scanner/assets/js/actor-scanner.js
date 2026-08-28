(function ($) {
    'use strict';

    const ActorScanner = {
        isRunning: false,
        total: 0,
        processed: 0,
        offset: 0,
        batchSize: 20,
        stats: { matched: 0, created: 0, avatars: 0 },

        getVal: function (fieldId) {
            let $el = $('[name*="[' + fieldId + ']"]');
            if ($el.length === 0) {
                $el = $('#' + fieldId);
            }
            if ($el.length === 0) return '';
            if ($el.length > 1) {
                if ($el.is(':radio') || $el.is(':checkbox')) {
                    return $el.filter(':checked').val() || 0;
                }
                $el = $el.first();
            }
            if ($el.is(':checkbox')) return $el.is(':checked') ? 1 : 0;
            return $el.val();
        },

        getSettings: function () {
            let types = this.getVal('actor_scan_post_types');
            // CSF multi-select returns comma-separated values
            if (typeof types === 'string' && types.indexOf(',') !== -1) {
                types = types.split(',');
            } else {
                types = types ? [types] : ['post'];
            }
            return {
                post_types: types,
                batch_size: parseInt(this.getVal('actor_batch_size'), 10) || 20,
                create_new: !!parseInt(this.getVal('actor_create_new'), 10),
                only_unassigned: !!parseInt(this.getVal('actor_only_unassigned'), 10),
                append_existing: !!parseInt(this.getVal('actor_append_existing'), 10)
            };
        },

        post: function (action, data) {
            data = $.extend({
                action: action,
                nonce: $('#actor_scanner_nonce').val()
            }, data || {});
            return $.post(ajaxurl, data);
        },

        init: function () {
            this.bindEvents();
            this.loadStats();
        },

        bindEvents: function () {
            $('#actor-prepare-btn').on('click', this.prepareIndex.bind(this));
            $('#actor-scan-btn').on('click', this.startScan.bind(this));
            $('#actor-stop-btn').on('click', this.stopScan.bind(this));
            $('#actor-reset-btn').on('click', this.resetProgress.bind(this));
        },

        prepareIndex: function (e) {
            e.preventDefault();
            const btn = $('#actor-prepare-btn');
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + 'Preparing…');

            this.post('w2p_actor_prepare', { force: 1 })
                .done((res) => {
                    if (res.success) {
                        const info = res.data.information || {};
                        $('#actor-gf-status').html(
                            '<span class="w2p-status-label" style="color:#00a32a;">✓ ' +
                            'Actors: ' + res.data.actors.toLocaleString() +
                            ' | Files: ' + (info.TotalNum || '?') +
                            ' | Updated: ' + (info.Timestamp ? new Date(info.Timestamp * 1000).toLocaleDateString() : '?') +
                            '</span>'
                        );
                    } else {
                        $('#actor-gf-status').html('<span class="w2p-status-label" style="color:#d63638;">✗ ' + (res.data && res.data.message || 'Failed') + '</span>');
                    }
                })
                .fail(() => {
                    $('#actor-gf-status').html('<span class="w2p-status-label" style="color:#d63638;">✗ Network error</span>');
                })
                .always(() => {
                    btn.prop('disabled', false).html('<i class="fa fa-cloud-download"></i> ' + 'Prepare Index');
                });
        },

        loadStats: function () {
            this.post('w2p_actor_stats').done((res) => {
                if (res.success && res.data.progress && res.data.progress.total) {
                    const p = res.data.progress;
                    this.total = p.total;
                    this.processed = p.processed || 0;
                    $('#actor-progress-text').text(this.processed + ' / ' + this.total);
                    $('#actor-progress-bar').css('width', (this.total ? (this.processed / this.total * 100) : 0) + '%');
                }
            });
        },

        startScan: function (e) {
            e.preventDefault();
            if (this.isRunning) return;

            this.isRunning = true;
            this.processed = 0;
            this.offset = 0;
            this.stats = { matched: 0, created: 0, avatars: 0 };

            $('#actor-scan-btn').hide();
            $('#actor-stop-btn').show();
            $('#actor-reset-btn').hide();
            $('#actor-logs-tbody').empty();
            $('#actor-progress-label').text('Scanning…');

            const settings = this.getSettings();
            this.batchSize = settings.batch_size;

            // Count total first
            this.post('w2p_actor_get_total', settings).done((res) => {
                if (res.success) {
                    this.total = res.data.total;
                    $('#actor-progress-text').text('0 / ' + this.total.toLocaleString());
                    if (this.total === 0) {
                        this.finishScan();
                        return;
                    }
                    this.scanBatch();
                } else {
                    this.finishScan();
                }
            }).fail(() => this.finishScan());
        },

        scanBatch: function () {
            if (!this.isRunning) return;
            if (this.offset >= this.total) {
                this.finishScan();
                return;
            }

            const settings = this.getSettings();
            settings.offset = this.offset;

            this.post('w2p_actor_scan_batch', settings)
                .done((res) => {
                    if (res.success) {
                        this.offset += res.data.count;
                        this.processed += res.data.count;
                        this.appendLogs(res.data.log || []);
                        this.updateUI();

                        if (res.data.log) {
                            res.data.log.forEach(l => {
                                this.stats.matched += l.names ? l.names.length : 0;
                            });
                        }
                        setTimeout(() => this.scanBatch(), 50);
                    } else {
                        alert(res.data && res.data.message || 'Scan failed');
                        this.finishScan();
                    }
                })
                .fail(() => {
                    alert('Network error');
                    this.finishScan();
                });
        },

        stopScan: function (e) {
            e.preventDefault();
            this.isRunning = false;
            $('#actor-progress-label').text('Stopped');
            $('#actor-stop-btn').hide();
            $('#actor-scan-btn').show();
            $('#actor-reset-btn').show();
        },

        finishScan: function () {
            this.isRunning = false;
            $('#actor-progress-label').text('Done');
            $('#actor-progress-bar').css('width', '100%');
            $('#actor-scan-btn').show();
            $('#actor-stop-btn').hide();
            $('#actor-reset-btn').show();
        },

        resetProgress: function (e) {
            e.preventDefault();
            if (!confirm('Reset scan progress?')) return;
            this.post('w2p_actor_reset').done(() => {
                this.processed = 0;
                this.offset = 0;
                $('#actor-progress-text').text('0 / 0');
                $('#actor-progress-bar').css('width', '0%');
                $('#actor-logs-tbody').html('<tr><td colspan="4" style="text-align:center;color:#999;">Ready</td></tr>');
                $('#actor-stats-row').hide();
            });
        },

        updateUI: function () {
            const pct = this.total ? Math.min(100, this.processed / this.total * 100) : 0;
            $('#actor-progress-text').text(this.processed.toLocaleString() + ' / ' + this.total.toLocaleString());
            $('#actor-progress-bar').css('width', pct + '%');
        },

        appendLogs: function (logs) {
            if (!logs.length) return;
            const tbody = $('#actor-logs-tbody');
            if (tbody.find('td[colspan]').length) tbody.empty();

            logs.forEach((l) => {
                tbody.append(
                    '<tr>' +
                    '<td>' + l.id + '</td>' +
                    '<td>' + $('<div>').text(l.title).html() + '</td>' +
                    '<td>' + $('<div>').text((l.names || []).join(', ')).html() + '</td>' +
                    '<td>' + (l.terms || []).length + '</td>' +
                    '</tr>'
                );
            });

            // Keep the log bounded
            while (tbody.children().length > 100) {
                tbody.children().first().remove();
            }
        }
    };

    $(function () {
        ActorScanner.init();
    });
})(jQuery);
