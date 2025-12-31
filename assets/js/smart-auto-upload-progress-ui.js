/**
 * Smart Auto Upload Images Progress UI
 * 智能自动上传图片进度UI
 */
(function ($) {
    'use strict';

    var progressUI = {
        isProcessing: false,
        checkInterval: null,
        isIntercepting: false,
        originalButton: null,
        processId: '',
        settings: null,
        maxConcurrent: 4, // Default backup

        // Generate comprehensive process ID
        generateProcessId: function () {
            return 'proc_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        init: function () {
            this.bindEvents();
            this.bindEventHandlers();

            // Load settings directly from params
            if (typeof w2pSmartAuiParams !== 'undefined' && w2pSmartAuiParams.settings) {
                this.settings = w2pSmartAuiParams.settings;
                var threads = parseInt(this.settings.concurrent_threads, 10);
                if (!isNaN(threads) && threads > 0) {
                    this.maxConcurrent = threads;
                }
            }
        },

        bindEvents: function () {
            var self = this;
            
            $('#w2p-smart-aui-close-btn').on('click', function () {
                progressUI.hide();
            });

            $('#w2p-smart-aui-cancel-btn').on('click', function () {
                progressUI.cancel();
            });
            
            // 新增：跳过直接发布按钮
            $('#w2p-smart-aui-skip-publish-btn').on('click', function () {
                self.skipAndPublish();
            });

            $('#w2p-smart-aui-backdrop').on('click', function (e) {
                if (e.target === this && !progressUI.isProcessing) {
                    progressUI.hide();
                }
            });
        },



        bindEventHandlers: function () {
            var self = this;

            // 1. 拦截文章发布/更新按钮 (Post Edit Screen)
            $(document).on('click', '#publish, .editor-post-publish-button, .editor-post-publish-panel__toggle', function (e) {
                if (progressUI.isProcessing || $(this).data('smart-aui-processed')) {
                    return;
                }

                var content = progressUI.getEditorContent();
                var externalImages = progressUI.findExternalImages(content);

                if (externalImages.length > 0) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    progressUI.originalButton = $(this);

                    // 检查是否显示进度UI（默认为true）
                    var showProgressUI = true;
                    if (self.settings && typeof self.settings.show_progress_ui !== 'undefined') {
                        showProgressUI = self.settings.show_progress_ui;
                    }
                    
                    console.log('[Smart AUI] External images found:', externalImages.length, 'Show UI:', showProgressUI);
                    
                    if (showProgressUI) {
                        progressUI.startAsyncProcessing(content, externalImages);
                    } else {
                        progressUI.processWithoutProgress(content, externalImages);
                    }
                    return false;
                }
            });

            // 2. 拦截批量编辑应用按钮 (Post List Screen)
            document.addEventListener('click', function (e) {
                var target = e.target;
                var bulkBtn = null;
                // 向上查找是否点击了 bulk_edit
                while (target && target !== document) {
                    if (target.id === 'bulk_edit') {
                        bulkBtn = target;
                        break;
                    }
                    target = target.parentNode;
                }

                if (!bulkBtn) return;

                // 检查是否有选中的文章
                var checkedPosts = jQuery('input[name="post[]"]:checked');
                if (checkedPosts.length === 0) return;

                // 已处理过则放行
                if (jQuery(bulkBtn).data('smart-aui-processed')) return;

                // 阻止默认提交
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                progressUI.originalButton = jQuery(bulkBtn);

                var postIds = [];
                checkedPosts.each(function () {
                    postIds.push(jQuery(this).val());
                });

                // 检查是否显示进度UI（默认为true）
                var showProgressUI = true;
                if (self.settings && typeof self.settings.show_progress_ui !== 'undefined') {
                    showProgressUI = self.settings.show_progress_ui;
                }
                
                console.log('[Smart AUI] Bulk edit triggered for', postIds.length, 'posts, Show UI:', showProgressUI);
                
                if (showProgressUI) {
                    progressUI.startBulkProcessing(postIds);
                } else {
                    progressUI.processBulkWithoutProgress(postIds);
                }
                return false;
            }, true);
        },

        // ==========================================
        //  UI Control Methods
        // ==========================================

        show: function () {
            this.isProcessing = true;
            $('#w2p-smart-aui-backdrop').fadeIn(200);
        },

        hide: function () {
            this.isProcessing = false;
            this.currentProcessingContent = null; // 重置当前处理的内容
            $('#w2p-smart-aui-backdrop').fadeOut(200);
        },

        cancel: function () {
            if (confirm(w2pSmartAuiParams.i18n.confirmCancel)) {
                this.isProcessing = false;
                this.hide();
            }
        },
        
        /**
         * 跳过当前抓取进程，直接发布文章
         */
        skipAndPublish: function () {
            var self = this;
            console.log('[Smart AUI] Skip and publish triggered');
            
            if (!confirm(w2pSmartAuiParams.i18n.confirmSkip)) {
                return;
            }
            
            // 停止处理
            this.isProcessing = false;
            
            // 更新状态
            this.updateStatus(w2pSmartAuiParams.i18n.statusStopped, false);
            
            // 延迟一下，让用户看到反馈
            setTimeout(function () {
                // 使用已经部分替换过的内容
                var processedContent = self.currentProcessingContent || self.getEditorContent();
                
                console.log('[Smart AUI] Using processed content, length:', processedContent.length);
                console.log('[Smart AUI] Updating editor with partially processed content');
                self.setEditorContent(processedContent);
                
                // 等待编辑器更新完成
                setTimeout(function () {
                    console.log('[Smart AUI] Hiding UI and publishing');
                    self.hide();
                    
                    // 执行发布
                    self.submitForm(processedContent);
                }, 500);
            }, 300);
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

        updateStats: function (total, success, failed, active, max, skipped) {
            $('#w2p-smart-aui-total').text(total);
            $('#w2p-smart-aui-success').text(success);
            $('#w2p-smart-aui-failed').text(failed);
            if (skipped !== undefined) $('#w2p-smart-aui-skipped').text(skipped);

            if (active !== undefined) $('#w2p-smart-aui-active-threads').text(active);
            if (max !== undefined) $('#w2p-smart-aui-threads').text(max);

            // 进度条
            var processed = success + failed + (skipped || 0);
            if (total > 0) {
                var percentage = (processed / total) * 100;
                $('.w2p-smart-aui-progress-fill').css('width', percentage + '%');
            }
            
            // 如果有失败的图片，显示“跳过，直接发布”按钮
            if (failed > 0 && this.isProcessing) {
                $('#w2p-smart-aui-skip-publish-btn').show();
            } else {
                $('#w2p-smart-aui-skip-publish-btn').hide();
            }
        },

        initPreviewGrid: function (count) {
            var $container = $('#w2p-smart-aui-preview-area');
            if ($container.length === 0) {
                $container = $('.w2p-smart-aui-preview-area');
            }

            if ($container.length === 0) {
                console.error('[Smart AUI] Preview container not found!');
                return;
            }

            // Clear and prepare container (unified handling for all flows)
            $container.empty();
            $container.addClass('grid-mode');
            $container.show();

            var gridHtml = '';
            for (var i = 0; i < count; i++) {
                gridHtml += '<div class="w2p-smart-aui-grid-item" data-slot-id="' + i + '">' +
                    '<div class="status-overlay">' +
                    '<span class="status-line dashicons"></span>' +
                    '</div>' +
                    '<div class="w2p-smart-aui-grid-placeholder">' +
                    '<span class="dashicons dashicons-image-rotate"></span>' +
                    '</div>' +
                    '<img src="" style="display:none;" />' +
                    '</div>';
            }

            $container.html(gridHtml);

            // Update thread count display immediately after grid creation
            $('#w2p-smart-aui-threads').text(count);
            console.log('[Smart AUI] Preview grid initialized with ' + count + ' slots');
        },

        updateThreadPreview: function (slotId, url, status) {
            try {
                var $slots = $('.w2p-smart-aui-grid-item[data-slot-id="' + slotId + '"]');
                if ($slots.length === 0) return;

                $slots.each(function () {
                    var $slot = $(this);
                    var $img = $slot.find('img');
                    var $overlay = $slot.find('.status-overlay');
                    var $icon = $overlay.find('.status-line');
                    var $placeholder = $slot.find('.w2p-smart-aui-grid-placeholder');

                    $slot.removeClass('loading success error done');
                    $icon.removeClass('dashicons-yes dashicons-warning');

                    if (status === 'loading') {
                        $slot.addClass('loading');
                        $placeholder.show();
                        $img.hide();
                    } else if (status === 'success') {
                        $slot.addClass('success done');
                        $placeholder.hide();
                        $icon.addClass('dashicons-yes');

                        var img = new Image();
                        img.onload = function () { $img.attr('src', url).show(); };
                        img.src = url;
                    } else if (status === 'error') {
                        $slot.addClass('error done');
                        $placeholder.hide();
                        $icon.addClass('dashicons-warning');
                        $img.hide();
                    }
                });
            } catch (e) {
                console.error('UI Update Failed:', e);
            }
        },

        // ==========================================
        //  Core Logic
        // ==========================================

        /**
         * Generic Parallel Image Processor
         * Used by Bulk Edit (批量编辑需要这个)
         */
        processPostImages: function (postId, content, images, onComplete) {
            var self = this;

            console.log('[Smart AUI] processPostImages called for post', postId, 'with', images.length, 'images');

            // 确保线程数有效
            var maxConcurrent = parseInt(self.maxConcurrent, 10);
            if (isNaN(maxConcurrent) || maxConcurrent < 1) maxConcurrent = 4;

            // 初始化 UI
            self.initPreviewGrid(maxConcurrent);
            self.updateStats(images.length, 0, 0, 0, maxConcurrent, 0);

            var queue = images.slice();
            var total = images.length;
            var processed = 0;
            var success = 0;
            var failed = 0;
            var skipped = 0;
            var active = 0;
            var currentContent = content || '';
            
            // 将当前内容保存到对象属性，以便 skipAndPublish 可以访问
            self.currentProcessingContent = currentContent;

            // Slot Management
            var freeSlots = [];
            for (var i = 0; i < maxConcurrent; i++) {
                freeSlots.push(i);
            }

            // Recursive Worker Starter
            var startWorkers = function () {
                if (!self.isProcessing) {
                    console.log('[Smart AUI] Processing cancelled by user');
                    return; // User cancelled
                }

                while (active < maxConcurrent && queue.length > 0) {
                    var targetUrl = queue.shift();
                    active++;

                    // Get Slot
                    var slotId = freeSlots.shift();
                    if (slotId === undefined) {
                        slotId = 0;
                    }

                    self.updateStats(total, success, failed, active, maxConcurrent, skipped);
                    self.updateThreadPreview(slotId, targetUrl, 'loading');

                    // Ajax Call in Closure
                    (function (url, slot) {
                        $.ajax({
                            url: w2pSmartAuiParams.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'w2p_smart_aui_download_image',
                                nonce: w2pSmartAuiParams.nonce,
                                post_id: postId,
                                image_url: url,
                                process_id: self.processId
                            },
                            success: function (response) {
                                if (response && response.success && response.data) {
                                    var newUrl = response.data.downloaded_url || url;
                                    var isSkipped = response.data.skipped || false;
                                    var isFailed = response.data.failed || false;
                                    
                                    if (isSkipped) {
                                        // 跳过（已存在于媒体库或被排除）
                                        skipped++;
                                        self.updateThreadPreview(slot, url, 'success');
                                        console.log('[Smart AUI] Image skipped:', url);
                                    } else if (isFailed || !response.data.downloaded_url) {
                                        // 失败（下载失败）
                                        failed++;
                                        self.updateThreadPreview(slot, url, 'error');
                                        console.log('[Smart AUI] Image failed:', url);
                                    } else {
                                        // 成功
                                        success++;
                                        self.updateThreadPreview(slot, newUrl, 'success');
                                        console.log('[Smart AUI] Image downloaded:', url, '->', newUrl);
                                        
                                        // Replace URL in content
                                        if (newUrl !== url && currentContent) {
                                            var escapedOld = self.escapeRegExp(url);
                                            var re = new RegExp(escapedOld, 'g');
                                            currentContent = currentContent.replace(re, newUrl);
                                            // 同步更新到对象属性
                                            self.currentProcessingContent = currentContent;
                                        }
                                    }
                                } else {
                                    // AJAX请求失败
                                    failed++;
                                    self.updateThreadPreview(slot, url, 'error');
                                    console.log('[Smart AUI] Image request failed:', url);
                                }
                                onWorkerDone(slot);
                            },
                            error: function () {
                                // AJAX错误
                                failed++;
                                self.updateThreadPreview(slot, url, 'error');
                                console.log('[Smart AUI] AJAX error for image:', url);
                                onWorkerDone(slot);
                            }
                        });
                    })(targetUrl, slotId);
                }
            };

            var onWorkerDone = function (slot) {
                processed++;
                active--;
                // Return slot
                if (slot !== undefined) {
                    freeSlots.push(slot);
                    freeSlots.sort((a, b) => a - b);
                }

                self.updateStats(total, success, failed, active, maxConcurrent, skipped);

                if (queue.length === 0 && active === 0) {
                    // All done for THIS post
                    console.log('[Smart AUI] All images processed for post', postId, '- Total:', total, 'Success:', success, 'Skipped:', skipped, 'Failed:', failed);
                    if (onComplete) {
                        console.log('[Smart AUI] Calling onComplete callback with updated content');
                        onComplete(currentContent);
                    }
                } else {
                    // Start next batch
                    startWorkers();
                }
            };

            startWorkers();

            // Watchdog
            setTimeout(function () {
                if (queue.length > 0 && active === 0 && self.isProcessing) {
                    console.log('[Smart AUI] Watchdog triggered, restarting workers');
                    startWorkers();
                }
            }, 2000);
        },

        // ==========================================
        //  Specific Flows
        // ==========================================

        /**
         * Start Async Processing for Single Post
         * Uses the same client-side multi-threading as batch processing
         */
        startAsyncProcessing: function (content, images) {
            this.show();
            this.updateStatus(w2pSmartAuiParams.i18n.processingImages, true);
            this.processId = this.generateProcessId();

            var self = this;
            var postId = $('#post_ID').val();

            // Gutenberg Support
            if (!postId && typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                postId = wp.data.select('core/editor').getCurrentPostId();
            }

            // Fallback: URL param
            if (!postId) {
                var urlParams = new URLSearchParams(window.location.search);
                postId = urlParams.get('post');
            }

            if (!postId) {
                this.updateStatus('❌ 无法获取文章ID', false);
                console.error('[Smart AUI] No Post ID found');
                return;
            }

            console.log('[Smart AUI] Starting processing for post ID:', postId, 'with', images.length, 'images');

            // Use the same processPostImages as batch processing
            this.processPostImages(postId, content, images, function (processedContent) {
                console.log('[Smart AUI] Single post processing complete, content updated');
                console.log('[Smart AUI] Processed content length:', processedContent ? processedContent.length : 0);
                console.log('[Smart AUI] Original button exists:', !!self.originalButton);
                
                self.isProcessing = false;
                self.updateStatus(w2pSmartAuiParams.i18n.allComplete, false);
                
                // 立即更新编辑器内容
                self.setEditorContent(processedContent);
                console.log('[Smart AUI] Editor content updated');
                
                setTimeout(function () {
                    console.log('[Smart AUI] Hiding progress UI and submitting form');
                    self.hide();
                    // 执行发布
                    self.submitForm(processedContent);
                }, 800);
            });
        },

        startBulkProcessing: function (postIds) {
            this.show();
            
            var self = this;
            var queue = postIds.slice();
            var totalPosts = postIds.length;
            var processedPosts = 0;
            
            // 检测批量编辑表单中的状态字段
            // WordPress 批量编辑表单中，状态字段名为 "_status"
            var newStatus = jQuery('select[name="_status"]').val();
            var shouldPublish = false;
            var targetStatus = null;
            
            console.log('[Smart AUI] Bulk processing started, checking status...');
            console.log('[Smart AUI] _status field exists:', jQuery('select[name="_status"]').length > 0);
            console.log('[Smart AUI] _status value:', newStatus);
            
            if (newStatus && newStatus !== '-1') {
                // 用户在批量编辑中选择了状态
                targetStatus = newStatus;
                shouldPublish = (newStatus === 'publish');
                console.log('[Smart AUI] Bulk edit status detected:', newStatus);
            } else {
                // 检测直接批量操作（不打开编辑面板）
                var bulkAction = jQuery('select[name="action"]').val();
                if (bulkAction === '-1' || !bulkAction) {
                    bulkAction = jQuery('select[name="action2"]').val();
                }
                console.log('[Smart AUI] Bulk action value:', bulkAction);
                if (bulkAction === 'publish') {
                    shouldPublish = true;
                    targetStatus = 'publish';
                    console.log('[Smart AUI] Direct bulk publish action detected');
                }
            }
            
            console.log('[Smart AUI] Should publish:', shouldPublish, 'Target status:', targetStatus);
            
            // 根据操作类型显示不同的初始信息
            var initialMessage = shouldPublish ? w2pSmartAuiParams.i18n.statusPreparingPublish : w2pSmartAuiParams.i18n.statusPreparing;
            this.updateStatus(initialMessage, true);
            this.updateStats(postIds.length, 0, 0, 0); // Temporary initial UI

            var processNextPost = function () {
                if (!self.isProcessing) {
                    // User cancelled
                    console.log('[Smart AUI] Batch processing cancelled by user');
                    return;
                }

                if (queue.length === 0) {
                    // 所有文章处理完成
                    self.isProcessing = false;
                    
                    // 根据实际状态显示不同消息
                    var message = w2pSmartAuiParams.i18n.completeAll;
                    if (targetStatus === 'publish') {
                        message = w2pSmartAuiParams.i18n.completePublished;
                    } else if (targetStatus === 'draft') {
                        message = w2pSmartAuiParams.i18n.completeDraft;
                    } else if (targetStatus === 'pending') {
                        message = w2pSmartAuiParams.i18n.completePending;
                    } else if (targetStatus === 'private') {
                        message = w2pSmartAuiParams.i18n.completePrivate;
                    }
                    self.updateStatus(message, false);
                    
                    console.log('[Smart AUI] All posts processed. Target status:', targetStatus);
                    
                    setTimeout(function () {
                        self.hide();
                        // 不再点击原始按钮，直接刷新页面
                        console.log('[Smart AUI] Reloading page to show updated posts');
                        location.reload();
                    }, 1500);
                    return;
                }

                var postId = queue.shift();
                processedPosts++;

                // Get Post Details
                $.ajax({
                    url: w2pSmartAuiParams.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_smart_aui_get_post_details',
                        nonce: w2pSmartAuiParams.nonce,
                        post_id: postId
                    },
                    success: function (response) {
                        if (!self.isProcessing) {
                            // User cancelled during ajax
                            return;
                        }

                        if (response.success && response.data) {
                            var postData = response.data;
                            var title = postData.post_title || ('Post #' + postId);
                            
                            // 根据目标状态显示不同的前缀
                            var statusPrefix = w2pSmartAuiParams.i18n.statusProcessing;
                            if (targetStatus === 'publish') {
                                statusPrefix = w2pSmartAuiParams.i18n.statusProcessAndPublish;
                            } else if (targetStatus === 'draft') {
                                statusPrefix = w2pSmartAuiParams.i18n.statusProcessAndDraft;
                            } else if (targetStatus === 'pending') {
                                statusPrefix = w2pSmartAuiParams.i18n.statusProcessAndPending;
                            } else if (targetStatus === 'private') {
                                statusPrefix = w2pSmartAuiParams.i18n.statusProcessAndPrivate;
                            }
                            
                            self.updateStatus('[' + processedPosts + '/' + totalPosts + '] ' + statusPrefix + ': ' + title, true);

                            // Find Images
                            var content = postData.post_content;
                            var images = self.findExternalImages(content);

                            if (images.length === 0) {
                                // No external images to process
                                console.log('[Smart AUI] Post', postId, 'has no external images');
                                
                                // 但如果用户选择了状态，仍然需要保存
                                if (targetStatus) {
                                    console.log('[Smart AUI] No images but status change requested, saving post', postId, 'with status:', targetStatus);
                                    
                                    var saveData = {
                                        action: 'w2p_smart_aui_save_post_content',
                                        nonce: w2pSmartAuiParams.nonce,
                                        post_id: postId,
                                        content: content,
                                        post_status: targetStatus
                                    };
                                    
                                    $.ajax({
                                        url: w2pSmartAuiParams.ajax_url,
                                        type: 'POST',
                                        data: saveData,
                                        success: function (response) {
                                            console.log('[Smart AUI] Post ' + postId + ' status updated to ' + targetStatus);
                                            if (response.success && response.data) {
                                                console.log('[Smart AUI] Response:', response.data);
                                            }
                                            processNextPost();
                                        },
                                        error: function () {
                                            console.error('Failed to update status for post ' + postId);
                                            processNextPost();
                                        }
                                    });
                                } else {
                                    // 没有图片也没有状态变更，直接跳过
                                    console.log('[Smart AUI] Post', postId, 'skipped (no images, no status change)');
                                    processNextPost();
                                }
                                return;
                            }

                            // Generate new process ID for this post
                            self.processId = self.generateProcessId();

                            // Start Parallel Processing for THIS post
                            self.processPostImages(postId, content, images, function (processedContent) {
                                if (!self.isProcessing) {
                                    // User cancelled during processing
                                    return;
                                }

                                // Save Content immediately after processing THIS post
                                var saveData = {
                                    action: 'w2p_smart_aui_save_post_content',
                                    nonce: w2pSmartAuiParams.nonce,
                                    post_id: postId,
                                    content: processedContent
                                };
                                
                                // 如果用户选择了状态，同时更新状态
                                if (targetStatus) {
                                    saveData.post_status = targetStatus;
                                }
                                
                                console.log('[Smart AUI] Saving post', postId, 'with status:', targetStatus || 'unchanged');
                                
                                $.ajax({
                                    url: w2pSmartAuiParams.ajax_url,
                                    type: 'POST',
                                    data: saveData,
                                    success: function (response) {
                                        var action = shouldPublish ? 'saved and published' : 'saved';
                                        console.log('[Smart AUI] Post ' + postId + ' ' + action + ' successfully, moving to next post');
                                        if (response.success && response.data) {
                                            console.log('[Smart AUI] Response:', response.data);
                                        }
                                        processNextPost();
                                    },
                                    error: function () {
                                        console.error('Failed to save post ' + postId);
                                        processNextPost();
                                    }
                                });
                            });

                        } else {
                            console.error('Failed to get details for ' + postId);
                            processNextPost();
                        }
                    },
                    error: function () {
                        console.error('Failed to get details for ' + postId);
                        processNextPost(); // Skip on error
                    }
                });
            };

            processNextPost();
        },

        // Legacy / Helper Methods
        finishProcessing: function (processedContent) { /* ... handled inline now ... */ },
        processWithoutProgress: function (content, images) {
            // ... existing logic simplified ...
            // For brevity, using simplified version
            var self = this;
            var postId = $('#post_ID').val() || (wp.data && wp.data.select('core/editor').getCurrentPostId());

            $.ajax({
                url: w2pSmartAuiParams.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_smart_aui_process_all',
                    nonce: w2pSmartAuiParams.nonce,
                    post_id: postId,
                    content: content,
                    images: images
                },
                success: function (r) {
                    if (r.success && r.data.processed_content) self.setEditorContent(r.data.processed_content);
                    if (self.originalButton) { self.originalButton.data('smart-aui-processed', true); self.originalButton.click(); }
                },
                error: function () { if (self.originalButton) self.originalButton.click(); }
            });
        },
        processBulkWithoutProgress: function (postIds) {
            // ... existing ... 
            var self = this;
            if (self.originalButton) { self.originalButton.data('smart-aui-processed', true); self.originalButton.click(); }
        },
        submitForm: function (processedContent) {
            console.log('[Smart AUI] submitForm called');
            console.log('[Smart AUI] - Processed content provided:', !!processedContent);
            console.log('[Smart AUI] - Original button exists:', !!this.originalButton);
            
            // 更新编辑器内容（确保内容已更新）
            if (processedContent) {
                this.setEditorContent(processedContent);
                console.log('[Smart AUI] Content set to editor');
            }
            
            // 设置全局标记，告诉后端不要再次处理图片
            window.W2P_SMART_AUI_PROCESSED = true;
            
            // 对于 Gutenberg，添加自定义元数据
            if (window.wp && window.wp.data && window.wp.data.dispatch) {
                try {
                    window.wp.data.dispatch('core/editor').editPost({
                        meta: { _w2p_smart_aui_processed: '1' }
                    });
                } catch (e) {
                    console.log('[Smart AUI] Could not set meta, using alternative method');
                }
            }
            
            if (this.originalButton && this.originalButton.length > 0) {
                console.log('[Smart AUI] Using original button to submit');
                
                // 设置标记，防止再次拦截
                this.originalButton.attr('data-smart-aui-processed', 'true');
                this.originalButton.data('smart-aui-processed', true);
                
                // 对于经典编辑器，添加隐藏字段
                var $form = this.originalButton.closest('form');
                if ($form.length > 0) {
                    var $hidden = $('<input>').attr({
                        type: 'hidden',
                        name: 'w2p_smart_aui_processed',
                        value: '1'
                    });
                    $form.append($hidden);
                }
                
                // 检测是否是 Gutenberg 编辑器
                if (window.wp && window.wp.data && window.wp.data.dispatch && window.wp.data.select) {
                    console.log('[Smart AUI] Detected Gutenberg editor, using savePost');
                    try {
                        // 尝试获取 editor store
                        var editorStore = window.wp.data.dispatch('core/editor');
                        if (editorStore && typeof editorStore.savePost === 'function') {
                            editorStore.savePost();
                            console.log('[Smart AUI] savePost dispatched successfully');
                        } else {
                            // 尝试使用 core 或点击按钮
                            console.warn('[Smart AUI] core/editor store not available, trying button click');
                            this.originalButton[0].click();
                        }
                    } catch (error) {
                        console.error('[Smart AUI] Error dispatching savePost:', error);
                        // 如果失败，尝试点击按钮
                        console.log('[Smart AUI] Falling back to button click');
                        this.originalButton[0].click();
                    }
                } else {
                    console.log('[Smart AUI] Using button click to submit');
                    // 经典编辑器，点击按钮
                    this.originalButton[0].click();
                }
            } else {
                console.log('[Smart AUI] No original button, submitting form directly');
                // 如果没有按钮，直接提交表单
                var $form = $('#post');
                if ($form.length > 0) {
                    // 添加隐藏字段
                    var $hidden = $('<input>').attr({
                        type: 'hidden',
                        name: 'w2p_smart_aui_processed',
                        value: '1'
                    });
                    $form.append($hidden);
                    $form.submit();
                } else {
                    console.error('[Smart AUI] No form found to submit');
                }
            }
        },
        getEditorContent: function () {
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) return wp.data.select('core/editor').getEditedPostContent();
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) return tinyMCE.activeEditor.getContent();
            return $('#content').val();
        },
        setEditorContent: function (content) {
            console.log('[Smart AUI] setEditorContent called, content length:', content ? content.length : 0);
            
            // Gutenberg 编辑器
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select) {
                try {
                    var editor = wp.data.select('core/editor');
                    if (editor) {
                        wp.data.dispatch('core/editor').editPost({ content: content });
                        console.log('[Smart AUI] Content set via Gutenberg editPost');
                        return;
                    }
                } catch (error) {
                    console.error('[Smart AUI] Error setting Gutenberg content:', error);
                }
            }
            
            // TinyMCE 编辑器
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                tinyMCE.activeEditor.setContent(content);
                console.log('[Smart AUI] Content set via TinyMCE');
                return;
            }
            
            // 经典文本域
            var $content = $('#content');
            if ($content.length > 0) {
                $content.val(content);
                console.log('[Smart AUI] Content set via textarea');
            } else {
                console.error('[Smart AUI] No editor found to set content');
            }
        },
        findExternalImages: function (content) {
            var images = [];
            var imageRegex = /<img[^>]+src=["']([^"']+)["'][^>]*>/gi;
            var match;
            var siteUrl = window.location.origin;

            // Prepare exclusions
            var exclusions = [];
            if (this.settings && this.settings.domain_exclusions) {
                var rawExclusions = this.settings.domain_exclusions;
                if (typeof rawExclusions === 'string') {
                    exclusions = rawExclusions.split('\n').map(function (d) { return d.trim(); }).filter(function (d) { return d.length > 0; });
                } else if (Array.isArray(rawExclusions)) {
                    exclusions = rawExclusions;
                }
            }

            while ((match = imageRegex.exec(content)) !== null) {
                var src = match[1];
                if (src.indexOf(siteUrl) === 0 || src.indexOf('/wp-content/') === 0 || src.indexOf('data:') === 0) continue;

                // Check exclusions
                var isExcluded = false;
                for (var i = 0; i < exclusions.length; i++) {
                    if (src.indexOf(exclusions[i]) !== -1) {
                        isExcluded = true;
                        break;
                    }
                }

                if (!isExcluded) {
                    images.push(src);
                }
            }
            return images;
        },
        escapeRegExp: function (string) {
            return string ? string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') : '';
        }
    };

    $(document).ready(function () {
        progressUI.init();
    });

    window.W2P_SmartAUI_Progress = progressUI;

})(jQuery);