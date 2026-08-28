<?php
/**
 * Smart AUI — Real-time Orphan Media Binding (save_post Reverse Binding + URL Rewrite + CLI Batch Binding)
 *
 * Provides real-time and batch orphan media association for posts:
 *   1. save_post real-time binding: Extracts referenced media IDs (wp-image-{ID}, wp-video-{ID},
 *      data-id, data-attachment-id, [video id="..."]) from post content on save. Binds unattached
 *      orphan attachments (post_parent=0) to the current post (first-reference-first-served),
 *      injects media IDs into video tags, and rewrites local /wp-content/uploads/ paths to bucket /wp-media/ URLs.
 *   2. wp media-bind-orphans: Batches through posts via offset/limit to perform identical binding
 *      and URL rewriting across existing content (--dry-run preview, --post-type restriction supported).
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Real-time Orphan Media Binding Class.
 */
class W2P_SmartAUI_Media_Orphan_Bind {

	/**
	 * save_post callback priority.
	 *
	 * @var int
	 */
	const SAVE_POST_PRIORITY = 20;

	/**
	 * CLI batch size limit to prevent memory and Redis connection exhaustion.
	 *
	 * @var int
	 */
	const CLI_BATCH_MAX = 2000;

	/**
	 * Candidate file extensions to test when resolving legacy uploads URLs in post content.
	 *
	 * @var string[]
	 */
	const OLD_EXT_CANDIDATES = array( 'webp', 'jpg', 'jpeg', 'png', 'gif' );

	/**
	 * Re-entrancy guard: Prevents infinite recursion when wp_update_post fires save_post inside adopt.
	 *
	 * @var bool
	 */
	private $processing = false;

	/**
	 * In-memory cache: URL to Attachment ID mapping to prevent duplicate DB queries during the same request.
	 *
	 * @var array<string, int>
	 */
	private static $url_to_id_cache = array();

	/**
	 * Constructor: Mount save_post hook and CLI command registration.
	 */
	public function __construct() {
		add_action( 'save_post', array( $this, 'realtime_bind_orphans' ), self::SAVE_POST_PRIORITY, 2 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'cli_init', array( $this, 'register_cli_commands' ) );
		}
	}

	/**
	 * On post save, bind referenced orphan media (images and videos) to current post,
	 * inject media IDs into video shortcodes and HTML video tags, and rewrite local uploads URLs to bucket URLs.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function realtime_bind_orphans( $post_id, $post ) {
		// Coexistence guard: Yield to child theme if legacy callback is defined.
		if ( function_exists( 'w2p_realtime_bind_orphans' ) ) {
			return;
		}

		// Skip autosaves, revisions, attachments, auto-drafts, and trash.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'attachment' === $post->post_type ) {
			return;
		}
		if ( in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
			return;
		}

		// Prevent re-entrancy.
		if ( $this->processing ) {
			return;
		}

		$content = isset( $post->post_content ) ? $post->post_content : '';
		if ( '' === $content ) {
			return;
		}

		$this->processing = true;
		try {
			// 1. Process video content, inject media IDs ([video id="..."] and <video class="wp-video-..." data-id="...">).
			$video_ids       = array();
			$updated_content = $this->process_video_content( $content, $video_ids );

			// If video IDs were injected, update post content directly in DB to prevent re-triggering save_post.
			if ( $updated_content !== $content ) {
				global $wpdb;
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $updated_content ),
					array( 'ID' => $post_id )
				);
				clean_post_cache( $post_id );
				wp_cache_delete( $post_id, 'posts' );
				$content = $updated_content;
			}

			// 2. Collect all referenced media IDs (images + videos).
			$ids = array_unique( array_merge( $this->extract_media_ids( $content ), $video_ids ) );
			if ( empty( $ids ) ) {
				return;
			}

			// Only collect attachments that are currently unattached (post_parent=0).
			$pairs = array();
			foreach ( $ids as $mid ) {
				$att = get_post( $mid );
				if ( ! $att || 'attachment' !== $att->post_type ) {
					continue;
				}
				if ( 0 !== (int) $att->post_parent ) {
					continue; // Already attached to another post.
				}
				$pairs[] = array( 'orphan_id' => $mid, 'post_id' => $post_id );
			}
			if ( empty( $pairs ) ) {
				return;
			}

			$this->adopt_orphans( $pairs );
		} finally {
			$this->processing = false;
		}
	}

	/**
	 * Register wp media-bind-orphans command.
	 *
	 * @return void
	 */
	public function register_cli_commands() {
		if ( function_exists( 'w2p_extract_media_ids_for_bind' )
			|| function_exists( 'w2p_ensure_orphan_service' )
			|| ( method_exists( 'WP_CLI', 'has_command' ) && WP_CLI::has_command( 'media-bind-orphans' ) )
		) {
			WP_CLI::warning( __( 'Detected child theme CLI commands (media-bind-orphans). Plugin CLI registration skipped to prevent conflict.', 'wp-genius' ) );
			return;
		}

		WP_CLI::add_command( 'media-bind-orphans', array( $this, 'cli_bind_orphans' ) );
	}

	/**
	 * wp media-bind-orphans — Batches through posts to bind referenced orphan media.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments: --limit / --offset / --post-type / --dry-run.
	 * @return void
	 */
	public function cli_bind_orphans( $args, $assoc_args ) {
		$limit     = isset( $assoc_args['limit'] ) ? max( 1, min( self::CLI_BATCH_MAX, (int) $assoc_args['limit'] ) ) : 500;
		$offset    = isset( $assoc_args['offset'] ) ? max( 0, (int) $assoc_args['offset'] ) : 0;
		$post_type = isset( $assoc_args['post-type'] ) ? sanitize_key( $assoc_args['post-type'] ) : '';
		$dry_run   = isset( $assoc_args['dry-run'] );

		global $wpdb;
		if ( '' !== $post_type ) {
			$pt_sql = $wpdb->prepare( ' AND post_type = %s', $post_type );
		} else {
			$pt_sql = " AND post_type NOT IN ('attachment','revision')";
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status != 'trash' {$pt_sql} ORDER BY ID ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		if ( empty( $ids ) ) {
			/* translators: %d: Offset number. */
			WP_CLI::success( sprintf( __( 'No more posts found (offset=%d)', 'wp-genius' ), $offset ) );
			return;
		}

		$pairs = array();
		foreach ( $ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post || empty( $post->post_content ) ) {
				continue;
			}

			$content         = $post->post_content;
			$video_ids       = array();
			$updated_content = $this->process_video_content( $content, $video_ids );

			if ( ! $dry_run && $updated_content !== $content ) {
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $updated_content ),
					array( 'ID' => $pid )
				);
				wp_cache_delete( $pid, 'posts' );
				$content = $updated_content;
			}

			$mids = array_unique( array_merge( $this->extract_media_ids( $content ), $video_ids ) );
			foreach ( $mids as $mid ) {
				$att = get_post( $mid );
				if ( ! $att || 'attachment' !== $att->post_type ) {
					continue;
				}
				if ( 0 !== (int) $att->post_parent ) {
					continue; // First reference wins.
				}
				$pairs[] = array( 'orphan_id' => $mid, 'post_id' => $pid );
			}
		}

		if ( empty( $pairs ) ) {
			/* translators: 1: Batch post count, 2: Offset number. */
			WP_CLI::log( sprintf( __( 'Batch of %1$d posts, no new orphan media to bind (offset=%2$d)', 'wp-genius' ), count( $ids ), $offset ) );
			return;
		}

		if ( $dry_run ) {
			/* translators: 1: Orphan count, 2: Batch post count, 3: Offset number. */
			WP_CLI::log( sprintf( __( 'DRY-RUN: Batch will bind %1$d orphan media items (from %2$d posts, offset=%3$d)', 'wp-genius' ), count( $pairs ), count( $ids ), $offset ) );
			foreach ( $pairs as $p ) {
				/* translators: 1: Orphan media ID, 2: Post ID. */
				WP_CLI::log( sprintf( __( '  orphan %1$d -> post %2$d', 'wp-genius' ), $p['orphan_id'], $p['post_id'] ) );
			}
			return;
		}

		$this->processing = true;
		try {
			$result = $this->adopt_orphans( $pairs );
		} finally {
			$this->processing = false;
		}

		/* translators: 1: Adopted count, 2: Failed count, 3: Offset number. */
		WP_CLI::log( sprintf( __( 'adopted=%1$d failed=%2$d (batch offset=%3$d)', 'wp-genius' ), $result['adopted'], $result['failed'], $offset ) );
	}

	/**
	 * Adopt orphan attachments: Assign post_parent to first referencing post and rewrite uploads URLs to bucket URLs.
	 *
	 * @param array $pairs List of [orphan_id => int, post_id => int] pairs.
	 * @return array { adopted:int, failed:int, results:array }
	 */
	public function adopt_orphans( $pairs ) {
		$pairs = is_array( $pairs ) ? $pairs : array();

		// Group by orphan_id while preserving insertion order.
		$grouped = array();
		foreach ( $pairs as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}
			$orphan_id = absint( isset( $pair['orphan_id'] ) ? $pair['orphan_id'] : 0 );
			$post_id   = absint( isset( $pair['post_id'] ) ? $pair['post_id'] : 0 );
			if ( ! $orphan_id || ! $post_id ) {
				continue;
			}
			if ( ! isset( $grouped[ $orphan_id ] ) ) {
				$grouped[ $orphan_id ] = array();
			}
			if ( ! in_array( $post_id, $grouped[ $orphan_id ], true ) ) {
				$grouped[ $orphan_id ][] = $post_id;
			}
		}

		if ( empty( $grouped ) ) {
			return array(
				'adopted' => 0,
				'failed'  => 0,
				'results' => array(),
			);
		}

		$adopted = 0;
		$failed  = 0;
		$results = array();

		foreach ( $grouped as $orphan_id => $post_ids ) {
			$result = array(
				'orphan_id' => $orphan_id,
				'post_ids'  => $post_ids,
				'success'   => false,
				'error'     => '',
				'parent'    => 0,
				'rewrite'   => null,
			);

			$att = get_post( $orphan_id );
			if ( ! $att || 'attachment' !== $att->post_type ) {
				$result['error'] = 'not_attachment';
				$results[]       = $result;
				++$failed;
				continue;
			}

			// Ensure attachment is still unattached.
			if ( 0 !== (int) $att->post_parent ) {
				$result['error'] = 'parent_changed';
				$results[]       = $result;
				++$failed;
				continue;
			}

			// 1. Establish parent-child relationship (first post wins).
			$parent_id = $post_ids[0];
			wp_update_post(
				array(
					'ID'          => $orphan_id,
					'post_parent' => $parent_id,
				)
			);
			wp_cache_delete( $orphan_id, 'posts' );
			wp_cache_delete( $orphan_id, 'post_meta' );
			$result['parent'] = $parent_id;

			// 2. Rewrite uploads -> bucket URLs in all referencing posts.
			$result['rewrite'] = $this->rewrite_orphan_urls( $orphan_id, $post_ids );

			// 3. Clear retry markers.
			delete_post_meta( $orphan_id, '_w2p_rewrite_pending' );
			delete_post_meta( $orphan_id, '_w2p_media_failed' );

			$result['success'] = true;
			$results[]         = $result;
			++$adopted;
		}

		return array(
			'adopted' => $adopted,
			'failed'  => $failed,
			'results' => $results,
		);
	}

	/**
	 * Rewrite local uploads URLs of an orphan attachment to bucket URLs across given post IDs.
	 *
	 * @param int   $attachment_id Orphan attachment ID.
	 * @param int[] $post_ids      List of referencing post IDs.
	 * @return array
	 */
	private function rewrite_orphan_urls( $attachment_id, $post_ids ) {
		$attached = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( '' === $attached || false !== strpos( $attached, '://' ) ) {
			return array(
				'success'  => false,
				'reason'   => 'no_rel_path',
				'attempts' => 0,
			);
		}

		$dir  = dirname( $attached );
		$stem = pathinfo( $attached, PATHINFO_FILENAME );
		if ( '.' === $dir || '' === $stem ) {
			return array(
				'success'  => false,
				'reason'   => 'invalid_path',
				'attempts' => 0,
			);
		}

		// Get current bucket URL.
		$new_url = wp_get_attachment_url( $attachment_id );
		if ( ! $new_url || false !== strpos( $new_url, '/wp-content/uploads/' ) ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'not_offloaded',
				'attempts' => 0,
			);
		}

		// Candidate extensions to search and replace.
		$candidates = array();
		$metadata   = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && ! empty( $metadata['original_image'] ) ) {
			$orig_ext = strtolower( pathinfo( $metadata['original_image'], PATHINFO_EXTENSION ) );
			if ( '' !== $orig_ext ) {
				$candidates[] = $orig_ext;
			}
		}
		$current_ext = strtolower( pathinfo( $attached, PATHINFO_EXTENSION ) );
		if ( '' !== $current_ext && ! in_array( $current_ext, $candidates, true ) ) {
			$candidates[] = $current_ext;
		}
		foreach ( self::OLD_EXT_CANDIDATES as $ext ) {
			if ( ! in_array( $ext, $candidates, true ) ) {
				$candidates[] = $ext;
			}
		}

		$home     = home_url();
		$base_old = rtrim( $home, '/' ) . '/wp-content/uploads/' . ltrim( $dir, '/\\' ) . '/';

		$attempts   = 0;
		$total_hits = 0;
		$best       = null;

		foreach ( $candidates as $old_ext ) {
			$old_url = $base_old . $stem . '.' . $old_ext;

			$result = $this->rewrite_content_for_posts( $post_ids, $old_url, $new_url );
			++$attempts;
			$total_hits += $this->count_hits( $result );

			if ( null === $best || $total_hits > $this->count_hits( $best ) ) {
				$best = $result;
			}

			// Stop on first candidate that produces replacements.
			if ( $this->count_hits( $result ) > 0 ) {
				return array(
					'success'  => true,
					'replaced' => true,
					'attempts' => $attempts,
					'old_url'  => $old_url,
					'new_url'  => $new_url,
					'posts'    => isset( $result['posts'] ) ? $result['posts'] : array(),
				);
			}
		}

		return array(
			'success'  => true,
			'replaced' => false,
			'reason'   => 'no_match',
			'attempts' => $attempts,
		);
	}

	/**
	 * Replace old URL with bucket URL across given posts (direct SQL update).
	 *
	 * @param int[]  $post_ids List of post IDs.
	 * @param string $old_url  Old local URL.
	 * @param string $new_url  New bucket URL.
	 * @return array { success:bool, replaced:bool, posts:array }
	 */
	private function rewrite_content_for_posts( $post_ids, $old_url, $new_url ) {
		global $wpdb;

		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) ) );
		if ( empty( $post_ids ) ) {
			return array(
				'success'  => true,
				'replaced' => false,
				'reason'   => 'no_posts',
				'posts'    => array(),
			);
		}

		$parts       = $this->get_pattern_parts( $old_url, $new_url );
		$pattern     = $parts[0];
		$replacement = $parts[1];

		$any_replaced = false;
		$posts_result = array();

		foreach ( $post_ids as $post_id ) {
			$post = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID = %d AND post_type NOT IN ('revision','attachment')",
					$post_id
				)
			);

			if ( ! $post ) {
				$posts_result[ $post_id ] = array(
					'replaced' => false,
					'reason'   => 'not_found',
				);
				continue;
			}

			$count       = 0;
			$new_content = preg_replace( $pattern, $replacement, $post->post_content, -1, $count );

			if ( 0 === $count || $new_content === $post->post_content ) {
				$posts_result[ $post_id ] = array(
					'replaced' => false,
					'reason'   => 'no_match',
				);
				continue;
			}

			$updated = $wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $new_content ),
				array( 'ID' => $post_id )
			);
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'posts' );
			wp_cache_delete( $post_id, 'post_meta' );

			$posts_result[ $post_id ] = array(
				'replaced' => true,
				'count'    => $count,
				'updated'  => ( false !== $updated ),
			);
			$any_replaced             = true;
		}

		return array(
			'success'  => true,
			'replaced' => $any_replaced,
			'posts'    => $posts_result,
		);
	}

	/**
	 * Build regex pattern and replacement string from old and new URLs.
	 *
	 * @param string $old_url Old URL.
	 * @param string $new_url New URL.
	 * @return array [pattern:string, replacement:string]
	 */
	private function get_pattern_parts( $old_url, $new_url ) {
		$old_path = wp_parse_url( $old_url, PHP_URL_PATH );
		$new_path = wp_parse_url( $new_url, PHP_URL_PATH );
		$rel_old  = wp_make_link_relative( $old_path ? $old_path : $old_url );
		$rel_new  = wp_make_link_relative( $new_path ? $new_path : $new_url );
		$old_info = pathinfo( $rel_old );
		$new_info = pathinfo( $rel_new );

		$old_dir  = trailingslashit( $old_info['dirname'] ?? '' );
		$new_dir  = trailingslashit( $new_info['dirname'] ?? '' );
		$filename = $old_info['filename'] ?? '';
		$old_ext  = $old_info['extension'] ?? '';
		$new_ext  = $new_info['extension'] ?? '';

		$pattern = '/'
			. '(https?:\/\/[^\/]+)?'
			. preg_quote( $old_dir, '/' )
			. preg_quote( $filename, '/' )
			. '((?:-\d+x\d+)?(?:-scaled)?)'
			. '\.' . preg_quote( $old_ext, '/' )
			. '/i';

		$replacement = '$1' . $new_dir . $filename . '$2.' . $new_ext;

		return array( $pattern, $replacement );
	}

	/**
	 * Extract referenced media IDs (wp-image-{ID}, wp-video-{ID}, [video id="..."], data-id, data-attachment-id) from post content.
	 *
	 * @param string $content Post content.
	 * @return int[]
	 */
	private function extract_media_ids( $content ) {
		$ids = array();
		if ( preg_match_all( '/wp-image-(\d+)/', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		if ( preg_match_all( '/wp-video-(\d+)/', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		if ( preg_match_all( '/\[video[^\]]*\bid=["\']?(\d+)/i', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		if ( preg_match_all( '/data-id=["\']?(\d+)/i', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		if ( preg_match_all( '/data-attachment-id=["\']?(\d+)/i', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		$ids = array_unique( array_map( 'absint', $ids ) );
		return array_values(
			array_filter(
				$ids,
				function ( $x ) {
					return $x > 0;
				}
			)
		);
	}

	/**
	 * Check whether a URL belongs to local site media (base_url, site_url, home_url, or relative uploads path).
	 * External URLs are skipped with zero DB queries.
	 *
	 * @param string $url Media URL.
	 * @return bool
	 */
	public function is_local_media_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}

		$url = trim( $url );

		// Relative path: must contain /wp-media/ or /wp-content/uploads/
		if ( strpos( $url, '/' ) === 0 && strpos( $url, '//' ) !== 0 ) {
			return ( strpos( $url, '/wp-media/' ) !== false || strpos( $url, '/wp-content/uploads/' ) !== false );
		}

		// Must use http:// or https:// protocol.
		if ( strpos( $url, 'http://' ) !== 0 && strpos( $url, 'https://' ) !== 0 ) {
			return false;
		}

		$settings = \SmartAutoUploadImages\Plugin::get_settings();
		$base_url = ! empty( $settings['base_url'] ) ? rtrim( $settings['base_url'], '/' ) : '';
		$site_url = rtrim( site_url(), '/' );
		$home_url = rtrim( home_url(), '/' );

		// 1. Check Smart AUI base_url prefix match.
		if ( ! empty( $base_url ) && strpos( $url, $base_url ) === 0 ) {
			return true;
		}

		// 2. Check WordPress site_url / home_url prefix match.
		if ( strpos( $url, $site_url ) === 0 || strpos( $url, $home_url ) === 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve attachment ID from a media URL.
	 *
	 * 1. Skip non-local URLs immediately with zero DB queries.
	 * 2. In-memory cache hit.
	 * 3. Exact relative path index match on _wp_attached_file.
	 * 4. Core attachment_url_to_postid().
	 *
	 * @param string $url Media URL.
	 * @return int Attachment ID, or 0 if not found / external.
	 */
	public function find_attachment_id_by_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return 0;
		}

		$url = trim( $url );
		if ( isset( self::$url_to_id_cache[ $url ] ) ) {
			return self::$url_to_id_cache[ $url ];
		}

		// Non-local media returns 0 immediately.
		if ( ! $this->is_local_media_url( $url ) ) {
			self::$url_to_id_cache[ $url ] = 0;
			return 0;
		}

		global $wpdb;
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! empty( $path ) ) {
			// 1. Match relative path against _wp_attached_file.
			if ( preg_match( '#/(?:wp-media|wp-content/uploads)/(.*)$#i', $path, $m ) ) {
				$rel_file = ltrim( $m[1], '/' );
				$found_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
						$rel_file
					)
				);
				if ( $found_id > 0 ) {
					self::$url_to_id_cache[ $url ] = (int) $found_id;
					return (int) $found_id;
				}
			}
		}

		// 2. Core lookup.
		$id = attachment_url_to_postid( $url );
		if ( $id > 0 ) {
			self::$url_to_id_cache[ $url ] = (int) $id;
			return (int) $id;
		}

		self::$url_to_id_cache[ $url ] = 0;
		return 0;
	}

	/**
	 * Parse video content in post body ([video] shortcodes and <video> HTML tags) and inject media IDs.
	 *
	 * @param string $content   Post content.
	 * @param int[]  $video_ids Output parameter: collected video attachment IDs.
	 * @return string Processed post content.
	 */
	public function process_video_content( $content, &$video_ids = array() ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		$updated_content = $content;
		$video_ids       = is_array( $video_ids ) ? $video_ids : array();

		// 1. Process [video ...] shortcodes.
		if ( false !== strpos( $updated_content, '[video' ) ) {
			$updated_content = preg_replace_callback(
				'/(\[video\b)([^\]]*)(\](?:.*?\[\/video\])?)/is',
				function ( $matches ) use ( &$video_ids ) {
					$prefix    = $matches[1];
					$attrs_str = $matches[2];
					$suffix    = $matches[3];

					// Extract video URL (mp4, src, webm, m4v, ogv, mov).
					if ( preg_match( '/(?:mp4|src|webm|m4v|ogv|mov)=["\']([^"\']+)["\']/i', $attrs_str, $url_m ) ) {
						$video_url = $url_m[1];
						$att_id    = $this->find_attachment_id_by_url( $video_url );
						if ( $att_id > 0 ) {
							$video_ids[] = $att_id;

							// Inject or update id attribute.
							if ( preg_match( '/\bid=["\']?\d+["\']?/i', $attrs_str ) ) {
								$attrs_str = preg_replace( '/\bid=["\']?\d+["\']?/i', 'id="' . $att_id . '"', $attrs_str );
							} else {
								$attrs_str = ' id="' . $att_id . '"' . $attrs_str;
							}
						}
					}
					return $prefix . $attrs_str . $suffix;
				},
				$updated_content
			);
		}

		// 2. Process <video> HTML tags.
		if ( false !== strpos( $updated_content, '<video' ) ) {
			$processor = new \WP_HTML_Tag_Processor( $updated_content );
			while ( $processor->next_tag( 'video' ) ) {
				$src = $processor->get_attribute( 'src' );
				if ( ! empty( $src ) ) {
					$att_id = $this->find_attachment_id_by_url( $src );
					if ( $att_id > 0 ) {
						$video_ids[] = $att_id;
						$processor->set_attribute( 'data-id', (string) $att_id );

						$existing_class = $processor->get_attribute( 'class' ) ?? '';
						$new_class      = 'wp-video-' . $att_id;
						if ( ! empty( $existing_class ) ) {
							if ( strpos( $existing_class, 'wp-video-' ) === false ) {
								$new_class = trim( $existing_class ) . ' ' . $new_class;
							} else {
								$new_class = preg_replace( '/wp-video-\d+/', 'wp-video-' . $att_id, $existing_class );
							}
						}
						$processor->set_attribute( 'class', $new_class );
					}
				}
			}
			$updated_content = $processor->get_updated_html();
		}

		$video_ids = array_values( array_unique( array_filter( $video_ids ) ) );
		return $updated_content;
	}

	/**
	 * Count number of posts that had at least one URL replaced.
	 *
	 * @param array $result Return value of rewrite_content_for_posts().
	 * @return int
	 */
	private function count_hits( $result ) {
		$count = 0;
		if ( isset( $result['posts'] ) && is_array( $result['posts'] ) ) {
			foreach ( $result['posts'] as $post_result ) {
				if ( ! empty( $post_result['replaced'] ) ) {
					++$count;
				}
			}
		}
		return $count;
	}
}
