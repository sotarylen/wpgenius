=== WP Genius ===
Contributors: sotarylen
Tags: content management, automation, media, ai, word-to-post, optimization
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: MIT
License URI: https://opensource.org/licenses/MIT

A comprehensive toolkit for WordPress content management, optimization, and automation.

== Description ==

WP Genius combines multiple WordPress utilities into a single plugin platform for content creation, media management, and system optimization.

* **AI Content Engine** - AI-powered content creation with multi-model support (OpenAI, Anthropic, Gemini, DeepSeek), scheduled generation, and queue processing.
* **Word to Post** - Import Word (.docx) documents and convert them into posts with automatic chapter splitting.
* **Media Engine** - Unified media processing: WebP conversion, MinIO offload, thumbnail regeneration, and orphaned-media auditing.
* **Smart AUI Lite** - Memory-optimized remote image downloader for large files, with MinIO/S3 compatibility.
* **Auto Publish** - Schedule and batch-publish drafts.
* **Frontend Enhancement** - Lightbox, video optimization, reading mode, and code highlighting.
* **Post Duplicator** - Duplicate any post type including custom fields and taxonomies.
* **System Health** - Database cleanup, orphaned media scanning, and duplicate detection.
* **Accelerate** - Admin cleanup, update control, local avatars, and upload renaming.
* **SMTP Mailer** - Configure SMTP for reliable email delivery.

= Security =

Sensitive data (API keys, database passwords) is stored encrypted using site-specific keys. All inputs are sanitized, all outputs escaped, and state-changing operations verify nonces and capabilities.

== Installation ==

1. Upload the `wpgenius` folder to the `/wp-content/plugins/` directory, or install via Plugins → Add New → Upload Plugin.
2. Activate the plugin through the Plugins screen.
3. Go to Tools → WP Genius Settings to enable and configure modules.

= Requirements =

* WordPress 5.0+
* PHP 7.4+

== Frequently Asked Questions ==

= Where do I configure modules? =

Tools → WP Genius Settings. Enable/disable modules on the Module Management tab, then configure each enabled module in its own tab.

= Are my API keys stored securely? =

Yes. API keys and database passwords are encrypted with a key derived from your WordPress site's auth salt (libsodium / OpenSSL AES-256-GCM).

== Changelog ==

= 1.2.0 =
* Security: encrypt API keys and DB passwords (W2P_Crypto), eliminate SQL injection surface, remove CSRF-prone async API, unify W2P_ class prefixes.
* Performance: on-demand module loading, CSF framework loaded on admin only.
* Data layer: AI schedules moved to a custom table, unified settings facade, resilient queue processing with stuck-task recovery.
* Engineering: phpcs clean (0 errors), WPCS compliance, GitHub Actions CI, directory listing protection, version constant.

= 1.0.1 =
* Memory optimizations, large-file handling improvements, video capture, enhanced system health tools.

== Upgrade Notice ==

= 1.2.0 =
Security hardening and architecture refactors. AI schedules are automatically migrated to a custom table on first run; legacy schedule options are cleaned up on uninstall.
