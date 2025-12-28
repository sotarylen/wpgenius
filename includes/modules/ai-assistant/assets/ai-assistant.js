jQuery(document).ready(function ($) {
    $('.w2p-ai-action').on('click', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var type = $btn.data('action');
        var $status = $('#w2p-ai-mb-status');

        // Get content from editor
        var content = '';
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content') && !tinyMCE.get('content').isHidden()) {
            content = tinyMCE.get('content').getContent();
        } else {
            content = $('#content').val();
        }

        if (!content) {
            alert('Please add some content first.');
            return;
        }

        $btn.prop('disabled', true);
        $status.text('Generating...').fadeIn();

        $.post(w2pAiParams.ajax_url, {
            action: 'w2p_ai_generate_' + type,
            content: content,
            nonce: w2pAiParams.nonce
        }, function (response) {
            if (response.success) {
                if (type === 'excerpt') {
                    // Update excerpt field
                    if ($('#excerpt').length) {
                        $('#excerpt').val(response.data);
                        $status.text('Excerpt generated!').fadeOut(3000);
                    }
                } else if (type === 'tags') {
                    // Update tags field (this depends on the tag UI, usually comma separated)
                    if ($('#new-tag-post_tag').length) {
                        $('#new-tag-post_tag').val(response.data.join(', '));
                        $status.text('Tags suggested!').fadeOut(3000);
                    }
                }
            } else {
                $status.text('Error: ' + response.data).fadeOut(5000);
            }
            $btn.prop('disabled', false);
        }).fail(function () {
            $status.text('Network error').fadeOut(5000);
            $btn.prop('disabled', false);
        });
    });
});
