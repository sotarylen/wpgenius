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

    // =========================================================================
    // 0. 隔离 CSF 框架的全局表单监听与未保存提醒
    // =========================================================================
    function clearCsfFormWarning() {
        $('.csf-form-warning').removeClass('csf-form-show').hide();
        window.onbeforeunload = null;
    }

    // 清除 CSF 未保存警告
    $(document).on('change input', '#w2p-tab-novel-upload, #w2p-tab-fix-index, #w2p-tab-maintenance', function () {
        clearCsfFormWarning();
    });

    // 定时/点击 Tab 时确保清除误触发的 warning
    $(document).on('click', '.csf-tab-item a, .csf-section a', function () {
        setTimeout(clearCsfFormWarning, 100);
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

    // 文件选择反馈与自动提取小说名
    $('#w2p_novel_file').on('change', function () {
        const file = this.files[0];
        if (file) {
            $('#w2p-selected-filename').text(file.name).show();
            const currentTitle = $('#w2p_novel_title').val();
            if (!currentTitle) {
                const nameWithoutExt = file.name.replace(/\.[^/.]+$/, '');
                $('#w2p_novel_title').val($.trim(nameWithoutExt));
            }
        } else {
            $('#w2p-selected-filename').hide();
        }
        clearCsfFormWarning();
    });

    // =========================================================================
    // 1.1 小说标签交互管理 (精简通用)
    // =========================================================================
    let selectedNovelTags = [];

    function renderNovelTags() {
        const $wrap = $('#w2p-tags-list');
        $wrap.empty();
        selectedNovelTags.forEach(function (tag, idx) {
            const $item = $('<span class="w2p-tag-badge"></span>');
            const $del = $('<button type="button" class="w2p-tag-del" data-idx="' + idx + '" title="Remove">&times;</button>');
            $item.append($del).append(document.createTextNode(' ' + tag));
            $wrap.append($item);
        });
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

    // Phase 1: 上传解析
    $('#w2p-novel-parse-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearCsfFormWarning();

        const fileInput = $('#w2p_novel_file')[0];
        if (!fileInput.files || !fileInput.files[0]) {
            showToast('Please choose a .docx or .txt file to upload.', 'error');
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

                // 自动补全小说标题与简介
                if (!$('#w2p_novel_title').val() && data.novel_title) {
                    $('#w2p_novel_title').val(data.novel_title);
                }
                if (!$('#w2p_novel_intro').val() && data.novel_intro) {
                    $('#w2p_novel_intro').val(data.novel_intro);
                }

                // 切换到第二步工作台
                $('#w2p-novel-step-upload').slideUp(200);
                $('#w2p-novel-step-preview').slideDown(300);

                renderChaptersTable();
                clearCsfFormWarning();
                showToast(i18n.parseSuccess || 'File parsed successfully!', 'success');
            },
            error: function (xhr, status, err) {
                parseBtn.prop('disabled', false).html('<i class="fa-solid fa-wand-magic-sparkles"></i> Upload & Parse Document');
                showToast('Network Error: ' + err, 'error');
            }
        });
    });

    // 渲染预览表格
    function renderChaptersTable() {
        const tbody = $('#w2p-chapters-preview-tbody');
        tbody.empty();

        let totalWords = 0;
        const volSet = new Set();

        parsedChapters.forEach(function (chap, idx) {
            chap.index = idx + 1;
            totalWords += parseInt(chap.word_count || 0, 10);
            if (chap.volume) volSet.add(chap.volume);

            const tr = $('<tr></tr>').attr('data-idx', idx);
            tr.append('<td><input type="checkbox" class="w2p-chap-checkbox" value="' + idx + '"></td>');
            tr.append('<td>' + chap.index + '</td>');
            tr.append('<td>' + escHtml(chap.chapter_index) + '</td>');
            tr.append('<td><input type="text" class="w2p-edit-vol-input" data-idx="' + idx + '" value="' + escAttr(chap.volume) + '"></td>');
            tr.append('<td><input type="text" class="w2p-edit-title-input" data-idx="' + idx + '" value="' + escAttr(chap.title) + '"></td>');
            tr.append('<td>' + (chap.word_count ? chap.word_count.toLocaleString() : '0') + '</td>');
            tr.append('<td><button type="button" class="w2p-btn w2p-btn-danger w2p-btn-sm w2p-del-row-btn" data-idx="' + idx + '"><i class="fa-solid fa-trash-can"></i></button></td>');

            tbody.append(tr);
        });

        $('#w2p-stat-chapters').text(parsedChapters.length.toLocaleString());
        $('#w2p-stat-volumes').text(volSet.size);
        $('#w2p-stat-words').text(totalWords.toLocaleString());
        clearCsfFormWarning();
    }

    // 监听实时修改
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

    // 单行删除
    $(document).on('click', '.w2p-del-row-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const idx = $(this).data('idx');
        parsedChapters.splice(idx, 1);
        renderChaptersTable();
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

    // 批量删除选中
    $('#w2p-preview-batch-del-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const selected = $('.w2p-chap-checkbox:checked');
        if (selected.length === 0) {
            showToast('Please select at least one chapter to delete.', 'warning');
            return;
        }

        if (!confirm(i18n.confirmDelete || 'Delete selected chapters from list?')) {
            return;
        }

        const indicesToDelete = new Set();
        selected.each(function () {
            indicesToDelete.add(parseInt($(this).val(), 10));
        });

        parsedChapters = parsedChapters.filter((_, idx) => !indicesToDelete.has(idx));
        $('#w2p-check-all-chapters').prop('checked', false);
        renderChaptersTable();
    });

    $('#w2p-preview-reparse-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (isImporting) return;
        $('#w2p-novel-step-preview').slideUp(200);
        $('#w2p-novel-step-upload').slideDown(300);
        parsedChapters = [];
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

        const novelTitle = $('#w2p_novel_title').val();
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
        progressStatus.text(i18n.importingNovel || 'Creating Novel record...');
        progressBar.css('width', '5%');

        $.post(params.ajaxUrl, novelData, function (resNovel) {
            if (!resNovel.success) {
                showToast(resNovel.data || 'Failed to create novel.', 'error');
                progressStatus.text('Error: ' + (resNovel.data || 'Failed to create novel.'));
                return;
            }

            const novelId = resNovel.data.novel_id;
            startBatchImportFlow(novelId, activeTaskId || $('#w2p_current_task_id').val(), 0);

        }).fail(function () {
            showToast('Network error when creating novel.', 'error');
            progressStatus.text('Network error when creating novel.');
        });
    });

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
    // 3. 章节顺序重构与分卷识别 (Fix Chapter Index: Part 1 单书 + Part 2 全自动)
    // =========================================================================
    let currentTargetNovel = null;
    let currentFixChapters = [];
    let autoFixState = {
        isRunning: false,
        queue: [],
        currentIndex: 0,
        total: 0
    };

    // 辅助转义函数 (XSS 防御)
    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }
    function escAttr(str) {
        return (str || '').replace(/"/g, '&quot;');
    }

    // -------------------------------------------------------------------------
    // Part 1: 单体小说章节排序与分卷修复 (Inspect Single Novel)
    // -------------------------------------------------------------------------
    let searchDebounceTimer = null;

    // 1. 输入框防抖实时搜索 (输入即触发，无搜索按钮)
    $('#w2p-fix-search-input').on('input', function () {
        const query = $.trim($(this).val());
        $('#w2p-fix-search-clear-btn').toggle(!!query);
        clearTimeout(searchDebounceTimer);

        if (!query) {
            $('#w2p-fix-search-results-box').addClass('w2p-hidden');
            $('#w2p-fix-search-results-tbody').empty();
            $('#w2p-fix-novel-workbench').addClass('w2p-hidden');
            $('#w2p-fix-chapters-tbody').empty();
            currentTargetNovel = null;
            currentFixChapters = [];
            return;
        }

        searchDebounceTimer = setTimeout(function () {
            doSearchNovels(query);
        }, 300);
    });

    // 清空搜索输入框
    $('#w2p-fix-search-clear-btn').on('click', function () {
        $('#w2p-fix-search-input').val('').trigger('input').focus();
    });

    function doSearchNovels(query) {
        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_search',
            nonce: params.fixNonce,
            query: query
        }, function (res) {
            if (res.success && Array.isArray(res.data.novels)) {
                renderSearchResults(res.data.novels);
            } else {
                $('#w2p-fix-search-results-box').addClass('w2p-hidden');
                $('#w2p-fix-search-results-tbody').empty();
                showToast(res.data || 'No novels found.', 'error');
            }
        });
    }

    // 2. 搜索结果列表渲染 (即使只有一条记录也显示为标准列表)
    function renderSearchResults(novels) {
        const $box = $('#w2p-fix-search-results-box');
        const $tbody = $('#w2p-fix-search-results-tbody').empty();

        if (novels.length === 0) {
            $tbody.html('<tr><td colspan="5" class="w2p-fix-empty-text">No matching novels found.</td></tr>');
            $box.removeClass('w2p-hidden');
            return;
        }

        novels.forEach(function (novel) {
            const tr = $('<tr></tr>');
            tr.append('<td><strong>' + escHtml(novel.id) + '</strong></td>');
            tr.append('<td><strong>' + escHtml(novel.title) + '</strong></td>');
            tr.append('<td>' + escHtml(novel.chapter_count) + ' Chapters</td>');

            const statusHtml = novel.is_finished
                ? '<span class="w2p-badge w2p-badge-success w2p-badge-sm">PROCESSED</span>'
                : '<span class="w2p-badge w2p-badge-secondary w2p-badge-sm">PENDING</span>';
            tr.append('<td>' + statusHtml + '</td>');

            const actionBtn = $('<button type="button" class="w2p-btn w2p-btn-sm w2p-btn-primary w2p-fix-rebuild-novel-btn"><i class="fa-solid fa-wrench"></i> Rebuild</button>');
            actionBtn.data('novel', novel);
            tr.append($('<td></td>').append(actionBtn));

            $tbody.append(tr);
        });

        $box.removeClass('w2p-hidden');
    }

    // 3. 点击操作按钮 (Rebuild) 展开待处理章节工作台
    $(document).on('click', '.w2p-fix-rebuild-novel-btn', function (e) {
        e.preventDefault();
        const novel = $(this).data('novel');
        if (!novel) return;

        currentTargetNovel = novel;
        const $btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Loading...');

        loadNovelChaptersForFix(novel.id, function () {
            $btn.prop('disabled', false).html('<i class="fa-solid fa-wrench"></i> Rebuild');
        });
    });

    function loadNovelChaptersForFix(novelId, callback) {
        const $workbench = $('#w2p-fix-novel-workbench');
        const $tbody = $('#w2p-fix-chapters-tbody');
        $tbody.html('<tr><td colspan="8" class="w2p-fix-loading-row"><i class="fa-solid fa-spinner fa-spin"></i> Loading chapters & identifying indexes...</td></tr>');
        $workbench.removeClass('w2p-hidden');

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_get_chapters',
            nonce: params.fixNonce,
            novel_id: novelId
        }, function (res) {
            if (callback) callback();
            if (res.success && res.data && Array.isArray(res.data.chapters)) {
                currentFixChapters = res.data.chapters;
                renderFixChaptersTable();
            } else {
                currentFixChapters = [];
                $tbody.html('<tr><td colspan="8" class="w2p-fix-empty-text">No chapters found for this novel.</td></tr>');
            }
        }).fail(function () {
            if (callback) callback();
            $tbody.html('<tr><td colspan="8" class="w2p-fix-empty-text w2p-fix-error-text">Failed to load chapters.</td></tr>');
        });
    }

    // 4. 渲染待处理章节列表：复选框，序号，旧index，推荐index，旧分卷，推荐分卷，章节标题，字符数
    function renderFixChaptersTable() {
        const $tbody = $('#w2p-fix-chapters-tbody').empty();
        $('#w2p-check-all-fix-chapters').prop('checked', false);

        if (currentFixChapters.length === 0) {
            $tbody.html('<tr><td colspan="8" class="w2p-fix-empty-text">No chapters available.</td></tr>');
            return;
        }

        currentFixChapters.forEach(function (chap, idx) {
            const tr = $('<tr></tr>');

            // 1. 复选框
            tr.append('<td><input type="checkbox" class="w2p-fix-chap-cb" value="' + idx + '"></td>');

            // 2. 序号
            tr.append('<td>' + (idx + 1) + '</td>');

            // 3. 旧 index
            tr.append('<td>' + escHtml(chap.old_index || '-') + '</td>');

            // 4. 推荐的 index
            const isIdxDiff = chap.new_index !== chap.old_index;
            const recIdxHtml = (isIdxDiff ? ' <span class="w2p-fix-diff-old">' + escHtml(chap.old_index) + '</span>' + '<i class="fa-solid fa-arrow-right"></i>' : '') +
                '<strong class="w2p-font-chapter-index">' + escHtml(chap.new_index) + '</strong>';
            tr.append('<td>' + recIdxHtml + '</td>');

            // 5. 旧分卷
            tr.append('<td>' + escHtml(chap.old_volume || '-') + '</td>');

            // 6. 推荐的分卷
            const isVolDiff = chap.new_volume !== chap.old_volume;
            const recVolHtml = escHtml(chap.new_volume) +
                (isVolDiff ? ' <span class="w2p-fix-diff-old">(' + escHtml(chap.old_volume) + ')</span>' : '');
            tr.append('<td>' + recVolHtml + '</td>');

            // 7. 章节标题
            tr.append('<td>' + escHtml(chap.title) + '</td>');

            // 8. 字符数
            const wordsCount = typeof chap.words !== 'undefined' && chap.words !== null ? Number(chap.words).toLocaleString() : '-';
            tr.append('<td>' + escHtml(wordsCount) + '</td>');

            $tbody.append(tr);
        });
    }

    let lastCheckedFixCb = null;

    // 5. 表头可操作按钮交互与选择
    // 5.1 Shift 键连续多选 (点击 100，按住 Shift 点击 200，连续多选 100~200 项)
    $(document).on('click', '.w2p-fix-chap-cb', function (e) {
        if (lastCheckedFixCb && e.shiftKey) {
            const $cbs = $('.w2p-fix-chap-cb');
            const start = $cbs.index(this);
            const end = $cbs.index(lastCheckedFixCb);

            if (start !== -1 && end !== -1) {
                const isChecked = $(this).prop('checked');
                $cbs.slice(Math.min(start, end), Math.max(start, end) + 1).prop('checked', isChecked);
            }
        }
        lastCheckedFixCb = this;
    });

    // 全选/反选
    $('#w2p-check-all-fix-chapters').on('change', function () {
        const checked = $(this).is(':checked');
        $('.w2p-fix-chap-cb').prop('checked', checked);
        lastCheckedFixCb = null;
    });

    // 5.2 批量设置分卷并自动联动重新计算章节索引
    $('#w2p-fix-batch-vol-btn').on('click', function (e) {
        e.preventDefault();
        const selected = $('.w2p-fix-chap-cb:checked');
        if (selected.length === 0) {
            showToast('Please select at least one chapter.', 'warning');
            return;
        }

        const firstIdx = parseInt(selected.first().val(), 10);
        const defaultVol = currentFixChapters[firstIdx] ? currentFixChapters[firstIdx].new_volume : '第一卷';
        const newVol = prompt('Enter new volume name for selected chapters:', defaultVol);
        if (newVol === null) return;

        const trimmed = $.trim(newVol);
        if (!trimmed) return;

        selected.each(function () {
            const idx = parseInt($(this).val(), 10);
            if (currentFixChapters[idx]) {
                currentFixChapters[idx].new_volume = trimmed;
            }
        });

        // 立即自动联动权威计算新索引并刷新视图
        recalculateAndRenderFixIndexes(false);
        showToast('Volume updated for ' + selected.length + ' chapters. Indexes recalculated.', 'success');
    });

    // 5.3 重新识别 index (复用后端 W2P_Novel_Helper 权威算法)
    $('#w2p-fix-regen-index-btn').on('click', function (e) {
        e.preventDefault();
        recalculateAndRenderFixIndexes(true);
    });

    function recalculateAndRenderFixIndexes(showFeedback) {
        if (!currentFixChapters || currentFixChapters.length === 0) return;

        const $btn = $('#w2p-fix-regen-index-btn').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Regenerating...');

        const payload = currentFixChapters.map(function (chap) {
            return {
                volume: chap.new_volume || '',
                title: chap.title || ''
            };
        });

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_regen_indexes',
            nonce: params.fixNonce || params.importNonce,
            chapters: JSON.stringify(payload)
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="fa-solid fa-list-ol"></i> ' + (i18n.regenIndex || 'Regenerate Chapter Index'));
            if (res.success && res.data && Array.isArray(res.data.items)) {
                res.data.items.forEach(function (item, idx) {
                    if (currentFixChapters[idx]) {
                        currentFixChapters[idx].new_index = item.chapter_index;
                        currentFixChapters[idx].new_volume = item.volume;
                    }
                });
                renderFixChaptersTable();
                if (showFeedback) {
                    showToast(i18n.indexRegenerated || 'Chapter indexes regenerated successfully.', 'success');
                }
            } else {
                if (showFeedback) {
                    showToast(res.data || 'Failed to regenerate indexes.', 'error');
                }
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('<i class="fa-solid fa-list-ol"></i> ' + (i18n.regenIndex || 'Regenerate Chapter Index'));
            if (showFeedback) {
                showToast('Network error during index recalculation.', 'error');
            }
        });
    }

    // 5.4 保存单体小说重建
    $('#w2p-fix-save-novel-btn').on('click', function (e) {
        e.preventDefault();
        if (!currentTargetNovel || !currentFixChapters || currentFixChapters.length === 0) return;

        const $btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

        const savePayload = currentFixChapters.map(function (chap) {
            return {
                id: chap.id,
                new_index: chap.new_index,
                new_volume: chap.new_volume
            };
        });

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_apply_single',
            nonce: params.fixNonce,
            novel_id: currentTargetNovel.id,
            chapters: JSON.stringify(savePayload)
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> ' + (i18n.save || 'Save'));
            if (res.success) {
                showToast('Successfully rebuilt and saved chapter indexes for novel!', 'success');
                currentTargetNovel.is_finished = true;

                // 更新搜索列表中当前书籍状态为 PROCESSED
                $('.w2p-fix-rebuild-novel-btn').each(function () {
                    const n = $(this).data('novel');
                    if (n && n.id === currentTargetNovel.id) {
                        n.is_finished = true;
                        $(this).closest('tr').find('td:nth-child(4)').html('<span class="w2p-badge w2p-badge-success w2p-badge-sm">PROCESSED</span>');
                    }
                });

                // 刷新全量统计
                updateAutoFixStats();

                // 重新加载一次对比列表
                loadNovelChaptersForFix(currentTargetNovel.id);
            } else {
                showToast(res.data || 'Failed to save chapter updates.', 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> ' + (i18n.save || 'Save'));
            showToast('Network error while saving.', 'error');
        });
    });

    // -------------------------------------------------------------------------
    // Part 2: 全自动全量扫描与逐书循环重建
    // -------------------------------------------------------------------------
    // 工作台模式切换 (Workflow Step Navigation)
    // -------------------------------------------------------------------------
    $(document).on('click', '.w2p-workflow-step-btn', function (e) {
        e.preventDefault();
        const mode = $(this).data('mode');
        $('.w2p-workflow-step-btn').removeClass('active');
        $(this).addClass('active');
        $('.w2p-fix-mode-pane').addClass('w2p-hidden');
        $('#w2p-fix-pane-' + mode).removeClass('w2p-hidden');
    });

    // -------------------------------------------------------------------------
    // 全自动统计数据刷新 (仅保留单一下方统计栏)
    // -------------------------------------------------------------------------
    function updateAutoFixStats() {
        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_get_unfixed_novels',
            nonce: params.fixNonce
        }, function (res) {
            if (res.success && res.data) {
                const finished = res.data.finished_count || 0;
                const pending = res.data.unfixed_count || 0;
                $('#w2p-fix-stat-finished').text(finished);
                $('#w2p-fix-stat-unfixed').text(pending);
            }
        });
    }

    // 启动全自动全量扫描
    $('#w2p-fix-auto-start-btn').on('click', function (e) {
        e.preventDefault();
        if (autoFixState.isRunning) return;

        const $startBtn = $(this);
        $startBtn.prop('disabled', true);

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_get_unfixed_novels',
            nonce: params.fixNonce
        }, function (res) {
            $startBtn.prop('disabled', false);
            if (!res.success || !res.data || !Array.isArray(res.data.unfixed)) {
                showToast('Failed to fetch novel list.', 'error');
                return;
            }

            const unfixed = res.data.unfixed;
            if (unfixed.length === 0) {
                showToast('All novels have already been processed! No pending books.', 'success');
                return;
            }

            // 初始化全自动队列状态
            autoFixState.isRunning = true;
            autoFixState.queue = unfixed;
            autoFixState.total = unfixed.length;
            autoFixState.currentIndex = 0;

            // 切换按钮状态 (使用 class 避免 !important 冲突)
            $('#w2p-fix-auto-start-btn').addClass('w2p-hidden');
            $('#w2p-fix-auto-stop-btn').removeClass('w2p-hidden').prop('disabled', false).html('<i class="fa-solid fa-stop"></i> ' + (i18n.stop || 'Stop'));

            // 展开进度条与处理日志表格
            $('#w2p-fix-auto-progress').removeClass('w2p-hidden');
            $('#w2p-fix-auto-log-box').removeClass('w2p-hidden');

            // 清空运行日志
            $('#w2p-fix-auto-log-tbody').empty();

            // 启动循环处理
            processNextAutoNovel();
        }).fail(function () {
            $startBtn.prop('disabled', false);
            showToast('Network error during initial scan.', 'error');
        });
    });

    function processNextAutoNovel() {
        if (!autoFixState.isRunning || autoFixState.currentIndex >= autoFixState.total) {
            finishAutoFix(autoFixState.currentIndex >= autoFixState.total);
            return;
        }

        const currentNovel = autoFixState.queue[autoFixState.currentIndex];
        const step = autoFixState.currentIndex + 1;
        const total = autoFixState.total;
        const pct = Math.min(100, Math.round((step / total) * 100));

        // 更新进度条
        $('#w2p-fix-auto-bar').css('width', pct + '%');
        $('#w2p-fix-auto-status').text('Rebuilding novel: ' + currentNovel.title + ' (ID: ' + currentNovel.id + ')...');
        $('#w2p-fix-auto-count').text(step + ' / ' + total);

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_auto_step',
            nonce: params.fixNonce,
            novel_id: currentNovel.id
        }, function (res) {
            if (!autoFixState.isRunning) {
                finishAutoFix(false);
                return;
            }

            const $tbody = $('#w2p-fix-auto-log-tbody');
            const tr = $('<tr></tr>');
            tr.append('<td><strong>' + escHtml(currentNovel.id) + '</strong></td>');
            tr.append('<td><strong>《' + escHtml(currentNovel.title) + '》</strong></td>');

            if (res.success) {
                const count = res.data ? res.data.chapter_count : 0;
                tr.append('<td>' + count + ' Chapters</td>');
                tr.append('<td><span class="w2p-badge w2p-badge-success w2p-badge-sm">SUCCESS</span></td>');
            } else {
                tr.append('<td>-</td>');
                tr.append('<td><span class="w2p-badge w2p-badge-danger w2p-badge-sm">FAILED</span></td>');
            }

            $tbody.prepend(tr);

            // 步进到下一本
            autoFixState.currentIndex++;
            setTimeout(processNextAutoNovel, 120);
        }).fail(function () {
            if (!autoFixState.isRunning) {
                finishAutoFix(false);
                return;
            }
            const $tbody = $('#w2p-fix-auto-log-tbody');
            const tr = $('<tr></tr>');
            tr.append('<td><strong>' + escHtml(currentNovel.id) + '</strong></td>');
            tr.append('<td><strong>《' + escHtml(currentNovel.title) + '》</strong></td>');
            tr.append('<td>-</td>');
            tr.append('<td><span class="w2p-badge w2p-badge-danger w2p-badge-sm">NETWORK ERROR</span></td>');
            $tbody.prepend(tr);

            autoFixState.currentIndex++;
            setTimeout(processNextAutoNovel, 300);
        });
    }

    function finishAutoFix(completedAll) {
        autoFixState.isRunning = false;
        $('#w2p-fix-auto-stop-btn').addClass('w2p-hidden').prop('disabled', false).html('<i class="fa-solid fa-stop"></i> ' + (i18n.stop || 'Stop'));
        $('#w2p-fix-auto-start-btn').removeClass('w2p-hidden').prop('disabled', false);

        if (completedAll) {
            $('#w2p-fix-auto-status').text('All pending novels successfully rebuilt!');
            $('#w2p-fix-auto-bar').css('width', '100%');
            showToast('Auto rebuild completed for all pending novels!', 'success');
        } else {
            $('#w2p-fix-auto-status').text('Auto rebuild paused.');
            showToast('Auto rebuild paused.', 'info');
        }

        updateAutoFixStats();
    }

    // 停止全自动循环 (提供即时反馈)
    $('#w2p-fix-auto-stop-btn').on('click', function (e) {
        e.preventDefault();
        autoFixState.isRunning = false;
        $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Stopping...');
        $('#w2p-fix-auto-status').text('Pausing after current novel finishes...');
    });

    // 清空已完成记录
    $('#w2p-fix-clear-progress-btn').on('click', function (e) {
        e.preventDefault();
        if (!confirm(i18n.confirmClear || 'Clear all progress records? Novels will be re-eligible for rebuild.')) {
            return;
        }

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_clear_progress',
            nonce: params.fixNonce
        }, function (res) {
            if (res.success) {
                showToast(res.data.message || 'Progress records cleared.', 'success');
                $('#w2p-fix-auto-progress').addClass('w2p-hidden');
                $('#w2p-fix-auto-log-box').addClass('w2p-hidden');
                $('#w2p-fix-auto-log-tbody').empty();
                updateAutoFixStats();
            }
        });
    });

    // 页面初次加载时更新统计
    if ($('#w2p-tab-fix-index').length) {
        updateAutoFixStats();
    }

    // =========================================================================
    // Novel 级联删除（edit-novel 列表页：Delete w/ Chapters）
    // =========================================================================
    if ($('a.w2p-delete-novel-with-chapters-btn').length) {
        $('body').append(
            '<div id="w2p-novel-delete-modal" class="w2p-modal-backdrop" style="display:none;">' +
            '<div class="w2p-nd-box">' +
            '<h4>' + escHtml(i18n.deletingNovel || 'Deleting Novel') + '</h4>' +
            '<div class="w2p-nd-count-row"><span>' + escHtml(i18n.chaptersToDelete || 'Chapters to delete') + ':</span> <strong id="w2p-nd-count">&mdash;</strong></div>' +
            '<p class="w2p-nd-text" id="w2p-nd-text">' + escHtml(i18n.countingChapters || 'Counting related chapters...') + '</p>' +
            '<div class="w2p-nd-progress-bg"><div class="w2p-nd-progress-fill" id="w2p-nd-progress-fill"></div></div>' +
            '</div></div>'
        );

        var w2pNovelDelete = {
            nonce: params.deleteNonce,
            novelId: 0,
            total: 0,
            deleted: 0,
            openModal: function () {
                $('#w2p-nd-count').text('&mdash;');
                $('#w2p-nd-text').text(i18n.countingChapters || 'Counting related chapters...');
                $('#w2p-nd-progress-fill').css('width', '0%');
                $('#w2p-novel-delete-modal').show();
            },
            closeModal: function () {
                $('#w2p-novel-delete-modal').hide();
            },
            setProgress: function () {
                var pct = this.total ? Math.min(100, Math.round(this.deleted / this.total * 100)) : 100;
                $('#w2p-nd-text').text((i18n.deletingChapters || 'Deleting chapters') + ': ' + this.deleted + ' / ' + this.total);
                $('#w2p-nd-progress-fill').css('width', pct + '%');
            },
            start: function (novelId) {
                this.novelId = novelId;
                this.total = 0;
                this.deleted = 0;
                this.openModal();
                this.deleteNextBatch(0);
            },
            deleteNextBatch: function (attempt) {
                var self = this;
                $.post(params.ajaxUrl, {
                    action: 'w2p_novel_delete_batch',
                    nonce: self.nonce,
                    novel_id: self.novelId
                }, function (res) {
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
                        self.total = d.total;
                        $('#w2p-nd-count').text(self.total);
                    }
                    self.deleted += d.batch_deleted;
                    self.setProgress();
                    if (d.done) {
                        $('#w2p-nd-text').text(i18n.deletingNovelItself || 'Deleting the novel itself...');
                        $('#w2p-nd-progress-fill').css('width', '100%');
                        self.finalize();
                        return;
                    }
                    setTimeout(function () { self.deleteNextBatch(0); }, 120);
                }).fail(function () {
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
                $.post(params.ajaxUrl, {
                    action: 'w2p_novel_delete_final',
                    nonce: self.nonce,
                    novel_id: self.novelId
                }, function (res2) {
                    if (res2 && res2.success) {
                        window.location.reload();
                    } else {
                        alert(res2 && res2.data ? res2.data : 'Delete failed');
                        self.closeModal();
                    }
                }).fail(function () {
                    alert('Network error');
                    self.closeModal();
                });
            }
        };

        $(document).on('click', 'a.w2p-delete-novel-with-chapters-btn', function (e) {
            e.preventDefault();
            var href = $(this).attr('href');
            if (!href || href === '#') { return; }
            var m = href.match(/post_id=(\d+)/);
            if (!m) { return; }
            if (confirm(i18n.confirmDeleteNovel || 'Delete this novel AND all of its chapters? This cannot be undone.')) {
                w2pNovelDelete.start(m[1]);
            }
        });
    }
});
