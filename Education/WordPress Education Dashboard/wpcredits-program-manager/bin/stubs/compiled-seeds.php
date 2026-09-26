<?php
/**
 * The four original tracks as a site holds them compiled, for every suite that reads the program
 * map or a report form.
 *
 * `WPCPM_Program`'s five maps and `WPCPM_Student_Report_Form::fields()` are answered through the
 * filters `WPCPM_Tracks::init()` hooks, from what a compile writes: the `wpcpm_tracks` option
 * (status => row) and one `wpcpm_track_fields_<key>` option per track (its form). Nothing stands
 * beside those filters since the hand-written rows and forms were removed (the Track Builder
 * design's section 8), so a suite that planted nothing would read an empty map and no form. So each
 * suite holds what a site holds: the four tracks, compiled.
 *
 * Built, never written out. Every row and form here is made from includes/tracks/seeds/*.json, the
 * files a fresh site seeds from, by `WPCPM_Track_Definition`'s own decode(), normalize(), row() and
 * compile_fields(), which are what `WPCPM_Track_Store::compile()` writes with: a seed or a compile
 * step that changes reaches every suite, with nothing here to update. The post IDs are the ones the
 * live site gave the four tracks, and the rows are in their order, which is the order the compiled
 * index keeps and the program map lists.
 *
 * Loaded with `require_once __DIR__ . '/stubs/compiled-seeds.php';` from a suite's header, after its
 * own WordPress stand-ins. It declares `__()`, `add_filter()`, `apply_filters()`, `add_action()` and
 * `get_option()` only for a suite that has none of its own: the map is made of filters now, so a
 * suite whose `apply_filters()` handed back its input gives that one up for the one here, which
 * runs what was hooked. The suite then calls `WPCPM_Tracks::init()` and plants the options with
 * `wpcpm_seed_compiled_options( $GLOBALS['opts'] );` before its first read of the map.
 */

// The program map first: `WPCPM_Tracks` names its status constants in the four original tracks'
// pairs, which PHP evaluates the first time the class is used.
require_once dirname( __DIR__, 2 ) . '/includes/class-wpcpm-program.php';
require_once dirname( __DIR__, 2 ) . '/includes/tracks/class-wpcpm-track-definition.php';
require_once dirname( __DIR__, 2 ) . '/includes/tracks/class-wpcpm-tracks.php';

if ( ! function_exists( '__' ) ) {
	/**
	 * Text as written, for the translated strings of the classes a suite loads.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Keep a filter, as WordPress keeps one.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      The filter.
	 * @param int      $priority      The lower runs first.
	 * @param int      $accepted_args How many of the arguments it is handed.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wpcpm_stub_filters'][ (string) $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );

		return true;
	}
}

/**
 * Run the filters `add_filter()` above kept for a hook, as WordPress runs them.
 *
 * By priority, the lowest first, and in the order they were added within one; each is handed as
 * many of the arguments as it accepts, which is what lets `WPCPM_Tracks::filter_fields()` read
 * the track key `fields()` passes beside the form. Named apart from `apply_filters()`, so a suite
 * whose own `apply_filters()` answers one hook itself can hand every other hook to this.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value The value to filter.
 * @param mixed  ...$args The rest of the caller's arguments.
 * @return mixed
 */
function wpcpm_stub_apply_filters( $hook, $value, ...$args ) {
	$hooked = isset( $GLOBALS['wpcpm_stub_filters'][ (string) $hook ] ) ? $GLOBALS['wpcpm_stub_filters'][ (string) $hook ] : array();

	ksort( $hooked );

	foreach ( $hooked as $filters ) {
		foreach ( $filters as $filter ) {
			$value = call_user_func_array( $filter[0], array_slice( array_merge( array( $value ), $args ), 0, $filter[1] ) );
		}
	}

	return $value;
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Filter a value through what was hooked.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value The value to filter.
	 * @param mixed  ...$args The rest of the caller's arguments.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		return wpcpm_stub_apply_filters( $hook, $value, ...$args );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Take an action and run none: a suite with no actions of its own fires none, and the one
	 * `WPCPM_Tracks::init()` adds, the chip styles on `init`, paints nothing for the four tracks.
	 *
	 * @return true
	 */
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	// The store every suite keeps its options in, made for the suite that keeps none.
	if ( ! isset( $GLOBALS['opts'] ) || ! is_array( $GLOBALS['opts'] ) ) {
		$GLOBALS['opts'] = array();
	}

	/**
	 * An option from `$GLOBALS['opts']`, the array a suite hands `wpcpm_seed_compiled_options()`.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default What a missing option reads as.
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $name ] : $default;
	}
}

/**
 * The four seeds compiled: the compiled index and each track's form, built once a run.
 *
 * `row()` is handed the definition and its post and nothing else. Its default automation, unticked,
 * is not what the live site's four carry, since somebody ticked each; no suite can tell the two
 * apart, because `WPCPM_Institutions::automation_statuses()` names these four statuses whatever a
 * row says.
 *
 * @return array{rows: array<string, array>, fields: array<string, array>} `rows`: status => row,
 *               in post order; `fields`: track key => form.
 */
function wpcpm_compiled_seeds() {
	static $compiled = null;

	if ( null !== $compiled ) {
		return $compiled;
	}

	// Each seed's post on the live site, by the seed's key, in post ID order: the order a compile reads
	// the published tracks in, and so the order of the index it writes.
	$posts = array(
		'150h'   => 984,
		'50h'    => 986,
		'dev'    => 988,
		'design' => 990,
	);
	$dir   = dirname( __DIR__, 2 ) . '/includes/tracks/seeds/';
	$paths = glob( $dir . '*.json' );
	$found = array_map(
		static function ( $path ) {
			return basename( $path, '.json' );
		},
		is_array( $paths ) ? $paths : array()
	);
	$named = array_keys( $posts );

	sort( $found );
	sort( $named );

	// A seed with no post here would be left out of every suite without a word.
	if ( $found !== $named ) {
		fwrite( STDERR, 'The seeds in ' . $dir . ' are ' . implode( ', ', $found ) . '; bin/stubs/compiled-seeds.php gives a post to ' . implode( ', ', $named ) . ".\n" );
		exit( 1 );
	}

	$compiled = array(
		'rows'   => array(),
		'fields' => array(),
	);

	foreach ( $posts as $key => $post_id ) {
		$definition = WPCPM_Track_Definition::decode( (string) file_get_contents( $dir . $key . '.json' ) );

		if ( ! is_array( $definition ) || ! isset( $definition['key'], $definition['status'] ) || $key !== $definition['key'] ) {
			fwrite( STDERR, 'The seed ' . $dir . $key . ".json is not a definition of the track it is named for.\n" );
			exit( 1 );
		}

		$definition = WPCPM_Track_Definition::normalize( $definition );

		$compiled['rows'][ (string) $definition['status'] ] = WPCPM_Track_Definition::row( $definition, $post_id );
		$compiled['fields'][ $key ]                          = WPCPM_Track_Definition::compile_fields( $definition );
	}

	return $compiled;
}

/**
 * Plant the four compiled tracks in a suite's options, as a compile writes them.
 *
 * The index replaces whatever the store held under its name. Then what `WPCPM_Tracks` read before
 * is forgotten, as `WPCPM_Track_Store::compile()` forgets it, so the next read of the map is of
 * these options wherever in a suite they are planted.
 *
 * @param array $options The array the suite's `get_option()` reads.
 */
function wpcpm_seed_compiled_options( array &$options ) {
	$compiled = wpcpm_compiled_seeds();

	$options[ WPCPM_Tracks::OPT_TRACKS ] = $compiled['rows'];

	foreach ( $compiled['fields'] as $key => $fields ) {
		$options[ WPCPM_Tracks::OPT_FIELDS_PREFIX . $key ] = $fields;
	}

	WPCPM_Tracks::flush();
}
