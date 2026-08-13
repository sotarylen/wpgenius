<?php
/**
 * AI Engine - Queue Tab
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$queue = $this->content_queue->get_queue( 1, 50 );
?>
<div class="w2p-ai-tab-content">
	<div class="w2p-ai-queue-section">
		<h2><?php esc_html_e( 'Content Queue', 'wp-genius' ); ?></h2>

		<div class="w2p-ai-queue-header">
			<div class="w2p-ai-queue-filters">
				<select id="ai-queue-status-filter" class="w2p-ai-select">
					<option value=""><?php esc_html_e( 'All Status', 'wp-genius' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'wp-genius' ); ?></option>
					<option value="processing"><?php esc_html_e( 'Processing', 'wp-genius' ); ?></option>
					<option value="completed"><?php esc_html_e( 'Completed', 'wp-genius' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Failed', 'wp-genius' ); ?></option>
				</select>
			</div>
			<div class="w2p-ai-queue-actions">
				<button type="button" id="ai-process-queue-btn" class="button button-primary">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Process Queue', 'wp-genius' ); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped w2p-ai-queue-table">
			<thead>
				<tr>
					<th class="column-id"><?php esc_html_e( 'ID', 'wp-genius' ); ?></th>
					<th class="column-provider"><?php esc_html_e( 'Provider', 'wp-genius' ); ?></th>
					<th class="column-prompt"><?php esc_html_e( 'Prompt', 'wp-genius' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
					<th class="column-created"><?php esc_html_e( 'Created', 'wp-genius' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'wp-genius' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $queue['items'] ) ) : ?>
					<?php foreach ( $queue['items'] as $item ) : ?>
						<tr data-id="<?php echo esc_attr( $item['id'] ); ?>">
							<td class="column-id">
								#<?php echo esc_html( $item['id'] ); ?>
							</td>
							<td class="column-provider">
								<?php echo esc_html( ucfirst( $item['provider'] ) ); ?>
							</td>
							<td class="column-prompt">
								<?php echo esc_html( $item['prompt_name'] ?? __( 'Unknown', 'wp-genius' ) ); ?>
							</td>
							<td class="column-status">
								<span class="w2p-ai-queue-status w2p-ai-status-<?php echo esc_attr( $item['status'] ); ?>">
									<?php echo esc_html( ucfirst( $item['status'] ) ); ?>
								</span>
								<?php if ( $item['status'] === 'failed' && ! empty( $item['error'] ) ) : ?>
									<span class="w2p-ai-queue-error" title="<?php echo esc_attr( $item['error'] ); ?>">
										(<?php esc_html_e( 'View Error', 'wp-genius' ); ?>)
									</span>
								<?php endif; ?>
							</td>
							<td class="column-created">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] ) ) ); ?>
							</td>
							<td class="column-actions">
								<?php if ( $item['status'] === 'completed' && $item['post_id'] ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>" target="_blank">
										<?php esc_html_e( 'View Post', 'wp-genius' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'Queue is empty.', 'wp-genius' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $queue['pages'] > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post( paginate_links( [
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $queue['page'],
						'total'   => $queue['pages'],
					] ) );
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
