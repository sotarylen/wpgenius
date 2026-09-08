<?php
/**
 * Platform embed template (used by the [wpg_music] shortcode).
 *
 * Expected vars: $embed_url, $fallback, $lx_link.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $embed_url ) ) {
	return;
}
?>
<div class="wpg-music-embed">
	<iframe
		class="wpg-music-embed__frame wpg-music-embed__frame--<?php echo esc_attr( $type ); ?>"
		src="<?php echo esc_url( $embed_url ); ?>"
		width="100%"
		height="<?php echo ( 'playlist' === $type ) ? esc_attr( '450' ) : esc_attr( '66' ); ?>"
		frameborder="no"
		border="0"
		marginwidth="0"
		marginheight="0"
		loading="lazy"
		title="<?php esc_attr_e( 'Embedded music player', 'wp-genius' ); ?>"
	></iframe>
	<div class="wpg-music-embed__footer">
		<?php if ( ! empty( $fallback['url'] ) ) : ?>
			<a class="wpg-music-embed__source" href="<?php echo esc_url( $fallback['url'] ); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo esc_html( $fallback['name'] ); ?>
			</a>
		<?php endif; ?>
		<?php if ( ! empty( $lx_link ) ) : ?>
			<a class="wpg-music-embed__lx" href="<?php echo esc_url( $lx_link ); ?>">
				<?php esc_html_e( 'Play in LX Music', 'wp-genius' ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>