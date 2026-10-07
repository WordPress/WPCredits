<?php
/**
 * Refuse to publish Campus Connect data that WordCamp Central does not.
 *
 * Run: php bin/check-mirror-safe.php [staged-mirror-folder]
 *
 * WordCamp_Loader::get_public_post_statuses() is ['wcpt-scheduled',
 * 'wcpt-closed']. Every other status is an application in progress - declined,
 * cancelled, on hold, still being vetted - and Central does not list those.
 * The frozen fixtures are exports of the WHOLE report, so they name the
 * institution and city behind each one, and WordPress/WPCredits is public.
 *
 * The mirror push is a standing habit, which is exactly why this exists: a
 * habit that depends on somebody remembering the exclusion is a leak waiting
 * for a distracted afternoon.
 *
 * Exits non-zero on any failure. Safe to wire into a push script.
 *
 * @package WPCC_Tracker
 */

require __DIR__ . '/../tests/harness.php';
require __DIR__ . '/../includes/class-wpcct-normalize.php';

/** Statuses Central publishes. */
const CMS_PUBLIC = array( 'Closed', 'Scheduled' );

$root   = dirname( __DIR__ );
$staged = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$fails  = array();
$notes  = array();

/**
 * Record a check result.
 *
 * @param bool   $ok    Did it pass.
 * @param string $label What was checked.
 * @param string $why   Shown only on failure.
 * @return void
 */
function cms_check( $ok, $label, $why = '' ) {
	global $fails;

	printf( "  %s %s\n", $ok ? 'ok  ' : 'FAIL', $label );

	if ( ! $ok ) {
		$fails[] = $label . ( '' !== $why ? "\n       " . $why : '' );
	}
}

/**
 * The seed must carry only what Central publishes.
 *
 * @param string $path Path to a seed.json.
 * @param string $where Label for messages.
 * @return void
 */
function cms_check_seed( $path, $where ) {
	if ( ! is_readable( $path ) ) {
		cms_check( false, "$where: seed.json is readable", $path );
		return;
	}

	$seed = json_decode( (string) file_get_contents( $path ), true );

	if ( ! is_array( $seed ) ) {
		cms_check( false, "$where: seed.json parses", $path );
		return;
	}

	$planning = isset( $seed['totals']['planning'] ) ? (int) $seed['totals']['planning'] : -1;
	cms_check(
		0 === $planning,
		"$where: seed planning figure is 0",
		"reads $planning. Rebuild with: php bin/build-seed.php"
	);

	$buckets = array();
	foreach ( ( isset( $seed['events'] ) ? $seed['events'] : array() ) as $event ) {
		$buckets[ isset( $event['bucket'] ) ? $event['bucket'] : '?' ] = true;
	}
	$stray = array_diff( array_keys( $buckets ), array( 'completed', 'scheduled' ) );
	cms_check(
		empty( $stray ),
		"$where: every seed event is completed or scheduled",
		'found: ' . implode( ', ', $stray )
	);

	$pipeline = array_keys( isset( $seed['pipeline'] ) ? (array) $seed['pipeline'] : array() );
	$stray    = array_diff( $pipeline, CMS_PUBLIC );
	cms_check(
		empty( $stray ),
		"$where: seed pipeline names only publicly listed statuses",
		'found: ' . implode( ', ', $stray )
	);
}

/**
 * Every seed event must trace to a publicly listed row, by id.
 *
 * The structural check above proves the seed's own buckets are right. This one
 * proves they were not simply mislabelled: it goes back to the report and
 * confirms Central actually lists each event. It is the check that matters, and
 * the text scan further down is only a second net beneath it.
 *
 * @param string $path  Path to a seed.json.
 * @param array  $rows  All fixture rows, keyed by nothing in particular.
 * @param string $where Label for messages.
 * @return void
 */
function cms_check_seed_ids( $path, array $rows, $where ) {
	if ( ! is_readable( $path ) || ! $rows ) {
		return;
	}

	$status = array();
	foreach ( $rows as $row ) {
		$status[ trim( (string) $row['ID'] ) ] = trim( (string) $row['Status'] );
	}

	$seed = json_decode( (string) file_get_contents( $path ), true );
	$bad  = array();

	foreach ( ( isset( $seed['events'] ) ? $seed['events'] : array() ) as $event ) {
		$id = (string) ( isset( $event['id'] ) ? $event['id'] : '' );

		if ( ! isset( $status[ $id ] ) ) {
			$bad[] = "$id (not in any fixture, so it could not be checked)";
		} elseif ( ! in_array( $status[ $id ], CMS_PUBLIC, true ) ) {
			$bad[] = "$id is {$status[ $id ]}";
		}
	}

	cms_check(
		empty( $bad ),
		"$where: every seed event traces to a publicly listed row",
		implode( '; ', $bad )
	);
}

echo "Seed, in the source tree\n";
cms_check_seed( $root . '/data/seed.json', 'source' );

/*
 * Terms that belong only to rows Central does not list. Anything here that
 * also appears in what is about to be published is a leak, unless the code
 * itself explains it: a country in the static region map is a world map, not
 * programme data, and a denylisted row is invented test junk.
 */
$fixtures = glob( $root . '/tests/fixtures/*.tsv' );
$nonpub   = array();
$public   = array();
$denied   = array();
$all      = array();

foreach ( $fixtures as $file ) {
	$lines = file( $file, FILE_IGNORE_NEW_LINES );
	$head  = explode( "\t", array_shift( $lines ) );

	foreach ( $lines as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}

		$cells    = array_pad( explode( "\t", $line ), count( $head ), '' );
		$row      = array_combine( $head, array_slice( $cells, 0, count( $head ) ) );
		$is_ok    = in_array( trim( (string) $row['Status'] ), CMS_PUBLIC, true );
		$all[]    = $row;

		foreach ( array( 'Institution Name', 'City', 'Name' ) as $column ) {
			$value = trim( (string) ( isset( $row[ $column ] ) ? $row[ $column ] : '' ) );

			if ( strlen( $value ) <= 6 ) {
				continue;
			}

			if ( WPCCT_Normalize::is_denied( $row ) ) {
				$denied[ $value ] = true;
			} elseif ( $is_ok ) {
				$public[ $value ] = true;
			} else {
				$nonpub[ $value ] = true;
			}
		}
	}
}

// Only a term that NEVER appears on a publicly listed row is evidence.
$suspect = array_keys( array_diff_key( $nonpub, $public, $denied ) );

/*
 * And it is only evidence if it is not PART of something public either. A
 * school name recurs across a region, one event's name is another's prefix,
 * and the same town hosts a closed event and a declined one. Five such
 * collisions were in the first real run of this script, every one explained by
 * a publicly listed row, which is exactly how a check trains people to ignore
 * it. Compared against decoded values, because the report HTML-encodes and the
 * seed stores what decode() produced.
 */
$public_text = array_map(
	function ( $value ) {
		return WPCCT_Normalize::decode( $value );
	},
	array_keys( $public )
);

$suspect = array_values(
	array_filter(
		$suspect,
		function ( $term ) use ( $public_text ) {
			$term = WPCCT_Normalize::decode( $term );

			foreach ( $public_text as $public_value ) {
				if ( false !== strpos( $public_value, $term ) ) {
					return false;
				}
			}

			return true;
		}
	)
);

// A country the plugin maps to a region is a world map, not programme data.
$suspect = array_values(
	array_filter(
		$suspect,
		function ( $term ) {
			return '' === WPCCT_Normalize::region_for( $term );
		}
	)
);

cms_check_seed_ids( $root . '/data/seed.json', $all, 'source' );

printf( "\nLeak terms derived from %d fixture(s): %d to test\n", count( $fixtures ), count( $suspect ) );

if ( ! $fixtures ) {
	$notes[] = 'No fixtures found, so the leak scan was skipped. Run this from a checkout that has them.';
}

if ( '' === $staged ) {
	$notes[] = 'No staged folder given, so only the source seed was checked. Pass the mirror folder to scan it.';
} else {
	printf( "\nStaged tree: %s\n", $staged );

	cms_check( is_dir( $staged ), 'staged folder exists', $staged );

	if ( is_dir( $staged ) ) {
		$files = array();
		$walk  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $staged, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $walk as $file ) {
			if ( $file->isFile() ) {
				$files[] = $file->getPathname();
			}
		}

		$tsv = array_values( array_filter( $files, function ( $f ) { return '.tsv' === substr( $f, -4 ); } ) );
		cms_check( empty( $tsv ), 'no .tsv anywhere in the staged tree', implode( ', ', $tsv ) );

		$fixdir = $staged . '/wpcc-tracker/tests/fixtures';
		if ( is_dir( $fixdir ) ) {
			$left = array_values( array_diff( scandir( $fixdir ), array( '.', '..', 'README.md' ) ) );
			cms_check( empty( $left ), 'staged tests/fixtures holds nothing but README.md', implode( ', ', $left ) );
		}

		cms_check_seed( $staged . '/wpcc-tracker/data/seed.json', 'staged' );
		cms_check_seed_ids( $staged . '/wpcc-tracker/data/seed.json', $all, 'staged' );

		// The scan itself. Read each file once; the term list is the hot loop.
		$hits = array();
		foreach ( $files as $file ) {
			if ( preg_match( '/\.(png|jpg|jpeg|gif|zip|woff2?|ttf)$/i', $file ) ) {
				continue;
			}

			$body = (string) file_get_contents( $file );

			foreach ( $suspect as $term ) {
				if ( false !== strpos( $body, $term ) ) {
					$hits[ $term ][] = str_replace( $staged . '/', '', $file );
				}
			}
		}

		cms_check(
			empty( $hits ),
			'no name from a non-public application appears in the staged tree',
			implode(
				"\n       ",
				array_map(
					function ( $term ) use ( $hits ) {
						return sprintf( '"%s" in %s', $term, implode( ', ', array_unique( $hits[ $term ] ) ) );
					},
					array_keys( $hits )
				)
			)
		);
	}
}

foreach ( $notes as $note ) {
	printf( "\nNote: %s\n", $note );
}

if ( $fails ) {
	printf( "\n%d check(s) FAILED:\n", count( $fails ) );
	foreach ( $fails as $fail ) {
		printf( "  - %s\n", $fail );
	}
	printf( "\nDo not push. See bin/build-seed.php and the rsync exclusions.\n" );
	exit( 1 );
}

printf( "\nAll checks passed. Nothing Central withholds is in what you are about to publish.\n" );
