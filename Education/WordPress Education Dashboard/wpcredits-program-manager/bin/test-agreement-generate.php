<?php
/**
 * Generating the Collaboration Agreement: the document, and the handler around it.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - **The document names the institution twice and prints no bracket.** Those two facts are
 *   the whole reason `WPCPM_Agreement_Template::merge()` exists, and the generator is the
 *   only thing that can undo them: a stylesheet with an attribute selector, a heading the
 *   renderer skipped, a footer that pasted the name a third time into the body. Counted
 *   here on the real template, over the real stylesheet.
 * - **The footer carries the version, the language and the date.** It is what a reviewer
 *   reads off a signed copy months later to know which wording was signed, and it is
 *   repeated on every sheet because page one is not the sheet in their hand.
 * - **The `<title>` is the filename.** The browser proposes it as the PDF's name; a sentence
 *   there produces a file called "Collaboration Agreement for Universidad Example.pdf" with
 *   spaces, or worse, "document.pdf".
 * - **One external resource, and it is the registered script.** The document is served from
 *   `admin-post.php` with no theme and no enqueue pass, so anything it links has to resolve
 *   on its own. The stylesheet is inlined for that reason, and the print script is the only
 *   thing left with a URL.
 * - **Curly quotes survive byte for byte.** The program's wording uses U+2018, U+2019,
 *   U+201C and U+201D, `esc_html()` leaves them alone, and a straight quote on a signed page
 *   is a difference from the Doc that this side's checksum would not catch. Counted per
 *   character against the template's own plain text.
 * - **`document()` is pure.** No post, no option, no request, no clock. That is what makes
 *   "Regenerate the template as they saw it" true rather than hopeful, and it is asserted
 *   twice: by running it twice, and by reading its source for the things it must not touch.
 * - **The handler's order.** Logged in, institution, nonce, `decide()`, the ceiling, the
 *   name, the merge, the post, Airtable, the document. The ceiling sits before the merge so
 *   a loop is stopped before it reads a template and inserts a post; Airtable sits last
 *   because it is the one write in this module allowed to fail without taking the request
 *   with it.
 * - **A member's own institution overrides any posted value.** Asserted through a resolver
 *   stub that follows section 5.5's three steps rather than answering a flag, because what
 *   this file has to be able to see is that the handler asks and acts on the answer.
 *
 * Three cases end in `echo` and `exit`, which cannot be observed from inside the process
 * that runs them. Those run this same file again as a child (`php bin/test-agreement-generate.php
 * child <case>`); the child prints the document, and a shutdown function prints the world it
 * left behind after a marker, so the parent can assert on both.
 *
 * The other pieces are stood in for exactly at their contracts, and every constant borrowed
 * from `WPCPM_Institution_Agreement` is pinned against the real file at the end.
 *
 * Run from the plugin root:  php bin/test-agreement-generate.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

/** A fixed clock, so the footer's date and the post's date are the same in both processes. */
define( 'WPCPM_TEST_CLOCK', 1788393600 );

/** What separates the child's document from the world it left behind. */
define( 'WPCPM_TEST_MARKER', '<<<WPCPM-STATE>>>' );

/** The scratch template language the merge-refusal block writes and deletes. */
define( 'WPCPM_TEST_LANGUAGE', 'zy' );

$GLOBALS['opts']      = array();
$GLOBALS['posts']     = array();
$GLOBALS['pmeta']     = array();
$GLOBALS['umeta']     = array();
$GLOBALS['users']     = array();
$GLOBALS['caps']      = false;
$GLOBALS['uid']       = 0;
$GLOBALS['logged_in'] = true;
$GLOBALS['next_id']   = 500;
$GLOBALS['nonces']    = array();
$GLOBALS['audit']     = array();
$GLOBALS['patched']   = array();
$GLOBALS['airtable']  = null;
$GLOBALS['allow']     = true;
$GLOBALS['decisions'] = array();
$GLOBALS['notified']  = array();
$GLOBALS['member_of'] = '';
$GLOBALS['fallback']  = '';
$GLOBALS['referer']   = 'https://example.test/institution-dashboard/';
$GLOBALS['view']      = '';
$GLOBALS['post_fail'] = false;
$GLOBALS['scripts']   = array();

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
	public $ID = 0, $display_name = '', $user_email = '';
	public function __construct( $id = 0, $name = '', $email = '' ) { $this->ID = $id; $this->display_name = $name; $this->user_email = $email; }
	public function exists() { return $this->ID > 0; }
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function esc_url( $u ) { return (string) $u; }
function esc_url_raw( $u, $p = null ) { return (string) $u; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) {
	$s = strtolower( trim( (string) $s ) );
	$s = preg_replace( '/[^a-z0-9]+/u', '-', $s );
	return trim( (string) $s, '-' );
}
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }
function wp_date( $f, $t = null, $z = null ) { return gmdate( $f, null === $t ? WPCPM_TEST_CLOCK : (int) $t ); }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_action( $h, $c, $p = 10, $a = 1 ) {}
function nocache_headers() {}
function wp_script_is( $handle, $list = 'enqueued' ) { return isset( $GLOBALS['scripts'][ $handle ] ); }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['scripts'][ $handle ] = array( 'src' => $src, 'ver' => $ver, 'footer' => $footer );
	return true;
}
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { $GLOBALS['nonces'][] = $a; return true; }
function current_user_can( $c ) { return (bool) $GLOBALS['caps']; }
function is_user_logged_in() { return (bool) $GLOBALS['logged_in']; }
function get_current_user_id() { return (int) $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_userdata( $id ) { return $GLOBALS['users'][ (int) $id ] ?? false; }
function wp_get_referer() { return '' !== $GLOBALS['referer'] ? $GLOBALS['referer'] : false; }
function wp_safe_redirect( $to ) { throw new Exception( 'redirect: ' . $to ); }
function wp_die( $m = '', $c = 0 ) { throw new Exception( 'wp_die: ' . $m ); }

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }

function get_user_meta( $id, $k = '', $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ( $single ? '' : array() ); }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }

function get_post_meta( $id, $key = '', $single = false ) {
	$rows = $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? array();
	if ( $single ) { return $rows ? $rows[0] : ''; }
	return $rows;
}
function add_post_meta( $id, $key, $value, $unique = false ) { $GLOBALS['pmeta'][ (int) $id ][ $key ][] = $value; return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = array( $value ); return true; }

function wp_insert_post( $a, $error = false ) {
	if ( ! empty( $GLOBALS['post_fail'] ) ) {
		return $error ? new WP_Error( 'wpcpm_test_insert', 'refused' ) : 0;
	}
	$post              = new WP_Post();
	$post->ID          = $GLOBALS['next_id']++;
	$post->post_type   = $a['post_type'] ?? 'post';
	$post->post_status = $a['post_status'] ?? 'publish';
	$post->post_author = (int) ( $a['post_author'] ?? 0 );
	$post->post_title  = $a['post_title'] ?? '';
	// The site's own clock, not an advancing one: `regenerate()` takes the date off the
	// post, and a document generated at 23:59 and reproduced a minute later must be the
	// same bytes.
	$post->post_date               = gmdate( 'Y-m-d H:i:s', WPCPM_TEST_CLOCK );
	$GLOBALS['posts'][ $post->ID ] = $post;
	return $post->ID;
}
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', '1.72.1' );

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ceiling.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-agreement-template.php';

/* ---- the other pieces, stubbed to their contracts ----------------------- */

if ( ! class_exists( 'WPCPM_Mentors_Sync' ) ) {
	class WPCPM_Mentors_Sync {
		public static function is_record_id( $value ) {
			return (bool) preg_match( '/^rec[A-Za-z0-9]{14}$/', trim( (string) $value ) );
		}
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institutions-index.php';

if ( ! class_exists( 'WPCPM_Institution_Agreement' ) ) {
	/**
	 * The agreement record, at the constants this piece writes and the one option it reads.
	 *
	 * Every constant here is pinned against the real file in the last block, because a
	 * generated post whose state meta is spelled differently from the one the panel and the
	 * queue read is a document nobody can see.
	 */
	class WPCPM_Institution_Agreement {
		const POST_TYPE             = 'wpcpm_agreement';
		const POST_STATUS           = 'private';
		const META_INSTITUTION      = '_wpcpm_agr_institution';
		const META_STATE            = '_wpcpm_agr_state';
		const META_KIND             = '_wpcpm_agr_kind';
		const META_LANGUAGE         = '_wpcpm_agr_language';
		const META_TEMPLATE_VERSION = '_wpcpm_agr_template_version';
		const META_NAME_ON_DOCUMENT = '_wpcpm_agr_name_on_document';
		const META_DECIDED_BY       = '_wpcpm_agr_decided_by';
		const META_AIRTABLE_PENDING = '_wpcpm_agr_airtable_pending';
		const META_EVENT            = '_wpcpm_agr_event';
		const STATE_GENERATED       = 'generated';
		const STATE_SUBMITTED       = 'submitted';
		const STATE_ACCEPTED        = 'accepted';
		const STATE_RETURNED        = 'returned';
		const STATE_REVOKED         = 'revoked';
		const KIND_TEMPLATE         = 'template';
		const KIND_OWN              = 'own';
		const KIND_LEGACY           = 'legacy';
		const SUMMARY_NONE          = 'none';
		const SUMMARY_GENERATED     = 'generated';
		const SUMMARY_SUBMITTED     = 'submitted';
		const SUMMARY_RETURNED      = 'returned';
		const SUMMARY_REVOKED       = 'revoked';
		const SUMMARY_ACCEPTED      = 'accepted';
		const SUMMARY_ON_FILE       = 'on_file';
		public static function option( $record_id ) {
			$row = get_option( 'wpcpm_agreement_' . trim( (string) $record_id ) );
			return is_array( $row ) ? $row : null;
		}

		/**
		 * The site half of the summary, worked out from the posts the way the real one is.
		 *
		 * Computed rather than answered, for the reason the resolver stub is computed: a
		 * stub that handed back whichever state a block asked for would pass a handler that
		 * never asked. An accepted post wins whatever else exists, a legacy one makes that
		 * `on_file`, a submitted post is "in review", and otherwise the most recent
		 * returned, revoked or generated post names the state. Newest first, which is the
		 * order `posts_for()` reads them in.
		 *
		 * @param string $record_id Institutions record ID.
		 * @return array{state: string, kind: string}
		 */
		public static function summary( $record_id ) {
			$record_id = trim( (string) $record_id );
			$accepted  = null;
			$pending   = 0;
			$latest    = '';

			foreach ( array_reverse( $GLOBALS['posts'], true ) as $post ) {
				if ( $record_id !== (string) get_post_meta( $post->ID, self::META_INSTITUTION, true ) ) {
					continue;
				}

				$state = (string) get_post_meta( $post->ID, self::META_STATE, true );

				if ( self::STATE_ACCEPTED === $state && null === $accepted ) {
					$accepted = $post;
				} elseif ( self::STATE_SUBMITTED === $state && ! $pending ) {
					$pending = (int) $post->ID;
				}

				if ( '' === $latest && in_array( $state, array( self::STATE_RETURNED, self::STATE_REVOKED, self::STATE_GENERATED ), true ) ) {
					$latest = $state;
				}
			}

			if ( $accepted instanceof WP_Post ) {
				$kind = (string) get_post_meta( $accepted->ID, self::META_KIND, true );

				return array( 'state' => self::KIND_LEGACY === $kind ? self::SUMMARY_ON_FILE : self::SUMMARY_ACCEPTED, 'kind' => $kind );
			}

			if ( $pending ) {
				return array( 'state' => self::SUMMARY_SUBMITTED, 'kind' => '' );
			}

			return array( 'state' => '' !== $latest ? $latest : self::SUMMARY_NONE, 'kind' => '' );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Policy' ) ) {
	/** The fence, at its contract: two subject builders, one refusal, one recorded ask. */
	class WPCPM_Institution_Policy {
		const ACT_AGREEMENT  = 'agreement';
		const GROUND_MANAGER = 'manager';
		const GROUND_MEMBER  = 'member';
		const REFUSAL_CODE   = 'wpcpm_inst_unknown';
		public static function subject_institution( $record_id ) {
			return array(
				'type'            => 'institution',
				'id'              => (string) $record_id,
				'institution_ids' => array( (string) $record_id ),
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
			$GLOBALS['decisions'][] = array( $action, $subject['type'], $subject['id'] );

			if ( ! $GLOBALS['allow'] ) {
				return array( 'allowed' => false, 'ground' => '', 'institution' => '', 'fields' => array(), 'why' => 'no-ground' );
			}

			if ( $GLOBALS['caps'] ) {
				return array( 'allowed' => true, 'ground' => self::GROUND_MANAGER, 'institution' => (string) ( $subject['institution_ids'][0] ?? '' ), 'fields' => null, 'why' => '' );
			}

			// Membership of an institution the subject names, which is what refuses a member
			// of one institution asking for another's document.
			if ( '' !== $GLOBALS['member_of'] && in_array( $GLOBALS['member_of'], (array) $subject['institution_ids'], true ) ) {
				return array( 'allowed' => true, 'ground' => self::GROUND_MEMBER, 'institution' => $GLOBALS['member_of'], 'fields' => null, 'why' => '' );
			}

			return array( 'allowed' => false, 'ground' => '', 'institution' => '', 'fields' => array(), 'why' => 'no-ground' );
		}
		public static function refusal() {
			return new WP_Error( self::REFUSAL_CODE, 'That record is not on your roster.' );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Roster' ) ) {
	/**
	 * Section 5.5's three steps, run rather than answered.
	 *
	 * The switcher only for a manager and only for a record the index holds, then the
	 * viewer's own membership, then a manager's fallback. A stub that returned whatever the
	 * form posted would pass a handler that let a member generate another institution's
	 * agreement, which is the one thing this resolution rule exists to stop.
	 */
	class WPCPM_Institution_Roster {
		const ARG_VIEW = 'wpcpm_institution_view';
		public static function resolve_institution( $viewer, $can_manage ) {
			if ( $can_manage ) {
				$asked = WPCPM_Request::text( self::ARG_VIEW );
				if ( WPCPM_Mentors_Sync::is_record_id( $asked ) && WPCPM_Institutions_Index::has( $asked ) ) {
					return trim( $asked );
				}
			}

			if ( '' !== $GLOBALS['member_of'] ) {
				return $GLOBALS['member_of'];
			}

			return $can_manage ? $GLOBALS['fallback'] : '';
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Sync' ) ) {
	/** Only the field map the T2 write reads. Checked against the fixture below. */
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
	/** The table the write names and the one ceiling this piece reads. */
	class WPCPM_Settings {
		public static function get() {
			return array( 'institutions_table' => 'tbl4V0FEbzRP7I2w2' );
		}
		public static function get_value( $key, $fallback = null ) {
			return $GLOBALS['settings'][ $key ] ?? $fallback;
		}
	}
}

if ( ! class_exists( 'WPCPM_Airtable' ) ) {
	/** The one write path, recorded so the cells and the choices can be read back. */
	class WPCPM_Airtable {
		public function __construct( $settings = null ) {}
		public function update_records( $table, array $records ) {
			$GLOBALS['patched'][] = array( $table, $records );
			if ( $GLOBALS['airtable'] instanceof WP_Error ) { return $GLOBALS['airtable']; }
			if ( is_array( $GLOBALS['airtable'] ) ) { return $GLOBALS['airtable']; }
			return array( $records[0]['id'] => true );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institution_Audit' ) ) {
	/** The log, at its contract, refusals included. */
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
			$GLOBALS['audit'][] = $entry;
			return 900 + count( $GLOBALS['audit'] );
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions' ) ) {
	/** The manager screen: its flash channel, and the one route to the program managers. */
	class WPCPM_Institutions {
		const FLASH = 'institutions';
		public static function notify_managers( $context, $build ) {
			if ( ! is_callable( $build ) ) { return 0; }
			$mail                = call_user_func( $build, new WP_User( 9, 'A Manager', 'manager@example.test' ) );
			$GLOBALS['notified'][] = array( $context, (string) $mail['subject'], (string) $mail['body'] );
			return 1;
		}
	}
}

if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	/** Where a refusal lands when the request carried no referer. */
	class WPCPM_Institutions_Dashboard {
		public static function page_url() { return 'https://example.test/institution-dashboard/'; }
	}
}

if ( ! class_exists( 'WPCPM_Mail' ) ) {
	/** Only the site name the manager notice puts in its subject. */
	class WPCPM_Mail {
		public static function site_name() { return 'WordPress Education'; }
	}
}

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-agreement-generate.php';

$GLOBALS['settings'] = array();

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
 * Forget every option, post, message and recorded call.
 *
 * The capability goes back to a member's, because every block below says who is acting and
 * a block that inherited the last one's manager would pass for the wrong reason.
 */
function reset_world() {
	$GLOBALS['opts']      = array();
	$GLOBALS['posts']     = array();
	$GLOBALS['pmeta']     = array();
	$GLOBALS['umeta']     = array();
	$GLOBALS['caps']      = false;
	$GLOBALS['logged_in'] = true;
	$GLOBALS['nonces']    = array();
	$GLOBALS['audit']     = array();
	$GLOBALS['patched']   = array();
	$GLOBALS['airtable']  = null;
	$GLOBALS['allow']     = true;
	$GLOBALS['decisions'] = array();
	$GLOBALS['notified']  = array();
	$GLOBALS['member_of'] = '';
	$GLOBALS['fallback']  = '';
	$GLOBALS['view']      = '';
	$GLOBALS['post_fail'] = false;
	$GLOBALS['settings']  = array();
	$GLOBALS['next_id']   = 500;
	$_GET                 = array();
	$_POST                = array();
	$_REQUEST             = array();
}

/**
 * What the last handler flashed, without consuming it.
 *
 * Read out of the meta rather than through `WPCPM_Flash::take()`, which memoizes per user
 * and channel for the length of a request: every block below is a fresh request on the live
 * site, and the memo would hand each one the outcome of the block before it.
 *
 * @return string
 */
function flashed() {
	$pending = $GLOBALS['umeta'][ $GLOBALS['uid'] ]['wpcpm_flash'] ?? array();

	return (string) ( $pending[ WPCPM_Institutions::FLASH ] ?? '' );
}

/**
 * Seed the pipeline index with one institution.
 *
 * @param string $record Institutions record ID.
 * @param string $status What the base's `Agreement Status` said at the last read.
 */
function seed_index( $record, $status = '' ) {
	$row                           = WPCPM_Institutions_Index::empty_row();
	$row['record_id']              = $record;
	$row['name']                   = 'Universidad Example';
	$row['stage']                  = 'Confirmed';
	$row['agreement']['status']    = $status;

	WPCPM_Institutions_Index::write( array( $record => $row ), WPCPM_TEST_CLOCK - 3600 );
}

/**
 * Sign in as somebody.
 *
 * @param int    $id      User ID.
 * @param bool   $manager Whether they hold CAP_MANAGE.
 * @param string $member  The institution they are a member of, or ''.
 */
function sign_in( $id, $manager, $member = '' ) {
	$GLOBALS['uid']            = (int) $id;
	$GLOBALS['users'][ $id ]   = new WP_User( (int) $id, 'A Person', 'person@example.edu' );
	$GLOBALS['caps']           = (bool) $manager;
	$GLOBALS['member_of']      = (string) $member;
}

/**
 * Put one agreement post on an institution, in the state a block needs it in.
 *
 * @param string $record Institutions record ID.
 * @param string $state  A `_wpcpm_agr_state` value.
 * @param string $kind   A `_wpcpm_agr_kind` value.
 * @return int The post ID.
 */
function seed_agreement( $record, $state, $kind = 'template' ) {
	$id = wp_insert_post(
		array(
			'post_type'   => WPCPM_Institution_Agreement::POST_TYPE,
			'post_status' => WPCPM_Institution_Agreement::POST_STATUS,
			'post_author' => 31,
			'post_title'  => 'seeded',
		)
	);

	update_post_meta( $id, WPCPM_Institution_Agreement::META_INSTITUTION, $record );
	update_post_meta( $id, WPCPM_Institution_Agreement::META_STATE, $state );
	update_post_meta( $id, WPCPM_Institution_Agreement::META_KIND, $kind );

	return (int) $id;
}

/** The merged English template every document block below is built from. */
function merged_template( $name ) {
	return WPCPM_Agreement_Template::merge( WPCPM_Agreement_Template::load( 'en' ), $name );
}

/** What the child process leaves behind, in a shape JSON can carry. */
function child_state() {
	$posts = array();

	foreach ( $GLOBALS['posts'] as $id => $post ) {
		$meta = array();

		foreach ( $GLOBALS['pmeta'][ $id ] ?? array() as $key => $rows ) {
			$meta[ $key ] = $rows;
		}

		$posts[] = array(
			'id'     => $id,
			'type'   => $post->post_type,
			'status' => $post->post_status,
			'author' => $post->post_author,
			'date'   => $post->post_date,
			'meta'   => $meta,
		);
	}

	return array(
		'posts'     => $posts,
		'audit'     => $GLOBALS['audit'],
		'patched'   => $GLOBALS['patched'],
		'nonces'    => $GLOBALS['nonces'],
		'decisions' => $GLOBALS['decisions'],
		'flash'     => $GLOBALS['umeta'][ $GLOBALS['uid'] ]['wpcpm_flash'] ?? array(),
		'notified'  => $GLOBALS['notified'],
	);
}

/**
 * Run one of the cases that ends in `echo` and `exit`, as a child process would.
 *
 * @param string $case Case name.
 */
function run_child( $case ) {
	reset_world();
	seed_index( 'recGENERATE000001' );
	sign_in( 31, false, 'recGENERATE000001' );

	$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ]     = 'Universidad Example';
	$_POST[ WPCPM_Agreement_Generate::FIELD_LANGUAGE ] = 'en';

	if ( 'pending' === $case ) {
		$GLOBALS['airtable'] = new WP_Error( 'wpcpm_airtable_http', 'the base did not answer' );
	}

	if ( 'regenerate' === $case ) {
		$id = wp_insert_post(
			array(
				'post_type'   => WPCPM_Institution_Agreement::POST_TYPE,
				'post_status' => WPCPM_Institution_Agreement::POST_STATUS,
				'post_author' => 31,
				'post_title'  => 'seeded',
			)
		);
		update_post_meta( $id, WPCPM_Institution_Agreement::META_INSTITUTION, 'recGENERATE000001' );
		update_post_meta( $id, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_GENERATED );
		update_post_meta( $id, WPCPM_Institution_Agreement::META_KIND, WPCPM_Institution_Agreement::KIND_TEMPLATE );
		update_post_meta( $id, WPCPM_Institution_Agreement::META_LANGUAGE, 'en' );
		update_post_meta( $id, WPCPM_Institution_Agreement::META_TEMPLATE_VERSION, WPCPM_Agreement_Template::version( WPCPM_Agreement_Template::load( 'en' ) ) );
		update_post_meta( $id, WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT, 'Universidad Example' );

		$_GET[ WPCPM_Agreement_Generate::FIELD_POST ] = (string) $id;
	}

	WPCPM_Agreement_Generate::handle_generate();
}

if ( isset( $argv[1] ) && 'child' === (string) $argv[1] ) {
	// The document is echoed and the request stops. Everything worth asserting happened
	// before that, so it is printed after a marker from a shutdown function, which runs
	// after `exit` and is the only seam a terminal handler leaves.
	register_shutdown_function(
		function () {
			echo "\n" . WPCPM_TEST_MARKER . "\n" . wp_json_encode( child_state() ) . "\n";
		}
	);

	run_child( isset( $argv[2] ) ? (string) $argv[2] : '' );
	exit( 0 );
}

/**
 * Run this file again for one terminal case, and split what it printed.
 *
 * @param string $case Case name.
 * @return array{document: string, state: array}
 */
function child( $case ) {
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child ' . escapeshellarg( $case );
	$out     = (string) shell_exec( $command );
	$parts   = explode( "\n" . WPCPM_TEST_MARKER . "\n", $out, 2 );

	return array(
		'document' => $parts[0],
		'state'    => isset( $parts[1] ) ? (array) json_decode( trim( $parts[1] ), true ) : array(),
	);
}

/**
 * The declarations of one CSS rule, as a map.
 *
 * Read as declarations rather than matched as a substring because the two questions this
 * file has to ask about the print stylesheet are what a property is set to and whether it is
 * set at all: `bottom: 0` on the printed footer is the whole of the bug that erased a line
 * of the agreement on every sheet, and a substring match cannot say "and nothing else
 * anchors it".
 *
 * @param string $sheet    The stylesheet.
 * @param string $selector The selector, exactly as it is written.
 * @param bool   $print    Read the copy inside `@media print` rather than the one outside.
 * @return array<string, string>
 */
function css_rule( $sheet, $selector, $print = false ) {
	$at    = (int) strpos( $sheet, '@media print' );
	$scope = $print ? substr( $sheet, $at ) : substr( $sheet, 0, $at );
	$out   = array();

	if ( 1 !== preg_match( '/(?:^|\s)' . preg_quote( $selector, '/' ) . ' \{([^}]*)\}/s', $scope, $found ) ) {
		return $out;
	}

	foreach ( explode( ';', $found[1] ) as $line ) {
		if ( false === strpos( $line, ':' ) ) {
			continue;
		}

		$pair = explode( ':', trim( $line ), 2 );

		$out[ trim( $pair[0] ) ] = trim( $pair[1] );
	}

	return $out;
}

/** The body of one method, for the source-level blocks. */
function method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );

	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

$source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-agreement-generate.php' );
$css    = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/agreement-print.css' );
$today  = gmdate( 'Y-m-d', WPCPM_TEST_CLOCK );

/* ---- the document ------------------------------------------------------- */

echo "=== The document, from the real template and the real stylesheet ===\n";

reset_world();

$name     = 'Universidad Example';
$template = WPCPM_Agreement_Template::load( 'en' );
$merged   = merged_template( $name );
$version  = WPCPM_Agreement_Template::version( $template );
$doc      = WPCPM_Agreement_Generate::document( $merged, $name, $today );

$main = substr( $doc, (int) strpos( $doc, '<main' ), (int) strpos( $doc, '</main>' ) - (int) strpos( $doc, '<main' ) );

ck( 'it is a whole document, top to bottom', array( 0 === strpos( $doc, '<!DOCTYPE html>' ), '</html>' === trim( substr( $doc, -8 ) ) ), array( true, true ) );
ck( 'the institution is named exactly twice in the agreement itself', substr_count( $main, $name ), WPCPM_Agreement_Template::OCCURRENCES );
ck( 'and once more in the footer, which is the whole document', substr_count( $doc, $name ), WPCPM_Agreement_Template::OCCURRENCES + 1 );
ck( 'no bracket of any kind survives, in the text, the markup or the stylesheet',
    array( strpos( $doc, '[' ), strpos( $doc, ']' ) ),
    array( false, false ) );

ck( 'the title is the filename the browser will propose', 1 === preg_match( '#<title>([^<]+)</title>#', $doc, $m ) ? $m[1] : '', 'Collaboration-Agreement-WordPress-Credits-universidad-example' );
ck( 'and filename() is that string on its own', WPCPM_Agreement_Generate::filename( $name ), 'Collaboration-Agreement-WordPress-Credits-universidad-example' );
ck( 'a name nothing can be slugged from leaves no trailing hyphen', WPCPM_Agreement_Generate::filename( '...' ), 'Collaboration-Agreement-WordPress-Credits' );

$footer = 1 === preg_match( '#<footer class="wpcpm-agreement__footer">(.*)</footer>#', $doc, $m ) ? $m[1] : '';

ck( 'the footer reads exactly what the design fixes', $footer, 'WordPress Credits Program - Collaboration Agreement - template ' . $version . ' (en) - generated for ' . $name . ' on ' . $today );
ck( 'so it carries the version, the language and the date',
    array( false !== strpos( $footer, $version ), false !== strpos( $footer, '(en)' ), false !== strpos( $footer, $today ) ),
    array( true, true, true ) );
// Where the footer prints, which is a box-model question this file got wrong twice before it
// got it right, so these assert the mechanism and not the spelling. A `position: fixed` box
// repeats on every sheet but is OUT of the flow, so the text is laid out as though it were
// not there: `bottom: 0` printed it over the last lines of every full sheet, and `top: 100%`
// put it outside the page area, where print output is clipped, losing it from the first sheet
// and repeating it at the top of the next. A table's footer group is IN the flow: pagination
// reserves its height on every sheet before it breaks the rows, so no line of the agreement
// can be laid out where the footer will print, whatever the footer's height. That is the only
// arrangement of the three that both repeats and cannot overlap, so it is what is asserted.
$screen_footer = css_rule( $css, '.wpcpm-agreement__footer' );
$print_footer  = css_rule( $css, '.wpcpm-agreement__footer', true );
$print_body    = css_rule( $css, '.wpcpm-agreement-print', true );
$print_main    = css_rule( $css, '.wpcpm-agreement', true );
$footer_gap    = 1 === preg_match( '/^(-?\d+(?:\.\d+)?)mm/', isset( $print_footer['padding-top'] ) ? $print_footer['padding-top'] : '', $m ) ? (float) $m[1] : -1.0;

ck( 'in print the document is a table, its text the row group and its footer the footer group',
    array(
		isset( $print_body['display'] ) ? $print_body['display'] : '',
		isset( $print_main['display'] ) ? $print_main['display'] : '',
		isset( $print_footer['display'] ) ? $print_footer['display'] : '',
	),
    array( 'table', 'table-row-group', 'table-footer-group' ) );

// The two mechanisms that put it over the text, named so neither can come back quietly.
ck( 'and it is taken out of the flow by nothing, on either medium',
    array(
		isset( $print_footer['position'] ) ? $print_footer['position'] : 'static',
		isset( $screen_footer['position'] ) ? $screen_footer['position'] : 'static',
	),
    array( 'static', 'static' ) );
ck( 'it is anchored to no edge, which is what put it outside the page area last time',
    array(
		isset( $print_footer['top'] ) ? $print_footer['top'] : 'unanchored',
		isset( $print_footer['bottom'] ) ? $print_footer['bottom'] : 'unanchored',
		isset( $screen_footer['bottom'] ) ? $screen_footer['bottom'] : 'unanchored',
	),
    array( 'unanchored', 'unanchored', 'unanchored' ) );
ck( 'the gap above it is padding inside the reserved height, so widening it moves the text and not the footer', $footer_gap > 0, true );
ck( 'and nothing paints it opaque, so a collision would show rather than erase',
    array( isset( $screen_footer['background'] ), isset( $print_footer['background'] ) ),
    array( false, false ) );

ck( 'the stylesheet is inlined and nothing is linked',
    array( substr_count( $doc, '<style>' ), substr_count( $doc, '<link' ), false !== strpos( $doc, '@page' ), false !== strpos( $doc, 'size: A4' ) ),
    array( 1, 0, true, true ) );
ck( 'the page is A4 with a 20mm margin and an 11pt body',
    array( 1 === preg_match( '/@page \{\s*size: A4;\s*margin: 20mm;\s*\}/', $css ), false !== strpos( $css, 'font-size: 11pt;' ) ),
    array( true, true ) );

// The stylesheet is the one part of the document that has not been through `merge()`, so
// it is where a bracket could get back onto a page somebody signs. Driven by putting one
// there: the file is restored immediately, and again from a shutdown function if this
// block never finishes.
$sheet = WPCPM_PLUGIN_DIR . 'assets/css/agreement-print.css';
register_shutdown_function(
	function () use ( $sheet, $css ) {
		if ( (string) file_get_contents( $sheet ) !== $css ) {
			file_put_contents( $sheet, $css );
		}
	}
);

file_put_contents( $sheet, $css . "\n.wpcpm-agreement__p" . '[data-x] { color: red; }' . "\n" );
$bracketed = WPCPM_Agreement_Generate::document( $merged, $name, $today );
file_put_contents( $sheet, $css );

ck( 'a stylesheet with a bracket in it is dropped whole rather than printed',
    array( strpos( $bracketed, '[' ), false !== strpos( $bracketed, '@page' ), false !== strpos( $bracketed, 'Collaboration Agreement' ) ),
    array( false, false, true ) );
ck( 'and the real one is back', WPCPM_Agreement_Generate::document( $merged, $name, $today ), $doc );

ck( 'the print script is the only external resource',
    array( substr_count( $doc, ' src="' ), substr_count( $doc, 'href' ) ),
    array( 1, 0 ) );
ck( 'and it is the registered one', 1 === preg_match( '#<script src="([^"]+)"></script>#', $doc, $m ) ? $m[1] : '', WPCPM_Agreement_Generate::script_url() );

WPCPM_Agreement_Generate::register_assets();

ck( 'which is the URL the handle is registered under, plus the version',
    WPCPM_Agreement_Generate::script_url(),
    $GLOBALS['scripts'][ WPCPM_Agreement_Generate::SCRIPT ]['src'] . '?ver=' . WPCPM_VERSION );
ck( 'and it points at the file that calls window.print()',
    array( false !== strpos( WPCPM_Agreement_Generate::script_url(), 'assets/js/agreement-print.js' ), false !== strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/agreement-print.js' ), 'window.print()' ) ),
    array( true, true ) );

/* ---- the wording, byte for byte ----------------------------------------- */

echo "\n=== The wording survives the render ===\n";

$plain  = WPCPM_Agreement_Template::plain_text( $merged );
$curly  = array( "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}" );
$counts = array();

foreach ( $curly as $character ) {
	$counts[] = array( substr_count( $plain, $character ), substr_count( $doc, $character ) );
}

ck( 'every curly quote in the template is in the document, and no others',
    $counts,
    array( array( 0, 0 ), array( 2, 2 ), array( 2, 2 ), array( 2, 2 ) ) );
ck( 'the two the wording depends on are there in full',
    array( false !== strpos( $doc, "\u{201C}the Institution\u{201D}" ), false !== strpos( $doc, "institution\u{2019}s website" ) ),
    array( true, true ) );

$headings = 0;
$items    = 0;

foreach ( $template['blocks'] as $block ) {
	if ( in_array( $block['type'], array( 'h1', 'h2', 'h3' ), true ) ) {
		++$headings;
	}
	if ( 'ul' === $block['type'] ) {
		$items += count( $block['items'] );
	}
}

ck( 'every heading and every bullet reached the page',
    array( substr_count( $doc, '<h1 ' ) + substr_count( $doc, '<h2 ' ) + substr_count( $doc, '<h3 ' ), substr_count( $doc, '<li>' ) ),
    array( $headings, $items ) );
ck( 'the Code of Conduct address prints as text, not as a link', array( false !== strpos( $doc, 'https://make.wordpress.org/handbook/community-code-of-conduct/' ), substr_count( $doc, '<a ' ) ), array( true, 0 ) );

$escaped = WPCPM_Agreement_Generate::document( merged_template( 'Escuela A & B' ), 'Escuela A & B', $today );

ck( 'a name with an ampersand is escaped, in the body and in the footer',
    array( substr_count( $escaped, 'Escuela A &amp; B' ), strpos( $escaped, 'Escuela A & B' ) ),
    array( 3, false ) );

/* ---- the signature blocks ------------------------------------------------ */

echo "\n=== Two signature columns, three ruled lines each ===\n";

ck( 'there are two parties and six lines to fill in',
    array( substr_count( $main, 'class="wpcpm-agreement__party"' ), substr_count( $main, 'wpcpm-agreement__line-rule' ) ),
    array( 2, 6 ) );
ck( 'each party carries three of them',
    array_map(
        function ( $part ) {
			return substr_count( $part, 'wpcpm-agreement__line-rule' );
		},
        array_slice( explode( '<div class="wpcpm-agreement__party">', $main ), 1 )
    ),
    array( 3, 3 ) );
ck( 'and they are labelled the way the template names them',
    array( substr_count( $main, '>Name</span>' ), substr_count( $main, '>Title</span>' ), substr_count( $main, '>Date</span>' ) ),
    array( 2, 2, 2 ) );
$row     = css_rule( $css, '.wpcpm-agreement__signatures' );
$column  = css_rule( $css, '.wpcpm-agreement__party' );
$gap     = 1 === preg_match( '/^(\d+(?:\.\d+)?)mm$/', isset( $row['gap'] ) ? $row['gap'] : '', $m ) ? (float) $m[1] : -1.0;
$share   = 1 === preg_match( '/calc\((\d+(?:\.\d+)?)% - (\d+(?:\.\d+)?)mm\)$/', isset( $column['flex'] ) ? $column['flex'] : '', $m ) ? array( (float) $m[1], (float) $m[2] ) : array( -1.0, -1.0 );

ck( 'the columns are two, side by side', array( isset( $row['display'] ) ? $row['display'] : '', $gap > 0 ), array( 'flex', true ) );

// The arithmetic, from the file's own numbers. A flex gap is space between the columns and
// it comes out of the row: asking for half the row each and then adding the gap on top is
// 12mm more than the row has, and the overflow lands on the second column, which is the
// institution's. Its three ruled lines then start 12mm past half way and run off the right
// of the page area into the margin, 12mm short of the width they were drawn at. Two columns
// and one gap have to be exactly the row, whatever the two numbers are changed to.
ck( 'and two columns plus the gap are exactly the row, not the row plus the gap',
    array( 2 * $share[0], 2 * $share[1], isset( $column['width'] ) ),
    array( 100.0, $gap, false ) );
ck( 'and the lines are ruled, not underscores', 1 === preg_match( '/\.wpcpm-agreement__line-rule \{[^}]*border-bottom: 1px solid/s', $css ), true );
ck( 'a signature block is never split across two sheets', 1 === preg_match( '/\.wpcpm-agreement__signatures \{[^}]*break-inside: avoid;/s', $css ), true );

/* ---- purity -------------------------------------------------------------- */

echo "\n=== document() is pure ===\n";

ck( 'the same three values give the same bytes', WPCPM_Agreement_Generate::document( merged_template( $name ), $name, $today ), $doc );

$pure = method_body( $source, 'document' ) . method_body( $source, 'blocks' ) . method_body( $source, 'list_block' )
	. method_body( $source, 'signatures' ) . method_body( $source, 'footer' ) . method_body( $source, 'filename' );

ck( 'and it reads no post, no option, no request and no clock',
    array(
		substr_count( $pure, 'get_post' ),
		substr_count( $pure, 'get_option' ),
		substr_count( $pure, 'WPCPM_Request' ),
		substr_count( $pure, 'wp_date(' ),
		substr_count( $pure, 'current_user' ),
	),
    array( 0, 0, 0, 0, 0 ) );

/* ---- the handler: who it generates for ---------------------------------- */

echo "\n=== Which institution the document is for ===\n";

reset_world();
seed_index( 'recMEMBEROWN00001' );
seed_index( 'recOTHERPLACE0001' );
sign_in( 31, false, 'recMEMBEROWN00001' );

$_POST[ WPCPM_Agreement_Generate::FIELD_RECORD ]   = 'recOTHERPLACE0001';
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ]     = 'Universidad Example';
$_POST[ WPCPM_Agreement_Generate::FIELD_LANGUAGE ] = 'en';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( "a member's own institution overrides the posted one, in the nonce", $GLOBALS['nonces'], array( WPCPM_Agreement_Generate::ACTION_GENERATE . '_recMEMBEROWN00001' ) );
ck( 'and in what was decided on', $GLOBALS['decisions'], array( array( 'agreement', 'institution', 'recMEMBEROWN00001' ) ) );
ck( 'and in the post that was written', get_post_meta( 500, WPCPM_Institution_Agreement::META_INSTITUTION, true ), 'recMEMBEROWN00001' );
ck( 'and the document was printed rather than a redirect', $outcome, 'nothing' );

reset_world();
seed_index( 'recSWITCHEDTO0001' );
sign_in( 7, true );
$GLOBALS['fallback']                    = 'recFALLBACK000001';
$_GET[ WPCPM_Institution_Roster::ARG_VIEW ] = 'recSWITCHEDTO0001';
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Example';

try {
	WPCPM_Agreement_Generate::generate();
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( "a manager's switcher is honoured", $GLOBALS['nonces'], array( WPCPM_Agreement_Generate::ACTION_GENERATE . '_recSWITCHEDTO0001' ) );

reset_world();
seed_index( 'recSWITCHEDTO0001' );
sign_in( 7, true );
$GLOBALS['fallback']                             = 'recFALLBACK000001';
$_POST[ WPCPM_Agreement_Generate::FIELD_RECORD ] = 'recSWITCHEDTO0001';
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ]   = 'Universidad Example';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

// Manager on behalf, which is the half of the switcher that has to survive the POST. The
// switcher is a query argument on the dashboard and this form posts to `admin-post.php`,
// which carries no query string, so the record the panel drew the form for travels in the
// form. Without reading it the handler resolved a manager to their fallback institution,
// checked a nonce keyed to that one against a form keyed to another, and died on the nonce
// before anything in this block happened.
ck( 'a manager generates for the institution the form was drawn for',
    array( $GLOBALS['nonces'], $outcome ),
    array( array( WPCPM_Agreement_Generate::ACTION_GENERATE . '_recSWITCHEDTO0001' ), 'nothing' ) );
ck( 'and the decision, the post and the log row all name that one, not the fallback',
    array( $GLOBALS['decisions'][0][2], get_post_meta( 500, WPCPM_Institution_Agreement::META_INSTITUTION, true ), $GLOBALS['audit'][0]['institution'] ),
    array( 'recSWITCHEDTO0001', 'recSWITCHEDTO0001', 'recSWITCHEDTO0001' ) );

reset_world();
seed_index( 'recSWITCHEDTO0001' );
sign_in( 7, true );
$GLOBALS['fallback']                             = 'recSWITCHEDTO0001';
$_POST[ WPCPM_Agreement_Generate::FIELD_RECORD ] = 'recNOTINTHEINDEX1';
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ]   = 'Universidad Example';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'a posted record the index does not hold is no record at all, and the resolver answers',
    array( $GLOBALS['nonces'], $outcome ),
    array( array( WPCPM_Agreement_Generate::ACTION_GENERATE . '_recSWITCHEDTO0001' ), 'nothing' ) );

reset_world();
seed_index( 'recMEMBEROWN00001' );
seed_index( 'recOTHERPLACE0001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$_POST[ WPCPM_Agreement_Generate::FIELD_RECORD ] = 'recOTHERPLACE0001';
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ]   = 'Universidad Example';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'and a member who posts the same field is still placed by their membership',
    array( $GLOBALS['nonces'], get_post_meta( 500, WPCPM_Institution_Agreement::META_INSTITUTION, true ) ),
    array( array( WPCPM_Agreement_Generate::ACTION_GENERATE . '_recMEMBEROWN00001' ), 'recMEMBEROWN00001' ) );

reset_world();
sign_in( 31, false );

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'somebody who is a member of nothing is refused before the nonce', array( $outcome, $GLOBALS['nonces'] ), array( 'wp_die: That record is not on your roster.', array() ) );

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$GLOBALS['logged_in'] = false;

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'and a stranger never reaches the resolver', array( $outcome, $GLOBALS['decisions'] ), array( 'wp_die: Please log in to generate the Collaboration Agreement.', array() ) );

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$GLOBALS['allow'] = false;
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Example';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'a refusal from the fence is the one refusal, and nothing is written', array( $outcome, count( $GLOBALS['posts'] ) ), array( 'wp_die: That record is not on your roster.', 0 ) );

/* ---- the handler: the ceiling and the name ------------------------------ */

echo "\n=== The ceiling, then the name, then the merge ===\n";

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$GLOBALS['settings'] = array( 'agreement_generations_per_day' => 2 );
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Example';

for ( $i = 0; $i < 2; $i++ ) {
	WPCPM_Agreement_Generate::generate();
}

$before = count( $GLOBALS['posts'] );

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'the setting is the limit, and the next one is refused', array( $before, $outcome ), array( 2, 'redirect: https://example.test/institution-dashboard/' ) );
ck( 'nothing was written for the refused one', count( $GLOBALS['posts'] ), 2 );
ck( 'and the panel is told it was the ceiling', flashed(), 'agreement-busy' );
ck( 'the ceiling is keyed to the institution and the day', WPCPM_Ceiling::count( 'agreement-generate:recMEMBEROWN00001', DAY_IN_SECONDS ), 2 );
ck( 'and another institution has its own', WPCPM_Ceiling::count( 'agreement-generate:recOTHERPLACE0001', DAY_IN_SECONDS ), 0 );

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = '   ';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'an empty name generates nothing at all', array( $outcome, count( $GLOBALS['posts'] ), count( $GLOBALS['patched'] ) ), array( 'redirect: https://example.test/institution-dashboard/', 0, 0 ) );
ck( 'and says so through the panel', flashed(), 'agreement-name' );

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$GLOBALS['referer'] = '';
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = '';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'a refusal with no referer lands on the dashboard', $outcome, 'redirect: https://example.test/institution-dashboard/' );

$GLOBALS['referer'] = 'https://example.test/institution-dashboard/';

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = str_repeat( "\u{00e9}", 260 );

WPCPM_Agreement_Generate::generate();

ck( 'a long name is cut in characters, not bytes', mb_strlen( (string) get_post_meta( 500, WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT, true ) ), WPCPM_Agreement_Generate::MAX_NAME );

/* ---- the handler: T2's From set ----------------------------------------- */

echo "\n=== What a generate may be asked over ===\n";

// T2's From column is `none`, `generated`, `returned`, `revoked`: every state in which the
// institution still owes the program a signed agreement. The three left out are the ones
// where a document already stands, and a generate over one of those inserts a second
// `generated` post and patches `Agreement Kind` to `Program template` - over the `Own` kind
// of an institution that signed its own paper, or the `Legacy` kind of a copy the program
// has held for years. The base is then saying it signed something it did not, which is why
// the refusal matters more than the second post does. The panel draws no form on those
// three, but the panel is a courtesy: this is where the answer is.
//
// After the ceiling, and the claim is spent on a refusal, because the Regenerate link sits
// between the two and must go on working for an institution that has since signed: the
// budget is a control on the route, not on the outcome, which is the order the upload
// handler uses too.
foreach ( array(
	'a fresh institution'      => array( '', '', true, '' ),
	'one that generated once'  => array( 'generated', 'template', true, '' ),
	'one that was returned'    => array( 'returned', 'template', true, '' ),
	'one that was revoked'     => array( 'revoked', 'own', true, '' ),
	'one waiting for review'   => array( 'submitted', 'template', false, 'agreement-generate-in-review' ),
	'one accepted on template' => array( 'accepted', 'template', false, 'agreement-generate-standing' ),
	'one accepted on its own'  => array( 'accepted', 'own', false, 'agreement-generate-standing' ),
	'one held on file'         => array( 'accepted', 'legacy', false, 'agreement-generate-standing' ),
) as $label => $case ) {
	list( $state, $kind, $allowed, $slug ) = $case;

	reset_world();
	seed_index( 'recMEMBEROWN00001' );
	sign_in( 31, false, 'recMEMBEROWN00001' );

	if ( '' !== $state ) {
		seed_agreement( 'recMEMBEROWN00001', $state, $kind );
	}

	$standing                                      = count( $GLOBALS['posts'] );
	$outcome                                       = 'nothing';
	$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Example';

	try {
		WPCPM_Agreement_Generate::generate();
	} catch ( Exception $e ) {
		$outcome = $e->getMessage();
	}

	ck(
		sprintf( '%s %s', $label, $allowed ? 'may generate' : 'is refused, by name, and nothing reaches the base' ),
		array( count( $GLOBALS['posts'] ) - $standing, count( $GLOBALS['patched'] ), flashed(), $outcome ),
		$allowed
			? array( 1, 1, '', 'nothing' )
			: array( 0, 0, $slug, 'redirect: https://example.test/institution-dashboard/' )
	);
}

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
seed_agreement( 'recMEMBEROWN00001', 'accepted', 'own' );

$printed = seed_agreement( 'recMEMBEROWN00001', 'superseded', 'template' );
update_post_meta( $printed, WPCPM_Institution_Agreement::META_LANGUAGE, 'en' );
update_post_meta( $printed, WPCPM_Institution_Agreement::META_TEMPLATE_VERSION, $version );
update_post_meta( $printed, WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT, 'Universidad Example' );
$_GET[ WPCPM_Agreement_Generate::FIELD_POST ]  = (string) $printed;
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Example';

// The other half of putting the fence after the Regenerate branch: an institution that has
// signed is exactly when somebody asks to see the template it was given.
ck( 'and an institution that has signed can still be shown the template it printed', WPCPM_Agreement_Generate::generate(), $doc );

/* ---- the handler: a template the merge refuses -------------------------- */

echo "\n=== A template with a placeholder nobody fills in ===\n";

$scratch = WPCPM_PLUGIN_DIR . 'includes/templates/collaboration-agreement-' . WPCPM_TEST_LANGUAGE . '.php';
register_shutdown_function(
	function () use ( $scratch ) {
		if ( file_exists( $scratch ) ) {
			unlink( $scratch );
		}
	}
);

$broken                      = $template;
$broken['language']          = WPCPM_TEST_LANGUAGE;
$broken['blocks'][0]['text'] = 'Collaboration Agreement [Signatory Title]';
file_put_contents( $scratch, "<?php\nreturn " . var_export( $broken, true ) . ";\n" );

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ]     = 'Universidad Example';
$_POST[ WPCPM_Agreement_Generate::FIELD_LANGUAGE ] = WPCPM_TEST_LANGUAGE;

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'the reader is told the template needs attention, not shown a placeholder', $outcome, 'wp_die: The agreement template needs attention; a program manager has been told. Nothing was generated, and nothing is wrong at your end.' );
ck( 'nothing was generated and nothing was written to the base', array( count( $GLOBALS['posts'] ), count( $GLOBALS['patched'] ) ), array( 0, 0 ) );
ck( 'and the people who can fix it were mailed', count( $GLOBALS['notified'] ) ? array( $GLOBALS['notified'][0][0], $GLOBALS['notified'][0][1] ) : array(), array( 'agreement-template', '[WordPress Education] The Collaboration Agreement template needs attention' ) );
$refused = WPCPM_Agreement_Template::load( WPCPM_TEST_LANGUAGE );

ck( 'with the institution and the reason the template gave, verbatim',
    count( $GLOBALS['notified'] ) ? array( false !== strpos( $GLOBALS['notified'][0][2], 'recMEMBEROWN00001' ), false !== strpos( $GLOBALS['notified'][0][2], $refused->get_error_message() ) ) : array(),
    array( true, true ) );

unlink( $scratch );

/* ---- the handler: the other bracket ------------------------------------- */

echo "\n=== A bracket the reader typed is not a template that needs attention ===\n";

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Ejemplo [Sede Norte]';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

// `merge()` refuses a bracket in the name as well as a bracket in the template, and both
// come back as `wpcpm_template_placeholder`: after the merge there is nothing left to tell
// them apart by. Routed together, a member's own typing produced a 500 that told them
// nothing was wrong at their end and mailed every program manager a false alarm about a
// template that was fine. The name is the reader's, so it is read before the merge and
// answered as a name.
ck( 'a name with a bracket in it is refused as a name, not as a 500',
    array( $outcome, count( $GLOBALS['posts'] ), count( $GLOBALS['patched'] ) ),
    array( 'redirect: https://example.test/institution-dashboard/', 0, 0 ) );
ck( 'the panel gets a slug that says what to fix', flashed(), 'agreement-name-brackets' );
ck( 'and nobody is mailed about a template that is fine', $GLOBALS['notified'], array() );

$typed = WPCPM_Agreement_Template::merge( $template, 'Universidad Ejemplo [Sede Norte]' );

ck( 'which is worth doing here, because the merge answers both with one code',
    array( is_wp_error( $typed ) ? $typed->get_error_code() : '', is_wp_error( $refused ) ? $refused->get_error_code() : '' ),
    array( 'wpcpm_template_placeholder', 'wpcpm_template_placeholder' ) );

/* ---- the handler: what lands on the base and in the log ----------------- */

echo "\n=== The post, the log and the base ===\n";

$run = child( 'generate' );

ck( 'the child printed exactly the document document() builds', $run['document'], WPCPM_Agreement_Generate::document( merged_template( 'Universidad Example' ), 'Universidad Example', $today ) );
ck( 'one post was written, private, authored by the person who pressed it',
    array( count( $run['state']['posts'] ), $run['state']['posts'][0]['type'], $run['state']['posts'][0]['status'], $run['state']['posts'][0]['author'] ),
    array( 1, 'wpcpm_agreement', 'private', 31 ) );

$meta = $run['state']['posts'][0]['meta'];

ck( 'with the institution, the state, the kind, the language, the version and the name',
    array(
		$meta['_wpcpm_agr_institution'][0],
		$meta['_wpcpm_agr_state'][0],
		$meta['_wpcpm_agr_kind'][0],
		$meta['_wpcpm_agr_language'][0],
		$meta['_wpcpm_agr_template_version'][0],
		$meta['_wpcpm_agr_name_on_document'][0],
		$meta['_wpcpm_agr_decided_by'][0],
	),
    array( 'recGENERATE000001', 'generated', 'template', 'en', $version, 'Universidad Example', 31 ) );
ck( 'and one event row naming who and when', array( count( $meta['_wpcpm_agr_event'] ), $meta['_wpcpm_agr_event'][0]['event'], $meta['_wpcpm_agr_event'][0]['actor'] ), array( 1, 'template generated', 31 ) );
ck( 'nothing was stamped as pending, because the base took the write', isset( $meta['_wpcpm_agr_airtable_pending'] ), false );

ck( 'the log row carries the ground the decision gave',
    array( $run['state']['audit'][0]['kind'], $run['state']['audit'][0]['institution'], $run['state']['audit'][0]['ground'], $run['state']['audit'][0]['evidence'] ),
    array( 'agreement_generated', 'recGENERATE000001', 'member', 'index' ) );
ck( 'and names the template it generated', array( $run['state']['audit'][0]['data']['template_version'], $run['state']['audit'][0]['data']['language'] ), array( $version, 'en' ) );

$cells = $run['state']['patched'][0][1][0]['fields'];

ck( 'one PATCH, on the Institutions table, for this record',
    array( count( $run['state']['patched'] ), $run['state']['patched'][0][0], $run['state']['patched'][0][1][0]['id'] ),
    array( 1, 'tbl4V0FEbzRP7I2w2', 'recGENERATE000001' ) );
ck( 'writing the kind, the version and the status, and no stage',
    array( $cells['Agreement Kind'], $cells['Agreement Template Version'], $cells['Agreement Status'], isset( $cells['Current Stage'] ) ),
    array( 'Program template', $version, 'Template generated', false ) );

$fixture = json_decode( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'bin/fixtures/institutions-table-fields.json' ), true );

ck( 'every cell it names is a column the base has', array_values( array_diff( array_keys( $cells ), $fixture['fields'] ) ), array() );
ck( 'and both choices are choices the base offers',
    array( in_array( 'Template generated', $fixture['choices']['Agreement Status'], true ), in_array( 'Program template', $fixture['choices']['Agreement Kind'], true ) ),
    array( true, true ) );

/* ---- the handler: Airtable may fail, the download may not --------------- */

echo "\n=== A base that does not answer does not stop the download ===\n";

$run = child( 'pending' );

ck( 'the document was printed anyway', $run['document'], WPCPM_Agreement_Generate::document( merged_template( 'Universidad Example' ), 'Universidad Example', $today ) );
ck( 'the post stands and is stamped for the sync to retry',
    array( count( $run['state']['posts'] ), $run['state']['posts'][0]['meta']['_wpcpm_agr_airtable_pending'][0] ),
    array( 1, 1 ) );
ck( 'and the panel says the base is behind on their next page load', $run['state']['flash'], array( 'institutions' => 'agreement-generated-later' ) );

/* ---- the status the write may overwrite --------------------------------- */

echo "\n=== The status is only written over an open one ===\n";

foreach ( array( '' => true, 'Not started' => true, 'Awaiting review' => false, 'Accepted' => false, 'Returned' => false ) as $status => $expected ) {
	reset_world();
	seed_index( 'recMEMBEROWN00001', $status );
	sign_in( 31, false, 'recMEMBEROWN00001' );
	$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Example';

	WPCPM_Agreement_Generate::generate();

	$cells = $GLOBALS['patched'][0][1][0]['fields'];

	ck( sprintf( 'a row at "%s" %s', $status, $expected ? 'has its status rewritten' : 'keeps the status it has' ), isset( $cells['Agreement Status'] ), $expected );
	ck( '  and the kind and the version are written either way', array( isset( $cells['Agreement Kind'] ), isset( $cells['Agreement Template Version'] ) ), array( true, true ) );
}

reset_world();
seed_index( 'recMEMBEROWN00001', 'Not started' );
sign_in( 31, false, 'recMEMBEROWN00001' );
update_option( 'wpcpm_agreement_recMEMBEROWN00001', array( 'v' => 1, 'airtable_status' => 'Awaiting review' ) );
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Example';

WPCPM_Agreement_Generate::generate();

ck( 'the option is read before the index, because a site transition rewrites it first', isset( $GLOBALS['patched'][0][1][0]['fields']['Agreement Status'] ), false );

/* ---- regenerate ---------------------------------------------------------- */

echo "\n=== Regenerate the template as they saw it ===\n";

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Example';

WPCPM_Agreement_Generate::generate();

$generated = 500;

ck( 'the same bytes come back from the post alone', WPCPM_Agreement_Generate::regenerate( $generated ), $doc );

$superseded = wp_insert_post( array( 'post_type' => WPCPM_Institution_Agreement::POST_TYPE, 'post_status' => 'private', 'post_author' => 31, 'post_title' => 'seeded' ) );
update_post_meta( $superseded, WPCPM_Institution_Agreement::META_INSTITUTION, 'recMEMBEROWN00001' );
update_post_meta( $superseded, WPCPM_Institution_Agreement::META_STATE, 'superseded' );
update_post_meta( $superseded, WPCPM_Institution_Agreement::META_KIND, WPCPM_Institution_Agreement::KIND_TEMPLATE );
update_post_meta( $superseded, WPCPM_Institution_Agreement::META_LANGUAGE, 'en' );
update_post_meta( $superseded, WPCPM_Institution_Agreement::META_TEMPLATE_VERSION, $version );
update_post_meta( $superseded, WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT, 'Universidad Example' );

ck( 'a superseded generated post reproduces too, which is when a reviewer asks', WPCPM_Agreement_Generate::regenerate( $superseded ), $doc );

update_post_meta( $superseded, WPCPM_Institution_Agreement::META_TEMPLATE_VERSION, '2024-01-01' );
$older = WPCPM_Agreement_Generate::regenerate( $superseded );

ck( 'a document from a template the plugin has moved past refuses rather than lies', is_wp_error( $older ) ? $older->get_error_code() : $older, 'wpcpm_agreement_version' );
ck( 'and names both versions', is_wp_error( $older ) ? array( false !== strpos( $older->get_error_message(), '2024-01-01' ), false !== strpos( $older->get_error_message(), $version ) ) : array(), array( true, true ) );

update_post_meta( $superseded, WPCPM_Institution_Agreement::META_TEMPLATE_VERSION, $version );
update_post_meta( $superseded, WPCPM_Institution_Agreement::META_KIND, WPCPM_Institution_Agreement::KIND_OWN );
$own = WPCPM_Agreement_Generate::regenerate( $superseded );

ck( 'an uploaded document has nothing to reproduce', is_wp_error( $own ) ? $own->get_error_code() : $own, 'wpcpm_agreement_kind' );

$unknown = WPCPM_Agreement_Generate::regenerate( 999999 );

ck( 'and a post that is not ours is unknown', is_wp_error( $unknown ) ? $unknown->get_error_code() : $unknown, 'wpcpm_agreement_unknown' );

$run = child( 'regenerate' );

ck( 'the Regenerate link prints the reproduced document', $run['document'], $doc );
ck( 'and writes nothing: no second post, no PATCH, no log row',
    array( count( $run['state']['posts'] ), count( $run['state']['patched'] ), count( $run['state']['audit'] ) ),
    array( 1, 0, 0 ) );
ck( 'it decided on the document, not on the form', $run['state']['decisions'][1], array( 'agreement', 'agreement', 500 ) );

reset_world();
seed_index( 'recMEMBEROWN00001' );
seed_index( 'recOTHERPLACE0001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$other = wp_insert_post( array( 'post_type' => WPCPM_Institution_Agreement::POST_TYPE, 'post_status' => 'private', 'post_author' => 8, 'post_title' => 'seeded' ) );
update_post_meta( $other, WPCPM_Institution_Agreement::META_INSTITUTION, 'recOTHERPLACE0001' );
update_post_meta( $other, WPCPM_Institution_Agreement::META_KIND, WPCPM_Institution_Agreement::KIND_TEMPLATE );
$_GET[ WPCPM_Agreement_Generate::FIELD_POST ] = (string) $other;

try {
	WPCPM_Agreement_Generate::regenerate( $other );
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( "a member asking for another institution's document gets the one refusal", $outcome, 'wp_die: That record is not on your roster.' );

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$_GET[ WPCPM_Agreement_Generate::FIELD_POST ] = '424242';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'a link to a post that does not exist says so and generates nothing', array( $outcome, count( $GLOBALS['posts'] ) ), array( 'redirect: https://example.test/institution-dashboard/', 0 ) );
ck( 'through the panel', flashed(), 'agreement-unknown' );

/* ---- the language -------------------------------------------------------- */

echo "\n=== The language ===\n";

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ]     = 'Universidad Example';
$_POST[ WPCPM_Agreement_Generate::FIELD_LANGUAGE ] = 'fr';

WPCPM_Agreement_Generate::generate();

ck( 'a language with no template falls back to English rather than refusing', get_post_meta( 500, WPCPM_Institution_Agreement::META_LANGUAGE, true ), 'en' );
ck( 'and the languages offered are the files that exist', WPCPM_Agreement_Template::languages(), array( 'en' ) );

/* ---- a post that will not save ------------------------------------------- */

echo "\n=== A post that will not save ===\n";

reset_world();
seed_index( 'recMEMBEROWN00001' );
sign_in( 31, false, 'recMEMBEROWN00001' );
$GLOBALS['post_fail'] = true;
$_POST[ WPCPM_Agreement_Generate::FIELD_NAME ] = 'Universidad Example';

try {
	WPCPM_Agreement_Generate::generate();
	$outcome = 'nothing';
} catch ( Exception $e ) {
	$outcome = $e->getMessage();
}

ck( 'nothing is printed that the site has no record of', array( $outcome, count( $GLOBALS['patched'] ) ), array( 'redirect: https://example.test/institution-dashboard/', 0 ) );

// Not `agreement-not-saved`, which is the on-file route's and says Airtable was updated and
// a Refresh will complete it. On this route the base has not been touched when the insert
// fails - T2 patches it after the post, because it is the one write allowed to fail - so
// both halves of that sentence would be false, and the reader would be sent to press a
// button that has nothing to finish.
ck( 'and the panel says what is true here: nothing was written anywhere', flashed(), 'agreement-generate-not-saved' );
ck( 'which is not the sentence the on-file route flashes', flashed() === 'agreement-not-saved', false );

/* ---- source level -------------------------------------------------------- */

echo "\n=== Read off the source ===\n";

$handler = method_body( $source, 'generate' );
$offsets = array(
	'logged in' => strpos( $handler, 'is_user_logged_in(' ),
	'resolve'   => strpos( $handler, 'self::record_for_request(' ),
	'nonce'     => strpos( $handler, 'check_admin_referer(' ),
	'decide'    => strpos( $handler, 'WPCPM_Institution_Policy::decide(' ),
	'ceiling'   => strpos( $handler, 'WPCPM_Ceiling::claim(' ),
	'regen'     => strpos( $handler, 'self::print_again(' ),
	'from'      => strpos( $handler, 'self::current_state(' ),
	'name'      => strpos( $handler, 'self::clean_name(' ),
	'merge'     => strpos( $handler, 'WPCPM_Agreement_Template::merge(' ),
	'post'      => strpos( $handler, 'self::insert_post(' ),
	'airtable'  => strpos( $handler, 'self::write_airtable(' ),
	'document'  => strpos( $handler, 'self::document(' ),
);
$sorted = $offsets;
asort( $sorted );

ck( 'the handler reads in the order the design fixes', array_keys( $sorted ), array_keys( $offsets ) );
ck( 'and the route itself is the send around it', trim( method_body( $source, 'handle_generate' ) ), 'function handle_generate() {' . "\n\t\t" . 'self::send( self::generate() );' );
ck( 'the route is admin_post_ and never admin_post_nopriv_',
    array( substr_count( $source, "'admin_post_' . self::ACTION_GENERATE" ), substr_count( $source, 'admin_post_nopriv' ) ),
    array( 1, 0 ) );
ck( 'the form is read with the posted readers, never the query string, except the link',
    array( substr_count( $handler, 'WPCPM_Request::posted_' ), substr_count( $handler, 'WPCPM_Request::text(' ) ),
    array( 2, 0 ) );

$resolver = method_body( $source, 'record_for_request' );

// The record a manager acts on comes out of the form, because the switcher's query argument
// does not survive a POST to `admin-post.php`. It is read for a manager only, asked of the
// capability here rather than taken from the form, and only for a record the index holds -
// and then it is what the nonce is keyed to, so a form drawn for one institution still
// cannot be replayed at another.
ck( 'the record a manager acts on is posted too, and is nobody else\'s to post',
    array(
		substr_count( $resolver, 'WPCPM_Request::posted_text( self::FIELD_RECORD )' ),
		substr_count( $resolver, 'WPCPM_Request::text(' ),
		substr_count( $resolver, 'current_user_can( WPCPM_Roles::CAP_MANAGE )' ),
		substr_count( $resolver, 'WPCPM_Institutions_Index::has(' ),
		substr_count( $resolver, 'WPCPM_Institution_Roster::resolve_institution(' ),
	),
    array( 1, 0, 1, 1, 1 ) );
ck( 'no institution ID is compared with === outside the policy', substr_count( $source, "=== \$record" ) + substr_count( $source, "\$record ===" ), 0 );
ck( 'the ceiling is claimed before a template is read or a post is written',
    array( $offsets['ceiling'] < $offsets['merge'], $offsets['ceiling'] < $offsets['post'] ),
    array( true, true ) );
ck( 'and T2\'s From set is settled after the Regenerate branch and before both of those',
    array( $offsets['regen'] < $offsets['from'], $offsets['from'] < $offsets['merge'], $offsets['from'] < $offsets['post'] ),
    array( true, true, true ) );
ck( 'and the Airtable write is the last thing before the document', $offsets['airtable'] < $offsets['document'], true );
ck( 'nothing here writes an em dash or an en dash',
    array( substr_count( $source, "\u{2014}" ), substr_count( $source, "\u{2013}" ), substr_count( $css, "\u{2014}" ), substr_count( $css, "\u{2013}" ) ),
    array( 0, 0, 0, 0 ) );

$real = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-agreement.php' );
$pins = array();

// The summary states are borrowed twice over: the stub above works them out from the posts
// the way the real class does, and the handler's own `FROM_STATES` is written in them. A
// state spelled differently on the two sides would let a generate through over an agreement
// that stands, which is the one thing the From set exists to stop.
$borrowed = array(
	'POST_TYPE'             => 'wpcpm_agreement',
	'POST_STATUS'           => 'private',
	'META_INSTITUTION'      => '_wpcpm_agr_institution',
	'META_STATE'            => '_wpcpm_agr_state',
	'META_KIND'             => '_wpcpm_agr_kind',
	'META_LANGUAGE'         => '_wpcpm_agr_language',
	'META_TEMPLATE_VERSION' => '_wpcpm_agr_template_version',
	'META_NAME_ON_DOCUMENT' => '_wpcpm_agr_name_on_document',
	'META_DECIDED_BY'       => '_wpcpm_agr_decided_by',
	'META_AIRTABLE_PENDING' => '_wpcpm_agr_airtable_pending',
	'META_EVENT'            => '_wpcpm_agr_event',
	'STATE_GENERATED'       => 'generated',
	'STATE_SUBMITTED'       => 'submitted',
	'STATE_ACCEPTED'        => 'accepted',
	'STATE_RETURNED'        => 'returned',
	'STATE_REVOKED'         => 'revoked',
	'KIND_TEMPLATE'         => 'template',
	'KIND_OWN'              => 'own',
	'KIND_LEGACY'           => 'legacy',
	'SUMMARY_NONE'          => 'none',
	'SUMMARY_GENERATED'     => 'generated',
	'SUMMARY_SUBMITTED'     => 'submitted',
	'SUMMARY_RETURNED'      => 'returned',
	'SUMMARY_REVOKED'       => 'revoked',
	'SUMMARY_ACCEPTED'      => 'accepted',
	'SUMMARY_ON_FILE'       => 'on_file',
);

foreach ( $borrowed as $constant => $value ) {
	$pins[ $constant ] = 1 === preg_match( '/const ' . $constant . '\s*=\s*\'([^\']+)\'/', $real, $m ) ? $m[1] : 'missing';
}

ck( 'every constant this piece borrows is the one the real agreement class declares', $pins, $borrowed );
ck( 'and the From set is four of those summary states, not four strings that look like them',
    WPCPM_Agreement_Generate::FROM_STATES,
    array( $borrowed['SUMMARY_NONE'], $borrowed['SUMMARY_GENERATED'], $borrowed['SUMMARY_RETURNED'], $borrowed['SUMMARY_REVOKED'] ) );

$panel = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-panel.php' );

// Every slug this piece flashes has to have words on the other side. The panel prints
// nothing for a slug it does not know, so a refusal with no row reads as silence, which is
// the one outcome worse than a refusal. The list is a contract between two files and this is
// where it is checked; a slug added here without a row there fails from this line.
//
// Read out of this file rather than typed here, so the contract cannot drift: every
// `agreement-` string it holds is a flash slug bar one, the mail context, which is named
// rather than skipped by pattern.
preg_match_all( "/'(agreement-[a-z-]+)'/", $source, $found );

$flashed = array_values( array_diff( array_unique( $found[1] ), array( WPCPM_Agreement_Generate::NOTIFY_TEMPLATE ) ) );
$slugs   = array();

foreach ( $flashed as $slug ) {
	if ( false === strpos( $panel, "'" . $slug . "'" ) ) {
		$slugs[] = $slug;
	}
}

// Four of them are new in this round and the panel's own piece is adding the words, since
// `WPCPM_Institution_Panel::messages()` is where every outcome in this module is written
// once. They are named here so that this stays a contract rather than a hole: anything else
// flashed without a row fails on the next line, and a slug renamed in this file without its
// name changing here fails on the one after.
$owed = array( 'agreement-generate-in-review', 'agreement-generate-not-saved', 'agreement-generate-standing', 'agreement-name-brackets' );

$handed = array_values( array_intersect( $flashed, $owed ) );
sort( $handed );
sort( $slugs );

ck( 'the panel has words for every outcome this piece flashes, bar the four being handed to it', array_values( array_diff( $slugs, $owed ) ), array() );
ck( 'and those four are flashed by this file, so the names the panel is given are these', $handed, $owed );
ck( 'the record a manager acts on travels in the panel\'s own forms',
    array( WPCPM_Agreement_Generate::FIELD_RECORD, false !== strpos( $panel, 'wpcpm_agreement_record' ) || false !== strpos( $panel, 'FIELD_RECORD' ) ),
    array( 'wpcpm_agreement_record', true ) );
ck( 'and the flash goes to the channel the panel reads', substr_count( $source, 'WPCPM_Flash::set( WPCPM_Institutions::FLASH,' ), 2 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
