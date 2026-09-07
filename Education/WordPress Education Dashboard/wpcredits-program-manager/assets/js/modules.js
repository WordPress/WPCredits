/**
 * The Student Report Card's movable modules (1.95.12).
 *
 * Each module under the profile and mentor columns carries two arrows: a form that posts "move
 * this module up or down" and comes back to the page. Without this file that round trip is the
 * whole feature, and it works. With it, the module moves in place the moment an arrow is pressed,
 * the way a block moves in the block editor, and the same form is posted in the background to
 * remember the order. The answer carries the order the server kept, and the page is put in that
 * order.
 *
 * When the server refuses the move - a nonce that expired while the page sat open is the ordinary
 * way, and these pages are left open for days - the module goes back where it started and the live
 * region says the move was not kept, so what is on screen is not an order nobody saved. Until
 * 1.99.0 a refusal was ignored: the page kept the move, the live region kept the confirmation it
 * had already spoken, and the next load silently undid both (deep check FFRNT-3). A request that
 * gets no usable answer - the connection went, or what came back was not the JSON the page asked
 * for, and the move may well have been saved - is the one case the page keeps what is on screen;
 * the next load shows what was kept.
 *
 * Nothing here knows the module names or the rules. The form holds every field the server needs,
 * and the arrows' labels, the spoken confirmations and the refusal sentence come from the markup.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var forms = Array.prototype.slice.call( document.querySelectorAll( '.wpcpm-module__mover' ) );

		if ( ! forms.length || ! window.fetch || ! window.FormData ) {
			return;
		}

		var first = forms[ 0 ].closest( '.wpcpm-module' );
		var live  = document.createElement( 'p' );
		var sent  = 0;

		// The newest press that has been answered, and the order the server is known to hold.
		// One order, not one per press: what the page has to go back to when a save is refused
		// is what the server has, which is the last order it said it kept - and it says nothing
		// when it refuses. Started from the page as drawn, which is that order.
		var answered = 0;
		var kept     = keys( modules() );

		live.className = 'screen-reader-text';
		live.setAttribute( 'aria-live', 'polite' );
		first.parentNode.insertBefore( live, first );

		/**
		 * Every module, in the order the page shows.
		 *
		 * @return {Element[]}
		 */
		function modules() {
			return Array.prototype.slice.call( document.querySelectorAll( '.wpcpm-module' ) );
		}

		/**
		 * The key each module in a list carries.
		 *
		 * @param {Element[]} list Modules.
		 * @return {string[]}
		 */
		function keys( list ) {
			return list.map( function ( module ) {
				return module.id.replace( 'wpcpm-module-', '' );
			} );
		}

		/**
		 * The top module cannot go up and the bottom one cannot go down.
		 */
		function refresh() {
			var list = modules();

			list.forEach( function ( module, i ) {
				var up   = module.querySelector( '.wpcpm-module__move--up' );
				var down = module.querySelector( '.wpcpm-module__move--down' );

				if ( up ) {
					up.disabled = 0 === i;
				}

				if ( down ) {
					down.disabled = i === list.length - 1;
				}
			} );
		}

		/**
		 * Put the page in the order the server kept.
		 *
		 * Two things it must not do. It must not touch the DOM when the order it was handed is
		 * the order already on screen, which is what the answer to a move ordinarily carries:
		 * re-inserting a module detaches it, and per the HTML standard removing the focused
		 * element's ancestor sends focus back to the document, so every arrow press threw the
		 * keyboard to the top of the page about a fifth of a second after it (deep check
		 * FFRNT-2). And when it does move something, it must put focus back where it found it.
		 *
		 * @param {string[]}   order   Module keys.
		 * @param {Element|null} pressed The arrow the person pressed, when there was one.
		 */
		function arrange( order, pressed ) {
			var list = modules();
			var focused = document.activeElement;
			var byKey = {};
			var parent;
			var marker;

			if ( ! list.length ) {
				return;
			}

			// The arrows were put right by the press that led here, so there is nothing left to
			// do and nothing to gain by doing it.
			if ( keys( list ).join( ',' ) === order.join( ',' ) ) {
				return;
			}

			// Something really is moving, which on this page means the save was refused. The
			// press disabled the arrow that had reached an edge and focus went to its neighbor;
			// putting the module back brings that arrow to life again, and it is the one the
			// person pressed and is looking at. Only while focus is still in the same pair, so
			// somebody who has tabbed away in the meantime is not pulled back to it.
			if ( pressed && document.contains( pressed ) && mover( focused ) === mover( pressed ) ) {
				focused = pressed;
			}

			parent = list[ 0 ].parentNode;
			marker = document.createComment( 'wpcpm-modules' );
			parent.insertBefore( marker, list[ 0 ] );

			keys( list ).forEach( function ( key, i ) {
				byKey[ key ] = list[ i ];
			} );

			order.forEach( function ( key ) {
				if ( byKey[ key ] ) {
					parent.insertBefore( byKey[ key ], marker );
				}
			} );

			parent.removeChild( marker );
			refresh();
			restore( focused );
		}

		/**
		 * The mover an element sits in, or null.
		 *
		 * @param {Element|null} element Any element, including document.body.
		 * @return {Element|null}
		 */
		function mover( element ) {
			return element && element.closest ? element.closest( '.wpcpm-module__mover' ) : null;
		}

		/**
		 * Put focus back on the control that had it before the modules were re-inserted.
		 *
		 * The arrow may have gone quiet while it was away - the module reached an edge - and a
		 * disabled control cannot take focus, so its neighbor in the same mover takes it, exactly
		 * as it does on the press itself.
		 *
		 * @param {Element|null} focused The control that had focus.
		 */
		function restore( focused ) {
			var pair;

			if ( ! focused || ! focused.focus || ! document.contains( focused ) ) {
				return;
			}

			if ( focused.disabled ) {
				pair    = mover( focused );
				focused = ( pair && pair.querySelector( 'button:not([disabled])' ) ) || focused;
			}

			focused.focus();
		}

		forms.forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				var button = event.submitter || document.activeElement;
				var module = form.closest( '.wpcpm-module' );
				var list;
				var index;
				var direction;
				var neighbor;
				var data;
				var ticket;

				// Not one of the two arrows: let the form go the ordinary way.
				if ( ! module || ! button || 'BUTTON' !== button.tagName || ! form.contains( button ) ) {
					return;
				}

				event.preventDefault();

				list      = modules();
				index     = list.indexOf( module );
				direction = button.value;
				neighbor  = 'up' === direction ? list[ index - 1 ] : list[ index + 1 ];

				if ( ! neighbor ) {
					return;
				}

				if ( 'up' === direction ) {
					neighbor.parentNode.insertBefore( module, neighbor );
				} else {
					neighbor.parentNode.insertBefore( neighbor, module );
				}

				refresh();

				// Focus stays with the module: on the arrow just pressed, or on the other one
				// when the module reached the edge and this one went quiet.
				( button.disabled ? form.querySelector( 'button:not([disabled])' ) || button : button ).focus();
				live.textContent = button.getAttribute( 'data-wpcpm-moved' ) || '';

				data = new FormData( form );
				data.append( button.name, direction );
				data.append( 'wpcpm_async', '1' );
				ticket = ++sent;

				// The attribute, not `form.action`: the hidden `action` field WordPress admin-post
				// needs shadows that property with the input element itself.
				fetch( form.getAttribute( 'action' ), {
					method: 'POST',
					body: data,
					credentials: 'same-origin',
					headers: { 'X-Requested-With': 'XMLHttpRequest' }
				} )
					.then( function ( response ) {
						return response.ok ? response.json() : null;
					} )
					.then( function ( json ) {
						var order = json && json.data && json.data.order;

						// An answer older than one already handled says nothing: the server has
						// moved past it and the newer answer is the one that describes it.
						if ( ticket <= answered ) {
							return;
						}

						answered = ticket;

						if ( Array.isArray( order ) ) {
							kept = order;
						} else {
							// Refused, and the live region has already said the module moved.
							// Replace what it said (deep check FFRNT-3). `kept` is left where it
							// is, which is the whole point: a refusal changed nothing, so the
							// order the server holds is the one it already held.
							live.textContent = form.getAttribute( 'data-wpcpm-refused' ) || '';
						}

						// Only the newest press arranges the page: two quick presses can be
						// answered out of order, and an older answer would undo the newer press.
						// This stands here and not in front of the refusal above, because a
						// refusal answered while a later press is in flight still has to be
						// spoken and still has to leave `kept` alone - two presses both refused
						// used to leave the first move on screen with nothing having saved it.
						if ( ticket !== sent ) {
							return;
						}

						arrange( kept, button );
					} )
					.catch( function () {
						// The page keeps the order on screen; the next load shows what was kept.
					} );
			} );
		} );
	} );
}() );
