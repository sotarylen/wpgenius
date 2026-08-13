<?php
/**
 * CMS Migrator Options
 *
 * @package WP_Genius
 * @subpackage Modules/CMSMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Helper to get template content.
$get_migrate_content = function () {
	ob_start();
	include plugin_dir_path( __FILE__ ) . 'templates/tab-migrate-inline.php';
	return ob_get_clean();
};

return array(
	'module_id' => 'cms-migrator',
	'id'        => 'cms_migrator',
	'title'     => __( 'CMS Migrator', 'wp-genius' ),
	'icon'      => 'fa fa-database',
	'fields'    => array(
		array(
			'type'    => 'content',
			'content' => $get_migrate_content(),
		),
	),
);
