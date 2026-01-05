<?php
/**
 * Clipboard Image Upload Module Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'w2p_clipboard_upload_settings', [] );
$defaults = [
	'enabled' => true,
	'image_prefix' => 'clipboard_',
];
$settings = wp_parse_args( $settings, $defaults );
?>

<div class="w2p-settings-panel">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_clipboard_upload_nonce' ); ?>
		<input type="hidden" name="action" value="word2posts_save_module_settings" />
		<input type="hidden" name="module_id" value="clipboard-image-upload" />
		
			<div class="w2p-section-header">
				<h4><?php esc_html_e( 'Clipboard Upload Settings', 'wp-genius' ); ?></h4>
			</div>
			<div class="w2p-section-body">
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="clipboard_enabled">
							<?php esc_html_e( 'Enable Module', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="clipboard_enabled" name="w2p_clipboard_upload_settings[enabled]" value="1" <?php checked( $settings['enabled'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description"><?php esc_html_e( 'Allow pasting images directly into the editor and media library.', 'wp-genius' ); ?></p>
					</div>
				</div>

				<div class="w2p-form-row border-none">
					<div class="w2p-form-label">
						<label for="image_prefix">
							<?php esc_html_e( 'Image Paste Prefix', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<input 
							type="text" 
							id="image_prefix"
							name="w2p_clipboard_upload_settings[image_prefix]" 
							value="<?php echo esc_attr( $settings['image_prefix'] ); ?>"
							class="w2p-input-medium"
						/>
						<p class="description"><?php esc_html_e( 'Prefix added to the filename of images uploaded via clipboard (e.g., prefix_uniqueid.png).', 'wp-genius' ); ?></p>
					</div>
				</div>
			</div>
			
			<div class="w2p-settings-actions">
				<button type="submit" name="submit" id="w2p-clipboard-upload-submit" class="w2p-btn w2p-btn-primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Clipboard Settings', 'wp-genius' ); ?>
				</button>
			</div>
	</form>
	<script>
		jQuery(document).ready(function($) {
			const urlParams = new URLSearchParams(window.location.search);
			if (urlParams.get('settings-updated') === 'true') {
				const $btn = $('#w2p-clipboard-upload-submit');
				if (window.WPGenius && WPGenius.UI) {
					WPGenius.UI.showFeedback($btn, '<?php esc_js( __( 'Settings Saved', 'wp-genius' ) ); ?>', 'success');
				}
			}
		});
	</script>
</div>
