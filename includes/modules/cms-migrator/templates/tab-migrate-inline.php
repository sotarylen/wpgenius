<?php
/**
 * CMS Migrator Inline Template
 *
 * @package WP_Genius
 * @subpackage Modules/CMSMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'w2p_cms_migrator_settings', array() );
$progress = get_option( 'w2p_cms_migration_progress', array() );

// Get registered post types for mapping dropdown.
$post_types = get_post_types(
	array(
		'public'   => true,
		'_builtin' => false,
	),
	'objects'
);
$all_types  = get_post_types( array( 'public' => true ), 'objects' );

// Content type definitions.
$content_types = array(
	'books'   => array(
		'label'   => __( 'Books', 'wp-genius' ),
		'icon'    => 'fa-solid fa-book',
		'default' => 'book',
	),
	'albums'  => array(
		'label'   => __( 'Albums', 'wp-genius' ),
		'icon'    => 'fa-solid fa-images',
		'default' => 'album',
	),
	'models'  => array(
		'label'   => __( 'Models', 'wp-genius' ),
		'icon'    => 'fa-solid fa-user',
		'default' => 'model',
	),
	'studios' => array(
		'label'   => __( 'Studios', 'wp-genius' ),
		'icon'    => 'fa-solid fa-building',
		'default' => 'studio',
	),
);

// Get taxonomies for genre mapping.
$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
?>
<div class="w2p-cms-migrator" id="w2p-cms-migrator-app">
	<div class="w2p-cms-migrator-tabs">
		<div class="w2p-cms-migrator-tab active" data-target="w2p-cms-tab-settings"><?php esc_html_e( 'Settings', 'wp-genius' ); ?></div>
		<div class="w2p-cms-migrator-tab" data-target="w2p-cms-tab-preview"><?php esc_html_e( 'Preview', 'wp-genius' ); ?></div>
		<div class="w2p-cms-migrator-tab" data-target="w2p-cms-tab-migrate"><?php esc_html_e( 'Migration', 'wp-genius' ); ?></div>
		<div class="w2p-cms-migrator-tab" data-target="w2p-cms-tab-log"><?php esc_html_e( 'Log', 'wp-genius' ); ?></div>
	</div>

	<div id="w2p-cms-status" class="w2p-cms-migrator-status w2p-cms-migrator-status-info" style="display: none;"></div>

	<!-- Settings Tab -->
	<div class="w2p-cms-migrator-tab-content active" id="w2p-cms-tab-settings">
		<div class="w2p-cms-migrator-card">
			<div class="w2p-cms-migrator-card-header">
				<h3><?php esc_html_e( 'Database Connection', 'wp-genius' ); ?></h3>
			</div>
			<div class="w2p-cms-migrator-card-body">
				<div class="w2p-cms-migrator-form-group">
					<label for="w2p-cms-db-host"><?php esc_html_e( 'Database Host', 'wp-genius' ); ?></label>
					<input type="text" id="w2p-cms-db-host" value="<?php echo esc_attr( $settings['db_host'] ?? '' ); ?>" placeholder="127.0.0.1">
				</div>
				<div class="w2p-cms-migrator-form-row">
					<div class="w2p-cms-migrator-form-group">
						<label for="w2p-cms-db-port"><?php esc_html_e( 'Port', 'wp-genius' ); ?></label>
						<input type="text" id="w2p-cms-db-port" value="<?php echo esc_attr( $settings['db_port'] ?? '3306' ); ?>" placeholder="3306">
					</div>
					<div class="w2p-cms-migrator-form-group">
						<label for="w2p-cms-db-name"><?php esc_html_e( 'Database Name', 'wp-genius' ); ?></label>
						<input type="text" id="w2p-cms-db-name" value="<?php echo esc_attr( $settings['db_name'] ?? '' ); ?>" placeholder="n8n">
					</div>
				</div>
				<div class="w2p-cms-migrator-form-row">
					<div class="w2p-cms-migrator-form-group">
						<label for="w2p-cms-db-user"><?php esc_html_e( 'Username', 'wp-genius' ); ?></label>
						<input type="text" id="w2p-cms-db-user" value="<?php echo esc_attr( $settings['db_user'] ?? '' ); ?>" placeholder="root">
					</div>
					<div class="w2p-cms-migrator-form-group">
						<label for="w2p-cms-db-pass"><?php esc_html_e( 'Password', 'wp-genius' ); ?></label>
						<input type="password" id="w2p-cms-db-pass" value="" placeholder="••••••••" autocomplete="new-password">
					</div>
				</div>
				<div class="w2p-cms-migrator-actions">
					<button type="button" class="w2p-cms-migrator-btn" id="w2p-cms-test-connection"><?php esc_html_e( 'Test Connection', 'wp-genius' ); ?></button>
					<button type="button" class="w2p-cms-migrator-btn w2p-cms-migrator-btn-primary" id="w2p-cms-save-settings"><?php esc_html_e( 'Save Settings', 'wp-genius' ); ?></button>
				</div>
			</div>
		</div>
	</div>

	<!-- Preview Tab -->
	<div class="w2p-cms-migrator-tab-content" id="w2p-cms-tab-preview">
		<div class="w2p-cms-migrator-card">
			<div class="w2p-cms-migrator-card-header">
				<h3><?php esc_html_e( 'Database Statistics', 'wp-genius' ); ?></h3>
			</div>
			<div class="w2p-cms-migrator-card-body">
				<button type="button" class="w2p-cms-migrator-btn" id="w2p-cms-get-stats"><?php esc_html_e( 'Refresh Stats', 'wp-genius' ); ?></button>
				<div class="w2p-cms-migrator-stats-grid" id="w2p-cms-stats-grid" style="display: none; margin-top: 15px;">
					<div class="w2p-cms-migrator-stat-card">
						<div class="w2p-cms-migrator-stat-number" id="w2p-cms-stat-books">0</div>
						<div class="w2p-cms-migrator-stat-label"><?php esc_html_e( 'Books', 'wp-genius' ); ?></div>
					</div>
					<div class="w2p-cms-migrator-stat-card">
						<div class="w2p-cms-migrator-stat-number" id="w2p-cms-stat-chapters">0</div>
						<div class="w2p-cms-migrator-stat-label"><?php esc_html_e( 'Chapters', 'wp-genius' ); ?></div>
					</div>
					<div class="w2p-cms-migrator-stat-card">
						<div class="w2p-cms-migrator-stat-number" id="w2p-cms-stat-albums">0</div>
						<div class="w2p-cms-migrator-stat-label"><?php esc_html_e( 'Albums', 'wp-genius' ); ?></div>
					</div>
					<div class="w2p-cms-migrator-stat-card">
						<div class="w2p-cms-migrator-stat-number" id="w2p-cms-stat-models">0</div>
						<div class="w2p-cms-migrator-stat-label"><?php esc_html_e( 'Models', 'wp-genius' ); ?></div>
					</div>
					<div class="w2p-cms-migrator-stat-card">
						<div class="w2p-cms-migrator-stat-number" id="w2p-cms-stat-studios">0</div>
						<div class="w2p-cms-migrator-stat-label"><?php esc_html_e( 'Studios', 'wp-genius' ); ?></div>
					</div>
					<div class="w2p-cms-migrator-stat-card">
						<div class="w2p-cms-migrator-stat-number" id="w2p-cms-stat-images">0</div>
						<div class="w2p-cms-migrator-stat-label"><?php esc_html_e( 'Images', 'wp-genius' ); ?></div>
					</div>
				</div>
			</div>
		</div>

		<div class="w2p-cms-migrator-card">
			<div class="w2p-cms-migrator-card-header">
				<h3><?php esc_html_e( 'Data Preview', 'wp-genius' ); ?></h3>
			</div>
			<div class="w2p-cms-migrator-card-body">
				<div class="w2p-cms-migrator-actions" style="margin-bottom: 15px;">
					<button type="button" class="w2p-cms-migrator-btn" id="w2p-cms-preview-books"><?php esc_html_e( 'Preview Books', 'wp-genius' ); ?></button>
					<button type="button" class="w2p-cms-migrator-btn" id="w2p-cms-preview-chapters"><?php esc_html_e( 'Preview Chapters', 'wp-genius' ); ?></button>
					<button type="button" class="w2p-cms-migrator-btn" id="w2p-cms-preview-albums"><?php esc_html_e( 'Preview Albums', 'wp-genius' ); ?></button>
				</div>
				<div id="w2p-cms-preview-container"></div>
			</div>
		</div>
	</div>

	<!-- Migration Tab -->
	<div class="w2p-cms-migrator-tab-content" id="w2p-cms-tab-migrate">
		<!-- Step 1: Select Content Types -->
		<div class="w2p-cms-migrator-card">
			<div class="w2p-cms-migrator-card-header">
				<h3><span class="w2p-cms-step-number">1</span> <?php esc_html_e( 'Select Content Types', 'wp-genius' ); ?></h3>
			</div>
			<div class="w2p-cms-migrator-card-body">
				<div id="w2p-cms-content-type-list">
					<?php foreach ( $content_types as $key => $ct ) : ?>
					<div class="w2p-cms-content-type-row" data-type="<?php echo esc_attr( $key ); ?>">
						<label class="w2p-cms-checkbox-label">
							<input type="checkbox" class="w2p-cms-type-checkbox" value="<?php echo esc_attr( $key ); ?>" data-count="0">
							<i class="<?php echo esc_attr( $ct['icon'] ); ?>"></i>
							<span class="w2p-cms-type-name"><?php echo esc_html( $ct['label'] ); ?></span>
							<span class="w2p-cms-type-count" data-count-id="w2p-cms-stat-<?php echo esc_attr( $key ); ?>">0</span>
						</label>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="w2p-cms-select-actions">
					<button type="button" class="w2p-cms-migrator-btn" id="w2p-cms-select-all"><?php esc_html_e( 'Select All', 'wp-genius' ); ?></button>
					<button type="button" class="w2p-cms-migrator-btn" id="w2p-cms-deselect-all"><?php esc_html_e( 'Deselect All', 'wp-genius' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Step 2: Map Post Types & Taxonomies -->
		<div class="w2p-cms-migrator-card">
			<div class="w2p-cms-migrator-card-header">
				<h3><span class="w2p-cms-step-number">2</span> <?php esc_html_e( 'Map Post Types & Taxonomies', 'wp-genius' ); ?></h3>
			</div>
			<div class="w2p-cms-migrator-card-body">
				<table class="w2p-cms-mapping-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'CMS Content Type', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'WordPress Post Type', 'wp-genius' ); ?></th>
							<th><?php esc_html_e( 'Genre Field → Taxonomy', 'wp-genius' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $content_types as $key => $ct ) : ?>
						<tr data-type="<?php echo esc_attr( $key ); ?>">
							<td>
								<i class="<?php echo esc_attr( $ct['icon'] ); ?>"></i>
								<?php echo esc_html( $ct['label'] ); ?>
							</td>
							<td>
								<select class="w2p-cms-mapping-post-type" data-type="<?php echo esc_attr( $key ); ?>">
									<?php foreach ( $all_types as $pt ) : ?>
									<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $pt->name, $ct['default'] ); ?>>
										<?php echo esc_html( $pt->labels->singular_name ); ?> (<?php echo esc_html( $pt->name ); ?>)
									</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select class="w2p-cms-mapping-taxonomy" data-type="<?php echo esc_attr( $key ); ?>">
									<option value=""><?php esc_html_e( '— No mapping —', 'wp-genius' ); ?></option>
									<?php foreach ( $taxonomies as $tax ) : ?>
									<option value="<?php echo esc_attr( $tax->name ); ?>" <?php selected( $tax->name, 'category' ); ?>>
										<?php echo esc_html( $tax->labels->singular_name ); ?> (<?php echo esc_html( $tax->name ); ?>)
									</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Genre field from CMS books will be mapped to the selected taxonomy as categories.', 'wp-genius' ); ?></p>
			</div>
		</div>

		<!-- Step 3: Execute Migration -->
		<div class="w2p-cms-migrator-card">
			<div class="w2p-cms-migrator-card-header">
				<h3><span class="w2p-cms-step-number">3</span> <?php esc_html_e( 'Execute Migration', 'wp-genius' ); ?></h3>
			</div>
			<div class="w2p-cms-migrator-card-body">
				<div class="w2p-cms-migrator-form-row">
					<div class="w2p-cms-migrator-form-group" style="margin-bottom: 0;">
						<label for="w2p-cms-batch-limit"><?php esc_html_e( 'Batch Limit (per type)', 'wp-genius' ); ?></label>
						<input type="number" id="w2p-cms-batch-limit" value="10" min="1" max="10000" style="max-width: 150px;">
						<p class="description" style="margin-top: 5px;"><?php esc_html_e( 'Max items to migrate per content type. Set to 0 for unlimited.', 'wp-genius' ); ?></p>
					</div>
				</div>

				<div class="w2p-cms-migrator-progress-bar" style="margin-top: 15px;">
					<div class="w2p-cms-migrator-progress-fill" id="w2p-cms-progress-fill" style="width: 0%;">0%</div>
				</div>

				<div id="w2p-cms-type-progress-list">
					<?php foreach ( $content_types as $key => $ct ) : ?>
					<div class="w2p-cms-type-progress-card" data-type="<?php echo esc_attr( $key ); ?>" style="display: none;">
						<div class="w2p-cms-type-progress-info">
							<i class="<?php echo esc_attr( $ct['icon'] ); ?>"></i>
							<span class="w2p-cms-type-progress-name"><?php echo esc_html( $ct['label'] ); ?></span>
							<span class="w2p-cms-type-progress-status" data-status-id="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Pending', 'wp-genius' ); ?></span>
						</div>
						<div class="w2p-cms-type-progress-bar">
							<div class="w2p-cms-type-progress-fill" data-fill-id="<?php echo esc_attr( $key ); ?>" style="width: 0%;"></div>
						</div>
						<div class="w2p-cms-type-progress-counts">
							<span data-current-id="<?php echo esc_attr( $key ); ?>">0</span> / <span data-total-id="<?php echo esc_attr( $key ); ?>">0</span>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<div class="w2p-cms-migrator-actions">
					<button type="button" class="w2p-cms-migrator-btn w2p-cms-migrator-btn-primary" id="w2p-cms-start-migration"><?php esc_html_e( 'Start Migration', 'wp-genius' ); ?></button>
					<button type="button" class="w2p-cms-migrator-btn w2p-cms-migrator-btn-danger" id="w2p-cms-stop-migration" disabled><?php esc_html_e( 'Stop Migration', 'wp-genius' ); ?></button>
					<button type="button" class="w2p-cms-migrator-btn w2p-cms-migrator-btn-danger" id="w2p-cms-rollback"><?php esc_html_e( 'Rollback', 'wp-genius' ); ?></button>
				</div>
			</div>
		</div>
	</div>

	<!-- Log Tab -->
	<div class="w2p-cms-migrator-tab-content" id="w2p-cms-tab-log">
		<div class="w2p-cms-migrator-card">
			<div class="w2p-cms-migrator-card-header">
				<h3><?php esc_html_e( 'Migration Log', 'wp-genius' ); ?></h3>
			</div>
			<div class="w2p-cms-migrator-card-body">
				<div class="w2p-cms-migrator-log" id="w2p-cms-migration-log">
					<?php
					if ( ! empty( $progress['log'] ) ) {
						foreach ( $progress['log'] as $entry ) {
							printf(
								'<div class="w2p-cms-migrator-log-entry %s">%s</div>',
								esc_attr( $entry['type'] ?? '' ),
								esc_html( $entry['message'] ?? '' )
							);
						}
					} else {
						echo '<div class="w2p-cms-migrator-log-entry">No log entries yet.</div>';
					}
					?>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	var w2pCMSSettings = <?php echo wp_json_encode( array_diff_key( $settings, array( 'db_pass' => true ) ) ); ?>;
	var w2pCMSContentTypes = <?php echo wp_json_encode( $content_types ); ?>;
</script>
