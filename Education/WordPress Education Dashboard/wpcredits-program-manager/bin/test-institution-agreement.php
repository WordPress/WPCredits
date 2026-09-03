<?php
/**
 * Does the agreement gate open only when both sources say so, and does every route to it check?
 *
 * `is_settled()` is what stands between an institution account and every student on its
 * roster, so the first half of this file pins one way it must stay closed per assertion: no
 * option, a malformed one, one at another version, one whose flag contradicts its own sides, a
 * grid row that says `Accepted` with no accepted post, an accepted post under a grid row that
 * says `Revoked`. And the one way it opens without an upload: a manager types `On file` and a
 * Drive link into the grid, the rebuild materialises a legacy post, and the two sides agree.
 *
 * The second half is the transitions that reach it, and what each one is worth pinning for:
 *
 * - **`inspect_pdf()` against real PDFs.** A `/Launch` written `/L#61unch`, and one hidden in
 *   a `FlateDecode` stream, and both at once. A scan that searched the raw bytes would pass
 *   every one of them, so the escapes are undone and the streams inflated first. The budget
 *   that stops a zip bomb is asserted as a budget, because it is also a limit on what the
 *   scan finds and the design says so out loud.
 * - **Every upload refusal names its reason, and nothing reaches the store.** Half these
 *   assertions are about a call that must not have happened: the ceiling is claimed before
 *   ten megabytes are read, and `store()` is reached only after the extension, the declared
 *   type, the magic bytes, `finfo` and the scan have all passed.
 * - **The download is never inline and never named by the uploader.** The body is read from a
 *   child process, because a successful download ends in `exit`; the headers are asserted
 *   against the source, because PHP under CLI discards them.
 * - **Accept reads live, writes once, and opens the gate on that request.** One PATCH with
 *   the five agreement cells, `Current Stage` only when the stage read precedes `Confirmed`,
 *   a terminal stage refused by name, and `is_settled()` true on the next line.
 * - **The two crons do only what they say.** Discard forgets the files of withdrawn and
 *   returned documents past the setting and nothing else, keeping every post; the digest goes
 *   out only when something is overdue, and once a day.
 *
 * The other classes are stood in for at their contracts: the fence, the membership store, the
 * roster's `resolve_institution()`, the private store, the ceiling, the mailer, the audit log,
 * the Airtable client and the sync's field map. The index, the roles, the request reader and
 * the flash are the real files. Nothing else is loaded.
 *
 * Run from the plugin root:  php bin/test-institution-agreement.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MB_IN_BYTES', 1048576 );

$GLOBALS['opts']       = array();
$GLOBALS['posts']      = array();
$GLOBALS['pmeta']      = array();
$GLOBALS['umeta']      = array();
$GLOBALS['post_types'] = array();
$GLOBALS['clock']      = 1756700000;
$GLOBALS['next_id']    = 500;

// The handler world: who is asking, what the stubs were told to answer, and a journal of
// everything that left the process. Every assertion below reads one of these.
$GLOBALS['users']       = array();
$GLOBALS['uid']         = 0;
$GLOBALS['caps']        = false;
$GLOBALS['referer']     = '';
$GLOBALS['nonces']      = array();
$GLOBALS['journal']     = array();
$GLOBALS['audit']       = array();
$GLOBALS['patched']     = array();
$GLOBALS['airtable']    = null;
$GLOBALS['live']        = array();
$GLOBALS['mail']        = array();
$GLOBALS['managers']    = array();
$GLOBALS['members']     = array();
$GLOBALS['switcher']    = '';
$GLOBALS['files']       = array();
$GLOBALS['stored']      = array();
$GLOBALS['forgotten']   = array();
$GLOBALS['store_fails'] = false;
$GLOBALS['post_fails']  = false;
$GLOBALS['claims']      = array();
$GLOBALS['decisions']   = array();
$GLOBALS['index_rows']  = array();
$GLOBALS['filetype']    = null;
$GLOBALS['settings']    = array();
$GLOBALS['countries']   = array();
$GLOBALS['temp_files']  = array();
$GLOBALS['queries']     = array();
$GLOBALS['lock_claims'] = 0;
$GLOBALS['lock_steal']  = 0;

class WP_Error {
	public $code = '';
	public $message = '';
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_author = 0, $post_title = '', $post_date = '';
	public function __construct( $id = 0, $type = '' ) { $this->ID = (int) $id; $this->post_type = (string) $type; }
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '', $email = '' ) { $this->ID = (int) $id; $this->display_name = $name; $this->user_email = $email; }
	public function exists() { return $this->ID > 0; }
}

/**
 * The slice of `$wpdb` the class touches: one LIKE over option names.
 */
class WPCPM_Test_Wpdb {
	public $options = 'wp_options';
	private $args = array();
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) { $this->args = $args; return $sql; }
	public function get_col( $sql ) {
		$like   = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), (string) ( $this->args[0] ?? '' ) );
		$prefix = rtrim( $like, '%' );
		$out    = array();
		foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
			if ( 0 === strpos( $name, $prefix ) ) { $out[] = $name; }
		}
		return $out;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Test_Wpdb();

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url_raw( $u, $p = null ) { return (string) $u; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_parse_url( $u, $c = -1 ) { return -1 === $c ? parse_url( $u ) : parse_url( $u, $c ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function add_action( $h, $c, $p = 10, $a = 1 ) {}
function register_post_type( $t, $a = array() ) { $GLOBALS['post_types'][ $t ] = $a; return (object) $a; }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	// A lock somebody else takes between two claims of this process's own. It is the only way
	// to stand in for a sync that starts rebuilding a record mid-transition, because a handler
	// lets go of its lock on the line before the rebuild takes it and no assertion can reach
	// in between. `lock_steal` says which claim from now on is the one that finds it held.
	if ( 0 === strpos( (string) $k, WPCPM_Institution_Agreement::LOCK_PREFIX ) ) {
		++$GLOBALS['lock_claims'];
		if ( $GLOBALS['lock_claims'] === $GLOBALS['lock_steal'] ) {
			$GLOBALS['opts'][ $k ] = time();
			return false;
		}
	}
	$GLOBALS['opts'][ $k ] = $v; return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

/**
 * Post meta that behaves like WordPress's: repeated rows, and `true` returns the first.
 *
 * The event log is a repeating key and the route detection reads it, so the harness must
 * keep rows apart rather than one value per key.
 */
function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value; return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ); return true; }

function wp_insert_post( $a, $error = false ) {
	$post              = new WP_Post();
	$post->ID          = $GLOBALS['next_id']++;
	$post->post_type   = $a['post_type'] ?? 'post';
	$post->post_status = $a['post_status'] ?? 'publish';
	$post->post_author = (int) ( $a['post_author'] ?? 0 );
	$post->post_title  = $a['post_title'] ?? '';
	$post->post_date   = gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] );
	$GLOBALS['clock'] += 60;
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }

/**
 * `get_posts()` as the class uses it: type, status, meta clauses, a direction, ids or posts.
 *
 * `EXISTS` is answered the way WordPress answers it, on the key and never on the value, and
 * `offset` is applied before the limit. Both are the discard cron's: it asks for the documents
 * that still have a file and pages through them, and a stub that ignored either clause would
 * pass a query that reads the same rows every night.
 */
function get_posts( $a = array() ) {
	$GLOBALS['queries'][] = $a;
	$out                  = array();
	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $a['post_type'] ) && $post->post_type !== $a['post_type'] ) { continue; }
		if ( isset( $a['post_status'] ) && 'any' !== $a['post_status'] && $post->post_status !== $a['post_status'] ) { continue; }
		if ( ! empty( $a['meta_query'] ) ) {
			foreach ( $a['meta_query'] as $clause ) {
				if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
				$rows    = get_post_meta( $post->ID, $clause['key'] );
				$compare = isset( $clause['compare'] ) ? strtoupper( (string) $clause['compare'] ) : '=';
				if ( 'EXISTS' === $compare ) {
					if ( ! $rows ) { continue 2; }
					continue;
				}
				$have = $rows ? $rows[0] : '';
				if ( 'IN' === $compare ? ! in_array( $have, (array) $clause['value'], true ) : $have !== $clause['value'] ) { continue 2; }
			}
		}
		$out[] = $post;
	}
	// The queue asks for oldest first and everything else for newest first, so the direction
	// is read from the arguments rather than fixed: a stub that always answered newest first
	// would pass a queue that hands a manager the most recent upload as the one that has
	// waited longest.
	$up = isset( $a['orderby']['date'] ) && 'ASC' === strtoupper( (string) $a['orderby']['date'] );
	usort( $out, function ( $x, $y ) use ( $up ) {
		$by_date = $up ? strcmp( $x->post_date, $y->post_date ) : strcmp( $y->post_date, $x->post_date );
		if ( 0 !== $by_date ) { return $by_date; }
		return $up ? $x->ID - $y->ID : $y->ID - $x->ID;
	} );
	if ( isset( $a['offset'] ) && (int) $a['offset'] > 0 ) {
		$out = array_slice( $out, (int) $a['offset'] );
	}
	if ( isset( $a['numberposts'] ) && (int) $a['numberposts'] > 0 ) {
		$out = array_slice( $out, 0, (int) $a['numberposts'] );
	}
	if ( isset( $a['fields'] ) && 'ids' === $a['fields'] ) {
		return array_map( function ( $p ) { return $p->ID; }, $out );
	}
	return $out;
}

/* ---- the handler world -------------------------------------------------- */

function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function number_format_i18n( $n ) { return (string) (int) $n; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_unslash( $v ) { return $v; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $s ); }
function sanitize_title( $s ) { return trim( preg_replace( '/-+/', '-', preg_replace( '/[^a-z0-9]/', '-', strtolower( (string) $s ) ) ), '-' ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_login_url( $to = '' ) { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $to ); }
function add_query_arg( $args, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function nocache_headers() { $GLOBALS['journal'][] = 'nocache'; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function current_user_can( $c ) { return (bool) $GLOBALS['caps']; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
function get_user_meta( $id, $k = '', $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ( $single ? '' : array() ); }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function wp_get_referer() { return '' !== $GLOBALS['referer'] ? $GLOBALS['referer'] : false; }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['nonces'][] = $a; return true; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect: ' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die: ' . $m ); }

/**
 * `wp_check_filetype_and_ext()` as WordPress writes it: the name, cross-checked with fileinfo.
 *
 * Faithful rather than a switch, because the point of step 7 is that a name saying `pdf` over
 * bytes that are not one is refused there. `$GLOBALS['filetype']` overrides it for the one
 * assertion that needs the check to pass on bytes chosen to fail a later step.
 *
 * @param string $file     Temporary path.
 * @param string $filename The name the browser sent.
 * @param array  $mimes    Allowed types.
 * @return array
 */
function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
	if ( null !== $GLOBALS['filetype'] ) { return $GLOBALS['filetype']; }
	$ext  = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
	$real = '';
	if ( function_exists( 'finfo_open' ) && is_readable( $file ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$real  = $finfo ? (string) finfo_file( $finfo, $file ) : '';
	}
	if ( 'pdf' !== $ext || ( '' !== $real && 'application/pdf' !== $real ) ) {
		return array( 'ext' => false, 'type' => false, 'proper_filename' => false );
	}
	return array( 'ext' => 'pdf', 'type' => 'application/pdf', 'proper_filename' => false );
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/**
	 * The membership store, at the two methods this class reads.
	 *
	 * `$GLOBALS['members']` is record ID to account IDs, so "every member" and "which
	 * institution is this account's" are answered from one place and cannot disagree.
	 */
	class WPCPM_Institution_Members {
		public static function members_of( $record_id ) {
			$out = array();
			foreach ( (array) ( $GLOBALS['members'][ (string) $record_id ] ?? array() ) as $id ) {
				if ( isset( $GLOBALS['users'][ $id ] ) ) { $out[] = $GLOBALS['users'][ $id ]; }
			}
			return $out;
		}
		public static function institution_of( $user = null ) {
			$id = $user instanceof WP_User ? $user->ID : (int) $user;
			foreach ( $GLOBALS['members'] as $record => $ids ) {
				if ( in_array( $id, (array) $ids, true ) ) { return (string) $record; }
			}
			return '';
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Roster' ) ) {
	/** Section 5.5, at its contract: a member's own institution, else a manager's switcher. */
	class WPCPM_Institution_Roster {
		public static function resolve_institution( $viewer, $can_manage ) {
			$own = WPCPM_Institution_Members::institution_of( $viewer );
			if ( '' !== $own ) { return $own; }
			return $can_manage ? (string) $GLOBALS['switcher'] : '';
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Policy' ) ) {
	/**
	 * The fence, deciding the way the real class does rather than answering a flag.
	 *
	 * A stub that said yes to everyone would pass a handler that never asked, which is the
	 * bug this module has already had once. The manager ground comes first and is
	 * unconditional; the member ground is membership of an institution the subject names,
	 * and it is gated on the agreement for every action but `ACT_AGREEMENT` itself, because
	 * a member whose agreement is outstanding is exactly who these handlers exist for.
	 */
	class WPCPM_Institution_Policy {
		const ACT_AGREEMENT  = 'agreement';
		const GROUND_MANAGER = 'manager';
		const GROUND_MEMBER  = 'member';
		const REFUSAL_CODE   = 'wpcpm_inst_unknown';
		public static function ungated() {
			return array( self::ACT_AGREEMENT );
		}
		public static function subject_institution( $record_id ) {
			return array(
				'type'            => 'institution',
				'id'              => $record_id,
				'institution_ids' => array( $record_id ),
				'evidence'        => 'index',
			);
		}
		public static function subject_post( WP_Post $post, $meta_key ) {
			return array(
				'type'            => 'agreement',
				'id'              => (int) $post->ID,
				'institution_ids' => (array) get_post_meta( (int) $post->ID, (string) $meta_key, true ),
				'evidence'        => 'cache',
			);
		}
		public static function decide( $action, array $subject, $user = null ) {
			$GLOBALS['decisions'][] = array( $action, $subject['id'] );
			$ids = array_values( array_filter( (array) ( $subject['institution_ids'] ?? array() ), array( 'WPCPM_Mentors_Sync', 'is_record_id' ) ) );

			if ( $GLOBALS['caps'] ) {
				return self::answer( true, self::GROUND_MANAGER, (string) ( $ids[0] ?? '' ) );
			}

			$mine = WPCPM_Institution_Members::institution_of( wp_get_current_user() );

			if ( '' !== $mine && in_array( $mine, $ids, true )
				&& ( in_array( $action, self::ungated(), true ) || WPCPM_Institution_Agreement::is_settled( $mine ) ) ) {
				return self::answer( true, self::GROUND_MEMBER, $mine );
			}

			return self::answer( false, '', '' );
		}
		public static function refusal() {
			return new WP_Error( self::REFUSAL_CODE, 'That record is not on your roster.' );
		}
		private static function answer( $allowed, $ground, $institution ) {
			return array(
				'allowed'     => (bool) $allowed,
				'ground'      => $ground,
				'institution' => $institution,
				'fields'      => $allowed ? null : array(),
				'why'         => $allowed ? '' : 'no-ground',
			);
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Sync' ) ) {
	/** Only the field map the writes read. Checked against the fixture below. */
	class WPCPM_Institutions_Sync {
		public static function fields() {
			return array(
				'name'             => 'Name',
				'stage'            => 'Current Stage',
				'country'          => 'Country',
				'city'             => 'City',
				'website'          => 'Website',
				'contact_person'   => 'Contact Person',
				'contact_email'    => 'Contact Email',
				'confirmed_on'     => 'Confirmed on',
				'consent'          => 'Privacy Policy Compliance',
				'agr_status'       => 'Agreement Status',
				'agr_kind'         => 'Agreement Kind',
				'agr_accepted_on'  => 'Agreement Accepted On',
				'agr_signed_on'    => 'Agreement Signed On',
				'agr_accepted_by'  => 'Agreement Accepted By',
				'agr_document'     => 'Agreement Document',
				'agr_submitted_on' => 'Agreement Submitted On',
				'agr_template'     => 'Agreement Template Version',
			);
		}
	}
}

if ( ! class_exists( 'WPCPM_Settings' ) ) {
	/** The settings, from one array the assertions move. */
	class WPCPM_Settings {
		public static function get() { return $GLOBALS['settings']; }
		public static function get_value( $key, $fallback = null ) {
			return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback;
		}
	}
}

if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/** Both routes, journalled, so their order among the other writes can be asserted. */
	class WPCPM_Airtable {
		public function __construct( $settings = null ) {}
		public function get_record( $table, $record_id ) {
			$GLOBALS['journal'][] = 'airtable-read';
			if ( $GLOBALS['live'] instanceof WP_Error ) { return $GLOBALS['live']; }
			return array( 'id' => $record_id, 'fields' => (array) $GLOBALS['live'] );
		}
		public function update_records( $table, array $records ) {
			$GLOBALS['journal'][] = 'airtable';
			$GLOBALS['patched'][] = array( $table, $records );
			if ( $GLOBALS['airtable'] instanceof WP_Error ) { return $GLOBALS['airtable']; }
			if ( is_array( $GLOBALS['airtable'] ) ) { return $GLOBALS['airtable']; }
			return array( $records[0]['id'] => true );
		}
	}
}

if ( ! class_exists( 'WPCPM_Private_Files' ) ) {
	/**
	 * The store, in memory, at its three-method contract.
	 *
	 * Every call is journalled, because half the assertions in this file are about a call
	 * that must NOT have happened: nothing reaches the private directory that has not passed
	 * every check, and the only way to see that is to watch the door.
	 */
	class WPCPM_Private_Files {
		public static function store( $bytes, $extension = 'pdf' ) {
			$GLOBALS['journal'][] = 'store';
			$GLOBALS['stored'][]  = array( 'bytes' => $bytes, 'extension' => $extension );
			if ( $GLOBALS['store_fails'] ) { return new WP_Error( 'wpcpm_private_write', 'The file could not be written.' ); }
			$path = gmdate( 'Y' ) . '/' . str_pad( (string) ( count( $GLOBALS['files'] ) + 1 ), 32, 'a', STR_PAD_LEFT ) . '.' . $extension;
			$GLOBALS['files'][ $path ] = $bytes;
			return array( 'path' => $path, 'sha256' => hash( 'sha256', $bytes ), 'size' => strlen( $bytes ) );
		}
		public static function read( $relative ) {
			return array_key_exists( $relative, $GLOBALS['files'] ) ? $GLOBALS['files'][ $relative ] : new WP_Error( 'wpcpm_private_missing', 'That file is not in the store.' );
		}
		public static function forget( $relative ) {
			$GLOBALS['journal'][]   = 'forget';
			$GLOBALS['forgotten'][] = $relative;
			unset( $GLOBALS['files'][ $relative ] );
			return true;
		}
	}
}

if ( ! class_exists( 'WPCPM_Ceiling' ) ) {
	/** The ceiling at its contract: the first `$limit` claims in a window pass, the rest do not. */
	class WPCPM_Ceiling {
		public static function claim( $key, $limit, $window ) {
			$GLOBALS['journal'][] = 'ceiling';
			$GLOBALS['claims'][]  = array( (string) $key, (int) $limit, (int) $window );
			$seen = 0;
			foreach ( $GLOBALS['claims'] as $claim ) {
				if ( $claim[0] === (string) $key ) { ++$seen; }
			}
			return $seen <= (int) $limit;
		}
	}
}

if ( ! class_exists( 'WPCPM_Mail' ) ) {
	/** Every send, with the built message, so "once per member" is countable. */
	class WPCPM_Mail {
		public static function send( $recipient, $context, $build ) {
			if ( ! $recipient instanceof WP_User || ! $recipient->exists() || '' === $recipient->user_email ) { return false; }
			$mail = (array) call_user_func( $build, $recipient );
			$GLOBALS['mail'][] = array(
				'to'      => $recipient->user_email,
				'context' => $context,
				'subject' => (string) ( $mail['subject'] ?? '' ),
				'body'    => (string) ( $mail['body'] ?? '' ),
				'headers' => (array) ( $mail['headers'] ?? array() ),
			);
			return true;
		}
		public static function send_to( $email, $context, $build, $locale = '' ) {
			$mail = (array) call_user_func( $build, $email );
			$GLOBALS['mail'][] = array(
				'to'      => (string) $email,
				'context' => $context,
				'subject' => (string) ( $mail['subject'] ?? '' ),
				'body'    => (string) ( $mail['body'] ?? '' ),
				'headers' => (array) ( $mail['headers'] ?? array() ),
			);
			return true;
		}
		public static function reply_to( $person ) {
			return $person instanceof WP_User && '' !== $person->user_email ? array( 'Reply-To: "' . $person->display_name . '" <' . $person->user_email . '>' ) : array();
		}
		public static function site_name() { return 'WPCredits'; }
	}
}

if ( ! class_exists( 'WPCPM_Institutions' ) ) {
	/** The flash channel and the one notification mechanism. */
	class WPCPM_Institutions {
		const FLASH = 'institutions';
		public static function notify_managers( $context, $build ) {
			$sent = 0;
			foreach ( $GLOBALS['managers'] as $manager ) {
				$sent += WPCPM_Mail::send( $manager, $context, $build ) ? 1 : 0;
			}
			return $sent;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Audit' ) ) {
	/**
	 * The log, at its contract, refusals included.
	 *
	 * It validates what the real class validates, so that a row this file asserts was written
	 * is a row the real class would have kept rather than silently dropped.
	 */
	class WPCPM_Institution_Audit {
		const EVIDENCE_INDEX = 'index';
		const EVIDENCE_CACHE = 'cache';
		const EVIDENCE_LIVE  = 'live';
		const GROUND_MANAGER = 'manager';
		const GROUND_MEMBER  = 'member';
		const GROUND_SYSTEM  = 'system';
		public static function grounds() {
			return array( self::GROUND_MANAGER, self::GROUND_MEMBER, self::GROUND_SYSTEM );
		}
		public static function evidence_levels() {
			return array( self::EVIDENCE_INDEX, self::EVIDENCE_CACHE, self::EVIDENCE_LIVE );
		}
		public static function record( array $entry ) {
			if ( '' === sanitize_key( (string) ( $entry['kind'] ?? '' ) )
				|| ! WPCPM_Mentors_Sync::is_record_id( (string) ( $entry['institution'] ?? '' ) )
				|| ! in_array( (string) ( $entry['ground'] ?? '' ), self::grounds(), true )
				|| ! in_array( (string) ( $entry['evidence'] ?? '' ), self::evidence_levels(), true ) ) {
				return new WP_Error( 'wpcpm_audit', 'refused' );
			}
			$GLOBALS['journal'][] = 'audit';
			$GLOBALS['audit'][]   = $entry;
			return 900 + count( $GLOBALS['audit'] );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	/** The page the accepted mail points at, for its URL alone. */
	class WPCPM_Institutions_Dashboard {
		public static function page_url() { return 'https://example.test/institution-dashboard/'; }
	}
}

if ( ! class_exists( 'WPCPM_Countries' ) ) {
	/**
	 * The routing table, at the two methods the revoked mail reads.
	 *
	 * `routing()` answers null for an unknown country and for a known one with nobody
	 * against it, which is the contract's one check for a caller that wants to route: the
	 * revoked mail has a sentence for each of those two and the assertions read both.
	 */
	class WPCPM_Countries {
		public static function routing( $record_id ) {
			$row = $GLOBALS['countries'][ (string) $record_id ] ?? null;
			return ( null === $row || '' === trim( (string) $row['email'] ) ) ? null : $row;
		}
		public static function name_of( $record_id ) {
			$row = $GLOBALS['countries'][ (string) $record_id ] ?? null;
			return null === $row ? '' : (string) $row['name'];
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php';

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
 * Forget every option, lock and post.
 */
function reset_world() {
	$GLOBALS['opts']        = array();
	$GLOBALS['posts']       = array();
	$GLOBALS['pmeta']       = array();
	$GLOBALS['umeta']       = array();
	$GLOBALS['users']       = array();
	$GLOBALS['uid']         = 0;
	$GLOBALS['caps']        = false;
	$GLOBALS['referer']     = '';
	$GLOBALS['nonces']      = array();
	$GLOBALS['journal']     = array();
	$GLOBALS['audit']       = array();
	$GLOBALS['patched']     = array();
	$GLOBALS['airtable']    = null;
	$GLOBALS['live']        = array();
	$GLOBALS['mail']        = array();
	$GLOBALS['managers']    = array();
	$GLOBALS['members']     = array();
	$GLOBALS['switcher']    = '';
	$GLOBALS['files']       = array();
	$GLOBALS['stored']      = array();
	$GLOBALS['forgotten']   = array();
	$GLOBALS['store_fails'] = false;
	$GLOBALS['claims']      = array();
	$GLOBALS['decisions']   = array();
	$GLOBALS['index_rows']  = array();
	$GLOBALS['countries']   = array();
	$GLOBALS['filetype']    = null;
	$GLOBALS['queries']     = array();
	$GLOBALS['lock_claims'] = 0;
	$GLOBALS['lock_steal']  = 0;
	$GLOBALS['settings']    = array(
		'institutions_table'        => 'tbl4V0FEbzRP7I2w2',
		'agreement_max_mb'          => 10,
		'agreement_uploads_per_day' => 5,
		'agreement_review_days'     => 3,
		'agreement_discard_days'    => 30,
	);
	$_POST                  = array();
	$_GET                   = array();
	$_FILES                 = array();
}

/**
 * Stand up an agreement post the way a later phase's handler would.
 *
 * @param string $record Institutions record ID.
 * @param string $state  A STATE_* value.
 * @param string $kind   A KIND_* value.
 * @param array  $meta   Extra meta rows.
 * @return int Post ID.
 */
function seed_post( $record, $state, $kind = 'template', array $meta = array() ) {
	$id = wp_insert_post( array(
		'post_type'   => WPCPM_Institution_Agreement::POST_TYPE,
		'post_status' => WPCPM_Institution_Agreement::POST_STATUS,
		'post_author' => 7,
		'post_title'  => 'seeded',
	) );
	update_post_meta( $id, WPCPM_Institution_Agreement::META_INSTITUTION, $record );
	update_post_meta( $id, WPCPM_Institution_Agreement::META_STATE, $state );
	update_post_meta( $id, WPCPM_Institution_Agreement::META_KIND, $kind );
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return $id;
}

/**
 * The Airtable agreement block for a record, every field present.
 *
 * @param string $status   Agreement Status.
 * @param string $document Agreement Document.
 * @param array  $more     Other fields.
 * @return array
 */
function block( $status, $document = '', array $more = array() ) {
	return array_merge( array(
		'status'           => $status,
		'kind'             => '',
		'accepted_on'      => '',
		'signed_on'        => '',
		'accepted_by'      => '',
		'document'         => $document,
		'submitted_on'     => '',
		'template_version' => '',
	), $more );
}

/**
 * Every agreement post for a record, oldest first, as `state/kind` strings.
 *
 * @param string $record Institutions record ID.
 * @return string[]
 */
function shapes_of( $record ) {
	$out = array();
	foreach ( array_reverse( WPCPM_Institution_Agreement::posts_for( $record ) ) as $post ) {
		$out[] = get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_STATE, true ) . '/' . get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_KIND, true );
	}
	return $out;
}


/**
 * Put one row in the pipeline index, keeping the ones already there.
 *
 * @param string $record Institutions record ID.
 * @param string $name   The institution's name, as Airtable holds it.
 * @param string $stage  Its `Current Stage`.
 */
function seed_index( $record, $name, $stage = 'Under Review' ) {
	$GLOBALS['index_rows'][ $record ] = array_merge(
		WPCPM_Institutions_Index::empty_row(),
		array(
			'record_id' => $record,
			'name'      => $name,
			'stage'     => $stage,
		)
	);
	WPCPM_Institutions_Index::write( $GLOBALS['index_rows'], time() );
}

/**
 * Give an institution a country, and that country somebody to write to.
 *
 * Two rows, because the address the revoked mail names is read through the index row and the
 * routing table together, and a fixture that set only one of them would pass a mail that
 * named a country nobody is against.
 *
 * @param string $record  Institutions record ID.
 * @param string $country Countries record ID.
 * @param string $name    The country's name.
 * @param string $email   The contact's address, or '' for a country with nobody against it.
 */
function seed_country( $record, $country, $name = 'Costa Rica', $email = 'costa.rica@example.org' ) {
	$GLOBALS['index_rows'][ $record ]['country'] = $country;
	WPCPM_Institutions_Index::write( $GLOBALS['index_rows'], time() );

	$GLOBALS['countries'][ $country ] = array(
		'name'     => $name,
		'manager'  => 'recTEAMAAAAAAAAA1',
		'email'    => $email,
		'calendly' => '',
	);
}

/**
 * Sign somebody in.
 *
 * @param int    $id         Account ID.
 * @param string $name       Display name.
 * @param string $email      Address.
 * @param bool   $can_manage Whether they hold CAP_MANAGE.
 * @return WP_User
 */
function sign_in( $id, $name, $email, $can_manage = false ) {
	$GLOBALS['users'][ $id ] = new WP_User( $id, $name, $email );
	$GLOBALS['uid']          = $id;
	$GLOBALS['caps']         = $can_manage;
	return $GLOBALS['users'][ $id ];
}

/**
 * Run a handler and say how it ended: the outcome slug it flashed, or the refusal it died on.
 *
 * Every handler here ends in `wp_safe_redirect()` or `wp_die()`, both of which throw in this
 * harness, so the message is the whole answer and no assertion has to guess whether a handler
 * fell off the end.
 *
 * @param string $method The handler to call.
 * @return string
 */
function run( $method ) {
	unset( $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ] );

	try {
		call_user_func( array( 'WPCPM_Institution_Agreement', $method ) );
	} catch ( Exception $e ) {
		if ( 0 === strpos( $e->getMessage(), 'wp_die: ' ) ) {
			return 'died: ' . substr( $e->getMessage(), 8 );
		}

		$flash = flashed();

		return '' === $flash ? $e->getMessage() : $flash;
	}

	return 'no exit';
}

/**
 * The outcome slug pending for the signed-in account, read from the meta the flash writes.
 *
 * Read from the meta rather than through `take()`, which memoizes per request and would hand
 * the second assertion in a row the first one's answer.
 *
 * @return string
 */
function flashed() {
	$pending = get_user_meta( $GLOBALS['uid'], WPCPM_Flash::META, true );

	return is_array( $pending ) && isset( $pending[ WPCPM_Institutions::FLASH ] ) ? (string) $pending[ WPCPM_Institutions::FLASH ] : '';
}

/**
 * Write a file into the system temporary directory and remember it for cleanup.
 *
 * Real files, because `filesize()`, `is_readable()` and `finfo_file()` are what the handler
 * asks about them and none of those can be stubbed.
 *
 * @param string $bytes What to write.
 * @return string The path.
 */
function temp_file( $bytes ) {
	$path = tempnam( sys_get_temp_dir(), 'wpcpm' );
	file_put_contents( $path, $bytes );
	$GLOBALS['temp_files'][] = $path;
	return $path;
}

/**
 * Present a file to the upload handler the way PHP would.
 *
 * @param string   $path  Temporary path.
 * @param string   $name  The name the browser sent.
 * @param int|null $size  What PHP reported; null for the real one.
 * @param int      $error The upload error code.
 */
function post_file( $path, $name = 'agreement.pdf', $size = null, $error = UPLOAD_ERR_OK ) {
	$_FILES = array(
		'wpcpm_agreement_file' => array(
			'name'     => $name,
			'type'     => 'application/pdf',
			'tmp_name' => $path,
			'error'    => $error,
			'size'     => null === $size ? (int) filesize( $path ) : (int) $size,
		),
	);
}

/**
 * A minimal PDF that every check in the handler passes.
 *
 * @param string $extra Anything to append before the trailer.
 * @return string
 */
function pdf_bytes( $extra = '' ) {
	return "%PDF-1.4\n"
		. "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
		. "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
		. "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
		. $extra
		. "trailer<</Root 1 0 R/Size 4>>\n%%EOF\n";
}

/**
 * A PDF object whose stream is deflated, so the scan has to inflate it to see inside.
 *
 * @param string $inside What the stream says once inflated.
 * @return string
 */
function pdf_flate_object( $inside ) {
	$body = gzcompress( $inside );

	return "4 0 obj<</Length " . strlen( $body ) . "/Filter/FlateDecode>>stream\n" . $body . "\nendstream endobj\n";
}

/**
 * The Airtable cells one PATCH carried, from the journal.
 *
 * @param int $which Which PATCH; 0 for the first.
 * @return array
 */
function patched_cells( $which = 0 ) {
	return $GLOBALS['patched'][ $which ][1][0]['fields'] ?? array();
}

/**
 * Every message sent in this block, as `context to address`.
 *
 * @return string[]
 */
function mail_log() {
	return array_map(
		function ( $row ) {
			return $row['context'] . ' to ' . $row['to'];
		},
		$GLOBALS['mail']
	);
}

/**
 * The state of every agreement post for a record, oldest first.
 *
 * @param string $record Institutions record ID.
 * @return string[]
 */
function states_of( $record ) {
	$out = array();
	foreach ( array_reverse( WPCPM_Institution_Agreement::posts_for( $record ) ) as $post ) {
		$out[] = (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_STATE, true );
	}
	return $out;
}

/**
 * One method's body, by brace depth, for the assertions that read source rather than behaviour.
 *
 * The response headers of a download cannot be read back at all under CLI, and the order of
 * the checks in an upload is a property of the text rather than of any one run, so both are
 * asserted against the file itself.
 *
 * @param string $source The file.
 * @param string $name   The method.
 * @return string|null
 */
function method_body( $source, $name ) {
	if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\([^)]*\)\s*(?::\s*[^{]+)?\{/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	$offset = $m[0][1] + strlen( $m[0][0] );
	$depth  = 1;
	$end    = $offset;
	$length = strlen( $source );

	while ( $end < $length && $depth > 0 ) {
		if ( '{' === $source[ $end ] ) {
			++$depth;
		} elseif ( '}' === $source[ $end ] ) {
			--$depth;
		}

		++$end;
	}

	return substr( $source, $offset, $end - $offset );
}

/**
 * The world a successful download runs in, for the child process below.
 *
 * @return int The post ID being downloaded.
 */
function download_world() {
	reset_world();
	seed_index( 'recAAAAAAAAAAAAA1', 'Universidad Example' );
	sign_in( 4, 'Ola Member', 'ola@example.edu' );
	$GLOBALS['members']['recAAAAAAAAAAAAA1'] = array( 4 );
	$GLOBALS['files']['2026/deadbeef.pdf']   = "%PDF-1.4 the signed copy\n";

	$id = seed_post(
		'recAAAAAAAAAAAAA1',
		'accepted',
		'own',
		array(
			WPCPM_Institution_Agreement::META_FILE          => array(
				'path'   => '2026/deadbeef.pdf',
				'size'   => 25,
				'sha256' => str_repeat( 'b', 64 ),
			),
			WPCPM_Institution_Agreement::META_ORIGINAL_NAME => 'Umowa podpisana.pdf',
		)
	);

	$_GET = array(
		'post'     => $id,
		'_wpnonce' => 'irrelevant-here',
	);

	return $id;
}

// A successful download ends in `exit`, which no harness can catch, so the two assertions
// about what it actually sends are made by running this same file as a child and reading its
// output. The headers cannot be read back at all: PHP under CLI discards them, so they are
// asserted against the source instead, at the foot of the download block.
if ( in_array( '--download-child', (array) ( $argv ?? array() ), true ) ) {
	download_world();

	// The same request from a program manager, who is on no institution's roster and reaches
	// the document through the fence's manager ground instead. In a child for the same reason:
	// the only proof that they reach it is the bytes it sends before exiting.
	if ( in_array( '--as-manager', (array) ( $argv ?? array() ), true ) ) {
		$GLOBALS['members'] = array();
		sign_in( 1, 'Kasia Manager', 'kasia@example.org', true );
	}

	WPCPM_Institution_Agreement::handle_download();
	exit( 0 );
}

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/institutions-table-fields.json' ), true );
$seed    = json_decode( file_get_contents( __DIR__ . '/fixtures/institutions-index-seed.json' ), true );
$drive   = 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz';
$rec_a   = 'recAAAAAAAAAAAAA1';
$rec_b   = 'recBBBBBBBBBBBBB2';
$rec_c   = 'recCCCCCCCCCCCCC3';

/* ---- the constants pin the base's spelling ------------------------------ */

echo "=== The constants pin the base's spelling ===\n";

// A wrong-length ID goes down the refusal path and passes half the file by accident.
ck( 'the IDs this file types are record-shaped', array( strlen( $rec_a ), strlen( $rec_b ), strlen( $rec_c ), WPCPM_Mentors_Sync::is_record_id( $rec_a ) ), array( 17, 17, 17, true ) );

$stages = array_merge( WPCPM_Institution_Agreement::STAGE_ORDER, WPCPM_Institution_Agreement::TERMINAL_STAGES );
sort( $stages, SORT_STRING );
$choices = $fixture['choices']['Current Stage'];
sort( $choices, SORT_STRING );

ck( 'STAGE_ORDER plus TERMINAL_STAGES is exactly the Current Stage choice list', $stages, $choices );
$order = WPCPM_Institution_Agreement::STAGE_ORDER;
ck( 'STAGE_ORDER ends in Student, which no write touches', end( $order ), 'Student' );
ck( 'every AIRTABLE_SETTLED value is an Agreement Status choice',
    array_values( array_diff( WPCPM_Institution_Agreement::AIRTABLE_SETTLED, $fixture['choices']['Agreement Status'] ) ), array() );
ck( 'AIRTABLE_ON_FILE is one of the settled values', in_array( WPCPM_Institution_Agreement::AIRTABLE_ON_FILE, WPCPM_Institution_Agreement::AIRTABLE_SETTLED, true ), true );
ck( 'Revoked and Awaiting review, which the assertions below type, are choices',
    array_values( array_diff( array( 'Revoked', 'Awaiting review' ), $fixture['choices']['Agreement Status'] ) ), array() );
ck( 'the option prefix and the lock prefix', array( WPCPM_Institution_Agreement::OPTION_PREFIX, WPCPM_Institution_Agreement::LOCK_PREFIX ), array( 'wpcpm_agreement_', 'wpcpm_agreement_lock_' ) );
ck( 'option_name() builds from the prefix', WPCPM_Institution_Agreement::option_name( $rec_a ), 'wpcpm_agreement_' . $rec_a );
ck( 'the post type is the one section 9 names', WPCPM_Institution_Agreement::POST_TYPE, 'wpcpm_agreement' );

/* ---- the post type is invisible ----------------------------------------- */

echo "\n=== The post type is invisible ===\n";

WPCPM_Institution_Agreement::register_post_type();
$args = $GLOBALS['post_types']['wpcpm_agreement'] ?? array();

ck( 'registered', empty( $args ), false );
ck( 'not public, no UI, not in REST, not searchable',
    array( $args['public'] ?? null, $args['show_ui'] ?? null, $args['show_in_rest'] ?? null, $args['exclude_from_search'] ?? null ),
    array( false, false, false, true ) );
ck( 'a capability type nobody holds, with map_meta_cap', array( $args['capability_type'] ?? null, $args['map_meta_cap'] ?? null ), array( array( 'wpcpm_agreement', 'wpcpm_agreements' ), true ) );

/* ---- the predicate fails closed ----------------------------------------- */

echo "\n=== The predicate fails closed ===\n";

reset_world();

ck( 'no option: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );
ck( 'no option: option() is null', WPCPM_Institution_Agreement::option( $rec_a ), null );
ck( 'not a record ID: locked', WPCPM_Institution_Agreement::is_settled( 'Universidad Example' ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = 'yes';
ck( 'a string where the array should be: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array( 'settled' => true );
ck( 'an array missing the version and the sides: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$good = array(
	'v'               => 1,
	'settled'         => true,
	'site_state'      => 'accepted',
	'airtable_status' => 'Accepted',
	'kind'            => 'template',
	'agreement_id'    => 12,
	'pending_id'      => 0,
	'generated_id'    => 0,
	'accepted_at'     => '2026-08-30',
	'drive_url'       => '',
	'updated'         => 1756700000,
);

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'v' => 2 ) );
ck( 'the wrong version: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'settled' => false, 'site_state' => 'none' ) );
ck( 'settled false: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'settled' => 1 ) );
ck( 'settled as an int rather than a bool: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'airtable_status' => 'Revoked' ) );
ck( 'a flag that says settled over a Revoked grid status: locked, the row is corrupt', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'site_state' => 'submitted' ) );
ck( 'a flag that says settled over a submitted site state: locked', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = $good;
ck( 'both sides settled and the flag agreeing: open', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
ck( 'option() hands the row back typed', WPCPM_Institution_Agreement::option( $rec_a ), $good );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'site_state' => 'on_file', 'airtable_status' => 'On file', 'kind' => 'legacy' ) );
ck( 'on file on both sides: open', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ] = array_merge( $good, array( 'site_state' => 'on_file', 'airtable_status' => 'Accepted', 'kind' => 'legacy' ) );
ck( 'on file here and Accepted there still agree on settled: open', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );

/* ---- rebuild: the two sources ------------------------------------------- */

echo "\n=== rebuild(): the two sources ===\n";

reset_world();
$post_a = seed_post( $rec_a, 'accepted', 'template', array( WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-08-30' ) );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted', '', array( 'kind' => 'Program template' ) ) );

ck( 'Accepted with an accepted template post: settled', $option['settled'], true );
ck( 'site_state accepted, kind template, the post named', array( $option['site_state'], $option['kind'], $option['agreement_id'] ), array( 'accepted', 'template', $post_a ) );
ck( 'accepted_at is the decision date', $option['accepted_at'], '2026-08-30' );
ck( 'the option is what was stored', $GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_a ], $option );
ck( 'the predicate now opens', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
ck( 'the lock was released', array_key_exists( 'wpcpm_agreement_lock_' . $rec_a, $GLOBALS['opts'] ), false );
ck( 'the version is stamped', $option['v'], 1 );
ck( 'route is site', WPCPM_Institution_Agreement::summary( $rec_a )['route'], 'site' );
ck( 'nothing to reconcile', WPCPM_Institution_Agreement::discrepancies(), array() );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );

ck( 'Accepted with no post: locked', $option['settled'], false );
ck( 'site_state none', $option['site_state'], 'none' );
ck( 'listed as a discrepancy naming both sides', WPCPM_Institution_Agreement::discrepancies(), array( $rec_a => array( 'site_state' => 'none', 'airtable_status' => 'Accepted' ) ) );
ck( 'no post was invented for a bare Accepted', shapes_of( $rec_a ), array() );

reset_world();
$post_b = seed_post( $rec_a, 'submitted', 'own' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Awaiting review' ) );

ck( 'Awaiting review with a submitted post: not settled', $option['settled'], false );
ck( 'site_state submitted, pending_id the post', array( $option['site_state'], $option['pending_id'], $option['agreement_id'] ), array( 'submitted', $post_b, 0 ) );
ck( 'in review is a queue, not a discrepancy', WPCPM_Institution_Agreement::discrepancies(), array() );

reset_world();
$post_c = seed_post( $rec_a, 'accepted', 'template' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Revoked' ) );

ck( 'Revoked in the grid over an accepted post: locked', $option['settled'], false );
ck( 'the post is untouched, the grid word is recorded', array( $option['site_state'], $option['airtable_status'] ), array( 'accepted', 'Revoked' ) );
ck( 'listed, both sides named', WPCPM_Institution_Agreement::discrepancies(), array( $rec_a => array( 'site_state' => 'accepted', 'airtable_status' => 'Revoked' ) ) );

reset_world();
seed_post( $rec_a, 'superseded', 'template' );
seed_post( $rec_a, 'revoked', 'template', array( WPCPM_Institution_Agreement::META_NOTE => 'ended' ) );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );

ck( 'a site-side revoke is not undone by a stale Accepted in the grid', $option['settled'], false );
ck( 'site_state revoked', $option['site_state'], 'revoked' );

reset_world();
seed_post( $rec_a, 'returned', 'own' );
seed_post( $rec_a, 'withdrawn', 'own' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Returned' ) );

ck( 'a withdrawn re-upload after a return leaves the return standing', $option['site_state'], 'returned' );

reset_world();
seed_post( $rec_a, 'generated', 'template' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Template generated' ) );

ck( 'a generated post reads generated and names itself', array( $option['site_state'], $option['generated_id'] > 0 ), array( 'generated', true ) );

reset_world();
$older = seed_post( $rec_a, 'accepted', 'template', array( WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-01-10' ) );
$newer = seed_post( $rec_a, 'submitted', 'template' );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );

ck( 'a replacement in review sits beside the accepted one without unseating it',
    array( $option['settled'], $option['site_state'], $option['agreement_id'], $option['pending_id'] ), array( true, 'accepted', $older, $newer ) );

reset_world();
ck( 'a bad record ID writes nothing', WPCPM_Institution_Agreement::rebuild( 'nope', block( 'Accepted' ) ), array() );
ck( 'and stores nothing', $GLOBALS['opts'], array() );

/* ---- the grid route ----------------------------------------------------- */

echo "\n=== The grid route: On file and a Drive link ===\n";

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2024-03-15', 'accepted_on' => '2026-09-01' ) ) );

ck( 'settled', $option['settled'], true );
ck( 'site_state on_file, kind legacy', array( $option['site_state'], $option['kind'] ), array( 'on_file', 'legacy' ) );
ck( 'one legacy post materialised', shapes_of( $rec_a ), array( 'accepted/legacy' ) );

$legacy = get_post( $option['agreement_id'] );

ck( 'author 0', $legacy->post_author, 0 );
ck( 'private, of our type', array( $legacy->post_status, $legacy->post_type ), array( 'private', 'wpcpm_agreement' ) );
ck( 'the Drive link and the signed date from the block', array( get_post_meta( $legacy->ID, '_wpcpm_agr_drive_url', true ), get_post_meta( $legacy->ID, '_wpcpm_agr_signed_on', true ) ), array( $drive, '2024-03-15' ) );
ck( 'decided by nobody, on the date the base holds', array( get_post_meta( $legacy->ID, '_wpcpm_agr_decided_by', true ), get_post_meta( $legacy->ID, '_wpcpm_agr_decided_at', true ) ), array( 0, '2026-09-01' ) );
ck( 'one event, recorded in Airtable', array_map( function ( $e ) { return $e['event']; }, get_post_meta( $legacy->ID, '_wpcpm_agr_event' ) ), array( 'recorded in Airtable' ) );
ck( 'no file on a legacy row', get_post_meta( $legacy->ID, '_wpcpm_agr_file', true ), '' );
ck( 'the option carries the Drive link', $option['drive_url'], $drive );
ck( 'accepted_at is the base date', $option['accepted_at'], '2026-09-01' );

$summary = WPCPM_Institution_Agreement::summary( $rec_a );
ck( 'summary: on_file, legacy, route grid, the grid status', array( $summary['state'], $summary['kind'], $summary['route'], $summary['airtable_status'], $summary['agreement_id'] ), array( 'on_file', 'legacy', 'grid', 'On file', $legacy->ID ) );
ck( 'the predicate opens', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );

$again = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2024-03-15', 'accepted_on' => '2026-09-01' ) ) );
ck( 'a second rebuild with the same inputs creates no second post', shapes_of( $rec_a ), array( 'accepted/legacy' ) );
ck( 'and names the same post', $again['agreement_id'], $legacy->ID );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', '', array( 'kind' => 'Legacy' ) ) );
ck( 'On file with no link: locked', $option['settled'], false );
ck( 'no post created', shapes_of( $rec_a ), array() );
ck( 'listed: On file in the grid, nothing here', WPCPM_Institution_Agreement::discrepancies(), array( $rec_a => array( 'site_state' => 'none', 'airtable_status' => 'On file' ) ) );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', 'https://www.dropbox.com/s/abc/agreement.pdf' ) );
ck( 'On file with a link that is not Drive: locked, no post', array( $option['settled'], shapes_of( $rec_a ) ), array( false, array() ) );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', 'http://drive.google.com/file/d/abc' ) );
ck( 'On file with a plain http Drive link: locked, no post', array( $option['settled'], shapes_of( $rec_a ) ), array( false, array() ) );
ck( 'and the option does not carry it', $option['drive_url'], '' );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', 'https://docs.google.com/document/d/abc/edit' ) );
ck( 'docs.google.com is Drive too', array( $option['settled'], shapes_of( $rec_a ) ), array( true, array( 'accepted/legacy' ) ) );
ck( 'a legacy row with no dates is accepted today', get_post_meta( $option['agreement_id'], '_wpcpm_agr_decided_at', true ), gmdate( 'Y-m-d' ) );
ck( 'and carries an empty signed date rather than a wrong one', get_post_meta( $option['agreement_id'], '_wpcpm_agr_signed_on', true ), '' );

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive, array( 'signed_on' => '15/03/2024', 'accepted_on' => '2026-02-30' ) ) );
ck( 'dates the base could not have written are dropped, not stored', array( get_post_meta( $option['agreement_id'], '_wpcpm_agr_signed_on', true ), get_post_meta( $option['agreement_id'], '_wpcpm_agr_decided_at', true ) ), array( '', gmdate( 'Y-m-d' ) ) );

reset_world();
$uploaded = seed_post( $rec_a, 'accepted', 'own' );
$option   = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );
ck( 'On file over an accepted upload: settled on the upload, no legacy post beside it', array( $option['settled'], $option['agreement_id'], shapes_of( $rec_a ) ), array( true, $uploaded, array( 'accepted/own' ) ) );
ck( 'route is site: a person accepted it here', WPCPM_Institution_Agreement::summary( $rec_a )['route'], 'site' );

ck( 'is_drive_link() accepts the two hosts over https only',
    array(
    	WPCPM_Institution_Agreement::is_drive_link( $drive ),
    	WPCPM_Institution_Agreement::is_drive_link( 'https://docs.google.com/x' ),
    	WPCPM_Institution_Agreement::is_drive_link( 'https://www.drive.google.com/x' ),
    	WPCPM_Institution_Agreement::is_drive_link( 'http://drive.google.com/x' ),
    	WPCPM_Institution_Agreement::is_drive_link( 'drive.google.com/x' ),
    	WPCPM_Institution_Agreement::is_drive_link( '' ),
    	WPCPM_Institution_Agreement::is_drive_link( 'https://drive.google.com.evil.example/x' ),
    ),
    array( true, true, false, false, false, false, false ) );

/* ---- the TEST record, as Phase 1 demonstrates it ------------------------ */

echo "\n=== The TEST record's grid route ===\n";

// Found by its label rather than by its ID, so that a seed refreshed from a base where the
// TEST record has been replaced fails here rather than silently walking a record that is not
// in the fixture at all.
$test_id = '';
foreach ( $seed['institutions'] as $row ) {
	if ( isset( $row['name'] ) && 0 === strpos( (string) $row['name'], 'TEST' ) ) {
		$test_id = (string) $row['id'];
	}
}

ck( 'the seed holds exactly one record labelled TEST, and it is the one the module is built against', $test_id, 'recDdomg5W6h410JT' );
ck( 'and it is a Confirmed-stage-free record nobody will mistake for a partner', count( array_filter( $seed['institutions'], function ( $row ) { return 0 === strpos( (string) $row['name'], 'TEST' ); } ) ), 1 );

reset_world();
$first = WPCPM_Institution_Agreement::rebuild( $test_id, block( 'On file', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2026-09-02' ) ) );
ck( 'typing On file and a Drive link and pressing Refresh settles it', WPCPM_Institution_Agreement::is_settled( $test_id ), true );
ck( 'by the grid route', WPCPM_Institution_Agreement::summary( $test_id )['route'], 'grid' );

$second = WPCPM_Institution_Agreement::rebuild( $test_id, block( 'Revoked', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2026-09-02' ) ) );
ck( 'typing Revoked locks it on the next rebuild', WPCPM_Institution_Agreement::is_settled( $test_id ), false );
ck( 'the legacy post still stands, so the screen can say what disagrees', WPCPM_Institution_Agreement::discrepancies(), array( $test_id => array( 'site_state' => 'on_file', 'airtable_status' => 'Revoked' ) ) );
ck( 'no second post was made while locked', shapes_of( $test_id ), array( 'accepted/legacy' ) );
ck( 'the same post is named across both rebuilds', $first['agreement_id'] === $second['agreement_id'] && $second['agreement_id'] > 0, true );

$third = WPCPM_Institution_Agreement::rebuild( $test_id, block( 'On file', $drive, array( 'kind' => 'Legacy', 'signed_on' => '2026-09-02' ) ) );
ck( 'putting On file back reopens it without a new post', array( $third['settled'], shapes_of( $test_id ) ), array( true, array( 'accepted/legacy' ) ) );

/* ---- the lock ----------------------------------------------------------- */

echo "\n=== The lock ===\n";

reset_world();
$GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_a ] = time();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );

ck( 'a held lock skips: nothing written', $option, array() );
ck( 'no option stored', array_key_exists( 'wpcpm_agreement_' . $rec_a, $GLOBALS['opts'] ), false );
ck( 'no post materialised', shapes_of( $rec_a ), array() );
ck( 'the lock is left to its holder', $GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_a ] > 0, true );
ck( 'the institution stays locked meanwhile', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );

delete_option( 'wpcpm_agreement_lock_' . $rec_a );
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );
ck( 'once released, the same call writes', $option['settled'], true );

reset_world();
$GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_a ] = time() - WPCPM_Institution_Agreement::LOCK_TIMEOUT - 1;
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'Accepted' ) );
ck( 'a lock older than the timeout belonged to a dead request and is cleared', $option['site_state'], 'none' );
ck( 'and released again after', array_key_exists( 'wpcpm_agreement_lock_' . $rec_a, $GLOBALS['opts'] ), false );

reset_world();
$GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_a ] = time();
ck( 'a lock row is not listed as an institution', WPCPM_Institution_Agreement::discrepancies(), array() );

/* ---- rebuild_all, summary, delete_all ----------------------------------- */

echo "\n=== rebuild_all(), summary() and delete_all() ===\n";

reset_world();
$GLOBALS['opts']['wpcpm_agreement_drift'] = array( 'read' => 1 );
seed_post( $rec_b, 'accepted', 'template' );
$written = WPCPM_Institution_Agreement::rebuild_all( array(
	$rec_a => block( 'On file', $drive ),
	$rec_b => block( 'Accepted' ),
	$rec_c => block( 'Not started' ),
	'junk' => block( 'Accepted' ),
	$rec_a . 'x' => 'not a block',
) );

ck( 'three options written, the junk keys skipped', $written, 3 );
ck( 'each row reads as its inputs say',
    array( WPCPM_Institution_Agreement::is_settled( $rec_a ), WPCPM_Institution_Agreement::is_settled( $rec_b ), WPCPM_Institution_Agreement::is_settled( $rec_c ) ),
    array( true, true, false ) );
ck( 'Not started with nothing here is a queue row, not a discrepancy', WPCPM_Institution_Agreement::discrepancies(), array() );
ck( 'a sibling option under the prefix is neither listed nor touched', $GLOBALS['opts']['wpcpm_agreement_drift'], array( 'read' => 1 ) );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_c ] = array( 'v' => 99, 'site_state' => 'accepted', 'airtable_status' => 'Accepted' );
ck( 'an unreadable row is listed with whatever it holds, because the predicate locks it', WPCPM_Institution_Agreement::discrepancies(), array( $rec_c => array( 'site_state' => 'accepted', 'airtable_status' => 'Accepted' ) ) );
ck( 'and it is locked', WPCPM_Institution_Agreement::is_settled( $rec_c ), false );

$GLOBALS['opts'][ 'wpcpm_agreement_' . $rec_c ] = 'garbage';
ck( 'a row that is not an array is listed with empty sides', WPCPM_Institution_Agreement::discrepancies(), array( $rec_c => array( 'site_state' => '', 'airtable_status' => '' ) ) );

reset_world();
$empty = WPCPM_Institution_Agreement::summary( $rec_a );
ck( 'summary with nothing at all', $empty, array(
	'state'           => 'none',
	'kind'            => '',
	'accepted_at'     => '',
	'agreement_id'    => 0,
	'pending_id'      => 0,
	'generated_id'    => 0,
	'airtable_status' => '',
	'route'           => '',
) );

$revoked = seed_post( $rec_a, 'revoked', 'template' );
ck( 'summary after a site-side revoke deleted the option still says revoked', array( WPCPM_Institution_Agreement::summary( $rec_a )['state'], WPCPM_Institution_Agreement::is_settled( $rec_a ) ), array( 'revoked', false ) );

ck( 'posts_for() is newest first', array_map( function ( $p ) { return $p->ID; }, WPCPM_Institution_Agreement::posts_for( $rec_a ) ), array( $revoked ) );
ck( 'posts_for() refuses a bad ID', WPCPM_Institution_Agreement::posts_for( 'x' ), array() );

reset_world();
WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive ) );
WPCPM_Institution_Agreement::rebuild( $rec_b, block( 'Accepted' ) );
$GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_c ] = time();
$GLOBALS['opts']['wpcpm_settings'] = array( 'keep' => true );
WPCPM_Institution_Agreement::delete_all();

ck( 'delete_all() removes every option and lock under the prefix and every post', array( array_keys( $GLOBALS['opts'] ), $GLOBALS['posts'] ), array( array( 'wpcpm_settings' ), array() ) );

/* ---- the courtesy scan -------------------------------------------------- */

echo "\n=== inspect_pdf(): two refusals, five flags, and the two ways round a naive search ===\n";

$clean_pdf = pdf_bytes();

ck( 'a minimal PDF passes with nothing to report', WPCPM_Institution_Agreement::inspect_pdf( $clean_pdf ), array( 'ok' => true, 'reason' => '', 'flags' => array() ) );

$encrypted_pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R/Encrypt 5 0 R>>\n%%EOF\n";
$scan          = WPCPM_Institution_Agreement::inspect_pdf( $encrypted_pdf );

ck( '/Encrypt refuses, by name', array( $scan['ok'], $scan['reason'] ), array( false, 'agreement-encrypted' ) );

$launch_pdf = pdf_bytes( "5 0 obj<</Type/Action/S/Launch/F(calc.exe)>>endobj\n" );
$scan       = WPCPM_Institution_Agreement::inspect_pdf( $launch_pdf );

ck( '/Launch refuses, by name', array( $scan['ok'], $scan['reason'] ), array( false, 'agreement-launch' ) );

// The first of the two ways round a naive search: a PDF name may write any of its characters
// as `#` and two hex digits, so `/L#61unch` is `/Launch` to every reader in the world.
$escaped_pdf = pdf_bytes( "5 0 obj<</Type/Action/S/L#61unch/F(calc.exe)>>endobj\n" );
$scan        = WPCPM_Institution_Agreement::inspect_pdf( $escaped_pdf );

ck( '/L#61unch is /Launch and refuses too', array( $scan['ok'], $scan['reason'] ), array( false, 'agreement-launch' ) );
ck( 'and a raw search would have missed it', false === strpos( $escaped_pdf, '/Launch' ), true );

// The second: the whole object inside a compressed stream, where no text search reaches.
$hidden_pdf = pdf_bytes( pdf_flate_object( '<</Type/Action/S/Launch/F(calc.exe)>>' ) );
$scan       = WPCPM_Institution_Agreement::inspect_pdf( $hidden_pdf );

ck( 'a /Launch inside a FlateDecode stream refuses', array( $scan['ok'], $scan['reason'] ), array( false, 'agreement-launch' ) );
ck( 'and a raw search would have missed that too', false === strpos( $hidden_pdf, '/Launch' ), true );

$both_pdf = pdf_bytes( pdf_flate_object( '<</Type/Action/S/L#61unch/F(calc.exe)>>' ) );
$scan     = WPCPM_Institution_Agreement::inspect_pdf( $both_pdf );

ck( 'both tricks at once still refuses', array( $scan['ok'], $scan['reason'] ), array( false, 'agreement-launch' ) );

$raw_deflate = "4 0 obj<</Filter/FlateDecode>>stream\n" . gzdeflate( '<</S/Launch>>' ) . "\nendstream endobj\n";
$scan        = WPCPM_Institution_Agreement::inspect_pdf( pdf_bytes( $raw_deflate ) );

ck( 'a raw deflate stream with no zlib header is read as well', array( $scan['ok'], $scan['reason'] ), array( false, 'agreement-launch' ) );

$encrypted_stream = pdf_bytes( pdf_flate_object( '<</Encrypt 9 0 R>>' ) );

ck( '/Encrypt hidden in a stream refuses as well', WPCPM_Institution_Agreement::inspect_pdf( $encrypted_stream )['reason'], 'agreement-encrypted' );

$flagged = WPCPM_Institution_Agreement::inspect_pdf( pdf_bytes( "5 0 obj<</OpenAction 6 0 R/AA 7 0 R>>endobj\n6 0 obj<</S/JavaScript/JS(app.alert\\(1\\))>>endobj\n8 0 obj<</Type/Filespec/EmbeddedFile 9 0 R>>endobj\n" ) );

ck( 'the other five are flags and never a refusal', $flagged['ok'], true );
ck( 'and every one of them is named, in the order the constant lists them', $flagged['flags'], array( '/JavaScript', '/JS', '/OpenAction', '/AA', '/EmbeddedFile' ) );
ck( 'the flag names are the ones a reviewer would search for', WPCPM_Institution_Agreement::SCAN_FLAGS, array( '/JavaScript', '/JS', '/OpenAction', '/AA', '/EmbeddedFile' ) );

$lookalikes = WPCPM_Institution_Agreement::inspect_pdf( pdf_bytes( "5 0 obj<</S/JScript/X/AArgh/Y/Launcher>>endobj\n" ) );

ck( 'a name that merely begins with a flag is not that flag', $lookalikes, array( 'ok' => true, 'reason' => '', 'flags' => array() ) );

$bomb = pdf_bytes( "4 0 obj<</Filter/FlateDecode>>stream\n" . gzcompress( str_repeat( 'A', 3 * 1024 * 1024 ) . '/Launch' ) . "\nendstream endobj\n" );
$scan = WPCPM_Institution_Agreement::inspect_pdf( $bomb );

// The bound, asserted as a bound. A stream that would inflate past SCAN_MAX_STREAM is not
// read at all, so what it holds is not found: that is the price of never letting a few
// hundred bytes of zlib decide how much memory this process uses, and it is exactly why the
// docblock, the panel and the reviewer's checklist all call the scan a courtesy.
ck( 'a stream that would inflate past the budget is skipped rather than read', $scan['ok'], true );
ck( 'and nothing was flagged from it', $scan['flags'], array() );
ck( 'the budget is the one the constants name', array( WPCPM_Institution_Agreement::SCAN_MAX_STREAM, WPCPM_Institution_Agreement::SCAN_MAX_TOTAL, WPCPM_Institution_Agreement::SCAN_MAX_STREAMS ), array( 2097152, 8388608, 200 ) );

ck( 'an empty file is nothing to report rather than a crash', WPCPM_Institution_Agreement::inspect_pdf( '' ), array( 'ok' => true, 'reason' => '', 'flags' => array() ) );

/* ---- the upload's refusals ---------------------------------------------- */

echo "\n=== handle_upload(): every refusal names its reason, and nothing is stored ===\n";

/**
 * A member of Universidad Example, signed in, with a good PDF on the form.
 *
 * @param string $record Institutions record ID.
 */
function upload_world( $record = 'recAAAAAAAAAAAAA1' ) {
	reset_world();
	seed_index( $record, 'Universidad Example' );
	sign_in( 4, 'Ola Member', 'ola@example.edu' );
	$GLOBALS['members'][ $record ] = array( 4, 5 );
	$GLOBALS['users'][5]           = new WP_User( 5, 'Bo Nowak', 'bo@example.edu' );
	$GLOBALS['managers']           = array( new WP_User( 1, 'Program Manager', 'pm@example.org' ) );
	$GLOBALS['referer']            = 'https://example.test/institution-dashboard/';
	$_POST                         = array(
		'wpcpm_agreement_signed' => '1',
		'wpcpm_agreement_kind'   => 'template',
	);
	post_file( temp_file( pdf_bytes() ) );
}

upload_world();
$GLOBALS['uid'] = 0;
ck( 'nobody signed in dies rather than redirecting', run( 'handle_upload' ), 'died: You need to be signed in to upload an agreement.' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
$GLOBALS['members'] = array();
ck( 'an account the fence refuses gets the one refusal', run( 'handle_upload' ), 'died: That record is not on your roster.' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
$_POST['wpcpm_agreement_signed'] = '0';
ck( 'an unticked declaration refuses', run( 'handle_upload' ), 'agreement-declare' );
unset( $_POST['wpcpm_agreement_signed'] );
ck( 'and so does an absent one', run( 'handle_upload' ), 'agreement-declare' );
$_POST['wpcpm_agreement_signed'] = 'yes';
ck( 'and so does anything else', run( 'handle_upload' ), 'agreement-declare' );
ck( 'nothing reached the store on any of the three', $GLOBALS['stored'], array() );

upload_world();
$_POST['wpcpm_agreement_kind'] = 'legacy';
ck( 'the legacy kind is not one a form may declare', run( 'handle_upload' ), 'agreement-kind' );
$_POST['wpcpm_agreement_kind'] = 'whatever';
ck( 'nor is a kind nobody has heard of', run( 'handle_upload' ), 'agreement-kind' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
seed_post( 'recAAAAAAAAAAAAA1', 'submitted', 'own' );
ck( 'one in review at a time', run( 'handle_upload' ), 'agreement-in-review' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
post_file( temp_file( pdf_bytes() ), 'agreement.pdf', 12 * 1024 * 1024 );
ck( 'twelve megabytes over a ten megabyte setting refuses', run( 'handle_upload' ), 'agreement-too-big' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );
ck( 'but the ceiling was claimed first, before ten megabytes would have been read', $GLOBALS['claims'], array( array( 'agreement-upload:recAAAAAAAAAAAAA1', 5, 86400 ) ) );

upload_world();
$GLOBALS['settings']['agreement_max_mb'] = 1;
post_file( temp_file( pdf_bytes( str_repeat( "% padding\n", 200000 ) ) ), 'agreement.pdf', 1000 );
ck( 'a small reported size over a large file on disk refuses on the file', run( 'handle_upload' ), 'agreement-too-big' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
post_file( temp_file( pdf_bytes() ), 'agreement.pdf', 0, UPLOAD_ERR_NO_FILE );
ck( 'no file chosen says so', run( 'handle_upload' ), 'agreement-no-file' );
$_FILES = array();
ck( 'and so does a form with no file field at all', run( 'handle_upload' ), 'agreement-no-file' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
post_file( temp_file( "MZ\x90\x00\x03\x00\x00\x00binary rubbish" ), 'agreement.pdf' );
ck( 'an executable renamed .pdf refuses', run( 'handle_upload' ), 'agreement-not-pdf' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
$GLOBALS['filetype'] = array( 'ext' => 'pdf', 'type' => 'application/pdf' );
post_file( temp_file( "MZ\x90\x00\x03\x00\x00\x00binary rubbish" ), 'agreement.pdf' );
ck( 'and if the name check were somehow passed, the first five bytes refuse it', run( 'handle_upload' ), 'agreement-not-pdf' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
post_file( temp_file( $encrypted_pdf ), 'agreement.pdf' );
ck( 'an encrypted PDF refuses', run( 'handle_upload' ), 'agreement-encrypted' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
post_file( temp_file( $both_pdf ), 'agreement.pdf' );
ck( 'a /Launch hidden in a compressed stream and spelled with an escape refuses', run( 'handle_upload' ), 'agreement-launch' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );

upload_world();
$GLOBALS['store_fails'] = true;
ck( 'a store that cannot write says so rather than pretending', run( 'handle_upload' ), 'agreement-file' );
ck( 'no post was left pointing at a file that is not there', shapes_of( 'recAAAAAAAAAAAAA1' ), array() );

upload_world();
for ( $i = 0; $i < 5; $i++ ) {
	WPCPM_Ceiling::claim( 'agreement-upload:recAAAAAAAAAAAAA1', 5, DAY_IN_SECONDS );
}
ck( 'the sixth upload in a day refuses', run( 'handle_upload' ), 'agreement-busy' );
ck( 'and nothing reached the store', $GLOBALS['stored'], array() );
ck( 'the ceiling was decided before the declaration, the kind or the file', $GLOBALS['journal'], array( 'ceiling', 'ceiling', 'ceiling', 'ceiling', 'ceiling', 'ceiling' ) );

upload_world();
$GLOBALS['settings']['agreement_uploads_per_day'] = 2;
WPCPM_Ceiling::claim( 'agreement-upload:recAAAAAAAAAAAAA1', 2, DAY_IN_SECONDS );
WPCPM_Ceiling::claim( 'agreement-upload:recAAAAAAAAAAAAA1', 2, DAY_IN_SECONDS );
ck( 'the setting is what the ceiling reads, not the default', run( 'handle_upload' ), 'agreement-busy' );

/* ---- a good upload ------------------------------------------------------ */

echo "\n=== handle_upload(): what a good one leaves behind ===\n";

upload_world();
$generated = seed_post( 'recAAAAAAAAAAAAA1', 'generated', 'template', array( WPCPM_Institution_Agreement::META_TEMPLATE_VERSION => '2025-11-04' ) );
post_file( temp_file( pdf_bytes( "5 0 obj<</OpenAction 6 0 R>>endobj\n" ) ), 'Umowa podpisana.pdf' );
$outcome = run( 'handle_upload' );

ck( 'the outcome is the one the panel prints', $outcome, 'agreement-uploaded' );
ck( 'the nonce was keyed to the institution', $GLOBALS['nonces'], array( 'wpcpm_agreement_upload_recAAAAAAAAAAAAA1' ) );
ck( 'the fence was asked about that institution', $GLOBALS['decisions'], array( array( 'agreement', 'recAAAAAAAAAAAAA1' ) ) );

$summary = WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' );
$new_id  = $summary['pending_id'];

ck( 'the document is in review', array( $summary['state'], $new_id > 0 ), array( 'submitted', true ) );
ck( 'the generated template it answers is history now', states_of( 'recAAAAAAAAAAAAA1' ), array( 'superseded', 'submitted' ) );
ck( 'and its version came with it, so the reviewer can compare footers', get_post_meta( $new_id, '_wpcpm_agr_template_version', true ), '2025-11-04' );
ck( 'the generated post says why it was retired', array_map( function ( $e ) { return $e['event']; }, get_post_meta( $generated, '_wpcpm_agr_event' ) ), array( 'superseded by a newer document' ) );

$file = get_post_meta( $new_id, '_wpcpm_agr_file', true );

ck( 'the path, the checksum and the size are recorded', array( array_keys( $file ), 64 === strlen( $file['sha256'] ), $file['size'] === strlen( $GLOBALS['stored'][0]['bytes'] ) ), array( array( 'path', 'sha256', 'size' ), true, true ) );
ck( 'the store was given the plaintext and told it is a pdf', $GLOBALS['stored'][0]['extension'], 'pdf' );
ck( 'the original name is kept for display and is not the one on disk', array( get_post_meta( $new_id, '_wpcpm_agr_original_name', true ), false !== strpos( $file['path'], 'Umowa' ) ), array( 'Umowa-podpisana.pdf', false ) );
ck( 'what the scan noticed is on the record for the reviewer', get_post_meta( $new_id, '_wpcpm_agr_flags', true ), array( '/OpenAction' ) );
ck( 'the uploader is the author', get_post( $new_id )->post_author, 4 );
ck( 'private, of our type', array( get_post( $new_id )->post_status, get_post( $new_id )->post_type ), array( 'private', 'wpcpm_agreement' ) );
ck( 'one event row says what happened', array_map( function ( $e ) { return $e['event']; }, get_post_meta( $new_id, '_wpcpm_agr_event' ) ), array( 'signed copy uploaded' ) );

ck( 'one PATCH, and only one', count( $GLOBALS['patched'] ), 1 );
ck( 'it names the record', $GLOBALS['patched'][0][1][0]['id'], 'recAAAAAAAAAAAAA1' );
ck( 'and carries exactly T3\'s three cells', patched_cells(), array(
	'Agreement Submitted On' => gmdate( 'Y-m-d' ),
	'Agreement Status'       => 'Awaiting review',
	'Agreement Kind'         => 'Program template',
) );
ck( 'no pending flag, because it landed', get_post_meta( $new_id, '_wpcpm_agr_airtable_pending', true ), '' );

$option = WPCPM_Institution_Agreement::option( 'recAAAAAAAAAAAAA1' );

ck( 'the option knows both sides, and the gate stays shut', array( $option['site_state'], $option['airtable_status'], $option['settled'] ), array( 'submitted', 'Awaiting review', false ) );

ck( 'every member was told once, and the managers once', mail_log(), array(
	'agreement-received to ola@example.edu',
	'agreement-received to bo@example.edu',
	'agreement-landed to pm@example.org',
) );
ck( 'the managers\' subject is the one the design writes out', $GLOBALS['mail'][2]['subject'], '[WPCredits] Signed agreement from Universidad Example is waiting for review' );
ck( 'and their message says the scan is a courtesy, in the same breath as what it found', array(
	false !== strpos( $GLOBALS['mail'][2]['body'], '/OpenAction' ),
	false !== strpos( $GLOBALS['mail'][2]['body'], 'a courtesy and not evidence' ),
), array( true, true ) );
ck( 'the members are told when to expect an answer and how to take it back', array(
	false !== strpos( $GLOBALS['mail'][0]['body'], 'within 3 working days' ),
	false !== strpos( $GLOBALS['mail'][0]['body'], 'withdraw it' ),
), array( true, true ) );

ck( 'one audit row, on the member ground, naming the document', array(
	count( $GLOBALS['audit'] ),
	$GLOBALS['audit'][0]['kind'],
	$GLOBALS['audit'][0]['ground'],
	$GLOBALS['audit'][0]['evidence'],
	$GLOBALS['audit'][0]['institution'],
	$GLOBALS['audit'][0]['subject'],
	$GLOBALS['audit'][0]['actor'],
), array( 1, 'agreement_upload', 'member', 'index', 'recAAAAAAAAAAAAA1', (string) $new_id, 4 ) );
ck( 'and the ceiling came before the store, which came before Airtable', array_slice( $GLOBALS['journal'], 0, 3 ), array( 'ceiling', 'store', 'airtable' ) );

/* ---- an institution's own paper, uploaded after a generate -------------- */

echo "\n=== handle_upload(): an own document inherits no template version ===\n";

// An institution that generated the program's template and then sent its lawyers' own paper
// instead. The generated row is answered either way, but the version on it belongs to a
// document nobody signed: carried onto the upload it would reach `handle_accept()`, which
// writes it into `Agreement Template Version` beside `Agreement Kind` = `Institution-specific`.

upload_world();
$answered                      = seed_post( 'recAAAAAAAAAAAAA1', 'generated', 'template', array( WPCPM_Institution_Agreement::META_TEMPLATE_VERSION => '2025-11-04' ) );
$_POST['wpcpm_agreement_kind'] = 'own';
$outcome                       = run( 'handle_upload' );
$own_id                        = WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['pending_id'];

ck( 'the upload lands', array( $outcome, $own_id > 0 ), array( 'agreement-uploaded', true ) );
ck( 'the generated template it walked away from is history all the same', states_of( 'recAAAAAAAAAAAAA1' ), array( 'superseded', 'submitted' ) );
ck( 'the retired row still says why', array_map( function ( $e ) { return $e['event']; }, get_post_meta( $answered, '_wpcpm_agr_event' ) ), array( 'superseded by a newer document' ) );
ck( 'but no version came with it: an own document was cut from no template, and the retired row keeps its own', array( get_post_meta( $own_id, '_wpcpm_agr_template_version', true ), get_post_meta( $answered, '_wpcpm_agr_template_version', true ) ), array( '', '2025-11-04' ) );
ck( 'and the base is told whose paper it is', patched_cells()['Agreement Kind'], 'Institution-specific' );

/* ---- Airtable is not allowed to fail the upload ------------------------- */

echo "\n=== handle_upload(): a base that is down does not fail an institution's upload ===\n";

upload_world();
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Service Unavailable' );
$outcome             = run( 'handle_upload' );
$pending             = WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['pending_id'];

ck( 'the upload still lands', array( $outcome, $pending > 0 ), array( 'agreement-uploaded', true ) );
ck( 'and is flagged for the sync to retry', get_post_meta( $pending, '_wpcpm_agr_airtable_pending', true ), 1 );
ck( 'the option does not claim the base was told', WPCPM_Institution_Agreement::option( 'recAAAAAAAAAAAAA1' )['airtable_status'], '' );
ck( 'the members and the managers were still told', count( $GLOBALS['mail'] ), 3 );

upload_world();
$GLOBALS['airtable'] = array();
run( 'handle_upload' );
$pending = WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['pending_id'];

ck( 'an empty answer is a failure too, not a success', get_post_meta( $pending, '_wpcpm_agr_airtable_pending', true ), 1 );

/* ---- T10: a replacement beside an accepted agreement -------------------- */

echo "\n=== handle_upload(): a replacement leaves the copy in force alone (T10) ===\n";

upload_world();
$standing = seed_post( 'recAAAAAAAAAAAAA1', 'accepted', 'own', array( WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-01-10' ) );
WPCPM_Institution_Agreement::rebuild( 'recAAAAAAAAAAAAA1', array( 'status' => 'Accepted' ) );
$outcome = run( 'handle_upload' );

ck( 'the upload lands', $outcome, 'agreement-uploaded' );
ck( 'the accepted copy is untouched and still in force', array( WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['agreement_id'], WPCPM_Institution_Agreement::is_settled( 'recAAAAAAAAAAAAA1' ) ), array( $standing, true ) );
ck( 'and the base was told the date and nothing else', patched_cells(), array( 'Agreement Submitted On' => gmdate( 'Y-m-d' ) ) );

/* ---- a manager uploading on behalf -------------------------------------- */

echo "\n=== handle_upload(): a manager on behalf, and a member who cannot pretend to be one ===\n";

reset_world();
seed_index( 'recAAAAAAAAAAAAA1', 'Universidad Example' );
seed_index( 'recBBBBBBBBBBBBB2', 'Politechnika Example' );
sign_in( 1, 'Program Manager', 'pm@example.org', true );
$GLOBALS['managers'] = array( $GLOBALS['users'][1] );
$_POST               = array(
	'wpcpm_agreement_signed' => '1',
	'wpcpm_agreement_kind'   => 'own',
	'wpcpm_agreement_record' => 'recBBBBBBBBBBBBB2',
);
post_file( temp_file( pdf_bytes() ) );

ck( 'a manager\'s posted record is honoured', run( 'handle_upload' ), 'agreement-uploaded' );
ck( 'and it is the one the nonce was keyed to', $GLOBALS['nonces'], array( 'wpcpm_agreement_upload_recBBBBBBBBBBBBB2' ) );
ck( 'the document sits under it', WPCPM_Institution_Agreement::summary( 'recBBBBBBBBBBBBB2' )['pending_id'] > 0, true );
ck( 'and nothing was written under the other institution', WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['pending_id'], 0 );
ck( 'the row says a manager did it', $GLOBALS['audit'][0]['ground'], 'manager' );

upload_world();
$_POST['wpcpm_agreement_record'] = 'recBBBBBBBBBBBBB2';
seed_index( 'recBBBBBBBBBBBBB2', 'Politechnika Example' );
run( 'handle_upload' );

ck( 'a member posting another institution\'s record is still their own institution', $GLOBALS['nonces'], array( 'wpcpm_agreement_upload_recAAAAAAAAAAAAA1' ) );
ck( 'and the document landed under theirs', array( WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['pending_id'] > 0, WPCPM_Institution_Agreement::summary( 'recBBBBBBBBBBBBB2' )['pending_id'] ), array( true, 0 ) );

/* ---- the download ------------------------------------------------------- */

echo "\n=== handle_download(): one route to the bytes, and it is never inline ===\n";

$child = array();
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --download-child', $child, $child_status );

// The body has to be read from a child process: a successful download ends in `exit`, which
// no harness can catch. What the child prints is the response body and nothing else.
ck( 'the child exited cleanly', $child_status, 0 );
ck( 'and what it sent is the decrypted file, byte for byte', implode( "\n", $child ), '%PDF-1.4 the signed copy' );

download_world();
$post_id = WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['agreement_id'];

ck( 'the member of that institution reaches it', $post_id > 0, true );

// The one refusal: another institution's document. The subject is the post's own stamp, so
// there is nothing on the request for the caller to change.
download_world();
$other = WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['agreement_id'];
$GLOBALS['members'] = array( 'recBBBBBBBBBBBBB2' => array( 4 ) );

ck( 'a member of B asking for A\'s document gets the one refusal', run( 'handle_download' ), 'died: That record is not on your roster.' );

$manager_child = array();
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --download-child --as-manager', $manager_child, $manager_status );

ck( 'a program manager on no roster reaches any of them', array( $manager_status, implode( "\n", $manager_child ) ), array( 0, '%PDF-1.4 the signed copy' ) );

download_world();
$GLOBALS['members'] = array();

ck( 'and without the capability the same request is the one refusal', run( 'handle_download' ), 'died: That record is not on your roster.' );

download_world();
$_GET['post'] = 99999;

ck( 'a document this site does not hold is a 404, not a hint', run( 'handle_download' ), 'died: That document is not one this site holds.' );

download_world();
$legacy = seed_post( 'recAAAAAAAAAAAAA1', 'accepted', 'legacy', array( WPCPM_Institution_Agreement::META_DRIVE_URL => $drive ) );
$_GET['post'] = $legacy;

ck( 'a legacy row has no file, and says so the same way', run( 'handle_download' ), 'died: That document has no file on this site.' );

download_world();
unset( $GLOBALS['files']['2026/deadbeef.pdf'] );

ck( 'a row pointing at bytes that are gone does not hand over an empty file', run( 'handle_download' ), 'died: That file could not be read. Tell a program manager.' );

$signed_out     = download_world();
$GLOBALS['uid'] = 0;

ck( 'a signed-out reader is sent to log in and back, not refused', run( 'handle_download' ), 'redirect: https://example.test/wp-login.php?redirect_to=' . rawurlencode( 'https://example.test/wp-admin/admin-post.php?action=wpcpm_agreement_download&post=' . $signed_out . '&_wpnonce=irrelevant-here' ) );

// PHP under CLI discards response headers, so the six the design lists are asserted against
// the source. They are the whole of the reviewer's protection and none of them is optional.
$body = method_body( file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' ), 'handle_download' );

foreach ( array(
	"header( 'Content-Type: application/pdf' );",
	"header( 'X-Content-Type-Options: nosniff' );",
	"header( \"Content-Security-Policy: default-src 'none'; sandbox\" );",
	"header( 'Cache-Control: private, no-store' );",
	"header( 'Content-Length: ' . strlen( \$bytes ) );",
	'nocache_headers();',
) as $line ) {
	ck( 'the response carries ' . trim( $line ), false !== strpos( $body, $line ), true );
}

ck( 'the disposition is an attachment, never inline', false !== strpos( $body, "header( 'Content-Disposition: attachment; filename=\"' . self::download_name( \$post ) . '\"' );" ), true );
ck( 'and the name it offers is built from the institution and the date, never from the upload',
	array(
		false !== strpos( method_body( file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' ), 'download_name' ), 'sanitize_file_name' ),
		false !== strpos( method_body( file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' ), 'download_name' ), 'META_ORIGINAL_NAME' ),
	),
	array( true, false ) );

/* ---- accept ------------------------------------------------------------- */

echo "\n=== handle_accept(): one PATCH, read live, and the gate opens on this request ===\n";

/**
 * A submitted document from Universidad Example, with a manager signed in to read it.
 *
 * @param string $kind  Which kind the uploader declared.
 * @param string $stage What the base says `Current Stage` is right now.
 * @return int The submitted post's ID.
 */
function review_world( $kind = 'template', $stage = 'Under Review' ) {
	reset_world();
	seed_index( 'recAAAAAAAAAAAAA1', 'Universidad Example' );
	sign_in( 1, 'Kasia Manager', 'kasia@example.org', true );
	$GLOBALS['members']['recAAAAAAAAAAAAA1'] = array( 4, 5 );
	$GLOBALS['users'][4]                     = new WP_User( 4, 'Ola Member', 'ola@example.edu' );
	$GLOBALS['users'][5]                     = new WP_User( 5, 'Bo Nowak', 'bo@example.edu' );
	$GLOBALS['managers']                     = array( $GLOBALS['users'][1] );
	$GLOBALS['live']                         = array( 'Current Stage' => $stage );

	$id = seed_post(
		'recAAAAAAAAAAAAA1',
		'submitted',
		$kind,
		array(
			WPCPM_Institution_Agreement::META_FILE             => array(
				'path'   => '2026/deadbeef.pdf',
				'size'   => 240128,
				'sha256' => str_repeat( 'b', 64 ),
			),
			WPCPM_Institution_Agreement::META_TEMPLATE_VERSION => '2025-11-04',
			WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT => 'Universidad Example Sede Central',
		)
	);

	$GLOBALS['files']['2026/deadbeef.pdf'] = '%PDF-1.4 signed';
	$_POST                                 = array( 'wpcpm_agreement_post' => $id );

	WPCPM_Institution_Agreement::rebuild( 'recAAAAAAAAAAAAA1', array( 'status' => 'Awaiting review' ) );

	return $id;
}

$id = review_world();
$GLOBALS['caps'] = false;
ck( 'a member cannot accept their own agreement', run( 'handle_accept' ), 'died: You do not have permission to manage the program.' );
ck( 'and nothing was written', $GLOBALS['patched'], array() );

$id = review_world( 'template', 'Not Moving Forward' );
ck( 'a terminal stage refuses acceptance', run( 'handle_accept' ), 'agreement-stage' );
ck( 'and nothing was written', $GLOBALS['patched'], array() );
ck( 'the lock was released', array_key_exists( 'wpcpm_agreement_lock_recAAAAAAAAAAAAA1', $GLOBALS['opts'] ), false );

foreach ( WPCPM_Institution_Agreement::TERMINAL_STAGES as $terminal ) {
	review_world( 'template', $terminal );
	ck( "the stage $terminal refuses by name", run( 'handle_accept' ), 'agreement-stage' );
}

$id            = review_world();
$GLOBALS['live'] = new WP_Error( 'wpcpm_airtable_http', 'Service Unavailable' );
ck( 'a record that cannot be read is not written', run( 'handle_accept' ), 'agreement-airtable' );
ck( 'and nothing was patched', $GLOBALS['patched'], array() );

$id                  = review_world();
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Service Unavailable' );
ck( 'a PATCH that fails refuses the whole acceptance', run( 'handle_accept' ), 'agreement-airtable' );
ck( 'the document is still in review', WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['state'], 'submitted' );
ck( 'the gate is still shut', WPCPM_Institution_Agreement::is_settled( 'recAAAAAAAAAAAAA1' ), false );
ck( 'and nobody was told anything', $GLOBALS['mail'], array() );

$id      = review_world();
$outcome = run( 'handle_accept' );

ck( 'the outcome is the one the panel prints', $outcome, 'agreement-accepted' );
ck( 'the nonce was keyed to the document', $GLOBALS['nonces'], array( 'wpcpm_agreement_accept_' . $id ) );
ck( 'the record was read live before it was written', array_slice( $GLOBALS['journal'], 0, 2 ), array( 'airtable-read', 'airtable' ) );
ck( 'one PATCH, and only one', count( $GLOBALS['patched'] ), 1 );
ck( 'carrying the five agreement cells and the stage', patched_cells(), array(
	'Agreement Status'           => 'Accepted',
	'Agreement Kind'             => 'Program template',
	'Agreement Accepted On'      => gmdate( 'Y-m-d' ),
	'Agreement Accepted By'      => 'Kasia Manager',
	'Agreement Template Version' => '2025-11-04',
	'Current Stage'              => 'Confirmed',
) );
ck( 'the document is accepted and dated', array(
	get_post_meta( $id, '_wpcpm_agr_state', true ),
	get_post_meta( $id, '_wpcpm_agr_decided_by', true ),
	get_post_meta( $id, '_wpcpm_agr_decided_at', true ),
), array( 'accepted', 1, gmdate( 'Y-m-d' ) ) );
ck( 'the gate is open on this request, with no sync in between', WPCPM_Institution_Agreement::is_settled( 'recAAAAAAAAAAAAA1' ), true );
ck( 'and the option says why', array( WPCPM_Institution_Agreement::option( 'recAAAAAAAAAAAAA1' )['site_state'], WPCPM_Institution_Agreement::option( 'recAAAAAAAAAAAAA1' )['airtable_status'] ), array( 'accepted', 'Accepted' ) );
ck( 'every member was told, once each, and nobody else', mail_log(), array( 'agreement-accepted to ola@example.edu', 'agreement-accepted to bo@example.edu' ) );
ck( 'the message says the account is open and where to go', array(
	false !== strpos( $GLOBALS['mail'][0]['body'], 'Your account on the site is open.' ),
	false !== strpos( $GLOBALS['mail'][0]['body'], 'https://example.test/institution-dashboard/' ),
), array( true, true ) );
ck( 'the audit row was made against what was read live', array( $GLOBALS['audit'][0]['kind'], $GLOBALS['audit'][0]['ground'], $GLOBALS['audit'][0]['evidence'] ), array( 'agreement_accept', 'manager', 'live' ) );
ck( 'the lock was released', array_key_exists( 'wpcpm_agreement_lock_recAAAAAAAAAAAAA1', $GLOBALS['opts'] ), false );
ck( 'a second press finds nothing to accept', run( 'handle_accept' ), 'agreement-gone' );
ck( 'and wrote nothing further', count( $GLOBALS['patched'] ), 1 );

// The fixture seeds a template version on both kinds on purpose: it stands for a row a site
// running an earlier build uploaded, when an own document could inherit the version of the
// template it replaced. The cell is written all the same, empty, because that is what clears
// a version the base is still holding beside `Institution-specific`.
$id = review_world( 'own', 'Confirmed' );
run( 'handle_accept' );
ck( 'a stage already at Confirmed is left alone', array_key_exists( 'Current Stage', patched_cells() ), false );
ck( 'and the institution\'s own paper is named as such', patched_cells()['Agreement Kind'], 'Institution-specific' );
ck( 'with no template version beside it, though the row carries one', array( array_key_exists( 'Agreement Template Version', patched_cells() ), patched_cells()['Agreement Template Version'] ), array( true, '' ) );

$id = review_world( 'template', 'Student' );
run( 'handle_accept' );
ck( 'a stage past Confirmed is never walked back', array_key_exists( 'Current Stage', patched_cells() ), false );

$id = review_world( 'template', '' );
run( 'handle_accept' );
ck( 'an empty stage counts as preceding, so it is set', patched_cells()['Current Stage'], 'Confirmed' );

$id = review_world( 'template', 'Something Nobody Configured' );
run( 'handle_accept' );
ck( 'a stage this list does not know is not guessed at', array_key_exists( 'Current Stage', patched_cells() ), false );
ck( 'but the acceptance still lands', WPCPM_Institution_Agreement::is_settled( 'recAAAAAAAAAAAAA1' ), true );

$id  = review_world();
$old = seed_post( 'recAAAAAAAAAAAAA1', 'accepted', 'template', array( WPCPM_Institution_Agreement::META_DECIDED_AT => '2025-02-02' ) );
run( 'handle_accept' );

ck( 'the copy that was in force becomes history', get_post_meta( $old, '_wpcpm_agr_state', true ), 'superseded' );
ck( 'and the new one is the agreement', WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['agreement_id'], $id );

$id                                   = review_world();
$GLOBALS['opts']['wpcpm_agreement_lock_recAAAAAAAAAAAAA1'] = time();
ck( 'a held lock stops an acceptance rather than racing it', run( 'handle_accept' ), 'agreement-busy' );
ck( 'and nothing was read or written', $GLOBALS['journal'], array() );

// And the lock taken in the one gap the handler cannot hold: it lets go before the rebuild,
// which takes the same lock, so a sync working through this record gets in and the rebuild
// skips. Both writes have landed and the gate is still shut, and the manager has to be told
// that rather than "the account is open", because a manager who reads that does not press
// Refresh. The handler's own claim is the first from here; the rebuild's is the second.
$id                     = review_world();
$GLOBALS['lock_claims'] = 0;
$GLOBALS['lock_steal']  = 2;
$outcome                = run( 'handle_accept' );

ck( 'a rebuild that finds the lock held is not reported as an open account', $outcome, 'agreement-later' );
ck( 'the acceptance itself stands: one PATCH, and the document is accepted', array( count( $GLOBALS['patched'] ), get_post_meta( $id, '_wpcpm_agr_state', true ) ), array( 1, 'accepted' ) );
ck( 'the gate is shut until the next sync writes the option', WPCPM_Institution_Agreement::is_settled( 'recAAAAAAAAAAAAA1' ), false );
ck( 'and every member was told all the same, because nothing sends that message later', mail_log(), array( 'agreement-accepted to ola@example.edu', 'agreement-accepted to bo@example.edu' ) );

/* ---- return ------------------------------------------------------------- */

echo "\n=== handle_return(): a note, verbatim, with somebody to reply to ===\n";

$id                            = review_world();
$_POST['wpcpm_agreement_note'] = 'too short';
ck( 'a note under twenty characters refuses', run( 'handle_return' ), 'agreement-note' );
ck( 'and nothing was written', array( $GLOBALS['patched'], get_post_meta( $id, '_wpcpm_agr_state', true ) ), array( array(), 'submitted' ) );

$_POST['wpcpm_agreement_note'] = str_repeat( 'a', 2001 );
ck( 'and so does one over two thousand', run( 'handle_return' ), 'agreement-note' );

$_POST['wpcpm_agreement_note'] = str_repeat( 'a', 2000 );
ck( 'two thousand exactly is allowed', run( 'handle_return' ), 'agreement-returned' );

$id                            = review_world();
$note                          = "Page 3 is not signed, and the rector's name is spelled Examplle.\n\nPlease send the corrected copy.";
$_POST['wpcpm_agreement_note'] = $note;
$outcome                       = run( 'handle_return' );

ck( 'the outcome is the one the panel prints', $outcome, 'agreement-returned' );
ck( 'the base is told', patched_cells(), array( 'Agreement Status' => 'Returned' ) );
ck( 'the document is returned, dated and signed', array(
	get_post_meta( $id, '_wpcpm_agr_state', true ),
	get_post_meta( $id, '_wpcpm_agr_decided_by', true ),
	get_post_meta( $id, '_wpcpm_agr_note', true ),
), array( 'returned', 1, $note ) );
ck( 'the panel will read the same words back', WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['state'], 'returned' );
ck( 'every member got the note, once each', mail_log(), array( 'agreement-returned to ola@example.edu', 'agreement-returned to bo@example.edu' ) );
ck( 'word for word', false !== strpos( $GLOBALS['mail'][0]['body'], $note ), true );
ck( 'naming the manager who wrote it', false !== strpos( $GLOBALS['mail'][0]['body'], 'Kasia Manager' ), true );
ck( 'and replying reaches them', $GLOBALS['mail'][0]['headers'], array( 'Reply-To: "Kasia Manager" <kasia@example.org>' ) );
ck( 'one audit row, on the manager ground', array( $GLOBALS['audit'][0]['kind'], $GLOBALS['audit'][0]['ground'] ), array( 'agreement_return', 'manager' ) );
ck( 'the lock was released', array_key_exists( 'wpcpm_agreement_lock_recAAAAAAAAAAAAA1', $GLOBALS['opts'] ), false );

$id                            = review_world();
$standing                      = seed_post( 'recAAAAAAAAAAAAA1', 'accepted', 'own', array( WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-01-10' ) );
$_POST['wpcpm_agreement_note'] = 'This replacement is missing the annexe, please send it again.';
run( 'handle_return' );

ck( 'returning a replacement does not tell the base the copy in force was returned', $GLOBALS['patched'], array() );
ck( 'and that copy is still in force', WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['agreement_id'], $standing );
ck( 'while the replacement is returned', get_post_meta( $id, '_wpcpm_agr_state', true ), 'returned' );

/* ---- withdraw ----------------------------------------------------------- */

echo "\n=== handle_withdraw(): the file goes at once, and nobody is emailed ===\n";

$id                 = review_world();
$GLOBALS['caps']    = false;
$GLOBALS['members'] = array( 'recBBBBBBBBBBBBB2' => array( 4 ) );
$GLOBALS['uid']     = 4;
ck( 'a member of B withdrawing A\'s document gets the one refusal', run( 'handle_withdraw' ), 'died: That record is not on your roster.' );
ck( 'and the file is still there', array_keys( $GLOBALS['files'] ), array( '2026/deadbeef.pdf' ) );

$id                 = review_world();
$GLOBALS['caps']    = false;
$GLOBALS['uid']     = 4;
$outcome            = run( 'handle_withdraw' );

ck( 'a member withdraws their own', $outcome, 'agreement-withdrawn' );
ck( 'the file is gone, at once', array( $GLOBALS['files'], $GLOBALS['forgotten'] ), array( array(), array( '2026/deadbeef.pdf' ) ) );
ck( 'and nothing points at it any more', get_post_meta( $id, '_wpcpm_agr_file', true ), '' );
ck( 'the post is kept, withdrawn', array( get_post_meta( $id, '_wpcpm_agr_state', true ), get_post( $id ) instanceof WP_Post ), array( 'withdrawn', true ) );
ck( 'nobody was emailed', $GLOBALS['mail'], array() );
ck( 'the base was not written to either', $GLOBALS['patched'], array() );
ck( 'but the summary was recomputed', array( WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['state'], WPCPM_Institution_Agreement::summary( 'recAAAAAAAAAAAAA1' )['pending_id'] ), array( 'none', 0 ) );
ck( 'one audit row, on the member ground', array( $GLOBALS['audit'][0]['kind'], $GLOBALS['audit'][0]['ground'], $GLOBALS['audit'][0]['actor'] ), array( 'agreement_withdraw', 'member', 4 ) );
ck( 'a second press finds nothing to withdraw', run( 'handle_withdraw' ), 'agreement-gone' );

$id = review_world();
ck( 'a manager withdraws on their behalf too', run( 'handle_withdraw' ), 'agreement-withdrawn' );
ck( 'and released the lock behind them', array_key_exists( 'wpcpm_agreement_lock_recAAAAAAAAAAAAA1', $GLOBALS['opts'] ), false );

$id = review_world();
$GLOBALS['opts']['wpcpm_agreement_lock_recAAAAAAAAAAAAA1'] = time();
ck( 'a withdrawal during an acceptance waits rather than deleting the file under it', run( 'handle_withdraw' ), 'agreement-busy' );
ck( 'and the file is still there for the reviewer', array_keys( $GLOBALS['files'] ), array( '2026/deadbeef.pdf' ) );

// The three handlers read the state again once the lock is theirs, which is the only way a
// document cannot be accepted and withdrawn in the same second.
$source_now = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );

foreach ( array( 'handle_accept', 'handle_return', 'handle_withdraw' ) as $handler ) {
	$body_now = method_body( $source_now, $handler );
	ck( $handler . '() reads the state again once the lock is its own', strpos( $body_now, 'self::lock(' ) < strrpos( $body_now, 'self::submitted_post( $post_id )' ), true );
}

/* ---- revoke ------------------------------------------------------------- */

echo "\n=== handle_revoke(): Airtable first, the option deleted, and the gate shut on this request (T8) ===\n";

/**
 * An institution whose agreement is in force, with a manager signed in to take it away.
 *
 * Both sides of the option agree, two members are there to be told, and the note box holds
 * something long enough to pass, so every assertion below starts from an open account and can
 * say which of the things that closes it happened.
 *
 * @param string $kind Which kind the accepted document is.
 * @return int The accepted document's ID.
 */
function settled_world( $kind = 'template' ) {
	reset_world();
	seed_index( 'recAAAAAAAAAAAAA1', 'Universidad Example', 'Confirmed' );
	sign_in( 1, 'Kasia Manager', 'kasia@example.org', true );
	$GLOBALS['members']['recAAAAAAAAAAAAA1'] = array( 4, 5 );
	$GLOBALS['users'][4]                     = new WP_User( 4, 'Ola Member', 'ola@example.edu' );
	$GLOBALS['users'][5]                     = new WP_User( 5, 'Bo Nowak', 'bo@example.edu' );
	$GLOBALS['managers']                     = array( $GLOBALS['users'][1] );

	$id = seed_post(
		'recAAAAAAAAAAAAA1',
		'accepted',
		$kind,
		array(
			WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-02-02',
			WPCPM_Institution_Agreement::META_FILE       => array(
				'path'   => '2026/deadbeef.pdf',
				'size'   => 240128,
				'sha256' => str_repeat( 'b', 64 ),
			),
		)
	);

	$GLOBALS['files']['2026/deadbeef.pdf'] = '%PDF-1.4 signed';

	$_POST = array(
		'wpcpm_agreement_post' => $id,
		'wpcpm_agreement_note' => 'The rector has asked us to pause every agreement the university holds until the privacy review is finished.',
	);

	// A legacy row settles under `On file` and a link; anything this site accepted settles
	// under `Accepted`. Both are the state the base is in before the revocation.
	$legacy = 'legacy' === $kind;

	WPCPM_Institution_Agreement::rebuild(
		'recAAAAAAAAAAAAA1',
		block( $legacy ? 'On file' : 'Accepted', $legacy ? 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz' : '' )
	);

	return $id;
}

$id = settled_world();
ck( 'the world starts with the gate open', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );

$GLOBALS['caps'] = false;
ck( 'a member cannot revoke their own institution\'s agreement', run( 'handle_revoke' ), 'died: You do not have permission to manage the program.' );
ck( 'and nothing was written', array( $GLOBALS['patched'], WPCPM_Institution_Agreement::is_settled( $rec_a ) ), array( array(), true ) );

$id                            = settled_world();
$_POST['wpcpm_agreement_note'] = str_repeat( 'a', 19 );
ck( 'a note under twenty characters refuses', run( 'handle_revoke' ), 'agreement-revoke-note' );
ck( 'nothing was written and the agreement is still in force', array(
	$GLOBALS['patched'],
	get_post_meta( $id, '_wpcpm_agr_state', true ),
	WPCPM_Institution_Agreement::is_settled( $rec_a ),
), array( array(), 'accepted', true ) );
ck( 'and no lock was taken for a refusal the posted string alone decides', array_key_exists( 'wpcpm_agreement_lock_' . $rec_a, $GLOBALS['opts'] ), false );

$_POST['wpcpm_agreement_note'] = str_repeat( 'a', 2001 );
ck( 'and so does one over two thousand', run( 'handle_revoke' ), 'agreement-revoke-note' );

$_POST['wpcpm_agreement_note'] = str_repeat( 'a', 20 );
ck( 'twenty exactly is enough to revoke on', run( 'handle_revoke' ), 'agreement-revoked' );

$id                  = settled_world();
$before              = get_option( 'wpcpm_agreement_' . $rec_a );
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Service Unavailable' );
ck( 'a PATCH that fails refuses the whole revocation', run( 'handle_revoke' ), 'agreement-airtable' );
ck( 'the document is still the one in force', get_post_meta( $id, '_wpcpm_agr_state', true ), 'accepted' );
ck( 'the option is exactly as it was', get_option( 'wpcpm_agreement_' . $rec_a ), $before );
ck( 'so the account is still open', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
ck( 'and nobody was told anything', $GLOBALS['mail'], array() );
ck( 'the lock was released', array_key_exists( 'wpcpm_agreement_lock_' . $rec_a, $GLOBALS['opts'] ), false );

$id                  = settled_world();
$GLOBALS['airtable'] = array();
ck( 'and a PATCH that reports nothing updated is a failure too', run( 'handle_revoke' ), 'agreement-airtable' );
ck( 'with the gate still open', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );

$id   = settled_world();
$note = (string) $_POST['wpcpm_agreement_note'];
seed_country( $rec_a, 'recCOUNTRYAAAAAA1' );
$outcome = run( 'handle_revoke' );

ck( 'the outcome is the one the panel prints', $outcome, 'agreement-revoked' );
ck( 'the nonce was keyed to the document, not to what the form said it was', $GLOBALS['nonces'], array( 'wpcpm_agreement_revoke_' . $id ) );
ck( 'Airtable was written before anything else left the process', $GLOBALS['journal'], array( 'airtable', 'audit' ) );
ck( 'one PATCH, carrying the status and nothing else', array( count( $GLOBALS['patched'] ), patched_cells() ), array( 1, array( 'Agreement Status' => 'Revoked' ) ) );
ck( 'the stage is not guessed at', array_key_exists( 'Current Stage', patched_cells() ), false );
ck( 'the document is revoked, dated, signed and carrying the note', array(
	get_post_meta( $id, '_wpcpm_agr_state', true ),
	get_post_meta( $id, '_wpcpm_agr_decided_by', true ),
	get_post_meta( $id, '_wpcpm_agr_decided_at', true ),
	get_post_meta( $id, '_wpcpm_agr_note', true ),
), array( 'revoked', 1, gmdate( 'Y-m-d' ), $note ) );
ck( 'the option is deleted, not rewritten', array_key_exists( 'wpcpm_agreement_' . $rec_a, $GLOBALS['opts'] ), false );
ck( 'so the gate is shut on this request, with no sync in between', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );
ck( 'and the panel can still say why', WPCPM_Institution_Agreement::summary( $rec_a )['state'], 'revoked' );
ck( 'every member was told, once each, and nobody else', mail_log(), array( 'agreement-revoked to ola@example.edu', 'agreement-revoked to bo@example.edu' ) );
ck( 'with the note word for word, in both of them', array(
	false !== strpos( $GLOBALS['mail'][0]['body'], $note ),
	false !== strpos( $GLOBALS['mail'][1]['body'], $note ),
), array( true, true ) );
ck( 'naming the manager who wrote it', false !== strpos( $GLOBALS['mail'][0]['body'], 'Kasia Manager' ), true );
ck( 'saying what is limited rather than what is gone', array(
	false !== strpos( $GLOBALS['mail'][0]['body'], 'limited to the agreement panel' ),
	false !== strpos( $GLOBALS['mail'][0]['body'], 'Nothing about your students has been deleted.' ),
), array( true, true ) );
ck( 'and naming the country contact to write to', false !== strpos( $GLOBALS['mail'][0]['body'], 'Your program contact for Costa Rica is costa.rica@example.org.' ), true );
ck( 'who is named and never mailed', in_array( 'costa.rica@example.org', array_column( $GLOBALS['mail'], 'to' ), true ), false );
ck( 'one audit row, on the manager ground, from what the site was holding', array(
	$GLOBALS['audit'][0]['kind'],
	$GLOBALS['audit'][0]['ground'],
	$GLOBALS['audit'][0]['evidence'],
	$GLOBALS['audit'][0]['actor'],
), array( 'agreement_revoke', 'manager', 'cache', 1 ) );
ck( 'the lock was released', array_key_exists( 'wpcpm_agreement_lock_' . $rec_a, $GLOBALS['opts'] ), false );
ck( 'a second press finds nothing in force to revoke', run( 'handle_revoke' ), 'agreement-not-accepted' );
ck( 'and wrote nothing further', count( $GLOBALS['patched'] ), 1 );

$id = settled_world();
run( 'handle_revoke' );
ck( 'an institution the base routes nowhere gets the neutral sentence instead', false !== strpos( $GLOBALS['mail'][0]['body'], 'Write to the program manager who has been in touch with your institution.' ), true );

$id = settled_world();
seed_country( $rec_a, 'recCOUNTRYAAAAAA1', 'Nigeria', '' );
run( 'handle_revoke' );
ck( 'and so does one whose country has nobody against it', false !== strpos( $GLOBALS['mail'][0]['body'], 'Write to the program manager who has been in touch with your institution.' ), true );

$id = settled_world( 'legacy' );
update_post_meta( $id, WPCPM_Institution_Agreement::META_NOTE, 'second folder, the 2025 copy' );
$outcome = run( 'handle_revoke' );

ck( 'a legacy row the program holds on file is revoked the same way', array( $outcome, patched_cells() ), array( 'agreement-revoked', array( 'Agreement Status' => 'Revoked' ) ) );
ck( 'and the manager\'s note about where the paper is does not become what the institution reads', get_post_meta( $id, '_wpcpm_agr_note', true ), (string) $_POST['wpcpm_agreement_note'] );

$id = settled_world();
$GLOBALS['opts'][ 'wpcpm_agreement_lock_' . $rec_a ] = time();
ck( 'a held lock stops a revocation rather than racing it', run( 'handle_revoke' ), 'agreement-busy' );
ck( 'and nothing was read, written or sent', array( $GLOBALS['journal'], WPCPM_Institution_Agreement::is_settled( $rec_a ) ), array( array(), true ) );

/* ---- reinstate ---------------------------------------------------------- */

echo "\n=== handle_reinstate(): the abort that makes revoking a safe click (T9) ===\n";

/**
 * The same institution one revocation later: a revoked document and a shut gate.
 *
 * The journal is cleared afterwards rather than the world rebuilt, because what is being
 * asserted is what the reinstatement does on top of a real revocation: the option this
 * handler has to write back is the one the revocation deleted.
 *
 * @param string $kind Which kind the document is.
 * @return int The revoked document's ID.
 */
function revoked_world( $kind = 'template' ) {
	$id = settled_world( $kind );

	run( 'handle_revoke' );

	$GLOBALS['journal']  = array();
	$GLOBALS['patched']  = array();
	$GLOBALS['audit']    = array();
	$GLOBALS['mail']     = array();
	$GLOBALS['nonces']   = array();
	$GLOBALS['airtable'] = null;
	$_POST               = array( 'wpcpm_agreement_post' => $id );

	return $id;
}

$id = revoked_world();
ck( 'the world starts with the gate shut and no option at all', array(
	WPCPM_Institution_Agreement::is_settled( $rec_a ),
	array_key_exists( 'wpcpm_agreement_' . $rec_a, $GLOBALS['opts'] ),
), array( false, false ) );

$GLOBALS['caps'] = false;
ck( 'a member cannot reinstate their own institution\'s agreement', run( 'handle_reinstate' ), 'died: You do not have permission to manage the program.' );
ck( 'and nothing was written', $GLOBALS['patched'], array() );

$id                  = revoked_world();
$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'Service Unavailable' );
ck( 'a PATCH that fails refuses the whole reinstatement', run( 'handle_reinstate' ), 'agreement-airtable' );
ck( 'the document is still revoked and the gate still shut', array(
	get_post_meta( $id, '_wpcpm_agr_state', true ),
	WPCPM_Institution_Agreement::is_settled( $rec_a ),
), array( 'revoked', false ) );
ck( 'and nobody was told anything', $GLOBALS['mail'], array() );

$id       = revoked_world();
$standing = seed_post( $rec_a, 'accepted', 'own', array( WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-03-03' ) );
ck( 'reinstating under an agreement that already stands is refused', run( 'handle_reinstate' ), 'agreement-reinstate-standing' );
ck( 'nothing was written and both documents are where they were', array(
	$GLOBALS['patched'],
	get_post_meta( $standing, '_wpcpm_agr_state', true ),
	get_post_meta( $id, '_wpcpm_agr_state', true ),
), array( array(), 'accepted', 'revoked' ) );

$id                            = revoked_world();
$newer                         = seed_post( $rec_a, 'revoked', 'own', array( WPCPM_Institution_Agreement::META_DECIDED_AT => '2026-04-04' ) );
$_POST['wpcpm_agreement_post'] = $id;
ck( 'only the most recently revoked document may be put back', run( 'handle_reinstate' ), 'agreement-not-revoked' );
ck( 'and nothing was written', $GLOBALS['patched'], array() );

$_POST['wpcpm_agreement_post'] = $newer;
ck( 'while the newest one is', run( 'handle_reinstate' ), 'agreement-reinstated' );

$id      = revoked_world();
$outcome = run( 'handle_reinstate' );

ck( 'the outcome is the one the panel prints', $outcome, 'agreement-reinstated' );
ck( 'the nonce was keyed to the document', $GLOBALS['nonces'], array( 'wpcpm_agreement_reinstate_' . $id ) );
ck( 'Airtable was written first here too', array_slice( $GLOBALS['journal'], 0, 1 ), array( 'airtable' ) );
ck( 'one PATCH, putting the status back and touching nothing else', array( count( $GLOBALS['patched'] ), patched_cells() ), array( 1, array( 'Agreement Status' => 'Accepted' ) ) );
ck( 'the document is in force again, dated the day it came back', array(
	get_post_meta( $id, '_wpcpm_agr_state', true ),
	get_post_meta( $id, '_wpcpm_agr_decided_at', true ),
), array( 'accepted', gmdate( 'Y-m-d' ) ) );
ck( 'the option is written again, and says why', array(
	WPCPM_Institution_Agreement::option( $rec_a )['site_state'],
	WPCPM_Institution_Agreement::option( $rec_a )['airtable_status'],
), array( 'accepted', 'Accepted' ) );
ck( 'so the gate is open on this request, with no sync in between', WPCPM_Institution_Agreement::is_settled( $rec_a ), true );
ck( 'every member was told, once each, under the acceptance context', mail_log(), array( 'agreement-accepted to ola@example.edu', 'agreement-accepted to bo@example.edu' ) );
ck( 'in the reinstated wording, saying nothing was lost meanwhile', array(
	false !== strpos( $GLOBALS['mail'][0]['body'], 'put back in force' ),
	false !== strpos( $GLOBALS['mail'][0]['body'], 'open again' ),
	false !== strpos( $GLOBALS['mail'][0]['body'], 'Nothing was removed while it was out of force' ),
), array( true, true, true ) );
ck( 'one audit row, on the manager ground', array(
	$GLOBALS['audit'][0]['kind'],
	$GLOBALS['audit'][0]['ground'],
	$GLOBALS['audit'][0]['evidence'],
), array( 'agreement_reinstate', 'manager', 'cache' ) );
ck( 'the lock was released', array_key_exists( 'wpcpm_agreement_lock_' . $rec_a, $GLOBALS['opts'] ), false );
ck( 'a second press finds nothing revoked to put back', run( 'handle_reinstate' ), 'agreement-not-revoked' );
ck( 'and wrote nothing further', count( $GLOBALS['patched'] ), 1 );

$id = revoked_world( 'legacy' );
run( 'handle_reinstate' );
ck( 'a legacy row goes back to On file rather than Accepted', patched_cells(), array( 'Agreement Status' => 'On file' ) );
ck( 'and settles on the route it settled on before', array(
	WPCPM_Institution_Agreement::is_settled( $rec_a ),
	WPCPM_Institution_Agreement::summary( $rec_a )['state'],
), array( true, 'on_file' ) );

// The one gap this handler cannot hold, exactly as `handle_accept()` has it: it lets go
// before the rebuild, which takes the same lock, so a sync working through this record gets
// in and the rebuild skips. The reinstatement itself has landed and the gate is still shut,
// which is what the manager has to be told rather than "the account is open".
$id                     = revoked_world();
$GLOBALS['lock_claims'] = 0;
$GLOBALS['lock_steal']  = 2;
$outcome                = run( 'handle_reinstate' );

ck( 'a rebuild that finds the lock held is not reported as an open account', $outcome, 'agreement-later' );
ck( 'the reinstatement itself stands', array( count( $GLOBALS['patched'] ), get_post_meta( $id, '_wpcpm_agr_state', true ) ), array( 1, 'accepted' ) );
ck( 'the gate is shut until the next sync writes the option', WPCPM_Institution_Agreement::is_settled( $rec_a ), false );
ck( 'and every member was told all the same', mail_log(), array( 'agreement-accepted to ola@example.edu', 'agreement-accepted to bo@example.edu' ) );

/* ---- what the two handlers are, read from the source --------------------- */

echo "\n=== Revoke and reinstate, read from the source ===\n";

$phase4_src = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );
$revoke     = method_body( $phase4_src, 'handle_revoke' );
$reinstate  = method_body( $phase4_src, 'handle_reinstate' );

foreach ( array( 'ACTION_REVOKE', 'ACTION_REINSTATE' ) as $action ) {
	ck( $action . ' is wired to admin_post_', false !== strpos( method_body( $phase4_src, 'init' ), "'admin_post_' . self::" . $action ), true );
}

// The order in the one handler on this page that takes access away is a property of the
// text rather than of any one run, so it is asserted against the file.
ck( 'revoke decides before it touches Airtable', strpos( $revoke, 'WPCPM_Institution_Policy::decide' ) < strpos( $revoke, 'WPCPM_Airtable' ), true );
ck( 'and writes Airtable before it deletes the option', strpos( $revoke, 'update_records' ) < strpos( $revoke, 'delete_option' ), true );
ck( 'the option is deleted once and never rewritten', array( substr_count( $revoke, 'delete_option' ), strpos( $revoke, 'self::rebuild(' ) ), array( 1, false ) );
ck( 'and the stage is not touched, because the plugin does not guess Not Moving Forward', array(
	strpos( $revoke, 'STAGE_CONFIRMED' ),
	strpos( $revoke, '$fields[\'stage\']' ),
), array( false, false ) );
ck( 'reinstate decides before it touches Airtable too', strpos( $reinstate, 'WPCPM_Institution_Policy::decide' ) < strpos( $reinstate, 'WPCPM_Airtable' ), true );
ck( 'and leaves the stage alone as well', strpos( $reinstate, '$fields[\'stage\']' ), false );

foreach ( array( 'handle_revoke', 'handle_reinstate' ) as $handler ) {
	$body_now = method_body( $phase4_src, $handler );
	ck( $handler . '() reads the state again once the lock is its own', strpos( $body_now, 'self::lock(' ) < strrpos( $body_now, 'self::document_in_state( $post_id' ), true );
}

/* ---- the queue ---------------------------------------------------------- */

echo "\n=== awaiting_review() and review_facts(): the queue, oldest first ===\n";

reset_world();
seed_index( $rec_a, 'Universidad Example' );
seed_index( $rec_b, 'Politechnika Example' );
$GLOBALS['members'][ $rec_a ] = array( 4, 5 );
$GLOBALS['users'][4]          = new WP_User( 4, 'Ola Member', 'ola@example.edu' );
$GLOBALS['users'][5]          = new WP_User( 5, 'Bo Nowak', 'bo@example.edu' );

// `seed_post()` stands a document up under author 7, the way an uploader's post carries the
// account that uploaded it; the checklist reads the name from there.
$GLOBALS['users'][7] = new WP_User( 7, 'Ola Member', 'ola@example.edu' );

$first  = seed_post( $rec_a, 'submitted', 'template', array(
	WPCPM_Institution_Agreement::META_FILE             => array( 'path' => '2026/one.pdf', 'size' => 240128, 'sha256' => str_repeat( 'c', 64 ) ),
	WPCPM_Institution_Agreement::META_TEMPLATE_VERSION => '2025-11-04',
	WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT => 'Universidad Example Sede Central',
	WPCPM_Institution_Agreement::META_ORIGINAL_NAME    => 'Umowa podpisana.pdf',
	WPCPM_Institution_Agreement::META_FLAGS            => array( '/JavaScript', '/OpenAction' ),
) );
$second = seed_post( $rec_b, 'submitted', 'own' );
seed_post( $rec_a, 'accepted', 'template' );
seed_post( $rec_b, 'withdrawn', 'own' );
seed_post( $rec_a, 'generated', 'template' );

ck( 'the queue is every document in review, across every institution, oldest first', WPCPM_Institution_Agreement::awaiting_review(), array( $first, $second ) );

$facts = WPCPM_Institution_Agreement::review_facts( $first );

ck( 'the checklist reads the institution from the index, not from the post', array( $facts['institution'], $facts['institution_name'] ), array( $rec_a, 'Universidad Example' ) );
ck( 'and the two names it has to compare', array( $facts['name_on_document'], $facts['template_version'], $facts['kind'] ), array( 'Universidad Example Sede Central', '2025-11-04', 'template' ) );
ck( 'who uploaded it, when, and how big it is', array( $facts['uploaded_by'], $facts['uploaded_at'], $facts['size'] ), array( 'Ola Member', substr( get_post( $first )->post_date, 0, 10 ), 240128 ) );
ck( 'what the scan noticed', $facts['flags'], array( '/JavaScript', '/OpenAction' ) );
ck( 'how many people an acceptance would email', $facts['members'], 2 );
ck( 'and no path to the file anywhere in it', false === strpos( wp_json_encode( $facts ), '2026/one.pdf' ), true );
ck( 'a post that is not ours answers with nothing to print', WPCPM_Institution_Agreement::review_facts( 999999 ), array() );

reset_world();
ck( 'an empty queue is an empty list', WPCPM_Institution_Agreement::awaiting_review(), array() );

/* ---- the discard cron --------------------------------------------------- */

echo "\n=== discard(): the files nobody is waiting on, and only those (T11) ===\n";

/**
 * An agreement post in a given state whose decision was made a given number of days ago.
 *
 * @param string $record Institutions record ID.
 * @param string $state  A STATE_* value.
 * @param int    $days   How long ago it was decided.
 * @param string $path   Where its file is, or '' for none.
 * @return int Post ID.
 */
function aged_post( $record, $state, $days, $path = '' ) {
	$meta = array( WPCPM_Institution_Agreement::META_DECIDED_AT => gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) ) );

	if ( '' !== $path ) {
		$meta[ WPCPM_Institution_Agreement::META_FILE ] = array( 'path' => $path, 'size' => 10, 'sha256' => str_repeat( 'd', 64 ) );
		$GLOBALS['files'][ $path ]                      = '%PDF-1.4 aged';
	}

	return seed_post( $record, $state, 'own', $meta );
}

reset_world();
$old_withdrawn = aged_post( $rec_a, 'withdrawn', 40, '2026/withdrawn-old.pdf' );
$old_returned  = aged_post( $rec_a, 'returned', 31, '2026/returned-old.pdf' );
$new_withdrawn = aged_post( $rec_a, 'withdrawn', 29, '2026/withdrawn-new.pdf' );
$old_accepted  = aged_post( $rec_a, 'accepted', 400, '2026/accepted-old.pdf' );
$old_super     = aged_post( $rec_a, 'superseded', 400, '2026/superseded-old.pdf' );
$no_file       = aged_post( $rec_a, 'withdrawn', 90 );

ck( 'two files were forgotten', WPCPM_Institution_Agreement::discard(), 2 );
ck( 'and they are the withdrawn and returned ones past the setting, oldest first', $GLOBALS['forgotten'], array( '2026/withdrawn-old.pdf', '2026/returned-old.pdf' ) );
ck( 'the ones still inside it are untouched, and so is every accepted or superseded copy', array_keys( $GLOBALS['files'] ), array( '2026/withdrawn-new.pdf', '2026/accepted-old.pdf', '2026/superseded-old.pdf' ) );
ck( 'nothing points at bytes that are gone', array( get_post_meta( $old_withdrawn, '_wpcpm_agr_file', true ), get_post_meta( $old_returned, '_wpcpm_agr_file', true ) ), array( '', '' ) );
ck( 'every post is kept, so the history still reads', count( WPCPM_Institution_Agreement::posts_for( $rec_a ) ), 6 );
ck( 'and each one that lost a file says so', array_map( function ( $e ) { return $e['event']; }, get_post_meta( $old_withdrawn, '_wpcpm_agr_event' ) ), array( 'file discarded' ) );
ck( 'the system did it, not a person', get_post_meta( $old_withdrawn, '_wpcpm_agr_event' )[0]['actor'], 0 );
ck( 'a second run finds nothing left to do', WPCPM_Institution_Agreement::discard(), 0 );

reset_world();
$GLOBALS['settings']['agreement_discard_days'] = 7;
aged_post( $rec_a, 'withdrawn', 10, '2026/ten-days.pdf' );
aged_post( $rec_a, 'withdrawn', 5, '2026/five-days.pdf' );

ck( 'the setting is what decides, not the default', array( WPCPM_Institution_Agreement::discard(), array_keys( $GLOBALS['files'] ) ), array( 1, array( '2026/five-days.pdf' ) ) );

reset_world();
$GLOBALS['clock'] = time() - ( 60 * DAY_IN_SECONDS );
$undated          = seed_post( $rec_a, 'withdrawn', 'own', array( WPCPM_Institution_Agreement::META_FILE => array( 'path' => '2026/undated.pdf', 'size' => 10, 'sha256' => '' ) ) );
$GLOBALS['files']['2026/undated.pdf'] = '%PDF-1.4';
$GLOBALS['clock']                     = 1756700000;

ck( 'a row with no decision date falls back to when it arrived', array( WPCPM_Institution_Agreement::discard(), $GLOBALS['files'] ), array( 1, array() ) );

/* ---- and a backlog longer than one batch -------------------------------- */

// The three assertions the old query could not have passed. It read the two hundred newest
// withdrawn and returned rows, whatever state their files were in, so past two hundred such
// documents the oldest file was never reached and the next night read the same two hundred
// again: a retention rule that has quietly stopped retaining. One case per clause of the
// query that replaced it, each seeding more documents than one batch reads.

reset_world();
$ancient = aged_post( $rec_a, 'withdrawn', 400, '2026/ancient.pdf' );

for ( $i = 0; $i < WPCPM_Institution_Agreement::DISCARD_BATCH; $i++ ) {
	aged_post( $rec_a, 'returned', 60, sprintf( '2026/backlog-%03d.pdf', $i ) );
}

ck( 'a backlog longer than one batch is cleared in one run', WPCPM_Institution_Agreement::discard(), WPCPM_Institution_Agreement::DISCARD_BATCH + 1 );
ck( 'the oldest file went first, and none was left behind', array( $GLOBALS['forgotten'][0], get_post_meta( $ancient, '_wpcpm_agr_file', true ), $GLOBALS['files'] ), array( '2026/ancient.pdf', '', array() ) );
ck( 'and the run after it has nothing to do', WPCPM_Institution_Agreement::discard(), 0 );

reset_world();

for ( $i = 0; $i < WPCPM_Institution_Agreement::DISCARD_BATCH; $i++ ) {
	aged_post( $rec_a, 'withdrawn', 2, sprintf( '2026/recent-%03d.pdf', $i ) );
}

aged_post( $rec_a, 'returned', 90, '2026/behind-a-full-page.pdf' );

ck( 'a whole page of files still inside the window does not hide the one past it', WPCPM_Institution_Agreement::discard(), 1 );
ck( 'and it is the only one forgotten', $GLOBALS['forgotten'], array( '2026/behind-a-full-page.pdf' ) );
ck( 'every file still inside the window is where it was', count( $GLOBALS['files'] ), WPCPM_Institution_Agreement::DISCARD_BATCH );

reset_world();

for ( $i = 0; $i < WPCPM_Institution_Agreement::DISCARD_BATCH; $i++ ) {
	aged_post( $rec_a, 'withdrawn', 200 );
}

aged_post( $rec_a, 'returned', 90, '2026/behind-the-history.pdf' );
$GLOBALS['queries'] = array();

ck( 'a page of documents whose files went months ago does not hide one still on disk', WPCPM_Institution_Agreement::discard(), 1 );
ck( 'and nothing is holding bytes afterwards', $GLOBALS['files'], array() );
ck( 'the cron read one page, not one for every two hundred rows of history', count( $GLOBALS['queries'] ), 1 );

/* ---- the reminder cron -------------------------------------------------- */

echo "\n=== remind(): only when something is overdue, and once a day ===\n";

/**
 * Put a document in review that arrived a given number of days ago.
 *
 * @param string $record Institutions record ID.
 * @param int    $days   How long it has been waiting.
 * @return int Post ID.
 */
function waiting_post( $record, $days ) {
	$was              = $GLOBALS['clock'];
	$GLOBALS['clock'] = time() - ( $days * DAY_IN_SECONDS );
	$id               = seed_post( $record, 'submitted', 'template' );
	$GLOBALS['clock'] = $was;
	return $id;
}

reset_world();
seed_index( $rec_a, 'Universidad Example' );
$GLOBALS['managers'] = array( new WP_User( 1, 'Program Manager', 'pm@example.org' ), new WP_User( 2, 'Second Manager', 'two@example.org' ) );

ck( 'an empty queue sends nothing', WPCPM_Institution_Agreement::remind(), 0 );
ck( 'and does not stamp the day, so it can still send later today', get_option( 'wpcpm_agreement_reminded', '' ), '' );

waiting_post( $rec_a, 1 );
ck( 'something that arrived yesterday is not overdue', WPCPM_Institution_Agreement::remind(), 0 );
ck( 'nothing was sent', $GLOBALS['mail'], array() );

$overdue = waiting_post( $rec_a, 5 );
ck( 'once one is past the review window, every manager is told', WPCPM_Institution_Agreement::remind(), 2 );
ck( 'once each, in the digest', mail_log(), array( 'agreement-reminder to pm@example.org', 'agreement-reminder to two@example.org' ) );
ck( 'the subject counts them, and counts one as one', $GLOBALS['mail'][0]['subject'], '[WPCredits] 1 signed agreement is waiting for review' );
ck( 'the body names the institution, the date and the queue', array(
	false !== strpos( $GLOBALS['mail'][0]['body'], 'Universidad Example, uploaded ' . gmdate( 'Y-m-d', time() - ( 5 * DAY_IN_SECONDS ) ) ),
	false !== strpos( $GLOBALS['mail'][0]['body'], 'longer than the 3-day review window' ),
	false !== strpos( $GLOBALS['mail'][0]['body'], 'https://example.test/wp-admin/admin.php?page=wpcpm-institutions' ),
), array( true, true, true ) );
ck( 'and the day is stamped', get_option( 'wpcpm_agreement_reminded', '' ), gmdate( 'Y-m-d' ) );

$GLOBALS['mail'] = array();
ck( 'a second run the same day sends nothing', WPCPM_Institution_Agreement::remind(), 0 );
ck( 'not one message', $GLOBALS['mail'], array() );

update_option( 'wpcpm_agreement_reminded', gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ), false );
ck( 'tomorrow it goes again, because the queue is still there', WPCPM_Institution_Agreement::remind(), 2 );

update_option( 'wpcpm_agreement_reminded', '', false );
$GLOBALS['mail'] = array();
update_post_meta( $overdue, WPCPM_Institution_Agreement::META_STATE, 'accepted' );
ck( 'once the overdue one is dealt with, the digest stops', WPCPM_Institution_Agreement::remind(), 0 );

update_option( 'wpcpm_agreement_reminded', '', false );
$GLOBALS['settings']['agreement_review_days'] = 1;
ck( 'the window is the setting, not the default', WPCPM_Institution_Agreement::remind(), 2 );

/* ---- the base's spelling, and the hooks --------------------------------- */

echo "\n=== The new writes spell the base's words, and the hooks are registered ===\n";

ck( 'every Agreement Status this phase writes is a choice the base has',
	array_values( array_diff(
		array(
			WPCPM_Institution_Agreement::AIRTABLE_AWAITING,
			WPCPM_Institution_Agreement::AIRTABLE_RETURNED,
			WPCPM_Institution_Agreement::AIRTABLE_ACCEPTED,
			WPCPM_Institution_Agreement::AIRTABLE_REVOKED,
		),
		$fixture['choices']['Agreement Status']
	) ),
	array() );
ck( 'and every Agreement Kind',
	array_values( array_diff(
		array(
			WPCPM_Institution_Agreement::AIRTABLE_KIND_TEMPLATE,
			WPCPM_Institution_Agreement::AIRTABLE_KIND_OWN,
			WPCPM_Institution_Agreement::AIRTABLE_KIND_LEGACY,
		),
		$fixture['choices']['Agreement Kind']
	) ),
	array() );
ck( 'Confirmed is a stage the order knows', in_array( WPCPM_Institution_Agreement::STAGE_CONFIRMED, WPCPM_Institution_Agreement::STAGE_ORDER, true ), true );
ck( 'and it is not the last one, so a stage past it can exist to be left alone', WPCPM_Institution_Agreement::STAGE_CONFIRMED !== end( $order ), true );

$source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );
$init   = method_body( $source, 'init' );

foreach ( array( 'ACTION_UPLOAD', 'ACTION_DOWNLOAD', 'ACTION_ACCEPT', 'ACTION_RETURN', 'ACTION_WITHDRAW' ) as $action ) {
	ck( $action . ' is wired to admin_post_', false !== strpos( $init, "'admin_post_' . self::" . $action ), true );
}

ck( 'download is the only action a signed-out request may reach', array(
	substr_count( $init, "'admin_post_nopriv_'" ),
	false !== strpos( $init, "'admin_post_nopriv_' . self::ACTION_DOWNLOAD" ),
), array( 1, true ) );
ck( 'both crons are hooked here, and neither is scheduled here', array(
	false !== strpos( $init, 'self::CRON_DISCARD' ),
	false !== strpos( $init, 'self::CRON_REMINDERS' ),
	false !== strpos( $source, 'wp_schedule_event' ),
), array( true, true, false ) );
ck( 'the upload checks that the file came from this request', false !== strpos( method_body( $source, 'arrived_by_post' ), 'is_uploaded_file( $path )' ), true );

$upload = method_body( $source, 'handle_upload' );

// The order of the six is the security design, and it is a property of the text.
ck( 'the ceiling is claimed before the file is looked at', strpos( $upload, 'WPCPM_Ceiling::claim' ) < strpos( $upload, 'self::uploaded_file()' ), true );
ck( 'and every check runs before anything is stored', array(
	strpos( $upload, 'wp_check_filetype_and_ext' ) < strpos( $upload, 'WPCPM_Private_Files::store' ),
	strpos( $upload, 'self::PDF_MAGIC' ) < strpos( $upload, 'WPCPM_Private_Files::store' ),
	strpos( $upload, 'self::mime_of' ) < strpos( $upload, 'WPCPM_Private_Files::store' ),
	strpos( $upload, 'self::inspect_pdf' ) < strpos( $upload, 'WPCPM_Private_Files::store' ),
), array( true, true, true, true ) );
ck( 'and the fence before the ceiling', strpos( $upload, 'WPCPM_Institution_Policy::decide' ) < strpos( $upload, 'WPCPM_Ceiling::claim' ), true );

foreach ( $GLOBALS['temp_files'] as $temp ) {
	unlink( $temp );
}


/* ---- nothing in the option is prose ------------------------------------- */

echo "\n=== The option holds no prose ===\n";

reset_world();
$option = WPCPM_Institution_Agreement::rebuild( $rec_a, block( 'On file', $drive, array( 'accepted_by' => 'A Person', 'kind' => 'Legacy' ) ) );
ck( 'the option has exactly the contract keys', array_keys( $option ), array( 'v', 'settled', 'site_state', 'airtable_status', 'kind', 'agreement_id', 'pending_id', 'generated_id', 'accepted_at', 'drive_url', 'updated' ) );
ck( 'the name typed into Accepted By reaches neither the option nor the post',
    array( strpos( serialize( $option ), 'A Person' ), strpos( serialize( $GLOBALS['pmeta'] ), 'A Person' ) ), array( false, false ) );

$source = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );
ck( 'every option write is non-autoloaded', preg_match_all( '/update_option\(/', $source ), preg_match_all( '/update_option\([^;]*?,\s*false\s*\);/s', $source ) );
ck( 'no institution ID is compared with === in the class', preg_match( '/===\s*\$record_id|\$record_id\s*===/', $source ), 0 );
ck( 'no em dash or en dash anywhere in the class', preg_match( '/[\x{2013}\x{2014}]/u', $source ), 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
