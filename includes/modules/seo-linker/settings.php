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
        <?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_seo_linker_nonce' ); ?>
        <input type="hidden" name="action" value="word2posts_save_module_settings" />
        <input type="hidden" name="module_id" value="seo-linker" />

        <!-- Internal Linker Section -->
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php esc_html_e( 'Auto Internal Linker', 'wp-genius' ); ?></h4>
            </div>
            
            <div class="w2p-form-row">
                <div class="w2p-form-label">
                    <strong><?php esc_html_e( 'Enable Auto Linker', 'wp-genius' ); ?></strong>
                </div>
                <div class="w2p-form-control">
                    <label class="w2p-switch">
                        <input type="checkbox" name="w2p_seo_linker_settings[linker_enabled]" value="1" <?php checked( ! empty( $settings['linker_enabled'] ) ); ?> />
                        <span class="w2p-slider"></span>
                    </label>
                </div>
            </div>

            <div id="w2p-keywords-wrapper" <?php echo empty( $settings['linker_enabled'] ) ? 'style="display:none;"' : ''; ?> class="w2p-section" style="margin-top: 20px;">
                <div class="w2p-section-header">
                    <h4><?php esc_html_e( 'Keyword Mappings', 'wp-genius' ); ?></h4>
                </div>
                <p class="description"><?php esc_html_e( 'Define keywords and the URLs they should link to. (Only the first occurrence in each post will be linked).', 'wp-genius' ); ?></p>
                
                <div class="w2p-keywords-list" id="w2p-keywords-container" style="margin-top: 15px;">
                    <div class="w2p-keywords-header w2p-grid w2p-gap-md" style="grid-template-columns: 1fr 1fr 1fr 40px; padding: 10px; background: var(--w2p-bg-soft); font-weight: bold; border-radius: var(--w2p-radius-md);">
                        <div><?php esc_html_e( 'Keyword', 'wp-genius' ); ?></div>
                        <div><?php esc_html_e( 'Target URL', 'wp-genius' ); ?></div>
                        <div><?php esc_html_e( 'Link Title', 'wp-genius' ); ?></div>
                        <div></div>
                    </div>
                    
                    <div id="w2p-keywords-body" class="w2p-flex-col w2p-gap-sm" style="margin-top: 10px;">
                        <?php if ( empty( $keywords ) ) : ?>
                            <div class="w2p-keyword-row w2p-grid w2p-gap-md" style="grid-template-columns: 1fr 1fr 1fr 40px; align-items: center;">
                                <div><input type="text" name="w2p_seo_linker_settings[keywords][0][keyword]" value="" placeholder="e.g. WordPress" class="w2p-input-large" /></div>
                                <div><input type="url" name="w2p_seo_linker_settings[keywords][0][url]" value="" placeholder="https://..." class="w2p-input-large" /></div>
                                <div><input type="text" name="w2p_seo_linker_settings[keywords][0][title]" value="" class="w2p-input-large" /></div>
                                <div><button type="button" class="button w2p-remove-keyword" title="<?php esc_attr_e('Remove', 'wp-genius'); ?>">×</button></div>
                            </div>
                        <?php else : ?>
                            <?php foreach ( $keywords as $index => $item ) : ?>
                                <div class="w2p-keyword-row w2p-grid w2p-gap-md" style="grid-template-columns: 1fr 1fr 1fr 40px; align-items: center;">
                                    <div><input type="text" name="w2p_seo_linker_settings[keywords][<?php echo $index; ?>][keyword]" value="<?php echo esc_attr( $item['keyword'] ); ?>" class="w2p-input-large" /></div>
                                    <div><input type="url" name="w2p_seo_linker_settings[keywords][<?php echo $index; ?>][url]" value="<?php echo esc_url( $item['url'] ); ?>" class="w2p-input-large" /></div>
                                    <div><input type="text" name="w2p_seo_linker_settings[keywords][<?php echo $index; ?>][title]" value="<?php echo esc_attr( $item['title'] ); ?>" class="w2p-input-large" /></div>
                                    <div><button type="button" class="button w2p-remove-keyword" title="<?php esc_attr_e('Remove', 'wp-genius'); ?>">×</button></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <p style="margin-top: 15px;"><button type="button" class="button button-secondary" id="w2p-add-keyword"><?php esc_html_e( 'Add Keyword Mapping', 'wp-genius' ); ?></button></p>
            </div>
        </div>

        <!-- Table of Contents Section -->
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php esc_html_e( 'Table of Contents (TOC)', 'wp-genius' ); ?></h4>
            </div>
            
            <div class="w2p-form-row">
                <div class="w2p-form-label">
                    <strong><?php esc_html_e( 'Enable Table of Contents', 'wp-genius' ); ?></strong>
                </div>
                <div class="w2p-form-control">
                    <label class="w2p-switch">
                        <input type="checkbox" name="w2p_seo_linker_settings[toc_enabled]" value="1" <?php checked( ! empty( $settings['toc_enabled'] ) ); ?> />
                        <span class="w2p-slider"></span>
                    </label>
                </div>
            </div>

            <div id="w2p-toc-wrapper" <?php echo empty( $settings['toc_enabled'] ) ? 'style="display:none;"' : ''; ?>>
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <strong><?php esc_html_e( 'Auto Insert TOC', 'wp-genius' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'If enabled, TOC will appear at the start of the post content.', 'wp-genius' ); ?></p>
                    </div>
                    <div class="w2p-form-control">
                        <label class="w2p-switch">
                            <input type="checkbox" name="w2p_seo_linker_settings[toc_auto_insert]" value="1" <?php checked( ! empty( $settings['toc_auto_insert'] ) ); ?> />
                            <span class="w2p-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="w2p-form-row border-none">
                    <div class="w2p-form-label">
                        <strong><?php esc_html_e( 'Heading Threshold', 'wp-genius' ); ?></strong>
                        <p class="description"><?php esc_html_e( 'Only show TOC if the post has at least this many headings.', 'wp-genius' ); ?></p>
                    </div>
                    <div class="w2p-form-control">
                        <input type="number" name="w2p_seo_linker_settings[toc_threshold]" value="<?php echo esc_attr( $settings['toc_threshold'] ); ?>" min="1" class="w2p-input-small" />
                    </div>
                </div>
            </div>
        </div>

        <div class="w2p-settings-actions">
            <input type="submit" name="submit" id="w2p-seo-linker-submit" class="button button-primary" value="<?php esc_attr_e( 'Save SEO Settings', 'wp-genius' ); ?>">
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
        var $body = $('#w2p-keywords-body');
        var index = $body.find('.w2p-keyword-row').length;
        var newRow = '<div class="w2p-keyword-row w2p-grid w2p-gap-md" style="grid-template-columns: 1fr 1fr 1fr 40px; align-items: center;">' +
            '<div><input type="text" name="w2p_seo_linker_settings[keywords][' + index + '][keyword]" value="" class="w2p-input-large" /></div>' +
            '<div><input type="url" name="w2p_seo_linker_settings[keywords][' + index + '][url]" value="" class="w2p-input-large" /></div>' +
            '<div><input type="text" name="w2p_seo_linker_settings[keywords][' + index + '][title]" value="" class="w2p-input-large" /></div>' +
            '<div><button type="button" class="button w2p-remove-keyword" title="<?php esc_attr_e('Remove', 'wp-genius'); ?>">×</button></div>' +
            '</div>';
        $body.append(newRow);
    });

    // Remove Keyword Row
    $(document).on('click', '.w2p-remove-keyword', function() {
        $(this).closest('.w2p-keyword-row').remove();
    });
});
</script>

