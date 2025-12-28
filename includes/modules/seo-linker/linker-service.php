<?php
/**
 * SEO Linker Service
 *
 * Handles regex replacement for keywords and header parsing for TOC.
 *
 * @package WP_Genius
 * @subpackage Modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SeoLinkerService {

    /**
     * Apply Internal Links
     */
    public function apply_internal_links( $content, $keywords ) {
        if ( empty( $keywords ) ) return $content;

        // Sort by length descending to match longest phrases first
        usort( $keywords, function( $a, $b ) {
            return strlen( $b['keyword'] ) - strlen( $a['keyword'] );
        });

        foreach ( $keywords as $item ) {
            if ( empty( $item['keyword'] ) || empty( $item['url'] ) ) continue;

            $keyword = preg_quote( $item['keyword'], '/' );
            $url     = esc_url( $item['url'] );
            $title   = ! empty( $item['title'] ) ? esc_attr( $item['title'] ) : '';

            // Regex: match keyword but not inside tags (e.g., <a ...>keyword</a> or <img alt="keyword">)
            // This is a simplified approach; real HTML parsing is safer but more expensive.
            $pattern = '/(?!(?:[^<]+>|[^>]+<\/a>))\b(' . $keyword . ')\b/iu';
            
            $content = preg_replace( 
                $pattern, 
                '<a href="' . $url . '" class="w2p-internal-link" title="' . $title . '">$1</a>', 
                $content, 
                1 // Only replace once per post
            );
        }

        return $content;
    }

    /**
     * Generate Table of Contents
     */
    public function generate_toc( $content, $threshold = 3, $depth = 3 ) {
        // Extract headers
        $pattern = '/<h([2-' . intval( $depth + 1 ) . '])([^>]*)>(.*?)<\/h\1>/i';
        if ( ! preg_match_all( $pattern, $content, $matches ) ) {
            return '';
        }

        if ( count( $matches[0] ) < $threshold ) {
            return '';
        }

        $toc_html = '<div class="w2p-toc-container">';
        $toc_html .= '<h4 class="w2p-toc-title">' . __( 'Table of Contents', 'wp-genius' ) . '</h4>';
        $toc_html .= '<ul class="w2p-toc-list">';

        foreach ( $matches[3] as $index => $title ) {
            $clean_title = strip_tags( $title );
            $anchor = sanitize_title( $clean_title ) . '-' . $index;
            
            // Note: We'd need to modify the content to add these IDs to the headers
            // For now, this generates a simple list
            $toc_html .= '<li class="w2p-toc-item w2p-toc-level-' . $matches[1][$index] . '">';
            $toc_html .= '<a href="#' . $anchor . '">' . $clean_title . '</a>';
            $toc_html .= '</li>';
        }

        $toc_html .= '</ul></div>';

        return $toc_html;
    }
}
