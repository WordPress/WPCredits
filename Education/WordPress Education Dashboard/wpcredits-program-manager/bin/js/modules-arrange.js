'use strict';
/*
 * assets/js/modules.js, run in node: what the page does with the answer to an arrow press.
 *
 *   php bin/test-student-modules.php
 *
 * which runs this file the way bin/test-dashboard-switcher.php runs its harness:
 *
 *   node bin/js/modules-arrange.js <path to modules.js>
 *
 * The script is run on a stand-in for the page: a small tree of elements that keep attributes,
 * classes, children and listeners, and a fetch the harness answers by hand. The page is the
 * Sponsor Dashboard's with its first section left out - the saved order holds four modules and the
 * page draws three - because that is the page the script got wrong: the answer to a press carries
 * the whole saved order, and the page's keys never matched it.
 *
 * The harness counts every time a module is put into the page, since a module that is detached
 * and inserted again sends the keyboard back to the top of the page, and models that too: when an
 * element that holds the focus is moved, the focus goes to the document. The optimistic move that
 * a press makes is one insertion; anything after it is the script re-arranging the page.
 *
 * Three scenarios, and a mutation proof: the script with the comparison it used to make, the
 * page's keys against the whole answer, run through the first scenario, must go wrong. A check
 * that passes whatever the script does would pass that too.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a
 * summary line, and exits 1 when any scenario differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'modules.js' ) );
const REFUSED = 'The move was not kept.';

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
	constructor( tag, attrs ) {
		this.tagName = tag.toUpperCase();
		this.attrs = Object.assign( {}, attrs || {} );
		this.id = this.attrs.id || '';
		this.className = this.attrs.class || '';
		this.name = this.attrs.name || '';
		this.value = this.attrs.value || '';
		this.disabled = false;
		this.textContent = '';
		this.children = [];
		this.parentNode = null;
		this.listeners = {};
		this.page = null;
	}

	append( child ) {
		child.parentNode = this;
		this.children.push( child );

		return child;
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

	matches( selector ) {
		return selector.split( ',' ).some( ( part ) => {
			const text = part.trim();

			if ( 'button:not([disabled])' === text ) {
				return 'BUTTON' === this.tagName && ! this.disabled;
			}

			if ( '.' === text[ 0 ] ) {
				return this.className.split( ' ' ).indexOf( text.slice( 1 ) ) !== -1;
			}

			return this.tagName === text.toUpperCase();
		} );
	}

	closest( selector ) {
		let node = this;

		while ( node ) {
			if ( node.matches && node.matches( selector ) ) {
				return node;
			}

			node = node.parentNode;
		}

		return null;
	}

	querySelectorAll( selector ) {
		return this.all().filter( ( node ) => node.matches( selector ) );
	}

	querySelector( selector ) {
		return this.querySelectorAll( selector )[ 0 ] || null;
	}

	contains( node ) {
		return node === this || this.all().indexOf( node ) !== -1;
	}

	// A node that is put somewhere is first taken out of where it was, and an element that holds
	// the focus and is taken out of the page loses it, as the HTML standard has it.
	insertBefore( node, reference ) {
		const page = this.page;

		if ( node.parentNode ) {
			node.parentNode.children.splice( node.parentNode.children.indexOf( node ), 1 );
		}

		if ( node.contains( page.document.activeElement ) ) {
			page.document.activeElement = page.document.body;
		}

		if ( node.matches && node.matches( '.wpcpm-module' ) ) {
			page.inserted++;
		}

		this.children.splice( reference ? this.children.indexOf( reference ) : this.children.length, 0, node );
		node.parentNode = this;
		node.page = page;

		return node;
	}

	removeChild( node ) {
		this.children.splice( this.children.indexOf( node ), 1 );
		node.parentNode = null;
	}

	addEventListener( type, listener ) {
		( this.listeners[ type ] = this.listeners[ type ] || [] ).push( listener );
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
	}

	setAttribute( name, value ) {
		this.attrs[ name ] = value;
	}

	focus() {
		if ( this.disabled ) {
			return;
		}

		this.page.document.activeElement = this;
	}
}

/**
 * The page the script runs on, and the means to press an arrow on it.
 *
 * @param {string}   source The script.
 * @param {string[]} saved  The saved order, every module the page could draw.
 * @param {string[]} drawn  The modules the page draws, in the order it draws them.
 * @return {object}
 */
function build( source, saved, drawn ) {
	const page = { inserted: 0, posted: [], answers: [], document: null };
	const document = new Element( 'html' );
	const body = document.append( new Element( 'body' ) );
	const root = body.append( new Element( 'div', { id: 'page' } ) );
	const pairs = {};
	let ready = null;

	page.document = document;
	document.page = page;
	document.body = body;
	document.activeElement = body;

	[ document, body, root ].forEach( ( node ) => {
		node.page = page;
	} );

	document.createElement = ( tag ) => {
		const node = new Element( tag );

		node.page = page;

		return node;
	};
	document.createComment = () => document.createElement( '#comment' );
	document.contains = ( node ) => document.all().indexOf( node ) !== -1;
	document.addEventListener = ( type, listener ) => {
		if ( 'DOMContentLoaded' === type ) {
			ready = listener;
		}
	};

	drawn.forEach( ( key ) => {
		const module = root.append( new Element( 'div', { id: 'wpcpm-module-' + key, class: 'wpcpm-module wpcpm-module--' + key } ) );
		const form = module.append( new Element( 'form', { class: 'wpcpm-module__mover', action: 'https://example.test/wp-admin/admin-post.php', 'data-wpcpm-refused': REFUSED } ) );

		[
			[ 'action', 'wpcpm_sponsor_module_move' ],
			[ 'wpcpm_module', key ],
			[ 'wpcpm_drawn', drawn.join( ',' ) ],
		].forEach( ( field ) => {
			form.append( new Element( 'input', { type: 'hidden', name: field[ 0 ], value: field[ 1 ] } ) );
		} );

		pairs[ key ] = { form };

		[ 'up', 'down' ].forEach( ( direction ) => {
			pairs[ key ][ direction ] = form.append( new Element( 'button', {
				class: 'wpcpm-module__move wpcpm-module__move--' + direction,
				name: 'wpcpm_direction',
				value: direction,
				'data-wpcpm-moved': key + ' moved ' + direction + '.',
			} ) );
		} );
	} );

	root.all().forEach( ( node ) => {
		node.page = page;
	} );

	// The script as the page sees it: the browser's own fetch and FormData, which here are the
	// harness's. A form's data is its hidden inputs, and a press is answered with what the
	// scenario queued for it.
	const sandbox = {
		window: { fetch: true, FormData: true },
		document,
		FormData: function ( form ) {
			this.entries = form.all().filter( ( node ) => 'INPUT' === node.tagName ).map( ( node ) => [ node.name, node.value ] );
			this.append = ( name, value ) => {
				this.entries.push( [ name, value ] );
			};
		},
		fetch: ( url, options ) => {
			const answer = page.answers.shift() || { ok: false };

			page.posted.push( options.body.entries );

			return Promise.resolve( {
				ok: answer.ok,
				json: () => Promise.resolve( { data: { order: answer.order } } ),
			} );
		},
	};

	vm.runInNewContext( source, sandbox );
	ready();
	page.inserted = 0;

	return {
		page,
		// The modules in the order the page shows them.
		order: () => root.children.filter( ( node ) => node.matches( '.wpcpm-module' ) ).map( ( node ) => node.id.replace( 'wpcpm-module-', '' ) ),
		// Which arrows are disabled, module by module in page order.
		edges: () => root.children.filter( ( node ) => node.matches( '.wpcpm-module' ) ).map( ( node ) => [ pairs[ node.id.replace( 'wpcpm-module-', '' ) ].up.disabled, pairs[ node.id.replace( 'wpcpm-module-', '' ) ].down.disabled ] ),
		live: () => body.all().concat( root.children ).filter( ( node ) => 'P' === node.tagName )[ 0 ].textContent,
		focus: () => {
			const at = document.activeElement;

			return at === body ? 'document' : at.closest( '.wpcpm-module' ).id.replace( 'wpcpm-module-', '' ) + ' ' + at.value;
		},
		// The reader presses an arrow, the server answers it, and the answer is handled.
		press: ( key, direction, answer ) => {
			const button = pairs[ key ][ direction ];

			page.answers.push( answer );
			button.disabled = false;
			pairs[ key ].form.listeners.submit[ 0 ]( { submitter: button, preventDefault() {} } );

			return new Promise( ( resolve ) => setTimeout( resolve, 5 ) );
		},
	};
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';

// The Sponsor Dashboard with the sponsorship section left out: four modules saved, three drawn.
const saved = [ 'offer', 'sponsorship', 'mentors', 'company' ];
const drawn = [ 'offer', 'mentors', 'company' ];

// What the first scenario sees, run against any version of the script.
function kept( script ) {
	const world = build( script, saved, drawn );

	return world.press( 'offer', 'down', { ok: true, order: [ 'mentors', 'sponsorship', 'offer', 'company' ] } ).then( () => ( {
		order: world.order(),
		inserted: world.page.inserted,
		focus: world.focus(),
		live: world.live(),
	} ) );
}

( async () => {
	await scenario(
		'a move the server keeps is left as it is: one insertion, the optimistic move, and focus stays on the arrow',
		{ order: [ 'mentors', 'offer', 'company' ], inserted: 1, focus: 'offer down', live: 'offer moved down.' },
		() => kept( source )
	);

	await scenario(
		'and the press posts the modules the page draws, with the direction and the flag for the background save',
		[ [ 'action', 'wpcpm_sponsor_module_move' ], [ 'wpcpm_module', 'offer' ], [ 'wpcpm_drawn', 'offer,mentors,company' ], [ 'wpcpm_direction', 'down' ], [ 'wpcpm_async', '1' ] ],
		async () => {
			const world = build( source, saved, drawn );

			await world.press( 'offer', 'down', { ok: true, order: [ 'mentors', 'sponsorship', 'offer', 'company' ] } );

			return world.page.posted[ 0 ];
		}
	);

	await scenario(
		'a refusal puts the page back, says so, and leaves focus on the arrow that was pressed',
		{ order: [ 'offer', 'mentors', 'company' ], edges: [ [ true, false ], [ false, false ], [ false, true ] ], live: REFUSED, focus: 'offer down' },
		async () => {
			const world = build( source, saved, drawn );

			await world.press( 'offer', 'down', { ok: false } );

			return { order: world.order(), edges: world.edges(), live: world.live(), focus: world.focus() };
		}
	);

	await scenario(
		'a refusal after a kept move puts the page back to the order the server kept, which is not the order the page started in',
		{ order: [ 'mentors', 'offer', 'company' ], edges: [ [ true, false ], [ false, false ], [ false, true ] ], live: REFUSED },
		async () => {
			const world = build( source, saved, drawn );

			await world.press( 'offer', 'down', { ok: true, order: [ 'mentors', 'sponsorship', 'offer', 'company' ] } );
			await world.press( 'company', 'up', { ok: false } );

			return { order: world.order(), edges: world.edges(), live: world.live() };
		}
	);

	// The script as it was before the answer was compared by the modules the page draws: the page's
	// keys against the whole saved order.
	const compared = 'shown.join( \',\' ) === drawn.join( \',\' )';
	const old = source.replace( compared, 'shown.join( \',\' ) === order.join( \',\' )' );

	await scenario(
		'the script that compares the page with the whole answer is told apart: it puts every module in again',
		true,
		async () => {
			if ( old === source ) {
				return 'the comparison to mutate was not found';
			}

			const result = await kept( old );

			return result.inserted > 1;
		}
	);

	console.log( ran + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
} )();
