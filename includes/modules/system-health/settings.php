<?php
/**
 * System Health Settings Panel
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$service = new SystemHealthCleanupService();
$stats = $service->get_stats();
?>

<div class="w2p-settings-panel w2p-system-health-settings">
    <h3><?php esc_html_e( 'System Health & Database Cleanup', 'wp-genius' ); ?></h3>
    <p class="description">
        <?php esc_html_e( 'Keep your site fast by removing unnecessary data from your database.', 'wp-genius' ); ?>
    </p>

    <div class="w2p-health-grid">
        <!-- Revisions -->
        <div class="w2p-health-card" data-type="revisions">
            <div class="w2p-health-info">
                <span class="w2p-health-label"><?php esc_html_e( 'Post Revisions', 'wp-genius' ); ?></span>
                <span class="w2p-health-count"><?php echo esc_html( $stats['revisions'] ); ?></span>
            </div>
            <button class="button w2p-health-action" data-action="revisions">
                <?php esc_html_e( 'Clean Revisions', 'wp-genius' ); ?>
            </button>
        </div>

        <!-- Auto Drafts -->
        <div class="w2p-health-card" data-type="auto_drafts">
            <div class="w2p-health-info">
                <span class="w2p-health-label"><?php esc_html_e( 'Auto Drafts', 'wp-genius' ); ?></span>
                <span class="w2p-health-count"><?php echo esc_html( $stats['auto_drafts'] ); ?></span>
            </div>
            <button class="button w2p-health-action" data-action="auto_drafts">
                <?php esc_html_e( 'Clean Auto Drafts', 'wp-genius' ); ?>
            </button>
        </div>

        <!-- Orphaned Meta -->
        <div class="w2p-health-card" data-type="orphaned_meta">
            <div class="w2p-health-info">
                <span class="w2p-health-label"><?php esc_html_e( 'Orphaned Metadata', 'wp-genius' ); ?></span>
                <span class="w2p-health-count"><?php echo esc_html( $stats['orphaned_meta'] ); ?></span>
            </div>
            <button class="button w2p-health-action" data-action="orphaned_meta">
                <?php esc_html_e( 'Clean Orphaned Meta', 'wp-genius' ); ?>
            </button>
        </div>

        <!-- Transients -->
        <div class="w2p-health-card" data-type="transients">
            <div class="w2p-health-info">
                <span class="w2p-health-label"><?php esc_html_e( 'Expired Transients', 'wp-genius' ); ?></span>
                <span class="w2p-health-count"><?php echo esc_html( $stats['transients'] ); ?></span>
            </div>
            <button class="button w2p-health-action" data-action="transients">
                <?php esc_html_e( 'Clean Transients', 'wp-genius' ); ?>
            </button>
        </div>
    </div>

    <div id="w2p-health-message" class="w2p-notice" style="display:none;"></div>
</div>

<style>
.w2p-health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.w2p-health-card {
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    padding: 20px;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: all 0.2s ease;
}

.w2p-health-card:hover {
    border-color: #0073aa;
    background: #fff;
}

.w2p-health-info {
    margin-bottom: 15px;
}

.w2p-health-label {
    display: block;
    font-size: 13px;
    color: #646970;
    margin-bottom: 5px;
}

.w2p-health-count {
    display: block;
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
}

.w2p-health-action {
    width: 100%;
}

.w2p-notice {
    margin-top: 20px;
    padding: 10px 15px;
    border-radius: 4px;
}
.w2p-notice-success {
    background-color: #edfaef;
    border-left: 4px solid #46b450;
    color: #2c3328;
}
.w2p-notice-error {
    background-color: #fcf0f1;
    border-left: 4px solid #d63638;
    color: #2c3328;
}
</style>
