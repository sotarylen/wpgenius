<?php
/**
 * System Health Cleanup Service
 *
 * Handles the database queries for optimization.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SystemHealthCleanupService {

    /**
     * Get Database Statistics
     */
    public function get_stats() {
        global $wpdb;

        $stats = [
            'revisions'     => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'" ),
            'auto_drafts'   => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'auto-draft'" ),
            'orphaned_meta' => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)" ),
            'transients'    => $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" ),
        ];

        return $stats;
    }

    /**
     * Clean Revisions
     */
    public function clean_revisions() {
        global $wpdb;
        $count = $wpdb->query( "DELETE FROM $wpdb->posts WHERE post_type = 'revision'" );
        return (int) $count;
    }

    /**
     * Clean Auto-Drafts
     */
    public function clean_auto_drafts() {
        global $wpdb;
        $count = $wpdb->query( "DELETE FROM $wpdb->posts WHERE post_status = 'auto-draft'" );
        return (int) $count;
    }

    /**
     * Clean Orphaned Meta
     */
    public function clean_orphaned_meta() {
        global $wpdb;
        $count = $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE post_id NOT IN (SELECT ID FROM $wpdb->posts)" );
        return (int) $count;
    }

    /**
     * Clean Transients
     */
    public function clean_transients() {
        global $wpdb;
        $count = $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
        return (int) $count;
    }
}
