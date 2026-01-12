<?php
/**
 * Image Watermark Core Methods
 *
 * @package WP_Genius
 * @subpackage Modules\Watermark
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Image Watermark class.
 *
 * @class W2P_Image_Watermark
 * @version	2.0.3
 */
final class W2P_Image_Watermark {

	private static $instance;
	private $extension = false;
	private $upload_handler;
	private $watermark_controller;
	private $allowed_mime_types = [
		'image/webp',
		'image/jpeg',
		'image/pjpeg',
		'image/png'
	];
	private $allowed_fonts = [
		'Caveat-Regular.ttf'            => 'Caveat',
		'Dosis-Regular.ttf'             => 'Dosis',
		'Lato-Regular.ttf'              => 'Lato',
		'LibreBaskerville-Regular.ttf'  => 'Libre Baskerville',
		'Merriweather-Regular.ttf'      => 'Merriweather',
		'OpenSans-Regular.ttf'          => 'Open Sans',
		'Roboto-Regular.ttf'            => 'Roboto',
		'Ubuntu-Regular.ttf'            => 'Ubuntu',
	];
	private $is_watermarked_metakey = 'iw-is-watermarked';
	public $is_backup_folder_writable = null;
	public $extensions;
	public $defaults = [
		'options'	 => [
			'watermark_on'		 => [],
			'watermark_cpt_on'	 => [ 'everywhere' ],
			'watermark_image'	 => [
				'extension'				 => '',
				'url'					 => 0,
				'type'				 => 'image',
				'text_string'		 => '',
				'text_font'			 => 'Lato-Regular.ttf',
				'text_color'			 => '#000000',
				'text_size'				 => 24,
				'width'					 => 80,
				'plugin_off'			 => 0,
				'frontend_active'		 => false,
				'manual_watermarking'	 => 0,
				'position'				 => 'bottom_right',
				'watermark_size_type'	 => 2,
				'offset_unit'			 => 'pixels',
				'offset_width'			 => 0,
				'offset_height'			 => 0,
				'absolute_width'		 => 0,
				'absolute_height'		 => 0,
				'transparent'			 => 50,
				'quality'				 => 90,
				'jpeg_format'			 => 'baseline',
				'deactivation_delete'	 => false,
				'review_notice'			 => true,
				'review_delay_date'		 => 0
			],
			'image_protection'	 => [
				'rightclick'		 => 1,
				'draganddrop'		 => 1,
				'devtools'			 => 1,
				'forlogged'			 => 1,
				'enable_toast'		 => 1,
				'toast_message'		 => 'This content is protected'
			],
			'backup'			 => [
				'backup_image'	 => true
			]
		],
		'version'	 => '2.0.3'
	];
	public $options = [];

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// define plugin constants
		$this->define_constants();

		// settings
		$options = get_option( 'w2p_image_watermark_options', $this->defaults['options'] );

		// Guard against corrupted/non-array option values
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$watermark_image = ( isset( $options['watermark_image'] ) && is_array( $options['watermark_image'] ) ) ? $options['watermark_image'] : [];
		$image_protection = ( isset( $options['image_protection'] ) && is_array( $options['image_protection'] ) ) ? $options['image_protection'] : [];
		$backup = ( isset( $options['backup'] ) && is_array( $options['backup'] ) ) ? $options['backup'] : [];

		$this->options = array_merge( $this->defaults['options'], $options );
		$this->options['watermark_image'] = array_merge( $this->defaults['options']['watermark_image'], $watermark_image );
		$this->options['image_protection'] = array_merge( $this->defaults['options']['image_protection'], $image_protection );
		$this->options['backup'] = array_merge( $this->defaults['options']['backup'], $backup );

		include_once( W2P_IMAGE_WATERMARK_PATH . 'includes/class-settings-api.php' );
		include_once( W2P_IMAGE_WATERMARK_PATH . 'includes/class-settings.php' );
		include_once( W2P_IMAGE_WATERMARK_PATH . 'includes/class-upload-handler.php' );
		include_once( W2P_IMAGE_WATERMARK_PATH . 'includes/class-actions-controller.php' );

		// Initialize settings only if needed (e.g. for API/validation), 
		// but since we render settings via module.php or the class's own method, we might instantiate it differently.
		// However, keeping it here ensures hooks are registered.
		new W2P_Image_Watermark_Settings( $this );

		$this->upload_handler = new W2P_Image_Watermark_Upload_Handler( $this );
		$this->watermark_controller = new W2P_Image_Watermark_Actions_Controller( $this, $this->upload_handler );

		// actions
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_enqueue_media', [ $this, 'wp_enqueue_media' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );
		add_action( 'load-upload.php', [ $this, 'watermark_bulk_action' ] );
		add_action( 'admin_init', [ $this, 'check_extensions' ] );
		add_action( 'delete_attachment', [ $this->upload_handler, 'delete_attachment' ] );
		add_action( 'wp_ajax_iw_watermark_bulk_action', [ $this->watermark_controller, 'watermark_action_ajax' ] );
		add_action( 'wp_ajax_iw_text_preview', [ $this, 'text_preview_ajax' ] );
		add_action( 'attachment_submitbox_misc_actions', [ $this, 'render_attachment_editor_actions' ], 20 );

		// filters
		add_filter( 'wp_handle_upload', [ $this, 'handle_upload_files' ] );
		add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_fields_to_edit' ], 10, 2 );

		// define our backup location
		$upload_dir = wp_upload_dir();

		define( 'W2P_IMAGE_WATERMARK_BACKUP_DIR', apply_filters( 'w2p_image_watermark_backup_dir', $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'iw-backup' ) );

		// create backup folder and security if enabled
		if ( $this->options['backup']['backup_image'] ) {
			if ( is_writable( $upload_dir['basedir'] ) ) {
				$this->is_backup_folder_writable = true;

				// create backup folder
				$backup_folder_created = wp_mkdir_p( W2P_IMAGE_WATERMARK_BACKUP_DIR );

				// check if the folder exists and is writable
				if ( $backup_folder_created && is_writable( W2P_IMAGE_WATERMARK_BACKUP_DIR ) ) {
					// check if the htaccess file exists
					if ( ! file_exists( W2P_IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . '.htaccess' ) ) {
						// htaccess security
						file_put_contents( W2P_IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all' );
					}
				} else
					$this->is_backup_folder_writable = false;
			} else
				$this->is_backup_folder_writable = false;

			if ( $this->is_backup_folder_writable !== true ) {
				// disable backup setting
				$this->options['backup']['backup_image'] = false;

				update_option( 'w2p_image_watermark_options', $this->options );
			}

			// Removed admin notice for folder writable to reduce noise, or keep it if critical.
			// add_action( 'admin_notices', [ $this, 'folder_writable_admin_notice' ] );
		}
	}

	/**
	 * Disable object cloning.
	 *
	 * @return void
	 */
	public function __clone() {}

	/**
	 * Disable unserializing of the class.
	 *
	 * @return void
	 */
	public function __wakeup() {}

	/**
	 * Main plugin instance, insures that only one instance of the plugin exists in memory at one time.
	 *
	 * @return object
	 */
	public static function instance() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Setup plugin constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		define( 'W2P_IMAGE_WATERMARK_URL', plugins_url( '', __FILE__ ) );
		define( 'W2P_IMAGE_WATERMARK_PATH', plugin_dir_path( __FILE__ ) );
		define( 'W2P_IMAGE_WATERMARK_BASENAME', plugin_basename( __FILE__ ) );
		define( 'W2P_IMAGE_WATERMARK_REL_PATH', dirname( W2P_IMAGE_WATERMARK_BASENAME ) );
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public function activate_watermark() {}
	public function deactivate_watermark() {}
	public function update_plugin() {}
	public function load_textdomain() {}

	public function wp_enqueue_media( $page ) {
		global $pagenow;
		if ( $pagenow !== 'options-general.php' || ! isset( $_GET['page'] ) || $_GET['page'] !== 'wp-genius' ) {
			wp_enqueue_style( 'watermark-admin', W2P_IMAGE_WATERMARK_URL . '/css/admin.css', [], $this->defaults['version'] );
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @global $pagenow
	 * @return void
	 */
	public function admin_enqueue_scripts( $page ) {
		global $pagenow;

		wp_register_style( 'watermark-admin-settings', W2P_IMAGE_WATERMARK_URL . '/css/admin-settings.css', [], $this->defaults['version'] );
		wp_register_style( 'watermark-admin', W2P_IMAGE_WATERMARK_URL . '/css/admin.css', [], $this->defaults['version'] );

		$media_script_data = null;
		if ( $this->options['watermark_image']['manual_watermarking'] == 1 && current_user_can( 'upload_files' ) ) {
			$media_script_data = [
				'backupImage'		=> (bool) $this->options['backup']['backup_image'],
				'applyWatermark'	=> __( 'Apply watermark', 'wp-genius' ),
				'removeWatermark'	=> __( 'Remove watermark', 'wp-genius' )
			];
		}

		if ( $page === 'settings_page_image-watermark' || ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'genius' ) !== false ) ) {
			wp_enqueue_media();

			wp_enqueue_script( 'image-watermark-upload-manager', W2P_IMAGE_WATERMARK_URL . '/js/admin-upload.js', [], $this->defaults['version'] );

			// prepare script data
			$script_data = [
				'title'			=> __( 'Select watermark image', 'wp-genius' ),
				'originalSize'	=> __( 'Original size', 'wp-genius' ),
				'noSelectedImg'	=> __( 'No watermark image has been selected yet.', 'wp-genius' ),
				'notAllowedImg'	=> __( 'This image cannot be used as a watermark. Use a JPEG, PNG, WebP, or GIF image.', 'wp-genius' ),
				'px'			=> __( 'px', 'wp-genius' ),
				'frame'			=> 'select',
				'button'		=> [ 'text' => __( 'Add watermark image', 'wp-genius' ) ],
				'multiple'		=> false
			];

			wp_add_inline_script( 'image-watermark-upload-manager', 'var iwArgsUpload = ' . wp_json_encode( $script_data ) . ";\n", 'before' );

			wp_enqueue_script( 'image-watermark-admin-settings', W2P_IMAGE_WATERMARK_URL . '/js/admin-settings.js', [], $this->defaults['version'] );

			// prepare script data
			$script_data = [
				'resetToDefaults' => __( 'Are you sure you want to reset all settings to their default values?', 'wp-genius' ),
				'generatePreview' => __( 'Generate Preview', 'wp-genius' ),
				'generatingPreview' => __( 'Generating...', 'wp-genius' ),
				'previewNonce' => wp_create_nonce( 'iw_text_preview' ),
				'originImageLabel' => __( 'Original watermark image:', 'wp-genius' ),
				'originImageMissing' => __( 'No watermark image selected.', 'wp-genius' ),
				'originImageLoading' => __( 'Loading…', 'wp-genius' ),
				'originTextLabel' => __( 'Original text size:', 'wp-genius' ),
				'originTextEmpty' => __( 'Enter text to preview.', 'wp-genius' ),
			];

			wp_add_inline_script( 'image-watermark-admin-settings', 'var iwArgsSettings = ' . wp_json_encode( $script_data ) . ";\n", 'before' );

			wp_enqueue_style( 'watermark-admin-settings' );

			wp_enqueue_script( 'postbox' );
		}

		if ( $pagenow === 'upload.php' ) {
			if ( $media_script_data ) {
				wp_enqueue_script( 'image-watermark-admin-media', W2P_IMAGE_WATERMARK_URL . '/js/admin-media.js', [], $this->defaults['version'], false );

				wp_add_inline_script( 'image-watermark-admin-media', 'var iwArgsMedia = ' . wp_json_encode( $media_script_data ) . ";\n", 'before' );
			}

			wp_enqueue_style( 'watermark-admin' );
		}

		// image modal could be loaded in various places
		if ( $this->options['watermark_image']['manual_watermarking'] == 1 ) {
			wp_enqueue_script( 'image-watermark-admin-image-actions', W2P_IMAGE_WATERMARK_URL . '/js/admin-image-actions.js', [], $this->defaults['version'], true );

			if ( $media_script_data ) {
				wp_add_inline_script( 'image-watermark-admin-image-actions', 'var iwArgsMedia = ' . wp_json_encode( $media_script_data ) . ";\n", 'before' );
			}

			// prepare script data
			$script_data = [
				'backup_image'		=> (bool) $this->options['backup']['backup_image'],
				'_nonce'			=> wp_create_nonce( 'wp-genius' ),
				'allowed_mimes'		=> $this->get_allowed_mime_types(),
				'apply_label'		=> __( 'Apply watermark', 'wp-genius' ),
				'remove_label'		=> __( 'Remove watermark', 'wp-genius' ),
				'setting_label'		=> __( 'Watermark', 'wp-genius' ),
				'single_running'	=> __( 'Working…', 'wp-genius' ),
				'single_applied'	=> __( 'Watermark applied.', 'wp-genius' ),
				'single_removed'	=> __( 'Watermark removed.', 'wp-genius' ),
				'single_error'		=> __( 'Action failed.', 'wp-genius' ),
				'__applied_none'	=> __( 'The watermark could not be applied to the selected files because no valid images (JPEG, PNG, WebP) were selected.', 'wp-genius' ),
				'__applied_one'		=> __( 'Watermark was successfully applied to 1 image.', 'wp-genius' ),
				'__applied_multi'	=> __( 'Watermark was successfully applied to %s images.', 'wp-genius' ),
				'__removed_none'	=> __( 'The watermark could not be removed from the selected files because no valid images (JPEG, PNG, WebP) were selected.', 'wp-genius' ),
				'__removed_one'		=> __( 'Watermark was successfully removed from 1 image.', 'wp-genius' ),
				'__removed_multi'	=> __( 'Watermark was successfully removed from %s images.', 'wp-genius' ),
				'__skipped'			=> __( 'Skipped images', 'wp-genius' ),
				'__running'			=> __( 'A bulk action is currently running. Please wait…', 'wp-genius' ),
				'__dismiss'			=> __( 'Dismiss this notice.' ) // WordPress default string
			];

			wp_add_inline_script( 'image-watermark-admin-image-actions', 'var iwArgsImageActions = ' . wp_json_encode( $script_data ) . ";\n", 'before' );
		}

		if ( $pagenow === 'post.php' && $this->options['watermark_image']['manual_watermarking'] == 1 && current_user_can( 'upload_files' ) ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $screen && $screen->post_type === 'attachment' && $post_id ) {
				$mime = get_post_mime_type( $post_id );

				if ( in_array( $mime, $this->get_allowed_mime_types(), true ) ) {
					wp_enqueue_script( 'image-watermark-admin-classic', W2P_IMAGE_WATERMARK_URL . '/js/admin-classic-editor.js', [], $this->defaults['version'], true );

					$script_data = [
						'postId'       => $post_id,
						'attachmentId' => $post_id,
						'backupImage'  => (bool) $this->options['backup']['backup_image'],
						'nonce'        => wp_create_nonce( 'wp-genius' ),
						'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
						'strings'      => [
							'apply'   => __( 'Apply watermark', 'wp-genius' ),
							'remove'  => __( 'Remove watermark', 'wp-genius' ),
							'applied' => __( 'Watermark applied.', 'wp-genius' ),
							'removed' => __( 'Watermark removed.', 'wp-genius' ),
							'error'   => __( 'Action failed.', 'wp-genius' ),
							'running' => __( 'Working…', 'wp-genius' ),
						],
					];

					wp_add_inline_script( 'image-watermark-admin-classic', 'var iwArgsClassic = ' . wp_json_encode( $script_data ) . ";\n", 'before' );
				}
			}
		}
	}

	/**
	 * Enqueue frontend script with 'no right click' and 'drag and drop' functions.
	 *
	 * @return void
	 */
	public function wp_enqueue_scripts() {
		$right_click = true;

		if ( ( $this->options['image_protection']['forlogged'] == 0 && is_user_logged_in() ) || ( $this->options['image_protection']['draganddrop'] == 0 && $this->options['image_protection']['rightclick'] == 0 && $this->options['image_protection']['devtools'] == 0 ) )
			$right_click = false;

		if ( apply_filters( 'iw_block_right_click', (bool) $right_click ) === true ) {
			wp_enqueue_script( 'image-watermark-no-right-click', W2P_IMAGE_WATERMARK_URL . '/js/no-right-click.js', [], $this->defaults['version'] );

			// prepare script data
			$script_data = [
				'rightclick'		=> ( $this->options['image_protection']['rightclick'] == 1 ? 'Y' : 'N' ),
				'draganddrop'		=> ( $this->options['image_protection']['draganddrop'] == 1 ? 'Y' : 'N' ),
				'devtools'			=> ( $this->options['image_protection']['devtools'] == 1 ? 'Y' : 'N' ),
				'enableToast'		=> ( $this->options['image_protection']['enable_toast'] == 1 ? 'Y' : 'N' ),
				'toastMessage'		=> ! empty( $this->options['image_protection']['toast_message'] ) ? esc_js( $this->options['image_protection']['toast_message'] ) : __( 'This content is protected', 'wp-genius' )
			];

			wp_add_inline_script( 'image-watermark-no-right-click', 'var iwArgsNoRightClick = ' . wp_json_encode( $script_data ) . ";\n", 'before' );
		}
	}

	/**
	 * Check which extension is available and set it.
	 *
	 * @return void
	 */
	public function check_extensions() {
		$ext = null;

		if ( $this->check_imagick() ) {
			$this->extensions['imagick'] = 'ImageMagick';
			$ext = 'imagick';
		}

		if ( $this->check_gd() ) {
			$this->extensions['gd'] = 'GD Library';

			if ( is_null( $ext ) )
				$ext = 'gd';
		}

		if ( isset( $this->options['watermark_image']['extension'] ) ) {
			if ( $this->options['watermark_image']['extension'] === 'imagick' && isset( $this->extensions['imagick'] ) )
				$this->extension = 'imagick';
			elseif ( $this->options['watermark_image']['extension'] === 'gd' && isset( $this->extensions['gd'] ) )
				$this->extension = 'gd';
			else
				$this->extension = $ext;
		} else
			$this->extension = $ext;
	}

	/**
	 * Check and display review notice.
	 *
	 * @return void
	 */
	public function check_review_notice() {}
	public function display_review_notice() {}
	public function review_notice_inline_js() {}
	public function dismiss_review_notice() {}

	/**
	 * Redirect old slug to new slug for backward compatibility.
	 *
	 * @return void
	 */
	public function redirect_old_slug() {}

	/**
	 * Apply watermark everywhere or for specific post types.
	 *
	 * @param resource $file
	 * @return resource
	 */
	public function handle_upload_files( $file ) {
		return $this->get_upload_handler()->handle_upload_files( $file );
	}

	/**
	 * Handle manual watermark AJAX requests.
	 *
	 * @return void
	 */
	public function text_preview_ajax() {
		check_ajax_referer( 'iw_text_preview', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'wp-genius' ) );
		}

		// Choose engine for preview (prefer active one).
		$engine = $this->get_extension();

		if ( ! $engine ) {
			$this->check_extensions();
			$engine = $this->get_extension();
		}

		if ( ! $engine ) {
			wp_send_json_error( __( 'No image library available to generate a preview.', 'wp-genius' ) );
		}

		if ( $engine === 'imagick' && ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick', false ) ) ) {
			wp_send_json_error( __( 'Imagick is selected but not available on the server.', 'wp-genius' ) );
		}

		if ( $engine === 'gd' && ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagejpeg' ) ) ) {
			wp_send_json_error( __( 'GD with JPEG support is required to generate a preview.', 'wp-genius' ) );
		}

		// Get current options or posted options
		$options = $this->options;

		$allowed_fonts = $this->get_allowed_fonts();

		// Override with posted values for preview
		if ( isset( $_POST['text_string'] ) ) {
			$options['watermark_image']['text_string'] = sanitize_text_field( $_POST['text_string'] );
		}
		if ( isset( $_POST['text_font'] ) && array_key_exists( $_POST['text_font'], $allowed_fonts ) ) {
			$options['watermark_image']['text_font'] = $_POST['text_font'];
		}
		if ( isset( $_POST['text_color'] ) && preg_match( '/^#[a-f0-9]{6}$/i', $_POST['text_color'] ) ) {
			$options['watermark_image']['text_color'] = $_POST['text_color'];
		}
		if ( isset( $_POST['text_size'] ) ) {
			$options['watermark_image']['text_size'] = max( 6, min( 400, (int) $_POST['text_size'] ) );
		}
		if ( isset( $_POST['position'] ) ) {
			$options['watermark_image']['position'] = $_POST['position'];
		}
		if ( isset( $_POST['transparent'] ) ) {
			$options['watermark_image']['transparent'] = max( 0, min( 100, (int) $_POST['transparent'] ) );
		}

		// Create a sample image
		$sample_image_path = $this->create_sample_image( $engine );

		if ( ! $sample_image_path ) {
			wp_send_json_error( __( 'Failed to create sample image.', 'wp-genius' ) );
		}

		// Apply text watermark
		$this->upload_handler->do_watermark( 0, $sample_image_path, 'full', wp_upload_dir(), [] );

		// Get image data
		$image_data = file_get_contents( $sample_image_path );
		$base64 = base64_encode( $image_data );

		// Clean up
		unlink( $sample_image_path );

		wp_send_json_success( [
			'image' => 'data:image/jpeg;base64,' . $base64,
		] );
	}

	/**
	 * Create a sample image for preview using the active engine.
	 *
	 * @param string $engine Active engine key ('imagick' or 'gd').
	 * @return string|false Path to sample image or false on failure.
	 */
	private function create_sample_image( $engine ) {
		$upload_dir = wp_upload_dir();
		$base_dir = trailingslashit( $upload_dir['basedir'] );
		$sample_path = $base_dir . 'iw-sample-preview-' . uniqid( '', true ) . '.jpg';

		if ( $engine === 'imagick' ) {
			return $this->create_sample_image_imagick( $sample_path );
		}

		return $this->create_sample_image_gd( $sample_path );
	}

	/**
	 * Create sample image via GD.
	 *
	 * @param string $sample_path
	 * @return string|false
	 */
	private function create_sample_image_gd( $sample_path ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagejpeg' ) ) {
			return false;
		}

		$image = imagecreatetruecolor( 400, 300 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		imagefill( $image, 0, 0, $white );

		$gray = imagecolorallocate( $image, 200, 200, 200 );
		imagerectangle( $image, 50, 50, 350, 250, $gray );
		imagestring( $image, 5, 150, 120, 'Sample Image', $gray );

		imagejpeg( $image, $sample_path, 90 );
		imagedestroy( $image );

		return ( is_file( $sample_path ) ? $sample_path : false );
	}

	/**
	 * Create sample image via Imagick.
	 *
	 * @param string $sample_path
	 * @return string|false
	 */
	private function create_sample_image_imagick( $sample_path ) {
		if ( ! class_exists( 'Imagick', false ) || ! class_exists( 'ImagickPixel', false ) || ! class_exists( 'ImagickDraw', false ) ) {
			return false;
		}

		try {
			$image = new Imagick();
			$image->newImage( 400, 300, new ImagickPixel( 'white' ) );
			$image->setImageFormat( 'jpeg' );
			$image->setImageCompressionQuality( 90 );

			$draw = new ImagickDraw();
			$draw->setStrokeColor( new ImagickPixel( '#c8c8c8' ) );
			$draw->setFillColor( 'none' );
			$draw->setStrokeWidth( 1 );
			$draw->rectangle( 50, 50, 350, 250 );

			$draw_text = new ImagickDraw();
			$draw_text->setFillColor( new ImagickPixel( '#c8c8c8' ) );
			$draw_text->setFontSize( 14 );

			$image->annotateImage( $draw_text, 150, 170, 0, 'Sample Image' );
			$image->drawImage( $draw );
			$image->writeImage( $sample_path );

			$draw->clear();
			$draw->destroy();
			$draw_text->clear();
			$draw_text->destroy();
			$image->clear();
			$image->destroy();
		} catch ( Exception $e ) {
			return false;
		}

		return ( is_file( $sample_path ) ? $sample_path : false );
	}

	/**
	 * Handle media library bulk watermark actions.
	 *
	 * @return void
	 */
	public function watermark_bulk_action() {
		$this->get_watermark_controller()->watermark_bulk_action();
	}

	/**
	 * Add watermark buttons on attachment image locations.
	 *
	 * @param array $form_fields
	 * @param object $post
	 * @return array
	 */
	public function attachment_fields_to_edit( $form_fields, $post ) {
		if ( $this->options['watermark_image']['manual_watermarking'] == 1 && $this->options['backup']['backup_image'] ) {
			$data = wp_get_attachment_metadata( $post->ID, false );

			// is this really an image?
			if ( in_array( get_post_mime_type( $post->ID ), $this->allowed_mime_types ) && is_array( $data ) ) {
				$form_fields['image_watermark'] = [
					'show_in_edit'	=> false,
					'tr'			=> '
					<div id="image_watermark_buttons"' . ( get_post_meta( $post->ID, $this->is_watermarked_metakey, true ) ? ' class="watermarked"' : '' ) . ' data-id="' . $post->ID . '" style="display: none;">
						<label class="setting">
							<span class="name">' . __( 'Image Watermark', 'wp-genius' ) . '</span>
							<span class="value" style="width: 63%"><a href="#" class="iw-watermark-action" data-action="applywatermark" data-id="' . $post->ID . '">' . __( 'Apply watermark', 'wp-genius' ) . '</a> | <a href="#" class="iw-watermark-action delete-watermark" data-action="removewatermark" data-id="' . $post->ID . '">' . __( 'Remove watermark', 'wp-genius' ) . '</a></span>
						</label>
						<div class="clear"></div>
					</div>'
				];
			}
		}

		return $form_fields;
	}

	/**
	 * Display admin notices.
	 *
	 * @return void
	 */
	public function bulk_admin_notices() {
		global $post_type, $pagenow;

		if ( $pagenow === 'upload.php' ) {
			if ( ! current_user_can( 'upload_files' ) )
				return;


			if ( isset( $_REQUEST['watermarked'], $_REQUEST['watermarkremoved'], $_REQUEST['skipped'] ) && $post_type === 'attachment' ) {
				$watermarked = (int) $_REQUEST['watermarked'];
				$watermarkremoved = (int) $_REQUEST['watermarkremoved'];
				$skipped = (int) $_REQUEST['skipped'];
				$messages = [];

				if ( isset( $_REQUEST['messages'] ) ) {
					$raw_messages = wp_unslash( $_REQUEST['messages'] );
					$raw_messages = is_array( $raw_messages ) ? $raw_messages : [ $raw_messages ];
					$messages = array_filter( array_map( 'sanitize_text_field', $raw_messages ) );
				}

				if ( $watermarked === 0 )
					echo '<div class="error"><p>' . __( 'The watermark could not be applied to the selected files because no valid images (JPEG, PNG, WebP) were selected.', 'wp-genius' ) . ($skipped > 0 ? ' ' . __( 'Skipped images', 'wp-genius' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				elseif ( $watermarked > 0 )
					echo '<div class="updated"><p>' . sprintf( _n( 'Watermark was successfully applied to 1 image.', 'Watermark was successfully applied to %s images.', $watermarked, 'wp-genius' ), number_format_i18n( $watermarked ) ) . ($skipped > 0 ? ' ' . __( 'Skipped images', 'wp-genius' ) . ': ' . $skipped . '.' : '') . '</p></div>';

				if ( $watermarkremoved === 0 )
					echo '<div class="error"><p>' . __( 'The watermark could not be removed from the selected files because no valid images (JPEG, PNG, WebP) were selected.', 'wp-genius' ) . ($skipped > 0 ? ' ' . __( 'Skipped images', 'wp-genius' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				elseif ( $watermarkremoved > 0 )
					echo '<div class="updated"><p>' . sprintf( _n( 'Watermark was successfully removed from 1 image.', 'Watermark was successfully removed from %s images.', $watermarkremoved, 'wp-genius' ), number_format_i18n( $watermarkremoved ) ) . ($skipped > 0 ? ' ' . __( 'Skipped images', 'wp-genius' ) . ': ' . $skipped . '.' : '') . '</p></div>';

				if ( ! empty( $messages ) ) {
					echo '<div class="error"><p>' . implode( '<br />', array_map( 'esc_html', $messages ) ) . '</p></div>';
				}

				$_SERVER['REQUEST_URI'] = esc_url( remove_query_arg( [ 'watermarked', 'watermarkremoved', 'skipped', 'messages' ], $_SERVER['REQUEST_URI'] ) );
			}
		}
	}

	/**
	 * Check whether ImageMagick extension is available.
	 *
	 * @return bool
	 */
	public function check_imagick() {
		// check Imagick's extension and classes
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick', false ) || ! class_exists( 'ImagickPixel', false ) )
			return false;

		// check version
		if ( version_compare( phpversion( 'imagick' ), '2.2.0', '<' ) )
			return false;

		// check for deep requirements within Imagick
		if ( ! defined( 'imagick::COMPRESSION_JPEG' ) || ! defined( 'imagick::COMPOSITE_OVERLAY' ) || ! defined( 'Imagick::INTERLACE_PLANE' ) || ! defined( 'imagick::FILTER_CATROM' ) || ! defined( 'Imagick::CHANNEL_ALL' ) )
			return false;

		// check methods
		if ( array_diff( [ 'clear', 'destroy', 'valid', 'getimage', 'writeimage', 'getimagegeometry', 'getimageformat', 'setimageformat', 'setimagecompression', 'setimagecompressionquality', 'scaleimage' ], get_class_methods( 'Imagick' ) ) )
			return false;

		return true;
	}

	/**
	 * Check whether GD extension is available.
	 *
	 * @return bool
	 */
	public function check_gd( $args = [] ) {
		// check extension
		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'gd_info' ) )
			return false;

		// ensure required formats are supported to avoid fatal errors
		$required_functions = [ 'imagecreatefromjpeg', 'imagecreatefrompng', 'imagecreatefromwebp' ];

		foreach ( $required_functions as $func ) {
			if ( ! function_exists( $func ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get active image extension.
	 *
	 * @return string|false
	 */
	public function get_extension() {
		return $this->extension;
	}

	/**
	 * Get allowed mime types.
	 *
	 * @return array
	 */
	public function get_allowed_mime_types() {
		return $this->allowed_mime_types;
	}

	/**
	 * Get allowed fonts list.
	 *
	 * @return array
	 */
	public function get_allowed_fonts() {
		return apply_filters( 'iw_allowed_fonts', $this->allowed_fonts );
	}

	/**
	 * Get meta key for watermark flag.
	 *
	 * @return string
	 */
	public function get_watermarked_meta_key() {
		return $this->is_watermarked_metakey;
	}

	/**
	 * Get upload handler.
	 *
	 * @return W2P_Image_Watermark_Upload_Handler
	 */
	public function get_upload_handler() {
		return $this->upload_handler;
	}

	/**
	 * Get watermark controller instance.
	 *
	 * @return W2P_Image_Watermark_Actions_Controller
	 */
	public function get_watermark_controller() {
		return $this->watermark_controller;
	}

	/**
	 * Apply watermark to selected image sizes.
	 *
	 * @param array	$data
	 * @param int|string $attachment_id	Attachment ID
	 * @param string $method
	 * @return array
	 */
	public function apply_watermark( $data, $attachment_id, $method = '' ) {
		return $this->get_upload_handler()->apply_watermark( $data, $attachment_id, $method );
	}

	/**
	 * Create admin notice when we can't create the backup folder.
	 *
	 * @return void
	 */
	function folder_writable_admin_notice() {
		if ( current_user_can( 'manage_options' ) && $this->is_backup_folder_writable !== true ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php _e( 'Image Watermark', 'wp-genius' ); ?> - <?php _e( 'Image backup', 'wp-genius' ); ?>: <?php _e( "Your uploads folder is not writable, so we can't create backups of your images. This feature has been disabled for now.", 'wp-genius' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Add link to Settings page.
	 *
	 * @param array $links
	 * @return array
	 */
	public function plugin_settings_link( $links ) {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) )
			return $links;

		array_unshift( $links, sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php' ) . '?page=image-watermark', __( 'Settings', 'wp-genius' ) ) );

		return $links;
	}

	/**
	 * Add link to Support Forum.
	 *
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	public function plugin_extend_links( $links, $file ) {
		if ( ! current_user_can( 'install_plugins' ) )
			return $links;

		if ( $file === W2P_IMAGE_WATERMARK_BASENAME )
			return array_merge( $links, [ sprintf( '<a href="http://www.dfactory.co/support/forum/image-watermark/" target="_blank">%s</a>', __( 'Support', 'wp-genius' ) ) ] );

		return $links;
	}

	/**
	 * Get font file path.
	 *
	 * @param string $font Font filename.
	 * @return string|null
	 */
	public function get_font_path( $font ) {
		if ( file_exists( $font ) && is_file( $font ) ) {
			$path = $font;
		} else {
			$path = W2P_IMAGE_WATERMARK_PATH . 'fonts/' . $font;
			if ( ! file_exists( $path ) || ! is_file( $path ) ) {
				$path = null;
			}
		}
		return apply_filters( 'iw_font_path', $path, $font );
	}

	/**
	 * Attachment editor sidebar actions.
	 *
	 * @return void
	 */
	public function render_attachment_editor_actions() {
		global $post;

		if ( ! $post || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		if ( $this->options['watermark_image']['manual_watermarking'] != 1 ) {
			return;
		}

		if ( $post->post_type !== 'attachment' ) {
			return;
		}

		$mime = get_post_mime_type( $post->ID );

		if ( ! in_array( $mime, $this->get_allowed_mime_types(), true ) ) {
			return;
		}

		$remove_allowed = (bool) $this->options['backup']['backup_image'];
		?>
		<div class="misc-pub-section iw-classic-actions">
			<button type="button" class="button-link iw-classic-apply"><?php esc_html_e( 'Apply watermark', 'wp-genius' ); ?></button>
			<?php if ( $remove_allowed ) : ?>
				| <button type="button" class="button-link iw-classic-remove"><?php esc_html_e( 'Remove watermark', 'wp-genius' ); ?></button>
			<?php endif; ?>
			<div class="iw-classic-status" aria-live="polite"></div>
		</div>
		<?php
	}
}


