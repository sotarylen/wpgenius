/**
 * Actor Scanner: Settings & Gfriends Index Sync Controller
 *
 * @package WP_Genius
 * @subpackage Modules\ActorScanner
 */

( function ( $ ) {
	'use strict';

	var ActorScannerSettings = {
		init: function () {
			if ( ! window.w2pActorScanner ) {
				return;
			}

			$( document ).ready( function () {
				ActorScannerSettings.bindEvents();
			} );
		},

		bindEvents: function () {
			$( '#actor-refresh-gf-btn' ).on( 'click', ActorScannerSettings.handleRefreshGf );
		},

		handleRefreshGf: function ( e ) {
			e.preventDefault();
			var $btn = $( '#actor-refresh-gf-btn' );
			$btn.prop( 'disabled', true ).find( 'i' ).addClass( 'fa-spin' );

			$.ajax( {
				url: window.w2pActorScanner.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'w2p_actor_prepare_index',
					nonce: window.w2pActorScanner.nonce,
					force: 1,
				},
				success: function ( res ) {
					$btn.prop( 'disabled', false ).find( 'i' ).removeClass( 'fa-spin' );
					if ( res && res.success && res.data ) {
						$( '#actor-gf-status .w2p-status-label' ).html(
							'<i class="fa-solid fa-circle-check w2p-text-success"></i> ' +
							'Currently indexed official actors: ' + res.data.actors
						);
						alert( window.w2pActorScanner.i18n.indexReady || 'Gfriends index sync complete!' );
					}
				},
				error: function () {
					$btn.prop( 'disabled', false ).find( 'i' ).removeClass( 'fa-spin' );
					alert( 'Network error: Failed to refresh Gfriends index.' );
				},
			} );
		},
	};

	ActorScannerSettings.init();
} )( jQuery );
