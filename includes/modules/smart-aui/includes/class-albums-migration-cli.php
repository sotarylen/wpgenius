<?php
/**
 * Smart AUI — Albums Migration WP-CLI Command
 *
 * Provides CLI commands to migrate unmanaged images under /wp-content/uploads/albums/ into WordPress Media Library.
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Class W2P_SmartAUI_Albums_Migration_CLI
 */
class W2P_SmartAUI_Albums_Migration_CLI {

	/**
	 * Migrate albums images to WordPress media library.
	 *
	 * ## OPTIONS
	 *
	 * [--post_id=<id>]
	 * : Specify a single post ID to process.
	 *
	 * [--post_type=<type>]
	 * : Specify post type to scan. Default 'albums'. Use 'all' for all post types.
	 *
	 * [--limit=<number>]
	 * : Maximum number of posts to process. Default 50. Use -1 for unlimited.
	 *
	 * [--batch-size=<number>]
	 * : Periodic batch size for object cache flushing and memory cleanup. Default 20.
	 *
	 * [--dry-run]
	 * : Preview affected posts and image counts without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview single post
	 *     wp smart-aui migrate-albums --post_id=1606828 --dry-run
	 *
	 *     # Migrate single post
	 *     wp smart-aui migrate-albums --post_id=1606828
	 *
	 *     # Migrate up to 100 posts (albums)
	 *     wp smart-aui migrate-albums --limit=100
	 *
	 *     # Full migration across all album posts
	 *     wp smart-aui migrate-albums --limit=-1
	 *
	 * @when after_wp_load
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative flags.
	 * @return void
	 */
	public function migrate_albums( $args, $assoc_args ) {
		global $wpdb;

		$post_id    = isset( $assoc_args['post_id'] ) ? absint( $assoc_args['post_id'] ) : 0;
		$post_type  = isset( $assoc_args['post_type'] ) ? sanitize_key( $assoc_args['post_type'] ) : 'albums';
		$limit      = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50;
		$batch_size = isset( $assoc_args['batch-size'] ) ? max( 1, (int) $assoc_args['batch-size'] ) : 20;
		$dry_run    = isset( $assoc_args['dry-run'] );

		// Temporarily ensure migrate_albums setting is enabled in memory for this CLI execution
		$filter_callback = function ( $value, $key ) {
			if ( 'migrate_albums' === $key ) {
				return true;
			}
			return $value;
		};
		add_filter( 'smart_aui_get_setting', $filter_callback, 999, 2 );

		$container = \SmartAutoUploadImages\get_container();
		/** @var \SmartAutoUploadImages\Services\ImageProcessorExtended $processor */
		$processor = $container->get( 'image_processor' );

		WP_CLI::line( '==================================================' );
		WP_CLI::line( esc_html__( 'Smart AUI: Albums Directory Image Migration', 'wp-genius' ) );
		WP_CLI::line( '==================================================' );

		if ( $dry_run ) {
			WP_CLI::warning( esc_html__( 'Running in DRY-RUN mode. No files will be modified or deleted.', 'wp-genius' ) );
		}

		// 1. Gather target post IDs
		$post_ids = array();
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				remove_filter( 'smart_aui_get_setting', $filter_callback, 999 );
				WP_CLI::error( sprintf( esc_html__( 'Post ID %d not found.', 'wp-genius' ), $post_id ) );
				return;
			}
			$post_ids[] = $post_id;
		} else {
			$type_label = ( 'all' === $post_type ) ? 'all post types' : "post_type '{$post_type}'";
			WP_CLI::line( sprintf( esc_html__( 'Scanning database (%s) for /wp-content/uploads/albums/...', 'wp-genius' ), $type_label ) );

			$type_clause  = ( 'all' !== $post_type ) ? $wpdb->prepare( 'AND post_type = %s', $post_type ) : '';
			$limit_clause = ( $limit > 0 ) ? $wpdb->prepare( 'LIMIT %d', $limit ) : '';
			$query        = "SELECT ID FROM {$wpdb->posts} 
				WHERE post_status NOT IN ('trash', 'auto-draft') 
				{$type_clause}
				AND post_content LIKE %s 
				ORDER BY ID ASC {$limit_clause}";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- clauses prepared above
			$post_ids = $wpdb->get_col( $wpdb->prepare( $query, '%/wp-content/uploads/albums/%' ) );
		}

		$total_posts = count( $post_ids );
		if ( 0 === $total_posts ) {
			remove_filter( 'smart_aui_get_setting', $filter_callback, 999 );
			WP_CLI::success( esc_html__( 'No posts containing albums directory images found.', 'wp-genius' ) );
			return;
		}

		WP_CLI::line( sprintf( esc_html__( 'Found %d post(s) to inspect.', 'wp-genius' ), $total_posts ) );

		$total_images_found     = 0;
		$total_images_processed = 0;
		$total_posts_updated    = 0;
		$start_time             = microtime( true );

		$progress = \WP_CLI\Utils\make_progress_bar( esc_html__( 'Processing posts', 'wp-genius' ), $total_posts );

		foreach ( $post_ids as $index => $p_id ) {
			$post = get_post( $p_id );
			if ( ! $post ) {
				$progress->tick();
				continue;
			}

			// Periodic memory management guard (flush cache, run GC, reset query log)
			if ( 0 === ( ( $index + 1 ) % $batch_size ) ) {
				if ( function_exists( 'wp_cache_flush' ) ) {
					wp_cache_flush();
				}
				if ( function_exists( 'gc_collect_cycles' ) ) {
					gc_collect_cycles();
				}
				if ( is_array( $wpdb->queries ) ) {
					$wpdb->queries = array();
				}
			}

			// Scan for albums images in content
			$album_images = array();
			if ( preg_match_all( '/<img[^>]+src=["\']([^"\']*\/wp-content\/uploads\/albums\/[^"\']+)["\'][^>]*>/i', $post->post_content, $matches ) ) {
				$album_images = array_unique( $matches[1] );
			}

			$img_count = count( $album_images );
			if ( $img_count > 0 ) {
				$total_images_found += $img_count;

				if ( $dry_run ) {
					WP_CLI::log( sprintf( '[DRY-RUN] Post #%d ("%s") contains %d album image(s).', $p_id, $post->post_title, $img_count ) );
				} else {
					$post_data = array(
						'ID'           => $post->ID,
						'post_title'   => $post->post_title,
						'post_content' => $post->post_content,
						'post_status'  => $post->post_status,
						'post_type'    => $post->post_type,
					);

					$processed_content = $processor->process_post_content( $post->post_content, $post_data );

					if ( false !== $processed_content && $processed_content !== $post->post_content ) {
						// Update post content
						$wpdb->update(
							$wpdb->posts,
							array( 'post_content' => $processed_content ),
							array( 'ID' => $p_id ),
							array( '%s' ),
							array( '%d' )
						);
						clean_post_cache( $p_id );

						$total_posts_updated++;
						$total_images_processed += $img_count;
						WP_CLI::log( sprintf( 'Post #%d updated successfully (%d image(s) migrated).', $p_id, $img_count ) );
					}
				}
			}

			$progress->tick();
		}

		$progress->finish();

		// Restore original setting filter
		remove_filter( 'smart_aui_get_setting', $filter_callback, 999 );

		$duration = round( microtime( true ) - $start_time, 2 );

		WP_CLI::line( '==================================================' );
		WP_CLI::line(
			sprintf(
				esc_html__( 'Summary: %1$d post(s) inspected | %2$d album image(s) detected | %3$d post(s) updated | Duration: %4$ss', 'wp-genius' ),
				$total_posts,
				$total_images_found,
				$total_posts_updated,
				$duration
			)
		);

		if ( $dry_run ) {
			WP_CLI::success( esc_html__( 'Dry-run inspection completed.', 'wp-genius' ) );
		} else {
			WP_CLI::success( sprintf( esc_html__( 'Migration completed. %d image(s) successfully migrated.', 'wp-genius' ), $total_images_processed ) );
		}
	}
}
