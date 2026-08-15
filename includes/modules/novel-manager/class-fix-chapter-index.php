<?php
/**
 * Fix Chapter Index Handler
 *
 * @package WP_Genius
 * @subpackage Modules/WordToPost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FixChapterIndex {

	public function __construct() {
		add_action( 'wp_ajax_fix_index_get_total', array( $this, 'getTotal' ) );
		add_action( 'wp_ajax_fix_index_scan', array( $this, 'scanBatch' ) );
		add_action( 'wp_ajax_fix_index_execute', array( $this, 'executeBatch' ) );
		add_action( 'wp_ajax_fix_index_mark_finished', array( $this, 'markFinished' ) );
		add_action( 'wp_ajax_fix_index_clear_progress', array( $this, 'clearFinishedProgress' ) );
	}

	/**
	 * Get the total scan count
	 */
	public function getTotal() {
		global $wpdb;
		check_ajax_referer( 'fix_chapter_index', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'wp-genius' ) );
		}

		$post_type  = 'chapter';
		$scan_mode  = sanitize_text_field( $_POST['scan_mode'] );
		$novel_id   = ! empty( $_POST['novel_id'] ) ? intval( $_POST['novel_id'] ) : 0;
		$scan_limit = intval( $_POST['scan_limit'] );

		// Get the list of already-finished books
		$finished_ids = get_option( 'w2p_fix_index_finished_books', array() );

		// Use native SQL counting to avoid WP_Query loading huge amounts of data
		$sql   = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p";
		$where = array( "p.post_type = '{$post_type}'", "p.post_status != 'trash'" );
		$join  = '';

		// Scan by book
		if ( $scan_mode === 'by_novel' ) {
			$join .= " INNER JOIN {$wpdb->postmeta} pm_novel ON p.ID = pm_novel.post_id AND pm_novel.meta_key = 'related_novel_id'";
			if ( $novel_id > 0 ) {
				$where[] = $wpdb->prepare( 'pm_novel.meta_value = %d', $novel_id );
			} elseif ( $scan_limit > 0 ) {
				$novel_ids = $this->getRecentNovelIds( $scan_limit );
				if ( ! empty( $novel_ids ) ) {
					$ids_str = implode( ',', array_map( 'intval', $novel_ids ) );
					$where[] = "pm_novel.meta_value IN ($ids_str)";
				} else {
					$where[] = '1=0'; // No books found, total is 0
				}
			}
		}

		// Exclude already-processed books (full-scan mode only)
		if ( ! empty( $finished_ids ) && $scan_mode === 'all' ) {
			$join   .= " LEFT JOIN {$wpdb->postmeta} pm_finished ON p.ID = pm_finished.post_id AND pm_finished.meta_key = 'related_novel_id'";
			$ids_str = implode( ',', array_map( 'intval', $finished_ids ) );
			$where[] = "(pm_finished.meta_value IS NULL OR pm_finished.meta_value NOT IN ($ids_str))";
		}

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL assembly; every fragment comes from a safe source: $sql/$join contain only $wpdb->prefix table names, $where elements are hardcoded literals / intval-cast lists / $wpdb->prepare results.
		$query_sql = $sql . $join . ' WHERE ' . implode( ' AND ', $where );
		$total     = intval( $wpdb->get_var( $query_sql ) );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		wp_send_json_success(
			array(
				'total'          => $total,
				'finished_count' => count( $finished_ids ),
			)
		);
	}

	/**
	 * Scan batch - preview only, does not write to the database
	 */
	public function scanBatch() {
		check_ajax_referer( 'fix_chapter_index', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'wp-genius' ) );
		}

		// CRITICAL: Force chapter post type
		$post_type  = 'chapter';
		$scan_mode  = sanitize_text_field( $_POST['scan_mode'] );
		$novel_id   = ! empty( $_POST['novel_id'] ) ? intval( $_POST['novel_id'] ) : 0;
		$scan_limit = intval( $_POST['scan_limit'] );
		$batch_size = intval( $_POST['batch_size'] );
		$offset     = intval( $_POST['offset'] );

		// Get the list of already-finished books
		$finished_ids = get_option( 'w2p_fix_index_finished_books', array() );

		// Build the query arguments (helper split)
		$query_args = $this->build_scan_query_args(
			$scan_mode,
			$novel_id,
			$scan_limit,
			$batch_size,
			$offset,
			$finished_ids
		);

		// Query chapter posts (fetch IDs only)
		$query = new WP_Query( $query_args );

		$logs        = array();
		$format      = sanitize_text_field( $_POST['index_format'] );
		$connector   = sanitize_text_field( $_POST['index_connector'] );
		$auto_volume = ! empty( $_POST['auto_volume'] );

		// Parse the context
		$context = array();
		if ( ! empty( $_POST['context'] ) ) {
			$context = json_decode( stripslashes( $_POST['context'] ), true );
		}

		$vol_idx       = isset( $context['vol_idx'] ) ? intval( $context['vol_idx'] ) : 1;
		$chap_idx      = isset( $context['chap_idx'] ) ? intval( $context['chap_idx'] ) : 1;
		$vol_name      = isset( $context['vol_name'] ) ? $context['vol_name'] : '';
		$last_novel_id = isset( $context['last_novel_id'] ) ? intval( $context['last_novel_id'] ) : 0;

		$finished_novel_id = 0;
		// Iterate over IDs and fetch titles individually (avoid loading large content at once)
		foreach ( $query->posts as $post_id ) {
			// Get the book ID
			$current_novel_id = intval( get_post_meta( $post_id, 'related_novel_id', true ) );

			// Cross-book detection: if novel_id changed
			if ( $last_novel_id > 0 && $current_novel_id !== $last_novel_id ) {
				if ( ! empty( $logs ) ) {
					// If this batch already processed chapters, stop here and leave the rest for the next batch
					// Also mark the previous book as finished
					$finished_novel_id = $last_novel_id;
					break;
				}
				// If this batch starts with a new book or crosses into another book, reset the recognition state
				$vol_idx  = 1;
				$chap_idx = 1;
				$vol_name = '';
			}
			$last_novel_id = $current_novel_id;

			$title            = get_the_title( $post_id );
			$current_vol_idx  = $vol_idx;
			$current_chap_idx = $chap_idx;

			// Detect volume - only extract the volume name when the title contains both a volume marker and a chapter marker
			// Format: e.g. "Volume 7 [name]·Chapter 151" or "Volume 7 [name] Chapter 151"
			if ( $auto_volume && preg_match( '/第\s*(.+?)\s*卷\s*(.+?)\s*第\s*(.+?)\s*[章节回话]/u', $title, $m ) ) {
				// Title format: Volume x [space] volume name [space] Chapter x chapter name
				$vol_num_str = trim( $m[1] );
				$vol_idx     = $this->parseNumber( $vol_num_str );

				// Clean the volume name: strip leading/trailing special characters (e.g. ·, -, spaces, etc.)
				$raw_vol_name     = trim( $m[2] );
				$cleaned_vol_name = preg_replace( '/^[·\s\-_:：|]+|[·\s\-_:：|]+$/u', '', $raw_vol_name );

				$vol_name = '第' . $vol_num_str . '卷 ' . $cleaned_vol_name;
				$chap_idx = 1;

				// Extract the chapter number
				$chapter_num = $this->parseNumber( trim( $m[3] ) );
				if ( $chapter_num !== null ) {
					$chap_idx = $chapter_num;
				}

				$current_vol_idx  = $vol_idx;
				$current_chap_idx = $chap_idx;
			} else {
				// Recognize the chapter number
				$chapter_num = $this->extractChapterNumber( $title );

				if ( $chapter_num === -1 ) {
					// Prologue, preface, foreword, etc. chapters -> force volume to 0
					$current_vol_idx = 0;
				} elseif ( $chapter_num !== null ) {
					$chap_idx         = $chapter_num;
					$current_vol_idx  = $vol_idx;
					$current_chap_idx = $chap_idx;
				} else {
					$current_vol_idx  = $vol_idx;
					$current_chap_idx = $chap_idx;
				}
			}

			// Generate the sequence number
			$parts    = explode( '-', $format );
			$vol_pad  = strlen( $parts[0] );
			$chap_pad = isset( $parts[1] ) ? strlen( $parts[1] ) : 5;

			$index = str_pad( $current_vol_idx, $vol_pad, '0', STR_PAD_LEFT ) .
					$connector .
					str_pad( $current_chap_idx, $chap_pad, '0', STR_PAD_LEFT );

			$logs[] = array(
				'post_id'   => $post_id,
				'index'     => $index,
				'volume'    => ( $current_vol_idx === 0 ) ? __( 'Preface/Related', 'wp-genius' ) : ( $vol_name ?: '-' ),
				'title'     => mb_strimwidth( $title, 0, 60, '...' ),
				'edit_link' => get_edit_post_link( $post_id ),
			);

			$chap_idx = $current_chap_idx + 1;

			// Local cleanup to prevent cache pressure from building up even with batching at 150k records
			clean_post_cache( $post_id );
		}

		// After scanning a batch, explicitly collect garbage
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		// Check whether the current book is finished
		// If this batch processed chapters and there is a current last_novel_id
		if ( $finished_novel_id === 0 && $last_novel_id > 0 && ! empty( $logs ) ) {
			// Check whether the next batch still has chapters of this book
			$next_offset                  = $offset + $batch_size;
			$check_args                   = $query_args;
			$check_args['posts_per_page'] = 1;
			$check_args['offset']         = $next_offset;

			$check_query = new WP_Query( $check_args );

			if ( $check_query->have_posts() ) {
				$next_post_id  = $check_query->posts[0];
				$next_novel_id = intval( get_post_meta( $next_post_id, 'related_novel_id', true ) );

				// If the next chapter does not belong to the current book, the current book is finished
				if ( $next_novel_id !== $last_novel_id ) {
					$finished_novel_id = $last_novel_id;
				}
			} else {
				// No next batch remains, so the current book is finished
				$finished_novel_id = $last_novel_id;
			}
		}

		wp_send_json_success(
			array(
				'count'             => count( $logs ),
				'logs'              => $logs,
				'finished_novel_id' => $finished_novel_id,
				'context'           => array(
					'vol_idx'       => $vol_idx,
					'chap_idx'      => $chap_idx,
					'vol_name'      => $vol_name,
					'last_novel_id' => $last_novel_id,
				),
			)
		);
	}

	/**
	 * Build scan query arguments (WP_Query args).
	 *
	 * @param string $scan_mode    Scan mode (all/by_novel).
	 * @param int    $novel_id     Specified book ID (0 means all).
	 * @param int    $scan_limit   Limit to the N most recent books.
	 * @param int    $batch_size   Batch size.
	 * @param int    $offset       Offset.
	 * @param array  $finished_ids List of finished book IDs.
	 * @return array
	 */
	private function build_scan_query_args( $scan_mode, $novel_id, $scan_limit, $batch_size, $offset, $finished_ids ) {
		$query_args = array(
			'post_type'              => 'chapter',
			'post_status'            => 'any',
			'posts_per_page'         => $batch_size,
			'offset'                 => $offset,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'ID'         => 'ASC',
			),
			// CRITICAL: fetch only ID and title, exclude post_content to avoid memory exhaustion
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$meta_query = array();

		// Exclude already-processed books (full-scan mode only)
		if ( ! empty( $finished_ids ) && $scan_mode === 'all' ) {
			$meta_query[] = array(
				'key'     => 'related_novel_id',
				'value'   => $finished_ids,
				'compare' => 'NOT IN',
			);
		}

		// Scan by book
		if ( $scan_mode === 'by_novel' ) {
			if ( $novel_id > 0 ) {
				// Specify a book ID
				$meta_query[] = array(
					'key'     => 'related_novel_id',
					'value'   => $novel_id,
					'compare' => '=',
				);
			} elseif ( $scan_limit > 0 ) {
				// Scan the N most recent books
				$novel_ids = $this->getRecentNovelIds( $scan_limit );
				if ( ! empty( $novel_ids ) ) {
					$meta_query[] = array(
						'key'     => 'related_novel_id',
						'value'   => $novel_ids,
						'compare' => 'IN',
					);
				}
			}
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		return $query_args;
	}

	/**
	 * Mark a book as processed/finished
	 */
	public function markFinished() {
		check_ajax_referer( 'fix_chapter_index', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'wp-genius' ) );
		}

		$novel_id = intval( $_POST['novel_id'] );
		if ( $novel_id <= 0 ) {
			wp_send_json_error( 'Invalid novel ID' );
		}

		$finished_ids = get_option( 'w2p_fix_index_finished_books', array() );
		if ( ! in_array( $novel_id, $finished_ids, true ) ) {
			$finished_ids[] = $novel_id;
			update_option( 'w2p_fix_index_finished_books', $finished_ids );
		}

		wp_send_json_success( array( 'message' => 'Book marked as finished' ) );
	}

	/**
	 * Clear the processing progress
	 */
	public function clearFinishedProgress() {
		check_ajax_referer( 'fix_chapter_index', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'wp-genius' ) );
		}

		delete_option( 'w2p_fix_index_finished_books' );
		wp_send_json_success( array( 'message' => __( 'Progress cleared', 'wp-genius' ) ) );
	}

	/**
	 * Get the ID list of the N most recent books
	 */
	private function getRecentNovelIds( $limit ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"
            SELECT DISTINCT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'related_novel_id'
            AND meta_value != ''
            ORDER BY meta_id DESC
            LIMIT %d
        ",
			$limit
		);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is the return value of the $wpdb->prepare() call above (LIMIT is bound with %d).
		$results = $wpdb->get_col( $query );
		return array_map( 'intval', $results );
	}

	/**
	 * Execute batch - actually writes to the database
	 */
	public function executeBatch() {
		check_ajax_referer( 'fix_chapter_index', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'wp-genius' ) );
		}

		// Get the scan results from POST
		$scan_results = json_decode( stripslashes( $_POST['scan_results'] ), true );

		if ( empty( $scan_results ) ) {
			wp_send_json_error( __( 'No scan results to execute', 'wp-genius' ) );
		}

		$updated = 0;
		$failed  = 0;
		$errors  = array();

		foreach ( $scan_results as $item ) {
			$post_id = intval( $item['post_id'] );
			$index   = sanitize_text_field( $item['index'] );
			$volume  = isset( $item['volume'] ) ? sanitize_text_field( $item['volume'] ) : '';

			// Verify that the post exists
			if ( ! get_post( $post_id ) ) {
				++$failed;
				$errors[] = "Post ID {$post_id} not found";
				continue;
			}

			$success = true;

			// Force the native post_meta approach (bypass ACF)
			$result_index = update_post_meta( $post_id, 'chapter_index', $index );

			if ( ! empty( $volume ) && $volume !== '-' ) {
				update_post_meta( $post_id, 'volume_name', $volume );
			}

			if ( $success ) {
				++$updated;

				// Prevent memory accumulation
				if ( $updated % 50 === 0 ) {
					clean_post_cache( $post_id );
				}
			} else {
				++$failed;
			}
		}

		// Batch finished, trigger garbage collection
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		// Return the detailed results
		// translators: %1: placeholder.
		$message = sprintf( __( 'Updated %d chapters', 'wp-genius' ), $updated );
		if ( $failed > 0 ) {
			// translators: %1: placeholder.
			$message .= sprintf( __( ', %d failed', 'wp-genius' ), $failed );
		}

		wp_send_json_success(
			array(
				'updated' => $updated,
				'failed'  => $failed,
				'total'   => count( $scan_results ),
				'message' => $message,
				'errors'  => $errors,
				'debug'   => array(
					'acf_available' => function_exists( 'update_field' ),
					'sample_data'   => ! empty( $scan_results ) ? $scan_results[0] : null,
				),
			)
		);
	}

	/**
	 * Extract the chapter number from the title
	 * Returns -1 for pre-content chapters (prologue, foreword, etc.)
	 */
	private function extractChapterNumber( $title ) {
		// Pre-content chapters: prologue, preface, foreword, intro, character intro, etc. -> mapped to 00-xxxxx
		if ( preg_match( '/(楔子|序章|前言|简介|内容简介|人物介绍|作品相关)/u', $title ) ) {
			return -1;
		}

		// Special chapters: epilogue, afterword, closing note, etc. -> 99999
		if ( preg_match( '/(尾声|后记|完结感言|后续|终章)/u', $title ) ) {
			return 99999;
		}

		// Format 1: starts with plain digits "233 Return"
		if ( preg_match( '/^(\d+)\s/u', $title, $m ) ) {
			return intval( $m[1] );
		}

		// Format 2: starts with digits and an enumeration comma "235 Departure"
		if ( preg_match( '/^(\d+)、/u', $title, $m ) ) {
			return intval( $m[1] );
		}

		// Format 3: "Chapter X" pattern (chapter/section/episode/tale)
		if ( preg_match( '/第\s*(.+?)\s*[章节回话]/u', $title, $m ) ) {
			return $this->parseNumber( trim( $m[1] ) );
		}

		// Format 4: contains a volume part (already handled above; kept here as a fallback)
		if ( preg_match( '/卷.*?第\s*(.+?)\s*[章节回话]/u', $title, $m ) ) {
			return $this->parseNumber( trim( $m[1] ) );
		}

		// Side story chapter handling
		if ( preg_match( '/番外/u', $title ) ) {
			// Side story 1, side story 2 -> extract the number
			if ( preg_match( '/番外\s*(\d+)/u', $title, $m ) ) {
				// Return 99000 + the side-story sequence number, e.g. side story 1 = 99001
				return 99000 + intval( $m[1] );
			}
			// Side story chapter one, side story chapter two
			if ( preg_match( '/番外\s*第\s*(.+?)\s*[章节]/u', $title, $m ) ) {
				$num = $this->parseNumber( trim( $m[1] ) );
				return 99000 + $num;
			}
			// Plain "side story" -> 99001
			return 99001;
		}

		return null;
	}

	/**
	 * Parse a number
	 */
	private function parseNumber( $str ) {
		$str = str_replace( ' ', '', $str );

		// Directly numeric
		if ( is_numeric( $str ) ) {
			return intval( $str );
		}

		// Map uppercase Chinese numerals to lowercase
		$upper_to_lower = array(
			'壹' => '一',
			'贰' => '二',
			'叁' => '三',
			'肆' => '四',
			'伍' => '五',
			'陆' => '六',
			'柒' => '七',
			'捌' => '八',
			'玖' => '九',
			'拾' => '十',
			'佰' => '百',
			'仟' => '千',
			'萬' => '万',
		);

		// Convert uppercase to lowercase
		$str = strtr( $str, $upper_to_lower );

		// Chinese numeral mapping
		$digits = array(
			'零' => 0,
			'〇' => 0,
			'一' => 1,
			'二' => 2,
			'两' => 2,
			'三' => 3,
			'四' => 4,
			'五' => 5,
			'六' => 6,
			'七' => 7,
			'八' => 8,
			'九' => 9,
		);

		$units = array(
			'十' => 10,
			'百' => 100,
			'千' => 1000,
			'万' => 10000,
		);

		// Single-character check
		if ( mb_strlen( $str ) === 1 ) {
			if ( isset( $digits[ $str ] ) ) {
				return $digits[ $str ];
			}
			if ( isset( $units[ $str ] ) ) {
				return $units[ $str ];
			}
		}

		// Parse Chinese numerals
		$total   = 0;
		$current = 0;
		$chars   = preg_split( '//u', $str, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $chars as $char ) {
			if ( isset( $digits[ $char ] ) ) {
				$current = $digits[ $char ];
			} elseif ( isset( $units[ $char ] ) ) {
				$unit_value = $units[ $char ];

				if ( $current === 0 ) {
					$current = 1; // "ten" -> 10, "hundred" -> 100
				}

				if ( $unit_value >= 10000 ) {
					// Ten-thousands (10,000)
					$total   = ( $total + $current ) * $unit_value;
					$current = 0;
				} else {
					// Tens, hundreds, thousands
					$total  += $current * $unit_value;
					$current = 0;
				}
			}
		}

		$total += $current;
		return $total > 0 ? $total : null;
	}
}

// Initialize
new FixChapterIndex();
