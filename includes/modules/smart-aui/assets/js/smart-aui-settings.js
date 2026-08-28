jQuery(document).ready(function ($) {
    // Function to load logs
    function loadSmartAuiLogs() {
        var $container = $("#w2p-smart-aui-logs-container");
        $container.html('<p class="description">' + w2pSmartAuiSettings.strings.loading + '</p>');

        $.ajax({
            url: w2pSmartAuiSettings.ajax_url,
            type: "POST",
            data: {
                action: "w2p_smart_aui_get_failed_logs",
                nonce: w2pSmartAuiSettings.nonce
            },
            success: function (response) {
                if (response.success && response.data && response.data.length > 0) {
                    var $list = $('<ul class="w2p-log-list"></ul>');
                    $.each(response.data, function (index, item) {
                        var $li = $('<li></li>');
                        $('<span></span>').addClass('w2p-badge w2p-badge-warning').text(item.time || '').appendTo($li);
                        $li.append(' ');
                        $('<code></code>').text(item.url || '').appendTo($li);
                        $list.append($li);
                    });
                    $container.empty().append($list);
                } else {
                    $container.empty().append($('<p></p>').text(w2pSmartAuiSettings.strings.no_logs));
                }
            },
            error: function () {
                $container.empty().append($('<p class="w2p-error"></p>').text(w2pSmartAuiSettings.strings.error_loading));
            }
        });
    }

    // Load logs immediately if container exists and is visible
    if ($("#w2p-smart-aui-logs-container").length) {
        loadSmartAuiLogs();
    }

    // Auto load logs when user switches to the Capture Failure Logs tab
    $(document).on("click", ".csf-tabbed-nav a, .csf-nav a, .csf-section a", function () {
        setTimeout(function () {
            if ($("#w2p-smart-aui-logs-container").is(":visible")) {
                loadSmartAuiLogs();
            }
        }, 150);
    });

    // Clear logs handler with event delegation
    $(document).on("click", "#w2p-smart-aui-clear-logs", function (e) {
        e.preventDefault();
        var $btn = $(this);

        // Define the execution logic
        var executeClear = function () {
            $btn.addClass("w2p-btn-loading");

            $.ajax({
                url: w2pSmartAuiSettings.ajax_url,
                type: "POST",
                data: {
                    action: "w2p_smart_aui_clear_failed_logs",
                    nonce: w2pSmartAuiSettings.nonce
                },
                success: function (response) {
                    $btn.removeClass("w2p-btn-loading");
                    if (response && response.success) {
                        if (window.w2p && typeof w2p.toast === 'function') {
                            w2p.toast(w2pSmartAuiSettings.strings.logs_cleared, "success");
                        } else {
                            alert(w2pSmartAuiSettings.strings.logs_cleared);
                        }
                        $("#w2p-smart-aui-logs-container").html('<p>' + w2pSmartAuiSettings.strings.no_logs + '</p>');
                    } else {
                        var msg = (response && response.data) ? response.data : w2pSmartAuiSettings.strings.unknown_error;
                        if (window.w2p && typeof w2p.toast === 'function') {
                            w2p.toast(w2pSmartAuiSettings.strings.error_prefix + msg, "error");
                        } else {
                            alert(w2pSmartAuiSettings.strings.error_prefix + msg);
                        }
                    }
                },
                error: function () {
                    $btn.removeClass("w2p-btn-loading");
                    if (window.w2p && typeof w2p.toast === 'function') {
                        w2p.toast(w2pSmartAuiSettings.strings.network_error, "error");
                    } else {
                        alert(w2pSmartAuiSettings.strings.network_error);
                    }
                }
            });
        };

        // Use custom confirm if available
        var confirmMsg = w2pSmartAuiSettings.strings.confirm_clear || 'Are you sure you want to clear all logs?';
        if (window.w2p && typeof w2p.confirm === 'function') {
            w2p.confirm(confirmMsg, executeClear);
        } else if (confirm(confirmMsg)) {
            executeClear();
        }
    });
});
