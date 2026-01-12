jQuery(document).ready(function ($) {
    $('#w2p-wechat-sync-btn').on('click', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var $status = $('#w2p-wechat-sync-status');
        var postId = $('#post_ID').val();

        if ($btn.prop('disabled')) return;

        $btn.prop('disabled', true).text(w2p_wechat_admin.i18n.pushing);
        $status.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'w2p_wechat_push_draft',
                post_id: postId,
                nonce: w2p_wechat_admin.nonce
            },
            success: function (response) {
                if (response.success) {
                    $status.html('<span style="color:green;">' + response.data + '</span>');
                } else {
                    $status.html('<span style="color:red;">' + response.data + '</span>');
                }
            },
            error: function () {
                $status.html('<span style="color:red;">' + w2p_wechat_admin.i18n.error + '</span>');
            },
            complete: function () {
                $btn.prop('disabled', false).text(w2p_wechat_admin.i18n.push_btn);
            }
        });
    });
});
