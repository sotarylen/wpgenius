<?php
/**
 * Plugin Name: Smart Auto Upload Images
 * Plugin URI: https://burhandodhy.me
 * Description: A modern WordPress plugin that automatically detects and uploads external images from post content with advanced settings.
 * Version: 1.2.1
 * Author: Burhan Nasir
 * Author URI: https://burhandodhy.me
 * Text Domain: smart-auto-upload-images
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.2
 * Tested up to: 6.8
 * Requires PHP: 8.0
 *
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
} else {
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Smart Auto Upload Images: Autoloader not found. Please run `composer install` to install the dependencies.', 'smart-auto-upload-images' ); ?></p>
		</div>
			<?php
		}
	);

	return;
}

/**
 * Register the container
 *
 * @return \SmartAutoUploadImages\Container
 */
function get_container() {
	static $container = null;

	if ( ! $container ) {
		$container = new \SmartAutoUploadImages\Container();
	}

	return $container;
}

// Initialize the plugin.
add_action(
	'plugins_loaded',
	function () {
		$container = get_container();
		$container->set( 'plugin', new \SmartAutoUploadImages\Plugin() );
		$container->set( 'logger', new \SmartAutoUploadImages\Utils\Logger() );
		$container->set( 'settings_manager', new \SmartAutoUploadImages\Admin\SettingsManager() );
		$container->set( 'image_processor', new \SmartAutoUploadImages\Services\ImageProcessor() );
		$container->set( 'image_downloader', new \SmartAutoUploadImages\Services\ImageDownloader() );
	}
);
