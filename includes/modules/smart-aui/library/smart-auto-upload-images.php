<?php
/**
 * @package SmartAutoUploadImages
 */

namespace SmartAutoUploadImages;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'SMART_AUI_VERSION', '1.2.1' );
define( 'SMART_AUI_PLUGIN_FILE', __FILE__ );
define( 'SMART_AUI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMART_AUI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMART_AUI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );


// Autoload classes.
if ( file_exists( SMART_AUI_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SMART_AUI_PLUGIN_DIR . 'vendor-prefixed/autoload.php';
	require_once SMART_AUI_PLUGIN_DIR . 'vendor/autoload.php';
	require_once SMART_AUI_PLUGIN_DIR . 'src/utils.php';
}

