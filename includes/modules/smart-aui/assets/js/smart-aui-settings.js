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
                    var html = '<ul class="w2p-log-list">';
                    $.each(response.data, function (index, item) {
                        html += '<li><span class="badgew2p-badge-warning">' + item.time + '</span> <code>' + item.url + '</code></li>';
                    });
                    html += '</ul>';
                    $container.html(html);
                } else {
                    $container.html('<p>' + w2pSmartAuiSettings.strings.no_logs + '</p>');
                }
            },
            error: function () {
                $container.html('<p class="w2p-error">' + w2pSmartAuiSettings.strings.error_loading + '</p>');
            }
        });
    }

    // Load logs immediately if container exists
    if ($("#w2p-smart-aui-logs-container").length) {
        loadSmartAuiLogs();
    }

    $("#w2p-smart-aui-clear-logs").on("click", function () {
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
                    if (response.success) {
                        if (window.w2p && w2p.toast) {
                            w2p.toast(w2pSmartAuiSettings.strings.logs_cleared, "success");
                        } else {
                            alert(w2pSmartAuiSettings.strings.logs_cleared);
                        }
                        $("#w2p-smart-aui-logs-container").html('<p>' + w2pSmartAuiSettings.strings.no_logs + '</p>');
                    } else {
                        var msg = response.data || w2pSmartAuiSettings.strings.unknown_error;
                        if (window.w2p && w2p.toast) {
                            w2p.toast(w2pSmartAuiSettings.strings.error_prefix + msg, "error");
                        } else {
                            alert(w2pSmartAuiSettings.strings.error_prefix + msg);
                        }
                    }
                },
                error: function () {
                    $btn.removeClass("w2p-btn-loading");
                    if (window.w2p && w2p.toast) {
                        w2p.toast(w2pSmartAuiSettings.strings.network_error, "error");
                    } else {
                        alert(w2pSmartAuiSettings.strings.network_error);
                    }
                }
            });
        };

        // Use custom confirm if available
        if (window.w2p && w2p.confirm) {
            w2p.confirm(w2pSmartAuiSettings.strings.confirm_clear, executeClear);
        } else if (confirm(w2pSmartAuiSettings.strings.confirm_clear)) {
            executeClear();
        }
    });
});
