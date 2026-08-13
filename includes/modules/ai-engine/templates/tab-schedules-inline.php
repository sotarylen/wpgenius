<?php
/**
 * AI Engine - Schedules Tab (Inline for CSF)
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables from options.php: $ai_providers, $ai_prompts.
$ai_providers = $ai_providers ?? array();
$ai_prompts   = $ai_prompts ?? array();

$nonce = wp_create_nonce( 'w2p_ai_engine_nonce' );

// Get schedules from options.
global $wpdb;
$ai_schedules        = array();
$ai_schedule_results = $wpdb->get_results(
	"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'w2p_ai_schedule_%' AND option_name NOT LIKE '%last_run%'",
	ARRAY_A
);
foreach ( $ai_schedule_results as $row ) {
	$schedule = json_decode( $row['option_value'], true );
	if ( $schedule ) {
		$ai_schedules[] = $schedule;
	}
}
?>
<div class="w2p-ai-engine-wrap">
	<h3><?php esc_html_e( 'Content Schedules', 'wp-genius' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Configure automatic content generation schedules.', 'wp-genius' ); ?></p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Provider', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Schedule', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-genius' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $ai_schedules ) ) : ?>
				<?php foreach ( $ai_schedules as $schedule ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $schedule['name'] ?? '' ); ?></strong></td>
						<td><?php echo esc_html( ucfirst( $schedule['provider'] ?? '' ) ); ?></td>
						<td>
							<?php
							$freq   = $schedule['frequency'] ?? 'daily';
							$labels = array(
								'hourly'      => 'Hourly',
								'twice_daily' => 'Twice Daily',
								'daily'       => 'Daily',
								'weekly'      => 'Weekly',
							);
							$time   = $schedule['time'] ?? '09:00';
							echo esc_html( ( $labels[ $freq ] ?? $freq ) . ' at ' . $time );
							?>
						</td>
						<td>
							<?php if ( ! empty( $schedule['enabled'] ) ) : ?>
								<span style="color: #00a32a;">Active</span>
							<?php else : ?>
								<span style="color: #d63638;">Inactive</span>
							<?php endif; ?>
						</td>
						<td>
							<a href="#" class="ai-toggle-schedule" data-id="<?php echo esc_attr( $schedule['id'] ?? '' ); ?>">
								<?php echo ! empty( $schedule['enabled'] ) ? 'Disable' : 'Enable'; ?>
							</a> |
							<a href="#" class="ai-delete-schedule" data-id="<?php echo esc_attr( $schedule['id'] ?? '' ); ?>" style="color: #d63638;">Delete</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No schedules configured.', 'wp-genius' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<p>
		<button type="button" id="ai-add-schedule-btn" class="button button-primary"><?php esc_html_e( 'Add New Schedule', 'wp-genius' ); ?></button>
	</p>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var nonce = '<?php echo $nonce; ?>';
	var prompts = <?php echo wp_json_encode( $ai_prompts ); ?>;

	$('#ai-add-schedule-btn').on('click', function() {
		var opts = '<option value="">Select Prompt</option>';
		$.each(prompts, function(i, p) {
			opts += '<option value="' + p.id + '">' + p.name + '</option>';
		});

		var html = '<div id="ai-schedule-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;">';
		html += '<div style="background:#fff;padding:20px;border-radius:4px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto;">';
		html += '<h3>Add New Schedule</h3>';
		html += '<p><label>Name</label><br><input type="text" id="sched-name" class="regular-text" placeholder="e.g., Daily Blog Posts"></p>';
		html += '<p><label>Prompt</label><br><select id="sched-prompt" class="regular-text">' + opts + '</select></p>';
		html += '<p><label>Frequency</label><br><select id="sched-frequency" class="regular-text"><option value="hourly">Hourly</option><option value="twice_daily">Twice Daily</option><option value="daily" selected>Daily</option><option value="weekly">Weekly</option></select></p>';
		html += '<p><label>Time</label><br><input type="time" id="sched-time" value="09:00"></p>';
		html += '<p><label>Posts per Run</label><br><input type="number" id="sched-quantity" class="small-text" value="1" min="1" max="10"></p>';
		html += '<p><label>Post Status</label><br><select id="sched-status" class="regular-text"><option value="draft">Draft</option><option value="pending">Pending Review</option><option value="publish">Published</option></select></p>';
		html += '<p><button type="button" class="button button-primary" id="modal-save-schedule">Save</button> <button type="button" class="button" id="modal-close-schedule">Cancel</button></p>';
		html += '</div></div>';
		$('body').append(html);

		$('#modal-close-schedule').on('click', function() { $('#ai-schedule-modal').remove(); });
		$('#modal-save-schedule').on('click', function() {
			$.post(ajaxurl, {
				action: 'w2p_ai_save_schedule',
				nonce: nonce,
				name: $('#sched-name').val(),
				provider: 'openai',
				model: 'gpt-4o-mini',
				prompt_id: $('#sched-prompt').val(),
				frequency: $('#sched-frequency').val(),
				time: $('#sched-time').val(),
				quantity: $('#sched-quantity').val(),
				status: $('#sched-status').val()
			}, function(res) {
				if (res.success) location.reload();
				else alert(res.data.message || 'Error');
			});
		});
	});

	$('.ai-toggle-schedule').on('click', function(e) {
		e.preventDefault();
		$.post(ajaxurl, {
			action: 'w2p_ai_toggle_schedule',
			nonce: nonce,
			schedule_id: $(this).data('id')
		}, function(res) { if (res.success) location.reload(); });
	});

	$('.ai-delete-schedule').on('click', function(e) {
		e.preventDefault();
		if (!confirm('Are you sure you want to delete this schedule?')) return;
		$.post(ajaxurl, {
			action: 'w2p_ai_delete_schedule',
			nonce: nonce,
			schedule_id: $(this).data('id')
		}, function(res) { if (res.success) location.reload(); });
	});
});
</script>
