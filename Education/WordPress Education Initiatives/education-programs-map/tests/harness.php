<?php
/**
 * Minimal assertion harness. Run with: php tests/run-tests.php
 *
 * No PHPUnit and no WordPress: the handful of WP functions the code under test
 * touches are stubbed below, which keeps this runnable from a clean checkout.
 *
 * @package Education_Programs_Map
 */

$GLOBALS['epm_t'] = array( 'pass' => 0, 'fail' => 0, 'fails' => array() );

function t_ok( $cond, $label ) {
	if ( $cond ) {
		++$GLOBALS['epm_t']['pass'];
		return;
	}
	++$GLOBALS['epm_t']['fail'];
	$GLOBALS['epm_t']['fails'][] = $label;
}

function t_eq( $actual, $expected, $label ) {
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$label .= sprintf( ' (expected %s, got %s)', var_export( $expected, true ), var_export( $actual, true ) );
	}
	t_ok( $ok, $label );
}

function t_report() {
	$t = $GLOBALS['epm_t'];
	foreach ( $t['fails'] as $f ) {
		echo 'FAIL  ' . $f . "\n";
	}
	printf( "\n%d passed, %d failed\n", $t['pass'], $t['fail'] );
	exit( $t['fail'] > 0 ? 1 : 0 );
}

// The class guards on ABSPATH, as a WordPress file should.
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/fake-wp/' );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { // phpcs:ignore
		return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $s ) ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $u ) { // phpcs:ignore
		return (string) $u;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { // phpcs:ignore
		return $text;
	}
}
