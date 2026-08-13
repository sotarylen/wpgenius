<div id="w2p-tab-fix-index" class="w2p-wrapper">
	<input type="hidden" id="fix_index_nonce" value="<?php echo esc_attr( wp_create_nonce( 'fix_chapter_index' ) ); ?>">
	
	<!-- Tools Section: Manual Batch Process -->
	<div class="w2p-section">
		<div class="w2p-section-header">
			<h4><?php esc_html_e( 'Manual Batch Process', 'wp-genius' ); ?></h4>
			<div class="w2p-section-actions">
				<button type="button" id="fix-index-scan-btn" class="w2p-btn w2p-btn-primary">
					<i class="fa fa-search"></i> <?php esc_html_e( 'Scan', 'wp-genius' ); ?>
				</button>
				<button type="button" id="fix-index-execute-btn" class="w2p-btn w2p-btn-danger" style="display:none;">
					<i class="fa fa-bolt"></i> <?php esc_html_e( 'Update', 'wp-genius' ); ?>
				</button>
				<button type="button" id="fix-index-auto-btn" class="w2p-btn w2p-btn-success">
					<i class="fa fa-magic"></i> <?php esc_html_e( 'Auto Update', 'wp-genius' ); ?>
				</button>
				<button type="button" id="fix-index-stop-btn" class="w2p-btn w2p-btn-stop" style="display:none;">
					<i class="fa fa-stop"></i> <?php esc_html_e( 'Stop', 'wp-genius' ); ?>
				</button>
				<button type="button" id="fix-index-reset-btn" class="w2p-btn w2p-btn-secondary" style="display:none;">
					<i class="fa fa-undo"></i> <?php esc_html_e( 'Reset', 'wp-genius' ); ?>
				</button>
			</div>
		</div>
		<div class="w2p-section-body">
			<!-- Progress Info -->
			<div class="w2p-progress-container">
				<div class="w2p-progress-info">
					<span id="fix-progress-text">0 / 0</span>
					<span class="w2p-status-label"><?php esc_html_e( 'Ready to scan', 'wp-genius' ); ?></span>
				</div>
				<div class="w2p-progress-bar-bg">
					<div id="fix-progress-bar" class="w2p-progress-bar-fill" style="width: 0%;"></div>
				</div>
				<!-- Finished Books / Clear -->
				<?php
					$finished_books = get_option( 'w2p_fix_index_finished_books', array() );
					$finished_count = count( $finished_books );
				?>
				<div id="finished-progress-row" style="<?php echo ( $finished_count > 0 ) ? '' : 'display:none;'; ?> margin-top: 10px; font-size: 12px; color: #666;">
					<span id="finished-count-text"><?php /* translators: %d: number of processed novels. */ printf( esc_html__( 'Processed Novels: %d', 'wp-genius' ), absint( $finished_count ) ); ?></span> 
					| <a href="#" id="fix-index-clear-progress"><?php esc_html_e( 'Clear', 'wp-genius' ); ?></a>
				</div>
			</div>

			<!-- Log Table -->
			<div class="w2p-log-section" style="margin-top: 20px;">
				<h5><?php esc_html_e( 'Process Log', 'wp-genius' ); ?></h5>
				<div class="w2p-log-container">
					<table class="w2p-log-table widefat striped">
						<thead>
							<tr>
								<th width="20%"><?php esc_html_e( 'Index', 'wp-genius' ); ?></th>
								<th width="20%"><?php esc_html_e( 'Volume', 'wp-genius' ); ?></th>
								<th><?php esc_html_e( 'Title', 'wp-genius' ); ?></th>
							</tr>
						</thead>
						<tbody id="fix-logs-tbody">
							<tr>
								<td colspan="3" style="text-align:center;color:#999;"><?php esc_html_e( 'No activity logged yet.', 'wp-genius' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
