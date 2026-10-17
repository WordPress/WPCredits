'use strict';
/*
 * The site header's scripts, run in node on the header's own server markup (bin/test-header-js.php).
 *
 *   node bin/js/header-harness.js <theme root>  < markup.json
 *
 * The markup comes on stdin, as bin/test-header-js.php renders it with the theme's own header filter:
 * a JSON object of named pages. Each scenario opens a page in the stand-in of bin/js/hub-dom.js, runs
 * assets/js/header-height.js and then assets/js/header-menu.js as a browser runs deferred scripts,
 * after the markup, and drives the page as a person does: a press (a pointerdown alone, or a full
 * one with the focus and the click after it), a touch, a key pressed on the element that has the
 * focus, the focus moved, a link followed.
 *
 * What the height script reads of the layout is stood in for on the page's window: a ResizeObserver
 * whose callback is kept and called by the page's `resize()`, frames that are queued and run by
 * `resize()` after the callback, and getComputedStyle() answering an element's `position` property,
 * 'static' when it has none.
 *
 * The plugin's handbook assistant is stood in for as its footer script behaves, listening on the
 * document, in the bubble phase, before the theme's scripts run: a click on
 * `[data-wpcpm-handbook-open]` is prevented, keeps that button as what opened the assistant, puts
 * `wpcpm-hb-open` on the root element and gives the focus to the page's `<main>`; the page's
 * `closeAssistant()` takes the class away, then gives the focus back to what opened it, as the
 * plugin's own close does.
 *
 * A scenario may return a promise, awaited before its answer is compared, so the mutation records a
 * browser delivers in a microtask have been delivered. Prints `ok   <scenario>` or `FAIL <scenario>`
 * with what was expected and what happened, then `N scenarios, M differ`, and exits 1 when any
 * differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const { page, pressKey, makeEvent } = require( './hub-dom.js' );

const ROOT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..' ) );
const PAGES = JSON.parse( fs.readFileSync( 0, 'utf8' ) );
const SCRIPTS = {
	height: fs.readFileSync( path.join( ROOT, 'assets/js/header-height.js' ), 'utf8' ),
	menu: fs.readFileSync( path.join( ROOT, 'assets/js/header-menu.js' ), 'utf8' ),
};

const scenarios = [];

function scenario( name, want, build ) {
	scenarios.push( { name, want, build } );
}

/**
 * A step a scenario's answer rests on. When it does not hold, the scenario stops and says which, so a
 * step that went wrong early cannot leave the answer looking right.
 *
 * @param {boolean} fact Whether the step holds.
 * @param {string}  what What it says.
 */
function holds( fact, what ) {
	if ( ! fact ) {
		throw new Error( what );
	}
}

/**
 * Open a named page and run the header's scripts on it.
 *
 * @param {string}   name   A page of PAGES.
 * @param {Function} before Optional: called with the page before the scripts run, as where a scenario
 *                          lays out the boxes the height script reads as it starts, or takes away
 *                          something the window would have.
 * @return {Object} The page, with its `resize()` and `closeAssistant()`.
 */
function open( name, before ) {
	const where = page( PAGES[ name ], '' );
	const root = where.document.documentElement;
	const observers = [];
	const frames = [];
	let opener = null;

	where.window.ResizeObserver = class {
		constructor( callback ) {
			this.callback = callback;
			observers.push( this );
		}

		observe( target ) {
			this.target = target;
		}
	};

	// A frame's number is never 0, as a browser's is not.
	where.window.requestAnimationFrame = ( callback ) => frames.push( callback );
	where.window.getComputedStyle = ( element ) => ( { position: element.position || 'static' } );

	where.resize = () => {
		observers.forEach( ( observer ) => observer.callback( [ { target: observer.target } ], observer ) );
		frames.splice( 0 ).forEach( ( frame ) => frame( 0 ) );
	};

	where.document.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest( '[data-wpcpm-handbook-open]' );

		if ( trigger ) {
			event.preventDefault();
			opener = trigger;
			root.classList.add( 'wpcpm-hb-open' );
			where.document.querySelector( 'main' ).focus();
		}
	} );

	where.closeAssistant = () => {
		root.classList.remove( 'wpcpm-hb-open' );

		if ( opener && root.contains( opener ) ) {
			opener.focus();
		}

		opener = null;
	};

	if ( before ) {
		before( where );
	}

	const context = vm.createContext( { document: where.document, window: where.window } );

	[ 'height', 'menu' ].forEach( ( key ) => vm.runInContext( SCRIPTS[ key ], context ) );

	return where;
}

/**
 * Focus an element, then click it, as a keyboard or a tap that focuses does.
 *
 * @param {Element} element The element.
 */
function tap( element ) {
	element.focus();
	element.click();
}

/**
 * A press as a browser makes it with a mouse: a pointerdown on the element, then the focus (to the
 * link, button or element with a tabindex the element is or sits in, or, where there is none, away
 * from whatever had it), then the click. A button is focused by the press here; Safari does not focus
 * one, which a scenario makes with `blur()` and `click()`.
 *
 * @param {Element} element What is pressed.
 */
function fullPress( element ) {
	const focusable = element.closest( 'a[href], button, [tabindex]' );
	const had = element.ownerDocument.activeElement;

	press( element );

	if ( focusable ) {
		focusable.focus();
	} else if ( had ) {
		had.blur();
	}

	element.click();
}

/**
 * Wait until every microtask queued so far, and every one those queue, has run.
 *
 * @return {Promise} Settled after them.
 */
function settled() {
	return new Promise( ( resolve ) => setImmediate( resolve ) );
}

const toggle = ( where ) => where.document.querySelector( '.wpc-menu-toggle' );
const header = ( where ) => where.document.querySelector( '.wpc-header' );
const panel = ( where ) => where.document.getElementById( 'wpc-header-menu' );
const expanded = ( where ) => toggle( where ).getAttribute( 'aria-expanded' );
const marked = ( where, name ) => header( where ).classList.contains( name );
const help = ( where ) => where.document.querySelector( '[data-wpcpm-handbook-open]' );
const text = ( where ) => where.document.querySelector( 'main p' );
const pageLink = ( where ) => where.document.querySelector( 'main a[href]' );
const focused = ( where, element ) => where.document.activeElement === element;
const assistantOpen = ( where ) => where.document.documentElement.classList.contains( 'wpcpm-hb-open' );
const entries = ( where ) => panel( where ).querySelectorAll( '.wp-block-navigation-item__content, .wpc-ask, .wpc-viewer__link, .wpc-viewer__logout' );
const press = ( element ) => element.dispatchEvent( makeEvent( 'pointerdown' ) );

/* ---- the button, Escape and a press outside --------------------------------------- */

scenario( 'the button opens the menu and closes it again, aria-expanded and the header\'s class saying so, the focus staying on the button', [ [ 'true', true, true ], [ 'false', false, true ] ], () => {
	const where = open( 'admin' );
	const button = toggle( where );
	const states = [];

	tap( button );
	states.push( [ expanded( where ), marked( where, 'wpc-header--open' ), focused( where, button ) ] );
	tap( button );
	states.push( [ expanded( where ), marked( where, 'wpc-header--open' ), focused( where, button ) ] );

	return states;
} );

// Each entry is focused in turn with the menu open, and every one answers alike; the line compares the
// answer from Log out, the last of them.
scenario( 'Escape closes the open menu from any entry in it and puts focus on the Menu button', [ 'false', true ], () => {
	const where = open( 'admin' );
	const button = toggle( where );
	const answers = entries( where ).map( ( entry ) => {
		tap( button );
		entry.focus();
		pressKey( where, 'Escape' );

		return [ expanded( where ), focused( where, button ) ];
	} );

	holds( 5 === answers.length, 'the panel lists ' + answers.length + ' entries, not 5' );
	answers.forEach( ( answer, at ) => holds( JSON.stringify( answer ) === JSON.stringify( answers[ answers.length - 1 ] ), 'entry ' + at + ' answered ' + JSON.stringify( answer ) ) );

	return answers[ answers.length - 1 ];
} );

// The navigation block binds its own keydown handlers inside the panel; one that stops the event would
// keep a listener of the bubble phase on the document from hearing it.
scenario( 'Escape still closes it when a listener inside the panel stops the keydown, since the menu listens in the capture phase', [ 'false', true ], () => {
	const where = open( 'admin' );
	const button = toggle( where );

	where.document.querySelector( 'nav' ).addEventListener( 'keydown', ( event ) => event.stopPropagation() );
	tap( button );
	where.document.querySelector( '.wp-block-navigation-item__content' ).focus();
	pressKey( where, 'Escape' );

	return [ expanded( where ), focused( where, button ) ];
} );

scenario( 'Escape with the menu closed changes nothing and moves no focus', [ 'false', true ], () => {
	const where = open( 'admin' );

	pageLink( where ).focus();
	pressKey( where, 'Escape' );

	return [ expanded( where ), focused( where, pageLink( where ) ) ];
} );

// The picture is the chip's last element, inside the panel in the markup, and drawn in the bar.
scenario( 'a click outside the menu and the button closes it and moves no focus; so does a click on the account picture, drawn in the bar', [ [ 'false', true ], 'false' ], () => {
	const where = open( 'admin' );
	const button = toggle( where );
	const answers = [];

	tap( button );
	text( where ).click();
	answers.push( [ expanded( where ), focused( where, button ) ] );

	tap( button );
	holds( 'true' === expanded( where ), 'the menu did not open again' );
	where.document.querySelector( '.wpc-viewer__avatar' ).click();
	answers.push( expanded( where ) );

	return answers;
} );

scenario( 'a touch outside closes it on pointerdown, as on a phone that sends the document no click for a tap on plain text; a touch on a link in the menu, or on the Menu button, does not', [ 'false', 'true', 'true' ], () => {
	const where = open( 'admin' );
	const button = toggle( where );
	const states = [];

	tap( button );
	press( text( where ) );
	states.push( expanded( where ) );

	tap( button );
	press( where.document.querySelector( '.wpc-viewer__link' ) );
	states.push( expanded( where ) );
	press( button );
	states.push( expanded( where ) );

	return states;
} );

// The pieces of a press are tried apart above. Together they must not undo each other: the pointerdown
// closes the menu, and the click that follows must not open it again; the press of a text or of the
// picture leaves the focus on nothing, as a browser does; the press of the Menu button opens it and
// the next closes it, once each.
scenario( 'a full press, pointerdown then focus then click, on page text or on the account picture closes the menu and leaves it closed with the focus on nothing; on the Menu button it opens the menu, and the next closes it', [ [ 'false', true ], [ 'false', true ], [ 'true', 'false' ] ], () => {
	const answers = [
		( where ) => text( where ),
		( where ) => where.document.querySelector( '.wpc-viewer__avatar' ),
	].map( ( pressed ) => {
		const where = open( 'admin' );

		tap( toggle( where ) );
		holds( 'true' === expanded( where ), 'the menu did not open' );
		fullPress( pressed( where ) );

		return [ expanded( where ), null === where.document.activeElement ];
	} );
	const where = open( 'admin' );
	const states = [];

	fullPress( toggle( where ) );
	states.push( expanded( where ) );
	fullPress( toggle( where ) );
	states.push( expanded( where ) );
	answers.push( states );

	return answers;
} );

// A script on the page may stop a press from going further up than its own element; a listener on the
// document in the bubble phase would then never hear it. Each of the two presses is tried alone, on a
// page of its own.
scenario( 'a press outside still closes it when a listener on the page\'s content stops the pointerdown, or the click, since the menu listens in the capture phase', [ 'false', 'false' ], () => [
	( where ) => press( text( where ) ),
	( where ) => text( where ).click(),
].map( ( pressOutside ) => {
	const where = open( 'admin' );
	const heard = [];

	[ 'pointerdown', 'click' ].forEach( ( type ) => {
		where.document.querySelector( 'main' ).addEventListener( type, ( event ) => event.stopPropagation() );
		where.document.addEventListener( type, () => heard.push( type ) );
	} );

	tap( toggle( where ) );
	holds( 'true' === expanded( where ), 'the menu did not open' );
	heard.length = 0;
	pressOutside( where );
	holds( 0 === heard.length, 'the document heard the stopped ' + heard.join( ' and ' ) + ' in the bubble phase' );

	return expanded( where );
} ) );

/* ---- links, Need help? and the assistant --------------------------------------- */

// Another script may prevent a link's default and load the page itself; the menu closes all the same.
scenario( 'a link followed from the menu closes it, its default prevented or not; one followed with Ctrl, Cmd, Shift or Alt held opens elsewhere and leaves it open', [ [ 'false', 'false' ], [ 'true', 'true', 'true', 'true' ] ], () => {
	const where = open( 'admin' );
	const button = toggle( where );
	const [ student, mentor ] = where.document.querySelectorAll( '.wpc-viewer__link' );
	const followed = [];
	const elsewhere = [];

	tap( button );
	tap( student );
	followed.push( expanded( where ) );

	mentor.addEventListener( 'click', ( event ) => event.preventDefault() );
	tap( button );
	tap( mentor );
	followed.push( expanded( where ) );

	tap( button );
	[ 'ctrlKey', 'metaKey', 'shiftKey', 'altKey' ].forEach( ( key ) => {
		student.click( { [ key ]: true } );
		elsewhere.push( expanded( where ) );
	} );

	return [ followed, elsewhere ];
} );

// The buttons are pressed as Safari presses a button, without focusing it, so nothing in the header has
// the focus to lose when the assistant takes it: the press itself closes the menu. The plugin gives the
// focus back to Need help?, which is then in the closed panel (a browser cannot focus it there; the
// stand-in can, so the step is seen); the mutation record of the class going away comes after, in a
// microtask, and the menu's script moves the focus to the Menu button then.
scenario( 'Need help? in the open menu closes it, the assistant opens, and when the assistant closes focus comes to the Menu button, after the plugin gave it to Need help?', [ 'false', true, true ], async () => {
	const where = open( 'admin' );
	const button = toggle( where );

	button.click();
	help( where ).click();

	const answers = [ expanded( where ), assistantOpen( where ) ];

	await settled();
	where.closeAssistant();
	holds( focused( where, help( where ) ), 'the plugin did not give the focus to Need help?' );
	await settled();
	answers.push( focused( where, button ) );

	return answers;
} );

// The hand-off is marked only by Need help? in the open menu, and the mark is cleared when the menu
// opens: an assistant opened from the menu and still open while the menu is opened again and closed by
// Escape gives the focus back where the plugin puts it, and so does one opened after that, with the
// menu closed.
scenario( 'the assistant opened with the menu closed, as from the desktop row, gets its focus back from the plugin, on Need help?; so does one opened after the menu was opened again and closed by Escape', [ true, true ], async () => {
	const answers = [];
	let where = open( 'admin' );

	tap( help( where ) );
	holds( assistantOpen( where ), 'the assistant did not open' );
	await settled();
	where.closeAssistant();
	await settled();
	answers.push( focused( where, help( where ) ) );

	where = open( 'admin' );
	tap( toggle( where ) );
	tap( help( where ) );
	await settled();
	tap( toggle( where ) );
	pressKey( where, 'Escape' );
	holds( 'false' === expanded( where ) && focused( where, toggle( where ) ), 'Escape did not close the menu opened again' );
	where.closeAssistant();
	await settled();
	holds( focused( where, help( where ) ), 'the assistant open while the menu was opened again did not leave the focus on Need help?' );

	tap( help( where ) );
	await settled();
	where.closeAssistant();
	await settled();
	answers.push( focused( where, help( where ) ) );

	return answers;
} );

// The hand-off is marked by Need help? in the open menu, but the focus goes to the Menu button only
// when the root element's class goes from present to absent. Here the assistant never opens (a script
// on the page stops the click above the document, where the plugin listens), so the mark stays set
// and the root's class changes later for another reason, absent before and after.
scenario( 'the root element\'s class changing while Need help? was pressed in the menu but the assistant never opened moves no focus to the Menu button', [ 'false', true, false ], async () => {
	const where = open( 'admin' );
	const button = toggle( where );

	where.document.querySelector( '.wpc-header-part' ).addEventListener( 'click', ( event ) => event.stopPropagation() );
	button.click();
	help( where ).click();
	holds( 'false' === expanded( where ), 'Need help? did not close the menu' );
	holds( ! assistantOpen( where ), 'the assistant opened' );
	pageLink( where ).focus();
	where.document.documentElement.classList.add( 'another-script-was-here' );
	await settled();

	return [ expanded( where ), focused( where, pageLink( where ) ), focused( where, button ) ];
} );

/* ---- the focus leaving ----------------------------------------------------------- */

scenario( 'focus moving from the menu to the page closes it; to the Menu button, or to nothing (blur(), a press Safari does not focus), leaves it open', [ 'false', 'true', 'true' ], () => {
	const where = open( 'admin' );
	const button = toggle( where );
	const logOut = where.document.querySelector( '.wpc-viewer__logout' );
	const states = [];

	tap( button );
	logOut.focus();
	pageLink( where ).focus();
	states.push( expanded( where ) );

	tap( button );
	logOut.focus();
	button.focus();
	states.push( expanded( where ) );

	logOut.focus();
	logOut.blur();
	holds( null === where.document.activeElement, 'something kept the focus after blur()' );
	states.push( expanded( where ) );

	return states;
} );

// The submenu's toggle is a button in the panel: a press on it, and the focus moving to it, are inside
// the menu and leave it open.
scenario( 'a navigation of three items with a submenu: the submenu\'s toggle and links are in the panel and close it as the others do', [ 'false' ], () => {
	const where = open( 'submenu' );
	const button = toggle( where );
	const submenuToggle = panel( where ).querySelector( '.wp-block-navigation-submenu__toggle' );
	const submenuLink = panel( where ).querySelector( '.wp-block-navigation__submenu-container a[href]' );

	holds( submenuToggle && submenuLink, 'the submenu is not in the panel' );
	tap( button );
	press( submenuToggle );
	tap( submenuToggle );
	holds( 'true' === expanded( where ), 'the submenu\'s toggle closed the menu' );
	tap( submenuLink );

	return [ expanded( where ) ];
} );

// A student on their own page: the Student Report Card link is the page being viewed, so it carries
// the current-page mark in the open menu, and the menu lists four entries, the link among them.
scenario( 'a student on their own Student Report Card page: the open menu lists Homepage, Need help?, the Student Report Card and Log out, and the Student Report Card alone is marked as the current page', [ 'true', [ 'Homepage', 'Need help?', 'Student Report Card', 'Log out' ], [ 'Student Report Card' ], [ 'page' ] ], () => {
	const where = open( 'student' );
	const words = ( element ) => element.textContent.replace( /\s+/g, ' ' ).trim();

	tap( toggle( where ) );

	const current = panel( where ).querySelectorAll( '[aria-current]' );

	return [ expanded( where ), entries( where ).map( words ), current.map( words ), current.map( ( element ) => element.getAttribute( 'aria-current' ) ) ];
} );

/* ---- a header without the button, and without MutationObserver ---------------------- */

scenario( 'a header without the button, signed out or with one entry, is left alone: nothing throws and nothing is marked', [ [ false, false ], [ false, false ] ], () => [ 'guest', 'one' ].map( ( name ) => {
	let where = null;
	let threw = false;

	try {
		where = open( name );
	} catch ( error ) {
		threw = true;
	}

	holds( null === where || ! toggle( where ), 'the ' + name + ' page\'s header has a Menu button' );

	try {
		press( text( where ) );
		text( where ).click();
		where.document.querySelector( '.wpc-viewer a' ).focus();
		pageLink( where ).focus();
		pressKey( where, 'Escape' );
	} catch ( error ) {
		threw = true;
	}

	return [ null !== where && marked( where, 'wpc-header--open' ), threw ];
} ) );

scenario( 'without MutationObserver the menu still opens and closes, and the assistant\'s focus stays the plugin\'s', [ 'true', 'false', true ], async () => {
	const where = open( 'admin', ( page ) => {
		delete page.window.MutationObserver;
	} );
	const button = toggle( where );
	const states = [];

	tap( button );
	states.push( expanded( where ) );
	tap( help( where ) );
	states.push( expanded( where ) );
	await settled();
	where.closeAssistant();
	await settled();
	states.push( focused( where, help( where ) ) );

	return states;
} );

/* ---- the height script ----------------------------------------------------------- */

// The menu's panel is the group holding the links, placed absolutely under the bar below 1200px: it is
// out of the bar's row, wherever it lies, and the bar is one row.
scenario( 'the bar is marked stacked while the group holding the links sits below the brand in the row, and never while the group is out of the row, as the menu\'s panel is', [ true, false ], () => {
	const where = open( 'admin', ( page ) => {
		page.document.querySelector( '.wpc-brand' ).box = { top: 10, bottom: 54 };
		page.document.querySelector( '.wpc-nav__center' ).box = { top: 64, bottom: 300 };
	} );
	const seen = [ marked( where, 'wpc-header--stacked' ) ];

	where.document.querySelector( '.wpc-nav__center' ).position = 'absolute';
	where.resize();
	seen.push( marked( where, 'wpc-header--stacked' ) );

	return seen;
} );

scenario( 'the header\'s height is written on the root as before, rounded up', '65px', () => {
	const where = open( 'admin', ( page ) => {
		page.document.querySelector( '.wpc-header-part' ).box = { top: 0, bottom: 64.2 };
	} );

	return where.document.documentElement.style.getPropertyValue( '--wpc-header-live' );
} );

( async () => {
	let failed = 0;

	for ( const one of scenarios ) {
		let got;

		try {
			got = await one.build();
		} catch ( error ) {
			got = 'threw: ' + error.message;
		}

		const ok = JSON.stringify( got ) === JSON.stringify( one.want );

		failed += ok ? 0 : 1;
		console.log( ( ok ? 'ok   ' : 'FAIL ' ) + one.name );

		if ( ! ok ) {
			console.log( '       exp: ' + JSON.stringify( one.want ) + '  got: ' + JSON.stringify( got ) );
		}
	}

	console.log( scenarios.length + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
} )();
