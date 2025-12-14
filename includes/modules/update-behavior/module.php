<?php
/**
 * Update Behavior Module
 *
 * Controls WordPress update-related behaviors via admin settings.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateBehaviorModule extends W2P_Abstract_Module {
    public static function id() { return 'update-behavior'; }
    public static function name() { return __( 'Update Behavior', 'wp-genius' ); }
    public static function description() { return __( 'Toggle automatic updates and core update hooks.', 'wp-genius' ); }

    public function init() {
        $this->register_settings();
        // Apply behavior early
        add_action( 'init', array( $this, 'apply_update_behavior' ), 1 );
    }

    public function register_settings() {
        $defaults = array(
            'disable_auto_update_plugin' => true,
            'disable_auto_update_theme'  => true,
            'remove_wp_update_plugins'   => true,
            'remove_wp_update_themes'    => true,
            'remove_maybe_update_core'   => true,
            'remove_maybe_update_plugins'=> true,
            'remove_maybe_update_themes' => true,
            'block_external_http'      => false,  // 危险级开关
            'hide_plugin_notices'      => false,  // 隐藏插件通知
            'block_acf_updates'        => false,  // 阻止ACF更新
        );

        $settings = get_option( 'w2p_update_behavior_settings', array() );
        $settings = wp_parse_args( $settings, $defaults );
        update_option( 'w2p_update_behavior_settings', $settings );
    }

    public function apply_update_behavior() {
        if ( ! $this->is_module_enabled() ) {
            return;
        }

        $s = get_option( 'w2p_update_behavior_settings', array() );

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

        // 危险级开关：阻止所有外部HTTP请求
        if ( ! empty( $s['block_external_http'] ) ) {
            define( 'WP_HTTP_BLOCK_EXTERNAL', true );
        }

        // 隐藏插件通知
        if ( ! empty( $s['hide_plugin_notices'] ) ) {
            add_action('admin_head', array($this, 'hide_plugin_notices'));
        }

        // 阻止ACF更新请求
        if ( ! empty( $s['block_acf_updates'] ) ) {
            add_filter('http_request_args', array($this, 'block_acf_update_requests'), 10, 2);
        }
    }

    private function is_module_enabled() {
        $modules = get_option( 'word2posts_modules', array() );
        return ! empty( $modules[ self::id() ] );
    }

    public function activate() { do_action( 'w2p_update_behavior_activated' ); }
    public function deactivate() { do_action( 'w2p_update_behavior_deactivated' ); }

    /**
     * 隐藏插件通知
     */
    public function hide_plugin_notices() {
        echo '<style>
            .otgs-is-not-registered, .otgs-notice, .update-message {
                display: none !important;
            }
        </style>';
    }

    /**
     * 阻止ACF更新请求
     */
    public function block_acf_update_requests($r, $url) {
        // 确保 $url 是字符串类型
        $url_string = is_array($url) ? (isset($url['url']) ? $url['url'] : '') : $url;
        
        if (strpos($url_string, 'https://connect.advancedcustomfields.com/v2/plugins/update-check') !== false) {
            $r['blocked'] = true;
        }
        return $r;
    }
}
