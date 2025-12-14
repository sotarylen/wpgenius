<?php
/**
 * Clean Up WordPress Module Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 不使用 AJAX，直接用标准表单提交

$settings = get_option( 'w2p_cleanup_settings', [] );
$defaults = [
	'remove_admin_bar_wp_logo'         => true,
	'remove_admin_bar_about'           => true,
	'remove_admin_bar_comments'        => true,
	'remove_admin_bar_new_content'     => true,
	'remove_admin_bar_search'          => true,
	'remove_admin_bar_updates'         => true,
	'remove_admin_bar_appearance'      => true,
	'remove_admin_bar_wporg'           => true,
	'remove_admin_bar_documentation'   => true,
	'remove_admin_bar_support_forums'  => true,
	'remove_admin_bar_feedback'        => true,
	'remove_admin_bar_view_site'       => true,
	'remove_dashboard_activity'        => true,
	'remove_dashboard_primary'         => false,
	'remove_dashboard_secondary'       => false,
	'remove_dashboard_site_health'     => false,
	'remove_dashboard_right_now'       => false,
];
$settings = wp_parse_args( $settings, $defaults );
?>

<div class="w2p-settings-panel w2p-cleanup-settings">
	<h3><?php esc_html_e( 'Clean Up WordPress', 'wp-genius' ); ?></h3>
	
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="w2p-cleanup-form-wrapper">
		<?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
		<input type="hidden" name="action" value="word2posts_save_module_settings" />
		<input type="hidden" name="module_id" value="cleanup-wordpress" />

		<div class="w2p-cleanup-section">
			<h4><?php esc_html_e( 'Admin Bar Items to Remove', 'wp-genius' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Select which items to remove from the WordPress admin bar', 'wp-genius' ); ?></p>
			
			<div class="w2p-cleanup-options">
				<!-- WP Logo -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_wp_logo]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_wp_logo'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'WordPress Logo', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes the WordPress logo', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- About -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_about]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_about'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'About WordPress', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes the About WordPress link', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Comments -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_comments]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_comments'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Comments', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes comments menu from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- New Content -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_new_content]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_new_content'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'New Content', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes "New" content menu from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Search -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_search]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_search'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Search', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes search from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Updates -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_updates]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_updates'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Updates', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes updates notification from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Appearance -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_appearance]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_appearance'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Appearance', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes Appearance menu from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- WordPress.org -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_wporg]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_wporg'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'WordPress.org', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes WordPress.org links from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Documentation -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_documentation]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_documentation'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Documentation', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes documentation link from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Support Forums -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_support_forums]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_support_forums'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Support Forums', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes support forums link from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Feedback -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_feedback]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_feedback'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Feedback', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes feedback link from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- View Site -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_admin_bar_view_site]" 
						value="1"
						<?php checked( $settings['remove_admin_bar_view_site'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'View Site', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes View Site link from admin bar', 'wp-genius' ); ?></span>
					</span>
				</label>
			</div>
		</div>

		<div class="w2p-cleanup-section">
			<h4><?php esc_html_e( 'Dashboard Widgets to Remove', 'wp-genius' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Select which dashboard widgets to remove', 'wp-genius' ); ?></p>
			
			<div class="w2p-cleanup-options">
				<!-- Activity -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_dashboard_activity]" 
						value="1"
						<?php checked( $settings['remove_dashboard_activity'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Activity', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes Activity widget from dashboard', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Primary (Right Now) -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_dashboard_primary]" 
						value="1"
						<?php checked( $settings['remove_dashboard_primary'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Primary Sidebar', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes Primary Sidebar widget from dashboard', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Secondary -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_dashboard_secondary]" 
						value="1"
						<?php checked( $settings['remove_dashboard_secondary'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Secondary Sidebar', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes Secondary Sidebar widget from dashboard', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Site Health -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_dashboard_site_health]" 
						value="1"
						<?php checked( $settings['remove_dashboard_site_health'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Site Health', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes Site Health widget from dashboard', 'wp-genius' ); ?></span>
					</span>
				</label>

				<!-- Right Now -->
				<label class="w2p-cleanup-item">
					<input 
						type="checkbox" 
						name="w2p_cleanup_settings[remove_dashboard_right_now]" 
						value="1"
						<?php checked( $settings['remove_dashboard_right_now'], 1 ); ?>
					/>
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Right Now', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Removes Right Now widget from dashboard', 'wp-genius' ); ?></span>
					</span>
				</label>
			</div>
		</div>

		<div class="w2p-cleanup-actions">
			<?php submit_button( __( 'Save Cleanup Settings', 'wp-genius' ), 'primary', 'submit', false, [ 'id' => 'w2p-save-cleanup' ] ); ?>
		</div>
	</form>
</div>

<style>
.w2p-cleanup-settings {
	background: #fff;
	padding: 20px;
	border: 1px solid #ddd;
	border-radius: 3px;
}

.w2p-cleanup-section {
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid #eee;
}

.w2p-cleanup-section:last-child {
	border-bottom: none;
	margin-bottom: 0;
	padding-bottom: 0;
}

.w2p-cleanup-section h4 {
	margin: 0 0 10px 0;
	font-size: 14px;
	font-weight: 600;
	color: #1d2327;
}

.w2p-cleanup-section > .description {
	margin: 0 0 15px 0;
	font-size: 13px;
	color: #50575e;
}

.w2p-cleanup-options {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 15px;
}

.w2p-cleanup-item {
	display: flex;
	align-items: flex-start;
	padding: 12px;
	background: #f9f9f9;
	border: 1px solid #e5e5e5;
	border-radius: 3px;
	cursor: pointer;
	transition: all 0.2s ease;
}

.w2p-cleanup-item:hover {
	background: #f0f0f1;
	border-color: #0073aa;
}

.w2p-cleanup-item input[type="checkbox"] {
	margin-top: 2px;
	margin-right: 12px;
	cursor: pointer;
	flex-shrink: 0;
}

.w2p-cleanup-label {
	display: flex;
	flex-direction: column;
	gap: 4px;
	flex: 1;
}

.w2p-cleanup-label strong {
	font-size: 13px;
	color: #1d2327;
	line-height: 1.4;
}

.w2p-cleanup-label .description {
	font-size: 12px;
	color: #72777c;
	margin: 0;
}

.w2p-cleanup-actions {
	display: flex;
	gap: 10px;
	align-items: center;
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid #eee;
}

.w2p-action-btn {
	min-height: 44px !important;
	padding: 8px 16px !important;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}



@media (max-width: 768px) {
	.w2p-cleanup-options {
		grid-template-columns: 1fr;
	}
}
</style>
