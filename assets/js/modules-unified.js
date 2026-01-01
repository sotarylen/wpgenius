/**
 * WP Genius - 统一模块JavaScript
 * 整合所有功能模块的JavaScript代码
 */

(function ($) {
    'use strict';

    // 全局命名空间
    window.WPGenius = window.WPGenius || {};

    // ==============================
    // AI助手模块 (AI Assistant)
    // ==============================
    WPGenius.AIAssistant = {
        init: function () {
            $('.w2p-ai-action').on('click', this.handleAction.bind(this));
        },

        handleAction: function (e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var type = $btn.data('action');
            var $status = $('#w2p-ai-mb-status');

            // Get content from editor
            var content = this.getEditorContent();

            if (!content) {
                alert('Please add some content first.');
                return;
            }

            $btn.prop('disabled', true);
            $status.text('Generating...').fadeIn();

            $.post(w2pAiParams.ajax_url, {
                action: 'w2p_ai_generate_' + type,
                content: content,
                nonce: w2pAiParams.nonce
            }, this.handleResponse.bind(this, type, $btn, $status))
                .fail(this.handleError.bind(this, $btn, $status));
        },

        handleResponse: function (type, $btn, $status, response) {
            if (response.success) {
                if (type === 'excerpt') {
                    // Update excerpt field
                    if ($('#excerpt').length) {
                        $('#excerpt').val(response.data);
                        $status.text('Excerpt generated!').fadeOut(3000);
                    }
                } else if (type === 'tags') {
                    // Update tags field
                    if ($('#new-tag-post_tag').length) {
                        $('#new-tag-post_tag').val(response.data.join(', '));
                        $status.text('Tags suggested!').fadeOut(3000);
                    }
                }
            } else {
                $status.text('Error: ' + response.data).fadeOut(5000);
            }
            $btn.prop('disabled', false);
        },

        handleError: function ($btn, $status) {
            $status.text('Network error').fadeOut(5000);
            $btn.prop('disabled', false);
        },

        getEditorContent: function () {
            // Gutenberg
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostContent();
            }
            // TinyMCE
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content') && !tinyMCE.get('content').isHidden()) {
                return tinyMCE.get('content').getContent();
            }
            // Text mode
            return $('#content').val();
        }
    };

    // ==============================
    // 自动发布模块 (Auto Publish)
    // ==============================
    WPGenius.AutoPublish = {
        init: function () {
            this.statusBox = $('#w2p-scheduled-task-status');
            if (this.statusBox.length) {
                this.refreshScheduledStatus();
                setInterval(this.refreshScheduledStatus.bind(this), 20000);
            }
        },

        refreshScheduledStatus: function () {
            if (!window.w2pAutoPublishParams || !w2pAutoPublishParams.nonce) return;

            $.ajax({
                url: w2pAutoPublishParams.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_auto_publish_get_stats',
                    nonce: w2pAutoPublishParams.nonce
                },
                success: this.handleStatsResponse.bind(this)
            });
        },

        handleStatsResponse: function (response) {
            if (response.success) {
                var statusBox = this.statusBox;
                var statusDetail = statusBox.find('.status-detail');

                if (response.data.active_lock === 'scheduled' && response.data.scheduled_status) {
                    var status = response.data.scheduled_status;
                    statusBox.fadeIn();
                    statusDetail.html(
                        (w2pAutoPublishParams.l10n.processing || 'Currently processing Post ID:') +
                        ' <strong>' + status.post_id + '</strong> - ' + status.title +
                        (status.image_progress ? ' <span style="display:block;margin-top:5px;font-style:italic;color:#666;">' + status.image_progress + '</span>' : '')
                    );
                } else {
                    statusBox.fadeOut();
                }

                // Trigger custom event
                $(document).trigger('w2p_auto_publish_stats_refreshed', [response.data]);
            }
        }
    };

    // ==============================
    // 剪贴板图片上传模块 (Clipboard Upload)
    // ==============================
    WPGenius.ClipboardUpload = {
        isEnabled: true,
        isUploading: false,

        init: function () {
            // Load settings
            if (window.w2pClipboardParams && w2pClipboardParams.settings) {
                this.isEnabled = w2pClipboardParams.settings.enabled !== false;
            }

            this.initGutenberg();
            this.initMediaLibrary();
            this.initTinyMCE();
        },

        initTinyMCE: function () {
            var self = this;

            if (typeof tinymce !== 'undefined') {
                tinymce.PluginManager.add('w2p_clipboard_upload', function (editor, url) {
                    // Add toggle button
                    editor.addButton('w2p_clipboard_toggle', {
                        title: 'Enable Clipboard Image Upload',
                        icon: 'w2p_clipboard_toggle',
                        onclick: function () {
                            self.isEnabled = !self.isEnabled;
                            this.active(self.isEnabled);

                            var msg = self.isEnabled ? 'Clipboard upload enabled' : 'Clipboard upload disabled';
                            editor.notificationManager.open({
                                text: msg,
                                type: 'info',
                                timeout: 2000
                            });
                        },
                        onPostRender: function () {
                            this.active(self.isEnabled);
                        }
                    });

                    // Listen for paste events
                    editor.on('paste', function (e) {
                        if (!self.isEnabled) return;

                        var items = (e.clipboardData || e.originalEvent.clipboardData).items;
                        for (var i = 0; i < items.length; i++) {
                            if (items[i].type.indexOf('image') !== -1) {
                                var blob = items[i].getAsFile();
                                self.handleImagePaste(blob, function (url) {
                                    editor.execCommand('mceInsertContent', false, '<img src="' + url + '" />');
                                });
                                e.preventDefault();
                            }
                        }
                    });
                });
            }
        },

        initGutenberg: function () {
            var self = this;

            $(document).on('paste', '.editor-styles-wrapper', function (e) {
                if (!self.isEnabled) return;

                var clipboardData = e.originalEvent.clipboardData;
                if (!clipboardData || !clipboardData.items) return;

                for (var i = 0; i < clipboardData.items.length; i++) {
                    var item = clipboardData.items[i];
                    if (item.type.indexOf('image') !== -1) {
                        var blob = item.getAsFile();
                        self.handleImagePaste(blob, function (url) {
                            if (typeof wp !== 'undefined' && wp.blocks) {
                                var block = wp.blocks.createBlock('core/image', { url: url });
                                wp.data.dispatch('core/block-editor').insertBlocks(block);
                            }
                        });
                        e.preventDefault();
                    }
                }
            });
        },

        initMediaLibrary: function () {
            var self = this;

            if ($('body').hasClass('upload-php') || $('.media-frame').length > 0) {
                $(document).on('paste', function (e) {
                    if ($(e.target).is('input, textarea, [contenteditable]')) {
                        return;
                    }

                    var items = (e.originalEvent.clipboardData || e.clipboardData).items;
                    for (var i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            var blob = items[i].getAsFile();
                            self.handleImagePaste(blob, function (url) {
                                if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                                    var view = wp.media.frame.content.get();
                                    if (view.collection) {
                                        view.collection.props.set({ ignore: (+ new Date()) });
                                    }
                                } else {
                                    location.reload();
                                }
                            });
                        }
                    }
                });
            }
        },

        handleImagePaste: function (blob, callback) {
            var self = this;
            var reader = new FileReader();

            reader.onload = function (event) {
                var base64Data = event.target.result;
                var postId = $('#post_ID').val() || 0;

                self.isUploading = true;
                // console.log('Uploading clipboard image...');

                $.ajax({
                    url: w2pClipboardParams.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_clipboard_upload',
                        nonce: w2pClipboardParams.nonce,
                        image_data: base64Data,
                        post_id: postId
                    },
                    success: function (response) {
                        self.isUploading = false;
                        if (response.success) {
                            if (callback) callback(response.data.url);
                        } else {
                            alert(w2pClipboardParams.l10n.error + ': ' + response.data);
                        }
                    },
                    error: function () {
                        self.isUploading = false;
                        alert(w2pClipboardParams.l10n.error);
                    }
                });
            };

            reader.readAsDataURL(blob);
        }
    };

    // ==============================
    // 媒体加速模块 (Media Turbo)
    // ==============================
    WPGenius.MediaTurbo = {
        allIds: [],
        previewItems: [],
        currentIndex: 0,
        isRunning: false,
        stopRequested: false,
        currentXHR: null,
        stats: { success: 0, skipped: 0, error: 0, affected: 0, deleted: 0 },

        init: function () {
            $('#w2p-scan-media').on('click', this.handleScan.bind(this));
            $('#w2p-start-bulk').on('click', this.startBulkConversion.bind(this));
            $('#w2p-stop-bulk').on('click', this.stopProcessing.bind(this));
            $('#w2p-reset-processed').on('click', this.resetProcessed.bind(this));

            // Handle scan mode toggle
            $('input[name="w2p_media_turbo_settings[scan_mode]"]').on('change', function () {
                if ($(this).val() === 'posts') {
                    $('#w2p-posts-scan-options').fadeIn();
                } else {
                    $('#w2p-posts-scan-options').fadeOut();
                }
            });
        },

        handleScan: function (e) {
            var $btn = $(e.currentTarget);
            var scanLimit = parseInt($('#w2p-scan-limit').val()) || 100;

            $btn.prop('disabled', true).text('Scanning...');

            $.post(w2pMediaTurbo.ajax_url, {
                action: 'w2p_media_turbo_get_stats',
                nonce: w2pMediaTurbo.nonce,
                limit: scanLimit
            }, this.handleScanResponse.bind(this))
                .fail(this.handleScanError.bind(this, $btn));
        },

        handleScanResponse: function (response) {
            var $btn = $('#w2p-scan-media');

            if (response.success) {
                this.allIds = response.data.allIds || [];
                this.previewItems = response.data.allIds || []; // 显示所有找到的图片，不限制50条

                this.renderScanResults();

                // 在顶部显示当前捕获到的文件数量
                var statusText = 'Found ' + this.allIds.length + ' images ready for conversion.';
                $('#w2p-bulk-status-detailed').html('<strong style="color:#10a754;">' + statusText + '</strong>');

                if (this.allIds.length > 0) {
                    $('#w2p-start-bulk').fadeIn();
                    $('#w2p-scan-results-wrapper').fadeIn();
                } else {
                    alert('No images found that need conversion.');
                }
            } else {
                alert('Scan failed: ' + (response.data || 'Unknown error'));
            }
            $btn.prop('disabled', false).text('Scan Media Library');
        },

        handleScanError: function ($btn) {
            alert('Network error or server-side failure during scan.');
            $btn.prop('disabled', false).text('Scan Media Library');
        },

        renderScanResults: function () {
            var $container = $('#w2p-scan-items');
            $container.empty();

            this.previewItems.forEach((item) => {
                $container.append(this.createRowHtml(item));
            });
        },

        createRowHtml: function (item, statusText = 'Pending', statusClass = 'pending', timeText = '') {
            var thumb = item.thumbUrl ?
                '<img src="' + item.thumbUrl + '" class="w2p-item-thumb" />' :
                '<div class="w2p-item-thumb" style="display:flex;align-items:center;justify-content:center;background:#eee;color:#999;font-size:10px;">No Img</div>';
            var fileName = item.fileName || 'ID: ' + item.id;
            var fileSize = item.fileSize ? ' <small>(' + item.fileSize + ' KB)</small>' : '';
            var association = item.parentUrl ?
                '<small>Post: <a href="' + item.parentUrl + '" target="_blank">' + item.parentTitle + '</a></small>' :
                '<small>Orphaned image</small>';

            return '<tr id="w2p-item-' + item.id + '">' +
                '<td>' + thumb + '</td>' +
                '<td><div class="w2p-item-info"><strong>' + fileName + fileSize + '</strong>' + association + '</div></td>' +
                '<td class="w2p-item-status"><span class="w2p-status-badge w2p-status-' + statusClass + '">' + statusText + '</span></td>' +
                '<td class="w2p-item-time"><small>' + timeText + '</small></td>' +
                '</tr>';
        },

        startBulkConversion: function (e) {
            if (this.isRunning) return;

            this.isRunning = true;
            this.stopRequested = false;

            $(e.currentTarget).prop('disabled', true).hide();
            $('#w2p-stop-bulk').fadeIn().prop('disabled', false).text('Stop');
            $('#w2p-scan-media').prop('disabled', true);
            $('#w2p-bulk-progress-wrapper').fadeIn();

            this.currentIndex = 0;
            this.stats = { success: 0, skipped: 0, error: 0, affected: 0, deleted: 0 };
            this.processNextBatch();
        },

        stopProcessing: function (e) {
            if (!this.isRunning) return;

            this.stopRequested = true;
            $(e.currentTarget).prop('disabled', true).text('Stopping...');

            if (this.currentXHR) {
                this.currentXHR.abort();
            }

            setTimeout(() => {
                if (this.isRunning) this.finishConversion('Stopped by user (Forced).');
            }, 2000);
        },

        processNextBatch: function () {
            if (this.stopRequested) {
                this.finishConversion('Stopped by user.');
                return;
            }

            if (this.currentIndex >= this.allIds.length) {
                this.finishConversion('Bulk Optimization Complete!');
                return;
            }

            var batchSize = parseInt($('#w2p-batch-size').val()) || 10;
            var chunk = this.allIds.slice(this.currentIndex, this.currentIndex + batchSize);

            $('#w2p-bulk-status-detailed').html(
                'Processing: ' + (this.currentIndex + 1) + ' to ' + Math.min(this.currentIndex + batchSize, this.allIds.length) + ' of ' + this.allIds.length + ' | ' +
                '<span style="color:#10a754;">Success: ' + this.stats.success + '</span> | ' +
                '<span style="color:#d94f1a;">Posts Updated: ' + this.stats.affected + '</span> | ' +
                '<span style="color:#0073aa;">Files Deleted: ' + this.stats.deleted + '</span>'
            );

            chunk.forEach((id, index) => {
                var $row = $('#w2p-item-' + id);
                if ($row.length === 0) {
                    var newItem = { id: id, fileName: 'Image ' + id };
                    $('#w2p-scan-items').prepend(this.createRowHtml(newItem, 'Processing...', 'processing'));
                    $row = $('#w2p-item-' + id);
                } else {
                    $row.find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-processing">Processing...</span>');
                }
                if (index === 0) {
                    $row[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });

            this.currentXHR = $.post(w2pMediaTurbo.ajax_url, {
                action: 'w2p_media_turbo_batch_convert',
                nonce: w2pMediaTurbo.nonce,
                ids: chunk
            }, this.handleBatchResponse.bind(this, chunk))
                .fail(this.handleBatchError.bind(this, chunk));
        },

        handleBatchResponse: function (chunk, response) {
            this.currentXHR = null;

            if (response.success && Array.isArray(response.data)) {
                response.data.forEach(res => {
                    var $itemRow = $('#w2p-item-' + res.id);
                    var $itemStatus = $itemRow.find('.w2p-item-status');
                    var $itemTime = $itemRow.find('.w2p-item-time');
                    var now = new Date();
                    var timeStr = now.getHours().toString().padStart(2, '0') + ':' +
                        now.getMinutes().toString().padStart(2, '0') + ':' +
                        now.getSeconds().toString().padStart(2, '0');

                    if (res.status === 'success') {
                        this.stats.success++;
                        this.stats.affected += res.affected || 0;
                        this.stats.deleted += res.deleted || 0;
                        var statusMsg = 'OK';
                        if (res.affected > 0) {
                            statusMsg += ' (' + res.affected + ' posts)';
                        }
                        if (res.deleted > 0) {
                            statusMsg += ' [' + res.deleted + ' files deleted]';
                        }
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-success">' + statusMsg + '</span>');
                        $itemTime.html('<small>' + timeStr + '</small>');

                        // 实时更新统计信息
                        this.updateOverallProgress();
                    } else if (res.status === 'skipped') {
                        this.stats.skipped++;
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-skipped">Skipped</span>');
                        $itemTime.html('<small>' + timeStr + '</small>');
                        this.updateOverallProgress();
                    } else {
                        this.stats.error++;
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-error">Fail</span>');
                        $itemTime.html('<small>' + timeStr + '</small>');
                        this.updateOverallProgress();
                    }
                });
            } else {
                this.stats.error += chunk.length;
                var now = new Date();
                var timeStr = now.getHours().toString().padStart(2, '0') + ':' +
                    now.getMinutes().toString().padStart(2, '0') + ':' +
                    now.getSeconds().toString().padStart(2, '0');
                chunk.forEach(id => {
                    $('#w2p-item-' + id).find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-error">Batch Error</span>');
                    $('#w2p-item-' + id).find('.w2p-item-time').html('<small>' + timeStr + '</small>');
                });
                this.updateOverallProgress();
            }

            this.currentIndex += chunk.length;

            if (!this.stopRequested) {
                setTimeout(this.processNextBatch.bind(this), 200);
            } else {
                this.finishConversion('Stopped by user.');
            }
        },

        handleBatchError: function (chunk, xhr, textStatus) {
            this.currentXHR = null;

            if (textStatus === 'abort') {
                this.finishConversion('Stopped by user.');
                return;
            }

            this.stats.error += chunk.length;
            chunk.forEach(id => {
                $('#w2p-item-' + id).find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-error">Batch Fail</span>');
            });

            this.currentIndex += chunk.length;
            this.updateOverallProgress();

            if (!this.stopRequested) {
                setTimeout(this.processNextBatch.bind(this), 200);
            } else {
                this.finishConversion('Stopped by user.');
            }
        },

        updateOverallProgress: function () {
            var processed = Math.min(this.currentIndex, this.allIds.length);
            var progress = (processed / this.allIds.length) * 100;
            $('#w2p-bulk-progress-bar').css('width', progress + '%');
            $('#w2p-bulk-status-detailed').html(
                'Processed: ' + processed + ' / ' + this.allIds.length + ' | ' +
                '<span style="color:#10a754;">Success: ' + this.stats.success + '</span> | ' +
                '<span style="color:#d94f1a;">Posts Updated: ' + this.stats.affected + '</span> | ' +
                '<span style="color:#0073aa;">Files Deleted: ' + this.stats.deleted + '</span>'
            );
        },

        finishConversion: function (message) {
            this.isRunning = false;
            this.currentXHR = null;
            $('#w2p-stop-bulk').hide();
            $('#w2p-start-bulk').show().text('Optimize Again').prop('disabled', false);
            $('#w2p-scan-media').prop('disabled', false);
            // 去除弹窗提示，仅在状态栏显示
            $('#w2p-bulk-status-detailed').html('<strong style="color:#10a754;">' + message + '</strong>');
        },

        resetProcessed: function () {
            if (!confirm('Are you sure you want to reset the processed posts list? This will allow you to re-process all posts.')) {
                return;
            }

            var $btn = $('#w2p-reset-processed');
            $btn.prop('disabled', true).text('Resetting...');

            $.post(w2pMediaTurbo.ajax_url, {
                action: 'w2p_media_turbo_reset_processed',
                nonce: w2pMediaTurbo.nonce
            }, function (response) {
                if (response.success) {
                    alert('Processed posts list has been reset!');
                    location.reload();
                } else {
                    alert('Failed to reset: ' + (response.data || 'Unknown error'));
                    $btn.prop('disabled', false).text('Reset Processed Posts');
                }
            }).fail(function () {
                alert('Network error occurred');
                $btn.prop('disabled', false).text('Reset Processed Posts');
            });
        }
    };

    // ==============================
    // 系统健康模块 (System Health)
    // ==============================
    WPGenius.SystemHealth = {
        init: function () {
            $('.w2p-health-action').on('click', this.handleHealthAction.bind(this));
            $('#w2p-health-scan-btn').on('click', this.handleScanAction.bind(this));
        },

        handleScanAction: function (e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var $counts = $('.w2p-health-count');

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update w2p-spin" style="margin-top: 4px; margin-right: 4px;"></span> Scanning...');
            $counts.text('...');

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_get_stats',
                nonce: w2pSystemHealth.nonce
            }, function (response) {
                if (response.success) {
                    $.each(response.data, function (key, count) {
                        $('.w2p-health-card[data-type="' + key + '"] .w2p-health-count').text(count);
                    });
                    WPGenius.SystemHealth.showMessage('Scan complete.', 'success');
                } else {
                    WPGenius.SystemHealth.showMessage('Scan failed: ' + (response.data || 'Unknown error'), 'error');
                    $counts.text('-');
                }
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top: 4px; margin-right: 4px;"></span> Scan System Status');
            }).fail(function () {
                WPGenius.SystemHealth.showMessage('Network error during scan.', 'error');
                $counts.text('-');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top: 4px; margin-right: 4px;"></span> Scan System Status');
            });
        },

        handleHealthAction: function (e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var action = $btn.data('action');
            var $card = $btn.closest('.w2p-health-card');
            var $count = $card.find('.w2p-health-count');
            var originalText = $btn.text();

            if (!action) {
                console.error('System Health: Missing action for button', $btn);
                return;
            }

            $btn.prop('disabled', true).text(w2pSystemHealth.cleaning);

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_clean',
                cleanup_type: action,
                nonce: w2pSystemHealth.nonce
            }, this.handleHealthResponse.bind(this, $btn, $count, originalText))
                .fail(this.handleHealthError.bind(this, $btn, originalText));
        },

        handleHealthResponse: function ($btn, $count, originalText, response) {
            if (response.success) {
                $count.text('0');
                this.showMessage(response.data.message, 'success');
            } else {
                this.showMessage(response.data.message || 'Error occurred', 'error');
            }
            $btn.prop('disabled', false).text(originalText);
        },

        handleHealthError: function ($btn, originalText) {
            this.showMessage('Network error', 'error');
            $btn.prop('disabled', false).text(originalText);
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
        },

        getButtonText: function (action) {
            switch (action) {
                case 'revisions': return 'Clean Revisions';
                case 'auto_drafts': return 'Clean Auto Drafts';
                case 'orphaned_meta': return 'Clean Orphaned Meta';
                case 'transients': return 'Clean Transients';
                default: return 'Clean Now';
            }
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

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update w2p-spin" style="margin-top: 4px; margin-right: 4px;"></span> ' + w2pSystemHealth.scanning);

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_scan_links',
                category_id: categoryId,
                nonce: w2pSystemHealth.nonce
            }, (response) => {
                if (response.success) {
                    this.allPosts = response.data || [];
                    this.renderResults();
                    $('#w2p-image-link-status').text('Found ' + this.allPosts.length + ' posts with linked images.');
                    $('#w2p-image-link-results-wrapper').fadeIn();
                    $('#w2p-image-link-execute-btn').prop('disabled', this.allPosts.length === 0);
                } else {
                    alert('Scan failed: ' + (response.data.message || 'Unknown error'));
                }
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top: 4px; margin-right: 4px;"></span> Scan for Linked Images');
            }).fail(() => {
                alert('Network error during scan.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="margin-top: 4px; margin-right: 4px;"></span> Scan for Linked Images');
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

            if (!confirm('Are you sure you want to remove links from images in ' + this.allPosts.length + ' posts?')) {
                return;
            }

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
            $firstRow[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });

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
            var $msg = $('#w2p-image-link-notice');
            $msg.removeClass('w2p-notice-success w2p-notice-error')
                .addClass('w2p-notice-' + type)
                .text(msg)
                .fadeIn();
        }
    };

    // ==============================
    // 重复文章清理模块 (Duplicate Post Cleaner) - Enhanced
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
            // Check if required variables are available
            if (typeof w2pSystemHealth === 'undefined') {
                console.error('DuplicateCleaner: w2pSystemHealth is not defined');
                return;
            }
            
            if (!w2pSystemHealth.ajax_url || !w2pSystemHealth.nonce) {
                console.error('DuplicateCleaner: Missing ajax_url or nonce', w2pSystemHealth);
                return;
            }
            
            console.log('DuplicateCleaner initialized with:', {
                ajax_url: w2pSystemHealth.ajax_url,
                nonce: w2pSystemHealth.nonce ? 'present' : 'missing'
            });
            
            $('#w2p-duplicate-scan-btn').on('click', this.handleScan.bind(this));
            $('#w2p-duplicate-clear-btn').on('click', this.handleClearSelection.bind(this));
            $('#w2p-duplicate-clean-btn').on('click', this.handleCleanAll.bind(this));
            $(document).on('click', '.w2p-clean-group-btn', this.handleCleanGroup.bind(this));
            $(document).on('change', '.w2p-duplicate-checkbox', this.handleCheckboxChange.bind(this));
        },

        /**
         * Truncate long slug for display
         * @param {string} slug - The slug to truncate
         * @param {number} maxLength - Maximum length before truncation
         * @returns {string} Truncated slug
         */
        truncateSlug: function (slug, maxLength) {
            maxLength = maxLength || 40;
            if (slug.length <= maxLength) {
                return slug;
            }
            
            var headLength = Math.floor(maxLength * 0.4);
            var tailLength = Math.floor(maxLength * 0.4);
            var head = slug.substring(0, headLength);
            var tail = slug.substring(slug.length - tailLength);
            
            return head + '...' + tail;
        },

        getTotalDuplicates: function () {
            var total = 0;
            this.duplicateGroups.forEach((group) => {
                // Count all posts except the recommended one (which defaults to not selected)
                group.posts.forEach((post) => {
                    if (post.selected) {
                        total++;
                    }
                });
            });
            return total;
        },

        renderResults: function () {
            var $container = $('#w2p-duplicate-groups');
            $container.empty();

            if (this.duplicateGroups.length === 0) {
                // Use empty state template
                var emptyTemplate = document.getElementById('w2p-duplicate-empty-template');
                $container.append($(emptyTemplate.content.cloneNode(true)));
                return;
            }

            var groupTemplate = document.getElementById('w2p-duplicate-group-template');
            var postTemplate = document.getElementById('w2p-duplicate-post-template');

            this.duplicateGroups.forEach((group, groupIndex) => {
                // Clone group template
                var $group = $(groupTemplate.content.cloneNode(true));
                
                // Set group title
                $group.find('.group-title').text(group.group_title);
                
                var $tbody = $group.find('.duplicate-posts-body');

                // Render each post in the group
                group.posts.forEach((post, postIndex) => {
                    var $postRow = $(postTemplate.content.cloneNode(true));
                    var $row = $postRow.find('.duplicate-post-row');
                    
                    // Set row data attributes
                    $row.attr('data-group', groupIndex);
                    $row.attr('data-post', postIndex);
                    
                    // Set checkbox - all checkboxes are enabled and clickable
                    var $checkbox = $postRow.find('.w2p-duplicate-checkbox');
                    $checkbox.attr('data-post-id', post.id);
                    // Set checked state based on 'selected' flag
                    if (post.selected) {
                        $checkbox.prop('checked', true);
                    }
                    // No checkbox is disabled - user can freely choose
                    
                    // Set post data
                    $postRow.find('.post-id').text(post.id);
                    
                    // Set post title (with link if available)
                    var $titleCell = $postRow.find('.post-title');
                    if (post.edit_url) {
                        var $link = $('<a></a>').attr({
                            'href': post.edit_url,
                            'target': '_blank'
                        }).text(post.title);
                        $titleCell.empty().append($link);
                    } else {
                        $titleCell.text(post.title);
                    }
                    
                    // Set truncated slug with full slug in title attribute
                    var truncatedSlug = this.truncateSlug(post.slug);
                    $postRow.find('.post-slug code').text(truncatedSlug).attr('title', post.slug);
                    
                    $postRow.find('.post-date').text(post.date);
                    
                    // Show status badge - recommended keep or to delete
                    var $statusCell = $postRow.find('.post-status');
                    if (post.recommended_keep) {
                        $statusCell.find('.status-keep').show();
                        $statusCell.find('.status-delete').hide();
                    } else {
                        $statusCell.find('.status-keep').hide();
                        $statusCell.find('.status-delete').show();
                    }
                    
                    $tbody.append($postRow);
                });

                $container.append($group);
            });
        },

        handleCheckboxChange: function (e) {
            var $checkbox = $(e.currentTarget);
            var postId = $checkbox.data('post-id');
            var $row = $checkbox.closest('tr');
            var groupIndex = $row.data('group');
            var postIndex = $row.data('post');

            if (this.duplicateGroups[groupIndex] && this.duplicateGroups[groupIndex].posts[postIndex]) {
                this.duplicateGroups[groupIndex].posts[postIndex].selected = $checkbox.is(':checked');
            }

            this.updateStatus();
        },

        updateStatus: function () {
            var count = 0;
            // Count all checked checkboxes
            $('.w2p-duplicate-checkbox:checked').each(function() {
                count++;
            });
            
            var totalGroups = this.duplicateGroups.length;
            $('#w2p-duplicate-status').text('Found ' + totalGroups + ' duplicate groups (' + count + ' posts selected for deletion).');
        },

        /**
         * Handle clear selection button
         */
        handleClearSelection: function () {
            $('.w2p-duplicate-checkbox:checked').prop('checked', false);
            this.updateStatus();
        },

        /**
         * Handle clean all selected posts
         */
        handleCleanAll: function (e) {
            var selectedIds = [];
            // Collect all checked checkboxes
            $('.w2p-duplicate-checkbox:checked').each(function() {
                selectedIds.push($(this).data('post-id'));
            });

            if (selectedIds.length === 0) {
                alert('Please select at least one post to delete.');
                return;
            }

            if (!confirm('Are you sure you want to move ' + selectedIds.length + ' duplicate posts to trash?')) {
                return;
            }

            var $btn = $(e.currentTarget);
            // Set loading state
            $btn.prop('disabled', true).addClass('is-loading');
            $btn.find('.btn-text').text($btn.data('text-cleaning'));

            this.cleanPosts(selectedIds, $btn, null);
        },

        /**
         * Handle clean single group
         */
        handleCleanGroup: function (e) {
            var $btn = $(e.currentTarget);
            var $group = $btn.closest('.w2p-duplicate-group');
            var selectedIds = [];
            
            // Collect checked checkboxes in this group
            $group.find('.w2p-duplicate-checkbox:checked').each(function() {
                selectedIds.push($(this).data('post-id'));
            });

            if (selectedIds.length === 0) {
                alert('Please select at least one post to delete in this group.');
                return;
            }

            // Set loading state
            $btn.prop('disabled', true).addClass('is-loading');
            $btn.find('.btn-text').text($btn.data('text-cleaning'));

            this.cleanPosts(selectedIds, $btn, $group);
        },

        /**
         * Clean posts using improved bulk processing (shared logic)
         */
        cleanPosts: function (postIds, $btn, $group) {
            console.log('W2P DuplicateCleaner: cleanPosts called');
            console.log('W2P DuplicateCleaner: postIds count:', postIds.length);
            
            if (this.isProcessing) {
                console.log('W2P DuplicateCleaner: Already processing, skipping');
                return;
            }
            
            this.isProcessing = true;
            
            // Fix: Split into smaller batches to avoid max_input_vars limit
            var batchSize = 50; // Reduced from 25 to be safer
            var batches = [];
            
            for (var i = 0; i < postIds.length; i += batchSize) {
                batches.push(postIds.slice(i, i + batchSize));
            }
            
            console.log('W2P DuplicateCleaner: Split into', batches.length, 'batches of max', batchSize, 'items each');
            
            var processedBatches = 0;
            var totalProcessed = 0;
            var failedBatches = 0;
            
            var processBatch = function(batchIndex) {
                if (batchIndex >= batches.length) {
                    // All batches processed
                    console.log('W2P DuplicateCleaner: All batches processed. Success:', totalProcessed, 'Failed:', failedBatches);
                    
                    if (failedBatches === 0) {
                        // Success - show success message and refresh
                        WPGenius.DuplicateCleaner.isProcessing = false;
                        
                        if ($group) {
                            // Single group cleanup
                            $btn.prop('disabled', true).removeClass('is-loading').addClass('button-primary');
                            $btn.find('.btn-text').text($btn.data('text-done'));
                            $btn.find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-yes');
                            
                            setTimeout(() => {
                                $group.fadeOut(300, function() {
                                    $(this).remove();
                                    var remaining = $('.w2p-duplicate-group').length;
                                    if (remaining === 0) {
                                        $('#w2p-duplicate-results-wrapper').fadeOut(300, function() {
                                            $(this).addClass('w2p-hidden');
                                        });
                                    }
                                });
                            }, 1500);
                        } else {
                            // Batch cleanup
                            WPGenius.DuplicateCleaner.showNotice('Successfully processed ' + totalProcessed + ' posts!', 'success');
                            setTimeout(() => {
                                $('#w2p-duplicate-scan-btn').click();
                            }, 1500);
                            $btn.prop('disabled', false).removeClass('is-loading');
                            $btn.find('.btn-text').text($btn.data('text-default'));
                        }
                    } else {
                        // Some batches failed
                        WPGenius.DuplicateCleaner.isProcessing = false;
                        WPGenius.DuplicateCleaner.showNotice('Partially completed: ' + totalProcessed + ' succeeded, ' + failedBatches + ' failed', 'error');
                        $btn.prop('disabled', false).removeClass('is-loading');
                        $btn.find('.btn-text').text($btn.data('text-default'));
                    }
                    return;
                }
                
                var currentBatch = batches[batchIndex];
                console.log('W2P DuplicateCleaner: Processing batch', (batchIndex + 1), 'of', batches.length, 'with', currentBatch.length, 'items');
                
                // Use $.ajax with timeout instead of $.post for better control
                $.ajax({
                    url: w2pSystemHealth.ajax_url,
                    type: 'POST',
                    timeout: 30000, // 30 second timeout per batch
                    data: {
                        action: 'w2p_system_health_trash_duplicates',
                        post_ids: currentBatch,
                        nonce: w2pSystemHealth.nonce
                    },
                    success: (response) => {
                        console.log('W2P DuplicateCleaner: Batch', (batchIndex + 1), 'response:', response);
                        
                        processedBatches++;
                        
                        if (response && response.success) {
                            var processedCount = response.data && response.data.count ? response.data.count : 0;
                            totalProcessed += processedCount;
                            console.log('W2P DuplicateCleaner: Batch', (batchIndex + 1), 'success, processed:', processedCount, 'total:', totalProcessed);
                            
                            // Update UI for this batch
                            var batchStart = batchIndex * batchSize + 1;
                            var batchEnd = Math.min((batchIndex + 1) * batchSize, postIds.length);
                            WPGenius.DuplicateCleaner.showNotice('Processed batch ' + (batchIndex + 1) + ' of ' + batches.length + ' (' + batchStart + '-' + batchEnd + ' of ' + postIds.length + ' posts)', 'success');
                            
                        } else {
                            failedBatches++;
                            var errorMsg = response && response.data && response.data.message ? response.data.message : 'Unknown error';
                            console.error('W2P DuplicateCleaner: Batch', (batchIndex + 1), 'failed:', errorMsg);
                            
                            // Show error but continue with next batch
                            WPGenius.DuplicateCleaner.showNotice('Batch ' + (batchIndex + 1) + ' failed: ' + errorMsg, 'error');
                        }
                        
                        // Process next batch after a small delay
                        setTimeout(() => {
                            processBatch(batchIndex + 1);
                        }, 500); // 0.5 second delay between batches
                        
                    },
                    error: (xhr, status, error) => {
                        console.error('W2P DuplicateCleaner: Batch', (batchIndex + 1), 'AJAX failed:', { xhr: xhr, status: status, error: error });
                        processedBatches++;
                        failedBatches++;
                        
                        // Try to extract error message from response
                        var errorMsg = 'Network error';
                        if (xhr.responseText) {
                            try {
                                var jsonResponse = JSON.parse(xhr.responseText);
                                if (jsonResponse && jsonResponse.data) {
                                    errorMsg = jsonResponse.data.message || jsonResponse.data;
                                }
                            } catch (e) {
                                errorMsg = 'Server error: ' + xhr.status;
                            }
                        }
                        
                        console.error('W2P DuplicateCleaner: Batch', (batchIndex + 1), 'detailed error:', errorMsg);
                        
                        // Show error but continue with next batch
                        WPGenius.DuplicateCleaner.showNotice('Batch ' + (batchIndex + 1) + ' network error: ' + errorMsg, 'error');
                        
                        // Continue with next batch even if this one failed
                        setTimeout(() => {
                            processBatch(batchIndex + 1);
                        }, 500);
                    }
                });
            };
            
            // Start processing batches
            processBatch(0);
        },

        showNotice: function (msg, type) {
            var $msg = $('#w2p-duplicate-notice');
            $msg.removeClass('w2p-notice-success w2p-notice-error')
                .addClass('w2p-notice-' + type)
                .text(msg)
                .removeClass('w2p-hidden')
                .fadeIn();
            
            setTimeout(function() {
                $msg.fadeOut(300, function() {
                    $(this).addClass('w2p-hidden');
                });
            }, 5000);
        },

        /**
         * Original scan method (kept for compatibility)
         */
        handleScan: function (e) {
            var $btn = $(e.currentTarget);
            var categoryId = $('#w2p-duplicate-category').val();

            // Set loading state
            $btn.prop('disabled', true).addClass('is-loading');
            $btn.find('.btn-text').text($btn.data('text-scanning'));

            $.post(w2pSystemHealth.ajax_url, {
                action: 'w2p_system_health_scan_duplicates',
                category_id: categoryId,
                nonce: w2pSystemHealth.nonce
            }, (response) => {
                console.log('Scan response:', response);
                
                if (response && response.success) {
                    // Ensure response.data is an array
                    this.duplicateGroups = Array.isArray(response.data) ? response.data : [];
                    console.log('Duplicate groups found:', this.duplicateGroups.length);
                    
                    this.renderResults();
                    var totalDuplicates = this.getTotalDuplicates();
                    $('#w2p-duplicate-status').text('Found ' + this.duplicateGroups.length + ' duplicate groups (' + totalDuplicates + ' posts to clean).');
                    $('#w2p-duplicate-results-wrapper').removeClass('w2p-hidden').fadeIn();
                    $('#w2p-duplicate-clean-btn').prop('disabled', this.duplicateGroups.length === 0);
                } else {
                    console.error('Scan failed:', response);
                    var errorMsg = 'Unknown error';
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (response.data.message) {
                            errorMsg = response.data.message;
                        }
                    }
                    alert('Scan failed: ' + errorMsg);
                }
                // Reset button state
                $btn.prop('disabled', false).removeClass('is-loading');
                $btn.find('.btn-text').text($btn.data('text-default'));
            }, 'json').fail((xhr, status, error) => {
                console.error('Scan AJAX error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                var errorMsg = 'Network error';
                if (xhr.responseText) {
                    try {
                        var jsonResponse = JSON.parse(xhr.responseText);
                        if (jsonResponse && jsonResponse.data) {
                            errorMsg = jsonResponse.data.message || jsonResponse.data;
                        }
                    } catch (e) {
                        errorMsg = 'Server error: ' + xhr.status;
                    }
                }
                
                alert('Scan failed: ' + errorMsg);
                
                // Reset button state
                $btn.prop('disabled', false).removeClass('is-loading');
                $btn.find('.btn-text').text($btn.data('text-default'));
            });
        }
    };

    // ==============================
    // 智能自动上传图片模块 (Smart Auto Upload)
    // ==============================
    WPGenius.SmartAutoUpload = {
        isProcessing: false,
        checkInterval: null,
        originalButton: null,
        processId: '',

        init: function () {
            this.bindEvents();
            this.hookIntoSave();
        },

        bindEvents: function () {
            $('#w2p-smart-aui-close-btn').on('click', this.hide.bind(this));
            $('#w2p-smart-aui-cancel-btn').on('click', this.cancel.bind(this));
            $('#w2p-smart-aui-backdrop').on('click', (e) => {
                if (e.target === this && !this.isProcessing) {
                    this.hide();
                }
            });
        },

        hookIntoSave: function () {
            // Intercept publish/update button clicks
            $(document).on('click', '#publish, .editor-post-publish-button, .editor-post-publish-panel__toggle', (e) => {
                if (this.isProcessing || $(e.target).data('smart-aui-processed')) {
                    return;
                }

                var content = this.getEditorContent();
                var externalImages = this.findExternalImages(content);

                if (externalImages.length > 0) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    this.originalButton = $(e.target);
                    this.startAsyncProcessing(content, externalImages);
                    return false;
                }
            });
        },

        startAsyncProcessing: function (content, images) {
            this.show();
            this.updateStatus('正在进行分布式图片抓取...', true);
            this.updateStats(images.length, 0, 0, 0);

            this.processId = this.generateProcessId();

            var self = this;
            var postId = $('#post_ID').val();

            // Gutenberg ID fallback
            if (!postId && wp && wp.data && wp.data.select('core/editor')) {
                postId = wp.data.select('core/editor').getCurrentPostId();
            }

            var queue = images.slice();
            var total = images.length;
            var processed = 0;
            var success = 0;
            var failed = 0;

            var processNextBatch = function () {
                if (queue.length === 0 || !self.isProcessing) {
                    self.finalizeProcessing();
                    return;
                }

                var targetUrl = queue.shift();
                processed++;
                self.updateCurrentImage(targetUrl);
                self.updateStatus('正在抓取: ' + (targetUrl.length > 40 ? targetUrl.substring(0, 40) + '...' : targetUrl), true);

                $.ajax({
                    url: w2pSmartAuiParams.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_smart_aui_process_content',
                        nonce: w2pSmartAuiParams.nonce,
                        post_id: postId,
                        content: self.getEditorContent(),
                        target_url: targetUrl,
                        process_id: self.processId
                    },
                    success: function (response) {
                        if (response.success) {
                            success++;
                            if (response.data.processed_content) {
                                self.setEditorContent(response.data.processed_content);
                            }
                        } else {
                            failed++;
                        }
                        self.updateStats(total, success, failed, processed);
                        processNextBatch();
                    },
                    error: function () {
                        failed++;
                        self.updateStats(total, success, failed, processed);
                        processNextBatch();
                    }
                });
            };

            processNextBatch();
        },

        finalizeProcessing: function () {
            this.updateStatus('✅ 图片处理完成！正在保存文章...', false);

            var self = this;
            setTimeout(function () {
                self.hide();
                self.submitForm();
            }, 500);
        },

        submitForm: function () {
            // Mark button as processed
            if (this.originalButton) {
                this.originalButton.attr('data-smart-aui-processed', 'true');
                this.originalButton.data('smart-aui-processed', true);

                if (window.wp && window.wp.data && window.wp.data.dispatch && window.wp.data.dispatch('core/editor')) {
                    window.wp.data.dispatch('core/editor').savePost();
                } else {
                    this.originalButton[0].click();
                }
            } else {
                $('#post').submit();
            }
        },

        getEditorContent: function () {
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostContent();
            }
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                return tinyMCE.activeEditor.getContent();
            }
            var $content = $('#content');
            return $content.length > 0 ? $content.val() : null;
        },

        setEditorContent: function (content) {
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && window.wp.data.dispatch('core/editor')) {
                wp.data.dispatch('core/editor').editPost({ content: content });
                return;
            }
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                tinyMCE.activeEditor.setContent(content);
                return;
            }
            $('#content').val(content);
        },

        findExternalImages: function (content) {
            var images = [];
            var imageRegex = /<img[^>]+src=["']([^"']+)["'][^>]*>/gi;
            var match;
            var siteUrl = window.location.origin;

            while ((match = imageRegex.exec(content)) !== null) {
                var src = match[1];

                if (src.indexOf(siteUrl) === 0 || src.indexOf('/wp-content/') === 0) {
                    continue;
                }

                if (src.indexOf('data:') === 0) {
                    continue;
                }

                images.push(src);
            }

            return images;
        },

        generateProcessId: function () {
            return 'proc_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        show: function () {
            this.isProcessing = true;
            $('#w2p-smart-aui-backdrop').fadeIn(200);
        },

        hide: function () {
            this.isProcessing = false;
            $('#w2p-smart-aui-backdrop').fadeOut(200);
        },

        cancel: function () {
            if (confirm('确定要取消图片上传吗？')) {
                this.hide();
                this.isProcessing = false;
            }
        },

        updateStatus: function (text, processing) {
            var $status = $('.w2p-smart-aui-status-text');
            $status.text(text);

            if (processing) {
                $status.addClass('processing');
            } else {
                $status.removeClass('processing');
            }
        },

        updateStats: function (total, success, failed, processed) {
            $('#w2p-smart-aui-total').text(total);
            $('#w2p-smart-aui-success').text(success);
            $('#w2p-smart-aui-failed').text(failed);

            if (total > 0) {
                var percentage = (processed / total) * 100;
                $('.w2p-smart-aui-progress-fill').css('width', percentage + '%');
            }
        },

        updateCurrentImage: function (url) {
            var displayUrl = url.length > 60 ? url.substring(0, 60) + '...' : url;
            $('#w2p-smart-aui-current-url').text(displayUrl);

            var $previewImg = $('#w2p-smart-aui-preview-img');
            var $placeholder = $('.w2p-smart-aui-preview-placeholder');

            $previewImg.hide();
            $placeholder.show();

            var img = new Image();
            img.onload = function () {
                $previewImg.attr('src', url).show();
                $placeholder.hide();
            };
            img.src = url;
        }
    };

    // ==============================
    // 图片水印模块 (Image Watermark)
    // ==============================
    WPGenius.ImageWatermark = {
        init: function () {
            this.initMediaUploader();
            this.initConditions();
        },

        initMediaUploader: function () {
            var self = this;
            var frame;

            $('#iw_upload_image_button').on('click', function (e) {
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: 'Select Watermark Image',
                    button: { text: 'Use as watermark' },
                    multiple: false
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#iw_upload_image').val(attachment.id);
                    $('#previewImg_image').attr('src', attachment.url).show();
                    $('#iw_turn_off_image_button').prop('disabled', false);

                    if (attachment.width && attachment.height) {
                        $('#previewImageInfo').text('Original size: ' + attachment.width + ' px / ' + attachment.height + ' px');
                    } else {
                        $('#previewImageInfo').text('Image selected. Save changes to update details.');
                    }
                });

                frame.open();
            });

            $('#iw_turn_off_image_button').on('click', function (e) {
                e.preventDefault();
                $('#iw_upload_image').val('0');
                $('#previewImg_image').attr('src', '').hide();
                $('#previewImageInfo').text('Watermark has not been selected yet.');
                $(this).prop('disabled', true);
            });
        },

        initConditions: function () {
            // Watermark Size Toggles
            $('input[name="iw_options[watermark_image][watermark_size_type]"]').on('change', function () {
                var val = $(this).val();
                $('.iw-watermark-size-custom').toggle(val == '1');
                $('.iw-watermark-size-scaled').toggle(val == '2');
            });

            // Post Type Toggles
            $('input[name="iw_options[watermark_cpt_on]"]').on('change', function () {
                var val = $(this).val();
                $('#cpt-select').toggle(val === 'specific');
            });
        }
    };

    // ==============================
    // 初始化所有模块
    // ==============================
    $(document).ready(function () {
        // 初始化各个模块
        if (window.w2pAiParams) WPGenius.AIAssistant.init();
        if (window.w2pAutoPublishParams) WPGenius.AutoPublish.init();
        if (window.w2pClipboardParams) WPGenius.ClipboardUpload.init();
        if (window.w2pMediaTurbo) WPGenius.MediaTurbo.init();
        if (window.w2pSystemHealth) {
            WPGenius.SystemHealth.init();
            WPGenius.ImageLinkRemover.init();
            WPGenius.DuplicateCleaner.init();
        }
        // Smart Auto Upload is now handled by smart-auto-upload-progress-ui.js
        // if (window.w2pSmartAuiParams) WPGenius.SmartAutoUpload.init();
        if ($('.w2p-module-settings-panel').length > 0) WPGenius.ImageWatermark.init();

        // 全局事件处理
        WPGenius.initGlobalEvents();
    });

    // ==============================
    // 全局功能和工具
    // ==============================
    WPGenius.initGlobalEvents = function () {
        // Global Sub-tab switching logic
        $(document).on('click', '.w2p-sub-tab-link', function (e) {
            e.preventDefault();
            var $link = $(this);
            var tab = $link.data('tab');
            if (!tab) return;

            var $container = $link.closest('.w2p-sub-tabs');

            // Toggle active link within this container
            $container.find('.w2p-sub-tab-link').removeClass('active');
            $link.addClass('active');

            // Toggle active content within this container
            $container.find('.w2p-sub-tab-content').removeClass('active');
            $container.find('#w2p-tab-' + tab).addClass('active');
        });
        
        // Module-specific tab switching logic for frontend enhancement settings
        $(document).on('click', '.w2p-tab-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var tab = $btn.data('tab');
            var module = $btn.closest('.w2p-tabs').data('module');
            
            if (!tab || !module) return;
            
            // Toggle active button within this module
            $btn.siblings('.w2p-tab-btn').removeClass('active');
            $btn.addClass('active');
            
            // Toggle active pane within this module
            $btn.closest('.w2p-tabs').find('.w2p-tab-pane').removeClass('active');
            $btn.closest('.w2p-tabs').find('[data-pane="' + tab + '"]').addClass('active');
        });

        // 处理所有AJAX错误
        $(document).ajaxError(function (event, xhr, settings, thrownError) {
            console.error('WP Genius AJAX Error:', thrownError);
        });

        // 工具提示
        $('[title]').on('mouseenter', function () {
            var $this = $(this);
            var title = $this.attr('title');
            if (title) {
                // 这里可以添加自定义工具提示逻辑
            }
        });
    };

    // 导出到全局命名空间
    window.WPGenius = WPGenius;

})(jQuery);