<?php
/**
 * The roles the plugin owns, and everything that has to move with them.
 *
 * A module is not one file. Adding an audience means a role slug, a marker capability, a
 * grant to Administrator, a matching removal on uninstall, an access level in the editor, a
 * notice audience, an entry in the module registry — and every one of those is somewhere
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

// Bare `student` or `sponsor` would be shared with whatever else claims it — an LMS, a
// donations plugin — and sharing a slug means sharing its capability set.
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
// without a matching membership test — the switch returns false for anything it does not
// recognise, so the notice just never appears.
ck( 'Sponsors are a notice audience, and membership is actually tested',
    array(
        false !== strpos( $notices, "'sponsor'     => __( 'Sponsors'" ),
        false !== strpos( $notices, "case 'sponsor':" ),
        false !== strpos( $notices, 'WPCPM_Roles::ROLE_SPONSOR' ),
    ),
    array( true, true, true ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
