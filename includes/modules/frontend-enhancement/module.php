<?php
/**
 * Frontend Enhancement Module
 * 
 * Enhance frontend user experience with Lightbox viewer, video optimization, and audio player.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Enhancement Module Class
 */
class FrontendEnhancementModule extends W2P_Abstract_Module {

	/**
	 * Module ID
	 */
	public static function id() {
		return 'frontend-enhancement';
	}

	/**
	 * Module Name
	 */
	public static function name() {
		return __( 'Frontend Enhancement', 'wp-genius' );
	}

	/**
	 * Module Description
	 */
	public static function description() {
		return __( 'Enhance frontend user experience with Lightbox image viewer, video player optimization, and audio player.', 'wp-genius' );
	}

	public static function icon() {
		return 'fa-solid fa-wand-sparkles';
	}

	/**
	 * Initialize Module
	 */
	public function init() {
		$this->register_settings();
		
		$settings = $this->get_settings();
		
		// Frontend asset loading (only on required pages)
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		
		// Lightbox functionality
		if ( ! empty( $settings['lightbox_enabled'] ) ) {
			$this->init_lightbox();
		}
		
		// Video optimization functionality
		if ( ! empty( $settings['video_enabled'] ) ) {
			$this->init_video_optimizer();
		}
		
		// Reader functionality
		if ( ! empty( $settings['reader_enabled'] ) ) {
			$this->init_reader();
		}
		
		// Audio player (reserved)
		if ( ! empty( $settings['audio_enabled'] ) ) {
			$this->init_audio_player();
		}
		
		// Code highlighting functionality
		if ( ! empty( $settings['code_highlight_enabled'] ) ) {
			$handler_path = plugin_dir_path( __FILE__ ) . 'includes/class-highlight-handler.php';
			if ( file_exists( $handler_path ) ) {
				require_once $handler_path;
				new WPG_Highlight_Handler( $settings );
			}
		}
		
		// AJAX handlers
		add_action( 'wp_ajax_wpg_set_featured_image', [ $this, 'ajax_set_featured_image' ] );
		add_action( 'wp_ajax_wpg_delete_attachment', [ $this, 'ajax_delete_attachment' ] );

        // Admin asset loading
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'wp-genius-settings' ) === false ) {
            return;
        }

        $plugin_url = plugin_dir_url( WP_GENIUS_FILE );
        
        // Register helper scripts that might be needed by other modules or this one in admin
        wp_register_script( 'w2p-fa-icons', $plugin_url . "assets/js/w2p-fa-icons.js", array( 'jquery' ), '1.0.0', true );
    }

	/**
	 * Register default settings
	 */
	public function register_settings() {
		return include plugin_dir_path( __FILE__ ) . 'options.php';
	}

    /**
     * Override get_settings to handle nested tab data from CSF
     */
    public function get_settings() {
        $settings = parent::get_settings();
        
        // Flatten frontend_enhancement_tabs if present (CSF nested tabs behavior)
        if ( isset( $settings['frontend_enhancement_tabs'] ) && is_array( $settings['frontend_enhancement_tabs'] ) ) {
            $settings = array_merge( $settings, $settings['frontend_enhancement_tabs'] );
        }
        
        return $settings;
    }

	/**
	 * Frontend asset loading (on-demand)
	 */
	/**
	 * Check if current post has video content
	 * 
	 * @return boolean
	 */
	private function has_video_content() {
		if ( ! is_singular() ) {
			return false;
		}

		global $post;
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		// Check for core video block or embed blocks
		if ( has_block( 'core/video' ) || has_block( 'core/embed' ) ) {
			return true;
		}

		// Check for video shortcodes or tags in content
		if ( has_shortcode( $post->post_content, 'video' ) || 
			 strpos( $post->post_content, '<video' ) !== false || 
			 strpos( $post->post_content, 'https://www.youtube.com' ) !== false ||
			 strpos( $post->post_content, 'https://youtu.be' ) !== false ||
			 strpos( $post->post_content, 'https://vimeo.com' ) !== false ||
			 strpos( $post->post_content, 'https://player.bilibili.com' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current post has images (for Lightbox)
	 * 
	 * @return boolean
	 */
	private function has_image_content() {
		if ( ! is_singular() ) {
			return false;
		}

		global $post;
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		// Check for core image or gallery blocks
		if ( has_block( 'core/image' ) || has_block( 'core/gallery' ) || has_block( 'core/media-text' ) ) {
			return true;
		}

		// Check for common image tags or shortcodes
		if ( has_shortcode( $post->post_content, 'gallery' ) || 
			 strpos( $post->post_content, '<img' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current post has reader container
	 * 
	 * @return boolean
	 */
	private function has_reader_container() {
		if ( ! is_singular() ) {
			return false;
		}

		global $post;
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		// Check 1: Explicit support for 'chapter' CPT (as seen in body classes)
		if ( is_singular( 'chapter' ) ) {
			return true;
		}

		// Check 2: User Rule - ID "w2p-book-chapters" exists in content
		// (Fallback for other post types where it might be manually added or shortcoded)
		if ( strpos( $post->post_content, 'w2p-book-chapters' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Frontend asset loading (on-demand)
	 */
	public function enqueue_frontend_assets() {
		// Only load on singular posts/pages
		if ( ! is_singular() ) {
			return;
		}
		
		$settings = $this->get_settings();

		// Styles are now loaded globally via w2p_core_enqueue_scripts
		// Only load Admin UI JS if we are actually going to use Lightbox or potentially other interactive features
		$load_admin_ui = false;

		// Lightbox assets
		if ( ! empty( $settings['lightbox_enabled'] ) && $this->has_image_content() ) {
			$load_admin_ui = true;
			
			wp_enqueue_script(
				'wpg-lightbox',
				plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/lightbox.js',
				[ 'jquery', 'w2p-admin-ui' ],
				'1.2.0', // Feature: support 3 animation types (fade, slide, zoom)
				true
			);
			
			// [Code Review] Inline JS extracted to external file assets/js/lightbox-init.js (Rule #1)
			wp_enqueue_script(
				'wpg-lightbox-init',
				plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/lightbox-init.js',
				[ 'wpg-lightbox' ],
				'1.0.0',
				true
			);
			
			wp_localize_script( 'wpg-lightbox', 'wpgLightboxConfig', [
				'postId'    => get_the_ID(),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wpg_lightbox_action' ),
				'canSetFeatured' => current_user_can( 'edit_posts' ),
				'canDelete'      => current_user_can( 'manage_options' ), // Only admins can delete
				'settings'  => $settings,
				'i18n'      => [
					'close'        => __( 'Close', 'wp-genius' ),
					'prev'         => __( 'Previous', 'wp-genius' ),
					'next'         => __( 'Next', 'wp-genius' ),
					'zoomIn'       => __( 'Zoom In', 'wp-genius' ),
					'zoomOut'      => __( 'Zoom Out', 'wp-genius' ),
					'setFeatured'  => __( 'Set as Featured', 'wp-genius' ),
					'deleteImage'  => __( 'Delete Image', 'wp-genius' ),
					'confirmDelete'=> __( 'Are you sure you want to permanently delete this image from media library?', 'wp-genius' ),
					'autoplay'     => __( 'Autoplay', 'wp-genius' ),
					'downloading'  => __( 'Downloading...', 'wp-genius' ),
					'success'      => __( 'Featured image updated!', 'wp-genius' ),
					'error'        => __( 'An error occurred.', 'wp-genius' ), // [FIX] Generic error message (was misleadingly "Failed to update featured image")
					'deleteSuccess'=> __( 'Image deleted successfully!', 'wp-genius' ),
					'deleteError'  => __( 'Failed to delete image.', 'wp-genius' ),
				],
			] );
		}
		
		// Plyr video player assets
		if ( ! empty( $settings['video_enabled'] ) && $this->has_video_content() ) {
			// Enqueue Plyr from CDN
			wp_enqueue_style(
				'plyr-css',
				'https://cdn.plyr.io/3.7.8/plyr.css',
				[],
				'3.7.8'
			);

			// Enqueue custom video player styles
			wp_enqueue_style(
				'wpg-video-player',
				plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/css/video-player.css',
				[ 'plyr-css' ],
				'1.0.0'
			);

			wp_enqueue_script(
				'plyr-js',
				'https://cdn.plyr.io/3.7.8/plyr.polyfilled.js',
				[],
				'3.7.8',
				true
			);

			// Custom video optimizer script
			wp_enqueue_script(
				'wpg-video-optimizer',
				plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/video-optimizer.js',
				[ 'jquery', 'plyr-js' ],
				'1.0.0',
				true
			);
			
			wp_localize_script( 'wpg-video-optimizer', 'wpgVideoConfig', [
				'settings' => $settings,
				'i18n'     => [
					'openInLightbox' => __( 'Play in Lightbox', 'wp-genius' ),
				],
			] );
		}

		// Reader enhancement assets (Strict Check: Only if container ID exists)
		if ( ! empty( $settings['reader_enabled'] ) && $this->has_reader_container() ) {
			wp_enqueue_style(
				'wpg-reader-css',
				plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/css/reader.css',
				[],
				'1.0.1'
			);

			wp_enqueue_script(
				'wpg-reader-js',
				plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/reader.js',
				[ 'jquery' ],
				'1.0.1',
				true
			);

			wp_localize_script( 'wpg-reader-js', 'wpgReaderConfig', [
				'postId'   => get_the_ID(),
				'settings' => $settings,
			] );
		}
		
		// Load Admin UI only if needed
		if ( $load_admin_ui ) {
			wp_enqueue_script(
				'w2p-admin-ui',
				plugin_dir_url( WP_GENIUS_FILE ) . 'assets/js/w2p-admin-ui.js',
				[ 'jquery' ],
				'1.0.0',
				true
			);
			wp_localize_script( 'w2p-admin-ui', 'w2p_ui_i18n', [
				'confirm'       => __( 'Confirm', 'wp-genius' ),
				'cancel'        => __( 'Cancel', 'wp-genius' ),
				'confirm_title' => __( 'Confirmation', 'wp-genius' ),
				'settings_saved'=> __( 'Settings saved successfully!', 'wp-genius' ),
			] );
		}

	}

	/**
	 * Initialize Lightbox functionality
	 */
	private function init_lightbox() {
		$handler_path = plugin_dir_path( __FILE__ ) . 'includes/class-lightbox-handler.php';
		if ( file_exists( $handler_path ) ) {
			require_once $handler_path;
			new WPG_Lightbox_Handler();
		}
	}

	/**
	 * Initialize video optimization functionality
	 */
	private function init_video_optimizer() {
		$handler_path = plugin_dir_path( __FILE__ ) . 'includes/class-video-handler.php';
		if ( file_exists( $handler_path ) ) {
			require_once $handler_path;
			new WPG_Video_Handler();
		}
	}

	/**
	 * Initialize Reader functionality
	 */
	private function init_reader() {
		$settings = $this->get_settings();
		$handler_path = plugin_dir_path( __FILE__ ) . 'includes/class-reader-handler.php';
		if ( file_exists( $handler_path ) ) {
			require_once $handler_path;
			new WPG_Reader_Handler( $settings );
		}
	}

	/**
	 * Initialize audio player (reserved)
	 */
	private function init_audio_player() {
		// Reserved interface
	}

	/**
	 * AJAX: Set featured image
	 */
	public function ajax_set_featured_image() {
		check_ajax_referer( 'wpg_lightbox_action', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-genius' ) );
		}

		$post_id       = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $post_id || ! $attachment_id ) {
			wp_send_json_error( __( 'Invalid parameters.', 'wp-genius' ) );
		}

		// Set featured image
		$result = set_post_thumbnail( $post_id, $attachment_id );

		if ( $result ) {
			wp_send_json_success( [
				'message' => __( 'Featured image updated successfully!', 'wp-genius' ),
			] );
		} else {
			wp_send_json_error( __( 'Failed to update featured image.', 'wp-genius' ) );
		}
	}
	
	/**
	 * AJAX: Delete attachment
	 */
	public function ajax_delete_attachment() {
		check_ajax_referer( 'wpg_lightbox_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied. Only administrators can delete images.', 'wp-genius' ) ] );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid parameters.', 'wp-genius' ) ] );
		}

		// [FIX] Ensure necessary WordPress admin files are loaded for delete operations
		if ( ! function_exists( 'wp_delete_attachment' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		try {
			// Delete attachment (this also deletes thumbnails/files from disk)
			// Force delete = true to bypass trash
			$result = wp_delete_attachment( $attachment_id, true );

			if ( $result ) {
				// [NEW] If post_id is provided, also remove the image tag from the post content
				$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
				if ( $post_id ) {
					$post = get_post( $post_id );
					if ( $post ) {
						$content = $post->post_content;
						
						// Case 1: Remove <figure> blocks containing the image with wp-image-{ID} class
						// Case 2: Remove <a> tags wrapping the image
						// Case 3: Remove floating <img> tags with the specific class
						
						$patterns = [
							// Figure wrappers (Gutenberg standard) - non-greedy to catch the closest figure
							'/<figure[^>]*>(?:(?!<\/figure>).)*?wp-image-' . $attachment_id . '.*?<\/figure>/is',
							// Links wrapping images
							'/<a[^>]*>(?:(?!<\/a>).)*?wp-image-' . $attachment_id . '.*?<\/a>/is',
							// Clean img tag
							'/<img[^>]*wp-image-' . $attachment_id . '[^>]*>/is'
						];
						
						$new_content = preg_replace( $patterns, '', $content );
						
						// Update post if content changed
						if ( $new_content !== $content ) {
							wp_update_post( [
								'ID'           => $post_id,
								'post_content' => $new_content
							] );
						}
					}
				}

				wp_send_json_success( [
					'message' => __( 'Delete Success!', 'wp-genius' ),
				] );
			} else {
				wp_send_json_error( [ 'message' => __( 'Failed to delete image from media library.', 'wp-genius' ) ] );
			}
		} catch ( \Throwable $e ) {
			// Catch any fatal errors or exceptions to prevent 500 header
			error_log( 'WP Genius Lightbox Delete Error: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => __( 'Internal Server Error: ', 'wp-genius' ) . $e->getMessage() ] );
		}
	}


}
