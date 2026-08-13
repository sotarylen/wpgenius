<div id="w2p-tab-upload" class="w2p-wrapper">
	<div class="w2p-section">
		<div class="w2p-section-body">
			<form id="word_to_posts_upload_form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="handle_upload">
				<?php wp_nonce_field( 'word_to_posts_upload', 'word_to_posts_upload_nonce' ); ?>
				
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="word_to_posts_category"><?php esc_html_e( 'Category', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<?php
						wp_dropdown_categories(
							array(
								'name'       => 'category',
								'hide_empty' => 0,
								'id'         => 'word_to_posts_category',
								'selected'   => 396,
								'class'      => 'w2p-input-medium',
							)
						);
						?>
					</div>
				</div>
				
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="word_to_posts_author"><?php esc_html_e( 'Author', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<?php
						wp_dropdown_users(
							array(
								'name'     => 'author',
								'id'       => 'word_to_posts_author',
								'selected' => 11,
								'class'    => 'w2p-input-medium',
							)
						);
						?>
					</div>
				</div>

				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="word_to_posts_tags"><?php esc_html_e( 'Tags', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<input type="text" name="tags" id="word_to_posts_tags" placeholder="<?php esc_html_e( 'Separate tags with commas', 'wp-genius' ); ?>" class="w2p-input-large">
					</div>
				</div>

				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="word_to_posts_cpt_type"><?php esc_html_e( 'Associate Post Type', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<select name="cpt_type" id="word_to_posts_cpt_type" class="w2p-input-medium">
							<?php
							$post_types = get_post_types( array( 'public' => true ), 'objects' );
							foreach ( $post_types as $w2p_pt ) :
								if ( $w2p_pt->name === 'attachment' ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $w2p_pt->name ); ?>"><?php echo esc_html( $w2p_pt->label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				
				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="word_to_posts_cpt_id"><?php esc_html_e( 'Associate Post ID', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<input type="number" name="cpt_id" id="word_to_posts_cpt_id" placeholder="<?php esc_html_e( 'Enter Post ID', 'wp-genius' ); ?>" class="w2p-input-medium" required>
					</div>
				</div>

				<div class="w2p-form-row">
					<div class="w2p-form-label">
						<label for="word_file"><?php esc_html_e( 'Word File (.docx)', 'wp-genius' ); ?></label>
					</div>
					<div class="w2p-form-control">
						<input type="file" name="word_file" id="word_file" accept=".docx,.doc" class="w2p-input-large">
						<p class="description"><?php esc_html_e( 'Select a .docx file to convert into posts/chapters.', 'wp-genius' ); ?></p>
					</div>
				</div>

				<div class="w2p-form-actions w2p-settings-actions">
					<button type="submit" class="w2p-btn w2p-btn-primary"><i class="fa-solid fa-file-word"></i> <?php esc_html_e( 'Upload and Begin Import', 'wp-genius' ); ?></button>
				</div>
			</form>
			<div id="word-to-posts-log-upload" class="word2postNotice w2p-info-box" style="margin-top: 15px; display: none;"></div>
		</div>
	</div>
</div>
