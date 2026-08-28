<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; } // Direct access guard. ?>
<div id="w2p-tab-actor-scanner" class="w2p-wrapper">
	<input type="hidden" id="actor_scanner_nonce" value="<?php echo esc_attr( wp_create_nonce( 'w2p_actor_scanner_nonce' ) ); ?>">

	<!-- Data Source Status -->
	<div class="w2p-section">
		<div class="w2p-section-header">
			<h4><?php esc_html_e( 'Gfriends Data Source', 'wp-genius' ); ?></h4>
			<div class="w2p-section-actions">
				<button type="button" id="actor-prepare-btn" class="w2p-btn w2p-btn-primary">
					<i class="fa fa-cloud-download"></i> <?php esc_html_e( 'Prepare Index', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
		<div class="w2p-section-body">
			<p class="w2p-hint">
				<?php esc_html_e( 'Downloads and caches the Gfriends Filetree index (actress names + avatar files) from the remote repository. Run this once before scanning.', 'wp-genius' ); ?>
			</p>
			<div id="actor-gf-status" class="w2p-status-box">
				<span class="w2p-status-label"><?php esc_html_e( 'Index not prepared yet.', 'wp-genius' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Scan Tool -->
	<div class="w2p-section">
		<div class="w2p-section-header">
			<h4><?php esc_html_e( 'Scan &amp; Assign', 'wp-genius' ); ?></h4>
			<div class="w2p-section-actions">
				<button type="button" id="actor-scan-btn" class="w2p-btn w2p-btn-success">
					<i class="fa fa-search"></i> <?php esc_html_e( 'Start Scan', 'wp-genius' ); ?>
				</button>
				<button type="button" id="actor-stop-btn" class="w2p-btn w2p-btn-stop" style="display:none;">
					<i class="fa fa-stop"></i> <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
				</button>
				<button type="button" id="actor-reset-btn" class="w2p-btn w2p-btn-secondary" style="display:none;">
					<i class="fa fa-undo"></i> <?php esc_html_e( 'Reset', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
		<div class="w2p-section-body">
			<div class="w2p-progress-container">
				<div class="w2p-progress-info">
					<span id="actor-progress-text">0 / 0</span>
					<span class="w2p-status-label" id="actor-progress-label"><?php esc_html_e( 'Ready', 'wp-genius' ); ?></span>
				</div>
				<div class="w2p-progress-bar-bg">
					<div id="actor-progress-bar" class="w2p-progress-bar-fill" style="width: 0%;"></div>
				</div>
				<div id="actor-stats-row" style="margin-top: 10px; font-size: 12px; color: #666; display:none;">
					<span id="actor-stats-text"></span>
				</div>
			</div>

			<!-- Log Table -->
			<div class="w2p-log-section" style="margin-top: 20px;">
				<h5><?php esc_html_e( 'Process Log', 'wp-genius' ); ?></h5>
				<div class="w2p-log-container">
					<table class="w2p-log-table widefat striped">
						<thead>
							<tr>
								<th width="10%"><?php esc_html_e( 'ID', 'wp-genius' ); ?></th>
								<th width="45%"><?php esc_html_e( 'Title', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Matched Actresses', 'wp-genius' ); ?></th>
								<th width="12%"><?php esc_html_e( 'Terms', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="actor-logs-tbody">
							<tr>
								<td colspan="4" style="text-align:center;color:#999;"><?php esc_html_e( 'No activity logged yet.', 'wp-genius' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- Matching Notes -->
	<div class="w2p-section">
		<div class="w2p-section-header">
			<h4><?php esc_html_e( 'How Matching Works', 'wp-genius' ); ?></h4>
		</div>
		<div class="w2p-section-body">
			<ul style="list-style: disc; padding-left: 18px; line-height: 1.8;">
				<li><?php esc_html_e( 'Japanese names containing kana (e.g. 伊奈美いずな) are extracted verbatim from the post and matched exactly against the Gfriends index and existing Humans terms.', 'wp-genius' ); ?></li>
				<li><?php esc_html_e( 'Chinese names (simplified / traditional) are matched against existing Humans term names and their nickname aliases first.', 'wp-genius' ); ?></li>
				<li><?php esc_html_e( 'When no exact term exists, the best fuzzy Gfriends candidate is used (highest similarity score) and a new Humans term is created.', 'wp-genius' ); ?></li>
				<li><?php esc_html_e( 'Existing terms are enriched: role is set to 演员, missing aliases are appended, and the Gfriends avatar is downloaded when the term has none.', 'wp-genius' ); ?></li>
			</ul>
		</div>
	</div>
</div>
