/**
 * WP Genius - Auto Publish Module
 */

(function ($) {
    'use strict';

    window.WPGenius = window.WPGenius || {};

    WPGenius.AutoPublish = {
        init: function () {
            this.statusBox = $('#w2p-scheduled-task-status');
            if (this.statusBox.length) {
                this.refreshScheduledStatus();
                setInterval(this.refreshScheduledStatus.bind(this), 20000);
            }
        },

        refreshScheduledStatus: function () {
            if (!window.w2pAutoPublishParams || !w2pAutoPublishParams.nonce) return;

            $.ajax({
                url: w2pAutoPublishParams.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_auto_publish_get_stats',
                    nonce: w2pAutoPublishParams.nonce
                },
                success: this.handleStatsResponse.bind(this)
            });
        },

        handleStatsResponse: function (response) {
            if (response.success) {
                var statusBox = this.statusBox;
                var statusDetail = statusBox.find('.status-detail');

                if (response.data.active_lock === 'scheduled' && response.data.scheduled_status) {
                    var status = response.data.scheduled_status;
                    statusBox.fadeIn();
                    statusDetail.html(
                        (w2pAutoPublishParams.l10n.processing || 'Currently processing Post ID:') +
                        ' <strong>' + status.post_id + '</strong> - ' + status.title +
                        (status.image_progress ? ' <span style="display:block;margin-top:5px;font-style:italic;color:#666;">' + status.image_progress + '</span>' : '')
                    );
                } else {
                    statusBox.fadeOut();
                }

                // Trigger custom event
                $(document).trigger('w2p_auto_publish_stats_refreshed', [response.data]);
            }
        }
    };

    $(document).ready(function () {
        if (window.w2pAutoPublishParams) WPGenius.AutoPublish.init();
    });

})(jQuery);
