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
				// Tab 1: Upload & Import Tool
				array(
					'title'  => __( 'Upload & Import', 'wp-genius' ),
					'icon'   => 'fa-solid fa-file-import',
					'fields' => array(
						array(
							'id'      => '_subheading_novel_document_import',
							'type'    => 'subheading',
							'content' => __( 'Novel Document Import', 'wp-genius' ),
						),
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

				// Tab 2: Fix Chapter Index
				array(
					'title'  => __( 'Fix Chapter Index', 'wp-genius' ),
					'icon'   => 'fa-solid fa-list-ol',
					'fields' => array(
						array(
							'id'      => '_subheading_chapter_index_fix',
							'type'    => 'subheading',
							'content' => __( 'Chapter Index Fix', 'wp-genius' ),
						),
						array(
							'type'    => 'content',
							'content' => ( function () use ( $module_dir ) {
								$view_file = $module_dir . 'views/tab-fix-index.php';
								if ( file_exists( $view_file ) ) {
									ob_start();
									include $view_file;
									return ob_get_clean();
								}
								return '<p class="w2p-error">' . esc_html__( 'View file not found: views/tab-fix-index.php', 'wp-genius' ) . '</p>';
							} )(),
						),
					),
				),
			),
		),
	),
);
