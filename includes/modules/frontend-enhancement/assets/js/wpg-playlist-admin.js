/**
 * WP Genius Playlist Admin
 * Track editor for the wpg_playlist post type (media picker + drag reorder).
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */
(function ($) {
    'use strict';

    var config = window.wpgPlaylistAdmin || {};
    var $list = $('#wpg-playlist-tracks-list');
    var $field = $('#wpg-playlist-tracks-field');
    var $addButton = $('#wpg-playlist-add-tracks');

    if (!$list.length || !$field.length || !$addButton.length) {
        return;
    }

    var template = document.getElementById('wpg-playlist-track-tpl');

    /**
     * Serialize the current row order into the hidden field.
     */
    function syncField() {
        var ids = [];
        $list.find('.wpg-playlist-editor__row[data-id]').each(function () {
            var id = parseInt($(this).attr('data-id'), 10);
            if (id > 0) {
                ids.push(id);
            }
        });
        $field.val(JSON.stringify(ids));
    }

    /**
     * Append a new row from an attachment.
     *
     * @param {number} id    Attachment ID.
     * @param {string} title Attachment title.
     */
    function appendRow(id, title) {
        if (!template) {
            return;
        }
        var node = template.content.firstElementChild.cloneNode(true);
        node.setAttribute('data-id', String(id));
        node.querySelector('.wpg-playlist-editor__name').textContent = title || '';
        $list.append(node);
        syncField();
    }

    // Open the media library picker (audio only).
    $addButton.on('click', function (e) {
        e.preventDefault();

        var frame = wp.media({
            title: config.chooseTitle || 'Select Audio Files',
            button: { text: config.addButton || 'Add Tracks' },
            library: { type: config.mimeType || 'audio' },
            multiple: true
        });

        frame.on('select', function () {
            var selection = frame.state().get('selection');
            selection.each(function (attachment) {
                appendRow(attachment.id, attachment.attributes.title || attachment.attributes.filename);
            });
        });

        frame.open();
    });

    // Remove a row.
    $list.on('click', '[data-action="remove"]', function () {
        $(this).closest('.wpg-playlist-editor__row').remove();
        syncField();
    });

    // HTML5 drag sorting.
    var dragRow = null;
    $list.on('dragstart', '.wpg-playlist-editor__row', function (e) {
        dragRow = this;
        e.originalEvent.dataTransfer.effectAllowed = 'move';
    });
    $list.on('dragover', '.wpg-playlist-editor__row', function (e) {
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'move';
    });
    $list.on('drop', '.wpg-playlist-editor__row', function (e) {
        e.preventDefault();
        if (dragRow && dragRow !== this) {
            if ($(this).position().top < $(dragRow).position().top) {
                $(this).before(dragRow);
            } else {
                $(this).after(dragRow);
            }
        }
        dragRow = null;
        syncField();
    });
    $list.on('dragend', function () {
        dragRow = null;
        syncField();
    });
})(jQuery);
