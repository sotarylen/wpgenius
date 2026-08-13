<?php
/**
 * Content Queue
 *
 * 内容生成队列
 *
 * @package WP_Genius
 * @subpackage Modules/AIEngine/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_AI_Content_Queue
 */
class W2P_AI_Content_Queue {

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
	 * Prompt Engine
	 *
	 * @var W2P_AI_Prompt_Engine
	 */
	private $prompt_engine;

	/**
	 * Constructor
	 *
	 * @param W2P_AI_Provider_Manager $provider_manager Provider manager instance.
	 * @param W2P_AI_Prompt_Engine    $prompt_engine Prompt engine instance.
	 */
	public function __construct( ?W2P_AI_Provider_Manager $provider_manager = null, ?W2P_AI_Prompt_Engine $prompt_engine = null ) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'w2p_ai_queue';

		$this->provider_manager = $provider_manager ?? new W2P_AI_Provider_Manager();
		$this->prompt_engine    = $prompt_engine ?? new W2P_AI_Prompt_Engine();

		$this->maybe_create_table();
	}

	/**
	 * Create database table if not exists
	 *
	 * @return void
	 */
	private function maybe_create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}w2p_ai_queue (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			schedule_id BIGINT(20) UNSIGNED DEFAULT NULL,
			provider VARCHAR(50) NOT NULL,
			model VARCHAR(100) NOT NULL,
			prompt_id BIGINT(20) UNSIGNED NOT NULL,
			variables TEXT,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			post_id BIGINT(20) UNSIGNED DEFAULT NULL,
			result TEXT,
			error TEXT,
			attempts INT DEFAULT 0,
			max_attempts INT DEFAULT 3,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			processed_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY schedule_id (schedule_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add item to queue
	 *
	 * @param array $data Queue item data.
	 * @return int|false Item ID or false on failure.
	 */
	public function add_to_queue( array $data ) {
		global $wpdb;

		$defaults = [
			'schedule_id' => null,
			'provider'    => '',
			'model'       => '',
			'prompt_id'   => 0,
			'variables'   => [],
			'status'      => 'pending',
			'max_attempts'=> 3,
		];

		$data = wp_parse_args( $data, $defaults );

		$insert_data = [
			'schedule_id'  => $data['schedule_id'] ? absint( $data['schedule_id'] ) : null,
			'provider'     => sanitize_text_field( $data['provider'] ),
			'model'        => sanitize_text_field( $data['model'] ),
			'prompt_id'    => absint( $data['prompt_id'] ),
			'variables'    => wp_json_encode( $data['variables'] ),
			'status'       => sanitize_text_field( $data['status'] ),
			'max_attempts' => absint( $data['max_attempts'] ),
		];

		$wpdb->insert( $this->table_name, $insert_data );

		return $wpdb->insert_id;
	}

	/**
	 * Get queue items
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Items per page.
	 * @param string $status Status filter.
	 * @return array
	 */
	public function get_queue( int $page = 1, int $per_page = 20, string $status = '' ): array {
		global $wpdb;

		$where      = '';
		$query_args = array();
		if ( ! empty( $status ) ) {
			$where       = ' WHERE q.status = %s';
			$query_args[] = $status;
		}

		$offset = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- 动态 WHERE 片段值经 prepare 占位符传递；LIMIT/OFFSET 在 prepare 内以 %d 绑定。
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.*, p.name as prompt_name
				 FROM {$wpdb->prefix}w2p_ai_queue q
				 LEFT JOIN {$wpdb->prefix}w2p_ai_prompts p ON q.prompt_id = p.id
				 {$where}
				 ORDER BY q.created_at DESC
				 LIMIT %d OFFSET %d",
				array_merge( $query_args, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}w2p_ai_queue q {$where}",
				$query_args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'items'     => $results ?? [],
			'total'     => (int) $total,
			'page'      => $page,
			'per_page'  => $per_page,
			'pages'     => ceil( $total / $per_page ),
		];
	}

	/**
	 * Process queue
	 *
	 * @return bool
	 */
	public function process_queue(): bool {
		global $wpdb;

		// Get pending items
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}w2p_ai_queue
				 WHERE status = 'pending'
				 AND attempts < max_attempts
				 ORDER BY created_at ASC
				 LIMIT %d",
				10 // Process 10 items at a time
			),
			ARRAY_A
		);

		if ( empty( $items ) ) {
			return true;
		}

		foreach ( $items as $item ) {
			$this->process_item( $item );
		}

		return true;
	}

	/**
	 * Process single queue item
	 *
	 * @param array $item Queue item data.
	 * @return bool
	 */
	private function process_item( array $item ): bool {
		global $wpdb;

		// Update status to processing
		$wpdb->update(
			$this->table_name,
			[ 'status' => 'processing' ],
			[ 'id' => $item['id'] ]
		);

		// Get provider
		$provider = $this->provider_manager->get_provider( $item['provider'] );
		if ( ! $provider ) {
			$this->mark_failed( $item['id'], 'Invalid provider' );
			return false;
		}

		// Get prompt
		$prompt = $this->prompt_engine->get_prompt( $item['prompt_id'] );
		if ( ! $prompt ) {
			$this->mark_failed( $item['id'], 'Invalid prompt' );
			return false;
		}

		// Process variables
		$variables = json_decode( $item['variables'], true ) ?? [];
		$processed_prompt = $this->prompt_engine->process_template( $prompt['template'], $variables );

		// Generate content
		$result = $provider->generate( [
			'prompt'      => $processed_prompt,
			'model'       => $item['model'],
			'temperature' => $prompt['temperature'] ?? 0.7,
			'max_tokens'  => $prompt['max_tokens'] ?? 2000,
		] );

		if ( is_wp_error( $result ) ) {
			$this->mark_failed( $item['id'], $result->get_error_message() );
			return false;
		}

		// Create post
		$post_id = $this->create_post_from_ai( $result['content'], $item );

		if ( is_wp_error( $post_id ) ) {
			$this->mark_failed( $item['id'], $post_id->get_error_message() );
			return false;
		}

		// Mark as completed
		$wpdb->update(
			$this->table_name,
			[
				'status'      => 'completed',
				'post_id'     => $post_id,
				'result'      => wp_json_encode( $result ),
				'processed_at' => current_time( 'mysql' ),
			],
			[ 'id' => $item['id'] ]
		);

		return true;
	}

	/**
	 * Create post from AI content
	 *
	 * @param string $content AI-generated content.
	 * @param array  $item    Queue item data.
	 * @return int|WP_Error Post ID or error.
	 */
	private function create_post_from_ai( string $content, array $item ) {
		// Parse content to extract title and body
		$lines = explode( "\n", $content );
		$title = '';
		$body  = $content;

		// Try to extract title from first heading
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( preg_match( '/^#+\s+(.+)$/', $trimmed, $matches ) ) {
				$title = $matches[1];
				$body  = trim( str_replace( $line, '', $content ) );
				break;
			}
		}

		if ( empty( $title ) ) {
			$title = substr( wp_strip_all_tags( $content ), 0, 100 );
		}

		// Get schedule settings
		$schedule = null;
		if ( $item['schedule_id'] ) {
			$schedule = get_option( 'w2p_ai_schedule_' . $item['schedule_id'], null );
		}

		$post_data = [
			'post_title'   => $title,
			'post_content' => wpautop( $body ),
			'post_status'  => $schedule['status'] ?? 'draft',
			'post_type'    => 'post',
		];

		// Add categories
		if ( ! empty( $schedule['categories'] ) ) {
			$post_data['post_category'] = $schedule['categories'];
		}

		// Add tags
		if ( ! empty( $schedule['tags'] ) ) {
			$post_data['tags_input'] = $schedule['tags'];
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Add meta data
		update_post_meta( $post_id, '_w2p_ai_generated', true );
		update_post_meta( $post_id, '_w2p_ai_provider', $item['provider'] );
		update_post_meta( $post_id, '_w2p_ai_model', $item['model'] );
		update_post_meta( $post_id, '_w2p_ai_prompt_id', $item['prompt_id'] );
		update_post_meta( $post_id, '_w2p_ai_queue_id', $item['id'] );

		return $post_id;
	}

	/**
	 * Mark item as failed
	 *
	 * @param int    $item_id Item ID.
	 * @param string $error   Error message.
	 * @return void
	 */
	private function mark_failed( int $item_id, string $error ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}w2p_ai_queue
				 SET status = IF(attempts + 1 >= max_attempts, 'failed', 'pending'),
				     attempts = attempts + 1,
				     error = %s,
				     processed_at = NOW()
				 WHERE id = %d",
				$error,
				$item_id
			)
		);
	}

	/**
	 * Clear completed items older than X days
	 *
	 * @param int $days Number of days.
	 * @return int Number of deleted items.
	 */
	public function clear_old_items( int $days = 30 ): int {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}w2p_ai_queue
				 WHERE status IN ('completed', 'failed')
				 AND processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return $deleted;
	}
}

// Legacy alias for backward compatibility (pre-1.2.0 class name).
if ( ! class_exists( 'AI_Content_Queue', false ) ) {
	class_alias( 'W2P_AI_Content_Queue', 'AI_Content_Queue' );
}
