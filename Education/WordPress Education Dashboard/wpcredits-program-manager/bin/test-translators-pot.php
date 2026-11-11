<?php
/**
 * The template translators are given, and the download of what they translated (1.123.72).
 *
 * `bin/translators-pot.php` cuts the full template down to the files students and mentors read, and
 * `bin/download-translations.php` brings the finished catalogs back. These checks pin the cut (an
 * entry is kept when any of its references is a listed file, the header survives, a listed file no
 * entry names is reported), that the list still matches the plugin (every listed file exists and
 * the real template has strings from each), and the download's choices: which languages ship, what
 * counts as a catalog, and which old files go.
 *
 * Nothing here touches the network.
 *
 * Run from the plugin root:  php bin/test-translators-pot.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'WPCPM_TRANSLATORS_POT_AS_LIBRARY', true );
define( 'WPCPM_DOWNLOAD_TRANSLATIONS_AS_LIBRARY', true );
require __DIR__ . '/translators-pot.php';
require __DIR__ . '/download-translations.php';

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

echo "=== The cut ===\n";

$pot = "# Copyright\nmsgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: Test 1.0\\n\"\n\"X-Domain: wpcredits-program-manager\\n\"\n\n"
	. "#: includes/a.php:10\nmsgid \"Student line\"\nmsgstr \"\"\n\n"
	. "#: includes/b.php:5\nmsgid \"Administrator line\"\nmsgstr \"\"\n\n"
	. "#. translators: %d: a count.\n#: includes/b.php:9 includes/a.php:44\nmsgid \"%d call\"\nmsgid_plural \"%d calls\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n"
	. "#: includes/b.php:12\n#: includes/c.php:3\nmsgctxt \"button\"\nmsgid \"Save\"\nmsgstr \"\"\n\n"
	. "#: includes/a.php.bak:1\nmsgid \"A look-alike path\"\nmsgstr \"\"\n";

$cut = wpcpm_translators_pot( $pot, array( 'includes/a.php', 'includes/c.php' ) );
ck( 'three of the five entries are kept', $cut['count'], 3 );
ck( 'the header comes first', 0 === strpos( $cut['pot'], "# Copyright\nmsgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: Test 1.0\\n\"" ), true );
ck( 'a listed file\'s entry is kept', false !== strpos( $cut['pot'], 'msgid "Student line"' ), true );
ck( 'an entry of no listed file is dropped', false === strpos( $cut['pot'], 'Administrator line' ), true );
ck( 'an entry shared with a listed file is kept, plural and comment whole', false !== strpos( $cut['pot'], "#. translators: %d: a count.\n#: includes/b.php:9 includes/a.php:44\nmsgid \"%d call\"\nmsgid_plural \"%d calls\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"" ), true );
ck( 'a reference on a second line counts, and the context stays', false !== strpos( $cut['pot'], "msgctxt \"button\"\nmsgid \"Save\"" ), true );
ck( 'a path that only starts like a listed file does not', false === strpos( $cut['pot'], 'look-alike' ), true );
ck( 'every listed file was used', $cut['unused'], array() );
ck( 'the template ends with one newline', "\n" === substr( $cut['pot'], -1 ) && "\n\n" !== substr( $cut['pot'], -2 ), true );

$cut = wpcpm_translators_pot( $pot, array( 'includes/a.php', 'includes/gone.php' ) );
ck( 'a listed file no entry names is reported', $cut['unused'], array( 'includes/gone.php' ) );
$cut = wpcpm_translators_pot( str_replace( "\n", "\r\n", $pot ), array( 'includes/a.php' ) );
ck( 'Windows line endings read the same', $cut['count'], 2 );

ck(
	'a list kept as text reads one path to a line, comments and blanks passed over',
	wpcpm_translators_list( "# What a student sees.\ninc/a.php\n\n  inc/b.php  # the menu\r\ninc/c.php" ),
	array( 'inc/a.php', 'inc/b.php', 'inc/c.php' )
);

echo "\n=== The list against the plugin ===\n";

$root    = dirname( __DIR__ );
$files   = wpcpm_translators_files();
$missing = array_values( array_filter( $files, static function ( $file ) use ( $root ) {
	return ! is_file( $root . '/' . $file );
} ) );
ck( 'every listed file exists', $missing, array() );
ck( 'no file is listed twice', count( $files ), count( array_unique( $files ) ) );

$real = wpcpm_translators_pot( (string) file_get_contents( $root . '/languages/wpcredits-program-manager.pot' ), $files );
ck( 'the real template has strings from every listed file', $real['unused'], array() );
ck( 'the cut is far smaller than the whole, and not empty', $real['count'] > 300 && $real['count'] < 2000, true );
ck( 'the domain is unchanged', false !== strpos( $real['pot'], 'X-Domain: wpcredits-program-manager\n' ), true );

echo "\n=== The download's choices ===\n";

$set = static function ( $locale, $wp, $percent, $slug = 'default' ) {
	return array( 'locale' => $locale, 'slug' => $slug, 'wp_locale' => $wp, 'percent_translated' => $percent );
};
$pick = wpcpm_translations_pick(
	array( $set( 'pl', 'pl_PL', 98 ), $set( 'de', 'de_DE', 51 ), $set( 'de', 'de_DE_formal', 99, 'formal' ), $set( 'pt-br', 'pt_BR', 80 ), $set( 'fr', 'fr_FR', 0 ) ),
	80
);
ck( 'a language at or over the mark ships', array_column( $pick['ship'], 'locale' ), array( 'pl_PL', 'pt_BR' ) );
ck( 'a started language under it is listed as short', array_column( $pick['short'], 'locale' ), array( 'de_DE' ) );
ck( 'the export address uses the site\'s own slug', $pick['ship'][1]['slug'], 'pt-br' );
ck( 'an empty answer is refused', wpcpm_translations_pick( array(), 80 ), null );
ck( 'an answer in another shape is refused', wpcpm_translations_pick( array( array( 'locale' => 'pl' ) ), 80 ), null );
$odd = wpcpm_translations_pick( array( $set( '../x', 'pl_PL', 90 ), $set( 'pl', 'pl/../x', 90 ), $set( 'tlh', '', 90 ), $set( 'la', null, 90 ), array( 'locale' => 'la', 'slug' => 'default', 'percent_translated' => 90 ), $set( 'cs', 'cs_CZ', 90 ) ), 80 );
ck( 'a set whose names are not a locale\'s is passed over, and the rest ship', array_column( $odd['ship'], 'locale' ), array( 'cs_CZ' ) );

ck( 'a little-endian catalog is one', wpcpm_translations_is_mo( "\xde\x12\x04\x95rest" ), true );
ck( 'a big-endian catalog is one', wpcpm_translations_is_mo( "\x95\x04\x12\xderest" ), true );
ck( 'an error page is not', wpcpm_translations_is_mo( '<!DOCTYPE html>' ), false );

ck(
	'a catalog of a language that no longer ships goes, and nothing else',
	wpcpm_translations_stale( array( 'wpcredits-program-manager-pl_PL.mo', 'wpcredits-program-manager-de_DE.mo', 'wpcredits-program-manager.pot', 'other-de_DE.mo' ), array( 'pl_PL' ) ),
	array( 'wpcredits-program-manager-de_DE.mo' )
);

ck(
	'the theme\'s catalogs are told from the plugin\'s by their name',
	wpcpm_translations_stale( array( 'wpcredits-theme-de_DE.mo', 'wpcredits-program-manager-de_DE.mo' ), array(), 'wpcredits-theme' ),
	array( 'wpcredits-theme-de_DE.mo' )
);

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
