jQuery(document).ready(function ($) {
    $("#w2p-test-smtp").on("click", function () {
        var $btn = $(this);
        if ($btn.hasClass("w2p-btn-loading")) return;

        $btn.addClass("w2p-btn-loading");

        // Collect values from the form inputs
        // CSF uses names like w2p_settings[module_smtp-mailer][smtp_host]
        var settings = {};
        var fields = ['smtp_host', 'smtp_port', 'smtp_secure', 'smtp_auth', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name'];

        fields.forEach(function (field) {
            // Find input by attribute ending matches
            var $input = $("input[name$='[" + field + "]'], select[name$='[" + field + "]']");
            if ($input.length) {
                if ($input.attr('type') === 'checkbox') {
                    settings[field] = $input.is(':checked') ? 1 : 0;
                } else {
                    settings[field] = $input.val();
                }
            }
        });

        $.ajax({
            url: w2p_smtp_data.ajax_url,
            type: "POST",
            data: {
                action: "w2p_smtp_test",
                nonce: w2p_smtp_data.nonce,
                w2p_smtp_settings: settings
            },
            success: function (response) {
                $btn.removeClass("w2p-btn-loading");
                if (response.success) {
                    if (window.w2p && w2p.toast) {
                        w2p.toast(response.data || w2p_smtp_data.strings.success, "success");
                    } else {
                        alert(response.data || w2p_smtp_data.strings.success);
                    }
                } else {
                    var msg = response.data || w2p_smtp_data.strings.unknown_error;
                    if (window.w2p && w2p.toast) {
                        w2p.toast(w2p_smtp_data.strings.fail_prefix + msg, "error", 5000);
                    } else {
                        alert(w2p_smtp_data.strings.fail_prefix + msg);
                    }
                }
            },
            error: function (xhr, status, error) {
                $btn.removeClass("w2p-btn-loading");
                var errMsg = w2p_smtp_data.strings.network_error;
                if (xhr.responseText) {
                    console.error("SMTP Test Error:", xhr.responseText);
                }
                if (window.w2p && w2p.toast) {
                    w2p.toast(errMsg, "error");
                } else {
                    alert(errMsg);
                }
            }
        });
    });
});
