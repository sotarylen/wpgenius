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
		<?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
		<input type="hidden" name="action" value="word2posts_save_module_settings" />
		<input type="hidden" name="module_id" value="clipboard-image-upload" />
		
		<div class="w2p-section">
			<h3><?php esc_html_e( 'Clipboard Image Upload Settings', 'wp-genius' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Configure how clipboard images are handled when pasted into the editor.', 'wp-genius' ); ?></p>
			
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="clipboard_enabled">
								<?php esc_html_e( 'Enable Module', 'wp-genius' ); ?>
							</label>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="clipboard_enabled"
									name="w2p_clipboard_upload_settings[enabled]" 
									value="1"
									<?php checked( $settings['enabled'], 1 ); ?>
								/>
								<?php esc_html_e( 'Allow pasting images directly into the editor and media library.', 'wp-genius' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="image_prefix">
								<?php esc_html_e( 'Image Paste Prefix', 'wp-genius' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="text" 
								id="image_prefix"
								name="w2p_clipboard_upload_settings[image_prefix]" 
								value="<?php echo esc_attr( $settings['image_prefix'] ); ?>"
								class="regular-text"
							/>
							<p class="description"><?php esc_html_e( 'Prefix added to the filename of images uploaded via clipboard (e.g., prefix_uniqueid.png).', 'wp-genius' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			
			<?php submit_button(); ?>
		</div>
	</form>
</div>
