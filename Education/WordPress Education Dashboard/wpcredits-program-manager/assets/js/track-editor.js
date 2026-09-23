/**
 * The Track Builder's question list: a question moves the moment its arrow is pressed.
 *
 * Each row carries two arrows, a form that posts "move this question up or down" and comes back
 * to the page. Without this file that round trip is the whole feature, and it works. With it the
 * row moves in place the moment an arrow is pressed and the same form is posted in the background
 * to remember the order (`assets/js/modules.js` is the pattern, and the reasons there hold here).
 * The answer carries the order the server kept, and the page is put in that order.
 *
 * A move stays inside the question's group: the server swaps a question with its neighbor in the
 * same group and nothing else, so the page does the same, and an arrow at the edge of its group
 * is disabled rather than pressed into doing nothing.
 *
 * When the server refuses the move - a nonce that expired while the page sat open, or a track
 * that can no longer be saved - the row goes back where it started, and the page says the move
 * was not kept, with the server's reason when it gave one: in the live region, and in a notice
 * above the list, so that a sighted person is told too. The server answers a refusal as JSON with
 * a status that is not 2xx; an answer that is not the order the server kept, a redirect the fetch
 * followed among them, reads the same way (the deep check of 1.109.1, BUILDER-4: a refusal
 * answered with a redirect left the row moved and said nothing). The reason is set as text, never
 * as markup. A request that gets no answer at all keeps what is on screen; the next load shows
 * what was kept.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var forms = Array.prototype.slice.call( document.querySelectorAll( '.wpcpm-question__mover' ) );

		if ( ! forms.length || ! window.fetch || ! window.FormData ) {
			return;
		}

		var list   = document.querySelector( '.wpcpm-questions' );
		var live   = document.createElement( 'p' );
		var sent   = 0;
		var notice = null;

		// The newest press that has been answered, and the order the server is known to hold.
		var answered = 0;
		var kept     = columns( rows() );

		live.className = 'screen-reader-text';
		live.setAttribute( 'aria-live', 'polite' );
		list.parentNode.insertBefore( live, list );

		/**
		 * Every question row, in the order the page shows.
		 *
		 * @return {Element[]}
		 */
		function rows() {
			return Array.prototype.slice.call( document.querySelectorAll( '.wpcpm-question' ) );
		}

		/**
		 * The column each row in a list carries, verbatim.
		 *
		 * @param {Element[]} list Rows.
		 * @return {string[]}
		 */
		function columns( list ) {
			return list.map( function ( row ) {
				return row.getAttribute( 'data-wpcpm-column' );
			} );
		}

		/**
		 * The rows of one group, in page order.
		 *
		 * @param {Element} row Any row of the group.
		 * @return {Element[]}
		 */
		function peers( row ) {
			var group = row.getAttribute( 'data-wpcpm-group' );

			return rows().filter( function ( other ) {
				return other.getAttribute( 'data-wpcpm-group' ) === group;
			} );
		}

		/**
		 * The first of a group cannot go up and the last cannot go down.
		 */
		function refresh() {
			rows().forEach( function ( row ) {
				var group = peers( row );
				var i     = group.indexOf( row );
				var up    = row.querySelector( '.wpcpm-question__move--up' );
				var down  = row.querySelector( '.wpcpm-question__move--down' );

				if ( up ) {
					up.disabled = 0 === i;
				}

				if ( down ) {
					down.disabled = i === group.length - 1;
				}
			} );
		}

		/**
		 * Put the page in the order the server kept, group by group, touching nothing that is
		 * already in place.
		 *
		 * The rows do not share a parent: the list is one table a group, so a row only ever
		 * moves within its own group's body, and the server's order (every group's, as one
		 * list) is filtered to the group at hand. Compared element by element rather than as
		 * a joined string, because a column name can hold a space; and the lookup has no
		 * prototype, so a column named like one of its properties cannot be mistaken for a
		 * row (the Task 6 review). When something really moves - on this page that means the
		 * save was refused - focus goes back where it was, as in modules.js.
		 *
		 * @param {string[]}     order   Column names, in the order the server kept.
		 * @param {Element|null} pressed The arrow the person pressed, when there was one.
		 */
		function arrange( order, pressed ) {
			var current = rows();
			var focused = document.activeElement;
			var groups  = Object.create( null );
			var moved   = false;

			if ( ! current.length ) {
				return;
			}

			if ( pressed && document.contains( pressed ) && mover( focused ) === mover( pressed ) ) {
				focused = pressed;
			}

			current.forEach( function ( row ) {
				var group = row.getAttribute( 'data-wpcpm-group' );

				if ( ! groups[ group ] ) {
					groups[ group ] = [];
				}

				groups[ group ].push( row );
			} );

			Object.keys( groups ).forEach( function ( group ) {
				var list     = groups[ group ];
				var byColumn = Object.create( null );
				var wanted;
				var same;
				var parent;
				var marker;

				list.forEach( function ( row ) {
					byColumn[ row.getAttribute( 'data-wpcpm-column' ) ] = row;
				} );

				wanted = order.filter( function ( column ) {
					return byColumn[ column ];
				} );

				same = wanted.length === list.length && wanted.every( function ( column, i ) {
					return byColumn[ column ] === list[ i ];
				} );

				if ( same ) {
					return;
				}

				parent = list[ 0 ].parentNode;
				marker = document.createComment( 'wpcpm-questions' );
				parent.insertBefore( marker, list[ 0 ] );

				wanted.forEach( function ( column ) {
					parent.insertBefore( byColumn[ column ], marker );
				} );

				parent.removeChild( marker );
				moved = true;
			} );

			if ( moved ) {
				refresh();
				restore( focused );
			}
		}

		/**
		 * The mover an element sits in, or null.
		 *
		 * @param {Element|null} element Any element.
		 * @return {Element|null}
		 */
		function mover( element ) {
			return element && element.closest ? element.closest( '.wpcpm-question__mover' ) : null;
		}

		/**
		 * Put focus back on the arrow that had it, or its neighbor when it went quiet.
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

		/**
		 * Say that a move was not kept, and why when the server said: in the live region, and in a
		 * notice above the list, set as text.
		 *
		 * @param {string} words  The page's own sentence, from the form.
		 * @param {string} reason The server's reason, or ''.
		 */
		function refused( words, reason ) {
			var message;

			words = reason ? words + ' ' + reason : words;

			if ( ! notice ) {
				notice           = document.createElement( 'div' );
				message          = document.createElement( 'p' );
				notice.className = 'notice notice-error inline wpcpm-questions__refused';
				notice.appendChild( message );
				list.parentNode.insertBefore( notice, list );
			}

			message             = notice.firstChild;
			message.textContent = words;
			live.textContent    = words;
		}

		/**
		 * Take the last refusal's notice away: a new press is about another move.
		 */
		function forget() {
			if ( notice && notice.parentNode ) {
				notice.parentNode.removeChild( notice );
			}

			notice = null;
		}

		refresh();

		forms.forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				var button = event.submitter || document.activeElement;
				var row    = form.closest( '.wpcpm-question' );
				var group;
				var index;
				var direction;
				var neighbor;
				var data;
				var ticket;

				if ( ! row || ! button || 'BUTTON' !== button.tagName || ! form.contains( button ) ) {
					return;
				}

				event.preventDefault();
				forget();

				group     = peers( row );
				index     = group.indexOf( row );
				direction = button.value;
				neighbor  = 'up' === direction ? group[ index - 1 ] : group[ index + 1 ];

				if ( ! neighbor ) {
					return;
				}

				if ( 'up' === direction ) {
					neighbor.parentNode.insertBefore( row, neighbor );
				} else {
					neighbor.parentNode.insertBefore( neighbor, row );
				}

				refresh();
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
						// A redirect the fetch followed is a page, not an answer, and a body that is
						// not JSON is none either: both read as a refusal, with no reason to give.
						if ( response.redirected ) {
							return null;
						}

						return response.json().catch( function () {
							return null;
						} );
					} )
					.then( function ( json ) {
						var order  = json && json.success && json.data && json.data.order;
						var reason = json && ! json.success && json.data && 'string' === typeof json.data.message ? json.data.message : '';

						// An answer older than one already handled says nothing.
						if ( ticket <= answered ) {
							return;
						}

						answered = ticket;

						if ( Array.isArray( order ) ) {
							kept = order;
						} else {
							// Refused: replace what the live region already said.
							refused( form.getAttribute( 'data-wpcpm-refused' ) || '', reason );
						}

						// Only the newest press arranges the page.
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
