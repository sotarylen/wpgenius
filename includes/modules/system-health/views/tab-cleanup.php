<?php
/**
 * System Health — cleanup Tab (CSF tabbed fragment)
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

		<div class="w2p-section">
			<div class="w2p-section-body">
				<div class="w2p-info-box w2p-flex w2p-justify-between w2p-items-center">
					<p class="w2p-m-0"><?php esc_html_e( 'Keep your site fast by removing unnecessary data from your database. Click the scan button to check current system health.', 'wp-genius' ); ?></p>
					<button type="button" id="w2p-health-scan-btn" class="w2p-btn w2p-btn-primary">
						<span class="fa-solid fa-magnifying-glass"></span>
						<?php esc_html_e( 'Scan System Status', 'wp-genius' ); ?>
					</button>
				</div>

				<div class="w2p-health-grid">
					<?php if ( isset( $stats ) && is_array( $stats ) ) : ?>
						<!-- Post Revisions -->
						<div class="w2p-health-card" data-type="revisions">
							<div class="w2p-health-info">
								<span class="w2p-health-label"><?php esc_html_e( 'Post Revisions', 'wp-genius' ); ?></span>
								<span class="w2p-health-count"><?php echo esc_html( $stats['revisions'] ?? '-' ); ?></span>
							</div>
							<button class="w2p-btn w2p-btn-secondary w2p-health-action" data-action="revisions">
								<span class="fa-solid fa-trash"></span>
								<?php esc_html_e( 'Clean Revisions', 'wp-genius' ); ?>
							</button>
						</div>

						<!-- Auto Drafts -->
						<div class="w2p-health-card" data-type="auto_drafts">
							<div class="w2p-health-info">
								<span class="w2p-health-label"><?php esc_html_e( 'Auto Drafts', 'wp-genius' ); ?></span>
								<span class="w2p-health-count"><?php echo esc_html( $stats['auto_drafts'] ?? '-' ); ?></span>
							</div>
							<button class="w2p-btn w2p-btn-secondary w2p-health-action" data-action="auto_drafts">
								<span class="fa-solid fa-trash"></span>
								<?php esc_html_e( 'Clean Auto Drafts', 'wp-genius' ); ?>
							</button>
						</div>

						<!-- Orphaned Meta -->
						<div class="w2p-health-card" data-type="orphaned_meta">
							<div class="w2p-health-info">
								<span class="w2p-health-label"><?php esc_html_e( 'Orphaned Metadata', 'wp-genius' ); ?></span>
								<span class="w2p-health-count"><?php echo esc_html( $stats['orphaned_meta'] ?? '-' ); ?></span>
							</div>
							<button class="w2p-btn w2p-btn-secondary w2p-health-action" data-action="orphaned_meta">
								<span class="fa-solid fa-trash"></span>
								<?php esc_html_e( 'Clean Orphaned Meta', 'wp-genius' ); ?>
							</button>
						</div>

						<!-- Transients -->
						<div class="w2p-health-card" data-type="transients">
							<div class="w2p-health-info">
								<span class="w2p-health-label"><?php esc_html_e( 'Expired Transients', 'wp-genius' ); ?></span>
								<span class="w2p-health-count"><?php echo esc_html( $stats['transients'] ?? '-' ); ?></span>
							</div>
							<button class="w2p-btn w2p-btn-secondary w2p-health-action" data-action="transients">
								<span class="fa-solid fa-trash"></span>
								<?php esc_html_e( 'Clean Transients', 'wp-genius' ); ?>
							</button>
						</div>
					<?php endif; ?>

					<!-- Custom Field Cleaner -->
					<div class="w2p-health-card w2p-health-card-full" data-type="custom_field">
						<div class="w2p-health-info">
							<span class="w2p-health-label"><?php esc_html_e( 'Clean Custom Field Data', 'wp-genius' ); ?></span>
							<div class="w2p-health-desc"><?php esc_html_e( 'Delete all postmeta entries for a specific custom field key (e.g. ACF).', 'wp-genius' ); ?></div>
						</div>
						<div class="w2p-health-input-group">
							<input type="text" id="w2p-custom-meta-key" class="w2p-input" placeholder="<?php esc_attr_e( 'Enter custom field name...', 'wp-genius' ); ?>">
							<button class="w2p-btn w2p-btn-danger w2p-health-action" data-action="custom_field">
								<span class="fa-solid fa-eraser"></span>
								<?php esc_html_e( 'Clean Data', 'wp-genius' ); ?>
							</button>
						</div>
					</div>

				</div>

				<div id="w2p-health-message" class="w2p-notice w2p-hidden"></div>
			</div>
		</div>
	</div>

	<!-- Image Link Remover Tab -->
