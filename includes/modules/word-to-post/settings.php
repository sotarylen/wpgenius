<?php
if (!defined('ABSPATH')) {
    exit;
}

// Display admin notices
settings_errors('word_to_posts');
?>

<div class="w2p-sub-tabs" id="w2p-word-to-post-sub-tabs">
    <div class="w2p-sub-tab-nav">
        <a href="#w2p-sub-tab-upload" class="w2p-sub-tab-link active" data-tab="upload"><i class="fa-solid fa-file-word"></i> <?php _e('Upload & Import', 'wp-genius'); ?></a>
        <a href="#w2p-sub-tab-maintenance" class="w2p-sub-tab-link" data-tab="maintenance"><i class="fa-solid fa-folder"></i> <?php _e('Directory Maintenance', 'wp-genius'); ?></a>
        <a href="#w2p-sub-tab-fix-index" class="w2p-sub-tab-link" data-tab="fix-index"><i class="fa-solid fa-list-ol"></i> <?php _e('Fix Chapter Index', 'wp-genius'); ?></a>
    </div>

    <!-- Tab 1: Upload & Import -->
    <div id="w2p-tab-upload" class="w2p-sub-tab-content active">
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e('Upload & Import Word Document', 'wp-genius'); ?></h4>
            </div>
            <div class="w2p-section-body">
                <form id="word_to_posts_upload_form" method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="handle_upload">
                    <?php wp_nonce_field('word_to_posts_upload', 'word_to_posts_upload_nonce'); ?>
                    
                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="word_to_posts_category"><?php _e('Category', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <?php wp_dropdown_categories([
                                'name' => 'category', 
                                'hide_empty' => 0, 
                                'id' => 'word_to_posts_category', 
                                'selected' => 396,
                                'class' => 'w2p-input-medium'
                            ]); ?>
                        </div>
                    </div>
                    
                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="word_to_posts_author"><?php _e('Author', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <?php wp_dropdown_users([
                                'name' => 'author', 
                                'id' => 'word_to_posts_author', 
                                'selected' => 11,
                                'class' => 'w2p-input-medium'
                            ]); ?>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="word_to_posts_tags"><?php _e('Tags', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <input type="text" name="tags" id="word_to_posts_tags" placeholder="<?php _e('Separate tags with commas', 'wp-genius'); ?>" class="w2p-input-large">
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="word_to_posts_cpt_type"><?php _e('Associate Post Type', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <select name="cpt_type" id="word_to_posts_cpt_type" class="w2p-input-medium">
                                <?php 
                                $post_types = get_post_types(['public' => true], 'objects');
                                foreach ($post_types as $post_type) : 
                                    if ($post_type->name === 'attachment') continue;
                                ?>
                                    <option value="<?php echo esc_attr($post_type->name); ?>"><?php echo esc_html($post_type->label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="word_to_posts_cpt_id"><?php _e('Associate Post ID', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <input type="number" name="cpt_id" id="word_to_posts_cpt_id" placeholder="<?php _e('Enter Post ID', 'wp-genius'); ?>" class="w2p-input-medium" required>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="word_file"><?php _e('Word File (.docx)', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <input type="file" name="word_file" id="word_file" accept=".docx,.doc" class="w2p-input-large">
                            <p class="description"><?php _e('Select a .docx file to convert into posts/chapters.', 'wp-genius'); ?></p>
                        </div>
                    </div>

                    <div class="w2p-form-actions w2p-settings-actions">
                        <button type="submit" class="w2p-btn w2p-btn-primary"><i class="fa-solid fa-file-word"></i> <?php _e('Upload and Begin Import', 'wp-genius'); ?></button>
                    </div>
                </form>
                <div id="word-to-posts-log-upload" class="word2postNotice w2p-info-box" style="margin-top: 15px; display: none;"></div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Directory Maintenance -->
    <div id="w2p-tab-maintenance" class="w2p-sub-tab-content">
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e('Directory Maintenance', 'wp-genius'); ?></h4>
            </div>
            <div class="w2p-section-body">
                <p class="w2p-tab-content-description" style="margin-bottom: var(--w2p-spacing-lg);">
                    <?php _e('Manage temporary files and directories used during the import process.', 'wp-genius'); ?>
                </p>
                
                <div class="w2p-grid w2p-grid-cols-2 w2p-gap-lg">
                    <div class="w2p-toggle-card">
                        <div class="w2p-toggle-header">
                            <span class="w2p-toggle-title"><?php _e('Scan Directory', 'wp-genius'); ?></span>
                        </div>
                        <p class="w2p-toggle-desc"><?php _e('Search the uploads directory for Word documents and synchronize the database records.', 'wp-genius'); ?></p>
                        <div class="w2p-form-actions" style="margin-top: var(--w2p-spacing-md); padding: 0; border: none;">
                            <form id="word_to_posts_scan_form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <input type="hidden" name="action" value="scan_uploads">
                                <?php wp_nonce_field('word_to_posts_scan', 'word_to_posts_scan_nonce'); ?>
                                <button type="submit" class="button button-secondary w2p-btn-full"><?php _e('Scan Upload Directory', 'wp-genius'); ?></button>
                            </form>
                        </div>
                    </div>

                    <div class="w2p-toggle-card">
                        <div class="w2p-toggle-header">
                            <span class="w2p-toggle-title"><?php _e('Clean Directory', 'wp-genius'); ?></span>
                        </div>
                        <p class="w2p-toggle-desc"><?php _e('Safely remove temporary uploaded files that are no longer associated with any posts or records.', 'wp-genius'); ?></p>
                        <div class="w2p-form-actions" style="margin-top: var(--w2p-spacing-md); padding: 0; border: none;">
                            <form id="word_to_posts_clean_form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <input type="hidden" name="action" value="clean_uploads">
                                <?php wp_nonce_field('word_to_posts_clean', 'word_to_posts_clean_nonce'); ?>
                                <button type="submit" class="button button-secondary w2p-btn-full" style="color: var(--w2p-color-danger); border-color: var(--w2p-color-danger);" onclick="return confirm('<?php _e('Are you sure you want to clean the uploads directory? All temporary files will be deleted.', 'wp-genius'); ?>');">
                                    <?php _e('Clean Temporary Files', 'wp-genius'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="word-to-posts-log-clean" class="word2postNotice w2p-info-box" style="margin-top: 15px; display: none;"></div>
            </div>
        </div>
    </div>

    <!-- Tab 3: Fix Chapter Index -->
    <div id="w2p-tab-fix-index" class="w2p-sub-tab-content">
        <div class="w2p-section-header">
            <h4><?php _e('Fix Chapter Index', 'wp-genius'); ?></h4>
            <p class="w2p-tab-content-description">
                <?php _e('Automatically scan and inference chapter index and volume name. Suitable for large datasets using batch processing.', 'wp-genius'); ?>
            </p>
        </div>

        <div class="w2p-fix-index-split">
            <!-- Left Column: Configuration -->
            <div class="w2p-fix-index-config">
                <div class="w2p-section">
                    <div class="w2p-section-header">
                        <h4><?php _e('Configuration', 'wp-genius'); ?></h4>
                    </div>
                    <div class="w2p-section-body">
                        <?php 
                            $fix_settings = get_option('w2p_fix_chapter_index_settings', [
                                    'target_post_type' => 'chapter',
                                    'scan_mode' => 'all',
                                    'novel_id' => '',
                                    'scan_limit' => 5,
                                    'index_format' => '01-00001',
                                    'index_connector' => '-',
                                    'auto_volume' => true,
                                    'batch_size' => 20
                                ]);
                        ?>
                        <input type="hidden" id="fix_index_nonce" value="<?php echo wp_create_nonce('fix_chapter_index'); ?>">
                            
                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="target_post_type"><?php _e('Target Post Type', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-control">
                                    <select id="target_post_type" class="w2p-input-full">
                                        <?php 
                                        $post_types = get_post_types(['public' => true], 'objects');
                                        foreach ($post_types as $pt) {
                                            if ($pt->name === 'attachment') continue;
                                            $selected = ($pt->name === $fix_settings['target_post_type']) ? 'selected' : '';
                                            echo sprintf('<option value="%s" %s>%s (%s)</option>', esc_attr($pt->name), $selected, esc_html($pt->label), esc_html($pt->name));
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="scan_mode"><?php _e('Scan Mode', 'wp-genius'); ?></label>
                                    <p class="description"><?php _e('Choose how to scan chapters', 'wp-genius'); ?></p>
                                </div>
                                <div class="w2p-form-control">
                                    <select id="scan_mode" class="w2p-input-full">
                                        <option value="all" <?php selected($fix_settings['scan_mode'], 'all'); ?>><?php _e('All Chapters', 'wp-genius'); ?></option>
                                        <option value="by_novel" <?php selected($fix_settings['scan_mode'], 'by_novel'); ?>><?php _e('By Novel', 'wp-genius'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="w2p-form-row" id="novel_id_row" style="display:<?php echo ($fix_settings['scan_mode'] === 'by_novel') ? 'flex' : 'none'; ?>;">
                                <div class="w2p-form-label">
                                    <label for="novel_id"><?php _e('Novel ID', 'wp-genius'); ?></label>
                                    <p class="description"><?php _e('Leave empty to scan all novels', 'wp-genius'); ?></p>
                                </div>
                                <div class="w2p-form-control">
                                    <input type="text" id="novel_id" class="w2p-input-full" value="<?php echo esc_attr($fix_settings['novel_id']); ?>" placeholder="<?php esc_attr_e('Optional: Enter specific novel ID', 'wp-genius'); ?>">
                                </div>
                            </div>

                            <div class="w2p-form-row" id="scan_limit_row" style="display:<?php echo ($fix_settings['scan_mode'] === 'by_novel') ? 'flex' : 'none'; ?>;">
                                <div class="w2p-form-label">
                                    <label for="scan_limit"><?php _e('Scan Limit', 'wp-genius'); ?></label>
                                    <p class="description"><?php _e('Number of recent novels to scan', 'wp-genius'); ?></p>
                                </div>
                                <div class="w2p-form-control">
                                    <input type="number" id="scan_limit" class="w2p-input-small" value="<?php echo esc_attr($fix_settings['scan_limit']); ?>" min="1" max="100">
                                </div>
                            </div>

                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="index_format"><?php _e('Index Format', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-control">
                                    <input type="text" id="index_format" value="<?php echo esc_attr($fix_settings['index_format']); ?>" class="w2p-input-full" placeholder="01-00001">
                                    <p class="description"><?php _e('"01" = Vol Pad 2, "00001" = Chap Pad 5.', 'wp-genius'); ?></p>
                                </div>
                            </div>

                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="w2p_index_connector"><?php _e('Connector', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-control">
                                    <input type="text" id="index_connector" value="<?php echo esc_attr($fix_settings['index_connector']); ?>" class="w2p-input-small">
                                </div>
                            </div>

                            <div class="w2p-form-row">
                                <div class="w2p-form-label">
                                    <label for="auto_volume"><?php _e('Auto Identify Volume', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-control">
                                    <label for="auto_volume" class="w2p-switch">
                                        <input type="checkbox" id="auto_volume" <?php checked($fix_settings['auto_volume']); ?>>
                                        <span class="w2p-slider"></span>
                                    </label>
                                    <p class="description"><?php _e('Auto identify volume from the content.', 'wp-genius'); ?></p>
                                </div>
                            </div>

                            <div class="w2p-form-row border-none">
                                <div class="w2p-form-label">
                                    <label for="w2p_batch_size"><?php _e('Batch Size', 'wp-genius'); ?></label>
                                </div>
                                <div class="w2p-form-control">
                                    <input type="number" id="batch_size" value="<?php echo esc_attr($fix_settings['batch_size']); ?>" min="1" max="100" class="w2p-input-small">
                                    <p class="description"><?php _e('Items per request.', 'wp-genius'); ?></p>
                                </div>
                            </div>



                            <div class="w2p-settings-actions">
                                <button type="button" id="fix-index-save-btn" class="w2p-btn w2p-btn-primary">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    <?php _e('Save Configuration', 'wp-genius'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Right Column: Status & Log -->
            <div class="w2p-fix-index-logs">
                <!-- Manual Processing -->
                <div class="w2p-section-header">
                    <h4><?php _e('Manual Batch Process', 'wp-genius'); ?></h4>
                    <div class="w2p-header-actions">
                        <button type="button" id="fix-index-scan-btn" class="w2p-btn w2p-btn-primary">
                            <i class="fa-solid fa-search"></i>
                            <?php _e('Scan', 'wp-genius'); ?>
                        </button>
                        <button type="button" id="fix-index-execute-btn" class="w2p-btn w2p-btn-success" style="display:none;">
                            <i class="fa-solid fa-check"></i>
                            <?php _e('Update', 'wp-genius'); ?>
                        </button>
                        <button type="button" id="fix-index-auto-btn" class="w2p-btn w2p-btn-primary">
                            <i class="fa-solid fa-bolt"></i>
                            <?php _e('Auto Update', 'wp-genius'); ?>
                        </button>
                        <button type="button" id="fix-index-reset-btn" class="w2p-btn w2p-btn-secondary" style="display:none;">
                            <i class="fa-solid fa-rotate-left"></i>
                            <?php _e('Reset', 'wp-genius'); ?>
                        </button>
                        <button type="button" id="fix-index-stop-btn" class="w2p-btn w2p-btn-stop" style="display:none;">
                            <i class="fa-solid fa-pause"></i>
                            <?php _e('Stop', 'wp-genius'); ?>
                        </button>
                    </div>
                </div>

                <div class="w2p-manual-publish-controls">
                    <?php
                        // CRITICAL: Always count chapter posts, not draft posts
                        $count_posts = wp_count_posts('chapter');
                        $total_count = 0;
                        if ($count_posts) {
                            $total_count = $count_posts->publish + $count_posts->draft + $count_posts->pending + $count_posts->private + $count_posts->future;
                        }
                        
                        // Debug info
                        echo '<!-- Chapter Count Debug: publish=' . $count_posts->publish . ' draft=' . $count_posts->draft . ' pending=' . $count_posts->pending . ' private=' . $count_posts->private . ' future=' . $count_posts->future . ' total=' . $total_count . ' -->';
                    ?>
                    <div class="w2p-manual-publish-info">
                        <div class="fix-index-count">
                            <strong><span id="fix-progress-text">0/<?php echo number_format($total_count); ?></span></strong>
                        </div>
                        <div class="fix-index-status"><?php _e('Ready to scan', 'wp-genius'); ?></div>
                    </div>

                    <div id="w2p-fix-progress" style="display:block; margin-top:15px;">
                        <div class="progress-bar-container">
                            <div class="progress-bar-inner" id="fix-progress-bar" style="width: 0%;"></div>
                        </div>
                        <?php 
                        $finished_books = get_option('w2p_fix_index_finished_books', []);
                        $finished_count = count($finished_books);
                        ?>
                        <div id="finished-progress-row" style="<?php echo ($finished_count > 0) ? '' : 'display:none;'; ?>; margin-top: 10px; font-size: 13px; color: #666;">
                            <span id="finished-count-text"><?php echo sprintf(__('Processed Novels: %d', 'wp-genius'), $finished_count); ?></span>
                            <span style="margin: 0 8px;">|</span>
                            <a href="#" id="fix-index-clear-progress" style="color: #2271b1; text-decoration: none;"><?php _e('Clear', 'wp-genius'); ?></a>
                        </div>
                    </div>
                </div>

                <!-- Log Section -->
                <div class="w2p-section-header w2p-section-spacing">
                    <h4><?php _e('Process Log', 'wp-genius'); ?></h4>
                </div>

                <div class="w2p-log-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="15%"><?php _e('Index', 'wp-genius'); ?></th>
                                <th width="25%"><?php _e('Volume', 'wp-genius'); ?></th>
                                <th><?php _e('Title', 'wp-genius'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="fix-logs-tbody">
                            <tr><td colspan="3"><?php _e('No activity logged yet.', 'wp-genius'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>