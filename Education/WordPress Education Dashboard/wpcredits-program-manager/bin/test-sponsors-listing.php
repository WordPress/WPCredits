<?php
/**
 * WPCPM_Sponsors_Listing: the Approved sponsors in one list, in alphabetical order, for the
 * wpcpm/sponsors block on the Partners page, the landing strip and the Sponsors screen's handbook
 * box.
 *
 * What is pinned here and why:
 *
 * - sponsors() reads WPCPM_Sponsors_Index::approved() alone (an In review sponsor is nowhere in
 *   its answer) and lists every sponsor in one alphabetical order by name, whatever its tier, as
 *   the partner page lists its partners (1.123.18): case aside, accents aside (remove_accents()
 *   runs first) and digits in natural order, so "2" comes before "10". It carries only the public
 *   facts a row holds - never a contact, an address, a manager id, a note, the funded line or the
 *   row's own key. Each entry's tier is WPCPM_Sponsor_Tiers::effective(): a sponsor whose term
 *   lapsed reads as a Friend from the day after it ends, still as its recorded tier on the last
 *   day. An unknown floor, or one that is not even a string, is the ladder's floor; a row with no
 *   name is nowhere in the answer.
 * - entry() reads the logo record itself: the color attachment first, the white one in its
 *   place when there is no color logo or its attachment is gone, an entry saying so (`white`)
 *   so item() can mark it; neither resolving to a real file is no logo at all. A tier listed by
 *   name alone reads neither its logo record nor an attachment, and sponsors() reads the logo
 *   records of the rest in one call before the first entry.
 * - visible() and preview_only() gate the block and the strip on the launch switch
 *   (WPCPM_Sponsor_Tiers::live()): off, a program manager still sees the listing, under a note
 *   that says so; off and not a manager, nothing renders. plain(), the handbook's own HTML, is
 *   empty while the switch is off regardless of who reads it - the box that shows it is one of
 *   the surfaces the switch gates, not a manager's preview.
 * - The full list is one list in one format, no tier heading, no prominence and no line naming
 *   what a sponsor funds or which track it partners: a Friend by name, every tier above by logo,
 *   name and description. The strip is one row from Supporter up in the same alphabetical order,
 *   then the Friends as a count. The handbook HTML is the same one list.
 * - The upcoming events: while the switch is on, the full list ends with a section of its own,
 *   after the sponsors, headed "Upcoming events", listing every event WPCPM_Sponsor_Event::
 *   public_upcoming() gives, soonest first, the program's own among them: its title, its host (a
 *   sponsor's name linked as its entry links it, or the WordPress Credits Program), when it runs on
 *   the site's clock with the zone named, how long, and its description; then the way in: for a
 *   visitor signed out, a button to the login screen; for a signed-in viewer the event store counts
 *   in an audience (WPCPM_Sponsor_Event::audience_of(), a program manager first), one link and no
 *   button, to the page they join on at the list's place there, named in full: a current student's
 *   Student Report Card, an Active mentor's Mentor Report Card, an institution contact's
 *   Institution Dashboard, a program manager's Administrator Dashboard; for anyone else signed in,
 *   a sponsor's member or a subscriber, or while their page is not published, no line at all.
 *   Everything else the full list draws, the strip and the handbook HTML are the visitor's, byte
 *   for byte, whoever looks. The block's full variant carries it, a floor or not; the strip and the
 *   handbook HTML are byte for byte what they are with no event at all; nothing of an event is
 *   drawn while the switch is off, a program manager's preview included, and none coming draws no
 *   section. An event of a sponsor that is Paused, in review or lapsed, one waiting for the
 *   program's OK, one canceled and one over are nowhere; one already running is listed until it
 *   ends.
 * - The privacy walk over the events: a call link pasted into a description never reaches a
 *   surface, and a mailto address is taken out with its scheme as a web address is, while an
 *   address with no scheme is words and reaches the full list as it was written, a bare www.
 *   address among them, which the walk records; nobody on an event by
 *   name, address or count, the places, the planner's clock, an event's ID and a sponsor's key
 *   never; and the section's only links are the host's website and the way in.
 * - The block's editor script is a program manager's alone, whatever the switch says: its settings
 *   in the editor name the tiers, and a sponsor member with posting on writes in the block editor.
 *   register() registers the block for everyone, its render callback with it, and for anyone else
 *   empties the registered type's editor script handles, so their editor never registers the block
 *   and its inserter never lists it; init() adds no filter, and every other block's type is left as
 *   it was. block.json gives the floor no list of values, since the schema reaches every editor,
 *   a sponsor member's too, and does not ask core to register the block in the editor by itself.
 * - Special characters: a name, a website, a line, a logo's address and an event's title and
 *   description holding an ampersand, markup and both quotes leave the full list, the
 *   strip and the handbook HTML escaped,
 *   each by the escape its place needs. What a sponsor types is cleaned before it is stored, but a
 *   migrated record or a manager's typing is not, and the escapes return markup rather than echo
 *   it, so the standard's sniff cannot see one dropped: esc_url() is stood in as core's display
 *   escaping, its check of the scheme included, since a stand-in that handed its argument back
 *   could not tell.
 * - Every private field, on every tier: one row per tier with a sentinel in every key of
 *   empty_row() outside the public set (the name, slug, website and tier, and the description of
 *   every tier from Supporter up), derived from the row's own shape so a key added later is walked
 *   too; no sentinel may reach sponsors(), the full list, the strip or the handbook HTML, the
 *   funded line among them, and every flag turned off must change nothing they draw. Each row's
 *   own line is empty, so a line that fell back to another field would print it.
 * - The house rules: the class file never reads a contact field or names Airtable, and carries
 *   no em dash or en dash.
 *
 * Run from the plugin root:  php bin/test-sponsors-listing.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['opts']      = array(
	'date_format' => 'F j, Y',
	'time_format' => 'g:i a',
);
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['settings']  = array();
$GLOBALS['can']       = false;
$GLOBALS['image_src'] = array();
$GLOBALS['blocks']    = array();
$GLOBALS['today']     = '2026-10-06';
$GLOBALS['now']       = (int) strtotime( '2026-10-06 12:00:00 UTC' );
$GLOBALS['site_tz']   = 'Europe/Warsaw';
$GLOBALS['users']     = array();
$GLOBALS['uid']       = 0;
$GLOBALS['managers']  = array();
$GLOBALS['audiences'] = array();

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

/**
 * Core's esc_url() for display, as far as a sponsor's addresses reach it: a character no address
 * may hold is dropped (a double quote and an angle bracket among them), a bare ampersand is written
 * `&#038;` and a single quote `&#039;`, an address with no scheme is given `http://`, and one whose
 * scheme core does not allow (`wp_allowed_protocols()`) is emptied, as `wp_kses_bad_protocol()`
 * empties a `javascript:` address. A stand-in that handed its argument back could not tell a dropped
 * escape from a kept one, and the special characters' fixture below is there to tell them apart.
 *
 * @param string $url An address.
 * @return string
 */
function esc_url( $url ) {
	$url = str_replace( ' ', '%20', ltrim( (string) $url ) );
	$url = (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );

	if ( '' === $url ) {
		return '';
	}

	if ( false === strpos( $url, ':' ) && ! in_array( $url[0], array( '/', '#', '?' ), true ) ) {
		$url = 'http://' . $url;
	}

	$allowed = array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' );

	if ( '/' !== $url[0] && preg_match( '#^([^:/?\#]+):#', $url, $scheme ) && ! in_array( strtolower( $scheme[1] ), $allowed, true ) ) {
		return '';
	}

	$url = (string) preg_replace( '/&(?!#?[a-z0-9]+;)/i', '&amp;', $url );

	return str_replace( array( '&amp;', "'" ), array( '&#038;', '&#039;' ), $url );
}

function esc_html__( $text, $domain = null ) {
	return esc_html( $text );
}

function esc_attr__( $text, $domain = null ) {
	return esc_attr( $text );
}

function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}

function number_format_i18n( $number, $decimals = 0 ) {
	return (string) $number;
}

function wp_list_pluck( $list, $field ) {
	return array_column( (array) $list, $field );
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

/** A small map covering the one accented letter this suite's fixtures use, "É". */
function remove_accents( $text ) {
	return strtr( (string) $text, array( 'É' => 'E', 'é' => 'e' ) );
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['actions'][ $hook ][] = $callback;
}

/** Recorded with its priority and arguments, for the check that init() adds no filter. */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['filters'][ $hook ][] = array( $callback, $priority, $args );
}

/**
 * Core's registry of block types, as far as the listing reads it: the one instance, holding each
 * type register_block_type() below registers, by name, a type registered again replacing the last.
 */
class WP_Block_Type_Registry {
	private static $instance = null;
	private $types           = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register( WP_Block_Type $type ) {
		$this->types[ $type->name ] = $type;
	}

	public function get_registered( $name ) {
		return isset( $this->types[ $name ] ) ? $this->types[ $name ] : null;
	}
}

/** A registered block type, as far as the listing touches it: its name and its editor scripts. */
class WP_Block_Type {
	public $name                  = '';
	public $editor_script_handles = array();
}

/** Every read of a sponsor's logo option is kept in order, for the check of what the listing reads. */
function get_option( $key, $default = false ) {
	if ( 0 === strpos( (string) $key, 'wpcpm_sponsor_logo_' ) ) {
		$GLOBALS['logo_reads'][] = (string) $key;
	}

	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}

/** Core's one query for many options (6.4): kept as it is asked, since each read after it is the cache's. */
function wp_prime_option_caches( $options ) {
	$GLOBALS['primed'][] = array_values( (array) $options );
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ] = $value;
	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['opts'][ $key ] );
	return true;
}

/**
 * Today, `Y-m-d` with no moment given, answers the pinned $GLOBALS['today']; a moment is written on
 * the clock given, the site's ($GLOBALS['site_tz']) when none is, as core's wp_date() writes one.
 */
function wp_date( $format, $timestamp = null, $timezone = null ) {
	if ( null === $timestamp ) {
		return 'Y-m-d' === $format ? $GLOBALS['today'] : gmdate( $format );
	}

	$when = new DateTime( '@' . (int) $timestamp );
	$when->setTimezone( $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( $GLOBALS['site_tz'] ) );

	return $when->format( $format );
}

function wp_timezone_string() {
	return $GLOBALS['site_tz'];
}

function wp_timezone() {
	return new DateTimeZone( $GLOBALS['site_tz'] );
}

/** The login screen's address, with the address to come back to when one is given. */
function wp_login_url( $redirect = '' ) {
	return 'https://example.test/wp-login.php' . ( '' !== $redirect ? '?redirect_to=' . rawurlencode( $redirect ) : '' );
}

/** Answers $GLOBALS['can'] for the one capability the class reads, false for any other. */
function current_user_can( $cap ) {
	return WPCPM_Roles::CAP_MANAGE === $cap ? (bool) $GLOBALS['can'] : false;
}

/** An account, as far as the event store asks one who is looking: its ID, and whether it exists. */
class WP_User {
	public $ID = 0;

	public function __construct( $id = 0 ) {
		$this->ID = (int) $id;
	}

	public function exists() {
		return $this->ID > 0;
	}
}

/** The person looking, $GLOBALS['uid']: 0 for a visitor who is signed out. */
function get_current_user_id() {
	return (int) $GLOBALS['uid'];
}

function is_user_logged_in() {
	return get_current_user_id() > 0;
}

function wp_get_current_user() {
	return new WP_User( get_current_user_id() );
}

/** The program manager's capability, for the accounts in $GLOBALS['managers'] alone. */
function user_can( $user, $cap ) {
	$id = $user instanceof WP_User ? $user->ID : (int) $user;

	return WPCPM_Roles::CAP_MANAGE === $cap && in_array( $id, $GLOBALS['managers'], true );
}

/** The full attachment record ( url, width, height ) a real Media Library size array holds. */
function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
	$GLOBALS['attachments_asked'][] = (int) $id;

	return isset( $GLOBALS['image_src'][ $id ] ) ? $GLOBALS['image_src'][ $id ] : false;
}

/** WPCPM_Sponsors_Index::display_logo()'s one external call, over the same table. */
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	return isset( $GLOBALS['image_src'][ $id ][0] ) ? $GLOBALS['image_src'][ $id ][0] : false;
}

/**
 * Records its arguments, so register() can be proven, and registers the type core would make from
 * the block.json it finds: its name, and for `editorScript` the handle core registers the file as.
 */
function register_block_type( $dir, $args = array() ) {
	$GLOBALS['blocks'][] = array( $dir, $args );

	$json                        = json_decode( (string) file_get_contents( $dir . '/block.json' ), true );
	$type                        = new WP_Block_Type();
	$type->name                  = (string) $json['name'];
	$type->editor_script_handles = isset( $json['editorScript'] ) ? array( str_replace( '/', '-', $type->name ) . '-editor-script' ) : array();

	WP_Block_Type_Registry::get_instance()->register( $type );

	return $type;
}

function get_block_wrapper_attributes( array $extra = array() ) {
	return 'class="wp-block-wpcpm-sponsors' . ( isset( $extra['class'] ) ? ' ' . $extra['class'] : '' ) . '"';
}

/** Only the one constant the class reads. */
class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

/** Stands in for the plugin's real settings store, read through the one key the class reads. */
class WPCPM_Settings {
	public static function get_value( $key, $fallback = null ) {
		return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
	}
}

/**
 * A post as the two stores read one: the sponsors store's slug, which bin/stubs/posts.php asks a
 * suite's own class to carry, and an event's description, which the event store reads off its
 * content.
 */
class WP_Post {
	public $ID           = 0;
	public $post_type    = 'post';
	public $post_status  = 'draft';
	public $post_title   = '';
	public $post_name    = '';
	public $post_author  = 0;
	public $post_content = '';
}

require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';

// For its constants alone: the tracks' reader locks the four original tracks by the program map's
// status constants (`WPCPM_Tracks::RESERVED_PAIRS`), which PHP resolves on the reader's first
// static call.
require_once __DIR__ . '/../includes/class-wpcpm-program.php';

// The real class, not a stand-in: partner_tracks() reads only get_option() (already stubbed
// above) and does no other read, so this suite seeds the compiled option itself
// (WPCPM_Tracks::OPT_TRACKS) rather than fake the one method the listing calls on it.
require_once __DIR__ . '/../includes/tracks/class-wpcpm-tracks.php';

// register()'s own file_exists() is the real function, so it needs a real block.json to find,
// but this suite must not depend on or write into the plugin's real blocks/ folder - its files
// are not this class's to create or assume. The fixture goes in this run's own temp directory
// (bin/stubs/temp-dir.php) instead, and WPCPM_PLUGIN_DIR points there for the length of this run.
$fixture_blocks = wpcpm_test_temp_dir() . 'blocks/sponsors';
mkdir( $fixture_blocks, 0700, true );
file_put_contents( $fixture_blocks . '/block.json', '{"name":"wpcpm/sponsors","editorScript":"file:./editor.js"}' );
define( 'WPCPM_PLUGIN_DIR', wpcpm_test_temp_dir() );

// The event store the full list's upcoming events come from (public_upcoming()), with the zone
// reader and the calls' range formatter the section writes a time with: the plugin's own, not
// stand-ins, so the privacy walk below reads what the page is given.
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-availability.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-mentor-calls.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event.php';

// Who a signed-in viewer is, as the event store's audience_of() reads it: a program manager first,
// then the three modules' own readers, stood in here and answering from $GLOBALS['audiences'] (user
// ID => the readers that say yes), each resolving the person as the real ones do: an account, an
// ID, or else the person looking. The readers themselves are the events suite's to pin; what is
// pinned here is that the Partners page asks the store, in the store's order. The event list, the
// plugin's own, maps each audience to its page, and the four dashboards' addresses are stood in,
// the Institution Dashboard's with a query string, so the escape of the address shows.
function listing_viewer_is( $user, $reader ) {
	if ( $user instanceof WP_User ) {
		$id = $user->ID;
	} else {
		$id = is_numeric( $user ) && (int) $user > 0 ? (int) $user : get_current_user_id();
	}

	return isset( $GLOBALS['audiences'][ $id ] ) && in_array( $reader, $GLOBALS['audiences'][ $id ], true );
}

class WPCPM_Sponsor_Tools {
	public static function is_current_student( $user ) {
		return listing_viewer_is( $user, 'student' );
	}
}

class WPCPM_Mentors_Dashboard {
	public static function is_active_mentor( $user = null ) {
		return listing_viewer_is( $user, 'mentor' );
	}

	public static function page_url() {
		return $GLOBALS['pages']['mentor'];
	}
}

class WPCPM_Institution_Roster {
	public static function resolve_institution( $viewer, $can_manage ) {
		return listing_viewer_is( $viewer, 'contact' ) ? 'recSEED0000000041' : '';
	}
}

class WPCPM_Students_Dashboard {
	public static function page_url() {
		return $GLOBALS['pages']['student'];
	}
}

class WPCPM_Institutions_Dashboard {
	public static function page_url() {
		return $GLOBALS['pages']['institution'];
	}
}

class WPCPM_Administrators_Dashboard {
	public static function page_url() {
		return $GLOBALS['pages']['administrator'];
	}
}

$GLOBALS['pages'] = array(
	'student'       => 'https://example.test/student-report-card/',
	'mentor'        => 'https://example.test/mentor-report-card/',
	'institution'   => 'https://example.test/?page_id=41&lang=en',
	'administrator' => 'https://example.test/administrator-dashboard/',
);

require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-event-list.php';

require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-listing.php';

// The store's clock is the suite's: the events below are to come, running or over against it.
WPCPM_Sponsor_Event::for_tests(
	static function () {
		return $GLOBALS['now'];
	}
);

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
 * @param string $status 'Approved' or 'In review'.
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

$L = 'WPCPM_Sponsors_Listing';

echo "=== Seed ===\n";

// Zeta also carries a manager's private fields, so the House rules and the leak checks below
// have something real to prove absent from every public surface.
add_sponsor( 'spoAAAA0000000001', 'Zeta Hosting', 'Approved', array(
	'tier'           => 'partner',
	'website'        => 'https://zeta.example/',
	'contact_person' => 'Should Never Appear',
	'contact_email'  => 'contact+listing@example.test',
	'manager'        => '7',
	'notes'          => 'Manager-only notes',
	'description'    => 'Hosting for student sites.',
) );
WPCPM_Sponsors_Index::write_logo_record( 'spoAAAA0000000001', array( 'colour' => 11, 'white' => 0, 'source' => 'site', 'airtable_id' => '' ) );

add_sponsor( 'spoAAAA0000000002', 'Acme Tools', 'Approved', array( 'tier' => 'champion', 'website' => 'https://acme.example/' ) );
WPCPM_Sponsors_Index::write_logo_record( 'spoAAAA0000000002', array( 'colour' => 12, 'white' => 0, 'source' => 'site', 'airtable_id' => '' ) );

// A Backer's funded line names it in the program's communications and its report, never on the
// Partners page, which prints its description as every tier's from Supporter up.
add_sponsor( 'spoAAAA0000000003', 'Beta Plugins', 'Approved', array( 'tier' => 'backer', 'website' => 'https://beta.example/', 'funded_line' => "the Design Track's mentors", 'description' => "Plugins for a school's classrooms." ) );
WPCPM_Sponsors_Index::write_logo_record( 'spoAAAA0000000003', array( 'colour' => 13, 'white' => 0, 'source' => 'site', 'airtable_id' => '' ) );

add_sponsor( 'spoAAAA0000000004', 'Gamma Cloud', 'Approved', array( 'tier' => 'supporter', 'website' => 'gamma.example/', 'description' => 'Managed hosting for schools.' ) );
WPCPM_Sponsors_Index::write_logo_record( 'spoAAAA0000000004', array( 'colour' => 14, 'white' => 0, 'source' => 'site', 'airtable_id' => '' ) );

add_sponsor( 'spoAAAA0000000005', 'Delta Services', 'Approved', array( 'tier' => 'supporter', 'website' => 'https://delta.example/', 'description' => 'Training for contributors.' ) );

add_sponsor( 'spoAAAA0000000006', 'Echo Inc', 'Approved', array( 'tier' => 'mentor', 'website' => 'https://echo.example/' ) );

add_sponsor( 'spoAAAA0000000007', 'Held Co', 'In review', array( 'tier' => 'champion', 'website' => 'https://held.example/' ) );

add_sponsor( 'spoAAAA0000000008', 'Lapsed Co', 'Approved', array( 'tier' => 'backer', 'tier_until' => '2026-01-31', 'website' => 'https://lapsed.example/' ) );
WPCPM_Sponsors_Index::write_logo_record( 'spoAAAA0000000008', array( 'colour' => 15, 'white' => 0, 'source' => 'site', 'airtable_id' => '' ) );

// A white-only logo: no color attachment, a working white one. It keeps its place in the
// listing (the ruling this fix round adds), carrying item()'s --white modifier.
add_sponsor( 'spoAAAA0000000009', 'Hotel Hosting', 'Approved', array( 'tier' => 'backer', 'website' => 'https://hotel.example/', 'funded_line' => "one mentor's stipend" ) );
WPCPM_Sponsors_Index::write_logo_record( 'spoAAAA0000000009', array( 'colour' => 0, 'white' => 16, 'source' => 'site', 'airtable_id' => '' ) );

// A color attachment the Media Library no longer holds (17 is never in image_src), no white
// logo to fall back to, and no website either: a text item with no link, still an item.
add_sponsor( 'spoAAAA0000000010', 'Nova Systems', 'Approved', array( 'tier' => 'champion' ) );
WPCPM_Sponsors_Index::write_logo_record( 'spoAAAA0000000010', array( 'colour' => 17, 'white' => 0, 'source' => 'site', 'airtable_id' => '' ) );

// Sorts between Delta and Gamma only if the compare is accent aside; an uppercase scheme, kept
// as the record holds it.
add_sponsor( 'spoAAAA0000000011', 'Émile Tools', 'Approved', array( 'tier' => 'supporter', 'website' => 'HTTPS://EMILE.EXAMPLE/' ) );

// An Approved row with no name: sponsors() must print nothing for it, anywhere.
add_sponsor( 'spoAAAA0000000012', '', 'Approved', array( 'tier' => 'mentor', 'website' => 'https://nameless.example/' ) );

$GLOBALS['image_src'] = array(
	11 => array( 'https://site.example/uploads/zeta.png', 300, 120 ),
	12 => array( 'https://site.example/uploads/acme.png', 300, 120 ),
	13 => array( 'https://site.example/uploads/beta.png', 300, 120 ),
	14 => array( 'https://site.example/uploads/gamma.png', 300, 120 ),
	15 => array( 'https://site.example/uploads/lapsed.png', 300, 120 ),
	16 => array( 'https://site.example/uploads/hotel-white.png', 300, 120 ),
	// 17 (Nova Systems' color attachment) is deliberately absent: it is gone.
);

// The upcoming events. A person on each of them, with a name and an address no surface may show;
// on each a call link, the one secret an event holds, and places, a number no surface prints.
$GLOBALS['users'][21]  = (object) array(
	'ID'           => 21,
	'display_name' => 'Joan Joinerwalk',
	'user_email'   => 'joan.joinerwalk@people.example',
);
$GLOBALS['event_ids'] = array();

/**
 * One event, written into the store as its writers write one, each meta under the store's own key:
 * its kind, its start, end and minutes, the planner's clock, its places, its call link, its state
 * and the person on it. An event of the program's own has no sponsor.
 *
 * @param string $record      The sponsor's key, or '' for the program's own.
 * @param string $state       `approved`, `planned` or `cancelled`.
 * @param string $start       When it starts, in UTC.
 * @param int    $minutes     How long it runs.
 * @param string $title       Its title.
 * @param string $description Its description, as the planner stored it.
 * @return int The event's ID.
 */
function add_event( $record, $state, $start, $minutes, $title, $description ) {
	$id    = wp_insert_post(
		array(
			'post_type'   => WPCPM_Sponsor_Event::POST_TYPE,
			'post_status' => 'private',
			'post_title'  => $title,
		)
	);
	$start = (int) strtotime( $start . ' UTC' );
	$meta  = array(
		WPCPM_Sponsor_Event::META_KIND    => '' === $record ? WPCPM_Sponsor_Event::KIND_PROGRAM : WPCPM_Sponsor_Event::KIND_CO_HOSTED,
		WPCPM_Sponsor_Event::META_START   => $start,
		WPCPM_Sponsor_Event::META_END     => $start + 60 * (int) $minutes,
		WPCPM_Sponsor_Event::META_ZONE    => 'America/New_York',
		WPCPM_Sponsor_Event::META_MINUTES => (int) $minutes,
		WPCPM_Sponsor_Event::META_PLACES  => 45,
		WPCPM_Sponsor_Event::META_LINK    => 'https://call.example/secret-room-4242?pwd=abc',
		WPCPM_Sponsor_Event::META_STATE   => $state,
		WPCPM_Sponsor_Event::META_PERSON  => 21,
	);

	if ( '' !== $record ) {
		$meta[ WPCPM_Sponsor_Event::META_SPONSOR ] = $record;
	}

	$GLOBALS['posts'][ $id ]->post_content = $description;

	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}

	$GLOBALS['event_ids'][] = $id;

	return $id;
}

// A Champion the program paused: in no listing, and nor is its event.
add_sponsor( 'spoAAAA0000000021', 'Paused Co', 'Paused', array( 'tier' => 'champion', 'website' => 'https://paused.example/' ) );

// The program's own and Zeta Hosting's, soonest first on the page, each description's line breaks
// and runs of spaces to fold; Zeta's holds its call link, a mailto and a bare www. address.
add_event( '', 'approved', '2026-10-14 14:00', 60, 'Meet the program', "An hour with the program's team,\nand what the next term holds." );
add_event( 'spoAAAA0000000001', 'approved', '2026-10-20 12:00', 90, 'Blocks for school sites', "Bring a site to work on.\n\nThe call:  https://call.example/secret-room-4242?pwd=abc\nQuestions: mailto:events@zeta.example or www.zeta.example/events" );
// None of these five reaches a page: a Paused sponsor's, one in review, one waiting for the
// program's OK, one canceled, and one that is over.
add_event( 'spoAAAA0000000021', 'approved', '2026-10-16 12:00', 60, 'Paused webinar', 'Hidden while the sponsor is paused.' );
add_event( 'spoAAAA0000000007', 'approved', '2026-10-18 12:00', 60, 'Held webinar', 'Hidden while the sponsor is in review.' );
add_event( 'spoAAAA0000000002', 'planned', '2026-10-22 12:00', 60, 'Waiting webinar', 'Hidden until the program approves it.' );
add_event( 'spoAAAA0000000002', 'cancelled', '2026-10-23 12:00', 60, 'Canceled webinar', 'Hidden once canceled.' );
add_event( 'spoAAAA0000000001', 'approved', '2026-10-01 12:00', 60, 'Past webinar', 'Hidden once it is over.' );

echo "=== Visibility ===\n";
$GLOBALS['settings'] = array();
$GLOBALS['can']      = false;
ck( 'the switch off, no manager: nothing is visible', $L::visible(), false );
ck( 'and the full list is the empty string', $L::render( array( 'variant' => 'full' ), '2026-10-06' ), '' );
ck( 'and so is the strip', $L::render( array( 'variant' => 'strip' ), '2026-10-06' ), '' );
ck( 'and the block prints nothing at all', $L::render_block( array( 'variant' => 'strip' ) ), '' );
$GLOBALS['can'] = true;
ck( 'the switch off, a program manager: visible, as a preview', array( $L::visible(), $L::preview_only() ), array( true, true ) );
$html = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
ck( 'and the preview note opens the manager\'s copy', 0 === strpos( $html, '<p class="wpcpm-sponsors__preview">Shown to you as an Administrator. Visitors see this once Sponsor tiers is on in Settings.</p>' ), true );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
$GLOBALS['can']      = false;
ck( 'the switch on: visible to everyone, and no note', array( $L::visible(), $L::preview_only(), strpos( $L::render( array( 'variant' => 'full' ), '2026-10-06' ), 'wpcpm-sponsors__preview' ) ), array( true, false, false ) );

echo "=== One list, in alphabetical order ===\n";
$listed = $L::sponsors( 'mentor', '2026-10-06' );
ck( 'one list of every Approved sponsor with a name, in alphabetical order whatever the tier: case aside, and accents aside, so Émile lands between Echo and Gamma', wp_list_pluck( $listed, 'name' ), array( 'Acme Tools', 'Beta Plugins', 'Delta Services', 'Echo Inc', 'Émile Tools', 'Gamma Cloud', 'Hotel Hosting', 'Lapsed Co', 'Nova Systems', 'Zeta Hosting' ) );
ck( 'each entry carries its effective tier and how it is listed: a Friend by name, every tier above by logo', array( wp_list_pluck( $listed, 'tier' ), wp_list_pluck( $listed, 'listing' ) ), array(
	array( 'champion', 'backer', 'supporter', 'mentor', 'supporter', 'supporter', 'backer', 'mentor', 'champion', 'partner' ),
	array( 'logo', 'logo', 'logo', 'name', 'logo', 'logo', 'logo', 'name', 'logo', 'logo' ),
) );
ck( 'the lapsed backer reads as a Friend today', $listed[7]['tier'], 'mentor' );
ck( 'and as a backer on the last day of its term', $L::sponsors( 'mentor', '2026-01-31' )[7]['tier'], 'backer' );
ck( 'the sponsor in review is nowhere', strpos( wp_json_encode( $listed ), 'Held Co' ), false );
ck( 'an Approved row with an empty name is nowhere in the output', strpos( wp_json_encode( $L::sponsors( 'mentor', '2026-10-06' ) ), 'nameless' ), false );
ck( 'every tier from Supporter up carries its description, a Backer\'s in place of its funded line, and a Friend none', array( $listed[1]['line'], $listed[2]['line'], $listed[9]['line'], $listed[0]['line'], $listed[3]['line'] ), array( "Plugins for a school's classrooms.", 'Training for contributors.', 'Hosting for student sites.', '', '' ) );
ck( 'no entry carries a funded line or a track', array( strpos( wp_json_encode( $listed ), 'Design Track' ), strpos( wp_json_encode( $listed ), 'stipend' ), array_key_exists( 'tracks', $listed[9] ) ), array( false, false, false ) );
ck( 'a logo is the medium size with its dimensions, and no logo is an empty address', array( $listed[9]['logo'], $listed[9]['width'], $listed[9]['height'], $listed[2]['logo'] ), array( 'https://site.example/uploads/zeta.png', 300, 120, '' ) );
ck( 'a white-only logo keeps its place, marked white, at the medium size', array( $listed[6]['logo'], $listed[6]['white'], $listed[6]['width'] ), array( 'https://site.example/uploads/hotel-white.png', true, 300 ) );
ck( 'a color logo is not marked white', $listed[9]['white'], false );
ck( 'a gone attachment and no white to fall back to: no logo at all', array( $listed[8]['logo'], $listed[8]['white'] ), array( '', false ) );
ck( 'a floor drops the sponsors below it and keeps the order', wp_list_pluck( $L::sponsors( 'backer', '2026-10-06' ), 'name' ), array( 'Acme Tools', 'Beta Plugins', 'Hotel Hosting', 'Nova Systems', 'Zeta Hosting' ) );
ck( 'an unknown floor is the floor of the ladder', count( $L::sponsors( 'gold', '2026-10-06' ) ), 10 );
ck( 'a floor that is not even a string - an array - is the floor of the ladder too, no notice', count( $L::sponsors( array( 'not', 'a', 'tier' ), '2026-10-06' ) ), 10 );
ck( 'no entry carries a contact, an address, a manager id, a note or a key', array( strpos( wp_json_encode( $listed ), 'contact' ), strpos( wp_json_encode( $listed ), '@' ), strpos( wp_json_encode( $listed ), 'spoAAAA' ), strpos( wp_json_encode( $listed ), 'Manager-only' ) ), array( false, false, false, false ) );
ck( 'the tier groups are gone: no groups() and no tier heading', array( method_exists( $L, 'groups' ), method_exists( $L, 'heading' ) ), array( false, false ) );

// The listing prints a Friend's name alone, so its logo is never asked for, the lapsed
// Backer's among them; the logo options of the eight it prints a logo for are read in one call
// before the first entry, where they used to cost one query a sponsor.
$GLOBALS['primed']            = array();
$GLOBALS['logo_reads']        = array();
$GLOBALS['attachments_asked'] = array();
$L::sponsors( 'mentor', '2026-10-06' );
$read_once = array_map( array( 'WPCPM_Sponsors_Index', 'logo_option' ), array( 'spoAAAA0000000001', 'spoAAAA0000000002', 'spoAAAA0000000003', 'spoAAAA0000000004', 'spoAAAA0000000005', 'spoAAAA0000000009', 'spoAAAA0000000010', 'spoAAAA0000000011' ) );
$one_call  = isset( $GLOBALS['primed'][0] ) ? $GLOBALS['primed'][0] : array();
sort( $read_once );
sort( $one_call );
ck( 'sponsors() reads the logo options it needs in one call before the entries, and a Friend\'s logo not at all', array(
	count( $GLOBALS['primed'] ),
	$one_call === $read_once,
	array_values( array_diff( $GLOBALS['logo_reads'], $read_once ) ),
	in_array( 15, $GLOBALS['attachments_asked'], true ),
), array( 1, true, array(), false ) );

// Natural order for digits, case aside: "2" before "10", and a name in lower case among the rest.
$saved_posts      = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
WPCPM_Sponsors_Index::forget();
add_sponsor( 'spoAAAA0000000031', '10up Studio', 'Approved', array( 'tier' => 'supporter' ) );
add_sponsor( 'spoAAAA0000000032', 'beta lower', 'Approved', array( 'tier' => 'partner' ) );
add_sponsor( 'spoAAAA0000000033', '2 Wheels', 'Approved', array( 'tier' => 'mentor' ) );
add_sponsor( 'spoAAAA0000000034', 'Alpha Upper', 'Approved', array( 'tier' => 'backer' ) );
add_sponsor( 'spoAAAA0000000035', 'Item 9', 'Approved', array( 'tier' => 'champion' ) );
add_sponsor( 'spoAAAA0000000036', 'item 10', 'Approved', array( 'tier' => 'supporter' ) );
ck( 'digits sort in natural order and case is set aside: 2 before 10, Item 9 before item 10, Alpha before beta', wp_list_pluck( $L::sponsors( 'mentor', '2026-10-06' ), 'name' ), array( '2 Wheels', '10up Studio', 'Alpha Upper', 'beta lower', 'Item 9', 'item 10' ) );
$GLOBALS['posts'] = $saved_posts;
WPCPM_Sponsors_Index::forget();

echo "=== The full list ===\n";
$html = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
ck( 'one list, every sponsor an item of it, no section and no heading before the upcoming events', array(
	0 === strpos( $html, '<ul class="wpcpm-sponsors__list"><li class="wpcpm-sponsors__item">' ),
	substr_count( $html, '<li class="wpcpm-sponsors__item' ),
	substr( $html, 0, (int) strpos( $html, '<section class="wpcpm-sponsors__events">' ) ) === substr( $html, 0, (int) strpos( $html, '</ul>' ) + 5 ),
	substr_count( $html, '<h3' ),
	strpos( $html, 'wpcpm-sponsors__group' ),
	strpos( $html, 'wpcpm-sponsors__names' ),
), array( true, 10, true, 1, false, false ) );
preg_match_all( '/<span class="wpcpm-sponsors__name">([^<]+)</', substr( $html, 0, (int) strpos( $html, '</ul>' ) ), $m );
ck( 'the items run in alphabetical order, each name printed for everyone to read', $m[1], array( 'Acme Tools', 'Beta Plugins', 'Delta Services', 'Echo Inc', 'Émile Tools', 'Gamma Cloud', 'Hotel Hosting', 'Lapsed Co', 'Nova Systems', 'Zeta Hosting' ) );
ck( 'a sponsor with a logo is a link holding the image, with an empty alt, and its name, then its description', false !== strpos( $html, '<li class="wpcpm-sponsors__item"><a class="wpcpm-sponsors__link" href="https://zeta.example/" rel="sponsored noopener"><img class="wpcpm-sponsors__logo" src="https://site.example/uploads/zeta.png" alt="" width="300" height="120" loading="lazy" /><span class="wpcpm-sponsors__name">Zeta Hosting</span></a><span class="wpcpm-sponsors__line">Hosting for student sites.</span></li>' ), true );
ck( 'a backer carries its description after the link, never its funded line', array( false !== strpos( $html, 'Beta Plugins</span></a><span class="wpcpm-sponsors__line">Plugins for a school&#039;s classrooms.</span></li>' ), strpos( $html, 'Design Track' ), strpos( $html, 'stipend' ) ), array( true, false, false ) );
ck( 'a sponsor with a logo and no description prints no line', false !== strpos( $html, '<li class="wpcpm-sponsors__item"><a class="wpcpm-sponsors__link" href="https://acme.example/" rel="sponsored noopener"><img class="wpcpm-sponsors__logo" src="https://site.example/uploads/acme.png" alt="" width="300" height="120" loading="lazy" /><span class="wpcpm-sponsors__name">Acme Tools</span></a></li>' ), true );
ck( 'a sponsor without a logo shows its name in the link', false !== strpos( $html, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><a class="wpcpm-sponsors__link" href="https://delta.example/" rel="sponsored noopener"><span class="wpcpm-sponsors__name">Delta Services</span></a><span class="wpcpm-sponsors__line">Training for contributors.</span></li>' ), true );
ck( 'a Friend is its name alone among the others, no link, no logo, no line', array( false !== strpos( $html, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><span class="wpcpm-sponsors__name">Echo Inc</span></li><li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><a class="wpcpm-sponsors__link" href="HTTPS://EMILE.EXAMPLE/" rel="sponsored noopener"><span class="wpcpm-sponsors__name">Émile Tools</span></a></li>' ), strpos( $html, 'echo.example' ), false !== strpos( $html, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><span class="wpcpm-sponsors__name">Lapsed Co</span></li>' ) ), array( true, false, true ) );
// The lowest tier is named Friend since 1.123.13, and the old name is printed nowhere.
ck( 'no tier is named on the list, and "Mentor sponsors" is printed nowhere', array( preg_match( '/\b(Friends|Supporters|Backers|Champions|Partners)\b/', $html ), preg_match( '/mentor sponsor/i', $html ) ), array( 0, 0 ) );
ck( 'a scheme-less website gets https', false !== strpos( $html, 'href="https://gamma.example/"' ), true );
ck( 'a white-only logo prints with the --white modifier and its name', false !== strpos( $html, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--white"><a class="wpcpm-sponsors__link" href="https://hotel.example/" rel="sponsored noopener"><img class="wpcpm-sponsors__logo" src="https://site.example/uploads/hotel-white.png" alt="" width="300" height="120" loading="lazy" /><span class="wpcpm-sponsors__name">Hotel Hosting</span></a></li>' ), true );
ck( 'a gone attachment and no website: a text item with no link at all', false !== strpos( $html, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><span class="wpcpm-sponsors__name">Nova Systems</span></li>' ), true );
$floored = $L::render( array( 'variant' => 'full', 'floor' => 'champion' ), '2026-10-06' );
ck( 'the full list with a floor: its three sponsors in the one list, then the upcoming events', array( substr_count( $floored, '<li class="wpcpm-sponsors__item' ), substr_count( $floored, '<section' ) ), array( 3, 1 ) );

echo "=== Upcoming events ===\n";
$events_at = strpos( $html, '<section class="wpcpm-sponsors__events">' );
$events    = false !== $events_at ? substr( $html, $events_at ) : '';
ck(
	'the full list ends with the upcoming events, headed as a group is: each event a list item with its title, its host (a sponsor\'s name linked as its entry links it, or the program), when it runs on the site\'s clock with the zone named, how long, and its description with the call link and the mailto taken out and its spaces run together, soonest first; then one link, to sign in',
	$events,
	'<section class="wpcpm-sponsors__events"><h3 class="wpcpm-sponsors__heading">Upcoming events</h3><ul class="wpcpm-sponsors__list">'
		. '<li class="wpcpm-sponsors__event"><span class="wpcpm-sponsors__event-title">Meet the program</span><span class="wpcpm-sponsors__line">Hosted by the WordPress Credits Program</span><span class="wpcpm-sponsors__line">October 14, 2026 4:00 pm to 5:00 pm (Europe / Warsaw). 60 minutes.</span><span class="wpcpm-sponsors__line">An hour with the program&#039;s team, and what the next term holds.</span></li>'
		. '<li class="wpcpm-sponsors__event"><span class="wpcpm-sponsors__event-title">Blocks for school sites</span><span class="wpcpm-sponsors__line">Hosted by <a href="https://zeta.example/" rel="sponsored noopener">Zeta Hosting</a></span><span class="wpcpm-sponsors__line">October 20, 2026 2:00 pm to 3:30 pm (Europe / Warsaw). 90 minutes.</span><span class="wpcpm-sponsors__line">Bring a site to work on. The call: Questions: or www.zeta.example/events</span></li>'
		. '</ul><p class="wpcpm-sponsors__join"><a class="wpcpm-button wpcpm-button--secondary" href="https://example.test/wp-login.php">Sign in to join</a></p></section>'
);
ck( 'the section follows the list, its last sponsor', false !== strpos( $html, '<span class="wpcpm-sponsors__line">Hosting for student sites.</span></li></ul><section class="wpcpm-sponsors__events">' ), true );
ck(
	'the full list holds the upcoming events, and no event of a Paused sponsor, of one in review, waiting for the program\'s OK, canceled or over',
	array_merge(
		array( '' !== $events ),
		array_map(
			static function ( $words ) use ( $html ) {
				return false !== strpos( $html, $words );
			},
			array( 'Paused webinar', 'Held webinar', 'Waiting webinar', 'Canceled webinar', 'Past webinar', 'Hidden' )
		)
	),
	array_merge( array( true ), array_fill( 0, 6, false ) )
);

$event_strip = $L::render( array( 'variant' => 'strip' ), '2026-10-06' );
$event_plain = $L::plain( '2026-10-06' );
$event_block = $L::render_block( array( 'variant' => 'full' ) );

// The same surfaces with no event in the store at all, for the byte-for-byte checks.
$saved_posts = $GLOBALS['posts'];
foreach ( $GLOBALS['posts'] as $event_id => $event_post ) {
	if ( WPCPM_Sponsor_Event::POST_TYPE === $event_post->post_type ) {
		unset( $GLOBALS['posts'][ $event_id ] );
	}
}
$no_event_html  = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
$no_event_strip = $L::render( array( 'variant' => 'strip' ), '2026-10-06' );
$no_event_plain = $L::plain( '2026-10-06' );
$GLOBALS['posts'] = $saved_posts;

ck(
	'the landing page\'s strip and the handbook HTML are byte for byte what they are with no event at all, and with none coming the full list ends with its list of sponsors, the same bytes either way',
	array( $event_strip === $no_event_strip, $event_plain === $no_event_plain, strpos( $no_event_html, 'wpcpm-sponsors__event' ), false !== $events_at && substr( $html, 0, $events_at ) === $no_event_html ),
	array( true, true, false, true )
);
ck(
	'the block\'s full variant carries the section inside its wrapper, so every page that holds the block lists the events',
	array( 0 === strpos( $event_block, '<div class="wp-block-wpcpm-sponsors wpcpm-sponsors wpcpm-sponsors--full">' ), substr_count( $event_block, '<section class="wpcpm-sponsors__events">' ), '' !== $events && substr( $event_block, -strlen( $events . '</div>' ) ) === $events . '</div>' ),
	array( true, 1, true )
);
$floored    = $L::render( array( 'variant' => 'full', 'floor' => 'partner' ), '2026-10-06' );
$floored_at = strpos( $floored, '<section class="wpcpm-sponsors__events">' );
ck(
	'a floor drops sponsors, never events: the list from Partner up ends with every upcoming event',
	array( substr_count( $floored, '<li class="wpcpm-sponsors__item' ), false !== $floored_at && substr( $floored, $floored_at ) === $events ),
	array( 1, true )
);

$call_found = array();
foreach ( array( 'full' => $html, 'block' => $event_block, 'strip' => $event_strip, 'handbook' => $event_plain ) as $surface => $text ) {
	foreach ( array( 'call.example', 'secret-room-4242', 'pwd=abc' ) as $needle ) {
		if ( false !== strpos( $text, $needle ) ) {
			$call_found[] = $surface . ': ' . $needle;
		}
	}
}
ck(
	'the privacy walk over a description holding a call link, a mailto and a bare www. address, recorded: the call link reaches no surface, the mailto is taken out of the full list with its scheme, and the address with no scheme is words the full list prints as it was written, since an http, https or mailto address is taken out and nothing else',
	array(
		'call link' => $call_found,
		'mailto'    => false !== strpos( $events, 'mailto:events@zeta.example' ),
		'www.'      => false !== strpos( $events, 'www.zeta.example/events' ),
	),
	array(
		'call link' => array(),
		'mailto'    => false,
		'www.'      => true,
	)
);

$event_id_found = array();
foreach ( $GLOBALS['event_ids'] as $event_id ) {
	if ( preg_match( '/(?<![0-9])' . (int) $event_id . '(?![0-9])/', $events ) ) {
		$event_id_found[] = $event_id;
	}
}
ck(
	'nothing a joiner alone should see reaches the page, which lists the events: nobody on an event by name or address, no count of who joined, no places, no event\'s ID, not the planner\'s clock, no sponsor\'s key',
	array(
		substr_count( $events, '<li class="wpcpm-sponsors__event">' ),
		strpos( $events, 'Joan Joinerwalk' ),
		strpos( $events, 'joan.joinerwalk' ),
		stripos( $events, 'joined' ),
		strpos( $events, '45' ),
		stripos( $events, 'place' ),
		stripos( $events, 'open to everyone' ),
		strpos( $events, 'New York' ),
		strpos( $events, 'spoAAAA' ),
		$event_id_found,
		preg_match( '/\s(id|data-[a-z-]+)="/', $events ),
	),
	array( 2, false, false, false, false, false, false, false, false, array(), 0 )
);
preg_match_all( '/href="([^"]*)"/', $events, $event_links );
ck( 'and the section\'s only links are the host\'s website, rel sponsored as its entry\'s, and the login screen', $event_links[1], array( 'https://zeta.example/', 'https://example.test/wp-login.php' ) );

/**
 * A full list without its way in, so two viewers' pages can be compared on everything else.
 *
 * @param string $text A full list.
 * @return string
 */
function without_way_in( $text ) {
	return (string) preg_replace( '#<p class="wpcpm-sponsors__join">.*?</p>#', '', (string) $text );
}

// A program manager, signed in: their way in is their own page, pinned below.
$GLOBALS['can']      = true;
$GLOBALS['uid']      = 31;
$GLOBALS['managers'] = array( 31 );
$events_on_manager   = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
$GLOBALS['settings'] = array();
$events_preview      = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
$GLOBALS['can']      = false;
$GLOBALS['uid']      = 0;
$GLOBALS['managers'] = array();
$events_off          = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
ck(
	'the switch decides, not who looks: on, a program manager reads the events as a visitor does, all but the way in, which is their own page (below); off, a program manager\'s preview holds the sponsors and no section, and a visitor sees nothing at all',
	array( without_way_in( $events_on_manager ) === without_way_in( $html ), 0 === strpos( $events_preview, '<p class="wpcpm-sponsors__preview">' ), substr_count( $events_preview, '<li class="wpcpm-sponsors__item' ), strpos( $events_preview, 'wpcpm-sponsors__event' ), strpos( $events_preview, 'Meet the program' ), $events_off, '' !== $events ),
	array( true, true, 10, false, false, '', true )
);

echo "=== The way in ===\n";

// Who looks: nobody (0), then 31 a program manager who is a current student too, 32 a current
// student who is an Active mentor too, 33 an Active mentor, 34 an institution contact, and 35 a
// sponsor's member, whom no reader counts, as none counts a subscriber. The store answers a
// program manager first, then a student, then a mentor, then a contact.
$GLOBALS['audiences'] = array(
	31 => array( 'student' ),
	32 => array( 'student', 'mentor' ),
	33 => array( 'mentor' ),
	34 => array( 'contact' ),
	35 => array(),
);
$way_full  = array();
$way_strip = array();
$way_plain = array();
$way_lines = array();

foreach ( array( 0, 31, 32, 33, 34, 35 ) as $viewer ) {
	$GLOBALS['uid']       = $viewer;
	$GLOBALS['managers']  = 31 === $viewer ? array( 31 ) : array();
	$GLOBALS['can']       = 31 === $viewer;
	$way_full[ $viewer ]  = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
	$way_strip[ $viewer ] = $L::render( array( 'variant' => 'strip' ), '2026-10-06' );
	$way_plain[ $viewer ] = $L::plain( '2026-10-06' );

	preg_match_all( '#<p class="wpcpm-sponsors__join">.*?</p>#', $way_full[ $viewer ], $found );
	$way_lines[ $viewer ] = $found[0];
}

$GLOBALS['uid']      = 0;
$GLOBALS['managers'] = array();
$GLOBALS['can']      = false;

ck(
	'signed out, the way in is the one button, to the login screen, and the page is the one every visitor is served',
	array( $way_lines[0], $way_full[0] === $html ),
	array( array( '<p class="wpcpm-sponsors__join"><a class="wpcpm-button wpcpm-button--secondary" href="https://example.test/wp-login.php">Sign in to join</a></p>' ), true )
);
ck(
	'signed in, each audience is given one link, to the page they join on at the list\'s place there, named in full, the store deciding who is who: a program manager first, then a student, then a mentor, then an institution contact, whose page\'s address is escaped',
	array( $way_lines[31], $way_lines[32], $way_lines[33], $way_lines[34] ),
	array(
		array( '<p class="wpcpm-sponsors__join"><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-events">Join it on your Administrator Dashboard</a></p>' ),
		array( '<p class="wpcpm-sponsors__join"><a href="https://example.test/student-report-card/#wpcpm-sevents">Join it on your Student Report Card</a></p>' ),
		array( '<p class="wpcpm-sponsors__join"><a href="https://example.test/mentor-report-card/#wpcpm-sevents">Join it on your Mentor Report Card</a></p>' ),
		array( '<p class="wpcpm-sponsors__join"><a href="https://example.test/?page_id=41&#038;lang=en#wpcpm-sevents">Join it on your Institution Dashboard</a></p>' ),
	)
);
ck(
	'a sponsor\'s member, whom no reader counts, as none counts a subscriber, is given neither the link nor the button: the section ends with its events',
	array( $way_lines[35], '</ul></section>' === substr( $way_full[35], -strlen( '</ul></section>' ) ) ),
	array( array(), true )
);
ck(
	'nobody signed in is offered the login screen, or a button of any kind',
	array_map(
		static function ( $viewer ) use ( $way_full ) {
			return array( strpos( $way_full[ $viewer ], 'wp-login.php' ), strpos( $way_full[ $viewer ], 'wpcpm-button' ) );
		},
		array( 31, 32, 33, 34, 35 )
	),
	array_fill( 0, 5, array( false, false ) )
);
ck(
	'and for every one of them the rest of the full list is the visitor\'s byte for byte, and the landing page\'s strip and the handbook HTML are the visitor\'s, which are what they were before anyone signed in',
	array_merge(
		array_map(
			static function ( $viewer ) use ( $way_full, $way_strip, $way_plain ) {
				return array( without_way_in( $way_full[ $viewer ] ) === without_way_in( $way_full[0] ), $way_strip[ $viewer ] === $way_strip[0], $way_plain[ $viewer ] === $way_plain[0] );
			},
			array( 31, 32, 33, 34, 35 )
		),
		array( array( $way_strip[0] === $event_strip, $way_plain[0] === $event_plain, strpos( $way_strip[0] . $way_plain[0], 'wpcpm-sponsors__join' ) ) )
	),
	array_merge( array_fill( 0, 5, array( true, true, true ) ), array( array( true, true, false ) ) )
);

$signed_at = strpos( $way_full[32], '<section class="wpcpm-sponsors__events">' );
preg_match_all( '/href="([^"]*)"/', false !== $signed_at ? substr( $way_full[32], $signed_at ) : '', $signed_links );
ck( 'and a signed-in viewer\'s section links to the host\'s website and to their own page alone', $signed_links[1], array( 'https://zeta.example/', 'https://example.test/student-report-card/#wpcpm-sevents' ) );

// A student whose Student Report Card is not published: no page of theirs lists the events, and
// the login screen, to somebody signed in already, would read as a failed login.
$GLOBALS['pages']['student'] = '';
$GLOBALS['uid']              = 32;
$unpublished                 = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
$GLOBALS['pages']['student'] = 'https://example.test/student-report-card/';
$GLOBALS['uid']              = 0;
ck(
	'a signed-in viewer whose page is not published is given no line either, and the rest as the visitor\'s',
	array( strpos( $unpublished, 'wpcpm-sponsors__join' ), strpos( $unpublished, 'wp-login.php' ), without_way_in( $unpublished ) === without_way_in( $html ) ),
	array( false, false, true )
);

echo "=== Upcoming events, sponsor by sponsor ===\n";

// Its own store, like the sections below, so the main fixture's lists keep their order: a Champion
// whose website has no scheme, one with no website, a Partner whose term lapsed before today, and
// an event that started half an hour ago and runs on.
$saved_posts      = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
WPCPM_Sponsors_Index::forget();

add_sponsor( 'spoAAAA0000000022', 'Orbit Labs', 'Approved', array( 'tier' => 'champion', 'website' => 'orbit.example/' ) );
add_sponsor( 'spoAAAA0000000023', 'Plain Co', 'Approved', array( 'tier' => 'champion' ) );
add_sponsor( 'spoAAAA0000000024', 'Faded Co', 'Approved', array( 'tier' => 'partner', 'tier_until' => '2026-09-30', 'website' => 'https://faded.example/' ) );
add_event( 'spoAAAA0000000022', 'approved', '2026-10-09 09:00', 45, 'Orbit webinar', 'https://call.example/secret-room-4242?pwd=abc' );
add_event( 'spoAAAA0000000023', 'approved', '2026-10-10 09:00', 30, 'Plain webinar', 'No website to link.' );
add_event( 'spoAAAA0000000024', 'approved', '2026-10-11 09:00', 30, 'Faded webinar', 'Hidden once the term lapsed.' );
add_event( 'spoAAAA0000000022', 'approved', '2026-10-06 11:30', 60, 'Running now', 'Started half an hour ago.' );

$by_sponsor    = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
$by_sponsor_at = strpos( $by_sponsor, '<ul class="wpcpm-sponsors__list"><li class="wpcpm-sponsors__event">' );
ck(
	'an event already running is listed until it ends, first; a website with no scheme is given https as the sponsor\'s entry gives it; a host with no website is its name alone; a description that was only a link leaves no line',
	false !== $by_sponsor_at ? substr( $by_sponsor, $by_sponsor_at, (int) strpos( $by_sponsor, '</ul>', $by_sponsor_at ) + 5 - $by_sponsor_at ) : '',
	'<ul class="wpcpm-sponsors__list">'
		. '<li class="wpcpm-sponsors__event"><span class="wpcpm-sponsors__event-title">Running now</span><span class="wpcpm-sponsors__line">Hosted by <a href="https://orbit.example/" rel="sponsored noopener">Orbit Labs</a></span><span class="wpcpm-sponsors__line">October 6, 2026 1:30 pm to 2:30 pm (Europe / Warsaw). 60 minutes.</span><span class="wpcpm-sponsors__line">Started half an hour ago.</span></li>'
		. '<li class="wpcpm-sponsors__event"><span class="wpcpm-sponsors__event-title">Orbit webinar</span><span class="wpcpm-sponsors__line">Hosted by <a href="https://orbit.example/" rel="sponsored noopener">Orbit Labs</a></span><span class="wpcpm-sponsors__line">October 9, 2026 11:00 am to 11:45 am (Europe / Warsaw). 45 minutes.</span></li>'
		. '<li class="wpcpm-sponsors__event"><span class="wpcpm-sponsors__event-title">Plain webinar</span><span class="wpcpm-sponsors__line">Hosted by Plain Co</span><span class="wpcpm-sponsors__line">October 10, 2026 11:00 am to 11:30 am (Europe / Warsaw). 30 minutes.</span><span class="wpcpm-sponsors__line">No website to link.</span></li>'
		. '</ul>'
);
ck(
	'a Partner whose term lapsed lists as a Friend, by name, and its event is nowhere, while the events of the two Champions are',
	array( false !== strpos( $by_sponsor, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><span class="wpcpm-sponsors__name">Faded Co</span></li>' ), substr_count( $by_sponsor, '<li class="wpcpm-sponsors__event">' ), strpos( $by_sponsor, 'Faded webinar' ), strpos( $by_sponsor, 'Hidden' ), strpos( $by_sponsor, 'call.example' ) ),
	array( true, 3, false, false, false )
);

$GLOBALS['posts'] = $saved_posts;
WPCPM_Sponsors_Index::forget();

echo "=== The strip ===\n";
$strip = $L::render( array( 'variant' => 'strip' ), '2026-10-06' );
ck( 'the strip opens with its eyebrow and a row of eight items', array(
	0 === strpos( $strip, '<p class="wpcpm-sponsors__eyebrow">Sponsored by</p><ul class="wpcpm-sponsors__list">' ),
	substr_count( $strip, '<li class="wpcpm-sponsors__item' ),
), array( true, 8 ) );
preg_match_all( '/<span class="wpcpm-sponsors__name[^"]*">([^<]+)</', $strip, $m );
ck( 'the row runs in the list\'s alphabetical order, whatever the tier and whether a logo is drawn', $m[1], array( 'Acme Tools', 'Beta Plugins', 'Delta Services', 'Émile Tools', 'Gamma Cloud', 'Hotel Hosting', 'Nova Systems', 'Zeta Hosting' ) );
ck( 'a text-only sponsor whose uppercase scheme is kept as is takes its place by name', false !== strpos( $strip, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><a class="wpcpm-sponsors__link" href="HTTPS://EMILE.EXAMPLE/" rel="sponsored noopener"><span class="wpcpm-sponsors__name">Émile Tools</span></a></li><li class="wpcpm-sponsors__item"><a class="wpcpm-sponsors__link" href="https://gamma.example/"' ), true );
ck( 'a logo in the strip keeps its name for a screen reader alone', false !== strpos( $strip, '<img class="wpcpm-sponsors__logo" src="https://site.example/uploads/zeta.png" alt="" width="300" height="120" loading="lazy" /><span class="wpcpm-sponsors__name screen-reader-text">Zeta Hosting</span></a></li></ul>' ), true );
ck( 'a white-only logo carries its modifier in the strip too, with no line', false !== strpos( $strip, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--white"><a class="wpcpm-sponsors__link" href="https://hotel.example/" rel="sponsored noopener"><img class="wpcpm-sponsors__logo" src="https://site.example/uploads/hotel-white.png" alt="" width="300" height="120" loading="lazy" /><span class="wpcpm-sponsors__name screen-reader-text">Hotel Hosting</span></a></li>' ), true );
ck( 'a gone attachment and no website print the same text item in the strip', false !== strpos( $strip, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><span class="wpcpm-sponsors__name">Nova Systems</span></li>' ), true );
ck( 'no line under a strip logo', strpos( $strip, 'wpcpm-sponsors__line' ), false );
ck( 'and the Friends are a count, with no link while there is no page', false !== strpos( $strip, '<p class="wpcpm-sponsors__more">and 2 companies sponsoring mentors</p>' ), true );

// Friends alone, nothing else in the row: the sentence opens rather than continues, so
// it drops the "and".
$saved_posts      = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
WPCPM_Sponsors_Index::forget();
add_sponsor( 'spoAAAA0000000013', 'Solo Mentor Co', 'Approved', array( 'tier' => 'mentor' ) );
ck( 'the strip with Friends alone drops the "and"', $L::render( array( 'variant' => 'strip' ), '2026-10-06' ), '<p class="wpcpm-sponsors__eyebrow">Sponsored by</p><p class="wpcpm-sponsors__more">1 company sponsoring mentors</p>' );
$GLOBALS['posts'] = $saved_posts;
WPCPM_Sponsors_Index::forget();

// One Friend: the singular.
WPCPM_Sponsors_Index::patch( 'spoAAAA0000000008', array( 'tier' => 'supporter', 'tier_until' => '' ) );
ck( 'one Friend reads in the singular', false !== strpos( $L::render( array( 'variant' => 'strip' ), '2026-10-06' ), 'and 1 company sponsoring mentors' ), true );
WPCPM_Sponsors_Index::patch( 'spoAAAA0000000008', array( 'tier' => 'backer', 'tier_until' => '2026-01-31' ) );
// No sponsor at all: nothing.

$saved_posts      = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
WPCPM_Sponsors_Index::forget();
ck( 'with no sponsor at all, the strip is empty', $L::render( array( 'variant' => 'strip' ), '2026-10-06' ), '' );
ck( 'with no sponsor at all, so is the full list', $L::render( array( 'variant' => 'full' ), '2026-10-06' ), '' );
$GLOBALS['posts'] = $saved_posts;
WPCPM_Sponsors_Index::forget();

echo "=== The block ===\n";
ck( 'the block wraps the strip with the block\'s own classes', 0 === strpos( $L::render_block( array( 'variant' => 'strip' ) ), '<div class="wp-block-wpcpm-sponsors wpcpm-sponsors wpcpm-sponsors--strip"><p class="wpcpm-sponsors__eyebrow">' ), true );
ck( 'an unknown variant is the full list, an unknown floor the ladder\'s floor: every sponsor in the one list, then the upcoming events', array( 0 === strpos( $L::render_block( array( 'variant' => 'wide', 'floor' => 'gold' ) ), '<div class="wp-block-wpcpm-sponsors wpcpm-sponsors wpcpm-sponsors--full"><ul class="wpcpm-sponsors__list">' ), substr_count( $L::render_block( array( 'variant' => 'wide', 'floor' => 'gold' ) ), '<li class="wpcpm-sponsors__item' ), substr_count( $L::render_block( array( 'variant' => 'wide', 'floor' => 'gold' ) ), '<section' ) ), array( true, 10, 1 ) );
$L::register();
ck( 'register() registers the block from its directory with the render callback', array( isset( $GLOBALS['blocks'][0][0] ) && false !== strpos( $GLOBALS['blocks'][0][0], 'blocks/sponsors' ), isset( $GLOBALS['blocks'][0][1]['render_callback'] ) ? $GLOBALS['blocks'][0][1]['render_callback'] : null ), array( true, array( 'WPCPM_Sponsors_Listing', 'render_block' ) ) );

echo "=== The editor script ===\n";
// The block is registered for everyone, since the front end and the block renderer need its render
// callback, and its editor script is a program manager's alone: a sponsor member with posting on
// writes in the block editor, and the block's settings there name the tiers. Without the script an
// editor never registers the block, so its inserter never lists it, and no other block is touched:
// here a paragraph type, registered first with an editor script of its own.
$registry                         = WP_Block_Type_Registry::get_instance();
$paragraph                        = new WP_Block_Type();
$paragraph->name                  = 'core/paragraph';
$paragraph->editor_script_handles = array( 'wp-block-paragraph-editor' );
$registry->register( $paragraph );
$GLOBALS['actions'] = array();
$GLOBALS['filters'] = array();
$L::init();
ck( 'init() registers the block on init and adds no filter, so the blocks every editor offers are left as they were', array( isset( $GLOBALS['actions']['init'] ) ? $GLOBALS['actions']['init'] : array(), $GLOBALS['filters'] ), array( array( array( $L, 'register' ) ), array() ) );
$GLOBALS['can'] = true;
$L::register();
$for_manager = $registry->get_registered( 'wpcpm/sponsors' );
ck( 'for a program manager, register() leaves the block\'s editor script as core registered it', $for_manager instanceof WP_Block_Type ? $for_manager->editor_script_handles : null, array( 'wpcpm-sponsors-editor-script' ) );
$GLOBALS['can'] = false;
$L::register();
$for_others  = $registry->get_registered( 'wpcpm/sponsors' );
$last_block  = end( $GLOBALS['blocks'] );
ck( 'for anyone else, the block stays registered with its render callback and loses its editor script, and every other block keeps its own', array(
	$for_others instanceof WP_Block_Type,
	isset( $last_block[1]['render_callback'] ) ? $last_block[1]['render_callback'] : null,
	$for_others instanceof WP_Block_Type ? $for_others->editor_script_handles : null,
	$registry->get_registered( 'core/paragraph' )->editor_script_handles,
), array( true, array( 'WPCPM_Sponsors_Listing', 'render_block' ), array(), array( 'wp-block-paragraph-editor' ) ) );
$GLOBALS['settings'] = array();
$L::register();
$switch_off = $registry->get_registered( 'wpcpm/sponsors' );
ck( 'the switch off changes none of it: the script is still a program manager\'s alone', $switch_off instanceof WP_Block_Type ? $switch_off->editor_script_handles : null, array() );
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );

echo "=== The handbook HTML ===\n";
$plain = $L::plain( '2026-10-06' );
ck( 'the handbook HTML is the one list in alphabetical order, absolute addresses, no heading, no class', array(
	0 === strpos( $plain, "<ul>\n<li><a href=\"https://acme.example/\"><img src=\"https://site.example/uploads/acme.png\" alt=\"\" width=\"300\" height=\"120\" /> <strong>Acme Tools</strong></a></li>\n" ),
	false !== strpos( $plain, "<li><a href=\"https://beta.example/\"><img src=\"https://site.example/uploads/beta.png\" alt=\"\" width=\"300\" height=\"120\" /> <strong>Beta Plugins</strong></a> - Plugins for a school&#039;s classrooms.</li>\n" ),
	false !== strpos( $plain, "<li><strong>Echo Inc</strong></li>\n<li><a href=\"HTTPS://EMILE.EXAMPLE/\"><strong>Émile Tools</strong></a></li>\n" ),
	false !== strpos( $plain, "<li><a href=\"https://zeta.example/\"><img src=\"https://site.example/uploads/zeta.png\" alt=\"\" width=\"300\" height=\"120\" /> <strong>Zeta Hosting</strong></a> - Hosting for student sites.</li>\n</ul>\n" ),
	substr_count( $plain, '<li>' ),
	strpos( $plain, '<h3' ),
	strpos( $plain, 'class=' ),
	strpos( $plain, 'Design Track' ),
), array( true, true, true, true, 10, false, false, false ) );
ck( 'a white-only logo is a name alone in the handbook HTML, no image - no stylesheet travels with it', false !== strpos( $plain, "<li><a href=\"https://hotel.example/\"><strong>Hotel Hosting</strong></a></li>\n" ), true );
ck( 'the handbook HTML is empty while the switch is off, manager or not', ( function () use ( $L ) { $GLOBALS['settings'] = array(); $GLOBALS['can'] = true; $out = $L::plain( '2026-10-06' ); $GLOBALS['settings'] = array( 'sponsor_tiers_live' => true ); return $out; } )(), '' );

$leak_probe = array(
	strpos( wp_json_encode( $listed ), 'Should Never Appear' ),
	strpos( wp_json_encode( $listed ), 'contact+listing@example.test' ),
	strpos( wp_json_encode( $listed ), '7' ),
	strpos( wp_json_encode( $listed ), 'Manager-only notes' ),
	strpos( $html, 'Should Never Appear' ),
	strpos( $html, 'contact+listing@example.test' ),
	strpos( $html, '7' ),
	strpos( $html, 'Manager-only notes' ),
	strpos( $strip, 'Should Never Appear' ),
	strpos( $strip, 'contact+listing@example.test' ),
	strpos( $strip, '7' ),
	strpos( $strip, 'Manager-only notes' ),
	strpos( $plain, 'Should Never Appear' ),
	strpos( $plain, 'contact+listing@example.test' ),
	strpos( $plain, '7' ),
	strpos( $plain, 'Manager-only notes' ),
);
ck( 'a contact person, an email, a manager id and a note never reach sponsors(), the full list, the strip or the handbook HTML', $leak_probe, array_fill( 0, 16, false ) );

echo "=== The block files ===\n";
$json = json_decode( (string) file_get_contents( __DIR__ . '/../blocks/sponsors/block.json' ), true );
ck( 'block.json names the block, its two attributes and their defaults', array( $json['name'], $json['version'], $json['attributes']['variant']['default'], $json['attributes']['variant']['enum'], $json['attributes']['floor']['default'], $json['supports']['align'], $json['supports']['html'], $json['editorScript'] ), array( 'wpcpm/sponsors', '1.123.18', 'full', array( 'full', 'strip' ), 'mentor', array( 'wide', 'full' ), false, 'file:./editor.js' ) );
// The block lists the partners of the Partners page in one alphabetical list since 1.123.18, so its
// description in the inserter says so, and names no grouping and no ladder.
ck( 'block.json describes the one alphabetical list on the Partners page, and no group', array( $json['description'], preg_match( '/group|ladder|sponsors page/i', $json['description'] ) ), array( 'The program\'s partners, in alphabetical order: the full list for the Partners page, or the strip of logos for the landing page. Prints nothing until the program turns the listing on in Settings.', 0 ) );
// The attribute schema is data every editor user receives, a sponsor member with posting on among
// them, whoever may insert the block: so it lists no value of the floor, which would name the
// tiers. render() guards the floor itself, reading any value that is not a tier as the ladder's.
ck( 'block.json gives the floor no list of values, so the schema every editor receives names no tier', isset( $json['attributes']['floor']['enum'] ), false );
// Core registers in the editor, with no script of its own, a block whose supports ask it to
// (`autoRegister`): this block must not, or the editor script's gate in register() would be moot.
ck( 'block.json does not ask core to register the block in the editor by itself', array_key_exists( 'autoRegister', $json['supports'] ), false );
$asset = include __DIR__ . '/../blocks/sponsors/editor.asset.php';
// WordPress puts this version on the script's URL, so it names the release the script last changed
// in: an editor holding an older copy of the script in its cache then fetches the new one.
ck( 'the asset file declares the six globals the script reads, at the release version', array( $asset['dependencies'], $asset['version'] ), array( array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ), '1.123.18' ) );
$js = (string) file_get_contents( __DIR__ . '/../blocks/sponsors/editor.js' );
ck( 'the editor script registers the block, previews it server-side and stores nothing', array( false !== strpos( $js, "registerBlockType( 'wpcpm/sponsors'" ), false !== strpos( $js, "block: 'wpcpm/sponsors'" ), false !== strpos( $js, 'return null;' ), substr_count( $js, 'SelectControl' ) ), array( true, true, true, 2 ) );
// The preview is empty until the switch is on and a sponsor is Approved: both are needed, so the
// placeholder says "and", and names the setting by the name the Settings screen gives it.
ck( 'the editor names the full shape without a grouping, and its floor the lowest tier shown', array( false !== strpos( $js, "label: __( 'Full list', 'wpcredits-program-manager' ), value: 'full'" ), strpos( $js, 'grouped' ), false !== strpos( $js, "label: __( 'Lowest tier shown', 'wpcredits-program-manager' )" ), strpos( $js, 'Lowest group' ) ), array( true, false, true, false ) );
ck( 'the editor\'s placeholder names the setting and says both things the list waits for', array( false !== strpos( $js, "'The sponsors list shows here once Sponsor tiers is switched on in Settings and there is a sponsor to show.'" ), strpos( $js, 'or when there is a sponsor to show' ) ), array( true, false ) );

echo "=== House rules ===\n";
$src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-listing.php' );
ck( 'the listing reads no contact field and names no base', array( strpos( $src, 'contact_' ), strpos( $src, 'WPCPM_Airtable' ), preg_match( '/\x{2013}|\x{2014}/u', $src ) ), array( false, false, 0 ) );

echo "=== Partner tracks (1.114.0) ===\n";

// Since 1.123.18 the Partners page names no track: the partner page lists every partner in the
// same format, and a Partner is named as program partner on the track it funds, on the track and
// on its students' Student Report Cards (bin/test-track-partners.php), never in this list. Isolated
// from the fixture above: a third Partner sponsor there would shift the strip's fixed row of eight
// and its order, checked earlier by position, so this scenario gets its own empty store, restored
// once it is done - the same way "the strip with Friends alone" and "with no sponsor at all" above
// get theirs.
$saved_posts      = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
WPCPM_Sponsors_Index::forget();

add_sponsor( 'spoAAAA0000000014', 'Juno Robotics', 'Approved', array( 'tier' => 'partner', 'website' => 'https://juno.example/' ) );
add_sponsor( 'spoAAAA0000000015', 'Kappa Cloud', 'Approved', array( 'tier' => 'partner', 'website' => 'https://kappa.example/' ) );
add_sponsor( 'spoAAAA0000000016', 'Lima Analytics', 'Approved', array( 'tier' => 'champion', 'tier_until' => '2027-01-31', 'website' => 'https://lima.example/' ) );

// Two compiled, definition-sourced tracks name Juno as their partner; Kappa is a Partner named
// on none. Lima's key sits on the Developer Track's compiled row too, though its own tier is
// Champion (a term that has not lapsed).
$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array(
	'developer' => array( 'key' => 'developer', 'label' => 'Developer Track', 'source' => 'definition', 'sponsors' => array( 'spoAAAA0000000014', 'spoAAAA0000000016' ), 'post' => 201 ),
	'design'    => array( 'key' => 'design', 'label' => 'Design Track', 'source' => 'definition', 'sponsors' => array( 'spoAAAA0000000014' ), 'post' => 202 ),
);
WPCPM_Tracks::flush();

$track_groups = $L::sponsors( 'mentor', '2026-10-06' );
$track_html   = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
$track_strip  = $L::render( array( 'variant' => 'strip' ), '2026-10-06' );
$track_plain  = $L::plain( '2026-10-06' );

ck( 'a Partner named on two compiled tracks is listed as any sponsor is, its name, with no track and no line', array( wp_list_pluck( $track_groups, 'name' ), array_key_exists( 'tracks', $track_groups[0] ), false !== strpos( $track_html, '<span class="wpcpm-sponsors__name">Juno Robotics</span></a></li>' ) ), array( array( 'Juno Robotics', 'Kappa Cloud', 'Lima Analytics' ), false, true ) );
ck( 'no surface names a track or says Partner of', array( strpos( $track_html . $track_strip . $track_plain, 'Track' ), strpos( $track_html . $track_strip . $track_plain, 'Partner of' ) ), array( false, false ) );
ck( 'plain() prints no suffix for a Partner on a track, nor for a Champion named on a compiled track', array( false !== strpos( $track_plain, 'Juno Robotics</strong></a></li>' ), false !== strpos( $track_plain, 'Lima Analytics</strong></a></li>' ) ), array( true, true ) );
ck( 'the strip prints no line for either sponsor, on a track or not', strpos( $track_strip, 'wpcpm-sponsors__line' ), false );

$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array();
WPCPM_Tracks::flush();
$GLOBALS['posts'] = $saved_posts;
WPCPM_Sponsors_Index::forget();

echo "=== Special characters ===\n";

// A name, a website and a line holding an ampersand, markup and both quotes. The
// application and the profile clean what a sponsor types, but a record migrated from the old base
// or typed by a manager reaches the listing as it is stored, and the three surfaces are public:
// every value leaves escaped, each by the escape its place needs. Its own store, like the Partner
// tracks above, so the main fixture's row of eight keeps its order.
$saved_posts      = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
WPCPM_Sponsors_Index::forget();

add_sponsor( 'spoAAAA0000000017', "Quote & \"Co\" <b>Bold</b> 'Ltd'", 'Approved', array( 'tier' => 'supporter', 'website' => "https://quote.example/?a=1&b=\"2\"<b>x</b>'y'", 'description' => "Tools & \"tips\" <i>here</i> for 'all'" ) );
add_sponsor( 'spoAAAA0000000018', 'Logo & <i>Co</i> "Q"', 'Approved', array( 'tier' => 'backer', 'website' => "quote-logo.example/?x=1&y='2'", 'funded_line' => 'Stipends & "books" <b>now</b>', 'description' => 'Books & "notes" <b>now</b>' ) );
WPCPM_Sponsors_Index::write_logo_record( 'spoAAAA0000000018', array( 'colour' => 21, 'white' => 0, 'source' => 'site', 'airtable_id' => '' ) );
add_sponsor( 'spoAAAA0000000019', 'Mentor & <b>Sons</b> "M"', 'Approved', array( 'tier' => 'mentor' ) );
add_sponsor( 'spoAAAA0000000020', 'Track & Co', 'Approved', array( 'tier' => 'partner', 'website' => 'https://track.example/' ) );
add_event( 'spoAAAA0000000020', 'approved', '2026-10-21 12:00', 60, "Tips & \"tricks\" <b>live</b> 'now'", "Bring <i>laptops</i> & \"notes\" for 'all' of it." );
$GLOBALS['image_src'][21] = array( "https://site.example/uploads/logo.png?ver=1&size='m'", 300, 120 );
$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array(
	'design' => array( 'key' => 'design', 'label' => 'Design & "UX" <b>Track</b>', 'source' => 'definition', 'sponsors' => array( 'spoAAAA0000000020' ), 'post' => 203 ),
);
WPCPM_Tracks::flush();

$special_html  = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
$special_strip = $L::render( array( 'variant' => 'strip' ), '2026-10-06' );
$special_plain = $L::plain( '2026-10-06' );

ck( 'the full list escapes a name in both of an item\'s forms, the website, the logo\'s address and each line', array(
	false !== strpos( $special_html, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><a class="wpcpm-sponsors__link" href="https://quote.example/?a=1&#038;b=2bx/b&#039;y&#039;" rel="sponsored noopener"><span class="wpcpm-sponsors__name">Quote &amp; &quot;Co&quot; &lt;b&gt;Bold&lt;/b&gt; &#039;Ltd&#039;</span></a><span class="wpcpm-sponsors__line">Tools &amp; &quot;tips&quot; &lt;i&gt;here&lt;/i&gt; for &#039;all&#039;</span></li>' ),
	false !== strpos( $special_html, '<li class="wpcpm-sponsors__item"><a class="wpcpm-sponsors__link" href="https://quote-logo.example/?x=1&#038;y=&#039;2&#039;" rel="sponsored noopener"><img class="wpcpm-sponsors__logo" src="https://site.example/uploads/logo.png?ver=1&#038;size=&#039;m&#039;" alt="" width="300" height="120" loading="lazy" /><span class="wpcpm-sponsors__name">Logo &amp; &lt;i&gt;Co&lt;/i&gt; &quot;Q&quot;</span></a><span class="wpcpm-sponsors__line">Books &amp; &quot;notes&quot; &lt;b&gt;now&lt;/b&gt;</span></li>' ),
	false !== strpos( $special_html, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><span class="wpcpm-sponsors__name">Mentor &amp; &lt;b&gt;Sons&lt;/b&gt; &quot;M&quot;</span></li>' ),
	false !== strpos( $special_html, 'Track &amp; Co</span></a></li>' ),
), array( true, true, true, true ) );
ck( 'the strip escapes a name in both of an item\'s forms, the website and the logo\'s address', array(
	false !== strpos( $special_strip, '<li class="wpcpm-sponsors__item wpcpm-sponsors__item--text"><a class="wpcpm-sponsors__link" href="https://quote.example/?a=1&#038;b=2bx/b&#039;y&#039;" rel="sponsored noopener"><span class="wpcpm-sponsors__name">Quote &amp; &quot;Co&quot; &lt;b&gt;Bold&lt;/b&gt; &#039;Ltd&#039;</span></a></li>' ),
	false !== strpos( $special_strip, '<li class="wpcpm-sponsors__item"><a class="wpcpm-sponsors__link" href="https://quote-logo.example/?x=1&#038;y=&#039;2&#039;" rel="sponsored noopener"><img class="wpcpm-sponsors__logo" src="https://site.example/uploads/logo.png?ver=1&#038;size=&#039;m&#039;" alt="" width="300" height="120" loading="lazy" /><span class="wpcpm-sponsors__name screen-reader-text">Logo &amp; &lt;i&gt;Co&lt;/i&gt; &quot;Q&quot;</span></a></li>' ),
), array( true, true ) );
ck( 'the upcoming events escape an event\'s title, its description and its host\'s name, each by the escape its place needs', false !== strpos( $special_html, '<li class="wpcpm-sponsors__event"><span class="wpcpm-sponsors__event-title">Tips &amp; &quot;tricks&quot; &lt;b&gt;live&lt;/b&gt; &#039;now&#039;</span><span class="wpcpm-sponsors__line">Hosted by <a href="https://track.example/" rel="sponsored noopener">Track &amp; Co</a></span><span class="wpcpm-sponsors__line">October 21, 2026 2:00 pm to 3:00 pm (Europe / Warsaw). 60 minutes.</span><span class="wpcpm-sponsors__line">Bring &lt;i&gt;laptops&lt;/i&gt; &amp; &quot;notes&quot; for &#039;all&#039; of it.</span></li>' ), true );
ck( 'the handbook HTML escapes a name, the website, the logo\'s address, a line and a Friend\'s name', array(
	false !== strpos( $special_plain, "<li><a href=\"https://quote.example/?a=1&#038;b=2bx/b&#039;y&#039;\"><strong>Quote &amp; &quot;Co&quot; &lt;b&gt;Bold&lt;/b&gt; &#039;Ltd&#039;</strong></a> - Tools &amp; &quot;tips&quot; &lt;i&gt;here&lt;/i&gt; for &#039;all&#039;</li>\n" ),
	false !== strpos( $special_plain, "<li><a href=\"https://quote-logo.example/?x=1&#038;y=&#039;2&#039;\"><img src=\"https://site.example/uploads/logo.png?ver=1&#038;size=&#039;m&#039;\" alt=\"\" width=\"300\" height=\"120\" /> <strong>Logo &amp; &lt;i&gt;Co&lt;/i&gt; &quot;Q&quot;</strong></a> - Books &amp; &quot;notes&quot; &lt;b&gt;now&lt;/b&gt;</li>\n" ),
	false !== strpos( $special_plain, "<strong>Track &amp; Co</strong></a></li>\n" ),
	false !== strpos( $special_plain, "<li><strong>Mentor &amp; &lt;b&gt;Sons&lt;/b&gt; &quot;M&quot;</strong></li>\n" ),
), array( true, true, true, true ) );
// Whatever the exact markup around them, none of the stored markup or quotes reaches a surface raw.
$special_raw = array();
foreach ( array( 'full' => $special_html, 'strip' => $special_strip, 'handbook' => $special_plain ) as $surface => $text ) {
	foreach ( array( '<b>', '<i>', '"Co"', "'Ltd'", '"2"', "'2'", "'m'", '"UX"', '"M"', '"tricks"', "'now'", '"notes"', 'Stipends', 'Partner of' ) as $raw ) {
		if ( false !== strpos( $text, $raw ) ) {
			$special_raw[] = $surface . ': ' . $raw;
		}
	}
}
ck( 'and no stored markup or quote reaches the full list, the strip or the handbook HTML unescaped', $special_raw, array() );
// The stand-in answers as core does for a scheme core does not allow: no website reaches one today,
// since a website with no http or https is given https, but a fixture that ever does meets core.
ck( 'the esc_url() stand-in empties an address whose scheme core does not allow, and keeps the ones it allows, as core does', array(
	esc_url( 'javascript:alert(1)' ),
	esc_url( 'JavaScript:alert(1)' ),
	esc_url( 'data:text/html;base64,PHA+' ),
	esc_url( 'https://ok.example/?a=1&b=2' ),
	esc_url( 'mailto:events@example.test' ),
), array( '', '', '', 'https://ok.example/?a=1&#038;b=2', 'mailto:events@example.test' ) );

unset( $GLOBALS['image_src'][21] );
$GLOBALS['opts'][ WPCPM_Tracks::OPT_TRACKS ] = array();
WPCPM_Tracks::flush();
$GLOBALS['posts'] = $saved_posts;
WPCPM_Sponsors_Index::forget();

echo "=== Every private field, on every tier ===\n";

// What the listing may print of a row: its name, slug, website and tier, and the description of
// every tier from Supporter up. The row's key never, nor its funded line since 1.123.18, and
// nothing else a row holds: the fields below are derived from empty_row() itself, so a key added
// to the row later is walked without anyone remembering to. One row per tier, each with its own
// line empty, so a line that fell back to another field for want of its own would print that
// field here; a description is private to a Friend, the one tier the listing prints it for none
// of. Its own store, like the sections above.
$saved_posts      = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
WPCPM_Sponsors_Index::forget();

$walk_public = array( 'record_id', 'slug', 'name', 'website', 'status', 'tier' );

/**
 * One walk row's private fields: a sentinel for every key of empty_row() outside the public set,
 * built from the key's own default. A flag holds no words to look for, so it is left to the check
 * that turns every flag over; a term owed its report and the agreement hold one per inner key, and
 * the extra events a term was granted one in their day, beside a count, which holds no words.
 *
 * @param string $tier   The row's tier.
 * @param array  $public The keys the listing may print.
 * @return array
 */
function walk_fields( $tier, array $public ) {
	$fields = array();

	foreach ( WPCPM_Sponsors_Index::empty_row() as $key => $default ) {
		if ( in_array( $key, $public, true ) || is_bool( $default ) ) {
			continue;
		}

		$mark = 'Walk' . ucfirst( $tier ) . '-' . $key;

		if ( 'description' === $key && WPCPM_Sponsor_Tiers::includes( $tier, 'description' ) ) {
			$fields[ $key ] = '';
		} elseif ( 'report_owed' === $key ) {
			$fields[ $key ] = array(
				'tier'        => $mark . '-tier',
				'until'       => $mark . '-until',
				'funded_line' => $mark . '-funded_line',
			);
		} elseif ( 'event_allowance' === $key ) {
			$fields[ $key ] = array(
				'until' => $mark . '-until',
				'extra' => 3,
			);
		} elseif ( is_array( $default ) && ! empty( $default ) ) {
			$fields[ $key ] = array();

			foreach ( $default as $inner => $inner_default ) {
				$fields[ $key ][ $inner ] = is_bool( $inner_default ) ? true : $mark . '-' . $inner;
			}
		} elseif ( is_array( $default ) ) {
			$fields[ $key ] = array( $mark );
		} else {
			$fields[ $key ] = $mark;
		}
	}

	return $fields;
}

/**
 * Every sentinel a value holds, however deep.
 *
 * @param mixed $value A field's value.
 * @return string[]
 */
function walk_marks( $value ) {
	if ( is_array( $value ) ) {
		return array_merge( array(), ...array_map( 'walk_marks', array_values( $value ) ) );
	}

	return is_string( $value ) && 0 === strpos( $value, 'Walk' ) ? array( $value ) : array();
}

$walk_marks = array();
$walk_flags = array();
foreach ( WPCPM_Sponsor_Tiers::KEYS as $walk_n => $walk_tier ) {
	$walk_key = 'spoWALK000000000' . ( $walk_n + 1 );
	$fields   = walk_fields( $walk_tier, $walk_public );

	// Every flag on, so the check after the probe can turn them all off and compare: all but the
	// test sponsor's, which decides whether a row is listed at all and has checks of its own below.
	foreach ( WPCPM_Sponsors_Index::empty_row() as $key => $default ) {
		if ( is_bool( $default ) && 'test' !== $key ) {
			$fields[ $key ]     = true;
			$walk_flags[ $key ] = true;
		}
	}

	add_sponsor( $walk_key, 'Walk ' . WPCPM_Sponsor_Tiers::label( $walk_tier ), 'Approved', array_merge( $fields, array( 'tier' => $walk_tier, 'website' => 'https://walk-' . $walk_tier . '.example/' ) ) );
	WPCPM_Sponsors_Index::write_logo_record( $walk_key, array( 'colour' => 30 + $walk_n, 'white' => 0, 'source' => 'Walk' . ucfirst( $walk_tier ) . '-logo-source', 'airtable_id' => 'Walk' . ucfirst( $walk_tier ) . '-logo-airtable_id' ) );
	$GLOBALS['image_src'][ 30 + $walk_n ] = array( 'https://site.example/uploads/walk-' . $walk_tier . '.png', 300, 120 );

	$walk_marks = array_merge( $walk_marks, walk_marks( $fields ), array( $walk_key, 'Walk' . ucfirst( $walk_tier ) . '-logo-source', 'Walk' . ucfirst( $walk_tier ) . '-logo-airtable_id' ) );
}

// Five rows of more than twenty private fields each, every one read back from the store: a walk
// that planted next to nothing, or that the row's shape quietly dropped, would pass the probe
// below without proving anything.
$walk_planted = wp_json_encode( WPCPM_Sponsors_Index::approved() ) . wp_json_encode( array_map( array( 'WPCPM_Sponsors_Index', 'logo_record' ), array_keys( WPCPM_Sponsors_Index::approved() ) ) );
ck( 'the walk plants a sentinel in every private field of a row at every tier, and the store keeps each one', array(
	count( $walk_marks ) > 5 * 20,
	array_values( array_filter( $walk_marks, static function ( $mark ) use ( $walk_planted ) { return false === strpos( $walk_planted, $mark ); } ) ),
), array( true, array() ) );

$walk_surfaces = array(
	'sponsors()' => wp_json_encode( $L::sponsors( 'mentor', '2026-10-06' ) ),
	'full'     => $L::render( array( 'variant' => 'full' ), '2026-10-06' ),
	'strip'    => $L::render( array( 'variant' => 'strip' ), '2026-10-06' ),
	'handbook' => $L::plain( '2026-10-06' ),
);
ck( 'every walk row is drawn: the five in the full list and the handbook HTML, the four from Supporter up in the strip and the Friend as its count', array(
	substr_count( $walk_surfaces['full'], '>Walk ' ),
	substr_count( $walk_surfaces['handbook'], 'Walk ' ),
	substr_count( $walk_surfaces['strip'], '>Walk ' ),
	false !== strpos( $walk_surfaces['strip'], 'and 1 company sponsoring mentors' ),
), array( 5, 5, 4, true ) );

$walk_found = array();
foreach ( $walk_surfaces as $surface => $text ) {
	foreach ( $walk_marks as $mark ) {
		if ( false !== strpos( $text, $mark ) ) {
			$walk_found[] = $surface . ': ' . $mark;
		}
	}
}
ck( 'no private field of any tier reaches sponsors(), the full list, the strip or the handbook HTML: a description only from Supporter up, the funded line and the key never', $walk_found, array() );

// A flag holds no words to look for, so every one, the agreement's own among them, is turned off
// instead: what the listing draws must not change by a byte.
foreach ( WPCPM_Sponsor_Tiers::KEYS as $walk_n => $walk_tier ) {
	$walk_key = 'spoWALK000000000' . ( $walk_n + 1 );
	$off      = array();

	foreach ( WPCPM_Sponsors_Index::empty_row() as $key => $default ) {
		if ( is_bool( $default ) ) {
			$off[ $key ] = false;
		} elseif ( is_array( $default ) && ! empty( $default ) ) {
			$off[ $key ] = WPCPM_Sponsors_Index::row( $walk_key )[ $key ];

			foreach ( $default as $inner => $inner_default ) {
				if ( is_bool( $inner_default ) ) {
					$off[ $key ][ $inner ]             = false;
					$walk_flags[ $key . '.' . $inner ] = true;
				}
			}
		}
	}

	WPCPM_Sponsors_Index::patch( $walk_key, $off );
}
ck( 'and every flag a row holds, turned off, changes nothing any of them draws', array(
	count( $walk_flags ) > 0,
	wp_json_encode( $L::sponsors( 'mentor', '2026-10-06' ) ) === $walk_surfaces['sponsors()'],
	$L::render( array( 'variant' => 'full' ), '2026-10-06' ) === $walk_surfaces['full'],
	$L::render( array( 'variant' => 'strip' ), '2026-10-06' ) === $walk_surfaces['strip'],
	$L::plain( '2026-10-06' ) === $walk_surfaces['handbook'],
), array( true, true, true, true, true ) );

foreach ( array_keys( WPCPM_Sponsor_Tiers::KEYS ) as $walk_n ) {
	unset( $GLOBALS['image_src'][ 30 + $walk_n ] );
}
$GLOBALS['posts'] = $saved_posts;
WPCPM_Sponsors_Index::forget();

echo "\n=== A test sponsor is never listed (1.123.18) ===\n";

// A test sponsor is left out of the one reader every listing draws from: the full list on the
// Partners page, the strip on the hub and the front page, its count of the companies sponsoring
// mentors, and the handbook listing. Its own Approved status and tier change nothing.
$saved_posts      = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
WPCPM_Sponsors_Index::forget();
$GLOBALS['settings'] = array( 'sponsor_tiers_live' => true );
add_sponsor( 'spoTESTLIST000001', 'Real Supporter Co', 'Approved', array( 'tier' => 'supporter' ) );
add_sponsor( 'spoTESTLIST000002', 'TEST - WordPress Education Dashboard (do not use)', 'Approved', array( 'tier' => 'supporter', 'test' => true ) );
add_sponsor( 'spoTESTLIST000003', 'Real Friend', 'Approved' );
add_sponsor( 'spoTESTLIST000004', 'A Friend under test', 'Approved', array( 'test' => true ) );
$t_full  = $L::render( array( 'variant' => 'full' ), '2026-10-06' );
$t_strip = $L::render( array( 'variant' => 'strip' ), '2026-10-06' );
$t_plain = $L::plain( '2026-10-06' );
ck( 'a test sponsor is in no listing: not in sponsors(), the full list, the strip or the handbook HTML, at any tier', array(
	array_column( $L::sponsors( 'mentor', '2026-10-06' ), 'name' ),
	strpos( $t_full . $t_strip . $t_plain, 'TEST - ' ),
	strpos( $t_full . $t_strip . $t_plain, 'under test' ),
	false !== strpos( $t_full, 'Real Supporter Co' ) && false !== strpos( $t_full, 'Real Friend' ),
), array( array( 'Real Friend', 'Real Supporter Co' ), false, false, true ) );
ck( 'and the strip counts the companies sponsoring mentors without it', array( false !== strpos( $t_strip, 'and 1 company sponsoring mentors' ), strpos( $t_strip, '2 companies' ) ), array( true, false ) );
$GLOBALS['posts'] = $saved_posts;
WPCPM_Sponsors_Index::forget();

printf( "\n%s (%d checks)\n", $fails ? "$fails FAILED" : 'ALL PASS', (int) $total );
exit( $fails ? 1 : 0 );
