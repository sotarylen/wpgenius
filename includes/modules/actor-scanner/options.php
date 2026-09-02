<?php
/**
 * Actor Scanner Module CSF Configuration
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$module_dir = plugin_dir_path( __FILE__ );

return array(
	'module_id' => 'actor-scanner',
	'id'        => 'actor_scanner',
	'title'     => __( 'Actor Scanner', 'wp-genius' ),
	'icon'      => 'fa-solid fa-user-tag',
	'fields'    => array(
		array(
			'id'   => 'actor_scanner_tabs',
			'type' => 'tabbed',
			'tabs' => array(
				// Tab 1: Deduplication & Governance Tool
				array(
					'title'  => __( 'Actor Governance', 'wp-genius' ),
					'icon'   => 'fa-solid fa-code-merge',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => ( function () use ( $module_dir ) {
								$view_file = $module_dir . 'views/tab-scanner.php';
								if ( file_exists( $view_file ) ) {
									ob_start();
									include $view_file;
									return ob_get_clean();
								}
								return '<p class="w2p-error">' . __( 'View file not found: views/tab-scanner.php', 'wp-genius' ) . '</p>';
							} )(),
						),
					),
				),

				// Tab 2: Settings
				array(
					'title'  => __( 'General Settings', 'wp-genius' ),
					'icon'   => 'fa-solid fa-gear',
					'fields' => array(
						array(
							'id'       => 'actor_manual_detect',
							'type'     => 'switcher',
							'title'    => __( 'Enable Actor Detection', 'wp-genius' ),
							'subtitle' => __( 'Add an "Identify Actor" button in the post editor Humans meta box and bulk actions in post list screens.', 'wp-genius' ),
							'default'  => true,
						),
					),
				),
			),
		),
	),
);
