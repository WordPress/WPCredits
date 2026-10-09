<?php
/**
 * The "Viewing as" switcher every dashboard draws for Administrators (`WPCPM_Dashboards::render_switcher()`).
 *
 * The institution, mentor, student and sponsor dashboards each carried a copy of the same form, and
 * each listed its entries in whatever order its source gave them: the institutions and the sponsors
 * in Airtable's order, which is no order a reader can search by eye in a list of about a hundred.
 * What each block pins, and why:
 *
 * - The list is A to Z as a reader expects names: without regard to accents, through
 *   `remove_accents()` (Álvaro among the A's, Łódź among the L's), nor to case in any script, through
 *   `mb_strtolower()` (Анна beside анна, where `strnatcasecmp()` alone folds only Latin capitals), and
 *   numbers as numbers (Student 2 before Student 10), the way `strnatcasecmp()` reads them, with the
 *   space between two words before any letter (Teo Polytechnic before Teodora School), where
 *   `strnatcasecmp()` alone skips white space. Two names alike in that reading keep one order
 *   whatever order they came in, so the list does not reshuffle between two reads.
 * - That order is one public comparison of two names, `compare_names()`, which the switcher's sort
 *   calls and by which the reconciliation lists its status disagreements, so there is one name order.
 * - The entry being viewed is selected, and only that one, after the sort as before it.
 * - The box that narrows the list sits above it inside the same form. Inside, because the WordPress
 *   Credits theme lifts the form out of the dashboard card by its opening tag and would leave a box
 *   outside it behind; the opening tag is pinned as the theme's filter reads it. Labeled, and posting
 *   nothing: it carries no name, so the GET form sends what it sent before. Drawn hidden, so a page
 *   without the script never offers a box that does nothing, and the stylesheet's display rule for
 *   the row does not undo the hidden attribute.
 * - The box and the list share one column, with the labels in the column before it, so the box
 *   starts where the list starts and is as wide as it is, whatever either label says; on a phone the
 *   column is the only one. Show and the note follow the list on its row.
 * - The note is the helper's own, "Only Administrators see this control.", the name the program
 *   gives its managers, and no dashboard passes or prints another.
 * - The script is registered and enqueued by the helper and only when it draws a switcher: a list of
 *   one entry is not a choice and draws nothing, and loads nothing.
 * - The four dashboards draw their switcher through the helper and print no select of their own,
 *   and no other file prints the switcher's form, so the four cannot drift apart again.
 * - The script, run by node (bin/js/switcher-filter.js): `fold()` and `narrow()` on their own, and
 *   the whole script on a stand-in select, typed into, chosen from, cleared with Escape, with the list
 *   it leaves checked after every step, its width held while a search narrows it and measured again
 *   when the window changes size, its no-match sentence set only when it changes, and three
 *   mutation proofs. Read off its source as well: options taken out of the list rather than hidden,
 *   which Safari ignores in a closed select; the no-match sentence from the markup, set only when
 *   what the line says changes; nothing in the script submitting the form; and the letters outside
 *   ASCII written as escapes, each named in a comment.
 *
 * Run from the plugin root:  php bin/test-dashboard-switcher.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

$GLOBALS['opts']       = array( 'permalink_structure' => '/%postname%/' );
$GLOBALS['queried']    = 0;
$GLOBALS['registered'] = array();
$GLOBALS['enqueued']   = array();

function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return esc_html( $s ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function get_queried_object_id() { return (int) $GLOBALS['queried']; }
// Core's, as `selected()` writes it: a string comparison, so 7 and '7' are one value.
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
	if ( $echo ) { echo $out; }
	return $out;
}
function wp_register_script( $h, $src, $deps = array(), $ver = false, $footer = false ) {
	$GLOBALS['registered'][ $h ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'footer' => $footer );
}
function wp_script_is( $h, $list = 'enqueued' ) {
	return 'registered' === $list ? isset( $GLOBALS['registered'][ $h ] ) : in_array( $h, $GLOBALS['enqueued'], true );
}
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
// Core's `remove_accents()` as far as the fixtures' names reach it, Ł to L among them, which
// Unicode's decomposition leaves as it is.
function remove_accents( $text, $locale = '' ) {
	return strtr(
		(string) $text,
		array( 'Á' => 'A', 'á' => 'a', 'É' => 'E', 'é' => 'e', 'Ł' => 'L', 'ł' => 'l', 'ó' => 'o', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z' )
	);
}

$fail = 0;
function ck( $l, $a, $e = true ) {
	global $fail; $ok = $a === $e; if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $l . "\n";
	if ( ! $ok ) { echo "       exp: " . var_export( $e, true ) . "  got: " . var_export( $a, true ) . "\n"; }
}

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';

/**
 * Draw one switcher and hand back its markup.
 *
 * @param array $options Value to label, in the order the caller has them.
 * @param mixed $current The value being viewed.
 * @param array $more    Arguments to add, or to put in place of the ones above.
 * @return string
 */
function draw( array $options, $current = '', array $more = array() ) {
	ob_start();

	WPCPM_Dashboards::render_switcher(
		array_merge(
			array(
				'id'      => 'wpcpm-test-switcher',
				'name'    => 'wpcpm_test_view',
				'options' => $options,
				'current' => $current,
				'label'   => 'Viewing as test',
				'find'    => 'Find a test entry',
				'none'    => 'No entries match that search.',
			),
			$more
		)
	);

	return (string) ob_get_clean();
}

/**
 * The options a switcher drew, in the order drawn.
 *
 * @param string $html The markup.
 * @return array[] Each `value`, `label` and `selected`.
 */
function options_of( $html ) {
	preg_match_all( '#<option value="([^"]*)"( selected=\'selected\')?>([^<]*)</option>#', $html, $found, PREG_SET_ORDER );

	return array_map(
		function ( $match ) {
			return array( 'value' => $match[1], 'label' => html_entity_decode( $match[3], ENT_QUOTES ), 'selected' => '' !== $match[2] );
		},
		$found
	);
}

function labels_of( $html ) {
	return array_column( options_of( $html ), 'label' );
}

$exists = method_exists( 'WPCPM_Dashboards', 'render_switcher' );

ck( 'WPCPM_Dashboards draws the switcher', $exists );

if ( ! $exists ) {
	echo "\n1 FAILURE(S): nothing else here can run without the helper\n";
	exit( 1 );
}

echo "\n=== The order ===\n";

// The institutions' shape: record IDs to names, in the index's order, which is Airtable's.
$index = array(
	'recZOE0000000001' => 'Zoe Academy',
	'recSTUDENT000010' => 'Student 10',
	'recALVARO0000001' => 'Álvaro University',
	'recLODZ000000001' => 'łódź Institute',
	'recSTUDENT000002' => 'Student 2',
	'recBERGEN0000001' => 'bergen school',
	'recECOLE00000001' => 'École 42',
);

$html = draw( $index, 'recSTUDENT000010' );

ck(
	'A to Z as a reader expects names, whatever order the index holds them in',
	labels_of( $html ),
	array( 'Álvaro University', 'bergen school', 'École 42', 'łódź Institute', 'Student 2', 'Student 10', 'Zoe Academy' )
);
ck( 'Álvaro before Zoe: the accent is read through remove_accents(), not as a byte after Z', array_search( 'Álvaro University', labels_of( $html ), true ) < array_search( 'Zoe Academy', labels_of( $html ), true ) );
ck( 'Student 2 before Student 10: numbers as numbers', array_search( 'Student 2', labels_of( $html ), true ) < array_search( 'Student 10', labels_of( $html ), true ) );
ck( 'and each label keeps the value it came with', array_column( options_of( $html ), 'value' ), array( 'recALVARO0000001', 'recBERGEN0000001', 'recECOLE00000001', 'recLODZ000000001', 'recSTUDENT000002', 'recSTUDENT000010', 'recZOE0000000001' ) );

// The mentors' and the students' shape: user IDs to display names.
$people = draw( array( 12 => 'Zed Mentor', 7 => 'Ana Mentor', 30 => 'ana mentor', 4 => 'Ana Mentor' ), 7 );
$again  = draw( array( 4 => 'Ana Mentor', 30 => 'ana mentor', 7 => 'Ana Mentor', 12 => 'Zed Mentor' ), 7 );

ck( 'user IDs keep their names through the sort', array_column( options_of( $people ), 'label' ), array( 'Ana Mentor', 'Ana Mentor', 'ana mentor', 'Zed Mentor' ) );
ck( 'two names alike are settled by their value, as numbers', array_column( options_of( $people ), 'value' ), array( '4', '7', '30', '12' ) );
ck( 'so the list reads the same whatever order it came in', options_of( $again ), options_of( $people ) );

// Cyrillic, whose capitals `strnatcasecmp()` does not fold: read byte by byte, every capital comes
// before every small letter, and Анна and анна land three names apart.
$cyrillic = draw( array( 30 => 'борис', 7 => 'Вера', 12 => 'Анна', 4 => 'Борис', 9 => 'анна' ) );

ck( 'a name in another script is grouped without regard to case, as mb_strtolower() reads it', array_column( options_of( $cyrillic ), 'value' ), array( '9', '12', '4', '30', '7' ) );

echo "\n=== One name order, shared ===\n";

// The switchers' order is the plugin's one name order, so the reconciliation's list of status
// disagreements is sorted by it too: a public comparison of two names, which the switcher's own
// sort calls rather than keeping a copy.
$shared = method_exists( 'WPCPM_Dashboards', 'compare_names' );

if ( $shared ) {
	$compare = new ReflectionMethod( 'WPCPM_Dashboards', 'compare_names' );
	$shared  = $compare->isPublic() && $compare->isStatic();
}

ck( 'the name order is a public static method other code can call', $shared );

if ( $shared ) {
	$sign = static function ( $a, $b ) {
		return WPCPM_Dashboards::compare_names( $a, $b ) <=> 0;
	};

	ck(
		'it reads accents away, case in any script and numbers as numbers',
		array( $sign( 'Álvaro', 'Beth' ), $sign( 'Łukasz', 'Zoe' ), $sign( 'Зоя', 'анна' ), $sign( 'Student 2', 'Student 10' ), $sign( 'студент 2', 'Студент 10' ) ),
		array( -1, -1, 1, -1, -1 )
	);
	ck( 'and two names alike in that reading compare as one, for the caller to settle', array( $sign( 'Анна', 'анна' ), $sign( 'Álvaro', 'alvaro' ) ), array( 0, 0 ) );

	// The space between two words comes before any letter or digit, as in a dictionary.
	// `strnatcasecmp()` alone skips white space, so it read Teo Polytechnic as TeoPolytechnic and put
	// it after Teodora School: each of the first three pairs below is one that reading turns round.
	ck(
		'a space between words comes before any letter, so a shorter first word comes first',
		array( $sign( 'Teo Polytechnic', 'Teodora School' ), $sign( 'Institut Sud', 'Instituto Este' ), $sign( 'D Y School', 'Duo Academy' ), $sign( 'Student 2', 'Student2' ) ),
		array( -1, -1, -1, -1 )
	);
	ck(
		'a run of spaces, a non-breaking space and spaces at the ends read as one space or none',
		array( $sign( 'Ana  Maria', 'Ana Maria' ), $sign( "Ana\u{00A0}Maria", 'Ana Maria' ), $sign( ' Ana Maria ', 'Ana Maria' ), $sign( 'Student 9', 'Student 10' ) ),
		array( 0, 0, 0, -1 )
	);
	ck(
		'a run that mixes spaces, a non-breaking space, a tab and a line break reads as one space, and so does one at either end',
		array( $sign( "Ana \u{00A0}\tMaria", 'Ana Maria' ), $sign( "Ana\n\nMaria", 'Ana Maria' ), $sign( "\u{00A0}Ana Maria\t", 'Ana Maria' ) ),
		array( 0, 0, 0 )
	);
	ck( 'a name of nothing but white space reads as no name, and comes before any name', array( $sign( '   ', '' ), $sign( " \u{00A0} ", 'Ann' ) ), array( 0, -1 ) );
	// The ends are trimmed by character, not by byte: 倠 ends on the byte a non-breaking space ends
	// on, and a trim by bytes cut it in half and lost the space before it.
	ck( 'a name that ends on a character sharing a byte with the non-breaking space keeps its order', $sign( 'Ann 倠', 'Anna' ), -1 );
}

$helper_source = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php' );
$sort_body     = preg_match( '/function sort_switcher_options\(.*?\n\t}\n/s', $helper_source, $sort_found ) ? $sort_found[0] : '';

ck( 'the switcher sorts its list with that comparison, so the two cannot drift apart', false !== strpos( $sort_body, 'self::compare_names(' ) );

echo "\n=== The current choice ===\n";

ck( 'the entry being viewed is selected after the sort', array_column( array_filter( options_of( $html ), function ( $o ) { return $o['selected']; } ), 'value' ), array( 'recSTUDENT000010' ) );
ck( 'and only that one', substr_count( $html, 'selected=' ), 1 );
ck( 'a user ID matches the ID it was given, as a number or as a string', array( substr_count( $people, "value=\"7\" selected='selected'" ), substr_count( draw( array( 12 => 'Zed', 7 => 'Ana' ), '7' ), "value=\"7\" selected='selected'" ) ), array( 1, 1 ) );

echo "\n=== The markup ===\n";

ck( 'the form opens exactly as the theme\'s filter finds it, to lift it above the card', 0 === strpos( $html, '<form class="wpcpm-dashboard__switcher" method="get">' ) );
ck( 'and closes once, with no form inside it', array( substr_count( $html, '<form' ), substr_count( $html, '</form>' ) ), array( 1, 1 ) );
ck( 'the box is a row of its own, drawn hidden', false !== strpos( $html, '<div class="wpcpm-dashboard__switcher-find" hidden>' ) );
ck( 'with a visible label in the caller\'s words', false !== strpos( $html, '<label for="wpcpm-test-switcher-find">Find a test entry</label>' ) );
ck( 'on a search box that names the list it narrows', false !== strpos( $html, '<input type="search" id="wpcpm-test-switcher-find" aria-controls="wpcpm-test-switcher" autocomplete="off" />' ) );
preg_match( '#<input type="search"[^>]*>#', $html, $box );
ck( 'and carries no name, so the GET form posts what it posted before', isset( $box[0] ) && false === strpos( $box[0], 'name=' ) );
ck( 'the no-match sentence waits in a status region, for the script to say', false !== strpos( $html, '<span class="wpcpm-dashboard__switcher-none" role="status" data-wpcpm-none="No entries match that search."></span>' ) );
ck( 'the box comes above the list', false !== strpos( $html, 'wpcpm-dashboard__switcher-find' ) && strpos( $html, 'wpcpm-dashboard__switcher-find' ) < strpos( $html, '<select' ) );
ck( 'and inside the form', strpos( $html, 'wpcpm-dashboard__switcher-find' ) > strpos( $html, '<form' ) && strpos( $html, 'wpcpm-dashboard__switcher-find' ) < strpos( $html, '</form>' ) );
ck( 'the list keeps its label, its field and its ID', array( false !== strpos( $html, '<label for="wpcpm-test-switcher">Viewing as test</label>' ), false !== strpos( $html, '<select name="wpcpm_test_view" id="wpcpm-test-switcher">' ) ), array( true, true ) );
ck( 'and the Show button and the note after it', array( false !== strpos( $html, '<button type="submit" class="wpcpm-button">Show</button>' ), false !== strpos( $html, '<span class="wpcpm-dashboard__switcher-note">Only Administrators see this control.</span>' ) ), array( true, true ) );

$fields = strpos( $html, '<div class="wpcpm-dashboard__switcher-fields">' );

ck( 'the box, the list, Show and the note are one block of fields, the form\'s first', '<form class="wpcpm-dashboard__switcher" method="get"><div class="wpcpm-dashboard__switcher-fields">', substr( $html, 0, (int) $fields + strlen( '<div class="wpcpm-dashboard__switcher-fields">' ) ) );
ck(
	'in reading order: the box\'s row, the list\'s label, the list, Show, the note',
	array(
		$fields < strpos( $html, '<div class="wpcpm-dashboard__switcher-find" hidden>' ),
		strpos( $html, '<div class="wpcpm-dashboard__switcher-find" hidden>' ) < strpos( $html, '<label for="wpcpm-test-switcher">' ),
		strpos( $html, '<label for="wpcpm-test-switcher">' ) < strpos( $html, '<select' ),
		strpos( $html, '</select>' ) < strpos( $html, '<button' ),
		strpos( $html, '</button>' ) < strpos( $html, 'wpcpm-dashboard__switcher-note' ),
	),
	array( true, true, true, true, true )
);
ck( 'and the block closes with the form', '</span></div></form>', substr( $html, -strlen( '</span></div></form>' ) ) );
$passed = draw( array( 'rec1' => 'A', 'rec2' => 'B' ), '', array( 'note' => 'Only editors see this control.' ) );

ck( 'the note is the helper\'s: one a caller passes is not printed', array( false !== strpos( $passed, 'Only Administrators see this control.' ), strpos( $passed, 'Only editors' ) ), array( true, false ) );
ck( 'with pretty permalinks the page needs no ID', false !== strpos( $html, 'name="page_id"' ), false );

unset( $GLOBALS['opts']['permalink_structure'] );
$GLOBALS['queried'] = 42;
ck( 'without them the form carries the page ID, which a GET form would otherwise drop', false !== strpos( draw( $index ), '<input type="hidden" name="page_id" value="42" />' ) );
$GLOBALS['opts']['permalink_structure'] = '/%postname%/';
$GLOBALS['queried']                     = 0;

ck( 'a name is escaped in the list', false !== strpos( draw( array( 'rec1' => 'A & B <School>', 'rec2' => 'C' ) ), '>A &amp; B &lt;School&gt;</option>' ) );

echo "\n=== The script ===\n";

$GLOBALS['registered'] = array();
$GLOBALS['enqueued']   = array();
$one                   = draw( array( 'recONLY000000001' => 'Only One' ), 'recONLY000000001' );

ck( 'one entry is not a choice: nothing is drawn', $one, '' );
ck( 'and nothing is loaded', $GLOBALS['enqueued'], array() );

draw( $index );

ck( 'a switcher drawn loads the script that narrows it', $GLOBALS['enqueued'], array( WPCPM_Dashboards::SWITCHER_SCRIPT ) );
ck(
	'registered from assets/js/switcher.js, in the footer, with no dependency',
	$GLOBALS['registered'][ WPCPM_Dashboards::SWITCHER_SCRIPT ] ?? null,
	array( 'src' => WPCPM_PLUGIN_URL . 'assets/js/switcher.js', 'deps' => array(), 'ver' => WPCPM_VERSION, 'footer' => true )
);
ck( 'and the file is there', is_file( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ) );

$css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/dashboard.css' );

// The second column's least is `auto`, the box's and the list's own minimums, so the width
// switcher.js holds them at keeps the column, and Show beside it, where they were; without the
// script that least is only the theme's.
ck( 'the fields are a grid across the switcher: labels, the box and the list, Show, the note', 1 === preg_match( '/\.wpcpm-dashboard__switcher-fields\s*\{[^}]*display:\s*grid;[^}]*flex:\s*1 1 100%;[^}]*grid-template-columns:\s*max-content minmax\( auto, max-content \) max-content minmax\( max-content, 1fr \);/', $css ) );
ck( 'the box\'s row lays its parts into that grid', 1 === preg_match( '/\.wpcpm-dashboard__switcher-find\s*\{\s*display:\s*contents;\s*\}/', $css ) );
ck( 'and the hidden attribute keeps the display that rule takes from it', 1 === preg_match( '/\.wpcpm-dashboard__switcher-find\[hidden\]\s*\{\s*display:\s*none;\s*\}/', $css ) );
ck( 'both labels in the first column', 1 === preg_match( '/\.wpcpm-dashboard__switcher-fields label\s*\{\s*grid-column:\s*1;\s*\}/', $css ) );
ck( 'the box and the list in the second, each as wide as the column, borders included', 1 === preg_match( '/\.wpcpm-dashboard__switcher-fields input,\s*\.wpcpm-dashboard__switcher-fields select\s*\{[^}]*box-sizing:\s*border-box;[^}]*grid-column:\s*2;[^}]*width:\s*100%;/', $css ) );
// From 601 to 900px the note leaves the fourth column, which keeps almost no floor; a filled
// no-match line still spanning into it forced it open and ran past the switcher, and the page
// scrolled sideways. There the line takes a row of its own under the box, as the note does.
ck( 'the note is never squeezed to wrap beside Show: at 900px and below it takes a row of its own, under the list, and so does the no-match line', 1 === preg_match( '/@media \( max-width: 900px \)\s*\{\s*\.wpcpm-dashboard__switcher-note,\s*\.wpcpm-dashboard__switcher-none\s*\{\s*grid-column:\s*2 \/ -1;\s*\}\s*\}/', $css ) );
ck( 'and the line never sizes a column at any width: it wraps in the room its row gives it', 1 === preg_match( '/\.wpcpm-dashboard__switcher-none\s*\{[^}]*contain:\s*inline-size;/', $css ) );
ck( 'and on a phone the grid is one column, the labels above the box and the list', 1 === preg_match( '/@media \( max-width: 600px \)\s*\{\s*\.wpcpm-dashboard__switcher-fields\s*\{\s*grid-template-columns:\s*minmax\( 0, 1fr \);\s*\}/', $css ) );
// Empty, the no-match line kept a grid row of its own on a phone, and the two gaps around it set
// the box 14px from the list's label where every other gap is 7px. Out of the grid while it is
// empty, and never `display: none` nor hidden, since a status region that is not in the page when
// its text arrives is not read out.
ck( 'the no-match line takes no room while it is empty: out of the grid, still in the page', 1 === preg_match( '/\.wpcpm-dashboard__switcher-none:empty\s*\{\s*position:\s*absolute;\s*\}/', $css ) );
ck( 'and nothing hides it', array( preg_match( '/\.wpcpm-dashboard__switcher-none[^{]*\{[^}]*display:\s*none/', $css ), strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ), 'status.hidden' ) ), array( 0, false ) );

echo "\n=== The four dashboards ===\n";

/**
 * One static method's body, out of a class file.
 *
 * @param string $file   The file, from the plugin root.
 * @param string $method The method's name.
 * @return string From its signature to its closing brace; '' when there is no such method.
 */
function method_body( $file, $method ) {
	$src = (string) file_get_contents( WPCPM_PLUGIN_DIR . $file );

	return preg_match( '/\n\t(?:private|public|protected) static function ' . preg_quote( $method, '/' ) . '\(.*?\n\t\}\n/s', $src, $found ) ? $found[0] : '';
}

$dashboards = array(
	'institution' => array( 'includes/modules/class-wpcpm-institutions-dashboard.php', 'render_switcher', 'Find an institution', 'No institutions match that search.' ),
	'mentor'      => array( 'includes/modules/class-wpcpm-mentors-dashboard.php', 'render_mentor_switcher', 'Find a mentor', 'No mentors match that search.' ),
	'student'     => array( 'includes/modules/class-wpcpm-students-dashboard.php', 'render_switcher', 'Find a student', 'No students match that search.' ),
	'sponsor'     => array( 'includes/modules/class-wpcpm-sponsors-dashboard.php', 'render_switcher', 'Find a sponsor', 'No sponsors match that search.' ),
);

foreach ( $dashboards as $who => $where ) {
	$body = method_body( $where[0], $where[1] );

	ck( "the $who switcher is drawn by the shared helper", '' !== $body && false !== strpos( $body, 'WPCPM_Dashboards::render_switcher(' ) );
	ck( "and prints no list of its own", array( strpos( $body, '<select' ), strpos( $body, '<option' ), strpos( $body, '<form' ) ), array( false, false, false ) );
	ck( "and names its box and its no-match sentence", array( false !== strpos( $body, "'" . $where[2] . "'" ), false !== strpos( $body, "'" . $where[3] . "'" ) ), array( true, true ) );
	ck( "and leaves the note to the helper", strpos( $body, "'note'" ), false );
}

// The two notes the dashboards used to print, "program managers" on two and "administrators" on two.
$old_notes = array();

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . 'includes', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	$src = 'php' === $file->getExtension() ? (string) file_get_contents( $file->getPathname() ) : '';

	if ( false !== strpos( $src, 'Only program managers see this control.' ) || false !== strpos( $src, 'Only administrators see this control.' ) ) {
		$old_notes[] = substr( $file->getPathname(), strlen( WPCPM_PLUGIN_DIR ) );
	}
}

ck( 'no file says either of the old notes', $old_notes, array() );

// Every file the plugin ships, read for the form's opening tag: one place prints it.
$printers = array();
$rii      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . 'includes', FilesystemIterator::SKIP_DOTS ) );

foreach ( $rii as $file ) {
	if ( 'php' === $file->getExtension() && false !== strpos( (string) file_get_contents( $file->getPathname() ), '<form class="wpcpm-dashboard__switcher"' ) ) {
		$printers[] = substr( $file->getPathname(), strlen( WPCPM_PLUGIN_DIR ) );
	}
}

ck( 'one file prints the switcher\'s form, so the four cannot drift apart', $printers, array( 'includes/class-wpcpm-dashboards.php' ) );

echo "\n=== The filter, run by node ===\n";

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip the filter was not run: node is not on the path\n";
} else {
	$out    = array();
	$status = 0;

	exec( escapeshellarg( $node ) . ' ' . escapeshellarg( WPCPM_PLUGIN_DIR . 'bin/js/switcher-filter.js' ) . ' ' . escapeshellarg( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ) . ' 2>&1', $out, $status );

	// As bin/test-forms-js.php reads its harness: each scenario line is this suite's own, the
	// summary is checked against the lines shown, and anything else is shown as it came.
	$ran     = 0;
	$differ  = 0;
	$summary = null;

	foreach ( $out as $line ) {
		if ( 0 === strpos( $line, 'ok   ' ) ) {
			++$ran;
			echo $line . "\n";
		} elseif ( 0 === strpos( $line, 'FAIL ' ) ) {
			++$ran;
			++$differ;
			++$fail;
			echo $line . "\n";
		} elseif ( 0 === strpos( $line, '       ' ) ) {
			echo $line . "\n";
		} elseif ( '' !== trim( $line ) ) {
			if ( preg_match( '/^(\d+) scenarios, (\d+) differ$/', trim( $line ), $counts ) ) {
				$summary = array( (int) $counts[1], (int) $counts[2] );
			}

			echo '     ' . $line . "\n";
		}
	}

	ck( 'the filter ran scenarios', $ran > 0 );
	ck( 'and its own summary counts the scenarios and the failures shown above', $summary, array( $ran, $differ ) );
	ck( 'and node ended with a status of 0, or of 1 when a scenario differed', $status, $differ ? 1 : 0 );
}

echo "\n=== What the script does with the answer ===\n";

$js = is_file( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ) ? (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ) : '';

// The code without its comments, which name `remove_accents()` in backticks as the other scripts' do.
$code = (string) preg_replace( array( '#/\*.*?\*/#s', '#(^|\s)//[^\n]*#' ), array( '', '$1' ), $js );

ck( 'plain ES5, as the other scripts: no arrow, no const or let, no template string', array( '' !== $code, strpos( $code, '=>' ), preg_match( '/\b(?:const|let)\s/', $code ), strpos( $code, '`' ) ), array( true, false, 0, false ) );
ck( 'it finds each box by its row and the list by the box\'s aria-controls', array( false !== strpos( $js, "'.wpcpm-dashboard__switcher-find'" ), false !== strpos( $js, "getAttribute( 'aria-controls' )" ) ), array( true, true ) );
ck( 'it shows the box it can work', false !== strpos( $js, 'row.hidden = false;' ) );
ck( 'it asks narrow() with the entry chosen right now, so that one stays', false !== strpos( $js, 'narrow( keys, entries.indexOf( select.options[ select.selectedIndex ] ), input.value )' ) );
ck( 'it takes an entry out of the list rather than hiding it, and puts it back in its place', array( false !== strpos( $js, 'select.removeChild( entry )' ), false !== strpos( $js, 'select.insertBefore( entry, ' ) ), array( true, true ) );
ck( 'the no-match sentence is the markup\'s, said when nothing matched', false !== strpos( $js, "var text = 0 === result.matched ? status.getAttribute( 'data-wpcpm-none' ) : '';" ) );
ck( 'and set only when what the line says changes, so a screen reader does not say it again', 1 === preg_match( '/if \( status\.textContent !== text \) \{\s*status\.textContent = text;\s*\}/', $js ) );
ck( 'it narrows as the person types', false !== strpos( $js, "input.addEventListener( 'input', apply )" ) );
ck( 'Escape clears the box and puts the whole list back', 1 === preg_match( "/'Escape' === event\.key[^}]*input\.value = '';\s*apply\(\);/s", $js ) );
ck( 'Enter in the box submits nothing: Show and the list work as they did', 1 === preg_match( "/'Enter' === event\.key[^}]*event\.preventDefault\(\);/s", $js ) );
ck( 'and nothing in the script submits the form', array( strpos( $js, '.submit(' ), strpos( $js, 'requestSubmit' ) ), array( false, false ) );
ck( 'the combining marks are a range of escapes', false !== strpos( $js, '.replace( /[' . chr( 92 ) . 'u0300-' . chr( 92 ) . 'u036f]/g, \'\' )' ) );

// Each letter Unicode does not decompose, by its code point, with what it is read as and the letter
// itself in the comment beside it.
$own = array(
	'0142' => 'l',
	'00f8' => 'o',
	'0111' => 'd',
	'00f0' => 'd',
	'0127' => 'h',
	'0131' => 'i',
	'00df' => 'ss',
	'00e6' => 'ae',
	'0153' => 'oe',
	'00fe' => 'th',
);
$written = array();

foreach ( $own as $point => $plain ) {
	$written[] = false !== strpos( $js, "'" . chr( 92 ) . 'u' . $point . "': '" . $plain . "', // " . html_entity_decode( '&#x' . $point . ';', ENT_QUOTES, 'UTF-8' ) );
}

ck( 'the letters of their own are escapes, each named in a comment', $written, array_fill( 0, count( $own ), true ) );
ck( 'so the code itself is ASCII, and only its comments name a letter outside it', preg_match( '/[^\x00-\x7F]/', $code ), 0 );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );
exit( $fail ? 1 : 0 );
