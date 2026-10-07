<?php
/**
 * Regenerate data/seed.json from a frozen report fixture.
 *
 * Run: php bin/build-seed.php [fixture-file-name]
 *
 * The seed carries ONLY events WordCamp Central itself publishes, and that is
 * not a preference. This file ships inside the plugin zip and is mirrored to a
 * public repository. WordCamp_Loader::get_public_post_statuses() is
 * ['wcpt-scheduled', 'wcpt-closed'] - which is the very reason this plugin
 * exists, since the planning pipeline cannot be read from the public API - and
 * every other status is an application still in progress. Shipping those would
 * name the institution and city behind a declined, cancelled or still-vetting
 * application, publishing exactly what Central withholds.
 *
 * So the seed's planning figure reads 0 until the first real sync. That is the
 * honest default for a file which is not allowed to carry the number.
 *
 * @package WPCC_Tracker
 */

require __DIR__ . '/../tests/harness.php';
require __DIR__ . '/../includes/class-wpcct-normalize.php';

/** Statuses Central publishes. Anything else is an application in progress. */
const WPCCT_SEED_PUBLIC = array( 'Closed', 'Scheduled' );

$fixture = isset( $argv[1] ) ? $argv[1] : 'campus-connect-details-2026-10-07.tsv';
$rows    = wpcct_load_fixture( $fixture );

$public = array_values(
	array_filter(
		$rows,
		function ( $row ) {
			$status = trim( (string) ( isset( $row['Status'] ) ? $row['Status'] : '' ) );

			return in_array( $status, WPCCT_SEED_PUBLIC, true );
		}
	)
);

$blob         = WPCCT_Normalize::build( $public );
$blob['seed'] = true;

$path = __DIR__ . '/../data/seed.json';
if ( ! is_dir( dirname( $path ) ) ) {
	mkdir( dirname( $path ), 0755, true );
}

file_put_contents( $path, json_encode( $blob, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

printf(
	"Wrote %s from %s\n  %d of %d rows kept (%s)\n  %d withheld as not publicly listed by Central\n  %d events, %d markers\n",
	$path,
	$fixture,
	count( $public ),
	count( $rows ),
	implode( ' + ', WPCCT_SEED_PUBLIC ),
	count( $rows ) - count( $public ),
	count( $blob['events'] ),
	count( $blob['markers'] )
);
