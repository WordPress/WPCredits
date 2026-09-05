<?php
/**
 * The roles the plugin owns, and everything that has to move with them.
 *
 * A module is not one file. Adding an audience means a role slug, a marker capability, a
 * grant to Administrator, a matching removal on uninstall, an access level in the editor, a
 * notice audience, an entry in the module registry - and every one of those is somewhere
 * else. Nothing fails loudly when one is missed: the role simply cannot read its own
 * content, or a capability survives an uninstall on a site that removed the plugin.
 *
 * So this asserts the wiring rather than any one behaviour, and does it in a loop over the
 * roles, so the next audience is covered the day it is added.
 *
 * Run from the plugin root:  php bin/test-roles.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$fail = 0;

function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_attr__( $s, $d = null ) { return $s; }
function _x( $s, $c, $d = null ) { return $s; }
function apply_filters( $tag, $value ) { return $value; }
function sprintf_noop() {}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
require_once __DIR__ . '/stubs/caps.php';
class WP_Role {
	public $name, $capabilities;
	public function __construct( $name = '', $caps = array() ) {
		$this->name         = $name;
		$this->capabilities = $caps;
	}
	public function add_cap( $cap, $grant = true ) { $this->capabilities[ $cap ] = $grant; }
	public function remove_cap( $cap ) { unset( $this->capabilities[ $cap ] ); }
}

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';

/**
 * Compare and report.
 *
 * @param string $label    What is being asserted.
 * @param array  $actual   Actual values.
 * @param array  $expected Expected values.
 */
function ck( $label, $actual, $expected ) {
	global $fail;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . implode( ', ', array_map( 'strval', $expected ) ) . "\n";
		echo "       actual:   " . implode( ', ', array_map( 'strval', $actual ) ) . "\n";
	}
}

echo "=== The roles themselves ===\n";

$roles = WPCPM_Roles::custom_roles();

ck( 'every audience has a role',
    array_keys( $roles ),
    array(
        WPCPM_Roles::ROLE_STUDENT,
        WPCPM_Roles::ROLE_MENTOR,
        WPCPM_Roles::ROLE_INSTITUTION,
        WPCPM_Roles::ROLE_SPONSOR,
    ) );

// Bare `student` or `sponsor` would be shared with whatever else claims it - an LMS, a
// donations plugin - and sharing a slug means sharing its capability set.
$unprefixed = array();
$unlabelled = array();
$capless    = array();
foreach ( $roles as $slug => $role ) {
	if ( 0 !== strpos( $slug, 'wpcpm_' ) ) { $unprefixed[] = $slug; }
	if ( empty( $role['label'] ) ) { $unlabelled[] = $slug; }
	if ( empty( $role['cap'] ) || 0 !== strpos( $role['cap'], 'wpcpm_view_' ) ) { $capless[] = $slug; }
}

ck( 'every slug is prefixed, labelled, and carries one view capability',
    array( $unprefixed, $unlabelled, $capless ),
    array( array(), array(), array() ) );

// One marker capability each. Two roles sharing one would let either read the other's
// content, which is the one thing the marker exists to prevent.
$caps = array_column( $roles, 'cap' );
ck( 'no two roles share a marker capability', array( count( $caps ), count( array_unique( $caps ) ) ), array( count( $roles ), count( $roles ) ) );

echo "\n=== What Administrator is granted, and what uninstall takes back ===\n";

$admin_caps = WPCPM_Roles::administrator_caps();

ck( 'Administrator gets every marker capability plus the management one',
    $admin_caps,
    array_merge( $caps, array( WPCPM_Roles::CAP_MANAGE ) ) );

// The bug this guards: a cap granted on activation but missing from the uninstall list
// stays on the Administrator role of a site that has removed the plugin. Both paths read
// this one list, so the assertion is that they still do.
$src = file_get_contents( __DIR__ . '/../includes/class-wpcpm-roles.php' );
ck( 'grant and removal both read that one list, rather than repeating it',
    array( substr_count( $src, 'self::administrator_caps()' ) ),
    array( 2 ) );

echo "\n=== The schema version ===\n";

// Sites update by dropping in files rather than by re-activating, so a new role only
// reaches them when this number moves and `maybe_upgrade()` re-runs the registration.
ck( 'the schema version is past 1, so the Sponsor role reaches existing sites',
    array( WPCPM_Roles::SCHEMA_VERSION >= 2 ),
    array( true ) );

echo "\n=== Everything that has to know about a role ===\n";

$access   = file_get_contents( __DIR__ . '/../includes/class-wpcpm-content-access.php' );
$notices  = file_get_contents( __DIR__ . '/../includes/class-wpcpm-notices.php' );
$registry = file_get_contents( __DIR__ . '/../includes/class-wpcpm-modules.php' );
$loader   = file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );

// The editor's access levels are derived from the roles rather than listed, which is why
// adding one needs no work here. Asserted so nobody replaces the loop with a hand-written
// list and quietly drops the newest role from the control.
ck( 'access levels are derived from the roles, not listed by hand',
    array( false !== strpos( $access, 'foreach ( WPCPM_Roles::custom_roles() as $slug => $role )' ) ),
    array( true ) );

foreach ( array( 'sponsors' => 'WPCPM_Sponsors', 'institutions' => 'WPCPM_Institutions', 'students' => 'WPCPM_Students', 'mentors' => 'WPCPM_Mentors' ) as $id => $class ) {
	ck( sprintf( 'the %s module is loaded and registered', $id ),
	    array(
	        false !== strpos( $loader, "class-wpcpm-{$id}.php" ),
	        false !== strpos( $registry, "new {$class}()" ),
	    ),
	    array( true, true ) );
}

// A notice aimed at Sponsors would silently reach nobody if the audience were listed
// without a matching membership test - the switch returns false for anything it does not
// recognise, so the notice just never appears.
ck( 'Sponsors are a notice audience, and membership is actually tested',
    array(
        false !== strpos( $notices, "'sponsor'     => __( 'Sponsors'" ),
        false !== strpos( $notices, "case 'sponsor':" ),
        false !== strpos( $notices, 'WPCPM_Roles::ROLE_SPONSOR' ),
    ),
    array( true, true, true ) );

echo "\n=== The dashboards in the toolbar ===\n";

/*
 * The two dashboards that are always loaded, stubbed to the two answers `links()` actually
 * reads: where the page is, and whether it is the viewer's own. The real classes are
 * sixteen hundred lines that want a database, and none of that decides what goes in the
 * toolbar.
 */
$GLOBALS['can_manage'] = false;
$GLOBALS['pages']      = array(
	'student'     => 'https://example.test/student-report-card/',
	'mentor'      => 'https://example.test/mentor-report-card/',
	'institution' => 'https://example.test/institution-dashboard/',
);
$GLOBALS['own'] = array( 'student' => false, 'mentor' => false, 'institution' => false );

/** Stands in for the student dashboard. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return $GLOBALS['pages']['student']; }
	public static function is_student( $user = null ) { return $GLOBALS['own']['student']; }
}
/** Stands in for the mentor dashboard. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return $GLOBALS['pages']['mentor']; }
	public static function is_mentor( $user = null ) { return $GLOBALS['own']['mentor']; }
}

require_once __DIR__ . '/../includes/class-wpcpm-dashboards.php';

/**
 * The toolbar entries as `id|title|own`, which is everything a caller reads off one.
 *
 * @return string[]
 */
function toolbar() {
	$out = array();

	foreach ( WPCPM_Dashboards::links() as $link ) {
		$out[] = $link['id'] . '|' . $link['title'] . '|' . ( $link['own'] ? 'own' : 'other' );
	}

	return $out;
}

// The institution dashboard is a later class in the same module, and this suite loads
// neither it nor the loader, so this is the real "not installed yet" state, asserted before
// anything declares the stand-in. A guard that only *looks* right is a menu item that
// fatals every page of the site on the release where the class is missing.
$GLOBALS['can_manage'] = true;
ck( 'without the institution dashboard class the toolbar holds the two it always had',
    toolbar(),
    array( 'wpcpm-student-dashboard|Student Dashboard|other', 'wpcpm-mentor-dashboard|Mentor Dashboard|other' ) );

$GLOBALS['can_manage']         = false;
$GLOBALS['own']['institution'] = true;
ck( 'and a member of an institution is offered nothing either', toolbar(), array() );

if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
	/** Stands in for the institution dashboard: a page, and membership rather than the role. */
	class WPCPM_Institutions_Dashboard {
		public static function page_url() { return $GLOBALS['pages']['institution']; }
		public static function is_member( $user = null ) { return $GLOBALS['own']['institution']; }
	}
}

// The page's own name in both cases. Open question 14 settled it as "Institution Dashboard"
// and put a TITLE_VERSION behind it so one rename reaches every site; a second name for the
// same page in the toolbar is exactly what that mechanism exists to prevent. The `own` flag
// still says whose it is.
ck( 'a member gets their own institution, named as theirs',
    toolbar(), array( 'wpcpm-institution-dashboard|Institution Dashboard|own' ) );

$GLOBALS['own']['institution'] = false;
$GLOBALS['can_manage']         = true;
ck( 'a manager gets all three, and is told the institution one is not theirs',
    toolbar(),
    array(
        'wpcpm-student-dashboard|Student Dashboard|other',
        'wpcpm-mentor-dashboard|Mentor Dashboard|other',
        'wpcpm-institution-dashboard|Institution Dashboard|other',
    ) );

// A page that does not exist yet, or is in the trash: `page_url()` returns '' and the entry
// has to go, because a toolbar link to nothing is worse than no link at all.
$GLOBALS['pages']['institution'] = '';
ck( 'no page, no entry, not even for a manager',
    toolbar(),
    array( 'wpcpm-student-dashboard|Student Dashboard|other', 'wpcpm-mentor-dashboard|Mentor Dashboard|other' ) );

$GLOBALS['pages']['institution'] = 'https://example.test/institution-dashboard/';
$GLOBALS['can_manage']           = false;
ck( 'and somebody who is neither is offered nothing', toolbar(), array() );

echo "\n=== What an empty dashboard says, per audience ===\n";

// The fall-through this used to have told everybody who was not a student that they did not
// hold the Mentor role. For an institution that is wrong twice over: wrong role, and the
// wrong test: membership is what the page reads.
// Asserted on what has to be true rather than on the exact sentence, because the wording is
// the product owner's to change and a test that pins prose is a test that gets edited to
// match whatever the code now says.
$member_sees = WPCPM_Dashboards::nothing_to_show( 'institutions', false );
ck( 'an institution is told about institutions, and never about the Mentor role',
    array(
        false !== stripos( $member_sees, 'institution' ),
        false !== strpos( $member_sees, 'Mentor role' ),
    ),
    array( true, false ) );

// A manager sees an empty page when no institution has an account, which no amount of
// syncing fixes: the resolver falls back to the first institution *with a live member*. So
// this one points at the screen that provisions and never says "sync".
$manager_sees = WPCPM_Dashboards::nothing_to_show( 'institutions', true );
ck( 'a manager is sent to the Institutions screen, and is not told to run a sync',
    array(
        false !== strpos( $manager_sees, 'admin.php?page=wpcpm-institutions' ),
        false !== stripos( $manager_sees, 'sync' ),
    ),
    array( true, false ) );

// The two that were there first, unchanged by the third.
ck( 'the student and mentor wordings are untouched',
    array(
        WPCPM_Dashboards::nothing_to_show( 'students', false ),
        WPCPM_Dashboards::nothing_to_show( 'mentors', false ),
    ),
    array(
        'This page is for program students. Your account is not linked to a student record.',
        'This page is for program mentors. Your account does not hold the Mentor role.',
    ) );
ck( 'and an unknown module ID still lands on the mentor wording it always did',
    array( WPCPM_Dashboards::nothing_to_show( 'nonsense', false ) ),
    array( 'This page is for program mentors. Your account does not hold the Mentor role.' ) );

// The fourth audience. A non-manager is told whose page this is and never about the Mentor
// role; a manager with nothing waiting is pointed at the Administrators screen.
ck( 'a non-manager is told the Administrator Dashboard is for the program managers',
    WPCPM_Dashboards::nothing_to_show( 'administrators', false ),
    'This page is for the program managers. Your account cannot manage the program.' );
$manager_admin_sees = WPCPM_Dashboards::nothing_to_show( 'administrators', true );
ck( 'a manager with nothing waiting is sent to the Administrators screen',
    array(
        false !== strpos( $manager_admin_sees, 'Nothing is waiting for a manager right now.' ),
        false !== strpos( $manager_admin_sees, 'admin.php?page=wpcpm-administrators' ),
    ),
    array( true, true ) );

// The fifth audience. A non-member is told whose page this is and never about the Mentor role;
// a manager with no sponsor account yet is pointed at the Sponsors screen.
ck( 'a non-member is told the Sponsor Dashboard is for the program sponsors',
    WPCPM_Dashboards::nothing_to_show( 'sponsors', false ),
    'This page is for the program sponsors. Your account is not attached to a sponsor.' );
$manager_sponsor_sees = WPCPM_Dashboards::nothing_to_show( 'sponsors', true );
ck( 'a manager with no sponsor account yet is sent to the Sponsors screen',
    array(
        false !== strpos( $manager_sponsor_sees, 'No sponsor has an account yet.' ),
        false !== strpos( $manager_sponsor_sees, 'admin.php?page=wpcpm-sponsors' ),
    ),
    array( true, true ) );

echo "\n=== A user ID out of whatever get_users() returned ===\n";

// This site's stack returns `stdClass` rows even when the query asked for `'ID'`, so the shape is
// never assumed. Casting such a row straight to int is what raised "Object of class stdClass could
// not be converted to int" on every Student Report Card render.
$row     = new stdClass();
$row->ID = 42;

ck( 'an int passes through', WPCPM_Roles::id_of( 7 ), 7 );
ck( 'a numeric string is an ID too', WPCPM_Roles::id_of( '7' ), 7 );
ck( 'a row object yields its ID - stdClass or WP_User, the branch is the same', WPCPM_Roles::id_of( $row ), 42 );
ck( 'anything else is nobody',
    array( WPCPM_Roles::id_of( null ), WPCPM_Roles::id_of( 'abc' ), WPCPM_Roles::id_of( new stdClass() ) ),
    array( 0, 0, 0 ) );

// The loader and uninstall.php build their class lists by hand, in parallel, and the day they
// differ is a fatal in the middle of cleanup that says nothing: `WPCPM_Modules::uninstall()`
// instantiates every module, and class-wpcpm-sponsors.php was missing from uninstall.php for
// ten releases. Every class file the loader requires unconditionally must be required there too.
$loader_src    = file_get_contents( dirname( __DIR__ ) . '/wpcredits-program-manager.php' );
$uninstall_src = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );
preg_match_all( "/^require_once WPCPM_PLUGIN_DIR \. '([^']+)';/m", $loader_src, $in_loader );
preg_match_all( "/^require_once plugin_dir_path\( __FILE__ \) \. '([^']+)';/m", $uninstall_src, $in_uninstall );
$loader_only = array_values( array_diff( $in_loader[1], $in_uninstall[1], array( 'includes/class-wpcpm-admin.php', 'includes/class-wpcpm-dashboards.php', 'includes/class-wpcpm-cli.php' ) ) );
ck( 'every class the loader requires is required by uninstall.php too (admin, dashboards and CLI excepted)', $loader_only, array() );
ck( 'and uninstall.php requires nothing the loader does not', array_values( array_diff( $in_uninstall[1], $in_loader[1] ) ), array() );
foreach ( $in_uninstall[1] as $rel ) {
	if ( ! file_exists( dirname( __DIR__ ) . '/' . $rel ) ) {
		ck( 'uninstall.php requires a file that exists: ' . $rel, false, true );
	}
}

// The third list nobody maintains by hand: the files themselves. A class file that lands in
// `includes/` with no `require_once` for it is neither a fatal nor a failing test. It is a
// class that silently never loads, so every `class_exists()` guard around it goes on
// reporting "not installed yet" and the feature is simply absent. Read off disk rather than
// listed here, so the next file is covered the day it lands.
preg_match_all( "/require_once WPCPM_PLUGIN_DIR \. '([^']+)';/", $loader_src, $anywhere );
$on_disk = array();
foreach ( array( 'includes', 'includes/modules', 'includes/tools' ) as $dir ) {
	foreach ( glob( dirname( __DIR__ ) . '/' . $dir . '/class-wpcpm-*.php' ) as $path ) {
		$on_disk[] = $dir . '/' . basename( $path );
	}
}
ck( 'every class file under includes/ is required by the loader',
    array_values( array_diff( $on_disk, $anywhere[1] ) ), array() );

// The institution dashboard's own wiring, asserted from the day the file exists rather than
// from a name written here in advance. Its page ID and its title-version flag are two options
// on a live site, and an option an uninstall leaves behind is invisible until somebody
// reinstalls: the page comes back pointing at a post that is not there any more, and the
// title migration never runs again because the flag says it already did.
$dashboard_file = 'includes/modules/class-wpcpm-institutions-dashboard.php';

if ( file_exists( dirname( __DIR__ ) . '/' . $dashboard_file ) ) {
	ck( 'the institution dashboard is loaded, and uninstall.php can see it',
	    array(
	        in_array( $dashboard_file, $anywhere[1], true ),
	        in_array( $dashboard_file, $in_uninstall[1], true ),
	    ),
	    array( true, true ) );

	/*
	 * Searched inside a `delete_option()` call, and across everything `uninstall.php` pulls
	 * in: the class file declares both constants and is required there, so a bare name search
	 * would pass on the declaration alone, and the other two dashboards have their page
	 * options deleted by their module's `uninstall()` rather than by `uninstall.php` itself.
	 * Either spelling of the name counts; what is asserted is that something on the uninstall
	 * path deletes it.
	 */
	$reach = $uninstall_src;

	foreach ( $in_uninstall[1] as $rel ) {
		$path = dirname( __DIR__ ) . '/' . $rel;

		if ( file_exists( $path ) ) {
			$reach .= file_get_contents( $path );
		}
	}

	$deleted = array();

	foreach ( array( 'OPT_PAGE' => 'wpcpm_institution_page_id', 'OPT_TITLE_FIXED' => 'wpcpm_institution_page_title_fixed' ) as $constant => $option ) {
		$deleted[] = (bool) preg_match(
			'/delete_option\(\s*(?:WPCPM_Institutions_Dashboard::' . $constant . "|'" . $option . "')\s*\)/",
			$reach
		);
	}

	ck( 'and its page option and title option are both deleted on uninstall', $deleted, array( true, true ) );
} else {
	echo "--   the institution dashboard has not landed yet: " . $dashboard_file . "\n";
}

// The administrator dashboard's own wiring, asserted the same way once the file exists: its
// page ID and its title-version flag are two options a removed plugin must not leave behind.
$dashboard_file = 'includes/modules/class-wpcpm-administrators-dashboard.php';

if ( file_exists( dirname( __DIR__ ) . '/' . $dashboard_file ) ) {
	ck( 'the administrator dashboard is loaded, and uninstall.php can see it',
	    array(
	        in_array( $dashboard_file, $anywhere[1], true ),
	        in_array( $dashboard_file, $in_uninstall[1], true ),
	    ),
	    array( true, true ) );

	// The deletes live in class-wpcpm-administrators.php's uninstall(), not in this class file
	// itself, so the search reaches across everything uninstall.php pulls in, the same way the
	// institution dashboard's check above does.
	$reach = $uninstall_src;

	foreach ( $in_uninstall[1] as $rel ) {
		$path = dirname( __DIR__ ) . '/' . $rel;

		if ( file_exists( $path ) ) {
			$reach .= file_get_contents( $path );
		}
	}

	$deleted = array();

	foreach ( array( 'OPT_PAGE' => 'wpcpm_administrator_page_id', 'OPT_TITLE_FIXED' => 'wpcpm_administrator_page_title_fixed' ) as $constant => $option ) {
		$deleted[] = (bool) preg_match(
			'/delete_option\(\s*(?:WPCPM_Administrators_Dashboard::' . $constant . "|'" . $option . "')\s*\)/",
			$reach
		);
	}

	ck( 'and its page option and title option are both deleted on uninstall', $deleted, array( true, true ) );
} else {
	echo "--   the administrator dashboard has not landed yet: " . $dashboard_file . "\n";
}

// The sponsor dashboard's own wiring, asserted the same way once the file exists: its page ID
// and its title-version flag are two options a removed plugin must not leave behind.
$dashboard_file = 'includes/modules/class-wpcpm-sponsors-dashboard.php';

if ( file_exists( dirname( __DIR__ ) . '/' . $dashboard_file ) ) {
	ck( 'the sponsor dashboard is loaded, and uninstall.php can see it',
	    array(
	        in_array( $dashboard_file, $anywhere[1], true ),
	        in_array( $dashboard_file, $in_uninstall[1], true ),
	    ),
	    array( true, true ) );

	// The deletes live in class-wpcpm-sponsors.php's uninstall(), not in this class file itself,
	// so the search reaches across everything uninstall.php pulls in, the same way the
	// administrator dashboard's check above does.
	$reach = $uninstall_src;

	foreach ( $in_uninstall[1] as $rel ) {
		$path = dirname( __DIR__ ) . '/' . $rel;

		if ( file_exists( $path ) ) {
			$reach .= file_get_contents( $path );
		}
	}

	$deleted = array();

	foreach ( array( 'OPT_PAGE' => 'wpcpm_sponsor_page_id', 'OPT_TITLE_FIXED' => 'wpcpm_sponsor_page_title_fixed' ) as $constant => $option ) {
		$deleted[] = (bool) preg_match(
			'/delete_option\(\s*(?:WPCPM_Sponsors_Dashboard::' . $constant . "|'" . $option . "')\s*\)/",
			$reach
		);
	}

	ck( 'and its page option and title option are both deleted on uninstall', $deleted, array( true, true ) );
} else {
	echo "--   the sponsor dashboard has not landed yet: " . $dashboard_file . "\n";
}
// The ceiling's rows are `add_option()` claims named by a hash, one per key per window, and no
// uninstall reaches them by name: they need the class's own `delete_all()` on the uninstall
// path and the sweep's schedule cleared, or a removed plugin leaves a heap of rows that nothing
// will ever read and a cron hook whose callback is gone. And the class must be `init()`ed
// from the boot path, or the schedule fires into nothing and the heap grows while the plugin
// is installed too. Asserted from the day the file exists, like the dashboard above.
$ceiling_file = 'includes/class-wpcpm-ceiling.php';

if ( file_exists( dirname( __DIR__ ) . '/' . $ceiling_file ) ) {
	ck( 'the ceiling is loaded, and uninstall.php can see it',
	    array(
	        in_array( $ceiling_file, $anywhere[1], true ),
	        in_array( $ceiling_file, $in_uninstall[1], true ),
	    ),
	    array( true, true ) );

	$boot_path = $loader_src . $registry . file_get_contents( dirname( __DIR__ ) . '/includes/modules/class-wpcpm-institutions.php' );
	ck( 'and its sweep is hooked from the boot path', false !== strpos( $boot_path, 'WPCPM_Ceiling::init()' ), true );

	$reach = $uninstall_src;

	foreach ( $in_uninstall[1] as $rel ) {
		$path = dirname( __DIR__ ) . '/' . $rel;

		if ( file_exists( $path ) ) {
			$reach .= file_get_contents( $path );
		}
	}

	ck( 'its rows are deleted and its sweep unscheduled on uninstall',
	    array(
	        false !== strpos( $reach, 'WPCPM_Ceiling::delete_all()' ),
	        (bool) preg_match( '/wp_clear_scheduled_hook\(\s*(?:WPCPM_Ceiling::CRON_SWEEP|\'wpcpm_ceiling_sweep\')\s*\)/', $reach ),
	    ),
	    array( true, true ) );
} else {
	echo "--   the ceiling has not landed yet: " . $ceiling_file . "\n";
}
/* ---- every post type this plugin declares is short enough to exist ------- */

// `register_post_type()` refuses a name longer than twenty characters and returns a WP_Error
// that nothing here was reading, so a too-long name is not a warning: the type simply does not
// exist, silently, while `get_posts()` goes on querying it. That is how `wpcpm_institution_app`
// shipped unregistered in 1.73.0. Read off the source rather than from a list kept by hand,
// because the list that gets forgotten is the one that would have caught this.
$declared = array();

foreach ( glob( dirname( __DIR__ ) . '/includes/**/*.php' ) + glob( dirname( __DIR__ ) . '/includes/*.php' ) as $file ) {
	if ( preg_match_all( "/const POST_TYPE\s*=\s*'([a-z0-9_]+)'/", (string) file_get_contents( $file ), $m ) ) {
		foreach ( $m[1] as $name ) {
			$declared[ $name ] = basename( $file );
		}
	}
}

ck( 'the plugin declares post types where this can see them', count( $declared ) > 4, true );

$too_long = array();

foreach ( $declared as $name => $file ) {
	if ( strlen( $name ) > 20 ) {
		$too_long[] = $name . ' (' . strlen( $name ) . ', ' . $file . ')';
	}
}

ck( 'and every one of them is inside WordPress\'s twenty-character limit', $too_long, array() );


echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
