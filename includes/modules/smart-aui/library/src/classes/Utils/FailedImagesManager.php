<?php
/**
 * Failed Images Manager
 *
 * @package SmartAutoUploadImages\Utils
 */

namespace SmartAutoUploadImages\Utils;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FailedImagesManager Class
 */
class FailedImagesManager {

	/**
	 * Option name for failed images log
	 */
	const OPTION_NAME = 'smart_aui_failed_images_log';

	/**
	 * Record a failed image URL
	 *
	 * @param string $url The image URL that failed to download.
	 * @return void
	 */
	public function add_failed_url( string $url ): void {
		if ( empty( $url ) ) {
			return;
		}

		$failed_logs = get_option( self::OPTION_NAME, [] );
		
		// Use URL as key to avoid duplicates and store timestamp as value
		$failed_logs[ $url ] = current_time( 'timestamp' );

		// Limit to last 500 entries to prevent option bloat
		if ( count( $failed_logs ) > 500 ) {
			asort( $failed_logs );
			$failed_logs = array_slice( $failed_logs, -500, null, true );
		}

		update_option( self::OPTION_NAME, $failed_logs );
	}

	/**
	 * Check if a URL has previously failed
	 *
	 * @param string $url The image URL to check.
	 * @return bool True if the URL is in the failed log.
	 */
	public function is_failed( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		$failed_logs = get_option( self::OPTION_NAME, [] );
		return isset( $failed_logs[ $url ] );
	}

	/**
	 * Get all failed image URLs
	 *
	 * @return array List of image URLs and their failure timestamps.
	 */
	public function get_failed_urls(): array {
		return get_option( self::OPTION_NAME, [] );
	}

	/**
	 * Clear the failed images log
	 *
	 * @return void
	 */
	public function clear_logs(): void {
		delete_option( self::OPTION_NAME );
	}
}
