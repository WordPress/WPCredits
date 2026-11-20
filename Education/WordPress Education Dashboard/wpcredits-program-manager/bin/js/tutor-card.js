'use strict';
/*
 * assets/js/tutor.js, run in node: what the AI Mentor card does with a message and with each reply.
 *
 *   php bin/test-tutor.php
 *
 * which draws the card's panel with the real WPCPM_Tutor_Card, then runs this file with that markup and the
 * page's script data on its standard input, as JSON (`page`, the panel with thirty-two turns and every
 * message of the day left; `last`, the same panel with one message left; `config`, what wp_localize_script()
 * hands the page):
 *
 *   node bin/js/tutor-card.js <path to tutor.js>
 *
 * The markup is parsed into a stand-in for the page: elements that keep attributes, children, text and
 * listeners, `hidden` and `disabled` read from their attributes, and an innerHTML that takes only '' (any
 * markup thrown, so a script that wrote some would fail here). fetch and the timers are the harness's,
 * answered by hand, and AbortController is node's own.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a summary
 * line, and exits 1 when any scenario differs. The last scenario is a mutation proof: the script made to
 * draw a reply from its `html` must go wrong on the scenario that pins drawing from its text.
 */
const fs = require( 'fs' );
const vm = require( 'vm' );

const SCRIPT = process.argv[ 2 ];
const input = JSON.parse( fs.readFileSync( 0, 'utf8' ) );

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
				console.log( '       exp: ' + JSON.stringify( want ) );
				console.log( '       got: ' + JSON.stringify( got ) );
			}
		} );
}

/* ---- The page ---- */

class Text {
	constructor( data ) {
		this.nodeType = 3;
		this.data = String( data );
		this.parentNode = null;
	}

	get textContent() {
		return this.data;
	}
}

const BOOLEAN = [ 'hidden', 'disabled' ];
const REFLECT = { id: 'id', className: 'class', type: 'type', href: 'href', target: 'target', rel: 'rel', rows: 'rows', maxLength: 'maxlength', tabIndex: 'tabindex', htmlFor: 'for' };

class Element {
	constructor( doc, tag ) {
		this.nodeType = 1;
		this.ownerDocument = doc;
		this.tagName = String( tag ).toUpperCase();
		this.attributes = {};
		this.childNodes = [];
		this.parentNode = null;
		this.listeners = {};
		this.value = '';
		this.scrollTop = 0;
		this.scrollHeight = 1000;

		BOOLEAN.forEach( ( name ) => {
			Object.defineProperty( this, name, {
				get: () => this.hasAttribute( name ),
				set: ( on ) => ( on ? this.setAttribute( name, '' ) : this.removeAttribute( name ) ),
			} );
		} );

		Object.keys( REFLECT ).forEach( ( prop ) => {
			Object.defineProperty( this, prop, {
				get: () => {
					const value = this.getAttribute( REFLECT[ prop ] );

					return 'maxLength' === prop || 'rows' === prop || 'tabIndex' === prop ? ( null === value ? -1 : Number( value ) ) : ( null === value ? '' : value );
				},
				set: ( value ) => this.setAttribute( REFLECT[ prop ], String( value ) ),
			} );
		} );
	}

	get children() {
		return this.childNodes.filter( ( node ) => 1 === node.nodeType );
	}

	get firstChild() {
		return this.childNodes[ 0 ] || null;
	}

	get textContent() {
		return this.childNodes.map( ( node ) => node.textContent ).join( '' );
	}

	set textContent( value ) {
		this.childNodes.forEach( ( node ) => {
			node.parentNode = null;
		} );
		this.childNodes = [];

		if ( '' !== String( value ) ) {
			this.appendChild( new Text( value ) );
		}
	}

	set innerHTML( value ) {
		if ( '' !== value ) {
			throw new Error( 'innerHTML was given markup: ' + value );
		}

		this.textContent = '';
	}

	get innerHTML() {
		throw new Error( 'innerHTML was read' );
	}

	getAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attributes, name ) ? this.attributes[ name ] : null;
	}

	setAttribute( name, value ) {
		this.attributes[ name ] = String( value );
	}

	hasAttribute( name ) {
		return Object.prototype.hasOwnProperty.call( this.attributes, name );
	}

	removeAttribute( name ) {
		delete this.attributes[ name ];
	}

	appendChild( node ) {
		return this.insertBefore( node, null );
	}

	insertBefore( node, reference ) {
		if ( node.parentNode ) {
			node.parentNode.removeChild( node );
		}

		const at = reference ? this.childNodes.indexOf( reference ) : -1;

		this.childNodes.splice( at < 0 ? this.childNodes.length : at, 0, node );
		node.parentNode = this;

		return node;
	}

	removeChild( node ) {
		this.childNodes.splice( this.childNodes.indexOf( node ), 1 );
		node.parentNode = null;

		return node;
	}

	remove() {
		if ( this.parentNode ) {
			this.parentNode.removeChild( this );
		}
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

	// One compound selector: a tag, `.class` and `[attribute]`, together.
	is( compound ) {
		const bits = compound.match( /[a-z0-9]+|\.[a-z0-9_-]+|\[[a-z0-9_-]+\]/gi ) || [];

		return bits.join( '' ) === compound && bits.every( ( bit ) => {
			if ( '.' === bit[ 0 ] ) {
				return this.className.split( /\s+/ ).indexOf( bit.slice( 1 ) ) !== -1;
			}

			if ( '[' === bit[ 0 ] ) {
				return this.hasAttribute( bit.slice( 1, -1 ) );
			}

			return this.tagName === bit.toUpperCase();
		} );
	}

	// The selectors the script and the scenarios use: compounds, a descendant of another, in a list.
	matches( selector ) {
		return selector.split( ',' ).some( ( part ) => {
			const compounds = part.trim().split( /\s+/ );

			if ( ! this.is( compounds.pop() ) ) {
				return false;
			}

			let node = this.parentNode;

			while ( compounds.length && node && 1 === node.nodeType ) {
				if ( node.is( compounds[ compounds.length - 1 ] ) ) {
					compounds.pop();
				}

				node = node.parentNode;
			}

			return ! compounds.length;
		} );
	}

	querySelectorAll( selector ) {
		return this.all().filter( ( node ) => node.matches( selector ) );
	}

	querySelector( selector ) {
		return this.querySelectorAll( selector )[ 0 ] || null;
	}

	closest( selector ) {
		let node = this;

		while ( node && 1 === node.nodeType ) {
			if ( node.matches( selector ) ) {
				return node;
			}

			node = node.parentNode;
		}

		return null;
	}

	contains( node ) {
		return node === this || this.all().indexOf( node ) !== -1;
	}

	addEventListener( type, listener ) {
		( this.listeners[ type ] = this.listeners[ type ] || [] ).push( listener );
	}

	// An event at this element, then up through its ancestors, as a click and a submit bubble.
	dispatch( type ) {
		const event = {
			type,
			target: this,
			defaultPrevented: false,
			preventDefault() {
				this.defaultPrevented = true;
			},
		};
		let node = this;

		while ( node && 1 === node.nodeType ) {
			( node.listeners[ type ] || [] ).forEach( ( listener ) => listener.call( node, event ) );
			node = node.parentNode;
		}

		return event;
	}

	focus() {
		if ( ! this.disabled ) {
			this.ownerDocument.activeElement = this;
		}
	}
}

function decode( text ) {
	return text.replace( /&(#0*39|#0*38|#\d+|amp|lt|gt|quot);/g, ( all, name ) => {
		const named = { amp: '&', lt: '<', gt: '>', quot: '"' };

		if ( named[ name ] ) {
			return named[ name ];
		}

		return String.fromCharCode( Number( name.replace( /^#0*/, '' ) ) );
	} );
}

// The server's own markup, which is well formed: tags, quoted attributes, bare attributes, text.
function parse( html, doc, parent ) {
	const voids = [ 'br', 'input', 'img', 'hr' ];
	const stack = [ parent ];
	const tokens = /<!--[\s\S]*?-->|<\/([a-z0-9]+)\s*>|<([a-z0-9]+)((?:\s+[^\s=>/]+(?:="[^"]*")?)*)\s*\/?>|([^<]+)/gi;
	let match;

	while ( ( match = tokens.exec( html ) ) ) {
		if ( match[ 1 ] ) {
			const open = stack.pop();

			if ( ! open || open.tagName !== match[ 1 ].toUpperCase() ) {
				throw new Error( 'the markup closes </' + match[ 1 ] + '> where ' + ( open ? open.tagName : 'nothing' ) + ' is open' );
			}
		} else if ( match[ 2 ] ) {
			const element = doc.createElement( match[ 2 ] );
			const pairs = /([^\s=>/]+)(?:="([^"]*)")?/g;
			let pair;

			while ( ( pair = pairs.exec( match[ 3 ] || '' ) ) ) {
				element.attributes[ pair[ 1 ] ] = undefined === pair[ 2 ] ? '' : decode( pair[ 2 ] );
			}

			stack[ stack.length - 1 ].appendChild( element );

			if ( voids.indexOf( match[ 2 ].toLowerCase() ) === -1 ) {
				stack.push( element );
			}
		} else if ( match[ 4 ] ) {
			stack[ stack.length - 1 ].appendChild( new Text( decode( match[ 4 ] ) ) );
		}
	}

	if ( 1 !== stack.length ) {
		throw new Error( 'the markup leaves ' + stack[ stack.length - 1 ].tagName + ' open' );
	}
}

/**
 * The card on a page, the script run on it, and the means to type, press and answer.
 *
 * @param {string} html   The panel, as the server drew it.
 * @param {string} source The script.
 * @return {object}
 */
function build( html, source ) {
	const doc = {
		activeElement: null,
		createElement: ( tag ) => new Element( doc, tag ),
		createTextNode: ( data ) => new Text( data ),
		querySelector: ( selector ) => root.querySelector( selector ),
		querySelectorAll: ( selector ) => root.querySelectorAll( selector ),
		getElementById: ( id ) => root.all().filter( ( node ) => node.id === id )[ 0 ] || null,
		contains: ( node ) => root.contains( node ),
	};
	const root = new Element( doc, 'body' );
	const page = { doc, root, requests: [], timers: [] };

	doc.body = root;
	doc.activeElement = root;
	parse( html, doc, root );

	const window = {
		wpcpmTutor: JSON.parse( JSON.stringify( input.config ) ),
		document: doc,
		setTimeout: ( fn, ms ) => {
			page.timers.push( { id: page.timers.length + 1, fn, ms, cleared: false, ran: false } );

			return page.timers.length;
		},
		clearTimeout: ( id ) => {
			page.timers.filter( ( timer ) => timer.id === id ).forEach( ( timer ) => {
				timer.cleared = true;
			} );
		},
		fetch: ( url, options ) => new Promise( ( resolve, reject ) => {
			const request = { url, options, body: JSON.parse( options.body ), resolve, reject };

			page.requests.push( request );

			if ( options.signal ) {
				options.signal.addEventListener( 'abort', () => {
					const error = new Error( 'The operation was aborted.' );

					error.name = 'AbortError';
					reject( error );
				} );
			}
		} ),
	};

	vm.runInNewContext( source, { window, document: doc, AbortController, console } );

	page.q = ( selector ) => root.querySelector( selector );
	page.qa = ( selector ) => root.querySelectorAll( selector );
	page.box = page.q( '[data-wpcpm-tutor-input]' );
	page.send = page.q( '[data-wpcpm-tutor-send]' );
	page.form = page.q( '[data-wpcpm-tutor-form]' );
	page.turns = () => page.q( '[data-wpcpm-tutor-turns]' ).children;
	page.last = () => page.turns()[ page.turns().length - 1 ];

	// What a press of Send does in a browser: nothing while the button is disabled, else the form submits.
	page.press = ( text ) => {
		if ( undefined !== text ) {
			page.box.value = text;
		}

		if ( ! page.send.disabled ) {
			page.form.dispatch( 'submit' );
		}
	};
	page.click = ( element ) => {
		if ( element && ! element.disabled ) {
			element.dispatch( 'click' );
		}
	};
	page.answer = ( request, status, json ) => request.resolve( { ok: status >= 200 && status < 300, status, json: () => Promise.resolve( json ) } );
	page.fire = ( ms ) => page.timers.filter( ( timer ) => timer.ms === ms && ! timer.cleared && ! timer.ran ).forEach( ( timer ) => {
		timer.ran = true;
		timer.fn();
	} );
	page.live = () => page.timers.filter( ( timer ) => ! timer.cleared && ! timer.ran ).length;
	page.shown = ( selector ) => {
		const element = page.q( selector );

		return element ? ! element.hidden : null;
	};

	return page;
}

const settle = async () => {
	for ( let i = 0; i < 6; i++ ) {
		await new Promise( ( resolve ) => setImmediate( resolve ) );
	}
};

// A paragraph's lines as the script drew them: each text node's text, each element's tag.
function lines( element ) {
	return element ? element.childNodes.map( ( node ) => ( 3 === node.nodeType ? node.data : node.tagName ) ) : null;
}

function textOf( element ) {
	return element ? element.textContent : null;
}

function links( element ) {
	return element ? element.querySelectorAll( 'a' ).map( ( a ) => ( { href: a.href, text: a.textContent, target: a.target, rel: a.rel } ) ) : null;
}

function reply( text, extra ) {
	return Object.assign( { text, html: '<p>' + text + '</p>', sources: [], unsourced: false, handoff: false, fallback: false, left: 29 }, extra || {} );
}

const source = fs.existsSync( SCRIPT ) ? fs.readFileSync( SCRIPT, 'utf8' ) : '';
const config = input.config;
const LEFT = ( left ) => config.strings.left.replace( '%1$d', left ).replace( '%2$d', config.turns );
const LIMIT = "You have used today's 30 messages. The AI Mentor is back tomorrow. For anything urgent, ask your mentor or the WordPress Credits Slack channel.";
const GUIDE = { title: 'Student guide: Reports', link: 'https://example.test/student-guide/#reports' };

/** S: a reply drawn from its text, the scenario the mutation proof runs again. */
async function drawnFromText( script ) {
	const page = build( input.page, script );

	page.press( 'How do I send a report?' );
	page.answer( page.requests[ 0 ], 200, reply( 'First line\nSecond <b>line</b>', { html: '<p><img src="x" onerror="alert(1)"></p>' } ) );
	await settle();

	return {
		turns: page.turns().length,
		message: textOf( page.last().querySelector( '.wpcpm-tutor__message .wpcpm-tutor__text' ) ),
		reply: lines( page.last().querySelector( '.wpcpm-tutor__reply .wpcpm-tutor__text' ) ),
		tags: [ page.last().querySelector( '.wpcpm-tutor__message .wpcpm-tutor__text' ).tagName, page.last().querySelector( '.wpcpm-tutor__reply .wpcpm-tutor__text' ).tagName ],
		images: page.qa( 'img' ).length,
		left: textOf( page.q( '[data-wpcpm-tutor-left]' ) ),
		working: page.shown( '[data-wpcpm-tutor-working]' ),
		send: page.send.disabled,
		box: page.box.value,
		timers: page.live(),
	};
}

const DRAWN = {
	turns: 33,
	message: 'How do I send a report?',
	reply: [ 'First line\nSecond <b>line</b>' ],
	tags: [ 'PRE', 'PRE' ],
	images: 0,
	left: LEFT( 29 ),
	working: false,
	send: false,
	box: '',
	timers: 0,
};

/** A hand-off reply drawn, its preview ready. */
async function handedOff() {
	const page = build( input.page, source );

	page.press( 'Can my mentor check my patch?' );
	page.answer( page.requests[ 0 ], 200, reply( 'Your mentor can look at it.', { sources: [ GUIDE ], handoff: true, question: 'Can my mentor check my patch?' } ) );
	await settle();

	page.preview = page.last().querySelector( '.wpcpm-tutor__preview' );

	return page;
}

( async () => {
	await scenario( 'the conversation opens at its newest turn, and an empty box sends nothing', { scrolled: 1000, requests: 0, working: false, turns: 32 }, async () => {
		const page = build( input.page, source );
		const opened = page.q( '[data-wpcpm-tutor-log]' ).scrollTop;

		page.press( '  \n  ' );
		await settle();

		return { scrolled: opened, requests: page.requests.length, working: page.shown( '[data-wpcpm-tutor-working]' ), turns: page.turns().length };
	} );

	await scenario(
		'a message is trimmed, capped at the box\'s 1,200 characters and posted as JSON with the REST nonce, one at a time',
		{ requests: 1, url: config.endpoint, method: 'POST', credentials: 'same-origin', type: 'application/json', nonce: config.nonce, keys: [ 'message' ], length: 1200, shown: 1200, send: true, working: true, box: '', giveUp: 1 },
		async () => {
			const page = build( input.page, source );

			page.press( '  ' + 'é'.repeat( 1300 ) + '  ' );
			const cleared = page.box.value;

			// Send is disabled while the first is out; a submit that comes anyway sends nothing either.
			page.press( 'A second message while the first is out' );
			page.form.dispatch( 'submit' );
			await settle();

			const request = page.requests[ 0 ];

			return {
				requests: page.requests.length,
				url: request.url,
				method: request.options.method,
				credentials: request.options.credentials,
				type: request.options.headers[ 'Content-Type' ],
				nonce: request.options.headers[ 'X-WP-Nonce' ],
				keys: Object.keys( request.body ),
				length: Array.from( request.body.message ).length,
				shown: Array.from( textOf( page.last().querySelector( '.wpcpm-tutor__message .wpcpm-tutor__text' ) ) ).length,
				send: page.send.disabled,
				working: page.shown( '[data-wpcpm-tutor-working]' ),
				box: cleared,
				giveUp: page.timers.filter( ( timer ) => 150000 === timer.ms ).length,
			};
		}
	);

	await scenario( 'a reply is drawn from its text, in a pre with its line breaks, and never from its html', DRAWN, () => drawnFromText( source ) );

	await scenario(
		'an unsourced reply is marked "Not from the program\'s guides", and a sourced one lists its http sources as links',
		{
			unsourced: { mark: config.strings.unsourced, links: [] },
			sourced: { mark: null, title: config.strings.sources, links: [ { href: GUIDE.link, text: GUIDE.title, target: '_blank', rel: 'noopener noreferrer' } ] },
			left: LEFT( 28 ),
		},
		async () => {
			const page = build( input.page, source );

			page.press( 'First?' );
			page.answer( page.requests[ 0 ], 200, reply( 'From what I know.', { unsourced: true } ) );
			await settle();

			const first = page.last();

			page.press( 'Second?' );
			page.answer( page.requests[ 1 ], 200, reply( 'From the guide [1].', { sources: [ GUIDE, { title: 'Not a page', link: 'javascript:alert(1)' } ], left: 28 } ) );
			await settle();

			const second = page.last();

			return {
				unsourced: { mark: textOf( first.querySelector( '.wpcpm-tutor__unsourced' ) ), links: links( first.querySelector( '.wpcpm-tutor__reply' ) ) },
				sourced: { mark: textOf( second.querySelector( '.wpcpm-tutor__unsourced' ) ), title: textOf( second.querySelector( '.wpcpm-tutor__sources-title' ) ), links: links( second.querySelector( '.wpcpm-tutor__reply' ) ) },
				left: textOf( page.q( '[data-wpcpm-tutor-left]' ) ),
			};
		}
	);

	await scenario(
		'a reply\'s sources are a numbered list, each at its marker\'s number: one left out keeps the next at its own',
		{ tag: 'OL', className: 'wpcpm-tutor__sources', items: [ { value: null, text: GUIDE.title }, { value: '3', text: 'Student guide: Certificates' } ] },
		async () => {
			const page = build( input.page, source );

			page.press( 'Which guides?' );
			page.answer( page.requests[ 0 ], 200, reply( 'These [1] [2] [3].', { sources: [ GUIDE, { title: 'Not a page', link: 'javascript:alert(1)' }, { title: 'Student guide: Certificates', link: 'https://example.test/student-guide/#certificates' } ] } ) );
			await settle();

			const list = page.last().querySelector( '.wpcpm-tutor__sources' );

			return {
				tag: list ? list.tagName : null,
				className: list ? list.className : null,
				items: list ? list.children.map( ( item ) => ( { value: item.getAttribute( 'value' ), text: item.textContent } ) ) : null,
			};
		}
	);

	await scenario(
		'past the limit the box and Send are disabled, the limit sentence shown, and the message kept, with no turn',
		{ box: 'One more?', boxDisabled: true, send: true, limit: true, text: LIMIT, turns: 32, left: LEFT( 0 ), working: false, again: 1 },
		async () => {
			const page = build( input.page, source );

			page.press( 'One more?' );
			page.answer( page.requests[ 0 ], 200, { limit: true, text: LIMIT, left: 0 } );
			await settle();
			page.press();

			return {
				box: page.box.value,
				boxDisabled: page.box.disabled,
				send: page.send.disabled,
				limit: page.shown( '[data-wpcpm-tutor-limit]' ),
				text: textOf( page.q( '[data-wpcpm-tutor-limit]' ) ),
				turns: page.turns().length,
				left: textOf( page.q( '[data-wpcpm-tutor-left]' ) ),
				working: page.shown( '[data-wpcpm-tutor-working]' ),
				again: page.requests.length,
			};
		}
	);

	await scenario(
		'the day\'s last message is answered, then the box closes with the limit sentence the page holds',
		{ turns: 33, reply: [ 'Done for today.' ], boxDisabled: true, send: true, limit: true, text: LIMIT, left: LEFT( 0 ) },
		async () => {
			const page = build( input.last, source );

			page.press( 'The last one?' );
			page.answer( page.requests[ 0 ], 200, reply( 'Done for today.', { left: 0 } ) );
			await settle();

			return {
				turns: page.turns().length,
				reply: lines( page.last().querySelector( '.wpcpm-tutor__reply .wpcpm-tutor__text' ) ),
				boxDisabled: page.box.disabled,
				send: page.send.disabled,
				limit: page.shown( '[data-wpcpm-tutor-limit]' ),
				text: textOf( page.q( '[data-wpcpm-tutor-limit]' ) ),
				left: textOf( page.q( '[data-wpcpm-tutor-left]' ) ),
			};
		}
	);

	await scenario(
		'an error reply shows the failure line, takes the turn back and keeps the message for another try',
		{ failed: true, text: config.strings.failed, turns: 32, box: 'Hello?', send: false, working: false, timers: 0, again: { requests: 2, failed: false } },
		async () => {
			const page = build( input.page, source );

			page.press( 'Hello?' );
			page.answer( page.requests[ 0 ], 500, { code: 'internal_server_error', message: 'There has been a critical error.' } );
			await settle();

			const got = {
				failed: page.shown( '[data-wpcpm-tutor-failed]' ),
				text: textOf( page.q( '[data-wpcpm-tutor-failed]' ) ),
				turns: page.turns().length,
				box: page.box.value,
				send: page.send.disabled,
				working: page.shown( '[data-wpcpm-tutor-working]' ),
				timers: page.live(),
			};

			page.press();
			got.again = { requests: page.requests.length, failed: page.shown( '[data-wpcpm-tutor-failed]' ) };

			return got;
		}
	);

	await scenario( 'a network failure shows the same line', { failed: true, turns: 32, box: 'Hello?', send: false }, async () => {
		const page = build( input.page, source );

		page.press( 'Hello?' );
		page.requests[ 0 ].reject( new TypeError( 'Failed to fetch' ) );
		await settle();

		return { failed: page.shown( '[data-wpcpm-tutor-failed]' ), turns: page.turns().length, box: page.box.value, send: page.send.disabled };
	} );

	await scenario(
		'the card gives up after 150 seconds, and an answer after that draws nothing',
		{ before: false, aborted: true, failed: true, box: 'Slow?', turns: 32, send: false, again: 2 },
		async () => {
			const page = build( input.page, source );

			page.press( 'Slow?' );
			page.fire( 149999 );
			const before = page.shown( '[data-wpcpm-tutor-failed]' );

			page.fire( 150000 );
			await settle();
			page.answer( page.requests[ 0 ], 200, reply( 'Too late.' ) );
			await settle();

			const got = {
				before,
				aborted: page.requests[ 0 ].options.signal.aborted,
				failed: page.shown( '[data-wpcpm-tutor-failed]' ),
				box: page.box.value,
				turns: page.turns().length,
				send: page.send.disabled,
			};

			page.press();
			got.again = page.requests.length;

			return got;
		}
	);

	await scenario(
		'a hand-off reply draws R2\'s preview under it, holding the question the route gives, with a line to add',
		{ title: config.strings.previewTitle, question: 'Can my mentor check my patch?', label: config.strings.noteLabel, labelled: true, max: 600, buttons: [ config.strings.submit, config.strings.cancel ], under: true },
		async () => {
			const page = await handedOff();
			const note = page.preview.querySelector( 'textarea' );
			const label = page.preview.querySelector( 'label' );

			return {
				title: textOf( page.preview.querySelector( '.wpcpm-tutor__preview-title' ) ),
				question: textOf( page.preview.querySelector( '.wpcpm-tutor__preview-question' ) ),
				label: textOf( label ),
				labelled: '' !== note.id && label.htmlFor === note.id,
				max: note.maxLength,
				buttons: page.preview.querySelectorAll( 'button' ).map( ( button ) => button.textContent ),
				under: page.last().querySelector( '.wpcpm-tutor__reply' ).contains( page.preview ),
			};
		}
	);

	await scenario(
		'Send posts the question, the line and the source links to R2\'s route, once, and R2\'s sentence replaces the preview',
		{
			requests: 1,
			url: config.sendEndpoint,
			nonce: config.nonce,
			body: { question: 'Can my mentor check my patch?', note: 'Thanks!', links: [ GUIDE.link ] },
			sending: config.strings.sending,
			preview: false,
			sentence: 'Sent. Your mentor gets an email and can reply to you directly.',
			role: 'status',
		},
		async () => {
			const page = await handedOff();
			const submit = page.preview.querySelectorAll( 'button' )[ 0 ];

			page.preview.querySelector( 'textarea' ).value = '  Thanks!  ';
			page.click( submit );
			page.click( submit );
			submit.dispatch( 'click' );

			const asked = page.requests.slice( 1 );
			const sending = submit.textContent;

			page.answer( asked[ 0 ], 200, { state: 'sent', message: 'Sent. Your mentor gets an email and can reply to you directly.' } );
			await settle();

			const sent = page.last().querySelector( '.wpcpm-tutor__sent' );

			return {
				requests: asked.length,
				url: asked[ 0 ].url,
				nonce: asked[ 0 ].options.headers[ 'X-WP-Nonce' ],
				body: asked[ 0 ].body,
				sending,
				preview: null !== page.last().querySelector( '.wpcpm-tutor__preview' ),
				sentence: textOf( sent ),
				role: sent ? sent.getAttribute( 'role' ) : null,
			};
		}
	);

	await scenario(
		'the preview holds the question the route gives, R2\'s send() kept as it is, drawn and sent: never a cut of the box\'s own',
		( () => {
			const question = 'Is this ready? ' + 'b'.repeat( 385 );

			return { turn: 'Is <b>this</b> ready? ' + 'b'.repeat( 500 ), drawn: question, sent: question, same: true };
		} )(),
		async () => {
			const page = build( input.page, source );
			const message = 'Is <b>this</b> ready? ' + 'b'.repeat( 500 );

			page.press( message );
			page.answer( page.requests[ 0 ], 200, reply( 'Your mentor can look at it.', { handoff: true, question: 'Is this ready? ' + 'b'.repeat( 385 ) } ) );
			await settle();

			const preview = page.last().querySelector( '.wpcpm-tutor__preview' );
			const drawn = textOf( preview.querySelector( '.wpcpm-tutor__preview-question' ) );

			page.click( preview.querySelectorAll( 'button' )[ 0 ] );

			const sent = page.requests[ 1 ].body.question;

			return {
				turn: textOf( page.last().querySelector( '.wpcpm-tutor__message .wpcpm-tutor__text' ) ),
				drawn,
				sent,
				same: drawn === sent,
			};
		}
	);

	await scenario( 'a send refused without R2\'s sentence shows R2\'s refusal', config.strings.refused, async () => {
		const page = await handedOff();

		page.click( page.preview.querySelectorAll( 'button' )[ 0 ] );
		page.answer( page.requests[ 1 ], 403, { code: 'rest_forbidden', message: 'Sorry, you are not allowed to do that.' } );
		await settle();

		return textOf( page.last().querySelector( '.wpcpm-tutor__sent' ) );
	} );

	await scenario( 'Cancel dismisses the preview, and the reply stays', { preview: false, reply: [ 'Your mentor can look at it.' ], requests: 1 }, async () => {
		const page = await handedOff();

		page.click( page.preview.querySelectorAll( 'button' )[ 1 ] );

		return {
			preview: null !== page.last().querySelector( '.wpcpm-tutor__preview' ),
			reply: lines( page.last().querySelector( '.wpcpm-tutor__reply .wpcpm-tutor__text' ) ),
			requests: page.requests.length,
		};
	} );

	await scenario(
		'a send that fails keeps the preview, with the failure line, to send again',
		{ preview: true, failed: config.strings.failed, submit: config.strings.submit, enabled: [ false, false ], again: 3 },
		async () => {
			const page = await handedOff();
			const buttons = page.preview.querySelectorAll( 'button' );

			page.click( buttons[ 0 ] );
			page.requests[ 1 ].reject( new TypeError( 'Failed to fetch' ) );
			await settle();

			const got = {
				preview: null !== page.last().querySelector( '.wpcpm-tutor__preview' ),
				failed: textOf( page.preview.querySelector( '.wpcpm-tutor__preview-failed' ) ),
				submit: buttons[ 0 ].textContent,
				enabled: [ buttons[ 0 ].disabled, buttons[ 1 ].disabled ],
			};

			page.click( buttons[ 0 ] );
			got.again = page.requests.length;

			return got;
		}
	);

	await scenario( '"Show earlier" shows the older turns, goes away, and takes the keyboard to the first of them', { before: 2, after: 0, button: false, focus: true }, async () => {
		const page = build( input.page, source );
		const before = page.turns().filter( ( turn ) => turn.hidden ).length;

		page.click( page.q( '[data-wpcpm-tutor-earlier]' ) );

		return {
			before,
			after: page.turns().filter( ( turn ) => turn.hidden ).length,
			button: null !== page.q( '[data-wpcpm-tutor-earlier]' ),
			focus: page.doc.activeElement === page.turns()[ 0 ],
		};
	} );

	await scenario(
		'a nothing answer links the Slack channel, and only a channel on http',
		{ linked: [ { href: 'https://example.test/slack/student', text: 'Ask in the WordPress Credits Slack channel', target: '_blank', rel: 'noopener noreferrer' } ], unlinked: [] },
		async () => {
			const page = build( input.page, source );
			const nothing = { fallback: true, nothing: true, channel: { url: 'https://example.test/slack/student', label: 'Ask in the WordPress Credits Slack channel' } };

			page.press( 'Something the guides never say?' );
			page.answer( page.requests[ 0 ], 200, reply( 'Nothing in this site\'s guides answers that.', nothing ) );
			await settle();

			const linked = links( page.last().querySelector( '.wpcpm-tutor__reply' ) );

			page.press( 'And again?' );
			page.answer( page.requests[ 1 ], 200, reply( 'Nothing again.', Object.assign( {}, nothing, { channel: { url: 'javascript:alert(1)', label: 'Not a channel' } } ) ) );
			await settle();

			return { linked, unlinked: links( page.last().querySelector( '.wpcpm-tutor__reply' ) ) };
		}
	);

	await scenario(
		'the working line shows its words, which the server leaves for the script (core would texturize "..." in them)',
		{ before: '', after: 'The AI Mentor is thinking...', shown: true },
		async () => {
			const page = build( input.page, source );
			const before = textOf( page.q( '[data-wpcpm-tutor-working]' ) );

			page.press( 'Anything?' );

			return { before, after: textOf( page.q( '[data-wpcpm-tutor-working]' ) ), shown: page.shown( '[data-wpcpm-tutor-working]' ) };
		}
	);

	await scenario(
		'a message and a reply keep a "--", a "..." and a "- " line as typed, in a pre each, as the server draws them after a reload',
		{ message: [ 'PRE', '<!-- wp:paragraph -->\n--force...' ], reply: [ 'PRE', 'Use --force\n- one -- two\nWait...' ] },
		async () => {
			const page = build( input.page, source );

			page.press( '<!-- wp:paragraph -->\r\n--force...' );
			page.answer( page.requests[ 0 ], 200, reply( 'Use --force\r\n- one -- two\nWait...' ) );
			await settle();

			const message = page.last().querySelector( '.wpcpm-tutor__message .wpcpm-tutor__text' );
			const answer = page.last().querySelector( '.wpcpm-tutor__reply .wpcpm-tutor__text' );

			return { message: [ message.tagName, message.textContent ], reply: [ answer.tagName, answer.textContent ] };
		}
	);

	await scenario(
		'a source with an extract draws it under its link, in a pre of its own class, as the server does; one without draws none',
		{ extracts: [ [ 'PRE', 'Send it -- then wait...' ], null ], links: 2 },
		async () => {
			const page = build( input.page, source );

			page.press( 'Which guide?' );
			page.answer(
				page.requests[ 0 ],
				200,
				reply( 'Here are parts of the guides.', { fallback: true, sources: [ Object.assign( {}, GUIDE, { extract: 'Send it -- then wait...' } ), { title: 'Second', link: 'https://example.test/second/' } ] } )
			);
			await settle();

			const items = page.last().querySelectorAll( '.wpcpm-tutor__sources li' );

			return {
				extracts: items.map( ( item ) => {
					const extract = item.querySelector( '.wpcpm-tutor__extract' );

					return extract ? [ extract.tagName, extract.textContent ] : null;
				} ),
				links: page.last().querySelectorAll( '.wpcpm-tutor__sources a' ).length,
			};
		}
	);

	await scenario( 'mutation proof: the script drawing a reply from its html goes wrong on the scenario above', { mutated: true, differs: true }, async () => {
		const mutated = source.replace( /data\.text/g, 'data.html' );
		let got;

		try {
			got = await drawnFromText( mutated );
		} catch ( error ) {
			got = 'threw: ' + error.message;
		}

		return { mutated: mutated !== source, differs: JSON.stringify( got ) !== JSON.stringify( DRAWN ) };
	} );

	console.log( ran + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
} )();
