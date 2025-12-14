<div class="wrap wp-genius-container">
    <h1><?php _e('WP Genius', 'wp-genius'); ?></h1>
    <p class="description"><?php _e('Import Word documents and manage your upload directory.', 'wp-genius'); ?></p>

    <!-- Display admin notices -->
    <?php settings_errors('word_to_posts'); ?>

    <!-- Modern Card Layout -->
    <div class="w2p-features-grid">
        
        <!-- Upload & Import Card -->
        <div class="w2p-feature-card">
            <div class="w2p-feature-header">
                <div class="w2p-feature-info">
                    <h2><?php _e('Upload & Import', 'wp-genius'); ?></h2>
                    <p class="description"><?php _e('Import a Word document file and convert it into a post.', 'wp-genius'); ?></p>
                </div>
            </div>

            <form id="word_to_posts_upload_form" method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>" class="w2p-upload-form">
                <input type="hidden" name="action" value="handle_upload">
                <?php wp_nonce_field('word_to_posts_upload', 'word_to_posts_upload_nonce'); ?>
                
                <div class="w2p-form-grid">
                    <!-- Category Selector -->
                    <div class="w2p-form-group">
                        <label for="word_to_posts_category"><?php _e('Category', 'wp-genius'); ?></label>
                        <?php wp_dropdown_categories(['name' => 'category', 'hide_empty' => 0, 'id' => 'word_to_posts_category', 'selected' => 396]); ?>
                    </div>
                    
                    <!-- Author field -->
                    <div class="w2p-form-group">
                        <label for="word_to_posts_author"><?php _e('Author', 'wp-genius'); ?></label>
                        <?php wp_dropdown_users(['name' => 'author', 'id' => 'word_to_posts_author', 'selected' => 11]); ?>
                    </div>

                    <!-- Tags input -->
                    <div class="w2p-form-group w2p-full-width">
                        <label for="word_to_posts_tags"><?php _e('Tags', 'wp-genius'); ?></label>
                        <input type="text" name="tags" id="word_to_posts_tags" placeholder="<?php _e('Separate tags with commas', 'wp-genius'); ?>">
                    </div>

                    <!-- Custom Taxonomy Selector -->
                    <div class="w2p-form-group">
                        <label for="word_to_posts_custom_taxonomy"><?php _e('Book Category', 'wp-genius'); ?></label>
                        <?php wp_dropdown_categories([
                            'taxonomy' => 'book',
                            'name' => 'custom_taxonomy',
                            'hide_empty' => 0,
                            'id' => 'word_to_posts_custom_taxonomy',
                            'show_option_none' => __('Select a book...', 'wp-genius')
                        ]); ?>
                    </div>

                    <!-- New Taxonomy field -->
                    <div class="w2p-form-group">
                        <label for="word_to_posts_new_custom_taxonomy"><?php _e('Or Add New', 'wp-genius'); ?></label>
                        <input type="text" name="new_custom_taxonomy" id="word_to_posts_new_custom_taxonomy" placeholder="<?php _e('New book name', 'wp-genius'); ?>">
                    </div>

                    <!-- File Upload -->
                    <div class="w2p-form-group w2p-full-width">
                        <label for="word_file"><?php _e('Word File (.docx)', 'wp-genius'); ?></label>
                        <input type="file" name="word_file" id="word_file" accept=".docx,.doc" class="w2p-file-input">
                    </div>
                </div>

                <div class="w2p-form-actions">
                    <button type="submit" class="button button-primary button-large"><?php _e('Upload and Import', 'wp-genius'); ?></button>
                </div>
            </form>

            <div id="wp-genius-log-upload" class="word2postNotice"></div>
        </div>

        <!-- Scan & Clean Card -->
        <div class="w2p-feature-card">
            <div class="w2p-feature-header">
                <div class="w2p-feature-info">
                    <h2><?php _e('Clean Uploads', 'wp-genius'); ?></h2>
                    <p class="description"><?php _e('Scan and manage files in your uploads directory.', 'wp-genius'); ?></p>
                </div>
            </div>

            <div class="w2p-clean-actions">
                <form id="word_to_posts_scan_form" method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="w2p-scan-form">
                    <input type="hidden" name="action" value="scan_uploads">
                    <?php wp_nonce_field('word_to_posts_scan', 'word_to_posts_scan_nonce'); ?>
                    <button type="submit" class="button"><?php _e('Scan Files', 'wp-genius'); ?></button>
                </form>

                <form id="word_to_posts_clean_form" method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="w2p-clean-form">
                    <input type="hidden" name="action" value="clean_uploads">
                    <?php wp_nonce_field('word_to_posts_clean', 'word_to_posts_clean_nonce'); ?>
                    <button type="submit" class="button button-primary" onclick="return confirm('<?php _e('Are you sure you want to clean the uploads directory?', 'wp-genius'); ?>');"><?php _e('Clean Files', 'wp-genius'); ?></button>
                </form>
            </div>

            <div id="wp-genius-log-clean" class="word2postNotice"></div>
        </div>

    </div>
</div>
