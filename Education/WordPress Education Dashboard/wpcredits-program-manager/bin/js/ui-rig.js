'use strict';
/*
 * The picture rig's lists and its comparer, run in node without a browser or a site:
 *
 *   php bin/test-ui-rig.php
 *
 * which runs this file as
 *
 *   node bin/js/ui-rig.js <plugin root>
 *
 * It imports bin/ui/scenes.mjs and bin/ui/compare.mjs and checks: every scene's file name is unique at each
 * width; the Administrator Dashboard's scenes cover every view and every card of the plan's Tasks 9 to 17 and
 * every Decide panel; the other surfaces' scenes cover both Report Cards, every Sponsor Dashboard view, the
 * Institution Dashboard both ways, every wp-admin tab of the design's sections 4.2 and 10 and the
 * Administrator guide's first screen; and the comparer's verdict on a psnr line and two sizes.
 *
 * Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened, then a summary
 * line, and exits 1 when any scenario differs.
 */
const path = require( 'path' );
const { pathToFileURL } = require( 'url' );

const ROOT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..' ) );

let ran = 0;
let failed = 0;

function scenario( name, want, got ) {
	ran++;
	const a = JSON.stringify( got );
	const b = JSON.stringify( want );
	if ( a === b ) {
		console.log( 'ok   ' + name );
	} else {
		failed++;
		console.log( 'FAIL ' + name );
		console.log( '       exp: ' + b + '  got: ' + a );
	}
}

( async function () {
	const sc = await import( pathToFileURL( path.join( ROOT, 'bin/ui/scenes.mjs' ) ).href );
	const cmp = await import( pathToFileURL( path.join( ROOT, 'bin/ui/compare.mjs' ) ).href );

	scenario( 'SIZES are 1280 at 1, 390 at 2 and 768 at 1, in that order', [
		{ width: 1280, height: 800, scale: 1 },
		{ width: 390, height: 844, scale: 2 },
		{ width: 768, height: 1024, scale: 1 },
	], sc.SIZES );

	const all = sc.scenes();
	scenario( 'scenes() lists scenes', true, Array.isArray( all ) && all.length > 50 );
	scenario( 'every scene has an area, a screen, a state and a path on the site (or a _PATH token)', [], all.filter( s => ! s.area || ! s.screen || ! s.state || typeof s.path !== 'string' || ! ( s.path.startsWith( '/' ) || /^\{[A-Z_]+_PATH\}$/.test( s.path ) ) ).map( s => s.screen || '?' ) );

	for ( const size of sc.SIZES ) {
		const names = all.filter( s => sc.takenAt( s, size ) ).map( s => sc.fileName( s, size ) );
		const dup = names.filter( ( n, i ) => names.indexOf( n ) !== i );
		scenario( `file names are unique at ${ size.width }`, [], dup );
		scenario( `file names at ${ size.width } read <area>-<screen>-<state>-${ size.width } in lowercase and hyphens`, [], names.filter( n => ! new RegExp( `^[a-z0-9]+(-[a-z0-9]+)*-${ size.width }$` ).test( n ) ) );
	}
	scenario( 'at 768, when --sizes lists it, only wp-admin tabs and the in-between widths\' dashboard scenes are taken', [], all.filter( s => sc.takenAt( s, sc.SIZES[ 2 ], [ 1280, 390, 768 ] ) && s.area !== 'wpadmin' && ! ( s.widths || [] ).includes( 768 ) ).map( s => s.screen ) );
	scenario( 'without --sizes no wp-admin tab is taken at 768', [], all.filter( s => s.area === 'wpadmin' && sc.takenAt( s, sc.SIZES[ 2 ] ) ).map( s => s.screen ) );
	scenario( 'every scene but the folded menu\'s is taken at 1280 and at 390', [], all.filter( s => ! s.folded && ( ! sc.takenAt( s, sc.SIZES[ 0 ] ) || ! sc.takenAt( s, sc.SIZES[ 1 ] ) ) ).map( s => s.screen ) );

	// ------------------------------------------------------------ the Administrator Dashboard
	const admin = all.filter( s => s.area === 'admin-dashboard' );
	const has = ( screen, state ) => admin.some( s => s.screen === screen && s.state === state );
	scenario( 'the Administrator Dashboard: each view closed and all open, the cohort timeline view included', [], [
		[ 'overview', 'closed' ], [ 'overview', 'all-open' ], [ 'watch', 'closed' ], [ 'watch', 'all-open' ],
		[ 'the-program', 'closed' ], [ 'the-program', 'all-open' ], [ 'cohort-timeline', 'closed' ], [ 'cohort-timeline', 'all-open' ],
	].filter( ( [ a, b ] ) => ! has( a, b ) ).map( p => p.join( ' ' ) ) );
	scenario( 'the cohort timeline view is reached at ?wpcpm_view=cohort-timeline', true, admin.some( s => s.screen === 'cohort-timeline' && s.path.includes( 'wpcpm_view=cohort-timeline' ) ) );

	// Every card of Tasks 9 to 17, by the anchor key it has on the page (`wpcpm-<key>`), with the view it is on.
	const cards = {
		overview: [ 'mentor-applications', 'applications', 'agreements', 'requests', 'reports', 'sponsor-applications', 'sponsor-posts', 'sponsor-offers-review', 'sponsor-events', 'sponsor-agreements', 'sponsor-reports' ],
		watch: [ 'cohort-timeline', 'offers-low', 'duplicates', 'interests', 'tiers-ending', 'health', 'quiet-students' ],
		'the-program': [ 'sponsors', 'programs', 'mentor-engagement', 'tools' ],
	};
	for ( const [ view, keys ] of Object.entries( cards ) ) {
		scenario( `every card of ${ view } has a scene of its own, open alone`, [], keys.filter( k => ! admin.some( s => s.card === k && s.screen.startsWith( view + '-' ) && ( s.state === 'card-open' || s.state === 'row' ) ) ) );
	}
	scenario( 'a card scene opens its card by its anchor', [], admin.filter( s => s.card && ! ( s.act && s.act.openCard === '#wpcpm-' + s.card ) ).map( s => s.screen ) );

	const panels = [
		[ 'applications', 'ask' ], [ 'applications', 'reject' ], [ 'agreements', 'return' ], [ 'requests', 'handled' ], [ 'requests', 'decline' ],
		[ 'sponsor-posts', 'return' ], [ 'sponsor-offers-review', 'return' ], [ 'sponsor-events', 'return' ], [ 'sponsor-agreements', 'return' ],
		[ 'sponsor-applications', 'ask' ], [ 'sponsor-applications', 'reject' ],
	];
	scenario( 'a Decide panel scene opens only its card\'s own fold before the press, so it is not the card-open picture again', [], admin.filter( s => s.panelOf && ! ( s.act && s.act.openHead === '#wpcpm-' + s.panelOf && ! s.act.openCard && ! s.act.open ) ).map( s => s.screen + ' ' + s.state ) );
	scenario( 'each Decide panel has a scene of its own, pressed open on its card', [], panels.filter( ( [ k, p ] ) => ! admin.some( s => s.panelOf === k && s.state === `${ p }-panel-open` && s.press && s.press.scope === '#wpcpm-' + k ) ).map( p => p.join( ' ' ) ) );

	// ------------------------------------------------------------ the other surfaces
	const areas = new Set( all.map( s => s.area ) );
	scenario( 'both Report Cards are photographed', [ true, true ], [ areas.has( 'mentor-report-card' ), areas.has( 'student-report-card' ) ] );
	const sponsorViews = all.filter( s => s.area === 'sponsor-dashboard' ).map( s => ( s.path.match( /wpcpm_view=([a-z-]+)/ ) || [ '', '' ] )[ 1 ] );
	scenario( 'every Sponsor Dashboard view is photographed', [], [ '', 'sponsorship', 'offers', 'reached', 'mentors', 'company', 'agreement' ].filter( v => ! sponsorViews.includes( v ) ) );
	const inst = all.filter( s => s.area === 'institution-dashboard' );
	scenario( 'the Institution Dashboard as an Administrator and as a representative', [ true, true ], [ inst.some( s => ! s.login ), inst.some( s => !! s.login ) ] );
	scenario( 'the guide\'s job "A school applied" with its notes, as the audit took it: revealed and cut from its heading', true, all.some( s => s.area === 'guide' && s.section === '#a-school-applied' && s.act && s.act.reveal === '#a-school-applied' ) );
	scenario( 'stage() refuses a page that sent the browser to sign in, so a sign-in page is never saved as a screen', true, /wp-login\\.php[\s\S]*document\.body\.classList\.contains\('login'\)[\s\S]*throw new Error\('the page asked to sign in/.test( require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/scenes.mjs' ), 'utf8' ) ) );
	scenario( "the Administrator guide's first screen", true, all.some( s => s.area === 'guide' && s.path === '/administrator-guide/' && s.viewportOnly ) );

	// Every wp-admin tab: release 2's (design 4.2) and the rest (design 10).
	const tabs = [
		[ 'wpcpm', '' ],
		[ 'wpcpm-institutions', 'queue' ], [ 'wpcpm-institutions', 'pipeline' ], [ 'wpcpm-institutions', 'accounts' ], [ 'wpcpm-institutions', 'reports' ], [ 'wpcpm-institutions', 'agreements' ], [ 'wpcpm-institutions', 'sync' ],
		[ 'wpcpm-sponsors', 'queue' ], [ 'wpcpm-sponsors', 'sponsors' ], [ 'wpcpm-sponsors', 'accounts' ], [ 'wpcpm-sponsors', 'offers' ], [ 'wpcpm-sponsors', 'interests' ], [ 'wpcpm-sponsors', 'reports' ], [ 'wpcpm-sponsors', 'agreements' ], [ 'wpcpm-sponsors', 'listing' ],
		[ 'wpcpm-mentors', 'queue' ], [ 'wpcpm-mentors', 'accounts' ], [ 'wpcpm-mentors', 'engagement' ], [ 'wpcpm-mentors', 'sync' ],
		[ 'wpcpm-students', 'accounts' ], [ 'wpcpm-students', 'registrations' ], [ 'wpcpm-students', 'interests' ], [ 'wpcpm-students', 'sync' ],
		[ 'wpcpm-administrators', 'accounts' ], [ 'wpcpm-administrators', 'capabilities' ],
		[ 'wpcpm-tools', '' ], [ 'wpcpm-tool-header-notices', '' ], [ 'wpcpm-tool-emails', 'templates' ], [ 'wpcpm-tool-emails', 'footer' ], [ 'wpcpm-tool-emails', 'log' ],
		[ 'wpcpm-tool-handbook', '' ], [ 'wpcpm-tool-mentor-status-checker', '' ], [ 'wpcpm-tool-duplicate-finder', '' ], [ 'wpcpm-tool-track-builder', '' ],
		[ 'wpcpm-settings', 'connection' ], [ 'wpcpm-settings', 'people' ], [ 'wpcpm-settings', 'institutions' ], [ 'wpcpm-settings', 'sponsors' ], [ 'wpcpm-settings', 'security' ], [ 'wpcpm-settings', 'mail' ], [ 'wpcpm-settings', 'advanced' ],
	];
	const wpadmin = all.filter( s => s.area === 'wpadmin' ).map( s => {
		const q = new URLSearchParams( s.path.split( '?' )[ 1 ] || '' );
		return [ q.get( 'page' ) || '', q.get( 'tab' ) || '' ];
	} );
	scenario( 'every wp-admin tab of every plugin screen is photographed', [], tabs.filter( ( [ p, t ] ) => ! wpadmin.some( ( [ a, b ] ) => a === p && b === t ) ).map( p => p.join( ' ' ) ) );
	const release2 = [ 'wpcpm-sponsors queue', 'wpcpm-sponsors agreements', 'wpcpm-mentors queue', 'wpcpm-students interests', 'wpcpm-institutions queue', 'wpcpm-institutions pipeline', 'wpcpm-institutions accounts', 'wpcpm-institutions agreements', 'wpcpm-tool-emails log' ];
	const tablet = all.filter( s => s.area === 'wpadmin' && sc.takenAt( s, sc.SIZES[ 2 ], [ 1280, 390, 768 ] ) ).map( s => { const q = new URLSearchParams( s.path.split( '?' )[ 1 ] || '' ); return q.get( 'page' ) + ' ' + q.get( 'tab' ); } );
	scenario( "release 2's tabs are the ones taken at 768", [ [], [] ], [ release2.filter( t => ! tablet.includes( t ) ), tablet.filter( t => ! release2.includes( t ) ) ] );
	scenario( 'the Institutions Accounts tab is photographed in both its states', true, all.some( s => s.area === 'wpadmin' && s.path.includes( 'page=wpcpm-institutions' ) && s.path.includes( 'tab=accounts' ) && s.path.includes( 'wpcpm_institution=' ) ) );

	// ------------------------------------------------------------ widths, the folded menu, the new panels and pages
	const ws = sc.widthsOf( all, '' );
	scenario( 'without --sizes a run takes 1280 and 390, the in-between widths, the wide tables\' and the sponsor agreements card\'s', [ [ 1280, 390 ], [ 1280, 390, 601, 700, 768, 782, 783, 900, 927, 928, 1000, 1024, 716, 880, 1015, 1110, 1200 ] ], [ ws.base, ws.all ] );
	const midScenes = [ 'overview closed', 'overview all-open', 'overview-applications card-open', 'watch closed', 'watch all-open', 'the-program closed', 'the-program all-open' ];
	scenario( 'the Overview as it loads, all open and with Institution applications open, Watch and The program as they load and all open, are taken at every in-between width', midScenes.map( () => true ), midScenes.map( k => { const s = all.find( x => x.area === 'admin-dashboard' && x.screen + ' ' + x.state === k ); return !! s && sc.MID_WIDTHS.every( w => sc.takenAt( s, sc.sizeOf( w ), ws.base ) ); } ) );
	scenario( 'the in-between widths are those gate 4 names', [ 601, 700, 768, 782, 783, 900, 927, 928, 1000, 1024 ], sc.MID_WIDTHS );
	scenario( 'the guide\'s and Program updates\' open scenes press On this page where it is shown', [ true, true ], [ 'contents-open', 'fold-open' ].map( st => { const s = all.find( x => x.area === 'admin-pages' && x.state === st ); return !! s && !! s.press && s.press.scope === '.wpcpm-guide__contents' && s.press.ifShown === true && ! s.act; } ) );
	scenario( 'the Events card is taken at 1015 as well', true, all.some( s => s.screen === 'overview-sponsor-events' && s.state === 'card-open' && sc.takenAt( s, sc.sizeOf( 1015 ), ws.base ) ) );
	// The overflow check's verdict (gate 3's case: a 429px page in a 390px window, which innerWidth hid under phone emulation).
	scenario( 'the overflow check writes H-SCROLL for a page wider than the window', [ 'H-SCROLL x-390: the page is 429px wide in a 390px window' ], sc.overflowLines( 'x-390', { scrollWidth: 429, width: 390, lines: [] } ) );
	scenario( 'and an OVERFLOW line per element past its card, none when nothing is wider', [ [ 'OVERFLOW x-390: past its card: div.row "A" ends at 428px, its limit 374px (card section "B")' ], [] ], [ sc.overflowLines( 'x-390', { scrollWidth: 390, width: 390, lines: [ { kind: 'OVERFLOW', what: 'past its card', el: 'div.row "A"', right: 428, limit: 374, card: 'section "B"' } ] } ), sc.overflowLines( 'x-1280', { scrollWidth: 1280, width: 1280, lines: [] } ) ] );
	// Gate 6: a stacked table's cell (gate 5's Last run, 36px past its track at 682px of main area).
	scenario( 'the overflow check writes a CELL line for a stacked cell wider than its box or running into the next pair', [ 'CELL x-716: content wider than its box: td.wpcpm-ui-cell--date "Last run" is 186px, its box 150px (row tr "Students")', 'CELL x-716: text into the next pair: td "Last run" ends at 420px, the next pair begins at 400px (row tr "Students")' ], sc.overflowLines( 'x-716', { scrollWidth: 716, width: 716, lines: [ { kind: 'CELL', what: 'content wider than its box', el: 'td.wpcpm-ui-cell--date "Last run"', right: 186, limit: 150, card: 'tr "Students"' }, { kind: 'CELL', what: 'text into the next pair', el: 'td "Last run"', right: 420, limit: 400, card: 'tr "Students"' } ] } ) );
	scenario( 'and the check reads every stacked row of a data table, its cells by scrollWidth against clientWidth', true, /table\.wpcpm-ui-table tr/.test( require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/scenes.mjs' ), 'utf8' ) ) && /c\.scrollWidth > c\.clientWidth \+ 1/.test( require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/scenes.mjs' ), 'utf8' ) ) );
	scenario( 'Watch with every card open is taken at 716 and 880 too', true, ( () => { const w = sc.scenes().find( s => s.screen === 'watch' && s.state === 'all-open' ); return w.widths.includes( 716 ) && w.widths.includes( 880 ); } )() );
	scenario( 'snap.mjs runs the check against the emulated width, never innerWidth', true, /page\.evaluate\(overflowFacts, size\.width\)/.test( require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/snap.mjs' ), 'utf8' ) ) && /const W = Number\(width\) \|\| innerWidth;/.test( require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/scenes.mjs' ), 'utf8' ) ) );
	scenario( 'the sponsor agreements card alone is taken at 601, 782 and 1015 as well', [ 'admin-dashboard-overview-sponsor-agreements-card-open-1015', 'admin-dashboard-overview-sponsor-agreements-card-open-601', 'admin-dashboard-overview-sponsor-agreements-card-open-782' ], [ 601, 782, 1015 ].flatMap( w => all.filter( s => sc.takenAt( s, sc.sizeOf( w ), ws.base ) && /sponsor-agreements/.test( s.screen ) ).map( s => sc.fileName( s, sc.sizeOf( w ) ) ) ).sort() );
	scenario( '--sizes takes any list of widths', [ 1000, 1440 ], sc.widthsOf( all, '1000,1440' ).base );
	scenario( 'a width of its own is 900 tall at scale 1, under 600 at scale 2', [ { width: 1015, height: 900, scale: 1 }, { width: 500, height: 900, scale: 2 }, sc.SIZES[ 0 ] ], [ sc.sizeOf( 1015 ), sc.sizeOf( 500 ), sc.sizeOf( 1280 ) ] );
	scenario( 'sizeOf refuses what is not a width', 'threw', ( () => { try { sc.sizeOf( 'x' ); return 'returned'; } catch ( e ) { return 'threw'; } } )() );
	const at = w => all.filter( s => sc.takenAt( s, sc.sizeOf( w ), ws.base ) ).map( s => sc.fileName( s, sc.sizeOf( w ) ) ).sort();
	scenario( 'at 1015 The program and the Cohort timeline every card open, the sponsor agreements, the Events card and the two reports cards are the scenes', [ 'admin-dashboard-cohort-timeline-all-open-1015', 'admin-dashboard-overview-reports-card-open-1015', 'admin-dashboard-overview-sponsor-agreements-card-open-1015', 'admin-dashboard-overview-sponsor-events-card-open-1015', 'admin-dashboard-overview-sponsor-reports-card-open-1015', 'admin-dashboard-the-program-all-open-1015' ], at( 1015 ) );
	scenario( 'the Semester reports and Sponsor reports cards alone are taken at 601, 768 and 1015 as well, the Requests card at 601 and 768', [ 'admin-dashboard-overview-reports-card-open-1015', 'admin-dashboard-overview-reports-card-open-601', 'admin-dashboard-overview-reports-card-open-768', 'admin-dashboard-overview-requests-card-open-601', 'admin-dashboard-overview-requests-card-open-768', 'admin-dashboard-overview-sponsor-reports-card-open-1015', 'admin-dashboard-overview-sponsor-reports-card-open-601', 'admin-dashboard-overview-sponsor-reports-card-open-768' ], [ 601, 768, 1015 ].flatMap( w => all.filter( s => s.state === 'card-open' && /^overview-(reports|sponsor-reports|requests)$/.test( s.screen ) && sc.takenAt( s, sc.sizeOf( w ), ws.base ) ).map( s => sc.fileName( s, sc.sizeOf( w ) ) ) ).sort() );
	scenario( 'at 1110 they are taken open and with the menu folded', [ 'admin-dashboard-cohort-timeline-all-open-1110', 'admin-dashboard-cohort-timeline-all-open-menu-folded-1110', 'admin-dashboard-the-program-all-open-1110', 'admin-dashboard-the-program-all-open-menu-folded-1110' ], at( 1110 ) );
	scenario( 'a folded scene is never taken at 1280 or 390', [], at( 1280 ).concat( at( 390 ) ).filter( f => f.includes( 'menu-folded' ) ) );
	scenario( 'a --sizes width is taken for every scene but the folded ones', all.filter( s => ! s.onlyWidths ).length, all.filter( s => sc.takenAt( s, sc.sizeOf( 1440 ), [ 1440 ] ) ).length );
	const ctx = { scripts: [], addInitScript( f ) { this.scripts.push( f ); } };
	await sc.prepare( ctx, { folded: false } );
	const unfolded = ctx.scripts.length;
	await sc.prepare( ctx, { folded: true } );
	scenario( 'prepare stores the frame\'s folded state before the page loads, for a folded scene only', [ 0, 1, true ], [ unfolded, ctx.scripts.length, /wpcpm-frame-folded/.test( String( ctx.scripts[ 0 ] ) ) ] );
	const newPanels = [ 'sponsor-applications approve', 'requests details-decline', 'sponsor-events cancel-note', 'sponsor-events change', 'sponsor-events cancel-event' ];
	scenario( 'the panels the shared parts gained are scenes, each "after only"', newPanels.map( () => 'after' ), newPanels.map( k => { const [ card, panel ] = k.split( ' ' ); const s = all.find( x => x.screen === 'overview-' + card && x.state === panel + '-panel-open' ); return s ? s.panelBuild : 'missing'; } ) );
	scenario( 'the Events card\'s planner fold is a scene', true, all.some( s => s.screen === 'overview-sponsor-events' && s.state === 'plan-fold-open' && s.act.openFold ) );
	scenario( 'every opener pattern allows the item\'s title after its words (no $)', [], sc.PANELS.filter( p => /\$$/.test( p.text ) ).map( p => p.card + ' ' + p.panel ) );
	scenario( 'the Administrator guide and the Program updates are taken as they load and with a fold open', [ 'guide as-loaded', 'guide contents-open', 'program-updates as-loaded', 'program-updates fold-open' ], all.filter( s => s.area === 'admin-pages' ).map( s => s.screen + ' ' + s.state ) );
	scenario( 'a _PATH token is filled as a path, not encoded', '/2026/11/05/an-update/', sc.fill( { path: '{UPDATES_PATH}' }, { UPDATES_PATH: '/2026/11/05/an-update/' } ).path );
	scenario( 'any other token is encoded', '/x/?r=a%26b', sc.fill( { path: '/x/?r={DESIGN_RECORD}' }, { DESIGN_RECORD: 'a&b' } ).path );

	// ------------------------------------------------------------ the guard, on a stand-in for WP-CLI
	const copy = await import( pathToFileURL( path.join( ROOT, 'bin/ui/copy.mjs' ) ).href );
	const answering = ( siteurl, home ) => {
		const asked = [];
		const ask = args => { asked.push( args.join( ' ' ) ); return args[ 2 ] === 'siteurl' ? siteurl : home; };
		return { ask, asked };
	};
	const outcome = ( base, stand ) => { try { copy.refuseUnlessCopy( base, stand.ask ); return 'returned'; } catch ( e ) { return String( e.message ).startsWith( 'refusing: ' ) ? 'refused' : 'threw ' + e.message; } };
	scenario( 'refuseUnlessCopy refuses a base that is not BASE, before asking the copy anything', [ 'refused', [] ], ( () => { const st = answering( copy.BASE, copy.BASE ); return [ outcome( 'http://localhost:8901', st ), st.asked ]; } )() );
	scenario( 'refuseUnlessCopy refuses when the copy answers another siteurl', 'refused', outcome( copy.BASE, answering( 'https://example.org', copy.BASE ) ) );
	scenario( 'refuseUnlessCopy refuses when the copy answers another home', 'refused', outcome( copy.BASE, answering( copy.BASE, 'https://example.org' ) ) );
	scenario( 'refuseUnlessCopy returns when both siteurl and home are BASE, having asked for both', [ 'returned', [ 'option get siteurl', 'option get home' ] ], ( () => { const st = answering( copy.BASE, copy.BASE ); return [ outcome( copy.BASE, st ), st.asked ]; } )() );

	// ------------------------------------------------------------ error pages and the proxy after a restore
	scenario( 'errorPage: the proxy\'s 502 is refused by its answer', 'the page answered 502', sc.errorPage( { status: 502, title: '502 Bad Gateway', text: '502 Bad Gateway nginx' } ) );
	scenario( 'errorPage: the proxy\'s page is refused by its words when no answer was read', 'the proxy answered with an error page', sc.errorPage( { status: 0, title: '502 Bad Gateway', text: '502 Bad Gateway\nnginx/1.30.5' } ) );
	scenario( 'errorPage: a wp_die page (body#error-page) is refused at 200', "WordPress's error page (wp_die)", sc.errorPage( { status: 200, title: 'WordPress', bodyId: 'error-page', text: 'The link you followed has expired.' } ) );
	scenario( 'errorPage: the critical error is refused', "WordPress's error page", sc.errorPage( { status: 200, title: 'WordPress', text: 'There has been a critical error on this website.' } ) );
	scenario( 'errorPage: a 404 is refused', 'the page answered 404', sc.errorPage( { status: 404, title: 'Page not found', text: 'Nothing here' } ) );
	scenario( 'errorPage: a dashboard at 200 is not an error page, whatever it says further down', '', sc.errorPage( { status: 200, title: 'Administrator Dashboard', bodyId: '', text: 'Overview\n' + 'x'.repeat( 700 ) + ' 502 Bad Gateway' } ) );
	const proxyRun = async ( codes, tries = 30 ) => {
		const log = [];
		let i = 0;
		let result;
		try {
			result = await copy.waitForCopy( { code: async () => { const c = codes[ Math.min( i, codes.length - 1 ) ]; i++; log.push( 'code ' + c ); return c; }, restart: () => log.push( 'restart proxy' ), pause: async () => {}, say: () => {}, tries } );
		} catch ( e ) {
			result = String( e.message ).startsWith( 'refusing: ' ) ? 'refused' : 'threw ' + e.message;
		}
		return { result, log };
	};
	scenario( 'waitForCopy: a copy that answers 200 at once is not touched', { result: 1, log: [ 'code 200' ] }, await proxyRun( [ 200 ] ) );
	scenario( 'waitForCopy: 502 three times restarts the proxy once, then 200 goes on', { result: 4, log: [ 'code 502', 'code 502', 'code 502', 'restart proxy', 'code 200' ] }, await proxyRun( [ 502, 502, 502, 200 ] ) );
	scenario( 'waitForCopy: a copy that never answers 200 is refused after two restarts', { result: 'refused', restarts: 2, tries: 30 }, await ( async () => { const r = await proxyRun( [ 502 ] ); return { result: r.result, restarts: r.log.filter( l => l === 'restart proxy' ).length, tries: r.log.filter( l => l.startsWith( 'code' ) ).length }; } )() );

	// ------------------------------------------------------------ the comparer
	scenario( 'verdict: average:inf and the same sizes is same', 'same', cmp.verdict( '[Parsed_psnr_0 @ 0x1] PSNR r:inf g:inf b:inf average:inf min:inf max:inf', '1280x900', '1280x900' ) );
	scenario( 'verdict: average:42.1 is differs', 'differs', cmp.verdict( '[Parsed_psnr_0 @ 0x1] PSNR r:41.0 g:42.0 b:43.3 average:42.1 min:40.0 max:44.0', '1280x900', '1280x900' ) );
	scenario( 'verdict: different sizes is differs, even at average:inf', 'differs', cmp.verdict( '[Parsed_psnr_0 @ 0x1] PSNR average:inf', '1280x900', '1280x901' ) );
	scenario( 'verdict: no psnr line at all is differs', 'differs', cmp.verdict( '', '1280x900', '1280x900' ) );

	console.log( '\n' + ran + ' scenarios, ' + failed + ' differ' );
	process.exit( failed ? 1 : 0 );
}() ).catch( e => {
	console.log( 'FAIL the harness could not run: ' + String( e && e.message || e ).split( '\n' )[ 0 ] );
	console.log( '\n' + ( ran + 1 ) + ' scenarios, ' + ( failed + 1 ) + ' differ' );
	process.exit( 1 );
} );
