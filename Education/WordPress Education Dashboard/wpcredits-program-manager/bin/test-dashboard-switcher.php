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
 * - The one field that stands in for the list sits beside it inside the same form. Inside, because
 *   the WordPress Credits theme lifts the form out of the dashboard card by its opening tag and
 *   would leave a field outside it behind; the opening tag is pinned as the theme's filter reads it.
 *   A text field in the combobox role, with the ARIA the pattern asks for, an empty listbox labeled
 *   by the switcher's label and a status line for the count and the no-match sentence, all drawn
 *   hidden in one block, so a page without the script shows the label, the sorted list, Show and
 *   the note and nothing that does nothing. The caller's find sentence is the placeholder. The
 *   field posts nothing: it carries no name, and the list stays in the form as what is sent. The
 *   separate search box of 1.122.14 is gone.
 * - The select and the field share the grid's second column, one at a time, after the label in the
 *   first, so the field starts where the select starts; Show and the note follow on the same row,
 *   the note under the field at 900px and below, and on a phone the column is the only one. The
 *   hidden attribute keeps whichever is hidden out of the page, whatever display a theme gives it.
 * - The note is the helper's own, "Only Administrators see this control.", the name the program
 *   gives its managers, and no dashboard passes or prints another.
 * - The script is registered and enqueued by the helper and only when it draws a switcher: a list of
 *   one entry is not a choice and draws nothing, and loads nothing.
 * - The four dashboards draw their switcher through the helper and print no select of their own,
 *   and no other file prints the switcher's form, so the four cannot drift apart again.
 * - The script, run by node (bin/js/switcher-filter.js) on the markup this helper draws, handed
 *   over on stdin: `fold()` and `narrow()` on their own, and the whole script on that markup, opened
 *   by a click and by Down, typed into, moved through with Up, Down, Home and End, picked from with
 *   Enter and with a click, left by Escape, Tab and a click elsewhere, with no match, on a long list
 *   that scrolls, on short names, and as the four dashboards' switchers on one page, each picking
 *   and sending its own; the field held at the width its longest name needs in its own type, never
 *   less than the select's nor more than the room, measured again when the window changes size; the
 *   list held at ten one-line rows; the status line set only when it changes; Enter never
 *   submitting; and three mutation proofs. Read off its source as well: the parts found through
 *   their ARIA, the field
 *   shown and the select hidden, names written as text, the sentences from the markup, picking
 *   setting the select, Enter and a press on the list kept from their defaults, nothing in the
 *   script submitting the form, and the letters outside ASCII written as escapes, each named in a
 *   comment.
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
ck( 'the list keeps its label, now with an ID the field\'s list is named by, its field and its ID', array( false !== strpos( $html, '<label for="wpcpm-test-switcher" id="wpcpm-test-switcher-label">Viewing as test</label>' ), false !== strpos( $html, '<select name="wpcpm_test_view" id="wpcpm-test-switcher" autocomplete="off">' ) ), array( true, true ) );
// A browser that puts a form back as it was left, after Back, would put the hidden list on the name
// picked last while the field reads the page's own, and Show would open somebody else.
ck( 'and asks the browser not to put it back as it was left, so after Back it holds the page\'s own name', 1 === preg_match( '#<select [^>]*autocomplete="off"[^>]*>#', $html ) );
ck( 'and the Show button and the note after it', array( false !== strpos( $html, '<button type="submit" class="wpcpm-button">Show</button>' ), false !== strpos( $html, '<span class="wpcpm-dashboard__switcher-note">Only Administrators see this control.</span>' ) ), array( true, true ) );
ck( 'there is no separate search box any more, nor its label or its row', array( strpos( $html, 'type="search"' ), strpos( $html, 'wpcpm-test-switcher-find' ), strpos( $html, 'wpcpm-dashboard__switcher-find' ), strpos( $html, 'wpcpm-dashboard__switcher-none' ) ), array( false, false, false, false ) );
ck( 'the field that stands in for the list is drawn hidden', false !== strpos( $html, '<div class="wpcpm-dashboard__switcher-combo" hidden>' ) );
ck(
	'a text field in the combobox role, which completes from a list it names, closed, with no autocomplete of the browser\'s, and the caller\'s find sentence as its placeholder',
	false !== strpos( $html, '<input type="text" id="wpcpm-test-switcher-input" class="wpcpm-dashboard__switcher-input" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="wpcpm-test-switcher-list" autocomplete="off" spellcheck="false" placeholder="Find a test entry" />' )
);
preg_match( '#<input type="text"[^>]*>#', $html, $field );
ck( 'and carries no name, so the GET form posts the list\'s field and nothing else', isset( $field[0] ) && false === strpos( $field[0], 'name=' ) );
ck( 'its list is an empty listbox, drawn hidden, labeled by the switcher\'s own label', false !== strpos( $html, '<ul id="wpcpm-test-switcher-list" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="wpcpm-test-switcher-label" hidden></ul>' ) );
ck( 'the count and the no-match sentence wait in a status line, for the script to say', false !== strpos( $html, '<span class="wpcpm-dashboard__switcher-status" role="status" data-wpcpm-count="Names in the list: %s" data-wpcpm-none="No entries match that search."></span>' ) );

$fields = strpos( $html, '<div class="wpcpm-dashboard__switcher-fields">' );
$combo  = strpos( $html, '<div class="wpcpm-dashboard__switcher-combo" hidden>' );
$closed = strpos( $html, '</div>', (int) $combo );

ck( 'the label, the list, the field, Show and the note are one block of fields, the form\'s first', '<form class="wpcpm-dashboard__switcher" method="get"><div class="wpcpm-dashboard__switcher-fields">', substr( $html, 0, (int) $fields + strlen( '<div class="wpcpm-dashboard__switcher-fields">' ) ) );
ck(
	'in reading order: the label, the list, the field, Show, the note',
	array(
		$fields < strpos( $html, '<label for="wpcpm-test-switcher"' ),
		strpos( $html, '<label for="wpcpm-test-switcher"' ) < strpos( $html, '<select' ),
		strpos( $html, '</select>' ) < $combo,
		$closed < strpos( $html, '<button' ),
		strpos( $html, '</button>' ) < strpos( $html, 'wpcpm-dashboard__switcher-note' ),
	),
	array( true, true, true, true, true )
);
ck( 'the hidden block holds the field, its list and its status line, and nothing else', 1 === preg_match( '#<div class="wpcpm-dashboard__switcher-combo" hidden><input type="text" [^>]*/><ul [^>]*></ul><span class="wpcpm-dashboard__switcher-status" [^>]*></span></div> <button#', $html ) );

// Without the script, what shows is what showed before the field: the label, the sorted list, Show
// and the note. Everything else in the block of fields is in the hidden part, and the hidden
// attribute is on the part itself, so no rule for its children can show them.
$visible = preg_replace( '#<div class="wpcpm-dashboard__switcher-combo" hidden>.*?</span></div>#s', '', substr( $html, (int) $fields ) );
preg_match_all( '#<(label|select|button|span|input|ul|div)\b#', (string) $visible, $tags );
ck( 'with no JavaScript only the label, the list, Show and the note are shown', $tags[1], array( 'div', 'label', 'select', 'button', 'span' ) );
ck( 'and the block closes with the form', '</span></div></form>', substr( $html, -strlen( '</span></div></form>' ) ) );
ck( 'the placeholder is escaped', false !== strpos( draw( array( 'rec1' => 'A', 'rec2' => 'B' ), '', array( 'find' => 'Find "one" & more' ) ), 'placeholder="Find &quot;one&quot; &amp; more"' ) );
ck( 'and so is the no-match sentence', false !== strpos( draw( array( 'rec1' => 'A', 'rec2' => 'B' ), '', array( 'none' => 'No <b>match</b> & "none"' ) ), 'data-wpcpm-none="No &lt;b&gt;match&lt;/b&gt; &amp; &quot;none&quot;"' ) );
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

ck( 'a switcher drawn loads the script that works its field', $GLOBALS['enqueued'], array( WPCPM_Dashboards::SWITCHER_SCRIPT ) );
ck(
	'registered from assets/js/switcher.js, in the footer, with no dependency',
	$GLOBALS['registered'][ WPCPM_Dashboards::SWITCHER_SCRIPT ] ?? null,
	array( 'src' => WPCPM_PLUGIN_URL . 'assets/js/switcher.js', 'deps' => array(), 'ver' => WPCPM_VERSION, 'footer' => true )
);
ck( 'and the file is there', is_file( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ) );

$css = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/css/dashboard.css' );

/**
 * One rule's declarations, out of the stylesheet, by its selector as written.
 *
 * @param string $css      The stylesheet.
 * @param string $selector The selector, exactly as the rule opens.
 * @return string What is between the rule's braces; '' when there is no such rule.
 */
function rule_of( $css, $selector ) {
	return preg_match( '/(?:^|\})\s*(?:\/\*.*?\*\/\s*)*' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/s', $css, $found ) ? $found[1] : '';
}

// The second column's least is `auto`, its items' own minimums, so the width switcher.js holds the
// field at keeps the column, and Show beside it, where they were; without the script that least is
// only the theme's.
ck( 'the fields are a grid across the switcher: the label, the list or the field, Show, the note', 1 === preg_match( '/\.wpcpm-dashboard__switcher-fields\s*\{[^}]*display:\s*grid;[^}]*flex:\s*1 1 100%;[^}]*gap:\s*0\.5em;[^}]*grid-template-columns:\s*max-content minmax\( auto, max-content \) max-content minmax\( max-content, 1fr \);/', $css ) );
ck( 'the label in the first column', 1 === preg_match( '/\.wpcpm-dashboard__switcher-fields label\s*\{\s*grid-column:\s*1;\s*\}/', $css ) );
ck( 'the select and the field in the second, one at a time, each as wide as the column, borders included', 1 === preg_match( '/\.wpcpm-dashboard__switcher-fields select,\s*\.wpcpm-dashboard__switcher-combo\s*\{[^}]*box-sizing:\s*border-box;[^}]*grid-column:\s*2;[^}]*max-width:\s*100%;[^}]*width:\s*100%;/', $css ) );
// A theme rule of the same weight that sets a display, printed after this sheet, would win over a
// plain restatement at its own weight; three classes deep holds against it.
ck( 'and the hidden attribute keeps either out of the page, whatever display a theme gives it', 1 === preg_match( '/\.wpcpm-dashboard__switcher \.wpcpm-dashboard__switcher-fields select\[hidden\],\s*\.wpcpm-dashboard__switcher \.wpcpm-dashboard__switcher-combo\[hidden\],\s*\.wpcpm-dashboard__switcher \.wpcpm-dashboard__switcher-list\[hidden\]\s*\{\s*display:\s*none;\s*\}/', $css ) );
ck( 'Show in the third column and the note in the fourth', array( 1 === preg_match( '/\.wpcpm-dashboard__switcher-fields \.wpcpm-button\s*\{\s*grid-column:\s*3;\s*\}/', $css ), 1 === preg_match( '/\.wpcpm-dashboard__switcher-note\s*\{\s*grid-column:\s*4;\s*\}/', $css ) ), array( true, true ) );
ck( 'the select is in the page\'s type, as the field and its list are, so the width measured on it holds the longest name in theirs', 1 === preg_match( '/\.wpcpm-dashboard__switcher select\s*\{\s*font:\s*inherit;\s*max-width:\s*100%;\s*\}/', $css ) );
ck( 'the field\'s block is what its list is placed against', false !== strpos( rule_of( $css, '.wpcpm-dashboard__switcher-combo' ), 'position: relative;' ) );

$field = rule_of( $css, '.wpcpm-dashboard__switcher-input' );

ck(
	'the field in the plugin\'s own look: the page\'s type, a control\'s edge, as wide as its block, and a chevron with room kept for it',
	array(
		false !== strpos( $field, 'font: inherit;' ),
		false !== strpos( $field, 'border: 1px solid var( --wpcpm-control-border, rgba( 128, 128, 128, 0.9 ) );' ),
		false !== strpos( $field, 'box-sizing: border-box;' ),
		false !== strpos( $field, 'width: 100%;' ),
		1 === preg_match( '/background-image:\s*url\( "data:image\/svg\+xml,[^"]*" \);/', $field ),
		false !== strpos( $field, 'background-position: right 8px center;' ),
		false !== strpos( $field, 'padding-right: 34px;' ),
	),
	array_fill( 0, 7, true )
);

$list = rule_of( $css, '.wpcpm-dashboard__switcher-list' );

// The theme's sticky group headings stack at 2 and its sticky header at 50: the list goes over the
// first and under the second, so a page scrolled under the header does not show the list over it.

ck(
	'its list opens under the field at the field\'s width, scrolls, and sits above what follows the switcher',
	array(
		false !== strpos( $list, 'position: absolute;' ),
		false !== strpos( $list, 'top: calc( 100% + 2px );' ),
		false !== strpos( $list, 'left: 0;' ),
		false !== strpos( $list, 'width: 100%;' ),
		false !== strpos( $list, 'box-sizing: border-box;' ),
		false !== strpos( $list, 'overflow-y: auto;' ),
		false !== strpos( $list, 'z-index: 10;' ),
	),
	array_fill( 0, 7, true )
);
ck( 'and is opaque, a pair of colors that read on each other, with no bullets', array( false !== strpos( $list, 'background: Canvas;' ), false !== strpos( $list, 'color: CanvasText;' ), false !== strpos( $list, 'list-style: none;' ), false !== strpos( $list, 'margin: 0;' ), false !== strpos( $list, 'padding: 0;' ) ), array( true, true, true, true, true ) );
ck( 'its rows are padded, and a long name wraps rather than running out of the list', 1 === preg_match( '/\.wpcpm-dashboard__switcher-option,\s*\.wpcpm-dashboard__switcher-empty\s*\{[^}]*margin:\s*0;[^}]*overflow-wrap:\s*anywhere;[^}]*padding:\s*0\.375em 0\.5em;/', $css ) );
ck( 'the highlighted row is drawn in the system\'s highlight, and stays so in forced colors', array( 1 === preg_match( '/\.wpcpm-dashboard__switcher-option\.is-active\s*\{[^}]*background:\s*Highlight;[^}]*color:\s*HighlightText;/', $css ), 1 === preg_match( '/@media \( forced-colors: active \)\s*\{\s*\.wpcpm-dashboard__switcher-option\.is-active\s*\{[^}]*forced-color-adjust:\s*none;/', $css ) ), array( true, true ) );
ck( 'the picked row is marked by its weight, not by a color alone', 1 === preg_match( '/\.wpcpm-dashboard__switcher-option\.is-current\s*\{\s*font-weight:\s*600;\s*\}/', $css ) );
// A status region that is not in the page when its text arrives is not read out, so the line is
// taken out of sight, never out of the page.
$status_rule = rule_of( $css, '.wpcpm-dashboard__switcher-status' );
ck( 'the status line is for a screen reader: out of sight, still in the page', array( false !== strpos( $status_rule, 'clip-path: inset( 50% );' ), false !== strpos( $status_rule, 'position: absolute;' ), false !== strpos( $status_rule, 'height: 1px;' ), false !== strpos( $status_rule, 'width: 1px;' ), false !== strpos( $status_rule, 'overflow: hidden;' ) ), array( true, true, true, true, true ) );
ck( 'and nothing hides it', array( preg_match( '/\.wpcpm-dashboard__switcher-status[^{]*\{[^}]*display:\s*none/', $css ), strpos( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ), 'status.hidden' ) ), array( 0, false ) );
ck( 'the rules for the separate search box and its no-match line are gone', array( strpos( $css, 'wpcpm-dashboard__switcher-find' ), strpos( $css, 'wpcpm-dashboard__switcher-none' ) ), array( false, false ) );
ck( 'the note is never squeezed to wrap beside Show: at 900px and below it takes a row of its own, under the field', 1 === preg_match( '/@media \( max-width: 900px \)\s*\{\s*\.wpcpm-dashboard__switcher-note\s*\{\s*grid-column:\s*2 \/ -1;\s*\}\s*\}/', $css ) );
ck( 'and on a phone the grid is one column, the label above the field, then Show, then the note', 1 === preg_match( '/@media \( max-width: 600px \)\s*\{\s*\.wpcpm-dashboard__switcher-fields\s*\{\s*grid-template-columns:\s*minmax\( 0, 1fr \);\s*\}\s*\.wpcpm-dashboard__switcher-fields label,\s*\.wpcpm-dashboard__switcher-fields select,\s*\.wpcpm-dashboard__switcher-combo,\s*\.wpcpm-dashboard__switcher-fields \.wpcpm-button,\s*\.wpcpm-dashboard__switcher-note\s*\{\s*grid-column:\s*1;\s*\}/', $css ) );

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

// Each dashboard's file and method, its field's placeholder, its no-match sentence, its ID, the
// query argument it posts and its label: the last three are what the node harness draws the four
// switchers with, so the page it drives is the one the four dashboards draw.
$dashboards = array(
	'institution' => array( 'includes/modules/class-wpcpm-institutions-dashboard.php', 'render_switcher', 'Find an institution', 'No institutions match that search.', 'wpcpm-institution-switcher', 'wpcpm_institution_view', 'Viewing as institution' ),
	'mentor'      => array( 'includes/modules/class-wpcpm-mentors-dashboard.php', 'render_mentor_switcher', 'Find a mentor', 'No mentors match that search.', 'wpcpm-mentor-switcher', 'wpcpm_mentor', 'Viewing as mentor' ),
	'student'     => array( 'includes/modules/class-wpcpm-students-dashboard.php', 'render_switcher', 'Find a student', 'No students match that search.', 'wpcpm-student-switcher', 'wpcpm_student_view', 'Viewing as student' ),
	'sponsor'     => array( 'includes/modules/class-wpcpm-sponsors-dashboard.php', 'render_switcher', 'Find a sponsor', 'No sponsors match that search.', 'wpcpm-sponsor-switcher', 'wpcpm_sponsor_view', 'Viewing as sponsor' ),
);

foreach ( $dashboards as $who => $where ) {
	$body = method_body( $where[0], $where[1] );

	ck( "the $who switcher is drawn by the shared helper", '' !== $body && false !== strpos( $body, 'WPCPM_Dashboards::render_switcher(' ) );
	ck( "and prints no list of its own", array( strpos( $body, '<select' ), strpos( $body, '<option' ), strpos( $body, '<form' ) ), array( false, false, false ) );
	ck( "and names its field's placeholder and its no-match sentence", array( false !== strpos( $body, "'" . $where[2] . "'" ), false !== strpos( $body, "'" . $where[3] . "'" ) ), array( true, true ) );
	ck( "and its ID and its label", array( false !== strpos( $body, "'" . $where[4] . "'" ), false !== strpos( $body, "'" . $where[6] . "'" ) ), array( true, true ) );
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

echo "\n=== The field, run by node ===\n";

$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $node ) {
	echo "skip the filter was not run: node is not on the path\n";
} else {
	// The pages the harness drives, drawn here by the helper: one switcher, one of names shorter
	// than a text field, one long enough to scroll, one whose first name wraps, and the four
	// dashboards' switchers on one page.
	// Handed over on stdin, so nothing is written to disk.
	$long = array();

	for ( $n = 1; $n <= 30; $n++ ) {
		$long[ 'recN' . $n ] = 'Name ' . $n;
	}

	$four = '';

	foreach ( array_values( $dashboards ) as $at => $where ) {
		$four .= draw(
			array( "v$at-1" => 'First Name', "v$at-2" => 'Second Name' ),
			"v$at-2",
			array( 'id' => $where[4], 'name' => $where[5], 'label' => $where[6], 'find' => $where[2], 'none' => $where[3] )
		);
	}

	$wrap = array( 'recWRAP' => 'An Institution With A Name Long Enough To Wrap' );

	for ( $n = 1; $n <= 15; $n++ ) {
		$wrap[ 'recW' . $n ] = 'Name ' . $n;
	}

	$pages = array(
		'one'   => draw( array( 'recZOE' => 'Zoe Academy', 'rec10' => 'Student 10', 'recKRAKOW' => 'Kraków Lab School', 'recALVARO' => 'Álvaro University', 'recLODZ' => 'Łódź Institute', 'rec2' => 'Student 2', 'recBERGEN' => 'bergen school', 'recECOLE' => 'École 42' ), 'rec10' ),
		'short' => draw( array( 'recBO' => 'Bo', 'recAL' => 'Al' ), 'recAL' ),
		'long'  => draw( $long, 'recN25' ),
		'four'  => $four,
		'wrap'  => draw( $wrap, 'recW1' ),
	);

	$process = proc_open(
		escapeshellarg( $node ) . ' ' . escapeshellarg( WPCPM_PLUGIN_DIR . 'bin/js/switcher-filter.js' ) . ' ' . escapeshellarg( WPCPM_PLUGIN_DIR . 'assets/js/switcher.js' ) . ' - 2>&1',
		array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ) ),
		$pipes
	);

	fwrite( $pipes[0], (string) json_encode( $pages ) );
	fclose( $pipes[0] );

	$out = explode( "\n", rtrim( (string) stream_get_contents( $pipes[1] ) ) );

	fclose( $pipes[1] );

	$status = proc_close( $process );

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
ck(
	'it finds each field by its block, the field\'s list by its aria-controls, the label by the list\'s aria-labelledby and the select by the label\'s for',
	array( false !== strpos( $js, "'.wpcpm-dashboard__switcher-combo'" ), false !== strpos( $js, "input.getAttribute( 'aria-controls' )" ), false !== strpos( $js, "list.getAttribute( 'aria-labelledby' )" ), false !== strpos( $js, "label.getAttribute( 'for' )" ) ),
	array( true, true, true, true )
);
ck( 'it shows the field, hides the select and points the label at the field', array( false !== strpos( $js, 'combo.hidden = false;' ), false !== strpos( $js, 'select.hidden = true;' ), false !== strpos( $js, "label.setAttribute( 'for', input.getAttribute( 'id' ) );" ) ), array( true, true, true ) );
ck( 'it writes each name into its row as text, never as markup', array( false !== strpos( $js, 'row.textContent = option.text;' ), strpos( $code, 'innerHTML' ), strpos( $code, 'insertAdjacentHTML' ) ), array( true, false, false ) );
ck( 'the count and the no-match sentence are the markup\'s', array( false !== strpos( $js, "status.getAttribute( 'data-wpcpm-count' )" ), false !== strpos( $js, "status.getAttribute( 'data-wpcpm-none' )" ) ), array( true, true ) );
ck( 'and set only when what the line says changes, so a screen reader does not say it again', 1 === preg_match( '/if \( status\.textContent !== text \) \{\s*status\.textContent = text;\s*\}/', $js ) );
ck( 'picking sets the select\'s choice, so Show sends what was picked', false !== strpos( $js, 'select.selectedIndex = index;' ) );
ck( 'Enter in the field submits nothing: Show does', 1 === preg_match( "/'Enter' === event\.key[^}]*event\.preventDefault\(\);/s", $js ) );
ck( 'a press on the list keeps the focus in the field', 1 === preg_match( "/list\.addEventListener\( 'mousedown', function \( event \) \{[^}]*event\.preventDefault\(\);/s", $js ) );
ck( 'leaving the field puts the picked name back', false !== strpos( $js, "input.addEventListener( 'blur', restore );" ) );
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
