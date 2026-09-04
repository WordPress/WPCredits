<?php
/**
 * Approval: the one path from an application to a record, a row and an account.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The three halves happen in one order, and every one of them is stamped the moment it
 *   lands.** The suite kills a run between the record and the account (`wp_insert_user()`
 *   throws once, which is what a fatal in the middle of a request looks like from here),
 *   ages the lock the dead request left behind, and presses Approve again. Afterwards there
 *   is one Airtable record, one index row, one account and one invitation. A handler that
 *   started over, or one that stamped at the end of a successful run, fails this.
 * - **The account half is three things, and a resume finishes whichever is missing.** The
 *   account, its membership and its invitation. `attach()` is made to refuse once and the
 *   button is pressed again: there must then be one record, one account, one membership and
 *   one invitation, and the queue must have called the approval half done in between. A
 *   handler that reads its own account stamp as proof of all three fails this, and the
 *   institution it left has one account that belongs to nobody and can see nothing. The two
 *   halves that are not ours to stamp answer for themselves: `attach()` says "already a
 *   member of this institution", which is a success, and says "acts for another institution",
 *   which is not; `queue_invites()` drops anybody already queued or already mailed.
 * - **An adopted record is finished from the record, never from the application.** A resume
 *   whose earlier press adopted has no search answer left to build a row from, so it reads
 *   the record live; a read that fails refuses the approval rather than describing a
 *   Confirmed institution out of an applicant's typing, and a resume that finds the row and
 *   the option already there reads nothing at all.
 * - **Found by email is a conflict, not a match.** Any account at all holding the contact
 *   address refuses the whole approval, and nothing is created. The single exception is the
 *   account this approval itself made on an earlier press, which the application names.
 * - **`array_merge()` and not `+`.** The stored answers are seeded here with a `Country`, a
 *   `Current Stage` and a consent value of their own, which the real form never stores, and
 *   the cells that reach Airtable must still be the server's four: a link array, the stage
 *   from the settings, a PHP boolean and `Not started`. Under `+` every one of them would be
 *   the applicant's, and the checkbox column would 422 the whole record.
 * - **The duplicate search adopts and never merges.** A hit creates nothing, and the index
 *   row and the agreement option it writes are read off the record the program already has,
 *   not off the application: an applicant's stage over a Confirmed institution, or
 *   `Not started` over an agreement on file, would lock out the members it has.
 * - **The lock.** Taken before the first refusal that could write anything, released on
 *   every exit after it, and never touched by a refusal that comes before it, because a lock
 *   this call did not take belongs to another request.
 * - **The base's spelling is the fixture's.** `create_records()` sends no `typecast`, so
 *   every column name and both choice values are checked against
 *   `bin/fixtures/institutions-table-fields.json`.
 *
 * The other pieces are stood in for exactly at their contracts: the application form's post
 * type and meta keys, the agreement option, the membership stamp, the mail queue, the sync's
 * field map, the Airtable client, the audit log. The pipeline index and the countries map are
 * the real files, because the row this writes has to be one they will hand back.
 *
 * Run from the plugin root:  php bin/test-institution-approval.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts']          = array();
$GLOBALS['opts_autoload'] = array();
$GLOBALS['posts']         = array();
$GLOBALS['pmeta']         = array();
$GLOBALS['users']         = array();
$GLOBALS['umeta']         = array();
$GLOBALS['next_id']       = 300;
$GLOBALS['next_user']     = 40;
$GLOBALS['journal']       = array();
$GLOBALS['audit']         = array();
$GLOBALS['created']       = array();
$GLOBALS['searched']      = array();
$GLOBALS['fetched']       = array();
$GLOBALS['attached']      = array();
$GLOBALS['invited']       = array();
$GLOBALS['rebuilt']       = array();
$GLOBALS['members']       = array();
$GLOBALS['mail_queue']    = array();
$GLOBALS['search_answer'] = array();
$GLOBALS['create_answer'] = null;
$GLOBALS['record_answer'] = null;
$GLOBALS['insert_dies']   = false;
$GLOBALS['attach_fails']  = 0;
$GLOBALS['manager_can']   = true;

class WP_Error {
	public $code = '';
	public $message = '';
	public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_Post {
	public $ID = 0, $post_type = '', $post_status = '', $post_author = 0, $post_title = '', $post_date = '';
}
class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '';
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
function sanitize_user( $s, $strict = false ) { return preg_replace( '/[^a-z0-9._\-]/', '', strtolower( (string) $s ) ); }
function wp_generate_password( $n = 12, $s = true, $x = false ) { return str_repeat( 'x', (int) $n ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? time() : (int) $t ); }
function wp_json_encode( $v ) { return json_encode( $v ); }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) {
	$GLOBALS['opts'][ $k ]          = $v;
	$GLOBALS['opts_autoload'][ $k ] = $a;
	return true;
}
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
	$post                          = new WP_Post();
	$post->ID                      = $GLOBALS['next_id']++;
	$post->post_type               = $a['post_type'] ?? 'post';
	$post->post_status             = $a['post_status'] ?? 'publish';
	$post->post_author             = (int) ( $a['post_author'] ?? 0 );
	$post->post_title              = $a['post_title'] ?? '';
	$post->post_date               = gmdate( 'Y-m-d H:i:s' );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}

/** The slice of `$wpdb` the lock sweep touches: one LIKE over option names. */
class WPCPM_Test_Wpdb {
	public $options = 'wp_options';
	private $args = array();
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) { $this->args = $args; return $sql; }
	public function get_col( $sql ) {
		// The pattern arrives escaped for LIKE, and every option name here is full of the
		// underscores `esc_like()` protects, so it is unescaped again before matching.
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
		if ( 'login' === $by && $user->user_login === (string) $value ) { return $user; }
	}
	return false;
}
function username_exists( $login ) { return false !== get_user_by( 'login', $login ); }
require_once __DIR__ . '/stubs/caps.php';
function get_user_meta( $id, $k = '', $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ( $single ? '' : array() ); }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }

/**
 * `wp_insert_user()`, with a way to die in the middle of a run.
 *
 * The throw is what a fatal between the record half and the account half looks like from
 * here: the handler does not return, so nothing after it runs and the lock it took is left
 * behind. That is the state the resume block then starts from.
 *
 * @param array $a User fields.
 * @return int|WP_Error
 */
function wp_insert_user( $a ) {
	if ( $GLOBALS['insert_dies'] ) {
		$GLOBALS['insert_dies'] = false;
		throw new Exception( 'the request died before the account was made' );
	}
	if ( get_user_by( 'email', $a['user_email'] ?? '' ) ) {
		return new WP_Error( 'existing_user_email', 'That address already has an account.' );
	}
	$GLOBALS['journal'][] = 'user';
	$id                   = $GLOBALS['next_user']++;
	$user                 = new WP_User( $id, (string) ( $a['user_email'] ?? '' ), (string) ( $a['user_login'] ?? '' ) );
	$user->display_name   = (string) ( $a['display_name'] ?? '' );
	$GLOBALS['users'][ $id ] = $user;
	$GLOBALS['umeta'][ $id ] = array( 'role' => $a['role'] ?? '' );
	return $id;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/' );
define( 'WPCPM_VERSION', 'test' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Application' ) ) {
	/** The public form, at its contract: the post type, the states and the meta keys. */
	class WPCPM_Institution_Application {
		const POST_TYPE          = 'wpcpm_inst_app';
		const STATE_NEW          = 'new';
		const STATE_HELD         = 'held';
		const STATE_SPAM         = 'spam';
		const STATE_INFO         = 'info';
		const STATE_APPROVED     = 'approved';
		const STATE_REJECTED     = 'rejected';
		const META_FIELDS        = '_wpcpm_app_fields';
		const META_STATE         = '_wpcpm_app_state';
		const META_REFERENCE     = '_wpcpm_app_reference';
		const META_COUNTRY       = '_wpcpm_app_country';
		const META_COUNTRY_NAME  = '_wpcpm_app_country_name';
		const META_MANAGER       = '_wpcpm_app_manager';
		const META_CONSENT       = '_wpcpm_app_consent';
		const META_SIGNALS       = '_wpcpm_app_signals';
		const META_EMAIL         = '_wpcpm_app_email';
		const META_VERIFIED      = '_wpcpm_app_verified';
		const META_RECORD        = '_wpcpm_app_record';
		const META_USER          = '_wpcpm_app_user';
		const META_EVENT         = '_wpcpm_app_event';
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Sync' ) ) {
	/** Only the field map. Checked against the fixture at the foot of this file. */
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
	/** The table the writes name and the stage a new record starts at. */
	class WPCPM_Settings {
		public static function get() {
			return array(
				'institutions_table'    => 'tbl4V0FEbzRP7I2w2',
				'institution_new_stage' => 'First Contact Made',
			);
		}
		public static function get_value( $key, $fallback = null ) {
			$settings = self::get();
			return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
		}
	}
}

if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/**
	 * The three calls approval makes, journalled so their order can be asserted.
	 *
	 * `create_records()` answers with a list of record IDs and `fetch_page()` with records
	 * and an offset, which is what the real client returns; the tests drive both through
	 * globals so a failed read and an empty answer can each be made to happen.
	 */
	class WPCPM_Airtable {
		public function __construct( $settings = null ) {}
		public function fetch_page( $table, array $args = array() ) {
			$GLOBALS['journal'][]  = 'search';
			$GLOBALS['searched'][] = array( $table, $args );
			if ( $GLOBALS['search_answer'] instanceof WP_Error ) { return $GLOBALS['search_answer']; }
			return array(
				'records' => (array) $GLOBALS['search_answer'],
				'offset'  => null,
			);
		}
		public function get_record( $table, $record_id ) {
			$GLOBALS['journal'][] = 'read';
			$GLOBALS['fetched'][] = array( $table, $record_id );
			if ( $GLOBALS['record_answer'] instanceof WP_Error ) { return $GLOBALS['record_answer']; }
			return array(
				'id'     => $record_id,
				'fields' => (array) $GLOBALS['record_answer'],
			);
		}
		public function create_records( $table, array $records ) {
			$GLOBALS['journal'][] = 'create';
			$GLOBALS['created'][] = array(
				'table'   => $table,
				'records' => $records,
			);
			if ( $GLOBALS['create_answer'] instanceof WP_Error ) { return $GLOBALS['create_answer']; }
			if ( is_array( $GLOBALS['create_answer'] ) ) { return $GLOBALS['create_answer']; }
			return array( $GLOBALS['create_answer'] );
		}
		public static function flatten( $value, $glue = ', ' ) {
			if ( is_array( $value ) ) {
				$parts = array();
				foreach ( $value as $item ) {
					$parts[] = is_array( $item ) && isset( $item['name'] ) ? (string) $item['name'] : (string) ( is_array( $item ) ? ( $item['id'] ?? '' ) : $item );
				}
				return implode( $glue, array_filter( $parts, 'strlen' ) );
			}
			return is_scalar( $value ) ? (string) $value : '';
		}
		public static function link_ids( $value ) {
			if ( ! is_array( $value ) ) { return array(); }
			$ids = array();
			foreach ( $value as $item ) {
				if ( is_string( $item ) && 0 === strpos( $item, 'rec' ) ) { $ids[] = $item; }
				elseif ( is_array( $item ) && ! empty( $item['id'] ) ) { $ids[] = (string) $item['id']; }
			}
			return $ids;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/**
	 * The gate's option, at its contract.
	 *
	 * `rebuild()` writes the same non-autoloaded option the real one writes and keeps the
	 * block it was handed, which is what the adoption block reads to prove that an
	 * institution the program already had does not have `Not started` written over it.
	 * `settled` is the Airtable half alone here: no agreement posts exist in this suite, so
	 * the site half would refuse everything and prove nothing.
	 */
	class WPCPM_Institution_Agreement {
		const OPT_PREFIX   = 'wpcpm_agreement_';
		const POST_TYPE       = 'wpcpm_agreement';
		const SUMMARY_NONE    = 'none';
		const STAGE_ORDER     = array( 'First Contact Made', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Call Scheduled', 'Agreement Sent', 'Confirmed', 'Student' );
		const TERMINAL_STAGES = array( 'Not Moving Forward', 'SPAM', 'Revisit Later' );
		const AIRTABLE_SETTLED = array( 'Accepted', 'On file' );
		public static function option_name( $record_id ) {
			return self::OPT_PREFIX . trim( (string) $record_id );
		}
		public static function option( $record_id ) {
			$row = get_option( self::option_name( $record_id ) );
			return is_array( $row ) ? $row : null;
		}
		public static function is_settled( $record_id ) {
			$row = self::option( $record_id );
			return null !== $row && true === $row['settled'];
		}
		public static function rebuild( $record_id, array $airtable ) {
			$GLOBALS['journal'][] = 'agreement';
			$GLOBALS['rebuilt'][] = array( $record_id, $airtable );
			$option               = array(
				'v'               => 1,
				'settled'         => false,
				'site_state'      => '',
				'airtable_status' => (string) ( $airtable['status'] ?? '' ),
				'updated'         => time(),
			);
			update_option( self::option_name( $record_id ), $option, false );
			return $option;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Members' ) ) {
	/**
	 * The membership stamp, at its contract, refusals included.
	 *
	 * It refuses a record the index does not hold, exactly as the real one does, which is
	 * what makes "the index row before the account" an assertion rather than a hope. It also
	 * keeps the live membership it writes, because the approval reads two of its refusals as
	 * answers: an account already attached to this institution is a half already done, and
	 * one attached to another is a person's problem and not a step to repeat.
	 */
	class WPCPM_Institution_Members {
		const HOW_PROVISIONED = 'provisioned';
		const HOW_APPROVED    = 'approved';
		const HOW_MANAGER     = 'manager';
		const HOW_INVITED     = 'invited';
		const HOW_LEGACY      = 'legacy';
		public static function attach( $user_id, $record_id, $how, $actor_id, $invite_id = 0 ) {
			if ( ! WPCPM_Institutions_Index::has( $record_id ) ) {
				return new WP_Error( 'wpcpm_member_not_indexed', 'That institution is not in the pipeline index yet.' );
			}
			if ( ! get_userdata( $user_id ) ) {
				return new WP_Error( 'wpcpm_member_no_account', 'There is no account with that ID.' );
			}
			// A membership that will not land, for as many calls as the test asked for. Any
			// of this method's refusals looks the same from the approval's side; a sync that
			// rewrote the index between the row insert and this call is the plausible one,
			// and what matters is what the next press does about it.
			if ( $GLOBALS['attach_fails'] > 0 ) {
				--$GLOBALS['attach_fails'];
				return new WP_Error( 'wpcpm_member_not_indexed', 'That institution is not in the pipeline index yet.' );
			}
			$live = isset( $GLOBALS['members'][ (int) $user_id ] ) ? $GLOBALS['members'][ (int) $user_id ] : '';
			if ( '' !== $live ) {
				return 0 === strcmp( $live, (string) $record_id )
					? new WP_Error( 'wpcpm_member_already', 'That account is already a member of this institution.' )
					: new WP_Error( 'wpcpm_member_elsewhere', 'That account already acts for another institution.' );
			}
			$GLOBALS['members'][ (int) $user_id ] = (string) $record_id;
			$GLOBALS['journal'][]                 = 'attach';
			$GLOBALS['attached'][]                = array(
				'user'   => (int) $user_id,
				'record' => (string) $record_id,
				'how'    => (string) $how,
				'actor'  => (int) $actor_id,
			);
			return true;
		}
	}
}

if ( ! class_exists( 'WPCPM_Mail' ) ) {
	/**
	 * The invitation queue, counted, and idempotent the way the real one is.
	 *
	 * `queue_invites()` drops an account already waiting in the queue and one already stamped
	 * invited, so the invitation is what remembers that it was sent and the approval may ask
	 * again on every press. Both halves of that test are here, because "one invitation" on a
	 * resume is an assertion about this behaviour and not about the caller counting presses.
	 * `welcome_email()`'s wording is `bin/test-mail.php`'s.
	 */
	class WPCPM_Mail {
		public static function queue_invites( array $user_ids ) {
			$fresh = array();
			foreach ( array_values( array_unique( array_map( 'intval', $user_ids ) ) ) as $id ) {
				if ( in_array( $id, $GLOBALS['mail_queue'], true ) || get_user_meta( $id, 'wpcpm_inst_invited', true ) ) {
					continue;
				}
				$GLOBALS['mail_queue'][] = $id;
				$fresh[]                 = $id;
			}
			if ( empty( $fresh ) ) {
				return 0;
			}
			$GLOBALS['journal'][] = 'invite';
			$GLOBALS['invited'][] = $fresh;
			return count( $fresh );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Audit' ) ) {
	/** The log, at its contract: it refuses the four rows the real one refuses. */
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

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-countries.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-approval.php';

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
 * A well-formed Airtable record ID from a readable stem.
 *
 * @param string $stem Up to 14 characters.
 * @return string
 */
function rid( $stem ) {
	return 'rec' . str_pad( substr( $stem, 0, 14 ), 14, '0' );
}

$institution = rid( 'INST' );
$country     = rid( 'COUNTRY' );
$other       = rid( 'OTHER' );

/**
 * Forget every option, post, account and journal entry, and seed the countries map.
 *
 * The country is seeded on every reset because approval re-reads it: a block that meant to
 * test something else must not pass or fail on a country that a previous block left behind.
 */
function reset_world() {
	$GLOBALS['opts']          = array();
	$GLOBALS['opts_autoload'] = array();
	$GLOBALS['posts']         = array();
	$GLOBALS['pmeta']         = array();
	$GLOBALS['users']         = array();
	$GLOBALS['umeta']         = array();
	$GLOBALS['journal']       = array();
	$GLOBALS['audit']         = array();
	$GLOBALS['created']       = array();
	$GLOBALS['searched']      = array();
	$GLOBALS['fetched']       = array();
	$GLOBALS['attached']      = array();
	$GLOBALS['invited']       = array();
	$GLOBALS['rebuilt']       = array();
	$GLOBALS['members']       = array();
	$GLOBALS['mail_queue']    = array();
	$GLOBALS['search_answer'] = array();
	$GLOBALS['create_answer'] = null;
	$GLOBALS['record_answer'] = null;
	$GLOBALS['insert_dies']   = false;
	$GLOBALS['attach_fails']  = 0;
	$GLOBALS['manager_can']   = true;
	$GLOBALS['next_id']       = 300;
	$GLOBALS['next_user']     = 40;

	update_option(
		WPCPM_Countries::OPT_NAME,
		array(
			'v'    => WPCPM_Countries::VERSION,
			'read' => time(),
			'rows' => array(
				$GLOBALS['country_id'] => array(
					'name'     => 'Poland',
					'manager'  => '',
					'email'    => 'pm@example.org',
					'calendly' => 'https://calendly.com/pm',
				),
			),
		),
		false
	);
}

/**
 * The thirteen answers as the form stores them, with three it never stores.
 *
 * `Country`, `Current Stage` and `Privacy Policy Compliance` are server-held and the real
 * `_wpcpm_app_fields` never contains them. They are here to prove the merge direction: under
 * `+` these three would be what reached Airtable.
 *
 * @return array
 */
function seeded_answers() {
	return array(
		'Name'                                                        => 'Universidad Example',
		'City'                                                        => 'Krakow',
		'Website'                                                     => 'example.edu',
		'Contact Person'                                              => 'Ada Example',
		'Contact Email'                                               => 'contact@example.edu',
		'Department'                                                  => 'Computer Science',
		'How do your internships or practices typically work?'        => array( ' Based on required hours (e.g. 150 hours)' ),
		'Comments'                                                    => '',
		'Estimated number of students who may be interested'          => 25,
		'Why are you interested in offering WordPress Credits to your students?' => 'Our students want real contributions.',
		'Anything else you’d like us to know?'                        => '',
		'Country'                                                     => 'not-a-record',
		'Current Stage'                                               => 'Confirmed',
		'Privacy Policy Compliance'                                   => 'yes',
	);
}

/**
 * Stand up one application post in the state a manager finds it in.
 *
 * @param string $state   One of the application states.
 * @param array  $meta    Extra meta rows, keyed by meta key.
 * @param array  $answers The stored answers; the seeded thirteen when empty.
 * @return int The post ID.
 */
function seed_application( $state = 'new', array $meta = array(), array $answers = array() ) {
	$id = wp_insert_post(
		array(
			'post_type'   => WPCPM_Institution_Application::POST_TYPE,
			'post_status' => 'private',
			'post_author' => 0,
			'post_title'  => 'Universidad Example',
		)
	);

	update_post_meta( $id, WPCPM_Institution_Application::META_FIELDS, $answers ? $answers : seeded_answers() );
	update_post_meta( $id, WPCPM_Institution_Application::META_STATE, $state );
	update_post_meta( $id, WPCPM_Institution_Application::META_COUNTRY, $GLOBALS['country_id'] );
	update_post_meta( $id, WPCPM_Institution_Application::META_COUNTRY_NAME, 'Poland' );
	update_post_meta( $id, WPCPM_Institution_Application::META_VERIFIED, time() );
	update_post_meta( $id, WPCPM_Institution_Application::META_REFERENCE, 'APP-2026-0007' );

	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}

	return $id;
}

/**
 * An application whose record and account halves both landed on an earlier press.
 *
 * What a run that died after `wp_insert_user()` leaves behind: the record stamped, its index
 * row in place, the account made and named on the application. What it deliberately does not
 * do is attach or invite, because that is the part each block below says for itself.
 *
 * @param string $record The institution the earlier press settled on.
 * @return array `array( $application_id, $user_id )`.
 */
function seed_stamped_account( $record ) {
	$application_id = seed_application(
		'new',
		array(
			WPCPM_Institution_Application::META_RECORD => $record,
		)
	);
	$user_id        = wp_insert_user(
		array(
			'user_login' => 'contact',
			'user_email' => 'contact@example.edu',
			'role'       => WPCPM_Roles::ROLE_INSTITUTION,
		)
	);

	update_post_meta( $application_id, WPCPM_Institution_Application::META_USER, $user_id );
	WPCPM_Institutions_Index::insert(
		array_merge(
			WPCPM_Institutions_Index::empty_row(),
			array(
				'record_id' => $record,
				'name'      => 'Universidad Example',
			)
		)
	);

	return array( $application_id, (int) $user_id );
}

/**
 * Say that an earlier press adopted this record, the way the handler says it.
 *
 * The event list is how a second press learns whether the record was created or joined, and
 * the stamp alone does not say. Written here as `event()` writes it, through the same
 * constant, so a renamed event is a failure here rather than a resume that quietly rebuilds
 * an adopted institution's row out of the application.
 *
 * @param int    $application_id The application post.
 * @param string $record         The record that was adopted.
 */
function adopted_on_an_earlier_press( $application_id, $record ) {
	add_post_meta(
		$application_id,
		WPCPM_Institution_Application::META_EVENT,
		array(
			'event' => WPCPM_Institution_Approval::EVENT_RECORD_ADOPTED,
			'at'    => time() - 120,
			'actor' => 7,
			'note'  => $record,
		)
	);
}

/** The application's stored answers reach `seed_application()` through this. */
$GLOBALS['country_id'] = $country;

/**
 * The cells an adopted record answers with: an institution the program already works with.
 *
 * @return array
 */
function adopted_cells() {
	return array(
		'Name'                      => 'Universidad Example ',
		'Current Stage'             => 'Confirmed',
		'Country'                   => array( $GLOBALS['country_id'] ),
		'City'                      => 'Krakow',
		'Website'                   => 'https://example.edu',
		'Contact Person'            => 'Someone Else',
		'Contact Email'             => 'Outreach@Example.edu',
		'Confirmed on'              => '2024-05-06',
		'Privacy Policy Compliance' => true,
		'Agreement Status'          => 'On file',
		'Agreement Kind'            => 'Legacy',
		'Agreement Accepted On'     => '2024-05-07',
		'Agreement Document'        => 'https://drive.google.com/file/d/abc/view',
	);
}

echo "\n-- the happy path: a record, a row, an option, an account, an invitation --\n";

reset_world();
$GLOBALS['create_answer'] = $institution;
$app                      = seed_application();
$result                   = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'approve() answers with the record, the account and how it got there', is_array( $result ) ? array_keys( $result ) : $result, array( 'record', 'user_id', 'adopted' ) );
ck( 'the record is the one Airtable made', $result['record'], $institution );
ck( 'nothing was adopted', $result['adopted'], false );
ck( 'one account was made', count( $GLOBALS['users'] ), 1 );
ck( 'and it is the one that came back', $result['user_id'], 40 );

$sent = $GLOBALS['created'][0]['records'][0]['fields'];

ck( 'one record was created', count( $GLOBALS['created'] ), 1 );
ck( 'in the Institutions table', $GLOBALS['created'][0]['table'], 'tbl4V0FEbzRP7I2w2' );
ck( 'Country is the link array and not the applicant string', $sent['Country'], array( $country ) );
ck( 'Current Stage is the settings one and not the applicant string', $sent['Current Stage'], 'First Contact Made' );
ck( 'Privacy Policy Compliance is a PHP boolean and not "yes"', $sent['Privacy Policy Compliance'], true );
ck( 'Agreement Status starts at Not started', $sent['Agreement Status'], 'Not started' );
ck( 'the applicant answers are carried over whole', array( $sent['Name'], $sent['City'], $sent['Department'] ), array( 'Universidad Example', 'Krakow', 'Computer Science' ) );
ck( 'the multi-select keeps the leading space of the first choice', $sent['How do your internships or practices typically work?'], array( ' Based on required hours (e.g. 150 hours)' ) );

ck( 'the record is stamped on the application', get_post_meta( $app, WPCPM_Institution_Application::META_RECORD, true ), $institution );
ck( 'the account is stamped on the application', (int) get_post_meta( $app, WPCPM_Institution_Application::META_USER, true ), 40 );
ck( 'the application is approved afterwards', get_post_meta( $app, WPCPM_Institution_Application::META_STATE, true ), 'approved' );

$row = WPCPM_Institutions_Index::row( $institution );

ck( 'the pipeline index holds the institution', is_array( $row ), true );
ck( 'with the name, city and stage the record was created at', array( $row['name'], $row['city'], $row['stage'] ), array( 'Universidad Example', 'Krakow', 'First Contact Made' ) );
ck( 'the country and its name', array( $row['country'], $row['country_name'] ), array( $country, 'Poland' ) );
ck( 'the contact address, lower-cased the way the sync stores it', $row['contact_email'], 'contact@example.edu' );
ck( 'consent recorded true, because the form refuses without it', $row['consent'], true );
ck( 'and the row says the agreement has not started', $row['agreement']['status'], 'Not started' );

ck( 'the agreement option was rebuilt once', count( $GLOBALS['rebuilt'] ), 1 );
ck( 'from an empty block at Not started', $GLOBALS['rebuilt'][0][1], array(
	'status'           => 'Not started',
	'kind'             => '',
	'accepted_on'      => '',
	'signed_on'        => '',
	'accepted_by'      => '',
	'document'         => '',
	'submitted_on'     => '',
	'template_version' => '',
) );
ck( 'the option exists and is locked, which is what a new institution sees', WPCPM_Institution_Agreement::is_settled( $institution ), false );

ck( 'the account was attached as approved, by the manager', $GLOBALS['attached'], array(
	array(
		'user'   => 40,
		'record' => $institution,
		'how'    => 'approved',
		'actor'  => 7,
	),
) );
ck( 'one invitation was queued, for that account', $GLOBALS['invited'], array( array( 40 ) ) );

ck( 'the halves happen in the one order', $GLOBALS['journal'], array( 'search', 'create', 'agreement', 'user', 'attach', 'invite', 'audit' ) );
ck( 'one audit row, naming the manager ground and the live read', array( count( $GLOBALS['audit'] ), $GLOBALS['audit'][0]['ground'], $GLOBALS['audit'][0]['evidence'] ), array( 1, 'manager', 'live' ) );
ck( 'the row is about the institution and names the actor', array( $GLOBALS['audit'][0]['institution'], $GLOBALS['audit'][0]['actor'] ), array( $institution, 7 ) );
ck( 'the lock is released', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );

$events = array();
foreach ( get_post_meta( $app, WPCPM_Institution_Application::META_EVENT, false ) as $event ) {
	$events[] = $event['event'];
}
ck( 'the application says what was done to it', $events, array( 'record created', 'account created', 'approved' ) );

echo "\n-- the duplicate search: the formula, and what a hit does --\n";

reset_world();
$GLOBALS['create_answer'] = $institution;
$app                      = seed_application();
WPCPM_Institution_Approval::approve( $app, 7 );

$formula = $GLOBALS['searched'][0][1]['formula'];

ck( 'the search compares the trimmed lower-cased name and the lower-cased address', $formula, "OR( TRIM(LOWER({Name})) = 'universidad example', LOWER({Contact Email}) = 'contact@example.edu' )" );
ck( 'and asks for the columns an index row is made of, and no prose', $GLOBALS['searched'][0][1]['fields'], array_values( WPCPM_Institutions_Sync::fields() ) );

reset_world();
$GLOBALS['search_answer'] = array(
	array(
		'id'     => $other,
		'fields' => adopted_cells(),
	),
);
$app    = seed_application();
$result = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'a hit adopts the record the program already has', $result['record'], $other );
ck( 'and says so', $result['adopted'], true );
ck( 'nothing was created', count( $GLOBALS['created'] ), 0 );
ck( 'the record is stamped all the same', get_post_meta( $app, WPCPM_Institution_Application::META_RECORD, true ), $other );

$row = WPCPM_Institutions_Index::row( $other );

ck( 'the index row is read off the record, not off the application', array( $row['stage'], $row['contact_person'] ), array( 'Confirmed', 'Someone Else' ) );
ck( 'the name keeps the trailing space the base holds', $row['name'], 'Universidad Example ' );
ck( 'the confirmed date comes across as a date', $row['confirmed_on'], '2024-05-06' );
ck( 'the agreement block is the base\'s and not Not started', $GLOBALS['rebuilt'][0][1]['status'], 'On file' );
ck( 'the Drive link never enters the index row', array_key_exists( 'document', $row['agreement'] ), false );
ck( 'the row says a document exists', $row['agreement']['has_document'], true );
ck( 'the account still attaches to the adopted record', $GLOBALS['attached'][0]['record'], $other );
ck( 'the audit row calls it an adoption', false !== strpos( $GLOBALS['audit'][0]['message'], 'already had' ), true );

reset_world();
$GLOBALS['search_answer'] = array(
	array(
		'id'     => $other,
		'fields' => adopted_cells(),
	),
);
$app = seed_application();
WPCPM_Institutions_Index::write(
	array(
		$other => array_merge(
			WPCPM_Institutions_Index::empty_row(),
			array(
				'record_id' => $other,
				'name'      => 'Universidad Example ',
				'stage'     => 'Confirmed',
			)
		),
	),
	time() - 100
);
WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'an adopted record already in the index keeps the sync\'s row', WPCPM_Institutions_Index::row( $other )['contact_person'], '' );

echo "\n-- the run that died between the halves, finished by pressing Approve again --\n";

reset_world();
$GLOBALS['create_answer'] = $institution;
$GLOBALS['insert_dies']   = true;
$app                      = seed_application();
$died                     = '';

try {
	WPCPM_Institution_Approval::approve( $app, 7 );
} catch ( Exception $e ) {
	$died = $e->getMessage();
}

ck( 'the run died where the account is made', $died, 'the request died before the account was made' );
ck( 'the record half had already landed and is stamped', get_post_meta( $app, WPCPM_Institution_Application::META_RECORD, true ), $institution );
ck( 'the index row had already landed', WPCPM_Institutions_Index::has( $institution ), true );
ck( 'no account exists', count( $GLOBALS['users'] ), 0 );
ck( 'the application is still undecided', get_post_meta( $app, WPCPM_Institution_Application::META_STATE, true ), 'new' );
ck( 'the dead request left its lock behind', false !== get_option( WPCPM_Institution_Approval::lock_name( $app ) ), true );
ck( 'and that lock was never autoloaded', $GLOBALS['opts_autoload'][ WPCPM_Institution_Approval::lock_name( $app ) ] ?? 'missing', false );
ck( 'and the queue can see the approval is half done', WPCPM_Institution_Approval::is_half_done( $app ), true );

$second = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'a second press inside the minute is refused rather than served twice', is_wp_error( $second ) ? $second->get_error_code() : $second, 'wpcpm_app_busy' );

// What waiting out `LOCK_TIMEOUT` looks like from here: the row a dead request left is
// aged rather than deleted, so the stale clear is what lets the next press through.
$GLOBALS['opts'][ WPCPM_Institution_Approval::lock_name( $app ) ] = time() - ( WPCPM_Institution_Approval::LOCK_TIMEOUT + 1 );

$GLOBALS['journal'] = array();
$result             = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'the second press finishes the job', is_array( $result ), true );
ck( 'one Airtable record', count( $GLOBALS['created'] ), 1 );
ck( 'one index row', count( WPCPM_Institutions_Index::rows() ), 1 );
ck( 'one account', count( $GLOBALS['users'] ), 1 );
ck( 'one membership', count( $GLOBALS['attached'] ), 1 );
ck( 'one agreement option, written on the first press and not again', count( $GLOBALS['rebuilt'] ), 1 );
ck( 'one invitation', $GLOBALS['invited'], array( array( 40 ) ) );
ck( 'the record it answers with is the one already stamped', $result['record'], $institution );
ck( 'the halves already done are skipped whole, down to the option', $GLOBALS['journal'], array( 'user', 'attach', 'invite', 'audit' ) );
ck( 'the application is approved', get_post_meta( $app, WPCPM_Institution_Application::META_STATE, true ), 'approved' );
ck( 'and the lock is released', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );
ck( 'the approval is no longer half done', WPCPM_Institution_Approval::is_half_done( $app ), false );

echo "\n-- a run that died after the whole account half, finished the same way --\n";

reset_world();
list( $app, $user ) = seed_stamped_account( $institution );

// Everything the account half does, done: the account, its membership and its invitation.
// The press that follows asks all three again, because two of them are not the
// application's to stamp, and each has to answer that it is already done.
WPCPM_Institution_Members::attach( $user, $institution, WPCPM_Institution_Members::HOW_APPROVED, 7 );
WPCPM_Mail::queue_invites( array( $user ) );

$GLOBALS['journal']  = array();
$GLOBALS['attached'] = array();
$GLOBALS['invited']  = array();
$result              = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'the account this approval made is not the conflict the email test refuses', is_array( $result ), true );
ck( 'it is the account the application already names', is_array( $result ) ? $result['user_id'] : $result, (int) $user );
ck( 'no second account, no second invitation', array( count( $GLOBALS['users'] ), count( $GLOBALS['invited'] ) ), array( 1, 0 ) );
ck( 'the membership it already holds is not written a second time', count( $GLOBALS['attached'] ), 0 );
ck( '"already a member of this institution" is an answer and not a refusal', is_wp_error( $result ), false );
ck( 'and nothing was created in Airtable', count( $GLOBALS['created'] ), 0 );

// The narrow reading of that answer: only the account's own institution counts as done. An
// account a manager has since moved elsewhere is a thing a person has to look at, and the
// approval must stop rather than credit this institution with a membership it has not got.
reset_world();
list( $app, $user ) = seed_stamped_account( $institution );
$GLOBALS['members'][ (int) $user ] = $other;

$out = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'an account that now acts for another institution refuses the approval', is_wp_error( $out ) ? $out->get_error_code() : $out, 'wpcpm_member_elsewhere' );
ck( 'no invitation goes out on the back of it', count( $GLOBALS['invited'] ), 0 );
ck( 'the application is left undecided for a person to look at', get_post_meta( $app, WPCPM_Institution_Application::META_STATE, true ), 'new' );
ck( 'and the lock is given back', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );

echo "\n-- a membership that would not attach, made on the next press --\n";

reset_world();
$GLOBALS['create_answer'] = $institution;
$GLOBALS['attach_fails']  = 1;
$app                      = seed_application();
$out                      = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'an attachment that failed refuses the whole approval', is_wp_error( $out ) ? $out->get_error_code() : $out, 'wpcpm_member_not_indexed' );
ck( 'the record half had landed and is stamped', get_post_meta( $app, WPCPM_Institution_Application::META_RECORD, true ), $institution );
ck( 'so had the account, which is left standing rather than deleted', array( count( $GLOBALS['users'] ), (int) get_post_meta( $app, WPCPM_Institution_Application::META_USER, true ) ), array( 1, 40 ) );
ck( 'no membership and no invitation', array( count( $GLOBALS['attached'] ), count( $GLOBALS['invited'] ) ), array( 0, 0 ) );
ck( 'the application is still undecided', get_post_meta( $app, WPCPM_Institution_Application::META_STATE, true ), 'new' );

// The half-done banner is what tells a manager to press again, and this is the state it
// would have missed: an account exists, so "no account yet" would read as finished while the
// institution's only account belongs to nobody and can see nothing.
ck( 'and the queue can still see the approval is half done', WPCPM_Institution_Approval::is_half_done( $app ), true );
ck( 'the lock is given back, so the next press is not made to wait', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );

$GLOBALS['journal'] = array();
$result             = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'the second press finishes the account half', is_array( $result ), true );
ck( 'one Airtable record', count( $GLOBALS['created'] ), 1 );
ck( 'one index row', count( WPCPM_Institutions_Index::rows() ), 1 );
ck( 'one account, the one the first press made', array( count( $GLOBALS['users'] ), is_array( $result ) ? $result['user_id'] : $result ), array( 1, 40 ) );
ck( 'one membership, for that account and this institution', $GLOBALS['attached'], array(
	array(
		'user'   => 40,
		'record' => $institution,
		'how'    => 'approved',
		'actor'  => 7,
	),
) );
ck( 'one invitation', $GLOBALS['invited'], array( array( 40 ) ) );
ck( 'only the halves that were missing were done', $GLOBALS['journal'], array( 'attach', 'invite', 'audit' ) );
ck( 'the application is approved', get_post_meta( $app, WPCPM_Institution_Application::META_STATE, true ), 'approved' );
ck( 'the account it answers with is the one already stamped', (int) get_post_meta( $app, WPCPM_Institution_Application::META_USER, true ), 40 );
ck( 'and the approval is no longer half done', WPCPM_Institution_Approval::is_half_done( $app ), false );

echo "\n-- an adopted record whose row and option never landed --\n";

reset_world();
$app = seed_application(
	'new',
	array(
		WPCPM_Institution_Application::META_RECORD => $other,
	)
);
adopted_on_an_earlier_press( $app, $other );
$GLOBALS['record_answer'] = adopted_cells();
$result                   = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'the record it already adopted is the one it finishes', $result['record'], $other );
ck( 'and it is still an adoption on this press', $result['adopted'], true );
ck( 'nothing is searched for and nothing is created', array( count( $GLOBALS['searched'] ), count( $GLOBALS['created'] ) ), array( 0, 0 ) );

// The one live read this handler makes: the search that adopted the record ran on a press
// whose answer is gone, and describing the institution from the application instead would
// write an applicant's stage over a Confirmed institution and lock out the members it has.
ck( 'it reads the adopted record itself, from the Institutions table', $GLOBALS['fetched'], array( array( 'tbl4V0FEbzRP7I2w2', $other ) ) );

$row = WPCPM_Institutions_Index::row( $other );

ck( 'the row it writes is the record\'s and not the application\'s', array( $row['stage'], $row['contact_person'] ), array( 'Confirmed', 'Someone Else' ) );
ck( 'down to the trailing space the base holds', $row['name'], 'Universidad Example ' );
ck( 'the agreement option is the base\'s and not Not started', $GLOBALS['rebuilt'][0][1]['status'], 'On file' );
ck( 'the account attaches to the adopted record', $GLOBALS['attached'][0]['record'], $other );
ck( 'and the halves left over happen in the one order', $GLOBALS['journal'], array( 'read', 'agreement', 'user', 'attach', 'invite', 'audit' ) );

reset_world();
$app = seed_application(
	'new',
	array(
		WPCPM_Institution_Application::META_RECORD => $other,
	)
);
adopted_on_an_earlier_press( $app, $other );
$GLOBALS['record_answer'] = new WP_Error( 'wpcpm_airtable_http', 'the base did not answer' );
$out                      = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'a live read that failed refuses the approval', is_wp_error( $out ) ? $out->get_error_code() : $out, 'wpcpm_app_airtable' );
ck( 'rather than describing the institution from what the applicant typed', count( WPCPM_Institutions_Index::rows() ), 0 );
ck( 'no agreement option is written over the one the record may have', count( $GLOBALS['rebuilt'] ), 0 );
ck( 'and no account is opened on the back of it', count( $GLOBALS['users'] ), 0 );
ck( 'the lock is given back', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );

reset_world();
$app = seed_application(
	'new',
	array(
		WPCPM_Institution_Application::META_RECORD => $other,
	)
);
adopted_on_an_earlier_press( $app, $other );
WPCPM_Institutions_Index::insert(
	array_merge(
		WPCPM_Institutions_Index::empty_row(),
		array(
			'record_id' => $other,
			'name'      => 'Universidad Example ',
			'stage'     => 'Confirmed',
		)
	)
);
WPCPM_Institution_Agreement::rebuild( $other, array( 'status' => 'On file' ) );

$GLOBALS['journal'] = array();
$GLOBALS['rebuilt'] = array();
$result             = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'an adopted record whose row and option both stand is read no further', count( $GLOBALS['fetched'] ), 0 );
ck( 'and its agreement option is not written again', count( $GLOBALS['rebuilt'] ), 0 );
ck( 'the sync\'s row stands untouched', WPCPM_Institutions_Index::row( $other )['stage'], 'Confirmed' );
ck( 'and the account half is all that is left to do', $GLOBALS['journal'], array( 'user', 'attach', 'invite', 'audit' ) );

echo "\n-- every refusal, and what it leaves behind --\n";

reset_world();
$app = seed_application( 'new', array( WPCPM_Institution_Application::META_VERIFIED => '' ) );
$out = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'an unconfirmed address refuses', $out->get_error_code(), 'wpcpm_app_unverified' );
ck( 'and nothing at all was written', array( count( $GLOBALS['created'] ), count( $GLOBALS['users'] ), count( $GLOBALS['journal'] ) ), array( 0, 0, 0 ) );

reset_world();
$app = seed_application();
wp_insert_user(
	array(
		'user_login' => 'ada',
		'user_email' => 'contact@example.edu',
		'role'       => WPCPM_Roles::ROLE_STUDENT,
	)
);
$GLOBALS['journal'] = array();
$out                = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'an address that already has any account refuses', $out->get_error_code(), 'wpcpm_app_email' );
ck( 'and creates no record, whoever that account belongs to', count( $GLOBALS['created'] ), 0 );
ck( 'the lock is given back', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );

reset_world();
$app = seed_application( 'new', array( WPCPM_Institution_Application::META_COUNTRY => rid( 'GONE' ) ) );
$out = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'a country that stopped resolving refuses', $out->get_error_code(), 'wpcpm_app_country' );
ck( 'before any read of the base', count( $GLOBALS['searched'] ), 0 );
ck( 'and gives the lock back', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );

foreach ( array( 'approved', 'rejected', 'spam' ) as $state ) {
	reset_world();
	$app = seed_application( $state );
	$out = WPCPM_Institution_Approval::approve( $app, 7 );

	ck( sprintf( 'an application at %s is refused, not approved twice', $state ), $out->get_error_code(), 'wpcpm_app_state' );
}

reset_world();
$app                      = seed_application();
$GLOBALS['search_answer'] = new WP_Error( 'wpcpm_airtable_http', 'the base did not answer' );
$out                      = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'a search that could not be made is not "no duplicate"', $out->get_error_code(), 'wpcpm_app_search' );
ck( 'so nothing is created on a failed read', count( $GLOBALS['created'] ), 0 );
ck( 'and the lock is given back', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );

reset_world();
$app                      = seed_application();
$GLOBALS['create_answer'] = array();
$out                      = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'a create that answered with nothing is a refusal', $out->get_error_code(), 'wpcpm_app_airtable' );
ck( 'no account was made on the back of it', count( $GLOBALS['users'] ), 0 );
ck( 'nothing was stamped', get_post_meta( $app, WPCPM_Institution_Application::META_RECORD, true ), '' );
ck( 'and the lock is given back', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );

reset_world();
$app                    = seed_application();
$GLOBALS['manager_can'] = false;
$out                    = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'an actor who cannot manage the program refuses', $out->get_error_code(), 'wpcpm_app_actor' );

reset_world();
$out = WPCPM_Institution_Approval::approve( 999999, 7 );

ck( 'a post that is not an application refuses', $out->get_error_code(), 'wpcpm_app_unknown' );

reset_world();
$app = seed_application( 'new', array(), array( 'Contact Email' => 'contact@example.edu' ) );
$out = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'an application with no institution name refuses rather than matching every nameless record', $out->get_error_code(), 'wpcpm_app_name' );
ck( 'before the search runs', count( $GLOBALS['searched'] ), 0 );

reset_world();
$app = seed_application( 'new', array(), array( 'Name' => 'Universidad Example' ) );
$out = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'an application with no usable address refuses', $out->get_error_code(), 'wpcpm_app_no_email' );

echo "\n-- the lock a refusal must not touch --\n";

reset_world();
$app = seed_application( 'new', array( WPCPM_Institution_Application::META_VERIFIED => '' ) );
add_option( WPCPM_Institution_Approval::lock_name( $app ), time(), '', false );
$out = WPCPM_Institution_Approval::approve( $app, 7 );

ck( 'a refusal before the lock is taken refuses', $out->get_error_code(), 'wpcpm_app_unverified' );
ck( 'and leaves the lock another request is holding alone', false !== get_option( WPCPM_Institution_Approval::lock_name( $app ) ), true );

// The lock a crashed request leaves behind is the one row nothing else would ever remove:
// the application it names is deleted with everything else on the way out.
ck( 'uninstall sweeps the locks', WPCPM_Institution_Approval::delete_all(), 1 );
ck( 'and leaves nothing under the prefix', get_option( WPCPM_Institution_Approval::lock_name( $app ) ), false );

echo "\n-- the source, and the base's spelling --\n";

$src     = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-approval.php' );
$mine    = file_get_contents( __FILE__ );
$members = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-members.php' );
$fixed   = json_decode( file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/institutions-table-fields.json' ), true );

// The one refusal this class reads as an answer belongs to another file, and the stub above
// only promises what this suite says it does. Checked against the real source, because a
// renamed code would turn every resume into "the membership could not be made" without a
// single test noticing.
ck( 'the members class still refuses an account it has already attached with that code', preg_match( "/'" . WPCPM_Institution_Approval::MEMBER_ALREADY . "'/", $members ), 1 );

foreach ( WPCPM_Institutions_Sync::fields() as $key => $column ) {
	ck( sprintf( 'the base has a column called %s', $column ), in_array( $column, $fixed['fields'], true ), true );
}
ck( 'First Contact Made is a stage the base offers', in_array( WPCPM_Institution_Approval::NEW_STAGE, $fixed['choices']['Current Stage'], true ), true );
ck( 'Not started is an Agreement Status the base offers', in_array( WPCPM_Institution_Approval::AGREEMENT_NOT_STARTED, $fixed['choices']['Agreement Status'], true ), true );
ck( 'and the setting the stage is read from agrees with the fixture', in_array( (string) WPCPM_Settings::get_value( 'institution_new_stage', '' ), $fixed['choices']['Current Stage'], true ), true );

/**
 * One method's body out of a source file, from its signature to its closing brace.
 *
 * @param string $src  The file.
 * @param string $name The method name.
 * @return string
 */
function method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );

	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

$approve = method_body( $src, 'approve' );
$create  = method_body( $src, 'create' );

$offsets = array(
	'actor'    => strpos( $approve, 'user_can(' ),
	'state'    => strpos( $approve, 'self::approvable()' ),
	'verified' => strpos( $approve, 'META_VERIFIED' ),
	'lock'     => strpos( $approve, 'self::lock(' ),
	'email'    => strpos( $approve, "get_user_by( 'email'" ),
	'country'  => strpos( $approve, 'WPCPM_Countries::options()' ),
	'airtable' => strpos( $approve, 'self::search(' ),
	'index'    => strpos( $approve, 'self::index(' ),
	'account'  => strpos( $approve, 'self::account(' ),
	'state_on' => strpos( $approve, 'STATE_APPROVED' ),
	'audit'    => strpos( $approve, 'WPCPM_Institution_Audit::record(' ),
	'unlock'   => strpos( $approve, 'self::unlock(' ),
);
$sorted  = $offsets;
asort( $sorted );

ck( 'approve() reads in the order the design fixes', array_keys( $sorted ), array_keys( $offsets ) );
ck( 'the record half is stamped before the index half is even called', strpos( $approve, 'META_RECORD, $record' ) < $offsets['index'], true );
ck( 'the create merges rather than adds, so the server values win', array( substr_count( $create, 'array_merge(' ), substr_count( $create, '$stored +' ) ), array( 1, 0 ) );

// Every exit from the lock onwards goes through `refuse()`, which is the only place the lock
// is given back on a refusal. Counted rather than read: the one that goes missing is the one
// nobody notices until an application cannot be approved for the rest of the minute.
$after = substr( $approve, (int) strpos( $approve, 'wpcpm_app_busy' ) );

ck( 'every refusal from the lock onwards hands it back', substr_count( $after, 'return new WP_Error(' ), 0 );
ck( 'and there is one for every way the halves can stop', substr_count( $after, 'return self::refuse(' ), 8 );
ck( 'the audit row is written before the lock is released', strpos( $approve, 'WPCPM_Institution_Audit::record(' ) < strrpos( $approve, 'self::unlock(' ), true );

ck( 'the class writes no option that autoloads', substr_count( $src, "add_option( \$key, time(), '', false )" ), 1 );
ck( 'it is not a form handler and checks no nonce of its own', strpos( $src, 'check_admin_referer' ), false );
ck( 'it prints nothing', strpos( $src, 'echo ' ), false );
ck( 'it never names accessibility', strpos( $src, 'accessibility' ), false );
ck( 'no em dash or en dash in the class', preg_match( '/[\x{2013}\x{2014}]/u', $src ), 0 );
ck( 'nor in this suite', preg_match( '/[\x{2013}\x{2014}]/u', $mine ), 0 );

// The one comparison rule the module keeps breaking: an institution ID is compared inside
// the policy and nowhere else. Approval looks its records up in the index and hands them to
// `attach()`; it never asks whether two of them are the same.
ck( 'no institution ID is compared in this class', preg_match( '/\$record\s*(===|!==)/', $src ), 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
