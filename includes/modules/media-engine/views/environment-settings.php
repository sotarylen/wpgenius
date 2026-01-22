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

// 加载环境检测类
if ( ! class_exists( 'MediaEngineEnvironmentChecker' ) ) {
	require_once dirname( __FILE__ ) . '/../includes/class-environment-checker.php';
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
				<button type="button" id="w2p-recheck-environment" class="w2p-btn w2p-btn-secondary">
					<i class="fa-solid fa-rotate"></i>
					<?php esc_html_e( 'Recheck Environment', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
		<div class="w2p-section-body">
			<?php echo MediaEngineEnvironmentChecker::render_status_html( $env_results ); ?>
		</div>
	</div>
</div>
