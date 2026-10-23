<?php
/**
 * WordPress, as far as the theme's one-time migrations reach (inc/migrate-1-32-0.php and
 * inc/migrate-1-32-1.php), stood in for their suites, bin/test-migrate-1-32-0.php and
 * bin/test-migrate-1-32-1.php: the posts, revisions, options and users the runners, the repair of a
 * save and the Site Editor's notice reach, kept in the globals below, which a check sets. Nothing here
 * talks to a database or the network.
 *
 * - `$GLOBALS['posts']`: ID => a WP_Post stand-in, revisions among them; `$GLOBALS['terms']`: ID =>
 *   the name of the post's wp_theme term.
 * - `$GLOBALS['calls']`: what the runners did, in order: each get_posts(), revision, write, kses
 *   switch and option write, so a check can tell what was done and in which order.
 * - `$GLOBALS['user']`: the reader, `id` and `caps`; `$GLOBALS['user_meta']`; `$GLOBALS['screen']`, the
 *   admin screen's ID; `$GLOBALS['inline']`, the inline scripts added, by handle.
 * - `$GLOBALS['kses_on']`: whether a save is filtered through kses, as a visitor's request is; with it
 *   on, an svg is stripped from what is saved.
 * - The switches a check turns on to make one step fail: `fail_update`, `throw_update`,
 *   `throw_after_update`, `rewrite_update`, `keep_target`, `fail_revision`, `rewrite_revision`,
 *   `throw_revision` (`before` or `after`), `revisions_off`, `throw_terms`, `throw_option`, and
 *   `refuse_copies`, a database that refuses an option whose value holds a copy of a template (`before`),
 *   as one refuses a value too large for it or with characters its table cannot hold.
 *
 * Loaded after bin/stubs/hub-wp.php, whose options, hooks and blocks it builds on, and before the
 * migration's files.
 *
 * @package WPCredits_Theme
 */

/* ---- posts, options and users, as far as the runner reaches ----------------------- */

$GLOBALS['posts']     = array(); // ID => WP_Post stand-in.
$GLOBALS['terms']     = array(); // ID => the wp_theme term's name.
$GLOBALS['calls']     = array(); // What the runner did, in order.
$GLOBALS['user']      = array(
	'id'   => 1,
	'caps' => array( 'edit_theme_options' ),
);
$GLOBALS['user_meta'] = array();
$GLOBALS['screen']    = 'site-editor';
$GLOBALS['inline']    = array();
$GLOBALS['kses_on']   = true; // This request is a visitor's, whose saves WordPress filters through kses.
$GLOBALS['last_id']   = 2000; // The last ID a revision was given.
$GLOBALS['stale']     = array(); // ID => the post as the object cache still holds it, until clean_post_cache().

/** A post, as core's WP_Post carries one. */
class WP_Post {
	public $ID;
	public $post_type;
	public $post_name;
	public $post_status;
	public $post_content;
	public $post_parent = 0;
	public $post_author = 0;

	public function __construct( array $fields ) {
		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
	}
}

/** Core's WP_Error, as far as is_wp_error() asks it. */
class WP_Error {
	public $code;

	public function __construct( $code = '' ) {
		$this->code = $code;
	}
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function get_stylesheet() { return 'wpcredits-theme'; }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : addslashes( (string) $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
function sanitize_text_field( $text ) { return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $text ) ) ); }

/**
 * Whether the database refuses to store a value, as `$GLOBALS['refuse_copies']` has it: one that holds a
 * copy of a template as it was, a `before` at any depth.
 *
 * @param mixed $value The value.
 * @return bool
 */
function wpcredits_test_refused( $value ) {
	if ( empty( $GLOBALS['refuse_copies'] ) || ! is_array( $value ) ) {
		return false;
	}

	foreach ( $value as $key => $inner ) {
		if ( 'before' === $key || wpcredits_test_refused( $inner ) ) {
			return true;
		}
	}

	return false;
}

function add_option( $key, $value = '', $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $key, $GLOBALS['opts'] ) ) {
		return false;
	}

	if ( wpcredits_test_refused( $value ) ) {
		$GLOBALS['calls'][] = 'add_option ' . $key . ' refused';

		return false;
	}

	$GLOBALS['opts'][ $key ] = $value;
	$GLOBALS['calls'][]      = 'add_option ' . $key . ' autoload ' . var_export( $autoload, true );

	return true;
}

/**
 * Core's get_posts(), as far as the runner asks it: the posts of a type, a name and a status other
 * than trash, in ID order.
 *
 * @param array $args The query.
 * @return WP_Post[]
 */
function get_posts( $args = array() ) {
	$GLOBALS['calls'][] = 'get_posts ' . json_encode( $args );

	return array_values(
		array_filter(
			$GLOBALS['posts'],
			static function ( $post ) use ( $args ) {
				return $post->post_type === $args['post_type'] && ( ! isset( $args['name'] ) || $post->post_name === $args['name'] ) && 'trash' !== $post->post_status;
			}
		)
	);
}

function get_post( $post ) {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;

	// The object cache keeps a post as it was until core cleans it, which core does after it writes.
	if ( isset( $GLOBALS['stale'][ $id ] ) ) {
		return $GLOBALS['stale'][ $id ];
	}

	return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null;
}
function clean_post_cache( $id ) { unset( $GLOBALS['stale'][ (int) $id ] ); }
function has_term( $term, $taxonomy, $post ) {
	if ( ! empty( $GLOBALS['throw_terms'] ) ) {
		throw new Error( 'thrown reading the terms' );
	}

	return 'wp_theme' === $taxonomy && isset( $GLOBALS['terms'][ $post->ID ] ) && $GLOBALS['terms'][ $post->ID ] === $term; }
function wp_revisions_enabled( $post ) { return empty( $GLOBALS['revisions_off'] ); }

/** Core's normalize_whitespace(), which wp_save_post_revision() compares a post and its newest revision by. */
function normalize_whitespace( $str ) {
	$str = str_replace( "\r", "\n", trim( $str ) );

	return preg_replace( array( '/\n+/', '/[ \t]+/' ), array( "\n", ' ' ), $str );
}

/**
 * Core's wp_get_post_revisions(): the revisions and autosaves of a post, newest first, by ID.
 *
 * @param int $id The post's ID.
 * @return WP_Post[]
 */
function wp_get_post_revisions( $id ) {
	$revisions = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'revision' === $post->post_type && (int) $post->post_parent === (int) $id ) {
			$revisions[ $post->ID ] = $post;
		}
	}

	krsort( $revisions );

	return empty( $GLOBALS['revisions_off'] ) ? $revisions : array();
}

/**
 * Core's wp_save_post_revision(), as far as the migration asks it, and as it behaved on live: nothing is
 * kept when the newest revision matches the post once whitespace is run together (unless a filter says
 * to keep one regardless); what is kept is what wp_insert_post() would store, so with kses on it is
 * stripped of what a visitor may not save, the svg of the hub's cards among it, and its author is the
 * current user, which wp_insert_post() fills in; the ID comes back, or null or 0 where nothing was kept.
 *
 * @param int $id The post's ID.
 * @return int|null
 */
function wp_save_post_revision( $id ) {
	$post = get_post( $id );

	if ( ! $post || ! wp_revisions_enabled( $post ) ) {
		return null;
	}

	$newest = null;

	foreach ( wp_get_post_revisions( $id ) as $revision ) {
		if ( false !== strpos( $revision->post_name, $revision->post_parent . '-revision' ) ) {
			$newest = $revision;
			break;
		}
	}

	if ( $newest && apply_filters( 'wp_save_post_revision_check_for_changes', true, $newest, $post ) && normalize_whitespace( $post->post_content ) === normalize_whitespace( $newest->post_content ) ) {
		return null;
	}

	$GLOBALS['calls'][] = 'revision ' . $id . ' of ' . sha1( $post->post_content );

	if ( 'before' === ( $GLOBALS['throw_revision'] ?? '' ) ) {
		throw new RuntimeException( 'thrown before the revision is stored' );
	}

	if ( ! empty( $GLOBALS['fail_revision'] ) ) {
		return 0;
	}

	$data = apply_filters(
		'wp_insert_post_data',
		array(
			'post_type'    => 'revision',
			'post_parent'  => $id,
			'post_author'  => get_current_user_id(),
			'post_content' => $GLOBALS['kses_on'] ? preg_replace( '#<svg\b.*?</svg>#s', '', $post->post_content ) : $post->post_content,
		)
	);

	$revision = new WP_Post(
		array(
			'ID'           => ++$GLOBALS['last_id'],
			'post_type'    => 'revision',
			'post_status'  => 'inherit',
			'post_name'    => $id . '-revision-v1',
			'post_parent'  => $data['post_parent'],
			'post_author'  => $data['post_author'],
			'post_content' => empty( $GLOBALS['rewrite_revision'] ) ? $data['post_content'] : str_replace( '<p', '<p data-rewritten', $data['post_content'] ),
		)
	);

	$GLOBALS['posts'][ $revision->ID ] = $revision;

	if ( 'after' === ( $GLOBALS['throw_revision'] ?? '' ) ) {
		throw new RuntimeException( 'thrown by a hook after the revision is stored' );
	}

	return $revision->ID;
}
function kses_remove_filters() { $GLOBALS['calls'][] = 'kses off'; $GLOBALS['kses_on'] = false; }
function kses_init() { $GLOBALS['calls'][] = 'kses on'; $GLOBALS['kses_on'] = true; }

/**
 * Core's wp_update_post(), as far as the runner asks it: the fields it is handed are slashed, as core
 * expects, and unslashed as core does before it saves them; a post that fails saves nothing; with kses
 * on, what a visitor may not save (an svg) is stripped from the content; core cleans the post's cache
 * and fires its after-insert action once the post is saved. An update nested more than three deep is
 * a runaway, and fails without saving.
 *
 * @param array $fields   The post's ID and fields, slashed.
 * @param bool  $wp_error Whether a failure is a WP_Error.
 * @return int|WP_Error
 */
function wp_update_post( $fields, $wp_error = false ) {
	static $depth = 0;

	$fields = wp_unslash( $fields );

	$GLOBALS['calls'][] = 'update ' . $fields['ID'] . ' to ' . sha1( $fields['post_content'] );

	if ( ! empty( $GLOBALS['fail_update'] ) || $depth > 3 ) {
		return new WP_Error( 'db_update_error' );
	}

	// A plugin's hook, or the database layer, that throws in the middle of the write.
	if ( ! empty( $GLOBALS['throw_update'] ) ) {
		throw new RuntimeException( 'thrown in the write' );
	}

	$id      = (int) $fields['ID'];
	$content = $fields['post_content'];

	if ( $GLOBALS['kses_on'] ) {
		$content = preg_replace( '#<svg\b.*?</svg>#s', '', $content );
	}

	// A plugin's content filter that rewrites what is saved, when a check asks for one.
	if ( ! empty( $GLOBALS['rewrite_update'] ) ) {
		$content = str_replace( '<p', '<p data-rewritten', $content );
	}

	// A plugin that puts back what the write took out, a target of the repair among it.
	if ( ! empty( $GLOBALS['keep_target'] ) ) {
		$content .= "\n" . '<!-- wp:wpcredits/program-index /-->';
	}

	// A hook after the database write that throws leaves the object cache holding the post as it was.
	if ( ! empty( $GLOBALS['throw_after_update'] ) ) {
		$GLOBALS['stale'][ $id ] = clone $GLOBALS['posts'][ $id ];
	}

	$GLOBALS['posts'][ $id ]->post_content = $content;

	if ( ! empty( $GLOBALS['throw_after_update'] ) ) {
		throw new RuntimeException( 'thrown by a hook after the write' );
	}

	clean_post_cache( $id );

	// Core fires its after-insert action for every update, the runner's own writes among them.
	++$depth;

	try {
		do_action( 'wp_after_insert_post', $id, $GLOBALS['posts'][ $id ], true, null );
	} finally {
		--$depth;
	}

	return $id;
}

/**
 * Core's do_action(): each callback in priority order, with as many arguments as it takes; each firing
 * of a hook counted in `$GLOBALS['fired']`.
 *
 * @param string $hook    The action.
 * @param mixed  ...$args Its arguments.
 */
function do_action( $hook, ...$args ) {
	$GLOBALS['fired'][ $hook ] = ( isset( $GLOBALS['fired'][ $hook ] ) ? $GLOBALS['fired'][ $hook ] : 0 ) + 1;

	$callbacks = isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array();

	usort( $callbacks, static function ( $a, $b ) { return $a[1] - $b[1]; } );

	foreach ( $callbacks as $callback ) {
		call_user_func_array( $callback[0], array_slice( $args, 0, $callback[2] ) );
	}
}

function update_option( $key, $value, $autoload = null ) {
	if ( ! empty( $GLOBALS['throw_option'] ) ) {
		throw new RuntimeException( 'thrown saving the option' );
	}

	if ( wpcredits_test_refused( $value ) ) {
		$GLOBALS['calls'][] = 'update_option ' . $key . ' refused';

		return false;
	}

	$GLOBALS['opts'][ $key ] = $value;
	$GLOBALS['calls'][]      = 'update_option ' . $key . ' autoload ' . var_export( $autoload, true );

	return true;
}

function get_current_user_id() { return (int) $GLOBALS['user']['id']; }
function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['user']['caps'], true ); }
function get_user_meta( $user, $key, $single = false ) { return isset( $GLOBALS['user_meta'][ $user ][ $key ] ) ? $GLOBALS['user_meta'][ $user ][ $key ] : ''; }
function update_user_meta( $user, $key, $value ) { $GLOBALS['user_meta'][ $user ][ $key ] = $value; return true; }
function get_current_screen() { return (object) array( 'id' => $GLOBALS['screen'] ); }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['registered'][ $handle ] = array( $src, $deps, $ver, $args ); return true; }
function wp_add_inline_script( $handle, $data, $position = 'after' ) { $GLOBALS['inline'][ $handle ][] = $data; return true; }
function admin_url( $path = '' ) { return 'https://site.example/wp-admin/' . $path; }
function wp_create_nonce( $action = -1 ) { return 'nonce-' . md5( $action . '|' . $GLOBALS['user']['id'] ); }

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

/** What wp_send_json_*() ends a request with: core prints the answer and dies, here it is thrown. */
class WPCREDITS_Test_Sent extends Exception {
	public $answer;

	public function __construct( array $answer ) {
		parent::__construct( 'sent' );
		$this->answer = $answer;
	}
}

/**
 * Core's check_ajax_referer(): the request's `_ajax_nonce` checked against the action's nonce for the
 * reader; a wrong one ends the request with -1, as core's does.
 *
 * @param string $action The nonce's action.
 * @return int
 */
function check_ajax_referer( $action = -1 ) {
	if ( ! isset( $_REQUEST['_ajax_nonce'] ) || wp_create_nonce( $action ) !== $_REQUEST['_ajax_nonce'] ) {
		throw new WPCREDITS_Test_Sent( array( 'die' => -1 ) );
	}

	return 1;
}
function wp_send_json_success( $data = null, $status = null ) { throw new WPCREDITS_Test_Sent( array( 'success' => true ) ); }
function wp_send_json_error( $data = null, $status = null ) { throw new WPCREDITS_Test_Sent( array( 'success' => false, 'status' => $status ) ); }

/**
 * The newest revision of a post, an autosave not counted.
 *
 * @param int $id The post's ID.
 * @return WP_Post|null
 */
function newest_revision_of( $id ) {
	foreach ( wp_get_post_revisions( $id ) as $revision ) {
		if ( false !== strpos( $revision->post_name, $id . '-revision' ) ) {
			return $revision;
		}
	}

	return null;
}

/**
 * How many callbacks hang on the two filters the migration hooks around its revision: none, once it is made.
 *
 * @return int
 */
function hooked_by_the_copy() {
	return count( $GLOBALS['hooks']['wp_insert_post_data'] ?? array() ) + count( $GLOBALS['hooks']['wp_save_post_revision_check_for_changes'] ?? array() );
}

/** Every revision and autosave dropped: a template whose history is not the one an earlier check made. */
function drop_revisions() {
	$GLOBALS['posts'] = array_filter(
		$GLOBALS['posts'],
		static function ( $post ) {
			return 'revision' !== $post->post_type;
		}
	);
}

/**
 * A revision or an autosave stored for a post, as an earlier save left it.
 *
 * @param int    $parent  The post's ID.
 * @param string $content What it holds.
 * @param int    $author  Its author.
 * @param string $kind    `revision`, or `autosave`.
 * @return WP_Post
 */
function add_revision( $parent, $content, $author = 3, $kind = 'revision' ) {
	$revision = new WP_Post(
		array(
			'ID'           => ++$GLOBALS['last_id'],
			'post_type'    => 'revision',
			'post_status'  => 'inherit',
			'post_name'    => $parent . '-' . $kind . '-v1',
			'post_parent'  => $parent,
			'post_author'  => $author,
			'post_content' => $content,
		)
	);

	$GLOBALS['posts'][ $revision->ID ] = $revision;

	return $revision;
}

/**
 * Some markup, parsed, to be asked with XPath.
 *
 * @param string $html The markup.
 * @return DOMXPath
 */
function dom_of( $html ) {
	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	return new DOMXPath( $document );
}

/**
 * A block's span in some markup: from its opening comment to its closing one, or its void comment.
 *
 * @param string $markup Block markup.
 * @param string $name   The block's name as its comment writes it, such as `wpcredits/program-index`.
 * @return array{0: int, 1: int}|null Its start and its length.
 */
function span_of( $markup, $name ) {
	if ( ! preg_match( '#<!-- wp:' . preg_quote( $name, '#' ) . '(?: \{.*?\})? (/?)-->#s', $markup, $open, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	if ( '/' === $open[1][0] ) {
		return array( $open[0][1], strlen( $open[0][0] ) );
	}

	$close = strpos( $markup, '<!-- /wp:' . $name . ' -->', $open[0][1] );

	return false === $close ? null : array( $open[0][1], $close + strlen( '<!-- /wp:' . $name . ' -->' ) - $open[0][1] );
}

/**
 * Some markup in pieces, cut at some spans: what lies before the first, between each two and after
 * the last.
 *
 * @param string  $markup The markup.
 * @param array[] $spans  Spans, as span_of() gives them, in order.
 * @return string[]
 */
function pieces_around( $markup, array $spans ) {
	$pieces = array();
	$at     = 0;

	foreach ( $spans as $span ) {
		$pieces[] = substr( $markup, $at, $span[0] - $at );
		$at       = $span[0] + $span[1];
	}

	$pieces[] = substr( $markup, $at );

	return $pieces;
}

/** The plugin's two application pages and its Partners page, published. */
class WPCPM_Institution_Application {
	public static function page_url() { return 'https://wordpresseducation.org/apply/'; }
}

/** The sponsorship application's page. */
class WPCPM_Sponsor_Application {
	public static function page_url() { return 'https://wordpresseducation.org/sponsor-application/'; }
}

/** The Partners page. */
class WPCPM_Sponsors_Page {
	public static function page_url() { return 'https://wordpresseducation.org/partners/'; }
}
