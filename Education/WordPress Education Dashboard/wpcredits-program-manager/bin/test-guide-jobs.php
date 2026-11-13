<?php
/**
 * The help links from the dashboards to the Administrator guide's jobs (WPCPM_Guide_Jobs).
 *
 * - Every job's anchor is the id of an h3 in docs/build/administrators.html whose text is exactly the
 *   job's title, so a renamed job fails here until the list follows.
 * - `url()` is the guide's address with the anchor; '' for an unknown key and for a site with no
 *   published guide.
 * - `link()` is one small link for an Administrator, escaped; '' for any other reader, an unknown key
 *   and a site with no guide.
 * - Every key is used by a `WPCPM_Guide_Jobs::link(` call in includes/, and no call names a key the
 *   list does not hold.
 *
 * Run from the plugin root:  php bin/test-guide-jobs.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'OBJECT', 'OBJECT' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

$GLOBALS['can']  = true;
$GLOBALS['page'] = null;
$GLOBALS['lookups'] = 0;

class WP_Post {
	public $ID = 1, $post_status = 'publish', $post_name = 'administrator-guide';
}

function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function apply_filters( $hook, $value ) { return $value; }
function get_permalink( $post ) { return 'https://example.test/administrator-guide/'; }
function get_page_by_path( $slug, $output = 'OBJECT', $post_type = 'page' ) { ++$GLOBALS['lookups']; return $GLOBALS['page']; }
function current_user_can( $cap ) { return $GLOBALS['can'] && 'wpcpm_manage_program' === $cap; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-guide-jobs.php';

$fails = 0;
function ck( $name, $got, $want ) {
	global $fails;
	if ( $got === $want ) {
		echo "  ok  $name\n";
		return;
	}
	++$fails;
	echo "FAIL  $name\n      got:  " . var_export( $got, true ) . "\n      want: " . var_export( $want, true ) . "\n";
}

echo "-- every job is a heading of the built guide ---------------------------\n";

$built = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/build/administrators.html' );
preg_match_all( '#<h3[^>]*\sid="([^"]*)"[^>]*>(.*?)</h3>#s', $built, $m, PREG_SET_ORDER );
$headings = array();
foreach ( $m as $h ) {
	$headings[ $h[1] ] = html_entity_decode( trim( strip_tags( $h[2] ) ), ENT_QUOTES, 'UTF-8' );
}

ck( 'the list holds the seventeen jobs', count( WPCPM_Guide_Jobs::JOBS ), 17 );
foreach ( WPCPM_Guide_Jobs::JOBS as $key => $job ) {
	ck( "$key: the anchor is an h3 of the guide", isset( $headings[ $job['anchor'] ] ), true );
	ck( "$key: that heading's words are the title, exactly", $headings[ $job['anchor'] ] ?? null, $job['title'] );
}

echo "\n-- url() ----------------------------------------------------------------\n";

$GLOBALS['page'] = new WP_Post(); WPCPM_Guide_Jobs::forget();
ck( 'the guide with the job\'s anchor', WPCPM_Guide_Jobs::url( 'institution-application' ), 'https://example.test/administrator-guide/#a-school-applied' );
ck( 'an unknown key has none', WPCPM_Guide_Jobs::url( 'nope' ), '' );
ck( 'an empty key has none', WPCPM_Guide_Jobs::url( '' ), '' );
$GLOBALS['page'] = null; WPCPM_Guide_Jobs::forget();
ck( 'no guide page, no address', WPCPM_Guide_Jobs::url( 'institution-application' ), '' );
$draft              = new WP_Post();
$draft->post_status = 'draft';
$GLOBALS['page']    = $draft; WPCPM_Guide_Jobs::forget();
ck( 'a guide that is a draft is no guide', WPCPM_Guide_Jobs::url( 'institution-application' ), '' );

$GLOBALS['page'] = new WP_Post(); WPCPM_Guide_Jobs::forget();
$GLOBALS['lookups'] = 0;
WPCPM_Guide_Jobs::url( 'institution-application' );
WPCPM_Guide_Jobs::url( 'syncs' );
WPCPM_Guide_Jobs::link( 'duplicates', 'X' );
ck( 'the guide is looked up once a request, not once a card', $GLOBALS['lookups'], 1 );
$GLOBALS['page'] = null; WPCPM_Guide_Jobs::forget();
$GLOBALS['lookups'] = 0;
WPCPM_Guide_Jobs::url( 'syncs' );
WPCPM_Guide_Jobs::url( 'syncs' );
ck( 'and a site with no guide is looked up once too', $GLOBALS['lookups'], 1 );

echo "\n-- link() ---------------------------------------------------------------\n";

$GLOBALS['page'] = new WP_Post(); WPCPM_Guide_Jobs::forget();
ck( 'the link, with the card\'s title in its name',
	WPCPM_Guide_Jobs::link( 'institution-application', 'Institution applications' ),
	'<a class="wpcpm-job-link" href="https://example.test/administrator-guide/#a-school-applied">How to do this<span class="screen-reader-text">: Institution applications</span></a>' );
ck( 'without a title, the words alone',
	WPCPM_Guide_Jobs::link( 'institution-application' ),
	'<a class="wpcpm-job-link" href="https://example.test/administrator-guide/#a-school-applied">How to do this</a>' );
ck( 'the title is escaped', false !== strpos( WPCPM_Guide_Jobs::link( 'syncs', '<b>"x"</b>' ), '&lt;b&gt;&quot;x&quot;&lt;/b&gt;' ) && false === strpos( WPCPM_Guide_Jobs::link( 'syncs', '<b>' ), '<b>' ), true );
ck( 'an unknown key gives none', WPCPM_Guide_Jobs::link( 'nope', 'X' ), '' );
$GLOBALS['can'] = false;
ck( 'a reader who cannot manage the program gets none', WPCPM_Guide_Jobs::link( 'institution-application', 'X' ), '' );
$GLOBALS['can']  = true;
$GLOBALS['page'] = null; WPCPM_Guide_Jobs::forget();
ck( 'no guide page, no link', WPCPM_Guide_Jobs::link( 'institution-application', 'X' ), '' );

echo "\n-- every key is used, and no call names another -------------------------\n";

$used = array();
$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . 'includes', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	if ( '.php' !== substr( $f->getFilename(), -4 ) || 'class-wpcpm-guide-jobs.php' === $f->getFilename() ) {
		continue;
	}
	preg_match_all( "/WPCPM_Guide_Jobs::link\(\s*'([^']*)'/", (string) file_get_contents( $f->getPathname() ), $c );
	foreach ( $c[1] as $key ) {
		$used[ $key ] = true;
	}
}
ck( 'each key in the list has a call', array_values( array_diff( array_keys( WPCPM_Guide_Jobs::JOBS ), array_keys( $used ) ) ), array() );
ck( 'no call names a key that is not in the list', array_values( array_diff( array_keys( $used ), array_keys( WPCPM_Guide_Jobs::JOBS ) ) ), array() );

echo $fails ? "\n$fails FAILED\n" : "\nAll guide-jobs checks passed.\n";
exit( $fails ? 1 : 0 );
