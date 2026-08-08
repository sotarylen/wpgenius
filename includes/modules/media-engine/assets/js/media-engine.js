/**
 * Media Engine - Batch Processing UI
 * Handles the frontend logic for the Media Processing Center
 */
(function ($) {
    'use strict';

    const MediaProcessingUI = {
        queue: [],
        processing: false,
        stopped: false,
        batchSize: (window.w2pMediaConfig && window.w2pMediaConfig.batchSize) || 5,
        currentBatchIndex: 0,
        batches: [],
        stats: {
            total: 0,
            pending: 0,
            processing: 0,
            completed: 0,
            failed: 0,
            failedTotal: 0          // 累计失败数（展示口径：跨轮次累计失败事件）
        },
        autoMode: 'idle',          // 'idle' | 'running' | 'paused' | 'completed' | 'stopped'
        pauseRequested: false,     // 点击暂停置 true；当前批次 AJAX 回调末尾消费
        stopRequested: false,      // 自动模式下点击停止置 true；当前批次回调末尾消费（优雅停止）
        roundStartedCompleted: 0,  // 本轮开始时的已完成数（用于无进展检测）
        roundNoProgress: 0,        // 连续无进展轮数
        maxNoProgressRounds: (window.w2pMediaEngine && window.w2pMediaEngine.max_no_progress_rounds) || 3, // 防死循环阈值
        failedIds: [],             // 本轮失败 id 集合（统计展示用）
        lastRoundFailedIds: [],    // 上一轮失败 id 集合（防死循环「假进展」检测）
        lastRoundQueueIds: [],     // 上一轮扫描队列 id（防死循环「假进展」检测）
        queueUnchanged: false,     // 本轮队列与上轮完全一致（无任何附件离开待处理集合）
        autoRound: 0,              // 当前轮次计数

        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
            // 确保初始按钮状态符合 idle 状态矩阵（页面加载时）
            this.setAutoUI('idle');
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            const self = this;

            $('#w2p-recheck-environment').on('click', function () {
                self.recheckEnvironment($(this));
            });

            $('#w2p-get-stats').on('click', function () {
                self.scanAttachments();
            });

            $('#w2p-start-conversion').on('click', function () {
                if (self.queue.length === 0) {
                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('Please scan for attachments first.', 'warning');
                    }
                    return;
                }

                if (typeof w2p !== 'undefined' && w2p.confirm) {
                    w2p.confirm(
                        'Start batch processing ' + self.queue.length + ' attachments (batch size: ' + self.batchSize + ')?',
                        function () {
                            self.startBatchProcessing();
                        }
                    );
                } else if (confirm('Start batch processing ' + self.queue.length + ' attachments (batch size: ' + self.batchSize + ')?')) {
                    self.startBatchProcessing();
                }
            });

            $('#w2p-stop-conversion').on('click', function () {
                self.stopProcessing();
            });

            // 全自动处理
            $('#w2p-start-auto').on('click', function () {
                self.startAutoProcessing();
            });

            // 暂停/恢复
            $('#w2p-pause-auto').on('click', function () {
                if (self.autoMode === 'running' && !self.pauseRequested) {
                    self.pauseAutoProcessing();
                } else if (self.autoMode === 'paused') {
                    self.resumeAutoProcessing();
                }
            });
        },

        /**
         * Recheck environment
         */
        recheckEnvironment: function ($button) {
            $button.prop('disabled', true);
            $button.find('i').removeClass().addClass('fa-solid fa-spinner fa-spin');

            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_execute_wpcli',
                    nonce: w2pMediaEngine.nonce,
                    command: 'wp media-engine check_env'
                },
                success: function (response) {
                    if (response.success) {
                        if (typeof w2p !== 'undefined' && w2p.toast) {
                            w2p.toast('Environment check completed. Reloading...', 'success');
                        }
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        $button.prop('disabled', false);
                        $button.find('i').removeClass().addClass('fa-solid fa-rotate');
                    }
                }
            });
        },

        /**
         * Scan attachments (manual flow — behavior unchanged)
         */
        scanAttachments: function () {
            const self = this;
            const $button = $('#w2p-get-stats');

            $button.prop('disabled', true).find('i').removeClass().addClass('fa-solid fa-spinner fa-spin');
            $('#w2p-processing-output').show();
            $('#w2p-output-content').html('<div style="color: #fbbf24;">Scanning...</div>');

            self.fetchPendingAttachments(function (response) {
                if (response.success) {
                    self.displayQueue();

                    $('#w2p-output-content').html(
                        '<div style="color: #10b981;">✓ Found ' + self.queue.length + ' attachments</div>'
                    );

                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('Found ' + self.queue.length + ' attachments', 'success');
                    }
                }
            }).always(function () {
                $button.prop('disabled', false).find('i').removeClass().addClass('fa-solid fa-chart-bar');
            });
        },

        /**
         * Display queue
         */
        displayQueue: function () {
            const self = this;
            const $tbody = $('#w2p-attachment-tbody');
            const $list = $('#w2p-attachment-list');

            $tbody.empty();

            if (self.queue.length === 0) {
                $list.hide();
                return;
            }

            self.queue.forEach(function (attachment, index) {
                const row = self.createAttachmentRow(attachment, index);
                $tbody.append(row);
            });

            // Fix: Remove hidden class explicitly
            $list.removeClass('w2p-hidden').show();
            self.updateQueueStats();
        },

        /**
         * Create row
         */
        createAttachmentRow: function (attachment, index) {
            const thumb = attachment.thumb_url ?
                '<img src="' + attachment.thumb_url + '" class="w2p-thumb-img">' :
                '<div class="w2p-thumb-placeholder"></div>';

            const fileName = attachment.file_name || 'ID: ' + attachment.id;
            const fileSize = attachment.file_size ? '<span class="w2p-file-size">' + attachment.file_size + '</span>' : '';

            let parentPost = '<div class="w2p-parent-info">Unattached</div>';
            if (attachment.parent_post) {
                const url = attachment.parent_url || '#';
                parentPost = '<div class="w2p-parent-info">' +
                    '<i class="fa-regular fa-file-lines"></i> ' +
                    '<a href="' + url + '" class="w2p-post-link" target="_blank" title="' + attachment.parent_post + '">' +
                    attachment.parent_post +
                    '</a></div>';
            }

            return $('<tr>')
                .attr('id', 'attachment-row-' + index)
                .attr('data-id', attachment.id)
                .html(
                    '<td class="w2p-cell-thumb">' + thumb + '</td>' +
                    '<td class="w2p-cell-name">' +
                    '<div class="w2p-filename w2p-file-meta">' + fileName + fileSize + '</div>' +
                    parentPost +
                    '</td>' +
                    '<td class="status-cell">' + this.getStatusBadge('PENDING') + '</td>'
                );
        },

        /**
         * Get status badge
         */
        getStatusBadge: function (status, message) {
            const badges = {
                'PENDING': '<span class="w2p-status-badge w2p-status-pending">PENDING</span>',
                'PROCESSING': '<span class="w2p-status-badge w2p-status-processing"><i class="fa-solid fa-spinner fa-spin"></i> PROCESSING</span>',
                'DONE': '<span class="w2p-status-badge w2p-status-success"><i class="fa-solid fa-check"></i> DONE</span>',
                'FAILED': '<span class="w2p-status-badge w2p-status-error"><i class="fa-solid fa-xmark"></i> FAILED</span>'
            };

            let html = badges[status] || badges['PENDING'];

            // Append error message if exists
            if (status === 'FAILED' && message) {
                html += '<div style="font-size: 11px; color: #ef4444; margin-top: 4px;">' + message + '</div>';
            }

            return html;
        },

        /**
         * Update stats
         */
        updateQueueStats: function () {
            const self = this;

            // Check if wp.template is available
            if (typeof wp !== 'undefined' && wp.template) {
                try {
                    const template = wp.template('w2p-media-queue-stats');
                    $('#w2p-queue-stats').html(template(self.stats));
                    return;
                } catch (e) {
                    console.error('Template rendering failed:', e);
                }
            }

            // Fallback to simple string if template fails or is missing
            $('#w2p-queue-stats').html(
                'Total: ' + self.stats.total + ' | ' +
                'Pending: ' + self.stats.pending + ' | ' +
                'Processing: ' + self.stats.processing + ' | ' +
                'Completed: ' + self.stats.completed + ' | ' +
                'Failed: ' + self.stats.failed
            );
        },

        /**
         * Start batch processing
         */
        startBatchProcessing: function () {
            const self = this;

            if (self.processing) return;

            // 手动批处理启动时重置自动模式状态，避免残留的 completed/stopped
            // 使 stopProcessing 误走自动分支（不置 stopRequested 也不 autoStop）而硬停失效
            self.autoMode = 'idle';
            self.processing = true;
            self.stopped = false;
            self.currentBatchIndex = 0;

            // Split into batches
            self.batches = [];
            for (let i = 0; i < self.queue.length; i += self.batchSize) {
                self.batches.push(self.queue.slice(i, i + self.batchSize));
            }

            // Show stop button
            $('#w2p-start-conversion').hide();
            $('#w2p-start-parallel').hide();
            $('#w2p-stop-conversion').removeClass('w2p-hidden').show();

            self.processNextBatch();
        },

        /**
         * Process next batch
         */
        processNextBatch: function () {
            const self = this;

            if (self.stopped || self.currentBatchIndex >= self.batches.length) {
                self.processing = false;
                self.showCompletionMessage();
                return;
            }

            const batch = self.batches[self.currentBatchIndex];
            const batchIds = batch.map(item => item.id);
            const startIndex = self.currentBatchIndex * self.batchSize;

            // Update output
            $('#w2p-output-content').html(
                '<div style="color: #3b82f6;">Processing batch ' + (self.currentBatchIndex + 1) + '/' + self.batches.length + '</div>' +
                '<div style="margin-top: 4px; font-size: 12px;">' + batchIds.length + ' images in this batch</div>'
            );

            // Update all rows in batch to PROCESSING
            batch.forEach((item, idx) => {
                const $row = $('#attachment-row-' + (startIndex + idx));
                self.stats.pending--;
                self.stats.processing++;
                self.updateRowStatus($row, 'PROCESSING', 'Batch processing...');
            });
            self.updateQueueStats();

            // Call batch API
            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_process_batch',
                    nonce: w2pMediaEngine.nonce,
                    attachment_ids: batchIds
                },
                success: function (response) {
                    if (response.success) {
                        const stats = response.data.stats;

                        // Update each row based on results
                        batch.forEach((item, idx) => {
                            const $row = $('#attachment-row-' + (startIndex + idx));
                            const convertResult = stats.convert[item.id];

                            self.stats.processing--;

                            if (convertResult && convertResult.success) {
                                self.stats.completed++;
                                self.updateRowStatus($row, 'DONE', 'Completed');
                            } else {
                                self.stats.failed++;
                                self.stats.failedTotal++;
                                self.updateRowStatus($row, 'FAILED', convertResult?.error || 'Unknown error');
                            }
                        });

                        self.updateQueueStats();

                        // Move to next batch
                        self.currentBatchIndex++;
                        if (!self.stopped) {
                            setTimeout(() => self.processNextBatch(), 500);
                        }
                    } else {
                        // Batch failed, mark all as failed
                        batch.forEach((item, idx) => {
                            const $row = $('#attachment-row-' + (startIndex + idx));
                            self.stats.processing--;
                            self.stats.failed++;
                            self.stats.failedTotal++;
                            self.updateRowStatus($row, 'FAILED', 'Batch failed');
                        });
                        self.updateQueueStats();

                        // Continue anyway
                        self.currentBatchIndex++;
                        if (!self.stopped) {
                            setTimeout(() => self.processNextBatch(), 500);
                        }
                    }
                },
                error: function () {
                    // Mark all as failed
                    batch.forEach((item, idx) => {
                        const $row = $('#attachment-row-' + (startIndex + idx));
                        self.stats.processing--;
                        self.stats.failed++;
                        self.stats.failedTotal++;
                        self.updateRowStatus($row, 'FAILED', 'Request failed');
                    });
                    self.updateQueueStats();

                    // Continue anyway
                    self.currentBatchIndex++;
                    if (!self.stopped) {
                        setTimeout(() => self.processNextBatch(), 1000);
                    }
                }
            });
        },

        /**
         * Stop processing
         * 手动模式（autoMode==='idle'）：保持现有硬停行为
         * 自动模式（running/paused）：优雅停止 —— 置 stopRequested，当前批次完成后结束整个流程
         */
        stopProcessing: function () {
            const self = this;

            // 自动模式下：置 stopRequested，由批次回调末尾消费
            if (self.autoMode !== 'idle') {
                if (self.autoMode === 'running' || self.autoMode === 'paused') {
                    self.stopRequested = true;

                    $('#w2p-output-content').append(
                        '<div style="color: #f59e0b;">⛔ 已请求停止，当前批次完成后结束…</div>'
                    );

                    if (typeof w2p !== 'undefined' && w2p.toast) {
                        w2p.toast('已请求停止，当前批次完成后结束', 'warning');
                    }
                }

                // 暂停状态下没有进行中的批次，直接结束
                if (self.autoMode === 'paused') {
                    self.autoStop('用户停止');
                }
                return;
            }

            // 手动模式：保持现有硬停行为
            self.stopped = true;
            self.processing = false;

            $('#w2p-stop-conversion').hide();
            $('#w2p-start-conversion').show();
            $('#w2p-start-parallel').show();

            $('#w2p-output-content').html(
                '<div style="color: #f59e0b;">⏸ Stopped by user</div>' +
                '<div style="margin-top: 8px;">Completed: ' + self.stats.completed + ' | Failed: ' + self.stats.failed + '</div>'
            );

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast('Processing stopped', 'warning');
            }
        },

        /**
         * Update row status
         */
        updateRowStatus: function ($row, status, message) {
            $row.find('.status-cell').html(this.getStatusBadge(status, message));
        },

        /**
         * Show completion
         */
        showCompletionMessage: function () {
            const self = this;

            $('#w2p-stop-conversion').addClass('w2p-hidden').hide();
            $('#w2p-start-conversion').show();
            $('#w2p-start-parallel').show();

            const message = self.stopped ? 'Stopped by user' : 'Processing complete!';

            $('#w2p-output-content').html(
                '<div style="color: ' + (self.stopped ? '#f59e0b' : '#10b981') + ';">' + (self.stopped ? '⏸' : '✓') + ' ' + message + '</div>' +
                '<div style="margin-top: 8px;">Completed: ' + self.stats.completed + ' | Failed: ' + self.stats.failedTotal + '</div>'
            );

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast(message, self.stopped ? 'warning' : 'success');
            }
        },

        /* ======================================================================
         * 全自动处理模式（MediaEngine 自动化处理）
         * 自动循环：扫描 → 批次转换 → 再扫描 → 再转换，直到全部处理完成。
         * 失败重试依赖「每轮重新 scan」：转换失败（mime 未变）或 Minio 失败（offload
         * meta 未写）的图片，下次 scan 天然返回，无需前端维护失败 ID 列表。
         * ====================================================================== */

        /**
         * 启动全自动处理（入口）
         */
        startAutoProcessing: function () {
            const self = this;

            if (self.autoMode === 'running' || self.autoMode === 'paused') return;

            // 手动批处理进行中不允许启动自动模式
            if (self.autoMode === 'idle' && self.processing) {
                if (typeof w2p !== 'undefined' && w2p.toast) {
                    w2p.toast('请先停止当前批处理，再启动全自动处理', 'warning');
                }
                return;
            }

            // 重置自动模式状态
            self.autoMode = 'running';
            self.pauseRequested = false;
            self.stopRequested = false;
            self.roundNoProgress = 0;
            self.failedIds = [];
            self.lastRoundFailedIds = [];
            self.lastRoundQueueIds = [];
            self.queueUnchanged = false;
            self.autoRound = 0;
            self.roundStartedCompleted = 0;
            self.currentBatchIndex = 0;
            self.batches = [];
            self.stopped = false;
            self.processing = true;

            self.setAutoUI('running');

            $('#w2p-processing-output').show();
            $('#w2p-output-content').html('<div style="color: #10b981;">▶ 全自动处理已启动</div>');

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast('全自动处理已启动', 'success');
            }

            self.autoScanAndProcess();
        },

        /**
         * 获取待处理附件（扫描 AJAX 封装，手动/自动共用）
         * 自动模式下：已完成数与累计失败数跨轮次保留（供「已完成 X / 失败 Y」展示），
         * 本轮失败数按轮重置（用于无进展检测）
         */
        fetchPendingAttachments: function (callback) {
            const self = this;
            const prevCompleted = self.stats.completed || 0;
            const prevFailedTotal = self.stats.failedTotal || 0;

            return $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_scan_attachments',
                    nonce: w2pMediaEngine.nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.queue = response.data.attachments || [];
                        self.stats = {
                            total: self.queue.length,
                            pending: self.queue.length,
                            processing: 0,
                            completed: 0,
                            failed: 0,
                            failedTotal: 0
                        };

                        // 自动模式下：已完成数与累计失败数跨轮次保留，本轮失败数按轮重置
                        if (self.autoMode === 'running') {
                            self.stats.completed = prevCompleted;
                            self.stats.failedTotal = prevFailedTotal;
                        }

                        if (typeof callback === 'function') {
                            callback(response);
                        }
                    }
                }
            });
        },

        /**
         * 自动扫描并处理（每轮入口）
         */
        autoScanAndProcess: function () {
            const self = this;

            if (self.autoMode !== 'running') return;

            self.autoRound++;
            self.failedIds = []; // 每轮清空失败集合

            $('#w2p-output-content').append(
                '<div style="color: #3b82f6;">🔄 第 ' + self.autoRound + ' 轮：扫描待处理图片…</div>'
            );

            self.fetchPendingAttachments(function (response) {
                if (!response.success) {
                    self.autoStop('扫描请求失败');
                    return;
                }

                // 记录本轮开始时的已完成数（completed 跨轮次累计）
                self.roundStartedCompleted = self.stats.completed;

                // 防死循环「假进展」检测：本轮队列与上轮完全一致 → 无任何附件离开待处理集合。
                // 覆盖「转换成功但 offload meta 未写」场景（completed 每轮 +1 却始终 pending，
                // 单靠 completed 增量无法识别无进展）
                self.queueUnchanged = (self.lastRoundQueueIds.length > 0 &&
                    self.lastRoundQueueIds.length === self.queue.length &&
                    self.queue.every(a => self.lastRoundQueueIds.indexOf(a.id) !== -1));
                self.lastRoundQueueIds = self.queue.map(a => a.id);

                if (self.queue.length === 0) {
                    self.autoComplete();
                    return;
                }

                self.displayQueue();

                // 按 batchSize 切分批次
                self.batches = [];
                for (let i = 0; i < self.queue.length; i += self.batchSize) {
                    self.batches.push(self.queue.slice(i, i + self.batchSize));
                }
                self.currentBatchIndex = 0;

                $('#w2p-output-content').append(
                    '<div style="color: #10b981;">✓ 第 ' + self.autoRound + ' 轮发现 ' + self.queue.length + ' 张待处理图片，共 ' + self.batches.length + ' 批</div>'
                );

                self.processNextAutoBatch();
            });
        },

        /**
         * 自动模式：处理下一批次（逻辑与手动 processNextBatch 平行）
         */
        processNextAutoBatch: function () {
            const self = this;

            if (self.autoMode !== 'running') return;

            if (self.stopRequested) {
                self.autoStop('用户停止');
                return;
            }

            if (self.pauseRequested) {
                self.enterPaused();
                return;
            }

            if (self.currentBatchIndex >= self.batches.length) {
                self.onAutoRoundFinished();
                return;
            }

            const batch = self.batches[self.currentBatchIndex];
            const batchIds = batch.map(item => item.id);
            const startIndex = self.currentBatchIndex * self.batchSize;

            $('#w2p-output-content').append(
                '<div style="color: #3b82f6;">⚙️ 第 ' + self.autoRound + ' 轮 · 批次 ' + (self.currentBatchIndex + 1) + '/' + self.batches.length + '（' + batchIds.length + ' 张）</div>'
            );

            // 更新当前批次行状态为 PROCESSING
            batch.forEach((item, idx) => {
                const $row = $('#attachment-row-' + (startIndex + idx));
                self.stats.pending--;
                self.stats.processing++;
                self.updateRowStatus($row, 'PROCESSING', 'Batch processing...');
            });
            self.updateQueueStats();

            // 调用批次处理 API
            $.ajax({
                url: w2pMediaEngine.ajax_url,
                type: 'POST',
                data: {
                    action: 'w2p_process_batch',
                    nonce: w2pMediaEngine.nonce,
                    attachment_ids: batchIds
                },
                success: function (response) {
                    if (response.success) {
                        const stats = response.data.stats;

                        // 逐项更新状态并收集失败 id
                        batch.forEach((item, idx) => {
                            const $row = $('#attachment-row-' + (startIndex + idx));
                            const convertResult = stats.convert[item.id];

                            self.stats.processing--;

                            if (convertResult && convertResult.success) {
                                self.stats.completed++;
                                self.updateRowStatus($row, 'DONE', 'Completed');
                            } else {
                                self.stats.failed++;
                                self.stats.failedTotal++;
                                self.failedIds.push(item.id);
                                self.updateRowStatus($row, 'FAILED', convertResult?.error || 'Unknown error');
                            }
                        });
                    } else {
                        // 整批失败
                        batch.forEach((item, idx) => {
                            const $row = $('#attachment-row-' + (startIndex + idx));
                            self.stats.processing--;
                            self.stats.failed++;
                            self.stats.failedTotal++;
                            self.failedIds.push(item.id);
                            self.updateRowStatus($row, 'FAILED', 'Batch failed');
                        });
                    }
                    self.updateQueueStats();
                    self.afterAutoBatch();
                },
                error: function () {
                    // 请求失败
                    batch.forEach((item, idx) => {
                        const $row = $('#attachment-row-' + (startIndex + idx));
                        self.stats.processing--;
                        self.stats.failed++;
                        self.stats.failedTotal++;
                        self.failedIds.push(item.id);
                        self.updateRowStatus($row, 'FAILED', 'Request failed');
                    });
                    self.updateQueueStats();
                    self.afterAutoBatch();
                }
            });
        },

        /**
         * 批次回调末尾公共逻辑（success / error 共用）
         * 替代手动流程中 currentBatchIndex++ 后直接继续的逻辑
         */
        afterAutoBatch: function () {
            const self = this;

            self.currentBatchIndex++;
            self.updateQueueStats();

            if (self.autoMode !== 'running') return;      // 已被停止/完成

            if (self.stopRequested) {
                self.autoStop('用户停止');
                return;
            }

            if (self.pauseRequested) {
                self.enterPaused();                        // 当前批次跑完 → 暂停
                return;
            }

            if (self.currentBatchIndex < self.batches.length) {
                setTimeout(() => self.processNextAutoBatch(), 500);  // 下一批
            } else {
                self.onAutoRoundFinished();                // 本轮完成
            }
        },

        /**
         * 一轮所有批次处理完成后的收尾逻辑（无进展检测 / 暂停 / 下一轮）
         */
        onAutoRoundFinished: function () {
            const self = this;

            if (self.autoMode !== 'running') return;

            // 本轮完成的增量（completed 跨轮次累计，roundStartedCompleted 为本轮起点）
            const roundCompleted = self.stats.completed - self.roundStartedCompleted;

            // 无进展检测（任一条件成立即累计）：
            // 1) 本轮无任何完成且存在失败（原始判定）
            // 2) 本轮失败集合与上一轮完全一致（新附件不断成功、旧附件持续失败时，completed 增量 >0
            //    会掩盖无进展，用失败集合不变来识别）
            // 3) 本轮队列与上轮完全一致（「假进展」漏洞：转换成功但 offload meta 未写时，
            //    completed 每轮 +1 却始终 pending，失败集合为空，需用队列未缩小来识别）
            const noProgressByCompletion = (roundCompleted === 0 && self.failedIds.length > 0);
            const noProgressBySameFailures = (self.failedIds.length > 0 &&
                self.lastRoundFailedIds.length === self.failedIds.length &&
                self.failedIds.every(id => self.lastRoundFailedIds.indexOf(id) !== -1));
            const noProgressByQueueUnchanged = self.queueUnchanged;

            if (noProgressByCompletion || noProgressBySameFailures || noProgressByQueueUnchanged) {
                self.roundNoProgress++;
            } else {
                self.roundNoProgress = 0;
            }
            self.lastRoundFailedIds = self.failedIds.slice();

            if (self.roundNoProgress >= self.maxNoProgressRounds) {
                self.autoStop('连续 ' + self.maxNoProgressRounds + ' 轮无进展，请检查错误日志');
                return;
            }

            if (self.stopRequested) {
                self.autoStop('用户停止');
                return;
            }

            if (self.pauseRequested) {
                self.enterPaused();
                return;
            }

            setTimeout(() => self.autoScanAndProcess(), 500);
        },

        /**
         * 请求暂停（不打断当前 AJAX，当前批次完成后生效）
         */
        pauseAutoProcessing: function () {
            const self = this;

            if (self.autoMode !== 'running' || self.pauseRequested) return;

            self.pauseRequested = true;

            $('#w2p-output-content').append(
                '<div style="color: #f59e0b;">⏸ 已请求暂停，将在当前批次完成后暂停…</div>'
            );

            self.setPauseButtonLabel('resume');

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast('已请求暂停，将在当前批次完成后暂停', 'warning');
            }
        },

        /**
         * 进入暂停状态
         */
        enterPaused: function () {
            const self = this;

            self.autoMode = 'paused';
            self.processing = false;
            self.setAutoUI('paused');

            $('#w2p-output-content').append(
                '<div style="color: #f59e0b;">⏸ 已暂停。点击[恢复]继续。已完成 ' + self.stats.completed + ' | 失败 ' + self.stats.failedTotal + '</div>'
            );
        },

        /**
         * 恢复处理（接着执行下一批次 / 下一轮）
         */
        resumeAutoProcessing: function () {
            const self = this;

            if (self.autoMode !== 'paused') return;

            self.autoMode = 'running';
            self.pauseRequested = false;
            self.processing = true;
            self.setAutoUI('running');

            $('#w2p-output-content').append(
                '<div style="color: #10b981;">▶ 继续处理…</div>'
            );

            if (self.currentBatchIndex < self.batches.length) {
                setTimeout(() => self.processNextAutoBatch(), 300);
            } else {
                setTimeout(() => self.autoScanAndProcess(), 300);
            }
        },

        /**
         * 自动处理完成
         */
        autoComplete: function () {
            const self = this;

            self.autoMode = 'completed';
            self.processing = false;
            self.setAutoUI('idle');

            $('#w2p-output-content').append(
                '<div style="color: #10b981;">✓ 自动处理完成！已完成 ' + self.stats.completed + ' | 失败 ' + self.stats.failedTotal + '</div>'
            );

            if (self.stats.failedTotal > 0) {
                $('#w2p-output-content').append(
                    '<div style="color: #ef4444;">⚠️ 有 ' + self.stats.failedTotal + ' 个文件失败，请检查日志</div>'
                );
            }

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast('自动处理完成！已完成 ' + self.stats.completed + ' | 失败 ' + self.stats.failedTotal, self.stats.failedTotal > 0 ? 'warning' : 'success');
            }
        },

        /**
         * 停止自动处理
         */
        autoStop: function (reason) {
            const self = this;

            self.autoMode = 'stopped';
            self.processing = false;
            self.pauseRequested = false;
            self.stopRequested = false;
            self.setAutoUI('idle');

            $('#w2p-output-content').append(
                '<div style="color: #ef4444;">⛔ 自动处理已停止：' + reason + '。已完成 ' + self.stats.completed + ' | 失败 ' + self.stats.failedTotal + '</div>'
            );

            if (typeof w2p !== 'undefined' && w2p.toast) {
                w2p.toast('自动处理已停止：' + reason, 'warning');
            }
        },

        /**
         * 统一按钮状态（按状态矩阵控制 5 个按钮的显隐/禁用/标签）
         */
        setAutoUI: function (mode) {
            const self = this;
            const $startAuto = $('#w2p-start-auto');
            const $pauseAuto = $('#w2p-pause-auto');
            const $stop = $('#w2p-stop-conversion');
            const $start = $('#w2p-start-conversion');
            const $getStats = $('#w2p-get-stats');

            const isActive = (mode === 'running' || mode === 'paused');

            // #w2p-start-auto：running/paused 置灰并显示「正在处理中…」，其余可用「全自动处理」
            if (isActive) {
                $startAuto.prop('disabled', true);
                self.setStartAutoLabel('running');
            } else {
                $startAuto.prop('disabled', false);
                self.setStartAutoLabel('idle');
            }
            $startAuto.show();

            // #w2p-pause-auto：running/paused 显示（暂停/恢复），其余隐藏
            if (isActive) {
                $pauseAuto.removeClass('w2p-hidden').show();
                self.setPauseButtonLabel(mode === 'paused' ? 'resume' : 'pause');
            } else {
                $pauseAuto.addClass('w2p-hidden').hide();
            }

            // #w2p-stop-conversion：running/paused 显示（复用为自动模式停止按钮），其余隐藏
            if (isActive) {
                $stop.removeClass('w2p-hidden').show();
            } else {
                $stop.addClass('w2p-hidden').hide();
            }

            // #w2p-start-conversion：running/paused 隐藏，其余显示
            if (isActive) {
                $start.hide();
            } else {
                $start.show();
            }

            // #w2p-get-stats：running/paused 禁用
            $getStats.prop('disabled', isActive);
        },

        /**
         * 设置「全自动处理」按钮文字（保留 <i> 图标结构，仅重设文字节点）
         */
        setStartAutoLabel: function (mode) {
            const $btn = $('#w2p-start-auto');
            const label = (mode === 'running' || mode === 'paused') ? '正在处理中…' : '全自动处理';
            const $icon = $btn.find('i');
            $btn.empty().append($icon).append(document.createTextNode(' ' + label));
        },

        /**
         * 设置「暂停/恢复」按钮图标与文字（fa-pause↔fa-play，暂停↔恢复）
         */
        setPauseButtonLabel: function (state) {
            const $btn = $('#w2p-pause-auto');
            const isResume = (state === 'resume');
            const $icon = $btn.find('i');
            $icon.removeClass().addClass('fa-solid ' + (isResume ? 'fa-play' : 'fa-pause'));
            $btn.empty().append($icon).append(document.createTextNode(' ' + (isResume ? '恢复' : '暂停')));
        }
    };

    // Initialize
    $(document).ready(function () {
        if (typeof w2pMediaEngine !== 'undefined') {
            MediaProcessingUI.init();
        } else {
            console.error('w2pMediaEngine is undefined');
        }
    });

})(jQuery);
