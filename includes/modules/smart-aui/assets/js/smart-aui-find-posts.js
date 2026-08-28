/**
 * Smart AUI — Find Posts Modal Extras (Attach Filter)
 *
 * Injects Type, ID Range, and Status filters into WordPress core find_posts dialog
 * and intercepts the search action.
 *
 * @package WP_Genius
 * @subpackage Modules/SmartAUI
 */
( function ( $ ) {
	'use strict';

	// Idempotence guard: Prevent duplicate injection.
	if ( window.w2pFindPostsInjected ) {
		return;
	}
	window.w2pFindPostsInjected = true;

	var params = window.w2pFindPostsParams || {};
	var w2pI18n = params.i18n || {
		searching: 'Searching...',
		no_items: 'No items found.',
		error: 'Request failed. Please try again.'
	};
	var html = params.html || '';

	function w2pInjectExtras() {
		var $search = $( '#find-posts .find-box-search' );
		if ( ! $search.length ) {
			return false;
		}
		if ( ! $search.next( '.w2p-find-posts-extras' ).length && html ) {
			$search.after( html );
		}
		// Remove findPosts.send click handler attached by media.js on #find-posts-search
		// so our capturing event listener takes over the search logic.
		$( '#find-posts-search' ).off( 'click' );
		return true;
	}

	// MutationObserver fallback if modal is injected dynamically.
	$( function () {
		if ( ! w2pInjectExtras() ) {
			var target = document.getElementById( 'find-posts' ) || document.body;
			var obs = new MutationObserver( function () {
				if ( w2pInjectExtras() ) {
					obs.disconnect();
				}
			} );
			obs.observe( target, { childList: true, subtree: true } );
		}
	} );

	// Intercept Search button click (#find-posts-search); leave Select (#find-posts-submit) untouched.
	document.addEventListener( 'click', function ( e ) {
		var target = e.target;
		if ( ! target || target.id !== 'find-posts-search' ) {
			return;
		}
		var $box = $( '#find-posts' );
		if ( ! $box.length ) {
			return;
		}

		e.preventDefault();
		e.stopImmediatePropagation();

		var nonce       = $box.find( 'input[name="_ajax_nonce"]' ).val() || '';
		var ps          = $box.find( '#find-posts-input' ).val() || '';
		var foundAction = $box.find( 'input[name="found_action"]' ).val() || '';
		var affected    = $box.find( '#affected' ).val() || '';

		var data = {
			action:       'find_posts',
			_ajax_nonce:  nonce,
			ps:           ps,
			found_action: foundAction,
			affected:     affected
		};

		data['ac_post_type']       = $box.find( 'select[name="ac_post_type"]' ).val() || 'all';
		data['ac_post_id_range']   = $box.find( 'input[name="ac_post_id_range"]' ).val() || '';
		data['ac_post_status']     = $box.find( 'select[name="ac_post_status"]' ).val() || 'any';
		data['ac_exclude_chapter'] = $box.find( 'input[name="ac_exclude_chapter"]' ).val() || '1';

		var $resp    = $( '#find-posts-response' );
		var $spinner = $box.find( '.spinner' );
		$spinner.addClass( 'is-active' );
		$resp.html( '<p>' + w2pI18n.searching + '</p>' );

		$.ajax( ajaxurl, {
			type: 'POST',
			data: data,
			dataType: 'json'
		} ).always( function () {
			$spinner.removeClass( 'is-active' );
		} ).done( function ( x ) {
			if ( ! x || ! x.success ) {
				$resp.html( '<div class="error"><p>' + w2pI18n.no_items + '</p></div>' );
				return;
			}
			$resp.html( x.data );
		} ).fail( function () {
			$resp.html( '<div class="error"><p>' + w2pI18n.error + '</p></div>' );
		} );
	}, true );
} )( jQuery );
