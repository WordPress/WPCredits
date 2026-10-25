<?php
/**
 * WPCPM_Track_Partners: the sponsors a track names as its partners, resolved at every read from the
 * compiled track, the sponsors store and the tier ladder.
 *
 * What is pinned here and why:
 *
 * - eligible() is every Approved sponsor whose effective tier is Partner, by name whatever order the
 *   store holds them in, case aside. qualifies() says no to a Champion, to a Paused Partner and to a
 *   Partner whose term ended, and yes on a term's last day: the day it judges is the one it is
 *   handed, so a lapsed partner stops showing without anything written to the track.
 * - choices() is what the Track Builder's picker offers: the eligible sponsors, then every key the
 *   track already names that is not among them, noted as no longer a Partner or as an unknown
 *   sponsor, so a save can never drop one silently.
 * - keys_of() and for_track() read the compiled track the live site reads, never a track post.
 *   for_track() answers the partners that qualify today, in the track's own order, with their name,
 *   website and logo.
 * - render_line() is the Student Report Card's partnership line: empty while the tiers' switch is
 *   off, empty for a track with no partner that qualifies, and otherwise a lead and one item a
 *   partner, the logo only when the site holds one, the link only when there is a website, every
 *   address escaped, and nothing of a sponsor but its name, website and logo. A name, a website and
 *   a logo's address holding both quotes, an ampersand and markup leave it escaped for their places,
 *   the name as text and the addresses as attributes. Handed a list of keys, as the Track Builder's
 *   preview hands a draft's, it draws those in place of the live track's.
 * - The plugin and its uninstall load the class right after the tracks' reader.
 *
 * The sponsors store, the tier ladder and the tracks' reader are the real classes, the store over
 * bin/stubs/posts.php; WordPress is stood in for the few calls they make.
 *
 * Run from the plugin root:  php bin/test-track-partners.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['opts']      = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['settings']  = array();
$GLOBALS['image_src'] = array();
$GLOBALS['today']     = '2026-10-06';

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

/** Encodes as core's does where it matters here, so an address printed without it shows. */
function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ] = $value;
	return true;
}

/** 'Y-m-d' answers the pinned $GLOBALS['today']; any other format falls back to gmdate(). */
function wp_date( $format, $timestamp = null ) {
	return 'Y-m-d' === $format ? $GLOBALS['today'] : gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
}

/** WPCPM_Sponsors_Index::display_logo()'s one external call, over the attachments this suite holds. */
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	return isset( $GLOBALS['image_src'][ $id ][0] ) ? $GLOBALS['image_src'][ $id ][0] : false;
}

/** Stands in for the plugin's real settings store, read through the one key the tier ladder reads. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
// For its constants alone: the tracks' reader locks the four original tracks by the program map's
// status constants (`WPCPM_Tracks::RESERVED_PAIRS`), which PHP resolves on the reader's first
// static call.
require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-partners.php';

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

/**
 * One sponsor, seeded through insert() as production code would, on empty_row()'s shape.
 *
 * @param string $key    The sponsor's key.
 * @param string $name   The sponsor's name.
 * @param string $status 'Approved' or 'Paused'.
 * @param array  $fields Any other empty_row() keys.
 */
function add_sponsor( $key, $name, $status, array $fields = array() ) {
	WPCPM_Sponsors_Index::insert(
		array_merge(
			WPCPM_Sponsors_Index::empty_row(),
			array(
				'record_id' => $key,
				'name'      => $name,
				'status'    => $status,
			),
			$fields
		)
	);
}

/**
 * Put the compiled tracks in place, as `WPCPM_Track_Store::compile()` leaves them, beside the
 * sponsors' logo options this suite keeps in the same table.
 *
 * @param array $rows Status => compiled row.
 */
function compiled( array $rows ) {
	$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = $rows;
	WPCPM_Tracks::flush();
}

/**
 * One compiled row, in `WPCPM_Track_Definition::row()`'s shape (as bin/test-tracks.php builds it).
 *
 * @param string $key       The track's key.
 * @param string $label     Its name.
 * @param array  $overrides Anything else.
 * @return array
 */
function row( $key, $label, array $overrides = array() ) {
	return array_merge(
		array( 'key' => $key, 'label' => $label, 'course_url' => '', 'course_id' => 0, 'hours' => null, 'hue' => 'cyan', 'sponsors' => array(), 'automation' => false, 'post' => 1 ),
		$overrides
	);
}

$acme     = 'spoCCCC0000000001';
$aardvark = 'spoCCCC0000000002';
$cedar    = 'spoCCCC0000000003';
$delta    = 'spoCCCC0000000004';
$lapsed   = 'spoCCCC0000000005';
$unknown  = 'spoZZZZ0000000009';

echo "=== Seed ===\n";

// Acme comes first in the store and second by name, so a list in the store's order and one by name
// can be told apart. It also carries a manager's private fields, so the line can be seen to leave
// them out, and a website with a query string, so the link can be seen escaped.
add_sponsor( $acme, 'Acme Tools', 'Approved', array(
	'tier'           => 'partner',
	'website'        => 'https://acme.example/?ref=wpcredits&x=1',
	'contact_person' => 'Should Never Appear',
	'contact_email'  => 'administrator+partners@example.test',
	'notes'          => 'Manager-only notes',
) );
WPCPM_Sponsors_Index::write_logo_record( $acme, array( 'colour' => 21, 'white' => 0, 'source' => 'site', 'airtable_id' => '' ) );

// A Partner with no logo, a website without its scheme, and a name with markup in it.
add_sponsor( $aardvark, 'Aardvark & Sons', 'Approved', array( 'tier' => 'partner', 'website' => 'aardvark.example' ) );

add_sponsor( $cedar, 'Cedar Hosting', 'Approved', array( 'tier' => 'champion', 'website' => 'https://cedar.example/' ) );
add_sponsor( $delta, 'Delta Services', 'Paused', array( 'tier' => 'partner', 'website' => 'https://delta.example/' ) );

// A Partner whose term ended before the pinned today, with a logo, so only its term keeps it out.
add_sponsor( $lapsed, 'Lapsed Co', 'Approved', array( 'tier' => 'partner', 'tier_until' => '2026-09-30', 'website' => 'https://lapsed.example/' ) );
WPCPM_Sponsors_Index::write_logo_record( $lapsed, array( 'colour' => 22, 'white' => 0, 'source' => 'site', 'airtable_id' => '' ) );

$GLOBALS['image_src'] = array(
	21 => array( 'https://site.example/uploads/acme.png', 300, 120 ),
	22 => array( 'https://site.example/uploads/lapsed.png', 300, 120 ),
);

ck( 'five sponsors in the store, in the order they were added', array_keys( WPCPM_Sponsors_Index::rows() ), array( $acme, $aardvark, $cedar, $delta, $lapsed ) );

echo "\n=== The sponsors a track may name ===\n";

ck( 'the picker\'s field, posted as a list', WPCPM_Track_Partners::FIELD, 'wpcpm_sponsors' );
ck( 'the two current Partners, by name, whatever order the store holds them in', WPCPM_Track_Partners::eligible(), array( $aardvark => 'Aardvark & Sons', $acme => 'Acme Tools' ) );

$rows = WPCPM_Sponsors_Index::rows();

ck( 'a current Partner qualifies; a Champion, a Paused Partner and one whose term ended do not',
	array( WPCPM_Track_Partners::qualifies( $rows[ $acme ] ), WPCPM_Track_Partners::qualifies( $rows[ $cedar ] ), WPCPM_Track_Partners::qualifies( $rows[ $delta ] ), WPCPM_Track_Partners::qualifies( $rows[ $lapsed ] ) ),
	array( true, false, false, false ) );
ck( 'judged on the day it is handed: on its term\'s last day the lapsed one still qualifies, and the day after it does not',
	array( WPCPM_Track_Partners::qualifies( $rows[ $lapsed ], '2026-09-30' ), WPCPM_Track_Partners::qualifies( $rows[ $lapsed ], '2026-10-01' ) ),
	array( true, false ) );
ck( 'and so is the list: on that last day it is eligible too, in its place by name',
	WPCPM_Track_Partners::eligible( '2026-09-30' ),
	array( $aardvark => 'Aardvark & Sons', $acme => 'Acme Tools', $lapsed => 'Lapsed Co' ) );

WPCPM_Sponsors_Index::patch( $aardvark, array( 'name' => 'aardvark & sons' ) );
ck( 'by name, case aside: a name in lower case keeps its place among the capitals', array_keys( WPCPM_Track_Partners::eligible() ), array( $aardvark, $acme ) );
WPCPM_Sponsors_Index::patch( $aardvark, array( 'name' => 'Aardvark & Sons' ) );

echo "\n=== The picker's choices ===\n";

ck( 'the eligible two, then a stored key that no longer qualifies with its note, and a key the store does not hold with its own',
	WPCPM_Track_Partners::choices( array( $lapsed, $unknown ) ),
	array(
		$aardvark => array( 'label' => 'Aardvark & Sons', 'note' => '' ),
		$acme     => array( 'label' => 'Acme Tools', 'note' => '' ),
		$lapsed   => array( 'label' => 'Lapsed Co', 'note' => 'no longer a Partner' ),
		$unknown  => array( 'label' => $unknown, 'note' => 'unknown sponsor' ),
	) );
ck( 'a stored key that still qualifies is offered once, with no note',
	WPCPM_Track_Partners::choices( array( $acme ) ),
	array(
		$aardvark => array( 'label' => 'Aardvark & Sons', 'note' => '' ),
		$acme     => array( 'label' => 'Acme Tools', 'note' => '' ),
	) );
ck( 'and a Champion and a Paused Partner the track names are kept too, both noted',
	array_intersect_key( WPCPM_Track_Partners::choices( array( $cedar, $delta ) ), array_flip( array( $cedar, $delta ) ) ),
	array(
		$cedar => array( 'label' => 'Cedar Hosting', 'note' => 'no longer a Partner' ),
		$delta => array( 'label' => 'Delta Services', 'note' => 'no longer a Partner' ),
	) );

echo "\n=== A track's partners, from the compiled track ===\n";

// The Developer Track's compiled row also names $unknown: a key the store no longer holds, the
// shape the Track Editor's JSON check allows and a removed record leaves behind. keys_of() reads
// the row as compiled, so it comes back with the rest; qualifying() must ask the store for it and
// take no for an answer rather than pass a missing row to qualifies(), which requires one. A row
// compiled before the removal still carries a `source`. None ever named a partner, since a marked
// track could not be saved, but `source` is not read, so one that did would be answered like any
// row: the 50-hour row is one.
compiled(
	array(
		'Developer Track' => row( 'dev', 'Developer Track', array( 'sponsors' => array( $acme, $cedar, $aardvark, $delta, $lapsed, $unknown ) ) ),
		'Designer Track'  => row( 'design', 'Designer Track', array( 'sponsors' => array( $cedar, $delta, $lapsed ) ) ),
		'Marketing Track' => row( 'marketing', 'Marketing Track' ),
		'In Sensei 50h'   => row( '50h', 'WordPress Credits Program 50h', array( 'source' => 'builtin', 'sponsors' => array( $acme ) ) ),
	)
);

ck( 'the keys a live track holds, in its order, the unknown one included', WPCPM_Track_Partners::keys_of( 'dev' ), array( $acme, $cedar, $aardvark, $delta, $lapsed, $unknown ) );
ck( 'none for a track that names none or a key no track has',
	array( WPCPM_Track_Partners::keys_of( 'marketing' ), WPCPM_Track_Partners::keys_of( 'nothing' ) ),
	array( array(), array() ) );

ck( 'the two current Partners of the six, in the track\'s order rather than by name, each with its name, website and logo: the unknown key answers no row and is skipped, not fatal',
	WPCPM_Track_Partners::for_track( 'dev' ),
	array(
		array( 'record' => $acme, 'name' => 'Acme Tools', 'website' => 'https://acme.example/?ref=wpcredits&x=1', 'logo' => 'https://site.example/uploads/acme.png' ),
		array( 'record' => $aardvark, 'name' => 'Aardvark & Sons', 'website' => 'aardvark.example', 'logo' => '' ),
	) );
ck( 'on the lapsed one\'s last day it is among them, in its place in the track',
	array_column( WPCPM_Track_Partners::for_track( 'dev', '2026-09-30' ), 'record' ),
	array( $acme, $aardvark, $lapsed ) );
ck( 'nothing for a track whose partners all fail to qualify, or one that names none',
	array( WPCPM_Track_Partners::for_track( 'design' ), WPCPM_Track_Partners::for_track( 'marketing' ) ),
	array( array(), array() ) );
ck( 'a row compiled before the removal, which still carries its source, is answered like any row: the 50-hour row\'s partner is Acme',
	array( WPCPM_Track_Partners::keys_of( '50h' ), array_column( WPCPM_Track_Partners::for_track( '50h' ), 'record' ) ),
	array( array( $acme ), array( $acme ) ) );

echo "\n=== The partnership line ===\n";

$GLOBALS['settings'] = array();
ck( 'while the tiers\' switch is off, no line at all', WPCPM_Track_Partners::render_line( 'dev' ), '' );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => false );
ck( 'and with it written off, the same', WPCPM_Track_Partners::render_line( 'dev' ), '' );

$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$line                = WPCPM_Track_Partners::render_line( 'dev' );

ck( 'with it on, the lead and one item a partner that qualifies: the logo and the name in a sponsored link, the address escaped, and no logo where the site holds none',
	$line,
	'<div class="wpcpm-student__partners"><p class="wpcpm-student__partners-lead">In partnership with</p><ul class="wpcpm-student__partners-list">'
	. '<li class="wpcpm-student__partner"><a class="wpcpm-student__partner-link" href="https://acme.example/?ref=wpcredits&amp;x=1" rel="sponsored noopener"><img class="wpcpm-student__partner-logo" src="https://site.example/uploads/acme.png" alt="" /><span class="wpcpm-student__partner-name">Acme Tools</span></a></li>'
	. '<li class="wpcpm-student__partner"><a class="wpcpm-student__partner-link" href="https://aardvark.example" rel="sponsored noopener"><span class="wpcpm-student__partner-name">Aardvark &amp; Sons</span></a></li>'
	. '</ul></div>' );
ck( 'nothing of a sponsor but its name, website and logo: no contact, no address, no note, no key',
	array( substr_count( $line, 'Should Never Appear' ), substr_count( $line, 'example.test' ), substr_count( $line, 'Manager-only' ), substr_count( $line, 'spoCCCC' ) ),
	array( 0, 0, 0, 0 ) );

// A test sponsor is never listed publicly (1.123.18), and the line is on a student's own card: a
// test Partner on a track is named to none of its students, nor binds them to its offers, while the
// Track Builder's picker still offers it to the Administrators who test with it.
WPCPM_Sponsors_Index::patch( $aardvark, array( 'test' => true ) );
ck( 'a test sponsor at Partner is left out of the track\'s partners and its line, and the picker still offers it',
	array( array_column( WPCPM_Track_Partners::for_track( 'dev' ), 'record' ), substr_count( WPCPM_Track_Partners::render_line( 'dev' ), 'Aardvark' ), array_key_exists( $aardvark, WPCPM_Track_Partners::eligible() ) ),
	array( array( $acme ), 0, true ) );
WPCPM_Sponsors_Index::patch( $aardvark, array( 'test' => false ) );

WPCPM_Sponsors_Index::patch( $aardvark, array( 'website' => '' ) );
ck( 'a partner with no website is its name alone, no link',
	substr_count( WPCPM_Track_Partners::render_line( 'dev' ), '<li class="wpcpm-student__partner"><span class="wpcpm-student__partner-name">Aardvark &amp; Sons</span></li>' ),
	1 );
WPCPM_Sponsors_Index::patch( $aardvark, array( 'website' => 'aardvark.example' ) );

// A migrated record or a manager's typing is not cleaned the way a sponsor's own save is, so the
// line meets both quotes, an ampersand and markup in a name, a website and a logo's address, and
// each leaves it escaped for its place.
WPCPM_Sponsors_Index::patch( $aardvark, array( 'name' => 'Aardvark "A&B" <b>O\'Neil</b>', 'website' => 'https://aardvark.example/?q="x"&r=<y>' ) );
$GLOBALS['image_src'][21] = array( 'https://site.example/uploads/acme"&<x>\'.png', 300, 120 );
$special_line             = WPCPM_Track_Partners::render_line( 'nothing', '', array( $aardvark, $acme ) );
$GLOBALS['image_src'][21] = array( 'https://site.example/uploads/acme.png', 300, 120 );
WPCPM_Sponsors_Index::patch( $aardvark, array( 'name' => 'Aardvark & Sons', 'website' => 'aardvark.example' ) );
ck( 'a name, a website and a logo\'s address holding both quotes, an ampersand and markup leave the line escaped, the name as text and each address as an attribute',
	$special_line,
	'<div class="wpcpm-student__partners"><p class="wpcpm-student__partners-lead">In partnership with</p><ul class="wpcpm-student__partners-list">'
	. '<li class="wpcpm-student__partner"><a class="wpcpm-student__partner-link" href="https://aardvark.example/?q=&quot;x&quot;&amp;r=&lt;y&gt;" rel="sponsored noopener"><span class="wpcpm-student__partner-name">Aardvark &quot;A&amp;B&quot; &lt;b&gt;O&#039;Neil&lt;/b&gt;</span></a></li>'
	. '<li class="wpcpm-student__partner"><a class="wpcpm-student__partner-link" href="https://acme.example/?ref=wpcredits&amp;x=1" rel="sponsored noopener"><img class="wpcpm-student__partner-logo" src="https://site.example/uploads/acme&quot;&amp;&lt;x&gt;&#039;.png" alt="" /><span class="wpcpm-student__partner-name">Acme Tools</span></a></li>'
	. '</ul></div>' );

ck( 'on the lapsed one\'s last day its line carries it, logo and all',
	substr_count( WPCPM_Track_Partners::render_line( 'dev', '2026-09-30' ), '<img class="wpcpm-student__partner-logo" src="https://site.example/uploads/lapsed.png" alt="" /><span class="wpcpm-student__partner-name">Lapsed Co</span>' ),
	1 );
ck( 'no line for a track with no partner that qualifies, one that names none, or a key no track has',
	array( WPCPM_Track_Partners::render_line( 'design' ), WPCPM_Track_Partners::render_line( 'marketing' ), WPCPM_Track_Partners::render_line( 'nothing' ) ),
	array( '', '', '' ) );
ck( 'and the 50-hour row, compiled before the removal, draws its partner\'s line like any row',
	substr_count( WPCPM_Track_Partners::render_line( '50h' ), '<span class="wpcpm-student__partner-name">Acme Tools</span>' ),
	1 );

// The Track Builder's preview hands the draft's partners, which no compiled track holds yet, and
// may itself name $unknown: the Track Editor's JSON check is shape-only, so a pasted key the store
// has never heard of reaches here too, and must be skipped rather than fail the preview.
ck( 'handed a list of keys, it draws the ones that qualify, in the list\'s order, whatever the live track holds, and skips one the store does not know',
	WPCPM_Track_Partners::render_line( 'nothing', '', array( $lapsed, $aardvark, $cedar, $acme, $unknown ) ),
	'<div class="wpcpm-student__partners"><p class="wpcpm-student__partners-lead">In partnership with</p><ul class="wpcpm-student__partners-list">'
	. '<li class="wpcpm-student__partner"><a class="wpcpm-student__partner-link" href="https://aardvark.example" rel="sponsored noopener"><span class="wpcpm-student__partner-name">Aardvark &amp; Sons</span></a></li>'
	. '<li class="wpcpm-student__partner"><a class="wpcpm-student__partner-link" href="https://acme.example/?ref=wpcredits&amp;x=1" rel="sponsored noopener"><img class="wpcpm-student__partner-logo" src="https://site.example/uploads/acme.png" alt="" /><span class="wpcpm-student__partner-name">Acme Tools</span></a></li>'
	. '</ul></div>' );
ck( 'an empty list draws nothing, even for a track whose compiled row names partners, and no list at all reads the live track\'s',
	array( WPCPM_Track_Partners::render_line( 'dev', '', array() ), WPCPM_Track_Partners::render_line( 'dev', '', null ) === WPCPM_Track_Partners::render_line( 'dev' ) ),
	array( '', true ) );

$GLOBALS['settings'] = array();
ck( 'and a list handed while the switch is off draws nothing either', WPCPM_Track_Partners::render_line( 'nothing', '', array( $acme ) ), '' );

echo "\n=== Loaded where the tracks are ===\n";

$plugin    = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
$uninstall = (string) file_get_contents( __DIR__ . '/../uninstall.php' );

ck( 'the plugin and its uninstall each require the class on the line after the tracks\' reader',
	array(
		preg_match( "#'includes/tracks/class-wpcpm-tracks\\.php';\\n[^\\n]*'includes/tracks/class-wpcpm-track-partners\\.php';\\n#", $plugin ),
		preg_match( "#'includes/tracks/class-wpcpm-tracks\\.php';\\n[^\\n]*'includes/tracks/class-wpcpm-track-partners\\.php';\\n#", $uninstall ),
	),
	array( 1, 1 ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
