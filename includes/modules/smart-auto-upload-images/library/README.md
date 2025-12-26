# Smart Auto Upload Images - Import External Images

Import external images automatically on save. Adds to media library and updates URLs. No manual downloads. Works with any post type.

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-6.8%20tested-blue)](https://wordpress.org/plugins/smart-auto-upload-images/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## Description

Smart Auto Upload Images automatically imports external images from your post content into your WordPress media library. When you save or update a post, the plugin detects any external image URLs, downloads them to your server, and replaces the original URLs with your hosted versions. This improves site performance, ensures image availability, and gives you complete control over your content.

## Why Auto Upload Images to Your Media Library?

When you copy content from external sources or use remote images, you risk broken images when the original source removes them. Hosting images on your own server provides several benefits:

* **Better SEO performance** - Search engines favor self-hosted images
* **Faster page load times** - Eliminates external HTTP requests
* **Full content control** - Images remain available even if sources go offline

## How Auto Upload Images Works

The plugin runs automatically whenever you save or update a post. Here's the process:

1. Scans post content for external image URLs (any image not hosted on your domain)
2. Downloads each external image to a temporary location
3. Validates image file integrity and format
4. Uploads valid images to your WordPress media library
5. Replaces original external URLs with new local URLs
6. Attaches imported images to your post in the media library

No manual intervention required. Just write your content and let the plugin handle the rest.

## Key Features

### Automatic External Image Detection

The plugin automatically identifies external images in your post content when you save. It distinguishes between local images (already hosted on your site) and external images that need importing.

### Smart URL Replacement

After importing images, the plugin intelligently replaces all instances of the external URL with your new local URL. This works with images in:

* Post content (Classic Editor and Gutenberg blocks)
* Image galleries
* Featured images

### Media Library Integration

All imported images are added to your WordPress media library with proper metadata. You can:

* Edit images using WordPress image editor
* View which post each image is attached to
* Set custom alt text during import
* Apply your site's image optimization settings

### Flexible Domain Exclusions

Exclude specific domains from auto-import. Useful for:

* CDN-hosted images you want to keep external
* Partner websites where you have permission to hotlink
* Your own secondary domains
* Social media embeds you want to keep as external

### Custom Post Type Control

Choose which post types trigger auto-upload. Enable for:

* Posts and pages (default)
* WooCommerce products
* Custom portfolio post types
* Documentation posts
* Or disable for specific types you want to skip

### Advanced File Naming Patterns

Set custom file naming patterns for imported images using dynamic tags:

* `%filename%` - Original filename
* `%post_title%` - Current post title
* `%post_id%` - Post ID
* `%image_title%` - Image title attribute
* `%date%` - Current date
* `%time%` - Current timestamp

Example: `%post_title%-%filename%` becomes `my-blog-post-example-image.jpg`

### Custom Alt Text Patterns

Define alt text patterns for better SEO:

* `%post_title%` - Use post title in alt text
* `%filename%` - Use filename as alt text
* Custom text - Set consistent alt text across imports

### Image Size Constraints

Set maximum width and height for imported images to:

* Control storage usage
* Maintain consistent image sizes
* Automatically resize oversized images
* Prevent huge images from slowing your site

### Featured Image from URL

Set a post's featured image using an external URL. The plugin will:

* Download the image from the URL
* Import it to your media library
* Set it as the post's featured image
* Work via REST API or post editor

## Installation

### From WordPress Dashboard

1. Navigate to **Plugins → Add New**
2. Search for "Smart Auto Upload Images"
3. Click **Install Now** and then **Activate**
4. Go to **Settings → Auto Upload Images** to configure

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/` directory
3. Extract the zip file
4. Activate through the **Plugins** menu in WordPress
5. Configure settings at **Settings → Auto Upload Images**

## Usage

### How to Import External Images from Posts

**Step 1:** Install and activate Auto Upload Images

**Step 2:** Go to **Settings → Auto Upload Images**

**Step 3:** Configure your preferences (or use defaults)

**Step 4:** Create or edit any post with external images

**Step 5:** Click Save or Update - images import automatically

**Step 6:** Check your Media Library to see imported images

### How to Exclude Specific Domains

If you want to prevent images from certain domains from being imported:

**Step 1:** Go to **Settings → Auto Upload Images**

**Step 2:** Find the "Excluded Domains" section

**Step 3:** Enter domains one per line (e.g., `cdn.example.com`)

**Step 4:** Save settings

**Step 5:** Images from excluded domains will be left as external URLs

### How to Set Custom File Names for Imported Images

**Step 1:** Navigate to **Settings → Auto Upload Images**

**Step 2:** Find "File Name Pattern" setting

**Step 3:** Enter your pattern using available tags:
* Example: `%post_title%-%filename%`
* Example: `imported-%date%-%filename%`

**Step 4:** Save settings

**Step 5:** New imports will use your naming pattern

This helps organize your media library and improves SEO with descriptive file names.

### How to Set Featured Image via URL

#### Using the Post Editor:

**Step 1:** Edit your post

**Step 2:** Find the Featured Image section in the sidebar

**Step 3:** Enter the external image URL in the "Set from URL" field

**Step 4:** The image imports automatically and sets as featured image

## Integration with Page Builders

Auto Upload Images works with popular page builders:

### Gutenberg Block Editor
All images in Gutenberg blocks are automatically detected and imported when you save the post.

### Classic Editor
External images in Classic Editor content are imported on post save.

### WooCommerce
Enable auto-import for Product post type to automatically import external product images.

### Custom Post Types
Configure any custom post type to trigger auto-import functionality.


## Performance and Storage Considerations

### Server Storage
Imported images consume server storage. Monitor your hosting plan's disk space if importing large quantities of images.

### Import Speed
Import time depends on:
* Image file sizes
* Your server's download speed
* Number of images per post
* Configured maximum dimensions

### Optimization Tips
* Set maximum width/height to reduce storage
* Use an image optimization plugin after import
* Exclude domains hosting very large images
* Test with small batches before bulk imports

## Developer Features

### Filter: smart_aui_validate_image_url

Programmatically control which image URLs get imported.

```php
add_filter( 'smart_aui_validate_image_url', function( $is_valid, $url ) {
	// Skip images from specific paths
	if ( strpos( $url, '/cdn/avatars/' ) !== false ) {
		return false;
	}
	return $is_valid;
}, 10, 2 );
```

### Additional Hooks

Check plugin documentation for additional filters and actions to customize behavior.

## Troubleshooting

### Images Not Importing

**Problem:** External images remain unchanged after saving post

**Solutions:**
* Check if domain is in excluded domains list
* Verify your server can make external HTTP requests
* Check WordPress debug log for errors
* Ensure PHP has necessary image processing libraries
* Verify write permissions on uploads directory

### Import Errors in Debug Log

**Problem:** Seeing errors in wp-content/debug.log

**Solutions:**
* Check image URL is publicly accessible
* Verify image format is supported (JPG, PNG, GIF, WebP)
* Ensure external server allows download/hotlinking
* Check SSL certificate validity if using HTTPS images

### Images Upload but URLs Not Replaced

**Problem:** Images added to media library but old URLs remain

**Solutions:**
* Clear any caching plugins
* Check post content in Text/HTML mode
* Verify images aren't in excluded domain list
* Review file naming pattern doesn't cause conflicts

### Duplicate Images in Media Library

**Problem:** Same image imported multiple times

**Solutions:**
* Plugin should detect and reuse existing images (v1.2.0+)
* Check if images have different URLs but same file
* Clear media library of duplicates and re-save post

### Featured Image Not Setting from URL

**Problem:** Featured image URL not importing

**Solutions:**
* Verify URL is publicly accessible
* Check image format is supported
* Ensure PHP memory limit is sufficient
* Review error logs for specific error messages

### Maximum Width/Height Not Applied

**Problem:** Images exceed configured dimensions

**Solutions:**
* Ensure GD or ImageMagick is installed on server
* Check PHP memory limit allows image processing
* Verify dimensions are set in plugin settings
* Test with smaller images first

## Frequently Asked Questions

### Does this work with Gutenberg blocks?

Yes, the plugin scans all post content including Gutenberg blocks for external image URLs and imports them automatically.

### Will this slow down my post save process?

Image import happens during post save, so very large images or many images may add a few seconds to save time. This is normal behavior for importing external content.

### What happens if the external image is removed?

Once imported, the image is hosted on your server, so you retain a copy even if the original source removes it. This is one of the main benefits of auto-importing external images.

### Can I exclude specific domains from import?

Yes, use the Excluded Domains setting to specify domains that should be left as external images. This is useful for CDN images or trusted partner sites.

### Does this work with WooCommerce products?

Yes, enable the Product post type in settings to auto-import external images in WooCommerce product descriptions.

### What image formats are supported?

JPG, JPEG, PNG, GIF, and WebP images are supported.

### Can I bulk import images from existing posts?

The plugin works on post save. To bulk import images from existing posts, you would need to update those posts (a bulk edit action could trigger imports on multiple posts).

### Will this import images from RSS feeds?

Yes, if you import posts via RSS, any external images in the imported content will be detected and imported when you save the posts.

### Does this affect site performance?

Image import happens server-side during post save. Once imported, images load from your server like any other media file. There's no performance impact on the frontend.

### Can I customize imported image file names?

Yes, use file name patterns in settings with dynamic tags like `%post_title%`, `%filename%`, `%date%`, etc. to create descriptive, SEO-friendly file names.

### Does this work with WordPress multisite?

Yes, each site in a multisite network can configure its own settings independently.

### How do I prevent duplicate imports?

Version 1.2.0+ automatically detects and reuses images that already exist in your media library based on the original image URL.

### What's the maximum image size I can import?

This is limited by your server's PHP memory limit and your configured maximum dimensions in plugin settings. Most servers can handle images up to 5-10MB.

### How do I troubleshoot failed imports?

Enable WordPress debug logging (WP_DEBUG_LOG) and check the wp-content/debug.log file for specific error messages about failed imports.

## Changelog

### [1.2.1] - 2025-11-01

#### Fixed
* Fixed a security issue. Thanks Wordfence for reporting it!

### [1.2.0] - 2025-09-28

#### Added
* Added featured image import from external URL - set featured images using remote image URLs
* Introduced `smart_aui_validate_image_url` filter hook for developers to customize image URL validation logic

#### Fixed
* Improved duplicate detection system - automatically reuses existing images in media library instead of importing duplicates
* Fixed undefined index PHP warning when processing images without complete metadata
* Enhanced image validation with better error handling and logging

### [1.1.1] - 2025-09-06

#### Fixed
* Fixed missing plugin files during WordPress.org deployment process
* Resolved asset loading issues in production environment

### [1.1.0] - 2025-09-06

#### Added
* Introduced new `%image_title%` dynamic tag for file naming patterns - use image title attributes in file names
* Added support for image title attribute extraction during import

#### Changed
* Replaced admin notices with modern snackbar notifications for better user experience
* Improved notification system with auto-dismiss functionality

#### Fixed
* Enhanced file name sanitization to properly handle special characters, spaces, and international characters
* Fixed image file naming conflicts with duplicate names

### [1.0.0]

* Initial Release

## License

This plugin is licensed under the GPL v2 or later.

## Contributors

* [burhandodhy](https://github.com/burhandodhy)

## Support

For support, feature requests, or bug reports, please visit the [WordPress.org support forum](https://wordpress.org/support/plugin/smart-auto-upload-images/)
