'use strict';
/*
 * assets/js/mentor-checker.js, run in node: where the focus goes after a Promote, what the
 * status region says, and how often it says it while a run walks the queue.
 *
 *   php bin/test-mentor-checker.php
 *
 * which runs this file the way bin/test-student-modules.php runs bin/js/modules-arrange.js:
 *
 *   node bin/js/mentor-checker-promote.js <path to mentor-checker.js>
 *
 * The script is run on a stand-in for the screen: elements that keep attributes, classes,
 * children and listeners, a table body that reads the few tags a result row is made of, and a
 * fetch the harness answers by hand. The names are invented.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a
 * summary line, and exits 1 when any scenario differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'mentor-checker.js' ) );

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
		this.disabled = false;
		this.hidden = false;
		this.style = {};
		this.children = [];
		this.parentNode = null;
		this.listeners = {};
		this.page = null;
		this._text = '';

		const element = this;

		this.classList = {
			add: ( name ) => {
				if ( element.className.split( ' ' ).indexOf( name ) === -1 ) {
					element.className = ( element.className + ' ' + name ).trim();
				}
			},
			remove: ( name ) => {
				element.className = element.className.split( ' ' ).filter( ( part ) => part !== name ).join( ' ' );
			},
			toggle: ( name, state ) => {
				element.classList[ state ? 'add' : 'remove' ]( name );
			},
		};
	}

	get textContent() {
		return this.children.length ? this.children.map( ( child ) => child.textContent ).join( '' ) : this._text;
	}

	set textContent( value ) {
		this.children.forEach( ( child ) => {
			child.parentNode = null;
		} );
		this.children = [];
		this._text = String( value );

		if ( this.onwrite ) {
			this.onwrite( this._text );
		}
	}

	// Setting '' empties the element; a string of rows is read by the table body only.
	set innerHTML( html ) {
		this.children.forEach( ( child ) => {
			child.parentNode = null;
		} );
		this.children = [];
		this._text = '';

		if ( html ) {
			readRows( this, String( html ) );
		}
	}

	append( child ) {
		child.parentNode = this;
		child.page = this.page;
		this.children.push( child );

		return child;
	}

	appendChild( child ) {
		return this.append( child );
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
		const classes = ( this.className || '' ).split( ' ' );
		let found;

		if ( '.' === selector[ 0 ] ) {
			return classes.indexOf( selector.slice( 1 ) ) !== -1;
		}

		if ( 'td:first-child' === selector ) {
			return 'TD' === this.tagName && this.parentNode && this.parentNode.children[ 0 ] === this;
		}

		if ( ( found = /^tr\[data-record-id(?:="([^"]*)")?\]$/.exec( selector ) ) ) {
			return 'TR' === this.tagName && null !== this.getAttribute( 'data-record-id' ) && ( undefined === found[ 1 ] || this.getAttribute( 'data-record-id' ) === found[ 1 ] );
		}

		if ( ( found = /^\[data-stat="([^"]*)"\]$/.exec( selector ) ) ) {
			return this.getAttribute( 'data-stat' ) === found[ 1 ];
		}

		return this.tagName === selector.toUpperCase();
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

	replaceWith( node ) {
		const parent = this.parentNode;

		node.parentNode = parent;
		node.page = this.page;
		parent.children.splice( parent.children.indexOf( this ), 1, node );
		this.parentNode = null;

		if ( this.page.document.activeElement && this.contains( this.page.document.activeElement ) ) {
			this.page.document.activeElement = this.page.document.body;
		}
	}

	remove() {
		this.parentNode.children.splice( this.parentNode.children.indexOf( this ), 1 );
		this.parentNode = null;
	}

	contains( node ) {
		return node === this || this.all().indexOf( node ) !== -1;
	}

	addEventListener( type, listener ) {
		( this.listeners[ type ] = this.listeners[ type ] || [] ).push( listener );
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
	}

	setAttribute( name, value ) {
		this.attrs[ name ] = String( value );
	}

	focus() {
		this.page.document.activeElement = this;
	}
}

// The result row's markup, as far as the script reads it: the row's attributes, the cells, the
// spans (the pills) and the Promote button.
function readRows( into, html ) {
	const rowPattern = /<tr\b([^>]*)>([\s\S]*?)<\/tr>/g;
	let row;

	while ( ( row = rowPattern.exec( html ) ) ) {
		const tr = into.append( new Element( 'tr', readAttributes( row[ 1 ] ) ) );
		const cellPattern = /<td\b([^>]*)>([\s\S]*?)<\/td>/g;
		let cell;

		while ( ( cell = cellPattern.exec( row[ 2 ] ) ) ) {
			const td = tr.append( new Element( 'td', readAttributes( cell[ 1 ] ) ) );
			const tagPattern = /<(span|button)\b([^>]*)>([\s\S]*?)<\/\1>/g;
			let tag;
			let any = false;

			while ( ( tag = tagPattern.exec( cell[ 2 ] ) ) ) {
				any = true;
				td.append( new Element( tag[ 1 ], readAttributes( tag[ 2 ] ) ) )._text = tag[ 3 ].replace( /<[^>]*>/g, '' ).trim();
			}

			if ( ! any ) {
				td._text = cell[ 2 ].replace( /<[^>]*>/g, '' ).trim();
			}
		}
	}
}

function readAttributes( text ) {
	const attrs = {};
	const pattern = /([a-z-]+)="([^"]*)"/g;
	let found;

	while ( ( found = pattern.exec( text ) ) ) {
		attrs[ found[ 1 ] ] = found[ 2 ];
	}

	return attrs;
}

function rowHtml( id, name, action, note ) {
	return '<tr data-record-id="' + id + '" data-action="' + action + '">'
		+ '<td class="wpcpm-checker-col-name"><strong>' + name + '</strong></td>'
		+ '<td class="wpcpm-checker-col-action">'
		+ ( note ? '<span class="wpcpm-checker-note">' + note + '</span>' : '' )
		+ ( 'eligible' === action ? '<button type="button" class="button button-small wpcpm-checker-promote" data-record-id="' + id + '">Promote</button>' : '' )
		+ '</td></tr>';
}

function resultRow( id, name, action, note ) {
	return { record_id: id, name, action, html: rowHtml( id, name, action, note ) };
}

const I18N = {
	starting: 'Reading the mentors to check.',
	checking: 'Reading profiles.',
	progress: 'Checked %1$d of %2$d mentors.',
	count: '%1$d of %2$d, %3$d%%',
	meta: '%1$d completed, %2$d need attention, %3$s elapsed',
	done: 'Finished. Checked %d mentors.',
	failed: 'The run stopped: %s',
	promoting: 'Promoting.',
	promote: 'Promote',
	doneLabel: 'Finished',
	stoppedLabel: 'Stopped',
	promotedName: 'Promoted %s.',
	promotedOne: 'Promoted %s mentor.',
	promotedMany: 'Promoted %s mentors.',
	confirmApply: 'Continue?',
	confirmOne: 'Continue?',
};

/**
 * The screen the script runs on, with a queue of mentors and the means to press its buttons.
 *
 * @param {string} source  The script.
 * @param {object} answers What the server answers, by action name: a function of the posted body.
 * @param {Array}  rows    Rows already in the table, as [ id, name, action ].
 * @return {object}
 */
function build( source, answers, rows ) {
	const page = { document: null, writes: [], posts: [] };
	const document = new Element( 'html' );
	const body = document.append( new Element( 'body' ) );
	const byId = {};

	page.document = document;
	document.page = page;
	document.body = body;
	document.activeElement = body;

	[ 'wpcpm-checker-run-dry', 'wpcpm-checker-promote-all', 'wpcpm-checker-status', 'wpcpm-checker-progress', 'wpcpm-checker-progress-bar', 'wpcpm-checker-progress-label', 'wpcpm-checker-progress-count', 'wpcpm-checker-progress-meta', 'wpcpm-checker-progress-eta', 'wpcpm-checker-results-body' ].forEach( ( id ) => {
		byId[ id ] = body.append( new Element( 'tbody' === id ? 'tbody' : 'div', { id } ) );
	} );

	byId[ 'wpcpm-checker-results-body' ].tagName = 'TBODY';
	byId[ 'wpcpm-checker-run-dry' ].tagName = 'BUTTON';
	byId[ 'wpcpm-checker-promote-all' ].tagName = 'BUTTON';
	byId[ 'wpcpm-checker-status' ].onwrite = ( text ) => {
		page.writes.push( text );
	};

	const tbody = byId[ 'wpcpm-checker-results-body' ];

	( rows || [] ).forEach( ( row ) => readRows( tbody, rowHtml( row[ 0 ], row[ 1 ], row[ 2 ] ) ) );
	body.all().forEach( ( node ) => {
		node.page = page;
	} );

	const sandbox = {
		window: {
			wpcpmCheckerData: { ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php', nonce: 'n', i18n: I18N },
			confirm: () => true,
			setInterval: () => 1,
			clearInterval: () => {},
		},
		document: {
			getElementById: ( id ) => byId[ id ] || null,
			createElement: ( tag ) => {
				const node = new Element( tag );

				node.page = page;

				return node;
			},
		},
		URLSearchParams,
		Date,
		String,
		Object,
		parseInt,
		Promise,
		Error,
		fetch: ( url, options ) => {
			const posted = new URLSearchParams( options.body );
			const action = posted.get( 'action' );

			page.posts.push( action );

			return Promise.resolve( {
				status: 200,
				json: () => Promise.resolve( { success: true, data: answers[ action ]( posted ) } ),
			} );
		},
	};

	sandbox.window.window = sandbox.window;
	vm.runInNewContext( source, Object.assign( sandbox, { window: sandbox.window } ) );

	const settle = () => new Promise( ( resolve ) => setTimeout( resolve, 20 ) );

	return {
		page,
		tbody,
		status: () => byId[ 'wpcpm-checker-status' ].textContent,
		// The row's Promote is pressed through the table body, where the script listens.
		promote: ( id ) => {
			const button = tbody.querySelector( 'tr[data-record-id="' + id + '"]' ).querySelector( '.wpcpm-checker-promote' );

			tbody.listeners.click[ 0 ]( { target: button } );

			return settle();
		},
		promoteAll: () => {
			byId[ 'wpcpm-checker-promote-all' ].disabled = false;
			byId[ 'wpcpm-checker-promote-all' ].listeners.click[ 0 ]( {} );

			return settle();
		},
		run: () => {
			byId[ 'wpcpm-checker-run-dry' ].listeners.click[ 0 ]( {} );

			return settle().then( settle );
		},
	};
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';

function queueOf( total ) {
	const rows = [];

	for ( let i = 1; i <= total; i++ ) {
		rows.push( resultRow( 'rec' + i, 'Mentor ' + i, 'none' ) );
	}

	return rows;
}

// A run of `total` mentors in batches of `size`, the server answering each batch.
function runAnswers( total, size ) {
	return {
		wpcpm_checker_start_run: () => ( { total, run_id: 'run1', batch_size: size, queue: queueOf( total ), airtable_error: '' } ),
		wpcpm_checker_process_batch: ( posted ) => {
			const offset = parseInt( posted.get( 'offset' ), 10 );
			const next = Math.min( offset + size, total );

			return { rows: queueOf( total ).slice( offset, next ), next_offset: next, total, done: next >= total };
		},
	};
}

const scenarios = [
	scenario( 'after a row\'s Promote the focus is on the new row\'s name cell, and the outcome is said', {
		focus: 'wpcpm-checker-col-name',
		tabindex: '-1',
		cellOfRow: 'rec1',
		said: 'Promoted Ada Example.',
	}, () => {
		const world = build( source, {
			wpcpm_checker_promote: () => ( { row: resultRow( 'rec1', 'Ada Example', 'promoted' ) } ),
		}, [ [ 'rec1', 'Ada Example', 'eligible' ], [ 'rec2', 'Bo Example', 'eligible' ] ] );

		return world.promote( 'rec1' ).then( () => {
			const at = world.page.document.activeElement;

			return {
				focus: at.className,
				tabindex: at.getAttribute( 'tabindex' ),
				cellOfRow: at.parentNode ? at.parentNode.getAttribute( 'data-record-id' ) : null,
				said: world.status(),
			};
		} );
	} ),

	scenario( 'a Promote that comes back not promoted says the row\'s own note, and the focus is on the new row\'s name cell', {
		said: 'Their course does not show yet.',
		focus: 'wpcpm-checker-col-name',
	}, () => {
		const world = build( source, {
			wpcpm_checker_promote: () => ( { row: resultRow( 'rec1', 'Ada Example', 'none', 'Their course does not show yet.' ) } ),
		}, [ [ 'rec1', 'Ada Example', 'eligible' ] ] );

		return world.promote( 'rec1' ).then( () => ( { said: world.status(), focus: world.page.document.activeElement.className } ) );
	} ),

	scenario( 'Promote all says how many were promoted', {
		said: 'Promoted 3 mentors.',
		promotedRowsOnly: 'Promoted 1 mentor.',
		focus: 'wpcpm-checker-col-name',
		focusRow: 'rec1',
		tabindex: '-1',
	}, () => {
		const make = ( actions ) => build( source, {
			wpcpm_checker_promote_all: () => ( { rows: actions.map( ( action, index ) => resultRow( 'rec' + ( index + 1 ), 'Mentor ' + ( index + 1 ), action ) ) } ),
		}, actions.map( ( action, index ) => [ 'rec' + ( index + 1 ), 'Mentor ' + ( index + 1 ), 'eligible' ] ) );
		const three = make( [ 'promoted', 'activated', 'promoted' ] );
		const mixed = make( [ 'promoted', 'failed', 'failed' ] );

		return three.promoteAll().then( () => mixed.promoteAll() ).then( () => {
			const at = three.page.document.activeElement;

			return {
				said: three.status(),
				promotedRowsOnly: mixed.status(),
				focus: at.className,
				focusRow: at.parentNode.getAttribute( 'data-record-id' ),
				tabindex: at.getAttribute( 'tabindex' ),
			};
		} );
	} ),

	scenario( 'progress is said at each quarter and at the end, and not after every batch', [
		'Reading the mentors to check.',
		'Checked 15 of 60 mentors.',
		'Checked 30 of 60 mentors.',
		'Checked 45 of 60 mentors.',
		'Finished. Checked 60 mentors.',
	], () => {
		const world = build( source, runAnswers( 60, 5 ), [] );

		return world.run().then( () => world.page.writes );
	} ),

	scenario( 'a run of fewer than four mentors is said at the end only', [
		'Reading the mentors to check.',
		'Finished. Checked 3 mentors.',
	], () => {
		const world = build( source, runAnswers( 3, 1 ), [] );

		return world.run().then( () => world.page.writes );
	} ),

	scenario( 'a batch that passes two quarters at once is said once', [
		'Reading the mentors to check.',
		'Checked 9 of 12 mentors.',
		'Finished. Checked 12 mentors.',
	], () => {
		const world = build( source, runAnswers( 12, 9 ), [] );

		return world.run().then( () => world.page.writes );
	} ),
];

Promise.all( scenarios ).then( () => {
	console.log( ran + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
} );
