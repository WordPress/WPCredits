/**
 * The handbook assistant's slide-over panel.
 *
 * Progressive enhancement, the same way the booking calendar works. Without this script the
 * inline block is a form that submits the question as a query argument and the page comes
 * back with the answer on it - slower, but complete. With it, the launcher in the header
 * opens a panel and the answer arrives without leaving the page.
 *
 * The launcher lives in the theme and this lives in the plugin. They know about each other
 * through one data attribute and nothing else, so a theme can put the button anywhere - or
 * nowhere - without either side needing to change.
 */
( function () {
	'use strict';

	var config = window.wpcpmHandbook || {};
	var panel = document.getElementById( 'wpcpm-hb-panel' );

	if ( ! panel || ! config.endpoint ) {
		return;
	}

	var sheet = panel.querySelector( '.wpcpm-hb-panel__sheet' );
	var form = panel.querySelector( '[data-wpcpm-handbook-form]' );
	var input = panel.querySelector( '.wpcpm-hb-panel__input' );
	var body = panel.querySelector( '[data-wpcpm-handbook-body]' );
	var strings = config.strings || {};
	var opener = null;
	var pending = null;

	/**
	 * Show the panel.
	 *
	 * @param {Element} trigger What opened it, so focus can go back there.
	 */
	function open( trigger ) {
		opener = trigger || null;
		panel.hidden = false;
		document.documentElement.classList.add( 'wpcpm-hb-open' );

		// After the frame in which it becomes visible, or the browser will not animate it
		// and will refuse to focus a hidden field.
		window.requestAnimationFrame( function () {
			panel.classList.add( 'is-open' );
			input.focus();
			input.select();
		} );
	}

	/**
	 * Hide the panel and give focus back to whatever opened it.
	 */
	function close() {
		panel.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'wpcpm-hb-open' );

		// Hidden only once the transition is over, so it does not vanish mid-animation.
		window.setTimeout( function () {
			if ( ! panel.classList.contains( 'is-open' ) ) {
				panel.hidden = true;
			}
		}, 200 );

		if ( opener && document.contains( opener ) ) {
			opener.focus();
		}

		opener = null;
	}

	/**
	 * Replace the panel's body with a single message.
	 *
	 * @param {string} message Text, inserted as text and never as markup.
	 * @param {string} variant Class suffix.
	 */
	function message( message, variant ) {
		body.innerHTML = '';

		var p = document.createElement( 'p' );

		p.className = 'wpcpm-hb-panel__' + ( variant || 'hint' );
		p.textContent = message;

		body.appendChild( p );
	}

	/**
	 * Show that something is happening, for as long as it takes.
	 *
	 * A grounded answer takes between ten and twenty-five seconds - the model searches, reads
	 * what it finds, then writes. A line of text for that long reads as a page that has
	 * stopped, so this is a bar that visibly moves and a count of seconds that proves it.
	 *
	 * Indeterminate on purpose: nothing reports how far through a search is, and a bar that
	 * pretends to know would be a lie that stalls at 90%.
	 */
	function working() {
		body.innerHTML = '';

		var wrap = document.createElement( 'div' );

		wrap.className = 'wpcpm-hb-panel__working';

		var label = document.createElement( 'p' );

		label.className = 'wpcpm-hb-panel__hint';
		label.textContent = strings.thinking || 'Looking…';

		var bar = document.createElement( 'div' );

		bar.className = 'wpcpm-hb-panel__bar';
		bar.setAttribute( 'role', 'progressbar' );
		bar.setAttribute( 'aria-label', strings.thinking || 'Looking…' );
		bar.appendChild( document.createElement( 'span' ) );

		var clock = document.createElement( 'p' );

		clock.className = 'wpcpm-hb-panel__clock';

		wrap.appendChild( label );
		wrap.appendChild( bar );
		wrap.appendChild( clock );
		body.appendChild( wrap );

		var seconds = 0;

		clock.textContent = ( strings.elapsed || '%ss' ).replace( '%s', seconds );

		return window.setInterval( function () {
			seconds += 1;
			clock.textContent = ( strings.elapsed || '%ss' ).replace( '%s', seconds );

			// Somewhere to look while a slow one finishes, rather than wondering.
			if ( 12 === seconds && strings.patience ) {
				label.textContent = strings.patience;
			}
		}, 1000 );
	}

	/**
	 * Draw an answer and the sections it came from.
	 *
	 * @param {Object} data The endpoint's response.
	 */
	function render( data ) {
		body.innerHTML = '';

		if ( data.notice ) {
			var notice = document.createElement( 'p' );

			notice.className = 'wpcpm-hb-panel__degraded';
			notice.textContent = data.notice;
			body.appendChild( notice );
		}

		if ( data.unsourced ) {
			var unsourced = document.createElement( 'p' );

			unsourced.className = 'wpcpm-hb-panel__unsourced';
			unsourced.textContent = strings.unsourced || '';
			body.appendChild( unsourced );
		}

		var answer = document.createElement( 'div' );

		answer.className = 'wpcpm-hb-panel__answer';
		// The only innerHTML here, and the only place it is defensible: this is the server's
		// own output, already through `wpautop()` and `wp_kses_post()`. Everything else on
		// this page is set with textContent.
		answer.innerHTML = data.html || '';
		body.appendChild( answer );

		if ( ! data.sources || ! data.sources.length ) {
			return;
		}

		var heading = document.createElement( 'h3' );

		heading.className = 'wpcpm-hb-panel__sources-title';
		heading.textContent = strings.sources || 'Pages it read';
		body.appendChild( heading );

		var list = document.createElement( 'ol' );

		list.className = 'wpcpm-hb-panel__sources';

		data.sources.forEach( function ( source ) {
			var item = document.createElement( 'li' );
			var link = document.createElement( 'a' );

			link.href = source.link;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = source.title;

			item.appendChild( link );

			// The address itself, under the name. A handbook page four levels deep is only
			// identifiable from its path.
			if ( source.extract ) {
				var url = document.createElement( 'p' );

				url.className = 'wpcpm-hb-panel__source-url';
				url.textContent = source.extract;
				item.appendChild( url );
			}

			list.appendChild( item );
		} );

		body.appendChild( list );
	}

	/**
	 * Ask, and draw whatever comes back.
	 *
	 * @param {string} question What to ask.
	 */
	function ask( question ) {
		// One question at a time. Without this, a second press while the first is in flight
		// races it, and whichever answer arrives last wins regardless of which was asked
		// last - which reads as the assistant answering the wrong question.
		if ( pending ) {
			pending.abort();
		}

		pending = typeof AbortController === 'function' ? new AbortController() : null;

		var ticking = working();

		// A ceiling of our own, above the server's. Without it a request the server never
		// answers leaves the bar moving for ever, which is the one thing worse than an error.
		var giveUp = window.setTimeout( function () {
			if ( pending ) {
				pending.abort();
			}

			window.clearInterval( ticking );
			message( strings.slow || strings.failed || '', 'degraded' );
			pending = null;
		}, 75000 );

		window
			.fetch( config.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				signal: pending ? pending.signal : undefined,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: JSON.stringify( { question: question } )
			} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}

				return response.json();
			} )
			.then( function ( data ) {
				pending = null;
				window.clearInterval( ticking );
				window.clearTimeout( giveUp );
				render( data );
			} )
			.catch( function ( error ) {
				window.clearInterval( ticking );
				window.clearTimeout( giveUp );

				// Aborted either by a newer question or by the ceiling above, both of which
				// have already put something on screen.
				if ( error && 'AbortError' === error.name ) {
					return;
				}

				pending = null;
				message( strings.failed || 'That did not work.', 'degraded' );
			} );
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();

		var question = input.value.trim();

		if ( ! question ) {
			message( strings.empty || 'Type a question first.', 'hint' );
			input.focus();

			return;
		}

		ask( question );
	} );

	// Delegated, so a launcher rendered by the theme, by a block, or added later all work
	// without this script knowing they exist.
	document.addEventListener( 'click', function ( event ) {
		var openTrigger = event.target.closest( '[data-wpcpm-handbook-open]' );

		if ( openTrigger ) {
			event.preventDefault();
			open( openTrigger );

			return;
		}

		if ( event.target.closest( '[data-wpcpm-handbook-close]' ) ) {
			event.preventDefault();
			close();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && ! panel.hidden ) {
			close();
		}

		if ( 'Tab' !== event.key || panel.hidden ) {
			return;
		}

		// Keep Tab inside the sheet while it is open. Without it the next Tab lands on the
		// page behind, which for a screen-reader user is indistinguishable from the panel
		// having closed itself.
		var focusable = sheet.querySelectorAll( 'a[href], button, input, [tabindex]:not([tabindex="-1"])' );

		if ( ! focusable.length ) {
			return;
		}

		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	} );
}() );
