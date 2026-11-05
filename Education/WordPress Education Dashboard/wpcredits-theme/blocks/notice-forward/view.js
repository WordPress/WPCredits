/**
 * The Notice template's forward: it counts down and takes the reader to the homepage, unless they stay.
 *
 * blocks/notice-forward/render.php prints the line with its count, a "Go now" link and a "Stay on this
 * page" button, an empty polite live region, and on the wrapper the homepage's address (`data-home`), the
 * line for each second from 1 up (`data-counts`, translated there) and the stopped line (`data-stayed`).
 * This script:
 *
 * - starts at once, at the last count, and tells the live region that first line, once;
 * - each second shows the next count in the line, which is not live, so a screen reader is not told every
 *   second;
 * - after the last second takes the reader to the homepage, once;
 * - on Stay stops the count for good, puts the stopped line in the line's place and tells the live region;
 *   where the button had the focus, the line takes it, so the focus is not lost with the button;
 * - when the browser shows the page again from its back-forward cache, as on Back from the homepage, stops
 *   the count and shows the stopped line, where the page would keep its last count with no timer behind it.
 *
 * A page without the forward, and a forward whose markup is not whole, are left alone.
 */
( function () {
	'use strict';

	var box = document.querySelector( '.wpc-notice-forward' );

	if ( ! box ) {
		return;
	}

	var line = box.querySelector( '.wpc-notice-forward__line' );
	var count = box.querySelector( '.wpc-notice-forward__count' );
	var said = box.querySelector( '.wpc-notice-forward__said' );
	var stay = box.querySelector( '.wpc-notice-forward__stay' );
	var home = box.getAttribute( 'data-home' );
	var stayed = box.getAttribute( 'data-stayed' );
	var counts;

	try {
		counts = JSON.parse( box.getAttribute( 'data-counts' ) || '[]' );
	} catch ( error ) {
		return;
	}

	if ( ! line || ! count || ! said || ! stay || ! home || ! stayed || ! Array.isArray( counts ) || ! counts.length ) {
		return;
	}

	var left = counts.length;
	var timer = null;

	function stop() {
		if ( null !== timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	}

	count.textContent = counts[ left - 1 ];
	said.textContent = counts[ left - 1 ];

	timer = window.setInterval( function () {
		left -= 1;

		if ( left < 1 ) {
			stop();
			window.location.assign( home );

			return;
		}

		count.textContent = counts[ left - 1 ];
	}, 1000 );

	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) {
			stop();
			line.textContent = stayed;
		}
	} );

	stay.addEventListener( 'click', function () {
		var focused = document.activeElement === stay;

		stop();
		line.textContent = stayed;
		said.textContent = stayed;

		if ( focused ) {
			line.setAttribute( 'tabindex', '-1' );
			line.focus();
		}
	} );
}() );
