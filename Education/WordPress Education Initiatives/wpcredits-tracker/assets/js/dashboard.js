/*
 * WPCredits-Tracker front-end renderer.
 * Ported from the upstream template.html <script>, reworked to be modular: the
 * dashboard is split into independent section renderers so each can be dropped
 * on a page as its own block. The combined ("full") block composes the very
 * same renderers under the Overview / Voices tabs, so there is no duplication.
 *
 * A mount is one of:
 *   - <div class="wpct-dashboard" ...>            full tabbed dashboard
 *   - <div class="wpct-dashboard wpct-dashboard--section" data-wpct-section="KEY">
 *                                                a single section
 * Both read window.WPCT_DATA / window.WPCT_MARKERS (inlined once by PHP).
 */
( function () {
	'use strict';

	/* ---- helpers ---- */
	function num( v ) { return ( v || 0 ).toLocaleString(); }

	// Every string that reaches innerHTML below goes through this first. The
	// synced blob carries third-party text (Airtable field names, institution
	// names), so it is never safe to interpolate raw.
	function esc( value ) {
		var div = document.createElement( 'div' );
		div.textContent = String( value == null ? '' : value );
		return div.innerHTML;
	}

	/* ---- section HTML builders (pure; no DOM side effects) ---- */

	function scaleHTML( g ) {
		var countryCount = Object.keys( g.instCountries || {} ).length;
		var grad = g.graduates || 0, drop = g.dropouts || 0, act = g.activeStudents || 0;
		var started = grad + drop + act;
		return '<div class="act-label">Scale &amp; momentum</div>'
			+ '<div class="cards">'
			+   '<div class="card"><div class="num">' + ( g.activeStudents || 0 ) + '</div><div class="lbl">Students currently in the program</div></div>'
			+   '<div class="card"><div class="num">' + started + '</div><div class="lbl">Students who have joined to date</div></div>'
			+   '<div class="card"><div class="num">' + countryCount + '</div><div class="lbl">Countries</div></div>'
			+ '</div>';
	}

	function growthHTML() {
		return '<div class="chart-container">'
			+ '<h3>How the program is growing</h3>'
			+ '<div class="chart-sub">Students joining and graduating, month by month</div>'
			+ '<div class="chart-legend">'
			+   '<span><i class="is-joined"></i>Joined</span>'
			+   '<span><i class="is-graduated"></i>Graduated</span>'
			+   '<span><i class="is-line is-cumulative"></i>Cumulative joined</span>'
			+ '</div>'
			+ '<div class="chart-canvas"><canvas class="wpct-growth" role="img" aria-label="Bar chart of students joining and graduating each month, with a line for everyone who has joined to date."></canvas></div>'
			+ '</div>';
	}

	function mapHTML() {
		return '<div class="chart-container"><h3>Where our partners are</h3><div class="chart-sub">Partner institutions around the world</div><div class="wpct-map"></div></div>';
	}

	function fosHTML() {
		return '<div class="chart-container"><h3>Who\'s joining us</h3><div class="chart-sub">The academic backgrounds students bring</div><div class="wpct-fos"></div></div>';
	}

	function skillsHTML() {
		return '<div class="act-label">Skills unlocked</div>'
			+ '<div class="chart-container skills">'
			+   '<div class="chart-sub">Technical and transferable skills students build through real open-source work.</div>'
			+   '<div class="skills-grid">'
			+     '<div><div class="skills-head">Technical</div><div class="skills-chips">'
			+       '<span class="skill">Website building &amp; customization</span>'
			+       '<span class="skill">Localization &amp; translation</span>'
			+       '<span class="skill">Design &amp; visual media</span>'
			+       '<span class="skill">Content &amp; documentation</span>'
			+       '<span class="skill">Quality, testing &amp; accessibility</span>'
			+     '</div></div>'
			+     '<div class="skills-transferable"><div class="skills-head">Transferable</div><div class="skills-chips">'
			+       '<span class="skill">Open-source collaboration, tools &amp; workflow</span>'
			+       '<span class="skill">Professional communication</span>'
			+       '<span class="skill">Project &amp; time management</span>'
			+       '<span class="skill">Giving &amp; receiving feedback</span>'
			+       '<span class="skill">Public speaking &amp; community participation</span>'
			+     '</div></div>'
			+   '</div>'
			+ '</div>';
	}

	function produceHTML( g ) {
		return '<div class="act-label">What students produce</div>'
			+ '<div class="cards">'
			+   '<div class="card"><div class="num">' + num( g.firstContributions ) + '</div><div class="lbl">First contributions made</div></div>'
			+   '<div class="card"><div class="num">' + num( g.sitesCreated ) + '</div><div class="lbl">WordPress sites created</div></div>'
			+   '<div class="card"><div class="num">' + Object.keys( g.teamDistribution || {} ).length + '</div><div class="lbl">WordPress teams contributed to</div></div>'
			+ '</div>';
	}

	function outcomesHTML( g, D ) {
		var grad = g.graduates || 0, drop = g.dropouts || 0;
		var finished = grad + drop;
		var completionRate = finished > 0 ? Math.round( grad / finished * 100 ) : null;
		var fb = D.feedback || {};
		var recPct = ( fb.recommend && fb.recommend.pct != null ) ? fb.recommend.pct + '%' : '-';
		var keepPct = ( fb.keep && fb.keep.pct != null ) ? fb.keep.pct + '%' : '-';
		var impact = ( fb.ratings && fb.ratings.impact ) || {};
		var rs = g.repeatSchools || {};
		return '<div class="act-label">Outcomes &amp; quality</div>'
			+ '<div class="cards cards-outcomes">'
			+   '<div class="card hl"><div class="num">' + ( completionRate != null ? completionRate + '%' : '-' ) + '</div><div class="lbl">Graduated from the program</div></div>'
			+   '<div class="card"><div class="num">' + grad + '</div><div class="lbl">Graduates to date</div></div>'
			+   '<div class="card"><div class="num">' + ( impact.avg != null ? impact.avg + '<span class="num-of">/5</span>' : '-' ) + '</div><div class="lbl">How impactful graduates rate their contributions</div></div>'
			+   '<div class="card"><div class="num">' + recPct + '</div><div class="lbl">Graduates who would recommend the program</div></div>'
			+   '<div class="card"><div class="num">' + keepPct + '</div><div class="lbl">Graduates who plan to keep contributing to WordPress</div></div>'
			+   '<div class="card"><div class="num">' + ( rs.pct != null ? rs.pct + '%' : '-' ) + '</div><div class="lbl">Schools that have run more than one cohort</div>' + ( rs.total ? '<div class="act-sub">of the ' + rs.total + ' schools with us long enough to return</div>' : '' ) + '</div>'
			+ '</div>';
	}

	function voicesHTML() {
		// A flag built from the ISO 3166-1 alpha-2 code (kept as plain ASCII in the
		// source). It is decoration: the country's name is always spelled out beside
		// it, since some platforms (Windows) draw no flag emoji, and a screen reader
		// is told to skip it.
		var flag = function ( cc ) { return cc.replace( /./g, function ( ch ) { return String.fromCodePoint( 0x1F1E6 + ch.charCodeAt( 0 ) - 65 ); } ); };
		// The quotes come from the site (the WPCredits-Tracker admin screen, or the six the
		// plugin ships with): see WPCT_Voices.
		var voices = Array.isArray( window.WPCT_VOICES ) ? window.WPCT_VOICES : [];
		if ( ! voices.length ) { return ''; }
		// These are literals above, but escaping them keeps one rule for the whole
		// file (nothing reaches innerHTML unescaped), so wiring them to the
		// synced blob later cannot reintroduce an injection.
		var voicesHtml = voices.map( function ( v ) { return '<figure class="voice"><blockquote class="voice-q"' + ( v.lang ? ' lang="' + esc( v.lang ) + '"' : '' ) + '>“' + esc( v.q ) + '”</blockquote><figcaption class="voice-meta"><span>' + esc( v.who ) + '</span>' + ( v.country ? '<span class="voice-tag">' + ( /^[A-Z]{2}$/.test( v.cc || '' ) ? '<span class="voice-flag" aria-hidden="true">' + flag( v.cc ) + '</span>' : '' ) + esc( v.country ) + '</span>' : '' ) + '</figcaption></figure>'; } ).join( '' );
		return '<h3>What students say</h3>'
			+ '<p class="voices-sub">In students’ own words, shared with their consent.</p>'
			+ '<div class="voices-grid">' + voicesHtml + '</div>';
	}

	/* ---- inits that need the rendered DOM (charts / map) ---- */

	function initGrowth( scope, D ) {
		var gr = D.growth, cv = scope.querySelector( '.wpct-growth' );
		if ( ! gr || ! cv || ! gr.months || ! gr.months.length || typeof Chart === 'undefined' ) { return; }
		// The chart draws on a canvas, so it cannot read the stylesheet: the type and the
		// colors are the stylesheet's own values (Inter; ink, brand, muted; the hairline).
		var font = { family: 'Inter, -apple-system, system-ui, "Segoe UI", Roboto, "Helvetica Neue", sans-serif', size: 12 };
		var ticks = { color: '#656a71', font: font };
		var still = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		new Chart( cv, {
			data: { labels: gr.months, datasets: [
				{ type: 'bar', label: 'Joined', data: gr.joined, backgroundColor: '#3858e9', yAxisID: 'y' },
				{ type: 'bar', label: 'Graduated', data: gr.graduated, backgroundColor: '#1e1e1e', yAxisID: 'y' },
				{ type: 'line', label: 'Cumulative joined', data: gr.cumulativeJoined, borderColor: '#656a71', backgroundColor: '#656a71', borderWidth: 2, tension: 0.3, pointRadius: 2, yAxisID: 'y1' }
			] },
			options: { responsive: true, maintainAspectRatio: false, animation: still ? false : undefined,
				plugins: { legend: { display: false }, tooltip: { titleFont: font, bodyFont: font } },
				scales: { x: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 45, minRotation: 45, color: '#656a71', font: font } },
					y: { beginAtZero: true, grid: { color: '#ededed' }, ticks: ticks, title: { display: true, text: 'per month', color: '#656a71', font: font } },
					y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: ticks, title: { display: true, text: 'cumulative', color: '#656a71', font: font } } } }
		} );
	}

	function initMap( scope, MARKERS ) {
		var mapEl = scope.querySelector( '.wpct-map' );
		if ( ! mapEl || typeof L === 'undefined' ) { return; }

		var map = L.map( mapEl, { scrollWheelZoom: false, zoomControl: true } ).setView( [ 30, 10 ], 2 );
		// OpenStreetMap's standard tiles, which need no API key. CARTO's light_all
		// basemap used to serve anonymous tiles and now stamps "API KEY REQUIRED"
		// across every one of them, while still answering 200 with a valid PNG, so
		// it failed silently. Keep this URL without {s} (OSM serves from a single
		// host now) and without {r} (OSM has no @2x tiles, so a retina suffix would
		// 404 on exactly the screens that asked for it).
		L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			maxZoom: 19
		} ).addTo( map );

		var wpIcon = L.divIcon( {
			className: 'wp-marker',
			html: '<div class="wpct-marker"></div>',
			iconSize: [ 28, 28 ], iconAnchor: [ 14, 14 ], popupAnchor: [ 0, -16 ]
		} );
		var wpIconMulti = function ( count ) {
			return L.divIcon( {
				className: 'wp-marker',
				html: '<div class="wpct-marker">' + count + '</div>',
				iconSize: [ 32, 32 ], iconAnchor: [ 16, 16 ], popupAnchor: [ 0, -18 ]
			} );
		};

		( MARKERS || [] ).forEach( function ( m ) {
			var icon = m.institutions.length > 1 ? wpIconMulti( m.institutions.length ) : wpIcon;
			var list = m.institutions.map( function ( n ) { return '<li>' + esc( n ) + '</li>'; } ).join( '' );
			var popup = '<h4>' + esc( m.city ) + ', ' + esc( m.country ) + '</h4><ul>' + list + '</ul>';
			L.marker( [ m.lat, m.lng ], { icon: icon } ).addTo( map ).bindPopup( popup, { maxWidth: 280 } );
		} );

		// When embedded in a tab (full dashboard), re-measure on tab show. As a
		// standalone block the map is always visible, so a single tick suffices.
		var sec = mapEl.closest ? mapEl.closest( '.section' ) : null;
		if ( sec ) {
			var observer = new MutationObserver( function () {
				if ( sec.classList.contains( 'active' ) ) {
					setTimeout( function () { map.invalidateSize(); }, 100 );
				}
			} );
			observer.observe( sec, { attributes: true, attributeFilter: [ 'class' ] } );
		}
		setTimeout( function () { map.invalidateSize(); }, 200 );
	}

	function initFos( scope, D ) {
		var fosEl = scope.querySelector( '.wpct-fos' );
		if ( ! fosEl ) { return; }
		var fosMap = {};
		( D.students || [] ).forEach( function ( s ) {
			// Every student in the data is in the program or a graduate: the sync decides who counts.
			var fos = s.fieldOfStudy || 'Unspecified';
			if ( ! fosMap[ fos ] ) { fosMap[ fos ] = { active: 0, graduated: 0 }; }
			if ( s.is_graduate ) { fosMap[ fos ].graduated++; } else { fosMap[ fos ].active++; }
		} );
		var fosEntries = Object.keys( fosMap ).map( function ( k ) { var v = fosMap[ k ]; return [ k, v.active + v.graduated, v.active, v.graduated ]; } ).sort( function ( a, b ) { return b[ 1 ] - a[ 1 ]; } );
		var fosTotal = fosEntries.reduce( function ( a, e ) { return a + e[ 1 ]; }, 0 );
		var fosColors = { 'Technology & Engineering': '#3858E9', 'Design & Creative Media': '#6B4E9E', 'Languages, Communication & Writing': '#2F7D5B', 'Education & Learning': '#2A7B8C', 'Business, Marketing & Management': '#B07A22', 'Humanities & Social Sciences': '#C0563A', 'Arts & Architecture': '#A34568', 'Natural Sciences & Mathematics': '#6E7B36', 'Health & Medicine': '#4E7A9E', 'Unspecified': '#979aa1' };
		var fosR = 70, fosCx = 90, fosCy = 90, fosSw = 24, fosCI = 2 * Math.PI * fosR;
		var fosOff = 0, fosPaths = '';
		fosEntries.forEach( function ( e ) {
			var n = e[ 0 ], c = e[ 1 ];
			if ( ! c ) { return; }
			var dl = fosCI * c / fosTotal;
			fosPaths += '<circle cx="' + fosCx + '" cy="' + fosCy + '" r="' + fosR + '" fill="none" stroke="' + ( fosColors[ n ] || '#979aa1' ) + '" stroke-width="' + fosSw + '" stroke-dasharray="' + dl + ' ' + ( fosCI - dl ) + '" stroke-dashoffset="' + ( -fosOff ) + '" transform="rotate(-90 ' + fosCx + ' ' + fosCy + ')"/>';
			fosOff += dl;
		} );
		var fosLegend = fosEntries.map( function ( e ) {
			var n = e[ 0 ], c = e[ 1 ], grad = e[ 3 ];
			var pct = fosTotal ? Math.round( c / fosTotal * 100 ) : 0;
			var gradNote = grad > 0 ? ' <small>(' + grad + ' graduated)</small>' : '';
			// fosColors is a fixed lookup, so only its own literal values can
			// reach the style attribute; n itself is third-party text.
			return '<li><span class="fos-dot" style="background:' + ( fosColors[ n ] || '#979aa1' ) + '"></span><span class="fos-name">' + esc( n ) + '</span><span class="fos-count">' + c + ' <small>(' + pct + '%)</small>' + gradNote + '</span></li>';
		} ).join( '' );
		fosEl.innerHTML = '<div class="fos"><div class="fos-ring"><svg viewBox="0 0 180 180" aria-hidden="true">' + fosPaths + '</svg><div class="fos-total"><b>' + fosTotal + '</b><span>Students</span></div></div><ul class="fos-list">' + fosLegend + '</ul></div>';
	}

	/* ---- section registry ---- */

	// The sections that make up the Overview tab, in order.
	var OVERVIEW = [ 'scale', 'growth', 'map', 'field-of-study', 'skills', 'produce', 'outcomes' ];

	function sectionHTML( key, D ) {
		var g = D.global || {};
		switch ( key ) {
			case 'scale': return scaleHTML( g );
			case 'growth': return growthHTML();
			case 'map': return mapHTML();
			case 'field-of-study': return fosHTML();
			case 'skills': return skillsHTML();
			case 'produce': return produceHTML( g );
			case 'outcomes': return outcomesHTML( g, D );
			case 'voices': return voicesHTML();
			default: return '';
		}
	}

	function sectionInit( scope, key, D, MARKERS ) {
		switch ( key ) {
			case 'growth': initGrowth( scope, D ); break;
			case 'map': initMap( scope, MARKERS ); break;
			case 'field-of-study': initFos( scope, D ); break;
		}
	}

	/* ---- mount handlers ---- */

	function initFull( root, D, MARKERS ) {
		// Tab switching.
		root.querySelectorAll( '.nav-item' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				root.querySelectorAll( '.nav-item' ).forEach( function ( t ) { t.classList.remove( 'active' ); t.setAttribute( 'aria-selected', 'false' ); } );
				root.querySelectorAll( '.section' ).forEach( function ( s ) { s.classList.remove( 'active' ); } );
				tab.classList.add( 'active' );
				tab.setAttribute( 'aria-selected', 'true' );
				var target = root.querySelector( '[data-wpct-sec="' + tab.dataset.section + '"]' );
				if ( target ) { target.classList.add( 'active' ); }
			} );
		} );

		var overview = root.querySelector( '[data-wpct-sec="global"]' );
		if ( overview ) {
			overview.innerHTML = OVERVIEW.map( function ( k ) { return sectionHTML( k, D ); } ).join( '' );
			OVERVIEW.forEach( function ( k ) { sectionInit( overview, k, D, MARKERS ); } );
		}
		var voices = root.querySelector( '[data-wpct-sec="feedback"]' );
		if ( voices ) {
			voices.innerHTML = sectionHTML( 'voices', D );
		}
	}

	function initDashboard( root ) {
		if ( ! root || root.dataset.wpctReady ) { return; }
		root.dataset.wpctReady = '1';

		var D = window.WPCT_DATA || {};
		var MARKERS = window.WPCT_MARKERS || [];

		var key = root.getAttribute( 'data-wpct-section' );
		if ( key ) {
			root.innerHTML = sectionHTML( key, D );
			sectionInit( root, key, D, MARKERS );
		} else {
			initFull( root, D, MARKERS );
		}
	}

	function boot() {
		document.querySelectorAll( '.wpct-dashboard' ).forEach( initDashboard );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
