<?php
/**
 * Image Masonry Handler Class
 *
 * Groups runs of consecutive <img> tags in post content into a true waterfall
 * (masonry) layout: the container uses CSS multi-columns, so each column
 * stacks its images independently and mixed landscape/portrait images never
 * leave large blank gaps (unlike a row-aligned grid). A single <img> is left
 * untouched and renders as-is.
 *
 * The grouping is markup-shape agnostic: it matches both bare <img> tags and
 * images wrapped in <p ...><img ...></p> (the shape used by migrated content),
 * while whitespace, <br> and empty <p> elements between images are treated as
 * mere separators. Real text paragraphs break the run, so only visually
 * consecutive gallery images are grouped.
 *
 * The column count is read from the "Images Per Row" setting
 * (masonry_columns, 1-6) instead of being hard-coded; the container receives
 * it as an inline columns rule.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Masonry Handler
 */
class WPG_Masonry_Handler {

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor
	 *
	 * @param array $settings Module settings.
	 */
	public function __construct( $settings = array() ) {
		$this->settings = $settings;

		if ( ! empty( $this->settings['masonry_enabled'] ) ) {
			add_filter( 'the_content', array( $this, 'apply_masonry_groups' ), 20 );
		}
	}

	/**
	 * Wrap runs of 2+ consecutive <img> tags in a masonry container.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function apply_masonry_groups( $content ) {
		if ( empty( $this->settings['masonry_enabled'] ) || false === strpos( $content, '<img' ) ) {
			return $content;
		}

		// One image "unit": an <img> tag, optionally wrapped in <a> and/or <p>.
		$unit = '(?:<p\b[^>]*>\s*)?(?:<a\b[^>]*>\s*)?<img\b[^>]*>(?:\s*</a>)?(?:\s*</p>)?';

		// Allowed content between two consecutive images: whitespace, <br>,
		// empty <p> (optionally holding a <br>). Anything else (text, other
		// blocks) breaks the run. Possessive quantifier keeps this fast.
		$sep = '(?>\s|&nbsp;|<br\s*/?>|<p\b[^>]*>\s*(?:<br\s*/?>)?\s*</p>)*+';

		$pattern = '~' . $unit . '(?:' . $sep . $unit . ')+~i';

		$columns = $this->get_columns();

		$result = preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( $columns ) {
				// Keep only the <img> (with its <a> wrapper when present),
				// drop <p> wrappers and separator noise from the masonry grid.
				if ( preg_match_all( '~<a\b[^>]*>\s*<img\b[^>]*>\s*</a>|<img\b[^>]*>~i', $matches[0], $units ) ) {
					// True waterfall: CSS multi-columns stack images per column,
					// so columns are height-independent (no row-alignment gaps).
					return '<div class="w2p-masonry" style="columns: ' . $columns . ';">' . implode(
						'
',
						$units[0]
					) . '</div>';
				}

				return $matches[0];
			},
			$content
		);

		return ( null === $result ) ? $content : $result;
	}

	/**
	 * Resolve the masonry column count from settings (1-6).
	 *
	 * @return int
	 */
	private function get_columns() {
		$columns = isset( $this->settings['masonry_columns'] ) ? absint( $this->settings['masonry_columns'] ) : 3;

		return min( 6, max( 1, $columns ) );
	}
}
