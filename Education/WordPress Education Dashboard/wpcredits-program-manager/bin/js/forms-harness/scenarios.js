'use strict';
/*
 * Scenarios for assets/js/forms.js: what a person pressing a control sees, run through the real
 * script on the DOM stand-in in run.js.
 *
 *   node bin/js/forms-harness/scenarios.js [path to forms.js]
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, and
 * exits 1 when any scenario differs. bin/test-forms-js.php runs it as a suite.
 *
 * "No submitter" stands for a browser that does not set `event.submitter` (Safari before 15.4),
 * or a submit that carries none; the script then asks the control it tracked, the last submit
 * control pressed in the form.
 */
const path = require( 'path' );
const { mk, load, flush, pageshow, dispatch, reset, registry, doc } = require( './run.js' );

const FORMS = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', '..', 'assets', 'js', 'forms.js' ) );

let ran = 0;
let failed = 0;

function scenario( name, want, build ) {
	let got;

	reset();

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

// A mouse press on a control, and a key press while it has focus.
const press = ( control ) => dispatch( control, 'mousedown' );
const key = ( control, name ) => dispatch( control, 'keydown', { key: name } );

// The form is submitted; `submitter` is what the browser says was pressed, when it says.
const submit = ( form, submitter ) => dispatch( form, 'submit', submitter ? { submitter } : {} );

// A control with a visible label, as a button reads on the page.
function control( tag, attrs, label ) {
	const el = mk( tag, attrs );

	el.innerHTML = label || '';
	el.textContent = label || '';

	return el;
}

/* ---- An application decision: the form is marked and locked, one button. ---- */

function decision( answer, withSubmitter ) {
	const form = mk( 'form', { 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Working', 'data-wpcpm-confirm': 'Approve?' } );
	const button = mk( 'button', { type: 'submit' } );

	form.add( button );

	const { log, state } = load( FORMS );

	state.answer = answer;

	const event = submit( form, withSubmitter ? button : null );

	return { asked: log.confirms.length, prevented: event.defaultPrevented, locked: '1' === form.getAttribute( 'data-wpcpm-sent' ), busy: form.getAttribute( 'aria-busy' ) };
}

scenario( 'Application decision, form marked, OK: asks once, posts, locks', { asked: 1, prevented: false, locked: true, busy: 'true' }, () => decision( true, true ) );
scenario( 'Application decision, form marked, Cancel: asks once, stops the post, no lock', { asked: 1, prevented: true, locked: false, busy: null }, () => decision( false, true ) );
scenario( 'Application decision, form marked, Cancel, no submitter: the same', { asked: 1, prevented: true, locked: false, busy: null }, () => decision( false, false ) );

/* ---- A note's Delete: the button is marked, the form holds hidden inputs only. ---- */

function noteDelete( withSubmitter, pressed, withTextField ) {
	const form = mk( 'form', {} );
	const button = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'Delete this note?' } );
	let text = null;

	form.add( button );

	if ( withTextField ) {
		text = mk( 'input', { type: 'text' } );
		form.add( text );
	}

	const { log } = load( FORMS );

	if ( 'button' === pressed ) {
		press( button );
	}

	if ( 'text' === pressed ) {
		press( text );
	}

	submit( form, withSubmitter ? button : null );

	return { asked: log.confirms.length };
}

scenario( 'Note delete, button marked, submitter present', { asked: 1 }, () => noteDelete( true ) );
scenario( 'Note delete, button marked, no submitter, nothing tracked: the marked control is asked', { asked: 1 }, () => noteDelete( false ) );
scenario( 'Note delete, button marked, no submitter, button tracked', { asked: 1 }, () => noteDelete( false, 'button' ) );
scenario( 'Note delete, button marked and a text field, no submitter, text field clicked then Enter', { asked: 1 }, () => noteDelete( false, 'text', true ) );

/* ---- The offer state form: several buttons, only End is marked, the form is not. ---- */

function offerState( which, withSubmitter, tracked ) {
	const form = mk( 'form', { 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Switching' } );
	const pause = mk( 'button', { type: 'submit', name: 'wpcpm_state', value: 'paused' } );
	const end = mk( 'button', { type: 'submit', name: 'wpcpm_state', value: 'ended', 'data-wpcpm-confirm': 'End this offer for good?' } );
	const pressed = 'pause' === which ? pause : end;

	form.add( pause, end );

	const { log } = load( FORMS );

	if ( tracked ) {
		press( pressed );
	}

	const event = submit( form, withSubmitter ? pressed : null );

	return { asked: log.confirms.slice(), prevented: event.defaultPrevented };
}

scenario( 'Offer state form, Pause with submitter: no question', { asked: [], prevented: false }, () => offerState( 'pause', true ) );
scenario( 'Offer state form, End with submitter: the End question', { asked: [ 'End this offer for good?' ], prevented: false }, () => offerState( 'end', true ) );
scenario( 'Offer state form, Pause, no submitter, tracked: no question', { asked: [], prevented: false }, () => offerState( 'pause', false, true ) );
scenario(
	'Offer state form, Pause, no submitter, nothing tracked: the form\'s one marked control is asked rather than none (fails closed)',
	{ asked: [ 'End this offer for good?' ], prevented: false },
	() => offerState( 'pause', false, false )
);

/* ---- The void form: a marked form of hidden inputs; its button sits in another form. ---- */

function voidForm( answer, withSubmitter ) {
	const add = mk( 'form', { 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Adding' } );
	const addButton = control( 'button', { type: 'submit' }, 'Add codes' );
	const voidButton = control( 'button', { type: 'submit', form: 'wpcpm-offer-void-7' }, 'Void' );
	const target = mk( 'form', { id: 'wpcpm-offer-void-7', 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Voiding', 'data-wpcpm-confirm': 'Void every code?' } );

	add.add( addButton, voidButton );

	const { log, state } = load( FORMS );

	state.answer = answer;
	press( voidButton );

	const event = submit( target, withSubmitter ? voidButton : null );

	flush( log );

	return {
		asked: log.confirms.slice(),
		prevented: event.defaultPrevented,
		voidFormLocked: '1' === target.getAttribute( 'data-wpcpm-sent' ),
		voidButtonDisabled: voidButton.disabled,
		voidButtonReads: voidButton.textContent,
	};
}

scenario(
	'Void form, external button, Cancel, submitter present: asked, stopped, nothing locked',
	{ asked: [ 'Void every code?' ], prevented: true, voidFormLocked: false, voidButtonDisabled: false, voidButtonReads: 'Void' },
	() => voidForm( false, true )
);
scenario(
	'Void form, external button, Cancel, no submitter: the same',
	{ asked: [ 'Void every code?' ], prevented: true, voidFormLocked: false, voidButtonDisabled: false, voidButtonReads: 'Void' },
	() => voidForm( false, false )
);
scenario(
	'Void form, external button, OK, submitter present: asked, posts, the button shows it is working',
	{ asked: [ 'Void every code?' ], prevented: false, voidFormLocked: true, voidButtonDisabled: true, voidButtonReads: 'Voiding' },
	() => voidForm( true, true )
);
scenario(
	'Void form, external button, OK, no submitter: the button pressed outside the form is the one shown working',
	{ asked: [ 'Void every code?' ], prevented: false, voidFormLocked: true, voidButtonDisabled: true, voidButtonReads: 'Voiding' },
	() => voidForm( true, false )
);

/* ---- A marked button inside one form that posts another: the form it sits in is not asked its question. ---- */

// The button names its form in a `form` attribute, so a press on it posts `other`, and `holder` only
// contains it in the markup. A press on `holder`'s own plain button, in a browser with no
// `event.submitter` and with nothing tracked, must not be asked the question of a button that posts
// somewhere else; `other` is asked it when the browser says that button was the one pressed.
scenario(
	'a marked button that posts another form: the form around it asks nothing for its own plain button, with no submitter and nothing tracked, and the other form asks when the button is the submitter',
	{ holder: [], posted: [ 'Remove?' ] },
	() => {
		const holder = mk( 'form', {} );
		const other = mk( 'form', { id: 'other-7' } );
		const elsewhere = control( 'button', { type: 'submit', form: 'other-7', 'data-wpcpm-confirm': 'Remove?' }, 'Remove' );
		const plain = control( 'button', { type: 'submit' }, 'Save' );

		holder.add( elsewhere, plain );

		const { log } = load( FORMS );
		const asked = {};

		submit( holder, null );
		asked.holder = log.confirms.splice( 0 );

		submit( other, elsewhere );
		asked.posted = log.confirms.splice( 0 );

		return asked;
	}
);

/* ---- The form and its button both marked; two marked buttons. ---- */

scenario( 'Form and button both marked: the button\'s question, once', [ 'BUTTON?' ], () => {
	const form = mk( 'form', { 'data-wpcpm-confirm': 'FORM?' } );
	const button = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'BUTTON?' } );

	form.add( button );

	const { log } = load( FORMS );

	submit( form, button );

	return log.confirms;
} );

scenario( 'Two marked buttons: the submitter\'s own question', [ 'C?' ], () => {
	const form = mk( 'form', {} );
	const first = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'A?' } );
	const second = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'C?' } );

	form.add( first, second );

	const { log } = load( FORMS );

	submit( form, second );

	return log.confirms;
} );

/* ---- A text field clicked in a form whose button is marked, a browser with no submitter. ---- */

scenario( 'Text field clicked last, no submitter, button marked: the question is asked', 1, () => {
	const form = mk( 'form', {} );
	const text = mk( 'input', { type: 'text' } );
	const button = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'Remove?' } );

	form.add( text, button );

	const { log } = load( FORMS );

	press( text );
	submit( form, null );

	return log.confirms.length;
} );

/* ---- A form that arrives after the script ran is asked too. ---- */

scenario( 'a form inserted after the script ran: the form\'s question is asked, and a Cancel stops the post', { asked: [ 'Remove?' ], prevented: true }, () => {
	const { log, state } = load( FORMS );
	const form = mk( 'form', { 'data-wpcpm-confirm': 'Remove?' } );
	const button = mk( 'button', { type: 'submit' } );

	form.add( button );
	state.answer = false;

	const event = submit( form, button );

	return { asked: log.confirms.slice(), prevented: event.defaultPrevented };
} );

scenario( 'a form inserted after the script ran, marked on its button: the press is tracked there too', { asked: [ 'Second?' ], prevented: false }, () => {
	const { log } = load( FORMS );
	const form = mk( 'form', {} );
	const first = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'First?' } );
	const second = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'Second?' } );

	form.add( first, second );
	press( second );

	const event = submit( form, null );

	return { asked: log.confirms.slice(), prevented: event.defaultPrevented };
} );

/* ---- A Student Report Card that arrives the way assets/js/dashboard.js inserts a fetched report. ---- */

// The Remove question as the card prints it: on the form every Remove posts through, which holds
// nothing but hidden fields. The Remove button sits inside the report form and names that form in a
// `form` attribute, because a form inside a form is markup a browser drops.
const REMOVE_ASKS = 'Remove this screenshot? It goes from this site and from the program records. You can upload another one afterward.';

// dashboard.js sets `body.outerHTML = data.html`: the placeholder and everything in it go, and the
// markup that takes its place is new nodes no listener was ever added to. The stand-in drops the
// placeholder from the page and hands `build` its parent to adopt the new nodes into.
function replaceOuter( placeholder, build ) {
	const parent = placeholder.parentNode;

	[ placeholder ].concat( placeholder.descendants() ).forEach( ( node ) => registry.splice( registry.indexOf( node ), 1 ) );
	parent.children.splice( parent.children.indexOf( placeholder ), 1 );

	return build( parent );
}

// The card as the page prints it: the report form holding the Remove and Save buttons, and the
// sibling form every Remove posts through.
function cardMarkup() {
	const report = mk( 'form', { class: 'wpcpm-report', 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Saving' } );
	const remove = control( 'button', { type: 'submit', class: 'wpcpm-report__image-remove', form: 'wpcpm-report-remove-7', name: 'field', value: 'a-screenshot' }, 'Remove' );
	const save = control( 'button', { type: 'submit', class: 'wpcpm-button' }, 'Save my report' );
	const sibling = mk( 'form', { class: 'wpcpm-report__remove', id: 'wpcpm-report-remove-7', 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Removing', 'data-wpcpm-confirm': REMOVE_ASKS } );

	report.add( remove, save );

	return { report, remove, save, sibling };
}

// The page a mentor or an institution opens, the script already run, and the card fetched in.
function fetchedCard() {
	const details = mk( 'details', { 'data-wpcpm-report': 'recSTU1' } );
	const body = mk( 'div', { 'data-wpcpm-report-body': '' } );

	details.add( body );

	const { log, state } = load( FORMS );

	return replaceOuter( body, ( parent ) => {
		const card = cardMarkup();

		parent.add( card.report, card.sibling );

		return Object.assign( { log, state }, card );
	} );
}

scenario(
	'a Student Report Card inserted with outerHTML, Remove with the browser\'s submitter: the question is asked once, a Cancel stops the post and an OK lets it go',
	{ cancel: { asked: [ REMOVE_ASKS ], prevented: true }, ok: { asked: [ REMOVE_ASKS ], prevented: false } },
	() => {
		const { log, state, remove, sibling } = fetchedCard();

		state.answer = false;

		const cancel = { prevented: submit( sibling, remove ).defaultPrevented, asked: log.confirms.splice( 0 ) };

		state.answer = true;

		const ok = { prevented: submit( sibling, remove ).defaultPrevented, asked: log.confirms.splice( 0 ) };

		return { cancel: { asked: cancel.asked, prevented: cancel.prevented }, ok: { asked: ok.asked, prevented: ok.prevented } };
	}
);

scenario(
	'a Student Report Card inserted with outerHTML, no submitter, Remove pressed: the form every Remove posts through asks its question',
	[ REMOVE_ASKS ],
	() => {
		const { log, remove, sibling } = fetchedCard();

		press( remove );
		submit( sibling, null );

		return log.confirms;
	}
);

// The page prints the card, so the script finds both forms when it runs and guards them. With no
// `event.submitter`, the Remove is the control the guard knows to show working only if the press was
// filed under the form its `form` attribute names, the sibling form that posts; filed under the report
// form around it, the question is still asked (the sibling's own mark is the fallback) but the Remove
// never reads Removing and is never disabled.
scenario(
	'a Student Report Card on the page, no submitter, Remove pressed and answered Yes: asked once, and the Remove shows it is working and is disabled',
	{ asked: [ REMOVE_ASKS ], prevented: false, removeReads: 'Removing', removeDisabled: true, reportLocked: false },
	() => {
		const { remove, report, sibling } = cardMarkup();
		const { log } = load( FORMS );

		press( remove );

		const event = submit( sibling, null );

		flush( log );

		return {
			asked: log.confirms.slice(),
			prevented: event.defaultPrevented,
			removeReads: remove.textContent,
			removeDisabled: remove.disabled,
			reportLocked: '1' === report.getAttribute( 'data-wpcpm-sent' ),
		};
	}
);

scenario(
	'a Student Report Card inserted with outerHTML: Save asks nothing, with the submitter, with a press tracked, and with nothing to say what was pressed',
	{ submitter: [], tracked: [], unidentified: [] },
	() => {
		const { log, report, save } = fetchedCard();
		const asked = {};

		submit( report, save );
		asked.submitter = log.confirms.splice( 0 );

		// The Remove button is a descendant of the report form, so a question marked on the button
		// would be the one a Save was asked when nothing identified the press. The card marks the
		// form the button posts, which the report form does not contain.
		press( save );
		submit( report, null );
		asked.tracked = log.confirms.splice( 0 );

		reset();

		const again = fetchedCard();

		submit( again.report, null );
		asked.unidentified = again.log.confirms.splice( 0 );

		return asked;
	}
);

/* ---- What a Cancel and an OK leave behind in a guarded form. ---- */

// A guarded form with two submit buttons; Delete is marked, Keep is not.
function guardedForm() {
	const form = mk( 'form', { 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Deleting' } );
	const del = control( 'button', { type: 'submit', 'data-wpcpm-confirm': 'Delete?' }, 'Delete' );
	const keep = control( 'button', { type: 'submit' }, 'Keep' );

	form.add( del, keep );

	return { form, del, keep };
}

function leftBehind( form, del, keep ) {
	return {
		sent: form.getAttribute( 'data-wpcpm-sent' ),
		busy: form.getAttribute( 'aria-busy' ),
		className: form.className,
		deleteDisabled: del.disabled,
		keepDisabled: keep.disabled,
		deleteReads: del.textContent,
		deleteSavedLabel: del.getAttribute( 'data-wpcpm-label' ),
	};
}

scenario(
	'a Cancel leaves the form unlocked and its buttons enabled, with their labels as they were',
	{ asked: [ 'Delete?' ], prevented: true, scheduled: 0, left: { sent: null, busy: null, className: '', deleteDisabled: false, keepDisabled: false, deleteReads: 'Delete', deleteSavedLabel: null } },
	() => {
		const { form, del, keep } = guardedForm();
		const { log, state } = load( FORMS );

		state.answer = false;

		const event = submit( form, del );
		const scheduled = log.timeouts.length;

		flush( log );

		return { asked: log.confirms.slice(), prevented: event.defaultPrevented, scheduled, left: leftBehind( form, del, keep ) };
	}
);

scenario(
	'an OK locks the form once: the pressed button shows it is working and is disabled after the request is built, the other is disabled, and a repeat is swallowed',
	{
		asked: [ 'Delete?' ],
		prevented: false,
		scheduled: 1,
		locked: { sent: '1', busy: 'true', className: ' is-sending', deleteDisabled: false, keepDisabled: true, deleteReads: 'Deleting', deleteSavedLabel: 'Delete' },
		settled: { deleteDisabled: true },
		repeat: { prevented: true, scheduled: 0, deleteReads: 'Deleting', deleteSavedLabel: 'Delete' },
	},
	() => {
		const { form, del, keep } = guardedForm();
		const { log } = load( FORMS );
		const first = submit( form, del );
		const asked = log.confirms.slice();
		const scheduled = log.timeouts.length;
		const locked = leftBehind( form, del, keep );

		flush( log );

		const settled = { deleteDisabled: del.disabled };
		const second = submit( form, del );

		return {
			asked,
			prevented: first.defaultPrevented,
			scheduled,
			locked,
			settled,
			repeat: { prevented: second.defaultPrevented, scheduled: log.timeouts.length, deleteReads: del.textContent, deleteSavedLabel: del.getAttribute( 'data-wpcpm-label' ) },
		};
	}
);

/* ---- A form the guard has sent is not asked again; one it has not sent is asked every time. ---- */

// How many of these submits were left to post: the ones nothing prevented.
const posts = ( events ) => events.filter( ( event ) => ! event.defaultPrevented ).length;

scenario(
	'a Yes, then a second submit: asked once in total, one post, the form stays locked',
	{ asked: [ 'Delete?' ], posts: 1, sent: '1', secondPrevented: true },
	() => {
		const { form, del } = guardedForm();
		const { log } = load( FORMS );
		const first = submit( form, del );
		const second = submit( form, del );

		return { asked: log.confirms.slice(), posts: posts( [ first, second ] ), sent: form.getAttribute( 'data-wpcpm-sent' ), secondPrevented: second.defaultPrevented };
	}
);

scenario(
	'a No, then a second submit answered Yes: asked twice, one post, then locked',
	{ asked: [ 'Delete?', 'Delete?' ], posts: 1, sentAfterNo: null, sentAfterYes: '1' },
	() => {
		const { form, del } = guardedForm();
		const { log, state } = load( FORMS );

		state.answer = false;

		const first = submit( form, del );
		const sentAfterNo = form.getAttribute( 'data-wpcpm-sent' );

		state.answer = true;

		const second = submit( form, del );

		return { asked: log.confirms.slice(), posts: posts( [ first, second ] ), sentAfterNo, sentAfterYes: form.getAttribute( 'data-wpcpm-sent' ) };
	}
);

scenario(
	'a form without data-wpcpm-once is never locked, so every press asks and every Yes posts',
	{ asked: [ 'Delete?', 'Delete?' ], posts: 2, sent: null },
	() => {
		const form = mk( 'form', {} );
		const del = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'Delete?' } );

		form.add( del );

		const { log } = load( FORMS );
		const first = submit( form, del );
		const second = submit( form, del );

		return { asked: log.confirms.slice(), posts: posts( [ first, second ] ), sent: form.getAttribute( 'data-wpcpm-sent' ) };
	}
);

/* ---- The tracker records submit controls and nothing else. ---- */

scenario(
	'a press on a type="button" control is not tracked: the marked submit control is asked, and the toggle is not shown working',
	{ asked: [ 'Delete?' ], toggleReads: 'Show details', toggleSavedLabel: null },
	() => {
		const form = mk( 'form', { 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Deleting' } );
		const toggle = control( 'button', { type: 'button' }, 'Show details' );
		const del = control( 'button', { type: 'submit', 'data-wpcpm-confirm': 'Delete?' }, 'Delete' );

		form.add( toggle, del );

		const { log } = load( FORMS );

		press( toggle );
		submit( form, null );
		flush( log );

		return { asked: log.confirms.slice(), toggleReads: toggle.textContent, toggleSavedLabel: toggle.getAttribute( 'data-wpcpm-label' ) };
	}
);

scenario( 'a press on a type="reset" control is not tracked', [ 'A?' ], () => {
	const form = mk( 'form', {} );
	const marked = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'A?' } );
	const clear = mk( 'button', { type: 'reset' } );

	form.add( marked, clear );

	const { log } = load( FORMS );

	press( clear );
	submit( form, null );

	return log.confirms;
} );

scenario( 'a press on an input of type submit, and one of type image, is tracked', [ [ 'B?' ], [ 'C?' ] ], () => {
	const form = mk( 'form', {} );
	const first = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'A?' } );
	const second = mk( 'input', { type: 'submit', 'data-wpcpm-confirm': 'B?' } );
	const third = mk( 'input', { type: 'image', 'data-wpcpm-confirm': 'C?' } );

	form.add( first, second, third );

	const { log } = load( FORMS );
	const asked = [];

	press( second );
	submit( form, null );
	asked.push( log.confirms.splice( 0 ) );
	press( third );
	submit( form, null );
	asked.push( log.confirms.splice( 0 ) );

	return asked;
} );

scenario( 'Enter or Space on a focused submit button is tracked; on a type="button" it is not', [ [ 'B?' ], [ 'A?' ] ], () => {
	const form = mk( 'form', {} );
	const first = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'A?' } );
	const second = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'B?' } );
	const toggle = mk( 'button', { type: 'button' } );

	form.add( first, second, toggle );

	const { log } = load( FORMS );
	const asked = [];

	key( second, 'Enter' );
	submit( form, null );
	asked.push( log.confirms.splice( 0 ) );
	// The earlier press stays tracked until a submit control is pressed; a type="button" does not replace it.
	key( first, ' ' );
	key( toggle, 'Enter' );
	submit( form, null );
	asked.push( log.confirms.splice( 0 ) );

	return asked;
} );

scenario( 'a press in one form is not carried into another', [ 'B?' ], () => {
	const one = mk( 'form', {} );
	const two = mk( 'form', {} );
	const first = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'A?' } );
	const second = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'B?' } );

	one.add( first );
	two.add( second );

	const { log } = load( FORMS );

	press( first );
	submit( two, null );

	return log.confirms;
} );

scenario(
	'a text field clicked last never gets the busy label or the disabled state',
	{ asked: [ 'Delete?' ], textReads: '', textSavedLabel: null, textDisabled: false },
	() => {
		const form = mk( 'form', { 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Deleting' } );
		const text = mk( 'input', { type: 'text' } );
		const del = control( 'button', { type: 'submit', 'data-wpcpm-confirm': 'Delete?' }, 'Delete' );

		form.add( text, del );

		const { log } = load( FORMS );

		press( text );
		submit( form, null );
		flush( log );

		return { asked: log.confirms.slice(), textReads: text.textContent, textSavedLabel: text.getAttribute( 'data-wpcpm-label' ), textDisabled: text.disabled };
	}
);

/* ---- Which press the reader follows, and what it skips. ---- */

scenario(
	'a press on a node inside a submit button (a slot button is two spans) is the press of that button',
	{ asked: [ 'Book it?' ], slotReads: 'Booking', slotSavedLabel: '9:00', slotDisabled: true, otherDisabled: true },
	() => {
		const form = mk( 'form', { 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Booking' } );
		const other = control( 'button', { type: 'submit', 'data-wpcpm-confirm': 'Other?' }, '10:00' );
		const slot = control( 'button', { type: 'submit', name: 'start', value: '9', 'data-wpcpm-confirm': 'Book it?' }, '9:00' );
		const time = mk( 'span', {} );

		form.add( other, slot );
		slot.add( time );

		const { log } = load( FORMS );

		press( time );
		submit( form, null );
		flush( log );

		return { asked: log.confirms.slice(), slotReads: slot.textContent, slotSavedLabel: slot.getAttribute( 'data-wpcpm-label' ), slotDisabled: slot.disabled, otherDisabled: other.disabled };
	}
);

scenario(
	'the browser\'s submitter beats the press the tracker holds: its question is asked and it is the one shown working',
	{ asked: [ 'Delete?' ], deleteReads: 'Deleting', keepReads: 'Keep' },
	() => {
		const { form, del, keep } = guardedForm();
		const { log } = load( FORMS );

		press( keep );
		submit( form, del );
		flush( log );

		return { asked: log.confirms.slice(), deleteReads: del.textContent, keepReads: keep.textContent };
	}
);

scenario( 'a press is tracked even when a handler on the button stops the event: mousedown and keydown', [ [ 'B?' ], [ 'A?' ] ], () => {
	const form = mk( 'form', {} );
	const first = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'A?' } );
	const second = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'B?' } );
	const stop = ( event ) => event.stopPropagation();

	form.add( first, second );
	second.addEventListener( 'mousedown', stop );
	first.addEventListener( 'keydown', stop );

	const { log } = load( FORMS );
	const asked = [];

	press( second );
	submit( form, null );
	asked.push( log.confirms.splice( 0 ) );
	key( first, ' ' );
	submit( form, null );
	asked.push( log.confirms.splice( 0 ) );

	return asked;
} );

scenario( 'a marked type="button" control is not the last resort: with no submitter and nothing tracked, the first marked submit control is asked', [ 'Delete?' ], () => {
	const form = mk( 'form', {} );
	const toggle = mk( 'button', { type: 'button', 'data-wpcpm-confirm': 'Toggle?' } );
	const del = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': 'Delete?' } );

	form.add( toggle, del );

	const { log } = load( FORMS );

	submit( form, null );

	return log.confirms;
} );

scenario(
	'an empty mark on a button asks nothing and does not fall back to the form\'s mark; an unmarked button in that form still asks it',
	{ quiet: [], unmarked: [ 'Form?' ] },
	() => {
		const form = mk( 'form', { 'data-wpcpm-confirm': 'Form?' } );
		const quiet = mk( 'button', { type: 'submit', 'data-wpcpm-confirm': '' } );
		const plain = mk( 'button', { type: 'submit' } );

		form.add( quiet, plain );

		const { log } = load( FORMS );

		submit( form, quiet );

		const asked = { quiet: log.confirms.splice( 0 ) };

		submit( form, plain );
		asked.unmarked = log.confirms.splice( 0 );

		return asked;
	}
);

/* ---- A form that arrives after the script ran is guarded too. ---- */

// The slot list of the booking calendar, inserted into the page after the script ran: one form, a time
// per button, and the live region the form's status sentence goes in. The script is loaded first, so
// nothing was bound to this form when it was made.
function lateSlots() {
	const { log, state } = load( FORMS );
	const form = mk( 'form', { 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Booking', 'data-wpcpm-status': 'Booking your call' } );
	const slot = control( 'button', { type: 'submit', name: 'start', value: '9' }, '9:00' );
	const other = control( 'button', { type: 'submit', name: 'start', value: '10' }, '10:00' );
	const status = mk( 'p', { 'data-wpcpm-busy-status': '' } );

	form.add( slot, other, status );

	return { log, state, form, slot, other, status };
}

function slotsLeft( { form, slot, other, status } ) {
	return {
		sent: form.getAttribute( 'data-wpcpm-sent' ),
		busy: form.getAttribute( 'aria-busy' ),
		className: form.className,
		slotReads: slot.textContent,
		slotSavedLabel: slot.getAttribute( 'data-wpcpm-label' ),
		slotDisabled: slot.disabled,
		otherDisabled: other.disabled,
		status: status.textContent,
	};
}

scenario(
	'a guarded form inserted after the script ran: one press locks it, the pressed slot reads its busy label and is disabled once the request is built, and the form posts once',
	{
		prevented: false,
		scheduled: 1,
		locked: { sent: '1', busy: 'true', className: ' is-sending', slotReads: 'Booking', slotSavedLabel: '9:00', slotDisabled: false, otherDisabled: true, status: 'Booking your call' },
		settled: { slotDisabled: true },
	},
	() => {
		const late = lateSlots();

		press( late.slot );

		const event = submit( late.form, late.slot );
		const scheduled = late.log.timeouts.length;
		const locked = slotsLeft( late );

		flush( late.log );

		return { prevented: event.defaultPrevented, scheduled, locked, settled: { slotDisabled: late.slot.disabled } };
	}
);

scenario(
	'a second press on a guarded form inserted after the script ran is dropped: one post in all, nothing more scheduled, and the busy label is not saved over the real one',
	{ posts: 1, secondPrevented: true, scheduled: 0, left: { slotReads: 'Booking', slotSavedLabel: '9:00' } },
	() => {
		const late = lateSlots();

		press( late.slot );

		const first = submit( late.form, late.slot );

		flush( late.log );

		// Enter in a field, or a second tap, with the browser naming no submitter this time.
		const second = submit( late.form, null );
		const left = slotsLeft( late );

		return {
			posts: posts( [ first, second ] ),
			secondPrevented: second.defaultPrevented,
			scheduled: late.log.timeouts.length,
			left: { slotReads: left.slotReads, slotSavedLabel: left.slotSavedLabel },
		};
	}
);

scenario(
	'a guarded form inserted after the script ran is restored after Back, and only from the cache: the labels, the buttons, the lock and the status come back, and it can be pressed again',
	{
		stays: { sent: '1', slotReads: 'Booking' },
		restored: { sent: null, busy: null, className: '', slotReads: '9:00', slotSavedLabel: null, slotDisabled: false, otherDisabled: false, status: '' },
		again: { prevented: false, sent: '1', scheduled: 1 },
	},
	() => {
		const late = lateSlots();

		press( late.slot );
		submit( late.form, late.slot );
		flush( late.log );

		// A page shown again without having been in the cache is left alone.
		pageshow( late.log, false );

		const stays = { sent: late.form.getAttribute( 'data-wpcpm-sent' ), slotReads: late.slot.textContent };

		pageshow( late.log, true );

		const restored = slotsLeft( late );
		const event = submit( late.form, late.slot );

		return { stays, restored, again: { prevented: event.defaultPrevented, sent: late.form.getAttribute( 'data-wpcpm-sent' ), scheduled: late.log.timeouts.length } };
	}
);

scenario(
	'a guarded form inserted after the script ran, posted through a control outside it: that control reads its busy label and is disabled, and after Back it is released',
	{
		asked: [],
		locked: { voidFormLocked: true, voidReads: 'Voiding', voidDisabled: true, addDisabled: false },
		restored: { voidFormLocked: false, voidReads: 'Void', voidDisabled: false },
	},
	() => {
		const { log } = load( FORMS );
		const add = mk( 'form', { 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Adding' } );
		const addButton = control( 'button', { type: 'submit' }, 'Add codes' );
		const voidButton = control( 'button', { type: 'submit', form: 'wpcpm-offer-void-7' }, 'Void' );
		const target = mk( 'form', { id: 'wpcpm-offer-void-7', 'data-wpcpm-once': '', 'data-wpcpm-busy': 'Voiding' } );

		add.add( addButton, voidButton );
		press( voidButton );
		submit( target, null );
		flush( log );

		const locked = { voidFormLocked: '1' === target.getAttribute( 'data-wpcpm-sent' ), voidReads: voidButton.textContent, voidDisabled: voidButton.disabled, addDisabled: addButton.disabled };

		pageshow( log, true );

		return {
			asked: log.confirms.slice(),
			locked,
			restored: { voidFormLocked: null !== target.getAttribute( 'data-wpcpm-sent' ), voidReads: voidButton.textContent, voidDisabled: voidButton.disabled },
		};
	}
);

// The reader runs first and the guard second, for a form made after the script ran as for any other: a
// No must leave the form as it was, with nothing scheduled, and a Yes must be asked before the form is
// locked, since a locked form is never asked again.
scenario(
	'a guarded form with a question, inserted after the script ran: a No leaves it unlocked with nothing scheduled, the Yes after it is asked and locks it, and a repeat is swallowed without a third question',
	{
		asked: [ 'Delete?', 'Delete?' ],
		afterNo: { prevented: true, scheduled: 0, left: { sent: null, busy: null, className: '', deleteDisabled: false, keepDisabled: false, deleteReads: 'Delete', deleteSavedLabel: null } },
		afterYes: { prevented: false, scheduled: 1, left: { sent: '1', busy: 'true', className: ' is-sending', deleteDisabled: false, keepDisabled: true, deleteReads: 'Deleting', deleteSavedLabel: 'Delete' } },
		repeat: { prevented: true, scheduled: 1 },
	},
	() => {
		const { log, state } = load( FORMS );
		const { form, del, keep } = guardedForm();

		state.answer = false;

		const no = submit( form, del );
		const afterNo = { prevented: no.defaultPrevented, scheduled: log.timeouts.length, left: leftBehind( form, del, keep ) };

		state.answer = true;

		const yes = submit( form, del );
		const afterYes = { prevented: yes.defaultPrevented, scheduled: log.timeouts.length, left: leftBehind( form, del, keep ) };
		const again = submit( form, del );

		return { asked: log.confirms.slice(), afterNo, afterYes, repeat: { prevented: again.defaultPrevented, scheduled: log.timeouts.length } };
	}
);

scenario(
	'a form inserted after the script ran without data-wpcpm-once is never locked: every press posts and nothing is scheduled',
	{ posts: 2, sent: null, scheduled: 0, reads: 'Save', disabled: false },
	() => {
		const { log } = load( FORMS );
		const form = mk( 'form', { 'data-wpcpm-busy': 'Saving' } );
		const save = control( 'button', { type: 'submit' }, 'Save' );

		form.add( save );

		const first = submit( form, save );
		const second = submit( form, save );

		return { posts: posts( [ first, second ] ), sent: form.getAttribute( 'data-wpcpm-sent' ), scheduled: log.timeouts.length, reads: save.textContent, disabled: save.disabled };
	}
);

scenario(
	'a submit another listener on the document had already prevented does not lock a guarded form inserted after the script ran',
	{ prevented: true, scheduled: 0, left: { sent: null, busy: null, className: '', slotReads: '9:00', slotSavedLabel: null, slotDisabled: false, otherDisabled: false, status: '' } },
	() => {
		doc.addEventListener( 'submit', ( event ) => event.preventDefault(), true );

		const late = lateSlots();
		const event = submit( late.form, late.slot );

		flush( late.log );

		return { prevented: event.defaultPrevented, scheduled: late.log.timeouts.length, left: slotsLeft( late ) };
	}
);

/* ---- A handler that stops the event does not hide the submit from the script. ---- */

// Another script's listener on a guarded form, added before forms.js ran, stops the submit event from
// travelling on: one with stopPropagation(), one with stopImmediatePropagation(). A listener on the
// document in the bubble phase would never hear of the submit, and the question would go unasked and
// the form unlocked; in the capture phase it is first in line.
scenario(
	'a listener on a guarded form that stops the submit event from bubbling does not hide it from the script: the question is asked, a No stops the post, and a Yes locks the form',
	{
		no: { asked: [ 'Delete?' ], prevented: true, sent: null, scheduled: 0 },
		yes: { asked: [ 'Delete?' ], prevented: false, sent: '1', scheduled: 1, deleteReads: 'Deleting' },
	},
	() => {
		const stopped = guardedForm();
		const stoppedNow = guardedForm();

		stopped.form.addEventListener( 'submit', ( event ) => event.stopPropagation() );
		stoppedNow.form.addEventListener( 'submit', ( event ) => event.stopImmediatePropagation() );

		const { log, state } = load( FORMS );

		state.answer = false;

		const refused = submit( stopped.form, stopped.del );
		const no = { asked: log.confirms.splice( 0 ), prevented: refused.defaultPrevented, sent: stopped.form.getAttribute( 'data-wpcpm-sent' ), scheduled: log.timeouts.length };

		state.answer = true;

		const allowed = submit( stoppedNow.form, stoppedNow.del );

		return {
			no,
			yes: { asked: log.confirms.splice( 0 ), prevented: allowed.defaultPrevented, sent: stoppedNow.form.getAttribute( 'data-wpcpm-sent' ), scheduled: log.timeouts.length, deleteReads: stoppedNow.del.textContent },
		};
	}
);

console.log( '\n' + ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
