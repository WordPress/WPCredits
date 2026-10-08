'use strict';
/*
 * A small DOM stand-in, enough to run assets/js/forms.js: the confirm reader, the submit guard
 * and the tracker of the last control pressed. There is no jsdom on the machines this runs on and
 * the harness takes no dependency, so this holds only the parts of the DOM the script touches.
 *
 * Events travel as the DOM specifies: capture listeners from the document down, then the target's
 * own (capturing ones first), then bubbling listeners back up to the document. Listeners on one
 * node run in the order they were added. stopPropagation() keeps the event from the nodes after
 * the current one, and stopImmediatePropagation() from the rest of the current node's listeners too.
 */
const fs = require( 'fs' );
const vm = require( 'vm' );

class El {
	constructor( tag, attrs ) {
		this.tagName = tag.toUpperCase();
		this.attrs = Object.assign( {}, attrs || {} );
		this.children = [];
		this.parentNode = null;
		this.listeners = [];
		this.disabled = false;
		this.className = this.attrs.class || '';
		this.innerHTML = '';
		this.textContent = '';
	}

	// A label read back as it was written, either way: setting one is setting the other, as it is on a
	// page for a label that holds no markup, so a control the script re-labels and restores reads as it did.
	get innerHTML() {
		return this.label;
	}

	set innerHTML( value ) {
		this.label = value;
	}

	get textContent() {
		return this.label;
	}

	set textContent( value ) {
		this.label = value;
	}

	// The type a browser reports: lower case, and the default of a button or an input.
	get type() {
		const given = this.attrs.type;

		if ( given ) {
			return given.toLowerCase();
		}

		return 'BUTTON' === this.tagName ? 'submit' : ( 'INPUT' === this.tagName ? 'text' : undefined );
	}

	get id() {
		return this.attrs.id || '';
	}

	// The form owner: the `form="<id>"` attribute when there is one, else the nearest form above.
	get form() {
		if ( this.attrs.form ) {
			return registry.find( ( e ) => 'FORM' === e.tagName && e.attrs.id === this.attrs.form ) || null;
		}

		let parent = this.parentNode;

		while ( parent && 'FORM' !== parent.tagName ) {
			parent = parent.parentNode;
		}

		return parent || null;
	}

	add( ...kids ) {
		kids.forEach( ( kid ) => {
			kid.parentNode = this;
			this.children.push( kid );
		} );

		return this;
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, name ) ? this.attrs[ name ] : null;
	}

	setAttribute( name, value ) {
		this.attrs[ name ] = String( value );
	}

	removeAttribute( name ) {
		delete this.attrs[ name ];
	}

	addEventListener( type, fn, options ) {
		const capture = true === options || !! ( options && options.capture );

		this.listeners.push( { type, fn, capture } );
	}

	descendants() {
		const out = [];

		( function walk( node ) {
			node.children.forEach( ( child ) => {
				out.push( child );
				walk( child );
			} );
		}( this ) );

		return out;
	}

	// Comma lists of `tag[attr][attr="value"]`: all the script's selectors are of that shape.
	matches( selector ) {
		return selector.split( ',' ).map( ( s ) => s.trim() ).some( ( part ) => {
			const m = part.match( /^([a-z]*)((?:\[[^\]]+\])*)$/i );

			if ( ! m ) {
				return false;
			}

			if ( m[ 1 ] && this.tagName !== m[ 1 ].toUpperCase() ) {
				return false;
			}

			return ( m[ 2 ].match( /\[[^\]]+\]/g ) || [] ).every( ( test ) => {
				const mm = test.match( /^\[([^=\]]+)(?:="([^"]*)")?\]$/ );
				const value = this.getAttribute( mm[ 1 ] );

				return undefined === mm[ 2 ] ? null !== value : value === mm[ 2 ];
			} );
		} );
	}

	querySelectorAll( selector ) {
		return this.descendants().filter( ( e ) => e.matches( selector ) );
	}

	querySelector( selector ) {
		return this.querySelectorAll( selector )[ 0 ] || null;
	}
}

// Every element made since the last reset(), in the order made: the document's tree, flat.
const registry = [];
const doc = new El( 'document' );

doc.readyState = 'complete';
doc.querySelectorAll = ( selector ) => registry.filter( ( e ) => e.matches( selector ) );
doc.querySelector = ( selector ) => doc.querySelectorAll( selector )[ 0 ] || null;

// A new element, attached to the document until something adopts it with add().
function mk( tag, attrs ) {
	const el = new El( tag, attrs );

	el.parentNode = doc;
	registry.push( el );

	return el;
}

function reset() {
	registry.length = 0;
	doc.listeners = [];
	doc.readyState = 'complete';
}

// Run forms.js in a fresh context, as a page that has just loaded it. `state.answer` is what
// window.confirm() answers; `log.confirms` holds every question asked; `log.timeouts` holds the
// callbacks handed to setTimeout(), which flush() runs; `log.windowListeners` holds the listeners the
// script put on the window, which pageshow() fires.
function load( path ) {
	const log = { confirms: [], timeouts: [], windowListeners: [] };
	const state = { answer: true };
	const win = {
		confirm: ( question ) => {
			log.confirms.push( question );

			return state.answer;
		},
		setTimeout: ( fn ) => {
			log.timeouts.push( fn );
		},
		addEventListener( type, fn ) {
			log.windowListeners.push( { type, fn } );
		},
		getSelection() {},
		CSS: undefined,
	};

	win.window = win;
	win.document = doc;
	vm.runInNewContext( fs.readFileSync( path, 'utf8' ), { window: win, document: doc, CSS: undefined } );

	return { log, state };
}

function flush( log ) {
	log.timeouts.splice( 0 ).forEach( ( fn ) => fn() );
}

// The browser shows a page again: `persisted` is true when it comes back from the back-forward cache,
// the case the script undoes a form's busy state for.
function pageshow( log, persisted ) {
	log.windowListeners.filter( ( l ) => 'pageshow' === l.type ).forEach( ( l ) => l.fn( { type: 'pageshow', persisted: !! persisted } ) );
}

// Dispatch an event at `target` through the capture, target and bubble phases.
function dispatch( target, type, init ) {
	const event = Object.assign( {
		type,
		target,
		defaultPrevented: false,
		stopped: false,
		stoppedNow: false,
		preventDefault() {
			this.defaultPrevented = true;
		},
		stopPropagation() {
			this.stopped = true;
		},
		stopImmediatePropagation() {
			this.stopped = true;
			this.stoppedNow = true;
		},
	}, init || {} );
	const ancestors = [];

	for ( let node = target.parentNode; node; node = node.parentNode ) {
		ancestors.push( node );
	}

	const run = ( node, capture ) => {
		if ( event.stopped ) {
			return;
		}

		node.listeners.slice()
			.filter( ( l ) => l.type === type && l.capture === capture )
			.forEach( ( l ) => {
				if ( event.stoppedNow ) {
					return;
				}

				event.currentTarget = node;
				l.fn( event );
			} );
	};

	ancestors.slice().reverse().forEach( ( node ) => run( node, true ) );
	run( target, true );
	run( target, false );
	ancestors.forEach( ( node ) => run( node, false ) );

	return event;
}

module.exports = { El, mk, load, flush, pageshow, dispatch, reset, registry, doc };
