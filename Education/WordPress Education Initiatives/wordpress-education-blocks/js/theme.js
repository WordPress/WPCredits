/**
 * WordPress Education Initiatives Blocks — animated statistics.
 *
 * The counter target is read from each element's own text (e.g. "1,200+"), so
 * the numbers stay fully editable in the Site Editor with no extra settings.
 * Navigation dropdowns and the mobile menu are provided by the core
 * Navigation block, so no JavaScript is needed for them.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var nodes = Array.prototype.slice.call(
			document.querySelectorAll( '.wpedu-stat__num' )
		);
		if ( ! nodes.length ) {
			return;
		}

		var reduceMotion =
			window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		function run( node ) {
			if ( node.dataset.wpeduDone ) {
				return;
			}
			node.dataset.wpeduDone = '1';

			var raw = node.textContent.trim();
			var digits = ( raw.match( /[0-9.,]+/ ) || [] )[ 0 ];

			// Non-numeric labels (e.g. "Endless") are left untouched.
			if ( ! digits ) {
				return;
			}

			var target = parseInt( digits.replace( /,/g, '' ), 10 );
			var suffix = raw.slice( raw.indexOf( digits ) + digits.length );

			function format( value ) {
				try {
					return value.toLocaleString() + suffix;
				} catch ( e ) {
					return String( value ) + suffix;
				}
			}

			if ( reduceMotion ) {
				node.textContent = format( target );
				return;
			}

			var duration = 1400;
			var start = null;

			function tick( now ) {
				if ( null === start ) {
					start = now;
				}
				var t = Math.min( 1, ( now - start ) / duration );
				var eased = 1 - Math.pow( 1 - t, 3 );
				node.textContent = format( Math.round( target * eased ) );
				if ( t < 1 ) {
					window.requestAnimationFrame( tick );
				}
			}

			window.requestAnimationFrame( tick );
		}

		if ( 'IntersectionObserver' in window ) {
			var observer = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting ) {
							run( entry.target );
							observer.unobserve( entry.target );
						}
					} );
				},
				{ threshold: 0.4 }
			);
			nodes.forEach( function ( node ) {
				observer.observe( node );
			} );
		} else {
			nodes.forEach( run );
		}
	} );
} )();
