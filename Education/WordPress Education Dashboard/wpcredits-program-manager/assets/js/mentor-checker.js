/**
 * Drives the Mentor Checker screen: batched runs and promotions over admin-ajax.
 *
 * The run is deliberately batched - each mentor can cost several requests to
 * WordPress.org, so one long PHP request would risk a timeout. Because a batch can
 * take tens of seconds, the whole queue is listed up front and each row resolves in
 * place, so the screen is never blank while work is happening.
 */
( function () {
	'use strict';

	var data = window.wpcpmCheckerData || {};
	var i18n = data.i18n || {};

	var runDry = document.getElementById( 'wpcpm-checker-run-dry' );
	var runApply = document.getElementById( 'wpcpm-checker-run-apply' );
	var promoteAll = document.getElementById( 'wpcpm-checker-promote-all' );
	var statusEl = document.getElementById( 'wpcpm-checker-status' );
	var progress = document.getElementById( 'wpcpm-checker-progress' );
	var progressBar = document.getElementById( 'wpcpm-checker-progress-bar' );
	var fill = document.getElementById( 'wpcpm-checker-progress-fill' );
	var labelEl = document.getElementById( 'wpcpm-checker-progress-label' );
	var countEl = document.getElementById( 'wpcpm-checker-progress-count' );
	var metaEl = document.getElementById( 'wpcpm-checker-progress-meta' );
	var etaEl = document.getElementById( 'wpcpm-checker-progress-eta' );
	var summary = document.getElementById( 'wpcpm-checker-summary' );
	var tbody = document.getElementById( 'wpcpm-checker-results-body' );
	var eligibleCount = document.getElementById( 'wpcpm-checker-eligible-count' );

	if ( ! runDry || ! tbody ) {
		return;
	}

	var busy = false;
	var startedAt = 0;
	var ticker = null;

	/**
	 * Fill a translated template. Handles %s, %d, positional %1$s / %1$d, and %%.
	 *
	 * @param {string} template Format string from the localized strings.
	 * @param {Array}  values   Replacements, in order for the non-positional forms.
	 * @return {string} The formatted string.
	 */
	function sprintf( template, values ) {
		var i = 0;
		return String( template ).replace( /%(\d+)\$[ds]|%d|%s|%%/g, function ( match, index ) {
			if ( '%%' === match ) {
				return '%';
			}
			if ( index ) {
				return values[ parseInt( index, 10 ) - 1 ];
			}
			return values[ i++ ];
		} );
	}

	function announce( message ) {
		if ( statusEl ) {
			statusEl.textContent = message;
		}
	}

	function setBusy( state ) {
		busy = state;
		[ runDry, runApply, promoteAll ].forEach( function ( button ) {
			if ( button ) {
				button.disabled = state;
			}
		} );
	}

	function post( action, body ) {
		var params = new URLSearchParams();
		params.set( 'action', action );
		params.set( 'nonce', data.nonce );

		Object.keys( body || {} ).forEach( function ( key ) {
			params.set( key, body[ key ] );
		} );

		return fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString()
		} )
			.then( function ( response ) {
				return response.json().catch( function () {
					throw new Error( 'HTTP ' + response.status );
				} );
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success ) {
					var message = payload && payload.data && payload.data.message
						? payload.data.message
						: 'Unexpected response.';
					throw new Error( message );
				}
				return payload.data;
			} );
	}

	function clearTable() {
		tbody.innerHTML = '';
	}

	function allRows() {
		return tbody.querySelectorAll( 'tr[data-record-id]' );
	}

	/**
	 * Insert a server-rendered row, replacing any earlier row for the same record.
	 *
	 * @param {Object} row Result row with an `html` fragment.
	 */
	function upsertRow( row ) {
		var placeholder = tbody.querySelector( '.wpcpm-checker-empty-row' );
		if ( placeholder ) {
			placeholder.remove();
		}

		var wrapper = document.createElement( 'tbody' );
		wrapper.innerHTML = row.html;
		var fresh = wrapper.querySelector( 'tr' );
		if ( ! fresh ) {
			return;
		}

		var existing = row.record_id
			? tbody.querySelector( 'tr[data-record-id="' + row.record_id + '"]' )
			: null;

		if ( existing ) {
			existing.replaceWith( fresh );
		} else {
			tbody.appendChild( fresh );
		}
	}

	/**
	 * Flag the rows whose batch is currently in flight.
	 *
	 * The row markup already carries both a "Queued" and a "Checking…" label; the
	 * class decides which one CSS reveals.
	 *
	 * @param {number} from First queue index in the batch.
	 * @param {number} size Number of mentors in the batch.
	 */
	function markChecking( from, size ) {
		var rows = allRows();

		rows.forEach( function ( row, index ) {
			row.classList.toggle( 'is-checking', index >= from && index < from + size );
		} );
	}

	function clearChecking() {
		allRows().forEach( function ( row ) {
			row.classList.remove( 'is-checking' );
		} );
	}

	/**
	 * Count the current state of the table.
	 *
	 * @return {Object} Tallies keyed by outcome.
	 */
	function tally() {
		var counts = { queued: 0, checked: 0, completed: 0, eligible: 0, promoted: 0, problems: 0 };

		allRows().forEach( function ( row ) {
			if ( row.querySelector( '.wpcpm-checker-pill--pending' ) ) {
				counts.queued++;
				return;
			}

			counts.checked++;

			if ( row.querySelector( '.wpcpm-checker-pill--yes' ) ) {
				counts.completed++;
			}
			if ( row.querySelector( '.wpcpm-checker-pill--warn' ) ) {
				counts.problems++;
			}

			var action = row.getAttribute( 'data-action' );
			if ( 'eligible' === action ) {
				counts.eligible++;
			} else if ( 'promoted' === action ) {
				counts.promoted++;
			}
		} );

		return counts;
	}

	/**
	 * Recount the summary tiles from what is currently in the table.
	 *
	 * @return {Object} The tallies, so callers can reuse them.
	 */
	function refreshSummary() {
		var counts = tally();

		if ( summary ) {
			summary.hidden = 0 === counts.checked;
			Object.keys( counts ).forEach( function ( key ) {
				var el = summary.querySelector( '[data-stat="' + key + '"]' );
				if ( el ) {
					el.textContent = counts[ key ];
				}
			} );
		}

		if ( eligibleCount ) {
			eligibleCount.textContent = '(' + counts.eligible + ')';
		}
		if ( promoteAll ) {
			promoteAll.disabled = busy || 0 === counts.eligible;
		}

		return counts;
	}

	function elapsedText() {
		var seconds = startedAt ? Math.round( ( Date.now() - startedAt ) / 1000 ) : 0;
		var minutes = Math.floor( seconds / 60 );
		var rest = seconds % 60;
		return minutes + ':' + ( rest < 10 ? '0' : '' ) + rest;
	}

	/**
	 * Estimate the time left from how long the finished mentors actually took.
	 *
	 * @param {number} done  Mentors checked so far.
	 * @param {number} total Mentors queued.
	 * @return {string} A human phrase, or '' when there is not enough data yet.
	 */
	function etaText( done, total ) {
		if ( ! startedAt || done < 2 || done >= total ) {
			return '';
		}

		var perMentor = ( Date.now() - startedAt ) / done;
		var remaining = ( total - done ) * perMentor / 1000;

		if ( remaining < 45 ) {
			return i18n.etaSoon;
		}

		var minutes = Math.round( remaining / 60 );
		return 1 === minutes ? i18n.etaOne : sprintf( i18n.etaMany, [ minutes ] );
	}

	/**
	 * Repaint the progress panel.
	 *
	 * @param {number} done  Mentors checked so far.
	 * @param {number} total Mentors queued.
	 * @param {string} label Heading for the current phase.
	 */
	function paintProgress( done, total, label ) {
		if ( ! progress ) {
			return;
		}

		progress.hidden = false;

		var percent = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;

		if ( fill ) {
			fill.style.width = percent + '%';
		}
		if ( progressBar ) {
			progressBar.setAttribute( 'aria-valuenow', String( percent ) );
		}
		if ( labelEl && undefined !== label ) {
			labelEl.textContent = label;
		}
		if ( countEl ) {
			countEl.textContent = total > 0 ? sprintf( i18n.count, [ done, total, percent ] ) : '';
		}

		var counts = tally();
		if ( metaEl ) {
			metaEl.textContent = sprintf( i18n.meta, [ counts.completed, counts.problems, elapsedText() ] );
		}
		if ( etaEl ) {
			etaEl.textContent = etaText( done, total );
		}
	}

	/**
	 * Keep the elapsed clock moving between batch responses.
	 *
	 * @param {number} done  Mentors checked so far.
	 * @param {number} total Mentors queued.
	 */
	function startTicker( done, total ) {
		stopTicker();
		ticker = window.setInterval( function () {
			paintProgress( done, total );
		}, 1000 );
	}

	function stopTicker() {
		if ( ticker ) {
			window.clearInterval( ticker );
			ticker = null;
		}
	}

	/**
	 * Walk the queue one batch at a time until the server reports it is done.
	 *
	 * @param {string} runId  Run identifier.
	 * @param {number} offset Queue offset to resume from.
	 * @param {number} total  Total mentors queued.
	 * @param {number} size   Mentors per batch.
	 */
	function processBatch( runId, offset, total, size ) {
		markChecking( offset, size );
		paintProgress( offset, total, i18n.checking );
		startTicker( offset, total );

		return post( 'wpcpm_checker_process_batch', { run_id: runId, offset: offset } ).then( function ( batch ) {
			batch.rows.forEach( upsertRow );
			refreshSummary();
			paintProgress( batch.next_offset, batch.total, i18n.checking );
			announce( sprintf( i18n.progress, [ batch.next_offset, batch.total ] ) );

			if ( ! batch.done ) {
				return processBatch( runId, batch.next_offset, batch.total, size );
			}

			stopTicker();
			clearChecking();
			paintProgress( batch.total, batch.total, i18n.doneLabel );
			announce( sprintf( i18n.done, [ batch.total ] ) );
			return batch;
		} );
	}

	function startRun( apply ) {
		if ( busy ) {
			return;
		}
		if ( apply && ! window.confirm( i18n.confirmApply ) ) {
			return;
		}

		setBusy( true );
		clearTable();
		refreshSummary();
		startedAt = Date.now();
		announce( i18n.starting );
		paintProgress( 0, 0, i18n.starting );

		post( 'wpcpm_checker_start_run', { apply: apply ? '1' : '' } )
			.then( function ( start ) {
				if ( 0 === start.total ) {
					stopTicker();
					paintProgress( 0, 0, i18n.doneLabel );
					announce( sprintf( i18n.done, [ 0 ] ) );
					return null;
				}

				// List every mentor before the first profile read, so the run is visible.
				start.queue.forEach( upsertRow );
				paintProgress( 0, start.total, i18n.checking );

				return processBatch( start.run_id, 0, start.total, start.batch_size || 1 );
			} )
			.catch( function ( error ) {
				stopTicker();
				clearChecking();
				if ( labelEl ) {
					labelEl.textContent = i18n.stoppedLabel;
				}
				announce( sprintf( i18n.failed, [ error.message ] ) );
			} )
			.finally( function () {
				stopTicker();
				setBusy( false );
				refreshSummary();
			} );
	}

	runDry.addEventListener( 'click', function () {
		startRun( false );
	} );

	if ( runApply ) {
		runApply.addEventListener( 'click', function () {
			startRun( true );
		} );
	}

	if ( promoteAll ) {
		promoteAll.addEventListener( 'click', function () {
			if ( busy || ! window.confirm( i18n.confirmApply ) ) {
				return;
			}

			var pending = tally().eligible;

			setBusy( true );
			startedAt = Date.now();
			announce( i18n.promoting );
			paintProgress( 0, pending, i18n.promoting );

			post( 'wpcpm_checker_promote_all', {} )
				.then( function ( result ) {
					result.rows.forEach( upsertRow );
					paintProgress( pending, pending, i18n.doneLabel );
				} )
				.catch( function ( error ) {
					if ( labelEl ) {
						labelEl.textContent = i18n.stoppedLabel;
					}
					announce( sprintf( i18n.failed, [ error.message ] ) );
				} )
				.finally( function () {
					setBusy( false );
					refreshSummary();
				} );
		} );
	}

	// Per-row promote buttons are rendered by the server, so delegate.
	tbody.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.wpcpm-checker-promote' );
		if ( ! button || busy ) {
			return;
		}

		var recordId = button.getAttribute( 'data-record-id' );
		if ( ! recordId || ! window.confirm( i18n.confirmOne ) ) {
			return;
		}

		var row = button.closest( 'tr' );

		setBusy( true );
		button.disabled = true;
		button.textContent = i18n.promoting;
		if ( row ) {
			row.classList.add( 'is-checking' );
		}

		post( 'wpcpm_checker_promote', { record_id: recordId } )
			.then( function ( result ) {
				upsertRow( result.row );
			} )
			.catch( function ( error ) {
				announce( sprintf( i18n.failed, [ error.message ] ) );
				button.disabled = false;
				button.textContent = i18n.promote;
				if ( row ) {
					row.classList.remove( 'is-checking' );
				}
			} )
			.finally( function () {
				setBusy( false );
				refreshSummary();
			} );
	} );

	refreshSummary();
}() );
