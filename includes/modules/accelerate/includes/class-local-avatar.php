<?php
/**
 * WP Genius Accelerate — 本地头像
 *
 * 从 module.php 拆分（God class 重构）。
 *
 * @package WP_Genius
 * @subpackage Modules/Accelerate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_Accelerate_LocalAvatar
 */
class W2P_Accelerate_LocalAvatar {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_AccelerateModule
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param W2P_AccelerateModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * ============================================
	 * Local Avatar Management Integration
	 * ============================================
	 */
	public function init_local_avatar() {
		$settings = $this->module->get_settings();
		if ( empty( $settings['accelerate_enable_local_avatar'] ) ) {
			return;
		}

		// Styles are handled by admin_styles()

		// Load media library scripts on profile pages
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_avatar_scripts' ) );

		// Show custom avatar field
		add_action( 'show_user_profile', array( $this, 'render_avatar_field' ) );
		add_action( 'edit_user_profile', array( $this, 'render_avatar_field' ) );

		// Print inline JS for upload/remove functionality
		// [Refactor] Should extract to JS file, but keeping inline for now to prioritize logic fix
		add_action( 'admin_print_footer_scripts', array( $this, 'print_avatar_js' ) );

		// Save avatar metadata
		add_action( 'personal_options_update', array( $this, 'save_avatar' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_avatar' ) );

		// Override get_avatar to use local avatars
		add_filter( 'get_avatar', array( $this, 'get_local_avatar' ), 10, 5 );
	}
	public function enqueue_avatar_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || ( $screen->base !== 'profile' && $screen->base !== 'user-edit' ) ) {
			return;
		}
		wp_enqueue_media();
		// Inline styles moved to admin_styles()
	}
	public function render_avatar_field( $user ) {
		$avatar_id = get_user_meta( $user->ID, 'st_local_avatar', true );
		$blank_img = includes_url( 'images/blank.gif' );
		?>
		<h3><?php esc_html_e( 'Local Avatar', 'wp-genius' ); ?></h3>
		<table class="form-table">
			<tr>
				<th>
					<label><?php esc_html_e( 'Current Avatar', 'wp-genius' ); ?></label>
				</th>
				<td>
					<input type="hidden" name="st_local_avatar" id="st_local_avatar" value="<?php echo esc_attr( $avatar_id ); ?>">
					<div id="st-avatar-preview">
						<?php
						if ( $avatar_id ) {
							echo wp_get_attachment_image( $avatar_id, 96 );
						} else {
							echo '<img src="' . esc_url( $blank_img ) . '" width="96" height="96" style="background:#f1f1f1;border-radius:50%;" />';
						}
						?>
					</div>
					<p>
						<button type="button" class="button" id="st-upload-avatar">
							<?php esc_html_e( 'Upload / Select Avatar', 'wp-genius' ); ?>
						</button>
						<button type="button" class="button" id="st-remove-avatar">
							<?php esc_html_e( 'Remove Avatar', 'wp-genius' ); ?>
						</button>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
	public function print_avatar_js() {
		$screen = get_current_screen();
		if ( ! $screen || ( $screen->base !== 'profile' && $screen->base !== 'user-edit' ) ) {
			return;
		}

		$blank_img = esc_url( includes_url( 'images/blank.gif' ) );
		?>
		<script>
		(function($) {
			$('#st-upload-avatar').on('click', function(e) {
				e.preventDefault();
				var frame = wp.media({
					title: '<?php esc_html_e( 'Select Avatar', 'wp-genius' ); ?>',
					library: { type: 'image' },
					multiple: false
				}).on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#st_local_avatar').val(attachment.id);
					$('#st-avatar-preview').html('<img src="' + attachment.url + '" width="96" height="96" style="border-radius:50%;" />');
				}).open();
			});

			$('#st-remove-avatar').on('click', function(e) {
				e.preventDefault();
				$('#st_local_avatar').val('');
				$('#st-avatar-preview').html('<img src="<?php echo $blank_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 行 382 已 esc_url 处理。 ?>" width="96" height="96" style="background:#f1f1f1;border-radius:50%;" />');
			});
		})(jQuery);
		</script>
		<?php
	}
	public function save_avatar( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- profile_update 钩子：WP 用户表单核心已验 nonce。
		$avatar_id = isset( $_POST['st_local_avatar'] ) ? absint( $_POST['st_local_avatar'] ) : 0;
		update_user_meta( $user_id, 'st_local_avatar', $avatar_id );
	}
	public function get_local_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
		if ( is_numeric( $id_or_email ) ) {
			$user = get_user_by( 'id', $id_or_email );
		} elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
			$user = get_user_by( 'id', $id_or_email->user_id );
		} else {
			$user = get_user_by( 'email', $id_or_email );
		}

		if ( ! $user ) {
			return $avatar;
		}

		$avatar_id = get_user_meta( $user->ID, 'st_local_avatar', true );
		if ( $avatar_id ) {
			return wp_get_attachment_image(
				$avatar_id,
				array( $size, $size ),
				false,
				array(
					'class' => "avatar avatar-{$size}",
					'alt'   => $alt,
				)
			);
		}

		$blank_img = esc_url( includes_url( 'images/blank.gif' ) );
		return '<img src="' . $blank_img . '" class="avatar avatar-' . $size . '" width="' . $size . '" height="' . $size . '" alt="' . esc_attr( $alt ) . '" />';
	}
}
