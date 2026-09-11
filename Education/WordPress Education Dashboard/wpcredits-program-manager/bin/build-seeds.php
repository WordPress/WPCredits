<?php
/**
 * Write the four seed definitions to includes/tracks/seeds/ (Track Builder, phase T2a).
 *
 * Offline, and the same bytes every run: bin/seed-definitions.php says what each seed is built
 * from. Run it after a hand-written form changes or a fixture is read again, and commit what it
 * writes; bin/test-track-definitions.php fails until the seeds and the PHP agree.
 *
 * Run from the plugin root:  php bin/build-seeds.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function __( $s, $d = null ) { return $s; }
function apply_filters( $tag, $value ) { return $value; }

require_once __DIR__ . '/../includes/class-wpcpm-program.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-student-report-form.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-palette.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/seed-definitions.php';

$folder = dirname( wpcpm_seed_path( '150h' ) );

if ( ! is_dir( $folder ) ) {
	mkdir( $folder, 0755, true );
}

foreach ( array_keys( wpcpm_seed_tracks() ) as $key ) {
	$definition = wpcpm_seed_build( $key );
	$lessons    = count( array_filter( array_column( $definition['questions'], 'learn_lesson_id' ) ) );

	file_put_contents( wpcpm_seed_path( $key ), wpcpm_seed_json( $definition ) );
	printf( "%-6s %2d questions, %2d reporting on a Learn lesson: includes/tracks/seeds/%s.json\n", $key, count( $definition['questions'] ), $lessons, $key );
}
