<?php
/**
 * Frontend Enhancement Module CSF Configuration
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'module_id' => 'frontend-enhancement',
	'id'     => 'frontend_enhancement',
	'title'  => __( 'Frontend Enhancement', 'wp-genius' ),
	'icon'   => 'fa-solid fa-wand-magic-sparkles',
	'fields' => [
		[
			'id'    => 'frontend_enhancement_tabs',
			'type'  => 'tabbed',
			'tabs'  => [
				// Tab 1: Lightbox
				[
					'title'  => __( 'Lightbox', 'wp-genius' ),
					'icon'   => 'fa fa-image',
					'fields' => [
						[
							'id'      => 'lightbox_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Lightbox', 'wp-genius' ),
							'desc'    => __( 'Enable Lightbox viewer for images in post content.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'lightbox_animation',
							'type'    => 'radio',
							'title'   => __( 'Open Animation', 'wp-genius' ),
							'options' => [
								'fade'  => __( 'Fade In/Out', 'wp-genius' ),
								'slide' => __( 'Slide', 'wp-genius' ),
								'zoom'  => __( 'Zoom', 'wp-genius' ),
							],
							'default' => 'fade',
						],
						[
							'id'      => 'lightbox_close_on_backdrop',
							'type'    => 'switcher',
							'title'   => __( 'Close on Backdrop Click', 'wp-genius' ),
							'desc'    => __( 'Close the Lightbox when clicking on the dark backdrop.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'lightbox_keyboard_nav',
							'type'    => 'switcher',
							'title'   => __( 'Keyboard Navigation', 'wp-genius' ),
							'desc'    => __( 'Enable arrow keys (←/→) for navigation and ESC to close.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'lightbox_show_counter',
							'type'    => 'switcher',
							'title'   => __( 'Show Image Counter', 'wp-genius' ),
							'desc'    => __( 'Display current image index (e.g., "3/10") in the Lightbox.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'lightbox_allow_set_featured',
							'type'    => 'switcher',
							'title'   => __( 'Allow Set as Featured Image', 'wp-genius' ),
							'desc'    => __( 'Show "Set as Featured Image" button in Lightbox toolbar (requires edit_posts permission).', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'lightbox_allow_delete',
							'type'    => 'switcher',
							'title'   => __( 'Allow Delete Image', 'wp-genius' ),
							'desc'    => __( 'Show "Delete Image" button in Lightbox toolbar (requires admin permissions).', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'lightbox_zoom_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Zoom Controls', 'wp-genius' ),
							'desc'    => __( 'Allow users to zoom in/out images.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'lightbox_max_zoom',
							'type'    => 'number',
							'title'   => __( 'Maximum Zoom Level', 'wp-genius' ),
							'desc'    => __( 'Maximum zoom multiplier (1.0 = original size, 3.0 = 3x zoom).', 'wp-genius' ),
							'default' => 3,
							'unit'    => 'x',
							'min'     => 1,
							'max'     => 5,
							'step'    => 0.5,
						],
						[
							'id'      => 'lightbox_autoplay_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Autoplay', 'wp-genius' ),
							'desc'    => __( 'Show autoplay controls in Lightbox toolbar.', 'wp-genius' ),
							'default' => false,
						],
						[
							'id'      => 'lightbox_autoplay_interval',
							'type'    => 'select',
							'title'   => __( 'Autoplay Interval', 'wp-genius' ),
							'desc'    => __( 'Interval between image transitions during autoplay.', 'wp-genius' ),
							'options' => [
								2 => __( '2 seconds', 'wp-genius' ),
								3 => __( '3 seconds', 'wp-genius' ),
								5 => __( '5 seconds', 'wp-genius' ),
							],
							'default' => 3,
						],
					],
				],
				
				// Tab 2: Video Player
				[
					'title'  => __( 'Video Player', 'wp-genius' ),
					'icon'   => 'fa fa-video',
					'fields' => [
						[
							'id'      => 'video_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Video Optimization', 'wp-genius' ),
							'desc'    => __( 'Enhance video player experience with additional features.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'video_extract_poster',
							'type'    => 'switcher',
							'title'   => __( 'Auto Extract Poster', 'wp-genius' ),
							'desc'    => __( 'Automatically extract the first frame as video poster image.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'video_exclusive_playback',
							'type'    => 'switcher',
							'title'   => __( 'Exclusive Playback', 'wp-genius' ),
							'desc'    => __( 'Automatically pause other videos when one starts playing.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'video_lightbox_button',
							'type'    => 'switcher',
							'title'   => __( 'Show Lightbox Button', 'wp-genius' ),
							'desc'    => __( 'Add "Play in Lightbox" button overlay on videos.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'video_supported_formats',
							'type'    => 'text',
							'title'   => __( 'Supported Video Formats', 'wp-genius' ),
							'desc'    => __( 'Comma-separated list of video file extensions. Recommended: mp4, webm, ogg (best browser compatibility). Note: mkv, avi, mov formats have limited browser support.', 'wp-genius' ),
							'default' => 'mp4,webm,ogg,ogv,mkv,mov,avi,m4v,3gp,flv',
						],
						[
							'id'      => 'video_lightbox_on_click',
							'type'    => 'switcher',
							'title'   => __( 'Open Lightbox on Click', 'wp-genius' ),
							'desc'    => __( 'Clicking on video opens it in Lightbox instead of playing inline.', 'wp-genius' ),
							'default' => false,
						],
						[
							'id'      => 'video_autoplay_prevention',
							'type'    => 'switcher',
							'title'   => __( 'Prevent Autoplay', 'wp-genius' ),
							'desc'    => __( 'Remove autoplay attribute from embedded videos for better user experience.', 'wp-genius' ),
							'default' => true,
						],
					],
				],
				
				// Tab 3: Reader Mode
				[
					'title'  => __( 'Reader Mode', 'wp-genius' ),
					'icon'   => 'fa fa-book-open',
					'fields' => [
						[
							'id'      => 'reader_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Reader Mode', 'wp-genius' ),
							'desc'    => __( 'Enable the reading enhancement toolbar on book chapters.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'reader_font_size',
							'type'    => 'number',
							'title'   => __( 'Default Font Size', 'wp-genius' ),
							'desc'    => __( 'Default font size (12-40px, step 2).', 'wp-genius' ),
							'default' => 18,
							'unit'    => 'px',
							'min'     => 12,
							'max'     => 40,
							'step'    => 2,
						],
						[
							'id'      => 'reader_font_family',
							'type'    => 'select',
							'title'   => __( 'Default Font Family', 'wp-genius' ),
							'options' => [
								'sans'      => __( '系统默认', 'wp-genius' ),
								'heiti'     => __( '黑体', 'wp-genius' ),
								'songti'    => __( '宋体', 'wp-genius' ),
								'kaiti'     => __( '楷体', 'wp-genius' ),
								'lishu'     => __( '隶书', 'wp-genius' ),
								'yahei'     => __( '微软雅黑', 'wp-genius' ),
								'droidsans' => __( '思源黑体', 'wp-genius' ),
							],
							'default' => 'sans',
						],
						[
							'id'      => 'reader_theme',
							'type'    => 'select',
							'title'   => __( 'Default Theme', 'wp-genius' ),
							'options' => [
								'light' => __( '明亮模式', 'wp-genius' ),
								'sepia' => __( '护眼模式', 'wp-genius' ),
								'green' => __( '自然模式', 'wp-genius' ),
								'dark'  => __( '暗黑模式', 'wp-genius' ),
							],
							'default' => 'light',
						],
					],
				],
				
				// Tab 4: Code Highlight
				[
					'title'  => __( 'Code Highlight', 'wp-genius' ),
					'icon'   => 'fa fa-code',
					'fields' => [
						[
							'id'      => 'code_highlight_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Code Highlighting', 'wp-genius' ),
							'desc'    => __( 'Enable syntax highlighting for code blocks.', 'wp-genius' ),
							'default' => false,
						],
						[
							'id'      => 'code_highlight_theme',
							'type'    => 'select',
							'title'   => __( 'Highlighting Theme', 'wp-genius' ),
							'desc'    => __( 'Select the theme for code highlighting.', 'wp-genius' ),
							'options' => [
								'default'        => __( 'Default', 'wp-genius' ),
								'coy'            => 'Coy',
								'dark'           => 'Dark',
								'funky'          => 'Funky',
								'okaidia'        => 'Okaidia',
								'solarizedlight' => 'Solarized Light',
								'tomorrow'       => 'Tomorrow',
								'twilight'       => 'Twilight',
							],
							'default' => 'default',
						],
						[
							'id'      => 'code_highlight_font_family',
							'type'    => 'select',
							'title'   => __( 'Font Family', 'wp-genius' ),
							'desc'    => __( 'Select the font family for code blocks.', 'wp-genius' ),
							'options' => [
								'monospace'       => 'Monospace',
								'consolas'        => 'Consolas',
								'courier'         => 'Courier',
								'fira-code'       => 'Fira Code',
								'source-code-pro' => 'Source Code Pro',
							],
							'default' => 'monospace',
						],
						[
							'id'      => 'code_highlight_line_numbers',
							'type'    => 'switcher',
							'title'   => __( 'Show Line Numbers', 'wp-genius' ),
							'desc'    => __( 'Display line numbers for code blocks.', 'wp-genius' ),
							'default' => false,
						],
						[
							'id'      => 'code_highlight_show_language',
							'type'    => 'switcher',
							'title'   => __( 'Show Language Label', 'wp-genius' ),
							'desc'    => __( 'Display the language name on code blocks.', 'wp-genius' ),
							'default' => false,
						],
						[
							'id'      => 'code_highlight_copy_clipboard',
							'type'    => 'switcher',
							'title'   => __( 'Enable Copy to Clipboard', 'wp-genius' ),
							'desc'    => __( 'Add a copy button to code blocks for easy copying.', 'wp-genius' ),
							'default' => false,
						],
						[
							'id'      => 'code_highlight_line_highlight',
							'type'    => 'switcher',
							'title'   => __( 'Enable Line Highlighting', 'wp-genius' ),
							'desc'    => __( 'Enable highlighting of specific lines in code blocks.', 'wp-genius' ),
							'default' => false,
						],
						[
							'id'      => 'code_highlight_command_line',
							'type'    => 'switcher',
							'title'   => __( 'Enable Command Line Style', 'wp-genius' ),
							'desc'    => __( 'Add command line interface styling to code blocks.', 'wp-genius' ),
							'default' => false,
						],
						[
							'id'      => 'code_highlight_singular_only',
							'type'    => 'switcher',
							'title'   => __( 'Apply to Singular Pages Only', 'wp-genius' ),
							'desc'    => __( 'Only apply code highlighting to single posts/pages, not to archive pages.', 'wp-genius' ),
							'default' => true,
						],
						[
							'id'      => 'code_highlight_custom_style',
							'type'    => 'code_editor',
							'title'   => __( 'Custom CSS', 'wp-genius' ),
							'desc'    => __( 'Add custom CSS to customize code highlighting appearance.', 'wp-genius' ),
							'settings' => [
								'theme' => 'mbo',
								'mode'  => 'css',
							],
							'default' => '',
						],
					],
				],
				
				// Tab 5: Audio Player (Coming Soon)
				[
					'title'  => __( 'Audio Player', 'wp-genius' ),
					'icon'   => 'fa fa-music',
					'fields' => [
						[
							'id'      => '_notice_audio_coming_soon',
							'type'    => 'notice',
							'style'   => 'info',
							'content' => __( 'Audio Player enhancement features are coming soon!', 'wp-genius' ),
						],
						[
							'id'      => 'audio_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Audio Player', 'wp-genius' ),
							'desc'    => __( 'Enable enhanced audio player (Coming Soon).', 'wp-genius' ),
							'default' => false,
						],
						[
							'id'      => 'audio_custom_player',
							'type'    => 'switcher',
							'title'   => __( 'Use Custom Player', 'wp-genius' ),
							'desc'    => __( 'Use custom audio player instead of browser default (Coming Soon).', 'wp-genius' ),
							'default' => false,
						],
					],
				],
			],
		],
	],
];
