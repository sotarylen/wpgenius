<?php
/**
 * System Health — info Tab (CSF tabbed fragment)
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

		<div id="w2p-health-info-container" class="w2p-loading-container">
			<div class="w2p-skeleton-loader">
				<div class="w2p-skeleton-row"></div>
				<div class="w2p-skeleton-row"></div>
				<div class="w2p-skeleton-row"></div>
			</div>
			<p class="w2p-text-center w2p-mt-md"><?php esc_html_e( 'Loading system information...', 'wp-genius' ); ?></p>
		</div>
	</div>
</div>
