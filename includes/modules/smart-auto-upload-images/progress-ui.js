/**
 * Smart Auto Upload Images Progress UI
 */
(function ($) {
    'use strict';

    var progressUI = {
        isProcessing: false,
        checkInterval: null,
        isIntercepting: false,
        originalButton: null,
        processId: '',

        generateProcessId: function () {
            return 'proc_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        init: function () {
            this.bindEvents();
            this.hookIntoSave();
        },



        bindEvents: function () {
            $('#w2p-smart-aui-close-btn').on('click', function () {
                progressUI.hide();
            });

            $('#w2p-smart-aui-cancel-btn').on('click', function () {
                progressUI.cancel();
            });

            $('#w2p-smart-aui-backdrop').on('click', function (e) {
                if (e.target === this && !progressUI.isProcessing) {
                    progressUI.hide();
                }
            });
        },

        hookIntoSave: function () {
            // Intercept publish/update button clicks on Post Edit screen
            $(document).on('click', '#publish, .editor-post-publish-button, .editor-post-publish-panel__toggle', function (e) {
                // If we are already processing or have finished processing, let it proceed
                if (progressUI.isProcessing || $(this).data('smart-aui-processed')) {
                    return;
                }

                // Check for external images
                var content = progressUI.getEditorContent();
                var externalImages = progressUI.findExternalImages(content);

                if (externalImages.length > 0) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    progressUI.originalButton = $(this);
                    progressUI.startAsyncProcessing(content, externalImages);
                    return false;
                }
            });

            // Intercept Bulk Edit application on Posts List screen
            // Use Capture Phase to ensure we intercept before WP's own handlers
            document.addEventListener('click', function (e) {
                // Find click target (handle buttons with icons, etc.)
                var target = e.target;
                var bulkBtn = null;

                // Traverse up to find button
                while (target && target !== document) {
                    if (target.id === 'bulk_edit') {
                        bulkBtn = target;
                        break;
                    }
                    target = target.parentNode;
                }

                if (!bulkBtn) {
                    return;
                }

                console.log('[Smart AUI] Intercepted Bulk Edit Click on', bulkBtn);

                // Check active selection
                var checkedPosts = jQuery('input[name="post[]"]:checked');
                if (checkedPosts.length === 0) {
                    console.log('[Smart AUI] No posts selected');
                    return;
                }

                // Check if already processed
                if (jQuery(bulkBtn).data('smart-aui-processed')) {
                    console.log('[Smart AUI] Already processed, allowing default');
                    return;
                }

                // Stop EVERYTHING
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                progressUI.originalButton = jQuery(bulkBtn);

                // Collect IDs
                var postIds = [];
                checkedPosts.each(function () {
                    postIds.push(jQuery(this).val());
                });

                console.log('[Smart AUI] Starting processing for posts:', postIds);
                progressUI.startBulkProcessing(postIds);
                return false;
            }, true); // true = Capture Phase
        },

        startBulkProcessing: function (postIds) {
            this.show();
            this.updateStatus('正在准备处理批量文章...', true);
            this.updateStats(postIds.length, 0, 0, 0);

            this.processId = this.generateProcessId();

            var self = this;
            // 启动进度轮询
            this.monitorProgress();

            // Queue based processing
            var queue = postIds.slice();
            var total = postIds.length;
            var processed = 0;

            var processNext = function () {
                if (queue.length === 0) {
                    // All done
                    progressUI.finishBulkProcessing();
                    return;
                }

                var postId = queue.shift();
                processed++;
                var title = $('#post-' + postId + ' .row-title').text() || ('ID: ' + postId);
                progressUI.updateStatus('正在处理: ' + title + ' (' + processed + '/' + total + ')...', true);

                // Generate a new process ID for each post to ensure strict isolation
                self.processId = self.generateProcessId();

                // We use the same backend endpoint but with a flag or just the ID implies server-side fetch
                // Actually we need a new endpoint that fetches content from DB, processes, and updates DB
                $.ajax({
                    url: w2pSmartAuiParams.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_smart_aui_bulk_process',
                        nonce: w2pSmartAuiParams.nonce,
                        post_id: postId,
                        process_id: self.processId
                    },
                    success: function (response) {
                        // We can parse stats from response if needed, but for bulk 
                        // we mainly care about "Done this post"
                        // Maybe update stats based on what backend returns
                        if (response.success && response.data && response.data.stats) {
                            var s = response.data.stats;
                            // Accumulate stats could be complex, for now just show progress bar of POSTS
                            // Or we can try to show total images processed
                        }

                        // Update UI progress bar based on Posts count
                        // progressUI.updateStats(total, processed, 0, processed);

                        // Next
                        processNext();
                    },
                    error: function () {
                        // Log error but continue
                        console.error('Failed to process post ' + postId);
                        processNext();
                    }
                });
            };

            // Start queue
            processNext();
        },

        finishBulkProcessing: function () {
            this.updateStatus('✅ 批量处理完成！正在应用修改...', false);
            var self = this;
            setTimeout(function () {
                self.hide();
                // Trigger original click
                if (self.originalButton) {
                    self.originalButton.data('smart-aui-processed', true);
                    self.originalButton.click();
                }
            }, 1000);
        },

        startAsyncProcessing: function (content, images) {
            this.show();
            this.updateStatus('正在准备处理图片...', true);
            this.updateStats(images.length, 0, 0, 0);

            this.processId = this.generateProcessId();

            var self = this;
            var postId = $('#post_ID').val();

            // 如果是 Gutenberg，ID 可能需要其他方式获取
            if (!postId && wp && wp.data && wp.data.select('core/editor')) {
                postId = wp.data.select('core/editor').getCurrentPostId();
            }

            // 立即开始轮询进度
            self.monitorProgress();

            $.ajax({
                url: w2pSmartAuiParams.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_smart_aui_process_content', // New endpoint
                    nonce: w2pSmartAuiParams.nonce,
                    post_id: postId,
                    content: content,
                    process_id: self.processId
                },
                success: function (response) {
                    if (response.success) {
                        // 处理完成，使用返回的新内容
                        var processedContent = response.data.processed_content || content;
                        self.finishProcessing(processedContent);
                    } else {
                        self.handleError(response.data || 'Failed to process content');
                    }
                },
                error: function (xhr, status, error) {
                    self.handleError('Connection error: ' + error);
                }
            });
        },

        monitorProgress: function () {
            var self = this;
            // 清除之前的
            this.stopMonitoring();

            this.checkInterval = setInterval(function () {
                self.checkProgress();
            }, 1000);
        },

        checkProgress: function () {
            var self = this;

            if (!self.processId) return;

            $.ajax({
                url: w2pSmartAuiParams.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_smart_aui_get_progress',
                    nonce: w2pSmartAuiParams.nonce,
                    process_id: self.processId
                },
                success: function (response) {
                    if (response.success && response.data) {
                        var data = response.data;

                        self.updateStats(
                            data.total || 0,
                            data.success || 0,
                            data.failed || 0,
                            data.processed || 0
                        );

                        if (data.current_url) {
                            self.updateCurrentImage(data.current_url);
                        }
                    }
                },
                error: function () {
                    // Ignore transient errors
                }
            });
        },

        finishProcessing: function (processedContent) {
            this.stopMonitoring();
            this.updateStatus('✅ 图片处理完成！正在保存文章...', false);

            console.log('[Smart AUI] Processing finished. Content length:', processedContent ? processedContent.length : 0);

            var self = this;
            setTimeout(function () {
                self.hide();
                self.submitForm(processedContent);
            }, 500);
        },

        submitForm: function (processedContent) {
            // Update editor content with processed content (local URLs)
            if (processedContent) {
                this.setEditorContent(processedContent);
            }

            // Mark button as processed to bypass interception
            if (this.originalButton) {
                // Ensure the flag is set on the DOM element
                this.originalButton.attr('data-smart-aui-processed', 'true');
                this.originalButton.data('smart-aui-processed', true);

                // For Gutenberg
                if (window.wp && window.wp.data && window.wp.data.dispatch && window.wp.data.dispatch('core/editor')) {
                    // Trigger save
                    window.wp.data.dispatch('core/editor').savePost();
                } else {
                    // For Classic
                    this.originalButton[0].click();
                }
            } else {
                // Fallback for classic editor direct submit
                $('#post').submit();
            }
        },

        getEditorContent: function () {
            // Gutenberg
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostContent();
            }
            // Classic Editor
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                return tinyMCE.activeEditor.getContent();
            }
            // Text mode
            var $content = $('#content');
            if ($content.length > 0) {
                return $content.val();
            }
            return null;
        },

        setEditorContent: function (content) {
            // Gutenberg - Use editPost instead of resetBlocks
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && window.wp.data.dispatch('core/editor')) {
                wp.data.dispatch('core/editor').editPost({ content: content });
                return;
            }
            // Classic Editor
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                tinyMCE.activeEditor.setContent(content);
                return;
            }
            // Text mode
            $('#content').val(content);
        },

        findExternalImages: function (content) {
            var images = [];
            var imageRegex = /<img[^>]+src=["']([^"']+)["'][^>]*>/gi;
            var match;
            var siteUrl = window.location.origin;

            while ((match = imageRegex.exec(content)) !== null) {
                var src = match[1];

                // 跳过本地图片
                if (src.indexOf(siteUrl) === 0 || src.indexOf('/wp-content/') === 0) {
                    continue;
                }

                // 跳过data URL
                if (src.indexOf('data:') === 0) {
                    continue;
                }

                images.push(src);
            }

            return images;
        },

        show: function () {
            this.isProcessing = true;
            $('#w2p-smart-aui-backdrop').fadeIn(200);
        },

        hide: function () {
            this.isProcessing = false;
            this.stopMonitoring();
            $('#w2p-smart-aui-backdrop').fadeOut(200);
        },

        cancel: function () {
            if (confirm('确定要取消图片上传吗？')) {
                this.hide();
                this.isProcessing = false;
            }
        },

        stopMonitoring: function () {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
                this.checkInterval = null;
            }
        },

        handleError: function (message) {
            this.stopMonitoring();
            this.updateStatus('❌ 错误: ' + message);
            alert('处理出错: ' + message);
            this.hide();
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

            // 更新进度条
            if (total > 0) {
                var percentage = (processed / total) * 100;
                $('.w2p-smart-aui-progress-fill').css('width', percentage + '%');
            }
        },

        updateCurrentImage: function (url) {
            var displayUrl = url.length > 60 ? url.substring(0, 60) + '...' : url;
            $('#w2p-smart-aui-current-url').text(displayUrl);

            // Update Preview
            var $previewImg = $('#w2p-smart-aui-preview-img');
            var $placeholder = $('.w2p-smart-aui-preview-placeholder');

            // Reset state
            $previewImg.hide();
            $placeholder.show();

            // Try to load image
            var img = new Image();
            img.onload = function () {
                $previewImg.attr('src', url).show();
                $placeholder.hide();
            };
            img.src = url;
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        progressUI.init();
    });

})(jQuery);
