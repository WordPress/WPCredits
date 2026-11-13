/**
 * The Administrator guide's list of sections (1.123.70), and the list of program updates beside each one
 * (1.123.71), which shares its layout: there every link is to another page, and the list only folds.
 *
 * Marks the part of the guide being read, opens only that section's parts, keeps the mark in view inside
 * the list when the list is taller than the window, and folds the list behind its heading when the main
 * area is too narrow for its own column (the stylesheet says when, through `--wpcpm-guide-fold`). Without
 * this script the whole list is shown, every entry a plain link.
 *
 * The guides' clarity release adds, on the guide's own page: a link into a folded part of the reference
 * opens it (parts hidden "until found", where the browser supports that), two buttons to open or close every part, and a search box over the guide's text. Every string
 * comes from the page (`wpcpmGuideStrings`); without it, or without the box, none of this runs.
 */
( function () {
	'use strict';

	// A table too wide for the guide's column scrolls inside its box, and a box that scrolls has to be
	// reachable from the keyboard: it is a stop, named for a screen reader, only while it scrolls.
	var guide = document.querySelector( '.wpcpm-guide' );
	var label = guide ? guide.getAttribute( 'data-wpcpm-table-label' ) : '';
	var tables = Array.prototype.slice.call( document.querySelectorAll( '.wpcpm-guide__body .wp-block-table' ) );

	function scrollers() {
		tables.forEach( function ( box ) {
			if ( box.scrollWidth > box.clientWidth + 1 ) {
				box.setAttribute( 'tabindex', '0' );
				box.setAttribute( 'role', 'region' );
				box.setAttribute( 'aria-label', label );
			} else if ( box.hasAttribute( 'tabindex' ) ) {
				box.removeAttribute( 'tabindex' );
				box.removeAttribute( 'role' );
				box.removeAttribute( 'aria-label' );
			}
		} );
	}

	if ( tables.length ) {
		scrollers();
		window.addEventListener( 'resize', scrollers );
	}

	var strings = window.wpcpmGuideStrings || null;
	var body = document.querySelector( '.wpcpm-guide__body' );
	var HEADINGS = '.wpcpm-guide__fold-heading';

	// A part of the reference is folded only where the browser opens a part hidden "until found" when its
	// text is searched or linked to; elsewhere the reference stays open, its headings plain.
	var canFold = !! ( body && strings && 'onbeforematch' in HTMLElement.prototype );

	function idOf( raw ) {
		try {
			return decodeURIComponent( raw );
		} catch ( e ) {
			return raw;
		}
	}

	function buttonOf( part ) {
		var heading = part.previousElementSibling;

		return heading ? heading.querySelector( '.wpcpm-guide__fold-toggle' ) : null;
	}

	// Opens or closes one part: its body and its heading's button, kept together.
	function setPart( part, open ) {
		var button = buttonOf( part );

		if ( open ) {
			part.removeAttribute( 'hidden' );
		} else {
			part.setAttribute( 'hidden', 'until-found' );
		}

		if ( button ) {
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	function parts() {
		return Array.prototype.slice.call( body.querySelectorAll( '.wpcpm-guide__fold-body' ) ).filter( function ( part ) {
			return buttonOf( part );
		} );
	}

	// The part an element is in, or is the heading of.
	function partOf( element ) {
		var inside = element ? element.closest( '.wpcpm-guide__fold-body' ) : null;
		var heading = element ? element.closest( HEADINGS ) : null;

		return inside || ( heading ? heading.nextElementSibling : null );
	}

	// Opens the part an element is in (a fold's own heading counts as in it), and says whether it opened one.
	function openAround( element ) {
		var part = canFold ? partOf( element ) : null;

		if ( ! part || ! part.hasAttribute( 'hidden' ) ) {
			return false;
		}

		setPart( part, true );

		return true;
	}

	function targetOf( href ) {
		if ( ! href || '#' !== href.charAt( 0 ) || href.length < 2 ) {
			return null;
		}

		return document.getElementById( idOf( href.slice( 1 ) ) );
	}

	if ( canFold ) {
		Array.prototype.forEach.call( body.querySelectorAll( HEADINGS ), function ( heading ) {
			var part = heading.nextElementSibling;

			if ( ! part || ! part.classList.contains( 'wpcpm-guide__fold-body' ) ) {
				return;
			}

			var button = document.createElement( 'button' );

			button.type = 'button';
			button.className = 'wpcpm-guide__fold-toggle';

			while ( heading.firstChild ) {
				button.appendChild( heading.firstChild );
			}

			heading.appendChild( button );
			button.addEventListener( 'click', function () {
				setPart( part, part.hasAttribute( 'hidden' ) );
			} );
			setPart( part, false );
		} );

		// The browser opened a part itself (find in page, a fragment): its button says so.
		document.addEventListener( 'beforematch', function ( event ) {
			var button = event.target.classList && event.target.classList.contains( 'wpcpm-guide__fold-body' ) ? buttonOf( event.target ) : null;

			if ( button ) {
				button.setAttribute( 'aria-expanded', 'true' );
			}
		} );

		// On paper every part is open; afterwards the ones that were closed close again.
		var closedForPrint = [];

		window.addEventListener( 'beforeprint', function () {
			closedForPrint = parts().filter( function ( part ) {
				return part.hasAttribute( 'hidden' );
			} );
			closedForPrint.forEach( function ( part ) {
				setPart( part, true );
			} );
		} );

		window.addEventListener( 'afterprint', function () {
			closedForPrint.forEach( function ( part ) {
				setPart( part, false );
			} );
			closedForPrint = [];
		} );
	}

	// A link into a folded part opens it before the browser scrolls; where the browser does that itself
	// nothing is lost, an open part stays open.
	if ( body ) {
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a[href^="#"]' ) : null;

			if ( link ) {
				openAround( targetOf( link.getAttribute( 'href' ) ) );
			}
		} );

		var arrive = function () {
			var target = targetOf( window.location.hash );

			if ( openAround( target ) ) {
				target.scrollIntoView();
			}
		};

		window.addEventListener( 'hashchange', arrive );
		arrive();
	}

	// "Open every part" and "Close every part", above the first folded part of the reference.
	var firstPart = canFold ? parts()[ 0 ] : null;

	if ( firstPart ) {
		var tools = document.createElement( 'p' );
		tools.className = 'wpcpm-guide__fold-tools';

		[ [ strings.open_all, true ], [ strings.close_all, false ] ].forEach( function ( pair ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'wpcpm-guide__fold-button';
			button.textContent = pair[ 0 ];
			button.addEventListener( 'click', function () {
				parts().forEach( function ( part ) {
					setPart( part, pair[ 1 ] );
				} );
			} );
			tools.appendChild( button );
		} );

		firstPart.parentNode.insertBefore( tools, firstPart.previousElementSibling );
	}

	// ---------- Search ----------

	var box = document.querySelector( '[data-wpcpm-guide-search]' );
	var input = box ? box.querySelector( 'input' ) : null;
	var status = box ? box.querySelector( '[role="status"]' ) : null;
	var results = box ? box.querySelector( 'ol' ) : null;
	var index = null;
	var HIGHLIGHT = 'wpcpm-guide-found';
	var canMark = !! ( window.CSS && window.CSS.highlights && 'function' === typeof window.Highlight );
	var MAX = 30;

	// Lowercase, without accents, with where each kept character was in the original, so a match in the
	// folded text is found again in the text as written.
	function plain( text ) {
		var out = '';
		var map = [];

		for ( var i = 0; i < text.length; i++ ) {
			var piece = text.charAt( i ).normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).toLowerCase();

			for ( var j = 0; j < piece.length; j++ ) {
				out += piece.charAt( j );
				map.push( i );
			}
		}

		return { text: out, map: map };
	}

	function squash( text ) {
		return text.replace( /\s+/g, ' ' ).trim();
	}

	// One entry per block of the guide: its words, and the section and part it sits under.
	function build() {
		var blocks = 'h2, h3, h4, p, li, tr, figcaption';
		var inline = 'a, abbr, b, code, em, i, kbd, mark, s, small, span, strong, sub, sup, u';
		var section = '';
		var part = '';
		var entries = [];

		Array.prototype.forEach.call( body.querySelectorAll( blocks ), function ( element ) {
			var own = '';

			// A name in bold or a link reads on with the words around it ("press Approve." keeps its
			// full stop beside the name); anything else inside the block, a list or a line break, is
			// set apart by a space, and a block that has an entry of its own is left to that entry.
			Array.prototype.forEach.call( element.childNodes, function ( node ) {
				if ( 1 !== node.nodeType || node.matches( inline ) ) {
					own += node.textContent;
				} else if ( node.matches( blocks ) ) {
					own += ' ';
				} else {
					own += ' ' + node.textContent + ' ';
				}
			} );

			if ( 'TR' === element.tagName ) {
				own = Array.prototype.map.call( element.cells, function ( cell ) {
					return cell.textContent;
				} ).join( ' ' );
			}

			var text = squash( own );
			var tag = element.tagName;

			if ( 'H2' === tag ) {
				section = text;
				part = '';
			} else if ( 'H3' === tag ) {
				part = text;
			}

			if ( ! text || element.closest( '.wpcpm-guide__fold-tools' ) ) {
				return;
			}

			var heading = 'H2' === tag || 'H3' === tag;

			entries.push( {
				element: element,
				text: text,
				folded: plain( text ),
				heading: heading,
				section: section,
				part: part,
				id: heading ? element.id : '',
				order: entries.length
			} );
		} );

		// Where a block has no heading of its own to link to, the nearest one before it.
		var last = '';

		entries.forEach( function ( entry ) {
			if ( entry.heading && entry.id ) {
				last = entry.id;
			} else if ( ! entry.id ) {
				entry.id = last;
			}
		} );

		return entries;
	}

	function find( entry, words ) {
		var first = -1;

		for ( var i = 0; i < words.length; i++ ) {
			var at = entry.folded.text.indexOf( words[ i ] );

			if ( at < 0 ) {
				return -1;
			}

			if ( first < 0 || at < first ) {
				first = at;
			}
		}

		return first;
	}

	function snippet( entry, at ) {
		var from = entry.folded.map[ at ] || 0;
		var start = Math.max( 0, from - 40 );
		var end = Math.min( entry.text.length, start + 120 );

		if ( start > 0 ) {
			var space = entry.text.indexOf( ' ', start );
			start = space > -1 && space < from ? space + 1 : start;
		}

		if ( end < entry.text.length ) {
			var cut = entry.text.lastIndexOf( ' ', end );
			end = cut > from ? cut : end;
		}

		return ( start > 0 ? strings.ellipsis : '' ) + entry.text.slice( start, end ) + ( end < entry.text.length ? strings.ellipsis : '' );
	}

	function clearMarks() {
		if ( canMark ) {
			window.CSS.highlights.delete( HIGHLIGHT );
		}
	}

	// Marks the typed words in one block, where the browser can mark text ranges; nowhere else.
	function markWords( element, words ) {
		clearMarks();

		if ( ! canMark ) {
			return;
		}

		var ranges = [];
		var walker = document.createTreeWalker( element, NodeFilter.SHOW_TEXT );

		for ( var node = walker.nextNode(); node; node = walker.nextNode() ) {
			var seen = plain( node.nodeValue );

			words.forEach( function ( word ) {
				for ( var at = seen.text.indexOf( word ); at > -1; at = seen.text.indexOf( word, at + word.length ) ) {
					var range = document.createRange();
					range.setStart( node, seen.map[ at ] );
					range.setEnd( node, seen.map[ at + word.length - 1 ] + 1 );
					ranges.push( range );
				}
			} );
		}

		if ( ranges.length ) {
			var found = new window.Highlight();

			ranges.forEach( function ( range ) {
				found.add( range );
			} );
			window.CSS.highlights.set( HIGHLIGHT, found );
		}
	}

	function go( entry, words ) {
		var element = entry.element;
		var quiet = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		openAround( element );
		element.setAttribute( 'tabindex', '-1' );
		element.scrollIntoView( { block: 'center', behavior: quiet ? 'auto' : 'smooth' } );
		element.focus( { preventScroll: true } );
		markWords( element, words );
		element.addEventListener( 'blur', clearMarks, { once: true } );
	}

	function fill( found, total, words ) {
		results.textContent = '';

		found.slice( 0, MAX ).forEach( function ( entry ) {
			var item = document.createElement( 'li' );
			var link = document.createElement( 'a' );
			var where = document.createElement( 'span' );
			var around = document.createElement( 'span' );
			var names = [ entry.section, entry.part ].filter( function ( name, i, all ) {
				return name && all.indexOf( name ) === i;
			} );

			link.className = 'wpcpm-guide__result';
			link.setAttribute( 'href', '#' + encodeURIComponent( entry.id ) );
			where.className = 'wpcpm-guide__result-where';
			where.textContent = names.join( strings.separator );
			around.className = 'wpcpm-guide__result-text';
			around.textContent = snippet( entry, find( entry, words ) );
			link.appendChild( where );
			link.appendChild( around );
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				go( entry, words );
			} );
			item.appendChild( link );
			results.appendChild( item );
		} );

		if ( ! total ) {
			status.textContent = strings.none;
		} else if ( total > MAX ) {
			status.textContent = strings.cut.replace( '%1$d', MAX ).replace( '%2$d', total );
		} else {
			status.textContent = ( 1 === total ? strings.found_one : strings.found_many ).replace( '%d', total );
		}
	}

	function reset() {
		results.textContent = '';
		results.hidden = true;
		status.textContent = '';
		clearMarks();

		if ( nav ) {
			nav.removeAttribute( 'data-searching' );
		}
	}

	function search() {
		var typed = plain( squash( input.value ) ).text;
		var words = typed.split( ' ' ).filter( Boolean );

		if ( typed.length < 2 ) {
			reset();
			return;
		}

		index = index || build();

		var found = [];

		index.forEach( function ( entry ) {
			if ( find( entry, words ) > -1 ) {
				found.push( entry );
			}
		} );

		// Headings first, then the steps and the text, each in the page's order.
		found.sort( function ( a, b ) {
			return ( a.heading === b.heading ? 0 : ( a.heading ? -1 : 1 ) ) || a.order - b.order;
		} );

		clearMarks();
		nav.setAttribute( 'data-searching', '' );
		results.hidden = false;
		fill( found, found.length, words );
	}

	var nav = document.querySelector( '[data-wpcpm-guide-contents]' );

	if ( box && input && status && results && body && strings && nav ) {
		var timer = 0;

		box.hidden = false;
		results.hidden = true;

		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( search, 150 );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				window.clearTimeout( timer );
				input.value = '';
				reset();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			var where = event.target;
			var typing = where && ( where.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test( where.tagName ) );

			if ( '/' === event.key && ! typing && ! event.ctrlKey && ! event.metaKey && ! event.altKey ) {
				event.preventDefault();
				input.focus();
			}
		} );
	}

	if ( ! nav ) {
		return;
	}

	var list = nav.querySelector( '.wpcpm-guide__list' );
	var toggle = nav.querySelector( '.wpcpm-guide__contents-toggle' );
	var links = Array.prototype.slice.call( nav.querySelectorAll( '.wpcpm-guide__link' ) );
	var targets = [];

	links.forEach( function ( link ) {
		var raw = ( link.getAttribute( 'href' ) || '' ).slice( 1 );
		var id = raw;

		// The page writes each id percent-encoded; one that is not stays as it is rather than stopping
		// the whole list (the release's review, 1.123.70).
		try {
			id = decodeURIComponent( raw );
		} catch ( e ) {
			id = raw;
		}

		var heading = id ? document.getElementById( id ) : null;

		if ( heading ) {
			targets.push( { link: link, heading: heading } );
		}
	} );

	if ( ! list ) {
		return;
	}

	nav.setAttribute( 'data-ready', '' );

	var folded = false;
	var open = false;
	var current = null;

	function setOpen( value ) {
		open = value;
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		list.hidden = folded && ! open;
	}

	// Folded or not is the stylesheet's call: its container query sets the flag this reads.
	function layout() {
		var fold = '1' === window.getComputedStyle( nav ).getPropertyValue( '--wpcpm-guide-fold' ).trim();

		if ( fold === folded ) {
			return;
		}

		folded = fold;
		nav.toggleAttribute( 'data-fold', folded );

		if ( toggle ) {
			toggle.hidden = ! folded;
		}

		setOpen( false );
	}

	// The part being read: the last heading whose top has passed a line a little below the top of the
	// window. Above the first one, nothing is marked.
	function mark() {
		var line = Math.min( 160, window.innerHeight * 0.3 );
		var found = null;

		for ( var i = 0; i < targets.length; i++ ) {
			if ( targets[ i ].heading.getBoundingClientRect().top - line > 0 ) {
				break;
			}

			found = targets[ i ];
		}

		// At the very end of the page the last heading may never reach the line.
		if ( window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2 ) {
			found = targets[ targets.length - 1 ];
		}

		if ( found === current ) {
			return;
		}

		if ( current ) {
			current.link.removeAttribute( 'aria-current' );
		}

		Array.prototype.forEach.call( nav.querySelectorAll( '.wpcpm-guide__entry.is-open' ), function ( entry ) {
			entry.classList.remove( 'is-open' );
		} );

		current = found;

		if ( ! current ) {
			return;
		}

		current.link.setAttribute( 'aria-current', 'location' );

		var entry = current.link.closest( '.wpcpm-guide__entry' );

		if ( entry ) {
			entry.classList.add( 'is-open' );
		}

		reveal( current.link );
	}

	// Scrolls the list itself, never the page, so the marked entry stays in view in a list taller than
	// the window.
	function reveal( link ) {
		if ( folded || nav.scrollHeight <= nav.clientHeight ) {
			return;
		}

		var top = link.offsetTop;
		var bottom = top + link.offsetHeight;
		var pad = 48;

		if ( top - pad < nav.scrollTop ) {
			nav.scrollTop = Math.max( 0, top - pad );
		} else if ( bottom + pad > nav.scrollTop + nav.clientHeight ) {
			nav.scrollTop = bottom + pad - nav.clientHeight;
		}
	}

	var queued = false;

	function queue() {
		if ( queued ) {
			return;
		}

		queued = true;
		window.requestAnimationFrame( function () {
			queued = false;
			mark();
		} );
	}

	if ( toggle ) {
		toggle.addEventListener( 'click', function () {
			setOpen( ! open );
		} );
	}

	// A section picked from the folded list closes it, so the reader lands on the section, not the list.
	list.addEventListener( 'click', function ( event ) {
		if ( folded && event.target.closest( 'a' ) ) {
			setOpen( false );
		}
	} );

	nav.addEventListener( 'keydown', function ( event ) {
		if ( folded && open && 'Escape' === event.key ) {
			setOpen( false );
			toggle.focus();
		}
	} );

	var main = nav.closest( '.wpcpm-frame__main' ) || document.documentElement;

	if ( window.ResizeObserver ) {
		new window.ResizeObserver( layout ).observe( main );
	} else {
		window.addEventListener( 'resize', layout );
	}

	layout();

	// Marking the part being read is for a list of the page's own headings; a list of other pages has
	// its own entry marked by the page.
	if ( targets.length ) {
		window.addEventListener( 'scroll', queue, { passive: true } );
		window.addEventListener( 'resize', queue );
		window.addEventListener( 'hashchange', queue );
		mark();
	}
}() );
