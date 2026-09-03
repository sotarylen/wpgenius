<?php
/**
 * Novel Manager — Fixer Engine
 *
 * 针对已存在的 Novel 及关联的 Chapter，进行章节顺序重构与分卷信息识别。
 * 遵循以 Novel 为聚合根的轻量高效设计，全面复用 W2P_Novel_Helper 权威计算。
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
	 * 搜索或定位小说（支持按 Novel ID 精准定位或按小说标题模糊匹配）
	 *
	 * @param string|int $keyword_or_id 搜索关键词或文章 ID
	 * @param int        $limit         返回结果上限
	 * @return array 小说列表
	 */
	public function search_novels( $keyword_or_id, $limit = 10 ) {
		global $wpdb;

		$keyword_or_id = trim( (string) $keyword_or_id );
		if ( '' === $keyword_or_id ) {
			return array();
		}

		$finished_ids = get_option( 'w2p_fix_index_finished_books', array() );
		$novels       = array();

		// 1. 若为纯数字，优先尝试精准 ID 定位
		if ( is_numeric( $keyword_or_id ) && intval( $keyword_or_id ) > 0 ) {
			$post_id = intval( $keyword_or_id );
			$post    = get_post( $post_id );
			if ( $post && 'novel' === $post->post_type && 'trash' !== $post->post_status ) {
				$novels[] = $post;
			}
		}

		// 2. 若未匹配到纯数字，或即使匹配到也可模糊匹配标题
		if ( empty( $novels ) ) {
			$search_term = '%' . $wpdb->esc_like( $keyword_or_id ) . '%';
			$sql         = $wpdb->prepare(
				"SELECT ID, post_title, post_status FROM {$wpdb->posts}
				 WHERE post_type = 'novel' AND post_status != 'trash' AND post_title LIKE %s
				 ORDER BY ID DESC LIMIT %d",
				$search_term,
				absint( $limit )
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $sql );
			if ( ! empty( $results ) ) {
				$novels = $results;
			}
		}

		$data = array();
		foreach ( $novels as $n ) {
			$id = intval( $n->ID );

			// 查询该小说关联的有效章节数
			$count_sql = $wpdb->prepare(
				"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'related_novel_id'
				 WHERE p.post_type = 'chapter' AND p.post_status != 'trash' AND pm.meta_value = %d",
				$id
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$chapter_count = intval( $wpdb->get_var( $count_sql ) );

			$data[] = array(
				'id'            => $id,
				'title'         => $n->post_title,
				'chapter_count' => $chapter_count,
				'is_finished'   => in_array( $id, $finished_ids, true ),
				'edit_link'     => get_edit_post_link( $id ),
			);
		}

		return $data;
	}

	/**
	 * 获取指定小说关联的所有章节，并调用权威方法计算预览新旧索引对比 (防 OOM: 不读取 post_content)
	 *
	 * @param int $novel_id 小说文章 ID
	 * @return array 包含 chapters 列表及统计信息的数组
	 */
	public function get_novel_chapters_preview( $novel_id ) {
		global $wpdb;

		$novel_id = absint( $novel_id );
		if ( $novel_id <= 0 ) {
			return array(
				'success'  => false,
				'message'  => __( 'Invalid novel ID.', 'wp-genius' ),
				'chapters' => array(),
			);
		}

		// 仅查询轻量必要字段与字符数，严禁读取庞大的 post_content
		$sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.menu_order,
			        CHAR_LENGTH(p.post_content) AS words,
			        pm_idx.meta_value AS current_index,
			        pm_vol.meta_value AS current_volume
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_rel ON p.ID = pm_rel.post_id AND pm_rel.meta_key = 'related_novel_id' AND pm_rel.meta_value = %d
			 LEFT JOIN {$wpdb->postmeta} pm_idx ON p.ID = pm_idx.post_id AND pm_idx.meta_key = 'chapter_index'
			 LEFT JOIN {$wpdb->postmeta} pm_vol ON p.ID = pm_vol.post_id AND pm_vol.meta_key = 'volume_name'
			 WHERE p.post_type = 'chapter' AND p.post_status != 'trash'
			 ORDER BY p.menu_order ASC, p.ID ASC",
			$novel_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );
		if ( empty( $rows ) ) {
			return array(
				'success'  => true,
				'novel_id' => $novel_id,
				'total'    => 0,
				'chapters' => array(),
			);
		}

		// 组装供 W2P_Novel_Helper 计算的轻量数组
		$input_chapters = array();
		foreach ( $rows as $r ) {
			$input_chapters[] = array(
				'id'            => intval( $r->ID ),
				'title'         => (string) $r->post_title,
				'words'         => intval( $r->words ),
				'volume'        => ! empty( $r->current_volume ) ? (string) $r->current_volume : '正文',
				'chapter_index' => ! empty( $r->current_index ) ? (string) $r->current_index : '',
				'old_index'     => ! empty( $r->current_index ) ? (string) $r->current_index : '-',
				'old_volume'    => ! empty( $r->current_volume ) ? (string) $r->current_volume : '-',
			);
		}

		// 调用核心权威计算静态方法
		$recalculated = W2P_Novel_Helper::recalculate_chapter_indexes( $input_chapters );

		$preview_list = array();
		foreach ( $recalculated as $c ) {
			$preview_list[] = array(
				'id'         => $c['id'],
				'title'      => $c['title'],
				'words'      => ! empty( $c['words'] ) ? intval( $c['words'] ) : 0,
				'new_index'  => $c['chapter_index'],
				'old_index'  => $c['old_index'],
				'new_volume' => $c['volume'],
				'old_volume' => $c['old_volume'],
				'edit_link'  => get_edit_post_link( $c['id'] ),
			);
		}

		return array(
			'success'  => true,
			'novel_id' => $novel_id,
			'total'    => count( $preview_list ),
			'chapters' => $preview_list,
		);
	}

	/**
	 * 保存小说章节（支持传入前端用户微调后的章节新索引和新分卷列表）
	 *
	 * @param int   $novel_id        小说 ID
	 * @param array $custom_chapters 可选的前端提交章节数组 [ { id, new_index, new_volume } ]
	 * @return array 处理结果统计
	 */
	public function save_novel_chapters_custom( $novel_id, $custom_chapters = array() ) {
		$novel_id = absint( $novel_id );
		if ( $novel_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid novel ID.', 'wp-genius' ),
			);
		}

		// 如果未传自定义章节列表，则走默认全量重新计算
		if ( empty( $custom_chapters ) || ! is_array( $custom_chapters ) ) {
			return $this->fix_single_novel( $novel_id );
		}

		$updated_count = 0;
		foreach ( $custom_chapters as $c ) {
			$chap_id = isset( $c['id'] ) ? absint( $c['id'] ) : 0;
			if ( $chap_id <= 0 || 'chapter' !== get_post_type( $chap_id ) ) {
				continue;
			}

			if ( isset( $c['new_index'] ) && '' !== $c['new_index'] && '-' !== $c['new_index'] ) {
				update_post_meta( $chap_id, 'chapter_index', sanitize_text_field( $c['new_index'] ) );
			}
			if ( isset( $c['new_volume'] ) && '' !== $c['new_volume'] && '-' !== $c['new_volume'] ) {
				update_post_meta( $chap_id, 'volume_name', sanitize_text_field( $c['new_volume'] ) );
			}

			++$updated_count;
			if ( 0 === $updated_count % 50 ) {
				clean_post_cache( $chap_id );
			}
		}

		$this->mark_novel_finished( $novel_id );

		return array(
			'success'       => true,
			'novel_id'      => $novel_id,
			'novel_title'   => get_the_title( $novel_id ),
			'chapter_count' => $updated_count,
			'updated_count' => $updated_count,
		);
	}

	/**
	 * 重建并保存单本小说的所有章节索引号和分卷信息（Part 1 & Part 2 核心服务方法）
	 *
	 * @param int $novel_id 小说文章 ID
	 * @return array 处理结果统计
	 */
	public function fix_single_novel( $novel_id ) {
		$novel_id = absint( $novel_id );
		$preview  = $this->get_novel_chapters_preview( $novel_id );

		if ( empty( $preview['chapters'] ) ) {
			// 无章节可处理时不标记完成，避免数据缺失的小说被永久跳过。
			return array(
				'success'       => true,
				'novel_id'      => $novel_id,
				'novel_title'   => get_the_title( $novel_id ),
				'chapter_count' => 0,
				'updated_count' => 0,
			);
		}

		$updated_count = 0;
		foreach ( $preview['chapters'] as $c ) {
			$chap_id    = intval( $c['id'] );
			$new_index  = sanitize_text_field( $c['new_index'] );
			$new_volume = sanitize_text_field( $c['new_volume'] );

			update_post_meta( $chap_id, 'chapter_index', $new_index );
			if ( ! empty( $new_volume ) && '-' !== $new_volume ) {
				update_post_meta( $chap_id, 'volume_name', $new_volume );
			}

			++$updated_count;
			if ( 0 === $updated_count % 50 ) {
				clean_post_cache( $chap_id );
			}
		}

		// 将该小说自动标记为已完成
		$this->mark_novel_finished( $novel_id );

		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		return array(
			'success'       => true,
			'novel_id'      => $novel_id,
			'novel_title'   => get_the_title( $novel_id ),
			'chapter_count' => count( $preview['chapters'] ),
			'updated_count' => $updated_count,
		);
	}

	/**
	 * 获取全自动全量扫描的待处理小说队列（排除已完成的书籍）
	 *
	 * @return array 待处理小说的 [ { id, title } ] 列表
	 */
	public function get_unfixed_novels() {
		global $wpdb;

		$finished_ids = get_option( 'w2p_fix_index_finished_books', array() );

		$where_finished = '';
		if ( ! empty( $finished_ids ) ) {
			$ids_str        = implode( ',', array_map( 'intval', $finished_ids ) );
			$where_finished = " AND ID NOT IN ($ids_str)";
		}

		// 获取所有已发布的有效小说
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = "SELECT ID, post_title FROM {$wpdb->posts}
		        WHERE post_type = 'novel' AND post_status = 'publish' {$where_finished}
		        ORDER BY ID ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );

		$list = array();
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				$list[] = array(
					'id'    => intval( $r->ID ),
					'title' => $r->post_title,
				);
			}
		}

		return $list;
	}

	/**
	 * 标记小说已处理完成
	 *
	 * @param int $novel_id 小说 ID
	 */
	public function mark_novel_finished( $novel_id ) {
		$novel_id = absint( $novel_id );
		if ( $novel_id <= 0 ) {
			return;
		}

		$finished_ids = get_option( 'w2p_fix_index_finished_books', array() );
		if ( ! is_array( $finished_ids ) ) {
			$finished_ids = array();
		}

		if ( ! in_array( $novel_id, $finished_ids, true ) ) {
			$finished_ids[] = $novel_id;
			update_option( 'w2p_fix_index_finished_books', $finished_ids );
		}
	}

	/**
	 * 清除所有已完成标记
	 */
	public function clear_progress() {
		delete_option( 'w2p_fix_index_finished_books' );
	}
}
