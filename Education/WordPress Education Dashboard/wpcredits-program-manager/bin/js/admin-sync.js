'use strict';
/*
 * assets/js/admin.js, run in node: the sync panels' poller (1.123.58), what a screen reader is told
 * while a sync runs on a Sync tab, and what the panel says it is doing.
 *
 *   php bin/test-admin-js.php
 *
 * which runs this file beside bin/js/admin-invites.js:
 *
 *   node bin/js/admin-sync.js <path to admin.js>
 *
 * The script runs on a stand-in for the page: a sync panel as the Mentors, Students and Institutions
 * screens print it, a fetch that answers what each scenario queues, a clock whose timers a scenario
 * runs by hand, and wp.a11y.speak() writing down what it was asked to say, beside the page's reload.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a summary
 * line, and exits 1 when any scenario differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'admin.js' ) );

const STALLED = 'No progress for over two minutes. The run may have been interrupted - cancel it and start again.';
const FINISHED = 'The sync has finished. The page will show its report.';
const STOPPED = 'Airtable returned no mentors. That is under half of the 140 accounts linked to a mentor\'s record, so the mentors sync stopped before taking anyone\'s Mentor role, and every account was kept.';

const HINT = 'Reload this page to choose Run anyway or Cancel.';

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

	// The selectors the poller asks for: `[attr]`.
	querySelector( selector ) {
		return this.all().find( ( el ) => null !== el.getAttribute( selector.slice( 1, -1 ) ) ) || null;
	}
}

// The panel as a Sync tab prints it while a run goes, at its second step.
function page() {
	const panel = new Element( { 'data-wpcpm-progress': '', 'data-action': 'wpcpm_mentors_tick', 'data-nonce': 'n1', 'data-poll': '3', 'data-finished': FINISHED } );
	const label = new Element( { 'data-wpcpm-label': '' } );
	const step = new Element( { 'data-wpcpm-step': '' } );
	const bar = new Element( { 'data-wpcpm-bar': '', 'aria-valuenow': '10' } );
	const fill = new Element( { 'data-wpcpm-fill': '' } );
	const percent = new Element( { 'data-wpcpm-percent': '' } );
	const detail = new Element( { 'data-wpcpm-detail': '' } );
	const elapsed = new Element( { 'data-wpcpm-elapsed': '', 'data-label': 'running for %s' } );
	const stalled = new Element( { 'data-wpcpm-stalled': '' } );
	const stop = new Element( { 'data-wpcpm-stopped': '', 'data-hint': HINT } );
	const stopSentence = new Element( { 'data-wpcpm-stopped-sentence': '' } );
	const stopHint = new Element( { 'data-wpcpm-stopped-hint': '' } );

	stalled.textContent = STALLED;
	stalled.hidden = true;
	stop.hidden = true;
	stop.add( stopSentence, stopHint );
	bar.add( fill );
	panel.add( label, step, bar, percent, detail, elapsed, stalled, stop );

	return { panel, label, step, stalled, stop, stopSentence, stopHint };
}

const LABELS = { 2: 'Reading field descriptions…', 3: 'Reading mentors…' };

// Progress as a sync's progress() answers it.
function progress( step, more ) {
	return Object.assign(
		{
			running: true,
			phase: 'p' + step,
			label: LABELS[ step ],
			detail: '',
			percent: step * 10,
			step,
			step_total: 7,
			step_label: 'Step ' + step + ' of 7',
			elapsed: 30,
			stalled: false,
			stopped: false,
			guard: null,
		},
		more || {}
	);
}

const done = () => progress( 7, { running: false, step_label: '', label: '', percent: 100 } );
const stopped = () => progress( 3, { running: false, step_label: '', stopped: true, guard: { read: 0, linked: 140, sentence: STOPPED } } );
const ok = ( data ) => ( { json: () => Promise.resolve( { success: true, data } ) } );

// Load admin.js on the page, with `answers` queued for fetch. Returns what a scenario drives and reads.
function load( answers ) {
	const els = page();
	const timers = [];
	const said = [];
	const busy = [];
	const listeners = {};
	const doc = {
		hidden: false,
		querySelectorAll: ( selector ) => ( '[data-wpcpm-progress]' === selector ? [ els.panel ] : [] ),
		querySelector: () => null,
		addEventListener: ( type, fn ) => {
			( listeners[ type ] = listeners[ type ] || [] ).push( fn );
		},
	};
	const set = els.panel.setAttribute.bind( els.panel );

	els.panel.setAttribute = ( name, value ) => {
		if ( 'aria-busy' === name ) {
			busy.push( String( value ) );
		}

		set( name, value );
	};

	const win = {
		ajaxurl: '/wp-admin/admin-ajax.php',
		setTimeout: ( fn, ms ) => timers.push( { fn, ms } ),
		setInterval: () => 0,
		fetch: () => {
			const answer = answers.shift();

			return undefined === answer ? Promise.reject( new Error( 'offline' ) ) : Promise.resolve( answer );
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
		busy,
		settle: () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) ),
		next: () => timers.shift().fn(),
	};
}

// Answer every queued poll in turn.
async function drive( run, polls ) {
	await run.settle();

	for ( let i = 1; i < polls; i++ ) {
		run.next();
		await run.settle();
	}
}

( async function () {
	await scenario( 'the panel is busy while a run goes and not after', { busy: [ 'true', 'false' ] }, async () => {
		const run = load( [ ok( progress( 2 ) ), ok( done() ) ] );

		await drive( run, 2 );

		return { busy: run.busy };
	} );

	await scenario( 'each step is said once', { said: [ 'Step 2 of 7: Reading field descriptions…', 'Step 3 of 7: Reading mentors…' ] }, async () => {
		const run = load( [ ok( progress( 2 ) ), ok( progress( 2 ) ), ok( progress( 2 ) ), ok( progress( 3 ) ) ] );

		await drive( run, 4 );

		return { said: run.said };
	} );

	await scenario( 'the stall line is said once, when it first shows', { said: [ 'Step 3 of 7: Reading mentors…', STALLED ], shown: true }, async () => {
		const run = load( [ ok( progress( 3 ) ), ok( progress( 3, { stalled: true } ) ), ok( progress( 3, { stalled: true } ) ) ] );

		await drive( run, 3 );

		return { said: run.said, shown: ! run.els.stalled.hidden };
	} );

	await scenario( 'the finish is said before the reload, which waits a moment for it to be heard', { beforeReload: [ 'Step 3 of 7: Reading mentors…', FINISHED ], wait: [ 1500 ], said: [ 'Step 3 of 7: Reading mentors…', FINISHED, 'RELOADED' ], timers: 0 }, async () => {
		const run = load( [ ok( progress( 3 ) ), ok( done() ) ] );

		await drive( run, 2 );

		const beforeReload = run.said.slice();
		const wait = run.timers.map( ( t ) => t.ms );

		run.next();

		return { beforeReload, wait, said: run.said, timers: run.timers.length };
	} );

	await scenario( 'a stopped run stops the poll and says its sentence once, with no reload', { said: [ STOPPED ], timers: 0, busy: 'false' }, async () => {
		const run = load( [ ok( stopped() ) ] );

		await drive( run, 1 );

		return { said: run.said, timers: run.timers.length, busy: run.els.panel.getAttribute( 'aria-busy' ) };
	} );

	await scenario( 'a stopped run prints its sentence in the panel and the way to Run anyway', { shown: true, sentence: STOPPED, hint: HINT, said: [ STOPPED ], timers: 0 }, async () => {
		const run = load( [ ok( stopped() ) ] );

		await drive( run, 1 );

		return { shown: ! run.els.stop.hidden, sentence: run.els.stopSentence.textContent, hint: run.els.stopHint.textContent, said: run.said, timers: run.timers.length };
	} );

	await scenario( 'a run that finishes prints no stop', { shown: false }, async () => {
		const run = load( [ ok( progress( 3 ) ), ok( done() ) ] );

		await drive( run, 2 );

		return { shown: ! run.els.stop.hidden };
	} );

	console.log( '\n' + ran + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
}() );
