<?php
/**
 * Code Highlight Handler Class
 * 
 * Backend handler for Prism.js code syntax highlighting.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Highlighting Handler
 */
class WPG_Highlight_Handler {

	/**
	 * Settings
	 */
	private $settings;

	/**
	 * Constructor
	 * 
	 * @param array $settings Module settings
	 */
	public function __construct( $settings = [] ) {
		$this->settings = $settings;
		$this->init();
	}

	/**
	 * Initialize highlighting hooks
	 */
	private function init() {
		if ( empty( $this->settings['code_highlight_enabled'] ) ) {
			return;
		}

		// Add the CSS class to content to enable Prism highlighting
		add_filter( 'the_content', [ $this, 'add_prism_class_to_pre' ] );
		
		// Enqueue language-specific JS files based on content
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_prism_language_scripts' ] );
		
		// Enqueue core assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_core_assets' ], 20 );
	}

	/**
	 * Enqueue core Prism assets and custom styles
	 */
	public function enqueue_core_assets() {
		// Only load on required pages if set
		if ( ! empty( $this->settings['code_highlight_singular_only'] ) && ! is_singular() ) {
			return;
		}

		// Enqueue Prism.js assets based on settings
		$theme = ! empty( $this->settings['code_highlight_theme'] ) ? $this->settings['code_highlight_theme'] : 'default';
		
		$theme_file_map = [
			'default'        => 'theme-default',
			'coy'            => 'theme-coy',
			'dark'           => 'theme-dark',
			'funky'          => 'theme-funky',
			'okaidia'        => 'theme-okaidia',
			'solarizedlight' => 'theme-solarized',
			'tomorrow'       => 'theme-tomorrow-night',
			'twilight'       => 'theme-twilight'
		];
		
		$theme_file = isset( $theme_file_map[$theme] ) ? $theme_file_map[$theme] : 'theme-default';
		
		wp_enqueue_style(
			'prism-css',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/' . $theme_file . '.css',
			[],
			'1.29.0'
		);
		
		wp_enqueue_script(
			'prism-js',
			plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/prism-core.js',
			[],
			'1.29.0',
			true
		);
		
		$needs_plugin_styles = false;
		
		if ( ! empty( $this->settings['code_highlight_line_numbers'] ) ) {
			wp_enqueue_script( 'prism-line-numbers', plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/plugin-line-numbers.js', [ 'prism-js' ], '1.29.0', true );
			$needs_plugin_styles = true;
		}
		
		if ( ! empty( $this->settings['code_highlight_show_language'] ) || ! empty( $this->settings['code_highlight_copy_clipboard'] ) ) {
			wp_enqueue_script( 'prism-toolbar', plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/plugin-toolbar.js', [ 'prism-js' ], '1.29.0', true );
			
			if ( ! empty( $this->settings['code_highlight_show_language'] ) ) {
				wp_enqueue_script( 'prism-show-language', plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/plugin-show-language.js', [ 'prism-js', 'prism-toolbar' ], '1.29.0', true );
			}
			
			if ( ! empty( $this->settings['code_highlight_copy_clipboard'] ) ) {
				wp_enqueue_script( 'prism-copy-clipboard', plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/plugin-copy-clipboard.js', [ 'prism-js', 'prism-toolbar' ], '1.29.0', true );
			}
			$needs_plugin_styles = true;
		}
		
		if ( ! empty( $this->settings['code_highlight_line_highlight'] ) ) {
			wp_enqueue_script( 'prism-line-highlight', plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/plugin-line-highlight.js', [ 'prism-js' ], '1.29.0', true );
			$needs_plugin_styles = true;
		}
		
		if ( ! empty( $this->settings['code_highlight_command_line'] ) ) {
			wp_enqueue_script( 'prism-command-line', plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/plugin-command-line.js', [ 'prism-js' ], '1.29.0', true );
			$needs_plugin_styles = true;
		}
		
		if ( $needs_plugin_styles ) {
			wp_enqueue_style( 'prism-plugin-styles', plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/plugin-styles.css', [ 'prism-css' ], '1.29.0' );
		}

		// Enqueue custom premium toolbar and color styles
		wp_enqueue_style(
			'wpg-code-highlight-custom',
			plugin_dir_url( WP_GENIUS_FILE ) . 'assets/css/modules/code-highlight.css',
			[ 'prism-css' ],
			'1.0.1'
		);
		
		// Add custom styles if provided
		if ( ! empty( $this->settings['code_highlight_custom_style'] ) ) {
			wp_add_inline_style( 'wpg-code-highlight-custom', $this->settings['code_highlight_custom_style'] );
		}
		
		// Add font family CSS variable
		$font_family = ! empty( $this->settings['code_highlight_font_family'] ) ? $this->settings['code_highlight_font_family'] : 'monospace';
		$font_stacks = [
			'monospace'       => 'monospace',
			'consolas'        => '"Consolas", "Monaco", "Lucida Console", monospace',
			'courier'         => '"Courier New", Courier, monospace',
			'fira-code'       => '"Fira Code", monospace',
			'source-code-pro' => '"Source Code Pro", monospace',
		];
		$font_stack = isset( $font_stacks[$font_family] ) ? $font_stacks[$font_family] : $font_family . ', monospace';

		wp_add_inline_style( 'wpg-code-highlight-custom', ':root { --wpg-code-font: ' . $font_stack . '; }' );
		
		// Run Prism highlighting
		wp_add_inline_script( 'prism-js', '(function() { if (typeof window.Prism !== "undefined") { if (document.readyState !== "loading") { Prism.highlightAll(); } else { document.addEventListener("DOMContentLoaded", function() { Prism.highlightAll(); }); } } })();' );
	}

	public function enqueue_prism_language_scripts() {
		if ( ! empty( $this->settings['code_highlight_singular_only'] ) && ! is_singular() ) {
			return;
		}
		
		$post_content = get_the_content();
		$languages = [ 'markup', 'css', 'javascript', 'php', 'clike' ];
		$detected_languages = $this->detect_used_languages( $post_content );
		$languages = array_unique( array_merge( $languages, $detected_languages ) );
		
		foreach ( $languages as $language ) {
			// Mapping for common aliases
			$lang_map = [
				'zsh'   => 'bash',
				'sh'    => 'bash',
				'shell' => 'bash',
				'js'    => 'javascript',
				'py'    => 'python'
			];
			$real_lang = isset( $lang_map[$language] ) ? $lang_map[$language] : $language;

			$lang_file_url = plugin_dir_url( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/lang-' . $real_lang . '.js';
			$lang_file_path = plugin_dir_path( WP_GENIUS_FILE ) . 'includes/modules/frontend-enhancement/assets/prism/lang-' . $real_lang . '.js';
			
			if ( file_exists( $lang_file_path ) ) {
				wp_enqueue_script( 'prism-lang-' . $real_lang, $lang_file_url, [ 'prism-js' ], '1.29.0', true );
			}
		}
	}

	/**
	 * Parse content to find code languages
	 */
	private function detect_used_languages( $content ) {
		$languages = [];
		if ( preg_match_all( '/<pre[^>]*class="[^"]*language-([a-z0-9-]+)[^>]*>.*?<\/pre>/is', $content, $matches ) ) {
			foreach ( $matches[1] as $lang ) {
				$lang = str_replace( [ 'lang-', 'language-' ], '', $lang );
				if ( ! in_array( $lang, $languages ) ) {
					$languages[] = $lang;
				}
			}
		}
		$detected_lang = $this->detect_language_from_content( $content );
		if ( $detected_lang && ! in_array( $detected_lang, $languages ) ) {
			$languages[] = $detected_lang;
		}
		return $languages;
	}

	/**
	 * Process content to add Prism classes
	 */
	public function add_prism_class_to_pre( $content ) {
		if ( ! empty( $this->settings['code_highlight_singular_only'] ) && ! is_singular() ) {
			return $content;
		}
		
		return preg_replace_callback( '/<pre(.*?)><code(.*?)>(.*?)<\/code><\/pre>/is', function( $matches ) {
			$attributes = $matches[1];
			$code_attributes = $matches[2];
			$code_content = $matches[3];
			
			$language = '';
			// Detect language from code attributes or pre attributes
			if ( preg_match( '/language-([a-zA-Z0-9-]+)/', $code_attributes, $lang_matches ) ) {
				$language = $lang_matches[1];
			} elseif ( preg_match( '/lang-([a-zA-Z0-9-]+)/', $code_attributes, $lang_matches ) ) {
				$language = $lang_matches[1];
			} elseif ( preg_match( '/language-([a-zA-Z0-9-]+)/', $attributes, $lang_matches ) ) {
				$language = $lang_matches[1];
			} elseif ( preg_match( '/lang-([a-zA-Z0-9-]+)/', $attributes, $lang_matches ) ) {
				$language = $lang_matches[1];
			}
			
			if ( ! $language && ! empty( $code_content ) ) {
				$language = $this->detect_language_from_content( $code_content );
			}
			
			$language_class = $language ? 'language-' . $language : 'language-none';
			
			if ( strpos( $attributes, 'class=' ) === false ) {
				$attributes .= ' class="' . $language_class . '"';
			} else {
				$attributes = preg_replace_callback( '/(class=)("|\')(.*?)("|\')/', function( $m ) use ( $language_class ) {
					$classes = preg_replace( '/\s*language-[a-zA-Z0-9-]+/', '', $m[3] );
					$classes = preg_replace( '/\s*lang-[a-zA-Z0-9-]+/', '', $classes );
					return 'class=' . $m[2] . trim( $classes ) . ' ' . $language_class . $m[4];
				}, $attributes );
			}
			
			if ( ! empty( $this->settings['code_highlight_line_numbers'] ) ) {
				$attributes = preg_replace_callback( '/(class=)("|\')(.*?)("|\')/', function( $m ) {
					if ( strpos( $m[3], 'line-numbers' ) === false ) {
						return 'class=' . $m[2] . trim( $m[3] ) . ' line-numbers' . $m[4];
					}
					return $m[0];
				}, $attributes );
			}
			
			return '<pre' . $attributes . '><code' . $code_attributes . '>' . $code_content . '</code></pre>';
		}, $content );
	}

	/**
	 * Guess language from content snippets
	 */
	private function detect_language_from_content( $content ) {
		$language_patterns = [
			'php' => [
				'patterns' => [
					'/^<\?php\b/im' => 20, '/^<\?=/im' => 20, '/\$[a-zA-Z_]/m' => 5,
					'/\bfunction\s+[a-zA-Z_]/m' => 3, '/\bclass\s+[a-zA-Z_]/m' => 3, '/\bnamespace\s+/m' => 4,
					'/\buse\s+[A-Z]/m' => 2, '/->/m' => 2, '/=>/' => 1, '/\bpublic\s+function/m' => 3
				],
				'threshold' => 8
			],
			'javascript' => [
				'patterns' => [
					'/\b(const|let|var)\s+[a-zA-Z_]/m' => 5, '/\bfunction\s+[a-zA-Z_]/m' => 3, '/=>\s*{/m' => 4,
					'/\bconsole\.log/m' => 4, '/\basync\s+function/m' => 4, '/\bawait\s+/m' => 3, '/\bimport\s+.*from/m' => 4,
					'/\bexport\s+(default|const)/m' => 4, '/\b(document|window)\./m' => 3
				],
				'threshold' => 8
			],
			'python' => [
				'patterns' => [
					'/^def\s+[a-zA-Z_]/m' => 5, '/^class\s+[A-Z]/m' => 5, '/^import\s+/m' => 3,
					'/^from\s+.*import/m' => 4, '/\bprint\s*\(/m' => 3, '/:\s*$/m' => 1, '/\bself\./m' => 3, '/\b__init__\b/m' => 5
				],
				'threshold' => 8
			],
			'java' => [
				'patterns' => [
					'/\bpublic\s+class\s+[A-Z]/m' => 5, '/\bimport\s+java\./m' => 5, '/\bSystem\.out\.print/m' => 4,
					'/\bpublic\s+static\s+void\s+main/m' => 5, '/\bextends\s+[A-Z]/m' => 3
				],
				'threshold' => 8
			],
			'css' => [
				'patterns' => [
					'/[.#]?[a-zA-Z][\w-]*\s*{[^}]*}/m' => 5, '/\w+\s*:\s*[^;]+;/m' => 2, '/@media/m' => 3, '/\bcolor\s*:/m' => 2
				],
				'threshold' => 6
			],
			'sql' => [
				'patterns' => [
					'/\bSELECT\b/mi' => 3, '/\bFROM\b/mi' => 3, '/\bUPDATE\b/mi' => 3, '/\bSET\b/mi' => 3, 
					'/\bINSERT\s+INTO\b/mi' => 5, '/\bWHERE\s+/mi' => 2, '/\bJOIN\s+/mi' => 2,
					'/\bCREATE\s+TABLE\b/mi' => 5, '/\bDROP\b/mi' => 3, '/\bALTER\b/mi' => 3
				],
				'threshold' => 5
			],
			'json' => [
				'patterns' => [ '/^\s*{[\s\S]*}\s*$/m' => 5, '/"\w+"\s*:\s*"/m' => 3, '/"\w+"\s*:\s*[\d\[\{]/m' => 3 ],
				'threshold' => 6
			],
			'markup' => [
				'patterns' => [ '/<html/mi' => 5, '/<head>/mi' => 4, '/<body>/mi' => 4, '/<div/mi' => 2, '/<\w+[^>]*>/m' => 1 ],
				'threshold' => 5
			],
			'bash' => [
				'patterns' => [ 
					'/^#!\/bin\/(ba)?sh/m' => 10, '/^#!\/bin\/zsh/m' => 10, 
					'/\becho\s+/m' => 2, '/^\s*\w+=.*/m' => 1, '/\$\{?\w+\}?/m' => 2,
					'/\bsudo\s+/m' => 2, '/\bapt-get\s+/m' => 3, '/\bnpm\s+/m' => 2
				],
				'threshold' => 4
			],
		];
		
		$scores = [];
		foreach ( $language_patterns as $lang => $config ) {
			$score = 0;
			foreach ( $config['patterns'] as $pattern => $weight ) {
				if ( preg_match( $pattern, $content ) ) {
					$score += $weight;
				}
			}
			if ( $score >= $config['threshold'] ) {
				$scores[$lang] = $score;
			}
		}
		
		if ( ! empty( $scores ) ) {
			arsort( $scores );
			return key( $scores );
		}
		
		return 'markup';
	}
}
