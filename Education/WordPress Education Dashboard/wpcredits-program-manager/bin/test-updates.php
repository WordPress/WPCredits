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
$GLOBALS['filters'] = array();

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
// `post_author` is declared here because core declares it: WP_Post gives every column of the
// posts table a property with a default, so `$post->post_author` on a post that has no author
// reads 0 rather than warning. A stub without it made class-wpcpm-content-access.php:201 warn
// on every run of this suite and made the seeded author below a dynamic property.
class WP_Post {
	public $ID = 0, $post_title = '', $post_date = '', $post_type = 'post', $post_status = 'publish';
	public $post_author = 0;
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
// A small filter registry, so the column's own `private_title_format` filter really runs and
// really comes off again: core prefixes a private post's title with "Private: ", and the
// Administrator Dashboard's column prints the title alone.
function apply_filters( $t, $v, ...$args ) {
	foreach ( $GLOBALS['filters'][ $t ] ?? array() as $f ) { $v = $f( $v, ...$args ); }
	return $v;
}
function add_filter( $t, $f, $priority = 10, $accepted = 1 ) { $GLOBALS['filters'][ $t ][] = $f; return true; }
function remove_filter( $t, $f, $priority = 10 ) {
	foreach ( $GLOBALS['filters'][ $t ] ?? array() as $i => $g ) {
		if ( $g === $f ) { unset( $GLOBALS['filters'][ $t ][ $i ] ); return true; }
	}
	return false;
}
function add_action() {} function register_post_meta() {}
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
// `read_post` as core maps it for the posts seeded here: a private post needs
// `read_private_posts`, or to be the reader's own; any other status is readable.
function current_user_can( $cap, ...$args ) {
	if ( 'read_post' === $cap ) {
		$post = get_post( $args[0] ?? 0 );

		if ( ! $post ) { return false; }

		if ( 'private' === $post->post_status ) {
			return user_can( $GLOBALS['uid'], 'read_private_posts' ) || ( $GLOBALS['uid'] > 0 && $GLOBALS['uid'] === (int) $post->post_author );
		}

		return true;
	}

	return user_can( $GLOBALS['uid'], $cap );
}

function get_post( $post = null ) {
	if ( $post instanceof WP_Post ) { return $post; }
	return $GLOBALS['posts'][ (int) $post ] ?? null;
}
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $key ] ?? ''; }
// As WordPress answers it for the posts seeded here, which are all published posts: the oEmbed
// filter asks it before the access level (SURFACES-2).
function is_post_publicly_viewable( $post = null ) { $post = get_post( $post ); return $post instanceof WP_Post && 'post' === $post->post_type && 'publish' === $post->post_status; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['pmeta'][ (int) $id ][ $key ] = $value; return true; }
function get_permalink( $post = null ) {
	$post = get_post( $post );
	return $post ? 'https://example.test/?p=' . $post->ID : '';
}
// As core's get_the_title() on the front end: a private post's title goes through
// `private_title_format`, "Private: %s" unless something filters it.
function get_the_title( $post = null ) {
	$post = get_post( $post );

	if ( ! $post ) { return ''; }

	if ( 'private' === $post->post_status ) {
		return sprintf( apply_filters( 'private_title_format', 'Private: %s', $post ), $post->post_title );
	}

	return $post->post_title;
}
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
 * Posts in a category, newest first, honoring `numberposts` and `post_status`.
 *
 * Deliberately does **not** apply the access meta query. `WPCPM_Content_Access::filter_queries()`
 * would do that on a real site, and stubbing it in would hide whether `WPCPM_Updates` filters
 * for itself - which is the property worth testing, since it is what still holds when the query
 * filter is bypassed.
 */
function get_posts( $args = array() ) {
	$cat      = (int) ( $args['cat'] ?? 0 );
	$limit    = (int) ( $args['numberposts'] ?? 5 );
	$statuses = (array) ( $args['post_status'] ?? 'publish' );
	$out      = array();

	$GLOBALS['last_statuses'] = $statuses;

	foreach ( $GLOBALS['posts'] as $post ) {
		if ( ! in_array( $post->post_status, $statuses, true ) || ! in_array( $cat, $post->cats, true ) ) {
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
 * @param array  $cats   Category IDs.
 * @param string $status Post status.
 */
function seed_post( $id, $title, $date, $level, $cats = array( 7 ), $status = 'publish' ) {
	$post              = new WP_Post();
	$post->ID          = $id;
	$post->post_title  = $title;
	$post->post_date   = $date;
	$post->cats        = $cats;
	$post->post_status = $status;
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
	WPCPM_Content_Access::LEVEL_STUDENTS_MENTORS => 'Students and mentors post',
);

$id = 100;

foreach ( $levels as $level => $title ) {
	seed_post( ++$id, $title, sprintf( '2026-07-%02d 10:00:00', $id - 100 ), $level );
}

// The matrix, written out in full rather than derived - a table generated from `levels()` would
// pass whatever the implementation happens to do.
$expected = array(
	// who          => public, student, mentor, institution, administrator, students and mentors
	'logged out'  => array( true, false, false, false, false, false ),
	'student'     => array( true, true,  false, false, false, true ),
	'mentor'      => array( true, false, true,  false, false, true ),
	'institution' => array( true, false, false, true,  false, false ),
	'manager'     => array( true, true,  true,  true,  true,  true ),
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

// The oEmbed endpoint answers for any published post to anyone holding the URL, so it is gated
// too: 102 is the student-level post seeded above, 101 the public one.
$GLOBALS['uid'] = $people['logged out']['id'];
ck( 'oEmbed answers nothing for a post the public cannot read, and passes a public post through', array( WPCPM_Content_Access::filter_oembed( array( 'title' => 'x' ), get_post( 102 ) ), WPCPM_Content_Access::filter_oembed( array( 'title' => 'x' ), get_post( 101 ) ) ), array( false, array( 'title' => 'x' ) ) );

// The author of a post reads it whatever its level (1.95.1): a sponsor's account holds only the
// sponsor marker while its published guides sit at the students-and-mentors level.
$GLOBALS['users'][15] = new WP_User( 15, 'sponsor author' );
$GLOBALS['caps'][15]  = array( 'wpcpm_view_sponsor_content' );
seed_post( 400, 'A guide by its author', '2026-09-06 10:00:00', WPCPM_Content_Access::LEVEL_STUDENTS_MENTORS );
$GLOBALS['posts'][400]->post_author = 15;
ck( 'the author reads their own post at a level they do not hold, and another sponsor account does not', array( WPCPM_Content_Access::can_view( 400, 15 ), WPCPM_Content_Access::can_view( 400, 13 ) ), array( true, false ) );
// The stub above has to have the shape core gives a post, or this section would be measuring
// a warning: core's WP_Post declares every column of the posts table with a default, so the
// author of a post that has none is 0 and `can_view()` reads it without asking whether it is
// set. A stub that left the property out is why that line warned on every run of this suite.
$shape = new ReflectionProperty( 'WP_Post', 'post_author' );
ck( 'the WP_Post stub declares post_author with core\'s default, so the read above is core\'s read', array( $shape->isPublic(), ( new WP_Post() )->post_author ), array( true, 0 ) );
// The fixture set is shared by the sections below: the seeded post and its author leave with the check.
unset( $GLOBALS['posts'][400], $GLOBALS['pmeta'][400], $GLOBALS['users'][15], $GLOBALS['caps'][15] );
ck( 'and the two ids are the posts this check means', array( get_post( 102 )->post_title, get_post( 101 )->post_title ), array( 'Students post', 'Everyone post' ) );

echo "\n=== What the column lists ===\n";

foreach ( array( 'student', 'mentor', 'institution' ) as $who ) {
	$GLOBALS['uid'] = $people[ $who ]['id'];

	$titles = array();

	foreach ( WPCPM_Updates::posts( 10 ) as $post ) {
		$titles[] = $post->post_title;
	}

	sort( $titles );

	// A student or mentor also holds the Students and mentors level's grant (Sponsors module
	// Phase S3), so their own view - unlike the institution's - includes its post too.
	$wants = array( 'Everyone post', ucfirst( $who ) . 's post' );

	if ( in_array( $who, array( 'student', 'mentor' ), true ) ) {
		$wants[] = 'Students and mentors post';
		sort( $wants );
	}

	ck( sprintf( 'the %s column lists only their own and the public one', $who ),
	    $titles,
	    $wants );
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
        false !== strpos( $column, 'Students and mentors post' ),
    ),
    array( true, true, false, false, false, true ) );

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

	// The shared level reaches this card too, now that `levels_for()` lists it for both the
	// student and the mentor audience (Sponsors module Phase S3).
	$wants = array( 'Everyone post', $own, 'Students and mentors post' );
	sort( $wants );

	ck( sprintf( 'a manager on the %s card sees the %s view', $audience, $audience ),
	    $titles, $wants );
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

if ( PHP_VERSION_ID < 80100 ) {
	// No effect since 8.1 and deprecated from 8.5; still required on the 7.4 the plugin supports.
	$levels_for->setAccessible( true );
}

ck( 'the sponsor audience maps to the sponsor level',
    $levels_for->invoke( null, 'sponsor' ), array( 'public', 'wpcpm_sponsor' ) );

// The sixth level `levels_for()` now adds (Sponsors module Phase S3): a sponsor's guide or a
// program-wide announcement posted once at the shared level reaches both the student and the
// mentor column, and reaches no other column.
ck( 'the student audience lists the shared level after its own', $levels_for->invoke( null, 'student' ), array( 'public', WPCPM_Roles::ROLE_STUDENT, WPCPM_Content_Access::LEVEL_STUDENTS_MENTORS ) );
ck( 'so does the mentor audience', $levels_for->invoke( null, 'mentor' ), array( 'public', WPCPM_Roles::ROLE_MENTOR, WPCPM_Content_Access::LEVEL_STUDENTS_MENTORS ) );
ck( 'and the institution, administrator and sponsor audiences do not', array( $levels_for->invoke( null, 'institution' ), $levels_for->invoke( null, 'administrator' ), $levels_for->invoke( null, 'sponsor' ) ), array( array( 'public', WPCPM_Roles::ROLE_INSTITUTION ), array( 'public', WPCPM_Roles::ROLE_ADMIN ), array( 'public', WPCPM_Roles::ROLE_SPONSOR ) ) );

// And the audience is a narrowing, never a widening: a student on a mentor-audience render must
// still not get mentor-only content, because `can_view()` is underneath. The shared level is not
// mentor-only, so it legitimately reaches them here too - they hold its grant regardless of
// whose card this is.
$GLOBALS['uid'] = $people['student']['id'];
$titles         = array();

foreach ( WPCPM_Updates::posts( 20, 'mentor' ) as $post ) {
	$titles[] = $post->post_title;
}

ck( 'the audience cannot widen what a reader is entitled to', $titles, array( 'Students and mentors post', 'Everyone post' ) );

// No audience at all is the viewer's own view, unchanged. Six now, not five: the manager reads
// every level, and the Students and mentors level added a sixth post to the fixture above.
$GLOBALS['uid'] = $people['manager']['id'];
ck( 'with no audience it is still the viewer\'s view', count( WPCPM_Updates::posts( 20 ) ), 6 );

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

echo "\n=== Private posts, on the Administrator Dashboard only ===\n";

// The biweekly site update is a private post at the Administrators level (owner, 21 October
// 2026): never published, so Jetpack never emails or syndicates it, and listed in the
// Administrator Dashboard's column for the Administrators who may read it. Every other card,
// and the viewer's own view, lists published posts alone, as before.
$GLOBALS['posts'] = array();
$GLOBALS['pmeta'] = array();
seed_post( 500, 'Announcement', '2026-10-01 10:00:00', 'public' );
seed_post( 501, 'Site update', '2026-10-09 10:00:00', WPCPM_Roles::ROLE_ADMIN, array( 7 ), 'private' );
seed_post( 502, 'Private at the public level', '2026-10-05 10:00:00', 'public', array( 7 ), 'private' );
seed_post( 503, 'Private elsewhere', '2026-10-10 10:00:00', WPCPM_Roles::ROLE_ADMIN, array( 8 ), 'private' );
seed_post( 504, 'Draft update', '2026-10-11 10:00:00', WPCPM_Roles::ROLE_ADMIN, array( 7 ), 'draft' );

// An Administrator as the site has them: the program's capability and core's private reading.
$GLOBALS['users'][16] = new WP_User( 16, 'administrator' );
$GLOBALS['caps'][16]  = array( WPCPM_Roles::CAP_MANAGE, 'read_private_posts' );

/**
 * The titles a card lists for the current reader, in the column's order.
 *
 * @param string $audience Whose card.
 * @return string[]
 */
function titles_for( $audience ) {
	$titles = array();

	foreach ( WPCPM_Updates::posts( 20, $audience ) as $post ) {
		$titles[] = $post->post_title;
	}

	return $titles;
}

$GLOBALS['uid'] = 16;

ck( 'an Administrator\'s column lists the private updates with the published ones, newest first', titles_for( 'administrator' ), array( 'Site update', 'Private at the public level', 'Announcement' ) );
ck( 'and asks for published and private posts, and nothing else', $GLOBALS['last_statuses'], array( 'publish', 'private' ) );

$column = WPCPM_Updates::render_column( 'administrator' );

ck( 'the rendered column links the private update by its title alone, without core\'s "Private: "',
    array(
        false !== strpos( $column, '>Site update</a>' ),
        false !== strpos( $column, 'Private: ' ),
        false !== strpos( $column, 'https://example.test/?p=501' ),
    ),
    array( true, false, true ) );
ck( 'and takes its title filter off again, so every other title on the page keeps core\'s prefix', array( get_the_title( 501 ), get_the_title( 500 ) ), array( 'Private: Site update', 'Announcement' ) );
ck( 'a private post in another category, and a draft in this one, are not listed', array( false !== strpos( $column, 'Private elsewhere' ), false !== strpos( $column, 'Draft update' ) ), array( false, false ) );

foreach ( array( 'student', 'mentor', 'institution', 'sponsor', '' ) as $audience ) {
	ck( sprintf( 'the %s keeps published posts alone, for an Administrator too', '' === $audience ? 'viewer\'s own view' : $audience . ' card' ), in_array( 'Site update', titles_for( $audience ), true ) || in_array( 'Private at the public level', titles_for( $audience ), true ), false );
}

ck( 'and asks for published posts alone', $GLOBALS['last_statuses'], array( 'publish' ) );

// Fails closed: the program's capability without core's private reading reads no private post,
// and neither does anybody else, whatever card they are shown.
$GLOBALS['uid'] = $people['manager']['id'];
ck( 'a manager who cannot read private posts gets the published ones alone', titles_for( 'administrator' ), array( 'Announcement' ) );

foreach ( array( 'student', 'mentor', 'institution', 'logged out' ) as $who ) {
	$GLOBALS['uid'] = $people[ $who ]['id'];
	ck( sprintf( 'a %s shown the administrator card reads no private post', $who ), titles_for( 'administrator' ), array( 'Announcement' ) );
}

// A private post's author reads it, as core lets them and as the access level's author rule
// does (1.95.1), whatever its level.
$GLOBALS['posts'][501]->post_author = $people['mentor']['id'];
$GLOBALS['uid']                     = $people['mentor']['id'];
ck( 'its author reads their own private post on the administrator card', titles_for( 'administrator' ), array( 'Site update', 'Announcement' ) );
$GLOBALS['posts'][501]->post_author = 0;

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
