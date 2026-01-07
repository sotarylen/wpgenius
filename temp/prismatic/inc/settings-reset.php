<?php // Prismatic - Reset Settings

if (!defined('ABSPATH')) exit;

function prismatic_admin_notice() {
	
	$screen_id = prismatic_get_current_screen_id();
	
	if ($screen_id === 'settings_page_prismatic') {
		
		if (isset($_GET['reset-options'])) {
			
			if ($_GET['reset-options'] === 'true') : ?>
				
				<div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e('Default options restored.', 'prismatic'); ?></strong></p></div>
				
			<?php else : ?>
				
				<div class="notice notice-info is-dismissible"><p><strong><?php esc_html_e('No changes made to options.', 'prismatic'); ?></strong></p></div>
				
			<?php endif;
			
		}
		
		if (!prismatic_check_date_expired() && !prismatic_dismiss_notice_check()) {
			
			$tabs = array('tab1', 'tab2', 'tab3', 'tab4', 'tab5');
			
			$tab = (isset($_GET['tab']) && in_array($_GET['tab'], $tabs)) ? $_GET['tab'] : 'tab1';
			
			?>
			
			<div class="notice notice-success notice-lh">
				<p>
					<strong><?php esc_html_e('Fall Sale!', 'prismatic'); ?></strong> 
					<?php esc_html_e('Take 25% OFF any of our', 'prismatic'); ?> 
					<a target="_blank" rel="noopener noreferrer" href="https://plugin-planet.com/"><?php esc_html_e('Pro WordPress plugins', 'prismatic'); ?></a> 
					<?php esc_html_e('and', 'prismatic'); ?> 
					<a target="_blank" rel="noopener noreferrer" href="https://books.perishablepress.com/"><?php esc_html_e('books', 'prismatic'); ?></a>. 
					<?php esc_html_e('Apply code', 'prismatic'); ?> <code>FALL2025</code> <?php esc_html_e('at checkout. Sale ends 1/11/2026.', 'prismatic'); ?> 
					<?php echo prismatic_dismiss_notice_link($tab); ?>
				</p>
			</div>
			
			<?php
			
		}
		
	}
	
}

//

function prismatic_dismiss_notice_activate() {
	
	delete_option('prismatic-dismiss-notice');
	
}

function prismatic_dismiss_notice_version() {
	
	$version_current = PRISMATIC_VERSION;
	
	$version_previous = get_option('prismatic-dismiss-notice');
	
	$version_previous = ($version_previous) ? $version_previous : $version_current;
	
	if (version_compare($version_current, $version_previous, '>')) {
		
		delete_option('prismatic-dismiss-notice');
		
	}
	
}

function prismatic_dismiss_notice_check() {
	
	$check = get_option('prismatic-dismiss-notice');
	
	return ($check) ? true : false;
	
}

function prismatic_dismiss_notice_save() {
	
	if (isset($_GET['dismiss-notice-verify']) && wp_verify_nonce($_GET['dismiss-notice-verify'], 'prismatic_dismiss_notice')) {
		
		if (!current_user_can('manage_options')) exit;
		
		$result = update_option('prismatic-dismiss-notice', PRISMATIC_VERSION, false);
		
		$result = $result ? 'true' : 'false';
		
		$tabs = array('tab1', 'tab2', 'tab3', 'tab4', 'tab5');
		
		$tab = (isset($_GET['tab']) && in_array($_GET['tab'], $tabs)) ? $_GET['tab'] : 'tab1';
		
		$location = admin_url('options-general.php?page=prismatic&tab='. $tab .'&dismiss-notice='. $result);
		
		wp_redirect($location);
		
		exit;
		
	}
	
}

function prismatic_dismiss_notice_link($tab) {
	
	$nonce = wp_create_nonce('prismatic_dismiss_notice');
	
	$href  = add_query_arg(array('dismiss-notice-verify' => $nonce), admin_url('options-general.php?page=prismatic&tab='. $tab));
	
	$label = esc_html__('Dismiss', 'prismatic');
	
	return '<a class="prismatic-dismiss-notice" href="'. esc_url($href) .'">'. esc_html($label) .'</a>';
	
}

function prismatic_check_date_expired() {
	
	$expires = apply_filters('prismatic_check_date_expired', '2026-01-11');
	
	return (new DateTime() > new DateTime($expires)) ? true : false;
	
}

//

function prismatic_reset_options() {
	
	if (isset($_GET['reset-options-verify']) && wp_verify_nonce($_GET['reset-options-verify'], 'prismatic_reset_options')) {
		
		if (!current_user_can('manage_options')) exit;
		
		$update_general   = update_option('prismatic_options_general',   Prismatic::options_general());
		$update_prism     = update_option('prismatic_options_prism',     Prismatic::options_prism());
		$update_highlight = update_option('prismatic_options_highlight', Prismatic::options_highlight());
		$update_plain     = update_option('prismatic_options_plain',     Prismatic::options_plain());
		
		$result = 'false';
		
		if (
			$update_general   || 
			$update_prism     || 
			$update_highlight || 
			$update_plain 
			
		) $result = 'true';
		
		$location = admin_url('options-general.php?page=prismatic&reset-options='. $result);
		
		wp_redirect($location);
		
		exit;
		
	}
	
}
