<?php

class WordToPosts {
    public function __construct() {
        // 注册处理程序（这些将由Word发布模块调用）
        add_action('admin_post_handle_upload', array($this, 'handleFileUpload'));       // 注册handleFileUpload方法
        add_action('admin_post_clean_uploads', array($this, 'cleanUploads'));           // 注册cleanUploads方法
        add_action('admin_post_scan_uploads', array($this, 'scanUploads'));             // 注册ScanUploads方法
        add_action('admin_notices', array($this, 'showAdminNotices'));                  // 注册showAdminNotices方法
        
        // 菜单注册现在由Word发布模块处理
        // 仅在模块框架未加载或Word发布模块禁用时提供备用菜单
        if (!class_exists('W2P_Module_Loader')) {
            add_action('admin_menu', array($this, 'registerMenu'));
        }
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
            wp_die(__('Nonce verification failed', 'wp-genius'));
        }
    
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
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
                echo '<p>' . __('File move failed', 'wp-genius') . '</p>';
            }
        } else {
            echo '<p>' . __('File upload failed', 'wp-genius') . '</p>';
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
        $customTaxonomy = $_POST['custom_taxonomy'];
        $newCustomTaxonomy = $_POST['new_custom_taxonomy'];
    
        if (!empty($newCustomTaxonomy)) {
            $customTaxonomy = $newCustomTaxonomy;
            $term = wp_insert_term($customTaxonomy, 'book');
            if (!is_wp_error($term)) {
                $term_id = $term['term_id'];
            } else {
                $term_id = null;
            }
        } else {
            $term = get_term_by('id', $customTaxonomy, 'book');
            if ($term) {
                $term_id = $term->term_id;
            } else {
                $term = get_term_by('name', $customTaxonomy, 'book');
                $term_id = $term ? $term->term_id : null;
            }
        }
    
        if (!$term_id) {
            wp_send_json_error(__('Invalid custom taxonomy term.', 'wp-genius'));
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
                'tax_input' => ['book' => [$term_id], 'post_tag' => explode(',', $tags)],
                'post_date'    => $post_date
            ];

            $post_id = wp_insert_post($post_data);
            if ($post_id) {
                wp_set_post_terms($post_id, explode(',', $tags), 'post_tag');

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
}

?>