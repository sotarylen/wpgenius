/**
 * WP Genius - AI Assistant Module
 */

(function ($) {
    'use strict';

    window.WPGenius = window.WPGenius || {};

    WPGenius.AIAssistant = {
        init: function () {
            $('.w2p-ai-action').on('click', this.handleAction.bind(this));
        },

        handleAction: function (e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var type = $btn.data('action');
            var $status = $('#w2p-ai-mb-status');

            // Get content from editor
            var content = this.getEditorContent();

            if (!content) {
                WPGenius.UI.toast('Please add some content first.', 'warning');
                return;
            }

            $btn.prop('disabled', true);
            $status.text('Generating...').fadeIn();

            $.post(w2pAiParams.ajax_url, {
                action: 'w2p_ai_generate_' + type,
                content: content,
                nonce: w2pAiParams.nonce
            }, this.handleResponse.bind(this, type, $btn, $status))
                .fail(this.handleError.bind(this, $btn, $status));
        },

        handleResponse: function (type, $btn, $status, response) {
            if (response.success) {
                if (type === 'excerpt') {
                    // Update excerpt field
                    if ($('#excerpt').length) {
                        $('#excerpt').val(response.data);
                        $status.text('Excerpt generated!').fadeOut(3000);
                    }
                } else if (type === 'tags') {
                    // Update tags field
                    if ($('#new-tag-post_tag').length) {
                        $('#new-tag-post_tag').val(response.data.join(', '));
                        $status.text('Tags suggested!').fadeOut(3000);
                    }
                }
            } else {
                $status.text('Error: ' + response.data).fadeOut(5000);
            }
            $btn.prop('disabled', false);
        },

        handleError: function ($btn, $status) {
            $status.text('Network error').fadeOut(5000);
            $btn.prop('disabled', false);
        },

        getEditorContent: function () {
            // Gutenberg
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostContent();
            }
            // TinyMCE
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content') && !tinyMCE.get('content').isHidden()) {
                return tinyMCE.get('content').getContent();
            }
            // Text mode
            return $('#content').val();
        }
    };

    $(document).ready(function () {
        if (window.w2pAiParams) WPGenius.AIAssistant.init();
    });

})(jQuery);
