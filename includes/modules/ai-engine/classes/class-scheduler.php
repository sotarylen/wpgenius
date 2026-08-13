<?php
/**
 * Scheduler
 *
 * 定时任务调度器
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Scheduler
 */
class AI_Scheduler {

	/**
	 * Option prefix for schedules
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
	 * Provider Manager
	 *
	 * @var AI_Provider_Manager
	 */
	private $provider_manager;

	/**
	 * Content Queue
	 *
	 * @var AI_Content_Queue
	 */
	private $content_queue;

	/**
	 * Constructor
	 *
	 * @param AI_Provider_Manager $provider_manager Provider manager instance.
	 * @param AI_Content_Queue    $content_queue Content queue instance.
	 */
	public function __construct( AI_Provider_Manager $provider_manager = null, AI_Content_Queue $content_queue = null ) {
		$this->provider_manager = $provider_manager ?? new AI_Provider_Manager();
		$this->content_queue    = $content_queue ?? new AI_Content_Queue( $this->provider_manager );

		// Initialize cron schedules
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedules( array $schedules ): array {
		$schedules['ai_generation_hourly'] = [
			'interval' => HOUR_IN_SECONDS,
			'display'  => __( 'Every Hour (AI Generation)', 'wp-genius' ),
		];

		$schedules['ai_generation_half_day'] = [
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Twice Daily (AI Generation)', 'wp-genius' ),
		];

		return $schedules;
	}

	/**
	 * Save schedule
	 *
	 * @param array $data Schedule data.
	 * @return int|false Schedule ID or false on failure.
	 */
	public function save_schedule( array $data ) {
		$defaults = [
			'name'       => '',
			'provider'   => '',
			'model'      => '',
			'prompt_id'  => 0,
			'frequency'  => 'daily',
			'time'       => '09:00',
			'quantity'   => 1,
			'status'     => 'draft',
			'categories' => [],
			'tags'       => [],
			'featured'   => false,
			'variables'  => [],
			'enabled'    => true,
		];

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
		update_option( $this->option_prefix . $data['id'], $data );

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
		return get_option( $this->option_prefix . $schedule_id, null );
	}

	/**
	 * Get all schedules
	 *
	 * @return array
	 */
	public function get_all_schedules(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT option_name, option_value
			 FROM {$wpdb->options}
			 WHERE option_name LIKE '{$this->option_prefix}%'
			 ORDER BY option_name ASC",
			ARRAY_A
		);

		$schedules = [];
		foreach ( $results as $result ) {
			$schedule = json_decode( $result['option_value'], true );
			if ( $schedule ) {
				$schedules[] = $schedule;
			}
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
		$deleted = delete_option( $this->option_prefix . $schedule_id );

		if ( $deleted ) {
			$this->update_cron_schedule();
		}

		return $deleted;
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

		$schedule['enabled'] = ! $schedule['enabled'];
		update_option( $this->option_prefix . $schedule_id, $schedule );

		$this->update_cron_schedule();

		return $schedule['enabled'];
	}

	/**
	 * Update cron schedule based on all enabled schedules
	 *
	 * @return void
	 */
	public function update_cron_schedule(): void {
		// Clear existing schedule
		wp_clear_scheduled_hook( $this->cron_hook );

		$schedules = $this->get_all_schedules();
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
		$schedules = $this->get_all_schedules();
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

		// Process queue
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
		$frequency = $schedule['frequency'] ?? 'daily';

		// Get last run time
		$last_run = get_option( $this->option_prefix . $schedule['id'] . '_last_run', '' );

		// Check if already ran today for daily/weekly schedules
		if ( in_array( $frequency, [ 'daily', 'weekly' ], true ) ) {
			$today = current_time( 'Y-m-d' );
			if ( $last_run && strpos( $last_run, $today ) === 0 ) {
				return false;
			}
		}

		// Check time window (run within 1 hour of scheduled time)
		$schedule_timestamp = strtotime( $schedule_time );
		$current_timestamp = strtotime( $current_time );

		$diff = abs( $schedule_timestamp - $current_timestamp );
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
				$week_ago = date( 'Y-m-d', strtotime( '-7 days' ) );
				if ( $last_run_date && $last_run_date > $week_ago ) {
					return false;
				}
				break;
		}

		// Update last run time
		update_option( $this->option_prefix . $schedule['id'] . '_last_run', current_time( 'mysql' ) );

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
			$this->content_queue->add_to_queue( [
				'schedule_id' => $schedule['id'],
				'provider'    => $schedule['provider'],
				'model'       => $schedule['model'],
				'prompt_id'   => $schedule['prompt_id'],
				'variables'   => $schedule['variables'] ?? [],
				'status'      => 'pending',
				'max_attempts'=> 3,
			] );
		}
	}

	/**
	 * Get schedule statistics
	 *
	 * @return array
	 */
	public function get_stats(): array {
		global $wpdb;

		$schedules = $this->get_all_schedules();
		$enabled_count = 0;
		$disabled_count = 0;

		foreach ( $schedules as $schedule ) {
			if ( ! empty( $schedule['enabled'] ) ) {
				$enabled_count++;
			} else {
				$disabled_count++;
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

		return [
			'total_schedules'   => count( $schedules ),
			'enabled_schedules' => $enabled_count,
			'disabled_schedules'=> $disabled_count,
			'queue_pending'     => (int) $pending,
			'queue_processing'  => (int) $processing,
			'completed_today'   => (int) $completed_today,
		];
	}
}
