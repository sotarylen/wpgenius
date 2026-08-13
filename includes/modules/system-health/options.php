<?php
/**
 * System Health Module - CSF Options
 *
 * 使用 CSF 原生 tabbed 字段组织工具面板（Cleanup / Image Remover / Duplicate / Info），
 * 替代旧的自绘 w2p-sub-tabs 导航。
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
 * 渲染指定 tab 视图片段。
 *
 * @param string $tab 视图文件名（不含 .php）。
 * @return string
 */
$render_tab = function ( $tab ) use ( $module_dir, $stats, $categories ) {
	ob_start();
	$file = $module_dir . 'views/tab-' . $tab . '.php';
	if ( file_exists( $file ) ) {
		// 视图片段使用变量：$stats / $w2p_categories。
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
				array(
					'title'  => __( 'System Info', 'wp-genius' ),
					'icon'   => 'fa-solid fa-circle-info',
					'fields' => array(
						array(
							'type'    => 'content',
							'content' => $render_tab( 'info' ),
						),
					),
				),
			),
		),
		// JS 动态渲染模板（System Info 等 AJAX 填充使用）。
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
