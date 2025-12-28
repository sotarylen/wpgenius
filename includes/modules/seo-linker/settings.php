<?php
/**
 * SEO & Linker Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'w2p_seo_linker_settings', [] );
$keywords = ! empty( $settings['keywords'] ) ? $settings['keywords'] : [];
?>

<div class="w2p-settings-panel w2p-seo-linker-settings">
    <h3><?php esc_html_e( 'SEO & Internal Linker Settings', 'wp-genius' ); ?></h3>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
        <input type="hidden" name="action" value="word2posts_save_module_settings" />
        <input type="hidden" name="module_id" value="seo-linker" />

        <!-- Internal Linker Section -->
        <div class="w2p-cleanup-section">
            <h4><?php esc_html_e( 'Auto Internal Linker', 'wp-genius' ); ?></h4>
            <div class="w2p-setting-row w2p-flex-row">
                <div class="w2p-setting-label">
                    <strong><?php esc_html_e( 'Enable Auto Linker', 'wp-genius' ); ?></strong>
                </div>
                <div class="w2p-setting-control">
                    <label class="switch">
                        <input type="checkbox" name="w2p_seo_linker_settings[linker_enabled]" value="1" <?php checked( ! empty( $settings['linker_enabled'] ) ); ?> />
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div id="w2p-keywords-wrapper" <?php echo empty( $settings['linker_enabled'] ) ? 'style="display:none;"' : ''; ?>>
                <h5><?php esc_html_e( 'Keyword Mappings', 'wp-genius' ); ?></h5>
                <p class="description"><?php esc_html_e( 'Define keywords and the URLs they should link to. (Only the first occurrence in each post will be linked).', 'wp-genius' ); ?></p>
                
                <table class="widefat" id="w2p-keywords-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Keyword', 'wp-genius' ); ?></th>
                            <th><?php esc_html_e( 'Target URL', 'wp-genius' ); ?></th>
                            <th><?php esc_html_e( 'Link Title (Optional)', 'wp-genius' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $keywords ) ) : ?>
                            <tr class="w2p-keyword-row">
                                <td><input type="text" name="w2p_seo_linker_settings[keywords][0][keyword]" value="" placeholder="e.g. WordPress" /></td>
                                <td><input type="url" name="w2p_seo_linker_settings[keywords][0][url]" value="" placeholder="https://..." /></td>
                                <td><input type="text" name="w2p_seo_linker_settings[keywords][0][title]" value="" /></td>
                                <td><button type="button" class="button w2p-remove-keyword">×</button></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $keywords as $index => $item ) : ?>
                                <tr class="w2p-keyword-row">
                                    <td><input type="text" name="w2p_seo_linker_settings[keywords][<?php echo $index; ?>][keyword]" value="<?php echo esc_attr( $item['keyword'] ); ?>" /></td>
                                    <td><input type="url" name="w2p_seo_linker_settings[keywords][<?php echo $index; ?>][url]" value="<?php echo esc_url( $item['url'] ); ?>" /></td>
                                    <td><input type="text" name="w2p_seo_linker_settings[keywords][<?php echo $index; ?>][title]" value="<?php echo esc_attr( $item['title'] ); ?>" /></td>
                                    <td><button type="button" class="button w2p-remove-keyword">×</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="w2p-add-keyword"><?php esc_html_e( 'Add Keyword', 'wp-genius' ); ?></button></p>
            </div>
        </div>

        <!-- Table of Contents Section -->
        <div class="w2p-cleanup-section">
            <h4><?php esc_html_e( 'Table of Contents (TOC)', 'wp-genius' ); ?></h4>
            <div class="w2p-setting-row w2p-flex-row">
                <div class="w2p-setting-label">
                    <strong><?php esc_html_e( 'Enable Table of Contents', 'wp-genius' ); ?></strong>
                </div>
                <div class="w2p-setting-control">
                    <label class="switch">
                        <input type="checkbox" name="w2p_seo_linker_settings[toc_enabled]" value="1" <?php checked( ! empty( $settings['toc_enabled'] ) ); ?> />
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div id="w2p-toc-wrapper" <?php echo empty( $settings['toc_enabled'] ) ? 'style="display:none;"' : ''; ?>>
                <div class="w2p-setting-row w2p-flex-row">
                    <div class="w2p-setting-label">
                        <strong><?php esc_html_e( 'Auto Insert TOC', 'wp-genius' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'If enabled, TOC will appear at the start of the post content.', 'wp-genius' ); ?></p>
                    </div>
                    <div class="w2p-setting-control">
                        <label class="switch">
                            <input type="checkbox" name="w2p_seo_linker_settings[toc_auto_insert]" value="1" <?php checked( ! empty( $settings['toc_auto_insert'] ) ); ?> />
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="w2p-setting-row">
                    <label><strong><?php esc_html_e( 'Heading Threshold', 'wp-genius' ); ?></strong></label>
                    <input type="number" name="w2p_seo_linker_settings[toc_threshold]" value="<?php echo esc_attr( $settings['toc_threshold'] ); ?>" min="1" />
                    <p class="description"><?php esc_html_e( 'Only show TOC if the post has at least this many headings.', 'wp-genius' ); ?></p>
                </div>
            </div>
        </div>

        <div class="w2p-cleanup-actions">
            <?php submit_button( __( 'Save SEO Settings', 'wp-genius' ), 'primary' ); ?>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle Wrapper Visibility
    $('input[name="w2p_seo_linker_settings[linker_enabled]"]').on('change', function() {
        $('#w2p-keywords-wrapper').toggle(this.checked);
    });
    $('input[name="w2p_seo_linker_settings[toc_enabled]"]').on('change', function() {
        $('#w2p-toc-wrapper').toggle(this.checked);
    });

    // Add Keyword Row
    $('#w2p-add-keyword').on('click', function() {
        var $table = $('#w2p-keywords-table tbody');
        var index = $table.find('.w2p-keyword-row').length;
        var newRow = '<tr class="w2p-keyword-row">' +
            '<td><input type="text" name="w2p_seo_linker_settings[keywords][' + index + '][keyword]" value="" /></td>' +
            '<td><input type="url" name="w2p_seo_linker_settings[keywords][' + index + '][url]" value="" /></td>' +
            '<td><input type="text" name="w2p_seo_linker_settings[keywords][' + index + '][title]" value="" /></td>' +
            '<td><button type="button" class="button w2p-remove-keyword">×</button></td>' +
            '</tr>';
        $table.append(newRow);
    });

    // Remove Keyword Row
    $(document).on('click', '.w2p-remove-keyword', function() {
        $(this).closest('tr').remove();
    });
});
</script>

<style>
.w2p-setting-row {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}
.w2p-setting-row:last-of-type {
    border-bottom: none;
}
.w2p-flex-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}
.w2p-setting-label {
    flex: 1;
}
.w2p-setting-label strong {
    display: block;
    font-size: 14px;
    margin-bottom: 5px;
}
.w2p-setting-control {
    flex: 0 0 auto;
}
#w2p-keywords-table input { width: 100%; }
.w2p-remove-keyword { color: #d63638; border-color: #d63638; }
.w2p-remove-keyword:hover { background: #fcf0f1; border-color: #d63638; color: #d63638; }
</style>
