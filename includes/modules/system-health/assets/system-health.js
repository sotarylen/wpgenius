jQuery(document).ready(function ($) {
    $('.w2p-health-action').on('click', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var action = $btn.data('action');
        var $card = $btn.closest('.w2p-health-card');
        var $count = $card.find('.w2p-health-count');

        if (!confirm(w2pSystemHealth.confirm)) {
            return;
        }

        $btn.prop('disabled', true).text(w2pSystemHealth.cleaning);

        $.post(w2pSystemHealth.ajax_url, {
            action: 'w2p_system_health_clean',
            cleanup_type: action,
            nonce: w2pSystemHealth.nonce
        }, function (response) {
            if (response.success) {
                $count.text('0');
                showMessage(response.data.message, 'success');
            } else {
                showMessage(response.data.message || 'Error occurred', 'error');
            }
            $btn.prop('disabled', false).text(getButtonText(action));
        }).fail(function () {
            showMessage('Network error', 'error');
            $btn.prop('disabled', false).text(getButtonText(action));
        });
    });

    function showMessage(msg, type) {
        var $msg = $('#w2p-health-message');
        $msg.removeClass('w2p-notice-success w2p-notice-error')
            .addClass('w2p-notice-' + type)
            .text(msg)
            .fadeIn();

        setTimeout(function () {
            $msg.fadeOut();
        }, 5000);
    }

    function getButtonText(action) {
        switch (action) {
            case 'revisions': return 'Clean Revisions';
            case 'auto_drafts': return 'Clean Auto Drafts';
            case 'orphaned_meta': return 'Clean Orphaned Meta';
            case 'transients': return 'Clean Transients';
            default: return 'Clean Now';
        }
    }
});
