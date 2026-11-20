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
	// Whose dashboard the button that opened the panel is on, sent with each question so it is answered first
	// from that dashboard's guide. Empty from a launcher that names none, such as the theme's header.
	var audience = '';
	// The hint the server printed in the body, kept to draw the empty state again, and whether an answer is
	// on show (an opened panel keeps its answer; one with none shows the examples of the card it was opened from).
	var hintNode = body.querySelector( '.wpcpm-hb-panel__hint' );
	var hint = hintNode ? hintNode.textContent : '';
	var answered = false;
	// The question the answer on show answers, which Send to my mentor sends as it was asked, and whether a
	// send is in flight: one request at a time, so a second press cannot send the question twice.
	var asked = '';
	var sending = false;

	/**
	 * Show the panel.
	 *
	 * @param {Element} trigger What opened it, so focus can go back there.
	 */
	function open( trigger ) {
		opener = trigger || null;
		audience = opener && opener.getAttribute ? opener.getAttribute( 'data-wpcpm-handbook-for' ) || '' : '';
		panel.hidden = false;
		document.documentElement.classList.add( 'wpcpm-hb-open' );

		if ( ! answered ) {
			drawEmpty();
		}

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
	 * The empty state: the hint, and three example questions of the card the panel was opened from, each a
	 * button that fills the field and asks it. The asker's own three for a card that names none.
	 */
	function drawEmpty() {
		body.innerHTML = '';

		var p = document.createElement( 'p' );

		p.className = 'wpcpm-hb-panel__hint';
		p.textContent = hint;
		body.appendChild( p );

		var all = config.examples || {};
		var examples = all[ audience ] || all[ config.defaultAudience ] || all.student || [];

		if ( ! examples.length ) {
			return;
		}

		var list = document.createElement( 'ul' );

		list.className = 'wpcpm-hb-panel__examples';

		examples.forEach( function ( example ) {
			var item = document.createElement( 'li' );
			var button = document.createElement( 'button' );

			button.type = 'button';
			button.className = 'wpcpm-hb-panel__example';
			button.setAttribute( 'data-wpcpm-handbook-example', '' );
			button.textContent = example;

			item.appendChild( button );
			list.appendChild( item );
		} );

		body.appendChild( list );
	}

	/**
	 * The link to the Slack channel a nothing answer sends the reader to.
	 *
	 * @param {Object} channel `url` and `label`, from the endpoint.
	 * @param {string} text    Optional words for the link, in place of the channel's own label.
	 * @return {Element|null} The paragraph holding the link, or null when there is nothing safe to link.
	 */
	function channelLink( channel, text ) {
		if ( ! channel || ! /^https?:\/\//.test( channel.url || '' ) || ! ( text || channel.label ) ) {
			return null;
		}

		var wrap = document.createElement( 'p' );
		var link = document.createElement( 'a' );

		wrap.className = 'wpcpm-hb-panel__channel-line';
		link.className = 'wpcpm-hb-panel__channel';
		link.href = channel.url;
		link.textContent = text || channel.label;
		link.target = '_blank';
		link.rel = 'noopener noreferrer';

		wrap.appendChild( link );

		return wrap;
	}

	/**
	 * Show that something is happening, for as long as it takes.
	 *
	 * A line and a bar that visibly moves: an answer is a lookup in the guides and a model's reply, a few
	 * seconds, so no count of them is kept. Indeterminate on purpose: nothing reports how far through it
	 * is, and a bar that pretended to know would be a lie that stalls at 90%.
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

		wrap.appendChild( label );
		wrap.appendChild( bar );
		body.appendChild( wrap );
	}

	/**
	 * Draw an answer and the sections it came from.
	 *
	 * @param {Object} data The endpoint's response.
	 */
	function render( data ) {
		body.innerHTML = '';
		answered = true;

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

		// Where Send to my mentor goes, when the reply says this student may send.
		var host = null;

		if ( data.may_send ) {
			host = document.createElement( 'div' );
			host.className = 'wpcpm-hb-panel__send';
		}

		// Nothing in the guides answers it: say where else to ask, and offer the question to the mentor.
		if ( data.nothing ) {
			var channel = channelLink( data.channel );

			if ( channel ) {
				body.appendChild( channel );
			}

			if ( host ) {
				body.appendChild( host );
				sendControl( host, true, data, asked );
			}
		}

		if ( data.sources && data.sources.length ) {
			drawSources( data.sources );
		}

		// Any other answer: one small link after the sources.
		if ( host && ! data.nothing ) {
			body.appendChild( host );
			sendControl( host, false, data, asked );
		}

		// A student whose mentor cannot be reached: the channel's link in place of that small link.
		if ( data.channel_only && ! data.nothing ) {
			var still = channelLink( data.channel, strings.stillChannel );

			if ( still ) {
				body.appendChild( still );
			}
		}
	}

	/**
	 * The sections an answer came from.
	 *
	 * @param {Object[]} sources `title`, `link` and `extract` of each.
	 */
	function drawSources( sources ) {
		var heading = document.createElement( 'h3' );

		heading.className = 'wpcpm-hb-panel__sources-title';
		heading.textContent = strings.sources || 'Sources';
		body.appendChild( heading );

		var list = document.createElement( 'ol' );

		list.className = 'wpcpm-hb-panel__sources';

		sources.forEach( function ( source ) {
			var item = document.createElement( 'li' );
			var link = document.createElement( 'a' );

			link.href = source.link;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = source.title;

			item.appendChild( link );

			// The part of the passage that matched, under the name, when the answer is the guides' own
			// words and not a model's.
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
	 * The control that offers the question to the student's mentor: the primary button after a nothing
	 * answer, the small link after any other. Pressing it replaces it with the preview.
	 *
	 * @param {Element} host     Where the control, the preview and the sentence are drawn, one at a time.
	 * @param {boolean} primary  Whether this is a nothing answer, whose control is the button.
	 * @param {Object}  data     The endpoint's reply: its source links and its channel are sent and shown.
	 * @param {string}  question The question the answer answers.
	 */
	function sendControl( host, primary, data, question ) {
		host.innerHTML = '';

		var button = document.createElement( 'button' );

		button.type = 'button';
		button.className = primary ? 'wpcpm-button wpcpm-hb-panel__send-button' : 'wpcpm-hb-panel__still';
		button.textContent = primary ? strings.send || 'Send to my mentor' : strings.still || '';

		button.addEventListener( 'click', function () {
			sendPreview( host, primary, data, question );
		} );

		host.appendChild( button );

		return button;
	}

	/**
	 * What will be sent, before it is: the question as text, a line of the student's own, Send and Cancel.
	 *
	 * @param {Element} host     The control's place.
	 * @param {boolean} primary  As `sendControl()`.
	 * @param {Object}  data     As `sendControl()`.
	 * @param {string}  question The question, sent as it was asked.
	 */
	function sendPreview( host, primary, data, question ) {
		host.innerHTML = '';

		var box = document.createElement( 'div' );
		var title = document.createElement( 'h3' );

		box.className = 'wpcpm-hb-panel__preview';
		title.className = 'wpcpm-hb-panel__preview-title';
		title.textContent = strings.previewTitle || '';
		box.appendChild( title );

		var text = document.createElement( 'p' );

		text.className = 'wpcpm-hb-panel__preview-question';
		text.textContent = question;
		box.appendChild( text );

		var label = document.createElement( 'label' );
		var note = document.createElement( 'textarea' );

		label.className = 'wpcpm-hb-panel__preview-label';
		label.setAttribute( 'for', 'wpcpm-hb-panel-note' );
		label.textContent = strings.noteLabel || '';
		note.id = 'wpcpm-hb-panel-note';
		note.className = 'wpcpm-hb-panel__preview-note';
		note.rows = 3;
		note.maxLength = config.noteMax || 600;
		box.appendChild( label );
		box.appendChild( note );

		var actions = document.createElement( 'div' );
		var submit = document.createElement( 'button' );
		var cancel = document.createElement( 'button' );

		actions.className = 'wpcpm-hb-panel__preview-actions';
		submit.type = 'button';
		submit.className = 'wpcpm-button';
		submit.textContent = strings.submit || 'Send';
		cancel.type = 'button';
		cancel.className = 'wpcpm-hb-panel__cancel';
		cancel.textContent = strings.cancel || 'Cancel';
		actions.appendChild( submit );
		actions.appendChild( cancel );
		box.appendChild( actions );
		host.appendChild( box );

		cancel.addEventListener( 'click', function () {
			if ( sending ) {
				return;
			}

			sendControl( host, primary, data, question ).focus();
		} );

		submit.addEventListener( 'click', function () {
			if ( sending ) {
				return;
			}

			sending = true;
			submit.disabled = true;
			cancel.disabled = true;
			submit.textContent = strings.sending || submit.textContent;

			var links = ( data.sources || [] ).map( function ( source ) {
				return source.link;
			} );

			window
				.fetch( config.sendEndpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.nonce
					},
					body: JSON.stringify( { question: question, note: note.value.trim(), links: links } )
				} )
				.then( function ( response ) {
					// A refusal answers with its sentence too; one that does not (the permission check's own
					// reply) is the refused sentence below.
					return response.json().then(
						function ( reply ) {
							return { ok: response.ok, reply: reply || {} };
						},
						function () {
							return { ok: response.ok, reply: {} };
						}
					);
				} )
				.then( function ( result ) {
					sending = false;

					if ( result.reply.state && result.reply.message ) {
						showSent( host, result.reply.state, result.reply.message, data.channel, data.nothing );
					} else {
						showSent( host, 'refused', strings.refused || '', data.channel, data.nothing );
					}
				} )
				.catch( function () {
					// Not sent, as far as is known: the preview stays, so the student can press Send again.
					sending = false;
					submit.disabled = false;
					cancel.disabled = false;
					submit.textContent = strings.submit || 'Send';

					var failed = box.querySelector( '.wpcpm-hb-panel__degraded' ) || document.createElement( 'p' );

					failed.className = 'wpcpm-hb-panel__degraded';
					failed.textContent = strings.failed || '';
					box.insertBefore( failed, actions );
				} );
		} );

		note.focus();
	}

	/**
	 * The sentence a send ended in, in place of the preview; for a limit, a repeat or a refusal, the channel's
	 * link with it.
	 *
	 * @param {Element} host    The control's place.
	 * @param {string}  state   `sent`, `limit`, `duplicate` or `refused`.
	 * @param {string}  message The sentence, as text.
	 * @param {Object}  channel The endpoint's `url` and `label`.
	 * @param {boolean} nothing Whether the answer was a nothing answer, whose own channel link is already on screen:
	 *                          then the sentence adds none, so there is one link and not two.
	 */
	function showSent( host, state, message, channel, nothing ) {
		host.innerHTML = '';

		var line = document.createElement( 'p' );

		line.className = 'wpcpm-hb-panel__sent';
		line.setAttribute( 'role', 'status' );
		line.textContent = message;
		host.appendChild( line );

		if ( 'sent' !== state && ! nothing ) {
			var link = channelLink( channel );

			if ( link ) {
				host.appendChild( link );
			}
		}
	}

	/**
	 * Ask, and draw whatever comes back.
	 *
	 * @param {string} question What to ask.
	 */
	function ask( question ) {
		asked = question;

		// One question at a time. Without this, a second press while the first is in flight
		// races it, and whichever answer arrives last wins regardless of which was asked
		// last - which reads as the assistant answering the wrong question.
		if ( pending ) {
			pending.abort();
		}

		pending = typeof AbortController === 'function' ? new AbortController() : null;

		working();

		// A ceiling of our own, above the server's. Without it a request the server never
		// answers leaves the bar moving for ever, which is the one thing worse than an error.
		var giveUp = window.setTimeout( function () {
			if ( pending ) {
				pending.abort();
			}

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
				body: JSON.stringify( { question: question, audience: audience } )
			} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}

				return response.json();
			} )
			.then( function ( data ) {
				pending = null;
				window.clearTimeout( giveUp );
				render( data );
			} )
			.catch( function ( error ) {
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

		var example = event.target.closest( '[data-wpcpm-handbook-example]' );

		if ( example ) {
			event.preventDefault();

			var text = example.textContent;

			input.value = text;
			ask( text );

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
		var focusable = sheet.querySelectorAll( 'a[href], button:not([disabled]), input, textarea, [tabindex]:not([tabindex="-1"])' );

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
