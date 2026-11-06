'use strict';
/*
 * assets/js/track-editor.js, run in node: what the page does with the answer to a question move.
 *
 *   php bin/test-track-builder.php
 *
 * which runs this file the way bin/test-student-modules.php runs bin/js/modules-arrange.js:
 *
 *   node bin/js/track-editor-move.js <path to track-editor.js>
 *
 * The script is run on a stand-in for the page: a small tree of elements that keep attributes,
 * classes, children and listeners, and a fetch the harness answers by hand. The page is a track's
 * with its property form's hidden revision field and three questions of one group.
 *
 * Five scenarios: a kept move writes the answer's revision into the property form (so the next
 * Save of the page is not stale); a move whose request fails on the network puts the kept order back
 * and says the move could not be sent; a refused move still says it was not saved; an older press that fails while a newer one is in flight has its notice taken away by the newer
 * answer; and a page with no property form takes a kept move without a word.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a
 * summary line, and exits 1 when any scenario differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'track-editor.js' ) );
const UNSENT = 'The move could not be sent. The questions are back in the order last saved.';
const REFUSED = 'The move was not saved. The question is back where it was.';

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
	}

	get firstChild() {
		return this.children[ 0 ] || null;
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

	insertBefore( node, reference ) {
		if ( node.parentNode ) {
			node.parentNode.children.splice( node.parentNode.children.indexOf( node ), 1 );
		}

		this.children.splice( reference ? this.children.indexOf( reference ) : this.children.length, 0, node );
		node.parentNode = this;

		return node;
	}

	removeChild( node ) {
		this.children.splice( this.children.indexOf( node ), 1 );
		node.parentNode = null;
	}

	appendChild( node ) {
		return this.insertBefore( node, null );
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

	focus() {}
}

/**
 * The page the script runs on, and the means to press an arrow on it.
 *
 * @param {string}  source   The script.
 * @param {boolean} withForm Whether the property form's hidden revision field is on the page.
 * @return {object}
 */
function build( source, withForm ) {
	const page = { posted: [], answers: [] };
	const document = new Element( 'html' );
	const body = document.append( new Element( 'body' ) );
	const pairs = {};
	const columns = [ 'Slack name', 'Your blog', 'Your team' ];
	let ready = null;

	document.activeElement = body;
	document.createElement = ( tag ) => new Element( tag );
	document.createComment = () => new Element( '#comment' );
	document.contains = ( node ) => document.all().indexOf( node ) !== -1;
	document.getElementById = ( id ) => document.all().filter( ( node ) => node.id === id )[ 0 ] || null;
	document.querySelectorAll = ( selector ) => document.all().filter( ( node ) => node.matches( selector ) );
	document.querySelector = ( selector ) => document.querySelectorAll( selector )[ 0 ] || null;
	document.addEventListener = ( type, listener ) => {
		if ( 'DOMContentLoaded' === type ) {
			ready = listener;
		}
	};

	if ( withForm ) {
		body.append( new Element( 'input', { type: 'hidden', id: 'wpcpm-track-revision', name: 'wpcpm_track_revision', value: '2026-11-01 10:00:00' } ) );
	}

	const list = body.append( new Element( 'div', { class: 'wpcpm-questions' } ) );

	columns.forEach( ( column ) => {
		const row = list.append( new Element( 'div', { class: 'wpcpm-question', 'data-wpcpm-group': 'project', 'data-wpcpm-column': column } ) );
		const form = row.append( new Element( 'form', { class: 'wpcpm-question__mover', action: 'https://example.test/wp-admin/admin-post.php', 'data-wpcpm-refused': REFUSED, 'data-wpcpm-unsent': UNSENT } ) );

		form.append( new Element( 'input', { type: 'hidden', name: 'wpcpm_question', value: column } ) );
		pairs[ column ] = { form };

		[ 'up', 'down' ].forEach( ( direction ) => {
			pairs[ column ][ direction ] = form.append( new Element( 'button', {
				class: 'wpcpm-question__move wpcpm-question__move--' + direction,
				name: 'wpcpm_direction',
				value: direction,
				'data-wpcpm-moved': column + ' moved ' + direction + '.',
			} ) );
		} );
	} );

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

			if ( answer.reject ) {
				return Promise.reject( new TypeError( 'Failed to fetch' ) );
			}

			return Promise.resolve( {
				ok: answer.ok,
				redirected: false,
				json: () => Promise.resolve( answer.ok ? { success: true, data: { order: answer.order, revision: answer.revision } } : { success: false, data: { message: '' } } ),
			} );
		},
	};

	vm.runInNewContext( source, sandbox );
	ready();

	return {
		page,
		order: () => list.children.map( ( row ) => row.getAttribute( 'data-wpcpm-column' ) ),
		revision: () => ( document.getElementById( 'wpcpm-track-revision' ) || { value: null } ).value,
		// The notice above the list, and what the live region says.
		notice: () => {
			const found = body.children.filter( ( node ) => node.className.indexOf( 'wpcpm-questions__refused' ) !== -1 )[ 0 ];

			return found ? found.firstChild.textContent : null;
		},
		live: () => body.children.filter( ( node ) => 'P' === node.tagName )[ 0 ].textContent,
		// The reader presses an arrow, the request is answered, and the answer is handled.
		press: ( column, direction, answer ) => {
			const button = pairs[ column ][ direction ];

			page.answers.push( answer );
			button.disabled = false;
			pairs[ column ].form.listeners.submit[ 0 ]( { submitter: button, preventDefault() {} } );

			return new Promise( ( resolve ) => setTimeout( resolve, 5 ) );
		},
	};
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';

( async () => {
	await scenario(
		'a move\'s answer writes the new revision into the property form',
		{ order: [ 'Your blog', 'Slack name', 'Your team' ], revision: '2026-11-06 09:30:00', notice: null },
		async () => {
			const world = build( source, true );

			await world.press( 'Slack name', 'down', { ok: true, order: [ 'Your blog', 'Slack name', 'Your team' ], revision: '2026-11-06 09:30:00' } );

			return { order: world.order(), revision: world.revision(), notice: world.notice() };
		}
	);

	await scenario(
		'a question move whose request fails puts the kept order back and says the move could not be sent',
		{ order: [ 'Your blog', 'Slack name', 'Your team' ], notice: UNSENT, live: UNSENT, revision: '2026-11-06 09:30:00' },
		async () => {
			const world = build( source, true );

			await world.press( 'Slack name', 'down', { ok: true, order: [ 'Your blog', 'Slack name', 'Your team' ], revision: '2026-11-06 09:30:00' } );
			await world.press( 'Your team', 'up', { reject: true } );

			return { order: world.order(), notice: world.notice(), live: world.live(), revision: world.revision() };
		}
	);

	await scenario(
		'and a move that was refused still says it was not saved, and leaves the revision as it was',
		{ order: [ 'Slack name', 'Your blog', 'Your team' ], notice: REFUSED, revision: '2026-11-01 10:00:00' },
		async () => {
			const world = build( source, true );

			await world.press( 'Slack name', 'down', { ok: false } );

			return { order: world.order(), notice: world.notice(), revision: world.revision() };
		}
	);

	await scenario(
		'an older press that fails while a newer one is in flight: the newer answer takes the unsent notice away and arranges the page',
		{ order: [ 'Slack name', 'Your team', 'Your blog' ], notice: null, revision: '2026-11-06 09:45:00' },
		async () => {
			const world = build( source, true );

			// Both requests are on their way before either is answered: the first is lost, the
			// second is kept by the server in the order below.
			const first = world.press( 'Slack name', 'down', { reject: true } );
			const second = world.press( 'Your team', 'up', { ok: true, order: [ 'Slack name', 'Your team', 'Your blog' ], revision: '2026-11-06 09:45:00' } );

			await Promise.all( [ first, second ] );

			return { order: world.order(), notice: world.notice(), revision: world.revision() };
		}
	);

	await scenario(
		'a page with no property form takes a kept move without a word',
		{ order: [ 'Your blog', 'Slack name', 'Your team' ], revision: null, notice: null },
		async () => {
			const world = build( source, false );

			await world.press( 'Slack name', 'down', { ok: true, order: [ 'Your blog', 'Slack name', 'Your team' ], revision: '2026-11-06 09:30:00' } );

			return { order: world.order(), revision: world.revision(), notice: world.notice() };
		}
	);

	console.log( ran + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
} )();
