<?php
/**
 * Novel Manager — Importer Engine
 *
 * 小说与章节文档解析（TXT / DOCX）、两阶段预览微调与断点续传批量导入引擎。
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-novel-helper.php';

class W2P_Novel_Importer {

	/**
	 * 获取临时上传目录
	 *
	 * @return string
	 */
	public static function get_temp_dir() {
		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/wpgenius/novel-importer-temp';
		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}
		return $target_dir;
	}

	/**
	 * 获取当前未完成的断点续传任务
	 *
	 * @return array|null 存在未完成任务返回任务信息，否则返回 null
	 */
	public static function get_active_task() {
		$task = get_option( 'w2p_novel_active_import_task', null );
		if ( empty( $task ) || ! is_array( $task ) ) {
			return null;
		}

		$novel_id = isset( $task['novel_id'] ) ? absint( $task['novel_id'] ) : 0;
		if ( ! $novel_id || get_post_type( $novel_id ) !== 'novel' ) {
			delete_option( 'w2p_novel_active_import_task' );
			return null;
		}

		// 检查任务章节缓存文件是否存在
		$temp_dir  = self::get_temp_dir();
		$json_file = $temp_dir . '/task_' . sanitize_file_name( $task['task_id'] ) . '.json';
		if ( ! file_exists( $json_file ) ) {
			delete_option( 'w2p_novel_active_import_task' );
			return null;
		}

		$imported = isset( $task['imported_count'] ) ? intval( $task['imported_count'] ) : 0;
		$total    = isset( $task['total_chapters'] ) ? intval( $task['total_chapters'] ) : 0;

		if ( $imported >= $total && $total > 0 ) {
			@unlink( $json_file );
			delete_option( 'w2p_novel_active_import_task' );
			return null;
		}

		return $task;
	}

	/**
	 * 自动清理过期临时文件 (默认清理超过 12 小时的文件)
	 *
	 * @param int $max_age 过期秒数
	 */
	public static function clean_expired_temp_files( $max_age = 43200 ) {
		$temp_dir = self::get_temp_dir();
		$files    = glob( $temp_dir . '/*' );
		if ( ! empty( $files ) ) {
			$now = time();
			foreach ( $files as $file ) {
				if ( is_file( $file ) && ( $now - filemtime( $file ) ) > $max_age ) {
					@unlink( $file );
				}
			}
		}
	}

	/**
	 * 放弃并清除导入任务及对应的临时文件
	 *
	 * @param string $specified_task_id 可选指定要删除的 task_id
	 * @return bool
	 */
	public static function discard_active_task( $specified_task_id = '' ) {
		$temp_dir = self::get_temp_dir();

		if ( ! empty( $specified_task_id ) ) {
			$json_file = $temp_dir . '/task_' . sanitize_file_name( $specified_task_id ) . '.json';
			if ( file_exists( $json_file ) ) {
				@unlink( $json_file );
			}
		}

		$task = get_option( 'w2p_novel_active_import_task', null );
		if ( ! empty( $task ) && ! empty( $task['task_id'] ) ) {
			$json_file = $temp_dir . '/task_' . sanitize_file_name( $task['task_id'] ) . '.json';
			if ( file_exists( $json_file ) ) {
				@unlink( $json_file );
			}
		}
		return delete_option( 'w2p_novel_active_import_task' );
	}

	/**
	 * 处理文件上传并执行初步解析（生成预览数据，不写入数据库）
	 *
	 * @param array $file_array $_FILES 数组中的文件项
	 * @return array|WP_Error 成功返回预览数据数组，失败返回 WP_Error
	 */
	public function parse_uploaded_file( $file_array ) {
		self::clean_expired_temp_files();

		if ( empty( $file_array ) || ! isset( $file_array['tmp_name'] ) || ! is_uploaded_file( $file_array['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'No file was uploaded or file upload failed.', 'wp-genius' ) );
		}

		$filename = sanitize_file_name( $file_array['name'] );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'txt', 'docx' ), true ) ) {
			return new WP_Error( 'invalid_format', __( 'Unsupported file format. Please upload a .txt or .docx file.', 'wp-genius' ) );
		}

		$temp_dir    = self::get_temp_dir();
		$task_id     = uniqid( 'novel_' . time() . '_', false );
		$target_file = $temp_dir . '/' . $task_id . '.' . $ext;

		if ( ! move_uploaded_file( $file_array['tmp_name'], $target_file ) ) {
			return new WP_Error( 'move_failed', __( 'Failed to save uploaded temporary file.', 'wp-genius' ) );
		}

		// 解析文件内容
		if ( $ext === 'txt' ) {
			$result = $this->parse_txt_file( $target_file, $filename );
		} else {
			$result = $this->parse_docx_file( $target_file, $filename );
		}

		if ( is_wp_error( $result ) ) {
			@unlink( $target_file );
			return $result;
		}

		// 缓存解析结果供断点续传使用
		$result['task_id'] = $task_id;
		$json_file         = $temp_dir . '/task_' . $task_id . '.json';
		file_put_contents( $json_file, wp_json_encode( $result ) );

		// 原始 txt/docx 可保留或清理
		@unlink( $target_file );

		return $result;
	}

	/**
	 * 解析纯文本 TXT 文件
	 *
	 * @param string $file_path 临时文件绝对路径
	 * @param string $raw_filename 原始文件名
	 * @return array|WP_Error
	 */
	public function parse_txt_file( $file_path, $raw_filename ) {
		$content = file_get_contents( $file_path );
		if ( $content === false ) {
			return new WP_Error( 'read_error', __( 'Failed to read TXT file.', 'wp-genius' ) );
		}

		// 编码检测与转换
		$encoding = mb_detect_encoding( $content, array( 'UTF-8', 'GB18030', 'GBK', 'BIG5', 'ASCII' ), true );
		if ( $encoding && $encoding !== 'UTF-8' ) {
			$content = mb_convert_encoding( $content, 'UTF-8', $encoding );
		}

		// 去除 UTF-8 BOM
		if ( substr( $content, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$content = substr( $content, 3 );
		}

		$lines = preg_split( '/\r\n|\r|\n/u', $content );
		unset( $content );

		$novel_title = pathinfo( $raw_filename, PATHINFO_FILENAME );
		$novel_title = trim( $novel_title );

		$chapters        = array();
		$current_vol     = '正文';
		$current_vol_idx = 1;
		$current_chapter = null;
		$current_content = array();
		$chap_counter    = 1;
		$novel_intro     = '';

		foreach ( $lines as $raw_line ) {
			$line = W2P_Novel_Helper::clean_line( $raw_line );
			if ( $line === '' ) {
				continue;
			}

			// 1. 检查是否为分卷行
			$vol_info = W2P_Novel_Helper::extract_volume( $line );
			if ( $vol_info && ! W2P_Novel_Helper::is_chapter_heading( $line ) ) {
				$current_vol     = $vol_info['vol_name'];
				$current_vol_idx = $vol_info['vol_idx'];
				continue;
			}

			// 2. 检查是否为章节标题行
			if ( W2P_Novel_Helper::is_chapter_heading( $line ) ) {
				$this->flush_current_chapter( $chapters, $current_chapter, $current_content, $novel_intro );
				$this->open_chapter( $chapters, $current_chapter, $current_vol, $current_vol_idx, $chap_counter, $line, $vol_info );
			} else {
				$current_content[] = $line;
			}
		}

		// 保存最后一章；无章节时以文件名兜底为单章
		$this->flush_current_chapter( $chapters, $current_chapter, $current_content, $novel_intro );
		if ( empty( $chapters ) && ! empty( $current_content ) ) {
			$body_text  = implode( "\n\n", $current_content );
			$chapters[] = array(
				'index'         => 1,
				'title'         => $novel_title,
				'volume'        => '正文',
				'vol_idx'       => 1,
				'chap_num'      => 1,
				'chapter_index' => '01-00001',
				'content'       => $this->format_paragraphs( $body_text ),
				'word_count'    => mb_strlen( strip_tags( $body_text ), 'UTF-8' ),
			);
		}

		return $this->finalize_result( $chapters, $novel_intro, $novel_title );
	}

	/**
	 * 解析 DOCX 文件
	 *
	 * @param string $file_path 临时文件绝对路径
	 * @param string $raw_filename 原始文件名
	 * @return array|WP_Error
	 */
	public function parse_docx_file( $file_path, $raw_filename ) {
		$autoload = dirname( __DIR__ ) . '/library/vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		if ( ! class_exists( '\PhpOffice\PhpWord\IOFactory' ) ) {
			return new WP_Error( 'missing_phpword', __( 'PHPWord library is not available for parsing DOCX.', 'wp-genius' ) );
		}

		try {
			$phpWord = \PhpOffice\PhpWord\IOFactory::createReader( 'Word2007' )->load( $file_path );
		} catch ( Exception $e ) {
			/* translators: %s: error message details */
			return new WP_Error( 'docx_error', sprintf( __( 'Error reading DOCX file: %s', 'wp-genius' ), $e->getMessage() ) );
		}

		$novel_title = pathinfo( $raw_filename, PATHINFO_FILENAME );
		$novel_title = trim( $novel_title );

		$chapters        = array();
		$current_vol     = '正文';
		$current_vol_idx = 1;
		$current_chapter = null;
		$current_content = array();
		$chap_counter    = 1;
		$novel_intro     = '';

		foreach ( $phpWord->getSections() as $section ) {
			foreach ( $section->getElements() as $element ) {
				$element_class = get_class( $element );
				$text          = '';

				if ( $element_class === 'PhpOffice\PhpWord\Element\TextRun' ) {
					foreach ( $element->getElements() as $child ) {
						if ( get_class( $child ) === 'PhpOffice\PhpWord\Element\Text' ) {
							$is_bold = $child->getFontStyle() && $child->getFontStyle()->isBold();
							$text   .= $is_bold ? '<strong>' . esc_html( $child->getText() ) . '</strong>' : esc_html( $child->getText() );
						}
					}
					$style_name = $element->getParagraphStyle() ? $element->getParagraphStyle()->getStyleName() : '';
				} elseif ( $element_class === 'PhpOffice\PhpWord\Element\Title' || $element_class === 'PhpOffice\PhpWord\Element\Heading' ) {
					$text       = method_exists( $element, 'getText' ) ? (string) $element->getText() : '';
					$style_name = 'Heading';
				} elseif ( method_exists( $element, 'getText' ) ) {
					$text       = (string) $element->getText();
					$style_name = '';
				} else {
					continue;
				}

				$clean_text = W2P_Novel_Helper::clean_line( wp_strip_all_tags( $text ) );
				if ( $clean_text === '' ) {
					continue;
				}

				$is_heading = in_array( (string) $style_name, array( '1', '2', '3', 'Heading', 'Heading 1', 'Heading 2', 'Heading 3', '标题 1', '标题 2', '标题 3' ), true )
					|| W2P_Novel_Helper::is_chapter_heading( $clean_text );

				$vol_info = W2P_Novel_Helper::extract_volume( $clean_text );
				if ( $vol_info && ! W2P_Novel_Helper::is_chapter_heading( $clean_text ) ) {
					$current_vol     = $vol_info['vol_name'];
					$current_vol_idx = $vol_info['vol_idx'];
					continue;
				}

				if ( $is_heading ) {
					$this->flush_current_chapter( $chapters, $current_chapter, $current_content, $novel_intro );
					$this->open_chapter( $chapters, $current_chapter, $current_vol, $current_vol_idx, $chap_counter, $clean_text, $vol_info );
				} else {
					$current_content[] = $text;
				}
			}
		}

		// 保存最后一章
		$this->flush_current_chapter( $chapters, $current_chapter, $current_content, $novel_intro );

		return $this->finalize_result( $chapters, $novel_intro, $novel_title );
	}

	/**
	 * 将当前章节刷入章节列表；无当前章节时把前置文本作为小说简介
	 *
	 * @param array      $chapters        章节列表（引用）
	 * @param array|null $current_chapter 当前章节（引用）
	 * @param array      $current_content 当前章节内容行（引用）
	 * @param string     $novel_intro     小说简介（引用）
	 * @return void
	 */
	private function flush_current_chapter( &$chapters, &$current_chapter, &$current_content, &$novel_intro ) {
		if ( $current_chapter !== null ) {
			$body_text                     = implode( "\n\n", $current_content );
			$current_chapter['content']    = $this->format_paragraphs( $body_text );
			$current_chapter['word_count'] = mb_strlen( strip_tags( $body_text ), 'UTF-8' );
			$chapters[]                    = $current_chapter;
			$current_chapter               = null;
			$current_content               = array();
		} elseif ( empty( $chapters ) && ! empty( $current_content ) ) {
			// 章节前的文本作为小说简介
			$novel_intro     = implode( "\n\n", $current_content );
			$current_content = array();
		}
	}

	/**
	 * 根据标题开启一个新章节（提取章号、推进计数器、构建章节数组）
	 *
	 * @param array      $chapters        章节列表（引用）
	 * @param array|null $current_chapter 当前章节（引用，输出）
	 * @param string     $current_vol     当前分卷名（引用）
	 * @param int        $current_vol_idx 当前分卷序号（引用）
	 * @param int        $chap_counter    章节计数器（引用）
	 * @param string     $title           章节标题
	 * @param array|null $vol_info        分卷信息（可选）
	 * @return void
	 */
	private function open_chapter( &$chapters, &$current_chapter, &$current_vol, &$current_vol_idx, &$chap_counter, $title, $vol_info = null ) {
		// 提取章号
		$chap_num = W2P_Novel_Helper::extract_chapter_number( $title );
		if ( $chap_num === null ) {
			$chap_num = $chap_counter;
			++$chap_counter;
		} elseif ( $chap_num > 0 ) {
			$chap_counter = $chap_num + 1;
		}

		if ( $vol_info ) {
			$current_vol     = $vol_info['vol_name'];
			$current_vol_idx = $vol_info['vol_idx'];
		}

		$index_str = W2P_Novel_Helper::format_chapter_index( $current_vol_idx, $chap_num );

		$current_chapter = array(
			'index'         => count( $chapters ) + 1,
			'title'         => $title,
			'volume'        => $current_vol,
			'vol_idx'       => $current_vol_idx,
			'chap_num'      => $chap_num,
			'chapter_index' => $index_str,
			'content'       => '',
			'word_count'    => 0,
		);
	}

	/**
	 * 过滤无效章节并汇总解析结果统计
	 *
	 * @param array  $chapters    章节列表
	 * @param string $novel_intro 小说简介
	 * @param string $novel_title 小说名称
	 * @return array 汇总后的解析结果
	 */
	private function finalize_result( $chapters, $novel_intro, $novel_title ) {
		// 过滤无效伪章节（如首条为书名且字数为0，或纯空内容伪章节）
		$chapters = $this->filter_invalid_chapters( $chapters, $novel_title );

		$total_words = 0;
		$volumes_map = array();
		foreach ( $chapters as $c ) {
			$total_words                += $c['word_count'];
			$volumes_map[ $c['volume'] ] = true;
		}

		return array(
			'novel_title'    => $novel_title,
			'novel_intro'    => $novel_intro,
			'total_chapters' => count( $chapters ),
			'total_words'    => $total_words,
			'total_volumes'  => count( $volumes_map ),
			'chapters'       => $chapters,
		);
	}

	/**
	 * 过滤无效/伪章节（字数为 0 的书名行或纯空行）
	 *
	 * @param array  $chapters 原始章节数组
	 * @param string $novel_title 小说名称
	 * @return array 过滤并重新编号后的章节数组
	 */
	private function filter_invalid_chapters( $chapters, $novel_title ) {
		if ( empty( $chapters ) ) {
			return array();
		}

		$clean_novel_title = trim( preg_replace( '/^[《【\[(（\s]+|[》】\])）\s]+$/u', '', $novel_title ) );
		$filtered          = array();
		$real_idx          = 1;

		foreach ( $chapters as $c ) {
			$clean_title = trim( preg_replace( '/^[《【\[(（\s]+|[》】\])）\s]+$/u', '', $c['title'] ) );
			$word_count  = isset( $c['word_count'] ) ? intval( $c['word_count'] ) : 0;

			// 1. 如果字数为 0 且标题与小说名相同或包含小说名
			if ( $word_count === 0 && ( $clean_title === $clean_novel_title || ( mb_strlen( $clean_novel_title ) >= 2 && mb_stripos( $clean_title, $clean_novel_title ) !== false ) ) ) {
				continue;
			}

			// 2. 如果字数为 0 且内容完全为空（排除序章/楔子等有内容的章节）
			if ( $word_count === 0 && empty( trim( strip_tags( (string) $c['content'] ) ) ) ) {
				continue;
			}

			$c['index'] = $real_idx;
			$filtered[] = $c;
			++$real_idx;
		}

		return $filtered;
	}

	/**
	 * 将换行文本格式化为规范的 HTML 段落
	 *
	 * @param string $text 纯文本或混合文本
	 * @return string
	 */
	private function format_paragraphs( $text ) {
		$paragraphs = preg_split( '/\n+/u', trim( $text ) );
		$output     = '';
		foreach ( $paragraphs as $p ) {
			$p = trim( $p );
			if ( $p !== '' ) {
				$output .= '<p>' . $p . '</p>' . "\n";
			}
		}
		return $output;
	}

	/**
	 * 创建或获取 Novel CPT 文章并设置分类法、封面与自定义字段 (book_id, Status)
	 *
	 * @param array $novel_data 小说数据
	 * @return int|WP_Error 成功返回 novel_id
	 */
	public function create_or_update_novel( $novel_data ) {
		$title        = ! empty( $novel_data['title'] ) ? sanitize_text_field( $novel_data['title'] ) : '';
		$intro        = ! empty( $novel_data['content'] ) ? wp_kses_post( $novel_data['content'] ) : '';
		$author       = ! empty( $novel_data['author_id'] ) ? absint( $novel_data['author_id'] ) : get_current_user_id();
		$post_status  = ! empty( $novel_data['status'] ) ? sanitize_key( $novel_data['status'] ) : 'publish';
		$cover_id     = ! empty( $novel_data['cover_id'] ) ? absint( $novel_data['cover_id'] ) : 0;
		$novel_status = ! empty( $novel_data['novel_status'] ) ? sanitize_text_field( $novel_data['novel_status'] ) : '已完结';

		if ( empty( $title ) ) {
			return new WP_Error( 'empty_title', __( 'Novel title cannot be empty.', 'wp-genius' ) );
		}

		// 检查是否传入了已有 novel_id
		$existing_id = ! empty( $novel_data['novel_id'] ) ? absint( $novel_data['novel_id'] ) : 0;
		if ( $existing_id > 0 && get_post_type( $existing_id ) === 'novel' ) {
			$novel_id = $existing_id;
			wp_update_post(
				array(
					'ID'           => $novel_id,
					'post_title'   => $title,
					'post_excerpt' => $intro,
					'post_content' => $intro,
				)
			);
		} else {
			$novel_id = wp_insert_post(
				array(
					'post_type'    => 'novel',
					'post_title'   => $title,
					'post_excerpt' => $intro,
					'post_content' => $intro,
					'post_status'  => $post_status,
					'post_author'  => $author,
				),
				true
			);

			if ( is_wp_error( $novel_id ) ) {
				return $novel_id;
			}
		}

		// 1. 设置自定义字段 book_id (注入当前 novel 的 post_id)
		update_post_meta( $novel_id, 'book_id', (string) $novel_id );
		if ( function_exists( 'update_field' ) ) {
			update_field( 'book_id', (string) $novel_id, $novel_id );
		}

		// 2. 设置自定义字段 Status (默认为 '已完结')
		update_post_meta( $novel_id, 'Status', $novel_status );
		if ( function_exists( 'update_field' ) ) {
			update_field( 'Status', $novel_status, $novel_id );
		}

		// 3. 设置分类 (category)
		if ( isset( $novel_data['category_ids'] ) ) {
			$cat_ids = is_array( $novel_data['category_ids'] ) ? array_map( 'absint', $novel_data['category_ids'] ) : array( absint( $novel_data['category_ids'] ) );
			$cat_ids = array_filter( $cat_ids );
			if ( ! empty( $cat_ids ) ) {
				wp_set_object_terms( $novel_id, $cat_ids, 'category' );
			}
		}

		// 4. 设置标签 (post_tag) - 支持标签名数组或 ID 数组
		if ( ! empty( $novel_data['tags'] ) ) {
			$tags = is_array( $novel_data['tags'] ) ? $novel_data['tags'] : explode( ',', (string) $novel_data['tags'] );
			$tags = array_filter( array_map( 'trim', $tags ) );
			if ( ! empty( $tags ) ) {
				wp_set_post_tags( $novel_id, $tags, false );
			}
		} elseif ( ! empty( $novel_data['tag_ids'] ) ) {
			$tag_ids = is_array( $novel_data['tag_ids'] ) ? array_map( 'absint', $novel_data['tag_ids'] ) : array( absint( $novel_data['tag_ids'] ) );
			$tag_ids = array_filter( $tag_ids );
			if ( ! empty( $tag_ids ) ) {
				wp_set_object_terms( $novel_id, $tag_ids, 'post_tag' );
			}
		}

		// 5. 设置人物 (humans) - 存在复用，不存在新建
		$author_name = ! empty( $novel_data['author_name'] ) ? sanitize_text_field( $novel_data['author_name'] ) : '';
		if ( ! empty( $author_name ) ) {
			$term = get_term_by( 'name', $author_name, 'humans' );
			if ( $term ) {
				$human_id = $term->term_id;
			} else {
				$new_term = wp_insert_term( $author_name, 'humans' );
				if ( ! is_wp_error( $new_term ) && isset( $new_term['term_id'] ) ) {
					$human_id = $new_term['term_id'];
				} else {
					$human_id = 0;
				}
			}
			if ( $human_id > 0 ) {
				wp_set_object_terms( $novel_id, array( $human_id ), 'humans' );
			}
		}

		// 6. 设置封面 (特色图片)
		if ( $cover_id > 0 ) {
			set_post_thumbnail( $novel_id, $cover_id );
		}

		// 7. 初始化断点续传活动任务记录
		if ( ! empty( $novel_data['task_id'] ) && ! empty( $novel_data['total_chapters'] ) ) {
			$task_data = array(
				'task_id'        => sanitize_file_name( $novel_data['task_id'] ),
				'novel_id'       => $novel_id,
				'novel_title'    => $title,
				'total_chapters' => absint( $novel_data['total_chapters'] ),
				'imported_count' => 0,
				'status'         => 'in_progress',
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			);
			update_option( 'w2p_novel_active_import_task', $task_data );
		}

		return $novel_id;
	}

	/**
	 * 批量创建章节并推进断点续传进度
	 *
	 * @param int    $novel_id 小说 post_id
	 * @param array  $chapters 章节列表
	 * @param int    $author_id 作者 ID
	 * @param string $task_id 任务 ID
	 * @return array 导入结果统计
	 */
	public function import_chapters_batch( $novel_id, $chapters, $author_id = 0, $task_id = '' ) {
		if ( empty( $novel_id ) || get_post_type( $novel_id ) !== 'novel' ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid novel ID.', 'wp-genius' ),
			);
		}

		$author_id     = $author_id > 0 ? $author_id : get_current_user_id();
		$created_count = 0;
		$failed_count  = 0;
		$current_time  = current_time( 'mysql' );

		foreach ( $chapters as $chap ) {
			$title         = ! empty( $chap['title'] ) ? sanitize_text_field( $chap['title'] ) : '';
			$content       = ! empty( $chap['content'] ) ? wp_kses_post( $chap['content'] ) : '';
			$volume        = ! empty( $chap['volume'] ) ? sanitize_text_field( $chap['volume'] ) : '正文';
			$chapter_index = ! empty( $chap['chapter_index'] ) ? sanitize_text_field( $chap['chapter_index'] ) : '';
			$menu_order    = isset( $chap['index'] ) ? intval( $chap['index'] ) : 0;

			if ( empty( $title ) ) {
				++$failed_count;
				continue;
			}

			$chapter_id = wp_insert_post(
				array(
					'post_type'    => 'chapter',
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_author'  => $author_id,
					'menu_order'   => $menu_order,
					'post_date'    => $current_time,
				)
			);

			if ( $chapter_id && ! is_wp_error( $chapter_id ) ) {
				update_post_meta( $chapter_id, 'related_novel_id', $novel_id );
				update_post_meta( $chapter_id, 'volume_name', $volume );
				update_post_meta( $chapter_id, 'chapter_index', $chapter_index );

				if ( function_exists( 'update_field' ) ) {
					update_field( 'related_novel_id', $novel_id, $chapter_id );
					update_field( 'volume_name', $volume, $chapter_id );
					update_field( 'chapter_index', $chapter_index, $chapter_id );
				}

				clean_post_cache( $chapter_id );
				++$created_count;
			} else {
				++$failed_count;
			}
		}

		// 同步推进断点续传进度
		$active_task = get_option( 'w2p_novel_active_import_task', null );
		if ( ! empty( $active_task ) && is_array( $active_task ) && intval( $active_task['novel_id'] ) === intval( $novel_id ) ) {
			$active_task['imported_count'] = intval( $active_task['imported_count'] ) + $created_count;
			$active_task['updated_at']     = current_time( 'mysql' );

			if ( $active_task['imported_count'] >= intval( $active_task['total_chapters'] ) ) {
				// 全部导入完成，自动清理任务记录与临时缓存文件
				self::discard_active_task();
			} else {
				update_option( 'w2p_novel_active_import_task', $active_task );
			}
		}

		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		return array(
			'success' => true,
			'created' => $created_count,
			'failed'  => $failed_count,
		);
	}
}
