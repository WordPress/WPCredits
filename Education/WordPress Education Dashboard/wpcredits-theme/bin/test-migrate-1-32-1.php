<?php
/**
 * The one-time migration of theme 1.32.1 (inc/migrate-1-32-1.php): the saved copy of the Partners
 * template, which theme releases no longer change, has the two Custom HTML blocks that hold its 1.31
 * cards and table replaced by the Partner levels and Levels at a glance blocks, the levels read from that
 * markup; the saved Education Hub template, as 1.32.0 migrated it, has its Program index marked
 * `editable`; nothing else of either is touched. The repair of a save from an editor tab opened before
 * the update, and the Site Editor's notice, now cover both migrations (inc/migrate-1-32-0.php).
 *
 * Checked on live's own saved copies: bin/fixtures/saved-partners.html, the Partners template as live
 * holds it (saved from 1.31.11 on 22 October 2026, read on 23 October 2026), and the Education Hub
 * template as 1.32.0 migrated it on live, which is bin/fixtures/saved-education-hub.html run through the
 * 1.32.0 migration with the index unmarked (the 1.32.0 release wrote no mark). Rendered on the
 * WordPress of bin/stubs/hub-wp.php, with the posts, options and users of bin/stubs/migrate-wp.php.
 * The test removes the clause the theme dropped on 27 October 2026 (`$partners`); the stale save keeps it.
 *
 * The copy of the template as it was is checked against the history live's 1.32.0 run met: a visitor's
 * request (kses on, no one signed in) and a newest revision that is the template without its svg.
 *
 * Run from the theme root:  php bin/test-migrate-1-32-1.php
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once __DIR__ . '/stubs/migrate-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once WPCREDITS_THEME_DIR . 'inc/partners.php';
require_once WPCREDITS_THEME_DIR . 'inc/migrate-1-32-0.php';
require_once WPCREDITS_THEME_DIR . 'inc/migrate-1-32-1.php';

$fail  = 0;
$total = 0;

/**
 * One check.
 *
 * @param string $label    What is asked.
 * @param mixed  $actual   What happened.
 * @param mixed  $expected What should have happened.
 */
function ck( $label, $actual, $expected ) {
	global $fail, $total;

	++$total;

	$ok = $actual === $expected;

	if ( ! $ok ) {
		++$fail;
	}

	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";

	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

/**
 * The first Custom HTML block in some markup whose content holds a text, as span_of() gives a span, with
 * its content.
 *
 * @param string $markup Block markup.
 * @param string $text   What its content holds, such as a class.
 * @return array{0: int, 1: int, 2: string}|null Its start, its length and its content.
 */
function html_block_with( $markup, $text ) {
	preg_match_all( '#<!-- wp:html -->(.*?)<!-- /wp:html -->#s', $markup, $blocks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

	foreach ( $blocks as $block ) {
		if ( false !== strpos( $block[1][0], $text ) ) {
			return array( $block[0][1], strlen( $block[0][0] ), $block[1][0] );
		}
	}

	return null;
}

/**
 * The migration runners hooked on init, run in their priority order, as WordPress runs init.
 *
 * @return int[] The priorities they ran at, in order.
 */
function run_init_migrations() {
	$runners = array_values(
		array_filter(
			$GLOBALS['hooks']['init'] ?? array(),
			static function ( $hooked ) {
				return in_array( $hooked[0], array( 'wpcredits_migrate_1_32_0', 'wpcredits_migrate_1_32_1' ), true );
			}
		)
	);

	usort( $runners, static function ( $a, $b ) { return $a[1] - $b[1]; } );

	foreach ( $runners as $runner ) {
		call_user_func( $runner[0] );
	}

	return array_map( static function ( $runner ) { return $runner[0] . ' ' . $runner[1]; }, $runners );
}

/**
 * The query the runners make for a slug's saved templates.
 *
 * @param string $slug The slug.
 * @return string As the calls log it.
 */
function query_for( $slug ) {
	return 'get_posts ' . json_encode(
		array(
			'post_type'      => 'wp_template',
			'post_status'    => 'any',
			'name'           => $slug,
			'posts_per_page' => 20,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
}

$saved      = (string) file_get_contents( __DIR__ . '/fixtures/saved-partners.html' );
$partners   = str_replace( ', with the exclusive role in that program', '', $saved );
$cards_span = html_block_with( $partners, 'wpc-partners-levels__grid' );
$table_span = html_block_with( $partners, 'wpc-partners-glance__table' );
$defaults   = wpcredits_partner_levels_defaults();

echo "=== Live's saved Partners template ===\n";

ck( 'the fixture is live\'s saved Partners template: four Custom HTML blocks, the cards in one and the table in a later one; no Partner levels or Levels at a glance block; the levels read from the two are the theme\'s five, the short amounts of the table\'s head among them',
	array(
		substr_count( $partners, '<!-- wp:html -->' ),
		null !== $cards_span && null !== $table_span && $cards_span[0] < $table_span[0],
		substr_count( $partners, 'wp:wpcredits/partner-' ),
		wpcredits_partner_levels_from_html( $cards_span[2] . $table_span[2] ) === $defaults,
		strlen( $saved ),
	),
	array( 4, true, 0, true, 25467 ) );

$migrated = wpcredits_migrate_1_32_1_content( $partners, 'page-partners' );
$after    = $migrated['content'];

ck( 'the migration replaces its two targets, and says so: the cards, read from the saved markup, and the table',
	array( $migrated['changed'], $migrated['skipped'] ),
	array( array( 'partner-levels', 'partner-glance' ), array() ) );

$levels_span = span_of( $after, 'wpcredits/partner-levels' );
$glance_span = span_of( $after, 'wpcredits/partner-glance' );

ck( 'every other byte of the template is unchanged: before the cards\' Custom HTML block, between it and the table\'s, and after the table\'s, the migrated copy is the saved copy byte for byte',
	array(
		null !== $levels_span && null !== $glance_span,
		pieces_around( $after, array( $levels_span, $glance_span ) ) === pieces_around( $partners, array( $cards_span, $table_span ) ),
		strlen( $after ) - $levels_span[1] - $glance_span[1] === strlen( $partners ) - $cards_span[1] - $table_span[1],
	),
	array( true, true, true ) );

ck( 'the cards\' place holds the Partner levels block with the five levels read from them, for live\'s copy the theme\'s own, as the levels pattern writes it; the table\'s holds the Levels at a glance block with no attribute, its words the theme\'s',
	array(
		substr( $after, $levels_span[0], $levels_span[1] ) === wpcredits_partner_levels_markup( $defaults ),
		count( wpcredits_test_block_in( $after, 'wpcredits/partner-levels' )['innerBlocks'] ?? array() ),
		substr( $after, $glance_span[0], $glance_span[1] ),
		substr( $after, $glance_span[0], $glance_span[1] ) === wpcredits_partner_glance_markup(),
	),
	array( true, 5, '<!-- wp:wpcredits/partner-glance /-->', true ) );

// The proof the page looks as it did: the migrated template draws its cards byte for byte as the saved
// Custom HTML block held them, and its table as it was but for its rows' words, now the levels' items
// in their own words (the design's D5).
wpcredits_test_saved_template( 'page-partners', $after );
wpcredits_partner_levels_reset();

$drawn_cards = render_block( wpcredits_test_block_in( $after, 'wpcredits/partner-levels' ) );
$drawn_table = render_block( wpcredits_test_block_in( $after, 'wpcredits/partner-glance' ) );
$items       = array_merge( array(), ...array_column( $defaults, 'items' ) );
$row_at      = 0;
$table_now   = preg_replace_callback(
	'#<th scope="row">[^<]*</th>#',
	static function () use ( &$row_at, $items ) {
		return '<th scope="row">' . esc_html( $items[ $row_at++ ] ?? '' ) . '</th>';
	},
	$table_span[2]
);

ck( 'the migrated template draws its cards byte for byte as the saved Custom HTML block held them, and its table byte for byte but for the fifteen rows\' words',
	array( $drawn_cards === $cards_span[2], $drawn_table === $table_now, $row_at ),
	array( true, true, 15 ) );

wpcredits_test_saved_template( 'page-partners', $partners );
wpcredits_partner_levels_reset();
$form_before = wpcredits_partners_levels_for_form( array() );

wpcredits_test_saved_template( 'page-partners', $after );
wpcredits_partner_levels_reset();
$form_after = wpcredits_partners_levels_for_form( array() );

ck( 'the sponsor application is handed the same levels before the migration (read from the 1.31 cards) and after it (read from the block): five, the Partner level\'s tag among them',
	array( $form_before === $form_after, count( $form_after ), $form_after[4]['tag'] ?? null ),
	array( true, 5, 'Funds a named program' ) );

$again = wpcredits_migrate_1_32_1_content( $after, 'page-partners' );

ck( 'run again it changes nothing, and says why: the levels and the table are already blocks',
	array( $again['content'] === $after, $again['changed'], $again['skipped'] ),
	array( true, array(), array( 'partner-levels-done', 'partner-glance-done' ) ) );

echo "\n=== Live's saved Education Hub template, as 1.32.0 migrated it ===\n";

$hub_saved   = (string) file_get_contents( __DIR__ . '/fixtures/saved-education-hub.html' );
$hub_1320    = wpcredits_migrate_1_32_0_content( $hub_saved, 'page-education-hub' )['content'];
$marked_open = '<!-- wp:wpcredits/program-index {"editable":true} -->';
$bare_open   = '<!-- wp:wpcredits/program-index -->';
$hub_live    = str_replace( $marked_open, $bare_open, $hub_1320 );

ck( 'live\'s hub is the 1.32.0 migration\'s template with its index unmarked, as the 1.32.0 release wrote it: a container index holding the six cards, with no `editable`, its opening comment the one difference',
	array(
		substr_count( $hub_1320, $marked_open ),
		substr_count( $hub_live, $bare_open ),
		substr_count( $hub_live, '"editable"' ),
		count( wpcredits_test_block_in( $hub_live, 'wpcredits/program-index' )['innerBlocks'] ?? array() ),
		strlen( $hub_1320 ) - strlen( $hub_live ),
	),
	array( 1, 1, 0, 6, strlen( '{"editable":true} ' ) ) );

$hub_run = wpcredits_migrate_1_32_1_content( $hub_live, 'page-education-hub' );
$open_at = strpos( $hub_live, $bare_open );

ck( 'the migration adds `"editable":true` to that index, first among its attributes as the Site Editor writes them, and changes nothing else: before the opening comment and after it the template is byte for byte what it was; it is the 1.32.0 migration\'s template exactly',
	array(
		$hub_run['changed'],
		$hub_run['skipped'],
		substr( $hub_run['content'], 0, $open_at ) === substr( $hub_live, 0, $open_at ),
		substr( $hub_run['content'], $open_at, strlen( $marked_open ) ),
		substr( $hub_run['content'], $open_at + strlen( $marked_open ) ) === substr( $hub_live, $open_at + strlen( $bare_open ) ),
		$hub_run['content'] === $hub_1320,
	),
	array( array( 'program-index-marked' ), array(), true, $marked_open, true, true ) );

ck( 'the page draws the marked index as it drew the unmarked one, byte for byte: the mark only tells an emptied index from the 1.31 one',
	render_block( wpcredits_test_block_in( $hub_run['content'], 'wpcredits/program-index' ) ) === render_block( wpcredits_test_block_in( $hub_live, 'wpcredits/program-index' ) ),
	true );

$hub_again = wpcredits_migrate_1_32_1_content( $hub_run['content'], 'page-education-hub' );

ck( 'run again it changes nothing, and says why: the index is already marked',
	array( $hub_again['content'] === $hub_run['content'], $hub_again['changed'], $hub_again['skipped'] ),
	array( true, array(), array( 'program-index-marked-done' ) ) );

$card    = '<!-- wp:wpcredits/program-card {"slug":"campus-connect","name":"Campus Connect"} /-->';
$indexes = array(
	'a lock, kept'                    => array( '<!-- wp:wpcredits/program-index {"lock":{"move":true,"remove":true}} -->' . "\n" . $card . "\n" . '<!-- /wp:wpcredits/program-index -->', '<!-- wp:wpcredits/program-index {"editable":true,"lock":{"move":true,"remove":true}} -->' . "\n" . $card . "\n" . '<!-- /wp:wpcredits/program-index -->', array( 'program-index-marked' ), array() ),
	'a mark that is not true'         => array( '<!-- wp:wpcredits/program-index {"lock":{"move":true},"editable":false} -->' . "\n" . $card . "\n" . '<!-- /wp:wpcredits/program-index -->', '<!-- wp:wpcredits/program-index {"editable":true,"lock":{"move":true}} -->' . "\n" . $card . "\n" . '<!-- /wp:wpcredits/program-index -->', array( 'program-index-marked' ), array() ),
	'marked, after another attribute' => array( '<!-- wp:wpcredits/program-index {"lock":{"move":true},"editable":true} -->' . "\n" . $card . "\n" . '<!-- /wp:wpcredits/program-index -->', null, array(), array( 'program-index-marked-done' ) ),
	'emptied (marked, no card)'       => array( '<!-- wp:wpcredits/program-index {"editable":true} /-->', null, array(), array( 'program-index-emptied' ) ),
	'the 1.31 index'                  => array( '<!-- wp:wpcredits/program-index /-->', null, array(), array( 'program-index-legacy' ) ),
	'a container with no card'        => array( '<!-- wp:wpcredits/program-index -->' . "\n" . '<!-- /wp:wpcredits/program-index -->', null, array(), array( 'program-index-legacy' ) ),
	'no index'                        => array( '<!-- wp:paragraph -->' . "\n" . '<p>No index.</p>' . "\n" . '<!-- /wp:paragraph -->', null, array(), array( 'program-index-missing' ) ),
);
$outcomes = array();
$expected = array();

foreach ( $indexes as $label => $index ) {
	$template           = '<!-- wp:paragraph -->' . "\n" . '<p>Before.</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n" . $index[0] . "\n\n" . '<!-- wp:paragraph -->' . "\n" . '<p>After.</p>' . "\n" . '<!-- /wp:paragraph -->';
	$run                = wpcredits_migrate_1_32_1_content( $template, 'page-education-hub' );
	$outcomes[ $label ] = array( $run['content'], $run['changed'], $run['skipped'] );
	$expected[ $label ] = array( null === $index[1] ? $template : str_replace( $index[0], $index[1], $template ), $index[2], $index[3] );
}

ck( 'a container index holding a card and without the mark gains it, its other attributes kept (a mark that is not the boolean true is made true); a marked index, an emptied one, the 1.31 index and a container with no card are left as they are, each with its reason; so is a template without an index',
	$outcomes, $expected );

$legacy_run = wpcredits_migrate_1_32_1_content( $hub_saved, 'page-education-hub' );

ck( 'the hub as 1.31 saved it, which only the 1.32.0 migration replaces, is left whole by the 1.32.1 migration, and said: its index is still the 1.31 one',
	array( $legacy_run['content'] === $hub_saved, $legacy_run['changed'], $legacy_run['skipped'] ),
	array( true, array(), array( 'program-index-legacy' ) ) );

echo "\n=== What the migration reads from the Partners template, and what it leaves ===\n";

// An Administrator's edits in the saved Custom HTML are the levels the migration writes: an amount and a
// short amount changed, an item reworded with markup and an ampersand; and an edit elsewhere in the
// template is kept byte for byte.
$edited = str_replace(
	array(
		'<p class="wpc-partners-level__amount">$5,000 a year</p>',
		'<li>A badge for your own site</li>',
		'<span class="wpc-partners-glance__level">Supporter</span> <span class="wpc-partners-glance__amount">$5,000</span>',
		'<h2 class="wp-block-heading wpc-partners-pilot__title">2027 is a pilot year</h2>',
	),
	array(
		'<p class="wpc-partners-level__amount">$6,000 a year</p>',
		'<li>A badge for your <strong>own</strong> site &amp; blog</li>',
		'<span class="wpc-partners-glance__level">Supporter</span> <span class="wpc-partners-glance__amount">$6,000</span>',
		'<h2 class="wp-block-heading wpc-partners-pilot__title">2027 is our pilot year</h2>',
	),
	$partners
);
$edited_run    = wpcredits_migrate_1_32_1_content( $edited, 'page-partners' );
$edited_levels = wpcredits_test_block_in( $edited_run['content'], 'wpcredits/partner-levels' );
$edited_cards  = html_block_with( $edited, 'wpc-partners-levels__grid' );
$edited_table  = html_block_with( $edited, 'wpc-partners-glance__table' );

ck( 'the levels are read from the saved markup, an Administrator\'s edits among them: the amount, the short amount from the table\'s head, the reworded item as plain text; the edit elsewhere in the template is kept, every other byte as it was',
	array(
		$edited_run['changed'],
		$edited_levels['innerBlocks'][1]['attrs'] ?? null,
		pieces_around( $edited_run['content'], array( span_of( $edited_run['content'], 'wpcredits/partner-levels' ), span_of( $edited_run['content'], 'wpcredits/partner-glance' ) ) ) === pieces_around( $edited, array( $edited_cards, $edited_table ) ),
		substr_count( $edited_run['content'], '2027 is our pilot year' ),
	),
	array(
		array( 'partner-levels', 'partner-glance' ),
		array(
			'name'   => 'Supporter',
			'amount' => '$6,000 a year',
			'short'  => '$6,000',
			'lead'   => 'Everything in Friend, plus:',
			'items'  => array( 'Logo and description on the partner page', 'A badge for your own site & blog', 'The annual program report' ),
		),
		true,
		1,
	) );

// The table's own words, its head word and the two words of its cells, are read too: an Administrator who
// changed them in the Custom HTML keeps them, as the block's attributes; the theme's words write none.
$worded     = str_replace(
	array( '<th scope="col">Recognition</th>', '<td class="wpc-partners-glance__yes">Yes</td>' ),
	array( '<th scope="col">What you get</th>', '<td class="wpc-partners-glance__yes">Included</td>' ),
	$partners
);
$worded_run = wpcredits_migrate_1_32_1_content( $worded, 'page-partners' );
$worded_at  = span_of( $worded_run['content'], 'wpcredits/partner-glance' );

ck( 'the table\'s head word and the word of its Yes cells, changed in the saved markup, are kept as the Levels at a glance block\'s words; its No, the theme\'s, is not written, and the table draws them',
	array(
		$worded_at ? substr( $worded_run['content'], $worded_at[0], $worded_at[1] ) : null,
		substr_count( render_block( wpcredits_test_block_in( $worded_run['content'], 'wpcredits/partner-glance' ) ), '<td class="wpc-partners-glance__yes">Included</td>' ),
		false !== strpos( render_block( wpcredits_test_block_in( $worded_run['content'], 'wpcredits/partner-glance' ) ), '<th scope="col">What you get</th>' ),
	),
	array( '<!-- wp:wpcredits/partner-glance {"label":"What you get","yes":"Included"} /-->', 45, true ) );

// An Administrator who removed every level from the 1.31 cards saved the grid with none in it, and one
// who emptied each card's name left cards the page does not draw: both are a well-formed grid that holds
// no level, which becomes the emptied Partner levels block (the container with no level), never the
// theme's five. The page and the sponsor application show no level after the migration, as before it.
$emptied_cards = substr_replace( $partners, "<!-- wp:html -->\n<ul class=\"wpc-partners-levels__grid\">\n</ul>\n<!-- /wp:html -->", $cards_span[0], $cards_span[1] );
$emptied_run   = wpcredits_migrate_1_32_1_content( $emptied_cards, 'page-partners' );
$nameless      = substr_replace( $partners, "<!-- wp:html -->\n<ul class=\"wpc-partners-levels__grid\"><li class=\"wpc-partners-level\"><h3 class=\"wpc-partners-level__name\"> </h3><p class=\"wpc-partners-level__amount\">$5,000 a year</p></li></ul>\n<!-- /wp:html -->", $cards_span[0], $cards_span[1] );
$nameless_run  = wpcredits_migrate_1_32_1_content( $nameless, 'page-partners' );

wpcredits_test_saved_template( 'page-partners', $emptied_cards );
wpcredits_partner_levels_reset();
$emptied_form_before = wpcredits_partners_levels_for_form( array() );

wpcredits_test_saved_template( 'page-partners', $emptied_run['content'] );
wpcredits_partner_levels_reset();
$emptied_form_after   = wpcredits_partners_levels_for_form( array() );
$emptied_levels_after = wpcredits_partner_levels();
$emptied_table_after  = trim( render_block( wpcredits_test_block_in( $emptied_run['content'], 'wpcredits/partner-glance' ) ) );

wpcredits_test_saved_template( 'page-partners', $after );
wpcredits_partner_levels_reset();

ck( 'a well-formed grid that holds no level, every card removed or none with a name, becomes the emptied Partner levels block in its place and the table its block, which draws nothing; the page and the sponsor application show no level after it, as before it, and the theme\'s levels are not written',
	array(
		$emptied_run['changed'],
		$emptied_run['skipped'],
		substr( $emptied_run['content'], $cards_span[0], strlen( '<!-- wp:wpcredits/partner-levels /-->' ) ),
		substr_count( $emptied_run['content'], 'wp:wpcredits/partner-level ' ),
		array( $emptied_form_before, $emptied_form_after, $emptied_levels_after, $emptied_table_after ),
		$nameless_run['changed'],
		$nameless_run['content'] === $emptied_run['content'],
	),
	array(
		array( 'partner-levels-emptied', 'partner-glance' ),
		array(),
		'<!-- wp:wpcredits/partner-levels /-->',
		0,
		array( array(), array(), array(), '' ),
		array( 'partner-levels-emptied', 'partner-glance' ),
		true,
	) );

$unreadable     = substr_replace( $partners, "<!-- wp:html -->\n<div class=\"wpc-partners-levels__grid\"><p>The levels are coming.</p></div>\n<!-- /wp:html -->", $cards_span[0], $cards_span[1] );
$unreadable_run = wpcredits_migrate_1_32_1_content( $unreadable, 'page-partners' );

ck( 'markup that is not a readable grid (the cards\' class on another element, no card in it) is written as the theme\'s five levels, and said in `skipped`',
	array(
		$unreadable_run['changed'],
		$unreadable_run['skipped'],
		false !== strpos( $unreadable_run['content'], wpcredits_partner_levels_markup( $defaults ) ),
	),
	array( array( 'partner-levels', 'partner-glance' ), array( 'partner-levels-unreadable' ), true ) );

// The table without the cards: 1.31 drew the table as its Custom HTML block held it. The Levels at a glance
// block draws from the template's levels, and with no levels block it draws nothing, so a table whose
// cards this run does not convert, in a template with no levels block, is left as it is, and said.
$table_only       = substr_replace( $partners, '', $cards_span[0], $cards_span[1] );
$table_only_run   = wpcredits_migrate_1_32_1_content( $table_only, 'page-partners' );
$table_beside     = substr_replace( $after, "<!-- wp:html -->\n" . trim( $table_span[2] ) . "\n<!-- /wp:html -->", $glance_span[0], $glance_span[1] );
$table_beside_run = wpcredits_migrate_1_32_1_content( $table_beside, 'page-partners' );

ck( 'a table without the cards, in a template with no levels block, is left whole, so the page still shows it, and said: no levels to draw it from; a table in a template that already holds the levels block becomes its block',
	array(
		$table_only_run['content'] === $table_only,
		$table_only_run['changed'],
		$table_only_run['skipped'],
		$table_beside_run['content'] === $after,
		$table_beside_run['changed'],
		$table_beside_run['skipped'],
	),
	array( true, array(), array( 'partner-levels-missing', 'partner-glance-no-levels' ), true, array( 'partner-glance' ), array( 'partner-levels-done' ) ) );

// The cards and the table in one Custom HTML block, as an Administrator who merged the two would leave
// them: the block becomes both blocks, in the order the markup had them.
$merged     = substr_replace( $partners, '', $table_span[0], $table_span[1] );
$merged     = substr_replace( $merged, "<!-- wp:html -->\n" . trim( $cards_span[2] ) . "\n" . trim( $table_span[2] ) . "\n<!-- /wp:html -->", $cards_span[0], $cards_span[1] );
$merged_run = wpcredits_migrate_1_32_1_content( $merged, 'page-partners' );

ck( 'the cards and the table in one Custom HTML block become the levels block and the table block, in that order, in its place, the levels read with the table\'s short amounts',
	array(
		$merged_run['changed'],
		substr( $merged_run['content'], $cards_span[0], strlen( wpcredits_partner_levels_markup( $defaults ) . "\n\n" . wpcredits_partner_glance_markup() ) ) === wpcredits_partner_levels_markup( $defaults ) . "\n\n" . wpcredits_partner_glance_markup(),
		substr_count( $merged_run['content'], '<!-- wp:html -->' ),
	),
	array( array( 'partner-levels', 'partner-glance' ), true, 2 ) );

// After the migration, only an editor of 1.32.1 can insert the blocks, so a template holding a block and,
// beside it, a Custom HTML block of the same part is an Administrator's choice, not a stale tab's save:
// that Custom HTML block is left whole, and so is one that merely names the class in another attribute.
$beside     = str_replace( '<!-- wp:wpcredits/partner-glance /-->', '<!-- wp:wpcredits/partner-glance /-->' . "\n\n" . "<!-- wp:html -->\n" . trim( $cards_span[2] ) . "\n<!-- /wp:html -->", $after );
$beside_run = wpcredits_migrate_1_32_1_content( $beside, 'page-partners' );
$named      = '<!-- wp:html -->' . "\n" . '<div data-class="wpc-partners-levels__grid">Not the cards.</div>' . "\n" . '<!-- /wp:html -->';
$named_run  = wpcredits_migrate_1_32_1_content( $named, 'page-partners' );

ck( 'a Custom HTML block of 1.31 cards beside the Partner levels block an Administrator already has is left whole, the levels said done, and no second levels block is made; a block naming the class only in another attribute is no target',
	array( $beside_run['content'] === $beside, $beside_run['changed'], $beside_run['skipped'], $named_run['content'] === $named, $named_run['skipped'] ),
	array( true, array(), array( 'partner-levels-done', 'partner-glance-done' ), true, array( 'partner-levels-missing', 'partner-glance-missing' ) ) );

// The 1.31 cards, or the table, hidden in the Site Editor: the Custom HTML block was saved with
// `"metadata":{"blockVisibility":false}` in its comment, which core draws as nothing whatever the block's
// support, so the page showed no cards, or no table. The block that replaces it carries the same metadata,
// a name an Administrator gave it in List View among it, so a hidden part stays hidden; a block with no
// metadata, or an empty one, or other attributes alone (a lock), is replaced exactly as live's is.
$html_open   = '<!-- wp:html -->';
$opened_with = static function ( $markup, array $span, $open ) use ( $html_open ) {
	return substr_replace( $markup, $open, $span[0], strlen( $html_open ) );
};
$hide        = '{"metadata":{"blockVisibility":false}}';
$hide_named  = '{"metadata":{"blockVisibility":false,"name":"The table"}}';
$levels_open = '<!-- wp:wpcredits/partner-levels -->';
$glance_void = '<!-- wp:wpcredits/partner-glance /-->';

$hidden_runs = array(
	'the cards hidden'           => wpcredits_migrate_1_32_1_content( $opened_with( $partners, $cards_span, '<!-- wp:html ' . $hide . ' -->' ), 'page-partners' ),
	'the table hidden and named' => wpcredits_migrate_1_32_1_content( $opened_with( $partners, $table_span, '<!-- wp:html ' . $hide_named . ' -->' ), 'page-partners' ),
	'both in one block, hidden'  => wpcredits_migrate_1_32_1_content( $opened_with( $merged, $cards_span, '<!-- wp:html ' . $hide . ' -->' ), 'page-partners' ),
	'an emptied grid, hidden'    => wpcredits_migrate_1_32_1_content( $opened_with( $emptied_cards, $cards_span, '<!-- wp:html ' . $hide . ' -->' ), 'page-partners' ),
	'an empty metadata'          => wpcredits_migrate_1_32_1_content( $opened_with( $partners, $cards_span, '<!-- wp:html {"metadata":{}} -->' ), 'page-partners' ),
	'a lock and no metadata'     => wpcredits_migrate_1_32_1_content( $opened_with( $opened_with( $partners, $table_span, '<!-- wp:html {"lock":{"remove":true}} -->' ), $cards_span, '<!-- wp:html {"lock":{"move":true}} -->' ), 'page-partners' ),
);
$both_blocks = array( 'partner-levels', 'partner-glance' );
$hidden_want = array(
	'the cards hidden'           => array( substr_replace( $after, '<!-- wp:wpcredits/partner-levels ' . $hide . ' -->', strpos( $after, $levels_open ), strlen( $levels_open ) ), $both_blocks ),
	'the table hidden and named' => array( substr_replace( $after, '<!-- wp:wpcredits/partner-glance ' . $hide_named . ' /-->', strpos( $after, $glance_void ), strlen( $glance_void ) ), $both_blocks ),
	'both in one block, hidden'  => array( str_replace( array( $levels_open, $glance_void ), array( '<!-- wp:wpcredits/partner-levels ' . $hide . ' -->', '<!-- wp:wpcredits/partner-glance ' . $hide . ' /-->' ), $merged_run['content'] ), $both_blocks ),
	'an emptied grid, hidden'    => array( str_replace( '<!-- wp:wpcredits/partner-levels /-->', '<!-- wp:wpcredits/partner-levels ' . $hide . ' /-->', $emptied_run['content'] ), array( 'partner-levels-emptied', 'partner-glance' ) ),
	'an empty metadata'          => array( $after, $both_blocks ),
	'a lock and no metadata'     => array( $after, $both_blocks ),
);

$hidden_got = array();

foreach ( $hidden_runs as $label => $run ) {
	$hidden_got[ $label ] = array( $run['content'] === $hidden_want[ $label ][0], $run['changed'] );
}

ck( 'a Custom HTML block of 1.31 cards, or of the table, that carries metadata is replaced by a block that carries the same, every other byte as live\'s template migrates; the two blocks of one such block both carry it; a block with an empty metadata, or with a lock and no metadata, is replaced exactly as live\'s is',
	$hidden_got,
	array_map( static function ( $want ) { return array( true, $want[1] ); }, $hidden_want ) );

// What the page draws, with core's block visibility hooked: the hidden cards drew nothing before the
// migration and their block draws nothing after it, so the page shows no levels and the table no
// columns, and the sponsor application lists none, as the page shows; the hidden table is still hidden.
add_filter( 'render_block', 'wpcredits_test_block_visibility', 10, 2 );

$hidden_cards_html = substr( $opened_with( $partners, $cards_span, '<!-- wp:html ' . $hide . ' -->' ), $cards_span[0], $cards_span[1] + strlen( $hide ) + 1 );

wpcredits_test_saved_template( 'page-partners', $hidden_runs['the cards hidden']['content'] );
wpcredits_partner_levels_reset();

$hidden_drawn = array(
	trim( render_block( parse_blocks( $hidden_cards_html )[0] ) ),
	trim( render_block( wpcredits_test_block_in( $hidden_runs['the cards hidden']['content'], 'wpcredits/partner-levels' ) ) ),
	trim( render_block( wpcredits_test_block_in( $hidden_runs['the cards hidden']['content'], 'wpcredits/partner-glance' ) ) ),
	wpcredits_partners_levels_for_form( array() ),
	trim( render_block( wpcredits_test_block_in( $hidden_runs['the table hidden and named']['content'], 'wpcredits/partner-glance' ) ) ),
	'' !== trim( render_block( wpcredits_test_block_in( $after, 'wpcredits/partner-levels' ) ) ),
);

remove_filter( 'render_block', 'wpcredits_test_block_visibility', 10 );
wpcredits_test_saved_template( 'page-partners', $after );
wpcredits_partner_levels_reset();

ck( 'the hidden cards stay hidden: their Custom HTML block drew nothing, and so do the Partner levels block and the table drawn from it, and the sponsor application is handed no level; the hidden table stays hidden; the levels block of a block not hidden draws its cards',
	$hidden_drawn,
	array( '', '', '', array(), '', true ) );

$bare_template = '<!-- wp:paragraph -->' . "\n" . '<p>A Partners page with neither.</p>' . "\n" . '<!-- /wp:paragraph -->';
$bare_run      = wpcredits_migrate_1_32_1_content( $bare_template, 'page-partners' );
$other_run     = wpcredits_migrate_1_32_1_content( $partners, 'page' );

ck( 'a template without the targets is left as it is and reported skipped, each target with its reason; a template of another slug is left whole, even one holding the 1.31 cards',
	array( $bare_run['content'] === $bare_template, $bare_run['changed'], $bare_run['skipped'], $other_run['content'] === $partners, $other_run['changed'], $other_run['skipped'] ),
	array( true, array(), array( 'partner-levels-missing', 'partner-glance-missing' ), true, array(), array( 'other-template' ) ) );

echo "\n=== The runner, once, after 1.32.0's ===\n";

ck( 'the runner is hooked on init after the 1.32.0 runner; functions.php loads its file right after the 1.32.0 migration\'s',
	array(
		array_values( array_map( static function ( $hooked ) { return $hooked[0] . ' ' . $hooked[1]; }, array_filter( $GLOBALS['hooks']['init'] ?? array(), static function ( $hooked ) { return in_array( $hooked[0], array( 'wpcredits_migrate_1_32_0', 'wpcredits_migrate_1_32_1' ), true ); } ) ) ),
		false !== strpos( (string) file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), "require_once get_theme_file_path( 'inc/migrate-1-32-0.php' );\nrequire_once get_theme_file_path( 'inc/migrate-1-32-1.php' );" ),
	),
	array( array( 'wpcredits_migrate_1_32_0 20', 'wpcredits_migrate_1_32_1 21' ), true ) );

// Live's case: the 1.32.0 runner ran, and its record is there; the hub as it migrated it, the Partners
// template as an Administrator saved it. The request is a visitor's (kses on, no one signed in), and the
// hub's newest revision is the template without its svg, as live's 1.32.0 run left it.
$live_record_1_32_0 = array(
	'ran'       => time() - DAY_IN_SECONDS,
	'templates' => array( array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'program-index', 'course-band', 'fit-list' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => 1990 ) ),
);

$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = $live_record_1_32_0;
$GLOBALS['posts']                             = array(
	1643 => new WP_Post( array( 'ID' => 1643, 'post_type' => 'wp_template', 'post_name' => 'page-partners', 'post_status' => 'publish', 'post_author' => 7, 'post_content' => $partners ) ),
	1654 => new WP_Post( array( 'ID' => 1654, 'post_type' => 'wp_template', 'post_name' => 'page-education-hub', 'post_status' => 'publish', 'post_author' => 7, 'post_content' => $hub_live ) ),
	1700 => new WP_Post( array( 'ID' => 1700, 'post_type' => 'wp_template', 'post_name' => 'page-education-hub', 'post_status' => 'publish', 'post_content' => $hub_live ) ),
	1701 => new WP_Post( array( 'ID' => 1701, 'post_type' => 'wp_template', 'post_name' => 'page-partners', 'post_status' => 'publish', 'post_content' => $partners ) ),
);
$GLOBALS['terms']                             = array(
	1643 => 'wpcredits-theme',
	1654 => 'wpcredits-theme',
	1700 => 'another-theme',
	1701 => 'another-theme',
);
$GLOBALS['user']                              = array(
	'id'   => 0,
	'caps' => array(),
);
$GLOBALS['kses_on']                           = true;

$partners_copy = add_revision( 1643, $partners, 7 );
$stripped      = add_revision( 1654, (string) preg_replace( '#<svg\b.*?</svg>#s', '', $hub_live ), 7 );

$GLOBALS['wpcredits_partner_levels'] = array( 'read before the run' );
$GLOBALS['calls']                    = array();

wpcredits_migrate_1_32_1();

$record   = get_option( 'wpcredits_migrated_1_32_1' );
$hub_copy = newest_revision_of( 1654 );

ck( 'the runner migrates this theme\'s saved Partners and Education Hub templates alone: its lock taken first, not autoloaded, before it reads the templates; the Partners template, whose newest revision already holds it, written with kses off; the hub, a revision of it as it was kept first, then written; the option, not autoloaded, and the lock let go of last; another theme\'s copies are not touched',
	array(
		$GLOBALS['calls'],
		$GLOBALS['posts'][1643]->post_content === $after,
		$GLOBALS['posts'][1654]->post_content === $hub_1320,
		$GLOBALS['posts'][1700]->post_content === $hub_live,
		$GLOBALS['posts'][1701]->post_content === $partners,
	),
	array(
		array(
			'add_option wpcredits_migrated_1_32_1_lock autoload false',
			query_for( 'page-partners' ),
			'kses off',
			'update 1643 to ' . sha1( $after ),
			'kses on',
			query_for( 'page-education-hub' ),
			'kses off',
			'revision 1654 of ' . sha1( $hub_live ),
			'kses on',
			'kses off',
			'update 1654 to ' . sha1( $hub_1320 ),
			'kses on',
			'add_option wpcredits_migrated_1_32_1 autoload false',
			'delete_option wpcredits_migrated_1_32_1_lock',
		),
		true,
		true,
		true,
		true,
	) );

ck( 'it records what it did for each template and where the copy of each as it was is: the revisions, by their IDs; the hub\'s is a new revision byte for byte the template, svg and all, not the stripped one that was newest; the Partners template\'s is the newest that already held it',
	array(
		is_array( $record ) ? array_keys( $record ) : null,
		$record['templates'] ?? null,
		$hub_copy && $hub_copy->ID !== $stripped->ID && $hub_copy->post_content === $hub_live,
		get_post( $record['templates'][1]['revision'] ?? 0 ) ? get_post( $record['templates'][1]['revision'] )->post_content === $hub_live : null,
		get_post( $record['templates'][0]['revision'] ?? 0 ) ? get_post( $record['templates'][0]['revision'] )->post_content === $partners : null,
	),
	array(
		array( 'ran', 'templates' ),
		array(
			array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array( 'partner-levels', 'partner-glance' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => $partners_copy->ID ),
			array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'program-index-marked' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => $hub_copy ? $hub_copy->ID : null ),
		),
		true,
		true,
		true,
	) );

ck( 'the levels read earlier in the request are forgotten once the Partners template is written, so a read after the run reads it again; the 1.32.0 record is left as it was',
	array( isset( $GLOBALS['wpcredits_partner_levels'] ), get_option( 'wpcredits_migrated_1_32_0' ) === $live_record_1_32_0 ),
	array( false, true ) );

$GLOBALS['calls'] = array();
wpcredits_migrate_1_32_1();
run_init_migrations();

ck( 'once: with both options set, a second request does nothing at all, either runner',
	$GLOBALS['calls'], array() );

$GLOBALS['user'] = array(
	'id'   => 1,
	'caps' => array( 'edit_theme_options' ),
);

echo "\n=== Each runner once, in order, neither undoing the other ===\n";

$migrated_partners = $GLOBALS['posts'][1643]->post_content;
$migrated_hub      = $GLOBALS['posts'][1654]->post_content;

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'], $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );
$GLOBALS['calls'] = array();
wpcredits_migrate_1_32_1();

ck( 'the 1.32.1 runner waits for the 1.32.0 runner: before its option is set, it reads nothing, writes nothing and records nothing',
	array( $GLOBALS['calls'], array_key_exists( 'wpcredits_migrated_1_32_1', $GLOBALS['opts'] ) ),
	array( array(), false ) );

// A fresh install: no saved template at all, neither runner run.
$all_posts        = $GLOBALS['posts'];
$GLOBALS['posts'] = array();
$GLOBALS['calls'] = array();
$fresh_order      = run_init_migrations();

ck( 'on a fresh install, with no saved template, both run on the first request, the 1.32.0 runner first, each in its own lock, and each records that it reached nothing, which the notice does not tell',
	array( $fresh_order, $GLOBALS['calls'], get_option( 'wpcredits_migrated_1_32_0' )['templates'] ?? null, get_option( 'wpcredits_migrated_1_32_1' )['templates'] ?? null, wpcredits_migrate_1_32_1_summary( get_option( 'wpcredits_migrated_1_32_1' ) ) ),
	array(
		array( 'wpcredits_migrate_1_32_0 20', 'wpcredits_migrate_1_32_1 21' ),
		array(
			'add_option wpcredits_migrated_1_32_0_lock autoload false',
			query_for( 'page-education-hub' ),
			'add_option wpcredits_migrated_1_32_0 autoload false',
			'delete_option wpcredits_migrated_1_32_0_lock',
			'add_option wpcredits_migrated_1_32_1_lock autoload false',
			query_for( 'page-partners' ),
			query_for( 'page-education-hub' ),
			'add_option wpcredits_migrated_1_32_1 autoload false',
			'delete_option wpcredits_migrated_1_32_1_lock',
		),
		array(),
		array(),
		'',
	) );

// A site that updates from 1.31 straight to 1.32.1: the 1.32.0 runner never ran, and both saved
// templates are as 1.31 saved them.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'], $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['posts'] = $all_posts;
drop_revisions();

$GLOBALS['posts'][1643]->post_content = $partners;
$GLOBALS['posts'][1654]->post_content = $hub_saved;
$GLOBALS['calls']                     = array();

run_init_migrations();

ck( 'where 1.32.0\'s runner never ran, both run on the first request, in order: the 1.32.0 runner migrates the hub (its index written marked) and records it; then the 1.32.1 runner migrates the Partners template and finds the hub\'s index already marked',
	array(
		$GLOBALS['calls'],
		$GLOBALS['posts'][1654]->post_content === $hub_1320,
		$GLOBALS['posts'][1643]->post_content === $after,
		get_option( 'wpcredits_migrated_1_32_0' )['templates'][0]['changed'] ?? null,
		array_map( static function ( $entry ) { return array( $entry['slug'], $entry['changed'], $entry['skipped'] ); }, get_option( 'wpcredits_migrated_1_32_1' )['templates'] ?? array() ),
	),
	array(
		array(
			'add_option wpcredits_migrated_1_32_0_lock autoload false',
			query_for( 'page-education-hub' ),
			'kses off',
			'revision 1654 of ' . sha1( $hub_saved ),
			'kses on',
			'kses off',
			'update 1654 to ' . sha1( $hub_1320 ),
			'kses on',
			'add_option wpcredits_migrated_1_32_0 autoload false',
			'delete_option wpcredits_migrated_1_32_0_lock',
			'add_option wpcredits_migrated_1_32_1_lock autoload false',
			query_for( 'page-partners' ),
			'kses off',
			'revision 1643 of ' . sha1( $partners ),
			'kses on',
			'kses off',
			'update 1643 to ' . sha1( $after ),
			'kses on',
			query_for( 'page-education-hub' ),
			'add_option wpcredits_migrated_1_32_1 autoload false',
			'delete_option wpcredits_migrated_1_32_1_lock',
		),
		true,
		true,
		array( 'program-index', 'course-band', 'fit-list' ),
		array(
			array( 'page-partners', array( 'partner-levels', 'partner-glance' ), array() ),
			array( 'page-education-hub', array(), array( 'program-index-marked-done' ) ),
		),
	) );

$GLOBALS['calls'] = array();
run_init_migrations();
$second_request = $GLOBALS['calls'];

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );
$GLOBALS['calls'] = array();
wpcredits_migrate_1_32_0();
$rerun_1_32_0 = $GLOBALS['calls'];

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );
$GLOBALS['calls'] = array();
wpcredits_migrate_1_32_1();
$rerun_1_32_1 = $GLOBALS['calls'];

ck( 'each ran once: the next request does nothing; and neither undoes the other, should an option be lost: the 1.32.0 runner run again over both finds nothing to replace in the hub and leaves the Partners template alone, the 1.32.1 runner run again writes nothing either',
	array(
		$second_request,
		count( preg_grep( '/^update /', $rerun_1_32_0 ) ),
		get_option( 'wpcredits_migrated_1_32_0' )['templates'][0]['skipped'] ?? null,
		count( preg_grep( '/^update /', $rerun_1_32_1 ) ),
		$GLOBALS['posts'][1654]->post_content === $hub_1320,
		$GLOBALS['posts'][1643]->post_content === $after,
	),
	array( array(), 0, array( 'program-index-done', 'fit-list-done' ), 0, true, true ) );

// The 1.32.0 runner ran and failed: its option holds the failure, and the hub is as 1.31 saved it. The
// 1.32.1 runner does not try the 1.32.0 migration again, and its own on the hub has nothing to do.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'], $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );
drop_revisions();

$failed_1_32_0                                = array(
	'ran'       => time() - HOUR_IN_SECONDS,
	'templates' => array(),
	'failed'    => 'update-failed',
);
$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = $failed_1_32_0;
$GLOBALS['posts'][1643]->post_content         = $partners;
$GLOBALS['posts'][1654]->post_content         = $hub_saved;
$GLOBALS['calls']                             = array();

run_init_migrations();

$after_failure = get_option( 'wpcredits_migrated_1_32_1' );

ck( 'where 1.32.0\'s runner failed, it is not run again, and the 1.32.1 runner migrates the Partners template and leaves the hub as it was, saying its index is still the 1.31 one; the 1.32.0 record keeps its failure',
	array(
		count( preg_grep( '/^update 1654 /', $GLOBALS['calls'] ) ),
		$GLOBALS['posts'][1654]->post_content === $hub_saved,
		$GLOBALS['posts'][1643]->post_content === $after,
		array_map( static function ( $entry ) { return array( $entry['slug'], $entry['changed'], $entry['skipped'] ); }, $after_failure['templates'] ?? array() ),
		get_option( 'wpcredits_migrated_1_32_0' ) === $failed_1_32_0,
	),
	array(
		0,
		true,
		true,
		array(
			array( 'page-partners', array( 'partner-levels', 'partner-glance' ), array() ),
			array( 'page-education-hub', array(), array( 'program-index-legacy' ) ),
		),
		true,
	) );

echo "\n=== Where the copy is kept, and a record that cannot be stored ===\n";

// Where revisions are off the record is the only copy of the template as it was. A database that refuses
// to store the record holding it (a value too large for it, or characters its table cannot hold) must
// not leave a record that says the copy was kept: the record stored instead says it was lost.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['posts'][1643]->post_content = $partners;
$GLOBALS['posts'][1654]->post_content = $hub_live;
$GLOBALS['revisions_off']             = true;
$GLOBALS['refuse_copies']             = true;
$GLOBALS['calls']                     = array();

wpcredits_migrate_1_32_1();

$GLOBALS['refuse_copies'] = false;
$GLOBALS['revisions_off'] = false;
$lost_record              = get_option( 'wpcredits_migrated_1_32_1' );

ck( 'a record that holds the only copies and is refused is not taken as stored: a record without the copies is stored in its place, each template\'s copy said lost, then the lock let go of, and the notice says the template as it was could not be kept, never that it was',
	array(
		array_slice( $GLOBALS['calls'], -3 ),
		array_map( static function ( $entry ) { return array( $entry['slug'], $entry['kept'], array_key_exists( 'before', $entry ) ); }, $lost_record['templates'] ?? array() ),
		false !== strpos( wpcredits_migrate_1_32_1_summary( $lost_record ), 'The template as it was before could not be kept, in its revisions or in the theme\'s migration record.' ),
		false !== strpos( wpcredits_migrate_1_32_1_summary( $lost_record ), 'is kept' ),
	),
	array(
		array( 'add_option wpcredits_migrated_1_32_1 refused', 'add_option wpcredits_migrated_1_32_1 autoload false', 'delete_option wpcredits_migrated_1_32_1_lock' ),
		array( array( 'page-partners', 'lost', false ), array( 'page-education-hub', 'lost', false ) ),
		true,
		false,
	) );

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['posts'][1643]->post_content = $partners;
$GLOBALS['posts'][1654]->post_content = $hub_live;
$GLOBALS['revisions_off']             = true;
$GLOBALS['calls']                     = array();

wpcredits_migrate_1_32_1();

$GLOBALS['revisions_off'] = false;
$record_copy              = get_option( 'wpcredits_migrated_1_32_1' );

ck( 'a record that holds the only copies and is stored keeps each template as it was, byte for byte, in `before`, and the notice says so',
	array(
		count( preg_grep( '/^add_option wpcredits_migrated_1_32_1 /', $GLOBALS['calls'] ) ),
		array_map( static function ( $entry ) { return array( $entry['kept'], $entry['revision'] ); }, $record_copy['templates'] ?? array() ),
		( $record_copy['templates'][0]['before'] ?? null ) === $partners,
		( $record_copy['templates'][1]['before'] ?? null ) === $hub_live,
		false !== strpos( wpcredits_migrate_1_32_1_summary( $record_copy ), 'The template as it was before is kept in the theme\'s migration record.' ),
	),
	array( 1, array( array( 'record', 0 ), array( 'record', 0 ) ), true, true, true ) );

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );
drop_revisions();

$GLOBALS['posts'][1643]->post_content = $partners;
$GLOBALS['posts'][1654]->post_content = $hub_live;
$GLOBALS['refuse_copies']             = true;
$GLOBALS['calls']                     = array();

wpcredits_migrate_1_32_1();

$GLOBALS['refuse_copies'] = false;

ck( 'where the copies are revisions the record holds none, so it is stored at the first try',
	array( preg_grep( '/^add_option wpcredits_migrated_1_32_1 /', $GLOBALS['calls'] ), array_column( get_option( 'wpcredits_migrated_1_32_1' )['templates'] ?? array(), 'kept' ) ),
	array( array( count( $GLOBALS['calls'] ) - 2 => 'add_option wpcredits_migrated_1_32_1 autoload false' ), array( 'revision', 'revision' ) ) );

// Live's case on a site that keeps one revision (WP_POST_REVISIONS): core keeps a revision of each write
// and deletes the oldest beyond the limit, so the Partners template's newest revision, which held it,
// and the hub's copy, made for it, are both gone once the templates are written. Each is read back, and
// the record keeps the template as it was instead; then a stale tab's save, repaired on the same site.
$limited_run = static function ( $keep ) use ( $partners, $hub_live ) {
	unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );
	drop_revisions();

	$GLOBALS['posts'][1643]->post_content = $partners;
	$GLOBALS['posts'][1654]->post_content = $hub_live;
	$GLOBALS['revisions_to_keep']         = $keep;

	add_revision( 1643, $partners, 7 );
	add_revision( 1654, (string) preg_replace( '#<svg\b.*?</svg>#s', '', $hub_live ), 7 );
	wpcredits_migrate_1_32_1();

	$GLOBALS['revisions_to_keep'] = null;
	$limited                      = get_option( 'wpcredits_migrated_1_32_1' );

	// Each template's slug, where its copy is, and whether that copy, the record's or the revision's, holds
	// the template as it was byte for byte.
	return array(
		array_map(
			static function ( $entry ) use ( $partners, $hub_live ) {
				$was  = 'page-partners' === $entry['slug'] ? $partners : $hub_live;
				$copy = array_key_exists( 'before', $entry ) ? $entry['before'] : ( get_post( $entry['revision'] ?? 0 ) ? get_post( $entry['revision'] )->post_content : null );

				return array( $entry['slug'], $entry['kept'], $entry['revision'], $copy === $was );
			},
			$limited['templates'] ?? array()
		),
		substr_count( wpcredits_migrate_1_32_1_summary( $limited ), 'The template as it was before is kept in the theme\'s migration record.' ),
		substr_count( wpcredits_migrate_1_32_1_summary( $limited ), 'The template as it was before is kept in its revisions.' ),
	);
};

$one_kept       = $limited_run( 1 );
$none_limit     = $limited_run( -1 );
$stale_partners = str_replace( '<li>Named in launch communications</li>', '<li>Named in every launch communication</li>', $partners );

$GLOBALS['posts'][1643]->post_content = $stale_partners;
$GLOBALS['revisions_to_keep']         = 1;

do_action( 'wp_after_insert_post', 1643, get_post( 1643 ), true, null );

$GLOBALS['revisions_to_keep'] = null;
$limited_repairs              = get_option( 'wpcredits_migrated_1_32_1' )['repairs'] ?? array();
$limited_repair               = $limited_repairs ? $limited_repairs[ count( $limited_repairs ) - 1 ] : array();

ck( 'on a site that keeps one revision, the runner reads each copy back after the write, finds it deleted, and records it in the record instead, `before` byte for byte the template, which the notice says for both; on a site with no limit both are revisions that hold the templates, as before; a stale tab\'s save repaired where one revision is kept is recorded the same way',
	array( $one_kept, $none_limit, array( $limited_repair['kept'] ?? null, $limited_repair['revision'] ?? null, ( $limited_repair['before'] ?? null ) === $stale_partners ) ),
	array(
		array( array( array( 'page-partners', 'record', 0, true ), array( 'page-education-hub', 'record', 0, true ) ), 2, 0 ),
		array( array( array( 'page-partners', 'revision', $none_limit[0][0][2], true ), array( 'page-education-hub', 'revision', $none_limit[0][1][2], true ) ), 0, 2 ),
		array( 'record', 0, true ),
	) );

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['posts'][1643]->post_content = $partners;
$GLOBALS['fail_update']               = true;
wpcredits_migrate_1_32_1();
$GLOBALS['fail_update'] = false;

ck( 'a write that fails leaves the template as it was and is recorded as failed, nothing claimed changed',
	array( $GLOBALS['posts'][1643]->post_content === $partners, get_option( 'wpcredits_migrated_1_32_1' )['templates'][0] ?? null ),
	array( true, array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array(), 'skipped' => array( 'update-failed' ), 'kept' => '' ) ) );

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['throw_terms'] = true;
wpcredits_migrate_1_32_1();
$GLOBALS['throw_terms'] = false;
$thrown                 = get_option( 'wpcredits_migrated_1_32_1' );

ck( 'a run that throws is caught and recorded as a failure, which the notice tells, and the option is set all the same',
	array( $thrown['templates'] ?? null, $thrown['failed'] ?? null, wpcredits_migrate_1_32_1_summary( $thrown ) ),
	array( array(), 'update-failed', 'The theme\'s update to 1.32.1 could not finish updating the saved Partners and Education Hub templates; a template it did not reach is as it was.' ) );

echo "\n=== The lock ===\n";

// Two requests at once on live's site, the 1.32.0 runner done: the second comes while the first writes the
// Partners template, before it has stored its option, and runs init's migrations as every request does.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );
drop_revisions();

$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = $live_record_1_32_0;
$GLOBALS['posts'][1643]->post_content         = $partners;
$GLOBALS['posts'][1654]->post_content         = $hub_live;
$meanwhile                                    = array();
$second_request                               = static function () use ( &$meanwhile ) {
	$from = count( $GLOBALS['calls'] );

	run_init_migrations();

	$meanwhile[] = array( array_slice( $GLOBALS['calls'], $from ), is_int( $GLOBALS['opts']['wpcredits_migrated_1_32_1_lock'] ?? null ), array_key_exists( 'wpcredits_migrated_1_32_1', $GLOBALS['opts'] ) );
};

add_action( 'wp_after_insert_post', $second_request, 5 );

$GLOBALS['calls'] = array();
wpcredits_migrate_1_32_1();

remove_filter( 'wp_after_insert_post', $second_request, 5 );

ck( 'a second request while the 1.32.1 runner runs, at each of its two writes, finds its lock and does nothing, either runner: no template read, nothing written, no record; the first writes each template once and records both, and lets go of the lock',
	array(
		$meanwhile,
		count( preg_grep( '/^update /', $GLOBALS['calls'] ) ),
		array_column( get_option( 'wpcredits_migrated_1_32_1' )['templates'] ?? array(), 'changed' ),
		array_key_exists( 'wpcredits_migrated_1_32_1_lock', $GLOBALS['opts'] ),
	),
	array(
		array( array( array(), true, false ), array( array(), true, false ) ),
		2,
		array( array( 'partner-levels', 'partner-glance' ), array( 'program-index-marked' ) ),
		false,
	) );

// On a site that updates from 1.31 straight to 1.32.1, a request while the 1.32.0 runner runs: that one
// finds the 1.32.0 lock, and the 1.32.1 runner waits for the 1.32.0 option, its own lock not taken.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'], $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['opts']['wpcredits_migrated_1_32_0_lock'] = time() - MINUTE_IN_SECONDS;
$GLOBALS['calls']                                  = array();
run_init_migrations();
$while_1_32_0 = array( $GLOBALS['calls'], array_key_exists( 'wpcredits_migrated_1_32_0', $GLOBALS['opts'] ), array_key_exists( 'wpcredits_migrated_1_32_1', $GLOBALS['opts'] ), array_key_exists( 'wpcredits_migrated_1_32_1_lock', $GLOBALS['opts'] ) );

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0_lock'] );

// A lock a request left behind when it died is taken over once it is ten minutes old.
$GLOBALS['opts']['wpcredits_migrated_1_32_0']      = $live_record_1_32_0;
$GLOBALS['opts']['wpcredits_migrated_1_32_1_lock'] = time() - 11 * MINUTE_IN_SECONDS;
$GLOBALS['posts'][1643]->post_content              = $partners;
$GLOBALS['posts'][1654]->post_content              = $hub_live;
$GLOBALS['calls']                                  = array();
wpcredits_migrate_1_32_1();
$stale_1_32_1 = array( $GLOBALS['calls'][0] ?? null, end( $GLOBALS['calls'] ), array_column( get_option( 'wpcredits_migrated_1_32_1' )['templates'] ?? array(), 'changed' ), array_key_exists( 'wpcredits_migrated_1_32_1_lock', $GLOBALS['opts'] ) );

// And let go of on the failure path: a run that throws.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['throw_terms'] = true;
$GLOBALS['calls']       = array();
wpcredits_migrate_1_32_1();
$GLOBALS['throw_terms'] = false;
$failed_1_32_1          = array( get_option( 'wpcredits_migrated_1_32_1' )['failed'] ?? null, array_slice( $GLOBALS['calls'], -2 ), array_key_exists( 'wpcredits_migrated_1_32_1_lock', $GLOBALS['opts'] ) );

ck( 'while the 1.32.0 runner holds its lock, neither runner does anything, the 1.32.1 lock not even taken; a 1.32.1 lock eleven minutes old is taken over, the run done and the lock let go of; a run that throws records the failure and lets go of the lock',
	array( $while_1_32_0, $stale_1_32_1, $failed_1_32_1 ),
	array(
		array( array(), false, false, false ),
		array( 'update_option wpcredits_migrated_1_32_1_lock autoload false', 'delete_option wpcredits_migrated_1_32_1_lock', array( array( 'partner-levels', 'partner-glance' ), array( 'program-index-marked' ) ), false ),
		array( 'update-failed', array( 'add_option wpcredits_migrated_1_32_1 autoload false', 'delete_option wpcredits_migrated_1_32_1_lock' ), false ),
	) );

// The window the lock leaves on its own: this request finds no 1.32.1 option, then another request stores
// its own and lets go of its lock, and only then is this one's lock granted. This request's object cache,
// as core keeps it, still says the option is missing, so the runner reads both options again, from the
// database, and stops there, its lock let go of.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = $live_record_1_32_0;
$GLOBALS['posts'][1643]->post_content         = $partners;
$GLOBALS['posts'][1654]->post_content         = $hub_live;
$GLOBALS['option_cache']                      = true;
$GLOBALS['wp_cache']                          = array();
$others_record                                = array(
	'ran'       => time(),
	'templates' => array( array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array( 'partner-levels', 'partner-glance' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => 1 ) ),
);

$GLOBALS['before_add']['wpcredits_migrated_1_32_1_lock'] = static function () use ( $others_record ) {
	$GLOBALS['opts']['wpcredits_migrated_1_32_1'] = $others_record;
};

$GLOBALS['calls'] = array();
wpcredits_migrate_1_32_1();
$granted_late = array( $GLOBALS['calls'], ( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] ?? null ) === $others_record, $GLOBALS['posts'][1643]->post_content === $partners && $GLOBALS['posts'][1654]->post_content === $hub_live, array_key_exists( 'wpcredits_migrated_1_32_1_lock', $GLOBALS['opts'] ) );

// A run that outlives its ten minutes: another request takes its lock over during the first one's write
// of the Partners template, in the database behind the first one's cache; the first, done at last, leaves
// the lock the other holds now.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['posts'][1643]->post_content = $partners;
$GLOBALS['posts'][1654]->post_content = $hub_live;
$GLOBALS['wp_cache']                  = array();
$others_time                          = null;
$take_over                            = static function () use ( &$others_time ) {
	if ( null === $others_time ) {
		$others_time = (int) $GLOBALS['opts']['wpcredits_migrated_1_32_1_lock'] + 11 * MINUTE_IN_SECONDS;

		$GLOBALS['opts']['wpcredits_migrated_1_32_1_lock'] = $others_time;
	}
};

add_action( 'wp_after_insert_post', $take_over, 5 );

$GLOBALS['calls'] = array();
wpcredits_migrate_1_32_1();

remove_filter( 'wp_after_insert_post', $take_over, 5 );

$outlived                = array( array_column( get_option( 'wpcredits_migrated_1_32_1' )['templates'] ?? array(), 'changed' ), in_array( 'delete_option wpcredits_migrated_1_32_1_lock', $GLOBALS['calls'], true ), null !== $others_time && ( $GLOBALS['opts']['wpcredits_migrated_1_32_1_lock'] ?? null ) === $others_time );
$GLOBALS['option_cache'] = false;
$GLOBALS['wp_cache']     = array();

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1_lock'] );

// And when storing the record throws, as a database layer can, the lock is let go of on the way out.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );

$GLOBALS['posts'][1643]->post_content = $partners;
$GLOBALS['posts'][1654]->post_content = $hub_live;
$GLOBALS['throw_option']              = 'wpcredits_migrated_1_32_1';
$GLOBALS['calls']                     = array();
$record_throws                        = 'returned';

try {
	wpcredits_migrate_1_32_1();
} catch ( Throwable $error ) {
	$record_throws = 'threw';
}

$GLOBALS['throw_option'] = false;
$record_threw            = array( $record_throws, end( $GLOBALS['calls'] ), array_key_exists( 'wpcredits_migrated_1_32_1', $GLOBALS['opts'] ), array_key_exists( 'wpcredits_migrated_1_32_1_lock', $GLOBALS['opts'] ) );

ck( 'a lock granted after another request stored the 1.32.1 option, which this request\'s cache still says is missing, is let go of with no template read and nothing written, the other\'s record kept; a run whose lock another request took over meanwhile records what it did and leaves that request\'s lock in place; a record whose storing throws goes on throwing, unstored, and the lock is let go of',
	array( $granted_late, $outlived, $record_threw ),
	array(
		array( array( 'add_option wpcredits_migrated_1_32_1_lock autoload false', 'delete_option wpcredits_migrated_1_32_1_lock' ), true, true, false ),
		array( array( array( 'partner-levels', 'partner-glance' ), array( 'program-index-marked' ) ), false, true ),
		array( 'threw', 'delete_option wpcredits_migrated_1_32_1_lock', false, false ),
	) );

// The migrated site again, as the checks below begin from.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] );
drop_revisions();

$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = $live_record_1_32_0;
$GLOBALS['posts'][1643]->post_content         = $partners;
$GLOBALS['posts'][1654]->post_content         = $hub_live;

wpcredits_migrate_1_32_1();

echo "\n=== A save from an editor tab opened before the update ===\n";

// An editor tab opened before the update holds the template as it was; saved after the migration, it
// writes the 1.31 Custom HTML back, with whatever else the Administrator changed in it. The save is
// repaired at once: the Custom HTML becomes the blocks, every other edit stays.
$stale_save = str_replace(
	array( '<h2 class="wp-block-heading wpc-partners-pilot__title">2027 is a pilot year</h2>', '<li>Named in launch communications</li>' ),
	array( '<h2 class="wp-block-heading wpc-partners-pilot__title">2027 is our pilot year</h2>', '<li>Named in every launch communication</li>' ),
	$saved
);
$stale_run = wpcredits_migrate_1_32_1_content( $stale_save, 'page-partners' );

$GLOBALS['posts'][1643]->post_content = $stale_save;
$GLOBALS['wpcredits_partner_levels']  = array( 'read before the save' );
$GLOBALS['calls']                     = array();
$GLOBALS['fired']                     = array();
$repairs_1_32_0                       = get_option( 'wpcredits_migrated_1_32_0' )['repairs'] ?? array();

do_action( 'wp_after_insert_post', 1643, get_post( 1643 ), true, null );

$repairs = get_option( 'wpcredits_migrated_1_32_1' )['repairs'] ?? array();

ck( 'a save of this theme\'s Partners template that holds the 1.31 Custom HTML is migrated at once: a revision of the save, the write, written once though the write fires the hook again, and recorded with the 1.32.1 migration, the 1.32.0 record untouched',
	array(
		$GLOBALS['calls'],
		$GLOBALS['posts'][1643]->post_content === $stale_run['content'],
		$GLOBALS['fired']['wp_after_insert_post'] ?? 0,
		count( $repairs ),
		$repairs ? array_slice( $repairs[0], 0, 4 ) : null,
		( get_option( 'wpcredits_migrated_1_32_0' )['repairs'] ?? array() ) === $repairs_1_32_0,
	),
	array(
		array(
			'kses off',
			'revision 1643 of ' . sha1( $stale_save ),
			'kses on',
			'kses off',
			'update 1643 to ' . sha1( $stale_run['content'] ),
			'kses on',
			'update_option wpcredits_migrated_1_32_1 autoload false',
		),
		true,
		2,
		1,
		array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array( 'partner-levels', 'partner-glance' ), 'skipped' => array() ),
		true,
	) );

$stale_cards = html_block_with( $stale_save, 'wpc-partners-levels__grid' );
$stale_table = html_block_with( $stale_save, 'wpc-partners-glance__table' );
$repaired    = $GLOBALS['posts'][1643]->post_content;

ck( 'the repair keeps the Administrator\'s other edits: the heading they changed, byte for byte everything around the two blocks, and the item they reworded in the 1.31 cards, read into the levels; the levels read in the request are forgotten',
	array(
		pieces_around( $repaired, array( span_of( $repaired, 'wpcredits/partner-levels' ), span_of( $repaired, 'wpcredits/partner-glance' ) ) ) === pieces_around( $stale_save, array( $stale_cards, $stale_table ) ),
		substr_count( $repaired, '2027 is our pilot year' ),
		wpcredits_test_block_in( $repaired, 'wpcredits/partner-levels' )['innerBlocks'][4]['attrs']['items'] ?? null,
		isset( $GLOBALS['wpcredits_partner_levels'] ),
	),
	array( true, 1, array( 'Named as program partner on the program you fund, with the exclusive role in that program', 'Named in every launch communication', 'A report specific to that program' ), false ) );

// A hub tab opened after 1.32.0 and before 1.32.1 saves the container index without the mark, with an
// edit of its own; one opened before 1.32.0 saves the 1.31 index and Custom HTML.
$stale_hub = str_replace( 'Pick the program that fits where you are today, then keep going.', 'Pick the program that fits you today, then keep going.', $hub_live );

$GLOBALS['posts'][1654]->post_content = $stale_hub;
$GLOBALS['calls']                     = array();

do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );

$hub_repairs = get_option( 'wpcredits_migrated_1_32_1' )['repairs'] ?? array();

ck( 'a save of the hub from a tab opened before 1.32.1, its index unmarked, is repaired at once: the index gains `"editable":true` and nothing else changes, the Administrator\'s edit kept',
	array(
		count( preg_grep( '/^update /', $GLOBALS['calls'] ) ),
		$GLOBALS['posts'][1654]->post_content === str_replace( $bare_open, $marked_open, $stale_hub ),
		substr_count( $GLOBALS['posts'][1654]->post_content, 'Pick the program that fits you today, then keep going.' ),
		$hub_repairs ? array_slice( $hub_repairs[ count( $hub_repairs ) - 1 ], 0, 4 ) : null,
	),
	array( 1, true, 1, array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'program-index-marked' ), 'skipped' => array( 'program-index-done', 'fit-list-done' ) ) ) );

$GLOBALS['posts'][1654]->post_content = $hub_saved;
$GLOBALS['calls']                     = array();

do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );

ck( 'a save of the hub from a tab opened before 1.32.0 runs both migrations at once, in order, in one write: the hub as the 1.32.0 migration writes it, its index marked',
	array( count( preg_grep( '/^update /', $GLOBALS['calls'] ) ), $GLOBALS['posts'][1654]->post_content === $hub_1320 ),
	array( 1, true ) );

$GLOBALS['calls'] = array();

do_action( 'wp_after_insert_post', 1643, get_post( 1643 ), true, null );
do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );
do_action( 'wp_after_insert_post', 1700, get_post( 1700 ), true, null );
do_action( 'wp_after_insert_post', 1701, get_post( 1701 ), true, null );

ck( 'a save of either migrated template is left alone, and so are another theme\'s copies, though they hold the 1.31 markup',
	$GLOBALS['calls'], array() );

// A tab opened before the update on a template whose cards an Administrator had removed saves the table
// alone: the save is left as it is, the table still shown. One on a template whose cards were emptied
// saves the empty grid: it is repaired to the emptied levels block, not the theme's levels.
$GLOBALS['posts'][1643]->post_content = $table_only;
$GLOBALS['calls']                     = array();

do_action( 'wp_after_insert_post', 1643, get_post( 1643 ), true, null );

$table_only_save = array( $GLOBALS['calls'], $GLOBALS['posts'][1643]->post_content === $table_only );

$GLOBALS['posts'][1643]->post_content = $emptied_cards;
$GLOBALS['calls']                     = array();

do_action( 'wp_after_insert_post', 1643, get_post( 1643 ), true, null );

$emptied_repairs = get_option( 'wpcredits_migrated_1_32_1' )['repairs'] ?? array();

ck( 'a stale tab\'s save of the table without the cards writes nothing and leaves the table whole; one of the emptied cards is repaired at once to the emptied levels block, with no level in it, and recorded',
	array(
		$table_only_save,
		count( preg_grep( '/^update /', $GLOBALS['calls'] ) ),
		$GLOBALS['posts'][1643]->post_content === $emptied_run['content'],
		substr_count( $GLOBALS['posts'][1643]->post_content, 'wp:wpcredits/partner-level ' ),
		$emptied_repairs ? array_slice( $emptied_repairs[ count( $emptied_repairs ) - 1 ], 0, 4 ) : null,
	),
	array(
		array( array(), true ),
		1,
		true,
		0,
		array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array( 'partner-levels-emptied', 'partner-glance' ), 'skipped' => array() ),
	) );

// Where revisions are off the repair's record holds the only copy too.
$GLOBALS['posts'][1643]->post_content = $stale_save;
$GLOBALS['revisions_off']             = true;
$GLOBALS['refuse_copies']             = true;
$GLOBALS['calls']                     = array();

do_action( 'wp_after_insert_post', 1643, get_post( 1643 ), true, null );

$GLOBALS['refuse_copies'] = false;
$GLOBALS['revisions_off'] = false;
$lost_repairs             = get_option( 'wpcredits_migrated_1_32_1' )['repairs'] ?? array();
$lost_repair              = $lost_repairs ? $lost_repairs[ count( $lost_repairs ) - 1 ] : array();

ck( 'a repair whose record holds the only copy and is refused is recorded with the copy said lost, never kept',
	array( array_slice( $GLOBALS['calls'], -2 ), $lost_repair['kept'] ?? null, array_key_exists( 'before', $lost_repair ), $GLOBALS['posts'][1643]->post_content === $stale_run['content'] ),
	array( array( 'update_option wpcredits_migrated_1_32_1 refused', 'update_option wpcredits_migrated_1_32_1 autoload false' ), 'lost', false, true ) );

echo "\n=== The notice ===\n";

$notice_for = static function ( array $caps, $user ) {
	$GLOBALS['screen']       = 'site-editor';
	$GLOBALS['user']         = array(
		'id'   => $user,
		'caps' => $caps,
	);
	$GLOBALS['inline']       = array();
	$GLOBALS['option_reads'] = array();

	wpcredits_migrate_1_32_0_notice();

	return isset( $GLOBALS['inline']['wpcredits-migration-1-32-0'] ) ? implode( '', $GLOBALS['inline']['wpcredits-migration-1-32-0'] ) : '';
};

/**
 * The notice's text, read from its script.
 *
 * @param string $script The inline script.
 * @return string|null
 */
function notice_text( $script ) {
	return preg_match( '/createNotice\( "info", ("(?:[^"\\\\]|\\\\.)*")/', $script, $found ) ? json_decode( $found[1] ) : null;
}

$GLOBALS['opts']['wpcredits_migrated_1_32_1'] = array_merge( $record, array( 'ran' => time() ) );

$both_records = array( get_option( 'wpcredits_migrated_1_32_0' ), get_option( 'wpcredits_migrated_1_32_1' ) );
$both         = $notice_for( array( 'edit_theme_options' ), 1 );
$live_1_32_1  = 'The theme\'s update to 1.32.1 changed the saved Partners template, so its partnership levels are edited here and the sponsor application shows the same ones: the partnership levels are now the Partner levels block, read from its Custom HTML block; the Levels at a glance table is now its own block, drawn from those levels. The template as it was before is kept in its revisions. The theme\'s update to 1.32.1 changed the saved Education Hub template: its Program index is now marked as edited in the Site Editor, so removing every card from it leaves it empty. The template as it was before is kept in its revisions.';

ck( 'on the Site Editor, an Administrator who has dismissed neither is told both migrations\' sentences in one notice, the 1.32.0 record\'s first; the 1.32.1 record\'s, for live\'s templates, say what changed in each and where the copies are',
	array(
		notice_text( $both ),
		wpcredits_migrate_1_32_1_summary( $both_records[1] ),
		false !== strpos( $both, 'body.append( "told", "wpcredits_migrated_1_32_0,wpcredits_migrated_1_32_1" );' ),
	),
	array( wpcredits_migrate_1_32_0_summary( $both_records[0] ) . ' ' . $live_1_32_1, $live_1_32_1, true ) );

$GLOBALS['user_meta'][2]['wpcredits_migrated_1_32_0_seen'] = time() - HOUR_IN_SECONDS;

$only_new = $notice_for( array( 'edit_theme_options' ), 2 );

ck( 'an Administrator who dismissed the 1.32.0 notice is told the 1.32.1 sentences alone, and the 1.32.0 option is not even read for them',
	array( notice_text( $only_new ), false !== strpos( $only_new, 'body.append( "told", "wpcredits_migrated_1_32_1" );' ), count( array_keys( $GLOBALS['option_reads'], 'wpcredits_migrated_1_32_0', true ) ) ),
	array( $live_1_32_1, true, 0 ) );

/**
 * The dismissal's request, as the notice sends it: the reader's own nonce, and what the notice told.
 *
 * @param int         $user The reader.
 * @param string|null $told What the notice told, as its script sends it; null for a notice printed by
 *                          1.32.0, whose script sends nothing.
 * @return array What the request answered.
 */
function dismissed_with( $user, $told ) {
	$GLOBALS['user']         = array(
		'id'   => $user,
		'caps' => array( 'edit_theme_options' ),
	);
	$_REQUEST['_ajax_nonce'] = wp_create_nonce( 'wpcredits_migration_1_32_0_seen' );
	$_POST                   = null === $told ? array() : array( 'told' => $told );

	try {
		wpcredits_migrate_1_32_0_dismiss();
	} catch ( WPCREDITS_Test_Sent $sent ) {
		return $sent->answer;
	}

	return array();
}

$dismissed = dismissed_with( 1, 'wpcredits_migrated_1_32_0,wpcredits_migrated_1_32_1' );

ck( 'dismissed, the notice is not shown to that Administrator again, for either record; another who has not dismissed it is still told both',
	array(
		$dismissed,
		array_keys( $GLOBALS['user_meta'][1] ?? array() ),
		$notice_for( array( 'edit_theme_options' ), 1 ),
		notice_text( $notice_for( array( 'edit_theme_options' ), 3 ) ),
	),
	array( array( 'success' => true ), array( 'wpcredits_migrated_1_32_0_seen', 'wpcredits_migrated_1_32_1_seen' ), '', wpcredits_migrate_1_32_0_summary( $both_records[0] ) . ' ' . $live_1_32_1 ) );

dismissed_with( 4, 'wpcredits_migrated_1_32_1,blogname' );
dismissed_with( 5, null );

ck( 'a dismissal marks only the records the theme knows of; one sent by a notice 1.32.0 printed, which says nothing of what it told, marks the 1.32.0 record alone, so its reader is still told the 1.32.1 sentences',
	array(
		array_keys( $GLOBALS['user_meta'][4] ?? array() ),
		array_keys( $GLOBALS['user_meta'][5] ?? array() ),
		notice_text( $notice_for( array( 'edit_theme_options' ), 5 ) ),
	),
	array( array( 'wpcredits_migrated_1_32_1_seen' ), array( 'wpcredits_migrated_1_32_0_seen' ), $live_1_32_1 ) );

$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = array_merge( $both_records[0], array( 'ran' => time() - 10 * DAY_IN_SECONDS ) );
$GLOBALS['opts']['wpcredits_migrated_1_32_1'] = array_merge( $both_records[1], array( 'ran' => time() - 2 * DAY_IN_SECONDS ) );
$within_week                                  = notice_text( $notice_for( array( 'edit_theme_options' ), 3 ) );
$GLOBALS['opts']['wpcredits_migrated_1_32_1'] = array_merge( $both_records[1], array( 'ran' => time() - WEEK_IN_SECONDS - 60 ) );
$after_week                                   = $notice_for( array( 'edit_theme_options' ), 3 );
$GLOBALS['opts']['wpcredits_migrated_1_32_1'] = array(
	'ran'       => time(),
	'templates' => array(),
);
$nothing_new                                  = $notice_for( array( 'edit_theme_options' ), 3 );
$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = $both_records[0];
$GLOBALS['opts']['wpcredits_migrated_1_32_1'] = $both_records[1];

ck( 'the notice is shown for a week after the newer run that has something to tell, both records\' sentences in it: ten days after 1.32.0 and two after 1.32.1 it is; a week after 1.32.1 it is not; a 1.32.1 run that reached no template does not bring back a 1.32.0 notice older than a week',
	array( $within_week, $after_week, $nothing_new ),
	array( wpcredits_migrate_1_32_0_summary( $both_records[0] ) . ' ' . $live_1_32_1, '', '' ) );

$records_said = array(
	'unreadable' => array(
		'ran'       => 1,
		'templates' => array( array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array( 'partner-levels', 'partner-glance' ), 'skipped' => array( 'partner-levels-unreadable' ), 'kept' => 'revision', 'revision' => 9 ) ),
	),
	'left'       => array(
		'ran'       => 1,
		'templates' => array(
			array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array(), 'skipped' => array( 'partner-levels-done', 'partner-glance-missing' ) ),
			array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array(), 'skipped' => array( 'program-index-legacy' ) ),
			array( 'id' => 1655, 'slug' => 'page-education-hub', 'changed' => array(), 'skipped' => array( 'program-index-emptied' ) ),
		),
	),
	'some'       => array(
		'ran'       => 1,
		'templates' => array( array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array( 'partner-levels' ), 'skipped' => array( 'partner-glance-missing', 'saved-differently' ), 'kept' => 'record', 'revision' => 0, 'before' => 'x' ) ),
	),
	'emptied'    => array(
		'ran'       => 1,
		'templates' => array( array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array( 'partner-levels-emptied', 'partner-glance' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => 9 ) ),
	),
	'table only' => array(
		'ran'       => 1,
		'templates' => array( array( 'id' => 1643, 'slug' => 'page-partners', 'changed' => array(), 'skipped' => array( 'partner-levels-missing', 'partner-glance-no-levels' ) ) ),
	),
);

ck( 'the sentences for what was read as the theme\'s, what was left and why, a part replaced, a save that differs and a copy in the record, levels emptied, and a table left for want of levels: each true of what the page shows',
	array_map( 'wpcredits_migrate_1_32_1_summary', $records_said ),
	array(
		'unreadable' => 'The theme\'s update to 1.32.1 changed the saved Partners template, so its partnership levels are edited here and the sponsor application shows the same ones: the partnership levels are now the Partner levels block, read from its Custom HTML block; the Levels at a glance table is now its own block, drawn from those levels. Its partnership levels could not be read from its Custom HTML block, so the theme\'s own levels were written in their place: compare them with the template as it was. The template as it was before is kept in its revisions.',
		'left'       => 'The theme\'s update to 1.32.1 left the saved Partners template as it was: its partnership levels are already the Partner levels block; it holds no Custom HTML block of the Levels at a glance table. The theme\'s update to 1.32.1 left the saved Education Hub template as it was: its Program index is still as theme 1.31 saved it, so it still shows the theme\'s own programs. The theme\'s update to 1.32.1 left the saved Education Hub template as it was: its Program index was emptied in the Site Editor, so it stays empty.',
		'some'       => 'The theme\'s update to 1.32.1 changed the saved Partners template, so its partnership levels are edited here and the sponsor application shows the same ones: the partnership levels are now the Partner levels block, read from its Custom HTML block. It left the rest as it was: it holds no Custom HTML block of the Levels at a glance table. What was saved differs from what the update wrote, so compare the template with its revisions. The template as it was before is kept in the theme\'s migration record.',
		'emptied'    => 'The theme\'s update to 1.32.1 changed the saved Partners template, so its partnership levels are edited here and the sponsor application shows the same ones: its Custom HTML block of partnership levels held no level, so it is now an empty Partner levels block: until levels are added to it, the page shows no levels and no table, and the sponsor application lists none; the Levels at a glance table is now its own block, drawn from those levels. The template as it was before is kept in its revisions.',
		'table only' => 'The theme\'s update to 1.32.1 left the saved Partners template as it was: it holds no Custom HTML block of partnership levels; its Levels at a glance table stays a Custom HTML block, as the template holds no partnership levels to draw it from.',
	) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
