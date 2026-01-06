/**
 * WP Genius - Image Watermark Module
 */

(function ($) {
    'use strict';

    window.WPGenius = window.WPGenius || {};

    WPGenius.ImageWatermark = {
        init: function () {
            this.initMediaUploader();
            this.initConditions();
        },

        initMediaUploader: function () {
            var self = this;
            var frame;

            $('#iw_upload_image_button').on('click', function (e) {
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: 'Select Watermark Image',
                    button: { text: 'Use as watermark' },
                    multiple: false
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#iw_upload_image').val(attachment.id);
                    $('#previewImg_image').attr('src', attachment.url).show();
                    $('#iw_turn_off_image_button').prop('disabled', false);

                    if (attachment.width && attachment.height) {
                        $('#previewImageInfo').text('Original size: ' + attachment.width + ' px / ' + attachment.height + ' px');
                    } else {
                        $('#previewImageInfo').text('Image selected. Save changes to update details.');
                    }
                });

                frame.open();
            });

            $('#iw_turn_off_image_button').on('click', function (e) {
                e.preventDefault();
                $('#iw_upload_image').val('0');
                $('#previewImg_image').attr('src', '').hide();
                $('#previewImageInfo').text('Watermark has not been selected yet.');
                $(this).prop('disabled', true);
            });
        },

        initConditions: function () {
            // Watermark Size Toggles
            $('input[name="iw_options[watermark_image][watermark_size_type]"]').on('change', function () {
                var val = $(this).val();
                $('.iw-watermark-size-custom').toggle(val == '1');
                $('.iw-watermark-size-scaled').toggle(val == '2');
            });

            // Post Type Toggles
            $('input[name="iw_options[watermark_cpt_on]"]').on('change', function () {
                var val = $(this).val();
                $('#cpt-select').toggle(val === 'specific');
            });
        }
    };

    $(document).ready(function () {
        if ($('.w2p-module-settings-panel').length > 0) WPGenius.ImageWatermark.init();
    });

})(jQuery);
