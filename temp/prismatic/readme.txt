=== Prismatic ===

Plugin Name: Prismatic
Plugin URI: https://perishablepress.com/prismatic/
Description: Display beautiful syntax-highlighted code snippets with Prism.js or Highlight.js
Tags: code, snippets, syntax, highlight, language
Author: Jeff Starr
Contributors: specialk
Author URI: https://plugin-planet.com/
Donate link: https://monzillamedia.com/donate.html
Requires at least: 4.7
Tested up to: 6.9
Stable tag: 3.6
Version:    3.6
Requires PHP: 5.6.20
Text Domain: prismatic
Domain Path: /languages
License: GPL v2 or later

Display beautiful syntax-highlighted code snippets with Prism.js or Highlight.js



== Description ==

__The only 3-in-1 syntax highlighter!__

Display beautiful code snippets with Prism.js, Highlight.js, or plain code escaping:

* __Prism.js__     - Code escape + syntax highlight using [Prism.js](https://prismjs.com/)
* __Highlight.js__ - Code escape + syntax highlight using [Highlight.js](https://highlightjs.org/)
* __Plain Flavor__ - Code escape without syntax highlight

Check out a [demo post using Highlight.js](https://dev-tricks.com/favorite-highlight-js-styles/). I also use this plugin at [WP-Mix](https://wp-mix.com/) and [Plugin Planet](https://plugin-planet.com/docs/) for all code snippets :)


**Prism.js Features**

* Supports __60+__ coding languages
* Choose from all __8__ available Prism themes
* Provides a Gutenberg block for adding code snippets
* Provides TinyMCE/Visual buttons for adding code snippets
* Option to enable Prism plugin [Line Numbers](https://prismjs.com/plugins/line-numbers/)
* Option to enable Prism plugin [Line Highlight](https://prismjs.com/plugins/line-highlight/)
* Option to enable Prism plugin [Show Language](https://prismjs.com/plugins/show-language/)
* Option to enable Prism plugin [Copy Code Button](https://prismjs.com/plugins/copy-to-clipboard/)
* Option to enable Prism plugin [Command Line](https://prismjs.com/plugins/command-line/)
* Highlights code in post content, excerpts, and comments
* Detects `language-` and `lang-` class prefixes
* Limit syntax highlighting to Posts and Pages
* Highlight single-line and multi-line code
* Granular control over code escaping
* Smart loading of CSS &amp; JS assets
* Support for ACF on single post views

**Highlight.js Features**

* Supports __50+__ coding languages
* Choose from all __90+__ available Highlight themes
* Provides a Gutenberg block for adding code snippets
* Provides TinyMCE/Visual buttons for adding code snippets
* Customize the Highlight.js init JavaScript
* Highlights code in post content, excerpts, and comments
* Limit syntax highlighting to Posts and Pages
* Highlight multi-line blocks of code
* Detects `language-` and `lang-` class prefixes
* Enable support for no-prefix class names
* Granular control over code escaping
* Smart loading of CSS &amp; JS assets

**Plain Flavor Features**

* Enable code escaping for post content, excerpts, and/or comments
* Enable code escaping on the frontend, Admin Area, or both
* Provides a Gutenberg block for adding code snippets
* Provides TinyMCE/Visual buttons for adding code snippets
* Escapes single-line and multi-line code snippets
* Escapes `<code>` tags (based on configuration)

**General Features**

* Easy to set up and use
* Built with WordPress APIs
* Born of simplicity, no frills
* Squeaky clean, error-free code
* Lightweight, fast and flexible
* Focused on performance and security
* Loads CSS/JS assets only when required
* Adheres to HTML coding best practices
* Works with the Gutenberg Block Editor
* Regularly updated and "future proof"

_Prismatic escapes only the essentials to keep your code clean._

[Check out the screenshots](https://wordpress.org/plugins/prismatic/screenshots/) for more details!

**Privacy**

This plugin does not collect or store any user data. It does not set any cookies, and it does not connect to any third-party locations. Thus, this plugin does not affect user privacy in any way.

Prismatic is developed and maintained by [Jeff Starr](https://x.com/perishable), 15-year [WordPress developer](https://plugin-planet.com/) and [book author](https://books.perishablepress.com/).

**Support development**

I develop and maintain this free plugin with love for the WordPress community. To show support, you can [make a donation](https://monzillamedia.com/donate.html) or purchase one of my books: 

* [The Tao of WordPress](https://wp-tao.com/)
* [Digging into WordPress](https://digwp.com/)
* [.htaccess made easy](https://htaccessbook.com/)
* [WordPress Themes In Depth](https://wp-tao.com/wordpress-themes-book/)
* [Wizard's SQL Recipes for WordPress](https://books.perishablepress.com/downloads/wizards-collection-sql-recipes-wordpress/)

And/or purchase one of my premium WordPress plugins:

* [BBQ Pro](https://plugin-planet.com/bbq-pro/) - Blazing fast WordPress firewall
* [Blackhole Pro](https://plugin-planet.com/blackhole-pro/) - Automatically block bad bots
* [Banhammer Pro](https://plugin-planet.com/banhammer-pro/) - Monitor traffic and ban the bad guys
* [GA Google Analytics Pro](https://plugin-planet.com/ga-google-analytics-pro/) - Connect WordPress to Google Analytics
* [Head Meta Pro](https://plugin-planet.com/head-meta-pro/) - Ultimate Meta Tags for WordPress
* [Simple Ajax Chat Pro](https://plugin-planet.com/simple-ajax-chat-pro/) - Unlimited chat rooms
* [USP Pro](https://plugin-planet.com/usp-pro/) - Unlimited front-end forms

Links, tweets and likes also appreciated. Thank you! :)



== Screenshots ==

1.  Prismatic General Settings
2.  Prismatic Prism.js Settings
3.  Prismatic Highlight.js Settings
4.  Prismatic Plain Flavor Settings
5.  Prism.js : Twilight theme (choose from 7 Prism.js themes!)
6.  Highlight.js : Arduino Light theme (choose from 77 Highlight.js themes!)
7.  Highlight.js : Gruvbox Dark theme (choose from 77 Highlight.js themes!)
8.  Cleanly escaped code without syntax highlighting (Plain Flavor)
9.  Gutenberg Prismatic block (under Formatting menu)
10. Prismatic block showing added code and language select
11. Prismatic TinyMCE/Visual button for adding code snippets
12. Prismatic TinyMCE panel showing added code and selected language


== Installation ==

**Installing Prismatic**

1. Make a backup of your database
2. Upload the plugin to your blog and activate
3. Visit the plugin settings to configure options

_[More info on installing WP plugins](https://wordpress.org/support/article/managing-plugins/#installing-plugins)_


**Quick Start Guide**

Here is a quick guide to get started with Prismatic:

1. Activate the plugin and visit the Prismatic settings page
2. Choose Prism.js or Highlight.js for syntax highlighting
3. Optionally visit the Prism.js or Highlight.js tab to customize options

You are now ready to go. To add a code snippet to any WP Post or Page:

* If using Gutenberg Block Editor, click on the Prismatic block
* If using Classic Editor, click on the Prismatic TinyMCE button

To get a better idea, view the screenshots on the [Prismatic homepage](https://wordpress.org/plugins/prismatic/).

The Prismatic block or button makes it easy to add your code snippet and choose a language. The plugin automatically will output the correct markup to display your code with syntax highlighting. No code editing required!


**Complete Usage Documentation**

Learn more about Prismatic. Check out the [extended documentation](https://plugin-planet.com/wp/docs/prismatic.html) for complete information and tips.


**Like the plugin?**

If you like Prismatic, please take a moment to [give a 5-star rating](https://wordpress.org/support/plugin/prismatic/reviews/?rate=5#new-post). It helps to keep development and support going strong. Thank you!


**Uninstalling**

This plugin cleans up after itself. All plugin settings will be removed from the WordPress database when the plugin is uninstalled via the Plugins screen. 

__Note:__ that uninstalling the plugin will NOT touch any of your post content. Only the plugin options are removed when the plugin is uninstalled via the Plugins screen.


**Restore Default Options**

To restore default plugin options, either uninstall/reinstall the plugin or visit the Prismatic General Settings &gt; Restore default plugin options.



== Upgrade Notice ==

Visit the WordPress Plugins screen, locate the plugin, and click "Update" :)

__Note:__ Deleting this plugin via the WordPress Plugins screen will remove all of its settings from the WordPress database.



== Frequently Asked Questions ==

**Can you add another language for Prism.js or Highlight.js?**

Yes, feel free to [suggest a language](https://plugin-planet.com/support/#contact)


**Does this work with Gutenberg Block Editor?**

Yes, the plugin provides a "Prismatic" block that makes it easy to add code snippets that will be highlighted on the front-end. Also provides "add code" buttons for the Classic TinyMCE (Visual/Text) Editor. Add code, choose a language, done!


**Display syntax-highlighted code inside Block Editor?**

If for some reason you want to view syntax-highlighted code inside of the Block Editor, you can do it with the Classic Block:

1. Select the Classic Block
2. Click on the Prismatic TinyMCE button
3. Enter your code and save changes

The code won't be highlighted initially, but if you refresh the page after making changes, or visit the page again in the future, the code will be displayed with syntax highlighting applied.


**How to syntax highlight code inside of ACF field?**

As of Prismatic version 2.3, code snippets inside of ACF fields are highlighted automatically. Simply add the required class (e.g., `language-php`) just like any other code snippet, and the plugin will detect and highlight the code. To also escape the highlighted code, enable escaping for post content via the setting, Prism &gt; Code Escaping &gt; Content. Note: ACF is supported only on single post views.


**How to make highlighting work with Autoptimize?**

For Prismatic plugin to work with Autoptimize, a script needs to be excluded. For details, check out [this post](https://ncoughlin.com/prismatic-syntax-highlighter-compatibility-autoptimize-plugin/) by Nick Coughlin.


**How to disable block styles on frontend?**

If you are not using Gutenberg Block Editor, you can disable the plugin's block stylesheet. Simply enable the plugin setting, "Block Styles". Save changes and done.

FYI: the Prismatic block styles are included via: `/prismatic/css/styles-blocks.css`


**How to escape nested code tags?**

Currently the only way to display nested `<code>` tags is to use the following shortcode:

`[prismatic_code][/prismatic_code]`

Currently this works only for **inline** `<code>` tags. More details [here](https://wordpress.org/support/topic/sourcecode-with-tags-i-e-nested-tags/#post-13651551).


**Line numbers not working?**

Apparently some themes have problems displaying line numbers on highlighted code snippets. A possible solution is to add the following code via [theme template or simple plugin](https://digwp.com/2022/12/custom-code-wordpress/):

`function prismatic_add_body_class($classes) {
	
	$classes[] = 'line-numbers';
	
	return $classes;
	
}
add_filter('body_class', 'prismatic_add_body_class');`

All that's doing is adding a class named `line-numbers` to the body tag of your web pages. Should do the trick to get line numbers working on stubborn themes. Original idea posted [here](https://wordpress.org/support/topic/line-numbers-line-highlight-not-working/).


**How to filter the language menu?**

Check out [Filtered Language Menus with Prismatic WordPress Plugin](https://perishablepress.com/prismatic-filtered-language-menus/).


**How to disable highlighting code in comments?**

Check out [Disable Highlighting in Comments with Prismatic WordPress Plugin](https://perishablepress.com/prismatic-disable-highlight-comments/).


**How I can remember to include the shortcode tags?**

[Thanks](https://wordpress.org/support/topic/render-block-nested-shortcodes/) to [@hupe13](https://wordpress.org/support/users/hupe13/), you can add this snippet to make sure the shortcode is included when working with blocks:

`function prismatic_block_shortcode_mod($block_content, $block) {
	if ($block['blockName'] === 'prismatic/blocks') {
		if (strpos($block['innerHTML'], 'prismatic_code') === false) {
			return str_replace('[', '&#091;', $block['innerHTML']);
		}
	}
	return $block_content;
}
add_filter('render_block', 'prismatic_block_shortcode_mod', 10, 2);`


**Got a question?**

Send any questions or feedback via my [contact form](https://plugin-planet.com/support/#contact)



== Changelog ==

Thank you to everyone providing feedback! If you like Prismatic, please take a moment to [give a 5-star rating](https://wordpress.org/support/plugin/prismatic/reviews/?rate=5#new-post). It helps to keep development and support going strong. Thank you!


**3.6 (2025/11/17)**

* Adds filter hook, `prismatic_prism_enqueue_languages`
* Restores `load_i18n()`
* Updates plugin settings page
* Improves readme.txt documentation
* Generates new language template
* Tests on WordPress 6.9 (beta)


Full changelog @ [https://plugin-planet.com/wp/changelog/prismatic.txt](https://plugin-planet.com/wp/changelog/prismatic.txt)
