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
 * **Showing that a form is working**, on every form these two pages post. Pressing a time
 * slot looked like nothing had happened until the page reloaded, so students pressed again
 * — and a second press can hit the booking lock and be told "another booking was going
 * through" when their own first press is what was going through.
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

	/**
	 * Stop a form being submitted twice, and say that it is working.
	 *
	 * The trap this has to avoid: the slot buttons carry the value being submitted
	 * (`name="start" value="…"`), and a *disabled* control is not serialized. Disabling the
	 * pressed button inside the submit handler would therefore post the form with no slot
	 * in it — booking would break outright, which is a good deal worse than the confusion
	 * this fixes. So the pressed button is disabled from a `setTimeout`, after the browser
	 * has already built the request; the buttons that were *not* pressed carry nothing and
	 * are safe to disable immediately.
	 *
	 * Changing a button's text is always safe: for `<button name value>` the submitted
	 * value is the `value` attribute, never the label.
	 */
	function guardForms() {
		var forms = document.querySelectorAll( 'form[data-wpcpm-once]' );
		var i;

		for ( i = 0; i < forms.length; i++ ) {
			guardForm( forms[ i ] );
		}
	}

	/**
	 * @param {HTMLFormElement} form Form to guard.
	 */
	function guardForm( form ) {
		var pressed = null;

		// `submitter` is not available everywhere, so the last control pressed is tracked
		// too. `mousedown` rather than `click`, because `click` on a submit button and the
		// form's `submit` event race in some browsers.
		form.addEventListener( 'mousedown', function ( event ) {
			var target = event.target;

			while ( target && target !== form ) {
				if ( 'BUTTON' === target.tagName || 'INPUT' === target.tagName ) {
					pressed = target;
					return;
				}
				target = target.parentNode;
			}
		} );

		form.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || ' ' === event.key ) {
				var target = event.target;

				if ( target && 'BUTTON' === target.tagName ) {
					pressed = target;
				}
			}
		} );

		form.addEventListener( 'submit', function ( event ) {
			if ( form.getAttribute( 'data-wpcpm-sent' ) ) {
				// Already on its way. Swallow the repeat rather than posting twice.
				event.preventDefault();
				return;
			}

			form.setAttribute( 'data-wpcpm-sent', '1' );
			form.setAttribute( 'aria-busy', 'true' );
			form.className += ' is-sending';

			var button = event.submitter || pressed;
			var busy = form.getAttribute( 'data-wpcpm-busy' );
			var buttons = form.querySelectorAll( 'button, input[type="submit"]' );
			var i;

			for ( i = 0; i < buttons.length; i++ ) {
				if ( buttons[ i ] !== button ) {
					// Not the pressed control, so it contributes nothing to this request.
					buttons[ i ].disabled = true;
				}
			}

			if ( button && busy ) {
				// `innerHTML` because a slot button is two spans — a time and an end time —
				// and restoring a saved `textContent` would bring them back as one run-on
				// string. It is this button's own markup, none of it from input.
				button.setAttribute( 'data-wpcpm-label', button.innerHTML );
				button.textContent = busy;
			}

			var status = form.querySelector( '[data-wpcpm-busy-status]' );

			if ( status ) {
				status.textContent = form.getAttribute( 'data-wpcpm-status' ) || busy || '';
			}

			// After the request has been built. See the note on guardForms().
			if ( button ) {
				window.setTimeout( function () {
					button.disabled = true;
				}, 0 );
			}
		} );
	}

	/**
	 * Undo the busy state when a page comes back from the browser's cache.
	 *
	 * Going back to a booking page would otherwise show every slot disabled and one of them
	 * still reading "Booking…", with nothing a student could do about it.
	 */
	function releaseOnRestore() {
		window.addEventListener( 'pageshow', function ( event ) {
			if ( ! event.persisted ) {
				return;
			}

			var forms = document.querySelectorAll( 'form[data-wpcpm-sent]' );
			var i;
			var j;

			for ( i = 0; i < forms.length; i++ ) {
				var form = forms[ i ];

				form.removeAttribute( 'data-wpcpm-sent' );
				form.removeAttribute( 'aria-busy' );
				form.className = form.className.replace( / ?is-sending/, '' );

				var buttons = form.querySelectorAll( 'button, input[type="submit"]' );

				for ( j = 0; j < buttons.length; j++ ) {
					buttons[ j ].disabled = false;

					var label = buttons[ j ].getAttribute( 'data-wpcpm-label' );

					if ( null !== label ) {
						buttons[ j ].innerHTML = label;
						buttons[ j ].removeAttribute( 'data-wpcpm-label' );
					}
				}

				var status = form.querySelector( '[data-wpcpm-busy-status]' );

				if ( status ) {
					status.textContent = '';
				}
			}
		} );
	}

	ready( function () {
		copyHours();
		guardForms();
		releaseOnRestore();

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
