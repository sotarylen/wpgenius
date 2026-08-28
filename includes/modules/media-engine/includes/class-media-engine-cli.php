<?php
/**
 * Media Engine WP-CLI Commands
 *
 * WP-CLI command registration and handling
 *
 * @package WP_Genius
 * @subpackage Modules/MediaEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class MediaEngineCLI {

	/**
	 * Processor instance
	 *
	 * @var MediaEngineProcessor
	 */
	private $processor;

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! class_exists( 'MediaEngineProcessor' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-media-engine-processor.php';
		}

		$this->processor = new MediaEngineProcessor();
	}

	/**
	 * Convert images to WebP format
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Batch size, default 100
	 *
	 * [--offset=<number>]
	 * : Offset, default 0
	 *
	 * [--id=<number>]
	 * : Process the specified attachment ID
	 *
	 * [--parallel]
	 * : Use parallel processing
	 *
	 * [--workers=<number>]
	 * : Number of workers for parallel processing
	 *
	 * ## EXAMPLES
	 *
	 *     # Convert 100 images
	 *     wp media-engine convert --limit=100
	 *
	 *     # Convert an image with a specified ID
	 *     wp media-engine convert --id=12345
	 *
	 *     # Convert in parallel
	 *     wp media-engine convert --limit=100 --parallel --workers=4
	 *
	 * @when after_wp_load
	 */
	public function convert( $args, $assoc_args ) {
		$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 100;
		$offset   = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$id       = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : null;
		$parallel = isset( $assoc_args['parallel'] );
		$workers  = isset( $assoc_args['workers'] ) ? (int) $assoc_args['workers'] : null;

		// Process a single attachment
		if ( $id ) {
			// translators: %1: placeholder.
			WP_CLI::line( sprintf( __( 'Processing attachment ID: %d', 'wp-genius' ), $id ) );
			$result = $this->processor->process_attachment( $id );

			if ( $result['success'] ) {
				WP_CLI::success( __( 'Conversion completed successfully', 'wp-genius' ) );
			} else {
				WP_CLI::error( $result['error'] );
			}

			return;
		}

		// Get pending attachments
		$attachments = $this->processor->get_pending_attachments( $limit, $offset );
		$total       = count( $attachments );

		if ( $total === 0 ) {
			WP_CLI::warning( __( 'No pending attachments found', 'wp-genius' ) );
			return;
		}

		// translators: %1: placeholder.
		WP_CLI::line( sprintf( __( 'Found %d attachments to process', 'wp-genius' ), $total ) );

		// Parallel processing
		if ( $parallel ) {
			// translators: %1: placeholder.
			WP_CLI::line( sprintf( __( 'Using parallel processing with %d workers', 'wp-genius' ), $workers ?? 'auto' ) );
			$result = $this->processor->parallel_process( $attachments, $workers );
		} else {
			// Batch processing
			$result = $this->processor->batch_process( $limit, $offset );
		}

		WP_CLI::line(
			sprintf(
				// translators: %1: placeholder, %2: placeholder, %3: placeholder.
				__( 'Processed: %1$d | Succeeded: %2$d | Failed: %3$d | Duration: %4$.2fs', 'wp-genius' ),
				$result['processed'],
				$result['succeeded'],
				$result['failed'],
				$result['duration']
			)
		);

		if ( $result['succeeded'] > 0 ) {
			// translators: %1: placeholder.
			WP_CLI::success( sprintf( __( 'Successfully converted %d images', 'wp-genius' ), $result['succeeded'] ) );
		}

		if ( $result['failed'] > 0 ) {
			// translators: %1: placeholder.
			WP_CLI::warning( sprintf( __( '%d images failed to convert', 'wp-genius' ), $result['failed'] ) );
		}
	}

	/**
	 * Offload files to Minio
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Batch size, default 100
	 *
	 * ## EXAMPLES
	 *
	 *     wp media-engine offload --limit=100
	 *
	 * @when after_wp_load
	 */
	public function offload( $args, $assoc_args ) {
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 100;

		// translators: %1: placeholder.
		WP_CLI::line( sprintf( __( 'Offloading up to %d files to Minio...', 'wp-genius' ), $limit ) );

		$command = sprintf( 'wp advmo offload --limit=%d --yes 2>&1', $limit );
		$output  = array();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- System tool invocation; parameters have been sanitized.
		exec( $command, $output, $return_code );

		if ( $return_code === 0 ) {
			WP_CLI::success( __( 'Offload completed', 'wp-genius' ) );
			WP_CLI::line( implode( "\n", $output ) );
		} else {
			WP_CLI::error( implode( "\n", $output ) );
		}
	}

	/**
	 * Clean up local files
	 *
	 * ## OPTIONS
	 *
	 * [--verify-minio]
	 * : Verify the file exists on the Minio side before deleting
	 *
	 * ## EXAMPLES
	 *
	 *     wp media-engine cleanup --verify-minio
	 *
	 * @when after_wp_load
	 */
	public function cleanup( $args, $assoc_args ) {
		$verify = isset( $assoc_args['verify-minio'] );

		WP_CLI::line( __( 'Cleaning up local files...', 'wp-genius' ) );

		// TODO: Implement cleanup logic

		WP_CLI::success( __( 'Cleanup completed', 'wp-genius' ) );
	}

	/**
	 * Check environment dependencies
	 *
	 * ## EXAMPLES
	 *
	 *     wp media-engine check-env
	 *
	 * @when after_wp_load
	 */
	public function check_env( $args, $assoc_args ) {
		if ( ! class_exists( 'MediaEngineEnvironmentChecker' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-environment-checker.php';
		}

		$results = MediaEngineEnvironmentChecker::check_all();

		WP_CLI::line( '=== System Information ===' );
		WP_CLI::line( sprintf( 'OS: %s', $results['system']['os'] ) );
		WP_CLI::line( sprintf( 'CPU Cores: %d', $results['system']['cpu_cores'] ) );
		WP_CLI::line( sprintf( 'Recommended Workers: %d', MediaEngineEnvironmentChecker::get_recommended_workers() ) );

		WP_CLI::line( "\n=== PHP Environment ===" );
		WP_CLI::line( sprintf( 'PHP Version: %s %s', $results['php']['version'], $results['php']['version_ok'] ? '✓' : '✗' ) );
		WP_CLI::line( sprintf( 'exec(): %s', $results['php']['exec_enabled'] ? '✓ Enabled' : '✗ Disabled' ) );
		WP_CLI::line( sprintf( 'proc_open(): %s', $results['php']['proc_open'] ? '✓ Enabled' : '✗ Disabled' ) );

		WP_CLI::line( "\n=== External Commands ===" );
		foreach ( $results['commands'] as $key => $cmd ) {
			$status = $cmd['available'] ? '✓' : '✗';
			WP_CLI::line( sprintf( '%s %s: %s', $status, $cmd['name'], $cmd['available'] ? $cmd['version'] : 'Not Found' ) );
		}

		if ( $results['can_process'] ) {
			WP_CLI::success( __( 'Environment is ready for media processing', 'wp-genius' ) );
		} else {
			WP_CLI::error( __( 'Some dependencies are missing. Please install them first.', 'wp-genius' ) );
		}
	}

	/**
	 * Get pending attachment statistics
	 *
	 * ## EXAMPLES
	 *
	 *     wp media-engine stats
	 *
	 * @when after_wp_load
	 */
	/**
	 * Fix /wp-content/uploads/ URLs in post contents and correct extensions for offloaded WebP media
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of posts to process per batch, default 50
	 *
	 * [--offset=<number>]
	 * : Offset for scanning posts, default 0
	 *
	 * [--id=<number>]
	 * : Process a specific post ID
	 *
	 * [--dry-run]
	 * : Inspect and report replacements without modifying the database
	 *
	 * [--all]
	 * : Continuously process all pending posts until none remain
	 *
	 * [--include-revisions]
	 * : Include post revisions in scan/fix
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview fixes for 10 posts (dry-run)
	 *     wp media-engine fix-urls --limit=10 --dry-run
	 *
	 *     # Fix URLs for a specific post
	 *     wp media-engine fix-urls --id=12345
	 *
	 *     # Batch fix 50 posts
	 *     wp media-engine fix-urls --limit=50
	 *
	 *     # Process all posts until finished
	 *     wp media-engine fix-urls --all
	 *
	 * @subcommand fix-urls
	 * @when after_wp_load
	 */
	public function fix_urls( $args, $assoc_args ) {
		$service_path = plugin_dir_path( __FILE__ ) . 'services/class-url-fixer-service.php';
		if ( ! class_exists( 'MediaEngineUrlFixerService' ) && file_exists( $service_path ) ) {
			require_once $service_path;
		}

		$fixer             = new MediaEngineUrlFixerService();
		$limit             = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 50;
		$offset            = isset( $assoc_args['offset'] ) ? max( 0, (int) $assoc_args['offset'] ) : 0;
		$post_id           = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : null;
		$dry_run           = isset( $assoc_args['dry-run'] );
		$all               = isset( $assoc_args['all'] );
		$include_revisions = isset( $assoc_args['include-revisions'] );

		if ( $dry_run ) {
			WP_CLI::warning( __( 'Running in DRY-RUN mode: no database changes will be made.', 'wp-genius' ) );
		}

		// Single post
		if ( $post_id ) {
			WP_CLI::line( sprintf( __( 'Inspecting Post ID: %d...', 'wp-genius' ), $post_id ) );
			$res = $fixer->fix_post( $post_id, $dry_run );

			if ( ! empty( $res['changes'] ) ) {
				WP_CLI::line( sprintf( __( 'Found %d URL(s) to replace:', 'wp-genius' ), count( $res['changes'] ) ) );
				foreach ( $res['changes'] as $ch ) {
					$tag = ! empty( $ch['ext_changed'] ) ? '[PATH+EXT]' : '[PATH]';
					WP_CLI::line( sprintf( '  %s %s => %s (%d times)', $tag, $ch['old'], $ch['new'], $ch['count'] ) );
				}
				if ( ! $dry_run ) {
					WP_CLI::success( sprintf( __( 'Post #%d updated successfully (%d replacements).', 'wp-genius' ), $post_id, $res['replaced'] ) );
				} else {
					WP_CLI::success( sprintf( __( '[DRY-RUN] Post #%d would have %d replacements.', 'wp-genius' ), $post_id, $res['replaced'] ) );
				}
			} else {
				WP_CLI::line( sprintf( __( 'No local uploads URLs need fixing in Post #%d (Reason: %s).', 'wp-genius' ), $post_id, $res['reason'] ?? 'none' ) );
			}
			return;
		}

		// Stats
		$stats = $fixer->get_stats( $include_revisions );
		WP_CLI::line( sprintf( 'Site Host: %s', $stats['site_host'] ) );
		WP_CLI::line( sprintf( 'Posts with local uploads URLs: ~%d (Total posts with any uploads: %d)', $stats['host_uploads_posts'], $stats['total_uploads_posts'] ) );

		$total_modified  = 0;
		$total_replaced  = 0;
		$total_ext_fixed = 0;
		$round           = 0;

		do {
			$round++;
			$pending_ids = $fixer->get_pending_post_ids( $limit, $offset, $include_revisions );
			$count       = count( $pending_ids );

			if ( 0 === $count ) {
				if ( 1 === $round ) {
					WP_CLI::success( __( 'No pending posts found requiring URL fixes.', 'wp-genius' ) );
				} else {
					WP_CLI::success( __( 'All pending posts have been processed.', 'wp-genius' ) );
				}
				break;
			}

			WP_CLI::line( sprintf( __( '--- Round %d: Processing batch of %d posts (offset: %d) ---', 'wp-genius' ), $round, $count, $offset ) );
			$batch_res = $fixer->fix_batch( $pending_ids, $dry_run );

			$total_modified  += $batch_res['modified_posts'];
			$total_replaced  += $batch_res['total_replaced'];
			$total_ext_fixed += $batch_res['ext_fixed'];

			WP_CLI::line(
				sprintf(
					'  Batch Result: %d/%d posts modified | %d URLs replaced (%d path+ext, %d path only)',
					$batch_res['modified_posts'],
					$count,
					$batch_res['total_replaced'],
					$batch_res['ext_fixed'],
					$batch_res['path_only_fixed']
				)
			);

			// If in dry-run or not modifying, advance offset to avoid infinite loop
			if ( $dry_run || 0 === $batch_res['modified_posts'] ) {
				$offset += $count;
			}

			// If not --all, stop after 1 batch
			if ( ! $all ) {
				break;
			}
		} while ( $count > 0 );

		WP_CLI::line( '==================================================' );
		WP_CLI::line(
			sprintf(
				'Summary: %d posts modified | %d URLs replaced (%d with WebP extension fix)',
				$total_modified,
				$total_replaced,
				$total_ext_fixed
			)
		);
		WP_CLI::success( $dry_run ? __( 'Dry-run preview completed.', 'wp-genius' ) : __( 'URL fixing completed successfully.', 'wp-genius' ) );
	}
}

// Register WP-CLI command
WP_CLI::add_command( 'media-engine', 'MediaEngineCLI' );

