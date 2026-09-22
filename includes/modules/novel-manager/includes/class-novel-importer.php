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
require_once __DIR__ . '/class-pdf-extractor.php';

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
	 * 安全彻底清空指定小说的所有关联章节文章及相关元数据
	 *
	 * @param int $novel_id 小说文章 ID
	 * @return int 清理的章节总数
	 */
	public static function truncate_novel_chapters( $novel_id ) {
		global $wpdb;

		$novel_id = absint( $novel_id );
		if ( $novel_id <= 0 || 'novel' !== get_post_type( $novel_id ) ) {
			return 0;
		}

		// 查询属于该小说的所有章节 ID
		$sql = $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'related_novel_id'
			 WHERE p.post_type = 'chapter' AND pm.meta_value = %d",
			$novel_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$chapter_ids = $wpdb->get_col( $sql );
		if ( empty( $chapter_ids ) ) {
			return 0;
		}

		$deleted_count = 0;
		foreach ( $chapter_ids as $chap_id ) {
			$chap_id = absint( $chap_id );
			if ( $chap_id > 0 ) {
				// force_delete = true 跳过回收站彻底删除
				wp_delete_post( $chap_id, true );
				++$deleted_count;

				if ( 0 === $deleted_count % 50 ) {
					clean_post_cache( $chap_id );
				}
			}
		}

		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		W2P_Novel_Helper::purge_novel_cache( $novel_id );

		return $deleted_count;
	}

	/**
	 * 获取指定小说最后一章的信息（包含最大 menu_order、总章节数、最后章节索引及分卷名）
	 *
	 * @param int $novel_id 小说文章 ID
	 * @return array 包含章节统计与末章元数据的数组
	 */
	public static function get_novel_last_chapter_info( $novel_id ) {
		global $wpdb;

		$novel_id = absint( $novel_id );
		if ( $novel_id <= 0 || 'novel' !== get_post_type( $novel_id ) ) {
			return array(
				'total_chapters' => 0,
				'max_order'      => 0,
				'last_index'     => '',
				'last_volume'    => '',
				'last_title'     => '',
			);
		}

		// 1. 获取现有章节总数（使用 %s 字符串类型匹配走 postmeta 索引，避免类型转换）
		$count_sql = $wpdb->prepare(
			"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'related_novel_id'
			 WHERE p.post_type = 'chapter' AND p.post_status != 'trash' AND pm.meta_value = %s",
			(string) $novel_id
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total_chapters = intval( $wpdb->get_var( $count_sql ) );

		// 2. 获取 menu_order 最大的一章（单表联查迅速定位末章记录，杜绝三重 JOIN 临时表排序卡死）
		$last_sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.menu_order
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE pm.meta_key = 'related_novel_id' AND pm.meta_value = %s
			   AND p.post_type = 'chapter' AND p.post_status != 'trash'
			 ORDER BY p.menu_order DESC, p.ID DESC
			 LIMIT 1",
			(string) $novel_id
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$last_row = $wpdb->get_row( $last_sql );

		$last_index  = '';
		$last_volume = '';
		$last_title  = '';
		$max_order   = $total_chapters;

		if ( $last_row ) {
			$max_order   = intval( $last_row->menu_order );
			$last_title  = (string) $last_row->post_title;
			$last_index  = (string) get_post_meta( $last_row->ID, 'chapter_index', true );
			$last_volume = (string) get_post_meta( $last_row->ID, 'volume_name', true );
		}

		return array(
			'total_chapters' => $total_chapters,
			'max_order'      => $max_order,
			'last_index'     => $last_index,
			'last_volume'    => $last_volume,
			'last_title'     => $last_title,
		);
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

		$allowed_exts = array( 'txt', 'docx', 'epub', 'html', 'htm', 'pdf' );
		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			return new WP_Error( 'invalid_format', __( 'Unsupported file format. Please upload a .txt, .docx, .epub, .html, or .pdf file.', 'wp-genius' ) );
		}

		$temp_dir    = self::get_temp_dir();
		$task_id     = uniqid( 'novel_' . time() . '_', false );
		$target_file = $temp_dir . '/' . $task_id . '.' . $ext;

		if ( ! move_uploaded_file( $file_array['tmp_name'], $target_file ) ) {
			return new WP_Error( 'move_failed', __( 'Failed to save uploaded temporary file.', 'wp-genius' ) );
		}

		// 解析文件内容（按扩展名自动路由到对应解析器）
		switch ( $ext ) {
			case 'txt':
				$result = $this->parse_txt_file( $target_file, $filename );
				break;
			case 'docx':
				$result = $this->parse_docx_file( $target_file, $filename );
				break;
			case 'epub':
				$result = $this->parse_epub_file( $target_file, $filename );
				break;
			case 'html':
			case 'htm':
				$result = $this->parse_html_file( $target_file, $filename );
				break;
			case 'pdf':
				$result = $this->parse_pdf_file( $target_file, $filename );
				break;
			default:
				$result = new WP_Error( 'invalid_format', __( 'Unsupported file format.', 'wp-genius' ) );
				break;
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

		return $this->parse_lines_stream( $lines, $raw_filename );
	}

	/**
	 * 解析 EPUB 电子书文件 (内存直读防 Zip Slip，LIBXML_NONET 防 XXE)
	 *
	 * @param string $file_path 临时文件绝对路径
	 * @param string $raw_filename 原始文件名
	 * @return array|WP_Error
	 */
	public function parse_epub_file( $file_path, $raw_filename ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_missing', __( 'PHP ZipArchive extension is required to parse EPUB files.', 'wp-genius' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return new WP_Error( 'epub_open_failed', __( 'Failed to open EPUB archive.', 'wp-genius' ) );
		}

		// 1. 读取 META-INF/container.xml 定位 OPF 清单路径 (内存直读)
		$container_xml = $zip->getFromName( 'META-INF/container.xml' );
		if ( empty( $container_xml ) ) {
			$zip->close();
			return new WP_Error( 'epub_invalid', __( 'Invalid EPUB: missing META-INF/container.xml.', 'wp-genius' ) );
		}

		$prev_libxml = libxml_use_internal_errors( true );
		$c_dom       = new DOMDocument();
		$c_dom->loadXML( $container_xml, LIBXML_NONET );
		$rootfiles = $c_dom->getElementsByTagName( 'rootfile' );
		$opf_path  = '';
		foreach ( $rootfiles as $rf ) {
			if ( 'application/oebps-package+xml' === $rf->getAttribute( 'media-type' ) || empty( $opf_path ) ) {
				$opf_path = $rf->getAttribute( 'full-path' );
				if ( ! empty( $opf_path ) ) {
					break;
				}
			}
		}

		if ( empty( $opf_path ) ) {
			$zip->close();
			libxml_use_internal_errors( $prev_libxml );
			return new WP_Error( 'epub_no_opf', __( 'Invalid EPUB: rootfile not found in container.', 'wp-genius' ) );
		}

		// 2. 读取并解析 OPF 元数据与资源清单
		$opf_content = $zip->getFromName( $opf_path );
		if ( empty( $opf_content ) ) {
			$zip->close();
			libxml_use_internal_errors( $prev_libxml );
			return new WP_Error( 'epub_read_opf_failed', __( 'Failed to read EPUB package document.', 'wp-genius' ) );
		}

		$opf_dir = dirname( $opf_path );
		$opf_dir = ( '.' === $opf_dir || '/' === $opf_dir ) ? '' : rtrim( $opf_dir, '/' ) . '/';

		$opf_dom = new DOMDocument();
		$opf_dom->loadXML( $opf_content, LIBXML_NONET );

		// 提取元数据 (Title, Creator, Description)
		$novel_title  = '';
		$novel_author = '';
		$novel_intro  = '';

		$titles = $opf_dom->getElementsByTagName( 'title' );
		if ( $titles->length > 0 ) {
			$novel_title = trim( $titles->item( 0 )->textContent );
		}
		$creators = $opf_dom->getElementsByTagName( 'creator' );
		if ( $creators->length > 0 ) {
			$novel_author = trim( $creators->item( 0 )->textContent );
		}
		$descriptions = $opf_dom->getElementsByTagName( 'description' );
		if ( $descriptions->length > 0 ) {
			$novel_intro = trim( $descriptions->item( 0 )->textContent );
		}

		if ( empty( $novel_title ) ) {
			$novel_title = pathinfo( $raw_filename, PATHINFO_FILENAME );
		}
		$novel_title = W2P_Novel_Helper::clean_novel_title( $novel_title, $novel_author );

		// 构建 manifest 字典: id => href
		$manifest = array();
		$items    = $opf_dom->getElementsByTagName( 'item' );
		$ncx_href = '';
		foreach ( $items as $it ) {
			$id        = $it->getAttribute( 'id' );
			$href      = $it->getAttribute( 'href' );
			$mt        = $it->getAttribute( 'media-type' );
			$full_href = $opf_dir . ltrim( rawurldecode( $href ), '/' );

			$manifest[ $id ] = $full_href;
			if ( 'application/x-dtbncx+xml' === $mt || 'ncx' === $id ) {
				$ncx_href = $full_href;
			}
		}

		// 提取 TOC 章节标题映射
		$toc_titles = array();
		if ( ! empty( $ncx_href ) ) {
			$ncx_content = $zip->getFromName( $ncx_href );
			if ( ! empty( $ncx_content ) ) {
				$ncx_dom = new DOMDocument();
				$ncx_dom->loadXML( $ncx_content, LIBXML_NONET );
				$nav_points = $ncx_dom->getElementsByTagName( 'navPoint' );
				$ncx_dir    = dirname( $ncx_href );
				$ncx_dir    = ( '.' === $ncx_dir || '/' === $ncx_dir ) ? '' : rtrim( $ncx_dir, '/' ) . '/';

				foreach ( $nav_points as $np ) {
					$text_nodes    = $np->getElementsByTagName( 'text' );
					$content_nodes = $np->getElementsByTagName( 'content' );
					if ( $text_nodes->length > 0 && $content_nodes->length > 0 ) {
						$t_val     = trim( $text_nodes->item( 0 )->textContent );
						$src       = $content_nodes->item( 0 )->getAttribute( 'src' );
						$src_clean = preg_replace( '/#.*$/', '', rawurldecode( $src ) );
						$full_src  = $ncx_dir . ltrim( $src_clean, '/' );
						if ( ! empty( $t_val ) && ! isset( $toc_titles[ $full_src ] ) ) {
							$toc_titles[ $full_src ] = $t_val;
						}
					}
				}
			}
		}

		// 读取 spine 顺序
		$spine_items = array();
		$itemrefs    = $opf_dom->getElementsByTagName( 'itemref' );
		foreach ( $itemrefs as $ir ) {
			$idref = $ir->getAttribute( 'idref' );
			if ( isset( $manifest[ $idref ] ) ) {
				$spine_items[] = $manifest[ $idref ];
			}
		}

		// 3. 遍历各 XHTML 章节提取内容
		$chapters        = array();
		$current_vol     = '正文';
		$current_vol_idx = 1;
		$chap_counter    = 1;

		foreach ( $spine_items as $chap_path ) {
			$html_src = $zip->getFromName( $chap_path );
			if ( empty( $html_src ) ) {
				continue;
			}

			$chap_dom = new DOMDocument();
			@$chap_dom->loadHTML( '<?xml encoding="UTF-8">' . $html_src, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

			$body = $chap_dom->getElementsByTagName( 'body' )->item( 0 );
			if ( ! $body ) {
				continue;
			}

			$lines = array();
			$this->extract_dom_text_lines( $body, $lines );
			if ( empty( $lines ) ) {
				continue;
			}

			// 优先使用 TOC 标题，否则取首个 h1-h6 或首行
			$chap_title = isset( $toc_titles[ $chap_path ] ) ? $toc_titles[ $chap_path ] : '';
			if ( empty( $chap_title ) ) {
				foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $htag ) {
					$hnodes = $body->getElementsByTagName( $htag );
					if ( $hnodes->length > 0 ) {
						$candidate = trim( $hnodes->item( 0 )->textContent );
						if ( ! empty( $candidate ) ) {
							$chap_title = $candidate;
							break;
						}
					}
				}
			}

			if ( empty( $chap_title ) && ! empty( $lines ) ) {
				if ( W2P_Novel_Helper::is_chapter_heading( $lines[0] ) ) {
					$chap_title = array_shift( $lines );
				}
			}

			if ( ! empty( $lines ) && ! empty( $chap_title ) && W2P_Novel_Helper::clean_line( $lines[0] ) === W2P_Novel_Helper::clean_line( $chap_title ) ) {
				array_shift( $lines );
			}

			$body_text = trim( implode( "\n\n", $lines ) );
			if ( empty( $body_text ) && empty( $chap_title ) ) {
				continue;
			}

			// 前置简介识别
			if ( empty( $novel_intro ) && mb_strlen( $body_text, 'UTF-8' ) < 800 && preg_match( '/(简介|文案|内容简介|作品简介)/u', $chap_title . ' ' . $body_text ) ) {
				$intro_ex = W2P_Novel_Helper::extract_author_and_intro( $lines, $raw_filename );
				if ( ! empty( $intro_ex['intro'] ) ) {
					$novel_intro = $intro_ex['intro'];
					continue;
				}
			}

			// 分卷识别
			$vol_info = W2P_Novel_Helper::extract_volume( $chap_title );
			if ( $vol_info ) {
				$current_vol     = $vol_info['vol_name'];
				$current_vol_idx = intval( $vol_info['vol_idx'] );
			}

			// 章号与索引
			$chap_num = W2P_Novel_Helper::extract_chapter_number( $chap_title );
			if ( null === $chap_num ) {
				$chap_num = $chap_counter++;
			} elseif ( $chap_num > 0 && $chap_num < 90000 ) {
				$chap_counter = $chap_num + 1;
			}

			if ( 99 === $current_vol_idx && $chap_num > 0 && $chap_num < 90000 ) {
				$chap_num = 99000 + $chap_num;
			}

			$chap_idx_str = W2P_Novel_Helper::format_chapter_index( $current_vol_idx, $chap_num );
			if ( empty( $chap_title ) ) {
				$chap_title = sprintf( __( 'Chapter %d', 'wp-genius' ), count( $chapters ) + 1 );
			}

			$chapters[] = array(
				'index'         => count( $chapters ) + 1,
				'title'         => $chap_title,
				'volume'        => $current_vol,
				'vol_idx'       => $current_vol_idx,
				'chap_num'      => $chap_num,
				'chapter_index' => $chap_idx_str,
				'content'       => $this->format_paragraphs( $body_text ),
				'word_count'    => mb_strlen( strip_tags( $body_text ), 'UTF-8' ),
			);
		}

		$zip->close();
		libxml_use_internal_errors( $prev_libxml );

		return $this->finalize_result( $chapters, $novel_intro, $novel_title, $novel_author );
	}

	/**
	 * 解析单文件 HTML 小说文档 (字符集转码、危险标签剥离与结构化段落抽取)
	 *
	 * @param string $file_path 临时文件绝对路径
	 * @param string $raw_filename 原始文件名
	 * @return array|WP_Error
	 */
	public function parse_html_file( $file_path, $raw_filename ) {
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return new WP_Error( 'read_error', __( 'Failed to read HTML file.', 'wp-genius' ) );
		}

		// 1. 字符集检测与转码为 UTF-8
		$charset = '';
		if ( preg_match( '/<meta[^>]+charset=["\']?([a-zA-Z0-9_\-]+)/i', $content, $cm ) ) {
			$charset = strtoupper( trim( $cm[1] ) );
		} elseif ( preg_match( '/<meta[^>]+content=["\'][^"\']*charset=([a-zA-Z0-9_\-]+)/i', $content, $cm ) ) {
			$charset = strtoupper( trim( $cm[1] ) );
		}

		if ( empty( $charset ) || 'UTF-8' !== $charset ) {
			$encoding = ! empty( $charset ) ? $charset : mb_detect_encoding( $content, array( 'UTF-8', 'GB18030', 'GBK', 'BIG5', 'ASCII' ), true );
			if ( $encoding && 'UTF-8' !== $encoding ) {
				$content = mb_convert_encoding( $content, 'UTF-8', $encoding );
			}
		}

		// 移除 UTF-8 BOM
		if ( substr( $content, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$content = substr( $content, 3 );
		}

		// 2. 剥离危险标签 (<script>, <style>, <iframe>, <noscript>)
		$content = preg_replace( '/<(?:script|style|iframe|noscript)[^>]*>.*?<\/(?:script|style|iframe|noscript)>/is', '', $content );

		$prev_libxml = libxml_use_internal_errors( true );
		$dom         = new DOMDocument();
		@$dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

		// 提取元数据
		$novel_title  = '';
		$novel_author = '';
		$novel_intro  = '';

		$titles = $dom->getElementsByTagName( 'title' );
		if ( $titles->length > 0 ) {
			$novel_title = trim( $titles->item( 0 )->textContent );
		}

		$metas = $dom->getElementsByTagName( 'meta' );
		foreach ( $metas as $m ) {
			$name = strtolower( $m->getAttribute( 'name' ) );
			$pval = $m->getAttribute( 'content' );
			if ( 'author' === $name && empty( $novel_author ) ) {
				$novel_author = trim( $pval );
			} elseif ( in_array( $name, array( 'description', 'intro' ), true ) && empty( $novel_intro ) ) {
				$novel_intro = trim( $pval );
			}
		}

		if ( empty( $novel_title ) ) {
			$novel_title = pathinfo( $raw_filename, PATHINFO_FILENAME );
		}
		$novel_title = W2P_Novel_Helper::clean_novel_title( $novel_title, $novel_author );

		// 3. 提取主体文本行序列
		$body  = $dom->getElementsByTagName( 'body' )->item( 0 );
		$lines = array();
		if ( $body ) {
			$this->extract_dom_text_lines( $body, $lines );
		} else {
			$plain = preg_replace( '/<(?:p|div|h[1-6]|br|tr)[^>]*>/i', "\n", $content );
			$plain = wp_strip_all_tags( $plain );
			$lines = preg_split( '/\r\n|\r|\n/u', $plain );
		}

		libxml_use_internal_errors( $prev_libxml );

		// 4. 复用通用章节流处理管道
		return $this->parse_lines_stream( $lines, $raw_filename, $novel_title, $novel_author, $novel_intro );
	}

	/**
	 * 解析 PDF 电子书文档（双轨加速：CLI pdftotext 优先 -> 内置纯 PHP ToUnicode CMap 流解析兜底）
	 *
	 * @param string $file_path 临时文件绝对路径
	 * @param string $raw_filename 原始文件名
	 * @return array|WP_Error
	 */
	public function parse_pdf_file( $file_path, $raw_filename ) {
		$text = '';

		// 轨道 1：优先尝试系统 CLI pdftotext (安全调用 + 函数可用性检测)
		if ( function_exists( 'exec' ) && ! in_array( 'exec', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ) ) {
			$which = @exec( 'which pdftotext 2>/dev/null' );
			if ( ! empty( $which ) && is_executable( $which ) ) {
				$out_file = $file_path . '.txt';
				$cmd      = escapeshellcmd( $which ) . ' -enc UTF-8 -layout ' . escapeshellarg( $file_path ) . ' ' . escapeshellarg( $out_file ) . ' 2>&1';
				@exec( $cmd );
				if ( file_exists( $out_file ) ) {
					$text = file_get_contents( $out_file );
					@unlink( $out_file );
				}
			}
		}

		// 轨道 2：若无 CLI，无缝使用内置纯 PHP PDF 流提取器兜底
		if ( empty( $text ) ) {
			$extractor = new W2P_Pdf_Extractor();
			$text      = $extractor->extract_text( $file_path );
		}

		if ( empty( $text ) ) {
			return new WP_Error( 'pdf_empty', __( 'Failed to extract readable text from PDF or PDF is scanned/empty.', 'wp-genius' ) );
		}

		$lines       = preg_split( '/\r\n|\r|\n/u', $text );
		$novel_title = pathinfo( $raw_filename, PATHINFO_FILENAME );
		$novel_title = W2P_Novel_Helper::clean_novel_title( $novel_title );

		return $this->parse_lines_stream( $lines, $raw_filename, $novel_title );
	}

	/**
	 * 通用文本行流解析管道 (DRY 核心复用：TXT / HTML / PDF 共享)
	 *
	 * @param array  $lines 纯文本行数组
	 * @param string $raw_filename 原始文件名
	 * @param string $novel_title 初始书名 (可选)
	 * @param string $novel_author 初始作者 (可选)
	 * @param string $novel_intro 初始简介 (可选)
	 * @return array
	 */
	public function parse_lines_stream( $lines, $raw_filename, $novel_title = '', $novel_author = '', $novel_intro = '' ) {
		if ( empty( $novel_title ) ) {
			$novel_title = pathinfo( $raw_filename, PATHINFO_FILENAME );
			$novel_title = trim( $novel_title );
		}

		$chapters        = array();
		$current_vol     = '正文';
		$current_vol_idx = 1;
		$current_chapter = null;
		$current_content = array();
		$chap_counter    = 1;

		foreach ( $lines as $raw_line ) {
			$line = W2P_Novel_Helper::clean_line( $raw_line );
			if ( $line === '' ) {
				continue;
			}

			// 1. 检查是否为分卷行（支持 Extract & Peel 双重剥离）
			$vol_info = W2P_Novel_Helper::extract_volume( $line );
			if ( $vol_info ) {
				$current_vol     = $vol_info['vol_name'];
				$current_vol_idx = $vol_info['vol_idx'];
				if ( ! empty( $vol_info['has_sub_chapter'] ) && ! empty( $vol_info['sub_chapter_line'] ) ) {
					$line = $vol_info['sub_chapter_line'];
				} else {
					continue;
				}
			}

			// 2. 检查是否为章节标题行
			if ( W2P_Novel_Helper::is_chapter_heading( $line ) ) {
				$this->flush_current_chapter( $chapters, $current_chapter, $current_content, $novel_intro, $novel_author, $raw_filename, $novel_title );
				$this->open_chapter( $chapters, $current_chapter, $current_vol, $current_vol_idx, $chap_counter, $line, $vol_info );
			} else {
				$current_content[] = $line;
			}
		}

		// 保存最后一章；无章节时以文件名兜底为单章
		$this->flush_current_chapter( $chapters, $current_chapter, $current_content, $novel_intro, $novel_author, $raw_filename, $novel_title );
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

		// 若作者仍未提取到，兜底通过文件名尝试识别
		if ( empty( $novel_author ) ) {
			$fn_extracted = W2P_Novel_Helper::extract_author_and_intro( array(), $raw_filename );
			if ( ! empty( $fn_extracted['author'] ) ) {
				$novel_author = $fn_extracted['author'];
			}
			if ( ! empty( $fn_extracted['title'] ) && $novel_title === pathinfo( $raw_filename, PATHINFO_FILENAME ) ) {
				$novel_title = $fn_extracted['title'];
			}
		}

		return $this->finalize_result( $chapters, $novel_intro, $novel_title, $novel_author );
	}

	/**
	 * 递归遍历 DOM 节点并提取结构化段落文本行
	 *
	 * @param DOMNode $node DOM 节点
	 * @param array   $lines 输出文本行数组（引用）
	 */
	private function extract_dom_text_lines( $node, &$lines ) {
		if ( ! $node ) {
			return;
		}

		if ( in_array( strtolower( $node->nodeName ), array( 'script', 'style', 'noscript', 'iframe' ), true ) ) {
			return;
		}

		$is_block = in_array( strtolower( $node->nodeName ), array( 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'tr', 'li', 'blockquote', 'section', 'article' ), true );

		if ( '#text' === $node->nodeName ) {
			$val = W2P_Novel_Helper::clean_line( $node->textContent );
			if ( '' !== $val ) {
				$lines[] = $val;
			}
			return;
		}

		if ( 'br' === strtolower( $node->nodeName ) ) {
			return;
		}

		if ( $node->hasChildNodes() ) {
			if ( $is_block && ! in_array( strtolower( $node->nodeName ), array( 'div', 'section', 'article' ), true ) ) {
				$t = W2P_Novel_Helper::clean_line( wp_strip_all_tags( $node->textContent ) );
				if ( '' !== $t ) {
					$lines[] = $t;
					return;
				}
			}

			foreach ( $node->childNodes as $child ) {
				$this->extract_dom_text_lines( $child, $lines );
			}
		}
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
		$novel_author    = '';

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
				if ( $vol_info ) {
					$current_vol     = $vol_info['vol_name'];
					$current_vol_idx = $vol_info['vol_idx'];
					if ( ! empty( $vol_info['has_sub_chapter'] ) && ! empty( $vol_info['sub_chapter_line'] ) ) {
						$clean_text = $vol_info['sub_chapter_line'];
						$is_heading = true;
					} else {
						continue;
					}
				}

				if ( $is_heading ) {
					$this->flush_current_chapter( $chapters, $current_chapter, $current_content, $novel_intro, $novel_author, $raw_filename, $novel_title );
					$this->open_chapter( $chapters, $current_chapter, $current_vol, $current_vol_idx, $chap_counter, $clean_text, $vol_info );
				} else {
					$current_content[] = $text;
				}
			}
		}

		// 保存最后一章
		$this->flush_current_chapter( $chapters, $current_chapter, $current_content, $novel_intro, $novel_author, $raw_filename, $novel_title );

		// 若作者仍未提取到，兜底通过文件名尝试识别
		if ( empty( $novel_author ) ) {
			$fn_extracted = W2P_Novel_Helper::extract_author_and_intro( array(), $raw_filename );
			if ( ! empty( $fn_extracted['author'] ) ) {
				$novel_author = $fn_extracted['author'];
			}
			if ( ! empty( $fn_extracted['title'] ) && $novel_title === pathinfo( $raw_filename, PATHINFO_FILENAME ) ) {
				$novel_title = $fn_extracted['title'];
			}
		}

		return $this->finalize_result( $chapters, $novel_intro, $novel_title, $novel_author );
	}

	/**
	 * 将当前章节刷入章节列表；无当前章节时把前置文本作为小说简介并智能提取作者
	 *
	 * @param array      $chapters        章节列表（引用）
	 * @param array|null $current_chapter 当前章节（引用）
	 * @param array      $current_content 当前章节内容行（引用）
	 * @param string     $novel_intro     小说简介（引用）
	 * @param string     $novel_author    小说作者（引用）
	 * @param string     $raw_filename    原始文件名
	 * @param string     $novel_title     小说名称（引用）
	 * @return void
	 */
	private function flush_current_chapter( &$chapters, &$current_chapter, &$current_content, &$novel_intro, &$novel_author = '', $raw_filename = '', &$novel_title = '' ) {
		if ( $current_chapter !== null ) {
			$body_text                     = implode( "\n\n", $current_content );
			$current_chapter['content']    = $this->format_paragraphs( $body_text );
			$current_chapter['word_count'] = mb_strlen( strip_tags( $body_text ), 'UTF-8' );
			$chapters[]                    = $current_chapter;
			$current_chapter               = null;
			$current_content               = array();
		} elseif ( empty( $chapters ) && ! empty( $current_content ) ) {
			// 首章前积累的前置文本：调用 Helper 智能识别作者与纯净简介
			$extracted = W2P_Novel_Helper::extract_author_and_intro( $current_content, $raw_filename );
			if ( ! empty( $extracted['author'] ) ) {
				$novel_author = $extracted['author'];
			}
			$novel_intro = $extracted['intro'];
			if ( ! empty( $extracted['title'] ) && ( empty( $novel_title ) || $novel_title === pathinfo( $raw_filename, PATHINFO_FILENAME ) ) ) {
				$novel_title = $extracted['title'];
			}
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
		} elseif ( $chap_num > 0 && $chap_num < 90000 ) {
			$chap_counter = $chap_num + 1;
		}

		if ( $vol_info ) {
			$current_vol     = $vol_info['vol_name'];
			$current_vol_idx = intval( $vol_info['vol_idx'] );
		} elseif ( ! empty( $current_vol ) && preg_match( '/(?:番外|外传|后传|前传|别传|新传|特别篇|作品相关)/u', $current_vol ) ) {
			$current_vol_idx = 99;
		}

		// 番外卷序号提升规则 (99-99001)
		if ( 99 === $current_vol_idx && $chap_num > 0 && $chap_num < 90000 ) {
			$chap_num = 99000 + $chap_num;
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
	 * @param array  $chapters     章节列表
	 * @param string $novel_intro  小说简介
	 * @param string $novel_title  小说名称
	 * @param string $novel_author 小说作者
	 * @return array 汇总后的解析结果
	 */
	private function finalize_result( $chapters, $novel_intro, $novel_title, $novel_author = '' ) {
		// 过滤无效伪章节（如首条为书名且字数为0，或纯空内容伪章节）
		$chapters = $this->filter_invalid_chapters( $chapters, $novel_title );

		$total_words = 0;
		$volumes_map = array();
		// 熔断与切碎风险检测 (Safety Breaker)
		$count_chapters = count( $chapters );
		$short_chaps    = 0;
		$avg_words      = $count_chapters > 0 ? ( $total_words / $count_chapters ) : 0;
		foreach ( $chapters as $c ) {
			if ( isset( $c['word_count'] ) && $c['word_count'] < 30 ) {
				$short_chaps++;
			}
		}

		$safety_warning = null;
		if ( $count_chapters >= 10 && ( $avg_words < 80 || ( $short_chaps / $count_chapters ) > 0.4 ) ) {
			$safety_warning = __( 'Warning: Unusually high number of very short chapters detected. Your custom rules may be over-splitting content.', 'wp-genius' );
		}

		return array(
			'novel_title'    => $novel_title,
			'novel_author'   => $novel_author,
			'novel_intro'    => $novel_intro,
			'total_chapters' => count( $chapters ),
			'total_words'    => $total_words,
			'total_volumes'  => count( $volumes_map ),
			'safety_warning' => $safety_warning,
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
		$existing_id     = ! empty( $novel_data['novel_id'] ) ? absint( $novel_data['novel_id'] ) : 0;
		$import_strategy = ! empty( $novel_data['import_strategy'] ) ? sanitize_key( $novel_data['import_strategy'] ) : 'append';

		if ( $existing_id > 0 && get_post_type( $existing_id ) === 'novel' ) {
			$novel_id = $existing_id;

			// 如果指定清空重导策略，执行安全彻底清空旧章节
			if ( 'truncate' === $import_strategy ) {
				self::truncate_novel_chapters( $novel_id );
			}

			// 如果前端显式提供了标题或简介，才更新小说主体文章
			$update_args = array( 'ID' => $novel_id );
			if ( ! empty( $title ) && $title !== get_the_title( $novel_id ) ) {
				$update_args['post_title'] = $title;
			}
			if ( ! empty( $intro ) ) {
				$update_args['post_excerpt'] = $intro;
				$update_args['post_content'] = $intro;
			}
			if ( count( $update_args ) > 1 ) {
				wp_update_post( $update_args );
			}
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

		// 5. 设置人物 (humans) - 支持中英文逗号多作者，存在复用，不存在新建
		$author_raw = ! empty( $novel_data['author_name'] ) ? sanitize_text_field( $novel_data['author_name'] ) : '';
		if ( ! empty( $author_raw ) ) {
			$author_names = preg_split( '/[,，]/u', $author_raw );
			$human_ids    = array();
			foreach ( $author_names as $name ) {
				$name = trim( $name );
				if ( '' === $name ) {
					continue;
				}
				$existing_term = term_exists( $name, 'humans' );
				if ( $existing_term ) {
					$term_id = is_array( $existing_term ) ? intval( $existing_term['term_id'] ) : intval( $existing_term );
				} else {
					$new_term = wp_insert_term( $name, 'humans' );
					if ( ! is_wp_error( $new_term ) && isset( $new_term['term_id'] ) ) {
						$term_id = intval( $new_term['term_id'] );
					} else {
						$term_id = 0;
					}
				}
				if ( $term_id > 0 && ! in_array( $term_id, $human_ids, true ) ) {
					$human_ids[] = $term_id;
				}
			}
			if ( ! empty( $human_ids ) ) {
				wp_set_object_terms( $novel_id, $human_ids, 'humans' );
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

		add_filter( 'smart_aui_skip_post_processing', '__return_true' );
		try {
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
		} finally {
			remove_filter( 'smart_aui_skip_post_processing', '__return_true' );
		}
		// 同步推进断点续传进度
		$active_task = get_option( 'w2p_novel_active_import_task', null );
		if ( ! empty( $active_task ) && is_array( $active_task ) && intval( $active_task['novel_id'] ) === intval( $novel_id ) ) {
			$active_task['imported_count'] = intval( $active_task['imported_count'] ) + $created_count;
			$active_task['updated_at']     = current_time( 'mysql' );

			if ( $active_task['imported_count'] >= intval( $active_task['total_chapters'] ) ) {
				// 全部导入完成，自动清理任务记录与临时缓存文件并刷新前后台缓存
				self::discard_active_task();
				W2P_Novel_Helper::purge_novel_cache( $novel_id );
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
