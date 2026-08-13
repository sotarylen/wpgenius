<?php
/**
 * System Health Settings Template
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="w2p-sub-tabs" id="w2p-system-health-tabs">
	<div class="w2p-sub-tab-nav">
		<a class="w2p-sub-tab-link active" data-tab="cleanup"><i class="fa-solid fa-database"></i><?php esc_html_e( 'Cleanup Tools', 'wp-genius' ); ?></a>
		<a class="w2p-sub-tab-link" data-tab="image-remover"><i class="fa-solid fa-unlink"></i><?php esc_html_e( 'Image Link Remover', 'wp-genius' ); ?></a>
		<a class="w2p-sub-tab-link" data-tab="duplicate-cleaner"><i class="fa-solid fa-copy"></i><?php esc_html_e( 'Duplicate Post Clean', 'wp-genius' ); ?></a>

		<a class="w2p-sub-tab-link" data-tab="info"><i class="fa-solid fa-circle-info"></i><?php esc_html_e( 'System Info', 'wp-genius' ); ?></a>
	</div>

	<!-- Cleanup Tools Tab -->
	<div class="w2p-sub-tab-content active" id="w2p-tab-cleanup">
		<div class="w2p-section">
			<div class="w2p-section-body">
				<div class="w2p-info-box w2p-flex w2p-justify-between w2p-items-center">
					<p class="w2p-m-0"><?php esc_html_e( 'Keep your site fast by removing unnecessary data from your database. Click the scan button to check current system health.', 'wp-genius' ); ?></p>
					<button type="button" id="w2p-health-scan-btn" class="w2p-btn w2p-btn-primary">
						<span class="fa-solid fa-magnifying-glass"></span>
						<?php esc_html_e( 'Scan System Status', 'wp-genius' ); ?>
					</button>
				</div>

				<div class="w2p-health-grid">
					<?php if ( isset( $stats ) && is_array( $stats ) ) : ?>
						<!-- Post Revisions -->
						<div class="w2p-health-card" data-type="revisions">
							<div class="w2p-health-info">
								<span class="w2p-health-label"><?php esc_html_e( 'Post Revisions', 'wp-genius' ); ?></span>
								<span class="w2p-health-count"><?php echo esc_html( $stats['revisions'] ?? '-' ); ?></span>
							</div>
							<button class="w2p-btn w2p-btn-secondary w2p-health-action" data-action="revisions">
								<span class="fa-solid fa-trash"></span>
								<?php esc_html_e( 'Clean Revisions', 'wp-genius' ); ?>
							</button>
						</div>

						<!-- Auto Drafts -->
						<div class="w2p-health-card" data-type="auto_drafts">
							<div class="w2p-health-info">
								<span class="w2p-health-label"><?php esc_html_e( 'Auto Drafts', 'wp-genius' ); ?></span>
								<span class="w2p-health-count"><?php echo esc_html( $stats['auto_drafts'] ?? '-' ); ?></span>
							</div>
							<button class="w2p-btn w2p-btn-secondary w2p-health-action" data-action="auto_drafts">
								<span class="fa-solid fa-trash"></span>
								<?php esc_html_e( 'Clean Auto Drafts', 'wp-genius' ); ?>
							</button>
						</div>

						<!-- Orphaned Meta -->
						<div class="w2p-health-card" data-type="orphaned_meta">
							<div class="w2p-health-info">
								<span class="w2p-health-label"><?php esc_html_e( 'Orphaned Metadata', 'wp-genius' ); ?></span>
								<span class="w2p-health-count"><?php echo esc_html( $stats['orphaned_meta'] ?? '-' ); ?></span>
							</div>
							<button class="w2p-btn w2p-btn-secondary w2p-health-action" data-action="orphaned_meta">
								<span class="fa-solid fa-trash"></span>
								<?php esc_html_e( 'Clean Orphaned Meta', 'wp-genius' ); ?>
							</button>
						</div>

						<!-- Transients -->
						<div class="w2p-health-card" data-type="transients">
							<div class="w2p-health-info">
								<span class="w2p-health-label"><?php esc_html_e( 'Expired Transients', 'wp-genius' ); ?></span>
								<span class="w2p-health-count"><?php echo esc_html( $stats['transients'] ?? '-' ); ?></span>
							</div>
							<button class="w2p-btn w2p-btn-secondary w2p-health-action" data-action="transients">
								<span class="fa-solid fa-trash"></span>
								<?php esc_html_e( 'Clean Transients', 'wp-genius' ); ?>
							</button>
						</div>
					<?php endif; ?>

					<!-- Custom Field Cleaner -->
					<div class="w2p-health-card w2p-health-card-full" data-type="custom_field">
						<div class="w2p-health-info">
							<span class="w2p-health-label"><?php esc_html_e( 'Clean Custom Field Data', 'wp-genius' ); ?></span>
							<div class="w2p-health-desc"><?php esc_html_e( 'Delete all postmeta entries for a specific custom field key (e.g. ACF).', 'wp-genius' ); ?></div>
						</div>
						<div class="w2p-health-input-group">
							<input type="text" id="w2p-custom-meta-key" class="w2p-input" placeholder="<?php esc_attr_e( 'Enter custom field name...', 'wp-genius' ); ?>">
							<button class="w2p-btn w2p-btn-danger w2p-health-action" data-action="custom_field">
								<span class="fa-solid fa-eraser"></span>
								<?php esc_html_e( 'Clean Data', 'wp-genius' ); ?>
							</button>
						</div>
					</div>

				</div>

				<div id="w2p-health-message" class="w2p-notice w2p-hidden"></div>
			</div>
		</div>
	</div>

	<!-- Image Link Remover Tab -->
	<div class="w2p-sub-tab-content" id="w2p-tab-image-remover">
		<div class="w2p-section">
			<div class="w2p-section-body">
				<div class="w2p-info-box w2p-flex w2p-items-center w2p-gap-md">
					<div class="w2p-flex-1">
						<p><?php esc_html_e( 'Scan posts for images wrapped in links and remove the links while keeping the images.', 'wp-genius' ); ?></p>
						<select id="w2p-image-link-category" class="w2p-select w2p-min-w-200">
							<option value="0"><?php esc_html_e( 'All Categories', 'wp-genius' ); ?></option>
							<?php if ( isset( $categories ) && is_array( $categories ) ) : ?>
								<?php
								foreach ( $categories as $cat ) :
									$cat_id    = is_object( $cat ) ? ( $cat->term_id ?? 0 ) : ( is_array( $cat ) ? ( $cat['term_id'] ?? 0 ) : 0 );
									$cat_name  = is_object( $cat ) ? ( $cat->name ?? '' ) : ( is_array( $cat ) ? ( $cat['name'] ?? '' ) : '' );
									$cat_count = is_object( $cat ) ? ( $cat->count ?? 0 ) : ( is_array( $cat ) ? ( $cat['count'] ?? 0 ) : 0 );
									if ( ! $cat_id ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( $cat_id ); ?>"><?php echo esc_html( $cat_name ) . ' (' . esc_html( $cat_count ) . ')'; ?></option>
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
	<div class="w2p-sub-tab-content" id="w2p-tab-duplicate-cleaner">
		<div class="w2p-section">
			<div class="w2p-section-body">
				<div class="w2p-info-box w2p-flex w2p-items-center w2p-gap-md">
					<div class="w2p-flex-1">
						<p class="w2p-mb-sm"><?php esc_html_e( 'Scan and remove duplicate posts based on title and slug matching.', 'wp-genius' ); ?></p>
						<select id="w2p-duplicate-category" class="w2p-select w2p-min-w-200">
							<option value="0"><?php esc_html_e( 'All Categories', 'wp-genius' ); ?></option>
							<?php if ( isset( $categories ) && is_array( $categories ) ) : ?>
								<?php
								foreach ( $categories as $cat ) :
									$cat_id    = is_object( $cat ) ? ( $cat->term_id ?? 0 ) : ( is_array( $cat ) ? ( $cat['term_id'] ?? 0 ) : 0 );
									$cat_name  = is_object( $cat ) ? ( $cat->name ?? '' ) : ( is_array( $cat ) ? ( $cat['name'] ?? '' ) : '' );
									$cat_count = is_object( $cat ) ? ( $cat->count ?? 0 ) : ( is_array( $cat ) ? ( $cat['count'] ?? 0 ) : 0 );
									if ( ! $cat_id ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( $cat_id ); ?>"><?php echo esc_html( $cat_name ) . ' (' . esc_html( $cat_count ) . ')'; ?></option>
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
	</div>



	<!-- System Info Tab -->
	<div class="w2p-sub-tab-content" id="w2p-tab-info">
		<div id="w2p-health-info-container" class="w2p-loading-container">
			<div class="w2p-skeleton-loader">
				<div class="w2p-skeleton-row"></div>
				<div class="w2p-skeleton-row"></div>
				<div class="w2p-skeleton-row"></div>
			</div>
			<p class="w2p-text-center w2p-mt-md"><?php esc_html_e( 'Loading system information...', 'wp-genius' ); ?></p>
		</div>
	</div>
</div>
