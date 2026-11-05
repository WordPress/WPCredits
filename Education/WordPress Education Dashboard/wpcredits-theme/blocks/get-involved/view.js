/**
 * The header's Get involved menu: its button opens and closes the panel of role rows it names.
 *
 * inc/get-involved.php prints a plain button and the panel, its next element, with an ID, in one navigation
 * item: without this script the panel opens under the pointer and while the focus is in it, so the button
 * says nothing of a state. This script makes it a disclosure, `aria-expanded="false"` and `aria-controls`
 * naming the panel; the stylesheet then shows the panel while the button says it is expanded and places it
 * against the item, so opening it moves nothing. It keeps `aria-expanded` in step:
 *
 * - the button opens the panel and closes it again;
 * - Escape closes it and puts the focus on the button;
 * - a press outside the item closes it and moves no focus. A press is a `pointerdown` as well as a
 *   `click`, as a phone may send the document no click for a tap on plain text, and the document's
 *   listeners are in the capture phase, so a script that stops the event cannot keep the panel open;
 * - a row followed closes it, whether or not another script prevented its default; one followed with
 *   Ctrl, Cmd, Shift or Alt held opens elsewhere and leaves it open;
 * - the focus moving from the item to another element closes it; the focus moving to nothing, as on a
 *   press Safari does not focus, leaves it open.
 *
 * Every listener but the button's does nothing while the panel is closed. The list at the top of a phone's
 * menu, and of a signed-in reader's Menu panel, is plain links and has no button: nothing here touches it.
 */
( function () {
	'use strict';

	Array.prototype.forEach.call( document.querySelectorAll( '.wpc-involve__toggle' ), function ( button ) {
		var item = button.closest( '.wpc-involve' );
		var panel = item ? item.querySelector( '.wpc-involve__panel' ) : null;

		if ( ! panel || ! panel.id ) {
			return;
		}

		button.setAttribute( 'aria-controls', panel.id );
		button.setAttribute( 'aria-expanded', 'false' );

		function isOpen() {
			return 'true' === button.getAttribute( 'aria-expanded' );
		}

		function setOpen( open ) {
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		function outside( event ) {
			if ( isOpen() && ! item.contains( event.target ) ) {
				setOpen( false );
			}
		}

		button.addEventListener( 'click', function () {
			setOpen( ! isOpen() );
		} );

		panel.addEventListener( 'click', function ( event ) {
			if ( isOpen() && event.target.closest( 'a[href]' ) && ! ( event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) ) {
				setOpen( false );
			}
		} );

		document.addEventListener( 'pointerdown', outside, true );
		document.addEventListener( 'click', outside, true );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && isOpen() ) {
				setOpen( false );
				button.focus();
			}
		}, true );

		item.addEventListener( 'focusout', function ( event ) {
			var next = event.relatedTarget;

			if ( isOpen() && next && ! item.contains( next ) ) {
				setOpen( false );
			}
		} );
	} );
}() );
