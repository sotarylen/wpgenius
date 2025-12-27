<?php
/**
 * Accelerate Module Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'w2p_accelerate_settings', [] );
$defaults = [
	// Cleanup Defaults
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
	'disable_months_dropdown'          => false,
    
    // Update Behavior Defaults
    'disable_auto_update_plugin' => true,
    'disable_auto_update_theme'  => true,
    'remove_wp_update_plugins'   => true,
    'remove_wp_update_themes'    => true,
    'remove_maybe_update_core'   => true,
    'remove_maybe_update_plugins'=> true,
    'remove_maybe_update_themes' => true,
    'block_external_http'      => false,
    'hide_plugin_notices'      => false,
    'block_acf_updates'        => false,
];
$settings = wp_parse_args( $settings, $defaults );
?>

<div class="w2p-settings-panel w2p-accelerate-settings">
	<h3><?php esc_html_e( 'Accelerate Settings', 'wp-genius' ); ?></h3>
	
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="w2p-accelerate-form-wrapper">
		<?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
		<input type="hidden" name="action" value="word2posts_save_module_settings" />
		<input type="hidden" name="module_id" value="accelerate" />

		<!-- Cleanup Section -->
		<div class="w2p-cleanup-section">
			<h4><?php esc_html_e( 'Admin Bar Items to Remove', 'wp-genius' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Select which items to remove from the WordPress admin bar', 'wp-genius' ); ?></p>
			
			<div class="w2p-cleanup-options">
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_wp_logo]" value="1" <?php checked( $settings['remove_admin_bar_wp_logo'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'WordPress Logo', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_about]" value="1" <?php checked( $settings['remove_admin_bar_about'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'About WordPress', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_comments]" value="1" <?php checked( $settings['remove_admin_bar_comments'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Comments', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_new_content]" value="1" <?php checked( $settings['remove_admin_bar_new_content'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'New Content', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_search]" value="1" <?php checked( $settings['remove_admin_bar_search'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Search', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_updates]" value="1" <?php checked( $settings['remove_admin_bar_updates'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Updates', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_appearance]" value="1" <?php checked( $settings['remove_admin_bar_appearance'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Appearance', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_wporg]" value="1" <?php checked( $settings['remove_admin_bar_wporg'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'WordPress.org', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_documentation]" value="1" <?php checked( $settings['remove_admin_bar_documentation'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Documentation', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_support_forums]" value="1" <?php checked( $settings['remove_admin_bar_support_forums'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Support Forums', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_feedback]" value="1" <?php checked( $settings['remove_admin_bar_feedback'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Feedback', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_admin_bar_view_site]" value="1" <?php checked( $settings['remove_admin_bar_view_site'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'View Site', 'wp-genius' ); ?></strong></span>
				</label>
			</div>
		</div>

		<div class="w2p-cleanup-section">
			<h4><?php esc_html_e( 'Dashboard Widgets to Remove', 'wp-genius' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Select which dashboard widgets to remove', 'wp-genius' ); ?></p>
			
			<div class="w2p-cleanup-options">
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_dashboard_activity]" value="1" <?php checked( $settings['remove_dashboard_activity'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Activity', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_dashboard_primary]" value="1" <?php checked( $settings['remove_dashboard_primary'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Primary Sidebar', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_dashboard_secondary]" value="1" <?php checked( $settings['remove_dashboard_secondary'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Secondary Sidebar', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_dashboard_site_health]" value="1" <?php checked( $settings['remove_dashboard_site_health'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Site Health', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_dashboard_right_now]" value="1" <?php checked( $settings['remove_dashboard_right_now'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Right Now', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[remove_dashboard_quick_draft]" value="1" <?php checked( $settings['remove_dashboard_quick_draft'], 1 ); ?> />
					<span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Quick Draft', 'wp-genius' ); ?></strong></span>
				</label>
				<label class="w2p-cleanup-item">
					<input type="checkbox" name="w2p_accelerate_settings[disable_months_dropdown]" value="1" <?php checked( $settings['disable_months_dropdown'], 1 ); ?> />
					<span class="w2p-cleanup-label">
						<strong><?php esc_html_e( 'Disable Months Dropdown', 'wp-genius' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Block slow "All dates" queries in post list and media library.', 'wp-genius' ); ?></span>
					</span>
				</label>
			</div>
		</div>

        <!-- Update Behavior Section -->
        <div class="w2p-cleanup-section">
            <h4><?php esc_html_e( 'Update Behaviors', 'wp-genius' ); ?></h4>
            <p class="description"><?php esc_html_e( 'Control automatic updates and update checks', 'wp-genius' ); ?></p>

            <div class="w2p-cleanup-options">
                <label class="w2p-cleanup-item">
                    <input type="checkbox" name="w2p_accelerate_settings[disable_auto_update_plugin]" value="1" <?php checked( $settings['disable_auto_update_plugin'], 1 ); ?> />
                    <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Disable automatic plugin updates', 'wp-genius' ); ?></strong></span>
                </label>
                <label class="w2p-cleanup-item">
                    <input type="checkbox" name="w2p_accelerate_settings[disable_auto_update_theme]" value="1" <?php checked( $settings['disable_auto_update_theme'], 1 ); ?> />
                    <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Disable automatic theme updates', 'wp-genius' ); ?></strong></span>
                </label>
                <label class="w2p-cleanup-item">
                    <input type="checkbox" name="w2p_accelerate_settings[remove_wp_update_plugins]" value="1" <?php checked( $settings['remove_wp_update_plugins'], 1 ); ?> />
                    <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove wp_update_plugins action', 'wp-genius' ); ?></strong></span>
                </label>
                <label class="w2p-cleanup-item">
                    <input type="checkbox" name="w2p_accelerate_settings[remove_wp_update_themes]" value="1" <?php checked( $settings['remove_wp_update_themes'], 1 ); ?> />
                    <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove wp_update_themes action', 'wp-genius' ); ?></strong></span>
                </label>
                <label class="w2p-cleanup-item">
                    <input type="checkbox" name="w2p_accelerate_settings[remove_maybe_update_core]" value="1" <?php checked( $settings['remove_maybe_update_core'], 1 ); ?> />
                    <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove _maybe_update_core on admin_init', 'wp-genius' ); ?></strong></span>
                </label>
                <label class="w2p-cleanup-item">
                    <input type="checkbox" name="w2p_accelerate_settings[remove_maybe_update_plugins]" value="1" <?php checked( $settings['remove_maybe_update_plugins'], 1 ); ?> />
                    <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove _maybe_update_plugins on admin_init', 'wp-genius' ); ?></strong></span>
                </label>
                <label class="w2p-cleanup-item">
                    <input type="checkbox" name="w2p_accelerate_settings[remove_maybe_update_themes]" value="1" <?php checked( $settings['remove_maybe_update_themes'], 1 ); ?> />
                    <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Remove _maybe_update_themes on admin_init', 'wp-genius' ); ?></strong></span>
                </label>
                <label class="w2p-cleanup-item">
                    <input type="checkbox" name="w2p_accelerate_settings[hide_plugin_notices]" value="1" <?php checked( $settings['hide_plugin_notices'], 1 ); ?> />
                    <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Hide plugin notices', 'wp-genius' ); ?></strong></span>
                </label>
                <label class="w2p-cleanup-item">
                    <input type="checkbox" name="w2p_accelerate_settings[block_acf_updates]" value="1" <?php checked( $settings['block_acf_updates'], 1 ); ?> />
                    <span class="w2p-cleanup-label"><strong><?php esc_html_e( 'Block ACF update requests', 'wp-genius' ); ?></strong></span>
                </label>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="w2p-danger-setting" style="margin-top: 20px;">
            <label class="w2p-cleanup-item" style="border-color: #d63638; background-color: #fcf0f1;">
                <input type="checkbox" name="w2p_accelerate_settings[block_external_http]" value="1" <?php checked( $settings['block_external_http'], 1 ); ?> class="w2p-danger-toggle" />
                <span class="w2p-cleanup-label">
                    <strong class="w2p-danger-title" style="color: #d63638;"><?php esc_html_e( 'Block all external HTTP requests (DANGER!)', 'wp-genius' ); ?></strong>
                    <span class="w2p-danger-desc"><?php esc_html_e( 'This will block all plugins and themes from checking for updates, dramatically speeding up the backend load times. Use only for local development!', 'wp-genius' ); ?></span>
                </span>
            </label>
        </div>

		<div class="w2p-cleanup-actions">
			<?php submit_button( __( 'Save Accelerate Settings', 'wp-genius' ), 'primary', 'submit', false, [ 'id' => 'w2p-save-accelerate' ] ); ?>
		</div>
	</form>
</div>

<style>
.w2p-accelerate-settings {
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

@media (max-width: 768px) {
	.w2p-cleanup-options {
		grid-template-columns: 1fr;
	}
}
</style>
