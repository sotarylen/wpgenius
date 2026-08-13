<?php
/**
 * AI Engine - Schedules Tab
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$providers = $this->provider_manager->get_providers_list();
$prompts   = $this->prompt_engine->get_prompts();
$schedules = $this->scheduler->get_all_schedules();
$stats     = $this->scheduler->get_stats();
?>
<div class="w2p-ai-tab-content">
	<div class="w2p-ai-schedules-section">
		<h2><?php esc_html_e( 'Content Schedules', 'wp-genius' ); ?></h2>

		<div class="w2p-ai-stats-row">
			<div class="w2p-ai-stat-box">
				<span class="w2p-ai-stat-value"><?php echo esc_html( $stats['enabled_schedules'] ); ?></span>
				<span class="w2p-ai-stat-label"><?php esc_html_e( 'Active Schedules', 'wp-genius' ); ?></span>
			</div>
			<div class="w2p-ai-stat-box">
				<span class="w2p-ai-stat-value"><?php echo esc_html( $stats['queue_pending'] ); ?></span>
				<span class="w2p-ai-stat-label"><?php esc_html_e( 'Pending Tasks', 'wp-genius' ); ?></span>
			</div>
			<div class="w2p-ai-stat-box">
				<span class="w2p-ai-stat-value"><?php echo esc_html( $stats['completed_today'] ); ?></span>
				<span class="w2p-ai-stat-label"><?php esc_html_e( 'Completed Today', 'wp-genius' ); ?></span>
			</div>
		</div>

		<div class="w2p-ai-schedules-header">
			<button type="button" id="ai-add-schedule-btn" class="button button-primary">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add New Schedule', 'wp-genius' ); ?>
			</button>
		</div>

		<table class="wp-list-table widefat fixed striped w2p-ai-schedules-table">
			<thead>
				<tr>
					<th class="column-name"><?php esc_html_e( 'Name', 'wp-genius' ); ?></th>
					<th class="column-provider"><?php esc_html_e( 'Provider', 'wp-genius' ); ?></th>
					<th class="column-schedule"><?php esc_html_e( 'Schedule', 'wp-genius' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'wp-genius' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $schedules ) ) : ?>
					<?php foreach ( $schedules as $schedule ) : ?>
						<tr data-id="<?php echo esc_attr( $schedule['id'] ); ?>">
							<td class="column-name">
								<strong><?php echo esc_html( $schedule['name'] ); ?></strong>
							</td>
							<td class="column-provider">
								<?php echo esc_html( ucfirst( $schedule['provider'] ) ); ?>
							</td>
							<td class="column-schedule">
								<?php
								$frequency_labels = array(
									'hourly'      => __( 'Every Hour', 'wp-genius' ),
									'twice_daily' => __( 'Twice Daily', 'wp-genius' ),
									'daily'       => __( 'Daily', 'wp-genius' ),
									'weekly'      => __( 'Weekly', 'wp-genius' ),
								);
								$frequency        = $schedule['frequency'] ?? 'daily';
								echo esc_html( ( $frequency_labels[ $frequency ] ?? $frequency ) . ' at ' . $schedule['time'] );
								?>
							</td>
							<td class="column-status">
								<?php if ( ! empty( $schedule['enabled'] ) ) : ?>
									<span class="w2p-ai-status-active"><?php esc_html_e( 'Active', 'wp-genius' ); ?></span>
								<?php else : ?>
									<span class="w2p-ai-status-inactive"><?php esc_html_e( 'Inactive', 'wp-genius' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="column-actions">
								<a href="#" class="ai-edit-schedule" data-id="<?php echo esc_attr( $schedule['id'] ); ?>">
									<?php esc_html_e( 'Edit', 'wp-genius' ); ?>
								</a>
								<a href="#" class="ai-toggle-schedule" data-id="<?php echo esc_attr( $schedule['id'] ); ?>">
									<?php echo ! empty( $schedule['enabled'] ) ? esc_html__( 'Disable', 'wp-genius' ) : esc_html__( 'Enable', 'wp-genius' ); ?>
								</a>
								<a href="#" class="ai-delete-schedule" data-id="<?php echo esc_attr( $schedule['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'wp-genius' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No schedules configured. Add a new schedule to start automatic content generation.', 'wp-genius' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Schedule Editor Modal -->
	<div id="ai-schedule-modal" class="w2p-ai-modal" style="display: none;">
		<div class="w2p-ai-modal-content w2p-ai-modal-large">
			<div class="w2p-ai-modal-header">
				<h2 id="ai-schedule-modal-title"><?php esc_html_e( 'Add New Schedule', 'wp-genius' ); ?></h2>
				<button type="button" class="w2p-ai-modal-close">&times;</button>
			</div>
			<div class="w2p-ai-modal-body">
				<input type="hidden" id="ai-schedule-id" value="">

				<div class="w2p-ai-form-columns">
					<div class="w2p-ai-form-column">
						<div class="w2p-ai-form-row">
							<label for="ai-schedule-name"><?php esc_html_e( 'Schedule Name', 'wp-genius' ); ?></label>
							<input type="text" id="ai-schedule-name" class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g., Daily Blog Posts', 'wp-genius' ); ?>">
						</div>

						<div class="w2p-ai-form-row">
							<label for="ai-schedule-provider"><?php esc_html_e( 'Provider', 'wp-genius' ); ?></label>
							<select id="ai-schedule-provider" class="w2p-ai-select">
								<option value=""><?php esc_html_e( 'Select Provider', 'wp-genius' ); ?></option>
								<?php foreach ( $providers as $provider ) : ?>
									<option value="<?php echo esc_attr( $provider['slug'] ); ?>">
										<?php echo esc_html( $provider['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="w2p-ai-form-row">
							<label for="ai-schedule-model"><?php esc_html_e( 'Model', 'wp-genius' ); ?></label>
							<select id="ai-schedule-model" class="w2p-ai-select" disabled>
								<option value=""><?php esc_html_e( 'Select provider first', 'wp-genius' ); ?></option>
							</select>
						</div>

						<div class="w2p-ai-form-row">
							<label for="ai-schedule-prompt"><?php esc_html_e( 'Prompt Template', 'wp-genius' ); ?></label>
							<select id="ai-schedule-prompt" class="w2p-ai-select">
								<option value=""><?php esc_html_e( 'Select Prompt', 'wp-genius' ); ?></option>
								<?php foreach ( $prompts as $prompt ) : ?>
									<option value="<?php echo esc_attr( $prompt['id'] ); ?>">
										<?php echo esc_html( $prompt['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="w2p-ai-form-column">
						<div class="w2p-ai-form-row">
							<label for="ai-schedule-frequency"><?php esc_html_e( 'Frequency', 'wp-genius' ); ?></label>
							<select id="ai-schedule-frequency" class="w2p-ai-select">
								<option value="hourly"><?php esc_html_e( 'Every Hour', 'wp-genius' ); ?></option>
								<option value="twice_daily"><?php esc_html_e( 'Twice Daily', 'wp-genius' ); ?></option>
								<option value="daily" selected><?php esc_html_e( 'Daily', 'wp-genius' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly', 'wp-genius' ); ?></option>
							</select>
						</div>

						<div class="w2p-ai-form-row">
							<label for="ai-schedule-time"><?php esc_html_e( 'Time', 'wp-genius' ); ?></label>
							<input type="time" id="ai-schedule-time" value="09:00">
						</div>

						<div class="w2p-ai-form-row">
							<label for="ai-schedule-quantity"><?php esc_html_e( 'Posts per Run', 'wp-genius' ); ?></label>
							<input type="number" id="ai-schedule-quantity" class="small-text" value="1" min="1" max="10">
						</div>

						<div class="w2p-ai-form-row">
							<label for="ai-schedule-status"><?php esc_html_e( 'Post Status', 'wp-genius' ); ?></label>
							<select id="ai-schedule-status" class="w2p-ai-select">
								<option value="draft"><?php esc_html_e( 'Draft', 'wp-genius' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending Review', 'wp-genius' ); ?></option>
								<option value="publish"><?php esc_html_e( 'Published', 'wp-genius' ); ?></option>
							</select>
						</div>

						<div class="w2p-ai-form-row">
							<label>
								<input type="checkbox" id="ai-schedule-featured">
								<?php esc_html_e( 'Set Featured Image', 'wp-genius' ); ?>
							</label>
						</div>
					</div>
				</div>

				<div id="ai-schedule-variables-container" class="w2p-ai-form-row" style="display: none;">
					<label><?php esc_html_e( 'Template Variables', 'wp-genius' ); ?></label>
					<div id="ai-schedule-variables"></div>
				</div>
			</div>
			<div class="w2p-ai-modal-footer">
				<button type="button" class="button w2p-ai-modal-close"><?php esc_html_e( 'Cancel', 'wp-genius' ); ?></button>
				<button type="button" id="ai-save-schedule-btn" class="button button-primary">
					<?php esc_html_e( 'Save Schedule', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
