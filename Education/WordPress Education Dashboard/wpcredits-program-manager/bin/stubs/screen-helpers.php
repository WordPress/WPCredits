<?php
/**
 * How the suites that draw an audience's accounts screen run a step and read what it drew.
 *
 * bin/test-students-screen.php, bin/test-mentors-screen.php and bin/test-administrators-screen.php
 * each carried a copy of these, the same to the byte, so a change in how core draws a list (a cell's
 * markup, a view's count, a sort link) that broke one reading would have had to be mended in three
 * places. What they are: running a step and keeping what came of it, and the request it runs for
 * (`run()`, `got()`, `request()`, `html_of()`); reading a drawn list (`between()`, `cell()`,
 * `actions_in()`, `link_of()`, `view_counts()`, `displaying()`, `sort_of()`); and reading the queries
 * a list made (`first_query()`, `arg()`), through the `queries()` each suite keeps, since what a
 * query was for is the audience's to say. Each suite keeps its own fixtures, its own readings of its
 * own screen (its query, its drawing, its heading) and its checks.
 *
 * A redirect and a death are the stand-ins' own exceptions (bin/stubs/accounts-screen.php), which a
 * suite requires before this file.
 *
 * Loaded with `require_once __DIR__ . '/stubs/screen-helpers.php';` from a suite's header.
 */

/**
 * Run a step and keep what came of it: what it returned, where it redirected, why it died, or what
 * it threw when the code it reaches is not there, so a check against a missing piece fails as a
 * check instead of ending the run.
 *
 * @param callable $step The step.
 * @return array{value: mixed, redirect: string, died: string, error: string}
 */
function run( callable $step ) {
	$out = array(
		'value'    => null,
		'redirect' => '',
		'died'     => '',
		'error'    => '',
	);

	try {
		$out['value'] = $step();
	} catch ( RedirectSignal $signal ) {
		$out['redirect'] = $signal->getMessage();
	} catch ( DieSignal $signal ) {
		$out['died'] = $signal->getMessage();
	} catch ( Throwable $thrown ) {
		$out['error'] = get_class( $thrown ) . ': ' . $thrown->getMessage();
	}

	return $out;
}

/**
 * What a check reads off a step, or what the step threw, so a failure says which.
 *
 * @param array    $out  What run() kept.
 * @param callable $pick What to read off it.
 * @return mixed
 */
function got( array $out, callable $pick ) {
	return '' !== $out['error'] ? 'threw ' . $out['error'] : $pick( $out );
}

/**
 * The request a screen is drawn for, or a press arrives with.
 *
 * @param array $query The query string.
 * @param array $post  The posted fields.
 */
function request( array $query, array $post = array() ) {
	$_GET                   = $query;
	$_POST                  = $post;
	$_REQUEST               = array_merge( $query, $post );
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php' . ( empty( $query ) ? '' : '?' . http_build_query( $query ) );
}

/**
 * The markup a step drew: its value when that is a string, and nothing otherwise.
 *
 * @param array $out What run() kept.
 * @return string
 */
function html_of( array $out ) {
	return is_string( $out['value'] ) ? $out['value'] : '';
}

/**
 * The part of the markup from one string to the next, the whole rest when the second is not there,
 * and nothing when the first is not.
 *
 * @param string $html  Markup.
 * @param string $start Where it starts.
 * @param string $end   Where it ends.
 * @return string
 */
function between( $html, $start, $end ) {
	$from = strpos( (string) $html, $start );

	if ( false === $from ) {
		return '';
	}

	$to = strpos( $html, $end, $from );

	return false === $to ? substr( $html, $from ) : substr( $html, $from, $to - $from );
}

/**
 * What one cell of a row holds, the row actions under the primary cell left out.
 *
 * @param string $row    A row's markup.
 * @param string $column The column.
 * @return string|null
 */
function cell( $row, $column ) {
	if ( ! preg_match( "/<t([hd]) class='" . preg_quote( $column, '/' ) . ' column-' . preg_quote( $column, '/' ) . "[^']*'[^>]*>(.*?)<\/t\\1>/s", (string) $row, $found ) ) {
		return null;
	}

	$value = strstr( $found[2], '<div class="row-actions">', true );

	return false === $value ? $found[2] : $value;
}

/**
 * The row actions a row draws, action => link, in order.
 *
 * @param string $row A row's markup.
 * @return array<string, string>
 */
function actions_in( $row ) {
	preg_match_all( "/<span class='([a-z]+)'>(.*?)(?: \| )?<\/span>/", between( $row, '<div class="row-actions">', '</div>' ), $found, PREG_SET_ORDER );

	$actions = array();
	foreach ( $found as $one ) {
		$actions[ $one[1] ] = $one[2];
	}

	return $actions;
}

/**
 * A link's address and its query arguments, entities decoded.
 *
 * @param string $html Markup holding one link, or an address.
 * @return array{0: string, 1: array}
 */
function link_of( $html ) {
	$url = preg_match( '/href=["\']([^"\']*)["\']/', (string) $html, $found ) ? $found[1] : (string) $html;
	$url = html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );

	$parts = explode( '?', $url, 2 );
	$query = array();

	if ( isset( $parts[1] ) ) {
		parse_str( $parts[1], $query );
	}

	return array( $parts[0], $query );
}

/**
 * The views a drawn list prints, view => its count.
 *
 * @param string $html Markup.
 * @return array<string, string>
 */
function view_counts( $html ) {
	preg_match_all( "/<li class='([a-z-]+)'><a [^>]*>[^<]*<span class=\"count\">\(([^)]*)\)<\/span>/", (string) $html, $found, PREG_SET_ORDER );

	$counts = array();
	foreach ( $found as $one ) {
		$counts[ $one[1] ] = $one[2];
	}

	return $counts;
}

/**
 * What the pagination above a drawn list says it holds, such as "3 items", or null for no pagination.
 *
 * @param string $html Markup.
 * @return string|null
 */
function displaying( $html ) {
	return preg_match( '/<span class="displaying-num">([^<]*)<\/span>/', (string) $html, $found ) ? $found[1] : null;
}

/**
 * The first query a drawn list made for one purpose, by the suite's own `queries()`, or nothing.
 *
 * @param string $kind What the query was for, as the suite's `queries()` names it.
 * @return array
 */
function first_query( $kind ) {
	$found = queries( $kind );

	return isset( $found[0] ) ? $found[0] : array();
}

/**
 * One argument of a query, or `absent`, so a check tells an argument left out from one set empty.
 *
 * @param array  $args The query's arguments.
 * @param string $key  The argument.
 * @return mixed
 */
function arg( array $args, $key ) {
	return array_key_exists( $key, $args ) ? $args[ $key ] : 'absent';
}

/**
 * The sort link a drawn list's column header carries: its `orderby`, or `not sortable`.
 *
 * @param string $html   Markup.
 * @param string $column The column.
 * @return string
 */
function sort_of( $html, $column ) {
	return preg_match( "/<th scope=\"col\" id='" . preg_quote( $column, '/' ) . "'[^>]*><a href=\"([^\"]*)\"/", (string) $html, $found ) ? arg( link_of( $found[1] )[1], 'orderby' ) : 'not sortable';
}
