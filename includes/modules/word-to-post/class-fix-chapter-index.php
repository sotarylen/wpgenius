<?php
/**
 * Fix Chapter Index Handler
 * 
 * @package WP_Genius
 * @subpackage Modules/WordToPost
 */

if (!defined('ABSPATH')) {
    exit;
}

class FixChapterIndex {
    
    public function __construct() {
        add_action('wp_ajax_fix_index_save_config', [$this, 'saveConfig']);
        add_action('wp_ajax_fix_index_get_total', [$this, 'getTotal']);
        add_action('wp_ajax_fix_index_scan', [$this, 'scanBatch']);
        add_action('wp_ajax_fix_index_execute', [$this, 'executeBatch']);
        add_action('wp_ajax_fix_index_mark_finished', [$this, 'markFinished']);
        add_action('wp_ajax_fix_index_clear_progress', [$this, 'clearFinishedProgress']);
    }
    
    /**
     * 保存配置
     */
    public function saveConfig() {
        check_ajax_referer('fix_chapter_index', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wp-genius'));
        }
        
        $settings = [
            'target_post_type' => sanitize_text_field($_POST['target_post_type']),
            'scan_mode' => sanitize_text_field($_POST['scan_mode']),
            'novel_id' => intval($_POST['novel_id']),
            'scan_limit' => intval($_POST['scan_limit']),
            'index_format' => sanitize_text_field($_POST['index_format']),
            'index_connector' => sanitize_text_field($_POST['index_connector']),
            'auto_volume' => !empty($_POST['auto_volume']),
            'batch_size' => intval($_POST['batch_size'])
        ];
        
        update_option('w2p_fix_chapter_index_settings', $settings);
        wp_send_json_success(['message' => __('Configuration saved', 'wp-genius')]);
    }
    
    /**
     * 获取扫描总数
     */
    public function getTotal() {
        global $wpdb;
        check_ajax_referer('fix_chapter_index', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wp-genius'));
        }
        
        $post_type = 'chapter';
        $scan_mode = sanitize_text_field($_POST['scan_mode']);
        $novel_id = !empty($_POST['novel_id']) ? intval($_POST['novel_id']) : 0;
        $scan_limit = intval($_POST['scan_limit']);
        
        // 获取已完成书籍列表
        $finished_ids = get_option('w2p_fix_index_finished_books', []);
        
        // 使用原生 SQL 计数，避免 WP_Query 加载海量数据
        $sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p";
        $where = ["p.post_type = '{$post_type}'", "p.post_status != 'trash'"];
        $join = "";

        // 按书籍扫描
        if ($scan_mode === 'by_novel') {
            $join .= " INNER JOIN {$wpdb->postmeta} pm_novel ON p.ID = pm_novel.post_id AND pm_novel.meta_key = 'related_novel_id'";
            if ($novel_id > 0) {
                $where[] = $wpdb->prepare("pm_novel.meta_value = %d", $novel_id);
            } else if ($scan_limit > 0) {
                $novel_ids = $this->getRecentNovelIds($scan_limit);
                if (!empty($novel_ids)) {
                    $ids_str = implode(',', array_map('intval', $novel_ids));
                    $where[] = "pm_novel.meta_value IN ($ids_str)";
                } else {
                    $where[] = "1=0"; // 没有找到书籍，总数为0
                }
            }
        }

        // 排除已处理的书籍（仅在全量扫描模式下）
        if (!empty($finished_ids) && $scan_mode === 'all') {
            $join .= " LEFT JOIN {$wpdb->postmeta} pm_finished ON p.ID = pm_finished.post_id AND pm_finished.meta_key = 'related_novel_id'";
            $ids_str = implode(',', array_map('intval', $finished_ids));
            $where[] = "(pm_finished.meta_value IS NULL OR pm_finished.meta_value NOT IN ($ids_str))";
        }

        $query_sql = $sql . $join . " WHERE " . implode(" AND ", $where);
        $total = intval($wpdb->get_var($query_sql));

        wp_send_json_success([
            'total' => $total,
            'finished_count' => count($finished_ids)
        ]);
    }
    
    /**
     * 扫描批次 - 只预览，不写入数据库
     */
    public function scanBatch() {
        check_ajax_referer('fix_chapter_index', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wp-genius'));
        }
        
        // CRITICAL: Force chapter post type
        $post_type = 'chapter';
        $scan_mode = sanitize_text_field($_POST['scan_mode']);
        $novel_id = !empty($_POST['novel_id']) ? intval($_POST['novel_id']) : 0;
        $scan_limit = intval($_POST['scan_limit']);
        $batch_size = intval($_POST['batch_size']);
        $offset = intval($_POST['offset']);
        
        // 获取已完成书籍列表
        $finished_ids = get_option('w2p_fix_index_finished_books', []);
        
        // 构建查询参数
        $query_args = [
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
            // CRITICAL: 只获取ID和title，排除post_content避免内存溢出
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];
        
        $meta_query = [];
        
        // 排除已处理的书籍 (仅在全量扫描模式下)
        if (!empty($finished_ids) && $scan_mode === 'all') {
            $meta_query[] = [
                'key' => 'related_novel_id',
                'value' => $finished_ids,
                'compare' => 'NOT IN'
            ];
        }
        
        // 按书籍扫描
        if ($scan_mode === 'by_novel') {
            if ($novel_id > 0) {
                // 指定书籍ID
                $meta_query[] = [
                    'key' => 'related_novel_id',
                    'value' => $novel_id,
                    'compare' => '='
                ];
            } else if ($scan_limit > 0) {
                // 扫描最新N本书
                $novel_ids = $this->getRecentNovelIds($scan_limit);
                if (!empty($novel_ids)) {
                    $meta_query[] = [
                        'key' => 'related_novel_id',
                        'value' => $novel_ids,
                        'compare' => 'IN'
                    ];
                }
            }
        }
        
        if (!empty($meta_query)) {
            $query_args['meta_query'] = $meta_query;
        }
        
        // 查询chapter文章 (只获取IDs)
        $query = new WP_Query($query_args);
        
        $logs = [];
        $format = sanitize_text_field($_POST['index_format']);
        $connector = sanitize_text_field($_POST['index_connector']);
        $auto_volume = !empty($_POST['auto_volume']);
        
        // 解析context
        $context = [];
        if (!empty($_POST['context'])) {
            $context = json_decode(stripslashes($_POST['context']), true);
        }
        
        $vol_idx = isset($context['vol_idx']) ? intval($context['vol_idx']) : 1;
        $chap_idx = isset($context['chap_idx']) ? intval($context['chap_idx']) : 1;
        $vol_name = isset($context['vol_name']) ? $context['vol_name'] : '';
        $last_novel_id = isset($context['last_novel_id']) ? intval($context['last_novel_id']) : 0;
        
        $finished_novel_id = 0;
        // 遍历IDs并单独获取标题（避免一次性加载大量内容）
        foreach ($query->posts as $post_id) {
            // 获取书籍ID
            $current_novel_id = intval(get_post_meta($post_id, 'related_novel_id', true));
            
            // 跨书籍检测：如果 novel_id 变了
            if ($last_novel_id > 0 && $current_novel_id != $last_novel_id) {
                if (!empty($logs)) {
                    // 如本批次已有处理过的章节，则在此截断处理，留待下一批次
                    // 同时标记上一个书籍已完成
                    $finished_novel_id = $last_novel_id;
                    break;
                }
                // 如果本批次开头就是新书籍，或者跨书籍，重置识别状态
                $vol_idx = 1;
                $chap_idx = 1;
                $vol_name = '';
            }
            $last_novel_id = $current_novel_id;

            $title = get_the_title($post_id);
            $current_vol_idx = $vol_idx;
            $current_chap_idx = $chap_idx;
            
            // 识别分卷 - 只有同时包含"第x卷"和"第x章"时才提取分卷名
            // 格式：第七卷朝天子·第一百五十一章 或 第七卷 朝天子 第一百五十一章
            if ($auto_volume && preg_match('/第\s*(.+?)\s*卷\s*(.+?)\s*第\s*(.+?)\s*[章节回话]/u', $title, $m)) {
                // 标题格式：第x卷[空格]卷名[空格]第x章 章名
                $vol_num_str = trim($m[1]);
                $vol_idx = $this->parseNumber($vol_num_str);
                
                // 清理卷名：移除开头和结尾的特殊字符（如 ·、-、空格等）
                $raw_vol_name = trim($m[2]);
                $cleaned_vol_name = preg_replace('/^[·\s\-_:：|]+|[·\s\-_:：|]+$/u', '', $raw_vol_name);
                
                $vol_name = '第' . $vol_num_str . '卷 ' . $cleaned_vol_name;
                $chap_idx = 1;
                
                // 提取章节号
                $chapter_num = $this->parseNumber(trim($m[3]));
                if ($chapter_num !== null) {
                    $chap_idx = $chapter_num;
                }
                
                $current_vol_idx = $vol_idx;
                $current_chap_idx = $chap_idx;
            } else {
                // 识别章节号
                $chapter_num = $this->extractChapterNumber($title);
                
                if ($chapter_num === -1) {
                    // 楔子、序章、前言等章节 -> 强制分卷为 0
                    $current_vol_idx = 0;
                } else if ($chapter_num !== null) {
                    $chap_idx = $chapter_num;
                    $current_vol_idx = $vol_idx;
                    $current_chap_idx = $chap_idx;
                } else {
                    $current_vol_idx = $vol_idx;
                    $current_chap_idx = $chap_idx;
                }
            }
            
            // 生成序号
            $parts = explode('-', $format);
            $vol_pad = strlen($parts[0]);
            $chap_pad = isset($parts[1]) ? strlen($parts[1]) : 5;
            
            $index = str_pad($current_vol_idx, $vol_pad, '0', STR_PAD_LEFT) . 
                     $connector . 
                     str_pad($current_chap_idx, $chap_pad, '0', STR_PAD_LEFT);
            
            $logs[] = [
                'post_id' => $post_id,
                'index' => $index,
                'volume' => ($current_vol_idx === 0) ? __('Preface/Related', 'wp-genius') : ($vol_name ?: '-'),
                'title' => mb_strimwidth($title, 0, 60, '...'),
                'edit_link' => get_edit_post_link($post_id)
            ];
            
            $chap_idx = $current_chap_idx + 1;
            
            // 局部清理，防止 15 万条数据下即便分批也可能累积的缓存压力
            clean_post_cache($post_id);
        }
        
        // 扫完一批，显式回收
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // 检查当前书籍是否已完成
        // 如果本批次处理了章节，且当前有 last_novel_id
        if ($finished_novel_id == 0 && $last_novel_id > 0 && !empty($logs)) {
            // 检查下一批次是否还有该书籍的章节
            $next_offset = $offset + $batch_size;
            $check_args = $query_args;
            $check_args['posts_per_page'] = 1;
            $check_args['offset'] = $next_offset;
            
            $check_query = new WP_Query($check_args);
            
            if ($check_query->have_posts()) {
                $next_post_id = $check_query->posts[0];
                $next_novel_id = intval(get_post_meta($next_post_id, 'related_novel_id', true));
                
                // 如果下一个章节不属于当前书籍，说明当前书籍已完成
                if ($next_novel_id != $last_novel_id) {
                    $finished_novel_id = $last_novel_id;
                }
            } else {
                // 没有下一批次了，当前书籍已完成
                $finished_novel_id = $last_novel_id;
            }
        }
        
        wp_send_json_success([
            'count' => count($logs),
            'logs' => $logs,
            'finished_novel_id' => $finished_novel_id,
            'context' => [
                'vol_idx' => $vol_idx,
                'chap_idx' => $chap_idx,
                'vol_name' => $vol_name,
                'last_novel_id' => $last_novel_id
            ]
        ]);
    }
    
    /**
     * 标记书籍已处理完成
     */
    public function markFinished() {
        check_ajax_referer('fix_chapter_index', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wp-genius'));
        }
        
        $novel_id = intval($_POST['novel_id']);
        if ($novel_id <= 0) {
            wp_send_json_error('Invalid novel ID');
        }
        
        $finished_ids = get_option('w2p_fix_index_finished_books', []);
        if (!in_array($novel_id, $finished_ids)) {
            $finished_ids[] = $novel_id;
            update_option('w2p_fix_index_finished_books', $finished_ids);
        }
        
        wp_send_json_success(['message' => 'Book marked as finished']);
    }
    
    /**
     * 清除处理进度
     */
    public function clearFinishedProgress() {
        check_ajax_referer('fix_chapter_index', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wp-genius'));
        }
        
        delete_option('w2p_fix_index_finished_books');
        wp_send_json_success(['message' => __('Progress cleared', 'wp-genius')]);
    }
    
    /**
     * 获取最新的N本书的ID列表
     */
    private function getRecentNovelIds($limit) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT DISTINCT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'related_novel_id'
            AND meta_value != ''
            ORDER BY meta_id DESC
            LIMIT %d
        ", $limit);
        
        $results = $wpdb->get_col($query);
        return array_map('intval', $results);
    }
    
    /**
     * 执行批次 - 实际写入数据库
     */
    public function executeBatch() {
        check_ajax_referer('fix_chapter_index', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wp-genius'));
        }
        
        // 从POST获取扫描结果
        $scan_results = json_decode(stripslashes($_POST['scan_results']), true);
        
        if (empty($scan_results)) {
            wp_send_json_error(__('No scan results to execute', 'wp-genius'));
        }
        
        $updated = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($scan_results as $item) {
            $post_id = intval($item['post_id']);
            $index = sanitize_text_field($item['index']);
            $volume = isset($item['volume']) ? sanitize_text_field($item['volume']) : '';
            
            // 验证文章是否存在
            if (!get_post($post_id)) {
                $failed++;
                $errors[] = "Post ID {$post_id} not found";
                continue;
            }
            
            $success = true;
            
            // 强制使用原生 post_meta 方式（绕过 ACF）
            $result_index = update_post_meta($post_id, 'chapter_index', $index);
            
            if (!empty($volume) && $volume !== '-') {
                update_post_meta($post_id, 'volume_name', $volume);
            }
            
            if ($success) {
                $updated++;
                
                // 防止内存累积
                if ($updated % 50 === 0) {
                    clean_post_cache($post_id);
                }
            } else {
                $failed++;
            }
        }
        
        // 批次结束，触发垃圾回收
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // 返回详细结果
        $message = sprintf(__('Updated %d chapters', 'wp-genius'), $updated);
        if ($failed > 0) {
            $message .= sprintf(__(', %d failed', 'wp-genius'), $failed);
        }
        
        wp_send_json_success([
            'updated' => $updated,
            'failed' => $failed,
            'total' => count($scan_results),
            'message' => $message,
            'errors' => $errors,
            'debug' => [
                'acf_available' => function_exists('update_field'),
                'sample_data' => !empty($scan_results) ? $scan_results[0] : null
            ]
        ]);
    }
    
    /**
     * 从标题中提取章节号
     * 返回 -1 表示前置章节（楔子、前言等）
     */
    private function extractChapterNumber($title) {
        // 前置章节：楔子、序章、前言、简介、人物介绍等 -> 映射为 00-xxxxx
        if (preg_match('/(楔子|序章|前言|简介|内容简介|人物介绍|作品相关)/u', $title)) {
            return -1;
        }

        // 特殊章节：尾声、后记、完结感言等 -> 99999
        if (preg_match('/(尾声|后记|完结感言|后续|终章)/u', $title)) {
            return 99999;
        }
        
        // 格式1: 纯数字开头 "233 回归"
        if (preg_match('/^(\d+)\s/u', $title, $m)) {
            return intval($m[1]);
        }
        
        // 格式2: 数字、顿号开头 "235、出发"
        if (preg_match('/^(\d+)、/u', $title, $m)) {
            return intval($m[1]);
        }
        
        // 格式3: 第X章/节/回/话
        if (preg_match('/第\s*(.+?)\s*[章节回话]/u', $title, $m)) {
            return $this->parseNumber(trim($m[1]));
        }
        
        // 格式4: 包含分卷的（已在上层处理，这里作为备用）
        if (preg_match('/卷.*?第\s*(.+?)\s*[章节回话]/u', $title, $m)) {
            return $this->parseNumber(trim($m[1]));
        }
        
        // 番外章节处理
        if (preg_match('/番外/u', $title)) {
            // 番外1, 番外2 -> 提取数字
            if (preg_match('/番外\s*(\d+)/u', $title, $m)) {
                // 返回99000 + 番外序号，例如番外1 = 99001
                return 99000 + intval($m[1]);
            }
            // 番外第一章, 番外第二章
            if (preg_match('/番外\s*第\s*(.+?)\s*[章节]/u', $title, $m)) {
                $num = $this->parseNumber(trim($m[1]));
                return 99000 + $num;
            }
            // 单纯的"番外" -> 99001
            return 99001;
        }
        
        return null;
    }
    
    /**
     * 解析数字
     */
    private function parseNumber($str) {
        $str = str_replace(' ', '', $str);
        
        // 直接是数字
        if (is_numeric($str)) {
            return intval($str);
        }
        
        // 大写中文数字映射到小写
        $upper_to_lower = [
            '壹' => '一', '贰' => '二', '叁' => '三', '肆' => '四', '伍' => '五',
            '陆' => '六', '柒' => '七', '捌' => '八', '玖' => '九', '拾' => '十',
            '佰' => '百', '仟' => '千', '萬' => '万'
        ];
        
        // 转换大写为小写
        $str = strtr($str, $upper_to_lower);
        
        // 中文数字映射
        $digits = [
            '零' => 0, '〇' => 0,
            '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4,
            '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9
        ];
        
        $units = [
            '十' => 10,
            '百' => 100,
            '千' => 1000,
            '万' => 10000
        ];
        
        // 单个字符检查
        if (mb_strlen($str) === 1) {
            if (isset($digits[$str])) {
                return $digits[$str];
            }
            if (isset($units[$str])) {
                return $units[$str];
            }
        }
        
        // 解析中文数字
        $total = 0;
        $current = 0;
        $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($chars as $char) {
            if (isset($digits[$char])) {
                $current = $digits[$char];
            } else if (isset($units[$char])) {
                $unit_value = $units[$char];
                
                if ($current === 0) {
                    $current = 1; // "十" -> 10, "百" -> 100
                }
                
                if ($unit_value >= 10000) {
                    // 万
                    $total = ($total + $current) * $unit_value;
                    $current = 0;
                } else {
                    // 十、百、千
                    $total += $current * $unit_value;
                    $current = 0;
                }
            }
        }
        
        $total += $current;
        return $total > 0 ? $total : null;
    }
}

// 初始化
new FixChapterIndex();
