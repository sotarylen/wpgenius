<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2P_NovelManagerModule extends W2P_Abstract_Module {
	public static function id() {
		return 'novel-manager';
	}

	public static function name() {
		return __( 'Novel Manager', 'wp-genius' );
	}

	public static function icon() {
		return 'fa-solid fa-book';
	}

	public static function description() {
		return __( 'Manage the novel and chapter content types: import content, fix chapter indexes, and keep related chapters organized.', 'wp-genius' );
	}

	public function init() {
		// Load the library dependencies (PHPWord, etc.)
		$autoload = __DIR__ . '/library/vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		// Include the logic handler class
		require_once __DIR__ . '/class-word-to-posts.php';

		// Include the Fix Chapter Index handler
		require_once __DIR__ . '/class-fix-chapter-index.php';

		// Register AJAX handlers for import operations
		add_action( 'admin_post_handle_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_scan_uploads', array( $this, 'handle_scan' ) );
		add_action( 'admin_post_clean_uploads', array( $this, 'handle_clean' ) );
		add_action( 'admin_post_fix_chapter_index', array( $this, 'handle_fix_chapter_index' ) );
		add_action( 'wp_ajax_fix_chapter_index_save_config', array( $this, 'handle_fix_chapter_index_save_config' ) );
		add_action( 'wp_ajax_fix_chapter_index_init', array( $this, 'handle_fix_chapter_index_init' ) );
		add_action( 'wp_ajax_fix_chapter_index_process', array( $this, 'handle_fix_chapter_index_process' ) );

		// Asset loading
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Bulk Action Script
		add_action( 'admin_footer', array( $this, 'inject_bulk_action_script' ) );

		// Only load these on admin pages
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Novel Manager: cascade delete/trash — removing a novel also removes its chapters.
		add_action( 'trashed_post', array( $this, 'cascade_trash_chapters' ) );
		add_action( 'before_delete_post', array( $this, 'cascade_delete_chapters' ) );

		// Novel Manager: explicit "Delete w/ Chapters" row action on the novel list.
		add_filter( 'post_row_actions', array( $this, 'add_novel_delete_row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_novel_delete_row_action' ), 10, 2 );
		add_action( 'admin_post_w2p_delete_novel_with_chapters', array( $this, 'handle_delete_novel_with_chapters' ) );
		add_action( 'wp_ajax_w2p_novel_delete_batch', array( $this, 'ajax_novel_delete_batch' ) );
		add_action( 'wp_ajax_w2p_novel_delete_final', array( $this, 'ajax_novel_delete_final' ) );
		add_action( 'admin_footer', array( $this, 'inject_novel_delete_confirm_script' ) );
	}

	/**
	 * Add a "Delete w/ Chapters" row action to novel list rows.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Current post object.
	 * @return array
	 */
	public function add_novel_delete_row_action( $actions, $post ) {
		if ( 'novel' !== $post->post_type ) {
			return $actions;
		}

		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return $actions;
		}

		if ( 'trash' === $post->post_status ) {
			return $actions;
		}

		$redirect_to = urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$url         = wp_nonce_url(
			admin_url( 'admin-post.php?action=w2p_delete_novel_with_chapters&post_id=' . $post->ID . '&redirect_to=' . $redirect_to ),
			'w2p_delete_novel_with_chapters_' . $post->ID
		);

		$actions['w2p_delete_novel_with_chapters'] = sprintf(
			'<a href="%s" class="delete w2p-delete-novel-with-chapters-btn" style="color:#b32d2e;">%s</a>',
			esc_url( $url ),
			esc_html__( 'Delete w/ Chapters', 'wp-genius' )
		);

		return $actions;
	}

	/**
	 * Handle the "Delete w/ Chapters" admin-post action.
	 *
	 * Permanently deletes the novel; the before_delete_post hook then cascades
	 * the deletion to every chapter linked via related_novel_id.
	 *
	 * @return void
	 */
	public function handle_delete_novel_with_chapters() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_die( esc_html__( 'Invalid post ID.', 'wp-genius' ) );
		}

		check_admin_referer( 'w2p_delete_novel_with_chapters_' . $post_id );

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this post.', 'wp-genius' ) );
		}

		if ( 'novel' !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'This action is only available for novels.', 'wp-genius' ) );
		}

		wp_delete_post( $post_id, true ); // Cascade deletes chapters via before_delete_post.

		$redirect_url = admin_url( 'edit.php?post_type=novel' );
		if ( ! empty( $_GET['redirect_to'] ) ) {
			$redirect_to  = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
			$redirect_url = wp_validate_redirect( $redirect_to, $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Inject a confirmation dialog for the "Delete w/ Chapters" row action (novel list only).
	 *
	 * @return void
	 */
	public function inject_novel_delete_confirm_script() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-novel' !== $screen->id ) {
			return;
		}
		?>
		<style>
		#w2p-novel-delete-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.6);
			z-index: 999999;
			display: none;
			justify-content: center;
			align-items: center;
		}
		#w2p-novel-delete-modal.active {
			display: flex;
		}
		#w2p-novel-delete-modal .w2p-nd-box {
			background: #fff;
			border-radius: 8px;
			padding: 24px 28px;
			width: 440px;
			max-width: 92%;
			box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
			font-size: 14px;
			color: #1d2327;
		}
		#w2p-novel-delete-modal .w2p-nd-box h4 {
			margin: 0 0 10px;
			font-size: 16px;
		}
		#w2p-novel-delete-modal .w2p-nd-count-row {
			margin: 0 0 8px;
			color: #1d2327;
		}
		#w2p-novel-delete-modal .w2p-nd-count-row span {
			color: #50575e;
		}
		#w2p-novel-delete-modal .w2p-nd-count-row strong {
			font-size: 18px;
			color: #b32d2e;
		}
		#w2p-novel-delete-modal .w2p-nd-text {
			margin: 0 0 12px;
			color: #50575e;
		}
		#w2p-novel-delete-modal .w2p-nd-progress-bg {
			height: 10px;
			background: #e5e7eb;
			border-radius: 5px;
			overflow: hidden;
			margin-top: 8px;
		}
		#w2p-novel-delete-modal .w2p-nd-progress-fill {
			height: 100%;
			width: 0%;
			background: #b32d2e;
			transition: width 0.25s ease;
		}
		</style>
		<div id="w2p-novel-delete-modal">
			<div class="w2p-nd-box">
				<h4><?php echo esc_html( __( 'Deleting Novel', 'wp-genius' ) ); ?></h4>
				<div class="w2p-nd-count-row">
					<span><?php echo esc_html( __( 'Chapters to delete', 'wp-genius' ) ); ?>:</span>
					<strong id="w2p-nd-count">&mdash;</strong>
				</div>
				<p class="w2p-nd-text" id="w2p-nd-text"><?php echo esc_html( __( 'Counting related chapters...', 'wp-genius' ) ); ?></p>
				<div class="w2p-nd-progress-bg">
					<div class="w2p-nd-progress-fill" id="w2p-nd-progress-fill"></div>
				</div>
			</div>
		</div>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var w2pNovelDelete = {
				nonce: '<?php echo esc_js( wp_create_nonce( 'w2p_novel_manager_delete' ) ); ?>',
				novelId: 0,
				total: 0,
				deleted: 0,

				openModal: function() {
					$('#w2p-nd-count').text('<?php echo esc_js( __( 'Counting...', 'wp-genius' ) ); ?>');
					$('#w2p-nd-text').text('<?php echo esc_js( __( 'Counting related chapters...', 'wp-genius' ) ); ?>');
					$('#w2p-nd-progress-fill').css('width', '0%');
					$('#w2p-novel-delete-modal').addClass('active');
				},
				closeModal: function() {
					$('#w2p-novel-delete-modal').removeClass('active');
				},
				setCount: function() {
					$('#w2p-nd-count').text(this.total);
				},
				setProgress: function() {
					var pct = this.total ? Math.min(100, Math.round(this.deleted / this.total * 100)) : 100;
					var text = '<?php echo esc_js( __( 'Deleting chapters', 'wp-genius' ) ); ?>: ' + this.deleted + ' / ' + this.total;
					$('#w2p-nd-text').text(text);
					$('#w2p-nd-progress-fill').css('width', pct + '%');
				},
				start: function(novelId) {
					this.novelId = novelId;
					this.total = 0;
					this.deleted = 0;
					this.openModal();
					this.deleteNextBatch(0);
				},
				// Retry wrapper: retries a failing request up to 3 times (deletion is idempotent) before giving up.
				retryOrFail: function(retryFn, attempt, msg) {
					var self = this;
					if (attempt < 3) {
						$('#w2p-nd-text').text('<?php echo esc_js( __( 'Retrying...', 'wp-genius' ) ); ?> (' + (attempt + 1) + '/3)');
						setTimeout(function() { retryFn(attempt + 1); }, 600 * (attempt + 1));
					} else {
						self.fail(msg);
					}
				},
				deleteNextBatch: function(attempt) {
					var self = this;
					attempt = typeof attempt === 'number' ? attempt : 0;
					$.post(ajaxurl, {
						action: 'w2p_novel_delete_batch',
						nonce: self.nonce,
						novel_id: self.novelId
					}, function(res) {
						if (!res || !res.success) {
							self.retryOrFail(function(a) { self.deleteNextBatch(a); }, attempt, res && res.data ? res.data : '<?php echo esc_js( __( 'Delete failed', 'wp-genius' ) ); ?>');
							return;
						}
						var d = res.data;
						if (self.total === 0) {
							self.total = d.total;
							self.setCount();
						}
						self.deleted += d.batch_deleted;
						self.setProgress();

						if (d.done) {
							$('#w2p-nd-text').text('<?php echo esc_js( __( 'Deleting the novel itself...', 'wp-genius' ) ); ?>');
							$('#w2p-nd-progress-fill').css('width', '100%');
							self.finalize(0);
							return;
						}
						setTimeout(function() { self.deleteNextBatch(); }, 120);
					}).fail(function() {
						self.retryOrFail(function(a) { self.deleteNextBatch(a); }, attempt, '<?php echo esc_js( __( 'AJAX request failed', 'wp-genius' ) ); ?>');
					});
				},
				finalize: function(attempt) {
					var self = this;
					attempt = typeof attempt === 'number' ? attempt : 0;
					$.post(ajaxurl, {
						action: 'w2p_novel_delete_final',
						nonce: self.nonce,
						novel_id: self.novelId
					}, function(res2) {
						if (res2 && res2.success) {
							window.location.reload();
						} else {
							self.retryOrFail(function(a) { self.finalize(a); }, attempt, res2 && res2.data ? res2.data : '<?php echo esc_js( __( 'Delete failed', 'wp-genius' ) ); ?>');
						}
					}).fail(function() {
						self.retryOrFail(function(a) { self.finalize(a); }, attempt, '<?php echo esc_js( __( 'AJAX request failed', 'wp-genius' ) ); ?>');
					});
				},
				fail: function(msg) {
					this.closeModal();
					if (typeof w2p !== 'undefined' && typeof w2p.toast === 'function') {
						w2p.toast(msg, 'error');
					} else {
						alert(msg);
					}
				}
			};

			$(document).on('click', 'a.w2p-delete-novel-with-chapters-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();

				var href = $(this).attr('href');
				if (!href || href === '#') return;

				var m = href.match(/post_id=(\d+)/);
				if (!m) return;
				var novelId = m[1];

				var confirmMsg = '<?php echo esc_js( __( 'Delete this novel AND all of its chapters? This cannot be undone.', 'wp-genius' ) ); ?>';

				var doDelete = function() { w2pNovelDelete.start(novelId); };

				if (typeof w2p !== 'undefined' && typeof w2p.confirm === 'function') {
					w2p.confirm(confirmMsg, doDelete);
				} else if (confirm(confirmMsg)) {
					doDelete();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Trash all chapters linked to a novel when the novel itself is trashed.
	 *
	 * @param int $post_id Post ID being trashed.
	 * @return void
	 */
	public function cascade_trash_chapters( $post_id ) {
		if ( 'novel' !== get_post_type( $post_id ) ) {
			return;
		}

		// Long-running cascade for large novels.
		set_time_limit( 0 );

		while ( true ) {
			$ids = $this->get_novel_chapter_ids( $post_id, 100 );
			if ( empty( $ids ) ) {
				break;
			}
			foreach ( $ids as $chapter_id ) {
				wp_trash_post( $chapter_id );
			}
		}
	}

	/**
	 * Permanently delete all chapters linked to a novel when the novel is permanently deleted.
	 *
	 * Deletion runs in small batches (always taking the first N matching rows) so very
	 * large novels do not time out in a single request.
	 *
	 * @param int $post_id Post ID being deleted.
	 * @return void
	 */
	public function cascade_delete_chapters( $post_id ) {
		if ( 'novel' !== get_post_type( $post_id ) ) {
			return;
		}

		// Long-running cascade for large novels.
		set_time_limit( 0 );

		while ( true ) {
			$ids = $this->get_novel_chapter_ids( $post_id, 100 );
			if ( empty( $ids ) ) {
				break;
			}
			foreach ( $ids as $chapter_id ) {
				wp_delete_post( $chapter_id, true );
			}
		}
	}

	/**
	 * Get the IDs of chapters linked to a novel via the related_novel_id meta key.
	 *
	 * With a limit this returns the FIRST matching rows (deletion then re-queries, so
	 * there is no OFFSET drift). Without a limit it returns every linked chapter.
	 * Direct SQL keeps memory low even for novels with a very large number of chapters.
	 *
	 * @param int $novel_id Novel post ID.
	 * @param int $limit    Maximum number of IDs to fetch (0 = all).
	 * @return int[] Chapter post IDs.
	 */
	private function get_novel_chapter_ids( $novel_id, $limit = 0 ) {
		global $wpdb;

		$sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d";
		if ( $limit > 0 ) {
			$sql .= ' LIMIT ' . (int) $limit;
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				$sql,
				'related_novel_id',
				$novel_id
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Count the chapters linked to a novel via the related_novel_id meta key.
	 *
	 * @param int $novel_id Novel post ID.
	 * @return int
	 */
	private function count_novel_chapters( $novel_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d",
				'related_novel_id',
				$novel_id
			)
		);
	}

	/**
	 * AJAX: delete the next batch of chapters for a novel (progress-driven cascade).
	 *
	 * Each call deletes the first batch of matching chapters so large novels can be
	 * removed across multiple requests without hitting execution timeouts.
	 *
	 * @return void
	 */
	public function ajax_novel_delete_batch() {
		// Large novels take many batches; never let PHP's execution time cap kill a batch mid-delete.
		set_time_limit( 0 );

		check_ajax_referer( 'w2p_novel_manager_delete', 'nonce' );

		$novel_id = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) ) {
			wp_send_json_error( 'Invalid novel' );
		}

		if ( ! current_user_can( 'delete_post', $novel_id ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$total         = $this->count_novel_chapters( $novel_id );
		$batch         = 50;
		$chapter_ids   = $this->get_novel_chapter_ids( $novel_id, $batch );
		$batch_deleted = 0;

		foreach ( $chapter_ids as $chapter_id ) {
			if ( wp_delete_post( $chapter_id, true ) ) {
				++$batch_deleted;
			}
		}

		wp_send_json_success(
			array(
				'total'         => $total,
				'batch_deleted' => $batch_deleted,
				'done'          => $batch_deleted < $batch,
			)
		);
	}

	/**
	 * AJAX: finalize — permanently delete the novel after all chapters are gone.
	 *
	 * @return void
	 */
	public function ajax_novel_delete_final() {
		// Deleting the novel itself may still trigger a last residual-chapter sweep.
		set_time_limit( 0 );

		check_ajax_referer( 'w2p_novel_manager_delete', 'nonce' );

		$novel_id = isset( $_POST['novel_id'] ) ? absint( $_POST['novel_id'] ) : 0;
		if ( ! $novel_id || 'novel' !== get_post_type( $novel_id ) ) {
			wp_send_json_error( 'Invalid novel' );
		}

		if ( ! current_user_can( 'delete_post', $novel_id ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		// Defense in depth: sweep any chapters that survived the batch loop before removing the novel itself.
		while ( true ) {
			$ids = $this->get_novel_chapter_ids( $novel_id, 50 );
			if ( empty( $ids ) ) {
				break;
			}
			foreach ( $ids as $chapter_id ) {
				wp_delete_post( $chapter_id, true );
			}
		}

		$deleted = wp_delete_post( $novel_id, true );
		if ( ! $deleted ) {
			wp_send_json_error( __( 'Failed to delete the novel', 'wp-genius' ) );
		}

		wp_send_json_success();
	}


	public function register_settings() {
		// Register settings for Word to Posts module (if needed for future expansion)
		register_setting(
			'word2posts_modules',
			'w2p_word_publish_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $settings as $key => $value ) {
			$sanitized[ sanitize_key( $key ) ] = sanitize_text_field( $value );
		}

		return $sanitized;
	}

	/**
	 * Handle DOCX file upload and conversion
	 */
	public function handle_upload() {
		// Verify nonce
		if ( ! isset( $_POST['word_to_posts_upload_nonce'] ) ||
			! wp_verify_nonce( $_POST['word_to_posts_upload_nonce'], 'word_to_posts_upload' ) ) {
			wp_die( esc_html__( 'Security check failed', 'wp-genius' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action', 'wp-genius' ) );
		}

		// Delegate to the main WordToPosts class for actual processing
		if ( class_exists( 'WordToPosts' ) ) {
			$word_to_posts = new WordToPosts();
			$word_to_posts->handleFileUpload();
		}

		wp_safe_redirect( admin_url( 'tools.php?page=wp-genius-settings#tab=novel-manager' ) );
		exit;
	}

	/**
	 * Handle scan uploads directory
	 */
	public function handle_scan() {
		if ( ! isset( $_POST['word_to_posts_scan_nonce'] ) ||
			! wp_verify_nonce( $_POST['word_to_posts_scan_nonce'], 'word_to_posts_scan' ) ) {
			wp_die( esc_html__( 'Security check failed', 'wp-genius' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action', 'wp-genius' ) );
		}

		if ( class_exists( 'WordToPosts' ) ) {
			$word_to_posts = new WordToPosts();
			$word_to_posts->scanUploads();
		}

		wp_safe_redirect( admin_url( 'tools.php?page=wp-genius-settings#tab=novel-manager' ) );
		exit;
	}

	/**
	 * Handle clean uploads directory
	 */
	public function handle_clean() {
		if ( ! isset( $_POST['word_to_posts_clean_nonce'] ) ||
			! wp_verify_nonce( $_POST['word_to_posts_clean_nonce'], 'word_to_posts_clean' ) ) {
			wp_die( esc_html__( 'Security check failed', 'wp-genius' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action', 'wp-genius' ) );
		}

		if ( class_exists( 'WordToPosts' ) ) {
			$word_to_posts = new WordToPosts();
			$word_to_posts->cleanUploads();
		}

		wp_safe_redirect( admin_url( 'tools.php?page=wp-genius-settings#tab=novel-manager' ) );
		exit;
	}

	/**
	 * Handle fix chapter index (Wrapper)
	 */
	public function handle_fix_chapter_index() {
		// This is primarily for the form submission which we handle via AJAX in class-word-to-posts.php 'fixChapterIndex'
		// But if someone hits the admin-post URL directly (without AJAX), we should handle it or redirect.
		// Actually, the class-word-to-posts.php registers the SAME hook 'admin_post_fix_chapter_index'.
		// To avoid double execution or conflict, we should rely on the class logic mostly.
		// However, since we are moving towards module.php handling hooks, let's delegate.

		// Check if it's an AJAX request (the class handles that).
		// If not, it's a direct POST.

		if ( class_exists( 'WordToPosts' ) ) {
			$word_to_posts = new WordToPosts();
			$word_to_posts->fixChapterIndex();
		}
		// Since fixChapterIndex returns JSON, we should probably exit here if not handled by it?
		// fixChapterIndex() sends json success/error.
		exit;
	}

	public function handle_fix_chapter_index_save_config() {
		// Legacy action: fix-index configuration is now managed by CSF settings.
		// Keep the endpoint secure and report the migration instead of a fatal call
		// to a removed method.
		check_ajax_referer( 'fix_chapter_index', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Fix Index settings are now managed in the WP Genius settings page.', 'wp-genius' ) ) );
	}

	public function handle_fix_chapter_index_init() {
		if ( class_exists( 'WordToPosts' ) ) {
			$word_to_posts = new WordToPosts();
			$word_to_posts->fixChapterIndexInit();
		}
		exit;
	}

	public function handle_fix_chapter_index_process() {
		// Legacy action without a backing method — refuse securely.
		check_ajax_referer( 'fix_chapter_index', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-genius' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'This action is no longer used. Use the batch fix-index tool instead.', 'wp-genius' ) ) );
	}

	public function inject_bulk_action_script() {
		$screen = get_current_screen();
		global $typenow;
		if ( ( $screen && $screen->id === 'edit-chapter' ) || $typenow === 'chapter' ) {
			?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Ensure the button exists in the select
				var bulkSelects = $('select[name="action"], select[name="action2"]');
				bulkSelects.each(function() {
					if ($(this).find('option[value="fix_chapter_index"]').length === 0) {
						$(this).append('<option value="fix_chapter_index"><?php esc_attr_e( 'Auto Identify (Fix Index)', 'wp-genius' ); ?></option>');
					}
				});

				// Handle Apply Click
				$('#doaction, #doaction2').on('click', function(e) {
					var selectId = $(this).attr('id') === 'doaction' ? 'action' : 'action2';
					var action = $('select[name="' + selectId + '"]').val();

					if (action === 'fix_chapter_index') {
						e.preventDefault();
						
						var selected = [];
						$('input[name="post[]"]:checked').each(function() {
							selected.push($(this).val());
						});

						if (selected.length === 0) {
							alert('<?php _e( 'Please select at least one chapter.', 'wp-genius' ); // phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction -- Static i18n embedded in JS, no user input. ?>');
							return;
						}
						if (!confirm('<?php _e( 'Are you sure you want to auto-identify indexes for specified chapters?', 'wp-genius' ); // phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction -- Static i18n embedded in JS, no user input. ?>')) {
							return;
						}

						// Use admin-ajax.php wrapper basically
						var data = {
							action: 'fix_chapter_index_init', // We reuse logic but might need custom handling for selection
							// Wait, logic supports post_ids? Yes, fixChapterIndexInit reads post_ids if passed?
							// Let's check fixChapterIndexInit... it does NOT read post_ids?
							// I need to update fixChapterIndexInit to support post_ids if I want to reuse it.
							// OR I use the old `handle_fix_chapter_index` which was synchronous?
							// The user wants batch processing.
							// If I use post_ids, count is small usually.
							// Let's just use the Init logic updated to accept post_ids.
							post_ids: selected,
							word_to_posts_fix_index_nonce: '<?php echo wp_create_nonce( 'word_to_posts_fix_index' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce embedded in JS, not user input. ?>'
						};
						
						// We need a JS function to handle the batch flow UI... 
						// But we are on edit.php, no UI for progress bar!
						// This is tricky.
						// Ideally we should open a modal or redirect to settings page with IDs?
						// Or just run it silently/alert?
						// Since `editor.php` bulk action usually reloads, maybe just use legacy synchronous for small batches?
						// But user asked for batch.
						// Let's keep it simple: Use legacy synchronous loop via admin-admin.php or AJAX loop but show alert progress?
						// "Batch Error" report might be from settings page.
						// Bulk action just needs to work.
						// Let's use `admin-post.php` action `fix_chapter_index` which exists and calls `fixChapterIndex` (synchronous).
						// I will update module.php to ensure `fixChapterIndex` (old one) still works?
						// No, I replaced it.
						// So I MUST use the new batch logic.
						// I will simple trigger a one-shot AJAX call that processes ALL selected (if small).
						// If large, it might timeout. Bulk selection is usually < 200.
						// So I will make a special AJAX call to `fix_chapter_index_process` with ALL IDs?
						// `fixChapterIndexProcess` takes offsets.
						// I will update `fixChapterIndexProcess` to handle `post_ids` explicitly if passed.
					}
				});
			});
			</script>
			<?php
		}
	}

	public function activate() {
		// Activation logic if needed
		do_action( 'w2p_word_publish_activated' );
	}

	public function deactivate() {
		// Deactivation logic if needed
		do_action( 'w2p_word_publish_deactivated' );
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'wp-genius-settings' ) === false ) {
			return;
		}

		$module_url = plugin_dir_url( __FILE__ );

		wp_enqueue_script(
			'word-to-posts-js',
			$module_url . 'assets/js/word-to-posts.js',
			array( 'jquery' ),
			W2P_VERSION,
			true
		);

		// Enqueue Fix Chapter Index script
		wp_enqueue_script(
			'fix-chapter-index-js',
			$module_url . 'assets/js/fix-chapter-index.js',
			array( 'jquery' ),
			W2P_VERSION,
			true
		);

		wp_localize_script(
			'word-to-posts-js',
			'word_to_posts_params',
			array(
				'starting_import' => __( 'Starting to import and publish chapters...', 'wp-genius' ),
				'cleaning'        => __( 'Cleaning uploads folder...', 'wp-genius' ),
				'scanning'        => __( 'Scanning uploads folder...', 'wp-genius' ),
				'error'           => __( 'Tips', 'wp-genius' ),
			)
		);
	}
}

?>
<?php

// Legacy aliases for backward compatibility (pre-rename class names).
if ( ! class_exists( 'WordToPostModule', false ) ) {
	class_alias( 'W2P_NovelManagerModule', 'WordToPostModule' );
}
if ( ! class_exists( 'NovelManagerModule', false ) ) {
	class_alias( 'W2P_NovelManagerModule', 'NovelManagerModule' );
}
