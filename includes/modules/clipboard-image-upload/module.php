<?php
/**
 * Clipboard Image Upload Module
 * 
 * Intercept clipboard paste events to upload Base64 images as physical files.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clipboard Image Upload Module Class
 */
class ClipboardImageUploadModule extends W2P_Abstract_Module {

	/**
	 * Module ID
	 */
	public static function id() {
		return 'clipboard-image-upload';
	}

	/**
	 * Module Name
	 */
	public static function name() {
		return __( 'Clipboard Image Upload', 'wp-genius' );
	}

	/**
	 * Module Description
	 */
	public static function description() {
		return __( 'Automatically convert and upload pasted clipboard images (Base64) to the media library.', 'wp-genius' );
	}

	/**
	 * Initialize Module
	 */
	public function init() {
		$this->register_settings();

		// Enqueue scripts for Admin
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Classic Editor Button
		add_filter( 'mce_buttons', [ $this, 'register_tinymce_button' ] );
		add_filter( 'mce_external_plugins', [ $this, 'register_tinymce_plugin' ] );

		// AJAX handlers
		add_action( 'wp_ajax_w2p_clipboard_upload', [ $this, 'ajax_handle_upload' ] );

		// CSS for icon
		add_action( 'admin_head', [ $this, 'add_icon_styles' ] );
	}

	/**
	 * Register TinyMCE Button
	 */
	public function register_tinymce_button( $buttons ) {
		array_push( $buttons, 'w2p_clipboard_toggle' );
		return $buttons;
	}

	/**
	 * Register TinyMCE Plugin
	 */
	public function register_tinymce_plugin( $plugin_array ) {
		$plugin_array['w2p_clipboard_upload'] = plugins_url( 'clipboard-upload.js', __FILE__ );
		return $plugin_array;
	}

	/**
	 * Add Icon Styles
	 */
	public function add_icon_styles() {
		?>
		<style>
			i.mce-i-w2p_clipboard_toggle:before {
				content: "\f124"; /* dashicons-paste */
				font-family: dashicons !important;
			}
			.mce-w2p_clipboard_toggle.mce-active i {
				color: #2271b1;
			}
		</style>
		<?php
	}

	/**
	 * Register Module Settings
	 */
	public function register_settings() {
		$defaults = [
			'enabled' => true,
			'image_prefix' => 'clipboard_',
		];

		$settings = get_option( 'w2p_clipboard_upload_settings', [] );
		if ( empty( $settings ) ) {
			update_option( 'w2p_clipboard_upload_settings', $defaults );
		}
	}

	/**
	 * Enqueue Assets
	 */
	public function enqueue_assets( $hook ) {
		// Only load on Post Edit and Media Library pages
		$relevant_pages = [ 'post.php', 'post-new.php', 'upload.php' ];
		if ( ! in_array( $hook, $relevant_pages ) ) {
			return;
		}

		$settings = get_option( 'w2p_clipboard_upload_settings', [] );
		
		wp_enqueue_script(
			'w2p-clipboard-upload',
			plugins_url( 'clipboard-upload.js', __FILE__ ),
			[ 'jquery' ],
			'1.0.0',
			true
		);

		wp_localize_script(
			'w2p-clipboard-upload',
			'w2pClipboardParams',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'w2p_clipboard_upload' ),
				'settings' => $settings,
				'l10n'     => [
					'uploading' => __( 'Uploading clipboard image...', 'wp-genius' ),
					'success'   => __( 'Image uploaded successfully!', 'wp-genius' ),
					'error'     => __( 'Failed to upload clipboard image.', 'wp-genius' ),
				]
			]
		);
	}

	/**
	 * AJAX Handle Upload
	 */
	public function ajax_handle_upload() {
		check_ajax_referer( 'w2p_clipboard_upload', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$base64_data = isset( $_POST['image_data'] ) ? $_POST['image_data'] : '';
		$post_id     = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( empty( $base64_data ) ) {
			wp_send_json_error( 'No image data provided' );
		}

		// Process Base64
		$result = $this->save_base64_image( $base64_data, $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Save Base64 Image to Media Library
	 */
	private function save_base64_image( $base64_data, $post_id ) {
		// Check for valid Base64 format
		if ( ! preg_match( '/^data:image\/(\w+);base64,/', $base64_data, $type ) ) {
			return new WP_Error( 'invalid_format', 'Invalid image format' );
		}

		$extension = strtolower( $type[1] ); // png, jpg, etc.
		if ( ! in_array( $extension, [ 'jpg', 'jpeg', 'gif', 'png', 'webp' ] ) ) {
			return new WP_Error( 'invalid_extension', 'Unsupported image type' );
		}

		$data = base64_decode( substr( $base64_data, strpos( $base64_data, ',' ) + 1 ) );
		if ( false === $data ) {
			return new WP_Error( 'decode_failed', 'Failed to decode image data' );
		}

		// Prepare filename
		$settings = get_option( 'w2p_clipboard_upload_settings', [] );
		$prefix = isset( $settings['image_prefix'] ) ? $settings['image_prefix'] : 'clipboard_';
		$filename = $prefix . uniqid() . '.' . $extension;

		$upload_dir = wp_upload_dir();
		$file_path = $upload_dir['path'] . '/' . $filename;

		// Save to file
		file_put_contents( $file_path, $data );

		// Check if file exists and has size
		if ( ! file_exists( $file_path ) || filesize( $file_path ) === 0 ) {
			return new WP_Error( 'save_failed', 'Failed to save physical file' );
		}

		// Add to Media Library
		$file_type = wp_check_filetype( $filename, null );
		$attachment = [
			'post_mime_type' => $file_type['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $upload_dir['url'] . '/' . $filename,
		];

		$attach_id = wp_insert_attachment( $attachment, $file_path, $post_id );

		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}

		// Generate metadata
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return [
			'id'  => $attach_id,
			'url' => wp_get_attachment_url( $attach_id ),
		];
	}
}
