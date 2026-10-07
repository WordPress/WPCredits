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
 *
 * wp-admin runs the same script: the admin class enqueues `wpcpm-forms` beside admin.js on every
 * plugin screen, so the attribute is live on every form there that prints it, and the forms
 * whose second press sends a second mail or can make a second track carry it with their busy
 * word. All of it is read off the source, as forms.js is.
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
ck( 'and wires it, after the confirm reader and with the bfcache release, the code-selector, the kind switch and the required-with switch, once the DOM is ready',
    (bool) preg_match( '/ready\( function \(\) \{\s*confirmFirst\(\);\s*guardForms\(\);\s*releaseOnRestore\(\);\s*selectOnClick\(\);\s*showForKind\(\);\s*requireWith\(\);\s*\} \);/', $js ) );

// The deep check of 1.109.1, SESSIONS-6: "Cancel the session" and "Leave the session" carried a
// `data-wpcpm-confirm` sentence that no script read, so one press canceled a session for everybody.
// The reader is bound before the guard (the wiring above): listeners on a form run in the order
// they were added, so a No has called preventDefault() by the time the guard's own listener runs,
// and the guard, which stands aside for a prevented submit, leaves the form as it was.
$confirm = substr( $js, (int) strpos( $js, 'function bindConfirm( form )' ) );
$confirm = substr( $confirm, 0, (int) strpos( $confirm, "\n\t}\n" ) );
ck( 'a submit is asked the pressed control\'s data-wpcpm-confirm sentence with window.confirm(), and a No prevents it',
    array(
        (bool) strpos( $js, 'function confirmFirst()' ),
        (bool) strpos( $confirm, "form.addEventListener( 'submit'" ),
        (bool) strpos( $confirm, "getAttribute( 'data-wpcpm-confirm' )" ),
        (bool) preg_match( '/if \( question && ! window\.confirm\( question \) \) \{\s*event\.preventDefault\(\);/', $confirm ),
    ),
    array( true, true, true, true ) );
ck( 'and the guard stands aside for a submit already prevented',
    (bool) preg_match( "/form\.addEventListener\( 'submit', function \( event \) \{.*?if \( event\.defaultPrevented \) \{\s*return;/s", $js ) );
ck( 'the session\'s Cancel and the Leave form carry the sentence the reader asks',
    substr_count( file_get_contents( dirname( __DIR__ ) . '/includes/modules/class-wpcpm-group-sessions.php' ), 'data-wpcpm-confirm="%1$s"' ),
    2 );

// The count of a repeat rule is required, and open, only while a rule is chosen (1.109.1): the
// marked control follows the named control's value on load, on change and after a bfcache restore.
$needs = substr( $js, strpos( $js, 'function bindRequireWith( control )' ) );
$needs = substr( $needs, 0, strpos( $needs, "\n\t}\n" ) );
ck( 'the required-with switch reads the control the mark names from the form',
    (bool) strpos( $needs, "form.elements[ control.getAttribute( 'data-wpcpm-needs' ) ]" ) );
ck( 'and sets required and disabled from that control\'s value, on change and after a bfcache restore',
    (bool) strpos( $needs, "needed = '' !== source.value" ) && (bool) strpos( $needs, 'control.required = needed' ) && (bool) strpos( $needs, 'control.disabled = ! needed' )
    && (bool) strpos( $needs, "source.addEventListener( 'change', apply )" ) && (bool) strpos( $needs, "window.addEventListener( 'pageshow', apply )" ) );
ck( 'the planning form marks its count box with the rule select\'s name',
    (bool) strpos( file_get_contents( dirname( __DIR__ ) . '/includes/modules/class-wpcpm-group-sessions.php' ), 'name="repeat_count" min="2" max="%d" step="1" aria-describedby="wpcpm-sessions-repeat-hint" data-wpcpm-needs="repeat"' ) );
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

/**
 * One method's source, from its signature to the brace that closes it at the class's indent,
 * with its comments taken out by PHP's own tokenizer, so a sentence about the code cannot pass
 * for the code and a `//` inside a string is left alone.
 *
 * @param string $src       The file.
 * @param string $signature The method's signature, as written.
 * @return string The method's code, or '' when the file has no such method.
 */
function method_code( $src, $signature ) {
	$at = strpos( $src, $signature );

	if ( false === $at ) {
		return '';
	}

	$code = '';

	foreach ( token_get_all( '<?php ' . substr( $src, $at, (int) strpos( $src, "\n\t}\n", $at ) - $at ) ) as $token ) {
		if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}

		$code .= is_array( $token ) ? $token[1] : $token;
	}

	return substr( $code, strlen( '<?php ' ) );
}

// wp-admin loads the same guard: the admin class enqueues the handle on the screens it enqueues
// admin.js on, every screen of the plugin's menu and no other, past the one return that keeps
// both to them. It registers nothing: the call calendar registers the handle on `init`, which
// wp-admin runs too, and the Mentors module boots the calendar on every request.
$admin_src = (string) file_get_contents( $root . '/includes/class-wpcpm-admin.php' );
$enqueue   = method_code( $admin_src, 'public function enqueue_assets( $hook_suffix )' );
$to_plugin = "if ( false === strpos( \$hook_suffix, self::MENU_SLUG ) ) {\n\t\t\treturn;\n\t\t}";
$past      = false === strpos( $enqueue, $to_plugin ) ? '' : substr( $enqueue, strpos( $enqueue, $to_plugin ) + strlen( $to_plugin ) );

ck( 'in wp-admin the admin class enqueues wpcpm-forms, once, in enqueue_assets()',
    array( substr_count( $admin_src, "wp_enqueue_script( 'wpcpm-forms' )" ), substr_count( $enqueue, "wp_enqueue_script( 'wpcpm-forms' );" ) ),
    array( 1, 1 ) );
ck( 'on the screens it enqueues wpcpm-admin on: both past the one return, for a screen that is not the plugin\'s, and the guard\'s own line at the method body\'s indent, inside no condition',
    array(
        false !== strpos( $enqueue, $to_plugin ),
        preg_match_all( '/\breturn\b/', $enqueue ),
        (bool) preg_match( "/wp_enqueue_script\(\s*'wpcpm-admin',/", $past ),
        false !== strpos( $past, "wp_enqueue_script( 'wpcpm-forms' );" ),
        false !== strpos( $past, "\n\t\twp_enqueue_script( 'wpcpm-forms' );" ),
    ),
    array( true, 1, true, true, true ) );
ck( 'and the handle it names is registered on init by the call calendar, which the Mentors module boots',
    array(
        (bool) preg_match( "/public static function init\(\) \{\s*add_action\( 'init', array\( __CLASS__, 'register_assets' \) \);/", $module ),
        false !== strpos( method_code( (string) file_get_contents( $root . '/includes/modules/class-wpcpm-mentors.php' ), 'public function boot()' ), 'WPCPM_Call_Calendar::init();' ),
    ),
    array( true, true ) );

// The wp-admin forms whose second press does harm and that carried no guard: the sample
// invitations on Settings > Mail, one form drawn for each audience, where a second press mails
// the presser again, and the Track Builder's Create the track and Make the copy, where two presses
// that overlap can make two tracks. Each method draws one form, and it carries the attribute and
// its busy word, translated and escaped for the attribute, with a `<button>` to show the word on:
// the guard swaps a button's text, and an input's label is its value, which it leaves alone.
$settings_src = (string) file_get_contents( $root . '/includes/class-wpcpm-settings-screen.php' );
$builder_src  = (string) file_get_contents( $root . '/includes/tools/class-wpcpm-track-builder-screen.php' );
$mail_card    = method_code( $settings_src, 'private function render_mail_card()' );
$marked       = array();

foreach ( array(
	'Settings > Mail'  => array( $mail_card, 'Sending' ),
	'Create the track' => array( method_code( $builder_src, 'public static function render_new( array $args )' ), 'Creating' ),
	'Make the copy'    => array( method_code( $builder_src, 'public static function render_duplicate( array $args )' ), 'Copying' ),
) as $form => $drawn ) {
	$marked[ $form ] = array(
		substr_count( $drawn[0], '<form' ),
		(bool) preg_match( '/<form [^>]*data-wpcpm-once data-wpcpm-busy="%\d\$s"/', $drawn[0] ),
		substr_count( $drawn[0], "esc_attr__( '" . $drawn[1] . "', 'wpcredits-program-manager' )" ),
		substr_count( $drawn[0], '<button type="submit"' ),
	);
}

ck( 'the Settings > Mail sample forms, Create the track and Make the copy each carry data-wpcpm-once and their busy word, Sending, Creating and Copying, on a button',
    $marked,
    array(
        'Settings > Mail'  => array( 1, true, 1, 1 ),
        'Create the track' => array( 1, true, 1, 1 ),
        'Make the copy'    => array( 1, true, 1, 1 ),
    ) );
ck( 'and the Mail tab draws its one form once for each of the four audiences',
    (bool) preg_match( '/foreach \( array\(\s*\'student\'\s+=>.*?\'mentor\'\s+=>.*?\'institution\'\s+=>.*?\'sponsor\'\s+=>.*?\) as \$kind => \$label \) \{\s*printf\(\s*\'<form /s', $mail_card ) );

/*
 * Every confirm is a `data-wpcpm-confirm` sentence forms.js asks (1.122.6), so nothing under
 * includes/ prints a value into an inline handler; the icon's constant onerror is the only one left.
 */
$handlers = array();

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes' ) ) as $source ) {
	if ( 'php' === $source->getExtension() && preg_match_all( '/\bon[a-z]+=["\'][^"\']*["\']/i', (string) file_get_contents( $source->getPathname() ), $found ) ) {
		$handlers = array_merge( $handlers, array_map( 'trim', $found[0] ) );
	}
}

ck( 'no file under includes/ prints a value into an inline event handler; the icon\'s constant onerror is the only one left',
    array_values( array_unique( $handlers ) ),
    array( 'onerror="this.remove()"' ) );
ck( 'the reader takes the sentence off the pressed control, else off the form itself, and binds the form a marked control submits, which `form="<id>"` can place outside it',
    array(
        (bool) strpos( $confirm, "form.getAttribute( 'data-wpcpm-confirm' )" ),
        (bool) preg_match( '/function confirmFirst\(\) \{.*?\'\[data-wpcpm-confirm\]\'.*?marked\[ i \]\.form/s', $js ),
    ),
    array( true, true ) );
/*
 * Without `event.submitter` the reader asks the control the person pressed, as the guard tracks it,
 * before the first marked control in the form: the offer form marks End alone among its buttons.
 */
ck( 'and without event.submitter it asks the last control pressed, tracked as the guard tracks it, before the one marked',
    array(
        (bool) strpos( $confirm, "event.submitter || lastPressed() || form.querySelector( '[data-wpcpm-confirm]' )" ),
        substr_count( $js, '= watchPressed( form );' ),
        (bool) strpos( $js, "if ( form.wpcpmPressed ) {" ),
    ),
    array( true, 2, true ) );
ck( 'the institution and sponsor application decisions carry their sentence as data-wpcpm-confirm, escaped with esc_attr()',
    array(
        substr_count( (string) file_get_contents( $root . '/includes/modules/class-wpcpm-institutions.php' ), '\' data-wpcpm-confirm="\' . esc_attr( $args[\'confirm\'] ) . \'"\'' ),
        substr_count( (string) file_get_contents( $root . '/includes/modules/class-wpcpm-sponsor-application.php' ), '\' data-wpcpm-confirm="\' . esc_attr( $args[\'confirm\'] ) . \'"\'' ),
    ),
    array( 1, 1 ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
