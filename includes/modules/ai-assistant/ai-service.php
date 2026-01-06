<?php
/**
 * AI Assistant Service
 *
 * Handles API calls to OpenAI/Claude for content generation.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AiAssistantService {

    private $api_key;
    private $model;
    private $base_url;

    public function __construct() {
        $settings = get_option( 'w2p_ai_assistant_settings', [] );
        $this->api_key  = ! empty( $settings['api_key'] ) ? $settings['api_key'] : '';
        $this->model    = ! empty( $settings['model'] ) ? $settings['model'] : 'gpt-3.5-turbo';
        $this->base_url = ! empty( $settings['api_base'] ) ? $settings['api_base'] : 'https://api.openai.com/v1/chat/completions';
    }

    /**
     * Generate Excerpt from Content
     */
    public function generate_excerpt( $content ) {
        if ( ! $this->api_key ) return false;

        $prompt = "Summarize the following content into a concise WordPress excerpt (approx 50 words). Reply ONLY with the summary:\n\n" . wp_strip_all_tags( $content );
        
        return $this->call_ai( $prompt );
    }

    /**
     * Generate Tags from Content
     */
    public function generate_tags( $content ) {
        if ( ! $this->api_key ) return false;

        $prompt = "Extract up to 5 relevant tags (keywords) for the following content. Reply ONLY with the tags separated by commas:\n\n" . wp_strip_all_tags( $content );
        
        $response = $this->call_ai( $prompt );
        if ( $response ) {
            return array_map( 'trim', explode( ',', $response ) );
        }
        return false;
    }

    /**
     * Call AI API
     */
    private function call_ai( $prompt ) {
        $body = [
            'model' => $this->model,
            'messages' => [
                [ 'role' => 'user', 'content' => $prompt ]
            ],
            'temperature' => 0.7
        ];

        $args = [
            'body'    => json_encode( $body ),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'timeout' => 30,
        ];

        $response = wp_remote_post( $this->base_url, $args );

        if ( is_wp_error( $response ) ) {
            W2P_Logger::error( 'AI API call failed: ' . $response->get_error_message(), 'ai-assistant' );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! empty( $data['choices'][0]['message']['content'] ) ) {
            return trim( $data['choices'][0]['message']['content'] );
        }

        return false;
    }
}
