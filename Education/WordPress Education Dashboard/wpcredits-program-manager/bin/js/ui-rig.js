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
	// The Institution Dashboard's views and pages carry their own widths (seventeen), pinned below, not these lists'.
	const isInstitutionScene = s => !! s.reader || !! s.gateB || !! s.noScript || s.area === 'institution-pages';
	scenario( 'scenes() lists scenes', true, Array.isArray( all ) && all.length > 50 );
	scenario( 'every scene has an area, a screen, a state and a path on the site (or a _PATH token)', [], all.filter( s => ! s.area || ! s.screen || ! s.state || typeof s.path !== 'string' || ! ( s.path.startsWith( '/' ) || /^\{[A-Z_]+_PATH\}$/.test( s.path ) ) ).map( s => s.screen || '?' ) );

	for ( const size of sc.SIZES ) {
		const names = all.filter( s => sc.takenAt( s, size ) ).map( s => sc.fileName( s, size ) );
		const dup = names.filter( ( n, i ) => names.indexOf( n ) !== i );
		scenario( `file names are unique at ${ size.width }`, [], dup );
		scenario( `file names at ${ size.width } read <area>-<screen>-<state>-${ size.width } in lowercase and hyphens`, [], names.filter( n => ! new RegExp( `^[a-z0-9]+(-[a-z0-9]+)*-${ size.width }$` ).test( n ) ) );
	}
	scenario( 'at 768, when --sizes lists it, only wp-admin tabs and the in-between widths\' dashboard scenes are taken', [], all.filter( s => sc.takenAt( s, sc.SIZES[ 2 ], [ 1280, 390, 768 ] ) && s.area !== 'wpadmin' && ! isInstitutionScene( s ) && ! ( s.widths || [] ).includes( 768 ) ).map( s => s.screen ) );
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
	// The Institution Dashboard in the dashboards' frame: every view for every kind of reader, at seventeen widths.
	const seventeen = [ 390, 601, 700, 716, 768, 782, 783, 880, 900, 927, 928, 1000, 1015, 1024, 1110, 1200, 1280 ];
	scenario( 'INSTITUTION_WIDTHS are the seventeen widths', seventeen, sc.INSTITUTION_WIDTHS );
	scenario( 'INSTITUTION_VIEWS are the eight views of the design, in the menu\'s order', [ 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events', 'administrators' ], sc.INSTITUTION_VIEWS );
	const byReader = all.filter( s => s.area === 'institution-dashboard' && s.reader && ! s.folded );
	const viewsOf = reader => [ ...new Set( byReader.filter( s => s.reader === reader && ! s.choice ).map( s => s.view ) ) ];
	scenario( 'the readers with scenes are the six (a locked school with no agreement at all among them)', [ 'admin-no-school', 'admin-viewing-school', 'representative', 'representative-agreement-in-review', 'representative-no-agreement', 'tutor' ], [ ...new Set( byReader.map( s => s.reader ) ) ].sort() );
	const wantViews = {
		'representative': [ 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events' ],
		'representative-agreement-in-review': [ 'overview', 'agreement' ],
		'tutor': [ 'overview', 'students', 'reports', 'institution', 'agreement' ],
		'admin-viewing-school': [ 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ],
		'representative-no-agreement': [ 'overview', 'agreement' ],
	};
	for ( const [ reader, want ] of Object.entries( wantViews ) ) {
		scenario( `${ reader }: scenes for exactly these views, ${ want.join( ', ' ) }`, [ ...want ].sort(), viewsOf( reader ).sort() );
	}
	scenario( 'a tutor has no enroll or events scene, a locked school only overview and agreement, only an Administrator For Administrators', [ false, false, [ 'agreement', 'overview' ], 0, 1 ], [
		viewsOf( 'tutor' ).includes( 'enroll' ), viewsOf( 'tutor' ).includes( 'events' ), viewsOf( 'representative-agreement-in-review' ).sort(),
		byReader.filter( s => s.view === 'administrators' && s.reader !== 'admin-viewing-school' ).length,
		[ ...new Set( byReader.filter( s => s.view === 'administrators' ).map( s => s.reader ) ) ].length,
	] );
	scenario( 'every reader scene is all-open or, for an Overview, as it loads (closed), and every Overview of a school is taken both ways', [], [
		...byReader.filter( s => ! [ 'all-open', 'closed' ].includes( s.state ) ).map( s => s.screen + ' ' + s.state ),
		...[ 'representative', 'representative-agreement-in-review', 'representative-no-agreement', 'tutor', 'admin-viewing-school' ].filter( r => ! [ 'all-open', 'closed' ].every( st => byReader.some( s => s.reader === r && s.view === 'overview' && s.state === st ) ) ),
		...byReader.filter( s => s.state === 'closed' && s.view !== 'overview' ).map( s => s.screen + ' closed' ),
	] );
	scenario( 'every reader scene is taken at each of the seventeen widths', [], byReader.filter( s => ! seventeen.every( w => sc.takenAt( s, sc.sizeOf( w ), [ 1280, 390 ] ) ) ).map( s => s.screen + ' ' + s.state ) );
	scenario( 'and at no other width', [], byReader.filter( s => ! s.onlyWidths || s.onlyWidths.join() !== seventeen.join() ).map( s => s.screen + ' ' + s.state ) );
	const loginOfReader = { 'representative': '{DESIGN_LOGIN}', 'representative-agreement-in-review': '{UNIVERSITY_LOGIN}', 'representative-no-agreement': '{NOAGREEMENT_LOGIN}', 'tutor': '{DESIGN_TUTOR_LOGIN}' };
	scenario( 'each reader signs in as its own account; an Administrator signs in as nobody (the rig\'s own Administrator)', [], byReader.filter( s => s.login !== loginOfReader[ s.reader ] ).map( s => s.screen ) );
	const addrOf = s => new URLSearchParams( ( s.path.split( '#' )[ 0 ].split( '?' )[ 1 ] || '' ) );
	scenario( 'each scene\'s address holds its view (the Overview none), and the school only for an Administrator viewing it', [], byReader.filter( s => {
		const q = addrOf( s );
		const viewOk = s.view === 'overview' ? ! q.has( 'wpcpm_view' ) : q.get( 'wpcpm_view' ) === s.view;
		const schoolOk = s.reader === 'admin-viewing-school' ? q.get( 'wpcpm_institution_view' ) === '{DESIGN_RECORD}' : ! q.has( 'wpcpm_institution_view' );
		return ! s.path.startsWith( '/institution-dashboard/' ) || ! viewOk || ! schoolOk;
	} ).map( s => s.screen + ' ' + s.path ) );
	scenario( 'the Administrator with no school: the choice on the bare page and on ?wpcpm_view=students, both with nothing named', [ '/institution-dashboard/', '/institution-dashboard/?wpcpm_view=students' ], byReader.filter( s => s.reader === 'admin-no-school' ).map( s => s.path ) );
	scenario( 'and those two scenes are told apart by name from every other', [ true, true ], [ 'admin-no-school-choose-an-institution', 'admin-no-school-choice-keeps-students' ].map( n => byReader.some( s => s.reader === 'admin-no-school' && s.screen === n ) ) );
	scenario( 'the four old institution scenes keep their names (the comparison needs them)', [
		'admin-viewing-school all-open', 'admin-viewing-school report-draft-open', 'representative all-open', 'representative-agreement-in-review all-open',
	], all.filter( s => s.area === 'institution-dashboard' && ! s.reader && ! s.gateB && ! s.noScript && s.state !== 'old-address' && s.state !== 'refused-view' ).map( s => s.screen + ' ' + s.state ) );
	// The old addresses: each must land on its view, and says how the snapper can tell (an `expect`).
	const old = all.filter( s => s.area === 'institution-dashboard' && s.state === 'old-address' );
	scenario( 'the old addresses are scenes that must land: a student, a report, #wpcpm-import, a batch', [
		[ 'students-card', '?wpcpm_institution_student={DESIGN_STUDENT}' ],
		[ 'reports-draft', '?wpcpm_report=2026-H1' ],
		[ 'enroll-hash', '#wpcpm-import' ],
		[ 'batch-enroll', '?wpcpm_batch={DESIGN_BATCH}' ],
	], old.map( s => [ s.screen, s.path.replace( /^\/institution-dashboard\//, '' ).replace( /&wpcpm_institution_view=\{DESIGN_RECORD\}$/, '' ) ] ) );
	// Only the #wpcpm-import hash is moved (by the script, so the address changes); a student and a report are read from
	// the address without it being rewritten, so those two prove the view the page DREW: the frame's current menu item
	// (a.wpcpm-frame__item.is-current, aria-current="page") for that view and the view's own card.
	const expectOf = name => ( old.find( s => s.screen === name ) || { expect: {} } ).expect;
	const current = v => `a.wpcpm-frame__item.is-current[aria-current="page"][href*="wpcpm_view=${ v }"]`;
	// Gate B: the design school's first student has an account (states.php's accounts), so the old address draws that
	// student's card (its Student Report Card fold, `data-wpcpm-report`, class-wpcpm-institution-student-view.php:725),
	// not the roster (`#wpcpm-roster`, class-wpcpm-institution-roster-view.php:151).
	scenario( 'students-card and reports-draft expect, with no address, every one of: the view\'s current menu item and the view\'s card', [
		{ selectors: [ current( 'students' ), '.wpcpm-ui-item__head', '[data-wpcpm-report]' ] },
		{ selectors: [ current( 'reports' ), '#wpcpm-report' ] },
	], [ 'students-card', 'reports-draft' ].map( n => expectOf( n ) ) );
	scenario( 'the #wpcpm-import scene is the bare page, waits for the script to move it, and expects the address', [ '/institution-dashboard/#wpcpm-import', true, 'wpcpm_view=enroll' ], [ old.find( s => s.screen === 'enroll-hash' ).path, expectOf( 'enroll-hash' ).wait === true, expectOf( 'enroll-hash' ).address ] );
	scenario( 'a batch by its old address expects Enroll students current and the import card', { selectors: [ current( 'enroll' ), '#wpcpm-import' ] }, expectOf( 'batch-enroll' ) );
	// A view the reader may not open, asked for by address, draws the Overview: the Overview's item current (its link names no view).
	const refused = all.filter( s => s.state === 'refused-view' );
	const overviewCurrent = 'a.wpcpm-frame__item.is-current[aria-current="page"]:not([href*="wpcpm_view="])';
	scenario( 'a locked school asking for Students and a tutor asking for Enroll students each expect the Overview current', [
		[ 'locked-asks-students', '{UNIVERSITY_LOGIN}', '/institution-dashboard/?wpcpm_view=students', [ overviewCurrent ] ],
		[ 'tutor-asks-enroll', '{DESIGN_TUTOR_LOGIN}', '/institution-dashboard/?wpcpm_view=enroll', [ overviewCurrent ] ],
	], refused.map( s => [ s.screen, s.login, s.path, ( s.expect || {} ).selectors ] ) );
	scenario( 'currentItem(): the Overview names no view, any other view its own', [ overviewCurrent, current( 'agreement' ) ], [ sc.currentItem( 'overview' ), sc.currentItem( 'agreement' ) ] );
	// The menu folded at 1110 and 1200: every reader scene all open, the choice, the guide and the update has a twin.
	const folded17 = all.filter( s => ( ( s.area === 'institution-dashboard' && s.reader ) || s.area === 'institution-pages' ) && s.folded );
	const unfoldedOpen = all.filter( s => ( ( s.area === 'institution-dashboard' && s.reader && ! s.folded ) || ( s.area === 'institution-pages' && ! s.folded ) ) && s.state !== 'closed' );
	scenario( 'every Institution Dashboard reader scene open, the choice, the guide and the update are taken folded at 1110 and 1200 alone', [ [], [] ], [
		unfoldedOpen.filter( s => ! folded17.some( f => f.screen === s.screen && f.state === s.state + '-menu-folded' && f.onlyWidths.join() === '1110,1200' && f.path === s.path ) ).map( s => s.screen + ' ' + s.state ),
		folded17.filter( f => ! f.onlyWidths || f.onlyWidths.join() !== '1110,1200' ).map( f => f.screen ),
	] );
	// The snapper's reading of `expect`: the verdicts, and that a scene that fails it saves no picture.
	scenario( 'expectProblem(): nothing expected, or all held, is no problem', [ '', '', '' ], [
		sc.expectProblem( undefined, {} ),
		sc.expectProblem( { address: 'wpcpm_view=enroll', selector: '#a' }, { address: 'http://x/?wpcpm_view=enroll#wpcpm-import', found: { '#a': true } } ),
		sc.expectProblem( { selectors: [ '#a', '#b' ] }, { address: '', found: { '#a': true, '#b': true } } ),
	] );
	scenario( 'expectProblem(): an address not held and every missing selector are named', [
		'the address does not hold wpcpm_view=enroll (it is http://x/#wpcpm-import)',
		'missing: #b, #c',
		'the address does not hold v=1 (it is nothing); missing: #a',
	], [
		sc.expectProblem( { address: 'wpcpm_view=enroll' }, { address: 'http://x/#wpcpm-import', found: {} } ),
		sc.expectProblem( { selector: '#b', selectors: [ '#a', '#c' ] }, { address: '', found: { '#a': true } } ),
		sc.expectProblem( { address: 'v=1', selectors: [ '#a' ] }, {} ),
	] );
	const snapText = require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/snap.mjs' ), 'utf8' );
	scenario( 'snap.mjs reads each scene\'s expect after staging it and, when it fails, reports the scene and saves no picture', [ true, true ], [
		/row\.how = await stage\(page, filled, BASE\);\s*const missed = await checkExpect\(page, filled\);/.test( snapText ),
		/if \(missed && !NO_PICTURES\) \{ row\.skipped = `expect failed: \$\{missed\}`; results\.push\(row\); console\.log\('  EXPECT', file, missed\); return; \}/.test( snapText ) && snapText.indexOf( 'const missed' ) < snapText.indexOf( 'page.screenshot' ),
	] );
	scenario( 'snap.mjs --beside restores nothing, needs the frozen moment and leaves the banners\' option to the run that restored', [ true, true, true ], [
		/if \(!opt\('beside'\)\) await restore\('ui-ready'\);\n\s*else if \(!frozenNow\(\)\) throw/.test( snapText ),
		/if \(opt\('beside'\) && \(!opt\('skip-seed'\) \|\| STATE\)\) throw/.test( snapText ),
		/if \(!opt\('beside'\)\) try \{ wp\(\['option', 'delete', 'wpcpm_ui_rig_keep_banners'\]\); \} catch \{\}/.test( snapText ),
	] );
	scenario( 'checkExpect() waits up to 10 seconds for an address when wait is set', true, /export async function checkExpect\(page, scene, waitMs = 10000\)/.test( require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/scenes.mjs' ), 'utf8' ) ) );
	scenario( 'every other scene that has an expect gives an address or selectors', [], all.filter( s => s.expect && ! s.expect.address && ! s.expect.selector && ! s.expect.selectors ).map( s => s.screen ) );
	// The guide and an update, for a representative and for an Administrator viewing the school.
	const pages = all.filter( s => s.area === 'institution-pages' && ! s.folded );
	scenario( 'the Institution guide and one update, each for a representative and for an Administrator viewing the school', [
		[ 'guide-administrator-viewing', '/institution-guide/?wpcpm_institution_view={DESIGN_RECORD}', '' ],
		[ 'guide-representative', '/institution-guide/', '{DESIGN_LOGIN}' ],
		[ 'update-administrator-viewing', '{INSTITUTION_UPDATES_VIEWING_PATH}', '' ],
		[ 'update-representative', '{INSTITUTION_UPDATES_PATH}', '{DESIGN_LOGIN}' ],
	], pages.map( s => [ s.screen, s.path, s.login || '' ] ).sort( ( a, b ) => a[ 0 ].localeCompare( b[ 0 ] ) ) );
	scenario( 'and each is taken at the seventeen widths', [], pages.filter( s => ! s.onlyWidths || s.onlyWidths.join() !== seventeen.join() ).map( s => s.screen ) );
	// The invented people and the addresses of the new scenes and of the rig's seed.
	const rigSource = [ 'bin/ui/scenes.mjs', 'bin/ui/seed.mjs', 'bin/js/ui-rig.js' ].map( f => ( { f, text: require( 'fs' ).readFileSync( path.join( ROOT, f ), 'utf8' ) } ) );
	scenario( 'every email address in a rig file ends at example.org, example.com or example.test', [], rigSource.flatMap( ( { f, text } ) => ( text.match( /[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/g ) || [] ).filter( m => ! /@example\.(org|com|test)$/i.test( m ) ).map( m => f + ' ' + m ) ) );
	const seedText = rigSource[ 1 ].text;
	scenario( 'the seed adds one invented tutor, tutor.example@example.org, and lookups() answers {DESIGN_TUTOR_LOGIN}, {DESIGN_STUDENT}, {INSTITUTION_UPDATES_PATH} and {INSTITUTION_UPDATES_VIEWING_PATH}', [ true, true, true, true ], [
		seedText.includes( "email: 'tutor.example@example.org'" ), /DESIGN_TUTOR_LOGIN: loginOf\(TUTOR\.email\)/.test( seedText ), /DESIGN_STUDENT:/.test( seedText ), /INSTITUTION_UPDATES_PATH:/.test( seedText ) && /INSTITUTION_UPDATES_VIEWING_PATH:/.test( seedText ),
	] );
	scenario( 'the tutor step is idempotent (it asks whether the address is listed first) and fails loudly when the Add a tutor form is not on the page', [ true, true, true ], [
		/export async function addTutor/.test( seedText ),
		/WPCPM_Institution_Tutors::list_for/.test( seedText ) && /already listed|alreadyListed/.test( seedText ),
		/throw new Error\([^)]*Add a tutor form/.test( seedText ),
	] );
	scenario( 'the tutor\'s account is made as the accept route makes it: WPCPM_Roles::insert_user with ROLE_INSTITUTION, attach by the representative at the tutor level, no copied role and no subscriber', [ true, true, true, true, false, false ], [
		/WPCPM_Roles::insert_user/.test( seedText ), /WPCPM_Roles::ROLE_INSTITUTION/.test( seedText ),
		/WPCPM_Institution_Members::attach\([^;]*LEVEL_TUTOR/.test( seedText ), /WPCPM_Institution_Members::level_of/.test( seedText ),
		/subscriber/.test( seedText ), /\$rep->roles/.test( seedText ),
	] );
	scenario( 'the seed follows the views: the agreement is generated and uploaded on the Agreement view, Enroll students opened only when it is a closed fold', [ true, true, false ], [
		/\/institution-dashboard\/\?wpcpm_view=agreement/.test( seedText ) && ! /page\.goto\(BASE \+ '\/institution-dashboard\/', \{ waitUntil/.test( seedText ),
		/if \(await sum\.count\(\)\)/.test( seedText ),
		/if \(!\(await det\.evaluate\(d => d\.open\)\)\) await sum\.click\(\);\n  await det\.getByLabel/.test( seedText ),
	] );
	scenario( 'the seed makes an update institutions read through the post editor, a school with no agreement, and lookups() answers {DESIGN_BATCH} and {NOAGREEMENT_LOGIN}', [ true, true, true, true ], [
		/export async function addInstitutionUpdate/.test( seedText ) && /post-new\.php/.test( seedText ) && /wpcpm-access-level-field/.test( seedText ),
		/await approveSchool\(newContext, BASE, NOAGREEMENT\.name\)/.test( seedText ),
		/DESIGN_BATCH:/.test( seedText ), /NOAGREEMENT_LOGIN: inState\(NOAGREEMENT, 'none', \(\) => loginOf\(NOAGREEMENT\.email\)\)/.test( seedText ),
	] );
	scenario( 'and make() calls it after the school has its tutor list', true, /await addTutor\(newContext, BASE\)/.test( seedText ) );
	scenario( 'the guide\'s job "A school applied" with its notes, as the audit took it: revealed and cut from its heading', true, all.some( s => s.area === 'guide' && s.section === '#a-school-applied' && s.act && s.act.reveal === '#a-school-applied' ) );
	scenario( 'stage() refuses a page that sent the browser to sign in, so a sign-in page is never saved as a screen', true, /wp-login\\.php[\s\S]*document\.body\.classList\.contains\('login'\)[\s\S]*throw new Error\('the page asked to sign in/.test( require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/scenes.mjs' ), 'utf8' ) ) );
	scenario( "the Administrator guide's first screen", true, all.some( s => s.area === 'guide' && s.path === '/administrator-guide/' && s.viewportOnly ) );

	// ------------------------------------------------------------ Gate B: every open state of the Institution Dashboard on the parts
	// Gate B's list (the plan's gate briefs) and who can see each state, from the frame design's section 3 and the cards'
	// own rules: a tutor sees the students, their cards and Student Report Cards and the reports and changes nothing;
	// only an Administrator has the editor, Revisions, Change the tracks and Change who follows it up; the school's
	// "Ask to correct these" is the school's (an Administrator's second form reads "Correct these"); news is the school's own.
	const R = 'representative', T = 'tutor', A = 'admin-viewing-school';
	const pair = s => [ 'representative-' + s, 'admin-viewing-' + s ];
	const gateWant = {
		'student-drew': [ R, T, A ], 'student-sky': [ R, T, A ], 'student-card': [ R, T, A ], 'student-card-invited': [ R, T, A ],
		'student-report-card': [ R, T, A ], 'student-correct': [ R, A ],
		'details-correct-now': [ R, A ], 'details-ask-checked': [ R ], 'details-correct-checked': [ A ], 'details-tracks': [ A ], 'details-assignment': [ A ],
		'reports-index': [ R, T, A ], 'reports-read': [ R, T, A ], 'reports-editor': [ A ], 'report-revisions': [ A ],
		'invite-refused': [ R, A ], 'tutor-add-refused': [ R, A ], 'details-refused': [ R, A ], 'student-correct-refused': [ R, A ],
		'agreement-none': pair( 'no-agreement' ), 'agreement-generated': pair( 'generated' ), 'agreement-submitted': [ 'representative-agreement-in-review', 'admin-viewing-in-review' ],
		'agreement-returned': pair( 'returned' ), 'agreement-revoked': pair( 'revoked' ), 'agreement-accepted': [ R, A ], 'agreement-newer-copy': pair( 'newer-copy' ),
		'enroll-check-13': pair( 'long-words' ), 'enroll-file-chosen': [ R, A ], 'agreement-file-chosen': pair( 'no-agreement' ),
		'viewing-as': [ A ], 'long-words-overview': pair( 'long-words' ), 'long-words-students': pair( 'long-words' ), 'long-words-institution': pair( 'long-words' ),
		'overview-contact': [ R, T, A ], 'overview-good-to-know': [ R, T ],
	};
	// The no-script set is a pass of its own (fix round 1), checked below.
	const gate = all.filter( s => s.area === 'institution-dashboard' && s.gateB && ! s.folded && ! s.noScript );
	scenario( 'GATE_B_STATES lists every open state of Gate B\'s list, and nothing else', Object.keys( gateWant ).sort(), Object.keys( sc.GATE_B_STATES || {} ).sort() );
	scenario( 'each Gate B state has a scene for every reader who can see it and for no other', [], Object.entries( gateWant ).filter( ( [ k, want ] ) => JSON.stringify( [ ...want ].sort() ) !== JSON.stringify( [ ...new Set( gate.filter( s => s.gateB === k ).map( s => s.who ) ) ].sort() ) ).map( ( [ k ] ) => k + ': ' + gate.filter( s => s.gateB === k ).map( s => s.who ).join( ',' ) ) );
	scenario( 'and one scene per state and reader', [], gate.filter( ( s, i ) => gate.findIndex( o => o.gateB === s.gateB && o.who === s.who ) !== i ).map( s => s.gateB + ' ' + s.who ) );
	// Who signs in as whom, and whose school an Administrator names: the seed's accounts and records, as lookups() answers them.
	const gateReaders = {
		[ R ]: [ '{DESIGN_LOGIN}', '' ], [ T ]: [ '{DESIGN_TUTOR_LOGIN}', '' ], [ A ]: [ undefined, '{DESIGN_RECORD}' ],
		'representative-no-agreement': [ '{NOAGREEMENT_LOGIN}', '' ], 'admin-viewing-no-agreement': [ undefined, '{NOAGREEMENT_RECORD}' ],
		'representative-generated': [ '{GENERATED_LOGIN}', '' ], 'admin-viewing-generated': [ undefined, '{GENERATED_RECORD}' ],
		'representative-agreement-in-review': [ '{UNIVERSITY_LOGIN}', '' ], 'admin-viewing-in-review': [ undefined, '{UNIVERSITY_RECORD}' ],
		'representative-returned': [ '{RETURNED_LOGIN}', '' ], 'admin-viewing-returned': [ undefined, '{RETURNED_RECORD}' ],
		'representative-revoked': [ '{REVOKED_LOGIN}', '' ], 'admin-viewing-revoked': [ undefined, '{REVOKED_RECORD}' ],
		'representative-newer-copy': [ '{INSTITUTE_LOGIN}', '' ], 'admin-viewing-newer-copy': [ undefined, '{INSTITUTE_RECORD}' ],
		'representative-long-words': [ '{LONG_LOGIN}', '' ], 'admin-viewing-long-words': [ undefined, '{LONG_RECORD}' ],
	};
	scenario( 'each Gate B scene signs in as its reader and names its school only for an Administrator', [], gate.filter( s => {
		const want = gateReaders[ s.who ];
		const q = addrOf( s );
		return ! want || s.login !== want[ 0 ] || ( q.get( 'wpcpm_institution_view' ) || '' ) !== want[ 1 ] || ! s.path.startsWith( '/institution-dashboard/' );
	} ).map( s => s.gateB + ' ' + s.who ) );
	// A state B2 photographs already (a view all open, an Overview as it loads) is that scene, named for the state too, and
	// keeps no expect, so today's build still draws its before picture; every new one is after only and proves its view.
	scenario( 'each Gate B scene is drawn on the view its state belongs to; each new one proves it by the frame\'s current menu item and is after only', [], gate.filter( s => {
		const view = ( sc.GATE_B_STATES || {} )[ s.gateB ];
		const q = addrOf( s );
		const proved = s.reader ? s.who === s.reader && s.view === view && ! s.expect : s.panelBuild === 'after' && !! s.expect && ( ( s.expect || {} ).selectors || [] ).includes( sc.currentItem( view ) );
		return ! view || ( view === 'overview' ? q.has( 'wpcpm_view' ) : q.get( 'wpcpm_view' ) !== view ) || ! proved;
	} ).map( s => s.gateB + ' ' + s.who ) );
	scenario( 'every Gate B scene is taken at the seventeen widths, and again folded at 1110 and 1200', [ [], [] ], [
		gate.filter( s => ! s.onlyWidths || s.onlyWidths.join() !== seventeen.join() ).map( s => s.gateB + ' ' + s.who ),
		gate.filter( s => s.state !== 'closed' && ! all.some( f => f.folded && f.screen === s.screen && f.state === s.state + '-menu-folded' && f.onlyWidths.join() === '1110,1200' && f.path === s.path ) ).map( s => s.gateB + ' ' + s.who ),
	] );
	const instWidths = [ ...seventeen ].map( w => sc.sizeOf( w ) );
	const instNames = instWidths.flatMap( z => all.filter( s => s.area === 'institution-dashboard' && sc.takenAt( s, z ) ).map( s => sc.fileName( s, z ) ) );
	scenario( 'the Institution Dashboard\'s file names are unique at every one of the seventeen widths', [], instNames.filter( ( n, i ) => instNames.indexOf( n ) !== i ) );
	// A fold, a Decide row or a form is opened by its words or its anchor (`openFold`, `press`); a refusal is posted by
	// the scene itself at every width (the kept typing is handed back once), and the scene expects the sentence under its box.
	const refusals = [ 'invite-refused', 'tutor-add-refused', 'details-refused', 'student-correct-refused' ];
	scenario( 'each refusal scene posts its form itself (act.refuse) and expects the problem printed under a box (wpcpm-field__problem)', [], gate.filter( s => refusals.includes( s.gateB ) && ! ( s.act && s.act.refuse && s.act.refuse.form && ( s.act.refuse.fill || [] ).length && ( ( s.expect || {} ).selectors || [] ).some( x => /\.wpcpm-field__problem/.test( x ) ) ) ).map( s => s.gateB + ' ' + s.who ) );
	scenario( 'and only a refusal scene posts', [], all.filter( s => s.act && s.act.refuse && ! refusals.includes( s.gateB ) ).map( s => s.screen ) );
	const folds = [ 'student-drew', 'student-sky', 'student-report-card', 'student-correct', 'details-correct-now', 'details-ask-checked', 'details-correct-checked', 'details-tracks', 'details-assignment', 'report-revisions' ];
	scenario( 'each inner fold of Gate B\'s list is opened by its words (openFold) and expected open', [], gate.filter( s => folds.includes( s.gateB ) && ! ( s.act && s.act.openFold && s.act.openFold.text && ( ( s.expect || {} ).selectors || [] ).some( x => /\[open\]/.test( x ) ) ) ).map( s => s.gateB + ' ' + s.who ) );
	scenario( 'the file box is taken after a file is chosen (act.choose, nothing posted), on Enroll students and on an unsettled Agreement', [], gate.filter( s => /file-chosen$/.test( s.gateB ) && ! ( s.act && s.act.choose && s.act.choose.input && s.act.choose.name ) ).map( s => s.gateB + ' ' + s.who ) );
	const scenesText = require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/scenes.mjs' ), 'utf8' );
	scenario( 'stage() posts a scene\'s refusal and waits for the page it lands on, chooses a file without posting, and never presses a link in a fold\'s head', [ true, true, true, true ], [
		/if \(act\.refuse\) \{ await refuse\(page, act\.refuse\);/.test( scenesText ),
		/waitForNavigation/.test( scenesText ),
		/if \(act\.choose\)[^\n]*setInputFiles/.test( scenesText ),
		/elementFromPoint[\s\S]{0,200}closest\('a'\)/.test( scenesText ),
	] );
	scenario( 'the enrollment check is the long-word school\'s list of thirteen lines, and the student\'s card is the design school\'s first student', [ true, true, true ], [
		gate.filter( s => s.gateB === 'enroll-check-13' ).every( s => /wpcpm_batch=\{LONG_BATCH\}/.test( s.path ) && ( ( s.expect || {} ).selectors || [] ).some( x => /tr:nth-child\(13\)/.test( x ) ) ),
		gate.filter( s => /^student-(card|report-card|correct|correct-refused)$/.test( s.gateB ) ).every( s => /wpcpm_institution_student=\{DESIGN_STUDENT\}/.test( s.path ) ),
		gate.filter( s => s.gateB === 'student-card-invited' ).every( s => /wpcpm_institution_student=\{DESIGN_STUDENT_INVITED\}/.test( s.path ) ),
	] );
	// No scene or seed step names a class the old cards printed: the page prints none of them now.
	const oldSelectors = [ 'wpcpm-roster__', 'wpcpm-import__', 'wpcpm-details__', 'wpcpm-people__', 'wpcpm-agreement-panel', 'wpcpm-institution__card', 'wpcpm-group__disclosure', 'section.wpcpm-roster' ];
	scenario( 'no scene and no seed step names a selector of the old Institution Dashboard cards', [], [ 'bin/ui/scenes.mjs', 'bin/ui/seed.mjs' ].flatMap( f => oldSelectors.filter( o => require( 'fs' ).readFileSync( path.join( ROOT, f ), 'utf8' ).includes( o ) ).map( o => f + ' ' + o ) ) );
	// Every token a scene names is one lookups() answers (or the state's own, PENDING_*).
	const tokens = [ ...new Set( all.flatMap( s => [ s.path, s.login || '' ].join( ' ' ).match( /\{[A-Z_]+\}/g ) || [] ) ) ].map( t => t.slice( 1, -1 ) );
	scenario( 'every token a scene names is answered by lookups() or by the state', [], tokens.filter( t => ! /^PENDING_/.test( t ) && ! new RegExp( '\\b' + t + ': ' ).test( seedText.slice( seedText.indexOf( 'export function lookups()' ) ) ) ) );
	// The long words: invented, "Example" in each, listed in INVENTED, each made through a real form.
	const seedMod = await import( pathToFileURL( path.join( ROOT, 'bin/ui/seed.mjs' ) ).href ).catch( e => ( { error: e } ) );
	const inv = seedMod.INVENTED || {};
	const longWord = v => String( v || '' ).split( /\s+/ ).find( w => w.length === 40 ) || '';
	scenario( 'the long words: a school\'s name, a student\'s and a tutor\'s each hold a 40-character unbroken word with Example in it, and an address is 48 characters at example.org', [ true, true, true, true ], [
		/example/i.test( longWord( ( inv.LONGWORD || {} ).name ) ),
		/example/i.test( longWord( ( inv.LONG_STUDENT || {} ).name ) ),
		/example/i.test( longWord( ( inv.LONG_TUTOR || {} ).name ) ),
		/^[a-z0-9.]+@example\.org$/.test( ( inv.LONG_TUTOR || {} ).email || '' ) && 48 === ( ( inv.LONG_TUTOR || {} ).email || '' ).length,
	] );
	scenario( 'and the seed makes each of them through the site\'s own forms: the application, Enroll students, Add a tutor', [ true, true, true ], [
		/await applyAndUpload\(newContext, BASE, LONGWORD\)/.test( seedText ) && /async function applyAndUpload\(newContext, BASE, school\) \{[^}]*await applySchool\(newContext, BASE, school\);/.test( seedText ),
		/enrollStudents\(newContext, BASE, LONGWORD\.name, \[[^\]]*LONG_STUDENT/.test( seedText ),
		/addTutorAs\(newContext, BASE, LONGWORD, LONG_TUTOR\)/.test( seedText ),
	] );
	// The seed's holes from Gate A, closed through the real forms (and states.php for what no form reaches).
	scenario( 'the seed assigns an Administrator through Change who follows it up, answers a details request the school asked, and saves the report once (Revisions)', [ true, true, true ], [
		/export async function assignAdministrator/.test( seedText ) && /select\[name="wpcpm_assigned"\]/.test( seedText ) && /soft\('assigned-administrator', \(\) => assignAdministrator\(newContext, BASE\)\)/.test( seedText ),
		/export async function askAndDecline/.test( seedText ) && /askDetails\(newContext, BASE, DESIGN, \{ city: /.test( seedText ) && /input\[name="wpcpm_details_\$\{key\}"\]/.test( seedText ) && /textarea\[name="wpcpm_request_note"\]/.test( seedText ) && /button\[name="decision"\]\[value="decline"\]/.test( seedText )
			// after states(), whose request at the design school is withdrawn first through Withdraw it, and one is asked at another school
			&& /ACTION_DETAILS_WITHDRAW/.test( seedText ) && /applyAccounts\(\);\s*\/\/[^\n]*\n\s*await soft\('details-request-declined', \(\) => askAndDecline\(newContext, BASE\)\);\s*await soft\('details-request-waiting-elsewhere', \(\) => askElsewhere\(newContext, BASE\)\);/.test( seedText ),
		/export async function saveReport/.test( seedText ) && /Save the report/.test( seedText ),
	] );
	scenario( 'the seed reaches each agreement state through the forms: generated, returned with a note, revoked on the Agreements tab, a newer copy uploaded', [ true, true, true, true ], [
		/await generateOnly\(newContext, BASE, loginOf\(GENERATED\.email\)\)/.test( seedText ),
		/textarea\[name="wpcpm_agreement_note"\]/.test( seedText ) && /await returnAgreement\(newContext, BASE, RETURNED\.name\)/.test( seedText ),
		/input\[name="wpcpm_agreement_post"\]/.test( seedText ) && /await revokeAgreement\(newContext, BASE, REVOKED\.name\)/.test( seedText ),
		/soft\('agreement-newer-copy', \(\) => uploadNewerCopy\(newContext, BASE, INSTITUTE\)\)/.test( seedText ),
	] );
	const statesText = require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/states.php' ), 'utf8' );
	scenario( 'states.php gives two of the design school\'s students accounts (no form makes a student account), one signed in and one invited and not signed in, and the seed asks for it after its forms', [ true, true, true, true ], [
		/if \( ! empty\( \$in\['accounts'\] \) \)/.test( statesText ) && statesText.indexOf( "! empty( $in['accounts'] )" ) < statesText.indexOf( '// ------------------------------------------------------------------ events' ),
		/'drew\.example@example\.org' => 'signed-in'/.test( statesText ) && /'sky\.example@example\.org' => 'invited'/.test( statesText ),
		/WPCPM_Roles::ROLE_STUDENT/.test( statesText ) && /WPCPM_Roster_Index::insert\( \$design, \$row \)/.test( statesText ),
		/states\(\);\s*applyAccounts\(\);/.test( seedText ),
	] );

	// ------------------------------------------------------------ Gate B, fix round 1
	// I2. Each agreement state proves itself by its own words as the panel prints them: the tag (WPCPM_UI::tag(),
	// class-wpcpm-ui.php:242-244, a `wpcpm-status--<tone>` span) and the sentence after it (class-wpcpm-institution-panel.php:
	// none :935, generated :1528, submitted :2407, returned :2414 and :1550, revoked :2414 and :1553, accepted :2707-2709,
	// a newer copy :2664). Each is [element, words the element holds].
	const agreementWords = {
		'agreement-none': [ [ '#wpcpm-agreement', 'The program has no Collaboration Agreement recorded for your institution.' ] ],
		'agreement-generated': [ [ '#wpcpm-agreement', 'You generated the program\'s agreement on' ], [ '#wpcpm-agreement', 'It is not signed and uploaded yet.' ] ],
		'agreement-submitted': [ [ '#wpcpm-agreement .wpcpm-status--amber', 'Waiting for review' ] ],
		'agreement-returned': [ [ '#wpcpm-agreement .wpcpm-status--red', 'Returned' ], [ '#wpcpm-agreement', 'An Administrator returned your agreement, with this note:' ] ],
		'agreement-revoked': [ [ '#wpcpm-agreement .wpcpm-status--red', 'Revoked' ], [ '#wpcpm-agreement', 'The program revoked this institution\'s agreement.' ] ],
		'agreement-accepted': [ [ '#wpcpm-agreement .wpcpm-status--green', 'Accepted' ] ],
		'agreement-newer-copy': [ [ '#wpcpm-agreement .wpcpm-status--green', 'Accepted' ], [ '#wpcpm-agreement', 'A newer signed agreement is waiting for review.' ] ],
	};
	scenario( 'each agreement state\'s scenes are new ones (after only) and expect that state\'s own tag and sentence', [], Object.entries( agreementWords ).flatMap( ( [ k, words ] ) => {
		const of = gate.filter( s => s.gateB === k );
		return of.length && of.every( s => ! s.reader && JSON.stringify( ( s.expect || {} ).texts ) === JSON.stringify( words ) ) ? [] : [ k ];
	} ) );
	const lookupsText = seedText.slice( seedText.indexOf( 'export function lookups()' ) );
	const gated = [ [ 'NOAGREEMENT', 'none' ], [ 'GENERATED', 'generated' ], [ 'UNIVERSITY', 'submitted' ], [ 'RETURNED', 'returned' ], [ 'REVOKED', 'revoked' ], [ 'INSTITUTE', 'newer-copy' ] ];
	scenario( 'lookups() answers a school\'s login and record only while its agreement is in the state its scenes are named for, and the long-word school\'s only once its long words are made', [], [
		...gated.flatMap( ( [ t, st ] ) => [ 'LOGIN', 'RECORD' ].filter( k => ! new RegExp( `\\b${ t }_${ k }: inState\\(${ t }, '${ st }', \\(\\) => ` ).test( lookupsText ) ).map( k => `${ t }_${ k }` ) ),
		...[ 'LONG_LOGIN', 'LONG_RECORD', 'LONG_BATCH' ].filter( k => ! new RegExp( `\\b${ k }: longWords\\(\\(\\) => ` ).test( lookupsText ) ),
		...( /function inState\(school, state, read\)[\s\S]{0,400}agreementFact\(key, 'state'\)[\s\S]{0,200}pending_id/.test( seedText ) ? [] : [ 'inState() reads the summary\'s state (and pending_id for a newer copy)' ] ),
		...( /function longWords\(read\)[\s\S]{0,600}LONG_STUDENT\.email[\s\S]{0,400}LONG_TUTOR\.email/.test( seedText ) ? [] : [ 'longWords() asks for the student and the tutor' ] ),
	] );
	scenario( 'expectProblem(): words an element must hold are read, and a missing element or missing words are named', [ '', 'without "Returned" in #a; no #b' ], [
		sc.expectProblem( { texts: [ [ '#a', 'Returned' ] ] }, { address: '', found: {}, texts: { '#a': 'x Returned y' } } ),
		sc.expectProblem( { texts: [ [ '#a', 'Returned' ], [ '#b', 'z' ] ] }, { address: '', found: {}, texts: { '#a': 'Revoked', '#b': null } } ),
	] );
	scenario( 'expectProblem(): a tag set in capitals holds its words, and what must be absent is named when it is drawn', [ '', 'drawn, and must not be: #row', '' ], [
		sc.expectProblem( { texts: [ [ '#a', 'Accepted' ] ] }, { address: '', found: {}, texts: { '#a': 'ACCEPTED' } } ),
		sc.expectProblem( { absent: [ '#row' ] }, { address: '', found: { '#row': true }, texts: {} } ),
		sc.expectProblem( { absent: [ '#row' ] }, { address: '', found: { '#row': false }, texts: {} } ),
	] );
	// Point 3: Accept it is pressed on the named school's row, never the first one.
	scenario( 'acceptAgreement() accepts the named school\'s waiting copy, by its post, and every call names a school', [ true, false ], [
		/export async function acceptAgreement\(newContext, BASE, name\)[\s\S]{0,700}agreementFact\(name, 'pending_id'\)[\s\S]{0,700}input\[name="wpcpm_agreement_post"\]\[value="\$\{pending\}"\]/.test( seedText ),
		/acceptAgreement\(newContext, BASE\)/.test( seedText ),
	] );
	// I1 and point 4. Each refusal posts its own form, types the boxes it names a value its handler refuses by a stated rule,
	// and expects exactly the problem element under that box (WPCPM_Form_Errors::problem(), class-wpcpm-form-errors.php:236,
	// its id the box's id and `-problem`) and the box marked invalid (:213).
	const noDot = v => /^[^@\s]+@[^@.\s]+$/.test( v ); // an address with no dot in its domain: sanitize_email() drops it, is_email() refuses it
	const endId = 'wpcpm-student-f' + require( 'crypto' ).createHash( 'md5' ).update( 'students|End Date' ).digest( 'hex' ).slice( 0, 12 ); // WPCPM_Institution_Student_Form::control_id()
	const designStart = ( seedText.match( /enrollStudents\(newContext, BASE, DESIGN\.name, [^\n]*start: '(\d{4}-\d\d-\d\d)'/ ) || [] )[ 1 ] || '';
	const refusalRules = {
		'invite-refused': { form: '#wpcpm-people-invite form', boxes: [ 'input[name="email"]' ], rule: f => noDot( f[ 0 ][ 1 ] ), problem: '#wpcpm-people-invite[open] p.wpcpm-field__problem[id^="wpcpm-invite-email-"][id$="-problem"]', invalid: '#wpcpm-people-invite[open] input[name="email"][aria-invalid="true"]' },
		'tutor-add-refused': { form: '#wpcpm-tutors-add form', boxes: [ 'input[name="name"]', 'input[name="email"]' ], rule: f => '' !== f[ 0 ][ 1 ] && noDot( f[ 1 ][ 1 ] ), problem: '#wpcpm-tutors-add[open] p.wpcpm-field__problem[id^="wpcpm-tutor-email-"][id$="-problem"]', invalid: '#wpcpm-tutors-add[open] input[name="email"][aria-invalid="true"]' },
		// An emptied required answer: Contact Person is `required` in the application (class-wpcpm-institution-application.php:779).
		'details-refused': { form: '#wpcpm-details-now form', boxes: [ 'input[name="wpcpm_details_contact_person"]' ], rule: f => '' === f[ 0 ][ 1 ], problem: '#wpcpm-details-now[open] p.wpcpm-field__problem#wpcpm-details-contact_person-problem', invalid: '#wpcpm-details-now[open] #wpcpm-details-contact_person[aria-invalid="true"]' },
		// An end date before its start date (the seed enrolls the design school's students from designStart).
		'student-correct-refused': { form: '#wpcpm-student-form form', boxes: [ '#' + endId ], rule: f => /^\d{4}-\d\d-\d\d$/.test( f[ 0 ][ 1 ] ) && '' !== designStart && f[ 0 ][ 1 ] < designStart, problem: `#wpcpm-student-form[open] p.wpcpm-field__problem#${ endId }-problem`, invalid: `#wpcpm-student-form[open] #${ endId }[aria-invalid="true"]` },
	};
	const refusalScenes = all.filter( s => s.area === 'institution-dashboard' && refusals.includes( s.gateB ) );
	scenario( 'each refusal names its form and the boxes it types, types what its handler refuses (no dot in the domain, an emptied required answer, an end before the start), and expects exactly the problem under that box and the box marked invalid', [], refusalScenes.filter( s => {
		const r = refusalRules[ s.gateB ];
		const p = ( s.act || {} ).refuse || {};
		const fill = p.fill || [];
		const view = ( sc.GATE_B_STATES || {} )[ s.gateB ];
		return ! r || p.form !== r.form || JSON.stringify( fill.map( f => f[ 0 ] ) ) !== JSON.stringify( r.boxes ) || ! r.rule( fill ) || JSON.stringify( ( s.expect || {} ).selectors ) !== JSON.stringify( [ sc.currentItem( view ), r.problem, r.invalid ] );
	} ).map( s => s.screen + ' ' + s.state ) );
	// Point 5. The compact Viewing as is the head's first row's GET form with its select (class-wpcpm-ui.php:1493, :1503;
	// the field's name is WPCPM_Institution_Roster::ARG_VIEW, class-wpcpm-institutions-dashboard.php:2326); the long words are on the page.
	const VIEWING = '.wpcpm-head .wpcpm-ui-head-row form.wpcpm-ui-viewing select[name="wpcpm_institution_view"]';
	const long40 = v => String( v || '' ).split( /\s+/ ).find( w => w.length === 40 ) || '';
	scenario( 'viewing-as expects the compact control itself, and each long-word scene the long word\'s own text', [ true, true, true, true ], [
		gate.filter( s => s.gateB === 'viewing-as' ).length > 0 && gate.filter( s => s.gateB === 'viewing-as' ).every( s => ! s.reader && ( ( s.expect || {} ).selectors || [] ).includes( VIEWING ) ),
		gate.filter( s => s.gateB === 'long-words-overview' ).every( s => JSON.stringify( ( s.expect || {} ).texts ) === JSON.stringify( [ [ '.wpcpm-head', ( inv.LONGWORD || {} ).name ] ] ) ),
		gate.filter( s => s.gateB === 'long-words-students' ).every( s => JSON.stringify( ( s.expect || {} ).texts ) === JSON.stringify( [ [ '#wpcpm-roster', long40( ( inv.LONG_STUDENT || {} ).name ) ] ] ) ),
		gate.filter( s => s.gateB === 'long-words-institution' ).every( s => JSON.stringify( ( s.expect || {} ).texts ) === JSON.stringify( [ [ 'body', long40( ( inv.LONG_TUTOR || {} ).name ) ], [ 'body', ( inv.LONG_TUTOR || {} ).email ] ] ) ),
	] );
	// Point 6. A missing box fails its scene within five seconds, not thirty.
	scenario( 'openAround() and act.choose ask whether the box is there first and wait five seconds at most', [ true, true ], [
		/async function openAround\(page, selector\) \{\s*if \(!\(await page\.locator\(selector\)\.count\(\)\)\) throw new Error/.test( scenesText ) && /evaluate\([\s\S]{0,400}\{ timeout: 5000 \}/.test( scenesText ),
		/if \(act\.choose\) \{ if \(!\(await page\.locator\(act\.choose\.input\)\.count\(\)\)\) throw new Error/.test( scenesText ) && /setInputFiles\(\{[^}]*\}, \{ timeout: 5000 \}\)/.test( scenesText ),
	] );
	// Point 7. With no script: each view of the representative and of an Administrator viewing the school at 390, 768 and 1280
	// as it loads, the Viewing as select with its Show, and one refusal of each form kind posted with no script.
	const ns = all.filter( s => s.noScript );
	const nsWant = [
		...[ 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events' ].map( v => 'representative ' + v + ' as-loaded' ),
		...[ 'overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators' ].map( v => 'admin-viewing-school ' + v + ' as-loaded' ),
		'admin-viewing-school viewing-as as-loaded',
		...refusals.map( r => 'representative ' + r + ' refused' ),
	].sort();
	scenario( 'the no-script set: every view of the representative and of an Administrator viewing the school, the Viewing as select with its Show, and one refusal of each form kind', nsWant, ns.map( s => `${ s.who } ${ s.view || s.gateB } ${ s.state }` ).sort() );
	scenario( 'each no-script scene is taken at 390, 768 and 1280 alone, presses no fold, and its file names say no-script', [], ns.filter( s => ! s.onlyWidths || s.onlyWidths.join() !== '390,768,1280' || ( s.act && ( s.act.open || s.act.openFold || s.act.openCard ) ) || ! /^noscript-/.test( s.screen ) ).map( s => s.screen ) );
	scenario( 'the no-script Viewing as expects the select and its Show (class-wpcpm-ui.php:1517, the button the script would hide)', true, ns.some( s => s.gateB === 'viewing-as' && JSON.stringify( ( s.expect || {} ).selectors ) === JSON.stringify( [ sc.currentItem( 'overview' ), VIEWING, '.wpcpm-head form.wpcpm-ui-viewing .wpcpm-ui-viewing__show button' ] ) ) );
	const measureText = require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/measure.mjs' ), 'utf8' ) + require( 'fs' ).readFileSync( path.join( ROOT, 'bin/ui/collect.mjs' ), 'utf8' );
	// Gate B, the owner's capture plan (16 Nov 2026): --no-pictures takes every scene's numbers and saves a picture only where a
	// line fires (a failed expect, an overflow line, a measure OFF, a gap off the rhythm, a broken picture of the copy).
	const firedAt = k => snapText.indexOf( k );
	scenario( 'snap.mjs --no-pictures measures every scene, fires on each kind of line, and saves a picture only for a line that fired, after the names check, one picture per scene for each kind of line', [ true, true, true, true, true, true, true, true ], [
		/const NO_PICTURES = !!opt\('no-pictures'\);/.test( snapText ),
		/if \(missed\) fired\.push\(`EXPECT \$\{file\}: \$\{missed\}`\);/.test( snapText ),
		/fired\.push\(\.\.\.row\.overflow\);/.test( snapText ) && /if \(\/ OFF\/\.test\(row\.gate\)\) fired\.push\(row\.gate\);/.test( snapText ),
		/fired\.push\(`GAP \$\{file\}: \$\{x\}`\)/.test( snapText ) && /fired\.push\(`BROKEN \$\{file\}:/.test( snapText ),
		/const raw = await page\.evaluate\(collect, size\.width\);/.test( snapText ) && /export function collect\(W\)/.test( measureText ),
		firedAt( "if (!fired.length) { row.measured = 'no picture: nothing fired';" ) > 0 && firedAt( "if (!fired.length) { row.measured = 'no picture: nothing fired';" ) < firedAt( 'await page.screenshot(' ),
		firedAt( 'const bad = problem(text);' ) > 0 && firedAt( 'const bad = problem(text);' ) < firedAt( "if (!fired.length) { row.measured" ),
		/const fresh = fired\.map\(.*\.filter\(k => !FIRED_SEEN\.has\(k\)\);/.test( snapText ) && firedAt( 'if (!fresh.length)' ) < firedAt( 'await page.screenshot(' ),
	] );
	scenario( 'snap.mjs and measure.mjs open a context with JavaScript off for a no-script scene', [ true, true, true ], [
		/javaScriptEnabled: !noScript/.test( snapText ) && /newContext\(\{ login: filled\.login \|\| ADMIN_LOGIN, size, noScript: !!filled\.noScript \}\)/.test( snapText ),
		/async function newContext\(\{ signedIn = true, login = ADMIN_LOGIN, size = SIZES\[0\], noScript = false \} = \{\}\)/.test( snapText ),
		/javaScriptEnabled: !scene\.noScript/.test( measureText ),
	] );
	// Point 8. What Gate B measures, read from what measure.mjs collects: one button height, card heads 56 and inner fold heads
	// 44, one field height, every row action's border 0; and no selector of a card the page no longer prints.
	const goodRaw = { buttons: [ { h: 40, parts: true }, { h: 40, parts: true }, { h: 30, parts: false } ], fields: [ { h: 40, tag: 'input', parts: true }, { h: 40, tag: 'select', parts: true }, { h: 120, tag: 'textarea', parts: true } ], heads: [ { kind: 'card', h: 56 }, { kind: 'inner', h: 44 } ], rowActions: [ { border: 0 } ] };
	const badRaw = { buttons: [ { h: 40, parts: true }, { h: 36, parts: true } ], fields: [ { h: 40, tag: 'input', parts: true }, { h: 38, tag: 'input', parts: true } ], heads: [ { kind: 'card', h: 57 }, { kind: 'inner', h: 44 }, { kind: 'inner', h: 48 } ], rowActions: [ { border: 0 }, { border: 2 } ] };
	scenario( 'gateMeasures(): the parts\' buttons of one height, card heads 56, inner fold heads 44, the parts\' boxes of one height, row actions with no border', [
		{ buttonHeights: [ 40 ], oneButtonHeight: true, cardHeads: [ 56 ], cardHeads56: true, innerHeads: [ 44 ], innerHeads44: true, fieldHeights: [ 40 ], oneFieldHeight: true, rowActionBorders: 0, rowActionsBorder0: true },
		{ buttonHeights: [ 36, 40 ], oneButtonHeight: false, cardHeads: [ 57 ], cardHeads56: false, innerHeads: [ 44, 48 ], innerHeads44: false, fieldHeights: [ 38, 40 ], oneFieldHeight: false, rowActionBorders: 1, rowActionsBorder0: false },
	], typeof sc.gateMeasures === 'function' ? [ sc.gateMeasures( goodRaw ), sc.gateMeasures( badRaw ) ] : 'no gateMeasures' );
	// Gate B's fix round: four lines the round found were the measure being too strict. Each scenario fails on the fault
	// and passes on the allowed case.
	const gm = raw => ( typeof sc.gateMeasures === 'function' ? sc.gateMeasures( Object.assign( { buttons: [], fields: [], heads: [], rowActions: [] }, raw ) ) : {} );
	// 1. A card head's stacked form, under 800px of main area, is 70px; at desktop widths 56.
	scenario( 'gateMeasures(): a card head under 800px of main area is its stacked form, 70px; 70 at a wide main area, or 56 in a narrow one, is off', [ true, false, false ], [
		gm( { heads: [ { kind: 'card', h: 56 }, { kind: 'card', h: 70, narrow: true } ] } ).cardHeads56,
		gm( { heads: [ { kind: 'card', h: 70 } ] } ).cardHeads56,
		gm( { heads: [ { kind: 'card', h: 56, narrow: true } ] } ).cardHeads56,
	] );
	// 2. The Overview's waiting rows (and its Good to know rows) carry the one secondary button the design gives them.
	scenario( 'gateMeasures(): an Overview waiting row\'s one secondary button is its action; a second bordered one, or a bordered action on any other row, is off', [ [ 0, true ], [ 1, false ], [ 1, false ] ], [
		[ gm( { rowActions: [ { border: 4, overview: true, secondary: true, alone: true } ] } ).rowActionBorders, gm( { rowActions: [ { border: 4, overview: true, secondary: true, alone: true } ] } ).rowActionsBorder0 ],
		[ gm( { rowActions: [ { border: 4, overview: true, secondary: true, alone: false } ] } ).rowActionBorders, gm( { rowActions: [ { border: 4, overview: true, secondary: true, alone: false } ] } ).rowActionsBorder0 ],
		[ gm( { rowActions: [ { border: 4, overview: false, secondary: true, alone: true } ] } ).rowActionBorders, gm( { rowActions: [ { border: 4, overview: false, secondary: true, alone: true } ] } ).rowActionsBorder0 ],
	] );
	// 3. B3, ruled: a fold head of words only is 44px; a head that holds a photo is as tall as the photo and the head's own
	// padding, the same above and below its content, and nothing touches the edge.
	scenario( 'gateMeasures(): a fold head holding a photo is the photo and equal padding, 68px or its stacked lines and equal padding; an unequal or touching one is off', [ true, false, false, false ], [
		gm( { heads: [ { kind: 'inner', h: 44 }, { kind: 'inner', h: 68, photo: { above: 12, below: 12 } }, { kind: 'inner', h: 174, photo: { above: 12, below: 12 } } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 72, photo: { above: 12, below: 16 } } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 44, photo: { above: 0, below: 0 } } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 66 } ] } ).innerHeads44,
	] );
	// Gate B round 2, F8: a card head of one line (no phrase, no tag) is 56px in the narrow form too; one with a phrase or a
	// tag there is 70px, and 56 is a fault of the page. An inner head of words is 44px in the one-row form, its second
	// piece on the title's line; under the title there only when the line is too short for both; in the stacked form
	// its lead's lines and the padding (66px with a second piece).
	scenario( 'gateMeasures(): a one-line card head is 56px in the narrow form; one with a phrase or a tag there is 70px, and 56 is off', [ true, false, false ], [
		gm( { heads: [ { kind: 'card', h: 56, narrow: true, words: false }, { kind: 'card', h: 70, narrow: true, words: true } ] } ).cardHeads56,
		gm( { heads: [ { kind: 'card', h: 56, narrow: true, words: true } ] } ).cardHeads56,
		gm( { heads: [ { kind: 'card', h: 70, narrow: true, words: false } ] } ).cardHeads56,
	] );
	scenario( 'gateMeasures(): an inner head of words: 44px in the one-row form; its second piece under the title there is off while the line has room, allowed when it has none; in the stacked form its lead and the padding', [ true, false, true, true, false ], [
		gm( { heads: [ { kind: 'inner', h: 44, lead: 20, pad: 24 } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 66, lead: 42, pad: 24, second: { under: true, fits: true } } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 66, lead: 42, pad: 24, second: { under: true, fits: false } } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 66, lead: 42, pad: 24, stacked: true, second: { under: true, fits: true } } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 66, lead: 20, pad: 24 } ] } ).innerHeads44,
	] );

	// Gate B round 3, R1: the box model makes any head its lead plus its padding, so the rule asks for the parts' own: 12px
	// above and below (24), and a lead that is its lines (20px each, 2px between the title and a second piece under it).
	scenario( 'gateMeasures(): an inner head with 16px of padding (52px) and one whose lead grew by a margin (48px) are off; 44px of one line and 66px stacked with a second piece hold', [ false, false, true, true ], [
		gm( { heads: [ { kind: 'inner', h: 52, lead: 20, pad: 32, lines: 1 } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 48, lead: 24, pad: 24, lines: 1 } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 44, lead: 20, pad: 24, lines: 1 } ] } ).innerHeads44,
		gm( { heads: [ { kind: 'inner', h: 66, lead: 42, pad: 24, lines: 2, stacked: true, second: { under: true, fits: true } } ] } ).innerHeads44,
	] );

	// 4. The guide pages' own headings are prose, read as an article: not measured against a dashboard block's rhythm.
	// And what collect() reads for the three above, in Chrome on a page of its own (skipped without Playwright).
	const col = await import( pathToFileURL( path.join( ROOT, 'bin/ui/collect.mjs' ) ).href );
	let pw = null;
	try {
		pw = await import( pathToFileURL( process.env.WPCPM_PLAYWRIGHT || path.join( require( 'os' ).homedir(), 'Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs' ) ).href );
	} catch ( e ) {
		pw = null;
	}
	let browser = null;
	if ( pw ) {
		try { browser = await pw.chromium.launch( { channel: 'chrome', headless: true } ); } catch ( e ) { browser = null; }
	}
	if ( ! browser ) {
		console.log( 'skip collect() was not run in Chrome: Playwright or Chrome is not there' );
	} else {
		try {
			const tab = await browser.newPage( { viewport: { width: 1280, height: 900 } } );
			const head = ( h, extra ) => `<summary class="wpcpm-ui-fold__head" style="display:flex;align-items:flex-start;box-sizing:border-box;padding:12px 0;height:${ h }px">${ extra }</summary>`;
			await tab.setContent( `<body style="margin:0"><main>
				<div class="wpcpm-frame__main" style="width:1000px"><section class="wpcpm-ui-fold-card"><details class="wpcpm-ui-fold">${ head( 56, '<span>Wide card</span>' ) }</details></section></div>
				<div class="wpcpm-frame__main" style="width:600px"><section class="wpcpm-ui-fold-card"><details class="wpcpm-ui-fold">${ head( 70, '<span>Narrow card</span><span class="wpcpm-ui-fold__phrase">2 students</span>' ) }</details></section>
				<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait">${ head( 68, '<span class="wpcpm-ui-fold__portrait" style="display:block;width:44px;height:44px"></span><span>Ama</span>' ) }</details>
				<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait">${ head( 72, '<span class="wpcpm-ui-fold__portrait" style="display:block;width:44px;height:44px"></span><span>Ama</span>' ) }</details>
				<section class="wpcpm-institution__waiting"><ul class="wpcpm-ui-list"><li><div class="wpcpm-ui-list__act"><a class="wpcpm-btn wpcpm-btn--secondary" style="border:1px solid" href="#a">Open the list</a></div></li></ul></section>
				<ul class="wpcpm-ui-list"><li><div class="wpcpm-ui-list__act"><a class="wpcpm-btn wpcpm-btn--secondary" style="border:1px solid" href="#b">Resend</a></div></li></ul>
				<div class="wpcpm-guide__body"><p style="margin:0">Prose.</p><h3 style="margin:36px 0 8px">Signing in with a code</h3><p style="margin:0">More prose.</p></div>
				<p style="margin:0">A block.</p><h4 class="wpcpm-ui-h" style="margin:36px 0 8px">Tutors</h4><p style="margin:0">A row.</p>
				</div></main></body>` );
			const raw = await tab.evaluate( col.collect, 1280 );
			const g = sc.gateMeasures( raw );
			scenario( 'collect() in Chrome: a card head knows a narrow main area, a photo head its padding above and below, an Overview row its one secondary button, and a guide\'s heading is prose', [
				[ false, true ], [ { above: 12, below: 12 }, { above: 12, below: 16 } ], [ [ true, true, true ], [ false, true, true ] ], [ true, false ],
				[ 'heading-above 36px h4.wpcpm-ui-h "Tutors"' ], { cardHeads56: true, innerHeads44: false, rowActionBorders: 1 },
			], [
				raw.heads.filter( h => h.kind === 'card' ).map( h => !! h.narrow ),
				raw.heads.filter( h => h.photo ).map( h => h.photo ),
				raw.rowActions.map( a => [ !! a.overview, !! a.secondary, !! a.alone ] ),
				raw.headings.map( h => !! h.prose ),
				col.summarize( raw ).offRhythmList.filter( l => l.startsWith( 'heading-above' ) ),
				{ cardHeads56: g.cardHeads56, innerHeads44: g.innerHeads44, rowActionBorders: g.rowActionBorders },
			] );
			// Round 2, F8: the same reading on the parts' own markup: a wrapped portrait row (its second piece's text two
			// lines), whose room above and below is read by the boxes of the portrait, the lead, the end and the chevron; a
			// one-line card head and one with a phrase in the narrow form; an inner head of words with its second piece under
			// the title in a wide main area with room for both, and one with it on the title's line.
			const tab2 = await browser.newPage( { viewport: { width: 1280, height: 900 } } );
			const sum = ( h, inner ) => `<summary class="wpcpm-ui-fold__head" style="display:flex;align-items:flex-start;gap:12px;box-sizing:border-box;padding:12px 0;height:${ h }px">${ inner }</summary>`;
			await tab2.setContent( `<body style="margin:0;font:400 14px/20px sans-serif"><main>
				<div class="wpcpm-frame__main" style="width:600px">
				<section class="wpcpm-ui-fold-card"><details class="wpcpm-ui-fold">${ sum( 56, '<h3 class="wpcpm-ui-fold__title" style="margin:0">Finishing this placement</h3>' ) }</details></section>
				<section class="wpcpm-ui-fold-card"><details class="wpcpm-ui-fold">${ sum( 56, '<h3 class="wpcpm-ui-fold__title" style="margin:0">Current</h3><span class="wpcpm-ui-fold__phrase">2 students</span>' ) }</details></section>
				<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait">${ sum( 86, '<span class="wpcpm-ui-fold__portrait" style="display:block;flex:none;width:44px;height:44px"></span><span class="wpcpm-ui-fold__lead" style="display:block;width:200px"><span class="wpcpm-ui-fold__title" style="display:block">Ama Example</span><span class="wpcpm-ui-fold__second" style="display:block;margin-top:2px"><span>2026-02-16</span> to <span>2026-09-30</span> · Mia Examplementor</span></span><span class="wpcpm-ui-fold__chev" style="display:block;width:20px;height:20px"></span>' ) }</details>
				</div>
				<div class="wpcpm-frame__main" style="width:1000px">
				<details class="wpcpm-ui-fold wpcpm-ui-fold--inner">${ sum( 66, '<span class="wpcpm-ui-fold__lead" style="display:block;flex:1"><span class="wpcpm-ui-fold__title" style="display:block">Correct these</span><span class="wpcpm-ui-fold__second" style="display:block;margin-top:2px">(saved at once)</span></span><span class="wpcpm-ui-fold__chev" style="display:block;width:20px;height:20px"></span>' ) }</details>
				<details class="wpcpm-ui-fold wpcpm-ui-fold--inner">${ sum( 44, '<span class="wpcpm-ui-fold__lead" style="display:flex;gap:2px 12px;flex:1"><span class="wpcpm-ui-fold__title">Correct these</span><span class="wpcpm-ui-fold__second">(saved at once)</span></span><span class="wpcpm-ui-fold__chev" style="display:block;width:20px;height:20px"></span>' ) }</details>
				</div></main></body>` );
			const raw2 = await tab2.evaluate( col.collect, 1280 );
			scenario( 'collect() in Chrome, round 2: a wrapped photo row is read by its boxes (12 and 12); a card head knows whether it holds a phrase or a tag; an inner head knows its second piece is under a title with room beside it', [
				[ { above: 12, below: 12 } ],
				[ [ true, false ], [ true, true ] ],
				[ { under: true, fits: true }, { under: false, fits: true } ],
				{ cardHeads56: false, innerHeads44: false },
			], [
				raw2.heads.filter( h => h.photo ).map( h => h.photo ),
				raw2.heads.filter( h => h.kind === 'card' ).map( h => [ !! h.narrow, !! h.words ] ),
				raw2.heads.filter( h => h.kind === 'inner' && ! h.photo ).map( h => h.second || null ),
				( g2 => ( { cardHeads56: g2.cardHeads56, innerHeads44: g2.innerHeads44 } ) )( sc.gateMeasures( raw2 ) ),
			] );
			// Task 28 fix round 2, X13: a student's row in the stacked form, a grid whose end group draws no box of its own
			// (display: contents) and whose tag is the grid's item under the name and dates: the room under it is read to the
			// tag, so 12 and 12 pass; the same row with more room under it is still off.
			const tab3 = await browser.newPage( { viewport: { width: 390, height: 900 } } );
			const stacked = pad => `<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait" open><summary class="wpcpm-ui-fold__head" style="display:grid;grid-template-columns:44px minmax(0,1fr) 20px;gap:6px 12px;box-sizing:border-box;padding:12px 0 ${ pad }px"><span class="wpcpm-ui-fold__portrait" style="display:block;width:44px;height:44px;grid-column:1;grid-row:1 / span 2"></span><span class="wpcpm-ui-fold__lead" style="display:block;grid-column:2;grid-row:1"><span class="wpcpm-ui-fold__title" style="display:block">Ama Example</span><span class="wpcpm-ui-fold__second" style="display:block;margin-top:2px">2026-02-16 to 2026-09-30</span></span><span class="wpcpm-ui-fold__end" style="display:contents"><span class="wpcpm-status" style="display:block;height:20px;grid-column:2;grid-row:2;justify-self:start">Program</span></span><span class="wpcpm-ui-fold__chev" style="display:block;width:20px;height:20px;grid-column:3;grid-row:1"></span></summary><div>x</div></details>`;
			await tab3.setContent( `<body style="margin:0;font:400 14px/20px sans-serif"><main><div class="wpcpm-frame__main" style="width:358px">${ stacked( 12 ) }${ stacked( 20 ) }</div></main></body>` );
			const raw3 = await tab3.evaluate( col.collect, 390 );
			scenario( 'collect() in Chrome, X13: a student\'s stacked row is read to its tag under the name, 12 and 12, and its head passes; with more room under it, it is off', [
				[ { above: 12, below: 12 }, { above: 12, below: 20 } ],
				[ true, false ],
			], [
				raw3.heads.filter( h => h.photo ).map( h => h.photo ),
				[ sc.gateMeasures( { heads: raw3.heads.slice( 0, 1 ) } ).innerHeads44, sc.gateMeasures( { heads: raw3.heads.slice( 1 ) } ).innerHeads44 ],
			] );
		} finally {
			await browser.close();
		}
	}

	scenario( 'measure.mjs reads the parts\' marks for them, prints the verdict, and names no class the page no longer prints', [ true, true, [] ], [
		/parts: b\.matches\('\.wpcpm-btn'\)/.test( measureText ) && /parts: f\.matches\('\.wpcpm-ui-field__control'\)/.test( measureText ),
		/gateMeasures\(r\)/.test( measureText ) && /GATE B/.test( measureText ),
		[ 'wpcpm-roster__filters', 'wpcpm-details__row', 'wpcpm-roster__strip-title' ].filter( o => measureText.includes( o ) ),
	] );

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
	const at = w => all.filter( s => ! isInstitutionScene( s ) && sc.takenAt( s, sc.sizeOf( w ), ws.base ) ).map( s => sc.fileName( s, sc.sizeOf( w ) ) ).sort();
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

	// ------------------------------------------------------------ the layout harness's page
	const lay = await import( pathToFileURL( path.join( ROOT, 'bin/ui/layout/page.mjs' ) ).href );
	const doc = lay.page( [ { name: 'Case one', html: '<p>x</p>' } ], [ 358 ], { pluginDir: ROOT, themeDir: path.join( ROOT, 'no-such-theme' ) } );
	scenario( 'layout page(): one case at one width holds its width once', 1, ( doc.match( /width:358px/g ) || [] ).length );
	scenario( 'layout page(): and its main-area container once', 1, ( doc.match( /container:wpcpm-main\/inline-size/g ) || [] ).length );

	// ------------------------------------------------------------ the layout harness, in Chrome
	const run = await import( pathToFileURL( path.join( ROOT, 'bin/ui/layout/run.mjs' ) ).href );
	scenario( 'layout windowsFor(): 358, 569, 736 and 799 have one window each (390, 601, 768, 927: above a 782px window the rail of the menu stands beside the main area); 800 has folded 928 and open 1120', [ [ 390 ], [ 601 ], [ 768 ], [ 927 ], [ 928, 1120 ] ], [ 358, 569, 736, 799, 800 ].map( w => run.windowsFor( w ).map( x => x.win ) ) );
	scenario( 'layout: a page with no case stops the run', 'no case to measure', await run.measureCases( [], [ 358 ] ).then( () => 'ran', e => e.message ) );
	scenario( 'layout: a missing plugin sheet stops the run', true, await run.measureCases( [ { name: 'a', html: '<p>x</p>' } ], [ 358 ], { pluginDir: path.join( ROOT, 'no-such-dir' ) } ).then( () => false, e => /style sheet not found/.test( e.message ) ) );
	let chrome = true;
	const table = body => '<section class="wpcpm-ui-panel"><table class="wpcpm-ui-table"><thead><tr><th>Name</th><th>Mail</th></tr></thead><tbody><tr><td>Ann</td><td>' + body + '</td></tr></tbody></table></section>';
	let got;
	try {
		got = await run.measureCases( [
			{ name: 'short', html: table( 'x' ) },
			{ name: 'unbroken', html: table( '<span style="white-space:nowrap">' + 'x'.repeat( 90 ) + '</span>' ) },
			{ name: 'squeezed', html: '<section class="wpcpm-ui-panel"><div style="display:flex;width:0;overflow:hidden"><div style="flex:none;width:300px;height:10px">wide</div></div></section>' },
			{ name: 'hiddenpanel', html: '<section class="wpcpm-ui-panel" hidden><div style="width:900px;height:10px"></div></section>' },
			{ name: 'wide', html: '<section class="wpcpm-ui-panel"><div class="h-wide" style="height:10px"></div></section>' },
			{ name: 'rail', html: '<div class="wpcpm-frame__menu" style="width:47px;position:relative"><span style="position:absolute;left:50px;width:200px;display:block">Overview label</span></div><section class="wpcpm-ui-panel"><p>Short</p></section>' },
			{ name: 'railwide', html: '<div class="wpcpm-frame__menu" style="width:47px;position:relative"><span style="position:absolute;left:50px;width:200px;display:block">Overview label</span></div><section class="wpcpm-ui-panel"><div style="width:900px;height:10px"></div></section>' },
			{ name: 'ign', html: '<section class="wpcpm-ui-panel"><div class="x"><div style="width:900px;height:10px"></div></div><div class="y"><div style="width:900px;height:10px"></div></div></section>' },
			{ name: 'ignx', html: '<section class="wpcpm-ui-panel"><div class="x"><div style="width:900px;height:10px"></div></div></section>' },
			{ name: 'media', html: '<section class="wpcpm-ui-panel"><div class="h-probe"></div></section>' },
			// A form control's own text scrolls inside its box (Institution Dashboard, Task 16, fix round 1): measured as a box only.
			{ name: 'inputlong', html: '<section class="wpcpm-ui-panel"><input type="text" style="width:200px" value="' + 'Exampleunbrokenstudentnamewithfortychars'.repeat( 2 ).slice( 0, 60 ) + '"><textarea style="width:200px;white-space:pre;overflow-wrap:normal" rows="2">' + 'y'.repeat( 60 ) + '</textarea><select style="width:120px"><option>' + 'z'.repeat( 60 ) + '</option></select></section>' },
			{ name: 'inputwide', html: '<section class="wpcpm-ui-panel"><input type="text" style="width:900px" value="short"></section>' },
			{ name: 'cellwide', html: '<section class="wpcpm-ui-panel"><div style="width:100px;overflow:hidden;white-space:nowrap">' + 'w'.repeat( 60 ) + '</div></section>' },
		], [ 358, 736, 799, 800 ], { extraCss: '.h-probe{height:10px;width:100px}@media (max-width:600px){.h-probe{width:900px}}@media (max-width:1100px){.h-wide{width:2000px}}', themeDir: path.join( ROOT, 'no-such-theme' ), ignore: [ '.x' ] } );
	} catch ( e ) {
		chrome = false;
		console.log( 'skip the layout harness was not run in Chrome: ' + String( e.message ).split( '\n' )[ 0 ] );
	}
	if ( chrome ) {
		const faults = ( name, w ) => got.results.filter( r => r.name === name && r.w === String( w ) ).flatMap( r => r.faults );
		scenario( 'layout: a stacked table with short cells reads ok at 358, 736 and 799 (its hidden thead is not measured)', [ [], [], [] ], [ 358, 736, 799 ].map( w => faults( 'short', w ) ) );
		scenario( 'layout: the same table with a long unbreakable word in a cell faults at 358 and at 799', [ true, true ], [ 358, 799 ].map( w => faults( 'unbroken', w ).length > 0 ) );
		scenario( 'layout: a rule in @media (max-width: 600px) applies at 358 (its own 390 window) and not at 736', [ true, false ], [ 358, 736 ].map( w => faults( 'media', w ).length > 0 ) );
		scenario( 'layout: a folded rail whose labels sit outside it reads ok; the same page with a 900px block in the main area still faults', [ 0, true ], [ faults( 'rail', 358 ).length, faults( 'railwide', 358 ).length > 0 ] );
		scenario( 'layout: --ignore .x silences a fault inside .x and not one beside it', [ 0, true ], [ faults( 'ignx', 358 ).length, faults( 'ign', 358 ).length > 0 ] );
		scenario( 'layout: the run says the menu is left out and names what it ignores', [ true, true ], [ got.notes[ 0 ].includes( 'side menu' ), got.notes.some( n => n === 'ignoring: .x' ) ] );
		scenario( 'layout: a flex box squeezed to width 0 around a 300px child is a fault, not a hidden box', true, faults( 'squeezed', 358 ).length > 0 );
		scenario( 'layout: a 60-character value scrolling inside a text box, a text area and a select that fit their card is no fault', [ [], [] ], [ 358, 736 ].map( w => faults( 'inputlong', w ) ) );
		scenario( 'layout: a text box whose own box is wider than its card still faults', [ true, true ], [ 358, 736 ].map( w => faults( 'inputwide', w ).some( f => /^OVERFLOW |^H-SCROLL /.test( f ) ) ) );
		scenario( 'layout: a cell that is no form control, its content wider than its box, still faults as CELL', [ true, true ], [ 358, 736 ].map( w => faults( 'cellwide', w ).some( f => /^CELL /.test( f ) ) ) );
		scenario( 'layout: a hidden panel holding a 900px block is ok in the first pass and faults with the panels open', [ 0, true ], [ faults( 'hiddenpanel', 358 ).length, faults( 'hiddenpanel', '358 panels open' ).length > 0 ] );
		scenario( 'layout: 800 is measured folded (928) and open (1120): a rule at max-width 1100px bites only the folded one', [ true, false ], [ faults( 'wide', '800 folded' ).length > 0, faults( 'wide', '800 open' ).length > 0 ] );
	}

	// ------------------------------------------------------------ the layout harness's census, GAP and EDGE (Task 27b)
	// The rules, without a browser: a gap under 6px between words (or a control that begins with its label) and a control
	// or a button, or a gap off the rhythm, is off; on the Institution Dashboard it is a GAP fault, elsewhere it is listed.
	const gapOf = ( u, l, gap ) => ( { c: 'form', u, l, gap, a: 'Upper', b: 'Lower' } );
	scenario( 'layout gapIsOff(): a sentence 0px under a button, a field 0px under an action row and a gap of 7px are off; a label 6px over its box, a hint 0px under an option\'s words and 14px between fields are not', [ true, true, true, false, false, false ], [ run.gapIsOff( gapOf( 'action row', 'paragraph', 0 ) ), run.gapIsOff( gapOf( 'action row', 'field', 0 ) ), run.gapIsOff( gapOf( 'paragraph', 'paragraph', 7 ) ), run.gapIsOff( gapOf( 'label', 'box', 6 ) ), run.gapIsOff( gapOf( 'option words', 'hint', 0 ) ), run.gapIsOff( gapOf( 'field', 'field', 14 ) ) ] );
	// Gate B round 3, B14 (ruled: stays): a view head's facts line over its button row stands 18px, the frame head's own
	// value (the copy's 10px gap and the row's 8px), the same on the live Sponsor Dashboard: not off there; 18px in any
	// other container, or another value there, is.
	scenario( 'layout gapIsOff(): a view head\'s facts line 18px over its button row is the frame head\'s own; 18px elsewhere, or 17px there, is off', [ false, true, true ], [
		run.gapIsOff( Object.assign( gapOf( 'facts line', 'action row', 18 ), { c: 'view head' } ) ),
		run.gapIsOff( Object.assign( gapOf( 'facts line', 'action row', 18 ), { c: 'block' } ) ),
		run.gapIsOff( Object.assign( gapOf( 'facts line', 'action row', 17 ), { c: 'view head' } ) ),
	] );
	// Task 28 fix round 2, X14: 28px is the frame's distance between a view's rows, under its head among them; inside a
	// card it is off.
	scenario( 'layout gapIsOff(): 28px between a view root\'s rows is the frame\'s own; 28px inside a card\'s body or a block is off', [ false, false, true, true ], [
		run.gapIsOff( Object.assign( gapOf( 'view head', 'card fold', 28 ), { c: 'view root' } ) ),
		run.gapIsOff( Object.assign( gapOf( 'block', 'card fold', 28 ), { c: 'view root' } ) ),
		run.gapIsOff( Object.assign( gapOf( 'muted line', 'muted line', 28 ), { c: 'fold body' } ) ),
		run.gapIsOff( Object.assign( gapOf( 'paragraph', 'list', 28 ), { c: 'block' } ) ),
	] );
	scenario( 'layout gapIsOff(): within 0.5px of the rhythm is on it (13.6 and 14.5), 14.6 is off', [ false, false, true ], [ 13.6, 14.5, 14.6 ].map( g => run.gapIsOff( gapOf( 'field', 'field', g ) ) ) );
	const seededCensus = { gaps: [ gapOf( 'action row', 'paragraph', 0 ) ], edges: [ { c: 'field set', k: 'legend', at: 2, words: 'Level', of: 'first option' }, { c: 'form', k: 'paragraph', at: 2, words: 'x', of: 'content edge' } ] };
	scenario( 'layout spacingFaults(): on the Institution Dashboard a GAP and an EDGE fault, named with their words; a block\'s own edge is no EDGE fault', [ 'GAP action row to paragraph 0: "Upper" over "Lower"', 'EDGE legend 2: "Level"' ], run.spacingFaults( seededCensus, 'institution' ) );
	scenario( 'layout spacingFaults(): on the Administrator Dashboard (any view) and the Sponsor Dashboard the same census fails on the legend only: its gaps are listed, not failed', [ [ 'EDGE legend 2: "Level"' ], [ 'EDGE legend 2: "Level"' ], [ 'EDGE legend 2: "Level"' ] ], [ 'administrator', 'administrator/watch', 'sponsor' ].map( s => run.spacingFaults( seededCensus, s ) ) );
	if ( chrome ) {
		// Seeded cases on the parts' own sheets (no theme): margins set on the blocks, so each gap is the one written.
		const m0 = 'margin:0';
		const seeds = [
			{ name: 'touch', html: `<section class="wpcpm-ui-panel"><div class="wpcpm-ui-actions" style="${ m0 }"><button type="button" class="wpcpm-btn">Save</button></div><p class="wpcpm-ui-intro" style="${ m0 }">A sentence touching the button.</p></section>` },
			{ name: 'touch-admin', surface: 'administrator', html: `<section class="wpcpm-ui-panel"><div class="wpcpm-ui-actions" style="${ m0 }"><button type="button" class="wpcpm-btn">Save</button></div><p class="wpcpm-ui-intro" style="${ m0 }">A sentence touching the button.</p></section>` },
			{ name: 'seven', html: `<section class="wpcpm-ui-panel"><p class="wpcpm-ui-intro" style="${ m0 }">One</p><p class="wpcpm-ui-intro" style="margin:7px 0 0">Two</p></section>` },
			{ name: 'eight', html: `<section class="wpcpm-ui-panel"><p class="wpcpm-ui-intro" style="${ m0 }">One</p><p class="wpcpm-ui-intro" hidden>Hidden</p><p class="wpcpm-ui-intro" style="margin:8px 0 0">Two</p></section>` },
			{ name: 'beside', html: `<section class="wpcpm-ui-panel"><div style="display:flex;gap:3px"><p class="wpcpm-ui-intro" style="${ m0 }">Left</p><div class="wpcpm-ui-actions" style="${ m0 }"><button type="button" class="wpcpm-btn">Beside</button></div></div></section>` },
			{ name: 'text', html: `<section class="wpcpm-ui-panel"><ol style="${ m0 };padding:0;list-style:none"><li class="wpcpm-ui-checklist__item" style="line-height:20px;font-size:14px">A sentence of the item.<form style="${ m0 }"><div class="wpcpm-ui-field" style="${ m0 }"><label class="wpcpm-ui-field__label" for="s-t">Name</label><input type="text" class="wpcpm-ui-field__control" id="s-t"></div></form></li></ol></section>` },
			{ name: 'legend', html: `<section class="wpcpm-ui-panel"><fieldset class="wpcpm-ui-choices" style="${ m0 };padding:0;border:0"><legend class="wpcpm-ui-choices__legend" style="padding:0 2px">Level</legend><div class="wpcpm-ui-choices__options"><label class="wpcpm-ui-choice"><input type="radio" class="wpcpm-ui-choice__input" name="s-l"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words">Representative</span></span></label></div></fieldset></section>` },
			{ name: 'legend-flush', html: `<section class="wpcpm-ui-panel"><fieldset class="wpcpm-ui-choices" style="${ m0 };padding:0;border:0"><legend class="wpcpm-ui-choices__legend" style="padding:0">Level</legend><div class="wpcpm-ui-choices__options"><label class="wpcpm-ui-choice"><input type="radio" class="wpcpm-ui-choice__input" name="s-m"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words">Representative</span></span></label></div></fieldset></section>` },
		];
		let seeded = null;
		try {
			seeded = await run.measureCases( seeds, [ 358, 960 ], { themeDir: path.join( ROOT, 'no-such-theme' ) } );
		} catch ( e ) {
			console.log( 'skip the census was not run in Chrome: ' + String( e.message ).split( '\n' )[ 0 ] );
		}
		if ( seeded ) {
			const of = ( name, w ) => seeded.results.filter( r => r.name === name && r.w === String( w ) );
			const spacing = ( name, w ) => of( name, w ).flatMap( r => r.faults ).filter( f => /^(GAP|EDGE) /.test( f ) );
			scenario( 'layout GAP: a sentence 0px under an action row fires on the Institution Dashboard at 358 and 960', [ [ 'GAP action row to paragraph 0: "Save" over "A sentence touching the button."' ], [ 'GAP action row to paragraph 0: "Save" over "A sentence touching the button."' ] ], [ spacing( 'touch', 358 ), spacing( 'touch', '960 open' ) ] );
			scenario( 'layout GAP: the same on the Administrator Dashboard fails nothing, and its census lists the gap', [ [], true ], [ spacing( 'touch-admin', 358 ), of( 'touch-admin', 358 )[ 0 ].census.gaps.some( g => 'action row' === g.u && 'paragraph' === g.l && 0 === g.gap ) ] );
			scenario( 'layout GAP: two paragraphs 7px apart fire as off the rhythm', [ 'GAP paragraph to paragraph 7: "One" over "Two"' ], spacing( 'seven', 358 ) );
			scenario( 'layout GAP: a hidden paragraph between two others is no neighbour: 8px, no fault', [ [], [ 8 ] ], [ spacing( 'eight', 358 ), of( 'eight', 358 )[ 0 ].census.gaps.filter( g => 'panel' === g.c ).map( g => g.gap ) ] );
			scenario( 'layout GAP: a sentence and a button side by side in one row are no pair: 3px apart, no fault', [ [], [] ], [ spacing( 'beside', 358 ), of( 'beside', 358 )[ 0 ].census.gaps.filter( g => 'paragraph' === g.u || 'paragraph' === g.l ) ] );
			// A run of text is measured by its line boxes to within 0.5px (Chrome draws its glyphs on a whole pixel).
			scenario( 'layout GAP: an item\'s own sentence over its form\'s field is measured by its line box, and touching (0 to 0.5px), fires', [ true ], spacing( 'text', 358 ).map( f => /^GAP line of text to field 0(\.5)?: "A sentence of the item\." over "Name"$/.test( f ) ) );
			scenario( 'layout EDGE: a legend keeping 2px of padding fires, 2px right of its first option; one with none does not', [ [ 'EDGE legend 2: "Level"' ], [] ], [ spacing( 'legend', 358 ), spacing( 'legend-flush', 358 ) ] );
			scenario( 'layout census: every case at every window has one, with its window', true, seeded.results.every( r => r.census && Array.isArray( r.census.gaps ) && r.win > 0 ) );
			scenario( 'layout censusRows(): a row per gap and edge off, and one count of the edges on, each with its case, surface and window', [ true, true, true ], ( () => { const rows = run.censusRows( seeded.results ); return [ rows.some( x => 'gap' === x.type && 'touch' === x.case && 'institution' === x.surface && 390 === x.win ), rows.some( x => 'edge' === x.type && 'legend' === x.k && 2 === x.at ), rows.some( x => 'zero edges' === x.type && x.count > 0 ) ]; } )() );
		}
	}

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
