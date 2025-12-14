// 自动上传图片功能 - 简化版本
jQuery(document).ready(function($) {
    // 全局状态
    var isProcessing = false;
    var originalButtonText = '';
    var uploadQueue = [];
    var processedCount = 0;
    var successCount = 0;
    var failCount = 0;
    var concurrentThreads = 5;
    var maxRetries = 3;
    var startTime = Date.now();
    var retryAttempts = {};
    var shouldStopProcessing = false;
    
    // 模板元素引用
    var $backdrop = null;
    var $progressContainer = null;
    
    // 初始化
    function init() {
        if (typeof w2p_aui_params === 'undefined') {
            return;
        }
        
        bindPublishEvents();
        loadProgressTemplate();
    }
    
    // 绑定发布事件
    function bindPublishEvents() {
        $(document).on('click', '#publish, #save-post, .editor-post-publish-button, .editor-post-update-button', function(e) {
            if (isProcessing) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
            return handlePublishAttempt(e, $(this));
        });
        
        // 浮动图层控制事件
        $(document).on('click', '#w2p-aui-close-btn, #w2p-aui-backdrop', function() {
            closeProgressUI();
        });
        
        $(document).on('click', '#w2p-aui-minimize-btn', function() {
            toggleMinimize();
        });
        
        $(document).on('click', '#w2p-aui-stop-btn', function() {
            shouldStopProcessing = true;
            $('.w2p-aui-status').text('⏹️ 用户手动停止处理中...');
            $('#w2p-aui-stop-btn').hide();
            $('#w2p-aui-close-btn').show();
        });
    }
    
    // 处理发布尝试
    function handlePublishAttempt(e, $button) {
        originalButtonText = $button.val() || $button.text();
        var content = getEditorContent();
        if (!content) {
            return true;
        }
        
        var externalImages = findExternalImages(content);
        if (externalImages.length === 0) {
            return true;
        }
        
        e.preventDefault();
        e.stopImmediatePropagation();
        
        startImageProcessing(externalImages, $button);
        return false;
    }
    
    // 获取编辑器内容
    function getEditorContent() {
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            try {
                var content = wp.data.select('core/editor').getEditedPostContent();
                if (content) {
                    return content;
                }
            } catch (err) {
                // 静默处理
            }
        }
        
        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            var content = tinyMCE.activeEditor.getContent();
            if (content) {
                return content;
            }
        }
        
        var content = $('#content').val();
        if (content) {
            return content;
        }
        
        return '';
    }
    
    // 查找外链图片
    function findExternalImages(content) {
        var externalImages = [];
        var siteHostname = window.location.hostname;
        
        var $temp = $('<div>').html(content);
        var $images = $temp.find('img');
        
        $images.each(function(index) {
            var $img = $(this);
            var src = $img.attr('src');
            
            if (!src) {
                return;
            }
            
            if (src.indexOf('data:image') === 0) {
                return;
            }
            
            var absoluteSrc = src;
            if (src.indexOf('//') === 0) {
                absoluteSrc = window.location.protocol + src;
            } else if (src.indexOf('/') === 0) {
                absoluteSrc = window.location.origin + src;
            } else if (src.indexOf('http') !== 0) {
                absoluteSrc = window.location.origin + '/' + src.replace(/^\.\//, '');
            }
            
            try {
                var url = new URL(absoluteSrc);
                var isExternal = url.hostname !== siteHostname;
                
                if (isExternal) {
                    externalImages.push({
                        src: src,
                        absoluteSrc: absoluteSrc,
                        alt: $img.attr('alt') || ''
                    });
                }
            } catch (err) {
                // 静默处理URL解析错误
            }
        });
        
        return externalImages;
    }
    
    // 开始图片处理
    function startImageProcessing(images, $button) {
        isProcessing = true;
        uploadQueue = images;
        processedCount = 0;
        successCount = 0;
        failCount = 0;
        retryAttempts = {};
        shouldStopProcessing = false;
        
        // 从设置中获取线程数和重试次数
        concurrentThreads = w2p_aui_params.concurrent_threads || 5;
        maxRetries = w2p_aui_params.max_retries || 3;
        
        $button.val('🚀 处理图片中...').prop('disabled', true);
        
        showProgressUI(images.length);
        
        startMultiThreadedProcessing($button);
    }
    
    // 显示进度UI
    function showProgressUI(totalImages) {
        var $backdrop = $('#w2p-aui-backdrop');
        var $container = $('#w2p-aui-progress-container');
        
        if ($backdrop.length === 0 || $container.length === 0) {
            showSimpleProgressUI(totalImages);
            return;
        }
        
        $('#w2p-aui-progress').remove();
        
        // 确保浮动图层显示
        $backdrop.show();
        $container.show();
        
        // 设置内容
        $('#w2p-aui-total-images').text(totalImages);
        $('.w2p-aui-status').text('🚀 开始处理图片...');
        $('#w2p-aui-threads-active').text('0');
        
        // 控制按钮显示
        $('#w2p-aui-stop-btn').show();
        $('#w2p-aui-close-btn').hide();
        
        createBatchPreview();
    }
    
    // 简单进度显示（备用）
    function showSimpleProgressUI(totalImages) {
        var progressHtml = `
            <div id="w2p-aui-progress" class="w2p-simple-progress">
                <div class="w2p-simple-title">🚀 自动上传图片</div>
                <div id="w2p-aui-status">准备处理 ${totalImages} 张图片...</div>
                <div id="w2p-aui-current"></div>
            </div>
        `;
        
        $('body').append(progressHtml);
    }
    
    // 创建批量预览
    function createBatchPreview() {
        var $preview = $('.w2p-aui-batch-preview');
        if ($preview.length === 0) return;
        
        $preview.empty();
        
        if (uploadQueue.length === 0) {
            $preview.hide();
            return;
        }
        
        $preview.show();
        
        uploadQueue.forEach(function(image, index) {
            var $template = $('#w2p-aui-batch-item-template');
            if ($template.length) {
                var $item = $template.clone();
                $item.attr('id', null).removeClass('template').attr('data-index', index);
                $item.find('.w2p-aui-batch-image').attr('src', image.src);
                $item.find('.w2p-aui-batch-status').text('⏳ 等待');
                
                // 初始化图片状态
                image.status = 'pending';
                
                $preview.append($item);
            } else {
                image.status = 'pending';
            }
        });
    }
    
    // 多线程处理
    function startMultiThreadedProcessing($button) {
        var activeThreads = 0;
        var failedImages = [];
        var isProcessingComplete = false; // 添加完成标志
        var processedIndices = {}; // 记录已处理的图片索引，防止重复处理
        
        function startThread() {
            if (shouldStopProcessing) {
                activeThreads--;
                $('#w2p-aui-threads-active').text(activeThreads);
                return;
            }
            
            var nextImageIndex = getNextUnprocessedImage();
            
            if (nextImageIndex === -1) {
                activeThreads--;
                $('#w2p-aui-threads-active').text(activeThreads);
                
                if (activeThreads === 0) {
                    checkForCompletionWithProtection();
                }
                return;
            }
            
            // 标记图片已被分配处理，防止重复处理
            processedIndices[nextImageIndex] = true;
            
            var image = uploadQueue[nextImageIndex];
            
            activeThreads++;
            $('#w2p-aui-threads-active').text(activeThreads);
            
            $('.w2p-aui-status').text('🚀 线程 ' + activeThreads + ' 处理中: ' + (nextImageIndex + 1) + '/' + uploadQueue.length);
            $('.w2p-aui-current-image').text(image.src.length > 50 ? image.src.substring(0, 50) + '...' : image.src);
            
            // 标记图片状态
            image.status = 'processing';
            updateImageStatus(nextImageIndex, '🚀处理中', '#2271b1');
            
            $.ajax({
                url: w2p_aui_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_auto_upload_image',
                    nonce: w2p_aui_params.nonce,
                    image_url: image.absoluteSrc,
                    image_alt: image.alt,
                    post_id: $('#post_ID').val() || 0
                },
                timeout: 30000,
                success: function(response) {
                    if (shouldStopProcessing) {
                        return;
                    }
                    
                    if (response.success) {
                        successCount++;
                        image.status = 'success';
                        updateImageStatus(nextImageIndex, '✅ 成功', '#00a32a');
                        replaceImageUrl(image.src, response.data.new_url);
                        
                        // 如果是已存在的文件，也算作成功
                        if (response.data.is_existing) {
                            updateImageStatus(nextImageIndex, '♻️ 已存在', '#666666');
                        }
                        
                        processedCount++;
                        
                        // 成功处理后立即清理失败队列中的这个图片
                        removeFromFailedQueue(image);
                    } else {
                        image.status = 'failed';
                        handleFailedImage(nextImageIndex, image, '❌ 失败');
                        processedCount++;
                        
                        // 如果处理失败，释放索引锁，允许重试
                        delete processedIndices[nextImageIndex];
                    }
                },
                error: function(xhr, status, error) {
                    if (shouldStopProcessing) {
                        return;
                    }
                    image.status = 'failed';
                    handleFailedImage(nextImageIndex, image, '❌ 错误');
                    processedCount++;
                    
                    // 如果处理失败，释放索引锁，允许重试
                    delete processedIndices[nextImageIndex];
                },
                complete: function() {
                    activeThreads--;
                    $('#w2p-aui-threads-active').text(activeThreads);
                    updateProgress();
                    
                    if (shouldStopProcessing) {
                        if (activeThreads === 0) {
                            forceStopProcessing();
                        }
                        return;
                    }
                    
                    // 继续处理或检查完成
                    if (!shouldStopProcessing) {
                        // 在启动新线程前，先清理一次失败队列
                        cleanSuccessfulImagesFromQueue();
                        
                        var nextIndex = getNextUnprocessedImage();
                        if (nextIndex !== -1) {
                            setTimeout(startThread, Math.random() * 100 + 50);
                        } else {
                            // 立即检查完成，不等待
                            checkForCompletion();
                        }
                    }
                }
            });
        }
        
        function getNextUnprocessedImage() {
            for (var i = 0; i < uploadQueue.length; i++) {
                var image = uploadQueue[i];
                // 只有状态为'pending'且未被分配的图片才需要处理
                if (image.status === 'pending' && !processedIndices[i]) {
                    return i;
                }
            }
            return -1;
        }
        
        // 添加完成保护机制
        var completionCheckCount = 0;
        var maxCompletionChecks = 50; // 最大检查次数防止死循环
        
        function checkForCompletionWithProtection() {
            completionCheckCount++;
            
            if (completionCheckCount > maxCompletionChecks) {
                // 强制完成处理
                isProcessingComplete = true;
                finishProcessing();
                return;
            }
            
            checkForCompletion();
        }
        
        function updateImageStatus(index, status, color) {
            var $item = $('.w2p-aui-batch-item[data-index="' + index + '"]');
            if ($item.length > 0) {
                $item.find('.w2p-aui-batch-status').text(status).css('background', color);
            }
        }
        
        function handleFailedImage(index, image, status) {
            if (shouldStopProcessing) {
                return;
            }
            
            // 防止重复处理失败的图片
            if (image.status === 'success') {
                return; // 如果已经是成功状态，直接返回
            }
            
            failCount++;
            retryAttempts[image.src] = (retryAttempts[image.src] || 0) + 1;
            
            // 检查重试次数是否超过最大值
            if (retryAttempts[image.src] > maxRetries) {
                updateImageStatus(index, '❌ 失败', '#d63638');
                image.status = 'failed';
                
                // 从失败队列中移除（如果存在）
                removeFromFailedQueue(image);
            } else {
                updateImageStatus(index, '⏳ 重试中', '#ff8c00');
                image.status = 'pending'; // 立即重置为pending状态
                
                // 只有当图片不在失败队列中时才添加
                if (!isInFailedQueue(image)) {
                    failedImages.push({index: index, image: image});
                }
            }
        }
        
        function isInFailedQueue(image) {
            for (var i = 0; i < failedImages.length; i++) {
                if (failedImages[i].image === image) {
                    return true;
                }
            }
            return false;
        }
        
        function removeFromFailedQueue(image) {
            for (var i = failedImages.length - 1; i >= 0; i--) {
                if (failedImages[i].image === image) {
                    failedImages.splice(i, 1);
                    return;
                }
            }
        }
        
        // 清理已成功处理的失败队列图片
        function cleanSuccessfulImagesFromQueue() {
            for (var i = failedImages.length - 1; i >= 0; i--) {
                var failedItem = failedImages[i];
                var image = failedItem.image;
                
                // 如果图片状态是success，说明已经成功处理，从队列中移除
                if (image.status === 'success') {
                    failedImages.splice(i, 1);
                }
            }
        }
        
        function updateProgress() {
            var percentage = (successCount / uploadQueue.length) * 100;
            $('.w2p-aui-progress-fill').css('width', percentage + '%');
        }
        
        function checkForCompletion() {
            if (shouldStopProcessing) {
                forceStopProcessing();
                return;
            }
            
            if (isProcessingComplete) {
                return; // 已经完成，跳过
            }
            
            // 清理失败队列中的已成功图片
            cleanSuccessfulImagesFromQueue();
            
            // 检查是否有需要重试的图片
            if (failedImages.length > 0) {
                var canRetry = false;
                var currentFailedCount = failedImages.length;
                
                for (var i = 0; i < failedImages.length; i++) {
                    var img = failedImages[i].image;
                    var retries = retryAttempts[img.src] || 0;
                    // 只有在重试次数未超过最大值且状态不是success时才重试
                    if (retries < maxRetries && img.status !== 'success') {
                        canRetry = true;
                        break;
                    }
                }
                
                if (canRetry && currentFailedCount > 0) {
                    retryFailedImages();
                    return;
                } else {
                    // 清理所有失败的图片状态
                    failedImages = [];
                }
            }
            
            var pendingCount = 0;
            var processingCount = 0;
            var successCount_local = 0;
            var failedCount_local = 0;
            
            // 统计所有图片状态
            for (var i = 0; i < uploadQueue.length; i++) {
                var image = uploadQueue[i];
                var status = image.status || 'pending';
                
                if (status === 'pending') {
                    pendingCount++;
                } else if (status === 'processing') {
                    processingCount++;
                } else if (status === 'success') {
                    successCount_local++;
                } else if (status === 'failed') {
                    failedCount_local++;
                }
            }
            
            // 完成条件：没有待处理和处理中的图片，且没有活跃线程
            if (pendingCount === 0 && processingCount === 0 && activeThreads === 0) {
                isProcessingComplete = true; // 设置完成标志
                completionCheckCount = 0; // 重置计数器
                finishProcessing();
            } else {
                // 如果条件不满足，延迟重试
                setTimeout(checkForCompletionWithProtection, 1000);
            }
        }
        
        function forceStopProcessing() {
            isProcessingComplete = true;
            $('.w2p-aui-status').text('🛑 用户手动停止处理');
            
            setTimeout(function() {
                $('.w2p-aui-status').text('正在恢复发布按钮...');
                
                var $btn = $('#publish');
                if ($btn.length === 0) $btn = $('#save-post');
                
                if ($btn.length > 0) {
                    $btn.val(originalButtonText).prop('disabled', false);
                }
                
                isProcessing = false;
                shouldStopProcessing = false;
                $btn.trigger('click');
                
                setTimeout(function() {
                    closeProgressUI();
                }, 1000);
                
            }, 1000);
        }
        
        function retryFailedImages() {
            if (failedImages.length === 0) {
                setTimeout(checkForCompletion, 100);
                return;
            }
            
            var $backdrop = $('#w2p-aui-backdrop');
            var $container = $('#w2p-aui-progress-container');
            if ($backdrop.length > 0 && $container.length > 0) {
                $backdrop.show().css('display', 'block');
                $container.show().css('display', 'block');
            }
            
            $('.w2p-aui-status').text('🔄 重试失败的图片...');
            
            // 只重试真正失败的图片（状态为failed且不在处理中）
            var imagesToRetry = [];
            
            for (var i = 0; i < failedImages.length; i++) {
                var failedItem = failedImages[i];
                var image = failedItem.image;
                
                // 只有状态为failed且不在processedIndices中的图片才需要重试
                if (image.status === 'failed' && !processedIndices[failedItem.index]) {
                    imagesToRetry.push(failedItem);
                }
            }
            
            if (imagesToRetry.length === 0) {
                // 没有需要重试的图片，直接清理队列
                failedImages = [];
                setTimeout(checkForCompletion, 100);
                return;
            }
            
            // 重置需要重试的图片状态
            for (var i = 0; i < imagesToRetry.length; i++) {
                var failedItem = imagesToRetry[i];
                var image = failedItem.image;
                
                image.status = 'pending';
                updateImageStatus(failedItem.index, '⏳ 等待重试', '#ff8c00');
            }
            
            // 从原队列中移除已重置的图片
            for (var i = failedImages.length - 1; i >= 0; i--) {
                var failedItem = failedImages[i];
                var image = failedItem.image;
                
                if (image.status === 'pending') {
                    failedImages.splice(i, 1);
                }
            }
            
            // 启动重试线程
            var threadsToStart = Math.min(concurrentThreads, imagesToRetry.length);
            
            for (var i = 0; i < threadsToStart; i++) {
                setTimeout(startThread, i * 200);
            }
        }
        
        // 启动线程
        for (var i = 0; i < Math.min(concurrentThreads, uploadQueue.length); i++) {
            setTimeout(startThread, i * 100);
        }
    }
    
    // 替换内容中的图片URL
    function replaceImageUrl(oldUrl, newUrl) {
        try {
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                var content = wp.data.select('core/editor').getEditedPostContent();
                if (content && content.indexOf(oldUrl) !== -1) {
                    var newContent = content.replace(new RegExp(escapeRegExp(oldUrl), 'g'), newUrl);
                    wp.data.dispatch('core/editor').editPost({ content: newContent });
                }
            }
            
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                var content = tinyMCE.activeEditor.getContent();
                if (content && content.indexOf(oldUrl) !== -1) {
                    var newContent = content.replace(new RegExp(escapeRegExp(oldUrl), 'g'), newUrl);
                    tinyMCE.activeEditor.setContent(newContent);
                }
            }
            
            var content = $('#content').val();
            if (content && content.indexOf(oldUrl) !== -1) {
                var newContent = content.replace(new RegExp(escapeRegExp(oldUrl), 'g'), newUrl);
                $('#content').val(newContent);
            }
            
        } catch (err) {
            // 静默处理
        }
    }
    
    // 完成处理
    function finishProcessing() {
        // 防止重复调用
        if (!isProcessing) {
            return;
        }
        
        // 强制设置状态
        isProcessing = false;
        shouldStopProcessing = false;
        
        $('.w2p-aui-results').empty();
        
        var retryCount = 0;
        for (var key in retryAttempts) {
            retryCount += retryAttempts[key];
        }
        
        $('#w2p-aui-success-count').text(successCount);
        $('#w2p-aui-fail-count').text(failCount);
        $('#w2p-aui-process-time').text('处理时间: ' + Math.round((Date.now() - startTime) / 1000) + '秒');
        
        if (retryCount > 0) {
            $('#w2p-aui-retry-info').text(' | 重试次数: ' + retryCount);
        } else {
            $('#w2p-aui-retry-info').text('');
        }
        
        $('.w2p-aui-results').slideDown();
        $('.w2p-aui-status').text('🎉 处理完成!');
        
        // 延迟保存文章
        setTimeout(function() {
            $('.w2p-aui-status').text('正在保存文章...');
            
            var $btn = $('#publish');
            if ($btn.length === 0) {
                $btn = $('#save-post');
            }
            if ($btn.length === 0) {
                $btn = $('.editor-post-publish-button');
            }
            if ($btn.length === 0) {
                $btn = $('.editor-post-update-button');
            }
            
            if ($btn.length > 0) {
                if ($btn.val()) {
                    $btn.val(originalButtonText);
                } else {
                    $btn.text(originalButtonText);
                }
                $btn.prop('disabled', false);
                
                try {
                    var clickEvent = new MouseEvent('click', {
                        view: window,
                        bubbles: true,
                        cancelable: true
                    });
                    $btn[0].dispatchEvent(clickEvent);
                } catch (e) {
                    try {
                        $btn.trigger('click');
                    } catch (e2) {
                        try {
                            var $form = $btn.closest('form');
                            if ($form.length > 0) {
                                $form.submit();
                            }
                        } catch (e3) {
                            // 静默处理
                        }
                    }
                }
            }
            
            // 延迟关闭UI
            setTimeout(function() {
                closeProgressUI();
            }, 3000);
            
        }, 1500);
    }
    
    // 关闭进度UI
    function closeProgressUI() {
        var $backdrop = $('#w2p-aui-backdrop');
        var $container = $('#w2p-aui-progress-container');
        if ($backdrop.length > 0 && $container.length > 0) {
            $backdrop.hide();
            $container.hide();
        }
        
        $('#w2p-aui-progress').remove();
        
        isProcessing = false;
        shouldStopProcessing = false;
        uploadQueue = [];
        processedCount = 0;
        successCount = 0;
        failCount = 0;
        retryAttempts = {};
    }
    
    // 切换最小化
    function toggleMinimize() {
        var $container = $('#w2p-aui-progress-container');
        var $preview = $('.w2p-aui-batch-preview');
        var $progress = $('.w2p-aui-progress-bar');
        var $status = $('.w2p-aui-status');
        var $current = $('.w2p-aui-current-image');
        var $results = $('.w2p-aui-results');
        
        if ($container.css('height') === '60px') {
            $preview.show();
            $progress.show();
            $status.show();
            $current.show();
            $results.show();
            $container.css('height', 'auto');
            $('#w2p-aui-minimize-btn').text('−');
        } else {
            $preview.hide();
            $progress.hide();
            $status.hide();
            $current.hide();
            $results.hide();
            $container.css('height', '60px');
            $('#w2p-aui-minimize-btn').text('+');
        }
    }
    
    // 加载进度模板
    function loadProgressTemplate() {
        $.ajax({
            url: w2p_aui_params.ajax_url,
            type: 'POST',
            data: {
                action: 'w2p_aui_load_progress_template',
                nonce: w2p_aui_params.nonce
            },
            success: function(response) {
                if (response.success && response.data.template) {
                    $('body').append(response.data.template);
                    initializeTemplateElements();
                    applyI18n();
                } else {
                    showSimpleProgressUI(0);
                }
            },
            error: function() {
                showSimpleProgressUI(0);
            }
        });
    }
    
    function initializeTemplateElements() {
        $backdrop = $('#w2p-aui-backdrop');
        $progressContainer = $('#w2p-aui-progress-container');
    }
    
    function applyI18n() {
        $('[data-i18n]').each(function() {
            var key = $(this).attr('data-i18n');
            if (w2p_aui_params.messages[key]) {
                $(this).text(w2p_aui_params.messages[key]);
            }
        });
    }
    
    // 转义正则表达式
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // 启动
    init();
});
