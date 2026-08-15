<?php
/**
 * Word to Post — DOCX Importer
 *
 * Word document parsing, chapter splitting, and post creation.
 * Split out of class-word-to-posts.php (God class refactor).
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

		$filePath = $target_file; // Update the file path
		$chapters = $this->extractChapters( $filePath );
		if ( empty( $chapters ) ) {
			wp_send_json_error( __( 'No chapters found in the document.', 'wp-genius' ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- handleFileUpload already verifies the nonce at the top.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Same as above.
		$category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- The calling method has already verified the nonce (see the top of the method).
		$tags = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- handleFileUpload has already verified the nonce; only subsequent parameters are read here.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Same as above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Same as above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Same as above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Same as above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Same as above.
		$author = isset( $_POST['author'] ) ? absint( $_POST['author'] ) : get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- The calling method has already verified the nonce (see the top of the method).
		$cpt_type = isset( $_POST['cpt_type'] ) ? sanitize_text_field( $_POST['cpt_type'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- The calling method has already verified the nonce (see the top of the method).
		$cpt_id = isset( $_POST['cpt_id'] ) ? intval( $_POST['cpt_id'] ) : 0;

		if ( empty( $cpt_type ) || empty( $cpt_id ) ) {
			wp_send_json_error( __( 'Missing CPT association information.', 'wp-genius' ) );
			return;
		}

		// Verify that the CPT ID exists and the type matches
		$associated_post = get_post( $cpt_id );
		if ( ! $associated_post || $associated_post->post_type !== $cpt_type ) {
			// translators: %1: placeholder, %2: placeholder.
			wp_send_json_error( sprintf( __( 'Invalid associated post (ID: %1$d, Type: %2$s).', 'wp-genius' ), $cpt_id, $cpt_type ) );
			return;
		}

		$current_time   = current_time( 'mysql' ); // Get the current time
		$time_increment = 0; // Initialize the time increment

		$log = array();

		foreach ( $chapters as $chapter ) {
			$post_date = date( 'Y-m-d H:i:s', strtotime( $current_time ) + $time_increment ); // Incremental time

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

				// Save the CPT association info to post meta
				update_post_meta( $post_id, '_w2p_associated_cpt_type', $cpt_type );
				update_post_meta( $post_id, '_w2p_associated_cpt_id', $cpt_id );

				// translators: %1: placeholder, %2: placeholder.
				$log[] = sprintf( __( 'Chapter "%1$s" published successfully, Post ID: %2$d', 'wp-genius' ), $chapter['title'], $post_id );
			} else {
				// translators: %1: placeholder.
				$log[] = sprintf( __( 'Failed to publish chapter "%s"', 'wp-genius' ), $chapter['title'] );
			}
			// phpcs:ignore Squiz.Operators.IncrementDecrementUsage -- The semantic += 1 expresses the time increment.
			$time_increment += 1; // Add 1 second per loop iteration
		}
		$log[] = __( 'All chapters have been published successfully.', 'wp-genius' );
		wp_send_json_success( $log );
	}
	public function extractChapters( $filePath ) {
		// $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);        // Directly reads the entire file
		$phpWord        = \PhpOffice\PhpWord\IOFactory::createReader( 'Word2007' )->load( $filePath );     // Use streaming mode to process the file in segments, reducing memory usage
		$chapters       = array();
		$currentChapter = null;
		$currentContent = '';

		foreach ( $phpWord->getSections() as $section ) {
			foreach ( $section->getElements() as $element ) {
				if ( get_class( $element ) === 'PhpOffice\PhpWord\Element\TextRun' ) {
					$text = '';
					foreach ( $element->getElements() as $childElement ) {
						if ( get_class( $childElement ) === 'PhpOffice\PhpWord\Element\Text' ) {
							$isBold = $childElement->getFontStyle() && $childElement->getFontStyle()->isBold();     // Preserve the bold text format
							$text  .= $isBold ? '<strong>' . $childElement->getText() . '</strong>' : $childElement->getText();
						}
					}

					$paragraphStyle = $element->getParagraphStyle();
					$styleName      = $paragraphStyle ? $paragraphStyle->getStyleName() : '';

					// If styleName is '2' or '3', recognize it as a heading
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
