<?php
if (!defined('ABSPATH')) {
    exit;
}

// Display admin notices
settings_errors('word_to_posts');
?>

<div class="w2p-sub-tabs" id="w2p-word-publish-sub-tabs">
    <div class="w2p-sub-tab-nav">
        <a href="#w2p-sub-tab-upload" class="w2p-sub-tab-link active" data-tab="upload"><?php _e('Upload & Import', 'wp-genius'); ?></a>
        <a href="#w2p-sub-tab-maintenance" class="w2p-sub-tab-link" data-tab="maintenance"><?php _e('Directory Maintenance', 'wp-genius'); ?></a>
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
                            <label for="word_to_posts_custom_taxonomy"><?php _e('Book Category', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <?php wp_dropdown_categories([
                                'taxonomy' => 'book',
                                'name' => 'custom_taxonomy',
                                'hide_empty' => 0,
                                'id' => 'word_to_posts_custom_taxonomy',
                                'show_option_none' => __('Select a book...', 'wp-genius'),
                                'class' => 'w2p-input-medium'
                            ]); ?>
                        </div>
                    </div>

                    <div class="w2p-form-row">
                        <div class="w2p-form-label">
                            <label for="word_to_posts_new_custom_taxonomy"><?php _e('Or Add New Book', 'wp-genius'); ?></label>
                        </div>
                        <div class="w2p-form-control">
                            <input type="text" name="new_custom_taxonomy" id="word_to_posts_new_custom_taxonomy" placeholder="<?php _e('New book name', 'wp-genius'); ?>" class="w2p-input-medium">
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

                    <div class="w2p-form-actions">
                        <button type="submit" class="button button-primary"><?php _e('Upload and Begin Import', 'wp-genius'); ?></button>
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
</div>
