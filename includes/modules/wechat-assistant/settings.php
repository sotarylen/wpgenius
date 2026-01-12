<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="w2p-settings-panel w2p-wechat-assistant-settings">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="word2posts_save_module_settings" />
		<input type="hidden" name="module_id" value="wechat-assistant" />
		<?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_wechat_assistant_nonce' ); ?>

		<div class="w2p-section">
			<div class="w2p-section-header">
				<h4><?php _e( 'API Configuration', 'wp-genius' ); ?></h4>
			</div>
			<div class="w2p-section-body">
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="w2p_wechat_appid"><?php _e( 'AppID', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<input type="text" id="w2p_wechat_appid" name="w2p_wechat_appid" value="<?php echo esc_attr( get_option( 'w2p_wechat_appid' ) ); ?>" class="w2p-input-large" />
						<p class="description"><?php _e( 'WeChat Official Account Developer ID (AppID)', 'wp-genius' ); ?></p>
					</div>
				</div>

				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="w2p_wechat_secret"><?php _e( 'AppSecret', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<input type="password" id="w2p_wechat_secret" name="w2p_wechat_secret" value="<?php echo esc_attr( get_option( 'w2p_wechat_secret' ) ); ?>" class="w2p-input-large" />
						<p class="description"><?php _e( 'WeChat Official Account Developer Secret (AppSecret)', 'wp-genius' ); ?></p>
					</div>
				</div>

				<div class="w2p-form-row border-none">
					<div class="w2p-form-label">
						<label><?php _e( 'Enable Frontend Share', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" name="w2p_wechat_enable_share" value="yes" <?php checked( 'yes', get_option( 'w2p_wechat_enable_share', 'yes' ) ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description"><?php _e( 'Enable WeChat JSSDK sharing features (custom title, description, icon)', 'wp-genius' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<div class="w2p-settings-actions">
			<button type="submit" class="w2p-btn w2p-btn-primary">
				<i class="fa-solid fa-floppy-disk"></i>
				<?php _e( 'Save Wechat Settings', 'wp-genius' ); ?>
			</button>
		</div>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
    // Show toast message if settings are updated
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('updated') === '1' && urlParams.get('w2p_module') === 'wechat-assistant') {
        if (typeof w2p !== 'undefined' && w2p.toast) {
            w2p.toast('<?php _e( 'Settings Saved', 'wp-genius' ); ?>', 'success');
        }
    }
});
</script>
