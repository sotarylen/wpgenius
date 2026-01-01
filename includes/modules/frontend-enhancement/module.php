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

	/**
	 * Initialize Module
	 */
	public function init() {
		$this->register_settings();
		
		$settings = get_option( 'w2p_frontend_enhancement_settings', [] );
		
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
		
		// AJAX handlers
		add_action( 'wp_ajax_wpg_set_featured_image', [ $this, 'ajax_set_featured_image' ] );
	}

	/**
	 * Register default settings
	 */
	public function register_settings() {
		$defaults = [
			// Lightbox settings
			'lightbox_enabled'              => true,
			'lightbox_animation'            => 'fade',
			'lightbox_close_on_backdrop'    => true,
			'lightbox_keyboard_nav'         => true,
			'lightbox_show_counter'         => true,
			'lightbox_allow_set_featured'   => true,
			'lightbox_autoplay_enabled'     => false,
			'lightbox_autoplay_interval'    => 3,
			'lightbox_zoom_enabled'         => true,
			'lightbox_zoom_step'            => 0.2,
			'lightbox_max_zoom'             => 3,
			
			// Video optimization settings
			'video_enabled'                 => true,
			'video_extract_poster'          => true,
			'video_exclusive_playback'      => true,
			'video_lightbox_button'         => true,
			'video_lightbox_on_click'       => false,
			'video_autoplay_prevention'     => true,
			
			// Audio player settings (reserved)
			'audio_enabled'                 => false,
			'audio_custom_player'           => false,
		];

		$settings = get_option( 'w2p_frontend_enhancement_settings', [] );
		if ( empty( $settings ) ) {
			update_option( 'w2p_frontend_enhancement_settings', $defaults );
		}
	}

	/**
	 * Frontend asset loading (on-demand)
	 */
	public function enqueue_frontend_assets() {
		// Only load on singular posts/pages
		if ( ! is_singular() ) {
			return;
		}
		
		$settings = get_option( 'w2p_frontend_enhancement_settings', [] );
		
		// Lightbox assets
		if ( ! empty( $settings['lightbox_enabled'] ) ) {
			wp_enqueue_script(
				'wpg-lightbox',
				plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/lightbox.js',
				[ 'jquery' ],
				'1.2.0', // Feature: support 3 animation types (fade, slide, zoom)
				true
			);
			
			// Add inline script to disable theme lightbox ASAP
			wp_add_inline_script( 'wpg-lightbox', '
				// Disable Magnific Popup on article images immediately
				(function($) {
					"use strict";
					console.log("WP Genius: Preparing to disable theme lightbox...");
					
					// Capture click events before theme lightbox
					document.addEventListener("click", function(e) {
						var target = e.target;
						
						// Check if clicked element is an image
						if (target.tagName === "IMG") {
							var $img = $(target);
							// Priority 1: Check custom container
							var inCustomContainer = $img.closest("#w2p-post-content").length > 0;
							// Priority 2: Check if on single post/page
							var inSinglePage = $("body").hasClass("single") || $("body").hasClass("single-post") || $("body").hasClass("page");
							// Priority 3: Check standard article containers
							var inArticle = $img.closest(".entry-content, .post-content, article.post, article[id^=\'post-\'], article.w-grid-item").length > 0;
							
							if (inCustomContainer || inSinglePage || inArticle) {
								console.log("WP Genius: Article image clicked, preventing theme lightbox");
								e.preventDefault();
								e.stopPropagation();
								e.stopImmediatePropagation();
								
								// Manually trigger WP Genius Lightbox after preventing theme lightbox
								if (window.wpgLightbox && window.wpgLightbox.images) {
									var index = window.wpgLightbox.images.findIndex(function(img) {
										return img.element === target;
									});
									if (index >= 0) {
										console.log("WP Genius: Manually opening lightbox for image index:", index);
										window.wpgLightbox.open(index);
									} else {
										console.warn("WP Genius: Image not found in lightbox collection");
									}
								}
								
								return false;
							}
						}
					}, true); // Use capture phase (runs before bubble phase)
				})(jQuery);
			', 'before' );
			
			wp_localize_script( 'wpg-lightbox', 'wpgLightboxConfig', [
				'postId'    => get_the_ID(),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wpg_lightbox_action' ),
				'settings'  => $settings,
				'i18n'      => [
					'close'        => __( 'Close', 'wp-genius' ),
					'prev'         => __( 'Previous', 'wp-genius' ),
					'next'         => __( 'Next', 'wp-genius' ),
					'zoomIn'       => __( 'Zoom In', 'wp-genius' ),
					'zoomOut'      => __( 'Zoom Out', 'wp-genius' ),
					'setFeatured'  => __( 'Set as Featured', 'wp-genius' ),
					'autoplay'     => __( 'Autoplay', 'wp-genius' ),
					'downloading'  => __( 'Downloading...', 'wp-genius' ),
					'success'      => __( 'Featured image updated!', 'wp-genius' ),
					'error'        => __( 'Failed to update featured image.', 'wp-genius' ),
				],
			] );
		}
		
		// Plyr video player assets
		if ( ! empty( $settings['video_enabled'] ) ) {
			// Enqueue Plyr from CDN
			wp_enqueue_style(
				'plyr-css',
				'https://cdn.plyr.io/3.7.8/plyr.css',
				[],
				'3.7.8'
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

		// Reader enhancement assets
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

		// Add debug information
		wp_add_inline_script( 'wpg-reader-js', '
			console.log("WP Genius Reader: Script loaded with config:", wpgReaderConfig);
			console.log("WP Genius Reader: Reader enabled:", ' . ( ! empty( $settings['reader_enabled'] ) ? 'true' : 'false' ) . ');
		', 'before' );
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
		$settings = get_option( 'w2p_frontend_enhancement_settings', [] );
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
}
