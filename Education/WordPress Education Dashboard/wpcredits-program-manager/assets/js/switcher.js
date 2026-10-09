/**
 * The "Viewing as" switchers: one field that drops down, takes typing and narrows its list as an
 * Administrator types.
 *
 * The lists run long - about a hundred institutions, several hundred students - and a select
 * offers no search beyond the first letter typed. The server draws each list A to Z as a select
 * and, beside it and hidden, a combobox: a text field, an empty list for its names and a status
 * line. This fills that list from the select's options, hides the select and shows the field in
 * its place, holding the name being viewed. It works as the WAI-ARIA combobox pattern's list
 * autocomplete with manual selection does:
 *
 * - a click on the field, or Down or Up, opens the whole list with the picked name highlighted,
 *   as a select opens on its choice, and a click selects the field's text, so that typing replaces
 *   it; a second click closes the list again, as a drop-down's does;
 * - typing narrows the list to the names that hold what was typed, anywhere in them and without
 *   regard to case or accents, and highlights the first; with no match the list says so;
 * - Up, Down, Home and End move the highlight, which a screen reader hears through
 *   `aria-activedescendant`, and the status line says how many names the list shows;
 * - Enter, or a click on a name, picks it: the field shows it and the select holds it;
 * - Escape, Tab or a click anywhere else closes the list and puts the picked name back.
 *
 * The page is about the name the server drew as chosen. The select carries `autocomplete="off"`, so a
 * browser that puts a form back after Back, which it does after the page has loaded, leaves it on
 * that name; the script starts the field and the current mark on it, and puts the field, the mark
 * and the select back on it when the page is shown again from the back-forward cache, as left.
 *
 * **Nothing here submits.** Picking a name does not open that page, and Enter in the field, which
 * would submit the form, is kept from doing so: Show decides, as it does without this file. The
 * select stays in the form as what it sends, hidden, which takes it out of sight, of the tab order
 * and of the accessibility tree; without the script it is the switcher, as before.
 *
 * PHP owns the words: the field's placeholder, the count's sentence and the sentence for no match
 * are in the markup.
 */
( function () {
	'use strict';

	// The list shows this many rows before it scrolls, the half row saying there is more.
	var ROWS = 10.5;

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
	 * The names that hold what was typed, anywhere in them. Nothing typed, or only spaces, is every
	 * name.
	 *
	 * @param {string[]} keys  The names as `fold()` reads them, in list order.
	 * @param {string}   query What was typed.
	 * @return {number[]} The places of the names that hold it, in list order.
	 */
	function narrow( keys, query ) {
		var needle = fold( query ).trim();
		var found = [];

		keys.forEach( function ( key, index ) {
			if ( '' === needle || -1 !== key.indexOf( needle ) ) {
				found.push( index );
			}
		} );

		return found;
	}

	/**
	 * Make one switcher's field work.
	 *
	 * Its parts are found through the ARIA that ties them together: the field names its list, the
	 * list names the label, and the label names the select it was drawn for.
	 *
	 * @param {Element} combo The field's block, as the server drew it.
	 */
	function wire( combo ) {
		var input = combo.querySelector( '[role="combobox"]' );
		var status = combo.querySelector( '[role="status"]' );
		var list = input ? document.getElementById( input.getAttribute( 'aria-controls' ) ) : null;
		var label = list ? document.getElementById( list.getAttribute( 'aria-labelledby' ) ) : null;
		var select = label ? document.getElementById( label.getAttribute( 'for' ) ) : null;

		if ( ! status || ! select ) {
			return;
		}

		var count = status.getAttribute( 'data-wpcpm-count' ) || '';
		var none = status.getAttribute( 'data-wpcpm-none' ) || '';

		// One row for each of the select's options, in its order, which is A to Z. A row's place is
		// its option's, so picking a row is choosing that option.
		var rows = Array.prototype.map.call( select.options, function ( option, index ) {
			var row = document.createElement( 'li' );

			row.setAttribute( 'id', select.getAttribute( 'id' ) + '-option-' + index );
			row.setAttribute( 'class', 'wpcpm-dashboard__switcher-option' );
			row.setAttribute( 'role', 'option' );
			row.setAttribute( 'aria-selected', 'false' );
			row.setAttribute( 'data-wpcpm-index', String( index ) );
			row.textContent = option.text;

			return row;
		} );
		var keys = Array.prototype.map.call( select.options, function ( option ) {
			return fold( option.text );
		} );

		// The row that says nothing matches. An option, as everything a listbox holds is, but
		// disabled, never highlighted and never picked.
		var empty = document.createElement( 'li' );

		empty.setAttribute( 'class', 'wpcpm-dashboard__switcher-empty' );
		empty.setAttribute( 'role', 'option' );
		empty.setAttribute( 'aria-disabled', 'true' );
		empty.textContent = none;

		// The option the select holds for a moment while it is measured: far wider than any page, so
		// the select is as wide as the room it is given.
		var wide = document.createElement( 'option' );

		wide.textContent = new Array( 401 ).join( 'M' );

		var shown = []; // The places of the rows the list holds, in its order.
		var active = -1; // Where in `shown` the highlighted row is; -1 for none.
		var open = false;
		var typed = false; // Whether the field holds something typed rather than the picked name.
		var timer = null;

		/**
		 * Set the status line, only when what it says changes: a screen reader may read a status
		 * line out again each time its text is set, even to the sentence it already holds.
		 *
		 * @param {string} text What it says.
		 */
		function say( text ) {
			if ( status.textContent !== text ) {
				status.textContent = text;
			}
		}

		/**
		 * Scroll the list so that a row is in view, as little as that takes.
		 *
		 * @param {Element} row The row.
		 */
		function reveal( row ) {
			var top = row.offsetTop;
			var bottom = top + row.offsetHeight;

			if ( top < list.scrollTop ) {
				list.scrollTop = top;
			} else if ( bottom > list.scrollTop + list.clientHeight ) {
				list.scrollTop = bottom - list.clientHeight;
			}
		}

		/**
		 * Highlight one row of the list, or none.
		 *
		 * @param {number} place Where in `shown`; -1 for none.
		 */
		function highlight( place ) {
			var row;

			if ( active >= 0 ) {
				row = rows[ shown[ active ] ];
				row.classList.remove( 'is-active' );
				row.setAttribute( 'aria-selected', 'false' );
			}

			active = place >= 0 && place < shown.length ? place : -1;

			if ( -1 === active ) {
				input.removeAttribute( 'aria-activedescendant' );

				return;
			}

			row = rows[ shown[ active ] ];
			row.classList.add( 'is-active' );
			row.setAttribute( 'aria-selected', 'true' );
			input.setAttribute( 'aria-activedescendant', row.getAttribute( 'id' ) );
			reveal( row );
		}

		/**
		 * What an element's sides add across or down: its padding and its border.
		 *
		 * @param {Element}  element The element.
		 * @param {string[]} sides   The two sides, as `[ 'Left', 'Right' ]`.
		 * @return {number} In px.
		 */
		function edges( element, sides ) {
			var style = window.getComputedStyle( element );

			return sides.reduce( function ( sum, side ) {
				return sum + ( parseFloat( style[ 'padding' + side ] ) || 0 ) + ( parseFloat( style[ 'border' + side + 'Width' ] ) || 0 );
			}, 0 );
		}

		/**
		 * Hold the list at about ten of its rows, measured, so it scrolls past them whatever size
		 * the theme gives a row. A row is measured at one line: the lowest of the rows shown, since
		 * a long name wraps to two at a phone's width, and ten of those would be twenty lines.
		 */
		function fit() {
			var row = shown.reduce( function ( least, index ) {
				var height = rows[ index ].offsetHeight;

				return height && ( ! least || height < least ) ? height : least;
			}, 0 ) || empty.offsetHeight;

			if ( row ) {
				list.style.maxHeight = ( row * ROWS + edges( list, [ 'Top', 'Bottom' ] ) ) + 'px';
			}
		}

		/**
		 * Open the list, or bring it up to date with what the field holds.
		 *
		 * With something typed it holds the names that match, the first highlighted, and with the
		 * picked name in the field it holds every name, the picked one highlighted.
		 */
		function show() {
			var all = ! typed || '' === fold( input.value ).trim();

			highlight( -1 );

			while ( list.firstChild ) {
				list.removeChild( list.firstChild );
			}

			shown = typed ? narrow( keys, input.value ) : keys.map( function ( key, index ) {
				return index;
			} );

			shown.forEach( function ( index ) {
				list.appendChild( rows[ index ] );
			} );

			if ( ! shown.length ) {
				list.appendChild( empty );
			}

			if ( ! open ) {
				open = true;
				list.hidden = false;
				input.setAttribute( 'aria-expanded', 'true' );
				combo.classList.add( 'is-open' );
			}

			fit();
			list.scrollTop = 0;
			highlight( typed ? ( all ? -1 : 0 ) : shown.indexOf( select.selectedIndex ) );
			// `%s`, or `%1$s` as a translation may number it.
			say( shown.length ? count.replace( /%(1\$)?s/, String( shown.length ) ) : none );
		}

		/**
		 * Close the list and empty the status line, so the next opening says its count again.
		 */
		function close() {
			highlight( -1 );

			if ( open ) {
				open = false;
				list.hidden = true;
				input.setAttribute( 'aria-expanded', 'false' );
				combo.classList.remove( 'is-open' );
			}

			say( '' );
		}

		/**
		 * Put the picked name back in the field and close the list.
		 */
		function restore() {
			var option = select.options[ select.selectedIndex ];

			typed = false;
			input.value = option ? option.text : '';
			close();
		}

		/**
		 * Set the select on a name, which is what Show sends, and mark that name's row as picked.
		 *
		 * @param {number} index The name's place in the select.
		 */
		function mark( index ) {
			var was = rows[ select.selectedIndex ];

			if ( was ) {
				was.classList.remove( 'is-current' );
			}

			select.selectedIndex = index;

			if ( rows[ index ] ) {
				rows[ index ].classList.add( 'is-current' );
			}
		}

		/**
		 * The place of the name the server drew as chosen, which is the name the page is about: its
		 * first option when it drew none chosen, as a select shows.
		 *
		 * @return {number}
		 */
		function own() {
			var place = 0;

			Array.prototype.some.call( select.options, function ( option, index ) {
				place = option.defaultSelected ? index : place;

				return option.defaultSelected;
			} );

			return place;
		}

		/**
		 * Pick a name: the select holds it, so Show sends it, and the field shows it, selected, so
		 * that what is typed next starts a new search rather than running on from the name.
		 *
		 * @param {number} index The name's place in the select.
		 */
		function pick( index ) {
			mark( index );
			restore();
			input.select();
		}

		/**
		 * The width the longest name takes in the field's own type, which a theme may set larger
		 * than the select's; 0 where the browser offers no canvas to measure with.
		 *
		 * @return {number} In px.
		 */
		function longest() {
			var canvas = document.createElement( 'canvas' );
			var context = canvas.getContext ? canvas.getContext( '2d' ) : null;
			var style = window.getComputedStyle( input );

			if ( ! context ) {
				return 0;
			}

			context.font = [ style.fontStyle, style.fontWeight, style.fontSize, style.fontFamily ].join( ' ' );

			return Array.prototype.reduce.call( select.options, function ( most, option ) {
				return Math.max( most, context.measureText( option.text ).width );
			}, 0 );
		}

		/**
		 * Hold the field at the width its longest name needs, so that it never moves while typing
		 * narrows its list: the select's width, or, where the field's type, padding and border take
		 * more, what the longest name takes in the field with them; never more than the room the
		 * page gives the select.
		 *
		 * The select is measured in its own place, shown beside the field, which is made no width at
		 * all for the measuring so that it does not widen the column they share; then again holding
		 * an option far wider than any page, which it is held to the room for, and that option is
		 * taken out at once. The field is never hidden for it: a field taken out of the page loses
		 * the focus it may hold. Nothing is drawn in between.
		 */
		function hold() {
			var width;
			var room;

			combo.style.width = '0px';
			select.hidden = false;
			width = select.getBoundingClientRect().width;
			select.appendChild( wide );
			room = select.getBoundingClientRect().width;
			select.removeChild( wide );
			select.hidden = true;
			width = Math.min( room, Math.max( width, Math.ceil( longest() ) + edges( input, [ 'Left', 'Right' ] ) ) );
			combo.style.width = width ? width + 'px' : '';
		}

		/**
		 * A window that changed size, measured again once it has settled for a moment.
		 */
		function resized() {
			window.clearTimeout( timer );
			timer = window.setTimeout( hold, 150 );
		}

		input.addEventListener( 'input', function () {
			typed = true;
			show();
		} );

		// A press on the field while it has the focus is kept from placing the caret: a browser does
		// that once the click is done, and would undo the selection the click makes. Without the
		// focus, the press is left alone, so that it gives the field the focus.
		input.addEventListener( 'mousedown', function ( event ) {
			if ( document.activeElement === input ) {
				event.preventDefault();
			}
		} );

		// A drop-down: a click opens the list, and a click on the field or its chevron while the list
		// is open closes it, the picked name back, as Escape does. Either way the name is selected,
		// so that what is typed next starts a new search.
		input.addEventListener( 'click', function () {
			if ( open ) {
				restore();
			} else {
				show();
			}

			input.select();
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var last = shown.length - 1;

			// The Enter that ends a composition belongs to the composition and picks nothing. Safari
			// sends it after the composition has ended, with the keyCode every key pressed during one
			// carries, and that Enter is still kept from sending the form.
			if ( event.isComposing ) {
				return;
			}

			if ( 229 === event.keyCode ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
				}

				return;
			}

			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();

				if ( ! open ) {
					show();
				} else if ( last >= 0 ) {
					highlight( 'ArrowDown' === event.key ? Math.min( active + 1, last ) : Math.max( active - 1, 0 ) );
				}
			} else if ( ( 'Home' === event.key || 'End' === event.key ) && open && last >= 0 ) {
				event.preventDefault();
				highlight( 'Home' === event.key ? 0 : last );
			} else if ( 'Enter' === event.key ) {
				// Enter in a text field submits its form. Show decides what is sent, and when.
				event.preventDefault();

				if ( open && active >= 0 ) {
					pick( shown[ active ] );
				}
			} else if ( 'Escape' === event.key && open ) {
				event.preventDefault();
				restore();
				input.select();
			}
		} );

		// Tab, or a click anywhere but the list: the picked name goes back.
		input.addEventListener( 'blur', restore );

		list.addEventListener( 'mousedown', function ( event ) {
			// Keeps the focus in the field, so that the click which follows picks rather than leaves.
			event.preventDefault();
		} );

		list.addEventListener( 'click', function ( event ) {
			var index = event.target.getAttribute ? event.target.getAttribute( 'data-wpcpm-index' ) : null;

			if ( null !== index ) {
				pick( parseInt( index, 10 ) );
			}
		} );

		window.addEventListener( 'resize', resized );
		window.addEventListener( 'orientationchange', resized );

		// A page from the back-forward cache comes back as it was left, the select on the name
		// picked last, though the page is about its own.
		window.addEventListener( 'pageshow', function ( event ) {
			if ( event.persisted ) {
				mark( own() );
				restore();
			}
		} );

		label.setAttribute( 'for', input.getAttribute( 'id' ) );
		mark( own() );
		restore();
		combo.hidden = false;
		hold();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.wpcpm-dashboard__switcher-combo' ), wire );
	} );
}() );
