<?php
/**
 * Media Thumbnail Generation Service
 *
 * Handles thumbnail generation for media files.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MediaThumbnailService {

    /**
     * Generate thumbnails for an image based on WordPress intermediate sizes
     * 
     * @param string $source_image_path Path to the source image (usually the WebP converted from original)
     * @param int $attachment_id The attachment ID
     * @param array $existing_sizes Already existing thumbnail sizes to skip
     * 
     * @return array Status of generation with count of generated thumbnails
     */
    public function generate_thumbnails( $source_image_path, $attachment_id, $existing_sizes = [] ) {
        if ( ! $source_image_path || ! file_exists( $source_image_path ) ) {
            return [
                'success' => false,
                'message' => 'Source image not found',
                'generated' => 0
            ];
        }

        $generated_count = 0;
        $base_dir = dirname( $source_image_path );
        $base_name = pathinfo( $source_image_path, PATHINFO_FILENAME ); // Without extension

        // Get all registered image sizes
        $sizes = wp_get_registered_image_subsizes();
        
        if ( empty( $sizes ) ) {
            W2P_Logger::info( "No registered image sizes found for attachment ID: $attachment_id", 'media-turbo' );
            return [
                'success' => true,
                'message' => 'No sizes to generate',
                'generated' => 0
            ];
        }

        // Get source image information
        $source_info = @getimagesize( $source_image_path );
        if ( ! $source_info ) {
            W2P_Logger::error( "Cannot read source image: $source_image_path", 'media-turbo' );
            return [
                'success' => false,
                'message' => 'Cannot read source image',
                'generated' => 0
            ];
        }

        $source_width = $source_info[0];
        $source_height = $source_info[1];
        $source_mime = $source_info['mime'];

        W2P_Logger::info( "Starting thumbnail generation for ID: $attachment_id, source size: {$source_width}x{$source_height}", 'media-turbo' );

        foreach ( $sizes as $size_name => $size_info ) {
            $target_width = $size_info['width'] ?? 0;
            $target_height = $size_info['height'] ?? 0;
            $crop = $size_info['crop'] ?? false;

            if ( $target_width <= 0 || $target_height <= 0 ) {
                continue;
            }

            // Skip if this size already exists
            if ( isset( $existing_sizes[ $size_name ] ) ) {
                W2P_Logger::info( "Skipping size '$size_name': already exists", 'media-turbo' );
                continue;
            }

            // Skip if target size is larger than source (no need to upscale)
            if ( $target_width >= $source_width || $target_height >= $source_height ) {
                if ( ! $crop ) {
                    W2P_Logger::info( "Skipping size '$size_name': target ({$target_width}x{$target_height}) >= source ({$source_width}x{$source_height})", 'media-turbo' );
                    continue;
                }
            }

            $thumbnail_path = $this->generate_single_thumbnail( 
                $source_image_path, 
                $base_dir, 
                $base_name,
                $target_width, 
                $target_height, 
                $crop,
                $size_name
            );

            if ( $thumbnail_path ) {
                $generated_count++;
                W2P_Logger::info( "Generated thumbnail for size '$size_name': " . basename( $thumbnail_path ), 'media-turbo' );
            } else {
                W2P_Logger::warning( "Failed to generate thumbnail for size '$size_name'", 'media-turbo' );
            }
        }

        W2P_Logger::info( "Thumbnail generation complete: generated $generated_count thumbnails", 'media-turbo' );

        return [
            'success' => true,
            'message' => 'Thumbnails generated',
            'generated' => $generated_count
        ];
    }

    /**
     * Generate a single thumbnail
     * 
     * @param string $source_path Path to source image
     * @param string $base_dir Directory to save thumbnail
     * @param string $base_name Base filename without extension
     * @param int $width Target width
     * @param int $height Target height
     * @param bool|array $crop Crop setting
     * @param string $size_name Size name for generating filename
     * 
     * @return string|false Path to generated thumbnail or false
     */
    private function generate_single_thumbnail( $source_path, $base_dir, $base_name, $width, $height, $crop, $size_name ) {
        // Generate thumbnail filename with size information
        $thumbnail_filename = $this->generate_thumbnail_filename( $base_name, $width, $height );
        $thumbnail_path = $base_dir . '/' . $thumbnail_filename;

        // Skip if thumbnail already exists
        if ( file_exists( $thumbnail_path ) ) {
            W2P_Logger::info( "Thumbnail already exists, skipping: $thumbnail_filename", 'media-turbo' );
            return $thumbnail_path;
        }

        // Load the source image
        $image = $this->load_image( $source_path );
        if ( ! $image ) {
            return false;
        }

        // Get original dimensions
        $orig_width = imagesx( $image );
        $orig_height = imagesy( $image );

        // Calculate resize and crop dimensions
        $resize_width = $width;
        $resize_height = $height;

        if ( $crop ) {
            // Calculate dimensions to fill the target area
            $aspect_ratio = $orig_width / $orig_height;
            $target_ratio = $width / $height;

            if ( $aspect_ratio > $target_ratio ) {
                // Image is wider than target
                $resize_height = $height;
                $resize_width = (int) ( $height * $aspect_ratio );
            } else {
                // Image is taller than target
                $resize_width = $width;
                $resize_height = (int) ( $width / $aspect_ratio );
            }
        } else {
            // Proportional scaling
            $aspect_ratio = $orig_width / $orig_height;
            if ( $width / $height > $aspect_ratio ) {
                $resize_width = (int) ( $height * $aspect_ratio );
            } else {
                $resize_height = (int) ( $width / $aspect_ratio );
            }
        }

        // Create resized image
        $resized = imagecreatetruecolor( $resize_width, $resize_height );
        if ( ! $resized ) {
            imagedestroy( $image );
            return false;
        }

        // Handle transparency for PNG
        if ( strpos( strtolower( $source_path ), '.png' ) !== false ) {
            imagealphablending( $resized, false );
            imagesavealpha( $resized, true );
            $transparent = imagecolorallocatealpha( $resized, 0, 0, 0, 127 );
            imagefilledrectangle( $resized, 0, 0, $resize_width, $resize_height, $transparent );
        }

        // Resize
        imagecopyresampled( $resized, $image, 0, 0, 0, 0, $resize_width, $resize_height, $orig_width, $orig_height );

        // Crop if needed
        if ( $crop && ( $resize_width !== $width || $resize_height !== $height ) ) {
            $cropped = imagecreatetruecolor( $width, $height );
            if ( ! $cropped ) {
                imagedestroy( $image );
                imagedestroy( $resized );
                return false;
            }

            // Handle transparency for PNG
            if ( strpos( strtolower( $source_path ), '.png' ) !== false ) {
                imagealphablending( $cropped, false );
                imagesavealpha( $cropped, true );
                $transparent = imagecolorallocatealpha( $cropped, 0, 0, 0, 127 );
                imagefilledrectangle( $cropped, 0, 0, $width, $height, $transparent );
            }

            // Center crop
            $crop_x = (int) ( ( $resize_width - $width ) / 2 );
            $crop_y = (int) ( ( $resize_height - $height ) / 2 );
            imagecopy( $cropped, $resized, 0, 0, $crop_x, $crop_y, $width, $height );
            imagedestroy( $resized );
            $resized = $cropped;
        }

        // Save as WebP (same format as source since source is already WebP)
        $success = imagewebp( $resized, $thumbnail_path, 80 );
        imagedestroy( $image );
        imagedestroy( $resized );

        return $success ? $thumbnail_path : false;
    }

    /**
     * Load image from file path
     * 
     * @param string $path File path
     * 
     * @return resource|false GD image resource or false
     */
    private function load_image( $path ) {
        $info = @getimagesize( $path );
        if ( ! $info ) {
            return false;
        }

        $mime = $info['mime'];

        switch ( $mime ) {
            case 'image/webp':
                return @imagecreatefromwebp( $path );
            case 'image/jpeg':
                return @imagecreatefromjpeg( $path );
            case 'image/png':
                $image = @imagecreatefrompng( $path );
                if ( $image ) {
                    imagepalettetotruecolor( $image );
                    imagealphablending( $image, true );
                    imagesavealpha( $image, true );
                }
                return $image;
            case 'image/gif':
                return @imagecreatefromgif( $path );
            default:
                return false;
        }
    }

    /**
     * Generate thumbnail filename in WordPress format
     * e.g., image-300x200.webp
     * 
     * @param string $base_name Base filename without extension
     * @param int $width Width
     * @param int $height Height
     * 
     * @return string Thumbnail filename
     */
    private function generate_thumbnail_filename( $base_name, $width, $height ) {
        return $base_name . '-' . $width . 'x' . $height . '.webp';
    }

    /**
     * Update attachment metadata with generated thumbnails
     * 
     * @param int $attachment_id Attachment ID
     * @param array $thumbnail_files Array of generated thumbnail filenames
     * 
     * @return bool Success status
     */
    public function update_metadata_with_thumbnails( $attachment_id, $thumbnail_files = [] ) {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! is_array( $metadata ) ) {
            $metadata = [];
        }

        if ( ! isset( $metadata['sizes'] ) ) {
            $metadata['sizes'] = [];
        }

        // Add new thumbnails to metadata
        foreach ( $thumbnail_files as $size_name => $filename ) {
            $metadata['sizes'][ $size_name ] = [
                'file'      => $filename,
                'width'     => $size_info['width'] ?? 0,
                'height'    => $size_info['height'] ?? 0,
                'mime-type' => 'image/webp'
            ];
        }

        return wp_update_attachment_metadata( $attachment_id, $metadata );
    }
}
