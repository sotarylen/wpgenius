/**
 * Smart AUI — Enhanced Media Attach (front-end)
 *
 * In the media library list view (Unattached filter), when the "Attach" dialog opens,
 * automatically search for posts whose content contains the attachment's core filename
 * and show them, so posts that reference the image (even with a differing remote path)
 * are listed by default.
 *
 * The native find_posts query is restricted to title-only by the child theme
 * (w2p_force_title_only), so we run our own content search via
 * w2p_smart_aui_find_posts_by_filename and inject the results into #find-posts-response.
 * Selecting a row still uses the native Select flow to finish the attach, and the URL
 * rewrite on attach happens server-side via the `wp_media_attach_action` hook.
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */
(function ($) {
    'use strict';

    var params = window.w2pSmartAuiAttachEnhance || {};

    function enhanceAttach() {
        if (typeof window.findPosts === 'undefined' || !window.findPosts.open) {
            return false;
        }

        var originalOpen = window.findPosts.open;

        window.findPosts.open = function (af_name, af_val) {
            originalOpen.apply(this, arguments);

            var attachmentId = af_val || $('#affected').val();
            if (!attachmentId || !params.ajax_url || !params.nonce) {
                return;
            }

            // 1. Resolve the attachment's core filename.
            $.post(
                params.ajax_url,
                {
                    action: 'w2p_smart_aui_get_attachment_filename',
                    attachment_id: attachmentId,
                    nonce: params.nonce
                },
                function (res) {
                    if (!res || !res.success || !res.data) {
                        return;
                    }
                    var term = res.data.core || res.data.filename;
                    if (!term) {
                        return;
                    }
                    $('#find-posts-input').val(term);

                    // 2. Search posts whose CONTENT contains this filename (own action, bypasses
                    //    the child theme's title-only find_posts restriction).
                    $.post(
                        params.ajax_url,
                        {
                            action: 'w2p_smart_aui_find_posts_by_filename',
                            core: term,
                            nonce: params.nonce
                        },
                        function (resp) {
                            var $resp = $('#find-posts-response');
                            if (resp && resp.success && resp.data) {
                                $resp.html(resp.data);
                            } else {
                                $resp.html('<div class="error"><p>' +
                                    (window.wp && wp.i18n ? wp.i18n.__('No posts found containing this filename.') : 'No posts found containing this filename.') +
                                    '</p></div>');
                            }
                        }
                    );
                }
            );
        };

        return true;
    }

    $(function () {
        if (!enhanceAttach()) {
            setTimeout(enhanceAttach, 300);
        }
    });
})(jQuery);
