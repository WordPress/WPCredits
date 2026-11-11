<?php
/**
 * A signed-in person's pages in the language their own account keeps (1.123.73).
 *
 * `WPCPM_Language` answers WordPress's question of which language a request is in with the reader's
 * own, and prints the small switcher they choose it on. These checks pin when it answers and when it stays
 * out of the way (wp-admin, the scheduled jobs, a visitor, a language with no catalog, a question
 * asked before the plugins have loaded), what the switcher lists and to whom, and that the handler
 * keeps only a language the switcher offered.
 *
 * Nothing here loads WordPress: the functions the class calls are stand-ins that read `$GLOBALS`.
 *
 * Run from the plugin root:  php bin/test-language.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', __DIR__ . '/fixtures/no-such-folder/' );

$GLOBALS['w'] = array();

function reset_world() {
	$GLOBALS['w'] = array(
		'loaded'    => true,
		'admin'     => false,
		'cron'      => false,
		'user'      => 7,
		'manage'    => false,
		'meta'      => array(),
		'posted'    => null,
		'referer'   => 'http://example.test/student-dashboard/?a=1#old',
		'redirect'  => null,
		'nonce_ok'  => true,
		'filters'   => array(),
	);
	$_POST = array();
}

class WPCPM_Test_Stop extends Exception {}

function add_filter( $hook, $callback ) { $GLOBALS['w']['filters'][] = $hook; }
function add_action( $hook, $callback ) { $GLOBALS['w']['filters'][] = $hook; }
function did_action( $hook ) { return $GLOBALS['w']['loaded'] ? 1 : 0; }
function is_admin() { return $GLOBALS['w']['admin']; }
function wp_doing_cron() { return $GLOBALS['w']['cron']; }
function is_user_logged_in() { return $GLOBALS['w']['user'] > 0; }
function get_current_user_id() { return $GLOBALS['w']['user']; }
function current_user_can( $cap ) { return $GLOBALS['w']['manage']; }
function get_locale() { return 'en_US'; }
function get_user_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['w']['meta'][ $id ][ $key ] ) ? $GLOBALS['w']['meta'][ $id ][ $key ] : '';
}
function update_user_meta( $id, $key, $value ) { $GLOBALS['w']['meta'][ $id ][ $key ] = $value; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['w']['meta'][ $id ][ $key ] ); return true; }
function check_admin_referer( $action ) {
	if ( ! $GLOBALS['w']['nonce_ok'] ) {
		throw new WPCPM_Test_Stop( 'nonce' );
	}
	return 1;
}
function wp_die( $message = '' ) { throw new WPCPM_Test_Stop( 'died' ); }
function wp_safe_redirect( $url ) {
	$GLOBALS['w']['redirect'] = $url;
	throw new WPCPM_Test_Stop( 'redirect' );
}
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function admin_url( $path = '' ) { return 'http://example.test/wp-admin/' . $path; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="n">'; }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected' : ''; }
function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $text ) { return (string) $text; }
function wp_kses( $text, $allowed ) { return (string) $text; }

class WP_User {
	public $ID;
	public function __construct( $id ) { $this->ID = (int) $id; }
	public function exists() { return $this->ID > 0; }
}
class WPCPM_Roles {
	const CAP_MANAGE = 'manage';
	public static function resolve_user( $user = null ) { return $user instanceof WP_User ? $user : new WP_User( (int) $user ); }
}
class WPCPM_Request {
	public static function posted_exact( $name, $fallback = '' ) { return isset( $_POST[ $name ] ) ? (string) $_POST[ $name ] : $fallback; }
	public static function back( ...$fallbacks ) { return $GLOBALS['w']['referer'] ? $GLOBALS['w']['referer'] : $fallbacks[0]; }
}

require dirname( __DIR__ ) . '/includes/class-wpcpm-language.php';

/** The catalogs the class finds, set without a folder of files. */
function catalogs( array $locales ) {
	$property = new ReflectionProperty( 'WPCPM_Language', 'catalogs' );
	if ( PHP_VERSION_ID < 80100 ) {
		$property->setAccessible( true );
	}
	$property->setValue( null, $locales );
}

function picker_of( $user_id ) {
	ob_start();
	WPCPM_Language::picker( $user_id );
	return (string) ob_get_clean();
}

function handle_with( $value ) {
	$_POST = null === $value ? array() : array( WPCPM_Language::FIELD => $value );
	try {
		WPCPM_Language::handle();
	} catch ( WPCPM_Test_Stop $stop ) {
		return $stop->getMessage();
	}
	return 'returned';
}

$fail = 0;
function ck( $label, $got, $want ) {
	global $fail;
	if ( $got === $want ) {
		echo "  ok    $label\n";
		return;
	}
	++$fail;
	echo "  FAIL  $label\n        got:  " . var_export( $got, true ) . "\n        want: " . var_export( $want, true ) . "\n";
}

echo "=== The catalogs ===\n";

reset_world();
ck( 'a folder with no catalog offers nothing', WPCPM_Language::catalogs(), array() );
ck(
	'the catalogs name the languages, each once, sorted, English and other files apart',
	WPCPM_Language::locales_of( array( '/x/wpcredits-program-manager-pt_BR.mo', 'wpcredits-program-manager-pl_PL.mo', 'wpcredits-program-manager-pl_PL.mo', 'wpcredits-program-manager-en_US.mo', 'wpcredits-program-manager.pot', 'wpcredits-theme-de_DE.mo', 'wpcredits-program-manager-de_DE.po' ) ),
	array( 'pl_PL', 'pt_BR' )
);
ck( 'the language of a locale', WPCPM_Language::language_of( 'pt_BR' ), 'pt' );

echo "\n=== Whose language a request is in ===\n";

catalogs( array( 'pl_PL', 'pt_BR' ) );
reset_world();
WPCPM_Language::init();
ck( 'the class answers WordPress\'s own question, and handles its form', $GLOBALS['w']['filters'], array( 'pre_determine_locale', 'admin_post_' . WPCPM_Language::ACTION ) );

$GLOBALS['w']['meta'][7]['locale'] = 'pl_PL';
ck( 'a signed-in reader\'s own language, on the site\'s pages', WPCPM_Language::follow( null ), 'pl_PL' );
ck( 'what an earlier filter decided stands', WPCPM_Language::follow( 'de_DE' ), 'de_DE' );

$GLOBALS['w']['admin'] = true;
ck( 'wp-admin is left to WordPress', WPCPM_Language::follow( null ), null );
$GLOBALS['w']['admin'] = false;
$GLOBALS['w']['cron']  = true;
ck( 'so are the scheduled jobs', WPCPM_Language::follow( null ), null );
$GLOBALS['w']['cron']   = false;
$GLOBALS['w']['loaded'] = false;
ck( 'a question asked before the plugins loaded is not answered', WPCPM_Language::follow( null ), null );
$GLOBALS['w']['loaded'] = true;
$GLOBALS['w']['user']   = 0;
ck( 'a visitor reads the site\'s language', WPCPM_Language::follow( null ), null );
$GLOBALS['w']['user'] = 7;

$GLOBALS['w']['meta'][7]['locale'] = 'ja';
ck( 'a language with no catalog is not followed', WPCPM_Language::follow( null ), null );
ck( 'and is not the reader\'s choice any more', WPCPM_Language::chosen( 7 ), '' );
unset( $GLOBALS['w']['meta'][7]['locale'] );
ck( 'an account with no language reads the site\'s', WPCPM_Language::follow( null ), null );

echo "\n=== What the form lists ===\n";

$choices = WPCPM_Language::choices( 'en_US', array( 'pl_PL', 'pt_BR', 'pt_PT', 'en_US' ) );
ck( 'the site\'s language first, as the empty value, then each catalog once', array_keys( $choices ), array( '', 'pl_PL', 'pt_BR', 'pt_PT' ) );
ck( 'each is marked as its own language for a screen reader', array_column( $choices, 'lang' ), array( 'en-US', 'pl-PL', 'pt-BR', 'pt-PT' ) );
ck( 'two of one language are told apart', $choices['pt_BR']['name'] !== $choices['pt_PT']['name'], true );
ck( 'a variant is marked as its language', WPCPM_Language::choices( 'en_US', array( 'de_DE_formal' ) )['de_DE_formal']['lang'], 'de-DE' );
ck( 'a language PHP does not know keeps its code', WPCPM_Language::name( 'qqq_ZZ' ), 'qqq_ZZ' );
ck( 'a name is never empty', '' !== WPCPM_Language::name( 'pl_PL' ), true );

echo "\n=== The switcher ===\n";

ck( 'a country\'s flag is its two letters as regional indicators', WPCPM_Language::flag( 'pl_PL' ), "\u{1F1F5}\u{1F1F1}" );
ck( 'a variant keeps its country\'s', WPCPM_Language::flag( 'de_DE_formal' ), "\u{1F1E9}\u{1F1EA}" );
ck( 'a language of one country has that country\'s', WPCPM_Language::flag( 'ja' ), "\u{1F1EF}\u{1F1F5}" );
ck( 'a language of no one country has none', WPCPM_Language::flag( 'ar' ), '' );
ck( 'every choice carries its flag', WPCPM_Language::choices( 'en_US', array( 'pl_PL' ) )['']['flag'], "\u{1F1FA}\u{1F1F8}" );

reset_world();
$form = picker_of( 7 );
ck( 'a reader is given the switcher, closed', 1 === substr_count( $form, '<details class="wpcpm-language" id="wpcpm-language">' ), true );
ck( 'it shows the language being read, named to a screen reader', 1 === preg_match( '#<summary class="wpcpm-language__current"><span class="screen-reader-text">Language:</span><span class="wpcpm-language__flag" aria-hidden="true">[^<]+</span><span class="wpcpm-language__name" lang="en-US">[^<]+</span></summary>#', $form ), true );
ck( 'a flag is hidden from a screen reader', substr_count( $form, '<span class="wpcpm-language__flag" aria-hidden="true">' ), 4 );
ck( 'its form posts to its own handler, with a nonce', false !== strpos( $form, 'name="action" value="wpcpm_language"' ) && false !== strpos( $form, '_wpnonce' ), true );
ck( 'each language is a button that saves it, the site\'s first', 1 === preg_match( '/<button type="submit" class="wpcpm-language__choice" name="wpcpm_language" value="" lang="en-US" aria-current="true">.*<button type="submit" class="wpcpm-language__choice" name="wpcpm_language" value="pl_PL" lang="pl-PL">.*value="pt_BR" lang="pt-BR">/s', $form ), true );
ck( 'there is no list to open and no second button to press', false === strpos( $form, '<select' ) && 3 === substr_count( $form, '<button' ), true );

$GLOBALS['w']['meta'][7]['locale'] = 'pt_BR';
$form = picker_of( 7 );
ck( 'a reader\'s own choice is the one shown and the one marked', false !== strpos( $form, 'lang="pt-BR">' ) && 1 === substr_count( $form, 'aria-current="true"' ) && false !== strpos( $form, 'value="pt_BR" lang="pt-BR" aria-current="true"' ), true );

$GLOBALS['w']['manage'] = true;
ck( 'an Administrator reading somebody\'s page is given none', picker_of( 7 ), '' );
$GLOBALS['w']['manage'] = false;
ck( 'nor is nobody', picker_of( 0 ), '' );
catalogs( array() );
ck( 'with no catalog there is no switcher', picker_of( 7 ), '' );

echo "\n=== Keeping the choice ===\n";

catalogs( array( 'pl_PL', 'pt_BR' ) );
reset_world();
ck( 'a language the switcher offered is kept', handle_with( 'pl_PL' ), 'redirect' );
ck( 'on the account, where WordPress keeps it', $GLOBALS['w']['meta'][7]['locale'], 'pl_PL' );
ck( 'the reader goes back to the page they came from, at its top', $GLOBALS['w']['redirect'], 'http://example.test/student-dashboard/?a=1' );

ck( 'a language the switcher did not offer goes back too', handle_with( '../../x' ), 'redirect' );
ck( 'and leaves the account as it was', $GLOBALS['w']['meta'][7]['locale'], 'pl_PL' );

handle_with( '' );
ck( 'the site\'s language takes the account\'s away', isset( $GLOBALS['w']['meta'][7]['locale'] ), false );

$GLOBALS['w']['referer'] = '';
handle_with( 'pt_BR' );
ck( 'with no page to go back to, the homepage', $GLOBALS['w']['redirect'], 'http://example.test/' );

$GLOBALS['w']['nonce_ok'] = false;
ck( 'a press without the nonce stops', handle_with( 'pl_PL' ), 'nonce' );
ck( 'and keeps nothing', $GLOBALS['w']['meta'][7]['locale'], 'pt_BR' );
$GLOBALS['w']['nonce_ok'] = true;
$GLOBALS['w']['user']     = 0;
ck( 'nobody signed in is refused', handle_with( 'pl_PL' ), 'died' );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
