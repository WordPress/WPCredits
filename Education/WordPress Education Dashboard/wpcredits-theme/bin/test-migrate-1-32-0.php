<?php
/**
 * The one-time migration of theme 1.32.0 (inc/migrate-1-32-0.php): a saved copy of the Education Hub
 * template, which theme releases no longer change, has its Program index of 1.31 and its "Find where
 * you fit" Custom HTML block replaced by the editable blocks, and nothing else of it touched.
 *
 * Checked on live's own saved copy (bin/fixtures/saved-education-hub.html, the wp_template post as two
 * Administrators saved it on 22 October 2026, no personal data), rendered on the WordPress of
 * bin/stubs/hub-wp.php, with the posts, options and users the runner reaches stood in by
 * bin/stubs/migrate-wp.php.
 *
 * The copy of the template as it was, kept before the write, is checked against the history live's run
 * met: a visitor's request (kses on, no one signed in) and a newest revision that was the template
 * without its svg. The suite also fails without the repair's guard against its own write, its catch
 * and its option's catch.
 *
 * Run from the theme root:  php bin/test-migrate-1-32-0.php
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

$saved = (string) file_get_contents( __DIR__ . '/fixtures/saved-education-hub.html' );

echo "=== Live's saved Education Hub template ===\n";

$html_span = null;

// The saved copy's "Find where you fit": the Custom HTML block whose markup holds the flow's rows.
if ( preg_match_all( '#<!-- wp:html -->(.*?)<!-- /wp:html -->#s', $saved, $html_blocks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
	foreach ( $html_blocks as $html_block ) {
		if ( false !== strpos( $html_block[1][0], 'wpc-hub-flow__rows' ) ) {
			$html_span = array( $html_block[0][1], strlen( $html_block[0][0] ) );
		}
	}
}

ck( 'the fixture is live\'s saved copy: the 1.31 Program index, self-closing, once; no courses band; the flow in one Custom HTML block of four; the Administrators\' own words in the section heads (its ledes are not the theme\'s)',
	array(
		substr_count( $saved, '<!-- wp:wpcredits/program-index /-->' ),
		substr_count( $saved, 'wp:wpcredits/course-band' ),
		substr_count( $saved, '<!-- wp:html -->' ),
		null !== $html_span,
		substr_count( $saved, 'Pick the program that fits where you are today, then keep going.' ),
		strlen( $saved ),
	),
	array( 1, 0, 4, true, 1, 32658 ) );

$migrated = wpcredits_migrate_1_32_0_content( $saved, 'page-education-hub' );
$after    = $migrated['content'];

ck( 'the migration replaces its two targets and adds the band, and says so: the Program index, the courses band after it, and the flow, read from the saved markup',
	array( $migrated['changed'], $migrated['skipped'] ),
	array( array( 'program-index', 'course-band', 'fit-list' ), array() ) );

$index_span = span_of( $after, 'wpcredits/program-index' );
$band_span  = span_of( $after, 'wpcredits/course-band' );
$fit_span   = span_of( $after, 'wpcredits/fit-list' );
$old_index  = span_of( $saved, 'wpcredits/program-index' );

ck( 'every other byte of the template is unchanged: before the index, between the index\'s place and the flow\'s, and after the flow, the migrated copy is the saved copy byte for byte; the index and the band sit where the old index sat, one after the other with a blank line between',
	array(
		null !== $index_span && null !== $band_span && null !== $fit_span,
		pieces_around( $after, array( $index_span, $fit_span ) ) === array( substr( $saved, 0, $old_index[0] ), "\n\n" . substr( $after, $band_span[0], $band_span[1] ) . substr( $saved, $old_index[0] + $old_index[1], $html_span[0] - $old_index[0] - $old_index[1] ), substr( $saved, $html_span[0] + $html_span[1] ) ),
		$band_span[0] === $index_span[0] + $index_span[1] + 2,
		strlen( $after ) - ( $index_span[1] + 2 + $band_span[1] + $fit_span[1] ) === strlen( $saved ) - ( $old_index[1] + $html_span[1] ),
	),
	array( true, true, true, true ) );

$programs_group = null;

foreach ( parse_blocks( $after ) as $parsed ) {
	if ( 'core/group' === $parsed['blockName'] ) {
		foreach ( $parsed['innerBlocks'] as $section ) {
			if ( isset( $section['attrs']['anchor'] ) && 'programs' === $section['attrs']['anchor'] ) {
				$programs_group = $section;
			}
		}
	}
}

$index_block = wpcredits_test_block_in( $after, 'wpcredits/program-index' );
$band_block  = wpcredits_test_block_in( $after, 'wpcredits/course-band' );
$fit_block   = wpcredits_test_block_in( $after, 'wpcredits/fit-list' );

ck( 'the programs section now holds its head, the index with a Program card for each of the six programs, live and in development, the band with its five Course cards, and the flow\'s group with its head and the Field of study rows, seven of them',
	array(
		$programs_group ? array_map( static function ( $parsed ) { return $parsed['blockName']; }, $programs_group['innerBlocks'] ) : null,
		array_map( static function ( $parsed ) { return $parsed['blockName'] . ' ' . $parsed['attrs']['slug']; }, $index_block['innerBlocks'] ?? array() ),
		count( $band_block['innerBlocks'] ?? array() ),
		$band_block['attrs']['anchor'] ?? null,
		count( $fit_block['innerBlocks'] ?? array() ),
	),
	array(
		array( 'core/group', 'wpcredits/program-index', 'wpcredits/course-band', 'core/group' ),
		array(
			'wpcredits/program-card campus-connect',
			'wpcredits/program-card wordpress-credits',
			'wpcredits/program-card facilitator-training',
			'wpcredits/program-card learn-wordpress',
			'wpcredits/program-card ai-leaders',
			'wpcredits/program-card wordpress-builder-certification',
		),
		5,
		'wordpress-credits-courses',
		7,
	) );

// A marked index with every card removed is saved self-closing, as 1.31 saved its index; the mark is what
// tells the two apart (theme 1.32.1), so the index the migration writes carries it.
ck( 'the index the migration writes is marked `editable`, as the Site Editor marks one an Administrator edits, so a save with every card removed is not mistaken for the 1.31 index',
	array( $index_block['attrs'] ?? null, wpcredits_hub_index_is_legacy( $index_block ?? array() ), 0 === strpos( substr( $after, $index_span[0] ), '<!-- wp:wpcredits/program-index {"editable":true} -->' ) ),
	array( array( 'editable' => true ), false, true ) );

ck( 'the index and the band are what the hub pattern writes, and the flow\'s rows, read from the saved markup, are today\'s seven, its labels today\'s',
	array(
		serialize_block( $index_block ) === wpcredits_hub_program_index_markup( wpcredits_hub_programs() ),
		serialize_block( $band_block ) === wpcredits_hub_course_bands_markup( wpcredits_hub_programs() ),
		serialize_block( $fit_block ) === wpcredits_hub_fit_list_markup( wpcredits_hub_fit_rows() ),
		wpcredits_fit_rows_from_html( substr( $saved, $html_span[0], $html_span[1] ) ) === wpcredits_hub_fit_rows(),
		wpcredits_fit_labels_from_html( substr( $saved, $html_span[0], $html_span[1] ) ),
	),
	array( true, true, true, true, wpcredits_hub_fit_labels() ) );

// The proof the page looks as it did: the migrated template's flow draws byte for byte what its Custom
// HTML block held, and its index and band what the theme's pattern draws.
preg_match( '#<!-- wp:html -->\n(.*?)\n<!-- /wp:html -->#s', substr( $saved, $html_span[0], $html_span[1] ), $saved_flow );

ck( 'the migrated template draws its flow byte for byte as the saved Custom HTML block held it, and its index and band as the hub pattern draws them; the address the WordPress Credits card links to is on the section once',
	array(
		trim( render_block( $fit_block ) ) === $saved_flow[1],
		render_block( $index_block ) === render_block( wpcredits_test_block_in( wpcredits_test_pattern( 'hub-programs.php' ), 'wpcredits/program-index' ) ),
		render_block( $band_block ) === render_block( wpcredits_test_block_in( wpcredits_test_pattern( 'hub-programs.php' ), 'wpcredits/course-band' ) ),
		$programs_group ? count( dom_of( render_block( $programs_group ) )->query( '//*[@id="wordpress-credits-courses"]' ) ) : 0,
	),
	array( true, true, true, 1 ) );

$again = wpcredits_migrate_1_32_0_content( $after, 'page-education-hub' );

ck( 'run again it changes nothing, and says why: the index already holds cards, the flow is already rows',
	array( $again['content'] === $after, $again['changed'], $again['skipped'] ),
	array( true, array(), array( 'program-index-done', 'fit-list-done' ) ) );

echo "\n=== What the migration reads, and what it leaves ===\n";

// An Administrator's edits in the saved Custom HTML are the rows the migration writes: a team renamed,
// a row added, a proof reworded with an ampersand, the header line and a screen reader label changed.
$edited_flow = str_replace(
	array( '<li class="wpc-hub-flow__tag">Polyglots</li>', 'Published translations</span>', '<span class="wpc-hub-flow__step wpc-hub-flow__step--field">Your field of study</span>', '<span class="screen-reader-text">The team you join:</span>' ),
	array( '<li class="wpc-hub-flow__tag">Polyglots team</li>', 'Published translations &amp; glossaries</span>', '<span class="wpc-hub-flow__step wpc-hub-flow__step--field">Your degree</span>', '<span class="screen-reader-text">Teams:</span>' ),
	substr( $saved, $html_span[0], $html_span[1] )
);
$edited_flow = str_replace( '</ul>' . "\n" . '</div>' . "\n" . '<!-- /wp:html -->', '<li class="wpc-hub-flow__row"><p class="wpc-hub-flow__field">Law</p><div class="wpc-hub-flow__teams"><ul class="wpc-hub-flow__tags"><li class="wpc-hub-flow__tag">Privacy</li></ul></div><p class="wpc-hub-flow__proof"><span class="wpc-hub-flow__shown"><span class="screen-reader-text">What you&#039;ll have to show:</span> A policy in use</span></p></li>' . "\n" . '</ul>' . "\n" . '</div>' . "\n" . '<!-- /wp:html -->', $edited_flow );
$edited      = substr_replace( $saved, $edited_flow, $html_span[0], $html_span[1] );
$edited_rows = wpcredits_fit_rows_from_html( $edited_flow );
$edited_run  = wpcredits_migrate_1_32_0_content( $edited, 'page-education-hub' );
$edited_list = wpcredits_test_block_in( $edited_run['content'], 'wpcredits/fit-list' );

ck( 'the rows are read from the saved markup, an Administrator\'s edits among them: a renamed team, a reworded proof, a row added; the header line and the labels too, so the migrated flow says what the saved one said',
	array(
		count( $edited_rows ),
		$edited_rows[3] ?? null,
		$edited_rows[7] ?? null,
		$edited_list['attrs']['fieldHeading'] ?? null,
		$edited_list['attrs']['teamsLabel'] ?? null,
		$edited_list ? array( found_text( render_block( $edited_list ), '//li[@class="wpc-hub-flow__row"][4]/div/span' ), found_text( render_block( $edited_list ), '//li[@class="wpc-hub-flow__row"][4]//li' ), found_text( render_block( $edited_list ), '//li[@class="wpc-hub-flow__row"][4]//span[@class="wpc-hub-flow__shown"]' ) ) : null,
	),
	array(
		8,
		array( 'field' => 'Translation, languages', 'teams' => array( 'Polyglots team' ), 'proof' => 'Published translations & glossaries' ),
		array( 'field' => 'Law', 'teams' => array( 'Privacy' ), 'proof' => 'A policy in use' ),
		'Your degree',
		'Teams:',
		array( 'Teams:', 'Polyglots team', 'What you\'ll have to show: Published translations & glossaries' ),
	) );

/**
 * The text of the first node an XPath query finds in some markup, whitespace run together.
 *
 * @param string $html  The markup.
 * @param string $query The query.
 * @return string
 */
function found_text( $html, $query ) {
	$node = dom_of( $html )->query( $query )->item( 0 );

	return $node ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : '';
}

$unreadable     = str_replace( substr( $saved, $html_span[0], $html_span[1] ), "<!-- wp:html -->\n<div class=\"wpc-hub-flow\"><ul class=\"wpc-hub-flow__rows\" role=\"list\"></ul></div>\n<!-- /wp:html -->", $saved );
$unreadable_run = wpcredits_migrate_1_32_0_content( $unreadable, 'page-education-hub' );

ck( 'markup it cannot read rows from falls back to the theme\'s rows, and says so',
	array( $unreadable_run['changed'], serialize_block( wpcredits_test_block_in( $unreadable_run['content'], 'wpcredits/fit-list' ) ) === wpcredits_hub_fit_list_markup( wpcredits_hub_fit_rows() ), wpcredits_fit_rows_from_html( '<p>No flow here</p>' ), wpcredits_fit_labels_from_html( '<p>No flow here</p>' ) ),
	array( array( 'program-index', 'course-band', 'fit-list-theme-rows' ), true, array(), array() ) );

$locked     = str_replace( '<!-- wp:wpcredits/program-index /-->', '<!-- wp:wpcredits/program-index {"lock":{"move":true,"remove":true}} /-->', $saved );
$locked_run = wpcredits_migrate_1_32_0_content( $locked, 'page-education-hub' );
$with_band  = str_replace( '<!-- wp:wpcredits/program-index /-->', '<!-- wp:wpcredits/program-index /-->' . "\n\n" . '<!-- wp:wpcredits/course-band {"anchor":"kept"} -->' . "\n" . '<!-- wp:wpcredits/course-card {"name":"Kept"} /-->' . "\n" . '<!-- /wp:wpcredits/course-band -->', $saved );
$band_run   = wpcredits_migrate_1_32_0_content( $with_band, 'page-education-hub' );

ck( 'the index keeps what an Administrator set on the old one, such as a lock; a template that already has a courses band keeps it and gets no second',
	array(
		wpcredits_test_block_in( $locked_run['content'], 'wpcredits/program-index' )['attrs'] ?? null,
		$band_run['changed'],
		substr_count( $band_run['content'], '<!-- wp:wpcredits/course-band ' ),
		false !== strpos( $band_run['content'], '<!-- wp:wpcredits/course-band {"anchor":"kept"} -->' ),
	),
	array( array( 'editable' => true, 'lock' => array( 'move' => true, 'remove' => true ) ), array( 'program-index', 'fit-list' ), 1, true ) );

// A marked index with no card is one an Administrator emptied (theme 1.32.1, ruling R3): the migration,
// and the repair of a save, must not put the theme's cards back in it.
$emptied_index = '<!-- wp:wpcredits/program-index {"editable":true} /-->';
$emptied_saved = str_replace( '<!-- wp:wpcredits/program-index /-->', $emptied_index, $saved );
$emptied_run   = wpcredits_migrate_1_32_0_content( $emptied_saved, 'page-education-hub' );
$emptied_at    = strpos( $emptied_saved, $emptied_index );

ck( 'an index an Administrator emptied (marked, no card) is left empty: no card is put in it and no courses band after it, and the migration says so; the flow after it is still read',
	array(
		$emptied_run['changed'],
		$emptied_run['skipped'],
		substr( $emptied_run['content'], 0, $emptied_at + strlen( $emptied_index ) ) === substr( $emptied_saved, 0, $emptied_at + strlen( $emptied_index ) ),
		substr_count( $emptied_run['content'], 'wp:wpcredits/program-card' ),
		substr_count( $emptied_run['content'], 'wp:wpcredits/course-band' ),
		substr_count( $emptied_run['content'], '<!-- wp:wpcredits/fit-list ' ),
	),
	array( array( 'fit-list' ), array( 'program-index-emptied' ), true, 0, 0, 1 ) );

$emptied_with_attrs = str_replace( '<!-- wp:wpcredits/program-index /-->', '<!-- wp:wpcredits/program-index {"editable":true,"lock":{"move":true}} /-->', $saved );

ck( 'the mark is read among other attributes, in any order; a mark that is not the boolean true is not one, and that index is migrated as the 1.31 index',
	array(
		wpcredits_migrate_1_32_0_content( $emptied_with_attrs, 'page-education-hub' )['skipped'],
		wpcredits_migrate_1_32_0_content( str_replace( '{"editable":true,"lock":{"move":true}}', '{"lock":{"move":true},"editable":true}', $emptied_with_attrs ), 'page-education-hub' )['skipped'],
		wpcredits_migrate_1_32_0_content( str_replace( '"editable":true', '"editable":false', $emptied_with_attrs ), 'page-education-hub' )['changed'],
		wpcredits_migrate_1_32_0_content( str_replace( '"editable":true', '"editable":"yes"', $emptied_with_attrs ), 'page-education-hub' )['changed'],
	),
	array( array( 'program-index-emptied' ), array( 'program-index-emptied' ), array( 'program-index', 'course-band', 'fit-list' ), array( 'program-index', 'course-band', 'fit-list' ) ) );

$bare_template = '<!-- wp:paragraph -->' . "\n" . '<p>An Education Hub with neither.</p>' . "\n" . '<!-- /wp:paragraph -->';
$bare_run      = wpcredits_migrate_1_32_0_content( $bare_template, 'page-education-hub' );
$partners_run  = wpcredits_migrate_1_32_0_content( $saved, 'page-partners' );

ck( 'a template without the targets is left as it is and reported skipped, each target with its reason; in 1.32.0 a template of another slug, the Partners page\'s among them, is left whole, even one holding the old index',
	array( $bare_run['content'] === $bare_template, $bare_run['changed'], $bare_run['skipped'], $partners_run['content'] === $saved, $partners_run['changed'], $partners_run['skipped'] ),
	array( true, array(), array( 'program-index-missing', 'fit-list-missing' ), true, array(), array( 'other-template' ) ) );

echo "\n=== The runner, once ===\n";

$GLOBALS['posts'] = array(
	1654 => new WP_Post( array( 'ID' => 1654, 'post_type' => 'wp_template', 'post_name' => 'page-education-hub', 'post_status' => 'publish', 'post_content' => $saved ) ),
	1643 => new WP_Post( array( 'ID' => 1643, 'post_type' => 'wp_template', 'post_name' => 'page-partners', 'post_status' => 'publish', 'post_content' => 'partners' ) ),
	1700 => new WP_Post( array( 'ID' => 1700, 'post_type' => 'wp_template', 'post_name' => 'page-education-hub', 'post_status' => 'publish', 'post_content' => $saved ) ),
);
$GLOBALS['terms'] = array(
	1654 => 'wpcredits-theme',
	1643 => 'wpcredits-theme',
	1700 => 'another-theme',
);

ck( 'the runner is hooked on init, after the theme registers its blocks, and the notice on the editor\'s assets; functions.php loads the file after the theme\'s others',
	array(
		array_values( array_map( static function ( $hooked ) { return $hooked[1]; }, array_filter( $GLOBALS['hooks']['init'] ?? array(), static function ( $hooked ) { return 'wpcredits_migrate_1_32_0' === $hooked[0]; } ) ) ),
		count( array_filter( $GLOBALS['hooks']['enqueue_block_editor_assets'] ?? array(), static function ( $hooked ) { return 'wpcredits_migrate_1_32_0_notice' === $hooked[0]; } ) ),
		false !== strpos( (string) file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), "require_once get_theme_file_path( 'inc/partners.php' );\nrequire_once get_theme_file_path( 'inc/migrate-1-32-0.php' );" ),
	),
	array( array( 20 ), 1, true ) );

wpcredits_migrate_1_32_0();

$record = get_option( 'wpcredits_migrated_1_32_0' );

ck( 'the runner migrates this theme\'s saved Education Hub template alone: a revision of it as it was first, written with kses off, then its content written with kses off around the write, the option last, not autoloaded; another theme\'s copy and the Partners template are not touched',
	array(
		$GLOBALS['calls'],
		$GLOBALS['posts'][1654]->post_content === $after,
		$GLOBALS['posts'][1700]->post_content === $saved,
		$GLOBALS['posts'][1643]->post_content,
	),
	array(
		array(
			'get_posts {"post_type":"wp_template","post_status":"any","name":"page-education-hub","posts_per_page":20,"orderby":"ID","order":"ASC"}',
			'kses off',
			'revision 1654 of ' . sha1( $saved ),
			'kses on',
			'kses off',
			'update 1654 to ' . sha1( $after ),
			'kses on',
			'add_option wpcredits_migrated_1_32_0 autoload false',
		),
		true,
		true,
		'partners',
	) );

$first_copy = newest_revision_of( 1654 );

ck( 'it records what it did for each template it reached, for Administrators to see, and where the copy of the template as it was is kept: the revision, by its ID, byte for byte the saved copy, svg and all',
	array( is_array( $record ) ? array_keys( $record ) : null, $record['templates'] ?? null, $first_copy ? $first_copy->post_content === $saved : null ),
	array(
		array( 'ran', 'templates' ),
		array( array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'program-index', 'course-band', 'fit-list' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => $first_copy ? $first_copy->ID : null ) ),
		true,
	) );

$GLOBALS['calls'] = array();
wpcredits_migrate_1_32_0();

ck( 'once: with its option set, a second request does nothing at all',
	$GLOBALS['calls'], array() );

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );
$GLOBALS['calls'] = array();
wpcredits_migrate_1_32_0();

ck( 'and should the option be lost, a run over the migrated template writes nothing and records why',
	array( $GLOBALS['calls'][1] ?? null, get_option( 'wpcredits_migrated_1_32_0' )['templates'][0]['skipped'] ?? null ),
	array( 'add_option wpcredits_migrated_1_32_0 autoload false', array( 'program-index-done', 'fit-list-done' ) ) );

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );
$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['fail_update']               = true;
$GLOBALS['calls']                     = array();
wpcredits_migrate_1_32_0();
$GLOBALS['fail_update'] = false;

ck( 'a write that fails leaves the template as it was and is recorded as failed, nothing claimed changed',
	array( $GLOBALS['posts'][1654]->post_content === $saved, get_option( 'wpcredits_migrated_1_32_0' )['templates'][0] ?? null ),
	array( true, array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array(), 'skipped' => array( 'update-failed' ), 'kept' => '' ) ) );

// Something that throws is caught: the template is recorded as failed, kses is turned back on, and the
// option is set all the same, so the site does not try again on every request.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );
$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['throw_update']              = true;
$GLOBALS['calls']                     = array();
wpcredits_migrate_1_32_0();
$thrown_calls  = $GLOBALS['calls'];
$thrown_record = get_option( 'wpcredits_migrated_1_32_0' );

$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['calls']                     = array();
$thrown_save                          = 'returned';

try {
	do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );
} catch ( Throwable $error ) {
	$thrown_save = 'threw';
}

$thrown_save_calls       = $GLOBALS['calls'];
$GLOBALS['throw_update'] = false;
$GLOBALS['calls']        = array();
wpcredits_migrate_1_32_0();
$after_thrown = $GLOBALS['calls'];

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );
$GLOBALS['throw_terms'] = true;
wpcredits_migrate_1_32_0();
$GLOBALS['throw_terms'] = false;
$terms_record           = get_option( 'wpcredits_migrated_1_32_0' );

ck( 'a write that throws is caught: the template is left as it was and recorded as failed, kses turned back on after it, the option set, and the next request tries nothing; a save that throws in the repair still returns, the failure recorded with the repairs; a run that throws before any write records the failure, which the notice tells, and is set all the same',
	array(
		$GLOBALS['posts'][1654]->post_content === $saved,
		$thrown_record['templates'][0] ?? null,
		array_slice( $thrown_calls, -3 ),
		$thrown_save,
		array_slice( $thrown_save_calls, -3 ),
		$after_thrown,
		is_array( $terms_record ) ? array( $terms_record['templates'], $terms_record['failed'] ?? null, wpcredits_migrate_1_32_0_summary( $terms_record ) ) : null,
	),
	array(
		true,
		array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array(), 'skipped' => array( 'update-failed' ), 'kept' => '' ),
		array( 'update 1654 to ' . sha1( $after ), 'kses on', 'add_option wpcredits_migrated_1_32_0 autoload false' ),
		'returned',
		array( 'update 1654 to ' . sha1( $after ), 'kses on', 'update_option wpcredits_migrated_1_32_0 autoload false' ),
		array(),
		array( array(), 'update-failed', 'The theme\'s update to 1.32.0 could not finish updating the saved Education Hub template; a template it did not reach is as it was.' ),
	) );

$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = $thrown_record;

// Where revisions are off for the template, wp_save_post_revision() keeps nothing and says so no more
// than when the last revision already matches: the template as it was is kept in the record instead.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );
$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['revisions_off']             = true;
$GLOBALS['calls']                     = array();
wpcredits_migrate_1_32_0();

$kept_record = get_option( 'wpcredits_migrated_1_32_0' );
$kept_calls  = $GLOBALS['calls'];

$GLOBALS['posts'][1654]->post_content = $saved;
do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );

$kept_repairs             = get_option( 'wpcredits_migrated_1_32_0' )['repairs'] ?? array();
$GLOBALS['revisions_off'] = false;

ck( 'with revisions off for the template, no revision is asked for: the template as it was is kept in the migration\'s record, not autoloaded, a repair\'s too, and the notice says so in place of the revisions',
	array(
		count( preg_grep( '/^revision /', $kept_calls ) ),
		$kept_record['templates'][0]['kept'] ?? null,
		( $kept_record['templates'][0]['before'] ?? null ) === $saved,
		$kept_repairs ? array( $kept_repairs[0]['kept'], $kept_repairs[0]['before'] === $saved ) : null,
		in_array( 'add_option wpcredits_migrated_1_32_0 autoload false', $kept_calls, true ),
		false !== strpos( wpcredits_migrate_1_32_0_summary( $kept_record ), 'The template as it was before is kept in the theme\'s migration record.' ),
		false !== strpos( wpcredits_migrate_1_32_0_summary( $kept_record ), 'revisions' ),
	),
	array( 0, 'record', true, array( 'record', true ), true, true, false ) );

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );
$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['rewrite_update']            = true;
wpcredits_migrate_1_32_0();
$GLOBALS['rewrite_update'] = false;
$rewritten                 = get_option( 'wpcredits_migrated_1_32_0' );

ck( 'what was saved is read back: a filter that rewrote it is recorded, the change kept, and the notice says to compare the template with its revisions',
	array( $rewritten['templates'][0]['changed'] ?? null, $rewritten['templates'][0]['skipped'] ?? null, false !== strpos( wpcredits_migrate_1_32_0_summary( $rewritten ), 'What was saved differs from what the update wrote, so compare the template with its revisions.' ) ),
	array( array( 'program-index', 'course-band', 'fit-list' ), array( 'saved-differently' ), true ) );

echo "\n=== The copy of the template as it was ===\n";

// On live the migration asked for its revision on a request with no signed-in user, so kses was on: the
// revision held the template without its svg, and core, comparing the post with the newest revision,
// saw nothing new to keep. These checks rebuild that history.
$without_svg = (string) preg_replace( '#<svg\b.*?</svg>#s', '', $saved );
$visitor     = array(
	'id'   => 0,
	'caps' => array(),
);

$GLOBALS['user']                      = $visitor;
$GLOBALS['kses_on']                   = true;
$GLOBALS['posts'][1654]->post_author  = 7;
$GLOBALS['posts'][1654]->post_content = $saved;

drop_revisions();

$stripped = add_revision( 1654, $without_svg, 7 );

$GLOBALS['calls'] = array();
$copy             = wpcredits_migrate_keep_copy( 1654 );
$made             = newest_revision_of( 1654 );

ck( 'live\'s case, a visitor\'s request: the newest revision is the template without its svg, and a revision byte for byte the template, svg and all, is added and said: its ID, nothing left for the record',
	array(
		$copy,
		$made && $made->ID !== $stripped->ID,
		$made ? $made->post_content === $saved : null,
		count( wp_get_post_revisions( 1654 ) ),
	),
	array(
		array( 'kept' => 'revision', 'revision' => $made ? $made->ID : null, 'before' => '' ),
		true,
		true,
		2,
	) );

ck( 'the revision is written with kses off and kses is on again after it, the two filters it hooked gone; its author, with no one signed in, is the template\'s',
	array( $GLOBALS['calls'], $GLOBALS['kses_on'], hooked_by_the_copy(), $made ? $made->post_author : null ),
	array( array( 'kses off', 'revision 1654 of ' . sha1( $saved ), 'kses on' ), true, 0, 7 ) );

// A revision whose author is the one who saved is the revision a person can follow in the editor.
drop_revisions();
add_revision( 1654, $without_svg, 7 );

$GLOBALS['user'] = array(
	'id'   => 5,
	'caps' => array( 'edit_theme_options' ),
);
wpcredits_migrate_keep_copy( 1654 );

ck( 'with someone signed in, the revision is theirs',
	newest_revision_of( 1654 ) ? newest_revision_of( 1654 )->post_author : null, 5 );

$GLOBALS['user'] = $visitor;

// What an Administrator saved is already the newest revision: core keeps it as it saves, so there is
// nothing to add, and the one that is there is said.
drop_revisions();

$exact            = add_revision( 1654, $saved, 7 );
$GLOBALS['calls'] = array();
$reused           = wpcredits_migrate_keep_copy( 1654 );

ck( 'a newest revision that already holds the template exactly is the copy: none added, its ID said, nothing asked of core and kses left alone',
	array( $reused, count( wp_get_post_revisions( 1654 ) ), $GLOBALS['calls'] ),
	array( array( 'kept' => 'revision', 'revision' => $exact->ID, 'before' => '' ), 1, array() ) );

// Core compares a post with its newest revision once whitespace is run together, and keeps nothing when
// they match: a newest revision that differs by a newline is no exact copy, and one is added regardless.
drop_revisions();

$spaced = add_revision( 1654, $saved . "\n\n", 7 );
$forced = wpcredits_migrate_keep_copy( 1654 );
$newest = newest_revision_of( 1654 );

ck( 'a newest revision that differs only in its whitespace is no exact copy: core sees no change, yet a revision byte for byte the template is added',
	array( $forced['kept'], $newest && $newest->ID !== $spaced->ID, $newest ? $newest->post_content === $saved : null, $forced['revision'] === ( $newest ? $newest->ID : null ), count( wp_get_post_revisions( 1654 ) ) ),
	array( 'revision', true, true, true, 2 ) );

// An autosave is replaced by the next one: it is no copy to keep, exact or not.
drop_revisions();

add_revision( 1654, $without_svg, 7 );
add_revision( 1654, $saved, 7, 'autosave' );

$beside_autosave = wpcredits_migrate_keep_copy( 1654 );
$real_one        = newest_revision_of( 1654 );

ck( 'an autosave that holds the template is not the copy: a revision is added, and it is the one said',
	array( $beside_autosave['kept'], $real_one ? $real_one->post_content === $saved : null, $beside_autosave['revision'] === ( $real_one ? $real_one->ID : null ), count( wp_get_post_revisions( 1654 ) ) ),
	array( 'revision', true, true, 3 ) );

// A revision that could not be written, or was stored as something else, or whose write threw: the
// template as it was goes to the record, which is said, and kses is turned on again whatever happened.
$attempts = array(
	'the insert fails'                    => array( 'fail_revision', true, 1 ),
	'what is stored is another\'s rewrite' => array( 'rewrite_revision', true, 2 ),
	'the write throws before it stores'   => array( 'throw_revision', 'before', 1 ),
);
$outcomes = array();

foreach ( $attempts as $label => $attempt ) {
	drop_revisions();
	add_revision( 1654, $without_svg, 7 );

	$GLOBALS[ $attempt[0] ] = $attempt[1];
	$GLOBALS['calls']       = array();
	$kept_as                = wpcredits_migrate_keep_copy( 1654 );
	$GLOBALS[ $attempt[0] ] = false;
	$newest_now             = newest_revision_of( 1654 );

	$outcomes[ $label ] = array( $kept_as, end( $GLOBALS['calls'] ), $GLOBALS['kses_on'], count( wp_get_post_revisions( 1654 ) ) === $attempt[2], $newest_now && $newest_now->post_content === $saved, hooked_by_the_copy() );
}

ck( 'a revision that cannot be written (the insert fails, what is stored is not what was written, the write throws) is the record\'s job: kept `record` with the template as it was in `before`, no revision of it exact, kses on again, the two filters it hooked gone',
	$outcomes,
	array(
		'the insert fails'                    => array( array( 'kept' => 'record', 'revision' => 0, 'before' => $saved ), 'kses on', true, true, false, 0 ),
		'what is stored is another\'s rewrite' => array( array( 'kept' => 'record', 'revision' => 0, 'before' => $saved ), 'kses on', true, true, false, 0 ),
		'the write throws before it stores'   => array( array( 'kept' => 'record', 'revision' => 0, 'before' => $saved ), 'kses on', true, true, false, 0 ),
	) );

// A hook that throws after the revision is stored does not undo it: the revision is read back.
drop_revisions();
add_revision( 1654, $without_svg, 7 );

$GLOBALS['throw_revision'] = 'after';
$GLOBALS['calls']          = array();
$thrown_after              = wpcredits_migrate_keep_copy( 1654 );
$GLOBALS['throw_revision'] = false;
$stored_anyway             = newest_revision_of( 1654 );

ck( 'a hook that throws after the revision is stored does not lose it: it is read back, and is the copy',
	array( $thrown_after, $stored_anyway ? $stored_anyway->post_content === $saved : null, end( $GLOBALS['calls'] ), $GLOBALS['kses_on'] ),
	array( array( 'kept' => 'revision', 'revision' => $stored_anyway ? $stored_anyway->ID : null, 'before' => '' ), true, 'kses on', true ) );

// Where revisions are off the template goes to the record at once, without a try.
drop_revisions();

$GLOBALS['revisions_off'] = true;
$GLOBALS['calls']         = array();
$off                      = wpcredits_migrate_keep_copy( 1654 );
$GLOBALS['revisions_off'] = false;

ck( 'where revisions are off for the template, the copy is the record\'s, without trying: no revision asked for, kses not touched',
	array( $off, $GLOBALS['calls'] ), array( array( 'kept' => 'record', 'revision' => 0, 'before' => $saved ), array() ) );

// The write uses it, and says where the copy is.
drop_revisions();
add_revision( 1654, $without_svg, 7 );

$GLOBALS['calls'] = array();
$written          = wpcredits_migrate_1_32_0_write( 1654, $after );
$kept_by_write    = newest_revision_of( 1654 );

ck( 'the write keeps the copy first and returns where it is, and that it saved: the revision\'s ID, the content written with kses off, svg and all',
	array( $written, $kept_by_write ? $kept_by_write->post_content === $saved : null, $GLOBALS['posts'][1654]->post_content === $after, $GLOBALS['calls'] ),
	array(
		array( 'saved' => true, 'kept' => 'revision', 'revision' => $kept_by_write ? $kept_by_write->ID : null ),
		true,
		true,
		array( 'kses off', 'revision 1654 of ' . sha1( $saved ), 'kses on', 'kses off', 'update 1654 to ' . sha1( $after ), 'kses on' ),
	) );

$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['calls']                     = array();
$unkept                               = wpcredits_migrate_1_32_0_write( 1654, $after, false );

ck( 'asked to keep no revision, the write leaves the copy to the record, and says so',
	array( $unkept, count( preg_grep( '/^revision /', $GLOBALS['calls'] ) ) ),
	array( array( 'saved' => true, 'kept' => 'record', 'revision' => 0 ), 0 ) );

$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['fail_update']               = true;
$failed_write                         = wpcredits_migrate_1_32_0_write( 1654, $after );
$GLOBALS['fail_update']               = false;

$GLOBALS['revisions_off']  = true;
$GLOBALS['rewrite_update'] = true;
$differing_write           = wpcredits_migrate_1_32_0_write( 1654, $after );
$GLOBALS['rewrite_update'] = false;
$GLOBALS['revisions_off']  = false;

ck( 'a write that failed says `update-failed`, one a filter rewrote `saved-differently`, each with where the copy is',
	array( $failed_write['saved'], $failed_write['kept'], $differing_write ),
	array( 'update-failed', 'revision', array( 'saved' => 'saved-differently', 'kept' => 'record', 'revision' => 0 ) ) );

// The reviewer's finding: a hook that throws after the database write has not undone it. The object
// cache still holds the template as it was, so the write reads it back from the database.
drop_revisions();
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );

$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['throw_after_update']        = true;
$GLOBALS['calls']                     = array();
wpcredits_migrate_1_32_0();
$GLOBALS['throw_after_update'] = false;
$late_throw                    = get_option( 'wpcredits_migrated_1_32_0' );
$late_copy                     = newest_revision_of( 1654 );

ck( 'a hook after the database write that throws is not a failed write: the template reads back with its new content, so it is recorded as saved (nothing skipped), the copy kept, kses on again, the cache left clean',
	array(
		$GLOBALS['posts'][1654]->post_content === $after,
		$late_throw['templates'][0] ?? null,
		$late_throw['failed'] ?? null,
		$GLOBALS['kses_on'],
		$GLOBALS['stale'],
	),
	array(
		true,
		array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'program-index', 'course-band', 'fit-list' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => $late_copy ? $late_copy->ID : null ),
		null,
		true,
		array(),
	) );

// A template whose revision could not be kept is recorded with the copy in the record.
drop_revisions();
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );

$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['fail_revision']             = true;
wpcredits_migrate_1_32_0();
$GLOBALS['fail_revision'] = false;
$record_copy              = get_option( 'wpcredits_migrated_1_32_0' );

ck( 'a revision that could not be written leaves the copy in the record, not autoloaded: kept `record`, no revision, the template as it was in `before`, and the notice says the record holds it, not the revisions',
	array(
		$record_copy['templates'][0]['kept'] ?? null,
		$record_copy['templates'][0]['revision'] ?? null,
		( $record_copy['templates'][0]['before'] ?? null ) === $saved,
		false !== strpos( wpcredits_migrate_1_32_0_summary( $record_copy ), 'The template as it was before is kept in the theme\'s migration record.' ),
		false !== strpos( wpcredits_migrate_1_32_0_summary( $record_copy ), 'revisions' ),
	),
	array( 'record', 0, true, true, false ) );

$GLOBALS['user'] = array(
	'id'   => 1,
	'caps' => array( 'edit_theme_options' ),
);

echo "\n=== A save that brings the 1.31 parts back ===\n";

// An editor tab opened before the update holds the template as it was; saved after the migration, it
// writes the 1.31 index and Custom HTML back. The save is repaired at once, as the migration does it.
ck( 'the repair is hooked where core reports a saved post, its terms set, taking the post\'s ID and the post',
	array_values( array_map( static function ( $hooked ) { return $hooked[1] . ' ' . $hooked[2]; }, array_filter( $GLOBALS['hooks']['wp_after_insert_post'] ?? array(), static function ( $hooked ) { return 'wpcredits_migrate_1_32_0_on_save' === $hooked[0]; } ) ) ),
	array( '10 2' ) );

drop_revisions();

$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['calls']                     = array();
$GLOBALS['fired']                     = array();
$repairs_before                       = count( get_option( 'wpcredits_migrated_1_32_0' )['repairs'] ?? array() );

do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );

$repairs = get_option( 'wpcredits_migrated_1_32_0' )['repairs'] ?? array();

ck( 'a save of this theme\'s Education Hub template that holds the 1.31 index and Custom HTML is migrated at once: a revision, the write with kses off, written once though the write fires the hook again, and recorded with the migration',
	array(
		$GLOBALS['calls'],
		$GLOBALS['posts'][1654]->post_content === $after,
		$GLOBALS['fired']['wp_after_insert_post'] ?? 0,
		count( $repairs ) - $repairs_before,
		$repairs ? array_slice( $repairs[ count( $repairs ) - 1 ], 0, 3 ) : null,
	),
	array(
		array(
			'kses off',
			'revision 1654 of ' . sha1( $saved ),
			'kses on',
			'kses off',
			'update 1654 to ' . sha1( $after ),
			'kses on',
			'update_option wpcredits_migrated_1_32_0 autoload false',
		),
		true,
		2,
		1,
		array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'program-index', 'course-band', 'fit-list' ) ),
	) );

$GLOBALS['calls'] = array();

do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );
do_action( 'wp_after_insert_post', 1700, get_post( 1700 ), true, null );
do_action( 'wp_after_insert_post', 1643, get_post( 1643 ), true, null );

ck( 'a save of the migrated template is left alone, and so is another theme\'s copy and the Partners template, each holding nothing to repair or not ours',
	$GLOBALS['calls'], array() );

// A stale editor tab that saves the 1.31 flow back into a template whose index an Administrator emptied:
// the flow is repaired, the index stays empty and no courses band appears under it.
$GLOBALS['posts'][1654]->post_content = $emptied_saved;
$GLOBALS['calls']                     = array();

do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );

ck( 'a save that holds an emptied index and the 1.31 flow is repaired for the flow alone: one write, the index still marked and empty, no card and no courses band put in',
	array(
		count( preg_grep( '/^update /', $GLOBALS['calls'] ) ),
		$GLOBALS['posts'][1654]->post_content === $emptied_run['content'],
		substr_count( $GLOBALS['posts'][1654]->post_content, $emptied_index ),
		substr_count( $GLOBALS['posts'][1654]->post_content, 'wp:wpcredits/program-card' ),
		substr_count( $GLOBALS['posts'][1654]->post_content, 'wp:wpcredits/course-band' ),
	),
	array( 1, true, 1, 0, 0 ) );

$GLOBALS['calls'] = array();

do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );

ck( 'a save of that template with nothing else to repair is left alone: the emptied index is not repaired, no revision and no write',
	array( $GLOBALS['calls'], substr_count( $GLOBALS['posts'][1654]->post_content, $emptied_index ) ),
	array( array(), 1 ) );

// The repair's own write fires the hook it listens to. What stops it setting itself off again is the
// guard: the migrated content holds nothing to repair, so only a write that keeps a target tells the
// guard from the content check (a plugin that puts the 1.31 index back, say).
$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['keep_target']               = true;
$GLOBALS['calls']                     = array();
$GLOBALS['fired']                     = array();

do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );

$GLOBALS['keep_target'] = false;

ck( 'a write that keeps a target is not repaired again by the save it fires: one update, and the hook fired twice (the save, and the write\'s own)',
	array( count( preg_grep( '/^update /', $GLOBALS['calls'] ) ), $GLOBALS['fired']['wp_after_insert_post'] ?? 0 ),
	array( 1, 2 ) );

// A save is never failed by the repair, whatever throws in it: reading the template's terms, or
// recording the repair in the option.
$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['throw_terms']               = true;
$GLOBALS['calls']                     = array();
$terms_save                           = 'returned';

try {
	do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );
} catch ( Throwable $error ) {
	$terms_save = 'threw';
}

$GLOBALS['throw_terms'] = false;
$terms_calls            = $GLOBALS['calls'];

$GLOBALS['posts'][1654]->post_content = $saved;
$GLOBALS['throw_option']              = true;
$option_save                          = 'returned';

try {
	do_action( 'wp_after_insert_post', 1654, get_post( 1654 ), true, null );
} catch ( Throwable $error ) {
	$option_save = 'threw';
}

$GLOBALS['throw_option'] = false;

ck( 'a save returns whatever throws in the repair: reading the terms (nothing written), or saving the option after the template was repaired (the repair stands)',
	array( $terms_save, $terms_calls, $option_save, $GLOBALS['posts'][1654]->post_content === $after ),
	array( 'returned', array(), 'returned', true ) );

echo "\n=== The notice ===\n";

unset( $GLOBALS['opts']['wpcredits_migrated_1_32_0'] );
$GLOBALS['posts'][1654]->post_content = $saved;
wpcredits_migrate_1_32_0();

$notice_for = static function ( $screen, array $caps, $user ) {
	$GLOBALS['screen']  = $screen;
	$GLOBALS['user']    = array(
		'id'   => $user,
		'caps' => $caps,
	);
	$GLOBALS['inline']     = array();
	$GLOBALS['scripts']    = array();
	$GLOBALS['registered'] = array();

	wpcredits_migrate_1_32_0_notice();

	return isset( $GLOBALS['inline']['wpcredits-migration-1-32-0'] ) ? implode( '', $GLOBALS['inline']['wpcredits-migration-1-32-0'] ) : '';
};

$first  = $notice_for( 'site-editor', array( 'edit_theme_options' ), 1 );
$script = isset( $GLOBALS['scripts']['wpcredits-migration-1-32-0'] ) ? ( $GLOBALS['registered']['wpcredits-migration-1-32-0'] ?? null ) : null;
$second = $notice_for( 'site-editor', array( 'edit_theme_options' ), 1 );
$other  = $notice_for( 'site-editor', array( 'edit_theme_options' ), 2 );
$editor = $notice_for( 'site-editor', array( 'edit_posts' ), 3 );
$posts  = $notice_for( 'post', array( 'edit_theme_options' ), 4 );

preg_match( '/createNotice\( "info", ("(?:[^"\\\\]|\\\\.)*")/', $first, $notice_text );

// A default editor notice shows only inside the editor, not on the Site Editor's browse view, so it is
// shown on every visit until the Administrator dismisses it, or until a week has passed since the run.
ck( 'on the Site Editor, an Administrator is told what the migration did, as an editor notice, again on the next visit while they have not dismissed it; another Administrator is told too; someone who cannot edit the theme is not, nor is anyone on another screen',
	array( '' !== $first, $script ? array( $script[0], $script[1] ) : null, '' !== $second, '' !== $other, $editor, $posts, isset( $notice_text[1] ) ? json_decode( $notice_text[1] ) : null ),
	array(
		true,
		array( false, array( 'wp-data', 'wp-notices', 'wp-dom-ready' ) ),
		true,
		true,
		'',
		'',
		'The theme\'s update to 1.32.0 changed the saved Education Hub template, so every word of it can be edited here: the Program index now holds a Program card for each of the theme\'s programs; the Courses band of WordPress Credits\' courses was added under the index; "Find where you fit" is now Field of study rows, read from its Custom HTML block. The template as it was before is kept in its revisions.',
	) );

// The option is not autoloaded, so reading it is a query: a request that is not the Site Editor's, or
// is not an Administrator's, or an Administrator who dismissed the notice, reads nothing.
$GLOBALS['option_reads'] = array();

$reads = array();

foreach ( array( array( 'post', array( 'edit_theme_options' ), 4 ), array( 'site-editor', array( 'edit_posts' ), 3 ), array( 'site-editor', array( 'edit_theme_options' ), 2 ) ) as $request ) {
	$GLOBALS['option_reads'] = array();

	$notice_for( $request[0], $request[1], $request[2] );

	$reads[] = count( array_keys( $GLOBALS['option_reads'], 'wpcredits_migrated_1_32_0', true ) );
}

ck( 'the notice reads its option only once it knows to show it: not on another screen, not for someone who cannot edit the theme; on the Site Editor, for an Administrator, once',
	$reads, array( 0, 0, 1 ) );

/**
 * The dismissal's request, as the notice sends it to admin-ajax.php: the reader's own nonce, or one
 * forged.
 *
 * @param int   $user   The reader.
 * @param array $caps   What they may do.
 * @param bool  $forged Whether the nonce sent is not theirs.
 * @return array What the request answered.
 */
function dismissed_by( $user, array $caps, $forged = false ) {
	$GLOBALS['user']        = array(
		'id'   => $user,
		'caps' => $caps,
	);
	$_REQUEST['_ajax_nonce'] = $forged ? 'forged' : wp_create_nonce( 'wpcredits_migration_1_32_0_seen' );

	try {
		wpcredits_migrate_1_32_0_dismiss();
	} catch ( WPCREDITS_Test_Sent $sent ) {
		return $sent->answer;
	}

	return array();
}

$GLOBALS['user'] = array(
	'id'   => 1,
	'caps' => array( 'edit_theme_options' ),
);

ck( 'the notice tells the site when it is dismissed: a request to admin-ajax.php with its action and the reader\'s nonce for it, hooked for signed-in readers',
	array(
		false !== strpos( $first, 'onDismiss: function () {' ),
		false !== strpos( $first, 'body.append( "action", "wpcredits_migration_1_32_0_seen" );' ),
		false !== strpos( $first, 'body.append( "_ajax_nonce", ' . json_encode( wp_create_nonce( 'wpcredits_migration_1_32_0_seen' ) ) . ' );' ),
		false !== strpos( $first, 'window.fetch( ' . wp_json_encode( 'https://site.example/wp-admin/admin-ajax.php' ) . ', { method: "POST", credentials: "same-origin", body: body } );' ),
		array_values( array_map( static function ( $hooked ) { return $hooked[0]; }, $GLOBALS['hooks']['wp_ajax_wpcredits_migration_1_32_0_seen'] ?? array() ) ),
	),
	array( true, true, true, true, array( 'wpcredits_migrate_1_32_0_dismiss' ) ) );

$GLOBALS['user']  = array(
	'id'   => 1,
	'caps' => array( 'edit_theme_options' ),
);
$dismiss_answers = array(
	dismissed_by( 1, array( 'edit_theme_options' ), true ),
	dismissed_by( 3, array( 'edit_posts' ) ),
	dismissed_by( 1, array( 'edit_theme_options' ) ),
);

ck( 'dismissed, it is not shown to that Administrator again, and still to the others; a forged request, or one from someone who cannot edit the theme, marks nothing',
	array(
		$dismiss_answers,
		'' === $notice_for( 'site-editor', array( 'edit_theme_options' ), 1 ),
		'' !== $notice_for( 'site-editor', array( 'edit_theme_options' ), 2 ),
		get_user_meta( 3, 'wpcredits_migrated_1_32_0_seen', true ),
	),
	array( array( array( 'die' => -1 ), array( 'success' => false, 'status' => 403 ), array( 'success' => true ) ), true, true, '' ) );

$week_record                                   = get_option( 'wpcredits_migrated_1_32_0' );
$GLOBALS['opts']['wpcredits_migrated_1_32_0']  = array_merge( $week_record, array( 'ran' => time() - WEEK_IN_SECONDS - 60 ) );
$after_a_week                                  = $notice_for( 'site-editor', array( 'edit_theme_options' ), 2 );
$GLOBALS['opts']['wpcredits_migrated_1_32_0']  = array_merge( $week_record, array( 'ran' => time() - WEEK_IN_SECONDS + 3600 ) );
$within_a_week                                 = $notice_for( 'site-editor', array( 'edit_theme_options' ), 2 );
$GLOBALS['opts']['wpcredits_migrated_1_32_0']  = $week_record;

ck( 'a week after the migration ran, no one is told any more, dismissed or not; within the week, they are',
	array( $after_a_week, '' !== $within_a_week ),
	array( '', true ) );

$skipped_record = array(
	'ran'       => 1,
	'templates' => array( array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array(), 'skipped' => array( 'program-index-missing', 'fit-list-done' ) ) ),
);

ck( 'a template it left as it was is told with its reasons; a run that reached no template tells nothing',
	array( wpcredits_migrate_1_32_0_summary( $skipped_record ), wpcredits_migrate_1_32_0_summary( array( 'ran' => 1, 'templates' => array() ) ) ),
	array( 'The theme\'s update to 1.32.0 left the saved Education Hub template as it was: it holds no Program index; "Find where you fit" is already Field of study rows.', '' ) );

$emptied_record = array(
	'ran'       => 1,
	'templates' => array( array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array(), 'skipped' => array( 'program-index-emptied', 'fit-list-done' ) ) ),
);

ck( 'an emptied Program index is told as left empty, in its own words, not as one that already holds cards',
	wpcredits_migrate_1_32_0_summary( $emptied_record ),
	'The theme\'s update to 1.32.0 left the saved Education Hub template as it was: its Program index was emptied in the Site Editor, so it stays empty; "Find where you fit" is already Field of study rows.' );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
