/**
 * Call calendar — the two small things it wants a script for.
 *
 * The calendar itself works without this: months and days are links, and booking is a
 * form post. Both jobs below are additions to a page that already works, and both are
 * rendered `hidden` in the markup and unhidden here, so nothing on screen without
 * JavaScript is a control that would do nothing.
 *
 * **Offering the browser's timezone**, on the student's booking calendar. A student who
 * has never set one is otherwise shown the site's clock with nothing to suggest it is not
 * theirs — the one mistake in a booking calendar that cannot be undone by looking harder.
 * It only ever *offers*: silently switching would mean a student on holiday, or behind a
 * VPN, quietly booking against a clock they never chose.
 *
 * **Copying hours between days**, in the mentor's availability editor. It writes into
 * inputs the form already posts, so there is no handler behind it — and nothing is saved
 * by copying. The mentor still presses Save, which means a copy that went to the wrong
 * days is undone by not saving.
 *
 * The submit guard these pages' forms also carry is forms.js, which this script depends on;
 * it started life in here and moved out once screens with forms and no calendar needed it.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
			return;
		}

		document.addEventListener( 'DOMContentLoaded', fn );
	}

	/**
	 * Which day numbers a target keyword covers. ISO: 1 is Monday, 7 is Sunday.
	 *
	 * @param {string} key Target keyword.
	 * @return {number[]}
	 */
	function targetDays( key ) {
		if ( 'weekdays' === key ) {
			return [ 1, 2, 3, 4, 5 ];
		}

		if ( 'weekend' === key ) {
			return [ 6, 7 ];
		}

		return [ 1, 2, 3, 4, 5, 6, 7 ];
	}

	/**
	 * One of a day's time inputs, by day and window index.
	 *
	 * Found by id rather than by name: the ids are flat and predictable, where the names
	 * are `availability[weekly][3][1][start]` and would need escaping to put in a selector.
	 *
	 * @param {number} day   ISO day number.
	 * @param {number} index Window index.
	 * @param {string} edge  'start' or 'end'.
	 * @return {Element|null}
	 */
	function timeInput( day, index, edge ) {
		return document.getElementById( 'wpcpm-av-' + day + '-' + index + '-' + edge );
	}

	/**
	 * The selected option's visible text, for the status message.
	 *
	 * @param {Element} select Select element.
	 * @return {string}
	 */
	function chosen( select ) {
		var option = select.options[ select.selectedIndex ];

		return option ? option.text : '';
	}

	/**
	 * Wire up the availability editor's copy control.
	 */
	function copyHours() {
		var box = document.querySelector( '[data-wpcpm-copy]' );

		if ( ! box ) {
			return;
		}

		var from = box.querySelector( '[data-wpcpm-copy-from]' );
		var to = box.querySelector( '[data-wpcpm-copy-to]' );
		var go = box.querySelector( '[data-wpcpm-copy-go]' );
		var status = box.querySelector( '[data-wpcpm-copy-status]' );

		if ( ! from || ! to || ! go ) {
			return;
		}

		var windows = parseInt( box.getAttribute( 'data-wpcpm-windows' ), 10 ) || 1;

		box.hidden = false;

		go.addEventListener( 'click', function () {
			var source = parseInt( from.value, 10 );
			var pairs = [];
			var index;

			for ( index = 0; index < windows; index++ ) {
				var start = timeInput( source, index, 'start' );
				var end = timeInput( source, index, 'end' );

				pairs.push( {
					start: start ? start.value : '',
					end: end ? end.value : ''
				} );
			}

			// Refuse to copy a day with nothing in it. Copying blanks over the week is a
			// legitimate reading of "copy", and a destructive surprise — a mentor reaching
			// for this wants to fill days in, not empty them. Days are still cleared one at
			// a time by hand.
			var filled = pairs.some( function ( pair ) {
				return '' !== pair.start || '' !== pair.end;
			} );

			if ( ! filled ) {
				if ( status ) {
					status.textContent = ( box.getAttribute( 'data-wpcpm-blank' ) || '' )
						.replace( '%s', chosen( from ) );
				}

				return;
			}

			targetDays( to.value ).forEach( function ( day ) {
				if ( day === source ) {
					return;
				}

				pairs.forEach( function ( pair, i ) {
					var start = timeInput( day, i, 'start' );
					var end = timeInput( day, i, 'end' );

					if ( start ) {
						start.value = pair.start;
					}

					if ( end ) {
						end.value = pair.end;
					}
				} );
			} );

			if ( status ) {
				status.textContent = ( box.getAttribute( 'data-wpcpm-copied' ) || '' )
					.replace( '%1$s', chosen( from ) )
					.replace( '%2$s', chosen( to ) );
			}
		} );
	}

	ready( function () {
		copyHours();

		var select = document.querySelector( '[data-wpcpm-zone]' );
		var hint = document.querySelector( '[data-wpcpm-zone-hint]' );

		// The hint is only rendered for somebody who has not chosen a timezone yet, so
		// its absence is the signal to leave a deliberate choice alone.
		if ( ! select || ! hint ) {
			return;
		}

		var guess = '';

		try {
			guess = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
		} catch ( error ) {
			return;
		}

		if ( ! guess || guess === select.value ) {
			return;
		}

		// Only worth offering if the server would accept it: the list is PHP's, and a
		// browser can name a zone PHP has never heard of.
		var offered = false;
		var index;

		for ( index = 0; index < select.options.length; index++ ) {
			if ( select.options[ index ].value === guess ) {
				offered = true;
				break;
			}
		}

		if ( ! offered ) {
			return;
		}

		// Pre-selected, not saved. The button is still the thing that commits it, so the
		// student sees what they are agreeing to before the times on screen change.
		select.value = guess;
		hint.hidden = false;
	} );
}() );
