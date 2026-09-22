<?php
/**
 * Novel Manager — Statistics Class
 *
 * 章节字数统计与书籍汇总回写。
 *
 * 职责边界（单一):
 *  1. 计算单篇 chapter 的正文字数，并落库到 chapter 的自定义字段 word-count。
 *  2. 按 related_novel_id 汇总某本 novel 的「已发布章节数」与「总字数」。
 *  3. 将汇总结果回写到 novel 的自定义字段 count-chapters / count-words。
 *  4. 提供批量游标扫描接口，供后台「统计校准」工具分批处理 30 万量级的章节。
 *
 * 统计口径（与文档导入器 W2P_Novel_Importer 完全一致）：
 *  字数 = mb_strlen( strip_tags( 正文 ), 'UTF-8' )
 *  范围 = 仅 post_status = publish 的 chapter（与前台读者可见范围保持一致）
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_Novel_Stats {

	/**
	 * chapter 自定义字段：单章字数
	 */
	const CHAPTER_WORDS_KEY = 'word-count';

	/**
	 * chapter 字数字段的 ACF field key（注册见 register_acf_fields）
	 */
	const CHAPTER_WORDS_FIELD_KEY = 'field_w2p_chapter_word_count';

	/**
	 * novel 自定义字段：章节数
	 */
	const NOVEL_CHAPTERS_KEY = 'count-chapters';

	/**
	 * novel 自定义字段：总字数
	 */
	const NOVEL_WORDS_KEY = 'count-words';

	/**
	 * 关联字段：chapter -> novel
	 */
	const RELATED_KEY = 'related_novel_id';

	/**
	 * 脏队列 option（未能在本请求内刷新的 novel id 集合）
	 */
	const DIRTY_OPTION = 'w2p_novel_stats_dirty';

	/**
	 * 单批次默认处理量
	 */
	const DEFAULT_BATCH = 500;

	/**
	 * 本请求内待刷新的 novel id（内存暂存，避免逐章写 option）
	 *
	 * @var array
	 */
	private static $runtime_dirty = array();

	/**
	 * 本请求是否已注册过 shutdown 钩子
	 *
	 * @var bool
	 */
	private static $shutdown_registered = false;

	// =====================================================================
	// 计算层
	// =====================================================================

	/**
	 * 计算正文字数（纯字符数，与导入器口径一致）
	 *
	 * @param string $html 正文 HTML
	 * @return int
	 */
	public static function count_words( $html ) {
		$text = strip_tags( (string) $html );

		// 还原 HTML 实体（&nbsp; &amp; 等），否则会被算作多个字符
		if ( '' !== $text && false !== strpos( $text, '&' ) ) {
			$decoded = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( '' !== $decoded ) {
				$text = $decoded;
			}
		}

		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text, 'UTF-8' );
		}

		// mbstring 缺失时的兜底（按 UTF-8 字符计数）
		return preg_match_all( '/./us', $text, $m );
	}

	/**
	 * 刷新单章字数并写入 chapter 的 word-count 字段
	 *
	 * 仅在数值发生变化时才执行写库，避免无意义的 meta 写入与钩子触发。
	 *
	 * @param int $chapter_id 章节 post_id
	 * @return int|false 返回字数，章节不存在返回 false
	 */
	public static function refresh_chapter_words( $chapter_id ) {
		$chapter_id = absint( $chapter_id );
		if ( ! $chapter_id || 'chapter' !== get_post_type( $chapter_id ) ) {
			return false;
		}

		$post = get_post( $chapter_id );
		if ( ! $post ) {
			return false;
		}

		$words = self::count_words( $post->post_content );
		$old   = get_post_meta( $chapter_id, self::CHAPTER_WORDS_KEY, true );

		if ( (string) $old === (string) $words ) {
			self::ensure_acf_reference( $chapter_id );
			return $words;
		}

		update_post_meta( $chapter_id, self::CHAPTER_WORDS_KEY, $words );
		self::ensure_acf_reference( $chapter_id );

		return $words;
	}

	/**
	 * 补齐 ACF 的 field key 引用 (_word-count)
	 *
	 * 批量回填时若逐个走 update_field()，等于给 30 万章各加两次写库。
	 * 这里直接补引用，让字段被 ACF 完整接管，代价仅一次读取。
	 *
	 * @param int $chapter_id
	 * @return void
	 */
	private static function ensure_acf_reference( $chapter_id ) {
		$ref_key = '_' . self::CHAPTER_WORDS_KEY;
		$ref     = get_post_meta( $chapter_id, $ref_key, true );

		if ( self::CHAPTER_WORDS_FIELD_KEY !== $ref ) {
			update_post_meta( $chapter_id, $ref_key, self::CHAPTER_WORDS_FIELD_KEY );
		}
	}

	// =====================================================================
	// 批量写库（性能核心）
	//
	// 以下两个方法必须把占位符拼进 SQL（数量是运行时才知道的），
	// 这会让 PHPCS 的 PreparedSQL 系列 sniff 误报。占位符本身由
	// array_fill() 按数组长度生成、值全部走 $wpdb->prepare()，
	// 不存在注入面，因此在这一段集中关闭告警。
	// =====================================================================
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

	/**
	 * 批量落库 chapter 字数
	 *
	 * 为什么不用 update_post_meta：实测 6.59 ms/条（30 万章 ≈ 34 分钟），
	 * 因为它每条都要走一波 hook + 对象缓存（这站点开了 Redis drop-in）。
	 * 改成按批拼 SQL 后是 0.077 ms/条，快了约 86 倍。
	 * 这是整个统计功能能不能用的分水岭。
	 *
	 * 特意没用 INSERT ... ON DUPLICATE KEY UPDATE：那依赖 (post_id, meta_key)
	 * 上有唯一索引，而本环境的 postmeta 主键恰好被改成 (post_id, meta_key, meta_id)
	 * 才碰巧成立。WP 原生 schema 主键只有 meta_id，表结构一旦还原就会插出重复行。
	 * 这里改成「已存在按 meta_id 批量 UPDATE + 不存在批量 INSERT」，两条语句搞定。
	 *
	 * @param array $rows post_id => array(
	 *                      words      => int,
	 *                      value      => bool,  是否要写正文字数
	 *                      meta_id    => int,   word-count 现有行的 meta_id（0=不存在）
	 *                      ref        => bool,  是否要补 ACF 引用
	 *                      ref_meta_id=> int,   _word-count 现有行的 meta_id（0=不存在）
	 *                    )
	 * @return int 涉及写库的章节数
	 */
	private static function bulk_write_words( array $rows ) {
		global $wpdb;

		if ( empty( $rows ) ) {
			return 0;
		}

		$updates = array(); // meta_id => 新值
		$inserts = array(); // array( post_id, meta_key, meta_value )
		$touched = array(); // 需要失效 meta 缓存的 post_id

		foreach ( $rows as $post_id => $info ) {
			if ( ! empty( $info['value'] ) ) {
				if ( ! empty( $info['meta_id'] ) ) {
					$updates[ (int) $info['meta_id'] ] = (string) $info['words'];
				} else {
					$inserts[] = array( $post_id, self::CHAPTER_WORDS_KEY, (string) $info['words'] );
				}
				$touched[ $post_id ] = true;
			}

			if ( ! empty( $info['ref'] ) ) {
				if ( ! empty( $info['ref_meta_id'] ) ) {
					$updates[ (int) $info['ref_meta_id'] ] = self::CHAPTER_WORDS_FIELD_KEY;
				} else {
					$inserts[] = array( $post_id, '_' . self::CHAPTER_WORDS_KEY, self::CHAPTER_WORDS_FIELD_KEY );
				}
				$touched[ $post_id ] = true;
			}
		}

		// ---- 已存在的行：一条 CASE ... WHEN 批量 UPDATE ----
		foreach ( array_chunk( $updates, 500, true ) as $chunk ) {
			$case      = array();
			$ids       = array();
			$case_args = array();
			$in_args   = array();

			// ⚠️ 顺序陷阱：SQL 里所有 CASE 占位符排在前、IN 占位符排在后，
			// 参数必须按同样的两段顺序拼，边拼占位符边 append 会错位导致写错行。
			foreach ( $chunk as $meta_id => $value ) {
				$case[]      = 'WHEN %d THEN %s';
				$case_args[] = $meta_id;
				$case_args[] = $value;

				$ids[]     = '%d';
				$in_args[] = $meta_id;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- 占位符由数组长度生成
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = CASE meta_id "
					. implode( ' ', $case )
					. ' END WHERE meta_id IN ( ' . implode( ', ', $ids ) . ' )',
					array_merge( $case_args, $in_args )
				)
			);
		}

		// ---- 不存在的行：一条多值 INSERT ----
		foreach ( array_chunk( $inserts, 200 ) as $chunk ) {
			$placeholders = array();
			$args         = array();

			foreach ( $chunk as $row ) {
				$placeholders[] = '(%d, %s, %s)';
				$args[]         = $row[0];
				$args[]         = $row[1];
				$args[]         = $row[2];
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- 占位符由数组长度生成
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->postmeta} ( post_id, meta_key, meta_value ) VALUES "
					. implode( ', ', $placeholders ),
					$args
				)
			);
		}

		// 绕过 WP 原生 API 写库后必须手动失效 meta 缓存，否则后续读到的还是旧值
		foreach ( array_keys( $touched ) as $pid ) {
			wp_cache_delete( $pid, 'post_meta' );
		}

		return count( $touched );
	}

	/**
	 * 处理一批章节：算字数 -> 比对 -> 批量落库
	 *
	 * @param array $rows post_id => post_content
	 * @return int 本批实际写库的章节数
	 */
	private static function sync_words_chunk( array $rows ) {
		global $wpdb;

		$ids = array_map( 'absint', array_keys( $rows ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- 占位符由数组长度生成
		$existing = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta}
				WHERE post_id IN ( {$placeholders} )
					AND meta_key IN ( %s, %s )",
				array_merge(
					$ids,
					array( self::CHAPTER_WORDS_KEY, '_' . self::CHAPTER_WORDS_KEY )
				)
			)
		);

		$map = array();
		foreach ( (array) $existing as $r ) {
			$map[ (int) $r->post_id ][ $r->meta_key ] = array(
				'meta_id' => (int) $r->meta_id,
				'value'   => $r->meta_value,
			);
		}

		$to_write = array();

		foreach ( $rows as $post_id => $content ) {
			$pid   = absint( $post_id );
			$words = self::count_words( $content );

			$cur_id  = isset( $map[ $pid ][ self::CHAPTER_WORDS_KEY ]['meta_id'] ) ? $map[ $pid ][ self::CHAPTER_WORDS_KEY ]['meta_id'] : 0;
			$cur_val = isset( $map[ $pid ][ self::CHAPTER_WORDS_KEY ]['value'] ) ? $map[ $pid ][ self::CHAPTER_WORDS_KEY ]['value'] : null;
			$ref_id  = isset( $map[ $pid ][ '_' . self::CHAPTER_WORDS_KEY ]['meta_id'] ) ? $map[ $pid ][ '_' . self::CHAPTER_WORDS_KEY ]['meta_id'] : 0;
			$ref_val = isset( $map[ $pid ][ '_' . self::CHAPTER_WORDS_KEY ]['value'] ) ? $map[ $pid ][ '_' . self::CHAPTER_WORDS_KEY ]['value'] : null;

			$need_val = ( null === $cur_val || (string) $cur_val !== (string) $words );
			$need_ref = ( self::CHAPTER_WORDS_FIELD_KEY !== $ref_val );

			if ( $need_val || $need_ref ) {
				$to_write[ $pid ] = array(
					'words'       => $words,
					'value'       => $need_val,
					'meta_id'     => $cur_id,
					'ref'         => $need_ref,
					'ref_meta_id' => $ref_id,
				);
			}
		}

		return self::bulk_write_words( $to_write );
	}

	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

	// =====================================================================
	// 脏队列（延迟汇总，避免批量导入时逐章重算整本书）
	// =====================================================================

	/**
	 * 标记某本书需要重新汇总
	 *
	 * @param int $novel_id novel post_id
	 * @return void
	 */
	public static function mark_dirty( $novel_id ) {
		$novel_id = absint( $novel_id );
		if ( ! $novel_id ) {
			return;
		}

		self::$runtime_dirty[ $novel_id ] = $novel_id;

		if ( ! self::$shutdown_registered ) {
			self::$shutdown_registered = true;
			add_action( 'shutdown', array( __CLASS__, 'flush_dirty' ), 20 );
		}
	}

	/**
	 * 获取 chapter 所属 novel id
	 *
	 * @param int $chapter_id
	 * @return int
	 */
	public static function get_chapter_novel_id( $chapter_id ) {
		return absint( get_post_meta( $chapter_id, self::RELATED_KEY, true ) );
	}

	/**
	 * 刷新脏队列中的所有书籍
	 *
	 * 未处理成功的会写回 option，交给下一次请求或计划任务补跑。
	 *
	 * @return int 本次成功处理的书籍数量
	 */
	public static function flush_dirty() {
		$stored = get_option( self::DIRTY_OPTION, array() );
		$stored = is_array( $stored ) ? array_map( 'absint', $stored ) : array();

		$pending             = array_unique( array_merge( $stored, self::$runtime_dirty ) );
		self::$runtime_dirty = array();

		if ( empty( $pending ) ) {
			return 0;
		}

		$remaining = array();
		$done      = 0;

		foreach ( $pending as $novel_id ) {
			if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) ) {
				continue;
			}

			if ( false === self::sync_novel( $novel_id ) ) {
				$remaining[] = $novel_id;
				continue;
			}

			++$done;
		}

		update_option( self::DIRTY_OPTION, $remaining, false );

		return $done;
	}

	/**
	 * 获取脏队列快照（后台展示用）
	 *
	 * @return array
	 */
	public static function get_dirty_ids() {
		$stored = get_option( self::DIRTY_OPTION, array() );
		return is_array( $stored ) ? array_map( 'absint', $stored ) : array();
	}

	// =====================================================================
	// 汇总层
	// =====================================================================

	/**
	 * 查询指定小说的章节数与总字数（单次 JOIN，已发布章节）
	 *
	 * 依赖 word-count 已落库，SUM 走 meta_value 索引，代价接近常量级。
	 *
	 * ⚠️ 两个关键写法（都是实测踩出来的坑，改动前先看这里的说明）：
	 *  1. meta_value 是 LONGTEXT，用整数去比（pm.meta_value = 1860413）会触发
	 *     隐式类型转换导致索引失效 —— 因此必须传字符串。实测：1.93s -> 2.3ms。
	 *  2. 必须 STRAIGHT_JOIN 从 pm_rel 出发。交给优化器的话它会挑 wp_posts
	 *     的 type_status_date 索引全扫 30 万条 chapter，再回头 join。
	 *     实测（6892 章那本）：348ms -> 64ms。
	 *
	 * @param int $novel_id
	 * @return array { chapters:int, words:int }
	 */
	public static function query_novel_stats( $novel_id ) {
		global $wpdb;

		$novel_id = absint( $novel_id );
		if ( ! $novel_id ) {
			return array(
				'chapters' => 0,
				'words'    => 0,
			);
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT( p.ID ) AS chapters,
					COALESCE( SUM( CAST( pm_words.meta_value AS UNSIGNED ) ), 0 ) AS words
				FROM {$wpdb->postmeta} pm_rel
				STRAIGHT_JOIN {$wpdb->posts} p
					ON p.ID = pm_rel.post_id
					AND p.post_type = %s
					AND p.post_status = %s
				LEFT JOIN {$wpdb->postmeta} pm_words
					ON pm_words.post_id = p.ID
					AND pm_words.meta_key = %s
				WHERE pm_rel.meta_key = %s
					AND pm_rel.meta_value = %s",
				'chapter',
				'publish',
				self::CHAPTER_WORDS_KEY,
				self::RELATED_KEY,
				(string) $novel_id
			)
		);

		if ( ! $row ) {
			return array(
				'chapters' => 0,
				'words'    => 0,
			);
		}

		return array(
			'chapters' => (int) $row->chapters,
			'words'    => (int) $row->words,
		);
	}

	/**
	 * 汇总并回写指定小说的章节数与字数
	 *
	 * @param int $novel_id
	 * @return array|false { chapters, words, changed } ，novel 无效返回 false
	 */
	public static function sync_novel( $novel_id ) {
		$novel_id = absint( $novel_id );
		if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) ) {
			return false;
		}

		$stats = self::query_novel_stats( $novel_id );

		$old_chapters = (int) get_post_meta( $novel_id, self::NOVEL_CHAPTERS_KEY, true );
		$old_words    = (int) get_post_meta( $novel_id, self::NOVEL_WORDS_KEY, true );

		$changed = false;

		if ( $old_chapters !== $stats['chapters'] ) {
			self::write_novel_field( $novel_id, self::NOVEL_CHAPTERS_KEY, $stats['chapters'] );
			$changed = true;
		}

		if ( $old_words !== $stats['words'] ) {
			self::write_novel_field( $novel_id, self::NOVEL_WORDS_KEY, $stats['words'] );
			$changed = true;
		}

		// 空值时补写：确保字段物理存在（ACF 取值为空字符串而非 null）
		if ( ! $changed && '' === get_post_meta( $novel_id, self::NOVEL_CHAPTERS_KEY, true ) ) {
			self::write_novel_field( $novel_id, self::NOVEL_CHAPTERS_KEY, $stats['chapters'] );
			self::write_novel_field( $novel_id, self::NOVEL_WORDS_KEY, $stats['words'] );
			$changed = true;
		}

		return array(
			'chapters' => $stats['chapters'],
			'words'    => $stats['words'],
			'changed'  => $changed,
		);
	}

	/**
	 * 统一写 novel 字段（post_meta + ACF 双写）
	 *
	 * @param int    $novel_id
	 * @param string $key
	 * @param mixed  $value
	 * @return void
	 */
	private static function write_novel_field( $novel_id, $key, $value ) {
		update_post_meta( $novel_id, $key, $value );
		if ( function_exists( 'update_field' ) ) {
			update_field( $key, $value, $novel_id );
		}
	}

	/**
	 * 单本全量重算：先扫这本书的所有章节字数，再汇总回写
	 *
	 * 为什么不能只做汇总 —— sync_novel() 依赖 chapter 的 word-count 缓存，
	 * 历史数据（从未扫过）缓存为空，直接汇总会得到「有章节数、字数却偏小甚至为 0」。
	 * 所以「单篇触发」必须两步走：先补这本书自己的章节缓存，再聚合。
	 *
	 * 只处理这一本书的章节（平均约 370 章，最大 6892 章），
	 * 按 ID 分块读取正文，避免一次把整本书的正文读进内存。
	 *
	 * @param int $novel_id
	 * @param int $chunk    每块处理的章节数
	 * @return array|false { chapters, words, changed, scanned, refreshed }，novel 无效返回 false
	 */
	public static function recount_novel( $novel_id, $chunk = 200 ) {
		global $wpdb;

		$novel_id = absint( $novel_id );
		if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) ) {
			return false;
		}

		$chunk = $chunk > 0 ? absint( $chunk ) : 200;

		// 写法说明见 query_novel_stats()：字符串比较 + STRAIGHT_JOIN，
		// 这里不加 ORDER BY —— 6892 章的 filesort 要多花 240ms，而这组 ID 顺序无关紧要。
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->postmeta} pm
				STRAIGHT_JOIN {$wpdb->posts} p
					ON p.ID = pm.post_id
					AND p.post_type = 'chapter'
					AND p.post_status = 'publish'
				WHERE pm.meta_key = %s
					AND pm.meta_value = %s",
				self::RELATED_KEY,
				(string) $novel_id
			)
		);

		$ids = array_map( 'absint', is_array( $ids ) ? $ids : array() );

		$refreshed = 0;

		foreach ( array_chunk( $ids, $chunk ) as $batch ) {
			$placeholders = implode( ', ', array_fill( 0, count( $batch ), '%d' ) );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN ( {$placeholders} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- 占位符由数组长度生成，值全部走 prepare
					$batch
				)
			);

			if ( empty( $rows ) ) {
				continue;
			}

			$payload = array();
			foreach ( $rows as $row ) {
				$payload[ (int) $row->ID ] = $row->post_content;
			}

			$refreshed += self::sync_words_chunk( $payload );

			// 释放 FC 结果集与 WP 内部缓存，避免整本书的正文堆在内存里
			$wpdb->flush();
			wp_cache_flush_runtime();
		}

		$stats = self::sync_novel( $novel_id );
		if ( false === $stats ) {
			return false;
		}

		return array(
			'chapters'  => $stats['chapters'],
			'words'     => $stats['words'],
			'changed'   => $stats['changed'],
			'scanned'   => count( $ids ),
			'refreshed' => $refreshed,
		);
	}

	// =====================================================================
	// 批量扫描（后台「统计校准」工具，游标深分页）
	// =====================================================================

	/**
	 * 批量刷新 chapter 字数
	 *
	 * @param int $cursor 上次处理到的最大 chapter ID（0 表示从头开始）
	 * @param int $limit  本批处理量
	 * @return array { processed, written, last_id, has_more }
	 */
	public static function scan_chapters_batch( $cursor = 0, $limit = 0 ) {
		global $wpdb;

		$cursor = absint( $cursor );
		$limit  = $limit > 0 ? absint( $limit ) : self::DEFAULT_BATCH;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- 游标与数量均为整型
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				WHERE post_type = 'chapter'
					AND post_status = 'publish'
					AND ID > %d
				ORDER BY ID ASC
				LIMIT %d",
				$cursor,
				$limit
			)
		);

		$processed = 0;
		$written   = 0;
		$last_id   = $cursor;

		if ( ! empty( $rows ) ) {
			$payload = array();

			foreach ( $rows as $row ) {
				++$processed;
				$last_id                   = (int) $row->ID;
				$payload[ (int) $row->ID ] = $row->post_content;
			}

			$written = self::sync_words_chunk( $payload );

			$wpdb->flush();
			wp_cache_flush_runtime();
		}

		return array(
			'processed' => $processed,
			'written'   => $written,
			'last_id'   => $last_id,
			'has_more'  => count( $rows ) === $limit,
		);
	}

	/**
	 * 批量汇总 novel（按 ID 游标）
	 *
	 * @param int $cursor
	 * @param int $limit
	 * @return array { processed, changed, last_id, has_more }
	 */
	public static function sync_novels_batch( $cursor = 0, $limit = 0 ) {
		global $wpdb;

		$cursor = absint( $cursor );
		$limit  = $limit > 0 ? absint( $limit ) : 100;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'novel'
					AND ID > %d
				ORDER BY ID ASC
				LIMIT %d",
				$cursor,
				$limit
			)
		);

		$processed = 0;
		$changed   = 0;
		$last_id   = $cursor;

		foreach ( $ids as $id ) {
			++$processed;
			$last_id = (int) $id;

			$res = self::sync_novel( $id );
			if ( ! empty( $res['changed'] ) ) {
				++$changed;
			}
		}

		wp_cache_flush_runtime();

		return array(
			'processed' => $processed,
			'changed'   => $changed,
			'last_id'   => $last_id,
			'has_more'  => count( $ids ) === $limit,
		);
	}

	// =====================================================================
	// 概览统计（后台展示）
	// =====================================================================

	/**
	 * 获取全站统计概览
	 *
	 * @return array
	 */
	public static function get_overview() {
		global $wpdb;

		$chapter_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'chapter' AND post_status = 'publish'"
		);

		$chapter_synced = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'chapter' AND p.post_status = 'publish'
				WHERE pm.meta_key = %s AND pm.meta_value <> ''",
				self::CHAPTER_WORDS_KEY
			)
		);

		$novel_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'novel' AND post_status = 'publish'"
		);

		$novel_synced = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'novel' AND p.post_status = 'publish'
				WHERE pm.meta_key = %s AND pm.meta_value <> '' AND pm.meta_value <> '0'",
				self::NOVEL_WORDS_KEY
			)
		);

		return array(
			'chapter_total'  => $chapter_total,
			'chapter_synced' => $chapter_synced,
			'novel_total'    => $novel_total,
			'novel_synced'   => $novel_synced,
		);
	}

	// =====================================================================
	// ACF 字段注册（chapter: word-count 只读）
	// =====================================================================

	/**
	 * 注册 chapter 的字数只读字段
	 *
	 * 幂等：重复调用只覆盖 local field group，不会产生副本。
	 *
	 * @return void
	 */
	public static function register_acf_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_w2p_chapter_stats',
				'title'                 => __( 'Chapter Statistics', 'wp-genius' ),
				'fields'                => array(
					array(
						'key'           => 'field_w2p_chapter_word_count',
						'label'         => __( 'Word Count', 'wp-genius' ),
						'name'          => self::CHAPTER_WORDS_KEY,
						'type'          => 'number',
						'instructions'  => __( 'Auto-calculated by Novel Manager. Counted as characters after stripping HTML tags.', 'wp-genius' ),
						'required'      => 0,
						'readonly'      => 1,
						'default_value' => '',
						'placeholder'   => '',
						'prepend'       => '',
						'append'        => '',
						'min'           => '',
						'max'           => '',
						'step'          => '',
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'chapter',
						),
					),
				),
				'menu_order'            => 100,
				'position'              => 'side',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen'        => '',
				'active'                => true,
				'description'           => '',
			)
		);
	}
}
