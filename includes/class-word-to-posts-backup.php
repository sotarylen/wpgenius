<?php

class WordToPosts {
    public function __construct() {
        add_action('admin_menu', array($this, 'registerMenu'));                         // 注册菜单
        add_action('admin_post_handle_upload', array($this, 'handleFileUpload'));       // 注册handleFileUpload方法
        add_action('admin_post_clean_uploads', array($this, 'cleanUploads'));           // 注册cleanUploads方法
        add_action('admin_notices', array($this, 'showAdminNotices'));                  // 注册showAdminNotices方法
    }

    public function run() {
        // error_log ('WordToPosts class initialized');
    }

    // 注册菜单（作为Tools的子菜单）
    public function registerMenu() {
        add_submenu_page(
            'tools.php', //在工具菜单下添加子菜单
            __('Word to Posts', 'wp-genius'),
            __('Word to Posts', 'wp-genius'),
            'manage_options',
            'wp-genius',
            array($this, 'renderAdminPage')
        );
    }

    // 注册模板页面
    public function renderAdminPage() {
        include plugin_dir_path(__FILE__) . 'templates/upload-form.php';
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
                $this->importAndPublish($target_file);
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
            echo '<p>' . __('Failed to move uploaded file.', 'wp-genius') . '</p>';
            return;
        }

        $chapters = $this->extractChapters($filePath);
        if (empty($chapters)) {
            echo '<p>' . __('No chapters found in the document.', 'wp-genius') . '</p>';
            return;
        }
        
        $category = $_POST['category'];
        $author = $_POST['author'];
        $customTaxonomy = $_POST['custom_taxonomy'];
        $newCustomTaxonomy = $_POST['new_custom_taxonomy'];

        if (!empty($newCustomTaxonomy)) {
            $customTaxonomy = $newCustomTaxonomy;
            $term = wp_insert_term($customTaxonomy, 'book');
            if (!is_wp_error($term)) {
                $term_id = $term['term_id'];
            } else {
                error_log('Failed to insert term: ' . $term->get_error_message());
                $term_id = null;
            }
        } else {
            $term = get_term_by('id', $customTaxonomy, 'book');
            if ($term) {
                $term_id = $term->term_id;
            } else {
                error_log('Term not found by ID, trying by name: ' . $customTaxonomy);
                $term = get_term_by('name', $customTaxonomy, 'book');
                $term_id = $term ? $term->term_id : null;
            }
        }

        if (!$term_id) {
            error_log('Invalid custom taxonomy term');
            echo '<p>' . __('Invalid custom taxonomy term.', 'wp-genius') . '</p>';
            return;
        }

        foreach ($chapters as $chapter) {
            $post_data = [
                'post_title'   => wp_strip_all_tags($chapter['title']),
                'post_content' => $chapter['content'],
                'post_status'  => 'publish',
                'post_author'  => $author,
                'post_category' => [$category],
                'tax_input' => ['book' => [$term_id]]
            ];

            $post_id = wp_insert_post($post_data);
            if ($post_id) {
                echo '<p>' . sprintf(__('Chapter "%s" published successfully, Post ID: %d', 'wp-genius'), $chapter['title'], $post_id) . '</p>';
            } else {
                echo '<p>' . sprintf(__('Failed to publish chapter "%s"', 'wp-genius'), $chapter['title']) . '</p>';
            }
            usleep(100000); // 延迟0.1秒发布
        }
        echo '<p>' . __('All chapters have been published successfully.', 'wp-genius') . '</p>';
    }

    // 清理上传目录
    public function cleanUploads() {
        if (!isset($_POST['word_to_posts_clean_nonce']) || !wp_verify_nonce($_POST['word_to_posts_clean_nonce'], 'word_to_posts_clean')) {
            error_log('Nonce verification failed');
            wp_send_json_error(['message' => __('Nonce verification failed', 'wp-genius')]);
            wp_die();
        }

        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/word2post';

        if (is_dir($target_dir)) {
            array_map('unlink', glob("$target_dir/*.*"));
            error_log('Uploads folder cleaned successfully.');
            wp_send_json_success(['message' => __('Uploads folder cleaned successfully.', 'wp-genius')]);
        } else {
            error_log('Uploads folder not found.');
            wp_send_json_error(['message' => __('Uploads folder not found.', 'wp-genius')]);
        }
        wp_die();
    }

    // 显示提示通知
    public function showAdminNotices() {
        if (get_current_screen()->id !== 'tools_page_wp-genius') {
            return;
        }

        if (isset($_SESSION['post_log'])) {
            echo '<div id="wp-genius-log-upload">';
            foreach ($_SESSION['post_log'] as $log) {
                echo '<div class="notice notice-success"><p>' . $log . '</p></div>';
            }
            echo '</div>';
            unset($_SESSION['post_log']);
        }
    }

    // 识别并提取文章内容
    // public function extractChapters($filePath) {
    //     // 加载 Word 文档
    //     $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
    //     // 初始化章节数组
    //     $chapters = [];
    //     // 初始化当前章节为 null
    //     $currentChapter = null;
    //     // 初始化是否为章节标题为 false
    //     $isChapterTitle = false;
    //     // 遍历文档的每个部分
    //     foreach ($phpWord->getSections() as $section) {
    //         // 遍历部分中的每个元素
    //         foreach ($section->getElements() as $element) {
    //             // 如果元素类型为 TextRun
    //             if (get_class($element) === 'PhpOffice\PhpWord\Element\TextRun') {
    //                 $text = '';
    //                 // 遍历 TextRun 中的子元素
    //                 foreach ($element->getElements() as $childElement) {
    //                     // 如果子元素类型为 Text
    //                     if (get_class($childElement) === 'PhpOffice\PhpWord\Element\Text') {
    //                         // 将子元素的文本拼接到 $text
    //                         $text .= $childElement->getText();
    //                     }
    //                 }
    //                 // 使用正则表达式匹配章节标题，包括阿拉伯数字
    //                 if (preg_match('/^(第[一二三四五六七八九十百千万零0-9]+[章节回卷部]|[0-9]+、|第[0-9]+章)/u', $text) && !$isChapterTitle) {
    //                     // 如果匹配成功且不是章节标题，则将当前章节添加到章节数组
    //                     if ($currentChapter) {
    //                         $chapters[] = $currentChapter;
    //                     }
    
    //                     // 初始化当前章节
    //                     $currentChapter = ['title' => $text, 'content' => ''];
    
    //                     // 设置是否为章节标题为 true
    //                     $isChapterTitle = true;
    //                 } else {
    //                     // 如果匹配失败或已经是章节标题，则将文本添加到当前章节的 content 中
    //                     if ($currentChapter) {
    //                         $currentChapter['content'] .= $text . '<br />';
    
    //                         // 设置是否为章节标题为 false
    //                         $isChapterTitle = false;
    //                     }
    //                 }
    //             }
    //         }
    //     }
    
    //     // 如果最后一个是章节，则将其添加到章节数组
    //     if ($currentChapter) {
    //         $chapters[] = $currentChapter;
    //     }
    //     // 返回章节数组
    //     return $chapters;
    // }

    // 识别并提取文章内容
    public function extractChapters($filePath) {
        // 加载 Word 文档
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
        // 初始化章节数组
        $chapters = [];
        // 初始化当前章节为 null
        $currentChapter = null;
        // 初始化是否为章节标题为 false
        $isChapterTitle = false;
        // 遍历文档的每个部分
        foreach ($phpWord->getSections() as $section) {
            // 遍历部分中的每个元素
            foreach ($section->getElements() as $element) {
                // 如果元素类型为 TextRun
                if (get_class($element) === 'PhpOffice\PhpWord\Element\TextRun') {
                    $text = '';
                    // 遍历 TextRun 中的子元素
                    foreach ($element->getElements() as $childElement) {
                        // 如果子元素类型为 Text
                        if (get_class($childElement) === 'PhpOffice\PhpWord\Element\Text') {
                            // 将子元素的文本拼接到 $text
                            $text .= $childElement->getText();
                        }
                    }
                    // 使用正则表达式匹配章节标题，包括阿拉伯数字
                    if (preg_match('/^(第[一二三四五六七八九十百千万零0-9]+[章节回卷部]|[0-9]+、|第[0-9]+章)/u', $text) && !$isChapterTitle) {
                        // 如果匹配成功且不是章节标题，则将当前章节添加到章节数组
                        if ($currentChapter) {
                            $chapters[] = $currentChapter;
                        }

                        // 初始化当前章节
                        $currentChapter = ['title' => $text, 'content' => ''];

                        // 设置是否为章节标题为 true
                        $isChapterTitle = true;
                    } else {
                        // 如果匹配失败或已经是章节标题，则将文本添加到当前章节的 content 中
                        if ($currentChapter) {
                            $currentChapter['content'] .= $text . '<br />';

                            // 设置是否为章节标题为 false
                            $isChapterTitle = false;
                        }
                    }
                }
            }
        }

        // 如果最后一个是章节，则将其添加到章节数组
        if ($currentChapter) {
            $chapters[] = $currentChapter;
        }
        // 返回章节数组
        return $chapters;
    }
    
}

?>