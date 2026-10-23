/**
 * The program index's filter chips (theme 1.24.15).
 *
 * The server prints every card, shown, and the chips with `hidden`; this shows the chips, and on a
 * press marks the chip pressed, hides each card whose audiences the chip leaves out and says what it
 * shows in the status line, in the words the chip carries from the server. A link to a card the
 * filter hides, a pathway step's or the page's own address, presses All first, so the card it opens
 * is never hidden. Focus stays where the person put it; nothing is stored or written to the address.
 *
 * Core's block visibility (theme 1.32.1) can hide a card at some sizes of the window, with the class
 * `wp-block-hidden-<size>` and a media query, so the server, which does not know the window, counts
 * it. The numbers the status line says count only the cards shown where the person is: each card with
 * such a class is asked whether the window shows it (its computed `display`, read with the filter's
 * own `hidden` put aside for the read), at the start and at each resize, and the line says the
 * sentence the chip carries as a format for that total (`data-announce-by-total`, the server's, its
 * noun in the plural of the total) filled with the two counts. A chip with no format says its sentence
 * as the server counted. After a resize the line is written again, once, only when a chip has been
 * pressed and what it says has changed.
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
		var pressed = null;
		var inView = [];

		if ( ! chips || root.hasAttribute( 'data-wpc-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-wpc-ready', '' );
		buttons = chips.querySelectorAll( 'button[data-filter]' );
		all = chips.querySelector( 'button[data-filter="all"]' );

		function audiencesOf( card ) {
			return ( card.getAttribute( 'data-audiences' ) || '' ).split( ' ' );
		}

		// Whether the window shows a card, whatever the filter says. Only a card core's block visibility
		// hides at some size carries its class; the others are shown. The filter hides a card with
		// `hidden`, which would answer for the window too, so the attribute is put aside for the read.
		function shownHere( card ) {
			var filtered = card.hidden;
			var shown;

			if ( ! /(^|\s)wp-block-hidden-/.test( card.className ) ) {
				return true;
			}

			if ( filtered ) {
				card.hidden = false;
			}

			shown = 'none' !== window.getComputedStyle( card ).display;

			if ( filtered ) {
				card.hidden = true;
			}

			return shown;
		}

		function measure() {
			each( cards, function ( card, at ) {
				inView[ at ] = shownHere( card );
			} );
		}

		// What a chip says when pressed: its sentence as a format for the total of the cards shown here,
		// filled with how many the chip shows and that total; the server's sentence, which counts every
		// card, where the chip has no format for the total.
		function sentence( button ) {
			var filter = button.getAttribute( 'data-filter' );
			var formats = null;
			var total = 0;
			var count = 0;
			var format;

			try {
				formats = JSON.parse( button.getAttribute( 'data-announce-by-total' ) || 'null' );
			} catch ( error ) {
				formats = null;
			}

			each( cards, function ( card, at ) {
				if ( inView[ at ] ) {
					total++;
					count += 'all' === filter || -1 !== audiencesOf( card ).indexOf( filter ) ? 1 : 0;
				}
			} );

			format = formats && 'object' === typeof formats ? formats[ String( total ) ] : null;

			if ( 'string' !== typeof format ) {
				return button.getAttribute( 'data-announce' ) || '';
			}

			return format.split( '%1$s' ).join( String( count ) ).split( '%2$s' ).join( String( total ) );
		}

		function press( button ) {
			var filter = button.getAttribute( 'data-filter' );

			pressed = button;

			each( buttons, function ( other ) {
				other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' );
			} );

			each( cards, function ( card ) {
				card.hidden = 'all' !== filter && -1 === audiencesOf( card ).indexOf( filter );
			} );

			if ( status ) {
				status.textContent = sentence( button );
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

		// The window made another size: the cards are counted again, and if a chip has been pressed the
		// line says what it now shows, when that is not what it said.
		window.addEventListener( 'resize', function () {
			var said;

			measure();

			if ( pressed && status ) {
				said = sentence( pressed );

				if ( status.textContent !== said ) {
					status.textContent = said;
				}
			}
		} );

		chips.hidden = false;
		measure();

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
