<?php
/**
 * Utils
 *
 * @package SmartAutoUploadImages
 */

namespace SmartAutoUploadImages\Utils;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get asset info from extracted asset files
 *
 * @param string $slug Asset slug as defined in build/webpack configuration
 * @param string $attribute Optional attribute to get. Can be version or dependencies
 * @return string|array
 */
function get_asset_info( $slug, $attribute = null ) {
	if ( file_exists( SMART_AUI_PLUGIN_DIR . 'dist/js/' . $slug . '.asset.php' ) ) {
		$asset = require SMART_AUI_PLUGIN_DIR . 'dist/js/' . $slug . '.asset.php';
	} elseif ( file_exists( SMART_AUI_PLUGIN_DIR . 'dist/css/' . $slug . '.asset.php' ) ) {
		$asset = require SMART_AUI_PLUGIN_DIR . 'dist/css/' . $slug . '.asset.php';
	} else {
		return null;
	}

	if ( ! empty( $attribute ) && isset( $asset[ $attribute ] ) ) {
		return $asset[ $attribute ];
	}

	return $asset;
}
