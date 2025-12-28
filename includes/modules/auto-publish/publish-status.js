/**
 * Auto Publish Progress Polling
 */
jQuery(document).ready(function ($) {
    const statusBox = $('#w2p-scheduled-task-status');
    const statusDetail = statusBox.find('.status-detail');
    const nonce = (typeof w2pAutoPublishParams !== 'undefined') ? w2pAutoPublishParams.nonce : '';

    function refreshScheduledStatus() {
        if (!nonce) return;

        $.ajax({
            url: w2pAutoPublishParams.ajax_url,
            type: 'POST',
            data: {
                action: 'w2p_auto_publish_get_stats',
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    // Handle Scheduled Status Display
                    if (response.data.active_lock === 'scheduled' && response.data.scheduled_status) {
                        let status = response.data.scheduled_status;
                        statusBox.fadeIn();
                        statusDetail.html(
                            (w2pAutoPublishParams.l10n.processing || 'Currently processing Post ID:') +
                            ' <strong>' + status.post_id + '</strong> - ' + status.title
                        );
                    } else {
                        statusBox.fadeOut();
                    }

                    // Trigger a custom event for other listeners (like settings page logs)
                    $(document).trigger('w2p_auto_publish_stats_refreshed', [response.data]);
                }
            }
        });
    }

    // Initial check
    if (statusBox.length) {
        refreshScheduledStatus();
        // Periodic status refresh
        setInterval(refreshScheduledStatus, 20000); // 20 seconds - Reduce browser overhead
    }
});
