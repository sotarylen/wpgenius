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
        return get_categories( [
            'hide_empty' => false,
        ] );
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
        try {
            $args = [
                'post_type'      => 'post',
                'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'ASC',
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
            $posts = $query->posts;

            if ( empty( $posts ) ) {
                return [];
            }

            $duplicates = [];
            $title_slug_map = [];

            // Group posts by title
            foreach ( $posts as $post ) {
                if ( ! isset( $post->post_title ) || ! isset( $post->post_name ) ) {
                    continue;
                }
                
                $title = trim( $post->post_title );
                $slug = $post->post_name;
                
                // Skip empty titles
                if ( empty( $title ) ) {
                    continue;
                }
                
                // Create a key for grouping: use title as primary key
                if ( ! isset( $title_slug_map[ $title ] ) ) {
                    $title_slug_map[ $title ] = [];
                }
                
                $edit_url = get_edit_post_link( $post->ID, 'raw' );
                
                $title_slug_map[ $title ][] = [
                    'id'         => $post->ID,
                    'title'      => $post->post_title,
                    'slug'       => $slug,
                    'date'       => $post->post_date,
                    'edit_url'   => $edit_url ? $edit_url : '',
                ];
            }

            // Filter out groups with only one post and check for slug matches
            foreach ( $title_slug_map as $title => $posts_group ) {
                if ( count( $posts_group ) > 1 ) {
                    // Check if slugs match or have suffixes (e.g., post-name, post-name-2, post-name-3)
                    $base_slug = $this->get_base_slug( $posts_group[0]['slug'] );
                    $is_duplicate_group = true;
                    
                    foreach ( $posts_group as $post_item ) {
                        $current_base_slug = $this->get_base_slug( $post_item['slug'] );
                        if ( $current_base_slug !== $base_slug ) {
                            $is_duplicate_group = false;
                            break;
                        }
                    }
                    
                    if ( $is_duplicate_group ) {
                        // Recommend keeping the oldest (first in ASC order), but user can change
                        $duplicate_items = [];
                        
                        foreach ( $posts_group as $index => $post_item ) {
                            if ( $index === 0 ) {
                                // First (oldest) post: recommended to keep, not selected for deletion
                                $post_item['recommended_keep'] = true;
                                $post_item['selected'] = false;
                            } else {
                                // Other posts: recommended to delete, selected by default
                                $post_item['recommended_keep'] = false;
                                $post_item['selected'] = true;
                            }
                            $duplicate_items[] = $post_item;
                        }
                        
                        $duplicates[] = [
                            'group_title' => $title,
                            'posts'       => $duplicate_items,
                        ];
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
            return 0;
        }

        $count = 0;
        foreach ( $post_ids as $post_id ) {
            $result = wp_trash_post( $post_id );
            if ( $result !== false ) {
                $count++;
            }
        }

        return $count;
    }
}
