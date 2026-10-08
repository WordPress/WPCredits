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
 *
 * The last block ties every confirm to a script that asks it: each file under includes/ that prints
 * a `data-wpcpm-confirm` mark has a row naming the pages that draw it, and each page is checked
 * for the line that loads `wpcpm-forms`.
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
ck( 'forms.js exists and defines the guard, as a handler of the submit event and not as something bound to each form',
    array(
        (bool) strpos( $js, 'function guardSubmit( event )' ),
        false !== strpos( $js, 'function guardForms()' ),
        false !== strpos( $js, 'function guardForm(' ),
        false !== strpos( $js, "querySelectorAll( 'form[data-wpcpm-once]' )" ),
    ),
    array( true, false, false, false ) );
ck( 'and wires it, with the confirm reader in the one submit listener, after the press tracker and with the bfcache release, the code-selector, the kind switch and the required-with switch, once the DOM is ready',
    (bool) preg_match( '/ready\( function \(\) \{\s*watchPressed\(\);\s*watchSubmits\(\);\s*releaseOnRestore\(\);\s*selectOnClick\(\);\s*showForKind\(\);\s*requireWith\(\);\s*\} \);/', $js ) );

// The deep check of 1.109.1, SESSIONS-6: "Cancel the session" and "Leave the session" carried a
// `data-wpcpm-confirm` sentence that no script read, so one press canceled a session for everybody.
// The reader and the guard are two steps of one capture-phase listener on the document, the reader
// first, so it runs before every listener on a form whichever was added first, and the order is in the
// listener and not in which call came first: a No has called preventDefault() by the time the guard
// runs, and the guard, which stands aside for a prevented submit, leaves the form as it was. With the
// guard first it would lock a form the reader then never asks.
$confirm = substr( $js, (int) strpos( $js, 'function askConfirm( event )' ) );
$confirm = substr( $confirm, 0, (int) strpos( $confirm, "\n\t}\n" ) );
$first   = substr( $js, (int) strpos( $js, 'function watchSubmits()' ) );
$first   = substr( $first, 0, (int) strpos( $first, "\n\t}\n" ) );
$guard   = substr( $js, (int) strpos( $js, 'function guardSubmit( event )' ) );
$guard   = substr( $guard, 0, (int) strpos( $guard, "\n\t}\n" ) );
ck( 'a submit is asked the pressed control\'s data-wpcpm-confirm sentence with window.confirm(), and a No prevents it',
    array(
        (bool) strpos( $js, 'function watchSubmits()' ),
        (bool) preg_match( "/document\.addEventListener\( 'submit', function \( event \) \{\s*askConfirm\( event \);/", $first ),
        (bool) strpos( $confirm, "getAttribute( 'data-wpcpm-confirm' )" ),
        (bool) preg_match( '/if \( question && ! window\.confirm\( question \) \) \{\s*event\.preventDefault\(\);/', $confirm ),
    ),
    array( true, true, true, true ) );
ck( 'the one submit listener is on the document, in the capture phase, and runs the reader and then the guard: no other listener of the script takes a submit',
    array(
        (bool) preg_match( "/^document\.addEventListener\( 'submit', function \( event \) \{\s*askConfirm\( event \);\s*guardSubmit\( event \);\s*\}, true \);$/m", preg_replace( '/^\s+/m', '', $first ) ),
        substr_count( $js, "addEventListener( 'submit'" ),
        substr_count( $js, 'askConfirm( event );' ),
        substr_count( $js, 'guardSubmit( event );' ),
    ),
    array( true, 1, 1, 1 ) );
ck( 'the guard reads the form off the event and acts only on a form carrying data-wpcpm-once, whenever it was added',
    array(
        (bool) strpos( $guard, 'var form = event.target;' ),
        (bool) strpos( $guard, "'FORM' !== form.tagName" ),
        (bool) strpos( $guard, "null === form.getAttribute( 'data-wpcpm-once' )" ),
    ),
    array( true, true, true ) );
ck( 'and the guard stands aside for a submit already prevented, before it sets anything',
    (bool) preg_match( "/data-wpcpm-once' \) \) \{\s*return;\s*\}.*?if \( event\.defaultPrevented \) \{\s*return;\s*\}.*?setAttribute\( 'data-wpcpm-sent'/s", $guard ) );
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
ck( 'calendar.js no longer defines the guard', false === strpos( $calendar, 'function guardForm' ) && false === strpos( $calendar, 'function guardSubmit' ) );
ck( 'nor calls it',
    false === strpos( $calendar, 'guardForms()' ) && false === strpos( $calendar, 'guardSubmit(' ) && false === strpos( $calendar, 'releaseOnRestore()' ) );

// The submit handler body.
$handler = $guard;

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
ck( 'the reader takes the sentence off the pressed control, else off the form being submitted, and binds no form: the one listener reads the form off the event, so a form inserted after load is asked',
    array(
        (bool) strpos( $confirm, "form.getAttribute( 'data-wpcpm-confirm' )" ),
        (bool) strpos( $confirm, 'var form = event.target;' ),
        false !== strpos( $first, 'querySelector' ),
        false !== strpos( $js, 'function bindConfirm(' ),
    ),
    array( true, true, false, false ) );
ck( 'and it leaves a form the guard has already sent alone, so a repeat press is swallowed without a second question',
    (bool) preg_match( "/var form = event\.target;.*?if \( form\.getAttribute\( 'data-wpcpm-sent' \) \) \{\s*return;\s*\}.*?var control = /s", $confirm ) );
/*
 * Without `event.submitter` the reader asks the control the person pressed, as the guard tracks it,
 * before the first marked submit control in the form: the offer form marks End alone among its buttons.
 * The tracker records submit controls only, in a WeakMap keyed by the form and not in a property of
 * it, from two capture-phase listeners on the document.
 */
$submit_control = substr( $js, (int) strpos( $js, 'function isSubmitControl( control )' ) );
$submit_control = substr( $submit_control, 0, (int) strpos( $submit_control, "\n\t}\n" ) );
$watch          = substr( $js, (int) strpos( $js, 'function watchPressed()' ) );
$watch          = substr( $watch, 0, (int) strpos( $watch, "\n\t}\n" ) );
ck( 'and without event.submitter it asks the last control pressed, tracked as the guard tracks it, before the first marked submit control',
    array(
        (bool) strpos( $confirm, 'event.submitter || lastPressed( form ) || firstMarkedSubmit( form )' ),
        substr_count( $js, 'event.submitter || lastPressed( form )' ),
        (bool) strpos( $js, 'var pressedIn = new WeakMap();' ),
        false !== strpos( $js, 'wpcpmPressed' ),
    ),
    array( true, 2, true, false ) );
ck( 'the tracker records a button that is not type=button or reset and an input of type submit or image, and nothing else, from the document in the capture phase',
    array(
        (bool) strpos( $submit_control, "'button' !== control.type && 'reset' !== control.type" ),
        (bool) strpos( $submit_control, "'INPUT' === control.tagName && ( 'submit' === control.type || 'image' === control.type )" ),
        substr_count( $watch, 'isSubmitControl(' ),
        (bool) strpos( $watch, "document.addEventListener( 'mousedown'" ),
        (bool) strpos( $watch, "document.addEventListener( 'keydown'" ),
        substr_count( $watch, '}, true );' ),
    ),
    array( true, true, 2, true, true, 2 ) );
ck( 'the institution and sponsor application decisions carry their sentence as data-wpcpm-confirm, escaped with esc_attr()',
    array(
        substr_count( (string) file_get_contents( $root . '/includes/modules/class-wpcpm-institutions.php' ), '\' data-wpcpm-confirm="\' . esc_attr( $args[\'confirm\'] ) . \'"\'' ),
        substr_count( (string) file_get_contents( $root . '/includes/modules/class-wpcpm-sponsor-application.php' ), '\' data-wpcpm-confirm="\' . esc_attr( $args[\'confirm\'] ) . \'"\'' ),
    ),
    array( 1, 1 ) );

/**
 * Whether PHP source prints the `data-wpcpm-confirm` attribute: the text is in a string or in markup
 * the file closes PHP for, and not only in a comment that names it.
 *
 * @param string $src A PHP file.
 * @return bool
 */
function prints_confirm_mark( $src ) {
	foreach ( token_get_all( $src ) as $token ) {
		if ( is_array( $token ) && in_array( $token[0], array( T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML ), true ) && false !== strpos( $token[1], 'data-wpcpm-confirm' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Where the first `return` of a method's code begins, or its length when it has none.
 *
 * @param string $code A method's code, from `method_code()`.
 * @return int Byte offset.
 */
function first_return_at( $code ) {
	$at = 0;

	foreach ( token_get_all( '<?php ' . $code ) as $token ) {
		if ( is_array( $token ) && T_RETURN === $token[0] ) {
			return max( 0, $at - strlen( '<?php ' ) );
		}

		$at += strlen( is_array( $token ) ? $token[1] : $token );
	}

	return strlen( $code );
}

/**
 * Where a statement begins in a method's code, when it is one of the method body's own statements:
 * the body is brace depth 1, and a statement starts right after a `;`, a `{` or a `}`. An enqueue
 * inside an `if`, a loop, an `else` or a closure is deeper, and one that follows `)` or `else` with
 * no braces is not after a statement end, so none of them is found.
 *
 * @param string $code   A method's code, from `method_code()`.
 * @param string $needle The statement, as written; it must start at a token.
 * @return int|false Byte offset of the first such statement, or false.
 */
function body_statement_at( $code, $needle ) {
	$depth = 0;
	$at    = 0;
	$prev  = '';
	$start = array();

	foreach ( token_get_all( '<?php ' . $code ) as $token ) {
		$text = is_array( $token ) ? $token[1] : $token;
		$id   = is_array( $token ) ? $token[0] : $token;

		if ( T_WHITESPACE !== $id && T_COMMENT !== $id && T_DOC_COMMENT !== $id ) {
			if ( 1 === $depth && in_array( $prev, array( ';', '{', '}' ), true ) ) {
				$start[ $at - strlen( '<?php ' ) ] = true;
			}

			if ( '{' === $text || T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id ) {
				++$depth;
			} elseif ( '}' === $text ) {
				--$depth;
			}

			$prev = $text;
		}

		$at += strlen( $text );
	}

	for ( $from = 0; false !== ( $found = strpos( $code, $needle, $from ) ); $from = $found + 1 ) {
		if ( isset( $start[ $found ] ) ) {
			return $found;
		}
	}

	return false;
}

/**
 * The classes a PHP file declares.
 *
 * @param string $src A PHP file.
 * @return string[]
 */
function declared_classes( $src ) {
	preg_match_all( '/^(?:final\s+|abstract\s+)?class (\w+)/m', $src, $named );

	return $named[1];
}

/**
 * Whether PHP source draws a class: calls one of its `render` methods, or names it as a string or
 * with `::class`, which is how a dashboard's card list and `call_user_func()` reach it. A comment
 * that names it does not count, and neither does `class_exists( 'Name' )`, which only asks whether
 * the class is loaded.
 *
 * @param string $src   A PHP file.
 * @param string $class The class's name.
 * @return bool
 */
function draws_class( $src, $class ) {
	$tokens = array();

	foreach ( token_get_all( $src ) as $token ) {
		if ( ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			$tokens[] = $token;
		}
	}

	$id = static function ( $at ) use ( $tokens ) {
		return isset( $tokens[ $at ] ) ? ( is_array( $tokens[ $at ] ) ? $tokens[ $at ][0] : $tokens[ $at ] ) : null;
	};
	$tx = static function ( $at ) use ( $tokens ) {
		return isset( $tokens[ $at ] ) ? ( is_array( $tokens[ $at ] ) ? $tokens[ $at ][1] : $tokens[ $at ] ) : '';
	};

	foreach ( array_keys( $tokens ) as $i ) {
		if ( T_CONSTANT_ENCAPSED_STRING === $id( $i ) && trim( $tx( $i ), '\'"' ) === $class ) {
			if ( ! ( '(' === $tx( $i - 1 ) && 'class_exists' === $tx( $i - 2 ) ) ) {
				return true;
			}
		}

		if ( T_STRING === $id( $i ) && $class === $tx( $i ) && T_DOUBLE_COLON === $id( $i + 1 ) ) {
			if ( T_CLASS === $id( $i + 2 ) || ( T_STRING === $id( $i + 2 ) && 0 === strpos( $tx( $i + 2 ), 'render' ) ) ) {
				return true;
			}
		}
	}

	return false;
}

/*
 * Every confirm is a sentence forms.js asks, so a page that prints a `data-wpcpm-confirm` mark and
 * does not load the script posts that form on one press, with no question and nothing to show that
 * anything is missing. $loaders says what puts `wpcpm-forms` on a kind of page, read off the source;
 * $surfaces says, for each file under includes/ that prints the attribute, which of those pages draw it.
 * A file that prints the attribute and has no row fails the first check below, so a new screen has to
 * say where its script comes from. A file that gains a page is a name added to its row, and a page
 * that loads the script some other way is one more entry in $loaders.
 *
 * A loader's proofs are each: the file, the method's signature, the text that method must contain, and
 * where it must stand: 'before the first return' is a statement of the method body itself (not inside an
 * `if`, a loop, an `else` or a closure) that comes ahead of the first `return` in the source, 'in the
 * body' is a statement of the body itself wherever it stands, and 'anywhere' is the text in the method.
 * A front-end page puts the script ahead of its early returns, so a visitor who is logged out or has
 * nothing to see still gets it. The check reads the source and does not run the method, so a `return`
 * inside a closure above the statement fails it as well. wp-admin's one enqueue sits past the guard that
 * keeps it to the plugin's screens, which the checks above pin line by line.
 */
$loaders = array(
	'admin'                   => array(
		'every wp-admin screen of the plugin',
		array( array( 'includes/class-wpcpm-admin.php', 'public function enqueue_assets( $hook_suffix )', "wp_enqueue_script( 'wpcpm-forms' );", 'in the body' ) ),
	),
	'administrator-dashboard' => array(
		'the Administrator Dashboard',
		array( array( 'includes/modules/class-wpcpm-administrators-dashboard.php', 'public static function render( $attributes = array() )', "wp_enqueue_script( 'wpcpm-forms' );", 'before the first return' ) ),
	),
	'institution-dashboard'   => array(
		'the Institution Dashboard',
		array( array( 'includes/modules/class-wpcpm-institutions-dashboard.php', 'public static function render( $atts = array() )', "wp_enqueue_script( 'wpcpm-forms' );", 'before the first return' ) ),
	),
	'sponsor-dashboard'       => array(
		'the Sponsor Dashboard',
		array(
			array( 'includes/modules/class-wpcpm-sponsors-dashboard.php', 'private static function enqueue_forms()', "wp_enqueue_script( 'wpcpm-forms' );", 'in the body' ),
			array( 'includes/modules/class-wpcpm-sponsors-dashboard.php', 'public static function render( $attributes = array() )', 'self::enqueue_forms();', 'before the first return' ),
		),
	),
	'mentor-report-card'      => array(
		'the Mentor Report Card',
		array( array( 'includes/modules/class-wpcpm-mentors-dashboard.php', 'public static function render( $atts = array() )', "wp_enqueue_script( 'wpcpm-forms' );", 'before the first return' ) ),
	),
	'student-report-card'     => array(
		'the Student Report Card',
		array( array( 'includes/modules/class-wpcpm-students-dashboard.php', 'public static function render( $atts = array() )', "wp_enqueue_script( 'wpcpm-forms' );", 'before the first return' ) ),
	),
	'calendar'                => array(
		'the pages that draw the call calendar, the Student Report Card and the Mentor Report Card, whose calendar script names it as a dependency',
		array(
			array( 'includes/modules/class-wpcpm-call-calendar.php', 'public static function render_student( WP_User $student, $can_manage )', 'wp_enqueue_script( self::SCRIPT );', 'before the first return' ),
			array( 'includes/modules/class-wpcpm-call-calendar.php', 'public static function render_mentor( WP_User $mentor )', 'wp_enqueue_script( self::SCRIPT );', 'before the first return' ),
			array( 'includes/modules/class-wpcpm-call-calendar.php', 'public static function register_assets()', "array( 'wpcpm-forms' ),", 'anywhere' ),
		),
	),
);

// File => the pages that draw it, by loader. An empty list says no page draws the file today.
$surfaces = array(
	'includes/class-wpcpm-mail.php'                                      => array( 'admin' ),
	'includes/modules/class-wpcpm-call-calendar.php'                     => array( 'calendar' ),
	'includes/modules/class-wpcpm-group-sessions.php'                    => array( 'calendar' ),
	'includes/modules/class-wpcpm-institution-import-form.php'           => array( 'institution-dashboard' ),
	'includes/modules/class-wpcpm-institution-invite.php'                => array( 'institution-dashboard' ),
	'includes/modules/class-wpcpm-institution-notes.php'                 => array(),
	'includes/modules/class-wpcpm-institution-panel.php'                 => array( 'admin', 'administrator-dashboard', 'institution-dashboard' ),
	'includes/modules/class-wpcpm-institution-people.php'                => array( 'admin', 'institution-dashboard' ),
	'includes/modules/class-wpcpm-institution-students.php'              => array( 'institution-dashboard' ),
	'includes/modules/class-wpcpm-institutions.php'                      => array( 'admin', 'administrator-dashboard' ),
	'includes/modules/class-wpcpm-mentor-notes.php'                      => array( 'mentor-report-card' ),
	'includes/modules/class-wpcpm-sponsor-agreement-card.php'            => array( 'sponsor-dashboard' ),
	'includes/modules/class-wpcpm-sponsor-agreement.php'                 => array( 'administrator-dashboard' ),
	'includes/modules/class-wpcpm-sponsor-application.php'               => array( 'admin', 'administrator-dashboard' ),
	'includes/modules/class-wpcpm-sponsor-logo.php'                      => array( 'sponsor-dashboard' ),
	'includes/modules/class-wpcpm-sponsor-offers.php'                    => array( 'sponsor-dashboard' ),
	'includes/modules/class-wpcpm-sponsors.php'                          => array( 'admin' ),
	// The Remove question is drawn for an editor, so on the Student Report Card only: the report route's
	// fragment, which the Mentor Report Card and the Institution's student page insert after they load, is
	// read only and prints none (bin/test-report-images.php draws it).
	'includes/modules/class-wpcpm-student-report-form.php'               => array( 'student-report-card' ),
	'includes/tools/class-wpcpm-track-builder-screen.php'                => array( 'admin' ),
	'includes/tools/class-wpcpm-track-editor-screen.php'                 => array( 'admin' ),
);

$printers = array();

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS ) ) as $source ) {
	if ( 'php' === $source->getExtension() && prints_confirm_mark( (string) file_get_contents( $source->getPathname() ) ) ) {
		$printers[] = substr( $source->getPathname(), strlen( $root ) + 1 );
	}
}

sort( $printers );

ck( 'every file under includes/ that prints a data-wpcpm-confirm mark has a row in the surface table, so a new screen has to say where its script comes from',
    array_values( array_diff( $printers, array_keys( $surfaces ) ) ), array() );
ck( 'and every row names a file that still prints one, so a row does not outlive its file',
    array_values( array_diff( array_keys( $surfaces ), $printers ) ), array() );
ck( 'every page a row names is a loader the table defines',
    array_values( array_unique( array_diff( array_merge( array(), ...array_values( $surfaces ) ), array_keys( $loaders ) ) ) ), array() );

// The readers the loader and empty-row checks lean on, run on samples, so that a change which loosens
// one turns a line red here and does not pass quietly. A statement counts as the method body's own only
// when no `if`, `else`, loop or closure sits around it; a name counts as drawing a class when it is
// called for rendering or handed on as a string, and `class_exists()` only asks whether it is loaded.
$body     = array(
	'a statement of the body'                                  => array( "public function f() {\n\t\twp_enqueue_script( 'x' );\n\t\treturn 1;\n\t}", true ),
	'after a block that closed'                                => array( "public function f() {\n\t\tif ( \$a ) {\n\t\t\tfoo();\n\t\t}\n\t\twp_enqueue_script( 'x' );\n\t}", true ),
	'inside an if on who is logged in'                         => array( "public function f() {\n\t\tif ( is_user_logged_in() ) {\n\t\t\twp_enqueue_script( 'x' );\n\t\t}\n\t}", false ),
	'inside a closure nothing calls'                           => array( "public function f() {\n\t\t\$never = function () {\n\t\t\twp_enqueue_script( 'x' );\n\t\t};\n\t}", false ),
	'in an else'                                               => array( "public function f() {\n\t\tif ( \$a ) {\n\t\t\tfoo();\n\t\t} else {\n\t\t\twp_enqueue_script( 'x' );\n\t\t}\n\t}", false ),
	'after an if with no braces'                               => array( "public function f() {\n\t\tif ( \$a ) wp_enqueue_script( 'x' );\n\t}", false ),
	'inside a loop'                                            => array( "public function f() {\n\t\tforeach ( \$a as \$b ) {\n\t\t\twp_enqueue_script( 'x' );\n\t\t}\n\t}", false ),
	'as the end of a longer statement'                         => array( "public function f() {\n\t\t\$ok = \$a && wp_enqueue_script( 'x' );\n\t}", false ),
	'inside a string'                                          => array( "public function f() {\n\t\t\$s = \"wp_enqueue_script( 'x' );\";\n\t}", false ),
	'in a comment'                                             => array( "public function f() {\n\t\t// wp_enqueue_script( 'x' );\n\t\treturn 1;\n\t}", false ),
);
$read     = array();
$expected = array();

foreach ( $body as $label => $sample ) {
	$read[ $label ]     = false !== body_statement_at( $sample[0], "wp_enqueue_script( 'x' );" );
	$expected[ $label ] = $sample[1];
}

ck( 'the loader proofs count an enqueue only as a statement of the method body: not in an if, an else, a loop or a closure, not after an if with no braces, not in a string or a comment',
    $read, $expected );

$draws = array(
	'a call to a render method'                                => array( "<?php\nWPCPM_Zz::render( 1 );", true ),
	'a card named as a string'                                 => array( "<?php\nself::card( 'WPCPM_Zz', array() );", true ),
	'a name in a list a loop reads'                            => array( "<?php\nforeach ( array( 'WPCPM_Zz' ) as \$c ) { self::card( \$c, array() ); }", true ),
	'a name in double quotes'                                  => array( "<?php\n\$cards = array( \"WPCPM_Zz\" );", true ),
	'a name handed to call_user_func()'                        => array( "<?php\ncall_user_func( array( 'WPCPM_Zz', 'render' ), 1 );", true ),
	'a name given with ::class'                                => array( "<?php\n\$cards = array( WPCPM_Zz::class );", true ),
	'class_exists(), which only asks whether it is loaded'     => array( "<?php\nreturn class_exists( 'WPCPM_Zz' ) && WPCPM_Zz::user_can_read( \$n );", false ),
	'a comment alone'                                          => array( "<?php\n// WPCPM_Zz::render() would draw it\n\$x = 1;", false ),
	'another class'                                            => array( "<?php\nWPCPM_Yy::render( 1 );\nself::card( 'WPCPM_Yy', array() );", false ),
);
$read     = array();
$expected = array();

foreach ( $draws as $label => $sample ) {
	$read[ $label ]     = draws_class( $sample[0], 'WPCPM_Zz' );
	$expected[ $label ] = $sample[1];
}

ck( 'the empty-row check finds a class drawn by a render call, a name in a string or a list, call_user_func() or ::class, and not by a comment or class_exists()',
    $read, $expected );
ck( 'and it reads the class a file declares whether it is plain, final or abstract',
    array_merge( declared_classes( "<?php\nclass A {}\n" ), declared_classes( "<?php\nfinal class B {}\n" ), declared_classes( "<?php\nabstract class C {}\n" ) ),
    array( 'A', 'B', 'C' ) );

foreach ( $loaders as $loader ) {
	$failed = array();

	foreach ( $loader[1] as $proof ) {
		list( $file, $signature, $needle, $where ) = $proof;

		$code = method_code( (string) file_get_contents( $root . '/' . $file ), $signature );
		$at   = 'anywhere' === $where ? strpos( $code, $needle ) : body_statement_at( $code, $needle );
		$held = '' !== $code && false !== $at && ( 'before the first return' !== $where || $at < first_return_at( $code ) );

		if ( ! $held ) {
			$failed[] = $file . ' ' . $signature . ': ' . $needle . ' (' . $where . ')';
		}
	}

	ck( 'wpcpm-forms is put on ' . $loader[0], $failed, array() );
}

// A file with an empty row is one no page draws. It stays true only while nothing renders it, so the
// day a screen does, the row has to name the screen's loader. The ways this plugin draws a file: a call
// to one of the class's render methods, and a name given as a string or with ::class, which is how a
// dashboard's card list and call_user_func() reach it.
foreach ( array_keys( array_filter( $surfaces, static function ( $pages ) { return array() === $pages; } ) ) as $file ) {
	$classes  = declared_classes( (string) file_get_contents( $root . '/' . $file ) );
	$drawn_by = array();

	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS ) ) as $other ) {
		$path = substr( $other->getPathname(), strlen( $root ) + 1 );

		if ( 'php' !== $other->getExtension() || $path === $file ) {
			continue;
		}

		foreach ( $classes as $class ) {
			if ( draws_class( (string) file_get_contents( $other->getPathname() ), $class ) ) {
				$drawn_by[] = $path;
			}
		}
	}

	ck( $file . ' declares a class the check can look for', array() !== $classes );
	ck( $file . ' has no page, and no other file renders it, so its empty row is true', $drawn_by, array() );
}

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
