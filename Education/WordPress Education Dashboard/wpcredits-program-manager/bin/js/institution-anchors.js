'use strict';
/*
 * assets/js/dashboard-open.js, run in node: an old link to the Institution Dashboard that names only an anchor,
 * `/institution-dashboard/#wpcpm-import`, moves to the view that holds the card (Task 10 of the dashboard frame).
 *
 *   php bin/test-institution-anchors.php
 *
 * which runs this file the way bin/test-dashboard-card.php runs bin/js/dashboard-open.js:
 *
 *   node bin/js/institution-anchors.js <path to dashboard-open.js> [<the map the Overview prints>]
 *
 * The Overview prints a map from each anchor the reader's views hold to the view's address, in a
 * `<script type="application/json" class="wpcpm-anchor-views">`. Once the page is parsed, when the fragment names
 * no element on the page and the map holds the fragment's id as its own key with an address for its value, the
 * script replaces the address with the view's, the fragment kept. Anything else is the old path: the card the
 * fragment names opens as before.
 *
 * The script is run on a stand-in for the page: an address that can be replaced (each call recorded), the
 * elements the fragment may name, a section whose first child is the folded `<details>` as Enroll students is
 * drawn, and the map's tags, each with its text. The second argument, when given, is the text the PHP suite drew
 * for a representative's Overview, so one scenario runs the real map.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a summary line,
 * and exits 1 when any scenario differs. The last scenarios are mutation proofs: the script without the test that
 * the fragment names no element on the page, without the test of the address's view and without the test of the
 * address itself must each go wrong where it matters; a check that passes whatever the script does would pass
 * those too.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'dashboard-open.js' ) );
const PRINTED = process.argv[ 3 ] || '';
const PAGE = 'https://example.test/institution-dashboard/';

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
 * The map as the Overview prints it: `wp_json_encode()` with its slashes escaped.
 *
 * @param {object} map Anchor to address.
 * @return {string}
 */
function printed( map ) {
	return JSON.stringify( map ).replace( /\//g, '\\/' );
}

// A representative's map, as `WPCPM_Institutions_Dashboard::anchor_views()` builds it for the fixture's page.
const MAP = printed( {
	'wpcpm-roster': PAGE + '?wpcpm_view=students',
	'wpcpm-import': PAGE + '?wpcpm_view=enroll',
	'wpcpm-report': PAGE + '?wpcpm_view=reports',
	'wpcpm-agreement': PAGE + '?wpcpm_view=agreement',
} );

/**
 * A page for the script to run on.
 *
 * @param {string} source The script.
 * @param {object} at     `href`, the address it loads at; `ids`, whether the Enroll students section is on the
 *                        page; `maps`, the text of each map tag, in order; `parsing`, whether the document is
 *                        still being parsed.
 * @return {object} `replaced`, the addresses the script replaced the page's with; `opened()`; `parsed()`; `go()`;
 *                  `flags`, what the sandbox's globals hold after the run.
 */
function load( source, at ) {
	const url = new URL( at.href );
	const replaced = [];
	const listeners = { window: {}, document: {} };
	const details = { nodeName: 'DETAILS', open: false, parentElement: null, firstElementChild: null };
	const section = { nodeName: 'SECTION', id: 'wpcpm-import', open: false, parentElement: null, firstElementChild: details };
	const scripts = ( at.maps || [] ).map( ( text ) => ( { nodeName: 'SCRIPT', textContent: text } ) );
	const sandbox = {};

	details.parentElement = section;

	const location = {
		get hash() {
			return url.hash;
		},
		get search() {
			return url.search;
		},
		get href() {
			return url.href;
		},
		replace: ( to ) => {
			replaced.push( String( to ) );
		},
	};

	const document = {
		readyState: at.parsing ? 'loading' : 'complete',
		body: {},
		activeElement: null,
		addEventListener: ( type, listener ) => {
			( listeners.document[ type ] = listeners.document[ type ] || [] ).push( listener );
		},
		getElementById: ( id ) => ( at.ids && 'wpcpm-import' === id ? section : null ),
		querySelector: ( selector ) => {
			if ( 'script.wpcpm-anchor-views' !== selector ) {
				throw new Error( 'asked for ' + selector );
			}

			return scripts[ 0 ] || null;
		},
	};

	sandbox.document = document;
	sandbox.window = {
		location,
		addEventListener: ( type, listener ) => {
			( listeners.window[ type ] = listeners.window[ type ] || [] ).push( listener );
		},
		setTimeout: () => {},
	};

	vm.runInNewContext( source, sandbox );

	const fire = ( where, type ) => ( listeners[ where ][ type ] || [] ).forEach( ( listener ) => listener( {} ) );

	return {
		replaced,
		opened: () => details.open,
		parsed: () => {
			document.readyState = 'interactive';
			fire( 'document', 'DOMContentLoaded' );
		},
		go: ( hash ) => {
			url.hash = hash;
			fire( 'window', 'hashchange' );
		},
		flags: () => ( { evaluated: !! sandbox.evaluated } ),
	};
}

// What a page loaded at an address does: the addresses it was replaced with, and whether Enroll students opened.
function loaded( source, href, maps, ids ) {
	const world = load( source, { href, maps, ids, parsing: true } );

	world.parsed();

	return { replaced: world.replaced, opened: world.opened() };
}

// The script with one rule taken out; a rule that is not found fails the proof instead of passing it.
function without( script, rule ) {
	if ( -1 === script.indexOf( rule ) ) {
		throw new Error( 'the rule to take out was not found' );
	}

	return script.replace( rule, '' );
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';
const STAY = { replaced: [], opened: false };

scenario(
	'an old link to #wpcpm-import on the Overview, which holds no such card, moves to the Enroll students view, the fragment kept',
	{ replaced: [ PAGE + '?wpcpm_view=enroll#wpcpm-import' ], opened: false },
	() => loaded( source, PAGE + '#wpcpm-import', [ MAP ], false )
);

scenario(
	'#wpcpm-import with the card on the page moves nowhere and opens it as before',
	{ replaced: [], opened: true },
	() => loaded( source, PAGE + '#wpcpm-import', [ MAP ], true )
);

scenario(
	'with no map on the page nothing moves: the Sponsor Dashboard, the Administrator Dashboard and every other view',
	STAY,
	() => loaded( source, PAGE + '#wpcpm-import', [], false )
);

scenario(
	'a page already parsed when the script runs moves at once',
	[ PAGE + '?wpcpm_view=enroll#wpcpm-import' ],
	() => load( source, { href: PAGE + '#wpcpm-import', maps: [ MAP ] } ).replaced
);

scenario(
	'an anchor the map does not hold moves nowhere: a card of a view the reader may not open, or no card at all',
	{ admin: STAY, nothing: STAY, none: STAY, empty: STAY },
	() => ( {
		admin: loaded( source, PAGE + '#wpcpm-for-administrators', [ MAP ], false ),
		nothing: loaded( source, PAGE + '#wpcpm-nothing-here', [ MAP ], false ),
		none: loaded( source, PAGE, [ MAP ], false ),
		empty: loaded( source, PAGE + '#', [ MAP ], false ),
	} )
);

scenario(
	'#__proto__, #constructor and the other names every object inherits move nowhere: only the map\'s own keys count',
	[ STAY, STAY, STAY, STAY, STAY ],
	() => [ '__proto__', 'constructor', 'toString', 'hasOwnProperty', 'valueOf' ].map( ( id ) => loaded( source, PAGE + '#' + id, [ MAP ], false ) )
);

scenario(
	'a map that holds the anchor with no address for it moves nowhere: a number, an object, a list, nothing, true, an empty string',
	[ STAY, STAY, STAY, STAY, STAY, STAY ],
	() => [ '1', '{}', '["' + PAGE + '"]', 'null', 'true', '""' ].map( ( value ) => loaded( source, PAGE + '#wpcpm-import', [ '{"wpcpm-import":' + value + '}' ], false ) )
);

scenario(
	'a map that is not an object moves nowhere: null, a list, a string, a number',
	[ STAY, STAY, STAY, STAY ],
	() => [ 'null', '["wpcpm-import"]', '"wpcpm-import"', '7' ].map( ( text ) => loaded( source, PAGE + '#wpcpm-import', [ text ], false ) )
);

scenario(
	'a broken map moves nowhere, nothing in it is run, and the old path runs: a later change of the fragment still opens the card',
	{ replaced: [], evaluated: false, later: true },
	() => {
		const world = load( source, { href: PAGE + '#wpcpm-import', maps: [ '{"wpcpm-import":"' + PAGE + '"}; evaluated = true' ], parsing: true } );
		const broken = [ '', '{', '{"wpcpm-import":', 'evaluated = true' ].map( ( text ) => loaded( source, PAGE + '#wpcpm-import', [ text ], false ).replaced.length );

		world.parsed();

		const replaced = world.replaced.concat( broken.filter( Boolean ) );
		const evaluated = world.flags().evaluated;

		// The page is the Overview, which holds no card: the old path is seen on a page that holds it.
		const page = load( source, { href: PAGE, maps: [ '{' ], ids: true, parsing: true } );

		page.parsed();
		page.go( '#wpcpm-import' );

		return { replaced, evaluated, later: page.opened() };
	}
);

scenario(
	'an address that already names a view moves nowhere: a view that falls back to the Overview never loops',
	{ students: STAY, enroll: STAY, empty: STAY, first: STAY },
	() => ( {
		students: loaded( source, PAGE + '?wpcpm_view=students#wpcpm-import', [ MAP ], false ),
		enroll: loaded( source, PAGE + '?wpcpm_institution_view=recEXA00000000001&wpcpm_view=enroll#wpcpm-import', [ MAP ], false ),
		empty: loaded( source, PAGE + '?wpcpm_view=#wpcpm-import', [ MAP ], false ),
		first: loaded( source, PAGE + '?wpcpm_view&x=1#wpcpm-import', [ MAP ], false ),
	} )
);

scenario(
	'and an argument whose name only ends in wpcpm_view names no view: the link still moves',
	[ PAGE + '?not_wpcpm_view=students&wpcpm_view=enroll#wpcpm-import' ],
	() => loaded( source, PAGE + '?not_wpcpm_view=students#wpcpm-import', [ printed( { 'wpcpm-import': PAGE + '?not_wpcpm_view=students&wpcpm_view=enroll' } ) ], false ).replaced
);

scenario(
	'a map whose address is the page\'s own address moves nowhere',
	{ bare: STAY, school: STAY },
	() => ( {
		bare: loaded( source, PAGE + '#wpcpm-import', [ printed( { 'wpcpm-import': PAGE } ) ], false ),
		school: loaded( source, PAGE + '?wpcpm_institution_view=recEXA00000000001#wpcpm-import', [ printed( { 'wpcpm-import': PAGE + '?wpcpm_institution_view=recEXA00000000001' } ) ], false ),
	} )
);

scenario(
	'after a move, the script run again on the address it moved to makes no second move: with the card there it opens it, and with a map and no card it stays',
	{ moved: [ PAGE + '?wpcpm_view=enroll#wpcpm-import' ], card: { replaced: [], opened: true }, fallback: STAY },
	() => {
		const moved = loaded( source, PAGE + '#wpcpm-import', [ MAP ], false ).replaced;

		return {
			moved,
			card: loaded( source, moved[ 0 ], [ MAP ], true ),
			fallback: loaded( source, moved[ 0 ], [ MAP ], false ),
		};
	}
);

scenario(
	'an Administrator\'s map keeps the school: the move lands on the view at the school',
	[ PAGE + '?wpcpm_view=enroll&wpcpm_institution_view=recEXA00000000001#wpcpm-import' ],
	() => loaded( source, PAGE + '?wpcpm_institution_view=recEXA00000000001#wpcpm-import', [ printed( { 'wpcpm-import': PAGE + '?wpcpm_view=enroll&wpcpm_institution_view=recEXA00000000001' } ) ], false ).replaced
);

scenario(
	'the fragment arrives percent-encoded, and the anchor put after the # is the map\'s key that matched',
	{ encoded: [ PAGE + '?wpcpm_view=enroll#wpcpm-import' ], broken: [] },
	() => ( {
		encoded: loaded( source, PAGE + '#wpcpm%2Dimport', [ MAP ], false ).replaced,
		broken: loaded( source, PAGE + '#wpcpm-import%E0%A4%A', [ MAP ], false ).replaced,
	} )
);

scenario(
	'the first map on the page is the one read',
	[ PAGE + '?wpcpm_view=enroll#wpcpm-import' ],
	() => loaded( source, PAGE + '#wpcpm-import', [ MAP, printed( { 'wpcpm-import': PAGE + '?wpcpm_view=students' } ) ], false ).replaced
);

scenario(
	'the move is made once, at load: a later change of the fragment to an anchor not on the page moves nowhere',
	[],
	() => {
		const world = load( source, { href: PAGE, maps: [ MAP ], parsing: true } );

		world.parsed();
		world.go( '#wpcpm-report' );

		return world.replaced;
	}
);

if ( PRINTED ) {
	scenario(
		'the map the PHP suite drew for a representative\'s Overview: #wpcpm-import moves to Enroll students, #wpcpm-for-administrators moves nowhere, and the live page\'s #wpcpm-module-people moves to Your institution',
		{ import: [ PAGE + '?wpcpm_view=enroll#wpcpm-import' ], admin: [], module: [ PAGE + '?wpcpm_view=institution#wpcpm-module-people' ] },
		() => ( {
			import: loaded( source, PAGE + '#wpcpm-import', [ PRINTED ], false ).replaced,
			admin: loaded( source, PAGE + '#wpcpm-for-administrators', [ PRINTED ], false ).replaced,
			module: loaded( source, PAGE + '#wpcpm-module-people', [ PRINTED ], false ).replaced,
		} )
	);
} else {
	console.log( 'the map the PHP suite drew was not given: its scenario is not run' );
}

/* Mutation proofs */
scenario(
	'the script without the test that the fragment names no element on the page is told apart: #wpcpm-import with the card there moves away',
	[ PAGE + '?wpcpm_view=enroll#wpcpm-import' ],
	() => loaded( without( source, 'if ( document.getElementById( id ) ) {\n\t\t\treturn false;\n\t\t}' ), PAGE + '#wpcpm-import', [ MAP ], true ).replaced
);

scenario(
	'and without the test of the address\'s view, an address that names a view moves again',
	[ PAGE + '?wpcpm_view=enroll#wpcpm-import' ],
	() => loaded( without( source, 'VIEW_NAMED.test( String( location.search ) ) ||' ), PAGE + '?wpcpm_view=students#wpcpm-import', [ MAP ], false ).replaced
);

scenario(
	'and without the test of the address itself, a map that names the page\'s own address replaces it with itself',
	[ PAGE + '#wpcpm-import' ],
	() => loaded( without( source, 'String( location.href ).split( \'#\' )[ 0 ] === target ||' ), PAGE + '#wpcpm-import', [ printed( { 'wpcpm-import': PAGE } ) ], false ).replaced
);

console.log( ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
