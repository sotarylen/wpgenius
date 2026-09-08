<?php
/**
 * Actor Scanner Gfriends Index Status View
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/includes/class-gfriends-client.php';

$gf           = new W2P_Gfriends_Client();
$cached_count = $gf->count_actors();
?>
<div id="w2p-tab-actor-scanner" class="w2p-wrapper">
	<input type="hidden" id="actor_scanner_nonce" value="<?php echo esc_attr( wp_create_nonce( 'w2p_actor_scanner_nonce' ) ); ?>">

	<!-- Gfriends Index Status Card -->
	<div class="w2p-section">
		<div class="w2p-section-header">
			<h4><i class="fa-solid fa-cloud-arrow-down"></i> <?php esc_html_e( 'Gfriends Official Data Source', 'wp-genius' ); ?></h4>
			<div class="w2p-section-actions">
				<button type="button" id="actor-refresh-gf-btn" class="w2p-btn w2p-btn-secondary">
					<i class="fa-solid fa-rotate"></i> <?php esc_html_e( 'Force Refresh Local Index', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
		<div class="w2p-section-body">
			<p class="w2p-hint">
				<?php esc_html_e( 'Locally cached in the plugin data directory. New actors are automatically matched with official Japanese names and aliases, with HD avatars downloaded from CDN.', 'wp-genius' ); ?>
			</p>
			<div id="actor-gf-status" class="w2p-status-box w2p-font-medium">
				<span class="w2p-status-label"><i class="fa-solid fa-circle-check w2p-text-success"></i> <?php
				/* translators: %d: number of indexed actors */
				echo esc_html( sprintf( __( 'Currently indexed official actors: %d', 'wp-genius' ), $cached_count ) );
				?></span>
			</div>
		</div>
	</div>
</div>
