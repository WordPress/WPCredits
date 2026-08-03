/*
 * WPCredits-Tracker — front-end renderer.
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
		return '<div class="act-label act-scale">Scale &amp; momentum</div>'
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
			+ '<div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:8px;font-size:12px;color:var(--text-light)">'
			+   '<span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#2F7D5B"></span>Joined</span>'
			+   '<span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#3858E9"></span>Graduated</span>'
			+   '<span style="display:flex;align-items:center;gap:5px"><span style="width:14px;height:3px;background:#B07A22;display:inline-block"></span>Cumulative joined</span>'
			+ '</div>'
			+ '<div style="position:relative;height:320px"><canvas class="wpct-growth"></canvas></div>'
			+ '</div>';
	}

	function mapHTML() {
		return '<div class="chart-container"><h3>Where our partners are</h3><div class="chart-sub">Partner institutions around the world</div><div class="wpct-map"></div></div>';
	}

	function fosHTML() {
		return '<div class="chart-container"><h3>Who\'s joining us</h3><div class="chart-sub">The academic backgrounds students bring</div><div class="wpct-fos"></div></div>';
	}

	function skillsHTML() {
		return '<div class="act-label act-skills">Skills unlocked</div>'
			+ '<div class="chart-container">'
			+   '<div class="chart-sub" style="margin-bottom:18px">Technical and transferable skills students build through real open-source work.</div>'
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
		return '<div class="act-label act-produce">What students produce</div>'
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
		var recPct = ( fb.recommend && fb.recommend.pct != null ) ? fb.recommend.pct + '%' : '—';
		var keepPct = ( fb.keep && fb.keep.pct != null ) ? fb.keep.pct + '%' : '—';
		var impact = ( fb.ratings && fb.ratings.impact ) || {};
		var rs = g.repeatSchools || {};
		return '<div class="act-label act-outcomes">Outcomes &amp; quality</div>'
			+ '<div class="cards cards-outcomes">'
			+   '<div class="card hl"><div class="num">' + ( completionRate != null ? completionRate + '%' : '—' ) + '</div><div class="lbl">Graduated from the program</div></div>'
			+   '<div class="card"><div class="num">' + grad + '</div><div class="lbl">Graduates to date</div></div>'
			+   '<div class="card"><div class="num">' + ( impact.avg != null ? impact.avg + '<span style="font-size:20px;color:var(--text-light)">/5</span>' : '—' ) + '</div><div class="lbl">How impactful graduates rate their contributions</div></div>'
			+   '<div class="card"><div class="num">' + recPct + '</div><div class="lbl">Graduates who would recommend the program</div></div>'
			+   '<div class="card"><div class="num">' + keepPct + '</div><div class="lbl">Graduates who plan to keep contributing to WordPress</div></div>'
			+   '<div class="card"><div class="num">' + ( rs.pct != null ? rs.pct + '%' : '—' ) + '</div><div class="lbl">Schools that have run more than one cohort</div>' + ( rs.total ? '<div class="act-sub">of the ' + rs.total + ' schools with us long enough to return</div>' : '' ) + '</div>'
			+ '</div>';
	}

	function voicesHTML() {
		// Build a flag from the ISO 3166-1 alpha-2 code (kept as plain ASCII in
		// the source). The country name is always spelled out beside it, since
		// some platforms (e.g. Windows) render no flag emoji.
		var flag = function ( cc ) { return cc.replace( /./g, function ( ch ) { return String.fromCodePoint( 0x1F1E6 + ch.charCodeAt( 0 ) - 65 ); } ); };
		var voices = [
			{ q: "My time in the WordPress Credits Internship program was enlightening. This was my first time actively contributing to a real open-source project, and my first internship. I learned a lot about my own skills, about open-source software, and about the WordPress Foundation and WordPress software. Additionally, I would like to think that my contribution will have a lasting impact on the local WordPress community.", who: "Carolyn · Central New Mexico Community College", cc: "US", country: "United States" },
			{ q: "This program was a great learning experience for me. I learned how to build and customize websites using WordPress, and also gained basic knowledge of HTML, CSS, and PHP. The mentors were very supportive and guided us throughout the journey. This course helped me gain confidence and real-world experience. I would highly recommend it to anyone interested in starting a career in WordPress and web development.", who: "Saad · Ahmad's Education", cc: "BD", country: "Bangladesh" },
			{ q: "WordPress Credits was a great experience for me. I could see how a global project works and I helped translate over 1,200 phrases. Sometimes you need patience, but the results give a lot of satisfaction. If you want to learn new technical skills and build your own portfolio, you should definitely join this program.", who: "Wiktoria · Krakow University of Economics", cc: "PL", country: "Poland" },
			{ q: "El curso ha estado genial y la experiencia ha sido muy positiva. Me ha gustado mucho integrarme en la comunidad de WordPress y ver cómo se trabaja en un proyecto real de código abierto. Ha estado muy bien organizado y me ha servido para aprender un montón sobre la localización y traducción de software. Muy recomendable.", who: "Juan Manuel · IES Azarquiel", cc: "ES", country: "Spain" },
			{ q: "Podría decir que no tengan miedo y se metan con todo menos con miedo a este programa, porque es una experiencia muy bonita, aprendes muchísimas cosas, sales de tu rutina diaria, te hace crecer personalmente y profesionalmente.", who: "Rossana · Universidad Privada Franz Tamayo", cc: "BO", country: "Bolivia" },
			{ q: "Mi experiencia en WPCredits fue muy positiva ya que aprendí sobre WordPress, la colaboración en comunidades de código abierto y la importancia de compartir conocimientos. Aunque al inicio fue un reto familiarizarme con las diferentes plataformas, con el tiempo logré adaptarme y desarrollar nuevas habilidades. Recomiendo esta experiencia a otros estudiantes porque permite aprender herramientas útiles y participar en una comunidad tecnológica.", who: "Teresa · Universidad Fidélitas", cc: "CR", country: "Costa Rica" }
		];
		// These are literals above, but escaping them keeps one rule for the whole
		// file — nothing reaches innerHTML unescaped — so wiring them to the
		// synced blob later cannot reintroduce an injection.
		var voicesHtml = voices.map( function ( v ) { return '<div class="voice"><div class="voice-q">“' + esc( v.q ) + '”</div><div class="voice-meta"><span>' + esc( v.who ) + '</span><span class="voice-tag"><span aria-hidden="true">' + flag( v.cc ) + '</span> ' + esc( v.country ) + '</span></div></div>'; } ).join( '' );
		return '<h3 style="font-size:18px;margin:4px 0 6px">Voices from the program</h3>'
			+ '<p style="font-size:13px;color:var(--text-light);margin:0 0 18px">In students’ own words, shared with their consent.</p>'
			+ '<div class="voices-grid">' + voicesHtml + '</div>';
	}

	/* ---- inits that need the rendered DOM (charts / map) ---- */

	function initGrowth( scope, D ) {
		var gr = D.growth, cv = scope.querySelector( '.wpct-growth' );
		if ( ! gr || ! cv || ! gr.months || ! gr.months.length || typeof Chart === 'undefined' ) { return; }
		new Chart( cv, {
			data: { labels: gr.months, datasets: [
				{ type: 'bar', label: 'Joined', data: gr.joined, backgroundColor: '#2F7D5B', yAxisID: 'y' },
				{ type: 'bar', label: 'Graduated', data: gr.graduated, backgroundColor: '#3858E9', yAxisID: 'y' },
				{ type: 'line', label: 'Cumulative joined', data: gr.cumulativeJoined, borderColor: '#B07A22', backgroundColor: '#B07A22', borderWidth: 2, tension: 0.3, pointRadius: 2, yAxisID: 'y1' }
			] },
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
				scales: { x: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 45, minRotation: 45 } },
					y: { beginAtZero: true, title: { display: true, text: 'per month' } },
					y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'cumulative' } } } }
		} );
	}

	function initMap( scope, MARKERS ) {
		var mapEl = scope.querySelector( '.wpct-map' );
		if ( ! mapEl || typeof L === 'undefined' ) { return; }

		var map = L.map( mapEl, { scrollWheelZoom: false, zoomControl: true } ).setView( [ 30, 10 ], 2 );
		L.tileLayer( 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
			subdomains: 'abcd',
			maxZoom: 19
		} ).addTo( map );

		var wpIcon = L.divIcon( {
			className: 'wp-marker',
			html: '<div style="background:#3858E9;width:28px;height:28px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px"></div>',
			iconSize: [ 28, 28 ], iconAnchor: [ 14, 14 ], popupAnchor: [ 0, -16 ]
		} );
		var wpIconMulti = function ( count ) {
			return L.divIcon( {
				className: 'wp-marker',
				html: '<div style="background:#3858E9;width:32px;height:32px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:14px">' + count + '</div>',
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
		var activeStatuses = { 'in sensei': 1, 'in sensei self-onboarding': 1, 'in sensei 50h': 1, 'pending graduation': 1 };
		var fosMap = {};
		( D.students || [] ).forEach( function ( s ) {
			if ( ! s.is_graduate && ! activeStatuses[ ( s.status || '' ).trim().toLowerCase() ] ) { return; }
			var fos = s.fieldOfStudy || 'Unspecified';
			if ( ! fosMap[ fos ] ) { fosMap[ fos ] = { active: 0, graduated: 0 }; }
			if ( s.is_graduate ) { fosMap[ fos ].graduated++; } else { fosMap[ fos ].active++; }
		} );
		var fosEntries = Object.keys( fosMap ).map( function ( k ) { var v = fosMap[ k ]; return [ k, v.active + v.graduated, v.active, v.graduated ]; } ).sort( function ( a, b ) { return b[ 1 ] - a[ 1 ]; } );
		var fosTotal = fosEntries.reduce( function ( a, e ) { return a + e[ 1 ]; }, 0 );
		var fosColors = { 'Technology & Engineering': '#3858E9', 'Design & Creative Media': '#6B4E9E', 'Languages, Communication & Writing': '#2F7D5B', 'Education & Learning': '#2A7B8C', 'Business, Marketing & Management': '#B07A22', 'Humanities & Social Sciences': '#C0563A', 'Arts & Architecture': '#A34568', 'Natural Sciences & Mathematics': '#6E7B36', 'Health & Medicine': '#4E7A9E', 'Unspecified': '#857C6E' };
		var fosR = 70, fosCx = 90, fosCy = 90, fosSw = 24, fosCI = 2 * Math.PI * fosR;
		var fosOff = 0, fosPaths = '';
		fosEntries.forEach( function ( e ) {
			var n = e[ 0 ], c = e[ 1 ];
			if ( ! c ) { return; }
			var dl = fosCI * c / fosTotal;
			fosPaths += '<circle cx="' + fosCx + '" cy="' + fosCy + '" r="' + fosR + '" fill="none" stroke="' + ( fosColors[ n ] || '#857C6E' ) + '" stroke-width="' + fosSw + '" stroke-dasharray="' + dl + ' ' + ( fosCI - dl ) + '" stroke-dashoffset="' + ( -fosOff ) + '" transform="rotate(-90 ' + fosCx + ' ' + fosCy + ')"/>';
			fosOff += dl;
		} );
		var fosLegend = fosEntries.map( function ( e ) {
			var n = e[ 0 ], c = e[ 1 ], grad = e[ 3 ];
			var pct = fosTotal ? Math.round( c / fosTotal * 100 ) : 0;
			var gradNote = grad > 0 ? ' <span style="color:#B07A22;font-size:11px">(' + grad + ' grad.)</span>' : '';
			// fosColors is a fixed lookup, so only its own literal values can
			// reach the style attribute; n itself is third-party text.
			return '<div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border)"><div style="width:14px;height:14px;border-radius:4px;background:' + ( fosColors[ n ] || '#857C6E' ) + ';flex-shrink:0"></div><div style="flex:1;min-width:0"><div style="font-size:13px;font-weight:500;color:var(--text)">' + esc( n ) + '</div></div><div style="font-size:15px;font-weight:700;color:var(--text);white-space:nowrap">' + c + ' <span style="font-size:11px;font-weight:400;color:var(--text-light)">(' + pct + '%)</span>' + gradNote + '</div></div>';
		} ).join( '' );
		fosEl.innerHTML = '<div style="display:flex;gap:24px;align-items:center;flex-wrap:wrap"><div style="width:180px;height:180px;position:relative;flex-shrink:0"><svg viewBox="0 0 180 180">' + fosPaths + '</svg><div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center"><div style="font-size:24px;font-weight:800;color:var(--text)">' + fosTotal + '</div><div style="font-size:10px;color:var(--text-light);text-transform:uppercase">Students</div></div></div><div style="flex:1;min-width:200px">' + fosLegend + '</div></div>';
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
				root.querySelectorAll( '.nav-item' ).forEach( function ( t ) { t.classList.remove( 'active' ); } );
				root.querySelectorAll( '.section' ).forEach( function ( s ) { s.classList.remove( 'active' ); } );
				tab.classList.add( 'active' );
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
