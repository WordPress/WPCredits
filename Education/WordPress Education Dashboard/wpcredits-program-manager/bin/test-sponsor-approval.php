<?php
/**
 * Approval: the one path from a sponsor application to a record, a row, an account and the rest.
 *
 * Design spec of 4 September 2026, section 9.3, amended for the site-side record (1.111.0).
 * What each block pins, and why:
 *
 * - **The key is minted first, and it cannot fail.** `WPCPM_Sponsors_Index::mint_key()` always
 *   answers with a fresh `spo` key, so the suite pins instead that the earlier refusals (no
 *   address, no name, a conflicting account) still write nothing and release the lock, and that
 *   `WPCPM_Airtable` is never constructed at all: the stub is gone from this file, so a call to
 *   it would be a fatal, not a failed check.
 * - **A row the store refuses stops the approval.** The `wp_insert_post()`
 *   stand-in refuses the store's next row once; the approval answers with its own code and
 *   sentence before the account is asked for, keeps the key stamped, writes no event, releases
 *   the lock, and the next press inserts the row under the same key.
 * - **The row is the application's answers, shaped for the store.** Name, website, status,
 *   option, anything, contact person, the address lowercased, consent, the dashboard flag and
 *   today's date, inserted through the real `WPCPM_Sponsors_Index`; and the tier the applicant
 *   said it was considering (1.113.0), kept only when it is a tier of the real
 *   `WPCPM_Sponsor_Tiers` or its "not sure yet", so no answer, an unknown one and an application
 *   from before the question all leave `tier_requested` empty.
 * - **The side effects run in the spec's order**, and the journal says so: key, row, account,
 *   category, logo record, offer, invitation, log.
 * - **Every half is stamped the moment it lands, and a second press finishes the rest.** The
 *   suite kills a run at the account step, ages the lock it left, presses again, and asserts one
 *   key, one index row, one account, one invitation - the same key both times.
 * - **The account half is the shipped provisioning path.** `WPCPM_Sponsors::provision_account()`
 *   is asked, and its refusal is the approval's refusal; it is stood in for here at its contract,
 *   refusing a record the index does not hold exactly as the real one does, which is what makes
 *   "the index row before the account" an assertion rather than a hope.
 * - **The lock.** Taken after the refusals that write nothing, released on every exit after it,
 *   and never touched by a refusal that came before it.
 *
 * `WPCPM_Sponsors_Index`, `WPCPM_Sponsor_Tiers` and `WPCPM_Roles` are the real files; everything
 * else is stood in for at its contract.
 *
 * Run from the plugin root:  php bin/test-sponsor-approval.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']           = array();
$GLOBALS['opts_autoload']  = array();
$GLOBALS['posts']          = array();
$GLOBALS['pmeta']          = array();
$GLOBALS['users']          = array();
$GLOBALS['attachments']    = array();
$GLOBALS['authored']       = array();
$GLOBALS['statused']       = array();
$GLOBALS['next_id']        = 300;
$GLOBALS['next_user']      = 40;
$GLOBALS['journal']        = array();
$GLOBALS['audit']          = array();
$GLOBALS['members']        = array();
$GLOBALS['mail_queue']     = array();
$GLOBALS['seeded']         = array();
$GLOBALS['account_answer']  = null;
$GLOBALS['category_answer'] = null;
$GLOBALS['account_dies']    = false;
$GLOBALS['insert_fails']    = false;
$GLOBALS['manager_can']     = true;

class WP_Error {
	public $code = '';
	public $message = '';
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_author = 0, $post_title = '', $post_name = '', $post_date = '';
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $email = '', $login = '' ) { $this->ID = $id; $this->user_email = $email; $this->user_login = $login; }
	public function exists() { return $this->ID > 0; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( (string) $u ) : parse_url( (string) $u, $c ); }
/** mint_key()'s own alphabet; journals 'key' so the order check sees exactly when it ran. */
function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	$out      = '';
	for ( $i = 0; $i < (int) $length; $i++ ) {
		$out .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
	}
	$GLOBALS['journal'][] = 'key';
	return $out;
}

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['opts_autoload'][ $k ] = $a; return true; }
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ]          = $v;
	$GLOBALS['opts_autoload'][ $k ] = $a;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ], $GLOBALS['opts_autoload'][ $k ] ); return true; }

function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value; return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function wp_insert_post( $a, $error = false ) {
	// A database that refuses the store's next row, once: the approval's own refusal for a row
	// that did not land. Nothing is written and nothing is
	// journaled, as nothing is on a site whose insert failed.
	if ( $GLOBALS['insert_fails'] && class_exists( 'WPCPM_Sponsors_Index' ) && WPCPM_Sponsors_Index::POST_TYPE === ( $a['post_type'] ?? '' ) ) {
		$GLOBALS['insert_fails'] = false;
		return $error ? new WP_Error( 'db_insert_error', 'Could not insert post into the database.' ) : 0;
	}
	$post                          = new WP_Post();
	$post->ID                      = $GLOBALS['next_id']++;
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$post->post_title              = $a['post_title'] ?? '';
	$post->post_name               = $a['post_name'] ?? '';
	$post->post_date               = gmdate( 'Y-m-d H:i:s' );
	$GLOBALS['posts'][ $post->ID ] = $post;
	// The store's own post type only: journals 'row' so the order check sees the index insert,
	// and an application post (seed_application()'s own wp_insert_post() call, same function)
	// never counts as one.
	if ( class_exists( 'WPCPM_Sponsors_Index' ) && WPCPM_Sponsors_Index::POST_TYPE === $post->post_type ) {
		$GLOBALS['journal'][] = 'row';
	}
	return $post->ID;
}
/** The one call the approval makes on an attachment: moving its author to the account. */
function wp_update_post( $a, $error = false ) {
	$id = (int) ( $a['ID'] ?? 0 );
	if ( isset( $a['post_author'] ) ) {
		$GLOBALS['journal'][]     = 'logo';
		$GLOBALS['authored'][ $id ] = (int) $a['post_author'];
		// The form stores a stranger's files private; approval publishes them in the same
		// write that moves the author.
		$GLOBALS['statused'][ $id ] = (string) ( $a['post_status'] ?? '' );
	}
	return $id;
}
function wp_get_attachment_url( $id ) { return isset( $GLOBALS['attachments'][ (int) $id ] ) ? $GLOBALS['attachments'][ (int) $id ] : false; }

/** The slice of `$wpdb` the lock sweep touches: one LIKE over option names. */
class WPCPM_Test_Wpdb {
	public $options = 'wp_options';
	private $args = array();
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) { $this->args = $args; return $sql; }
	public function get_col( $sql ) {
		$prefix = str_replace( array( '\\_', '\\%' ), array( '_', '%' ), rtrim( (string) ( $this->args[0] ?? '' ), '%' ) );
		$out    = array();
		foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
			if ( 0 === strpos( $name, $prefix ) ) { $out[] = $name; }
		}
		return $out;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
function get_user_by( $by, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $by && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
		if ( 'id' === $by && $user->ID === (int) $value ) { return $user; }
	}
	return false;
}
require_once __DIR__ . '/stubs/caps.php';
function add_filter() { return true; }
function remove_filter() { return true; }
function wp_insert_user( $a ) {
	$id                      = $GLOBALS['next_user']++;
	$user                    = new WP_User( $id, (string) ( $a['user_email'] ?? '' ), (string) ( $a['user_login'] ?? '' ) );
	$user->display_name      = (string) ( $a['display_name'] ?? '' );
	$user->roles             = array( (string) ( $a['role'] ?? '' ) );
	$GLOBALS['users'][ $id ] = $user;
	return $id;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once __DIR__ . '/stubs/posts.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

/** The form, at its contract: the post type, the states, the meta keys, the columns, the logos. */
class WPCPM_Sponsor_Application {
	const POST_TYPE      = 'wpcpm_sponsor_app';
	const STATE_NEW      = 'new';
	const STATE_HELD     = 'held';
	const STATE_SPAM     = 'spam';
	const STATE_INFO     = 'info';
	const STATE_APPROVED = 'approved';
	const STATE_REJECTED = 'rejected';
	const META_FIELDS    = '_wpcpm_sapp_fields';
	const META_STATE     = '_wpcpm_sapp_state';
	const META_REFERENCE = '_wpcpm_sapp_reference';
	const META_LOGOS     = '_wpcpm_sapp_logos';
	const META_RECORD    = '_wpcpm_sapp_record';
	const META_USER      = '_wpcpm_sapp_user';
	const META_EVENT     = '_wpcpm_sapp_event';
	const META_DECIDED   = '_wpcpm_sapp_decided';
	const EVENT_APPROVED = 'approved';
	const EVENT_REJECTED = 'rejected';
	const EVENT_SPAM     = 'marked as spam';
	const EVENT_REOPENED = 'reopened';
	const COL_NAME       = 'Company Name';
	const COL_WEBSITE    = 'Website';
	const COL_PERSON     = 'Contact Person Full Name';
	const COL_EMAIL      = 'Contact Email';
	const COL_OPTION     = 'Sponsorship options';
	const COL_TIER       = 'Tier considered';
	const COL_ANYTHING   = "Anything else you'd like to share.";
	public static function open_states() { return array( 'new', 'held', 'info' ); }
	/** The real writer's shape: the event row, and the decision time for a terminal event (clean-up 1.98.1). */
	public static function add_event( $post_id, $event, $actor = 0, $note = '' ) {
		add_post_meta( (int) $post_id, self::META_EVENT, array( 'event' => (string) $event, 'at' => time(), 'actor' => (int) $actor, 'note' => (string) $note ) );
		if ( in_array( (string) $event, array( self::EVENT_APPROVED, self::EVENT_REJECTED, self::EVENT_SPAM ), true ) ) { update_post_meta( (int) $post_id, self::META_DECIDED, time() ); }
		elseif ( self::EVENT_REOPENED === (string) $event ) { $GLOBALS['pmeta'][ (int) $post_id ][ self::META_DECIDED ] = array(); }
	}
	public static function logos_of( $post_id ) {
		$stored = get_post_meta( (int) $post_id, self::META_LOGOS, true );
		$stored = is_array( $stored ) ? $stored : array();
		return array( 'colour' => isset( $stored['colour'] ) ? (int) $stored['colour'] : 0, 'white' => isset( $stored['white'] ) ? (int) $stored['white'] : 0 );
	}
}

/**
 * The shipped provisioning path's account half, at its contract.
 *
 * It refuses a record the index does not hold, exactly as the real one does through
 * `WPCPM_Sponsor_Members::attach()`, and it remembers the account it attached so a second
 * press answers `already`. It can be made to die once (a fatal between two halves) and to
 * answer a refusal of the test's choosing.
 */
class WPCPM_Sponsors {
	public static function provision_account( $record, $actor_id ) {
		$GLOBALS['journal'][] = 'account';
		if ( $GLOBALS['account_dies'] ) {
			$GLOBALS['account_dies'] = false;
			throw new Exception( 'the request died at the account' );
		}
		if ( is_array( $GLOBALS['account_answer'] ) ) { return $GLOBALS['account_answer']; }
		if ( ! WPCPM_Sponsors_Index::has( $record ) ) {
			return array( 'status' => 'provision-refused', 'user_id' => 0, 'created' => false, 'already' => false, 'detail' => 'That sponsor is not in the index yet.' );
		}
		$row = WPCPM_Sponsors_Index::row( $record );
		if ( isset( $GLOBALS['members'][ $record ] ) ) {
			return array( 'status' => 'provision-attached', 'user_id' => $GLOBALS['members'][ $record ], 'created' => false, 'already' => true, 'detail' => '' );
		}
		$existing = get_user_by( 'email', $row['contact_email'] );
		$created  = false;
		if ( $existing instanceof WP_User ) {
			$user_id = (int) $existing->ID;
		} else {
			$user_id = (int) wp_insert_user( array( 'user_login' => 'sponsor', 'user_email' => $row['contact_email'], 'role' => 'wpcpm_sponsor', 'display_name' => $row['contact_person'] ) );
			$created = true;
		}
		$GLOBALS['members'][ $record ] = $user_id;
		return array( 'status' => $created ? 'provisioned' : 'provision-attached', 'user_id' => $user_id, 'created' => $created, 'already' => false, 'detail' => '' );
	}
}
class WPCPM_Sponsor_Posts {
	public static function ensure_terms( $record ) { $GLOBALS['journal'][] = 'category'; return null === $GLOBALS['category_answer'] ? 77 : $GLOBALS['category_answer']; }
}
class WPCPM_Sponsor_Offers {
	public static function seed( $record ) {
		$GLOBALS['journal'][] = 'offer';
		if ( isset( $GLOBALS['seeded'][ $record ] ) ) { return false; }
		$GLOBALS['seeded'][ $record ] = 501;
		return 501;
	}
}
class WPCPM_Mail {
	public static function queue_invites( array $user_ids ) {
		$fresh = array();
		foreach ( array_values( array_unique( array_map( 'intval', $user_ids ) ) ) as $id ) {
			if ( in_array( $id, $GLOBALS['mail_queue'], true ) ) { continue; }
			$GLOBALS['mail_queue'][] = $id;
			$fresh[]                 = $id;
		}
		if ( empty( $fresh ) ) { return 0; }
		$GLOBALS['journal'][] = 'invite';
		return count( $fresh );
	}
}
class WPCPM_Institution_Audit {
	const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache'; const EVIDENCE_LIVE = 'live';
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system';
	public static function record_sponsor( array $entry ) {
		if ( '' === sanitize_key( (string) ( $entry['kind'] ?? '' ) ) || ! WPCPM_Sponsors_Index::is_key( (string) ( $entry['sponsor'] ?? '' ) ) || ! in_array( (string) ( $entry['ground'] ?? '' ), array( 'manager', 'member', 'system' ), true ) || ! in_array( (string) ( $entry['evidence'] ?? '' ), array( 'index', 'cache', 'live' ), true ) ) {
			return new WP_Error( 'wpcpm_audit', 'refused' );
		}
		$GLOBALS['journal'][] = 'audit';
		$GLOBALS['audit'][]   = $entry;
		return 900 + count( $GLOBALS['audit'] );
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors-index.php';
// The tier ladder, for which answers to the tier question the record may keep (1.113.0). Only its
// pure half is read, so no settings stand-in is needed.
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-tiers.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-approval.php';

$fail = 0; $checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/** Forget every option, post, account and journal entry, and seed a manager. */
function reset_world() {
	$GLOBALS['opts']           = array();
	$GLOBALS['opts_autoload']  = array();
	$GLOBALS['posts']          = array();
	$GLOBALS['pmeta']          = array();
	// The store (1.111.0) caches rows() for the request; clearing the posts out from under it
	// without this would leave a prior section's rows answering here too.
	WPCPM_Sponsors_Index::forget();
	// A reserved address, and deliberately not the applicant's: the manager pressing Approve is
	// somebody else, and an account already sitting at the contact address would be attached
	// rather than created, which is the one thing this suite must not stub away.
	$GLOBALS['users']          = array( 3 => new WP_User( 3, 'manager@wpcredits.example', 'manager' ) );
	$GLOBALS['manage']         = array( 3 );
	$GLOBALS['attachments']    = array();
	$GLOBALS['authored']       = array();
	$GLOBALS['statused']       = array();
	$GLOBALS['journal']        = array();
	$GLOBALS['audit']          = array();
	$GLOBALS['members']        = array();
	$GLOBALS['mail_queue']     = array();
	$GLOBALS['seeded']         = array();
	$GLOBALS['account_answer']  = null;
	$GLOBALS['category_answer'] = null;
	$GLOBALS['account_dies']    = false;
	$GLOBALS['insert_fails']    = false;
	$GLOBALS['next_id']         = 300;
	$GLOBALS['next_user']       = 40;
}

/** The six answers as the form stored them before the tier question joined them (1.113.0). */
function stored_answers() {
	return array(
		'Company Name'                      => 'TEST Sponsor',
		'Website'                           => 'https://test-sponsor.example',
		'Contact Person Full Name'          => 'Rep One',
		'Contact Email'                     => 'administrator@example.test',
		'Sponsorship options'               => 'Sponsor mentors + tools/services',
		"Anything else you'd like to share." => 'We make widgets.',
	);
}

/**
 * Stand up one application post in the state a manager finds it in.
 *
 * @param string $state   One of the application states.
 * @param array  $meta    Extra meta rows, keyed by meta key.
 * @param array  $answers The stored answers; the seeded six when empty.
 * @param bool   $logos   Whether the application holds the two attachments.
 * @return int The post ID.
 */
function seed_application( $state = 'new', array $meta = array(), array $answers = array(), $logos = true ) {
	$id = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Application::POST_TYPE, 'post_status' => 'private', 'post_author' => 0, 'post_title' => 'TEST Sponsor' ) );

	update_post_meta( $id, WPCPM_Sponsor_Application::META_FIELDS, $answers ? $answers : stored_answers() );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, $state );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_REFERENCE, 'SAPP-2026-0300' );

	if ( $logos ) {
		$GLOBALS['attachments'][640] = 'https://example.test/uploads/test-sponsor-logo.png';
		$GLOBALS['attachments'][641] = 'https://example.test/uploads/test-sponsor-logo-white.png';
		update_post_meta( $id, WPCPM_Sponsor_Application::META_LOGOS, array( 'colour' => 640, 'white' => 641 ) );
	} else {
		update_post_meta( $id, WPCPM_Sponsor_Application::META_LOGOS, array( 'colour' => 0, 'white' => 0 ) );
	}

	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}

	return $id;
}

/** Press Approve as manager 3. */
function approve( $id ) {
	return WPCPM_Sponsor_Approval::approve( $id, 3 );
}

/** The event names written on one application, in order. */
function events_of( $id ) {
	return array_map( static function ( $row ) { return $row['event']; }, (array) get_post_meta( $id, WPCPM_Sponsor_Application::META_EVENT, false ) );
}

echo "=== Refusals before the lock ===\n";
reset_world();
$page              = new WP_Post();
$page->ID          = 42;
$page->post_type   = 'page';
$GLOBALS['posts'][42] = $page;
ck( 'a post that is not an application', approve( 42 )->get_error_code(), 'wpcpm_sapp_unknown' );
ck( 'and one that does not exist', approve( 9999 )->get_error_code(), 'wpcpm_sapp_unknown' );
$id = seed_application( 'rejected' );
ck( 'a decided application', approve( $id )->get_error_code(), 'wpcpm_sapp_state' );
$id = seed_application( 'new' );
$GLOBALS['manage'] = array();
ck( 'an actor who cannot manage the program', approve( $id )->get_error_code(), 'wpcpm_sapp_actor' );
$GLOBALS['manage'] = array( 3 );
$id = seed_application( 'new', array( WPCPM_Sponsor_Application::META_FIELDS => array() ) );
ck( 'an application with no answers', approve( $id )->get_error_code(), 'wpcpm_sapp_fields' );
ck( 'none of which took the lock or journaled anything', array( count( preg_grep( '/^wpcpm_sapp_lock_/', array_keys( $GLOBALS['opts'] ) ) ), $GLOBALS['journal'] ), array( 0, array() ) );

echo "\n=== The lock ===\n";
reset_world();
$id = seed_application();
update_option( WPCPM_Sponsor_Approval::lock_name( $id ), time(), false );
ck( 'a fresh lock refuses the press, and nothing is written', array( approve( $id )->get_error_code(), $GLOBALS['journal'] ), array( 'wpcpm_sapp_busy', array() ) );
update_option( WPCPM_Sponsor_Approval::lock_name( $id ), time() - WPCPM_Sponsor_Approval::LOCK_TIMEOUT - 1, false );
$result = approve( $id );
ck( 'a lock older than the timeout belonged to a dead request and is taken over', is_array( $result ), true );
ck( 'and released on the way out', get_option( WPCPM_Sponsor_Approval::lock_name( $id ) ), false );

echo "\n=== Refusals after the lock release it ===\n";
reset_world();
$id = seed_application( 'new', array(), array_merge( stored_answers(), array( 'Contact Email' => 'not an address' ) ) );
ck( 'an address WordPress cannot make an account with', array( approve( $id )->get_error_code(), get_option( WPCPM_Sponsor_Approval::lock_name( $id ) ), $GLOBALS['journal'] ), array( 'wpcpm_sapp_no_email', false, array() ) );
$id = seed_application( 'new', array(), array_merge( stored_answers(), array( 'Company Name' => '  ' ) ) );
ck( 'a nameless application', array( approve( $id )->get_error_code(), get_option( WPCPM_Sponsor_Approval::lock_name( $id ) ) ), array( 'wpcpm_sapp_name', false ) );

echo "\n=== Never adopts a stranger's account ===\n";
reset_world();
$GLOBALS['users'][50] = new WP_User( 50, 'administrator@example.test', 'existing' );
$id      = seed_application();
$refused = approve( $id );
ck( 'the contact address already belongs to somebody else\'s account, refused before a record is minted', array( $refused->get_error_code(), $GLOBALS['journal'] ), array( 'wpcpm_sapp_conflict', array() ) );
ck( 'nothing was stamped, the state is untouched, and the lock is released', array( get_post_meta( $id, WPCPM_Sponsor_Application::META_RECORD, true ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ), get_option( WPCPM_Sponsor_Approval::lock_name( $id ) ) ), array( '', 'new', false ) );
$id2                = seed_application( 'new', array( WPCPM_Sponsor_Application::META_USER => 50 ) );
$GLOBALS['journal'] = array();
$result             = approve( $id2 );
ck( 'but the application\'s own stamped user at that same address is not a conflict: the press proceeds', array( is_array( $result ), $result['user_id'] ), array( true, 50 ) );

echo "\n=== One press, everything in the spec's order ===\n";
reset_world();
$id     = seed_application();
$result = approve( $id );
$record = is_array( $result ) ? (string) $result['record'] : '';
ck( 'the answer names a key, the account, the offer and the category', array( $result['user_id'], $result['offer_id'], $result['term_id'] ), array( 40, 501, 77 ) );
ck( 'the side effects ran in the spec\'s order: key, row, account, category, logo, offer, invite, audit', $GLOBALS['journal'], array( 'key', 'row', 'account', 'category', 'logo', 'logo', 'offer', 'invite', 'audit' ) );
$row = WPCPM_Sponsors_Index::row( $record );
ck( 'a press mints a spo key, stamps it and inserts the Approved row with the application\'s answers', array(
	WPCPM_Sponsors_Index::is_key( $record ),
	0 === strpos( $record, 'spo' ),
	get_post_meta( $id, WPCPM_Sponsor_Application::META_RECORD, true ),
	$row['name'],
	$row['website'],
	$row['status'],
	$row['option'],
	$row['anything'],
	$row['contact_person'],
	$row['contact_email'],
	$row['consent'],
	$row['dashboard_account'],
	$row['created'],
), array(
	true,
	true,
	$record,
	'TEST Sponsor',
	'https://test-sponsor.example',
	'Approved',
	'Sponsor mentors + tools/services',
	'We make widgets.',
	'Rep One',
	'administrator@example.test',
	true,
	true,
	gmdate( 'Y-m-d' ),
) );
ck( 'the account is stamped, and the state is approved', array( get_post_meta( $id, WPCPM_Sponsor_Application::META_USER, true ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ) ), array( 40, 'approved' ) );
ck( 'three events say what the site did', events_of( $id ), array( 'sponsor record created on the site', 'account created', 'approved' ) );
ck( 'and the approval stamps the decision time Recently decided sorts by (clean-up 1.98.1)', (int) get_post_meta( $id, WPCPM_Sponsor_Application::META_DECIDED, true ) > 0, true );
ck( 'the logo record says the site owns both halves', WPCPM_Sponsors_Index::logo_record( $record ), array( 'colour' => 640, 'white' => 641, 'source' => 'site', 'airtable_id' => '' ) );
ck( 'and the attachments now belong to the account, and are published in the same write', array( $GLOBALS['authored'], $GLOBALS['statused'] ), array( array( 640 => 40, 641 => 40 ), array( 640 => 'inherit', 641 => 'inherit' ) ) );
ck( 'the account was invited once, through the queue', $GLOBALS['mail_queue'], array( 40 ) );
$entry = end( $GLOBALS['audit'] );
ck( 'the log row names the approval, the record, the manager and what was made', array( $entry['kind'], $entry['sponsor'], $entry['actor'], $entry['ground'], $entry['evidence'], $entry['data'] ), array( 'application_approved', $record, 3, 'manager', 'live', array( 'application' => $id, 'user' => 40, 'offer' => 501, 'term' => 77 ) ) );
ck( 'the lock is released', get_option( WPCPM_Sponsor_Approval::lock_name( $id ) ), false );
ck( 'and nothing this class wrote is autoloaded', in_array( true, array_values( $GLOBALS['opts_autoload'] ), true ), false );
ck( 'a second press of a decided application is refused', approve( $id )->get_error_code(), 'wpcpm_sapp_state' );

echo "\n=== The tier the applicant was considering, onto the record (1.113.0) ===\n";
// The application stores the tier question's key, and approval copies it into `tier_requested`,
// which the Sponsors screen's Tier box reads. Only what the question offers is copied: a tier or
// "not sure yet". No answer, a tier the ladder does not have, a label in place of its key, and an
// application filed before the question existed all leave the field empty.
$requested = array();
foreach ( array( 'backer' => 'backer', 'unsure' => 'unsure', 'no answer' => '', 'unknown tier' => 'gold', 'a label' => 'Backer', 'before the question' => null ) as $case => $answer ) {
	reset_world();
	$answers = stored_answers();
	if ( null !== $answer ) {
		$answers['Tier considered'] = $answer;
	}
	$result             = approve( seed_application( 'new', array(), $answers ) );
	$requested[ $case ] = is_array( $result ) ? WPCPM_Sponsors_Index::row( $result['record'] )['tier_requested'] : 'refused';
}
ck( 'an application considering Backer gives the record Backer, one not sure yet gives it not sure yet, and anything else leaves it empty', $requested, array( 'backer' => 'backer', 'unsure' => 'unsure', 'no answer' => '', 'unknown tier' => '', 'a label' => '', 'before the question' => '' ) );

echo "\n=== The support question, no longer asked (1.123.12) ===\n";
// The form stopped asking "How would you like to support WP Credits?" in 1.123.12, since the tiers
// cover it. An application filed since holds no answer under `Sponsorship options`, and approving
// it makes the same record, with the Sponsorship option left empty for an Administrator to set on
// the Edit form if the program wants one; an application filed before still carries its answer to
// the record, as it always did (the main approval above).
$support = array();
foreach ( array( 'filed since' => null, 'filed before' => 'Sponsor one or multiple mentors' ) as $case => $answer ) {
	reset_world();
	$answers = stored_answers();
	unset( $answers['Sponsorship options'] );
	$answers['Tier considered'] = 'backer';
	if ( null !== $answer ) {
		$answers['Sponsorship options'] = $answer;
	}
	$result           = approve( seed_application( 'new', array(), $answers ) );
	$row              = is_array( $result ) ? WPCPM_Sponsors_Index::row( $result['record'] ) : array();
	$support[ $case ] = is_array( $result ) ? array( $row['name'], $row['status'], $row['option'], $row['tier_requested'], $row['anything'] ) : array( 'refused', $result->get_error_code() );
}
ck( 'an application with no support answer is approved into the same record with the option empty, and one filed before keeps its answer on the record', $support, array(
	'filed since'  => array( 'TEST Sponsor', 'Approved', '', 'backer', 'We make widgets.' ),
	'filed before' => array( 'TEST Sponsor', 'Approved', 'Sponsor one or multiple mentors', 'backer', 'We make widgets.' ),
) );

echo "\n=== A press that died at the account, finished by the next ===\n";
reset_world();
$id = seed_application();
$GLOBALS['account_dies'] = true;
try {
	approve( $id );
	$died = false;
} catch ( Exception $e ) {
	$died = true;
}
$half_record = WPCPM_Sponsor_Approval::stamped_record( $id );
ck( 'the run died after the key and the row landed', array( $died, $GLOBALS['journal'], WPCPM_Sponsors_Index::is_key( $half_record ) ), array( true, array( 'key', 'row', 'account' ), true ) );
ck( 'and left its lock behind', is_int( get_option( WPCPM_Sponsor_Approval::lock_name( $id ) ) ), true );
ck( 'which the queue reads as half done', WPCPM_Sponsor_Approval::is_half_done( $id ), true );
update_option( WPCPM_Sponsor_Approval::lock_name( $id ), time() - WPCPM_Sponsor_Approval::LOCK_TIMEOUT - 1, false );
$GLOBALS['journal'] = array();
$result = approve( $id );
ck( 'the half-done path finishes the rest in order, minting no second key and inserting no second row', array( is_array( $result ), $GLOBALS['journal'], $result['record'] ), array( true, array( 'account', 'category', 'logo', 'logo', 'offer', 'invite', 'audit' ), $half_record ) );
ck( 'one record, one account, one invitation', array( $result['record'], count( $GLOBALS['users'] ) - 1, $GLOBALS['mail_queue'] ), array( $half_record, 1, array( 40 ) ) );
ck( 'and the queue no longer calls it half done', WPCPM_Sponsor_Approval::is_half_done( $id ), false );

echo "\n=== The account refused, then sorted out ===\n";
reset_world();
$id = seed_application();
$GLOBALS['account_answer'] = array( 'status' => 'provision-refused', 'user_id' => 0, 'created' => false, 'already' => false, 'detail' => 'That account belongs to a student.' );
$refused = approve( $id );
ck( 'the provisioning path\'s refusal is the approval\'s, with its own words', array( $refused->get_error_code(), $refused->get_error_message() ), array( 'wpcpm_sapp_account', 'That account belongs to a student.' ) );
$half_record = WPCPM_Sponsor_Approval::stamped_record( $id );
ck( 'the record stands stamped, the state is still open, the lock is released, and nothing after the row ran', array( WPCPM_Sponsors_Index::is_key( $half_record ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ), get_option( WPCPM_Sponsor_Approval::lock_name( $id ) ), $GLOBALS['journal'] ), array( true, 'new', false, array( 'key', 'row', 'account' ) ) );
$GLOBALS['account_answer'] = null;
$GLOBALS['journal']        = array();
$result = approve( $id );
ck( 'once the account is sorted out, the next press finishes without a second record', array( is_array( $result ), $GLOBALS['journal'][0], $result['record'] ), array( true, 'account', $half_record ) );

echo "\n=== A category failure refuses after the record and the account ===\n";
reset_world();
$id                          = seed_application();
$GLOBALS['category_answer'] = 0;
$refused                    = approve( $id );
ck( 'ensure_terms() answering with no term refuses the approval', $refused->get_error_code(), 'wpcpm_sapp_category' );
$half_record = WPCPM_Sponsor_Approval::stamped_record( $id );
ck( 'the record and the account already stand, the state is still open, and the lock is released', array( WPCPM_Sponsors_Index::is_key( $half_record ), get_post_meta( $id, WPCPM_Sponsor_Application::META_USER, true ), get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ), get_option( WPCPM_Sponsor_Approval::lock_name( $id ) ) ), array( true, 40, 'new', false ) );
$GLOBALS['category_answer'] = null;
$GLOBALS['journal']         = array();
$result                     = approve( $id );
ck( 'the second press completes without a second record or a second account', array( is_array( $result ), $GLOBALS['journal'], $result['record'] ), array( true, array( 'account', 'category', 'logo', 'logo', 'offer', 'invite', 'audit' ), $half_record ) );
ck( 'and writes no second record created or account created event', events_of( $id ), array( 'sponsor record created on the site', 'account created', 'approved' ) );

echo "\n=== A row the store refuses stops the approval, and the next press inserts it ===\n";
// A failed insert used to go on to the account, which the
// provisioning path refused for a key the store does not hold, and the manager read "the sponsor
// record was created" under it; pressing Add as that sentence advised made a second record under
// a second key. The refusal is the approval's own now, before the account is asked for.
reset_world();
$id                      = seed_application();
$GLOBALS['insert_fails'] = true;
$refused                 = approve( $id );
$half_record             = WPCPM_Sponsor_Approval::stamped_record( $id );
ck( 'a row the store refuses stops the approval with its own code and sentence, before the account is asked for', array( is_wp_error( $refused ) ? $refused->get_error_code() : 'approved', is_wp_error( $refused ) ? $refused->get_error_message() : '', $GLOBALS['journal'] ), array( 'wpcpm_sapp_record', 'The sponsor record could not be saved just now. Press Approve again in a moment.', array( 'key' ) ) );
ck( 'the key stays stamped, the store holds no row, no account is made, the state is untouched and the lock is released', array( WPCPM_Sponsors_Index::is_key( $half_record ), WPCPM_Sponsors_Index::has( $half_record ), count( $GLOBALS['users'] ) - 1, get_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, true ), get_option( WPCPM_Sponsor_Approval::lock_name( $id ) ) ), array( true, false, 0, 'new', false ) );
ck( 'and the history names no record the store does not hold', events_of( $id ), array() );
$GLOBALS['journal'] = array();
$result             = approve( $id );
ck( 'the next press inserts the row under the stamped key and finishes, minting no second key', array( is_array( $result ), $GLOBALS['journal'], is_array( $result ) ? $result['record'] : '', array_keys( WPCPM_Sponsors_Index::rows() ) ), array( true, array( 'row', 'account', 'category', 'logo', 'logo', 'offer', 'invite', 'audit' ), $half_record, array( $half_record ) ) );
ck( 'and the history says the record was created once, by the press that created it', events_of( $id ), array( 'sponsor record created on the site', 'account created', 'approved' ) );

echo "\n=== A second press creates no second record ===\n";
reset_world();
$id                        = seed_application();
$GLOBALS['users'][77]      = new WP_User( 77, 'attached@example.test', 'attached' );
$GLOBALS['account_answer'] = array( 'status' => 'provision-attached', 'user_id' => 77, 'created' => false, 'already' => true, 'detail' => '' );
$result                    = approve( $id );
ck( 'the account is stamped on the application even though it was attached, not created', array( is_array( $result ), get_post_meta( $id, WPCPM_Sponsor_Application::META_USER, true ) ), array( true, 77 ) );
ck( 'but no account created event is written for an account that was only attached', events_of( $id ), array( 'sponsor record created on the site', 'approved' ) );

echo "\n=== The halves that answer for themselves ===\n";
reset_world();
$id     = seed_application();
$first  = approve( $id );
update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, 'new' );
$GLOBALS['journal'] = array();
$again = approve( $id );
ck( 'a second press creates no second record: the same key both times, and no key or row step run again', array( $again['record'], $again['record'] === $first['record'], $GLOBALS['journal'] ), array( $first['record'], true, array( 'account', 'category', 'logo', 'logo', 'offer', 'audit' ) ) );
ck( 'and attaches nothing new, seeds nothing, invites nobody', array( $again['offer_id'], count( $GLOBALS['mail_queue'] ) ), array( 0, 1 ) );
ck( 'and writes no second account stamp or event', events_of( $id ), array( 'sponsor record created on the site', 'account created', 'approved', 'approved' ) );

echo "\n=== Without a logo ===\n";
reset_world();
$id     = seed_application( 'new', array(), array(), false );
$result = approve( $id );
ck( 'no attachment, no logo step', $GLOBALS['journal'], array( 'key', 'row', 'account', 'category', 'offer', 'invite', 'audit' ) );
ck( 'and the logo record stays untouched', WPCPM_Sponsors_Index::logo_record( $result['record'] ), array( 'colour' => 0, 'white' => 0, 'source' => '', 'airtable_id' => '' ) );

echo "\n=== The index's insert ===\n";
reset_world();
ck( 'a malformed record is refused', WPCPM_Sponsors_Index::insert( array( 'record_id' => 'nope', 'name' => 'X' ) ), false );
// 1.111.0: the store trims the name on the way in (the site is the record now, so nothing
// needs the base's exact bytes the way the old, option-backed index did); the input keeps its
// trailing space on purpose, to prove that trim happens.
ck( 'a row is inserted, shaped, and found', array( WPCPM_Sponsors_Index::insert( array( 'record_id' => 'recSPN00000000001', 'name' => 'Inserted ', 'status' => 'Approved' ) ), WPCPM_Sponsors_Index::has( 'recSPN00000000001' ), WPCPM_Sponsors_Index::row( 'recSPN00000000001' )['name'], WPCPM_Sponsors_Index::row( 'recSPN00000000001' )['dashboard_account'] ), array( true, true, 'Inserted', false ) );
WPCPM_Sponsors_Index::insert( array( 'record_id' => 'recSPN00000000001', 'name' => 'Renamed', 'status' => 'Approved' ) );
ck( 'inserting again replaces the row and adds no second', array( count( WPCPM_Sponsors_Index::rows() ), WPCPM_Sponsors_Index::row( 'recSPN00000000001' )['name'] ), array( 1, 'Renamed' ) );

echo "\n=== A test application, approved into a test sponsor (1.123.18) ===\n";
// Beside Approve sits "Test sponsor: not listed publicly": ticked, the record the approval makes is
// a test sponsor from its first write, so it is listed nowhere public; the box is the
// Administrator's to tick, so approve() reads nothing from the name.
reset_world();
$t_result = WPCPM_Sponsor_Approval::approve( seed_application(), 3, true );
ck( 'approved with the box ticked, the record is a test sponsor', is_array( $t_result ) ? WPCPM_Sponsors_Index::row( $t_result['record'] )['test'] : 'refused', true );
reset_world();
$t_result = approve( seed_application() );
ck( 'and approved without it, it is not', is_array( $t_result ) ? WPCPM_Sponsors_Index::row( $t_result['record'] )['test'] : 'refused', false );
reset_world();
$t_id                    = seed_application();
$GLOBALS['account_dies'] = true;
try {
	WPCPM_Sponsor_Approval::approve( $t_id, 3, false );
} catch ( Exception $e ) {
	unset( $e );
}
update_option( WPCPM_Sponsor_Approval::lock_name( $t_id ), time() - WPCPM_Sponsor_Approval::LOCK_TIMEOUT - 1, false );
$t_result = WPCPM_Sponsor_Approval::approve( $t_id, 3, true );
ck( 'a press that finishes a half-done approval leaves the record flagged as its own box said', array( is_array( $t_result ), WPCPM_Sponsors_Index::row( WPCPM_Sponsor_Approval::stamped_record( $t_id ) )['test'] ), array( true, true ) );

echo "\n=== Uninstall ===\n";
reset_world();
update_option( 'wpcpm_sapp_lock_7', time(), false );
update_option( 'wpcpm_sapp_lock_8', time(), false );
update_option( 'wpcpm_sponsor_logo_recSPN00000000001', array( 'colour' => 1 ), false );
ck( 'delete_all() sweeps every lock and nothing else', array( WPCPM_Sponsor_Approval::delete_all(), array_keys( $GLOBALS['opts'] ) ), array( 2, array( 'wpcpm_sponsor_logo_recSPN00000000001' ) ) );

echo "\n=== House rules ===\n";
$src     = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-approval.php' );
$approve = substr( $src, (int) strpos( $src, 'function approve(' ) );
$approve = substr( $approve, 0, (int) strpos( $approve, "\n\t}\n" ) );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
ck( 'the lock comes after the refusals that write nothing and before the record', strpos( $approve, 'self::lock(' ) > strpos( $approve, 'self::approvable()' ) && strpos( $approve, 'self::lock(' ) < strpos( $approve, 'WPCPM_Sponsors_Index::mint_key(' ), true );
ck( 'the record is stamped before the row, the row before the account, the account before the rest', array(
	strpos( $approve, 'WPCPM_Sponsor_Application::META_RECORD, $record' ) < strpos( $approve, 'WPCPM_Sponsors_Index::insert(' ),
	strpos( $approve, 'WPCPM_Sponsors_Index::insert(' ) < strpos( $approve, 'WPCPM_Sponsors::provision_account(' ),
	strpos( $approve, 'WPCPM_Sponsors::provision_account(' ) < strpos( $approve, 'WPCPM_Sponsor_Posts::ensure_terms(' ),
	strpos( $approve, 'WPCPM_Sponsor_Posts::ensure_terms(' ) < strpos( $approve, 'self::record_logos(' ),
	strpos( $approve, 'self::record_logos(' ) < strpos( $approve, 'WPCPM_Sponsor_Offers::seed(' ),
	strpos( $approve, 'WPCPM_Sponsor_Offers::seed(' ) < strpos( $approve, 'WPCPM_Mail::queue_invites(' ),
	strpos( $approve, 'WPCPM_Mail::queue_invites(' ) < strpos( $approve, 'WPCPM_Institution_Audit::record_sponsor(' ),
), array( true, true, true, true, true, true, true ) );
ck( 'accounts come from the shipped provisioning path alone', array( strpos( $src, 'wp_insert_user(' ), strpos( $src, 'WPCPM_Roles::insert_user(' ) ), array( false, false ) );
ck( 'no typecast anywhere in the file: there is no payload left to send one in', strpos( $src, 'typecast' ), false );
ck( 'the approval class never touches the Dashboard account column: no PATCH, no create_records, at all', array( strpos( $src, 'update_records(' ), strpos( $src, 'create_records(' ) ), array( false, false ) );
ck( 'the approval never constructs WPCPM_Airtable: the class does not even exist in this run', class_exists( 'WPCPM_Airtable' ), false );

$sponsors = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsors.php' );
ck( 'provision() is the account half plus its four side effects, in one body each', array( substr_count( $sponsors, 'function provision_account(' ), substr_count( $sponsors, 'self::provision_account(' ), strpos( $sponsors, 'wp_insert_user(' ) ), array( 1, 1, false ) );

$application = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-application.php' );
ck( 'the queue now reaches the approval by name', array( false !== strpos( $application, 'WPCPM_Sponsor_Approval::approve(' ), strpos( $application, "call_user_func( array( 'WPCPM_Sponsor_Approval'" ) ), array( true, false ) );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
