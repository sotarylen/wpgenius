<?php
/**
 * Smart Auto Upload Images Configuration Hooks
 * 
 * Modifies the original plugin's behavior to fix issues
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 增加HTTP请求超时时间
add_filter( 'http_request_timeout', function( $timeout, $url ) {
	// 只对图片请求增加超时
	if ( preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url ) ) {
		return 30; // 30秒
	}
	return $timeout;
}, 10, 2 );

// 修改wp_remote_get的参数
add_filter( 'http_request_args', function( $args, $url ) {
	// 只对图片请求修改
	if ( preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url ) ) {
		$args['timeout'] = 30;
		$args['sslverify'] = false;
		
		if ( ! isset( $args['headers'] ) ) {
			$args['headers'] = [];
		}
		
		// 添加User-Agent避免被拒绝
		if ( ! isset( $args['headers']['User-Agent'] ) ) {
			$args['headers']['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
		}
	}
	
	return $args;
}, 10, 2 );

// 增加WordPress的最大执行时间
add_filter( 'wp_php_timeout', function( $timeout ) {
	// 在保存文章时增加超时时间
	if ( isset( $_POST['action'] ) && in_array( $_POST['action'], [ 'editpost', 'inline-save' ] ) ) {
		return 300; // 5分钟
	}
	return $timeout;
});

// 禁用WordPress的心跳检测，避免在处理图片时被中断
add_action( 'admin_enqueue_scripts', function() {
	global $pagenow;
	if ( in_array( $pagenow, [ 'post.php', 'post-new.php' ] ) ) {
		// 延长心跳间隔
		add_filter( 'heartbeat_settings', function( $settings ) {
			$settings['interval'] = 60; // 60秒
			return $settings;
		});
	}
});