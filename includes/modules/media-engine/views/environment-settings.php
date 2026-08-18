<?php
/**
 * Environment Check Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the environment detection class
if ( ! class_exists( 'MediaEngineEnvironmentChecker' ) ) {
	require_once __DIR__ . '/../includes/services/class-environment-service.php';
}
$env_results = MediaEngineEnvironmentChecker::check_all();
?>

<div class="w2p-settings-panel w2p-environment-settings">
	<div class="w2p-section">
		<div class="w2p-section-header">
			<div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
				<div>
					<h4><i class="fa-solid fa-stethoscope"></i> <?php esc_html_e( 'Environment Check', 'wp-genius' ); ?></h4>
					<p class="description" style="margin: 5px 0 0;"><?php esc_html_e( 'Verify that all required dependencies are available for media processing.', 'wp-genius' ); ?></p>
				</div>
				<button type="button" id="w2p-recheck-environment" class="w2p-btn w2p-btn-primary">
					<i class="fa-solid fa-rotate"></i>
					<?php esc_html_e( 'Recheck Environment', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
		<div class="w2p-section-body">
			<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_status_html escapes internally (esc_html_e); it is a safe rendering boundary.
			echo MediaEngineEnvironmentChecker::render_status_html( $env_results );
			?>
		</div>
	</div>
</div>
