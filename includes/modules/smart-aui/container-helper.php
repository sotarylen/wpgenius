<?php
/**
 * Smart Auto Upload Images Container Helper
 *
 * Defines the get_container function in the correct namespace
 */

namespace SmartAutoUploadImages;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


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
