<?php
/**
 * The institution's record on the site (1.123.29): one private post per institution, keyed by
 * its Airtable record ID, holding the answers the application asks for that Airtable has no
 * column for.
 *
 * What is pinned here and why:
 *
 * - **The type is invisible everywhere.** Not public, no UI, no REST route, no archive, no
 *   rewrite, no query variable, and a capability type nobody is granted: a row names an
 *   institution's contact and who signs for it. It is registered on `init`, and its name is
 *   under WordPress's twenty-character limit.
 * - **`fill_empty()` is the one way in, and it only fills.** An unknown institution gets one
 *   private post whose title and `_wpcpm_inst_record` meta are the record ID and whose
 *   `_wpcpm_inst_answers` meta holds the answers. A second call fills the keys that are empty and
 *   keeps the others, and says `false` when it changed nothing, which is what a second press of
 *   Approve and an approval that joined a record the program already had both rely on: the first
 *   answers stand. A blank answer is not an answer: a call with only blank ones writes no post.
 * - **A record ID is `rec` and fourteen letters or digits.** Anything else is refused and writes
 *   nothing, whatever type it arrives as.
 * - **The words survive the round trip.** Post meta is unslashed by core, so the write slashes
 *   first; a backslash or a quote comes back as typed.
 * - **One institution, one post.** A post another process trashed or drafted is still the
 *   institution's post, found rather than duplicated.
 * - **`delete_all()` removes every row,** in any status, and nothing else.
 * - **The class writes no option,** so nothing it keeps is ever autoloaded.
 *
 * The stand-ins below model enough of WordPress for a private post type with two meta values:
 * `wp_insert_post()` unslashes the post array as core does and fails for the title
 * `$GLOBALS['insert_fail']` names, `update_post_meta()` unslashes, and `get_posts()` honors
 * `post_type`, `post_status`, `meta_key`/`meta_value`, `fields` and `numberposts`.
 *
 * Run from the plugin root:  php bin/test-institution-record.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['posts']       = array();
$GLOBALS['pmeta']       = array();
$GLOBALS['post_types']  = array();
$GLOBALS['hooks']       = array();
$GLOBALS['next_post']   = 100;
$GLOBALS['insert_fail'] = '';      // A post title wp_insert_post() refuses.
$GLOBALS['meta_writes'] = 0;       // How many times update_post_meta() was called.
$GLOBALS['options']     = 0;       // How many option writes were made.

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

class WP_Post {
	public $ID = 0;
	public $post_type = 'post';
	public $post_status = 'draft';
	public $post_title = '';
	public $post_name = '';
	public $post_author = 0;
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
	return $text;
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][] = array( $hook, $callback );
}

function register_post_type( $type, $args ) {
	$GLOBALS['post_types'][ $type ] = $args;
}

function wp_slash( $value ) {
	return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
}

function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}

// Options are not this class's: a write to one is counted so the suite can say there were none.
function update_option( $key, $value, $autoload = null ) {
	++$GLOBALS['options'];
	return true;
}

function add_option( $key, $value = '', $deprecated = '', $autoload = null ) {
	++$GLOBALS['options'];
	return true;
}

function wp_insert_post( $postarr, $wp_error = false ) {
	$postarr = wp_unslash( $postarr );

	if ( '' !== $GLOBALS['insert_fail'] && $GLOBALS['insert_fail'] === ( isset( $postarr['post_title'] ) ? $postarr['post_title'] : '' ) ) {
		return new WP_Error( 'db_insert_error', 'Could not insert post into the database.' );
	}

	$id   = $GLOBALS['next_post']++;
	$post = new WP_Post();

	$post->ID          = $id;
	$post->post_type   = isset( $postarr['post_type'] ) ? $postarr['post_type'] : 'post';
	$post->post_status = isset( $postarr['post_status'] ) ? $postarr['post_status'] : 'draft';
	$post->post_title  = isset( $postarr['post_title'] ) ? $postarr['post_title'] : '';
	$post->post_author = isset( $postarr['post_author'] ) ? (int) $postarr['post_author'] : 0;

	$GLOBALS['posts'][ $id ] = $post;

	return $id;
}

function get_post( $id ) {
	return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ] : null;
}

function get_post_meta( $id, $key = '', $single = false ) {
	return isset( $GLOBALS['pmeta'][ (int) $id ][ $key ] ) ? $GLOBALS['pmeta'][ (int) $id ][ $key ] : '';
}

// Core unslashes what it is handed; that is the whole reason the class slashes first.
function update_post_meta( $id, $key, $value ) {
	++$GLOBALS['meta_writes'];
	$GLOBALS['pmeta'][ (int) $id ][ $key ] = wp_unslash( $value );
	return true;
}

function wp_delete_post( $id, $force = false ) {
	unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] );
	return true;
}

function get_posts( $args = array() ) {
	$GLOBALS['last_get_posts'] = $args;
	$out                       = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
			continue;
		}

		if ( isset( $args['post_status'] ) ) {
			if ( 'any' === $args['post_status'] ) {
				// Core's own 'any' leaves trash and auto-draft out.
				if ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					continue;
				}
			} elseif ( ! in_array( $post->post_status, (array) $args['post_status'], true ) ) {
				continue;
			}
		}

		if ( isset( $args['meta_key'] ) ) {
			$have = get_post_meta( $post->ID, $args['meta_key'], true );

			if ( '' === $have || (string) $have !== (string) ( isset( $args['meta_value'] ) ? $args['meta_value'] : '' ) ) {
				continue;
			}
		}

		$out[] = $post;
	}

	usort(
		$out,
		static function ( $a, $b ) {
			return $a->ID - $b->ID;
		}
	);

	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) {
		$out = array_slice( $out, 0, (int) $args['numberposts'] );
	}

	if ( 'ids' === ( isset( $args['fields'] ) ? $args['fields'] : '' ) ) {
		$out = array_map(
			static function ( $post ) {
				return $post->ID;
			},
			$out
		);
	}

	return $out;
}

define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-record.php';

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

/** Forget every post, meta row and counter, so a block starts from an empty site. */
function reset_world() {
	$GLOBALS['posts']       = array();
	$GLOBALS['pmeta']       = array();
	$GLOBALS['next_post']   = 100;
	$GLOBALS['insert_fail'] = '';
	$GLOBALS['meta_writes'] = 0;
	$GLOBALS['options']     = 0;
}

/**
 * The posts of the record type, in ID order.
 *
 * @return WP_Post[]
 */
function records() {
	$out = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'wpcpm_inst_record' === $post->post_type ) {
			$out[] = $post;
		}
	}

	return $out;
}

$one = 'recINSTITUTION001';
$two = 'recINSTITUTION002';

echo "\n-- the type: private everywhere, registered on init --\n";

ck( 'the post type is wpcpm_inst_record', WPCPM_Institution_Record::POST_TYPE, 'wpcpm_inst_record' );
ck( 'under WordPress\'s twenty characters', strlen( WPCPM_Institution_Record::POST_TYPE ) <= 20, true );
ck( 'the posts are private', WPCPM_Institution_Record::POST_STATUS, 'private' );
ck( 'the record ID is kept under _wpcpm_inst_record', WPCPM_Institution_Record::META_RECORD, '_wpcpm_inst_record' );
ck( 'and the answers under _wpcpm_inst_answers', WPCPM_Institution_Record::META_ANSWERS, '_wpcpm_inst_answers' );

WPCPM_Institution_Record::init();

ck( 'init() registers the type on init', $GLOBALS['hooks'], array( array( 'init', array( 'WPCPM_Institution_Record', 'register_post_type' ) ) ) );

WPCPM_Institution_Record::register_post_type();

$args = isset( $GLOBALS['post_types']['wpcpm_inst_record'] ) ? $GLOBALS['post_types']['wpcpm_inst_record'] : array();

ck(
	'not public, not queryable, out of search, with no UI, menu, REST route, archive, rewrite or query variable',
	array(
		$args['public'] ?? null,
		$args['publicly_queryable'] ?? null,
		$args['exclude_from_search'] ?? null,
		$args['show_ui'] ?? null,
		$args['show_in_menu'] ?? null,
		$args['show_in_rest'] ?? null,
		$args['has_archive'] ?? null,
		$args['rewrite'] ?? null,
		$args['query_var'] ?? null,
	),
	array( false, false, true, false, false, false, false, false, false )
);
ck( 'a capability type nobody is granted', $args['capability_type'] ?? null, array( 'wpcpm_inst_record', 'wpcpm_inst_records' ) );
ck( 'with the capabilities mapped from it', $args['map_meta_cap'] ?? null, true );
ck( 'and a title and nothing else', $args['supports'] ?? null, array( 'title' ) );

echo "\n-- the first call creates the institution's one post --\n";

reset_world();

$answers = array(
	'contact_role'      => 'Head of the international office',
	'student_languages' => array( 'English', 'Spanish' ),
	'signer_name'       => 'First Signer',
	'signer_title'      => 'Dean of Engineering',
	'credits_page'      => 'https://example.test/credits',
);

ck( 'nothing is kept for an institution nobody has written to', WPCPM_Institution_Record::get( $one ), array() );
ck( 'filling an unknown institution says it changed something', WPCPM_Institution_Record::fill_empty( $one, $answers ), true );

$posts = records();

ck( 'exactly one post was made', count( $posts ), 1 );
ck( 'it is private', $posts[0]->post_status, 'private' );
ck( 'it is of the record type', $posts[0]->post_type, 'wpcpm_inst_record' );
ck( 'its title is the record ID', $posts[0]->post_title, $one );
ck( 'it has no author', $posts[0]->post_author, 0 );
ck( 'the record ID is in the meta the lookup reads', get_post_meta( $posts[0]->ID, '_wpcpm_inst_record', true ), $one );
ck( 'the answers are in the answers meta, in the order they came', get_post_meta( $posts[0]->ID, '_wpcpm_inst_answers', true ), $answers );
ck( 'and nothing else is kept on the post', array_keys( $GLOBALS['pmeta'][ $posts[0]->ID ] ), array( '_wpcpm_inst_record', '_wpcpm_inst_answers' ) );
ck( 'get() reads back what was kept', WPCPM_Institution_Record::get( $one ), $answers );
ck( 'another institution still has none', WPCPM_Institution_Record::get( $two ), array() );
ck( 'no option was written, so nothing is autoloaded', $GLOBALS['options'], 0 );

echo "\n-- a second call fills what is empty and keeps the rest --\n";

reset_world();

ck( 'a first call with some answers blank creates the post', WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Registrar', 'student_languages' => array( 'English' ), 'signer_name' => '', 'signer_title' => '', 'credits_page' => '' ) ), true );
ck( 'and keeps only the answers that were given', WPCPM_Institution_Record::get( $one ), array( 'contact_role' => 'Registrar', 'student_languages' => array( 'English' ) ) );

$changed = WPCPM_Institution_Record::fill_empty(
	$one,
	array(
		'contact_role'      => 'Somebody else',
		'student_languages' => array( 'French' ),
		'signer_name'       => 'First Signer',
		'signer_title'      => 'Dean',
		'credits_page'      => '',
	)
);

ck( 'a second call that fills something says so', $changed, true );
ck(
	'it filled the empty keys and left the first answers standing',
	WPCPM_Institution_Record::get( $one ),
	array(
		'contact_role'      => 'Registrar',
		'student_languages' => array( 'English' ),
		'signer_name'       => 'First Signer',
		'signer_title'      => 'Dean',
	)
);
ck( 'still one post', count( records() ), 1 );

$writes = $GLOBALS['meta_writes'];

ck(
	'a call that has nothing new to add says false',
	WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Third', 'signer_name' => 'Second Signer', 'student_languages' => array( 'German' ) ) ),
	false
);
ck( 'and writes nothing at all', $GLOBALS['meta_writes'], $writes );
ck(
	'the record is as it was',
	WPCPM_Institution_Record::get( $one ),
	array(
		'contact_role'      => 'Registrar',
		'student_languages' => array( 'English' ),
		'signer_name'       => 'First Signer',
		'signer_title'      => 'Dean',
	)
);
ck( 'the same answers pressed again change nothing', WPCPM_Institution_Record::fill_empty( $one, WPCPM_Institution_Record::get( $one ) ), false );

// A key a record holds as blank is empty and is filled: the rule is "empty", not "absent".
$id = records()[0]->ID;

$GLOBALS['pmeta'][ $id ]['_wpcpm_inst_answers'] = array( 'contact_role' => '  ', 'student_languages' => array(), 'signer_name' => 'First Signer' );

ck( 'a stored blank string and a stored empty list are empty keys', WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Registrar', 'student_languages' => array( 'Spanish' ), 'signer_name' => 'Other' ) ), true );
ck(
	'and are filled, while the answered key stays',
	WPCPM_Institution_Record::get( $one ),
	array( 'contact_role' => 'Registrar', 'student_languages' => array( 'Spanish' ), 'signer_name' => 'First Signer' )
);

echo "\n-- a blank answer is not an answer --\n";

reset_world();

ck(
	'only blank answers write no post for an institution with none',
	WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => '', 'student_languages' => array(), 'signer_name' => '   ', 'signer_title' => null, 'credits_page' => '' ) ),
	false
);
ck( 'there is no post', count( records() ), 0 );
ck( 'and no meta', $GLOBALS['pmeta'], array() );
ck( 'an empty list of answers is the same', WPCPM_Institution_Record::fill_empty( $one, array() ), false );
ck( 'still no post', count( records() ), 0 );
ck( 'a list of blank entries is blank', WPCPM_Institution_Record::fill_empty( $one, array( 'student_languages' => array( '', ' ' ) ) ), false );
ck( 'and writes none', count( records() ), 0 );

WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Registrar' ) );
$writes = $GLOBALS['meta_writes'];

ck( 'blank answers over a record that exists say false', WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => '', 'signer_name' => '' ) ), false );
ck( 'and write nothing', $GLOBALS['meta_writes'], $writes );
ck( 'a zero is an answer', WPCPM_Institution_Record::fill_empty( $one, array( 'estimated_students' => 0 ) ), true );
ck( 'and is kept', WPCPM_Institution_Record::get( $one ), array( 'contact_role' => 'Registrar', 'estimated_students' => 0 ) );

echo "\n-- a record ID is rec and fourteen letters or digits --\n";

reset_world();

$bad = array(
	'empty'              => '',
	'blank'              => '   ',
	'twelve long'        => 'recINSTITUTION1',
	'thirteen long'      => 'recINSTITUTION01',
	'fifteen long'       => 'recINSTITUTION0011',
	'sixteen long'       => 'recINSTITUTION00123',
	'without the prefix' => 'xyzINSTITUTION001',
	'in the wrong case'  => 'RecINSTITUTION001',
	'a space inside'     => 'recINSTITUTI N001',
	'a dash inside'      => 'recINSTITUTI-N001',
	'a newline inside'   => "recINSTITUTI\nN001",
	'a quote inside'     => "recINSTITUTI'N001",
	'a non-ASCII letter' => 'recINSTITUTIÖ001',
	'a sponsor key'      => 'spoINSTITUTION001',
	'null'               => null,
	'an integer'         => 12345,
	'a list'             => array( 'recINSTITUTION001' ),
);

foreach ( $bad as $why => $id ) {
	ck( sprintf( 'a record ID that is %s is refused', $why ), WPCPM_Institution_Record::fill_empty( $id, array( 'contact_role' => 'Registrar' ) ), false );
}

ck( 'and nothing was written for any of them', array( count( $GLOBALS['posts'] ), $GLOBALS['pmeta'], $GLOBALS['meta_writes'] ), array( 0, array(), 0 ) );

foreach ( $bad as $why => $id ) {
	if ( '' === $id ) {
		continue;
	}

	ck( sprintf( 'get() answers nothing for a record ID that is %s', $why ), WPCPM_Institution_Record::get( $id ), array() );
}

ck( 'get() asked for an empty ID answers nothing', WPCPM_Institution_Record::get( '' ), array() );

ck( 'a record ID with spaces around it is the record ID', WPCPM_Institution_Record::fill_empty( "  {$one}\n", array( 'contact_role' => 'Registrar' ) ), true );
ck( 'and is kept trimmed', array( count( records() ), records()[0]->post_title, get_post_meta( records()[0]->ID, '_wpcpm_inst_record', true ) ), array( 1, $one, $one ) );
ck( 'every letter and digit is allowed', WPCPM_Institution_Record::fill_empty( 'recabcXYZ00000000', array( 'contact_role' => 'Registrar' ) ), true );

echo "\n-- one post per institution, whatever its status --\n";

reset_world();

WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Registrar' ) );
WPCPM_Institution_Record::fill_empty( $two, array( 'contact_role' => 'Director' ) );

ck( 'two institutions, two posts', count( records() ), 2 );
ck( 'each reads its own', array( WPCPM_Institution_Record::get( $one ), WPCPM_Institution_Record::get( $two ) ), array( array( 'contact_role' => 'Registrar' ), array( 'contact_role' => 'Director' ) ) );

// What a command line trash or a hand-made draft leaves: the post is still the institution's.
$first = records()[0];

$first->post_status = 'trash';

ck( 'a trashed post is still found', WPCPM_Institution_Record::get( $one ), array( 'contact_role' => 'Registrar' ) );
ck(
	'because the lookup names every status, which core\'s own shorthand would not',
	array_diff( array( 'publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft' ), (array) $GLOBALS['last_get_posts']['post_status'] ),
	array()
);
ck( 'and is filled, not duplicated', WPCPM_Institution_Record::fill_empty( $one, array( 'signer_name' => 'First Signer' ) ), true );
ck( 'there are still two posts', count( records() ), 2 );

$first->post_status = 'draft';

ck( 'a draft is found the same way', WPCPM_Institution_Record::fill_empty( $one, array( 'signer_title' => 'Dean' ) ), true );
ck( 'still two posts', count( records() ), 2 );
ck( 'and the first institution holds all three answers', WPCPM_Institution_Record::get( $one ), array( 'contact_role' => 'Registrar', 'signer_name' => 'First Signer', 'signer_title' => 'Dean' ) );
ck( 'the other institution is untouched', WPCPM_Institution_Record::get( $two ), array( 'contact_role' => 'Director' ) );

echo "\n-- the words come back as typed --\n";

reset_world();

$typed = array(
	'contact_role'      => 'Head of "Languages" \\ Culture',
	'student_languages' => array( "Cote d'Ivoire French" ),
	'signer_name'       => 'Signer \\n One',
	'credits_page'      => 'https://example.test/a?b=1&c=\\d',
);

WPCPM_Institution_Record::fill_empty( $one, $typed );

ck( 'a quote and a backslash survive the write, in a string and in a list', WPCPM_Institution_Record::get( $one ), $typed );

WPCPM_Institution_Record::fill_empty( $one, array( 'signer_title' => "Dean's \\ office" ) );

ck( 'and survive the second write too', WPCPM_Institution_Record::get( $one ), array_merge( $typed, array( 'signer_title' => "Dean's \\ office" ) ) );

echo "\n-- a write WordPress refuses --\n";

reset_world();
$GLOBALS['insert_fail'] = $one;

ck( 'a post that could not be made says false', WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Registrar' ) ), false );
ck( 'and leaves nothing behind', array( count( $GLOBALS['posts'] ), $GLOBALS['pmeta'] ), array( 0, array() ) );
ck( 'get() has nothing to read', WPCPM_Institution_Record::get( $one ), array() );

echo "\n-- a stored value that is not a list is nothing --\n";

reset_world();

WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Registrar' ) );

$GLOBALS['pmeta'][ records()[0]->ID ]['_wpcpm_inst_answers'] = 'garbled';

ck( 'get() answers an empty list for meta that is not an array', WPCPM_Institution_Record::get( $one ), array() );
ck( 'and a fill makes the record whole again', WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Registrar' ) ), true );
ck( 'with the one post', array( count( records() ), WPCPM_Institution_Record::get( $one ) ), array( 1, array( 'contact_role' => 'Registrar' ) ) );

echo "\n-- delete_all() --\n";

reset_world();

WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Registrar' ) );
WPCPM_Institution_Record::fill_empty( $two, array( 'contact_role' => 'Director' ) );
WPCPM_Institution_Record::fill_empty( 'recINSTITUTION003', array( 'contact_role' => 'Chair' ) );
WPCPM_Institution_Record::fill_empty( 'recINSTITUTION004', array( 'contact_role' => 'Lecturer' ) );

records()[0]->post_status = 'trash';
records()[1]->post_status = 'draft';
records()[2]->post_status = 'auto-draft';

$bystander = wp_insert_post( array( 'post_type' => 'wpcpm_inst_request', 'post_status' => 'private', 'post_title' => 'Not ours' ) );

update_post_meta( $bystander, '_wpcpm_req_kind', 'mentor' );

ck( 'four rows, in four statuses, before', count( records() ), 4 );
ck( 'delete_all() answers nothing', WPCPM_Institution_Record::delete_all(), null );
ck( 'every row is gone, trash and auto-draft included', count( records() ), 0 );
ck( 'with its meta', array_keys( $GLOBALS['pmeta'] ), array( $bystander ) );
ck( 'and nothing of another type was touched', array( isset( $GLOBALS['posts'][ $bystander ] ), get_post_meta( $bystander, '_wpcpm_req_kind', true ) ), array( true, 'mentor' ) );
ck( 'get() has nothing left to read', WPCPM_Institution_Record::get( $one ), array() );
ck( 'a second sweep has nothing to do', WPCPM_Institution_Record::delete_all(), null );
ck( 'and can be followed by a fresh record', WPCPM_Institution_Record::fill_empty( $one, array( 'contact_role' => 'Registrar' ) ), true );

echo "\n-- the source --\n";

$src  = file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-institution-record.php' );
$mine = file_get_contents( __FILE__ );

ck( 'the class writes no option, so nothing it keeps can be autoloaded', array( strpos( $src, 'update_option(' ), strpos( $src, 'add_option(' ), strpos( $src, 'set_transient(' ) ), array( false, false, false ) );
ck( 'it is not a form handler and checks no nonce of its own', array( strpos( $src, 'check_admin_referer' ), strpos( $src, 'wp_verify_nonce' ) ), array( false, false ) );
ck( 'it prints nothing', strpos( $src, 'echo ' ), false );
ck( 'no em dash or en dash in the class', preg_match( '/[\x{2013}\x{2014}]/u', $src ), 0 );
ck( 'nor in this suite', preg_match( '/[\x{2013}\x{2014}]/u', $mine ), 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
