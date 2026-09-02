<?php
/**
 * Bulk & Auto Actor Detection Progress Modal View
 * Consistent with WP-Genius Smart-AUI Design System
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="w2p-actor-backdrop" class="w2p-hidden">
	<div id="w2p-actor-progress-container">
		<!-- Header -->
		<div class="w2p-actor-header">
			<h3><i class="fa-solid fa-users-viewfinder"></i> <span id="w2p-actor-modal-title"><?php esc_html_e( 'Identify Actor', 'wp-genius' ); ?></span></h3>
			<button id="w2p-actor-close-btn" type="button" class="dashicons dashicons-no-alt"></button>
		</div>

		<!-- Progress Bar -->
		<div class="w2p-actor-progress-bar">
			<div class="w2p-actor-progress-fill"></div>
		</div>

		<!-- Status Text -->
		<div class="w2p-actor-status-bar">
			<span class="w2p-actor-status-text"><?php esc_html_e( 'Preparing...', 'wp-genius' ); ?></span>
		</div>

		<!-- Main Body -->
		<div class="w2p-actor-body">
			<!-- Overall Stats -->
			<div class="w2p-actor-stats-row">
				<div class="stat-item total">
					<span class="label"><?php esc_html_e( 'Total Posts', 'wp-genius' ); ?></span>
					<span id="w2p-actor-total" class="value">0</span>
				</div>
				<div class="stat-item success">
					<span class="label"><?php esc_html_e( 'Successful', 'wp-genius' ); ?></span>
					<span id="w2p-actor-success" class="value">0</span>
				</div>
				<div class="stat-item skipped">
					<span class="label"><?php esc_html_e( 'Skipped', 'wp-genius' ); ?></span>
					<span id="w2p-actor-skipped" class="value">0</span>
				</div>
				<div class="stat-item failed">
					<span class="label"><?php esc_html_e( 'Failed', 'wp-genius' ); ?></span>
					<span id="w2p-actor-failed" class="value">0</span>
				</div>
				<div class="stat-item threads">
					<span class="label"><?php esc_html_e( 'Threads', 'wp-genius' ); ?></span>
					<span class="value"><span id="w2p-actor-active-threads">0</span>/<span id="w2p-actor-threads">2</span></span>
				</div>
			</div>

			<!-- Log / Preview Area -->
			<div id="w2p-actor-preview-area" class="w2p-actor-preview-area">
				<ul id="w2p-actor-log-list" class="w2p-actor-log-list"></ul>
			</div>
		</div>

		<!-- Footer -->
		<div class="w2p-actor-footer">
			<button id="w2p-actor-done-btn" type="button" class="w2p-btn w2p-btn-primary w2p-hidden"><i class="fa-solid fa-rotate-right"></i><?php esc_html_e( 'Done & Refresh', 'wp-genius' ); ?></button>
			<button id="w2p-actor-cancel-btn" type="button" class="w2p-btn w2p-btn-stop"><i class="fa-solid fa-xmark"></i><?php esc_html_e( 'Stop', 'wp-genius' ); ?></button>
		</div>
	</div>
</div>
