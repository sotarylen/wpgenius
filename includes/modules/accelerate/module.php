<?php
/**
 * Accelerate Module
 *
 * Merges functionality from Cleanup WordPress and Update Behavior modules.
 * Now also includes functionality from removed sub-modules (Cleanup Images) for a flatter structure.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AccelerateModule extends W2P_Abstract_Module {

	protected static $original_titles = array();

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

	public function init() {
		// Cleanup Functionality Hooks
		add_action( 'wp_before_admin_bar_render', array( $this, 'clean_admin_bar' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'clean_dashboard_widgets' ), 999 );

		// Date Dropdown Optimization
		add_filter( 'disable_months_dropdown', array( $this, 'should_disable_months_dropdown' ), 10, 2 );
		add_filter( 'media_library_months_with_files', array( $this, 'disable_media_months' ) );
		add_filter( 'query', array( $this, 'intercept_date_query' ) );

		// Update Behavior Hooks
		add_action( 'init', array( $this, 'apply_update_behavior' ), 1 );

		// Local Avatar Management Hooks
		$this->init_local_avatar();

		// Upload Rename Hooks
		$this->init_upload_rename();

		// Delete with Images Hooks (Merged from AccelerateCleanupImages)
		$this->init_cleanup_images();

		// Admin Body Classes for conditional styles
		add_filter( 'admin_body_class', array( $this, 'add_body_classes' ) );
	}

	/**
	 * Add admin body classes for styling hooks
	 */
	public function add_body_classes( $classes ) {
		$settings = $this->get_settings();

		if ( ! empty( $settings['accelerate_hide_plugin_notices'] ) ) {
			$classes .= ' w2p-acc-hide-notices';
		}

		if ( ! empty( $settings['accelerate_enable_local_avatar'] ) ) {
			$classes .= ' w2p-acc-local-avatar';
		}

		return $classes;
	}

	/**
	 * Clean Admin Bar
	 */
	public function clean_admin_bar() {
		global $wp_admin_bar;
		$settings = $this->get_settings();

		$items_to_remove = array(
			'accelerate_remove_admin_bar_wp_logo'        => 'wp-logo',
			'accelerate_remove_admin_bar_about'          => 'about',
			'accelerate_remove_admin_bar_comments'       => 'comments',
			'accelerate_remove_admin_bar_new_content'    => 'new-content',
			'accelerate_remove_admin_bar_search'         => 'search',
			'accelerate_remove_admin_bar_updates'        => 'updates',
			'accelerate_remove_admin_bar_appearance'     => 'appearance',
			'accelerate_remove_admin_bar_customize'      => 'customize',
			'accelerate_remove_admin_bar_wporg'          => 'wporg',
			'accelerate_remove_admin_bar_documentation'  => 'documentation',
			'accelerate_remove_admin_bar_support_forums' => 'support-forums',
			'accelerate_remove_admin_bar_feedback'       => 'feedback',
			'accelerate_remove_admin_bar_view_site'      => 'view-site',
		);

		foreach ( $items_to_remove as $setting => $menu_item ) {
			if ( ! empty( $settings[ $setting ] ) ) {
				$wp_admin_bar->remove_menu( $menu_item );
			}
		}
	}

	/**
	 * Clean Dashboard Widgets
	 */
	public function clean_dashboard_widgets() {
		global $wp_meta_boxes;
		$settings = $this->get_settings();

		$widgets_to_remove = array(
			'accelerate_remove_dashboard_primary'     => array( 'dashboard', 'side', 'core', 'dashboard_primary' ),
			'accelerate_remove_dashboard_secondary'   => array( 'dashboard', 'side', 'core', 'dashboard_secondary' ),
			'accelerate_remove_dashboard_site_health' => array( 'dashboard', 'normal', 'core', 'dashboard_site_health' ),
			'accelerate_remove_dashboard_right_now'   => array( 'dashboard', 'normal', 'core', 'dashboard_right_now' ),
			'accelerate_remove_dashboard_quick_draft' => array( 'dashboard', 'side', 'core', 'dashboard_quick_press' ),
			'accelerate_remove_dashboard_activity'    => array( 'dashboard', 'normal', 'core', 'dashboard_activity' ),
		);

		foreach ( $widgets_to_remove as $setting => $path ) {
			if ( ! empty( $settings[ $setting ] ) ) {
				if ( isset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] ) ) {
					unset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] );
				}
			}
		}
	}

	/**
	 * Should Disable Months Dropdown (Post List)
	 */
	public function should_disable_months_dropdown( $disable, $post_type ) {
		$settings = $this->get_settings();
		if ( ! empty( $settings['accelerate_disable_months_dropdown'] ) ) {
			return true;
		}
		return $disable;
	}

	/**
	 * Disable Media Months UI
	 */
	public function disable_media_months( $months ) {
		$settings = $this->get_settings();
		if ( ! empty( $settings['accelerate_disable_months_dropdown'] ) ) {
			return array();
		}
		return $months;
	}

	/**
	 * Intercept and block date-based SELECT DISTINCT queries
	 */
	public function intercept_date_query( $query ) {
		if ( ! is_admin() ) {
			return $query;
		}

		// Target the specific slow queries for years/months
		if ( strpos( $query, 'SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month' ) !== false ) {
			$settings = $this->get_settings();
			if ( ! empty( $settings['accelerate_disable_months_dropdown'] ) ) {
				return 'SELECT 1 FROM wp_posts WHERE 1=0';
			}
		}

		return $query;
	}

	/**
	 * Apply Update Behaviors
	 */
	public function apply_update_behavior() {
		$s = $this->get_settings();

		// 1. Auto-Updates (Unified)
		if ( ! empty( $s['accelerate_disable_auto_updates'] ) ) {
			add_filter( 'auto_update_plugin', '__return_false' );
			add_filter( 'auto_update_theme', '__return_false' );
		}

		// 2. Plugin Update Checks (Unified Cron + Init)
		if ( ! empty( $s['accelerate_disable_plugin_updates'] ) ) {
			// Remove Cron Hooks
			remove_action( 'load-update-core.php', 'wp_update_plugins' );
			remove_action( 'load-plugins.php', 'wp_update_plugins' );
			remove_action( 'load-update.php', 'wp_update_plugins' );
			remove_action( 'wp_update_plugins', 'wp_update_plugins' );
			// Remove Init Hooks
			remove_action( 'admin_init', '_maybe_update_plugins' );
			remove_action( 'admin_init', 'wp_plugin_update_rows' );

			// Force hide updates by filtering transient
			add_filter( 'pre_site_transient_update_plugins', array( $this, 'force_no_plugin_updates' ) );
		}

		// 3. Theme Update Checks (Unified Cron + Init)
		if ( ! empty( $s['accelerate_disable_theme_updates'] ) ) {
			// Remove Cron Hooks
			remove_action( 'load-themes.php', 'wp_update_themes' );
			remove_action( 'load-update.php', 'wp_update_themes' );
			remove_action( 'load-update-core.php', 'wp_update_themes' );
			remove_action( 'wp_update_themes', 'wp_update_themes' );
			// Remove Init Hooks
			remove_action( 'admin_init', '_maybe_update_themes' );
			remove_action( 'admin_init', 'wp_theme_update_rows' );

			// Force hide updates by filtering transient
			add_filter( 'pre_site_transient_update_themes', array( $this, 'force_no_theme_updates' ) );
		}

		// 4. Core Update Checks
		if ( ! empty( $s['accelerate_disable_core_updates'] ) ) {
			remove_action( 'admin_init', '_maybe_update_core' );
			remove_action( 'wp_version_check', 'wp_version_check' );
			add_filter( 'pre_site_transient_update_core', array( $this, 'force_no_core_updates' ) );
		}

		// 5. Global HTTP Block
		if ( ! empty( $s['accelerate_block_external_http'] ) ) {
			if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
				define( 'WP_HTTP_BLOCK_EXTERNAL', true );
			}
		}

		// 6. Custom HTTP Blocker
		if ( ! empty( $s['accelerate_blind_http_requests'] ) ) {
			add_filter( 'http_request_args', array( $this, 'block_custom_http_requests' ), 10, 2 );
		}
	}

	/**
	 * Force No Plugin Updates
	 */
	public function force_no_plugin_updates() {
		$current               = new stdClass();
		$current->last_checked = time();
		$current->response     = array();
		$current->translations = array();
		$current->no_update    = array();
		return $current;
	}

	/**
	 * Force No Theme Updates
	 */
	public function force_no_theme_updates() {
		$current               = new stdClass();
		$current->last_checked = time();
		$current->response     = array();
		$current->translations = array();
		$current->checked      = array();
		return $current;
	}

	/**
	 * Force No Core Updates
	 */
	public function force_no_core_updates() {
		$current                  = new stdClass();
		$current->last_checked    = time();
		$current->updates         = array();
		$current->version_checked = get_bloginfo( 'version' );
		return $current;
	}

	/**
	 * Block Custom HTTP Requests
	 */
	public function block_custom_http_requests( $r, $url ) {
		$settings = $this->get_settings();
		$patterns = ! empty( $settings['accelerate_blind_http_requests'] ) ? $settings['accelerate_blind_http_requests'] : array();

		$url_string = is_array( $url ) ? ( isset( $url['url'] ) ? $url['url'] : '' ) : $url;

		foreach ( $patterns as $item ) {
			if ( empty( $item['url_pattern'] ) ) {
				continue;
			}

			// Case-insensitive sub-string match
			if ( stripos( $url_string, $item['url_pattern'] ) !== false ) {
				$r['blocked'] = true;
				break;
			}
		}

		return $r;
	}

	/**
	 * ============================================
	 * Local Avatar Management Integration
	 * ============================================
	 */
	protected function init_local_avatar() {
		$settings = $this->get_settings();
		if ( empty( $settings['accelerate_enable_local_avatar'] ) ) {
			return;
		}

		// Styles are handled by admin_styles()

		// Load media library scripts on profile pages
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_avatar_scripts' ) );

		// Show custom avatar field
		add_action( 'show_user_profile', array( $this, 'render_avatar_field' ) );
		add_action( 'edit_user_profile', array( $this, 'render_avatar_field' ) );

		// Print inline JS for upload/remove functionality
		// [Refactor] Should extract to JS file, but keeping inline for now to prioritize logic fix
		add_action( 'admin_print_footer_scripts', array( $this, 'print_avatar_js' ) );

		// Save avatar metadata
		add_action( 'personal_options_update', array( $this, 'save_avatar' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_avatar' ) );

		// Override get_avatar to use local avatars
		add_filter( 'get_avatar', array( $this, 'get_local_avatar' ), 10, 5 );
	}

	public function enqueue_avatar_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || ( $screen->base !== 'profile' && $screen->base !== 'user-edit' ) ) {
			return;
		}
		wp_enqueue_media();
		// Inline styles moved to admin_styles()
	}

	public function render_avatar_field( $user ) {
		$avatar_id = get_user_meta( $user->ID, 'st_local_avatar', true );
		$blank_img = includes_url( 'images/blank.gif' );
		?>
		<h3><?php esc_html_e( 'Local Avatar', 'wp-genius' ); ?></h3>
		<table class="form-table">
			<tr>
				<th>
					<label><?php esc_html_e( 'Current Avatar', 'wp-genius' ); ?></label>
				</th>
				<td>
					<input type="hidden" name="st_local_avatar" id="st_local_avatar" value="<?php echo esc_attr( $avatar_id ); ?>">
					<div id="st-avatar-preview">
						<?php
						if ( $avatar_id ) {
							echo wp_get_attachment_image( $avatar_id, 96 );
						} else {
							echo '<img src="' . esc_url( $blank_img ) . '" width="96" height="96" style="background:#f1f1f1;border-radius:50%;" />';
						}
						?>
					</div>
					<p>
						<button type="button" class="button" id="st-upload-avatar">
							<?php esc_html_e( 'Upload / Select Avatar', 'wp-genius' ); ?>
						</button>
						<button type="button" class="button" id="st-remove-avatar">
							<?php esc_html_e( 'Remove Avatar', 'wp-genius' ); ?>
						</button>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function print_avatar_js() {
		$screen = get_current_screen();
		if ( ! $screen || ( $screen->base !== 'profile' && $screen->base !== 'user-edit' ) ) {
			return;
		}

		$blank_img = esc_url( includes_url( 'images/blank.gif' ) );
		?>
		<script>
		(function($) {
			$('#st-upload-avatar').on('click', function(e) {
				e.preventDefault();
				var frame = wp.media({
					title: '<?php esc_html_e( 'Select Avatar', 'wp-genius' ); ?>',
					library: { type: 'image' },
					multiple: false
				}).on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#st_local_avatar').val(attachment.id);
					$('#st-avatar-preview').html('<img src="' + attachment.url + '" width="96" height="96" style="border-radius:50%;" />');
				}).open();
			});

			$('#st-remove-avatar').on('click', function(e) {
				e.preventDefault();
				$('#st_local_avatar').val('');
				$('#st-avatar-preview').html('<img src="<?php echo $blank_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 行 382 已 esc_url 处理。?>" width="96" height="96" style="background:#f1f1f1;border-radius:50%;" />');
			});
		})(jQuery);
		</script>
		<?php
	}

	public function save_avatar( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- profile_update 钩子：WP 用户表单核心已验 nonce。
		$avatar_id = isset( $_POST['st_local_avatar'] ) ? absint( $_POST['st_local_avatar'] ) : 0;
		update_user_meta( $user_id, 'st_local_avatar', $avatar_id );
	}

	public function get_local_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
		if ( is_numeric( $id_or_email ) ) {
			$user = get_user_by( 'id', $id_or_email );
		} elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
			$user = get_user_by( 'id', $id_or_email->user_id );
		} else {
			$user = get_user_by( 'email', $id_or_email );
		}

		if ( ! $user ) {
			return $avatar;
		}

		$avatar_id = get_user_meta( $user->ID, 'st_local_avatar', true );
		if ( $avatar_id ) {
			return wp_get_attachment_image(
				$avatar_id,
				array( $size, $size ),
				false,
				array(
					'class' => "avatar avatar-{$size}",
					'alt'   => $alt,
				)
			);
		}

		$blank_img = esc_url( includes_url( 'images/blank.gif' ) );
		return '<img src="' . $blank_img . '" class="avatar avatar-' . $size . '" width="' . $size . '" height="' . $size . '" alt="' . esc_attr( $alt ) . '" />';
	}

	/**
	 * ============================================
	 * Upload Rename Integration
	 * ============================================
	 */
	protected function init_upload_rename() {
		$settings = $this->get_settings();
		if ( empty( $settings['accelerate_enable_upload_rename'] ) ) {
			return;
		}

		add_filter( 'wp_handle_upload_prefilter', array( $this, 'handle_upload_prefilter' ) );
		add_filter( 'wp_insert_attachment_data', array( $this, 'maybe_replace_attachment_title' ), 10, 2 );
	}

	public function handle_upload_prefilter( $file ) {
		if ( empty( $file['name'] ) ) {
			return $file;
		}

		$settings = $this->get_settings();
		$pattern  = isset( $settings['accelerate_upload_rename_pattern'] ) ? $settings['accelerate_upload_rename_pattern'] : '{timestamp}_{sanitized}';

		$pattern_template = $pattern;
		$pattern          = preg_replace_callback(
			'/\{date(?::([^}]+))?\}/',
			function ( $m ) {
				$fmt = isset( $m[1] ) && $m[1] ? $m[1] : 'Y-m-d';
				return date( $fmt );
			},
			$pattern
		);

		$original_base = pathinfo( $file['name'], PATHINFO_FILENAME );
		$original_base = sanitize_file_name( $original_base );
		$sanitized     = $original_base;
		$ext           = pathinfo( $file['name'], PATHINFO_EXTENSION );
		$timestamp     = time();
		$random        = wp_rand( 1000, 9999 );

		$current_user = wp_get_current_user();
		$user_login   = ! empty( $current_user->user_login ) ? $current_user->user_login : '';
		$user_id      = ! empty( $current_user->ID ) ? $current_user->ID : 0;

		$replacements = array(
			'{timestamp}'  => $timestamp,
			'{sanitized}'  => $sanitized,
			'{rand}'       => $random,
			'{datetime}'   => date( 'YmdHis' ),
			'{year}'       => date( 'Y' ),
			'{month}'      => date( 'm' ),
			'{day}'        => date( 'd' ),
			'{hour}'       => date( 'H' ),
			'{minute}'     => date( 'i' ),
			'{second}'     => date( 's' ),
			'{user_id}'    => $user_id,
			'{user_login}' => $user_login,
			'{orig}'       => $original_base,
			'{ext}'        => $ext,
			'{uniqid}'     => uniqid(),
		);

		$new_name = strtr( $pattern, $replacements );

		if ( false === strpos( $pattern_template, '{ext}' ) ) {
			$new_name = $new_name . ( $ext ? '.' . $ext : '' );
		}

		$new_sanitized = sanitize_file_name( $new_name );
		$file['name']  = $new_sanitized;

		$new_base                           = pathinfo( $new_sanitized, PATHINFO_FILENAME );
		self::$original_titles[ $new_base ] = $original_base;

		return $file;
	}

	public function maybe_replace_attachment_title( $data, $postarr ) {
		if ( empty( $data['post_title'] ) ) {
			return $data;
		}

		$current_base = sanitize_file_name( $data['post_title'] );
		if ( isset( self::$original_titles[ $current_base ] ) ) {
			$data['post_title'] = self::$original_titles[ $current_base ];
		}

		return $data;
	}

	/**
	 * ============================================
	 * Delete with Images Integration
	 * ============================================
	 */
	protected function init_cleanup_images() {
		$settings = $this->get_settings();
		if ( empty( $settings['accelerate_enable_delete_with_images'] ) ) {
			return;
		}

		// Add row action link
		add_filter( 'post_row_actions', array( $this, 'cleanup_images_add_row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'cleanup_images_add_row_action' ), 10, 2 );

		// Add Admin Bar Action
		add_action( 'admin_bar_menu', array( $this, 'cleanup_images_add_admin_bar_action' ), 100 );

		// Handle deletion action
		add_action( 'admin_post_w2p_delete_post_with_images', array( $this, 'cleanup_images_handle_delete_action' ) );

		// Add link to Edit Post screen
		add_action( 'post_submitbox_start', array( $this, 'cleanup_images_add_edit_post_action' ) );

		// Enqueue scripts (use core admin ui, merged CSS in admin_styles)
		add_action( 'admin_enqueue_scripts', array( $this, 'cleanup_images_enqueue_scripts' ) );
		add_action( 'admin_footer', array( $this, 'cleanup_images_print_footer_scripts' ) );

		// Frontend enqueues for admin bar
		add_action( 'wp_enqueue_scripts', array( $this, 'cleanup_images_enqueue_frontend_scripts' ) );
		add_action( 'wp_footer', array( $this, 'cleanup_images_print_footer_scripts' ) );

		// Bulk Actions
		add_filter( 'bulk_actions-edit-post', array( $this, 'cleanup_images_register_bulk_actions' ) );
		add_filter( 'bulk_actions-edit-page', array( $this, 'cleanup_images_register_bulk_actions' ) );
		// Note: 'handle_bulk_actions-{screen}' hooks need precise screen IDs.
		add_filter( 'handle_bulk_actions-edit-post', array( $this, 'cleanup_images_handle_bulk_actions' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-page', array( $this, 'cleanup_images_handle_bulk_actions' ), 10, 3 );
	}

	/**
	 * Register Bulk Action
	 */
	public function cleanup_images_register_bulk_actions( $bulk_actions ) {
		$bulk_actions['w2p_delete_with_images_bulk'] = __( 'Delete w/ Images', 'wp-genius' );
		return $bulk_actions;
	}

	/**
	 * Handle Bulk Actions
	 */
	public function cleanup_images_handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( $doaction !== 'w2p_delete_with_images_bulk' ) {
			return $redirect_to;
		}

		$deleted_images = 0;
		$deleted_posts  = 0;

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				continue;
			}

			// Reuse the processing logic
			$result = $this->cleanup_images_process_single_post_deletion( $post_id );
			if ( $result ) {
				++$deleted_posts;
				$deleted_images += $result['deleted_images'];
			}
		}

		$redirect_to = add_query_arg(
			array(
				'w2p_bulk_deleted_posts'  => $deleted_posts,
				'w2p_bulk_deleted_images' => $deleted_images,
			),
			$redirect_to
		);

		return $redirect_to;
	}

	/**
	 * Add "Delete w/ Images" link to row actions
	 */
	public function cleanup_images_add_row_action( $actions, $post ) {
		// Only for posts with permission
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return $actions;
		}

		// Only if not in trash
		if ( 'trash' === $post->post_status ) {
			return $actions;
		}

		// Capture current URL for safe redirect
		$redirect_to = urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		// Build the deletion URL
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=w2p_delete_post_with_images&post_id=' . $post->ID . '&redirect_to=' . $redirect_to ),
			'w2p_delete_with_images_' . $post->ID
		);

		// Add the action
		$actions['w2p_delete_with_images'] = sprintf(
			'<a href="%s" class="delete w2p-delete-with-images-btn">%s</a>',
			esc_url( $url ),
			esc_html__( 'Delete w/ Images', 'wp-genius' )
		);

		return $actions;
	}

	/**
	 * Add "Delete w/ Images" link to Admin Bar
	 */
	public function cleanup_images_add_admin_bar_action( $wp_admin_bar ) {
		if ( ! is_admin() && is_singular() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- admin bar 链接构造只读；实际删除在带 check_admin_referer 的 admin-post handler。
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 同上（重定向 URL 读取）。
			$post_id = get_the_ID();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- admin bar 链接构造只读；实际删除在带 check_admin_referer 的 admin-post handler。
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 同上。
		} elseif ( is_admin() && isset( $_GET['post'] ) && $_GET['action'] === 'edit' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- 上层方法已验 nonce（见方法开头）。
			$post_id = (int) $_GET['post'];
		} else {
			return;
		}

		if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'trash' === $post->post_status ) {
			return;
		}

		// Capture current URL if on backend, or homepage if frontend (since post deletes, staying on page gives 404)
		if ( is_admin() ) {
			$redirect_to = urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		} else {
			// Frontend: Try to stay in context by redirecting to next or previous post.
			$next_post = get_adjacent_post( false, '', false );
			if ( $next_post ) {
				$redirect_to = urlencode( get_permalink( $next_post ) );
			} else {
				$prev_post = get_adjacent_post( false, '', true );
				if ( $prev_post ) {
					$redirect_to = urlencode( get_permalink( $prev_post ) );
				} else {
					$redirect_to = urlencode( home_url() );
				}
			}
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=w2p_delete_post_with_images&post_id=' . $post_id . '&redirect_to=' . $redirect_to ),
			'w2p_delete_with_images_' . $post_id
		);

		$wp_admin_bar->add_node(
			array(
				'id'    => 'w2p-delete-with-images',
				'title' => __( 'Delete w/ Images', 'wp-genius' ),
				'href'  => esc_url( $url ),
				'meta'  => array(
					'class' => 'w2p-delete-with-images-btn',
				),
			)
		);
	}

	/**
	 * Add "Delete w/ Images" link to Edit Post screen (Publish Meta Box)
	 */
	public function cleanup_images_add_edit_post_action() {
		global $post;

		if ( ! $post || ! current_user_can( 'delete_post', $post->ID ) ) {
			return;
		}

		if ( 'trash' === $post->post_status ) {
			return;
		}

		// Redirect to post list after deletion from single edit screen
		$redirect_to = urlencode( admin_url( 'edit.php?post_type=' . $post->post_type ) );

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=w2p_delete_post_with_images&post_id=' . $post->ID . '&redirect_to=' . $redirect_to ),
			'w2p_delete_with_images_' . $post->ID
		);

		// Render View
		?>
		<div id="w2p-delete-with-images-wrap">
			<a href="<?php echo esc_url( $url ); ?>" class="submitdelete w2p-delete-with-images-btn">
				<?php esc_html_e( 'Delete w/ Images', 'wp-genius' ); ?>
			</a>
		</div>
		<script>
		// Move to the bottom next to Move to Trash if possible, or keep at top of submit box
		jQuery(document).ready(function($) {
			var $link = $('#w2p-delete-with-images-wrap');
			var $trashLink = $('#delete-action');
			
			if ($trashLink.length) {
				$link.css('margin-left', '10px'); // JS styling required for dynamic positioning
				$link.contents().appendTo($trashLink);
				$link.remove();
			}
		});
		</script>
		<?php
	}

	public function cleanup_images_enqueue_scripts() {
		wp_enqueue_script( 'w2p-admin-ui' );
		wp_enqueue_style( 'w2p-core-css' );

		// Module specific admin styles are already injected via admin_styles()
	}

	public function cleanup_images_enqueue_frontend_scripts() {
		if ( is_user_logged_in() && current_user_can( 'delete_posts' ) ) {
			wp_enqueue_script( 'w2p-admin-ui' );
			wp_enqueue_style( 'w2p-core-css' );
		}
	}

	public function cleanup_images_print_footer_scripts() {
		if ( ! is_admin() && ( ! is_user_logged_in() || ! current_user_can( 'delete_posts' ) ) ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Precise target selectors to intercept clicks exactly on the A tags:
			var btnSelectors = '#wp-admin-bar-w2p-delete-with-images a, a.w2p-delete-with-images-btn, #w2p-delete-with-images-wrap a';
			
			$(document).on('click', btnSelectors, function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var href = $(this).attr('href');
				if (!href || href === '#' || href === '') return;

				
				var confirmMsg = '<?php echo esc_js( __( 'Are you sure you want to delete this post AND all its associated local images? This cannot be undone.', 'wp-genius' ) ); ?>';
				
				if (typeof w2p !== 'undefined' && typeof w2p.confirm === 'function') {
					w2p.confirm(
						confirmMsg,
						function() {
							window.location.href = href;
						}
					);
				} else {
					if (confirm(confirmMsg)) {
						window.location.href = href;
					}
				}
			});

			// Bulk Action Confirm
			$('#doaction, #doaction2').on('click', function(e) {
				var action = $(this).prev('select').val();
				
				if (action === 'w2p_delete_with_images_bulk') {
					e.preventDefault();
					var $form = $(this).closest('form');
					
					// Check if any items selected
					if ($form.find('input[name="post[]"]:checked').length === 0) {
						return;
					}

					var message = '<?php echo esc_js( __( 'Are you sure you want to delete the selected posts AND all their associated local images? This cannot be undone.', 'wp-genius' ) ); ?>';

					if (typeof w2p !== 'undefined' && typeof w2p.confirm === 'function') {
						w2p.confirm(
							message,
							function() {
								// We need to submit the form. 
								// Since we prevented default, we need to re-trigger or submit manually.
								$form.submit();
							}
						);
					} else {
						if (confirm(message)) {
							$form.submit();
						}
					}
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle the deletion action
	 */
	public function cleanup_images_handle_delete_action() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_die( esc_html__( 'Invalid post ID.', 'wp-genius' ) );
		}

		check_admin_referer( 'w2p_delete_with_images_' . $post_id );

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this post.', 'wp-genius' ) );
		}

		$result = $this->cleanup_images_process_single_post_deletion( $post_id );

		// Default redirect
		$redirect_url = admin_url( 'edit.php?post_type=' . get_post_type( $post_id ) );

		// Check for custom redirect_to (with validation to prevent open redirect)
		if ( ! empty( $_GET['redirect_to'] ) ) {
			$redirect_to  = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
			$redirect_url = wp_validate_redirect( $redirect_to, $redirect_url );
		}

		if ( $result ) {
			$redirect_url = add_query_arg(
				array(
					'w2p_deleted_images' => $result['deleted_images'],
					'w2p_deleted_post'   => $post_id,
				),
				$redirect_url
			);
		}

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process deletion for a single post
	 */
	private function cleanup_images_process_single_post_deletion( $post_id ) {
		// 1. Collect Images
		$image_ids      = $this->cleanup_images_collect_post_images( $post_id );
		$deleted_images = 0;

		// 2. Delete Images
		foreach ( $image_ids as $attachment_id ) {
			if ( wp_delete_attachment( $attachment_id, true ) ) {
				++$deleted_images;
			}
		}

		// 3. Delete Post
		$result = wp_delete_post( $post_id, true ); // Force delete

		if ( $result ) {
			return array( 'deleted_images' => $deleted_images );
		}

		return false;
	}

	/**
	 * Collect all local attachment IDs from post content and thumbnails
	 */
	private function cleanup_images_collect_post_images( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$image_ids = array();

		// A. Featured Image
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			$image_ids[] = $thumbnail_id;
		}

		// B. Content Images
		$content = $post->post_content;

		// 1. Try to match by class wp-image-{id} (Most reliable)
		if ( preg_match_all( '/class="[^"]*wp-image-(\d+)[^"]*"/', $content, $matches ) ) {
			if ( ! empty( $matches[1] ) ) {
				foreach ( $matches[1] as $id ) {
					$image_ids[] = absint( $id );
				}
			}
		}

		// 2. Scan for img src URLs for images not caught by class match (e.g. pasted directly)
		if ( preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/', $content, $matches ) ) {
			if ( ! empty( $matches[1] ) ) {
				$site_url    = home_url();
				$site_domain = parse_url( $site_url, PHP_URL_HOST );

				foreach ( $matches[1] as $url ) {
					// Check if it's a local URL
					$img_domain = parse_url( $url, PHP_URL_HOST );
					if ( $img_domain !== $site_domain ) {
						continue; // Skip external images
					}

					// Try to resolve to ID using built-in WP function
					$id = attachment_url_to_postid( $url );

					// If failed, try to handle scaled images (remove -150x150 suffix etc)
					if ( ! $id ) {
						// Simple regex to strip dimensions: file-name-100x100.jpg -> file-name.jpg
						$clean_url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif|webp)$)/i', '', $url );
						if ( $clean_url !== $url ) {
							$id = attachment_url_to_postid( $clean_url );
						}
					}

					if ( $id ) {
						$image_ids[] = $id;
					}
				}
			}
		}

		// Unique and valid check
		$image_ids = array_unique( $image_ids );

		// Filter to ensure they are actually attachments
		$valid_ids = array();
		foreach ( $image_ids as $id ) {
			if ( 'attachment' === get_post_type( $id ) ) {
				$valid_ids[] = $id;
			}
		}

		return $valid_ids;
	}
}
