(function ($) {
    'use strict';

    // Access localized variables
    const config = window.w2p_auto_publish_config || {};
    let isRunning = false;
    let processedCount = 0;
    let totalToProcess = config.draft_count || 0;
    let failedPostIds = [];

    // Initialize UI
    $(document).ready(function () {
        initializeUI();
        attachEvents();
        refreshStats(); // Initial stats load
    });

    function initializeUI() {
        if (totalToProcess === 0) {
            $('#w2p-start-publish').prop('disabled', true);
        }
        updateProgressUI(0);
    }

    function attachEvents() {
        // Page exit protection
        $(window).on('beforeunload', function (e) {
            if (isRunning) {
                e.preventDefault();
                return config.i18n.confirm_nav;
            }
        });

        $('#w2p-start-publish').on('click', startPublishing);
        $('#w2p-stop-publish').on('click', stopPublishing);
        $('#w2p-clean-logs').on('click', clearLogs);

        // Handle stats update from external shared script or other sources
        $(document).on('w2p_auto_publish_stats_refreshed', function (e, data) {
            handleStatsUpdate(data);
        });
    }

    function startPublishing() {
        if (isRunning) return;

        const btn = $(this);

        // Check for scheduled lock before starting
        refreshStats().done(function (response) {
            if (response.success && response.data.active_lock === 'scheduled') {
                w2p.toast(config.i18n.scheduled_running, 'warning');
                return;
            }

            isRunning = true;
            btn.prop('disabled', true).addClass('w2p-hidden');
            $('#w2p-stop-publish').removeClass('w2p-hidden');
            $('#w2p-publish-progress').removeClass('w2p-hidden');

            processedCount = 0;
            failedPostIds = [];
            updateProgressUI(0);
            processNext();
        });
    }

    function stopPublishing() {
        isRunning = false;
        // Also stop Smart AUI if running
        if (window.W2P_SmartAUI_Progress && window.W2P_SmartAUI_Progress.isProcessing) {
            window.W2P_SmartAUI_Progress.isProcessing = false;
        }
        $(this).addClass('w2p-hidden');
        $('#w2p-start-publish').removeClass('w2p-hidden').prop('disabled', false);
        $('.progress-text').text(config.i18n.stopping);
    }

    function clearLogs() {
        const btn = $(this);
        w2p.confirm(config.i18n.confirm_clear_logs, () => {
            w2p.loading(btn, true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'w2p_auto_publish_clean_logs',
                    nonce: config.nonce
                },
                success: function (response) {
                    w2p.loading(btn, false);
                    if (response.success) {
                        refreshStats();
                        w2p.toast(config.i18n.logs_cleared, 'success');
                    } else {
                        w2p.toast(config.i18n.error_clearing_logs, 'error');
                    }
                },
                error: function () {
                    w2p.loading(btn, false);
                    w2p.toast(config.i18n.network_error, 'error');
                }
            });
        });
    }

    function processNext() {
        if (!isRunning) {
            $('.progress-text').text(config.i18n.stopped);
            return;
        }

        // 1. Get stats to find next post ID, excluding failed ones
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'w2p_auto_publish_stats',
                nonce: config.nonce,
                exclude: failedPostIds
            },
            success: function (response) {
                if (response.success && response.data.next_post) {
                    const nextPost = response.data.next_post;
                    const postId = nextPost.id;
                    const title = nextPost.title;

                    $('.progress-text').html(`<strong>${config.i18n.preparing} ${postId}:</strong> ${title}`);

                    // 2. Decide if we use Smart AUI
                    // Check if Smart AUI is available and enabled via params
                    // Note: In module.php we need to make sure we pass smartAuiNonce if needed
                    // For now assuming existing logic holds
                    let smartAuiNonce = (typeof w2pSmartAuiParams !== 'undefined') ? w2pSmartAuiParams.nonce : '';

                    if (window.W2P_SmartAUI_Progress && smartAuiNonce) {
                        fetchAndProcessImages(postId, title, smartAuiNonce);
                    } else {
                        publishPost(postId, title);
                    }
                } else if (response.success && (response.data.draft_count === 0 || !response.data.next_post)) {
                    finishAll();
                } else {
                    finishAll();
                }
            },
            error: function () {
                $('.progress-text').text(config.i18n.retry_stats);
                setTimeout(processNext, 2000);
            }
        });
    }

    function fetchAndProcessImages(postId, title, smartAuiNonce) {
        // Inject custom style if not exists
        if ($('#w2p-auto-publish-custom-style').length === 0) {
            $('head').append('<style id="w2p-auto-publish-custom-style">.w2p-auto-publish-hidden { display: none !important; visibility: hidden !important; }</style>');
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'w2p_smart_aui_get_post_details',
                nonce: smartAuiNonce,
                post_id: postId
            },
            success: function (response) {
                if (response.success && response.data) {
                    const content = response.data.post_content;

                    // Check if Smart AUI logic is available
                    if (typeof W2P_SmartAUI_Progress === 'undefined') {
                        console.warn('Auto Publish: Smart AUI library not loaded, falling back to simple publish.');
                        publishPost(postId, title);
                        return;
                    }

                    // Scan for images manually using the available helper
                    const images = W2P_SmartAUI_Progress.findExternalImages(content);
                    const mediaItems = [];

                    // Map string URLs to object structure {url: '...', type: 'image'}
                    if (images && images.length > 0) {
                        images.forEach(function (url) {
                            mediaItems.push({ url: url, type: 'image' });
                        });
                    }

                    if (mediaItems.length > 0) {
                        $('.progress-text').html(`<strong>${config.i18n.processing} ${postId}:</strong> ${title} (${mediaItems.length} images)`);

                        W2P_SmartAUI_Progress.processId = 'auto_pub_' + postId + '_' + Date.now();
                        W2P_SmartAUI_Progress.isProcessing = true;

                        // HIDE UI: Force hide the backdrop
                        $('#w2p-smart-aui-backdrop').addClass('w2p-auto-publish-hidden');

                        // Use the CORRECT public method: processPostMedia
                        W2P_SmartAUI_Progress.processPostMedia(postId, content, mediaItems, function (processedContent) {
                            W2P_SmartAUI_Progress.hide();
                            $('#w2p-smart-aui-backdrop').removeClass('w2p-auto-publish-hidden');
                            publishPost(postId, title, processedContent);
                        });
                    } else {
                        publishPost(postId, title);
                    }
                } else {
                    publishPost(postId, title);
                }
            },
            error: function () {
                publishPost(postId, title);
            }
        });
    }

    function publishPost(postId, title, processedContent) {
        if (!isRunning) return;

        $('.progress-text').html(`<strong>${config.i18n.publishing} ${postId}:</strong> ${title}`);

        const postData = {
            action: 'w2p_auto_publish_process',
            nonce: config.nonce,
            exclude: failedPostIds
        };

        if (processedContent) {
            postData.post_content = processedContent;
            postData.skip_image_processing = 1;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: postData,
            success: function (response) {
                if (response.success) {
                    if (response.data && response.data.finished) {
                        finishAll();
                        return;
                    }
                    processedCount++;
                    const progress = totalToProcess > 0 ? (processedCount / totalToProcess) * 100 : 0;
                    updateProgressUI(progress);
                    refreshStats();

                    $('#w2p-smart-aui-preview-area').empty().removeClass('grid-mode');
                    processNext();
                } else {
                    handlePublishError(postId, response.data);
                }
            },
            error: function () {
                handlePublishError(postId, config.i18n.connection_error);
            }
        });
    }

    function handlePublishError(postId, msg) {
        $('.progress-text').html(`<span class="w2p-error">${config.i18n.error_prefix} ${postId}: ${msg}</span>`);
        failedPostIds.push(postId);
        processedCount++;
        setTimeout(processNext, 1000);
    }

    function finishAll() {
        $('.progress-text').text(config.i18n.all_finished);
        $('#w2p-stop-publish').addClass('w2p-hidden');
        $('#w2p-start-publish').removeClass('w2p-hidden').prop('disabled', true);
        if (window.w2p) {
            w2p.toast(config.i18n.all_finished, 'success');
        }
        updateProgressUI(100);
        isRunning = false;
    }

    function updateProgressUI(percent) {
        $('.progress-bar-inner').css('width', percent + '%');
        $('.w2p-manual-publish-controls .w2p-progress-counter').text(`${processedCount}/${totalToProcess}`);
    }

    function handleStatsUpdate(data) {
        totalToProcess = data.draft_count + processedCount;
        $('.w2p-manual-publish-controls .w2p-progress-counter').text(`${processedCount}/${totalToProcess}`);

        // Update Scheduled Task Status Panel
        const statusBox = $('#w2p-scheduled-task-status');
        if (data.active_lock === 'scheduled') {
            statusBox.removeClass('w2p-hidden');
            if (data.scheduled_status) {
                const title = data.scheduled_status.title || 'Unknown Post';
                const time = data.scheduled_status.time || '';
                statusBox.find('.status-detail').html(`${time} - Processing: <strong>${title}</strong>`);
            } else {
                statusBox.find('.status-detail').text(config.i18n.scheduled_running);
            }

            if (!isRunning) {
                $('#w2p-start-publish').prop('disabled', true).attr('title', config.i18n.scheduled_running);
            }
        } else {
            statusBox.addClass('w2p-hidden');

            if (!isRunning) {
                $('#w2p-start-publish').prop('disabled', data.draft_count === 0);
            }
        }

        updateLogs(data.logs);
    }

    function updateLogs(logs) {
        let logHtml = '';
        if (!logs || logs.length === 0) {
            logHtml = `<tr><td colspan="4">${config.i18n.no_activity}</td></tr>`;
        } else {
            logs.forEach(function (log) {
                const source = log.source || 'manual';
                const sourceLabel = (source === 'scheduled') ? config.i18n.scheduled : config.i18n.manual;
                // Note: We use template literals here. Ensure target browser support is decent (all modern ones do).
                logHtml += `<tr>
                    <td>${log.time}</td>
                    <td>${log.title} (ID: ${log.post_id})</td>
                    <td><span class="status-badge ${source}">${sourceLabel}</span></td>
                    <td><span class="status-badge ${log.status}">${log.status}</span></td>
                </tr>`;
            });
        }
        $('#w2p-publish-logs-body').html(logHtml);
    }

    function refreshStats() {
        return $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'w2p_auto_publish_stats',
                nonce: config.nonce
            },
            success: function (response) {
                if (response.success) {
                    $(document).trigger('w2p_auto_publish_stats_refreshed', [response.data]);
                }
            }
        });
    }

})(jQuery);
