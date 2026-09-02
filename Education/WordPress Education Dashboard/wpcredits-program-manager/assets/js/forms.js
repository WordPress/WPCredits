/**
 * Submit guard - every form marked `data-wpcpm-once` is posted once, and says it is working.
 *
 * Pressing a time slot on the booking calendar looked like nothing had happened until the
 * page reloaded, so students pressed again - and a second press can hit the booking lock
 * and be told "another booking was going through" when their own first press is what was
 * going through. Every form the dashboards post has the same shape of problem, which is
 * why the opt-in is an attribute on the form rather than a list of forms in here.
 *
 * Its own file, rather than the tail of calendar.js where it started: the calendar's two
 * pages were the only ones with a form worth guarding, so the guard rode along with the
 * calendar script and reached the other dashboard forms because they share those pages.
 * The Institutions screens have forms and no calendar, and loading a calendar for a guard
 * that has nothing to do with it would have been the price of keeping them together. The
 * calendar script now declares this one as a dependency, so every page that had the guard
 * still has it, loaded first.
 *
 * Nothing here is a control. With JavaScript off every form posts exactly as before, only
 * without the "working" state. The markup it reads:
 *
 * - `data-wpcpm-once` on the form opts it in;
 * - `data-wpcpm-busy` is the label the pressed control shows while the request is in flight;
 * - `data-wpcpm-status`, optional, is a sentence for the form's `[data-wpcpm-busy-status]`
 *   live region, for a screen reader that cannot see the button change.
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
	 * Stop a form being submitted twice, and say that it is working.
	 *
	 * The trap this has to avoid: the slot buttons carry the value being submitted
	 * (`name="start" value="..."`), and a *disabled* control is not serialized. Disabling the
	 * pressed button inside the submit handler would therefore post the form with no slot
	 * in it - booking would break outright, which is a good deal worse than the confusion
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
				// `innerHTML` because a slot button is two spans - a time and an end time -
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
	 * still reading "Booking...", with nothing a student could do about it.
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
		guardForms();
		releaseOnRestore();
	} );
}() );
