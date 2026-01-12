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
	'remove_admin_bar_customize'       => false,
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
	'remove_dashboard_quick_draft'     => true,
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
    
    // General Interface Defaults
    'enable_local_avatar'      => false,
    'enable_upload_rename'     => false,
    'upload_rename_pattern'    => '{timestamp}_{sanitized}',
];
$settings = wp_parse_args( $settings, $defaults );
?>

<div class="w2p-settings-panel w2p-accelerate-settings">	
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="w2p-accelerate-form-wrapper">
		<?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_accelerate_nonce' ); ?>
		<input type="hidden" name="action" value="word2posts_save_module_settings" />
		<input type="hidden" name="module_id" value="accelerate" />

		<!-- Admin Bar Items -->
		<div class="w2p-section">
			<div class="w2p-section-header">
				<h4><?php esc_html_e( 'Admin Bar', 'wp-genius' ); ?></h4>
			</div>
			<div class="w2p-section-body">
				<div class="w2p-grid-cards">
					<?php 
					$admin_bar_items = [
						'remove_admin_bar_wp_logo'       => [ 'title' => __( 'WordPress Logo', 'wp-genius' ), 'desc' => __( 'Removes the WP logo from the top left.', 'wp-genius' ) ],
						'remove_admin_bar_about'         => [ 'title' => __( 'About WordPress', 'wp-genius' ), 'desc' => __( 'Removes the "About WordPress" link.', 'wp-genius' ) ],
						'remove_admin_bar_comments'      => [ 'title' => __( 'Comments', 'wp-genius' ), 'desc' => __( 'Removes the comments moderation icon.', 'wp-genius' ) ],
						'remove_admin_bar_new_content'   => [ 'title' => __( 'New Content', 'wp-genius' ), 'desc' => __( 'Removes the "+ New" menu.', 'wp-genius' ) ],
						'remove_admin_bar_search'        => [ 'title' => __( 'Search', 'wp-genius' ), 'desc' => __( 'Removes the search bar from admin bar.', 'wp-genius' ) ],
						'remove_admin_bar_updates'       => [ 'title' => __( 'Updates', 'wp-genius' ), 'desc' => __( 'Removes the updates notification icon.', 'wp-genius' ) ],
						'remove_admin_bar_appearance'    => [ 'title' => __( 'Appearance', 'wp-genius' ), 'desc' => __( 'Removes the Appearance menu.', 'wp-genius' ) ],
						'remove_admin_bar_customize'     => [ 'title' => __( 'Customize', 'wp-genius' ), 'desc' => __( 'Removes the Customize menu.', 'wp-genius' ) ],
						'remove_admin_bar_wporg'         => [ 'title' => __( 'WordPress.org', 'wp-genius' ), 'desc' => __( 'Removes WordPress.org external links.', 'wp-genius' ) ],
						'remove_admin_bar_documentation' => [ 'title' => __( 'Documentation', 'wp-genius' ), 'desc' => __( 'Removes documentation links.', 'wp-genius' ) ],
						'remove_admin_bar_support_forums'=> [ 'title' => __( 'Support Forums', 'wp-genius' ), 'desc' => __( 'Removes support forum links.', 'wp-genius' ) ],
						'remove_admin_bar_feedback'      => [ 'title' => __( 'Feedback', 'wp-genius' ), 'desc' => __( 'Removes the feedback link.', 'wp-genius' ) ],
						'remove_admin_bar_view_site'     => [ 'title' => __( 'View Site', 'wp-genius' ), 'desc' => __( 'Removes the "View Site" link.', 'wp-genius' ) ],
					];
					foreach ( $admin_bar_items as $key => $item ) : 
					?>
						<div class="w2p-toggle-card">
							<div class="w2p-toggle-header">
								<span class="w2p-toggle-title"><?php echo esc_html( $item['title'] ); ?></span>
								<label class="w2p-switch">
									<input type="checkbox" name="w2p_accelerate_settings[<?php echo $key; ?>]" value="1" <?php checked( $settings[$key], 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
							</div>
							<p class="w2p-toggle-desc"><?php echo esc_html( $item['desc'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<!-- Dashboard Widgets -->
		<div class="w2p-section">
			<div class="w2p-section-header">
				<h4><?php esc_html_e( 'Dashboard Widgets to Remove', 'wp-genius' ); ?></h4>
			</div>
			<div class="w2p-section-body">
				<p class="description" style="margin-bottom: var(--w2p-spacing-md);"><?php esc_html_e( 'Select which dashboard widgets to remove', 'wp-genius' ); ?></p>
				
				<div class="w2p-grid-cards">
					<?php 
					$dashboard_items = [
						'remove_dashboard_activity'    => [ 'title' => __( 'Activity', 'wp-genius' ), 'desc' => __( 'Removes the "Activity" widget.', 'wp-genius' ) ],
						'remove_dashboard_primary'     => [ 'title' => __( 'Primary Sidebar', 'wp-genius' ), 'desc' => __( 'Removes the primary WordPress events widget.', 'wp-genius' ) ],
						'remove_dashboard_secondary'   => [ 'title' => __( 'Secondary Sidebar', 'wp-genius' ), 'desc' => __( 'Removes the secondary WordPress widget.', 'wp-genius' ) ],
						'remove_dashboard_site_health' => [ 'title' => __( 'Site Health', 'wp-genius' ), 'desc' => __( 'Removes the Site Health status widget.', 'wp-genius' ) ],
						'remove_dashboard_right_now'   => [ 'title' => __( 'At a Glance', 'wp-genius' ), 'desc' => __( 'Removes the "At a Glance" widget.', 'wp-genius' ) ],
						'remove_dashboard_quick_draft' => [ 'title' => __( 'Quick Draft', 'wp-genius' ), 'desc' => __( 'Removes the Quick Draft widget.', 'wp-genius' ) ],
					];
					foreach ( $dashboard_items as $key => $item ) : 
					?>
						<div class="w2p-toggle-card">
							<div class="w2p-toggle-header">
								<span class="w2p-toggle-title"><?php echo esc_html( $item['title'] ); ?></span>
								<label class="w2p-switch">
									<input type="checkbox" name="w2p_accelerate_settings[<?php echo $key; ?>]" value="1" <?php checked( $settings[$key], 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
							</div>
							<p class="w2p-toggle-desc"><?php echo esc_html( $item['desc'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<!-- General Interface -->
		<div class="w2p-section">
			<div class="w2p-section-header">
				<h4><?php esc_html_e( 'General Interface', 'wp-genius' ); ?></h4>
			</div>
			<div class="w2p-grid-cards">
				<div class="w2p-toggle-card">
					<div class="w2p-toggle-header">
						<span class="w2p-toggle-title"><?php esc_html_e( 'Months Dropdown', 'wp-genius' ); ?></span>
						<label class="w2p-switch">
							<input type="checkbox" name="w2p_accelerate_settings[disable_months_dropdown]" value="1" <?php checked( $settings['disable_months_dropdown'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
					</div>
					<p class="w2p-toggle-desc"><?php esc_html_e( 'Block slow "All dates" queries in post list and media library.', 'wp-genius' ); ?></p>
				</div>
				
				<div class="w2p-toggle-card">
					<div class="w2p-toggle-header">
						<span class="w2p-toggle-title"><?php esc_html_e( 'Local Avatar Manager', 'wp-genius' ); ?></span>
						<label class="w2p-switch">
							<input type="checkbox" name="w2p_accelerate_settings[enable_local_avatar]" value="1" <?php checked( $settings['enable_local_avatar'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
					</div>
					<p class="w2p-toggle-desc"><?php esc_html_e( 'Replace Gravatar with local user avatar management. Upload and store avatars directly in your media library.', 'wp-genius' ); ?></p>
				</div>
				
				<div class="w2p-toggle-card">
					<div class="w2p-toggle-header">
						<span class="w2p-toggle-title"><?php esc_html_e( 'Upload Rename', 'wp-genius' ); ?></span>
						<label class="w2p-switch">
							<input type="checkbox" name="w2p_accelerate_settings[enable_upload_rename]" value="1" <?php checked( $settings['enable_upload_rename'], 1 ); ?> id="w2p-upload-rename-toggle" />
							<span class="w2p-slider"></span>
						</label>
					</div>
					<p class="w2p-toggle-desc"><?php esc_html_e( 'Automatically normalize and rename uploaded files using customizable patterns.', 'wp-genius' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Upload Rename Pattern Configuration -->
		<div class="w2p-section">
			<div class="w2p-section-header">
				<h4><?php esc_html_e( 'Upload Rename Pattern Configuration', 'wp-genius' ); ?></h4>
			</div>
			<div class="w2p-section-body">
				<div class="w2p-form-row" id="w2p-upload-rename-config" <?php echo empty($settings['enable_upload_rename']) ? 'style="display:none;"' : ''; ?>>
					<div class="w2p-form-label">
						<label for="w2p_upload_rename_pattern"><?php _e('Rename Pattern', 'wp-genius'); ?></label>
					</div>
					<div class="w2p-form-control">
						<input name="w2p_accelerate_settings[upload_rename_pattern]" type="text" id="w2p_upload_rename_pattern" value="<?php echo esc_attr($settings['upload_rename_pattern']); ?>" class="w2p-input-large" />
						<p class="description"><?php _e('Click any variable below to insert it into the pattern field.', 'wp-genius'); ?></p>

						<div>
							<p><?php _e('Available Variables (Click to Insert):', 'wp-genius'); ?></p>
							<div class="w2p-pattern-var">
								<a href="#" data-var="{timestamp}" title="<?php esc_attr_e('Unix timestamp', 'wp-genius'); ?>">{timestamp}</a>
								<a href="#" data-var="{sanitized}" title="<?php esc_attr_e('Sanitized original filename', 'wp-genius'); ?>">{sanitized}</a>
								<a href="#" data-var="{rand}" title="<?php esc_attr_e('Random number', 'wp-genius'); ?>">{rand}</a>
								<a href="#" data-var="{date}" title="<?php esc_attr_e('Date (Y-m-d)', 'wp-genius'); ?>">{date}</a>
								<a href="#" data-var="{date:Ymd}" title="<?php esc_attr_e('Date (Ymd)', 'wp-genius'); ?>">{date:Ymd}</a>
								<a href="#" data-var="{datetime}" title="<?php esc_attr_e('YmdHis format', 'wp-genius'); ?>">{datetime}</a>
								<a href="#" data-var="{year}" title="<?php esc_attr_e('Year', 'wp-genius'); ?>">{year}</a>
								<a href="#" data-var="{month}" title="<?php esc_attr_e('Month', 'wp-genius'); ?>">{month}</a>
								<a href="#" data-var="{day}" title="<?php esc_attr_e('Day', 'wp-genius'); ?>">{day}</a>
								<a href="#" data-var="{hour}" title="<?php esc_attr_e('Hour', 'wp-genius'); ?>">{hour}</a>
								<a href="#" data-var="{minute}" title="<?php esc_attr_e('Minute', 'wp-genius'); ?>">{minute}</a>
								<a href="#" data-var="{second}" title="<?php esc_attr_e('Second', 'wp-genius'); ?>">{second}</a>
								<a href="#" data-var="{user_id}" title="<?php esc_attr_e('User ID', 'wp-genius'); ?>">{user_id}</a>
								<a href="#" data-var="{user_login}" title="<?php esc_attr_e('User login name', 'wp-genius'); ?>">{user_login}</a>
								<a href="#" data-var="{orig}" title="<?php esc_attr_e('Original filename', 'wp-genius'); ?>">{orig}</a>
								<a href="#" data-var="{ext}" title="<?php esc_attr_e('File extension', 'wp-genius'); ?>">{ext}</a>
								<a href="#" data-var="{uniqid}" title="<?php esc_attr_e('PHP uniqid', 'wp-genius'); ?>">{uniqid}</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

        <!-- Update Behaviors -->
        <div class="w2p-section">
            <div class="w2p-section-header">
                <h4><?php esc_html_e( 'Update Behaviors', 'wp-genius' ); ?></h4>
            </div>
            <div class="w2p-section-body">
                <p class="description" style="margin-bottom: var(--w2p-spacing-md);"><?php esc_html_e( 'Control automatic updates and update checks', 'wp-genius' ); ?></p>

                <div class="w2p-grid-cards">
                    <?php 
                    $update_items = [
                        'disable_auto_update_plugin'  => [ 'title' => __( 'Disable Plugin Updates', 'wp-genius' ), 'desc' => __( 'Prevents plugins from updating automatically.', 'wp-genius' ) ],
                        'disable_auto_update_theme'   => [ 'title' => __( 'Disable Theme Updates', 'wp-genius' ), 'desc' => __( 'Prevents themes from updating automatically.', 'wp-genius' ) ],
                        'remove_wp_update_plugins'    => [ 'title' => __( 'Disable Plugin Check', 'wp-genius' ), 'desc' => __( 'Removes the wp_update_plugins schedule.', 'wp-genius' ) ],
                        'remove_wp_update_themes'     => [ 'title' => __( 'Disable Theme Check', 'wp-genius' ), 'desc' => __( 'Removes the wp_update_themes schedule.', 'wp-genius' ) ],
                        'remove_maybe_update_core'    => [ 'title' => __( 'Disable Core Check', 'wp-genius' ), 'desc' => __( 'Removes core update checks.', 'wp-genius' ) ],
                        'remove_maybe_update_plugins' => [ 'title' => __( 'Disable Plugin Check (Admin)', 'wp-genius' ), 'desc' => __( 'Removes plugin checks in admin.', 'wp-genius' ) ],
                        'remove_maybe_update_themes'  => [ 'title' => __( 'Disable Theme Check (Admin)', 'wp-genius' ), 'desc' => __( 'Removes theme checks in admin.', 'wp-genius' ) ],
                        'hide_plugin_notices'         => [ 'title' => __( 'Hide Plugin Notices', 'wp-genius' ), 'desc' => __( 'Hides update notices for plugins.', 'wp-genius' ) ],
                        'block_acf_updates'           => [ 'title' => __( 'Block ACF Updates', 'wp-genius' ), 'desc' => __( 'Blocks Advanced Custom Fields update requests.', 'wp-genius' ) ],
                    ];
                    foreach ( $update_items as $key => $item ) : 
                    ?>
                        <div class="w2p-toggle-card">
							<div class="w2p-toggle-header">
								<span class="w2p-toggle-title"><?php echo esc_html( $item['title'] ); ?></span>
								<label class="w2p-switch">
									<input type="checkbox" name="w2p_accelerate_settings[<?php echo $key; ?>]" value="1" <?php checked( $settings[$key], 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
							</div>
							<p class="w2p-toggle-desc"><?php echo esc_html( $item['desc'] ); ?></p>
						</div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="w2p-section">
			<div class="w2p-section-header">
                <h4><?php esc_html_e( 'Danger Zone', 'wp-genius' ); ?></h4>
            </div>
            <div class="w2p-section-body">
				<p class="description"><?php esc_html_e( 'Be careful with these options. These options can break your site if not used correctly.', 'wp-genius' ); ?></p>
                <div class="w2p-grid-cards">
                    <div class="w2p-toggle-card-danger">
                        <div class="w2p-toggle-header">
                            <span class="w2p-toggle-title-danger"><?php esc_html_e( 'Block External HTTP', 'wp-genius' ); ?> (DANGER!)</span>
                            <label class="w2p-switch">
                                <input type="checkbox" name="w2p_accelerate_settings[block_external_http]" value="1" <?php checked( $settings['block_external_http'], 1 ); ?> />
                                <span class="w2p-slider"></span>
                            </label>
                        </div>
                        <p class="w2p-toggle-desc" style="color: var(--w2p-color-danger); opacity: 0.9;">
                            <?php esc_html_e( 'This will block all plugins and themes from checking for updates, dramatically speeding up the backend load times. Use only for local development!', 'wp-genius' ); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

		<div class="w2p-settings-actions">
			<button type="submit" class="w2p-btn w2p-btn-primary" id="w2p-save-accelerate">
                <i class="fa-solid fa-floppy-disk"></i>
                <?php esc_html_e( 'Save Accelerate Settings', 'wp-genius' ); ?>
            </button>
		</div>
	</form>
</div>


<script>
(function($) {
	$(document).ready(function() {
		// Toggle upload rename pattern config visibility
		$('#w2p-upload-rename-toggle').on('change', function() {
			if ($(this).is(':checked')) {
				$('#w2p-upload-rename-config').slideDown();
			} else {
				$('#w2p-upload-rename-config').slideUp();
			}
		});
		
		// Handle pattern variable clicks
		$('.w2p-pattern-var').on('click', function(e) {
			e.preventDefault();
			var $input = $('#w2p_upload_rename_pattern');
			var variable = $(this).data('var');
			var currentValue = $input.val();
			
			// Insert at cursor position or append
			var input = $input[0];
			if (input.selectionStart || input.selectionStart === 0) {
				var startPos = input.selectionStart;
				var endPos = input.selectionEnd;
				$input.val(currentValue.substring(0, startPos) + variable + currentValue.substring(endPos));
				// Set cursor position after inserted text
				input.selectionStart = input.selectionEnd = startPos + variable.length;
			} else {
				$input.val(currentValue + variable);
			}
		});
	});
})(jQuery);
</script>

