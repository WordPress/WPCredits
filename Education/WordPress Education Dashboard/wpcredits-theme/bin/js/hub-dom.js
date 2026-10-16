'use strict';
/*
 * A stand-in for the page, for running the hub's view scripts in node (bin/test-hub-js.php).
 *
 * The server's own markup is read into a small tree of elements that keep their attributes, classes,
 * children and listeners, and fire events that bubble, as a browser's do. It knows the selectors the
 * hub's scripts ask for (a tag, an ID, a class, an attribute present, equal to, starting with or
 * holding a value, and the descendant and child combinators), focus, the address's hash and its
 * hashchange, and what pressing Enter or Space on a button does: it clicks it. Nothing is laid out:
 * `hidden` is an attribute, as the scripts read it. Where an element lies on the screen is what a
 * scenario says: `element.box = { top, bottom, left, width }`, read by `getBoundingClientRect()`,
 * against the window's `innerHeight` (800); an element with no box lies in view, with no width. A
 * custom property a script sets on an element's `style` is kept, to be read back.
 */

const VOID = new Set( [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr' ] );

/**
 * Text with its character references read, as a browser reads them.
 *
 * @param {string} text Raw text.
 * @return {string} The text.
 */
function decode( text ) {
	const named = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };

	return String( text ).replace( /&(#x[0-9a-f]+|#[0-9]+|[a-z]+);/gi, ( whole, name ) => {
		if ( '#' === name[ 0 ] ) {
			return String.fromCodePoint( 'x' === name[ 1 ].toLowerCase() ? parseInt( name.slice( 2 ), 16 ) : parseInt( name.slice( 1 ), 10 ) );
		}

		return Object.prototype.hasOwnProperty.call( named, name.toLowerCase() ) ? named[ name.toLowerCase() ] : whole;
	} );
}

class Node {
	constructor( document ) {
		this.ownerDocument = document;
		this.parentNode = null;
		this.childNodes = [];
	}

	get firstChild() {
		return this.childNodes[ 0 ] || null;
	}

	get textContent() {
		return this.childNodes.map( ( child ) => child.textContent ).join( '' );
	}

	appendChild( child ) {
		if ( child.parentNode ) {
			child.parentNode.removeChild( child );
		}

		child.parentNode = this;
		this.childNodes.push( child );

		return child;
	}

	removeChild( child ) {
		const at = this.childNodes.indexOf( child );

		if ( -1 !== at ) {
			this.childNodes.splice( at, 1 );
			child.parentNode = null;
		}

		return child;
	}

	replaceChild( fresh, old ) {
		const at = this.childNodes.indexOf( old );

		if ( fresh.parentNode ) {
			fresh.parentNode.removeChild( fresh );
		}

		this.childNodes[ at ] = fresh;
		fresh.parentNode = this;
		old.parentNode = null;

		return old;
	}

	contains( other ) {
		for ( let node = other; node; node = node.parentNode ) {
			if ( node === this ) {
				return true;
			}
		}

		return false;
	}
}

class Text extends Node {
	constructor( document, text ) {
		super( document );
		this.nodeType = 3;
		this.nodeValue = text;
	}

	get textContent() {
		return this.nodeValue;
	}
}

class Element extends Node {
	constructor( document, tag ) {
		super( document );
		this.nodeType = 1;
		this.tagName = tag.toUpperCase();
		this.attributes = new Map();
		this.listeners = {};
		this.textWrites = 0;
		this.box = null;
		this.style = {
			props: {},
			setProperty( name, value ) {
				this.props[ name ] = String( value );
			},
			getPropertyValue( name ) {
				return undefined === this.props[ name ] ? '' : this.props[ name ];
			},
		};
	}

	getAttribute( name ) {
		return this.attributes.has( name ) ? this.attributes.get( name ) : null;
	}

	setAttribute( name, value ) {
		this.attributes.set( name, String( value ) );
	}

	removeAttribute( name ) {
		this.attributes.delete( name );
	}

	hasAttribute( name ) {
		return this.attributes.has( name );
	}

	get id() {
		return this.getAttribute( 'id' ) || '';
	}

	get className() {
		return this.getAttribute( 'class' ) || '';
	}

	set className( value ) {
		this.setAttribute( 'class', value );
	}

	get classList() {
		const element = this;
		const list = () => element.className.split( /\s+/ ).filter( Boolean );

		return {
			contains: ( name ) => list().includes( name ),
			add: ( name ) => {
				if ( ! list().includes( name ) ) {
					element.className = list().concat( name ).join( ' ' );
				}
			},
			remove: ( name ) => {
				element.className = list().filter( ( one ) => one !== name ).join( ' ' );
			},
		};
	}

	get hidden() {
		return this.hasAttribute( 'hidden' );
	}

	set hidden( value ) {
		if ( value ) {
			this.setAttribute( 'hidden', '' );
		} else {
			this.removeAttribute( 'hidden' );
		}
	}

	get type() {
		return this.getAttribute( 'type' ) || ( 'BUTTON' === this.tagName ? 'submit' : '' );
	}

	set type( value ) {
		this.setAttribute( 'type', value );
	}

	get hash() {
		const href = this.getAttribute( 'href' ) || '';
		const at = href.indexOf( '#' );

		return -1 === at ? '' : href.slice( at );
	}

	get children() {
		return this.childNodes.filter( ( child ) => 1 === child.nodeType );
	}

	get textContent() {
		return super.textContent;
	}

	set textContent( value ) {
		this.childNodes.forEach( ( child ) => {
			child.parentNode = null;
		} );
		this.childNodes = [];
		this.textWrites++;

		if ( '' !== String( value ) ) {
			this.appendChild( new Text( this.ownerDocument, String( value ) ) );
		}
	}

	matches( selector ) {
		return matchList( this, selector );
	}

	closest( selector ) {
		for ( let node = this; node && 1 === node.nodeType; node = node.parentNode ) {
			if ( node.matches( selector ) ) {
				return node;
			}
		}

		return null;
	}

	querySelectorAll( selector ) {
		const found = [];

		walk( this, ( node ) => {
			if ( node !== this && node.matches( selector ) ) {
				found.push( node );
			}
		} );

		return found;
	}

	querySelector( selector ) {
		return this.querySelectorAll( selector )[ 0 ] || null;
	}

	addEventListener( type, listener ) {
		( this.listeners[ type ] = this.listeners[ type ] || [] ).push( listener );
	}

	dispatchEvent( event ) {
		return dispatch( this, event );
	}

	focus() {
		this.ownerDocument.activeElement = this;
	}

	click( more ) {
		this.dispatchEvent( makeEvent( 'click', more ) );
	}

	/**
	 * Where the element lies, as a scenario set it with `box`; in view (the window is 800 tall) when
	 * it has not.
	 *
	 * @return {Object} `top`, `bottom` and `height`, in pixels from the window's top, and `left`,
	 *                  `width` and `right`, from its left edge (0 wide when the scenario says nothing).
	 */
	getBoundingClientRect() {
		const box = this.box || { top: 120, bottom: 420 };
		const left = box.left || 0;
		const width = box.width || 0;

		return { top: box.top, bottom: box.bottom, height: box.bottom - box.top, left, width, right: left + width };
	}

	/**
	 * Scroll the element into view. The ID it was scrolled to is kept in `scrolledTo`, and the
	 * arguments it was called with in `scrollArgs`, so a scenario can see that none was given: no
	 * `behavior`, so a reader who asked for less motion gets none.
	 *
	 * @param {...*} args What the script passed.
	 */
	scrollIntoView( ...args ) {
		this.ownerDocument.scrolledTo.push( this.id );
		this.ownerDocument.scrollArgs.push( JSON.stringify( args ) );
	}
}

/**
 * Every element below a node, in document order, the node included.
 *
 * @param {Node}     node     Where to start.
 * @param {Function} callback Called with each element.
 */
function walk( node, callback ) {
	if ( 1 === node.nodeType ) {
		callback( node );
	}

	node.childNodes.slice().forEach( ( child ) => walk( child, callback ) );
}

/**
 * One compound selector, such as `button[data-filter="all"]`, against one element.
 *
 * @param {Element} element  The element.
 * @param {string}  compound The compound selector.
 * @return {boolean} Whether it matches.
 */
function matchCompound( element, compound ) {
	const parts = compound.match( /^[a-z0-9]+|#[\w-]+|\.[\w-]+|\[[^\]]+\]/gi ) || [];

	if ( parts.join( '' ) !== compound ) {
		throw new Error( 'The stand-in cannot read the selector "' + compound + '"' );
	}

	return parts.every( ( part ) => {
		if ( '#' === part[ 0 ] ) {
			return element.id === part.slice( 1 );
		}

		if ( '.' === part[ 0 ] ) {
			return element.classList.contains( part.slice( 1 ) );
		}

		if ( '[' === part[ 0 ] ) {
			const test = part.slice( 1, -1 ).match( /^([\w-]+)(?:([\^*]?=)"([^"]*)")?$/ );
			const value = element.getAttribute( test[ 1 ] );

			if ( null === value ) {
				return false;
			}

			if ( ! test[ 2 ] ) {
				return true;
			}

			return '=' === test[ 2 ] ? value === test[ 3 ] : '^=' === test[ 2 ] ? value.startsWith( test[ 3 ] ) : value.includes( test[ 3 ] );
		}

		return element.tagName === part.toUpperCase();
	} );
}

/**
 * A complex selector, compounds joined by descendant or child combinators, against one element.
 *
 * @param {Element} element  The element.
 * @param {string}  selector The selector.
 * @return {boolean} Whether it matches.
 */
function matchComplex( element, selector ) {
	const tokens = selector.trim().replace( /\s*>\s*/g, ' > ' ).split( /\s+/ );

	const match = ( node, at ) => {
		if ( ! node || 1 !== node.nodeType || ! matchCompound( node, tokens[ at ] ) ) {
			return false;
		}

		if ( 0 === at ) {
			return true;
		}

		if ( '>' === tokens[ at - 1 ] ) {
			return match( node.parentNode, at - 2 );
		}

		for ( let up = node.parentNode; up && 1 === up.nodeType; up = up.parentNode ) {
			if ( match( up, at - 1 ) ) {
				return true;
			}
		}

		return false;
	};

	return match( element, tokens.length - 1 );
}

/**
 * A selector list against one element.
 *
 * @param {Element} element  The element.
 * @param {string}  selector Selectors, comma-separated.
 * @return {boolean} Whether any matches.
 */
function matchList( element, selector ) {
	return selector.split( ',' ).some( ( one ) => matchComplex( element, one ) );
}

/**
 * An event, as the scripts read one.
 *
 * @param {string} type The event's type.
 * @param {Object} more What else it carries, such as `key`, or a modifier key held (`ctrlKey`).
 * @return {Object} The event.
 */
function makeEvent( type, more ) {
	return Object.assign(
		{
			type,
			bubbles: true,
			ctrlKey: false,
			metaKey: false,
			shiftKey: false,
			altKey: false,
			defaultPrevented: false,
			stopped: false,
			preventDefault() {
				this.defaultPrevented = true;
			},
			stopPropagation() {
				this.stopped = true;
			},
		},
		more || {}
	);
}

/**
 * Fire an event at an element, then up through its ancestors to the document, as a browser bubbles
 * it, and do what the browser does by default for a link followed: change the address's hash, unless
 * a modifier key is held, which sends the link to a new tab or window and leaves this page as it is.
 *
 * @param {Element} target The element.
 * @param {Object}  event  The event.
 * @return {boolean} Whether the default was not prevented.
 */
function dispatch( target, event ) {
	event.target = target;

	const path = [];

	for ( let node = target; node; node = node.parentNode ) {
		path.push( node );
	}

	for ( const node of path ) {
		event.currentTarget = node;

		( node.listeners[ event.type ] || [] ).slice().forEach( ( listener ) => listener.call( node, event ) );

		if ( event.stopped || ! event.bubbles ) {
			break;
		}
	}

	const elsewhere = event.ctrlKey || event.metaKey || event.shiftKey || event.altKey;

	if ( 'click' === event.type && ! event.defaultPrevented && ! elsewhere ) {
		const link = target.closest ? target.closest( 'a[href]' ) : null;

		if ( link && '#' === link.getAttribute( 'href' )[ 0 ] ) {
			target.ownerDocument.defaultView.navigate( link.getAttribute( 'href' ) );
		}
	}

	return ! event.defaultPrevented;
}

/**
 * Whether an element is shown: neither it nor any element around it is hidden.
 *
 * @param {Element} element The element.
 * @return {boolean} Whether it is shown.
 */
function shownOnPage( element ) {
	for ( let node = element; node && 1 === node.nodeType; node = node.parentNode ) {
		if ( node.hidden ) {
			return false;
		}
	}

	return true;
}

/**
 * Read markup into a tree under a body element.
 *
 * @param {Document} document The document.
 * @param {string}   html     The markup.
 * @return {Element} The body.
 */
function parse( document, html ) {
	const body = new Element( document, 'body' );
	const stack = [ body ];
	const pattern = /<!--[\s\S]*?-->|<\/([a-z0-9]+)\s*>|<([a-z0-9]+)((?:\s+[^\s"'>\/=]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'=<>`]+))?)*)\s*(\/?)>|([^<]+)/gi;
	let token;

	while ( null !== ( token = pattern.exec( html ) ) ) {
		const top = stack[ stack.length - 1 ];

		if ( token[ 1 ] ) {
			const name = token[ 1 ].toUpperCase();
			const at = stack.map( ( element ) => element.tagName ).lastIndexOf( name );

			if ( at > 0 ) {
				stack.length = at;
			}
		} else if ( token[ 2 ] ) {
			const element = new Element( document, token[ 2 ] );
			const attributes = /([^\s"'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
			let attribute;

			while ( null !== ( attribute = attributes.exec( token[ 3 ] || '' ) ) ) {
				const value = undefined !== attribute[ 2 ] ? attribute[ 2 ] : undefined !== attribute[ 3 ] ? attribute[ 3 ] : attribute[ 4 ];

				element.setAttribute( attribute[ 1 ].toLowerCase(), decode( undefined === value ? '' : value ) );
			}

			top.appendChild( element );

			if ( ! VOID.has( token[ 2 ].toLowerCase() ) && ! token[ 4 ] ) {
				stack.push( element );
			}
		} else if ( token[ 5 ] ) {
			top.appendChild( new Text( document, decode( token[ 5 ] ) ) );
		}
	}

	return body;
}

/**
 * A page holding some markup, with its window, at an address's hash.
 *
 * @param {string} html The body's markup.
 * @param {string} hash The address's hash when the page opens, such as `#programs`, or ''.
 * @return {Object} `document` and `window`.
 */
function page( html, hash ) {
	const document = {
		readyState: 'complete',
		activeElement: null,
		scrolledTo: [],
		scrollArgs: [],
		listeners: {},
		nodeType: 9,
		parentNode: null,
		childNodes: [],
		createElement( tag ) {
			return new Element( document, tag );
		},
		createTextNode( text ) {
			return new Text( document, text );
		},
		getElementById( id ) {
			let found = null;

			// A browser finds no element for an empty ID, and does not take the first one that has none.
			if ( '' === id ) {
				return null;
			}

			walk( document.body, ( node ) => {
				if ( ! found && node.id === id ) {
					found = node;
				}
			} );

			return found;
		},
		querySelectorAll( selector ) {
			return document.body.querySelectorAll( selector );
		},
		querySelector( selector ) {
			return document.body.querySelector( selector );
		},
		addEventListener( type, listener ) {
			( document.listeners[ type ] = document.listeners[ type ] || [] ).push( listener );
		},
	};

	const window = {
		document,
		innerHeight: 800,
		listeners: {},
		location: { hash: hash || '' },
		addEventListener( type, listener ) {
			( window.listeners[ type ] = window.listeners[ type ] || [] ).push( listener );
		},
		// A browser jumps to the target at once, if it is shown, and fires hashchange after.
		navigate( next ) {
			const target = document.getElementById( next.slice( 1 ) );

			if ( target && shownOnPage( target ) ) {
				document.scrolledTo.push( target.id );
			}

			if ( next !== window.location.hash ) {
				window.location.hash = next;
				( window.listeners.hashchange || [] ).forEach( ( listener ) => listener( makeEvent( 'hashchange' ) ) );
			}
		},
	};

	document.defaultView = window;
	document.body = parse( document, html );
	document.body.parentNode = document;

	return { document, window };
}

/**
 * Press a key on the element that has the focus: a keydown, and for Enter or Space on a button the
 * click a browser makes of it.
 *
 * @param {Object} where The page.
 * @param {string} key   The key, such as 'Enter' or ' '.
 */
function pressKey( where, key ) {
	const element = where.document.activeElement;

	if ( ! element ) {
		throw new Error( 'Nothing has the focus.' );
	}

	const event = makeEvent( 'keydown', { key } );

	element.dispatchEvent( event );

	if ( ! event.defaultPrevented && 'BUTTON' === element.tagName && ( 'Enter' === key || ' ' === key ) ) {
		element.click();
	}
}

module.exports = { page, pressKey, makeEvent };
