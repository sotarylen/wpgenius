<?php
/**
 * Media Turbo Converter Service
 *
 * Handles the actual image conversion logic.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MediaTurboConverterService {

    /**
     * Convert Image to WebP
     */
    public function convert_to_webp( $file_path, $quality = 80 ) {
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return false;
        }

        $info = @getimagesize( $file_path );
        if ( ! $info ) {
            return false;
        }

        $mime = $info['mime'];
        $image = false;

        switch ( $mime ) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg( $file_path );
                break;
            case 'image/png':
                $image = @imagecreatefrompng( $file_path );
                if ( $image ) {
                    imagepalettetotruecolor( $image );
                    imagealphablending( $image, true );
                    imagesavealpha( $image, true );
                }
                break;
        }

        if ( ! $image ) {
            return false;
        }

        $webp_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );
        
        // Ensure unique filename if optimized version already exists
        if ( file_exists( $webp_path ) && basename( $webp_path ) !== basename( $file_path ) ) {
            // If it's the exact same name but different ext, we overwrite it.
            // If we already have a -optimized.webp, we might need to be careful.
        }

        $success = imagewebp( $image, $webp_path, $quality );
        imagedestroy( $image );

        return $success ? $webp_path : false;
    }

    /**
     * Convert full attachment including all sizes
     */
    public function convert_attachment( $attachment_id, $quality = 80 ) {
        $start_time = microtime( true );
        W2P_Logger::info( ">>> Starting conversion for attachment ID: $attachment_id", 'media-turbo' );
        
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path ) {
            W2P_Logger::error( "Cannot get file path for attachment ID: $attachment_id", 'media-turbo' );
            return false;
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        $base_dir = dirname( $file_path );
        
        // Get settings to check if we should keep original files
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        $keep_original = ! empty( $settings['keep_original'] );
        
        // 1. Convert Original
        $convert_start = microtime( true );
        $new_original = $this->convert_to_webp( $file_path, $quality );
        if ( ! $new_original ) {
            W2P_Logger::error( "Failed to convert original image: $file_path", 'media-turbo' );
            return false;
        }
        W2P_Logger::info( sprintf( "Original converted in %.2fs: %s", microtime( true ) - $convert_start, basename( $new_original ) ), 'media-turbo' );

        $old_url = wp_get_attachment_url( $attachment_id );
        $new_url = str_replace( basename( $file_path ), basename( $new_original ), $old_url );

        // Track files to delete
        $files_to_delete = [];
        if ( ! $keep_original ) {
            $files_to_delete[] = $file_path;
        }

        // 2. Convert Thumbnails
        $thumb_count = 0;
        if ( ! empty( $metadata['sizes'] ) ) {
            $thumb_start = microtime( true );
            foreach ( $metadata['sizes'] as $size => $info ) {
                $thumb_path = $base_dir . '/' . $info['file'];
                $new_thumb = $this->convert_to_webp( $thumb_path, $quality );
                if ( $new_thumb ) {
                    $metadata['sizes'][$size]['file'] = basename( $new_thumb );
                    $metadata['sizes'][$size]['mime-type'] = 'image/webp';
                    $thumb_count++;
                    
                    // Mark original thumbnail for deletion
                    if ( ! $keep_original && file_exists( $thumb_path ) ) {
                        $files_to_delete[] = $thumb_path;
                    }
                }
            }
            W2P_Logger::info( sprintf( "%d thumbnails converted in %.2fs", $thumb_count, microtime( true ) - $thumb_start ), 'media-turbo' );
        }

        // 3. Update Metadata and Post
        $db_start = microtime( true );
        $metadata['file'] = str_replace( basename( $file_path ), basename( $new_original ), $metadata['file'] );
        wp_update_attachment_metadata( $attachment_id, $metadata );
        update_attached_file( $attachment_id, $new_original );

        global $wpdb;
        $wpdb->update( 
            $wpdb->posts, 
            [ 'post_mime_type' => 'image/webp', 'guid' => $new_url ], 
            [ 'ID' => $attachment_id ] 
        );
        W2P_Logger::info( sprintf( "Database updated in %.2fs", microtime( true ) - $db_start ), 'media-turbo' );

        // 4. Replace in Content
        $replace_start = microtime( true );
        W2P_Logger::info( "Starting URL replacement. Old URL: $old_url, New URL: $new_url, Attachment ID: $attachment_id", 'media-turbo' );
        $affected = $this->replace_url_in_content( $old_url, $new_url, $attachment_id );
        W2P_Logger::info( sprintf( "URL replacement completed in %.2fs. Posts affected: %d", microtime( true ) - $replace_start, $affected ), 'media-turbo' );

        // 5. Delete Original Files (if keep_original is disabled)
        $deleted_count = 0;
        if ( ! $keep_original && ! empty( $files_to_delete ) ) {
            foreach ( $files_to_delete as $file_to_delete ) {
                if ( file_exists( $file_to_delete ) && @unlink( $file_to_delete ) ) {
                    $deleted_count++;
                } else {
                    W2P_Logger::warning( "Failed to delete: $file_to_delete", 'media-turbo' );
                }
            }
            W2P_Logger::info( "Deleted $deleted_count original files", 'media-turbo' );
        }

        $total_time = microtime( true ) - $start_time;
        W2P_Logger::info( sprintf( "<<< Conversion complete for ID %d in %.2fs (converted: 1 original + %d thumbs, replaced: %d posts, deleted: %d files)", 
            $attachment_id, $total_time, $thumb_count, $affected, $deleted_count ), 'media-turbo' );

        return [
            'success' => true,
            'new_url' => $new_url,
            'affected' => $affected,
            'deleted' => $deleted_count
        ];
    }

    /**
     * Get Total Candidate Count
     */
    public function get_total_candidate_count() {
        global $wpdb;
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        $scan_mode = $settings['scan_mode'] ?? 'media';
        $min_file_size = isset( $settings['min_file_size'] ) ? absint( $settings['min_file_size'] ) * 1024 : 1024 * 1024;
        
        if ( $scan_mode === 'posts' ) {
            // Count images in recent posts
            $posts_limit = isset( $settings['posts_limit'] ) ? absint( $settings['posts_limit'] ) : 10;
            $post_ids = $this->get_recent_unprocessed_post_ids( $posts_limit );
            
            if ( empty( $post_ids ) ) {
                return 0;
            }
            
            $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
            $query = "SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts} 
                      WHERE post_type = 'attachment' 
                      AND post_mime_type IN ('image/jpeg', 'image/png')
                      AND post_parent IN ($placeholders)";
            
            return (int) $wpdb->get_var( $wpdb->prepare( $query, $post_ids ) );
        } else {
            // Count all images that meet file size requirement
            // Use batch processing to avoid loading all IDs into memory at once
            $batch_size = 1000; // Process in batches of 1000
            $current_offset = 0;
            $count = 0;
            
            while ( true ) {
                $query = "SELECT ID FROM {$wpdb->posts} 
                          WHERE post_type = 'attachment' 
                          AND post_mime_type IN ('image/jpeg', 'image/png') 
                          ORDER BY ID DESC
                          LIMIT $batch_size OFFSET $current_offset";
                
                $batch_ids = $wpdb->get_col( $query );
                
                if ( empty( $batch_ids ) ) {
                    break; // No more images in database
                }
                
                // Filter by file size in this batch
                foreach ( $batch_ids as $id ) {
                    $file = get_attached_file( $id );
                    if ( ! $file || ! file_exists( $file ) ) {
                        continue;
                    }
                    
                    if ( filesize( $file ) >= $min_file_size ) {
                        $count++;
                    }
                }
                
                $current_offset += $batch_size;
            }
            
            return $count;
        }
    }

    /**
     * Get Conversion Candidates
     */
    public function get_conversion_candidates( $limit = 100, $offset = 0, $ids_only = false ) {
        global $wpdb;
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        $scan_mode = $settings['scan_mode'] ?? 'media';
        $min_file_size = isset( $settings['min_file_size'] ) ? absint( $settings['min_file_size'] ) * 1024 : 1024 * 1024; // Convert KB to bytes
            
        if ( $scan_mode === 'posts' ) {
            // Scan by posts - get images from recent unprocessed posts
            $posts_limit = isset( $settings['posts_limit'] ) ? absint( $settings['posts_limit'] ) : 10;
            $post_ids = $this->get_recent_unprocessed_post_ids( $posts_limit );
                
            if ( empty( $post_ids ) ) {
                return $ids_only ? [] : [];
            }
                
            $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
            $query = "SELECT DISTINCT ID FROM {$wpdb->posts} 
                      WHERE post_type = 'attachment' 
                      AND post_mime_type IN ('image/jpeg', 'image/png')
                      AND post_parent IN ($placeholders)
                      ORDER BY ID DESC";
                
            // 在按文章扫描模式下，不应用scan_limit限制，返回这些文章中的所有图片
            // 只有在ids_only=false时才应用limit用于预览显示
            if ( ! $ids_only && $limit > 0 ) {
                $query .= " LIMIT " . absint( $limit );
            }
                
            $ids = $wpdb->get_col( $wpdb->prepare( $query, $post_ids ) );
        } else {
            // Original media library scan - get ALL candidates first, then filter by size
            // We need to fetch more than $limit to ensure we get enough valid candidates after filtering
            $batch_size = $limit * 5; // Fetch 5x to account for filtering
            $current_offset = $offset;
            $ids = [];
                
            while ( count( $ids ) < $limit && $current_offset < 10000 ) { // Safety limit
                $query = "SELECT ID FROM {$wpdb->posts} 
                          WHERE post_type = 'attachment' 
                          AND post_mime_type IN ('image/jpeg', 'image/png') 
                          ORDER BY ID DESC
                          LIMIT $batch_size OFFSET $current_offset";
                    
                $batch_ids = $wpdb->get_col( $query );
                    
                if ( empty( $batch_ids ) ) {
                    break; // No more images in database
                }
                    
                // Filter by file size
                foreach ( $batch_ids as $id ) {
                    if ( count( $ids ) >= $limit ) {
                        break;
                    }
                        
                    $file = get_attached_file( $id );
                    if ( ! $file || ! file_exists( $file ) ) {
                        continue;
                    }
                        
                    if ( filesize( $file ) >= $min_file_size ) {
                        $ids[] = $id;
                    }
                }
                    
                $current_offset += $batch_size;
            }
        }
            
        if ( $ids_only ) {
            return $ids;
        }
    
        $candidates = [];
        foreach ( $ids as $id ) {
            $file = get_attached_file( $id );
            if ( ! $file || ! file_exists( $file ) ) continue;
                
            $file_size = filesize( $file );
    
            $post_parent = get_post_field( 'post_parent', $id );
            $parent_title = $post_parent ? get_the_title( $post_parent ) : __( 'Orphaned', 'wp-genius' );
            $parent_url = $post_parent ? get_edit_post_link( $post_parent ) : '';
            $thumb = wp_get_attachment_image_src( $id, 'thumbnail' );
    
            $candidates[] = [
                'id'          => $id,
                'fileName'    => basename( $file ),
                'fileSize'    => round( $file_size / 1024, 2 ), // KB
                'thumbUrl'    => $thumb ? $thumb[0] : '',
                'parentTitle' => $parent_title,
                'parentUrl'   => $parent_url,
                'mime'        => get_post_mime_type( $id ),
            ];
        }
    
        return $candidates;
    }
    
    /**
     * Get recent unprocessed post IDs
     */
    private function get_recent_unprocessed_post_ids( $limit = 10 ) {
        global $wpdb;
        
        // Get processed post IDs
        $processed_posts = get_option( 'w2p_media_turbo_processed_posts', [] );
        
        $query = "SELECT ID FROM {$wpdb->posts} 
                  WHERE post_type = 'post' 
                  AND post_status = 'publish'";
        
        if ( ! empty( $processed_posts ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $processed_posts ), '%d' ) );
            $query .= " AND ID NOT IN ($placeholders)";
            $query .= " ORDER BY post_date DESC LIMIT " . absint( $limit );
            
            return $wpdb->get_col( $wpdb->prepare( $query, $processed_posts ) );
        } else {
            $query .= " ORDER BY post_date DESC LIMIT " . absint( $limit );
            
            return $wpdb->get_col( $query );
        }
    }
    
    /**
     * Mark post as processed
     */
    public function mark_post_as_processed( $post_id ) {
        $processed_posts = get_option( 'w2p_media_turbo_processed_posts', [] );
        if ( ! in_array( $post_id, $processed_posts ) ) {
            $processed_posts[] = $post_id;
            update_option( 'w2p_media_turbo_processed_posts', $processed_posts );
        }
    }

    /**
     * Replace URL in all post content and return count
     */
    public function replace_url_in_content( $old_url, $new_url, $attachment_id = 0 ) {
        global $wpdb;
        @set_time_limit( 300 );

        W2P_Logger::debug( "[Replace] Old URL: $old_url", 'media-turbo' );
        W2P_Logger::debug( "[Replace] New URL: $new_url", 'media-turbo' );

        $upload_dir = wp_get_upload_dir();
        $base_url = $upload_dir['baseurl'];
        W2P_Logger::debug( "[Replace] Upload base URL: $base_url", 'media-turbo' );
        
        // Extract the path relative to the uploads base
        $old_rel_path = str_replace( $base_url, '', $old_url );
        $new_rel_path = str_replace( $base_url, '', $new_url );

        if ( empty( $old_rel_path ) || $old_rel_path === $old_url ) {
            // Fallback to basename matching if something is wrong with the path
            $old_rel_path = '/' . basename( $old_url );
            $new_rel_path = '/' . basename( $new_url );
        }

        $old_base_name = pathinfo( $old_rel_path, PATHINFO_FILENAME );
        $old_ext = pathinfo( $old_rel_path, PATHINFO_EXTENSION );
        $new_base_name = pathinfo( $new_rel_path, PATHINFO_FILENAME );

        // We will build a list of patterns to search for
        $searches = [];
        
        // 1. Full absolute URL
        $searches[] = $old_url;
        
        // 2. Protocol relative URL
        $searches[] = preg_replace( '/^https?:/', '', $old_url );
        
        // 3. Absolute path from root
        $home_url = home_url();
        $searches[] = str_replace( $home_url, '', $old_url );

        // 4. Just the relative path within uploads (very common)
        $searches[] = $old_rel_path;
        
        // Remove empty or duplicate searches
        $searches = array_unique( array_filter( $searches ) );
        W2P_Logger::debug( "[Replace] Search patterns: " . print_r( $searches, true ), 'media-turbo' );

        $total_affected = 0;
        $processed_posts = [];

        // First, check the parent post specifically (most likely location)
        if ( $attachment_id ) {
            $parent_id = get_post_field( 'post_parent', $attachment_id );
            if ( $parent_id ) {
                $content = get_post_field( 'post_content', $parent_id );
                W2P_Logger::debug( "[Replace] Parent post content length: " . strlen( $content ) . " chars", 'media-turbo' );
                
                // Show a sample of content containing the image filename
                if ( strpos( $content, $old_base_name ) !== false ) {
                    preg_match( '/.{0,100}' . preg_quote( $old_base_name, '/' ) . '.{0,100}/s', $content, $matches );
                    if ( ! empty( $matches[0] ) ) {
                        W2P_Logger::debug( "[Replace] Sample content around image: " . substr( $matches[0], 0, 200 ), 'media-turbo' );
                    }
                } else {
                    W2P_Logger::warning( "[Replace] WARNING: Filename '$old_base_name' NOT found in post content!", 'media-turbo' );
                }
                
                $new_content = $this->apply_replacements_to_content( $content, $searches, $old_base_name, $old_ext, $new_base_name );
                
                if ( $new_content !== $content ) {
                    W2P_Logger::debug( "[Replace] Content changed, updating database...", 'media-turbo' );
                    
                    // First clear cache BEFORE update to prevent race conditions
                    clean_post_cache( $parent_id );
                    
                    $updated = $wpdb->update( 
                        $wpdb->posts, 
                        [ 
                            'post_content' => $new_content,
                            'post_modified' => current_time( 'mysql' ),
                            'post_modified_gmt' => current_time( 'mysql', 1 )
                        ], 
                        [ 'ID' => $parent_id ],
                        [ '%s', '%s', '%s' ],
                        [ '%d' ]
                    );
                    
                    if ( $updated === false ) {
                        W2P_Logger::error( "[Replace] ERROR: Database update failed for post ID: $parent_id. wpdb error: " . $wpdb->last_error, 'media-turbo' );
                    } else {
                        W2P_Logger::debug( "[Replace] Database update returned: $updated (rows affected)", 'media-turbo' );
                        
                        // Clear cache again after update
                        clean_post_cache( $parent_id );
                        wp_cache_delete( $parent_id, 'posts' );
                        wp_cache_delete( $parent_id, 'post_meta' );
                        
                        // Verify the update by reading directly from database
                        $verify_content = $wpdb->get_var( $wpdb->prepare( 
                            "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", 
                            $parent_id 
                        ) );
                        
                        if ( strpos( $verify_content, '.webp' ) !== false ) {
                            W2P_Logger::debug( "[Replace] ✓ VERIFIED: Database contains .webp", 'media-turbo' );
                        } else {
                            W2P_Logger::error( "[Replace] ✗ CRITICAL ERROR: Database still contains old format!", 'media-turbo' );
                            W2P_Logger::debug( "[Replace] Sample of DB content: " . substr( $verify_content, 0, 300 ), 'media-turbo' );
                        }
                    }
                    
                    $total_affected++;
                    $processed_posts[] = $parent_id;
                    W2P_Logger::info( "[Replace] ✓ Replaced in parent post ID: $parent_id", 'media-turbo' );
                } else {
                    W2P_Logger::debug( "[Replace] ✗ No changes in parent post ID: $parent_id (image not found in content)", 'media-turbo' );
                }
            }
        }

        // Now search all other relevant posts using basename only (much faster)
        $basename_pattern = pathinfo( $old_rel_path, PATHINFO_BASENAME );
        W2P_Logger::debug( "[Replace] Searching for basename: $basename_pattern", 'media-turbo' );
        
        $query = "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type IN ('post', 'page')";
        if ( ! empty( $processed_posts ) ) {
            $query .= " AND ID NOT IN (" . implode( ',', array_map( 'intval', $processed_posts ) ) . ")";
        }

        $posts = $wpdb->get_results( $wpdb->prepare( $query, '%' . $wpdb->esc_like( $basename_pattern ) . '%' ) );
        W2P_Logger::debug( "[Replace] Found " . count( $posts ) . " posts to check", 'media-turbo' );
        
        if ( ! empty( $posts ) ) {
            foreach ( $posts as $post ) {
                $new_content = $this->apply_replacements_to_content( $post->post_content, $searches, $old_base_name, $old_ext, $new_base_name );
                
                if ( $new_content !== $post->post_content ) {
                    $updated = $wpdb->update( 
                        $wpdb->posts, 
                        [ 'post_content' => $new_content ], 
                        [ 'ID' => $post->ID ],
                        [ '%s' ],
                        [ '%d' ]
                    );
                    
                    if ( $updated === false ) {
                        W2P_Logger::error( "[Replace] ERROR: Database update failed for post ID: {$post->ID}. wpdb error: " . $wpdb->last_error, 'media-turbo' );
                    } else {
                        clean_post_cache( $post->ID );
                        $total_affected++;
                        W2P_Logger::info( "[Replace] ✓ Replaced in post ID: {$post->ID} (rows affected: $updated)", 'media-turbo' );
                    }
                }
            }
        }

        W2P_Logger::info( "[Replace] Final result: $total_affected posts updated", 'media-turbo' );
        return $total_affected;
    }

    /**
     * Helper to apply replacements with thumbnail support
     */
    private function apply_replacements_to_content( $content, $searches, $old_base, $ext, $new_base ) {
        $result = $content;
        $changes_made = false;
            
        W2P_Logger::debug( "[Replace] apply_replacements_to_content called", 'media-turbo' );
        W2P_Logger::debug( "[Replace] old_base: $old_base, ext: $ext, new_base: $new_base", 'media-turbo' );
            
        // Replace exact variations
        foreach ( $searches as $search ) {
            $replace = str_replace( $old_base . '.' . $ext, $new_base . '.webp', $search );
            W2P_Logger::debug( "[Replace] Trying to replace: '$search' -> '$replace'", 'media-turbo' );
                
            $before_length = strlen( $result );
            $result = str_replace( $search, $replace, $result );
            $after_length = strlen( $result );
                
            if ( $before_length !== $after_length ) {
                $changes_made = true;
                W2P_Logger::debug( "[Replace] ✓ Replaced! Content length changed: $before_length -> $after_length", 'media-turbo' );
            } else {
                W2P_Logger::debug( "[Replace] ✗ No match found for this pattern", 'media-turbo' );
            }
        }
    
        // Replace thumbnails specifically (e.g. filename-300x200.jpg -> filename-300x200.webp)
        $pattern = '/' . preg_quote( $old_base, '/' ) . '-(\\d+x\\d+)\\.' . preg_quote( $ext, '/' ) . '/i';
        W2P_Logger::debug( "[Replace] Thumbnail regex pattern: $pattern", 'media-turbo' );
            
        $before_length = strlen( $result );
        $result = preg_replace( $pattern, $new_base . '-$1.webp', $result );
        $after_length = strlen( $result );
            
        if ( $before_length !== $after_length ) {
            $changes_made = true;
            W2P_Logger::debug( "[Replace] ✓ Thumbnail replacements made! Content length changed: $before_length -> $after_length", 'media-turbo' );
        }
            
        if ( ! $changes_made ) {
            W2P_Logger::debug( "[Replace] WARNING: No replacements were made in content!", 'media-turbo' );
        }
    
        return $result;
    }

    /**
     * Check if WebP is supported by GD
     */
    public static function is_webp_supported() {
        return function_exists( 'imagewebp' );
    }
}
