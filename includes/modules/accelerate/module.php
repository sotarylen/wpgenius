<?php
/**
 * Accelerate Module
 *
 * Merges functionality from Cleanup WordPress and Update Behavior modules.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccelerateModule extends W2P_Abstract_Module {
    
    public static function id() {
        return 'accelerate';
    }

    public static function name() {
        return __( 'Accelerate', 'wp-genius' );
    }

    public static function description() {
        return __( 'Optimize WordPress performance by cleaning up admin interface and controlling update behaviors.', 'wp-genius' );
    }

    public function __construct() {
        $this->migrate_settings();
    }

    private function migrate_settings() {
        $transient_key = 'w2p_accelerate_migration_completed';
        if ( get_transient( $transient_key ) ) {
            return;
        }

        // 1. Migrate Module Activation Status
        $modules_enabled = get_option( 'word2posts_modules', [] );
        $old_modules = ['cleanup-wordpress', 'update-behavior'];
        $should_enable = false;

        foreach ($old_modules as $old_id) {
            if ( !empty($modules_enabled[$old_id]) ) {
                $should_enable = true;
                unset($modules_enabled[$old_id]); // Clean up old entry
            }
        }

        if ( $should_enable && empty($modules_enabled[self::id()]) ) {
            $modules_enabled[self::id()] = true;
            update_option( 'word2posts_modules', $modules_enabled );
        }

        // 2. Migrate Options
        $new_settings_key = 'w2p_accelerate_settings';
        $current_settings = get_option( $new_settings_key, [] );
        
        if ( empty($current_settings) ) {
            $cleanup_settings = get_option('w2p_cleanup_settings', []);
            $update_settings = get_option('w2p_update_behavior_settings', []);
            
            // Merge defaults just in case, but usually we just merge what we have
            $merged_settings = array_merge( $cleanup_settings, $update_settings );
            
            if ( !empty($merged_settings) ) {
                update_option( $new_settings_key, $merged_settings );
            }
        }
        
        set_transient( $transient_key, true, MONTH_IN_SECONDS );
    }

    public function init() {
        $this->register_settings();

        // Cleanup Functionality Hooks
        add_action( 'wp_before_admin_bar_render', [ $this, 'clean_admin_bar' ] );
        add_action( 'wp_dashboard_setup', [ $this, 'clean_dashboard_widgets' ], 999 );
        
        // Date Dropdown Optimization
        add_filter( 'disable_months_dropdown', [ $this, 'should_disable_months_dropdown' ], 10, 2 );
        add_filter( 'media_library_months_with_files', [ $this, 'disable_media_months' ] );
        add_filter( 'query', [ $this, 'intercept_date_query' ] );

        // Update Behavior Hooks
        add_action( 'init', array( $this, 'apply_update_behavior' ), 1 );
    }

    public function register_settings() {
        $defaults = [
            // Cleanup Defaults
            'remove_admin_bar_wp_logo'         => true,
            'remove_admin_bar_about'           => true,
            'remove_admin_bar_comments'        => true,
            'remove_admin_bar_new_content'     => true,
            'remove_admin_bar_search'          => true,
            'remove_admin_bar_updates'         => true,
            'remove_admin_bar_appearance'      => true,
            'remove_admin_bar_wporg'           => true,
            'remove_admin_bar_documentation'   => true,
            'remove_admin_bar_support_forums'  => true,
            'remove_admin_bar_feedback'        => true,
            'remove_admin_bar_view_site'       => true,
            'remove_dashboard_activity'        => true,
            'remove_dashboard_primary'         => false,
            'remove_dashboard_secondary'       => false,
            'remove_dashboard_site_health'     => false,
            'remove_dashboard_right_now'       => false,
            'remove_dashboard_quick_draft'     => true,
            'disable_months_dropdown'          => false,
            
            // Update Behavior Defaults
            'disable_auto_update_plugin' => true,
            'disable_auto_update_theme'  => true,
            'remove_wp_update_plugins'   => true,
            'remove_wp_update_themes'    => true,
            'remove_maybe_update_core'   => true,
            'remove_maybe_update_plugins'=> true,
            'remove_maybe_update_themes' => true,
            'block_external_http'      => false,
            'hide_plugin_notices'      => false,
            'block_acf_updates'        => false,
        ];

        $settings = get_option( 'w2p_accelerate_settings', [] );
        $settings = wp_parse_args( $settings, $defaults );
        update_option( 'w2p_accelerate_settings', $settings );
    }

    /**
     * Clean Admin Bar
     */
    public function clean_admin_bar() {
        if ( ! $this->is_module_enabled() ) {
            return;
        }

        global $wp_admin_bar;
        $settings = get_option( 'w2p_accelerate_settings', [] );

        $items_to_remove = [
            'remove_admin_bar_wp_logo'       => 'wp-logo',
            'remove_admin_bar_about'         => 'about',
            'remove_admin_bar_comments'      => 'comments',
            'remove_admin_bar_new_content'   => 'new-content',
            'remove_admin_bar_search'        => 'search',
            'remove_admin_bar_updates'       => 'updates',
            'remove_admin_bar_appearance'    => 'appearance',
            'remove_admin_bar_wporg'         => 'wporg',
            'remove_admin_bar_documentation' => 'documentation',
            'remove_admin_bar_support_forums' => 'support-forums',
            'remove_admin_bar_feedback'      => 'feedback',
            'remove_admin_bar_view_site'     => 'view-site',
        ];

        foreach ( $items_to_remove as $setting => $menu_item ) {
            if ( ! empty( $settings[ $setting ] ) ) {
                $wp_admin_bar->remove_menu( $menu_item );
            }
        }
    }

    /**
     * Clean Dashboard Widgets
     */
    public function clean_dashboard_widgets() {
        if ( ! $this->is_module_enabled() ) {
            return;
        }

        global $wp_meta_boxes;
        $settings = get_option( 'w2p_accelerate_settings', [] );

        $widgets_to_remove = [
            'remove_dashboard_primary'      => [ 'dashboard', 'side', 'core', 'dashboard_primary' ],
            'remove_dashboard_secondary'    => [ 'dashboard', 'side', 'core', 'dashboard_secondary' ],
            'remove_dashboard_site_health'  => [ 'dashboard', 'normal', 'core', 'dashboard_site_health' ],
            'remove_dashboard_right_now'    => [ 'dashboard', 'normal', 'core', 'dashboard_right_now' ],
            'remove_dashboard_quick_draft'  => [ 'dashboard', 'side', 'core', 'dashboard_quick_press' ],
            'remove_dashboard_activity'     => [ 'dashboard', 'normal', 'core', 'dashboard_activity' ],
        ];

        foreach ( $widgets_to_remove as $setting => $path ) {
            if ( ! empty( $settings[ $setting ] ) ) {
                if ( isset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] ) ) {
                    unset( $wp_meta_boxes[ $path[0] ][ $path[1] ][ $path[2] ][ $path[3] ] );
                }
            }
        }
    }

    /**
     * Should Disable Months Dropdown (Post List)
     * 
     * Returning true here prevents the SQL query entirely.
     */
    public function should_disable_months_dropdown( $disable, $post_type ) {
        if ( ! $this->is_module_enabled() ) {
            return $disable;
        }

        $settings = get_option( 'w2p_accelerate_settings', [] );
        if ( ! empty( $settings['disable_months_dropdown'] ) ) {
            return true;
        }

        return $disable;
    }

    /**
     * Disable Media Months UI
     */
    public function disable_media_months( $months ) {
        if ( ! $this->is_module_enabled() ) {
            return $months;
        }

        $settings = get_option( 'w2p_accelerate_settings', [] );
        if ( ! empty( $settings['disable_months_dropdown'] ) ) {
            return [];
        }

        return $months;
    }

    /**
     * Intercept and block date-based SELECT DISTINCT queries
     * 
     * This is a fallback for cases where there is no "disable" filter (like Grid Media Library).
     */
    public function intercept_date_query( $query ) {
        if ( ! is_admin() || ! $this->is_module_enabled() ) {
            return $query;
        }

        // Target the specific slow queries for years/months
        if ( strpos( $query, 'SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month' ) !== false ) {
            $settings = get_option( 'w2p_accelerate_settings', [] );
            if ( ! empty( $settings['disable_months_dropdown'] ) ) {
                // Return a valid query that result in 0 rows to satisfy the get_results call without hitting table indexes
                return "SELECT 1 FROM wp_posts WHERE 1=0";
            }
        }

        return $query;
    }

    /**
     * Apply Update Behaviors
     */
    public function apply_update_behavior() {
        if ( ! $this->is_module_enabled() ) {
            return;
        }

        $s = get_option( 'w2p_accelerate_settings', array() );

        if ( ! empty( $s['disable_auto_update_plugin'] ) ) {
            add_filter( 'auto_update_plugin', '__return_false' );
        }

        if ( ! empty( $s['disable_auto_update_theme'] ) ) {
            add_filter( 'auto_update_theme', '__return_false' );
        }

        if ( ! empty( $s['remove_wp_update_plugins'] ) ) {
            remove_action( 'wp_update_plugins', 'wp_update_plugins' );
        }

        if ( ! empty( $s['remove_wp_update_themes'] ) ) {
            remove_action( 'wp_update_themes', 'wp_update_themes' );
        }

        if ( ! empty( $s['remove_maybe_update_core'] ) ) {
            remove_action( 'admin_init', '_maybe_update_core' );
        }

        if ( ! empty( $s['remove_maybe_update_plugins'] ) ) {
            remove_action( 'admin_init', '_maybe_update_plugins' );
        }

        if ( ! empty( $s['remove_maybe_update_themes'] ) ) {
            remove_action( 'admin_init', '_maybe_update_themes' );
        }

        if ( ! empty( $s['block_external_http'] ) ) {
            if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
                define( 'WP_HTTP_BLOCK_EXTERNAL', true );
            }
        }

        if ( ! empty( $s['hide_plugin_notices'] ) ) {
            add_action('admin_head', array($this, 'hide_plugin_notices'));
        }

        if ( ! empty( $s['block_acf_updates'] ) ) {
            add_filter('http_request_args', array($this, 'block_acf_update_requests'), 10, 2);
        }
    }

    /**
     * Hide Plugin Notices
     *
     * @return void
     */
    public function hide_plugin_notices() {
        echo '<style>
            .otgs-is-not-registered, .otgs-notice, .update-message {
                display: none !important;
            }
        </style>';
    }

    /**
     * Block ACF Update Requests
     */
    public function block_acf_update_requests($r, $url) {
        $url_string = is_array($url) ? (isset($url['url']) ? $url['url'] : '') : $url;
        
        if (strpos($url_string, 'https://connect.advancedcustomfields.com/v2/plugins/update-check') !== false) {
            $r['blocked'] = true;
        }
        return $r;
    }

    private function is_module_enabled() {
        $modules = get_option( 'word2posts_modules', array() );
        return ! empty( $modules[ self::id() ] );
    }

    public function activate() {
        do_action( 'w2p_accelerate_activated' );
    }

    public function deactivate() {
        do_action( 'w2p_accelerate_deactivated' );
    }
}
