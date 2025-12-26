<?php
if (!defined('ABSPATH')) {
    exit;
}

// 获取模块实例
$module = W2P_Image_Watermark::instance();
$options = $module->options;
?>

<div class="w2p-module-settings" id="w2p-module-settings-image-watermark">
    <div class="w2p-module-header">
        <h2><?php _e('Image Watermark Settings', 'wp-genius'); ?></h2>
        <a href="#" class="w2p-module-toggle w2p-module-toggle-button" data-target="w2p-module-settings-image-watermark">
            <?php _e('Toggle', 'wp-genius'); ?>
        </a>
    </div>
    
    <div class="w2p-module-content">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('word2posts_save_module_settings', 'word2posts_module_nonce'); ?>
            <input type="hidden" name="action" value="word2posts_save_module_settings" />
            <input type="hidden" name="module_id" value="image-watermark" />
            <input type="hidden" name="iw_options" value="1" />
            
            <table class="form-table">
                <!-- General settings -->
                <tr>
                    <th scope="row"><?php _e('Automatic watermarking', 'wp-genius'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="iw_options[watermark_image][plugin_off]" value="1" <?php checked(!empty($options['watermark_image']['plugin_off']), 1); ?> />
                            <?php _e('Enable watermark for uploaded images.', 'wp-genius'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Manual watermarking', 'wp-genius'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="iw_options[watermark_image][manual_watermarking]" value="1" <?php checked(!empty($options['watermark_image']['manual_watermarking']), 1); ?> />
                            <?php _e('Enable Apply Watermark option for Media Library images.', 'wp-genius'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Watermark image', 'wp-genius'); ?></th>
                    <td>
                        <div class="iw_watermark_image">
                            <input id="iw_upload_image" type="hidden" name="iw_options[watermark_image][url]" value="<?php echo (int) $options['watermark_image']['url']; ?>" />
                            <input id="iw_upload_image_button" type="button" class="button button-secondary" value="<?php _e('Select image', 'wp-genius'); ?>" />
                            <input id="iw_turn_off_image_button" type="button" class="button button-secondary" value="<?php _e('Remove image', 'wp-genius'); ?>" <?php echo empty($options['watermark_image']['url']) ? 'disabled="disabled"' : ''; ?>/>
                            <p class="description"><?php _e('You have to save changes after the selection or removal of the image.', 'wp-genius'); ?></p>
                        </div>
                        
                        <div id="previewImg_imageDiv">
                            <?php if (!empty($options['watermark_image']['url'])): ?>
                                <?php 
                                $image = wp_get_attachment_image_src($options['watermark_image']['url'], [300, 300], false);
                                if ($image): 
                                ?>
                                    <img id="previewImg_image" src="<?php echo $image[0]; ?>" alt="" width="300" />
                                <?php else: ?>
                                    <img id="previewImg_image" src="" alt="" width="300" style="display: none;" />
                                <?php endif; ?>
                            <?php else: ?>
                                <img id="previewImg_image" src="" alt="" width="300" style="display: none;" />
                            <?php endif; ?>
                        </div>
                        <p id="previewImageInfo" class="description">
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
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Watermark size', 'wp-genius'); ?></th>
                    <td>
                        <fieldset id="iw_watermark_size">
                            <div id="watermark-type">
                                <label><input type="radio" id="type1" value="0" name="iw_options[watermark_image][watermark_size_type]" <?php checked($options['watermark_image']['watermark_size_type'], 0); ?> /> <?php _e('original', 'wp-genius'); ?></label>
                                <label><input type="radio" id="type2" value="1" name="iw_options[watermark_image][watermark_size_type]" <?php checked($options['watermark_image']['watermark_size_type'], 1); ?> /> <?php _e('custom', 'wp-genius'); ?></label>
                                <label><input type="radio" id="type3" value="2" name="iw_options[watermark_image][watermark_size_type]" <?php checked($options['watermark_image']['watermark_size_type'], 2); ?> /> <?php _e('scaled', 'wp-genius'); ?></label>
                            </div>
                            <p class="description"><?php _e('Select method of aplying watermark size.', 'wp-genius'); ?></p>
                        </fieldset>
                    </td>
                </tr>

                <tr class="iw-watermark-size-custom" style="display: none;">
                    <th scope="row"><?php _e('Watermark custom size', 'wp-genius'); ?></th>
                    <td>
                        <fieldset id="iw_watermark_size_custom">
                            <?php _e('x:', 'wp-genius'); ?> <input type="text" size="5" class="small-text" name="iw_options[watermark_image][absolute_width]" value="<?php echo $options['watermark_image']['absolute_width']; ?>"> <?php _e('px', 'wp-genius'); ?>
                            <br />
                            <?php _e('y:', 'wp-genius'); ?> <input type="text" size="5" class="small-text" name="iw_options[watermark_image][absolute_height]" value="<?php echo $options['watermark_image']['absolute_height']; ?>"> <?php _e('px', 'wp-genius'); ?>
                        </fieldset>
                        <p class="description"><?php _e('Those dimensions will be used if "custom" method is selected above.', 'wp-genius'); ?></p>
                    </td>
                </tr>

                <tr class="iw-watermark-size-scaled" style="display: none;">
                    <th scope="row"><?php _e('Watermark scale', 'wp-genius'); ?></th>
                    <td>
                        <fieldset id="iw_watermark_size_scaled">
                            <div>
                                <input type="text" id="iw_size_input" maxlength="3" class="small-text" name="iw_options[watermark_image][width]" value="<?php echo $options['watermark_image']['width']; ?>" />
                            </div>
                        </fieldset>
                        <p class="description"><?php _e('Enter a number ranging from 0 to 100. 100 makes width of watermark image equal to width of the image it is applied to.', 'wp-genius'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Enable watermark for', 'wp-genius'); ?></th>
                    <td>
                        <fieldset id="iw_enable_for">
                            <div id="thumbnail-select">
                                <?php
                                $image_sizes = get_intermediate_image_sizes();
                                $image_sizes[] = 'full';
                                sort($image_sizes, SORT_STRING);

                                foreach ($image_sizes as $image_size) {
                                    ?>
                                    <input name="iw_options[watermark_on][<?php echo $image_size; ?>]" type="checkbox" id="image_size_<?php echo $image_size; ?>" value="1" <?php echo (in_array($image_size, array_keys($options['watermark_on'])) ? ' checked="checked"' : ''); ?> /><label for="image_size_<?php echo $image_size; ?>"><?php echo $image_size; ?></label>
                                    <?php
                                }
                                ?>
                            </div>
                            <p class="description">
                                <?php echo __('Check the image sizes watermark will be applied to.', 'wp-genius'); ?><br />
                                <?php echo __('<strong>IMPORTANT:</strong> checking full size is NOT recommended as it\'s the original image. You may need it later - for removing or changing watermark, image sizes regeneration or any other image manipulations. Use it only if you know what you are doing.', 'wp-genius'); ?>
                            </p>

                            <?php
                            $watermark_cpt_on = $options['watermark_cpt_on'];
                            $post_types_selected = array_keys($options['watermark_cpt_on']);

                            if (in_array('everywhere', $watermark_cpt_on) && count($watermark_cpt_on) === 1) {
                                $first_checked = true;
                                $second_checked = false;
                                $watermark_cpt_on = [];
                            } else {
                                $first_checked = false;
                                $second_checked = true;
                            }
                            ?>

                            <div id="cpt-specific">
                                <input id="df_option_everywhere" type="radio" name="iw_options[watermark_cpt_on]" value="everywhere" <?php echo ($first_checked === true ? 'checked="checked"' : ''); ?>/><label for="df_option_everywhere"><?php _e('everywhere', 'wp-genius'); ?></label>
                                <input id="df_option_cpt" type="radio" name="iw_options[watermark_cpt_on]" value="specific" <?php echo ($second_checked === true ? 'checked="checked"' : ''); ?> /><label for="df_option_cpt"><?php _e('on selected post types only', 'wp-genius'); ?></label>
                            </div>

                            <div id="cpt-select" <?php echo ($second_checked === false ? 'style="display: none;"' : ''); ?>>
                                <?php
                                $post_types = array_merge(['post', 'page'], get_post_types(['_builtin' => false], 'names'));
                                foreach ($post_types as $cpt) {
                                    ?>
                                    <input name="iw_options[watermark_cpt_on_type][<?php echo $cpt; ?>]" type="checkbox" id="post_type_<?php echo $cpt; ?>" value="1" <?php echo (in_array($cpt, $post_types_selected) ? ' checked="checked"' : ''); ?> /><label for="post_type_<?php echo $cpt; ?>"><?php echo $cpt; ?></label>
                                    <?php
                                }
                                ?>
                            </div>

                            <p class="description"><?php echo __('Check custom post types on which watermark should be applied to uploaded images.', 'wp-genius'); ?></p>
                        </fieldset>
                    </td>
                </tr>

                
                
                <tr>
                    <th scope="row"><?php _e('Watermark position', 'wp-genius'); ?></th>
                    <td>
                        <fieldset id="iw_alignment">
                            <table id="watermark_position" border="1">
                                <?php
                                $watermark_positions = [
                                    'x' => ['left', 'center', 'right'],
                                    'y' => ['top', 'middle', 'bottom']
                                ];
                                $watermark_position = $options['watermark_image']['position'];
                                
                                foreach ($watermark_positions['y'] as $y) {
                                    echo '<tr>';
                                    foreach ($watermark_positions['x'] as $x) {
                                        $position = $y . '_' . $x;
                                        echo '<td title="' . ucfirst($y . ' ' . $x) . '">';
                                        echo '<input name="iw_options[watermark_image][position]" type="radio" value="' . $position . '"' . checked($watermark_position, $position, false) . ' />';
                                        echo '</td>';
                                    }
                                    echo '</tr>';
                                }
                                ?>
                            </table>
                            <p class="description"><?php _e('Select the watermark alignment.', 'wp-genius'); ?></p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Watermark padding', 'wp-genius'); ?></th>
                    <td>
                        <fieldset id="iw_padding">
                            <div style="margin-bottom: 8px;">
                                <label style="display: inline-block; width: 80px;"><?php echo __('Top:', 'wp-genius'); ?></label>
                                <input type="number" class="small-text" name="iw_options[watermark_image][padding_top]" value="<?php echo $options['watermark_image']['padding_top']; ?>"> px
                            </div>
                            <div style="margin-bottom: 8px;">
                                <label style="display: inline-block; width: 80px;"><?php echo __('Right:', 'wp-genius'); ?></label>
                                <input type="number" class="small-text" name="iw_options[watermark_image][padding_right]" value="<?php echo $options['watermark_image']['padding_right']; ?>"> px
                            </div>
                            <div style="margin-bottom: 8px;">
                                <label style="display: inline-block; width: 80px;"><?php echo __('Bottom:', 'wp-genius'); ?></label>
                                <input type="number" class="small-text" name="iw_options[watermark_image][padding_bottom]" value="<?php echo $options['watermark_image']['padding_bottom']; ?>"> px
                            </div>
                            <div style="margin-bottom: 8px;">
                                <label style="display: inline-block; width: 80px;"><?php echo __('Left:', 'wp-genius'); ?></label>
                                <input type="number" class="small-text" name="iw_options[watermark_image][padding_left]" value="<?php echo $options['watermark_image']['padding_left']; ?>"> px
                            </div>
                            <p class="description"><?php _e('Set the padding (in pixels) from each edge of the image.', 'wp-genius'); ?></p>
                        </fieldset>
                    </td>
                </tr>
                
                
                <tr>
                    <th scope="row"><?php _e('Watermark transparency / opacity', 'wp-genius'); ?></th>
                    <td>
                        <fieldset id="iw_watermark_opacity">
                            <div>
                                <input type="text" id="iw_opacity_input" maxlength="3" class="small-text" name="iw_options[watermark_image][transparent]" value="<?php echo $options['watermark_image']['transparent']; ?>" />
                            </div>
                        </fieldset>
                        <p class="description"><?php _e('Enter a number ranging from 0 to 100. 0 makes watermark image completely transparent, 100 shows it as is.', 'wp-genius'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Image quality', 'wp-genius'); ?></th>
                    <td>
                        <fieldset id="iw_image_quality">
                            <div>
                                <input type="text" id="iw_quality_input" maxlength="3" class="small-text" name="iw_options[watermark_image][quality]" value="<?php echo $options['watermark_image']['quality']; ?>" />
                            </div>
                        </fieldset>
                        <p class="description"><?php _e('Set output image quality.', 'wp-genius'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Image protection', 'wp-genius'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="iw_options[image_protection][rightclick]" value="1" <?php checked(!empty($options['image_protection']['rightclick']), 1); ?> />
                                <?php _e('Disable right mouse click on images', 'wp-genius'); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox" name="iw_options[image_protection][draganddrop]" value="1" <?php checked(!empty($options['image_protection']['draganddrop']), 1); ?> />
                                <?php _e('Prevent drag and drop', 'wp-genius'); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox" name="iw_options[image_protection][forlogged]" value="1" <?php checked(!empty($options['image_protection']['forlogged']), 1); ?> />
                                <?php _e('Enable image protection for logged-in users also', 'wp-genius'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Backup full size image', 'wp-genius'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="iw_options[backup][backup_image]" value="1" <?php checked(!empty($options['backup']['backup_image']), 1); ?> />
                            <?php _e('Backup full size image.', 'wp-genius'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save Module Settings', 'wp-genius')); ?>
        </form>
    </div>
</div>