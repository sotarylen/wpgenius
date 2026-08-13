/**
 * CMS Migrator JavaScript
 *
 * @package WP_Genius
 * @subpackage Modules/CMSMigrator
 */

/* global jQuery, w2pCMSMigrator, w2pCMSContentTypes */
(function ($) {
    'use strict';

    var CMSMigrator = {
        progressInterval: null,

        escapeHtml: function (str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(String(str)));
            return div.innerHTML;
        },

        init: function () {
            this.bindEvents();
            this.loadStats();
        },

        bindEvents: function () {
            $(document).on('click', '#w2p-cms-test-connection', this.testConnection.bind(this));
            $(document).on('click', '#w2p-cms-save-settings', this.saveSettings.bind(this));
            $(document).on('click', '#w2p-cms-get-stats', this.getStats.bind(this));
            $(document).on('click', '#w2p-cms-start-migration', this.startMigration.bind(this));
            $(document).on('click', '#w2p-cms-stop-migration', this.stopMigration.bind(this));
            $(document).on('click', '#w2p-cms-rollback', this.rollback.bind(this));
            $(document).on('click', '#w2p-cms-preview-books', this.previewData.bind(this, 'books'));
            $(document).on('click', '#w2p-cms-preview-chapters', this.previewData.bind(this, 'chapters'));
            $(document).on('click', '#w2p-cms-preview-albums', this.previewData.bind(this, 'albums'));
            $(document).on('click', '.w2p-cms-migrator-tab', this.switchTab.bind(this));
            $(document).on('click', '#w2p-cms-select-all', this.selectAll.bind(this));
            $(document).on('click', '#w2p-cms-deselect-all', this.deselectAll.bind(this));
            $(document).on('change', '.w2p-cms-type-checkbox', this.onTypeCheck.bind(this));
        },

        ajax: function (action, data, callback) {
            data = data || {};
            data.action = 'w2p_cms_' + action;
            data.nonce = w2pCMSMigrator.nonce;

            $.post(w2pCMSMigrator.ajax_url, data, function (response) {
                if (typeof callback === 'function') {
                    callback(response);
                }
            }).fail(function (jqXHR, textStatus) {
                CMSMigrator.showStatus('Request failed: ' + textStatus, 'error');
            });
        },

        showStatus: function (message, type) {
            var $status = $('#w2p-cms-status');
            $status.removeClass('w2p-cms-migrator-status-success w2p-cms-migrator-status-error w2p-cms-migrator-status-info w2p-cms-migrator-status-warning');
            $status.addClass('w2p-cms-migrator-status-' + type).text(message).show();
        },

        hideStatus: function () {
            $('#w2p-cms-status').hide();
        },

        /* Load stats on init to populate type counts */
        loadStats: function () {
            var self = this;
            this.ajax('get_stats', {}, function (response) {
                if (response.success) {
                    self.populateStats(response.data.stats);
                }
            });
        },

        populateStats: function (stats) {
            $('#w2p-cms-stat-books').text(stats.books || 0);
            $('#w2p-cms-stat-chapters').text(stats.chapters || 0);
            $('#w2p-cms-stat-albums').text(stats.albums || 0);
            $('#w2p-cms-stat-models').text(stats.models || 0);
            $('#w2p-cms-stat-studios').text(stats.studios || 0);
            $('#w2p-cms-stat-images').text(stats.images || 0);
            $('#w2p-cms-stats-grid').show();

            // Update type selection counts.
            var types = ['books', 'albums', 'models', 'studios'];
            types.forEach(function (type) {
                var count = stats[type] || 0;
                $('.w2p-cms-type-checkbox[value="' + type + '"]').attr('data-count', count);
                $('.w2p-cms-content-type-row[data-type="' + type + '"] .w2p-cms-type-count').text(count);
            });
        },

        /* Step 1: Select/Deselect all */
        selectAll: function (e) {
            e.preventDefault();
            $('.w2p-cms-type-checkbox').prop('checked', true);
        },

        deselectAll: function (e) {
            e.preventDefault();
            $('.w2p-cms-type-checkbox').prop('checked', false);
        },

        onTypeCheck: function (e) {
            var $checkbox = $(e.currentTarget);
            var type = $checkbox.val();
            var $row = $checkbox.closest('.w2p-cms-content-type-row');
            if ($checkbox.is(':checked')) {
                $row.css('background', '#f0f6fc').css('border-color', '#2271b1');
            } else {
                $row.css('background', '').css('border-color', '');
            }
        },

        getSelectedTypes: function () {
            var selected = [];
            $('.w2p-cms-type-checkbox:checked').each(function () {
                selected.push($(this).val());
            });
            return selected;
        },

        getMappings: function () {
            var mappings = {};
            $('.w2p-cms-mapping-post-type').each(function () {
                var type = $(this).data('type');
                mappings[type] = {
                    post_type: $(this).val(),
                    taxonomy: $('.w2p-cms-mapping-taxonomy[data-type="' + type + '"]').val() || ''
                };
            });
            return mappings;
        },

        testConnection: function (e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).html('<span class="w2p-cms-migrator-loading"></span> Testing...');

            this.ajax('test_connection', {
                db_host: $('#w2p-cms-db-host').val(),
                db_port: $('#w2p-cms-db-port').val(),
                db_name: $('#w2p-cms-db-name').val(),
                db_user: $('#w2p-cms-db-user').val(),
                db_pass: $('#w2p-cms-db-pass').val()
            }, function (response) {
                $btn.prop('disabled', false).text('Test Connection');
                if (response.success) {
                    CMSMigrator.showStatus(response.data.message, 'success');
                } else {
                    CMSMigrator.showStatus(response.data.message, 'error');
                }
            });
        },

        saveSettings: function (e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).html('<span class="w2p-cms-migrator-loading"></span> Saving...');

            this.ajax('save_settings', {
                db_host: $('#w2p-cms-db-host').val(),
                db_port: $('#w2p-cms-db-port').val(),
                db_name: $('#w2p-cms-db-name').val(),
                db_user: $('#w2p-cms-db-user').val(),
                db_pass: $('#w2p-cms-db-pass').val()
            }, function (response) {
                $btn.prop('disabled', false).text('Save Settings');
                if (response.success) {
                    CMSMigrator.showStatus(response.data.message, 'success');
                } else {
                    CMSMigrator.showStatus(response.data.message, 'error');
                }
            });
        },

        getStats: function (e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).html('<span class="w2p-cms-migrator-loading"></span> Loading...');

            this.ajax('get_stats', {}, function (response) {
                $btn.prop('disabled', false).text('Refresh Stats');
                if (response.success) {
                    CMSMigrator.populateStats(response.data.stats);
                } else {
                    CMSMigrator.showStatus(response.data.message, 'error');
                }
            });
        },

        startMigration: function (e) {
            e.preventDefault();

            var selected = this.getSelectedTypes();
            if (selected.length === 0) {
                this.showStatus('Please select at least one content type to migrate.', 'error');
                return;
            }

            var mappings = this.getMappings();
            var msg = 'Migrate the following content types?\n\n';
            selected.forEach(function (type) {
                var label = w2pCMSContentTypes[type] ? w2pCMSContentTypes[type].label : type;
                var pt = mappings[type] ? mappings[type].post_type : type;
                msg += '- ' + label + ' → ' + pt + '\n';
            });

            if (!confirm(msg)) {
                return;
            }

            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).html('<span class="w2p-cms-migrator-loading"></span> Starting...');
            $('#w2p-cms-stop-migration').prop('disabled', false);

            // Show progress cards for selected types.
            $('.w2p-cms-type-progress-card').hide().attr('data-status', 'pending');
            selected.forEach(function (type) {
                var $card = $('.w2p-cms-type-progress-card[data-type="' + type + '"]');
                $card.show().attr('data-status', 'pending');
                CMSMigrator.updateTypeProgress(type, 'pending', 0, 0);
            });

            this.ajax('start_migration', {
                types: selected,
                mappings: mappings,
                batch_limit: parseInt($('#w2p-cms-batch-limit').val(), 10) || 0
            }, function (response) {
                if (response.success) {
                    CMSMigrator.showStatus(response.data.message, 'info');
                    CMSMigrator.startProgressPolling();
                } else {
                    $btn.prop('disabled', false).text('Start Migration');
                    CMSMigrator.showStatus(response.data.message, 'error');
                }
            });
        },

        stopMigration: function (e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).html('<span class="w2p-cms-migrator-loading"></span> Stopping...');

            this.ajax('stop_migration', {}, function (response) {
                $btn.prop('disabled', false).text('Stop Migration');
                $('#w2p-cms-start-migration').prop('disabled', false).text('Start Migration');
                CMSMigrator.stopProgressPolling();
                CMSMigrator.showStatus(response.data.message, 'warning');
            });
        },

        rollback: function (e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to rollback? This will delete all migrated posts.')) {
                return;
            }

            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).html('<span class="w2p-cms-migrator-loading"></span> Rolling back...');

            this.ajax('rollback', {}, function (response) {
                $btn.prop('disabled', false).text('Rollback');
                if (response.success) {
                    CMSMigrator.showStatus(response.data.message, 'success');
                    // Reset type progress cards.
                    $('.w2p-cms-type-progress-card').hide().attr('data-status', 'pending');
                    $('#w2p-cms-progress-fill').css('width', '0%').text('0%');
                } else {
                    CMSMigrator.showStatus(response.data.message, 'error');
                }
            });
        },

        previewData: function (type, e) {
            e.preventDefault();
            var $container = $('#w2p-cms-preview-container');
            $container.html('<span class="w2p-cms-migrator-loading"></span> Loading preview...');

            this.ajax('preview_data', {
                type: type,
                limit: 5,
                db_host: $('#w2p-cms-db-host').val(),
                db_port: $('#w2p-cms-db-port').val(),
                db_name: $('#w2p-cms-db-name').val(),
                db_user: $('#w2p-cms-db-user').val(),
                db_pass: $('#w2p-cms-db-pass').val()
            }, function (response) {
                if (response.success) {
                    CMSMigrator.renderPreview(type, response.data.data, $container);
                } else {
                    $container.html('<div class="w2p-cms-migrator-status w2p-cms-migrator-status-error">' + CMSMigrator.escapeHtml(response.data.message) + '</div>');
                }
            });
        },

        renderPreview: function (type, data, $container) {
            if (!data || data.length === 0) {
                $container.html('<p>No data found.</p>');
                return;
            }

            var headers = Object.keys(data[0]);
            var html = '<table class="w2p-cms-migrator-preview-table"><thead><tr>';
            headers.forEach(function (header) {
                html += '<th>' + CMSMigrator.escapeHtml(header) + '</th>';
            });
            html += '</tr></thead><tbody>';
            data.forEach(function (row) {
                html += '<tr>';
                headers.forEach(function (header) {
                    var value = row[header];
                    if (typeof value === 'string' && value.length > 50) {
                        value = value.substring(0, 50) + '...';
                    }
                    html += '<td>' + CMSMigrator.escapeHtml(value || '') + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            $container.html(html);
        },

        startProgressPolling: function () {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
            }
            this.progressInterval = setInterval(function () {
                CMSMigrator.getProgress();
            }, 2000);
        },

        stopProgressPolling: function () {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }
        },

        getProgress: function () {
            this.ajax('get_progress', {}, function (response) {
                if (response.success) {
                    CMSMigrator.updateProgressUI(response.data.progress);
                }
            });
        },

        updateProgressUI: function (progress) {
            if (!progress) return;

            // Update overall progress bar.
            var types = progress.types || {};
            var typeKeys = Object.keys(types);
            var totalItems = 0;
            var completedItems = 0;

            typeKeys.forEach(function (type) {
                var t = types[type];
                totalItems += (t.total || 0);
                completedItems += (t.current || 0);
            });

            var percent = totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;
            $('#w2p-cms-progress-fill').css('width', percent + '%').text(percent + '%');

            // Update per-type progress cards.
            typeKeys.forEach(function (type) {
                var t = types[type];
                CMSMigrator.updateTypeProgress(type, t.status || 'pending', t.current || 0, t.total || 0);
            });

            // Update log.
            if (progress.log) {
                var $log = $('#w2p-cms-migration-log');
                $log.html('');
                progress.log.forEach(function (entry) {
                    var safeType = CMSMigrator.escapeHtml(entry.type || '');
                    var safeMsg = CMSMigrator.escapeHtml(entry.message || '');
                    $log.append('<div class="w2p-cms-migrator-log-entry ' + safeType + '">' + safeMsg + '</div>');
                });
                $log.scrollTop($log[0].scrollHeight);
            }

            // Check overall status.
            if (progress.status === 'completed') {
                CMSMigrator.stopProgressPolling();
                $('#w2p-cms-start-migration').prop('disabled', false).text('Start Migration');
                $('#w2p-cms-stop-migration').prop('disabled', true);
                CMSMigrator.showStatus('Migration completed!', 'success');
            } else if (progress.status === 'failed') {
                CMSMigrator.stopProgressPolling();
                $('#w2p-cms-start-migration').prop('disabled', false).text('Start Migration');
                $('#w2p-cms-stop-migration').prop('disabled', true);
                CMSMigrator.showStatus('Migration failed. Check logs for details.', 'error');
            } else if (progress.status === 'stopped') {
                CMSMigrator.stopProgressPolling();
                $('#w2p-cms-start-migration').prop('disabled', false).text('Start Migration');
                $('#w2p-cms-stop-migration').prop('disabled', true);
                CMSMigrator.showStatus('Migration stopped by user.', 'warning');
            }
        },

        updateTypeProgress: function (type, status, current, total) {
            var $card = $('.w2p-cms-type-progress-card[data-type="' + type + '"]');
            if ($card.length === 0) return;

            $card.attr('data-status', status);

            // Update status label.
            var $status = $card.find('.w2p-cms-type-progress-status');
            $status.attr('data-status', status);
            var labels = {
                pending: 'Pending',
                running: 'Running...',
                completed: 'Completed',
                failed: 'Failed',
                skipped: 'Skipped'
            };
            $status.text(labels[status] || status);

            // Update progress bar.
            var pct = total > 0 ? Math.round((current / total) * 100) : 0;
            $card.find('.w2p-cms-type-progress-fill').css('width', pct + '%');

            // Update counts.
            $card.find('.w2p-cms-type-progress-counts span:first').text(current);
            $card.find('.w2p-cms-type-progress-counts span:last').text(total);
        },

        switchTab: function (e) {
            var $tab = $(e.currentTarget);
            var target = $tab.data('target');

            $('.w2p-cms-migrator-tab').removeClass('active');
            $tab.addClass('active');

            $('.w2p-cms-migrator-tab-content').removeClass('active');
            $('#' + target).addClass('active');
        }
    };

    $(document).ready(function () {
        CMSMigrator.init();
    });

})(jQuery);
