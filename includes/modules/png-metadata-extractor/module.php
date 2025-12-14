<?php
if (!defined('ABSPATH')) {
    exit;
}

class PngMetadataExtractorModule extends W2P_Abstract_Module {
    public static function id() {
        return 'png-metadata-extractor';
    }

    public static function name() {
        return __('PNG Metadata Extractor', 'wp-genius');
    }

    public static function description() {
        return __('Extract Stable Diffusion parameters from PNG images and display them in media library and posts.', 'wp-genius');
    }

    public function init() {
        // 添加媒体库中的批量操作
        add_filter('bulk_actions-upload', array($this, 'add_bulk_action'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_action'), 10, 3);
        
        // 在媒体库中显示元数据
        add_filter('attachment_fields_to_edit', array($this, 'add_metadata_fields'), 10, 2);
        
        // 添加前端样式和脚本
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // 在文章中的图片上添加元数据显示图标
        add_filter('the_content', array($this, 'add_metadata_icon_to_images'), 20);
        
        // 添加AJAX处理程序
        add_action('wp_ajax_get_png_metadata', array($this, 'ajax_get_png_metadata'));
        add_action('wp_ajax_extract_batch_png_metadata', array($this, 'ajax_extract_batch_png_metadata'));
    }

    /**
     * 添加批量操作到媒体库
     */
    public function add_bulk_action($bulk_actions) {
        $bulk_actions['extract_png_metadata'] = __('Extract PNG Metadata', 'wp-genius');
        return $bulk_actions;
    }

    /**
     * 处理批量操作
     */
    public function handle_bulk_action($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'extract_png_metadata') {
            return $redirect_to;
        }

        $processed = 0;
        foreach ($post_ids as $post_id) {
            $attachment = get_post($post_id);
            if ($attachment && $attachment->post_type === 'attachment' && wp_attachment_is_image($post_id)) {
                $mime_type = get_post_mime_type($post_id);
                if ($mime_type === 'image/png') {
                    $this->extract_and_save_metadata($post_id);
                    $processed++;
                }
            }
        }

        $redirect_to = add_query_arg('extracted_png_metadata', $processed, $redirect_to);
        return $redirect_to;
    }

    /**
     * 提取并保存PNG元数据
     */
    private function extract_and_save_metadata($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }

        $metadata = $this->extract_png_metadata($file_path);
        if (!empty($metadata)) {
            update_post_meta($attachment_id, '_stable_diffusion_metadata', $metadata);
            return true;
        }

        return false;
    }

    /**
     * 从PNG文件中提取Stable Diffusion元数据
     */
    private function extract_png_metadata($file_path) {
        try {
            // 读取PNG文件
            $file_content = file_get_contents($file_path);
            if ($file_content === false) {
                return false;
            }

            // PNG文件头检查
            if (substr($file_content, 0, 8) !== "\x89PNG\r\n\x1a\n") {
                return false;
            }

            // 查找tEXt或iTXt块
            $metadata = array();
            $offset = 8; // 跳过PNG文件头

            while ($offset < strlen($file_content)) {
                // 读取块长度
                $length_data = substr($file_content, $offset, 4);
                if (strlen($length_data) < 4) break;
                
                $length = unpack('N', $length_data)[1];
                $offset += 4;

                // 读取块类型
                $chunk_type = substr($file_content, $offset, 4);
                $offset += 4;

                // 检查是否是tEXt或iTXt块
                if ($chunk_type === 'tEXt' || $chunk_type === 'iTXt') {
                    $chunk_data = substr($file_content, $offset, $length);
                    $offset += $length;
                    
                    // 跳过CRC
                    $offset += 4;

                    // 解析tEXt块
                    if ($chunk_type === 'tEXt') {
                        $null_pos = strpos($chunk_data, "\x00");
                        if ($null_pos !== false) {
                            $keyword = substr($chunk_data, 0, $null_pos);
                            $text = substr($chunk_data, $null_pos + 1);
                            
                            // 检查是否是Stable Diffusion参数
                            if ($keyword === 'parameters') {
                                $metadata['parameters'] = $text;
                            }
                        }
                    }
                    // 解析iTXt块
                    elseif ($chunk_type === 'iTXt') {
                        // 简化处理，只查找参数
                        if (strpos($chunk_data, 'parameters') !== false) {
                            $parts = explode("\x00", $chunk_data);
                            if (count($parts) >= 3 && $parts[0] === 'parameters') {
                                $metadata['parameters'] = $parts[2];
                            }
                        }
                    }
                } else {
                    // 跳过其他块
                    $offset += $length + 4; // +4 for CRC
                }

                // 如果是IEND块，停止处理
                if ($chunk_type === 'IEND') {
                    break;
                }
            }

            return !empty($metadata) ? $metadata : false;
        } catch (Exception $e) {
            error_log('PNG Metadata extraction error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加元数据字段到媒体库
     */
    public function add_metadata_fields($form_fields, $post) {
        if ($post->post_type !== 'attachment' || !wp_attachment_is_image($post->ID)) {
            return $form_fields;
        }

        $metadata = get_post_meta($post->ID, '_stable_diffusion_metadata', true);
        if (!empty($metadata) && isset($metadata['parameters'])) {
            $form_fields['stable_diffusion_metadata'] = array(
                'label' => __('Stable Diffusion Parameters', 'wp-genius'),
                'input' => 'html',
                'html' => '<textarea readonly style="width: 100%; height: 100px;">' . esc_textarea($metadata['parameters']) . '</textarea>',
                'show_in_edit' => true,
                'show_in_modal' => true,
            );
        }

        return $form_fields;
    }

    /**
     * 前端资源加载
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style('png-metadata-extractor', plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/style.css');
        wp_enqueue_script('png-metadata-extractor', plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/png-metadata-extractor.js', array('jquery'), null, true);
        
        wp_localize_script('png-metadata-extractor', 'pngMetadataExtractor', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('png-metadata-extractor-nonce'),
        ));
        
        wp_localize_script('png-metadata-extractor', 'pngMetadataExtractorStrings', array(
            'error_message' => __('Error loading metadata. Please try again.', 'wp-genius'),
            'extract_metadata' => __('Extract PNG Metadata', 'wp-genius'),
            'no_png_selected' => __('Please select PNG images to extract metadata.', 'wp-genius'),
            'confirm_extraction' => __('Are you sure you want to extract metadata from %d selected PNG images?', 'wp-genius'),
            'extracting' => __('Extracting metadata...', 'wp-genius'),
            'extraction_success' => __('Successfully extracted metadata from %d images.', 'wp-genius'),
            'extraction_error' => __('Error extracting metadata. Please try again.', 'wp-genius')
        ));
    }

    /**
     * 在文章中的图片上添加元数据显示图标
     */
    public function add_metadata_icon_to_images($content) {
        // 处理带有包装器的图片（如WordPress的figure或div）
        $pattern = '/(<(?:figure|div)[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>)(.*?)(<\/(?:figure|div)>)/is';
        $content = preg_replace_callback($pattern, array($this, 'replace_wrapped_image_with_metadata_icon'), $content);
        
        // 处理独立的图片标签
        $pattern = '/(<img[^>]+>)/i';
        $content = preg_replace_callback($pattern, array($this, 'replace_standalone_image_with_metadata_icon'), $content);
        
        return $content;
    }

    /**
     * 替换带有包装器的图片，添加元数据图标
     */
    private function replace_wrapped_image_with_metadata_icon($matches) {
        $opening_tag = $matches[1];
        $inner_content = $matches[2];
        $closing_tag = $matches[3];
        
        // 查找图片ID
        $image_id = 0;
        if (preg_match('/wp-image-(\d+)/i', $inner_content, $id_match)) {
            $image_id = $id_match[1];
        }
        
        // 检查是否有Stable Diffusion元数据
        if ($image_id && get_post_meta($image_id, '_stable_diffusion_metadata', true)) {
            // 添加元数据图标容器
            $metadata_html = '<div class="png-metadata-container" data-image-id="' . esc_attr($image_id) . '">';
            $metadata_html .= '<span class="png-metadata-icon" title="' . esc_attr__('View Stable Diffusion Parameters', 'wp-genius') . '">📊</span>';
            $metadata_html .= '<div class="png-metadata-popup" style="display: none;"></div>';
            $metadata_html .= '</div>';
            
            // 返回修改后的内容
            return $opening_tag . $inner_content . $metadata_html . $closing_tag;
        }
        
        return $matches[0];
    }

    /**
     * 替换独立的图片标签，添加元数据图标
     */
    private function replace_standalone_image_with_metadata_icon($matches) {
        $img_tag = $matches[1];
        
        // 提取图片ID
        $image_id = 0;
        if (preg_match('/wp-image-(\d+)/i', $img_tag, $id_match)) {
            $image_id = $id_match[1];
        }
        
        // 检查是否有Stable Diffusion元数据
        if ($image_id && get_post_meta($image_id, '_stable_diffusion_metadata', true)) {
            // 创建包装容器
            $wrapper = '<div class="png-metadata-wrapper">';
            $wrapper .= $img_tag;
            
            // 添加元数据图标容器
            $wrapper .= '<div class="png-metadata-container" data-image-id="' . esc_attr($image_id) . '">';
            $wrapper .= '<span class="png-metadata-icon" title="' . esc_attr__('View Stable Diffusion Parameters', 'wp-genius') . '">📊</span>';
            $wrapper .= '<div class="png-metadata-popup" style="display: none;"></div>';
            $wrapper .= '</div>';
            
            $wrapper .= '</div>';
            
            return $wrapper;
        }
        
        return $img_tag;
    }

    /**
     * AJAX处理程序：获取PNG元数据
     */
    public function ajax_get_png_metadata() {
        check_ajax_referer('png-metadata-extractor-nonce', 'nonce');
        
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        if (!$image_id) {
            wp_send_json_error(array('message' => __('Invalid image ID', 'wp-genius')));
        }
        
        $metadata = get_post_meta($image_id, '_stable_diffusion_metadata', true);
        if (empty($metadata)) {
            wp_send_json_error(array('message' => __('No metadata found', 'wp-genius')));
        }
        
        wp_send_json_success(array(
            'metadata' => $metadata,
            'html' => '<div class="stable-diffusion-metadata">' . 
                     '<h4>' . __('Stable Diffusion Parameters', 'wp-genius') . '</h4>' .
                     '<pre>' . esc_textarea($metadata['parameters']) . '</pre>' .
                     '</div>'
        ));
    }

    /**
     * AJAX处理程序：批量提取PNG元数据
     */
    public function ajax_extract_batch_png_metadata() {
        check_ajax_referer('png-metadata-extractor-nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-genius')));
        }
        
        $image_ids = isset($_POST['image_ids']) ? array_map('intval', $_POST['image_ids']) : array();
        if (empty($image_ids)) {
            wp_send_json_error(array('message' => __('No images selected', 'wp-genius')));
        }
        
        $processed = 0;
        foreach ($image_ids as $image_id) {
            if ($this->extract_and_save_metadata($image_id)) {
                $processed++;
            }
        }
        
        wp_send_json_success(array(
            'processed' => $processed,
            'message' => sprintf(__('Successfully extracted metadata from %d images', 'wp-genius'), $processed)
        ));
    }

    public function register_settings() {
        // 模块设置将在settings.php中实现
    }

    public function activate() {
        // 激活时的操作
    }

    public function deactivate() {
        // 停用时的操作
    }
}
?>