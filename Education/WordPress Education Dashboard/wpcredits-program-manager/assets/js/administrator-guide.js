/**
 * The Administrator guide's list of sections (1.123.70).
 *
 * Marks the part of the guide being read, opens only that section's parts, keeps the mark in view inside
 * the list when the list is taller than the window, and folds the list behind its heading when the main
 * area is too narrow for its own column (the stylesheet says when, through `--wpcpm-guide-fold`). Without
 * this script the whole list is shown, every entry a plain link.
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

	var nav = document.querySelector( '[data-wpcpm-guide-contents]' );

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

	if ( ! list || ! targets.length ) {
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

	window.addEventListener( 'scroll', queue, { passive: true } );
	window.addEventListener( 'resize', queue );
	window.addEventListener( 'hashchange', queue );

	layout();
	mark();
}() );
