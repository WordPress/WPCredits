/**
 * WPCC-Tracker admin screen: progressive-enhancement AJAX sync with a real,
 * honest progress display.
 *
 * If this file fails to load, or JavaScript is disabled, #wpcct-sync-form
 * submits normally to admin-post.php and the sync still runs - this script
 * only intercepts that submit when it can actually run.
 *
 * Every value rendered here is set via textContent, never innerHTML, so
 * nothing this script writes into the DOM needs separate HTML-escaping.
 *
 * Requires the wpcctAdmin object localized by WPCCT_Settings::enqueue_assets()
 * (ajaxUrl, nonce, action names, and translated strings).
 */
( function () {
	'use strict';

	if ( typeof window.wpcctAdmin === 'undefined' ) {
		return;
	}

	/**
	 * Stage list. Read from the server (WPCCT_Sync::STAGES, localized as
	 * wpcctAdmin.stages) so PHP stays the single source of truth and adding a
	 * stage there rescales the bar here automatically. The literal is only a
	 * fallback for an older localized payload that carries no stages.
	 */
	var STAGES = ( wpcctAdmin.stages && wpcctAdmin.stages.length )
		? wpcctAdmin.stages
		: [ 'starting', 'events', 'coords', 'saving', 'done' ];

	function indexOf( list, value ) {
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ] === value ) {
				return i;
			}
		}
		return -1;
	}

	function stageIndex( stage ) {
		var i = indexOf( STAGES, stage );
		return i === -1 ? 0 : i;
	}

	/**
	 * POST an admin-ajax action with the shared nonce and hand the parsed
	 * JSON response (or null on a malformed/network failure) to callback.
	 */
	function post( action, callback ) {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', wpcctAdmin.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.onload = function () {
			var data = null;
			try {
				data = JSON.parse( xhr.responseText );
			} catch ( e ) {
				data = null;
			}
			callback( xhr.status, data );
		};
		xhr.onerror = function () {
			callback( 0, null );
		};
		xhr.send( 'action=' + encodeURIComponent( action ) + '&nonce=' + encodeURIComponent( wpcctAdmin.nonce ) );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'wpcct-sync-form' );
		var submitBtn = document.getElementById( 'wpcct-sync-submit' );
		var panel = document.getElementById( 'wpcct-sync-progress' );
		var fill = document.getElementById( 'wpcct-sync-progress-fill' );
		var stageText = document.getElementById( 'wpcct-sync-progress-stage' );

		if ( ! form || ! panel || ! fill || ! stageText ) {
			return;
		}

		var polling = false;
		var pollTimer = null;

		// Server clock (seconds) as of the first reading after this click, used
		// to date stale readings. Never Date.now(): started_at is stamped by
		// the server, and a browser clock a few seconds behind the server's
		// would make every reading of this run look stale, leaving the bar
		// stuck on "Starting…" while the sync quietly succeeded.
		var serverBaseline = 0;

		// Has the poller seen a real stage from the server yet? Distinguishes
		// "the start request was rejected outright" from "the start request
		// outlived a PHP/proxy timeout while the sync carried on running".
		var sawStage = false;

		function stopPolling() {
			polling = false;
			if ( pollTimer ) {
				window.clearTimeout( pollTimer );
				pollTimer = null;
			}
		}

		function setFill( pct, isError ) {
			fill.className = isError
				? 'wpcct-sync-progress__fill wpcct-sync-progress__fill--error'
				: 'wpcct-sync-progress__fill';
			fill.style.width = pct + '%';
		}

		function setStageText( text, isError ) {
			stageText.className = isError
				? 'wpcct-sync-progress__stage wpcct-sync-progress__stage--error'
				: 'wpcct-sync-progress__stage';
			stageText.textContent = text;
		}

		/**
		 * Render one progress reading from the server. Only ever shows a
		 * stage the sync has actually reached (per the returned "stage"),
		 * never an interpolated or time-based value - the bar's fill is a
		 * discrete step position (current stage index / total stages), not
		 * an animation.
		 */
		function renderProgress( p ) {
			if ( ! p || ! p.stage ) {
				return;
			}

			// Both clocks in this comparison are the server's: p.now is the
			// server time this reading was taken, and the first one seen after
			// the click is close enough to "when this run started". A reading
			// whose started_at predates that by more than a couple of seconds
			// belongs to a previous run (e.g. a poll that raced ahead of the
			// not-yet-processed start request) - do not show its stale
			// "done"/"error" as if it were this run's outcome.
			if ( ! serverBaseline ) {
				serverBaseline = p.now ? p.now : ( p.started_at || 0 );
			}

			if ( p.started_at && serverBaseline && p.started_at < ( serverBaseline - 2 ) ) {
				setFill( 100 / STAGES.length, false );
				setStageText( wpcctAdmin.i18n.starting, false );
				return;
			}

			// 'idle' is what the server reports when no progress record
			// exists at all (first sync after install, or a poll that beat
			// the start request's worker to it). It is not a reading of this
			// run: leave the "Starting…" display in place, and - the reason
			// this guard matters - do not let it count as a stage the poller
			// has observed, or the start request's genuine rejection below
			// would be swallowed and the bar would sit blank forever.
			if ( 'error' !== p.stage && indexOf( STAGES, p.stage ) === -1 ) {
				return;
			}

			sawStage = true;

			if ( 'error' === p.stage ) {
				setFill( ( ( stageIndex( ( p.counts && p.counts.failed_stage ) || 'starting' ) + 1 ) / STAGES.length ) * 100, true );
				setStageText( p.message || wpcctAdmin.i18n.error, true );
				stopPolling();
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}
				return;
			}

			setFill( ( ( stageIndex( p.stage ) + 1 ) / STAGES.length ) * 100, false );
			setStageText( p.message || '', false );

			if ( 'done' === p.stage ) {
				var counts = p.counts || {};
				var summary = wpcctAdmin.i18n.done;
				if ( typeof counts.events !== 'undefined' ) {
					summary += ' ' + counts.events + ' ' + wpcctAdmin.i18n.eventsLabel + ',';
				}
				if ( typeof counts.markers !== 'undefined' ) {
					summary += ' ' + counts.markers + ' ' + wpcctAdmin.i18n.markersLabel + '.';
				}
				setStageText( summary, false );
				stopPolling();

				// Refresh the status/unmapped-countries panels below with
				// the data this sync just wrote - a full reload is the
				// simplest honest way to do that, and matches what the
				// no-JS fallback already does via its redirect.
				window.setTimeout( function () {
					window.location.reload();
				}, 1200 );
			}
		}

		function poll() {
			if ( ! polling ) {
				return;
			}
			post( wpcctAdmin.actions.progress, function ( status, data ) {
				if ( data && data.success ) {
					renderProgress( data.data );
				}
				if ( polling ) {
					pollTimer = window.setTimeout( poll, 700 );
				}
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			if ( submitBtn ) {
				submitBtn.disabled = true;
			}

			panel.hidden = false;
			setFill( 0, false );
			setStageText( wpcctAdmin.i18n.starting, false );

			serverBaseline = 0;
			sawStage = false;
			polling = true;
			pollTimer = window.setTimeout( poll, 300 );

			post( wpcctAdmin.actions.start, function ( status, data ) {
				// The polling loop above is the source of truth for stage,
				// detail and counts. This response only matters when the
				// request itself was rejected (bad nonce, no permission)
				// before the sync could write any progress at all - in
				// that case polling would otherwise be left showing
				// "Starting…" forever.
				if ( ! data || ! data.success ) {
					// This request blocks for the whole sync, so it can also
					// be cut short by a PHP or proxy timeout while the sync
					// itself runs on to a successful finish. If the poller has
					// already seen a real stage, that is what happened: keep
					// polling and let the server report the true outcome
					// rather than painting a failure over a working sync.
					if ( sawStage ) {
						return;
					}

					stopPolling();
					setFill( 0, true );
					setStageText( ( data && data.data && data.data.message ) || wpcctAdmin.i18n.error, true );
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}
				}
			} );
		} );
	} );
}() );
