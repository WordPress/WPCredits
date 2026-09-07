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
 *   live region, for a screen reader that cannot see the button change;
 * - `data-wpcpm-select` on an element selects its text on click (a claimed sponsor code);
 * - `data-wpcpm-shows-for="codes|shared"` on an element shows it while the form's checked
 *   `wpcpm_kind` radio has that value (the offer forms on the Sponsor Dashboard).
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
			// An inline `onsubmit="return confirm(...)"` - the Administrator Dashboard's Approve,
			// Reject, Reject as spam and Delete for good all carry one - has already run by the
			// time this listener does. When the manager presses Cancel, that handler already
			// called preventDefault() and nothing was submitted; locking the form "Working" and
			// disabling its buttons for a press that never went anywhere misreports the page's
			// state, and the decisions with a confirm are exactly the destructive ones, where a
			// wrong "Working" is worst.
			if ( event.defaultPrevented ) {
				return;
			}

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
	 * Take the busy state off every control in a list.
	 *
	 * Every one of them: the loop's bound is the length of the list and nothing else. A version
	 * that stopped short would leave part of a form disabled after a Back, with nothing on the
	 * page saying why, and no static check would see it.
	 *
	 * @param {NodeList} controls Controls to re-enable and re-label.
	 */
	function releaseControls( controls ) {
		var j;

		for ( j = 0; j < controls.length; j++ ) {
			controls[ j ].disabled = false;

			var label = controls[ j ].getAttribute( 'data-wpcpm-label' );

			if ( null !== label ) {
				controls[ j ].innerHTML = label;
				controls[ j ].removeAttribute( 'data-wpcpm-label' );
			}
		}
	}

	/**
	 * Undo the busy state when a page comes back from the browser's cache.
	 *
	 * Going back to a booking page would otherwise show every slot disabled and one of them
	 * still reading "Booking...", with nothing a student could do about it.
	 *
	 * A control does not have to sit inside the form it submits, which is why the form's own
	 * descendants are not the whole list. The sponsor's "Void unclaimed codes" button is printed
	 * inside the Add-codes form and posts the void form through `form="wpcpm-offer-void-<id>"`,
	 * and that form holds nothing but hidden inputs: asking it for its buttons found none, so
	 * after a Back the button stayed disabled and still read "Voiding" until a full reload (deep
	 * check FFRNT-1). Anything naming the form in a `form` attribute is released with it.
	 */
	function releaseOnRestore() {
		window.addEventListener( 'pageshow', function ( event ) {
			if ( ! event.persisted ) {
				return;
			}

			var forms = document.querySelectorAll( 'form[data-wpcpm-sent]' );
			var i;

			for ( i = 0; i < forms.length; i++ ) {
				var form = forms[ i ];

				form.removeAttribute( 'data-wpcpm-sent' );
				form.removeAttribute( 'aria-busy' );
				form.className = form.className.replace( / ?is-sending/, '' );

				releaseControls( form.querySelectorAll( 'button, input[type="submit"]' ) );

				// A form with no id owns nothing outside itself, and `[form=""]` is a selector
				// worth nobody's time.
				//
				// The id goes straight into a selector, so it is escaped on the way in. Every id
				// this plugin prints is its own integer, but one carrying a quote, a bracket or
				// a space would make `[form="..."]` a selector the browser refuses - and the
				// exception is thrown here, inside the pageshow handler, which ends this loop
				// and leaves every form after it on the page disabled with its button still
				// reading "Sending". CSS.escape where the browser has it; a backslash before the
				// two characters that can close a CSS string where it does not.
				if ( form.id ) {
					var id = window.CSS && CSS.escape ? CSS.escape( form.id ) : form.id.replace( /["\\]/g, '\\$&' );

					releaseControls( document.querySelectorAll( '[form="' + id + '"]' ) );
				}

				var status = form.querySelector( '[data-wpcpm-busy-status]' );

				if ( status ) {
					status.textContent = '';
				}
			}
		} );
	}

	/**
	 * Select everything inside one element. The one routine both listeners below call, so a
	 * click and a key press cannot drift apart.
	 *
	 * @param {Element|null} target The element to select, or null.
	 */
	function selectContents( target ) {
		if ( ! target || ! window.getSelection || ! document.createRange ) {
			return;
		}

		var range = document.createRange();
		var selection = window.getSelection();

		range.selectNodeContents( target );
		selection.removeAllRanges();
		selection.addRange( range );
	}

	/**
	 * A claimed code is a `<button type="button" class="wpcpm-tools__code" data-wpcpm-select>`:
	 * one click, or Enter or Space while it has focus, selects the whole thing, so a person
	 * copies it with the shortcut they already know. No clipboard API, which asks for a
	 * permission on some browsers and silently does nothing on others; a selection works
	 * everywhere and the person sees what they are about to copy.
	 *
	 * It was a `<code data-wpcpm-select tabindex="0">` until 1.99.0 - a tab stop that announced
	 * neither a role nor a name, so a screen reader reached it and could say nothing about it
	 * (deep check FFRNT-6). The hook is still the attribute rather than the element, because
	 * nothing here should care which element the markup chose.
	 */
	function selectOnClick() {
		document.addEventListener( 'click', function ( event ) {
			selectContents( event.target && event.target.closest ? event.target.closest( '[data-wpcpm-select]' ) : null );
		} );

		// A button answers Enter and Space by itself, so on today's markup this listener only
		// gets there first. It stays for the same reason the hook is an attribute: an element
		// that is focusable without being a button - which is what carried this until 1.99.0 -
		// gets no key handling from the browser at all. preventDefault() so Space selects
		// instead of scrolling the page.
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' !== event.key && ' ' !== event.key ) {
				return;
			}

			var target = event.target && event.target.closest ? event.target.closest( '[data-wpcpm-select]' ) : null;

			if ( ! target ) {
				return;
			}

			selectContents( target );
			event.preventDefault();
		} );
	}

	/**
	 * Show the box that belongs to the kind of offer chosen.
	 *
	 * The offer forms ask for one thing a pool needs (the codes) and one thing a shared offer
	 * needs (the code or link); showing both left sponsors filling in the wrong one. Every
	 * element marked `data-wpcpm-shows-for` inside a form is shown while the form's checked
	 * `wpcpm_kind` radio has that value and hidden otherwise, on load and on every change.
	 *
	 * A convenience, never a control: with JavaScript off both boxes stay visible, a form with
	 * no such radio (the kind is fixed once the pool holds anything) shows everything, and the
	 * handler reads only the box that belongs to the kind posted.
	 */
	function showForKind() {
		var forms = document.querySelectorAll( 'form' );
		var i;

		for ( i = 0; i < forms.length; i++ ) {
			bindShowForKind( forms[ i ] );
		}
	}

	/**
	 * @param {HTMLFormElement} form A form that may carry kind radios and marked boxes.
	 */
	function bindShowForKind( form ) {
		var marked = form.querySelectorAll( '[data-wpcpm-shows-for]' );
		var radios = form.querySelectorAll( 'input[type="radio"][name="wpcpm_kind"]' );
		var i;

		if ( ! marked.length || ! radios.length ) {
			return;
		}

		function apply() {
			var checked = form.querySelector( 'input[type="radio"][name="wpcpm_kind"]:checked' );
			var kind = checked ? checked.value : '';
			var j;

			for ( j = 0; j < marked.length; j++ ) {
				marked[ j ].hidden = '' !== kind && marked[ j ].getAttribute( 'data-wpcpm-shows-for' ) !== kind;
			}
		}

		for ( i = 0; i < radios.length; i++ ) {
			radios[ i ].addEventListener( 'change', apply );
		}

		apply();
	}

	ready( function () {
		guardForms();
		releaseOnRestore();
		selectOnClick();
		showForKind();
	} );
}() );
