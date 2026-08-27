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
            // Load settings. Treat any truthy value as enabled so the
            // switcher's stored '0'/'1' strings behave correctly.
            if (window.w2pClipboardParams && w2pClipboardParams.settings) {
                this.isEnabled = !!( w2pClipboardParams.settings && w2pClipboardParams.settings.enabled );
            }

            this.initTinyMCE();
            this.initGutenberg();
            this.initMediaLibrary();
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

                        var data = (e.clipboardData || (e.originalEvent && e.originalEvent.clipboardData));
                        if (!data || !data.items) return;

                        for (var i = 0; i < data.items.length; i++) {
                            if (data.items[i].type.indexOf('image') !== -1) {
                                var blob = data.items[i].getAsFile();
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

            // Non-iframe editor: the paste event bubbles to the parent document.
            $(document).on('paste', '.editor-styles-wrapper', function (e) {
                self.handleGutenbergPaste(e);
            });

            // Since WordPress 6.1 the block editor canvas is rendered inside an
            // iframe; paste events there never bubble out, so we must listen
            // inside the iframe's own document.
            this.attachIframePaste();
        },

        attachIframePaste: function () {
            var self = this;

            var bindIframe = function (iframe) {
                if (!iframe || !iframe.contentDocument || iframe.contentDocument.__w2pClipBound) {
                    return;
                }
                iframe.contentDocument.__w2pClipBound = true;
                iframe.contentDocument.addEventListener('paste', function (e) {
                    self.handleGutenbergPaste(e);
                });
            };

            var tryAttach = function () {
                var candidates = document.querySelectorAll(
                    '.editor-post-visual-editor__iframe, iframe[name="editor-canvas"], .block-editor-block-list__layout'
                );
                for (var i = 0; i < candidates.length; i++) {
                    var node = candidates[i];
                    if (node.tagName === 'IFRAME') {
                        bindIframe(node);
                    } else if (node.contentDocument) {
                        bindIframe(node);
                    }
                }
            };

            tryAttach();

            if (window.MutationObserver) {
                var mo = new MutationObserver(function () { tryAttach(); });
                mo.observe(document.body, { childList: true, subtree: true });
            }

            // The iframe may mount a tick after this script runs.
            setTimeout(tryAttach, 500);
            setTimeout(tryAttach, 1500);
        },

        handleGutenbergPaste: function (e) {
            if (!this.isEnabled) return;

            var data = (e.originalEvent && e.originalEvent.clipboardData)
                ? e.originalEvent.clipboardData
                : (e.clipboardData || null);

            if (!data || !data.items) return;

            var handled = false;
            for (var i = 0; i < data.items.length; i++) {
                var item = data.items[i];
                if (item.type && item.type.indexOf('image') !== -1) {
                    var blob = item.getAsFile();
                    if (!blob) continue;
                    this.handleImagePaste(blob, function (url) {
                        if (typeof wp !== 'undefined' && wp.blocks && wp.data) {
                            var block = wp.blocks.createBlock('core/image', { url: url });
                            wp.data.dispatch('core/block-editor').insertBlocks(block);
                        }
                    });
                    handled = true;
                }
            }

            if (handled) {
                e.preventDefault();
                if (e.stopImmediatePropagation) {
                    e.stopImmediatePropagation();
                }
            }
        },

        initMediaLibrary: function () {
            var self = this;

            // Listen on the parent document for media-library / media-modal
            // pastes. Inputs/textareas/contenteditable are skipped so typing is
            // unaffected; Gutenberg already stops propagation for editor pastes.
            $(document).on('paste', function (e) {
                if ($(e.target).is('input, textarea, [contenteditable]')) {
                    return;
                }
                if (!self.isEnabled) return;

                var data = (e.originalEvent && e.originalEvent.clipboardData)
                    ? e.originalEvent.clipboardData
                    : (e.clipboardData || null);

                if (!data || !data.items) return;

                for (var i = 0; i < data.items.length; i++) {
                    var item = data.items[i];
                    if (item.type && item.type.indexOf('image') !== -1) {
                        var blob = item.getAsFile();
                        if (!blob) continue;
                        self.handleImagePaste(blob, function (url) {
                            if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                                var view = wp.media.frame.content.get();
                                if (view && view.collection) {
                                    view.collection.props.set({ ignore: (+ new Date()) });
                                }
                            } else {
                                location.reload();
                            }
                        });
                        e.preventDefault();
                    }
                }
            });
        },

        handleImagePaste: function (blob, callback) {
            var self = this;
            var reader = new FileReader();

            reader.onload = function (event) {
                var base64Data = event.target.result;
                var postId = $('#post_ID').val() || 0;

                self.isUploading = true;

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
