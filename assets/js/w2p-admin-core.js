/**
 * WP Genius - Core JavaScript
 * Contains UI helpers and global logic.
 */

(function ($) {
    'use strict';

    // Global namespace
    window.WPGenius = window.WPGenius || {};

    // ==============================
    // UI Helpers
    // ==============================
    WPGenius.UI = {
        /**
         * Show feedback on a button (Wrapper for w2p)
         */
        showFeedback: function ($btn, message, type = 'success', duration = 2000) {
            if (window.w2p) {
                w2p.toast(message, type);
                w2p.loading($btn, true);
                setTimeout(function () {
                    w2p.loading($btn, false);
                }, duration);
            }
        },

        /**
         * Show a toast notification (Wrapper for w2p)
         */
        toast: function (message, type = 'success', duration = 3000) {
            if (window.w2p) {
                w2p.toast(message, type, duration);
            }
        }
    };

    // ==============================
    // Global functions and utilities
    // ==============================
    WPGenius.initGlobalEvents = function () {
        // Global Sub-tab switching logic
        $(document).on('click', '.w2p-sub-tab-link', function (e) {
            e.preventDefault();
            var $link = $(this);
            var tab = $link.data('tab');
            if (!tab) return;

            var $container = $link.closest('.w2p-sub-tabs');

            // Toggle active link within this container
            $container.find('.w2p-sub-tab-link').removeClass('active');
            $link.addClass('active');

            // Toggle active content within this container
            $container.find('.w2p-sub-tab-content').removeClass('active');
            $container.find('#w2p-tab-' + tab).addClass('active');
        });

        // Module-specific tab switching logic for frontend enhancement settings
        $(document).on('click', '.w2p-tab-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var tab = $btn.data('tab');
            var module = $btn.closest('.w2p-tabs').data('module');

            if (!tab || !module) return;

            // Toggle active button within this module
            $btn.siblings('.w2p-tab-btn').removeClass('active');
            $btn.addClass('active');

            // Toggle active pane within this module
            $btn.closest('.w2p-tabs').find('.w2p-tab-pane').removeClass('active');
            $btn.closest('.w2p-tabs').find('[data-pane="' + tab + '"]').addClass('active');
        });

        // Handle all AJAX errors
        $(document).ajaxError(function (event, xhr, settings, thrownError) {
            console.error('WP Genius AJAX Error:', thrownError);
        });

        // Tooltips
        $('[title]').on('mouseenter', function () {
            var $this = $(this);
            var title = $this.attr('title');
            if (title) {
                // Custom tooltip logic can be added here
            }
        });

        // Danger-level toggle confirmation dialog
        $(document).on('change', 'input[data-confirm]', function (e) {
            var $input = $(this);

            if ($input.is(':checkbox') && !$input.is(':checked')) {
                return;
            }

            // Revert first
            if ($input.is(':checkbox')) {
                $input.prop('checked', !$input.prop('checked'));
            } else {
            }

            var confirmMessage = $input.data('confirm') || 'Are you sure?';

            if (window.w2p && window.w2p.confirm) {
                w2p.confirm(
                    confirmMessage,
                    function () {
                        // Confirmed: Apply action
                        if ($input.is(':checkbox')) {
                            $input.prop('checked', !$input.prop('checked')); // Toggle back
                        }
                    },
                    function () {
                        // Cancelled: Do nothing (already reverted)
                    }
                );
            } else {
                if (confirm(confirmMessage)) {
                    if ($input.is(':checkbox')) {
                        $input.prop('checked', !$input.prop('checked'));
                    }
                }
            }
        });

    };

    $(document).ready(function () {
        WPGenius.initGlobalEvents();
    });

    window.WPGenius = WPGenius;

})(jQuery);
