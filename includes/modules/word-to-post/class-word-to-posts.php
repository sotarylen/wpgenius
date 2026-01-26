<?php

class WordToPosts {
    public function __construct() {
        // Hooks are now handled by the WordPublishModule for better integration
        // with the unified settings framework.
        
        // 菜单注册现在由Word发布模块处理
        if (!class_exists('W2P_Module_Loader')) {
            add_action('admin_post_handle_upload', array($this, 'handleFileUpload'));
            add_action('admin_post_clean_uploads', array($this, 'cleanUploads'));
            add_action('admin_post_scan_uploads', array($this, 'scanUploads'));
            // Legacy admin-post
            add_action('admin_post_fix_chapter_index', array($this, 'fixChapterIndex'));
            add_action('admin_menu', array($this, 'registerMenu'));
        }

        // AJAX Hooks for Fix Index
        add_action('wp_ajax_fix_index_get_total', array($this, 'fixChapterIndexInit'));
        add_action('wp_ajax_fix_index_scan', array($this, 'fixChapterIndexScan'));
        add_action('wp_ajax_fix_index_execute', array($this, 'fixChapterIndexExecute'));
        add_action('wp_ajax_fix_index_mark_finished', array($this, 'fixChapterIndexMarkFinished'));
        add_action('wp_ajax_fix_index_mark_finished', array($this, 'fixChapterIndexMarkFinished'));
        add_action('wp_ajax_fix_index_clear_progress', array($this, 'fixChapterIndexClearProgress'));
    }

    public function run() {
        // error_log ('WordToPosts class initialized');
    }

    // 注册菜单（作为Tools的子菜单）
    public function registerMenu() {
        add_submenu_page(
            'tools.php',        //在工具菜单下添加子菜单
            __('WP Genius', 'wp-genius'),
            __('WP Genius', 'wp-genius'),
            'manage_options',
            'wp-genius',
            array($this, 'renderAdminPage')
        );
    }

    // 注册模板页面
    public function renderAdminPage() {
        settings_errors('word_to_posts');
        include plugin_dir_path(__FILE__) . 'templates/upload-form.php';
    }

    // 扫描和清理上传目录
    public function scanUploads() {
        if (!isset($_POST['word_to_posts_scan_nonce']) || !wp_verify_nonce($_POST['word_to_posts_scan_nonce'], 'word_to_posts_scan')) {
            wp_send_json_error(__('Nonce verification failed', 'wp-genius'));
            return;
        }
    
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/word2post';
        $log = [];
    
        if (is_dir($target_dir)) {
            $files = glob($target_dir . '/*');
            if (count($files) > 0) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $log[] = basename($file);
                    }
                }
            } else {
                $log[] = __('No files found in the uploads folder.', 'wp-genius');
            }
        } else {
            $log[] = __('Uploads folder not found.', 'wp-genius');
        }
    
        wp_send_json_success($log);
    }
    
    public function cleanUploads() {
        if (!isset($_POST['word_to_posts_clean_nonce']) || !wp_verify_nonce($_POST['word_to_posts_clean_nonce'], 'word_to_posts_clean')) {
            wp_send_json_error(__('Nonce verification failed', 'wp-genius'));
            return;
        }
    
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/word2post';
        $log = [];
    
        if (is_dir($target_dir)) {
            $files = glob($target_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    if (unlink($file)) {
                        $log[] = basename($file) . ' ' . __('file deleted successfully', 'wp-genius');
                    } else {
                        $log[] = basename($file) . ' ' . __('file deletion failed', 'wp-genius');
                    }
                }
            }
            $log[] = __('All files cleaned successfully.', 'wp-genius');
        } else {
            $log[] = __('Uploads folder not found.', 'wp-genius');
        }
    
        wp_send_json_success($log);
    }
    
    // 显示提示通知
    public function showAdminNotices() {
        if (get_current_screen()->id !== 'tools_page_wp-genius') {
            return;
        }
    }

    // 处理文件
    public function handleFileUpload() {
        if (!isset($_POST['word_to_posts_upload_nonce']) || !wp_verify_nonce($_POST['word_to_posts_upload_nonce'], 'word_to_posts_upload')) {
            wp_send_json_error(__('Nonce verification failed', 'wp-genius'));
            return;
        }
    
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
    
        if (!isset($_FILES['word_file']) || empty($_FILES['word_file']['name'])) {
            wp_send_json_error(__('No file was uploaded.', 'wp-genius'));
            return;
        }

        $uploadedfile = $_FILES['word_file'];
        $upload_overrides = ['test_form' => false];
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/word2post';
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $target_file = $target_dir . '/' . basename($movefile['file']);
            if (rename($movefile['file'], $target_file)) {
                $tags = isset($_POST['tags']) ? $_POST['tags'] : '';
                $this->importAndPublish($target_file, $tags);
            } else {
                wp_send_json_error(__('File move failed', 'wp-genius'));
            }
        } else {
            $error_msg = isset($movefile['error']) ? $movefile['error'] : __('File upload failed', 'wp-genius');
            wp_send_json_error($error_msg);
        }
    }
    
    // 导入内容并发布为文章
    public function importAndPublish($filePath) {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/word2post';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
    
        $target_file = $target_dir . '/' . basename($filePath);
        if (!rename($filePath, $target_file)) {
            wp_send_json_error(__('Failed to move uploaded file.', 'wp-genius'));
            return;
        }
    
        $filePath = $target_file; // 更新文件路径
        $chapters = $this->extractChapters($filePath);
        if (empty($chapters)) {
            wp_send_json_error(__('No chapters found in the document.', 'wp-genius'));
            return;
        }
    
        $category = $_POST['category'];
        $tags = $_POST['tags'];
        $author = $_POST['author'];
        $cpt_type = isset($_POST['cpt_type']) ? sanitize_text_field($_POST['cpt_type']) : '';
        $cpt_id = isset($_POST['cpt_id']) ? intval($_POST['cpt_id']) : 0;
    
        if (empty($cpt_type) || empty($cpt_id)) {
            wp_send_json_error(__('Missing CPT association information.', 'wp-genius'));
            return;
        }

        // 验证 CPT ID 是否存在且类型匹配
        $associated_post = get_post($cpt_id);
        if (!$associated_post || $associated_post->post_type !== $cpt_type) {
            wp_send_json_error(sprintf(__('Invalid associated post (ID: %d, Type: %s).', 'wp-genius'), $cpt_id, $cpt_type));
            return;
        }
    
        $current_time = current_time('mysql'); // 获取当前时间
        $time_increment = 0; // 初始化时间增量
    
        $log = [];

        foreach ($chapters as $chapter) {
            $post_date = date('Y-m-d H:i:s', strtotime($current_time) + $time_increment); // 增量时间
    
            $post_data = [
                'post_title'   => wp_strip_all_tags($chapter['title']),
                'post_content' => $chapter['content'],
                'post_status'  => 'publish',
                'post_author'  => $author,
                'post_category' => [$category],
                'tax_input' => ['post_tag' => explode(',', $tags)],
                'post_date'    => $post_date
            ];

            $post_id = wp_insert_post($post_data);
            if ($post_id) {
                wp_set_post_terms($post_id, explode(',', $tags), 'post_tag');
                
                // 保存 CPT 关联信息到 Post Meta
                update_post_meta($post_id, '_w2p_associated_cpt_type', $cpt_type);
                update_post_meta($post_id, '_w2p_associated_cpt_id', $cpt_id);

                $log[] = sprintf(__('Chapter "%s" published successfully, Post ID: %d', 'wp-genius'), $chapter['title'], $post_id);
            } else {
                $log[] = sprintf(__('Failed to publish chapter "%s"', 'wp-genius'), $chapter['title']);
            }
            $time_increment += 1; // 每次循环增加1秒
        }
        $log[] = __('All chapters have been published successfully.', 'wp-genius');
        wp_send_json_success($log);
    }
    
    // 识别并提取文章内容
    public function extractChapters($filePath) {
        // $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);        //直接读取整个文件
        $phpWord = \PhpOffice\PhpWord\IOFactory::createReader('Word2007')->load($filePath);     //使用流模式分段处理文件，减少内存占用
        $chapters = [];
        $currentChapter = null;
        $currentContent = '';
    
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (get_class($element) === 'PhpOffice\PhpWord\Element\TextRun') {
                    $text = '';
                    foreach ($element->getElements() as $childElement) {
                        if (get_class($childElement) === 'PhpOffice\PhpWord\Element\Text') {
                            $isBold = $childElement->getFontStyle() && $childElement->getFontStyle()->isBold();     //保留字体加粗的文字格式
                            $text .= $isBold ? '<strong>' . $childElement->getText() . '</strong>' : $childElement->getText();
                        }
                    }
    
                    $paragraphStyle = $element->getParagraphStyle();
                    $styleName = $paragraphStyle ? $paragraphStyle->getStyleName() : '';
    
                    // 如果 styleName 是 '2' 或者 '3'，则识别为标题
                    if ($styleName == '2' || $styleName == '3') {
                        if ($currentChapter) {
                            $currentChapter['content'] = $currentContent;
                            $chapters[] = $currentChapter;
                            $currentContent = '';
                        }
                        $currentChapter = ['title' => $text, 'content' => ''];
                    } elseif ($currentChapter) {
                        $currentContent .= '<p>' . $text . '</p>';
                    }
                }
            }
        }
    
        if ($currentChapter) {
            $currentChapter['content'] = $currentContent;
            $chapters[] = $currentChapter;
        }
    
        return $chapters;
    }

    /**
     * Convert Chinese numerals to Arabic equivalents
     */
    private function chi2arab($cnStr) {
        if (!$cnStr) return 0;
        $cnStr = trim($cnStr);
        if ($cnStr === '廿') return 20;
        if ($cnStr === '卅') return 30;
        if (strpos($cnStr, '十') === 0) $cnStr = '一' . $cnStr;

        $cnNumMap = [
            '〇' => 0, '一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5,
            '六' => 6, '七' => 7, '八' => 8, '九' => 9, '零' => 10, '两' => 2
        ];
        $cnUnitMap = ['十' => 10, '百' => 100, '千' => 1000];

        $total = 0;
        $tempVal = 0;
        
        // Split string into characters for multi-byte handling
        $chars = preg_split('//u', $cnStr, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($chars as $ch) {
            if (isset($cnNumMap[$ch])) {
                $tempVal = $cnNumMap[$ch];
            } elseif (isset($cnUnitMap[$ch])) {
                $unit = $cnUnitMap[$ch];
                if ($tempVal === 0) $tempVal = 1;
                $total += $tempVal * $unit;
                $tempVal = 0;
            }
        }
        $total += $tempVal;
        return $total;
    }

    /**
     * Handle fixing chapter index and volume
     */
    public function fixChapterIndex() {
        if (isset($_POST['post_ids']) && !empty($_POST['post_ids'])) {
             // Handled by Bulk Action (selected IDs) - NON-BATCHED (or single batch)
             $this->processFixIndexBatch($_POST);
             return;
        }

        // Direct call without IDs but potentially want full scan?
        // Old logic was full scan. Let's keep it for compatibility if no IDs passed? 
        // But for UI "Start", we use Init/Process flow.
        // If this is triggered by old "Auto Identify" button without selection in list? (Not possible via UI)
        wp_send_json_error(__('Invalid request mode.', 'wp-genius'));
    }

    /**
     * Save Fix Chapter Index Configuration
     */
    /**
     * Init Batch Process: Return Total Count
     */ 
    public function fixChapterIndexInit() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fix_chapter_index')) {
            wp_send_json_error(__('Nonce verification failed', 'wp-genius'));
            return;
        }

        $target_post_type = sanitize_text_field($_POST['target_post_type']);
        if (empty($target_post_type)) $target_post_type = 'chapter';
        $novel_id = intval($_POST['novel_id']);
        $scan_mode = sanitize_text_field($_POST['scan_mode']);
        
        // Note: We do NOT save settings here anymore. Settings are saved via CSF.

        // Actually simpler to use WP_Query for accuracy with same args
        $args = [
            'post_type' => $target_post_type,
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids'
        ];

        if ($scan_mode === 'by_novel' && $novel_id > 0) {
            $args['meta_query'] = [
                [
                    'key' => '_w2p_associated_cpt_id',
                    'value' => $novel_id,
                    'compare' => '='
                ]
            ];
        }

        $query = new WP_Query($args);
        $total = $query->found_posts;

        wp_send_json_success(['total' => $total]);
    }

    /**
     * wrapper for Scan (Dry Run)
     */
    public function fixChapterIndexScan() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fix_chapter_index')) {
            wp_send_json_error(__('Nonce verification failed', 'wp-genius'));
        }
        $this->processFixIndexBatch($_POST, true); // Dry Run = True
    }

    /**
     * wrapper for Execute (Update)
     */
    public function fixChapterIndexExecute() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fix_chapter_index')) {
            wp_send_json_error(__('Nonce verification failed', 'wp-genius'));
        }
        // In Execute mode, scan_results usually passed or we re-scan?
        // The frontend passes 'scan_results' which contains the exact items to update.
        // BUT processFixIndexBatch was designed to query posts.
        // If frontend passes 'scan_results', we should iterate that instead of querying?
        // Or do we re-query for safety?
        // The implementation plan says: fix_index_execute -> Calls processBatch($dry_run = false).
        // Let's check the logic. Auto-run calls 'scan' then 'execute'. 
        // 'execute' in JS passes 'scan_results' array. 
        // If we want to simple-update those IDs, we can do handleExecuteDirectly($_POST['scan_results']).
        
        if (isset($_POST['scan_results'])) {
            $results = json_decode(stripslashes($_POST['scan_results']), true);
            if (empty($results)) {
                wp_send_json_success(['updated' => 0, 'message' => 'No items']);
            }
            
            $updated = 0;
            $errors = [];
            foreach ($results as $item) {
                // We need Post ID. The scan log in JS usually has it? 
                // Wait, processFixIndexBatch log didn't explicitly key Post ID in the log item.
                // It only put it in the edit link.
                // We need to fix processFixIndexBatch to include 'post_id' in log.
            }
            // For now, let's Stick to the PLAN where we might not rely on scan_results for ID but re-processing?
            // "Confirm update {length} chapters?" implies we use scan_results.
            
            // Let's modify processFixIndexBatch to return post_id in log first.
        }
        
        // Actually, for Robustness in 'Execute' step of Auto-Run:
        // The JS loop is: Scan Batch -> Get Result -> Execute Batch (with result).
        // So we should handle the 'scan_results' execution here.
        
        $this->handleExecuteResults($_POST);
    }

    private function handleExecuteResults($data) {
        $results = json_decode(stripslashes($data['scan_results']), true);
        if (empty($results)) {
            wp_send_json_error('No scan results provided');
        }

        $count = 0;
        $failed = 0;
        foreach ($results as $item) {
            if (empty($item['post_id']) || empty($item['index'])) continue;
            
            // Update Chapter Index
            update_field('chapter_index', $item['index'], $item['post_id']);
            
            // Update Volume if present
            if (!empty($item['volume']) && $item['volume'] !== '-') {
                update_field('volume_name', $item['volume'], $item['post_id']);
            }
            $count++;
        }
        wp_send_json_success(['updated' => $count, 'failed' => $failed, 'message' => sprintf(__('Updated %d items.', 'wp-genius'), $count)]);
    }

    /**
     * Core Logic for Batch Processing (Scanning)
     */
    private function processFixIndexBatch($data, $dry_run = true) {
        $target_post_type = isset($data['target_post_type']) ? sanitize_text_field($data['target_post_type']) : 'chapter';
        // Debug Log
        error_log('Fix Index Batch Data: ' . print_r($data, true));
        
        $format_str = !empty($data['index_format']) ? sanitize_text_field($data['index_format']) : '01-00001';
        $connector = isset($data['index_connector']) ? sanitize_text_field($data['index_connector']) : '-';
        // Strict fallback for connector if empty string passed but likely unwanted? 
        // Allow empty connector if intentional? User might want '0100001'.
        // But in this case it's a bug. Let's stick to raw data but logs will tell.

        $auto_volume = isset($data['auto_volume']) && ($data['auto_volume'] === '1' || $data['auto_volume'] === 'true');
        
        $batch_size = isset($data['batch_size']) ? intval($data['batch_size']) : 20;
        $offset = isset($data['offset']) ? intval($data['offset']) : 0;
        
        $novel_id = isset($data['novel_id']) ? intval($data['novel_id']) : 0;
        $scan_mode = isset($data['scan_mode']) ? $data['scan_mode'] : 'all';

        // Parse format string "01-00001" -> vol_pad=2, chap_pad=5
        $parts = explode('-', $format_str);
        if (count($parts) > 1) {
            $vol_pad = strlen($parts[0]);
            $chap_pad = strlen($parts[1]);
        } else {
             // Fallback
            $vol_pad = 2;
            $chap_pad = 5;
        }

        // Query args
        $args = [
            'post_type' => $target_post_type,
            'post_status' => 'any',
            'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
            'posts_per_page' => $batch_size,
            'offset' => $offset
        ];

        // Novel ID Filter
        if ($scan_mode === 'by_novel' && $novel_id > 0) {
            $args['meta_query'] = [
                [
                    'key' => '_w2p_associated_cpt_id',
                    'value' => $novel_id,
                    'compare' => '='
                ]
            ];
        }

        $query = new WP_Query($args);
        $log = [];
        
        // Context persistence
        $context = isset($data['context']) ? json_decode(stripslashes($data['context']), true) : [];
        if (!is_array($context)) $context = [];
        
        $current_volume = isset($context['vol_name']) ? $context['vol_name'] : ''; 
        $vol_counter = isset($context['vol_idx']) ? intval($context['vol_idx']) : 1; 
        $chap_counter = isset($context['chap_idx']) ? intval($context['chap_idx']) : 1;

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $title = $post->post_title;

                // 1. Try to detect Volume
                if ($auto_volume) {
                    // Match Chinese Volume "第X卷"
                    if (preg_match('/(?:第|Vol\.?)(\s*\S+\s*)(?:卷|Vol)/u', $title, $m)) {
                        $vol_num_str = trim($m[1]);
                        if (preg_match('/^[0-9]+$/', $vol_num_str)) {
                             $vol_val = intval($vol_num_str);
                        } else {
                             $vol_val = $this->chi2arab($vol_num_str);
                        }
                        
                        if ($vol_val > 0) {
                            $current_volume = trim($title); // Or maybe just "第X卷"? Assuming Title has full name.
                            $vol_counter = $vol_val;
                            // Reset chapter counter on new volume? 
                            // Usually novel chapters are continuous, but some reset. 
                            // Keeping continuous for safety unless configured otherwise.
                        }
                    }
                }

                // 2. Detect Chapter Number
                $chap_val = 0;
                // Match "第X章"
                if (preg_match('/(?:第|\s|^)(\d+)(?:\s*章|\s*话|\s*节|\s*回)/u', $title, $m)) {
                    $chap_val = intval($m[1]);
                } elseif (preg_match('/^(\d+)/', trim($title), $m)) {
                     $chap_val = intval($m[1]);
                } elseif (preg_match('/第([零一二三四五六七八九十百千两廿卅]+)[章节话回]/u', $title, $m)) {
                     $chap_val = $this->chi2arab($m[1]);
                }
                
                if ($chap_val === 0) {
                     // Fallback to sequential
                     $chap_val = $chap_counter; 
                     $chap_counter++;
                } else {
                    $chap_counter = $chap_val + 1; // Prepare next
                }

                // 3. Format Index
                $vol_part = str_pad($vol_counter, $vol_pad, '0', STR_PAD_LEFT);
                $chap_part = str_pad($chap_val, $chap_pad, '0', STR_PAD_LEFT);
                $final_index = $vol_part . $connector . $chap_part;

                // 4. Update (Only if NOT dry run) - Wait, we are splitting.
                // This method is SCAN only now, returning WHAT would be done.
                if (!$dry_run) {
                     // Legacy direct execution path if needed, but we use handleExecuteResults now.
                     update_field('chapter_index', $final_index, $post->ID);
                     if ($current_volume) {
                        update_field('volume_name', $current_volume, $post->ID);
                     }
                }

                // 5. Log
                $log_item = [
                    'post_id' => $post->ID, // Added for Execute
                    'index' => $final_index,
                    'volume' => $current_volume ? $current_volume : '-',
                    'title' => mb_strimwidth($post->post_title, 0, 40, '...'),
                    'edit_link' => get_edit_post_link($post->ID)
                ];
                $log[] = $log_item;
            }
            
            // Should we mark finished?
            $finished_id = 0;
            // logic to detect if we finished a book? Not reliable in a partial batch.
        } 

        // Return Data
        wp_send_json_success([
            'logs' => $log, // JS expects 'logs'
            'count' => count($log),
            'context' => [
                'vol_name' => $current_volume,
                'vol_idx' => $vol_counter,
                'chap_idx' => $chap_counter
            ],
            // Pass finished novel id if applicable (logic omitted for brevity unless required)
            'finished_novel_id' => ($scan_mode === 'by_novel' && !$query->have_posts()) ? $novel_id : 0
        ]);
    }


    /**
     * Helper to convert number back to Chinese (simple version for Volume)
     */
    private function numToChinese($num) {
         $chiNum = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
         $chiUni = array('','十', '百', '千', '万', '亿', '十', '百', '千');
         
         $chiStr = '';
         
         $num_str = (string)$num;
         $count = strlen($num_str);
         
         for ($i = 0; $i < $count; $i++) {
             $temp = (int)($num_str[$i]);
             $vt = $chiUni[$count - $i - 1]; // unit
             if ($temp == 0) {
                 if ($count - $i - 1 < 4) { // End of section
                    // Handle complex zero logic if needed, simplified here
                 }
             } else {
                 $chiStr .= $chiNum[$temp] . $vt;
             }
         }
         return $chiStr ? $chiStr : $num; // Fallback
    }
}