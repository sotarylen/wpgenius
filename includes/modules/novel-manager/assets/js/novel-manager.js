/**
 * Novel Manager — Unified Admin JS
 *
 * @package WP_Genius
 */

jQuery(document).ready(function ($) {
    'use strict';

    const params = window.w2pNovelParams || {};
    const i18n = params.i18n || {};

    function showToast(msg, type) {
        if (typeof w2p !== 'undefined' && typeof w2p.toast === 'function') {
            w2p.toast(msg, type || 'success');
        } else {
            alert(msg);
        }
    }

    // 辅助转义函数 (XSS 防御)
    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }
    function escAttr(str) {
        return (str || '').replace(/"/g, '&quot;');
    }

    // =========================================================================
    // 0. 隔离 CSF 框架的全局表单监听与未保存提醒
    // =========================================================================
    function clearCsfFormWarning() {
        $('.csf-form-warning').removeClass('csf-form-show').hide();
        window.onbeforeunload = null;
    }

    // 清除 CSF 未保存警告
    $(document).on('change input', '#w2p-tab-novel-upload, #w2p-tab-fix-index, #w2p-tab-maintenance, #w2p-tab-stats', function () {
        clearCsfFormWarning();
    });

    // 定时/点击 Tab 时确保清除误触发的 warning
    $(document).on('click', '.csf-tab-item a, .csf-section a', function () {
        setTimeout(clearCsfFormWarning, 100);
    });

    // =========================================================================
    // 0.1 导入目标模式切换 (复用 Media Engine 经典工作流步骤设计) 与现有书籍管理
    // =========================================================================
    let currentTargetMode = 'new';
    let currentImportStrategy = 'append';
    let selectedExistingNovel = null;
    let uploadSearchTimer = null;
    let currentManageChapters = [];  // C 线路：管理现有章节的数据状态
    let lastCheckedManageCb = null;  // C 线路：Shift 多选辅助

    // 切换导入目标模式 (Create New vs Import to Existing)
    $(document).on('click', '.w2p-target-steps-nav .w2p-workflow-step-btn', function (e) {
        e.preventDefault();
        const target = $(this).data('target');
        if (!target) return;

        currentTargetMode = target;
        $('.w2p-target-steps-nav .w2p-workflow-step-btn').removeClass('active');
        $(this).addClass('active');

        if (target === 'existing') {
            $('#w2p-new-novel-fields-wrap').slideUp(200);
            $('#w2p-existing-novel-wrap').removeClass('w2p-hidden').slideDown(250);
            if (!selectedExistingNovel) {
                $('#w2p-strategy-row').addClass('w2p-hidden').hide();
                $('#w2p-upload-file-section').slideUp(200);
                $('#w2p-upload-actions').slideUp(200);
                $('#w2p-upload-search-novel-input').focus();
            } else {
                $('#w2p-strategy-row').removeClass('w2p-hidden').show();
                if (currentImportStrategy === 'manage') {
                    $('#w2p-upload-file-section').slideUp(200);
                    $('#w2p-upload-actions').slideUp(200);
                    $('#w2p-manage-chapters-wrap').removeClass('w2p-hidden').slideDown(250);
                } else {
                    $('#w2p-manage-chapters-wrap').slideUp(200);
                    $('#w2p-upload-file-section').removeClass('w2p-hidden').slideDown(250);
                    $('#w2p-upload-actions').removeClass('w2p-hidden').slideDown(250);
                }
            }
        } else {
            $('#w2p-existing-novel-wrap').slideUp(200);
            $('#w2p-manage-chapters-wrap').slideUp(200);
            $('#w2p-upload-file-section').removeClass('w2p-hidden').slideDown(250);
            $('#w2p-new-novel-fields-wrap').slideDown(250);
            $('#w2p-upload-actions').removeClass('w2p-hidden').slideDown(250);
        }
        clearCsfFormWarning();
    });

    // 切换现有书籍导入策略 (Append / Truncate / Manage)
    $(document).on('click', '.w2p-strategy-card', function (e) {
        const strat = $(this).data('strategy');
        if (!strat) return;

        currentImportStrategy = strat;
        $('.w2p-strategy-card').removeClass('active');
        $(this).addClass('active');
        $(this).find('input[type="radio"]').prop('checked', true);

        if (strat === 'manage') {
            // C 线路：收起文件上传区和操作栏，展示管理工作台
            $('#w2p-upload-file-section').slideUp(200);
            $('#w2p-upload-actions').slideUp(200);
            $('#w2p-manage-chapters-wrap').removeClass('w2p-hidden').slideDown(250);
            // 若已有选中书籍，立即加载章节
            if (selectedExistingNovel) {
                loadManageChapters(selectedExistingNovel.id);
            }
        } else {
            // A / B 线路：收起管理工作台，平滑展开上传区与操作栏
            $('#w2p-manage-chapters-wrap').slideUp(200);
            $('#w2p-upload-file-section').removeClass('w2p-hidden').slideDown(250);
            $('#w2p-upload-actions').removeClass('w2p-hidden').slideDown(250);
        }
        clearCsfFormWarning();
    });

    // 现有书籍实时防抖搜索
    $('#w2p-upload-search-novel-input').on('input', function () {
        const query = $.trim($(this).val());
        $('#w2p-upload-search-clear-btn').toggle(!!query);
        clearTimeout(uploadSearchTimer);

        if (!query) {
            $('#w2p-upload-search-results').empty().addClass('w2p-hidden');
            return;
        }

        uploadSearchTimer = setTimeout(function () {
            $('#w2p-upload-search-results').removeClass('w2p-hidden').html('<div class="w2p-fix-loading-row"><i class="fa-solid fa-spinner fa-spin"></i> Searching...</div>');

            $.post(params.ajaxUrl, {
                action: 'w2p_novel_fix_search',
                nonce: params.importNonce || params.fixNonce,
                query: query
            }, function (res) {
                const $results = $('#w2p-upload-search-results');
                $results.empty();

                if (res.success && Array.isArray(res.data.novels) && res.data.novels.length > 0) {
                    res.data.novels.forEach(function (n) {
                        const item = $('<div class="w2p-search-dropdown-item"></div>');
                        item.html(
                            '<div class="w2p-search-item-title">《' + escHtml(n.title) + '》 <span class="w2p-badge w2p-badge-secondary">ID: ' + escHtml(n.id) + '</span></div>' +
                            '<div class="w2p-search-item-meta">' + escHtml(n.chapter_count) + ' Chapters</div>'
                        );
                        item.on('click', function () {
                            selectTargetNovel(n.id);
                        });
                        $results.append(item);
                    });
                } else {
                    $results.html('<div class="w2p-fix-empty-box">' + (i18n.noNovelsFound || 'No matching novels found.') + '</div>');
                }
            }).fail(function () {
                $('#w2p-upload-search-results').html('<div class="w2p-fix-empty-box w2p-text-danger">Search request failed.</div>');
            });
        }, 300);
    });

    // 清空搜索输入框
    $('#w2p-upload-search-clear-btn').on('click', function (e) {
        e.preventDefault();
        $('#w2p-upload-search-novel-input').val('').focus();
        $('#w2p-upload-search-results').empty().addClass('w2p-hidden');
        $(this).hide();
    });

    // 选中目标现有书籍
    function selectTargetNovel(novelId) {
        $('#w2p-upload-search-results').empty().addClass('w2p-hidden');

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_get_target_novel_info',
            nonce: params.importNonce || params.fixNonce,
            novel_id: novelId
        }, function (res) {
            if (!res.success || !res.data) {
                showToast(res.data || 'Failed to fetch novel details.', 'error');
                return;
            }

            const data = res.data;
            selectedExistingNovel = data;
            $('#w2p_selected_existing_novel_id').val(data.id);

            // 填充卡片信息
            $('#w2p-selected-novel-title').text('《' + data.title + '》');
            $('#w2p-selected-novel-id-badge').text('ID: ' + data.id);
            $('#w2p-selected-novel-author').text(data.author_name || '-');
            $('#w2p-selected-novel-chapters').text(data.total_chapters || 0);
            $('#w2p-selected-novel-last-index').text(data.last_index || (data.total_chapters > 0 ? '#' + data.total_chapters : '-'));

            if (data.cover_url) {
                $('#w2p-selected-novel-thumb').html('<img src="' + escAttr(data.cover_url) + '" alt="Cover">');
            } else {
                $('#w2p-selected-novel-thumb').html('<i class="fa-solid fa-book"></i>');
            }

            // 同步标题输入框以备用
            $('#w2p_novel_title').val(data.title);

            // 显示卡片，收起搜索行，展开操作类型三选一
            $('#w2p-existing-search-row').addClass('w2p-hidden');
            $('#w2p-selected-novel-card-row').removeClass('w2p-hidden');
            $('#w2p-strategy-row').removeClass('w2p-hidden').slideDown(200);

            // 若当前策略为 append 或 truncate：平滑展开上传入口与操作栏；若为 manage 则展示工作台
            if (currentImportStrategy === 'manage') {
                $('#w2p-upload-file-section').slideUp(200);
                $('#w2p-upload-actions').slideUp(200);
                $('#w2p-manage-chapters-wrap').removeClass('w2p-hidden').slideDown(250);
                loadManageChapters(data.id);
            } else {
                $('#w2p-manage-chapters-wrap').slideUp(200);
                $('#w2p-upload-file-section').removeClass('w2p-hidden').slideDown(250);
                $('#w2p-upload-actions').removeClass('w2p-hidden').slideDown(250);
            }

            clearCsfFormWarning();
            showToast('Target novel 《' + data.title + '》 selected.', 'success');
        }).fail(function () {
            showToast('Network error while fetching novel details.', 'error');
        });
    }

    // 点击更换目标现有书籍按钮
    $('#w2p-change-target-novel-btn').on('click', function (e) {
        e.preventDefault();
        selectedExistingNovel = null;
        $('#w2p_selected_existing_novel_id').val('');
        $('#w2p-selected-novel-card-row').addClass('w2p-hidden');
        $('#w2p-existing-search-row').removeClass('w2p-hidden');
        $('#w2p-strategy-row').addClass('w2p-hidden').hide();
        $('#w2p-upload-search-novel-input').val('').focus();
        $('#w2p-upload-search-clear-btn').hide();

        // 隐藏上传区与操作栏（尚未选定新书籍）
        $('#w2p-upload-file-section').slideUp(200);
        $('#w2p-upload-actions').slideUp(200);

        // 重置 manage 工作台：清空表格，策略卡片归 A
        currentImportStrategy = 'append';
        currentManageChapters = [];
        $('#w2p-manage-chapters-wrap').hide();
        $('#w2p-manage-chapters-tbody').empty();
        $('.w2p-strategy-card').removeClass('active');
        $('.w2p-strategy-card[data-strategy="append"]').addClass('active');
        $('input[name="import_strategy"][value="append"]').prop('checked', true);

        clearCsfFormWarning();
    });

    // 点击页面空白处收起搜索下拉浮层
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#w2p-existing-search-row').length) {
            $('#w2p-upload-search-results').addClass('w2p-hidden');
        }
    });

    // =========================================================================
    // 0.2 C 线路：管理现有章节工作台（复用 Fix Chapter Index 逻辑，独立状态与 DOM）
    // =========================================================================

    /**
     * 加载指定书籍的现有章节（调用 fix_get_chapters AJAX 接口）
     * @param {number} novelId
     */
    function loadManageChapters(novelId) {
        const $tbody = $('#w2p-manage-chapters-tbody');
        $tbody.html('<tr><td colspan="7" class="w2p-fix-loading-row"><i class="fa-solid fa-spinner fa-spin"></i> Loading chapters...</td></tr>');

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_get_chapters',
            nonce: params.fixNonce,
            novel_id: novelId
        }, function (res) {
            if (res.success && res.data && Array.isArray(res.data.chapters)) {
                currentManageChapters = res.data.chapters;
                renderManageChaptersTable();
            } else {
                currentManageChapters = [];
                $tbody.html('<tr><td colspan="7" class="w2p-fix-empty-text">No chapters found for this novel.</td></tr>');
            }
        }).fail(function () {
            $tbody.html('<tr><td colspan="7" class="w2p-fix-empty-text w2p-fix-error-text">Failed to load chapters.</td></tr>');
        });
    }

    /**
     * 渲染 C 线路章节编辑表格 (统一 7 列，字符串拼接一次性注入，杜绝重排卡死)
     */
    function renderManageChaptersTable() {
        const $tbody = $('#w2p-manage-chapters-tbody');
        $('#w2p-manage-check-all').prop('checked', false);

        if (!currentManageChapters || currentManageChapters.length === 0) {
            $tbody.html('<tr><td colspan="7" class="w2p-fix-empty-text">No chapters available.</td></tr>');
            return;
        }

        let html = '';
        for (let idx = 0; idx < currentManageChapters.length; idx++) {
            const chap = currentManageChapters[idx];
            const indexVal = chap.new_index || chap.chapter_index || '';
            const volumeVal = chap.new_volume || chap.volume || '正文';
            const titleVal = chap.title || '';
            const wordsVal = (chap.words && chap.words !== '-') ? Number(chap.words).toLocaleString() : '-';
            const editLink = chap.edit_link || '#';

            html += '<tr>' +
                '<td><input type="checkbox" class="w2p-manage-chap-cb" value="' + idx + '"></td>' +
                '<td>' + (idx + 1) + '</td>' +
                '<td><input type="text" class="w2p-edit-index-input w2p-manage-edit-index-input" data-idx="' + idx + '" value="' + escAttr(indexVal) + '"></td>' +
                '<td><input type="text" class="w2p-edit-vol-input w2p-manage-edit-vol-input" data-idx="' + idx + '" value="' + escAttr(volumeVal) + '"></td>' +
                '<td><input type="text" class="w2p-edit-title-input w2p-manage-edit-title-input" data-idx="' + idx + '" value="' + escAttr(titleVal) + '"></td>' +
                '<td>' + escHtml(wordsVal) + '</td>' +
                '<td><a href="' + escAttr(editLink) + '" target="_blank" class="w2p-btn w2p-btn-sm w2p-btn-secondary" title="Edit in WP Admin"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>' +
                '</tr>';
        }

        $tbody.html(html);
        clearCsfFormWarning();
    }

    // 监听 C 线路章节 Index 就地编辑
    $(document).on('change input', '.w2p-manage-edit-index-input', function (e) {
        e.stopPropagation();
        const idx = parseInt($(this).data('idx'), 10);
        if (currentManageChapters[idx] !== undefined) {
            const val = $.trim($(this).val());
            currentManageChapters[idx].new_index = val;
            currentManageChapters[idx].chapter_index = val;
        }
        clearCsfFormWarning();
    });

    // 监听 C 线路章节分卷就地编辑
    $(document).on('change input', '.w2p-manage-edit-vol-input', function (e) {
        e.stopPropagation();
        const idx = parseInt($(this).data('idx'), 10);
        if (currentManageChapters[idx] !== undefined) {
            const val = $.trim($(this).val());
            currentManageChapters[idx].new_volume = val;
            currentManageChapters[idx].volume = val;
        }
        clearCsfFormWarning();
    });

    // 监听 C 线路章节标题就地编辑
    $(document).on('change input', '.w2p-manage-edit-title-input', function (e) {
        e.stopPropagation();
        const idx = parseInt($(this).data('idx'), 10);
        if (currentManageChapters[idx] !== undefined) {
            currentManageChapters[idx].title = $(this).val();
        }
        clearCsfFormWarning();
    });

    // Shift 连续多选
    $(document).on('click', '.w2p-manage-chap-cb', function (e) {
        if (lastCheckedManageCb && e.shiftKey) {
            const $cbs = $('.w2p-manage-chap-cb');
            const start = $cbs.index(this);
            const end = $cbs.index(lastCheckedManageCb);
            if (start !== -1 && end !== -1) {
                const isChecked = $(this).prop('checked');
                $cbs.slice(Math.min(start, end), Math.max(start, end) + 1).prop('checked', isChecked);
            }
        }
        lastCheckedManageCb = this;
    });

    // 全选 / 反选
    $('#w2p-manage-check-all').on('change', function () {
        $('.w2p-manage-chap-cb').prop('checked', $(this).is(':checked'));
        lastCheckedManageCb = null;
    });

    // 批量设置分卷
    $('#w2p-manage-batch-vol-btn').on('click', function (e) {
        e.preventDefault();
        const $selected = $('.w2p-manage-chap-cb:checked');
        if ($selected.length === 0) {
            showToast('Please select at least one chapter.', 'warning');
            return;
        }
        const firstIdx = parseInt($selected.first().val(), 10);
        const defaultVol = currentManageChapters[firstIdx] ? (currentManageChapters[firstIdx].new_volume || currentManageChapters[firstIdx].volume || '') : '';
        const newVol = prompt('Enter new volume name for selected chapters:', defaultVol);
        if (newVol === null) return;
        const trimmed = $.trim(newVol);
        if (!trimmed) return;

        $selected.each(function () {
            const idx = parseInt($(this).val(), 10);
            if (currentManageChapters[idx] !== undefined) {
                currentManageChapters[idx].new_volume = trimmed;
                currentManageChapters[idx].volume = trimmed;
            }
        });
        renderManageChaptersTable();
        manageRecalculateIndexes(false);
        const updateMsg = (i18n.volumeUpdated || 'Volume updated for %d chapters.').replace('%d', $selected.length);
        showToast(updateMsg, 'success');
    });

    // 重建索引
    $('#w2p-manage-regen-index-btn').on('click', function (e) {
        e.preventDefault();
        manageRecalculateIndexes(true);
    });

    /**
     * 调用后端权威算法重新计算章节索引
     * @param {boolean} showFeedback
     */
    function manageRecalculateIndexes(showFeedback) {
        if (!currentManageChapters || currentManageChapters.length === 0) return;

        const $btn = $('#w2p-manage-regen-index-btn').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Regenerating...');

        const payload = currentManageChapters.map(function (chap) {
            const vol = (chap.new_volume !== undefined && chap.new_volume !== null && chap.new_volume !== '') ? chap.new_volume : (chap.volume || '正文');
            return { volume: vol, title: chap.title || '' };
        });

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_regen_indexes',
            nonce: params.fixNonce || params.importNonce,
            chapters: JSON.stringify(payload)
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="fa-solid fa-list-ol"></i> ' + (i18n.regenIndex || 'Regenerate Index'));
            if (res.success && res.data && Array.isArray(res.data.items)) {
                res.data.items.forEach(function (item, idx) {
                    if (currentManageChapters[idx] !== undefined) {
                        currentManageChapters[idx].new_index = item.chapter_index;
                        if (item.volume !== undefined && item.volume !== null && item.volume !== '') {
                            currentManageChapters[idx].new_volume = item.volume;
                            currentManageChapters[idx].volume = item.volume;
                        }
                    }
                });
                renderManageChaptersTable();
                if (showFeedback) showToast(i18n.indexRegenerated || 'Chapter indexes regenerated.', 'success');
            } else {
                if (showFeedback) showToast(res.data || 'Failed to regenerate indexes.', 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('<i class="fa-solid fa-list-ol"></i> ' + (i18n.regenIndex || 'Regenerate Index'));
            if (showFeedback) showToast('Network error during index recalculation.', 'error');
        });
    }

    // 批量修改标题（触发共用批量标题弹窗，manage 上下文）
    $('#w2p-manage-batch-title-btn').on('click', function (e) {
        e.preventDefault();
        if (!currentManageChapters || currentManageChapters.length === 0) {
            showToast('No chapters to modify.', 'warning');
            return;
        }
        openBatchTitleModal('manage');
    });

    // 保存 C 线路章节修改
    $('#w2p-manage-save-btn').on('click', function (e) {
        e.preventDefault();
        if (!selectedExistingNovel || !currentManageChapters || currentManageChapters.length === 0) {
            showToast('No novel selected or no chapter data to save.', 'warning');
            return;
        }

        const $btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

        const savePayload = currentManageChapters.map(function (chap) {
            return {
                id: chap.id,
                title: chap.title || '',
                new_title: chap.title || '',
                new_index: chap.new_index || chap.chapter_index || '',
                new_volume: chap.new_volume || chap.volume || '正文'
            };
        });

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_apply_single',
            nonce: params.fixNonce,
            novel_id: selectedExistingNovel.id,
            chapters: JSON.stringify(savePayload)
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Changes');
            if (res.success) {
                showToast('Chapter indexes and volumes saved successfully!', 'success');
                // 重新加载最新状态
                loadManageChapters(selectedExistingNovel.id);
            } else {
                showToast(res.data || 'Failed to save chapter updates.', 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Changes');
            showToast('Network error while saving.', 'error');
        });
    });

    // =========================================================================
    // 1. 封面媒体库选择与文件交互
    // =========================================================================
    let coverFrame = null;
    $('#w2p-cover-select-btn').on('click', function (e) {
        e.preventDefault();

        if (coverFrame) {
            coverFrame.open();
            return;
        }

        coverFrame = wp.media({
            title: i18n.selectCover || 'Select Novel Cover',
            button: { text: i18n.useImage || 'Use as Cover' },
            multiple: false,
            library: { type: 'image' }
        });

        coverFrame.on('select', function () {
            const attachment = coverFrame.state().get('selection').first().toJSON();
            $('#w2p_novel_cover_id').val(attachment.id);
            const previewUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
            $('#w2p-cover-preview img').attr('src', previewUrl);
            $('#w2p-cover-preview').show();
            $('#w2p-cover-select-btn').hide();
            clearCsfFormWarning();
        });

        coverFrame.open();
    });

    $('#w2p-cover-remove-btn').on('click', function (e) {
        e.preventDefault();
        $('#w2p_novel_cover_id').val('');
        $('#w2p-cover-preview img').attr('src', '');
        $('#w2p-cover-preview').hide();
        $('#w2p-cover-select-btn').show();
        clearCsfFormWarning();
    });

    // 文件选择反馈与自动提取小说名（及即时读文件头智能回填）
    $('#w2p_novel_file').on('change', function () {
        const file = this.files[0];
        if (file) {
            $('#w2p-selected-filename').text(file.name).removeClass('w2p-hidden').show();
            const currentTitle = $('#w2p_novel_title').val();
            if (!currentTitle) {
                const nameWithoutExt = file.name.replace(/\.[^/.]+$/, '');
                $('#w2p_novel_title').val($.trim(nameWithoutExt));
            }

            // 若为 .txt / .html 文本文件，使用 FileReader 极速预读前 32KB（Base64 二进制流）即时识别书名、作者、简介与分类
            if (/\.(txt|html|htm)$/i.test(file.name) && typeof FileReader !== 'undefined') {
                try {
                    const reader = new FileReader();
                    const slice = file.slice(0, 32768);
                    reader.onload = function (e) {
                        const dataUrl = e.target.result;
                        if (dataUrl) {
                            const commaIdx = dataUrl.indexOf(',');
                            const base64Str = commaIdx !== -1 ? dataUrl.substring(commaIdx + 1) : '';
                            $.post(params.ajaxUrl, {
                                action: 'w2p_novel_inspect_header',
                                nonce: params.importNonce,
                                header_base64: base64Str,
                                filename: file.name
                            }, function (res) {
                                if (res.success && res.data) {
                                    const d = res.data;
                                    if (d.title) {
                                        $('#w2p_novel_title').val(d.title);
                                    }
                                    if (d.author) {
                                        $('#w2p_novel_author').val(d.author);
                                    }
                                    if (d.intro) {
                                        $('#w2p_novel_intro').val(d.intro);
                                    }
                                    if (d.category_id && parseInt(d.category_id, 10) > 0) {
                                        $('#w2p_novel_category').val(d.category_id).trigger('change');
                                    }
                                }
                            });
                        }
                    };
                    reader.readAsDataURL(slice);
                } catch (err) {}
            }
        } else {
            $('#w2p-selected-filename').addClass('w2p-hidden').hide();
        }
        clearCsfFormWarning();
    });

    // =========================================================================
    // 1.1 小说标签交互管理 (精简通用，支持 localStorage 记忆)
    // =========================================================================
    let selectedNovelTags = [];

    // 从 localStorage 读取记忆的分类与标签
    try {
        const cachedCat = localStorage.getItem('w2p_novel_last_cat');
        if (cachedCat && $('#w2p_novel_category').length) {
            $('#w2p_novel_category').val(cachedCat);
        }
        const cachedTags = localStorage.getItem('w2p_novel_last_tags');
        if (cachedTags) {
            const parsedTags = JSON.parse(cachedTags);
            if (Array.isArray(parsedTags) && parsedTags.length > 0) {
                selectedNovelTags = parsedTags;
            }
        }
    } catch (e) {}

    // 监听分类变更并存入 localStorage
    $('#w2p_novel_category').on('change', function () {
        try {
            const val = $(this).val();
            if (val) {
                localStorage.setItem('w2p_novel_last_cat', val);
            }
        } catch (e) {}
    });

    function renderNovelTags() {
        const $wrap = $('#w2p-tags-list');
        $wrap.empty();
        selectedNovelTags.forEach(function (tag, idx) {
            const $item = $('<span class="w2p-tag-badge"></span>');
            const $del = $('<button type="button" class="w2p-tag-del" data-idx="' + idx + '" title="Remove">&times;</button>');
            $item.append($del).append(document.createTextNode(' ' + tag));
            $wrap.append($item);
        });
        try {
            localStorage.setItem('w2p_novel_last_tags', JSON.stringify(selectedNovelTags));
        } catch (e) {}
    }

    if (selectedNovelTags.length > 0) {
        renderNovelTags();
    }

    function addNovelTags(inputStr) {
        if (!inputStr || typeof inputStr !== 'string') return;
        const items = inputStr.split(/[,，]/);
        let changed = false;
        items.forEach(function (str) {
            const tag = $.trim(str);
            if (tag && selectedNovelTags.indexOf(tag) === -1) {
                selectedNovelTags.push(tag);
                changed = true;
            }
        });
        if (changed) {
            renderNovelTags();
        }
    }

    // 点击添加按钮
    $(document).on('click', '#w2p-add-tag-btn', function (e) {
        e.preventDefault();
        const $input = $('#w2p_novel_new_tag');
        addNovelTags($input.val());
        $input.val('').focus();
    });

    // 输入框回车或键入逗号
    $(document).on('keydown', '#w2p_novel_new_tag', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            addNovelTags($(this).val());
            $(this).val('');
        }
    }).on('input', '#w2p_novel_new_tag', function () {
        const val = $(this).val();
        if (val.indexOf(',') !== -1 || val.indexOf('，') !== -1) {
            addNovelTags(val);
            $(this).val('');
        }
    });

    // 点击删除标签
    $(document).on('click', '.w2p-tag-del', function (e) {
        e.preventDefault();
        const idx = parseInt($(this).data('idx'), 10);
        if (!isNaN(idx) && idx >= 0 && idx < selectedNovelTags.length) {
            selectedNovelTags.splice(idx, 1);
            renderNovelTags();
        }
    });

    // 点击推荐标签
    $(document).on('click', '.w2p-quick-tag', function (e) {
        e.preventDefault();
        const tag = $(this).data('tag');
        if (tag) {
            addNovelTags(String(tag));
        }
    });

    // =========================================================================
    // 2. 文档解析与两阶段导入工作台
    // =========================================================================
    let parsedChapters = [];
    let isImporting = false;
    let activeTaskId = '';
    let currentNovelId = 0;
    let currentNovelPermalink = '';

    // Phase 1: 上传解析
    $('#w2p-novel-parse-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearCsfFormWarning();

        if (currentTargetMode === 'existing' && (!selectedExistingNovel || !selectedExistingNovel.id)) {
            showToast('Please search and select a target existing novel first.', 'warning');
            $('#w2p-upload-search-novel-input').focus();
            return;
        }

        const fileInput = $('#w2p_novel_file')[0];
        if (!fileInput.files || !fileInput.files[0]) {
            showToast('Please choose a novel file (.txt, .docx, .epub, .html, .pdf) to upload.', 'error');
            return;
        }

        const uploadFile = fileInput.files[0];
        if (!/\.(txt|docx|epub|html|htm|pdf)$/i.test(uploadFile.name)) {
            showToast('Unsupported file format. Please upload a .txt, .docx, .epub, .html, or .pdf file.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'w2p_novel_parse_file');
        formData.append('nonce', params.importNonce);
        formData.append('novel_file', fileInput.files[0]);

        const parseBtn = $(this);
        parseBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> ' + (i18n.parsing || 'Parsing...'));

        $.ajax({
            url: params.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                parseBtn.prop('disabled', false).html('<i class="fa-solid fa-wand-magic-sparkles"></i> Upload & Parse Document');
                if (!res.success) {
                    showToast(res.data || 'Parse error', 'error');
                    return;
                }

                const data = res.data;
                parsedChapters = data.chapters || [];
                activeTaskId = data.task_id || '';
                $('#w2p_current_task_id').val(activeTaskId);

                // 目标书籍模式下的标题与简介联动
                if (currentTargetMode === 'existing' && selectedExistingNovel) {
                    $('#w2p_novel_title').val(selectedExistingNovel.title);
                } else {
                    if (!$('#w2p_novel_title').val() && data.novel_title) {
                        $('#w2p_novel_title').val(data.novel_title);
                    }
                    if (!$('#w2p_novel_intro').val() && data.novel_intro) {
                        $('#w2p_novel_intro').val(data.novel_intro);
                    }
                    if (!$('#w2p_novel_author').val() && data.novel_author) {
                        $('#w2p_novel_author').val(data.novel_author);
                    }
                }

                // 如果为现有书籍的追加模式 (Append)，自动顺延起始序号与索引
                if (currentTargetMode === 'existing' && currentImportStrategy === 'append' && selectedExistingNovel) {
                    const baseOrder = selectedExistingNovel.total_chapters || 0;
                    parsedChapters.forEach(function (chap, idx) {
                        chap.index = baseOrder + idx + 1;
                    });
                }

                // 切换到第二步工作台
                $('#w2p-novel-step-upload').slideUp(200);
                $('#w2p-novel-step-preview').slideDown(300);

                renderChaptersTable();
                clearCsfFormWarning();
                if (data.safety_warning) {
                    showToast(data.safety_warning, 'warning', 8000);
                } else {
                    showToast(i18n.parseSuccess || 'File parsed successfully!', 'success');
                }
            },
            error: function (xhr, status, err) {
                parseBtn.prop('disabled', false).html('<i class="fa-solid fa-wand-magic-sparkles"></i> Upload & Parse Document');
                if (xhr && (xhr.status === 0 || xhr.status >= 500)) {
                    showToast(i18n.docChangeDetected || 'Document change detected, please re-select and upload the document.', 'error');
                } else {
                    showToast('Network Error: ' + err, 'error');
                }
            }
        });
    });

    // 章节内容无损回并辅助函数（用于误拆章节删除时将内容安全拼接到上一章）
    function mergeChapterInto(targetChap, srcChap) {
        if (!targetChap || !srcChap) return;
        let addHtml = '';
        if (srcChap.title) {
            addHtml += '<p><strong>' + escHtml(srcChap.title) + '</strong></p>';
        }
        if (srcChap.content) {
            addHtml += srcChap.content;
        }
        targetChap.content = (targetChap.content || '') + addHtml;
        targetChap.word_count = (parseInt(targetChap.word_count, 10) || 0) + (parseInt(srcChap.word_count, 10) || 0);
    }

    let isShortFilterOnly = false;

    // 渲染预览表格
    function renderChaptersTable() {
        const tbody = $('#w2p-chapters-preview-tbody');
        tbody.empty();

        let totalWords = 0;
        const volSet = new Set();

        parsedChapters.forEach(function (chap, idx) {
            chap.index = idx + 1;
            const words = parseInt(chap.word_count, 10) || 0;
            totalWords += words;
            if (chap.volume) volSet.add(chap.volume);

            const tr = $('<tr></tr>').attr('data-idx', idx);
            tr.append('<td><input type="checkbox" class="w2p-chap-checkbox" value="' + idx + '"></td>');
            tr.append('<td>' + chap.index + '</td>');
            tr.append('<td><input type="text" class="w2p-edit-index-input" data-idx="' + idx + '" value="' + escAttr(chap.chapter_index) + '"></td>');
            tr.append('<td><input type="text" class="w2p-edit-vol-input" data-idx="' + idx + '" value="' + escAttr(chap.volume) + '"></td>');
            tr.append('<td><input type="text" class="w2p-edit-title-input" data-idx="' + idx + '" value="' + escAttr(chap.title) + '"></td>');
            tr.append('<td class="w2p-word-count-cell" data-words="' + words + '">' + (words ? words.toLocaleString() : '0') + '</td>');
            tr.append('<td><button type="button" class="w2p-btn w2p-btn-danger w2p-btn-sm w2p-del-row-btn" data-idx="' + idx + '"><i class="fa-solid fa-trash-can"></i></button></td>');

            tbody.append(tr);
        });

        $('#w2p-stat-chapters').text(parsedChapters.length.toLocaleString());
        $('#w2p-stat-volumes').text(volSet.size);
        $('#w2p-stat-words').text(totalWords.toLocaleString());
        updateShortChapterHighlights();
        clearCsfFormWarning();
    }

    // 更新短章节异常高亮与过滤
    function updateShortChapterHighlights() {
        const threshold = parseInt($('#w2p-word-threshold-input').val(), 10) || 0;
        let shortCount = 0;

        $('#w2p-chapters-preview-tbody tr').each(function () {
            const $tr = $(this);
            const $cell = $tr.find('.w2p-word-count-cell');
            const words = parseInt($cell.attr('data-words'), 10) || 0;

            if (threshold > 0 && words < threshold) {
                shortCount++;
                $tr.addClass('w2p-row-warning');
                if ($cell.find('.w2p-word-warning-badge').length === 0) {
                    $cell.append('<span class="w2p-word-warning-badge" title="Words below threshold"><i class="fa-solid fa-triangle-exclamation"></i></span>');
                }
            } else {
                $tr.removeClass('w2p-row-warning');
                $cell.find('.w2p-word-warning-badge').remove();
            }

            if (isShortFilterOnly) {
                if ($tr.hasClass('w2p-row-warning')) {
                    $tr.show();
                } else {
                    $tr.hide();
                }
            } else {
                $tr.show();
            }
        });

        const $badge = $('#w2p-short-count-badge');
        $badge.text(shortCount);
        if (shortCount > 0) {
            $badge.removeClass('w2p-hidden').show();
        } else {
            $badge.addClass('w2p-hidden').hide();
        }
    }

    // 监听实时修改
    $(document).on('change input', '.w2p-edit-index-input', function (e) {
        e.stopPropagation();
        const idx = $(this).data('idx');
        if (parsedChapters[idx]) {
            parsedChapters[idx].chapter_index = $.trim($(this).val());
        }
        clearCsfFormWarning();
    });

    $(document).on('change input', '.w2p-edit-vol-input', function (e) {
        e.stopPropagation();
        const idx = $(this).data('idx');
        if (parsedChapters[idx]) {
            parsedChapters[idx].volume = $(this).val();
        }
        clearCsfFormWarning();
    });

    $(document).on('change input', '.w2p-edit-title-input', function (e) {
        e.stopPropagation();
        const idx = $(this).data('idx');
        if (parsedChapters[idx]) {
            parsedChapters[idx].title = $(this).val();
        }
        clearCsfFormWarning();
    });

    // 单行删除（支持误拆章节自动无损回并上一章）
    $(document).on('click', '.w2p-del-row-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const idx = parseInt($(this).data('idx'), 10);
        if (isNaN(idx) || !parsedChapters[idx]) return;

        if (idx > 0) {
            const currChap = parsedChapters[idx];
            const prevChap = parsedChapters[idx - 1];
            mergeChapterInto(prevChap, currChap);
            parsedChapters.splice(idx, 1);
            renderChaptersTable();
            showToast(i18n.chapterMerged || 'Chapter merged into previous chapter and removed.', 'success');
        } else {
            if (confirm(i18n.confirmDeleteFirstChapter || 'This is the first chapter and cannot be merged into a previous chapter. Delete it directly?')) {
                parsedChapters.splice(idx, 1);
                renderChaptersTable();
                showToast(i18n.firstChapterDeleted || 'First chapter deleted.', 'info');
            }
        }
    });

    let lastCheckedUploadCb = null;

    // Shift 键连选支持 (Upload 章节预览表格)
    $(document).on('click', '.w2p-chap-checkbox', function (e) {
        if (lastCheckedUploadCb && e.shiftKey) {
            const $cbs = $('.w2p-chap-checkbox');
            const start = $cbs.index(this);
            const end = $cbs.index(lastCheckedUploadCb);
            if (start !== -1 && end !== -1) {
                const isChecked = $(this).prop('checked');
                $cbs.slice(Math.min(start, end), Math.max(start, end) + 1).prop('checked', isChecked);
            }
        }
        lastCheckedUploadCb = this;
    });

    // 全选/反选
    $('#w2p-check-all-chapters').on('change', function (e) {
        e.stopPropagation();
        const checked = $(this).is(':checked');
        $('.w2p-chap-checkbox').prop('checked', checked);
        lastCheckedUploadCb = null;
        clearCsfFormWarning();
    });

    // 批量修改卷名
    $('#w2p-preview-batch-vol-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const selected = $('.w2p-chap-checkbox:checked');
        if (selected.length === 0) {
            showToast('Please select at least one chapter.', 'warning');
            return;
        }

        const newVol = prompt('Enter new volume name for selected chapters:', '第1卷 正文');
        if (newVol === null) return;

        selected.each(function () {
            const idx = $(this).val();
            if (parsedChapters[idx]) {
                parsedChapters[idx].volume = newVol;
            }
        });
        renderChaptersTable();
    });

    // =========================================================================
    // 批量修改章节标题通用模态框 (Batch Modify Titles Modal)
    // =========================================================================
    let currentBatchTitleContext = 'upload'; // 'upload' | 'fix' | 'manage'

    function openBatchTitleModal(context) {
        currentBatchTitleContext = context;
        $('#w2p-' + context + '-batch-title-find').val('');
        $('#w2p-' + context + '-batch-title-replace').val('');
        $('#w2p-' + context + '-batch-title-prefix').val('');
        $('#w2p-' + context + '-batch-title-suffix').val('');

        let count = 0;
        let total = 0;
        if (context === 'upload') {
            count = $('.w2p-chap-checkbox:checked').length;
            total = parsedChapters ? parsedChapters.length : 0;
        } else if (context === 'manage') {
            count = $('.w2p-manage-chap-cb:checked').length;
            total = currentManageChapters ? currentManageChapters.length : 0;
        }

        if (count > 0) {
            $('#w2p-' + context + '-batch-title-scope-tip').text('Will be applied to ' + count + ' selected chapter(s).');
        } else {
            $('#w2p-' + context + '-batch-title-scope-tip').text('Will be applied to all ' + total + ' chapter(s).');
        }

        $('#w2p-' + context + '-batch-title-modal').addClass('active');
        setTimeout(function () {
            $('#w2p-' + context + '-batch-title-find').focus();
        }, 100);
    }

    function closeBatchTitleModal() {
        $('.w2p-batch-title-modal').removeClass('active');
    }

    // 触发批量修改标题 (Upload 预览)
    $('#w2p-preview-batch-title-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!parsedChapters || parsedChapters.length === 0) {
            showToast('No chapters to modify.', 'warning');
            return;
        }
        openBatchTitleModal('upload');
    });

    // 关闭模态框 (upload / fix / manage 弹窗均适用)
    $(document).on('click', '.w2p-batch-title-cancel-btn, .w2p-batch-title-modal-close, .w2p-manage-batch-title-modal-close', function (e) {
        e.preventDefault();
        closeBatchTitleModal();
    });

    $('.w2p-batch-title-modal').on('click', function (e) {
        if ($(e.target).hasClass('w2p-batch-title-modal')) {
            closeBatchTitleModal();
        }
    });

    // 应用批量修改标题
    $(document).on('click', '.w2p-batch-title-apply-btn', function (e) {
        e.preventDefault();
        const ctx = currentBatchTitleContext;
        const findVal = $('#w2p-' + ctx + '-batch-title-find').val();
        const replaceVal = $('#w2p-' + ctx + '-batch-title-replace').val() || '';
        const prefixVal = $('#w2p-' + ctx + '-batch-title-prefix').val() || '';
        const suffixVal = $('#w2p-' + ctx + '-batch-title-suffix').val() || '';

        if (!findVal && !replaceVal && !prefixVal && !suffixVal) {
            showToast('Please enter text to find/replace or prefix/suffix.', 'warning');
            return;
        }

        let modifiedCount = 0;
        if (ctx === 'upload') {
            const selected = $('.w2p-chap-checkbox:checked');
            const targetIndices = [];
            if (selected.length > 0) {
                selected.each(function () {
                    targetIndices.push(parseInt($(this).val(), 10));
                });
            } else {
                parsedChapters.forEach(function (_, i) {
                    targetIndices.push(i);
                });
            }

            targetIndices.forEach(function (idx) {
                if (parsedChapters[idx]) {
                    let t = parsedChapters[idx].title || '';
                    if (findVal) {
                        t = t.split(findVal).join(replaceVal);
                    }
                    if (prefixVal) {
                        t = prefixVal + t;
                    }
                    if (suffixVal) {
                        t = t + suffixVal;
                    }
                    parsedChapters[idx].title = t;
                    modifiedCount++;
                }
            });

            closeBatchTitleModal();
            renderChaptersTable();
            showToast('Batch updated titles for ' + modifiedCount + ' chapters.', 'success');
        } else if (ctx === 'manage') {
            const selected = $('.w2p-manage-chap-cb:checked');
            const targetIndices = [];
            if (selected.length > 0) {
                selected.each(function () {
                    targetIndices.push(parseInt($(this).val(), 10));
                });
            } else {
                currentManageChapters.forEach(function (_, i) {
                    targetIndices.push(i);
                });
            }

            targetIndices.forEach(function (idx) {
                if (currentManageChapters[idx]) {
                    let t = currentManageChapters[idx].title || '';
                    if (findVal) { t = t.split(findVal).join(replaceVal); }
                    if (prefixVal) { t = prefixVal + t; }
                    if (suffixVal) { t = t + suffixVal; }
                    currentManageChapters[idx].title = t;
                    modifiedCount++;
                }
            });

            closeBatchTitleModal();
            manageRecalculateIndexes(false);
            showToast('Batch updated titles for ' + modifiedCount + ' chapters. Indexes recalculated.', 'success');
        }
    });



    // 重新生成章节索引号 (复用后端 W2P_Novel_Helper 权威计算，轻量传输仅发送卷名和标题)
    function regenerateChapterIndexes() {
        if (!parsedChapters || parsedChapters.length === 0) {
            return;
        }

        const $btn = $('#w2p-preview-regen-index-btn').prop('disabled', true);
        const payload = parsedChapters.map(function (chap) {
            return {
                volume: chap.volume || '',
                title: chap.title || ''
            };
        });

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_regen_indexes',
            nonce: params.importNonce,
            chapters: JSON.stringify(payload)
        }, function (res) {
            $btn.prop('disabled', false);
            if (res.success && res.data && Array.isArray(res.data.items)) {
                res.data.items.forEach(function (item, idx) {
                    if (parsedChapters[idx]) {
                        parsedChapters[idx].vol_idx = item.vol_idx;
                        parsedChapters[idx].chap_num = item.chap_num;
                        parsedChapters[idx].chapter_index = item.chapter_index;
                        parsedChapters[idx].volume = item.volume;
                    }
                });
                renderChaptersTable();
                showToast(i18n.indexRegenerated || 'Chapter indexes regenerated successfully.', 'success');
            } else {
                showToast(res.data || 'Failed to regenerate indexes.', 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            showToast('Network error while regenerating indexes.', 'error');
        });
    }

    // 点击重新生成索引号
    $('#w2p-preview-regen-index-btn').on('click', function (e) {
        e.preventDefault();
        regenerateChapterIndexes();
    });

    // 监听 Index 表头点击排序（支持按 chapter_index 升序/降序切换，复用 renderChaptersTable）
    let indexSortAsc = false;
    $(document).on('click', '.w2p-sortable-th[data-sort="index"]', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!parsedChapters || parsedChapters.length === 0) return;

        indexSortAsc = !indexSortAsc;
        parsedChapters.sort(function (a, b) {
            const idxA = a.chapter_index || '';
            const idxB = b.chapter_index || '';
            const cmp = idxA.localeCompare(idxB, undefined, { numeric: true });
            return indexSortAsc ? cmp : -cmp;
        });

        // 更新表头指示图标
        $(this).find('.w2p-sort-icon')
            .removeClass('fa-sort fa-sort-up fa-sort-down')
            .addClass(indexSortAsc ? 'fa-sort-up' : 'fa-sort-down');

        // 直接复用已有渲染函数，内部自动完成序号重赋值 chap.index = idx + 1
        renderChaptersTable();
    });

    // 批量删除选中（支持按自然顺序无损回并至前序最近保留章节）
    $('#w2p-preview-batch-del-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const selected = $('.w2p-chap-checkbox:checked');
        if (selected.length === 0) {
            showToast('Please select at least one chapter to delete.', 'warning');
            return;
        }

        const indicesToDelete = new Set();
        selected.each(function () {
            indicesToDelete.add(parseInt($(this).val(), 10));
        });

        // 检查全部删除
        if (indicesToDelete.size >= parsedChapters.length) {
            if (!confirm(i18n.confirmDeleteAll || 'Delete all chapters from list?')) {
                return;
            }
            parsedChapters = [];
            $('#w2p-check-all-chapters').prop('checked', false);
            renderChaptersTable();
            showToast(i18n.allDeleted || 'All chapters deleted.', 'info');
            return;
        }

        // 检查是否包含首章（首章无前序章节可回并）
        if (indicesToDelete.has(0)) {
            if (!confirm(i18n.confirmDeleteFirstChapterInBatch || 'The selection includes the first chapter, which cannot be merged into a previous chapter. Delete it directly?')) {
                return;
            }
        }

        // 按自然顺序遍历：为每个要删除的章节寻找前面最近保留的章节并回并内容
        let lastRetainedChap = null;
        let mergedCount = 0;
        const remainingChapters = [];

        for (let i = 0; i < parsedChapters.length; i++) {
            const chap = parsedChapters[i];
            if (indicesToDelete.has(i)) {
                if (lastRetainedChap) {
                    mergeChapterInto(lastRetainedChap, chap);
                    mergedCount++;
                }
            } else {
                lastRetainedChap = chap;
                remainingChapters.push(chap);
            }
        }

        parsedChapters = remainingChapters;
        $('#w2p-check-all-chapters').prop('checked', false);
        renderChaptersTable();

        if (mergedCount > 0) {
            showToast(mergedCount + ' chapter(s) content merged into preceding chapters.', 'success');
        } else {
            showToast('Selected chapter(s) deleted.', 'info');
        }
    });

    // 短章节异常过滤交互事件
    $(document).on('input change', '#w2p-word-threshold-input', function (e) {
        e.stopPropagation();
        updateShortChapterHighlights();
    });

    $('#w2p-filter-short-toggle-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        isShortFilterOnly = !isShortFilterOnly;
        $(this).toggleClass('w2p-btn-warning active', isShortFilterOnly);
        updateShortChapterHighlights();
    });

    $('#w2p-select-short-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $warningRows = $('#w2p-chapters-preview-tbody tr.w2p-row-warning');
        if ($warningRows.length === 0) {
            showToast(i18n.noShortChapters || 'No anomaly chapters found below threshold.', 'info');
            return;
        }
        $('.w2p-chap-checkbox').prop('checked', false);
        $warningRows.find('.w2p-chap-checkbox').prop('checked', true);

        const totalCbs = $('.w2p-chap-checkbox').length;
        const checkedCbs = $('.w2p-chap-checkbox:checked').length;
        $('#w2p-check-all-chapters').prop('checked', totalCbs > 0 && totalCbs === checkedCbs);
        showToast((i18n.selectedAnomalies || 'Selected anomalies: ') + $warningRows.length, 'success');
    });

    $('#w2p-preview-reparse-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (isImporting) return;
        $('#w2p-novel-step-preview').slideUp(200);
        $('#w2p-novel-step-upload').slideDown(300);
        parsedChapters = [];
        isShortFilterOnly = false;
        $('#w2p-filter-short-toggle-btn').removeClass('w2p-btn-warning active');
        $('#w2p_novel_file').val('');
        $('#w2p-selected-filename').text('').addClass('w2p-hidden').hide();
        clearCsfFormWarning();
    });

    // 启动批量章节入库流（支持从 startOffset 开始断点续传）
    function startBatchImportFlow(novelId, taskId, startOffset) {
        isImporting = true;
        currentNovelId = novelId;

        const commitBtn = $('#w2p-novel-commit-import-btn');
        commitBtn.prop('disabled', true);
        $('#w2p-preview-reparse-btn, #w2p-preview-batch-vol-btn, #w2p-preview-batch-del-btn').prop('disabled', true);

        const progressContainer = $('#w2p-import-progress-container');
        const progressStatus = $('#w2p-import-progress-status');
        const progressCount = $('#w2p-import-progress-count');
        const progressBar = $('#w2p-import-progress-bar');

        const batchSize = 25;
        let currentOffset = startOffset || 0;
        const totalCount = parsedChapters.length;

        progressContainer.show();
        const initialPct = totalCount > 0 ? Math.round((currentOffset / totalCount) * 100) : 0;
        progressBar.css('width', initialPct + '%');
        progressCount.text(currentOffset + ' / ' + totalCount);

        function importNextBatch() {
            if (currentOffset >= totalCount) {
                finishImport(true, novelId);
                return;
            }

            const chunk = parsedChapters.slice(currentOffset, currentOffset + batchSize);
            progressStatus.text('Importing chapters ' + (currentOffset + 1) + ' - ' + Math.min(currentOffset + chunk.length, totalCount) + '...');

            $.post(params.ajaxUrl, {
                action: 'w2p_novel_import_batch',
                nonce: params.importNonce,
                novel_id: novelId,
                task_id: taskId,
                chapters: JSON.stringify(chunk)
            }, function (resBatch) {
                if (!resBatch.success) {
                    finishImport(false, resBatch.data || 'Batch import failed.');
                    return;
                }

                currentOffset += chunk.length;
                const pct = Math.min(100, Math.round((currentOffset / totalCount) * 100));
                progressBar.css('width', pct + '%');
                progressCount.text(currentOffset + ' / ' + totalCount);

                setTimeout(importNextBatch, 80);
            }).fail(function () {
                finishImport(false, 'Network or server error during batch import. Progress is saved and can be resumed.');
            });
        }

        function finishImport(success, info) {
            isImporting = false;
            commitBtn.prop('disabled', false);
            $('#w2p-preview-reparse-btn, #w2p-preview-batch-vol-btn, #w2p-preview-batch-del-btn').prop('disabled', false);

            if (success) {
                progressBar.css('width', '100%');
                progressStatus.text(i18n.importSuccess || 'Import complete!');
                showToast(i18n.importSuccess || 'Novel and chapters successfully imported!', 'success');
                $('#w2p-active-task-alert').slideUp(200);
                clearCsfFormWarning();

                // 延迟展示仪式感成功弹窗
                setTimeout(function () {
                    const viewUrl = currentNovelPermalink || ('/novel/' + currentNovelId + '.html');
                    $('#w2p-view-novel-btn').attr('href', viewUrl);
                    $('#w2p-import-success-modal').addClass('active');
                }, 300);
            } else {
                showToast(info, 'error');
                progressStatus.text('Paused/Error: ' + info);
            }
        }

        importNextBatch();
    }

    // 点击“继续导入”按钮，原生直接刷新页面（干净彻底重置所有状态）
    $('#w2p-import-another-btn').on('click', function (e) {
        e.preventDefault();
        window.location.reload();
    });

    // 成功弹窗右上角关闭按钮
    $('#w2p-success-modal-close').on('click', function (e) {
        e.preventDefault();
        $('#w2p-import-success-modal').removeClass('active');
    });

    // 点击遮罩空白区域关闭弹窗
    $('#w2p-import-success-modal').on('click', function (e) {
        if ($(e.target).is('#w2p-import-success-modal')) {
            $(this).removeClass('active');
        }
    });

    // 清空重导二次确认模态框交互
    function openTruncateConfirmModal(novelTitle, chaptersCount) {
        $('#w2p-truncate-modal-novel-name').text('《' + novelTitle + '》');
        $('#w2p-truncate-modal-chapters-count').text(chaptersCount + ' chapters');
        $('#w2p-truncate-confirm-modal').addClass('active');
    }

    function closeTruncateConfirmModal() {
        $('#w2p-truncate-confirm-modal').removeClass('active');
    }

    $(document).on('click', '#w2p-truncate-modal-cancel, #w2p-truncate-modal-close', function (e) {
        e.preventDefault();
        closeTruncateConfirmModal();
    });

    $('#w2p-truncate-confirm-modal').on('click', function (e) {
        if ($(e.target).is('#w2p-truncate-confirm-modal')) {
            closeTruncateConfirmModal();
        }
    });

    $(document).on('click', '#w2p-truncate-modal-confirm', function (e) {
        e.preventDefault();
        closeTruncateConfirmModal();
        executeCommitImport();
    });

    // Phase 2: 确认并分批导入入库
    $('#w2p-novel-commit-import-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearCsfFormWarning();

        if (isImporting) return;
        if (parsedChapters.length === 0) {
            showToast('No chapters to import.', 'error');
            return;
        }

        if (currentTargetMode === 'existing') {
            if (!selectedExistingNovel || !selectedExistingNovel.id) {
                showToast('Please select a target existing novel first.', 'error');
                return;
            }
            if (currentImportStrategy === 'truncate') {
                // 触发清空重导高危二次确认
                openTruncateConfirmModal(selectedExistingNovel.title, selectedExistingNovel.total_chapters || 0);
                return;
            }
        }

        executeCommitImport();
    });

    function executeCommitImport() {
        const novelTitle = $('#w2p_novel_title').val() || (selectedExistingNovel ? selectedExistingNovel.title : '');
        if (!novelTitle) {
            showToast('Please enter a novel title.', 'error');
            $('#w2p_novel_title').focus();
            return;
        }

        // 确保输入框内未按回车的标签也被一并加入
        const pendingTag = $('#w2p_novel_new_tag').val();
        if (pendingTag) {
            addNovelTags(pendingTag);
            $('#w2p_novel_new_tag').val('');
        }

        const novelData = {
            action: 'w2p_novel_create_novel',
            nonce: params.importNonce,
            title: novelTitle,
            novel_id: currentTargetMode === 'existing' && selectedExistingNovel ? selectedExistingNovel.id : 0,
            existing_id: currentTargetMode === 'existing' && selectedExistingNovel ? selectedExistingNovel.id : 0,
            import_strategy: currentTargetMode === 'existing' ? currentImportStrategy : 'new',
            novel_status: $('#w2p_novel_status').val() || '已完结',
            content: $('#w2p_novel_intro').val(),
            category_ids: $('#w2p_novel_category').val() ? [$('#w2p_novel_category').val()] : [],
            tags: selectedNovelTags,
            author_name: $.trim($('#w2p_novel_author').val()),
            cover_id: $('#w2p_novel_cover_id').val(),
            task_id: activeTaskId || $('#w2p_current_task_id').val(),
            total_chapters: parsedChapters.length
        };

        const progressContainer = $('#w2p-import-progress-container');
        const progressStatus = $('#w2p-import-progress-status');
        const progressBar = $('#w2p-import-progress-bar');
        progressContainer.show();
        progressStatus.text(i18n.importingNovel || 'Creating / Preparing Novel record...');
        progressBar.css('width', '5%');

        $.post(params.ajaxUrl, novelData, function (resNovel) {
            if (!resNovel.success) {
                showToast(resNovel.data || 'Failed to create novel.', 'error');
                progressStatus.text('Error: ' + (resNovel.data || 'Failed to create novel.'));
                return;
            }

            const novelId = resNovel.data.novel_id;
            currentNovelPermalink = (resNovel.data && resNovel.data.permalink) ? resNovel.data.permalink : '';
            startBatchImportFlow(novelId, activeTaskId || $('#w2p_current_task_id').val(), 0);

        }).fail(function () {
            showToast('Network error when creating novel.', 'error');
            progressStatus.text('Network error when creating novel.');
        });
    }

    // =========================================================================
    // 2.2 断点续传任务（Resume & Discard）
    // =========================================================================
    $('#w2p-resume-task-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearCsfFormWarning();

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Loading...');

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_get_active_task',
            nonce: params.importNonce
        }, function (res) {
            btn.prop('disabled', false).html('<i class="fa-solid fa-play"></i> Resume Import Now');

            if (!res.success) {
                showToast(res.data || 'Failed to get task data.', 'error');
                $('#w2p-active-task-alert').slideUp(200);
                return;
            }

            const task = res.data.task;
            parsedChapters = res.data.chapters || [];
            activeTaskId = task.task_id;
            $('#w2p_current_task_id').val(activeTaskId);

            $('#w2p_novel_title').val(task.novel_title || '');
            $('#w2p-novel-step-upload').slideUp(200);
            $('#w2p-novel-step-preview').slideDown(300);

            renderChaptersTable();
            showToast('Resuming task: ' + task.novel_title + ' from chapter ' + (task.imported_count + 1), 'info');

            // 启动断点续传
            currentNovelPermalink = task.permalink || ('/novel/' + task.novel_id + '.html');
            startBatchImportFlow(task.novel_id, task.task_id, task.imported_count || 0);

        }).fail(function () {
            btn.prop('disabled', false).html('<i class="fa-solid fa-play"></i> Resume Import Now');
            showToast('Network error while resuming task.', 'error');
        });
    });

    $('#w2p-discard-task-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearCsfFormWarning();

        if (!confirm('Are you sure you want to discard this unfinished import task?')) {
            return;
        }

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_discard_active_task',
            nonce: params.importNonce
        }, function (res) {
            if (res.success) {
                $('#w2p-active-task-alert').slideUp(200);
                showToast('Task discarded.', 'success');
            }
        });
    });

    // =========================================================================
    // Novel 级联删除（列表页与单篇编辑页：Delete w/ Chapters）
    // =========================================================================
    if ($('a.w2p-delete-novel-with-chapters-btn').length || $('#w2p-novel-edit-delete-wrap').length) {
        // 编辑页将按钮移动至 Publish 动作区内与 Move to Trash 并排
        if ($('#w2p-novel-edit-delete-wrap').length && $('#delete-action').length) {
            $('#delete-action').append($('#w2p-novel-edit-delete-wrap'));
        }

        if (!$('#w2p-novel-delete-modal').length) {
            $('body').append(
                '<div id="w2p-novel-delete-modal" class="w2p-novel-delete-backdrop w2p-hidden">' +
                '<div class="w2p-novel-delete-container">' +
                '<div class="w2p-novel-delete-header">' +
                '<h3><i class="fa-solid fa-trash-can"></i> <span id="w2p-nd-title">' + escHtml(i18n.deleteNovel || 'Delete Novel & Chapters') + '</span></h3>' +
                '<button id="w2p-nd-close-btn" type="button" class="w2p-novel-delete-close-btn" title="' + escHtml(i18n.cancel || 'Cancel') + '"><i class="fa-solid fa-xmark"></i></button>' +
                '</div>' +
                '<div class="w2p-novel-delete-progress-bar">' +
                '<div id="w2p-nd-progress-fill" class="w2p-novel-delete-progress-fill"></div>' +
                '</div>' +
                '<div class="w2p-novel-delete-body">' +
                '<div class="w2p-novel-delete-status-bar">' +
                '<span class="w2p-novel-delete-status-text" id="w2p-nd-status-text"><i class="fa-solid fa-spinner fa-spin"></i> <span>' + escHtml(i18n.countingChapters || 'Counting related chapters...') + '</span></span>' +
                '<span class="w2p-novel-delete-status-pct" id="w2p-nd-status-pct">0%</span>' +
                '</div>' +
                '<div class="w2p-novel-delete-stats-row">' +
                '<div class="w2p-novel-delete-stat-card stat-total">' +
                '<span class="label">' + escHtml(i18n.totalChapters || 'Total Chapters') + '</span>' +
                '<span class="value" id="w2p-nd-stat-total">&mdash;</span>' +
                '</div>' +
                '<div class="w2p-novel-delete-stat-card stat-deleted">' +
                '<span class="label">' + escHtml(i18n.deletedChapters || 'Deleted') + '</span>' +
                '<span class="value" id="w2p-nd-stat-deleted">0</span>' +
                '</div>' +
                '<div class="w2p-novel-delete-stat-card stat-remaining">' +
                '<span class="label">' + escHtml(i18n.remainingChapters || 'Remaining') + '</span>' +
                '<span class="value" id="w2p-nd-stat-remaining">&mdash;</span>' +
                '</div>' +
                '</div>' +
                '<div class="w2p-novel-delete-notice">' +
                '<i class="fa-solid fa-triangle-exclamation"></i>' +
                '<span id="w2p-nd-notice-text">' + escHtml(i18n.keepWindowOpen || 'Please do not close this window until cleanup completes.') + '</span>' +
                '</div>' +
                '</div>' +
                '<div class="w2p-novel-delete-footer">' +
                '<button id="w2p-nd-cancel-btn" type="button" class="w2p-btn w2p-btn-secondary"><i class="fa-solid fa-xmark"></i> ' + escHtml(i18n.cancel || 'Cancel') + '</button>' +
                '<button id="w2p-nd-done-btn" type="button" class="w2p-btn w2p-btn-primary w2p-hidden"><i class="fa-solid fa-check"></i> ' + escHtml(i18n.done || 'Done') + '</button>' +
                '</div>' +
                '</div>' +
                '</div>'
            );
        }

        var w2pNovelDelete = {
            nonce: params.deleteNonce,
            novelId: 0,
            total: 0,
            deleted: 0,
            isSingle: false,
            isProcessing: false,
            isCancelled: false,
            autoReloadTimer: null,
            openModal: function (novelTitle) {
                var titleText = i18n.deleteNovel || 'Delete Novel & Chapters';
                if (novelTitle) {
                    titleText += ' — 《' + novelTitle + '》';
                }
                $('#w2p-nd-title').text(titleText);
                $('#w2p-nd-status-text').html('<i class="fa-solid fa-spinner fa-spin"></i> <span>' + escHtml(i18n.countingChapters || 'Counting related chapters...') + '</span>');
                $('#w2p-nd-status-pct').removeClass('w2p-success').text('0%');
                $('#w2p-nd-progress-fill').css('width', '0%');
                $('#w2p-nd-stat-total').text('—');
                $('#w2p-nd-stat-deleted').text('0');
                $('#w2p-nd-stat-remaining').text('—');
                $('#w2p-nd-cancel-btn').removeClass('w2p-hidden').prop('disabled', false);
                $('#w2p-nd-done-btn').addClass('w2p-hidden');
                $('#w2p-nd-close-btn').prop('disabled', false);

                var $modal = $('#w2p-novel-delete-modal');
                $modal.removeClass('w2p-hidden');
                setTimeout(function () {
                    $modal.addClass('active');
                }, 10);
            },
            closeModal: function () {
                var self = this;
                if (self.autoReloadTimer) {
                    clearTimeout(self.autoReloadTimer);
                    self.autoReloadTimer = null;
                }
                var $modal = $('#w2p-novel-delete-modal');
                $modal.removeClass('active');
                setTimeout(function () {
                    $modal.addClass('w2p-hidden');
                    self.isProcessing = false;
                    self.isCancelled = false;
                }, 250);
            },
            setProgress: function () {
                var total = this.total || 0;
                var deleted = this.deleted || 0;
                var remaining = Math.max(0, total - deleted);
                var pct = total > 0 ? Math.min(100, Math.round((deleted / total) * 100)) : 0;

                $('#w2p-nd-stat-total').text(total);
                $('#w2p-nd-stat-deleted').text(deleted);
                $('#w2p-nd-stat-remaining').text(remaining);
                $('#w2p-nd-progress-fill').css('width', pct + '%');
                $('#w2p-nd-status-pct').text(pct + '%');
                $('#w2p-nd-status-text').html(
                    '<i class="fa-solid fa-spinner fa-spin"></i> <span>' +
                    escHtml((i18n.deletingChapters || 'Deleting chapters') + ': ' + deleted + ' / ' + total) +
                    '</span>'
                );
            },
            start: function (novelId, novelTitle, isSingle) {
                if (this.isProcessing) return;
                this.isProcessing = true;
                this.isCancelled = false;
                this.isSingle = !!isSingle;
                this.novelId = novelId;
                this.total = 0;
                this.deleted = 0;
                this.openModal(novelTitle);
                this.deleteNextBatch(0);
            },
            deleteNextBatch: function (attempt) {
                var self = this;
                if (self.isCancelled) return;

                $.post(params.ajaxUrl, {
                    action: 'w2p_novel_delete_batch',
                    nonce: self.nonce,
                    novel_id: self.novelId
                }, function (res) {
                    if (self.isCancelled) return;

                    if (!res || !res.success) {
                        if (attempt < 3) {
                            setTimeout(function () { self.deleteNextBatch(attempt + 1); }, 600);
                        } else {
                            alert(res && res.data ? res.data : 'Delete failed');
                            self.closeModal();
                        }
                        return;
                    }
                    var d = res.data;
                    if (self.total === 0) {
                        self.total = d.total || 0;
                        $('#w2p-nd-stat-total').text(self.total);
                    }
                    self.deleted += (d.batch_deleted || 0);
                    self.setProgress();

                    if (d.done) {
                        $('#w2p-nd-status-text').html(
                            '<i class="fa-solid fa-spinner fa-spin"></i> <span>' +
                            escHtml(i18n.deletingNovelItself || 'Deleting the novel itself...') +
                            '</span>'
                        );
                        $('#w2p-nd-progress-fill').css('width', '100%');
                        $('#w2p-nd-status-pct').text('100%');
                        self.finalize();
                        return;
                    }
                    setTimeout(function () { self.deleteNextBatch(0); }, 120);
                }).fail(function () {
                    if (self.isCancelled) return;
                    if (attempt < 3) {
                        setTimeout(function () { self.deleteNextBatch(attempt + 1); }, 600);
                    } else {
                        alert('Network error');
                        self.closeModal();
                    }
                });
            },
            finalize: function () {
                var self = this;
                if (self.isCancelled) return;

                $.post(params.ajaxUrl, {
                    action: 'w2p_novel_delete_final',
                    nonce: self.nonce,
                    novel_id: self.novelId
                }, function (res2) {
                    if (self.isCancelled) return;

                    if (res2 && res2.success) {
                        self.isProcessing = false;
                        $('#w2p-nd-progress-fill').css('width', '100%');
                        $('#w2p-nd-status-pct').addClass('w2p-success').text('100%');
                        $('#w2p-nd-stat-remaining').text('0');
                        $('#w2p-nd-status-text').html(
                            '<i class="fa-solid fa-circle-check w2p-text-success"></i> <span>' +
                            escHtml(i18n.deleteSuccess || 'Novel and chapters deleted successfully.') +
                            '</span>'
                        );
                        $('#w2p-nd-cancel-btn').addClass('w2p-hidden');
                        $('#w2p-nd-done-btn').removeClass('w2p-hidden');
                        $('#w2p-nd-close-btn').prop('disabled', false);

                        // 1.5 秒后平滑刷新页面或跳转回列表页
                        self.autoReloadTimer = setTimeout(function () {
                            if (self.isSingle) {
                                window.location.href = 'edit.php?post_type=novel';
                            } else {
                                window.location.reload();
                            }
                        }, 1500);
                    } else {
                        alert(res2 && res2.data ? res2.data : 'Delete failed');
                        self.closeModal();
                    }
                }).fail(function () {
                    if (self.isCancelled) return;
                    alert('Network error during final cleanup');
                    self.closeModal();
                });
            },
            requestCancel: function () {
                var self = this;
                if (!self.isProcessing) {
                    self.closeModal();
                    return;
                }
                var cancelMsg = i18n.confirmCancel || 'Deletion is in progress. Are you sure you want to stop?';
                var doCancel = function () {
                    self.isCancelled = true;
                    self.isProcessing = false;
                    self.closeModal();
                };

                if (typeof w2p !== 'undefined' && typeof w2p.confirm === 'function') {
                    w2p.confirm(cancelMsg, doCancel);
                } else if (confirm(cancelMsg)) {
                    doCancel();
                }
            }
        };

        // 按钮事件绑定
        $(document).on('click', '#w2p-nd-close-btn, #w2p-nd-cancel-btn', function (e) {
            e.preventDefault();
            w2pNovelDelete.requestCancel();
        });

        $(document).on('click', '#w2p-nd-done-btn', function (e) {
            e.preventDefault();
            if (w2pNovelDelete.isSingle) {
                window.location.href = 'edit.php?post_type=novel';
            } else {
                window.location.reload();
            }
        });

        $(document).on('click', 'a.w2p-delete-novel-with-chapters-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var novelId = $btn.data('novel-id');
            var novelTitle = $btn.data('novel-title');
            var isSingle = $btn.data('is-single') ? true : false;

            if (!novelId) {
                var href = $btn.attr('href');
                if (href && href !== '#') {
                    var m = href.match(/post_id=(\d+)/);
                    if (m) {
                        novelId = m[1];
                    }
                }
            }

            if (!novelId) { return; }

            if (!novelTitle) {
                var $row = $btn.closest('tr');
                novelTitle = $row.find('.row-title').text().trim() || '';
            }

            var confirmMsg = i18n.confirmDeleteNovel || 'Delete this novel AND all of its chapters? This cannot be undone.';
            var startDelete = function () {
                w2pNovelDelete.start(novelId, novelTitle, isSingle);
            };

            if (typeof w2p !== 'undefined' && typeof w2p.confirm === 'function') {
                w2p.confirm(confirmMsg, startDelete);
            } else if (confirm(confirmMsg)) {
                startDelete();
            }
        });
    }

    // =========================================================================
    // Statistics: 章节字数缓存 + 书籍统计汇总的后台校准工具
    // =========================================================================
    (function initStats() {
        if (!$('#w2p-tab-stats').length) { return; }

        const $searchInput = $('#w2p-stats-search-input');
        const $resultBox   = $('#w2p-stats-search-results');
        const $resultList  = $resultBox.find('.w2p-stats-result-list');
        const $card        = $('#w2p-stats-single-card');

        let selectedNovel = null;
        let running = false;
        let stopped = false;

        // ---------- 概览懒加载 ----------
        // 概览是 30 万章级别的聚合查询，不允许随页面渲染执行；
        // 本 Tab 第一次真正可见时才通过 AJAX 拉一次，其余后台页面零开销。
        let overviewReady = null;

        function applyOverview(d) {
            $('#w2p-stats-kpi-chapter-total').text(d.chapter_total.toLocaleString());
            $('#w2p-stats-kpi-chapter-synced').text(d.chapter_synced.toLocaleString());
            $('#w2p-stats-kpi-novel-total').text(d.novel_total.toLocaleString());
            $('#w2p-stats-kpi-novel-synced').text(d.novel_synced.toLocaleString());
            $('#w2p-stats-kpi-chapter-pending').text(Math.max(0, d.chapter_total - d.chapter_synced).toLocaleString());
            $('#w2p-stats-kpi-novel-pending').text(Math.max(0, d.novel_total - d.novel_synced).toLocaleString());
            $('#w2p-stats-dirty-count').text(d.dirty.toLocaleString());
            $('.w2p-stats-actions').attr({
                'data-chapter-total': d.chapter_total,
                'data-novel-total': d.novel_total
            });
            totals.chapters = d.chapter_total;
            totals.novels   = d.novel_total;
        }

        function ensureOverview() {
            if (overviewReady) { return overviewReady; }
            overviewReady = $.post(params.ajaxUrl, {
                action: 'w2p_novel_stats_overview',
                nonce: params.statsNonce
            }, null, 'json').then(function (res) {
                if (res && res.success) { applyOverview(res.data); }
            }, function () {
                overviewReady = null; // 失败允许下次重试
            });
            return overviewReady;
        }

        function maybeLoadOverview() {
            if ($('#w2p-tab-stats').is(':visible')) { ensureOverview(); }
        }

        maybeLoadOverview();                    // 本 Tab 若默认激活，就绪即拉
        $(document).on('click', maybeLoadOverview); // CSF Tab 切换是 JS 显隐，委托到点击统一探测

        // ---------- 搜索并选中单本 ----------
        function doSearch() {
            const q = $.trim($searchInput.val());
            if (!q) { return; }

            $resultBox.hide().find('.w2p-stats-result-list').empty();
            $resultList.append('<li class="w2p-stats-result-item is-muted">' + escHtml(i18n.statsSearching || 'Searching...') + '</li>');
            $resultBox.show();

            $.post(params.ajaxUrl, {
                action: 'w2p_novel_fix_search',
                nonce: params.fixNonce,
                query: q
            }, function (res) {
                const list = (res && res.success && res.data && res.data.novels) ? res.data.novels : [];
                $resultList.empty();

                if (!list.length) {
                    $resultList.append('<li class="w2p-stats-result-item is-muted">' + escHtml(i18n.statsNoResult || 'No result.') + '</li>');
                    return;
                }

                list.forEach(function (n) {
                    const $li = $('<li class="w2p-stats-result-item"></li>')
                        .attr('data-id', n.id)
                        .attr('data-title', n.title)
                        .html('<strong>' + escHtml(n.title) + '</strong><span class="w2p-stats-result-meta">#' +
                            escHtml(n.id) + ' · ' + escHtml(n.chapter_count || 0) + ' chapters</span>');
                    $resultList.append($li);
                });
            });
        }

        function selectNovel(id, title) {
            selectedNovel = { id: parseInt(id, 10) || 0, title: title };
            $card.show();
            $('#w2p-stats-single-title').text(selectedNovel.title);
            $('#w2p-stats-single-id').text(selectedNovel.id);
            $('#w2p-stats-single-chapters').text('-');
            $('#w2p-stats-single-words').text('-');
            $resultBox.hide();
        }

        $(document).on('click', '#w2p-stats-search-btn', doSearch);
        $searchInput.on('keydown', function (e) {
            if (e.which === 13) { e.preventDefault(); doSearch(); }
        });

        $(document).on('click', '.w2p-stats-result-item[data-id]', function () {
            const $it = $(this);
            selectNovel($it.data('id'), $it.data('title'));
        });

        // ---------- 单本重算 ----------
        $(document).on('click', '#w2p-stats-single-run-btn', function () {
            if (!selectedNovel || !selectedNovel.id) {
                showToast(i18n.statsSelectNovel || 'Select a novel first.', 'warning');
                return;
            }

            const $btn = $(this).prop('disabled', true);
            $('#w2p-stats-single-chapters').text(i18n.statsCalculating || '...');

            $.post(params.ajaxUrl, {
                action: 'w2p_novel_stats_single',
                nonce: params.statsNonce,
                novel_id: selectedNovel.id
            }, function (res) {
                $btn.prop('disabled', false);
                if (!res || !res.success) {
                    const msg = (res && res.data && res.data.message) ? res.data.message : 'Failed.';
                    showToast(msg, 'error');
                    return;
                }

                const d = res.data;
                $('#w2p-stats-single-chapters').text(d.chapters.toLocaleString());
                $('#w2p-stats-single-words').text(d.words.toLocaleString());
                showToast(d.message || (i18n.statsDone || 'Done'), 'success');
            }).fail(function () {
                $btn.prop('disabled', false);
                showToast('Request failed.', 'error');
            });
        });

        // ---------- 全站分批回填 ----------
        const $progressBox = $('#w2p-stats-progress-container');
        const $bar         = $('#w2p-stats-progress-bar');
        const $status      = $('#w2p-stats-progress-status');
        const $countText   = $('#w2p-stats-progress-count');
        const $log         = $('#w2p-stats-log');
        const $stopBtn     = $('#w2p-stats-stop-btn');

        function setProgress(current, total, status) {
            $progressBox.show();
            const pct = total > 0 ? Math.min(100, Math.round((current / total) * 100)) : 0;
            $bar.css('width', pct + '%');
            $status.text(status);
            $countText.text(current.toLocaleString() + ' / ' + total.toLocaleString());
        }

        function appendLog(msg) {
            $log.show();
            const time = new Date().toTimeString().slice(0, 8);
            $log.append('<div class="w2p-log-line"><span class="w2p-log-time">' + escHtml(time) + '</span>' + escHtml(msg) + '</div>');
            $log.scrollTop($log[0].scrollHeight);
        }

        function toggleButtons(isRunning) {
            $('#w2p-stats-run-all-btn, #w2p-stats-scan-btn, #w2p-stats-sync-btn').prop('disabled', isRunning);
            if (isRunning) { $stopBtn.show(); } else { $stopBtn.hide(); }
        }

        /**
         * 按游标分批执行
         */
        function runBatch(action, total, phaseLabel, batchLog, onFinish) {
            if (stopped) {
                toggleButtons(false);
                running = false;
                appendLog(i18n.statsStopped || 'Stopped.');
                return;
            }

            $.post(params.ajaxUrl, {
                action: action,
                nonce: params.statsNonce,
                cursor: runBatch.cursor,
                limit: action === 'w2p_novel_stats_scan' ? 500 : 100
            }, function (res) {
                if (!res || !res.success) {
                    const msg = (res && res.data && res.data.message) ? res.data.message : 'Request failed.';
                    appendLog('[ERROR] ' + msg);
                    toggleButtons(false);
                    running = false;
                    return;
                }

                const d = res.data;
                runBatch.processed = (runBatch.processed || 0) + d.processed;
                runBatch.written   = (runBatch.written || 0) + (d.written !== undefined ? d.written : d.changed);
                runBatch.cursor    = d.last_id;

                appendLog(batchLog.replace('%1$s', d.processed.toLocaleString()).replace('%2$s', (d.written !== undefined ? d.written : d.changed).toLocaleString()));
                setProgress(runBatch.processed, total, phaseLabel);

                if (d.has_more) {
                    runBatch(action, total, phaseLabel, batchLog, onFinish);
                } else {
                    onFinish();
                }
            }).fail(function () {
                appendLog('[ERROR] Request failed.');
                toggleButtons(false);
                running = false;
            });
        }

        function runPhase(action, total, phaseLabel, batchLog, next) {
            appendLog(phaseLabel);
            setProgress(0, total, phaseLabel);
            runBatch.cursor    = 0;
            runBatch.processed = 0;
            runBatch.written   = 0;
            runBatch(action, total, phaseLabel, batchLog, next);
        }

        const totals = {
            chapters: parseInt($('.w2p-stats-actions').data('chapter-total'), 10) || 0,
            novels: parseInt($('.w2p-stats-actions').data('novel-total'), 10) || 0
        };

        const phaseChapterLog = i18n.statsScanBatch || 'Chapters scanned: %1$s (updated %2$s)';
        const phaseNovelLog   = i18n.statsSyncBatch || 'Novels aggregated: %1$s (updated %2$s)';
        const phase1Label     = i18n.statsScanningPhase || 'Phase 1: caching chapter words...';
        const phase2Label     = i18n.statsSyncingPhase || 'Phase 2: aggregating novels...';

        function finishAll() {
            toggleButtons(false);
            running = false;
            appendLog(i18n.statsDone || 'Done.');
            setProgress(1, 1, i18n.statsDone || 'Done.');
            showToast(i18n.statsDone || 'Done.', 'success');
        }

        function startRebuild(withChapters) {
            if (running) { return; }

            const confirmRun = function () {
                // 先确保概览（分母 totals）已加载，再开跑
                ensureOverview().then(function () {
                    running = true;
                    stopped = false;
                    toggleButtons(true);
                    $log.empty();

                    if (withChapters) {
                        runPhase('w2p_novel_stats_scan', totals.chapters, phase1Label, phaseChapterLog, function () {
                            runPhase('w2p_novel_stats_sync_batch', totals.novels, phase2Label, phaseNovelLog, finishAll);
                        });
                    } else {
                        runPhase('w2p_novel_stats_sync_batch', totals.novels, phase2Label, phaseNovelLog, finishAll);
                    }
                });
            };

            const msg = i18n.statsConfirmRun || 'Run now?';
            if (typeof w2p !== 'undefined' && typeof w2p.confirm === 'function') {
                w2p.confirm(msg, confirmRun);
            } else if (confirm(msg)) {
                confirmRun();
            }
        }

        $(document).on('click', '#w2p-stats-run-all-btn', function (e) { e.preventDefault(); startRebuild(true); });
        $(document).on('click', '#w2p-stats-scan-btn', function (e) {
            e.preventDefault();
            ensureOverview().then(function () {
                running = true; stopped = false; toggleButtons(true); $log.empty();
                runPhase('w2p_novel_stats_scan', totals.chapters, phase1Label, phaseChapterLog, finishAll);
            });
        });
        $(document).on('click', '#w2p-stats-sync-btn', function (e) {
            e.preventDefault();
            ensureOverview().then(function () {
                running = true; stopped = false; toggleButtons(true); $log.empty();
                runPhase('w2p_novel_stats_sync_batch', totals.novels, phase2Label, phaseNovelLog, finishAll);
            });
        });
        $(document).on('click', '#w2p-stats-stop-btn', function (e) {
            e.preventDefault();
            stopped = true;
            $stopBtn.prop('disabled', true).find('.fa-solid').addClass('fa-spin');
        });
    })();

    // =========================================================================
    // Novel edit screen: 单篇触发章节数 / 字数重算
    // =========================================================================
    (function initNovelStatsRecalc() {
        const $btn = $('#w2p-novel-stats-recalc');
        if (!$btn.length) { return; }

        const $status  = $('#w2p-novel-stats-status');
        const novelId  = parseInt($btn.data('novel-id'), 10) || 0;

        if (!novelId) { return; }

        /**
         * 把数值写回 ACF 输入框，让用户在点「更新」前就能看到结果
         */
        function setFieldValue(name, value) {
            const $field = $('[data-name="' + name + '"]');
            if (!$field.length) { return; }

            const $input = $field.find('input');
            if (!$input.length) { return; }

            $input.val(value).trigger('change');
        }

        $btn.on('click', function (e) {
            e.preventDefault();

            if ($btn.hasClass('is-busy')) { return; }

            $btn.addClass('is-busy').prop('disabled', true);
            $status.removeClass('is-error is-success').text(i18n.statsCalculating || 'Calculating...');

            $.post(params.ajaxUrl, {
                action: 'w2p_novel_stats_single',
                nonce: params.statsNonce,
                novel_id: novelId
            }).done(function (res) {
                if (!res || !res.success) {
                    const msg = (res && res.data && typeof res.data === 'string')
                        ? res.data
                        : (i18n.statsFailed || 'Failed.');
                    $status.addClass('is-error').text(msg);
                    return;
                }

                const d = res.data;
                setFieldValue('count-chapters', d.chapters);
                setFieldValue('count-words', d.words);

                $status.addClass('is-success').text(d.message || i18n.statsSaved || 'Updated.');
                showToast(d.message || i18n.statsSaved || 'Updated.', 'success');
            }).fail(function () {
                $status.addClass('is-error').text(i18n.statsFailed || 'Request failed.');
            }).always(function () {
                $btn.removeClass('is-busy').prop('disabled', false);
            });
        });

        // 首次打开一本「从未统计过」的书：自动跑一次，省掉一次点击。
        // 只在这两个字段都为空时触发；写入后字段变为 "0" 也不再满足条件，不会反复跑。
        if ('1' === String($btn.data('needs-sync'))) {
            $status.text(i18n.statsCalculating || 'Calculating...');
            $btn.trigger('click');
        }
    })();
});
