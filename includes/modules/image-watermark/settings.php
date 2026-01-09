<?php
if (!defined('ABSPATH')) {
    exit;
}

// 获取模块实例
$module = W2P_Image_Watermark::instance();
$options = $module->options;
?>

<div class="w2p-module-settings-panel">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('word2posts_save_module_settings', 'w2p_image_watermark_nonce'); ?>
        <input type="hidden" name="action" value="word2posts_save_module_settings" />
        <input type="hidden" name="module_id" value="image-watermark" />
        <input type="hidden" name="iw_options" value="1" />

        <!-- Activation & Mode -->
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e('Watermarking Behavior', 'wp-genius'); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <label><?php _e('Automatic Watermarking', 'wp-genius'); ?></label>
                    </div>
                    <div class="w2p-form-control">
                        <label class="w2p-switch">
                            <input type="checkbox" name="iw_options[watermark_image][plugin_off]" value="1" <?php checked(!empty($options['watermark_image']['plugin_off']), 1); ?> />
                            <span class="w2p-slider"></span>
                        </label>
                        <p class="description"><?php _e('Apply watermark to images as they are uploaded.', 'wp-genius'); ?></p>
                    </div>
                </div>

                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <label><?php _e('Manual Watermarking', 'wp-genius'); ?></label>
                    </div>
                    <div class="w2p-form-control">
                        <label class="w2p-switch">
                            <input type="checkbox" name="iw_options[watermark_image][manual_watermarking]" value="1" <?php checked(!empty($options['watermark_image']['manual_watermarking']), 1); ?> />
                            <span class="w2p-slider"></span>
                        </label>
                        <p class="description"><?php _e('Enable watermark action in the Media Library.', 'wp-genius'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Watermark Asset -->
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e('Watermark Image', 'wp-genius'); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <label><?php _e('Select Watermark', 'wp-genius'); ?></label>
                        <p class="description"><?php _e('Choose the image to use as your watermark.', 'wp-genius'); ?></p>
                    </div>
                    <div class="w2p-form-control">
                        <div class="w2p-flex w2p-items-center w2p-gap-sm iw_watermark_image">
                            <input id="iw_upload_image" type="hidden" name="iw_options[watermark_image][url]" value="<?php echo (int) $options['watermark_image']['url']; ?>" />
                            <button type="button" id="iw_upload_image_button" class="w2p-btn w2p-btn-secondary">
                                <i class="fa-solid fa-image"></i>
                                <?php _e('Select Image', 'wp-genius'); ?>
                            </button>
                            <button type="button" id="iw_turn_off_image_button" class="w2p-btn w2p-btn-secondary" <?php echo empty($options['watermark_image']['url']) ? 'disabled="disabled"' : ''; ?>>
                                <i class="fa-solid fa-trash"></i>
                                <?php _e('Remove Image', 'wp-genius'); ?>
                            </button>
                        </div>
                        
                        <div id="previewImg_imageDiv" style="margin-top: 20px; border: 1px dashed var(--w2p-border-color); padding: 10px; border-radius: var(--w2p-radius-md); display: inline-block;">
                            <?php if (!empty($options['watermark_image']['url'])): ?>
                                <?php 
                                $image = wp_get_attachment_image_src($options['watermark_image']['url'], [300, 300], false);
                                if ($image): 
                                ?>
                                    <img id="previewImg_image" src="<?php echo $image[0]; ?>" alt="" style="max-width: 300px; height: auto; display: block;" />
                                <?php else: ?>
                                    <img id="previewImg_image" src="" alt="" style="max-width: 300px; height: auto; display: none;" />
                                <?php endif; ?>
                            <?php else: ?>
                                <img id="previewImg_image" src="" alt="" style="max-width: 300px; height: auto; display: none;" />
                            <?php endif; ?>
                        </div>
                        <p id="previewImageInfo" class="description" style="margin-top: 10px;">
                            <?php if (empty($options['watermark_image']['url'])): ?>
                                <?php _e('Watermark has not been selected yet.', 'wp-genius'); ?>
                            <?php else: ?>
                                <?php 
                                $image_full_size = wp_get_attachment_image_src($options['watermark_image']['url'], 'full', false);
                                if ($image_full_size): 
                                    echo __('Original size', 'wp-genius') . ': ' . $image_full_size[1] . ' ' . __('px', 'wp-genius') . ' / ' . $image_full_size[2] . ' ' . __('px', 'wp-genius');
                                endif;
                                ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                        <label><?php _e('Watermark Size', 'wp-genius'); ?></label>
                    </div>
                    <div class="w2p-form-control">
                        <div id="iw_watermark_size" class="w2p-flex w2p-gap-md">
                            <label class="w2p-flex w2p-items-center w2p-gap-xs"><input type="radio" id="type1" value="0" name="iw_options[watermark_image][watermark_size_type]" <?php checked($options['watermark_image']['watermark_size_type'], 0); ?> /> <span><?php _e('Original', 'wp-genius'); ?></span></label>
                            <label class="w2p-flex w2p-items-center w2p-gap-xs"><input type="radio" id="type2" value="1" name="iw_options[watermark_image][watermark_size_type]" <?php checked($options['watermark_image']['watermark_size_type'], 1); ?> /> <span><?php _e('Custom', 'wp-genius'); ?></span></label>
                            <label class="w2p-flex w2p-items-center w2p-gap-xs"><input type="radio" id="type3" value="2" name="iw_options[watermark_image][watermark_size_type]" <?php checked($options['watermark_image']['watermark_size_type'], 2); ?> /> <span><?php _e('Scaled', 'wp-genius'); ?></span></label>
                        </div>

                        <!-- Custom Size Inputs -->
                        <div class="iw-watermark-size-custom" style="margin-top: 15px; <?php echo ($options['watermark_image']['watermark_size_type'] == 1 ? '' : 'display: none;'); ?>">
                             <div class="w2p-flex w2p-items-center w2p-gap-sm">
                                <span><?php _e('Width:', 'wp-genius'); ?></span> <input type="text" size="5" class="small-text w2p-input-small" name="iw_options[watermark_image][absolute_width]" value="<?php echo $options['watermark_image']['absolute_width']; ?>"> <span><?php _e('px', 'wp-genius'); ?></span>
                                <span><?php _e('Height:', 'wp-genius'); ?></span> <input type="text" size="5" class="small-text w2p-input-small" name="iw_options[watermark_image][absolute_height]" value="<?php echo $options['watermark_image']['absolute_height']; ?>"> <span><?php _e('px', 'wp-genius'); ?></span>
                            </div>
                        </div>

                        <!-- Scaled Size Input -->
                        <div class="iw-watermark-size-scaled" style="margin-top: 15px; <?php echo ($options['watermark_image']['watermark_size_type'] == 2 ? '' : 'display: none;'); ?>">
                            <div class="w2p-flex w2p-items-center w2p-gap-sm">
                                <input type="text" id="iw_size_input" maxlength="3" class="small-text w2p-input-small" name="iw_options[watermark_image][width]" value="<?php echo $options['watermark_image']['width']; ?>" />
                                <span>% <?php _e('of image width', 'wp-genius'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Position & Transparency -->
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e('Watermark Appearance', 'wp-genius'); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-form-row">
                    <div class="w2p-form-label"><?php _e('Watermark Position', 'wp-genius'); ?></div>
                    <div class="w2p-form-control">
                        <div id="watermark_position" class="w2p-grid w2p-gap-xs" style="grid-template-columns: repeat(3, 1fr); width: 150px; background: var(--w2p-bg-surface-secondary); padding: 5px; border-radius: var(--w2p-radius-md);">
                            <?php
                            $watermark_positions = [
                                'y' => ['top', 'middle', 'bottom'],
                                'x' => ['left', 'center', 'right']
                            ];
                            $watermark_position = $options['watermark_image']['position'];
                            
                            foreach ($watermark_positions['y'] as $y) {
                                foreach ($watermark_positions['x'] as $x) {
                                    $position = $y . '_' . $x;
                                    $is_checked = ($watermark_position === $position);
                                    ?>
                                    <label title="<?php echo ucfirst($y . ' ' . $x); ?>" class="w2p-flex w2p-items-center w2p-justify-center" style="height: 40px; background: <?php echo $is_checked ? 'var(--w2p-color-primary-light)' : 'var(--w2p-bg-surface)'; ?>; border: 1px solid <?php echo $is_checked ? 'var(--w2p-color-primary)' : 'var(--w2p-border-color)'; ?>; cursor: pointer; border-radius: 4px;">
                                        <input name="iw_options[watermark_image][position]" type="radio" value="<?php echo $position; ?>" <?php checked($watermark_position, $position); ?> style="margin: 0;" />
                                    </label>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="w2p-form-row">
                    <div class="w2p-form-label"><?php _e('Watermark Padding', 'wp-genius'); ?></div>
                    <div class="w2p-form-control">
                        <div id="iw_padding" class="w2p-grid w2p-grid-cols-2 w2p-gap-md" style="max-width: 400px;">
                            <div class="w2p-flex w2p-items-center w2p-gap-sm">
                                <span style="min-width: 60px;"><?php echo __('Top:', 'wp-genius'); ?></span>
                                <input type="number" class="small-text w2p-input-small" name="iw_options[watermark_image][padding_top]" value="<?php echo $options['watermark_image']['padding_top']; ?>"> <span class="description">px</span>
                            </div>
                            <div class="w2p-flex w2p-items-center w2p-gap-sm">
                                <span style="min-width: 60px;"><?php echo __('Right:', 'wp-genius'); ?></span>
                                <input type="number" class="small-text w2p-input-small" name="iw_options[watermark_image][padding_right]" value="<?php echo $options['watermark_image']['padding_right']; ?>"> <span class="description">px</span>
                            </div>
                            <div class="w2p-flex w2p-items-center w2p-gap-sm">
                                <span style="min-width: 60px;"><?php echo __('Bottom:', 'wp-genius'); ?></span>
                                <input type="number" class="small-text w2p-input-small" name="iw_options[watermark_image][padding_bottom]" value="<?php echo $options['watermark_image']['padding_bottom']; ?>"> <span class="description">px</span>
                            </div>
                            <div class="w2p-flex w2p-items-center w2p-gap-sm">
                                <span style="min-width: 60px;"><?php echo __('Left:', 'wp-genius'); ?></span>
                                <input type="number" class="small-text w2p-input-small" name="iw_options[watermark_image][padding_left]" value="<?php echo $options['watermark_image']['padding_left']; ?>"> <span class="description">px</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                         <label><?php _e('Opacity', 'wp-genius'); ?></label>
                    </div>
                    <div class="w2p-form-control">
                        <input type="text" maxlength="3" class="small-text w2p-input-small" name="iw_options[watermark_image][transparent]" value="<?php echo $options['watermark_image']['transparent']; ?>" />
                        <p class="description"><?php _e('0 (transparent) to 100 (opaque).', 'wp-genius'); ?></p>
                    </div>
                </div>

                <div class="w2p-form-row border-none">
                    <div class="w2p-form-label">
                         <label><?php _e('Image Quality', 'wp-genius'); ?></label>
                    </div>
                    <div class="w2p-form-control">
                        <input type="text" maxlength="3" class="small-text w2p-input-small" name="iw_options[watermark_image][quality]" value="<?php echo $options['watermark_image']['quality']; ?>" />
                        <p class="description"><?php _e('Output image compression level.', 'wp-genius'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sizes & Permissions -->
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e('Apply Rules', 'wp-genius'); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-form-row">
                    <div class="w2p-form-label"><?php _e('Apply to Large Sizes', 'wp-genius'); ?></div>
                    <div class="w2p-form-control">
                        <div class="w2p-flex-wrap w2p-gap-sm">
                            <?php
                            $image_sizes = get_intermediate_image_sizes();
                            $image_sizes[] = 'full';
                            sort($image_sizes, SORT_STRING);
                            foreach ($image_sizes as $image_size) {
                                ?>
                                <label class="w2p-flex w2p-items-center w2p-gap-xs" style="min-width: 140px; background: var(--w2p-bg-surface-secondary); padding: 5px 10px; border-radius: var(--w2p-radius-sm); margin-bottom: 5px;">
                                    <input name="iw_options[watermark_on][<?php echo $image_size; ?>]" type="checkbox" value="1" class="w2p-checkbox" <?php echo (in_array($image_size, array_keys($options['watermark_on'])) ? ' checked="checked"' : ''); ?> />
                                    <span><?php echo $image_size; ?></span>
                                </label>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="w2p-form-row">
                    <div class="w2p-form-label"><?php _e('Post Types', 'wp-genius'); ?></div>
                    <div class="w2p-form-control">
                        <?php
                        $watermark_cpt_on = $options['watermark_cpt_on'];
                        $post_types_selected = array_keys($options['watermark_cpt_on']);
                        $everywhere = (in_array('everywhere', $watermark_cpt_on) && count($watermark_cpt_on) === 1);
                        ?>
                        <div class="w2p-flex w2p-gap-md" style="margin-bottom: 15px;">
                            <label class="w2p-flex w2p-items-center w2p-gap-xs"><input id="df_option_everywhere" type="radio" name="iw_options[watermark_cpt_on]" value="everywhere" <?php checked($everywhere, true); ?>/><span><?php _e('Everywhere', 'wp-genius'); ?></span></label>
                            <label class="w2p-flex w2p-items-center w2p-gap-xs"><input id="df_option_cpt" type="radio" name="iw_options[watermark_cpt_on]" value="specific" <?php checked($everywhere, false); ?> /><span><?php _e('Specific Post Types', 'wp-genius'); ?></span></label>
                        </div>

                        <div id="cpt-select" class="w2p-flex-wrap w2p-gap-sm" style="<?php echo ($everywhere ? 'display: none;' : ''); ?>">
                            <?php
                            $post_types = array_merge(['post', 'page'], get_post_types(['_builtin' => false], 'names'));
                            foreach ($post_types as $cpt) {
                                ?>
                                <label class="w2p-flex w2p-items-center w2p-gap-xs" style="min-width: 140px; background: var(--w2p-bg-surface-secondary); padding: 5px 10px; border-radius: var(--w2p-radius-sm); margin-bottom: 5px;">
                                    <input name="iw_options[watermark_cpt_on_type][<?php echo $cpt; ?>]" type="checkbox" value="1" class="w2p-checkbox" <?php echo (in_array($cpt, $post_types_selected) ? ' checked="checked"' : ''); ?> />
                                    <span><?php echo $cpt; ?></span>
                                </label>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security & Backup -->
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php _e('Image Protection', 'wp-genius'); ?></h4>
            </div>
            <div class="w2p-section-body">
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                         <label><?php _e('Right-click Protection', 'wp-genius'); ?></label>
                    </div>
                    <div class="w2p-form-control">
                        <label class="w2p-switch">
                            <input type="checkbox" name="iw_options[image_protection][rightclick]" value="1" <?php checked(!empty($options['image_protection']['rightclick']), 1); ?> />
                            <span class="w2p-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                         <label><?php _e('Drag & Drop Protection', 'wp-genius'); ?></label>
                    </div>
                    <div class="w2p-form-control">
                        <label class="w2p-switch">
                            <input type="checkbox" name="iw_options[image_protection][draganddrop]" value="1" <?php checked(!empty($options['image_protection']['draganddrop']), 1); ?> />
                            <span class="w2p-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="w2p-form-row">
                    <div class="w2p-form-label">
                         <label><?php _e('Full Backup', 'wp-genius'); ?></label>
                    </div>
                    <div class="w2p-form-control">
                        <label class="w2p-switch">
                            <input type="checkbox" name="iw_options[backup][backup_image]" value="1" <?php checked(!empty($options['backup']['backup_image']), 1); ?> />
                            <span class="w2p-slider"></span>
                        </label>
                        <p class="description"><?php _e('Keep original image without watermark.', 'wp-genius'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="w2p-settings-actions">
            <button type="submit" name="submit" id="w2p-image-watermark-submit" class="w2p-btn w2p-btn-primary">
                <i class="fa-solid fa-floppy-disk"></i>
                <?php esc_html_e( 'Save Watermark Settings', 'wp-genius' ); ?>
            </button>
        </div>
    </form>
    <script>
        jQuery(document).ready(function($) {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('settings-updated') === 'true') {
                const $btn = $('#w2p-image-watermark-submit');
                if (window.WPGenius && WPGenius.UI) {
                    WPGenius.UI.showFeedback($btn, '<?php esc_js( __( 'Settings Saved', 'wp-genius' ) ); ?>', 'success');
                }
            }
        });
    </script>
</div>