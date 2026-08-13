<?php
/**
 * AI Engine Module Options
 *
 * CSF Settings Configuration - Integrated as tab in WP Genius Settings
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load provider classes if not already loaded.
$ai_engine_path = __DIR__;
if ( ! class_exists( 'AI_Provider_Interface' ) ) {
	require_once $ai_engine_path . '/providers/class-provider-interface.php';
	require_once $ai_engine_path . '/providers/class-openai.php';
	require_once $ai_engine_path . '/providers/class-anthropic.php';
	require_once $ai_engine_path . '/providers/class-gemini.php';
	require_once $ai_engine_path . '/providers/class-deepseek.php';
}

// Build providers list for dropdowns.
$ai_providers = array(
	array(
		'slug' => 'openai',
		'name' => 'OpenAI',
	),
	array(
		'slug' => 'anthropic',
		'name' => 'Anthropic',
	),
	array(
		'slug' => 'gemini',
		'name' => 'Google Gemini',
	),
	array(
		'slug' => 'deepseek',
		'name' => 'DeepSeek',
	),
);

// Build prompts list.
global $wpdb;
$ai_prompts_table = $wpdb->prefix . 'w2p_ai_prompts';
$ai_prompts       = array();
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ai_prompts_table}'" ) === $ai_prompts_table ) {
	$ai_prompts = $wpdb->get_results( "SELECT id, name FROM {$ai_prompts_table} ORDER BY name ASC", ARRAY_A ) ?: array();
}

// Helper to get generate tab content.
$get_generate_content = function () use ( $ai_providers, $ai_prompts ) {
	ob_start();
	include plugin_dir_path( __FILE__ ) . 'templates/tab-generate-inline.php';
	return ob_get_clean();
};

// Helper to get prompts tab content.
$get_prompts_content = function () use ( $ai_prompts ) {
	ob_start();
	include plugin_dir_path( __FILE__ ) . 'templates/tab-prompts-inline.php';
	return ob_get_clean();
};

// Helper to get schedules tab content.
$get_schedules_content = function () use ( $ai_prompts ) {
	ob_start();
	include plugin_dir_path( __FILE__ ) . 'templates/tab-schedules-inline.php';
	return ob_get_clean();
};

// Helper to get queue tab content.
$get_queue_content = function () {
	ob_start();
	include plugin_dir_path( __FILE__ ) . 'templates/tab-queue-inline.php';
	return ob_get_clean();
};

// Helper to get settings tab content.
$get_settings_content = function () {
	ob_start();
	include plugin_dir_path( __FILE__ ) . 'templates/tab-settings-inline.php';
	return ob_get_clean();
};

return array(
	'module_id' => 'ai-engine',
	'id'        => 'ai_engine',
	'title'     => __( 'AI Content Engine', 'wp-genius' ),
	'icon'      => 'fa fa-robot',
	'fields'    => array(
		array(
			'id'   => 'ai_engine_tabs',
			'type' => 'tabbed',
			'tabs' => array(
				// Tab 1: Generate.
				array(
					'title'  => __( 'Generate', 'wp-genius' ),
					'icon'   => 'fa fa-magic',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $get_generate_content(),
						),
					),
				),
				// Tab 2: Prompts.
				array(
					'title'  => __( 'Prompts', 'wp-genius' ),
					'icon'   => 'fa fa-file-alt',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $get_prompts_content(),
						),
					),
				),
				// Tab 3: Schedules.
				array(
					'title'  => __( 'Schedules', 'wp-genius' ),
					'icon'   => 'fa fa-clock',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $get_schedules_content(),
						),
					),
				),
				// Tab 4: Queue.
				array(
					'title'  => __( 'Queue', 'wp-genius' ),
					'icon'   => 'fa fa-list',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $get_queue_content(),
						),
					),
				),
				// Tab 5: Settings.
				array(
					'title'  => __( 'Settings', 'wp-genius' ),
					'icon'   => 'fa fa-cog',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $get_settings_content(),
						),
					),
				),
			),
		),
	),
);
