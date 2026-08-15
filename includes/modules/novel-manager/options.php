<?php
/**
 * Novel Manager Module CSF Configuration
 * Mixed Mode: CSF Fields + Content Fields for Tools
 *
 * @package WP_Genius
 * @subpackage Modules
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
				// Tab 1: Upload & Import Tool
				array(
					'title'  => __( 'Upload & Import', 'wp-genius' ),
					'icon'   => 'fa-solid fa-file-word',
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
								return '<p class="w2p-error">' . __( 'View file not found: views/tab-upload.php', 'wp-genius' ) . '</p>';
							} )(),
						),
					),
				),

				// Tab 2: Directory Maintenance Tool
				array(
					'title'  => __( 'Directory Maintenance', 'wp-genius' ),
					'icon'   => 'fa fa-folder',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => ( function () use ( $module_dir ) {
								$view_file = $module_dir . 'views/tab-maintenance.php';
								if ( file_exists( $view_file ) ) {
									ob_start();
									include $view_file;
									return ob_get_clean();
								}
								return '<p class="w2p-error">' . __( 'View file not found: views/tab-maintenance.php', 'wp-genius' ) . '</p>';
							} )(),
						),
					),
				),

				// Tab 3: Fix Chapter Index
				array(
					'title'  => __( 'Fix Chapter Index', 'wp-genius' ),
					'icon'   => 'fa fa-list-ol',
					'fields' => array(
						array(
							'id'      => 'fix_settings_heading',
							'type'    => 'subheading',
							'content' => __( 'Configuration', 'wp-genius' ),
						),
						array(
							'id'         => 'fix_target_post_type',
							'type'       => 'select',
							'title'      => __( 'Target Post Type', 'wp-genius' ),
							'options'    => 'post_types',
							'query_args' => array(
								'public' => true,
							),
							'default'    => 'chapter',
						),
						array(
							'id'       => 'fix_scan_mode',
							'type'     => 'select',
							'title'    => __( 'Scan Mode', 'wp-genius' ),
							'subtitle' => __( 'Choose how to scan chapters', 'wp-genius' ),
							'options'  => array(
								'all'      => __( 'All Chapters', 'wp-genius' ),
								'by_novel' => __( 'By Novel', 'wp-genius' ),
							),
							'default'  => 'all',
						),
						array(
							'id'         => 'fix_novel_id',
							'type'       => 'text',
							'title'      => __( 'Novel ID', 'wp-genius' ),
							'subtitle'   => __( 'Optional: Enter specific novel ID', 'wp-genius' ),
							'dependency' => array( 'fix_scan_mode', '==', 'by_novel' ),
						),
						array(
							'id'         => 'fix_scan_limit',
							'type'       => 'number',
							'title'      => __( 'Scan Limit', 'wp-genius' ),
							'subtitle'   => __( 'Number of recent novels to scan', 'wp-genius' ),
							'default'    => 5,
							'min'        => 1,
							'max'        => 100,
							'dependency' => array( 'fix_scan_mode', '==', 'by_novel' ),
						),
						array(
							'id'       => 'fix_index_format',
							'type'     => 'text',
							'title'    => __( 'Index Format', 'wp-genius' ),
							'subtitle' => __( '"01" = Vol Pad 2, "00001" = Chap Pad 5.', 'wp-genius' ),
							'default'  => '01-00001',
						),
						array(
							'id'         => 'fix_index_connector',
							'type'       => 'text',
							'title'      => __( 'Connector', 'wp-genius' ),
							'default'    => '-',
							'attributes' => array(
								'style' => 'width: 60px;',
							),
						),
						array(
							'id'       => 'fix_auto_volume',
							'type'     => 'switcher',
							'title'    => __( 'Auto Identify Volume', 'wp-genius' ),
							'subtitle' => __( 'Auto identify volume from the content.', 'wp-genius' ),
							'default'  => true,
						),
						array(
							'id'       => 'fix_batch_size',
							'type'     => 'number',
							'title'    => __( 'Batch Size', 'wp-genius' ),
							'subtitle' => __( 'Items per request.', 'wp-genius' ),
							'default'  => 20,
							'min'      => 1,
							'max'      => 100,
						),
						// Tools View
						array(
							'type'    => 'content',
							'content' => ( function () use ( $module_dir ) {
								$view_file = $module_dir . 'views/tab-fix-index.php';
								if ( file_exists( $view_file ) ) {
									ob_start();
									include $view_file;
									return ob_get_clean();
								}
								return '<p class="w2p-error">' . __( 'View file not found: views/tab-fix-index.php', 'wp-genius' ) . '</p>';
							} )(),
						),
					),
				),
			),
		),
	),
);
