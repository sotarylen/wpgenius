<?php // Prismatic - Enqueue Resources

if (!defined('ABSPATH')) exit;

function prismatic_enqueue() {
	
	global $prismatic_options_general;
	
	$library = (isset($prismatic_options_general['library'])) ? $prismatic_options_general['library'] : 'none';
	
	if (is_admin()) {
		
		$screen_id = prismatic_get_current_screen_id();
		
		if ($screen_id === 'post' || $screen_id === 'page') {
			
			if ($library === 'prism') {
				
				prismatic_prism_enqueue();
				
			} elseif ($library === 'highlight') {
				
				prismatic_highlight_enqueue();
				
			}
			
		}
		
	} else {
		
		if ($library === 'prism') {
			
			prismatic_prism_enqueue();
			
		} elseif ($library === 'highlight') {
			
			prismatic_highlight_enqueue();
			
		}
		
		prismatic_custom_enqueue();
		
	}
	
}

function prismatic_enqueue_settings() {
	
	$screen_id = prismatic_get_current_screen_id();
	
	if ($screen_id === 'settings_page_prismatic') {
		
		wp_enqueue_style('prismatic-font-icons', PRISMATIC_URL .'css/styles-font-icons.css', array(), PRISMATIC_VERSION);
		
		wp_enqueue_style('prismatic-settings', PRISMATIC_URL .'css/styles-settings.css', array(), PRISMATIC_VERSION);
		
		wp_enqueue_style('wp-jquery-ui-dialog');
		
		$js_deps = array('jquery', 'jquery-ui-core', 'jquery-ui-dialog');
		
		wp_enqueue_script('prismatic-settings', PRISMATIC_URL .'js/scripts-settings.js', $js_deps, PRISMATIC_VERSION);
		
		$data = prismatic_get_vars_admin();
		
		wp_localize_script('prismatic-settings', 'prismatic_settings', $data);
		
	}
	
}

function prismatic_enqueue_buttons() {
	
	$screen_id = prismatic_get_current_screen_id();
	
	if ($screen_id === 'post' || $screen_id === 'page') {
		
		wp_enqueue_style('prismatic-buttons', PRISMATIC_URL .'css/styles-buttons.css', array(), PRISMATIC_VERSION);
		
	}
	
}

function prismatic_get_vars_admin() {
	
	$data = array(
		
		'reset_title'   => __('Confirm Reset',            'prismatic'),
		'reset_message' => __('Restore default options?', 'prismatic'),
		'reset_true'    => __('Yes, make it so.',         'prismatic'),
		'reset_false'   => __('No, abort mission.',       'prismatic'),
		
	);
	
	return $data;
	
}

function prismatic_prism_enqueue() {
	
	global $prismatic_options_prism, $prismatic_options_advanced;
	
	if (isset($prismatic_options_prism['singular_only']) && $prismatic_options_prism['singular_only'] && !is_singular() && !is_admin()) return;
	
	$languages = prismatic_active_languages('prism');
	
	$languages = apply_filters('prismatic_prism_enqueue_languages', array_filter($languages));
	
	if (!empty($languages)) {
		
		$theme = (isset($prismatic_options_prism['prism_theme'])) ? $prismatic_options_prism['prism_theme'] : 'default';
		
		wp_enqueue_style('prismatic-prism', PRISMATIC_URL .'lib/prism/css/theme-'. $theme .'.css', array(), PRISMATIC_VERSION, 'all');
		
		wp_enqueue_script('prismatic-prism', PRISMATIC_URL .'lib/prism/js/prism-core.js', array(), PRISMATIC_VERSION, true);
		
		if (
			(isset($prismatic_options_prism['show_language'])  && $prismatic_options_prism['show_language']) || 
			(isset($prismatic_options_prism['copy_clipboard']) && $prismatic_options_prism['copy_clipboard'])
		) {
			
			wp_enqueue_style('prismatic-plugin-styles', PRISMATIC_URL .'lib/prism/css/plugin-styles.css', array(), PRISMATIC_VERSION, 'all');
			
			wp_enqueue_script('prismatic-prism-toolbar', PRISMATIC_URL .'lib/prism/js/plugin-toolbar.js', array('prismatic-prism'), PRISMATIC_VERSION, true);
			
		}
		
		if (isset($prismatic_options_prism['line_highlight']) && $prismatic_options_prism['line_highlight']) {
			
			wp_enqueue_script('prismatic-prism-line-highlight', PRISMATIC_URL .'lib/prism/js/plugin-line-highlight.js', array('prismatic-prism'), PRISMATIC_VERSION, true);
			
		}
		
		if (isset($prismatic_options_prism['line_numbers']) && $prismatic_options_prism['line_numbers']) {
			
			wp_enqueue_script('prismatic-prism-line-numbers', PRISMATIC_URL .'lib/prism/js/plugin-line-numbers.js', array('prismatic-prism'), PRISMATIC_VERSION, true);
			
		}
		
		if (isset($prismatic_options_prism['show_language']) && $prismatic_options_prism['show_language']) {
			
			wp_enqueue_script('prismatic-prism-show-language', PRISMATIC_URL .'lib/prism/js/plugin-show-language.js', array('prismatic-prism'), PRISMATIC_VERSION, true);
			
		}
		
		if (isset($prismatic_options_prism['copy_clipboard']) && $prismatic_options_prism['copy_clipboard']) {
			
			wp_enqueue_script('prismatic-copy-clipboard', PRISMATIC_URL .'lib/prism/js/plugin-copy-clipboard.js', array('prismatic-prism'), PRISMATIC_VERSION, true);
			
		}
		
		if (isset($prismatic_options_prism['command_line']) && $prismatic_options_prism['command_line']) {
			
			wp_enqueue_script('prismatic-command-line', PRISMATIC_URL .'lib/prism/js/plugin-command-line.js', array('prismatic-prism'), PRISMATIC_VERSION, true);
			
		}
		
		$prefix = array('lang-', 'language-');
		
		foreach ($languages as $language) {
			
			$language = str_replace($prefix, '', $language);
			
			$file = PRISMATIC_DIR .'lib/prism/js/lang-'. $language .'.js';
			
			if (file_exists($file)) {
				
				wp_enqueue_script('prismatic-prism-'. $language, PRISMATIC_URL .'lib/prism/js/lang-'. $language .'.js', array('prismatic-prism'),  PRISMATIC_VERSION, true);
				
			}
			
		}
		
		if (is_admin()) {
			
			// todo: once gutenberg is further developed, find a better way to add editor support
			
			function prismatic_prism_inline_js() {
				
				?>
				
				<script type="text/javascript">
					document.onreadystatechange = function () {
					    if (document.readyState == 'complete') {
					        Prism.highlightAll();
					    }
					}
				</script>
				
				<?php
				
			}
			add_action('admin_print_footer_scripts', 'prismatic_prism_inline_js');
				
		}
		
	}
	
}

function prismatic_highlight_enqueue() {
	
	global $prismatic_options_highlight, $prismatic_options_advanced;
	
	if (isset($prismatic_options_highlight['singular_only']) && $prismatic_options_highlight['singular_only'] && !is_singular() && !is_admin()) return;
	
	$always_load = (isset($prismatic_options_highlight['noprefix_classes']) && $prismatic_options_highlight['noprefix_classes']) ? true : false;
	
	$languages = prismatic_active_languages('highlight');
	
	$languages = array_filter($languages);
	
	if (!empty($languages) || $always_load) {
		
		$theme = (isset($prismatic_options_highlight['highlight_theme'])) ? $prismatic_options_highlight['highlight_theme'] : 'default';
		
		wp_enqueue_style('prismatic-highlight', PRISMATIC_URL .'lib/highlight/css/'. $theme .'.css', array(), PRISMATIC_VERSION, 'all');
		
		wp_enqueue_script('prismatic-highlight', PRISMATIC_URL .'lib/highlight/js/highlight-core.js', array(), PRISMATIC_VERSION, true);
		
		$init = (isset($prismatic_options_highlight['init_javascript'])) ? $prismatic_options_highlight['init_javascript'] : '';
		
		if ($init) {
			
			wp_add_inline_script('prismatic-highlight', $init, 'after');
			
		}
		
		if (is_admin()) {
			
			// todo: once gutenberg is further developed, find a better way to add editor support
			
			function prismatic_highlight_inline_js() {
				
				?>
				
				<script type="text/javascript">
					document.onreadystatechange = function () {
					    if (document.readyState == 'complete') {
					        jQuery('pre > code').each(function() {
								hljs.highlightBlock(this);
							});
					    }
					}
				</script>
				
				<?php
				
			}
			add_action('admin_print_footer_scripts', 'prismatic_highlight_inline_js');
				
		}
		
	}
	
}

function prismatic_custom_enqueue() {
	
	global $prismatic_options_advanced;
	
	$custom_style = isset($prismatic_options_advanced['custom_style']) ? $prismatic_options_advanced['custom_style'] : '';
	
	if ($custom_style) {
		
		wp_register_style('prismatic-custom', false);
		wp_enqueue_style('prismatic-custom');
		wp_add_inline_style('prismatic-custom', $custom_style);
		
	}
	
}

function prismatic_active_languages($library) {
	
	global $posts, $post;
	
	$languages = array();
	
	if (is_admin()) {
		
		$content = $post->post_content;
		
		$languages = prismatic_active_languages_loop($library, '', $content, array(), null);
		
	} else {
		
		if (is_singular()) {
			
			$excerpt = $post->post_excerpt;
			
			$content = $post->post_content;
			
			$comments = ($post->comment_count) ? get_comments(array('post_id' => $post->ID, 'status' => 'approve')) : array();
			
			$fields = function_exists('get_fields') ? get_fields($post->ID) : null; // ACF
			
			$languages = prismatic_active_languages_loop($library, $excerpt, $content, $comments, $fields);
			
		} else {
			
			foreach ($posts as $post) {
				
				$excerpt = $post->post_excerpt;
				
				$content = $post->post_content;
				
				$comments = array();
				
				$langs_array[] = prismatic_active_languages_loop($library, $excerpt, $content, $comments, null);
				
			}
			
			if (!empty($langs_array) && is_array($langs_array)) {
				
				foreach($langs_array as $key => $value) {
					
					foreach ($value as $k => $v) {
						
						$languages[] = $v;
						
					}
					
				}
				
			}
			
		}
		
	}
	
	return $languages;
	
}

function prismatic_active_languages_loop($library, $excerpt, $content, $comments, $fields) {
	
	$languages = array();
	
	$classes = ($library === 'prism') ? prismatic_prism_classes() : prismatic_highlight_classes();
	
	foreach ($classes as $class) {
		
		foreach($class as $cls) {
			
			//
			
			if ($library === 'prism') {
				
				if ($excerpt && preg_match("/(\s|\")(" . $cls . ")(\s|\")/", $excerpt)) {
					
					$languages[] = $cls;
					
				}
				
			} else {
				
				if ($excerpt && strpos($excerpt, $cls) !== false) {
					
					$languages[] = $cls;
					
				}
				
			}
			
			//
			
			if ($library === 'prism') {
				
				if ($content && preg_match("/(\s|\")(" . $cls . ")(\s|\")/", $content)) {
					
					$languages[] = $cls;
					
				}
				
			} else {
				
				if ($content && strpos($content, $cls) !== false) {
					
					$languages[] = $cls;
					
				}
				
			}
			
			//
			
			foreach ($comments as $comment) {
				
				if ($library === 'prism') {
					
					if ($comment->comment_content && preg_match("/(\s|\")(" . $cls . ")(\s|\")/", $comment->comment_content)) {
						
						$languages[] = $cls;
						
					}
					
				} else {
					
					if ($comment->comment_content && strpos($comment->comment_content, $cls) !== false) {
						
						$languages[] = $cls;
						
					}
					
				}
				
			}
			
			//
			
			if ($fields) {
				
				foreach ($fields as $key => $value) {
					
					if ($library === 'prism') {
						
						if ($value && is_string($value) && preg_match("/(\s|\")(" . $cls . ")(\s|\")/", $value)) {
							
							$languages[] = $cls;
							
						}
						
					} else {
						
						if ($value && is_string($value) && strpos($value, $cls) !== false) {
							
							$languages[] = $cls;
							
						}
						
					}
					
				}
				
			}
			
		}
		
	}
	
	$languages = array_unique($languages);
	
	return $languages;
	
}

function prismatic_prism_classes() {
	
	$classes = array(
		
		array(
			'language-apacheconf', 
			'language-applescript', 
			'language-arduino', 
			'language-asmatmel', 
			'language-awk', 
			'language-bash', 
			'language-batch', 
			'language-c', 
			'language-clike', 
			'language-coffeescript', 
			'language-cpp', 
			'language-csharp', 
			'language-css', 
			'language-d', 
			'language-dart', 
			'language-diff', 
			'language-elixir', 
			'language-gcode', 
			'language-git', 
			'language-go', 
			'language-graphql', 
			'language-groovy', 
			'language-hcl', 
			'language-http', 
			'language-ini', 
			'language-java', 
			'language-javascript', 
			'language-json', 
			'language-jsx', 
			'language-julia', 
			'language-kotlin', 
			'language-latex', 
			'language-liquid', 
			'language-lua', 
			'language-makefile', 
			'language-markdown', 
			'language-markup', 
			'language-matlab', 
			'language-nginx', 
			'language-objectivec', 
			'language-pascal', 
			'language-perl', 
			'language-php', 
			'language-powerquery', 
			'language-powershell', 
			'language-python', 
			'language-r', 
			'language-ruby', 
			'language-rust', 
			'language-sas', 
			'language-sass', 
			'language-scala',
			'language-scss', 
			'language-shell-session', 
			'language-solidity', 
			'language-sparql', 
			'language-splunk-spl', 
			'language-sql', 
			'language-swift', 
			'language-tsx', 
			'language-turtle', 
			'language-twig',
			'language-typescript', 
			'language-verilog', 
			'language-vhdl', 
			'language-vim', 
			'language-visual-basic', 
			'language-yaml', 
			
			// aliases
			
			'language-html', 
			'language-mathml', 
			'language-rss', 
			'language-ssml', 
			'language-svg', 
			'language-xml', 
			
			// none
			
			'language-none'
		),
		
		array(
			'lang-apacheconf', 
			'lang-applescript', 
			'lang-arduino', 
			'lang-asmatmel', 
			'lang-awk', 
			'lang-bash', 
			'lang-batch', 
			'lang-c', 
			'lang-clike', 
			'lang-coffeescript', 
			'lang-cpp', 
			'lang-csharp', 
			'lang-css', 
			'lang-d', 
			'lang-dart', 
			'lang-diff', 
			'lang-elixir', 
			'lang-gcode', 
			'lang-git', 
			'lang-go', 
			'lang-graphql', 
			'lang-groovy', 
			'lang-hcl', 
			'lang-http', 
			'lang-ini', 
			'lang-java', 
			'lang-javascript', 
			'lang-json', 
			'lang-jsx', 
			'lang-julia', 
			'lang-kotlin', 
			'lang-latex', 
			'lang-liquid', 
			'lang-lua', 
			'lang-makefile', 
			'lang-markdown', 
			'lang-markup', 
			'lang-matlab', 
			'lang-nginx', 
			'lang-objectivec', 
			'lang-pascal', 
			'lang-perl', 
			'lang-php', 
			'lang-powerquery', 
			'lang-powershell', 
			'lang-python', 
			'lang-r', 
			'lang-ruby', 
			'lang-rust', 
			'lang-sas', 
			'lang-sass', 
			'lang-scala',
			'lang-scss', 
			'lang-shell-session', 
			'lang-solidity', 
			'lang-sparql', 
			'lang-splunk-spl', 
			'lang-sql', 
			'lang-swift', 
			'lang-tsx', 
			'lang-turtle', 
			'lang-twig',
			'lang-typescript', 
			'lang-verilog', 
			'lang-vhdl', 
			'lang-vim', 
			'lang-visual-basic', 
			'lang-yaml', 
			
			// aliases
			
			'lang-html', 
			'lang-mathml', 
			'lang-rss', 
			'lang-ssml', 
			'lang-svg', 
			'lang-xml', 
			
			// none
			
			'lang-none'
			
		)
		
	);
	
	return $classes;
	
}

function prismatic_highlight_classes() {
	
	$classes = array(
			
		array(
			'language-'
		),
		
		array(
			'lang-', 
		)
		
	);
	
	return $classes;
	
}

function prismatic_get_current_screen_id() {
	
	if (!function_exists('get_current_screen')) require_once ABSPATH .'/wp-admin/includes/screen.php';
	
	$screen = get_current_screen();
	
	if ($screen && property_exists($screen, 'id')) return $screen->id;
	
	return false;
	
}