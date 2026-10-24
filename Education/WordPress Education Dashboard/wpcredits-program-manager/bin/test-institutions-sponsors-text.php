<?php
/**
 * The Institutions and Sponsors emails, word for word: what each says, as 1.122.21 wrote it.
 *
 * These emails move into the template registry in 1.122.22, and the move must change nothing a
 * recipient reads. So before the move this suite recorded, through the real send paths, every email
 * the two areas send for a set of example cases into bin/fixtures/institutions-sponsors-1-122-21.json:
 * who it went to, the id it was logged under, its subject and body as `wp_mail()` was handed them (the
 * signature and footer included, as 1.122.21 adds it), and its headers. From then on it runs the same
 * cases and compares, byte for byte.
 *
 * The send paths, each the code that builds the email and hands it to `WPCPM_Mail`:
 *
 * - reached as their handlers and jobs call them, the private ones by reflection: the institution
 *   application's two (`mail_applicant()`, `mail_managers()`), the institution invitation
 *   (`mail_invite()`), the institution agreement's six (`mail_received()`, `mail_landed()`,
 *   `mail_accepted()`, `mail_reinstated()`, `mail_returned()`, `mail_revoked()`) and its daily
 *   digest (`remind()`), the agreement generator's notice (`template_needs_attention()`, which ends
 *   in `wp_die()`), a membership ending (`WPCPM_Institution_Members::detach()`, which writes to the
 *   Administrators when the last member goes), the sponsor application's two, the sponsor
 *   agreement's five, and the codes' two (`maybe_low_stock()`, `report_problem()`);
 * - six are built inside a request handler and can be reached no other way, so the suite makes the
 *   request: the institution and sponsor queues' question and rejection (`handle_info()`,
 *   `handle_reject()`), the Sponsor Dashboard's form for sponsoring a mentor
 *   (`WPCPM_Sponsor_Mentors::handle_interest()`), and a sponsor post's return (`handle_return()`).
 *   Each is posted what its form posts, its capability and nonce are answered yes, and the redirect
 *   it ends with stops it; who it writes to, and under which id, is recorded as for every other
 *   email;
 * - the interest, which the Sponsor Dashboard's Offer more card sent through its handler until that
 *   card left the page in 1.123.22, is filed through `WPCPM_Sponsor_Interests::file()`, the road the
 *   Sponsorship card's Talk to your program contact takes, from each case's inputs read as that
 *   form read them (`file_interest()`), so its email is still built by the code that sends it.
 *
 * The recipients are the real ones: `WPCPM_Institutions::notify_managers()` (every Administrator, or
 * the accounts and bare addresses a setting names), `WPCPM_Sponsor_Interests::mail_manager()` (the
 * sponsor's assigned Administrator with an account or without one, else the setting, else every
 * Administrator), the members of an institution or a sponsor (`members_of()`, the live members only),
 * an applicant's or an invitee's address, a post's author and a code's claimant.
 *
 * The inputs: every optional line in and out (a call to book, the courtesy scan's notes, a country
 * contact, the logo not kept, each dashboard link), every count of one and of several, and the
 * highest low mark the Offers form takes, a typed question, note, reason and message holding "<",
 * "&", both quotes, a "%", a placeholder's name in braces and a line break (and each that an email
 * carries as a browser posts a text box too, each line break CRLF), names and titles holding "&",
 * quotes and a "%", and as WordPress stores them, their entities written out (an institution
 * application's title, an offer's, a post's and an account's name), letters beyond ASCII (an
 * institution, a sponsor, an Administrator, a mentor, an offer, a post and a typed text), a site
 * name holding "&", a quote and a "%", an institution and a sponsor the index holds no name for, an
 * uploader, an Administrator, a sponsor's member and a post's author with no name, a sponsor's
 * application held or flagged for something other than its logo, every dashboard page published
 * and none, and each alone, and an account that holds two roles. No email of the two areas shows a
 * time on a recipient's clock: each date is the site's, and the site keeps Los Angeles'
 * clock here at a moment when its date is a day behind UTC's, while an Administrator and a member
 * keep Tokyo's, so a date taken from another clock would show. The clock is fixed: `wp_date()` reads
 * the suite's moment, 2036-03-10 04:30 UTC, for "now", and a moment the code works out from PHP's own
 * clock (an invitation's expiry, fourteen days on) is moved onto that moment, so every run records
 * the same dates. The daily digest reads an upload's age on PHP's own clock, so the documents it
 * finds waiting were uploaded early in 2026, and the one it must leave out in 2099.
 *
 * The names are invented; every address is example.test's and is written "at example.test" in the
 * recording, which holds no address; every link that carries a secret carries a fake one
 * ("EXAMPLE-NOT-A-REAL-...") and every code is a fake one. The stand-in translations hand English
 * back as written and mark any other language with its locale, so a later check can tell which
 * language an email was built in; the recording is English throughout.
 *
 * The recording is made once: `--record` refuses when the file is there, and the suite pins the
 * file's sha256, so a recording made again from later code can never stand in for 1.122.21's.
 *
 * 1.122.23 changes some of these emails on purpose, and needs inputs the recording holds no email
 * for. Those inputs are the cases of `added_cases()`, recorded once from 1.122.22, before anything of
 * 1.122.23 moved, into bin/fixtures/institutions-sponsors-added-1-122-22.json by `--record-added`,
 * which refuses when that file is there, and the suite pins its sha256 too: a signed Collaboration
 * Agreement uploaded through the Sponsor Dashboard's own handler by the sponsor's member (1.122.22
 * refused it, so its recording holds no email) and by an Administrator; the courtesy scan's notes as
 * the scan stores them, each name with its slash; a company's name, an institution's name and an
 * institution's contact as the form's cleaner keeps a "<", and a name holding a run the subject's
 * cleaner reads as a tag, in a body and in a subject; an Administrator whose name holds a double
 * quote, one whose name is longer than one encoded word holds, one whose name holds a line break and
 * one whose name is spaces; a revocation and an invitation by an Administrator whose account holds
 * no name; an invitation by a member whose account holds a name and no address; and an offer's
 * codes counted past a thousand. Every email of both recordings is sent as recorded, byte for byte,
 * but those `changes()` lists, each change with the item of the 1.122.23 plan it comes from and the
 * exact text it changes (docs/plans/2026-10-11-mail-fixes-1-122-23.md): an email that changes
 * without a line there fails, and so does a line that changes nothing. What 1.123.24 changes after
 * them is bin/stubs/mail-since-1-123-24.php's, run over every recorded body where it is compared,
 * and the suite says each change was made in the emails it belongs to and in no other; what
 * 1.123.26 changes, the acknowledgement's sentence about its link, is bin/stubs/mail-since-1-123-26.php's,
 * run after it, the same way.
 *
 * Run from the plugin root:  php bin/test-institutions-sponsors-text.php
 * Record (once, against 1.122.21, before the move):  php bin/test-institutions-sponsors-text.php --record
 * Record the added cases (once, against 1.122.22):  php bin/test-institutions-sponsors-text.php --record-added
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'MB_IN_BYTES', 1048576 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

/** The suite's moment: 2036-03-10 04:30 UTC, the evening of March 9 on the site's clock. */
define( 'WPCPM_TEST_NOW', 2088736200 );

$GLOBALS['opts']      = array();
$GLOBALS['umeta']     = array();
$GLOBALS['users']     = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['mail']      = array();
$GLOBALS['uid']       = 0;
$GLOBALS['manage']    = array( 40, 41, 43, 55 );
$GLOBALS['site_name'] = 'WordPress Credits';
$GLOBALS['ids']       = array();

class WP_Error {
	private $code;
	private $message;
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', $roles = array() ) {
		$this->ID           = $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( preg_replace( '/[^A-Za-z]/', '', $name ) );
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
	public function remove_role( $role ) { $this->roles = array_values( array_diff( $this->roles, array( $role ) ) ); }
	public function set_role( $role ) { $this->roles = array( $role ); }
}
class WP_Post { public $ID = 0, $post_title = '', $post_content = '', $post_type = '', $post_status = 'private', $post_date = '', $post_author = 0; }

/** What a handler ends with, a redirect or `wp_die()`, stops it here. */
class WPCPM_Test_Stop extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
// The language each string is asked in: English as written, any other marked with its locale, so a
// check can tell which language an email was built in. The recording is English throughout.
function __( $s, $d = null ) { return wpcpm_test_in_language( $s ); }
function _n( $a, $b, $n, $d = null ) { return wpcpm_test_in_language( 1 === (int) $n ? $a : $b ); }
function esc_html__( $s, $d = null ) { return __( $s, $d ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wpcpm_test_in_language( $s ) { return 'en_US' === determine_locale() ? $s : '[' . determine_locale() . '] ' . $s; }
function is_email( $s ) { return (bool) filter_var( (string) $s, FILTER_VALIDATE_EMAIL ); }
function sanitize_email( $s ) { return is_email( trim( (string) $s ) ) ? trim( (string) $s ) : ''; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $n ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_bloginfo( $k = 'name' ) { return $GLOBALS['site_name']; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// A signature the site would make with its secret salt, made here with none: plainly not a real one.
function wp_hash( $data, $scheme = 'auth' ) { return 'EXAMPLE-NOT-A-REAL-SIGNATURE' . md5( (string) $data ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_user_by( $f, $v ) {
	if ( 'id' === $f ) {
		return $GLOBALS['users'][ (int) $v ] ?? false;
	}

	// An empty value finds nobody, as core's lookup answers it: an account that holds no address is
	// not the one an empty address names.
	if ( '' === trim( (string) $v ) ) {
		return false;
	}

	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $f && strtolower( $user->user_email ) === strtolower( trim( (string) $v ) ) ) {
			return $user;
		}
	}

	return false;
}
function get_userdata( $id ) { return get_user_by( 'id', $id ); }
/**
 * `get_users()` as the senders ask it: the accounts holding a capability, or a meta key's value.
 *
 * @param array $args The query.
 * @return WP_User[]
 */
function get_users( $args = array() ) {
	$out = array();

	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( isset( $args['capability'] ) && ! user_can( $user, $args['capability'] ) ) {
			continue;
		}

		if ( isset( $args['meta_key'] ) && (string) get_user_meta( $id, $args['meta_key'], true ) !== (string) $args['meta_value'] ) {
			continue;
		}

		$out[] = $user;
	}

	return $out;
}
function get_post( $id = null ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
// The documents a query for agreements waiting on review finds, as a case gives them.
function get_posts( $args = array() ) { return $GLOBALS['waiting']; }
function get_post_meta( $id, $k = '', $single = false ) { $rows = $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? array(); $rows = is_array( $rows ) ? $rows : array( $rows ); if ( $single ) { return $rows ? $rows[0] : ''; } return $rows; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = array( $v ); return true; }
function add_post_meta( $id, $k, $v ) { return true; }
function delete_post_meta( $id, $k, $v = '' ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
function wp_update_post( $post, $wp_error = false ) { return (int) $post['ID']; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function check_admin_referer( $action = -1, $name = '_wpnonce' ) { return 1; }
function wp_get_referer() { return false; }
function wp_safe_redirect( $to ) { throw new WPCPM_Test_Stop( 'redirect' ); }
function wp_die( $m = '', $c = 0 ) { throw new WPCPM_Test_Stop( 'wp_die' ); }
// As core's, which writes the locale's thousands separator, `&nbsp;` where a locale's is a space.
function number_format_i18n( $n ) { return number_format( (float) $n, 0, '.', $GLOBALS['thousands_sep'] ?? ',' ); }
function wp_timezone_string() { return 'America/Los_Angeles'; }
function wp_timezone() { return new DateTimeZone( wp_timezone_string() ); }
/**
 * `wp_date()` on the suite's clock: "now" is `WPCPM_TEST_NOW`, and a moment worked out from PHP's own
 * clock, as an invitation's expiry is, is moved onto it, so every run records the same dates.
 *
 * @param string            $format A date format.
 * @param int|null          $ts     A moment, or null for now.
 * @param DateTimeZone|null $zone   A clock, or null for the site's.
 * @return string
 */
function wp_date( $format, $ts = null, $zone = null ) {
	$ts = null === $ts ? time() : (int) $ts;

	if ( abs( $ts - time() ) < 400 * DAY_IN_SECONDS ) {
		$ts += WPCPM_TEST_NOW - time();
	}

	$d = new DateTime( '@' . $ts );
	$d->setTimezone( $zone instanceof DateTimeZone ? $zone : wp_timezone() );

	return $d->format( $format );
}
function add_action() {}
// The filters as WordPress runs them: by priority, then in the order they were added. The one hooked
// here is the media library's allowed types (`upload_mimes`), which the sponsor posts' fence narrows
// for a sponsor's member and the PDF check reads; no sender adds a filter.
$GLOBALS['filters'] = array();
function add_filter( $hook, $callback = null, $priority = 10, $accepted = 1 ) {
	$GLOBALS['filters'][ $hook ][ $priority ][] = $callback;
	return true;
}
function remove_filter( $hook, $callback, $priority = 10 ) {
	foreach ( $GLOBALS['filters'][ $hook ][ $priority ] ?? array() as $n => $held ) {
		if ( $held === $callback ) {
			unset( $GLOBALS['filters'][ $hook ][ $priority ][ $n ] );
			return true;
		}
	}
	return false;
}
function apply_filters( $hook, $value, ...$args ) {
	$by_priority = $GLOBALS['filters'][ $hook ] ?? array();
	ksort( $by_priority );
	foreach ( $by_priority as $held ) {
		foreach ( $held as $callback ) {
			$value = call_user_func( $callback, $value, ...$args );
		}
	}
	return $value;
}
function do_action( $h ) {}
/**
 * The types an upload may have, as core lists the ones this suite meets, for the account signed in:
 * the `upload_mimes` filter decides.
 *
 * @param mixed $user The account, or null for the one signed in.
 * @return array
 */
function get_allowed_mime_types( $user = null ) {
	return apply_filters(
		'upload_mimes',
		array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
		),
		$user
	);
}
/**
 * Core's `wp_check_filetype_and_ext()` for the files this suite posts: the name's extension against
 * the map given (or the allowed types), the bytes read by fileinfo, which must say the same type, and
 * then, as core asks of every file, a type the account signed in may upload.
 *
 * @param string     $file     The file on disk.
 * @param string     $filename The name it came with.
 * @param array|null $mimes    The map the caller allows.
 * @return array
 */
function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
	$ext  = false;
	$type = false;

	foreach ( null === $mimes ? get_allowed_mime_types() : $mimes as $extensions => $mime ) {
		if ( preg_match( '!\.(' . $extensions . ')$!i', (string) $filename, $found ) ) {
			$ext  = strtolower( $found[1] );
			$type = $mime;
			break;
		}
	}

	if ( $type && function_exists( 'finfo_open' ) && is_readable( $file ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$real  = $finfo ? (string) finfo_file( $finfo, $file ) : '';

		if ( '' !== $real && $real !== $type ) {
			$ext  = false;
			$type = false;
		}
	}

	// The mime type must be allowed.
	if ( $type && ! in_array( $type, get_allowed_mime_types(), true ) ) {
		$ext  = false;
		$type = false;
	}

	return array( 'ext' => $ext, 'type' => $type, 'proper_filename' => false );
}
function add_option( $k, $v, $d = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
// A post written as core writes one, dated now on the site's clock: the one document an upload files.
function wp_insert_post( $postarr, $wp_error = false ) {
	post(
		871,
		$postarr['post_type'],
		array(
			'post_title'  => $postarr['post_title'] ?? '',
			'post_status' => $postarr['post_status'] ?? 'draft',
			'post_author' => (int) ( $postarr['post_author'] ?? 0 ),
			'post_date'   => wp_date( 'Y-m-d H:i:s' ),
		),
		array()
	);
	return 871;
}
// A person's language is the one a check gives their account, English when it gives none; switching
// into it and back is a stack, as WordPress's own switcher keeps one.
$GLOBALS['user_locale']  = array();
$GLOBALS['locale_stack'] = array();
function switch_to_user_locale( $user_id ) {
	if ( ! isset( $GLOBALS['user_locale'][ (int) $user_id ] ) ) {
		return false;
	}

	$GLOBALS['locale_stack'][] = $GLOBALS['user_locale'][ (int) $user_id ];

	return true;
}
function switch_to_locale( $locale ) { return false; }
function restore_previous_locale() { array_pop( $GLOBALS['locale_stack'] ); return true; }
function determine_locale() { return array() === $GLOBALS['locale_stack'] ? 'en_US' : end( $GLOBALS['locale_stack'] ); }
/**
 * Core's `add_query_arg()` in its two forms, a fragment kept at the end, as core keeps it.
 *
 * @param mixed ...$args The arguments, in either form.
 * @return string
 */
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$pairs = $args[0];
		$url   = (string) ( $args[1] ?? '' );
	} else {
		$pairs = array( $args[0] => $args[1] );
		$url   = (string) ( $args[2] ?? '' );
	}

	$fragment = '';

	if ( false !== strpos( $url, '#' ) ) {
		$fragment = substr( $url, strpos( $url, '#' ) );
		$url      = substr( $url, 0, strpos( $url, '#' ) );
	}

	foreach ( $pairs as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}

	return $url . $fragment;
}
/**
 * `wp_mail()`, keeping what it was handed and the id the mail log's capture takes.
 */
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = array(
		'to'      => $to,
		'context' => WPCPM_Mail::take_context(),
		'subject' => $subject,
		'body'    => $body,
		'headers' => array_values( (array) $headers ),
		'files'   => count( (array) $attachments ),
	);

	WPCPM_Mail::take_test();

	return true;
}
require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/temp-dir.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';
require_once __DIR__ . '/stubs/mail-since-1-123-24.php';
require_once __DIR__ . '/stubs/mail-since-1-123-26.php';
require_once __DIR__ . '/stubs/mail-since-1-123-27.php';

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sync-module.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/trait-wpcpm-accounts-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-invite.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-agreement-generate.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-application.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-agreement.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-members.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-interests.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-mentors.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-claims.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-sponsor-posts.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-pdf-check.php';

// The fence on what a sponsor's member writing posts may upload, hooked for everyone as
// `WPCPM_Sponsor_Posts::init()` hooks it, and deciding per call.
add_filter( 'upload_mimes', array( 'WPCPM_Sponsor_Posts', 'limit_mimes' ) );

/** The dashboards, each at the address a case gives it, '' while its page is not published. */
class WPCPM_Students_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Mentors_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Institutions_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above, and the Sponsor Dashboard's way back from a press, which stops the handler here. */
class WPCPM_Sponsors_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
	public static function leave( $status, $card = '', $record = '', $detail = '' ) { throw new WPCPM_Test_Stop( 'leave: ' . $status ); }
}
/** As above. */
class WPCPM_Administrators_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** The one-shot outcomes a press leaves: nothing to keep here. */
class WPCPM_Flash {
	public static function set( $channel, $value ) {}
	public static function take( $channel ) { return null; }
	public static function sweep( $test ) {}
}
/** The institutions the index holds by record, as the cases give them. */
class WPCPM_Institutions_Index {
	public static $rows = array();
	public static function row( $record ) { return self::$rows[ $record ] ?? null; }
}
/** The sponsors the index holds by record, and each one's assigned Administrator, as a case gives them. */
class WPCPM_Sponsors_Index {
	public static $rows    = array();
	public static $manager = null;
	public static function row( $record ) { return self::$rows[ $record ] ?? null; }
	public static function manager_of( $record ) { return self::$manager; }
	public static function patch( $record, array $patch ) { return true; }
	public static function empty_row() { return array( 'name' => '', 'support' => array() ); }
	public static function is_key( $value ) { return is_string( $value ) && 1 === preg_match( '/^(?:rec|spo)[A-Za-z0-9]{14}$/', $value ); }
}
/** Each country's routing: its contact's address and booking link, as the countries map holds them. */
class WPCPM_Countries {
	public static function routing( $country ) {
		$map = array(
			'PL' => array( 'email' => 'pl-contact@example.test', 'calendly' => 'https://calendly.example.test/pl-contact' ),
			'XX' => array( 'email' => '', 'calendly' => '' ),
		);

		return $map[ $country ] ?? null;
	}
	public static function name_of( $country ) { return 'PL' === $country ? 'Poland' : $country; }
}
/** A record ID, as Airtable writes one, and the mentors looking for a sponsor, as the mentors sync keeps them. */
class WPCPM_Mentors_Sync {
	public static function is_record_id( $value ) { return is_scalar( $value ) && 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) ); }
	public static function sponsorship() {
		return array(
			'recSEED0000000201' => array( 'status' => 'Active', 'wants' => true, 'sponsored' => false, 'name' => 'Ben Lindqvist', 'profile' => 'https://profiles.wordpress.org/mentor-two/', 'expertise' => array(), 'user_id' => 0 ),
			'recSEED0000000202' => array( 'status' => 'Active', 'wants' => true, 'sponsored' => false, 'name' => 'Rio "Q&A" O\'Example 100%', 'profile' => 'https://profiles.wordpress.org/rio-example/', 'expertise' => array(), 'user_id' => 0 ),
			'recSEED0000000203' => array( 'status' => 'Active', 'wants' => true, 'sponsored' => true, 'name' => 'Sponsored Already', 'profile' => 'https://profiles.wordpress.org/mentor-three/', 'expertise' => array(), 'user_id' => 0 ),
			'recSEED0000000204' => array( 'status' => 'Active', 'wants' => true, 'sponsored' => false, 'name' => 'Józef Żółćwicz-Núñez', 'profile' => 'https://profiles.wordpress.org/mentor-four/', 'expertise' => array(), 'user_id' => 0 ),
		);
	}
}
/** The Institutions table's columns the queue reads an applicant's address under. */
class WPCPM_Institutions_Sync {
	public static function fields() { return array( 'contact_email' => 'Contact Email' ); }
}
/** The audit log: what a press records, kept nowhere here. */
class WPCPM_Institution_Audit {
	const KIND_MEMBER_REMOVED = 'member_removed';
	const GROUND_MANAGER      = 'manager';
	const GROUND_MEMBER       = 'member';
	const GROUND_SYSTEM       = 'system';
	const EVIDENCE_INDEX      = 'index';
	public static function record( array $row ) { return 1; }
	public static function record_sponsor( array $row ) { return 1; }
}
/** The day's ceilings, never reached here. */
class WPCPM_Ceiling {
	public static function key( $kind, $who ) { return $kind . ':' . $who; }
	public static function claim( $key, $limit, $window ) { return true; }
}
/** The Sponsors table: a history read and written back, as the interests card does. */
class WPCPM_Airtable {
	public function get_record( $table, $record ) { return array( 'fields' => array( 'Sponsorship interests' => '2036-01-05 by Sami Sponsor: note: An earlier line' ) ); }
	public function update_records( $table, array $rows ) { return array(); }
	public static function flatten( $value, $glue = ', ' ) { return is_array( $value ) ? implode( $glue, $value ) : (string) $value; }
}
/** Who may act for a sponsor: the case's sponsor, for the account signed in. */
class WPCPM_Sponsor_Roster {
	const ARG_VIEW = 'wpcpm_sponsor_view';
	public static function claim( $record, $act ) {
		return array(
			'record'   => (string) $record,
			'row'      => WPCPM_Sponsors_Index::row( (string) $record ),
			'decision' => array( 'ground' => 'member' ),
		);
	}
}
/** The sponsor policy's names, and its yes. */
class WPCPM_Sponsor_Policy {
	const ACT_EXPRESS_INTEREST = 'express_interest';
	const ACT_PUBLISH_POST     = 'publish_post';
	const ACT_AGREEMENT        = 'agreement';
	const META_POST_SPONSOR    = 'wpcpm_post_sponsor';
	public static function subject_post( $post ) { return $post; }
	public static function decide( $act, $subject ) { return array( 'allowed' => true ); }
}
/** The offers, as a case gives them. */
class WPCPM_Sponsor_Offers {
	const KIND_CODES    = 'codes';
	const META_LOW_SENT = 'wpcpm_offer_low_sent';
	public static $offers = array();
	public static function read( $id ) { return self::$offers[ (int) $id ] ?? null; }
}
/** An offer's pool: how many codes are left, and the fake code a claimant holds. */
class WPCPM_Sponsor_Codes {
	const LOCK_PREFIX  = 'wpcpm_codes_lock_';
	const LOCK_TIMEOUT = 30;
	const SHARED_INDEX = -1;
	public static $available = 0;
	public static function counts( $offer_id ) { return array( 'available' => self::$available ); }
	public static function code_at( $offer_id, $index ) { return 'EXAMPLE-CODE-7Q2X'; }
	public static function shared( $offer_id ) { return 'SHARED-EXAMPLE-AB12'; }
}
/** The private store an uploaded agreement goes to: kept nowhere here. */
class WPCPM_Private_Files {
	public static function store( $bytes, $extension = 'pdf' ) { return array( 'path' => '2036/example-upload.' . $extension, 'sha256' => hash( 'sha256', (string) $bytes ), 'size' => strlen( (string) $bytes ) ); }
	public static function forget( $relative ) { return true; }
}
/** The Sponsors table's column an upload writes the agreement's status to. */
class WPCPM_Sponsors_Sync {
	public static function fields() { return array( 'agr_status' => 'Agreement Status' ); }
}
/** The Sponsors screen's flash channel. */
class WPCPM_Sponsors {
	const FLASH = 'sponsors_admin';
}

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

/*
 * The people, the institutions and the sponsors
 * --------------------------------------------------------------------
 */

$GLOBALS['opts']['date_format'] = 'F j, Y';
$GLOBALS['opts']['time_format'] = 'g:i a';

$GLOBALS['users'][40] = new WP_User( 40, 'Ida Admin', 'ida@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][41] = new WP_User( 41, "Omar O'Admin, Ops", 'omar@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][42] = new WP_User( 42, 'Rhea Reviewer', 'rhea@example.test', array() );
// An Administrator whose account holds no name, as an account made by hand can.
$GLOBALS['users'][43] = new WP_User( 43, '', 'nameless@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
// An Administrator whose name is as WordPress stores an account's on save, "&" written `&amp;` and a
// "<" `&lt;`, and one whose name is in letters beyond ASCII. Each holds the program's capability only
// in a case that adds it (`manage`), so every other case's Administrators are the four above.
$GLOBALS['users'][44] = new WP_User( 44, 'Gus &amp; Gwen &lt;Ops', 'gus@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][45] = new WP_User( 45, 'Zoë Ångström-Núñez', 'zoe@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
// Three Administrators the cases 1.122.23 adds: one whose name holds a double quote and a semicolon,
// one whose name, in letters beyond ASCII with a comma, a quote and an "&", is longer than one encoded
// word holds, and one whose name holds a line break, as an account written by code can. Each holds
// the program's capability only in a case that adds it.
$GLOBALS['users'][46] = new WP_User( 46, 'Dee "DJ" Okafor; Ops', 'dee@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][47] = new WP_User( 47, 'Zoë Ångström-Núñez, Head of Partnerships & Outreach "EU"', 'zoe-eu@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][48] = new WP_User( 48, "Lee\nLine Example", 'lee@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
// And an Administrator whose account's name is spaces, which a form that does not trim can store.
$GLOBALS['users'][49] = new WP_User( 49, '   ', 'blank@example.test', array( WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][50] = new WP_User( 50, 'Ines Example', 'ines@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
// An institution's member who is also a mentor.
$GLOBALS['users'][51] = new WP_User( 51, 'Noor Example', 'noor@example.test', array( WPCPM_Roles::ROLE_INSTITUTION, WPCPM_Roles::ROLE_MENTOR ) );
// A member whose membership has ended: no longer written to.
$GLOBALS['users'][52] = new WP_User( 52, 'Pia Former', 'pia@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][53] = new WP_User( 53, 'Lena Last', 'lena@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][54] = new WP_User( 54, 'Theo Second', 'theo@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
// An institution's member who is also an Administrator.
$GLOBALS['users'][55] = new WP_User( 55, 'Una Second', 'una@example.test', array( WPCPM_Roles::ROLE_INSTITUTION, WPCPM_Roles::ROLE_ADMIN ) );
$GLOBALS['users'][56] = new WP_User( 56, 'Vera Third', 'vera@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
// An institution's member whose name is as WordPress stores it: a member only in a case that makes it one.
$GLOBALS['users'][57] = new WP_User( 57, 'Mia &amp; Max &lt;Team', 'mia@example.test', array( WPCPM_Roles::ROLE_INSTITUTION ) );
// An institution's member whose account holds a name and no address, as an account made by hand can.
$GLOBALS['users'][58] = new WP_User( 58, 'Ivo Noaddress', '', array( WPCPM_Roles::ROLE_INSTITUTION ) );
$GLOBALS['users'][60] = new WP_User( 60, 'Sami Sponsor', 'sami@example.test', array( WPCPM_Roles::ROLE_SPONSOR ) );
// A sponsor's member who is also a student.
$GLOBALS['users'][61] = new WP_User( 61, 'Tove Sponsor', 'tove@example.test', array( WPCPM_Roles::ROLE_SPONSOR, WPCPM_Roles::ROLE_STUDENT ) );
$GLOBALS['users'][62] = new WP_User( 62, 'Ulla Gone', 'ulla@example.test', array( WPCPM_Roles::ROLE_SPONSOR ) );
// A sponsor's account that holds no name, as an account made by hand can: the author of a post sent back.
$GLOBALS['users'][63] = new WP_User( 63, '', 'nameless-sponsor@example.test', array( WPCPM_Roles::ROLE_SPONSOR ) );
// A sponsor's account whose name is as WordPress stores it, and one that holds no address, as an
// account made by hand can: each a member only in a case that makes it one.
$GLOBALS['users'][64] = new WP_User( 64, 'Sid &amp; Sal &lt;Sales', 'sid@example.test', array( WPCPM_Roles::ROLE_SPONSOR ) );
$GLOBALS['users'][65] = new WP_User( 65, 'Wes Noaddress', '', array( WPCPM_Roles::ROLE_SPONSOR ) );
$GLOBALS['users'][70] = new WP_User( 70, "Kai O'Claimant", 'kai@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
// A claimant whose name is as WordPress stores it.
$GLOBALS['users'][71] = new WP_User( 71, 'Kit &amp; Kin &lt;3', 'kit@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );

/**
 * Every live and ended membership, as each case starts with them, and the clock two accounts keep: an
 * Administrator and a member read Tokyo's, a day ahead of the site's here, so a date an email showed on
 * its recipient's clock would differ from the site's one the recording holds.
 */
function memberships() {
	$in = WPCPM_Institution_Members::META_RECORD_ID;
	$on = WPCPM_Institution_Members::META_ACTIVE;
	$sp = WPCPM_Sponsor_Members::META_RECORD_ID;
	$so = WPCPM_Sponsor_Members::META_ACTIVE;
	// WPCPM_Mentor_Availability::META_TIMEZONE, the profile's time zone.
	$tz = 'wpcpm_timezone';

	return array(
		40 => array( $tz => 'Asia/Tokyo' ),
		50 => array( $in => 'recSEED0000000016', $on => 1, $tz => 'Asia/Tokyo' ),
		51 => array( $in => 'recSEED0000000016', $on => 1 ),
		52 => array( $in => 'recSEED0000000016', $on => 0 ),
		53 => array( $in => 'recSEED0000000017', $on => 1 ),
		54 => array( $in => 'recSEED0000000018', $on => 1 ),
		55 => array( $in => 'recSEED0000000018', $on => 1 ),
		56 => array( $in => 'recSEED0000000020', $on => 1 ),
		60 => array( $sp => 'recSEED0000000101', $so => 1 ),
		61 => array( $sp => 'recSEED0000000101', $so => 1 ),
		62 => array( $sp => 'recSEED0000000101', $so => 0 ),
	);
}

/** A question, a note, a reason or a message as a person types one into a box. */
const TYPED = "Could you <3 a Q&A with \"both\" 'quotes' at 100% {note}?\nAnd a second line.";

/** A name or a title as a person types one. */
const NAMED = 'Example University & "Partners" 100%';

/** A name holding a placeholder's name in braces, which is never read as one. */
const NAMED_BRACES = 'Example University & "Partners" 100% {institution_name}';

/** The site's name as WordPress stores it, its entities written out. */
const SITE_NAMED = 'Ada &amp; Ben&#039;s 100% Credits';

/**
 * A name as WordPress stores the title of an application filed by a visitor who is not signed in:
 * "&" written `&amp;`, a "<" `&lt;`.
 */
const STORED = 'Arts &amp; Sciences &lt;Faculty "Uni" 100%';

/** A question, a note, a reason or a message typed in letters beyond ASCII, curly quotes among them. */
const TYPED_NON_ASCII = "Czy możecie przysłać „kopię” <3 & 'Größe' 100% {note}?\nZażółć gęślą jaźń, señor.";

/** An institution's name in letters beyond ASCII. */
const NAMED_NON_ASCII = 'Akademia Przykładowa w Łące & „Żółw” 100%';

/** A sponsor's name in letters beyond ASCII. */
const SPONSOR_NON_ASCII = 'Größe & Söhne Beispiel-Hosting';

/** A company's name typed as 'Acme <3 "Co" & Sons', as the form's cleaner keeps it. */
const CLEANED_SPONSOR = 'Acme &lt;3 &quot;Co&quot; &amp; Sons';

/** An institution's name typed as 'Faculty <3 "Arts" & Design', as the form's cleaner keeps it and the index carries it on. */
const CLEANED_INSTITUTION = 'Faculty &lt;3 &quot;Arts&quot; &amp; Design';

/** A company's name holding a run the subject's cleaner reads as a tag, as WordPress stores it. */
const TAG_LIKE_SPONSOR = 'Acme &lt;b&gt;Co&lt;/b&gt; Hosting';

/**
 * Every case: what it sends, through which sender, and the world it sends in.
 *
 * Each case names the sender (`send`), who is signed in (`actor`), the dashboard pages published
 * (`pages`, any of student, mentor, institution, sponsor and administrator), the site's name as
 * WordPress stores it (`site`), the settings it changes (`settings`), and what the sender is handed or
 * finds (`args`). Among `args`, a case can give the text typed (`typed`), a name an index row holds
 * in place of its own (`renamed`), a membership beside every case's (`joins`), an Administrator
 * beside the four (`manage`) and the signals a sponsor's application was filed with (`signals`).
 *
 * @return array<string,array>
 */
function cases() {
	$all   = array( 'student', 'mentor', 'institution', 'sponsor', 'administrator' );
	$named = array( 'agreement_notify' => 'rhea@example.test, reviews@example.test,  ida@example.test , rhea@example.test' );
	$snamed = array( 'sponsor_notify' => 'reviews@example.test, omar@example.test' );
	// A live membership of an institution or of the sponsor, for an account a case makes a member.
	$of_16  = array( WPCPM_Institution_Members::META_RECORD_ID => 'recSEED0000000016', WPCPM_Institution_Members::META_ACTIVE => 1 );
	$of_19  = array( WPCPM_Institution_Members::META_RECORD_ID => 'recSEED0000000019', WPCPM_Institution_Members::META_ACTIVE => 1 );
	$of_101 = array( WPCPM_Sponsor_Members::META_RECORD_ID => 'recSEED0000000101', WPCPM_Sponsor_Members::META_ACTIVE => 1 );
	// What an Administrator typed, as a browser posts a text box, each line break CRLF.
	$crlf = str_replace( "\n", "\r\n", TYPED );

	$base = array(
		'actor'    => 40,
		'pages'    => $all,
		'site'     => 'WordPress Credits',
		'settings' => array(),
		'args'     => array(),
	);

	$cases = array(
		// The institution application: the applicant, with and without a call to book, and the
		// Administrators, every one or those a setting names.
		'applied'                                        => array( 'send' => 'inst-applied' ),
		'applied-no-call'                                => array( 'send' => 'inst-applied', 'args' => array( 'calendly' => '' ) ),
		'applied-no-routing'                             => array( 'send' => 'inst-applied', 'args' => array( 'routing' => false ) ),
		'applied-names'                                  => array( 'send' => 'inst-applied', 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'name' => NAMED_BRACES ) ),
		'applied-contact-in-the-footer'                  => array( 'send' => 'inst-applied', 'settings' => array( 'mail_contact' => 'program@example.test' ) ),
		'applied-no-address'                             => array( 'send' => 'inst-applied', 'args' => array( 'email' => 'not an address' ) ),
		'application-every-administrator'                => array( 'send' => 'inst-application' ),
		'application-named-reviewers'                    => array( 'send' => 'inst-application', 'settings' => $named ),
		'application-bare-fields'                        => array( 'send' => 'inst-application', 'args' => array( 'city' => '', 'country' => '', 'person' => '' ) ),
		'application-names'                              => array( 'send' => 'inst-application', 'site' => SITE_NAMED, 'args' => array( 'name' => NAMED_BRACES ) ),
		'application-no-page'                            => array( 'send' => 'inst-application', 'pages' => array() ),
		'application-administrator-page-only'            => array( 'send' => 'inst-application', 'pages' => array( 'administrator' ) ),
		'application-no-administrator'                   => array( 'send' => 'inst-application', 'args' => array( 'nobody' => true ) ),
		// The institution's name as WordPress stores the title of an application filed by a visitor who is
		// not signed in, and in letters beyond ASCII.
		'applied-title-as-stored'                        => array( 'send' => 'inst-applied', 'args' => array( 'name' => STORED ) ),
		'application-title-as-stored'                    => array( 'send' => 'inst-application', 'args' => array( 'name' => STORED ) ),
		'applied-non-ascii'                              => array( 'send' => 'inst-applied', 'args' => array( 'name' => NAMED_NON_ASCII ) ),
		'application-non-ascii'                          => array( 'send' => 'inst-application', 'args' => array( 'name' => NAMED_NON_ASCII, 'city' => 'Łódź', 'person' => 'Łucja Żółćwicz' ) ),
		'application-fields-with-quotes'                 => array( 'send' => 'inst-application', 'args' => array( 'city' => "L'Aquila", 'country' => "Côte d'Ivoire", 'person' => "Seán O'Example & Ana" ) ),
		// The institution queue's question and rejection, through their handlers.
		'information'                                    => array( 'send' => 'inst-information' ),
		'information-administrator-with-quotes'          => array( 'send' => 'inst-information', 'actor' => 41, 'site' => SITE_NAMED ),
		'information-administrator-with-no-name'         => array( 'send' => 'inst-information', 'actor' => 43 ),
		'information-no-address'                         => array( 'send' => 'inst-information', 'args' => array( 'email' => '' ) ),
		'information-administrator-name-as-stored'       => array( 'send' => 'inst-information', 'actor' => 44, 'args' => array( 'manage' => array( 44 ) ) ),
		'information-non-ascii'                          => array( 'send' => 'inst-information', 'actor' => 45, 'args' => array( 'manage' => array( 45 ), 'name' => NAMED_NON_ASCII, 'typed' => TYPED_NON_ASCII ) ),
		'information-typed-in-a-browser'                 => array( 'send' => 'inst-information', 'args' => array( 'typed' => $crlf ) ),
		'declined'                                       => array( 'send' => 'inst-declined' ),
		'declined-site-name'                             => array( 'send' => 'inst-declined', 'site' => SITE_NAMED, 'pages' => array() ),
		'declined-no-address'                            => array( 'send' => 'inst-declined', 'args' => array( 'email' => '' ) ),
		// An invitation to join an institution.
		'invite'                                         => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 50 ) ),
		'invite-unnamed-institution'                     => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000017', 'actor' => 51 ) ),
		'invite-no-inviter'                              => array( 'send' => 'inst-invite', 'site' => SITE_NAMED, 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 0 ) ),
		'invite-no-address'                              => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 50, 'email' => 'not an address' ) ),
		'invite-inviter-name-as-stored'                  => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 57, 'joins' => array( 57 => $of_16 ) ) ),
		'invite-non-ascii'                               => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 50, 'renamed' => array( 'recSEED0000000016' => NAMED_NON_ASCII ) ) ),
		// The institution's Collaboration Agreement: to its members, and to the Administrators.
		'agreement-received'                             => array( 'send' => 'agr-received', 'args' => array( 'author' => 50 ) ),
		'agreement-received-one-day-no-uploader'         => array( 'send' => 'agr-received', 'settings' => array( 'agreement_review_days' => 1 ), 'args' => array( 'author' => 0 ) ),
		'agreement-received-institution-page-only'       => array( 'send' => 'agr-received', 'pages' => array( 'institution' ), 'args' => array( 'author' => 51 ) ),
		'agreement-received-mentor-page-only'            => array( 'send' => 'agr-received', 'pages' => array( 'mentor' ), 'args' => array( 'author' => 50 ) ),
		'agreement-received-no-members'                  => array( 'send' => 'agr-received', 'args' => array( 'record' => 'recSEED0000000019', 'author' => 0 ) ),
		'agreement-received-uploader-name-as-stored'     => array( 'send' => 'agr-received', 'args' => array( 'author' => 57, 'joins' => array( 57 => $of_16 ) ) ),
		'agreement-received-site-name'                   => array( 'send' => 'agr-received', 'site' => SITE_NAMED, 'args' => array( 'author' => 50 ) ),
		'agreement-landed'                               => array( 'send' => 'agr-landed', 'args' => array( 'author' => 50 ) ),
		'agreement-landed-one-flag'                      => array( 'send' => 'agr-landed', 'settings' => $named, 'args' => array( 'author' => 50, 'flags' => array( 'JavaScript' ) ) ),
		'agreement-landed-three-flags'                   => array( 'send' => 'agr-landed', 'site' => SITE_NAMED, 'args' => array( 'author' => 51, 'flags' => array( 'JavaScript', 'OpenAction', 'EmbeddedFile' ) ) ),
		'agreement-landed-unnamed-no-uploader'           => array( 'send' => 'agr-landed', 'pages' => array(), 'args' => array( 'record' => 'recSEED0000000017', 'author' => 0 ) ),
		'agreement-landed-no-uploader-name'              => array( 'send' => 'agr-landed', 'args' => array( 'author' => 0 ) ),
		'agreement-landed-institution-page-only'         => array( 'send' => 'agr-landed', 'pages' => array( 'institution' ), 'args' => array( 'author' => 51 ) ),
		'agreement-landed-uploader-name-as-stored'       => array( 'send' => 'agr-landed', 'args' => array( 'author' => 57, 'joins' => array( 57 => $of_16 ) ) ),
		'agreement-landed-non-ascii'                     => array( 'send' => 'agr-landed', 'args' => array( 'author' => 50, 'renamed' => array( 'recSEED0000000016' => NAMED_NON_ASCII ) ) ),
		'agreement-accepted'                             => array( 'send' => 'agr-accepted' ),
		'agreement-accepted-no-page'                     => array( 'send' => 'agr-accepted', 'pages' => array() ),
		'agreement-accepted-institution-page-only'       => array( 'send' => 'agr-accepted', 'pages' => array( 'institution' ) ),
		'agreement-accepted-other-pages-only'            => array( 'send' => 'agr-accepted', 'pages' => array( 'student', 'mentor', 'sponsor', 'administrator' ) ),
		'agreement-accepted-student-page-only'           => array( 'send' => 'agr-accepted', 'pages' => array( 'student' ) ),
		'agreement-accepted-site-name'                   => array( 'send' => 'agr-accepted', 'site' => SITE_NAMED ),
		'agreement-reinstated'                           => array( 'send' => 'agr-reinstated', 'site' => SITE_NAMED ),
		'agreement-reinstated-no-page'                   => array( 'send' => 'agr-reinstated', 'pages' => array() ),
		'agreement-reinstated-institution-page-only'     => array( 'send' => 'agr-reinstated', 'pages' => array( 'institution' ) ),
		'agreement-returned'                             => array( 'send' => 'agr-returned' ),
		'agreement-returned-administrator-with-quotes'   => array( 'send' => 'agr-returned', 'actor' => 41, 'pages' => array() ),
		'agreement-returned-administrator-with-no-name'  => array( 'send' => 'agr-returned', 'actor' => 43, 'site' => SITE_NAMED ),
		'agreement-returned-sponsor-page-only'           => array( 'send' => 'agr-returned', 'pages' => array( 'sponsor' ) ),
		'agreement-returned-administrator-name-as-stored' => array( 'send' => 'agr-returned', 'actor' => 44 ),
		'agreement-returned-non-ascii'                   => array( 'send' => 'agr-returned', 'actor' => 45, 'args' => array( 'typed' => TYPED_NON_ASCII ) ),
		'agreement-returned-typed-in-a-browser'          => array( 'send' => 'agr-returned', 'args' => array( 'typed' => $crlf ) ),
		'agreement-revoked-with-a-contact'               => array( 'send' => 'agr-revoked' ),
		'agreement-revoked-contact-with-no-address'      => array( 'send' => 'agr-revoked', 'args' => array( 'record' => 'recSEED0000000018' ) ),
		'agreement-revoked-no-country'                   => array( 'send' => 'agr-revoked', 'actor' => 43, 'args' => array( 'record' => 'recSEED0000000017' ) ),
		'agreement-revoked-unknown-country'              => array( 'send' => 'agr-revoked', 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'record' => 'recSEED0000000020' ) ),
		'agreement-revoked-administrator-page-only'      => array( 'send' => 'agr-revoked', 'pages' => array( 'administrator' ) ),
		'agreement-revoked-administrator-name-as-stored' => array( 'send' => 'agr-revoked', 'actor' => 44 ),
		'agreement-revoked-administrator-with-quotes'    => array( 'send' => 'agr-revoked', 'actor' => 41 ),
		'agreement-revoked-non-ascii'                    => array( 'send' => 'agr-revoked', 'actor' => 45, 'args' => array( 'typed' => TYPED_NON_ASCII ) ),
		'agreement-revoked-typed-in-a-browser'           => array( 'send' => 'agr-revoked', 'args' => array( 'typed' => $crlf ) ),
		'agreement-revoked-no-contact-typed-in-a-browser' => array( 'send' => 'agr-revoked', 'args' => array( 'record' => 'recSEED0000000017', 'typed' => $crlf ) ),
		'reminder-one-waiting'                           => array( 'send' => 'agr-reminder', 'args' => array( 'waiting' => array( 741 ), 'later' => array( 744 ) ) ),
		'reminder-three-waiting-one-day'                 => array( 'send' => 'agr-reminder', 'settings' => array( 'agreement_review_days' => 1 ) + $named, 'args' => array( 'waiting' => array( 741, 742, 743 ), 'later' => array( 744 ) ) ),
		'reminder-none-waiting-too-long'                 => array( 'send' => 'agr-reminder', 'args' => array( 'waiting' => array(), 'later' => array( 744 ) ) ),
		'reminder-sent-today'                            => array( 'send' => 'agr-reminder', 'args' => array( 'waiting' => array( 741 ), 'later' => array(), 'reminded' => true ) ),
		'reminder-site-name'                             => array( 'send' => 'agr-reminder', 'site' => SITE_NAMED, 'args' => array( 'waiting' => array( 741 ), 'later' => array( 744 ) ) ),
		'template-needs-attention'                       => array( 'send' => 'agr-template', 'actor' => 50 ),
		'template-needs-attention-names'                 => array( 'send' => 'agr-template', 'actor' => 50, 'site' => SITE_NAMED, 'settings' => $named, 'args' => array( 'name' => 'Example "Uni" <School> & 100%' ) ),
		// A membership ending: the last member, with and without a profile name, and one of two.
		'member-last-removed'                            => array( 'send' => 'member-last', 'args' => array( 'user' => 53, 'reason' => 'removed', 'profile' => NAMED ) ),
		'member-last-left-no-profile'                    => array( 'send' => 'member-last', 'site' => SITE_NAMED, 'settings' => $named, 'args' => array( 'user' => 53, 'reason' => 'left', 'profile' => '' ) ),
		'member-not-last'                                => array( 'send' => 'member-last', 'args' => array( 'user' => 54, 'reason' => 'revoked', 'profile' => 'Second College' ) ),
		'member-last-revoked-by-the-sync'                => array( 'send' => 'member-last', 'actor' => 0, 'args' => array( 'user' => 56, 'reason' => 'revoked', 'profile' => 'Third College' ) ),
		'member-last-name-as-stored'                     => array( 'send' => 'member-last', 'args' => array( 'user' => 57, 'reason' => 'left', 'profile' => '', 'joins' => array( 57 => $of_19 ) ) ),
		// The sponsor application: the applicant, the logo kept and not, and the Administrators.
		'sponsor-applied'                                => array( 'send' => 'spon-applied' ),
		'sponsor-applied-logo-not-kept'                  => array( 'send' => 'spon-applied', 'args' => array( 'skipped' => true ) ),
		'sponsor-applied-names'                          => array( 'send' => 'spon-applied', 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'skipped' => true, 'name' => 'Acme & "Sons" 50% Hosting' ) ),
		'sponsor-applied-name-from-the-title'            => array( 'send' => 'spon-applied', 'args' => array( 'name' => '' ) ),
		// A row held or flagged for something other than its logo: the logo's line is for files-skipped alone.
		'sponsor-applied-held-no-logo-sent'              => array( 'send' => 'spon-applied', 'args' => array( 'signals' => array( 'site-ceiling' ) ) ),
		'sponsor-applied-logo-failed'                    => array( 'send' => 'spon-applied', 'args' => array( 'signals' => array( 'logo-failed' ) ) ),
		'sponsor-applied-duplicate'                      => array( 'send' => 'spon-applied', 'args' => array( 'signals' => array( 'duplicate', 'in-base' ) ) ),
		'sponsor-applied-non-ascii'                      => array( 'send' => 'spon-applied', 'args' => array( 'name' => SPONSOR_NON_ASCII ) ),
		'sponsor-applied-no-address'                     => array( 'send' => 'spon-applied', 'args' => array( 'email' => 'not an address' ) ),
		'sponsor-application-every-administrator'        => array( 'send' => 'spon-application' ),
		'sponsor-application-named-reviewers'            => array( 'send' => 'spon-application', 'settings' => $snamed ),
		'sponsor-application-bare-fields'                => array( 'send' => 'spon-application', 'site' => SITE_NAMED, 'pages' => array( 'administrator' ), 'args' => array( 'website' => '', 'person' => '', 'name' => 'Acme & "Sons" 50% {company_name}' ) ),
		'sponsor-application-no-administrator'           => array( 'send' => 'spon-application', 'args' => array( 'nobody' => true ) ),
		'sponsor-application-contact-with-quotes'        => array( 'send' => 'spon-application', 'args' => array( 'person' => "Seán O'Example & Ana" ) ),
		'sponsor-information'                            => array( 'send' => 'spon-information' ),
		'sponsor-information-administrator-with-quotes'  => array( 'send' => 'spon-information', 'actor' => 41, 'site' => SITE_NAMED ),
		'sponsor-information-no-address'                 => array( 'send' => 'spon-information', 'args' => array( 'email' => '' ) ),
		'sponsor-information-administrator-with-no-name' => array( 'send' => 'spon-information', 'actor' => 43 ),
		'sponsor-information-administrator-name-as-stored' => array( 'send' => 'spon-information', 'actor' => 44, 'args' => array( 'manage' => array( 44 ) ) ),
		'sponsor-information-non-ascii'                  => array( 'send' => 'spon-information', 'actor' => 45, 'args' => array( 'manage' => array( 45 ), 'name' => SPONSOR_NON_ASCII, 'typed' => TYPED_NON_ASCII ) ),
		'sponsor-information-typed-in-a-browser'         => array( 'send' => 'spon-information', 'args' => array( 'typed' => $crlf ) ),
		'sponsor-declined'                               => array( 'send' => 'spon-declined' ),
		'sponsor-declined-site-name'                     => array( 'send' => 'spon-declined', 'site' => SITE_NAMED ),
		'sponsor-declined-no-address'                    => array( 'send' => 'spon-declined', 'args' => array( 'email' => '' ) ),
		// The sponsor's Collaboration Agreement: to its assigned Administrator, and to its members.
		'sponsor-agreement-received-assigned'            => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'ida@example.test', 'author' => 60 ) ),
		'sponsor-agreement-received-assigned-no-account' => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'pat@example.test', 'author' => 61 ) ),
		'sponsor-agreement-received-unassigned-named'    => array( 'send' => 'sagr-received', 'settings' => $snamed, 'args' => array( 'manager' => null, 'author' => 60 ) ),
		'sponsor-agreement-received-unassigned'          => array( 'send' => 'sagr-received', 'pages' => array(), 'args' => array( 'manager' => null, 'author' => 60 ) ),
		'sponsor-agreement-received-bad-address'         => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'not an address', 'author' => 60 ) ),
		'sponsor-agreement-received-one-day-unnamed'     => array( 'send' => 'sagr-received', 'site' => SITE_NAMED, 'settings' => array( 'agreement_review_days' => 1 ), 'args' => array( 'manager' => 'ida@example.test', 'author' => 0, 'record' => 'recSEED0000000102' ) ),
		'sponsor-agreement-received-uploader-name-as-stored' => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'ida@example.test', 'author' => 64, 'joins' => array( 64 => $of_101 ) ) ),
		'sponsor-agreement-accepted'                     => array( 'send' => 'sagr-accepted' ),
		'sponsor-agreement-accepted-no-page'             => array( 'send' => 'sagr-accepted', 'pages' => array() ),
		'sponsor-agreement-accepted-sponsor-page-only'   => array( 'send' => 'sagr-accepted', 'pages' => array( 'sponsor' ) ),
		'sponsor-agreement-accepted-student-page-only'   => array( 'send' => 'sagr-accepted', 'pages' => array( 'student' ) ),
		'sponsor-agreement-accepted-no-members'          => array( 'send' => 'sagr-accepted', 'args' => array( 'record' => 'recSEED0000000102' ) ),
		'sponsor-agreement-accepted-site-name'           => array( 'send' => 'sagr-accepted', 'site' => SITE_NAMED ),
		'sponsor-agreement-returned'                     => array( 'send' => 'sagr-returned', 'settings' => array( 'mail_contact' => 'program@example.test' ) ),
		'sponsor-agreement-returned-no-name-no-page'     => array( 'send' => 'sagr-returned', 'actor' => 43, 'pages' => array() ),
		'sponsor-agreement-returned-with-quotes'         => array( 'send' => 'sagr-returned', 'actor' => 41, 'site' => SITE_NAMED, 'pages' => array( 'sponsor' ) ),
		'sponsor-agreement-returned-administrator-name-as-stored' => array( 'send' => 'sagr-returned', 'actor' => 44 ),
		'sponsor-agreement-returned-non-ascii'           => array( 'send' => 'sagr-returned', 'actor' => 45, 'args' => array( 'typed' => TYPED_NON_ASCII ) ),
		'sponsor-agreement-returned-typed-in-a-browser'  => array( 'send' => 'sagr-returned', 'args' => array( 'typed' => $crlf ) ),
		'sponsor-agreement-revoked'                      => array( 'send' => 'sagr-revoked' ),
		'sponsor-agreement-revoked-no-name'              => array( 'send' => 'sagr-revoked', 'actor' => 43, 'site' => SITE_NAMED, 'pages' => array() ),
		'sponsor-agreement-revoked-mentor-page-only'     => array( 'send' => 'sagr-revoked', 'pages' => array( 'mentor' ) ),
		'sponsor-agreement-revoked-administrator-name-as-stored' => array( 'send' => 'sagr-revoked', 'actor' => 44 ),
		'sponsor-agreement-revoked-typed-in-a-browser'   => array( 'send' => 'sagr-revoked', 'args' => array( 'typed' => $crlf ) ),
		'sponsor-agreement-reinstated'                   => array( 'send' => 'sagr-reinstated' ),
		'sponsor-agreement-reinstated-no-page'           => array( 'send' => 'sagr-reinstated', 'site' => SITE_NAMED, 'pages' => array() ),
		'sponsor-agreement-reinstated-institution-page-only'=> array( 'send' => 'sagr-reinstated', 'pages' => array( 'institution' ) ),
		// An interest, filed as the Sponsor Dashboard's Offer more form filed one until 1.123.22.
		'interest-everything'                            => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'choices' => array( 'Sponsor a mentor or multiple mentors', 'Sponsor tools or services' ), 'events' => "WordCamp Example 2037\nExample Summit & \"More\"", 'note' => TYPED ) ),
		'interest-note-only-unassigned'                  => array( 'send' => 'interest', 'actor' => 61, 'pages' => array(), 'args' => array( 'manager' => null, 'choices' => array(), 'events' => '', 'note' => TYPED ) ),
		'interest-choices-only-unnamed'                  => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'pat@example.test', 'record' => 'recSEED0000000102', 'choices' => array( 'Other (please specify)' ), 'events' => '', 'note' => '' ) ),
		'interest-events-only-named'                     => array( 'send' => 'interest', 'actor' => 60, 'settings' => $snamed, 'args' => array( 'manager' => null, 'choices' => array(), 'events' => 'WordCamp Example 2037', 'note' => '' ) ),
		'interest-site-name'                             => array( 'send' => 'interest', 'actor' => 60, 'site' => SITE_NAMED, 'args' => array( 'manager' => 'ida@example.test', 'choices' => array( 'Provide financial support (for program costs)' ), 'events' => '', 'note' => 'One line.' ) ),
		// What a sponsor typed as a browser posts a text box, each line break CRLF.
		'interest-note-typed-in-a-browser'               => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'choices' => array(), 'events' => "WordCamp Example 2037\r\nExample Summit", 'note' => str_replace( "\n", "\r\n", TYPED ) ) ),
		'interest-nothing'                               => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'choices' => array(), 'events' => '', 'note' => '' ) ),
		'interest-member-with-no-name'                   => array( 'send' => 'interest', 'actor' => 63, 'args' => array( 'manager' => 'ida@example.test', 'joins' => array( 63 => $of_101 ), 'choices' => array( 'Sponsor tools or services' ), 'events' => '', 'note' => 'One line.' ) ),
		'interest-member-name-as-stored'                 => array( 'send' => 'interest', 'actor' => 64, 'args' => array( 'manager' => 'ida@example.test', 'joins' => array( 64 => $of_101 ), 'choices' => array(), 'events' => '', 'note' => 'One line.' ) ),
		'interest-non-ascii'                             => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'renamed' => array( 'recSEED0000000101' => SPONSOR_NON_ASCII ), 'choices' => array(), 'events' => 'WordCamp Łódź 2037', 'note' => TYPED_NON_ASCII ) ),
		'mentor-interest'                                => array( 'send' => 'mentor-interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'mentor' => 'recSEED0000000201' ) ),
		'mentor-interest-names-unassigned'               => array( 'send' => 'mentor-interest', 'actor' => 61, 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'manager' => null, 'mentor' => 'recSEED0000000202' ) ),
		'mentor-interest-unnamed-sponsor'                => array( 'send' => 'mentor-interest', 'actor' => 60, 'settings' => array( 'sponsor_notify' => 'reviews@example.test' ), 'args' => array( 'manager' => null, 'record' => 'recSEED0000000102', 'mentor' => 'recSEED0000000201' ) ),
		'mentor-interest-sponsored-already'              => array( 'send' => 'mentor-interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'mentor' => 'recSEED0000000203' ) ),
		// To every Administrator, one of them also an institution's member, with every page published.
		'mentor-interest-unassigned-pages'               => array( 'send' => 'mentor-interest', 'actor' => 61, 'args' => array( 'manager' => null, 'mentor' => 'recSEED0000000202' ) ),
		'mentor-interest-non-ascii'                      => array( 'send' => 'mentor-interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'renamed' => array( 'recSEED0000000101' => SPONSOR_NON_ASCII ), 'mentor' => 'recSEED0000000204' ) ),
		// The codes: running low, to the sponsor's members and its Administrator, and a code that
		// did not work, to its Administrator.
		'low-stock'                                      => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 4, 'low' => 5 ) ),
		'low-stock-one-left'                             => array( 'send' => 'low-stock', 'site' => SITE_NAMED, 'args' => array( 'manager' => 'pat@example.test', 'available' => 1, 'low' => 3 ) ),
		'low-stock-unassigned-no-page'                   => array( 'send' => 'low-stock', 'pages' => array(), 'args' => array( 'manager' => null, 'available' => 0, 'low' => 2 ) ),
		'low-stock-student-page-only'                    => array( 'send' => 'low-stock', 'pages' => array( 'student' ), 'args' => array( 'manager' => 'ida@example.test', 'available' => 2, 'low' => 5 ) ),
		'low-stock-title'                                => array( 'send' => 'low-stock', 'pages' => array( 'sponsor' ), 'args' => array( 'manager' => 'ida@example.test', 'available' => 3, 'low' => 10, 'title' => 'Hosting &lt;3 &amp; "more" 20% {codes_left}' ) ),
		'low-stock-already-sent'                         => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 1, 'low' => 5, 'sent' => 2088000000 ) ),
		'low-stock-not-below'                            => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 5, 'low' => 5 ) ),
		'low-stock-administrator-page-only'              => array( 'send' => 'low-stock', 'pages' => array( 'administrator' ), 'args' => array( 'manager' => 'ida@example.test', 'available' => 1, 'low' => 2 ) ),
		'low-stock-link-offer'                           => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 0, 'low' => 5, 'kind' => 'link' ) ),
		'low-stock-no-members'                           => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 1, 'low' => 2, 'record' => 'recSEED0000000102' ) ),
		// The highest low mark the Offers form takes, and the count below it.
		'low-stock-at-the-highest-mark'                  => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 999, 'low' => 1000 ) ),
		'low-stock-non-ascii'                            => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 1, 'low' => 2, 'renamed' => array( 'recSEED0000000101' => SPONSOR_NON_ASCII ), 'title' => 'Hosting für Studenten &amp; Schüler „100%”' ) ),
		'claim-problem'                                  => array( 'send' => 'claim-problem', 'actor' => 70, 'args' => array( 'manager' => 'ida@example.test', 'index' => 3 ) ),
		'claim-problem-shared-code-unassigned'           => array( 'send' => 'claim-problem', 'actor' => 70, 'site' => SITE_NAMED, 'pages' => array(), 'args' => array( 'manager' => null, 'index' => -1 ) ),
		'claim-problem-title-assigned-no-account'        => array( 'send' => 'claim-problem', 'actor' => 70, 'args' => array( 'manager' => 'pat@example.test', 'index' => 0, 'title' => 'Hosting &lt;3 &amp; "more" 20% {offer_title}' ) ),
		'claim-problem-not-claimed'                      => array( 'send' => 'claim-problem', 'actor' => 70, 'args' => array( 'manager' => 'ida@example.test', 'index' => null ) ),
		'claim-problem-claimant-name-as-stored'          => array( 'send' => 'claim-problem', 'actor' => 71, 'args' => array( 'manager' => 'ida@example.test', 'index' => 2 ) ),
		'claim-problem-non-ascii'                        => array( 'send' => 'claim-problem', 'actor' => 70, 'args' => array( 'manager' => 'ida@example.test', 'index' => 3, 'title' => 'Hosting für Studenten &amp; Schüler „100%”' ) ),
		// A sponsor post sent back to its author, through its handler.
		'post-returned'                                  => array( 'send' => 'post-returned', 'args' => array( 'author' => 60 ) ),
		'post-returned-two-roles-student-page-only'      => array( 'send' => 'post-returned', 'pages' => array( 'student' ), 'args' => array( 'author' => 61, 'title' => 'Hosting &lt;3 &amp; "more" 20% {post_title}' ) ),
		'post-returned-site-name'                        => array( 'send' => 'post-returned', 'site' => SITE_NAMED, 'pages' => array( 'sponsor' ), 'args' => array( 'author' => 60 ) ),
		'post-returned-untitled'                         => array( 'send' => 'post-returned', 'args' => array( 'author' => 60, 'title' => '' ) ),
		'post-returned-author-with-no-name'              => array( 'send' => 'post-returned', 'args' => array( 'author' => 63 ) ),
		// A note as a browser posts a text box, each line break CRLF.
		'post-returned-note-typed-in-a-browser'          => array( 'send' => 'post-returned', 'args' => array( 'author' => 60, 'note' => str_replace( "\n", "\r\n", TYPED ) ) ),
		'post-returned-no-note'                          => array( 'send' => 'post-returned', 'args' => array( 'author' => 60, 'note' => '' ) ),
		'post-returned-author-gone'                      => array( 'send' => 'post-returned', 'args' => array( 'author' => 99 ) ),
		'post-returned-author-name-as-stored'            => array( 'send' => 'post-returned', 'args' => array( 'author' => 64 ) ),
		'post-returned-non-ascii'                        => array( 'send' => 'post-returned', 'args' => array( 'author' => 60, 'title' => 'Nasz pierwszy wpis: część „1” &amp; więcej', 'note' => TYPED_NON_ASCII ) ),
		'post-returned-author-with-no-address'           => array( 'send' => 'post-returned', 'args' => array( 'author' => 65 ) ),
	);

	foreach ( $cases as $name => $case ) {
		$cases[ $name ] = array_merge( $base, $case );
	}

	return $cases;
}

/**
 * The cases 1.122.23 adds: inputs its items need that the 1.122.21 recording holds no email for,
 * recorded from 1.122.22 (`--record-added`), in the world `cases()` describes.
 *
 * @return array<string,array>
 */
function added_cases() {
	$base = array(
		'actor'    => 40,
		'pages'    => array( 'student', 'mentor', 'institution', 'sponsor', 'administrator' ),
		'site'     => 'WordPress Credits',
		'settings' => array(),
		'args'     => array(),
	);

	$cases = array(
		// A signed PDF posted to the Sponsor Dashboard's form, by the sponsor's member, whom 1.122.22
		// refused ("That file is not a PDF."), and by an Administrator for the sponsor (item 1).
		'sponsor-agreement-uploaded-by-a-member'           => array( 'send' => 'sagr-upload', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test' ) ),
		'sponsor-agreement-uploaded-by-an-administrator'   => array( 'send' => 'sagr-upload', 'actor' => 40, 'args' => array( 'manager' => 'ida@example.test' ) ),
		// A revocation with a country contact, and an invitation, by an Administrator whose account
		// holds no name; and an invitation by a member whose account holds a name and no address,
		// which no reply can reach (item 2).
		'agreement-revoked-with-a-contact-administrator-with-no-name' => array( 'send' => 'agr-revoked', 'actor' => 43 ),
		'invite-inviter-with-no-name'                      => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 43 ) ),
		'invite-inviter-without-an-address'                => array( 'send' => 'inst-invite', 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 58 ) ),
		// An Administrator whose account's name is spaces returns an agreement, the institution's and
		// the sponsor's; and an offer's codes counted past a thousand (item 2).
		'agreement-returned-administrator-name-of-spaces'  => array( 'send' => 'agr-returned', 'actor' => 49 ),
		'sponsor-agreement-returned-administrator-name-of-spaces' => array( 'send' => 'sagr-returned', 'actor' => 49 ),
		'low-stock-past-a-thousand'                        => array( 'send' => 'low-stock', 'args' => array( 'manager' => 'ida@example.test', 'available' => 4999, 'low' => 5000 ) ),
		// Names as the form's cleaner keeps a "<" typed in them, and the quote marks and the "&" after
		// it (item 3).
		'sponsor-applied-name-as-cleaned'                  => array( 'send' => 'spon-applied', 'args' => array( 'name' => CLEANED_SPONSOR ) ),
		'sponsor-application-name-as-cleaned'              => array( 'send' => 'spon-application', 'args' => array( 'name' => CLEANED_SPONSOR ) ),
		'application-fields-as-cleaned'                    => array( 'send' => 'inst-application', 'args' => array( 'city' => 'Łódź &lt;3', 'person' => 'Ana & Bo &lt;3' ) ),
		// The same names as the index carries them on, into the subjects that name a company or an
		// institution, and a name holding a run the subject's cleaner reads as a tag (item 3).
		'sponsor-agreement-received-name-as-cleaned'       => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'ida@example.test', 'author' => 60, 'renamed' => array( 'recSEED0000000101' => CLEANED_SPONSOR ) ) ),
		'sponsor-agreement-received-tag-like-name'         => array( 'send' => 'sagr-received', 'args' => array( 'manager' => 'ida@example.test', 'author' => 60, 'renamed' => array( 'recSEED0000000101' => TAG_LIKE_SPONSOR ) ) ),
		'interest-name-as-cleaned'                         => array( 'send' => 'interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'renamed' => array( 'recSEED0000000101' => CLEANED_SPONSOR ), 'choices' => array(), 'events' => '', 'note' => 'One line.' ) ),
		'mentor-interest-name-as-cleaned'                  => array( 'send' => 'mentor-interest', 'actor' => 60, 'args' => array( 'manager' => 'ida@example.test', 'renamed' => array( 'recSEED0000000101' => CLEANED_SPONSOR ), 'mentor' => 'recSEED0000000201' ) ),
		'member-last-institution-name-as-cleaned'          => array( 'send' => 'member-last', 'args' => array( 'user' => 53, 'reason' => 'removed', 'profile' => CLEANED_INSTITUTION ) ),
		// A Reply-To for a name holding a double quote and a semicolon, for one longer than an encoded
		// word holds, and for one holding a line break (item 4).
		'information-administrator-with-a-double-quote'    => array( 'send' => 'inst-information', 'actor' => 46, 'args' => array( 'manage' => array( 46 ) ) ),
		'sponsor-agreement-returned-administrator-with-a-long-name' => array( 'send' => 'sagr-returned', 'actor' => 47 ),
		'information-administrator-name-with-a-line-break' => array( 'send' => 'inst-information', 'actor' => 48, 'args' => array( 'manage' => array( 48 ) ) ),
		// The courtesy scan's notes as the scan stores them, each name with its slash (item 6).
		'agreement-landed-one-flag-as-scanned'             => array( 'send' => 'agr-landed', 'args' => array( 'author' => 50, 'flags' => array( '/OpenAction' ) ) ),
		'agreement-landed-every-flag-as-scanned'           => array( 'send' => 'agr-landed', 'args' => array( 'author' => 51, 'flags' => array( '/JavaScript', '/JS', '/OpenAction', '/AA', '/EmbeddedFile' ) ) ),
	);

	foreach ( $cases as $name => $case ) {
		$cases[ $name ] = array_merge( $base, $case );
	}

	return $cases;
}

/**
 * A private method of a class, as its own class calls it.
 *
 * @param string $class  The class.
 * @param string $method The method.
 * @return ReflectionMethod
 */
function reach( $class, $method ) {
	$reached = new ReflectionMethod( $class, $method );

	if ( PHP_VERSION_ID < 80100 ) {
		$reached->setAccessible( true );
	}

	return $reached;
}

/**
 * A post, with its meta.
 *
 * @param int    $id    Its ID.
 * @param string $type  Its type.
 * @param array  $props Its title, date, author and status.
 * @param array  $meta  Key to value.
 */
function post( $id, $type, array $props, array $meta ) {
	$post            = new WP_Post();
	$post->ID        = $id;
	$post->post_type = $type;

	foreach ( $props as $k => $v ) {
		$post->$k = $v;
	}

	$GLOBALS['posts'][ $id ] = $post;
	$GLOBALS['pmeta'][ $id ] = array();

	foreach ( $meta as $k => $v ) {
		$GLOBALS['pmeta'][ $id ][ $k ] = array( $v );
	}
}

/**
 * An interest filed as the Sponsor Dashboard's Offer more form filed one until 1.123.22, when the card
 * left the page: the ways ticked that are among the choices, each line of the events through the
 * one-line cleaner with a blank one naming none, and the note through the text area reader, handed to
 * `WPCPM_Sponsor_Interests::file()`, the road the Sponsorship card's Talk to your program contact
 * takes. Nothing is filed for a typing the cleaner would take words from, or with nothing to say, as
 * the form refused both, so every case's inputs reach the email as they reached it when it was
 * recorded.
 *
 * @param array $args The case's `record`, `choices`, `events` and `note`.
 */
function file_interest( array $args ) {
	$record  = $args['record'] ?? 'recSEED0000000101';
	$_POST   = array(
		'wpcpm_events' => $args['events'],
		'wpcpm_note'   => $args['note'],
	);
	$choices = array_values( array_intersect( WPCPM_Sponsor_Interests::CHOICES, array_map( 'sanitize_text_field', (array) $args['choices'] ) ) );
	$events  = array();

	foreach ( array( $args['events'], $args['note'] ) as $typed ) {
		if ( WPCPM_Typed_Text::cleaner_loses( (string) $typed, 'lines' ) ) {
			$_POST = array();
			return;
		}
	}

	foreach ( preg_split( '/\r\n|\r|\n/', WPCPM_Request::posted_lines( 'wpcpm_events' ) ) as $event ) {
		$event = sanitize_text_field( $event );

		if ( '' !== $event ) {
			$events[] = $event;
		}
	}

	$note  = WPCPM_Request::posted_lines( 'wpcpm_note' );
	$_POST = array();

	if ( empty( $choices ) && empty( $events ) && '' === $note ) {
		return;
	}

	WPCPM_Sponsor_Interests::file( $record, WPCPM_Sponsors_Index::row( $record ), $choices, $events, $note, wp_get_current_user()->display_name, 'member' );
}

/**
 * Run a handler until the redirect or `wp_die()` it ends with.
 *
 * @param callable $handler The handler.
 */
function press( $handler ) {
	try {
		call_user_func( $handler );
	} catch ( WPCPM_Test_Stop $stop ) {
		return;
	}
}

/**
 * One case's emails, through the real senders and the real mail layer.
 *
 * @param array $case One of `cases()`.
 * @return array[] Each email as `wp_mail()` was handed it, in the order it was sent.
 */
function emails_of( array $case ) {
	$GLOBALS['mail']      = array();
	$GLOBALS['posts']     = array();
	$GLOBALS['pmeta']     = array();
	$GLOBALS['waiting']   = array();
	$GLOBALS['uid']       = (int) $case['actor'];
	$GLOBALS['site_name'] = $case['site'];
	$GLOBALS['opts']      = array(
		'date_format'              => 'F j, Y',
		'time_format'              => 'g:i a',
		WPCPM_Settings::OPT_NAME   => $case['settings'],
	);
	$GLOBALS['umeta']     = memberships();
	$_POST                = array();

	foreach ( array( 'student' => 'WPCPM_Students_Dashboard', 'mentor' => 'WPCPM_Mentors_Dashboard', 'institution' => 'WPCPM_Institutions_Dashboard', 'sponsor' => 'WPCPM_Sponsors_Dashboard', 'administrator' => 'WPCPM_Administrators_Dashboard' ) as $page => $class ) {
		$class::$url = in_array( $page, $case['pages'], true ) ? 'https://example.test/' . $page . '-dashboard/' : '';
	}

	WPCPM_Institutions_Index::$rows = array(
		'recSEED0000000016' => array( 'name' => NAMED . ' ', 'country' => 'PL' ),
		'recSEED0000000017' => array( 'name' => '  ', 'country' => '' ),
		'recSEED0000000018' => array( 'name' => 'Second College', 'country' => 'XX' ),
		'recSEED0000000020' => array( 'name' => 'Third College', 'country' => 'ZZ' ),
	);
	// Each sponsor's row as the store keeps it, its interests' history with it.
	WPCPM_Sponsors_Index::$rows    = array(
		'recSEED0000000101' => array( 'name' => 'Acme & "Sons" 50% Hosting', 'support' => array(), 'interests' => '' ),
		'recSEED0000000102' => array( 'name' => '', 'support' => array(), 'interests' => '' ),
	);
	WPCPM_Sponsors_Index::$manager = null;

	$args = $case['args'];

	// A name a case gives an institution or a sponsor the index holds, in place of its own.
	foreach ( $args['renamed'] ?? array() as $record => $name ) {
		if ( isset( WPCPM_Institutions_Index::$rows[ $record ] ) ) {
			WPCPM_Institutions_Index::$rows[ $record ]['name'] = $name;
		} else {
			WPCPM_Sponsors_Index::$rows[ $record ]['name'] = $name;
		}
	}

	// A membership a case gives an account, beside every case's.
	foreach ( $args['joins'] ?? array() as $user => $meta ) {
		$GLOBALS['umeta'][ $user ] = $meta + ( $GLOBALS['umeta'][ $user ] ?? array() );
	}

	// Who may manage the program: the four Administrators and any a case adds, or, for a case that
	// says so, nobody.
	$GLOBALS['manage'] = empty( $args['nobody'] ) ? array_merge( array( 40, 41, 43, 55 ), $args['manage'] ?? array() ) : array();

	if ( array_key_exists( 'manager', $args ) && null !== $args['manager'] ) {
		WPCPM_Sponsors_Index::$manager = array( 'email' => $args['manager'] );
	}

	switch ( $case['send'] ) {
		case 'inst-applied':
		case 'inst-application':
		case 'inst-information':
		case 'inst-declined':
			$routing = array_key_exists( 'routing', $args ) ? '' : array( 'email' => 'pl-contact@example.test', 'calendly' => $args['calendly'] ?? 'https://calendly.example.test/pl-contact' );
			post(
				701,
				WPCPM_Institution_Application::POST_TYPE,
				array( 'post_title' => $args['name'] ?? 'Example Polytechnic', 'post_date' => '2036-03-09 18:05:00' ),
				array(
					WPCPM_Institution_Application::META_FIELDS       => array(
						'Name'           => $args['name'] ?? 'Example Polytechnic',
						'City'           => $args['city'] ?? 'Krakow',
						'Contact Person' => $args['person'] ?? 'Ana Applicant',
						'Contact Email'  => $args['email'] ?? 'ana@example.test',
					),
					WPCPM_Institution_Application::META_REFERENCE    => 'APP-2036-0701',
					WPCPM_Institution_Application::META_COUNTRY_NAME => $args['country'] ?? 'Poland',
					WPCPM_Institution_Application::META_MANAGER      => $routing,
					WPCPM_Institution_Application::META_EMAIL        => 'hashed-address',
					WPCPM_Institution_Application::META_STATE        => WPCPM_Institution_Application::STATE_NEW,
				)
			);

			if ( 'inst-applied' === $case['send'] ) {
				reach( 'WPCPM_Institution_Application', 'mail_applicant' )->invoke( null, 701 );
			} elseif ( 'inst-application' === $case['send'] ) {
				reach( 'WPCPM_Institution_Application', 'mail_managers' )->invoke( null, 701 );
			} else {
				$_POST = array(
					WPCPM_Institutions::FIELD_APPLICATION                       => '701',
					'inst-information' === $case['send'] ? 'wpcpm_question' : 'wpcpm_reason' => $args['typed'] ?? TYPED,
				);
				press( array( new WPCPM_Institutions(), 'inst-information' === $case['send'] ? 'handle_info' : 'handle_reject' ) );
			}
			break;

		case 'inst-invite':
			post(
				721,
				WPCPM_Institution_Invite::POST_TYPE,
				array(),
				array(
					WPCPM_Institution_Invite::META_EMAIL       => $args['email'] ?? 'colleague@example.test',
					WPCPM_Institution_Invite::META_INSTITUTION => $args['record'],
					WPCPM_Institution_Invite::META_ACTOR       => $args['actor'],
				)
			);
			reach( 'WPCPM_Institution_Invite', 'mail_invite' )->invoke( null, 721, 'EXAMPLE-NOT-A-REAL-TOKEN-0721' );
			break;

		case 'agr-received':
		case 'agr-landed':
		case 'agr-accepted':
		case 'agr-reinstated':
		case 'agr-returned':
		case 'agr-revoked':
			$record = $args['record'] ?? 'recSEED0000000016';
			post(
				731,
				WPCPM_Institution_Agreement::POST_TYPE,
				array( 'post_date' => '2036-03-02 09:15:00', 'post_author' => $args['author'] ?? 50 ),
				array(
					WPCPM_Institution_Agreement::META_INSTITUTION => $record,
					WPCPM_Institution_Agreement::META_FLAGS       => $args['flags'] ?? array(),
					WPCPM_Institution_Agreement::META_STATE       => 'submitted',
				)
			);
			$method = array(
				'agr-received'   => 'mail_received',
				'agr-landed'     => 'mail_landed',
				'agr-accepted'   => 'mail_accepted',
				'agr-reinstated' => 'mail_reinstated',
				'agr-returned'   => 'mail_returned',
				'agr-revoked'    => 'mail_revoked',
			)[ $case['send'] ];

			if ( in_array( $case['send'], array( 'agr-returned', 'agr-revoked' ), true ) ) {
				reach( 'WPCPM_Institution_Agreement', $method )->invoke( null, $record, 731, sanitize_textarea_field( $args['typed'] ?? TYPED ) );
			} else {
				reach( 'WPCPM_Institution_Agreement', $method )->invoke( null, $record, 731 );
			}
			break;

		case 'agr-reminder':
			// Waiting longer than the review window by PHP's own clock, which the digest reads, and one
			// that never will be.
			$dates = array( 741 => '2026-01-05 10:00:00', 742 => '2026-02-20 23:30:00', 743 => '2026-03-01 08:00:00', 744 => '2099-01-01 00:00:00' );
			$owner = array( 741 => 'recSEED0000000016', 742 => 'recSEED0000000017', 743 => 'recSEED0000000018', 744 => 'recSEED0000000018' );

			foreach ( array_merge( $args['waiting'], $args['later'] ) as $id ) {
				post( $id, WPCPM_Institution_Agreement::POST_TYPE, array( 'post_date' => $dates[ $id ], 'post_author' => 50 ), array( WPCPM_Institution_Agreement::META_INSTITUTION => $owner[ $id ], WPCPM_Institution_Agreement::META_STATE => 'submitted' ) );
			}

			$GLOBALS['waiting'] = array_merge( $args['waiting'], $args['later'] );

			if ( ! empty( $args['reminded'] ) ) {
				$GLOBALS['opts'][ WPCPM_Institution_Agreement::OPT_REMINDED ] = wp_date( 'Y-m-d' );
			}

			WPCPM_Institution_Agreement::remind();
			break;

		case 'agr-template':
			press(
				static function () use ( $args ) {
					reach( 'WPCPM_Agreement_Generate', 'template_needs_attention' )->invoke( null, 'recSEED0000000016', $args['name'] ?? 'Example University', 'en', new WP_Error( 'wpcpm_template_token', 'The template still holds [Signatory Title] after the merge, and a "<token>" can\'t be printed.' ) );
				}
			);
			break;

		case 'member-last':
			if ( '' !== $args['profile'] ) {
				$GLOBALS['umeta'][ $args['user'] ][ WPCPM_Institution_Members::META_PROFILE ] = array( 'name' => $args['profile'] );
			}

			WPCPM_Institution_Members::detach( $args['user'], $args['reason'], (int) $case['actor'] );
			break;

		case 'spon-applied':
		case 'spon-application':
		case 'spon-information':
		case 'spon-declined':
			post(
				801,
				WPCPM_Sponsor_Application::POST_TYPE,
				array( 'post_title' => 'Acme Title &amp; Co', 'post_date' => '2036-03-09 17:40:00' ),
				array(
					WPCPM_Sponsor_Application::META_FIELDS    => array(
						WPCPM_Sponsor_Application::COL_NAME    => $args['name'] ?? 'Acme Hosting',
						WPCPM_Sponsor_Application::COL_WEBSITE => $args['website'] ?? 'https://acme.example.test',
						WPCPM_Sponsor_Application::COL_PERSON  => $args['person'] ?? 'Sol Applicant',
						WPCPM_Sponsor_Application::COL_EMAIL   => $args['email'] ?? 'sol@example.test',
					),
					WPCPM_Sponsor_Application::META_REFERENCE => 'SAPP-2036-0801',
					WPCPM_Sponsor_Application::META_SIGNALS   => $args['signals'] ?? ( ! empty( $args['skipped'] ) ? array( 'site-ceiling', 'files-skipped' ) : array() ),
					WPCPM_Sponsor_Application::META_STATE     => WPCPM_Sponsor_Application::STATE_NEW,
				)
			);

			if ( 'spon-applied' === $case['send'] ) {
				reach( 'WPCPM_Sponsor_Application', 'mail_applicant' )->invoke( null, 801 );
			} elseif ( 'spon-application' === $case['send'] ) {
				reach( 'WPCPM_Sponsor_Application', 'mail_managers' )->invoke( null, 801 );
			} else {
				$_POST = array(
					WPCPM_Sponsor_Application::FIELD_APPLICATION                => '801',
					'spon-information' === $case['send'] ? 'wpcpm_question' : 'wpcpm_reason' => $args['typed'] ?? TYPED,
				);
				press( array( 'WPCPM_Sponsor_Application', 'spon-information' === $case['send'] ? 'handle_info' : 'handle_reject' ) );
			}
			break;

		case 'sagr-received':
		case 'sagr-accepted':
		case 'sagr-returned':
		case 'sagr-revoked':
		case 'sagr-reinstated':
			$record = $args['record'] ?? 'recSEED0000000101';
			post(
				831,
				WPCPM_Sponsor_Agreement::POST_TYPE,
				array( 'post_date' => '2036-03-03 16:20:00', 'post_author' => $args['author'] ?? 60 ),
				array( WPCPM_Sponsor_Agreement::META_SPONSOR => $record )
			);

			if ( 'sagr-received' === $case['send'] || 'sagr-accepted' === $case['send'] ) {
				reach( 'WPCPM_Sponsor_Agreement', 'sagr-received' === $case['send'] ? 'mail_received' : 'mail_accepted' )->invoke( null, $record, 831 );
			} elseif ( 'sagr-reinstated' === $case['send'] ) {
				reach( 'WPCPM_Sponsor_Agreement', 'mail_reinstated' )->invoke( null, $record );
			} else {
				reach( 'WPCPM_Sponsor_Agreement', 'sagr-returned' === $case['send'] ? 'mail_returned' : 'mail_revoked' )->invoke( null, $record, sanitize_textarea_field( $args['typed'] ?? TYPED ) );
			}
			break;

		case 'sagr-upload':
			// A signed PDF posted to the Sponsor Dashboard's form as a browser posts it, by the account
			// signed in, the declaration ticked, and the handler run to the redirect it ends with.
			$file = wpcpm_test_tempnam( 'wpcpm-upload-', 'pdf' );
			file_put_contents( $file, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n" );
			$_POST  = array(
				'wpcpm_sponsor'                       => $args['record'] ?? 'recSEED0000000101',
				WPCPM_Sponsor_Agreement::FIELD_SIGNED => '1',
			);
			$_FILES = array(
				WPCPM_Sponsor_Agreement::FIELD_FILE => array(
					'name'     => 'signed-agreement.pdf',
					'type'     => 'application/pdf',
					'tmp_name' => $file,
					'error'    => UPLOAD_ERR_OK,
					'size'     => (int) filesize( $file ),
				),
			);
			press( array( 'WPCPM_Sponsor_Agreement', 'handle_upload' ) );
			$_FILES = array();
			break;

		case 'interest':
			file_interest( $args );
			break;

		case 'mentor-interest':
			$_POST = array(
				'wpcpm_sponsor' => $args['record'] ?? 'recSEED0000000101',
				'wpcpm_mentor'  => $args['mentor'],
			);
			press( array( 'WPCPM_Sponsor_Mentors', 'handle_interest' ) );
			break;

		case 'low-stock':
			WPCPM_Sponsor_Codes::$available = $args['available'];
			WPCPM_Sponsor_Claims::maybe_low_stock(
				array(
					'id'       => 851,
					'sponsor'  => $args['record'] ?? 'recSEED0000000101',
					'title'    => $args['title'] ?? 'Hosting for students',
					'kind'     => $args['kind'] ?? WPCPM_Sponsor_Offers::KIND_CODES,
					'low'      => $args['low'],
					'low_sent' => $args['sent'] ?? 0,
				)
			);
			break;

		case 'claim-problem':
			WPCPM_Sponsor_Offers::$offers = array(
				852 => array(
					'id'      => 852,
					'sponsor' => 'recSEED0000000101',
					'title'   => $args['title'] ?? 'Hosting for students',
					'kind'    => WPCPM_Sponsor_Offers::KIND_CODES,
				),
			);
			// The claimant is the account signed in.
			if ( null !== $args['index'] ) {
				$GLOBALS['umeta'][ (int) $case['actor'] ][ WPCPM_Sponsor_Claims::META_CLAIMS ] = array( 852 => array( 'i' => $args['index'], 'at' => 2088000000 ) );
			}

			WPCPM_Sponsor_Claims::report_problem( 852, (int) $case['actor'] );
			break;

		case 'post-returned':
			post(
				861,
				'post',
				array( 'post_title' => $args['title'] ?? 'Our &amp; your "first" post', 'post_status' => 'pending', 'post_author' => $args['author'] ),
				array( WPCPM_Sponsor_Policy::META_POST_SPONSOR => 'recSEED0000000101' )
			);
			$_POST = array(
				'wpcpm_post' => '861',
				'wpcpm_note' => $args['note'] ?? TYPED,
			);
			press( array( 'WPCPM_Sponsor_Posts', 'handle_return' ) );
			break;
	}

	return array_map( 'recorded_form', $GLOBALS['mail'] );
}

/**
 * An email as the recording holds it: every address written "at example.test".
 *
 * @param array $mail What `wp_mail()` was handed.
 * @return array
 */
function recorded_form( array $mail ) {
	$plain = static function ( $text ) {
		return preg_replace( '/([A-Za-z0-9._%+-]+)@example\.test/', '$1 at example.test', (string) $text );
	};

	$mail['to']      = $plain( $mail['to'] );
	$mail['body']    = $plain( $mail['body'] );
	$mail['subject'] = $plain( $mail['subject'] );
	$mail['headers'] = array_map( $plain, $mail['headers'] );

	return $mail;
}

/**
 * The id each recorded email is logged under now: its template's own since 1.122.22, where the
 * recording holds the id the email shared with another that says other words; as recorded for every
 * other.
 *
 * @param string $case     The case.
 * @param int    $index    Which of its emails, from 0.
 * @param string $recorded The id the recording holds.
 * @return string
 */
function sent_as( $case, $index, $recorded ) {
	$ids = array(
		'applied-no-call'                            => 'institution-applied-no-call',
		'applied-no-routing'                         => 'institution-applied-no-call',
		'agreement-landed-one-flag'                  => 'agreement-landed-flagged',
		'agreement-landed-three-flags'               => 'agreement-landed-flagged',
		'agreement-reinstated'                       => 'agreement-reinstated',
		'agreement-reinstated-no-page'               => 'agreement-reinstated',
		'agreement-reinstated-institution-page-only' => 'agreement-reinstated',
		'agreement-revoked-contact-with-no-address'  => 'agreement-revoked-no-contact',
		'agreement-revoked-no-country'               => 'agreement-revoked-no-contact',
		'agreement-revoked-unknown-country'          => 'agreement-revoked-no-contact',
		'agreement-revoked-no-contact-typed-in-a-browser' => 'agreement-revoked-no-contact',
		'sponsor-applied-logo-not-kept'              => 'sponsor-applied-no-logo',
		'sponsor-applied-names'                      => 'sponsor-applied-no-logo',
		'mentor-interest'                            => 'sponsor-mentor-interest',
		'mentor-interest-names-unassigned'           => 'sponsor-mentor-interest',
		'mentor-interest-unnamed-sponsor'            => 'sponsor-mentor-interest',
		'mentor-interest-unassigned-pages'           => 'sponsor-mentor-interest',
		'mentor-interest-non-ascii'                  => 'sponsor-mentor-interest',
	);

	return isset( $ids[ $case ] ) ? $ids[ $case ] : $recorded;
}

/**
 * What 1.122.23 changes on purpose in the emails of the two recordings, each change with the item of
 * the 1.122.23 plan it comes from. A row names a case and its item, and either the emails the case
 * sends now, in full (`emails`, for a case whose recording holds a different set of emails), or the
 * text that changes in each email it sends, or in those `only` names by their place in the
 * recording (`replace`: a field, `context`, `subject`, `body` or `headers`, to pairs of the text the
 * recording holds and the text that stands in its place now). Every other email of both recordings
 * is sent as recorded, byte for byte.
 *
 * @return array[]
 */
function changes() {
	$rows = array(
		// Item 1: the sponsor's member's signed PDF is taken, so its arrival is told to the sponsor's
		// assigned Administrator, as an Administrator's upload for the sponsor always was.
		array(
			'case'   => 'sponsor-agreement-uploaded-by-a-member',
			'item'   => 1,
			'emails' => array(
				array(
					'to'      => 'ida at example.test',
					'context' => 'sponsor-agreement-received',
					'subject' => '[WordPress Credits] Acme & "Sons" 50% Hosting uploaded a signed Collaboration Agreement',
					'body'    => "Acme & \"Sons\" 50% Hosting uploaded a signed Collaboration Agreement on 2036-03-09 (Sami Sponsor).\r\n\r\nThe program aims to answer within 3 working days.\r\n\r\nAccept or return it on the Administrator Dashboard. Read it first on the Sponsors screen:\r\n\r\nhttps://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nhttps://example.test/administrator-dashboard/\r\nThis email comes from the WordPress Credits program.",
					'headers' => array(),
					'files'   => 0,
				),
			),
		),
		// Item 3: a name or a title as WordPress stores it reaches every email as a person reads it:
		// an application's title, which kses writes with `&amp;`; its fields and a company's name as the
		// form's cleaner keeps a "<", `&lt;` and the quote marks and "&" after it as entities; and every
		// account's name (`_wp_specialchars()` on save). Every subject is plain text now, so such a name
		// reads as typed there too, a "<" and a run the subject's cleaner read as a tag kept.
		array(
			'case'    => 'applied-title-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Arts &amp; Sciences &lt;Faculty "Uni" 100%' => 'Arts & Sciences <Faculty "Uni" 100%' ) ),
		),
		array(
			'case'    => 'application-title-as-stored',
			'item'    => 3,
			'replace' => array(
				'subject' => array( 'Arts &amp; Sciences &lt;Faculty "Uni" 100%' => 'Arts & Sciences <Faculty "Uni" 100%' ),
				'body'    => array( 'Arts &amp; Sciences &lt;Faculty "Uni" 100%' => 'Arts & Sciences <Faculty "Uni" 100%' ),
			),
		),
		array(
			'case'    => 'application-fields-as-cleaned',
			'item'    => 3,
			'replace' => array(
				'body' => array( 'Łódź &lt;3' => 'Łódź <3', 'Ana & Bo &lt;3' => 'Ana & Bo <3' ),
			),
		),
		array(
			'case'    => 'sponsor-applied-name-from-the-title',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Acme Title &amp; Co' => 'Acme Title & Co' ) ),
		),
		array(
			'case'    => 'sponsor-applied-name-as-cleaned',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Acme &lt;3 &quot;Co&quot; &amp; Sons' => 'Acme <3 "Co" & Sons' ) ),
		),
		array(
			'case'    => 'sponsor-application-name-as-cleaned',
			'item'    => 3,
			'replace' => array(
				'subject' => array( 'Acme &lt;3 &quot;Co&quot; &amp; Sons' => 'Acme <3 "Co" & Sons' ),
				'body'    => array( 'Acme &lt;3 &quot;Co&quot; &amp; Sons' => 'Acme <3 "Co" & Sons' ),
			),
		),
		array(
			'case'    => 'invite-inviter-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Mia &amp; Max &lt;Team' => 'Mia & Max <Team' ) ),
		),
		array(
			'case'    => 'agreement-received-uploader-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Mia &amp; Max &lt;Team' => 'Mia & Max <Team' ) ),
		),
		array(
			'case'    => 'agreement-landed-uploader-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Mia &amp; Max &lt;Team' => 'Mia & Max <Team' ) ),
		),
		array(
			'case'    => 'member-last-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Mia &amp; Max &lt;Team' => 'Mia & Max <Team' ) ),
		),
		array(
			'case'    => 'agreement-returned-administrator-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Gus &amp; Gwen &lt;Ops' => 'Gus & Gwen <Ops' ) ),
		),
		array(
			'case'    => 'agreement-revoked-administrator-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Gus &amp; Gwen &lt;Ops' => 'Gus & Gwen <Ops' ) ),
		),
		array(
			'case'    => 'sponsor-agreement-returned-administrator-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Gus &amp; Gwen &lt;Ops' => 'Gus & Gwen <Ops' ) ),
		),
		array(
			'case'    => 'sponsor-agreement-received-uploader-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Sid &amp; Sal &lt;Sales' => 'Sid & Sal <Sales' ) ),
		),
		array(
			'case'    => 'post-returned-author-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Sid &amp; Sal &lt;Sales' => 'Sid & Sal <Sales' ) ),
		),
		array(
			'case'    => 'claim-problem-claimant-name-as-stored',
			'item'    => 3,
			'replace' => array( 'body' => array( 'Kit &amp; Kin &lt;3' => 'Kit & Kin <3' ) ),
		),
		array(
			'case'    => 'sponsor-agreement-received-name-as-cleaned',
			'item'    => 3,
			'replace' => array(
				'subject' => array( 'Acme &lt;3 &quot;Co&quot; &amp; Sons' => 'Acme <3 "Co" & Sons' ),
				'body'    => array( 'Acme &lt;3 &quot;Co&quot; &amp; Sons' => 'Acme <3 "Co" & Sons' ),
			),
		),
		array(
			'case'    => 'sponsor-agreement-received-tag-like-name',
			'item'    => 3,
			'replace' => array(
				'subject' => array( 'Acme &lt;b&gt;Co&lt;/b&gt; Hosting' => 'Acme <b>Co</b> Hosting' ),
				'body'    => array( 'Acme &lt;b&gt;Co&lt;/b&gt; Hosting' => 'Acme <b>Co</b> Hosting' ),
			),
		),
		array(
			'case'    => 'interest-name-as-cleaned',
			'item'    => 3,
			'replace' => array(
				'subject' => array( 'Acme &lt;3 &quot;Co&quot; &amp; Sons' => 'Acme <3 "Co" & Sons' ),
				'body'    => array( 'Acme &lt;3 &quot;Co&quot; &amp; Sons' => 'Acme <3 "Co" & Sons' ),
			),
		),
		array(
			'case'    => 'mentor-interest-name-as-cleaned',
			'item'    => 3,
			'replace' => array(
				'subject' => array( 'Acme &lt;3 &quot;Co&quot; &amp; Sons' => 'Acme <3 "Co" & Sons' ),
				'body'    => array( 'Acme &lt;3 &quot;Co&quot; &amp; Sons' => 'Acme <3 "Co" & Sons' ),
			),
		),
		array(
			'case'    => 'member-last-institution-name-as-cleaned',
			'item'    => 3,
			'replace' => array(
				'subject' => array( 'Faculty &lt;3 &quot;Arts&quot; &amp; Design' => 'Faculty <3 "Arts" & Design' ),
				'body'    => array( 'Faculty &lt;3 &quot;Arts&quot; &amp; Design' => 'Faculty <3 "Arts" & Design' ),
			),
		),
		// Item 4: a Reply-To keeps every character of a name. A name as WordPress stores it arrives read
		// back, its ";" no longer lost; a name goes as it is, in no quotes, for the mailer to quote,
		// escape and encode (the fix wave); and a name holding a comma, which `wp_mail()` would split the
		// header at, goes as encoded words, which keep it, in as many words as it takes.
		array(
			'case'    => 'information-administrator-name-as-stored',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Gus &amp Gwen &lt Ops"' => 'Gus & Gwen <Ops' ) ),
		),
		array(
			'case'    => 'agreement-returned-administrator-name-as-stored',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Gus &amp Gwen &lt Ops"' => 'Gus & Gwen <Ops' ) ),
		),
		array(
			'case'    => 'sponsor-information-administrator-name-as-stored',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Gus &amp Gwen &lt Ops"' => 'Gus & Gwen <Ops' ) ),
		),
		array(
			'case'    => 'sponsor-agreement-returned-administrator-name-as-stored',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Gus &amp Gwen &lt Ops"' => 'Gus & Gwen <Ops' ) ),
		),
		array(
			'case'    => 'sponsor-agreement-revoked-administrator-name-as-stored',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Gus &amp Gwen &lt Ops"' => 'Gus & Gwen <Ops' ) ),
		),
		array(
			'case'    => 'invite-inviter-name-as-stored',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Mia &amp Max &lt Team"' => 'Mia & Max <Team' ) ),
		),
		array(
			'case'    => 'claim-problem-claimant-name-as-stored',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Kit &amp Kin &lt 3"' => 'Kit & Kin <3' ) ),
		),
		array(
			'case'    => 'information-administrator-with-quotes',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Omar O\'Admin Ops"' => '=?UTF-8?B?T21hciBPJ0FkbWluLCBPcHM=?=' ) ),
		),
		array(
			'case'    => 'agreement-returned-administrator-with-quotes',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Omar O\'Admin Ops"' => '=?UTF-8?B?T21hciBPJ0FkbWluLCBPcHM=?=' ) ),
		),
		array(
			'case'    => 'sponsor-information-administrator-with-quotes',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Omar O\'Admin Ops"' => '=?UTF-8?B?T21hciBPJ0FkbWluLCBPcHM=?=' ) ),
		),
		array(
			'case'    => 'sponsor-agreement-returned-with-quotes',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Omar O\'Admin Ops"' => '=?UTF-8?B?T21hciBPJ0FkbWluLCBPcHM=?=' ) ),
		),
		array(
			'case'    => 'information-administrator-with-a-double-quote',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Dee DJ Okafor Ops"' => 'Dee "DJ" Okafor; Ops' ) ),
		),
		array(
			'case'    => 'sponsor-agreement-returned-administrator-with-a-long-name',
			'item'    => 4,
			'replace' => array( 'headers' => array( '"Zoë Ångström-Núñez Head of Partnerships & Outreach EU"' => '=?UTF-8?B?Wm/DqyDDhW5nc3Ryw7ZtLU7DusOxZXosIEhlYWQgb2YgUGFydG5lcnNoaXBz?= =?UTF-8?B?ICYgT3V0cmVhY2ggIkVVIg==?=' ) ),
		),
		// Item 2: the five oddities 1.122.22 kept word for word. The low codes count one code as "1 code",
		// and write a count past a thousand and the mark beside it alike; with the Sponsor Dashboard's
		// page not published, the line naming its Offers card is left out rather than sent as a bare
		// query string; an Administrator whose account holds no name, or spaces, signs "by an
		// Administrator"; an invitation with nobody to name as its sender is an email of its own, which
		// says who is invited to what; an invitation promises a reply only while it carries an address
		// to reply to; and the two interest emails name the site.
		array(
			'case'    => 'low-stock-one-left',
			'item'    => 2,
			'replace' => array(
				'subject' => array( ': 1 codes left' => ': 1 code left' ),
				'body'    => array( 'has 1 codes left;' => 'has 1 code left;' ),
			),
		),
		array(
			'case'    => 'low-stock-no-members',
			'item'    => 2,
			'replace' => array(
				'subject' => array( ': 1 codes left' => ': 1 code left' ),
				'body'    => array( 'has 1 codes left;' => 'has 1 code left;' ),
			),
		),
		array(
			'case'    => 'low-stock-non-ascii',
			'item'    => 2,
			'replace' => array(
				'subject' => array( ': 1 codes left' => ': 1 code left' ),
				'body'    => array( 'has 1 codes left;' => 'has 1 code left;' ),
			),
		),
		array(
			'case'    => 'low-stock-administrator-page-only',
			'item'    => 2,
			'replace' => array(
				'subject' => array( ': 1 codes left' => ': 1 code left' ),
				'body'    => array( 'has 1 codes left;' => 'has 1 code left;', "\n\nAdd more codes, or pause the offer, from the Offers card: ?wpcpm_sponsor_view=recSEED0000000101#wpcpm-sponsor-offers" => '' ),
			),
		),
		array(
			'case'    => 'low-stock-unassigned-no-page',
			'item'    => 2,
			'replace' => array(
				'body' => array( "\n\nAdd more codes, or pause the offer, from the Offers card: ?wpcpm_sponsor_view=recSEED0000000101#wpcpm-sponsor-offers" => '' ),
			),
		),
		array(
			'case'    => 'low-stock-student-page-only',
			'item'    => 2,
			'replace' => array(
				'body' => array( "\n\nAdd more codes, or pause the offer, from the Offers card: ?wpcpm_sponsor_view=recSEED0000000101#wpcpm-sponsor-offers" => '' ),
			),
		),
		array(
			'case'    => 'agreement-returned-administrator-with-no-name',
			'item'    => 2,
			'replace' => array(
				'body' => array( 'by , with this note:' => 'by an Administrator, with this note:' ),
			),
		),
		array(
			'case'    => 'agreement-revoked-no-country',
			'item'    => 2,
			'replace' => array(
				'body' => array( 'by , with this note:' => 'by an Administrator, with this note:' ),
			),
		),
		array(
			'case'    => 'agreement-revoked-with-a-contact-administrator-with-no-name',
			'item'    => 2,
			'replace' => array(
				'body' => array( 'by , with this note:' => 'by an Administrator, with this note:' ),
			),
		),
		array(
			'case'    => 'invite-no-inviter',
			'item'    => 2,
			'replace' => array(
				'context' => array( 'institution-invite' => 'institution-invite-no-sender' ),
				'body'    => array( ' has invited you to see' => 'You have been invited to see', ' Replying to this message reaches the person who sent it.' => '' ),
			),
		),
		array(
			'case'    => 'invite-inviter-with-no-name',
			'item'    => 2,
			'replace' => array(
				'context' => array( 'institution-invite' => 'institution-invite-no-sender' ),
				'body'    => array( ' has invited you to see' => 'You have been invited to see' ),
			),
		),
		array(
			'case'    => 'invite-inviter-without-an-address',
			'item'    => 2,
			'replace' => array(
				'body' => array( ' Replying to this message reaches the person who sent it.' => '' ),
			),
		),
		array(
			'case'    => 'low-stock-at-the-highest-mark',
			'item'    => 2,
			'replace' => array(
				'body' => array( 'warns below 1000.' => 'warns below 1,000.' ),
			),
		),
		array(
			'case'    => 'low-stock-past-a-thousand',
			'item'    => 2,
			'replace' => array(
				'subject' => array( ': 4999 codes left' => ': 4,999 codes left' ),
				'body'    => array( 'has 4999 codes left; it warns below 5000.' => 'has 4,999 codes left; it warns below 5,000.' ),
			),
		),
		array(
			'case'    => 'agreement-returned-administrator-name-of-spaces',
			'item'    => 2,
			'replace' => array(
				'body' => array( 'by    , with this note:' => 'by an Administrator, with this note:' ),
			),
		),
		array(
			'case'    => 'sponsor-agreement-returned-administrator-name-of-spaces',
			'item'    => 2,
			'replace' => array(
				'body' => array( '    read your Collaboration Agreement' => 'An Administrator read your Collaboration Agreement' ),
			),
		),
		array(
			'case'    => 'interest-site-name',
			'item'    => 2,
			'replace' => array(
				'subject' => array( '[WordPress Credits] Acme' => '[Ada & Ben\'s 100% Credits] Acme' ),
			),
		),
		array(
			'case'    => 'mentor-interest-names-unassigned',
			'item'    => 2,
			'replace' => array(
				'subject' => array( '[WordPress Credits] Acme' => '[Ada & Ben\'s 100% Credits] Acme' ),
			),
		),
		// Item 6: the courtesy scan's notes in words an Administrator reads, each with the PDF's own name
		// for it in brackets. A name this scanner does not record, as the recording's cases of 1.122.21
		// give without the slash, is given as stored, so those cases are as recorded.
		array(
			'case'    => 'agreement-landed-one-flag-as-scanned',
			'item'    => 6,
			'replace' => array(
				'body' => array( 'noticed: /OpenAction.' => 'noticed: an action that runs when the file is opened (/OpenAction).' ),
			),
		),
		array(
			'case'    => 'agreement-landed-every-flag-as-scanned',
			'item'    => 6,
			'replace' => array(
				'body' => array( 'noticed: /JavaScript, /JS, /OpenAction, /AA, /EmbeddedFile.' => 'noticed: JavaScript code (/JavaScript), JavaScript code (/JS), an action that runs when the file is opened (/OpenAction), actions that run when a page or a form field is used (/AA), a file embedded inside it (/EmbeddedFile).' ),
			),
		),
		// End of the changes.
	);

	// Item 4, as the fix wave made it: `wp_mail()` hands the mailer what stands before the address as
	// the name, quote marks included, and the mailer quotes a name itself, so a name the plugin wrote
	// in quotes reached the recipient as `"Ida Admin"`. A name goes as it is now, and each Reply-To
	// the recordings hold as `Reply-To: "Name"` is `Reply-To: Name`, in every email of its case.
	$bare = array(
		'information'                                      => 'Ida Admin',
		'information-non-ascii'                            => 'Zoë Ångström-Núñez',
		'information-typed-in-a-browser'                   => 'Ida Admin',
		'information-administrator-name-with-a-line-break' => 'Lee Line Example',
		'invite'                                           => 'Ines Example',
		'invite-unnamed-institution'                       => 'Noor Example',
		'invite-non-ascii'                                 => 'Ines Example',
		'agreement-returned'                               => 'Ida Admin',
		'agreement-returned-sponsor-page-only'             => 'Ida Admin',
		'agreement-returned-non-ascii'                     => 'Zoë Ångström-Núñez',
		'agreement-returned-typed-in-a-browser'            => 'Ida Admin',
		'sponsor-information'                              => 'Ida Admin',
		'sponsor-information-non-ascii'                    => 'Zoë Ångström-Núñez',
		'sponsor-information-typed-in-a-browser'           => 'Ida Admin',
		'sponsor-agreement-returned'                       => 'Ida Admin',
		'sponsor-agreement-returned-non-ascii'             => 'Zoë Ångström-Núñez',
		'sponsor-agreement-returned-typed-in-a-browser'    => 'Ida Admin',
		'sponsor-agreement-revoked'                        => 'Ida Admin',
		'sponsor-agreement-revoked-mentor-page-only'       => 'Ida Admin',
		'sponsor-agreement-revoked-typed-in-a-browser'     => 'Ida Admin',
		'claim-problem'                                    => 'Kai O\'Claimant',
		'claim-problem-shared-code-unassigned'             => 'Kai O\'Claimant',
		'claim-problem-title-assigned-no-account'          => 'Kai O\'Claimant',
		'claim-problem-non-ascii'                          => 'Kai O\'Claimant',
	);

	foreach ( $bare as $case => $name ) {
		$rows[] = array(
			'case'    => $case,
			'item'    => 4,
			'replace' => array( 'headers' => array( 'Reply-To: "' . $name . '"' => 'Reply-To: ' . $name ) ),
		);
	}

	// The sponsors live on the site, each one a private post, and an interest's history is kept on
	// the Sponsors screen, which the email's last sentence names, in every interest case of both
	// recordings that sends one: a case with nothing ticked, named or typed sends none. These rows
	// carry no `item`, since the change is not one of the 1.122.23 changes the rows above list.
	foreach ( array_merge( cases(), added_cases() ) as $case => $about ) {
		$said = array_filter( array( $about['args']['choices'] ?? array(), $about['args']['events'] ?? '', $about['args']['note'] ?? '' ) );

		if ( 'interest' === $about['send'] && array() !== $said ) {
			$rows[] = array(
				'case'    => $case,
				'replace' => array( 'body' => array( 'The full history is in the Sponsorship interests column of the Sponsors table.' => 'The full history is on the Sponsors screen.' ) ),
			);
		}
	}

	return $rows;
}

/**
 * A case's emails as 1.122.23 sends them: as recorded, with the changes `changes()` lists for it
 * made in turn. A pair whose recorded text an email does not hold is noted in `$GLOBALS['stale']`,
 * which a check below holds empty: a change listed is a change made. So is a row whose `only` names
 * a place the recording holds no email at, and a pair for a field the email has not, which would
 * otherwise change nothing and pass.
 *
 * @param string     $case     The case.
 * @param array      $recorded Its emails, as recorded.
 * @param array|null $rows     The changes, `changes()` unless a check hands its own.
 * @return array
 */
function as_changed( $case, array $recorded, $rows = null ) {
	foreach ( null === $rows ? changes() : $rows as $row ) {
		if ( $case !== $row['case'] ) {
			continue;
		}

		foreach ( isset( $row['only'] ) ? array_diff( $row['only'], array_keys( $recorded ) ) : array() as $none ) {
			$GLOBALS['stale'][] = $case . ' #' . $none . ': no such email';
		}

		if ( isset( $row['emails'] ) ) {
			$recorded = $row['emails'];
			continue;
		}

		foreach ( $recorded as $n => $mail ) {
			// A row may name the emails of its case it changes, by their place in the recording.
			if ( isset( $row['only'] ) && ! in_array( $n, $row['only'], true ) ) {
				continue;
			}

			foreach ( $row['replace'] as $field => $pairs ) {
				if ( ! array_key_exists( $field, $mail ) ) {
					$GLOBALS['stale'][] = $case . ' #' . $n . ' ' . $field . ': no such field';
					continue;
				}

				foreach ( $pairs as $old => $new ) {
					$was            = $mail[ $field ];
					$mail[ $field ] = str_replace( (string) $old, $new, $mail[ $field ] );

					if ( $mail[ $field ] === $was ) {
						$GLOBALS['stale'][] = $case . ' #' . $n . ' ' . $field . ': ' . $old;
					}
				}
			}

			$recorded[ $n ] = $mail;
		}
	}

	return $recorded;
}

$fixture = __DIR__ . '/fixtures/institutions-sponsors-1-122-21.json';
$added   = __DIR__ . '/fixtures/institutions-sponsors-added-1-122-22.json';

if ( in_array( '--record-added', $argv, true ) ) {
	// Made once, from 1.122.22, before anything of 1.122.23 moved: made again later, it would hold
	// what the later code says, and prove nothing.
	if ( file_exists( $added ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What the cases 1.122.23 adds said in 1.122.22, recorded through the real senders and WPCPM_Mail by bin/test-institutions-sponsors-text.php --record-added before anything of 1.122.23 moved. Invented names, every address written "at example.test", and fake secrets and codes. Never edit by hand: 1.122.23 is held to these bytes but for the changes the suite lists.',
		'cases'    => array(),
	);

	foreach ( added_cases() as $name => $case ) {
		$recorded['cases'][ $name ] = emails_of( $case );
	}

	file_put_contents( $added, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n" );
	printf( "Recorded %d cases, %d emails, in %s\n", count( $recorded['cases'] ), array_sum( array_map( 'count', $recorded['cases'] ) ), basename( $added ) );

	exit( 0 );
}

if ( in_array( '--record', $argv, true ) ) {
	// Made once, from 1.122.21: a recording made again would be made from whatever the code says
	// then, and would prove nothing.
	if ( file_exists( $fixture ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What each Institutions and Sponsors email said in 1.122.21, recorded through the real senders and WPCPM_Mail by bin/test-institutions-sponsors-text.php --record before the emails moved into the template registry. Invented names, every address written "at example.test", and fake secrets and codes. Never edit by hand: the registry is held to these bytes.',
		'cases'    => array(),
	);

	foreach ( cases() as $name => $case ) {
		$recorded['cases'][ $name ] = emails_of( $case );
	}

	// A text JSON cannot hold stops the recording here, before a file is written.
	file_put_contents( $fixture, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n" );
	printf( "Recorded %d cases, %d emails, in %s\n", count( $recorded['cases'] ), array_sum( array_map( 'count', $recorded['cases'] ) ), basename( $fixture ) );

	exit( 0 );
}

$recorded = is_file( $fixture ) ? json_decode( (string) file_get_contents( $fixture ), true ) : null;
$more     = is_file( $added ) ? json_decode( (string) file_get_contents( $added ), true ) : null;
$cases    = cases();

ck( 'the recording is there, and holds every case', is_array( $recorded ) && isset( $recorded['cases'] ) ? array_keys( $recorded['cases'] ) : null, array_keys( $cases ) );
ck( 'the recording is the one made from 1.122.21', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, '7f90af7ea73ceef3d4853b3afbcc7b078c2ec0d63b3fb19305ab2107c65138ff' );
ck( 'the recording of the cases 1.122.23 adds is there, and holds every one of them', is_array( $more ) && isset( $more['cases'] ) ? array_keys( $more['cases'] ) : null, array_keys( added_cases() ) );
ck( 'and it is the one made from 1.122.22', is_file( $added ) ? hash_file( 'sha256', $added ) : null, '388e371ca71942bc69ba164aefaa6592829a58bcd4486e42b2a721f14e2dc258' );

// Every case of both recordings, as 1.122.23 sends it: as recorded, but for the changes listed.
$GLOBALS['stale'] = array();
$listed           = array_unique( array_column( changes(), 'case' ) );
$changed          = array();

foreach ( array( array( $cases, $recorded ), array( added_cases(), $more ) ) as $pair ) {
	foreach ( $pair[0] as $name => $case ) {
		$held = isset( $pair[1]['cases'][ $name ] ) ? $pair[1]['cases'][ $name ] : null;
		$want = null === $held ? null : as_changed( $name, $held );
		$got  = emails_of( $case );
		$as   = in_array( $name, $listed, true ) ? ', as 1.122.23 changes it' : '';
		// What the case's site was, for the changes of 1.123.27 that name it or the institution the
		// index holds a name for.
		$site = array(
			'name'         => $case['site'],
			'date_format'  => 'F j, Y',
			'institutions' => array_filter( array_map( function ( $row ) { return trim( (string) $row['name'] ); }, WPCPM_Institutions_Index::$rows ), 'strlen' ),
		);

		if ( null !== $held && $want !== $held ) {
			$changed[] = $name;
		}

		ck( $name . ': the same emails, to the same people, in the same order' . $as, array_column( $got, 'to' ), null === $want ? null : array_column( $want, 'to' ) );

		foreach ( (array) $want as $n => $mail ) {
			$now = isset( $got[ $n ] ) ? $got[ $n ] : array_fill_keys( array_keys( $mail ), null );

			$since = wpcpm_mail_since_1_123_27( array( 'body' => wpcpm_mail_since_1_123_26( wpcpm_mail_since_1_123_24( $mail['body'] ) ) ) + $mail, $site );

			ck( $name . ' #' . $n . ': logged under its own id' . $as, $now['context'], sent_as( $name, $n, $mail['context'] ) );
			ck( $name . ' #' . $n . ': the subject, byte for byte' . $as, $now['subject'], $since['subject'] );
			ck( $name . ' #' . $n . ': the body, byte for byte, the signature and footer included' . $as, $now['body'], $since['body'] );
			ck( $name . ' #' . $n . ': the headers, byte for byte, and no file attached' . $as, array( $now['headers'], $now['files'] ), array( $mail['headers'], 0 ) );
		}
	}
}

ck( 'every change listed is made, to a case of one of the two recordings, and changes what its recording holds', array( $GLOBALS['stale'], array_values( array_diff( $listed, $changed ) ) ), array( array(), array() ) );

// 1.123.24: the sponsor rejection's third paragraph, in the two cases that send one, and in no other
// email of either recording.
ck( 'the sponsor rejection asks again without guessing why, in the two emails that are one, as 1.123.24 sends it', $GLOBALS['wpcpm_since_1_123_24']['declined'], 2 );
// And the site's address on the line under the signature's first, in every recorded email but those
// whose body names the home page whole, which keep their footer as recorded.
// 373: the two recordings' 372 emails and the one a change row brings in their place; none of their
// bodies names the home page whole.
ck( 'every recorded email ends with the site\'s address in its footer, as 1.123.24 sends it, but one whose body names the home page whole', array( $GLOBALS['wpcpm_since_1_123_24']['site_line'], $GLOBALS['wpcpm_since_1_123_24']['site_kept'] ), array( 373, 0 ) );
// 1.123.26: the acknowledgement says its link is needed, in the seven recorded acknowledgements of an
// institution application and in no other email of either recording.
ck( 'the acknowledgement of an institution application says its link is needed, in the seven emails that are one, as 1.123.26 sends it', $GLOBALS['wpcpm_since_1_123_26']['confirm_link'], 7 );
// 1.123.27: the Institutions' emails, each change made in the emails it belongs to and in no other.
// The counts are the recordings' own, read off the two files by what each email said before: seven
// acknowledgements of an institution application and seven invitations (the sponsors' acknowledgements
// keep their words), 13 receipts, 16 returned agreements and 19 revocations that sent a school to the
// agreement panel, 8 Administrators' notices of an upload that gave an uploader with no name as the
// institution's record, 7 notices of a template that cannot be used, and 19 of an institution with no
// members left. No Semester reports email is in these recordings.
ck(
	'the Institutions\' emails say what 1.123.27 changes, in the emails that said it before and in no other',
	$GLOBALS['wpcpm_since_1_123_27'],
	array(
		'next'                 => 7,
		'form_at'              => 7,
		'invite_site'          => 7,
		'invite_expiry'        => 7,
		'received'             => 13,
		'returned'             => 16,
		'revoked'              => 19,
		'landed'               => 8,
		'template_institution' => 7,
		'template_fix'         => 7,
		'member_line'          => 19,
		'member_untouched'     => 19,
		'report_subject'       => 0,
	)
);

// The check above holds for every row: a row whose `only` names no email of its case, or whose pair
// names a field no email has, changes nothing, and is noted as a row that does.
$GLOBALS['stale'] = array();
as_changed(
	'a-case',
	array( array( 'subject' => 'Old', 'body' => 'Old', 'headers' => array() ) ),
	array(
		array( 'case' => 'a-case', 'item' => 0, 'only' => array( 0, 5 ), 'replace' => array( 'subject' => array( 'Old' => 'New' ) ) ),
		array( 'case' => 'a-case', 'item' => 0, 'replace' => array( 'subjet' => array( 'Old' => 'New' ) ) ),
	)
);
ck( 'and a row whose `only` names no email of its case, or whose field no email has, is noted as a change that changes nothing', $GLOBALS['stale'], array( 'a-case #5: no such email', 'a-case #0 subjet: no such field' ) );
$GLOBALS['stale'] = array();

// Every send site of the two areas, each with an email in the recording, under the id it was sent
// under in 1.122.21: 28 send sites, 26 ids.
$sites = array();
foreach ( $cases as $name => $case ) {
	foreach ( isset( $recorded['cases'][ $name ] ) ? $recorded['cases'][ $name ] : array() as $mail ) {
		$sites[ $case['send'] ][ $mail['context'] ] = true;
	}
}
$sites = array_map( 'array_keys', $sites );
ck(
	'the recording holds an email from every one of the 28 send sites of the two areas, under the id each sent under in 1.122.21',
	$sites,
	array(
		'inst-applied'     => array( 'institution-applied' ),
		'inst-application' => array( 'institution-application' ),
		'inst-information' => array( 'institution-information' ),
		'inst-declined'    => array( 'institution-declined' ),
		'inst-invite'      => array( 'institution-invite' ),
		'agr-received'     => array( 'agreement-received' ),
		'agr-landed'       => array( 'agreement-landed' ),
		'agr-accepted'     => array( 'agreement-accepted' ),
		'agr-reinstated'   => array( 'agreement-accepted' ),
		'agr-returned'     => array( 'agreement-returned' ),
		'agr-revoked'      => array( 'agreement-revoked' ),
		'agr-reminder'     => array( 'agreement-reminder' ),
		'agr-template'     => array( 'agreement-template' ),
		'member-last'      => array( 'member-last' ),
		'spon-applied'     => array( 'sponsor-applied' ),
		'spon-application' => array( 'sponsor-application' ),
		'spon-information' => array( 'sponsor-information' ),
		'spon-declined'    => array( 'sponsor-declined' ),
		'sagr-received'    => array( 'sponsor-agreement-received' ),
		'sagr-accepted'    => array( 'sponsor-agreement-accepted' ),
		'sagr-returned'    => array( 'sponsor-agreement-returned' ),
		'sagr-revoked'     => array( 'sponsor-agreement-revoked' ),
		'sagr-reinstated'  => array( 'sponsor-agreement-reinstated' ),
		'interest'         => array( 'sponsor-interest' ),
		'mentor-interest'  => array( 'sponsor-interest' ),
		'low-stock'        => array( 'offer-low-stock' ),
		'claim-problem'    => array( 'claim-problem' ),
		'post-returned'    => array( 'sponsor-post-returned' ),
	)
);

// What the move changes on purpose, and so not in the recording: a value worked out outside an email's
// builder until 1.122.22 was worded in the language of whoever pressed, or of the site for a job, and
// is worded in the recipient's now, as the sentence around it always was. A member whose account reads
// Polish is told who uploaded the agreement in Polish, "a member of your institution" when the
// uploader's account holds no name, while the other member is told in English.
$GLOBALS['user_locale'][50] = 'pl_PL';
$polish                     = emails_of( $cases['agreement-received-one-day-no-uploader'] );
unset( $GLOBALS['user_locale'][50] );
ck(
	'a member who reads Polish is sent the receipt in Polish, an uploader with no name worded in Polish too, and the other member in English',
	array_map(
		function ( $mail ) {
			return strtok( $mail['body'], "\r" );
		},
		$polish
	),
	array(
		'[pl_PL] We received your signed Collaboration Agreement on 2036-03-02, uploaded by [pl_PL] a member of your institution.',
		'We received your signed Collaboration Agreement on 2036-03-02, uploaded by a member of your institution.',
	)
);

// The Sponsors as the Institutions: an unedited template reaches a recipient who reads Polish in
// Polish, and so does each value worded for that recipient. A sponsor's member who reads Polish is
// sent the returned agreement in Polish, "An Administrator" in Polish too when the Administrator's
// account holds no name, while the other member is sent it in English; and an Administrator who reads
// Polish is told of an upload in Polish, "somebody at the company" for an uploader whose account
// holds no name and the review window, counted, in Polish too.
$GLOBALS['user_locale'][60] = 'pl_PL';
$GLOBALS['user_locale'][40] = 'pl_PL';
$returned                   = emails_of( $cases['sponsor-agreement-returned-no-name-no-page'] );
$received                   = emails_of( $cases['sponsor-agreement-received-one-day-unnamed'] );
unset( $GLOBALS['user_locale'][60], $GLOBALS['user_locale'][40] );
$lines = array();
foreach ( $returned as $mail ) {
	$lines[] = $mail['subject'];
	$lines[] = strtok( $mail['body'], "\r" );
}
foreach ( $received as $mail ) {
	$lines = array_merge( $lines, array_slice( explode( "\r\n\r\n", $mail['body'] ), 0, 2 ) );
}
ck(
	'a sponsor\'s member who reads Polish is sent the returned agreement in Polish, "An Administrator" worded in Polish too, and the other member in English; an Administrator who reads Polish is told of the upload in Polish, its uploader with no name and its review window worded in Polish too',
	$lines,
	array(
		'[pl_PL] [WordPress Credits] Your Collaboration Agreement came back with a note',
		'[pl_PL] [pl_PL] An Administrator read your Collaboration Agreement and sent it back, with this note:',
		'[WordPress Credits] Your Collaboration Agreement came back with a note',
		'An Administrator read your Collaboration Agreement and sent it back, with this note:',
		'[pl_PL] recSEED0000000102 uploaded a signed Collaboration Agreement on 2036-03-03 ([pl_PL] somebody at the company).',
		'[pl_PL] The program aims to answer within [pl_PL] 1 working day.',
	)
);

// 1.122.23's worded values are worded in each recipient's language, as the sentence around them is:
// the codes left, counted, to a sponsor's member who reads Polish, while the other member reads them in
// English; and "an Administrator", for one whose account holds no name, to an institution's member who
// reads Polish, while the other member reads it in English.
$GLOBALS['user_locale'][60] = 'pl_PL';
$GLOBALS['user_locale'][50] = 'pl_PL';
$low                        = emails_of( $cases['low-stock-one-left'] );
$back                       = emails_of( $cases['agreement-returned-administrator-with-no-name'] );
unset( $GLOBALS['user_locale'][60], $GLOBALS['user_locale'][50] );
// And the invitation with nobody to name, in a request made in Polish, as the colleague's has no
// account: its promise of a reply, a value worded where it is sent, is Polish too.
$GLOBALS['locale_stack'][] = 'pl_PL';
$twin                      = emails_of( added_cases()['invite-inviter-with-no-name'] );
array_pop( $GLOBALS['locale_stack'] );
ck(
	'the invitation with nobody to name promises a reply in the language it is built in',
	explode( "\r\n\r\n", $twin[0]['body'] ?? '' )[4] ?? null,
	'[pl_PL] If you were not expecting this, ignore this message: opening the link only shows you the invitation, nothing happens until you press Accept, and it stops working on its own. Replying to this message reaches [pl_PL] the person who sent it.'
);
ck(
	'the codes left and "an Administrator" are worded in each recipient\'s language',
	array( $low[0]['subject'] ?? null, $low[1]['subject'] ?? null, strtok( $back[0]['body'] ?? '', "\r" ), strtok( $back[1]['body'] ?? '', "\r" ) ),
	array(
		"[pl_PL] [Ada & Ben's 100% Credits] Hosting for students: [pl_PL] 1 code left",
		"[Ada & Ben's 100% Credits] Hosting for students: 1 code left",
		'[pl_PL] Your signed Collaboration Agreement was sent back on 2036-03-09 by [pl_PL] an Administrator, with this note:',
		'Your signed Collaboration Agreement was sent back on 2036-03-09 by an Administrator, with this note:',
	)
);

// And the scan's notes, in words, to an Administrator who reads Polish, while the others read English.
$GLOBALS['user_locale'][40] = 'pl_PL';
$scanned                    = emails_of( added_cases()['agreement-landed-one-flag-as-scanned'] );
unset( $GLOBALS['user_locale'][40] );
ck(
	'the scan\'s notes are worded in each Administrator\'s language',
	array_map(
		function ( $mail ) {
			return explode( "\r\n\r\n", $mail['body'] )[1];
		},
		array_slice( $scanned, 0, 2 )
	),
	array(
		'[pl_PL] The courtesy scan noticed: [pl_PL] [pl_PL] an action that runs when the file is opened (/OpenAction). It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.',
		'The courtesy scan noticed: an action that runs when the file is opened (/OpenAction). It is a courtesy and not evidence: download the file and open it in a viewer of your choosing.',
	)
);

// "An Administrator" in each recipient's language for the revocation too, as for the return: an
// institution's member who reads Polish is told who revoked the agreement in Polish, the other member
// in English (the fix wave).
$GLOBALS['user_locale'][50] = 'pl_PL';
$revoked                    = emails_of( added_cases()['agreement-revoked-with-a-contact-administrator-with-no-name'] );
unset( $GLOBALS['user_locale'][50] );
ck(
	'and "an Administrator" in the revocation is worded in each recipient\'s language too',
	array_map(
		function ( $mail ) {
			return strtok( $mail['body'], "\r" );
		},
		$revoked
	),
	array(
		'[pl_PL] Your Collaboration Agreement was revoked on 2036-03-09 by [pl_PL] an Administrator, with this note:',
		'Your Collaboration Agreement was revoked on 2036-03-09 by an Administrator, with this note:',
	)
);

// An account whose name is spaces names nobody, as one that holds no name: a revocation by such an
// Administrator says "by an Administrator", and an invitation by such an inviter is the one with no
// sender named, its promise of a reply kept, since the account holds an address (outside the
// recordings, which are never made again).
$spaces = array(
	'revoked' => emails_of( array_merge( $cases['agreement-revoked-no-country'], array( 'actor' => 49 ) ) ),
	'invited' => emails_of( array_merge( $cases['invite'], array( 'args' => array( 'record' => 'recSEED0000000016', 'actor' => 49 ) ) ) ),
);
ck(
	'a revocation by an Administrator whose name is spaces says "by an Administrator", and an invitation by an inviter whose name is spaces is the one with no sender named',
	array(
		strtok( $spaces['revoked'][0]['body'] ?? '', "\r" ),
		$spaces['invited'][0]['context'] ?? null,
		strtok( $spaces['invited'][0]['body'] ?? '', "\r" ),
		false !== strpos( $spaces['invited'][0]['body'] ?? '', 'Replying to this message reaches the person who sent it.' ),
		$spaces['invited'][0]['headers'] ?? null,
	),
	array(
		'Your Collaboration Agreement was revoked on 2036-03-09 by an Administrator, with this note:',
		'institution-invite-no-sender',
		'You have been invited to see Example University & "Partners" 100%\'s students in the WordPress Credits Program on the WordPress Credits.',
		true,
		array( 'Reply-To: blank at example.test' ),
	)
);

// Every value is read back once, as it is stored, and no deeper (the fix wave): a note, a question or
// an interest an Administrator, a sponsor or a member typed as "&amp;lt;3", which the cleaner keeps
// as typed, a post's and an offer's title stored with it, and a site's name WordPress stores as
// "R&amp;amp;D Credits", arrive as "&lt;3" and "R&amp;D Credits", as 1.122.22 sent a typed text and
// a site's name. A builder that read a value back before handing it over had it read back twice, to
// "<3" and "R&D Credits".
$typed = 'Typed &amp;lt;3 once';
$once  = array();
foreach ( array(
	'agreement-returned'               => array( 'typed' => $typed ),
	'agreement-revoked-with-a-contact' => array( 'typed' => $typed ),
	'information'                      => array( 'typed' => $typed ),
	'sponsor-information'              => array( 'typed' => $typed ),
	'sponsor-agreement-returned'       => array( 'typed' => $typed ),
	'sponsor-agreement-revoked'        => array( 'typed' => $typed ),
	'interest-everything'              => array( 'note' => $typed ),
	'post-returned'                    => array( 'title' => $typed, 'note' => $typed ),
	'claim-problem'                    => array( 'title' => $typed ),
	'low-stock-one-left'               => array( 'title' => $typed ),
	'member-last-removed'              => array(),
) as $name => $args ) {
	$mails = emails_of( array_merge( $cases[ $name ], array( 'site' => 'R&amp;amp;D Credits', 'args' => array_merge( $cases[ $name ]['args'], $args ) ) ) );
	$text  = isset( $mails[0] ) ? $mails[0]['subject'] . "\n" . $mails[0]['body'] : '';

	// How often each value was read back: once, twice, or not in the email at all.
	$times = static function ( $read_once, $read_twice ) use ( $text ) {
		return false !== strpos( $text, $read_twice ) ? 'twice' : ( false !== strpos( $text, $read_once ) ? 'once' : 'not in it' );
	};

	$once[ $name ] = array( $times( 'Typed &lt;3 once', 'Typed <3 once' ), $times( 'R&amp;D Credits', 'R&D Credits' ) );
}
ck(
	'every value is read back once, as stored: a typed "&amp;lt;3" arrives as "&lt;3", and a site\'s name stored as "R&amp;amp;D" as "R&amp;D"',
	$once,
	array_merge(
		array_fill_keys( array_keys( $once ), array( 'once', 'once' ) ),
		array(
			'post-returned'       => array( 'once', 'not in it' ),
			'member-last-removed' => array( 'not in it', 'once' ),
		)
	)
);

// A count and a mark past a thousand in a language whose thousands separator is a space: core writes
// that separator as `&nbsp;`, which a plain-text email would print as six characters, so it arrives as
// the no-break space it stands for, in the subject and in the body (the fix wave).
$GLOBALS['thousands_sep'] = '&nbsp;';
$spaced                   = emails_of( added_cases()['low-stock-past-a-thousand'] );
unset( $GLOBALS['thousands_sep'] );
ck(
	'a count and a mark past a thousand, in a language that separates thousands with a space, carry a no-break space, never "&nbsp;"',
	array( $spaced[0]['subject'] ?? null, 1 === preg_match( "/has 4\u{00A0}999 codes left; it warns below 5\u{00A0}000\\./u", $spaced[0]['body'] ?? '' ), false !== strpos( ( $spaced[0]['subject'] ?? '' ) . ( $spaced[0]['body'] ?? '' ), '&nbsp;' ) ),
	array( "[WordPress Credits] Hosting for students: 4\u{00A0}999 codes left", true, false )
);

// The examples a preview and Send me a test fill an email with are worked out in the language of the
// Administrator looking, as the email's own values are: the codes left, counted, and what the scan
// noticed, in words (the fix wave; they were English whatever the language).
$GLOBALS['locale_stack'][] = 'pl_PL';
$examples                  = array( WPCPM_Mail_Templates::example_values( 'offer-low-stock' )['codes_left'], WPCPM_Mail_Templates::example_values( 'agreement-landed-flagged' )['scan_flags'] );
array_pop( $GLOBALS['locale_stack'] );
ck(
	'the codes left and the scan\'s notes are given as examples in the language of the Administrator looking',
	$examples,
	array( '[pl_PL] 3 codes', '[pl_PL] [pl_PL] JavaScript code (/JavaScript), [pl_PL] [pl_PL] an action that runs when the file is opened (/OpenAction)' )
);
ck(
	'and in English, the examples are the words the email gives',
	array( WPCPM_Mail_Templates::example_values( 'offer-low-stock' )['codes_left'], WPCPM_Mail_Templates::example_values( 'agreement-landed-flagged' )['scan_flags'] ),
	array( '3 codes', WPCPM_Pdf_Check::in_words( array( '/JavaScript', '/OpenAction' ) ) )
);

// A rejection tells the applicant nothing but that it was decided: the reason the Administrator typed
// reaches neither area's email, and neither template has a place for it.
$reasons = array();
foreach ( array( 'declined', 'declined-site-name', 'sponsor-declined', 'sponsor-declined-site-name' ) as $name ) {
	foreach ( emails_of( $cases[ $name ] ) as $mail ) {
		$reasons[] = false !== strpos( $mail['subject'] . $mail['body'], 'Could you <3' ) || false !== strpos( $mail['subject'] . $mail['body'], 'second line' );
	}
}
ck(
	'and a rejection, of an institution or of a sponsor, carries none of the reason the Administrator typed, and its template has no place for one',
	array( $reasons, array_keys( WPCPM_Mail_Templates::get( 'institution-declined' )['placeholders'] ), array_keys( WPCPM_Mail_Templates::get( 'sponsor-declined' )['placeholders'] ) ),
	array( array( false, false, false, false ), array( 'site_name' ), array( 'site_name' ) )
);

$text = is_file( $fixture ) ? (string) file_get_contents( $fixture ) : '';
preg_match_all( '/[?&]t=([A-Za-z0-9-]+)/', $text, $tokens );
preg_match_all( '/\(ending ([^)]*)\)/', $text, $endings );
ck(
	'and the recording holds no address, no secret but a fake one, no code but a fake one\'s end, and no record ID but the seeds\'',
	is_file( $fixture ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', $text ), array_values( array_unique( array_map( function ( $t ) { return substr( $t, 0, 19 ); }, $tokens[1] ) ) ), array_values( array_unique( $endings[1] ) ), preg_match( '/rec(?!SEED0\d{9})[A-Za-z0-9]{14}/', $text ) ) : null,
	array( 0, array( 'EXAMPLE-NOT-A-REAL-' ), array( '7Q2X', 'AB12' ), 0 )
);
$text = is_file( $added ) ? (string) file_get_contents( $added ) : '';
preg_match_all( '/[?&]t=([A-Za-z0-9-]+)/', $text, $tokens );
ck(
	'and so does the recording of the cases 1.122.23 adds',
	is_file( $added ) ? array( preg_match( '/[A-Za-z0-9._%+-]*@[A-Za-z0-9-]+\.[A-Za-z]{2,}/', $text ), array_values( array_unique( array_map( function ( $t ) { return substr( $t, 0, 19 ); }, $tokens[1] ) ) ), preg_match( '/rec(?!SEED0\d{9})[A-Za-z0-9]{14}/', $text ) ) : null,
	array( 0, array( 'EXAMPLE-NOT-A-REAL-' ), 0 )
);

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
