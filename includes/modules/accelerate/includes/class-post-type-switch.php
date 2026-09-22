<?php
/**
 * WP Genius Accelerate — Change Post Type
 *
 * Adds a "Change PostType" select to the Publish panel on the edit screen and a
 * "PostType" select to the Bulk Edit panel on list screens, letting admins move
 * posts between post types (e.g. post <-> albums).
 *
 * Split from module.php (refactored from the God class), same pattern as ImageCleanup.
 *
 * @package WP_Genius
 * @subpackage Modules/Accelerate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class W2P_Accelerate_PostTypeSwitch
 */
class W2P_Accelerate_PostTypeSwitch {

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
	 * Wire up hooks — the module only calls this when the feature is enabled,
	 * so nothing is registered while the toggle is off.
	 *
	 * @return void
	 */
	public function init_change_post_type() {
		$settings = $this->module->get_settings();
		if ( empty( $settings['accelerate_enable_change_post_type'] ) ) {
			return;
		}

		// Single edit screen: select inside the Publish panel.
		add_action( 'post_submitbox_misc_actions', array( $this, 'render_publish_panel_field' ) );

		// Single edit screen: process the change.
		add_action( 'admin_post_w2p_change_post_type', array( $this, 'handle_change_post_type' ) );

		// List screens: inject the PostType select into the Bulk Edit panel.
		add_action( 'admin_footer-edit.php', array( $this, 'render_bulk_edit_field' ) );

		// Core's bulk_edit_posts() forces post_type back to its original value;
		// override it only when our bulk edit field is part of the request.
		add_filter( 'wp_insert_post_data', array( $this, 'override_bulk_post_type' ), 10, 2 );
	}

	/**
	 * Selectable post types (reuses the module's filtered type list, minus attachment).
	 *
	 * @return array slug => label.
	 */
	public static function get_allowed_post_types() {
		$types = W2P_AccelerateModule::get_months_dropdown_post_types();
		unset( $types['attachment'] );

		return $types;
	}

	/**
	 * Whether the given post type slug may act as source or target.
	 *
	 * @param string $slug Post type slug.
	 * @return bool
	 */
	private static function is_allowed( $slug ) {
		return '' !== $slug && isset( self::get_allowed_post_types()[ $slug ] );
	}

	/**
	 * Plain post type name for display.
	 *
	 * The shared list helper appends " (slug)" to every label, which is noise
	 * for a publish panel row - the dropdown keeps it, this does not.
	 *
	 * @param string $slug Post type slug.
	 * @return string
	 */
	private static function get_type_label( $slug ) {
		$pto = get_post_type_object( $slug );

		return $pto ? $pto->labels->name : $slug;
	}

	/**
	 * Render the "Change Type" row in the Publish panel.
	 *
	 * Mirrors core's Status row: a plain label + current value with an "Edit"
	 * toggle that reveals the select plus OK / Cancel buttons.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_publish_panel_field( $post ) {
		if ( ! $post instanceof WP_Post || ! self::is_allowed( $post->post_type ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$types    = self::get_allowed_post_types();
		$current  = self::get_type_label( $post->post_type );
		$url      = wp_nonce_url(
			admin_url( 'admin-post.php?action=w2p_change_post_type&post_id=' . $post->ID ),
			'w2p_change_post_type_' . $post->ID
		);
		?>
		<div class="misc-pub-section misc-pub-post-type" id="w2p-post-type">
			<span class="dashicons dashicons-randomize" style="vertical-align:middle;margin-right:4px" aria-hidden="true"></span>
			<?php esc_html_e( 'Change Type:', 'wp-genius' ); ?>
			<strong id="w2p-post-type-display"><?php echo esc_html( $current ); ?></strong>

			<a href="#w2p_post_type" class="edit-w2p-post-type hide-if-no-js" role="button"><span aria-hidden="true"><?php esc_html_e( 'Edit', 'wp-genius' ); ?></span> <span class="screen-reader-text"><?php esc_html_e( 'Edit post type', 'wp-genius' ); ?></span></a>

			<div id="w2p-post-type-select" class="hide-if-js">
				<label for="w2p-new-post-type" class="screen-reader-text"><?php esc_html_e( 'Set post type', 'wp-genius' ); ?></label>
				<select id="w2p-new-post-type">
					<?php foreach ( $types as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $post->post_type ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<a href="#w2p_post_type" class="save-w2p-post-type hide-if-no-js button"><?php esc_html_e( 'OK', 'wp-genius' ); ?></a>
				<a href="#w2p_post_type" class="cancel-w2p-post-type hide-if-no-js button-cancel"><?php esc_html_e( 'Cancel', 'wp-genius' ); ?></a>
			</div>
		</div>
		<script>
		jQuery( function( $ ) {
			var $select  = $( '#w2p-post-type-select' ),
				original = <?php echo wp_json_encode( $post->post_type ); ?>,
				target   = <?php echo wp_json_encode( wp_specialchars_decode( $url, ENT_QUOTES ) ); ?>;

			$select.siblings( '.edit-w2p-post-type' ).on( 'click', function( event ) {
				if ( $select.is( ':hidden' ) ) {
					$select.slideDown( 'fast', function() {
						$select.find( 'select' ).trigger( 'focus' );
					} );
					$( this ).hide();
				}
				event.preventDefault();
			} );

			var close = function() {
				$select.slideUp( 'fast' ).siblings( '.edit-w2p-post-type' ).show().trigger( 'focus' );
			};

			$select.find( '.cancel-w2p-post-type' ).on( 'click', function( event ) {
				$( '#w2p-new-post-type' ).val( original );
				close();
				event.preventDefault();
			} );

			$select.find( '.save-w2p-post-type' ).on( 'click', function( event ) {
				event.preventDefault();
				var type = $( '#w2p-new-post-type' ).val();
				if ( ! type || type === original ) {
					close();
					return;
				}
				window.location.href = target + '&new_post_type=' + encodeURIComponent( type );
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Process the single post type change (admin-post handler).
	 *
	 * @return void
	 */
	public function handle_change_post_type() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$new     = isset( $_GET['new_post_type'] ) ? sanitize_key( wp_unslash( $_GET['new_post_type'] ) ) : '';

		check_admin_referer( 'w2p_change_post_type_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || ! self::is_allowed( $post->post_type ) || ! self::is_allowed( $new ) ) {
			wp_die( esc_html__( 'Invalid post or post type.', 'wp-genius' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this post.', 'wp-genius' ) );
		}
		$new_pto = get_post_type_object( $new );
		if ( ! $new_pto || ! current_user_can( $new_pto->cap->edit_posts ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this post type.', 'wp-genius' ) );
		}

		if ( $new !== $post->post_type ) {
			// Switching type must not drag Smart AUI along: it hooks
			// wp_insert_post_data to pull remote images (and save_post to auto-set
			// the featured image), both of which honour this skip filter. Without
			// it the save stalls on a silent, progress-less capture.
			add_filter( 'smart_aui_skip_post_processing', '__return_true' );

			wp_update_post(
				array(
					'ID'        => $post_id,
					'post_type' => $new,
				)
			);

			remove_filter( 'smart_aui_skip_post_processing', '__return_true' );
		}

		// Back to where the change was made, fallback to the new type's list.
		$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . $new );
		wp_safe_redirect( wp_validate_redirect( $redirect, admin_url( 'edit.php?post_type=' . $new ) ) );
		exit;
	}

	/**
	 * Print the PostType select (hidden) plus the small script that slides it
	 * into the Bulk Edit panel. Core's inline editor submits every :input in
	 * the bulk row, so the value rides along automatically.
	 *
	 * Placement: core renders its Format field as a bare label that leaves the
	 * row's right-hand slot empty, so we wrap that label in an
	 * .inline-edit-group and drop our own alignright label beside it. That
	 * reuses core's own alignleft/alignright pairing and puts PostType in the
	 * right-hand column, directly under Pings / Sticky.
	 *
	 * Our own group cannot do that: .inline-edit-group is clear:both, so it
	 * always starts a fresh row with a gap above it.
	 *
	 * @return void
	 */
	public function render_bulk_edit_field() {
		?>
		<div id="w2p-bulk-post-type-field" class="inline-edit-group wp-clearfix" style="display:none">
			<label class="alignright">
				<span class="title"><?php esc_html_e( 'PostType', 'wp-genius' ); ?></span>
				<select name="w2p_bulk_post_type">
					<option value="-1"><?php esc_html_e( '&mdash; No Change &mdash;', 'wp-genius' ); ?></option>
					<?php foreach ( self::get_allowed_post_types() as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<script>
		jQuery( function( $ ) {
			var $field = $( '#w2p-bulk-post-type-field' ),
				$col   = $( '#bulk-edit .inline-edit-col-right .inline-edit-col' );
			if ( ! $field.length || ! $col.length ) {
				return;
			}

			var $label  = $field.children( 'label' ),
				$format = $col.find( 'select[name="post_format"]' ).closest( 'label' );

			if ( $format.length ) {
				// Share the Format row rather than claiming a row of our own.
				if ( ! $format.parent().hasClass( 'inline-edit-group' ) ) {
					$format.wrap( '<div class="inline-edit-group wp-clearfix"></div>' );
				}
				$label.appendTo( $format.parent() );
				$field.remove();
				return;
			}

			// No Format row (post type without post formats): right-aligned row.
			$field.appendTo( $col ).show();
		} );
		</script>
		<?php
	}

	/**
	 * Override post_type during bulk edit saves.
	 *
	 * Bulk Edit submits the posts form (GET bulk_edit=Update) to edit.php, which
	 * passes check_admin_referer( 'bulk-posts' ) before dispatching, and
	 * bulk_edit_posts() enforces per-post edit_post caps; only the target type
	 * cap is verified here. Any other request is passed through untouched.
	 *
	 * @param array $data    Sanitized post data to be written.
	 * @param array $postarr Raw post array (post_type holds the original, core-forced value).
	 * @return array
	 */
	public function override_bulk_post_type( $data, $postarr ) {
		if ( ! isset( $_REQUEST['bulk_edit'] ) || ! isset( $_REQUEST['w2p_bulk_post_type'] ) ) {
			return $data;
		}

		$new = sanitize_key( wp_unslash( $_REQUEST['w2p_bulk_post_type'] ) );
		if ( ! self::is_allowed( $new ) ) {
			return $data;
		}

		$original = isset( $postarr['post_type'] ) ? $postarr['post_type'] : '';
		if ( ! self::is_allowed( $original ) || $new === $original ) {
			return $data;
		}

		$pto = get_post_type_object( $new );
		if ( ! $pto || ! current_user_can( $pto->cap->edit_posts ) ) {
			return $data;
		}

		$data['post_type'] = $new;

		return $data;
	}
}
