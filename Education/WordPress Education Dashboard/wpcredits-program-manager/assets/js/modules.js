/**
 * The Student Report Card's movable modules (1.95.12).
 *
 * Each module under the profile and mentor columns carries two arrows: a form that posts "move
 * this module up or down" and comes back to the page. Without this file that round trip is the
 * whole feature, and it works. With it, the module moves in place the moment an arrow is pressed,
 * the way a block moves in the block editor, and the same form is posted in the background to
 * remember the order. The answer carries the order the server kept, and the page is put in that
 * order, so what is on screen is never something the server did not save.
 *
 * Nothing here knows the module names or the rules. The form holds every field the server needs,
 * and the arrows' labels and spoken confirmations come from the markup.
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
		 * @param {string[]} order Module keys.
		 */
		function arrange( order ) {
			var list = modules();
			var byKey = {};
			var parent;
			var marker;

			if ( ! list.length ) {
				return;
			}

			parent = list[ 0 ].parentNode;
			marker = document.createComment( 'wpcpm-modules' );
			parent.insertBefore( marker, list[ 0 ] );

			list.forEach( function ( module ) {
				byKey[ module.id.replace( 'wpcpm-module-', '' ) ] = module;
			} );

			order.forEach( function ( key ) {
				if ( byKey[ key ] ) {
					parent.insertBefore( byKey[ key ], marker );
				}
			} );

			parent.removeChild( marker );
			refresh();
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

						// Only the newest answer arranges the page: two quick presses can be
						// answered out of order, and the older answer would undo the newer press.
						if ( ticket === sent && Array.isArray( order ) ) {
							arrange( order );
						}
					} )
					.catch( function () {
						// The page keeps the order on screen; the next load shows what was kept.
					} );
			} );
		} );
	} );
}() );
