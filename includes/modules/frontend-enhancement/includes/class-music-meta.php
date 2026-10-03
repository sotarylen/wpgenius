<?php
/**
 * Music Metadata Handler Class
 *
 * Augments the WordPress core metadata pipeline (framework-native getID3,
 * zero second parse) to persist USLT lyrics into a dedicated postmeta key
 * decoupled from core metadata lifecycle. Cover art is already handled by
 * core: WP >= 7.1 creates a cover attachment from ID3 APIC (see
 * wp_generate_attachment_metadata) and stores it as the audio attachment
 * thumbnail (get_post_thumbnail_id) with _cover_hash deduplication.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Music Metadata Handler
 */
class WPG_Music_Meta {

	/**
	 * Lyric postmeta key (lifecycle-decoupled from core metadata).
	 */
	const LYRIC_META = 'wpg_audio_lyric';

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Lyric handoff cache keyed by filename hash (same request only).
	 * Each entry holds { uslt, lrc } strings; finalize picks per priority.
	 *
	 * @var array
	 */
	private static $pending_lyric = array();

	/**
	 * Constructor
	 *
	 * @param array $settings Module settings.
	 */
	public function __construct( $settings = array() ) {
		$this->settings = $settings;

		// Only wire the pipeline when the feature and tag extraction are on.
		if ( ! empty( $this->settings['music_enabled'] ) && ! empty( $this->settings['music_tag_extract'] ) ) {
			add_filter( 'wp_read_audio_metadata', array( $this, 'capture_lyric' ), 20, 4 );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'finalize_metadata' ), 20, 2 );
		}
	}

	/**
	 * Capture USLT lyrics from the core getID3 analysis.
	 *
	 * WP bundles getID3, which stores the USLT text as comments key
	 * 'unsynchronised_lyric' (core copies it into $metadata already); older
	 * tag layouts may surface it under other keys, hence the fallback chain.
	 *
	 * @param array       $metadata    Core audio metadata.
	 * @param string      $file        Absolute file path.
	 * @param string|null $file_format File format.
	 * @param array       $data        Raw getID3 analysis.
	 * @return array
	 */
	public function capture_lyric( $metadata, $file, $file_format, $data ) {
		if ( ! is_string( $file ) || '' === $file ) {
			return $metadata;
		}

		$uslt = '';
		if ( ! empty( $metadata['unsynchronised_lyric'] ) ) {
			$uslt = $metadata['unsynchronised_lyric'];
		} elseif ( ! empty( $data['id3v2']['comments']['unsynchronised_lyric'][0] ) ) {
			$uslt = $data['id3v2']['comments']['unsynchronised_lyric'][0];
		} elseif ( ! empty( $data['comments']['lyrics'][0] ) ) {
			$uslt = $data['comments']['lyrics'][0];
		} elseif ( ! empty( $data['id3v2']['USLT'][0]['data'] ) ) { // Dead on WP 7.1 (getID3 unsets data); kept for cross-version defense.
			$uslt = $data['id3v2']['USLT'][0]['data'];
		}

		$entry = array(
			'uslt' => ( '' !== $uslt ) ? self::normalize_lyric( $uslt ) : '',
			'lrc'  => self::read_sidecar_lrc( $file ),
		);

		if ( '' !== $entry['uslt'] || '' !== $entry['lrc'] ) {
			// Key by basename hash: wp_generate_attachment_metadata only sees the
			// relative metadata['file'], so absolute/relative paths must resolve
			// to the same bucket within this request. Known boundary: two same-
			// named files in one request would share a bucket (rare; last write wins).
			self::$pending_lyric[ md5( basename( $file ) ) ] = $entry;
		}

		return $metadata;
	}

	/**
	 * Read a sidecar .lrc file next to the audio file, if present and small.
	 *
	 * @param string $file Absolute audio path.
	 * @return string
	 */
	private static function read_sidecar_lrc( $file ) {
		$lrc_path = preg_replace( '/\.[^.]+$/', '.lrc', $file );
		if ( $lrc_path === $file || ! file_exists( $lrc_path ) || ! is_readable( $lrc_path ) ) {
			return '';
		}
		if ( filesize( $lrc_path ) > 65536 ) {
			return '';
		}

		$contents = file_get_contents( $lrc_path );
		if ( false === $contents ) {
			return '';
		}

		return self::normalize_lyric( $contents );
	}

	/**
	 * Persist the captured lyric as a dedicated postmeta key.
	 *
	 * @param array $metadata      Generated attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function finalize_metadata( $metadata, $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $this->is_audio_metadata( $metadata ) ) {
			return $metadata;
		}

		// Audio metadata has no 'file' key (images only), so resolve the
		// real attached path; basenames align with the capture bucket.
		$attached = get_attached_file( $attachment_id );
		$name     = $attached ? basename( $attached ) : ( isset( $metadata['file'] ) ? basename( $metadata['file'] ) : '' );
		$hash     = md5( $name );

		if ( isset( self::$pending_lyric[ $hash ] ) ) {
			$entry = self::$pending_lyric[ $hash ];
			unset( self::$pending_lyric[ $hash ] );

			$priority = isset( $this->settings['music_lyric_priority'] ) ? $this->settings['music_lyric_priority'] : 'file';
			$lyric    = ( 'lrc' === $priority && '' !== $entry['lrc'] ) ? $entry['lrc'] : $entry['uslt'];

			// Fall back to the other source when the preferred one is empty.
			if ( '' === $lyric && '' !== $entry['lrc'] ) {
				$lyric = $entry['lrc'];
			}

			if ( '' !== $lyric ) {
				$existing = get_post_meta( $attachment_id, self::LYRIC_META, true );
				if ( $existing !== $lyric ) {
					update_post_meta( $attachment_id, self::LYRIC_META, $lyric );
				}
			}
		}

		return $metadata;
	}

	/**
	 * Normalize raw USLT text to sanitized UTF-8.
	 *
	 * @param string $raw Raw lyric text from the file.
	 * @return string
	 */
	private static function normalize_lyric( $raw ) {
		$text = wp_strip_all_tags( (string) $raw );

		// Coerce non-UTF-8 ID3 payloads (ISO-8859-1 / GBK etc.) to UTF-8.
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$converted = @iconv( 'GB18030', 'UTF-8//IGNORE', $text );
			if ( false !== $converted ) {
				$text = $converted;
			}
		}

		return sanitize_textarea_field( trim( $text ) );
	}

	/**
	 * Whether metadata belongs to an audio file.
	 *
	 * @param array $metadata Attachment metadata.
	 * @return bool
	 */
	private function is_audio_metadata( $metadata ) {
		if ( ! empty( $metadata['mime_type'] ) && 0 === strpos( $metadata['mime_type'], 'audio/' ) ) {
			return true;
		}

		$audio_formats = array( 'mp3', 'flac', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'wma' );
		return ! empty( $metadata['fileformat'] ) && in_array( strtolower( $metadata['fileformat'] ), $audio_formats, true );
	}
}
