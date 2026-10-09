'use strict';
/*
 * assets/js/switcher.js, run in node: the one field of a "Viewing as" switcher, driven as an
 * Administrator drives it, with the mouse and with the keyboard.
 *
 *   php bin/test-dashboard-switcher.php
 *
 * which runs, with the switchers' markup as WPCPM_Dashboards::render_switcher() draws it on stdin:
 *
 *   node bin/js/switcher-filter.js <path to switcher.js> -
 *
 * Two parts. First the script's two pure functions, `fold()` and `narrow()`, read out of its source
 * by name and run in a fresh context with no DOM at all. Then the whole script, run on a stand-in
 * for the page: the server's own markup, read into a small tree of elements that keep attributes,
 * classes, children and listeners, and fire events that bubble, as a browser's do. Each page is
 * driven as a person drives it: a click on the field, typing, Up, Down, Home, End, Enter, Escape, a
 * click on a name, and leaving the field by Tab or a click elsewhere. A click on a name is modeled
 * in a browser's order: the press, then the field losing the focus unless the press was kept from
 * moving it, then the click, which lands on the name only if it is still shown. Show sends what a
 * GET form sends, every named field in the form, so the field's own text, which carries no name,
 * is never part of it.
 *
 * The select has a width as a select's is: as wide as its longest name at 8px a letter and its 1px
 * border on either side, within the room the page gives it, and as wide as anything else in its
 * column, the field's block included, which is as wide as its inline width or, without one, a text
 * field's own 160px. The field has 8px of padding on its left, 34px on its right for the chevron
 * and a 1px border, and its type is 16px unless a page says otherwise; a canvas measures a text at
 * half its type's size a letter, so 8px in the field's own type. A hidden element measures 0, as one
 * out of the layout does. The list's rows are 32px each, two rows for a name over 40 letters, which
 * wraps; its border is 1px; and the window it sits in can change size, with its timers run by hand.
 *
 * The status line counts each time its text is set, since a screen reader may read it out again on
 * any of them, the same sentence or not.
 *
 * The last scenarios are mutation proofs: the script with accents no longer taken off, with the
 * press on a name no longer kept from moving the focus, and with its status line set whatever it
 * already says, run through the same steps, must each go wrong. A check that passes whatever the
 * script does would pass them too.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, and
 * exits 1 when any scenario differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'switcher.js' ) );

let ran = 0;
let failed = 0;

function scenario( name, want, build ) {
	let got;

	try {
		got = build();
	} catch ( error ) {
		got = 'threw: ' + error.message;
	}

	const ok = JSON.stringify( got ) === JSON.stringify( want );

	ran++;
	failed += ok ? 0 : 1;
	console.log( ( ok ? 'ok   ' : 'FAIL ' ) + name );

	if ( ! ok ) {
		console.log( '       exp: ' + JSON.stringify( want ) + '  got: ' + JSON.stringify( got ) );
	}
}

/**
 * One named function declaration out of a source, from `function <name>(` to the brace that
 * closes its body. The two functions read here hold no brace inside a string or a pattern, so
 * counting braces finds the end.
 *
 * @param {string} source The script.
 * @param {string} name   The function's name.
 * @return {string} Its declaration, or '' when the script has none of that name.
 */
function declaration( source, name ) {
	const start = source.indexOf( 'function ' + name + '(' );

	if ( -1 === start ) {
		return '';
	}

	let depth = 0;

	for ( let at = source.indexOf( '{', start ); at < source.length; at++ ) {
		if ( '{' === source[ at ] ) {
			depth++;
		} else if ( '}' === source[ at ] ) {
			depth--;

			if ( 0 === depth ) {
				return source.slice( start, at + 1 );
			}
		}
	}

	return '';
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';
const fold = declaration( source, 'fold' );
const narrow = declaration( source, 'narrow' );

// The server's markup, one page of HTML for each name the suite gives it.
let markup = {};

if ( '-' === process.argv[ 3 ] ) {
	try {
		markup = JSON.parse( fs.readFileSync( 0, 'utf8' ) );
	} catch ( error ) {
		markup = {};
	}
}

scenario( 'the script declares fold() and narrow()', [ true, true ], () => [ '' !== fold, '' !== narrow ] );
scenario( 'the server\'s markup was handed over: one switcher, short names, a long list, a list whose first name wraps, and the four dashboards\' switchers on one page', [ 'four', 'long', 'one', 'short', 'wrap' ], () => Object.keys( markup ).sort() );

// Run as ES5 runs them: nothing from the page, nothing from node.
const filter = ( '' !== fold && '' !== narrow )
	? vm.runInNewContext( '"use strict";\n' + fold + '\n' + narrow + '\n({ fold: fold, narrow: narrow });', {} )
	: { fold: () => '', narrow: () => [] };

/* ---- fold(): what is compared ---- */

scenario( 'fold() lowers the case', 'zoe academy', () => filter.fold( 'ZOE Academy' ) );
scenario( 'and takes the accents off, as remove_accents() does on the server', 'alvaro ecole sao paulo krakow', () => filter.fold( 'Álvaro École São Paulo Kraków' ) );
scenario( 'and the letters Unicode does not decompose, Ł among them', 'lodz oresund dakovo strasse', () => filter.fold( 'Łódź Øresund Đakovo Straße' ) );
scenario( 'and leaves the rest of the name as it is', 'student 10 (lab)', () => filter.fold( 'Student 10 (Lab)' ) );

/* ---- narrow(): which names the list shows ---- */

// The names as the server draws them, A to Z.
const labels = [ 'Álvaro University', 'bergen school', 'École 42', 'Kraków Lab School', 'Łódź Institute', 'Student 2', 'Student 10', 'Zoe Academy' ];
const keys = labels.map( ( label ) => filter.fold( label ) );

function found( query ) {
	return filter.narrow( keys, query ).map( ( index ) => labels[ index ] );
}

scenario( 'nothing typed shows every name, in the order drawn', labels, () => found( '' ) );
scenario( 'only spaces typed is nothing typed', labels, () => found( '   ' ) );
scenario( 'typed without the accent, the accented name is found: "krakow" finds Kraków', [ 'Kraków Lab School' ], () => found( 'krakow' ) );
scenario( 'typed with the accent, too', [ 'École 42' ], () => found( 'Éco' ) );
scenario( 'in capitals, the same', [ 'École 42' ], () => found( 'ECOLE' ) );
scenario( 'Ł typed as L', [ 'Łódź Institute' ], () => found( 'lodz' ) );
scenario( 'anywhere in the name, not only at its start', [ 'bergen school', 'Kraków Lab School' ], () => found( 'school' ) );
scenario( 'a number is matched as typed: "student 1" finds Student 10 and not Student 2', [ 'Student 10' ], () => found( 'student 1' ) );
scenario( 'the spaces around what was typed are not part of it', [ 'Álvaro University' ], () => found( '  alv  ' ) );
scenario( 'nothing matching shows no name at all', [], () => found( 'nothing like it' ) );

/* ---- the page: a small tree of elements ---- */

const ROW = 32;
const OWN = 160;

// The field's padding and border across: 8px, 34px for the chevron, and 1px on either side.
const EDGES = 44;

/** An element, as far as the script reaches one. */
class Element {
	constructor( document, tag ) {
		this.ownerDocument = document;
		this.tagName = tag.toUpperCase();
		this.attributes = {};
		this.childNodes = [];
		this.parentNode = null;
		this.listeners = {};
		this.style = {};
		this.ownText = '';
		this.writes = 0;
		this.scrollTop = 0;
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attributes, name ) ? this.attributes[ name ] : null;
	}

	setAttribute( name, value ) {
		this.attributes[ name ] = String( value );

		if ( 'hidden' === name ) {
			this.ownerDocument.hid( this );
		}
	}

	removeAttribute( name ) {
		delete this.attributes[ name ];
	}

	hasAttribute( name ) {
		return null !== this.getAttribute( name );
	}

	get id() {
		return this.getAttribute( 'id' ) || '';
	}

	set id( value ) {
		this.setAttribute( 'id', value );
	}

	get className() {
		return this.getAttribute( 'class' ) || '';
	}

	set className( value ) {
		this.setAttribute( 'class', value );
	}

	get hidden() {
		return this.hasAttribute( 'hidden' );
	}

	set hidden( value ) {
		if ( value ) {
			this.setAttribute( 'hidden', '' );
		} else {
			this.removeAttribute( 'hidden' );
		}
	}

	get classList() {
		const element = this;
		const names = () => element.className.split( /\s+/ ).filter( Boolean );

		return {
			add( name ) {
				if ( ! names().includes( name ) ) {
					element.className = names().concat( name ).join( ' ' );
				}
			},
			remove( name ) {
				element.className = names().filter( ( one ) => one !== name ).join( ' ' );
			},
			contains: ( name ) => names().includes( name ),
		};
	}

	get firstChild() {
		return this.childNodes[ 0 ] || null;
	}

	get lastChild() {
		return this.childNodes[ this.childNodes.length - 1 ] || null;
	}

	appendChild( child ) {
		if ( child.parentNode ) {
			child.parentNode.removeChild( child );
		}

		this.childNodes.push( child );
		child.parentNode = this;

		return child;
	}

	removeChild( child ) {
		const at = this.childNodes.indexOf( child );

		if ( -1 === at ) {
			throw new Error( 'removeChild(): not a child of this element' );
		}

		this.childNodes.splice( at, 1 );
		child.parentNode = null;

		return child;
	}

	get textContent() {
		return this.childNodes.length ? this.childNodes.map( ( child ) => child.textContent ).join( '' ) : this.ownText;
	}

	set textContent( text ) {
		this.childNodes.slice().forEach( ( child ) => this.removeChild( child ) );
		this.ownText = String( text );
		this.writes++;
	}

	// The selectors the script uses: a class, an attribute with a value, or a tag.
	matches( selector ) {
		const attribute = /^\[([a-z-]+)="([^"]*)"\]$/.exec( selector );

		if ( attribute ) {
			return this.getAttribute( attribute[ 1 ] ) === attribute[ 2 ];
		}

		if ( '.' === selector[ 0 ] ) {
			return this.classList.contains( selector.slice( 1 ) );
		}

		return this.tagName === selector.toUpperCase();
	}

	descendants() {
		return this.childNodes.reduce( ( all, child ) => all.concat( child, child.descendants() ), [] );
	}

	querySelector( selector ) {
		return this.descendants().find( ( one ) => one.matches( selector ) ) || null;
	}

	querySelectorAll( selector ) {
		return this.descendants().filter( ( one ) => one.matches( selector ) );
	}

	addEventListener( type, fn ) {
		( this.listeners[ type ] = this.listeners[ type ] || [] ).push( fn );
	}

	// An event at this element, bubbling up through its ancestors as a browser's does.
	fire( type, init ) {
		const event = Object.assign( {
			type,
			target: this,
			defaultPrevented: false,
			preventDefault() {
				this.defaultPrevented = true;
			},
		}, init || {} );

		for ( let node = this; node; node = node.parentNode ) {
			( node.listeners[ type ] || [] ).forEach( ( fn ) => fn( event ) );
		}

		return event;
	}

	// In the layout: neither it nor anything it sits in is hidden.
	get rendered() {
		for ( let node = this; node; node = node.parentNode ) {
			if ( node.hidden ) {
				return false;
			}
		}

		return true;
	}

	// A row of the list is 32px, and a name over 40 letters wraps to two; the list is as tall as its
	// rows, within its max-height, with a 1px border above and below. Anything out of the layout
	// measures 0.
	get offsetHeight() {
		if ( ! this.rendered ) {
			return 0;
		}

		if ( 'UL' === this.tagName ) {
			return this.clientHeight + 2;
		}

		return this.textContent.length > 40 ? 2 * ROW : ROW;
	}

	get offsetTop() {
		if ( ! this.rendered || ! this.parentNode ) {
			return 0;
		}

		const before = this.parentNode.childNodes.slice( 0, this.parentNode.childNodes.indexOf( this ) );

		return before.reduce( ( sum, row ) => sum + row.offsetHeight, 0 );
	}

	get clientHeight() {
		if ( ! this.rendered ) {
			return 0;
		}

		const most = parseFloat( this.style.maxHeight );
		const rows = this.childNodes.reduce( ( sum, row ) => sum + row.offsetHeight, 0 );

		return isNaN( most ) ? rows : Math.min( rows, most - 2 );
	}
}

/** A text field: its value, and what of it is selected. */
class Input extends Element {
	constructor( document, tag ) {
		super( document, tag );
		this.value = '';
		this.selection = null;
	}

	select() {
		this.selection = [ 0, this.value.length ];
	}
}

/** An option of a select. */
class Option extends Element {
	constructor( document, tag ) {
		super( document, tag );
		this.selected = false;
	}

	get value() {
		return this.getAttribute( 'value' ) || '';
	}

	get text() {
		return this.textContent;
	}

	// Chosen in the markup, which is the page's own name whatever the select holds now.
	get defaultSelected() {
		return this.hasAttribute( 'selected' );
	}
}

/** A single select, with the width a select has. */
class Select extends Element {
	get options() {
		return this.childNodes.filter( ( child ) => 'OPTION' === child.tagName );
	}

	get selectedIndex() {
		return this.options.findIndex( ( option ) => option.selected );
	}

	set selectedIndex( index ) {
		this.options.forEach( ( option, at ) => {
			option.selected = at === index;
		} );
	}

	get value() {
		const chosen = this.options[ this.selectedIndex ];

		return chosen ? chosen.value : '';
	}

	// As wide as its longest name, or as anything else in its column, the field's block included,
	// within the room the page gives it. Out of the layout, 0.
	getBoundingClientRect() {
		if ( ! this.rendered ) {
			return { width: 0 };
		}

		const longest = this.options.reduce( ( most, option ) => Math.max( most, option.text.length * 8 + 2 ), 0 );
		const combo = this.parentNode.querySelector( '.wpcpm-dashboard__switcher-combo' );
		let beside = 0;

		if ( combo && combo.rendered ) {
			beside = '' === ( combo.style.width || '' ) ? OWN : parseFloat( combo.style.width );
		}

		return { width: Math.min( this.ownerDocument.room, Math.max( longest, beside ) ) };
	}
}

/** A canvas, whose context measures a text at half its type's size a letter; or none, on a page with canvases off. */
class Canvas extends Element {
	getContext() {
		if ( false === this.ownerDocument.canvas ) {
			return null;
		}

		return {
			font: '10px sans-serif',
			measureText( text ) {
				const size = /(\d+(?:\.\d+)?)px/.exec( this.font );

				return { width: String( text ).length * ( size ? parseFloat( size[ 1 ] ) / 2 : 5 ) };
			},
		};
	}
}

/** The document: a body, the elements in it by ID, and what the script asks of it. */
class Document {
	constructor() {
		this.listeners = {};
		this.room = 400;
		this.activeElement = null;
		this.hidden = [];
		this.body = new Element( this, 'body' );
	}

	// Every element given the hidden attribute, in order, so a scenario can see what was hidden.
	hid( element ) {
		this.hidden.push( element );
	}

	createElement( tag ) {
		const kinds = { input: Input, option: Option, select: Select, canvas: Canvas };

		return new ( kinds[ tag.toLowerCase() ] || Element )( this, tag );
	}

	getElementById( id ) {
		return this.body.descendants().find( ( one ) => one.id === id ) || null;
	}

	querySelectorAll( selector ) {
		return this.body.querySelectorAll( selector );
	}

	addEventListener( type, fn ) {
		( this.listeners[ type ] = this.listeners[ type ] || [] ).push( fn );
	}
}

/**
 * The server's markup read into the document's body: tags, their attributes and their text, with
 * the entities esc_html() and esc_attr() write read back.
 *
 * @param {Document} document The document.
 * @param {string}   html     The markup.
 */
function read( document, html ) {
	const decode = ( text ) => text.replace( /&quot;/g, '"' ).replace( /&#0?39;/g, '\'' ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&amp;/g, '&' );
	const stack = [ document.body ];
	const tokens = /<\/([a-z]+)\s*>|<([a-z]+)((?:\s+[a-z-]+(?:=(?:"[^"]*"|'[^']*'))?)*)\s*(\/?)>|([^<]+)/gi;
	let token;

	while ( ( token = tokens.exec( html ) ) ) {
		if ( token[ 1 ] ) {
			while ( stack.length > 1 && stack.pop().tagName !== token[ 1 ].toUpperCase() ) {
				// Up to the element the closing tag closes.
			}
		} else if ( token[ 2 ] ) {
			const element = document.createElement( token[ 2 ] );
			const attributes = /([a-z-]+)(?:=(?:"([^"]*)"|'([^']*)'))?/gi;
			let attribute;

			while ( ( attribute = attributes.exec( token[ 3 ] ) ) ) {
				element.attributes[ attribute[ 1 ] ] = decode( attribute[ 2 ] || attribute[ 3 ] || '' );
			}

			if ( 'OPTION' === element.tagName ) {
				element.selected = element.hasAttribute( 'selected' );
			}

			stack[ stack.length - 1 ].appendChild( element );

			if ( ! token[ 4 ] && 'INPUT' !== element.tagName ) {
				stack.push( element );
			}
		} else if ( '' !== token[ 5 ].trim() ) {
			stack[ stack.length - 1 ].ownText += decode( token[ 5 ] );
		}
	}
}

/**
 * A page of the server's markup with the script run on it and its DOMContentLoaded handled.
 *
 * @param {string} script The script's source.
 * @param {string} name   Which of the server's pages.
 * @param {number} room   The room the page gives a list, in px.
 * @param {Object} more   `font`, the field's type, as `16px`; `canvas: false` for a page with no canvas;
 *                        `count`, a count's sentence in place of the server's, as a translation
 *                        has it; `restored`, a value the select already holds when the script starts
 *                        in place of the drawn one (a browser restores a form only after the page
 *                        has loaded, which `autocomplete="off"` prevents, so this is the start-up
 *                        call's own case).
 * @return {Object} The document, its switchers and the window's timers.
 */
function page( script, name, room, more ) {
	const document = new Document();
	const timers = new Map();
	const listeners = {};
	const font = ( more && more.font ) || '16px';
	let made = 0;

	document.room = room || 400;
	document.canvas = more && false === more.canvas ? false : true;
	read( document, markup[ name ] || '' );

	if ( more && more.count ) {
		document.body.querySelector( '[role="status"]' ).setAttribute( 'data-wpcpm-count', more.count );
	}

	if ( more && more.restored ) {
		document.body.querySelector( 'select' ).options.forEach( ( option ) => {
			option.selected = option.value === more.restored;
		} );
	}

	const forms = document.querySelectorAll( 'form' );

	forms.forEach( ( form ) => {
		form.submits = 0;
		form.submit = () => form.submits++;
		form.requestSubmit = () => form.submits++;
	} );

	const window = {
		addEventListener( type, fn ) {
			( listeners[ type ] = listeners[ type ] || [] ).push( fn );
		},
		setTimeout( fn ) {
			timers.set( ++made, fn );

			return made;
		},
		clearTimeout( id ) {
			timers.delete( id );
		},
		// The list's padding and border, and the field's type, padding and border, as the stylesheet
		// draws them.
		getComputedStyle( element ) {
			if ( 'UL' === element.tagName ) {
				return { paddingTop: '0px', paddingBottom: '0px', borderTopWidth: '1px', borderBottomWidth: '1px' };
			}

			if ( 'INPUT' === element.tagName ) {
				return { fontStyle: 'normal', fontWeight: '400', fontSize: font, fontFamily: 'sans-serif', paddingLeft: '8px', paddingRight: '34px', borderLeftWidth: '1px', borderRightWidth: '1px' };
			}

			return {};
		},
	};

	vm.runInNewContext( script, { window, document } );
	( document.listeners.DOMContentLoaded || [] ).forEach( ( fn ) => fn() );

	return {
		document,
		switchers: forms.map( ( form ) => drive( form ) ),
		resize( wide, type ) {
			document.room = wide;
			( listeners[ type || 'resize' ] || [] ).forEach( ( fn ) => fn( { type: type || 'resize' } ) );
		},
		// The page shown again, from the back-forward cache when `persisted`, as it was left.
		pageshow: ( persisted ) => ( listeners.pageshow || [] ).forEach( ( fn ) => fn( { type: 'pageshow', persisted } ) ),
		waiting: () => timers.size,
		flush() {
			const due = Array.from( timers.values() );

			timers.clear();
			due.forEach( ( fn ) => fn() );
		},
	};
}

/**
 * One switcher's parts, and what a person does with them.
 *
 * @param {Element} form The switcher's form.
 * @return {Object}
 */
function drive( form ) {
	const document = form.ownerDocument;
	const select = form.querySelector( 'select' );
	const combo = form.querySelector( '.wpcpm-dashboard__switcher-combo' );
	const input = form.querySelector( '[role="combobox"]' );
	const list = form.querySelector( '[role="listbox"]' );
	const status = form.querySelector( '[role="status"]' );
	const label = form.querySelector( 'label' );
	const rows = () => list.childNodes.filter( ( row ) => row.rendered );
	const row = ( text ) => rows().find( ( one ) => one.textContent === text );

	return {
		form,
		document,
		select,
		combo,
		input,
		list,
		status,
		label,
		// A click on the field: the press, which gives the field the focus unless it is kept from its
		// default, then the click. A press on a field that already had the focus, left to the browser,
		// puts the caret where it pressed once the click is done, which undoes a selection made during
		// the click.
		click() {
			const focused = document.activeElement === input;
			const press = input.fire( 'mousedown' );

			if ( ! press.defaultPrevented ) {
				document.activeElement = input;
			}

			input.fire( 'click' );

			if ( focused && ! press.defaultPrevented && input.selection ) {
				input.selection = [ input.value.length, input.value.length ];
			}
		},
		type( text ) {
			document.activeElement = input;
			input.value = text;
			input.fire( 'input' );
		},
		key( name, more ) {
			document.activeElement = input;

			return input.fire( 'keydown', Object.assign( { key: name }, more || {} ) ).defaultPrevented;
		},
		// Tab, or a click anywhere else: the field loses the focus.
		leave() {
			document.activeElement = null;
			input.fire( 'blur' );
		},
		// A click on a row: the press, the focus moving unless the press kept it, then the click, on
		// the row only if the row is still shown.
		choose( text ) {
			const target = row( text );
			const press = target.fire( 'mousedown' );

			if ( ! press.defaultPrevented ) {
				input.fire( 'blur' );
			}

			if ( target.rendered ) {
				target.fire( 'click' );
			}

			return press.defaultPrevented;
		},
		open: () => ! list.hidden && 'true' === input.getAttribute( 'aria-expanded' ),
		// The three ways the list is told open or closed: the list's hidden attribute, the field's
		// aria-expanded, and the field's block's is-open, which a theme dresses.
		signs: () => [ list.hidden ? 'hidden' : 'shown', input.getAttribute( 'aria-expanded' ), combo.classList.contains( 'is-open' ) ? 'is-open' : 'not open' ],
		listed: () => ( list.hidden ? [] : rows().map( ( one ) => one.textContent ) ),
		active() {
			const id = input.getAttribute( 'aria-activedescendant' );
			const one = id ? document.getElementById( id ) : null;

			return one && one.parentNode === list ? one.textContent : null;
		},
		// The rows marked as the highlighted one: by class, and by aria-selected.
		marked: () => [
			list.childNodes.filter( ( one ) => one.classList.contains( 'is-active' ) ).map( ( one ) => one.textContent ),
			list.childNodes.filter( ( one ) => 'true' === one.getAttribute( 'aria-selected' ) ).map( ( one ) => one.textContent ),
		],
		current: () => list.childNodes.filter( ( one ) => one.classList.contains( 'is-current' ) ).map( ( one ) => one.textContent ),
		// What Show sends: every named field in the form, as a GET form sends it.
		show: () => form.descendants().filter( ( one ) => one.hasAttribute( 'name' ) && ! one.hasAttribute( 'disabled' ) ).map( ( one ) => [ one.getAttribute( 'name' ), one.value ] ),
		field: () => input.value,
		value: () => select.value,
		said: () => status.textContent,
		writes: () => status.writes,
	};
}

const one = () => page( source, 'one' ).switchers[ 0 ];
const ALL = labels;

/* ---- the field takes the list's place ---- */

scenario( 'the script shows the field and hides the select, which the hidden attribute takes out of sight, of the tab order and of the accessibility tree', { combo: false, select: true }, () => {
	const s = one();

	return { combo: s.combo.hidden, select: s.select.hidden };
} );

scenario( 'the label now names the field, and the list of names is labeled by it', { label: 'wpcpm-test-switcher-input', list: 'wpcpm-test-switcher-label', id: 'wpcpm-test-switcher-label' }, () => {
	const s = one();

	return { label: s.label.getAttribute( 'for' ), list: s.list.getAttribute( 'aria-labelledby' ), id: s.label.id };
} );

scenario( 'the field holds the name being viewed, its list closed and empty of highlights', { field: 'Student 10', expanded: 'false', hidden: true, active: null }, () => {
	const s = one();

	return { field: s.field(), expanded: s.input.getAttribute( 'aria-expanded' ), hidden: s.list.hidden, active: s.input.getAttribute( 'aria-activedescendant' ) };
} );

scenario( 'the field\'s list is built from the select\'s options, A to Z as drawn, each an option with an ID of its own', { names: ALL, roles: 8, ids: 8, values: true }, () => {
	const s = one();

	s.click();

	const items = s.list.childNodes;

	return {
		names: s.listed(),
		roles: items.filter( ( item ) => 'option' === item.getAttribute( 'role' ) ).length,
		ids: new Set( items.map( ( item ) => item.id ).filter( ( id ) => 0 === id.indexOf( 'wpcpm-test-switcher-' ) ) ).size,
		values: items.every( ( item, at ) => String( at ) === item.getAttribute( 'data-wpcpm-index' ) ),
	};
} );

scenario( 'the name being viewed is the one marked as picked', [ 'Student 10' ], () => {
	const s = one();

	s.click();

	return s.current();
} );

/* ---- opening ---- */

scenario( 'a click on the field opens the whole list, with the name being viewed highlighted', { open: true, listed: ALL, active: 'Student 10', expanded: 'true' }, () => {
	const s = one();

	s.click();

	return { open: s.open(), listed: s.listed(), active: s.active(), expanded: s.input.getAttribute( 'aria-expanded' ) };
} );

scenario( 'and selects the field\'s text, so that typing replaces it', [ 0, 10 ], () => {
	const s = one();

	s.click();

	return s.input.selection;
} );

scenario( 'Down opens it too, kept from moving the caret', { prevented: true, open: true, active: 'Student 10' }, () => {
	const s = one();

	return { prevented: s.key( 'ArrowDown' ), open: s.open(), active: s.active() };
} );

scenario( 'and so does Up', { prevented: true, open: true, active: 'Student 10' }, () => {
	const s = one();

	return { prevented: s.key( 'ArrowUp' ), open: s.open(), active: s.active() };
} );

// The field is a drop-down: a click opens its list, and a second click, on the field or on its
// chevron, closes it again as Escape does.
scenario( 'a click on the field while the list is open closes it and puts the picked name back, as Escape does', { open: false, signs: [ 'hidden', 'false', 'not open' ], field: 'Student 10', value: 'rec10', selection: [ 0, 10 ], said: '' }, () => {
	const s = one();

	s.type( 'stu' );
	s.key( 'ArrowDown' );
	s.input.selection = null;
	s.click();

	return { open: s.open(), signs: s.signs(), field: s.field(), value: s.value(), selection: s.input.selection, said: s.said() };
} );

scenario( 'a click on a field without the focus gives it the focus, so typing reaches it', true, () => {
	const s = one();

	s.click();

	return s.document.activeElement === s.input;
} );

scenario( 'a second click closes it with the name selected, so what is typed next starts a new search', { open: false, selection: [ 0, 10 ] }, () => {
	const s = one();

	s.click();
	s.click();

	return { open: s.open(), selection: s.input.selection };
} );

scenario( 'and a click on the field after a pick opens the list with the name selected', { open: true, selection: [ 0, 11 ] }, () => {
	const s = one();

	s.type( 'zoe' );
	s.key( 'Enter' );
	s.click();

	return { open: s.open(), selection: s.input.selection };
} );

scenario( 'and a click after that opens it again, on the picked name', { open: true, active: 'Student 10', listed: 8 }, () => {
	const s = one();

	s.click();
	s.click();
	s.click();

	return { open: s.open(), active: s.active(), listed: s.listed().length };
} );

scenario( 'a translation that numbers its placeholder gets the count in its place too', 'Names shown: 8', () => {
	const s = page( source, 'one', 400, { count: 'Names shown: %1$s' } ).switchers[ 0 ];

	s.click();

	return s.said();
} );

scenario( 'the status line says how many names the list shows', 'Names in the list: 8', () => {
	const s = one();

	s.click();

	return s.said();
} );

scenario( 'the list scrolls past about ten rows: at most ten and a half of its rows high, border included', { short: ( ROW * 10.5 + 2 ) + 'px', long: ( ROW * 10.5 + 2 ) + 'px', scrolls: true }, () => {
	const s = one();
	const l = page( source, 'long' ).switchers[ 0 ];

	s.click();
	l.click();

	return { short: s.list.style.maxHeight, long: l.list.style.maxHeight, scrolls: l.list.clientHeight < 30 * ROW };
} );

scenario( 'where the first name wraps to two lines, the list is still about ten one-line rows high', ( ROW * 10.5 + 2 ) + 'px', () => {
	const s = page( source, 'wrap' ).switchers[ 0 ];

	s.click();

	return s.list.style.maxHeight;
} );

scenario( 'and so it is when typing leaves only the name that wraps first', ( ROW * 10.5 + 2 ) + 'px', () => {
	const s = page( source, 'wrap' ).switchers[ 0 ];

	s.type( 'name' );

	return s.list.style.maxHeight;
} );

/* ---- typing ---- */

scenario( 'typing narrows the list to the names that hold it, anywhere and without regard to accents', { krakow: [ 'Kraków Lab School' ], school: [ 'bergen school', 'Kraków Lab School' ], lodz: [ 'Łódź Institute' ] }, () => {
	const s = one();
	const out = {};

	[ 'krakow', 'school', 'lodz' ].forEach( ( query ) => {
		s.type( query );
		out[ query ] = s.listed();
	} );

	return out;
} );

scenario( 'the first match is highlighted, so Enter picks it', { listed: [ 'Student 2', 'Student 10' ], active: 'Student 2' }, () => {
	const s = one();

	s.type( 'stu' );

	return { listed: s.listed(), active: s.active() };
} );

scenario( 'typing opens a closed list', { open: true, listed: [ 'Zoe Academy' ] }, () => {
	const s = one();

	s.type( 'zoe' );

	return { open: s.open(), listed: s.listed() };
} );

scenario( 'and the count follows what it shows', [ 'Names in the list: 2', 'Names in the list: 1' ], () => {
	const s = one();
	const said = [];

	s.type( 'stu' );
	said.push( s.said() );
	s.type( 'student 1' );
	said.push( s.said() );

	return said;
} );

scenario( 'an emptied field shows every name and highlights none', { listed: ALL, active: null }, () => {
	const s = one();

	s.type( 'zoe' );
	s.type( '' );

	return { listed: s.listed(), active: s.active() };
} );

scenario( 'typing alone never changes what Show sends', 'rec10', () => {
	const s = one();

	s.type( 'zoe' );
	s.key( 'ArrowDown' );

	return s.value();
} );

/* ---- moving ---- */

scenario( 'Down and Up move the highlight one name at a time and stop at the ends, Home and End go to them', [ 'Zoe Academy', 'Zoe Academy', 'Student 10', 'Álvaro University', 'Álvaro University', 'Zoe Academy', 'Álvaro University' ], () => {
	const s = one();
	const seen = [];

	s.click();
	// Student 10 is the seventh of eight, Zoe Academy the last.
	s.key( 'ArrowDown' );
	seen.push( s.active() );
	s.key( 'ArrowDown' );
	seen.push( s.active() );
	s.key( 'ArrowUp' );
	seen.push( s.active() );
	s.key( 'Home' );
	seen.push( s.active() );
	s.key( 'ArrowUp' );
	seen.push( s.active() );
	s.key( 'End' );
	seen.push( s.active() );
	s.key( 'Home' );
	seen.push( s.active() );

	return seen;
} );

scenario( 'each move is kept from moving the caret', [ true, true, true, true ], () => {
	const s = one();

	s.click();

	return [ s.key( 'ArrowDown' ), s.key( 'ArrowUp' ), s.key( 'Home' ), s.key( 'End' ) ];
} );

scenario( 'Home and End are left to the field while the list is closed', { home: false, end: false, open: false }, () => {
	const s = one();

	return { home: s.key( 'Home' ), end: s.key( 'End' ), open: s.open() };
} );

scenario( 'Home and End are left to the field once the list has been opened and closed again, too', { home: false, end: false, open: false, active: null }, () => {
	const s = one();

	s.click();
	s.key( 'Escape' );

	return { home: s.key( 'Home' ), end: s.key( 'End' ), open: s.open(), active: s.input.getAttribute( 'aria-activedescendant' ) };
} );

scenario( 'the highlight is announced: aria-activedescendant names it, the one row marked by is-active and aria-selected', { active: 'Student 2', marked: [ [ 'Student 2' ], [ 'Student 2' ] ], others: 'false' }, () => {
	const s = one();

	s.click();
	s.key( 'ArrowUp' );
	s.key( 'ArrowUp' );
	s.key( 'ArrowDown' );

	const others = s.list.childNodes.filter( ( item ) => 'Student 2' !== item.textContent ).map( ( item ) => item.getAttribute( 'aria-selected' ) );

	return { active: s.active(), marked: s.marked(), others: Array.from( new Set( others ) ).join() };
} );

scenario( 'down a long list, the highlight is scrolled into view, and back up', { end: 30 * ROW - ( ROW * 10.5 ), home: 0, middle: true }, () => {
	const s = page( source, 'long' ).switchers[ 0 ];

	s.click();
	s.key( 'End' );

	const end = s.list.scrollTop;

	s.key( 'Home' );

	const home = s.list.scrollTop;

	for ( let step = 0; step < 15; step++ ) {
		s.key( 'ArrowDown' );
	}

	const item = s.list.childNodes[ 15 ];

	return { end, home, middle: item.offsetTop >= s.list.scrollTop && item.offsetTop + ROW <= s.list.scrollTop + s.list.clientHeight };
} );

scenario( 'a field emptied while the list was scrolled to the name viewed shows the whole list from its top', { before: true, after: 0 }, () => {
	const s = page( source, 'long' ).switchers[ 0 ];

	s.click();

	const before = s.list.scrollTop > 0;

	s.type( '' );

	return { before, after: s.list.scrollTop };
} );

scenario( 'opening a long list shows the name being viewed', true, () => {
	const s = page( source, 'long' ).switchers[ 0 ];

	s.click();

	const item = s.list.childNodes[ 24 ];

	return 'Name 25' === s.active() && item.offsetTop >= s.list.scrollTop && item.offsetTop + ROW <= s.list.scrollTop + s.list.clientHeight;
} );

/* ---- open and closed, told three ways ---- */

// The list's hidden attribute closes it, aria-expanded tells a screen reader, and is-open lets a
// theme dress the field; each is checked on its own, so none can drift from the other two.
scenario( 'open and closed are told the same three ways at every step: the list shown, aria-expanded, and is-open on the field\'s block', [
	[ 'drawn', 'hidden', 'false', 'not open' ],
	[ 'click', 'shown', 'true', 'is-open' ],
	[ 'Enter', 'hidden', 'false', 'not open' ],
	[ 'Down', 'shown', 'true', 'is-open' ],
	[ 'Escape', 'hidden', 'false', 'not open' ],
	[ 'typing', 'shown', 'true', 'is-open' ],
	[ 'leaving', 'hidden', 'false', 'not open' ],
	[ 'no match', 'shown', 'true', 'is-open' ],
	[ 'click on a name', 'hidden', 'false', 'not open' ],
], () => {
	const s = one();
	const seen = [ [ 'drawn' ].concat( s.signs() ) ];
	const step = ( name, act ) => {
		act();
		seen.push( [ name ].concat( s.signs() ) );
	};

	step( 'click', () => s.click() );
	step( 'Enter', () => s.key( 'Enter' ) );
	step( 'Down', () => s.key( 'ArrowDown' ) );
	step( 'Escape', () => s.key( 'Escape' ) );
	step( 'typing', () => s.type( 'stu' ) );
	step( 'leaving', () => s.leave() );
	step( 'no match', () => s.type( 'nothing like it' ) );
	step( 'click on a name', () => {
		s.type( 'zoe' );
		s.choose( 'Zoe Academy' );
	} );

	return seen;
} );

/* ---- picking ---- */

scenario( 'Enter picks the highlighted name: the field shows it, the list holds it, and the list closes', { prevented: true, field: 'Zoe Academy', value: 'recZOE', open: false, active: null, said: '' }, () => {
	const s = one();

	s.type( 'zoe' );

	return { prevented: s.key( 'Enter' ), field: s.field(), value: s.value(), open: s.open(), active: s.input.getAttribute( 'aria-activedescendant' ), said: s.said() };
} );

scenario( 'and Show sends what was picked, and only that', [ [ 'wpcpm_test_view', 'recZOE' ] ], () => {
	const s = one();

	s.type( 'zoe' );
	s.key( 'Enter' );

	return s.show();
} );

scenario( 'Show sends the name being viewed when nothing was picked', [ [ 'wpcpm_test_view', 'rec10' ] ], () => one().show() );

scenario( 'picking never submits: Show alone sends the form', 0, () => {
	const s = one();

	s.type( 'zoe' );
	s.key( 'Enter' );
	s.click();
	s.choose( 'École 42' );

	return s.form.submits;
} );

scenario( 'a click on a name picks it, and the press keeps the focus in the field', { kept: true, field: 'École 42', value: 'recECOLE', open: false }, () => {
	const s = one();

	s.click();

	const kept = s.choose( 'École 42' );

	return { kept, field: s.field(), value: s.value(), open: s.open() };
} );

scenario( 'a click on a narrowed name picks it as well', { field: 'Student 2', value: 'rec2' }, () => {
	const s = one();

	s.type( 'student' );
	s.choose( 'Student 2' );

	return { field: s.field(), value: s.value() };
} );

scenario( 'the name picked is the one marked and highlighted the next time the list opens', { current: [ 'École 42' ], active: 'École 42' }, () => {
	const s = one();

	s.click();
	s.choose( 'École 42' );
	s.click();

	return { current: s.current(), active: s.active() };
} );

/* ---- coming back to the page ---- */

// The page is somebody's: the name the server drew as chosen. A browser may hand the page back with
// the select on another name, from the back-forward cache as the person left it, or by putting a
// form back after Back; Show would then open somebody the page is not about.
scenario( 'a page shown again from the back-forward cache is put back on its own name: the select, the field, and the name marked as picked', { left: 'recZOE', value: 'rec10', field: 'Student 10', current: [ 'Student 10' ], open: false, sent: [ [ 'wpcpm_test_view', 'rec10' ] ] }, () => {
	const p = page( source, 'one' );
	const s = p.switchers[ 0 ];

	s.type( 'zoe' );
	s.key( 'Enter' );

	const left = s.value();

	s.type( 'ber' );
	p.pageshow( true );

	const field = s.field();
	const open = s.open();

	s.click();

	return { left, value: s.value(), field, current: s.current(), open, sent: s.show() };
} );

scenario( 'a page shown for the first time is left as it is', 'recZOE', () => {
	const p = page( source, 'one' );
	const s = p.switchers[ 0 ];

	s.type( 'zoe' );
	s.key( 'Enter' );
	p.pageshow( false );

	return s.value();
} );

scenario( 'a select already off the drawn name when the script starts begins on the page\'s own name', { value: 'rec10', field: 'Student 10' }, () => {
	const s = page( source, 'one', 400, { restored: 'recZOE' } ).switchers[ 0 ];

	return { value: s.value(), field: s.field() };
} );

/* ---- leaving without picking ---- */

scenario( 'Escape closes the list and puts the picked name back', { prevented: true, field: 'Student 10', value: 'rec10', open: false, said: '' }, () => {
	const s = one();

	s.type( 'zoe' );

	return { prevented: s.key( 'Escape' ), field: s.field(), value: s.value(), open: s.open(), said: s.said() };
} );

scenario( 'after Enter, a click on a name or Escape the name in the field is selected, so typing again starts a new search', [ [ 0, 11 ], [ 0, 8 ], [ 0, 10 ] ], () => {
	const s = one();
	const seen = [];

	s.type( 'zoe' );
	s.key( 'Enter' );
	seen.push( s.input.selection );
	s.input.selection = null;
	s.type( 'ecole' );
	s.choose( 'École 42' );
	seen.push( s.input.selection );

	const t = one();

	t.type( 'zoe' );
	t.key( 'Escape' );
	seen.push( t.input.selection );

	return seen;
} );

scenario( 'but leaving the field selects nothing, so the focus goes where the person sent it', null, () => {
	const s = one();

	s.type( 'zoe' );
	s.leave();

	return s.input.selection;
} );

scenario( 'Escape with the list closed is left to the browser', false, () => one().key( 'Escape' ) );

scenario( 'Tab, or a click elsewhere, closes the list and puts the picked name back, picking nothing', { field: 'Student 10', value: 'rec10', open: false, active: null }, () => {
	const s = one();

	s.type( 'zoe' );
	s.leave();

	return { field: s.field(), value: s.value(), open: s.open(), active: s.input.getAttribute( 'aria-activedescendant' ) };
} );

scenario( 'after a pick, leaving puts back the name picked, not the one first viewed', { field: 'Zoe Academy', value: 'recZOE' }, () => {
	const s = one();

	s.type( 'zoe' );
	s.key( 'Enter' );
	s.type( 'ber' );
	s.leave();

	return { field: s.field(), value: s.value() };
} );

/* ---- no match ---- */

scenario( 'with no match the list holds one row that says so, which is no choice, and the status line says it too', { open: true, listed: [ 'No entries match that search.' ], empty: true, disabled: 'true', index: null, active: null, said: 'No entries match that search.' }, () => {
	const s = one();

	s.type( 'nothing like it' );

	const item = s.list.firstChild;

	return {
		open: s.open(),
		listed: s.listed(),
		empty: item.classList.contains( 'wpcpm-dashboard__switcher-empty' ),
		disabled: item.getAttribute( 'aria-disabled' ),
		index: item.getAttribute( 'data-wpcpm-index' ),
		active: s.active(),
		said: s.said(),
	};
} );

scenario( 'Enter, Up and Down with no match pick nothing and send nothing', { prevented: true, field: 'nothing like it', value: 'rec10', open: true, active: null }, () => {
	const s = one();

	s.type( 'nothing like it' );
	s.key( 'ArrowDown' );
	s.key( 'ArrowUp' );

	return { prevented: s.key( 'Enter' ), field: s.field(), value: s.value(), open: s.open(), active: s.active() };
} );

scenario( 'a click on the no-match row picks nothing', { field: 'nothing like it', value: 'rec10', open: true }, () => {
	const s = one();

	s.type( 'nothing like it' );
	s.choose( 'No entries match that search.' );

	return { field: s.field(), value: s.value(), open: s.open() };
} );

scenario( 'and the sentence goes once something matches again', { listed: [ 'Student 2', 'Student 10' ], said: 'Names in the list: 2' }, () => {
	const s = one();

	s.type( 'nothing like it' );
	s.type( 'stu' );

	return { listed: s.listed(), said: s.said() };
} );

/* ---- the status line ---- */

// A status line is read out when its text is set, and setting it to the sentence it already holds
// can have it read out again. So it is set when what it says changes, and at no other step.
scenario( 'the status line is set only when what it says changes, so a screen reader says each sentence once', { open: 1, stu: 2, stud: 2, none: 3, still: 3, closed: 4, again: 5 }, () => {
	const s = one();
	const out = {};

	s.click();
	out.open = s.writes();
	s.type( 'stu' );
	out.stu = s.writes();
	s.type( 'stud' );
	out.stud = s.writes();
	s.type( 'nothing' );
	out.none = s.writes();
	s.type( 'nothing like it' );
	s.key( 'ArrowDown' );
	out.still = s.writes();
	s.key( 'Escape' );
	out.closed = s.writes();
	s.click();
	out.again = s.writes();

	return out;
} );

/* ---- Enter never submits ---- */

scenario( 'Enter in the field never submits the form: closed, open, on a match or on none', [ true, true, true, true ], () => {
	const states = [
		( s ) => s,
		( s ) => s.click(),
		( s ) => s.type( 'zoe' ),
		( s ) => s.type( 'nothing like it' ),
	];

	return states.map( ( state ) => {
		const s = one();

		state( s );

		return s.key( 'Enter' );
	} );
} );

scenario( 'Enter on a list opened and closed again picks nothing, and after a pick a second Enter picks nothing more', { escaped: { prevented: true, field: 'Student 10', value: 'rec10', open: false }, picked: { prevented: true, field: 'Zoe Academy', value: 'recZOE', open: false } }, () => {
	const s = one();

	s.click();
	s.key( 'ArrowDown' );
	s.key( 'Escape' );

	const escaped = { prevented: s.key( 'Enter' ), field: s.field(), value: s.value(), open: s.open() };
	const t = one();

	t.type( 'zoe' );
	t.key( 'Enter' );

	return { escaped, picked: { prevented: t.key( 'Enter' ), field: t.field(), value: t.value(), open: t.open() } };
} );

scenario( 'nor does an Enter that ends a composition pick', { field: 'zoe', value: 'rec10', open: true }, () => {
	const s = one();

	s.type( 'zoe' );
	s.key( 'Enter', { isComposing: true } );

	return { field: s.field(), value: s.value(), open: s.open() };
} );

// Safari sends the Enter that ends a composition after the composition has ended, so with
// isComposing false, but with the keyCode every key pressed during a composition carries.
scenario( 'nor the Enter Safari sends after a composition, which carries keyCode 229, and which still sends nothing', { prevented: true, field: 'zoe', value: 'rec10', open: true }, () => {
	const s = one();

	s.type( 'zoe' );

	const prevented = s.key( 'Enter', { isComposing: false, keyCode: 229 } );

	return { prevented, field: s.field(), value: s.value(), open: s.open() };
} );

/* ---- the field's width ---- */

// The longest name drawn, Álvaro University, is 17 letters: 136px of text, which the select holds
// at 138px with its border, and the field at 180px with its padding, its chevron's room and its
// border.
scenario( 'the field is as wide as its longest name needs in its own type, padding, chevron and border included, where the select is narrower', { width: '180px', select: true }, () => {
	const s = one();

	return { width: s.combo.style.width, select: s.select.hidden };
} );

scenario( 'and as wide as the select where the select is the wider, as in a smaller type', '138px', () => page( source, 'one', 400, { font: '8px' } ).switchers[ 0 ].combo.style.width );

scenario( 'in a larger type than the select\'s, the field is measured in its own: 17 letters at 9px and the edges', '197px', () => page( source, 'one', 400, { font: '18px' } ).switchers[ 0 ].combo.style.width );

scenario( 'with no canvas to measure with, the field keeps the select\'s width', '138px', () => page( source, 'one', 400, { canvas: false } ).switchers[ 0 ].combo.style.width );

scenario( 'even where every name is shorter than a text field\'s own width', '60px', () => page( source, 'short' ).switchers[ 0 ].combo.style.width );

scenario( 'the select is measured with nothing added to it or left behind, and its choice unchanged', { options: [ 8, 8 ], value: [ 'rec10', 'rec10' ] }, () => {
	const p = page( source, 'one' );
	const s = p.switchers[ 0 ];
	const options = [ s.select.options.length ];
	const value = [ s.value() ];

	p.resize( 100 );
	p.flush();
	options.push( s.select.options.length );
	value.push( s.value() );

	return { options, value };
} );

scenario( 'within the room the page gives it', '100px', () => page( source, 'one', 100 ).switchers[ 0 ].combo.style.width );

scenario( 'and typing, which narrows its list, never changes it', [ '180px', '180px' ], () => {
	const s = one();

	s.type( 'zoe' );

	const typed = s.combo.style.width;

	s.key( 'Escape' );

	return [ typed, s.combo.style.width ];
} );

scenario( 'a resize measures it again once the window settles', { before: '180px', waiting: 1, after: '100px' }, () => {
	const p = page( source, 'one' );

	p.resize( 100 );

	const before = p.switchers[ 0 ].combo.style.width;
	const waiting = p.waiting();

	p.flush();

	return { before, waiting, after: p.switchers[ 0 ].combo.style.width };
} );

scenario( 'a wider window gives the room back', '180px', () => {
	const p = page( source, 'one' );

	p.resize( 100 );
	p.flush();
	p.resize( 400 );
	p.flush();

	return p.switchers[ 0 ].combo.style.width;
} );

scenario( 'turning a phone is a resize too, and a burst of resizes is measured once', { turned: '100px', waiting: 1, after: '90px' }, () => {
	const p = page( source, 'one' );

	p.resize( 100, 'orientationchange' );
	p.flush();

	const turned = p.switchers[ 0 ].combo.style.width;

	p.resize( 120 );
	p.resize( 110 );
	p.resize( 90 );

	const waiting = p.waiting();

	p.flush();

	return { turned, waiting, after: p.switchers[ 0 ].combo.style.width };
} );

// The field may hold the focus while the window changes size, and a field taken out of the layout
// loses it: the measuring shows the list beside the field rather than in its place.
scenario( 'measuring never hides the field, so a field with the focus keeps it, and leaves the select hidden', { hidden: [ 'SELECT' ], select: true, combo: false }, () => {
	const p = page( source, 'one' );
	const s = p.switchers[ 0 ];

	s.click();
	p.document.hidden.length = 0;
	p.resize( 100 );
	p.flush();

	return { hidden: p.document.hidden.map( ( element ) => element.tagName ), select: s.select.hidden, combo: s.combo.hidden };
} );

/* ---- the four dashboards' switchers on one page ---- */

const FOUR = [
	[ 'wpcpm-institution-switcher', 'wpcpm_institution_view', 'Viewing as institution', 'No institutions match that search.' ],
	[ 'wpcpm-mentor-switcher', 'wpcpm_mentor', 'Viewing as mentor', 'No mentors match that search.' ],
	[ 'wpcpm-student-switcher', 'wpcpm_student_view', 'Viewing as student', 'No students match that search.' ],
	[ 'wpcpm-sponsor-switcher', 'wpcpm_sponsor_view', 'Viewing as sponsor', 'No sponsors match that search.' ],
];

scenario( 'the four switchers are each wired: each field named by its own label, holding its own name', FOUR.map( ( f ) => [ f[ 0 ] + '-input', f[ 2 ], 'Second Name' ] ), () => page( source, 'four' ).switchers.map( ( s ) => [ s.label.getAttribute( 'for' ), s.label.textContent, s.field() ] ) );

FOUR.forEach( ( f, at ) => {
	scenario( 'the ' + f[ 2 ].replace( 'Viewing as ', '' ) + ' switcher picks and sends its own, and leaves the other three as they were', { sent: [ [ f[ 1 ], 'v' + at + '-1' ] ], others: [ 'v-2', 'v-2', 'v-2' ], none: f[ 3 ] }, () => {
		const p = page( source, 'four' );
		const s = p.switchers[ at ];

		s.type( 'first' );
		s.key( 'Enter' );

		const sent = s.show();
		const others = p.switchers.filter( ( other ) => other !== s ).map( ( other ) => other.value().replace( /^v\d/, 'v' ) );

		s.type( 'nothing like it' );

		return { sent, others, none: s.said() };
	} );
} );

scenario( 'and no two of their rows share an ID', [ 8, 8 ], () => {
	const p = page( source, 'four' );

	p.switchers.forEach( ( s ) => {
		s.click();
		s.key( 'Escape' );
	} );

	const items = p.document.querySelectorAll( 'li' );

	return [ items.length, new Set( items.map( ( item ) => item.id ) ).size ];
} );

/* ---- mutation proofs: the same steps on a broken script must go wrong ---- */

/**
 * The script with one piece of its source replaced, and whether the piece was there to replace.
 *
 * @param {string} find    What to replace.
 * @param {string} replace What to put instead.
 * @return {Object} `script` and `applied`.
 */
function mutant( find, replace ) {
	return { script: source.split( find ).join( replace ), applied: -1 !== source.indexOf( find ) };
}

scenario( 'mutation: with the accents left on, "krakow" no longer finds Kraków', { applied: true, listed: [ 'No entries match that search.' ] }, () => {
	const broken = mutant( '.replace( /[\\u0300-\\u036f]/g, \'\' )', '' );
	const s = page( broken.script, 'one' ).switchers[ 0 ];

	s.type( 'krakow' );

	return { applied: broken.applied, listed: s.listed() };
} );

scenario( 'mutation: a press on a name that moves the focus closes the list before the click, and nothing is picked', { applied: true, kept: false, field: 'Student 10', value: 'rec10' }, () => {
	const broken = mutant( 'list.addEventListener( \'mousedown\'', 'list.addEventListener( \'mousedown-not-heard\'' );
	const s = page( broken.script, 'one' ).switchers[ 0 ];

	s.click();

	const kept = s.choose( 'École 42' );

	return { applied: broken.applied, kept, field: s.field(), value: s.value() };
} );

scenario( 'mutation: a status line set whatever it holds is set again for the same no match', { applied: true, more: true }, () => {
	const broken = mutant( 'if ( status.textContent !== text ) {', 'if ( true ) {' );
	const s = page( broken.script, 'one' ).switchers[ 0 ];

	s.type( 'nothing' );

	const once = s.writes();

	s.type( 'nothing like it' );

	return { applied: broken.applied, more: s.writes() > once };
} );

console.log( '\n' + ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
