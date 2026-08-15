<?php
/**
 * WP Genius Accelerate — Delete Posts with Images
 *
 * Split from module.php (refactored from the God class).
 *
 * @package WP_Genius
 * @subpackage Modules/Accelerate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_Accelerate_ImageCleanup
 */
class W2P_Accelerate_ImageCleanup {

	/**
	 * Parent module instance.
	 *
	 * @var W2P_AccelerateModule
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param W2P_AccelerateModule $module Parent module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * ============================================
	 * Delete with Images Integration
	 * ============================================
	 */
	public function init_cleanup_images() {
		$settings = $this->module->get_settings();
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

		// Bulk Actions — scoped to the post and albums post types only (bulk actions are per-screen hooks).
		add_filter( 'bulk_actions-edit-post', array( $this, 'cleanup_images_register_bulk_actions' ) );
		add_filter( 'bulk_actions-edit-albums', array( $this, 'cleanup_images_register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-post', array( $this, 'cleanup_images_handle_bulk_actions' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-albums', array( $this, 'cleanup_images_handle_bulk_actions' ), 10, 3 );
	}

	/**
	 * Whether the Delete w/ Images feature applies to the given post type.
	 *
	 * Reads the user-configurable post type list (accelerate_delete_with_images_post_types);
	 * falls back to posts + albums when the option has not been saved yet.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function cleanup_images_allowed_post_type( $post_type ) {
		$settings = $this->module->get_settings();
		$raw      = isset( $settings['accelerate_delete_with_images_post_types'] )
			? (array) $settings['accelerate_delete_with_images_post_types']
			: array( 'post', 'albums' );

		$valid    = W2P_AccelerateModule::get_months_dropdown_post_type_slugs();
		$selected = array();

		foreach ( $raw as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug && in_array( $slug, $valid, true ) ) {
				$selected[] = $slug;
			}
		}

		return in_array( $post_type, $selected, true );
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

			// Only the scoped post types may use this bulk action.
			if ( ! $this->cleanup_images_allowed_post_type( get_post_type( $post_id ) ) ) {
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
		// Only for the scoped post types (post / albums).
		if ( ! $this->cleanup_images_allowed_post_type( $post->post_type ) ) {
			return $actions;
		}

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
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- admin bar link construction is read-only; the actual deletion happens in the admin-post handler with check_admin_referer.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Same as above (redirect URL read).
			$post_id = get_the_ID();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- admin bar link construction is read-only; the actual deletion happens in the admin-post handler with check_admin_referer.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Same as above.
		} elseif ( is_admin() && isset( $_GET['post'] ) && $_GET['action'] === 'edit' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- The calling method has already verified the nonce (see the top of the method).
			$post_id = (int) $_GET['post'];
		} else {
			return;
		}

		if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! $this->cleanup_images_allowed_post_type( $post->post_type ) || 'trash' === $post->post_status ) {
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

		if ( ! $this->cleanup_images_allowed_post_type( $post->post_type ) ) {
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

		// Defense in depth: only the scoped post types may use this action.
		if ( ! $this->cleanup_images_allowed_post_type( get_post_type( $post_id ) ) ) {
			wp_die( esc_html__( 'This action is not available for this post type.', 'wp-genius' ) );
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

		wp_safe_redirect( $redirect_url );
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
