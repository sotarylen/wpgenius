        <!-- Tab 3: Fix Chapter Index -->
        <div id="w2p-tab-fix-index" class="w2p-sub-tab-content">
            <?php
            // Load settings
            $fix_settings = get_option('w2p_fix_chapter_index_settings', [
                'target_post_type' => 'chapter',
                'index_format' => '01-00001',
                'index_connector' => '-',
                'auto_volume' => true,
                'batch_size' => 50
            ]);
            
            // Count posts - use chapter by default
            $target_post_type = isset($fix_settings['target_post_type']) ? $fix_settings['target_post_type'] : 'chapter';
            $count_posts = wp_count_posts($target_post_type);
            $total_count = 0;
            if ($count_posts) {
                $total_count = $count_posts->publish + $count_posts->draft + $count_posts->pending + $count_posts->private + $count_posts->future;
            }
            ?>
            
            <script>
            window.fixIndexSettings = <?php echo json_encode($fix_settings); ?>;
            </script>
            
            <div class="w2p-section-header">
                <h4><?php _e('Fix Chapter Index', 'wp-genius'); ?></h4>
                <p class="w2p-tab-content-description">
                    <?php _e('Automatically identify and assign chapter index and volume name. Total chapters: ' . number_format($total_count), 'wp-genius'); ?>
                </p>
            </div>

            <div class="w2p-fix-index-split">
                <!-- Left: Config -->
                <div class="w2p-fix-index-config">
                    <div class="w2p-section">
                        <div class="w2p-section-header">
                            <h4><?php _e('Configuration', 'wp-genius'); ?></h4>
                        </div>
                        <div class="w2p-section-body">
                            <input type="hidden" id="fix_index_nonce" value="<?php echo wp_create_nonce('fix_chapter_index'); ?>">
                            
                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="target_post_type"><?php _e('Target Post Type', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-input">
                                    <select id="target_post_type" class="w2p-input">
                                        <option value="chapter" <?php selected($target_post_type, 'chapter'); ?>>Chapter</option>
                                        <option value="post" <?php selected($target_post_type, 'post'); ?>>Post</option>
                                    </select>
                                </div>
                            </div>

                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="index_format"><?php _e('Index Format', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-input">
                                    <input type="text" id="index_format" class="w2p-input" value="<?php echo esc_attr($fix_settings['index_format']); ?>">
                                </div>
                            </div>

                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="index_connector"><?php _e('Connector', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-input">
                                    <input type="text" id="index_connector" class="w2p-input" value="<?php echo esc_attr($fix_settings['index_connector']); ?>">
                                </div>
                            </div>

                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="auto_volume"><?php _e('Auto Volume', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-input">
                                    <label class="w2p-switch">
                                        <input type="checkbox" id="auto_volume" <?php checked($fix_settings['auto_volume']); ?>>
                                        <span class="w2p-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="batch_size"><?php _e('Batch Size', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-input">
                                    <input type="number" id="batch_size" class="w2p-input" value="<?php echo esc_attr($fix_settings['batch_size']); ?>" min="1" max="100">
                                </div>
                            </div>

                            <div class="w2p-form-row">
                                <button type="button" id="fix-index-save-btn" class="w2p-btn w2p-btn-primary">
                                    <i class="fa-solid fa-save"></i> <?php _e('Save', 'wp-genius'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Process -->
                <div class="w2p-fix-index-logs">
                    <div class="w2p-section-header">
                        <h4><?php _e('Process', 'wp-genius'); ?></h4>
                        <div class="w2p-header-actions">
                            <button type="button" id="fix-index-start-btn" class="w2p-btn w2p-btn-primary">
                                <i class="fa-solid fa-play"></i> <?php _e('Start', 'wp-genius'); ?>
                            </button>
                            <button type="button" id="fix-index-stop-btn" class="w2p-btn w2p-btn-stop" style="display:none;">
                                <i class="fa-solid fa-pause"></i> <?php _e('Stop', 'wp-genius'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="w2p-manual-publish-controls">
                        <div class="w2p-manual-publish-info">
                            <div class="w2p-progress-text">
                                <strong><span id="fix-progress-text">0/<?php echo number_format($total_count); ?></span></strong>
                            </div>
                        </div>
                        <div class="progress-bar-container">
                            <div id="fix-progress-bar" class="progress-bar-inner" style="width:0%;"></div>
                        </div>
                    </div>

                    <div class="w2p-section">
                        <div class="w2p-section-header">
                            <h4><?php _e('Log', 'wp-genius'); ?></h4>
                        </div>
                        <div class="w2p-log-container">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width:120px;">Index</th>
                                        <th style="width:150px;">Volume</th>
                                        <th>Title</th>
                                    </tr>
                                </thead>
                                <tbody id="fix-logs-tbody">
                                    <tr><td colspan="3" style="text-align:center;color:#999;">Ready</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
