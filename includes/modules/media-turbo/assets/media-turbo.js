jQuery(document).ready(function ($) {
    let allIds = [];
    let previewItems = [];
    let currentIndex = 0;
    let isRunning = false;
    let stopRequested = false;
    let currentXHR = null;
    let stats = { success: 0, skipped: 0, error: 0, affected: 0 };

    // Scan Media Library
    $('#w2p-scan-media').on('click', function () {
        console.log('WP Genius: Scan button clicked');
        const $btn = $(this);
        // Read Scan Limit from the input field
        const scanLimit = parseInt($('#w2p-scan-limit').val()) || 100;

        $btn.prop('disabled', true).text('Scanning...');

        if (typeof w2pMediaTurbo === 'undefined') {
            alert('Initialization error: w2pMediaTurbo is missing.');
            $btn.prop('disabled', false).text('Scan Media Library');
            return;
        }

        $.post(w2pMediaTurbo.ajax_url, {
            action: 'w2p_media_turbo_get_stats',
            nonce: w2pMediaTurbo.nonce,
            limit: scanLimit
        }, function (response) {
            if (response.success) {
                allIds = response.data.allIds || [];
                previewItems = response.data.preview || [];

                renderScanResults();

                let statusText = 'Loaded ' + allIds.length + ' images for conversion (as set in limit).';
                if (allIds.length > previewItems.length) {
                    statusText += ' (Showing first ' + previewItems.length + ')';
                }
                $('#w2p-bulk-status-detailed').text(statusText);

                if (allIds.length > 0) {
                    $('#w2p-start-bulk').fadeIn();
                    $('#w2p-scan-results-wrapper').fadeIn();
                } else {
                    alert('No images found that need conversion.');
                }
            } else {
                alert('Scan failed: ' + (response.data || 'Unknown error'));
            }
            $btn.prop('disabled', false).text('Scan Media Library');
        }).fail(function () {
            alert('Network error or server-side failure during scan.');
            $btn.prop('disabled', false).text('Scan Media Library');
        });
    });

    function renderScanResults() {
        const $container = $('#w2p-scan-items');
        $container.empty();

        previewItems.forEach((item) => {
            $container.append(createRowHtml(item));
        });
    }

    function createRowHtml(item, statusText = 'Pending', statusClass = 'pending') {
        const thumb = item.thumbUrl ? `<img src="${item.thumbUrl}" class="w2p-item-thumb" />` : '<div class="w2p-item-thumb" style="display:flex;align-items:center;justify-content:center;background:#eee;color:#999;font-size:10px;">No Img</div>';
        const fileName = item.fileName || 'ID: ' + item.id;
        const association = item.parentUrl ? `<small>Post: <a href="${item.parentUrl}" target="_blank">${item.parentTitle}</a></small>` : '<small>Orphaned image</small>';

        return `
            <tr id="w2p-item-${item.id}">
                <td>${thumb}</td>
                <td>
                    <div class="w2p-item-info">
                        <strong>${fileName}</strong>
                        ${association}
                    </div>
                </td>
                <td class="w2p-item-status">
                    <span class="w2p-status-badge w2p-status-${statusClass}">${statusText}</span>
                </td>
            </tr>
        `;
    }

    // Start Bulk Conversion
    $('#w2p-start-bulk').on('click', function () {
        if (isRunning) return;

        isRunning = true;
        stopRequested = false;

        $(this).prop('disabled', true).hide();
        $('#w2p-stop-bulk').fadeIn().prop('disabled', false).text('Stop');
        $('#w2p-scan-media').prop('disabled', true);
        $('#w2p-bulk-progress-wrapper').fadeIn();

        currentIndex = 0;
        stats = { success: 0, skipped: 0, error: 0, affected: 0 };
        processNextBatch();
    });

    // Stop Processing
    $('#w2p-stop-bulk').on('click', function () {
        if (!isRunning) return;
        stopRequested = true;
        $(this).prop('disabled', true).text('Stopping...');
        if (currentXHR) {
            currentXHR.abort();
        }
        // Force finish if not responded in 2 seconds
        setTimeout(() => {
            if (isRunning) finishConversion('Stopped by user (Forced).');
        }, 2000);
    });

    function processNextBatch() {
        if (stopRequested) {
            finishConversion('Stopped by user.');
            return;
        }

        if (currentIndex >= allIds.length) {
            finishConversion('Bulk Optimization Complete!');
            return;
        }

        // Read Batch Size from the input field
        const batchSize = parseInt($('#w2p-batch-size').val()) || 10;
        const chunk = allIds.slice(currentIndex, currentIndex + batchSize);

        // Indicate processing for the whole chunk
        $('#w2p-bulk-status-detailed').text(`Processing batch ${Math.floor(currentIndex / batchSize) + 1}... (${currentIndex + 1} to ${Math.min(currentIndex + batchSize, allIds.length)})`);

        chunk.forEach((id, index) => {
            let $row = $('#w2p-item-' + id);
            if ($row.length === 0) {
                const newItem = { id: id, fileName: 'Image ' + id };
                $('#w2p-scan-items').prepend(createRowHtml(newItem, 'Processing...', 'processing'));
                $row = $('#w2p-item-' + id);
            } else {
                $row.find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-processing">Processing...</span>');
            }
            if (index === 0) {
                $row[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });

        currentXHR = $.post(w2pMediaTurbo.ajax_url, {
            action: 'w2p_media_turbo_batch_convert',
            nonce: w2pMediaTurbo.nonce,
            ids: chunk
        }, function (response) {
            currentXHR = null;
            if (response.success && Array.isArray(response.data)) {
                response.data.forEach(res => {
                    const $itemRow = $('#w2p-item-' + res.id);
                    const $itemStatus = $itemRow.find('.w2p-item-status');

                    if (res.status === 'success') {
                        stats.success++;
                        stats.affected += res.affected;
                        $itemStatus.html(`<span class="w2p-status-badge w2p-status-success">Batch OK (${res.affected})</span>`);
                    } else if (res.status === 'skipped') {
                        stats.skipped++;
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-skipped">Skipped</span>');
                    } else {
                        stats.error++;
                        $itemStatus.html('<span class="w2p-status-badge w2p-status-error">Fail</span>');
                    }
                });
            } else {
                stats.error += chunk.length;
                chunk.forEach(id => {
                    $('#w2p-item-' + id).find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-error">Batch Error</span>');
                });
            }

            currentIndex += chunk.length;
            updateOverallProgress();

            if (!stopRequested) {
                setTimeout(processNextBatch, 200);
            } else {
                finishConversion('Stopped by user.');
            }
        }).fail(function (xhr, textStatus) {
            currentXHR = null;
            if (textStatus === 'abort') {
                finishConversion('Stopped by user.');
                return;
            }
            stats.error += chunk.length;
            chunk.forEach(id => {
                $('#w2p-item-' + id).find('.w2p-item-status').html('<span class="w2p-status-badge w2p-status-error">Batch Fail</span>');
            });
            currentIndex += chunk.length;
            updateOverallProgress();
            if (!stopRequested) {
                setTimeout(processNextBatch, 200);
            } else {
                finishConversion('Stopped by user.');
            }
        });
    }

    function updateOverallProgress() {
        const processed = Math.min(currentIndex, allIds.length);
        const progress = (processed / allIds.length) * 100;
        $('#w2p-bulk-progress-bar').css('width', progress + '%');
        $('#w2p-bulk-status-detailed').html(
            `Processed: ${processed} / ${allIds.length} | ` +
            `<span style="color:#10a754;">Success: ${stats.success}</span> | ` +
            `<span style="color:#d94f1a;">Links Updated: ${stats.affected}</span>`
        );
    }

    function finishConversion(message) {
        isRunning = false;
        currentXHR = null;
        $('#w2p-stop-bulk').hide();
        $('#w2p-start-bulk').show().text('Optimize Again').prop('disabled', false);
        $('#w2p-scan-media').prop('disabled', false);
        alert(message);
    }
});
