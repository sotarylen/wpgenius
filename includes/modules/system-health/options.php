<?php
/**
 * System Health Module - CSF Options
 *
 * Organizes the tool panels using CSF's native tabbed field (Cleanup / Image Remover / Duplicate / Info),
 * replacing the old custom-drawn w2p-sub-tabs navigation.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$module_dir = plugin_dir_path( __FILE__ );

// Ensure Service is loaded.
if ( file_exists( $module_dir . 'cleanup-service.php' ) ) {
	require_once $module_dir . 'cleanup-service.php';
}

$service    = new SystemHealthCleanupService();
$stats      = array(
	'revisions'     => '-',
	'auto_drafts'   => '-',
	'orphaned_meta' => '-',
	'transients'    => '-',
);
$categories = $service->get_categories();

/**
 * Renders the view fragment of the specified tab.
 *
 * @param string $tab View file name (without .php).
 * @return string
 */
$render_tab = function ( $tab ) use ( $module_dir, $stats, $categories ) {
	ob_start();
	$file = $module_dir . 'views/tab-' . $tab . '.php';
	if ( file_exists( $file ) ) {
		// View fragments use these variables: $stats / $w2p_categories.
		$w2p_categories = $categories;
		include $file;
	} else {
		echo '<div class="w2p-notice w2p-notice-error"><p>' . esc_html__( 'Error: View file not found.', 'wp-genius' ) . '</p></div>';
	}
	return ob_get_clean();
};

return array(
	'module_id' => 'system-health',
	'id'        => 'system_health',
	'title'     => __( 'System Health', 'wp-genius' ),
	'icon'      => 'fa-solid fa-heartbeat',
	'fields'    => array(
		array(
			'type' => 'tabbed',
			'id'   => 'system_health_tabs',
			'tabs' => array(
				array(
					'title'  => __( 'Cleanup Tools', 'wp-genius' ),
					'icon'   => 'fa-solid fa-database',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $render_tab( 'cleanup' ),
						),
					),
				),
				array(
					'title'  => __( 'Image Link Remover', 'wp-genius' ),
					'icon'   => 'fa-solid fa-unlink',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $render_tab( 'image-remover' ),
						),
					),
				),
				array(
					'title'  => __( 'Duplicate Post Clean', 'wp-genius' ),
					'icon'   => 'fa-solid fa-copy',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $render_tab( 'duplicate-cleaner' ),
						),
					),
				),
			),
		),
		// JS dynamically rendered templates (used by AJAX filling such as System Info).
		array(
			'type'    => 'content',
			'content' => ( function () use ( $module_dir ) {
				ob_start();
				$file = $module_dir . 'views/js-templates.php';
				if ( file_exists( $file ) ) {
					include $file;
				}
				return ob_get_clean();
			} )(),
		),
	),
);
