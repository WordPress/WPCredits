<?php
/**
 * The Updates column, and who is allowed to see which update.
 *
 * The access matrix is the whole point of this file. Every post in the Updates category carries
 * a "Program access" level, and getting the gate wrong in either direction is serious: too tight
 * and a mentor never sees an announcement written for them, too loose and a student reads
 * something meant for administrators.
 *
 * So the real `WPCPM_Content_Access::can_view()` runs here - it is not stubbed. Only WordPress
 * is faked, and `user_can()` is driven by an explicit per-user capability map rather than by
 * anything the implementation decides, so the assertions cannot be satisfied by the code under
 * test agreeing with itself.
 *
 * Run from the plugin root:  php bin/test-updates.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
$GLOBALS['users'] = array();
$GLOBALS['caps']  = array();
$GLOBALS['uid']   = 0;
$GLOBALS['terms'] = array();

class WP_Error {
	private $code, $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
}
class WP_User {
	public $ID = 0, $display_name = '', $roles = array();
	public function __construct( $id = 0, $name = '' ) { $this->ID = $id; $this->display_name = $name; }
	public function exists() { return $this->ID > 0; }
}
class WP_Post {
	public $ID = 0, $post_title = '', $post_date = '', $post_type = 'post', $post_status = 'publish';
	public $cats = array();
}
class WP_Term {
	public $term_id = 0, $name = '', $slug = '', $count = 0, $taxonomy = 'category';
	public function __construct( $id = 0, $name = '', $slug = '', $count = 0 ) {
		$this->term_id = $id; $this->name = $name; $this->slug = $slug; $this->count = $count;
	}
}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function _x( $s, $c, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function apply_filters( $t, $v ) { return $v; }
function add_action() {} function add_filter() {} function register_post_meta() {}
function add_meta_box() {} function wp_nonce_field() {} function selected( $a, $b, $e = true ) { return ''; }
function wp_enqueue_style() {} function is_admin() { return false; }
function is_singular() { return false; }
function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_user_by( $f, $v ) { return $GLOBALS['users'][ (int) $v ] ?? false; }

/**
 * Capability lookup, from the map and from nothing else.
 *
 * `$GLOBALS['caps'][ $user_id ]` is the list of capabilities that user holds. A user missing
 * from the map holds none, which is what a logged-out visitor is.
 */
function user_can( $user, $cap ) {
	$id = is_object( $user ) ? $user->ID : (int) $user;

	return in_array( $cap, $GLOBALS['caps'][ $id ] ?? array(), true );
}
function current_user_can( $cap ) { return user_can( $GLOBALS['uid'], $cap ); }

function get_post( $post = null ) {
	if ( $post instanceof WP_Post ) { return $post; }
	return $GLOBALS['posts'][ (int) $post ] ?? null;
}
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = $value; return true; }
function get_permalink( $post = null ) {
	$post = get_post( $post );
	return $post ? 'https://example.test/?p=' . $post->ID : '';
}
function get_the_title( $post = null ) { $post = get_post( $post ); return $post ? $post->post_title : ''; }
function get_the_date( $format = '', $post = null ) { $post = get_post( $post ); return $post ? $post->post_date : ''; }
function get_term_link( $term ) { return 'https://example.test/category/' . ( is_object( $term ) ? $term->slug : $term ) . '/'; }

function get_term_by( $field, $value, $taxonomy = 'category' ) {
	foreach ( $GLOBALS['terms'] as $term ) {
		if ( 'slug' === $field && $term->slug === $value ) { return $term; }
		if ( 'name' === $field && $term->name === $value ) { return $term; }
	}

	return false;
}

/**
 * Posts in a category, newest first, honouring `numberposts`.
 *
 * Deliberately does **not** apply the access meta query. `WPCPM_Content_Access::filter_queries()`
 * would do that on a real site, and stubbing it in would hide whether `WPCPM_Updates` filters
 * for itself - which is the property worth testing, since it is what still holds when the query
 * filter is bypassed.
 */
function get_posts( $args = array() ) {
	$cat   = (int) ( $args['cat'] ?? 0 );
	$limit = (int) ( $args['numberposts'] ?? 5 );
	$out   = array();

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( 'publish' !== $post->post_status || ! in_array( $cat, $post->cats, true ) ) {
			continue;
		}

		$out[] = $post;
	}

	usort( $out, static function ( $a, $b ) { return strcmp( $b->post_date, $a->post_date ); } );

	return array_slice( $out, 0, $limit );
}

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-content-access.php';
require_once __DIR__ . '/../includes/class-wpcpm-updates.php';

$fails = 0;
$total = 0;

/**
 * Assert and report.
 *
 * @param string $label What is being checked.
 * @param mixed  $got   Actual.
 * @param mixed  $want  Expected.
 */
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

/**
 * Add a post to the fake site.
 *
 * @param int    $id    Post ID.
 * @param string $title Title.
 * @param string $date  Date, used for ordering.
 * @param string $level Access level.
 * @param array  $cats  Category IDs.
 */
function seed_post( $id, $title, $date, $level, $cats = array( 7 ) ) {
	$post              = new WP_Post();
	$post->ID          = $id;
	$post->post_title  = $title;
	$post->post_date   = $date;
	$post->cats        = $cats;
	$GLOBALS['posts'][ $id ] = $post;

	update_post_meta( $id, WPCPM_Content_Access::META_KEY, $level );
}

// One user per level, plus a program manager and a logged-out visitor.
$people = array(
	'logged out'  => array( 'id' => 0, 'caps' => array() ),
	'student'     => array( 'id' => 11, 'caps' => array( WPCPM_Roles::CAP_VIEW_STUDENT ) ),
	'mentor'      => array( 'id' => 12, 'caps' => array( WPCPM_Roles::CAP_VIEW_MENTOR ) ),
	'institution' => array( 'id' => 13, 'caps' => array( WPCPM_Roles::CAP_VIEW_INSTITUTION ) ),
	'manager'     => array( 'id' => 14, 'caps' => array( WPCPM_Roles::CAP_MANAGE ) ),
);

foreach ( $people as $who => $person ) {
	if ( $person['id'] ) {
		$GLOBALS['users'][ $person['id'] ] = new WP_User( $person['id'], $who );
		$GLOBALS['caps'][ $person['id'] ]  = $person['caps'];
	}
}

$GLOBALS['terms'][] = new WP_Term( 7, 'Updates', 'updates', 5 );

echo "=== The category ===\n";

ck( 'found by slug', WPCPM_Updates::term()->term_id, 7 );

$GLOBALS['terms'] = array( new WP_Term( 9, 'Updates', 'announcements', 2 ) );
ck( 'found by name when the slug differs', WPCPM_Updates::term()->term_id, 9 );

$GLOBALS['terms'] = array();
ck( 'no category means no term', WPCPM_Updates::term(), null );
ck( 'and no posts, rather than every post on the site', WPCPM_Updates::posts(), array() );

// A missing category must not take the column down with it, or a site that has not made one
// gets a Report Card with half a section on it.
$column = WPCPM_Updates::render_column();
ck( 'the column still renders, with the empty line',
    array(
        false !== strpos( $column, 'Program updates and announcements' ),
        false !== strpos( $column, 'Nothing new right now.' ),
        false !== strpos( $column, '<ul' ),
    ),
    array( true, true, false ) );

$GLOBALS['terms'] = array( new WP_Term( 7, 'Updates', 'updates', 5 ) );

echo "\n=== Who sees which level ===\n";

// One post per level, so every cell of the matrix has something to find.
$levels = array(
	'public'                        => 'Everyone post',
	WPCPM_Roles::ROLE_STUDENT       => 'Students post',
	WPCPM_Roles::ROLE_MENTOR        => 'Mentors post',
	WPCPM_Roles::ROLE_INSTITUTION   => 'Institutions post',
	WPCPM_Roles::ROLE_ADMIN         => 'Administrators post',
);

$id = 100;

foreach ( $levels as $level => $title ) {
	seed_post( ++$id, $title, sprintf( '2026-07-%02d 10:00:00', $id - 100 ), $level );
}

// The matrix, written out in full rather than derived - a table generated from `levels()` would
// pass whatever the implementation happens to do.
$expected = array(
	// who          => public, student, mentor, institution, administrator
	'logged out'  => array( true, false, false, false, false ),
	'student'     => array( true, true,  false, false, false ),
	'mentor'      => array( true, false, true,  false, false ),
	'institution' => array( true, false, false, true,  false ),
	'manager'     => array( true, true,  true,  true,  true ),
);

foreach ( $expected as $who => $wants ) {
	$GLOBALS['uid'] = $people[ $who ]['id'];

	$got = array();

	foreach ( array_keys( $levels ) as $level ) {
		$post  = null;
		$index = 101;

		foreach ( array_keys( $levels ) as $candidate ) {
			if ( $candidate === $level ) { $post = $GLOBALS['posts'][ $index ]; break; }
			++$index;
		}

		$got[] = WPCPM_Content_Access::can_view( $post );
	}

	ck( sprintf( 'a %s sees: public/student/mentor/institution/admin', $who ), $got, $wants );
}

echo "\n=== What the column lists ===\n";

foreach ( array( 'student', 'mentor', 'institution' ) as $who ) {
	$GLOBALS['uid'] = $people[ $who ]['id'];

	$titles = array();

	foreach ( WPCPM_Updates::posts( 10 ) as $post ) {
		$titles[] = $post->post_title;
	}

	sort( $titles );

	ck( sprintf( 'the %s column lists only their own and the public one', $who ),
	    $titles,
	    array( 'Everyone post', ucfirst( $who ) . 's post' ) );
}

$GLOBALS['uid'] = $people['logged out']['id'];
$titles         = array();

foreach ( WPCPM_Updates::posts( 10 ) as $post ) {
	$titles[] = $post->post_title;
}

ck( 'a logged-out visitor gets the public one and nothing else', $titles, array( 'Everyone post' ) );

// Rendered, not just queried: a gate that holds in `posts()` and leaks in the markup is still
// a leak.
$GLOBALS['uid'] = $people['student']['id'];
$column         = WPCPM_Updates::render_column();

ck( 'and the rendered column leaks no title it should not',
    array(
        false !== strpos( $column, 'Everyone post' ),
        false !== strpos( $column, 'Students post' ),
        false !== strpos( $column, 'Mentors post' ),
        false !== strpos( $column, 'Institutions post' ),
        false !== strpos( $column, 'Administrators post' ),
    ),
    array( true, true, false, false, false ) );

echo "\n=== Whose card it is, not who is looking ===\n";

// The bug this section exists for: a program manager may read every level, so a column that
// asked only `can_view()` listed mentor announcements on the Student Report Card and student
// ones on the Mentor Report Card whenever a manager looked at either.
$GLOBALS['uid'] = $people['manager']['id'];

foreach ( array( 'student' => 'Students post', 'mentor' => 'Mentors post' ) as $audience => $own ) {
	$titles = array();

	foreach ( WPCPM_Updates::posts( 20, $audience ) as $post ) {
		$titles[] = $post->post_title;
	}

	sort( $titles );

	ck( sprintf( 'a manager on the %s card sees the %s view', $audience, $audience ),
	    $titles, array( 'Everyone post', $own ) );
}

// The Administrator Dashboard's own column: a manager's own level and the public posts,
// exactly as `levels_for()` now maps it. Read through the rendered markup rather than
// `posts()`, the way the leak check above does, because a leak in the column is the failure
// that matters.
$column = WPCPM_Updates::render_column( 'administrator' );

ck( 'a manager on the administrator card sees the administrator view',
    array(
        false !== strpos( $column, 'Everyone post' ),
        false !== strpos( $column, 'Students post' ),
        false !== strpos( $column, 'Administrators post' ),
    ),
    array( true, false, true ) );

// The fifth audience `levels_for()` maps (design spec of 4 September 2026, section 5.3), read
// directly through reflection since the method stays private - nothing outside this class
// needs the mapping, only this suite needs to pin it down.
$levels_for = new ReflectionMethod( 'WPCPM_Updates', 'levels_for' );
$levels_for->setAccessible( true );

ck( 'the sponsor audience maps to the sponsor level',
    $levels_for->invoke( null, 'sponsor' ), array( 'public', 'wpcpm_sponsor' ) );

// And the audience is a narrowing, never a widening: a student on a mentor-audience render must
// still not get mentor content, because `can_view()` is underneath.
$GLOBALS['uid'] = $people['student']['id'];
$titles         = array();

foreach ( WPCPM_Updates::posts( 20, 'mentor' ) as $post ) {
	$titles[] = $post->post_title;
}

ck( 'the audience cannot widen what a reader is entitled to', $titles, array( 'Everyone post' ) );

// No audience at all is the viewer's own view, unchanged.
$GLOBALS['uid'] = $people['manager']['id'];
ck( 'with no audience it is still the viewer\'s view', count( WPCPM_Updates::posts( 20 ) ), 5 );

echo "\n=== The list's shape ===\n";

$GLOBALS['uid'] = $people['manager']['id'];

// Eight public posts on top of the five above, so the manager has thirteen and the column has
// to choose.
for ( $i = 1; $i <= 8; $i++ ) {
	seed_post( 200 + $i, 'Filler ' . $i, sprintf( '2026-08-%02d 10:00:00', $i ), 'public' );
}

$column = WPCPM_Updates::render_column();

ck( 'the column shows the limit and no more',
    substr_count( $column, 'wpcpm-updates__item' ), WPCPM_Updates::LIMIT );
ck( 'newest first',
    false !== strpos( $column, 'Filler 8' ) && false === strpos( $column, 'Filler 1' ), true );
ck( 'and offers the archive when there is more', false !== strpos( $column, 'All updates' ), true );

// The "All updates" link must follow what *this* reader can see, not the term's own count -
// which includes levels they may not read.
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
seed_post( 300, 'The only one', '2026-08-01 10:00:00', 'public' );
seed_post( 301, 'Not for students', '2026-08-02 10:00:00', WPCPM_Roles::ROLE_ADMIN );

$GLOBALS['uid'] = $people['student']['id'];
$column         = WPCPM_Updates::render_column();

ck( 'a reader who can see everything they are allowed to is offered no archive',
    array(
        substr_count( $column, 'wpcpm-updates__item' ),
        false !== strpos( $column, 'All updates' ),
    ),
    array( 1, false ) );

echo "\n=== Escaping ===\n";

$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
seed_post( 400, 'Bread & <script>alert(1)</script>', '2026-08-01 10:00:00', 'public' );

$GLOBALS['uid'] = $people['manager']['id'];
$column         = WPCPM_Updates::render_column();

ck( 'a title is escaped, not printed',
    array(
        false !== strpos( $column, '<script>' ),
        false !== strpos( $column, '&lt;script&gt;' ),
        false !== strpos( $column, 'Bread &amp; ' ),
    ),
    array( false, true, true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
