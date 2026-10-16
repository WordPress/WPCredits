/**
 * The pathway's switch and steps (theme 1.24.15; the detail's point at its step, 1.24.17).
 *
 * The server prints both ladders, every detail shown, each step an in-page link to its detail, and
 * the switch and the hint with `hidden`. This shows the switch and the hint, marks the block
 * interactive, turns each step's link into a button of the same content that says which detail it
 * controls and whether it is pressed, presses each ladder's opening step (its `data-start`) and
 * shows that detail alone, and shows the first ladder alone. A step pressed shows its detail and says
 * so, and brings the detail into view if it is out of sight, as it is below the steps on a phone; the
 * switch shows its ladder, the other keeping its own pressed step, and says the ladder's heading. An
 * address naming a detail, the page's own or a link followed, selects its ladder and its step, said
 * once and only if that changes what is shown, and one changed to a detail that was hidden is
 * scrolled to once the detail is shown. The detail shown points at its step: the middle of the step,
 * from the details' left edge, is set as --wpc-notch at each press, each ladder shown and each resize.
 * Every word said comes from the server; focus stays where the person put it; nothing is stored or
 * written to the address.
 */
( function () {
	'use strict';

	function each( list, callback ) {
		Array.prototype.forEach.call( list, callback );
	}

	function init( root ) {
		var switcher = root.querySelector( '.wpc-hub-switch' );
		var status = root.querySelector( '[role="status"]' );
		var ladders = root.querySelectorAll( '.wpc-hub-ladder' );
		var options;

		if ( ! switcher || ! ladders.length || root.hasAttribute( 'data-wpc-ready' ) ) {
			return;
		}

		root.setAttribute( 'data-wpc-ready', '' );
		options = switcher.querySelectorAll( 'button[aria-controls]' );

		function say( text ) {
			if ( status ) {
				status.textContent = text;
			}
		}

		function picks( ladder ) {
			return ladder.querySelectorAll( '.wpc-hub-stair__pick' );
		}

		// The detail shown points up at its step where the steps stand side by side (hub.css draws the
		// point from 900px): the middle of the pressed step, from the details' left edge, set on the
		// details as --wpc-notch. Nothing is set while the details have no width, as a hidden ladder's
		// have none; showing the ladder sets it then.
		function point( ladder ) {
			var details = ladder.querySelector( '.wpc-hub-details' );
			var pressed = ladder.querySelector( '.wpc-hub-stair__pick[aria-pressed="true"]' );
			var stair = pressed && pressed.closest ? pressed.closest( '.wpc-hub-stair' ) : null;
			var from;
			var to;

			if ( ! details || ! stair || ! details.style || ! details.getBoundingClientRect ) {
				return;
			}

			from = details.getBoundingClientRect();
			to = stair.getBoundingClientRect();

			if ( ! from.width ) {
				return;
			}

			details.style.setProperty( '--wpc-notch', Math.round( to.left + ( to.width / 2 ) - from.left ) + 'px' );
		}

		function select( ladder, pick, announce ) {
			var controls = pick.getAttribute( 'aria-controls' );

			each( picks( ladder ), function ( other ) {
				other.setAttribute( 'aria-pressed', other === pick ? 'true' : 'false' );
			} );

			each( ladder.querySelectorAll( '.wpc-hub-detail' ), function ( detail ) {
				detail.hidden = detail.id !== controls;
			} );

			point( ladder );

			if ( announce ) {
				say( pick.getAttribute( 'data-announce' ) || '' );
			}
		}

		// A step pressed on a phone: its detail lies below the whole column of steps, out of the window, so
		// the press would change nothing the person can see. Bring the detail into view when it is out of
		// sight, at once and with no options (a reader who asked for less motion gets none), its
		// scroll-margin keeping it under the sticky header. Where it is in sight already, as beside the
		// staircase on a wide screen, nothing moves. Focus stays on the button.
		function bring( detail ) {
			var box;

			if ( ! detail || ! detail.getBoundingClientRect ) {
				return;
			}

			box = detail.getBoundingClientRect();

			if ( box.top >= window.innerHeight || box.bottom <= 0 ) {
				detail.scrollIntoView();
			}
		}

		function show( ladder, announce ) {
			var heading = ladder.querySelector( 'h2' );

			each( ladders, function ( other ) {
				other.hidden = other !== ladder;
			} );

			each( options, function ( option ) {
				option.setAttribute( 'aria-pressed', option.getAttribute( 'aria-controls' ) === ladder.id ? 'true' : 'false' );
			} );

			point( ladder );

			if ( announce ) {
				say( heading ? heading.textContent.trim() : '' );
			}
		}

		// An address naming a detail of this block: its ladder shown and its step pressed.
		function follow( hash, announce ) {
			var detail;
			var ladder;
			var pick;
			var changed;

			if ( 'string' !== typeof hash || 0 !== hash.indexOf( '#wpc-hub-step-' ) ) {
				return null;
			}

			detail = document.getElementById( hash.slice( 1 ) );
			ladder = detail && detail.closest ? detail.closest( '.wpc-hub-ladder' ) : null;

			if ( ! ladder || ! root.contains( ladder ) ) {
				return null;
			}

			pick = ladder.querySelector( '.wpc-hub-stair__pick[aria-controls="' + detail.id + '"]' );

			if ( ! pick ) {
				return null;
			}

			// Said only if it changes what is shown: a link followed is said by its click, and the change of
			// address that follows finds the step pressed already.
			changed = ladder.hidden || 'true' !== pick.getAttribute( 'aria-pressed' );

			show( ladder, false );
			select( ladder, pick, announce && changed );

			return detail;
		}

		each( ladders, function ( ladder ) {
			var start = parseInt( ladder.getAttribute( 'data-start' ), 10 );
			var all;

			each( picks( ladder ), function ( link ) {
				var button = document.createElement( 'button' );

				button.type = 'button';
				button.className = link.className;
				button.setAttribute( 'aria-describedby', link.getAttribute( 'aria-describedby' ) || '' );
				button.setAttribute( 'aria-controls', ( link.getAttribute( 'href' ) || '#' ).slice( 1 ) );
				button.setAttribute( 'aria-pressed', 'false' );
				button.setAttribute( 'data-announce', link.getAttribute( 'data-announce' ) || '' );

				while ( link.firstChild ) {
					button.appendChild( link.firstChild );
				}

				link.parentNode.replaceChild( button, link );

				button.addEventListener( 'click', function () {
					select( ladder, button, true );
					bring( document.getElementById( button.getAttribute( 'aria-controls' ) ) );
				} );
			} );

			all = picks( ladder );

			if ( all.length ) {
				select( ladder, all[ start >= 1 && start <= all.length ? start - 1 : 0 ], false );
			}
		} );

		each( options, function ( option ) {
			option.addEventListener( 'click', function () {
				var ladder = document.getElementById( option.getAttribute( 'aria-controls' ) );

				if ( ladder && root.contains( ladder ) ) {
					show( ladder, true );
				}
			} );
		} );

		// A link followed to a detail: before the browser jumps, so the detail it jumps to is shown. Not
		// when the link opens somewhere else, in a new tab or window (a modifier key held) or by another
		// script: the page left behind keeps its ladder and its step.
		document.addEventListener( 'click', function ( event ) {
			var link;

			if ( event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) {
				return;
			}

			link = event.target && event.target.closest ? event.target.closest( 'a[href^="#wpc-hub-step-"]' ) : null;

			if ( link ) {
				follow( link.getAttribute( 'href' ), true );
			}
		} );

		// The steps move as the window is resized, so the shown ladder's detail points at its step again.
		window.addEventListener( 'resize', function () {
			each( ladders, function ( ladder ) {
				if ( ! ladder.hidden ) {
					point( ladder );
				}
			} );
		} );

		// An address changed to a detail that is hidden, itself or with its ladder: the browser's own jump
		// found it hidden and cannot be counted on to land, so once it is shown, go there. A detail that
		// was shown is the browser's to jump to, as a link followed is, so that jump is not doubled.
		window.addEventListener( 'hashchange', function () {
			var target = document.getElementById( window.location.hash.slice( 1 ) );
			var wasHidden = !! target && !! target.closest && null !== target.closest( '[hidden]' );
			var detail = follow( window.location.hash, true );

			if ( detail && wasHidden ) {
				detail.scrollIntoView();
			}
		} );

		switcher.hidden = false;
		each( root.querySelectorAll( '.wpc-hub-ladder__hint' ), function ( hint ) {
			hint.hidden = false;
		} );
		root.classList.add( 'is-interactive' );
		show( ladders[ 0 ], false );

		// The page opened at a detail's address: the browser jumped before this ran, while every detail
		// was shown, so the jump is made again to where the detail is now.
		( function ( opened ) {
			if ( opened ) {
				opened.scrollIntoView();
			}
		}( follow( window.location.hash, false ) ) );
	}

	function start() {
		each( document.querySelectorAll( '.wpc-hub-pathway' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
