<?php
/**
 * Media Engine Module Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="w2p-settings-panel">

	<!-- Sub-tabs Navigation -->
	<div class="w2p-sub-tabs" id="w2p-media-engine-tabs">
		<div class="w2p-sub-tab-nav">
			<a class="w2p-sub-tab-link active" data-tab="turbo">
				<i class="fa-solid fa-rocket"></i>
				<?php esc_html_e( 'Format Conversion', 'wp-genius' ); ?>
			</a>
			<a class="w2p-sub-tab-link" data-tab="clipboard">
				<i class="fa-solid fa-paste"></i>
				<?php esc_html_e( 'Clipboard Upload', 'wp-genius' ); ?>
			</a>
		</div>
		
		<!-- Tab 2: Media Turbo -->
		<div class="w2p-sub-tab-content active" id="w2p-tab-turbo">
			<?php
			$turbo_settings_path = plugin_dir_path( __FILE__ ) . 'views/turbo-settings.php';
			if ( file_exists( $turbo_settings_path ) ) {
				include $turbo_settings_path;
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Format conversion settings file not found.', 'wp-genius' ) . '</p></div>';
			}
			?>
		</div>
		
		<!-- Tab 3: Clipboard Upload -->
		<div class="w2p-sub-tab-content" id="w2p-tab-clipboard">
			<?php
			$clipboard_settings_path = plugin_dir_path( __FILE__ ) . 'views/clipboard-settings.php';
			if ( file_exists( $clipboard_settings_path ) ) {
				include $clipboard_settings_path;
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Clipboard upload settings file not found.', 'wp-genius' ) . '</p></div>';
			}
			?>
		</div>
	</div>
</div>
