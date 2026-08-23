<?php
/**
 * Smart AUI — Enhanced Media Attach
 *
 * Enhances the WordPress core media-library "Attach" action (list mode, Unattached filter):
 *  1. Base: when opening the Attach dialog, auto-fill the search box with the attachment's
 *     filename so posts containing that image (by filename) are listed by default.
 *  2. Advanced: when an attachment is attached to a post, rewrite the media URL back into
 *     the post content — matching the filename loosely even when the stored path differs
 *     (e.g. Smart AUI downloaded the file but the URL rewrite failed, leaving the original
 *     remote URL such as https://image.playno1.com/.../xxx.jpg in the post).
 *
 * Only loaded (mounted) when the Smart AUI setting toggle is enabled.
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class W2P_SmartAUI_Attach_Enhance
 */
class W2P_SmartAUI_Attach_Enhance {

	/**
	 * Constructor. Hooks the enhancement into the admin lifecycle.
	 */
	public function __construct() {
		// Fired by WP core after media is attached/detached to a post (sets post_parent).
		add_action( 'wp_media_attach_action', array( $this, 'rewrite_url_on_attach' ), 10, 3 );

		// Inject the front-end JS (list mode) that auto-searches by filename.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Lightweight AJAX endpoint returning the filename for an attachment.
		add_action( 'wp_ajax_w2p_smart_aui_get_attachment_filename', array( $this, 'ajax_get_attachment_filename' ) );

		// Search posts whose CONTENT contains the core filename (the child theme limits the
		// native find_posts query to title-only, so we search content via our own action).
		add_action( 'wp_ajax_w2p_smart_aui_find_posts_by_filename', array( $this, 'ajax_find_posts_by_filename' ) );
	}

	/**
	 * Rewrite the media URL back into the post content when an attachment is attached.
	 *
	 * Hooked to `wp_media_attach_action` (action='attach'). Loosely matches the attachment's
	 * core filename inside the post content (ignoring Smart AUI date / WP size / -scaled
	 * suffixes) and replaces those image src URLs with the attachment's local URL.
	 *
	 * @param string $action        Attach/detach action. Accepts 'attach' or 'detach'.
	 * @param int    $attachment_id The attachment ID.
	 * @param int    $parent_id     The post ID the attachment is attached to.
	 * @return void
	 */
	public function rewrite_url_on_attach( $action, $attachment_id, $parent_id ) {
		if ( 'attach' !== $action ) {
			return;
		}

		$attachment_id = (int) $attachment_id;
		$parent_id     = (int) $parent_id;

		if ( $attachment_id <= 0 || $parent_id <= 0 ) {
			return;
		}

		$parent_post = get_post( $parent_id );
		if ( ! $parent_post || empty( $parent_post->post_content ) ) {
			return;
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( ! $attachment_url ) {
			return;
		}

		// Core filename (without extension), then strip Smart AUI / WP suffixes for a loose match.
		$core_name = $this->get_core_filename( $attachment_url );
		if ( '' === $core_name ) {
			return;
		}

		$content = $parent_post->post_content;

		// Match any <img src="..."> whose URL contains the core filename, swap to the local URL.
		$pattern = '/<img([^>]*)src=["\']([^"\']*' . preg_quote( $core_name, '/' ) . '[^"\']*)["\']([^>]*)>/i';

		$updated_content = preg_replace_callback(
			$pattern,
			function ( $m ) use ( $attachment_url ) {
				return '<img' . $m[1] . 'src="' . esc_attr( $attachment_url ) . '"' . $m[3] . '>';
			},
			$content
		);

		if ( null !== $updated_content && $updated_content !== $content ) {
			wp_update_post(
				array(
					'ID'           => $parent_id,
					'post_content' => $updated_content,
				)
			);
		}
	}

	/**
	 * Extract a loose-match core filename from an attachment URL.
	 *
	 * Strips the extension and trailing Smart AUI date suffix (-YYYY-MM-DD), WP size suffix
	 * (-WxH) and -scaled suffix, so posts referencing the original (un-suffixed) filename
	 * still match.
	 *
	 * @param string $url Attachment URL.
	 * @return string
	 */
	private function get_core_filename( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return '';
		}

		$stem = pathinfo( $path, PATHINFO_FILENAME );

		// Smart AUI date suffix, e.g. -2026-08-23.
		$stem = preg_replace( '/-\d{4}-\d{2}-\d{2}$/', '', $stem );
		// WP size suffix, e.g. -150x150.
		$stem = preg_replace( '/-\d+x\d+$/', '', $stem );
		// WP -scaled suffix.
		$stem = preg_replace( '/-scaled$/i', '', $stem );

		return $stem;
	}

	/**
	 * Enqueue the list-mode admin script (only on the media library list screen).
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'upload.php' !== $hook ) {
			return;
		}

		// List mode only (not the grid view).
		$mode = isset( $_GET['mode'] ) ? sanitize_key( $_GET['mode'] ) : get_user_option( 'media_library_mode' );
		if ( 'list' !== $mode ) {
			return;
		}

		// Depend on core 'media' handle so window.findPosts is defined before we wrap it.
		wp_enqueue_script(
			'w2p-smart-aui-attach-enhance',
			plugin_dir_url( __FILE__ ) . '../assets/js/smart-aui-attach-enhance.js',
			array( 'jquery', 'media' ),
			filemtime( __DIR__ . '/../assets/js/smart-aui-attach-enhance.js' ),
			true
		);

		wp_localize_script(
			'w2p-smart-aui-attach-enhance',
			'w2pSmartAuiAttachEnhance',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'w2p_smart_aui_attach_enhance' ),
			)
		);
	}

	/**
	 * AJAX: Return the core filename for an attachment ID.
	 *
	 * @return void
	 */
	public function ajax_get_attachment_filename() {
		check_ajax_referer( 'w2p_smart_aui_attach_enhance', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		if ( $attachment_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid attachment ID' ) );
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			wp_send_json_error( array( 'message' => 'Attachment not found' ) );
		}

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'filename'      => basename( $url ),
				'core'          => $this->get_core_filename( $url ),
			)
		);
	}

	/**
	 * AJAX: Find posts whose CONTENT contains the given core filename.
	 *
	 * The native find_posts query is limited to post_title by the child theme
	 * (w2p_force_title_only), so an image filename living only in post_content can
	 * never match there. This endpoint runs under its own action (which the child
	 * theme filter ignores) and searches post_content directly. It returns the same
	 * HTML table shape as the native find_posts dialog so the Select button keeps
	 * working unchanged.
	 *
	 * @return void
	 */
	public function ajax_find_posts_by_filename() {
		check_ajax_referer( 'w2p_smart_aui_attach_enhance', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$core = isset( $_POST['core'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['core'] ) ) ) : '';
		if ( '' === $core ) {
			wp_send_json_error( array( 'message' => 'No search term' ) );
		}

		// Public post types except attachment (the object being attached).
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		$posts = get_posts(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => 'any',
				'posts_per_page' => 25,
				's'              => $core,
			)
		);

		if ( empty( $posts ) ) {
			wp_send_json_error( __( 'No posts found containing this filename.', 'wp-genius' ) );
		}

		$post_type_labels = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			$post_type_labels[ $pt->name ] = $pt->labels->singular_name;
		}

		$html = '<table class="widefat"><thead><tr><th class="found-radio"><br /></th><th>' . esc_html__( 'Title', 'wp-genius' ) . '</th><th class="no-break">' . esc_html__( 'Type', 'wp-genius' ) . '</th><th class="no-break">' . esc_html__( 'Date', 'wp-genius' ) . '</th><th class="no-break">' . esc_html__( 'Status', 'wp-genius' ) . '</th></tr></thead><tbody>';

		$alternate = '';
		foreach ( $posts as $post ) {
			$title     = trim( $post->post_title ) ? $post->post_title : __( '(no title)', 'wp-genius' );
			$alternate = ( 'alternate' === $alternate ) ? '' : 'alternate';

			switch ( $post->post_status ) {
				case 'publish':
				case 'private':
					$stat = __( 'Published', 'wp-genius' );
					break;
				case 'future':
					$stat = __( 'Scheduled', 'wp-genius' );
					break;
				case 'pending':
					$stat = __( 'Pending Review', 'wp-genius' );
					break;
				case 'draft':
					$stat = __( 'Draft', 'wp-genius' );
					break;
				default:
					$stat = $post->post_status;
			}

			$time = ( '0000-00-00 00:00:00' === $post->post_date ) ? '' : mysql2date( __( 'Y/m/d', 'wp-genius' ), $post->post_date );

			$html .= '<tr class="' . trim( 'found-posts ' . $alternate ) . '"><td class="found-radio"><input type="radio" id="found-' . $post->ID . '" name="found_post_id" value="' . esc_attr( $post->ID ) . '"></td>';
			$html .= '<td><label for="found-' . $post->ID . '">' . esc_html( $title ) . '</label></td><td class="no-break">' . esc_html( $post_type_labels[ $post->post_type ] ?? $post->post_type ) . '</td><td class="no-break">' . esc_html( $time ) . '</td><td class="no-break">' . esc_html( $stat ) . ' </td></tr>';
		}

		$html .= '</tbody></table>';

		wp_send_json_success( $html );
	}
}
