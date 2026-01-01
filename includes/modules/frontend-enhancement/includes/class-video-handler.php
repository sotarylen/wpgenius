<?php
/**
 * Video Handler Class
 * 
 * Backend handler for video player optimization.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Video Handler
 */
class WPG_Video_Handler {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Content filter to modify video output
		add_filter( 'the_content', [ $this, 'process_video_content' ], 20 );
	}

	/**
	 * Process video content
	 * 
	 * @param string $content Post content
	 * @return string Modified content
	 */
	public function process_video_content( $content ) {
		if ( ! is_singular() ) {
			return $content;
		}

		$settings = get_option( 'w2p_frontend_enhancement_settings', [] );

		// Remove autoplay if enabled
		if ( ! empty( $settings['video_autoplay_prevention'] ) ) {
			$content = preg_replace( '/(<video[^>]+)autoplay([^>]*>)/i', '$1$2', $content );
		}

		// Wrap videos in container for additional controls
		if ( ! empty( $settings['video_lightbox_button'] ) ) {
			$content = preg_replace_callback(
				'/(<video[^>]*>.*?<\/video>)/is',
				[ $this, 'wrap_video' ],
				$content
			);
		}

		return $content;
	}

	/**
	 * Wrap video with container
	 * 
	 * @param array $matches Regex matches
	 * @return string Wrapped video HTML
	 */
	private function wrap_video( $matches ) {
		$video_html = $matches[1];
		
		// Check if already wrapped
		if ( strpos( $video_html, 'wpg-video-wrapper' ) !== false ) {
			return $video_html;
		}

		// Add wrapper with data attribute for JavaScript
		return sprintf(
			'<div class="wpg-video-wrapper" data-wpg-video="true">%s</div>',
			$video_html
		);
	}

	/**
	 * Get videos from post content
	 * 
	 * @param int $post_id Post ID
	 * @return array Array of video data
	 */
	public static function get_post_videos( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}

		$videos = [];
		$content = $post->post_content;

		// Parse HTML to extract videos
		if ( preg_match_all( '/<video[^>]*>.*?<\/video>/is', $content, $matches ) ) {
			foreach ( $matches[0] as $video_tag ) {
				// Extract source
				$src = '';
				if ( preg_match( '/src=["\']([^"\']+)["\']/i', $video_tag, $src_match ) ) {
					$src = $src_match[1];
				} elseif ( preg_match( '/<source[^>]+src=["\']([^"\']+)["\']/i', $video_tag, $src_match ) ) {
					$src = $src_match[1];
				}

				if ( $src ) {
					$videos[] = [
						'src'  => $src,
						'html' => $video_tag,
					];
				}
			}
		}

		return $videos;
	}
}
