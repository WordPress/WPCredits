'use strict';
/*
 * assets/js/switcher.js, run in node: what stays in a "Viewing as" list for what an Administrator
 * has typed, and the list the page is left with.
 *
 *   node bin/js/switcher-filter.js [path to switcher.js]
 *
 * Two parts. First the script's two pure functions, `fold()` and `narrow()`, read out of its source
 * by name and run in a fresh context with no DOM at all. Then the whole script, run on a stand-in for
 * the few things on the page it touches (the box's row, the box, the status line and a select) and
 * driven as a person drives it: typing, choosing, Escape and Enter. The select keeps its options as a
 * browser does. insertBefore() and removeChild() move them, a reference that is not one of its options
 * is refused, and a select left with no chosen option chooses its first, as the HTML selectedness
 * rules have it, so an entry taken out by mistake shows as a changed choice. It also has a width, as
 * a select's is: as wide as its longest name, within the room the page gives it, and never below its
 * inline `min-width`; and the window it sits in can change size, with its timers run by hand. The
 * forms harness under bin/js/forms-harness/ models only what forms.js touches and has no select, so
 * it is not used here.
 *
 * The status line counts each time its text is set, since a screen reader may read it out again on
 * any of them, the same sentence or not.
 *
 * The last scenarios are mutation proofs: the script with the cursor of its re-insertion broken, and
 * with the chosen entry no longer kept, run through the same steps, must each leave a list that is
 * wrong, and with its no-match sentence set whatever the line already says, must set it twice. A
 * check that passes whatever the script does would pass them too.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, and
 * exits 1 when any scenario differs. bin/test-dashboard-switcher.php runs it as part of its suite.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..', 'assets', 'js', 'switcher.js' ) );

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
 * One named function declaration out of a source, from `function <name>(` to the brace that
 * closes its body. The two functions read here hold no brace inside a string or a pattern, so
 * counting braces finds the end.
 *
 * @param {string} source The script.
 * @param {string} name   The function's name.
 * @return {string} Its declaration, or '' when the script has none of that name.
 */
function declaration( source, name ) {
	const start = source.indexOf( 'function ' + name + '(' );

	if ( -1 === start ) {
		return '';
	}

	let depth = 0;

	for ( let at = source.indexOf( '{', start ); at < source.length; at++ ) {
		if ( '{' === source[ at ] ) {
			depth++;
		} else if ( '}' === source[ at ] ) {
			depth--;

			if ( 0 === depth ) {
				return source.slice( start, at + 1 );
			}
		}
	}

	return '';
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';
const fold = declaration( source, 'fold' );
const narrow = declaration( source, 'narrow' );

scenario( 'the script declares fold() and narrow()', [ true, true ], () => [ '' !== fold, '' !== narrow ] );

// Run as ES5 runs them: nothing from the page, nothing from node.
const filter = ( '' !== fold && '' !== narrow )
	? vm.runInNewContext( '"use strict";\n' + fold + '\n' + narrow + '\n({ fold: fold, narrow: narrow });', {} )
	: { fold: () => '', narrow: () => ( {} ) };

/* ---- fold(): what is compared ---- */

scenario( 'fold() lowers the case', 'zoe academy', () => filter.fold( 'ZOE Academy' ) );
scenario( 'and takes the accents off, as remove_accents() does on the server', 'alvaro ecole sao paulo', () => filter.fold( 'Álvaro École São Paulo' ) );
scenario( 'and the letters Unicode does not decompose, Ł among them', 'lodz oresund dakovo strasse', () => filter.fold( 'Łódź Øresund Đakovo Straße' ) );
scenario( 'and leaves the rest of the name as it is', 'student 10 (lab)', () => filter.fold( 'Student 10 (Lab)' ) );

/* ---- narrow(): what stays in the list ---- */

// The list as the server draws it, A to Z, with Zoe Academy the one being viewed.
const labels = [ 'Álvaro University', 'bergen school', 'École 42', 'Łódź Institute', 'Student 2', 'Student 10', 'Zoe Academy' ];
const keys = labels.map( ( label ) => filter.fold( label ) );
const viewing = 6;

function kept( query, selected ) {
	const result = filter.narrow( keys, undefined === selected ? viewing : selected, query );

	return { names: labels.filter( ( label, index ) => result.keep[ index ] ), matched: result.matched };
}

scenario( 'an empty box keeps the whole list', { names: labels, matched: 7 }, () => kept( '' ) );
scenario( 'a box of spaces is an empty box', { names: labels, matched: 7 }, () => kept( '   ' ) );
scenario( 'typed without the accent, the accented name is found', { names: [ 'Álvaro University', 'Zoe Academy' ], matched: 1 }, () => kept( 'alv' ) );
scenario( 'typed with the accent, too', { names: [ 'École 42', 'Zoe Academy' ], matched: 1 }, () => kept( 'Éco' ) );
scenario( 'in capitals, the same', { names: [ 'École 42', 'Zoe Academy' ], matched: 1 }, () => kept( 'ECOLE' ) );
scenario( 'Ł typed as L', { names: [ 'Łódź Institute', 'Zoe Academy' ], matched: 1 }, () => kept( 'lodz' ) );
scenario( 'anywhere in the name, not only at its start', { names: [ 'bergen school', 'Zoe Academy' ], matched: 1 }, () => kept( 'school' ) );
scenario( 'a number is matched as typed: "student 1" finds Student 10 and not Student 2', { names: [ 'Student 10', 'Zoe Academy' ], matched: 1 }, () => kept( 'student 1' ) );
scenario( 'the spaces around what was typed are not part of it', { names: [ 'Álvaro University', 'Zoe Academy' ], matched: 1 }, () => kept( '  alv  ' ) );
scenario( 'the one being viewed is never taken out, matching or not', { names: [ 'Zoe Academy' ], matched: 0 }, () => kept( 'nothing like it' ) );
scenario( 'and counts as a match when it is one', { names: [ 'Zoe Academy' ], matched: 1 }, () => kept( 'zoe' ) );
scenario( 'whichever entry is chosen is the one kept', { names: [ 'bergen school' ], matched: 0 }, () => kept( 'nothing like it', 1 ) );
scenario( 'a list with nothing chosen keeps only the matches', { names: [ 'Student 2', 'Student 10' ], matched: 2 }, () => kept( 'student', -1 ) );

/* ---- the script on a page: what the list holds after each step ---- */

/** One entry of a select. */
class Option {
	constructor( value, text, selected ) {
		this.value = value;
		this.text = text;
		this.selected = !! selected;
		this.parentNode = null;
	}
}

/** A single select, as far as the script reaches it. */
class Select {
	constructor( id, options ) {
		this.id = id;
		this.children = [];
		this.style = { minWidth: '' };
		// The room the page gives the list, in px: a narrower window is less room.
		this.room = 400;
		options.forEach( ( option ) => this.insertBefore( option, null ) );
	}

	// As wide as its longest name at 8px a letter, within its room, and never below its minimum.
	getBoundingClientRect() {
		const longest = this.children.reduce( ( most, option ) => Math.max( most, option.text.length ), 0 );

		return { width: Math.max( parseFloat( this.style.minWidth ) || 0, Math.min( this.room, longest * 8 ) ) };
	}

	get options() {
		return this.children.slice();
	}

	get selectedIndex() {
		return this.children.findIndex( ( option ) => option.selected );
	}

	get value() {
		const chosen = this.children[ this.selectedIndex ];

		return chosen ? chosen.value : '';
	}

	// What a person does with the list itself.
	choose( value ) {
		this.children.forEach( ( option ) => {
			option.selected = option.value === value;
		} );
	}

	insertBefore( option, before ) {
		if ( option.parentNode ) {
			option.parentNode.removeChild( option );
		}

		const at = null === before ? this.children.length : this.children.indexOf( before );

		if ( -1 === at ) {
			throw new Error( 'insertBefore(): the reference is not an option of this list' );
		}

		this.children.splice( at, 0, option );
		option.parentNode = this;
		this.settle();

		return option;
	}

	removeChild( option ) {
		const at = this.children.indexOf( option );

		if ( -1 === at ) {
			throw new Error( 'removeChild(): not an option of this list' );
		}

		this.children.splice( at, 1 );
		option.parentNode = null;
		this.settle();

		return option;
	}

	// The selectedness rules of a single select: with none chosen the first is, and with two the
	// last in the list stays chosen.
	settle() {
		const chosen = this.children.filter( ( option ) => option.selected );

		if ( ! chosen.length && this.children.length ) {
			this.children[ 0 ].selected = true;
		}

		chosen.slice( 0, -1 ).forEach( ( option ) => {
			option.selected = false;
		} );
	}
}

/** An element that holds attributes and listeners, and fires events at them. */
function element( attrs ) {
	const listeners = {};

	return {
		value: '',
		hidden: true,
		textContent: '',
		getAttribute( name ) {
			return Object.prototype.hasOwnProperty.call( attrs, name ) ? attrs[ name ] : null;
		},
		addEventListener( type, fn ) {
			( listeners[ type ] = listeners[ type ] || [] ).push( fn );
		},
		fire( type, init ) {
			const event = Object.assign( {
				type,
				defaultPrevented: false,
				preventDefault() {
					this.defaultPrevented = true;
				},
			}, init || {} );

			( listeners[ type ] || [] ).forEach( ( fn ) => fn( event ) );

			return event;
		},
	};
}

// The list as the server draws it, A to Z, with Student 10 the one being viewed: in the middle, so
// entries go back both before and after it.
const DRAWN = [
	[ 'recALVARO', 'Álvaro University' ],
	[ 'recBERGEN', 'bergen school' ],
	[ 'recECOLE', 'École 42' ],
	[ 'recLODZ', 'Łódź Institute' ],
	[ 'rec2', 'Student 2' ],
	[ 'rec10', 'Student 10' ],
	[ 'recZOE', 'Zoe Academy' ],
];
const ALL = DRAWN.map( ( entry ) => entry[ 1 ] );

/**
 * A page holding one switcher, with the script run on it and its DOMContentLoaded handled.
 *
 * @param {string} script The script's source.
 * @param {string} kept   What the browser kept in the box across a reload.
 * @return {Object} The parts, and what a person does with them.
 */
function page( script, kept ) {
	const select = new Select( 'wpcpm-test-switcher', DRAWN.map( ( entry ) => new Option( entry[ 0 ], entry[ 1 ], 'rec10' === entry[ 0 ] ) ) );
	const input = element( { 'aria-controls': 'wpcpm-test-switcher' } );

	input.style = { minWidth: '' };
	const status = element( { 'data-wpcpm-none': 'No entries match that search.' } );

	// Each time the script sets the status line's text, counted, the same sentence or not.
	let said = '';

	status.writes = 0;
	Object.defineProperty( status, 'textContent', {
		get: () => said,
		set( text ) {
			said = String( text );
			status.writes++;
		},
	} );

	const row = element( {} );
	const ready = [];

	const timers = new Map();
	const listeners = {};
	let made = 0;

	input.value = kept || '';
	row.querySelector = ( selector ) => ( 'input' === selector ? input : ( '[role="status"]' === selector ? status : null ) );

	vm.runInNewContext( script, {
		window: {
			addEventListener( type, fn ) {
				( listeners[ type ] = listeners[ type ] || [] ).push( fn );
			},
			setTimeout( fn ) {
				timers.set( ++made, fn );

				return made;
			},
			clearTimeout( id ) {
				timers.delete( id );
			},
		},
		document: {
			addEventListener( type, fn ) {
				if ( 'DOMContentLoaded' === type ) {
					ready.push( fn );
				}
			},
			querySelectorAll: ( selector ) => ( '.wpcpm-dashboard__switcher-find' === selector ? [ row ] : [] ),
			getElementById: ( id ) => ( id === select.id ? select : null ),
		},
	} );
	ready.forEach( ( fn ) => fn() );

	return {
		select,
		input,
		status,
		row,
		type( text ) {
			input.value = text;
			input.fire( 'input' );
		},
		key: ( name ) => input.fire( 'keydown', { key: name } ).defaultPrevented,
		listed: () => select.options.map( ( option ) => option.text ),
		// The window changes size (or a phone turns), giving the list `room` px.
		resize( room, type ) {
			select.room = room;
			( listeners[ type || 'resize' ] || [] ).forEach( ( fn ) => fn( { type: type || 'resize' } ) );
		},
		waiting: () => timers.size,
		flush() {
			const due = Array.from( timers.values() );

			timers.clear();
			due.forEach( ( fn ) => fn() );
		},
		width: () => select.getBoundingClientRect().width,
		writes: () => status.writes,
	};
}

scenario( 'the script shows the box the server drew hidden', false, () => page( source ).row.hidden );

scenario( 'typing narrows the list to the matches and the chosen entry, in the order drawn', { listed: [ 'Student 10', 'Zoe Academy' ], value: 'rec10', status: '' }, () => {
	const p = page( source );

	p.type( 'zoe' );

	return { listed: p.listed(), value: p.select.value, status: p.status.textContent };
} );

scenario( 'an entry before the chosen one is found as well', { listed: [ 'Łódź Institute', 'Student 10' ], value: 'rec10' }, () => {
	const p = page( source );

	p.type( 'zoe' );
	p.type( 'lodz' );

	return { listed: p.listed(), value: p.select.value };
} );

scenario( 'nothing matching leaves the chosen entry, chosen, and says so', { listed: [ 'Student 10' ], value: 'rec10', status: 'No entries match that search.' }, () => {
	const p = page( source );

	p.type( 'nothing like it' );

	return { listed: p.listed(), value: p.select.value, status: p.status.textContent };
} );

scenario( 'and the sentence goes once something matches again', { listed: [ 'Student 2', 'Student 10' ], status: '' }, () => {
	const p = page( source );

	p.type( 'nothing like it' );
	p.type( 'stu' );

	return { listed: p.listed(), status: p.status.textContent };
} );

scenario( 'emptying the box puts every entry back in the order drawn, the chosen one still chosen', { listed: ALL, value: 'rec10' }, () => {
	const p = page( source );

	p.type( 'zoe' );
	p.type( '' );

	return { listed: p.listed(), value: p.select.value };
} );

scenario( 'an entry chosen from the narrowed list is the one kept from then on', { narrowed: [ 'Student 2', 'Zoe Academy' ], value: 'rec2', back: ALL, still: 'rec2' }, () => {
	const p = page( source );

	p.type( 'student' );
	p.select.choose( 'rec2' );
	p.type( 'zoe' );

	const narrowed = p.listed();
	const value = p.select.value;

	p.type( '' );

	return { narrowed, value, back: p.listed(), still: p.select.value };
} );

scenario( 'Escape clears the box and puts the list back', { prevented: true, box: '', listed: ALL, status: '' }, () => {
	const p = page( source );

	p.type( 'nothing like it' );

	const prevented = p.key( 'Escape' );

	return { prevented, box: p.input.value, listed: p.listed(), status: p.status.textContent };
} );

scenario( 'Escape in an empty box is left to the browser', false, () => page( source ).key( 'Escape' ) );
scenario( 'Enter in the box submits nothing', true, () => page( source ).key( 'Enter' ) );
scenario( 'a box the browser kept filled across a reload narrows the list at once', [ 'Student 2', 'Student 10' ], () => page( source, 'student' ).listed() );

// Every step of a session, checked against narrow(): the list holds exactly the entries it keeps,
// in the order drawn, and the choice never moves.
scenario( 'through a run of typing, the list is always what narrow() keeps, in the order drawn', [], () => {
	const p = page( source );
	const keysDrawn = ALL.map( ( label ) => filter.fold( label ) );
	const wrong = [];

	[ 'a', 'zo', '', 's', 'student 1', 'l', '', 'x', 'e', 'ber', 'Á', '' ].forEach( ( query ) => {
		p.type( query );

		const result = filter.narrow( keysDrawn, 5, query );
		const want = ALL.filter( ( label, index ) => result.keep[ index ] );

		if ( JSON.stringify( p.listed() ) !== JSON.stringify( want ) || 'rec10' !== p.select.value ) {
			wrong.push( query );
		}
	} );

	return wrong;
} );

// A status line is read out when its text is set, and setting it to the sentence it already holds
// can have it read out again. Typing and a settled resize each run the search, so the sentence is
// set when nothing matches where something did, and emptied when something matches again, and at
// no other step.
scenario( 'the no-match sentence is set once, not again on each keystroke or settled resize, so a screen reader says it once', { matching: 0, none: 1, still: 1, said: 'No entries match that search.', back: 2, again: 2, status: '' }, () => {
	const p = page( source );

	p.type( 'zoe' );

	const matching = p.writes();

	p.type( 'nothing' );

	const none = p.writes();

	p.type( 'nothing like' );
	p.resize( 100 );
	p.flush();
	p.type( 'nothing like it' );

	const still = p.writes();
	const said = p.status.textContent;

	p.type( 'stu' );

	const back = p.writes();

	p.type( 'stud' );
	p.key( 'Escape' );

	return { matching, none, still, said, back, again: p.writes(), status: p.status.textContent };
} );

/* ---- the list's width: held while typing narrows it ---- */

// The longest name drawn, Álvaro University, is 17 letters: 136px. Student 10 and Zoe Academy, all
// that 'zoe' leaves, would be 88px.
scenario( 'with every entry in it, the list\'s width is set as its minimum, and the box\'s', { minWidth: '136px', width: 136, box: '136px' }, () => {
	const p = page( source );

	return { minWidth: p.select.style.minWidth, width: p.width(), box: p.input.style.minWidth };
} );

scenario( 'and held while typing narrows it, so the box and Show stay where they are', { minWidth: '136px', width: 136, back: 136 }, () => {
	const p = page( source );

	p.type( 'zoe' );

	const held = { minWidth: p.select.style.minWidth, width: p.width() };

	p.type( '' );

	return Object.assign( held, { back: p.width() } );
} );

scenario( 'a box the browser kept filled is measured before it narrows', '136px', () => page( source, 'zoe' ).select.style.minWidth );

scenario( 'a resize with an empty box clears the minimum and measures again, once it settles', { before: '136px', waiting: 1, after: '100px' }, () => {
	const p = page( source );

	p.resize( 100 );

	const before = p.select.style.minWidth;
	const waiting = p.waiting();

	p.flush();

	return { before, waiting, after: p.select.style.minWidth };
} );

scenario( 'and a wider window gives the room back', '136px', () => {
	const p = page( source );

	p.resize( 100 );
	p.flush();
	p.resize( 400 );
	p.flush();

	return p.select.style.minWidth;
} );

scenario( 'turning a phone is a resize too', '100px', () => {
	const p = page( source );

	p.resize( 100, 'orientationchange' );
	p.flush();

	return p.select.style.minWidth;
} );

scenario( 'a burst of resizes is measured once', { waiting: 1, after: '90px' }, () => {
	const p = page( source );

	p.resize( 120 );
	p.resize( 110 );
	p.resize( 90 );

	const waiting = p.waiting();

	p.flush();

	return { waiting, after: p.select.style.minWidth };
} );

// With text in the box, a settled resize puts every entry back, measures, and narrows again, all in
// one go: the person sees the same narrowed list, at the new width, with the same entry chosen and
// the same text in the box.
scenario( 'with text in the box a settled resize measures the full list at once and narrows it again', { minWidth: [ '100px', '100px' ], listed: [ 'Student 10', 'Zoe Academy' ], value: 'rec10', typed: 'zoe', status: '' }, () => {
	const p = page( source );

	p.type( 'zoe' );
	p.resize( 100 );
	p.flush();

	return { minWidth: [ p.select.style.minWidth, p.input.style.minWidth ], listed: p.listed(), value: p.select.value, typed: p.input.value, status: p.status.textContent };
} );

scenario( 'and keeps an entry chosen from the narrowed list, and the no-match sentence', { chosen: { minWidth: '100px', listed: [ 'Student 2', 'Student 10' ], value: 'rec2', typed: 'student' }, none: { listed: [ 'Student 10' ], status: 'No entries match that search.' } }, () => {
	const p = page( source );

	p.type( 'student' );
	p.select.choose( 'rec2' );
	p.resize( 100 );
	p.flush();

	const chosen = { minWidth: p.select.style.minWidth, listed: p.listed(), value: p.select.value, typed: p.input.value };
	const q = page( source );

	q.type( 'nothing like it' );
	q.resize( 100 );
	q.flush();

	return { chosen, none: { listed: q.listed(), status: q.status.textContent } };
} );

scenario( 'the measuring leaves the full list in order when the box is cleared afterwards', { minWidth: '100px', listed: ALL }, () => {
	const p = page( source );

	p.type( 'zoe' );
	p.resize( 100 );
	p.flush();
	p.key( 'Escape' );

	return { minWidth: p.select.style.minWidth, listed: p.listed() };
} );

/* ---- mutation proofs: the same steps on a broken script must go wrong ---- */

/**
 * The script with one piece of its source replaced, and whether the piece was there to replace.
 *
 * @param {string} find    What to replace.
 * @param {string} replace What to put instead.
 * @return {Object} `script` and `applied`.
 */
function mutant( find, replace ) {
	return { script: source.split( find ).join( replace ), applied: -1 !== source.indexOf( find ) };
}

scenario( 'mutation: a cursor that never moves puts the entries back out of order', { applied: true, inOrder: false }, () => {
	const broken = mutant( 'next = entry;', '' );
	const p = page( broken.script );

	p.type( 'zoe' );
	p.type( '' );

	return { applied: broken.applied, inOrder: JSON.stringify( p.listed() ) === JSON.stringify( ALL ) };
} );

scenario( 'mutation: an entry chosen but not kept is taken out, and the choice moves to the first', { applied: true, value: 'recZOE' }, () => {
	const broken = mutant( 'keep.push( hit || index === selected );', 'keep.push( hit );' );
	const p = page( broken.script );

	p.type( 'zoe' );

	return { applied: broken.applied, value: p.select.value };
} );

scenario( 'mutation: a sentence set whatever the line holds is set again for the same no match', { applied: true, writes: 2 }, () => {
	const broken = mutant( 'if ( status.textContent !== text ) {', 'if ( true ) {' );
	const p = page( broken.script );

	p.type( 'nothing' );
	p.type( 'nothing like it' );

	return { applied: broken.applied, writes: p.writes() };
} );

console.log( '\n' + ran + ' scenarios, ' + failed + ' differ' );
process.exit( failed ? 1 : 0 );
