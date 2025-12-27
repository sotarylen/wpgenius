/**
 * Clipboard Image Upload Frontend Logic
 * Supports Gutenberg, Classic Editor, and Media Library
 */
(function ($) {
    'use strict';

    // 1. TinyMCE Plugin Registration (Classic Editor)
    if (typeof tinymce !== 'undefined') {
        tinymce.PluginManager.add('w2p_clipboard_upload', function (editor, url) {
            // Add a toggle button to the toolbar
            editor.addButton('w2p_clipboard_toggle', {
                title: 'Enable Clipboard Image Upload',
                icon: 'w2p_clipboard_toggle', // Matches mce-i-w2p_clipboard_toggle CSS
                onclick: function () {
                    clipboardApp.isEnabled = !clipboardApp.isEnabled;
                    this.active(clipboardApp.isEnabled);

                    // Show feedback
                    var msg = clipboardApp.isEnabled ? 'Clipboard upload enabled' : 'Clipboard upload disabled';
                    editor.notificationManager.open({
                        text: msg,
                        type: 'info',
                        timeout: 2000
                    });
                },
                onPostRender: function () {
                    this.active(clipboardApp.isEnabled);
                }
            });

            // Listen for paste events
            editor.on('paste', function (e) {
                if (!clipboardApp.isEnabled) return;

                var items = (e.clipboardData || e.originalEvent.clipboardData).items;
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        var blob = items[i].getAsFile();
                        clipboardApp.handleImagePaste(blob, function (url) {
                            // Insert image into editor
                            editor.execCommand('mceInsertContent', false, '<img src="' + url + '" />');
                        });
                        e.preventDefault();
                    }
                }
            });
        });
    }

    const clipboardApp = {
        isEnabled: true,
        isUploading: false,

        init: function () {
            // Load state from settings or local storage
            var settings = (typeof w2pClipboardParams !== 'undefined') ? w2pClipboardParams.settings : {};
            this.isEnabled = settings.enabled || true;

            // 1. Gutenberg Integration
            this.initGutenberg();

            // 2. Media Library Integration
            this.initMediaLibrary();
        },

        /**
         * Gutenberg Integration
         */
        initGutenberg: function () {
            var self = this;

            // Gutenberg handles paste events differently. 
            // We hook into the global document paste if the editor is active.
            $(document).on('paste', '.editor-styles-wrapper', function (e) {
                if (!self.isEnabled) return;

                var clipboardData = e.originalEvent.clipboardData;
                if (!clipboardData || !clipboardData.items) return;

                for (var i = 0; i < clipboardData.items.length; i++) {
                    var item = clipboardData.items[i];
                    if (item.type.indexOf('image') !== -1) {
                        var blob = item.getAsFile();

                        self.handleImagePaste(blob, function (url) {
                            // In Gutenberg, we create an image block
                            if (typeof wp !== 'undefined' && wp.blocks) {
                                var block = wp.blocks.createBlock('core/image', {
                                    url: url
                                });
                                wp.data.dispatch('core/block-editor').insertBlocks(block);
                            }
                        });

                        e.preventDefault();
                    }
                }
            });
        },

        /**
         * Media Library Integration
         */
        initMediaLibrary: function () {
            var self = this;

            // Check if we are on the Media Library page
            if ($('body').hasClass('upload-php') || $('.media-frame').length > 0) {
                $(document).on('paste', function (e) {
                    // Skip if pasting into an input or textarea
                    if ($(e.target).is('input, textarea, [contenteditable]')) {
                        return;
                    }

                    var items = (e.originalEvent.clipboardData || e.clipboardData).items;
                    for (var i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            var blob = items[i].getAsFile();

                            self.handleImagePaste(blob, function (url) {
                                // Refresh media library if we are in the grid view
                                if (typeof wp !== 'undefined' && wp.media && wp.media.frame && wp.media.frame.content && wp.media.frame.content.get()) {
                                    var view = wp.media.frame.content.get();
                                    if (view.collection) {
                                        view.collection.props.set({ ignore: (+ new Date()) }); // Trigger fetch
                                    }
                                } else {
                                    // Or just reload for upload.php
                                    location.reload();
                                }
                            });
                        }
                    }
                });
            }
        },

        /**
         * Core Image Upload Logic
         */
        handleImagePaste: function (blob, callback) {
            var self = this;
            var reader = new FileReader();

            reader.onload = function (event) {
                var base64Data = event.target.result;
                var postId = $('#post_ID').val() || 0;

                self.isUploading = true;

                // Show a global spinner or notification if useful
                console.log('Uploading clipboard image...');

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
                            alert(w2pClipboardParams.l10n.error + ': ' + response.data);
                        }
                    },
                    error: function () {
                        self.isUploading = false;
                        alert(w2pClipboardParams.l10n.error);
                    }
                });
            };

            reader.readAsDataURL(blob);
        }
    };

    $(document).ready(function () {
        clipboardApp.init();
    });

})(jQuery);
