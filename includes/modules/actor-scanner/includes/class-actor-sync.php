<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Actor sync service
 *
 * Creates / updates Humans taxonomy terms for actresses and downloads their
 * avatar from Gfriends into the Media Library. Handles the ACF fields
 * (human_role, human_nickname, human_desc, human_url, human_avatar, human_type)
 * that the site already uses.
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */
class W2P_Actor_Sync {

	/**
	 * Gfriends client.
	 *
	 * @var W2P_Gfriends_Client
	 */
	protected $gf;

	/**
	 * Matcher (used to look up existing aliases).
	 *
	 * @var W2P_Actor_Matcher
	 */
	protected $matcher;

	/**
	 * Constructor.
	 *
	 * @param W2P_Gfriends_Client $gf      Gfriends client.
	 * @param W2P_Actor_Matcher   $matcher Matcher instance.
	 */
	public function __construct( $gf, $matcher ) {
		$this->gf      = $gf;
		$this->matcher = $matcher;
	}

	/**
	 * Ensure a Humans term exists for the given actor info.
	 *
	 * Creates the term if missing (name: Gfriends Japanese name, or the Chinese
	 * name when no Gfriends match), then fills/enriches the ACF meta.
	 *
	 * @param array $actor Resolved actor info from W2P_Actor_Matcher::resolve_all().
	 * @return int Term id (0 on failure).
	 */
	public function ensure_term( $actor ) {
		$this->matcher->load_terms();

		if ( ! empty( $actor['term_id'] ) ) {
			return $this->enrich_existing( $actor['term_id'], $actor );
		}

		// Need to create a new term.
		$name = ! empty( $actor['gf_name'] ) ? $actor['gf_name'] : $actor['name'];

		// Guard: never create the term if the name is empty or a stopword.
		if ( '' === trim( $name ) || mb_strlen( $name ) < 2 ) {
			return 0;
		}

		// Re-check under the Gfriends name too (term may exist under the JP name).
		$existing = $this->find_term_by_alias( $name );
		if ( $existing ) {
			return $this->enrich_existing( $existing, $actor );
		}

		$term = wp_insert_term(
			$name,
			'humans',
			array(
				'slug'        => sanitize_title( $name ),
				'description' => '',
			)
		);

		if ( is_wp_error( $term ) ) {
			// Slug collision → retry with suffix.
			$term = wp_insert_term(
				$name,
				'humans',
				array(
					'slug' => sanitize_title( $name ) . '-' . wp_rand( 100, 999 ),
				)
			);
		}

		if ( is_wp_error( $term ) ) {
			W2P_Logger::error( 'Actor term creation failed for "' . $name . '": ' . $term->get_error_message(), 'actor-scanner' );
			return 0;
		}

		$term_id = (int) $term['term_id'];

		// Initialize role + nickname.
		update_term_meta( $term_id, 'human_role', '演员' );

		$aliases    = array();
		$alias_pool = array( $actor['name'] );
		if ( ! empty( $actor['aliases'] ) && is_array( $actor['aliases'] ) ) {
			$alias_pool = array_merge( $alias_pool, $actor['aliases'] );
		}
		foreach ( $alias_pool as $a ) {
			$a = trim( (string) $a );
			if ( '' !== $a && $a !== $name && ! in_array( $a, $aliases, true ) ) {
				$aliases[] = $a;
			}
		}
		if ( ! empty( $aliases ) ) {
			update_term_meta( $term_id, 'human_nickname', implode( ', ', $aliases ) );
		}

		// Avatar from Gfriends.
		$this->sync_avatar( $term_id, $actor );

		return $term_id;
	}

	/**
	 * Find an existing term by alias (name or nickname), exact normalized match.
	 *
	 * @param string $name Candidate name.
	 * @return int Term id or 0.
	 */
	protected function find_term_by_alias( $name ) {
		$key = $this->matcher->normalize_key( $name );
		if ( '' === $key ) {
			return 0;
		}
		// Bypass the object-cache / query cache: within one batch several posts may
		// mention the same actress, and the term we just created must be found.
		global $wpdb;

		$sql     = $wpdb->prepare(
			"SELECT t.term_id FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			 WHERE tt.taxonomy = 'humans' AND t.name = %s LIMIT 1",
			$name
		);
		$term_id = (int) $wpdb->get_var( $sql );
		if ( $term_id ) {
			return $term_id;
		}

		// Fall back to nickname aliases (direct SQL join on termmeta).
		$sql     = $wpdb->prepare(
			"SELECT tm.term_id FROM {$wpdb->termmeta} tm
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
			 WHERE tt.taxonomy = 'humans' AND tm.meta_key = 'human_nickname' AND tm.meta_value LIKE %s LIMIT 1",
			'%' . $wpdb->esc_like( $name ) . '%'
		);
		$term_id = (int) $wpdb->get_var( $sql );

		return $term_id;
	}

	/**
	 * Enrich an existing term: fill empty ACF fields and refresh the avatar.
	 *
	 * @param int   $term_id Term id.
	 * @param array $actor   Resolved actor info.
	 * @return int
	 */
	public function enrich_existing( $term_id, $actor ) {
		$term_id = (int) $term_id;
		if ( ! $term_id ) {
			return 0;
		}

		$term = get_term( $term_id, 'humans' );
		if ( ! $term || is_wp_error( $term ) ) {
			return 0;
		}

		// Role: ensure it includes 演员 (never strips an existing role).
		$role = (string) get_term_meta( $term_id, 'human_role', true );
		if ( '' === $role ) {
			update_term_meta( $term_id, 'human_role', '演员' );
		} elseif ( false === strpos( $role, '演员' ) ) {
			update_term_meta( $term_id, 'human_role', $role . ',演员' );
		}

		// Nickname: append the Chinese/other aliases if not already present.
		$alias_pool = array( $actor['name'] );
		if ( ! empty( $actor['aliases'] ) && is_array( $actor['aliases'] ) ) {
			$alias_pool = array_merge( $alias_pool, $actor['aliases'] );
		}
		$changed = false;
		$nick    = (string) get_term_meta( $term_id, 'human_nickname', true );
		$parts   = $nick ? preg_split( '/[\s,，、;；]+/u', $nick ) : array();

		foreach ( $alias_pool as $a ) {
			$a = trim( (string) $a );
			if ( '' === $a || $a === $term->name ) {
				continue;
			}
			$found = false;
			foreach ( $parts as $p ) {
				if ( $this->matcher->normalize_key( $p ) === $this->matcher->normalize_key( $a ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$parts[] = $a;
				$changed = true;
			}
		}
		if ( $changed ) {
			update_term_meta( $term_id, 'human_nickname', implode( ', ', array_filter( $parts ) ) );
		}

		// Avatar: download from Gfriends when missing.
		$this->sync_avatar( $term_id, $actor );

		return $term_id;
	}

	/**
	 * Download the Gfriends avatar into the Media Library and set human_avatar.
	 *
	 * Only runs when the term has no human_avatar yet (enrichment), or when the
	 * resolved source is gfriends_exact/fuzzy and the term lacks one.
	 *
	 * @param int   $term_id Term id.
	 * @param array $actor   Actor info (may carry gf_name).
	 * @return int Attachment id or 0.
	 */
	public function sync_avatar( $term_id, $actor ) {
		$term_id = (int) $term_id;
		if ( ! $term_id ) {
			return 0;
		}

		// Already has an avatar? Keep it (don't overwrite user picks).
		$current = (int) get_term_meta( $term_id, 'human_avatar', true );
		if ( $current > 0 ) {
			return $current;
		}

		$gf_name = ! empty( $actor['gf_name'] ) ? $actor['gf_name'] : '';
		if ( '' === $gf_name ) {
			return 0;
		}

		$url = $this->gf->get_best_avatar_url( $gf_name );
		if ( '' === $url ) {
			return 0;
		}

		$term  = get_term( $term_id, 'humans' );
		$title = $term ? $term->name : $gf_name;

		$attachment_id = $this->download_avatar( $url, $title );
		if ( $attachment_id > 0 ) {
			update_term_meta( $term_id, 'human_avatar', $attachment_id );
		}

		return $attachment_id;
	}

	/**
	 * Download an avatar image into the Media Library.
	 *
	 * @param string $url   Avatar URL.
	 * @param string $title Attachment title.
	 * @return int Attachment id or 0.
	 */
	public function download_avatar( $url, $title ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Avoid duplicate downloads: check by source URL meta.
		$existing = $this->find_attachment_by_source( $url );
		if ( $existing ) {
			return $existing;
		}

		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			W2P_Logger::error( 'Avatar download failed: ' . $tmp->get_error_message(), 'actor-scanner' );
			return 0;
		}

		$file_array = array(
			'name'     => sanitize_file_name( $title . '.' . pathinfo( $url, PATHINFO_EXTENSION ) ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0, $title );

		if ( is_wp_error( $attachment_id ) ) {
			// Fallback: try with .jpg extension when the source had no clear ext.
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			W2P_Logger::error( 'Avatar sideload failed: ' . $attachment_id->get_error_message(), 'actor-scanner' );
			return 0;
		}

		// Record source URL so we never import the same avatar twice.
		update_post_meta( $attachment_id, '_w2p_gfriends_source', esc_url_raw( $url ) );

		return (int) $attachment_id;
	}

	/**
	 * Find an existing attachment previously imported from a Gfriends URL.
	 *
	 * @param string $url Source URL.
	 * @return int Attachment id or 0.
	 */
	protected function find_attachment_by_source( $url ) {
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'meta_key'       => '_w2p_gfriends_source', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => esc_url_raw( $url ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'         => 'ids',
			)
		);
		return ! empty( $found ) ? (int) $found[0] : 0;
	}

	/**
	 * Assign Humans terms to a post (append mode — never removes existing).
	 *
	 * @param int   $post_id Post id.
	 * @param int[] $term_ids Term ids.
	 * @return bool
	 */
	public function assign_to_post( $post_id, $term_ids ) {
		$post_id  = (int) $post_id;
		$term_ids = array_map( 'absint', $term_ids );
		$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );

		if ( ! $post_id || empty( $term_ids ) ) {
			return false;
		}

		// Merge with existing humans terms (append).
		$existing = wp_get_object_terms( $post_id, 'humans', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $existing ) ) {
			$existing = array();
		}

		$merged = array_values( array_unique( array_merge( $existing, $term_ids ) ) );

		$result = wp_set_object_terms( $post_id, $merged, 'humans' );

		return ! is_wp_error( $result );
	}
}
