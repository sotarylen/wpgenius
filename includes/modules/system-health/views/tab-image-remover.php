<?php
/**
 * System Health — image-remover Tab (CSF tabbed fragment)
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

		<div class="w2p-section">
			<div class="w2p-section-body">
				<div class="w2p-info-box w2p-flex w2p-items-center w2p-gap-md">
					<div class="w2p-flex-1">
						<p><?php esc_html_e( 'Scan posts for images wrapped in links and remove the links while keeping the images.', 'wp-genius' ); ?></p>
						<select id="w2p-image-link-category" class="w2p-select w2p-min-w-200">
							<option value="0"><?php esc_html_e( 'All Categories', 'wp-genius' ); ?></option>
							<?php if ( isset( $w2p_categories ) && is_array( $w2p_categories ) ) : ?>
								<?php
								foreach ( $w2p_categories as $w2p_cat ) :
									$w2p_cat_id = is_object( $w2p_cat ) ? ( $w2p_cat->term_id ?? 0 ) : ( is_array( $w2p_cat ) ? ( $w2p_cat['term_id'] ?? 0 ) : 0 );
									$cat_name   = is_object( $w2p_cat ) ? ( $w2p_cat->name ?? '' ) : ( is_array( $w2p_cat ) ? ( $w2p_cat['name'] ?? '' ) : '' );
									$cat_count  = is_object( $w2p_cat ) ? ( $w2p_cat->count ?? 0 ) : ( is_array( $w2p_cat ) ? ( $w2p_cat['count'] ?? 0 ) : 0 );
									if ( ! $w2p_cat_id ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( $w2p_cat_id ); ?>"><?php echo esc_html( $cat_name ) . ' (' . esc_html( $cat_count ) . ')'; ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</div>
					<button type="button" id="w2p-image-link-scan-btn" class="w2p-btn w2p-btn-primary">
						<span class="fa-solid fa-magnifying-glass"></span>
						<?php esc_html_e( 'Scan for Linked Images', 'wp-genius' ); ?>
					</button>
				</div>

				<div id="w2p-image-link-results-wrapper" class="w2p-hidden w2p-mt-lg">
					<div id="w2p-image-link-notice" class="w2p-notice w2p-hidden w2p-mb-md"></div>
					<div class="w2p-info-box w2p-flex w2p-justify-between w2p-items-center">
						<div id="w2p-image-link-status"></div>
						<div class="w2p-flex w2p-items-center w2p-gap-sm">
							<div class="w2p-flex w2p-items-center w2p-gap-xs">
								<label for="w2p-image-link-batch-size" class="w2p-text-secondary w2p-font-size-sm"><?php esc_html_e( 'Batch Size:', 'wp-genius' ); ?></label>
								<input type="number" id="w2p-image-link-batch-size" value="10" min="1" max="100" class="w2p-input-small w2p-w-60 w2p-h-30 w2p-px-8">
							</div>
							<button type="button" id="w2p-image-link-execute-btn" class="w2p-btn w2p-btn-primary">
								<span class="fa-solid fa-bolt"></span>
								<?php esc_html_e( 'Execute Removal', 'wp-genius' ); ?>
							</button>
							<button type="button" id="w2p-image-link-stop-btn" class="w2p-btn w2p-btn-stop w2p-hidden">
								<span class="fa-solid fa-hand"></span>
								<?php esc_html_e( 'Stop', 'wp-genius' ); ?>
							</button>
						</div>
					</div>

					<div class="w2p-log-container">
						<table class="wp-list-table fixed striped">
							<thead>
								<tr>
									<th width="100px"><?php esc_html_e( 'Post ID', 'wp-genius' ); ?></th>
									<th><?php esc_html_e( 'Title', 'wp-genius' ); ?></th>
									<th width="120px"><?php esc_html_e( 'Status', 'wp-genius' ); ?></th>
								</tr>
							</thead>
							<tbody id="w2p-image-link-items"></tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	
	<!-- Duplicate Post Cleaner Tab -->
