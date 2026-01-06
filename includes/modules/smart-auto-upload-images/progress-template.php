<div id="w2p-smart-aui-backdrop" style="display:none;">
    <div id="w2p-smart-aui-progress-container">
        <!-- Header -->
        <div class="w2p-smart-aui-header">
            <h3><?php _e( 'Smart Auto Upload Images', 'wp-genius' ); ?></h3>
            <button id="w2p-smart-aui-close-btn" type="button" class="dashicons dashicons-no-alt"></button>
        </div>

        <!-- Progress Bar -->
        <div class="w2p-smart-aui-progress-bar">
            <div class="w2p-smart-aui-progress-fill" style="width: 0%;"></div>
        </div>

        <!-- Status Text (Moved here) -->
        <div class="w2p-smart-aui-status-bar">
             <span class="w2p-smart-aui-status-text"><?php _e( 'Preparing...', 'wp-genius' ); ?></span>
        </div>

        <!-- Main Content -->
        <div class="w2p-smart-aui-body">
            
            <!-- Overall Stats -->
            <div class="w2p-smart-aui-stats-row">
                <div class="stat-item total">
                    <span class="label"><?php _e( 'Total', 'wp-genius' ); ?></span>
                    <span id="w2p-smart-aui-total" class="value">0</span>
                </div>
                <div class="stat-item success">
                    <span class="label"><?php _e( 'Success', 'wp-genius' ); ?></span>
                    <span id="w2p-smart-aui-success" class="value">0</span>
                </div>
                <div class="stat-item skipped">
                    <span class="label"><?php _e( 'Skipped', 'wp-genius' ); ?></span>
                    <span id="w2p-smart-aui-skipped" class="value">0</span>
                </div>
                <div class="stat-item failed">
                    <span class="label"><?php _e( 'Failed', 'wp-genius' ); ?></span>
                    <span id="w2p-smart-aui-failed" class="value">0</span>
                </div>
                <div class="stat-item threads">
                    <span class="label"><?php _e( 'Threads', 'wp-genius' ); ?></span>
                    <span class="value"><span id="w2p-smart-aui-active-threads">0</span> / <span id="w2p-smart-aui-threads">4</span></span>
                </div>
            </div>

            <!-- Grid Preview Area -->
            <div id="w2p-smart-aui-preview-area" class="w2p-smart-aui-preview-area grid-mode">
                <!-- Grid items will be injected here by JS -->
                <div class="w2p-smart-aui-grid-placeholder">
                    <span class="dashicons dashicons-images-alt2"></span>
                    <p><?php _e( 'Waiting for task to start...', 'wp-genius' ); ?></p>
                </div>
            </div>
            
            <div id="w2p-smart-aui-current-url" style="display:none;"></div> <!-- Hidden debug info -->
        </div>

        <!-- Footer -->
        <div class="w2p-smart-aui-footer">
            <button id="w2p-smart-aui-skip-publish-btn" type="button" class="w2p-btn w2p-btn-primary" style="display:none;"><?php _e( 'Skip and Publish', 'wp-genius' ); ?></button>
            <button id="w2p-smart-aui-cancel-btn" type="button" class="w2p-btn w2p-btn-secondary"><?php _e( 'Cancel Task', 'wp-genius' ); ?></button>
        </div>
    </div>
</div>
