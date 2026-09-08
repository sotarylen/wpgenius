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
					'is_finished'   => in_array( $id, $finished_ids, true ),
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

		// 如果未传自定义章节列表，则走默认全量重新计算
		if ( empty( $custom_chapters ) || ! is_array( $custom_chapters ) ) {
			return $this->fix_single_novel( $novel_id );
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

		$this->mark_novel_finished( $novel_id );
		W2P_Novel_Helper::purge_novel_cache( $novel_id );

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
			clean_post_cache( $chap_id );
		}

		// 将该小说自动标记为已完成并主动刷新前后台缓存
		$this->mark_novel_finished( $novel_id );
		W2P_Novel_Helper::purge_novel_cache( $novel_id );

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

	/**
	 * 扫描孤儿章节（未关联小说或关联小说已不存在/已删除的章节）
	 *
	 * @return array 包含 total 与 items 的孤儿章节列表
	 */
	public function scan_orphan_chapters() {
		global $wpdb;

		// Step 1: 缺少 related_novel_id meta 的章节
		$sql_missing = "SELECT p.ID, p.post_title, p.post_date
		                FROM {$wpdb->posts} p
		                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'related_novel_id'
		                WHERE p.post_type = 'chapter' AND p.post_status != 'trash' AND pm.post_id IS NULL
		                ORDER BY p.ID DESC LIMIT 200";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$missing_rows = $wpdb->get_results( $sql_missing );

		// Step 2: 关联小说不存在或已被删除的章节（遵循两步差集法避免类型转换与跨表全扫描）
		// 2.1 获取当前所有有效的小说 ID 列表（非垃圾箱）
		$existing_novel_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'novel' AND post_status != 'trash'"
		);
		$existing_set = array_flip( array_map( 'intval', $existing_novel_ids ) );

		// 2.2 获取章节表中被引用的所有 distinct novel_id
		$meta_novel_ids = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE pm.meta_key = 'related_novel_id' AND p.post_type = 'chapter' AND p.post_status != 'trash'"
		);

		$invalid_novel_ids = array();
		if ( ! empty( $meta_novel_ids ) ) {
			foreach ( $meta_novel_ids as $nid_val ) {
				$nid_int = absint( $nid_val );
				if ( 0 === $nid_int || ! isset( $existing_set[ $nid_int ] ) ) {
					$invalid_novel_ids[] = (string) $nid_val;
				}
			}
		}

		$invalid_rows = array();
		if ( ! empty( $invalid_novel_ids ) ) {
			$slice_ids    = array_slice( $invalid_novel_ids, 0, 100 );
			$placeholders = implode( ',', array_fill( 0, count( $slice_ids ), '%s' ) );
			$sql_invalid  = $wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_date, pm.meta_value AS invalid_novel_id
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'related_novel_id'
				 WHERE p.post_type = 'chapter'
				   AND p.post_status != 'trash'
				   AND pm.meta_value IN ($placeholders)
				 ORDER BY p.ID DESC LIMIT 200",
				$slice_ids
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$invalid_rows = $wpdb->get_results( $sql_invalid );
		}

		$items    = array();
		$seen_ids = array();

		// 合并缺少关联的小说章节
		if ( ! empty( $missing_rows ) ) {
			foreach ( $missing_rows as $row ) {
				$id = intval( $row->ID );
				if ( ! isset( $seen_ids[ $id ] ) ) {
					$seen_ids[ $id ] = true;
					$items[]         = array(
						'id'         => $id,
						'title'      => ! empty( $row->post_title ) ? $row->post_title : __( '(Untitled)', 'wp-genius' ),
						'date'       => $row->post_date,
						'reason'     => 'missing_novel_id',
						'reason_txt' => __( 'Missing related novel ID', 'wp-genius' ),
						'novel_id'   => 0,
					);
				}
			}
		}

		// 合并关联小说已失效的小说章节
		if ( ! empty( $invalid_rows ) ) {
			foreach ( $invalid_rows as $row ) {
				$id = intval( $row->ID );
				if ( ! isset( $seen_ids[ $id ] ) ) {
					$seen_ids[ $id ] = true;
					$items[]         = array(
						'id'         => $id,
						'title'      => ! empty( $row->post_title ) ? $row->post_title : __( '(Untitled)', 'wp-genius' ),
						'date'       => $row->post_date,
						'reason'     => 'novel_not_found',
						'reason_txt' => sprintf(
							/* translators: %s: invalid novel ID */
							__( 'Related novel (#%s) deleted / missing', 'wp-genius' ),
							esc_html( $row->invalid_novel_id )
						),
						'novel_id'   => intval( $row->invalid_novel_id ),
					);
				}
			}
		}

		return array(
			'total' => count( $items ),
			'items' => $items,
		);
	}

	/**
	 * 清理/删除指定的孤儿章节
	 *
	 * @param array $chapter_ids 待删除的章节 ID 列表
	 * @return array 处理结果统计
	 */
	public function clean_orphan_chapters( $chapter_ids = array() ) {
		$chapter_ids   = array_filter( array_map( 'absint', (array) $chapter_ids ) );
		$cleaned_count = 0;

		if ( empty( $chapter_ids ) ) {
			return array(
				'success'       => true,
				'cleaned_count' => 0,
			);
		}

		foreach ( $chapter_ids as $chap_id ) {
			if ( 'chapter' === get_post_type( $chap_id ) ) {
				// 永久删除章节及其 meta 与缓存
				wp_delete_post( $chap_id, true );
				$cleaned_count++;

				if ( 0 === $cleaned_count % 50 ) {
					clean_post_cache( $chap_id );
				}
			}
		}

		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		return array(
			'success'       => true,
			'cleaned_count' => $cleaned_count,
		);
	}

	/**
	 * 章节完整性与断号体检（分批扫描排查断号与重复序号）
	 *
	 * @param int $offset 偏移量
	 * @param int $limit  每批扫描小说数量
	 * @return array 批次体检报告
	 */
	public function audit_novel_chapters_integrity( $offset = 0, $limit = 20 ) {
		global $wpdb;

		$offset = absint( $offset );
		$limit  = absint( $limit );
		if ( $limit <= 0 ) {
			$limit = 20;
		}

		// 1. 获取有效小说总数
		$total_novels = (int) $wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'novel' AND post_status = 'publish'"
		);

		// 2. 查询当前批次的小说
		$sql_novels = $wpdb->prepare(
			"SELECT ID, post_title FROM {$wpdb->posts}
			 WHERE post_type = 'novel' AND post_status = 'publish'
			 ORDER BY ID ASC LIMIT %d OFFSET %d",
			$limit,
			$offset
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$novels = $wpdb->get_results( $sql_novels );

		$issues        = array();
		$checked_count = count( $novels );
		$healthy_count = 0;

		if ( ! empty( $novels ) ) {
			$novel_ids  = wp_list_pluck( $novels, 'ID' );
			$id_strings = array_map( 'strval', $novel_ids );

			// 批量查询当前批次所有小说的章节 menu_order，消除 N+1 查询
			$placeholders = implode( ',', array_fill( 0, count( $id_strings ), '%s' ) );
			$chapters_sql = $wpdb->prepare(
				"SELECT p.ID, p.menu_order, pm.meta_value AS novel_id
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'related_novel_id'
				 WHERE p.post_type = 'chapter'
				   AND p.post_status != 'trash'
				   AND pm.meta_value IN ($placeholders)
				 ORDER BY pm.meta_value ASC, p.menu_order ASC, p.ID ASC",
				$id_strings
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$all_chapters = $wpdb->get_results( $chapters_sql );

			$chapters_by_novel = array();
			if ( ! empty( $all_chapters ) ) {
				foreach ( $all_chapters as $chap ) {
					$nid = intval( $chap->novel_id );
					if ( ! isset( $chapters_by_novel[ $nid ] ) ) {
						$chapters_by_novel[ $nid ] = array();
					}
					$chapters_by_novel[ $nid ][] = intval( $chap->menu_order );
				}
			}

			foreach ( $novels as $novel ) {
				$nid            = intval( $novel->ID );
				$orders         = isset( $chapters_by_novel[ $nid ] ) ? $chapters_by_novel[ $nid ] : array();
				$total_chapters = count( $orders );

				if ( 0 === $total_chapters ) {
					// 无章节的小说记为正常（或跳过）
					$healthy_count++;
					continue;
				}

				$gaps             = array();
				$duplicate_orders = array();
				$seen_orders      = array();
				$prev_order       = null;

				foreach ( $orders as $order ) {
					// 1. 重复序号检查
					if ( isset( $seen_orders[ $order ] ) ) {
						if ( ! in_array( $order, $duplicate_orders, true ) ) {
							$duplicate_orders[] = $order;
						}
					} else {
						$seen_orders[ $order ] = true;
					}

					// 2. 断号/跳号检查（非首章且 menu_order 差值大于 1）
					if ( null !== $prev_order ) {
						if ( $order > $prev_order + 1 ) {
							$gaps[] = $prev_order . ' -> ' . $order;
						}
					}
					$prev_order = $order;
				}

				if ( ! empty( $gaps ) || ! empty( $duplicate_orders ) ) {
					$issues[] = array(
						'novel_id'         => $nid,
						'title'            => $novel->post_title,
						'total_chapters'   => $total_chapters,
						'gaps'             => $gaps,
						'duplicate_orders' => $duplicate_orders,
					);
				} else {
					$healthy_count++;
				}
			}
		}

		$has_more = ( $offset + $checked_count ) < $total_novels;

		return array(
			'total_novels'  => $total_novels,
			'offset'        => $offset,
			'limit'         => $limit,
			'processed'     => $checked_count,
			'has_more'      => $has_more,
			'issues'        => $issues,
			'issues_count'  => count( $issues ),
			'healthy_count' => $healthy_count,
		);
	}
}
