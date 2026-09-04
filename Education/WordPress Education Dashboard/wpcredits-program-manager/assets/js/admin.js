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
		};

		var elapsedLabel = el.elapsed ? el.elapsed.getAttribute( 'data-label' ) || '%s' : '%s';
		var elapsed = 0;
		var finished = false;

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

					if ( result.data.running ) {
						window.setTimeout( tick, poll * 1000 );
					} else {
						// Done: reload to show the completed report.
						finished = true;
						window.location.reload();
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

	document.addEventListener( 'DOMContentLoaded', function () {
		// The live poll supersedes a blunt reload, so only fall back when absent.
		if ( 0 === initProgress() ) {
			initReload();
		}
	} );
}() );
