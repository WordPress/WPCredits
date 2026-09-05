<?php
/**
 * The Sponsor Dashboard's three acting cards: profile, interests, sponsored mentors.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - The profile is an allowlist: eight fields, spelled exactly as the base spells them and
 *   checked against bin/fixtures/sponsors-table-fields.json, because update_records() sends no
 *   typecast and one field spelled wrong is a 422 for the whole PATCH - so one rejected field
 *   rejects the whole save, and the audit row names which fields changed, never their values.
 * - The interests card writes the six choices, an events list and a note in one PATCH, appends
 *   one dated line to the base's own history column, mails the assigned manager (or every
 *   manager when none is assigned, through WPCPM_Institutions::notify_managers()), and is
 *   ceilinged at five a day per account, the same as the mentor-interest form.
 * - The sponsored-mentors card never names a student: only mentors and their counts, read
 *   through WPCPM_Mentors_Sync::sponsorship() so a mentor who is not Active is counted and not
 *   named. The one press to say "I would like to sponsor this mentor" goes to the same mailer
 *   and the same ceiling.
 * - Every handler claims through WPCPM_Sponsor_Roster::claim() before it reads or writes
 *   anything else, asserted by counting the calls in the source.
 * - WPCPM_Sponsors_Dashboard (Task 10) is a stub here, called by array callable exactly as the
 *   cards call it, so bin/check-references.php keeps failing on a direct call to a method that
 *   is not declared yet rather than being fooled by this suite's own stand-in.
 *
 * Run from the plugin root:  php bin/test-sponsor-cards.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']  = array();
$GLOBALS['umeta'] = array();
$GLOBALS['users'] = array();

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}

/**
 * Enough of `WP_User` for the real sponsor classes to run against.
 *
 * The constructor's argument order (id, roles, name, email) is Task 8's own
 * (bin/test-sponsors-screen.php), kept unchanged here because the fixture below already
 * constructs every account in that order: `new WP_User( 1, array( 'administrator' ), 'Manager',
 * 'maciej@a8c.com' )` reads correctly against it with nothing to adjust.
 */
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, array $roles = array(), $name = '', $email = '' ) {
		$this->ID           = (int) $id;
		$this->roles        = $roles;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
}

/**
 * The two outcomes `post()` tells apart, Task 8's own shape: `wp_die()` raises one, and here
 * `WPCPM_Sponsors_Dashboard::leave()` (below) raises the other directly, never through
 * `wp_safe_redirect()` - the cards hand it their flash and their record and it throws itself.
 */
class WPCPM_Test_Die extends Exception {}
class WPCPM_Test_Redirect extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
// The real WPCPM_Field_Value::clean_url() (required below) needs both of these at call time.
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? $url : ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function add_action( $h, $c, $p = 10, $n = 1 ) {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
	}
	return false;
}
/** `get_users()` by meta key and value; a call with neither gets everyone. */
function get_users( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( ! isset( $args['meta_key'] ) ) { $out[] = $user; continue; }
		$value = $GLOBALS['umeta'][ (int) $id ][ $args['meta_key'] ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $args['meta_value'] ?? '' ) ) ) { $out[] = $user; }
	}
	return $out;
}
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	if ( ! $GLOBALS['nonce_ok'] ) {
		wp_die( 'The link you followed has expired.' );
	}
	return true;
}
function wp_die( $message = '', $code = 0 ) {
	throw new WPCPM_Test_Die( (string) $message . ( $code ? ' [' . (int) $code . ']' : '' ) );
}
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />'; }
function selected( $a, $b = true, $echo = true ) {
	$r = ( (string) $a === (string) $b ) ? ' selected="selected"' : '';
	if ( $echo ) { echo $r; }
	return $r;
}
function checked( $a, $b = true, $echo = true ) {
	$r = ( (string) $a === (string) $b ) ? ' checked="checked"' : '';
	if ( $echo ) { echo $r; }
	return $r;
}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function wp_date( $format, $ts = null, $zone = null ) { return gmdate( $format, (int) $ts ); }

class WPCPM_Airtable {
	public function update_records( $table, array $records ) { $GLOBALS['patched'][] = array( $table, $records ); return isset( $GLOBALS['airtable_fail'] ) ? new WP_Error( 'x', 'Airtable said no' ) : array( $records[0]['id'] => true ); }
	public function get_record( $table, $id ) { $GLOBALS['read'][] = $id; if ( isset( $GLOBALS['record_fail'] ) ) { return new WP_Error( 'x', 'no' ); } return array( 'id' => $id, 'fields' => isset( $GLOBALS['live_fields'] ) ? $GLOBALS['live_fields'] : array() ); }
	/* Copied from the real client (includes/class-wpcpm-airtable.php): the interests card's
	   live read passes its history cell through this before comparing it. */
	public static function flatten( $value, $glue = ', ' ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) { return (string) $value['name']; }
			$parts = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) { $parts[] = (string) $item; }
				elseif ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) { $parts[] = (string) $item['name']; }
			}
			return implode( $glue, array_filter( $parts, 'strlen' ) );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator'; const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
}
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache';
	public static function record_sponsor( array $e ) { $GLOBALS['audit'][] = $e; return count( $GLOBALS['audit'] ); }
}
class WPCPM_Request {
	// Both methods read $_POST directly, exactly as the real class does (includes/class-wpcpm-request.php):
	// the cards' own direct `isset( $_POST[...] )` presence checks (verbatim from the brief, matching
	// the codebase's established pattern of checking $_POST before reading it through this class) must
	// see the same field this class reads, which only holds if both read the one superglobal.
	public static function posted_text( $n, $f = '' ) { return isset( $_POST[ $n ] ) ? trim( (string) $_POST[ $n ] ) : $f; }

	/**
	 * The real `WPCPM_Request::posted_lines()` (includes/class-wpcpm-request.php) hands back
	 * one trimmed string with its line breaks kept, not an array: every other caller
	 * (class-wpcpm-institution-import-form.php, class-wpcpm-semester-report-screen.php) treats
	 * the result as a block of text, never as a list to iterate. This stand-in mirrors that
	 * contract - it cleans the posted value the same way (split on newlines, trim each line,
	 * drop the empty ones) and joins the survivors back into one string - so a card that wants a
	 * list of individual lines splits the result itself, exactly as it would have to against the
	 * real class.
	 *
	 * @param string $n Key in the posted fields.
	 * @param string $f Fallback when the key is absent.
	 * @return string
	 */
	public static function posted_lines( $n, $f = '' ) {
		if ( ! isset( $_POST[ $n ] ) ) {
			return $f;
		}

		$lines = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $_POST[ $n ] ) as $line ) {
			$line = trim( $line );

			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		return implode( "\n", $lines );
	}
}
class WPCPM_Settings { public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } }

class WPCPM_Ceiling {
	public static function claim( $key, $limit, $window, $amount = 1 ) { $n = isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0; if ( $n + $amount > $limit ) { return false; } $GLOBALS['buckets'][ $key ] = $n + $amount; return true; }
	public static function count( $key, $window ) { return isset( $GLOBALS['buckets'][ $key ] ) ? $GLOBALS['buckets'][ $key ] : 0; }
	public static function key( ...$p ) { return implode( ':', $p ); }
}
class WPCPM_Mail {
	public static function send( $user, $context, $build ) { $GLOBALS['sent'][] = array( 'user', $user->ID, $context, call_user_func( $build, $user ) ); return true; }
	public static function send_to( $email, $context, $build, $locale = '' ) { $GLOBALS['sent'][] = array( 'to', $email, $context, call_user_func( $build, null ) ); return true; }
}
class WPCPM_Institutions { public static function notify_managers( $context, $build, $key = 'agreement_notify' ) { $GLOBALS['sent'][] = array( 'managers', $key, $context, call_user_func( $build, null ) ); return isset( $GLOBALS['managers_reachable'] ) ? (int) $GLOBALS['managers_reachable'] : 2; } }
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
	public static function sponsorship() { return $GLOBALS['sponsorship']; }
}
class WPCPM_Mentors_Dashboard { public static function get_mentees( $user_id ) { return isset( $GLOBALS['mentees'][ $user_id ] ) ? $GLOBALS['mentees'][ $user_id ] : array(); } }
class WPCPM_Sponsor_Offers { public static function offers_of( $record ) { return isset( $GLOBALS['offers'][ $record ] ) ? $GLOBALS['offers'][ $record ] : array(); } }
class WPCPM_Sponsors_Dashboard {
	const FLASH = 'sponsor_dashboard';
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
	public static function leave( $status, $card, $record = '' ) { $GLOBALS['left'] = array( $status, $card, $record ); throw new WPCPM_Test_Redirect( $status ); }
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-profile.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-mentors.php';

// The fixture: one sponsor with a member (user 5), a manager (user 1), the team, three mentors.
$A = 'recSPONSOR0000001'; $M1 = 'recMENTOR00000001'; $M2 = 'recMENTOR00000002'; $M3 = 'recMENTOR00000003'; $M4 = 'recMENTOR00000004';
WPCPM_Sponsors_Index::write( array( $A => array( 'name' => 'miniOrange ', 'status' => 'Approved', 'website' => 'https://plugins.miniorange.com/', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'product_type' => 'Hosting', 'offer' => 'One year free', 'instructions' => 'Use the code.', 'more_info' => '', 'support' => array( 'Sponsor tools or services' ), 'interests' => '2026-09-01 by Rep One: Sponsor tools or services', 'manager' => 'recTEAM0000000001', 'mentors' => array( $M1, $M2, $M4 ) ) ), time() );
WPCPM_Sponsors_Index::write_team( array( 'recTEAM0000000001' => array( 'name' => 'Maciej (Matt) Pilarski', 'email' => 'maciej@a8c.com', 'calendly' => 'https://calendly.com/matt' ) ), time() );
$GLOBALS['settings'] = array( 'sponsors_table' => 'tblSPONSORS' );
$GLOBALS['users'] = array( 1 => new WP_User( 1, array( 'administrator' ), 'Manager', 'maciej@a8c.com' ), 5 => new WP_User( 5, array( 'wpcpm_sponsor' ), 'Rep One', 'maciej@a8c.com' ), 42 => new WP_User( 42, array( 'wpcpm_mentor' ), 'Emilia', 'maciej@a8c.com' ) );
$GLOBALS['manage'] = array( 1 );
$GLOBALS['umeta'][5] = array( WPCPM_Sponsor_Members::META_RECORD_ID => $A, WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['sponsorship'] = array(
	$M1 => array( 'name' => 'Emilia Pustelnik', 'profile' => 'https://profiles.wordpress.org/emilia/', 'status' => 'Active', 'user_id' => 42, 'sponsored' => true, 'wants' => false, 'company' => array( $A ), 'expertise' => array( 'Core' ) ),
	$M2 => array( 'name' => 'Nilo Velez', 'profile' => 'https://profiles.wordpress.org/nilo/', 'status' => 'Active', 'user_id' => 0, 'sponsored' => true, 'wants' => false, 'company' => array( $A ), 'expertise' => array() ),
	$M3 => array( 'name' => 'Ana Looking', 'profile' => 'https://profiles.wordpress.org/ana/', 'status' => 'Active', 'user_id' => 0, 'sponsored' => false, 'wants' => true, 'company' => array(), 'expertise' => array( 'Polyglots', 'Community' ) ),
);
$GLOBALS['mentees'][42] = array( array( 'name' => 'Student One', 'is_past' => false ), array( 'name' => 'Student Two', 'is_past' => false ), array( 'name' => 'Old Student', 'is_past' => true ) );
$GLOBALS['uid'] = 5; $GLOBALS['nonce_ok'] = true; $GLOBALS['patched'] = array(); $GLOBALS['sent'] = array(); $GLOBALS['audit'] = array(); $GLOBALS['buckets'] = array();
$context = array( 'can_manage' => false, 'open' => '', 'viewer' => $GLOBALS['users'][5] );

// $_POST itself, not a side channel: both new cards check `isset( $_POST[...] )` directly
// before reading a field (the established pattern for a presence check, matching how the real
// WPCPM_Request::posted_text() reads $_POST directly too), so a helper that populated anything
// else would leave that check always false.
function post( array $fields, $action ) { $_POST = $fields; $GLOBALS['left'] = null; try { call_user_func( $action ); } catch ( WPCPM_Test_Redirect $e ) { return $GLOBALS['left']; } catch ( WPCPM_Test_Die $e ) { return array( 'die', $e->getMessage() ); } return array( 'fell-through' ); }
function card( $class, $record, $context ) { ob_start(); call_user_func( array( $class, 'render' ), $record, $context ); return ob_get_clean(); }

$fail   = 0;
$checks = 0;
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

echo "=== Profile: the allowlist ===\n";
ck( 'the eight fields, spelled as the base spells them', array_column( WPCPM_Sponsor_Profile::FIELDS, 'name' ), array( 'Website', 'Contact Person Full Name', 'Contact Email', 'Type of product', 'Offer', 'Brief instructions', 'More info link', "Anything else you'd like to share." ) );
$fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/sponsors-table-fields.json' ), true );
ck( 'every one is a field of the table', array_values( array_diff( array_column( WPCPM_Sponsor_Profile::FIELDS, 'name' ), $fixture['fields'] ) ), array() );
ck( 'the product choices are the fixture\'s, byte for byte', WPCPM_Sponsor_Profile::CHOICES['Type of product'], $fixture['choices']['Type of product'] );
ck( 'and the support choices are too', WPCPM_Sponsor_Interests::CHOICES, $fixture['choices']['How would you like to support WP Credits?'] );
ck( 'a URL without a scheme is completed', WPCPM_Sponsor_Profile::clean( 'website', 'plugins.miniorange.com' ), array( 'ok' => true, 'value' => 'https://plugins.miniorange.com' ) );
ck( 'a URL with a user is refused', WPCPM_Sponsor_Profile::clean( 'website', 'https://name@host.test' )['ok'], false );
ck( 'and a URL with a password too', WPCPM_Sponsor_Profile::clean( 'more_info', 'https://user:pw@host.test/x' )['ok'], false );
ck( 'a choice spelled another way is refused', WPCPM_Sponsor_Profile::clean( 'product_type', 'hosting' )['ok'], false );
ck( 'an empty choice clears the cell', WPCPM_Sponsor_Profile::clean( 'product_type', '' ), array( 'ok' => true, 'value' => null ) );
ck( 'text is capped at MAX_TEXT', mb_strlen( WPCPM_Sponsor_Profile::clean( 'instructions', str_repeat( 'x', 5000 ) )['value'] ), 4000 );
ck( 'an address that is not one is refused', WPCPM_Sponsor_Profile::clean( 'contact_email', 'not-an-address' )['ok'], false );
ck( 'a field outside the allowlist is refused', WPCPM_Sponsor_Profile::clean( 'status', 'Approved' )['ok'], false );

echo "\n=== Profile: saving ===\n";
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.miniorange.com/', 'wpcpm_contact_person' => 'Rep One', 'wpcpm_contact_email' => 'maciej@a8c.com', 'wpcpm_product_type' => 'Plugin', 'wpcpm_offer' => 'One year free', 'wpcpm_instructions' => 'Use the code.', 'wpcpm_more_info' => '', 'wpcpm_anything' => '' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a member saves, and lands on the profile card', $r, array( 'profile-saved', 'profile', $A ) );
ck( 'only the changed cell is written, spelled as the base spells it', $GLOBALS['patched'][0][1][0]['fields'], array( 'Type of product' => 'Plugin' ) );
ck( 'the index says so at once', WPCPM_Sponsors_Index::row( $A )['product_type'], 'Plugin' );
ck( 'and an audit row names the fields, not their values', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data']['fields'], end( $GLOBALS['audit'] )['ground'] ), array( 'profile_saved', array( 'product_type' ), 'member' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.miniorange.com/', 'wpcpm_contact_person' => 'Rep One', 'wpcpm_contact_email' => 'maciej@a8c.com', 'wpcpm_product_type' => 'Plugin', 'wpcpm_offer' => 'One year free', 'wpcpm_instructions' => 'Use the code.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'nothing changed is said, and nothing is written', array( $r[0], count( $GLOBALS['patched'] ) ), array( 'profile-unchanged', 1 ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => 'We can offer licences.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'the free-text field saves', array( $r[0], end( $GLOBALS['patched'] )[1][0]['fields'] ), array( 'profile-saved', array( "Anything else you'd like to share." => 'We can offer licences.' ) ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_anything' => 'We can offer licences.' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'and is remembered, so the same text again is no change', array( $r[0], count( $GLOBALS['patched'] ) ), array( 'profile-unchanged', 2 ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://x@y', 'wpcpm_product_type' => 'Plugin' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'one rejected field rejects the whole save', array( $r[0], count( $GLOBALS['patched'] ) ), array( 'profile-rejected', 2 ) );
$GLOBALS['airtable_fail'] = true;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 'Two years free' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'the base refusing is said, and the index is untouched', array( $r[0], WPCPM_Sponsors_Index::row( $A )['offer'] ), array( 'profile-failed', 'One year free' ) );
unset( $GLOBALS['airtable_fail'] );
$GLOBALS['uid'] = 9; $GLOBALS['users'][9] = new WP_User( 9, array( 'subscriber' ), 'Stranger', 'maciej@a8c.com' );
$before_patched = count( $GLOBALS['patched'] );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_offer' => 'Hijack' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a stranger is refused with the one message, and metered', array( $r[0], $GLOBALS['buckets']['sponsor-refused:9'], count( $GLOBALS['patched'] ) ), array( 'refused', 1, $before_patched ) );
$GLOBALS['uid'] = 5;
$html = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'the card is the canonical disclosure with the eight fields prefilled', substr_count( $html, 'name="wpcpm_' ) >= 9 && false !== strpos( $html, 'id="wpcpm-sponsor-profile"' ) && false !== strpos( $html, 'wpcpm-group__disclosure' ) && false !== strpos( $html, 'value="Plugin"' ), true );
ck( 'closed until a flash names it', strpos( $html, '<details' ) !== false && strpos( $html, ' open>' ) === false, true );
ck( 'and open when one does', strpos( card( 'WPCPM_Sponsor_Profile', $A, array_merge( $context, array( 'open' => 'profile' ) ) ), ' open>' ) !== false, true );
ck( 'the form says what changing the address does, and carries the guard', false !== strpos( $html, 'changes the address Airtable holds' ) && false !== strpos( $html, 'data-wpcpm-once' ), true );

echo "\n=== Profile: the three fields the primary offer owns ===\n";
$GLOBALS['offers'][ $A ] = array( 900 => array( 'id' => 900, 'primary' => true ) );
$owned = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'with a primary offer the three fields it owns are not inputs here', array( strpos( $owned, 'name="wpcpm_offer"' ), strpos( $owned, 'name="wpcpm_instructions"' ), strpos( $owned, 'name="wpcpm_more_info"' ) ), array( false, false, false ) );
ck( 'and the card says where they are edited instead', false !== strpos( $owned, 'edited on the Offers card' ), true );
$before_patched = count( $GLOBALS['patched'] );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_website' => 'https://plugins.miniorange.com/', 'wpcpm_contact_person' => 'Rep One', 'wpcpm_contact_email' => 'maciej@a8c.com', 'wpcpm_product_type' => 'Plugin', 'wpcpm_anything' => 'We can offer licences.', 'wpcpm_offer' => 'Changed by the profile' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a posted offer is ignored, so the save writes nothing', array( $r[0], count( $GLOBALS['patched'] ), WPCPM_Sponsors_Index::row( $A )['offer'] ), array( 'profile-unchanged', $before_patched, 'One year free' ) );
unset( $GLOBALS['offers'] );
$unowned = card( 'WPCPM_Sponsor_Profile', $A, $context );
ck( 'and without an offer the three inputs are back', array( false !== strpos( $unowned, 'name="wpcpm_offer"' ), false !== strpos( $unowned, 'name="wpcpm_instructions"' ), false !== strpos( $unowned, 'name="wpcpm_more_info"' ) ), array( true, true, true ) );

echo "\n=== Interests ===\n";
// The append now reads the base's own current value first (item 1 of the final review fix
// wave): Airtable starts in agreement with the index's fixture, the one line already there.
$GLOBALS['live_fields'] = array( 'Sponsorship interests' => '2026-09-01 by Rep One: Sponsor tools or services' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_support' => array( 'Sponsor tools or services', 'Made up' ), 'wpcpm_events' => "WordCamp Europe 2027\nWordCamp Asia 2027", 'wpcpm_note' => 'Happy to help.' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'an interest is sent', $r, array( 'interest-sent', 'interests', $A ) );
$cells = end( $GLOBALS['patched'] )[1][0]['fields'];
ck( 'the multiple select is written with the known choices only', $cells['How would you like to support WP Credits?'], array( 'Sponsor tools or services' ) );
ck( 'and one dated line is appended to the history', substr_count( $cells['Sponsorship interests'], "\n" ) === 1 && false !== strpos( $cells['Sponsorship interests'], 'by Rep One: Sponsor tools or services; events: WordCamp Europe 2027, WordCamp Asia 2027; note: Happy to help.' ), true );
ck( 'the assigned manager is mailed, once, in the interest context', array( count( $GLOBALS['sent'] ), $GLOBALS['sent'][0][0], $GLOBALS['sent'][0][1], $GLOBALS['sent'][0][2] ), array( 1, 'user', 1, 'sponsor-interest' ) );
ck( 'the mail names the sponsor and what it said', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'miniOrange' ) && false !== strpos( $GLOBALS['sent'][0][3]['body'], 'WordCamp Europe 2027' ), true );
ck( 'the audit row carries the line', end( $GLOBALS['audit'] )['kind'] === 'sponsor_interest' && false !== strpos( end( $GLOBALS['audit'] )['message'], 'Happy to help.' ), true );
$r = post( array( 'wpcpm_sponsor' => $A ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'nothing ticked and nothing written is said', $r[0], 'interest-empty' );
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '' ) );
$GLOBALS['sent'] = array();
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_support' => array( 'Other (please specify)' ), 'wpcpm_note' => 'x' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'with no manager assigned, every manager is told, through the sponsor_notify setting', array( $GLOBALS['sent'][0][0], $GLOBALS['sent'][0][1] ), array( 'managers', 'sponsor_notify' ) );
$GLOBALS['managers_reachable'] = 0;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'Please reach out.' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'when nobody could be mailed, the sponsor is not told that somebody was', $r[0], 'interest-unsent' );
unset( $GLOBALS['managers_reachable'] );
for ( $i = 0; $i < 3; $i++ ) { post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'again' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) ); }
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'sixth' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'five a day per account', $r[0], 'interest-ceiling' );
$html = card( 'WPCPM_Sponsor_Interests', $A, $context );
ck( 'the card lists the six choices, the events box, the note and the history', substr_count( $html, 'name="wpcpm_support[]"' ) === 6 && false !== strpos( $html, 'wpcpm_events' ) && false !== strpos( $html, '2026-09-01 by Rep One' ), true );

echo "\n=== The live history is read right before the append ===\n";
// User 5's ceiling is already at five for today (the loop above); this scenario is about
// the read-before-append, not the ceiling, so it gets a fresh bucket like the mentors
// section below already does.
$GLOBALS['buckets']     = array();
$GLOBALS['live_fields'] = array( 'Sponsorship interests' => "2026-09-01 by Rep One: old line\n2026-09-02 by Rep One: grid edit" );
$GLOBALS['patched']     = array();
post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'fresh note' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
$cells        = end( $GLOBALS['patched'] )[1][0]['fields'];
$new_line     = WPCPM_Sponsor_Interests::line( array(), array(), 'fresh note', 'Rep One', '1970-01-01' );
ck( 'the history is read live before the append, so a grid edit survives', $cells['Sponsorship interests'], $GLOBALS['live_fields']['Sponsorship interests'] . "\n" . $new_line );
unset( $GLOBALS['live_fields'] );

$GLOBALS['record_fail'] = true;
$GLOBALS['patched']     = array();
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_note' => 'unread history' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a history that cannot be read is not appended to', array( $r[0], $GLOBALS['patched'] ), array( 'interest-failed', array() ) );
unset( $GLOBALS['record_fail'] );

echo "\n=== A member whose sponsor left the index cannot be written back to ===\n";
// A stamp naming a record the index no longer holds: the roster's claim() still allows the
// member (the stamp is well-formed and the ground is ungated), but WPCPM_Sponsors_Index::row()
// answers null. A different account from every other check in this file, so its own ceiling
// is untouched by anything above or below.
$GLOBALS['users'][6] = new WP_User( 6, array( 'wpcpm_sponsor' ), 'Departed Rep', 'maciej@a8c.com' );
$GLOBALS['umeta'][6] = array( WPCPM_Sponsor_Members::META_RECORD_ID => 'recSPONSOR0000009', WPCPM_Sponsor_Members::META_ACTIVE => 1 );
$GLOBALS['uid']      = 6;
$GLOBALS['patched']  = array();
$r = post( array( 'wpcpm_sponsor' => 'recSPONSOR0000009', 'wpcpm_offer' => 'Anything' ), array( 'WPCPM_Sponsor_Profile', 'handle_save' ) );
ck( 'a sponsor the index does not hold cannot be written back to (profile)', array( $r[0], $GLOBALS['patched'] ), array( 'profile-failed', array() ) );
$r = post( array( 'wpcpm_sponsor' => 'recSPONSOR0000009', 'wpcpm_note' => 'hello' ), array( 'WPCPM_Sponsor_Interests', 'handle_save' ) );
ck( 'a sponsor the index does not hold cannot be written back to (interests)', array( $r[0], $GLOBALS['patched'] ), array( 'interest-failed', array() ) );
$GLOBALS['uid'] = 5;

echo "\n=== Sponsored mentors ===\n";
$linked = WPCPM_Sponsor_Mentors::linked( $A );
ck( 'the linked mentors are named with their student counts, never a student\'s name', array( array_column( $linked['mentors'], 'name' ), $linked['mentors'][0]['current'], $linked['mentors'][0]['past'], $linked['others'] ), array( array( 'Emilia Pustelnik', 'Nilo Velez' ), 2, 1, 1 ) );
ck( 'linked() carries user_id, 0 for a mentor with no site account', array( $linked['mentors'][0]['user_id'], $linked['mentors'][1]['user_id'] ), array( 42, 0 ) );
ck( 'the mentors looking for a sponsor: Active, wanting, unsponsored', array_column( WPCPM_Sponsor_Mentors::looking(), 'name' ), array( 'Ana Looking' ) );
$html = card( 'WPCPM_Sponsor_Mentors', $A, $context );
ck( 'the card prints the mentors and the counts, and the one looking with a form', false !== strpos( $html, 'Emilia Pustelnik' ) && false !== strpos( $html, 'Ana Looking' ) && false !== strpos( $html, 'Polyglots' ) && 1 === substr_count( $html, 'value="' . WPCPM_Sponsor_Mentors::ACTION_INTEREST_MENTOR . '"' ), true );
ck( 'and no student is named anywhere on it', strpos( $html, 'Student One' ) === false && strpos( $html, 'Old Student' ) === false, true );
ck( 'a linked mentor with no site account yet says so, instead of a false zero', substr_count( $html, 'no site account yet' ), 1 );
ck( 'a linked mentor with an account still shows real counts', false !== strpos( $html, '2 students now, 1 before' ), true );
$saved_sponsorship      = $GLOBALS['sponsorship'];
$GLOBALS['sponsorship'] = array();
$stale = card( 'WPCPM_Sponsor_Mentors', $A, $context );
ck( 'before the mentors sync has filled the index the card says so, instead of calling every linked mentor inactive', array( false !== strpos( $stale, 'The mentor list refreshes with the next program sync.' ), strpos( $stale, 'not currently active' ) ), array( true, false ) );
ck( 'and the summary still counts the linked records', false !== strpos( $stale, '<span class="wpcpm-group__count">3</span>' ), true );
$GLOBALS['sponsorship'] = $saved_sponsorship;
$GLOBALS['buckets'] = array(); $GLOBALS['sent'] = array();
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => 'recTEAM0000000001' ) );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M3 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'interest in a mentor is sent to the manager and logged', array( $r[0], $GLOBALS['sent'][0][2], end( $GLOBALS['audit'] )['kind'] ), array( 'mentor-interest-sent', 'sponsor-interest', 'sponsor_interest_mentor' ) );
ck( 'the mail names the mentor', false !== strpos( $GLOBALS['sent'][0][3]['body'], 'Ana Looking' ), true );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M1 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'a mentor who is not looking cannot be asked for', $r[0], 'mentor-interest-unknown' );
WPCPM_Sponsors_Index::patch( $A, array( 'manager' => '' ) );
$GLOBALS['managers_reachable'] = 0;
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_mentor' => $M3 ), array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
ck( 'nobody could be told, so the sentence says so', $r[0], 'mentor-interest-failed' );
ck( 'but the interest is on record either way, with the count', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['data']['mailed'] ), array( 'sponsor_interest_mentor', 0 ) );
unset( $GLOBALS['managers_reachable'] );

echo "\n=== House rules ===\n";
$all = '';
foreach ( array( 'profile', 'interests', 'mentors' ) as $f ) { $all .= file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-' . $f . '.php' ); }
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $all ), 0 );
ck( 'every handler claims before it writes', substr_count( $all, 'WPCPM_Sponsor_Roster::claim(' ) >= 3, true );
ck( 'the messages maps cover every status the handlers flash', array_values( array_diff( array( 'profile-saved', 'profile-unchanged', 'profile-rejected', 'profile-failed', 'interest-sent', 'interest-unsent', 'interest-empty', 'interest-ceiling', 'interest-failed', 'mentor-interest-sent', 'mentor-interest-unknown', 'mentor-interest-ceiling', 'mentor-interest-failed', 'refused' ), array_merge( array_keys( WPCPM_Sponsor_Profile::messages() ), array_keys( WPCPM_Sponsor_Interests::messages() ), array_keys( WPCPM_Sponsor_Mentors::messages() ) ) ) ), array() );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
