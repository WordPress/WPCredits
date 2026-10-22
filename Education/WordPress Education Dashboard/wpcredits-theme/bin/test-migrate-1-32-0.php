<?php
/**
 * The one-time migration of theme 1.32.0 (inc/migrate-1-32-0.php): a saved copy of the Education Hub
 * template, which theme releases no longer change, has its Program index of 1.31 and its "Find where
 * you fit" Custom HTML block replaced by the editable blocks, and nothing else of it touched.
 *
 * Checked on live's own saved copy (bin/fixtures/saved-education-hub.html, the wp_template post as two
 * Administrators saved it on 22 October 2026, no personal data), rendered on the WordPress of
 * bin/stubs/hub-wp.php, with the posts, options and users the runner reaches stood in below.
 *
 * Run from the theme root:  php bin/test-migrate-1-32-0.php
 *
 * @package WPCredits_Theme
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';

/* ---- posts, options and users, as far as the runner reaches ----------------------- */

$GLOBALS['posts']     = array(); // ID => WP_Post stand-in.
$GLOBALS['terms']     = array(); // ID => the wp_theme term's name.
$GLOBALS['calls']     = array(); // What the runner did, in order.
$GLOBALS['user']      = array(
	'id'   => 1,
	'caps' => array( 'edit_theme_options' ),
);
$GLOBALS['user_meta'] = array();
$GLOBALS['screen']    = 'site-editor';
$GLOBALS['inline']    = array();

/** A post, as core's WP_Post carries one. */
class WP_Post {
	public $ID;
	public $post_type;
	public $post_name;
	public $post_status;
	public $post_content;

	public function __construct( array $fields ) {
		foreach ( $fields as $key => $value ) {
			$this->$key = $value;
		}
	}
}

/** Core's WP_Error, as far as is_wp_error() asks it. */
class WP_Error {
	public $code;

	public function __construct( $code = '' ) {
		$this->code = $code;
	}
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function get_stylesheet() { return 'wpcredits-theme'; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : addslashes( (string) $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
function add_option( $key, $value = '', $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $key, $GLOBALS['opts'] ) ) {
		return false;
	}

	$GLOBALS['opts'][ $key ] = $value;
	$GLOBALS['calls'][]      = 'add_option ' . $key . ' autoload ' . var_export( $autoload, true );

	return true;
}

/**
 * Core's get_posts(), as far as the runner asks it: the posts of a type, a name and a status other
 * than trash, in ID order.
 *
 * @param array $args The query.
 * @return WP_Post[]
 */
function get_posts( $args = array() ) {
	$GLOBALS['calls'][] = 'get_posts ' . json_encode( $args );

	return array_values(
		array_filter(
			$GLOBALS['posts'],
			static function ( $post ) use ( $args ) {
				return $post->post_type === $args['post_type'] && ( ! isset( $args['name'] ) || $post->post_name === $args['name'] ) && 'trash' !== $post->post_status;
			}
		)
	);
}

function get_post( $post ) { $id = $post instanceof WP_Post ? $post->ID : (int) $post; return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ] : null; }
function has_term( $term, $taxonomy, $post ) {
	if ( ! empty( $GLOBALS['throw_terms'] ) ) {
		throw new Error( 'thrown reading the terms' );
	}

	return 'wp_theme' === $taxonomy && isset( $GLOBALS['terms'][ $post->ID ] ) && $GLOBALS['terms'][ $post->ID ] === $term; }
function wp_revisions_enabled( $post ) { return empty( $GLOBALS['revisions_off'] ); }
function wp_save_post_revision( $id ) { $GLOBALS['calls'][] = 'revision ' . $id . ' of ' . sha1( $GLOBALS['posts'][ $id ]->post_content ); return 900 + $id; }
function kses_remove_filters() { $GLOBALS['calls'][] = 'kses off'; }
function kses_init() { $GLOBALS['calls'][] = 'kses on'; }

/**
 * Core's wp_update_post(), as far as the runner asks it: the fields it is handed are slashed, as core
 * expects, and unslashed as core does before it saves them; a post that fails saves nothing.
 *
 * @param array $fields   The post's ID and fields, slashed.
 * @param bool  $wp_error Whether a failure is a WP_Error.
 * @return int|WP_Error
 */
function wp_update_post( $fields, $wp_error = false ) {
	$fields = wp_unslash( $fields );

	$GLOBALS['calls'][] = 'update ' . $fields['ID'] . ' to ' . sha1( $fields['post_content'] );

	if ( ! empty( $GLOBALS['fail_update'] ) ) {
		return new WP_Error( 'db_update_error' );
	}

	// A plugin's hook, or the database layer, that throws in the middle of the write.
	if ( ! empty( $GLOBALS['throw_update'] ) ) {
		throw new RuntimeException( 'thrown in the write' );
	}

	// A plugin's content filter that rewrites what is saved, when a check asks for one.
	$GLOBALS['posts'][ (int) $fields['ID'] ]->post_content = empty( $GLOBALS['rewrite_update'] ) ? $fields['post_content'] : str_replace( '<p', '<p data-rewritten', $fields['post_content'] );

	// Core fires its after-insert action for every update, the runner's own writes among them.
	do_action( 'wp_after_insert_post', (int) $fields['ID'], $GLOBALS['posts'][ (int) $fields['ID'] ], true, null );

	return (int) $fields['ID'];
}

/**
 * Core's do_action(): each callback in priority order, with as many arguments as it takes; each firing
 * of a hook counted in `$GLOBALS['fired']`.
 *
 * @param string $hook    The action.
 * @param mixed  ...$args Its arguments.
 */
function do_action( $hook, ...$args ) {
	$GLOBALS['fired'][ $hook ] = ( isset( $GLOBALS['fired'][ $hook ] ) ? $GLOBALS['fired'][ $hook ] : 0 ) + 1;

	$callbacks = isset( $GLOBALS['hooks'][ $hook ] ) ? $GLOBALS['hooks'][ $hook ] : array();

	usort( $callbacks, static function ( $a, $b ) { return $a[1] - $b[1]; } );

	foreach ( $callbacks as $callback ) {
		call_user_func_array( $callback[0], array_slice( $args, 0, $callback[2] ) );
	}
}

function update_option( $key, $value, $autoload = null ) { $GLOBALS['opts'][ $key ] = $value; $GLOBALS['calls'][] = 'update_option ' . $key . ' autoload ' . var_export( $autoload, true ); return true; }

function get_current_user_id() { return (int) $GLOBALS['user']['id']; }
function current_user_can( $capability ) { return in_array( $capability, $GLOBALS['user']['caps'], true ); }
function get_user_meta( $user, $key, $single = false ) { return isset( $GLOBALS['user_meta'][ $user ][ $key ] ) ? $GLOBALS['user_meta'][ $user ][ $key ] : ''; }
function update_user_meta( $user, $key, $value ) { $GLOBALS['user_meta'][ $user ][ $key ] = $value; return true; }
function get_current_screen() { return (object) array( 'id' => $GLOBALS['screen'] ); }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['registered'][ $handle ] = array( $src, $deps, $ver, $args ); return true; }
function wp_add_inline_script( $handle, $data, $position = 'after' ) { $GLOBALS['inline'][ $handle ][] = $data; return true; }
function admin_url( $path = '' ) { return 'https://site.example/wp-admin/' . $path; }
function wp_create_nonce( $action = -1 ) { return 'nonce-' . md5( $action . '|' . $GLOBALS['user']['id'] ); }

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

/** What wp_send_json_*() ends a request with: core prints the answer and dies, here it is thrown. */
class WPCREDITS_Test_Sent extends Exception {
	public $answer;

	public function __construct( array $answer ) {
		parent::__construct( 'sent' );
		$this->answer = $answer;
	}
}

/**
 * Core's check_ajax_referer(): the request's `_ajax_nonce` checked against the action's nonce for the
 * reader; a wrong one ends the request with -1, as core's does.
 *
 * @param string $action The nonce's action.
 * @return int
 */
function check_ajax_referer( $action = -1 ) {
	if ( ! isset( $_REQUEST['_ajax_nonce'] ) || wp_create_nonce( $action ) !== $_REQUEST['_ajax_nonce'] ) {
		throw new WPCREDITS_Test_Sent( array( 'die' => -1 ) );
	}

	return 1;
}
function wp_send_json_success( $data = null, $status = null ) { throw new WPCREDITS_Test_Sent( array( 'success' => true ) ); }
function wp_send_json_error( $data = null, $status = null ) { throw new WPCREDITS_Test_Sent( array( 'success' => false, 'status' => $status ) ); }

require_once WPCREDITS_THEME_DIR . 'inc/migrate-1-32-0.php';

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
 * Some markup, parsed, to be asked with XPath.
 *
 * @param string $html The markup.
 * @return DOMXPath
 */
function dom_of( $html ) {
	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	return new DOMXPath( $document );
}

/**
 * A block's span in some markup: from its opening comment to its closing one, or its void comment.
 *
 * @param string $markup Block markup.
 * @param string $name   The block's name as its comment writes it, such as `wpcredits/program-index`.
 * @return array{0: int, 1: int}|null Its start and its length.
 */
function span_of( $markup, $name ) {
	if ( ! preg_match( '#<!-- wp:' . preg_quote( $name, '#' ) . '(?: \{.*?\})? (/?)-->#s', $markup, $open, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	if ( '/' === $open[1][0] ) {
		return array( $open[0][1], strlen( $open[0][0] ) );
	}

	$close = strpos( $markup, '<!-- /wp:' . $name . ' -->', $open[0][1] );

	return false === $close ? null : array( $open[0][1], $close + strlen( '<!-- /wp:' . $name . ' -->' ) - $open[0][1] );
}

/**
 * Some markup in pieces, cut at some spans: what lies before the first, between each two and after
 * the last.
 *
 * @param string  $markup The markup.
 * @param array[] $spans  Spans, as span_of() gives them, in order.
 * @return string[]
 */
function pieces_around( $markup, array $spans ) {
	$pieces = array();
	$at     = 0;

	foreach ( $spans as $span ) {
		$pieces[] = substr( $markup, $at, $span[0] - $at );
		$at       = $span[0] + $span[1];
	}

	$pieces[] = substr( $markup, $at );

	return $pieces;
}

/** The plugin's two application pages and its Partners page, published. */
class WPCPM_Institution_Application {
	public static function page_url() { return 'https://wordpresseducation.org/apply/'; }
}

/** The sponsorship application's page. */
class WPCPM_Sponsor_Application {
	public static function page_url() { return 'https://wordpresseducation.org/sponsor-application/'; }
}

/** The Partners page. */
class WPCPM_Sponsors_Page {
	public static function page_url() { return 'https://wordpresseducation.org/partners/'; }
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
	array( array( 'lock' => array( 'move' => true, 'remove' => true ) ), array( 'program-index', 'fit-list' ), 1, true ) );

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

ck( 'the runner migrates this theme\'s saved Education Hub template alone: a revision of it as it was first, then its content written with kses off around the write, the option last, not autoloaded; another theme\'s copy and the Partners template are not touched',
	array(
		$GLOBALS['calls'],
		$GLOBALS['posts'][1654]->post_content === $after,
		$GLOBALS['posts'][1700]->post_content === $saved,
		$GLOBALS['posts'][1643]->post_content,
	),
	array(
		array(
			'get_posts {"post_type":"wp_template","post_status":"any","name":"page-education-hub","posts_per_page":20,"orderby":"ID","order":"ASC"}',
			'revision 1654 of ' . sha1( $saved ),
			'kses off',
			'update 1654 to ' . sha1( $after ),
			'kses on',
			'add_option wpcredits_migrated_1_32_0 autoload false',
		),
		true,
		true,
		'partners',
	) );

ck( 'it records what it did for each template it reached, for Administrators to see',
	array( is_array( $record ) ? array_keys( $record ) : null, $record['templates'] ?? null ),
	array(
		array( 'ran', 'templates' ),
		array( array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'program-index', 'course-band', 'fit-list' ), 'skipped' => array(), 'kept' => 'revision' ) ),
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

echo "\n=== A save that brings the 1.31 parts back ===\n";

// An editor tab opened before the update holds the template as it was; saved after the migration, it
// writes the 1.31 index and Custom HTML back. The save is repaired at once, as the migration does it.
ck( 'the repair is hooked where core reports a saved post, its terms set, taking the post\'s ID and the post',
	array_values( array_map( static function ( $hooked ) { return $hooked[1] . ' ' . $hooked[2]; }, array_filter( $GLOBALS['hooks']['wp_after_insert_post'] ?? array(), static function ( $hooked ) { return 'wpcredits_migrate_1_32_0_on_save' === $hooked[0]; } ) ) ),
	array( '10 2' ) );

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
			'revision 1654 of ' . sha1( $saved ),
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

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
