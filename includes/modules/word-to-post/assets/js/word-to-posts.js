jQuery(document).ready(function ($) {
    $('#tabs').tabs();

    var uploadForm = $('#word_to_posts_upload_form');
    var scanForm = $('#word_to_posts_scan_form');
    var cleanForm = $('#word_to_posts_clean_form');
    var fixIndexForm = $('#word_to_posts_fix_index_form');
    var logDiv = $('#word-to-posts-log-upload');
    var cleanLogDiv = $('#word-to-posts-log-clean');
    var fixIndexLogDiv = $('#word-to-posts-log-fix-index');

    // Fix Chapter Index "Save Configuration"
    fixIndexForm.on('submit', function (e) {
        e.preventDefault();

        var submitBtn = $(this).find('button[type="submit"]');
        submitBtn.addClass('w2p-btn-loading');

        var requestData = {
            action: 'fix_chapter_index_save_config',
            word_to_posts_fix_index_nonce: $('input[name="word_to_posts_fix_index_nonce"]').val(),
            target_post_type: $('#w2p_target_post_type').val(),
            index_format: $('#w2p_index_format').val(),
            index_connector: $('#w2p_index_connector').val(),
            auto_volume: $('#w2p_auto_volume').is(':checked') ? '1' : '0',
            batch_size: $('#w2p_batch_size').val()
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            success: function (response) {
                submitBtn.removeClass('w2p-btn-loading');
                if (response.success) {
                    showToast('Configuration saved.', 'success');
                } else {
                    showToast('Error: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                submitBtn.removeClass('w2p-btn-loading');
                showToast('Network Error: ' + error, 'error');
            }
        });
    });

    // 处理其他表单 (Original Logic Preserved)
    uploadForm.on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        logDiv.html('<p>' + word_to_posts_params.starting_import + '</p>').show();

        $.ajax({
            url: uploadForm.attr('action'),
            type: uploadForm.attr('method'),
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                logDiv.empty();
                if (response.success) {
                    response.data.forEach(function (message) {
                        logDiv.append('<p>' + message + '</p>');
                    });
                } else {
                    logDiv.append('<p>' + word_to_posts_params.error + ': ' + response.data + '</p>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                logDiv.append('<p>' + word_to_posts_params.error + ': ' + errorThrown + '</p>');
            }
        });
    });

    scanForm.on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        cleanLogDiv.html('<p>' + word_to_posts_params.scanning + '</p>').show();
        $.ajax({
            url: scanForm.attr('action'),
            type: scanForm.attr('method'),
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                cleanLogDiv.empty();
                if (response.success) {
                    response.data.forEach(function (message) {
                        cleanLogDiv.append('<p>' + message + '</p>');
                    });
                } else {
                    cleanLogDiv.append('<p>' + word_to_posts_params.error + ': ' + response.data + '</p>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                cleanLogDiv.append('<p>' + word_to_posts_params.error + ': ' + errorThrown + '</p>');
            }
        });
    });

    cleanForm.on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        cleanLogDiv.html('<p>' + word_to_posts_params.cleaning + '</p>').show();
        $.ajax({
            url: cleanForm.attr('action'),
            type: cleanForm.attr('method'),
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                cleanLogDiv.empty();
                if (response.success) {
                    response.data.forEach(function (message) {
                        cleanLogDiv.append('<p>' + message + '</p>');
                    });
                } else {
                    cleanLogDiv.append('<p>' + word_to_posts_params.error + ': ' + response.data + '</p>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                cleanLogDiv.append('<p>' + word_to_posts_params.error + ': ' + errorThrown + '</p>');
            }
        });
    });

    // Fix Chapter Index Batch Processing
    var isFixing = false;
    var fixTotal = 0;
    var fixProcessed = 0;
    var fixContext = {};

    $('#w2p-start-fix-btn').on('click', function () {
        if (isFixing) return;

        // Reset UI
        fixIndexLogDiv.empty().append('<tr><td colspan="3">' + (word_to_posts_params.starting_import || 'Processing...') + '</td></tr>');
        $('#w2p-fix-progress-bar').css('width', '0%');
        $('#w2p-fix-status-count').text('0 / 0');
        $('#w2p-fix-status-text').text(word_to_posts_params.starting_import || 'Initializing...');

        // Manually build config object
        var requestData = {
            action: 'fix_chapter_index_init',
            word_to_posts_fix_index_nonce: $('input[name="word_to_posts_fix_index_nonce"]').val(),
            target_post_type: $('#w2p_target_post_type').val(),
            index_format: $('#w2p_index_format').val(),
            index_connector: $('#w2p_index_connector').val(),
            auto_volume: $('#w2p_auto_volume').is(':checked') ? '1' : '0',
            batch_size: $('#w2p_batch_size').val()
        };

        isFixing = true;
        toggleFixButtons(true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            success: function (response) {
                if (response.success) {
                    fixTotal = response.data.total;
                    fixProcessed = 0;
                    fixContext = {};

                    $('#w2p-fix-status-count').text('0 / ' + fixTotal);
                    $('#w2p-fix-status-text').text('Processing...');

                    if (fixTotal > 0) {
                        processFixBatch();
                    } else {
                        finishFixing('No posts found.');
                    }
                } else {
                    finishFixing('Error: ' + response.data, true);
                }
            },
            error: function (xhr, status, error) {
                finishFixing('Network Error: ' + error, true);
            }
        });
    });

    $('#w2p-stop-fix-btn').on('click', function () {
        isFixing = false;
        $('#w2p-fix-status-text').text('Stopping...');
    });

    function processFixBatch() {
        if (!isFixing) {
            finishFixing('Stopped by user.');
            return;
        }

        if (fixProcessed >= fixTotal) {
            finishFixing('Completed successfully.');
            return;
        }

        // Serialize form data
        var formParams = fixIndexForm.serializeArray();
        var requestData = {};
        $.each(formParams, function (i, field) {
            requestData[field.name] = field.value;
        });

        // Set action and batch parameters
        requestData.action = 'fix_chapter_index_process';
        requestData.offset = fixProcessed;

        if (fixContext.vol_name) requestData.current_context_vol_name = fixContext.vol_name;
        if (fixContext.vol_idx) requestData.current_context_vol_idx = fixContext.vol_idx;
        if (fixContext.chap_idx) requestData.current_context_chap_idx = fixContext.chap_idx;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            success: function (response) {
                if (response.success) {
                    var logs = response.data.log || [];
                    var nextContext = response.data.next_context || {};
                    var batchCount = logs.length;

                    fixContext = nextContext;

                    logs.forEach(function (item) {
                        var row = $('<tr></tr>');
                        row.append('<td>' + item.index + '</td>');
                        row.append('<td>' + item.volume + '</td>');
                        row.append('<td>' + item.title + '</td>');
                        fixIndexLogDiv.append(row);
                    });

                    fixProcessed += batchCount;
                    var percent = Math.min(100, Math.round((fixProcessed / fixTotal) * 100));
                    $('#w2p-fix-progress-bar').css('width', percent + '%');
                    $('#w2p-fix-status-count').text(fixProcessed + ' / ' + fixTotal);

                    var logContainer = fixIndexLogDiv.closest('.w2p-log-container');
                    logContainer.scrollTop(logContainer[0].scrollHeight);

                    if (batchCount > 0 && fixProcessed < fixTotal) {
                        processFixBatch();
                    } else {
                        finishFixing('Done.');
                    }
                } else {
                    finishFixing('Batch Error: ' + response.data, true);
                }
            },
            error: function (xhr, status, error) {
                finishFixing('Network Error: ' + error, true);
            }
        });
    }

    function toggleFixButtons(processing) {
        if (processing) {
            $('#w2p-start-fix-btn').hide();
            $('#w2p-stop-fix-btn').show();
            $('#word_to_posts_fix_index_form :input').prop('disabled', true);
        } else {
            $('#w2p-start-fix-btn').show();
            $('#w2p-stop-fix-btn').hide();
            $('#word_to_posts_fix_index_form :input').prop('disabled', false);
        }
    }

    function finishFixing(msg, isError) {
        isFixing = false;
        toggleFixButtons(false);
        $('#w2p-fix-status-text').text(msg).css('color', isError ? 'red' : 'green');

        if (isError) {
            showToast(msg, 'error');
        } else if (msg === 'Done.' || msg === 'Completed successfully.') {
            showToast(msg, 'success');
        }
    }

    // Toast Helper
    function showToast(message, type = 'success', duration = 3000) {
        if (typeof w2p !== 'undefined' && w2p.toast) {
            w2p.toast(message, type, duration);
            return;
        }

        var container = $('.w2p-toast-container');
        if (container.length === 0) {
            container = $('<div class="w2p-toast-container"></div>').appendTo('body');
        }

        var icon = type === 'success' ? '<i class="fa-solid fa-check-circle"></i>' :
            type === 'error' ? '<i class="fa-solid fa-circle-exclamation"></i>' :
                '<i class="fa-solid fa-circle-info"></i>';

        var toast = $(
            '<div class="w2p-toast ' + type + '">' +
            '<div class="w2p-toast-icon">' + icon + '</div>' +
            '<div class="w2p-toast-message">' + message + '</div>' +
            '<div class="w2p-toast-close"><i class="fa-solid fa-xmark"></i></div>' +
            '</div>'
        );

        container.append(toast);
        setTimeout(function () {
            toast.addClass('fade-out');
            setTimeout(function () { toast.remove(); }, 300);
        }, duration);

        toast.find('.w2p-toast-close').on('click', function () {
            toast.addClass('fade-out');
            setTimeout(function () { toast.remove(); }, 300);
        });
    }
    window.w2p_show_toast = showToast;
});
