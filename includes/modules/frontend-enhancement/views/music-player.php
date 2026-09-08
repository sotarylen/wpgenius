<?php
/**
 * Music player template (used by the [wpg_playlist] shortcode).
 *
 * Expected vars: $tracks, $missing_tracks, $mode, $order, $playlist_title,
 * $track_count.
 *
 * APlayer clears its container's children on init, so the header and the
 * missing-track list live OUTSIDE the player container, inside the card shell.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_card = ( 'card' === $mode );
?><div class="wpg-music-card<?php echo $is_card ? '' : ' wpg-music-playlist-mini'; ?>">
	<?php if ( $is_card && ! empty( $playlist_title ) ) : ?>
		<div class="wpg-music-playlist__header">
			<h3 class="wpg-music-playlist__title"><?php echo esc_html( $playlist_title ); ?></h3>
			<span class="wpg-music-playlist__meta"><?php echo esc_html( (string) $track_count ); ?> <?php esc_html_e( 'tracks', 'wp-genius' ); ?></span>
		</div>
	<?php endif; ?>

	<div class="wpg-music-playlist" data-wpg-mode="<?php echo esc_attr( $mode ); ?>" data-wpg-order="<?php echo esc_attr( $order ); ?>" data-wpg-config="<?php echo esc_attr( wp_json_encode( $tracks, JSON_UNESCAPED_UNICODE ) ); ?>"></div>

	<?php if ( ! empty( $missing_tracks ) ) : ?>
		<ul class="wpg-music-playlist__missing">
			<?php foreach ( $missing_tracks as $track ) : ?>
				<li class="wpg-music-playlist__missing-item">
					<span class="wpg-music-playlist__missing-name"><?php echo esc_html( $track['name'] ); ?></span>
					<span class="wpg-music-playlist__missing-note"><?php esc_html_e( 'Audio file is missing.', 'wp-genius' ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>