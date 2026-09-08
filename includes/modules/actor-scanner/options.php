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

if ( ! function_exists( 'w2p_actor_scanner_render_tab_scanner' ) ) {
	/**
	 * Render Actor Scanner Gfriends Index Tab.
	 */
	function w2p_actor_scanner_render_tab_scanner() {
		$view_file = plugin_dir_path( __FILE__ ) . 'views/tab-scanner.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		}
	}
}

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
				// Tab 1: Gfriends Data Source Status & Sync
				array(
					'title'  => __( 'Gfriends Index', 'wp-genius' ),
					'icon'   => 'fa-solid fa-cloud-arrow-down',
					'fields' => array(
						array(
							'type'     => 'callback',
							'function' => 'w2p_actor_scanner_render_tab_scanner',
						),
					),
				),

				// Tab 2: General Settings
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
