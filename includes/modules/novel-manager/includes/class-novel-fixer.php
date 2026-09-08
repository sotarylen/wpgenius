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

		$novels = array();

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
		if ( ! empty( $novels ) ) {
			$id_strings = array();
			foreach ( $novels as $n ) {
				$id_strings[] = (string) intval( $n->ID );
			}

			// 消除 N+1 循环查询：使用动态 %s 占位符单条批量 GROUP BY 查询章节数
			$placeholders = implode( ',', array_fill( 0, count( $id_strings ), '%s' ) );
			$count_sql    = $wpdb->prepare(
				"SELECT pm.meta_value AS novel_id, COUNT(p.ID) AS chapter_count
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE pm.meta_key = 'related_novel_id'
				   AND pm.meta_value IN ($placeholders)
				   AND p.post_type = 'chapter'
				   AND p.post_status != 'trash'
				 GROUP BY pm.meta_value",
				$id_strings
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_rows = $wpdb->get_results( $count_sql );
			$counts_map = array();
			if ( ! empty( $count_rows ) ) {
				foreach ( $count_rows as $row ) {
					$counts_map[ intval( $row->novel_id ) ] = intval( $row->chapter_count );
				}
			}

			foreach ( $novels as $n ) {
				$id            = intval( $n->ID );
				$chapter_count = isset( $counts_map[ $id ] ) ? $counts_map[ $id ] : 0;
				$data[]        = array(
					'id'            => $id,
					'title'         => $n->post_title,
					'chapter_count' => $chapter_count,
					'edit_link'     => admin_url( 'post.php?post=' . $id . '&action=edit' ),
				);
			}
		}

		return $data;
	}

	/**
	 * 获取指定小说关联的所有章节，并调用权威方法计算预览新旧索引对比 (极致性能: 去除 CHAR_LENGTH，走两步索引查询)
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

		// 第一步：走 meta_value 字符串索引秒查关联章节 ID (实测 2ms)
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'related_novel_id' AND meta_value = %s",
				(string) $novel_id
			)
		);

		if ( empty( $post_ids ) ) {
			return array(
				'success'  => true,
				'novel_id' => $novel_id,
				'total'    => 0,
				'chapters' => array(),
			);
		}

		$post_ids = array_values( array_unique( array_map( 'absint', $post_ids ) ) );
		if ( empty( $post_ids ) ) {
			return array(
				'success'  => true,
				'novel_id' => $novel_id,
				'total'    => 0,
				'chapters' => array(),
			);
		}

		// 第二步：批量动态占位符查询文章基础信息与对应元数据
		$p_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$posts_sql      = $wpdb->prepare(
			"SELECT ID, post_title, menu_order FROM {$wpdb->posts}
			 WHERE ID IN ($p_placeholders) AND post_type = 'chapter' AND post_status != 'trash'
			 ORDER BY menu_order ASC, ID ASC",
			$post_ids
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $posts_sql );
		if ( empty( $rows ) ) {
			return array(
				'success'  => true,
				'novel_id' => $novel_id,
				'total'    => 0,
				'chapters' => array(),
			);
		}

		$valid_ids      = wp_list_pluck( $rows, 'ID' );
		$v_placeholders = implode( ',', array_fill( 0, count( $valid_ids ), '%d' ) );
		$meta_sql       = $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE post_id IN ($v_placeholders) AND meta_key IN ('chapter_index', 'volume_name')",
			$valid_ids
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$meta_rows = $wpdb->get_results( $meta_sql );
		$meta_map  = array();
		if ( ! empty( $meta_rows ) ) {
			foreach ( $meta_rows as $mr ) {
				$meta_map[ intval( $mr->post_id ) ][ $mr->meta_key ] = $mr->meta_value;
			}
		}

		// 组装供 W2P_Novel_Helper 计算的轻量数组
		$input_chapters = array();
		foreach ( $rows as $r ) {
			$cid   = intval( $r->ID );
			$c_idx = isset( $meta_map[ $cid ]['chapter_index'] ) ? (string) $meta_map[ $cid ]['chapter_index'] : '';
			$c_vol = isset( $meta_map[ $cid ]['volume_name'] ) ? (string) $meta_map[ $cid ]['volume_name'] : '正文';
			$input_chapters[] = array(
				'id'            => $cid,
				'title'         => (string) $r->post_title,
				'words'         => '-',
				'volume'        => $c_vol ? $c_vol : '正文',
				'chapter_index' => $c_idx,
				'old_index'     => $c_idx ? $c_idx : '-',
				'old_volume'    => $c_vol ? $c_vol : '-',
			);
		}

		// 调用核心权威计算静态方法
		$recalculated = W2P_Novel_Helper::recalculate_chapter_indexes( $input_chapters );

		$preview_list = array();
		foreach ( $recalculated as $c ) {
			$preview_list[] = array(
				'id'         => $c['id'],
				'title'      => $c['title'],
				'words'      => '-',
				'new_index'  => $c['chapter_index'],
				'old_index'  => $c['old_index'],
				'new_volume' => $c['volume'],
				'old_volume' => $c['old_volume'],
				'edit_link'  => admin_url( 'post.php?post=' . $c['id'] . '&action=edit' ),
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
	 * @param array $custom_chapters 可选的前端提交章节数组 [ { id, new_index, new_volume, title } ]
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

		if ( empty( $custom_chapters ) || ! is_array( $custom_chapters ) ) {
			return array(
				'success' => false,
				'message' => __( 'No chapter data provided to update.', 'wp-genius' ),
			);
		}

		$updated_count = 0;
		add_filter( 'smart_aui_skip_post_processing', '__return_true' );
		try {
			foreach ( $custom_chapters as $c ) {
				$chap_id = isset( $c['id'] ) ? absint( $c['id'] ) : 0;
				if ( $chap_id <= 0 || 'chapter' !== get_post_type( $chap_id ) ) {
					continue;
				}

				// 1. 检查并更新章节标题 (支持 new_title 或 title)
				$title_val = ! empty( $c['new_title'] ) ? $c['new_title'] : ( ! empty( $c['title'] ) ? $c['title'] : '' );
				if ( ! empty( $title_val ) ) {
					$new_title = sanitize_text_field( $title_val );
					$cur_post  = get_post( $chap_id );
					if ( $cur_post && $cur_post->post_title !== $new_title ) {
						wp_update_post(
							array(
								'ID'         => $chap_id,
								'post_title' => $new_title,
							)
						);
					}
				}

				// 2. 检查并更新章节索引 (兼容 new_index 或 chapter_index)
				$idx_val = isset( $c['new_index'] ) ? $c['new_index'] : ( isset( $c['chapter_index'] ) ? $c['chapter_index'] : null );
				if ( null !== $idx_val && '' !== $idx_val && '-' !== $idx_val ) {
					update_post_meta( $chap_id, 'chapter_index', sanitize_text_field( $idx_val ) );
				}

				// 3. 检查并更新分卷名称 (兼容 new_volume 或 volume)
				$vol_val = isset( $c['new_volume'] ) ? $c['new_volume'] : ( isset( $c['volume'] ) ? $c['volume'] : null );
				if ( null !== $vol_val && '' !== $vol_val && '-' !== $vol_val ) {
					update_post_meta( $chap_id, 'volume_name', sanitize_text_field( $vol_val ) );
				}

				++$updated_count;
				clean_post_cache( $chap_id );
			}
		} finally {
			remove_filter( 'smart_aui_skip_post_processing', '__return_true' );
		}

		W2P_Novel_Helper::purge_novel_cache( $novel_id );

		return array(
			'success'       => true,
			'novel_id'      => $novel_id,
			'novel_title'   => get_the_title( $novel_id ),
			'chapter_count' => $updated_count,
			'updated_count' => $updated_count,
		);
	}

}
