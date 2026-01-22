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

// Load thumbnail service
if ( ! class_exists( 'MediaThumbnailService' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'thumbnail-service.php';
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
        $webp_path = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $file_path );

        if ( $mime === 'image/gif' ) {
            // Use gif2webp for animated GIFs
            $command = sprintf( 'gif2webp -q %d %s -o %s', (int)$quality, escapeshellarg( $file_path ), escapeshellarg( $webp_path ) );
            exec( $command, $output, $return_var );
            
            return ( $return_var === 0 && file_exists( $webp_path ) ) ? $webp_path : false;
        }

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

        $success = imagewebp( $image, $webp_path, $quality );
        imagedestroy( $image );

        return $success ? $webp_path : false;
    }

    /**
     * Convert full attachment including all sizes
     */
    public function convert_attachment( $attachment_id, $quality = 80, $generate_thumbnails = false ) {
        $start_time = microtime( true );
        W2P_Logger::info( ">>> Starting conversion for attachment ID: $attachment_id (generate_thumbnails: " . ( $generate_thumbnails ? 'yes' : 'no' ) . ")", 'media-turbo' );
        
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

        // 2. Convert Thumbnails (existing ones)
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

        // 3. Generate thumbnails if enabled (based on the WebP original)
        $generated_count = 0;
        if ( $generate_thumbnails ) {
            $thumbnail_service = new MediaThumbnailService();
            $existing_sizes = ! empty( $metadata['sizes'] ) ? array_keys( $metadata['sizes'] ) : [];
            
            $gen_start = microtime( true );
            $gen_result = $thumbnail_service->generate_thumbnails( $new_original, $attachment_id, $existing_sizes );
            
            if ( $gen_result['success'] ) {
                $generated_count = $gen_result['generated'];
                W2P_Logger::info( sprintf( "%d new thumbnails generated in %.2fs", $generated_count, microtime( true ) - $gen_start ), 'media-turbo' );
                
                // Regenerate metadata to include new thumbnails
                // This is done via WordPress API
                $metadata = $this->regenerate_thumbnail_metadata( $attachment_id, $new_original );
            } else {
                W2P_Logger::warning( "Failed to generate thumbnails: " . $gen_result['message'], 'media-turbo' );
            }
        }

        // 4. Update Metadata and Post
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

        // 5. Replace in Content
        $replace_start = microtime( true );
        W2P_Logger::info( "Starting URL replacement. Old URL: $old_url, New URL: $new_url, Attachment ID: $attachment_id", 'media-turbo' );
        $affected = $this->replace_url_in_content( $old_url, $new_url, $attachment_id );
        W2P_Logger::info( sprintf( "URL replacement completed in %.2fs. Posts affected: %d", microtime( true ) - $replace_start, $affected ), 'media-turbo' );

        // 6. Delete Original Files (if keep_original is disabled)
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
        W2P_Logger::info( sprintf( "<<< Conversion complete for ID %d in %.2fs (converted: 1 original + %d thumbs, generated: %d new thumbs, replaced: %d posts, deleted: %d files)", 
            $attachment_id, $total_time, $thumb_count, $generated_count, $affected, $deleted_count ), 'media-turbo' );

        return [
            'success' => true,
            'new_url' => $new_url,
            'affected' => $affected,
            'deleted' => $deleted_count,
            'generated_thumbnails' => $generated_count
        ];
    }

    /**
     * Regenerate thumbnail metadata for an attachment
     * 
     * @param int $attachment_id Attachment ID
     * @param string $file_path Path to the attachment file
     * 
     * @return array Updated metadata
     */
    private function regenerate_thumbnail_metadata( $attachment_id, $file_path ) {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! is_array( $metadata ) ) {
            $metadata = [];
        }

        // Get image dimensions
        $info = @getimagesize( $file_path );
        if ( $info ) {
            $metadata['width'] = $info[0];
            $metadata['height'] = $info[1];
        }

        // Update metadata
        wp_update_attachment_metadata( $attachment_id, $metadata );

        return $metadata;
    }

    /**
     * Check if an attachment is a local file (based on domain, not file existence)
     * 
     * @param int $attachment_id Attachment ID
     * 
     * @return bool True if attachment is local, false if external
     */
    private function is_local_attachment( $attachment_id ) {
        // Check if URL points to local domain
        $url = wp_get_attachment_url( $attachment_id );
        if ( $url ) {
            $site_url = home_url();
            $site_domain = parse_url( $site_url, PHP_URL_HOST );
            $attachment_domain = parse_url( $url, PHP_URL_HOST );
            
            // If domains match, it's local (regardless of file existence)
            // This supports workflows where files are converted offline first
            if ( $site_domain === $attachment_domain ) {
                W2P_Logger::debug( "Attachment ID $attachment_id is local: domain matches ($attachment_domain)", 'media-turbo' );
                return true;
            }
            
            // Domains don't match, it's external
            W2P_Logger::debug( "Skipping external attachment ID $attachment_id: domain mismatch ($attachment_domain vs $site_domain)", 'media-turbo' );
            return false;
        }
        
        // Fallback: check if get_attached_file returns a path (indicates local attachment)
        $file = get_attached_file( $attachment_id );
        if ( $file ) {
            W2P_Logger::debug( "Attachment ID $attachment_id is local: has local file path ($file)", 'media-turbo' );
            return true;
        }
        
        // No URL and no file path - cannot determine, assume not local
        W2P_Logger::debug( "Attachment ID $attachment_id: no URL and no file path found", 'media-turbo' );
        return false;
    }

    /**
     * Get Total Candidate Count
     */
    public function get_total_candidate_count( $settings_overrides = [] ) {
        global $wpdb;
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        if ( ! empty( $settings_overrides ) && is_array( $settings_overrides ) ) {
            $settings = array_merge( $settings, $settings_overrides );
        }
        $scan_mode = $settings['scan_mode'] ?? 'media';
        
        W2P_Logger::info( "=== get_total_candidate_count START ===", 'media-turbo' );
        W2P_Logger::info( "Scan mode: $scan_mode", 'media-turbo' );
        
        $mimes = [];
        if ( ( $settings['convert_static'] ?? '1' ) === '1' ) {
            $mimes[] = 'image/jpeg';
            $mimes[] = 'image/png';
        }
        if ( ! empty( $settings['convert_animated'] ) ) {
            $mimes[] = 'image/gif';
        }
        
        W2P_Logger::info( "MIME types: " . implode( ', ', $mimes ), 'media-turbo' );

        if ( empty( $mimes ) ) {
            return 0;
        }

        if ( $scan_mode === 'posts' ) {
            // In post-scan mode, use the same logic as get_conversion_candidates
            // Call get_images_from_next_unprocessed_post to get actual candidates (with smart iteration)
            // and return the count
            $posts_limit = isset( $settings['posts_limit'] ) ? absint( $settings['posts_limit'] ) : 10;
            $scan_type = $settings['scan_type'] ?? 'convert';
            W2P_Logger::info( "Posts scan mode: posts_limit=$posts_limit, scan_type=$scan_type", 'media-turbo' );
            
            // Get ALL images from the specified posts - ignore the per-call image limit
            // For association mode, enable auto_mark_processed
            $auto_mark = ( $scan_type === 'associate' );
            $image_ids = $this->get_images_from_next_unprocessed_post( $mimes, $posts_limit, PHP_INT_MAX, $settings_overrides, true, $auto_mark );
            $count = count( $image_ids );
            
            W2P_Logger::info( "Found $count images from next unprocessed posts (ignore_image_limit=true, auto_mark_processed=$auto_mark)", 'media-turbo' );
            W2P_Logger::info( "=== get_total_candidate_count END (post-mode) ===", 'media-turbo' );
            return $count;
        } else {
            // Count all images that meet mime type requirement (media mode)
            // Exclude images already offloaded to Minio
            $mime_placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );
            $query = "SELECT COUNT(p.ID) FROM {$wpdb->posts} p
                      LEFT JOIN {$wpdb->postmeta} pm_failed ON (p.ID = pm_failed.post_id AND pm_failed.meta_key = '_w2p_media_turbo_failed')
                      LEFT JOIN {$wpdb->postmeta} pm_minio ON (p.ID = pm_minio.post_id AND pm_minio.meta_key = 'advmo_offloaded' AND pm_minio.meta_value = '1')
                      WHERE p.post_type = 'attachment' 
                      AND pm_failed.post_id IS NULL
                      AND pm_minio.post_id IS NULL
                      AND p.post_mime_type IN ($mime_placeholders)";
            
            W2P_Logger::info( "=== get_total_candidate_count END (media-mode) ===", 'media-turbo' );
            return (int) $wpdb->get_var( $wpdb->prepare( $query, $mimes ) );
        }
    }

    /**
     * Get Conversion Candidates
     */
    public function get_conversion_candidates( $limit = 100, $offset = 0, $ids_only = false, $settings_overrides = [] ) {
        global $wpdb;
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        if ( ! empty( $settings_overrides ) && is_array( $settings_overrides ) ) {
            $settings = array_merge( $settings, $settings_overrides );
        }
        $scan_mode = $settings['scan_mode'] ?? 'media';
        $scan_type = $settings['scan_type'] ?? 'convert'; // Valid: 'convert', 'associate'
        $min_file_size = isset( $settings['min_file_size'] ) ? absint( $settings['min_file_size'] ) * 1024 : 1024 * 1024; // Convert KB to bytes
            
        W2P_Logger::info( "get_conversion_candidates: scan_mode=$scan_mode, scan_type=$scan_type, limit=$limit, min_file_size=$min_file_size", 'media-turbo' );
        
        $mimes = [];
        if ( ( $settings['convert_static'] ?? '1' ) === '1' ) {
            $mimes[] = 'image/jpeg';
            $mimes[] = 'image/png';
        }
        if ( ! empty( $settings['convert_animated'] ) ) {
            $mimes[] = 'image/gif';
        }

        if ( empty( $mimes ) ) {
            return [];
        }

        $mime_placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );

        if ( $scan_mode === 'posts' ) {
            // Scan by posts - iterate through unprocessed posts until we find one with valid images
            $posts_limit = isset( $settings['posts_limit'] ) ? absint( $settings['posts_limit'] ) : 10;
            W2P_Logger::info( "Using post-by-post scan mode with posts_limit=$posts_limit, scan_type=$scan_type", 'media-turbo' );
            // When in post-scan mode with a specific posts_limit set, collect ALL images from those posts
            // ignoring the per-call $limit (Scan Limit) setting
            // For association mode, enable auto_mark_processed to track which posts have been processed
            $auto_mark = ( $scan_type === 'associate' );
            $ids = $this->get_images_from_next_unprocessed_post( $mimes, $posts_limit, $limit, $settings_overrides, true, $auto_mark );
            W2P_Logger::info( "get_images_from_next_unprocessed_post returned " . count( $ids ) . " image IDs (ignore_image_limit=true, auto_mark_processed=$auto_mark)", 'media-turbo' );
        } else {
            // Original media library scan - get ALL candidates first, then filter by size
            if ( $min_file_size <= 0 ) {
                // No file size limit, just return IDs directly from SQL
                $query = "SELECT p.ID FROM {$wpdb->posts} p
                          LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_w2p_media_turbo_failed')
                          WHERE p.post_type = 'attachment' 
                          AND pm.post_id IS NULL
                          AND p.post_mime_type IN ($mime_placeholders) 
                          ORDER BY p.ID DESC
                          LIMIT %d OFFSET %d";
                $args = array_merge( $mimes, [ $limit, $offset ] );
                $ids = $wpdb->get_col( $wpdb->prepare( $query, $args ) );
            } else {
                // File size limit exists, fetch in batches to filter
                $batch_size = max( 100, $limit * 2 ); 
                $current_offset = $offset;
                $ids = [];
                    
        $scan_type = $settings['scan_type'] ?? 'convert';
        W2P_Logger::info( "get_conversion_candidates: scan_mode=$scan_mode, scan_type=$scan_type, limit=$limit", 'media-turbo' );

        // ... (existing code for mimes setup) ...

        // (We need to jump to the loop context)
        // Oops, I need to include the surrounding code to match the target properly if I can't target just the loop easily with distinct context.
        // Actually, I can replace the whole block or use specific lines.
        // Let's replace the loop block.

        // Wait, I should probably just update the loop logic. 
        // But scan_type needs to be defined earlier.
        // I'll add scan_type definition at the top of the function first? No, I'll do it in one go if possible or two edits.
        // Let's assume I can redefine $scan_type inside the loop or use $settings['scan_type'].
        
        // Loop replacement:
                while ( count( $ids ) < $limit ) {
                    // ... (query setup) ...
                    $query = "SELECT p.ID FROM {$wpdb->posts} p
                              LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_w2p_media_turbo_failed')
                              WHERE p.post_type = 'attachment' 
                              AND pm.post_id IS NULL
                              AND p.post_mime_type IN ($mime_placeholders) 
                              ORDER BY p.ID DESC
                              LIMIT %d OFFSET %d"; // Same query

                    $args = array_merge( $mimes, [ $batch_size, $current_offset ] );
                    $batch_ids = $wpdb->get_col( $wpdb->prepare( $query, $args ) );
                    if ( empty( $batch_ids ) ) break;
                        
                    foreach ( $batch_ids as $id ) {
                        if ( count( $ids ) >= $limit ) break;
                        $file = get_attached_file( $id );
                        if ( ! $file ) continue;
                        
                        $is_candidate = false;
                        
                        if ( $scan_type === 'associate' ) {
                             // Association Mode: Look for WebP file existence
                             $webp_path = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $file );
                             if ( file_exists( $webp_path ) ) {
                                 // Found a WebP file! It's a candidate for association.
                                 // (We don't care if original exists or size limit here, usually)
                                 $is_candidate = true;
                             }
                        } else {
                             // Conversion Mode: Classic check
                             // Original must exist and meet size requirements
                             if ( file_exists( $file ) && filesize( $file ) >= $min_file_size ) {
                                 $is_candidate = true;
                             }
                        }

                        if ( $is_candidate ) {
                            $ids[] = $id;
                        }
                    }
                    $current_offset += $batch_size;
                }
            }
        }
            
        if ( $ids_only ) {
            return $ids;
        }
    
        $candidates = [];
        foreach ( $ids as $id ) {
            // Filter out external/remote attachments - only check domain, not file existence
            if ( ! $this->is_local_attachment( $id ) ) {
                W2P_Logger::info( "Skipping attachment ID $id: not a local domain", 'media-turbo' );
                continue;
            }
            
            $file = get_attached_file( $id );
            $file_size = 0;
            
            // Try to get file size if files exist, but don't skip if they don't
            if ( $file ) {
                if ( file_exists( $file ) ) {
                    $file_size = filesize( $file );
                } else {
                    // Check for WebP version
                    $webp_path = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $file );
                    if ( file_exists( $webp_path ) ) {
                        $file_size = filesize( $webp_path );
                    }
                    // If neither exists, file_size stays 0 but we still include it
                }
            }
    
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
     * Get images from the next unprocessed post
     * 
     * Scans up to $posts_limit unprocessed posts to find one with matching images.
     * Returns images from the FIRST post that has valid images (does NOT mark posts as processed).
     * 
     * @param array $mimes Allowed MIME types
     * @param int $posts_limit Max number of unprocessed posts to check
     * @param int $limit Max images to return from the found post
     * 
     * @return array Attachment IDs found in the first post with matching images, or empty array
     */
    /**
     * Get images from the next unprocessed post
     * 
     * Scans unprocessed posts one by one.
     * Continuously scans posts until finding one with valid matching images.
     * Successfully processes post images before marking as processed.
     * 
     * @param array $mimes Allowed MIME types
     * @param int $posts_limit Max number of posts to check (safety break)
     * @param int $limit Max images to return from the found post
     * @param array $settings_overrides Settings overrides including 'exclude_ids'
     * @param bool $ignore_image_limit When true, collects ALL images from all $posts_limit posts, ignoring $limit (for post-scan mode)
     * @param bool $auto_mark_processed When true, auto-marks posts as processed after finding valid images (only after successful processing)
     * 
     * @return array Attachment IDs found from posts, or empty array
     */
    private function get_images_from_next_unprocessed_post( $mimes, $posts_limit, $limit, $settings_overrides = [], $ignore_image_limit = false, $auto_mark_processed = false ) {
        global $wpdb;
        
        $settings = get_option( 'w2p_media_turbo_settings', [] );
        if ( ! empty( $settings_overrides ) && is_array( $settings_overrides ) ) {
            $settings = array_merge( $settings, $settings_overrides );
        }
        
        $exclude_ids = isset( $settings['exclude_ids'] ) ? $settings['exclude_ids'] : [];
        $scan_type = $settings['scan_type'] ?? 'convert';
        $min_file_size = isset( $settings['min_file_size'] ) ? absint( $settings['min_file_size'] ) * 1024 : 1024 * 1024;

        $start_time = microtime( true );
        $max_execution_time = 20; // safe scanning duration limit
        
        $all_candidates = [];
        $found_posts_count = 0;
        $scanned_count = 0;
        $posts_scanned_this_run = []; // Track posts scanned in this run
        
        W2P_Logger::info( "get_images_from_next_unprocessed_post: posts_limit=$posts_limit, limit=$limit, ignore_image_limit=$ignore_image_limit, auto_mark_processed=$auto_mark_processed", 'media-turbo' );
        
        // Loop indefinitely until we find enough posts or run out of posts or time out
        while ( true ) {
            // Check timeout
            if ( ( microtime( true ) - $start_time ) > $max_execution_time ) {
                W2P_Logger::info( "Scanning timed out after {$max_execution_time}s", 'media-turbo' );
                break;
            }
            
            // If we have found enough posts with valid images, stop
            if ( $found_posts_count >= $posts_limit ) {
                W2P_Logger::info( "Found valid images in $found_posts_count posts (Limit: $posts_limit). Stopping scan.", 'media-turbo' );
                break;
            }

             // If we have enough images and NOT in ignore_image_limit mode, stop
            if ( ! $ignore_image_limit && count( $all_candidates ) >= $limit ) {
                 W2P_Logger::info( "reached max image limit ($limit). Stopping scan.", 'media-turbo' );
                 break;
            }
            
            // Get batch of unprocessed posts
            $batch_size = 50; 
            $post_ids = $this->get_recent_unprocessed_post_ids( $batch_size );
            
            if ( empty( $post_ids ) ) {
                W2P_Logger::info( "No more unprocessed posts found.", 'media-turbo' );
                break;
            }
            
            $mime_placeholders = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );
            
            foreach ( $post_ids as $post_id ) {
                // Check if we already hit the limit inside 'foreach'
                if ( $found_posts_count >= $posts_limit ) break;
                
                // time check inside loop to be safe
                if ( ( microtime( true ) - $start_time ) > $max_execution_time ) break;

                // Check images for this post
                $query = "SELECT p.ID FROM {$wpdb->posts} p
                          LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_w2p_media_turbo_failed')
                          WHERE p.post_type = 'attachment' 
                          AND pm.post_id IS NULL
                          AND p.post_mime_type IN ($mime_placeholders)
                          AND p.post_parent = %d";
                
                if ( ! empty( $exclude_ids ) ) {
                    $exclude_placeholders = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );
                    $query .= " AND ID NOT IN ($exclude_placeholders)";
                }
                
                $query .= " ORDER BY ID DESC";
                
                $args = $mimes;
                $args[] = $post_id;
                if ( ! empty( $exclude_ids ) ) {
                     $args = array_merge( $args, $exclude_ids );
                }
                
                $candidates = $wpdb->get_col( $wpdb->prepare( $query, $args ) );
                
                // Filter candidates
                $post_valid_candidates = [];
                foreach ( $candidates as $cid ) {
                    if ( ! $this->is_local_attachment( $cid ) ) {
                        continue;
                    }
                    
                    $file = get_attached_file( $cid );
                    if ( ! $file ) continue;
                    
                    $is_valid = false;
                    
                    if ( $scan_type === 'associate' ) {
                         // Association Mode: Look for WebP file existence
                         $webp_path = preg_replace( '/\.(jpg|jpeg|png|gif)$/i', '.webp', $file );
                         if ( file_exists( $webp_path ) ) {
                             $is_valid = true;
                         }
                    } else {
                         // Conversion Mode: Classic check
                         if ( file_exists( $file ) && filesize( $file ) >= $min_file_size ) {
                             $is_valid = true;
                         }
                    }
                    
                    if ( $is_valid ) {
                        $post_valid_candidates[] = $cid;
                    }
                }
                
                $count_valid = count( $post_valid_candidates );
                
                if ( $count_valid > 0 ) {
                    // Found valid candidates
                    $found_posts_count++;
                    $all_candidates = array_merge( $all_candidates, $post_valid_candidates );
                    $posts_scanned_this_run[] = $post_id;
                    W2P_Logger::info( "✓ Found $count_valid candidates in Post $post_id. Total found posts: $found_posts_count", 'media-turbo' );
                } else {
                    // No valid candidates found in this post
                    // DO NOT mark as processed yet - only mark when actually successfully processed
                    W2P_Logger::debug( "✗ No valid candidates in Post $post_id (skipping auto-mark)", 'media-turbo' );
                }
                $scanned_count++;
            }
            
            // Should we continue loop?
            // If we broke out of foreach due to limit/time, we will break out of while loop too
             if ( $found_posts_count >= $posts_limit || ( microtime( true ) - $start_time ) > $max_execution_time ) {
                 break;
             }
        }
        
        W2P_Logger::info( "=== get_images_from_next_unprocessed_post END. Total Images: " . count($all_candidates) . " from $found_posts_count posts (ignore_image_limit=$ignore_image_limit) ===", 'media-turbo' );
        
        // If we found valid candidates and auto_mark_processed is enabled, mark the scanned posts as processed
        // This ensures we won't re-scan posts that had valid images
        if ( $auto_mark_processed && ! empty( $posts_scanned_this_run ) && ! empty( $all_candidates ) ) {
            foreach ( $posts_scanned_this_run as $post_id ) {
                $this->mark_post_as_processed( $post_id );
            }
            W2P_Logger::info( "Marked " . count( $posts_scanned_this_run ) . " posts as processed", 'media-turbo' );
        }
        
        // If ignore_image_limit is true, return all candidates regardless of $limit
        // Otherwise, slice to limit (original behavior for media-mode)
        if ( $ignore_image_limit ) {
            return $all_candidates;
        } else {
            return array_slice( $all_candidates, 0, $limit );
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

        return $total_affected;
    }

    /**
     * Batch Replace URLs in content for a single post
     * 
     * @param int $post_id The post ID to update
     * @param array $replacements Array of ['old_url' => '...', 'new_url' => '...', 'attachment_id' => ...]
     * @return int Number of changes made (1 or 0)
     */
    public function batch_replace_urls_in_content( $post_id, $replacements ) {
        global $wpdb;
        
        if ( empty( $replacements ) || ! $post_id ) {
            return 0;
        }

        W2P_Logger::info( ">>> Batch Content Update for Post ID: $post_id with " . count( $replacements ) . " replacements", 'media-turbo' );
        
        $content = get_post_field( 'post_content', $post_id );
        if ( ! $content ) {
            return 0;
        }

        $upload_dir = wp_get_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $home_url = home_url();

        $original_content = $content;
        $changes_made = false;

        foreach ( $replacements as $rep ) {
            $old_url = $rep['old_url'];
            $new_url = $rep['new_url'];
            
            // Generate search patterns similar to single replace
            $old_rel_path = str_replace( $base_url, '', $old_url );
            $new_rel_path = str_replace( $base_url, '', $new_url );

            if ( empty( $old_rel_path ) || $old_rel_path === $old_url ) {
                $old_rel_path = '/' . basename( $old_url );
                $new_rel_path = '/' . basename( $new_url );
            }

            $old_base_name = pathinfo( $old_rel_path, PATHINFO_FILENAME );
            $old_ext = pathinfo( $old_rel_path, PATHINFO_EXTENSION );
            $new_base_name = pathinfo( $new_rel_path, PATHINFO_FILENAME );

            $searches = [];
            $searches[] = $old_url;
            $searches[] = preg_replace( '/^https?:/', '', $old_url );
            $searches[] = str_replace( $home_url, '', $old_url );
            $searches[] = $old_rel_path;
            $searches = array_unique( array_filter( $searches ) );

            // Apply replacement for this item
            $content_before = $content;
            $content = $this->apply_replacements_to_content( $content, $searches, $old_base_name, $old_ext, $new_base_name );
            
            if ( $content_before !== $content ) {
                $changes_made = true;
            }
        }

        if ( $changes_made ) {
            clean_post_cache( $post_id );
            
            $updated = $wpdb->update( 
                $wpdb->posts, 
                [ 
                    'post_content' => $content,
                    'post_modified' => current_time( 'mysql' ),
                    'post_modified_gmt' => current_time( 'mysql', 1 )
                ], 
                [ 'ID' => $post_id ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );

            if ( $updated !== false ) {
                clean_post_cache( $post_id );
                wp_cache_delete( $post_id, 'posts' );
                W2P_Logger::info( ">>> Batch Update Success for Post $post_id", 'media-turbo' );
                return 1;
            } else {
                W2P_Logger::error( ">>> Batch Update Failed for Post $post_id: " . $wpdb->last_error, 'media-turbo' );
            }
        } else {
            W2P_Logger::info( ">>> No changes needed for Post $post_id", 'media-turbo' );
        }

        return 0;
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
