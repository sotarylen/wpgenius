<?php
/**
 * AI Engine - Queue Tab (Inline for CSF)
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nonce = wp_create_nonce( 'w2p_ai_engine_nonce' );

// Get queue items from database.
global $wpdb;
$ai_queue_table = $wpdb->prefix . 'w2p_ai_queue';
$ai_queue_items = array();
$ai_queue_total = 0;

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ai_queue_table ) ) === $ai_queue_table ) {
	$ai_queue_items = $wpdb->get_results(
		"SELECT q.*, p.name as prompt_name
		 FROM {$wpdb->prefix}w2p_ai_queue q
		 LEFT JOIN {$wpdb->prefix}w2p_ai_prompts p ON q.prompt_id = p.id
		 ORDER BY q.created_at DESC
		 LIMIT 20",
		ARRAY_A
	) ?: array();
	$ai_queue_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}w2p_ai_queue" );
}
?>
<div class="w2p-ai-engine-wrap">
	<h3><?php esc_html_e( 'Content Queue', 'wp-genius' ); ?></h3>

	<p class="description">
		<?php
		printf(
			/* translators: %d: total queue items */
			esc_html__( 'Total items in queue: %d', 'wp-genius' ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- absint number embedded in JS, not user input.
			$ai_queue_total
		);
		?>
	</p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Prompt', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Provider', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Created', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-genius' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $ai_queue_items ) ) : ?>
				<?php foreach ( $ai_queue_items as $item ) : ?>
					<tr>
						<td>#<?php echo esc_html( $item['id'] ); ?></td>
						<td><?php echo esc_html( $item['prompt_name'] ?? 'N/A' ); ?></td>
						<td><?php echo esc_html( ucfirst( $item['provider'] ?? '' ) ); ?></td>
						<td>
							<?php
							$item_status = $item['status'] ?? 'pending';
							$colors      = array(
								'completed'  => array(
									'bg' => '#edfaef',
									'fg' => '#00a32a',
								),
								'failed'     => array(
									'bg' => '#fceeee',
									'fg' => '#d63638',
								),
								'processing' => array(
									'bg' => '#fcf0e3',
									'fg' => '#996800',
								),
							);
							$style       = $colors[ $item_status ] ?? array(
								'bg' => '#f0f0f1',
								'fg' => '#50575e',
							);
							?>
							<span style="padding: 2px 8px; border-radius: 3px; background: <?php echo esc_attr( $style['bg'] ); ?>; color: <?php echo esc_attr( $style['fg'] ); ?>;">
								<?php echo esc_html( ucfirst( $item_status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $item['created_at'] ?? '' ); ?></td>
						<td>
							<?php if ( 'completed' === $item_status && ! empty( $item['post_id'] ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>" target="_blank">View Post</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Queue is empty.', 'wp-genius' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<p>
		<button type="button" id="ai-process-queue-btn" class="button button-primary"><?php esc_html_e( 'Process Queue Now', 'wp-genius' ); ?></button>
	</p>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#ai-process-queue-btn').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true).text('Processing...');
		$.post(ajaxurl, {
			action: 'w2p_ai_process_queue',
			nonce: '<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal value / self-escaped output embedded in JS/templates, not user input. ?>'
		}, function(res) {
			$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Process Queue Now', 'wp-genius' ) ); ?>');
			location.reload();
		}).fail(function() {
			$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Process Queue Now', 'wp-genius' ) ); ?>');
		});
	});
});
</script>
