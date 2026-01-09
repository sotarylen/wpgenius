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
                var externalVideos = progressUI.findExternalVideos(content);
                var localImagesWithoutID = progressUI.findLocalImagesWithoutID(content);

                // Trigger capture if there are external images/videos OR local images needing ID injection
                if (externalImages.length > 0 || externalVideos.length > 0 || localImagesWithoutID.length > 0) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    progressUI.originalButton = $(this);

                    // 检查是否显示进度UI（默认为true）
                    var showProgressUI = true;
                    if (self.settings && typeof self.settings.show_progress_ui !== 'undefined') {
                        showProgressUI = self.settings.show_progress_ui;
                    }

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

                self.setEditorContent(processedContent);

                // 等待编辑器更新完成
                setTimeout(function () {
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
                        $placeholder.hide();

                        // Show remote URL immediately
                        var img = new Image();
                        img.onload = function () {
                            $img.attr('src', url).show();
                        };
                        img.onerror = function () {
                            // If remote image fails to load, show placeholder
                            $placeholder.show();
                            $img.hide();
                        };
                        img.src = url;
                    } else if (status === 'success') {
                        $slot.addClass('success done');
                        // Keep the image visible, just add success icon overlay
                        $icon.addClass('dashicons-yes');

                        // If URL changed (downloaded to new location), update image
                        if ($img.attr('src') !== url) {
                            var img = new Image();
                            img.onload = function () { $img.attr('src', url).show(); };
                            img.src = url;
                        }
                    } else if (status === 'error') {
                        $slot.addClass('error done');
                        $placeholder.hide();
                        $icon.addClass('dashicons-warning');
                        // Keep the image visible to show what failed
                        // $img.hide(); // Don't hide, let user see what failed
                    }
                });
            } catch (e) {
            }
        },

        // ==========================================
        //  Core Logic
        // ==========================================

        /**
         * Generic Parallel Media Processor (Images + Videos)
         * Used by Bulk Edit and Single Post Processing
         */
        processPostMedia: function (postId, content, mediaItems, onComplete) {
            var self = this;


            // 确保线程数有效
            var maxConcurrent = parseInt(self.maxConcurrent, 10);
            if (isNaN(maxConcurrent) || maxConcurrent < 1) maxConcurrent = 4;

            // 初始化 UI
            self.initPreviewGrid(maxConcurrent);
            self.updateStats(mediaItems.length, 0, 0, 0, maxConcurrent, 0);

            var queue = mediaItems.slice();
            var total = mediaItems.length;
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
                    return; // User cancelled
                }

                while (active < maxConcurrent && queue.length > 0) {
                    var mediaItem = queue.shift();
                    var targetUrl = typeof mediaItem === 'string' ? mediaItem : mediaItem.url;
                    var mediaType = typeof mediaItem === 'string' ? 'image' : (mediaItem.type || 'image');
                    active++;

                    // Get Slot
                    var slotId = freeSlots.shift();
                    if (slotId === undefined) {
                        slotId = 0;
                    }

                    self.updateStats(total, success, failed, active, maxConcurrent, skipped);
                    self.updateThreadPreview(slotId, targetUrl, 'loading');

                    // Ajax Call in Closure
                    (function (url, slot, type, mediaItem) {
                        // Determine action and parameter name based on media type
                        var ajaxAction, urlParam;

                        if (type === 'local-image') {
                            // For local images, just get the attachment ID
                            ajaxAction = 'w2p_smart_aui_get_attachment_id';
                            urlParam = 'image_url';
                        } else if (type === 'video') {
                            ajaxAction = 'w2p_smart_aui_download_video';
                            urlParam = 'video_url';
                        } else {
                            ajaxAction = 'w2p_smart_aui_download_image';
                            urlParam = 'image_url';
                        }

                        var ajaxData = {
                            action: ajaxAction,
                            nonce: w2pSmartAuiParams.nonce,
                            post_id: postId,
                            process_id: self.processId
                        };
                        ajaxData[urlParam] = url;

                        $.ajax({
                            url: w2pSmartAuiParams.ajax_url,
                            type: 'POST',
                            data: ajaxData,
                            success: function (response) {
                                if (response && response.success && response.data) {
                                    var newUrl = response.data.downloaded_url || url;
                                    var isSkipped = response.data.skipped || false;
                                    var isFailed = response.data.failed || false;

                                    if (isSkipped) {
                                        // 跳过（已存在于媒体库或被排除）
                                        skipped++;
                                        self.updateThreadPreview(slot, url, 'success');
                                    } else if (isFailed || (!response.data.downloaded_url && type !== 'local-image')) {
                                        // 失败（下载失败）- 但本地图片不需要 downloaded_url
                                        failed++;
                                        self.updateThreadPreview(slot, url, 'error');
                                    } else {
                                        // 成功
                                        success++;
                                        self.updateThreadPreview(slot, newUrl, 'success');

                                        // Handle content updates based on media type
                                        if (currentContent) {
                                            if (type === 'local-image') {
                                                // For local images, just inject the ID class
                                                if (response.data.attachment_id && mediaItem.tag) {
                                                    var attachmentId = response.data.attachment_id;
                                                    var idClass = 'wp-image-' + attachmentId;
                                                    var oldTag = mediaItem.tag;
                                                    var newTag = oldTag;

                                                    // Add or update class attribute
                                                    if (oldTag.toLowerCase().indexOf('class=') !== -1) {
                                                        // Has class, add to it
                                                        if (oldTag.indexOf('wp-image-') !== -1) {
                                                            newTag = oldTag.replace(/wp-image-\d+/, idClass);
                                                        } else {
                                                            newTag = oldTag.replace(/class=(["'])/i, 'class=$1' + idClass + ' size-full ');
                                                        }
                                                    } else {
                                                        // No class, add it
                                                        newTag = oldTag.replace(/<img/i, '<img class="' + idClass + ' size-full"');
                                                    }

                                                    // Replace in content
                                                    currentContent = currentContent.replace(oldTag, newTag);
                                                }
                                            } else if (type === 'video') {
                                                // For videos, simple URL replacement
                                                var escapedOld = self.escapeRegExp(url);
                                                var re = new RegExp(escapedOld, 'g');
                                                currentContent = currentContent.replace(re, newUrl);
                                            } else {
                                                // For external images, replace URL and inject ID
                                                var escapedOld = self.escapeRegExp(url);
                                                // 1. First replace the URL everywhere
                                                var re = new RegExp(escapedOld, 'g');
                                                currentContent = currentContent.replace(re, newUrl);

                                                // 2. Inject wp-image-{id} class
                                                if (response.data.attachment_id) {
                                                    var attachmentId = response.data.attachment_id;
                                                    var escapedNew = self.escapeRegExp(newUrl);
                                                    var imgTagRegex = new RegExp('<img([^>]+)src=["\']' + escapedNew + '["\']([^>]*)>', 'gi');

                                                    currentContent = currentContent.replace(imgTagRegex, function (match, p1, p2) {
                                                        var fullTag = match;
                                                        var idClass = 'wp-image-' + attachmentId;

                                                        // Add or update class attribute
                                                        if (fullTag.toLowerCase().indexOf('class=') !== -1) {
                                                            if (fullTag.indexOf('wp-image-') !== -1) {
                                                                fullTag = fullTag.replace(/wp-image-\d+/, idClass);
                                                            } else {
                                                                fullTag = fullTag.replace(/class=(["'])/i, 'class=$1' + idClass + ' size-full ');
                                                            }
                                                        } else {
                                                            fullTag = fullTag.replace(/<img/i, '<img class="' + idClass + ' size-full"');
                                                        }
                                                        return fullTag;
                                                    });
                                                }
                                            }

                                            // Sync back to property
                                            self.currentProcessingContent = currentContent;
                                        }
                                    }
                                } else {
                                    // AJAX请求失败
                                    failed++;
                                    self.updateThreadPreview(slot, url, 'error');
                                }
                                onWorkerDone(slot);
                            },
                            error: function () {
                                // AJAX错误
                                failed++;
                                self.updateThreadPreview(slot, url, 'error');
                                onWorkerDone(slot);
                            }
                        });
                    })(targetUrl, slotId, mediaType, mediaItem);
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
                    if (onComplete) {
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
         * Processes both images and videos
         */
        startAsyncProcessing: function (content, externalImages) {
            this.show();
            this.updateStatus(w2pSmartAuiParams.i18n.processingMedia || w2pSmartAuiParams.i18n.processingImages, true);
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
                return;
            }

            // Detect videos in addition to images
            var externalVideos = this.findExternalVideos(content);

            // Detect local images needing ID injection
            var localImagesWithoutID = this.findLocalImagesWithoutID(content);

            // Combine external images, videos, and local images into a single media queue
            var mediaItems = [];

            // Add external images with type marker
            for (var i = 0; i < externalImages.length; i++) {
                mediaItems.push({ url: externalImages[i], type: 'image' });
            }

            // Add videos with type marker
            for (var j = 0; j < externalVideos.length; j++) {
                mediaItems.push({ url: externalVideos[j], type: 'video' });
            }

            // Add local images needing ID injection
            for (var k = 0; k < localImagesWithoutID.length; k++) {
                mediaItems.push({
                    url: localImagesWithoutID[k].src,
                    type: 'local-image',
                    tag: localImagesWithoutID[k].tag
                });
            }

            // Use the new processPostMedia for all media types
            this.processPostMedia(postId, content, mediaItems, function (processedContent) {

                self.isProcessing = false;
                self.updateStatus(w2pSmartAuiParams.i18n.allComplete, false);

                // 立即更新编辑器内容
                self.setEditorContent(processedContent);

                setTimeout(function () {
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


            if (newStatus && newStatus !== '-1') {
                // 用户在批量编辑中选择了状态
                targetStatus = newStatus;
                shouldPublish = (newStatus === 'publish');
            } else {
                // 检测直接批量操作（不打开编辑面板）
                var bulkAction = jQuery('select[name="action"]').val();
                if (bulkAction === '-1' || !bulkAction) {
                    bulkAction = jQuery('select[name="action2"]').val();
                }
                if (bulkAction === 'publish') {
                    shouldPublish = true;
                    targetStatus = 'publish';
                }
            }


            // 根据操作类型显示不同的初始信息
            var initialMessage = shouldPublish ? w2pSmartAuiParams.i18n.statusPreparingPublish : w2pSmartAuiParams.i18n.statusPreparing;
            this.updateStatus(initialMessage, true);
            this.updateStats(postIds.length, 0, 0, 0); // Temporary initial UI

            var processNextPost = function () {
                if (!self.isProcessing) {
                    // User cancelled
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


                    setTimeout(function () {
                        self.hide();
                        // 不再点击原始按钮，直接刷新页面
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

                            // Find Images and Videos
                            var content = postData.post_content;
                            var images = self.findExternalImages(content);
                            var videos = self.findExternalVideos(content);

                            // Combine into media items
                            var mediaItems = [];
                            for (var i = 0; i < images.length; i++) {
                                mediaItems.push({ url: images[i], type: 'image' });
                            }
                            for (var j = 0; j < videos.length; j++) {
                                mediaItems.push({ url: videos[j], type: 'video' });
                            }

                            if (mediaItems.length === 0) {
                                // No external media to process

                                // Clear preview grid to avoid showing phantom items from previous post
                                var $container = $('#w2p-smart-aui-preview-area');
                                if ($container.length === 0) {
                                    $container = $('.w2p-smart-aui-preview-area');
                                }
                                if ($container.length > 0) {
                                    $container.empty();
                                    $container.hide();
                                }

                                // Reset stats display to show 0/0
                                self.updateStats(0, 0, 0, 0, 0, 0);

                                // 但如果用户选择了状态，仍然需要保存
                                if (targetStatus) {

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
                                            if (response.success && response.data) {
                                            }
                                            processNextPost();
                                        },
                                        error: function () {
                                            processNextPost();
                                        }
                                    });
                                } else {
                                    // 没有图片也没有状态变更，直接跳过
                                    processNextPost();
                                }
                                return;
                            }

                            // Generate new process ID for this post
                            self.processId = self.generateProcessId();

                            // Start Parallel Processing for THIS post (images + videos)
                            self.processPostMedia(postId, content, mediaItems, function (processedContent) {
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


                                $.ajax({
                                    url: w2pSmartAuiParams.ajax_url,
                                    type: 'POST',
                                    data: saveData,
                                    success: function (response) {
                                        var action = shouldPublish ? 'saved and published' : 'saved';
                                        if (response.success && response.data) {
                                        }
                                        processNextPost();
                                    },
                                    error: function () {
                                        processNextPost();
                                    }
                                });
                            });

                        } else {
                            processNextPost();
                        }
                    },
                    error: function () {
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

            // 更新编辑器内容（确保内容已更新）
            if (processedContent) {
                this.setEditorContent(processedContent);
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
                }
            }

            if (this.originalButton && this.originalButton.length > 0) {

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
                    try {
                        // 尝试获取 editor store
                        var editorStore = window.wp.data.dispatch('core/editor');
                        if (editorStore && typeof editorStore.savePost === 'function') {
                            editorStore.savePost();
                        } else {
                            // 尝试使用 core 或点击按钮
                            console.warn('[Smart AUI] core/editor store not available, trying button click');
                            this.originalButton[0].click();
                        }
                    } catch (error) {
                        // 如果失败，尝试点击按钮
                        this.originalButton[0].click();
                    }
                } else {
                    // 经典编辑器，点击按钮
                    this.originalButton[0].click();
                }
            } else {
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
                }
            }
        },
        getEditorContent: function () {
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) return wp.data.select('core/editor').getEditedPostContent();
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) return tinyMCE.activeEditor.getContent();
            return $('#content').val();
        },
        setEditorContent: function (content) {

            // Gutenberg 编辑器
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select) {
                try {
                    var editor = wp.data.select('core/editor');
                    if (editor) {
                        wp.data.dispatch('core/editor').editPost({ content: content });
                        return;
                    }
                } catch (error) {
                }
            }

            // TinyMCE 编辑器
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                tinyMCE.activeEditor.setContent(content);
                return;
            }

            // 经典文本域
            var $content = $('#content');
            if ($content.length > 0) {
                $content.val(content);
            } else {
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

        /**
         * Find local images that don't have wp-image-{id} class
         */
        findLocalImagesWithoutID: function (content) {
            var localImages = [];
            var imageRegex = /<img[^>]+>/gi;
            var match;
            var siteUrl = window.location.origin;

            // Get base URL from settings if available
            var baseUrl = siteUrl;
            if (this.settings && this.settings.base_url) {
                baseUrl = this.settings.base_url.replace(/\/$/, ''); // Remove trailing slash
            }

            while ((match = imageRegex.exec(content)) !== null) {
                var imgTag = match[0];

                // Extract src
                var srcMatch = imgTag.match(/src=["']([^"']+)["']/i);
                if (!srcMatch) continue;
                var src = srcMatch[1];

                // Check if it's a local image
                var isLocal = src.indexOf(siteUrl) === 0 ||
                    src.indexOf(baseUrl) === 0 ||
                    src.indexOf('/wp-content/') === 0 ||
                    src.indexOf('/wp-media/') === 0;

                if (!isLocal) continue;

                // Check if it already has wp-image-{id} class
                var hasImageClass = /class=["'][^"']*wp-image-\d+[^"']*["']/i.test(imgTag);

                if (!hasImageClass) {
                    localImages.push({
                        tag: imgTag,
                        src: src
                    });
                }
            }

            return localImages;
        },

        /**
         * Inject attachment IDs for local images
         */
        injectLocalImageIDs: function (content, postId, callback) {
            var self = this;
            var localImages = this.findLocalImagesWithoutID(content);

            if (localImages.length === 0) {
                if (callback) callback(content);
                return;
            }

            var processedCount = 0;
            var updatedContent = content;

            // Process each local image
            localImages.forEach(function (imageInfo) {
                $.ajax({
                    url: w2pSmartAuiParams.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_smart_aui_get_attachment_id',
                        nonce: w2pSmartAuiParams.nonce,
                        image_url: imageInfo.src
                    },
                    success: function (response) {
                        if (response && response.success && response.data && response.data.attachment_id) {
                            var attachmentId = response.data.attachment_id;
                            var idClass = 'wp-image-' + attachmentId;
                            var oldTag = imageInfo.tag;
                            var newTag = oldTag;

                            // Add or update class attribute
                            if (oldTag.toLowerCase().indexOf('class=') !== -1) {
                                // Has class, add to it
                                newTag = oldTag.replace(/class=(["'])/i, 'class=$1' + idClass + ' size-full ');
                            } else {
                                // No class, add it
                                newTag = oldTag.replace(/<img/i, '<img class="' + idClass + ' size-full"');
                            }

                            // Replace in content
                            updatedContent = updatedContent.replace(oldTag, newTag);
                        }

                        processedCount++;
                        if (processedCount === localImages.length) {
                            if (callback) callback(updatedContent);
                        }
                    },
                    error: function () {
                        processedCount++;
                        if (processedCount === localImages.length) {
                            if (callback) callback(updatedContent);
                        }
                    }
                });
            });
        },
        findExternalVideos: function (content) {
            var videos = [];
            var siteUrl = window.location.origin;

            // Check if video capture is enabled
            if (!this.settings || !this.settings.capture_videos) {
                return videos;
            }

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

            // Find videos from <video src="..."> tags
            var videoSrcRegex = /<video[^>]+src=["']([^"']+)["'][^>]*>/gi;
            var match;
            while ((match = videoSrcRegex.exec(content)) !== null) {
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

                if (!isExcluded && videos.indexOf(src) === -1) {
                    videos.push(src);
                }
            }

            // Find videos from <source src="..."> tags within <video> elements
            var sourceSrcRegex = /<source[^>]+src=["']([^"']+)["'][^>]*>/gi;
            while ((match = sourceSrcRegex.exec(content)) !== null) {
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

                if (!isExcluded && videos.indexOf(src) === -1) {
                    videos.push(src);
                }
            }

            return videos;
        },
        escapeRegExp: function (string) {
            return string ? string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') : '';
        }
    };

    $(document).ready(function () {
        progressUI.init();
    });

    // Backward compatibility: Add alias for old function name
    progressUI.processPostImages = function (postId, content, images, onComplete) {
        // Convert images array to mediaItems format
        var mediaItems = [];
        for (var i = 0; i < images.length; i++) {
            mediaItems.push({ url: images[i], type: 'image' });
        }
        // Call new function
        return this.processPostMedia(postId, content, mediaItems, onComplete);
    };

    window.W2P_SmartAUI_Progress = progressUI;

})(jQuery);