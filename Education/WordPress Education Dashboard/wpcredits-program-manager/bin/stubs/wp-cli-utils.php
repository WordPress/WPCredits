<?php
/**
 * WP-CLI's `format_items()`, stood in: it keeps what it was asked to print, so a suite can read it.
 *
 * Loaded with `require __DIR__ . '/stubs/wp-cli-utils.php';` from a suite whose WP_CLI stand-in has
 * a public static `$lines`.
 */

namespace WP_CLI\Utils;

function format_items( $format, $items, $fields ) {
	\WP_CLI::$lines[] = 'items: ' . $format . ' ' . json_encode( array( 'fields' => array_values( (array) $fields ), 'items' => array_values( (array) $items ) ) );
}
