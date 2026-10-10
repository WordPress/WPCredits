'use strict';
/*
 * assets/js/switcher.js on the Emails screen's Email filter, run in node.
 *
 *   php bin/test-emails-screen.php
 *
 * which hands over, as JSON on stdin, the filter form exactly as the screen draws it
 * (WPCPM_Emails::render_admin_page()), what to type and the ID of the email that should be picked:
 *
 *   node bin/js/emails-field.js <path to switcher.js> -
 *
 * bin/js/switcher-filter.js drives the script on the markup of the Viewing as switchers, five pages
 * it holds the expected answers for, and cannot take another page. This is its smaller sibling for
 * one question that suite cannot ask: does the field wire up on the markup of a form that is not a
 * Viewing as switcher? The script finds its parts through the ARIA that ties them together (the
 * field names its list, the list names the label, the label names the select), so a form that
 * draws the label with an ID the list does not name is not wired, and the select stays what the
 * form sends, shown, with the field hidden. What the script does with a list is that suite's job.
 *
 * The page is a small tree of elements that keep attributes, classes, children and listeners, read
 * from the server's markup and driven as a person drives the field: typing, then Enter. The script
 * is run in a fresh context with nothing but a window and a document.
 *
 * Prints one line of JSON, what happened, for the suite to compare; the last part, `unwired`, is the
 * same form with its label broken, a control that shows the observations can tell a wired field from
 * one the script left alone.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const ROW = 32;

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
		this.scrollTop = 0;
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attributes, name ) ? this.attributes[ name ] : null;
	}

	setAttribute( name, value ) {
		this.attributes[ name ] = String( value );
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
		const names = () => ( element.getAttribute( 'class' ) || '' ).split( /\s+/ ).filter( Boolean );

		return {
			add( name ) {
				if ( ! names().includes( name ) ) {
					element.setAttribute( 'class', names().concat( name ).join( ' ' ) );
				}
			},
			remove( name ) {
				element.setAttribute( 'class', names().filter( ( one ) => one !== name ).join( ' ' ) );
			},
			contains: ( name ) => names().includes( name ),
		};
	}

	get firstChild() {
		return this.childNodes[ 0 ] || null;
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
	}

	// The selectors the script uses: a class, or an attribute with a value.
	matches( selector ) {
		const attribute = /^\[([a-z-]+)="([^"]*)"\]$/.exec( selector );

		if ( attribute ) {
			return this.getAttribute( attribute[ 1 ] ) === attribute[ 2 ];
		}

		return '.' === selector[ 0 ] && this.classList.contains( selector.slice( 1 ) );
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

	// A row is a fixed height and the list is as tall as its rows, within its max-height.
	get offsetHeight() {
		return ROW;
	}

	get offsetTop() {
		return this.parentNode ? this.parentNode.childNodes.indexOf( this ) * ROW : 0;
	}

	get clientHeight() {
		const most = parseFloat( this.style.maxHeight );
		const rows = this.childNodes.length * ROW;

		return isNaN( most ) ? rows : Math.min( rows, most );
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

	get defaultSelected() {
		return this.hasAttribute( 'selected' );
	}
}

/** A single select: the option chosen in the markup, or the first as a browser shows it. */
class Select extends Element {
	get options() {
		return this.childNodes.filter( ( child ) => 'OPTION' === child.tagName );
	}

	get selectedIndex() {
		const chosen = this.options.findIndex( ( option ) => option.selected );

		return -1 === chosen && this.options.length ? 0 : chosen;
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

	getBoundingClientRect() {
		return { width: 300 };
	}
}

/** A canvas whose context measures a text at 8px a letter. */
class Canvas extends Element {
	getContext() {
		return {
			font: '',
			measureText: ( text ) => ( { width: String( text ).length * 8 } ),
		};
	}
}

/** The document: a body, the elements in it by ID, and what the script asks of it. */
class Document {
	constructor() {
		this.listeners = {};
		this.activeElement = null;
		this.body = new Element( this, 'body' );
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
 * @param {string} html   The markup.
 * @return {Object} The document and the field's parts.
 */
function open( script, html ) {
	const document = new Document();
	const window = {
		addEventListener() {},
		setTimeout: () => 0,
		clearTimeout() {},
		getComputedStyle: () => ( {} ),
	};

	read( document, html );
	vm.runInNewContext( script, { window, document } );
	( document.listeners.DOMContentLoaded || [] ).forEach( ( fn ) => fn() );

	const input = document.body.querySelector( '[role="combobox"]' );
	const list = document.body.querySelector( '[role="listbox"]' );
	const status = document.body.querySelector( '[role="status"]' );
	// The form holds other selects and labels: the Email filter's are the one named `email` and the one
	// the list names.
	const select = document.body.descendants().find( ( one ) => 'SELECT' === one.tagName && 'email' === one.getAttribute( 'name' ) );
	const label = list ? document.getElementById( list.getAttribute( 'aria-labelledby' ) ) : null;

	return {
		document,
		combo: document.body.querySelector( '.wpcpm-dashboard__switcher-combo' ),
		input,
		list,
		status,
		select,
		label,
		// The names the list holds, as a person reads them.
		listed: () => ( list.hidden ? [] : list.childNodes.map( ( row ) => row.textContent ) ),
		type( text ) {
			document.activeElement = input;
			input.value = text;
			input.fire( 'input' );
		},
		key( name ) {
			document.activeElement = input;

			return input.fire( 'keydown', { key: name } ).defaultPrevented;
		},
		// What the form sends for one field: every enabled, named element of that name, as a GET form sends it.
		sent: ( name ) => document.body.descendants().filter( ( one ) => name === one.getAttribute( 'name' ) && ! one.hasAttribute( 'disabled' ) ).map( ( one ) => one.value ),
	};
}

const script = fs.readFileSync( path.resolve( process.argv[ 2 ] ), 'utf8' );
const given = '-' === process.argv[ 3 ] ? JSON.parse( fs.readFileSync( 0, 'utf8' ) ) : {};
const out = { error: null };

try {
	const page = open( script, given.html );

	out.wired = {
		select_hidden: page.select.hidden,
		combo_shown: ! page.combo.hidden,
		label_names_field: page.label.getAttribute( 'for' ) === page.input.id,
		field: page.input.value,
		list_closed: page.list.hidden,
		options: page.select.options.length,
	};

	page.type( given.query );
	out.typed = { listed: page.listed(), said: page.status.textContent };
	out.picked = { prevented: page.key( 'Enter' ), field: page.input.value, select: page.select.value, sent: page.sent( 'email' ), closed: page.list.hidden };

	// The control: the same form with the label the list names no longer there to name.
	const broken = open( script, given.html.replace( /aria-labelledby="[^"]*"/, 'aria-labelledby="no-label-has-this-id"' ) );

	out.unwired = { select_hidden: broken.select.hidden, combo_shown: ! broken.combo.hidden };
} catch ( error ) {
	out.error = error.message;
}

console.log( JSON.stringify( out ) );
