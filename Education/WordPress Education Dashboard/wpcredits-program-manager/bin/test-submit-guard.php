<?php
/**
 * Static assertions on the submit guard.
 *
 * Run from the plugin root:  php bin/test-submit-guard.php
 *
 * The one that matters: a *disabled* control is not serialized, and the slot buttons carry
 * the value being submitted (`name="start" value="…"`). Disabling the pressed button inside
 * the submit handler would post the form with no slot in it - booking would break outright,
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
ck( 'and wires it, with the bfcache release, the code-selector and the kind switch, once the DOM is ready',
    (bool) preg_match( '/ready\( function \(\) \{\s*guardForms\(\);\s*releaseOnRestore\(\);\s*selectOnClick\(\);\s*showForKind\(\);\s*\} \);/', $js ) );
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
ck( 'a restored page re-enables the buttons', (bool) preg_match( '/controls\[ j \]\.disabled = false;/', $js ) );

/*
 * FFRNT-1. A control does not have to sit inside the form it submits. The sponsor's "Void
 * unclaimed codes" button is printed inside the Add-codes form and posts the void form through
 * `form="wpcpm-offer-void-<id>"`, and that form holds nothing but hidden inputs - so asking the
 * form for its own descendants released nothing, and after a Back the button was still disabled
 * and still read "Voiding" until a full reload.
 *
 * The first check below is the one a crippled release has to fail. Cutting the loop down to
 * `for ( j = 0; j < 1 && j < controls.length; j++ )` used to leave this file printing ALL PASS,
 * because every assertion about the release was a grep for one line inside it. The loop's own
 * bound is read now, and it may be nothing but the length of the list it walks.
 */
$release_at = strpos( $js, 'function releaseControls( controls )' );
$release    = false === $release_at ? '' : substr( $js, $release_at, (int) strpos( $js, "\n\t}", $release_at ) - $release_at );
preg_match_all( '/for \(([^)]*)\)/', $release, $loops );

ck( 'the release loop runs to the end of the list, with no second bound on it',
    array_map( 'trim', $loops[1] ), array( 'j = 0; j < controls.length; j++' ) );

ck( 'and the release reaches the form\'s own controls and the ones that name it in a `form` attribute',
    array(
        (bool) strpos( $js, "releaseControls( form.querySelectorAll( 'button, input[type=\"submit\"]' ) );" ),
        (bool) strpos( $js, "releaseControls( document.querySelectorAll( '[form=\"' + id + '\"]' ) );" ),
    ),
    array( true, true ) );

// The id is put straight into a selector, so it is escaped on the way in. The ids the plugin
// prints are its own integers today, but an id carrying a quote, a bracket or a space makes
// `[form="..."]` a selector the browser refuses - and the exception is thrown inside the
// pageshow handler, which ends the loop and leaves every form after it on the page disabled
// with its button still reading "Sending" (the Task 7 item the whole-branch review pulled in).
ck( 'and the id is escaped before it becomes one, so a malformed id cannot end the loop',
    array(
        (bool) strpos( $js, 'window.CSS && CSS.escape ? CSS.escape( form.id ) : form.id.replace(' ),
        false !== strpos( $js, "'[form=\"' + form.id + '\"]'" ),
    ),
    array( true, false ) );

// The markup the fix is for: the void button carries the `form` attribute, and the form it
// names is printed after it with that id and nothing in it but hidden fields.
$offers = file_get_contents( $root . '/includes/modules/class-wpcpm-sponsor-offers.php' );
ck( 'the void button really does submit a form it does not sit inside',
    array(
        (bool) strpos( $offers, 'form="wpcpm-offer-void-%1$d"' ),
        (bool) strpos( $offers, 'id="wpcpm-offer-void-%4$d"' ),
    ),
    array( true, true ) );

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
	// Any placeholder, not the second one: which argument carries the label is an accident of
	// each printf's own argument list, and pinning `%2$s` made a form with three arguments look
	// like a form with no busy label at all.
	$busy += preg_match_all( '/data-wpcpm-busy="%\d+\$s"/', $src );
}
ck( 'every guarded form declares a busy label', array( $once, $busy ), array( 7, 7 ) );
ck( 'the booking form declares a visible status',
    (bool) strpos( file_get_contents( $php_files[0] ), 'data-wpcpm-status=' ) );
ck( 'and renders the live region it goes in',
    (bool) strpos( file_get_contents( $php_files[0] ), 'data-wpcpm-busy-status' ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
