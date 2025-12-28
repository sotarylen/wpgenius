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
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path ) return false;

        $metadata = wp_get_attachment_metadata( $attachment_id );
        $base_dir = dirname( $file_path );
        
        // 1. Convert Original
        $new_original = $this->convert_to_webp( $file_path, $quality );
        if ( ! $new_original ) return false;

        $old_url = wp_get_attachment_url( $attachment_id );
        $new_url = str_replace( basename( $file_path ), basename( $new_original ), $old_url );

        // 2. Convert Thumbnails
        if ( ! empty( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $size => $info ) {
                $thumb_path = $base_dir . '/' . $info['file'];
                $new_thumb = $this->convert_to_webp( $thumb_path, $quality );
                if ( $new_thumb ) {
                    $metadata['sizes'][$size]['file'] = basename( $new_thumb );
                    $metadata['sizes'][$size]['mime-type'] = 'image/webp';
                }
            }
        }

        // 3. Update Metadata and Post
        $metadata['file'] = str_replace( basename( $file_path ), basename( $new_original ), $metadata['file'] );
        wp_update_attachment_metadata( $attachment_id, $metadata );
        update_attached_file( $attachment_id, $new_original );

        global $wpdb;
        $wpdb->update( 
            $wpdb->posts, 
            [ 'post_mime_type' => 'image/webp', 'guid' => $new_url ], 
            [ 'ID' => $attachment_id ] 
        );

        // 4. Replace in Content
        $affected = $this->replace_url_in_content( $old_url, $new_url, $attachment_id );

        return [
            'success' => true,
            'new_url' => $new_url,
            'affected' => $affected
        ];
    }

    /**
     * Get Total Candidate Count
     */
    public function get_total_candidate_count() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('image/jpeg', 'image/png')" );
    }

    /**
     * Get Conversion Candidates
     */
    public function get_conversion_candidates( $limit = 100, $offset = 0, $ids_only = false ) {
        global $wpdb;
        $query = "SELECT ID FROM {$wpdb->posts} 
                  WHERE post_type = 'attachment' 
                  AND post_mime_type IN ('image/jpeg', 'image/png') 
                  ORDER BY ID DESC";
        
        if ( $limit > 0 ) {
            $query .= " LIMIT " . absint( $limit ) . " OFFSET " . absint( $offset );
        }
        
        $ids = $wpdb->get_col( $query );
        if ( $ids_only ) {
            return $ids;
        }

        $candidates = [];
        foreach ( $ids as $id ) {
            $file = get_attached_file( $id );
            if ( ! $file || ! file_exists( $file ) ) continue;

            $post_parent = get_post_field( 'post_parent', $id );
            $parent_title = $post_parent ? get_the_title( $post_parent ) : __( 'Orphaned', 'wp-genius' );
            $parent_url = $post_parent ? get_edit_post_link( $post_parent ) : '';
            $thumb = wp_get_attachment_image_src( $id, 'thumbnail' );

            $candidates[] = [
                'id'          => $id,
                'fileName'    => basename( $file ),
                'thumbUrl'    => $thumb ? $thumb[0] : '',
                'parentTitle' => $parent_title,
                'parentUrl'   => $parent_url,
                'mime'        => get_post_mime_type( $id ),
            ];
        }

        return $candidates;
    }

    /**
     * Replace URL in all post content and return count
     */
    public function replace_url_in_content( $old_url, $new_url, $attachment_id = 0 ) {
        global $wpdb;
        @set_time_limit( 300 );

        $upload_dir = wp_get_upload_dir();
        $base_url = $upload_dir['baseurl'];
        
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

        $total_affected = 0;
        $processed_posts = [];

        // First, check the parent post specifically (most likely location)
        if ( $attachment_id ) {
            $parent_id = get_post_field( 'post_parent', $attachment_id );
            if ( $parent_id ) {
                $content = get_post_field( 'post_content', $parent_id );
                $new_content = $this->apply_replacements_to_content( $content, $searches, $old_base_name, $old_ext, $new_base_name );
                
                if ( $new_content !== $content ) {
                    $wpdb->update( $wpdb->posts, [ 'post_content' => $new_content ], [ 'ID' => $parent_id ] );
                    $total_affected++;
                    $processed_posts[] = $parent_id;
                    error_log( "[Media Turbo] Replaced in parent post: $parent_id" );
                }
            }
        }

        // Now search all other relevant posts
        foreach ( $searches as $search ) {
            $query = "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type IN ('post', 'page')";
            if ( ! empty( $processed_posts ) ) {
                $query .= " AND ID NOT IN (" . implode( ',', array_map( 'intval', $processed_posts ) ) . ")";
            }

            $post_ids = $wpdb->get_col( $wpdb->prepare( $query, '%' . $wpdb->esc_like( $search ) . '%' ) );
            
            if ( ! empty( $post_ids ) ) {
                foreach ( $post_ids as $pid ) {
                    $content = get_post_field( 'post_content', $pid );
                    $new_content = $this->apply_replacements_to_content( $content, [ $search ], $old_base_name, $old_ext, $new_base_name );
                    
                    if ( $new_content !== $content ) {
                        $wpdb->update( $wpdb->posts, [ 'post_content' => $new_content ], [ 'ID' => $pid ] );
                        $total_affected++;
                        $processed_posts[] = $pid;
                    }
                }
            }
        }

        return $total_affected;
    }

    /**
     * Helper to apply replacements with thumbnail support
     */
    private function apply_replacements_to_content( $content, $searches, $old_base, $ext, $new_base ) {
        $result = $content;
        
        // Replace exact variations
        foreach ( $searches as $search ) {
            $replace = str_replace( $old_base . '.' . $ext, $new_base . '.webp', $search );
            $result = str_replace( $search, $replace, $result );
        }

        // Replace thumbnails specifically (e.g. filename-300x200.jpg -> filename-300x200.webp)
        $pattern = '/' . preg_quote( $old_base, '/' ) . '-(\d+x\d+)\.' . preg_quote( $ext, '/' ) . '/i';
        $result = preg_replace( $pattern, $new_base . '-$1.webp', $result );

        return $result;
    }

    /**
     * Check if WebP is supported by GD
     */
    public static function is_webp_supported() {
        return function_exists( 'imagewebp' );
    }
}
