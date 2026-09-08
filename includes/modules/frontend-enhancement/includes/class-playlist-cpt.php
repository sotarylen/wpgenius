<?php
/**
 * Playlist CPT Handler Class
 *
 * Registers the wpg_playlist post type with a track editor meta box.
 * Tracks are stored as an ordered array of audio attachment IDs; all display
 * data (title, artist, url, cover, lyrics) is resolved from the attachments
 * at render time so deleted files surface as fallback cards instead of
 * broken URLs.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Playlist CPT Handler
 */
class WPG_Playlist_CPT {

	/**
	 * Tracks postmeta key.
	 */
	const TRACKS_META = 'wpg_playlist_tracks';

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

		add_action( 'init', array( $this, 'register_playlist_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_tracks_meta_box' ) );
		add_action( 'save_post_wpg_playlist', array( $this, 'save_tracks' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the playlist post type.
	 *
	 * @return void
	 */
	public function register_playlist_type() {
		register_post_type(
			'wpg_playlist',
			array(
				'labels'              => array(
					'name'          => __( 'Music Playlists', 'wp-genius' ),
					'singular_name' => __( 'Music Playlist', 'wp-genius' ),
					'add_new_item'  => __( 'Add New Playlist', 'wp-genius' ),
					'edit_item'     => __( 'Edit Playlist', 'wp-genius' ),
					'search_items'  => __( 'Search Playlists', 'wp-genius' ),
					'not_found'     => __( 'No playlists found.', 'wp-genius' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'upload.php',
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-album',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
			)
		);
	}

	/**
	 * Register the tracks meta box.
	 *
	 * @return void
	 */
	public function register_tracks_meta_box() {
		add_meta_box(
			'wpg-playlist-tracks',
			__( 'Playlist Tracks', 'wp-genius' ),
			array( $this, 'render_tracks_meta_box' ),
			'wpg_playlist',
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue admin assets on playlist screens.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'wpg_playlist' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'wpg-playlist-admin-css',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/css/music.css',
			array(),
			W2P_VERSION
		);
		wp_enqueue_script(
			'wpg-playlist-admin-js',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/js/wpg-playlist-admin.js',
			array( 'jquery', 'media-editor' ),
			W2P_VERSION,
			true
		);
		wp_localize_script(
			'wpg-playlist-admin-js',
			'wpgPlaylistAdmin',
			array(
				'chooseTitle' => __( 'Select Audio Files', 'wp-genius' ),
				'addButton'   => __( 'Add Tracks', 'wp-genius' ),
				'removeTitle' => __( 'Remove track', 'wp-genius' ),
				'missing'     => __( 'Missing audio file', 'wp-genius' ),
				'mimeType'    => 'audio',
			)
		);
	}

	/**
	 * Render the tracks meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_tracks_meta_box( $post ) {
		wp_nonce_field( 'wpg_playlist_meta', 'wpg_playlist_meta_nonce' );

		$ids  = self::get_track_ids( $post->ID );
		$rows = array();

		if ( $ids ) {
			$attachments = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'include'        => $ids,
					'orderby'        => 'post__in',
					'posts_per_page' => -1,
				)
			);
			foreach ( $attachments as $attachment ) {
				$rows[] = array(
					'id'    => $attachment->ID,
					'title' => $attachment->post_title,
				);
			}
		}

		$has_missing = count( $ids ) > count( $rows );
		?>		<input type="hidden" id="wpg-playlist-tracks-field" name="wpg_playlist_tracks" value="<?php echo esc_attr( wp_json_encode( $ids ) ); ?>" />
		<div class="wpg-playlist-editor">
			<ul id="wpg-playlist-tracks-list" class="wpg-playlist-editor__list">
				<?php foreach ( $rows as $row ) : ?>
					<li class="wpg-playlist-editor__row" draggable="true" data-id="<?php echo esc_attr( $row['id'] ); ?>">
						<span class="wpg-playlist-editor__handle" aria-hidden="true">&#8801;</span>
						<span class="wpg-playlist-editor__name"><?php echo esc_html( $row['title'] ); ?></span>
						<button type="button" class="button-link wpg-playlist-editor__remove" data-action="remove"><?php esc_html_e( 'Remove', 'wp-genius' ); ?></button>
					</li>
				<?php endforeach; ?>
				<?php if ( $has_missing ) : ?>
					<li class="wpg-playlist-editor__row wpg-playlist-editor__row--missing">
						<span class="wpg-playlist-editor__name"><?php esc_html_e( 'Some tracks are missing their audio files.', 'wp-genius' ); ?></span>
					</li>
				<?php endif; ?>
			</ul>
			<p>
				<button type="button" class="button" id="wpg-playlist-add-tracks"><?php echo esc_html( __( 'Add Tracks', 'wp-genius' ) ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Pick audio files from the media library. Drag rows to reorder.', 'wp-genius' ); ?></p>
		</div>
		<template id="wpg-playlist-track-tpl">
			<li class="wpg-playlist-editor__row" draggable="true">
				<span class="wpg-playlist-editor__handle" aria-hidden="true">&#8801;</span>
				<span class="wpg-playlist-editor__name"></span>
				<button type="button" class="button-link wpg-playlist-editor__remove" data-action="remove"><?php esc_html_e( 'Remove', 'wp-genius' ); ?></button>
			</li>
		</template>
		<?php
	}

	/**
	 * Save the track order (IDs only; display data resolves at render).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_tracks( $post_id, $post ) {
		// Classic guards: autosaves, revisions and non-edit contexts.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'wpg_playlist' !== $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST['wpg_playlist_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wpg_playlist_meta_nonce'] ), 'wpg_playlist_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['wpg_playlist_tracks'] ) ) {
			return;
		}

		// Input arrives as a JSON array of attachment IDs (WP slashed).
		$raw     = wp_unslash( $_POST['wpg_playlist_tracks'] );
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			delete_post_meta( $post_id, self::TRACKS_META );
			return;
		}

		$ids = array_values( array_unique( array_map( 'absint', $decoded ) ) );
		$ids = array_filter( $ids );

		if ( $ids ) {
			update_post_meta( $post_id, self::TRACKS_META, $ids );
		} else {
			delete_post_meta( $post_id, self::TRACKS_META );
		}
	}

	/**
	 * Get sanitized track IDs for a playlist.
	 *
	 * @param int $playlist_id Playlist post ID.
	 * @return int[]
	 */
	public static function get_track_ids( $playlist_id ) {
		$value = get_post_meta( $playlist_id, self::TRACKS_META, true );

		// Accept both native serialized arrays and JSON strings: observed that
		// CLI writes stored a serialized array while web requests read a JSON
		// string back (object-cache normalization). Keep both until the
		// intermediate layer is pinned to a single encoding.
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			}
		}

		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$ids = array_values( array_unique( array_map( 'absint', $value ) ) );
		return array_filter( $ids );
	}
}