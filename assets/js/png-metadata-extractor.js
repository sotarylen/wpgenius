(function ($) {
    'use strict';

    $(document).ready(function () {
        // 处理元数据图标点击事件
        $(document).on('click', '.png-metadata-icon', function (e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation(); // 阻止其他事件处理程序执行

            var $icon = $(this);
            var $container = $icon.closest('.png-metadata-container');
            var $popup = $container.find('.png-metadata-popup');
            var imageId = $container.data('image-id');

            // 如果弹出框已经显示，则隐藏
            if ($popup.is(':visible')) {
                $popup.hide();
                return;
            }

            // 隐藏所有其他弹出框
            $('.png-metadata-popup').hide();

            // 显示加载状态
            $icon.html('<span class="png-metadata-loading"></span>');

            // 发送AJAX请求获取元数据
            $.ajax({
                url: pngMetadataExtractor.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_png_metadata',
                    image_id: imageId,
                    nonce: pngMetadataExtractor.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $popup.html(response.data.html).show();

                        // 调整弹出框位置，确保不超出视口
                        adjustPopupPosition($popup);

                        // 阻止弹出框内的点击事件冒泡
                        $popup.on('click', function (e) {
                            e.stopPropagation();
                        });
                    } else {
                        $popup.html('<div class="error">' + response.data.message + '</div>').show();
                    }

                    // 恢复图标
                    $icon.html('📊');
                },
                error: function () {
                    $popup.html('<div class="error">' + pngMetadataExtractorStrings.error_message + '</div>').show();
                    $icon.html('📊');
                }
            });

            return false; // 确保不会触发其他事件
        });

        // 点击页面其他地方关闭弹出框
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.png-metadata-container').length) {
                $('.png-metadata-popup').hide();
            }
        });

        // 媒体库中的批量提取功能
        if (typeof wp !== 'undefined' && wp.media) {
            // 添加批量提取按钮到媒体库工具栏
            var mediaToolbar = function () {
                var toolbarView = wp.media.view.Toolbar.Select;

                wp.media.view.Toolbar.Select = toolbarView.extend({
                    initialize: function () {
                        toolbarView.prototype.initialize.apply(this, arguments);

                        this.primary.add('extract-png-metadata', new wp.media.view.Button({
                            text: pngMetadataExtractorStrings.extract_metadata,
                            priority: 80,
                            click: function () {
                                var selection = wp.media.frame.state().get('selection');
                                var imageIds = [];

                                selection.each(function (attachment) {
                                    if (attachment.get('type') === 'image' && attachment.get('subtype') === 'png') {
                                        imageIds.push(attachment.get('id'));
                                    }
                                });

                                if (imageIds.length === 0) {
                                    if (window.WPGenius && window.WPGenius.UI) {
                                        WPGenius.UI.toast(pngMetadataExtractorStrings.no_png_selected, 'warning');
                                    }
                                    return;
                                }

                                if (confirm(pngMetadataExtractorStrings.confirm_extraction.replace('%d', imageIds.length))) {
                                    extractBatchMetadata(imageIds);
                                }
                            }
                        }));
                    }
                });
            };

            // 确保在媒体库加载后执行
            if (wp.media.frame) {
                mediaToolbar();
            } else {
                wp.media.view.MediaFrame.Select.on('ready', mediaToolbar);
            }
        }
    });

    // 调整弹出框位置
    function adjustPopupPosition($popup) {
        var $container = $popup.closest('.png-metadata-container');
        var windowWidth = $(window).width();
        var windowHeight = $(window).height();
        var scrollTop = $(window).scrollTop();
        var scrollLeft = $(window).scrollLeft();

        // 获取容器的位置
        var containerOffset = $container.offset();
        var containerRight = containerOffset.left + $container.outerWidth();
        var containerBottom = containerOffset.top + $container.outerHeight();

        // 获取弹出框尺寸
        var popupWidth = $popup.outerWidth();
        var popupHeight = $popup.outerHeight();

        // 重置位置
        $popup.css({
            'top': '45px',
            'right': '0',
            'left': 'auto',
            'bottom': 'auto'
        });

        // 检查是否超出右边界
        if (containerRight + popupWidth > windowWidth + scrollLeft) {
            $popup.css({
                'right': 'auto',
                'left': '0'
            });
        }

        // 检查是否超出下边界
        if (containerBottom + popupHeight > windowHeight + scrollTop) {
            $popup.css({
                'top': 'auto',
                'bottom': '45px'
            });
        }

        // 如果向上显示也会超出上边界，则优先向下显示并滚动
        var popupTop = $popup.offset().top;
        if (popupTop < scrollTop) {
            $popup.css({
                'top': '45px',
                'bottom': 'auto'
            });
        }
    }

    // 批量提取元数据
    function extractBatchMetadata(imageIds) {
        // 显示加载指示器
        var $loading = $('<div class="media-loading">' + pngMetadataExtractorStrings.extracting + '</div>');
        $('.media-frame-content').append($loading);

        $.ajax({
            url: pngMetadataExtractor.ajaxurl,
            type: 'POST',
            data: {
                action: 'extract_batch_png_metadata',
                image_ids: imageIds,
                nonce: pngMetadataExtractor.nonce
            },
            success: function (response) {
                $loading.remove();

                if (response.success) {
                    alert(pngMetadataExtractorStrings.extraction_success.replace('%d', response.data.processed));
                    // 刷新媒体库以显示新的元数据
                    if (wp.media.frame) {
                        wp.media.frame.content.mode('browse');
                    }
                } else {
                    if (window.WPGenius && window.WPGenius.UI) {
                        WPGenius.UI.toast(response.data.message, 'error');
                    }
                }
            },
            error: function () {
                $loading.remove();
                if (window.WPGenius && window.WPGenius.UI) {
                    WPGenius.UI.toast(pngMetadataExtractorStrings.extraction_error, 'error');
                }
            }
        });
    }

})(jQuery);