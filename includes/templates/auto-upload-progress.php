<?php
/**
 * Auto Upload Images Progress Template
 * Template file for the auto upload images progress interface
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Auto Upload Images Progress Interface Template -->
<div id="w2p-aui-backdrop"></div>
<div id="w2p-aui-progress-container">
    <div class="w2p-aui-header">
        <h3 class="w2p-aui-title" data-i18n="title">🚀 Auto Upload Images</h3>
        <div class="w2p-aui-header-buttons">
            <button id="w2p-aui-stop-btn" class="w2p-aui-stop-btn" style="display:none;" data-i18n="stop"> Stop</button>
            <button id="w2p-aui-minimize-btn" class="w2p-aui-minimize-btn" data-i18n="minimize">−</button>
            <button id="w2p-aui-close-btn" class="w2p-aui-close-btn" data-i18n="close">×</button>
        </div>
    </div>
    
    <div class="w2p-aui-threads-info">
        <span id="w2p-aui-threads-active">0</span> <span data-i18n="active_threads">active threads</span> |
        <span data-i18n="total_images">Total</span> <span id="w2p-aui-total-images">0</span> <span data-i18n="images">images</span>
    </div>
    
    <div id="w2p-aui-batch-preview" class="w2p-aui-batch-preview"></div>
    
    <div id="w2p-aui-batch-item-template" class="w2p-aui-batch-item">
        <img class="w2p-aui-batch-image" src="">
        <div class="w2p-aui-batch-status"></div>
    </div>
    
    <div class="w2p-aui-progress-bar">
        <div id="w2p-aui-progress-fill" class="w2p-aui-progress-fill"></div>
    </div>
    
    <div id="w2p-aui-status" class="w2p-aui-status" data-i18n="preparing">Preparing to process images...</div>
    <div id="w2p-aui-current-image" class="w2p-aui-current-image"></div>
    
    <div id="w2p-aui-results" class="w2p-aui-results" style="display:none;">
        <div class="w2p-aui-result-summary">
            <div class="w2p-aui-result-item w2p-aui-success-text">
                ✅ <span data-i18n="success">Success</span>: <span id="w2p-aui-success-count">0</span> <span data-i18n="images">Images</span>
            </div>
            <div class="w2p-aui-result-item w2p-aui-error-text">
                ❌ <span data-i18n="failed">Failed</span>: <span id="w2p-aui-fail-count">0</span> <span data-i18n="images">Images</span>
            </div>
            <div class="w2p-aui-result-item" id="w2p-aui-process-info">
                <span id="w2p-aui-process-time"></span>
                <span id="w2p-aui-retry-info"></span>
            </div>
        </div>
    </div>
</div>
