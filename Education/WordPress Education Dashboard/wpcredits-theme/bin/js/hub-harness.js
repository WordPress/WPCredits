'use strict';
/*
 * The hub's view scripts, run in node on the server's own markup (bin/test-hub-js.php).
 *
 *   node bin/js/hub-harness.js <theme root>  < markup.json
 *
 * The markup comes on stdin, as bin/test-hub-js.php renders it with the theme's own render.php files:
 * a JSON object of named pages. Each scenario opens a page in the stand-in of bin/js/hub-dom.js, runs
 * the scripts as a browser runs a deferred script, after the markup, and drives the page as a person
 * does: a click, a key pressed on the control that has the focus, a link followed, an address with a
 * hash. Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then
 * `N scenarios, M differ`, and exits 1 when any differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const { page, pressKey } = require( './hub-dom.js' );

const ROOT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..' ) );
const PAGES = JSON.parse( fs.readFileSync( 0, 'utf8' ) );
const SCRIPTS = {
	index: fs.readFileSync( path.join( ROOT, 'blocks/program-index/view.js' ), 'utf8' ),
	pathway: fs.readFileSync( path.join( ROOT, 'blocks/pathway/view.js' ), 'utf8' ),
};

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
 * Open a named page at a hash and run some of the view scripts on it.
 *
 * @param {string}   name    A page of PAGES.
 * @param {string[]} scripts Keys of SCRIPTS, run in order.
 * @param {string}   hash    The address's hash.
 * @param {Function} before  Optional: called with the page before the scripts run, as where a scenario
 *                           lays out the boxes a script reads as it starts.
 * @return {Object} The page.
 */
function open( name, scripts, hash, before ) {
	const where = page( PAGES[ name ], hash || '' );
	const context = vm.createContext( { document: where.document, window: where.window } );

	if ( before ) {
		before( where );
	}

	scripts.forEach( ( key ) => vm.runInContext( SCRIPTS[ key ], context ) );

	return where;
}

/**
 * Focus an element, then click it, as a person does with the mouse or a tap.
 *
 * @param {Object}  where   The page.
 * @param {Element} element The element.
 */
function tap( where, element ) {
	element.focus();
	element.click();
}

/* ---- the program index ---------------------------------------------------------- */

const shown = ( where ) => where.document.querySelectorAll( '.wpc-hub-program' ).filter( ( card ) => ! card.hidden ).map( ( card ) => card.id.replace( 'program-', '' ) );
const pressed = ( where ) => where.document.querySelectorAll( '.wpc-hub-chip' ).map( ( chip ) => chip.getAttribute( 'aria-pressed' ) );
const chip = ( where, filter ) => where.document.querySelector( '.wpc-hub-chip[data-filter="' + filter + '"]' );
const said = ( where, root ) => where.document.querySelector( root + ' [role="status"]' ).textContent;

scenario( 'without the script the chips stay hidden and every card is shown', [ true, 5 ], () => {
	const where = open( 'index', [] );

	return [ where.document.querySelector( '.wpc-hub-chips' ).hidden, shown( where ).length ];
} );

scenario( 'the script shows the chips, All pressed, every card shown and nothing said', [ false, [ 'true', 'false', 'false', 'false' ], 5, '' ], () => {
	const where = open( 'index', [ 'index' ] );

	return [ where.document.querySelector( '.wpc-hub-chips' ).hidden, pressed( where ), shown( where ).length, said( where, '.wpc-hub-index' ) ];
} );

scenario( 'each chip pressed shows its programs, is the one pressed, says what it shows, and keeps the focus where it was', [
	[ 'student', [ 'false', 'true', 'false', 'false' ], [ 'campus-connect', 'wordpress-credits', 'learn-wordpress' ], 'Showing 3 of 5 programs for students', true ],
	[ 'faculty', [ 'false', 'false', 'true', 'false' ], [ 'facilitator-training', 'learn-wordpress' ], 'Showing 2 of 5 programs for faculty', true ],
	[ 'career', [ 'false', 'false', 'false', 'true' ], [ 'wordpress-credits', 'ai-leaders' ], 'Showing 2 of 5 programs in Career and credentials', true ],
	[ 'all', [ 'true', 'false', 'false', 'false' ], [ 'campus-connect', 'wordpress-credits', 'facilitator-training', 'learn-wordpress', 'ai-leaders' ], 'Showing all 5 programs', true ],
], () => {
	const where = open( 'index', [ 'index' ] );

	return [ 'student', 'faculty', 'career', 'all' ].map( ( filter ) => {
		tap( where, chip( where, filter ) );

		return [ filter, pressed( where ), shown( where ), said( where, '.wpc-hub-index' ), where.document.activeElement === chip( where, filter ) ];
	} );
} );

// The band of WordPress Credits' courses is no card: under "For faculty" it stays, as an institution
// picks the course, while the Credits card hides with the students' and careers' programs.
scenario( 'the band of courses stays under every chip, "For faculty" too, while the chips hide and show only the cards', [ [ false, false, false, false ], [ 'facilitator-training', 'learn-wordpress' ] ], () => {
	const where = open( 'index', [ 'index' ] );
	const states = [];

	[ 'student', 'faculty', 'career', 'all' ].forEach( ( filter ) => {
		tap( where, chip( where, filter ) );
		states.push( where.document.getElementById( 'wordpress-credits-courses' ).hidden );
	} );

	tap( where, chip( where, 'faculty' ) );

	return [ states, shown( where ) ];
} );

scenario( 'by keyboard: Enter and Space on the chip that has the focus press it', [ [ 'false', 'false', 'true', 'false' ], [ 'false', 'true', 'false', 'false' ] ], () => {
	const where = open( 'index', [ 'index' ] );
	const states = [];

	chip( where, 'faculty' ).focus();
	pressKey( where, 'Enter' );
	states.push( pressed( where ) );
	chip( where, 'student' ).focus();
	pressKey( where, ' ' );
	states.push( pressed( where ) );

	return states;
} );

scenario( 'a link followed to a card the filter hides presses All first, so the card it opens is shown, and says so', [ true, [ 'true', 'false', 'false', 'false' ], 'Showing all 5 programs', '#program-wordpress-credits', [ 'program-wordpress-credits' ] ], () => {
	const where = open( 'index', [ 'index' ] );

	tap( where, chip( where, 'faculty' ) );
	// No change of address answers (as when the page is at the card's address already), so this is the
	// click's own doing: the jump that follows finds the card shown only if the click showed it.
	where.window.listeners.hashchange = [];
	where.document.querySelector( 'a[href="#program-wordpress-credits"]' ).click();

	return [ ! where.document.getElementById( 'program-wordpress-credits' ).hidden, pressed( where ), said( where, '.wpc-hub-index' ), where.window.location.hash, where.document.scrolledTo ];
} );

scenario( 'a link to a card the filter shows leaves the filter as it is', [ [ 'false', 'true', 'false', 'false' ], 'Showing 3 of 5 programs for students' ], () => {
	const where = open( 'index', [ 'index' ] );

	tap( where, chip( where, 'student' ) );
	where.document.querySelector( 'a[href="#program-wordpress-credits"]' ).click();

	return [ pressed( where ), said( where, '.wpc-hub-index' ) ];
} );

scenario( 'an address changed to a hidden card\'s, by the back button or by hand, presses All too and scrolls to the card, which the browser could not while it was hidden', [ 'true', true, [ 'program-facilitator-training' ] ], () => {
	const where = open( 'index', [ 'index' ] );

	tap( where, chip( where, 'career' ) );
	where.window.navigate( '#program-facilitator-training' );

	return [ chip( where, 'all' ).getAttribute( 'aria-pressed' ), ! where.document.getElementById( 'program-facilitator-training' ).hidden, where.document.scrolledTo ];
} );

scenario( 'a link followed with a modifier key held, or whose default another script prevented, does not open in this page, so the filter, the address and the scroll stay as they were', [ [ 'false', 'false', 'true', 'false' ], 2, '', [] ], () => {
	const where = open( 'index', [ 'index' ] );
	const link = where.document.querySelector( 'a[href="#program-wordpress-credits"]' );

	tap( where, chip( where, 'faculty' ) );
	[ 'ctrlKey', 'metaKey', 'shiftKey', 'altKey' ].forEach( ( key ) => link.click( { [ key ]: true } ) );
	link.addEventListener( 'click', ( event ) => event.preventDefault() );
	link.click();

	return [ pressed( where ), shown( where ).length, where.window.location.hash, where.document.scrolledTo ];
} );

// The address is the card's already, so no change of address follows to press All in the click's
// place: only the click itself can, from the arrow up to the link around it.
scenario( 'a click on the arrow inside a link, not on the link itself, is followed even when the address is the card\'s already: All is pressed, and the card it opens is shown and jumped to', [ [ 'true', 'false', 'false', 'false' ], true, [ 'program-wordpress-credits' ] ], () => {
	const where = open( 'index', [ 'index' ], '#program-wordpress-credits' );

	tap( where, chip( where, 'faculty' ) );
	where.document.querySelector( 'nav a[href="#program-wordpress-credits"] span' ).click();

	return [ pressed( where ), ! where.document.getElementById( 'program-wordpress-credits' ).hidden, where.document.scrolledTo ];
} );

scenario( 'a page opened at a card\'s address, or at one no card has, or at #program- alone, opens with All pressed and every card shown', [ [ 5, 'true' ], [ 5, 'true' ], [ 5, 'true' ] ], () => [ '#program-ai-leaders', '#program-nothing', '#program-' ].map( ( hash ) => {
	const where = open( 'index', [ 'index' ], hash );

	return [ shown( where ).length, chip( where, 'all' ).getAttribute( 'aria-pressed' ) ];
} ) );

scenario( 'a link to an address no card has changes nothing', [ [ 'false', 'false', 'true', 'false' ], 2 ], () => {
	const where = open( 'index', [ 'index' ] );

	tap( where, chip( where, 'faculty' ) );
	where.document.querySelector( 'a[href="#program-nothing"]' ).click();

	return [ pressed( where ), shown( where ).length ];
} );

scenario( 'the script run twice, as when it is printed twice, still answers each press once', [ 1, [ 'false', 'true', 'false', 'false' ] ], () => {
	const where = open( 'index', [ 'index', 'index' ] );

	tap( where, chip( where, 'student' ) );

	return [ where.document.querySelector( '.wpc-hub-index [role="status"]' ).textWrites, pressed( where ) ];
} );

// Theme 1.32.1: core's block visibility hides a card at a size of the window with CSS, so the card is on
// the page and the server counted it; the chips count the cards shown where the person is. The page
// 'hidden' hides Campus Connect (students) on mobile and Learn WordPress (students and faculty) on
// desktop; the stand-in models core's media queries (480px and 782px).
const openAt = ( width, hash ) => open( 'hidden', [ 'index' ], hash, ( where ) => {
	where.window.innerWidth = width;
} );
const saysAt = ( width ) => {
	const where = openAt( width );

	return [ 'all', 'student', 'faculty', 'career' ].map( ( filter ) => {
		tap( where, chip( where, filter ) );

		return said( where, '.wpc-hub-index' );
	} );
};

scenario( 'the chips count the cards shown at the window\'s width, not those core\'s block visibility hides there: four of five on a phone and on a desktop, all five between', [
	[ 'Showing all 4 programs', 'Showing 2 of 4 programs for students', 'Showing 2 of 4 programs for faculty', 'Showing 2 of 4 programs in Career and credentials' ],
	[ 'Showing all 5 programs', 'Showing 3 of 5 programs for students', 'Showing 2 of 5 programs for faculty', 'Showing 2 of 5 programs in Career and credentials' ],
	[ 'Showing all 4 programs', 'Showing 2 of 4 programs for students', 'Showing 1 of 4 programs for faculty', 'Showing 2 of 4 programs in Career and credentials' ],
], () => [ saysAt( 400 ), saysAt( 600 ), saysAt( 1280 ) ] );

scenario( 'the sizes meet where core\'s queries do: a phone to 480px, a tablet above it to 782px, a desktop above that', [ 'Showing 2 of 4 programs for students', 'Showing 3 of 5 programs for students', 'Showing 3 of 5 programs for students', 'Showing 2 of 4 programs for students' ], () => [ 480, 481, 782, 783 ].map( ( width ) => saysAt( width )[ 1 ] ) );

scenario( 'the filter\'s own hiding is left as it was: a card the filter hides and the window hides too stays hidden by the filter after the count, and the cards the window hides are not hidden by the script', [
	[ 'facilitator-training', 'learn-wordpress' ],
	true,
	[ 'campus-connect', 'wordpress-credits', 'ai-leaders' ],
	'Showing 2 of 4 programs for faculty',
], () => {
	const where = openAt( 400 );

	tap( where, chip( where, 'faculty' ) );

	return [
		shown( where ),
		where.document.getElementById( 'program-campus-connect' ).hidden,
		where.document.querySelectorAll( '.wpc-hub-program' ).filter( ( card ) => card.hidden ).map( ( card ) => card.id.replace( 'program-', '' ) ),
		said( where, '.wpc-hub-index' ),
	];
} );

scenario( 'the window resized after a chip is pressed: the cards are counted again and the line says what the chip now shows, once, and not before a chip has been pressed', [ '', 'Showing 1 of 4 programs for faculty', 'Showing 2 of 4 programs for faculty', 'Showing 2 of 5 programs for faculty', 'Showing 2 of 5 programs for faculty', 1, 'Showing 1 of 4 programs for faculty' ], () => {
	const where = openAt( 1280 );
	const line = where.document.querySelector( '.wpc-hub-index [role="status"]' );
	const lines = [];

	where.window.resizeTo( 400 );
	lines.push( said( where, '.wpc-hub-index' ) );
	where.window.resizeTo( 1280 );
	tap( where, chip( where, 'faculty' ) );
	lines.push( said( where, '.wpc-hub-index' ) );
	where.window.resizeTo( 400 );
	lines.push( said( where, '.wpc-hub-index' ) );
	where.window.resizeTo( 600 );
	lines.push( said( where, '.wpc-hub-index' ) );
	where.window.resizeTo( 700 );
	lines.push( said( where, '.wpc-hub-index' ) );

	// 600 and 700 are both tablets: the line is not written again.
	const writes = line.textWrites;

	where.window.resizeTo( 1280 );
	lines.push( line.textWrites - writes, said( where, '.wpc-hub-index' ) );

	return lines;
} );

scenario( 'a chip pressed after a resize counts where the window is now, and the card a link opens is shown whatever the width', [ 'Showing 2 of 4 programs for students', true ], () => {
	const where = openAt( 1280 );

	where.window.resizeTo( 400 );
	tap( where, chip( where, 'student' ) );

	return [ said( where, '.wpc-hub-index' ), ! where.document.getElementById( 'program-wordpress-credits' ).hidden ];
} );

scenario( 'a page with no card hidden at any size counts as the server did and reads no style: the chips say the server\'s sentences at any width', [ [ 'Showing all 5 programs', 'Showing 3 of 5 programs for students' ], 0 ], () => {
	const where = open( 'index', [ 'index' ], '', ( one ) => {
		one.window.innerWidth = 400;
		one.window.reads = 0;
		const read = one.window.getComputedStyle;

		one.window.getComputedStyle = ( element ) => {
			one.window.reads++;

			return read( element );
		};
	} );

	tap( where, chip( where, 'all' ) );
	const all = said( where, '.wpc-hub-index' );

	tap( where, chip( where, 'student' ) );
	where.window.resizeTo( 1280 );

	return [ [ all, said( where, '.wpc-hub-index' ) ], where.window.reads ];
} );

scenario( 'the script run twice, as when it is printed twice, counts and answers once', [ 1, 'Showing 2 of 4 programs for faculty' ], () => {
	const where = open( 'hidden', [ 'index', 'index' ], '', ( one ) => {
		one.window.innerWidth = 400;
	} );

	tap( where, chip( where, 'faculty' ) );

	return [ where.document.querySelector( '.wpc-hub-index [role="status"]' ).textWrites, said( where, '.wpc-hub-index' ) ];
} );

/* ---- the Field of study rows block's editor (theme 1.32.1) -------------------------- */

/*
 * The list's editor script is run on a stand-in of the `wp.*` globals: an element is a plain object that
 * keeps its props, a component a named function never called, and `wp.i18n.__` answers from a scenario's
 * translations. The block's `edit` is called with the attributes a scenario saved, and the checks read
 * the props it hands the header line's three RichText elements and the settings' two TextControls.
 */
function component( name ) {
	const made = function () {};

	Object.defineProperty( made, 'name', { value: name } );

	return made;
}

function fitEditor( translations ) {
	const registered = {};
	const wp = {
		blocks: {
			registerBlockType: ( name, settings ) => {
				registered[ name ] = settings;
			},
		},
		element: {
			createElement: ( type, props, ...children ) => ( { type, props: props || {}, children: children.flat( Infinity ).filter( ( child ) => null !== child && undefined !== child && false !== child ) } ),
			Fragment: component( 'Fragment' ),
		},
		blockEditor: {
			RichText: component( 'RichText' ),
			InspectorControls: component( 'InspectorControls' ),
			InnerBlocks: { Content: component( 'InnerBlocksContent' ) },
			useBlockProps: ( props ) => Object.assign( {}, props ),
			useInnerBlocksProps: ( props ) => Object.assign( {}, props ),
		},
		components: {
			PanelBody: component( 'PanelBody' ),
			TextControl: component( 'TextControl' ),
		},
		i18n: {
			__: ( text ) => translations[ text ] || text,
		},
	};

	vm.runInContext( fs.readFileSync( path.join( ROOT, 'blocks/fit-list/index.js' ), 'utf8' ), vm.createContext( { window: { wp } } ) );

	return registered[ 'wpcredits/fit-list' ];
}

function elementsOf( node, name ) {
	if ( ! node || 'object' !== typeof node ) {
		return [];
	}

	return ( 'function' === typeof node.type && name === node.type.name ? [ node ] : [] ).concat( ...node.children.map( ( child ) => elementsOf( child, name ) ) );
}

/** What the editor shows for a list that saved these attributes: the three names and the two labels. */
function fitShown( translations, attributes ) {
	const tree = fitEditor( translations ).edit( { attributes, setAttributes: () => {} } );

	return {
		head: elementsOf( tree, 'RichText' ).map( ( one ) => one.props.value ),
		labels: elementsOf( tree, 'TextControl' ).map( ( one ) => one.props.value ),
	};
}

scenario( 'in the editor a list that saved none of its five words shows the translated strings, the three names and the two labels, as the server draws them', [ true, true ], () => PAGES.fit && [ 'today', 'translated' ].map( ( which ) => {
	const drawn = PAGES.fit[ which ];
	const shown = fitShown( drawn.translations, {} );

	return JSON.stringify( [ shown.head, shown.labels ] ) === JSON.stringify( [ drawn.head, drawn.labels ] );
} ) );

scenario( 'in the editor a word saved shows as saved, the others stay the translations, and a word saved empty shows empty', [
	{ head: [ '', 'Squad', 'What you\'ll have to show' ], labels: [ 'The team you join:', 'Shown:' ] },
	{ head: [ '', '', '' ], labels: [ '', '' ] },
], () => [
	fitShown( {}, { fieldHeading: '', teamsHeading: 'Squad', proofLabel: 'Shown:' } ),
	fitShown( {}, { fieldHeading: '', teamsHeading: '', proofHeading: '', teamsLabel: '', proofLabel: '' } ),
] );

scenario( 'in the editor a translated name is shown as rich text keeps a text, its ampersand and angle brackets escaped, and a label as the plain text it is', [ [ 'Fields &amp; &lt;more&gt;', 'The team you join', 'What you\'ll have to show' ], [ 'Teams & <co>:', 'What you\'ll have to show:' ] ], () => {
	const shown = fitShown( { 'Your field of study': 'Fields & <more>', 'The team you join:': 'Teams & <co>:' }, {} );

	return [ shown.head, shown.labels ];
} );

/* ---- the pathway --------------------------------------------------------------- */

const ladderShown = ( where ) => where.document.querySelectorAll( '.wpc-hub-ladder' ).filter( ( ladder ) => ! ladder.hidden ).map( ( ladder ) => ladder.id.replace( 'wpc-hub-ladder-', '' ) );
const detailsShown = ( where ) => where.document.querySelectorAll( '.wpc-hub-detail' ).filter( ( detail ) => ! detail.hidden ).map( ( detail ) => detail.id.replace( 'wpc-hub-step-', '' ) );
const stepsPressed = ( where ) => where.document.querySelectorAll( '.wpc-hub-stair__pick[aria-pressed="true"]' ).map( ( step ) => step.getAttribute( 'aria-controls' ).replace( 'wpc-hub-step-', '' ) );
const step = ( where, ladder, n ) => where.document.querySelector( '.wpc-hub-stair__pick[aria-controls="wpc-hub-step-' + ladder + '-' + n + '"]' );
const option = ( where, ladder ) => where.document.querySelector( '.wpc-hub-switch__option[aria-controls="wpc-hub-ladder-' + ladder + '"]' );
const switchPressed = ( where ) => where.document.querySelectorAll( '.wpc-hub-switch__option' ).map( ( one ) => one.getAttribute( 'aria-pressed' ) );

scenario( 'without the script both ladders show whole: the switch and both hints hidden, every detail shown, each step a link to its detail', [ true, [ true, true ], [ 'students', 'institutions' ], 11, 11 ], () => {
	const where = open( 'pathway', [] );

	return [ where.document.querySelector( '.wpc-hub-switch' ).hidden, where.document.querySelectorAll( '.wpc-hub-ladder__hint' ).map( ( hint ) => hint.hidden ), ladderShown( where ), detailsShown( where ).length, where.document.querySelectorAll( 'a.wpc-hub-stair__pick' ).length ];
} );

scenario( 'the script shows the switch and both ladders\' hints, marks the block interactive, and opens on the students\' ladder at step 3, the institutions\' keeping level 2', [ false, [ false, false ], true, [ 'students' ], [ 'students-3', 'institutions-2' ], [ 'students-3', 'institutions-2' ], [ 'true', 'false' ], '' ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	return [
		where.document.querySelector( '.wpc-hub-switch' ).hidden,
		where.document.querySelectorAll( '.wpc-hub-ladder__hint' ).map( ( hint ) => hint.hidden ),
		where.document.querySelector( '.wpc-hub-pathway' ).classList.contains( 'is-interactive' ),
		ladderShown( where ),
		stepsPressed( where ),
		detailsShown( where ),
		switchPressed( where ),
		said( where, '.wpc-hub-pathway' ),
	];
} );

scenario( 'each step\'s link becomes a button of the same content, described by the same line, controlling its detail', [ 0, 11, [ 'BUTTON', 'button', 'Step 1 Attendee', 'wpc-hub-by-students-1', 'wpc-hub-step-students-1', 'Step 1 of 7: Attendee' ] ], () => {
	const where = open( 'pathway', [ 'pathway' ] );
	const first = step( where, 'students', 1 );

	return [
		where.document.querySelectorAll( 'a.wpc-hub-stair__pick' ).length,
		where.document.querySelectorAll( 'button.wpc-hub-stair__pick' ).length,
		[ first.tagName, first.type, first.textContent.replace( /\s+/g, ' ' ).trim(), first.getAttribute( 'aria-describedby' ), first.getAttribute( 'aria-controls' ), first.getAttribute( 'data-announce' ) ],
	];
} );

scenario( 'a step pressed shows its detail alone, is the one pressed, says so, and keeps the focus', [ [ 'students-5', 'institutions-2' ], [ 'students-5', 'institutions-2' ], 'Step 5 of 7: Specialist', true ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	tap( where, step( where, 'students', 5 ) );

	return [ stepsPressed( where ), detailsShown( where ), said( where, '.wpc-hub-pathway' ), where.document.activeElement === step( where, 'students', 5 ) ];
} );

// The detail shown points at its step (hub.css draws the point from 900px): the script sets the middle
// of the pressed step, from the details' left edge, as --wpc-notch, at each press, each ladder shown and
// each resize, and sets nothing while the details have no width, as a hidden ladder's have none.
scenario( 'the detail points at its step: the middle of the step pressed, from the details\' left edge, set at a press, at a resize and when the switch shows a ladder, and nothing while the details have no width', [ '', '470px', '270px', '600px', '' ], () => {
	const where = open( 'pathway', [ 'pathway' ] );
	const details = ( ladder ) => where.document.querySelector( '#wpc-hub-ladder-' + ladder + ' .wpc-hub-details' );
	const stair = ( ladder, n ) => step( where, ladder, n ).closest( '.wpc-hub-stair' );
	const notch = ( ladder ) => details( ladder ).style.getPropertyValue( '--wpc-notch' );
	const seen = [ notch( 'students' ) ];

	details( 'students' ).box = { top: 500, bottom: 700, left: 100, width: 1000 };
	stair( 'students', 5 ).box = { top: 200, bottom: 480, left: 500, width: 140 };
	tap( where, step( where, 'students', 5 ) );
	seen.push( notch( 'students' ) );

	stair( 'students', 5 ).box = { top: 200, bottom: 480, left: 300, width: 140 };
	( where.window.listeners.resize || [] ).forEach( ( listener ) => listener( {} ) );
	seen.push( notch( 'students' ) );

	details( 'institutions' ).box = { top: 500, bottom: 700, left: 100, width: 1000 };
	stair( 'institutions', 2 ).box = { top: 200, bottom: 480, left: 550, width: 300 };
	option( where, 'institutions' ).click();
	seen.push( notch( 'institutions' ) );

	details( 'institutions' ).box = { top: 0, bottom: 0, left: 0, width: 0 };
	details( 'institutions' ).style.props = {};
	tap( where, step( where, 'institutions', 3 ) );
	seen.push( notch( 'institutions' ) );

	return seen;
} );

scenario( 'a page opened at a detail\'s address shows its ladder, and the detail points at its step at once', [ [ 'institutions' ], [ 'institutions-3' ], '440px' ], () => {
	const where = open( 'pathway', [ 'pathway' ], '#wpc-hub-step-institutions-3', ( page ) => {
		page.document.querySelector( '#wpc-hub-ladder-institutions .wpc-hub-details' ).box = { top: 500, bottom: 700, left: 100, width: 1000 };
		page.document.querySelector( 'a.wpc-hub-stair__pick[href="#wpc-hub-step-institutions-3"]' ).closest( '.wpc-hub-stair' ).box = { top: 200, bottom: 480, left: 470, width: 140 };
	} );

	return [ ladderShown( where ), detailsShown( where ).filter( ( id ) => 0 === id.indexOf( 'institutions' ) ), where.document.querySelector( '#wpc-hub-ladder-institutions .wpc-hub-details' ).style.getPropertyValue( '--wpc-notch' ) ];
} );

scenario( 'by keyboard: Enter and Space on the step that has the focus press it', [ [ 'students-1', 'institutions-2' ], [ 'students-7', 'institutions-2' ] ], () => {
	const where = open( 'pathway', [ 'pathway' ] );
	const states = [];

	step( where, 'students', 1 ).focus();
	pressKey( where, 'Enter' );
	states.push( stepsPressed( where ) );
	step( where, 'students', 7 ).focus();
	pressKey( where, ' ' );
	states.push( stepsPressed( where ) );

	return states;
} );

// A phone: the staircase is a column of rows and a detail lies below it, out of the window. A press
// then brings the detail into view, at once and with no options (so a reader who asked for less motion
// gets none), and the focus stays on the button. Where the detail is in sight already, as beside the
// staircase on a wide screen, nothing moves. The window is 800 tall; a detail with no box lies in view.
const place = ( where, ladder, n, top, bottom ) => {
	where.document.getElementById( 'wpc-hub-step-' + ladder + '-' + n ).box = { top, bottom };
};

scenario( 'a step pressed whose detail lies below the window brings the detail into view, once and at once, in either ladder, says so once, and keeps the focus on the button', [
	[ [ 'wpc-hub-step-students-5' ], [ '[]' ], 1, true, [ 'students-5', 'institutions-2' ] ],
	[ [ 'wpc-hub-step-institutions-4' ], [ '[]' ], 1, true, [ 'students-3', 'institutions-4' ] ],
], () => [ [ 'students', 5 ], [ 'institutions', 4 ] ].map( ( [ ladder, n ] ) => {
	const where = open( 'pathway', [ 'pathway' ] );
	const status = where.document.querySelector( '.wpc-hub-pathway [role="status"]' );

	tap( where, option( where, ladder ) );
	status.textWrites = 0;
	place( where, ladder, n, 900, 1300 );
	tap( where, step( where, ladder, n ) );

	return [ where.document.scrolledTo, where.document.scrollArgs, status.textWrites, where.document.activeElement === step( where, ladder, n ), stepsPressed( where ) ];
} ) );

scenario( 'a step pressed whose detail is in sight, whole or in part, moves nothing, and is still shown, said once, with the focus kept', [
	[ [], 1, true, [ 'students-5', 'institutions-2' ] ],
	[ [], 1, true, [ 'students-5', 'institutions-2' ] ],
], () => [ null, { top: 700, bottom: 1100 } ].map( ( box ) => {
	const where = open( 'pathway', [ 'pathway' ] );
	const status = where.document.querySelector( '.wpc-hub-pathway [role="status"]' );

	if ( box ) {
		place( where, 'students', 5, box.top, box.bottom );
	}

	tap( where, step( where, 'students', 5 ) );

	return [ where.document.scrolledTo, status.textWrites, where.document.activeElement === step( where, 'students', 5 ), detailsShown( where ) ];
} ) );

scenario( 'the detail is out of sight when its top is at or below the window\'s bottom, or its bottom at or above the window\'s top, and not a pixel sooner', [ [], [ 'wpc-hub-step-students-5' ], [], [ 'wpc-hub-step-students-5' ] ], () => [
	{ top: 799, bottom: 1199 },
	{ top: 800, bottom: 1200 },
	{ top: -399, bottom: 1 },
	{ top: -400, bottom: 0 },
].map( ( box ) => {
	const where = open( 'pathway', [ 'pathway' ] );

	place( where, 'students', 5, box.top, box.bottom );
	tap( where, step( where, 'students', 5 ) );

	return where.document.scrolledTo;
} ) );

scenario( 'by keyboard: Enter on a step whose detail lies below the window brings it into view too, and the focus stays on the step', [ [ 'wpc-hub-step-students-7' ], [ '[]' ], true ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	place( where, 'students', 7, 1500, 1900 );
	step( where, 'students', 7 ).focus();
	pressKey( where, 'Enter' );

	return [ where.document.scrolledTo, where.document.scrollArgs, where.document.activeElement === step( where, 'students', 7 ) ];
} );

scenario( 'the switch shows its ladder and hides the other, which keeps its pressed step, and says the ladder\'s heading', [
	[ [ 'institutions' ], [ 'false', 'true' ], 'Four partner levels, measured on student outcomes', [ 'students-5', 'institutions-2' ] ],
	[ [ 'students' ], [ 'true', 'false' ], 'Seven steps from first workshop to first job', [ 'students-5', 'institutions-2' ] ],
], () => {
	const where = open( 'pathway', [ 'pathway' ] );
	const states = [];

	tap( where, step( where, 'students', 5 ) );
	tap( where, option( where, 'institutions' ) );
	states.push( [ ladderShown( where ), switchPressed( where ), said( where, '.wpc-hub-pathway' ), stepsPressed( where ) ] );
	tap( where, option( where, 'students' ) );
	states.push( [ ladderShown( where ), switchPressed( where ), said( where, '.wpc-hub-pathway' ), stepsPressed( where ) ] );

	return states;
} );

scenario( 'a page opened at a level\'s address shows that ladder and that level, lands on its detail, and says nothing', [ [ 'institutions' ], [ 'false', 'true' ], [ 'students-3', 'institutions-2' ], [ 'wpc-hub-step-institutions-2' ], '' ], () => {
	const where = open( 'pathway', [ 'pathway' ], '#wpc-hub-step-institutions-2' );

	return [ ladderShown( where ), switchPressed( where ), stepsPressed( where ), where.document.scrolledTo, said( where, '.wpc-hub-pathway' ) ];
} );

scenario( 'a link followed to a step\'s address, with the other ladder shown, shows its ladder and its step before the jump, and says so', [ [ 'students' ], [ 'students-6', 'institutions-4' ], 'Step 6 of 7: Community Leader', [ 'wpc-hub-step-students-6' ] ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	tap( where, option( where, 'institutions' ) );
	tap( where, step( where, 'institutions', 4 ) );
	// No change of address answers (as when the page is at the step's address already), so this is the
	// click's own doing: the jump that follows finds the detail shown only if the click showed it.
	where.window.listeners.hashchange = [];
	where.document.querySelector( 'a[href="#wpc-hub-step-students-6"]' ).click();

	return [ ladderShown( where ), stepsPressed( where ), said( where, '.wpc-hub-pathway' ), where.document.scrolledTo ];
} );

// No change of address answers (none is listening, as when the page is at the step's address already),
// so only the click itself can follow the link, from the arrow up to the link around it.
scenario( 'a click on the arrow inside a link, not on the link itself, is followed: the ladder and the step the link names are shown, and the page jumps to the detail', [ [ 'students' ], [ 'students-6', 'institutions-4' ], 'Step 6 of 7: Community Leader', [ 'wpc-hub-step-students-6' ] ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	tap( where, option( where, 'institutions' ) );
	tap( where, step( where, 'institutions', 4 ) );
	where.window.listeners.hashchange = [];
	where.document.querySelector( 'nav a[href="#wpc-hub-step-students-6"] span' ).click();

	return [ ladderShown( where ), stepsPressed( where ), said( where, '.wpc-hub-pathway' ), where.document.scrolledTo ];
} );

// The browser's own jump cannot reach a detail that is hidden, itself or because its ladder is: the
// institutions' level 2 is shown in its own right while its ladder is not. One that is shown, or one a
// link followed opens, it jumps to once, and the script does not jump again. Nor does it speak twice: a
// link followed is answered by the click, and the change of address it makes finds the step pressed
// already; an address typed, or gone back to, is said once, and one that changes nothing is not said.
// The status line is counted from the moment of the address or the click.
scenario( 'an address changed to a detail that is hidden, itself or with its ladder, shows its ladder and its step, scrolls to the detail and says so once; a detail that was shown, or one a link followed opens, is the browser\'s to jump to, once, and a step already pressed is not said again', [
	[ [ 'students' ], [ 'students-5', 'institutions-2' ], [ 'wpc-hub-step-students-5' ], 'Step 5 of 7: Specialist', 1 ],
	[ [ 'institutions' ], [ 'students-3', 'institutions-4' ], [ 'wpc-hub-step-institutions-4' ], 'Level 4 of 4: Flagship', 1 ],
	[ [ 'institutions' ], [ 'students-3', 'institutions-2' ], [ 'wpc-hub-step-institutions-2' ], 'Level 2 of 4: Partner', 1 ],
	[ [ 'students' ], [ 'students-3', 'institutions-2' ], [ 'wpc-hub-step-students-3' ], '', 0 ],
	[ [ 'students' ], [ 'students-6', 'institutions-4' ], [ 'wpc-hub-step-students-6' ], 'Step 6 of 7: Community Leader', 1 ],
], () => [
	[ 'address', '#wpc-hub-step-students-5' ],
	[ 'address', '#wpc-hub-step-institutions-4' ],
	[ 'address', '#wpc-hub-step-institutions-2' ],
	[ 'address', '#wpc-hub-step-students-3' ],
	[ 'link', '#wpc-hub-step-students-6' ],
].map( ( [ how, hash ] ) => {
	const where = open( 'pathway', [ 'pathway' ] );
	const status = where.document.querySelector( '.wpc-hub-pathway [role="status"]' );

	if ( 'link' === how ) {
		tap( where, option( where, 'institutions' ) );
		tap( where, step( where, 'institutions', 4 ) );
		status.textWrites = 0;
		where.document.querySelector( 'a[href="' + hash + '"]' ).click();
	} else {
		where.window.navigate( hash );
	}

	return [ ladderShown( where ), stepsPressed( where ), where.document.scrolledTo, said( where, '.wpc-hub-pathway' ), status.textWrites ];
} ) );

scenario( 'a link followed with a modifier key held, or whose default another script prevented, does not open in this page, so the ladder, the step, the address and the scroll stay as they were', [ [ 'institutions' ], [ 'students-3', 'institutions-4' ], 'Level 4 of 4: Flagship', '', [] ], () => {
	const where = open( 'pathway', [ 'pathway' ] );
	const link = where.document.querySelector( 'a[href="#wpc-hub-step-students-6"]' );

	tap( where, option( where, 'institutions' ) );
	tap( where, step( where, 'institutions', 4 ) );
	[ 'ctrlKey', 'metaKey', 'shiftKey', 'altKey' ].forEach( ( key ) => link.click( { [ key ]: true } ) );
	link.addEventListener( 'click', ( event ) => event.preventDefault() );
	link.click();

	return [ ladderShown( where ), stepsPressed( where ), said( where, '.wpc-hub-pathway' ), where.window.location.hash, where.document.scrolledTo ];
} );

scenario( 'an address that names no step, or no step that exists, opens on the students\' ladder at step 3', [ [ [ 'students' ], [ 'students-3', 'institutions-2' ] ], [ [ 'students' ], [ 'students-3', 'institutions-2' ] ], [ [ 'students' ], [ 'students-3', 'institutions-2' ] ] ], () => [ '#wpc-hub-step-students-99', '#wpc-hub-step-', '#wpc-hub-ladder-institutions' ].map( ( hash ) => {
	const where = open( 'pathway', [ 'pathway' ], hash );

	return [ ladderShown( where ), stepsPressed( where ) ];
} ) );

scenario( 'the script run twice still answers each press once', [ 1, [ 'students-2', 'institutions-2' ] ], () => {
	const where = open( 'pathway', [ 'pathway', 'pathway' ] );

	tap( where, step( where, 'students', 2 ) );

	return [ where.document.querySelector( '.wpc-hub-pathway [role="status"]' ).textWrites, stepsPressed( where ) ];
} );

scenario( 'the pathway with no program index on the page: a step\'s link to the index changes nothing on the pathway, and nothing throws', [ [ 'students-3', 'institutions-2' ], '#program-wordpress-credits' ], () => {
	const where = open( 'pathway', [ 'pathway' ] );

	where.document.querySelector( '.wpc-hub-detail a[href="#program-wordpress-credits"]' ).click();

	return [ stepsPressed( where ), where.window.location.hash ];
} );

/* ---- the page: the template's five patterns and both blocks, with the planned content - */

scenario( 'on the page, the hero\'s two buttons and the students\' door jump to their sections', [ 'programs', 'pathway', 'programs' ], () => {
	const where = open( 'page', [ 'index', 'pathway' ] );

	where.document.querySelector( '.wpc-hub-hero a[href="#programs"]' ).click();
	where.document.querySelector( '.wpc-hub-hero a[href="#pathway"]' ).click();
	where.document.querySelector( '.wpc-hub-door__go a[href="#programs"]' ).click();

	return where.document.scrolledTo;
} );

scenario( 'on the page, a step\'s link to a program the filter hides presses All and lands on the card', [ [ 'true', 'false', 'false', 'false' ], true, [ 'program-wordpress-credits' ] ], () => {
	const where = open( 'page', [ 'index', 'pathway' ] );

	tap( where, chip( where, 'faculty' ) );
	// No change of address answers (as when the page is at the card's address already), so this is the
	// click's own doing.
	where.window.listeners.hashchange = [];
	where.document.querySelector( '#wpc-hub-step-students-3 a[href="#program-wordpress-credits"]' ).click();

	return [ pressed( where ), ! where.document.getElementById( 'program-wordpress-credits' ).hidden, where.document.scrolledTo ];
} );

scenario( 'and each block\'s presses are its own: a step pressed leaves the filter, a chip pressed leaves the step', [ [ 'false', 'false', 'true', 'false' ], [ 'students-6', 'institutions-2' ], 'Showing 2 of 6 programs for faculty', 'Step 6 of 7: Community Leader' ], () => {
	const where = open( 'page', [ 'index', 'pathway' ] );

	tap( where, step( where, 'students', 6 ) );
	tap( where, chip( where, 'faculty' ) );

	return [ pressed( where ), stepsPressed( where ), said( where, '.wpc-hub-index' ), said( where, '.wpc-hub-pathway' ) ];
} );

/* ---- the page as offered today: no pathway (a program lead, 21 October 2026) ------------- */

scenario( 'on the page as offered today, the hero\'s one button and the students\' door jump to the programs, nothing links to a pathway, and the index\'s chips count the five programs', [ [ 'programs', 'programs' ], 0, 0, 'Showing 3 of 5 programs for students' ], () => {
	const where = open( 'today', [ 'index', 'pathway' ] );

	where.document.querySelector( '.wpc-hub-hero a[href="#programs"]' ).click();
	where.document.querySelector( '.wpc-hub-door__go a[href="#programs"]' ).click();

	const scrolled = where.document.scrolledTo;

	tap( where, chip( where, 'student' ) );

	return [ scrolled, where.document.querySelectorAll( 'a[href="#pathway"]' ).length, where.document.querySelectorAll( '.wpc-hub-pathway' ).length, said( where, '.wpc-hub-index' ) ];
} );

console.log( ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
