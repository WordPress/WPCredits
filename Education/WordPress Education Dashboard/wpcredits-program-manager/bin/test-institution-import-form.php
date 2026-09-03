<?php
/**
 * The import form, and the two handlers behind it.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The institution is never read from the request.** It comes from the reader's own stamp,
 *   or from a manager's switcher, through `resolve_institution()`. A post carrying another
 *   school's record ID changes nothing about whose import it is.
 * - **The order is the module's rule.** The cheap decision refuses before anything else; the
 *   nonce is checked before a single request reaches Airtable; the ceilings are claimed before
 *   a byte is parsed. A cross-site post therefore costs this site nothing at all.
 * - **Cancel reads the institution off the batch.** A member of one school posting another
 *   school's batch ID is decided against the school that batch belongs to, and refused.
 * - **The blocked sentence is one sentence.** Whatever the real reason a row hit something
 *   outside this school, the school reads the same words, and the reason never reaches the
 *   page. Otherwise a preview is a lookup service for anybody who can paste addresses.
 * - The file is read and never moved: `wp_handle_upload()` would put a list of names and
 *   addresses into the uploads directory, which this site serves over the web.
 * - The single-student route becomes the same CSV the file route produces, so every cleaning
 *   rule and every duplicate check is written once and covers both.
 * - A start date more than a year away is a typo, and a whole cohort filed under 2036 is a
 *   mess somebody unpicks a record at a time.
 *
 * Run from the plugin root:  php bin/test-institution-import-form.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['opts']     = array();
$GLOBALS['posts']    = array();
$GLOBALS['pmeta']    = array();
$GLOBALS['flash']    = array();
$GLOBALS['referer']  = array();
$GLOBALS['redirect'] = '';
$GLOBALS['uid']      = 7;
$GLOBALS['manage']   = false;
$GLOBALS['resolved'] = '';
$GLOBALS['allowed']  = true;
$GLOBALS['staged']   = 0;
$GLOBALS['calls']    = array();
$GLOBALS['next_id']  = 900;
$GLOBALS['checked']  = null;

function __( $t, $d = null ) { return $t; }
function esc_html__( $t, $d = null ) { return $t; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $u ) { return $u; }
function esc_url_raw( $u ) { return $u; }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function number_format_i18n( $n ) { return (string) $n; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v = null, $url = '' ) {
	if ( is_array( $k ) ) { $url = $v; $pairs = $k; } else { $pairs = array( $k => $v ); }
	$join = false === strpos( (string) $url, '?' ) ? '?' : '&';
	$bits = array();
	foreach ( $pairs as $key => $value ) { $bits[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $value ); }
	return $url . $join . implode( '&', $bits );
}
function wp_nonce_field( $action ) { $GLOBALS['calls'][] = array( 'nonce_field', $action ); echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '" />'; }
function check_admin_referer( $action = -1, $q = '_wpnonce' ) { $GLOBALS['referer'][] = $action; return true; }
function wp_safe_redirect( $url ) { $GLOBALS['redirect'] = $url; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_get_current_user() { return (object) array( 'ID' => (int) $GLOBALS['uid'] ); }
function current_user_can( $cap ) { return (bool) $GLOBALS['manage']; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function add_action() {}
function apply_filters( $t, $v ) { return $v; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function wp_hash( $v ) { return md5( (string) $v ); }
$GLOBALS['transients'] = array();
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transients'] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
function sanitize_email( $e ) { return filter_var( trim( (string) $e ), FILTER_SANITIZE_EMAIL ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u ); }
function absint( $v ) { return abs( (int) $v ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function add_filter() {}
function do_action() {}

/** The handlers end in `exit`; here they end in an exception the runner can catch. */
class Left extends Exception {}
function wpcpm_test_exit() { throw new Left( $GLOBALS['redirect'] ); }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-program.php';

/** Only what the form calls, so the suite stays about the form. */
class WPCPM_Mentors_Sync {
	public static function is_record_id( $id ) { return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $id ); }
	public static function wporg_username( $raw ) {
		$v = strtolower( trim( ltrim( trim( (string) $raw ), '@' ) ) );
		$v = preg_replace( '#^https?://#', '', $v );
		$v = trim( (string) preg_replace( '#^profiles\.wordpress\.org/#', '', $v ), '/' );
		return preg_match( '/^[a-z0-9][a-z0-9._\-]{0,59}$/', $v ) ? $v : '';
	}
	public static function fields() {
		return array(
			'student_record_name' => 'Full Name', 'student_email' => 'Email', 'student_status' => 'Status',
			'student_institution' => 'Educational Institutions', 'student_start' => 'Start Date', 'student_profile' => 'WP Profile',
			'report_name' => 'Name', 'report_email' => 'Email', 'report_status' => 'Status',
			'report_instituton' => 'Educational institution', 'report_profile' => 'WordPress Profile',
		);
	}
}
class WPCPM_Airtable {
	public function __construct( $s = null ) {}
	public function formula_in( $f, array $v, $l = false ) { return empty( $v ) ? '' : 'in'; }
	public function formula_contains( $f, array $v, $l = true ) { return empty( $v ) ? '' : 'has'; }
	public function fetch_all( $t, array $a = array() ) { return array(); }
	public static function flatten( $v, $g = ', ' ) { return is_array( $v ) ? implode( $g, $v ) : (string) $v; }
	public static function link_ids( $v ) { return array_values( array_filter( (array) $v, 'strlen' ) ); }
}
class WPCPM_Roster_Index { public static function rows( $r ) { return array(); } }
class WPCPM_Roles { const CAP_MANAGE = 'wpcpm_manage'; }
class WPCPM_Settings {
	public static function get() { return array( 'students_table' => 'tblS', 'reports_table' => 'tblR' ); }
	public static function get_value( $k ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : false; }
}
class WPCPM_Flash {
	public static function set( $c, $v, $u = 0 ) { $GLOBALS['flash'][ $c ] = $v; }
	public static function take( $c, $u = 0 ) { $v = isset( $GLOBALS['flash'][ $c ] ) ? $GLOBALS['flash'][ $c ] : null; unset( $GLOBALS['flash'][ $c ] ); return $v; }
}
class WPCPM_Request {
	public static function text( $n, $f = '' ) { return isset( $_GET[ $n ] ) ? (string) $_GET[ $n ] : $f; }
	public static function posted_text( $n, $f = '' ) { return isset( $_POST[ $n ] ) ? (string) $_POST[ $n ] : $f; }
}
class WPCPM_Institution_Policy {
	const ACT_ADD_STUDENT = 'add_student';
	public static function subject_institution( $id ) { $GLOBALS['calls'][] = array( 'subject', $id ); return array( 'institution' => $id ); }
	public static function decide( $action, $subject ) {
		$GLOBALS['calls'][] = array( 'decide', $action, isset( $subject['institution'] ) ? $subject['institution'] : '' );
		return array( 'allowed' => (bool) $GLOBALS['allowed'], 'institution' => isset( $subject['institution'] ) ? $subject['institution'] : '' );
	}
}
class WPCPM_Institution_Roster {
	public static function resolve_institution( $viewer, $can_manage ) { $GLOBALS['calls'][] = array( 'resolve' ); return (string) $GLOBALS['resolved']; }
}
class WPCPM_Institutions_Dashboard { public static function page_url() { return 'https://example.test/institution-dashboard/'; } }
class WPCPM_Institution_Student_Form {
	public static function choices( $n ) { return 'field_of_study' === $n ? array( 'Technology & Engineering' ) : array(); }
}

function wp_insert_post( $args, $e = false ) {
	$id = ++$GLOBALS['next_id'];
	$GLOBALS['posts'][ $id ] = (object) array_merge( array( 'ID' => $id, 'post_type' => '', 'post_author' => 0, 'post_status' => '', 'post_title' => '' ), $args );
	return $id;
}
function wp_delete_post( $id, $f = false ) { $g = isset( $GLOBALS['posts'][ (int) $id ] ); unset( $GLOBALS['posts'][ (int) $id ] ); return $g; }
function get_post( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
function get_post_meta( $id, $k, $s = false ) { return isset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $k ] : ''; }
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( $post->post_type !== $args['post_type'] ) { continue; }
		$ok = true;
		foreach ( isset( $args['meta_query'] ) ? $args['meta_query'] : array() as $c ) {
			if ( get_post_meta( $id, $c['key'], true ) !== $c['value'] ) { $ok = false; break; }
		}
		if ( $ok ) { $out[] = $id; }
	}
	return $out;
}
function register_post_type( $t, $a ) { return true; }
class WP_Error { public $c; public $m; public function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } public function get_error_message() { return $this->m; } }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_user_by( $by, $v ) { return false; }

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import.php';

// The handlers call `exit`. Loaded through a rewrite so the runner can catch the end instead.
$form_source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import-form.php' );
$form_source = preg_replace( '/^\s*exit;\s*$/m', "\t\twpcpm_test_exit();", $form_source );
$form_source = preg_replace( '/^<\?php/', '', $form_source, 1 );
$form_source = str_replace( "defined( 'ABSPATH' ) || exit;", '', $form_source );
eval( $form_source );

$fails = 0;
$total = 0;

function ck( $label, $actual, $expected ) {
	global $fails, $total;
	++$total;
	if ( $actual === $expected ) { printf( "ok   %s\n", $label ); return; }
	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $actual, true ), var_export( $expected, true ) );
}

$HERE  = 'recHERE0000000001';
$THERE = 'recTHERE00000001';

/** Post the form and report where it left the reader, and what it said. */
function post_check( array $fields ) {
	$GLOBALS['referer']  = array();
	$GLOBALS['calls']    = array();
	$GLOBALS['redirect'] = '';
	$GLOBALS['flash']    = array();
	$_POST               = $fields;
	$_GET                = array();
	$_FILES              = isset( $fields['__files'] ) ? $fields['__files'] : array();
	unset( $_POST['__files'] );

	try {
		WPCPM_Institution_Import_Form::handle_check();
	} catch ( Left $e ) {
		// The handler ended where it meant to.
	}

	$flash = isset( $GLOBALS['flash']['institution_import'] ) ? $GLOBALS['flash']['institution_import'] : array();

	return isset( $flash['status'] ) ? $flash['status'] : '';
}

/** The batch-wide answers a valid post carries, so a case can change one of them. */
function batch_fields( array $over = array() ) {
	return array_merge(
		array(
			'program' => 'In Sensei',
			'start'   => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ),
			'end'     => '',
			'notified' => '1',
			'paste'   => "Name,Email\nAnna Kowalska,anna@uek.krakow.pl\n",
		),
		$over
	);
}

$GLOBALS['settings'] = array( 'import_enabled' => true );
$GLOBALS['resolved'] = $HERE;

echo "=== The institution is the reader's, never the form's ===\n";

$status = post_check( batch_fields( array( 'institution' => $THERE, 'record' => $THERE ) ) );

ck( 'a valid list is checked and staged', $status, 'checked' );

$decided = array();
foreach ( $GLOBALS['calls'] as $call ) { if ( 'decide' === $call[0] ) { $decided[] = $call[2]; } }

// The form can say whatever it likes; the answer comes from `resolve_institution()`.
ck( 'the decision was made about the reader\'s own institution', $decided, array( $HERE ) );
ck( 'and never about the one the form named', in_array( $THERE, $decided, true ), false );

$staged_id = WPCPM_Institution_Import::staged_for( $HERE );
ck( 'the batch belongs to the reader\'s institution', WPCPM_Institution_Import::batch( $staged_id )['institution'], $HERE );
ck( 'and the reader is its author', WPCPM_Institution_Import::batch( $staged_id )['author'], 7 );

echo "\n=== The order, which is what makes a forged post free ===\n";

// The nonce is keyed to the institution, so a token for one school is not a token for another.
ck( 'the nonce names the institution', $GLOBALS['referer'], array( WPCPM_Institution_Import_Form::ACTION_CHECK . '_' . $HERE ) );

$order = array();
foreach ( $GLOBALS['calls'] as $call ) { $order[] = $call[0]; }

// Resolve, then decide, then the nonce. `decide()` costs nothing; the network comes after.
ck( 'the institution is resolved first', $order[0], 'resolve' );
ck( 'and decided before the nonce is even read', array_search( 'decide', $order, true ) < count( $order ), true );

$GLOBALS['allowed'] = false;
$GLOBALS['referer'] = array();
$status             = post_check( batch_fields() );
ck( 'a reader the policy refuses is refused', $status, 'refused' );
// The cheap decision comes first, so a refused reader never causes a nonce check or a request.
ck( 'and no nonce was checked for them', $GLOBALS['referer'], array() );
$GLOBALS['allowed'] = true;

$GLOBALS['resolved'] = '';
ck( 'a reader with no institution at all is refused', post_check( batch_fields() ), 'refused' );
$GLOBALS['resolved'] = $HERE;

$GLOBALS['settings'] = array( 'import_enabled' => false );
ck( 'and nothing happens at all while the feature is off', post_check( batch_fields() ), 'off' );
$GLOBALS['settings'] = array( 'import_enabled' => true );

echo "\n=== One list at a time ===\n";

// A school cannot stage six lists and confirm them in an order nobody intended.
ck( 'a second list is refused while one is waiting', post_check( batch_fields() ), 'already-staged' );

echo "\n=== The batch-wide answers ===\n";

/**
 * A world with no staged batch and no spent ceiling.
 *
 * Each of these cases is one check, and the hourly ceiling admits five: without the reset the
 * sixth case would be refused for being the sixth rather than for the thing it is testing, and
 * the suite would go green on the wrong answer.
 */
function fresh_world() {
	$GLOBALS['posts'] = array();
	$GLOBALS['pmeta'] = array();
	$GLOBALS['opts']  = array();
	$GLOBALS['flash'] = array();
}

$cases = array(
	array( 'a program this site does not offer is refused', array( 'program' => 'Astronaut Track' ), 'bad-program' ),
	array( 'a start date that is not a date is refused', array( 'start' => 'next term' ), 'bad-start' ),
	// Pattern and checkdate() both, so this is refused rather than becoming the first of March.
	array( 'the thirty-first of February is refused', array( 'start' => '2026-02-31' ), 'bad-start' ),
	// A term starting next autumn is ordinary; one starting in 2036 is a finger on the wrong
	// digit, and a whole cohort filed under it is a mess somebody unpicks a record at a time.
	array( 'a start date ten years out is refused as a typo', array( 'start' => '2036-09-01' ), 'start-far' ),
	array( 'an end date before the start is refused', array( 'end' => '2020-01-01' ), 'bad-end' ),
	array( 'and one more than a year after it', array( 'end' => gmdate( 'Y-m-d', time() + 800 * DAY_IN_SECONDS ) ), 'bad-end' ),
	// The school's own statement, and the form does not proceed without it.
	array( 'the list is refused until the school confirms it has notified them', array( 'notified' => '' ), 'not-notified' ),
	array( 'a post with nothing in it at all is refused', array( 'paste' => '' ), 'nothing-sent' ),
);

foreach ( $cases as $case ) {
	fresh_world();
	ck( $case[0], post_check( batch_fields( $case[1] ) ), $case[2] );
}

echo "\n=== One shape reaches the checking, not two ===\n";

fresh_world();

// The five boxes become the file they describe, so every cleaning rule and every duplicate
// check is written once and covers both routes.
$status = post_check(
	batch_fields(
		array(
			'paste'          => '',
			'name'           => 'Bartek Zielinski',
			'email'          => 'bartek@uek.krakow.pl',
			'profile'        => '@bartekz',
			// Posted as a forged field would be: the form no longer offers it.
			'field_of_study' => 'Technology & Engineering',
			'tutor'          => 'Dr Nowak',
		)
	)
);

ck( 'one student sent through the boxes is checked the same way', $status, 'checked' );

$rows = WPCPM_Institution_Import::batch( WPCPM_Institution_Import::staged_for( $HERE ) )['rows'];

ck( 'and arrives as one cleaned row', count( $rows ), 1 );
ck( 'and the address lowercased for comparing', $rows[0]['email_key'], 'bartek@uek.krakow.pl' );
ck( 'and one posted anyway is not carried', $rows[0]['profile'], '' );

// Drawn with nothing staged, or the section shows the preview and every assertion about the
// form's controls would be asking the wrong markup and passing for the wrong reason.
fresh_world();
$form = draw_section( $HERE );

ck( 'the form is the form and not a preview', false !== strpos( $form, 'name="paste"' ), true );
// Getting a WordPress.org profile is the student's own first step of onboarding, after they
// are enrolled, so at the moment a school fills this in nobody has one to give.
ck( 'the form asks for no profile', false !== strpos( $form, 'name="profile"' ), false );

// A single-select in Airtable, and create_records() sends no typecast, so a value spelled any
// other way is a 422 for the whole record: a text box here is one a school can only get wrong.
ck( 'the field of study is a picker', false !== strpos( $form, '<select id="wpcpm-import-field_of_study"' ), true );
ck( 'holding the base\'s own nine', substr_count( $form, '<option value="Technology &amp; Engineering"' ), 1 );
// Neither field is required, and a picker that opens on the first real answer files half a
// cohort under it.
ck( 'opening on a blank answer', false !== strpos( $form, '<option value="">Not recorded</option>' ), true );

echo "\n=== Cancel decides against the batch's institution ===\n";

// Its own batch, staged here rather than borrowed from a block above: the form assertions
// clear the world between them, and a cancel test reading whatever survived that is a test
// that passes or fails on the order of the file.
fresh_world();
post_check( batch_fields() );

$batch_id = WPCPM_Institution_Import::staged_for( $HERE );

ck( 'there is a batch to cancel', $batch_id > 0, true );

/** Press Cancel as somebody, and report what the screen said. */
function post_cancel( $batch_id ) {
	$GLOBALS['referer']  = array();
	$GLOBALS['calls']    = array();
	$GLOBALS['flash']    = array();
	$_POST               = array( 'batch' => (string) (int) $batch_id );

	try {
		WPCPM_Institution_Import_Form::handle_cancel();
	} catch ( Left $e ) {
		// Ended where it meant to.
	}

	$flash = isset( $GLOBALS['flash']['institution_import'] ) ? $GLOBALS['flash']['institution_import'] : array();

	return isset( $flash['status'] ) ? $flash['status'] : '';
}

// The reader may be anybody; the batch says whose it is, and that is what is decided about.
$GLOBALS['resolved'] = $THERE;
$GLOBALS['allowed']  = false;
ck( 'somebody the policy refuses cannot cancel it', post_cancel( $batch_id ), 'refused' );
$decided = array();
foreach ( $GLOBALS['calls'] as $call ) { if ( 'decide' === $call[0] ) { $decided[] = $call[2]; } }
ck( 'and the decision was about the batch\'s institution', $decided, array( $HERE ) );
ck( 'the batch is still there', WPCPM_Institution_Import::staged_for( $HERE ) > 0, true );

$GLOBALS['allowed']  = true;
$GLOBALS['resolved'] = $HERE;
ck( 'its own institution can throw it away', post_cancel( $batch_id ), 'cancelled' );
ck( 'and then there is none waiting', WPCPM_Institution_Import::staged_for( $HERE ), 0 );
ck( 'cancelling a batch that is gone says so', post_cancel( $batch_id ), 'no-batch' );

echo "\n=== What the preview says, and what it does not ===\n";

$said = WPCPM_Institution_Import_Form::messages();

// The one sentence every outside hit gets. If this ever branches, a preview becomes a lookup
// service: paste three hundred addresses, read three hundred answers about who is in the
// program. The reason lives on the batch, for a program manager.
$source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-import-form.php' );
ck( 'the blocked sentence is written once', substr_count( $source, 'This student cannot be imported from here' ), 1 );
ck( 'and the manager-only reason never reaches the screen', false !== strpos( $code, 'manager_reason' ), false );

// A school that pasted thirty students and got twenty-eight has to be able to fix the two.
foreach ( array( 'parse-not_utf8', 'parse-no_columns', 'parse-too_many_rows', 'parse-batch_mismatch', 'too_often', 'rows_today' ) as $key ) {
	ck( sprintf( '"%s" has a sentence somebody can act on', $key ), isset( $said[ $key ] ) && strlen( $said[ $key ] ) > 20, true );
}

echo "\n=== The section folds, and opens when it is asking for something ===\n";

/** Draw the section as the reader would meet it, and hand back the markup. */
function draw_section( $record ) {
	ob_start();
	WPCPM_Institution_Import_Form::render( $record, array( 'can_manage' => false ) );
	return (string) ob_get_clean();
}

fresh_world();
$closed = draw_section( $HERE );

// The same disclosure the roster's own buckets use, so it opens with the control the reader
// has already met four times above it rather than with a fifth kind of thing.
ck( 'the section is a disclosure', false !== strpos( $closed, '<details class="wpcpm-group wpcpm-import__disclosure"' ), true );
// Enrolling is occasional and this form is the longest thing on the page; left open it pushes
// the people and the agreement off the screen for every visit that came to read the roster.
ck( 'and it is folded when there is nothing to answer', false !== strpos( $closed, 'wpcpm-import__disclosure" open' ), false );
ck( 'its summary is the shared one, not a fifth kind of heading', false !== strpos( $closed, 'class="wpcpm-group__summary"' ), true );

// A list waiting to be looked at is the page asking the reader for something.
post_check( batch_fields() );
$with_batch = draw_section( $HERE );
ck( 'a waiting list opens it', false !== strpos( $with_batch, 'wpcpm-import__disclosure" open' ), true );

// So is a message from the last attempt, which folded away unread before this.
fresh_world();
post_check( batch_fields( array( 'notified' => '' ) ) );
$with_message = draw_section( $HERE );
ck( 'and so does something left to say', false !== strpos( $with_message, 'wpcpm-import__disclosure" open' ), true );
// Read twice in one request: once to decide whether to open, once to print. `take()` is
// memoized for exactly that, and without it the message would be shown inside a folded section.
ck( 'the message still prints after being read to make that decision', false !== strpos( $with_message, 'Confirm that these students have been notified' ), true );

fresh_world();
$GLOBALS['settings'] = array( 'import_enabled' => false );
ck( 'and none of it is drawn while the setting is off', draw_section( $HERE ), '' );
$GLOBALS['settings'] = array( 'import_enabled' => true );

// Every class the form prints has a rule of its own in the page's stylesheet. `.wpcpm-field`
// is defined in the application form's, which is not loaded here, so the fields came out as
// the browser's own on the day this shipped.
$css = file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/institution.css' );

foreach ( array( 'wpcpm-import__body', 'wpcpm-import__batch', 'wpcpm-import__subtitle', 'wpcpm-import__row', 'wpcpm-import__verdict', 'wpcpm-import__message', 'wpcpm-field--check', 'wpcpm-field__hint' ) as $class ) {
	ck( sprintf( '.%s is styled on this page', $class ), false !== strpos( $css, '.' . $class ), true );
}

// It carries a rule above it and the page's rhythm, like the two sections it sits between.
ck( 'the section joins the two that already carry a rule', false !== strpos( $css, ".wpcpm-roster,\n.wpcpm-import,\n.wpcpm-people {" ), true );

echo "\n=== The file is read, never stored ===\n";

/**
 * The module with its comments removed.
 *
 * Scanned instead of the raw text, because the comments explain why the module does not call
 * these things and name them to do it. Reading the raw source made "nothing here moves an
 * upload" fail on the sentence saying wp_handle_upload() would put a list of names into a
 * web-served directory, which is the assertion punishing the documentation for being specific.
 */
$code = '';

foreach ( token_get_all( '<?php ' . $source ) as $token ) {
	if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
		continue;
	}

	$code .= is_array( $token ) ? $token[1] : $token;
}

// `wp_handle_upload()` would move a list of names and addresses into the uploads directory,
// which this site serves over the web.
ck( 'nothing here moves an upload', false !== strpos( $code, 'wp_handle_upload' ), false );
// The size is checked before the read, so a large file costs the memory of nothing.
ck( 'the size is checked before the contents are read', strpos( $code, 'self::MAX_BYTES' ) < strpos( $code, 'file_get_contents' ), true );
ck( 'and only a real upload is read at all', false !== strpos( $code, 'is_uploaded_file' ), true );

// Never in this project, chat, code or comment alike.
foreach ( array( 'module' => $source, 'suite' => file_get_contents( __FILE__ ) ) as $what => $text ) {
	ck( sprintf( 'no dash but the plain hyphen in the %s', $what ), preg_match( '/[\x{2013}\x{2014}]/u', $text ), 0 );
}

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
