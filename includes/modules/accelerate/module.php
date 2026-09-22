<?php
/**
 * WP Genius — Accelerate Module
 *
 * Admin cleanup, update control, local avatars, upload renaming, and the "Delete Posts with Images" tool.
 *
 * This file is the module facade: it handles metadata and hook wiring,
 * delegating the actual logic to the responsibility classes under includes/ (AdminCleanup/UpdateControl/LocalAvatar/UploadRename/ImageCleanup).
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Accelerate Module Class
 */
class W2P_AccelerateModule extends W2P_Abstract_Module {

	/**
	 * Admin cleanup handler.
	 *
	 * @var W2P_Accelerate_AdminCleanup|null
	 */
	private $admin_cleanup;

	/**
	 * Update control handler.
	 *
	 * @var W2P_Accelerate_UpdateControl|null
	 */
	private $update_control;

	/**
	 * Local avatar handler.
	 *
	 * @var W2P_Accelerate_LocalAvatar|null
	 */
	private $local_avatar;

	/**
	 * Upload rename handler.
	 *
	 * @var W2P_Accelerate_UploadRename|null
	 */
	private $upload_rename;

	/**
	 * Image cleanup handler.
	 *
	 * @var W2P_Accelerate_ImageCleanup|null
	 */
	private $image_cleanup;

	/**
	 * Post type switch handler.
	 *
	 * @var W2P_Accelerate_PostTypeSwitch|null
	 */
	private $post_type_switch;

	public function settings_key() {
		return 'w2p_settings';
	}
	public static function id() {
		return 'accelerate';
	}
	public static function name() {
		return __( 'Accelerate', 'wp-genius' );
	}
	public static function description() {
		return __( 'Optimize WordPress performance by cleaning up admin interface and controlling update behaviors.', 'wp-genius' );
	}
	public static function icon() {
		return 'fa-solid fa-gauge-high';
	}
	/**
	 * Initialize Module
	 *
	 * @return void
	 */
	public function init() {
		// Load delegated handler classes.
		require_once __DIR__ . '/includes/class-admin-cleanup.php';
		require_once __DIR__ . '/includes/class-update-control.php';
		require_once __DIR__ . '/includes/class-local-avatar.php';
		require_once __DIR__ . '/includes/class-upload-rename.php';
		require_once __DIR__ . '/includes/class-image-cleanup.php';
		require_once __DIR__ . '/includes/class-post-type-switch.php';

		// Wire up the responsibility classes.
		$this->admin_cleanup  = new W2P_Accelerate_AdminCleanup( $this );
		$this->update_control = new W2P_Accelerate_UpdateControl( $this );
		$this->local_avatar   = new W2P_Accelerate_LocalAvatar( $this );
		$this->upload_rename  = new W2P_Accelerate_UploadRename( $this );
		$this->image_cleanup  = new W2P_Accelerate_ImageCleanup( $this );
		$this->post_type_switch = new W2P_Accelerate_PostTypeSwitch( $this );

		// Cleanup Functionality Hooks
		add_action( 'wp_before_admin_bar_render', array( $this->admin_cleanup, 'clean_admin_bar' ) );
		add_action( 'wp_dashboard_setup', array( $this->admin_cleanup, 'clean_dashboard_widgets' ), 999 );
		add_filter( 'disable_months_dropdown', array( $this->admin_cleanup, 'should_disable_months_dropdown' ), 10, 2 );
		add_filter( 'media_library_months_with_files', array( $this->admin_cleanup, 'disable_media_months' ) );
		add_filter( 'query', array( $this->admin_cleanup, 'intercept_date_query' ) );

		// Months dropdown disable scope: when CSF instantiates the options page at init:10 it fires the
		// csf_w2p_settings_sections filter (by which point CPTs registered by us-core/ACF etc. are ready),
		// so we dynamically inject the "post type" multi-select options here, avoiding an incomplete list
		// when config is collected at init:5.
		add_filter( 'csf_w2p_settings_sections', array( $this, 'inject_months_dropdown_options' ), 10, 2 );

		// One-time migration of the legacy global switch (switcher-stored true/'1') into a post type array,
		// automatically undoing the unintended disabling of the media library's months filter.
		add_action( 'admin_init', array( $this, 'migrate_months_dropdown_setting' ), 5 );

		// Update Behavior Hooks
		$this->update_control->apply_update_behavior();

		// Local Avatar Management Hooks
		$this->local_avatar->init_local_avatar();

		// Upload Rename Hooks
		$this->upload_rename->init_upload_rename();

		// Delete with Images Hooks
		$this->image_cleanup->init_cleanup_images();

		// Change Post Type Hooks (only registered when the feature is enabled).
		$this->post_type_switch->init_change_post_type();

		// Admin Body Classes for conditional styles
		add_filter( 'admin_body_class', array( $this->admin_cleanup, 'add_body_classes' ) );
	}
	// ---------------------------------------------------------------------
	// Compatibility delegation: keeps the public API signatures, forwards logic to the responsibility classes.
	// ---------------------------------------------------------------------

	/**
	 * Clean admin bar.
	 *
	 * @return void
	 */
	public function clean_admin_bar() {
		$this->admin_cleanup->clean_admin_bar();
	}

	/**
	 * Clean dashboard widgets.
	 *
	 * @return void
	 */
	public function clean_dashboard_widgets() {
		$this->admin_cleanup->clean_dashboard_widgets();
	}

	/**
	 * Should disable months dropdown.
	 *
	 * @param bool   $disable   Current value.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function should_disable_months_dropdown( $disable, $post_type ) {
		return $this->admin_cleanup->should_disable_months_dropdown( $disable, $post_type );
	}

	/**
	 * Disable media months.
	 *
	 * @param array $months Months.
	 * @return array
	 */
	public function disable_media_months( $months ) {
		return $this->admin_cleanup->disable_media_months( $months );
	}

	/**
	 * Intercept date query.
	 *
	 * @param string $query Query.
	 * @return string
	 */
	public function intercept_date_query( $query ) {
		return $this->admin_cleanup->intercept_date_query( $query );
	}

	// ---------------------------------------------------------------------
	// Months dropdown disable scope (post type multi-select)
	// ---------------------------------------------------------------------

	/**
	 * Gets the selectable "Disable Months Dropdown" post type list (slug => label).
	 *
	 * Only includes post types with an admin content list page (show_ui) that are not framework-internal:
	 * built-in types (posts / pages / media library) plus custom post types registered by the theme, ACF, etc.
	 *
	 * @return array
	 */
	public static function get_months_dropdown_post_types() {
		static $post_types = null;

		if ( null === $post_types ) {
			$post_types = array();
			$excluded   = array(
				// WP core internal types: no content list page or no practical meaning.
				'revision',
				'nav_menu_item',
				'custom_css',
				'customize_changeset',
				'oembed_cache',
				'user_request',
				'wp_block',
				'wp_navigation',
				'wp_template',
				'wp_template_part',
				'wp_global_styles',
				'wp_font_family',
				'wp_font_face',
				// ACF internal types.
				'acf-field',
				'acf-field-group',
				'acf-post-type',
				'acf-taxonomy',
				'acf-ui-options-page',
				// Impreza / us-core internal types (headers, templates, blocks, layouts).
				'us_header',
				'us_grid_layout',
				'us_content_template',
				'us_page_block',
				// WPBakery internal types (grid items).
				'vc_grid_item',
			);

			foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $slug => $post_type ) {
				if ( in_array( $slug, $excluded, true ) ) {
					continue;
				}
				/* translators: %s: post type slug. */
				$post_types[ $slug ] = $post_type->labels->name . ' (' . $slug . ')';
			}
		}

		return $post_types;
	}

	/**
	 * Gets the slug array of the selectable post types.
	 *
	 * @return array
	 */
	public static function get_months_dropdown_post_type_slugs() {
		return array_keys( self::get_months_dropdown_post_types() );
	}

	/**
	 * CSF filter: injects the post type multi-select options into the "Disable Months Dropdown" field.
	 *
	 * CSF_Options fires the csf_{unique}_sections filter when instantiated at init:10,
	 * by which point CPTs registered by us-core (init:8), ACF (init:5), etc. are all ready,
	 * so the full post type list is available (options.php collects config at init:5 when the list is incomplete).
	 *
	 * @param array  $sections Settings section configuration.
	 * @param object $csf      CSF_Options instance.
	 * @return array
	 */
	public function inject_months_dropdown_options( $sections, $csf ) {
		if ( ! is_array( $sections ) || empty( $sections ) ) {
			return $sections;
		}

		$options = self::get_months_dropdown_post_types();

		$dynamic_fields = array(
			'accelerate_disable_months_dropdown',
			'accelerate_delete_with_images_post_types',
		);

		foreach ( $sections as $section_index => $section ) {
			if ( empty( $section['module_id'] ) || 'accelerate' !== $section['module_id'] ) {
				continue;
			}
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}
			foreach ( $section['fields'] as $field_index => $field ) {
				if ( ! empty( $field['id'] ) && in_array( $field['id'], $dynamic_fields, true ) ) {
					$sections[ $section_index ]['fields'][ $field_index ]['options'] = $options;
				}
			}
		}

		return $sections;
	}

	/**
	 * One-time migration of the legacy global switch stored value.
	 *
	 * The legacy switcher stored accelerate_disable_months_dropdown as true/'1' (applied globally,
	 * including the media library). It is migrated to an array of "all selectable post types except the
	 * media library", automatically undoing the unintended disabling of the media library's months filter;
	 * users can then check options as needed on the settings page and save.
	 *
	 * @return void
	 */
	public function migrate_months_dropdown_setting() {
		$settings = get_option( 'w2p_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings['accelerate_disable_months_dropdown'] ) ) {
			return;
		}

		$raw = $settings['accelerate_disable_months_dropdown'];
		if ( is_array( $raw ) ) {
			return; // Already the new format (array of post type slugs).
		}

		$settings['accelerate_disable_months_dropdown'] = array_values(
			array_diff( self::get_months_dropdown_post_type_slugs(), array( 'attachment' ) )
		);
		update_option( 'w2p_settings', $settings );
	}

	/**
	 * Apply update behavior.
	 *
	 * @return void
	 */
	public function apply_update_behavior() {
		$this->update_control->apply_update_behavior();
	}

	/**
	 * Save avatar.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function save_avatar( $user_id ) {
		$this->local_avatar->save_avatar( $user_id );
	}

	/**
	 * Get local avatar.
	 *
	 * @param mixed        $avatar      Avatar.
	 * @param int|string   $id_or_email User ID or email.
	 * @param int          $size        Size.
	 * @param string       $default     Default.
	 * @param string       $alt         Alt text.
	 * @return mixed
	 */
	public function get_local_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
		return $this->local_avatar->get_local_avatar( $avatar, $id_or_email, $size, $default, $alt );
	}

	/**
	 * Handle upload prefilter.
	 *
	 * @param array $file File data.
	 * @return array
	 */
	public function handle_upload_prefilter( $file ) {
		return $this->upload_rename->handle_upload_prefilter( $file );
	}

	/**
	 * Maybe replace attachment title.
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Post array.
	 * @return array
	 */
	public function maybe_replace_attachment_title( $data, $postarr ) {
		return $this->upload_rename->maybe_replace_attachment_title( $data, $postarr );
	}

	/**
	 * Cleanup images — handle bulk actions.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction    Action.
	 * @param array  $post_ids    Post IDs.
	 * @return string
	 */
	public function cleanup_images_handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		return $this->image_cleanup->cleanup_images_handle_bulk_actions( $redirect_to, $doaction, $post_ids );
	}

	/**
	 * Cleanup images — handle delete action.
	 *
	 * @return void
	 */
	public function cleanup_images_handle_delete_action() {
		$this->image_cleanup->cleanup_images_handle_delete_action();
	}
}

// Legacy alias for backward compatibility (pre-2.0.0 class name).
if ( ! class_exists( 'AccelerateModule', false ) ) {
	class_alias( 'W2P_AccelerateModule', 'AccelerateModule' );
}
