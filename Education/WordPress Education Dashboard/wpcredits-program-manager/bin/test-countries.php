<?php
/**
 * Does the Countries map route an institution to the right program manager, and stay put
 * when a read fails?
 *
 * The map is what the applicant acknowledgement embeds a Calendly link from, what the
 * pipeline prints a country's name from, and what the manager screen lists routing gaps
 * from. Three things about it are easy to get silently wrong: a failed or empty read
 * replacing a good map with nothing (every country on the pipeline goes blank, no error);
 * a misspelled field name, which Airtable answers with records carrying no fields rather
 * than a 422; and a lookup cell, which arrives as an array even when it holds one value.
 * Each is pinned here, and so is the seed fixture's arithmetic: 196 countries, 58 with no
 * contact, and every one an institution names resolving to a name.
 *
 * Nothing here touches the network: the client is the real `WPCPM_Airtable` with
 * `fetch_all()` replaced by a queue of canned answers, so `flatten()` is the real one. The
 * manager values are synthetic - the seed fixture carries none, on purpose.
 *
 * Run from the plugin root:  php bin/test-countries.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();
$GLOBALS['settings'] = array();
$GLOBALS['queue']    = array();
$GLOBALS['reads']    = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
/**
 * Strips what the real one strips, so a junk address does not become a valid-looking one
 * by accident here and get asserted as kept.
 */
function sanitize_email( $e ) {
	$e = (string) $e;

	if ( strlen( $e ) < 3 || false === strpos( $e, '@', 1 ) || substr_count( $e, '@' ) > 1 ) {
		return '';
	}

	list( $local, $domain ) = explode( '@', $e, 2 );

	$local  = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.\[\]-]/', '', $local );
	$domain = preg_replace( '/[^a-zA-Z0-9\.\-]/', '', $domain );

	return ( '' === $local || '' === $domain ) ? '' : $local . '@' . $domain;
}
function is_email( $e ) { return false !== filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
/**
 * Honours the protocol allowlist, as the real one does.
 *
 * A pass-through stub would make the "a javascript: booking link is refused" assertion
 * pass no matter what the class did.
 */
function esc_url_raw( $u, $protocols = null ) {
	$u = trim( (string) $u );

	if ( '' === $u ) {
		return '';
	}

	$scheme = strtolower( (string) parse_url( $u, PHP_URL_SCHEME ) );

	if ( null === $protocols ) {
		$protocols = array( 'http', 'https', 'mailto' );
	}

	return in_array( $scheme, (array) $protocols, true ) ? $u : '';
}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $a; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

/* ---- the other pieces, to their contracts ------------------------------- */

if ( ! class_exists( 'WPCPM_Settings' ) ) {
	class WPCPM_Settings {
		public static function get() { return $GLOBALS['settings']; }
	}
}

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
		}
	}
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';

/**
 * The real client with the one method that would reach the network replaced.
 *
 * Hands out canned answers in order and remembers what was asked for, so the suite can
 * assert the table and the exact field list. An empty queue is a mistake in the test,
 * not in the code, so it throws rather than inventing an answer.
 */
class WPCPM_Countries_Test_Airtable extends WPCPM_Airtable {
	public function fetch_all( $table, array $args = array() ) {
		$GLOBALS['reads'][] = array( 'table' => $table, 'args' => $args );

		if ( empty( $GLOBALS['queue'] ) ) {
			throw new Exception( 'fetch_all() called with nothing queued: ' . $table );
		}

		return array_shift( $GLOBALS['queue'] );
	}
}

$fail = 0;
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * A fixture, decoded, or an empty array when the file is missing or not JSON.
 *
 * @param string $name Basename under bin/fixtures/.
 * @return array
 */
function fixture( $name ) {
	$path = __DIR__ . '/fixtures/' . $name;

	if ( ! is_file( $path ) ) {
		return array();
	}

	$data = json_decode( file_get_contents( $path ), true );

	return is_array( $data ) ? $data : array();
}

/**
 * One Airtable record, in the shape `fetch_all()` returns.
 *
 * @param string $id     Record ID.
 * @param array  $fields Cells, as the REST API would send them.
 * @return array
 */
function rec( $id, array $fields ) {
	return array( 'id' => $id, 'createdTime' => '2026-01-01T00:00:00.000Z', 'fields' => $fields );
}

$configured = array(
	'api_token'            => 'pat-test',
	'base_id'              => 'appTESTTESTTESTTE',
	'countries_table'      => 'tbltB7GSRoTtSi4Ps',
	'countries_name_field' => 'Name',
);

$GLOBALS['settings'] = $configured;

$client = new WPCPM_Countries_Test_Airtable( $configured );

$contact  = WPCPM_Countries::FIELD_CONTACT;
$email    = WPCPM_Countries::FIELD_EMAIL;
$calendly = WPCPM_Countries::FIELD_CALENDLY;

$empty_shape = array( 'v' => 1, 'read' => 0, 'rows' => array() );

/* ---- the stored shape --------------------------------------------------- */

echo "=== The stored shape ===\n";

ck( 'the option name is the one the spec lists', WPCPM_Countries::OPT_NAME, 'wpcpm_countries' );
ck( 'and the shape is version 1', WPCPM_Countries::VERSION, 1 );
ck( 'the three columns are spelled as the base spells them',
    array( $contact, $email, $calendly ),
    array( 'Person of contact (Team)', 'Email (from Person of contact (Team))', 'Calendly link (from Person of contact (Team))' ) );

ck( 'read() is the empty shape when nothing is stored', WPCPM_Countries::read(), $empty_shape );
ck( 'all() is empty', WPCPM_Countries::all(), array() );
ck( 'name_of() is empty for any ID', WPCPM_Countries::name_of( 'recAAAAAAAAAAAAAA' ), '' );
ck( 'routing() is null', WPCPM_Countries::routing( 'recAAAAAAAAAAAAAA' ), null );
ck( 'contact_of() is empty', WPCPM_Countries::contact_of( 'recAAAAAAAAAAAAAA' ), '' );
ck( 'gaps() is empty, not an error', WPCPM_Countries::gaps(), array() );
ck( 'options() is empty', WPCPM_Countries::options(), array() );

$GLOBALS['opts']['wpcpm_countries'] = array( 'v' => 0, 'read' => 5, 'rows' => array( 'recAAAAAAAAAAAAAA' => array( 'name' => 'Old', 'manager' => '', 'email' => '', 'calendly' => '' ) ) );
ck( 'a map written by another version is discarded on read', WPCPM_Countries::read(), $empty_shape );
ck( 'so its rows are not shown', WPCPM_Countries::name_of( 'recAAAAAAAAAAAAAA' ), '' );

$GLOBALS['opts']['wpcpm_countries'] = 'not an array';
ck( 'and so is a stored value that is not an array', WPCPM_Countries::read(), $empty_shape );

$GLOBALS['opts']['wpcpm_countries'] = array( 'v' => 1 );
ck( 'a versioned option with no rows reads as no rows', WPCPM_Countries::read(), $empty_shape );

unset( $GLOBALS['opts']['wpcpm_countries'] );

/* ---- refresh() builds the map ------------------------------------------- */

echo "\n=== refresh() builds the map from what the table returns ===\n";

$GLOBALS['queue'][] = array(
	// Every cell present, the lookups as arrays, the address in mixed case.
	rec( 'recAAAAAAAAAAAAAA', array(
		'Name'    => 'Turkey',
		$contact  => array( 'recTEAM0000000001' ),
		$email    => array( 'Manager.One@Example.test' ),
		$calendly => array( 'https://calendly.example.test/manager-one' ),
	) ),
	// A name and nothing else: a routing gap.
	rec( 'recBBBBBBBBBBBBBB', array( 'Name' => 'Singapore' ) ),
	// A contact whose Team row has no address or link yet: a contact, not a gap.
	rec( 'recCCCCCCCCCCCCCC', array( 'Name' => 'Chile', $contact => array( 'recTEAM0000000002' ) ) ),
	// Values that must not survive: a javascript: link and an address that is not one.
	rec( 'recDDDDDDDDDDDDDD', array(
		'Name'    => 'Denmark',
		$contact  => array( 'recTEAM0000000003' ),
		$email    => array( 'not an address' ),
		$calendly => array( 'javascript:alert(1)' ),
	) ),
	// Markup and padding in a name.
	rec( 'recEEEEEEEEEEEEEE', array( 'Name' => '  <b>Zimbabwe</b> ' ) ),
	// The shape a lookup of a collaborator field takes: objects with a name.
	rec( 'recFFFFFFFFFFFFFF', array(
		'Name'    => 'Finland',
		$contact  => array( array( 'id' => 'usr1', 'name' => 'Manager Two', 'email' => 'two@example.test' ) ),
		$email    => array( 'two@example.test' ),
		$calendly => array( 'https://calendly.example.test/manager-two' ),
	) ),
	// Two contacts linked: the first routes.
	rec( 'recGGGGGGGGGGGGGG', array(
		'Name'    => 'Ghana',
		$contact  => array( 'recTEAM0000000004', 'recTEAM0000000005' ),
		$email    => array( 'four@example.test', 'five@example.test' ),
		$calendly => array( 'https://calendly.example.test/manager-four', 'https://calendly.example.test/manager-five' ),
	) ),
	// Not a record ID: skipped.
	rec( 'not-a-record', array( 'Name' => 'Nowhere' ) ),
	// No name: skipped rather than stored blank.
	rec( 'recHHHHHHHHHHHHHH', array( $contact => array( 'recTEAM0000000006' ) ) ),
);

$result = WPCPM_Countries::refresh( $client );

ck( 'refresh() returns true', $result, true );
ck( 'one read was made', count( $GLOBALS['reads'] ), 1 );
ck( 'of the table the setting names', $GLOBALS['reads'][0]['table'], 'tbltB7GSRoTtSi4Ps' );
ck( 'asking for exactly the four columns, the name first',
    $GLOBALS['reads'][0]['args'],
    array( 'fields' => array( 'Name', $contact, $email, $calendly ) ) );
ck( 'the option is written without autoload', $GLOBALS['autoload']['wpcpm_countries'], false );

$stored = WPCPM_Countries::read();

ck( 'the stored map is version 1', $stored['v'], 1 );
ck( 'and records when it was read', $stored['read'] > 0, true );
ck( 'seven countries survived', count( WPCPM_Countries::all() ), 7 );
ck( 'the two unusable records did not',
    array( isset( $stored['rows']['not-a-record'] ), isset( $stored['rows']['recHHHHHHHHHHHHHH'] ) ),
    array( false, false ) );

ck( 'a full row: name, the Team record ID, the address lowercased, the link',
    $stored['rows']['recAAAAAAAAAAAAAA'],
    array( 'name' => 'Turkey', 'manager' => 'recTEAM0000000001', 'email' => 'manager.one@example.test', 'calendly' => 'https://calendly.example.test/manager-one' ) );
ck( 'a name-only row has the same four keys, empty',
    $stored['rows']['recBBBBBBBBBBBBBB'],
    array( 'name' => 'Singapore', 'manager' => '', 'email' => '', 'calendly' => '' ) );
ck( 'a bad address and a javascript: link are dropped, the contact is kept',
    $stored['rows']['recDDDDDDDDDDDDDD'],
    array( 'name' => 'Denmark', 'manager' => 'recTEAM0000000003', 'email' => '', 'calendly' => '' ) );
ck( 'markup is stripped from a name and the padding trimmed', $stored['rows']['recEEEEEEEEEEEEEE']['name'], 'Zimbabwe' );
ck( 'a collaborator-shaped contact stores its name', $stored['rows']['recFFFFFFFFFFFFFF']['manager'], 'Manager Two' );
ck( 'with two contacts linked, the first one routes',
    $stored['rows']['recGGGGGGGGGGGGGG'],
    array( 'name' => 'Ghana', 'manager' => 'recTEAM0000000004', 'email' => 'four@example.test', 'calendly' => 'https://calendly.example.test/manager-four' ) );

foreach ( $stored['rows'] as $row ) {
	ck( 'every row holds exactly the four keys, in order', array_keys( $row ), array( 'name', 'manager', 'email', 'calendly' ) );
	break;
}

/* ---- the readers -------------------------------------------------------- */

echo "\n=== name_of(), routing(), contact_of(), gaps(), options() ===\n";

ck( 'name_of() resolves a country with a contact', WPCPM_Countries::name_of( 'recAAAAAAAAAAAAAA' ), 'Turkey' );
ck( 'and one without', WPCPM_Countries::name_of( 'recBBBBBBBBBBBBBB' ), 'Singapore' );
ck( 'and is empty for an unknown ID', WPCPM_Countries::name_of( 'recZZZZZZZZZZZZZZ' ), '' );
ck( 'and for a value that is not a record ID', WPCPM_Countries::name_of( 'Turkey' ), '' );
ck( 'and tolerates padding around the ID', WPCPM_Countries::name_of( ' recAAAAAAAAAAAAAA ' ), 'Turkey' );

ck( 'routing() hands back the row for a country with a contact',
    WPCPM_Countries::routing( 'recAAAAAAAAAAAAAA' ),
    array( 'name' => 'Turkey', 'manager' => 'recTEAM0000000001', 'email' => 'manager.one@example.test', 'calendly' => 'https://calendly.example.test/manager-one' ) );
ck( 'routing() is null for a country with nobody to route to', WPCPM_Countries::routing( 'recBBBBBBBBBBBBBB' ), null );
ck( 'even though name_of() still resolves it', WPCPM_Countries::name_of( 'recBBBBBBBBBBBBBB' ), 'Singapore' );
ck( 'routing() is null for an unknown ID', WPCPM_Countries::routing( 'recZZZZZZZZZZZZZZ' ), null );
ck( 'a contact with no address yet still routes', is_array( WPCPM_Countries::routing( 'recCCCCCCCCCCCCCC' ) ), true );

ck( 'contact_of() prints the address when the contact is a record ID', WPCPM_Countries::contact_of( 'recAAAAAAAAAAAAAA' ), 'manager.one@example.test' );
ck( 'and the name when the base supplied one', WPCPM_Countries::contact_of( 'recFFFFFFFFFFFFFF' ), 'Manager Two' );
ck( 'and nothing when there is neither', WPCPM_Countries::contact_of( 'recCCCCCCCCCCCCCC' ), '' );
ck( 'never a record ID', WPCPM_Mentors_Sync::is_record_id( WPCPM_Countries::contact_of( 'recAAAAAAAAAAAAAA' ) ), false );

ck( 'gaps() lists the two countries with no contact, keyed by record ID and in name order',
    array_keys( WPCPM_Countries::gaps() ),
    array( 'recBBBBBBBBBBBBBB', 'recEEEEEEEEEEEEEE' ) );
ck( 'options() lists every country by name, sorted',
    WPCPM_Countries::options(),
    array(
        'recCCCCCCCCCCCCCC' => 'Chile',
        'recDDDDDDDDDDDDDD' => 'Denmark',
        'recFFFFFFFFFFFFFF' => 'Finland',
        'recGGGGGGGGGGGGGG' => 'Ghana',
        'recBBBBBBBBBBBBBB' => 'Singapore',
        'recAAAAAAAAAAAAAA' => 'Turkey',
        'recEEEEEEEEEEEEEE' => 'Zimbabwe',
    ) );

/* ---- the name column comes from the setting ------------------------------ */

echo "\n=== The name column comes from the setting ===\n";

$GLOBALS['settings']['countries_name_field'] = 'Country';
$GLOBALS['reads'] = array();
$GLOBALS['queue'][] = array( rec( 'recAAAAAAAAAAAAAA', array( 'Country' => 'Turkey' ) ) );

ck( 'a renamed column is read', WPCPM_Countries::refresh( $client ), true );
ck( 'and requested in place of Name', $GLOBALS['reads'][0]['args']['fields'][0], 'Country' );

$GLOBALS['settings']['countries_name_field'] = '';
$GLOBALS['reads'] = array();
$GLOBALS['queue'][] = array( rec( 'recAAAAAAAAAAAAAA', array( 'Name' => 'Turkey' ) ) );

ck( 'an empty setting falls back to Name', WPCPM_Countries::refresh( $client ), true );
ck( 'in the request', $GLOBALS['reads'][0]['args']['fields'][0], 'Name' );

$GLOBALS['settings'] = $configured;

/* ---- a failed read keeps the last good copy ----------------------------- */

echo "\n=== A failed read keeps the last good copy ===\n";

$GLOBALS['queue'][] = array( rec( 'recAAAAAAAAAAAAAA', array( 'Name' => 'Turkey', $contact => array( 'recTEAM0000000001' ) ) ) );
WPCPM_Countries::refresh( $client );
$before = $GLOBALS['opts']['wpcpm_countries'];

ck( 'a good map is in place', WPCPM_Countries::name_of( 'recAAAAAAAAAAAAAA' ), 'Turkey' );

$GLOBALS['queue'][] = new WP_Error( 'wpcpm_rate_limited', 'Airtable asked for a pause.' );
$result = WPCPM_Countries::refresh( $client );

ck( 'a WP_Error from the client is handed back as it is', is_wp_error( $result ) ? $result->get_error_code() : $result, 'wpcpm_rate_limited' );
ck( 'and the stored map is untouched', $GLOBALS['opts']['wpcpm_countries'], $before );

$GLOBALS['queue'][] = array();
$result = WPCPM_Countries::refresh( $client );

ck( 'a read that returns no records is an error, not an empty map', is_wp_error( $result ) ? $result->get_error_code() : $result, 'wpcpm_countries_empty' );
ck( 'and the stored map is untouched', $GLOBALS['opts']['wpcpm_countries'], $before );

// What Airtable answers when a requested field name does not exist: records with no
// fields at all. Without the check this would be "196 countries, none of them named".
$GLOBALS['queue'][] = array( array( 'id' => 'recAAAAAAAAAAAAAA' ), array( 'id' => 'recBBBBBBBBBBBBBB', 'fields' => array() ) );
$result = WPCPM_Countries::refresh( $client );

ck( 'records with no names are a distinguishable error', is_wp_error( $result ) ? $result->get_error_code() : $result, 'wpcpm_countries_no_names' );
ck( 'and the stored map is untouched', $GLOBALS['opts']['wpcpm_countries'], $before );

$GLOBALS['reads'] = array();
$GLOBALS['settings']['countries_table'] = '';
$result = WPCPM_Countries::refresh( $client );

ck( 'no table configured is refused before any read', is_wp_error( $result ) ? $result->get_error_code() : $result, 'wpcpm_countries_no_table' );
ck( 'with nothing asked of the client', $GLOBALS['reads'], array() );
ck( 'and the stored map untouched', $GLOBALS['opts']['wpcpm_countries'], $before );

$GLOBALS['settings'] = $configured;

// No client given: one is built from the settings. With no token the real client's own
// guard answers, which is how the suite knows the real class was constructed.
$GLOBALS['settings']['api_token'] = '';
$result = WPCPM_Countries::refresh();

ck( 'with no client given, one is built from the settings and its guard answers', is_wp_error( $result ) ? $result->get_error_code() : $result, 'wpcpm_no_token' );
ck( 'and the stored map is untouched', $GLOBALS['opts']['wpcpm_countries'], $before );

$GLOBALS['settings'] = $configured;

/* ---- the seed fixture --------------------------------------------------- */

echo "\n=== The seed fixture: 196 countries, 58 with no contact, every named one resolves ===\n";

$seed      = fixture( 'institutions-index-seed.json' );
$countries = isset( $seed['countries'] ) ? $seed['countries'] : array();
$counts    = isset( $seed['counts'] ) ? $seed['counts'] : array();

ck( 'the fixture loaded', isset( $seed['countries_table'] ), true );
ck( 'and describes the table the setting names', isset( $seed['countries_table'] ) ? $seed['countries_table'] : null, $configured['countries_table'] );

// The fixture carries only whether a contact, an address and a link exist. The values
// are made up here, per country, and never resemble anyone's.
$records = array();
$n       = 0;

foreach ( $countries as $country ) {
	$fields = array( 'Name' => $country['name'] );
	$n++;

	if ( ! empty( $country['has_contact'] ) ) {
		$fields[ $contact ] = array( sprintf( 'recTEAM%010d', $n ) );
	}
	if ( ! empty( $country['has_email'] ) ) {
		$fields[ $email ] = array( sprintf( 'manager-%d@example.test', $n ) );
	}
	if ( ! empty( $country['has_calendly'] ) ) {
		$fields[ $calendly ] = array( sprintf( 'https://calendly.example.test/manager-%d', $n ) );
	}

	$records[] = rec( $country['id'], $fields );
}

$GLOBALS['queue'][] = $records;

ck( 'the seed refreshes', WPCPM_Countries::refresh( $client ), true );
ck( 'every country is in the map', count( WPCPM_Countries::all() ), count( $countries ) );
ck( 'which is the count the fixture records', count( WPCPM_Countries::all() ), isset( $counts['countries'] ) ? $counts['countries'] : null );
ck( 'the countries with no contact are the gaps', count( WPCPM_Countries::gaps() ), isset( $counts['countries_without_contact'] ) ? $counts['countries_without_contact'] : null );
ck( 'options() offers every one of them, gaps included', count( WPCPM_Countries::options() ), count( $countries ) );

$sorted = WPCPM_Countries::options();
natcasesort( $sorted );
ck( 'in name order', array_values( WPCPM_Countries::options() ), array_values( $sorted ) );

$gap_names = array_values( array_map( static function ( $row ) { return $row['name']; }, WPCPM_Countries::gaps() ) );
$expected  = $gap_names;
natcasesort( $expected );
ck( 'and gaps() is in name order too', $gap_names, array_values( $expected ) );

// Every country an institution names.
$used       = array();
$unresolved = array();
$unrouted   = array();

foreach ( isset( $seed['institutions'] ) ? $seed['institutions'] : array() as $institution ) {
	foreach ( (array) ( isset( $institution['country'] ) ? $institution['country'] : array() ) as $country_id ) {
		$used[ $country_id ] = true;

		if ( '' === WPCPM_Countries::name_of( $country_id ) ) {
			$unresolved[] = $country_id;
		} elseif ( null === WPCPM_Countries::routing( $country_id ) ) {
			$unrouted[ $country_id ] = WPCPM_Countries::name_of( $country_id );
		}
	}
}

$unrouted = array_values( array_unique( $unrouted ) );
sort( $unrouted );

ck( 'institutions name the number of countries the fixture counts', count( $used ), isset( $counts['countries_used_by_institutions'] ) ? $counts['countries_used_by_institutions'] : null );
ck( 'every one of them resolves to a name', $unresolved, array() );
ck( 'the ones with no contact are the three the spec names', $unrouted, array( 'Cambodia', 'Nigeria', 'Thailand' ) );
ck( 'which is the count the fixture records', count( $unrouted ), isset( $counts['countries_used_without_contact'] ) ? $counts['countries_used_without_contact'] : null );

// And the routed ones carry what the acknowledgement embeds.
$routed = 0;
$linked = 0;

foreach ( array_keys( $used ) as $country_id ) {
	$route = WPCPM_Countries::routing( $country_id );

	if ( null === $route ) {
		continue;
	}

	$routed++;

	if ( 0 === strpos( $route['calendly'], 'https://calendly.example.test/' ) && '' !== $route['email'] ) {
		$linked++;
	}
}

ck( 'every routed country carries a booking link and an address', $linked, $routed );
ck( 'and there are thirty of them', $routed, 30 );

// The option holds four short values per row and nothing else.
$keys_ok = true;
foreach ( WPCPM_Countries::all() as $row ) {
	if ( array( 'name', 'manager', 'email', 'calendly' ) !== array_keys( $row ) ) {
		$keys_ok = false;
	}
}
ck( 'no row holds anything beyond the four keys', $keys_ok, true );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
