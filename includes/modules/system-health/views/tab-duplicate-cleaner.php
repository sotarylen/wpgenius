<?php
/**
 * System Health — duplicate-cleaner Tab (CSF tabbed fragment)
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
						<p class="w2p-mb-sm"><?php esc_html_e( 'Scan and remove duplicate posts based on title and slug matching.', 'wp-genius' ); ?></p>
						<select id="w2p-duplicate-category" class="w2p-select w2p-min-w-200">
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
					<button type="button" id="w2p-duplicate-scan-btn" class="w2p-btn w2p-btn-primary" data-text-default="<?php esc_attr_e( 'Scan for Duplicates', 'wp-genius' ); ?>" data-text-scanning="<?php esc_attr_e( 'Scanning...', 'wp-genius' ); ?>">
						<span class="fa-solid fa-magnifying-glass"></span>
						<span class="btn-text"><?php esc_html_e( 'Scan for Duplicates', 'wp-genius' ); ?></span>
					</button>
				</div>

				<div id="w2p-duplicate-results-wrapper" class="w2p-hidden w2p-mt-md">
					<div id="w2p-duplicate-notice" class="w2p-notice w2p-hidden w2p-mb-sm"></div>
					<div class="w2p-info-box w2p-flex w2p-justify-between w2p-items-center">
						<div id="w2p-duplicate-status"></div>
						<div class="w2p-flex w2p-gap-sm">
							<button type="button" id="w2p-duplicate-clear-btn" class="w2p-btn w2p-btn-secondary">
								<span class="fa-solid fa-xmark"></span>
								<?php esc_html_e( 'Clear Selection', 'wp-genius' ); ?>
							</button>
							<button type="button" id="w2p-duplicate-clean-btn" class="w2p-btn w2p-btn-primary" data-text-default="<?php esc_attr_e( 'Clean All Selected', 'wp-genius' ); ?>" data-text-cleaning="<?php esc_attr_e( 'Cleaning...', 'wp-genius' ); ?>">
								<span class="fa-solid fa-trash"></span>
								<span class="btn-text"><?php esc_html_e( 'Clean All Selected', 'wp-genius' ); ?></span>
							</button>
						</div>
					</div>

					<div id="w2p-duplicate-groups" class="w2p-mt-sm">
						<!-- Duplicate groups will be rendered here -->
					</div>
				</div>
			</div>
		</div>
