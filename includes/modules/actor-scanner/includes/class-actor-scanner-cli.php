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
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$total        = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders})",
				$post_types
			)
		);

		WP_CLI::log( sprintf( 'Starting actor identification on %d posts (batch %d, offset %d)...', $total, $batch, $offset ) );

		$scanned = 0;
		$matched = 0;

		while ( $offset < $total ) {
			$post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_status = 'publish' AND post_type IN ({$placeholders})
					 ORDER BY ID ASC LIMIT %d OFFSET %d",
					array_merge( $post_types, array( $batch, $offset ) )
				)
			);

			if ( empty( $post_ids ) ) {
				break;
			}

			foreach ( $post_ids as $pid ) {
				$res = $sync->detect_and_assign_post( (int) $pid );
				$scanned++;

				if ( ! empty( $res['success'] ) && ! empty( $res['terms'] ) ) {
					$matched++;
					$names = wp_list_pluck( $res['terms'], 'name' );
					WP_CLI::log( sprintf( '  [%d] Matched: %s', $pid, implode( ', ', $names ) ) );
				}
			}

			$offset += $batch;
		}

		WP_CLI::success( sprintf( 'Finished: %d posts scanned, %d posts matched.', $scanned, $matched ) );
	}

	/**
	 * Deduplicate repeating Humans terms and migrate post relationships.
	 *
	 * ## EXAMPLES
	 *
	 *     wp w2p actor-dedupe
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function dedupe( $args, $assoc_args ) {
		require_once __DIR__ . '/class-gfriends-client.php';
		require_once __DIR__ . '/class-actor-matcher.php';
		require_once __DIR__ . '/class-actor-deduplicator.php';

		$gf           = new W2P_Gfriends_Client();
		$matcher      = new W2P_Actor_Matcher( $gf );
		$deduplicator = new W2P_Actor_Deduplicator( $gf, $matcher );

		WP_CLI::log( 'Scanning Humans taxonomy for duplicate clusters...' );
		$clusters = $deduplicator->scan_duplicates();

		if ( empty( $clusters ) ) {
			WP_CLI::success( 'No duplicate actors found in database.' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d duplicate clusters. Starting merge...', count( $clusters ) ) );

		$stats = $deduplicator->merge_all();

		WP_CLI::success(
			sprintf(
				'Deduplication complete: %d clusters merged, %d posts migrated, %d duplicate terms deleted.',
				$stats['clusters_merged'],
				$stats['posts_migrated'],
				$stats['deleted_terms']
			)
		);
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
WP_CLI::add_command( 'w2p actor-dedupe', array( 'W2P_ActorScannerCLI', 'dedupe' ) );
WP_CLI::add_command( 'w2p actor-refresh-index', array( 'W2P_ActorScannerCLI', 'refresh_index' ) );
