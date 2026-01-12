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
    // 全局功能和工具
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

        // 处理所有AJAX错误
        $(document).ajaxError(function (event, xhr, settings, thrownError) {
            console.error('WP Genius AJAX Error:', thrownError);
        });

        // 工具提示
        $('[title]').on('mouseenter', function () {
            var $this = $(this);
            var title = $this.attr('title');
            if (title) {
                // 这里可以添加自定义工具提示逻辑
            }
        });

        // 危险级开关确认对话框 (Migrated from admin-modules.js)
        $(document).on('change', '.w2p-danger-toggle', function (e) {
            if ($(this).is(':checked')) {
                var $checkbox = $(this);
                // Revert first to handle async flow
                $checkbox.prop('checked', false);

                var confirmMessage = '警告：启用此功能将阻止所有外部HTTP请求，包括插件和主题的更新检查。这会显著加快后台加载速度，但可能导致某些功能失效。\n\n您确定要继续吗？';

                if (window.w2p && window.w2p.confirm) {
                    w2p.confirm(
                        confirmMessage,
                        function () {
                            // User confirmed, apply the check
                            $checkbox.prop('checked', true);
                        },
                        function () {
                            // User cancelled, do nothing (already reverted)
                        }
                    );
                } else {
                    // Fallback to native confirm if w2p not ready
                    if (confirm(confirmMessage)) {
                        $checkbox.prop('checked', true);
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
