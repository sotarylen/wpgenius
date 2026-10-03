<?php
/**
 * Music Handler Class
 *
 * Renders the [wpg_playlist] shortcode. Track display data (title, artist,
 * URL, cover, lyrics) is resolved from audio attachments at render time in a
 * single post cache batch; missing or deleted files degrade to fallback cards
 * instead of broken links.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Music Handler
 */
class WPG_Music_Handler {

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor
	 *
	 * @param array $settings Module settings.
	 */
	public function __construct( $settings = array() ) {
		$this->settings = $settings;
		add_shortcode( 'wpg_playlist', array( $this, 'render_playlist' ) );
		add_shortcode( 'wpg_music', array( $this, 'render_embed' ) );
	}

	/**
	 * Render a playlist shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_playlist( $atts ) {

		$atts        = shortcode_atts( array( 'id' => 0 ), $atts, 'wpg_playlist' );
		$playlist_id = absint( $atts['id'] );

		if ( ! $playlist_id ) {

			return '';
		}

		$playlist = get_post( $playlist_id );
		if ( ! $playlist || 'wpg_playlist' !== $playlist->post_type || 'publish' !== $playlist->post_status ) {

			return '';
		}

		$track_ids = WPG_Playlist_CPT::get_track_ids( $playlist_id );
		if ( empty( $track_ids ) ) {

			return '';
		}

		// Batch-resolve attachments + postmeta in two queries.
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'include'        => $track_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1,
			)
		);
		update_meta_cache( 'post', $track_ids );

		$by_id = array();
		foreach ( $attachments as $attachment ) {
			$by_id[ $attachment->ID ] = $attachment;
		}

		$aplayer_tracks = array();
		$missing_tracks = array();
		$index          = 0;

		foreach ( $track_ids as $track_id ) {
			$track_id   = absint( $track_id );
			$attachment = isset( $by_id[ $track_id ] ) ? $by_id[ $track_id ] : null;

			$artist = '';
			$meta   = $attachment ? get_post_meta( $track_id, '_wp_attachment_metadata', true ) : array();

			if ( is_array( $meta ) ) {
				if ( ! empty( $meta['artist'] ) ) {
					$artist = $meta['artist'];
				}
			}

			// Prefer the ID3 title, fall back to the attachment title, then index.
			$name = ( is_array( $meta ) && ! empty( $meta['title'] ) ) ? $meta['title'] : '';
			$name = $name ? $name : ( $attachment ? $attachment->post_title : '' );
			if ( ! $name ) {
				// translators: %d: Track index in the playlist.
				$name = sprintf( __( 'Track %d', 'wp-genius' ), $index + 1 );
			}

			$url   = $attachment ? wp_get_attachment_url( $track_id ) : '';
			$url   = self::is_http_url( $url ) ? $url : '';
			$cover = '';
			$lyric = '';

			if ( $attachment ) {
				// Cover art is created natively by WP core (audio thumbnail),
				// see wp_generate_attachment_metadata / _cover_hash deduplication.
				$cover_id = absint( get_post_thumbnail_id( $track_id ) );
				if ( $cover_id ) {
					$cover_url = wp_get_attachment_image_url( $cover_id, 'thumbnail' );
					$cover     = self::is_http_url( $cover_url ) ? $cover_url : '';
				}
				$lyric = get_post_meta( $track_id, 'wpg_audio_lyric', true );
				$lyric = is_string( $lyric ) ? sanitize_textarea_field( $lyric ) : '';
			}

			++$index;

			if ( '' !== $url ) {
				$aplayer_tracks[] = array(
					'name'   => $name,
					'artist' => $artist,
					'url'    => $url,
					'cover'  => $cover,
					'lrc'    => $lyric,
				);
			} else {
				$missing_tracks[] = array( 'name' => $name );
			}
		}

		if ( empty( $aplayer_tracks ) ) {

			return '';
		}

		$mode  = isset( $this->settings['music_player_mode'] ) ? $this->settings['music_player_mode'] : 'card';
		$order = isset( $this->settings['music_playlist_order'] ) ? $this->settings['music_playlist_order'] : 'sequence';

		$data = array(
			'tracks'         => $aplayer_tracks,
			'missing_tracks' => $missing_tracks,
			'mode'           => ( 'mini' === $mode ) ? 'mini' : 'card',
			'order'          => in_array( $order, array( 'sequence', 'loop', 'random' ), true ) ? $order : 'sequence',
			'playlist_title' => $playlist->post_title,
			'track_count'    => count( $aplayer_tracks ),
		);

		$tracks         = $data['tracks'];
		$playlist_title = $data['playlist_title'];
		$track_count    = $data['track_count'];

		ob_start();
		$view = plugin_dir_path( __FILE__ ) . '../views/music-player.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
		return (string) ob_get_clean();
	}

	/**
	 * Allow only http(s) URLs (protocol whitelist).
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	private static function is_http_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$scheme = parse_url( $url, PHP_URL_SCHEME );
		return in_array( $scheme, array( 'http', 'https' ), true );
	}

	/**
	 * Render a platform embed ([wpg_music]).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_embed( $atts ) {
		$atts = shortcode_atts(
			array(
				'platform' => 'netease',
				'type'     => 'song',
				'id'       => 0,
				'auto'     => 0,
			),
			$atts,
			'wpg_music'
		);

		$platform = sanitize_key( $atts['platform'] );
		$type     = in_array( $atts['type'], array( 'playlist', 'song' ), true ) ? $atts['type'] : 'song';
		$id       = absint( $atts['id'] );
		$auto     = $atts['auto'] ? 1 : 0;

		if ( ! $id || 'netease' !== $platform ) {
			return '';
		}

		$embed_url = self::netease_embed_url( $id, $type, $auto );
		$fallback  = self::netease_fallback( $id, $type );
		$lx_link   = $this->lx_open_playlist_link( $platform, $id );

		// Serve the embed directly; the fallback line plus the LX push stay
		// visible so a copyright-locked or offline embed degrades gracefully.
		ob_start();
		$view = plugin_dir_path( __FILE__ ) . '../views/music-embed.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
		return (string) ob_get_clean();
	}

	/**
	 * NetEase outchain embed URL (song 66px bar, playlist 450px panel).
	 *
	 * @param int    $id   NetEase song/playlist id.
	 * @param string $type song|playlist.
	 * @param int    $auto autoplay flag.
	 * @return string
	 */
	private static function netease_embed_url( $id, $type, $auto ) {
		$height = ( 'playlist' === $type ) ? 450 : 66;
		return add_query_arg(
			array(
				'type'   => 2,
				'id'     => $id,
				'auto'   => $auto,
				'height' => $height,
			),
			'https://music.163.com/outchain/player'
		);
	}

	/**
	 * Fallback card payload when the embed cannot render.
	 *
	 * @param int    $id   NetEase id.
	 * @param string $type song|playlist.
	 * @return array
	 */
	private static function netease_fallback( $id, $type ) {
		$pc     = ( 'playlist' === $type );
		$target = $pc
			? 'https://music.163.com/#/playlist?id=' . $id
			: 'https://music.163.com/#/song?id=' . $id;

		return array(
			'name'   => $pc ? __( 'NetEase playlist', 'wp-genius' ) : __( 'NetEase song', 'wp-genius' ),
			'url'    => esc_url_raw( $target ),
			'source' => 'netease',
		);
	}

	/**
	 * "Open in LX Music" deep link (protocol whitelist at the call site so
	 * esc_url keeps lxmusic:// instead of stripping it).
	 *
	 * @param string $platform Source slug (netease → wy).
	 * @param int    $id       Source id.
	 * @return string
	 */
	private function lx_open_playlist_link( $platform, $id ) {
		if ( ! $id ) {
			return '';
		}

		$source = ( 'netease' === $platform ) ? 'wy' : $platform;
		$link   = 'lxmusic://songlist/open/' . urlencode( $source ) . '/' . absint( $id );

		// Only build the link for users allowed to see the LX dock.
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return esc_url( $link, array( 'lxmusic', 'http', 'https' ) );
	}
}
