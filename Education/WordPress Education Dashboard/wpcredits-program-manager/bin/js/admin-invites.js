'use strict';
/*
 * assets/js/admin.js, run in node: the invitations card's poller (1.123.58), what a person on an
 * audience screen sees while a bulk invitation run sends, and what a screen reader is told.
 *
 *   php bin/test-admin-js.php
 *
 * which runs this file as bin/test-forms-js.php runs the forms harness:
 *
 *   node bin/js/admin-invites.js <path to admin.js>
 *
 * The script runs on a stand-in for the page: the card's panel as WPCPM_Mail::render_invite_card()
 * prints it, a fetch that answers what each scenario queues, a clock whose timers a scenario runs by
 * hand, a tab that can be hidden and shown, and wp.a11y.speak() writing down what it was asked to say.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a summary
 * line, and exits 1 when any scenario differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'admin.js' ) );

let ran = 0;
let failed = 0;

async function scenario( name, want, build ) {
	let got;

	try {
		got = await build();
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

class Element {
	constructor( attrs ) {
		this.attrs = Object.assign( {}, attrs || {} );
		this.children = [];
		this.textContent = '';
		this.hidden = false;
		this.style = {};
	}

	add( ...kids ) {
		this.children.push( ...kids );

		return this;
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
	}

	setAttribute( name, value ) {
		this.attrs[ name ] = String( value );
	}

	all() {
		const out = [];

		( function walk( node ) {
			node.children.forEach( ( child ) => {
				out.push( child );
				walk( child );
			} );
		}( this ) );

		return out;
	}

	// The selectors the poller asks for: `[attr]` and `form`.
	querySelector( selector ) {
		return this.all().find( ( el ) => ( 'form' === selector ? 'form' === el.attrs.tag : null !== el.getAttribute( selector.slice( 1, -1 ) ) ) ) || null;
	}
}

// The panel as the card prints it while a run sends: 10 of 40 sent.
function page() {
	const panel = new Element( { 'data-wpcpm-invite-poll': '', 'data-action': 'wpcpm_invite_progress', 'data-nonce': 'n1', 'data-poll': '10', 'data-total': '40', 'data-stopped': 'The count stopped updating.', 'data-finished': 'This run has finished: every invitation in it has been sent or passed over.', 'data-cleared': 'This run was stopped, or cleared in another tab. The count above is where it ended.', 'data-stop-label': 'Stop and cancel the remaining %d', 'aria-busy': 'true' } );
	const count = new Element( { 'data-wpcpm-invite-count': '' } );
	const bar = new Element( { tag: 'progress', 'data-wpcpm-invite-bar': '' } );
	const detail = new Element( { 'data-wpcpm-invite-detail': '' } );
	const stop = new Element( { tag: 'form' } );
	const stopButton = new Element( { 'data-wpcpm-invite-stop': '' } );

	count.textContent = 'Sending: 10 of 40 sent.';
	detail.textContent = '30 still to go.';
	stopButton.textContent = 'Stop and cancel the remaining 30';
	stop.add( stopButton );
	bar.value = 25;
	panel.add( count, bar, detail, stop );

	return { panel, count, bar, detail, stop, stopButton };
}

// Figures as WPCPM_Mail::invite_progress() answers them.
function figures( sent, total, running ) {
	return {
		running,
		sent,
		total,
		waiting: running ? total - sent : 0,
		percent: Math.round( ( sent / total ) * 100 ),
		count: 'Sending: ' + sent + ' of ' + total + ' sent.',
		detail: running ? ( total - sent ) + ' still to go.' : '',
		said: 'Invitations sent: ' + sent + ' of ' + total + '.',
	};
}

const ok = ( data ) => ( { json: () => Promise.resolve( { success: true, data } ) } );
const refused = () => ( { json: () => Promise.resolve( -1 ) } );

// Load admin.js on the page, with `answers` queued for fetch: a response, or 'drop' for a request that
// fails. Returns what a scenario drives and reads.
function load( answers, options ) {
	const els = page();
	const timers = [];
	const said = [];
	const asked = [];
	const listeners = {};
	const doc = {
		hidden: !! ( options && options.hidden ),
		querySelectorAll: ( selector ) => ( '[data-wpcpm-invite-poll]' === selector ? [ els.panel ] : [] ),
		querySelector: () => null,
		addEventListener: ( type, fn ) => {
			( listeners[ type ] = listeners[ type ] || [] ).push( fn );
		},
	};
	const win = {
		ajaxurl: '/wp-admin/admin-ajax.php',
		setTimeout: ( fn, ms ) => timers.push( { fn, ms } ),
		setInterval: () => 0,
		fetch: ( url, init ) => {
			asked.push( init.body );
			const answer = answers.shift();

			return 'drop' === answer || undefined === answer ? Promise.reject( new Error( 'offline' ) ) : Promise.resolve( answer );
		},
		location: { reload: () => said.push( 'RELOADED' ) },
		wp: { a11y: { speak: ( text ) => said.push( text ) } },
	};

	win.window = win;
	win.document = doc;
	vm.runInNewContext( fs.readFileSync( SCRIPT, 'utf8' ), { window: win, document: doc, encodeURIComponent, Math, parseInt, String } );
	( listeners.DOMContentLoaded || [] ).forEach( ( fn ) => fn() );

	return {
		els,
		timers,
		said,
		asked,
		doc,
		settle: () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) ),
		next: () => timers.shift().fn(),
		show: () => {
			doc.hidden = false;
			( listeners.visibilitychange || [] ).forEach( ( fn ) => fn() );
		},
	};
}

( async function () {
	await scenario( 'the first answer moves the count, the bar and the line in place, says nothing, and asks again in ten seconds', { count: 'Sending: 12 of 40 sent.', bar: '30', detail: '28 still to go.', said: [], next: [ 10000 ], body: 'action=wpcpm_invite_progress&nonce=n1' }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ) ] );

		await run.settle();

		return { count: run.els.count.textContent, bar: String( run.els.bar.value ), detail: run.els.detail.textContent, said: run.said, next: run.timers.map( ( t ) => t.ms ), body: run.asked[ 0 ] };
	} );

	await scenario( 'a screen reader is told as the run passes each quarter, once each, and not between', { said: [ 'Invitations sent: 20 of 40.', 'Invitations sent: 30 of 40.' ] }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ), ok( figures( 16, 40, true ) ), ok( figures( 20, 40, true ) ), ok( figures( 24, 40, true ) ), ok( figures( 30, 40, true ) ) ] );

		for ( let i = 0; i < 4; i++ ) {
			await run.settle();
			run.next();
		}

		await run.settle();

		return { said: run.said };
	} );

	await scenario( 'at the finish it says what was sent, once, prints that the run has finished, hides the Stop form, is no longer busy, and asks nothing more', { said: [ 'Invitations sent: 40 of 40.' ], count: 'Invitations sent: 40 of 40.', detail: 'This run has finished: every invitation in it has been sent or passed over.', stopHidden: true, busy: 'false', timers: 0, reloaded: false }, async () => {
		const run = load( [ ok( figures( 36, 40, true ) ), ok( figures( 40, 40, false ) ) ] );

		await run.settle();
		run.next();
		await run.settle();

		return { said: run.said, count: run.els.count.textContent, detail: run.els.detail.textContent, stopHidden: run.els.stop.hidden, busy: run.els.panel.getAttribute( 'aria-busy' ), timers: run.timers.length, reloaded: run.said.includes( 'RELOADED' ) };
	} );

	await scenario( 'a run stopped in another tab ends the panel as stopped, with its sentence, and the count stays', { said: [ 'This run was stopped, or cleared in another tab. The count above is where it ended.' ], count: 'Sending: 12 of 40 sent.', bar: '30', detail: 'This run was stopped, or cleared in another tab. The count above is where it ended.', stopHidden: false, busy: 'false', timers: 0, asked: 2 }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ), ok( { running: false, sent: 0, total: 0, waiting: 0, percent: 0, count: 'Sending: 0 of 0 sent.', detail: '', said: 'Invitations sent: 0 of 0.' } ) ] );

		await run.settle();
		run.next();
		await run.settle();

		return { said: run.said, count: run.els.count.textContent, bar: String( run.els.bar.value ), detail: run.els.detail.textContent, stopHidden: run.els.stop.hidden, busy: run.els.panel.getAttribute( 'aria-busy' ), timers: run.timers.length, asked: run.asked.length };
	} );

	await scenario( 'a refused answer, as an expired nonce on a tab left open overnight gives, stops the panel with its sentence and asks nothing more', { detail: 'The count stopped updating.', busy: 'false', timers: 0, asked: 2 }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ), refused() ] );

		await run.settle();
		run.next();
		await run.settle();

		return { detail: run.els.detail.textContent, busy: run.els.panel.getAttribute( 'aria-busy' ), timers: run.timers.length, asked: run.asked.length };
	} );

	await scenario( 'a request that fails is asked again after twice the wait each time, up to a minute, and five in a row stop the panel', { waits: [ 20000, 40000, 60000, 60000 ], detail: 'The count stopped updating.', timers: 0 }, async () => {
		const run = load( [ 'drop', 'drop', 'drop', 'drop', 'drop' ] );
		const waits = [];

		for ( let i = 0; i < 4; i++ ) {
			await run.settle();
			waits.push( run.timers[ 0 ].ms );
			run.next();
		}

		await run.settle();

		return { waits, detail: run.els.detail.textContent, timers: run.timers.length };
	} );

	await scenario( 'one answer after a failure starts the count of failures again', { waits: [ 20000, 10000, 20000 ] }, async () => {
		const run = load( [ 'drop', ok( figures( 14, 40, true ) ), 'drop' ] );
		const waits = [];

		for ( let i = 0; i < 3; i++ ) {
			await run.settle();
			waits.push( run.timers[ 0 ].ms );
			run.next();
		}

		return { waits };
	} );

	await scenario( 'while the tab is hidden nothing is asked; the poll that came due is asked once the tab is shown', { whileHidden: 1, afterShown: 2, count: 'Sending: 20 of 40 sent.' }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ), ok( figures( 20, 40, true ) ) ] );

		await run.settle();
		run.doc.hidden = true;
		run.next();
		await run.settle();

		const whileHidden = run.asked.length;

		run.show();
		await run.settle();

		return { whileHidden, afterShown: run.asked.length, count: run.els.count.textContent };
	} );

	await scenario( 'the Stop button counts what is still to go from each answer, in the words the card printed it with', { first: 'Stop and cancel the remaining 28', second: 'Stop and cancel the remaining 20' }, async () => {
		const run = load( [ ok( figures( 12, 40, true ) ), ok( figures( 20, 40, true ) ) ] );

		await run.settle();

		const first = run.els.stopButton.textContent;

		run.next();
		await run.settle();

		return { first, second: run.els.stopButton.textContent };
	} );

	console.log( '\n' + ran + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
}() );
