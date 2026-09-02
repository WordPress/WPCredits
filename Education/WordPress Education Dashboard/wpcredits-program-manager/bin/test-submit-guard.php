<?php
/**
 * Static assertions on the submit guard.
 *
 * Run from the plugin root:  php bin/test-submit-guard.php
 *
 * The one that matters: a *disabled* control is not serialized, and the slot buttons carry
 * the value being submitted (`name="start" value="…"`). Disabling the pressed button inside
 * the submit handler would post the form with no slot in it — booking would break outright,
 * which is worse than the confusion this fixes. So the pressed button must only be disabled
 * from a deferred callback, and the others (which carry nothing) immediately.
 *
 * The guard lives in assets/js/forms.js. It started as the tail of calendar.js and moved out
 * when the Institutions screens - forms, no calendar - needed it, so this also pins the move:
 * the guard is defined once, calendar.js neither defines nor calls it, the module registers
 * `wpcpm-forms` beside the calendar script and names it as that script's dependency. That
 * last one is what keeps every page that had the guard on it. The same file pins admin.js
 * polling every `[data-wpcpm-progress]` on a page rather than the first, because the
 * Institutions screen draws a sync panel and a provisioning run on one page.
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root     = dirname( __DIR__ );
$forms    = $root . '/assets/js/forms.js';
$js       = is_file( $forms ) ? file_get_contents( $forms ) : '';
$calendar = file_get_contents( $root . '/assets/js/calendar.js' );
$admin    = file_get_contents( $root . '/assets/js/admin.js' );
$module   = file_get_contents( $root . '/includes/modules/class-wpcpm-call-calendar.php' );

$fail = 0;
function ck( $l, $a, $e = true ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $l . "\n";
	if ( ! $ok ) { echo "       exp: " . var_export( $e, true ) . "  got: " . var_export( $a, true ) . "\n"; }
}

// Where the guard lives: forms.js, and nowhere else.
ck( 'forms.js exists and defines the guard',
    (bool) strpos( $js, 'function guardForms()' ) && (bool) strpos( $js, 'function guardForm( form )' ) );
ck( 'and wires it, with the bfcache release, once the DOM is ready',
    (bool) preg_match( '/ready\( function \(\) \{\s*guardForms\(\);\s*releaseOnRestore\(\);\s*\} \);/', $js ) );
ck( 'calendar.js no longer defines the guard', false === strpos( $calendar, 'function guardForm' ) );
ck( 'nor calls it',
    false === strpos( $calendar, 'guardForms()' ) && false === strpos( $calendar, 'releaseOnRestore()' ) );

// The submit handler body.
$at = strpos( $js, "form.addEventListener( 'submit'" );
$handler = substr( $js, $at, strpos( $js, "\n\t\t} );", $at ) - $at );

ck( 'the pressed button is disabled only inside a setTimeout',
    (bool) preg_match( '/setTimeout\(\s*function \(\) \{\s*button\.disabled = true;/', $handler ) );

// Every `X.disabled = true` in the handler is either inside the timeout or guarded by a
// "not the pressed button" test.
preg_match_all( '/^\s*([A-Za-z_][\w\[\]\s]*?)\.disabled = true;/m', $handler, $sets, PREG_SET_ORDER );
$targets = array_map( function ( $m ) { return preg_replace( '/\s+/', ' ', trim( $m[1] ) ); }, $sets );
ck( 'the only things disabled are `button` (deferred) and loop members',
    array_values( array_unique( $targets ) ), array( 'buttons[ i ]', 'button' ) );

ck( 'the loop skips the pressed control before disabling',
    (bool) preg_match( '/if \( buttons\[ i \] !== button \) \{[^}]*buttons\[ i \]\.disabled = true;/s', $handler ) );

ck( 'a repeat submit is swallowed, not posted',
    (bool) preg_match( "/data-wpcpm-sent.*?event\.preventDefault\(\)/s", $handler ) );

ck( 'the label is saved as markup, not text',
    (bool) strpos( $handler, "button.innerHTML );" ) );

ck( 'the busy label is written as text (cannot inject)',
    (bool) strpos( $handler, 'button.textContent = busy;' ) );

// bfcache release.
ck( 'a restored page clears the sent flag', (bool) strpos( $js, "removeAttribute( 'data-wpcpm-sent' )" ) );
ck( 'a restored page re-enables the buttons', (bool) preg_match( '/buttons\[ j \]\.disabled = false;/', $js ) );

// The module registers the guard beside the calendar script, and the calendar script
// depends on it: that dependency is what keeps the guard on every page that had it.
ck( 'call-calendar.php registers wpcpm-forms from assets/js/forms.js, in the footer',
    (bool) preg_match( "/wp_register_script\(\s*'wpcpm-forms',\s*WPCPM_PLUGIN_URL \. 'assets\/js\/forms\.js',\s*array\(\),\s*WPCPM_VERSION,\s*true\s*\);/", $module ) );
ck( 'and names it as a dependency of the calendar script',
    (bool) preg_match( "/wp_register_script\(\s*self::SCRIPT,\s*WPCPM_PLUGIN_URL \. 'assets\/js\/calendar\.js',\s*array\( 'wpcpm-forms' \),/", $module ) );

// Every progress panel on a page is polled, not only the first one found.
ck( 'admin.js wires every progress panel',
    (bool) strpos( $admin, "querySelectorAll( '[data-wpcpm-progress]' )" ) );
ck( 'and never only the first',
    false === strpos( $admin, "querySelector( '[data-wpcpm-progress]' )" ) );

// Every guarded form declares a busy label, and the booking form declares a status too.
$php_files = array(
	$root . '/includes/modules/class-wpcpm-call-calendar.php',
	$root . '/includes/modules/class-wpcpm-mentor-availability.php',
	$root . '/includes/modules/class-wpcpm-student-report-form.php',
);
$once = 0; $busy = 0;
foreach ( $php_files as $f ) {
	$src = file_get_contents( $f );
	$once += substr_count( $src, 'data-wpcpm-once' );
	$busy += substr_count( $src, 'data-wpcpm-busy="%2$s"' );
}
ck( 'every guarded form declares a busy label', array( $once, $busy ), array( 6, 6 ) );
ck( 'the booking form declares a visible status',
    (bool) strpos( file_get_contents( $php_files[0] ), 'data-wpcpm-status=' ) );
ck( 'and renders the live region it goes in',
    (bool) strpos( file_get_contents( $php_files[0] ), 'data-wpcpm-busy-status' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
