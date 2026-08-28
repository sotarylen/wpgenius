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
				// Tab 1: Scan tool
				array(
					'title'  => __( 'Scan Tool', 'wp-genius' ),
					'icon'   => 'fa-solid fa-magnifying-glass',
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
					'title'  => __( 'Settings', 'wp-genius' ),
					'icon'   => 'fa-solid fa-gear',
					'fields' => array(
						array(
							'id'         => 'actor_scan_post_types',
							'type'       => 'select',
							'title'      => __( 'Scan Content Types', 'wp-genius' ),
							'subtitle'   => __( 'Which post types to scan for actress mentions.', 'wp-genius' ),
							'options'    => 'post_types',
							'query_args' => array(
								'public' => true,
							),
							'default'    => 'post',
							'multiple'   => true,
							'chosen'     => true,
							'attributes' => array(
								'style' => 'width: 100%;',
							),
						),
						array(
							'id'       => 'actor_batch_size',
							'type'     => 'number',
							'title'    => __( 'Batch Size', 'wp-genius' ),
							'subtitle' => __( 'Posts processed per AJAX request.', 'wp-genius' ),
							'default'  => 20,
							'min'      => 1,
							'max'      => 200,
						),
						array(
							'id'       => 'actor_create_new',
							'type'     => 'switcher',
							'title'    => __( 'Auto-create New Actors', 'wp-genius' ),
							'subtitle' => __( 'Create a Humans term when an actress is mentioned but does not exist yet.', 'wp-genius' ),
							'default'  => true,
						),
						array(
							'id'       => 'actor_download_avatar',
							'type'     => 'switcher',
							'title'    => __( 'Download Avatars', 'wp-genius' ),
							'subtitle' => __( 'Fetch the actress avatar from Gfriends into the Media Library when missing.', 'wp-genius' ),
							'default'  => true,
						),
						array(
							'id'       => 'actor_only_unassigned',
							'type'     => 'switcher',
							'title'    => __( 'Only Unassigned Content', 'wp-genius' ),
							'subtitle' => __( 'Only scan posts that have no Humans term yet (faster, avoids re-scanning).', 'wp-genius' ),
							'default'  => true,
						),
						array(
							'id'       => 'actor_append_existing',
							'type'     => 'switcher',
							'title'    => __( 'Append to Existing Terms', 'wp-genius' ),
							'subtitle' => __( 'Add matched actresses to posts that already have Humans terms (instead of replacing).', 'wp-genius' ),
							'default'  => true,
						),
					),
				),
			),
		),
	),
);
