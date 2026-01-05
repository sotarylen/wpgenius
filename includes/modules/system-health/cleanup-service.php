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

class SystemHealthCleanupService {

    /**
     * Get Database Statistics
     */
    public function get_stats() {
        global $wpdb;

        $stats = [
            'revisions'     => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'" ),
            'auto_drafts'   => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'auto-draft'" ),
            'orphaned_meta' => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)" ),
            'transients'    => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" ),
        ];

        return $stats;
    }

    /**
     * Clean Revisions
     */
    public function clean_revisions() {
        global $wpdb;
        $count = $wpdb->query( "DELETE FROM $wpdb->posts WHERE post_type = 'revision'" );
        return (int) $count;
    }

    /**
     * Clean Auto-Drafts
     */
    public function clean_auto_drafts() {
        global $wpdb;
        $count = $wpdb->query( "DELETE FROM $wpdb->posts WHERE post_status = 'auto-draft'" );
        return (int) $count;
    }

    /**
     * Clean Orphaned Meta
     */
    public function clean_orphaned_meta() {
        global $wpdb;
        $count = $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)" );
        return (int) $count;
    }

    /**
     * Clean Transients
     */
    public function clean_transients() {
        global $wpdb;
        $count = $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
        return (int) $count;
    }

    /**
     * Get System Information
     */
    public function get_system_info() {
        global $wpdb;

        return [
            'server' => [
                'php_version'        => PHP_VERSION,
                'mysql_version'      => $wpdb->db_version(),
                'server_software'    => $_SERVER['SERVER_SOFTWARE'],
                'memory_limit'       => ini_get( 'memory_limit' ),
                'post_max_size'      => ini_get( 'post_max_size' ),
                'upload_max_filesize'=> ini_get( 'upload_max_filesize' ),
                'max_execution_time' => ini_get( 'max_execution_time' ),
                'gd_version'         => function_exists( 'gd_info' ) ? gd_info()['GD Version'] : 'Not Installed',
                'curl_version'       => function_exists( 'curl_version' ) ? curl_version()['version'] : 'Not Installed',
            ],
            'wordpress' => [
                'version'            => get_bloginfo( 'version' ),
                'site_url'           => get_site_url(),
                'home_url'           => get_home_url(),
                'multisite'          => is_multisite() ? 'Yes' : 'No',
                'debug_mode'         => WP_DEBUG ? 'On' : 'Off',
                'memory_limit'       => WP_MEMORY_LIMIT,
                'table_prefix'       => $wpdb->prefix,
                'language'           => get_locale(),
                'timezone'           => date_default_timezone_get(),
            ]
        ];
    }

    /**
     * Get all categories
     */
    public function get_categories() {
        $terms = get_terms( [
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ] );
        
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        // Force convert to objects if they are arrays for some reason
        return array_map( function( $term ) {
            return (object) $term;
        }, $terms );
    }

    /**
     * Scan for posts with images wrapped in links
     */
    public function scan_posts_with_linked_images( $category_id = 0 ) {
        $args = [
            'post_type'      => 'post',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'suppress_filters' => true,
        ];

        if ( $category_id > 0 ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => $category_id,
                ],
            ];
        }

        $query = new WP_Query( $args );
        $post_ids = $query->posts;

        $results = [];

        if ( ! empty( $post_ids ) ) {
            foreach ( $post_ids as $post_id ) {
                $content = get_post_field( 'post_content', $post_id );
                
                // Regular expression to find a links surrounding img tags, allowing whitespace
                // <a ...>\s*<img ...>\s*</a>
                if ( preg_match( '/<a [^>]*>\s*<img [^>]*>\s*<\/a>/is', $content ) ) {
                    $results[] = [
                        'id'       => $post_id,
                        'title'    => get_the_title( $post_id ),
                        'edit_url' => get_edit_post_link( $post_id, '' ),
                    ];
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
        $pattern = '/<a [^>]*>\s*(<img [^>]*>)\s*<\/a>/is';
        $new_content = preg_replace( $pattern, '$1', $content );

        if ( $new_content !== $content ) {
            $wpdb_update = wp_update_post( [
                'ID'           => $post_id,
                'post_content' => $new_content,
            ] );
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
            // 1. First, find titles that have duplicates using a lightweight SQL query
            // We only care about post titles that appear more than once.
            
            $post_types = "'post'";
            $post_statuses = "'publish', 'draft', 'pending', 'private', 'future'";
            
            $sql_find_duplicates = "
                SELECT post_title, COUNT(*) as count
                FROM $wpdb->posts
                WHERE post_type = $post_types
                AND post_status IN ($post_statuses)
                AND post_title != ''
            ";

            if ( $category_id > 0 ) {
                // If category filtering is needed, we need a JOIN
                $sql_find_duplicates .= "
                    AND ID IN (
                        SELECT object_id 
                        FROM $wpdb->term_relationships tr
                        LEFT JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE tt.term_id = %d
                    )
                ";
                $sql_find_duplicates = $wpdb->prepare( $sql_find_duplicates, $category_id );
            }

            $sql_find_duplicates .= "
                GROUP BY post_title
                HAVING count > 1
            ";

            // Optimize: limit results if too many? No, user wants to find all.
            // But to avoid OOM on millions of rows, maybe we process in chunks?
            // For now, getting just titles having duplicates is relatively light.
            
            $duplicate_titles_rows = $wpdb->get_results( $sql_find_duplicates );

            if ( empty( $duplicate_titles_rows ) ) {
                return [];
            }

            $duplicate_titles = wp_list_pluck( $duplicate_titles_rows, 'post_title' );
            
            // 2. Now fetch the actual post data only for these titles
            // To prevent huge queries, we might need to chunk this if there are thousands of duplicate titles.
            // But let's assume a reasonable limit or fetch all since we are only fetching minimal fields.
            
            // Escape titles for IN clause
            $escaped_titles = [];
            foreach ( $duplicate_titles as $t ) {
                $escaped_titles[] = $wpdb->prepare( '%s', $t );
            }
            
            if ( empty( $escaped_titles ) ) {
                return [];
            }

            $duplicates = [];
            
            // Chunking titles to avoid "Query too large" error
            $chunk_size = 100;
            $chunks = array_chunk( $escaped_titles, $chunk_size );

            foreach ( $chunks as $title_chunk ) {
                $in_clause = implode( ',', $title_chunk );
                
                $sql_get_posts = "
                    SELECT ID, post_title, post_name, post_date
                    FROM $wpdb->posts
                    WHERE post_title IN ($in_clause)
                    AND post_type = 'post'
                    AND post_status IN ($post_statuses)
                    ORDER BY post_title ASC, post_date ASC
                ";
                
                $posts = $wpdb->get_results( $sql_get_posts );

                if ( ! empty( $posts ) ) {
                    // Group by title
                    $grouped_posts = [];
                    foreach ( $posts as $p ) {
                        $grouped_posts[ $p->post_title ][] = $p;
                    }

                    foreach ( $grouped_posts as $title => $group ) {
                        if ( count( $group ) > 1 ) {
                            $duplicate_items = [];
                            foreach ( $group as $index => $p ) {
                                $edit_url = get_edit_post_link( $p->ID, 'raw' );
                                $duplicate_items[] = [
                                    'id'               => $p->ID,
                                    'title'            => $p->post_title,
                                    'slug'             => $p->post_name,
                                    'date'             => $p->post_date,
                                    'edit_url'         => $edit_url ? $edit_url : '', // Can be slow if called many times, but ID-based link generation is fast
                                    'recommended_keep' => ( $index === 0 ), // Keep the oldest
                                    'selected'         => ( $index !== 0 ), // Select others
                                ];
                            }

                            $duplicates[] = [
                                'group_title' => $title,
                                'posts'       => $duplicate_items,
                            ];
                        }
                    }
                }
            }

            return $duplicates;

        } catch ( Exception $e ) {
            error_log( 'Duplicate scan error: ' . $e->getMessage() );
            return [];
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
            error_log( 'W2P DuplicateCleaner: Invalid post_ids provided: ' . print_r( $post_ids, true ) );
            return 0;
        }

        $count = 0;
        $total = count( $post_ids );
        error_log( 'W2P DuplicateCleaner: trash_duplicate_posts called with ' . $total . ' post IDs: ' . implode( ',', array_slice( $post_ids, 0, 10 ) ) . ( $total > 10 ? '...' : '' ) );
        
        foreach ( $post_ids as $index => $post_id ) {
            if ( ! is_numeric( $post_id ) || $post_id <= 0 ) {
                error_log( 'W2P DuplicateCleaner: Invalid post ID at index ' . $index . ': ' . $post_id );
                continue;
            }
            
            $post = get_post( $post_id );
            if ( ! $post ) {
                error_log( 'W2P DuplicateCleaner: Post ID ' . $post_id . ' not found or already deleted' );
                continue;
            }
            
            if ( $post->post_status === 'trash' ) {
                error_log( 'W2P DuplicateCleaner: Post ID ' . $post_id . ' is already in trash' );
                $count++;
                continue;
            }
            
            $result = wp_trash_post( $post_id );
            if ( $result !== false ) {
                $count++;
                error_log( 'W2P DuplicateCleaner: Successfully trashed post ID ' . $post_id );
            } else {
                error_log( 'W2P DuplicateCleaner: Failed to trash post ID ' . $post_id . '. Error: ' . print_r( $result, true ) );
            }
            
            // Add small delay every 10 posts to prevent overwhelming the database
            if ( ( $index + 1 ) % 10 === 0 ) {
                usleep( 100000 ); // 0.1 second delay
            }
        }

        error_log( 'W2P DuplicateCleaner: Processed ' . $total . ' posts, successfully trashed ' . $count . ' posts' );
        return $count;
    }
}
