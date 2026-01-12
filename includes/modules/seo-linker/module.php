<?php
/**
 * SEO & Internal Linker Module
 *
 * Automates internal linking and generates Table of Contents.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SeoLinkerModule extends W2P_Abstract_Module {
    
    public static function id() {
        return 'seo-linker';
    }

    public static function name() {
        return __( 'SEO & Internal Linker', 'wp-genius' );
    }

    public static function description() {
        return __( 'Automate internal linking with keywords and generate a Table of Contents for posts.', 'wp-genius' );
    }

    public static function icon() {
        return 'fa-solid fa-link';
    }

    public function init() {
        require_once __DIR__ . '/linker-service.php';
        
        // Hooks for Content Processing
        add_filter( 'the_content', [ $this, 'process_content' ], 20 );
        
        // Shortcode for TOC
        add_shortcode( 'w2p_toc', [ $this, 'render_toc_shortcode' ] );

        $this->register_settings();
        
        // Asset loading
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts( $hook ) {
        // Assets are now handled globally
    }

    public function register_settings() {
        register_setting( 'w2p_seo_linker_settings', 'w2p_seo_linker_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => [
                'linker_enabled' => true,
                'keywords'       => [],
                'toc_enabled'    => true,
                'toc_threshold'  => 3,
                'toc_depth'      => 3,
                'toc_auto_insert'=> true,
            ]
        ] );
    }

    public function sanitize_settings( $input ) {
        $output = [];
        $output['linker_enabled']  = ! empty( $input['linker_enabled'] );
        $output['keywords']        = isset( $input['keywords'] ) && is_array( $input['keywords'] ) ? $input['keywords'] : [];
        $output['toc_enabled']     = ! empty( $input['toc_enabled'] );
        $output['toc_threshold']   = isset( $input['toc_threshold'] ) ? absint( $input['toc_threshold'] ) : 3;
        $output['toc_depth']       = isset( $input['toc_depth'] ) ? absint( $input['toc_depth'] ) : 3;
        $output['toc_auto_insert'] = ! empty( $input['toc_auto_insert'] );
        return $output;
    }

    /**
     * Process Content (Links & TOC)
     */
    public function process_content( $content ) {
        if ( ! is_main_query() || ! is_singular() ) {
            return $content;
        }

        $settings = get_option( 'w2p_seo_linker_settings', [] );
        $service = new SeoLinkerService();

        // 1. Process Internal Links
        if ( ! empty( $settings['linker_enabled'] ) && ! empty( $settings['keywords'] ) ) {
            $content = $service->apply_internal_links( $content, $settings['keywords'] );
        }

        // 2. Process TOC
        if ( ! empty( $settings['toc_enabled'] ) && ! empty( $settings['toc_auto_insert'] ) && ! has_shortcode( $content, 'w2p_toc' ) ) {
            $toc = $service->generate_toc( $content, $settings['toc_threshold'], $settings['toc_depth'] );
            if ( $toc ) {
                $content = $toc . $content;
            }
        }

        return $content;
    }

    /**
     * Render TOC Shortcode
     */
    public function render_toc_shortcode( $atts ) {
        $settings = get_option( 'w2p_seo_linker_settings', [] );
        $service = new SeoLinkerService();
        
        // Need to get the raw content here
        global $post;
        if ( ! $post ) return '';

        return $service->generate_toc( $post->post_content, $settings['toc_threshold'], $settings['toc_depth'] );
    }

    public function render_settings() {
        $this->render_view( 'settings' );
    }

    public function activate() {}
    public function deactivate() {}
}
