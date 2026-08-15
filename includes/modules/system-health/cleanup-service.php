<?php
/**
 * System Health Cleanup Service
 *
 * Handles the database queries for optimization.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable Universal.Files.SeparateFunctionsFromOO -- Legacy utility file contains both helper functions and an OO class.
class SystemHealthCleanupService {

	/**
	 * Get Database Statistics
	 */
	public function get_stats() {
		global $wpdb;

		$stats = array(
			'revisions'     => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'" ),
			'auto_drafts'   => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'auto-draft'" ),
			'orphaned_meta' => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta pm LEFT JOIN $wpdb->posts p ON pm.post_id = p.ID WHERE p.ID IS NULL" ),
			'transients'    => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" ),
		);

		return $stats;
	}

	/**
	 * Clean Revisions (Safe Mode)
	 */
	public function clean_revisions() {
		global $wpdb;
		// Get IDs first to use WP API
		$ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'revision'" );

		if ( empty( $ids ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $ids as $id ) {
			// Force delete, skip trash
			if ( wp_delete_post_revision( $id ) ) {
				++$count;
			}
			// Small pause every 50 items to prevent server overload
			if ( $count % 50 === 0 ) {
				usleep( 50000 );
			}
		}
		return $count;
	}

	/**
	 * Clean Auto-Drafts (Safe Mode)
	 */
	public function clean_auto_drafts() {
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'auto-draft'" );

		if ( empty( $ids ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				++$count;
			}
			if ( $count % 50 === 0 ) {
				usleep( 50000 );
			}
		}
		return $count;
	}

	/**
	 * Clean Orphaned Meta
	 */
	public function clean_orphaned_meta() {
		global $wpdb;
		// SQL is most efficient here as iterating all meta is not feasible
		$count = $wpdb->query( "DELETE pm FROM $wpdb->postmeta pm LEFT JOIN $wpdb->posts p ON pm.post_id = p.ID WHERE p.ID IS NULL" );
		return (int) $count;
	}

	/**
	 * Clean Transients
	 */
	public function clean_transients() {
		global $wpdb;
		// Standard SQL way to bulk clear transients
		$count = $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
		return (int) $count;
	}

	/**
	 * Clean Custom Field by Key
	 *
	 * @param string $meta_key The meta key to delete.
	 * @return int Number of rows deleted.
	 */
	public function clean_custom_field( $meta_key ) {
		global $wpdb;

		if ( empty( $meta_key ) ) {
			return 0;
		}

		// Delete all meta entries with the specified key
		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->postmeta WHERE meta_key = %s",
				$meta_key
			)
		);

		return (int) $count;
	}

	/**
	 * Get System Information
	 */
	public function get_system_info() {
		global $wpdb;

		return array(
			'server'    => array(
				'php_version'         => PHP_VERSION,
				'mysql_version'       => $wpdb->db_version(),
				'server_software'     => $_SERVER['SERVER_SOFTWARE'],
				'memory_limit'        => ini_get( 'memory_limit' ),
				'post_max_size'       => ini_get( 'post_max_size' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
				'max_execution_time'  => ini_get( 'max_execution_time' ),
				'gd_version'          => function_exists( 'gd_info' ) ? gd_info()['GD Version'] : 'Not Installed',
				'curl_version'        => function_exists( 'curl_version' ) ? curl_version()['version'] : 'Not Installed',
			),
			'wordpress' => array(
				'version'      => get_bloginfo( 'version' ),
				'site_url'     => get_site_url(),
				'home_url'     => get_home_url(),
				'multisite'    => is_multisite() ? 'Yes' : 'No',
				'debug_mode'   => WP_DEBUG ? 'On' : 'Off',
				'memory_limit' => WP_MEMORY_LIMIT,
				'table_prefix' => $wpdb->prefix,
				'language'     => get_locale(),
				'timezone'     => date_default_timezone_get(),
			),
		);
	}

	/**
	 * Get all categories
	 */
	public function get_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		// Force convert to objects if they are arrays for some reason
		return array_map(
			function ( $term ) {
				return (object) $term;
			},
			$terms
		);
	}

	/**
	 * Scan for posts with images wrapped in links
	 */
	public function scan_posts_with_linked_images( $category_id = 0 ) {
		global $wpdb;

		// Get all public post types to avoid missing CPTs
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		// Ensure default 'post' is included (though logic above should catch it)
		if ( ! in_array( 'post', $post_types, true ) ) {
			$post_types[] = 'post';
		}

		// Sanitize for SQL IN clause
		$post_types_sql = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";
		$post_statuses  = "'publish', 'draft', 'pending', 'private', 'future'";

		$where_category = '';
		if ( $category_id > 0 ) {
			$term_taxonomy_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy WHERE term_id = %d", $category_id ) );
			if ( $term_taxonomy_id ) {
				$where_category = $wpdb->prepare( " AND ID IN (SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d)", $term_taxonomy_id );
			}
		}

		// Use direct SQL for performance
		// First filter with strict LIKE to find candidates (much faster than PHP loop)
		// LIMIT 500 to prevent browser crash rendering too many rows
		$like_pattern = '%<a%<img%</a>%';
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- All dynamic fragments come from safe sources: $post_types_sql goes through esc_sql, $post_statuses is hardcoded, and $where_category is the result of $wpdb->prepare.
		$sql = "
            SELECT ID, post_title, post_content 
            FROM $wpdb->posts 
            WHERE post_type IN ($post_types_sql) 
            AND post_status IN ($post_statuses) 
            AND post_content LIKE %s 
            $where_category
            ORDER BY ID DESC
            LIMIT 10000
        ";

		// Query expecting one string arg for LIKE pattern
		$posts = $wpdb->get_results( $wpdb->prepare( $sql, $like_pattern ) );
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$results = array();

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				// Double check with Regex to ensure it's truly an image inside a link
				// Uses \s+ to match any whitespace including newlines
				if ( preg_match( '/<a\s+[^>]*>\s*<img\s+[^>]*>\s*<\/a>/is', $post->post_content ) ) {
					$edit_url  = get_edit_post_link( $post->ID, 'raw' );
					$results[] = array(
						'id'       => $post->ID,
						'title'    => $post->post_title,
						'edit_url' => $edit_url ? $edit_url : '',
					);
				}
			}
		}

		return $results;
	}

	/**
	 * Remove links from images in a post
	 */
	public function remove_image_links_from_post( $post_id ) {
		$content = get_post_field( 'post_content', $post_id );

		// Replace <a ...>\s*<img ...>\s*</a> with just the img tag content
		// Uses \s+ for robust whitespace matching
		$pattern     = '/<a\s+[^>]*>\s*(<img\s+[^>]*>)\s*<\/a>/is';
		$new_content = preg_replace( $pattern, '$1', $content );

		if ( $new_content !== $content ) {
			$wpdb_update = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $new_content,
				)
			);
			return ! is_wp_error( $wpdb_update ) ? 1 : 0;
		}

		return 0;
	}

	/**
	 * Scan for duplicate posts by title and slug
	 */
	public function scan_duplicate_posts( $category_id = 0 ) {
		global $wpdb;

		try {
			// Get all public post types
			$post_types = get_post_types( array( 'public' => true ), 'names' );
			if ( ! in_array( 'post', $post_types, true ) ) {
				$post_types[] = 'post';
			}
			$post_types_sql = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

			$post_statuses = "'publish', 'draft', 'pending', 'private', 'future'";

            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Dynamic fragments come from esc_sql / hardcoded statuses / prepare results, same rationale as the block above at line 216.
			if ( $category_id > 0 ) {
				$sql_find_duplicates = "
                    SELECT p.post_title, COUNT(*) as count
                    FROM $wpdb->posts p
                    INNER JOIN $wpdb->term_relationships tr ON (p.ID = tr.object_id)
                    INNER JOIN $wpdb->term_taxonomy tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                    WHERE p.post_type IN ($post_types_sql)
                    AND p.post_status IN ($post_statuses)
                    AND p.post_title != ''
                    AND tt.term_id = %d
                    GROUP BY p.post_title
                    HAVING count > 1
                ";
				$sql_find_duplicates = $wpdb->prepare( $sql_find_duplicates, $category_id );
			} else {
				$sql_find_duplicates = "
                    SELECT post_title, COUNT(*) as count
                    FROM $wpdb->posts
                    WHERE post_type IN ($post_types_sql)
                    AND post_status IN ($post_statuses)
                    AND post_title != ''
                    GROUP BY post_title
                    HAVING count > 1
                ";
			}

			$duplicate_titles_rows = $wpdb->get_results( $sql_find_duplicates );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

			if ( empty( $duplicate_titles_rows ) ) {
				return array();
			}

			$duplicate_titles = wp_list_pluck( $duplicate_titles_rows, 'post_title' );

			// Escape titles for IN clause
			$escaped_titles = array();
			foreach ( $duplicate_titles as $t ) {
				$escaped_titles[] = $wpdb->prepare( '%s', $t );
			}

			if ( empty( $escaped_titles ) ) {
				return array();
			}

			$duplicates = array();

			// Chunking titles to avoid "Query too large" error
			$chunk_size = 100;
			$chunks     = array_chunk( $escaped_titles, $chunk_size );

			foreach ( $chunks as $title_chunk ) {
				$in_clause = implode( ',', $title_chunk );

                // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $in_clause is built from titles escaped via $wpdb->prepare('%s'), and $post_statuses is hardcoded.
				$sql_get_posts = "
                    SELECT ID, post_title, post_name, post_date
                    FROM $wpdb->posts
                    WHERE post_title IN ($in_clause)
                    AND post_type = 'post'
                    AND post_status IN ($post_statuses)
                    ORDER BY post_title ASC, post_date ASC
                ";

				$posts = $wpdb->get_results( $sql_get_posts );
                // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

				if ( ! empty( $posts ) ) {
					// Group by title
					$grouped_posts = array();
					foreach ( $posts as $p ) {
						$grouped_posts[ $p->post_title ][] = $p;
					}

					foreach ( $grouped_posts as $title => $group ) {
						if ( count( $group ) > 1 ) {
							$duplicate_items = array();
							foreach ( $group as $index => $p ) {
								$edit_url          = get_edit_post_link( $p->ID, 'raw' );
								$duplicate_items[] = array(
									'id'               => $p->ID,
									'title'            => $p->post_title,
									'slug'             => $p->post_name,
									'date'             => $p->post_date,
									'edit_url'         => $edit_url ? $edit_url : '',
									'recommended_keep' => ( $index === 0 ),
									'selected'         => ( $index !== 0 ),
								);
							}

							$duplicates[] = array(
								'group_title' => $title,
								'posts'       => $duplicate_items,
							);
						}
					}
				}
			}

			return $duplicates;

		} catch ( Exception $e ) {
			W2P_Logger::error( 'Duplicate scan error: ' . $e->getMessage(), 'system-health' );
			return array();
		}
	}

	/**
	 * Extract base slug without numeric suffix
	 */
	private function get_base_slug( $slug ) {
		// Remove trailing -2, -3, etc.
		return preg_replace( '/-\d+$/', '', $slug );
	}

	/**
	 * Move duplicate posts to trash
	 */
	public function trash_duplicate_posts( $post_ids ) {
		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			W2P_Logger::error( 'Invalid post_ids provided: ' . print_r( $post_ids, true ), 'system-health' );
			return 0;
		}

		$count = 0;
		$total = count( $post_ids );
		W2P_Logger::info( 'trash_duplicate_posts called with ' . $total . ' post IDs: ' . implode( ',', array_slice( $post_ids, 0, 10 ) ) . ( $total > 10 ? '...' : '' ), 'system-health' );

		foreach ( $post_ids as $index => $post_id ) {
			if ( ! is_numeric( $post_id ) || $post_id <= 0 ) {
				W2P_Logger::error( 'Invalid post ID at index ' . $index . ': ' . $post_id, 'system-health' );
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				W2P_Logger::warning( 'Post ID ' . $post_id . ' not found or already deleted', 'system-health' );
				continue;
			}

			if ( $post->post_status === 'trash' ) {
				W2P_Logger::info( 'Post ID ' . $post_id . ' is already in trash', 'system-health' );
				++$count;
				continue;
			}

			$result = wp_trash_post( $post_id );
			if ( $result !== false ) {
				++$count;
				W2P_Logger::info( 'Successfully trashed post ID ' . $post_id, 'system-health' );
			} else {
				W2P_Logger::error( 'Failed to trash post ID ' . $post_id . '. Error: ' . print_r( $result, true ), 'system-health' );
			}

			// Add small delay every 10 posts to prevent overwhelming the database
			if ( ( $index + 1 ) % 10 === 0 ) {
				usleep( 100000 ); // 0.1 second delay
			}
		}

		W2P_Logger::info( 'Processed ' . $total . ' posts, successfully trashed ' . $count . ' posts', 'system-health' );
		return $count;
	}
}
