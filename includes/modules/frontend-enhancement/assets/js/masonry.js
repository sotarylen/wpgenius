/**
 * WP Genius Image Masonry
 * Shortest-column placement for runs of consecutive images.
 *
 * Walks the images in DOM order and drops each one into the currently shortest
 * column. That is the whole point: the top row reads 1,2,3 instead of the
 * 1,3,5 that CSS multi-columns produces, and mixed landscape/portrait images
 * still tile without row gaps. Re-runs once pending images load and on resize.
 *
 * @package WP_Genius
 * @subpackage Frontend_Enhancement
 */

(function () {
	'use strict';

	var GAP   = 12;
	var MIN_W = 180; // columns narrower than this get dropped (narrow screens)

	function layout( box ) {
		var want  = parseInt( box.style.getPropertyValue( '--w2p-cols' ), 10 ) || 3;
		var items = Array.prototype.slice.call( box.children );
		var avail = box.clientWidth;

		if ( ! items.length || avail < 1 ) {
			return;
		}

		var cols    = Math.min( want, Math.max( 1, Math.floor( ( avail + GAP ) / ( MIN_W + GAP ) ) ) );
		var colW    = ( avail - GAP * ( cols - 1 ) ) / cols;
		var heights = new Array( cols ).fill( 0 );

		items.forEach( function ( el ) {
			var col = 0;
			for ( var k = 1; k < cols; k++ ) {
				if ( heights[ k ] < heights[ col ] ) {
					col = k;
				}
			}
			// Width first: the image only reports its scaled height after the
			// column width has been applied.
			el.style.width = colW + 'px';
			el.style.left  = ( col * ( colW + GAP ) ) + 'px';
			el.style.top   = heights[ col ] + 'px';
			heights[ col ] += el.offsetHeight + GAP;
		} );

		box.style.height = ( Math.max.apply( null, heights ) - GAP ) + 'px';
	}

	var boxes = Array.prototype.slice.call( document.querySelectorAll( '.w2p-masonry' ) );

	if ( ! boxes.length ) {
		return;
	}

	boxes.forEach( function ( box ) {
		box.setAttribute( 'data-masonry', 'on' );

		// Images still in flight report no height yet; re-run once they land.
		var pending = Array.prototype.slice.call( box.querySelectorAll( 'img' ) ).filter( function ( img ) {
			return ! img.complete;
		} );
		var left = pending.length;

		pending.forEach( function ( img ) {
			[ 'load', 'error' ].forEach( function ( evt ) {
				img.addEventListener( evt, function () {
					if ( --left <= 0 ) {
						layout( box );
					}
				} );
			} );
		} );

		layout( box );
	} );

	// ponytail: debounced full re-layout; swap for a ResizeObserver only if
	// container widths ever change without the window resizing.
	var timer;
	window.addEventListener( 'resize', function () {
		clearTimeout( timer );
		timer = setTimeout( function () {
			boxes.forEach( layout );
		}, 150 );
	} );
})();
