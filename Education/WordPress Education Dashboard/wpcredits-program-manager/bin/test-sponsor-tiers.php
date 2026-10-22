<?php
/**
 * The sponsor tier ladder (1.112.0): one table, `WPCPM_Sponsor_Tiers::table()`, that every gate
 * is meant to read rather than deciding a benefit on its own.
 *
 * What is pinned here and why:
 *
 * - `table()` cell by cell, against the ladder written out in full below: the five tiers in
 *   `KEYS`, lowest first, each row's entry on the Partners page (a name, or a logo with the name
 *   and a description), its cap on posts (`null` reads as "any number"), its badge, its report
 *   sections and its events. No tier caps live offers, so the table holds no such cap at all
 *   since 1.123.18.
 *   `rank()`, `next()`, `labels()`, `label()` and the per-field accessors all read that one
 *   table, and an unknown tier reads as `MENTOR`, the ladder's floor, so every gate fails closed
 *   rather than open.
 * - `includes()` against the full `KEYS` x `BENEFITS` matrix: mentor has nothing, and each tier
 *   above it has exactly what the matrix below spells out, the logo and the description every
 *   tier's from Supporter up, since the Partners page lists each of them in one format. A benefit
 *   `includes()` does not recognize answers false from its own default branch, never a fatal.
 * - `effective()` is the one function every later gate is meant to call to find a sponsor's real
 *   tier: the recorded tier with no term or a term that has not ended, `MENTOR` the day after it
 *   ends (spec section 4), and a malformed date is no term at all rather than an end.
 * - `live()` and `grace_until()` read `WPCPM_Settings` through the two keys the ladder is gated
 *   by, `sponsor_tiers_live` and `sponsor_tier_grace_until`. Both are in
 *   `WPCPM_Settings::defaults()`, but an absent key must still read as the switch off and the
 *   default date, so the settings stand-in below holds neither until a check sets one.
 * - `days_left()` and `is_date()` are the two date primitives every later "days left" card and
 *   mail are meant to read; every function that reads "today" takes an optional `$today`, so
 *   this suite never depends on the clock it runs on, and `wp_date()` is stubbed to answer
 *   `$GLOBALS['today']` for the one check that leaves `$today` out.
 * - The house rules: the class file never compares a sponsor ID literal with `===`
 *   (`bin/test-sponsor-policy.php` scans every `includes/modules/class-wpcpm-sponsor*.php` for
 *   it, and its glob picks this file up), and it never reads the sponsors store or names
 *   Airtable - the policy is pure apart from the two settings reads and `wp_date()`.
 * - The daily run, `WPCPM_Sponsor_Tiers_Run`, in the second half: `plan_for()` is one sponsor's
 *   plan, pure and ungated, which the Sponsors screen's grace preview reads as well; the primary
 *   offer and then the oldest hold the slots, the oldest published posts hold theirs, and the
 *   dormant ones wake in the same order. `apply()` holds the gates (dormant only with the switch
 *   on and past the grace date, wake with the switch on, the thirty-day mail always), counts only
 *   the moves that took, writes one audit row when anything moved and stamps the term only once
 *   somebody was mailed; the mail says a term already over ended. `run()` walks the Approved
 *   sponsors alone and reads the site's day once, so a run that crosses midnight on the grace date
 *   applies that day's decision whole and the next run the next day's. Its collaborators are stood
 *   in, each for the calls the run makes.
 * - The daily draft (1.115.0): `apply()` asks `WPCPM_Sponsor_Report::maybe_draft()` after the
 *   mail, whatever the switch says, and only while the class is loaded. `WPCPM_Sponsor_Report`
 *   is stood in with a `maybe_draft()` that logs its own call and answers a scripted list of
 *   booleans, loaded only partway through the daily run section, so the checks above that point
 *   prove the "class not loaded" path for real rather than by simulation. The real class's own
 *   rules, a Friend and a term not yet ended among them, are pinned once, in
 *   `bin/test-sponsor-report.php`.
 * - The sponsor events' drain and reminders: `run()` asks `WPCPM_Sponsor_Event::resume_drain()`
 *   once, after every sponsor, and only while the class is loaded, so a drain whose event the clock
 *   lost waits a day at most; then, while Sponsor tiers is on and only then, `queue_reminders()`,
 *   last, handed the day the run read at its start, so a run that crosses the site's midnight
 *   reminds by the day it began on. The event store is stood in the way the report is, loaded last,
 *   so every run above it proves the run without it; its own rules are pinned in
 *   `bin/test-sponsor-events.php`.
 *
 * Run from the plugin root:  php bin/test-sponsor-tiers.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['settings'] = array();
$GLOBALS['today']    = '2026-10-06';
$GLOBALS['opts']     = array();

function __( $text, $domain = null ) {
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return $text;
}

function wp_date( $format, $timestamp = null ) {
	return 'Y-m-d' === $format ? $GLOBALS['today'] : gmdate( $format, null === $timestamp ? time() : $timestamp );
}

/** Stands in for the site's date_format option, which format_day() reads; unset means the F j, Y default. */
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}

/**
 * Stands in for the plugin's real settings store, whose defaults() holds the two keys this class
 * reads. A check that sets `$GLOBALS['day_turns_to']` has the site's day turn to it at the next read
 * of the launch switch: the daily run's first question in apply(), asked once plan_for() has read
 * the day, which is where a run started just before the site's midnight crosses it.
 */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		if ( 'sponsor_tiers_live' === $key && isset( $GLOBALS['day_turns_to'] ) ) {
			$GLOBALS['today'] = $GLOBALS['day_turns_to'];
			unset( $GLOBALS['day_turns_to'] );
		}

		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

$T = 'WPCPM_Sponsor_Tiers';

echo "=== The ladder and the table ===\n";

ck( 'the ladder is the five tiers, lowest first', $T::KEYS, array( 'mentor', 'supporter', 'backer', 'champion', 'partner' ) );

$expected_table = array(
	$T::MENTOR    => array(
		'label'   => 'Friend',
		'listing' => $T::LISTING_NAME,
		'posts'   => 0,
		'badge'   => false,
		'reports' => array(),
		'events'  => array(),
		'track'   => false,
	),
	$T::SUPPORTER => array(
		'label'   => 'Supporter',
		'listing' => $T::LISTING_LOGO,
		'posts'   => 0,
		'badge'   => true,
		'reports' => array( $T::REPORT_PROGRAM ),
		'events'  => array(),
		'track'   => false,
	),
	$T::BACKER    => array(
		'label'   => 'Backer',
		'listing' => $T::LISTING_LOGO,
		'posts'   => 1,
		'badge'   => true,
		'reports' => array( $T::REPORT_PROGRAM, $T::REPORT_COST_LINE ),
		'events'  => array(),
		'track'   => false,
	),
	$T::CHAMPION  => array(
		'label'   => 'Champion',
		'listing' => $T::LISTING_LOGO,
		'posts'   => 2,
		'badge'   => true,
		'reports' => array( $T::REPORT_PROGRAM, $T::REPORT_COST_LINE ),
		'events'  => array( $T::EVENT_PARTNERS_SESSION, $T::EVENT_CO_HOSTED ),
		'track'   => false,
	),
	$T::PARTNER   => array(
		'label'   => 'Partner',
		'listing' => $T::LISTING_LOGO,
		'posts'   => 2,
		'badge'   => true,
		'reports' => array( $T::REPORT_PROGRAM, $T::REPORT_COST_LINE, $T::REPORT_TRACK ),
		'events'  => array( $T::EVENT_PARTNERS_SESSION, $T::EVENT_CO_HOSTED, $T::EVENT_LAUNCH ),
		'track'   => true,
	),
);
ck( 'the table, cell by cell', $T::table(), $expected_table );

echo "\n=== Labels, caps, listing, rank, next ===\n";

ck( 'labels() and label(): the five names, and an unknown tier reads as a Friend',
	array(
		$T::labels(),
		$T::label( $T::MENTOR ),
		$T::label( $T::SUPPORTER ),
		$T::label( $T::BACKER ),
		$T::label( $T::CHAMPION ),
		$T::label( $T::PARTNER ),
		$T::label( 'gold' ),
	),
	array(
		array(
			$T::MENTOR    => 'Friend',
			$T::SUPPORTER => 'Supporter',
			$T::BACKER    => 'Backer',
			$T::CHAMPION  => 'Champion',
			$T::PARTNER   => 'Partner',
		),
		'Friend',
		'Supporter',
		'Backer',
		'Champion',
		'Partner',
		'Friend',
	)
);

// The lowest tier is named Friend since 1.123.13, on the owner's decision of 17 October 2026. Only
// the name a person reads changed: the stored key is still 'mentor' and the constant still MENTOR,
// so every row, option and event that holds the key reads as before, and the ladder still starts there.
ck( 'the lowest tier is named Friend, under the key and the constant it always had', array( $T::MENTOR, $T::KEYS[0], $T::label( 'mentor' ), $T::table()['mentor']['label'], $T::effective( array( 'tier' => '' ) ) ), array( 'mentor', 'mentor', 'Friend', 'Friend', 'mentor' ) );
$old_name = array();
foreach ( array( 'includes', 'blocks' ) as $shipped ) {
	$walk = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/' . $shipped, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $walk as $file ) {
		if ( preg_match( '/\.(php|js)$/', $file->getFilename() ) && preg_match( '/mentor sponsor/i', (string) file_get_contents( $file->getPathname() ) ) ) {
			$old_name[] = substr( $file->getPathname(), strlen( dirname( __DIR__ ) . '/' ) );
		}
	}
}
ck( 'and no shipped file names the tier the old way, in a string or in a comment', $old_name, array() );

$post_caps = array();
foreach ( $T::KEYS as $tier ) {
	$post_caps[] = $T::post_cap( $tier );
}
// The partner page (1.123.18): products are offered at any level, separately from the levels, so
// no tier caps live offers, and the ladder holds no cap on them to ask: offer_cap() is gone with the
// column it read. Posts follow the page: Backer's one self-written post, Champion's second, and
// Partner everything in Champion.
ck( 'post_cap(): 0, 0, 1, 2, 2, an unknown tier taking the floor; and no cap on live offers to ask, in the table or of the class',
	array( $post_caps, $T::post_cap( 'gold' ), method_exists( $T, 'offer_cap' ), array_values( array_filter( array_map( static function ( $row ) { return array_key_exists( 'offers', $row ); }, $T::table() ) ) ) ),
	array( array( 0, 0, 1, 2, 2 ), 0, false, array() )
);

$listings = array();
$ranks    = array();
$nexts    = array();
foreach ( $T::KEYS as $tier ) {
	$listings[] = $T::listing( $tier );
	$ranks[]    = $T::rank( $tier );
	$nexts[]    = $T::next( $tier );
}
ck( "listing(): a Friend by name, every tier above in one format, by logo; rank(): 0 to 4; next(): the key above and '' at the top and for an unknown tier",
	array( $listings, $ranks, $nexts, $T::next( 'gold' ) ),
	array(
		array( $T::LISTING_NAME, $T::LISTING_LOGO, $T::LISTING_LOGO, $T::LISTING_LOGO, $T::LISTING_LOGO ),
		array( 0, 1, 2, 3, 4 ),
		array( $T::SUPPORTER, $T::BACKER, $T::CHAMPION, $T::PARTNER, '' ),
		'',
	)
);

// The partner page lists every partner in the same format, in alphabetical order rather than by
// level: no prominence, no top placement and no line naming what a sponsor funds or which track it
// partners. The three listing kinds that ranked them, and the benefit that put a tier on top, are gone.
ck( 'the listing kinds are a name and a logo alone, and no benefit places a tier on top',
	array( defined( $T . '::LISTING_FUNDED' ), defined( $T . '::LISTING_TOP' ), defined( $T . '::LISTING_TRACK' ), in_array( 'top_placement', $T::BENEFITS, true ), $T::includes( $T::PARTNER, 'top_placement' ) ),
	array( false, false, false, false, false )
);

echo "\n=== includes(): the benefit matrix ===\n";

$matrix = array();
foreach ( $T::KEYS as $tier ) {
	$row = array();
	foreach ( $T::BENEFITS as $benefit ) {
		$row[ $benefit ] = $T::includes( $tier, $benefit );
	}
	$matrix[ $tier ] = $row;
}
$expected_matrix = array(
	$T::MENTOR    => array_fill_keys( $T::BENEFITS, false ),
	$T::SUPPORTER => array(
		'logo'                   => true,
		'description'            => true,
		'funded_line'            => false,
		'badge'                  => true,
		'events'                 => false,
		'launch_communications'  => false,
		'track'                  => false,
		'exclusive_offers'       => false,
		'report_program'         => true,
		'report_cost_line'       => false,
		'report_track'           => false,
	),
	$T::BACKER    => array(
		'logo'                   => true,
		'description'            => true,
		'funded_line'            => true,
		'badge'                  => true,
		'events'                 => false,
		'launch_communications'  => false,
		'track'                  => false,
		'exclusive_offers'       => false,
		'report_program'         => true,
		'report_cost_line'       => true,
		'report_track'           => false,
	),
	$T::CHAMPION  => array(
		'logo'                   => true,
		'description'            => true,
		'funded_line'            => true,
		'badge'                  => true,
		'events'                 => true,
		'launch_communications'  => false,
		'track'                  => false,
		'exclusive_offers'       => false,
		'report_program'         => true,
		'report_cost_line'       => true,
		'report_track'           => false,
	),
	// Every benefit: the Partners page prints a logo, the name and a description for every tier
	// from Supporter up, a Partner's among them.
	$T::PARTNER   => array_fill_keys( $T::BENEFITS, true ),
);
ck( 'includes(): every benefit for every tier', $matrix, $expected_matrix );

ck( 'an unknown benefit or an unknown tier is not included',
	array(
		$T::includes( $T::PARTNER, 'time_travel' ),
		$T::includes( 'gold', 'logo' ),
	),
	array( false, false )
);

echo "\n=== effective() and days_left() ===\n";

ck( 'effective(): the recorded tier with no term; empty and unknown tiers read as a Friend',
	array(
		$T::effective( array( 'tier' => $T::CHAMPION ) ),
		$T::effective( array( 'tier' => '' ) ),
		$T::effective( array( 'tier' => 'gold' ) ),
	),
	array( $T::CHAMPION, $T::MENTOR, $T::MENTOR )
);

ck( 'effective(): on the last day of the term still the tier, the day after a Friend',
	array(
		$T::effective( array( 'tier' => $T::BACKER, 'tier_until' => '2026-10-06' ), '2026-10-06' ),
		$T::effective( array( 'tier' => $T::BACKER, 'tier_until' => '2026-10-06' ), '2026-10-07' ),
	),
	array( $T::BACKER, $T::MENTOR )
);

ck( 'effective(): today comes from wp_date() when not given',
	$T::effective( array( 'tier' => $T::BACKER, 'tier_until' => '2026-10-05' ) ),
	$T::MENTOR
);

ck( 'effective(): a malformed date is no term',
	$T::effective( array( 'tier' => $T::CHAMPION, 'tier_until' => '2026-13-40' ) ),
	$T::CHAMPION
);

ck( 'days_left(): null with no term, 0 on the last day, -1 the day after, 30 thirty days before',
	array(
		$T::days_left( array( 'tier' => $T::BACKER ) ),
		$T::days_left( array( 'tier_until' => '2026-10-06' ), '2026-10-06' ),
		$T::days_left( array( 'tier_until' => '2026-10-06' ), '2026-10-07' ),
		$T::days_left( array( 'tier_until' => '2026-11-05' ), '2026-10-06' ),
	),
	array( null, 0, -1, 30 )
);

echo "\n=== live(), grace_until(), in_grace() ===\n";

ck( 'live() is off until the setting says on', $T::live(), false );
$GLOBALS['settings'][ $T::OPT_LIVE ] = 1;
ck( 'and on when it does', $T::live(), true );
unset( $GLOBALS['settings'][ $T::OPT_LIVE ] );

$grace_got = array();
$grace_got[] = $T::grace_until();
$GLOBALS['settings'][ $T::OPT_GRACE ] = '2026-12-3';
$grace_got[] = $T::grace_until();
$GLOBALS['settings'][ $T::OPT_GRACE ] = '2027-02-28';
$grace_got[] = $T::grace_until();
$grace_got[] = $T::in_grace( '2027-02-28' );
$grace_got[] = $T::in_grace( '2027-03-01' );
ck( "grace_until() defaults to 2026-12-31 and a malformed value reads as the default; in_grace() includes the grace date itself",
	$grace_got,
	array( '2026-12-31', '2026-12-31', '2027-02-28', true, false )
);
unset( $GLOBALS['settings'][ $T::OPT_GRACE ] );

echo "\n=== describe(), next_line(), event_label() ===\n";

// The partner page is the source of truth for the levels (the owner, 21 October 2026): each tier's
// lines are the page's bullets for its level, in its words but US English ("Acknowledgment"), led
// above Friend by its "Everything in <the level below>, plus:" line. No line offers products: the
// page offers them at any level, separately from the levels.
$described = array();
foreach ( array_merge( $T::KEYS, array( 'gold' ) ) as $tier ) {
	$described[ $tier ] = $T::describe( $tier );
}
ck( 'describe(): the partner page\'s bullets for each level, and above Friend the page\'s lead apart from them, what the level below has, so no lead is ever a checked item; an unknown tier reads as a Friend', $described, array(
	$T::MENTOR    => array(
		'lead'  => '',
		'items' => array(
			'Company name on the partner page',
			'Mention in the mentor feature',
			'Acknowledgment in the annual partner thank you post',
		),
	),
	$T::SUPPORTER => array(
		'lead'  => 'Everything in Friend, plus:',
		'items' => array(
			'Logo and description on the partner page',
			'A badge for your own site',
			'The annual program report',
		),
	),
	$T::BACKER    => array(
		'lead'  => 'Everything in Supporter, plus:',
		'items' => array(
			'Named for the part of the program you fund in program communications',
			'A report covering that cost line',
			'One self-written educational post about the sponsored product',
		),
	),
	$T::CHAMPION  => array(
		'lead'  => 'Everything in Backer, plus:',
		'items' => array(
			'A second self-written post with factual, value-neutral information about additional products',
			'An invitation to the annual education partners session',
			'A webinar or event co-hosted with the education program',
		),
	),
	$T::PARTNER   => array(
		'lead'  => 'Everything in Champion, plus:',
		'items' => array(
			'Named as program partner on the program you fund, with the exclusive role in that program',
			'Named in launch communications',
			'A report specific to that program',
		),
	),
	'gold'        => array(
		'lead'  => '',
		'items' => array(
			'Company name on the partner page',
			'Mention in the mentor feature',
			'Acknowledgment in the annual partner thank you post',
		),
	),
) );
$offer_lines = array();
foreach ( $T::KEYS as $tier ) {
	foreach ( array_merge( array( $T::describe( $tier )['lead'] ), $T::describe( $tier )['items'] ) as $line ) {
		if ( preg_match( '/\boffers?\b|\bproducts? exclusive\b|\bprominence\b|\btop placement\b|\blisting\b/i', $line ) ) {
			$offer_lines[] = $tier . ': ' . $line;
		}
	}
}
ck( 'and no tier\'s lines name an offer, a listing, prominence or top placement', $offer_lines, array() );

ck( 'next_line(): one sentence naming what the next level adds, from the same lists, and nothing for Partner or an unknown tier',
	array(
		$T::next_line( $T::MENTOR ),
		$T::next_line( $T::SUPPORTER ),
		$T::next_line( $T::BACKER ),
		$T::next_line( $T::CHAMPION ),
		$T::next_line( $T::PARTNER ),
		$T::next_line( 'gold' ),
	),
	array(
		'Supporter adds your logo and description on the partner page, a badge for your own site and the annual program report.',
		'Backer names your company for the part of the program you fund in program communications and adds a report covering that cost line and one self-written educational post about the sponsored product.',
		'Champion adds a second self-written post with factual, value-neutral information about additional products, an invitation to the annual education partners session and a webinar or event co-hosted with the education program.',
		'Partner adds a complete program funded end to end, with your company named as its program partner and in launch communications, the exclusive role in that program and a report specific to that program.',
		'',
		'',
	)
);

ck( "event_label(): a sentence per event and '' for an unknown one",
	array(
		$T::event_label( $T::EVENT_PARTNERS_SESSION ),
		$T::event_label( $T::EVENT_CO_HOSTED ),
		$T::event_label( $T::EVENT_LAUNCH ),
		$T::event_label( 'unknown_event' ),
	),
	array(
		'An invitation to the annual education partners session',
		'A webinar or event co-hosted with the education program',
		'Named in launch communications',
		'',
	)
);

echo "\n=== is_date() and the house rules ===\n";

ck( 'is_date(): a real date only',
	array(
		$T::is_date( '2026-02-29' ),
		$T::is_date( '2028-02-29' ),
		$T::is_date( '2026-10-6' ),
		$T::is_date( '' ),
	),
	array( false, true, false, false )
);

echo "\n=== format_day() ===\n";

// No date_format set: the F j, Y default. The daily run's mail, the Sponsors screen, the Your
// sponsorship card and the Administrator Dashboard print the same day through this one method,
// so a site that changes its date format changes every one of them at once.
$default_format                 = $T::format_day( '2026-10-06' );
$GLOBALS['opts']['date_format'] = 'Y/m/d';
$custom_format                  = $T::format_day( '2026-10-06' );
unset( $GLOBALS['opts']['date_format'] );

ck( 'format_day() writes a Y-m-d the way the site writes days',
	array( $default_format, $custom_format ),
	array( 'October 6, 2026', '2026/10/06' )
);

$class_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php' );
ck( 'the class reads the store nowhere and never names Airtable',
	array(
		false !== strpos( $class_src, 'WPCPM_Sponsors_Index' ),
		false !== strpos( $class_src, 'WPCPM_Airtable' ),
	),
	array( false, false )
);

echo "\n=== The daily run ===\n";

/*
 * WPCPM_Sponsor_Tiers_Run applies the ladder once a day. What it calls outside the
 * ladder is stood in below, each class for the calls the run makes and no others, over globals a
 * check seeds and reads back: the store's Approved rows and patch(), the offers and the posts
 * with their two program moves each (every move that took is written to $GLOBALS['moves'], in
 * the order it was asked for), the one road every mail to a sponsor's manager takes, the audit
 * log, and the Sponsors screen's one builder of the address of a sponsor's Edit form, which the
 * mail links, with the `admin_url()` that address is written with. The ladder is the real class
 * loaded above, so every cap, term and effective tier is the table's own. WPCPM_Sponsor_Report is
 * stood in too, further down, but declared only inside load_sponsor_report_stub() so it can stay
 * unloaded for the checks that need it that way.
 */
$GLOBALS['rows']      = array(); // Sponsor key => its row, as the store keeps it.
$GLOBALS['patched']   = array(); // Each patch() call: the key and the fields.
$GLOBALS['offers']    = array(); // Offer ID => its id, sponsor, state, primary and expires.
$GLOBALS['sposts']    = array(); // Post ID => the post: ID, sponsor, post_date, post_status, dormant.
$GLOBALS['moves']     = array(); // Each move an offer or a post took, in order.
$GLOBALS['sent']      = array(); // Each mail handed to mail_manager(): record, context, subject, body.
$GLOBALS['reachable'] = 1;       // What mail_manager() answers: how many were mailed.
$GLOBALS['audit']     = array(); // Each audit row, whole.
$GLOBALS['actions']   = array(); // Each add_action() call.
$GLOBALS['events']    = array(); // 'mail RECORD' and 'draft RECORD', in the order they happened.
$GLOBALS['draft_calls']   = array(); // Each maybe_draft() call: array( $record, $row, $today ).
$GLOBALS['draft_answers'] = array(); // The booleans maybe_draft() answers, oldest call first.

function add_action( $hook, $callback ) {
	$GLOBALS['actions'][] = array( $hook, $callback );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

/** A count's two forms, picked as core picks them for English. */
function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}

/** The store: the Approved rows, and patch() over them, every call recorded. */
class WPCPM_Sponsors_Index {
	public static function approved() {
		$out = array();

		foreach ( $GLOBALS['rows'] as $record => $row ) {
			if ( 'Approved' === $row['status'] ) {
				$out[ $record ] = $row;
			}
		}

		return $out;
	}

	public static function patch( $record, array $fields ) {
		$GLOBALS['patched'][] = array( $record, $fields );

		if ( ! isset( $GLOBALS['rows'][ $record ] ) ) {
			return false;
		}

		$GLOBALS['rows'][ $record ] = array_merge( $GLOBALS['rows'][ $record ], $fields );

		return true;
	}
}

/**
 * The Sponsors screen's one builder of the address of a sponsor's Edit form, which the mail links:
 * the Sponsors tab with the sponsor's key, written as the real `WPCPM_Sponsors::edit_url()` writes
 * it. bin/test-sponsors-screen.php pins the real one's address.
 */
class WPCPM_Sponsors {
	const ARG_EDIT = 'edit';

	public static function edit_url( $key ) {
		return admin_url( 'admin.php?page=wpcpm-sponsors&tab=sponsors&' . self::ARG_EDIT . '=' . rawurlencode( (string) $key ) );
	}
}

/**
 * The offers, oldest first as the real query reads them, the real is_live() rule and the program's
 * one move left on them, wake(): nothing puts an offer to dormant since no tier caps live offers,
 * and the real class has no such move since 1.123.23, so a run that called one would stop here.
 */
class WPCPM_Sponsor_Offers {
	const STATE_LIVE    = 'live';
	const STATE_DORMANT = 'dormant';

	public static function offers_of( $record ) {
		$out = array();

		foreach ( $GLOBALS['offers'] as $id => $offer ) {
			if ( $offer['sponsor'] === $record ) {
				$out[ $id ] = $offer;
			}
		}

		ksort( $out );

		return $out;
	}

	public static function is_live( array $offer, $today = '' ) {
		if ( self::STATE_LIVE !== $offer['state'] ) {
			return false;
		}

		if ( '' === (string) $offer['expires'] ) {
			return true;
		}

		return strcmp( (string) $offer['expires'], '' !== (string) $today ? (string) $today : wp_date( 'Y-m-d' ) ) >= 0;
	}

	public static function wake( $offer_id ) {
		if ( ! isset( $GLOBALS['offers'][ $offer_id ] ) || self::STATE_DORMANT !== $GLOBALS['offers'][ $offer_id ]['state'] ) {
			return false;
		}

		$GLOBALS['offers'][ $offer_id ]['state'] = self::STATE_LIVE;
		$GLOBALS['moves'][]                      = 'offer ' . $offer_id . ' woken';

		return true;
	}
}

/**
 * The posts, in the order they were seeded, which is never the order they went live in: the real
 * posts_of() reads the newest change first, so the plan has to put them in order itself.
 */
class WPCPM_Sponsor_Posts {
	public static function published_of( $record ) {
		$out = array();

		foreach ( $GLOBALS['sposts'] as $post ) {
			if ( $post->sponsor === $record && 'publish' === $post->post_status ) {
				$out[] = $post;
			}
		}

		return $out;
	}

	public static function dormant_of( $record ) {
		$out = array();

		foreach ( $GLOBALS['sposts'] as $post ) {
			if ( $post->sponsor === $record && 'pending' === $post->post_status && '' !== $post->dormant ) {
				$out[] = $post;
			}
		}

		return $out;
	}

	public static function set_dormant( $post_id, $today = '' ) {
		if ( ! isset( $GLOBALS['sposts'][ $post_id ] ) || 'publish' !== $GLOBALS['sposts'][ $post_id ]->post_status ) {
			return false;
		}

		$day = '' !== (string) $today ? (string) $today : wp_date( 'Y-m-d' );

		$GLOBALS['sposts'][ $post_id ]->post_status = 'pending';
		$GLOBALS['sposts'][ $post_id ]->dormant     = $day;
		$GLOBALS['moves'][]                         = 'post ' . $post_id . ' dormant on ' . $day;

		return true;
	}

	public static function wake( $post_id ) {
		if ( ! isset( $GLOBALS['sposts'][ $post_id ] ) || 'pending' !== $GLOBALS['sposts'][ $post_id ]->post_status || '' === $GLOBALS['sposts'][ $post_id ]->dormant ) {
			return false;
		}

		$GLOBALS['sposts'][ $post_id ]->post_status = 'publish';
		$GLOBALS['sposts'][ $post_id ]->dormant     = '';
		$GLOBALS['moves'][]                         = 'post ' . $post_id . ' woken';

		return true;
	}
}

/** The one road every mail to a sponsor's manager takes: the builder run, the mail kept, $GLOBALS['reachable'] answered. */
class WPCPM_Sponsor_Interests {
	public static function mail_manager( $record, $build, $context = '' ) {
		$mail = call_user_func( $build, null );

		wpcpm_record_mail( (string) $record, (string) $context, (string) $mail['subject'], (string) $mail['body'] );
		$GLOBALS['sent'][] = array(
			'record'  => $record,
			'context' => $context,
			'subject' => $mail['subject'],
			'body'    => $mail['body'],
		);
		$GLOBALS['events'][] = 'mail ' . $record;

		return (int) $GLOBALS['reachable'];
	}
}

/** The mail layer's one call the term's mail makes: the site's name, as the real layer hands it over, as stored. */
class WPCPM_Mail {
	public static function site_name() {
		return isset( $GLOBALS['site_name'] ) ? (string) $GLOBALS['site_name'] : 'WordPress Education';
	}
}

/** The audit log: every row kept whole. */
class WPCPM_Institution_Audit {
	const GROUND_SYSTEM  = 'system';
	const EVIDENCE_INDEX = 'index';

	public static function record_sponsor( array $entry ) {
		$GLOBALS['audit'][] = $entry;

		return count( $GLOBALS['audit'] );
	}
}

/**
 * WPCPM_Sponsor_Report is optional: apply() calls maybe_draft() only while the class is loaded,
 * behind class_exists(). The stub is declared inside this function rather than at the file's top
 * level, because PHP does not bind a class declared inside a function until that line actually
 * runs: WPCPM_Sponsor_Report stays undefined until this is called once, so every apply() and
 * run() check that runs before it is called below exercises the "class not loaded" path for
 * real, not as a simulation. $GLOBALS['draft_calls'] and $GLOBALS['draft_answers'] work the way
 * every other collaborator here does: a check seeds the answers and reads the calls back.
 * $GLOBALS['events'] is the one log maybe_draft() shares with mail_manager() above, which is how
 * the call order is pinned.
 */
function load_sponsor_report_stub() {
	if ( class_exists( 'WPCPM_Sponsor_Report' ) ) {
		return;
	}

	class WPCPM_Sponsor_Report {
		public static function maybe_draft( $record, array $row, $today = '' ) {
			$GLOBALS['draft_calls'][] = array( $record, $row, $today );
			$GLOBALS['events'][]      = 'draft ' . $record;

			return (bool) array_shift( $GLOBALS['draft_answers'] );
		}
	}
}

/**
 * WPCPM_Sponsor_Event is optional too: run() asks resume_drain() and queue_reminders() only while
 * the class is loaded. Declared inside this function for the report stub's reason, so every run()
 * check before the first call proves the run without the event store; its two methods log their
 * calls to $GLOBALS['events'], the log the mail and the draft share, which is how the order is
 * pinned, and queue_reminders() the day it is handed with it.
 */
function load_sponsor_event_stub() {
	if ( class_exists( 'WPCPM_Sponsor_Event' ) ) {
		return;
	}

	class WPCPM_Sponsor_Event {
		public static function resume_drain() {
			$GLOBALS['events'][] = 'resume drain';
		}

		public static function queue_reminders( $today = '' ) {
			$GLOBALS['events'][] = 'queue reminders ' . $today;

			return 0;
		}
	}
}

require_once __DIR__ . '/stubs/mail-recording.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers-run.php';
// Core's reader of the five entities (bin/stubs/specialchars.php), which the registry reads a value back by.
require_once __DIR__ . '/stubs/specialchars.php';
// The template registry, which every event and term email is filled from since 1.123.9.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';

$R = 'WPCPM_Sponsor_Tiers_Run';

/** Every store the run reads and writes emptied, the switch off and the grace date its default. */
function fresh_run() {
	$GLOBALS['rows']      = array();
	$GLOBALS['patched']   = array();
	$GLOBALS['offers']    = array();
	$GLOBALS['sposts']    = array();
	$GLOBALS['moves']     = array();
	$GLOBALS['sent']      = array();
	$GLOBALS['reachable'] = 1;
	$GLOBALS['audit']     = array();
	$GLOBALS['settings']  = array();
	$GLOBALS['events']    = array();
	$GLOBALS['draft_calls']   = array();
	$GLOBALS['draft_answers'] = array();
}

/**
 * A sponsor's row with every key the store writes on every row (its empty_row(), the six tier
 * keys of 1.112.0 included), Approved unless a check says otherwise, and the fields a check names
 * over the empty values.
 */
function sponsor_row( array $fields ) {
	return array_merge(
		array(
			'record_id'         => '',
			'slug'              => '',
			'name'              => '',
			'website'           => '',
			'status'            => 'Approved',
			'option'            => '',
			'support'           => array(),
			'product_type'      => '',
			'anything'          => '',
			'contact_person'    => '',
			'contact_email'     => '',
			'manager'           => '',
			'mentors'           => array(),
			'consent'           => false,
			'created'           => '',
			'agreement'         => array( 'status' => '', 'accepted_on' => '', 'has_document' => false ),
			'interests'         => '',
			'dashboard_account' => false,
			'notes'             => '',
			'tier'              => '',
			'tier_until'        => '',
			'funded_line'       => '',
			'description'       => '',
			'tier_requested'    => '',
			'tier_mailed_for'   => '',
		),
		$fields
	);
}

/** A sponsor in the store; answers its row, as approved() hands it to the run. */
function seed_sponsor( $record, array $fields ) {
	$GLOBALS['rows'][ $record ] = sponsor_row( array_merge( array( 'record_id' => $record ), $fields ) );

	return $GLOBALS['rows'][ $record ];
}

/** An offer of a sponsor's. */
function seed_offer( $id, $record, $state, $primary = false, $expires = '' ) {
	$GLOBALS['offers'][ $id ] = array(
		'id'      => $id,
		'sponsor' => $record,
		'state'   => $state,
		'primary' => $primary,
		'expires' => $expires,
	);
}

/** A post of a sponsor's, dated the day it went live. */
function seed_post( $id, $record, $day, $status = 'publish', $dormant = '' ) {
	$GLOBALS['sposts'][ $id ] = (object) array(
		'ID'          => $id,
		'sponsor'     => $record,
		'post_date'   => $day . ' 09:30:00',
		'post_status' => $status,
		'dormant'     => $dormant,
	);
}

$none = array( 'offers_woken' => 0, 'posts_dormant' => 0, 'posts_woken' => 0, 'mailed' => 0, 'drafted' => 0 );

// Since 1.123.18 no tier caps live offers: the partner page offers products at any level,
// separately from the levels, so the run puts no live offer to dormant and wakes every dormant
// one that would be live, an offer the daily run parked under an earlier cap among them.
fresh_run();
$key = 'spoAAAA0000000001';
$row = seed_sponsor( $key, array( 'name' => 'Supporter Example', 'tier' => 'supporter' ) );
seed_offer( 11, $key, 'live' );
seed_offer( 12, $key, 'live', true );
seed_offer( 13, $key, 'live' );
ck( 'plan_for(): no tier caps live offers, so a Supporter with three keeps every one',
	$R::plan_for( $key, $row, '2026-10-06' ),
	array( 'tier' => 'supporter', 'offers_wake' => array(), 'posts_dormant' => array(), 'posts_wake' => array(), 'mail' => false )
);

fresh_run();
$key  = 'spoBBBB0000000002';
$row  = seed_sponsor( $key, array( 'tier' => 'champion' ) );
seed_offer( 21, $key, 'dormant' );
seed_offer( 22, $key, 'dormant', true );
$plan = $R::plan_for( $key, $row, '2026-10-06' );
ck( 'plan_for(): a Champion with two dormant offers wakes both, the primary first, and the plan names no offer to put to dormant',
	array( $plan['offers_wake'], array_key_exists( 'offers_dormant', $plan ) ),
	array( array( 22, 21 ), false )
);

// The second Supporter's paused offer is the oldest of its three: the sponsor's own pause is never
// the program's to lift, so it stays paused while the two dormant ones wake.
fresh_run();
$one_each  = seed_sponsor( 'spoCCCC0000000003', array( 'tier' => 'supporter' ) );
seed_offer( 31, 'spoCCCC0000000003', 'live' );
seed_offer( 32, 'spoCCCC0000000003', 'dormant' );
$none_live = seed_sponsor( 'spoDDDD0000000004', array( 'tier' => 'supporter' ) );
seed_offer( 40, 'spoDDDD0000000004', 'paused' );
seed_offer( 41, 'spoDDDD0000000004', 'dormant' );
seed_offer( 42, 'spoDDDD0000000004', 'dormant' );
$a = $R::plan_for( 'spoCCCC0000000003', $one_each, '2026-10-06' );
$b = $R::plan_for( 'spoDDDD0000000004', $none_live, '2026-10-06' );
ck( 'plan_for(): every dormant offer wakes beside the live ones, and the sponsor\'s own pause is never lifted',
	array( $a['offers_wake'], $b['offers_wake'] ),
	array( array( 32 ), array( 41, 42 ) )
);

// The primary is the one past its last day: it is shown to nobody, and nothing of it moves.
fresh_run();
$key  = 'spoEEEE0000000005';
$row  = seed_sponsor( $key, array( 'tier' => 'supporter' ) );
seed_offer( 51, $key, 'live', true, '2026-10-05' );
seed_offer( 52, $key, 'live' );
seed_offer( 53, $key, 'live', false, '2026-12-31' );
$plan = $R::plan_for( $key, $row, '2026-10-06' );
ck( 'plan_for(): an offer past its last day is not woken, and no live offer is moved',
	array( $plan['offers_wake'], array_key_exists( 'offers_dormant', $plan ) ),
	array( array(), false )
);

// The dormant primary is past its last day: woken, it would be shown to nobody, so it stays down
// for a Supporter and a Champion alike while the others wake.
fresh_run();
$supporter = seed_sponsor( 'spoXXXX0000000022', array( 'tier' => 'supporter' ) );
seed_offer( 141, 'spoXXXX0000000022', 'dormant', true, '2026-10-01' );
seed_offer( 142, 'spoXXXX0000000022', 'dormant' );
seed_offer( 143, 'spoXXXX0000000022', 'dormant', false, '2026-12-31' );
$champion = seed_sponsor( 'spoYYYY0000000023', array( 'tier' => 'champion' ) );
seed_offer( 151, 'spoYYYY0000000023', 'dormant', true, '2026-10-05' );
seed_offer( 152, 'spoYYYY0000000023', 'dormant' );
ck( 'plan_for(): an expired dormant offer is never woken; the others are',
	array( $R::plan_for( 'spoXXXX0000000022', $supporter, '2026-10-06' )['offers_wake'], $R::plan_for( 'spoYYYY0000000023', $champion, '2026-10-06' )['offers_wake'] ),
	array( array( 142, 143 ), array( 152 ) )
);

// Seeded newest change first, as posts_of() reads them: 62 went live last, 61 first.
fresh_run();
$key    = 'spoFFFF0000000006';
$backer = seed_sponsor( $key, array( 'tier' => 'backer' ) );
seed_post( 62, $key, '2026-09-15' );
seed_post( 63, $key, '2026-09-10' );
seed_post( 61, $key, '2026-09-01' );
$parked = $R::plan_for( $key, $backer, '2026-10-06' )['posts_dormant'];
// The run parks those two, and a manager then makes the sponsor a Champion, then a Partner: each
// has room for two, the one still published and the older of the two parked.
foreach ( $parked as $post_id ) {
	$GLOBALS['sposts'][ $post_id ]->post_status = 'pending';
	$GLOBALS['sposts'][ $post_id ]->dormant     = '2027-01-01';
}
$champion = seed_sponsor( $key, array( 'tier' => 'champion' ) );
$back     = $R::plan_for( $key, $champion, '2027-01-02' );
$partner  = seed_sponsor( $key, array( 'tier' => 'partner' ) );
$back_p   = $R::plan_for( $key, $partner, '2027-01-02' );
ck( 'plan_for(): a Backer with three published posts puts the two newest to dormant, and a Champion or a Partner, with room for two, wakes the older of them',
	array( $parked, $back['posts_wake'], $back['posts_dormant'], $back_p['posts_wake'], $back_p['posts_dormant'] ),
	array( array( 63, 62 ), array( 63 ), array(), array( 63 ), array() )
);

// The page gives a Champion two self-written posts and a Partner everything in Champion: a third
// published post, which an unlimited cap allowed before 1.123.18, is the newest, and goes dormant.
fresh_run();
$key      = 'spoFFFF0000000041';
$champion = seed_sponsor( $key, array( 'tier' => 'champion' ) );
seed_post( 412, $key, '2026-09-15' );
seed_post( 413, $key, '2026-09-20' );
seed_post( 411, $key, '2026-09-01' );
$three_c  = $R::plan_for( $key, $champion, '2026-10-06' );
$partner  = seed_sponsor( $key, array( 'tier' => 'partner' ) );
$three_p  = $R::plan_for( $key, $partner, '2026-10-06' );
ck( 'plan_for(): a Champion or a Partner with three published posts keeps the two oldest and puts the newest to dormant',
	array( $three_c['posts_dormant'], $three_c['posts_wake'], $three_p['posts_dormant'], $three_p['posts_wake'] ),
	array( array( 413 ), array(), array( 413 ), array() )
);

// Two posts that went live in the same second, seeded higher ID first: the lower ID is the older
// post and keeps the slot, whatever order the query read them in.
fresh_run();
$key = 'spoIIII0000000025';
$row = seed_sponsor( $key, array( 'tier' => 'backer' ) );
seed_post( 172, $key, '2026-09-01' );
seed_post( 171, $key, '2026-09-01' );
seed_post( 173, $key, '2026-09-02' );
ck( 'plan_for(): posts that went live in the same second are ordered by ID, the older first',
	$R::plan_for( $key, $row, '2026-10-06' )['posts_dormant'],
	array( 172, 173 )
);

// No tier recorded: every sponsor reads as a Friend until a manager sets one.
fresh_run();
$key = 'spoGGGG0000000007';
$row = seed_sponsor( $key, array( 'name' => 'Mentor Example' ) );
seed_offer( 82, $key, 'live' );
seed_offer( 81, $key, 'live', true );
seed_offer( 83, $key, 'draft' );
seed_post( 85, $key, '2026-08-02' );
seed_post( 84, $key, '2026-08-01' );
seed_post( 86, $key, '2026-08-03', 'draft' );
ck( 'plan_for(): a Friend puts every published post to dormant and keeps its live offers live',
	$R::plan_for( $key, $row, '2026-10-06' ),
	array( 'tier' => 'mentor', 'offers_wake' => array(), 'posts_dormant' => array( 84, 85 ), 'posts_wake' => array(), 'mail' => false )
);

fresh_run();
$due = array();
foreach ( array(
	array( '2026-11-05', '' ),           // Thirty days before the last day.
	array( '2026-10-01', '' ),           // Five days after it, never mailed.
	array( '2026-10-16', '2026-10-16' ), // Ten days before it, mailed for this term.
	array( '', '' ),                     // No term at all.
	array( '2026-11-06', '' ),           // Thirty-one days before it.
	array( '2026-11-01', '2026-06-30' ), // Mailed for a term since moved to a new day: a new term.
) as $term ) {
	$due[] = $R::plan_for( 'spoHHHH0000000008', sponsor_row( array( 'tier' => 'backer', 'tier_until' => $term[0], 'tier_mailed_for' => $term[1] ) ), '2026-10-06' )['mail'];
}
ck( 'plan_for(): the mail is due thirty days before the term ends, still due after it, and not due once stamped for this term',
	$due,
	array( true, true, false, false, false, true )
);

// A Backer twenty days from the end of its term, on the day after the grace date: two live offers,
// which no tier caps, a dormant post its tier would take back, and the mail due.
fresh_run();
$key    = 'spoJJJJ0000000009';
$row    = seed_sponsor( $key, array( 'name' => 'Off Example', 'tier' => 'backer', 'tier_until' => '2027-01-21' ) );
seed_offer( 91, $key, 'live', true );
seed_offer( 92, $key, 'live' );
seed_post( 93, $key, '2026-11-02', 'pending', '2026-12-01' );
$plan   = $R::plan_for( $key, $row, '2027-01-01' );
$counts = $R::apply( $key, $row, $plan, '2027-01-01' );
ck( 'apply(): with the switch off nothing moves and the mail still goes',
	array( array( $plan['posts_wake'], $plan['mail'] ), $counts, $GLOBALS['moves'], $GLOBALS['audit'], count( $GLOBALS['sent'] ) ),
	array( array( array( 93 ), true ), array_merge( $none, array( 'mailed' => 1 ) ), array(), array(), 1 )
);

fresh_run();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => 1, 'sponsor_tier_grace_until' => '2026-12-31' );
$key    = 'spoKKKK0000000010';
$row    = seed_sponsor( $key, array( 'tier' => 'backer' ) );
seed_offer( 101, $key, 'live', true );
seed_offer( 102, $key, 'dormant' );
seed_post( 104, $key, '2026-09-01' );
seed_post( 105, $key, '2026-09-15' );
$plan   = $R::plan_for( $key, $row, '2026-10-06' );
$counts = $R::apply( $key, $row, $plan, '2026-10-06' );
ck( 'apply(): with the switch on and inside the grace date, wake happens and dormant waits',
	array( array( $plan['posts_dormant'], $plan['offers_wake'] ), $counts, $GLOBALS['moves'], $GLOBALS['sposts'][105]->post_status ),
	array( array( array( 105 ), array( 102 ) ), array_merge( $none, array( 'offers_woken' => 1 ) ), array( 'offer 102 woken' ), 'publish' )
);

// A Backer whose term ended on December 15, already mailed for it: a Friend on the first
// of January, the day after the default grace date. Its two published posts go dormant, and its
// two dormant offers wake, since no tier caps offers. The plan is a moment old when it is applied,
// and the sponsor ended one of those offers in between, so that move does not take and is not
// counted.
fresh_run();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => 1 );
$key    = 'spoLLLL0000000011';
$row    = seed_sponsor( $key, array( 'name' => 'Lapsed Example', 'tier' => 'backer', 'tier_until' => '2026-12-15', 'tier_mailed_for' => '2026-12-15' ) );
seed_offer( 121, $key, 'live', true );
seed_offer( 122, $key, 'dormant' );
seed_offer( 123, $key, 'dormant' );
seed_post( 125, $key, '2026-11-20' );
seed_post( 124, $key, '2026-11-01' );
$plan = $R::plan_for( $key, $row, '2027-01-01' );
$GLOBALS['offers'][123]['state'] = 'ended';
$counts = $R::apply( $key, $row, $plan, '2027-01-01' );
ck( 'apply(): past the grace date the dormant lists are applied, the counts are right, and one audit row names the tier and the counts',
	array( $plan['offers_wake'], $counts, $GLOBALS['moves'], $GLOBALS['audit'] ),
	array(
		array( 122, 123 ),
		array_merge( $none, array( 'offers_woken' => 1, 'posts_dormant' => 2 ) ),
		array( 'post 124 dormant on 2027-01-01', 'post 125 dormant on 2027-01-01', 'offer 122 woken' ),
		array(
			array(
				'kind'     => 'sponsor_tier_run',
				'sponsor'  => $key,
				'subject'  => $key,
				'actor'    => 0,
				'ground'   => 'system',
				'evidence' => 'index',
				'message'  => 'The daily run applied the Friend tier: 2 posts set dormant, 1 offer and 0 posts woken.',
				'data'     => array(
					'counts' => array( 'offers_woken' => 1, 'posts_dormant' => 2, 'posts_woken' => 0 ),
					'ids'    => array( 'offers_woken' => array( 122 ), 'posts_dormant' => array( 124, 125 ), 'posts_woken' => array() ),
				),
			),
		),
	)
);

// A Champion whose term ended five days ago: a Friend now, so the mail has to name the
// tier the term was, never the effective one. The name comes with the spaces a form left on it.
fresh_run();
$key    = 'spoMMMM0000000012';
$row    = seed_sponsor( $key, array( 'name' => ' Quill Example ', 'tier' => 'champion', 'tier_until' => '2026-10-01' ) );
$counts = $R::apply( $key, $row, $R::plan_for( $key, $row, '2026-10-06' ), '2026-10-06' );
$mail   = isset( $GLOBALS['sent'][0] ) ? $GLOBALS['sent'][0] : array( 'record' => '', 'context' => '', 'subject' => '', 'body' => '' );
ck( 'apply(): the mail names the sponsor, the recorded tier and the day, links the Sponsors screen\'s Edit form, is logged as sponsor-term-ended, its term being over, and stamps the term once it went out',
	array( $mail['context'], $mail['record'], $mail['subject'], $mail['body'], $GLOBALS['patched'], $counts['mailed'], $R::plan_for( $key, $GLOBALS['rows'][ $key ], '2026-10-07' )['mail'] ),
	array(
		'sponsor-term-ended',
		$key,
		'[WordPress Education] Quill Example: the Champion term ended on October 1, 2026',
		"Quill Example's Champion term ended on October 1, 2026. Renewal is moving the day on the Sponsors screen: https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors&edit=" . $key . "\n\nFrom the day after, the sponsor reads as a Friend, and once the grace date has passed, the daily run puts what a Friend's tier does not cover to dormant, while Sponsor tiers is on in Settings.",
		array( array( $key, array( 'tier_mailed_for' => '2026-10-01' ) ) ),
		1,
		false,
	)
);

fresh_run();
$GLOBALS['reachable'] = 0;
$key    = 'spoNNNN0000000013';
$row    = seed_sponsor( $key, array( 'tier' => 'supporter', 'tier_until' => '2026-10-20' ) );
$counts = $R::apply( $key, $row, $R::plan_for( $key, $row, '2026-10-06' ), '2026-10-06' );
ck( 'apply(): an unreachable manager leaves the stamp alone, so tomorrow tries again',
	array( count( $GLOBALS['sent'] ), $counts['mailed'], $GLOBALS['patched'], $GLOBALS['rows'][ $key ]['tier_mailed_for'], $R::plan_for( $key, $GLOBALS['rows'][ $key ], '2026-10-07' )['mail'] ),
	array( 1, 0, array(), '', true )
);

// A run that was off for a while can find a term already over and not yet mailed: the mail says
// that term ended, and a term still running keeps saying when it ends.
fresh_run();
$ended   = seed_sponsor( 'spoXXXX0000000026', array( 'name' => 'Ended Example', 'tier' => 'backer', 'tier_until' => '2026-10-01' ) );
$running = seed_sponsor( 'spoYYYY0000000027', array( 'name' => 'Running Example', 'tier' => 'supporter', 'tier_until' => '2026-10-16' ) );
foreach ( array( 'spoXXXX0000000026' => $ended, 'spoYYYY0000000027' => $running ) as $record => $sponsor ) {
	$R::apply( $record, $sponsor, $R::plan_for( $record, $sponsor, '2026-10-06' ), '2026-10-06' );
}
// The mail goes out whether or not the tiers are switched on, and the daily run puts nothing to
// dormant while they are off, so both bodies say the dormant step needs the switch.
ck( 'the mail says "ended on" for a term already over, and "ends on" for one still running, and each says the dormant step waits for the switch',
	array( WPCPM_Sponsor_Tiers::days_left( $ended, '2026-10-06' ), WPCPM_Sponsor_Tiers::days_left( $running, '2026-10-06' ), array_column( $GLOBALS['sent'], 'subject' ), array_map( static function ( $body ) { return substr( (string) $body, (int) strpos( (string) $body, "\n\n" ) + 2 ); }, array_column( $GLOBALS['sent'], 'body' ) ) ),
	array(
		-5,
		10,
		array(
			'[WordPress Education] Ended Example: the Backer term ended on October 1, 2026',
			'[WordPress Education] Running Example: the Supporter term ends on October 16, 2026',
		),
		array_fill( 0, 2, 'From the day after, the sponsor reads as a Friend, and once the grace date has passed, the daily run puts what a Friend\'s tier does not cover to dormant, while Sponsor tiers is on in Settings.' ),
	)
);

// Four sponsors on January 2, the switch on: a Supporter with two dormant offers, a Backer with a
// post beyond its cap and a dormant offer, a Paused Supporter with a dormant offer, and a Champion
// with a dormant offer, a dormant post and its term ending in nine days.
fresh_run();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => 1 );
seed_sponsor( 'spoPPPP0000000014', array( 'tier' => 'supporter' ) );
seed_offer( 201, 'spoPPPP0000000014', 'live', true );
seed_offer( 202, 'spoPPPP0000000014', 'dormant' );
seed_offer( 203, 'spoPPPP0000000014', 'dormant' );
seed_sponsor( 'spoQQQQ0000000015', array( 'tier' => 'backer' ) );
seed_offer( 211, 'spoQQQQ0000000015', 'live' );
seed_offer( 212, 'spoQQQQ0000000015', 'dormant' );
seed_post( 213, 'spoQQQQ0000000015', '2026-12-01' );
seed_post( 214, 'spoQQQQ0000000015', '2026-12-02' );
seed_sponsor( 'spoRRRR0000000016', array( 'status' => 'Paused', 'tier' => 'supporter' ) );
seed_offer( 221, 'spoRRRR0000000016', 'live' );
seed_offer( 222, 'spoRRRR0000000016', 'dormant' );
seed_sponsor( 'spoSSSS0000000017', array( 'tier' => 'champion', 'tier_until' => '2027-01-11' ) );
seed_offer( 231, 'spoSSSS0000000017', 'dormant' );
seed_post( 232, 'spoSSSS0000000017', '2026-11-01', 'pending', '2027-01-01' );
$totals = $R::run( '2027-01-02' );
ck( 'run() walks the Approved sponsors alone and sums the counts',
	array( $totals, array( $GLOBALS['offers'][221]['state'], $GLOBALS['offers'][222]['state'] ), array_column( $GLOBALS['audit'], 'sponsor' ), array_column( $GLOBALS['sent'], 'record' ) ),
	array(
		array( 'offers_woken' => 4, 'posts_dormant' => 1, 'posts_woken' => 1, 'mailed' => 1, 'drafted' => 0 ),
		array( 'live', 'dormant' ),
		array( 'spoPPPP0000000014', 'spoQQQQ0000000015', 'spoSSSS0000000017' ),
		array( 'spoSSSS0000000017' ),
	)
);
ck( 'the audit sentence counts in words: one post set dormant and one offer woken read singular',
	array_column( $GLOBALS['audit'], 'message', 'sponsor' )['spoQQQQ0000000015'],
	'The daily run applied the Backer tier: 1 post set dormant, 1 offer and 0 posts woken.'
);

echo "\n=== The daily run drafts a sponsor's report (1.115.0) ===\n";

// WPCPM_Sponsor_Report has not been loaded anywhere above this point, so every apply() and run()
// check already run above proved the "class not loaded" path on its own: $none reads
// 'drafted' => 0 throughout, and none of them raised anything. This is that same path pinned on
// its own terms, with the warning capture bin/test-sponsor-offers.php uses for the same claim:
// an absent class is not merely uncounted, it is silent.
fresh_run();
$key            = 'spoOOOO0000000028';
$row            = seed_sponsor( $key, array( 'tier' => 'backer', 'tier_until' => '2026-10-01' ) );
$class_warnings = array();
set_error_handler(
	static function ( $errno, $errstr ) use ( &$class_warnings ) {
		$class_warnings[] = $errstr;
		return true;
	}
);
$counts = $R::apply( $key, $row, $R::plan_for( $key, $row, '2026-10-06' ), '2026-10-06' );
restore_error_handler();
ck( 'apply(): with WPCPM_Sponsor_Report not loaded, nothing is drafted and nothing warns',
	array( $counts['drafted'], $class_warnings ),
	array( 0, array() )
);

load_sponsor_report_stub();

// A Backer whose term ended five days ago, the switch left off by fresh_run(): the draft is a
// fact of the record, not a benefit the switch gates, so it is still asked for. The mail is due
// too, which is what proves the order: both stubs append to $GLOBALS['events'], so the mail
// entry landing first is the call order apply() actually made, not a coincidence of two separate
// logs read side by side.
fresh_run();
$key = 'spoOOOO0000000029';
$row = seed_sponsor( $key, array( 'name' => 'Draft Example', 'tier' => 'backer', 'tier_until' => '2026-10-01' ) );
$GLOBALS['draft_answers'] = array( true );
$counts                   = $R::apply( $key, $row, $R::plan_for( $key, $row, '2026-10-06' ), '2026-10-06' );
ck( 'apply(): maybe_draft() is called once, with the record, the row and today exactly',
	$GLOBALS['draft_calls'],
	array( array( $key, $row, '2026-10-06' ) )
);
ck( 'apply(): the draft is asked after the mail, both logged in the order they happened',
	$GLOBALS['events'],
	array( 'mail ' . $key, 'draft ' . $key )
);
ck( 'apply(): drafted is 1 when maybe_draft() answers true, and the run\'s own audit row still names only what moved, not the draft',
	array( $counts['drafted'], $GLOBALS['audit'] ),
	array( 1, array() )
);

fresh_run();
$key = 'spoOOOO0000000030';
$row = seed_sponsor( $key, array( 'tier' => 'backer', 'tier_until' => '2026-10-01' ) );
$GLOBALS['draft_answers'] = array( false );
$counts                   = $R::apply( $key, $row, $R::plan_for( $key, $row, '2026-10-06' ), '2026-10-06' );
ck( 'apply(): drafted is 0 when maybe_draft() answers false',
	$counts['drafted'],
	0
);

// Two sponsors, one drafted and one not: run() has to sum the per-sponsor counts rather than
// carry a single flag, and each sponsor gets its own call.
fresh_run();
$key1 = 'spoOOOO0000000031';
$key2 = 'spoOOOO0000000032';
seed_sponsor( $key1, array( 'tier' => 'backer', 'tier_until' => '2026-10-01' ) );
seed_sponsor( $key2, array( 'tier' => 'champion', 'tier_until' => '2026-09-28' ) );
$GLOBALS['draft_answers'] = array( true, false );
$totals                   = $R::run( '2026-10-06' );
ck( 'run() sums drafted over its sponsors, one call to maybe_draft() each',
	array( $totals['drafted'], count( $GLOBALS['draft_calls'] ) ),
	array( 1, 2 )
);

echo "\n=== The daily run puts the sponsor events' drain back ===\n";

// The event store is loaded from here on: a Backer whose term ended five days ago is mailed and its
// report asked for, and then, once, after it, the store is asked to put a lost drain back.
load_sponsor_event_stub();
fresh_run();
$key = 'spoOOOO0000000033';
seed_sponsor( $key, array( 'tier' => 'backer', 'tier_until' => '2026-10-01' ) );
$R::run( '2026-10-06' );
ck( 'run() asks the event store to put a lost drain back, once, after every sponsor',
	$GLOBALS['events'],
	array( 'mail ' . $key, 'draft ' . $key, 'resume drain' )
);
fresh_run();
$R::run( '2026-10-06' );
ck( 'and asks it on a day with no sponsor to walk', $GLOBALS['events'], array( 'resume drain' ) );

// With Sponsor tiers on, the day before's reminders come last, once the drain is put back.
fresh_run();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$key                 = 'spoOOOO0000000034';
seed_sponsor( $key, array( 'tier' => 'backer', 'tier_until' => '2026-10-01' ) );
$R::run( '2026-10-06' );
ck( 'with Sponsor tiers on, run() then asks the event store to queue the day before\'s reminders, once, last, handed the run\'s day',
	$GLOBALS['events'],
	array( 'mail ' . $key, 'draft ' . $key, 'resume drain', 'queue reminders 2026-10-06' )
);
fresh_run();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => false );
$R::run( '2026-10-06' );
ck( 'and asks nothing of the reminders while Sponsor tiers is off: they are mail about a benefit the switch gates', $GLOBALS['events'], array( 'resume drain' ) );

// A run with no day given, started just before the site's midnight on 31 December: the day turns at
// the run's first read of the switch, and the reminders are handed the day the run began on.
fresh_run();
$GLOBALS['settings']     = array( 'sponsor_tiers_live' => true );
$key                     = 'spoOOOO0000000035';
seed_sponsor( $key, array( 'tier' => 'champion', 'tier_until' => '2027-06-30' ) );
$GLOBALS['today']        = '2026-12-31';
$GLOBALS['day_turns_to'] = '2027-01-01';
$R::run();
ck( 'a run with no day given that crosses the site\'s midnight hands the reminders the day it read at its start, as it hands every question it asks',
	array( isset( $GLOBALS['day_turns_to'] ), $GLOBALS['today'], $GLOBALS['events'] ),
	array( false, '2027-01-01', array( 'draft ' . $key, 'resume drain', 'queue reminders 2026-12-31' ) )
);
$GLOBALS['today'] = '2026-10-06';

// A run started just before the site's midnight on the grace date, 31 December, for a Champion
// whose term ends that day with two published posts: the day turns while the run works through it.
// The run reads the day once, so it applies the one day's decision whole: on the grace date nothing
// goes dormant, and the run the next day, when the sponsor is a Friend, puts both to dormant
// and says so. Reading the day again at the gates would put one post to dormant under a row naming
// the Champion tier, on a day the sponsor no longer holds it.
fresh_run();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$midnight            = 'spoMMMM0000000040';
seed_sponsor( $midnight, array( 'name' => 'Midnight Example', 'tier' => 'champion', 'tier_until' => '2026-12-31', 'tier_mailed_for' => '2026-12-31' ) );
seed_post( 401, $midnight, '2026-11-01' );
seed_post( 402, $midnight, '2026-11-02' );
$GLOBALS['today']        = '2026-12-31';
$GLOBALS['day_turns_to'] = '2027-01-01';
$straddled               = $R::run();
ck( 'run() with no day given reads the site\'s day once: a run that crosses midnight on the grace date puts nothing to dormant and writes no row',
	array( isset( $GLOBALS['day_turns_to'] ), $GLOBALS['today'], $straddled['posts_dormant'], array( $GLOBALS['sposts'][401]->post_status, $GLOBALS['sposts'][402]->post_status ), $GLOBALS['audit'] ),
	array( false, '2027-01-01', 0, array( 'publish', 'publish' ), array() )
);

$next_day = $R::run();
ck( 'and the next run, on 1 January, puts both to dormant under a row that names the tier the sponsor holds that day',
	array( $next_day['posts_dormant'], array( $GLOBALS['sposts'][401]->post_status, $GLOBALS['sposts'][402]->post_status ), count( $GLOBALS['audit'] ), isset( $GLOBALS['audit'][0]['message'] ) && false !== strpos( $GLOBALS['audit'][0]['message'], 'the Friend tier' ) ),
	array( 2, array( 'pending', 'pending' ), 1, true )
);
$GLOBALS['today'] = '2026-10-06';

// A grace date at the end of February, so the day after it rolls the month over. The second
// sponsor has neither a name nor a tier: its key stands in for the name, and it reads as a Friend.
// The Champion has nothing beyond its cap of two posts, no offer goes dormant under any tier, and
// the Paused sponsor is not the run's.
fresh_run();
$GLOBALS['settings'] = array( 'sponsor_tier_grace_until' => '2027-02-28' );
seed_sponsor( 'spoTTTT0000000018', array( 'name' => ' Mango Example ', 'tier' => 'backer', 'tier_until' => '2027-03-10' ) );
seed_offer( 301, 'spoTTTT0000000018', 'live', true );
seed_offer( 302, 'spoTTTT0000000018', 'live' );
seed_post( 303, 'spoTTTT0000000018', '2026-09-01' );
seed_post( 304, 'spoTTTT0000000018', '2026-09-02' );
seed_sponsor( 'spoUUUU0000000019', array() );
seed_offer( 311, 'spoUUUU0000000019', 'live' );
seed_post( 312, 'spoUUUU0000000019', '2026-09-01' );
seed_sponsor( 'spoVVVV0000000020', array( 'name' => 'Champion Example', 'tier' => 'champion' ) );
seed_offer( 321, 'spoVVVV0000000020', 'live' );
seed_offer( 322, 'spoVVVV0000000020', 'live' );
seed_post( 323, 'spoVVVV0000000020', '2026-09-01' );
seed_post( 324, 'spoVVVV0000000020', '2026-09-02' );
seed_sponsor( 'spoWWWW0000000021', array( 'name' => 'Paused Example', 'status' => 'Paused', 'tier' => 'supporter' ) );
seed_offer( 331, 'spoWWWW0000000021', 'live' );
seed_post( 332, 'spoWWWW0000000021', '2026-09-01' );
$off = $R::preview();
$GLOBALS['settings']['sponsor_tiers_live'] = 1;
$on = $R::preview();
ck( 'preview() lists each sponsor with something to go dormant, the totals and the day after the grace date, and says whether the switch is on',
	array( $off, $on['live'] ),
	array(
		array(
			'day'      => '2027-03-01',
			'live'     => false,
			'sponsors' => array(
				'spoTTTT0000000018' => array( 'name' => 'Mango Example', 'tier' => 'Backer', 'posts' => 1 ),
				'spoUUUU0000000019' => array( 'name' => 'spoUUUU0000000019', 'tier' => 'Friend', 'posts' => 1 ),
			),
			'posts'    => 2,
		),
		true,
	)
);

// Asked again with the switch on: the plan is for March 1, the day the run itself would move all
// of that and mail the Backer, nine days before its term ends.
$late = $R::preview();
ck( 'preview() moves nothing, mails nobody and writes nothing, even past the grace date with the switch on',
	array( $late['posts'], $GLOBALS['moves'], $GLOBALS['sent'], $GLOBALS['audit'], $GLOBALS['patched'] ),
	array( 2, array(), array(), array(), array() )
);

// A Champion whose term ends on December 15, before the default grace date: on the day after
// that date, January 1, it reads as a Friend, so both its published posts go dormant that day,
// and its two live offers stay live.
fresh_run();
seed_sponsor( 'spoZZZZ0000000024', array( 'name' => 'Ending Example', 'tier' => 'champion', 'tier_until' => '2026-12-15' ) );
seed_offer( 161, 'spoZZZZ0000000024', 'live', true );
seed_offer( 162, 'spoZZZZ0000000024', 'live' );
seed_post( 163, 'spoZZZZ0000000024', '2026-11-01' );
seed_post( 164, 'spoZZZZ0000000024', '2026-11-02' );
$ending = $R::preview();
ck( 'preview() plans for the day after the grace date: a term that ends before it counts as a Friend',
	array( $ending['day'], $ending['sponsors'], $ending['posts'], array_key_exists( 'offers', $ending ) ),
	array( '2027-01-01', array( 'spoZZZZ0000000024' => array( 'name' => 'Ending Example', 'tier' => 'Friend', 'posts' => 2 ) ), 2, false )
);

$GLOBALS['actions'] = array();
$R::init();
ck( 'init() hooks run() to CRON_HOOK, the daily event the Sponsors module schedules',
	$GLOBALS['actions'],
	array( array( 'wpcpm_sponsor_tiers_daily', array( 'WPCPM_Sponsor_Tiers_Run', 'run' ) ) )
);

// What every mail of the suite says, as the recording made before the event and term emails moved
// into the template registry holds it (bin/stubs/mail-recording.php).
// The term's mail names the site at the head of its subject, as every Sponsors email does, where the
// run wrote "[WordPress Credits]": each of the thirteen mails recorded. And the lowest tier is named
// Friend since 1.123.13, where the recording says "a Mentor sponsor", twice in every body: the
// stored key is still 'mentor', so only the words change.
$named_site = array();
foreach ( range( 0, 12 ) as $n ) {
	$named_site[ $n ] = array(
		'subject' => array( '[WordPress Credits] ' => '[WordPress Education] ' ),
		'body'    => array( 'a Mentor sponsor' => 'a Friend' ),
	);
}
foreach ( wpcpm_recording_checks( 'sponsor-term-mails-run.json', '52f2c8dfa1ece10749607227760fa6a4bc841f760fa7920a2735715f6dfcc1d1', array( 'sponsor-term-ending', 'sponsor-term-ended' ), $named_site, '' ) as $recorded_check ) {
	ck( $recorded_check[0], $recorded_check[1], $recorded_check[2] );
}

// Since the term's mail is the registry's, a name as the Sponsors screen's cleaner stores it, its "<"
// as an entity, reads as it was typed, as every value of every template does
// (`WPCPM_Mail_Templates::fill()`): before, the subject and the body carried "&lt;". After the
// recording's checks, so the recording keeps the mails it was made with.
fresh_run();
$key = 'spoAMPS0000000099';
$row = seed_sponsor( $key, array( 'name' => 'Ada & Bo &lt;3 Hosting', 'tier' => 'backer', 'tier_until' => '2026-11-05' ) );
$R::apply( $key, $row, $R::plan_for( $key, $row, '2026-10-06' ), '2026-10-06' );
$read = isset( $GLOBALS['sent'][0] ) ? $GLOBALS['sent'][0] : array( 'subject' => '', 'body' => '' );
ck( 'a sponsor\'s name stored with the cleaner\'s "&lt;" reaches the term\'s mail as typed, in its subject and its body',
	array( $read['subject'], strtok( $read['body'], "\n" ) ),
	array( '[WordPress Education] Ada & Bo <3 Hosting: the Backer term ends on November 5, 2026', 'Ada & Bo <3 Hosting\'s Backer term ends on November 5, 2026. Renewal is moving the day on the Sponsors screen: ' . WPCPM_Sponsors::edit_url( $key ) )
);

// The site's name as WordPress stores it, its "&" an entity, is handed over as stored and read back once
// by the registry: both term mails name the site as a person typed it, and neither shows "&amp;" in its
// subject or its body.
$GLOBALS['site_name'] = 'Arts &amp; Sciences Dashboard';
fresh_run();
$amp_mails = array();
foreach ( array( 'spoAMPS0000000098' => '2026-10-01', 'spoAMPS0000000097' => '2026-11-05' ) as $key => $until ) {
	$row = seed_sponsor( $key, array( 'name' => 'Example Hosting', 'tier' => 'backer', 'tier_until' => $until ) );
	$R::apply( $key, $row, $R::plan_for( $key, $row, '2026-10-06' ), '2026-10-06' );
}
foreach ( $GLOBALS['sent'] as $mail ) {
	$amp_mails[ $mail['context'] ] = array( 0 === strpos( $mail['subject'], '[Arts & Sciences Dashboard] ' ), false === strpos( $mail['subject'] . $mail['body'], '&amp;' ) );
}
unset( $GLOBALS['site_name'] );
ck( 'a site name holding an "&", stored as an entity, reads as typed in the subject of both term mails, and "&amp;" is in neither their subjects nor their bodies',
	$amp_mails,
	array( 'sponsor-term-ended' => array( true, true ), 'sponsor-term-ending' => array( true, true ) )
);

printf( "\n%s (%d checks)\n", $fails ? "$fails FAILED" : 'ALL PASS', (int) $total );
exit( $fails ? 1 : 0 );
