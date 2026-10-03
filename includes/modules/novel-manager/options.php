<?php
/**
 * Novel Manager Module CSF Configuration
 *
 * @package WP_Genius
 * @subpackage Modules/NovelManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$module_dir = plugin_dir_path( __FILE__ );

return array(
	'module_id' => 'novel-manager',
	'id'        => 'novel_manager',
	'title'     => __( 'Novel Manager', 'wp-genius' ),
	'icon'      => 'fa-solid fa-book',
	'fields'    => array(
		array(
			'id'   => 'novel_manager_tabs',
			'type' => 'tabbed',
			'tabs' => array(
				// Tab 1: Novel Manager & Import
				array(
					'title'  => __( 'Novel Manager & Import', 'wp-genius' ),
					'icon'   => 'fa-solid fa-file-import',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => ( function () use ( $module_dir ) {
								$view_file = $module_dir . 'views/tab-upload.php';
								if ( file_exists( $view_file ) ) {
									ob_start();
									include $view_file;
									return ob_get_clean();
								}
								return '<p class="w2p-error">' . esc_html__( 'View file not found: views/tab-upload.php', 'wp-genius' ) . '</p>';
							} )(),
						),
					),
				),

				// Tab 2: General Settings
				array(
					'title'  => __( 'General Settings', 'wp-genius' ),
					'icon'   => 'fa-solid fa-gear',
					'fields' => array(
						array(
							'id'      => 'novel_delete_with_chapters',
							'type'    => 'switcher',
							'title'   => __( 'Delete with Chapters', 'wp-genius' ),
							'label'   => __( 'Adds a "Delete w/ Chapters" action to the novel list to cascade delete novel and all its chapters.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'novel_enable_bracket_chapters',
							'type'    => 'switcher',
							'title'   => __( 'Bracket Chapter Numbers', 'wp-genius' ),
							'label'   => __( 'Recognize formats like "Title (一)" or "Title (1)" with full-width and half-width brackets.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'       => 'novel_custom_chapter_rules',
							'type'     => 'repeater',
							'title'    => __( 'Custom Chapter Rules', 'wp-genius' ),
							'subtitle' => __( 'Supplement custom patterns for chapter titles not covered by built-in rules.', 'wp-genius' ),
							'fields'   => array(
								array(
									'id'    => 'rule_pattern',
									'type'  => 'text',
									'title' => __( 'Pattern or Keyword', 'wp-genius' ),
									'desc'  => __( 'Enter prefix or regex pattern (e.g. /^Scene\s*(\d+)/u).', 'wp-genius' ),
								),
								array(
									'id'    => 'rule_name',
									'type'  => 'text',
									'title' => __( 'Rule Description', 'wp-genius' ),
									'desc'  => __( 'Short note or sample title for this rule.', 'wp-genius' ),
								),
							),
						),
						array(
							'id'       => 'novel_special_volumes',
							'type'     => 'repeater',
							'title'    => __( 'Special Volume Rules', 'wp-genius' ),
							'subtitle' => __( 'Define keywords and index numbering rules for side stories, prequels, and extra volumes.', 'wp-genius' ),
							'fields'   => array(
								array(
									'id'    => 'keyword',
									'type'  => 'text',
									'title' => __( 'Volume Keyword', 'wp-genius' ),
									'desc'  => __( 'e.g. 外传, 前传, 后传, 番外, 特别篇', 'wp-genius' ),
								),
								array(
									'id'      => 'policy',
									'type'    => 'select',
									'title'   => __( 'Index Policy', 'wp-genius' ),
									'options' => array(
										'append' => __( 'Append after main volumes', 'wp-genius' ),
										'fixed'  => __( 'Fixed volume index number', 'wp-genius' ),
										'zero'   => __( 'Index 0 (Prequel/Prologue)', 'wp-genius' ),
									),
									'default' => 'append',
								),
								array(
									'id'         => 'fixed_idx',
									'type'       => 'number',
									'title'      => __( 'Fixed Index Value', 'wp-genius' ),
									'default'    => 99,
									'dependency' => array( 'policy', '==', 'fixed' ),
								),
							),
						),
					),
				),
				// Tab 3: Statistics Calibration
				array(
					'title'  => __( 'Statistics', 'wp-genius' ),
					'icon'   => 'fa-solid fa-calculator',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => ( function () use ( $module_dir ) {
								$view_file = $module_dir . 'views/tab-stats.php';
								if ( file_exists( $view_file ) ) {
									ob_start();
									include $view_file;
									return ob_get_clean();
								}
								return '<p class="w2p-error">' . esc_html__( 'View file not found: views/tab-stats.php', 'wp-genius' ) . '</p>';
							} )(),
						),
					),
				),
			),
		),
	),
);
