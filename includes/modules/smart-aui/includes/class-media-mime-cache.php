<?php
/**
 * Smart AUI — 媒体库性能优化（get_available_post_mime_types 三级缓存 + CLI）
 *
 * 迁移自子主题 Impreza-child/functions.php [任务1]（行 303–473）与
 * inc/media-perf/cli-verify.php（media-perf-verify 命令），重写为插件内独立类。
 *
 * 背景：媒体库/上传界面会调用 get_available_post_mime_types('attachment')，
 *       触发 SELECT DISTINCT post_mime_type ... 在约百万附件下表扫描 ~20s。
 *       本类通过 pre_get_available_post_mime_types 过滤器短路
 *       （WP 7.1+ 核心已提供该过滤器，见 wp-includes/post.php:8658），
 *       结果缓存进 option，三级缓存 + 双写 + 命中日志。
 *
 * 三级缓存：
 *   L1  Redis（wp_cache_get，group='w2p'，TTL 12h）—— web 主缓存。
 *   L2  wp_options（autoload=auto）—— CLI 兜底，也可回填 Redis。
 *   L3  fallback SQL：双写回 Redis + wp_options。
 *
 * option / Redis key 与子主题完全同名（w2p_media_mime_types*，group 'w2p'），
 * 迁移期共享缓存、不冷启动双份。
 *
 * 并存期（子主题代码未删除时）：
 *   - pre_get_available_post_mime_types：function_exists('w2p_short_circuit_mime_types')
 *     为真 → 直接透传 $null，由子主题接管（链式短路结果一致、仅重复执行与日志）。
 *   - cli_init：function_exists('w2p_register_cli_commands') 或 WP_CLI::has_command
 *     命中 → 跳过自身注册并 WP_CLI::warning 提示删除子主题代码。并存期插件
 *     完全不注册，防止 WP_CLI::add_command 同名抛异常让所有 wp 命令不可用。
 *
 * 失效方式（C 方案，auto-flush 禁用）：CLI wp media-mime-flush（双路全清）/
 * 手动 flush_cache() / 12h 自然过期。修正子主题缺陷：子主题 CLI flush 只清
 * option 不清 Redis，会导致 web 上下文继续命中陈旧 Redis；插件版必须双路全清。
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_SmartAUI_Media_Mime_Cache
 */
class W2P_SmartAUI_Media_Mime_Cache {

	/** 对象缓存 group（与子主题一致）。 */
	const CACHE_GROUP = 'w2p';

	/** Redis 缓存 TTL（12h；与子主题一致）。 */
	const CACHE_TTL = 43200;

	/** Redis key：mime 类型数组（与子主题同名）。 */
	const REDIS_KEY = 'w2p_media_mime_types_v2';

	/** Redis key：时间戳（与子主题同名）。 */
	const REDIS_TS_KEY = 'w2p_media_mime_types_v2_ts';

	/** wp_options key：mime 类型数组（与子主题同名）。 */
	const OPT_KEY = 'w2p_media_mime_types';

	/** wp_options key：时间戳（与子主题同名）。 */
	const OPT_TS_KEY = 'w2p_media_mime_types_ts';

	/** 子主题函数名清单（并存检测 / deferral 用）。 */
	const CHILD_FUNC_SHORT_CIRCUIT = 'w2p_short_circuit_mime_types';
	const CHILD_FUNC_CLI_REGISTER  = 'w2p_register_cli_commands';
	const CHILD_FUNC_CLI_FLUSH     = 'w2p_cli_media_mime_flush';
	const CHILD_FUNC_CLI_TEST      = 'w2p_cli_media_mime_test';

	/**
	 * 构造器：挂载过滤器与 WP-CLI 注册钩子。
	 */
	public function __construct() {
		// 短路 get_available_post_mime_types 慢查询（仅对 attachment 介入）。
		add_filter( 'pre_get_available_post_mime_types', array( $this, 'short_circuit_mime_types' ), 10, 2 );

		// WP-CLI 命令注册（cli_init 在每次 wp 命令执行时触发）。
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'cli_init', array( $this, 'register_cli_commands' ) );
		}
	}

	/**
	 * 短路 get_available_post_mime_types —— 三级缓存 + 双写 + 命中日志。
	 *
	 * L1 Redis（wp_cache_get，group='w2p'，TTL 12h）—— web 主缓存。
	 * L2 wp_options（autoload=auto）—— CLI 兜底，也可 fallback Redis miss。
	 * L3 fallback SQL：双写回 Redis + wp_options。
	 *
	 * debug 日志通过 W2P_DEBUG_LOGGING 控制：默认开启，可在 wp-config.php 关闭。
	 * 命中 Redis 不打日志（量太大），只有 Redis miss / fallback / flush 才打。
	 *
	 * 并存期 deferral：子主题同名函数存在时直接透传 $null，让子主题接管
	 * （先注册者先返回缓存值，后注册者收到非 null 透传 → 结果一致）。
	 *
	 * @param array|null $null 过滤器初始值（null）。
	 * @param string     $type 文章类型，仅对 attachment 介入。
	 * @return array|null
	 */
	public function short_circuit_mime_types( $null, $type ) {
		// 仅对 attachment 介入；其它类型透传原生行为（AC-3.5）。
		if ( 'attachment' !== $type ) {
			return $null;
		}

		// 并存期 deferral：子主题同名函数存在 → 让子主题接管。
		if ( function_exists( self::CHILD_FUNC_SHORT_CIRCUIT ) ) {
			return $null;
		}

		$debug = $this->debug_enabled();

		// L1: Redis 对象缓存（web 上下文走 Redis，CLI 走内存/文件无伤大雅）。
		$cached = wp_cache_get( self::REDIS_KEY, self::CACHE_GROUP );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			// 命中 Redis 不写日志（每次浏览媒体库都打太吵）。
			return $cached;
		}

		// L2: wp_options 兜底。
		$opt = get_option( self::OPT_KEY, array() );
		$ts  = (int) get_option( self::OPT_TS_KEY, 0 );

		// 12h 内视为有效（旧 TTL 是 1h，提到 12h 减少 fail 概率）。
		if ( ! empty( $opt ) && ( time() - $ts ) < self::CACHE_TTL ) {
			// 把 option 的值回填到 Redis（web 上下文这一步把缓存带起来了）。
			wp_cache_set( self::REDIS_KEY, $opt, self::CACHE_GROUP, self::CACHE_TTL );
			wp_cache_set( self::REDIS_TS_KEY, $ts, self::CACHE_GROUP, self::CACHE_TTL );
			if ( $debug ) {
				error_log( '[w2p-mime] HIT wp_option (Redis miss), count=' . count( $opt ) . ' age=' . ( time() - $ts ) . 's' );
			}
			return $opt;
		}

		// L3: 都 miss——只能走一次 SQL。
		if ( $debug ) {
			error_log( '[w2p-mime] MISS - run DISTINCT SQL. opt_count=' . count( $opt ) . ' opt_age=' . ( time() - $ts ) . 's trace=' . wp_debug_backtrace_summary( 6 ) );
		}

		global $wpdb;
		$mime_types = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_mime_type FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type != ''",
				$type
			)
		);
		$mime_types = array_values( array_filter( $mime_types ) );

		// 双写：Redis（web 主）+ wp_options（CLI + 降级兜底）。
		wp_cache_set( self::REDIS_KEY, $mime_types, self::CACHE_GROUP, self::CACHE_TTL );
		wp_cache_set( self::REDIS_TS_KEY, time(), self::CACHE_GROUP, self::CACHE_TTL );
		update_option( self::OPT_KEY, $mime_types );
		update_option( self::OPT_TS_KEY, time() );

		return $mime_types;
	}

	/**
	 * 失效钩子：双路全清 Redis + wp_options 缓存（修正子主题缺陷）。
	 *
	 * 与子主题 w2p_flush_mime_cache() 行为一致；子主题的 CLI flush 只清 option
	 * 不清 Redis（web 继续命中陈旧 Redis），本方法 CLI 与手动调用统一双路全清。
	 *
	 * 默认不自动挂到 add_attachment / delete_attachment（C 方案），批量上传/扫描
	 * 期间不会被打掉。需要强制失效：wp media-mime-flush，或 admin 里手动调本方法。
	 *
	 * 注：若站点在 CLI 下显式禁用 Redis（wp-config.php 的 WP_REDIS_DISABLED），
	 * wp_cache_delete 只作用于该上下文的内存缓存；web 上下文（Redis 启用）执行
	 * 本方法即真正清空 Redis key。
	 *
	 * @param int    $post_id 附件 ID（手动调时传 0）。
	 * @param string $reason  触发原因，用于诊断日志。
	 * @return void
	 */
	public function flush_cache( $post_id = 0, $reason = 'manual' ) {
		$debug = $this->debug_enabled();
		if ( $debug ) {
			$trace = function_exists( 'wp_debug_backtrace_summary' ) ? wp_debug_backtrace_summary( 8 ) : 'n/a';
			error_log( '[w2p-mime-flush] reason=' . $reason . ' post_id=' . intval( $post_id ) . ' trace=' . $trace );
		}

		// 双路全清：wp_options 2 个 + Redis 2 个 key（group 'w2p'）。
		delete_option( self::OPT_KEY );
		delete_option( self::OPT_TS_KEY );
		wp_cache_delete( self::REDIS_KEY, self::CACHE_GROUP );
		wp_cache_delete( self::REDIS_TS_KEY, self::CACHE_GROUP );
	}

	/**
	 * 注册 wp-cli 子命令（media-mime-flush / media-mime-test / media-perf-verify）。
	 *
	 * 并存守卫（关键风险，见规格 3.6）：cli_init 每次 wp 命令执行都会触发；
	 * WP_CLI::add_command() 对已注册同名命令直接抛异常，会让所有 wp 命令不可用。
	 * 因此：function_exists('w2p_register_cli_commands') 为真 → 跳过自身注册并
	 * WP_CLI::warning 提示删除子主题代码。WP_CLI::has_command() 并非所有 WP-CLI
	 * 版本都有（本站版本即缺失，会 fatal），故以 method_exists 守卫做双保险。
	 * 删除子主题代码后插件恢复注册。
	 *
	 * @return void
	 */
	public function register_cli_commands() {
		if ( function_exists( self::CHILD_FUNC_CLI_REGISTER )
			|| function_exists( self::CHILD_FUNC_CLI_FLUSH )
			|| ( method_exists( 'WP_CLI', 'has_command' ) && ( WP_CLI::has_command( 'media-mime-flush' ) || WP_CLI::has_command( 'media-mime-test' ) || WP_CLI::has_command( 'media-perf-verify' ) ) )
		) {
			WP_CLI::warning( __( '检测到子主题同名 CLI 命令（media-mime-flush / media-mime-test / media-perf-verify），插件已跳过自身注册。请删除子主题 functions.php 的 [任务1] 段及对 inc/media-perf/cli-verify.php 的引用，随后插件将自动接管。', 'wp-genius' ) );
			return;
		}

		WP_CLI::add_command( 'media-mime-flush', array( $this, 'cli_media_mime_flush' ) );
		WP_CLI::add_command( 'media-mime-test', array( $this, 'cli_media_mime_test' ) );
		WP_CLI::add_command( 'media-perf-verify', array( $this, 'cli_media_perf_verify' ) );
	}

	/**
	 * wp media-mime-flush —— 双路全清缓存（option + Redis）。
	 *
	 * @param array $args       位置参数。
	 * @param array $assoc_args 关联参数。
	 * @return void
	 */
	public function cli_media_mime_flush( $args, $assoc_args ) {
		// 双路全清（含 Redis）——修正子主题 CLI flush 只清 option 的缺陷。
		$this->flush_cache( 0, 'cli:media-mime-flush' );
		WP_CLI::success( __( '媒体 mime 缓存已双路清空（wp_options + Redis）', 'wp-genius' ) );
	}

	/**
	 * wp media-mime-test [--flush] —— 对比「缓存路径」vs「真实 SQL」耗时(ms)。
	 *
	 * --flush 先清缓存再测（双路全清）。输出机器可解析的 {cached: x.xxx} /
	 * {uncached: x.xxx} 两行（毫秒，AC-3.3）。
	 *
	 * @param array $args       位置参数。
	 * @param array $assoc_args 关联参数（支持 --flush 先清缓存）。
	 * @return void
	 */
	public function cli_media_mime_test( $args, $assoc_args ) {
		if ( isset( $assoc_args['flush'] ) ) {
			$this->flush_cache( 0, 'cli:media-mime-test --flush' );
		}

		// 缓存路径计时（命中则极快；未命中则计算一次并写回 option）。
		$t0     = microtime( true );
		get_available_post_mime_types( 'attachment' );
		$cached = ( microtime( true ) - $t0 ) * 1000;

		// 真实 SQL 路径计时（与原查询完全一致）。
		global $wpdb;
		$t1       = microtime( true );
		$wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_mime_type FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type != ''",
				'attachment'
			)
		);
		$uncached = ( microtime( true ) - $t1 ) * 1000;

		WP_CLI::line( sprintf( '{cached: %.3f}', $cached ) );
		WP_CLI::line( sprintf( '{uncached: %.3f}', $uncached ) );
	}

	/**
	 * wp media-perf-verify —— 逐项自检媒体库性能优化（AC-3.4）。
	 *
	 * 输出：
	 *   ① pre_get_available_post_mime_types 是否挂载（has_filter）。
	 *   ② option / Redis 缓存存在性与 age（<12h）。
	 *   ③ 与子主题并存检测（function_exists 子主题函数名 → 提示并存与删除指引）。
	 *   ④ 缓存 vs SQL 耗时对比（约定 uncached >= cached*2 判 PASS）。
	 * 全部项目有明确 PASS/FAIL 输出，任一项 FAIL 最终整体 FAIL。
	 *
	 * @param array $args       位置参数。
	 * @param array $assoc_args 关联参数。
	 * @return void
	 */
	public function cli_media_perf_verify( $args, $assoc_args ) {
		WP_CLI::log( __( '===== 媒体库性能优化验证 =====', 'wp-genius' ) );
		$has_fail = false;

		// ① 过滤器挂载自检。
		if ( has_filter( 'pre_get_available_post_mime_types' ) ) {
			WP_CLI::success( __( 'PASS: pre_get_available_post_mime_types 过滤器已挂载', 'wp-genius' ) );
		} else {
			WP_CLI::error( __( 'FAIL: pre_get_available_post_mime_types 过滤器未挂载', 'wp-genius' ), false );
			$has_fail = true;
		}

		// ② option 缓存存在性与 age（<12h）。
		$opt = get_option( self::OPT_KEY, array() );
		$ts  = (int) get_option( self::OPT_TS_KEY, 0 );
		$age = ( $ts > 0 ) ? ( time() - $ts ) : -1;

		if ( ! empty( $opt ) && is_array( $opt ) && $age >= 0 && $age < self::CACHE_TTL ) {
			/* translators: 1: 缓存条目数 2: 缓存时长（秒）。 */
			WP_CLI::success( sprintf( __( 'PASS: option 缓存有效（count=%1$d, age=%2$ds < 12h）', 'wp-genius' ), count( $opt ), $age ) );
		} else {
			/* translators: %d: 缓存时长（秒）。 */
			WP_CLI::error( sprintf( __( 'FAIL: option 缓存缺失或过期（age=%ds；需先访问一次媒体库或执行 media-mime-test 重建）', 'wp-genius' ), $age ), false );
			$has_fail = true;
		}

		// ②b Redis 缓存存在性与 age（<12h）。CLI 下站点显式禁用 Redis 时该项仅提示。
		$redis  = wp_cache_get( self::REDIS_KEY, self::CACHE_GROUP );
		$rts    = (int) wp_cache_get( self::REDIS_TS_KEY, self::CACHE_GROUP );
		$rage   = ( $rts > 0 ) ? ( time() - $rts ) : -1;

		if ( defined( 'WP_REDIS_DISABLED' ) && WP_REDIS_DISABLED ) {
			WP_CLI::log( __( 'NOTE: 当前 wp 命令运行于 Redis 禁用上下文（WP_REDIS_DISABLED），Redis 项改为提示；web 前台 Redis 正常启用', 'wp-genius' ) );
			if ( ! empty( $redis ) && is_array( $redis ) && $rage >= 0 && $rage < self::CACHE_TTL ) {
				/* translators: 1: 缓存条目数 2: 缓存时长（秒）。 */
				WP_CLI::success( sprintf( __( 'PASS: Redis 缓存有效（count=%1$d, age=%2$ds < 12h）', 'wp-genius' ), count( $redis ), $rage ) );
			} else {
				/* translators: %d: 缓存时长（秒）。 */
				WP_CLI::log( sprintf( __( 'INFO: Redis 缓存未命中或过期（age=%ds），web 首次访问将重建双写', 'wp-genius' ), $rage ) );
			}
		} elseif ( ! empty( $redis ) && is_array( $redis ) && $rage >= 0 && $rage < self::CACHE_TTL ) {
			/* translators: 1: 缓存条目数 2: 缓存时长（秒）。 */
			WP_CLI::success( sprintf( __( 'PASS: Redis 缓存有效（count=%1$d, age=%2$ds < 12h）', 'wp-genius' ), count( $redis ), $rage ) );
		} else {
			/* translators: %d: 缓存时长（秒）。 */
			WP_CLI::error( sprintf( __( 'FAIL: Redis 缓存缺失或过期（age=%ds）', 'wp-genius' ), $rage ), false );
			$has_fail = true;
		}

		// ③ 与子主题并存检测。
		$child_funcs = array(
			self::CHILD_FUNC_SHORT_CIRCUIT,
			self::CHILD_FUNC_CLI_REGISTER,
			self::CHILD_FUNC_CLI_FLUSH,
			self::CHILD_FUNC_CLI_TEST,
		);
		$found       = array();
		foreach ( $child_funcs as $func ) {
			if ( function_exists( $func ) ) {
				$found[] = $func;
			}
		}
		if ( ! empty( $found ) ) {
			/* translators: %s: 检测到的子主题函数名（逗号分隔）。 */
			WP_CLI::warning( sprintf( __( 'WARN: 检测到子主题函数仍在（%s）——并存期以先加载者生效；请删除子主题 functions.php 的 [任务1] 段及对 inc/media-perf/cli-verify.php 的引用', 'wp-genius' ), implode( ', ', $found ) ) );
		} else {
			WP_CLI::success( __( 'PASS: 未检测到子主题同名函数（无并存）', 'wp-genius' ) );
		}

		// ④ 缓存 vs SQL 耗时对比（约定 uncached >= cached*2 判 PASS）。
		$t0         = microtime( true );
		get_available_post_mime_types( 'attachment' );
		$cached_ms  = ( microtime( true ) - $t0 ) * 1000;

		global $wpdb;
		$t1          = microtime( true );
		$wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_mime_type FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type != ''",
				'attachment'
			)
		);
		$uncached_ms = ( microtime( true ) - $t1 ) * 1000;

		/* translators: 1: 缓存路径耗时（毫秒） 2: 全表扫描耗时（毫秒）。 */
		WP_CLI::log( sprintf( __( '耗时对比：cached=%1$.3fms / uncached=%2$.3fms', 'wp-genius' ), $cached_ms, $uncached_ms ) );

		if ( $uncached_ms > 0 && $cached_ms > ( $uncached_ms / 2 ) ) {
			WP_CLI::error( __( 'FAIL: cache 路径未达预期加速（uncached < cached*2）', 'wp-genius' ), false );
			WP_CLI::log( __( '建议：检查 w2p_media_mime_types 缓存是否写入、过滤器是否短路返回。', 'wp-genius' ) );
			$has_fail = true;
		} else {
			WP_CLI::success( __( 'PASS: cache 路径显著快于全扫（uncached >= cached*2）', 'wp-genius' ) );
		}

		// 汇总。
		if ( $has_fail ) {
			WP_CLI::error( __( '媒体库性能优化验证存在 FAIL 项，请按上方提示处理', 'wp-genius' ) );
		} else {
			WP_CLI::success( __( '媒体库性能优化验证全部通过', 'wp-genius' ) );
		}
	}

	/**
	 * debug 日志开关：默认开；要彻底关在 wp-config.php 加
	 * define('W2P_DEBUG_LOGGING', false);
	 *
	 * @return bool
	 */
	private function debug_enabled() {
		return defined( 'W2P_DEBUG_LOGGING' ) ? (bool) W2P_DEBUG_LOGGING : true;
	}
}
