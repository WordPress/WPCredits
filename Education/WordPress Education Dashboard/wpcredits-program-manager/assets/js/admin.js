/**
 * WPCredits Program Manager - admin behavior.
 *
 * Drives every running sync on the page and reports its progress. Each poll does
 * two jobs: it advances the sync by one slice (so the work does not depend on
 * WP-Cron firing) and it refreshes the readout. Between polls a local clock keeps
 * counting, so there is never a stretch of seconds where nothing on screen moves.
 *
 * Falls back gracefully: with JavaScript off, the server prints a meta refresh
 * and cron carries the run instead.
 */
( function () {
	'use strict';

	var POLL_FALLBACK = 3;

	/**
	 * How long the page waits after the finish is said before it reloads, in milliseconds: long enough
	 * for a screen reader to start reading the sentence before the page it is on goes (1.123.58).
	 */
	var FINISH_PAUSE = 1500;

	/**
	 * Format seconds as a clock value, matching WPCPM_Mentors::format_duration().
	 *
	 * @param {number} seconds Elapsed seconds.
	 * @return {string} Formatted duration.
	 */
	function formatDuration( seconds ) {
		seconds = Math.max( 0, Math.floor( seconds ) );

		var pad = function ( n ) {
			return n < 10 ? '0' + n : String( n );
		};

		if ( seconds >= 3600 ) {
			return (
				Math.floor( seconds / 3600 ) +
				':' +
				pad( Math.floor( ( seconds % 3600 ) / 60 ) ) +
				':' +
				pad( seconds % 60 )
			);
		}

		return Math.floor( seconds / 60 ) + ':' + pad( seconds % 60 );
	}

	/**
	 * Wire up every progress panel on the page.
	 *
	 * One poller per panel, each reading its own action, nonce and interval. A screen
	 * used to draw at most one run, so the first `[data-wpcpm-progress]` was the only
	 * one; the Institutions screen draws a sync panel and a provisioning run on the
	 * same page, and with a single poller the second would sit at zero until somebody
	 * reloaded. A page with one panel behaves exactly as it did.
	 *
	 * @return {number} How many panels were wired.
	 */
	function initProgress() {
		var roots = document.querySelectorAll( '[data-wpcpm-progress]' );
		var i;

		if ( ! roots.length || typeof window.ajaxurl === 'undefined' ) {
			return 0;
		}

		for ( i = 0; i < roots.length; i++ ) {
			pollProgress( roots[ i ] );
		}

		return roots.length;
	}

	/**
	 * Drive one progress panel until its run finishes.
	 *
	 * Everything it needs is on the panel, so two of these on a page never share
	 * state: each has its own clock, its own `finished` flag and its own back-off.
	 * Whichever run finishes first reloads the page, and the fresh page wires a new
	 * poller for the one still going.
	 *
	 * What a screen reader is told (1.123.58, through `speak()`): the panel is busy
	 * (`aria-busy`) while the run goes and not once it ends; each step's line once, as
	 * the panel prints it, when the step changes; the stall line once, when it first
	 * shows; and the panel's `data-finished` sentence, a moment before the page reloads. A run
	 * the count check stopped ends the poll: its sentence is said once and printed in the
	 * panel's `data-wpcpm-stopped` notice with the way to Run anyway (1.123.58, trial T5),
	 * and nothing reloads, so the Sync tab drawn again by the person shows the stop and
	 * Run anyway.
	 *
	 * @param {Element} root The panel element.
	 */
	function pollProgress( root ) {
		var action = root.getAttribute( 'data-action' );
		var nonce = root.getAttribute( 'data-nonce' );
		var poll = parseInt( root.getAttribute( 'data-poll' ), 10 ) || POLL_FALLBACK;

		var el = {
			label: root.querySelector( '[data-wpcpm-label]' ),
			step: root.querySelector( '[data-wpcpm-step]' ),
			bar: root.querySelector( '[data-wpcpm-bar]' ),
			fill: root.querySelector( '[data-wpcpm-fill]' ),
			percent: root.querySelector( '[data-wpcpm-percent]' ),
			detail: root.querySelector( '[data-wpcpm-detail]' ),
			elapsed: root.querySelector( '[data-wpcpm-elapsed]' ),
			stalled: root.querySelector( '[data-wpcpm-stalled]' ),
			stopped: root.querySelector( '[data-wpcpm-stopped]' ),
			stoppedSentence: root.querySelector( '[data-wpcpm-stopped-sentence]' ),
			stoppedHint: root.querySelector( '[data-wpcpm-stopped-hint]' ),
		};

		var elapsedLabel = el.elapsed ? el.elapsed.getAttribute( 'data-label' ) || '%s' : '%s';
		var elapsed = 0;
		var finished = false;
		var saidStep = null;
		var saidStall = false;

		/**
		 * End the poll: the panel is no longer busy, and nothing more is asked.
		 */
		function end() {
			finished = true;
			root.setAttribute( 'aria-busy', 'false' );
		}

		/**
		 * Print why the count check stopped the run, and the way to Run anyway, in the
		 * panel's error notice, so a sighted person is not left with a bar that no longer
		 * moves. Nothing reloads.
		 *
		 * @param {string} sentence The count check's sentence.
		 */
		function printStop( sentence ) {
			if ( ! el.stopped ) {
				return;
			}

			if ( el.stoppedSentence ) {
				el.stoppedSentence.textContent = sentence || '';
			}

			if ( el.stoppedHint ) {
				el.stoppedHint.textContent = el.stopped.getAttribute( 'data-hint' ) || '';
			}

			el.stopped.hidden = false;
		}

		/**
		 * Paint the elapsed clock from the local counter.
		 */
		function paintElapsed() {
			if ( el.elapsed ) {
				el.elapsed.textContent = elapsedLabel.replace( '%s', formatDuration( elapsed ) );
			}
		}

		/**
		 * Apply a progress payload from the server.
		 *
		 * @param {Object} data Progress payload.
		 */
		function render( data ) {
			if ( el.label && data.label ) {
				el.label.textContent = data.label;
			}

			if ( el.step && data.step_label ) {
				el.step.textContent = data.step_label;
			}

			if ( typeof data.percent === 'number' ) {
				if ( el.fill ) {
					el.fill.style.width = data.percent + '%';
				}
				if ( el.bar ) {
					el.bar.setAttribute( 'aria-valuenow', String( data.percent ) );
				}
				if ( el.percent ) {
					el.percent.textContent = data.percent + '%';
				}
			}

			if ( el.detail ) {
				el.detail.textContent = data.detail || '';
			}

			// Trust the server's clock over the local one; they drift apart if the
			// tab was suspended in the background.
			if ( typeof data.elapsed === 'number' ) {
				elapsed = data.elapsed;
				paintElapsed();
			}

			if ( el.stalled ) {
				el.stalled.hidden = ! data.stalled;

				if ( data.stalled && ! saidStall ) {
					saidStall = true;
					speak( el.stalled.textContent );
				}
			}

			if ( data.running && data.step !== saidStep && data.step_label && data.label ) {
				saidStep = data.step;
				speak( data.step_label + ': ' + data.label );
			}
		}

		/**
		 * Advance the sync one slice, then schedule the next poll.
		 */
		function tick() {
			if ( finished ) {
				return;
			}

			var body = 'action=' + encodeURIComponent( action ) + '&nonce=' + encodeURIComponent( nonce );

			window
				.fetch( window.ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body,
				} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					if ( ! result || ! result.success || ! result.data ) {
						// Something rejected the request - reload so the server can
						// render whatever the real state is, including any error.
						window.location.reload();
						return;
					}

					render( result.data );

					if ( result.data.stopped ) {
						// The count check stopped the run: printed, said once, and the poll ends here.
						end();
						printStop( result.data.guard && result.data.guard.sentence );
						speak( result.data.guard && result.data.guard.sentence );
					} else if ( result.data.running ) {
						window.setTimeout( tick, poll * 1000 );
					} else {
						// Done: said, then, after a moment to be heard, reload to show the completed report.
						end();
						speak( root.getAttribute( 'data-finished' ) );
						window.setTimeout( function () {
							window.location.reload();
						}, FINISH_PAUSE );
					}
				} )
				.catch( function () {
					// A dropped request is not fatal - cron is still running the
					// sync, so back off and try again rather than giving up.
					window.setTimeout( tick, poll * 2000 );
				} );
		}

		window.setInterval( function () {
			if ( ! finished ) {
				elapsed += 1;
				paintElapsed();
			}
		}, 1000 );

		root.setAttribute( 'aria-busy', 'true' );
		tick();
	}

	/**
	 * Reload the page after a set interval, for panels that ask for it.
	 */
	function initReload() {
		var target = document.querySelector( '[data-wpcpm-reload]' );

		if ( ! target ) {
			return;
		}

		var seconds = parseInt( target.getAttribute( 'data-wpcpm-reload' ), 10 );

		if ( isNaN( seconds ) || seconds < 5 ) {
			seconds = 15;
		}

		window.setTimeout( function () {
			window.location.reload();
		}, seconds * 1000 );
	}

	/**
	 * Tell a screen reader something, politely, through WordPress's own live region (`wp-a11y`, a
	 * dependency of this script). Nothing happens on a page without it.
	 *
	 * @param {string} text What to say.
	 */
	function speak( text ) {
		if ( text && window.wp && window.wp.a11y && 'function' === typeof window.wp.a11y.speak ) {
			window.wp.a11y.speak( text, 'polite' );
		}
	}

	/**
	 * Wire every invitations card that is sending (1.123.58).
	 *
	 * The card reloaded the whole screen every 30 seconds while a bulk run sent, which threw away a
	 * typed search, ticked rows and a screen reader's place (ADM-01, LOG-04, WCAG 2.2.1). It now
	 * draws a panel marked `data-wpcpm-invite-poll`, which this reads every `data-poll` seconds
	 * through `WPCPM_Mail::ACTION_PROGRESS` and moves in place.
	 *
	 * @return {number} How many panels were wired.
	 */
	function initInvites() {
		var panels = document.querySelectorAll( '[data-wpcpm-invite-poll]' );
		var i;

		if ( ! panels.length || typeof window.ajaxurl === 'undefined' ) {
			return 0;
		}

		for ( i = 0; i < panels.length; i++ ) {
			pollInvites( panels[ i ] );
		}

		return panels.length;
	}

	/**
	 * Keep one invitations panel current until its run finishes, its request is refused, or the
	 * network stays away.
	 *
	 * - The count, the bar, the line under it and the Stop button's count move on every answer, the
	 *   button in the words of the panel's `data-stop-label` (1.123.58, trial T10); the panel is no live region,
	 *   so they are not read out every ten seconds. A screen reader is told "Invitations sent: N of M"
	 *   as the run passes each quarter, and once at the finish (`speak()`); the first answer sets the
	 *   quarter the page opened at, unsaid.
	 * - At the finish the count says what was sent, the line under the bar says the run has finished
	 *   (the panel's `data-finished`, a notice a sighted person sees), the Stop form is hidden,
	 *   `aria-busy` goes false, and nothing more is asked.
	 * - A refused answer (an expired nonce on a tab left open overnight, a signed-out session) stops the
	 *   panel with the sentence in its `data-stopped`; the card's link to refresh the page stays.
	 * - A request that fails is asked again after twice the wait, up to a minute, and after five in a
	 *   row the panel stops as a refused one does.
	 * - While the tab is hidden nothing is asked; the poll that came due is asked when the tab is
	 *   shown again.
	 *
	 * @param {Element} panel The panel element.
	 */
	function pollInvites( panel ) {
		var action = panel.getAttribute( 'data-action' );
		var nonce = panel.getAttribute( 'data-nonce' );
		var wait = ( parseInt( panel.getAttribute( 'data-poll' ), 10 ) || 10 ) * 1000;
		var el = {
			count: panel.querySelector( '[data-wpcpm-invite-count]' ),
			bar: panel.querySelector( '[data-wpcpm-invite-bar]' ),
			fill: panel.querySelector( '[data-wpcpm-invite-fill]' ),
			detail: panel.querySelector( '[data-wpcpm-invite-detail]' ),
			stop: panel.querySelector( 'form' ),
			stopButton: panel.querySelector( '[data-wpcpm-invite-stop]' ),
		};
		var stopLabel = panel.getAttribute( 'data-stop-label' );
		var total = parseInt( panel.getAttribute( 'data-total' ), 10 ) || 0;
		var told = -1;
		var failures = 0;
		var stopped = false;
		var due = false;

		function end( sentence ) {
			stopped = true;
			panel.setAttribute( 'aria-busy', 'false' );

			if ( sentence && el.detail ) {
				el.detail.textContent = sentence;
			}
		}

		function render( data ) {
			var quarter = data.total > 0 ? Math.floor( ( data.sent / data.total ) * 4 ) : 0;

			// A run that is gone (stopped or cleared in another tab) answers a total of 0, or less than
			// the panel's own: it did not finish. The count and the bar stay where they were.
			if ( ! data.running && ( ! data.total || data.total < total ) ) {
				speak( panel.getAttribute( 'data-cleared' ) );
				end( panel.getAttribute( 'data-cleared' ) );

				return;
			}

			if ( el.count && data.count ) {
				el.count.textContent = data.running ? data.count : data.said;
			}

			if ( typeof data.percent === 'number' ) {
				if ( el.fill ) {
					el.fill.style.width = data.percent + '%';
				}
				if ( el.bar ) {
					el.bar.setAttribute( 'aria-valuenow', String( data.percent ) );
				}
			}

			if ( el.detail ) {
				el.detail.textContent = data.detail || '';
			}

			// The Stop button counts what is still queued as the line above it does, in the words the
			// server printed it with (`data-stop-label`), rather than keeping the page load's count.
			if ( data.running && el.stopButton && stopLabel && typeof data.waiting === 'number' ) {
				el.stopButton.textContent = stopLabel.replace( '%d', String( data.waiting ) );
			}

			if ( ! data.running ) {
				speak( data.said );

				if ( el.stop ) {
					el.stop.hidden = true;
				}

				end( panel.getAttribute( 'data-finished' ) );

				return;
			}

			if ( told >= 0 && quarter > told && quarter < 4 ) {
				speak( data.said );
			}

			told = Math.max( told, quarter );
		}

		function later( ms ) {
			window.setTimeout( function () {
				if ( document.hidden ) {
					due = true;
					return;
				}

				ask();
			}, ms );
		}

		function ask() {
			if ( stopped ) {
				return;
			}

			due = false;

			window
				.fetch( window.ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: 'action=' + encodeURIComponent( action ) + '&nonce=' + encodeURIComponent( nonce ),
				} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					failures = 0;

					if ( ! result || ! result.success || ! result.data ) {
						end( panel.getAttribute( 'data-stopped' ) );
						return;
					}

					render( result.data );

					if ( ! stopped ) {
						later( wait );
					}
				} )
				.catch( function () {
					failures += 1;

					if ( failures >= 5 ) {
						end( panel.getAttribute( 'data-stopped' ) );
						return;
					}

					later( Math.min( 60000, wait * Math.pow( 2, failures ) ) );
				} );
		}

		document.addEventListener( 'visibilitychange', function () {
			if ( ! document.hidden && due ) {
				ask();
			}
		} );

		ask();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initInvites();

		// The live poll supersedes a blunt reload, so only fall back when absent.
		if ( 0 === initProgress() ) {
			initReload();
		}
	} );
}() );
