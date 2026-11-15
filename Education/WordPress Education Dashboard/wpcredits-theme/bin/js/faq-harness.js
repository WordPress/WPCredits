'use strict';
/*
 * The FAQ page's script (assets/js/faq.js), run in node on a page of questions (bin/test-faq-js.php).
 *
 *   node bin/js/faq-harness.js <theme root> [script]  < pages.json
 *
 * Each scenario opens a page in the stand-in of bin/js/hub-dom.js at an address's fragment and runs the
 * script as a browser runs a deferred one, after the markup. The page's `window.setTimeout` keeps its
 * callbacks, and the scenario's `later()` runs them, as the browser does once the press is over. The
 * script is the theme's unless a second argument names another file: bin/test-faq-js.php hands it
 * broken copies, to prove the scenarios fail when the script does not do its job. Prints `ok   <scenario>`
 * or `FAIL <scenario>` with what was expected and what happened, then `N scenarios, M differ`, and exits 1
 * when any differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const { page } = require( './hub-dom.js' );

const ROOT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..' ) );
const FILE = process.argv[ 3 ] ? path.resolve( process.argv[ 3 ] ) : path.join( ROOT, 'assets/js/faq.js' );
const PAGES = JSON.parse( fs.readFileSync( 0, 'utf8' ) );
const SCRIPT = fs.existsSync( FILE ) ? fs.readFileSync( FILE, 'utf8' ) : '';

// The invented address the pages are served at (an example host, as the fixtures use).
const ADDRESS = 'https://site.example/faq/';

const scenarios = [];

function scenario( name, want, build ) {
	scenarios.push( { name, want, build } );
}

/**
 * Open a named page at a fragment, and run the script on it.
 *
 * @param {string} name A page of PAGES.
 * @param {string} hash The address's fragment, such as `#slack-email`, or ''.
 * @return {Object} The page, with `later()`, which runs the timeouts the script set.
 */
function open( name, hash ) {
	if ( '' === SCRIPT ) {
		throw new Error( 'there is no assets/js/faq.js' );
	}

	const where = page( PAGES[ name ], hash );
	const queued = [];
	const base = new URL( ADDRESS );

	// The stand-in's address is a bare hash; a browser's has an origin, a path and a query too, and writes
	// a fragment percent-encoded. A link is followed as a browser follows it: resolved against the address,
	// and a fragment that is the current one fires no hashchange.
	base.hash = hash;
	where.window.location = {
		href: base.href,
		origin: base.origin,
		pathname: base.pathname,
		search: base.search,
		hash: base.hash,
	};
	where.window.navigate = ( next ) => {
		const target = new URL( next, base.href );

		if ( target.origin !== base.origin || target.pathname !== base.pathname || target.search !== base.search ) {
			return;
		}

		let id = target.hash.slice( 1 );

		try {
			id = decodeURIComponent( id );
		} catch ( error ) {
			// Left as written: a browser finds nothing for it either.
		}

		const wanted = where.document.getElementById( id );

		if ( wanted ) {
			where.document.scrolledTo.push( wanted.id );
		}

		if ( target.hash !== where.window.location.hash ) {
			where.window.location.hash = target.hash;
			( where.window.listeners.hashchange || [] ).forEach( ( listener ) => listener( { type: 'hashchange' } ) );
		}
	};

	where.window.setTimeout = ( callback ) => {
		queued.push( callback );

		return queued.length;
	};
	where.later = () => queued.splice( 0 ).forEach( ( callback ) => callback() );

	vm.runInContext( SCRIPT, vm.createContext( { document: where.document, window: where.window, URL } ) );

	return where;
}

const opened = ( where ) => where.document.querySelectorAll( 'details' ).filter( ( one ) => one.hasAttribute( 'open' ) ).map( ( one ) => one.id || one.querySelector( 'summary' ).textContent );
const brought = ( where ) => where.document.scrollArgs.length;
const link = ( where, href ) => where.document.querySelectorAll( 'a' ).find( ( one ) => href === one.getAttribute( 'href' ) );

scenario( 'an address naming a question opens it, and only it, and brings it into view once, with no behavior asked, so nothing animates', [ [ 'slack-email' ], [ 'slack-email' ], [ '[]' ] ], () => {
	const where = open( 'faq', '#slack-email' );

	return [ opened( where ), where.document.scrolledTo, where.document.scrollArgs ];
} );

scenario( 'an address with no fragment, or a bare #, opens nothing and scrolls nowhere', [ [ [], 0 ], [ [], 0 ] ], () => [ '', '#' ].map( ( hash ) => {
	const where = open( 'faq', hash );

	return [ opened( where ), brought( where ) ];
} ) );

scenario( 'a link to another question changes the fragment, and the hashchange opens that one too; the one open before stays open', [ [ 'account-email', 'slack-email' ], 1 ], () => {
	const where = open( 'faq', '#slack-email' );

	link( where, '#account-email' ).click();

	return [ opened( where ), where.document.scrollArgs.length - 1 ];
} );

scenario( 'a reader who closed the question and presses the link to it again, which fires no hashchange, sees it open again once the press is over', [ [], [ 'slack-email' ] ], () => {
	const where = open( 'faq', '#slack-email' );

	where.document.getElementById( 'slack-email' ).removeAttribute( 'open' );
	link( where, '#slack-email' ).click();

	const during = opened( where );

	where.later();

	return [ during, opened( where ) ];
} );

scenario( 'a fragment naming something inside a question opens the question around it, and the one around that', [ [ 'outer', 'inner' ], [ 'inside' ] ], () => {
	const where = open( 'nested', '#inside' );

	return [ opened( where ), where.document.scrollArgs.length ? [ 'inside' ] : [] ];
} );

scenario( 'a fragment naming a section\'s heading, or nothing on the page, is left to the browser: nothing opens and the script scrolls nowhere', [ [ [], 0 ], [ [], 0 ] ], () => [ '#registering', '#not-a-question' ].map( ( hash ) => {
	const where = open( 'faq', hash );

	return [ opened( where ), brought( where ) ];
} ) );

scenario( 'an encoded fragment is read as its words, and one that cannot be decoded throws nothing and opens nothing', [ [ 'café' ], [ [], 0 ] ], () => {
	const encoded = open( 'faq', '#caf%C3%A9' );
	const broken = open( 'faq', '#%E0%A4%A' );

	return [ opened( encoded ), [ opened( broken ), brought( broken ) ] ];
} );

scenario( 'a question already open is left open, its open attribute not written again', [ [ 'already' ], 1 ], () => {
	const where = open( 'saved', '' );
	const question = where.document.getElementById( 'already' );
	let writes = 0;
	const set = question.setAttribute.bind( question );

	question.setAttribute = ( name, value ) => {
		writes += 'open' === name ? 1 : 0;
		set( name, value );
	};
	where.window.location.hash = '#already';
	( where.window.listeners.hashchange || [] ).forEach( ( listener ) => listener( { type: 'hashchange' } ) );

	return [ opened( where ), writes + brought( where ) ];
} );

scenario( 'a percent-encoded fragment opens the question with that accented id; closed by the reader, a press on a link written with the raw letters, or with other percent-encoding of them, opens it again', [ [ 'café' ], [], [ 'café', 'café' ] ], () => {
	const where = open( 'links', '#caf%C3%A9' );
	const first = opened( where );

	where.document.getElementById( 'café' ).removeAttribute( 'open' );

	const closed = opened( where );

	link( where, '#café' ).click();
	where.later();

	const again = opened( where );

	where.document.getElementById( 'café' ).removeAttribute( 'open' );
	link( where, '#caf%c3%a9' ).click();
	where.later();

	return [ first, closed, again.concat( opened( where ) ) ];
} );

scenario( 'an absolute link to this same page with the fragment already in the address opens the closed question again', [ [], [ 'account-username' ] ], () => {
	const where = open( 'links', '#account-username' );

	where.document.getElementById( 'account-username' ).removeAttribute( 'open' );
	link( where, 'https://site.example/faq/#account-username' ).click();

	const during = opened( where );

	where.later();

	return [ during, opened( where ) ];
} );

scenario( 'a link with the same fragment on another page, another query or another origin is left alone: nothing opens, nothing is scrolled', [ [], [], [] ], () => [
	'https://site.example/other/#account-username',
	'https://site.example/faq/?page=2#account-username',
	'https://other.example/faq/#account-username',
].map( ( href ) => {
	const where = open( 'links', '#account-username' );

	where.document.getElementById( 'account-username' ).removeAttribute( 'open' );

	const scrolls = brought( where );

	link( where, href ).click();
	where.later();

	return opened( where ).concat( brought( where ) - scrolls ? [ 'scrolled' ] : [] );
} ) );

scenario( 'a fragment that cannot be decoded, a bare #, or one holding a quote throws nothing and opens nothing, as an address and as a link', [ [ [], 0 ], [ [], 0 ], [ [], 0 ] ], () => [ '#%E0%A4%A', '#', '#a"b' ].map( ( hash ) => {
	const where = open( 'links', hash );

	where.document.querySelectorAll( 'a' ).filter( ( one ) => [ '#', '#a"b', '#%E0%A4%A' ].includes( one.getAttribute( 'href' ) ) ).forEach( ( one ) => one.click() );
	where.later();

	return [ opened( where ), brought( where ) ];
} ) );

scenario( 'a fragment naming a paragraph inside an answer opens its question and asks the paragraph itself, not the top of the page, to scroll', [ [ 'midway' ], [ 'answer' ], [ '[]' ] ], () => {
	const where = open( 'answer', '#answer' );

	return [ opened( where ), where.document.scrolledTo, where.document.scrollArgs ];
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
