/*
 * WPCC-Tracker - front-end renderer.
 *
 * Reads window.WPCCT_DATA (the synced data blob, inlined once by PHP) and
 * finds every mount `[data-wpcct-sec="KEY"]` on the page - whether it came
 * from the combined [wpcc_tracker] view or a single section block - and
 * dispatches each one to its section builder.
 *
 * Builder contract (Tasks 11-13 register these below, in BUILDERS):
 *
 *   function builder( mount, data, fmt )
 *     - mount : the DOM element for this section, i.e. the element carrying
 *               data-wpcct-sec="<key>". Empty when the builder runs.
 *     - data  : window.WPCCT_DATA - the full synced blob (totals, regions,
 *               pipeline, timeline, markers, events, unmapped_countries).
 *     - fmt   : the shared formatter helpers below (esc, num, date,
 *               monthLabel).
 *
 *   A builder renders synchronously into `mount` (innerHTML and/or DOM
 *   methods) and returns nothing. Any third-party text (event names,
 *   institutions, cities, countries) MUST go through fmt.esc() before it
 *   reaches innerHTML.
 *
 * A mount whose key has no registered builder - including every key today,
 * since Task 9 ships the registry empty - renders a harmless placeholder
 * instead of throwing.
 *
 * Plain ES5, no imports, no build step.
 */
( function () {
	'use strict';

	if ( typeof window.WPCCT_DATA === 'undefined' ) {
		return;
	}

	/* ---- shared formatters ---- */

	// Every string that reaches innerHTML goes through this first. The
	// synced blob carries third-party text (Airtable free text, organiser
	// input), so it is never safe to interpolate raw.
	function esc( value ) {
		var div = document.createElement( 'div' );
		div.textContent = ( value === null || typeof value === 'undefined' ) ? '' : String( value );
		return div.innerHTML;
	}

	// Locale-formatted integer, e.g. 6490 -> "6,490". Non-numeric input
	// (missing/blank fields are common in the synced blob) falls back to 0.
	function num( value ) {
		var n = Number( value );
		if ( isNaN( n ) ) {
			n = 0;
		}
		return n.toLocaleString();
	}

	// "YYYY-MM-DD" (or "YYYY-MM") -> a short human date, e.g. "Aug 27, 2026".
	// Falls back to the raw string for anything that doesn't parse cleanly.
	function date( value ) {
		if ( ! value ) {
			return '';
		}
		var parts = String( value ).split( '-' );
		if ( parts.length < 2 ) {
			return String( value );
		}
		var y = parseInt( parts[ 0 ], 10 );
		var m = parseInt( parts[ 1 ], 10 ) - 1;
		var d = parts.length > 2 ? parseInt( parts[ 2 ], 10 ) : 1;
		var dt = new Date( y, m, d );
		if ( isNaN( dt.getTime() ) ) {
			return String( value );
		}
		var opts = { year: 'numeric', month: 'short' };
		if ( parts.length > 2 ) {
			opts.day = 'numeric';
		}
		return dt.toLocaleDateString( undefined, opts );
	}

	// "YYYY-MM" -> "Aug 2026", for timeline axis labels.
	function monthLabel( key ) {
		var parts = String( key ).split( '-' );
		if ( parts.length !== 2 ) {
			return String( key );
		}
		var y = parseInt( parts[ 0 ], 10 );
		var m = parseInt( parts[ 1 ], 10 ) - 1;
		var dt = new Date( y, m, 1 );
		if ( isNaN( dt.getTime() ) ) {
			return String( key );
		}
		return dt.toLocaleDateString( undefined, { year: 'numeric', month: 'short' } );
	}

	var fmt = {
		esc: esc,
		num: num,
		date: date,
		monthLabel: monthLabel
	};

	/* ---- section builders ----
	 * Tasks 11-13 add one function per section here (scale, regions, map,
	 * timeline, pipeline, events) and register each in BUILDERS below. Task 9
	 * ships the registry, the dispatcher and the placeholder only.
	 */

	// The five regions this data set groups events into, in the same order as
	// the PHP-side WPCCT_Normalize::REGIONS - so a legitimately-zero region
	// (North America) still gets a row/bar instead of being iterated away.
	var REGION_ORDER = [ 'Asia', 'Europe', 'Africa', 'Latin America and Caribbean', 'North America' ];

	// The calendar year the synced blob was generated in. Drives the
	// "Completed in <year>" tile label so it never hardcodes a year.
	function scaleYear( data ) {
		var year = new Date().getFullYear();
		if ( data && data.generated ) {
			var generated = new Date( data.generated );
			if ( ! isNaN( generated.getTime() ) ) {
				year = generated.getFullYear();
			}
		}
		return year;
	}

	// Task 11, section 1: six stat tiles from data.totals.
	function buildScale( mount, data, fmt ) {
		var totals = ( data && data.totals ) || {};
		var year = scaleYear( data );

		var tiles = [
			{ label: 'Completed events (all time)', value: totals.completed, hl: true },
			{ label: 'Completed in ' + year, value: totals.completed_this_year },
			{ label: 'Scheduled now', value: totals.scheduled },
			{ label: 'In setup & planning', value: totals.planning },
			{ label: 'Total attendees', value: totals.attendees, note: 'at completed events' },
			{ label: 'Countries reached', value: totals.countries }
		];

		var html = '<div class="wpcct-tracker__stats">';
		for ( var i = 0; i < tiles.length; i++ ) {
			var tile = tiles[ i ];
			html += '<div class="wpcct-tracker__stat' + ( tile.hl ? ' wpcct-tracker__stat--hl' : '' ) + '">';
			html += '<div class="wpcct-tracker__stat-num">' + fmt.num( tile.value ) + '</div>';
			html += '<div class="wpcct-tracker__stat-label">' + fmt.esc( tile.label ) + '</div>';
			if ( tile.note ) {
				html += '<div class="wpcct-tracker__stat-note">' + fmt.esc( tile.note ) + '</div>';
			}
			html += '</div>';
		}
		html += '</div>';

		mount.innerHTML = html;
	}

	// Task 11, section 2: grouped bar chart + full table over data.regions.
	function buildRegions( mount, data, fmt ) {
		var regions = ( data && data.regions ) || {};
		var empty = { completed: 0, scheduled: 0, planning: 0, institutions: 0, attendees: 0 };

		var labels = [];
		var completedSeries = [];
		var scheduledSeries = [];
		var planningSeries = [];
		var rows = '';

		// The totals row is derived by summing these rendered rows, not by
		// reading data.totals - one live event has a blank Country and is
		// counted in data.totals.planning but excluded from the regional
		// roll-up, so the two can legitimately differ by that one event.
		var totals = { completed: 0, scheduled: 0, planning: 0, institutions: 0, attendees: 0 };

		for ( var i = 0; i < REGION_ORDER.length; i++ ) {
			var key = REGION_ORDER[ i ];
			var r = regions[ key ] || empty;

			labels.push( key );
			completedSeries.push( Number( r.completed ) || 0 );
			scheduledSeries.push( Number( r.scheduled ) || 0 );
			planningSeries.push( Number( r.planning ) || 0 );

			totals.completed += Number( r.completed ) || 0;
			totals.scheduled += Number( r.scheduled ) || 0;
			totals.planning += Number( r.planning ) || 0;
			totals.institutions += Number( r.institutions ) || 0;
			totals.attendees += Number( r.attendees ) || 0;

			rows += '<tr>'
				+ '<td>' + fmt.esc( key ) + '</td>'
				+ '<td>' + fmt.num( r.completed ) + '</td>'
				+ '<td>' + fmt.num( r.scheduled ) + '</td>'
				+ '<td>' + fmt.num( r.planning ) + '</td>'
				+ '<td>' + fmt.num( r.institutions ) + '</td>'
				+ '<td>' + fmt.num( r.attendees ) + '</td>'
				+ '</tr>';
		}

		rows += '<tr class="wpcct-tracker__row--total">'
			+ '<td>' + fmt.esc( 'Total' ) + '</td>'
			+ '<td>' + fmt.num( totals.completed ) + '</td>'
			+ '<td>' + fmt.num( totals.scheduled ) + '</td>'
			+ '<td>' + fmt.num( totals.planning ) + '</td>'
			+ '<td>' + fmt.num( totals.institutions ) + '</td>'
			+ '<td>' + fmt.num( totals.attendees ) + '</td>'
			+ '</tr>';

		var html = '';
		html += '<div class="wpcct-tracker__panel">';
		html += '<h3>' + fmt.esc( 'Events by region' ) + '</h3>';
		html += '<p class="wpcct-tracker__panel-sub">' + fmt.esc( 'Completed, scheduled and in-planning Campus Connect events by world region.' ) + '</p>';
		html += '<div class="wpcct-tracker__chart-wrap"><canvas></canvas></div>';
		html += '</div>';

		html += '<div class="wpcct-tracker__table-wrap">';
		html += '<table class="wpcct-tracker__table">';
		html += '<thead><tr>'
			+ '<th>' + fmt.esc( 'Region' ) + '</th>'
			+ '<th>' + fmt.esc( 'Completed' ) + '</th>'
			+ '<th>' + fmt.esc( 'Scheduled' ) + '</th>'
			+ '<th>' + fmt.esc( 'In Planning' ) + '</th>'
			+ '<th>' + fmt.esc( 'Institutions' ) + '</th>'
			+ '<th>' + fmt.esc( 'Attendees' ) + '</th>'
			+ '</tr></thead>';
		html += '<tbody>' + rows + '</tbody>';
		html += '</table>';
		html += '</div>';

		mount.innerHTML = html;

		var canvas = mount.querySelector( 'canvas' );
		if ( canvas && typeof Chart !== 'undefined' ) {
			new Chart( canvas, {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [
						{ label: 'Completed', data: completedSeries, backgroundColor: '#2F7D5B' },
						{ label: 'Scheduled', data: scheduledSeries, backgroundColor: '#B07A22' },
						{ label: 'In Planning', data: planningSeries, backgroundColor: '#2A7B8C' }
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: {
						x: { grid: { display: false } },
						y: { beginAtZero: true, ticks: { precision: 0 } }
					}
				}
			} );
		}
	}

	// Attribute-safe escaping for values that land inside an HTML attribute
	// (e.g. href="..."), not just text content. esc() alone leaves the quote
	// character untouched - fine for text nodes, not safe to drop into an
	// attribute where a stray '"' could break out of it.
	function escAttr( value ) {
		return esc( value ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}

	// Only http(s) (and protocol-relative) URLs are allowed into an href.
	// Third-party free text (here, an event's linked site) could otherwise
	// carry a `javascript:` URL, so anything else is dropped rather than
	// linked.
	function safeUrl( value ) {
		if ( ! value ) {
			return '';
		}
		var trimmed = String( value ).trim();
		if ( /^(https?:)?\/\//i.test( trimmed ) ) {
			return trimmed;
		}
		return '';
	}

	// Task 12, section 1: Leaflet map of data.markers, colour-coded by
	// bucket. Markers only carry coordinates once a Campus Connect event is
	// completed or scheduled, so the bundled seed data ships with an empty
	// list - that empty path is the one that actually renders until the
	// first live sync, and it must read as "not here yet", not as a broken
	// or blank map.
	function buildMap( mount, data, fmt ) {
		var raw = ( data && data.markers ) || [];
		var markers = [];
		var i, m, lat, lng;

		for ( i = 0; i < raw.length; i++ ) {
			m = raw[ i ];
			lat = Number( m && m.lat );
			lng = Number( m && m.lng );
			if ( m && isFinite( lat ) && isFinite( lng ) ) {
				markers.push( m );
			}
		}

		var html = '<div class="wpcct-tracker__panel">';
		html += '<h3>' + fmt.esc( 'Event locations' ) + '</h3>';
		html += '<p class="wpcct-tracker__panel-sub">' + fmt.esc( 'Completed and scheduled Campus Connect events around the world.' ) + '</p>';

		if ( ! markers.length ) {
			html += '<p class="wpcct-tracker__notice">' + fmt.esc( 'Map data arrives with the first sync.' ) + '</p>';
			html += '</div>';
			mount.innerHTML = html;
			return;
		}

		html += '<div class="wpcct-tracker__map"></div>';
		html += '<div class="wpcct-tracker__map-legend">'
			+ '<span class="wpcct-tracker__chip wpcct-tracker__chip--completed">' + fmt.esc( 'Completed' ) + '</span>'
			+ '<span class="wpcct-tracker__chip wpcct-tracker__chip--scheduled">' + fmt.esc( 'Scheduled' ) + '</span>'
			+ '</div>';
		html += '</div>';

		mount.innerHTML = html;

		var mapEl = mount.querySelector( '.wpcct-tracker__map' );
		if ( ! mapEl ) {
			return;
		}

		if ( typeof L === 'undefined' ) {
			mapEl.innerHTML = '<p class="wpcct-tracker__notice">' + fmt.esc( 'Map library failed to load.' ) + '</p>';
			return;
		}

		/*
		 * fadeAnimation is off deliberately. Leaflet 1.9 fades tiles in by
		 * setting opacity 0 and animating up under mix-blend-mode: plus-lighter;
		 * inside this theme the animation never completes, so every tile stays
		 * at opacity 0 and the basemap renders blank while the markers and the
		 * attribution sit on top of nothing. Tiles now simply appear.
		 */
		var map = L.map( mapEl, {
			scrollWheelZoom: false,
			zoomControl: true,
			fadeAnimation: false
		} ).setView( [ 20, 0 ], 2 );
		/*
		 * OpenStreetMap's own tiles, which need no API key. CARTO's basemap CDN
		 * still answers 200 for an anonymous request, but the PNG it returns is
		 * stamped "API KEY REQUIRED" across every tile -- so a status check does
		 * not catch it and only looking at a rendered map does.
		 */
		L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			maxZoom: 19
		} ).addTo( map );

		var icons = {
			completed: L.divIcon( {
				className: 'wpcct-tracker__marker wpcct-tracker__marker--completed',
				html: '<span></span>',
				iconSize: [ 16, 16 ],
				iconAnchor: [ 8, 8 ],
				popupAnchor: [ 0, -10 ]
			} ),
			scheduled: L.divIcon( {
				className: 'wpcct-tracker__marker wpcct-tracker__marker--scheduled',
				html: '<span></span>',
				iconSize: [ 16, 16 ],
				iconAnchor: [ 8, 8 ],
				popupAnchor: [ 0, -10 ]
			} )
		};

		var latlngs = [];
		for ( i = 0; i < markers.length; i++ ) {
			m = markers[ i ];
			lat = Number( m.lat );
			lng = Number( m.lng );
			latlngs.push( [ lat, lng ] );

			var icon = icons[ m.bucket ] || icons.completed;
			var name = fmt.esc( m.name || 'Untitled event' );
			var href = safeUrl( m.url );
			var title = href ? '<a href="' + escAttr( href ) + '" target="_blank" rel="noopener noreferrer">' + name + '</a>' : name;

			var lines = '';
			if ( m.institution ) {
				lines += '<li>' + fmt.esc( m.institution ) + '</li>';
			}
			var where = [];
			if ( m.city ) {
				where.push( fmt.esc( m.city ) );
			}
			if ( m.country ) {
				where.push( fmt.esc( m.country ) );
			}
			if ( where.length ) {
				lines += '<li>' + where.join( ', ' ) + '</li>';
			}
			if ( m.start ) {
				lines += '<li>' + fmt.esc( fmt.date( m.start ) ) + '</li>';
			}
			var attendees = Number( m.attendees ) || 0;
			if ( attendees > 0 ) {
				lines += '<li>' + fmt.num( attendees ) + ' ' + fmt.esc( 'attendees' ) + '</li>';
			}

			var popup = '<h4>' + title + '</h4><ul>' + lines + '</ul>';
			L.marker( [ lat, lng ], { icon: icon } ).addTo( map ).bindPopup( popup, { maxWidth: 280 } );
		}

		if ( latlngs.length === 1 ) {
			map.setView( latlngs[ 0 ], 6 );
		} else if ( latlngs.length > 1 ) {
			map.fitBounds( L.latLngBounds( latlngs ), { padding: [ 24, 24 ], maxZoom: 10 } );
		}

		// The panel can be laid out (e.g. inside a tab, or before webfonts
		// settle) after Leaflet measures its container, which leaves the
		// tile layer mis-sized until something nudges it.
		setTimeout( function () {
			map.invalidateSize();
		}, 200 );
	}

	// Task 12, section 2: Chart.js combo over data.timeline - stacked bars
	// for completed/scheduled events per month, plus a line for cumulative
	// attendance on a second axis.
	function buildTimeline( mount, data, fmt ) {
		var timeline = ( data && data.timeline ) || {};
		var keys = [];
		var key;
		for ( key in timeline ) {
			if ( Object.prototype.hasOwnProperty.call( timeline, key ) ) {
				keys.push( key );
			}
		}
		keys.sort();

		var html = '<div class="wpcct-tracker__panel">';
		html += '<h3>' + fmt.esc( 'Events over time' ) + '</h3>';
		html += '<p class="wpcct-tracker__panel-sub">' + fmt.esc( 'Completed and scheduled events per month, with cumulative attendance at completed events.' ) + '</p>';

		if ( ! keys.length ) {
			html += '<p class="wpcct-tracker__notice">' + fmt.esc( 'Timeline data arrives with the first sync.' ) + '</p>';
			html += '</div>';
			mount.innerHTML = html;
			return;
		}

		html += '<div class="wpcct-tracker__chart-wrap"><canvas></canvas></div>';
		html += '<p class="wpcct-tracker__panel-sub wpcct-tracker__caption">' + fmt.esc( 'Events without a recorded start date are not shown here, though they are still counted in the programme totals.' ) + '</p>';
		html += '</div>';

		mount.innerHTML = html;

		var canvas = mount.querySelector( 'canvas' );
		if ( ! canvas || typeof Chart === 'undefined' ) {
			return;
		}

		var labels = [];
		var completedSeries = [];
		var scheduledSeries = [];
		var cumulativeAttendance = [];
		var running = 0;
		var i, row, monthAttendees;

		for ( i = 0; i < keys.length; i++ ) {
			row = timeline[ keys[ i ] ] || {};
			labels.push( fmt.monthLabel( keys[ i ] ) );
			completedSeries.push( Number( row.completed ) || 0 );
			scheduledSeries.push( Number( row.scheduled ) || 0 );
			monthAttendees = Number( row.attendees ) || 0;
			running += monthAttendees;
			cumulativeAttendance.push( running );
		}

		new Chart( canvas, {
			data: {
				labels: labels,
				datasets: [
					{ type: 'bar', label: 'Completed', data: completedSeries, backgroundColor: '#2F7D5B', stack: 'events', yAxisID: 'y' },
					{ type: 'bar', label: 'Scheduled', data: scheduledSeries, backgroundColor: '#B07A22', stack: 'events', yAxisID: 'y' },
					{ type: 'line', label: 'Cumulative attendance', data: cumulativeAttendance, borderColor: '#3858E9', backgroundColor: '#3858E9', borderWidth: 2, tension: 0.3, pointRadius: 2, yAxisID: 'y1' }
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					x: { stacked: true, grid: { display: false } },
					y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Events' } },
					y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Cumulative attendance' } }
				}
			}
		} );
	}

	// The organiser journey, in order. data.pipeline is a raw status -> count
	// map keyed by whatever string Airtable holds today; this array is the
	// only place that order is asserted, so a status this plugin has never
	// seen yet still has somewhere to land (see the append step below) rather
	// than being silently dropped.
	var PIPELINE_ORDER = [
		'Needs Vetting',
		'Needs Orientation/Interview',
		'Interview/Orientation Scheduled',
		'Approved for Pre-Planning Pending Agreement',
		'Needs to Fill Out Listing',
		'In Pre-Planning',
		'On Hold',
		'Scheduled',
		'Closed'
	];

	// Cancelled and Declined never appear in PIPELINE_ORDER - they are not a
	// step in the journey, they are its two exits, so they render separately
	// under "Did not proceed" rather than as bars in the live funnel.
	var PIPELINE_STOPPED = [ 'Cancelled', 'Declined' ];

	// Task 13, section 1: one horizontal bar per raw organiser status in
	// data.pipeline, in journey order - plus a muted, visually separated
	// "Did not proceed" group for Cancelled/Declined. The spreadsheet this
	// data comes from flattens all of this into a single "in planning"
	// number and hides cancelled/declined entirely, so this block is the
	// only place the real funnel - and the real drop-off - is visible.
	function buildPipeline( mount, data, fmt ) {
		var pipeline = ( data && data.pipeline ) || {};
		var status, count;

		var total = 0;
		for ( status in pipeline ) {
			if ( Object.prototype.hasOwnProperty.call( pipeline, status ) ) {
				total += Number( pipeline[ status ] ) || 0;
			}
		}

		var html = '<div class="wpcct-tracker__panel">';
		html += '<h3>' + fmt.esc( 'Organiser pipeline' ) + '</h3>';
		html += '<p class="wpcct-tracker__panel-sub">' + fmt.esc( 'Every Campus Connect organiser status, from first outreach through to a closed event.' ) + '</p>';

		if ( ! total ) {
			html += '<p class="wpcct-tracker__notice">' + fmt.esc( 'Pipeline data arrives with the first sync.' ) + '</p>';
			html += '</div>';
			mount.innerHTML = html;
			return;
		}

		// Build the live-journey list from PIPELINE_ORDER, then append any
		// status the data holds that PIPELINE_ORDER doesn't know about yet
		// - so a new upstream status still renders instead of vanishing. The
		// "Did not proceed" pair is tracked separately and excluded here.
		var seen = {};
		var live = [];
		var i;

		for ( i = 0; i < PIPELINE_STOPPED.length; i++ ) {
			seen[ PIPELINE_STOPPED[ i ] ] = true;
		}

		for ( i = 0; i < PIPELINE_ORDER.length; i++ ) {
			status = PIPELINE_ORDER[ i ];
			seen[ status ] = true;
			if ( Object.prototype.hasOwnProperty.call( pipeline, status ) ) {
				live.push( status );
			}
		}
		for ( status in pipeline ) {
			if ( Object.prototype.hasOwnProperty.call( pipeline, status ) && ! seen[ status ] ) {
				live.push( status );
				seen[ status ] = true;
			}
		}

		var liveMax = 0;
		for ( i = 0; i < live.length; i++ ) {
			liveMax = Math.max( liveMax, Number( pipeline[ live[ i ] ] ) || 0 );
		}

		html += '<div class="wpcct-tracker__pipeline">';
		for ( i = 0; i < live.length; i++ ) {
			status = live[ i ];
			count = Number( pipeline[ status ] ) || 0;
			html += pipelineBar( fmt, status, count, liveMax, '' );
		}
		html += '</div>';

		var stoppedTotal = 0;
		var stoppedRows = '';
		var stoppedMax = 0;
		for ( i = 0; i < PIPELINE_STOPPED.length; i++ ) {
			status = PIPELINE_STOPPED[ i ];
			if ( Object.prototype.hasOwnProperty.call( pipeline, status ) ) {
				stoppedMax = Math.max( stoppedMax, Number( pipeline[ status ] ) || 0 );
			}
		}
		for ( i = 0; i < PIPELINE_STOPPED.length; i++ ) {
			status = PIPELINE_STOPPED[ i ];
			if ( Object.prototype.hasOwnProperty.call( pipeline, status ) ) {
				count = Number( pipeline[ status ] ) || 0;
				stoppedTotal += count;
				stoppedRows += pipelineBar( fmt, status, count, stoppedMax, ' wpcct-tracker__pipeline-row--stopped' );
			}
		}

		if ( stoppedRows ) {
			var share = total ? Math.round( ( stoppedTotal / total ) * 100 ) : 0;
			html += '<h4 class="wpcct-tracker__pipeline-subhead">' + fmt.esc( 'Did not proceed' ) + '</h4>';
			html += '<p class="wpcct-tracker__panel-sub wpcct-tracker__caption">'
				+ fmt.esc( fmt.num( stoppedTotal ) + ' of ' + fmt.num( total ) + ' events (' + share + '%) were cancelled or declined before an event took place.' )
				+ '</p>';
			html += '<div class="wpcct-tracker__pipeline wpcct-tracker__pipeline--stopped">' + stoppedRows + '</div>';
		}

		html += '</div>';

		mount.innerHTML = html;
	}

	// One <div> row for buildPipeline: a label, a proportional bar (against
	// the max within its own group, so the "Did not proceed" bars aren't
	// dwarfed by Closed) and the raw count.
	function pipelineBar( fmt, status, count, max, extraClass ) {
		var pct = max ? Math.round( ( count / max ) * 100 ) : 0;
		var row = '<div class="wpcct-tracker__pipeline-row' + extraClass + '">';
		row += '<div class="wpcct-tracker__pipeline-label">' + fmt.esc( status ) + '</div>';
		row += '<div class="wpcct-tracker__pipeline-track"><div class="wpcct-tracker__pipeline-fill" style="width:' + pct + '%"></div></div>';
		row += '<div class="wpcct-tracker__pipeline-count">' + fmt.num( count ) + '</div>';
		row += '</div>';
		return row;
	}

	// Shared row renderer for the Upcoming/Recent tables in buildEvents.
	// `showAnticipated` adds the free-text "anticipated" column (Upcoming
	// only) - it is organiser free text, never a number, so it is never
	// summed, parsed, or right-aligned; it is displayed exactly as typed.
	function eventRow( fmt, event, showAnticipated ) {
		var href = safeUrl( event.url );
		var name = fmt.esc( event.name || 'Untitled event' );
		var link = href ? '<a href="' + escAttr( href ) + '" target="_blank" rel="noopener noreferrer">' + name + '</a>' : name;

		var where = [];
		if ( event.city ) {
			where.push( fmt.esc( event.city ) );
		}
		if ( event.country ) {
			where.push( fmt.esc( event.country ) );
		}

		var row = '<tr>';
		row += '<td>' + fmt.esc( fmt.date( event.start ) ) + '</td>';
		row += '<td class="wpcct-tracker__cell--wrap">' + link + '</td>';
		row += '<td class="wpcct-tracker__cell--wrap">' + fmt.esc( event.institution ) + '</td>';
		row += '<td>' + where.join( ', ' ) + '</td>';

		var attendees = Number( event.attendees ) || 0;
		row += '<td>' + ( attendees > 0 ? fmt.num( attendees ) : '' ) + '</td>';

		if ( showAnticipated ) {
			var anticipated = event.anticipated;
			row += '<td class="wpcct-tracker__cell--wrap">'
				+ ( anticipated ? fmt.esc( anticipated ) + ' ' + fmt.esc( 'anticipated' ) : '' )
				+ '</td>';
		}

		row += '</tr>';
		return row;
	}

	// One panel: a heading, an optional caption, and either a table over
	// `rows` or a readable empty-state notice.
	function eventsPanel( fmt, heading, sub, rows, headCols, caption ) {
		var html = '<div class="wpcct-tracker__panel">';
		html += '<h3>' + fmt.esc( heading ) + '</h3>';
		html += '<p class="wpcct-tracker__panel-sub">' + fmt.esc( sub ) + '</p>';

		if ( ! rows.length ) {
			html += '<p class="wpcct-tracker__notice">' + fmt.esc( 'No events to show here yet.' ) + '</p>';
			html += '</div>';
			return html;
		}

		html += '<div class="wpcct-tracker__table-wrap">';
		html += '<table class="wpcct-tracker__table">';
		html += '<thead><tr>';
		for ( var i = 0; i < headCols.length; i++ ) {
			html += '<th>' + fmt.esc( headCols[ i ] ) + '</th>';
		}
		html += '</tr></thead>';
		html += '<tbody>' + rows.join( '' ) + '</tbody>';
		html += '</table>';
		html += '</div>';

		if ( caption ) {
			html += '<p class="wpcct-tracker__panel-sub wpcct-tracker__caption">' + fmt.esc( caption ) + '</p>';
		}

		html += '</div>';
		return html;
	}

	// Recent is capped so the panel doesn't grow without bound as more
	// events complete; anything beyond the cap is still real and is called
	// out in the caption rather than silently dropped.
	var RECENT_CAP = 20;

	// Task 13, section 2: two tables built from data.events (already sorted
	// newest-first by the sync) - Upcoming (scheduled, soonest first) and
	// Recent (completed, capped, most recent first).
	function buildEvents( mount, data, fmt ) {
		var events = ( data && data.events ) || [];
		var i, e;

		var upcoming = [];
		var completed = [];
		for ( i = 0; i < events.length; i++ ) {
			e = events[ i ] || {};
			if ( 'scheduled' === e.bucket ) {
				upcoming.push( e );
			} else if ( 'completed' === e.bucket ) {
				completed.push( e );
			}
		}

		// events is newest-first; reversing the scheduled subset puts the
		// soonest upcoming event first.
		upcoming.reverse();

		var recentOverflow = Math.max( 0, completed.length - RECENT_CAP );
		var recent = completed.slice( 0, RECENT_CAP );

		var upcomingRows = [];
		for ( i = 0; i < upcoming.length; i++ ) {
			upcomingRows.push( eventRow( fmt, upcoming[ i ], true ) );
		}

		var recentRows = [];
		for ( i = 0; i < recent.length; i++ ) {
			recentRows.push( eventRow( fmt, recent[ i ], false ) );
		}

		var html = '';
		html += eventsPanel(
			fmt,
			'Upcoming events',
			'Scheduled Campus Connect events, soonest first.',
			upcomingRows,
			[ 'Date', 'Event', 'Institution', 'Location', 'Attendees', 'Anticipated' ],
			''
		);

		var recentCaption = recentOverflow
			? ( fmt.num( recentOverflow ) + ' more completed event' + ( 1 === recentOverflow ? '' : 's' ) + ' not shown here.' )
			: '';

		html += eventsPanel(
			fmt,
			'Recent events',
			'The most recently completed Campus Connect events.',
			recentRows,
			[ 'Date', 'Event', 'Institution', 'Location', 'Attendees' ],
			recentCaption
		);

		mount.innerHTML = html;
	}

	/* ---- registry ---- */

	var BUILDERS = {
		scale: buildScale,
		regions: buildRegions,
		map: buildMap,
		timeline: buildTimeline,
		pipeline: buildPipeline,
		events: buildEvents
	};

	/**
	 * Shown for any mount whose section key has no registered builder yet.
	 *
	 * @param {Element} mount
	 */
	function placeholder( mount ) {
		mount.innerHTML = '<p class="wpcct-tracker__notice">' + esc( 'This section is not available yet.' ) + '</p>';
	}

	/**
	 * Initialise a single mount: dispatch to its builder, or fall back to
	 * the placeholder. Idempotent - a mount is only ever initialised once.
	 *
	 * @param {Element} mount
	 */
	function initMount( mount ) {
		if ( mount.getAttribute( 'data-wpcct-ready' ) ) {
			return;
		}
		mount.setAttribute( 'data-wpcct-ready', '1' );

		var key = mount.getAttribute( 'data-wpcct-sec' );
		// hasOwnProperty, not a bare lookup: 'constructor', 'toString' and the
		// rest of Object.prototype are functions too, and would otherwise pass
		// the typeof check below and be called as builders.
		var builder = ( key && Object.prototype.hasOwnProperty.call( BUILDERS, key ) )
			? BUILDERS[ key ]
			: null;

		if ( typeof builder === 'function' ) {
			builder( mount, window.WPCCT_DATA, fmt );
		} else {
			placeholder( mount );
		}
	}

	function boot() {
		var mounts = document.querySelectorAll( '[data-wpcct-sec]' );
		for ( var i = 0; i < mounts.length; i++ ) {
			initMount( mounts[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
