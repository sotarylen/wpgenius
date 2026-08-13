<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="w2p-tab-maintenance" class="w2p-wrapper">
	<div class="w2p-section">
		<div class="w2p-section-header">
			<h4><?php _e( 'Directory Maintenance', 'wp-genius' ); ?></h4>
		</div>
		<div class="w2p-section-body">
			<p class="w2p-tab-content-description" style="margin-bottom: var(--w2p-spacing-lg);">
				<?php _e( 'Manage temporary files and directories used during the import process.', 'wp-genius' ); ?>
			</p>
			
			<div class="w2p-grid w2p-grid-cols-2 w2p-gap-lg">
				<div class="w2p-toggle-card">
					<div class="w2p-toggle-header">
						<span class="w2p-toggle-title"><?php _e( 'Scan Directory', 'wp-genius' ); ?></span>
					</div>
					<p class="w2p-toggle-desc"><?php _e( 'Search the uploads directory for Word documents and synchronize the database records.', 'wp-genius' ); ?></p>
					<div class="w2p-form-actions" style="margin-top: var(--w2p-spacing-md); padding: 0; border: none;">
						<form id="word_to_posts_scan_form" method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
							<input type="hidden" name="action" value="scan_uploads">
							<?php wp_nonce_field( 'word_to_posts_scan', 'word_to_posts_scan_nonce' ); ?>
							<button type="submit" class="button button-secondary w2p-btn-full"><?php _e( 'Scan Upload Directory', 'wp-genius' ); ?></button>
						</form>
					</div>
				</div>

				<div class="w2p-toggle-card">
					<div class="w2p-toggle-header">
						<span class="w2p-toggle-title"><?php _e( 'Clean Directory', 'wp-genius' ); ?></span>
					</div>
					<p class="w2p-toggle-desc"><?php _e( 'Safely remove temporary uploaded files that are no longer associated with any posts or records.', 'wp-genius' ); ?></p>
					<div class="w2p-form-actions" style="margin-top: var(--w2p-spacing-md); padding: 0; border: none;">
						<form id="word_to_posts_clean_form" method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
							<input type="hidden" name="action" value="clean_uploads">
							<?php wp_nonce_field( 'word_to_posts_clean', 'word_to_posts_clean_nonce' ); ?>
							<button type="submit" class="button button-secondary w2p-btn-full" style="color: var(--w2p-color-danger); border-color: var(--w2p-color-danger);" onclick="return confirm('<?php _e( 'Are you sure you want to clean the uploads directory? All temporary files will be deleted.', 'wp-genius' ); ?>');">
								<?php _e( 'Clean Temporary Files', 'wp-genius' ); ?>
							</button>
						</form>
					</div>
				</div>
			</div>

			<div id="word-to-posts-log-clean" class="word2postNotice w2p-info-box" style="margin-top: 15px; display: none;"></div>
		</div>
	</div>
</div>
