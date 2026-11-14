'use strict';
/*
 * assets/js/parts.js, run in node: what the parts' script does with a Decide area and a list's row forms.
 *
 *   php bin/test-parts-js.php
 *
 * which runs this file the way bin/test-dashboard-card.php runs bin/js/dashboard-open.js:
 *
 *   node bin/js/parts.js <path to parts.js> <path to the decide() fixture>
 *
 * The script is run on a stand-in for the page: a small tree of elements that keep their attributes, their
 * children, a field's value and where the focus is, and forms that record what they post. The Decide areas
 * are `WPCPM_UI::decide()`'s own markup, written by bin/test-parts-js.php to a fixture file passed as the
 * second argument and read into the stand-in, so the page cannot drift from what the site prints (design
 * 3.2: every panel printed open, the openers and the Cancels `hidden`):
 *
 *   - areas A and B, two applications: Approve (its whole form in the row), and Ask and Reject, each an
 *     opener with its own panel; one page prints A's Ask panel `data-wpcpm-open`, as after a refusal;
 *   - area C, a school's request: Mark as handled and Decline, two openers controlling one shared panel,
 *     each opener and each submit carrying `data-wpcpm-for` its decision's key.
 *
 * Built by hand, for what decide() cannot print: list L, two rows each with an Edit opener and its row
 * form; a Decide area and a row form holding the plugin's type-and-pick box (an input with
 * `role="combobox"`, `aria-controls` and `aria-expanded`); a shared panel whose openers are in the other
 * order from its submits; and a page whose lookup of one panel throws.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a summary
 * line, and exits 1 when any scenario differs. Two scenarios are mutation proofs: the script without the
 * line that hides the area's other panels, and without the line that moves the focus into a panel, must each
 * make a scenario go wrong; a check that passes whatever the script does would pass those too.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'parts.js' ) );
// The fixture, argument 3, is read below (FIXTURE).

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

/**
 * Whether an element matches one simple selector: a tag, `.class`, `[attr]` or `[attr="value"]`, or a
 * tag or class followed by attribute tests. The script's selectors are these and lists of them.
 *
 * @param {Element} node     The element.
 * @param {string}  selector One selector, no commas.
 * @return {boolean}
 */
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

	const tests = parts[ 3 ].match( /\[[^\]]+\]/g ) || [];

	return tests.every( ( test ) => {
		const pair = /^\[([a-z0-9_-]+)(?:="([^"]*)")?\]$/i.exec( test );

		if ( ! node.hasAttribute( pair[ 1 ] ) ) {
			return false;
		}

		return undefined === pair[ 2 ] || node.getAttribute( pair[ 1 ] ) === pair[ 2 ];
	} );
}

class Element {
	constructor( page, tag, attrs, label ) {
		this.page = page;
		this.nodeName = tag.toUpperCase();
		this.tagName = this.nodeName;
		this.attrs = {};
		this.label = label || '';
		this.children = [];
		this.parentElement = null;
		this.value = '';
		Object.keys( attrs || {} ).forEach( ( name ) => {
			if ( false !== attrs[ name ] ) {
				this.attrs[ name ] = true === attrs[ name ] ? '' : String( attrs[ name ] );
			}
		} );
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

	contains( other ) {
		for ( let node = other; node; node = node.parentElement ) {
			if ( node === this ) {
				return true;
			}
		}

		return false;
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

	// A click on a submit button posts its form, as a browser does; the stand-in records what posted.
	click() {
		const form = this.closest( 'form' );

		if ( form && 'submit' === ( this.getAttribute( 'type' ) || 'submit' ) && 'BUTTON' === this.nodeName ) {
			this.page.submit( form, null, this );
		}
	}

	// A form's requestSubmit( submitter ), where the page has it.
	get requestSubmit() {
		if ( 'FORM' !== this.nodeName || this.page.noRequestSubmit ) {
			return undefined;
		}

		return ( submitter ) => {
			this.page.requested.push( submitter ? submitter.label : '(none)' );
			this.page.submit( this, submitter, submitter );
		};
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

/**
 * Reads markup into the stand-in: the tags and attributes `decide()` prints, a tag's text becoming its label
 * (the words a test looks it up by). Void elements and `/>` close themselves; an attribute with no value is
 * a bare attribute; `&quot;`, `&#039;`, `&lt;`, `&gt;` and `&amp;` are decoded.
 *
 * @param {string}   html The markup.
 * @param {Element}  into Where it goes.
 * @param {Function} make Makes an element.
 */
function parseInto( html, into, make ) {
	const VOID = [ 'input', 'br', 'img', 'hr', 'meta', 'link', 'source', 'wbr' ];
	const decode = ( text ) => text.replace( /&quot;/g, '"' ).replace( /&#0?39;/g, '\'' ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&#038;/g, '&' ).replace( /&amp;/g, '&' );
	const stack = [ into ];
	const token = /<(\/?)([a-zA-Z][a-zA-Z0-9]*)((?:\s+[^\s=>\/]+(?:\s*=\s*"[^"]*")?)*)\s*(\/?)>|([^<]+)/g;
	let match;

	while ( null !== ( match = token.exec( html ) ) ) {
		const top = stack[ stack.length - 1 ];

		if ( undefined !== match[ 5 ] ) {
			const text = decode( match[ 5 ] ).trim();

			if ( text ) {
				top.label = ( top.label ? top.label + ' ' : '' ) + text;
			}
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
 * The real markup of `decide()`, written by bin/test-parts-js.php: a JSON object of pages, each the areas
 * A and B (two applications: Approve as its form in the row, Ask and Reject as openers with their panels)
 * and C (a school's request: Mark as handled and Decline sharing one panel). `plain` has nothing open,
 * `a-ask-open` prints A's Ask panel `data-wpcpm-open` as after a refused question, `c-declined-open`
 * prints the shared panel open after a refused Decline.
 */
const FIXTURE = process.argv[ 3 ] && fs.existsSync( process.argv[ 3 ] ) ? JSON.parse( fs.readFileSync( process.argv[ 3 ], 'utf8' ) ) : {};

/**
 * Fill a page with one fixture page, then name what the tests look up: a panel `decide-A-ask-panel` gives
 * its box the label `A ask box` and its Cancel `A ask Cancel`.
 *
 * @param {string}   name The fixture page.
 * @param {Element}  into Where it goes.
 * @param {Function} make Makes an element.
 */
function decided( name, into, make ) {
	if ( ! FIXTURE[ name ] ) {
		throw new Error( 'no fixture page ' + name + ' (bin/test-parts-js.php writes it)' );
	}

	parseInto( FIXTURE[ name ], into, make );

	into.all().filter( ( node ) => node.hasAttribute( 'data-wpcpm-panel' ) ).forEach( ( panel ) => {
		const key = panel.id.replace( /^decide-/, '' ).replace( /-panel$/, '' ).replace( '-', ' ' );

		panel.all().forEach( ( node ) => {
			if ( 'TEXTAREA' === node.nodeName ) {
				node.label = key + ' box';
			} else if ( node.hasAttribute( 'data-wpcpm-cancel' ) ) {
				node.label = key + ' Cancel';
			} else if ( 'INPUT' === node.nodeName && 'hidden' !== node.type ) {
				// Task V1: a one-line box or a checkbox, by its type ("D change text", "E approve checkbox").
				node.label = key + ' ' + node.type;
			}
		} );
	} );
}

/**
 * A list of two rows, as row() prints them with a caller's opener and form: each row's form under its line.
 *
 * @param {Function} make Makes an element.
 * @param {Element}  into Where it goes.
 */
function list( make, into ) {
	const ul = into.append( make( 'ul', { class: 'wpcpm-ui-list', id: 'list-L' } ) );

	[ '1', '2' ].forEach( ( n ) => {
		const li = ul.append( make( 'li', { class: 'wpcpm-ui-list__row' } ) );
		const line = li.append( make( 'div', { class: 'wpcpm-ui-list__line' } ) );

		line.append( make( 'div', { class: 'wpcpm-ui-list__act' } ) ).append( make( 'button', { type: 'button', class: 'wpcpm-btn wpcpm-btn--quiet', hidden: true, 'aria-controls': 'row-form-' + n, 'aria-expanded': 'false' }, 'row ' + n + ' Edit' ) );

		const holder = li.append( make( 'div', { class: 'wpcpm-ui-list__form', id: 'row-form-' + n } ) );
		const form = holder.append( make( 'form', { method: 'post' } ) );

		form.append( make( 'input', { type: 'hidden', name: 'row', value: n } ) );
		form.append( make( 'input', { type: 'date', name: 'until' }, 'row ' + n + ' date' ) );
		form.append( make( 'button', { type: 'submit', class: 'wpcpm-btn' }, 'row ' + n + ' submit' ) );
		form.append( make( 'button', { type: 'button', class: 'wpcpm-btn wpcpm-btn--quiet', hidden: true, 'data-wpcpm-cancel': true }, 'row ' + n + ' Cancel' ) );
	} );
}

/**
 * A page for the script to run on, and the means to load it, press a control on it and type in it.
 *
 * @param {string}   source The script.
 * @param {Function} fill   Fills the body; given the body and a function that makes an element.
 * @param {object}   [opt]  parsing: the document is still loading when the script runs; twice: the script
 *                          is included twice.
 * @return {object}
 */
function build( source, fill, opt ) {
	const o = opt || {};
	const page = { listeners: { window: {}, document: {} }, posted: [], requested: [], submits: 0, noRequestSubmit: !! o.noRequestSubmit, throwOn: o.throwOn || '' };
	const make = ( tag, attrs, label ) => new Element( page, tag, attrs, label );
	const document = make( 'html' );
	const body = document.append( make( 'body' ) );

	page.document = document;
	document.body = body;
	document.documentElement = document;
	document.activeElement = body;
	document.readyState = o.parsing ? 'loading' : 'complete';
	fill( body, make );

	document.getElementById = ( id ) => {
		if ( page.throwOn && id === page.throwOn ) {
			throw new Error( 'the lookup of ' + id + ' failed' );
		}

		return body.all().filter( ( node ) => node.id === id )[ 0 ] || null;
	};
	document.addEventListener = ( type, listener ) => {
		( page.listeners.document[ type ] = page.listeners.document[ type ] || [] ).push( listener );
	};

	const window = {
		document,
		addEventListener: ( type, listener ) => {
			( page.listeners.window[ type ] = page.listeners.window[ type ] || [] ).push( listener );
		},
	};
	const sandbox = { document, window };

	vm.runInNewContext( source, sandbox );

	if ( o.twice ) {
		vm.runInNewContext( source, sandbox );
	}

	// An event reaches the document's listeners, as one bubbling from the element would (a listener in
	// the capture phase is among them: nothing else on the stand-in listens).
	const fire = ( type, event ) => {
		( page.listeners.document[ type ] || [] ).forEach( ( listener ) => listener( event ) );
	};
	// A form submitted: the submit event, with the submitter a browser reports (none for an old browser or
	// a form posted by a click it did not see), then, unless a listener prevented it, the post, recorded
	// as the button that posted it.
	page.submit = ( form, submitter, via ) => {
		const event = { type: 'submit', target: form, submitter: submitter || null, defaultPrevented: false, preventDefault() {
			this.defaultPrevented = true;
		} };

		if ( ++page.submits > 10 ) {
			throw new Error( 'the form was submitted again and again' );
		}

		fire( 'submit', event );

		if ( ! event.defaultPrevented ) {
			page.posted.push( via ? via.label : ( submitter ? submitter.label : '(none)' ) );
		}
	};
	const byLabel = ( label ) => {
		const found = body.all().filter( ( node ) => node.label === label )[ 0 ];

		if ( ! found ) {
			throw new Error( 'no element ' + label );
		}

		return found;
	};

	return {
		page,
		body,
		byLabel,
		byId: ( id ) => document.getElementById( id ),
		parsed: () => {
			document.readyState = 'interactive';
			fire( 'DOMContentLoaded', {} );
		},
		// A press: a hidden control cannot be pressed; the control takes the focus and a click follows,
		// which is also what Enter and Space on a button do.
		press: ( label ) => {
			const node = byLabel( label );

			for ( let at = node; at; at = at.parentElement ) {
				if ( at.hidden ) {
					throw new Error( label + ' is hidden and cannot be pressed' );
				}
			}

			document.activeElement = node;
			fire( 'click', { type: 'click', target: node, button: 0 } );
		},
		key: ( key, extra ) => {
			fire( 'keydown', Object.assign( { type: 'keydown', key, target: document.activeElement, defaultPrevented: false, isComposing: false }, extra || {} ) );
		},
		// The form around a control submitted, with the submitter a browser reports: a label, or null.
		submit: ( inside, submitter ) => {
			page.submit( byLabel( inside ).closest( 'form' ), submitter ? byLabel( submitter ) : null );

			return page.posted;
		},
		window,
		type: ( label, text ) => {
			const node = byLabel( label );

			node.focus();
			node.value = text;
		},
		// Whether an element is shown: neither it nor anything around it carries `hidden`.
		shown: ( label ) => {
			for ( let at = byLabel( label ); at; at = at.parentElement ) {
				if ( at.hidden ) {
					return false;
				}
			}

			return true;
		},
		// Whether an element carries no `hidden` of its own (a Cancel in a folded panel is revealed, though
		// not shown until its panel is).
		revealed: ( label ) => ! byLabel( label ).hidden,
		// Read past a lookup the page is made to fail, so a test can see what the failure left.
		panelShown: ( id ) => ! body.all().filter( ( node ) => node.id === id )[ 0 ].hidden,
		expanded: ( label ) => byLabel( label ).getAttribute( 'aria-expanded' ),
		focus: () => ( document.activeElement === body ? 'page' : document.activeElement.label ),
		snapshot: () => body.all().map( ( node ) => node.nodeName + JSON.stringify( node.attrs ) + node.value ).join( '|' ),
	};
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';

// The whole page: A (with the given panel printed open), B, C (with the given opener expanded) and L.
function everything( openA, expandedC ) {
	let name = 'plain';

	if ( 'ask' === openA ) {
		name = 'a-ask-open';
	} else if ( 'declined' === expandedC ) {
		name = 'c-declined-open';
	}

	return ( body, make ) => {
		decided( name, body, make );
		list( make, body );
	};
}

// A page as it loads: still parsing when the script runs, then parsed.
function loaded( script, openA, expandedC ) {
	const world = build( script, everything( openA, expandedC ), { parsing: true } );

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

const PANELS = [ 'decide-A-ask-panel', 'decide-A-reject-panel', 'decide-B-ask-panel', 'decide-B-reject-panel', 'decide-C-done-panel', 'row-form-1', 'row-form-2' ];
const OPENERS = [ 'A ask opener', 'A reject opener', 'B ask opener', 'B reject opener', 'Mark as handled', 'Decline', 'row 1 Edit', 'row 2 Edit' ];
const CANCELS = [ 'A ask Cancel', 'A reject Cancel', 'B ask Cancel', 'B reject Cancel', 'C done Cancel', 'row 1 Cancel', 'row 2 Cancel' ];

// Which panels are shown, which openers say they are expanded.
function state( world ) {
	return {
		panels: PANELS.filter( ( id ) => world.panelShown( id ) ),
		expanded: OPENERS.filter( ( label ) => 'true' === world.expanded( label ) ),
	};
}

/* After load */
scenario(
	'with no script, the server\'s page holds every panel and row form open and every opener and Cancel hidden (what the script starts from)',
	{ panels: PANELS, openers: [], cancels: [] },
	() => {
		const world = build( '', everything( 'ask', '' ) );

		return { panels: PANELS.filter( ( id ) => world.panelShown( id ) ), openers: OPENERS.filter( world.revealed ), cancels: CANCELS.filter( world.revealed ) };
	}
);

scenario(
	'after load: every opener and every Cancel is revealed; every panel and row form is folded but the one printed data-wpcpm-open, whose opener reads aria-expanded="true"; the focus is not moved',
	{ openers: OPENERS, cancels: CANCELS, panels: [ 'decide-A-ask-panel' ], expanded: [ 'A ask opener' ], focus: 'page' },
	() => {
		const world = loaded( source, 'ask', '' );
		const now = state( world );

		return { openers: OPENERS.filter( world.revealed ), cancels: CANCELS.filter( world.revealed ), panels: now.panels, expanded: now.expanded, focus: world.focus() };
	}
);

scenario(
	'and a page already parsed when the script runs is folded at once; with no panel printed open, every panel is folded and no opener is expanded',
	{ parsed: [ 'decide-A-ask-panel' ], none: { panels: [], expanded: [] } },
	() => {
		const parsed = build( source, everything( 'ask', '' ) );

		return { parsed: state( parsed ).panels, none: state( loaded( source, '', '' ) ) };
	}
);

/* One area at a time */
scenario(
	'pressing the second area\'s Reject: its panel is shown, its opener expanded, the focus in its box, and the first area\'s open panel is untouched',
	{ panels: [ 'decide-A-ask-panel', 'decide-B-reject-panel' ], expanded: [ 'A ask opener', 'B reject opener' ], focus: 'B reject box' },
	() => {
		const world = loaded( source, 'ask', '' );

		world.press( 'B reject opener' );

		return Object.assign( state( world ), { focus: world.focus() } );
	}
);

scenario(
	'pressing the first area\'s Ask, then its Reject: Ask\'s panel is folded and its opener reads false; Reject\'s panel is shown, its opener true, the focus in its box',
	{ first: { panels: [ 'decide-A-ask-panel' ], expanded: [ 'A ask opener' ], focus: 'A ask box' }, then: { panels: [ 'decide-A-reject-panel' ], expanded: [ 'A reject opener' ], focus: 'A reject box' } },
	() => {
		const world = loaded( source, '', '' );

		world.press( 'A ask opener' );
		const first = Object.assign( state( world ), { focus: world.focus() } );

		world.press( 'A reject opener' );

		return { first, then: Object.assign( state( world ), { focus: world.focus() } ) };
	}
);

scenario(
	'pressing an open panel\'s opener again folds it, its opener reads false and keeps the focus',
	{ panels: [], expanded: [], focus: 'A reject opener' },
	() => {
		const world = loaded( source, '', '' );

		world.press( 'A reject opener' );
		world.press( 'A reject opener' );

		return Object.assign( state( world ), { focus: world.focus() } );
	}
);

/* Cancel and Escape */
scenario(
	'Cancel: the panel is folded, its opener reads false, the focus is back on the opener, and what was typed is kept',
	{ panels: [], expanded: [], focus: 'A reject opener', typed: 'Not this term.' },
	() => {
		const world = loaded( source, '', '' );

		world.press( 'A reject opener' );
		world.type( 'A reject box', 'Not this term.' );
		world.press( 'A reject Cancel' );

		return Object.assign( state( world ), { focus: world.focus(), typed: world.byLabel( 'A reject box' ).value } );
	}
);

scenario(
	'Escape in the box: the same; and Escape on the panel printed open after a refusal folds it and puts the focus on its opener',
	{ escape: { panels: [], expanded: [], focus: 'B ask opener' }, refused: { panels: [], expanded: [], focus: 'A ask opener' } },
	() => {
		const one = loaded( source, '', '' );

		one.press( 'B ask opener' );
		one.key( 'Escape' );

		const two = loaded( source, 'ask', '' );

		two.byLabel( 'A ask box' ).focus();
		two.key( 'Escape' );

		return { escape: Object.assign( state( one ), { focus: one.focus() } ), refused: Object.assign( state( two ), { focus: two.focus() } ) };
	}
);

scenario(
	'any other key in the box, and Escape outside every area or in another area, change nothing',
	{ other: { panels: [ 'decide-A-ask-panel' ], expanded: [ 'A ask opener' ], focus: 'A ask box' }, outside: { panels: [ 'decide-A-ask-panel' ], expanded: [ 'A ask opener' ], focus: 'page' }, another: { panels: [ 'decide-A-ask-panel' ], expanded: [ 'A ask opener' ], focus: 'B Approve' } },
	() => {
		const one = loaded( source, '', '' );

		one.press( 'A ask opener' );
		one.key( 'Enter' );
		one.key( 'a' );

		const two = loaded( source, 'ask', '' );

		two.key( 'Escape' );

		const three = loaded( source, 'ask', '' );

		three.byLabel( 'B Approve' ).focus();
		three.key( 'Escape' );

		return { other: Object.assign( state( one ), { focus: one.focus() } ), outside: Object.assign( state( two ), { focus: two.focus() } ), another: Object.assign( state( three ), { focus: three.focus() } ) };
	}
);

// Task V1, ruling 4: Escape closes the area's open panel wherever the focus is inside that area (its opener, a
// press of its row, the panel and its buttons), and puts the focus back on the opener that opened it.
scenario(
	'V1: Escape with the focus on a press of the area\'s row, or back on the opener, folds the area\'s open panel and puts the focus on its opener',
	{ row: { panels: [], expanded: [], focus: 'A ask opener' }, opener: { panels: [], expanded: [], focus: 'A reject opener' } },
	() => {
		const one = loaded( source, 'ask', '' );

		one.byLabel( 'A Approve' ).focus();
		one.key( 'Escape' );

		const two = loaded( source, '', '' );

		two.press( 'A reject opener' );
		two.byLabel( 'A reject opener' ).focus();
		two.key( 'Escape' );

		return { row: Object.assign( state( one ), { focus: one.focus() } ), opener: Object.assign( state( two ), { focus: two.focus() } ) };
	}
);

/* The shared panel */
scenario(
	'the shared panel: "Mark as handled" shows it with only the done submit; "Decline" then keeps it open with only the declined submit, swaps the two aria-expanded values and keeps what was typed',
	{
		handled: { panels: [ 'decide-C-done-panel' ], expanded: [ 'Mark as handled' ], submits: [ 'done submit' ], focus: 'C done box' },
		decline: { panels: [ 'decide-C-done-panel' ], expanded: [ 'Decline' ], submits: [ 'declined submit' ], typed: 'Called the school.' },
	},
	() => {
		const world = loaded( source, '', '' );
		const submits = () => [ 'done submit', 'declined submit' ].filter( world.shown );

		world.press( 'Mark as handled' );
		const handled = Object.assign( state( world ), { submits: submits(), focus: world.focus() } );

		world.type( 'C done box', 'Called the school.' );
		world.press( 'Decline' );

		return { handled, decline: Object.assign( state( world ), { submits: submits(), typed: world.byLabel( 'C done box' ).value } ) };
	}
);

scenario(
	'and Decline pressed again folds it; Escape after Decline puts the focus on Decline, the opener that opened it',
	{ again: { panels: [], expanded: [] }, escape: { panels: [], expanded: [], focus: 'Decline' } },
	() => {
		const one = loaded( source, '', '' );

		one.press( 'Decline' );
		one.press( 'Decline' );

		const two = loaded( source, '', '' );

		two.press( 'Mark as handled' );
		two.press( 'Decline' );
		two.key( 'Escape' );

		return { again: state( one ), escape: Object.assign( state( two ), { focus: two.focus() } ) };
	}
);

scenario(
	'a shared panel printed open after a refused Decline shows only the declined submit at load',
	{ panels: [ 'decide-C-done-panel' ], expanded: [ 'Decline' ], submits: [ 'declined submit' ] },
	() => {
		const world = loaded( source, '', 'declined' );

		return Object.assign( state( world ), { submits: [ 'done submit', 'declined submit' ].filter( world.shown ) } );
	}
);

/* A list's row forms */
scenario(
	'a list: a row\'s Edit shows its form under the row with the focus in its first field, the other row\'s Edit folds it, Cancel folds the second and puts the focus back on its Edit',
	{
		first: { panels: [ 'row-form-1' ], expanded: [ 'row 1 Edit' ], focus: 'row 1 date' },
		second: { panels: [ 'row-form-2' ], expanded: [ 'row 2 Edit' ], focus: 'row 2 date' },
		cancel: { panels: [], expanded: [], focus: 'row 2 Edit' },
	},
	() => {
		const world = loaded( source, '', '' );

		world.press( 'row 1 Edit' );
		const first = Object.assign( state( world ), { focus: world.focus() } );

		world.press( 'row 2 Edit' );
		const second = Object.assign( state( world ), { focus: world.focus() } );

		world.press( 'row 2 Cancel' );

		return { first, second, cancel: Object.assign( state( world ), { focus: world.focus() } ) };
	}
);

scenario(
	'opening a row form leaves every Decide area as it was, and opening a panel leaves the list as it was',
	{ panels: [ 'decide-A-ask-panel', 'decide-C-done-panel', 'row-form-1' ], expanded: [ 'A ask opener', 'Mark as handled', 'row 1 Edit' ] },
	() => {
		const world = loaded( source, 'ask', '' );

		world.press( 'row 1 Edit' );
		world.press( 'Mark as handled' );

		return state( world );
	}
);

/* Pages it must leave alone */
scenario(
	'a page with none of the parts: nothing changes and nothing is thrown, at load, on a press or on Escape',
	{ same: true, focus: 'a link' },
	() => {
		const world = build( source, ( body, make ) => {
			const p = body.append( make( 'p' ) );

			p.append( make( 'a', { href: '#x' }, 'a link' ) );
			p.append( make( 'button', { type: 'button', 'aria-controls': 'elsewhere', 'aria-expanded': 'false' }, 'a toggle of another script' ) );
			body.append( make( 'div', { id: 'elsewhere', hidden: true } ) );
		}, { parsing: true } );
		const before = world.snapshot();

		world.parsed();
		world.press( 'a link' );
		world.key( 'Escape' );
		world.press( 'a toggle of another script' );
		world.press( 'a link' );
		( world.page.listeners.document.click || [] ).forEach( ( fn ) => fn( { type: 'click', target: null } ) );
		( world.page.listeners.document.keydown || [] ).forEach( ( fn ) => fn( { type: 'keydown', key: 'Escape', target: world.page.document } ) );

		return { same: before === world.snapshot(), focus: world.focus() };
	}
);

scenario(
	'the script included twice runs once: one press opens a panel, and the second press folds it',
	{ open: [ 'decide-A-reject-panel' ], closed: [] },
	() => {
		const world = build( source, everything( '', '' ), { parsing: true, twice: true } );

		world.parsed();
		world.press( 'A reject opener' );
		const open = state( world ).panels;

		world.press( 'A reject opener' );

		return { open, closed: state( world ).panels };
	}
);

/* A widget of the plugin's own inside a panel: the type-and-pick box (switcher.js) */

/**
 * A type-and-pick box as the plugin prints it: an input with `role="combobox"`, `aria-controls` naming its
 * list and `aria-expanded`, and the list after it.
 *
 * @param {Function} make  Makes an element.
 * @param {Element}  into  Where it goes.
 * @param {string}   id    The input's id; the list's is `{id}-list`.
 * @param {boolean}  shown Whether the server printed the list shown.
 */
function combobox( make, into, id, shown ) {
	into.append( make( 'input', { type: 'text', id, role: 'combobox', 'aria-controls': id + '-list', 'aria-expanded': 'false', 'aria-autocomplete': 'list' }, id ) );
	into.append( make( 'ul', { id: id + '-list', role: 'listbox', hidden: ! shown }, id + ' list' ) );
}

// What switcher.js does when its list opens or closes.
function pick( world, id, open ) {
	world.byLabel( id ).setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	world.byLabel( id + ' list' ).hidden = ! open;
}

// A Decide area D whose one panel holds a type-and-pick box, and the list L whose first row form holds one.
function widgets( body, make ) {
	const area = body.append( make( 'div', { class: 'wpcpm-ui-decide', id: 'decide-D' } ) );
	const row = area.append( make( 'div', { class: 'wpcpm-ui-actions' } ) );

	row.append( make( 'button', { type: 'button', class: 'wpcpm-btn', hidden: true, 'aria-controls': 'decide-D-pick-panel', 'aria-expanded': 'false' }, 'D opener' ) );

	const form = area.append( make( 'div', { class: 'wpcpm-ui-panel', id: 'decide-D-pick-panel', 'data-wpcpm-panel': true } ) ).append( make( 'form', { method: 'post' } ) );

	combobox( make, form, 'D-pick', true );
	form.append( make( 'button', { type: 'submit', class: 'wpcpm-btn' }, 'D submit' ) );
	form.append( make( 'button', { type: 'button', class: 'wpcpm-btn wpcpm-btn--quiet', hidden: true, 'data-wpcpm-cancel': true }, 'D Cancel' ) );
	list( make, body );
	combobox( make, body.all().filter( ( node ) => 'row-form-1' === node.id )[ 0 ].children[ 0 ], 'row-1-pick', false );
}

function widgetsLoaded() {
	const world = build( source, widgets, { parsing: true } );

	world.parsed();

	return world;
}

// What a test reads of the widgets' page.
function widgetState( world ) {
	return {
		panels: [ 'decide-D-pick-panel', 'row-form-1', 'row-form-2' ].filter( ( id ) => world.panelShown( id ) ),
		lists: [ 'D-pick list', 'row-1-pick list' ].filter( world.revealed ),
		expanded: [ 'D-pick', 'row-1-pick' ].filter( ( label ) => 'true' === world.expanded( label ) ),
	};
}

scenario(
	'a type-and-pick box in a panel and in a row form is no opener: after load its list is as the server printed it, and only the form\'s own opener folded the form',
	{ panels: [], lists: [ 'D-pick list' ], expanded: [], revealed: [ 'D opener', 'row 1 Edit' ] },
	() => {
		const world = widgetsLoaded();

		return Object.assign( widgetState( world ), { revealed: [ 'D opener', 'row 1 Edit' ].filter( world.revealed ) } );
	}
);

scenario(
	'opening the form and pressing the box leaves the form open; Escape with the box\'s list open leaves it open (switcher.js handles that Escape), and Escape with the list closed folds it and puts the focus on its opener',
	{
		row: { pressed: [ 'row-form-1' ], listOpen: [ 'row-form-1' ], handled: [ 'row-form-1' ], closed: [], focus: 'row 1 Edit' },
		panel: { pressed: [ 'decide-D-pick-panel' ], listOpen: [ 'decide-D-pick-panel' ], composing: [ 'decide-D-pick-panel' ], closed: [], focus: 'D opener' },
	},
	() => {
		const one = widgetsLoaded();
		const shown = ( world ) => widgetState( world ).panels;

		one.press( 'row 1 Edit' );
		one.press( 'row-1-pick' );
		const rowPressed = shown( one );

		pick( one, 'row-1-pick', true );
		one.key( 'Escape' );
		const rowListOpen = shown( one );

		one.key( 'Escape', { defaultPrevented: true } );
		const rowHandled = shown( one );

		pick( one, 'row-1-pick', false );
		one.key( 'Escape' );

		const two = widgetsLoaded();

		two.press( 'D opener' );
		two.press( 'D-pick' );
		const panelPressed = shown( two );

		pick( two, 'D-pick', true );
		two.key( 'Escape' );
		const panelListOpen = shown( two );

		pick( two, 'D-pick', false );
		two.key( 'Escape', { isComposing: true } );
		const panelComposing = shown( two );

		two.key( 'Escape' );

		return {
			row: { pressed: rowPressed, listOpen: rowListOpen, handled: rowHandled, closed: shown( one ), focus: one.focus() },
			panel: { pressed: panelPressed, listOpen: panelListOpen, composing: panelComposing, closed: shown( two ), focus: two.focus() },
		};
	}
);

/* One area that fails does not stop the others */
scenario(
	'an area whose panel lookup throws is left as the server printed it, the area after it is folded, and a later window.wpcpmParts() folds it once the lookup works',
	{ failed: { panels: [ 'decide-A-ask-panel', 'decide-A-reject-panel' ], openers: [], cancels: [] }, after: { panels: [], openers: [ 'B ask opener', 'B reject opener' ] }, again: { panels: [], openers: [ 'A ask opener', 'A reject opener' ] } },
	() => {
		const world = build( source, everything( '', '' ), { parsing: true, throwOn: 'decide-A-ask-panel' } );
		const read = ( letter ) => ( {
			panels: [ 'decide-' + letter + '-ask-panel', 'decide-' + letter + '-reject-panel' ].filter( ( id ) => world.panelShown( id ) ),
			openers: [ letter + ' ask opener', letter + ' reject opener' ].filter( world.revealed ),
		} );

		world.parsed();
		const failed = Object.assign( read( 'A' ), { cancels: [ 'A ask Cancel', 'A reject Cancel' ].filter( world.revealed ) } );
		const after = read( 'B' );

		world.page.throwOn = '';
		world.window.wpcpmParts();

		return { failed, after, again: read( 'A' ) };
	}
);

/* A shared panel pairs an opener with its submit by key */
function shared( keyed ) {
	return ( body, make ) => {
		const area = body.append( make( 'div', { class: 'wpcpm-ui-decide', id: 'decide-F' } ) );
		const row = area.append( make( 'div', { class: 'wpcpm-ui-actions' } ) );

		// The openers in the other order from the submits: only a key can pair them right.
		[ [ 'declined', 'F Decline' ], [ 'done', 'F Mark as handled' ] ].forEach( ( pair ) => {
			row.append( make( 'button', { type: 'button', class: 'wpcpm-btn', hidden: true, 'aria-controls': 'decide-F-panel', 'aria-expanded': 'false', 'data-wpcpm-for': keyed ? pair[ 0 ] : false }, pair[ 1 ] ) );
		} );

		const form = area.append( make( 'div', { class: 'wpcpm-ui-panel', id: 'decide-F-panel', 'data-wpcpm-panel': true } ) ).append( make( 'form', { method: 'post' } ) );

		form.append( make( 'input', { type: 'text', name: 'note' }, 'F box' ) );
		[ 'done', 'declined' ].forEach( ( key ) => {
			form.append( make( 'button', { type: 'submit', name: 'state', value: key, 'data-wpcpm-for': key }, 'F ' + key + ' submit' ) );
		} );
	};
}

scenario(
	'an opener carrying data-wpcpm-for shows the submit with the same key whatever their order; an opener with none falls back to its position',
	{ keyed: [ 'F declined submit' ], position: [ 'F done submit' ] },
	() => {
		const keyed = build( source, shared( true ) );
		const position = build( source, shared( false ) );
		const subs = ( world ) => [ 'F done submit', 'F declined submit' ].filter( world.shown );

		keyed.press( 'F Decline' );
		position.press( 'F Decline' );

		return { keyed: subs( keyed ), position: subs( position ) };
	}
);

/* A hidden submit is never posted */
scenario(
	'in a shared panel opened by Decline, a post the browser makes with the hidden done submit (Enter in a one-line box) or with no submitter goes out with the shown declined submit',
	{ hidden: [ 'declined submit' ], none: [ 'declined submit' ], requested: [ 'declined submit' ] },
	() => {
		const one = loaded( source, '', '' );

		one.press( 'Decline' );
		one.submit( 'C done box', 'done submit' );

		const two = loaded( source, '', '' );

		two.press( 'Decline' );
		two.submit( 'C done box', null );

		return { hidden: one.page.posted, none: two.page.posted, requested: one.page.requested };
	}
);

scenario(
	'a press of the shown submit is left alone, a panel with one submit is left alone, and where requestSubmit is missing the shown submit is clicked, once',
	{ shown: [ 'declined submit' ], shownRequested: [], single: [ '(none)' ], noRequest: [ 'declined submit' ], submits: 2 },
	() => {
		const one = loaded( source, '', '' );

		one.press( 'Decline' );
		one.submit( 'C done box', 'declined submit' );

		const two = loaded( source, '', '' );

		two.press( 'A reject opener' );
		two.submit( 'A reject box', null );

		const three = build( source, everything( '', '' ), { parsing: true, noRequestSubmit: true } );

		three.parsed();
		three.press( 'Decline' );
		three.submit( 'C done box', 'done submit' );

		return { shown: one.page.posted, shownRequested: one.page.requested, single: two.page.posted, noRequest: three.page.posted, submits: three.page.submits };
	}
);

/* Mutation proofs */
scenario(
	'the script without the line that hides the area\'s other panels is told apart: Ask stays open beside Reject',
	[ 'decide-A-ask-panel', 'decide-A-reject-panel' ],
	() => {
		const world = loaded( without( source, 'closeOthers( area, panel );' ), '', '' );

		world.press( 'A ask opener' );
		world.press( 'A reject opener' );

		return state( world ).panels;
	}
);

scenario(
	'and the script without the line that moves the focus into the panel is told apart: the focus stays on the opener',
	'B reject opener',
	() => {
		// Since task V1 the call is the test of an `if`, whose other branch is the dismissing button's focus:
		// the test is made false, so no field takes the focus (a panel with a field has its Cancel too).
		const line = 'if ( ! focusFirstField( panel ) ) {';
		const world = loaded( without( source, line ).replace( '\n\t\t\tfocusDismiss( panel );\n\t\t}', '' ), '', '' );

		world.press( 'B reject opener' );

		return world.focus();
	}
);

/* Task V1, ruling 4: a panel with no field, and the events' and the sponsor application's panels */
const V1_PANELS = [ 'decide-D-change-panel', 'decide-D-cancel-panel', 'decide-E-approve-panel' ];
const V1_OPENERS = [ 'D change opener', 'D cancel opener', 'E approve opener' ];

function noField() {
	const world = build( source, ( body, make ) => decided( 'no-field', body, make ), { parsing: true } );

	world.parsed();

	return world;
}

function v1State( world ) {
	return {
		panels: V1_PANELS.filter( ( id ) => world.panelShown( id ) ),
		expanded: V1_OPENERS.filter( ( label ) => 'true' === world.expanded( label ) ),
		focus: world.focus(),
	};
}

scenario(
	'V1: "Cancel the event", a panel with no field: pressed, it opens with its opener expanded and the focus on its dismissing button ("Keep the event"), never on the confirming one; Escape there folds it and puts the focus back on the opener',
	{ opened: { panels: [ 'decide-D-cancel-panel' ], expanded: [ 'D cancel opener' ], focus: 'D cancel Cancel' }, escaped: { panels: [], expanded: [], focus: 'D cancel opener' } },
	() => {
		const world = noField();

		world.press( 'D cancel opener' );
		const opened = v1State( world );

		world.key( 'Escape' );

		return { opened, escaped: v1State( world ) };
	}
);

scenario(
	'V1: and Escape with the focus on its opener or on its confirming button folds it too, the focus on the opener; its dismissing button folds it as Cancel does',
	{ opener: { panels: [], expanded: [], focus: 'D cancel opener' }, confirm: { panels: [], expanded: [], focus: 'D cancel opener' }, keep: { panels: [], expanded: [], focus: 'D cancel opener' } },
	() => {
		const one = noField();

		one.press( 'D cancel opener' );
		one.byLabel( 'D cancel opener' ).focus();
		one.key( 'Escape' );

		const two = noField();

		two.press( 'D cancel opener' );
		two.byLabel( 'D cancel submit' ).focus();
		two.key( 'Escape' );

		const three = noField();

		three.press( 'D cancel opener' );
		three.press( 'D cancel Cancel' );

		return { opener: v1State( one ), confirm: v1State( two ), keep: v1State( three ) };
	}
);

scenario(
	'V1: "Change the event" opens with the focus in its first field, and the sponsor application\'s Approve on its checkbox; Escape folds each and puts the focus back on its opener',
	{
		change: { panels: [ 'decide-D-change-panel' ], expanded: [ 'D change opener' ], focus: 'D change text' },
		changeEscaped: { panels: [], expanded: [], focus: 'D change opener' },
		approve: { panels: [ 'decide-E-approve-panel' ], expanded: [ 'E approve opener' ], focus: 'E approve checkbox' },
		approveEscaped: { panels: [], expanded: [], focus: 'E approve opener' },
	},
	() => {
		const world = noField();

		world.press( 'D change opener' );
		const change = v1State( world );

		world.key( 'Escape' );
		const changeEscaped = v1State( world );

		world.press( 'E approve opener' );
		const approve = v1State( world );

		world.key( 'Escape' );

		return { change, changeEscaped, approve, approveEscaped: v1State( world ) };
	}
);

scenario(
	'V1: and the script without the line that moves the focus to a dismissing button is told apart: the focus stays on the opener',
	'D cancel opener',
	() => {
		const world = build( without( source, 'focusDismiss( panel );' ), ( body, make ) => decided( 'no-field', body, make ), { parsing: true } );

		world.parsed();
		world.press( 'D cancel opener' );

		return world.focus();
	}
);

scenario(
	'V1: and the script without the line that folds the area\'s open panel on an Escape outside it is told apart: the panel stays open',
	[ 'decide-D-cancel-panel' ],
	() => {
		const world = build( without( source, 'closeOpenIn( area );' ), ( body, make ) => decided( 'no-field', body, make ), { parsing: true } );

		world.parsed();
		world.press( 'D cancel opener' );
		world.byLabel( 'D cancel opener' ).focus();
		world.key( 'Escape' );

		return V1_PANELS.filter( ( id ) => world.panelShown( id ) );
	}
);

console.log( ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
