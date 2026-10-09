/**
 * The "Viewing as" switchers: a box above the list that narrows it as an Administrator types.
 *
 * The lists run long - about a hundred institutions, several hundred students - and a select
 * offers no search beyond the first letter typed. The server draws each list A to Z and the box
 * above it, hidden. This shows the box and, as the person types, takes out of the list every entry
 * whose name does not hold what was typed, anywhere in it and without regard to case or accents,
 * and puts each back in its place as the box empties.
 *
 * **The entry chosen is never taken out.** It is what Show would send, and a list whose chosen
 * entry vanished would quietly choose another. Nor does anything here submit: the list and its
 * Show button work exactly as they do without this file, and Enter in the box, which would submit
 * the form with whatever the list happened to hold, is kept from doing so.
 *
 * Entries are taken out of the list rather than hidden, because a hidden option still shows in
 * Safari's list. PHP owns the words: the box's label and the sentence for no match are in the
 * markup.
 */
( function () {
	'use strict';

	/**
	 * A name, or what was typed, as the two are compared: lower case, with the accents taken off.
	 *
	 * Unicode's decomposition takes the accent off É and ó. A few letters are letters of their own
	 * to it rather than a letter and an accent, Ł and Ø among them, and those are spelled the way a
	 * reader types them, so "lodz" finds Łódź as `remove_accents()` lets the server sort it.
	 *
	 * @param {string} text The text.
	 * @return {string} The text as compared.
	 */
	function fold( text ) {
		var folded = String( text ).toLowerCase();
		var plain = {
			'\u0142': 'l', // ł
			'\u00f8': 'o', // ø
			'\u0111': 'd', // đ
			'\u00f0': 'd', // ð
			'\u0127': 'h', // ħ
			'\u0131': 'i', // ı
			'\u00df': 'ss', // ß
			'\u00e6': 'ae', // æ
			'\u0153': 'oe', // œ
			'\u00fe': 'th', // þ
		};

		if ( folded.normalize ) {
			folded = folded.normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' );
		}

		return folded.replace( /[\u0142\u00f8\u0111\u00f0\u0127\u0131\u00df\u00e6\u0153\u00fe]/g, function ( letter ) {
			return plain[ letter ];
		} );
	}

	/**
	 * Which entries stay in the list for what was typed.
	 *
	 * Each entry whose name holds what was typed, anywhere in it, and the chosen entry whatever its
	 * name holds. Nothing typed, or only spaces, keeps them all.
	 *
	 * @param {string[]} keys     The entries' names as `fold()` reads them, in list order.
	 * @param {number}   selected The chosen entry's place in that order; -1 for none.
	 * @param {string}   query    What was typed.
	 * @return {Object} `keep`, a boolean for each entry, and `matched`, how many names held it.
	 */
	function narrow( keys, selected, query ) {
		var needle = fold( query ).trim();
		var keep = [];
		var matched = 0;

		keys.forEach( function ( key, index ) {
			var hit = '' === needle || -1 !== key.indexOf( needle );

			if ( hit ) {
				matched++;
			}

			keep.push( hit || index === selected );
		} );

		return { keep: keep, matched: matched };
	}

	/**
	 * Make one switcher's box work.
	 *
	 * @param {Element} row The box's row, as the server drew it.
	 */
	function wire( row ) {
		var input = row.querySelector( 'input' );
		var status = row.querySelector( '[role="status"]' );
		var select = input ? document.getElementById( input.getAttribute( 'aria-controls' ) ) : null;

		if ( ! select || ! status ) {
			return;
		}

		// Every entry in the order drawn, which is the order each goes back in.
		var entries = Array.prototype.slice.call( select.options );
		var keys = entries.map( function ( entry ) {
			return fold( entry.text );
		} );

		// The list is as wide as its longest name, and narrowing takes names out: left alone, the
		// column it shares with the box would shrink under the person typing, and the box and Show
		// with it. So the width it has with every entry in it is held as its minimum, and as the
		// box's, so that the two stay one width even where the column cannot give them the room. A
		// window that changes size, or a phone that turns, measures it again once it settles.
		var timer = null;

		/**
		 * Hold the list, and the box with it, at the width the list has with every entry in it.
		 */
		function hold() {
			var width;

			select.style.minWidth = '';
			input.style.minWidth = '';
			width = select.getBoundingClientRect().width;
			select.style.minWidth = width ? width + 'px' : '';
			input.style.minWidth = select.style.minWidth;
		}

		/**
		 * Put in the list the entries marked to stay, each in its place, and take out the rest.
		 *
		 * @param {boolean[]} keep Whether each entry stays, in the order drawn.
		 */
		function place( keep ) {
			var next = null;

			// From the end, so an entry put back goes in before the next one still in the list. The
			// chosen entry is always kept and so is never moved, which keeps it chosen.
			for ( var index = entries.length - 1; index >= 0; index-- ) {
				var entry = entries[ index ];
				var listed = entry.parentNode === select;

				if ( keep[ index ] ) {
					if ( ! listed ) {
						select.insertBefore( entry, next );
					}

					next = entry;
				} else if ( listed ) {
					select.removeChild( entry );
				}
			}
		}

		/**
		 * A window that changed size, settled for a moment before anything is measured.
		 */
		function resized() {
			window.clearTimeout( timer );

			// With something typed, every entry goes back for the measuring and the search is run
			// again straight after, in one go: nothing is drawn in between, and the box's text and
			// the entry chosen are left as they were.
			timer = window.setTimeout( function () {
				place( entries.map( function () {
					return true;
				} ) );
				hold();
				apply();
			}, 150 );
		}

		/**
		 * Take out what does not match, put back what does, and say so when nothing does.
		 */
		function apply() {
			var result = narrow( keys, entries.indexOf( select.options[ select.selectedIndex ] ), input.value );
			var text = 0 === result.matched ? status.getAttribute( 'data-wpcpm-none' ) : '';

			place( result.keep );

			// This runs on every keystroke and every settled resize, and a screen reader may read a
			// status line out again each time its text is set, even to the sentence it already
			// holds. So the line is set only when what it says changes.
			if ( status.textContent !== text ) {
				status.textContent = text;
			}
		}

		input.addEventListener( 'input', apply );

		input.addEventListener( 'keydown', function ( event ) {
			// Escape clears the box from inside it, which is where anyone who has just mistyped a
			// name already is.
			if ( 'Escape' === event.key && '' !== input.value ) {
				event.preventDefault();
				input.value = '';
				apply();
			}

			// Enter in a text box submits its form, and this form would post whatever the list
			// happened to hold. The list and Show decide what is sent, as they did before the box.
			if ( 'Enter' === event.key ) {
				event.preventDefault();
			}
		} );

		window.addEventListener( 'resize', resized );
		window.addEventListener( 'orientationchange', resized );

		// Shown before it is measured: the box shares the list's column, and its own width counts.
		row.hidden = false;
		hold();

		// A browser that kept what was typed in the box across a reload starts from it.
		if ( '' !== input.value ) {
			apply();
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.wpcpm-dashboard__switcher-find' ), wire );
	} );
}() );
