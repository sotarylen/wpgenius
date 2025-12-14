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
                    <th scope="row"><?php _e('Watermark transparency / opacity', 'wp-genius'); ?></th>
                    <td>
                        <fieldset id="iw_watermark_opacity">
                            <div>
                                <input type="text" id="iw_opacity_input" maxlength="3" class="hide-if-js" name="iw_options[watermark_image][transparent]" value="<?php echo $options['watermark_image']['transparent']; ?>" />
                                <div class="wplike-slider">
                                    <span class="left hide-if-no-js">0</span><span class="middle" id="iw_opacity_span" title="<?php echo $options['watermark_image']['transparent']; ?>"><span class="iw-current-value" style="left: <?php echo $options['watermark_image']['transparent']; ?>%;"><?php echo $options['watermark_image']['transparent']; ?></span></span><span class="right hide-if-no-js">100</span>
                                </div>
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
                                <input type="text" id="iw_quality_input" maxlength="3" class="hide-if-js" name="iw_options[watermark_image][quality]" value="<?php echo $options['watermark_image']['quality']; ?>" />
                                <div class="wplike-slider">
                                    <span class="left hide-if-no-js">0</span><span class="middle" id="iw_quality_span" title="<?php echo $options['watermark_image']['quality']; ?>"><span class="iw-current-value" style="left: <?php echo $options['watermark_image']['quality']; ?>%;"><?php echo $options['watermark_image']['quality']; ?></span></span><span class="right hide-if-no-js">100</span>
                                </div>
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