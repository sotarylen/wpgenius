<?php
/**
 * Smart AUI — Media Library Performance Optimization (get_available_post_mime_types 3-Tier Cache + CLI)
 *
 * Implements performance optimization for media library MIME type lookup:
 *  - Short-circuits get_available_post_mime_types('attachment') using the pre_get_available_post_mime_types filter
 *    to avoid expensive table scans (`SELECT DISTINCT post_mime_type ...`) across large attachment tables.
 *  - Uses a 3-tier caching structure:
 *      L1: Redis object cache (wp_cache_get, group='w2p', TTL 12h) — Primary web cache.
 *      L2: wp_options (autoload=auto) — CLI fallback and cold-start source.
 *      L3: Fallback SQL query: dual-writes back to Redis + wp_options.
 *  - Cache invalidation: CLI command `wp media-mime-flush` (clears both wp_options and Redis) or manual flush_cache().
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

	/** Object cache group name. */
	const CACHE_GROUP = 'w2p';

	/** Redis cache TTL (12h). */
	const CACHE_TTL = 43200;

	/** Redis key for MIME types array. */
	const REDIS_KEY = 'w2p_media_mime_types_v2';

	/** Redis key for timestamp. */
	const REDIS_TS_KEY = 'w2p_media_mime_types_v2_ts';

	/** wp_options key for MIME types array. */
	const OPT_KEY = 'w2p_media_mime_types';

	/** wp_options key for timestamp. */
	const OPT_TS_KEY = 'w2p_media_mime_types_ts';

	/** Child theme function names (for coexistence check / deferral). */
	const CHILD_FUNC_SHORT_CIRCUIT = 'w2p_short_circuit_mime_types';
	const CHILD_FUNC_CLI_REGISTER  = 'w2p_register_cli_commands';
	const CHILD_FUNC_CLI_FLUSH     = 'w2p_cli_media_mime_flush';
	const CHILD_FUNC_CLI_TEST      = 'w2p_cli_media_mime_test';

	/**
	 * Constructor: Mount filters and CLI registration hooks.
	 */
	public function __construct() {
		// Short-circuit get_available_post_mime_types query (attachment post type only).
		add_filter( 'pre_get_available_post_mime_types', array( $this, 'short_circuit_mime_types' ), 10, 2 );

		// Register WP-CLI commands.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'cli_init', array( $this, 'register_cli_commands' ) );
		}
	}

	/**
	 * Short-circuit get_available_post_mime_types — 3-tier cache + dual-write.
	 *
	 * L1: Redis (wp_cache_get, group='w2p', TTL 12h) — Primary web cache.
	 * L2: wp_options (autoload=auto) — CLI fallback and Redis miss cache source.
	 * L3: Fallback SQL query — dual-writes to Redis + wp_options.
	 *
	 * @param array|null $null Filter initial value (null).
	 * @param string     $type Post type; only intercepts 'attachment'.
	 * @return array|null
	 */
	public function short_circuit_mime_types( $null, $type ) {
		// Only intercept attachment post type; let others pass through natively.
		if ( 'attachment' !== $type ) {
			return $null;
		}

		// Coexistence deferral: Yield if child theme function exists.
		if ( function_exists( self::CHILD_FUNC_SHORT_CIRCUIT ) ) {
			return $null;
		}

		$debug = $this->debug_enabled();

		// L1: Redis object cache.
		$cached = wp_cache_get( self::REDIS_KEY, self::CACHE_GROUP );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return $cached;
		}

		// L2: wp_options fallback.
		$opt = get_option( self::OPT_KEY, array() );
		$ts  = (int) get_option( self::OPT_TS_KEY, 0 );

		if ( ! empty( $opt ) && ( time() - $ts ) < self::CACHE_TTL ) {
			// Populate Redis cache from option.
			wp_cache_set( self::REDIS_KEY, $opt, self::CACHE_GROUP, self::CACHE_TTL );
			wp_cache_set( self::REDIS_TS_KEY, $ts, self::CACHE_GROUP, self::CACHE_TTL );
			if ( $debug ) {
				error_log( '[w2p-mime] HIT wp_option (Redis miss), count=' . count( $opt ) . ' age=' . ( time() - $ts ) . 's' );
			}
			return $opt;
		}

		// L3: Cache miss on all tiers — perform single SQL query.
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

		// Dual-write: Redis + wp_options.
		wp_cache_set( self::REDIS_KEY, $mime_types, self::CACHE_GROUP, self::CACHE_TTL );
		wp_cache_set( self::REDIS_TS_KEY, time(), self::CACHE_GROUP, self::CACHE_TTL );
		update_option( self::OPT_KEY, $mime_types );
		update_option( self::OPT_TS_KEY, time() );

		return $mime_types;
	}

	/**
	 * Flush cache: Clears both Redis and wp_options cache keys.
	 *
	 * @param int    $post_id Attachment ID (0 for manual invocation).
	 * @param string $reason  Trigger reason for logging.
	 * @return void
	 */
	public function flush_cache( $post_id = 0, $reason = 'manual' ) {
		$debug = $this->debug_enabled();
		if ( $debug ) {
			$trace = function_exists( 'wp_debug_backtrace_summary' ) ? wp_debug_backtrace_summary( 8 ) : 'n/a';
			error_log( '[w2p-mime-flush] reason=' . $reason . ' post_id=' . intval( $post_id ) . ' trace=' . $trace );
		}

		// Dual flush: wp_options + Redis (group 'w2p').
		delete_option( self::OPT_KEY );
		delete_option( self::OPT_TS_KEY );
		wp_cache_delete( self::REDIS_KEY, self::CACHE_GROUP );
		wp_cache_delete( self::REDIS_TS_KEY, self::CACHE_GROUP );
	}

	/**
	 * Register WP-CLI commands (media-mime-flush / media-mime-test / media-perf-verify).
	 *
	 * @return void
	 */
	public function register_cli_commands() {
		if ( function_exists( self::CHILD_FUNC_CLI_REGISTER )
			|| function_exists( self::CHILD_FUNC_CLI_FLUSH )
			|| ( method_exists( 'WP_CLI', 'has_command' ) && ( WP_CLI::has_command( 'media-mime-flush' ) || WP_CLI::has_command( 'media-mime-test' ) || WP_CLI::has_command( 'media-perf-verify' ) ) )
		) {
			WP_CLI::warning( __( 'Detected child theme CLI commands (media-mime-flush / media-mime-test / media-perf-verify). Plugin CLI registration skipped to prevent conflict.', 'wp-genius' ) );
			return;
		}

		WP_CLI::add_command( 'media-mime-flush', array( $this, 'cli_media_mime_flush' ) );
		WP_CLI::add_command( 'media-mime-test', array( $this, 'cli_media_mime_test' ) );
		WP_CLI::add_command( 'media-perf-verify', array( $this, 'cli_media_perf_verify' ) );
	}

	/**
	 * wp media-mime-flush — Dual-flush cache (wp_options + Redis).
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function cli_media_mime_flush( $args, $assoc_args ) {
		$this->flush_cache( 0, 'cli:media-mime-flush' );
		WP_CLI::success( __( 'Media MIME cache flushed from both wp_options and Redis.', 'wp-genius' ) );
	}

	/**
	 * wp media-mime-test [--flush] — Compare execution time between cached path vs raw SQL query (ms).
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments (supports --flush).
	 * @return void
	 */
	public function cli_media_mime_test( $args, $assoc_args ) {
		if ( isset( $assoc_args['flush'] ) ) {
			$this->flush_cache( 0, 'cli:media-mime-test --flush' );
		}

		// Cached path timing.
		$t0     = microtime( true );
		get_available_post_mime_types( 'attachment' );
		$cached = ( microtime( true ) - $t0 ) * 1000;

		// Raw SQL query timing.
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
	 * wp media-perf-verify — Run health checks on media library performance optimization.
	 *
	 * Checks:
	 *   1. Filter registration on pre_get_available_post_mime_types.
	 *   2. wp_options and Redis cache existence and freshness (<12h).
	 *   3. Child theme conflict detection.
	 *   4. Cached vs raw SQL performance speedup comparison.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function cli_media_perf_verify( $args, $assoc_args ) {
		WP_CLI::log( __( '===== Media Library Performance Verification =====', 'wp-genius' ) );
		$has_fail = false;

		// 1. Filter registration check.
		if ( has_filter( 'pre_get_available_post_mime_types' ) ) {
			WP_CLI::success( __( 'PASS: pre_get_available_post_mime_types filter is active', 'wp-genius' ) );
		} else {
			WP_CLI::error( __( 'FAIL: pre_get_available_post_mime_types filter is not active', 'wp-genius' ), false );
			$has_fail = true;
		}

		// 2. Option cache check (<12h).
		$opt = get_option( self::OPT_KEY, array() );
		$ts  = (int) get_option( self::OPT_TS_KEY, 0 );
		$age = ( $ts > 0 ) ? ( time() - $ts ) : -1;

		if ( ! empty( $opt ) && is_array( $opt ) && $age >= 0 && $age < self::CACHE_TTL ) {
			/* translators: 1: Cache items count, 2: Cache age in seconds. */
			WP_CLI::success( sprintf( __( 'PASS: option cache is valid (count=%1$d, age=%2$ds < 12h)', 'wp-genius' ), count( $opt ), $age ) );
		} else {
			/* translators: %d: Cache age in seconds. */
			WP_CLI::error( sprintf( __( 'FAIL: option cache missing or expired (age=%ds; visit media library or run media-mime-test to rebuild)', 'wp-genius' ), $age ), false );
			$has_fail = true;
		}

		// 2b. Redis cache check (<12h).
		$redis  = wp_cache_get( self::REDIS_KEY, self::CACHE_GROUP );
		$rts    = (int) wp_cache_get( self::REDIS_TS_KEY, self::CACHE_GROUP );
		$rage   = ( $rts > 0 ) ? ( time() - $rts ) : -1;

		if ( defined( 'WP_REDIS_DISABLED' ) && WP_REDIS_DISABLED ) {
			WP_CLI::log( __( 'NOTE: Current CLI running with Redis disabled (WP_REDIS_DISABLED); web requests still utilize Redis cache', 'wp-genius' ) );
			if ( ! empty( $redis ) && is_array( $redis ) && $rage >= 0 && $rage < self::CACHE_TTL ) {
				/* translators: 1: Cache items count, 2: Cache age in seconds. */
				WP_CLI::success( sprintf( __( 'PASS: Redis cache is valid (count=%1$d, age=%2$ds < 12h)', 'wp-genius' ), count( $redis ), $rage ) );
			} else {
				/* translators: %d: Cache age in seconds. */
				WP_CLI::log( sprintf( __( 'INFO: Redis cache miss or expired (age=%ds); web request will rebuild dual cache', 'wp-genius' ), $rage ) );
			}
		} elseif ( ! empty( $redis ) && is_array( $redis ) && $rage >= 0 && $rage < self::CACHE_TTL ) {
			/* translators: 1: Cache items count, 2: Cache age in seconds. */
			WP_CLI::success( sprintf( __( 'PASS: Redis cache is valid (count=%1$d, age=%2$ds < 12h)', 'wp-genius' ), count( $redis ), $rage ) );
		} else {
			/* translators: %d: Cache age in seconds. */
			WP_CLI::error( sprintf( __( 'FAIL: Redis cache missing or expired (age=%ds)', 'wp-genius' ), $rage ), false );
			$has_fail = true;
		}

		// 3. Child theme conflict detection.
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
			/* translators: %s: Comma-separated child theme function names. */
			WP_CLI::warning( sprintf( __( 'WARN: Child theme functions still detected (%s); please remove them from child theme functions.php', 'wp-genius' ), implode( ', ', $found ) ) );
		} else {
			WP_CLI::success( __( 'PASS: No conflicting child theme functions detected', 'wp-genius' ) );
		}

		// 4. Cached vs raw SQL performance comparison.
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

		/* translators: 1: Cached duration in ms, 2: Uncached duration in ms. */
		WP_CLI::log( sprintf( __( 'Timing comparison: cached=%1$.3fms / uncached=%2$.3fms', 'wp-genius' ), $cached_ms, $uncached_ms ) );

		if ( $uncached_ms > 0 && $cached_ms > ( $uncached_ms / 2 ) ) {
			WP_CLI::error( __( 'FAIL: Cached path did not achieve expected speedup (uncached < cached * 2)', 'wp-genius' ), false );
			WP_CLI::log( __( 'Recommendation: Check if w2p_media_mime_types cache is written and filter returns early.', 'wp-genius' ) );
			$has_fail = true;
		} else {
			WP_CLI::success( __( 'PASS: Cached path is significantly faster than table scan (uncached >= cached * 2)', 'wp-genius' ) );
		}

		// Summary.
		if ( $has_fail ) {
			WP_CLI::error( __( 'Media library performance verification failed on one or more checks. See details above.', 'wp-genius' ) );
		} else {
			WP_CLI::success( __( 'Media library performance verification passed all checks.', 'wp-genius' ) );
		}
	}

	/**
	 * Debug logging helper: Controlled by W2P_DEBUG_LOGGING constant.
	 *
	 * @return bool
	 */
	private function debug_enabled() {
		return defined( 'W2P_DEBUG_LOGGING' ) ? (bool) W2P_DEBUG_LOGGING : false;
	}
}

