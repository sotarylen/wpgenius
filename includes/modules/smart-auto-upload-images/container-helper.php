<?php
/**
 * Smart Auto Upload Images Container Helper
 * 
 * 在正确的命名空间中定义 get_container 函数
 */

namespace SmartAutoUploadImages;

if ( ! function_exists( 'SmartAutoUploadImages\\get_container' ) ) {
	/**
	 * Get or create the plugin container
	 *
	 * @return Container
	 */
	function get_container() {
		static $container = null;
		
		if ( ! $container ) {
			$container = new Container();
		}
		
		return $container;
	}
}
