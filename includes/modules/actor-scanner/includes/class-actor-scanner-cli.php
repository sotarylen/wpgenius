<?php
/**
 * Actor Scanner & Deduplication WP-CLI Commands
 *
 * @package WP_Genius
 * @subpackage Modules/ActorScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class W2P_ActorScannerCLI {

	/**
	 * Scan posts from first line for actress mentions and assign Humans terms.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : Post type to scan. Repeatable. Default: post
	 *
	 * [--batch=<number>]
	 * : Posts processed per pass. Default: 50
	 *
	 * [--offset=<number>]
	 * : Skip this many posts. Default: 0
	 *
	 * ## EXAMPLES
	 *
	 *     wp w2p actor-scan --post-type=post --batch=50
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function scan( $args, $assoc_args ) {
		require_once __DIR__ . '/class-gfriends-client.php';
		require_once __DIR__ . '/class-actor-matcher.php';
		require_once __DIR__ . '/class-actor-sync.php';

		$post_types = ! empty( $assoc_args['post-type'] ) ? (array) $assoc_args['post-type'] : array( 'post' );
		$batch      = isset( $assoc_args['batch'] ) ? max( 1, (int) $assoc_args['batch'] ) : 50;
		$offset     = isset( $assoc_args['offset'] ) ? max( 0, (int) $assoc_args['offset'] ) : 0;

		$gf      = new W2P_Gfriends_Client();
		$matcher = new W2P_Actor_Matcher( $gf );
		$sync    = new W2P_Actor_Sync( $gf, $matcher );

		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder count always matches $post_types; WPCS cannot see a dynamically built placeholder list.
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$total        = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders})",
				$post_types
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		WP_CLI::log( sprintf( 'Starting actor identification on %d posts (batch %d, offset %d)...', $total, $batch, $offset ) );

		$scanned = 0;
		$matched = 0;

		while ( $offset < $total ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder count matches array_merge( $post_types, ... ); WPCS cannot see a dynamically built placeholder list.
			$post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_status = 'publish' AND post_type IN ({$placeholders})
					 ORDER BY ID ASC LIMIT %d OFFSET %d",
					array_merge( $post_types, array( $batch, $offset ) )
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

			if ( empty( $post_ids ) ) {
				break;
			}

			foreach ( $post_ids as $pid ) {
				$res = $sync->detect_and_assign_post( (int) $pid );
				++$scanned;

				if ( ! empty( $res['success'] ) && ! empty( $res['terms'] ) ) {
					++$matched;
					$names = wp_list_pluck( $res['terms'], 'name' );
					WP_CLI::log( sprintf( '  [%d] Matched: %s', $pid, implode( ', ', $names ) ) );
				}
			}

			$offset += $batch;
		}

		WP_CLI::success( sprintf( 'Finished: %d posts scanned, %d posts matched.', $scanned, $matched ) );
	}

	/**
	 * Refresh Gfriends official actor index.
	 *
	 * ## EXAMPLES
	 *
	 *     wp w2p actor-refresh-index
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function refresh_index( $args, $assoc_args ) {
		require_once __DIR__ . '/class-gfriends-client.php';

		$gf   = new W2P_Gfriends_Client();
		$data = $gf->get_actor_index( true );

		WP_CLI::success( sprintf( 'Gfriends index refreshed: %d actors cached.', count( $data ) ) );
	}
}

WP_CLI::add_command( 'w2p actor-scan', array( 'W2P_ActorScannerCLI', 'scan' ) );
WP_CLI::add_command( 'w2p actor-refresh-index', array( 'W2P_ActorScannerCLI', 'refresh_index' ) );
