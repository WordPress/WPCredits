/**
 * The AI Mentor card on the Student Report Card (AI Mentor R3).
 *
 * The server draws the panel: the conversation of the last 90 days, the box with Send, and the working,
 * failure and limit lines hidden until they are needed. This script sends a message to the turn route,
 * one at a time, gives up after 150 seconds, and adds the turn to the conversation. Every element it adds
 * is made with createElement and filled with textContent: a reply is drawn from its text, in a pre as the
 * server draws it, and never from its html. A reply that offers the hand-off draws R2's
 * preview under it (Need help?'s "Send this to your mentor"), which sends through R2's own route.
 */
( function () {
	'use strict';

	var config = window.wpcpmTutor || {};
	var card = document.querySelector( '[data-wpcpm-tutor]' );

	if ( ! card || ! config.endpoint ) {
		return;
	}

	var strings = config.strings || {};
	var form = card.querySelector( '[data-wpcpm-tutor-form]' );
	var input = card.querySelector( '[data-wpcpm-tutor-input]' );
	var send = card.querySelector( '[data-wpcpm-tutor-send]' );
	var log = card.querySelector( '[data-wpcpm-tutor-log]' );
	var turns = card.querySelector( '[data-wpcpm-tutor-turns]' );
	var working = card.querySelector( '[data-wpcpm-tutor-working]' );
	var failed = card.querySelector( '[data-wpcpm-tutor-failed]' );
	var limit = card.querySelector( '[data-wpcpm-tutor-limit]' );
	var leftLine = card.querySelector( '[data-wpcpm-tutor-left]' );
	var earlier = card.querySelector( '[data-wpcpm-tutor-earlier]' );
	// wp_localize_script() hands every top-level value over as a string.
	var max = parseInt( config.max, 10 ) || 1200;
	var total = parseInt( config.turns, 10 ) || 0;
	var noteMax = parseInt( config.noteMax, 10 ) || 600;
	var GIVE_UP = 150000;
	// The request in flight, by number, or 0: one at a time, and an answer to one given up on draws nothing.
	var pending = 0;
	var asked = 0;
	var notes = 0;

	if ( ! form || ! input || ! send || ! log || ! turns ) {
		return;
	}

	/**
	 * An element of a class with its text.
	 *
	 * @param {string} tag       The tag.
	 * @param {string} className Its class.
	 * @param {string} text      Its text, or '' for none.
	 * @return {Element}
	 */
	function make( tag, className, text ) {
		var element = document.createElement( tag );

		element.className = className;

		if ( text ) {
			element.textContent = text;
		}

		return element;
	}

	/**
	 * What was said, as the server draws it: the name for a screen reader, then the text.
	 *
	 * @param {string} className `wpcpm-tutor__message` or `wpcpm-tutor__reply`.
	 * @param {string} who       The name for a screen reader.
	 * @param {string} text      What was said.
	 * @return {Element}
	 */
	function said( className, who, text ) {
		var box = make( 'div', className, '' );

		box.appendChild( make( 'span', 'screen-reader-text', who ) );
		// A pre, as the server draws it, which keeps the line breaks and which core's texturizing skips.
		box.appendChild( make( 'pre', 'wpcpm-tutor__text', String( text ).replace( /\r\n|\r/g, '\n' ) ) );

		return box;
	}

	/**
	 * A link that opens beside the card, or null for an address that is not http or https.
	 *
	 * @param {string} href The address.
	 * @param {string} text Its words.
	 * @return {Element|null}
	 */
	function link( href, text ) {
		if ( ! /^https?:\/\//i.test( href || '' ) || ! text ) {
			return null;
		}

		var a = document.createElement( 'a' );

		a.href = href;
		a.target = '_blank';
		a.rel = 'noopener noreferrer';
		a.textContent = text;

		return a;
	}

	/**
	 * The day's messages left, and once there are none, the box closed with the limit sentence.
	 *
	 * @param {number} left     How many are left.
	 * @param {string} sentence The limit sentence, when a reply gave one.
	 */
	function showLeft( left, sentence ) {
		left = Math.max( 0, parseInt( left, 10 ) || 0 );

		if ( leftLine && strings.left ) {
			leftLine.textContent = strings.left.replace( '%1$d', left ).replace( '%2$d', total );
		}

		if ( left > 0 ) {
			return;
		}

		input.disabled = true;
		send.disabled = true;

		if ( limit ) {
			if ( sentence ) {
				limit.textContent = sentence;
			}

			limit.hidden = false;
		}
	}

	/**
	 * Keep the newest turn in view.
	 */
	function scrollDown() {
		log.scrollTop = log.scrollHeight;
	}

	/**
	 * A reply under the student's message: its text, its numbered sources, the mark of an unsourced reply, the
	 * channel's link after a nothing answer, and the hand-off's preview when the reply offers it.
	 *
	 * @param {Element} turn The turn, holding the student's message.
	 * @param {Object}  data The route's reply.
	 */
	function drawReply( turn, data ) {
		var reply = said( 'wpcpm-tutor__reply', strings.mentor || '', data.text || '' );
		var items = [];

		// Source i is the reply's marker [i], so the list is numbered, and one left out keeps the next at its own number.
		( data.sources || [] ).forEach( function ( source, index ) {
			var a = source ? link( source.link, source.title ) : null;

			if ( a ) {
				var item = document.createElement( 'li' );

				if ( items.length !== index ) {
					item.setAttribute( 'value', String( index + 1 ) );
				}

				item.appendChild( a );

				// A fallback's source carries the start of its passage, drawn under the link as the server does.
				if ( source.extract && 'string' === typeof source.extract && source.extract.trim() ) {
					item.appendChild( make( 'pre', 'wpcpm-tutor__extract', source.extract.trim() ) );
				}

				items.push( item );
			}
		} );

		if ( items.length ) {
			var list = make( 'ol', 'wpcpm-tutor__sources', '' );

			items.forEach( function ( item ) {
				list.appendChild( item );
			} );

			reply.appendChild( make( 'p', 'wpcpm-tutor__sources-title', strings.sources || '' ) );
			reply.appendChild( list );
		}

		if ( data.unsourced ) {
			reply.appendChild( make( 'p', 'wpcpm-student__note wpcpm-tutor__unsourced', strings.unsourced || '' ) );
		}

		if ( data.nothing && data.channel ) {
			var channel = link( data.channel.url, data.channel.label );

			if ( channel ) {
				var line = make( 'p', 'wpcpm-tutor__channel', '' );

				line.appendChild( channel );
				reply.appendChild( line );
			}
		}

		turn.appendChild( reply );

		if ( data.handoff ) {
			var host = make( 'div', 'wpcpm-tutor__send', '' );

			reply.appendChild( host );
			preview( host, data );
		}
	}

	/**
	 * R2's preview of what is sent to the mentor: the route's question as text, a line of their own,
	 * Send and Cancel. Send posts to R2's route and R2's sentence takes the preview's place; Cancel
	 * dismisses it. The question is the student's message as R2's send() will keep it, which the route
	 * gives with the reply, so the preview holds exactly what the mentor gets.
	 *
	 * @param {Element} host Where the preview, then the sentence, is drawn.
	 * @param {Object}  data The route's reply: its question, and its source links, which go with it.
	 */
	function preview( host, data ) {
		var box = make( 'div', 'wpcpm-tutor__preview', '' );
		var id = 'wpcpm-tutor-note-' + ( ++notes );
		var label = make( 'label', 'wpcpm-tutor__preview-label', strings.noteLabel || '' );
		var note = make( 'textarea', 'wpcpm-tutor__preview-note', '' );
		var actions = make( 'p', 'wpcpm-tutor__preview-actions', '' );
		var submit = make( 'button', 'wpcpm-button', strings.submit || '' );
		var cancel = make( 'button', 'wpcpm-tutor__cancel', strings.cancel || '' );
		var sending = false;
		var question = 'string' === typeof data.question ? data.question : '';

		box.appendChild( make( 'h4', 'wpcpm-tutor__preview-title', strings.previewTitle || '' ) );
		box.appendChild( make( 'p', 'wpcpm-tutor__preview-question', question ) );

		label.htmlFor = id;
		note.id = id;
		note.rows = 3;
		note.maxLength = noteMax;
		box.appendChild( label );
		box.appendChild( note );

		submit.type = 'button';
		cancel.type = 'button';
		actions.appendChild( submit );
		actions.appendChild( cancel );
		box.appendChild( actions );
		host.appendChild( box );

		cancel.addEventListener( 'click', function () {
			if ( ! sending ) {
				host.remove();
				input.focus();
			}
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
					// A refusal answers with its sentence too; one that does not is R2's refused sentence.
					return response.json().then(
						function ( reply ) {
							return reply || {};
						},
						function () {
							return {};
						}
					);
				} )
				.then( function ( reply ) {
					var sentence = make( 'p', 'wpcpm-tutor__sent', reply.state && reply.message ? reply.message : strings.refused || '' );

					sentence.setAttribute( 'role', 'status' );
					host.textContent = '';
					host.appendChild( sentence );
				} )
				.catch( function () {
					// Not sent, as far as is known: the preview stays, so the student can press Send again.
					sending = false;
					submit.disabled = false;
					cancel.disabled = false;
					submit.textContent = strings.submit || '';

					var line = box.querySelector( '.wpcpm-tutor__preview-failed' ) || make( 'p', 'wpcpm-tutor__preview-failed', '' );

					line.textContent = strings.failed || '';
					line.setAttribute( 'role', 'alert' );
					box.insertBefore( line, actions );
				} );
		} );
	}

	/**
	 * The turn could not be had: it is taken back, the message goes back into the box, and the failure
	 * line says so.
	 *
	 * @param {number}  mine    The request.
	 * @param {Element} turn    The turn drawn for it.
	 * @param {string}  message The student's message.
	 */
	function fail( mine, turn, message ) {
		if ( mine !== pending ) {
			return;
		}

		done();
		takeBack( turn, message );

		if ( failed ) {
			failed.hidden = false;
		}
	}

	/**
	 * A turn that was not had, taken off the conversation, and its message back in the box unless the
	 * student has started another there meanwhile.
	 *
	 * @param {Element} turn    The turn drawn for it.
	 * @param {string}  message The student's message.
	 */
	function takeBack( turn, message ) {
		turn.remove();
		log.hidden = ! turns.children.length;

		if ( ! input.value.trim() ) {
			input.value = message;
		}
	}

	/**
	 * The request is over, whichever way.
	 */
	function done() {
		pending = 0;

		if ( working ) {
			working.hidden = true;
		}

		send.disabled = input.disabled;
	}

	/**
	 * Send a message, and draw what comes back.
	 *
	 * @param {string} message The message, trimmed and capped.
	 */
	function ask( message ) {
		var mine = ++asked;
		var controller = typeof AbortController === 'function' ? new AbortController() : null;
		var turn = make( 'li', 'wpcpm-tutor__turn', '' );

		pending = mine;
		send.disabled = true;
		input.value = '';

		if ( failed ) {
			failed.hidden = true;
		}

		if ( working ) {
			// The server leaves the words to the script: drawn by it, "..." stays as typed.
			if ( ! working.textContent && strings.working ) {
				working.textContent = strings.working;
			}

			working.hidden = false;
		}

		turn.appendChild( said( 'wpcpm-tutor__message', strings.you || '', message ) );
		turns.appendChild( turn );
		log.hidden = false;
		scrollDown();

		// A ceiling of the card's own, above the server's turn, which stops calling the model after 55 seconds:
		// without it a request nobody answers would leave the working line up for ever.
		var giveUp = window.setTimeout( function () {
			if ( controller ) {
				controller.abort();
			}

			fail( mine, turn, message );
		}, GIVE_UP );

		window
			.fetch( config.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				signal: controller ? controller.signal : undefined,
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: JSON.stringify( { message: message } )
			} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}

				return response.json();
			} )
			.then( function ( data ) {
				if ( mine !== pending ) {
					return;
				}

				window.clearTimeout( giveUp );
				data = data || {};

				// Past the limit nothing was sent or kept: no turn, and the box closed with the sentence.
				if ( data.limit ) {
					done();
					takeBack( turn, message );
					showLeft( 0, data.text || '' );

					return;
				}

				drawReply( turn, data );
				done();
				showLeft( data.left, '' );
				scrollDown();
			} )
			.catch( function () {
				window.clearTimeout( giveUp );
				fail( mine, turn, message );
			} );
	}

	function submitMessage() {
		if ( pending || input.disabled ) {
			return;
		}

		var message = input.value.trim();

		if ( ! message ) {
			input.focus();

			return;
		}

		// Counted in characters, as the route counts them, so a capped message is never cut inside one.
		var characters = Array.from( message );

		if ( characters.length > max ) {
			message = characters.slice( 0, max ).join( '' );
		}

		ask( message );
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		submitMessage();
	} );

	// Enter sends, as Send does; Shift+Enter is left to the browser for a new line. A key pressed while an input
	// method is composing (keyCode 229) or with Ctrl, Alt or Meta held is left alone too.
	input.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' !== event.key || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey || event.isComposing || 229 === event.keyCode ) {
			return;
		}

		event.preventDefault();

		if ( send.disabled ) {
			return;
		}

		submitMessage();
	} );

	if ( earlier ) {
		earlier.addEventListener( 'click', function () {
			var first = null;

			Array.prototype.forEach.call( turns.children, function ( turn ) {
				if ( turn.hidden ) {
					turn.hidden = false;
					first = first || turn;
				}
			} );

			var more = earlier.parentNode;

			more.parentNode.removeChild( more );

			// The keyboard goes to the first turn it showed, so the reader starts where the conversation does.
			if ( first ) {
				first.setAttribute( 'tabindex', '-1' );
				first.focus();
			}
		} );
	}

	scrollDown();
}() );
