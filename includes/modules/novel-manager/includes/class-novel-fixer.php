<?php
/**
 * Novel Manager — Fixer Engine
 *
 * 针对已存在的 Novel 及关联的 Chapter，进行章节顺序重构与分卷信息识别。
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-novel-helper.php';

class W2P_Novel_Fixer {

	/**
	 * 获取待扫描的章节总数
	 *
	 * @param string $scan_mode  'all' 或 'by_novel'
	 * @param int    $novel_id   指定小说 ID
	 * @param int    $scan_limit 最近 N 本小说限制
	 * @return int
	 */
	public function get_total( $scan_mode = 'all', $novel_id = 0, $scan_limit = 5 ) {
		global $wpdb;

		$sql   = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p";
		$where = array( "p.post_type = 'chapter'", "p.post_status != 'trash'" );
		$join  = '';

		if ( $scan_mode === 'by_novel' ) {
			$join .= " INNER JOIN {$wpdb->postmeta} pm_novel ON p.ID = pm_novel.post_id AND pm_novel.meta_key = 'related_novel_id'";
			if ( $novel_id > 0 ) {
				$where[] = $wpdb->prepare( 'pm_novel.meta_value = %d', $novel_id );
			} elseif ( $scan_limit > 0 ) {
				$novel_ids = $this->get_recent_novel_ids( $scan_limit );
				if ( ! empty( $novel_ids ) ) {
					$ids_str = implode( ',', array_map( 'intval', $novel_ids ) );
					$where[] = "pm_novel.meta_value IN ($ids_str)";
				} else {
					return 0;
				}
			}
		}

		$finished_ids = get_option( 'w2p_fix_index_finished_books', array() );
		if ( ! empty( $finished_ids ) && $scan_mode === 'all' ) {
			$join   .= " LEFT JOIN {$wpdb->postmeta} pm_finished ON p.ID = pm_finished.post_id AND pm_finished.meta_key = 'related_novel_id'";
			$ids_str = implode( ',', array_map( 'intval', $finished_ids ) );
			$where[] = "(pm_finished.meta_value IS NULL OR pm_finished.meta_value NOT IN ($ids_str))";
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$query_sql = $sql . $join . ' WHERE ' . implode( ' AND ', $where );
		$total     = intval( $wpdb->get_var( $query_sql ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return $total;
	}

	/**
	 * 批量扫描章节（Dry Run 预览模式）
	 *
	 * @param array $params 扫描参数
	 * @return array 扫描结果
	 */
	public function scan_batch( $params ) {
		$scan_mode   = isset( $params['scan_mode'] ) ? sanitize_text_field( $params['scan_mode'] ) : 'all';
		$novel_id    = isset( $params['novel_id'] ) ? intval( $params['novel_id'] ) : 0;
		$scan_limit  = isset( $params['scan_limit'] ) ? intval( $params['scan_limit'] ) : 5;
		$batch_size  = isset( $params['batch_size'] ) ? intval( $params['batch_size'] ) : 20;
		$offset      = isset( $params['offset'] ) ? intval( $params['offset'] ) : 0;
		$format      = isset( $params['index_format'] ) ? sanitize_text_field( $params['index_format'] ) : '01-00001';
		$connector   = isset( $params['index_connector'] ) ? sanitize_text_field( $params['index_connector'] ) : '-';
		$auto_volume = ! empty( $params['auto_volume'] );

		$context = isset( $params['context'] ) && is_array( $params['context'] ) ? $params['context'] : array();

		$vol_idx       = isset( $context['vol_idx'] ) ? intval( $context['vol_idx'] ) : 1;
		$chap_idx      = isset( $context['chap_idx'] ) ? intval( $context['chap_idx'] ) : 1;
		$vol_name      = isset( $context['vol_name'] ) ? $context['vol_name'] : '正文';
		$last_novel_id = isset( $context['last_novel_id'] ) ? intval( $context['last_novel_id'] ) : 0;

		$finished_ids = get_option( 'w2p_fix_index_finished_books', array() );

		// 构建查询
		$query_args = array(
			'post_type'              => 'chapter',
			'post_status'            => 'any',
			'posts_per_page'         => $batch_size,
			'offset'                 => $offset,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'ID'         => 'ASC',
			),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$meta_query = array();
		if ( ! empty( $finished_ids ) && $scan_mode === 'all' ) {
			$meta_query[] = array(
				'key'     => 'related_novel_id',
				'value'   => $finished_ids,
				'compare' => 'NOT IN',
			);
		}

		if ( $scan_mode === 'by_novel' ) {
			if ( $novel_id > 0 ) {
				$meta_query[] = array(
					'key'     => 'related_novel_id',
					'value'   => $novel_id,
					'compare' => '=',
				);
			} elseif ( $scan_limit > 0 ) {
				$novel_ids = $this->get_recent_novel_ids( $scan_limit );
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

		$query             = new WP_Query( $query_args );
		$logs              = array();
		$finished_novel_id = 0;

		foreach ( $query->posts as $post_id ) {
			$current_novel_id = intval( get_post_meta( $post_id, 'related_novel_id', true ) );

			// 跨书检测
			if ( $last_novel_id > 0 && $current_novel_id !== $last_novel_id ) {
				if ( ! empty( $logs ) ) {
					$finished_novel_id = $last_novel_id;
					break;
				}
				$vol_idx  = 1;
				$chap_idx = 1;
				$vol_name = '正文';
			}
			$last_novel_id = $current_novel_id;

			$title = get_the_title( $post_id );

			// 识别分卷
			if ( $auto_volume ) {
				$vol_info = W2P_Novel_Helper::extract_volume( $title );
				if ( $vol_info ) {
					$vol_idx  = $vol_info['vol_idx'];
					$vol_name = $vol_info['vol_name'];
				}
			}

			// 识别章节序号
			$chap_num = W2P_Novel_Helper::extract_chapter_number( $title );
			if ( $chap_num === 0 ) {
				$current_vol_idx  = 0;
				$current_chap_idx = 0;
				$display_vol      = __( 'Preface/Related', 'wp-genius' );
			} elseif ( $chap_num === 99999 ) {
				$current_vol_idx  = $vol_idx;
				$current_chap_idx = 99999;
				$display_vol      = $vol_name;
			} elseif ( $chap_num !== null ) {
				$current_vol_idx  = $vol_idx;
				$current_chap_idx = $chap_num;
				$chap_idx         = $chap_num + 1;
				$display_vol      = $vol_name;
			} else {
				$current_vol_idx  = $vol_idx;
				$current_chap_idx = $chap_idx;
				++$chap_idx;
				$display_vol = $vol_name;
			}

			$index_str = W2P_Novel_Helper::format_chapter_index( $current_vol_idx, $current_chap_idx, $format, $connector );

			$old_index  = (string) get_post_meta( $post_id, 'chapter_index', true );
			$old_volume = (string) get_post_meta( $post_id, 'volume_name', true );

			$logs[] = array(
				'post_id'    => $post_id,
				'novel_id'   => $current_novel_id,
				'title'      => mb_strimwidth( $title, 0, 50, '...' ),
				'index'      => $index_str,
				'old_index'  => $old_index ?: '-',
				'volume'     => $display_vol,
				'old_volume' => $old_volume ?: '-',
				'edit_link'  => get_edit_post_link( $post_id ),
			);

			clean_post_cache( $post_id );
		}

		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		return array(
			'count'             => count( $logs ),
			'logs'              => $logs,
			'finished_novel_id' => $finished_novel_id,
			'context'           => array(
				'vol_idx'       => $vol_idx,
				'chap_idx'      => $chap_idx,
				'vol_name'      => $vol_name,
				'last_novel_id' => $last_novel_id,
			),
		);
	}

	/**
	 * 批量执行写入更新（Execute 模式）
	 *
	 * @param array $scan_results 待更新的项目数组
	 * @return array 统计结果
	 */
	public function execute_batch( $scan_results ) {
		if ( empty( $scan_results ) || ! is_array( $scan_results ) ) {
			return array(
				'updated' => 0,
				'failed'  => 0,
			);
		}

		$updated = 0;
		$failed  = 0;

		foreach ( $scan_results as $item ) {
			$post_id = intval( $item['post_id'] );
			$index   = sanitize_text_field( $item['index'] );
			$volume  = isset( $item['volume'] ) ? sanitize_text_field( $item['volume'] ) : '';

			if ( ! get_post( $post_id ) ) {
				++$failed;
				continue;
			}

			update_post_meta( $post_id, 'chapter_index', $index );
			if ( ! empty( $volume ) && $volume !== '-' ) {
				update_post_meta( $post_id, 'volume_name', $volume );
			}

			++$updated;
			if ( $updated % 50 === 0 ) {
				clean_post_cache( $post_id );
			}
		}

		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		return array(
			'updated' => $updated,
			'failed'  => $failed,
			'total'   => count( $scan_results ),
		);
	}

	/**
	 * 获取最近有章节关联的小说 ID 列表
	 *
	 * @param int $limit 数量
	 * @return array
	 */
	public function get_recent_novel_ids( $limit = 10 ) {
		global $wpdb;

		$limit = absint( $limit );
		$sql   = $wpdb->prepare(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'related_novel_id' AND meta_value != '' ORDER BY meta_id DESC LIMIT %d",
			$limit
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_col( $sql );
		return array_map( 'intval', $results );
	}
}
