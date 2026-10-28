'use strict';
/*
 * assets/js/dashboard-open.js, run in node: what the opener does with a fragment, and with a press on a
 * link to one, on the three pages that load it.
 *
 *   php bin/test-dashboard-card.php
 *
 * which runs this file the way bin/test-student-modules.php runs bin/js/modules-arrange.js:
 *
 *   node bin/js/dashboard-open.js <path to dashboard-open.js>
 *
 * The script is run on a stand-in for the page: a small tree of elements that keep their attributes,
 * their children, whether a `<details>` is open and where the focus is, and a browser that follows a link
 * the way one does (the address changes, a hashchange follows when it did, the focus of what a press hid
 * goes to the page) before the script's own task after the press runs. Three pages are built, each as the
 * plugin prints the part of it that matters here:
 *
 *   - the Institution Dashboard: a part is a `section` whose id is its anchor, `#wpcpm-import` for
 *     Enroll students, and whose first child is the folded `<details>`; the link inside it that was not
 *     found, Download a sample list, sits in the folded body;
 *   - the Administrator Dashboard: a card is a `section` of the same shape, and a tile in the At a glance
 *     strip links to it;
 *   - the Sponsor Dashboard: a card's anchor is its `<details>` itself, and a row's action after the
 *     fold links to it and is hidden once the card is open.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a summary
 * line, and exits 1 when any scenario differs. Two scenarios are mutation proofs: the script without the
 * rule that opens the disclosure a section names, and without the rule that opens the ones around the
 * element a fragment names, each run through the page it matters on, must go wrong; a check that passes
 * whatever the script does would pass those too.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'dashboard-open.js' ) );

let ran = 0;
let failed = 0;

function scenario( name, want, build ) {
	return Promise.resolve()
		.then( build )
		.catch( ( error ) => 'threw: ' + error.message )
		.then( ( got ) => {
			const ok = JSON.stringify( got ) === JSON.stringify( want );

			ran++;
			failed += ok ? 0 : 1;
			console.log( ( ok ? 'ok   ' : 'FAIL ' ) + name );

			if ( ! ok ) {
				console.log( '       exp: ' + JSON.stringify( want ) + '  got: ' + JSON.stringify( got ) );
			}
		} );
}

/* ---- The page ---- */

class Element {
	constructor( tag, attrs, label ) {
		this.nodeName = tag.toUpperCase();
		this.attrs = Object.assign( {}, attrs || {} );
		this.id = this.attrs.id || '';
		this.className = this.attrs.class || '';
		this.label = label || '';
		this.children = [];
		this.parentElement = null;
		this.open = false;
		this.hiddenWith = null;
		this.page = null;
	}

	append( child ) {
		child.parentElement = this;
		this.children.push( child );

		return child;
	}

	get firstElementChild() {
		return this.children[ 0 ] || null;
	}

	get nextElementSibling() {
		const siblings = this.parentElement ? this.parentElement.children : [];

		return siblings[ siblings.indexOf( this ) + 1 ] || null;
	}

	// A summary has a place in the tab order and takes the focus, as every current browser has it.
	get tabIndex() {
		return 'SUMMARY' === this.nodeName ? 0 : -1;
	}

	get isLink() {
		return 'A' === this.nodeName;
	}

	matches( selector ) {
		if ( 'a[href^="#wpcpm-"]' === selector ) {
			return this.isLink && 0 === String( this.attrs.href ).indexOf( '#wpcpm-' );
		}

		if ( '.' === selector[ 0 ] ) {
			return this.className.split( ' ' ).indexOf( selector.slice( 1 ) ) !== -1;
		}

		return this.nodeName === selector.toUpperCase();
	}

	closest( selector ) {
		let node = this;

		while ( node ) {
			if ( node.matches( selector ) ) {
				return node;
			}

			node = node.parentElement;
		}

		return null;
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
	}

	setAttribute( name, value ) {
		this.attrs[ name ] = value;
	}

	// A row's action has no box once its card is open: one rule in the stylesheets.
	getClientRects() {
		return this.hiddenWith && this.hiddenWith.open ? [] : [ {} ];
	}

	focus() {
		this.page.document.activeElement = this;
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
}

/**
 * A page for the script to run on, and the means to load it, change its address and press a link on it.
 *
 * @param {string}   source The script.
 * @param {Function} fill   Fills the page's body; given the body and a function that makes an element.
 * @param {object}   [from] The address the page loads with, and whether the document is still parsing.
 * @return {object}
 */
function build( source, fill, from ) {
	const start = from || {};
	const page = { document: null, hash: start.hash || '', timers: [], listeners: { window: {}, document: {} } };
	const document = new Element( 'html' );
	const body = document.append( new Element( 'body' ) );

	page.document = document;
	document.page = page;
	document.body = body;
	document.activeElement = body;
	document.readyState = start.parsing ? 'loading' : 'complete';

	const make = ( tag, attrs, label ) => {
		const node = new Element( tag, attrs, label );

		node.page = page;

		return node;
	};

	body.page = page;
	fill( body, make );

	document.getElementById = ( id ) => body.all().filter( ( node ) => node.id === id )[ 0 ] || null;
	document.addEventListener = ( type, listener ) => {
		( page.listeners.document[ type ] = page.listeners.document[ type ] || [] ).push( listener );
	};

	const sandbox = {
		document,
		window: {
			location: {
				get hash() {
					return page.hash;
				},
			},
			addEventListener: ( type, listener ) => {
				( page.listeners.window[ type ] = page.listeners.window[ type ] || [] ).push( listener );
			},
			setTimeout: ( fn ) => {
				page.timers.push( fn );
			},
		},
	};

	vm.runInNewContext( source, sandbox );

	const fire = ( where, type, event ) => ( page.listeners[ where ][ type ] || [] ).forEach( ( listener ) => listener( event ) );
	// The task a press leaves for after the browser has followed the link.
	const settle = () => {
		const timers = page.timers.splice( 0 );

		timers.forEach( ( fn ) => fn() );
	};
	// A browser drops the focus of what it hides to the page.
	const dropHidden = () => {
		const at = document.activeElement;

		if ( at !== body && at.getClientRects && 0 === at.getClientRects().length ) {
			document.activeElement = body;
		}
	};
	const byId = ( id ) => document.getElementById( id );

	return {
		page,
		byId,
		// The document has been parsed.
		parsed: () => {
			document.readyState = 'interactive';
			fire( 'document', 'DOMContentLoaded', {} );
		},
		// The reader follows a link to a fragment, or the address is changed by hand.
		go: ( hash ) => {
			const changed = hash !== page.hash;

			page.hash = hash;

			if ( changed ) {
				fire( 'window', 'hashchange', {} );
			}
		},
		// Which disclosures are open, by the title each is drawn with.
		opened: () => body.all().filter( ( node ) => 'DETAILS' === node.nodeName && node.open ).map( ( node ) => node.label ),
		// Where the focus is: the title of the disclosure it is in, the link it is on, or the page.
		focus: () => {
			const at = document.activeElement;

			if ( at === body ) {
				return 'page';
			}

			return 'SUMMARY' === at.nodeName ? 'title of ' + at.parentElement.label : 'link ' + at.label;
		},
		// A press on a link: the click reaches the document, the browser follows the link (the address
		// changes, and so the page hears of it when it is not the address already), what the press hid
		// loses the focus, and then the task the script left runs.
		press: ( link, modifiers ) => {
			const href = link.getAttribute( 'href' );

			document.activeElement = link;
			fire( 'document', 'click', Object.assign( { target: link, button: 0 }, modifiers || {} ) );

			if ( ! ( modifiers && ( modifiers.ctrlKey || modifiers.metaKey || modifiers.shiftKey || modifiers.altKey || modifiers.button ) ) ) {
				const changed = href !== page.hash;

				page.hash = href;

				if ( changed ) {
					fire( 'window', 'hashchange', {} );
				}
			}

			dropHidden();
			settle();
		},
	};
}

/* ---- The three pages, as much of each as the opener meets ---- */

/**
 * A card's disclosure: folded unless asked, a summary with its title, and a body.
 *
 * @param {Function} make  Makes an element.
 * @param {Element}  into  Where it goes.
 * @param {string}   title The title.
 * @param {object}   attrs The disclosure's attributes.
 * @param {boolean}  open  Whether it is drawn open.
 * @return {Element} The disclosure.
 */
function disclosure( make, into, title, attrs, open ) {
	const details = into.append( make( 'details', attrs, title ) );
	const summary = details.append( make( 'summary', { class: 'wpcpm-group__summary' } ) );

	summary.append( make( 'h3', {}, title ) );
	details.open = !! open;

	return details;
}

// The Institution Dashboard's Students module, as the roster and the Enroll students part print it: the
// part is a section whose first child is the folded disclosure, and the form, with the sample list's link
// beside the file box, is in the disclosure's body. The roster's own groups and the semester report's part
// are drawn around it in the same shape, People open and Semester report folded.
function institution( body, make ) {
	const students = body.append( make( 'div', { id: 'wpcpm-module-students', class: 'wpcpm-module wpcpm-module--students' } ) );
	const roster = disclosure( make, students.append( make( 'section', { class: 'wpcpm-institution__card' } ) ), 'Current', { class: 'wpcpm-group__disclosure' }, true );
	const student = disclosure( make, roster.append( make( 'div', { class: 'wpcpm-group__body' } ) ), 'A student', { class: 'wpcpm-mentee__disclosure', id: 'wpcpm-student-rec1' }, false );
	const section = students.append( make( 'section', { id: 'wpcpm-import', class: 'wpcpm-institution__card wpcpm-import' } ) );
	const enroll = disclosure( make, section, 'Enroll students', { class: 'wpcpm-group wpcpm-group__disclosure wpcpm-import__disclosure' }, false );
	const form = enroll.append( make( 'div', { class: 'wpcpm-group__body wpcpm-import__body' } ) ).append( make( 'form' ) );

	form.append( make( 'input', { id: 'wpcpm-import-file', type: 'file' } ) );
	form.append( make( 'a', { href: 'https://example.test/wp-admin/admin-post.php?action=sample', class: 'wpcpm-import__sample' }, 'Download a sample list' ) );
	disclosure( make, body.append( make( 'section', { id: 'wpcpm-report', class: 'wpcpm-institution__card wpcpm-report-card' } ) ), 'Semester report', { class: 'wpcpm-group__disclosure' }, false );
	disclosure( make, body.append( make( 'section', { id: 'wpcpm-people', class: 'wpcpm-institution__card' } ) ), 'People', { class: 'wpcpm-group__disclosure' }, true );

	// A link in a mail, a notice or a colleague's message to the part, and a link whose box is gone once
	// the part is open (a row's action, which the other two dashboards hide with one rule).
	body.append( make( 'a', { href: '#wpcpm-import', id: 'to-import' }, 'to Enroll students' ) );
	body.append( make( 'a', { href: '#wpcpm-import', id: 'to-import-hidden' }, 'Enroll students action' ) ).hiddenWith = enroll;
	body.append( make( 'a', { href: '#wpcpm-import-file', id: 'to-file' }, 'to the file box' ) );
	body.append( make( 'a', { href: '#wpcpm-student-rec1', id: 'to-student' }, 'to a student' ) );
	body.append( make( 'a', { href: '#wpcpm-nothing-here', id: 'to-nothing' }, 'to nothing' ) );
	body.append( make( 'a', { href: '#wpcpm-module-students', id: 'to-module' }, 'to the module' ) );
}

// The Administrator Dashboard: a card is a section of the same shape, with its action after the fold,
// and the At a glance strip's tiles link to the section.
function administrator( body, make ) {
	const tiles = body.append( make( 'ul', { class: 'wpcpm-attention__tiles' } ) );
	const section = body.append( make( 'section', { id: 'wpcpm-institution-applications', class: 'wpcpm-administrator__card' } ) );
	const card = disclosure( make, section, 'Institution applications', { class: 'wpcpm-administrator__disclosure wpcpm-group__disclosure' }, false );

	card.append( make( 'div', { class: 'wpcpm-group__body' } ) );
	section.append( make( 'a', { href: '#wpcpm-institution-applications', id: 'action' }, 'Review' ) ).hiddenWith = card;
	tiles.append( make( 'li' ) ).append( make( 'a', { href: '#wpcpm-institution-applications', id: 'tile' }, 'Institution applications tile' ) );
}

// The Sponsor Dashboard: a card's anchor is its disclosure's own id.
function sponsor( body, make ) {
	const tiles = body.append( make( 'ul', { class: 'wpcpm-attention__tiles' } ) );
	const section = body.append( make( 'section', { class: 'wpcpm-sponsor__card' } ) );
	const card = disclosure( make, section, 'Posts', { id: 'wpcpm-sponsor-posts', class: 'wpcpm-sponsor__disclosure wpcpm-group__disclosure' }, false );

	card.append( make( 'div', { class: 'wpcpm-group__body' } ) );
	section.append( make( 'a', { href: '#wpcpm-sponsor-posts', id: 'action' }, 'Write a post' ) ).hiddenWith = card;
	tiles.append( make( 'li' ) ).append( make( 'a', { href: '#wpcpm-sponsor-posts', id: 'tile' }, 'Posts tile' ) );
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';

// What the Institution Dashboard shows of the opener after a page loads at an address.
function institutionLoaded( script, hash ) {
	const world = build( script, institution, { hash, parsing: true } );

	world.parsed();

	return world.opened();
}

// The script with one rule taken out; a rule that is not found fails the proof instead of passing it.
function without( script, rule ) {
	if ( -1 === script.indexOf( rule ) ) {
		throw new Error( 'the rule to take out was not found' );
	}

	return script.replace( rule, '' );
}

( async () => {
	/* The Institution Dashboard */
	await scenario(
		'Institution Dashboard: a page that loads at #wpcpm-import opens Enroll students, the disclosure inside the section the fragment names, and nothing else',
		[ 'Current', 'Enroll students', 'People' ],
		() => institutionLoaded( source, '#wpcpm-import' )
	);

	await scenario(
		'and a page already parsed when the script runs opens it at once, and one that loads at no fragment opens nothing',
		{ parsed: [ 'Current', 'Enroll students', 'People' ], none: [ 'Current', 'People' ] },
		() => {
			const parsed = build( source, institution, { hash: '#wpcpm-import' } );

			return { parsed: parsed.opened(), none: institutionLoaded( source, '' ) };
		}
	);

	await scenario(
		'and the fragment changed to #wpcpm-import later, by a link or by hand, opens it',
		{ before: [ 'Current', 'People' ], after: [ 'Current', 'Enroll students', 'People' ] },
		() => {
			const world = build( source, institution, { hash: '', parsing: true } );
			const before = world.opened();

			world.parsed();
			world.go( '#wpcpm-import' );

			return { before, after: world.opened() };
		}
	);

	await scenario(
		'a fragment naming something inside the part, the sample list\'s file box, opens it, and one naming a student opens the roster group around the student and the student',
		{ file: [ 'Current', 'Enroll students', 'People' ], student: [ 'Current', 'A student', 'People' ] },
		() => {
			const file = build( source, institution, { hash: '#wpcpm-import-file', parsing: true } );
			const student = build( source, institution, { hash: '#wpcpm-student-rec1', parsing: true } );

			file.parsed();
			student.parsed();

			return { file: file.opened(), student: student.opened() };
		}
	);

	await scenario(
		'a press on a link to #wpcpm-import opens the part even when the address already holds that fragment, which fires no hashchange, and the link still on screen keeps the focus',
		{ opened: [ 'Current', 'Enroll students', 'People' ], focus: 'link to Enroll students' },
		() => {
			const world = build( source, institution, { hash: '#wpcpm-import', parsing: true } );

			world.parsed();
			world.byId( 'wpcpm-import' ).firstElementChild.open = false;
			world.press( world.byId( 'to-import' ) );

			return { opened: world.opened(), focus: world.focus() };
		}
	);

	await scenario(
		'and a link with no box once the part is open (the pressed link is hidden by its own card opening) hands the focus to the part\'s title, as it does on the other two dashboards',
		{ opened: [ 'Current', 'Enroll students', 'People' ], focus: 'title of Enroll students' },
		() => {
			const world = build( source, institution, { parsing: true } );

			world.parsed();
			world.press( world.byId( 'to-import-hidden' ) );

			return { opened: world.opened(), focus: world.focus() };
		}
	);

	await scenario(
		'a fragment naming nothing on the page changes nothing: not at load, not on a change, not on a press, and the focus stays where it was',
		{ opened: [ 'Current', 'People' ], focus: 'link to nothing' },
		() => {
			const world = build( source, institution, { hash: '#wpcpm-nothing-here', parsing: true } );

			world.parsed();
			world.go( '#' );
			world.go( '' );
			world.go( '#wpcpm-import%' );
			world.go( '#wpcpm-%E0%A4%A' );
			world.press( world.byId( 'to-nothing' ) );

			return { opened: world.opened(), focus: world.focus() };
		}
	);

	await scenario(
		'a fragment naming an element that is in no disclosure and holds none as its first child, the module the page moves to after an arrow press, opens nothing and moves no focus',
		{ opened: [ 'Current', 'People' ], focus: 'link to the module' },
		() => {
			const world = build( source, institution, { hash: '#wpcpm-module-students', parsing: true } );

			world.parsed();
			world.press( world.byId( 'to-module' ) );

			return { opened: world.opened(), focus: world.focus() };
		}
	);

	await scenario(
		'a press that opens its link somewhere else, a new tab (Ctrl, Cmd or Shift held, or a button other than the first) or a download (Alt held), opens nothing here and moves no focus',
		[ 'Current,People|link Enroll students action', 'Current,People|link Enroll students action', 'Current,People|link Enroll students action', 'Current,People|link Enroll students action', 'Current,People|link Enroll students action' ],
		() => [ { ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 } ].map( ( modifiers ) => {
			const world = build( source, institution, { parsing: true } );

			world.parsed();
			world.press( world.byId( 'to-import-hidden' ), modifiers );

			return world.opened().join( ',' ) + '|' + world.focus();
		} )
	);

	await scenario(
		'a part already open stays open when a link to it is pressed, and nothing is ever closed',
		[ 'Current', 'Enroll students', 'People' ],
		() => {
			const world = build( source, institution, { parsing: true } );

			world.parsed();
			world.go( '#wpcpm-import' );
			world.press( world.byId( 'to-import' ) );
			world.press( world.byId( 'to-file' ) );

			return world.opened();
		}
	);

	/* The Administrator Dashboard and the Sponsor Dashboard, as before */
	await scenario(
		'Administrator Dashboard: a card\'s anchor is its section\'s, and a link to it opens the card, and a tile pressed hands the focus to the card\'s title although the tile stays on screen',
		{ load: [ 'Institution applications' ], action: [ 'Institution applications', 'title of Institution applications' ], tile: [ 'Institution applications', 'title of Institution applications' ], none: [] },
		() => {
			const loaded = build( source, administrator, { hash: '#wpcpm-institution-applications', parsing: true } );
			const action = build( source, administrator, { parsing: true } );
			const tile = build( source, administrator, { parsing: true } );
			const none = build( source, administrator, { hash: '#wpcpm-nothing-here', parsing: true } );

			[ loaded, action, tile, none ].forEach( ( world ) => world.parsed() );
			action.press( action.byId( 'action' ) );
			tile.press( tile.byId( 'tile' ) );

			return {
				load: loaded.opened(),
				action: [ action.opened()[ 0 ], action.focus() ],
				tile: [ tile.opened()[ 0 ], tile.focus() ],
				none: none.opened(),
			};
		}
	);

	await scenario(
		'Sponsor Dashboard: a card\'s anchor is its disclosure\'s own id, and a link to it opens the card, a row\'s action hidden by the opening hands the focus to the card\'s title, and a tile does too',
		{ load: [ 'Posts' ], action: [ 'Posts', 'title of Posts' ], tile: [ 'Posts', 'title of Posts' ], none: [] },
		() => {
			const loaded = build( source, sponsor, { hash: '#wpcpm-sponsor-posts', parsing: true } );
			const action = build( source, sponsor, { parsing: true } );
			const tile = build( source, sponsor, { parsing: true } );
			const none = build( source, sponsor, { hash: '#wpcpm-sponsor-nothing', parsing: true } );

			[ loaded, action, tile, none ].forEach( ( world ) => world.parsed() );
			action.press( action.byId( 'action' ) );
			tile.press( tile.byId( 'tile' ) );

			return {
				load: loaded.opened(),
				action: [ action.opened()[ 0 ], action.focus() ],
				tile: [ tile.opened()[ 0 ], tile.focus() ],
				none: none.opened(),
			};
		}
	);

	/* Mutation proofs */
	await scenario(
		'the script without the rule that opens the disclosure a section names is told apart: Enroll students stays folded at #wpcpm-import',
		[ 'Current', 'People' ],
		() => institutionLoaded( without( source, 'disclosure = target.firstElementChild;\n\t\t\tdisclosure.open = true;' ), '#wpcpm-import' )
	);

	await scenario(
		'and without the rule that opens the disclosures around the element a fragment names, #wpcpm-import-file opens nothing',
		[ 'Current', 'People' ],
		() => institutionLoaded( without( source, 'node.open = true;' ), '#wpcpm-import-file' )
	);

	await scenario(
		'and the script that never moves the focus is told apart: a link hidden by the opening leaves the focus on the page',
		'page',
		() => {
			const world = build( without( source, 'focusCard( disclosure, link );' ), institution, { parsing: true } );

			world.parsed();
			world.press( world.byId( 'to-import-hidden' ) );

			return world.focus();
		}
	);

	console.log( ran + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
} )();
