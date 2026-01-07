<?php // Prismatic - Settings Callbacks

if (!defined('ABSPATH')) exit;

function prismatic_section_general() {
	
	echo '<p>'. esc_html__('Thank you for using the free version of', 'prismatic') .' <a target="_blank" rel="noopener noreferrer" href="https://wordpress.org/plugins/prismatic/">'. esc_html__('Prismatic', 'prismatic') .'</a>.</p>';
	
}

function prismatic_section_prism() {
	
	echo '<p>'. esc_html__('Settings for syntax highlighting via', 'prismatic') .' <a target="_blank" rel="noopener noreferrer" href="https://prismjs.com/">'. esc_html__('Prism.js', 'prismatic') .'</a>.</p>';
	
}

function prismatic_section_prism_code() {
	
	echo '<p>'. esc_html__('Settings for code escaping when Prism.js is enabled. By default all code contained in code tags is escaped on the frontend. Here you may choose also to enable escaping in the Admin Area. If unsure, use the default settings.', 'prismatic') .'</p>';
	
}

function prismatic_section_highlight() {
	
	echo '<p>'. esc_html__('Settings for syntax highlighting via', 'prismatic') .' <a target="_blank" rel="noopener noreferrer" href="https://highlightjs.org/">'. esc_html__('Highlight.js', 'prismatic') .'</a>.</p>';
	
}

function prismatic_section_highlight_code() {
	
	echo '<p>'. esc_html__('Settings for code escaping when Highlight.js is enabled. By default all code contained in code tags is escaped on the frontend. Here you may choose also to enable escaping in the Admin Area. If unsure, use the default settings.', 'prismatic') .'</p>';
	
}

function prismatic_section_plain() {
	
	echo '<p>'. esc_html__('Settings for code escaping when syntax highlighting is disabled. By default all code contained in code tags is escaped on the frontend. Here you may choose also to enable escaping in the Admin Area. If unsure, use the default settings.', 'prismatic') .'</p>';
	
}

function prismatic_section_advanced() {
	
	echo '<p>'. esc_html__('Advanced settings. These settings apply regardless of which library is active (Prism.js, Highlight.js, Plain Flavor, or None).', 'prismatic') .'</p>';
	
}

function prismatic_library() {
	
	$library = array(
		
		'prism' => array(
			'value' => 'prism',
			'label' => esc_html__('Prism.js', 'prismatic'),
		),
		'highlight' => array(
			'value' => 'highlight',
			'label' => esc_html__('Highlight.js', 'prismatic'),
		),
		'plain' => array(
			'value' => 'plain',
			'label' => esc_html__('Plain Flavor', 'prismatic'),
		),
		'none' => array(
			'value' => 'none',
			'label' => esc_html__('None (Disable)', 'prismatic'),
		),
	);
	
	return $library;
	
}

function prismatic_location() {
	
	$array = array(
		
		'none' => array(
			'value' => 'none',
			'label' => esc_html__('Disable (default)', 'prismatic'),
		),
		'admin' => array(
			'value' => 'admin',
			'label' => esc_html__('Enable', 'prismatic'),
		),
	);
	
	return $array;
	
}

function prismatic_prism_theme() {
	
	$theme = array(
		
		'coy' => array(
			'value' => 'coy',
			'label' => esc_html__('Coy', 'prismatic'),
		),
		'dark' => array(
			'value' => 'dark',
			'label' => esc_html__('Dark', 'prismatic'),
		),
		'default' => array(
			'value' => 'default',
			'label' => esc_html__('Default', 'prismatic'),
		),
		'funky' => array(
			'value' => 'funky',
			'label' => esc_html__('Funky', 'prismatic'),
		),
		'okaidia' => array(
			'value' => 'okaidia',
			'label' => esc_html__('Okaidia', 'prismatic'),
		),
		'solarized' => array(
			'value' => 'solarized',
			'label' => esc_html__('Solarized', 'prismatic'),
		),
		'tomorrow-night' => array(
			'value' => 'tomorrow-night',
			'label' => esc_html__('Tomorrow Night', 'prismatic'),
		),
		'twilight' => array(
			'value' => 'twilight',
			'label' => esc_html__('Twilight', 'prismatic'),
		),
	);
	
	return $theme;
	
}

function prismatic_highlight_theme() {
	
	$theme = array();
	
	require_once PRISMATIC_DIR .'lib/highlight/themes.php';
	
	return $theme;
	
}

function prismatic_callback_select($args) {
	
	$id      = isset($args['id'])      ? $args['id']      : '';
	$label   = isset($args['label'])   ? $args['label']   : '';
	$section = isset($args['section']) ? $args['section'] : '';
	
	$setting = 'prismatic_options_'. $section;
	
	$options = prismatic_get_default_options($section);
	
	$value = isset($options[$id]) ? sanitize_text_field($options[$id]) : '';
	
	$options_array = array();
	
	if ($id === 'library') {
		
		$options_array = prismatic_library();
		
	} elseif ($id === 'filter_content' || $id === 'filter_excerpts' || $id === 'filter_comments') {
		
		$options_array = prismatic_location();
		
	} elseif ($id === 'prism_theme') {
		
		$options_array = prismatic_prism_theme();
		
	} elseif ($id === 'highlight_theme') {
		
		$options_array = prismatic_highlight_theme();
		
	}
	
	echo '<select name="'. $setting .'['. $id .']">';
	
	foreach ($options_array as $option) {
		echo '<option '. selected($option['value'], $value, false) .' value="'. $option['value'] .'">'. $option['label'] .'</option>';
	}
	echo '</select> <label class="prismatic-label inline-block" for="'. $setting .'['. $id .']">'. $label .'</label>';
	
}

function prismatic_callback_text($args) {
	
	$id      = isset($args['id'])      ? $args['id']      : '';
	$label   = isset($args['label'])   ? $args['label']   : '';
	$section = isset($args['section']) ? $args['section'] : '';
	
	$setting = 'prismatic_options_'. $section;
	
	$options = prismatic_get_default_options($section);
	
	$value = isset($options[$id]) ? sanitize_text_field($options[$id]) : '';
	
	echo '<input name="'. $setting .'['. $id .']" type="text" size="40" value="'. $value .'"> ';
	echo '<label for="'. $setting .'['. $id .']" class="prismatic-label">'. $label .'</label>';
	
}

function prismatic_callback_textarea($args) {
	
	$id      = isset($args['id'])      ? $args['id']      : '';
	$label   = isset($args['label'])   ? $args['label']   : '';
	$section = isset($args['section']) ? $args['section'] : '';
	
	$setting = 'prismatic_options_'. $section;
	
	$options = prismatic_get_default_options($section);
	
	$allowed_tags = wp_kses_allowed_html('post');
	
	$value = isset($options[$id]) ? wp_kses(stripslashes_deep($options[$id]), $allowed_tags) : '';
	
	echo '<textarea name="'. $setting .'['. $id .']" rows="3" cols="50">'. $value .'</textarea> ';
	echo '<label for="'. $setting .'['. $id .']" class="prismatic-label" >'. $label .'</label>';
	
}

function prismatic_callback_checkbox($args) {
	
	$id      = isset($args['id'])      ? $args['id']      : '';
	$label   = isset($args['label'])   ? $args['label']   : '';
	$section = isset($args['section']) ? $args['section'] : '';
	
	$setting = 'prismatic_options_'. $section;
	
	$options = prismatic_get_default_options($section);
	
	$checked = isset($options[$id]) ? checked($options[$id], 1, false) : '';
	
	echo '<input name="'. $setting .'['. $id .']" value="1" type="checkbox" '. $checked .'> ';
	echo '<label for="'. $setting .'['. $id .']" class="prismatic-label inline">'. $label .'</label>';
	
}

function prismatic_callback_number($args) {
	
	$id      = isset($args['id'])      ? $args['id']      : '';
	$label   = isset($args['label'])   ? $args['label']   : '';
	$section = isset($args['section']) ? $args['section'] : '';
	
	$setting = 'prismatic_options_'. $section;
	
	$options = prismatic_get_default_options($section);
	
	$value = isset($options[$id]) ? sanitize_text_field($options[$id]) : '';
	
	$min = 0;
	$max = 999;
	
	echo '<input name="'. $setting .'['. $id .']" type="number" min="'. $min .'" max="'. $max .'" value="'. $value .'"> ';
	echo '<label for="'. $setting .'['. $id .']" class="prismatic-label inline-block">'. $label .'</label>';
	
}

function prismatic_callback_reset($args) {
	
	$nonce = wp_create_nonce('prismatic_reset_options');
	$url   = admin_url('options-general.php?page=prismatic');
	$href  = esc_url(add_query_arg(array('reset-options-verify' => $nonce), $url));
	
	echo '<a class="prismatic-reset-options" href="'. $href .'">'. esc_html__('Restore default plugin options', 'prismatic') .'</a>';
	
}

function prismatic_callback_rate($args) {
	
	$href  = 'https://wordpress.org/support/plugin/'. PRISMATIC_SLUG .'/reviews/?rate=5#new-post';
	$title = esc_attr__('Help keep Prismatic going strong! A huge THANK YOU for your support!', 'prismatic');
	$text  = isset($args['label']) ? $args['label'] : esc_html__('Show support with a 5-star rating &raquo;', 'prismatic');
	
	echo '<a target="_blank" rel="noopener noreferrer" class="prismatic-rate-plugin" href="'. $href .'" title="'. $title .'">'. $text .'</a>';
	
}

function prismatic_callback_support($args) {
	
	$href  = 'https://monzillamedia.com/donate.html';
	$title = esc_attr__('Donate via PayPal, credit card, or cryptocurrency', 'prismatic');
	$text  = isset($args['label']) ? $args['label'] : esc_html__('Show support with a small donation&nbsp;&raquo;', 'prismatic');
	
	echo '<a target="_blank" rel="noopener noreferrer" class="prismatic-show-support" href="'. $href .'" title="'. $title .'">'. $text .'</a>';
	
}
