<?php
/**
 * Image Masonry Handler Class
 *
 * Groups runs of consecutive <img> tags in post content into a true waterfall
 * (masonry) layout: masonry.js places each image into the currently shortest
 * column, so the DOM order reads left-to-right (1,2,3 then 4,5,6) while mixed
 * landscape/portrait images still leave no large blank gaps. A single <img> is
 * left untouched and renders as-is.
 *
 * Two cheaper options were tried and rejected, do not go back to them:
 * - A row-aligned grid: the tallest image sets the row height, so short
 *   landscape images leave a large gap underneath. This is what the feature
 *   originally promised to avoid.
 * - CSS multi-columns (the previous implementation): fills column-major, so
 *   6 images render as 1,3,5 / 2,4,6 visually instead of 1,2,3 / 4,5,6. The
 *   fill direction is intrinsic to multi-column layout and no CSS fixes it.
 *   It is kept only as the no-JS fallback.
 *
 * The grouping is markup-shape agnostic: it matches both bare <img> tags and
 * images wrapped in <p ...><img ...></p> (the shape used by migrated content),
 * while whitespace, <br> and empty <p> elements between images are treated as
 * mere separators. Real text paragraphs break the run, so only visually
 * consecutive gallery images are grouped.
 *
 * The column count is read from the "Images Per Row" setting
 * (masonry_columns, 1-6) instead of being hard-coded; it is passed to the
 * script as an inline CSS variable, which the stylesheet also consumes for
 * the no-JS multi-column fallback.
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
					// --w2p-cols carries the column count to masonry.js; the
					// stylesheet also reads it for the no-JS multi-column fallback.
					return '<div class="w2p-masonry" style="--w2p-cols:' . $columns . '">' . implode(
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
