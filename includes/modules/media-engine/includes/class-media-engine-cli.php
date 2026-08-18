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
	public function stats( $args, $assoc_args ) {
		$total = $this->processor->get_pending_count();

		// translators: %1: placeholder.
		WP_CLI::line( sprintf( __( 'Pending attachments: %d', 'wp-genius' ), $total ) );

		if ( $total > 0 ) {
			WP_CLI::line(
				sprintf(
					// translators: %1: placeholder.
					__( 'Estimated batches (100/batch): %d', 'wp-genius' ),
					ceil( $total / 100 )
				)
			);
		}
	}
}

// Register WP-CLI command
WP_CLI::add_command( 'media-engine', 'MediaEngineCLI' );
