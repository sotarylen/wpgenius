/**
 * Smart Auto Upload Images Progress UI
 * Smart Auto Upload Images progress UI
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

            // New: skip-and-publish button
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

            // 1. Intercept the post publish/update button (Post Edit Screen: Classic & Gutenberg)
            var publishSelector = '#publish, #save-post, .editor-post-publish-button, .editor-post-publish-button__button, .editor-post-publish-panel__toggle, .editor-post-publish-panel__header-publish-button, button[class*="editor-post-publish-button"]';
            $(document).on('click', publishSelector, function (e) {
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

                    // Check whether to show the progress UI (default true)
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

            // 2. Intercept the bulk-edit Update button (Post List Screen: edit.php)
            $(document).on('click', '#bulk_edit', function (e) {
                var bulkBtn = this;

                // Check whether any posts are selected
                var checkedPosts = $('input[name="post[]"]:checked');
                if (checkedPosts.length === 0) {
                    return;
                }

                // Already processed - allow native submission
                if ($(bulkBtn).data('smart-aui-processed')) {
                    return;
                }

                // Prevent the default submit
                e.preventDefault();
                e.stopImmediatePropagation();

                progressUI.originalButton = $(bulkBtn);

                var postIds = [];
                checkedPosts.each(function () {
                    postIds.push($(this).val());
                });

                // Check whether to show the progress UI (default true)
                var showProgressUI = true;
                if (self.settings && typeof self.settings.show_progress_ui !== 'undefined') {
                    showProgressUI = self.settings.show_progress_ui;
                }

                // Hand control back to the native form once the queue is done.
                // Without this callback startBulkProcessing() only calls
                // location.reload(), so the intercepted submit never runs and
                // every other bulk-edit field (author, status, format, post
                // type, taxonomies…) is silently dropped. Skipped when the user
                // cancels the capture - a cancel should stay a cancel.
                var onQueueDone = function (completed) {
                    if (completed === false) {
                        return;
                    }
                    progressUI.resumeNativeSubmit(progressUI.originalButton);
                };

                if (showProgressUI) {
                    progressUI.startBulkProcessing(postIds, onQueueDone);
                } else {
                    progressUI.processBulkWithoutProgress(postIds);
                }
                return false;
            });
        },

        // ==========================================
        //  UI Control Methods
        // ==========================================

        show: function () {
            this.isProcessing = true;
            $('#w2p-smart-aui-backdrop').removeClass('w2p-hidden').hide().fadeIn(200);
        },

        hide: function () {
            this.isProcessing = false;
            this.currentProcessingContent = null; // Reset the currently processed content
            $('#w2p-smart-aui-backdrop').fadeOut(200, function () {
                $(this).addClass('w2p-hidden');
            });
        },

        cancel: function (force) {
            // Direct stop without alert / confirm modal
            this.isProcessing = false;
            this.hide();
        },

        /**
         * Skip the current capture process and publish the post directly
         */
        skipAndPublish: function () {
            var self = this;

            if (!confirm(w2pSmartAuiParams.i18n.confirmSkip)) {
                return;
            }

            // Stop processing
            this.isProcessing = false;

            // Update status
            this.updateStatus(w2pSmartAuiParams.i18n.statusStopped, false);

            // Small delay so the user sees the feedback
            setTimeout(function () {
                // Use the already partially replaced content
                var processedContent = self.currentProcessingContent || self.getEditorContent();

                self.setEditorContent(processedContent);

                // Wait for the editor update to finish
                setTimeout(function () {
                    self.hide();

                    // Perform the publish
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

            // Progress bar
            var processed = success + failed + (skipped || 0);
            if (total > 0) {
                var percentage = (processed / total) * 100;
                $('.w2p-smart-aui-progress-fill').css('width', percentage + '%');
            }

            // If there are failed images, show the \u201cSkip, publish directly\u201d button
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
                    '<div class="status-overlay" style="display:none;">' +
                    '<span class="status-line dashicons"></span>' +
                    '</div>' +
                    '<div class="w2p-smart-aui-grid-placeholder">' +
                    '<span class="dashicons dashicons-image-rotate w2p-spin"></span>' +
                    '</div>' +
                    '<img referrerpolicy="no-referrer" style="display:none;" />' +
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
                        $placeholder.show();
                        $overlay.hide();

                        if (url) {
                            // Show remote URL immediately with no-referrer policy
                            var imgObj = new Image();
                            imgObj.referrerPolicy = 'no-referrer';
                            imgObj.onload = function () {
                                $img.attr('src', url).css('display', 'block');
                                $placeholder.hide();
                            };
                            imgObj.onerror = function () {
                                // Fallback: still set the src so browser attempts rendering or shows broken image box
                                $img.attr('src', url).css('display', 'block');
                                $placeholder.hide();
                            };
                            imgObj.src = url;
                        }
                    } else if (status === 'success') {
                        $slot.addClass('success done');
                        $icon.addClass('dashicons-yes');
                        $overlay.css('display', 'flex');
                        $placeholder.hide();

                        var displayUrl = url || $img.attr('src');
                        if (displayUrl) {
                            $img.attr('src', displayUrl).css('display', 'block');
                        }
                    } else if (status === 'error') {
                        $slot.addClass('error done');
                        $icon.addClass('dashicons-warning');
                        $overlay.css('display', 'flex');
                        $placeholder.hide();
                    }
                });
            } catch (e) {
                console.warn('[Smart AUI Preview Error]', e);
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


            // Ensure the thread count is valid
            var maxConcurrent = parseInt(self.maxConcurrent, 10);
            if (isNaN(maxConcurrent) || maxConcurrent < 1) maxConcurrent = 4;

            // Initialize UI
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

            // Save the current content to an object property so skipAndPublish can access it
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
                                        // Skipped (already in the media library or excluded)
                                        skipped++;
                                        self.updateThreadPreview(slot, url, 'success');
                                    } else if (isFailed || (!response.data.downloaded_url && type !== 'local-image')) {
                                        // Failed (download failed) - local images do not need downloaded_url
                                        failed++;
                                        self.updateThreadPreview(slot, url, 'error');
                                    } else {
                                        // Success
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
                                    // AJAX request failed
                                    failed++;
                                    self.updateThreadPreview(slot, url, 'error');
                                }
                                onWorkerDone(slot);
                            },
                            error: function () {
                                // AJAX error
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
                this.updateStatus('❌ ' + w2pSmartAuiParams.i18n.failedGetPostId, false);
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

                // Update the editor content immediately
                self.setEditorContent(processedContent);

                setTimeout(function () {
                    self.hide();
                    // Perform the publish
                    self.submitForm(processedContent);
                }, 800);
            });
        },

        startBulkProcessing: function (postIds, onAllComplete, onPostDone) {
            this.show();

            var self = this;
            var queue = postIds.slice();
            var totalPosts = postIds.length;
            var processedPosts = 0;

            // Detect the status field in the bulk-edit form
            // In the WordPress bulk-edit form, the status field is named "_status"
            var newStatus = jQuery('select[name="_status"]').val();
            var shouldPublish = false;
            var targetStatus = null;


            if (newStatus && newStatus !== '-1') {
                // The user selected a status in bulk edit
                targetStatus = newStatus;
                shouldPublish = (newStatus === 'publish');
            } else {
                // Detect direct bulk actions (without opening the edit panel)
                var bulkAction = jQuery('select[name="action"]').val();
                if (bulkAction === '-1' || !bulkAction) {
                    bulkAction = jQuery('select[name="action2"]').val();
                }
                if (bulkAction === 'publish') {
                    shouldPublish = true;
                    targetStatus = 'publish';
                }
            }


            // Show different initial messages depending on the action type
            var initialMessage = shouldPublish ? w2pSmartAuiParams.i18n.statusPreparingPublish : w2pSmartAuiParams.i18n.statusPreparing;
            this.updateStatus(initialMessage, true);
            this.updateStats(postIds.length, 0, 0, 0); // Temporary initial UI

            var processNextPost = function () {
                if (!self.isProcessing) {
                    // User cancelled
                    if (typeof onAllComplete === 'function') {
                        onAllComplete(false);
                    }
                    return;
                }

                if (queue.length === 0) {
                    // All posts processed
                    self.isProcessing = false;

                    // Show different messages depending on the actual status
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
                        if (typeof onAllComplete === 'function') {
                            onAllComplete(true);
                        } else {
                            location.reload();
                        }
                    }, 1200);
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

                            // Show a different prefix depending on the target status
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
                            // Local images needing ID injection (same as single-post update)
                            var localImagesWithoutID = self.findLocalImagesWithoutID(content);

                            // Combine into media items
                            var mediaItems = [];
                            for (var i = 0; i < images.length; i++) {
                                mediaItems.push({ url: images[i], type: 'image' });
                            }
                            for (var j = 0; j < videos.length; j++) {
                                mediaItems.push({ url: videos[j], type: 'video' });
                            }
                            // Local images needing ID injection (same as single-post update)
                            for (var l = 0; l < localImagesWithoutID.length; l++) {
                                mediaItems.push({
                                    url: localImagesWithoutID[l].src,
                                    type: 'local-image',
                                    tag: localImagesWithoutID[l].tag
                                });
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

                                // But if the user selected a status, it still needs to be saved
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
                                    // No images and no status change - skip directly
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

                                // If the user selected a status, update it too
                                if (targetStatus) {
                                    saveData.post_status = targetStatus;
                                }


                                $.ajax({
                                    url: w2pSmartAuiParams.ajax_url,
                                    type: 'POST',
                                    data: saveData,
                                    success: function (response) {
                                        if (typeof onPostDone === 'function') {
                                            onPostDone(postId, true);
                                        }
                                        processNextPost();
                                    },
                                    error: function () {
                                        if (typeof onPostDone === 'function') {
                                            onPostDone(postId, false);
                                        }
                                        processNextPost();
                                    }
                                });
                            });

                        } else {
                            if (typeof onPostDone === 'function') {
                                onPostDone(postId, false);
                            }
                            processNextPost();
                        }
                    },
                    error: function () {
                        if (typeof onPostDone === 'function') {
                            onPostDone(postId, false);
                        }
                        processNextPost(); // Skip on error
                    }
                });
            };

            processNextPost();
        },

        // Hand the form back to the browser after the queue finished.
        //
        // Two things have to happen: mark the button so this click is not
        // intercepted a second time, and tell the backend the images are
        // already handled. Without that flag the native bulk-edit request
        // re-runs the whole image pipeline for every selected post - each
        // unreachable URL burns 3 retries and wp_insert_post_data fires twice
        // per post, so a large batch stalls PHP until the gateway answers 502.
        resumeNativeSubmit: function (button) {
            if (!button || !button.length) {
                return;
            }

            button.data('smart-aui-processed', true);

            var $form = button.closest('form');
            if ($form.length && $form.find('input[name="w2p_smart_aui_processed"]').length === 0) {
                $form.append($('<input>').attr({
                    type: 'hidden',
                    name: 'w2p_smart_aui_processed',
                    value: '1'
                }));
            }

            button.click();
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
                    if (r.success && r.data && r.data.processed_content) {
                        self.setEditorContent(r.data.processed_content);
                        // Backend already rewrote this content - the native
                        // submit only has to persist it.
                        self.resumeNativeSubmit(self.originalButton);
                        return;
                    }
                    // Processing did not report success: submit without the
                    // flag so the backend still gets a shot at the images.
                    if (self.originalButton) { self.originalButton.click(); }
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

            // Update the editor content (ensure it is refreshed)
            if (processedContent) {
                this.setEditorContent(processedContent);
            }

            // Set a global flag telling the backend not to process images again
            window.W2P_SMART_AUI_PROCESSED = true;

            // For Gutenberg, add custom metadata
            if (window.wp && window.wp.data && window.wp.data.dispatch) {
                try {
                    window.wp.data.dispatch('core/editor').editPost({
                        meta: { _w2p_smart_aui_processed: '1' }
                    });
                } catch (e) {
                }
            }

            if (this.originalButton && this.originalButton.length > 0) {

                // Set the flag to prevent further interception
                this.originalButton.attr('data-smart-aui-processed', 'true');
                this.originalButton.data('smart-aui-processed', true);

                // For the classic editor, add a hidden field
                var $form = this.originalButton.closest('form');
                if ($form.length > 0) {
                    var $hidden = $('<input>').attr({
                        type: 'hidden',
                        name: 'w2p_smart_aui_processed',
                        value: '1'
                    });
                    $form.append($hidden);
                }

                // Detect whether this is a Gutenberg editor
                if (window.wp && window.wp.data && window.wp.data.dispatch && window.wp.data.select) {
                    try {
                        // Try to get the editor store
                        var editorStore = window.wp.data.dispatch('core/editor');
                        if (editorStore && typeof editorStore.savePost === 'function') {
                            editorStore.savePost();
                        } else {
                            // Try to use core or click the button
                            console.warn('[Smart AUI] core/editor store not available, trying button click');
                            this.originalButton[0].click();
                        }
                    } catch (error) {
                        // If that fails, try clicking the button
                        this.originalButton[0].click();
                    }
                } else {
                    // Classic editor - click the button
                    this.originalButton[0].click();
                }
            } else {
                // If there is no button, submit the form directly
                var $form = $('#post');
                if ($form.length > 0) {
                    // Add a hidden field
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

            // Gutenberg editor
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

            // TinyMCE editor
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                tinyMCE.activeEditor.setContent(content);
                return;
            }

            // Classic textarea
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

            var migrateAlbums = this.settings && (this.settings.migrate_albums === true || this.settings.migrate_albums === '1' || this.settings.migrate_albums === 1);

            while ((match = imageRegex.exec(content)) !== null) {
                var src = match[1];
                if (src.indexOf('data:') === 0) continue;

                var isAlbumsPath = src.indexOf('/wp-content/uploads/albums/') !== -1;
                if (!(migrateAlbums && isAlbumsPath)) {
                    if (src.indexOf(siteUrl) === 0 || src.indexOf('/wp-content/') === 0) continue;
                }

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

            var migrateAlbums = this.settings && (this.settings.migrate_albums === true || this.settings.migrate_albums === '1' || this.settings.migrate_albums === 1);

            while ((match = imageRegex.exec(content)) !== null) {
                var imgTag = match[0];

                // Extract src
                var srcMatch = imgTag.match(/src=["']([^"']+)["']/i);
                if (!srcMatch) continue;
                var src = srcMatch[1];

                // If migrating albums, do not treat albums images as existing local images needing ID lookup
                if (migrateAlbums && src.indexOf('/wp-content/uploads/albums/') !== -1) {
                    continue;
                }

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
        findExternalVideos: function (content) {
            var videos = [];
            var siteUrl = window.location.origin;

            // Check if video capture is enabled and auto-capture on save is enabled
            if (!this.settings || !this.settings.capture_videos || !this.settings.auto_capture_videos_on_save) {
                return videos;
            }

            // Get base URL from settings if available
            var baseUrl = siteUrl;
            if (this.settings && this.settings.base_url) {
                baseUrl = this.settings.base_url.replace(/\/$/, '');
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

            var isLocalOrExcluded = function (src) {
                if (!src || typeof src !== 'string') return true;
                if (src.indexOf(siteUrl) === 0 || src.indexOf(baseUrl) === 0 || src.indexOf('/wp-content/') === 0 || src.indexOf('/wp-media/') === 0 || src.indexOf('data:') === 0) {
                    return true;
                }
                for (var i = 0; i < exclusions.length; i++) {
                    if (src.indexOf(exclusions[i]) !== -1) {
                        return true;
                    }
                }
                return false;
            };

            // 1. Find videos from <video src="..."> tags
            var videoSrcRegex = /<video[^>]+src=["']([^"']+)["'][^>]*>/gi;
            var match;
            while ((match = videoSrcRegex.exec(content)) !== null) {
                var src = match[1];
                if (!isLocalOrExcluded(src) && videos.indexOf(src) === -1) {
                    videos.push(src);
                }
            }

            // 2. Find videos from <source src="..."> tags within <video> elements
            var sourceSrcRegex = /<source[^>]+src=["']([^"']+)["'][^>]*>/gi;
            while ((match = sourceSrcRegex.exec(content)) !== null) {
                var src = match[1];
                if (!isLocalOrExcluded(src) && videos.indexOf(src) === -1) {
                    videos.push(src);
                }
            }

            // 3. Find videos from [video ...] shortcodes
            var shortcodeRegex = /\[video\b[^\]]*\b(?:mp4|src|webm|m4v|ogv|mov)=["']([^"']+)["'][^\]]*\]/gi;
            while ((match = shortcodeRegex.exec(content)) !== null) {
                var src = match[1];
                if (!isLocalOrExcluded(src) && videos.indexOf(src) === -1) {
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