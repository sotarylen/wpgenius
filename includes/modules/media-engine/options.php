<?php
/**
 * Media Engine Module CSF Configuration
 * Mixed Mode: CSF Fields for Settings + Content Fields for Tools
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$module_dir = plugin_dir_path( __FILE__ );

return array(
	'module_id' => 'media-engine',
	'id'        => 'media_engine',
	'title'     => __( 'Media Engine', 'wp-genius' ),
	'icon'      => 'fa-solid fa-image',
	'fields'    => array(
		array(
			'id'   => 'media_engine_tabs',
			'type' => 'tabbed',
			'tabs' => array(
				// Tab 1: Format Conversion - Settings (Real CSF Fields)
				array(
					'title'  => __( 'Conversion Settings', 'wp-genius' ),
					'icon'   => 'fa fa-cog',
					'fields' => array(
						array(
							'id'      => '_subheading_webp_conversion',
							'type'    => 'subheading',
							'content' => __( 'WebP Conversion', 'wp-genius' ),
						),
						array(
							'id'      => 'keep_original',
							'type'    => 'switcher',
							'title'   => __( 'Keep Original', 'wp-genius' ),
							'label'   => __( 'If disabled, original JPG/PNG/GIF files will be deleted after conversion.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => '_subheading_scan_settings',
							'type'    => 'subheading',
							'content' => __( 'Scan Settings', 'wp-genius' ),
						),
						array(
							'id'       => 'scan_limit',
							'type'     => 'slider',
							'title'    => __( 'Scan Limit', 'wp-genius' ),
							'label'    => __( 'Maximum number of media items to scan (1-5000).', 'wp-genius' ),
							'subtitle' => __( 'Number of media items to scan in each execution.', 'wp-genius' ),
							'min'      => 100,
							'max'      => 5000,
							'step'     => 100,
							'default'  => 100,
						),
						array(
							'id'       => 'batch_size',
							'type'     => 'slider',
							'title'    => __( 'Batch Size', 'wp-genius' ),
							'label'    => __( 'Items per batch (1-50).', 'wp-genius' ),
							'subtitle' => __( 'Number of media items to process in each execution.', 'wp-genius' ),
							'default'  => 10,
							'min'      => 5,
							'max'      => 50,
							'step'     => 5,
						),
					),
				),

				// Tab 2: Format Conversion - Tools (Content Field)
				array(
					'title'  => __( 'Batch Processing', 'wp-genius' ),
					'icon'   => 'fa fa-rocket',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => ( function () use ( $module_dir ) {
								$turbo_path = $module_dir . 'views/turbo-settings.php';
								if ( file_exists( $turbo_path ) ) {
									// Include the full turbo-settings view
									// The settings form at the top is read-only now since we use CSF fields
									ob_start();
									include $turbo_path;
									return ob_get_clean();
								}
								return '<div class="w2p-info-box"><p>' . __( 'Batch processing tools not available.', 'wp-genius' ) . '</p></div>';
							} )(),
						),
					),
				),

				// Tab 3: Environment Check (Content Field)
				array(
					'title'  => __( 'Environment Check', 'wp-genius' ),
					'icon'   => 'fa fa-stethoscope',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => ( function () use ( $module_dir ) {
								$env_path = $module_dir . 'views/environment-settings.php';
								if ( file_exists( $env_path ) ) {
									ob_start();
									include $env_path;
									return ob_get_clean();
								}
								return '<p>' . __( 'Environment check not available.', 'wp-genius' ) . '</p>';
							} )(),
						),
					),
				),

				// Tab 4: Clipboard Upload (Content Field)
				array(
					'title'  => __( 'Clipboard Upload', 'wp-genius' ),
					'icon'   => 'fa fa-paste',
					'fields' => array(
						array(
							'id'      => 'clipboard_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Module', 'wp-genius' ),
							'label'   => __( 'Allow pasting images directly into the editor and media library.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'         => 'clipboard_prefix',
							'type'       => 'text',
							'title'      => __( 'Image Paste Prefix', 'wp-genius' ),
							'label'      => __( 'Prefix added to the filename of images uploaded via clipboard (e.g., prefix_uniqueid.png).', 'wp-genius' ),
							'default'    => 'clipboard_',
							'dependency' => array( 'clipboard_enabled', '==', 'true' ),
						),
					),
				),

				// Tab 5: Residual Media Audit (Content Field)
				array(
					'title'  => __( '残留媒体审计', 'wp-genius' ),
					'icon'   => 'fa fa-search',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => ( function () use ( $module_dir ) {
								$audit_path = $module_dir . 'views/audit-settings.php';
								if ( file_exists( $audit_path ) ) {
									ob_start();
									include $audit_path;
									return ob_get_clean();
								}
								return '<p>' . __( 'Residual media audit not available.', 'wp-genius' ) . '</p>';
							} )(),
						),
					),
				),
			),
		),
	),
);
