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
            tr.append('<td><code>' + escHtml(chap.chapter_index) + '</code></td>');
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

    // 全选/反选
    $('#w2p-check-all-chapters').on('change', function (e) {
        e.stopPropagation();
        const checked = $(this).is(':checked');
        $('.w2p-chap-checkbox').prop('checked', checked);
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

    // 取消重新上传
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
            } else {
                showToast(info, 'error');
                progressStatus.text('Paused/Error: ' + info);
            }
        }

        importNextBatch();
    }

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
    // 3. 章节顺序重构与分卷识别 (Fix Chapter Index)
    // =========================================================================
    let fixState = {
        isScanning: false,
        isAuto: false,
        total: 0,
        scanned: 0,
        scanResults: [],
        context: {}
    };

    $('#w2p_fix_scan_mode').on('change', function (e) {
        e.stopPropagation();
        if ($(this).val() === 'by_novel') {
            $('#w2p_fix_novel_selector_box').show();
        } else {
            $('#w2p_fix_novel_selector_box').hide();
        }
        updateFixTotalCount();
        clearCsfFormWarning();
    });

    $('#w2p_fix_novel_id').on('change', function (e) {
        e.stopPropagation();
        updateFixTotalCount();
        clearCsfFormWarning();
    });

    function getFixParams() {
        return {
            scan_mode: $('#w2p_fix_scan_mode').val(),
            novel_id: $('#w2p_fix_novel_id').val() || 0,
            index_format: $('#w2p_fix_index_format').val() || '01-00001',
            auto_volume: $('#w2p_fix_auto_volume').is(':checked') ? 1 : 0
        };
    }

    function updateFixTotalCount() {
        const p = getFixParams();
        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_get_total',
            nonce: params.fixNonce,
            ...p
        }, function (res) {
            if (res.success) {
                fixState.total = res.data.total || 0;
                $('#w2p-fix-progress-text').text('0 / ' + fixState.total.toLocaleString());
                if (res.data.finished_count > 0) {
                    $('#w2p-fix-finished-text').text('Processed Novels: ' + res.data.finished_count);
                    $('#w2p-fix-finished-row').show();
                } else {
                    $('#w2p-fix-finished-row').hide();
                }
            }
        });
    }

    // 扫描预览
    $('#w2p-fix-scan-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearCsfFormWarning();

        if (fixState.isScanning) return;

        fixState.isScanning = true;
        fixState.scanned = 0;
        fixState.scanResults = [];
        fixState.context = {};

        $('#w2p-fix-scan-btn, #w2p-fix-auto-btn, #w2p-fix-execute-btn').hide();
        $('#w2p-fix-stop-btn').show();
        $('#w2p-fix-logs-tbody').empty();

        const p = getFixParams();
        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_get_total',
            nonce: params.fixNonce,
            ...p
        }, function (res) {
            if (res.success) {
                fixState.total = res.data.total || 0;
                runScanBatch();
            } else {
                showToast('Failed to get total count.', 'error');
                stopFix();
            }
        });
    });

    function runScanBatch() {
        if (!fixState.isScanning || fixState.scanned >= fixState.total) {
            finishScan();
            return;
        }

        const p = getFixParams();
        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_scan',
            nonce: params.fixNonce,
            offset: fixState.scanned,
            batch_size: 25,
            context: JSON.stringify(fixState.context),
            ...p
        }, function (res) {
            if (!res.success || !fixState.isScanning) {
                finishScan();
                return;
            }

            const d = res.data;
            fixState.scanned += d.count;
            fixState.context = d.context;
            fixState.scanResults = fixState.scanResults.concat(d.logs);

            appendFixLogs(d.logs);
            updateFixProgressUI();

            if (d.finished_novel_id) {
                $.post(params.ajaxUrl, {
                    action: 'w2p_novel_fix_mark_finished',
                    nonce: params.fixNonce,
                    novel_id: d.finished_novel_id
                });
            }

            setTimeout(runScanBatch, 80);
        }).fail(function () {
            finishScan();
        });
    }

    function appendFixLogs(logs) {
        const tbody = $('#w2p-fix-logs-tbody');
        logs.forEach(function (item) {
            const tr = $('<tr></tr>');
            tr.append('<td><strong>' + item.index + '</strong> <span style="color:#94a3b8;font-size:11px;">(' + item.old_index + ')</span></td>');
            tr.append('<td>' + item.volume + ' <span style="color:#94a3b8;font-size:11px;">(' + item.old_volume + ')</span></td>');
            tr.append('<td><a href="' + item.edit_link + '" target="_blank">' + item.title + '</a></td>');
            tbody.append(tr);
        });

        const container = tbody.closest('.w2p-log-container');
        if (container.length) {
            container.scrollTop(container[0].scrollHeight);
        }
    }

    function updateFixProgressUI() {
        const pct = fixState.total > 0 ? Math.round((fixState.scanned / fixState.total) * 100) : 0;
        $('#w2p-fix-progress-bar').css('width', pct + '%');
        $('#w2p-fix-progress-text').text(fixState.scanned + ' / ' + fixState.total.toLocaleString());
    }

    function finishScan() {
        fixState.isScanning = false;
        $('#w2p-fix-stop-btn').hide();
        $('#w2p-fix-scan-btn, #w2p-fix-auto-btn').show();

        if (fixState.scanResults.length > 0) {
            $('#w2p-fix-execute-btn, #w2p-fix-reset-btn').show();
            showToast('Scan complete: ' + fixState.scanResults.length + ' chapters found.', 'success');
        }
        clearCsfFormWarning();
    }

    // 批量应用更新
    $('#w2p-fix-execute-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearCsfFormWarning();

        if (fixState.scanResults.length === 0) return;

        if (!confirm('Apply index updates for ' + fixState.scanResults.length + ' chapters?')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Applying...');

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_execute',
            nonce: params.fixNonce,
            scan_results: JSON.stringify(fixState.scanResults)
        }, function (res) {
            btn.prop('disabled', false).html('<i class="fa-solid fa-bolt"></i> Apply Updates');
            if (res.success) {
                showToast('Successfully updated ' + res.data.updated + ' chapters.', 'success');
                btn.hide();
                $('#w2p-fix-reset-btn').show();
            } else {
                showToast('Update failed: ' + res.data, 'error');
            }
            clearCsfFormWarning();
        }).fail(function () {
            btn.prop('disabled', false).html('<i class="fa-solid fa-bolt"></i> Apply Updates');
            showToast('Network error during execution.', 'error');
        });
    });

    // 自动批量重构
    $('#w2p-fix-auto-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearCsfFormWarning();

        if (!confirm(i18n.confirmAutoFix || 'Start auto rebuild?')) return;

        fixState.isAuto = true;
        fixState.scanned = 0;
        fixState.context = {};

        $('#w2p-fix-scan-btn, #w2p-fix-auto-btn, #w2p-fix-execute-btn, #w2p-fix-reset-btn').hide();
        $('#w2p-fix-stop-btn').show();
        $('#w2p-fix-logs-tbody').empty();

        const p = getFixParams();
        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_get_total',
            nonce: params.fixNonce,
            ...p
        }, function (res) {
            if (res.success) {
                fixState.total = res.data.total || 0;
                runAutoLoop();
            } else {
                stopFix();
            }
        });
    });

    function runAutoLoop() {
        if (!fixState.isAuto || fixState.scanned >= fixState.total) {
            stopFix();
            showToast('Auto rebuild complete!', 'success');
            return;
        }

        const p = getFixParams();
        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_scan',
            nonce: params.fixNonce,
            offset: fixState.scanned,
            batch_size: 20,
            context: JSON.stringify(fixState.context),
            ...p
        }, function (scanRes) {
            if (!scanRes.success || !fixState.isAuto) {
                stopFix();
                return;
            }

            const logs = scanRes.data.logs;
            fixState.context = scanRes.data.context;

            if (logs.length === 0) {
                stopFix();
                return;
            }

            // 执行这一批
            $.post(params.ajaxUrl, {
                action: 'w2p_novel_fix_execute',
                nonce: params.fixNonce,
                scan_results: JSON.stringify(logs)
            }, function (execRes) {
                if (execRes.success && fixState.isAuto) {
                    fixState.scanned += logs.length;
                    appendFixLogs(logs);
                    updateFixProgressUI();

                    if (scanRes.data.finished_novel_id) {
                        $.post(params.ajaxUrl, {
                            action: 'w2p_novel_fix_mark_finished',
                            nonce: params.fixNonce,
                            novel_id: scanRes.data.finished_novel_id
                        });
                    }

                    setTimeout(runAutoLoop, 300);
                } else {
                    stopFix();
                }
            }).fail(function () {
                stopFix();
            });
        }).fail(function () {
            stopFix();
        });
    }

    function stopFix() {
        fixState.isScanning = false;
        fixState.isAuto = false;
        $('#w2p-fix-stop-btn').hide();
        $('#w2p-fix-scan-btn, #w2p-fix-auto-btn, #w2p-fix-reset-btn').show();
        clearCsfFormWarning();
    }

    $('#w2p-fix-stop-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        stopFix();
    });

    $('#w2p-fix-reset-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        fixState.scanned = 0;
        fixState.scanResults = [];
        fixState.context = {};
        $('#w2p-fix-logs-tbody').html('<tr><td colspan="3" style="text-align:center;color:#94a3b8;">Ready to scan.</td></tr>');
        $('#w2p-fix-progress-bar').css('width', '0%');
        updateFixTotalCount();
        $('#w2p-fix-execute-btn, #w2p-fix-reset-btn').hide();
        clearCsfFormWarning();
    });

    $('#w2p-fix-clear-history-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!confirm(i18n.confirmClear || 'Clear all progress records?')) return;

        $.post(params.ajaxUrl, {
            action: 'w2p_novel_fix_clear_progress',
            nonce: params.fixNonce
        }, function (res) {
            if (res.success) {
                showToast(res.data.message || 'Progress cleared.', 'success');
                updateFixTotalCount();
            }
            clearCsfFormWarning();
        });
    });

    // 辅助转义函数
    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }
    function escAttr(str) {
        return (str || '').replace(/"/g, '&quot;');
    }

    // 初始化获取一次总数
    if ($('#w2p-tab-fix-index').length) {
        updateFixTotalCount();
    }
    clearCsfFormWarning();

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
