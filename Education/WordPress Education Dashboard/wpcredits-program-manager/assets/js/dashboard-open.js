/**
 * Opens the card a link lands on, on the Sponsor Dashboard, the Administrator Dashboard and the
 * Institution Dashboard.
 *
 * Every card on the Sponsor and Administrator Dashboards folds in a `<details>` drawn by one shell
 * (`WPCPM_Dashboard_Card`), and the Institution Dashboard's folded parts (Enroll students and the
 * Semester report among them) fold in a `<details>` of the same shape, each drawn by its own card.
 * A browser that follows a link to a closed one scrolls to its title and leaves it closed: a link
 * meant to show a card or a part (an At a glance tile, a row's action, a link in a mail, a
 * decision's way back, a link to the form that holds the sample list) would land on a folded row.
 * A Sponsor Dashboard card's anchor, `#wpcpm-sponsor-<card>`, is its disclosure's id; an
 * Administrator Dashboard card's, `#wpcpm-<card>`, and an Institution Dashboard part's,
 * `#wpcpm-import` for Enroll students, are their section's, and the section opens with the
 * disclosure. So when the page has been parsed, and whenever the fragment changes, the disclosure
 * the fragment names is opened, and so is the one a section it names opens with, and every
 * disclosure around the element it names, since a target inside a closed one is hidden. A link to
 * the fragment already in the address changes nothing the browser reports, so a click on a link to
 * one of the page's anchors opens that card too. The browser does the scrolling, as it does for
 * any fragment. On the Sponsor Dashboard a handler's flash also opens its card from the server; a
 * decision made on the Administrator Dashboard comes back with its card's anchor in the address,
 * which this opens once the page is parsed.
 *
 * A row's action is hidden while its card is open (one rule in the stylesheets), so the press that
 * opens the card hides the link that was pressed, and a browser drops the focus of what it hides to
 * the page. An At a glance tile stays on screen, but a browser following its link to a card, which
 * takes no focus, leaves the focus on the page, or on the tile scrolled out of view, and not in the
 * card. Either way a screen reader has nothing in the card to announce. So once the browser has
 * followed either link, the focus goes to the title of the card it opened.
 *
 * A press that opens the link somewhere else, a new tab or window (Ctrl, Cmd or Shift held, or a
 * button other than the first) or a download (Alt held), is the browser's alone: it does not follow
 * the link on this page, so no card opens here and no focus moves.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
			return;
		}

		document.addEventListener( 'DOMContentLoaded', fn );
	}

	function isDetails( node ) {
		return !! node && 'details' === node.nodeName.toLowerCase();
	}

	// Opens the disclosure a fragment names, the one a section it names opens with, and every
	// disclosure around the element it names. Answers the disclosure whose title the focus belongs
	// to: the one a section it names opens with, a card's; else the innermost one at or around the
	// element it names, the card's own when the fragment names a card and one inside the card when
	// it names that; null when it names nothing inside a disclosure.
	function openFragment( hash ) {
		var id = String( hash ).slice( 1 );
		var disclosure = null;
		var target;
		var node;

		if ( ! id ) {
			return null;
		}

		// The fragment arrives percent-encoded; one that does not decode names nothing here.
		try {
			id = decodeURIComponent( id );
		} catch ( error ) {
			return null;
		}

		target = document.getElementById( id );

		// An Administrator Dashboard card's anchor is on its section, and so is an Institution
		// Dashboard part's; the section's first child is the disclosure.
		if ( target && ! isDetails( target ) && isDetails( target.firstElementChild ) ) {
			disclosure = target.firstElementChild;
			disclosure.open = true;
		}

		for ( node = target; node; node = node.parentElement ) {
			if ( isDetails( node ) ) {
				node.open = true;
				disclosure = disclosure || node;
			}
		}

		return disclosure;
	}

	function openTarget() {
		openFragment( window.location.hash );
	}

	// A disclosure's own summary: its title, and the first thing a keyboard reaches in it.
	function summaryOf( card ) {
		var child;

		for ( child = card.firstElementChild; child; child = child.nextElementSibling ) {
			if ( 'summary' === child.nodeName.toLowerCase() ) {
				return child;
			}
		}

		return null;
	}

	// After a press that opened a disclosure, once the browser has followed the link: if the link
	// pressed has no box any more (a row's action, which the stylesheets hide while its card is open)
	// or is an At a glance tile, in the strip's list of tiles on either page, and the browser has
	// given the focus to nothing else, it goes to the title of the disclosure the link opened, the
	// card's own for a tile or a row's action. Any other link still on screen keeps what the browser
	// did with the focus, and so does a target that takes the focus itself.
	function focusCard( disclosure, link ) {
		var summary = summaryOf( disclosure );
		var active = document.activeElement;

		if (
			! summary ||
			( link.getClientRects().length && ! link.closest( '.wpcpm-attention__tiles' ) ) ||
			( active && active !== document.body && active !== link )
		) {
			return;
		}

		// The browser has scrolled to the card already; the focus moves without scrolling again.
		summary.focus( { preventScroll: true } );

		// Every current browser focuses a summary, which it gives a place in the tab order; one that
		// gives it none takes the focus with a tabindex of -1. A summary that has a place keeps it,
		// since -1 would take it out of the tab order for good.
		if ( document.activeElement !== summary && summary.tabIndex < 0 ) {
			summary.setAttribute( 'tabindex', '-1' );
			summary.focus( { preventScroll: true } );
		}
	}

	// Whether a press opens its link somewhere other than this page: a new tab or window, with Ctrl,
	// Cmd or Shift held or with a button other than the first, or a download, with Alt held.
	function elsewhere( event ) {
		return !! ( event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) || ( 'number' === typeof event.button && 0 !== event.button );
	}

	// A tile pressed again after its card was folded links to the fragment already in the address,
	// and the browser fires no hashchange for it: the press itself opens the card the link names.
	// Nothing is prevented, so the browser still follows the link and scrolls to the card. Following
	// a link is the browser's to finish after this press, its own handling of the focus included, so
	// the focus is seen to in a task of its own, after it. A press that opens the link somewhere else
	// leaves this page as it is.
	function openClicked( event ) {
		var link = ! elsewhere( event ) && event.target && event.target.closest ? event.target.closest( 'a[href^="#wpcpm-"]' ) : null;
		var disclosure = link ? openFragment( link.getAttribute( 'href' ) ) : null;

		if ( disclosure ) {
			window.setTimeout( function () {
				focusCard( disclosure, link );
			}, 0 );
		}
	}

	ready( openTarget );
	window.addEventListener( 'hashchange', openTarget );
	document.addEventListener( 'click', openClicked );
}() );
