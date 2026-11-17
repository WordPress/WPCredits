'use strict';
/*
 * assets/js/parts.js, run in node: what the parts' script does with the file box (parts design 3.2, job 3).
 *
 *   php bin/test-parts-file.php
 *
 * which runs this file the way bin/test-parts-js.php runs bin/js/parts.js:
 *
 *   node bin/js/parts-file.js <path to parts.js> <path to the file() fixture>
 *
 * The script is run on a stand-in for the page: a small tree of elements that keep their attributes, their
 * children, their text, their listeners, a file control's chosen files and where the focus is. The pages are
 * `WPCPM_UI::file()`'s own markup, written by bin/test-parts-file.php to a fixture file passed as the second
 * argument, so the page cannot drift from what the site prints (the browser's own control, then the button and
 * the name, both printed `hidden`):
 *
 *   - one: a required box with a hint, in a form;
 *   - two: that box and an optional one;
 *   - translated: the box with "No file chosen" translated;
 *   - none: a text field and an old-style file control, no file box;
 *   - no-input: a box whose control is missing, then a whole one.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a summary line,
 * and exits 1 when any scenario differs. Two scenarios are mutation proofs: the script without the line that
 * listens for the choice, and without the line that clips the control, must each make a scenario go wrong.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'parts.js' ) );
const FIXTURE = process.argv[ 3 ] && fs.existsSync( process.argv[ 3 ] ) ? JSON.parse( fs.readFileSync( process.argv[ 3 ], 'utf8' ) ) : {};

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

/* ---- The page ---- */

// Whether an element matches one simple selector: a tag, `.class`, `[attr]` or `[attr="value"]`, or a tag or
// class followed by attribute tests (as bin/js/parts.js reads them).
function matchesOne( node, selector ) {
	const re = /^([a-z0-9]*)((?:\.[a-z0-9_-]+)*)((?:\[[a-z0-9_-]+(?:="[^"]*")?\])*)$/i;
	const parts = re.exec( selector.trim() );

	if ( ! parts ) {
		throw new Error( 'the stand-in does not read the selector ' + selector );
	}

	if ( parts[ 1 ] && node.nodeName !== parts[ 1 ].toUpperCase() ) {
		return false;
	}

	const classes = ( node.getAttribute( 'class' ) || '' ).split( ' ' );

	if ( parts[ 2 ].split( '.' ).slice( 1 ).some( ( name ) => classes.indexOf( name ) === -1 ) ) {
		return false;
	}

	return ( parts[ 3 ].match( /\[[^\]]+\]/g ) || [] ).every( ( test ) => {
		const pair = /^\[([a-z0-9_-]+)(?:="([^"]*)")?\]$/i.exec( test );

		return node.hasAttribute( pair[ 1 ] ) && ( undefined === pair[ 2 ] || node.getAttribute( pair[ 1 ] ) === pair[ 2 ] );
	} );
}

class Element {
	constructor( page, tag, attrs ) {
		this.page = page;
		this.nodeName = tag.toUpperCase();
		this.tagName = this.nodeName;
		this.attrs = {};
		this.text = '';
		this.children = [];
		this.parentElement = null;
		this.listeners = {};
		this.files = 'INPUT' === this.nodeName && 'file' === String( attrs && attrs.type ) ? [] : undefined;
		Object.keys( attrs || {} ).forEach( ( name ) => {
			this.attrs[ name ] = true === attrs[ name ] ? '' : String( attrs[ name ] );
		} );

		const self = this;

		this.classList = {
			contains: ( name ) => ( self.getAttribute( 'class' ) || '' ).split( ' ' ).indexOf( name ) !== -1,
			add: ( name ) => {
				if ( ! self.classList.contains( name ) ) {
					self.setAttribute( 'class', ( ( self.getAttribute( 'class' ) || '' ) + ' ' + name ).trim() );
				}
			},
			remove: ( name ) => {
				self.setAttribute( 'class', ( self.getAttribute( 'class' ) || '' ).split( ' ' ).filter( ( one ) => one !== name ).join( ' ' ) );
			},
		};
	}

	get parentNode() {
		return this.parentElement;
	}

	get id() {
		return this.getAttribute( 'id' ) || '';
	}

	get hidden() {
		return this.hasAttribute( 'hidden' );
	}

	set hidden( on ) {
		if ( on ) {
			this.attrs.hidden = '';
		} else {
			delete this.attrs.hidden;
		}
	}

	get disabled() {
		return this.hasAttribute( 'disabled' );
	}

	get type() {
		return ( this.getAttribute( 'type' ) || ( 'INPUT' === this.nodeName ? 'text' : '' ) ).toLowerCase();
	}

	get textContent() {
		return this.text + this.children.map( ( child ) => child.textContent ).join( '' );
	}

	set textContent( value ) {
		this.children = [];
		this.text = String( value );
	}

	append( child ) {
		child.parentElement = this;
		this.children.push( child );

		return child;
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
	}

	hasAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, name );
	}

	setAttribute( name, value ) {
		this.attrs[ name ] = String( value );
	}

	removeAttribute( name ) {
		delete this.attrs[ name ];
	}

	matches( selector ) {
		return selector.split( ',' ).some( ( one ) => matchesOne( this, one ) );
	}

	closest( selector ) {
		for ( let node = this; node; node = node.parentElement ) {
			if ( node.matches( selector ) ) {
				return node;
			}
		}

		return null;
	}

	all() {
		const out = [];
		const walk = ( node ) => {
			node.children.forEach( ( child ) => {
				out.push( child );
				walk( child );
			} );
		};

		walk( this );

		return out;
	}

	querySelectorAll( selector ) {
		return this.all().filter( ( node ) => node.matches( selector ) );
	}

	querySelector( selector ) {
		return this.querySelectorAll( selector )[ 0 ] || null;
	}

	addEventListener( type, listener ) {
		( this.listeners[ type ] = this.listeners[ type ] || [] ).push( listener );
		this.page.added++;
	}

	removeEventListener( type, listener ) {
		const list = this.listeners[ type ] || [];

		if ( -1 !== list.indexOf( listener ) ) {
			list.splice( list.indexOf( listener ), 1 );
			this.page.added--;
		}
	}

	// A file control's click() opens the browser's chooser: the stand-in counts each opening, by the control's id.
	click() {
		if ( 'INPUT' === this.nodeName && 'file' === this.type ) {
			this.page.opened.push( this.id );
		}
	}

	// A browser gives no focus to an element that is hidden, or inside one that is.
	focus() {
		for ( let node = this; node; node = node.parentElement ) {
			if ( node.hidden ) {
				return;
			}
		}

		this.page.document.activeElement = this;
	}
}

// Reads markup into the stand-in: tags, attributes and text. Void elements close themselves; an attribute with no
// value is a bare attribute; the entities esc_html() prints are decoded.
function parseInto( html, into, make ) {
	const VOID = [ 'input', 'br', 'img', 'hr', 'meta', 'link', 'source', 'wbr' ];
	const decode = ( text ) => text.replace( /&quot;/g, '"' ).replace( /&#0?39;/g, '\'' ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&amp;/g, '&' );
	const stack = [ into ];
	const token = /<(\/?)([a-zA-Z][a-zA-Z0-9]*)((?:\s+[^\s=>\/]+(?:\s*=\s*"[^"]*")?)*)\s*(\/?)>|([^<]+)/g;
	let match;

	while ( null !== ( match = token.exec( html ) ) ) {
		const top = stack[ stack.length - 1 ];

		if ( undefined !== match[ 5 ] ) {
			top.text += decode( match[ 5 ] );
			continue;
		}

		const tag = match[ 2 ].toLowerCase();

		if ( match[ 1 ] ) {
			for ( let i = stack.length - 1; i > 0; i-- ) {
				if ( stack[ i ].nodeName === tag.toUpperCase() ) {
					stack.length = i;
					break;
				}
			}
			continue;
		}

		const attrs = {};
		const pair = /([^\s=>\/]+)(?:\s*=\s*"([^"]*)")?/g;
		let a;

		while ( null !== ( a = pair.exec( match[ 3 ] ) ) ) {
			attrs[ a[ 1 ] ] = undefined === a[ 2 ] ? true : decode( a[ 2 ] );
		}

		const node = top.append( make( tag, attrs ) );

		if ( ! match[ 4 ] && -1 === VOID.indexOf( tag ) ) {
			stack.push( node );
		}
	}
}

/**
 * A page for the script to run on, and the means to load it, press a control on it and choose a file in it.
 *
 * @param {string} source The script.
 * @param {string} name   The fixture page.
 * @param {object} [opt]  parsing: the document is still loading when the script runs.
 * @return {object}
 */
function build( source, name, opt ) {
	const o = opt || {};
	const page = { listeners: { window: {}, document: {} }, added: 0, opened: [], errors: [] };
	const make = ( tag, attrs ) => new Element( page, tag, attrs );
	const document = make( 'html' );
	const body = document.append( make( 'body' ) );

	if ( undefined === FIXTURE[ name ] ) {
		throw new Error( 'no fixture page ' + name + ' (bin/test-parts-file.php writes it)' );
	}

	parseInto( FIXTURE[ name ], body, make );
	page.document = document;
	document.body = body;
	document.documentElement = document;
	document.activeElement = body;
	document.readyState = o.parsing ? 'loading' : 'complete';
	document.getElementById = ( id ) => body.all().filter( ( node ) => node.id === id )[ 0 ] || null;
	document.addEventListener = ( type, listener ) => {
		( page.listeners.document[ type ] = page.listeners.document[ type ] || [] ).push( listener );
	};

	const window = {
		document,
		console: { error: ( error ) => page.errors.push( String( error && error.message ? error.message : error ) ) },
		addEventListener: ( type, listener ) => {
			( page.listeners.window[ type ] = page.listeners.window[ type ] || [] ).push( listener );
		},
	};

	vm.runInNewContext( source, { document, window } );

	// An event on an element: its own listeners, then each ancestor's, then the document's, as one that bubbles.
	const fire = ( target, type, extra ) => {
		const event = Object.assign( { type, target, defaultPrevented: false, preventDefault() {
			this.defaultPrevented = true;
		} }, extra || {} );

		for ( let node = target; node; node = node.parentElement ) {
			( node.listeners[ type ] || [] ).slice().forEach( ( listener ) => listener.call( node, event ) );
		}
		( page.listeners.document[ type ] || [] ).forEach( ( listener ) => listener( event ) );

		return event;
	};
	const one = ( selector, at ) => body.querySelectorAll( selector )[ at || 0 ];

	return {
		page,
		body,
		window,
		one,
		parsed: () => {
			document.readyState = 'interactive';
			( page.listeners.document.DOMContentLoaded || [] ).forEach( ( listener ) => listener( {} ) );
		},
		// A press: a hidden control cannot be pressed; the control takes the focus and a click follows.
		press: ( node ) => {
			for ( let at = node; at; at = at.parentElement ) {
				if ( at.hidden ) {
					throw new Error( 'the control is hidden and cannot be pressed' );
				}
			}

			document.activeElement = node;
			fire( node, 'click', { button: 0 } );
		},
		// The chooser closed: with these files (a list of names; [] when the person canceled and nothing is chosen),
		// and the event the browser sends (change when the choice changed, cancel when it did not).
		choose: ( input, names, type ) => {
			input.files = names.map( ( n ) => ( { name: n } ) );
			fire( input, type || 'change' );
		},
		focus: () => ( document.activeElement === body ? 'page' : document.activeElement.id || document.activeElement.getAttribute( 'class' ) ),
		elementListeners: () => body.all().reduce( ( n, node ) => n + Object.keys( node.listeners ).reduce( ( m, type ) => m + node.listeners[ type ].length, 0 ), 0 ),
		documentListeners: () => Object.keys( page.listeners.document ).sort().map( ( type ) => type + ' ' + page.listeners.document[ type ].length ),
		snapshot: () => body.all().map( ( node ) => node.nodeName + JSON.stringify( node.attrs ) + node.text ).join( '|' ),
	};
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';

// A page as it loads: still parsing when the script runs, then parsed.
function loaded( script, name ) {
	const world = build( script, name, { parsing: true } );

	world.parsed();

	return world;
}

// The script with one line taken out; a line that is not found fails the proof instead of passing it.
function without( script, line ) {
	if ( -1 === script.indexOf( line ) ) {
		throw new Error( 'the line to take out was not found: ' + line );
	}

	return script.replace( line, '' );
}

// What a box shows: the control's classes and whether it carries hidden, the button's and the name's hidden, the
// name's words, the button's tabindex and the control's required.
function boxState( world, at ) {
	const input = world.one( '.wpcpm-ui-file__input', at );
	const button = world.one( '.wpcpm-ui-file__choose', at );
	const name = world.one( '.wpcpm-ui-file__name', at );

	return {
		input: input.getAttribute( 'class' ),
		inputHidden: input.hidden,
		required: input.hasAttribute( 'required' ),
		button: button.hidden ? 'hidden' : 'shown',
		tabindex: button.getAttribute( 'tabindex' ),
		name: name.hidden ? 'hidden' : 'shown',
		words: name.textContent,
	};
}

const LONG = 'Exampleagreementsignedbytheinstitutionrepresentative2026.pdf';

/* With no script */
scenario(
	'with no script: the browser\'s own control shows (no hidden, no clipping class), and the button and the name are hidden',
	{ input: 'wpcpm-ui-file__input', inputHidden: false, required: true, button: 'hidden', tabindex: null, name: 'hidden', words: 'No file chosen' },
	() => boxState( build( '', 'one' ) )
);

/* After load */
scenario(
	'after load: the button and the name are shown, the control carries the clipping class and is not hidden, keeps required, and is the one tab stop (the button tabindex -1); the name reads No file chosen; the focus is not moved',
	{ state: { input: 'wpcpm-ui-file__input wpcpm-ui-file__input--hidden', inputHidden: false, required: true, button: 'shown', tabindex: '-1', name: 'shown', words: 'No file chosen' }, focus: 'page' },
	() => {
		const world = loaded( source, 'one' );

		return { state: boxState( world ), focus: world.focus() };
	}
);

scenario(
	'and a page already parsed when the script runs is drawn at once',
	'shown',
	() => boxState( build( source, 'one' ) ).button
);

/* The button */
scenario(
	'pressing Choose a file calls the control\'s click() once (the browser\'s chooser) and leaves the focus on the control, where the ring is drawn on the button',
	{ opened: [ 'wpcpm-agreement-file' ], focus: 'wpcpm-agreement-file' },
	() => {
		const world = loaded( source, 'one' );

		world.press( world.one( '.wpcpm-ui-file__choose' ) );

		return { opened: world.page.opened, focus: world.focus() };
	}
);

/* The name follows the choice */
scenario(
	'a file chosen: the name reads the file\'s name',
	'Signed agreement 2026.pdf',
	() => {
		const world = loaded( source, 'one' );

		world.choose( world.one( '.wpcpm-ui-file__input' ), [ 'Signed agreement 2026.pdf' ] );

		return world.one( '.wpcpm-ui-file__name' ).textContent;
	}
);

scenario(
	'a 60-character name with no space is shown whole, as given',
	{ length: 60, words: LONG },
	() => {
		const world = loaded( source, 'one' );

		world.choose( world.one( '.wpcpm-ui-file__input' ), [ LONG ] );

		return { length: LONG.length, words: world.one( '.wpcpm-ui-file__name' ).textContent };
	}
);

scenario(
	'the box is for one file: the name is the first file\'s, never a list',
	'first.pdf',
	() => {
		const world = loaded( source, 'one' );

		world.choose( world.one( '.wpcpm-ui-file__input' ), [ 'first.pdf', 'second.pdf' ] );

		return world.one( '.wpcpm-ui-file__name' ).textContent;
	}
);

/* A cleared choice */
scenario(
	'the chooser opened and canceled: with the control empty the name returns to No file chosen, whether the browser sends change (it cleared the choice) or cancel; a cancel that keeps the file keeps its name',
	{ clearedByChange: 'No file chosen', canceledEmpty: 'No file chosen', canceledKept: 'kept.pdf' },
	() => {
		const world = loaded( source, 'one' );
		const input = world.one( '.wpcpm-ui-file__input' );
		const name = world.one( '.wpcpm-ui-file__name' );

		world.choose( input, [ 'a.pdf' ] );
		world.choose( input, [], 'change' );
		const clearedByChange = name.textContent;

		world.choose( input, [ 'b.pdf' ] );
		world.choose( input, [], 'cancel' );
		const canceledEmpty = name.textContent;

		world.choose( input, [ 'kept.pdf' ] );
		world.choose( input, [ 'kept.pdf' ], 'cancel' );

		return { clearedByChange, canceledEmpty, canceledKept: name.textContent };
	}
);

scenario(
	'the empty words are the markup\'s own: a translated page returns to its translated words',
	{ before: 'Aucun fichier choisi', after: 'Aucun fichier choisi' },
	() => {
		const world = loaded( source, 'translated' );
		const input = world.one( '.wpcpm-ui-file__input' );
		const before = world.one( '.wpcpm-ui-file__name' ).textContent;

		world.choose( input, [ 'a.pdf' ] );
		world.choose( input, [] );

		return { before, after: world.one( '.wpcpm-ui-file__name' ).textContent };
	}
);

/* Two boxes, and a broken one */
scenario(
	'two boxes keep apart: a choice in one names nothing in the other, each button opens its own control',
	{ names: [ 'one.pdf', 'No file chosen' ], opened: [ 'wpcpm-import-file' ] },
	() => {
		const world = loaded( source, 'two' );

		world.choose( world.one( '.wpcpm-ui-file__input', 0 ), [ 'one.pdf' ] );
		world.press( world.one( '.wpcpm-ui-file__choose', 1 ) );

		return { names: [ world.one( '.wpcpm-ui-file__name', 0 ).textContent, world.one( '.wpcpm-ui-file__name', 1 ).textContent ], opened: world.page.opened };
	}
);

scenario(
	'a box whose control is missing is left as printed (its button stays hidden, it opens nothing) and the whole box after it still works',
	{ broken: { button: 'hidden', name: 'hidden' }, whole: { button: 'shown', words: 'x.pdf' }, errors: [] },
	() => {
		const world = loaded( source, 'no-input' );

		world.choose( world.one( '.wpcpm-ui-file__input', 0 ), [ 'x.pdf' ] );

		const broken = { button: world.one( '.wpcpm-ui-file__choose', 0 ).hidden ? 'hidden' : 'shown', name: world.one( '.wpcpm-ui-file__name', 0 ).hidden ? 'hidden' : 'shown' };
		const whole = { button: world.one( '.wpcpm-ui-file__choose', 1 ).hidden ? 'hidden' : 'shown', words: world.one( '.wpcpm-ui-file__name', 1 ).textContent };

		return { broken, whole, errors: world.page.errors };
	}
);

/* Run again, or on a page with no box */
scenario(
	'the script run again (window.wpcpmParts()) adds nothing: one press still opens the chooser once, one listener set per box',
	{ listeners: 3, opened: [ 'wpcpm-agreement-file' ] },
	() => {
		const world = loaded( source, 'one' );

		world.window.wpcpmParts();
		world.window.wpcpmParts();
		world.press( world.one( '.wpcpm-ui-file__choose' ) );

		return { listeners: world.elementListeners(), opened: world.page.opened };
	}
);

scenario(
	'a page with no file box: no error, nothing changed (the old-style file control left alone), no listener on any element, and the document\'s listeners are the other job\'s alone',
	{ errors: [], same: true, elements: 0, document: [ 'DOMContentLoaded 1', 'click 1', 'keydown 1', 'submit 1' ] },
	() => {
		const before = build( '', 'none' ).snapshot();
		const world = loaded( source, 'none' );

		world.window.wpcpmParts();

		return { errors: world.page.errors, same: world.snapshot() === before, elements: world.elementListeners(), document: world.documentListeners() };
	}
);

/* Mutation proofs */
scenario(
	'and the script without the line that listens for the choice is told apart: the name stays No file chosen',
	'No file chosen',
	() => {
		const world = loaded( without( source, 'input.addEventListener( \'change\', follow );' ), 'one' );

		world.choose( world.one( '.wpcpm-ui-file__input' ), [ 'Signed agreement 2026.pdf' ] );

		return world.one( '.wpcpm-ui-file__name' ).textContent;
	}
);

scenario(
	'and the script without the line that clips the control is told apart: the control keeps its class alone, so the browser\'s control would show beside the button',
	'wpcpm-ui-file__input',
	() => boxState( loaded( without( source, 'input.classList.add( \'wpcpm-ui-file__input--hidden\' );' ), 'one' ) ).input
);

console.log( ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
