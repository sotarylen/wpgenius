<?php // Prismatic - Register Settings

if (!defined('ABSPATH')) exit;

function prismatic_register_settings() {
	
	
	// register_setting( $option_group, $option_name, $sanitize_callback )
	register_setting('prismatic_options_general', 'prismatic_options_general', 'prismatic_validate_general');
	
	// add_settings_section( $id, $title, $callback, $page )
	add_settings_section('settings_general', 'General settings', 'prismatic_section_general', 'prismatic_options_general');
	
	// add_settings_field( $id, $title, $callback, $page, $section, $args )
	add_settings_field('library',              'Library',        'prismatic_callback_select',   'prismatic_options_general', 'settings_general', array('id' => 'library',              'section' => 'general', 'label' => esc_html__('', 'prismatic')));
	add_settings_field('disable_block_styles', 'Block Styles',   'prismatic_callback_checkbox', 'prismatic_options_general', 'settings_general', array('id' => 'disable_block_styles', 'section' => 'general', 'label' => esc_html__('Disable the Prismatic block stylesheet on the frontend', 'prismatic')));
	add_settings_field('null_reset_options',   'Reset Options',  'prismatic_callback_reset',    'prismatic_options_general', 'settings_general', array('id' => 'null_reset_options',   'section' => 'general', 'label' => esc_html__('Restore default options', 'prismatic')));
	add_settings_field('null_rate_plugin',     'Rate Plugin',    'prismatic_callback_rate',     'prismatic_options_general', 'settings_general', array('id' => 'null_rate_plugin',     'section' => 'general', 'label' => esc_html__('Show support with a 5-star rating &raquo;', 'prismatic')));
	add_settings_field('null_show_support',    'Show Support',   'prismatic_callback_support',  'prismatic_options_general', 'settings_general', array('id' => 'null_show_support',    'section' => 'general', 'label' => esc_html__('Show support with a small donation&nbsp;&raquo;', 'prismatic')));
	
	// Prism
	register_setting('prismatic_options_prism', 'prismatic_options_prism', 'prismatic_validate_prism');
	
	add_settings_section('settings_prism', 'Prism.js settings', 'prismatic_section_prism', 'prismatic_options_prism');
	
	add_settings_field('prism_theme',    'Theme',             'prismatic_callback_select',   'prismatic_options_prism', 'settings_prism', array('id' => 'prism_theme',    'section' => 'prism', 'label' => esc_html__('Prism theme', 'prismatic')));
	add_settings_field('line_highlight', 'Line Highlight',    'prismatic_callback_checkbox', 'prismatic_options_prism', 'settings_prism', array('id' => 'line_highlight', 'section' => 'prism', 'label' => esc_html__('Enable', 'prismatic') .' <a target="_blank" rel="noopener noreferrer" href="https://prismjs.com/plugins/line-highlight/">'.    esc_html__('Line Highlight',    'prismatic') .'</a>'));
	add_settings_field('line_numbers',   'Line Numbers',      'prismatic_callback_checkbox', 'prismatic_options_prism', 'settings_prism', array('id' => 'line_numbers',   'section' => 'prism', 'label' => esc_html__('Enable', 'prismatic') .' <a target="_blank" rel="noopener noreferrer" href="https://prismjs.com/plugins/line-numbers/">'.      esc_html__('Line Numbers',      'prismatic') .'</a>'));
	add_settings_field('show_language',  'Show Language',     'prismatic_callback_checkbox', 'prismatic_options_prism', 'settings_prism', array('id' => 'show_language',  'section' => 'prism', 'label' => esc_html__('Enable', 'prismatic') .' <a target="_blank" rel="noopener noreferrer" href="https://prismjs.com/plugins/show-language/">'.     esc_html__('Show Language',     'prismatic') .'</a>'));
	add_settings_field('copy_clipboard', 'Copy to Clipboard', 'prismatic_callback_checkbox', 'prismatic_options_prism', 'settings_prism', array('id' => 'copy_clipboard', 'section' => 'prism', 'label' => esc_html__('Enable', 'prismatic') .' <a target="_blank" rel="noopener noreferrer" href="https://prismjs.com/plugins/copy-to-clipboard/">'. esc_html__('Copy to Clipboard', 'prismatic') .'</a>'));
	add_settings_field('command_line',   'Command Line',      'prismatic_callback_checkbox', 'prismatic_options_prism', 'settings_prism', array('id' => 'command_line',   'section' => 'prism', 'label' => esc_html__('Enable', 'prismatic') .' <a target="_blank" rel="noopener noreferrer" href="https://prismjs.com/plugins/command-line/">'.      esc_html__('Command Line',      'prismatic') .'</a>'));
	add_settings_field('singular_only',  'Limit to Posts',    'prismatic_callback_checkbox', 'prismatic_options_prism', 'settings_prism', array('id' => 'singular_only',  'section' => 'prism', 'label' => esc_html__('Limit to Posts and Pages', 'prismatic')));
	
	add_settings_section('settings_prism_code', 'Code Escaping', 'prismatic_section_prism_code', 'prismatic_options_prism');
	
	add_settings_field('filter_content',  'Post Content',  'prismatic_callback_select', 'prismatic_options_prism', 'settings_prism_code', array('id' => 'filter_content',  'section' => 'prism', 'label' => esc_html__('Escape code for post content in the Admin Area', 'prismatic')));
	add_settings_field('filter_excerpts', 'Post Excerpts', 'prismatic_callback_select', 'prismatic_options_prism', 'settings_prism_code', array('id' => 'filter_excerpts', 'section' => 'prism', 'label' => esc_html__('Escape code for post excerpts in the Admin Area', 'prismatic')));
	add_settings_field('filter_comments', 'Post Comments', 'prismatic_callback_select', 'prismatic_options_prism', 'settings_prism_code', array('id' => 'filter_comments', 'section' => 'prism', 'label' => esc_html__('Escape code for post comments in the Admin Area', 'prismatic')));
	
	
	// Highlight
	register_setting('prismatic_options_highlight', 'prismatic_options_highlight', 'prismatic_validate_highlight');
	
	add_settings_section('settings_highlight', 'Highlight.js settings', 'prismatic_section_highlight', 'prismatic_options_highlight');
	
	add_settings_field('highlight_theme',  'Theme',          'prismatic_callback_select',   'prismatic_options_highlight', 'settings_highlight', array('id' => 'highlight_theme',  'section' => 'highlight', 'label' => esc_html__('Highlight theme', 'prismatic')));
	add_settings_field('init_javascript',  'Init Script',    'prismatic_callback_textarea', 'prismatic_options_highlight', 'settings_highlight', array('id' => 'init_javascript',  'section' => 'highlight', 'label' => esc_html__('Init script for Highlight.js (required)', 'prismatic')));
	add_settings_field('noprefix_classes', 'No Prefixes',    'prismatic_callback_checkbox', 'prismatic_options_highlight', 'settings_highlight', array('id' => 'noprefix_classes', 'section' => 'highlight', 'label' => esc_html__('Support no-prefix class names', 'prismatic')));
	add_settings_field('singular_only',    'Limit to Posts', 'prismatic_callback_checkbox', 'prismatic_options_highlight', 'settings_highlight', array('id' => 'singular_only',    'section' => 'highlight', 'label' => esc_html__('Limit to Posts and Pages', 'prismatic')));
	
	add_settings_section('settings_highlight_code', 'Code Escaping', 'prismatic_section_highlight_code', 'prismatic_options_highlight');
	
	add_settings_field('filter_content',  'Content',  'prismatic_callback_select', 'prismatic_options_highlight', 'settings_highlight_code', array('id' => 'filter_content',  'section' => 'highlight', 'label' => esc_html__('Escape code for post content in the Admin Area', 'prismatic')));
	add_settings_field('filter_excerpts', 'Excerpts', 'prismatic_callback_select', 'prismatic_options_highlight', 'settings_highlight_code', array('id' => 'filter_excerpts', 'section' => 'highlight', 'label' => esc_html__('Escape code for post excerpts in the Admin Area', 'prismatic')));
	add_settings_field('filter_comments', 'Comments', 'prismatic_callback_select', 'prismatic_options_highlight', 'settings_highlight_code', array('id' => 'filter_comments', 'section' => 'highlight', 'label' => esc_html__('Escape code for post comments in the Admin Area', 'prismatic')));
	
	
	// Plain
	register_setting('prismatic_options_plain', 'prismatic_options_plain', 'prismatic_validate_plain');
	
	add_settings_section('settings_plain', 'Code Escaping', 'prismatic_section_plain', 'prismatic_options_plain');
	
	add_settings_field('filter_content',  'Content',  'prismatic_callback_select', 'prismatic_options_plain', 'settings_plain', array('id' => 'filter_content',  'section' => 'plain', 'label' => esc_html__('Escape code for post content in the Admin Area', 'prismatic')));
	add_settings_field('filter_excerpts', 'Excerpts', 'prismatic_callback_select', 'prismatic_options_plain', 'settings_plain', array('id' => 'filter_excerpts', 'section' => 'plain', 'label' => esc_html__('Escape code for post excerpts in the Admin Area', 'prismatic')));
	add_settings_field('filter_comments', 'Comments', 'prismatic_callback_select', 'prismatic_options_plain', 'settings_plain', array('id' => 'filter_comments', 'section' => 'plain', 'label' => esc_html__('Escape code for post comments in the Admin Area', 'prismatic')));
	
	
	// Advanced
	register_setting('prismatic_options_advanced', 'prismatic_options_advanced', 'prismatic_validate_advanced');
	
	add_settings_section('settings_advanced', 'Advanced Settings', 'prismatic_section_advanced', 'prismatic_options_advanced');
	
	add_settings_field('custom_style',  'Custom CSS', 'prismatic_callback_textarea', 'prismatic_options_advanced', 'settings_advanced', array('id' => 'custom_style', 'section' => 'advanced', 'label' => esc_html__('Optional CSS to include on all pages (do *not* include &lt;style&gt; tags)', 'prismatic')));
	
}
