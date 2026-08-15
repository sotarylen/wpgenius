<?php
/**
 * Scheduler
 *
 * Scheduled task scheduler
 *
 * Schedule data is stored in the custom table {$wpdb->prefix}w2p_ai_schedules (replacing the earlier one-option-per-schedule storage,
 * eliminating LIKE scans on the options table).
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_AI_Scheduler
 */
class W2P_AI_Scheduler {

	/**
	 * Option prefix for schedules (legacy storage, used only for migration cleanup).
	 *
	 * @var string
	 */
	private $option_prefix = 'w2p_ai_schedule_';

	/**
	 * Cron hook name
	 *
	 * @var string
	 */
	private $cron_hook = 'w2p_ai_content_generation';

	/**
	 * Database table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Provider Manager
	 *
	 * @var W2P_AI_Provider_Manager
	 */
	private $provider_manager;

	/**
	 * Content Queue
	 *
	 * @var W2P_AI_Content_Queue
	 */
	private $content_queue;

	/**
	 * Constructor
	 *
	 * @param W2P_AI_Provider_Manager $provider_manager Provider manager instance.
	 * @param W2P_AI_Content_Queue    $content_queue Content queue instance.
	 */
	public function __construct( ?W2P_AI_Provider_Manager $provider_manager = null, ?W2P_AI_Content_Queue $content_queue = null ) {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'w2p_ai_schedules';

		$this->provider_manager = $provider_manager ?? new W2P_AI_Provider_Manager();
		$this->content_queue    = $content_queue ?? new W2P_AI_Content_Queue( $this->provider_manager );

		$this->maybe_create_table();
		$this->maybe_migrate_legacy();

		// Initialize cron schedules
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedules( array $schedules ): array {
		$schedules['ai_generation_hourly'] = array(
			'interval' => HOUR_IN_SECONDS,
			'display'  => __( 'Every Hour (AI Generation)', 'wp-genius' ),
		);

		$schedules['ai_generation_half_day'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Twice Daily (AI Generation)', 'wp-genius' ),
		);

		return $schedules;
	}

	/**
	 * Create the schedules table if it does not exist.
	 *
	 * @return void
	 */
	private function maybe_create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}w2p_ai_schedules (
			id VARCHAR(36) NOT NULL,
			name VARCHAR(255) NOT NULL DEFAULT '',
			provider VARCHAR(50) NOT NULL DEFAULT '',
			model VARCHAR(100) NOT NULL DEFAULT '',
			prompt_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			frequency VARCHAR(20) NOT NULL DEFAULT 'daily',
			time VARCHAR(10) NOT NULL DEFAULT '09:00',
			quantity INT NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			categories TEXT,
			tags TEXT,
			featured TINYINT(1) NOT NULL DEFAULT 0,
			variables TEXT,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			last_run_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * One-time migration: import legacy w2p_ai_schedule_* options into the table.
	 *
	 * Legacy options are kept in place after import (safe rollback); they are
	 * cleaned up by uninstall.php.
	 *
	 * @return void
	 */
	private function maybe_migrate_legacy() {
		if ( get_option( 'w2p_ai_schedules_migrated' ) ) {
			return;
		}

		global $wpdb;

		// Only migrate when the legacy storage actually contains schedules.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value
				 FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 ORDER BY option_name ASC",
				$this->option_prefix . '%'
			),
			ARRAY_A
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$schedule_id = substr( $result['option_name'], strlen( $this->option_prefix ) );

				// Skip last-run marker options (stored separately in legacy format).
				if ( false !== strpos( $schedule_id, '_last_run' ) ) {
					continue;
				}

				$data = json_decode( $result['option_value'], true );
				if ( ! is_array( $data ) || empty( $data['id'] ) ) {
					continue;
				}

				$data['last_run_at'] = get_option( $this->option_prefix . $schedule_id . '_last_run', '' );

				$this->upsert( $data );
			}
		}

		update_option( 'w2p_ai_schedules_migrated', true );
	}

	/**
	 * Insert or update a schedule row.
	 *
	 * @param array $data Schedule data.
	 * @return void
	 */
	private function upsert( array $data ) {
		global $wpdb;

		$wpdb->replace(
			$this->table_name,
			array(
				'id'          => $data['id'],
				'name'        => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
				'provider'    => isset( $data['provider'] ) ? sanitize_text_field( $data['provider'] ) : '',
				'model'       => isset( $data['model'] ) ? sanitize_text_field( $data['model'] ) : '',
				'prompt_id'   => isset( $data['prompt_id'] ) ? absint( $data['prompt_id'] ) : 0,
				'frequency'   => isset( $data['frequency'] ) ? sanitize_text_field( $data['frequency'] ) : 'daily',
				'time'        => isset( $data['time'] ) ? sanitize_text_field( $data['time'] ) : '09:00',
				'quantity'    => isset( $data['quantity'] ) ? absint( $data['quantity'] ) : 1,
				'status'      => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'draft',
				'categories'  => isset( $data['categories'] ) ? wp_json_encode( (array) $data['categories'] ) : '[]',
				'tags'        => isset( $data['tags'] ) ? wp_json_encode( (array) $data['tags'] ) : '[]',
				'featured'    => ! empty( $data['featured'] ) ? 1 : 0,
				'variables'   => isset( $data['variables'] ) ? wp_json_encode( (array) $data['variables'] ) : '{}',
				'enabled'     => ! empty( $data['enabled'] ) ? 1 : 0,
				'last_run_at' => ! empty( $data['last_run_at'] ) ? $data['last_run_at'] : null,
			)
		);
	}

	/**
	 * Save schedule
	 *
	 * @param array $data Schedule data.
	 * @return string|false Schedule ID or false on failure.
	 */
	public function save_schedule( array $data ) {
		$defaults = array(
			'name'       => '',
			'provider'   => '',
			'model'      => '',
			'prompt_id'  => 0,
			'frequency'  => 'daily',
			'time'       => '09:00',
			'quantity'   => 1,
			'status'     => 'draft',
			'categories' => array(),
			'tags'       => array(),
			'featured'   => false,
			'variables'  => array(),
			'enabled'    => true,
		);

		$data = wp_parse_args( $data, $defaults );

		// Generate schedule ID if new
		if ( empty( $data['id'] ) ) {
			$data['id'] = wp_generate_uuid4();
		}

		// Validate provider
		$provider = $this->provider_manager->get_provider( $data['provider'] );
		if ( ! $provider ) {
			return false;
		}

		// Save schedule
		$this->upsert( $data );

		// Update cron
		$this->update_cron_schedule();

		return $data['id'];
	}

	/**
	 * Get schedule by ID
	 *
	 * @param string $schedule_id Schedule ID.
	 * @return array|null
	 */
	public function get_schedule( string $schedule_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}w2p_ai_schedules WHERE id = %s",
				$schedule_id
			),
			ARRAY_A
		);

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Get all schedules
	 *
	 * @return array
	 */
	public function get_all_schedules(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}w2p_ai_schedules ORDER BY created_at ASC, name ASC",
			ARRAY_A
		);

		$schedules = array();
		foreach ( $results as $row ) {
			$schedules[] = $this->hydrate( $row );
		}

		return $schedules;
	}

	/**
	 * Delete schedule
	 *
	 * @param string $schedule_id Schedule ID.
	 * @return bool
	 */
	public function delete_schedule( string $schedule_id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete(
			$this->table_name,
			array( 'id' => $schedule_id )
		);

		if ( $deleted ) {
			$this->update_cron_schedule();
		}

		return (bool) $deleted;
	}

	/**
	 * Toggle schedule enabled/disabled
	 *
	 * @param string $schedule_id Schedule ID.
	 * @return bool New enabled state.
	 */
	public function toggle_schedule( string $schedule_id ): bool {
		$schedule = $this->get_schedule( $schedule_id );

		if ( ! $schedule ) {
			return false;
		}

		$new_state = ! $schedule['enabled'];

		global $wpdb;
		$wpdb->update(
			$this->table_name,
			array( 'enabled' => $new_state ? 1 : 0 ),
			array( 'id' => $schedule_id )
		);

		$this->update_cron_schedule();

		return $new_state;
	}

	/**
	 * Update cron schedule based on all enabled schedules
	 *
	 * @return void
	 */
	public function update_cron_schedule(): void {
		// Clear existing schedule
		wp_clear_scheduled_hook( $this->cron_hook );

		$schedules   = $this->get_all_schedules();
		$has_enabled = false;

		foreach ( $schedules as $schedule ) {
			if ( ! empty( $schedule['enabled'] ) ) {
				$has_enabled = true;
				break;
			}
		}

		if ( $has_enabled ) {
			// Schedule to run every hour, individual schedules will be checked at runtime
			if ( ! wp_next_scheduled( $this->cron_hook ) ) {
				wp_schedule_event( time(), 'hourly', $this->cron_hook );
			}
		}
	}

	/**
	 * Run scheduled tasks
	 *
	 * @return void
	 */
	public function run_scheduled_tasks(): void {
		$schedules    = $this->get_all_schedules();
		$current_time = current_time( 'H:i' );

		foreach ( $schedules as $schedule ) {
			if ( empty( $schedule['enabled'] ) ) {
				continue;
			}

			// Check if it's time to run this schedule
			if ( ! $this->should_run_now( $schedule, $current_time ) ) {
				continue;
			}

			// Add tasks to queue
			$this->add_tasks_to_queue( $schedule );
		}

		// Process queue (P1-4: processes a bounded number of items per tick)
		$this->content_queue->process_queue();
	}

	/**
	 * Check if schedule should run now
	 *
	 * @param array  $schedule     Schedule data.
	 * @param string $current_time Current time (H:i).
	 * @return bool
	 */
	private function should_run_now( array $schedule, string $current_time ): bool {
		$schedule_time = $schedule['time'] ?? '09:00';
		$frequency     = $schedule['frequency'] ?? 'daily';
		$last_run      = $schedule['last_run_at'] ?? '';

		// Check if already ran today for daily/weekly schedules
		if ( in_array( $frequency, array( 'daily', 'weekly' ), true ) ) {
			$today = current_time( 'Y-m-d' );
			if ( $last_run && strpos( $last_run, $today ) === 0 ) {
				return false;
			}
		}

		// Check time window (run within 1 hour of scheduled time)
		$schedule_timestamp = strtotime( $schedule_time );
		$current_timestamp  = strtotime( $current_time );

		$diff     = abs( $schedule_timestamp - $current_timestamp );
		$max_diff = HOUR_IN_SECONDS; // 1 hour window

		if ( $diff > $max_diff ) {
			return false;
		}

		// Check frequency
		switch ( $frequency ) {
			case 'hourly':
				if ( $last_run ) {
					$last_run_time = strtotime( $last_run );
					if ( time() - $last_run_time < HOUR_IN_SECONDS ) {
						return false;
					}
				}
				break;

			case 'twice_daily':
				if ( $last_run ) {
					$last_run_time = strtotime( $last_run );
					if ( time() - $last_run_time < 12 * HOUR_IN_SECONDS ) {
						return false;
					}
				}
				break;

			case 'daily':
				// Already checked above
				break;

			case 'weekly':
				$last_run_date = $last_run ? date( 'Y-m-d', strtotime( $last_run ) ) : '';
				$week_ago      = date( 'Y-m-d', strtotime( '-7 days' ) );
				if ( $last_run_date && $last_run_date > $week_ago ) {
					return false;
				}
				break;
		}

		// Persist last run time on the schedule row.
		global $wpdb;
		$wpdb->update(
			$this->table_name,
			array( 'last_run_at' => current_time( 'mysql' ) ),
			array( 'id' => $schedule['id'] )
		);

		return true;
	}

	/**
	 * Add tasks to queue based on schedule
	 *
	 * @param array $schedule Schedule data.
	 * @return void
	 */
	private function add_tasks_to_queue( array $schedule ): void {
		$quantity = $schedule['quantity'] ?? 1;

		for ( $i = 0; $i < $quantity; $i++ ) {
			$this->content_queue->add_to_queue(
				array(
					'schedule_id'  => $schedule['id'],
					'provider'     => $schedule['provider'],
					'model'        => $schedule['model'],
					'prompt_id'    => $schedule['prompt_id'],
					'variables'    => $schedule['variables'] ?? array(),
					'status'       => 'pending',
					'max_attempts' => 3,
				)
			);
		}
	}

	/**
	 * Get schedule statistics
	 *
	 * @return array
	 */
	public function get_stats(): array {
		global $wpdb;

		$schedules      = $this->get_all_schedules();
		$enabled_count  = 0;
		$disabled_count = 0;

		foreach ( $schedules as $schedule ) {
			if ( ! empty( $schedule['enabled'] ) ) {
				++$enabled_count;
			} else {
				++$disabled_count;
			}
		}

		// Get queue stats
		$pending = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}w2p_ai_queue WHERE status = 'pending'"
		);

		$processing = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}w2p_ai_queue WHERE status = 'processing'"
		);

		$completed_today = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}w2p_ai_queue
				 WHERE status = 'completed'
				 AND processed_at >= %s",
				current_time( 'Y-m-d' )
			)
		);

		return array(
			'total_schedules'    => count( $schedules ),
			'enabled_schedules'  => $enabled_count,
			'disabled_schedules' => $disabled_count,
			'queue_pending'      => (int) $pending,
			'queue_processing'   => (int) $processing,
			'completed_today'    => (int) $completed_today,
		);
	}

	/**
	 * Convert a DB row into the schedule array shape used across the module.
	 *
	 * @param array $row Raw table row.
	 * @return array
	 */
	private function hydrate( array $row ): array {
		return array(
			'id'          => $row['id'],
			'name'        => $row['name'],
			'provider'    => $row['provider'],
			'model'       => $row['model'],
			'prompt_id'   => (int) $row['prompt_id'],
			'frequency'   => $row['frequency'],
			'time'        => $row['time'],
			'quantity'    => (int) $row['quantity'],
			'status'      => $row['status'],
			'categories'  => json_decode( $row['categories'] ?: '[]', true ),
			'tags'        => json_decode( $row['tags'] ?: '[]', true ),
			'featured'    => (bool) $row['featured'],
			'variables'   => json_decode( $row['variables'] ?: '{}', true ),
			'enabled'     => (bool) $row['enabled'],
			'created_at'  => $row['created_at'] ?? '',
			'updated_at'  => $row['updated_at'] ?? '',
			'last_run_at' => $row['last_run_at'] ?? '',
		);
	}
}

// Legacy alias for backward compatibility (pre-1.2.0 class name).
if ( ! class_exists( 'AI_Scheduler', false ) ) {
	class_alias( 'W2P_AI_Scheduler', 'AI_Scheduler' );
}
