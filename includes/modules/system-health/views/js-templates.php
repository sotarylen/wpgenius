<?php
/**
 * JS Templates for System Health Module
 *
 * @package WP_Genius
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- Template: System Info Grid -->
<template id="w2p-system-info-template">
	<div class="w2p-flex-1">
		<div class="w2p-section">
			<div class="w2p-section-header">
				<h4></h4><!-- Section Title -->
			</div>
			<div class="w2p-section-body">
				<div class="w2p-info-grid">
					<!-- Rows will be appended here -->
				</div>
			</div>
		</div>
	</div>
</template>

<!-- Template: System Info Row -->
<template id="w2p-system-info-row-template">
	<div class="w2p-info-row">
		<div class="w2p-info-label"></div>
		<div class="w2p-info-value"></div>
	</div>
</template>

<!-- Template: Image Link Scan Result Row -->
<template id="w2p-image-link-row-template">
	<tr>
		<td class="post-id"></td>
		<td class="post-title"></td>
		<td class="status-cell"><span class="status-badge pending">Pending</span></td>
	</tr>
</template>

<!-- Template: Duplicate Group (Container) -->
<template id="w2p-duplicate-group-template">
	<div class="w2p-duplicate-group">
		<div class="w2p-duplicate-group-header">
			<h4 class="group-title"></h4>
			<button type="button" class="w2p-btn w2p-btn-secondary w2p-clean-group-btn" data-text-cleaning="Deleting...">
			<span class="fa-solid fa-trash"></span><?php esc_html_e( 'Clean Group', 'wp-genius' ); ?>
			</button>
		</div>
		<div class="w2p-log-container" style="margin: 10px;">
			<table class="wp-list-table fixed striped">
				<thead>
					<tr>
						<th width="30px"><input type="checkbox" disabled></th>
						<th width="80px"><?php esc_html_e( 'ID', 'wp-genius' ); ?></th>
						<th width="30%"><?php esc_html_e( 'Title', 'wp-genius' ); ?></th>
						<th width="40%"><?php esc_html_e( 'Slug', 'wp-genius' ); ?></th>
						<th width="15%"><?php esc_html_e( 'Date', 'wp-genius' ); ?></th>
						<th width="10%"><?php esc_html_e( 'Action', 'wp-genius' ); ?></th>
					</tr>
				</thead>
				<tbody class="duplicate-posts-body"></tbody>
			</table>
		</div>
	</div>
</template>

<!-- Template: Duplicate Post Row -->
<template id="w2p-duplicate-post-template">
	<tr class="duplicate-post-row">
		<td class="check-column">
			<input type="checkbox" class="w2p-duplicate-checkbox">
		</td>
		<td class="post-id"></td>
		<td class="post-title"><strong></strong></td>
		<td class="post-slug"><code></code></td>
		<td class="post-date"></td>
		<td class="post-status">
			<span class="status-badge status-keep"><span class="fa-solid fa-check"></span><?php esc_html_e( 'Keep', 'wp-genius' ); ?></span>
			<span class="status-badge status-delete"><span class="fa-solid fa-trash-can"></span><?php esc_html_e( 'Delete', 'wp-genius' ); ?></span>
		</td>
	</tr>
</template>

<!-- Template: Duplicate Empty -->
<template id="w2p-duplicate-empty-template">
	<div class="w2p-notice w2p-notice-success">
		<p><span class="fa-solid fa-check-circle"></span><?php esc_html_e( 'Great! No duplicate posts found.', 'wp-genius' ); ?></p>
	</div>
</template>


