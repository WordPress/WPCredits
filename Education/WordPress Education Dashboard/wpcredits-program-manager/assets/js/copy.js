/**
 * The Copy button: `<button data-wpcpm-copy="#id">` copies the text of the element the selector
 * names. The clipboard API is asked first, in a secure context; when it is missing or refuses,
 * the text is selected instead and the person copies it with the keyboard, which is what the
 * plugin's forms script does for a claimed code. Either way the outcome is said in the
 * `.wpcpm-copy__status` beside the button, from the words the button carries, so the script
 * holds no sentence of its own.
 */
( function () {
	'use strict';

	function textOf( target ) {
		return 'string' === typeof target.value ? target.value : target.textContent || '';
	}

	function select( target ) {
		if ( 'function' === typeof target.select ) {
			target.focus();
			target.select();
			return;
		}

		if ( window.getSelection && document.createRange ) {
			var range = document.createRange();
			var selection = window.getSelection();

			range.selectNodeContents( target );
			selection.removeAllRanges();
			selection.addRange( range );
		}
	}

	function say( button, text ) {
		var status = button.parentNode ? button.parentNode.querySelector( '.wpcpm-copy__status' ) : null;

		if ( ! status ) {
			return;
		}

		// Emptied first and filled a moment later: a live region speaks when its text changes,
		// and "Copied." written over "Copied." is no change, so a second press would go unsaid.
		status.textContent = '';
		window.setTimeout( function () {
			status.textContent = text;
		}, 100 );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target && event.target.closest ? event.target.closest( '[data-wpcpm-copy]' ) : null;

		if ( ! button ) {
			return;
		}

		var target = document.querySelector( button.getAttribute( 'data-wpcpm-copy' ) );

		if ( ! target ) {
			return;
		}

		var fallback = function () {
			select( target );
			say( button, button.getAttribute( 'data-wpcpm-select-hint' ) || '' );
		};

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( textOf( target ) ).then( function () {
				say( button, button.getAttribute( 'data-wpcpm-copied' ) || '' );
			}, fallback );
		} else {
			fallback();
		}
	} );
}() );
