<?php
/**
 * Frontend Enhancement Module Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'w2p_frontend_enhancement_settings', [] );
$defaults = [
	// Lightbox settings
	'lightbox_enabled'              => true,
	'lightbox_animation'            => 'fade',
	'lightbox_close_on_backdrop'    => true,
	'lightbox_keyboard_nav'         => true,
	'lightbox_show_counter'         => true,
	'lightbox_allow_set_featured'   => true,
	'lightbox_allow_delete'         => true,
	'lightbox_autoplay_enabled'     => false,
	'lightbox_autoplay_interval'    => 3,
	'lightbox_zoom_enabled'         => true,
	'lightbox_zoom_step'            => 0.2,
	'lightbox_max_zoom'             => 3,
	
	// Video optimization settings
	'video_enabled'                 => true,
	'video_extract_poster'          => true,
	'video_exclusive_playback'      => true,
	'video_lightbox_button'         => true,
	'video_lightbox_on_click'       => false,
	'video_autoplay_prevention'     => true,
	
	// Audio player settings (reserved)
	'audio_enabled'                 => false,
	'audio_custom_player'           => false,

	// Reader settings
	'reader_enabled'                => true,
	'reader_font_size'              => 18,
	'reader_font_family'            => 'sans',
	'reader_theme'                  => 'light',
	
	// Code highlighting settings
	'code_highlight_enabled'        => false,
	'code_highlight_theme'          => 'default',
	'code_highlight_line_numbers'   => false,
	'code_highlight_show_language'  => false,
	'code_highlight_copy_clipboard' => false,
	'code_highlight_line_highlight' => false,
	'code_highlight_command_line'   => false,
	'code_highlight_singular_only'  => true,
	'code_highlight_custom_style'   => '',
	'code_highlight_font_family'    => 'monospace', // Add default value for font family
];
$settings = wp_parse_args( $settings, $defaults );
?>

<div class="w2p-settings-panel">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'word2posts_save_module_settings', 'w2p_frontend_enhancement_nonce' ); ?>
		<input type="hidden" name="action" value="word2posts_save_module_settings" />
		<input type="hidden" name="module_id" value="frontend-enhancement" />
		
		<!-- Sub-tabs Navigation -->
		<div class="w2p-sub-tabs" id="w2p-frontend-enhancement-tabs">
			<div class="w2p-sub-tab-nav">
				<a class="w2p-sub-tab-link active" data-tab="lightbox">
					<i class="fas fa-image"></i>
					<?php esc_html_e( 'Lightbox', 'wp-genius' ); ?>
				</a>
				<a class="w2p-sub-tab-link" data-tab="video">
					<i class="fas fa-video"></i>
					<?php esc_html_e( 'Video Player', 'wp-genius' ); ?>
				</a>
				<a class="w2p-sub-tab-link" data-tab="reader">
					<i class="fas fa-book-open"></i>
					<?php esc_html_e( 'Reader Mode', 'wp-genius' ); ?>
				</a>
				<a class="w2p-sub-tab-link" data-tab="code-highlight">
					<i class="fas fa-code"></i>
					<?php esc_html_e( 'Code Highlight', 'wp-genius' ); ?>
				</a>
				<a class="w2p-sub-tab-link" data-tab="audio">
					<i class="fas fa-music"></i>
					<?php esc_html_e( 'Audio Player', 'wp-genius' ); ?>
					<span class="w2p-badge inactive"><?php esc_html_e( 'Coming Soon', 'wp-genius' ); ?></span>
				</a>
			</div>
			
			<div class="w2p-sub-tab-content active" id="w2p-tab-lightbox">
				<div class="w2p-section">
					<div class="w2p-section-header">
						<h4><?php esc_html_e( 'Lightbox Image Viewer', 'wp-genius' ); ?></h4>
					</div>
					<div class="w2p-section-body">
				
				<!-- Enable Lightbox -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_enabled">
							<?php esc_html_e( 'Enable Lightbox', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="lightbox_enabled" 
								   name="w2p_frontend_enhancement_settings[lightbox_enabled]" 
								   value="1" <?php checked( $settings['lightbox_enabled'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable Lightbox viewer for images in post content.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Open Animation (Radio Buttons) -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label>
							<?php esc_html_e( 'Open Animation', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<div class="w2p-radio-group">
							<label class="w2p-radio-item">
								<input type="radio" name="w2p_frontend_enhancement_settings[lightbox_animation]" 
									   value="fade" <?php checked( $settings['lightbox_animation'], 'fade' ); ?> />
								<span class="w2p-radio-label">
									<i class="fas fa-adjust"></i>
									<?php esc_html_e( 'Fade In/Out', 'wp-genius' ); ?>
								</span>
							</label>
							<label class="w2p-radio-item">
								<input type="radio" name="w2p_frontend_enhancement_settings[lightbox_animation]" 
									   value="slide" <?php checked( $settings['lightbox_animation'], 'slide' ); ?> />
								<span class="w2p-radio-label">
									<i class="fas fa-arrows-alt-h"></i>
									<?php esc_html_e( 'Slide', 'wp-genius' ); ?>
								</span>
							</label>
							<label class="w2p-radio-item">
								<input type="radio" name="w2p_frontend_enhancement_settings[lightbox_animation]" 
									   value="zoom" <?php checked( $settings['lightbox_animation'], 'zoom' ); ?> />
								<span class="w2p-radio-label">
									<i class="fas fa-search-plus"></i>
									<?php esc_html_e( 'Zoom', 'wp-genius' ); ?>
								</span>
							</label>
						</div>
					</div>
				</div>
				
				<!-- Close on Backdrop Click -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_close_on_backdrop">
							<?php esc_html_e( 'Close on Backdrop Click', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="lightbox_close_on_backdrop" 
								   name="w2p_frontend_enhancement_settings[lightbox_close_on_backdrop]" 
								   value="1" <?php checked( $settings['lightbox_close_on_backdrop'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Close the Lightbox when clicking on the dark backdrop.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Keyboard Navigation -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_keyboard_nav">
							<?php esc_html_e( 'Keyboard Navigation', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="lightbox_keyboard_nav" 
								   name="w2p_frontend_enhancement_settings[lightbox_keyboard_nav]" 
								   value="1" <?php checked( $settings['lightbox_keyboard_nav'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable arrow keys (←/→) for navigation and ESC to close.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Show Image Counter -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_show_counter">
							<?php esc_html_e( 'Show Image Counter', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="lightbox_show_counter" 
								   name="w2p_frontend_enhancement_settings[lightbox_show_counter]" 
								   value="1" <?php checked( $settings['lightbox_show_counter'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Display current image index (e.g., "3/10") in the Lightbox.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Allow Set as Featured Image -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_allow_set_featured">
							<?php esc_html_e( 'Allow Set as Featured Image', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="lightbox_allow_set_featured" 
								   name="w2p_frontend_enhancement_settings[lightbox_allow_set_featured]" 
								   value="1" <?php checked( $settings['lightbox_allow_set_featured'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Show "Set as Featured Image" button in Lightbox toolbar (requires edit_posts permission).', 'wp-genius' ); ?>
						</p>
					</div>
				</div>

				<!-- Allow Delete Image -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_allow_delete">
							<?php esc_html_e( 'Allow Delete Image', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="lightbox_allow_delete" 
								   name="w2p_frontend_enhancement_settings[lightbox_allow_delete]" 
								   value="1" <?php checked( isset( $settings['lightbox_allow_delete'] ) ? $settings['lightbox_allow_delete'] : 0, 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Show "Delete Image" button in Lightbox toolbar (requires admin permissions).', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Enable Zoom Controls -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_zoom_enabled">
							<?php esc_html_e( 'Enable Zoom Controls', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="lightbox_zoom_enabled" 
								   name="w2p_frontend_enhancement_settings[lightbox_zoom_enabled]" 
								   value="1" <?php checked( $settings['lightbox_zoom_enabled'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Allow users to zoom in/out images.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Maximum Zoom Level -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_max_zoom">
							<?php esc_html_e( 'Maximum Zoom Level', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<input type="number" id="lightbox_max_zoom" 
							   name="w2p_frontend_enhancement_settings[lightbox_max_zoom]" 
							   value="<?php echo esc_attr( $settings['lightbox_max_zoom'] ); ?>"
							   min="1" max="5" step="0.5" class="w2p-input-small" />
						<p class="description">
							<?php esc_html_e( 'Maximum zoom multiplier (1.0 = original size, 3.0 = 3x zoom).', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Enable Autoplay -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_autoplay_enabled">
							<?php esc_html_e( 'Enable Autoplay', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="lightbox_autoplay_enabled" 
								   name="w2p_frontend_enhancement_settings[lightbox_autoplay_enabled]" 
								   value="1" <?php checked( $settings['lightbox_autoplay_enabled'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Show autoplay controls in Lightbox toolbar.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Autoplay Interval -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="lightbox_autoplay_interval">
							<?php esc_html_e( 'Autoplay Interval', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<select id="lightbox_autoplay_interval" 
								name="w2p_frontend_enhancement_settings[lightbox_autoplay_interval]" 
								class="w2p-input-small">
							<option value="2" <?php selected( $settings['lightbox_autoplay_interval'], 2 ); ?>>
								<?php esc_html_e( '2 seconds', 'wp-genius' ); ?>
							</option>
							<option value="3" <?php selected( $settings['lightbox_autoplay_interval'], 3 ); ?>>
								<?php esc_html_e( '3 seconds', 'wp-genius' ); ?>
							</option>
							<option value="5" <?php selected( $settings['lightbox_autoplay_interval'], 5 ); ?>>
								<?php esc_html_e( '5 seconds', 'wp-genius' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Interval between image transitions during autoplay.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
					</div>
				</div>
			</div>
			<!-- End Tab: Lightbox -->
			
			<!-- ==================== Tab: Video Player ==================== -->
			<div class="w2p-sub-tab-content" id="w2p-tab-video">
				<div class="w2p-section">
					<div class="w2p-section-header">
						<h4><?php esc_html_e( 'Video Player Optimization', 'wp-genius' ); ?></h4>
					</div>
					<div class="w2p-section-body">
				
				<!-- Enable Video Optimization -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="video_enabled">
							<?php esc_html_e( 'Enable Video Optimization', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="video_enabled" 
								   name="w2p_frontend_enhancement_settings[video_enabled]" 
								   value="1" <?php checked( $settings['video_enabled'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enhance video player experience with additional features.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Auto Extract Poster -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="video_extract_poster">
							<?php esc_html_e( 'Auto Extract Poster', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="video_extract_poster" 
								   name="w2p_frontend_enhancement_settings[video_extract_poster]" 
								   value="1" <?php checked( $settings['video_extract_poster'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically extract the first frame as video poster image.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Exclusive Playback -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="video_exclusive_playback">
							<?php esc_html_e( 'Exclusive Playback', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="video_exclusive_playback" 
								   name="w2p_frontend_enhancement_settings[video_exclusive_playback]" 
								   value="1" <?php checked( $settings['video_exclusive_playback'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically pause other videos when one starts playing.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Show Lightbox Button -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="video_lightbox_button">
							<?php esc_html_e( 'Show Lightbox Button', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="video_lightbox_button" 
								   name="w2p_frontend_enhancement_settings[video_lightbox_button]" 
								   value="1" <?php checked( $settings['video_lightbox_button'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Add "Play in Lightbox" button overlay on videos.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Supported Video Formats -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="video_supported_formats">
							<?php esc_html_e( 'Supported Video Formats', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<?php
						$default_formats = 'mp4,webm,ogg,ogv,mkv,mov,avi,m4v,3gp,flv';
						$supported_formats = isset( $settings['video_supported_formats'] ) ? $settings['video_supported_formats'] : $default_formats;
						?>
						<input type="text" id="video_supported_formats" 
							   name="w2p_frontend_enhancement_settings[video_supported_formats]" 
							   value="<?php echo esc_attr( $supported_formats ); ?>" 
							   class="w2p-input-large" 
							   placeholder="mp4,webm,ogg,mkv" />
						<p class="description">
							<?php esc_html_e( 'Comma-separated list of video file extensions. Recommended: mp4, webm, ogg (best browser compatibility).', 'wp-genius' ); ?><br>
							<strong><?php esc_html_e( 'Note:', 'wp-genius' ); ?></strong> <?php esc_html_e( 'mkv, avi, mov formats have limited browser support.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Open Lightbox on Click -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="video_lightbox_on_click">
							<?php esc_html_e( 'Open Lightbox on Click', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="video_lightbox_on_click" 
								   name="w2p_frontend_enhancement_settings[video_lightbox_on_click]" 
								   value="1" <?php checked( $settings['video_lightbox_on_click'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Clicking on video opens it in Lightbox instead of playing inline.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Prevent Autoplay -->
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="video_autoplay_prevention">
							<?php esc_html_e( 'Prevent Autoplay', 'wp-genius' ); ?>
						</label>
					</div>
					<div class="w2p-form-control">
						<label class="w2p-switch">
							<input type="checkbox" id="video_autoplay_prevention" 
								   name="w2p_frontend_enhancement_settings[video_autoplay_prevention]" 
								   value="1" <?php checked( $settings['video_autoplay_prevention'], 1 ); ?> />
							<span class="w2p-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Remove autoplay attribute from embedded videos for better user experience.', 'wp-genius' ); ?>
						</p>
					</div>
				</div>
				
					</div>
				</div>
			</div>
			<!-- End Tab: Video Player -->
			
			<!-- ==================== Tab: Reader Mode ==================== -->
			<div class="w2p-sub-tab-content" id="w2p-tab-reader">
				<div class="w2p-section">
					<div class="w2p-section-header">
						<h4><?php esc_html_e( 'Book Chapter Reader', 'wp-genius' ); ?></h4>
					</div>
					<div class="w2p-section-body">
						
						<!-- Enable Reader -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="reader_enabled">
									<?php esc_html_e( 'Enable Reader Mode', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<label class="w2p-switch">
									<input type="checkbox" id="reader_enabled" 
										   name="w2p_frontend_enhancement_settings[reader_enabled]" 
										   value="1" <?php checked( isset( $settings['reader_enabled'] ) ? $settings['reader_enabled'] : 0, 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Enable the reading enhancement toolbar on book chapters.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>

						<!-- Default Font Size -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="reader_font_size">
									<?php esc_html_e( 'Default Font Size (px)', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<input type="number" id="reader_font_size" 
									   name="w2p_frontend_enhancement_settings[reader_font_size]" 
									   value="<?php echo esc_attr( isset( $settings['reader_font_size'] ) ? $settings['reader_font_size'] : 18 ); ?>"
									   min="12" max="40" step="2" class="w2p-input-small" />
								<p class="description">
									<?php esc_html_e( 'Default font size (12-40px, step 2).', 'wp-genius' ); ?>
								</p>
							</div>
						</div>

						<!-- Default Font Family -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="reader_font_family">
									<?php esc_html_e( 'Default Font Family', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<select id="reader_font_family" name="w2p_frontend_enhancement_settings[reader_font_family]">
									<option value="sans" <?php selected( isset( $settings['reader_font_family'] ) ? $settings['reader_font_family'] : 'sans', 'sans' ); ?>><?php esc_html_e( '系统默认', 'wp-genius' ); ?></option>
									<option value="heiti" <?php selected( isset( $settings['reader_font_family'] ) ? $settings['reader_font_family'] : '', 'heiti' ); ?>><?php esc_html_e( '黑体', 'wp-genius' ); ?></option>
									<option value="songti" <?php selected( isset( $settings['reader_font_family'] ) ? $settings['reader_font_family'] : '', 'songti' ); ?>><?php esc_html_e( '宋体', 'wp-genius' ); ?></option>
									<option value="kaiti" <?php selected( isset( $settings['reader_font_family'] ) ? $settings['reader_font_family'] : '', 'kaiti' ); ?>><?php esc_html_e( '楷体', 'wp-genius' ); ?></option>
									<option value="lishu" <?php selected( isset( $settings['reader_font_family'] ) ? $settings['reader_font_family'] : '', 'lishu' ); ?>><?php esc_html_e( '隶书', 'wp-genius' ); ?></option>
									<option value="yahei" <?php selected( isset( $settings['reader_font_family'] ) ? $settings['reader_font_family'] : '', 'yahei' ); ?>><?php esc_html_e( '微软雅黑', 'wp-genius' ); ?></option>
									<option value="droidsans" <?php selected( isset( $settings['reader_font_family'] ) ? $settings['reader_font_family'] : '', 'droidsans' ); ?>><?php esc_html_e( '思源黑体', 'wp-genius' ); ?></option>
								</select>
							</div>
						</div>

						<!-- Default Theme -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="reader_theme">
									<?php esc_html_e( 'Default Theme', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<select id="reader_theme" name="w2p_frontend_enhancement_settings[reader_theme]">
									<option value="light" <?php selected( isset( $settings['reader_theme'] ) ? $settings['reader_theme'] : 'light', 'light' ); ?>><?php esc_html_e( '明亮模式', 'wp-genius' ); ?></option>
									<option value="sepia" <?php selected( isset( $settings['reader_theme'] ) ? $settings['reader_theme'] : '', 'sepia' ); ?>><?php esc_html_e( '护眼模式', 'wp-genius' ); ?></option>
									<option value="green" <?php selected( isset( $settings['reader_theme'] ) ? $settings['reader_theme'] : '', 'green' ); ?>><?php esc_html_e( '自然模式', 'wp-genius' ); ?></option>
									<option value="dark" <?php selected( isset( $settings['reader_theme'] ) ? $settings['reader_theme'] : '', 'dark' ); ?>><?php esc_html_e( '暗黑模式', 'wp-genius' ); ?></option>
								</select>
							</div>
						</div>

					</div>
				</div>
			</div>
			<!-- End Tab: Reader Mode -->
			
			<!-- ==================== Tab: Code Highlight ==================== -->
			<div class="w2p-sub-tab-content" id="w2p-tab-code-highlight">
				<div class="w2p-section">
					<div class="w2p-section-header">
						<h4><?php esc_html_e( 'Code Highlighting', 'wp-genius' ); ?></h4>
					</div>
					<div class="w2p-section-body">
						
						<!-- Enable Code Highlighting -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_enabled">
									<?php esc_html_e( 'Enable Code Highlighting', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<label class="w2p-switch">
									<input type="checkbox" id="code_highlight_enabled" 
										   name="w2p_frontend_enhancement_settings[code_highlight_enabled]" 
										   value="1" <?php checked( isset( $settings['code_highlight_enabled'] ) ? $settings['code_highlight_enabled'] : 0, 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Enable syntax highlighting for code blocks.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>
						
						<!-- Theme Selection -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_theme">
									<?php esc_html_e( 'Highlighting Theme', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<select id="code_highlight_theme" 
										name="w2p_frontend_enhancement_settings[code_highlight_theme]">
									<option value="default" <?php selected( isset( $settings['code_highlight_theme'] ) ? $settings['code_highlight_theme'] : 'default', 'default' ); ?>><?php esc_html_e( 'Default', 'wp-genius' ); ?></option>
									<option value="coy" <?php selected( isset( $settings['code_highlight_theme'] ) ? $settings['code_highlight_theme'] : '', 'coy' ); ?>><?php esc_html_e( 'Coy', 'wp-genius' ); ?></option>
									<option value="dark" <?php selected( isset( $settings['code_highlight_theme'] ) ? $settings['code_highlight_theme'] : '', 'dark' ); ?>><?php esc_html_e( 'Dark', 'wp-genius' ); ?></option>
									<option value="funky" <?php selected( isset( $settings['code_highlight_theme'] ) ? $settings['code_highlight_theme'] : '', 'funky' ); ?>><?php esc_html_e( 'Funky', 'wp-genius' ); ?></option>
									<option value="okaidia" <?php selected( isset( $settings['code_highlight_theme'] ) ? $settings['code_highlight_theme'] : '', 'okaidia' ); ?>><?php esc_html_e( 'Okaidia', 'wp-genius' ); ?></option>
									<option value="solarizedlight" <?php selected( isset( $settings['code_highlight_theme'] ) ? $settings['code_highlight_theme'] : '', 'solarizedlight' ); ?>><?php esc_html_e( 'Solarized Light', 'wp-genius' ); ?></option>
									<option value="tomorrow" <?php selected( isset( $settings['code_highlight_theme'] ) ? $settings['code_highlight_theme'] : '', 'tomorrow' ); ?>><?php esc_html_e( 'Tomorrow', 'wp-genius' ); ?></option>
									<option value="twilight" <?php selected( isset( $settings['code_highlight_theme'] ) ? $settings['code_highlight_theme'] : '', 'twilight' ); ?>><?php esc_html_e( 'Twilight', 'wp-genius' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the theme for code highlighting.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>
						
						<!-- Font Family -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_font_family">
									<?php esc_html_e( 'Font Family', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<select id="code_highlight_font_family" 
										name="w2p_frontend_enhancement_settings[code_highlight_font_family]">
									<option value="monospace" <?php selected( isset( $settings['code_highlight_font_family'] ) ? $settings['code_highlight_font_family'] : 'monospace', 'monospace' ); ?>><?php esc_html_e( 'Monospace', 'wp-genius' ); ?></option>
									<option value="consolas" <?php selected( isset( $settings['code_highlight_font_family'] ) ? $settings['code_highlight_font_family'] : '', 'consolas' ); ?>><?php esc_html_e( 'Consolas', 'wp-genius' ); ?></option>
									<option value="courier" <?php selected( isset( $settings['code_highlight_font_family'] ) ? $settings['code_highlight_font_family'] : '', 'courier' ); ?>><?php esc_html_e( 'Courier', 'wp-genius' ); ?></option>
									<option value="fira-code" <?php selected( isset( $settings['code_highlight_font_family'] ) ? $settings['code_highlight_font_family'] : '', 'fira-code' ); ?>><?php esc_html_e( 'Fira Code', 'wp-genius' ); ?></option>
									<option value="source-code-pro" <?php selected( isset( $settings['code_highlight_font_family'] ) ? $settings['code_highlight_font_family'] : '', 'source-code-pro' ); ?>><?php esc_html_e( 'Source Code Pro', 'wp-genius' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the font family for code blocks.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>
						
						<!-- Enable Line Numbers -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_line_numbers">
									<?php esc_html_e( 'Show Line Numbers', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<label class="w2p-switch">
									<input type="checkbox" id="code_highlight_line_numbers" 
										   name="w2p_frontend_enhancement_settings[code_highlight_line_numbers]" 
										   value="1" <?php checked( isset( $settings['code_highlight_line_numbers'] ) ? $settings['code_highlight_line_numbers'] : 0, 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Display line numbers for code blocks.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>
						
						<!-- Enable Show Language -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_show_language">
									<?php esc_html_e( 'Show Language Label', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<label class="w2p-switch">
									<input type="checkbox" id="code_highlight_show_language" 
										   name="w2p_frontend_enhancement_settings[code_highlight_show_language]" 
										   value="1" <?php checked( isset( $settings['code_highlight_show_language'] ) ? $settings['code_highlight_show_language'] : 0, 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Display the language name on code blocks.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>
						
						<!-- Enable Copy to Clipboard -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_copy_clipboard">
									<?php esc_html_e( 'Enable Copy to Clipboard', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<label class="w2p-switch">
									<input type="checkbox" id="code_highlight_copy_clipboard" 
										   name="w2p_frontend_enhancement_settings[code_highlight_copy_clipboard]" 
										   value="1" <?php checked( isset( $settings['code_highlight_copy_clipboard'] ) ? $settings['code_highlight_copy_clipboard'] : 0, 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Add a copy button to code blocks for easy copying.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>
						
						<!-- Enable Line Highlighting -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_line_highlight">
									<?php esc_html_e( 'Enable Line Highlighting', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<label class="w2p-switch">
									<input type="checkbox" id="code_highlight_line_highlight" 
										   name="w2p_frontend_enhancement_settings[code_highlight_line_highlight]" 
										   value="1" <?php checked( isset( $settings['code_highlight_line_highlight'] ) ? $settings['code_highlight_line_highlight'] : 0, 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Enable highlighting of specific lines in code blocks.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>
						
						<!-- Enable Command Line Interface -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_command_line">
									<?php esc_html_e( 'Enable Command Line Style', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<label class="w2p-switch">
									<input type="checkbox" id="code_highlight_command_line" 
										   name="w2p_frontend_enhancement_settings[code_highlight_command_line]" 
										   value="1" <?php checked( isset( $settings['code_highlight_command_line'] ) ? $settings['code_highlight_command_line'] : 0, 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Add command line interface styling to code blocks.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>
						
						<!-- Apply to Singular Only -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_singular_only">
									<?php esc_html_e( 'Apply to Singular Pages Only', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<label class="w2p-switch">
									<input type="checkbox" id="code_highlight_singular_only" 
										   name="w2p_frontend_enhancement_settings[code_highlight_singular_only]" 
										   value="1" <?php checked( isset( $settings['code_highlight_singular_only'] ) ? $settings['code_highlight_singular_only'] : 0, 1 ); ?> />
									<span class="w2p-slider"></span>
								</label>
								<p class="description">
									<?php esc_html_e( 'Only apply code highlighting to single posts/pages, not to archive pages.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>
						
						<!-- Custom CSS -->
						<div class="w2p-form-row">
							<div class="w2p-form-label">
								<label for="code_highlight_custom_style">
									<?php esc_html_e( 'Custom CSS', 'wp-genius' ); ?>
								</label>
							</div>
							<div class="w2p-form-control">
								<textarea id="code_highlight_custom_style" 
										  name="w2p_frontend_enhancement_settings[code_highlight_custom_style]" 
										  rows="5" 
										  class="w2p-input-large"
										  placeholder="<?php esc_attr_e( 'Enter custom CSS for code highlighting', 'wp-genius' ); ?>"><?php echo esc_textarea( isset( $settings['code_highlight_custom_style'] ) ? $settings['code_highlight_custom_style'] : '' ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Add custom CSS to customize code highlighting appearance.', 'wp-genius' ); ?>
								</p>
							</div>
						</div>

					</div>
				</div>
			</div>
			<!-- End Tab: Code Highlight -->
			
		</div>
		<!-- End Tabs -->
		
		<!-- Save Button -->
		<div class="w2p-settings-actions">
			<button type="submit" name="submit" id="w2p-frontend-enhancement-submit" class="w2p-btn w2p-btn-primary">
				<i class="fa-solid fa-floppy-disk"></i>
				<?php esc_html_e( 'Save Frontend Enhancement Settings', 'wp-genius' ); ?>
			</button>
		</div>
	</form>
</div>