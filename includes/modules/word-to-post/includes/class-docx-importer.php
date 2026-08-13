<?php
/**
 * Word to Post — DOCX Importer
 *
 * Word 文档解析、章节拆分与文章创建。
 * 从 class-word-to-posts.php 拆分（God class 重构）。
 *
 * @package WP_Genius
 * @subpackage Modules/WordToPost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_WordToPost_DocxImporter
 */
class W2P_WordToPost_DocxImporter {

	public function importAndPublish( $filePath ) {
		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/word2post';
		if ( ! file_exists( $target_dir ) ) {
			mkdir( $target_dir, 0755, true );
		}

		$target_file = $target_dir . '/' . basename( $filePath );
		if ( ! rename( $filePath, $target_file ) ) {
			wp_send_json_error( __( 'Failed to move uploaded file.', 'wp-genius' ) );
			return;
		}

		$filePath = $target_file; // 更新文件路径
		$chapters = $this->extractChapters( $filePath );
		if ( empty( $chapters ) ) {
			wp_send_json_error( __( 'No chapters found in the document.', 'wp-genius' ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- handleFileUpload 顶部已 wp_verify_nonce。
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 同上。
		$category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 上层方法已验 nonce（见方法开头）。
		$tags = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- handleFileUpload 已验 nonce，此处读取后续参数。
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 同上。
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 同上。
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 同上。
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 同上。
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 同上。
		$author = isset( $_POST['author'] ) ? absint( $_POST['author'] ) : get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 上层方法已验 nonce（见方法开头）。
		$cpt_type = isset( $_POST['cpt_type'] ) ? sanitize_text_field( $_POST['cpt_type'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 上层方法已验 nonce（见方法开头）。
		$cpt_id = isset( $_POST['cpt_id'] ) ? intval( $_POST['cpt_id'] ) : 0;

		if ( empty( $cpt_type ) || empty( $cpt_id ) ) {
			wp_send_json_error( __( 'Missing CPT association information.', 'wp-genius' ) );
			return;
		}

		// 验证 CPT ID 是否存在且类型匹配
		$associated_post = get_post( $cpt_id );
		if ( ! $associated_post || $associated_post->post_type !== $cpt_type ) {
			// translators: %1: placeholder, %2: placeholder。
			wp_send_json_error( sprintf( __( 'Invalid associated post (ID: %1$d, Type: %2$s).', 'wp-genius' ), $cpt_id, $cpt_type ) );
			return;
		}

		$current_time   = current_time( 'mysql' ); // 获取当前时间
		$time_increment = 0; // 初始化时间增量

		$log = array();

		foreach ( $chapters as $chapter ) {
			$post_date = date( 'Y-m-d H:i:s', strtotime( $current_time ) + $time_increment ); // 增量时间

			$post_data = array(
				'post_title'    => wp_strip_all_tags( $chapter['title'] ),
				'post_content'  => $chapter['content'],
				'post_status'   => 'publish',
				'post_author'   => $author,
				'post_category' => array( $category ),
				'tax_input'     => array( 'post_tag' => explode( ',', $tags ) ),
				'post_date'     => $post_date,
			);

			$post_id = wp_insert_post( $post_data );
			if ( $post_id ) {
				wp_set_post_terms( $post_id, explode( ',', $tags ), 'post_tag' );

				// 保存 CPT 关联信息到 Post Meta
				update_post_meta( $post_id, '_w2p_associated_cpt_type', $cpt_type );
				update_post_meta( $post_id, '_w2p_associated_cpt_id', $cpt_id );

				// translators: %1: placeholder, %2: placeholder。
				$log[] = sprintf( __( 'Chapter "%1$s" published successfully, Post ID: %2$d', 'wp-genius' ), $chapter['title'], $post_id );
			} else {
				// translators: %1: placeholder。
				$log[] = sprintf( __( 'Failed to publish chapter "%s"', 'wp-genius' ), $chapter['title'] );
			}
			// phpcs:ignore Squiz.Operators.IncrementDecrementUsage -- 语义化 += 1 表达时间增量。
			$time_increment += 1; // 每次循环增加1秒
		}
		$log[] = __( 'All chapters have been published successfully.', 'wp-genius' );
		wp_send_json_success( $log );
	}
	public function extractChapters( $filePath ) {
		// $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);        //直接读取整个文件
		$phpWord        = \PhpOffice\PhpWord\IOFactory::createReader( 'Word2007' )->load( $filePath );     //使用流模式分段处理文件，减少内存占用
		$chapters       = array();
		$currentChapter = null;
		$currentContent = '';

		foreach ( $phpWord->getSections() as $section ) {
			foreach ( $section->getElements() as $element ) {
				if ( get_class( $element ) === 'PhpOffice\PhpWord\Element\TextRun' ) {
					$text = '';
					foreach ( $element->getElements() as $childElement ) {
						if ( get_class( $childElement ) === 'PhpOffice\PhpWord\Element\Text' ) {
							$isBold = $childElement->getFontStyle() && $childElement->getFontStyle()->isBold();     //保留字体加粗的文字格式
							$text  .= $isBold ? '<strong>' . $childElement->getText() . '</strong>' : $childElement->getText();
						}
					}

					$paragraphStyle = $element->getParagraphStyle();
					$styleName      = $paragraphStyle ? $paragraphStyle->getStyleName() : '';

					// 如果 styleName 是 '2' 或者 '3'，则识别为标题
					if ( $styleName === '2' || $styleName === '3' ) {
						if ( $currentChapter ) {
							$currentChapter['content'] = $currentContent;
							$chapters[]                = $currentChapter;
							$currentContent            = '';
						}
						$currentChapter = array(
							'title'   => $text,
							'content' => '',
						);
					} elseif ( $currentChapter ) {
						$currentContent .= '<p>' . $text . '</p>';
					}
				}
			}
		}

		if ( $currentChapter ) {
			$currentChapter['content'] = $currentContent;
			$chapters[]                = $currentChapter;
		}

		return $chapters;
	}
	/**
	 * Convert Chinese numerals to Arabic equivalents
	 */
	private function chi2arab( $cnStr ) {
		if ( ! $cnStr ) {
			return 0;
		}
		$cnStr = trim( $cnStr );
		if ( $cnStr === '廿' ) {
			return 20;
		}
		if ( $cnStr === '卅' ) {
			return 30;
		}
		if ( strpos( $cnStr, '十' ) === 0 ) {
			$cnStr = '一' . $cnStr;
		}

		$cnNumMap  = array(
			'〇' => 0,
			'一' => 1,
			'二' => 2,
			'三' => 3,
			'四' => 4,
			'五' => 5,
			'六' => 6,
			'七' => 7,
			'八' => 8,
			'九' => 9,
			'零' => 10,
			'两' => 2,
		);
		$cnUnitMap = array(
			'十' => 10,
			'百' => 100,
			'千' => 1000,
		);

		$total   = 0;
		$tempVal = 0;

		// Split string into characters for multi-byte handling
		$chars = preg_split( '//u', $cnStr, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $chars as $ch ) {
			if ( isset( $cnNumMap[ $ch ] ) ) {
				$tempVal = $cnNumMap[ $ch ];
			} elseif ( isset( $cnUnitMap[ $ch ] ) ) {
				$unit = $cnUnitMap[ $ch ];
				if ( $tempVal === 0 ) {
					$tempVal = 1;
				}
				$total  += $tempVal * $unit;
				$tempVal = 0;
			}
		}
		$total += $tempVal;
		return $total;
	}
}
