<?php
/**
 * WPCPM_Sponsors_Feed: the public feed the Tracker and the Monthly Report switch to once the
 * Airtable Sponsors table is an archive (design of 23 September 2026, section 6).
 *
 * What is pinned here and why:
 *
 * - The route (`GET /wp-json/wpcpm/v1/sponsors`) is public and answers no argument.
 * - The answer lists only the Approved sponsors, by name case-insensitively, each with exactly
 *   six public keys - never a contact person, an address, a note, a status or a key.
 * - A Paused (or any non-Approved) sponsor never appears.
 * - Every private field stays out: one Approved row with a sentinel in every key of `empty_row()`
 *   but six (the name, slug and website the feed publishes, the day the row was created, whose
 *   month it publishes, the status, which must be Approved for the row to be listed at all, and the
 *   key the row is stored under), each sentinel holding both quotes, an ampersand and markup (a
 *   flag holds no words and is turned on instead), derived from the row's own shape so a key added
 *   later is walked too. The mentors list is walked like the rest, since the feed publishes how
 *   many it holds and never what; the logo record's own private keys hold a sentinel too, and the
 *   row's key is looked for besides. None may reach the answer, every sentinel is read back from
 *   the store first so the walk cannot pass by planting nothing, and every flag turned off changes
 *   nothing in it. The public fields hold the same characters and reach the answer as the store
 *   holds them: the feed is data, which its two readers escape for their own pages.
 * - `applications` holds the last twenty-four months, the current one last, every month
 *   zero-filled, `received` from the application posts' `post_date`, leaving out an application
 *   in the spam state, and `approved` from the store's Approved rows' `created`; neither counts a
 *   test sponsor, nor, since 1.123.23, an application or a sponsor with a test name, in any state.
 * - The built answer is cached for fifteen minutes and the cache is dropped the moment a store
 *   write calls `WPCPM_Sponsors_Index::forget()`, a logo upload or removal included, proven here
 *   without a rebuild by pre-loading a sentinel transient and reading it back.
 * - A single address is metered to sixty requests an hour; the sixty-first is refused with 429
 *   and never reaches `build()`, and another address is unaffected.
 * - A successful answer carries `Cache-Control: public, max-age=900`.
 *
 * The store (`WPCPM_Sponsors_Index`) and the feed (`WPCPM_Sponsors_Feed`) are the real classes;
 * everything they call is a stand-in. `WPCPM_Ceiling` counts claims per key rather than running
 * the real class's option-and-bucket arithmetic, and `WPCPM_Form_Guard::actor_key()` returns a
 * fixed string built from `$GLOBALS['ip_hash']` rather than hashing a real address - the feed
 * only ever passes the key straight to `WPCPM_Ceiling::claim()`, so nothing here needs the real
 * hashing or the real per-window bucket math, both already pinned by their own suites.
 * `WPCPM_Sponsor_Application` is a small stand-in: the feed reads only its `POST_TYPE`,
 * `META_STATE`, `STATE_SPAM`, since 1.123.18 `META_RECORD`, and since 1.123.23 the company's name
 * through `name_of_post()`, which the stand-in reads as the real class does, the stored answer first
 * and the title after it; the application suite pins the real one.
 *
 * The twenty-four-month window is read off the site's clock, as `WPCPM_Sponsors_Feed` reads it
 * through `wp_date()`: `post_date` and `created` are site-local,
 * and UTC's month is not the site's for a few hours at every month's turn. The `wp_date()`
 * stand-in pins that clock, so the suite answers the same on any day it is run:
 * `$GLOBALS['now']` is 22:30 UTC on 31 January 2027 and the site is two hours ahead, already in
 * February while UTC is still in January, with the window's oldest month in another year.
 * `ym_ago()` counts back from the same pinned month with PHP's own `DateTimeImmutable` month
 * arithmetic rather than a copy of the class's hand-rolled rollover loop, a second, independent
 * way to land on the same month, so the two would have to share a bug rather than a formula to
 * both be wrong the same way.
 *
 * Run from the plugin root:  php bin/test-sponsors-feed.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['posts']         = array();
$GLOBALS['pmeta']         = array();
$GLOBALS['opts']          = array();
$GLOBALS['transients']    = array();
$GLOBALS['transient_ttl'] = array();
$GLOBALS['buckets']       = array(); // WPCPM_Ceiling stand-in: key => claims made.
$GLOBALS['actions']       = array(); // add_action() stand-in: hook => callbacks.
$GLOBALS['routes']        = array(); // register_rest_route() stand-in: namespace => route => args.
$GLOBALS['ip_hash']       = 'test-address';
$GLOBALS['app_next_id']   = 9000; // Application posts; sponsor posts get their own IDs from posts.php.
$GLOBALS['now']           = gmmktime( 22, 30, 0, 1, 31, 2027 ); // The pinned clock, in UTC; see the file docblock.
$GLOBALS['site_offset']   = 2 * HOUR_IN_SECONDS;                // The site's timezone, two hours ahead of UTC.

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

// A post_name property (bin/stubs/posts.php requires one from a suite's own WP_Post) and a
// post_date property, which no sponsor-store suite has needed before this one.
class WP_Post {
	public $ID = 0;
	public $post_type = 'post';
	public $post_status = 'draft';
	public $post_title = '';
	public $post_name = '';
	public $post_author = 0;
	public $post_date = '';
}

function __( $text, $domain = null ) {
	return $text;
}

/**
 * The site's clock: core's `wp_date()` formats a moment in the site's timezone, and this one
 * formats the pinned `$GLOBALS['now']` (or the moment it is given) at the pinned offset.
 *
 * @param string   $format    A `date()` format.
 * @param int|null $timestamp A Unix time, or null for now.
 * @param mixed    $timezone  Unused: the site's timezone is the pinned offset.
 * @return string
 */
function wp_date( $format, $timestamp = null, $timezone = null ) {
	return gmdate( $format, ( null === $timestamp ? $GLOBALS['now'] : (int) $timestamp ) + $GLOBALS['site_offset'] );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['opts'][ $key ] = $value;
	return true;
}

// WPCPM_Sponsors_Index::display_logo()'s one external call: a deterministic address from the ID,
// so a sponsor's logo field in the answer can be checked without a real Media Library.
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	return 'https://example.test/logo-' . (int) $id . '.png';
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['actions'][ $hook ][] = $callback;
}

function register_rest_route( $namespace, $route, $args = array() ) {
	$GLOBALS['routes'][ $namespace ][ $route ] = $args;
}

function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $key ] : false;
}

function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['transients'][ $key ]    = $value;
	$GLOBALS['transient_ttl'][ $key ] = $expiration;
	return true;
}

function delete_transient( $key ) {
	unset( $GLOBALS['transients'][ $key ], $GLOBALS['transient_ttl'][ $key ] );
	return true;
}

class WP_REST_Request {
	private $params;

	public function __construct( $params = array() ) {
		$this->params = $params;
	}

	public function get_param( $key ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
	}
}

class WP_REST_Response {
	private $data;
	private $status;
	private $headers = array();

	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_status() {
		return $this->status;
	}

	public function get_headers() {
		return $this->headers;
	}

	public function header( $key, $value, $replace = true ) {
		$this->headers[ $key ] = $value;
		return true;
	}
}

/** Refuses a key's (limit + 1)th claim; the real class's own bucket-and-window arithmetic has its own suite. */
class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) {
		$claimed = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0;

		if ( $claimed + $amount > $limit ) {
			return false;
		}

		$GLOBALS['buckets'][ $key ] = $claimed + $amount;

		return true;
	}
}

/** The real class hashes and honors the trusted-proxy setting; the feed only needs the prefixed key back. */
class WPCPM_Form_Guard {
	public static function actor_key( $prefix ) {
		return (string) $prefix . ':' . ( isset( $GLOBALS['ip_hash'] ) ? $GLOBALS['ip_hash'] : '' );
	}
}

/** Only what the feed reads; the real class is thousands of lines of form handling this suite does not touch. */
class WPCPM_Sponsor_Application {
	const POST_TYPE   = 'wpcpm_sponsor_app';
	const META_STATE  = '_wpcpm_sapp_state';
	const STATE_SPAM  = 'spam';
	const META_RECORD = '_wpcpm_sapp_record';
	const META_FIELDS = '_wpcpm_sapp_fields';
	const COL_NAME    = 'Company Name';

	/** The company's name as the real class reads it: the stored answer, else the title. */
	public static function name_of_post( WP_Post $post ) {
		$fields = get_post_meta( $post->ID, self::META_FIELDS, true );
		$name   = is_array( $fields ) && isset( $fields[ self::COL_NAME ] ) ? trim( (string) $fields[ self::COL_NAME ] ) : '';

		return '' !== $name ? $name : trim( (string) $post->post_title );
	}
}

require_once __DIR__ . '/stubs/posts.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-feed.php';

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

/** Empty every store-shaped global between sections that count rows, keeping the site's other state. */
function reset_store() {
	$GLOBALS['posts'] = array();
	$GLOBALS['pmeta']  = array();
	WPCPM_Sponsors_Index::forget();
}

/**
 * The `Y-m` string for the month `$offset` months before the site's current one (0 = this month),
 * by PHP's own month arithmetic, independent of `WPCPM_Sponsors_Feed`'s own hand-rolled rollover
 * loop; see the file docblock.
 *
 * @param int $offset Months back.
 * @return string
 */
function ym_ago( $offset ) {
	$first = new DateTimeImmutable( wp_date( 'Y-m-01' ), new DateTimeZone( 'UTC' ) );

	return $first->modify( '-' . (int) $offset . ' months' )->format( 'Y-m' );
}

/** A `post_date` value, the 15th at noon, for the month `$offset` months back. */
function date_ago( $offset ) {
	return ym_ago( $offset ) . '-15 12:00:00';
}

/** A `created` value (`Y-m-d`), the 15th, for the month `$offset` months back. */
function created_ago( $offset ) {
	return ym_ago( $offset ) . '-15';
}

/** The twenty-four `Y-m` keys build() should produce, ending with the current month. */
function expected_month_keys() {
	$keys = array();

	for ( $i = 23; $i >= 0; $i-- ) {
		$keys[] = ym_ago( $i );
	}

	return $keys;
}

/**
 * One application post (WPCPM_Sponsor_Application::POST_TYPE), dated `$offset` months back.
 *
 * @param int    $offset Months back.
 * @param string $state  The application's state meta, or '' to write none.
 * @param string $name   The company's name as the form stored it, or '' to store no answers.
 * @param string $title  The post's title, the name's fallback.
 */
function add_application( $offset, $state = '', $name = '', $title = '' ) {
	$id = $GLOBALS['app_next_id']++;

	$post              = new WP_Post();
	$post->ID          = $id;
	$post->post_type   = WPCPM_Sponsor_Application::POST_TYPE;
	$post->post_status = 'private';
	$post->post_date   = date_ago( $offset );
	$post->post_title  = $title;

	$GLOBALS['posts'][ $id ] = $post;

	if ( '' !== $name ) {
		$GLOBALS['pmeta'][ $id ][ WPCPM_Sponsor_Application::META_FIELDS ] = array( WPCPM_Sponsor_Application::COL_NAME => $name );
	}

	if ( '' !== $state ) {
		$GLOBALS['pmeta'][ $id ][ WPCPM_Sponsor_Application::META_STATE ] = $state;
	}
}

echo "=== The route ===\n";

WPCPM_Sponsors_Feed::init();

ck(
	'init() hooks register_route to rest_api_init',
	isset( $GLOBALS['actions']['rest_api_init'] ) && in_array( array( 'WPCPM_Sponsors_Feed', 'register_route' ), $GLOBALS['actions']['rest_api_init'], true ),
	true
);

WPCPM_Sponsors_Feed::register_route();
$route = $GLOBALS['routes']['wpcpm/v1'][ WPCPM_Sponsors_Feed::ROUTE ];

ck(
	'the route is public and GET only',
	array( $route['methods'], $route['permission_callback'], $route['callback'] ),
	array( 'GET', '__return_true', array( 'WPCPM_Sponsors_Feed', 'rest_sponsors' ) )
);

echo "\n=== build(): the Approved sponsors ===\n";
reset_store();

$KEY_APPLE  = 'recSEED0000000101';
$KEY_ZEBRA  = 'recSEED0000000102';
$KEY_PAUSED = 'recSEED0000000103';

// A name pair where case-sensitive ordering would disagree with the alphabet ('Z' sorts before
// 'a' in plain byte order), so a build() that forgot strcasecmp() would list Zebra first.
WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id'      => $KEY_APPLE,
			'name'           => 'apple Sponsor',
			'website'        => 'https://apple.example/',
			'status'         => 'Approved',
			'mentors'        => array(),
			'created'        => '2026-01-05',
			'contact_person' => 'Should Never Appear',
			'contact_email'  => 'administrator+apple@example.test',
			'notes'          => 'Manager-only notes',
		)
	)
);

WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id' => $KEY_ZEBRA,
			'name'      => 'Zebra Sponsor',
			'website'   => 'https://zebra.example/',
			'status'    => 'Approved',
			'mentors'   => array( 'recMENTOR000000001', 'recMENTOR000000002' ),
			'created'   => '2026-02-20',
		)
	)
);

WPCPM_Sponsors_Index::write_logo_record( $KEY_ZEBRA, array( 'colour' => 501, 'source' => 'site' ) );

WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id' => $KEY_PAUSED,
			'name'      => 'Paused Co',
			'status'    => 'Paused',
		)
	)
);

$built = WPCPM_Sponsors_Feed::build();

ck(
	'an answer lists the Approved sponsors by name with six public keys and no other',
	$built['sponsors'],
	array(
		array(
			'name'    => 'apple Sponsor',
			'slug'    => 'apple-sponsor',
			'website' => 'https://apple.example/',
			'logo'    => '',
			'mentors' => 0,
			'since'   => '2026-01',
		),
		array(
			'name'    => 'Zebra Sponsor',
			'slug'    => 'zebra-sponsor',
			'website' => 'https://zebra.example/',
			'logo'    => 'https://example.test/logo-501.png',
			'mentors' => 2,
			'since'   => '2026-02',
		),
	)
);

ck(
	'a Paused sponsor is absent',
	array_column( $built['sponsors'], 'name' ),
	array( 'apple Sponsor', 'Zebra Sponsor' )
);

echo "\n=== build(): every private field stays out ===\n";
reset_store();

$feed_key    = 'spoFEEDWALK000001';
$feed_tail   = ' "&<b>\'';
$feed_public = array( 'record_id', 'slug', 'name', 'website', 'status', 'created' );
$feed_fields = array();
$feed_flags  = array();

// A sentinel for every key outside what the feed publishes, built from the key's own default: a
// list holds one, the agreement and a term owed its report one per inner key, the extra events a
// term was granted one in their day beside a count, which holds no words, and a flag holds no
// words, so it is turned over instead. All but the test sponsor's flag, which decides whether a
// sponsor is in the answer at all and has its own checks below.
foreach ( WPCPM_Sponsors_Index::empty_row() as $key => $default ) {
	if ( in_array( $key, $feed_public, true ) || 'test' === $key ) {
		continue;
	}

	$mark = 'FeedWalk-' . $key;

	if ( is_bool( $default ) ) {
		$feed_fields[ $key ] = true;
		$feed_flags[ $key ]  = false;
	} elseif ( 'report_owed' === $key ) {
		$feed_fields[ $key ] = array(
			'tier'        => $mark . '-tier' . $feed_tail,
			'until'       => $mark . '-until' . $feed_tail,
			'funded_line' => $mark . '-funded_line' . $feed_tail,
		);
	} elseif ( 'event_allowance' === $key ) {
		$feed_fields[ $key ] = array(
			'until' => $mark . '-until' . $feed_tail,
			'extra' => 3,
		);
	} elseif ( is_array( $default ) && ! empty( $default ) ) {
		$feed_fields[ $key ] = array();

		foreach ( $default as $inner => $inner_default ) {
			$feed_fields[ $key ][ $inner ] = is_bool( $inner_default ) ? true : $mark . '-' . $inner . $feed_tail;
		}
	} elseif ( is_array( $default ) ) {
		$feed_fields[ $key ] = array( $mark . $feed_tail );
	} else {
		$feed_fields[ $key ] = $mark . $feed_tail;
	}
}

WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		$feed_fields,
		array(
			'record_id' => $feed_key,
			'name'      => 'Feed "Walk" & <b>Co</b>',
			'website'   => 'https://feed.example/?a="b"&c=<d>',
			'status'    => 'Approved',
			'created'   => '2026-03-15',
		)
	)
);
WPCPM_Sponsors_Index::write_logo_record( $feed_key, array( 'colour' => 601, 'white' => 0, 'source' => 'FeedWalk-logo-source' . $feed_tail, 'airtable_id' => 'FeedWalk-logo-airtable_id' . $feed_tail ) );

/**
 * Every sentinel's head a value holds, however deep: the words before the special characters, which
 * an escape or an encoding of the tail cannot hide.
 *
 * @param mixed $value A field's value.
 * @return string[]
 */
function feed_marks( $value ) {
	if ( is_array( $value ) ) {
		return array_merge( array(), ...array_map( 'feed_marks', array_values( $value ) ) );
	}

	return is_string( $value ) && 0 === strpos( $value, 'FeedWalk-' ) ? array( substr( $value, 0, strpos( $value, ' ' ) ) ) : array();
}

$feed_marks   = array_merge( feed_marks( $feed_fields ), array( 'FeedWalk-logo-source', 'FeedWalk-logo-airtable_id', $feed_key ) );
$feed_planted = json_encode( WPCPM_Sponsors_Index::row( $feed_key ) ) . json_encode( WPCPM_Sponsors_Index::logo_record( $feed_key ) );

ck(
	'the walk plants a sentinel in every private field of the row and its logo record, and the store keeps each one',
	array( count( $feed_marks ) > 20, array_values( array_filter( $feed_marks, static function ( $mark ) use ( $feed_planted ) { return false === strpos( $feed_planted, $mark ); } ) ) ),
	array( true, array() )
);

$feed_answer = WPCPM_Sponsors_Feed::build();
$feed_json   = json_encode( $feed_answer );

ck(
	'no private field reaches the answer, the row\'s key included',
	array_values( array_filter( $feed_marks, static function ( $mark ) use ( $feed_json ) { return false !== strpos( $feed_json, $mark ); } ) ),
	array()
);
ck(
	'the public fields reach it as the store holds them, special characters and all: the feed is data, escaped by its readers',
	$feed_answer['sponsors'],
	array(
		array(
			'name'    => 'Feed "Walk" & <b>Co</b>',
			'slug'    => WPCPM_Sponsors_Index::row( $feed_key )['slug'],
			'website' => 'https://feed.example/?a="b"&c=<d>',
			'logo'    => 'https://example.test/logo-601.png',
			'mentors' => 1,
			'since'   => '2026-03',
		),
	)
);

$feed_off = $feed_flags;

foreach ( WPCPM_Sponsors_Index::empty_row() as $key => $default ) {
	if ( is_array( $default ) && ! empty( $default ) && 'report_owed' !== $key ) {
		$feed_off[ $key ] = WPCPM_Sponsors_Index::row( $feed_key )[ $key ];

		foreach ( $default as $inner => $inner_default ) {
			if ( is_bool( $inner_default ) ) {
				$feed_off[ $key ][ $inner ] = false;
			}
		}
	}
}

WPCPM_Sponsors_Index::patch( $feed_key, $feed_off );
ck( 'and every flag the row holds, turned off, changes nothing in it', array( count( $feed_flags ) > 0, WPCPM_Sponsors_Feed::build()['sponsors'] === $feed_answer['sponsors'] ), array( true, true ) );

echo "\n=== build(): applications ===\n";
reset_store();

// Received: application posts by post_date month. Offset 23 is the oldest included month;
// offset 24 is one month outside the twenty-four-month window and must not be counted anywhere.
add_application( 0 );
add_application( 5 );
add_application( 5 );
add_application( 23 );
add_application( 24 );

// Approved: the store's rows by the month of created.
$KEY_APP_FIVE = 'recSEED0000000201';
$KEY_APP_TEN  = 'recSEED0000000202';

WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id' => $KEY_APP_FIVE,
			'name'      => 'Approved Five Months Back',
			'status'    => 'Approved',
			'created'   => created_ago( 5 ),
		)
	)
);

WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id' => $KEY_APP_TEN,
			'name'      => 'Approved Ten Months Back',
			'status'    => 'Approved',
			'created'   => created_ago( 10 ),
		)
	)
);

$expected_apps = array_fill_keys(
	expected_month_keys(),
	array(
		'received' => 0,
		'approved' => 0,
	)
);

$expected_apps[ ym_ago( 23 ) ]['received'] = 1;
$expected_apps[ ym_ago( 10 ) ]['approved'] = 1;
$expected_apps[ ym_ago( 5 ) ]              = array(
	'received' => 2,
	'approved' => 1,
);
$expected_apps[ ym_ago( 0 ) ]['received']  = 1;

ck(
	'applications are counted by month for 24 months',
	WPCPM_Sponsors_Feed::build()['applications'],
	$expected_apps
);

// application_posts() used to read every application ever filed, unbounded, to
// throw all but twenty-four months of them away right after. The bound is the oldest month
// build() reports, at its first day, from the same ym_ago() arithmetic used above.
WPCPM_Sponsors_Feed::build();

ck(
	'the applications query is bounded to the months the feed reports',
	isset( $GLOBALS['last_get_posts']['date_query'] ) ? $GLOBALS['last_get_posts']['date_query'] : null,
	array(
		array(
			'after'     => ym_ago( 23 ) . '-01 00:00:00',
			'inclusive' => true,
		),
	)
);

echo "\n=== build(): the site's clock ===\n";
reset_store();

// Ten minutes into February on the site's clock, while UTC is still in January: an application
// the site dated then belongs to February, which is the month the window has to end with.
$first_minutes              = new WP_Post();
$first_minutes->ID          = $GLOBALS['app_next_id']++;
$first_minutes->post_type   = WPCPM_Sponsor_Application::POST_TYPE;
$first_minutes->post_status = 'private';
$first_minutes->post_date   = '2027-02-01 00:10:00';

$GLOBALS['posts'][ $first_minutes->ID ] = $first_minutes;

$site_months = WPCPM_Sponsors_Feed::build()['applications'];
$month_keys  = array_keys( $site_months );

ck(
	"the window ends with the site's month, not UTC's, and an application the site dated in its first minutes counts there",
	array( gmdate( 'Y-m', $GLOBALS['now'] ), $month_keys[0], $month_keys[ count( $month_keys ) - 1 ], isset( $site_months['2027-02'] ) ? $site_months['2027-02']['received'] : null ),
	array( '2027-01', '2025-03', '2027-02', 1 )
);

echo "\n=== build(): an application marked as spam is no enquiry ===\n";
reset_store();

// To the Credits Monthly Report `received` is the month's
// enquiries, and a row the spam layer caught or a manager marked as spam was never one. Held
// (by the anti-spam checks, for a manager to look at) and decided rows came in all the same.
add_application( 3 );
add_application( 3, 'held' );
add_application( 3, 'rejected' );
add_application( 3, 'spam' );

ck( 'an application in the spam state is not counted as received; a new, a held and a rejected one are', WPCPM_Sponsors_Feed::build()['applications'][ ym_ago( 3 ) ]['received'], 3 );

echo "\n=== build(): a test sponsor is in no answer (1.123.18) ===\n";
reset_store();

// The feed is public, and the WPCredits Tracker and the Credits Monthly Report count from it: a
// test sponsor is neither listed nor counted among the approvals, and the application approved
// into it is no enquiry either.
$KEY_REAL = 'recSEED0000000201';
$KEY_TEST = 'recSEED0000000202';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_REAL, 'name' => 'Real Sponsor', 'status' => 'Approved', 'created' => date_ago( 2 ) ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $KEY_TEST, 'name' => 'TEST - WordPress Education Dashboard (do not use)', 'status' => 'Approved', 'created' => date_ago( 2 ), 'test' => true ) ) );
add_application( 2, 'approved' );
add_application( 2, 'approved' );
$GLOBALS['pmeta'][ $GLOBALS['app_next_id'] - 1 ][ WPCPM_Sponsor_Application::META_RECORD ] = $KEY_TEST;
$t_built = WPCPM_Sponsors_Feed::build();
ck( 'a test sponsor is not listed, not counted as approved, and the application it was approved from is not counted as received', array( array_column( $t_built['sponsors'], 'name' ), $t_built['applications'][ ym_ago( 2 ) ] ), array( array( 'Real Sponsor' ), array( 'received' => 1, 'approved' => 1 ) ) );

echo "\n=== build(): no TEST application is counted (1.123.23) ===\n";
reset_store();

// To the Credits Monthly Report `received` is the month's enquiries and `approved` its new
// sponsors: an application from a company with a test name (`WPCPM_Sponsors_Index::is_test_name()`)
// is neither, whatever its state, and nor is a sponsor with one, even with its flag cleared for a
// trial. A real company whose name only begins with the letters counts.
add_application( 4, '', 'TEST - Acme' );
add_application( 4, 'held', '  TEST Acme ' );
add_application( 4, 'rejected', 'TEST: Acme' );
add_application( 4, 'approved', 'TEST' );
add_application( 4, '', '', 'TEST(1)' );
add_application( 4, '', 'TESTAMENT Publishing' );
add_application( 4, 'rejected', 'Test Acme' );
add_application( 4, '', '', 'Plain Title Co' );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => 'recSEED0000000211', 'name' => 'TEST - Acme', 'status' => 'Approved', 'created' => created_ago( 4 ) ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => 'recSEED0000000212', 'name' => 'TESTAMENT Publishing', 'status' => 'Approved', 'created' => created_ago( 4 ) ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => 'recSEED0000000213', 'name' => 'Real Co', 'status' => 'Approved', 'created' => created_ago( 4 ) ) ) );
$n_built = WPCPM_Sponsors_Feed::build();
ck( 'an application with a test name is counted nowhere, new, held, rejected or approved, its title read when it holds no answers; nor is a sponsor with one, flag or none', $n_built['applications'][ ym_ago( 4 ) ], array( 'received' => 3, 'approved' => 2 ) );
ck( 'the list is still the flag\'s to decide: a sponsor with a test name and its flag cleared is listed for its trial', array_column( $n_built['sponsors'], 'name' ), array( 'Real Co', 'TEST - Acme', 'TESTAMENT Publishing' ) );

echo "\n=== caching ===\n";
reset_store();
$GLOBALS['transients']    = array();
$GLOBALS['transient_ttl'] = array();
$GLOBALS['buckets']       = array();
$GLOBALS['ip_hash']       = 'cache-address';

$KEY_CACHE = 'recSEED0000000301';

WPCPM_Sponsors_Index::insert(
	array_merge(
		WPCPM_Sponsors_Index::empty_row(),
		array(
			'record_id' => $KEY_CACHE,
			'name'      => 'Cache Sponsor',
			'status'    => 'Approved',
		)
	)
);

WPCPM_Sponsors_Feed::rest_sponsors( new WP_REST_Request() );
$ttl = isset( $GLOBALS['transient_ttl'][ WPCPM_Sponsors_Feed::TRANSIENT ] ) ? $GLOBALS['transient_ttl'][ WPCPM_Sponsors_Feed::TRANSIENT ] : null;

// A sentinel written straight into the transient: if the next call answers it back, that call
// read the cache rather than calling build() again.
set_transient(
	WPCPM_Sponsors_Feed::TRANSIENT,
	array(
		'sponsors'     => array(),
		'applications' => array(),
		'generated'    => 'SENTINEL',
	),
	900
);
$cached_answer = WPCPM_Sponsors_Feed::rest_sponsors( new WP_REST_Request() )->get_data();

WPCPM_Sponsors_Index::patch( $KEY_CACHE, array( 'name' => 'Cache Sponsor Renamed' ) );

ck(
	'the answer is cached for fifteen minutes and dropped by a store write',
	array( $ttl, $cached_answer['generated'], get_transient( WPCPM_Sponsors_Feed::TRANSIENT ) ),
	array( 15 * MINUTE_IN_SECONDS, 'SENTINEL', false )
);

// The logo is in the answer, so a logo upload or removal drops the
// cached answer too, instead of reaching the feed only when the fifteen minutes run out.
$sentinel = array(
	'sponsors'     => array(),
	'applications' => array(),
	'generated'    => 'SENTINEL',
);

set_transient( WPCPM_Sponsors_Feed::TRANSIENT, $sentinel, 900 );
WPCPM_Sponsors_Index::write_logo_record( $KEY_CACHE, array( 'colour' => 77, 'source' => 'site' ) );
$after_upload = get_transient( WPCPM_Sponsors_Feed::TRANSIENT );

set_transient( WPCPM_Sponsors_Feed::TRANSIENT, $sentinel, 900 );
WPCPM_Sponsors_Index::write_logo_record(
	$KEY_CACHE,
	array(
		'colour'      => 0,
		'white'       => 0,
		'source'      => '',
		'airtable_id' => '',
	)
);
$after_removal = get_transient( WPCPM_Sponsors_Feed::TRANSIENT );

ck( 'a logo upload and a logo removal each drop the cached answer', array( $after_upload, $after_removal ), array( false, false ) );

echo "\n=== the ceiling ===\n";
reset_store();
$GLOBALS['transients']    = array();
$GLOBALS['transient_ttl'] = array();
$GLOBALS['buckets']       = array();
$GLOBALS['ip_hash']       = 'ceiling-address';

$statuses = array();

for ( $i = 0; $i < 60; $i++ ) {
	$statuses[] = WPCPM_Sponsors_Feed::rest_sponsors( new WP_REST_Request() )->get_status();
}

$refused = WPCPM_Sponsors_Feed::rest_sponsors( new WP_REST_Request() );

ck(
	'the sixty-first request from one address in an hour is refused with 429',
	array(
		array_unique( $statuses ),
		$refused->get_status(),
		$refused->get_data(),
	),
	array(
		array( 200 ),
		429,
		array(
			'code'    => 'wpcpm_feed_busy',
			'message' => 'Too many requests. Try again in an hour.',
		),
	)
);

$GLOBALS['ip_hash'] = 'a-different-address';
$other              = WPCPM_Sponsors_Feed::rest_sponsors( new WP_REST_Request() );

ck( "a different address is unaffected by the first address's ceiling", $other->get_status(), 200 );

echo "\n=== the header ===\n";
reset_store();
$GLOBALS['transients']    = array();
$GLOBALS['transient_ttl'] = array();
$GLOBALS['buckets']       = array();
$GLOBALS['ip_hash']       = 'header-address';

$response = WPCPM_Sponsors_Feed::rest_sponsors( new WP_REST_Request() );

ck( 'the header says public, max-age=900', $response->get_headers(), array( 'Cache-Control' => 'public, max-age=900' ) );

printf( "\n%s (%d checks)\n", $fails ? "$fails FAILED" : 'ALL PASS', (int) $total );
exit( $fails ? 1 : 0 );
