<?php
/**
 * Actor Deduplication & Governance Tool View
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
<div id="w2p-tab-actor-dedupe" class="w2p-wrapper">
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
			<div id="actor-gf-status" class="w2p-status-box" style="margin-top: 10px; font-weight: 500;">
				<span class="w2p-status-label"><i class="fa-solid fa-circle-check" style="color:var(--w2p-color-success);"></i> <?php
				/* translators: %d: number of indexed actors */
				echo esc_html( sprintf( __( 'Currently indexed official actors: %d', 'wp-genius' ), $cached_count ) );
				?></span>
			</div>
		</div>
	</div>

	<!-- Duplicate Actor Governance Card -->
	<div class="w2p-section">
		<div class="w2p-section-header">
			<h4><i class="fa-solid fa-code-merge"></i> <?php esc_html_e( 'Duplicate Actor Scan & Merge', 'wp-genius' ); ?></h4>
			<div class="w2p-section-actions">
				<button type="button" id="actor-scan-dupes-btn" class="w2p-btn w2p-btn-primary">
					<i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e( 'Scan Duplicate Actors', 'wp-genius' ); ?>
				</button>
				<button type="button" id="actor-merge-dupes-btn" class="w2p-btn w2p-btn-stop" style="display:none;">
					<i class="fa-solid fa-code-merge"></i> <?php esc_html_e( 'Merge All Duplicate Actors', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
		<div class="w2p-section-body">
			<p class="w2p-hint">
				<?php esc_html_e( 'Follows a 4-step algorithm: cluster duplicate terms -> pick Japanese canonical term -> remap attached posts -> delete redundant terms.', 'wp-genius' ); ?>
			</p>

			<div id="actor-dedupe-status" class="w2p-status-box" style="margin-top: 10px;">
				<span id="actor-dedupe-status-text" class="w2p-status-label"><?php esc_html_e( 'Click "Scan Duplicate Actors" above to begin scanning.', 'wp-genius' ); ?></span>
			</div>

			<!-- Duplicate Clusters Preview Table -->
			<div id="actor-dupes-table-wrapper" class="w2p-log-section" style="margin-top: 20px; display:none;">
				<h5><?php esc_html_e( 'Detected Duplicate Actor Clusters (Preview)', 'wp-genius' ); ?></h5>
				<div class="w2p-log-container" style="max-height: 400px; overflow-y: auto;">
					<table class="w2p-log-table widefat striped">
						<thead>
							<tr>
								<th width="8%"><?php esc_html_e( 'No.', 'wp-genius' ); ?></th>
								<th width="15%"><?php esc_html_e( 'Type', 'wp-genius' ); ?></th>
								<th width="30%"><?php esc_html_e( 'Keep (Winner)', 'wp-genius' ); ?></th>
								<th width="32%"><?php esc_html_e( 'To Delete', 'wp-genius' ); ?></th>
								<th width="15%"><?php esc_html_e( 'Merged Aliases', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="actor-dupes-tbody">
						</tbody>
					</table>
				</div>
			</div>

			<!-- Execution Log -->
			<div id="actor-merge-log-wrapper" class="w2p-log-section" style="margin-top: 20px; display:none;">
				<h5><?php esc_html_e( 'Merge Execution Report', 'wp-genius' ); ?></h5>
				<div class="w2p-log-container">
					<ul id="actor-merge-log-list" style="margin:0; padding:10px; list-style:disc; padding-left:20px; font-size:12px; line-height:1.8;"></ul>
				</div>
			</div>
		</div>
	</div>
</div>
