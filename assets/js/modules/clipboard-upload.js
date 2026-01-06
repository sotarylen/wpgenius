/**
 * WP Genius - Clipboard Upload Module
 */

(function ($) {
    'use strict';

    window.WPGenius = window.WPGenius || {};

    WPGenius.ClipboardUpload = {
        isEnabled: true,
        isUploading: false,

        init: function () {
            // Load settings
            if (window.w2pClipboardParams && w2pClipboardParams.settings) {
                this.isEnabled = w2pClipboardParams.settings.enabled !== false;
            }

            this.initGutenberg();
            this.initMediaLibrary();
            this.initTinyMCE();
        },

        initTinyMCE: function () {
            var self = this;

            if (typeof tinymce !== 'undefined') {
                tinymce.PluginManager.add('w2p_clipboard_upload', function (editor, url) {
                    // Add toggle button
                    editor.addButton('w2p_clipboard_toggle', {
                        title: 'Enable Clipboard Image Upload',
                        icon: 'w2p_clipboard_toggle',
                        onclick: function () {
                            self.isEnabled = !self.isEnabled;
                            this.active(self.isEnabled);

                            var msg = self.isEnabled ? 'Clipboard upload enabled' : 'Clipboard upload disabled';
                            editor.notificationManager.open({
                                text: msg,
                                type: 'info',
                                timeout: 2000
                            });
                        },
                        onPostRender: function () {
                            this.active(self.isEnabled);
                        }
                    });

                    // Listen for paste events
                    editor.on('paste', function (e) {
                        if (!self.isEnabled) return;

                        var items = (e.clipboardData || e.originalEvent.clipboardData).items;
                        for (var i = 0; i < items.length; i++) {
                            if (items[i].type.indexOf('image') !== -1) {
                                var blob = items[i].getAsFile();
                                self.handleImagePaste(blob, function (url) {
                                    editor.execCommand('mceInsertContent', false, '<img src="' + url + '" />');
                                });
                                e.preventDefault();
                            }
                        }
                    });
                });
            }
        },

        initGutenberg: function () {
            var self = this;

            $(document).on('paste', '.editor-styles-wrapper', function (e) {
                if (!self.isEnabled) return;

                var clipboardData = e.originalEvent.clipboardData;
                if (!clipboardData || !clipboardData.items) return;

                for (var i = 0; i < clipboardData.items.length; i++) {
                    var item = clipboardData.items[i];
                    if (item.type.indexOf('image') !== -1) {
                        var blob = item.getAsFile();
                        self.handleImagePaste(blob, function (url) {
                            if (typeof wp !== 'undefined' && wp.blocks) {
                                var block = wp.blocks.createBlock('core/image', { url: url });
                                wp.data.dispatch('core/block-editor').insertBlocks(block);
                            }
                        });
                        e.preventDefault();
                    }
                }
            });
        },

        initMediaLibrary: function () {
            var self = this;

            if ($('body').hasClass('upload-php') || $('.media-frame').length > 0) {
                $(document).on('paste', function (e) {
                    if ($(e.target).is('input, textarea, [contenteditable]')) {
                        return;
                    }

                    var items = (e.originalEvent.clipboardData || e.clipboardData).items;
                    for (var i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            var blob = items[i].getAsFile();
                            self.handleImagePaste(blob, function (url) {
                                if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                                    var view = wp.media.frame.content.get();
                                    if (view.collection) {
                                        view.collection.props.set({ ignore: (+ new Date()) });
                                    }
                                } else {
                                    location.reload();
                                }
                            });
                        }
                    }
                });
            }
        },

        handleImagePaste: function (blob, callback) {
            var self = this;
            var reader = new FileReader();

            reader.onload = function (event) {
                var base64Data = event.target.result;
                var postId = $('#post_ID').val() || 0;

                self.isUploading = true;
                // console.log('Uploading clipboard image...');

                $.ajax({
                    url: w2pClipboardParams.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'w2p_clipboard_upload',
                        nonce: w2pClipboardParams.nonce,
                        image_data: base64Data,
                        post_id: postId
                    },
                    success: function (response) {
                        self.isUploading = false;
                        if (response.success) {
                            if (callback) callback(response.data.url);
                        } else {
                            WPGenius.UI.toast(w2pClipboardParams.l10n.error + ': ' + response.data, 'error');
                        }
                    },
                    error: function () {
                        self.isUploading = false;
                        WPGenius.UI.toast(w2pClipboardParams.l10n.error, 'error');
                    }
                });
            };

            reader.readAsDataURL(blob);
        }
    };

    $(document).ready(function () {
        if (window.w2pClipboardParams) WPGenius.ClipboardUpload.init();
    });

})(jQuery);
