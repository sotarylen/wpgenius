<?php
/**
 * CMS Migration Logger
 *
 * @package WP_Genius
 * @subpackage Modules/CMSMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CMS_Migration_Logger
 */
class CMS_Migration_Logger {

	/**
	 * Log entries
	 *
	 * @var array
	 */
	private $entries = array();

	/**
	 * Log a message
	 *
	 * @param string $message Log message.
	 * @param string $type Log type (info, success, warning, error).
	 * @return void
	 */
	public function log( $message, $type = 'info' ) {
		// Sanitize message to prevent log injection.
		$message = wp_strip_all_tags( $message );
		$message = str_replace( array( "\r", "\n" ), ' ', $message );

		$this->entries[] = array(
			'message' => $message,
			'type'    => $type,
			'time'    => current_time( 'mysql' ),
		);

		// Use W2P_Logger if available.
		if ( class_exists( 'W2P_Logger' ) ) {
			switch ( $type ) {
				case 'error':
					W2P_Logger::error( '[CMS Migrator] ' . $message );
					break;
				case 'warning':
					W2P_Logger::warning( '[CMS Migrator] ' . $message );
					break;
				case 'success':
					W2P_Logger::info( '[CMS Migrator] ' . $message );
					break;
				default:
					W2P_Logger::info( '[CMS Migrator] ' . $message );
					break;
			}
		}
	}

	/**
	 * Get all log entries
	 *
	 * @return array
	 */
	public function get_entries() {
		return $this->entries;
	}

	/**
	 * Clear log entries
	 *
	 * @return void
	 */
	public function clear() {
		$this->entries = array();
	}

	/**
	 * Get log entries as formatted string
	 *
	 * @return string
	 */
	public function get_formatted_log() {
		$output = '';
		foreach ( $this->entries as $entry ) {
			$output .= sprintf( '[%s] [%s] %s' . PHP_EOL, $entry['time'], strtoupper( $entry['type'] ), $entry['message'] );
		}
		return $output;
	}

	/**
	 * Get log entries count
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->entries );
	}

	/**
	 * Get entries by type
	 *
	 * @param string $type Log type.
	 * @return array
	 */
	public function get_entries_by_type( $type ) {
		return array_filter(
			$this->entries,
			function ( $entry ) use ( $type ) {
				return $entry['type'] === $type;
			}
		);
	}

	/**
	 * Get error count
	 *
	 * @return int
	 */
	public function get_error_count() {
		return count( $this->get_entries_by_type( 'error' ) );
	}

	/**
	* Get success count
	 *
	 * @return int
	 */
	public function get_success_count() {
		return count( $this->get_entries_by_type( 'success' ) );
	}
}
