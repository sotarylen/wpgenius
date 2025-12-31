jQuery(document).ready(function ($) {
    $('#tabs').tabs();

    var uploadForm = $('#word_to_posts_upload_form');
    var scanForm = $('#word_to_posts_scan_form');
    var cleanForm = $('#word_to_posts_clean_form');
    var logDiv = $('#word-to-posts-log-upload');
    var cleanLogDiv = $('#word-to-posts-log-clean');


    // 处理上传表单的通知
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

    // 处理扫描表单的通知
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

    // 处理清理表单的通知
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

});


