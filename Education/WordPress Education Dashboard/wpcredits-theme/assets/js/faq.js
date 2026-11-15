/**
 * The FAQ page: open the question an address's fragment names (inc/faq.php).
 *
 * Every question is a Details block with an HTML anchor, so the program team can send a link such as
 * `/faq/#slack-email-not-arrived`. A browser scrolls to the element a fragment names but leaves a
 * closed `details` closed, so the reader lands on the question and not its answer. This opens it: on
 * load, when the fragment changes (`hashchange`), and when a link to the fragment already in the
 * address is pressed again, which fires no `hashchange`. A fragment naming something inside a question
 * opens every question around it. Then the element the fragment names is brought into view; its scroll
 * margin (assets/css/faq.css: the question, its summary, and a target inside the list) keeps it below
 * the sticky header, and no `behavior` is asked, so nothing animates.
 *
 * A fragment that names nothing, names an element outside every question, or cannot be decoded, is left
 * to the browser. Without this script the page still works: the fragment scrolls to the closed
 * question, and a press on it opens it.
 */
( function () {
	'use strict';

	/**
	 * A fragment's words: decoded, or as written when it cannot be decoded. Never throws.
	 *
	 * @param {string} hash A fragment, with or without its `#`.
	 * @return {string} The words after the `#`.
	 */
	function words( hash ) {
		var raw = '#' === hash.charAt( 0 ) ? hash.slice( 1 ) : hash;

		try {
			return decodeURIComponent( raw );
		} catch ( error ) {
			return raw;
		}
	}

	/**
	 * The element the address's fragment names, or null.
	 *
	 * @return {?Element} The element.
	 */
	function named() {
		var hash = window.location.hash || '';
		var id;

		if ( hash.length < 2 ) {
			return null;
		}

		try {
			id = decodeURIComponent( hash.slice( 1 ) );
		} catch ( error ) {
			return null;
		}

		return document.getElementById( id );
	}

	/**
	 * The fragment of a link that leads to this very page (same origin, path and query), or ''.
	 *
	 * @param {string} href The link's address as written.
	 * @return {string} The fragment as the browser writes it, or '' for another page or none.
	 */
	function samePageHash( href ) {
		var here;
		var url;

		try {
			here = new URL( window.location.href );
			url = new URL( href, here );
		} catch ( error ) {
			return '';
		}

		if ( url.origin !== here.origin || url.pathname !== here.pathname || url.search !== here.search ) {
			return '';
		}

		return url.hash.length > 1 ? url.hash : '';
	}

	/**
	 * Open the questions around the element the fragment names, and bring it into view.
	 */
	function reveal() {
		var element = named();
		var question = element ? element.closest( 'details' ) : null;

		if ( ! question ) {
			return;
		}

		for ( var around = question; around; around = around.parentNode ? around.parentNode.closest( 'details' ) : null ) {
			if ( ! around.hasAttribute( 'open' ) ) {
				around.setAttribute( 'open', '' );
			}
		}

		element.scrollIntoView();
	}

	window.addEventListener( 'hashchange', reveal );

	// A link to the fragment already in the address changes nothing a `hashchange` would tell: open the
	// question again after the browser's own jump, should the reader have closed it.
	document.addEventListener( 'click', function ( event ) {
		var link = event.target && event.target.closest ? event.target.closest( 'a[href]' ) : null;
		var hash = link && ! event.defaultPrevented ? samePageHash( link.getAttribute( 'href' ) ) : '';

		if ( '' !== hash && words( hash ) === words( window.location.hash || '' ) ) {
			window.setTimeout( reveal, 0 );
		}
	} );

	reveal();
}() );
