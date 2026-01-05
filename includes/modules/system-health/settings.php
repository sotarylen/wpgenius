<?php
/**
 * System Health Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$service = new SystemHealthCleanupService();
$stats = [
    'revisions'     => '-',
    'auto_drafts'   => '-',
    'orphaned_meta' => '-',
    'transients'    => '-',
];
$system_info = $service->get_system_info();
?>

<div class="w2p-sub-tabs" id="w2p-system-health-tabs">
    <div class="w2p-sub-tab-nav">
        <a class="w2p-sub-tab-link active" data-tab="cleanup"><?php esc_html_e( 'Cleanup Tools', 'wp-genius' ); ?></a>
        <a class="w2p-sub-tab-link" data-tab="image-remover"><?php esc_html_e( 'Image Link Remover', 'wp-genius' ); ?></a>
        <a class="w2p-sub-tab-link" data-tab="duplicate-cleaner"><?php esc_html_e( 'Duplicate Post Clean', 'wp-genius' ); ?></a>

        <a class="w2p-sub-tab-link" data-tab="info"><?php esc_html_e( 'System Info', 'wp-genius' ); ?></a>
    </div>

    <!-- Cleanup Tools Tab -->
    <div class="w2p-sub-tab-content active" id="w2p-tab-cleanup">
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php esc_html_e( 'Database Cleaning & Optimization', 'wp-genius' ); ?></h4>
            </div>

            <div class="w2p-section-body">
                <div class="w2p-info-box w2p-flex w2p-justify-between w2p-items-center">
                    <p style="margin:0;"><?php esc_html_e( 'Keep your site fast by removing unnecessary data from your database. Click the scan button to check current system health.', 'wp-genius' ); ?></p>
                    <button type="button" id="w2p-health-scan-btn" class="button button-primary">
                        <span class="dashicons dashicons-search" style="margin-top: 4px; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Scan System Status', 'wp-genius' ); ?>
                    </button>
                </div>

                <div class="w2p-health-grid">
                    <!-- Cards remain same -->
                    <div class="w2p-health-card" data-type="revisions">
                        <div class="w2p-health-info">
                            <span class="w2p-health-label"><?php esc_html_e( 'Post Revisions', 'wp-genius' ); ?></span>
                            <span class="w2p-health-count"><?php echo esc_html( $stats['revisions'] ); ?></span>
                        </div>
                        <button class="button w2p-health-action" data-action="revisions">
                            <?php esc_html_e( 'Clean Revisions', 'wp-genius' ); ?>
                        </button>
                    </div>

                    <div class="w2p-health-card" data-type="auto_drafts">
                        <div class="w2p-health-info">
                            <span class="w2p-health-label"><?php esc_html_e( 'Auto Drafts', 'wp-genius' ); ?></span>
                            <span class="w2p-health-count"><?php echo esc_html( $stats['auto_drafts'] ); ?></span>
                        </div>
                        <button class="button w2p-health-action" data-action="auto_drafts">
                            <?php esc_html_e( 'Clean Auto Drafts', 'wp-genius' ); ?>
                        </button>
                    </div>

                    <div class="w2p-health-card" data-type="orphaned_meta">
                        <div class="w2p-health-info">
                            <span class="w2p-health-label"><?php esc_html_e( 'Orphaned Metadata', 'wp-genius' ); ?></span>
                            <span class="w2p-health-count"><?php echo esc_html( $stats['orphaned_meta'] ); ?></span>
                        </div>
                        <button class="button w2p-health-action" data-action="orphaned_meta">
                            <?php esc_html_e( 'Clean Orphaned Meta', 'wp-genius' ); ?>
                        </button>
                    </div>

                    <div class="w2p-health-card" data-type="transients">
                        <div class="w2p-health-info">
                            <span class="w2p-health-label"><?php esc_html_e( 'Expired Transients', 'wp-genius' ); ?></span>
                            <span class="w2p-health-count"><?php echo esc_html( $stats['transients'] ); ?></span>
                        </div>
                        <button class="button w2p-health-action" data-action="transients">
                            <?php esc_html_e( 'Clean Transients', 'wp-genius' ); ?>
                        </button>
                    </div>
                </div>

                <div id="w2p-health-message" class="w2p-notice" style="display:none;"></div>
            </div>
        </div>
    </div>

    <!-- Image Link Remover Tab -->
    <div class="w2p-sub-tab-content" id="w2p-tab-image-remover">
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php esc_html_e( 'Image Link Remover', 'wp-genius' ); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-info-box w2p-flex w2p-items-center w2p-gap-md">
                    <div class="w2p-flex-1">
                        <p><?php esc_html_e( 'Scan posts for images wrapped in links and remove the links while keeping the images.', 'wp-genius' ); ?></p>
                        <select id="w2p-image-link-category" class="w2p-select" style="min-width: 200px;">
                            <option value="0"><?php esc_html_e( 'All Categories', 'wp-genius' ); ?></option>
                            <?php 
                            $sh_categories = $service->get_categories();
                            foreach ( (array) $sh_categories as $cat ) {
                                $cat_id = is_object($cat) ? ($cat->term_id ?? 0) : (is_array($cat) ? ($cat['term_id'] ?? 0) : 0);
                                $cat_name = is_object($cat) ? ($cat->name ?? '') : (is_array($cat) ? ($cat['name'] ?? '') : '');
                                $cat_count = is_object($cat) ? ($cat->count ?? 0) : (is_array($cat) ? ($cat['count'] ?? 0) : 0);
                                if ( !$cat_id ) continue;
                                echo '<option value="' . esc_attr( $cat_id ) . '">' . esc_html( $cat_name ) . ' (' . esc_html( $cat_count ) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button type="button" id="w2p-image-link-scan-btn" class="button button-primary">
                        <span class="dashicons dashicons-search" style="margin-top: 4px; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Scan for Linked Images', 'wp-genius' ); ?>
                    </button>
                </div>

                <div id="w2p-image-link-results-wrapper" style="display:none; margin-top: 20px;">
                    <div id="w2p-image-link-notice" class="w2p-notice" style="display:none; margin-bottom: 15px;"></div>
                    <div class="w2p-info-box w2p-flex w2p-justify-between w2p-items-center">
                        <div id="w2p-image-link-status"></div>
                        <div class="w2p-flex w2p-items-center w2p-gap-sm">
                            <div class="w2p-flex w2p-items-center w2p-gap-xs">
                                <label for="w2p-image-link-batch-size" style="font-size: 12px; color: var(--w2p-text-secondary);"><?php esc_html_e( 'Batch Size:', 'wp-genius' ); ?></label>
                                <input type="number" id="w2p-image-link-batch-size" value="10" min="1" max="100" class="w2p-input-small" style="width: 60px; height: 30px; padding: 0 8px;">
                            </div>
                            <button type="button" id="w2p-image-link-execute-btn" class="button button-primary">
                                <span class="dashicons dashicons-performance" style="margin-top: 4px; margin-right: 4px;"></span>
                                <?php esc_html_e( 'Execute Removal', 'wp-genius' ); ?>
                            </button>
                            <button type="button" id="w2p-image-link-stop-btn" class="button w2p-btn" style="display:none; background: var(--w2p-color-error); color: white; border: none; border-radius: 50px;">
                                <span class="dashicons dashicons-no-alt" style="margin-top: 4px; margin-right: 4px;"></span>
                                <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- <div class="w2p-progress-container" id="w2p-image-link-progress-wrapper" style="display:none; margin: 15px 0;">
                        <div class="w2p-progress-bar">
                            <div class="w2p-progress-fill" id="w2p-image-link-progress-fill" style="width: 0%;"></div>
                        </div>
                        <div class="w2p-progress-status" id="w2p-image-link-progress-status" style="margin-top: 5px; font-size: 12px; color: var(--w2p-text-secondary);"></div>
                    </div> -->

                    <div class="w2p-scroll-area" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--w2p-border-color); border-radius: 4px; margin-top: 10px;">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 80px;"><?php esc_html_e( 'Post ID', 'wp-genius' ); ?></th>
                                    <th><?php esc_html_e( 'Title', 'wp-genius' ); ?></th>
                                    <th style="width: 120px;"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
                                </tr>
                            </thead>
                            <tbody id="w2p-image-link-items"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <!-- Duplicate Post Cleaner Tab -->
    <div class="w2p-sub-tab-content" id="w2p-tab-duplicate-cleaner">
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php esc_html_e( 'Duplicate Post Clean', 'wp-genius' ); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-info-box w2p-flex w2p-items-center w2p-gap-md">
                    <div class="w2p-flex-1">
                        <p class="w2p-mb-sm"><?php esc_html_e( 'Scan and remove duplicate posts based on title and slug matching.', 'wp-genius' ); ?></p>
                        <select id="w2p-duplicate-category" class="w2p-select w2p-min-w-200">
                            <option value="0"><?php esc_html_e( 'All Categories', 'wp-genius' ); ?></option>
                            <?php 
                            foreach ( (array) $sh_categories as $cat ) {
                                $cat_id = is_object($cat) ? ($cat->term_id ?? 0) : (is_array($cat) ? ($cat['term_id'] ?? 0) : 0);
                                $cat_name = is_object($cat) ? ($cat->name ?? '') : (is_array($cat) ? ($cat['name'] ?? '') : '');
                                $cat_count = is_object($cat) ? ($cat->count ?? 0) : (is_array($cat) ? ($cat['count'] ?? 0) : 0);
                                if ( !$cat_id ) continue;
                                echo '<option value="' . esc_attr( $cat_id ) . '">' . esc_html( $cat_name ) . ' (' . esc_html( $cat_count ) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button type="button" id="w2p-duplicate-scan-btn" class="button button-primary" data-text-default="<?php esc_attr_e( 'Scan for Duplicates', 'wp-genius' ); ?>" data-text-scanning="<?php esc_attr_e( 'Scanning...', 'wp-genius' ); ?>">
                        <span class="dashicons dashicons-search"></span>
                        <span class="btn-text"><?php esc_html_e( 'Scan for Duplicates', 'wp-genius' ); ?></span>
                    </button>
                </div>

                <div id="w2p-duplicate-results-wrapper" class="w2p-hidden w2p-mt-md">
                    <div id="w2p-duplicate-notice" class="w2p-notice w2p-hidden w2p-mb-sm"></div>
                    <div class="w2p-info-box w2p-flex w2p-justify-between w2p-items-center">
                        <div id="w2p-duplicate-status"></div>
                        <div class="w2p-flex w2p-gap-sm">
                            <button type="button" id="w2p-duplicate-clear-btn" class="button button-secondary">
                                <span class="dashicons dashicons-no"></span>
                                <?php esc_html_e( 'Clear Selection', 'wp-genius' ); ?>
                            </button>
                            <button type="button" id="w2p-duplicate-clean-btn" class="button button-primary" data-text-default="<?php esc_attr_e( 'Clean All Selected', 'wp-genius' ); ?>" data-text-cleaning="<?php esc_attr_e( 'Cleaning...', 'wp-genius' ); ?>">
                                <span class="dashicons dashicons-trash"></span>
                                <span class="btn-text"><?php esc_html_e( 'Clean All Selected', 'wp-genius' ); ?></span>
                            </button>
                        </div>
                    </div>

                    <div id="w2p-duplicate-groups" class="w2p-mt-sm">
                        <!-- Duplicate groups will be rendered here -->
                    </div>

                    <!-- Hidden template for empty state -->
                    <template id="w2p-duplicate-empty-template">
                        <p class="w2p-info-box"><?php esc_html_e( 'No duplicate posts found.', 'wp-genius' ); ?></p>
                    </template>

                    <!-- Hidden template for duplicate group -->
                    <template id="w2p-duplicate-group-template">
                        <div class="w2p-duplicate-group">
                            <div class="w2p-duplicate-group-header w2p-flex w2p-justify-between w2p-items-center">
                                <h3 class="group-title"></h3>
                                <button type="button" class="button button-secondary w2p-clean-group-btn" data-text-default="<?php esc_attr_e( 'Clean This Group', 'wp-genius' ); ?>" data-text-cleaning="<?php esc_attr_e( 'Cleaning...', 'wp-genius' ); ?>" data-text-done="<?php esc_attr_e( 'Done', 'wp-genius' ); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                    <span class="btn-text"><?php esc_html_e( 'Clean This Group', 'wp-genius' ); ?></span>
                                </button>
                            </div>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th class="w2p-col-select"></th>
                                        <th class="w2p-col-id"><?php esc_html_e( 'Post ID', 'wp-genius' ); ?></th>
                                        <th class="w2p-col-title"><?php esc_html_e( 'Title', 'wp-genius' ); ?></th>
                                        <th class="w2p-col-slug"><?php esc_html_e( 'Slug', 'wp-genius' ); ?></th>
                                        <th class="w2p-col-date"><?php esc_html_e( 'Date', 'wp-genius' ); ?></th>
                                        <th class="w2p-col-status"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody class="duplicate-posts-body">
                                    <!-- Posts will be rendered here -->
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <!-- Hidden template for duplicate post row -->
                    <template id="w2p-duplicate-post-template">
                        <tr class="duplicate-post-row">
                            <td>
                                <input type="checkbox" class="w2p-duplicate-checkbox w2p-checkbox" />
                            </td>
                            <td class="post-id"></td>
                            <td class="post-title"></td>
                            <td class="post-slug"><code></code></td>
                            <td class="post-date"></td>
                            <td class="post-status">
                                <span class="status-keep status-badge success">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php esc_html_e( 'Keep', 'wp-genius' ); ?>
                                </span>
                                <span class="status-delete status-badge error"><?php esc_html_e( 'To Delete', 'wp-genius' ); ?></span>
                            </td>
                        </tr>
                    </template>
                </div>
            </div>
        </div>
    </div>



    <!-- System Info Tab -->
    <div class="w2p-sub-tab-content" id="w2p-tab-info">
        <div class="w2p-flex w2p-gap-lg">
            <!-- Server Environment -->
            <div class="w2p-flex-1">
                <div class="w2p-section">
                    <div class="w2p-section-header">
                        <h4><?php esc_html_e( 'Server Environment', 'wp-genius' ); ?></h4>
                    </div>
                    <div class="w2p-section-body">
                        <div class="w2p-info-grid">
                            <?php foreach ( $system_info['server'] as $key => $value ) : ?>
                                <div class="w2p-info-row">
                                    <div class="w2p-info-label"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></div>
                                    <div class="w2p-info-value"><?php echo esc_html( $value ); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WordPress Configuration -->
            <div class="w2p-flex-1">
                <div class="w2p-section">
                    <div class="w2p-section-header">
                        <h4><?php esc_html_e( 'WordPress Configuration', 'wp-genius' ); ?></h4>
                    </div>
                    <div class="w2p-section-body">
                        <div class="w2p-info-grid">
                            <?php foreach ( $system_info['wordpress'] as $key => $value ) : ?>
                                <div class="w2p-info-row">
                                    <div class="w2p-info-label"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></div>
                                    <div class="w2p-info-value"><?php echo esc_html( $value ); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>