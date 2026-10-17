/**
 * The site header's Menu button: it opens and closes the panel of the header's links below desktop
 * width.
 *
 * inc/header.php gives a signed-in reader's header the button, right before the group holding the
 * links, which the button names with `aria-controls`. Below 1200px the stylesheet lays that group
 * out as a panel under the bar, shown while the header has `wpc-header--open`; from 1200px the
 * button is not displayed and the group is the row of links, whatever this script says. This script
 * keeps `aria-expanded` and that class in step:
 *
 * - the button opens the menu and closes it again;
 * - Escape closes it and puts the focus on the button;
 * - a press outside the panel and the button closes it and moves no focus, and so does a press on
 *   the account picture, which sits in the panel's markup and is drawn in the bar. A press is a
 *   `pointerdown` as well as a `click`, as a phone may send the document no click for a tap on plain
 *   text. The document's listeners are in the capture phase, so a handler in the panel that stops
 *   the event, as the navigation block's own keydown handlers may, cannot keep the menu open;
 * - a link followed from the panel closes it, whether or not another script prevented its default;
 *   one followed with Ctrl, Cmd, Shift or Alt held opens elsewhere and leaves it open;
 * - the focus moving from the panel or the button to another element closes it; the focus moving to
 *   nothing, as on a press Safari does not focus, leaves it open;
 * - Need help? closes it, and the plugin opens its handbook assistant. The plugin gives the focus
 *   back to Need help? when the assistant closes, and Need help? is then in the closed panel, where
 *   the browser cannot focus it. So this reads the class `wpcpm-hb-open` the plugin puts on the root
 *   element while the assistant is open: when the class goes from present to absent after Need
 *   help? was pressed in the open menu, the focus goes to the button. The press is remembered until
 *   then, and forgotten each time the menu opens. Without `MutationObserver` the focus is left where
 *   the plugin puts it.
 *
 * Every event listener but the button's does nothing while the menu is closed. Nothing is done on a
 * resize or a scroll and nothing is stored: a menu left open across 1200px is hidden by the
 * stylesheet and shown again below it. A header without the button (signed out, or one entry) is
 * left alone.
 */
( function () {
	'use strict';

	var button = document.querySelector( '.wpc-menu-toggle' );
	var panel = button ? document.getElementById( button.getAttribute( 'aria-controls' ) ) : null;
	var header = button ? button.closest( '.wpc-header' ) : null;

	if ( ! button || ! panel || ! header ) {
		return;
	}

	// Need help? was pressed in the open menu, and the assistant's closing has not been seen since.
	var handedOff = false;

	function isOpen() {
		return 'true' === button.getAttribute( 'aria-expanded' );
	}

	function setOpen( open ) {
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		header.classList.toggle( 'wpc-header--open', open );

		if ( open ) {
			handedOff = false;
		}
	}

	function inside( element ) {
		return panel.contains( element ) || button.contains( element );
	}

	function outside( event ) {
		var target = event.target;

		if ( ! isOpen() ) {
			return;
		}

		if ( ! inside( target ) || ( target.closest && target.closest( '.wpc-viewer__avatar' ) ) ) {
			setOpen( false );
		}
	}

	button.addEventListener( 'click', function () {
		setOpen( ! isOpen() );
	} );

	panel.addEventListener( 'click', function ( event ) {
		if ( ! isOpen() ) {
			return;
		}

		if ( event.target.closest( '[data-wpcpm-handbook-open]' ) ) {
			setOpen( false );
			handedOff = true;

			return;
		}

		if ( event.target.closest( 'a[href]' ) && ! ( event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) ) {
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

	header.addEventListener( 'focusout', function ( event ) {
		var next = event.relatedTarget;

		if ( isOpen() && next && ! inside( next ) ) {
			setOpen( false );
		}
	} );

	if ( 'function' !== typeof window.MutationObserver ) {
		return;
	}

	var root = document.documentElement;
	var assistantOpen = root.classList.contains( 'wpcpm-hb-open' );

	new window.MutationObserver( function () {
		var now = root.classList.contains( 'wpcpm-hb-open' );

		if ( assistantOpen && ! now && handedOff ) {
			handedOff = false;
			button.focus();
		}

		assistantOpen = now;
	} ).observe( root, { attributes: true, attributeFilter: [ 'class' ] } );
}() );
