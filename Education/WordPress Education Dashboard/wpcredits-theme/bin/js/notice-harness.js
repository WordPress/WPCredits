'use strict';
/*
 * The Notice template's forward to the homepage, run in node on its own server markup
 * (bin/test-notice-js.php).
 *
 *   node bin/js/notice-harness.js <theme root>  < markup.json
 *
 * Each scenario opens a page in the stand-in of bin/js/hub-dom.js and runs the forward block's view script
 * (blocks/notice-forward/view.js) as a browser runs a deferred script, after the markup. The page's clock
 * is the scenario's: `window.setInterval` and `window.clearInterval` keep the callbacks, and the page's
 * `tick()` runs those still set, one second passing; `window.location.assign()` records where the page was
 * sent instead of going. Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what
 * happened, then `N scenarios, M differ`, and exits 1 when any differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const { page } = require( './hub-dom.js' );

const ROOT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..' ) );
const PAGES = JSON.parse( fs.readFileSync( 0, 'utf8' ) );
const FILE = path.join( ROOT, 'blocks/notice-forward/view.js' );
const SCRIPT = fs.existsSync( FILE ) ? fs.readFileSync( FILE, 'utf8' ) : '';
const HOME = 'https://site.example/';

const scenarios = [];

function scenario( name, want, build ) {
	scenarios.push( { name, want, build } );
}

function holds( fact, what ) {
	if ( ! fact ) {
		throw new Error( what );
	}
}

/**
 * Open a named page with a clock of the scenario's, and run the script on it.
 *
 * @param {string} name A page of PAGES.
 * @return {Object} The page, with `tick()`, `timers` (how many are set) and `sent` (where it was sent).
 */
function open( name ) {
	const where = page( PAGES[ name ], '' );
	const timers = new Map();
	let next = 1;

	where.sent = [];
	where.window.setInterval = ( callback, delay ) => {
		timers.set( next, { callback, delay } );

		return next++;
	};
	where.window.clearInterval = ( id ) => timers.delete( id );
	where.window.setTimeout = () => {
		throw new Error( 'the script set a timeout; the clock here keeps intervals' );
	};
	where.window.location.assign = ( url ) => where.sent.push( url );
	where.tick = () => Array.from( timers.values() ).forEach( ( timer ) => timer.delay === 1000 && timer.callback() );
	where.timers = () => timers.size;

	vm.runInContext( SCRIPT, vm.createContext( { document: where.document, window: where.window } ) );

	return where;
}

const words = ( element ) => ( element ? element.textContent.replace( /\s+/g, ' ' ).trim() : null );
const count = ( where ) => words( where.document.querySelector( '.wpc-notice-forward__count' ) );
const line = ( where ) => words( where.document.querySelector( '.wpc-notice-forward__line' ) );
const said = ( where ) => words( where.document.querySelector( '.wpc-notice-forward__said' ) );
const stay = ( where ) => where.document.querySelector( '.wpc-notice-forward__stay' );

function openNotice() {
	const where = open( 'notice' );

	holds( !! where.document.querySelector( '.wpc-notice-forward' ), 'the page has no forward' );
	holds( '' !== SCRIPT, 'there is no blocks/notice-forward/view.js' );

	return where;
}

scenario( 'the countdown starts at once: the line says 5 seconds, one timer of a second is set, and the live region says the first line, once, for a screen reader', [ 'Taking you to the homepage in 5 seconds.', 1, 'Taking you to the homepage in 5 seconds.' ], () => {
	const where = openNotice();

	return [ count( where ), where.timers(), said( where ) ];
} );

scenario( 'each second the line counts down, "in 4 seconds" to "in 1 second", while the live region keeps the first line, so a screen reader is not told every second, and the page stays', [ [ 'Taking you to the homepage in 4 seconds.', 'Taking you to the homepage in 3 seconds.', 'Taking you to the homepage in 2 seconds.', 'Taking you to the homepage in 1 second.' ], [ 'Taking you to the homepage in 5 seconds.' ], [] ], () => {
	const where = openNotice();
	const lines = [];
	const told = new Set();

	for ( let second = 1; second <= 4; second++ ) {
		where.tick();
		lines.push( count( where ) );
		told.add( said( where ) );
	}

	return [ lines, Array.from( told ), where.sent ];
} );

scenario( 'after the fifth second the page goes to the homepage, once, and the timer is cleared', [ [ 'https://site.example/' ], 0 ], () => {
	const where = openNotice();

	for ( let second = 1; second <= 7; second++ ) {
		where.tick();
	}

	return [ where.sent, where.timers() ];
} );

scenario( '"Stay on this page" stops the countdown: no timer is left and no second sends the page anywhere; the line is replaced by "You\'re staying on this page.", which the live region says; the focus, which was on the button, moves to that line', [ [ 0, [] ], 'You\'re staying on this page.', 'You\'re staying on this page.', [ false, true ] ], () => {
	const where = openNotice();
	const button = stay( where );

	where.tick();
	where.tick();
	button.focus();
	button.click();

	for ( let second = 1; second <= 10; second++ ) {
		where.tick();
	}

	const stopped = where.document.querySelector( '.wpc-notice-forward__line' );

	return [ [ where.timers(), where.sent ], line( where ), said( where ), [ !! stay( where ), where.document.activeElement === stopped ] ];
} );

scenario( 'a press on Stay that does not focus the button (Safari\'s) stops it all the same and moves no focus; pressing the same button again, as a double press does, changes nothing more', [ [ 0, [], 'You\'re staying on this page.' ], null, [ 0, [], 'You\'re staying on this page.', 'You\'re staying on this page.' ], null ], () => {
	const where = openNotice();
	const button = stay( where );

	button.click();
	where.tick();

	const once = [ [ where.timers(), where.sent, line( where ) ], where.document.activeElement ];

	button.click();
	where.tick();

	return once.concat( [ [ where.timers(), where.sent, line( where ), said( where ) ], where.document.activeElement ] );
} );

// The review of 935f4b5 (M4): a page restored from the back-forward cache after the forward has gone keeps its
// last count and no timer; it shows the stayed line instead. A page shown afresh is left to count.
scenario( 'back from the homepage, the page restored from the back-forward cache (`pageshow`, persisted) shows "You\'re staying on this page." with no timer, never a frozen count; a `pageshow` that is no restore changes nothing', [ [ [ 'https://site.example/' ], 'You\'re staying on this page.', 0 ], [ 'Taking you to the homepage in 4 seconds.', 1 ] ], () => {
	const where = openNotice();

	for ( let second = 1; second <= 5; second++ ) {
		where.tick();
	}

	( where.window.listeners.pageshow || [] ).forEach( ( listener ) => listener( { type: 'pageshow', persisted: true } ) );

	const restored = [ where.sent, line( where ), where.timers() ];
	const fresh = openNotice();

	fresh.tick();
	( fresh.window.listeners.pageshow || [] ).forEach( ( listener ) => listener( { type: 'pageshow', persisted: false } ) );

	return [ restored, [ count( fresh ), fresh.timers() ] ];
} );

scenario( '"Go now" is a link to the homepage, there without the script, and its press is left to the browser', [ 'https://site.example/', 'Go now' ], () => {
	const where = openNotice();
	const go = where.document.querySelector( '.wpc-notice-forward__go' );

	return [ go.getAttribute( 'href' ), words( go ) ];
} );

scenario( 'a page without the forward, as every page but a Notice page is, is left alone: no timer, nothing sent, nothing throws', [ 0, [] ], () => {
	const where = open( 'plain' );

	where.tick();

	return [ where.timers(), where.sent ];
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
