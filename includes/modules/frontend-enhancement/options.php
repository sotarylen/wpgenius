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

return array(
	'module_id' => 'frontend-enhancement',
	'id'        => 'frontend_enhancement',
	'title'     => __( 'Frontend Enhancement', 'wp-genius' ),
	'icon'      => 'fa-solid fa-wand-magic-sparkles',
	'fields'    => array(
		array(
			'id'   => 'frontend_enhancement_tabs',
			'type' => 'tabbed',
			'tabs' => array(
				// Tab 1: Lightbox
				array(
					'title'  => __( 'Lightbox', 'wp-genius' ),
					'icon'   => 'fa fa-image',
					'fields' => array(
						array(
							'id'      => '_notice_lightbox_container',
							'type'    => 'notice',
							'style'   => 'info',
							'content' => __( 'The Lightbox intercepts the container with ID w2p-post-content. If clicking an image does not open the Lightbox, check whether the container id is set in your page template.', 'wp-genius' ),
						),
						array(
							'id'      => 'lightbox_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Lightbox', 'wp-genius' ),
							'label'   => __( 'Enable Lightbox viewer for images in post content.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'lightbox_animation',
							'type'    => 'radio',
							'title'   => __( 'Open Animation', 'wp-genius' ),
							'options' => array(
								'fade'  => __( 'Fade In/Out', 'wp-genius' ),
								'slide' => __( 'Slide', 'wp-genius' ),
								'zoom'  => __( 'Zoom', 'wp-genius' ),
							),
							'default' => 'fade',
						),
						array(
							'id'      => 'lightbox_close_on_backdrop',
							'type'    => 'switcher',
							'title'   => __( 'Close on Backdrop Click', 'wp-genius' ),
							'label'   => __( 'Close the Lightbox when clicking on the dark backdrop.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'lightbox_keyboard_nav',
							'type'    => 'switcher',
							'title'   => __( 'Keyboard Navigation', 'wp-genius' ),
							'label'   => __( 'Enable arrow keys (←/→) for navigation and ESC to close.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'lightbox_show_counter',
							'type'    => 'switcher',
							'title'   => __( 'Show Image Counter', 'wp-genius' ),
							'label'   => __( 'Display current image index (e.g., "3/10") in the Lightbox.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'lightbox_allow_set_featured',
							'type'    => 'switcher',
							'title'   => __( 'Allow Set as Featured Image', 'wp-genius' ),
							'label'   => __( 'Show "Set as Featured Image" button in Lightbox toolbar (requires edit_posts permission).', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'lightbox_allow_delete',
							'type'    => 'switcher',
							'title'   => __( 'Allow Delete Image', 'wp-genius' ),
							'label'   => __( 'Show "Delete Image" button in Lightbox toolbar (requires admin permissions).', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'lightbox_zoom_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Zoom Controls', 'wp-genius' ),
							'label'   => __( 'Allow users to zoom in/out images.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'lightbox_max_zoom',
							'type'    => 'number',
							'title'   => __( 'Maximum Zoom Level', 'wp-genius' ),
							'label'   => __( 'Maximum zoom multiplier (1.0 = original size, 3.0 = 3x zoom).', 'wp-genius' ),
							'default' => 3,
							'unit'    => 'x',
							'min'     => 1,
							'max'     => 5,
							'step'    => 0.5,
						),
						array(
							'id'      => 'lightbox_autoplay_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Autoplay', 'wp-genius' ),
							'label'   => __( 'Show autoplay controls in Lightbox toolbar.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'lightbox_autoplay_interval',
							'type'    => 'select',
							'title'   => __( 'Autoplay Interval', 'wp-genius' ),
							'label'   => __( 'Interval between image transitions during autoplay.', 'wp-genius' ),
							'options' => array(
								2 => __( '2 seconds', 'wp-genius' ),
								3 => __( '3 seconds', 'wp-genius' ),
								5 => __( '5 seconds', 'wp-genius' ),
							),
							'default' => 3,
						),
						array(
							'id'      => 'masonry_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Masonry Layout', 'wp-genius' ),
							'label'   => __( 'Arrange consecutive images (2 or more) in a self-adapting waterfall layout. Columns stack independently, so mixed landscape/portrait images never leave large blank gaps. Single images are displayed as-is.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'         => 'masonry_columns',
							'type'       => 'slider',
							'title'      => __( 'Images Per Row', 'wp-genius' ),
							'subtitle'   => __( 'Number of columns in the waterfall layout (1-6). The browser distributes images across this many columns and each column stacks independently.', 'wp-genius' ),
							'min'        => 1,
							'max'        => 6,
							'step'       => 1,
							'default'    => 3,
							'unit'       => ' ' . __( 'images', 'wp-genius' ),
							'dependency' => array( 'masonry_enabled', '==', 'true' ),
						),
					),
				),

				// Tab 2: Video Player
				array(
					'title'  => __( 'Video Player', 'wp-genius' ),
					'icon'   => 'fa fa-video',
					'fields' => array(
						array(
							'id'      => 'video_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Video Optimization', 'wp-genius' ),
							'label'   => __( 'Enhance video player experience with additional features.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'video_extract_poster',
							'type'    => 'switcher',
							'title'   => __( 'Auto Extract Poster', 'wp-genius' ),
							'label'   => __( 'Automatically extract the first frame as video poster image.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'video_exclusive_playback',
							'type'    => 'switcher',
							'title'   => __( 'Exclusive Playback', 'wp-genius' ),
							'label'   => __( 'Automatically pause other videos when one starts playing.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'video_lightbox_button',
							'type'    => 'switcher',
							'title'   => __( 'Show Lightbox Button', 'wp-genius' ),
							'label'   => __( 'Add "Play in Lightbox" button overlay on videos.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'video_supported_formats',
							'type'    => 'text',
							'title'   => __( 'Supported Video Formats', 'wp-genius' ),
							'label'   => __( 'Comma-separated list of video file extensions. Recommended: mp4, webm, ogg (best browser compatibility). Note: mkv, avi, mov formats have limited browser support.', 'wp-genius' ),
							'default' => 'mp4,webm,ogg,ogv,mkv,mov,avi,m4v,3gp,flv',
						),
						array(
							'id'      => 'video_lightbox_on_click',
							'type'    => 'switcher',
							'title'   => __( 'Open Lightbox on Click', 'wp-genius' ),
							'label'   => __( 'Clicking on video opens it in Lightbox instead of playing inline.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'video_autoplay_prevention',
							'type'    => 'switcher',
							'title'   => __( 'Prevent Autoplay', 'wp-genius' ),
							'label'   => __( 'Remove autoplay attribute from embedded videos for better user experience.', 'wp-genius' ),
							'default' => true,
						),
					),
				),

				// Tab 3: Reader Mode
				array(
					'title'  => __( 'Reader Mode', 'wp-genius' ),
					'icon'   => 'fa fa-book-open',
					'fields' => array(
						array(
							'id'      => 'reader_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Reader Mode', 'wp-genius' ),
							'label'   => __( 'Enable the reading enhancement toolbar on book chapters.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'reader_font_size',
							'type'    => 'number',
							'title'   => __( 'Default Font Size', 'wp-genius' ),
							'label'   => __( 'Default font size (12-40px, step 2).', 'wp-genius' ),
							'default' => 18,
							'unit'    => 'px',
							'min'     => 12,
							'max'     => 40,
							'step'    => 2,
						),
						array(
							'id'      => 'reader_font_family',
							'type'    => 'select',
							'title'   => __( 'Default Font Family', 'wp-genius' ),
							'options' => array(
								'sans'      => __( 'System Default', 'wp-genius' ),
								'heiti'     => __( 'SimHei (Heiti)', 'wp-genius' ),
								'songti'    => __( 'SimSun (Songti)', 'wp-genius' ),
								'kaiti'     => __( 'KaiTi', 'wp-genius' ),
								'lishu'     => __( 'LiSu', 'wp-genius' ),
								'yahei'     => __( 'Microsoft YaHei', 'wp-genius' ),
								'droidsans' => __( 'Source Han Sans', 'wp-genius' ),
							),
							'default' => 'sans',
						),
						array(
							'id'      => 'reader_theme',
							'type'    => 'select',
							'title'   => __( 'Default Theme', 'wp-genius' ),
							'options' => array(
								'light' => __( 'Light Mode', 'wp-genius' ),
								'sepia' => __( 'Sepia Mode', 'wp-genius' ),
								'green' => __( 'Green Mode', 'wp-genius' ),
								'dark'  => __( 'Dark Mode', 'wp-genius' ),
							),
							'default' => 'light',
						),
					),
				),

				// Tab 4: Code Highlight
				array(
					'title'  => __( 'Code Highlight', 'wp-genius' ),
					'icon'   => 'fa fa-code',
					'fields' => array(
						array(
							'id'      => 'code_highlight_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Code Highlighting', 'wp-genius' ),
							'label'   => __( 'Enable syntax highlighting for code blocks.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'code_highlight_theme',
							'type'    => 'select',
							'title'   => __( 'Highlighting Theme', 'wp-genius' ),
							'label'   => __( 'Select the theme for code highlighting.', 'wp-genius' ),
							'options' => array(
								'default'        => __( 'Default', 'wp-genius' ),
								'coy'            => 'Coy',
								'dark'           => 'Dark',
								'funky'          => 'Funky',
								'okaidia'        => 'Okaidia',
								'solarizedlight' => 'Solarized Light',
								'tomorrow'       => 'Tomorrow',
								'twilight'       => 'Twilight',
							),
							'default' => 'default',
						),
						array(
							'id'      => 'code_highlight_font_family',
							'type'    => 'select',
							'title'   => __( 'Font Family', 'wp-genius' ),
							'label'   => __( 'Select the font family for code blocks.', 'wp-genius' ),
							'options' => array(
								'monospace'       => 'Monospace',
								'consolas'        => 'Consolas',
								'courier'         => 'Courier',
								'fira-code'       => 'Fira Code',
								'source-code-pro' => 'Source Code Pro',
							),
							'default' => 'monospace',
						),
						array(
							'id'      => 'code_highlight_line_numbers',
							'type'    => 'switcher',
							'title'   => __( 'Show Line Numbers', 'wp-genius' ),
							'label'   => __( 'Display line numbers for code blocks.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'code_highlight_show_language',
							'type'    => 'switcher',
							'title'   => __( 'Show Language Label', 'wp-genius' ),
							'label'   => __( 'Display the language name on code blocks.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'code_highlight_copy_clipboard',
							'type'    => 'switcher',
							'title'   => __( 'Enable Copy to Clipboard', 'wp-genius' ),
							'label'   => __( 'Add a copy button to code blocks for easy copying.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'code_highlight_line_highlight',
							'type'    => 'switcher',
							'title'   => __( 'Enable Line Highlighting', 'wp-genius' ),
							'label'   => __( 'Enable highlighting of specific lines in code blocks.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'code_highlight_command_line',
							'type'    => 'switcher',
							'title'   => __( 'Enable Command Line Style', 'wp-genius' ),
							'label'   => __( 'Add command line interface styling to code blocks.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'code_highlight_singular_only',
							'type'    => 'switcher',
							'title'   => __( 'Apply to Singular Pages Only', 'wp-genius' ),
							'label'   => __( 'Only apply code highlighting to single posts/pages, not to archive pages.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'       => 'code_highlight_custom_style',
							'type'     => 'code_editor',
							'title'    => __( 'Custom CSS', 'wp-genius' ),
							'label'    => __( 'Add custom CSS to customize code highlighting appearance.', 'wp-genius' ),
							'settings' => array(
								'theme' => 'mbo',
								'mode'  => 'css',
							),
							'default'  => '',
						),
					),
				),

				// Tab 5: Music (playlists, tags, lyrics; embed & LX Music in M3)
				array(
					'title'  => __( 'Music', 'wp-genius' ),
					'icon'   => 'fa fa-music',
					'fields' => array(
						array(
							'id'      => '_notice_music_container',
							'type'    => 'notice',
							'style'   => 'info',
							'content' => __( 'Music adds playlists, ID3 tag and lyric extraction for uploaded audio. Platform embeds and LX Music local integration settings take effect in the next milestone.', 'wp-genius' ),
						),
						array(
							'id'      => 'music_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable Music', 'wp-genius' ),
							'label'   => __( 'Enable playlists and audio metadata features.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'music_player_mode',
							'type'    => 'select',
							'title'   => __( 'Player Mode', 'wp-genius' ),
							'options' => array(
								'card' => __( 'Card', 'wp-genius' ),
								'mini' => __( 'Mini bar', 'wp-genius' ),
							),
							'default' => 'card',
						),
						array(
							'id'      => 'music_playlist_order',
							'type'    => 'select',
							'title'   => __( 'Default Playback Order', 'wp-genius' ),
							'options' => array(
								'sequence' => __( 'Sequence', 'wp-genius' ),
								'loop'     => __( 'Loop', 'wp-genius' ),
								'random'   => __( 'Random', 'wp-genius' ),
							),
							'default' => 'sequence',
						),
						array(
							'id'      => 'music_embed_default_platform',
							'type'    => 'select',
							'title'   => __( 'Default Embed Platform', 'wp-genius' ),
							'label'   => __( 'Takes effect when platform embeds ship (M3).', 'wp-genius' ),
							'options' => array(
								'netease' => __( 'NetEase Music', 'wp-genius' ),
							),
							'default' => 'netease',
						),
						array(
							'id'      => 'music_embed_autoplay',
							'type'    => 'switcher',
							'title'   => __( 'Auto-play Embeds', 'wp-genius' ),
							'label'   => __( 'Takes effect when platform embeds ship (M3).', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'music_tag_extract',
							'type'    => 'switcher',
							'title'   => __( 'Extract Tags on Upload', 'wp-genius' ),
							'label'   => __( 'Extract ID3 tags, lyrics and cover art from uploaded audio files.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'music_lyric_priority',
							'type'    => 'select',
							'title'   => __( 'Lyric Source Priority', 'wp-genius' ),
							'label'   => __( 'Prefer embedded file tags (USLT) or a sidecar .lrc file next to the audio.', 'wp-genius' ),
							'options' => array(
								'file' => __( 'File tag (USLT)', 'wp-genius' ),
								'lrc'  => __( 'Sidecar .lrc file', 'wp-genius' ),
							),
							'default' => 'file',
						),
						array(
							'id'      => '_subheading_music_lx',
							'type'    => 'subheading',
							'content' => __( 'LX Music local integration (takes effect in M3)', 'wp-genius' ),
						),
						array(
							'id'      => 'music_lx_enabled',
							'type'    => 'switcher',
							'title'   => __( 'Enable LX Music Integration', 'wp-genius' ),
							'label'   => __( 'Connect your local LX Music app (127.0.0.1). Visible to your own browser only.', 'wp-genius' ),
							'default' => false,
						),
						array(
							'id'      => 'music_lx_port',
							'type'    => 'number',
							'title'   => __( 'LX Music API Port', 'wp-genius' ),
							'default' => 23330,
							'min'     => 1,
							'max'     => 65535,
						),
						array(
							'id'      => 'music_lx_timeout',
							'type'    => 'number',
							'title'   => __( 'Status Timeout', 'wp-genius' ),
							'label'   => __( 'Milliseconds before LX Music is considered offline.', 'wp-genius' ),
							'default' => 2000,
							'unit'    => 'ms',
						),
						array(
							'id'      => 'music_lx_sse',
							'type'    => 'switcher',
							'title'   => __( 'Live Status via SSE', 'wp-genius' ),
							'label'   => __( 'Subscribe to LX Music status updates in real time.', 'wp-genius' ),
							'default' => true,
						),
						array(
							'id'      => 'music_lx_visible',
							'type'    => 'select',
							'title'   => __( 'LX Controls Visibility', 'wp-genius' ),
							'options' => array(
								'admin'  => __( 'Administrators only', 'wp-genius' ),
								'logged' => __( 'Logged-in users', 'wp-genius' ),
								'all'    => __( 'All visitors', 'wp-genius' ),
							),
							'default' => 'admin',
						),
					),
				),
			),
		),
	),
);
