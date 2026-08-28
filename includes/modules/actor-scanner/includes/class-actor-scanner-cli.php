<?php
/**
 * Actor Scanner WP-CLI Commands
 *
 * Scan posts/albums for actress mentions, create/enrich Humans terms and
 * download Gfriends avatars from the command line (recommended for the full
 * 39k+ post sweep; the admin tool is fine for incremental batches).
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
	 * Scan posts for actress mentions and assign Humans terms.
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
	 * [--dry-run]
	 * : Preview matches without writing anything.
	 *
	 * [--no-create]
	 * : Do not auto-create new Humans terms for unmatched Japanese names.
	 *
	 * [--no-avatar]
	 * : Do not download avatars.
	 *
	 * ## EXAMPLES
	 *
	 *     wp w2p actor-scan --post-type=post --batch=100 --dry-run
	 *     wp w2p actor-scan --post-type=post --post-type=albums
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function scan( $args, $assoc_args ) {
		require_once __DIR__ . '/class-gfriends-client.php';
		require_once __DIR__ . '/class-actor-matcher.php';
		require_once __DIR__ . '/class-actor-sync.php';

		$post_types  = ! empty( $assoc_args['post-type'] ) ? (array) $assoc_args['post-type'] : array( 'post' );
		$batch       = isset( $assoc_args['batch'] ) ? max( 1, (int) $assoc_args['batch'] ) : 50;
		$offset      = isset( $assoc_args['offset'] ) ? max( 0, (int) $assoc_args['offset'] ) : 0;
		$dry_run     = isset( $assoc_args['dry-run'] );
		$create_new  = ! isset( $assoc_args['no-create'] );
		$with_avatar = ! isset( $assoc_args['no-avatar'] );

		$gf      = new W2P_Gfriends_Client();
		$matcher = new W2P_Actor_Matcher( $gf );
		$sync    = new W2P_Actor_Sync( $gf, $matcher );

		WP_CLI::log( 'Preparing Gfriends index…' );
		$actors = count( $gf->get_actor_index() );
		WP_CLI::success( sprintf( 'Gfriends index ready (%d actors).', $actors ) );

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$total        = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders})",
				$post_types
			)
		);

		WP_CLI::log( sprintf( 'Scanning %d %s (batch %d, offset %d, dry-run: %s)…', $total, implode( ',', $post_types ), $batch, $offset, $dry_run ? 'yes' : 'no' ) );

		$stats = array(
			'posts_scanned'  => 0,
			'posts_matched'  => 0,
			'actors_found'   => 0,
			'terms_created'  => 0,
			'terms_enriched' => 0,
			'avatars_added'  => 0,
		);

		$next_log = 0;

		while ( $offset < $total ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title, post_content FROM {$wpdb->posts}
					 WHERE post_status = 'publish' AND post_type IN ({$placeholders})
					 ORDER BY ID ASC LIMIT %d OFFSET %d",
					array_merge( $post_types, array( $batch, $offset ) )
				)
			);

			foreach ( $rows as $post ) {
				$post_id = (int) $post->ID;
				$text    = $post->post_title . "\n" . $post->post_content;

				$candidates = $matcher->extract_candidates( $text );
				if ( empty( $candidates ) ) {
					++$stats['posts_scanned'];
					continue;
				}

				$resolved = $matcher->resolve_all( $candidates );
				if ( empty( $resolved ) ) {
					++$stats['posts_scanned'];
					continue;
				}

				$term_ids = array();
				foreach ( $resolved as $actor ) {
					$term_id = 0;
					if ( ! empty( $actor['term_id'] ) ) {
						$term_id = $sync->enrich_existing( $actor['term_id'], $actor );
						if ( $term_id ) {
							++$stats['terms_enriched'];
						}
					} elseif ( $create_new ) {
						$term_id = $sync->ensure_term( $actor );
						if ( $term_id ) {
							++$stats['terms_created'];
						}
					}

					if ( $term_id > 0 ) {
						$term_ids[] = $term_id;
						++$stats['actors_found'];
						WP_CLI::log( sprintf( '  [%d] %s → term %d', $post_id, $actor['name'], $term_id ) );
					}
				}

				$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );
				if ( ! empty( $term_ids ) ) {
					++$stats['posts_matched'];
					if ( ! $dry_run ) {
						$sync->assign_to_post( $post_id, $term_ids );
					}
				}

				++$stats['posts_scanned'];
			}

			$offset += $batch;
		}

		WP_CLI::success( 'Scan complete.' );
		WP_CLI::log( '--- Stats ---' );
		foreach ( $stats as $k => $v ) {
			WP_CLI::log( sprintf( '  %s: %d', $k, $v ) );
		}
	}

	/**
	 * Refresh the Gfriends actor index from the remote repository.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function refresh( $args, $assoc_args ) {
		require_once __DIR__ . '/class-gfriends-client.php';

		$gf   = new W2P_Gfriends_Client();
		$data = $gf->get_actor_index( true );

		WP_CLI::success( sprintf( 'Index refreshed: %d actors cached.', count( $data ) ) );
	}

	/**
	 * Show current Gfriends index stats.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		require_once __DIR__ . '/class-gfriends-client.php';

		$gf     = new W2P_Gfriends_Client();
		$actors = $gf->count_actors();

		$file = wp_upload_dir();
		$file = trailingslashit( $file['basedir'] ) . 'w2p-actor-scanner/gfriends-index.json';
		$age  = file_exists( $file ) ? human_time_diff( filemtime( $file ) ) : 'n/a';

		WP_CLI::success( sprintf( 'Actors in index: %d (cached %s ago)', $actors, $age ) );
	}
}

WP_CLI::add_command( 'w2p actor-scan', 'W2P_ActorScannerCLI' );
