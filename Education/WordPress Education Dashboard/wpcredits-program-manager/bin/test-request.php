<?php
/**
 * WPCPM_Request: what a request's numbers and words are read as.
 *
 * `id()` and `posted_id()` promise 0 for anything that is not a number. `absint()` casts a
 * non-empty array to 1, so `?wpcpm_export_student_id[]=x` named user 1 while the docblock said 0;
 * nothing reachable that way differed from `=1`, but a reader promising 0 has to mean it.
 *
 * Run from the plugin root:  php bin/test-request.php
 */
if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function network_admin_url( $p = '' ) { return 'https://example.test/wp-admin/network/' . $p; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function is_multisite() { return false; }

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';

$total = 0;
$fails = 0;
function ck( $label, $actual, $expected ) {
	global $total, $fails;
	++$total;
	if ( $actual === $expected ) {
		echo "ok   $label\n";
		return;
	}
	++$fails;
	echo "FAIL $label\n     got:  " . var_export( $actual, true ) . "\n     want: " . var_export( $expected, true ) . "\n";
}

echo "=== id() and posted_id() mean 0 when they say 0 ===\n";

$_GET  = array( 'n' => '42', 'arr' => array( 'x' ), 'nested' => array( 'a' => array( 'b' ) ), 'neg' => '-7', 'word' => 'seven' );
$_POST = array( 'n' => '17', 'arr' => array( '9' ), 'blank' => '' );

ck( 'a number is read', WPCPM_Request::id( 'n' ), 42 );
ck( 'an array is 0, not the 1 absint() makes of it', WPCPM_Request::id( 'arr' ), 0 );
ck( 'so is a nested array', WPCPM_Request::id( 'nested' ), 0 );
ck( 'a negative number is its absolute value, as before', WPCPM_Request::id( 'neg' ), 7 );
ck( 'a word is 0', WPCPM_Request::id( 'word' ), 0 );
ck( 'and an absent argument is 0', WPCPM_Request::id( 'missing' ), 0 );
ck( 'the same on a posted number', WPCPM_Request::posted_id( 'n' ), 17 );
ck( 'a posted array is 0 even when its one value is a number', WPCPM_Request::posted_id( 'arr' ), 0 );
ck( 'a posted blank is 0', WPCPM_Request::posted_id( 'blank' ), 0 );

echo "\n=== posted_verbatim(): a code is kept as it was typed ===\n";

$_POST = array(
	'link'    => 'https://shop.example/?code=WP%20CREDITS&next=%2Fcart',
	'control' => "ABC\x07DEF",
	'tabbed'  => "  A\tB  ",
	'lines'   => " A-1 \r\n\r\nB%202 \n",
);

ck( 'a percent-encoded checkout link survives whole', WPCPM_Request::posted_verbatim( 'link' ), 'https://shop.example/?code=WP%20CREDITS&next=%2Fcart' );
ck( 'a control character is dropped', WPCPM_Request::posted_verbatim( 'control' ), 'ABCDEF' );
ck( 'a tab inside is kept and the ends are trimmed', WPCPM_Request::posted_verbatim( 'tabbed' ), "A\tB" );
ck( 'an absent field is the fallback', WPCPM_Request::posted_verbatim( 'missing', 'none' ), 'none' );
ck( 'the lines variant trims each line and drops the empty ones', WPCPM_Request::posted_verbatim_lines( 'lines' ), "A-1\nB%202" );
ck( 'and its absent field is the fallback too', WPCPM_Request::posted_verbatim_lines( 'missing', 'none' ), 'none' );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILED', $fails ) : 'ALL PASS', $total );
exit( $fails ? 1 : 0 );
