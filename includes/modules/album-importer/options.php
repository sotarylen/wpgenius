<?php
/**
 * Album Importer Module CSF Configuration
 *
 * 只有一页：导入工作台。浏览根不再作为设置项——它必须等于 php 容器里的只读挂载点
 * （见 W2P_Album_Importer::BROWSE_ROOT），做成可配置只会多出一个能配错的安全边界。
 *
 * @package WP_Genius
 * @subpackage Modules/AlbumImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$module_dir = plugin_dir_path( __FILE__ );

return array(
	'module_id' => 'album-importer',
	'id'        => 'album_importer',
	'title'     => __( 'Album Importer', 'wp-genius' ),
	'icon'      => 'fa-solid fa-images',
	'fields'    => array(
		array(
			'type'    => 'content',
			'content' => ( function () use ( $module_dir ) {
				$view_file = $module_dir . 'views/tab-import.php';
				if ( file_exists( $view_file ) ) {
					ob_start();
					include $view_file;
					return ob_get_clean();
				}
				return '<p class="w2p-error">' . esc_html__( 'View file not found: views/tab-import.php', 'wp-genius' ) . '</p>';
			} )(),
		),
	),
);
