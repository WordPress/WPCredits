/**
 * Student Duplicate Finder: the live count, Select all ready and Clear.
 *
 * The form works without this. It only counts the rows the ticked boxes stand for, in the words
 * the bar's data-template gives, and shows the two buttons, which ship hidden so that nothing on
 * the page does nothing when scripts are off.
 */
( function () {
	'use strict';

	// A submit disables the red button at once, so a slow response or an eager second click
	// cannot send the same delete twice.
	Array.prototype.slice.call( document.querySelectorAll( '.wpcpm-duplicates__delete' ) ).forEach( function ( button ) {
		if ( ! button.form ) {
			return;
		}

		button.form.addEventListener( 'submit', function () {
			button.disabled = true;
		} );
	} );

	var form = document.querySelector( '[data-wpcpm-duplicates]' );

	if ( ! form ) {
		return;
	}

	var count = form.querySelector( '[data-wpcpm-selection-count]' );
	var ready = form.querySelector( '[data-wpcpm-select-ready]' );
	var clear = form.querySelector( '[data-wpcpm-clear]' );
	var submit = form.querySelector( '[data-wpcpm-selection] button[type="submit"]' );

	function boxes() {
		return Array.prototype.slice.call( form.querySelectorAll( 'input[type="checkbox"][data-rows]' ) );
	}

	function update() {
		var tally = { students: 0, reports: 0, feedback: 0 };
		var total = 0;

		boxes().forEach( function ( box ) {
			if ( ! box.checked ) {
				return;
			}

			( box.getAttribute( 'data-rows' ) || '' ).split( ' ' ).forEach( function ( table ) {
				if ( Object.prototype.hasOwnProperty.call( tally, table ) ) {
					tally[ table ]++;
					total++;
				}
			} );
		} );

		if ( count && count.getAttribute( 'data-template' ) ) {
			count.textContent = count.getAttribute( 'data-template' )
				.replace( '%1$s', String( total ) )
				.replace( '%2$s', String( tally.students ) )
				.replace( '%3$s', String( tally.reports ) )
				.replace( '%4$s', String( tally.feedback ) );
		}

		if ( submit ) {
			submit.disabled = 0 === total;
		}
	}

	// Select all ready is printed only while deleting is on and no scan runs; without it the
	// server's disabled Review selection and its sentence stand as they are.
	if ( ! ready ) {
		return;
	}

	ready.hidden = false;
	ready.addEventListener( 'click', function () {
		form.querySelectorAll( 'input[data-ready]' ).forEach( function ( box ) {
			box.checked = true;
		} );
		update();
	} );

	if ( clear ) {
		clear.hidden = false;
		clear.addEventListener( 'click', function () {
			boxes().forEach( function ( box ) {
				box.checked = false;
			} );
			update();
		} );
	}

	form.addEventListener( 'change', update );

	update();
}() );
