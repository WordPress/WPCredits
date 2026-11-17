'use strict';
/*
 * What a pick does in the Institution Dashboard's Viewing as institution (Task 14): assets/js/switcher.js and
 * assets/js/parts.js, the two real files, run in node on the markup the dashboard draws.
 *
 *   php bin/test-institution-switcher.php
 *
 * which runs, with the three forms as the dashboard draws them on stdin (`marked`: an Administrator viewing a
 * school, the surface on; `choice`: the choice state, the surface on; `unmarked`: today's switcher):
 *
 *   node bin/js/switcher-pick.js <path to switcher.js> <path to parts.js> -
 *
 * switcher.js submits nothing: it announces a pick, one bubbling `wpcpm:picked` event on the form. The parts'
 * script submits a form marked `data-wpcpm-pick-submits` once on it, never for the school already shown, and is
 * the one that hides Show, after it listens. So a marked form whose parts' script did not load keeps Show and
 * submits nothing on a pick, and an unmarked form never submits, on a pick or on Enter.
 *
 * The page is a small tree of elements that keep attributes, classes, children and listeners, and fire events
 * that bubble up to the document, as a browser's do; a form counts what it was asked to submit, with the value of
 * its select at that moment. The last two scenarios are mutation proofs: switcher.js without its announcement, and
 * the parts' script without its test of the mark, run through the same steps, must each go wrong.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then
 * `<n> scenarios, <m> differ`, and exits 1 when any scenario differs.
 */
const fs = require( 'fs' );
const vm = require( 'vm' );

const SWITCHER = fs.existsSync( process.argv[ 2 ] || '' ) ? fs.readFileSync( process.argv[ 2 ], 'utf8' ) : '';
const PARTS = fs.existsSync( process.argv[ 3 ] || '' ) ? fs.readFileSync( process.argv[ 3 ], 'utf8' ) : '';
let markup = {};

if ( '-' === process.argv[ 4 ] ) {
	try {
		markup = JSON.parse( fs.readFileSync( 0, 'utf8' ) );
	} catch ( error ) {
		markup = {};
	}
}

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

/* ---- the page ---- */

// One simple selector: a tag, `.class`, `[attr]` or `[attr="value"]`, or a tag or class followed by attribute tests.
function matchesOne( node, selector ) {
	const parts = /^([a-z0-9]*)((?:\.[a-z0-9_-]+)*)((?:\[[a-z0-9_-]+(?:="[^"]*")?\])*)$/i.exec( selector.trim() );

	if ( ! parts ) {
		throw new Error( 'the stand-in does not read the selector ' + selector );
	}

	if ( parts[ 1 ] && node.tagName !== parts[ 1 ].toUpperCase() ) {
		return false;
	}

	const classes = node.className.split( /\s+/ );

	if ( parts[ 2 ].split( '.' ).slice( 1 ).some( ( one ) => ! classes.includes( one ) ) ) {
		return false;
	}

	return ( parts[ 3 ].match( /\[[^\]]+\]/g ) || [] ).every( ( test ) => {
		const pair = /^\[([a-z0-9_-]+)(?:="([^"]*)")?\]$/i.exec( test );

		return node.hasAttribute( pair[ 1 ] ) && ( undefined === pair[ 2 ] || node.getAttribute( pair[ 1 ] ) === pair[ 2 ] );
	} );
}

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

	appendChild( child ) {
		if ( child.parentNode ) {
			child.parentNode.removeChild( child );
		}

		this.childNodes.push( child );
		child.parentNode = this;

		return child;
	}

	removeChild( child ) {
		this.childNodes.splice( this.childNodes.indexOf( child ), 1 );
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

	matches( selector ) {
		return selector.split( ',' ).some( ( one ) => matchesOne( this, one ) );
	}

	descendants() {
		return this.childNodes.reduce( ( all, child ) => all.concat( child, child.descendants() ), [] );
	}

	querySelector( selector ) {
		return this.querySelectorAll( selector )[ 0 ] || null;
	}

	querySelectorAll( selector ) {
		return this.descendants().filter( ( one ) => one.matches( selector ) );
	}

	closest( selector ) {
		for ( let node = this; node && node.tagName; node = node.parentNode ) {
			if ( node.matches( selector ) ) {
				return node;
			}
		}

		return null;
	}

	addEventListener( type, fn ) {
		( this.listeners[ type ] = this.listeners[ type ] || [] ).push( fn );
	}

	// An event dispatched here: this element's listeners, then each ancestor's, then the document's, while it bubbles.
	dispatchEvent( event ) {
		event.target = this;

		for ( let node = this; node; node = node.parentNode ) {
			( node.listeners[ type( event ) ] || [] ).forEach( ( fn ) => fn( event ) );

			if ( ! event.bubbles ) {
				return true;
			}
		}

		( this.ownerDocument.listeners[ type( event ) ] || [] ).forEach( ( fn ) => fn( event ) );

		return ! event.defaultPrevented;
	}

	// What a person does: an event that bubbles.
	fire( kind, init ) {
		const event = Object.assign( { type: kind, bubbles: true, defaultPrevented: false, preventDefault() {
			this.defaultPrevented = true;
		} }, init || {} );

		this.dispatchEvent( event );

		return event;
	}

	get rendered() {
		for ( let node = this; node && node.tagName; node = node.parentNode ) {
			if ( node.hidden ) {
				return false;
			}
		}

		return true;
	}

	get offsetHeight() {
		return this.rendered ? 32 : 0;
	}

	get offsetTop() {
		return 0;
	}

	get clientHeight() {
		return this.rendered ? 320 : 0;
	}

	getBoundingClientRect() {
		return { width: this.rendered ? 300 : 0 };
	}
}

function type( event ) {
	return event.type;
}

class Input extends Element {
	constructor( document, tag ) {
		super( document, tag );
		this.value = '';
	}

	select() {}
}

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

class Select extends Element {
	get options() {
		return this.childNodes.filter( ( child ) => 'OPTION' === child.tagName );
	}

	get selectedIndex() {
		const at = this.options.findIndex( ( option ) => option.selected );

		return -1 === at && this.options.length ? 0 : at;
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

	get required() {
		return this.hasAttribute( 'required' );
	}
}

class Canvas extends Element {
	getContext() {
		return { font: '', measureText: ( text ) => ( { width: String( text ).length * 8 } ) };
	}
}

class Document {
	constructor() {
		this.listeners = {};
		this.readyState = 'loading';
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

	addEventListener( kind, fn ) {
		( this.listeners[ kind ] = this.listeners[ kind ] || [] ).push( fn );
	}
}

// The server's markup read into the body: tags, attributes and text, with the entities esc_html() writes read back.
function read( document, html ) {
	const decode = ( text ) => text.replace( /&quot;/g, '"' ).replace( /&#0?39;/g, '\'' ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&amp;/g, '&' );
	const stack = [ document.body ];
	const tokens = /<\/([a-z]+)\s*>|<([a-z]+)((?:\s+[a-z-]+(?:=(?:"[^"]*"|'[^']*'))?)*)\s*(\/?)>|([^<]+)/gi;
	let token;

	while ( ( token = tokens.exec( html ) ) ) {
		if ( token[ 1 ] ) {
			while ( stack.length > 1 && stack.pop().tagName !== token[ 1 ].toUpperCase() ) {
				// Up to the element the tag closes.
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
 * One of the server's forms with the scripts run on it, as a page loads them: switcher.js, then the parts' script
 * unless it is left out, then the page parsed.
 *
 * @param {string} name     Which form.
 * @param {Object} more     `parts: false` for a page whose parts' script did not load; `switcher` and `script`,
 *                          a source in place of the real one.
 * @return {Object} What a person does with it, and what the form was asked to submit.
 */
function page( name, more ) {
	const options = more || {};
	const document = new Document();
	const listeners = {};
	const sent = [];
	const timers = new Map(); // id to { fn, at }: what the page asked for, and when it falls due.
	const delays = []; // Every delay the scripts asked for, in ms, in order.
	let made = 0;
	let now = 0;

	read( document, undefined !== options.html ? options.html : ( markup[ name ] || '' ) );

	const form = document.body.querySelector( 'form' );

	if ( ! form ) {
		throw new Error( 'no form in the markup of ' + name );
	}

	const select = form.querySelector( 'select' );
	const value = () => ( select ? select.value : '' );

	// What the form was asked to send, and by which call: `requestSubmit()`, or `submit()` where it is missing.
	if ( ! options.noRequestSubmit ) {
		form.requestSubmit = () => sent.push( value() );
	}
	form.submit = () => sent.push( ( options.noRequestSubmit ? 'submit() ' : '' ) + value() );

	class CustomEvent {
		constructor( kind, init ) {
			this.type = kind;
			this.bubbles = !! ( init && init.bubbles );
			this.detail = init ? init.detail : null;
			this.defaultPrevented = false;
		}

		preventDefault() {
			this.defaultPrevented = true;
		}
	}

	const window = {
		CustomEvent: options.noCustomEvent ? undefined : CustomEvent,
		console: { error: ( error ) => thrown.push( String( error && error.message || error ) ) },
		addEventListener( kind, fn ) {
			( listeners[ kind ] = listeners[ kind ] || [] ).push( fn );
		},
		// The window's timers, run by hand: `advance()` lets time pass, `flush()` runs every one.
		setTimeout( fn, ms ) {
			const wait = Number( ms ) || 0;

			delays.push( wait );
			timers.set( ++made, { fn, at: now + wait } );

			return made;
		},
		clearTimeout( id ) {
			timers.delete( id );
		},
		getComputedStyle: () => ( { fontSize: '16px', paddingLeft: '0px', paddingRight: '0px', borderLeftWidth: '0px', borderRightWidth: '0px', paddingTop: '0px', paddingBottom: '0px', borderTopWidth: '0px', borderBottomWidth: '0px' } ),
	};

	const thrown = [];
	const run = ( source ) => vm.runInNewContext( source, { window, document } );
	const switcher = () => {
		if ( false !== options.switcher ) {
			run( options.switcher || SWITCHER );
		}
	};
	const parts = () => {
		if ( false !== options.parts ) {
			run( options.script || PARTS );
		}
	};

	// The order the page loads the two in: switcher.js first, or the parts' script first.
	if ( options.partsFirst ) {
		parts();
		switcher();
	} else {
		switcher();
		parts();
	}

	( document.listeners.DOMContentLoaded || [] ).forEach( ( fn ) => fn() );
	document.readyState = 'complete';
	// The window's load, which comes after every DOMContentLoaded listener has run.
	( listeners.load || [] ).forEach( ( fn ) => fn( { type: 'load' } ) );

	const input = form.querySelector( '[role="combobox"]' );
	const list = form.querySelector( '[role="listbox"]' );
	const announced = [];

	document.addEventListener( 'wpcpm:picked', ( event ) => announced.push( event.detail ) );

	const rows = () => list.childNodes.filter( ( row ) => row.rendered );

	return {
		form,
		select,
		input,
		sent,
		announced,
		thrown,
		value,
		delays,
		// Every timer, run now: as much time passing as any of them waits.
		flush() {
			const due = Array.from( timers.values() );

			timers.clear();
			due.forEach( ( one ) => one.fn() );
		},
		// Time passing, in ms: the timers that fall due within it run, in the order they fall due.
		advance( ms ) {
			now += ms;

			Array.from( timers.entries() ).filter( ( entry ) => entry[ 1 ].at <= now ).sort( ( a, b ) => a[ 1 ].at - b[ 1 ].at ).forEach( ( entry ) => {
				timers.delete( entry[ 0 ] );
				entry[ 1 ].fn();
			} );
		},

		// Show pressed: the form's own submit event, which nothing here may stop.
		press: () => ! form.fire( 'submit' ).defaultPrevented,
		show: () => form.querySelector( '[data-wpcpm-noscript]' ),
		listed: () => ( list.hidden ? [] : rows().map( ( row ) => row.textContent ) ),
		click() {
			document.activeElement = input;
			input.fire( 'mousedown' );
			input.fire( 'click' );
		},
		type( text ) {
			document.activeElement = input;
			input.value = text;
			input.fire( 'input' );
		},
		key( name ) {
			document.activeElement = input;

			return input.fire( 'keydown', { key: name } ).defaultPrevented;
		},
		leave() {
			document.activeElement = null;
			input.fire( 'blur' );
		},
		// A click on a shown row of the list, as a browser orders it: the press, kept from moving the focus, then the click.
		choose( text ) {
			const row = rows().find( ( one ) => one.textContent === text );

			if ( ! row ) {
				throw new Error( 'no row reads ' + text );
			}

			row.fire( 'mousedown' );
			row.fire( 'click' );
		},
		pageshow: ( persisted ) => ( listeners.pageshow || [] ).forEach( ( fn ) => fn( { type: 'pageshow', persisted } ) ),
	};
}

const SCHOOL = 'Exampleinstituteforappliedwebengineerings';
const SECOND = 'Exampleinstituteforappliedwebengineerings Second Campus';
const REC = 'recEXA00000000001';
const REC2 = 'recEXA00000000002';

/* ---- the markup ---- */

scenario( 'the dashboard handed over its three forms', [ 'choice', 'marked', 'unmarked' ], () => Object.keys( markup ).sort() );

/* ---- a marked form, both scripts loaded ---- */

scenario( 'once the parts\' script listens, Show is hidden; the field shows the school being viewed', { show: true, field: SCHOOL }, () => {
	const p = page( 'marked' );

	return { show: p.show().hidden, field: p.input.value };
} );

scenario( 'a pick by a click submits the form once, with the select on the school picked', [ REC2 ], () => {
	const p = page( 'marked' );

	p.click();
	p.choose( SECOND );

	return p.sent;
} );

scenario( 'a pick by Enter submits once, and a click on a name after it, or a second Enter, submits nothing more', { sent: [ REC2 ], again: [ REC2 ] }, () => {
	const p = page( 'marked' );

	p.type( 'second' );
	p.key( 'Enter' );

	const sent = p.sent.slice();

	p.click();
	p.choose( SECOND );
	p.type( 'second' );
	p.key( 'Enter' );

	return { sent, again: p.sent };
} );

scenario( 'typing submits nothing, keystroke by keystroke, nor moving through the list', [], () => {
	const p = page( 'marked' );

	[ 'S', 'Se', 'Sec', 'Seco' ].forEach( ( text ) => p.type( text ) );
	p.key( 'ArrowDown' );
	p.key( 'ArrowUp' );
	p.key( 'Escape' );
	p.leave();

	return p.sent;
} );

scenario( 'a pick of the school already shown submits nothing', [], () => {
	const p = page( 'marked' );

	p.click();
	p.choose( SCHOOL );
	p.type( 'applied' );
	p.key( 'Enter' );

	return p.sent;
} );

scenario( 'a page brought back from the back-forward cache submits again on a new pick', [ REC2, REC2 ], () => {
	const p = page( 'marked' );

	p.click();
	p.choose( SECOND );
	p.pageshow( true );
	p.click();
	p.choose( SECOND );

	return p.sent;
} );

/* ---- the choice state ---- */

scenario( 'the choice: the field is empty, its placeholder the server\'s, and the empty option is never listed', { field: '', placeholder: 'Find an institution', listed: [ SCHOOL, SECOND ], value: '' }, () => {
	const p = page( 'choice' );
	const field = p.input.value;

	p.click();

	return { field, placeholder: p.input.getAttribute( 'placeholder' ), listed: p.listed(), value: p.select.value };
} );

scenario( 'leaving it, or Escape, puts the empty field back and the select on no school', { field: '', value: '', sent: [] }, () => {
	const p = page( 'choice' );

	p.type( 'Exa' );
	p.key( 'Escape' );
	p.leave();

	return { field: p.input.value, value: p.select.value, sent: p.sent };
} );

scenario( 'a pick on the choice opens that school: the first school too', [ REC ], () => {
	const p = page( 'choice' );

	p.click();
	p.choose( SCHOOL );

	return p.sent;
} );

/* ---- an unmarked form, today's switcher ---- */

scenario( 'an unmarked form: a pick is announced and submits nothing', { sent: [], announced: [ { value: REC2, own: REC } ], show: false }, () => {
	const p = page( 'unmarked' );

	p.click();
	p.choose( SECOND );

	return { sent: p.sent, announced: p.announced, show: !! p.show() };
} );

scenario( 'an unmarked form: Enter in the field submits nothing, closed, open on a name and after a pick, and is kept from its default', { sent: [], kept: [ true, true, true ] }, () => {
	const p = page( 'unmarked' );
	const kept = [ p.key( 'Enter' ) ];

	p.type( 'second' );
	kept.push( p.key( 'Enter' ) );
	kept.push( p.key( 'Enter' ) );

	return { sent: p.sent, kept };
} );

/* ---- the parts' script did not load ---- */

scenario( 'a marked form without the parts\' script: Show stays shown, and a pick submits nothing', { show: false, sent: [], announced: 1 }, () => {
	const p = page( 'marked', { parts: false } );

	p.click();
	p.choose( SECOND );

	return { show: p.show().hidden, sent: p.sent, announced: p.announced.length };
} );

/* ---- Show is hidden only once the field is really there (fix round 1, item 1) ---- */

scenario( 'the parts\' script without switcher.js: Show stays shown and sends the form, and nothing is thrown', { show: false, sent: true, thrown: [] }, () => {
	const p = page( 'marked', { switcher: false } );

	return { show: p.show().hidden, sent: p.press(), thrown: p.thrown };
} );

scenario( 'the choice without switcher.js: Show stays shown, the only way forward', false, () => page( 'choice', { switcher: false } ).show().hidden );

scenario( 'the parts\' script loaded before switcher.js: Show is hidden once the field is there, at the window\'s load, and a pick sends the form', { show: true, sent: [ REC2 ] }, () => {
	const p = page( 'marked', { partsFirst: true } );

	p.click();
	p.choose( SECOND );

	return { show: p.show().hidden, sent: p.sent };
} );

/* ---- a pick whose page never came (fix round 1, item 2) ---- */

/*
 * A pick whose navigation never happens, on the choice, whose own value is '': the first pick is one school, the pick
 * within the window the OTHER school, which neither the page's own nor the first pick, so only the window can stop it.
 * The window is the delay the script asks for: above 0 and at most a few thousand milliseconds.
 */
function pickAgain( script ) {
	const p = page( 'choice', script ? { script } : {} );

	p.click();
	p.choose( SECOND );
	p.advance( 100 );
	p.click();
	p.choose( SCHOOL );

	const within = p.sent.slice();
	const asked = p.delays.slice();

	p.advance( 10000 );
	p.click();
	p.choose( SCHOOL );
	p.click();
	p.choose( SECOND );

	return { within, window: asked.length > 0 && asked.every( ( ms ) => ms > 0 && ms <= 5000 ), after: p.sent };
}

scenario( 'a pick whose navigation never happens: another school picked within the window sends nothing; the window is above 0 and at most 5000ms; after it a pick sends the form once, and the next waits again', { within: [ REC2 ], window: true, after: [ REC2, REC ] }, () => pickAgain() );

/* ---- the edges (fix round 1, item 5) ---- */

scenario( 'a browser without requestSubmit(): a pick sends the form by submit(), once', [ 'submit() ' + REC2 ], () => {
	const p = page( 'marked', { noRequestSubmit: true } );

	p.click();
	p.choose( SECOND );
	p.type( 'second' );
	p.key( 'Enter' );

	return p.sent;
} );

scenario( 'a browser without CustomEvent: a pick sets the select, nothing is thrown, nothing is sent, and Show stays', { value: REC2, field: SECOND, thrown: [], sent: [], show: false }, () => {
	const p = page( 'marked', { noCustomEvent: true } );

	p.click();
	p.choose( SECOND );

	return { value: p.value(), field: p.input.value, thrown: p.thrown, sent: p.sent, show: p.show().hidden };
} );

scenario( 'a marked form with no select: nothing is thrown, nothing is sent, and Show stays', { thrown: [], sent: [], show: false }, () => {
	const html = ( markup.marked || '' ).replace( /<select[\s\S]*?<\/select>/, '' );

	if ( html === markup.marked ) {
		throw new Error( 'the marked form has no select to take out' );
	}

	const p = page( 'marked', { html } );

	return { thrown: p.thrown, sent: p.sent, show: p.show().hidden };
} );

/* ---- mutation proofs ---- */

function mutant( source, find, replace ) {
	if ( -1 === source.indexOf( find ) ) {
		throw new Error( 'the mutation found nothing to change: ' + find );
	}

	return source.split( find ).join( replace );
}

scenario( 'switcher.js without its announcement is told apart: a pick in a marked form submits nothing', [], () => {
	const p = page( 'marked', { switcher: mutant( SWITCHER, 'form.dispatchEvent(', 'void (' ) } );

	p.click();
	p.choose( SECOND );

	return p.sent;
} );

scenario( 'the parts\' script without its test of the mark is told apart: the unmarked form submits', [ REC2 ], () => {
	const p = page( 'unmarked', { script: mutant( PARTS, "'form[data-wpcpm-pick-submits]'", "'form'" ) } );

	p.click();
	p.choose( SECOND );

	return p.sent;
} );

scenario( 'the parts\' script with the pick-again delay set to 0 is told apart: the window scenario differs', true, () => {
	const got = pickAgain( mutant( PARTS, 'var PICK_AGAIN = 4000;', 'var PICK_AGAIN = 0;' ) );

	return JSON.stringify( got ) !== JSON.stringify( { within: [ REC2 ], window: true, after: [ REC2, REC ] } );
} );

console.log( ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
