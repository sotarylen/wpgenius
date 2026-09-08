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
class W2P_FrontendEnhancementModule extends W2P_Abstract_Module {

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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// LX Music local dock: independent of the singular-only gate so the
		// site owner's status bar works on any frontend page.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_lx_assets' ), 20 );

		// Lightbox functionality
		if ( ! empty( $settings['lightbox_enabled'] ) ) {
			$this->init_lightbox();
		}

		// Image masonry (waterfall) grouping for consecutive images
		$handler_path = plugin_dir_path( __FILE__ ) . 'includes/class-masonry-handler.php';
		if ( file_exists( $handler_path ) ) {
			require_once $handler_path;
			new WPG_Masonry_Handler( $settings );
		}

		// Video optimization functionality
		if ( ! empty( $settings['video_enabled'] ) ) {
			$this->init_video_optimizer();
		}

		// Reader functionality
		if ( ! empty( $settings['reader_enabled'] ) ) {
			$this->init_reader();
		}

		// Music capability line (playlists, tags, lyrics)
		if ( ! empty( $settings['music_enabled'] ) ) {
			$this->init_music( $settings );
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
		add_action( 'wp_ajax_wpg_set_featured_image', array( $this, 'ajax_set_featured_image' ) );
		add_action( 'wp_ajax_wpg_delete_attachment', array( $this, 'ajax_delete_attachment' ) );

		// Admin asset loading
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'wp-genius-settings' ) === false ) {
			return;
		}
		$plugin_url = plugin_dir_url( WP_GENIUS_FILE );
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

		// Fill missing Music keys with defaults (CSF only persists on save).
		return array_merge( self::music_defaults(), $settings );
	}

	/**
	 * Music feature defaults (single source, matches CSF defaults).
	 *
	 * @return array
	 */
	public static function music_defaults() {
		return array(
			'music_enabled'                => true,
			'music_player_mode'            => 'card',
			'music_playlist_order'         => 'sequence',
			'music_embed_default_platform' => 'netease',
			'music_embed_autoplay'         => false,
			'music_tag_extract'            => true,
			'music_lyric_priority'         => 'file',
			'music_lx_enabled'             => false,
			'music_lx_port'                => 23330,
			'music_lx_timeout'             => 2000,
			'music_lx_sse'                 => true,
			'music_lx_visible'             => 'admin',
		);
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
	 * Check if current post has music content (playlists or audio).
	 *
	 * @return boolean
	 */
	private function has_music_content() {
		if ( ! is_singular() ) {
			return false;
		}

		global $post;
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		// Playlist shortcode, platform embeds, core audio block or raw audio tags.
		if ( has_shortcode( $post->post_content, 'wpg_playlist' ) ||
			has_shortcode( $post->post_content, 'wpg_music' ) ||
			has_block( 'core/audio' ) ||
			strpos( $post->post_content, '<audio' ) !== false ) {
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

		// Lightbox assets
		if ( ! empty( $settings['lightbox_enabled'] ) && $this->has_image_content() ) {
			$this->enqueue_lightbox_assets();
		}

		// Image masonry styles (enabled + content has images)
		if ( ! empty( $settings['masonry_enabled'] ) && $this->has_image_content() ) {
			wp_enqueue_style(
				'w2p-masonry',
				plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/css/masonry.css',
				array(),
				W2P_VERSION
			);
		}

		// Plyr video player assets
		if ( ! empty( $settings['video_enabled'] ) && $this->has_video_content() ) {
			$this->enqueue_video_assets();
		}

		// Reader enhancement assets (Strict Check: Only if container ID exists)
		if ( ! empty( $settings['reader_enabled'] ) && $this->has_reader_container() ) {
			$this->enqueue_reader_assets();
		}

		// Music assets (playlists / audio content)
		if ( ! empty( $settings['music_enabled'] ) && $this->has_music_content() ) {
			$this->enqueue_music_assets();
		}
	}



	/**
	 * Enqueue Lightbox assets (singular content with images).
	 *
	 * @return void
	 */
	private function enqueue_lightbox_assets() {
		$settings = $this->get_settings();

		// Guard: only load when the feature is enabled and the current content has images.
		if ( empty( $settings['lightbox_enabled'] ) || ! $this->has_image_content() ) {
			return;
		}

		// Core UI assets used by the Lightbox overlay/toolbar (registered in wp-genius.php).
		wp_enqueue_style( 'w2p-core-css' );
		wp_enqueue_script( 'w2p-admin-ui' );

		wp_enqueue_script(
			'wpg-lightbox',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/lightbox.js',
			array( 'jquery', 'w2p-admin-ui' ),
			W2P_VERSION, // Feature: support 3 animation types (fade, slide, zoom)
			true
		);

		wp_localize_script(
			'wpg-lightbox',
			'wpgLightboxConfig',
			array(
				'postId'         => get_the_ID(),
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wpg_lightbox_action' ),
				'canSetFeatured' => current_user_can( 'edit_posts' ),
				'canDelete'      => current_user_can( 'manage_options' ), // Only admins can delete
				'settings'       => $settings,
				'i18n'           => array(
					'close'         => __( 'Close', 'wp-genius' ),
					'prev'          => __( 'Previous', 'wp-genius' ),
					'next'          => __( 'Next', 'wp-genius' ),
					'zoomIn'        => __( 'Zoom In', 'wp-genius' ),
					'zoomOut'       => __( 'Zoom Out', 'wp-genius' ),
					'setFeatured'   => __( 'Set as Featured', 'wp-genius' ),
					'deleteImage'   => __( 'Delete Image', 'wp-genius' ),
					'confirmDelete' => __( 'Are you sure you want to permanently delete this image from media library?', 'wp-genius' ),
					'autoplay'      => __( 'Autoplay', 'wp-genius' ),
					'downloading'   => __( 'Downloading...', 'wp-genius' ),
					'success'       => __( 'Featured image updated!', 'wp-genius' ),
					'error'         => __( 'An error occurred.', 'wp-genius' ), // [FIX] Generic error message (was misleadingly "Failed to update featured image")
					'deleteSuccess' => __( 'Image deleted successfully!', 'wp-genius' ),
					'deleteError'   => __( 'Failed to delete image.', 'wp-genius' ),
				),
			)
		);
	}

	/**
	 * Enqueue Plyr video player assets (singular content with videos).
	 *
	 * @return void
	 */
	private function enqueue_video_assets() {
		$settings = $this->get_settings();

		// Guard: only load when the feature is enabled and the current content has video.
		if ( empty( $settings['video_enabled'] ) || ! $this->has_video_content() ) {
			return;
		}

		// Enqueue Plyr from CDN
		wp_enqueue_style(
			'plyr-css',
			'https://cdn.plyr.io/3.7.8/plyr.css',
			array(),
			'3.7.8'
		);
		// Enqueue custom video player styles
		wp_enqueue_style(
			'wpg-video-player',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/css/video-player.css',
			array( 'plyr-css' ),
			W2P_VERSION
		);
		wp_enqueue_script(
			'plyr-js',
			'https://cdn.plyr.io/3.7.8/plyr.polyfilled.js',
			array(),
			'3.7.8',
			true
		);
		// Custom video optimizer script
		wp_enqueue_script(
			'wpg-video-optimizer',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/video-optimizer.js',
			array( 'jquery', 'plyr-js' ),
			W2P_VERSION,
			true
		);
		wp_localize_script(
			'wpg-video-optimizer',
			'wpgVideoConfig',
			array(
				'settings' => $settings,
				'i18n'     => array(
					'openInLightbox' => __( 'Play in Lightbox', 'wp-genius' ),
				),
			)
		);
	}

	/**
	 * Enqueue reader enhancement assets (container present).
	 *
	 * @return void
	 */
	private function enqueue_reader_assets() {
		$settings = $this->get_settings();

		// Guard: only load when the feature is enabled and the reader container exists.
		if ( empty( $settings['reader_enabled'] ) || ! $this->has_reader_container() ) {
			return;
		}

		wp_enqueue_style(
			'wpg-reader-css',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/css/reader.css',
			array(),
			W2P_VERSION
		);
		wp_enqueue_script(
			'wpg-reader-js',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/reader.js',
			array( 'jquery' ),
			W2P_VERSION,
			true
		);
		wp_localize_script(
			'wpg-reader-js',
			'wpgReaderConfig',
			array(
				'postId'   => get_the_ID(),
				'settings' => $settings,
				'i18n'     => array(
					'decreaseFont'   => __( 'Decrease font size', 'wp-genius' ),
					'increaseFont'   => __( 'Increase font size', 'wp-genius' ),
					'font'           => __( 'Font', 'wp-genius' ),
					'fontSans'       => __( 'System Default', 'wp-genius' ),
					'fontHeiti'      => __( 'SimHei (Heiti)', 'wp-genius' ),
					'fontSongti'     => __( 'SimSun (Songti)', 'wp-genius' ),
					'fontKaiti'      => __( 'KaiTi', 'wp-genius' ),
					'fontLishu'      => __( 'LiSu', 'wp-genius' ),
					'fontYahei'      => __( 'Microsoft YaHei', 'wp-genius' ),
					'fontDroidsans'  => __( 'Source Han Sans', 'wp-genius' ),
					'themeLight'     => __( 'Light Mode', 'wp-genius' ),
					'themeSepia'     => __( 'Sepia Mode', 'wp-genius' ),
					'themeGreen'     => __( 'Green Mode', 'wp-genius' ),
					'themeDark'      => __( 'Dark Mode', 'wp-genius' ),
					'fullscreen'     => __( 'Fullscreen / Focus Mode', 'wp-genius' ),
					'prevChapter'    => __( 'Previous Chapter', 'wp-genius' ),
					'toc'            => __( 'Table of Contents', 'wp-genius' ),
					'nextChapter'    => __( 'Next Chapter', 'wp-genius' ),
					'exitFullscreen' => __( 'Exit Fullscreen / Focus Mode', 'wp-genius' ),
				),
			)
		);
	}


	/**
	 * Enqueue Music assets (local APlayer vendor).
	 *
	 * @return void
	 */
	private function enqueue_music_assets() {
		$settings = $this->get_settings();

		// Guard: only load when enabled and music content exists.
		if ( empty( $settings['music_enabled'] ) || ! $this->has_music_content() ) {
			return;
		}

		$base = plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/';

		wp_enqueue_style(
			'wpg-aplayer-css',
			$base . 'lib/aplayer/APlayer.min.css',
			array(),
			'1.10.1'
		);
		wp_enqueue_script(
			'wpg-aplayer-js',
			$base . 'lib/aplayer/APlayer.min.js',
			array(),
			'1.10.1',
			true
		);
		// Version by file mtime so UI changes bust caches without bumping the plugin version.
		$music_css_ver = filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/music.css' ) ?: W2P_VERSION;
		$music_js_ver  = filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/wpg-music.js' ) ?: W2P_VERSION;

		wp_enqueue_style(
			'wpg-music-css',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/css/music.css',
			array( 'wpg-aplayer-css' ),
			$music_css_ver
		);
		wp_enqueue_script(
			'wpg-music-js',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/wpg-music.js',
			array( 'wpg-aplayer-js' ),
			$music_js_ver,
			true
		);
		wp_localize_script(
			'wpg-music-js',
			'wpgMusicConfig',
			array(
				'settings' => $settings,
			)
		);
	}

	/**
	 * Enqueue LX Music local dock assets (site-owner tool, any frontend page).
	 *
	 * Only loads for users allowed by the visibility setting; the server never
	 * proxies localhost (pod network is unreachable from the browser user's
	 * desktop), so all LX traffic stays client-side.
	 *
	 * @return void
	 */
	public function enqueue_lx_assets() {
		$settings = $this->get_settings();

		if ( empty( $settings['music_lx_enabled'] ) || ! $this->lx_visible_for_current_user() ) {
			return;
		}

		$js_ver = filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/wpg-lx.js' ) ?: W2P_VERSION;

		wp_enqueue_script(
			'wpg-lx-js',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/wpg-lx.js',
			array(),
			$js_ver,
			true
		);
		wp_localize_script(
			'wpg-lx-js',
			'wpgLxConfig',
			array(
				'port'        => absint( $settings['music_lx_port'] ),
				'timeout'     => absint( $settings['music_lx_timeout'] ),
				'useSse'      => ! empty( $settings['music_lx_sse'] ),
				'canSee'      => true,
				'i18n'        => array(
					'offlineTitle'    => __( 'LX Music is not running', 'wp-genius' ),
					'launch'          => __( 'Start LX Music', 'wp-genius' ),
					'launching'       => __( 'Waiting for LX Music…', 'wp-genius' ),
					'play'            => __( 'Play', 'wp-genius' ),
					'pause'           => __( 'Pause', 'wp-genius' ),
					'prev'            => __( 'Previous', 'wp-genius' ),
					'next'            => __( 'Next', 'wp-genius' ),
					'mute'            => __( 'Mute', 'wp-genius' ),
					'unmute'          => __( 'Unmute', 'wp-genius' ),
					'close'           => __( 'Close', 'wp-genius' ),
					'ariaDock'        => __( 'LX Music player', 'wp-genius' ),
				),
			)
		);
	}

	/**
	 * Whether the current user is allowed to see the LX Music dock.
	 *
	 * @return bool
	 */
	private function lx_visible_for_current_user() {
		$settings = $this->get_settings();
		$who      = isset( $settings['music_lx_visible'] ) ? $settings['music_lx_visible'] : 'admin';

		switch ( $who ) {
			case 'logged':
				return is_user_logged_in();
			case 'all':
				return true;
			case 'admin':
			default:
				return current_user_can( 'manage_options' );
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
		$settings     = $this->get_settings();
		$handler_path = plugin_dir_path( __FILE__ ) . 'includes/class-reader-handler.php';
		if ( file_exists( $handler_path ) ) {
			require_once $handler_path;
			new WPG_Reader_Handler( $settings );
		}
	}

	/**
	 * Initialize Music capability line
	 */
	private function init_music( $settings = array() ) {
		$handler_path = plugin_dir_path( __FILE__ ) . 'includes/class-music-handler.php';
		$meta_path    = plugin_dir_path( __FILE__ ) . 'includes/class-music-meta.php';
		$cpt_path     = plugin_dir_path( __FILE__ ) . 'includes/class-playlist-cpt.php';

		if ( file_exists( $handler_path ) ) {
			require_once $handler_path;
			new WPG_Music_Handler( $settings );
		}

		if ( file_exists( $meta_path ) ) {
			require_once $meta_path;
			new WPG_Music_Meta( $settings );
		}

		if ( file_exists( $cpt_path ) ) {
			require_once $cpt_path;
			new WPG_Playlist_CPT( $this->get_settings() );
		}
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
			wp_send_json_success(
				array(
					'message' => __( 'Featured image updated successfully!', 'wp-genius' ),
				)
			);
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
			wp_send_json_error( array( 'message' => __( 'Permission denied. Only administrators can delete images.', 'wp-genius' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'wp-genius' ) ) );
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

						$patterns = array(
							// Figure wrappers (Gutenberg standard) - non-greedy to catch the closest figure
							'/<figure[^>]*>(?:(?!<\/figure>).)*?wp-image-' . $attachment_id . '.*?<\/figure>/is',
							// Links wrapping images
							'/<a[^>]*>(?:(?!<\/a>).)*?wp-image-' . $attachment_id . '.*?<\/a>/is',
							// Clean img tag
							'/<img[^>]*wp-image-' . $attachment_id . '[^>]*>/is',
						);

						$new_content = preg_replace( $patterns, '', $content );

						// Update post if content changed
						if ( $new_content !== $content ) {
							wp_update_post(
								array(
									'ID'           => $post_id,
									'post_content' => $new_content,
								)
							);
						}
					}
				}

				wp_send_json_success(
					array(
						'message' => __( 'Delete Success!', 'wp-genius' ),
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to delete image from media library.', 'wp-genius' ) ) );
			}
		} catch ( \Throwable $e ) {
			// Catch any fatal errors or exceptions to prevent 500 header
			error_log( 'WP Genius Lightbox Delete Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Internal Server Error: ', 'wp-genius' ) . $e->getMessage() ) );
		}
	}
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'FrontendEnhancementModule', false ) ) {
	class_alias( 'W2P_FrontendEnhancementModule', 'FrontendEnhancementModule' );
}
