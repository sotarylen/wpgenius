<?php
/**
 * Smart Auto Upload Images Module Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'smart_aui_settings', [] );
$defaults = [
	'auto_set_featured_image' => true,
	'show_progress_ui' => true,
];
$settings = wp_parse_args( $settings, $defaults );
?>

<div class="w2p-settings-panel w2p-smart-aui-settings">
	<!-- WP Genius Enhanced Features -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="w2p-smart-aui-wp-genius-form">
		<?php wp_nonce_field( 'word2posts_save_module_settings', 'word2posts_module_nonce' ); ?>
		<input type="hidden" name="action" value="word2posts_save_module_settings" />
		<input type="hidden" name="module_id" value="smart-auto-upload-images" />
		
		<div class="w2p-smart-aui-wp-genius-section">
			<h3><?php esc_html_e( 'WP Genius Enhanced Features', 'wp-genius' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Additional features provided by WP Genius module', 'wp-genius' ); ?></p>
			
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="auto_set_featured_image">
								<?php esc_html_e( 'Auto Set Featured Image', 'wp-genius' ); ?>
							</label>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="auto_set_featured_image"
									name="smart_aui_settings[auto_set_featured_image]" 
									value="1"
									<?php checked( $settings['auto_set_featured_image'], 1 ); ?>
								/>
								<?php esc_html_e( 'Automatically set the first image as featured image if none exists', 'wp-genius' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="show_progress_ui">
								<?php esc_html_e( 'Show Upload Progress', 'wp-genius' ); ?>
							</label>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="show_progress_ui"
									name="smart_aui_settings[show_progress_ui]" 
									value="1"
									<?php checked( $settings['show_progress_ui'], 1 ); ?>
								/>
								<?php esc_html_e( 'Display a progress bar when saving posts with external images', 'wp-genius' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="process_images_on_rest_api">
								<?php esc_html_e( 'Process Images on REST API', 'wp-genius' ); ?>
							</label>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="process_images_on_rest_api"
									name="smart_aui_settings[process_images_on_rest_api]" 
									value="1"
									<?php checked( $settings['process_images_on_rest_api'] ?? true, 1 ); ?>
								/>
								<?php esc_html_e( 'Automatically upload remote images when content is created via REST API (e.g., from n8n)', 'wp-genius' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Disable this if you want to skip automatic image processing when creating posts programmatically through external APIs.', 'wp-genius' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			
			<?php submit_button( __( 'Save WP Genius Settings', 'wp-genius' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
	
	<hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;" />
	
	<!-- Native React App Container -->
	<div id="smart-aui-admin-root"></div>
</div>

<style>
.w2p-smart-aui-settings {
	background: #fff;
	padding: 20px;
	border: 1px solid #ddd;
	border-radius: 3px;
}

.w2p-smart-aui-wp-genius-section {
	margin-bottom: 20px;
}

.w2p-smart-aui-wp-genius-section h3 {
	margin-top: 0;
	margin-bottom: 10px;
	font-size: 18px;
}

.w2p-smart-aui-wp-genius-section .description {
	margin-bottom: 15px;
	color: #646970;
}

.w2p-smart-aui-wp-genius-form .form-table th {
	width: 200px;
	padding: 15px 10px 15px 0;
}

.w2p-smart-aui-wp-genius-form .form-table td {
	padding: 15px 10px;
}

/* Hide Redundant Headers in React App */
#smart-aui-admin-root h1, 
#smart-aui-admin-root > div > div > h2:first-child {
    display: none !important;
}

/* Adjust spacing for React App */
#smart-aui-admin-root {
    margin-top: 0;
}
</style>
