<?php
/**
 * AI Content Assistant Module
 *
 * Automates editorial tasks using AI.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AiAssistantModule extends W2P_Abstract_Module {
    
    public static function id() {
        return 'ai-assistant';
    }

    public static function name() {
        return __( 'AI Content Assistant', 'wp-genius' );
    }

    public static function description() {
        return __( 'Use AI to automatically generate excerpts, tags, and image alt text.', 'wp-genius' );
    }

    public static function icon() {
        return 'fa-solid fa-wand-magic-sparkles';
    }

    public function init() {
        require_once __DIR__ . '/ai-service.php';

        // AJAX handlers for manual triggers
        add_action( 'wp_ajax_w2p_ai_generate_excerpt', [ $this, 'ajax_generate_excerpt' ] );
        add_action( 'wp_ajax_w2p_ai_generate_tags', [ $this, 'ajax_generate_tags' ] );

        // Admin scripts for buttons
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // Meta Box for manual triggers
        add_action( 'add_meta_boxes', [ $this, 'add_ai_meta_box' ] );
        
        // Settings registration
        $this->register_settings();
    }

    public function add_ai_meta_box() {
        add_meta_box(
            'w2p-ai-assistant-mb',
            __( 'AI Content Assistant', 'wp-genius' ),
            [ $this, 'render_ai_meta_box' ],
            [ 'post', 'page' ],
            'side'
        );
    }

    public function render_ai_meta_box( $post ) {
        ?>
        <div class="w2p-ai-meta-box">
            <p>
                <button type="button" id="w2p-ai-btn-excerpt" class="button w2p-ai-action" data-action="excerpt">
                    <?php esc_html_e( 'Generate AI Excerpt', 'wp-genius' ); ?>
                </button>
            </p>
            <p>
                <button type="button" id="w2p-ai-btn-tags" class="button w2p-ai-action" data-action="tags">
                    <?php esc_html_e( 'Suggest AI Tags', 'wp-genius' ); ?>
                </button>
            </p>
            <div id="w2p-ai-mb-status" style="display:none; margin-top:10px; font-style:italic;"></div>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting( 'w2p_ai_assistant_settings', 'w2p_ai_assistant_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => [
                'api_key'      => '',
                'api_base'     => 'https://api.openai.com/v1/chat/completions',
                'model'        => 'gpt-3.5-turbo',
                'auto_excerpt' => false,
                'auto_tags'    => false,
            ]
        ] );
    }

    public function sanitize_settings( $input ) {
        $output = [];
        $output['api_key']      = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
        $output['api_base']     = isset( $input['api_base'] ) ? esc_url_raw( $input['api_base'] ) : 'https://api.openai.com/v1/chat/completions';
        $output['model']        = isset( $input['model'] ) ? sanitize_key( $input['model'] ) : 'gpt-3.5-turbo';
        $output['auto_excerpt'] = ! empty( $input['auto_excerpt'] );
        $output['auto_tags']    = ! empty( $input['auto_tags'] );
        return $output;
    }

    /**
     * Enqueue Admin Scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
            return;
        }

        $plugin_url = plugin_dir_url( WP_GENIUS_FILE );
        wp_register_script( 'w2p-ai-assistant', $plugin_url . "assets/js/modules/ai-assistant.js", array( 'w2p-core-js' ), '1.0.0', true );

        wp_enqueue_script( 'w2p-ai-assistant' );
        wp_localize_script( 'w2p-ai-assistant', 'w2pAiParams', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'w2p_ai_nonce' ),
        ] );
    }

    /**
     * AJAX: Generate Excerpt
     */
    public function ajax_generate_excerpt() {
        check_ajax_referer( 'w2p_ai_nonce', 'nonce' );
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        if ( empty( $content ) ) {
            wp_send_json_error( 'No content provided' );
        }

        $service = new AiAssistantService();
        $excerpt = $service->generate_excerpt( $content );

        if ( $excerpt ) {
            wp_send_json_success( $excerpt );
        } else {
            wp_send_json_error( 'AI call failed' );
        }
    }

    /**
     * AJAX: Generate Tags
     */
    public function ajax_generate_tags() {
        check_ajax_referer( 'w2p_ai_nonce', 'nonce' );
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        if ( empty( $content ) ) {
            wp_send_json_error( 'No content provided' );
        }

        $service = new AiAssistantService();
        $tags = $service->generate_tags( $content );

        if ( $tags ) {
            wp_send_json_success( $tags );
        } else {
            wp_send_json_error( 'AI call failed' );
        }
    }

    public function render_settings() {
        $this->render_view( 'settings' );
    }

    public function activate() {}
    public function deactivate() {}
}
