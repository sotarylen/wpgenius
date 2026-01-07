<?php // Prismatic - Display Settings

if (!defined('ABSPATH')) exit;

function prismatic_menu_pages() {
	
	// add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function )
	add_options_page('Prismatic', 'Prismatic', 'manage_options', 'prismatic', 'prismatic_display_settings');
	
}

function prismatic_get_tabs() {
	
	$tabs = array(
		'tab1' => esc_html__('General',      'prismatic'), 
		'tab2' => esc_html__('Prism.js',     'prismatic'), 
		'tab3' => esc_html__('Highlight.js', 'prismatic'), 
		'tab4' => esc_html__('Plain Flavor', 'prismatic'), 
		'tab5' => esc_html__('Advanced',     'prismatic'), 
	);
	
	return $tabs;
	
}

function prismatic_display_settings() { 
	
	$tab_active = isset($_GET['tab']) ? $_GET['tab'] : 'tab1';
	
	$tab_href = admin_url('options-general.php?page=prismatic');
	
	$tab_names = prismatic_get_tabs();
	
	?>
	
	<div class="wrap wrap-<?php echo esc_attr($tab_active); ?>">
		<h1><span class="fa fa-pad fa-code"></span> <?php echo PRISMATIC_NAME; ?> <span class="prismatic-version"><?php echo PRISMATIC_VERSION; ?></span></h1>
		<h2 class="nav-tab-wrapper">
			
			<?php 
				
				foreach ($tab_names as $key => $value) {
					
					$active = ($tab_active === $key) ? ' nav-tab-active' : '';
					
					echo '<a href="'. $tab_href .'&tab='. $key .'" class="nav-tab nav-'. $key . $active .'">'. $value .'</a>';
					
				}
				
			?>
			
		</h2>
		<form method="post" action="options.php">
			
			<?php
				
				if ($tab_active === 'tab1') {
					
					settings_fields('prismatic_options_general');
					do_settings_sections('prismatic_options_general');
				
				} elseif ($tab_active === 'tab2') {
					
					settings_fields('prismatic_options_prism');
					do_settings_sections('prismatic_options_prism');
					
				} elseif ($tab_active === 'tab3') {
					
					settings_fields('prismatic_options_highlight');
					do_settings_sections('prismatic_options_highlight');
					
				} elseif ($tab_active === 'tab4') {
					
					settings_fields('prismatic_options_plain');
					do_settings_sections('prismatic_options_plain');
					
				} elseif ($tab_active === 'tab5') {
					
					settings_fields('prismatic_options_advanced');
					do_settings_sections('prismatic_options_advanced');
					
				}
				
				submit_button();
				
				if ($tab_active !== 'tab1') prismatic_wp_resources($tab_active);
				
			?>
			
		</form>
	</div>
	
<?php }

function prismatic_wp_resources($tab_active) {
	
	if ($tab_active !== 'tab1') :
		
		$array = array(
			
			0  => '<a target="_blank" rel="noopener noreferrer" href="https://digwp.com/" title="Take your WP skills to the next level"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/book-digging-into-wordpress.jpg" alt=""></a>',
			
			1  => '<a target="_blank" rel="noopener noreferrer" href="https://htaccessbook.com/" title="Secure and optimize your website"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/book-htaccess-made-easy.jpg" alt=""></a>',
			
			2  => '<a target="_blank" rel="noopener noreferrer" href="https://wp-tao.com/" title="Learn the Way of WordPress"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/book-tao-of-wordpress.jpg" alt=""></a>',
			
			3  => '<a target="_blank" rel="noopener noreferrer" href="https://books.perishablepress.com/downloads/wizards-collection-sql-recipes-wordpress/" title="Wizard&rsquo;s SQL Recipes for WordPress"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/book-wizards-sql-wordpress.jpg" alt=""></a>',
			
			4  => '<a target="_blank" rel="noopener noreferrer" href="https://wp-tao.com/wordpress-themes-book/" title="Build and sell awesome themes"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/book-wp-themes-in-depth.jpg" alt=""></a>',
			
			//
			
			5  => '<a target="_blank" rel="noopener noreferrer" href="https://plugin-planet.com/banhammer-pro/" title="Banhammer Pro: Drop the hammer."><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/plugin-banhammer-pro.jpg" alt=""></a>',
			
			6  => '<a target="_blank" rel="noopener noreferrer" href="https://plugin-planet.com/bbq-pro/" title="Fastest WordPress Firewall"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/plugin-bbq-pro.jpg" alt=""></a>',	
						
			7  => '<a target="_blank" rel="noopener noreferrer" href="https://plugin-planet.com/blackhole-pro/" title="Trap bad bots in a virtual black hole"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/plugin-blackhole-pro.jpg" alt=""></a>',
			
			8  => '<a target="_blank" rel="noopener noreferrer" href="https://plugin-planet.com/ga-google-analytics-pro/" title="Connect Google Analytics to WordPress"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/plugin-ga-pro.jpg" alt=""></a>',
			
			9  => '<a target="_blank" rel="noopener noreferrer" href="https://plugin-planet.com/head-meta-pro/" title="Ultimate meta tags for WordPress"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/head-meta-pro.jpg" alt=""></a>',
			
			10 => '<a target="_blank" rel="noopener noreferrer" href="https://plugin-planet.com/simple-ajax-chat-pro/" title="Unlimited chats for WordPress"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/plugin-sac-pro.jpg" alt=""></a>',
					
			11 => '<a target="_blank" rel="noopener noreferrer" href="https://plugin-planet.com/usp-pro/" title="Unlimited front-end forms"><img width="125" height="125" src="'. PRISMATIC_URL .'img/resources/plugin-usp-pro.jpg" alt=""></a>',
			
		);
		
		$items = array_rand($array, 4);
		
		$item1 = isset($array[$items[0]]) ? $array[$items[0]] : null;
		$item2 = isset($array[$items[1]]) ? $array[$items[1]] : null;
		$item3 = isset($array[$items[2]]) ? $array[$items[2]] : null;
		$item4 = isset($array[$items[3]]) ? $array[$items[3]] : null;
		
		if ($item1 && $item3 && $item3 && $item4) :
		
		?>
			
			<hr class="wp-resources-sep">
			
			<div class="wp-resources">
				
				<h2 class="prismatic-noicon"><span class="dashicons dashicons-coffee"></span> <?php esc_html_e('WP Resources', 'prismatic'); ?></h2>
				
				<p><?php esc_html_e('Check out more WordPress resources from this developer:', 'prismatic'); ?></p>
				
				<div class="wp-resources-wrap"><?php echo $item1 . $item2 . $item3 . $item4; ?></div>
				
			</div>
			
		<?php
		
		endif;
		
	endif;
	
}
