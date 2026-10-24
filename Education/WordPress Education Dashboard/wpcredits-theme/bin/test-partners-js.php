<?php
/**
 * Runs the Partner levels blocks' editor scripts in node, on a stand-in for the block editor (theme
 * 1.32.1).
 *
 * Run from the theme root:  php bin/test-partners-js.php
 *
 * The Levels at a glance block's editor draws the table from the Partner levels block the editor holds,
 * read from the editor's store, and the server draws it from the levels of the Partners template in
 * use: the two must say the same. So the levels are taken from the theme's own levels pattern, the
 * table the server draws for a Partners template holding them is rendered here, by the blocks'
 * render.php files on the WordPress of bin/stubs/hub-wp.php, and both are handed to
 * bin/js/partners-editor-harness.js on stdin, which runs the three editor scripts on a stand-in of the
 * `wp.*` globals and compares. Each scenario the harness prints is shown here as one line, and its
 * summary, "N scenarios, M differ", is read as well.
 *
 * Every other check of the theme is PHP and needs nothing installed, so a machine without node on the
 * path gets one `skip` line and an exit of 0 rather than a failure.
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post = 0 ) { return ''; }
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) { return false; }
}

if ( ! function_exists( 'get_stylesheet' ) ) {
	function get_stylesheet() { return 'wpcredits-theme'; }
}

// Core's get_post(), for the synced patterns (wp_block posts) a template refers to: `$GLOBALS['synced']`,
// post ID => the post, published.
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null ) { return isset( $GLOBALS['synced'][ (int) $post ] ) ? $GLOBALS['synced'][ (int) $post ] : null; }
}

require_once WPCREDITS_THEME_DIR . 'inc/partners.php';
require_once __DIR__ . '/stubs/node-suite.php';

if ( '' === wpcredits_node() ) {
	echo "skip the Partner levels blocks' editor scripts were not run: node is not on the path\n";
	exit( 0 );
}

/**
 * A Partner levels block holding a level block for each of some levels' attributes, as block markup.
 *
 * @param array[] $levels     Each Partner level block's attributes.
 * @param array   $attributes The levels block's own attributes.
 * @return string
 */
function wpcredits_test_levels_markup( array $levels, array $attributes = array() ) {
	return wpcredits_hub_block_markup( 'wpcredits/partner-levels', $attributes, array_map( static function ( $level ) { return wpcredits_hub_block_markup( 'wpcredits/partner-level', $level ); }, $levels ) );
}

/**
 * The table the server draws on a page whose Partners template holds some levels, as words: its head's
 * cells and each row's, white space run together, as the harness reads the editor's.
 *
 * @param array[] $levels       Each Partner level block's attributes.
 * @param array   $attributes   The table block's attributes.
 * @param array   $translations What a translated string comes back as, as `$GLOBALS['translated']` has it.
 * @return array{head: string[], rows: string[][]}|null Null when the server draws no table.
 */
function wpcredits_test_glance_drawn( array $levels, array $attributes = array(), array $translations = array() ) {
	return wpcredits_test_glance_drawn_in( wpcredits_test_levels_markup( $levels ), $attributes, $translations );
}

/**
 * The table the server draws on a page whose Partners template is some block markup, as words, as
 * wpcredits_test_glance_drawn() gives it.
 *
 * @param string|null $template     The saved Partners template's content, or null for no saved copy, the
 *                                  theme's file the template in use.
 * @param array       $attributes   The table block's attributes.
 * @param array       $translations What a translated string comes back as.
 * @param array       $parts        Slug => content: saved template parts of this theme, published, that
 *                                  the template may refer to.
 * @return array{head: string[], rows: string[][]}|null Null when the server draws no table.
 */
function wpcredits_test_glance_drawn_in( $template, array $attributes = array(), array $translations = array(), array $parts = array() ) {
	$GLOBALS['block_templates'] = array();
	$GLOBALS['translated']      = $translations;

	if ( null !== $template ) {
		wpcredits_test_saved_template( 'page-partners', $template );
	}

	foreach ( $parts as $slug => $content ) {
		wpcredits_test_saved_template( $slug, $content, 'publish', 'wp_template_part' );
	}

	wpcredits_partner_levels_reset();

	$html = do_blocks( wpcredits_hub_block_markup( 'wpcredits/partner-glance', $attributes ) );

	$GLOBALS['block_templates'] = array();
	$GLOBALS['translated']      = array();
	wpcredits_partner_levels_reset();

	if ( '' === trim( $html ) ) {
		return null;
	}

	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	$xpath = new DOMXPath( $document );
	$said  = static function ( $node ) { return trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) ); };
	$rows  = array();

	foreach ( $xpath->query( '//tbody/tr' ) as $row ) {
		$rows[] = array_map( $said, iterator_to_array( $xpath->query( './*', $row ) ) );
	}

	return array(
		'head' => array_map( $said, iterator_to_array( $xpath->query( '//thead/tr/th' ) ) ),
		'rows' => $rows,
	);
}

// Today's levels, as the theme's levels pattern writes them, and the same levels as an Administrator
// may have edited them: a level renamed, an item added, a short amount taken out, a level with no name.
$pattern_levels = wpcredits_test_block_in( wpcredits_test_pattern( 'partners-levels.php' ), 'wpcredits/partner-levels' );
$today          = array_map( static function ( $parsed ) { return $parsed['attrs']; }, $pattern_levels ? $pattern_levels['innerBlocks'] : array() );
$edited         = $today;

$edited[1]['name']    = 'Sustainer';
$edited[1]['items'][] = 'A seat at the program review';
$edited[3]['short']   = '';
$edited[]             = array( 'items' => array( 'A draft level, with no name' ) );

$translations = array(
	'Recognition'                                             => 'Anerkennung',
	"a partnership level has this recognition\4Yes"           => 'Ja',
	"a partnership level does not have this recognition\4No" => 'Nein',
);
$typed        = array(
	'label' => 'What it gives',
	'yes'   => 'Included',
	'no'    => 'Not included',
);

// Theme 1.32.1, the final review: core's block visibility. A level hidden from its block's menu, saved as
// `"metadata":{"blockVisibility":false}`, is drawn as nothing on the page, and the server leaves it out of
// the table; a level hidden at one window size only is still on the page. A levels block hidden, or one in
// a group that is hidden, draws no level, and the server draws no table.
$hidden_everywhere = array( 'metadata' => array( 'blockVisibility' => false ) );
$hidden            = $today;
$on_phones         = $today;

$hidden[1]    += $hidden_everywhere;
$on_phones[2] += array( 'metadata' => array( 'blockVisibility' => array( 'viewport' => array( 'mobile' => false ) ) ) );

// Theme 1.32.2: a template that holds two Partner levels blocks shown, one among its own blocks and one in
// what it refers to (a synced pattern, a template part), even earlier in the markup. The server reads the
// template's own blocks first, and follows a reference only where they hold no levels block it draws,
// each reference's own blocks again first; the editor's table names the same block. The levels in the
// references are the edited ones, so each table tells which block it was drawn from.
$GLOBALS['synced'] = array(
	601 => (object) array(
		'ID'            => 601,
		'post_type'     => 'wp_block',
		'post_status'   => 'publish',
		'post_content'  => wpcredits_test_levels_markup( $edited ),
		'post_password' => '',
	),
	602 => (object) array(
		'ID'            => 602,
		'post_type'     => 'wp_block',
		'post_status'   => 'publish',
		'post_content'  => '<!-- wp:template-part {"slug":"partners-inner-part","theme":"wpcredits-theme"} /-->' . "\n\n" . wpcredits_test_levels_markup( $today ),
		'post_password' => '',
	),
	603 => (object) array(
		'ID'            => 603,
		'post_type'     => 'wp_block',
		'post_status'   => 'publish',
		'post_content'  => '<!-- wp:template-part {"slug":"partners-today-part","theme":"wpcredits-theme"} /-->',
		'post_password' => '',
	),
);
$own_levels        = wpcredits_hub_block_markup( 'core/group', array(), array( wpcredits_test_levels_markup( $today ) ) );
$synced_levels     = '<!-- wp:block {"ref":601} /-->';
$part_levels       = '<!-- wp:template-part {"slug":"partners-levels-part","theme":"wpcredits-theme"} /-->';
$parts             = array(
	'partners-levels-part' => wpcredits_test_levels_markup( $edited ),
	'partners-inner-part'  => wpcredits_test_levels_markup( $edited ),
	'partners-today-part'  => wpcredits_test_levels_markup( $today ),
);

$cases = array(
	'today'        => array(
		'levels' => $today,
		'drawn'  => wpcredits_test_glance_drawn( $today ),
	),
	'hidden'       => array(
		'levels' => $hidden,
		'drawn'  => wpcredits_test_glance_drawn( $hidden ),
	),
	'onPhones'     => array(
		'levels' => $on_phones,
		'drawn'  => wpcredits_test_glance_drawn( $on_phones ),
	),
	'levelsHidden' => array(
		'drawn' => wpcredits_test_glance_drawn_in( wpcredits_test_levels_markup( $today, $hidden_everywhere ) ),
	),
	'groupHidden'  => array(
		'drawn' => wpcredits_test_glance_drawn_in( wpcredits_hub_block_markup( 'core/group', $hidden_everywhere, array( wpcredits_test_levels_markup( $today ) ) ) ),
	),
	'edited'       => array(
		'levels' => $edited,
		'drawn'  => wpcredits_test_glance_drawn( $edited ),
	),
	// The table block placed where the editor holds no Partner levels block, on another page or in another
	// template: the server draws the Partners template's table there, the theme's file's five levels while
	// no copy is saved, and nothing while the saved copy shows no levels.
	'elsewhere'    => array(
		'drawn' => wpcredits_test_glance_drawn_in( null ),
		'none'  => wpcredits_test_glance_drawn_in( '<!-- wp:paragraph --><p>No levels.</p><!-- /wp:paragraph -->' ),
	),
	'translated'   => array(
		'translations' => $translations,
		'drawn'        => wpcredits_test_glance_drawn( $today, array(), $translations ),
	),
	'typed'        => array(
		'attributes' => $typed,
		'drawn'      => wpcredits_test_glance_drawn( $today, $typed ),
	),
	// The template's own levels block after a synced pattern, or a template part, holding another; the
	// reference alone; the template's own levels block hidden, with the synced pattern after it; a synced
	// pattern that holds a template part with levels, then its own levels block; and two synced patterns,
	// the first holding a template part with levels, the second a levels block of its own.
	'references'   => array(
		'ownAfterSynced' => wpcredits_test_glance_drawn_in( $synced_levels . "\n\n" . $own_levels ),
		'ownAfterPart'   => wpcredits_test_glance_drawn_in( $part_levels . "\n\n" . $own_levels, array(), array(), $parts ),
		'syncedOnly'     => wpcredits_test_glance_drawn_in( $synced_levels ),
		'hiddenOwn'      => wpcredits_test_glance_drawn_in( wpcredits_test_levels_markup( $today, $hidden_everywhere ) . "\n\n" . $synced_levels ),
		'nested'         => wpcredits_test_glance_drawn_in( '<!-- wp:block {"ref":602} /-->', array(), array(), $parts ),
		'firstReference' => wpcredits_test_glance_drawn_in( '<!-- wp:block {"ref":603} /-->' . "\n\n" . $synced_levels, array(), array(), $parts ),
	),
);

exit( wpcredits_run_node_suite( __DIR__ . '/js/partners-editor-harness.js', $cases ) );
