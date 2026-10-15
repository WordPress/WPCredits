/**
 * The program index's filter chips (theme 1.24.15).
 *
 * The server prints every card, shown, and the chips with `hidden`; this shows the chips, and on a
 * press marks the chip pressed, hides each card whose audiences the chip leaves out and says what it
 * shows in the status line, in the words the chip carries from the server. A link to a card the
 * filter hides, a pathway step's or the page's own address, presses All first, so the card it opens
 * is never hidden. Focus stays where the person put it; nothing is stored or written to the address.
 */
( function () {
	'use strict';

	function each( list, callback ) {
		Array.prototype.forEach.call( list, callback );
	}

	function init( root ) {
		var chips = root.querySelector( '.wpc-hub-chips' );
		var status = root.querySelector( '[role="status"]' );
		var cards = root.querySelectorAll( '.wpc-hub-program' );
		var buttons;
		var all;

		if ( ! chips || root.hasAttribute( 'data-wpc-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-wpc-ready', '' );
		buttons = chips.querySelectorAll( 'button[data-filter]' );
		all = chips.querySelector( 'button[data-filter="all"]' );

		function press( button ) {
			var filter = button.getAttribute( 'data-filter' );

			each( buttons, function ( other ) {
				other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' );
			} );

			each( cards, function ( card ) {
				var audiences = ( card.getAttribute( 'data-audiences' ) || '' ).split( ' ' );

				card.hidden = 'all' !== filter && -1 === audiences.indexOf( filter );
			} );

			if ( status ) {
				status.textContent = button.getAttribute( 'data-announce' ) || '';
			}
		}

		function reveal( hash ) {
			var target;

			if ( ! all || 'string' !== typeof hash || 0 !== hash.indexOf( '#program-' ) ) {
				return;
			}

			target = document.getElementById( hash.slice( 1 ) );

			if ( target && target.hidden && root.contains( target ) ) {
				press( all );
			}
		}

		each( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				press( button );
			} );
		} );

		// A link followed: before the browser jumps, so the card it jumps to is shown. Not when the link
		// opens somewhere else, in a new tab or window (a modifier key held) or by another script: the
		// page left behind keeps its filter.
		document.addEventListener( 'click', function ( event ) {
			var link;

			if ( event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) {
				return;
			}

			link = event.target && event.target.closest ? event.target.closest( 'a[href^="#program-"]' ) : null;

			if ( link ) {
				reveal( link.getAttribute( 'href' ) );
			}
		} );

		// An address changed to a card the filter hides: the browser's own jump found it hidden and cannot
		// be counted on to land, so once it is shown, go there. A card that was shown is the browser's to
		// jump to, as a link followed is, so that jump is not doubled.
		window.addEventListener( 'hashchange', function () {
			var target = document.getElementById( window.location.hash.slice( 1 ) );
			var wasHidden = !! target && target.hidden;

			reveal( window.location.hash );

			if ( wasHidden && ! target.hidden ) {
				target.scrollIntoView();
			}
		} );

		chips.hidden = false;

		// The server prints every card shown, so this acts only if a later change prints one hidden.
		reveal( window.location.hash );
	}

	function start() {
		each( document.querySelectorAll( '.wpc-hub-index' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
