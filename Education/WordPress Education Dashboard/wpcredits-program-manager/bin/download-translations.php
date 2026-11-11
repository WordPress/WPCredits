<?php
/**
 * Downloads the plugin's translations from translate.wordpress.org into languages/.
 *
 * A release step, run from the plugin root before the version bump is committed:
 *
 *     php bin/download-translations.php --project=meta/<slug>
 *     php bin/download-translations.php --project=meta/<slug> --min=50
 *     php bin/download-translations.php --project=meta/<slug> --dry-run
 *
 * It asks translate.wordpress.org which languages the project has, and writes one
 * `wpcredits-program-manager-<locale>.mo` for each that is at least `--min` percent translated
 * (80 unless said). A language that has fallen below the mark loses its file: a student would
 * otherwise read a screen that is half English. Only approved translations are exported.
 *
 * Nothing in languages/ changes unless every download succeeded: the files are fetched into a
 * temporary folder first, each is checked to be a gettext catalog, and only then moved into place.
 *
 * The theme's translations come the same way, into its own folder under its own name:
 *
 *     php bin/download-translations.php --project=meta/<slug> --domain=wpcredits-theme \
 *         --dir=<theme>/languages
 *
 * `--project` is required until `WPCPM_TRANSLATIONS_PROJECT` below names the project the Meta team
 * creates. `--dry-run` prints the table and writes nothing.
 *
 * @package WPCredits_Program_Manager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

// The project's path on translate.wordpress.org, once it exists. Empty: --project must say it.
const WPCPM_TRANSLATIONS_PROJECT = '';
const WPCPM_TRANSLATIONS_HOST    = 'https://translate.wordpress.org';
const WPCPM_TRANSLATIONS_DOMAIN  = 'wpcredits-program-manager';
const WPCPM_TRANSLATIONS_MIN     = 80;

/**
 * Which of a project's translation sets ship, and which fall short.
 *
 * A variant (German formal, say) is left out: only a language's default set is exported.
 *
 * @param mixed $sets What the API answered under `translation_sets`.
 * @param int   $min  The percent a language needs to ship.
 * @return array{ship:array<int,array{slug:string,locale:string,percent:int}>,short:array<int,array{slug:string,locale:string,percent:int}>}|null
 *         Null when the answer is not in the shape this script knows.
 */
function wpcpm_translations_pick( $sets, $min ) {
	if ( ! is_array( $sets ) || ! $sets ) {
		return null;
	}
	$out = array(
		'ship'  => array(),
		'short' => array(),
	);
	foreach ( $sets as $set ) {
		if ( ! is_array( $set ) || ! isset( $set['locale'], $set['slug'], $set['percent_translated'] ) ) {
			return null;
		}
		// A language WordPress has no locale for (Latin, say) comes without one, and cannot ship.
		if ( ! isset( $set['wp_locale'] ) ) {
			continue;
		}
		if ( 'default' !== $set['slug'] || 0 === strpos( (string) $set['wp_locale'], 'en_US' ) ) {
			continue;
		}
		// Both become part of a URL or a file name, so a set whose names are anything but what a
		// locale is made of is passed over (the site lists a few with no WordPress locale at all).
		if ( ! preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) $set['locale'] ) || ! preg_match( '/^[A-Za-z0-9_]+$/', (string) $set['wp_locale'] ) ) {
			continue;
		}
		$percent = (int) $set['percent_translated'];
		if ( 0 === $percent ) {
			continue;
		}
		$row = array(
			'slug'    => (string) $set['locale'],
			'locale'  => (string) $set['wp_locale'],
			'percent' => $percent,
		);
		$out[ $percent >= $min ? 'ship' : 'short' ][] = $row;
	}
	return $out;
}

/**
 * Whether these bytes are a gettext catalog (.mo), in either byte order.
 *
 * @param string $bytes The file's content.
 * @return bool
 */
function wpcpm_translations_is_mo( $bytes ) {
	$magic = substr( (string) $bytes, 0, 4 );
	return "\xde\x12\x04\x95" === $magic || "\x95\x04\x12\xde" === $magic;
}

/**
 * The catalogs in languages/ that belong to none of the shipped locales.
 *
 * @param string[] $present File names in languages/.
 * @param string[] $locales The WordPress locales that ship.
 * @param string   $domain  The text domain the catalogs are named after.
 * @return string[] The file names to remove.
 */
function wpcpm_translations_stale( array $present, array $locales, $domain = WPCPM_TRANSLATIONS_DOMAIN ) {
	$stale = array();
	foreach ( $present as $name ) {
		if ( preg_match( '/^' . preg_quote( $domain, '/' ) . '-([A-Za-z0-9_]+)\.mo$/', $name, $m ) && ! in_array( $m[1], $locales, true ) ) {
			$stale[] = $name;
		}
	}
	return $stale;
}

// Loaded by the test for the functions above: nothing below runs.
if ( defined( 'WPCPM_DOWNLOAD_TRANSLATIONS_AS_LIBRARY' ) ) {
	return;
}

/**
 * Fetches a URL, waiting and trying again when translate.wordpress.org says it was asked too often.
 *
 * @param string $url The address.
 * @return array{status:int,body:string}
 */
function wpcpm_translations_get( $url ) {
	$status = 0;
	$body   = '';
	for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
		$context = stream_context_create(
			array(
				'http' => array(
					'header'        => "User-Agent: WPCredits Program Manager translations\r\n",
					'timeout'       => 30,
					'ignore_errors' => true,
				),
			)
		);
		$body    = @file_get_contents( $url, false, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A command-line tool, outside WordPress.
		$status  = 0;
		$wait    = 15;
		// PHP 8.4 added the function and 8.5 deprecated the variable it replaces.
		$headers = function_exists( 'http_get_last_response_headers' ) ? (array) http_get_last_response_headers() : array();
		if ( ! $headers ) {
			// Read by name, so PHP 8.5 does not report the variable at compile time.
			$local   = get_defined_vars();
			$headers = isset( $local['http_response_header'] ) ? (array) $local['http_response_header'] : array();
		}
		foreach ( $headers as $line ) {
			if ( preg_match( '#^HTTP/\S+ (\d{3})#', $line, $m ) ) {
				$status = (int) $m[1];
			} elseif ( preg_match( '/^retry-after:\s*(\d+)/i', $line, $m ) ) {
				$wait = min( 60, max( 1, (int) $m[1] ) );
			}
		}
		if ( 429 !== $status || 3 === $attempt ) {
			break;
		}
		sleep( $wait );
	}
	return array(
		'status' => $status,
		'body'   => false === $body ? '' : (string) $body,
	);
}

/**
 * Stops with a sentence.
 *
 * @param string $sentence What went wrong.
 * @return void
 */
function wpcpm_translations_stop( $sentence ) {
	fwrite( STDERR, $sentence . " Nothing in languages/ was changed.\n" );
	exit( 1 );
}

$wpcpm_project = WPCPM_TRANSLATIONS_PROJECT;
$wpcpm_min     = WPCPM_TRANSLATIONS_MIN;
$wpcpm_dry     = false;
$wpcpm_domain  = WPCPM_TRANSLATIONS_DOMAIN;
$wpcpm_dir     = dirname( __DIR__ ) . '/languages';
foreach ( array_slice( $argv, 1 ) as $wpcpm_arg ) {
	if ( 0 === strpos( $wpcpm_arg, '--project=' ) ) {
		$wpcpm_project = substr( $wpcpm_arg, 10 );
	} elseif ( preg_match( '/^--min=(\d{1,3})$/', $wpcpm_arg, $wpcpm_m ) ) {
		$wpcpm_min = min( 100, (int) $wpcpm_m[1] );
	} elseif ( preg_match( '/^--domain=([a-z0-9-]+)$/', $wpcpm_arg, $wpcpm_m ) ) {
		$wpcpm_domain = $wpcpm_m[1];
	} elseif ( 0 === strpos( $wpcpm_arg, '--dir=' ) ) {
		$wpcpm_dir = rtrim( substr( $wpcpm_arg, 6 ), '/' );
	} elseif ( '--dry-run' === $wpcpm_arg ) {
		$wpcpm_dry = true;
	} else {
		wpcpm_translations_stop( "Unknown argument: $wpcpm_arg." );
	}
}
if ( ! preg_match( '#^[a-z0-9-]+(/[a-z0-9-]+)*$#', $wpcpm_project ) ) {
	wpcpm_translations_stop( 'Say which project to read: --project=meta/<slug>, as translate.wordpress.org names it.' );
}

if ( ! is_dir( $wpcpm_dir ) ) {
	wpcpm_translations_stop( "There is no folder $wpcpm_dir." );
}
$wpcpm_api = wpcpm_translations_get( WPCPM_TRANSLATIONS_HOST . '/api/projects/' . $wpcpm_project . '/' );
if ( 404 === $wpcpm_api['status'] ) {
	wpcpm_translations_stop( "translate.wordpress.org has no project $wpcpm_project." );
}
if ( 200 !== $wpcpm_api['status'] ) {
	wpcpm_translations_stop( "translate.wordpress.org answered {$wpcpm_api['status']} for the project." );
}
$wpcpm_data = json_decode( $wpcpm_api['body'], true );
$wpcpm_pick = wpcpm_translations_pick( is_array( $wpcpm_data ) && isset( $wpcpm_data['translation_sets'] ) ? $wpcpm_data['translation_sets'] : null, $wpcpm_min );
if ( null === $wpcpm_pick ) {
	wpcpm_translations_stop( 'translate.wordpress.org answered in a shape this script does not know.' );
}

$wpcpm_tmp = sys_get_temp_dir() . '/wpcpm-translations-' . getmypid();
if ( ! $wpcpm_dry && ! mkdir( $wpcpm_tmp, 0700 ) ) {
	wpcpm_translations_stop( 'The temporary folder could not be made.' );
}
foreach ( $wpcpm_pick['ship'] as $wpcpm_row ) {
	if ( $wpcpm_dry ) {
		continue;
	}
	$wpcpm_mo = wpcpm_translations_get( WPCPM_TRANSLATIONS_HOST . '/projects/' . $wpcpm_project . '/' . $wpcpm_row['slug'] . '/default/export-translations/?format=mo' );
	if ( 200 !== $wpcpm_mo['status'] || ! wpcpm_translations_is_mo( $wpcpm_mo['body'] ) ) {
		array_map( 'unlink', (array) glob( $wpcpm_tmp . '/*' ) );
		rmdir( $wpcpm_tmp );
		wpcpm_translations_stop( "The export for {$wpcpm_row['locale']} answered {$wpcpm_mo['status']}, or was not a gettext catalog." );
	}
	file_put_contents( $wpcpm_tmp . '/' . $wpcpm_domain . '-' . $wpcpm_row['locale'] . '.mo', $wpcpm_mo['body'] );
}

$wpcpm_locales = array_column( $wpcpm_pick['ship'], 'locale' );
$wpcpm_stale   = wpcpm_translations_stale( array_map( 'basename', (array) glob( $wpcpm_dir . '/*.mo' ) ), $wpcpm_locales, $wpcpm_domain );
if ( ! $wpcpm_dry ) {
	foreach ( (array) glob( $wpcpm_tmp . '/*.mo' ) as $wpcpm_file ) {
		rename( $wpcpm_file, $wpcpm_dir . '/' . basename( $wpcpm_file ) );
	}
	rmdir( $wpcpm_tmp );
	foreach ( $wpcpm_stale as $wpcpm_name ) {
		unlink( $wpcpm_dir . '/' . $wpcpm_name );
	}
}

echo ( $wpcpm_dry ? 'Would ship' : 'Shipped' ) . " (at least $wpcpm_min%):\n";
foreach ( $wpcpm_pick['ship'] as $wpcpm_row ) {
	echo "  {$wpcpm_row['locale']}  {$wpcpm_row['percent']}%\n";
}
echo $wpcpm_pick['ship'] ? '' : "  none\n";
echo "Started, below the mark:\n";
foreach ( $wpcpm_pick['short'] as $wpcpm_row ) {
	echo "  {$wpcpm_row['locale']}  {$wpcpm_row['percent']}%\n";
}
echo $wpcpm_pick['short'] ? '' : "  none\n";
if ( $wpcpm_stale ) {
	echo ( $wpcpm_dry ? 'Would remove: ' : 'Removed: ' ) . implode( ', ', $wpcpm_stale ) . "\n";
}
