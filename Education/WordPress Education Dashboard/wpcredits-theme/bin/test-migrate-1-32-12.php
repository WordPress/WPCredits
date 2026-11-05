<?php
/**
 * The one-time migration of theme 1.32.12 (inc/migrate-1-32-12.php): the saved copy of the homepage's
 * template (`page-education-hub`), which theme releases no longer change, has its "Who this is for"
 * section replaced by the band of four roles, directly under the hero, but only where the saved section
 * has the shape the theme shipped; anywhere else nothing changes, and the record and the Site Editor's
 * notice say why.
 *
 * Checked on live's own saved copy: bin/fixtures/saved-education-hub-1-32-11.html, the wp_template post
 * 1654 as an Administrator last saved it in the Site Editor on 27 October 2026, read on 5 November 2026
 * (its words edited there: the hero's lede, and the institutions' door's line cut short). Rendered on the
 * WordPress of bin/stubs/hub-wp.php, with the posts, options and users of bin/stubs/migrate-wp.php.
 *
 * Run from the theme root:  php bin/test-migrate-1-32-12.php
 *
 * @package WPCredits_Theme
 */

// phpcs:ignoreFile -- a command-line check, not theme code.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once __DIR__ . '/stubs/migrate-wp.php';

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post = 0 ) { return ''; }
}

/** The Partners page's launch switch: off, as live's is (5 November 2026), unless a check sets it. */
class WPCPM_Sponsor_Tiers {
	public static $live = false;
	public static function live() { return self::$live; }
}

require_once WPCREDITS_THEME_DIR . 'inc/hub.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once WPCREDITS_THEME_DIR . 'inc/template-tags.php';
require_once WPCREDITS_THEME_DIR . 'inc/partners.php';
require_once WPCREDITS_THEME_DIR . 'inc/migrate-1-32-0.php';
require_once WPCREDITS_THEME_DIR . 'inc/migrate-1-32-1.php';

if ( is_file( WPCREDITS_THEME_DIR . 'inc/migrate-1-32-12.php' ) ) {
	require_once WPCREDITS_THEME_DIR . 'inc/migrate-1-32-12.php';
}

$fail  = 0;
$total = 0;

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
 * A theme function's answer, or null while the theme has no such function.
 *
 * @param string $name    The function.
 * @param mixed  ...$args Its arguments.
 * @return mixed
 */
function theme_call( $name, ...$args ) {
	return function_exists( $name ) ? $name( ...$args ) : null;
}

function found( DOMXPath $xpath, $query, $attribute = '' ) {
	$said = array();

	foreach ( $xpath->query( $query ) as $node ) {
		$said[] = '' === $attribute ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : (string) $node->getAttribute( $attribute );
	}

	return $said;
}

/**
 * The span of the first group block whose class list holds a class: from its opening comment to its
 * closing one, nested groups counted.
 *
 * @param string $markup Block markup.
 * @param string $class  The class.
 * @return array{0: int, 1: int}|null Its start and its length.
 */
function group_span( $markup, $class ) {
	if ( ! preg_match( '#<!-- wp:group (\{(?:[^}]|\}(?! -->))*?"className":"(?:[^"]* )?' . preg_quote( $class, '#' ) . '(?: [^"]*)?"(?:[^}]|\}(?! -->))*\}) -->#', $markup, $open, PREG_OFFSET_CAPTURE ) ) {
		return null;
	}

	$depth = 0;

	preg_match_all( '#<!-- (/?)wp:group(?: \{(?:[^}]|\}(?! -->))*\})? (/?)-->#', $markup, $comments, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $open[0][1] );

	foreach ( $comments as $comment ) {
		if ( '/' === $comment[2][0] ) {
			continue;
		}

		$depth += '/' === $comment[1][0] ? -1 : 1;

		if ( 0 === $depth ) {
			return array( $open[0][1], $comment[0][1] + strlen( $comment[0][0] ) - $open[0][1] );
		}
	}

	return null;
}

/** What a content function answers, as `changed` then `skipped`. */
function said( $result ) {
	return is_array( $result ) ? array( $result['changed'], $result['skipped'] ) : null;
}

/**
 * The sections of a template's main, by the hub's section classes, in order, as the page draws them here:
 * the plugin's sponsor strip and the sign-in section draw nothing without the plugin, and the programs'
 * group holds the field-of-study flow.
 */
function sections_of( $content ) {
	preg_match( '#<main[^>]*>(.*)</main>#s', do_blocks( $content ), $main );

	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . ( $main[1] ?? '' ) . '</body></html>' );
	libxml_clear_errors();

	$xpath = new DOMXPath( $document );
	$said  = array();

	foreach ( $xpath->query( '/html/body/*' ) as $node ) {
		$classes = array_intersect( array( 'wpc-hub-hero', 'wpc-hub-roles', 'wpc-hub-doors', 'wpc-hub-anchored', 'wpc-hub-fit', 'wpc-hub-cta', 'wpc-hub-sign-in' ), explode( ' ', (string) $node->getAttribute( 'class' ) ) );
		$said[]  = $classes ? implode( ' ', $classes ) : $node->nodeName;
	}

	return array( $said, $xpath );
}

$live = (string) file_get_contents( __DIR__ . '/fixtures/saved-education-hub-1-32-11.html' );
$band = (string) theme_call( 'wpcredits_hub_roles_markup' );

echo "=== Live's saved homepage template ===\n";

$hero_span  = group_span( $live, 'wpc-hub-hero' );
$doors_span = group_span( $live, 'wpc-hub-doors' );

ck( 'the fixture is live\'s saved template: the hero, then the "Who this is for" section directly under it, two doors (students and institutions, the companies\' parked), its words as an Administrator left them, and no band of roles',
	array(
		null !== $hero_span && null !== $doors_span && $doors_span[0] === $hero_span[0] + $hero_span[1] + 2,
		substr( $live, $hero_span[0] + $hero_span[1], 2 ),
		substr_count( substr( $live, $doors_span[0], $doors_span[1] ), '<!-- wp:column -->' ),
		false !== strpos( $live, '<p class="wpc-hub-door__text">Start with a one-day workshop or a WordPress Credits pilot. </p>' ),
		false !== strpos( $live, 'wpc-hub-roles' ),
	),
	array( true, "\n\n", 2, true, false ) );

$migrated = theme_call( 'wpcredits_migrate_1_32_12_content', $live, 'page-education-hub' );
$after    = is_array( $migrated ) ? $migrated['content'] : '';

ck( 'the migration finds the section in the shape the theme shipped (its words, which an Administrator may edit, are not its shape) and puts the band of roles in its place, directly under the hero, and says so',
	said( $migrated ),
	array( array( 'roles-band' ), array() ) );

ck( 'every other byte of the template is unchanged: before the section and after it the migrated copy is the saved copy byte for byte, and the section\'s place holds the band exactly as the theme\'s pattern writes it, `wpcredits_hub_roles_markup()`',
	array(
		'' !== $band,
		substr( $after, 0, $doors_span[0] ) === substr( $live, 0, $doors_span[0] ),
		substr( $after, $doors_span[0], strlen( $band ) ) === $band,
		substr( $after, $doors_span[0] + strlen( $band ) ) === substr( $live, $doors_span[0] + $doors_span[1] ),
	),
	array( true, true, true, true ) );

list( $sections_before )         = sections_of( $live );
list( $sections_after, $page )   = sections_of( $after );
WPCPM_Sponsor_Tiers::$live       = true;
list( , $page_live )             = sections_of( $after );
WPCPM_Sponsor_Tiers::$live       = false;
$card_titles                     = '//div[contains(concat(" ", @class, " "), " wpc-hub-role ")]/h3';

ck( 'the page draws the band where the doors were, under the hero and above the programs: three cards while the Partners page is not public, as live\'s is, and four, the sponsors\' last, while it is, since the sponsors\' card is the theme\'s block, never written into the template',
	array(
		$sections_before,
		$sections_after,
		found( $page, $card_titles ),
		found( $page_live, $card_titles ),
		false !== strpos( $after, '<!-- wp:wpcredits/sponsor-card /-->' ),
		false !== strpos( $after, 'Back the next contributors' ),
	),
	array(
		array( 'wpc-hub-hero', 'wpc-hub-doors', 'wpc-hub-anchored', 'wpc-hub-cta' ),
		array( 'wpc-hub-hero', 'wpc-hub-roles', 'wpc-hub-anchored', 'wpc-hub-cta' ),
		array( 'Students', 'Mentors', 'Institutions' ),
		array( 'Students', 'Mentors', 'Institutions', 'Sponsors' ),
		true,
		false,
	) );

ck( 'run again it changes nothing, and says why: the band is there already',
	array( said( theme_call( 'wpcredits_migrate_1_32_12_content', $after, 'page-education-hub' ) ), is_array( theme_call( 'wpcredits_migrate_1_32_12_content', $after, 'page-education-hub' ) ) ? theme_call( 'wpcredits_migrate_1_32_12_content', $after, 'page-education-hub' )['content'] === $after : null ),
	array( array( array(), array( 'roles-done' ) ), true ) );

echo "\n=== The match rule: the shape the theme shipped, exactly ===\n";

$doors = substr( $live, $doors_span[0], $doors_span[1] );

/**
 * Live's template with its section changed, run through the migration.
 *
 * @param callable $change Takes the section's markup and answers the changed one.
 * @return array|null `changed`, `skipped` and whether the content came back byte for byte.
 */
function with_doors( callable $change ) {
	global $live, $doors;

	$content = str_replace( $doors, $change( $doors ), $live );
	$result  = theme_call( 'wpcredits_migrate_1_32_12_content', $content, 'page-education-hub' );

	return is_array( $result ) ? array( $result['changed'], $result['skipped'], $result['content'] === $content ) : null;
}

$kept_reason = array( array(), array( 'doors-edited' ), true );
$matched     = array( array( 'roles-band' ), array(), false );
ck( 'a section whose shape an Administrator changed is left whole, and said: a card removed or added (a door\'s column copied), a door\'s link line deleted, a door\'s picture block taken out, a paragraph turned into a heading, a block added after the grid, a style changed on the section, a class added to a card',
	array(
		with_doors( static function ( $markup ) { return preg_replace( '#\n\n<!-- wp:column -->.*?<!-- /wp:column -->(?=</div>\s*<!-- /wp:columns -->)#s', '', $markup ); } ),
		with_doors( static function ( $markup ) { return preg_replace( '#(<!-- wp:column -->.*?<!-- /wp:column -->)#s', "$1\n\n$1", $markup, 1 ); } ),
		with_doors( static function ( $markup ) { return preg_replace( '#\n\n<!-- wp:paragraph \{"className":"wpc-hub-door__go"\} -->.*?<!-- /wp:paragraph -->#s', '', $markup, 1 ); } ),
		with_doors( static function ( $markup ) { return preg_replace( '#<!-- wp:html -->.*?<!-- /wp:html -->\n\n#s', '', $markup, 1 ); } ),
		with_doors( static function ( $markup ) { return preg_replace( '#<!-- wp:paragraph \{"className":"is-style-eyebrow"\} -->\s*<p class="is-style-eyebrow">(.*?)</p>\s*<!-- /wp:paragraph -->#s', "<!-- wp:heading {\"level\":4} -->\n<h4 class=\"wp-block-heading\">$1</h4>\n<!-- /wp:heading -->", $markup, 1 ); } ),
		with_doors( static function ( $markup ) { return str_replace( '<!-- /wp:columns --></div>', "<!-- /wp:columns -->\n\n<!-- wp:paragraph -->\n<p>More soon.</p>\n<!-- /wp:paragraph --></div>", $markup ); } ),
		with_doors( static function ( $markup ) { return str_replace( array( '"padding":{"top":"48px","bottom":"48px"}', 'padding-top:48px;padding-bottom:48px' ), array( '"padding":{"top":"64px","bottom":"48px"}', 'padding-top:64px;padding-bottom:48px' ), $markup ); } ),
		with_doors( static function ( $markup ) { return preg_replace( '#"className":"is-style-card wpc-hub-door"(.*?)class="wp-block-group is-style-card wpc-hub-door"#s', '"className":"is-style-card wpc-hub-door is-featured"$1class="wp-block-group is-style-card wpc-hub-door is-featured"', $markup, 1 ); } ),
	),
	array_fill( 0, 8, $kept_reason ) );

ck( 'what an Administrator may change without changing the shape is replaced with it, the band\'s words being the design\'s: the words of every eyebrow, heading, line and link, a link\'s address, the section\'s name in List View (its metadata), or none at all, and the attributes in another order or escaped as the Site Editor writes them',
	array(
		with_doors( static function ( $markup ) { return str_replace( array( 'Students', 'Programs matched to your field of study', 'Browse programs' ), array( 'Learners', 'Pick a program', 'See all programs' ), $markup ); } ),
		with_doors( static function ( $markup ) { return str_replace( 'https://wordpresseducation.org/institution-application/', 'https://wordpresseducation.org/apply-now/', $markup ); } ),
		with_doors( static function ( $markup ) { return preg_replace( '#"metadata":\{"patternName":"wpcredits/hub-doors".*?"categories":\["wpcredits"\]\},#', '', $markup, 1 ); } ),
		with_doors( static function ( $markup ) { return str_replace( '"name":"Education Hub doors"', '"name":"Who it is for"', $markup ); } ),
		with_doors( static function ( $markup ) { return str_replace( '{"className":"is-style-card wpc-hub-door","layout":{"type":"default"}}', '{"layout":{"type":"default"},"className":"is-style-card wpc-hub-door"}', $markup ); } ),
		with_doors( static function ( $markup ) { return str_replace( '{"className":"wpc-hub-doors__grid wpc-hub-stack"}', '{"className":"wpc-hub-doors__grid wpc-hub-stack","metadata":{"name":"Doors"}}', $markup ); } ),
	),
	array_fill( 0, 6, $matched ) );

$three_doors = static function ( $markup ) {
	$company = "\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:group {\"className\":\"is-style-card wpc-hub-door\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group is-style-card wpc-hub-door\"><!-- wp:html -->\n<div class=\"wpc-hub-pic\" aria-hidden=\"true\"><div class=\"wpc-hub-pic__piece wpc-hub-pic__profile\"><span class=\"wpc-hub-pic__person\"></span><span><span class=\"wpc-hub-art__strong\">Verified WordPress experience</span><span class=\"wpc-hub-art__small\">Graduates open to work</span></span></div></div>\n<!-- /wp:html -->\n\n<!-- wp:paragraph {\"className\":\"is-style-eyebrow\"} -->\n<p class=\"is-style-eyebrow\">Companies</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Hire people with verified WordPress experience.</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"wpc-hub-door__text\"} -->\n<p class=\"wpc-hub-door__text\">Browse graduates open to work, or post internships and entry-level roles.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph {\"className\":\"wpc-hub-door__go\"} -->\n<p class=\"wpc-hub-door__go\"><a href=\"https://jobs.wordpress.net/\">Hire from WordPress<span aria-hidden=\"true\"> →</span></a></p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group --></div>\n<!-- /wp:column -->";

	return str_replace( "<!-- /wp:column --></div>\n<!-- /wp:columns -->", '<!-- /wp:column -->' . $company . "</div>\n<!-- /wp:columns -->", $markup );
};

ck( 'the section the theme shipped with its parked companies\' door shown (`wpcredits_hub_show_planned`), three doors in the order students, institutions, companies, is the theme\'s shape too, and replaced; the companies\' door first is not',
	array(
		with_doors( $three_doors ),
		with_doors( static function ( $markup ) use ( $three_doors ) {
			$three = $three_doors( $markup );

			preg_match_all( '#<!-- wp:column -->.*?<!-- /wp:column -->#s', $three, $columns );

			// The companies' door first: the first column and the third change places.
			return strtr( $three, array( $columns[0][0] => $columns[0][2], $columns[0][2] => $columns[0][0] ) );
		} ),
	),
	array( $matched, $kept_reason ) );

$programs_span = group_span( $live, 'wpc-hub-anchored' );
$moved_live    = substr( $live, 0, $doors_span[0] ) . substr( $live, $doors_span[0] + $doors_span[1] + 2, $programs_span[0] + $programs_span[1] - ( $doors_span[0] + $doors_span[1] + 2 ) ) . "\n\n" . $doors . substr( $live, $programs_span[0] + $programs_span[1] );
$moved         = theme_call( 'wpcredits_migrate_1_32_12_content', $moved_live, 'page-education-hub' );
list( $moved_sections ) = sections_of( is_array( $moved ) ? $moved['content'] : '' );

ck( 'a section of the theme\'s shape that an Administrator moved further down is taken out of its place and the band put directly under the hero, the two blank lines that stood between it and the block before it going with it; and said',
	array(
		said( $moved ),
		$moved_sections,
		is_array( $moved ) ? $moved['content'] === $after : null,
	),
	array(
		array( array( 'roles-band', 'roles-band-moved' ), array() ),
		array( 'wpc-hub-hero', 'wpc-hub-roles', 'wpc-hub-anchored', 'wpc-hub-cta' ),
		true,
	) );

$no_hero  = str_replace( substr( $live, $hero_span[0], $hero_span[1] + 2 ), '', $live );
$no_doors = str_replace( $doors . "\n\n", '', $live );
$two      = str_replace( $doors, $doors . "\n\n" . $doors, $live );

ck( 'a template with no "Who this is for" section, one with two, one with no hero to put the band under, and a template of another slug are left whole, each with its reason',
	array(
		said( theme_call( 'wpcredits_migrate_1_32_12_content', $no_doors, 'page-education-hub' ) ),
		said( theme_call( 'wpcredits_migrate_1_32_12_content', $two, 'page-education-hub' ) ),
		said( theme_call( 'wpcredits_migrate_1_32_12_content', $no_hero, 'page-education-hub' ) ),
		said( theme_call( 'wpcredits_migrate_1_32_12_content', $live, 'page-partners' ) ),
		array_map(
			static function ( $content ) {
				$result = theme_call( 'wpcredits_migrate_1_32_12_content', $content, 'page-education-hub' );

				return is_array( $result ) && $result['content'] === $content;
			},
			array( $no_doors, $two, $no_hero )
		),
	),
	array(
		array( array(), array( 'doors-missing' ) ),
		array( array(), array( 'doors-edited' ) ),
		array( array(), array( 'hero-missing' ) ),
		array( array(), array( 'other-template' ) ),
		array( true, true, true ),
	) );

echo "\n=== The runner, once, after 1.32.1's ===\n";

$init_runners = static function () {
	return array_values(
		array_map(
			static function ( $hooked ) { return $hooked[0] . ' ' . $hooked[1]; },
			array_filter( $GLOBALS['hooks']['init'] ?? array(), static function ( $hooked ) { return 0 === strpos( (string) $hooked[0], 'wpcredits_migrate_1_32_' ) && false === strpos( (string) $hooked[0], '_notice' ); } )
		)
	);
};

ck( 'the runner is hooked on init after the 1.32.1 runner; functions.php loads its file right after the 1.32.1 migration\'s',
	array(
		$init_runners(),
		false !== strpos( (string) file_get_contents( WPCREDITS_THEME_DIR . 'functions.php' ), "require_once get_theme_file_path( 'inc/migrate-1-32-1.php' );\nrequire_once get_theme_file_path( 'inc/migrate-1-32-12.php' );" ),
	),
	array( array( 'wpcredits_migrate_1_32_0 20', 'wpcredits_migrate_1_32_1 21', 'wpcredits_migrate_1_32_12 22' ), true ) );

$GLOBALS['posts'] = array(
	1654 => new WP_Post( array( 'ID' => 1654, 'post_type' => 'wp_template', 'post_name' => 'page-education-hub', 'post_status' => 'publish', 'post_author' => 7, 'post_content' => $live ) ),
	1700 => new WP_Post( array( 'ID' => 1700, 'post_type' => 'wp_template', 'post_name' => 'page-education-hub', 'post_status' => 'publish', 'post_content' => $live ) ),
);
$GLOBALS['terms'] = array(
	1654 => 'wpcredits-theme',
	1700 => 'another-theme',
);
$GLOBALS['user']  = array(
	'id'   => 0,
	'caps' => array(),
);
$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = array( 'ran' => time() - 9 * DAY_IN_SECONDS, 'templates' => array() );
$GLOBALS['calls']                             = array();

theme_call( 'wpcredits_migrate_1_32_12' );

ck( 'the runner waits for the 1.32.1 runner: before its option is set, it reads nothing, writes nothing and records nothing',
	array( $GLOBALS['calls'], get_option( 'wpcredits_migrated_1_32_12', false ) ),
	array( array(), false ) );

$GLOBALS['opts']['wpcredits_migrated_1_32_1'] = array( 'ran' => time() - 8 * DAY_IN_SECONDS, 'templates' => array() );
$GLOBALS['calls']                             = array();

theme_call( 'wpcredits_migrate_1_32_12' );

// The review of 935f4b5 (M5): the band's addresses are the plugin's pages, read as the band is written, so a run
// while the plugin is not active would write fallbacks into the saved template for good. No plugin here yet.
ck( 'while the plugin is not active the runner waits too: it takes no lock, reads and writes nothing, and records nothing, so the next request with the plugin active runs it',
	array( $GLOBALS['calls'], get_option( 'wpcredits_migrated_1_32_12', false ), $GLOBALS['posts'][1654]->post_content === $live ),
	array( array(), false, true ) );

// The plugin from here on: its mentors dashboard is how the theme tells it is active.
if ( ! class_exists( 'WPCPM_Mentors_Dashboard' ) ) {
	class WPCPM_Mentors_Dashboard {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
}

$copy             = add_revision( 1654, $live, 7 );
$GLOBALS['calls'] = array();

theme_call( 'wpcredits_migrate_1_32_12' );

$record = get_option( 'wpcredits_migrated_1_32_12' );

ck( 'on live\'s template the runner takes its lock, reads this theme\'s saved homepage template, keeps the newest revision that already holds it as the copy, writes the band with kses off, records what it did, not autoloaded, and lets go of the lock; another theme\'s copy is not touched',
	array(
		$GLOBALS['calls'],
		$GLOBALS['posts'][1654]->post_content === $after,
		$GLOBALS['posts'][1700]->post_content === $live,
		is_array( $record ) ? array_keys( $record ) : null,
		$record['templates'] ?? null,
	),
	array(
		array(
			'add_option wpcredits_migrated_1_32_12_lock autoload false',
			'get_posts ' . json_encode( array( 'post_type' => 'wp_template', 'post_status' => 'any', 'name' => 'page-education-hub', 'posts_per_page' => 20, 'orderby' => 'ID', 'order' => 'ASC' ) ),
			'kses off',
			'update 1654 to ' . sha1( $after ),
			'kses on',
			'add_option wpcredits_migrated_1_32_12 autoload false',
			'delete_option wpcredits_migrated_1_32_12_lock',
		),
		true,
		true,
		array( 'ran', 'templates' ),
		array( array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'roles-band' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => $copy->ID ) ),
	) );

$GLOBALS['calls'] = array();
theme_call( 'wpcredits_migrate_1_32_12' );

ck( 'once: with its option set, the next request does nothing at all',
	$GLOBALS['calls'], array() );

// A template whose section an Administrator changed: nothing is written, and the record says why.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_12'] );
$edited                                = str_replace( $doors, preg_replace( '#\n\n<!-- wp:paragraph \{"className":"wpc-hub-door__go"\} -->.*?<!-- /wp:paragraph -->#s', '', $doors, 1 ), $live );
$GLOBALS['posts'][1654]->post_content = $edited;
$GLOBALS['calls']                      = array();

theme_call( 'wpcredits_migrate_1_32_12' );

$kept_record = get_option( 'wpcredits_migrated_1_32_12' );

ck( 'where the section\'s shape was changed the runner writes nothing, keeps no copy, and records the template left, with its reason',
	array(
		array_values( preg_grep( '/^(update|revision|kses)/', $GLOBALS['calls'] ) ),
		$GLOBALS['posts'][1654]->post_content === $edited,
		$kept_record['templates'] ?? null,
	),
	array(
		array(),
		true,
		array( array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array(), 'skipped' => array( 'doors-edited' ), 'kept' => '' ) ),
	) );

// The review of 935f4b5 (M6): a template whose newest revision is not it, as where live's 1.32.0 run stripped its
// svg in a visitor's request. The copy is made by the runner itself, with kses off, read back byte for byte, and
// only then is the template written.
unset( $GLOBALS['opts']['wpcredits_migrated_1_32_12'] );
drop_revisions();
$GLOBALS['posts'][1654]->post_content = $live;
$stripped                              = add_revision( 1654, (string) preg_replace( '#<svg\b.*?</svg>#s', '', $live ), 7 );
$GLOBALS['kses_on']                    = true;
$GLOBALS['calls']                      = array();

theme_call( 'wpcredits_migrate_1_32_12' );

$made_record = get_option( 'wpcredits_migrated_1_32_12' );
$made_copy   = newest_revision_of( 1654 );

ck( 'with no revision that holds the template (its newest is the template without its svg, as a visitor\'s request left one), the runner makes the copy itself, with kses off, and reads it back byte for byte, svg and all, before it writes the band; the record names that copy',
	array(
		array_values( preg_grep( '/^(update|revision|kses)/', $GLOBALS['calls'] ) ),
		$made_copy && $made_copy->ID !== $stripped->ID && $made_copy->post_content === $live,
		( $made_record['templates'][0]['revision'] ?? null ) === ( $made_copy ? $made_copy->ID : false ),
		$GLOBALS['posts'][1654]->post_content === $after,
	),
	array(
		array( 'kses off', 'revision 1654 of ' . sha1( $live ), 'kses on', 'kses off', 'update 1654 to ' . sha1( $after ), 'kses on' ),
		true,
		true,
		true,
	) );

echo "\n=== The note it leaves ===\n";

$notice_for = static function () {
	$GLOBALS['inline']   = array();
	$GLOBALS['user']     = array(
		'id'   => 1,
		'caps' => array( 'edit_theme_options' ),
	);
	$GLOBALS['screen']   = 'site-editor';
	$GLOBALS['user_meta'] = array();

	wpcredits_migrate_1_32_0_notice();

	$script = implode( '', $GLOBALS['inline']['wpcredits-migration-1-32-0'] ?? array() );

	return preg_match( '/createNotice\( "info", ("(?:[^"\\\\]|\\\\.)*")/', $script, $said ) ? json_decode( $said[1] ) : '';
};

$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = array( 'ran' => time() - 9 * DAY_IN_SECONDS, 'templates' => array() );
$GLOBALS['opts']['wpcredits_migrated_1_32_1'] = array( 'ran' => time() - 8 * DAY_IN_SECONDS, 'templates' => array() );
$GLOBALS['opts']['wpcredits_migrated_1_32_12'] = $record;
$changed_notice                                = $notice_for();
$GLOBALS['opts']['wpcredits_migrated_1_32_12'] = $kept_record;
$kept_notice                                   = $notice_for();

ck( 'the Site Editor\'s notice tells Administrators what the 1.32.12 run did, with the earlier migrations\' records: that the "Who this is for" section is now the band of roles under the hero, the sponsors\' card shown while the Partners page is public, and where the template as it was is kept; or, where it was left, why, and how to show the roles by hand; the 1.32.12 record among the records a dismissal marks',
	array(
		$changed_notice,
		$kept_notice,
		array_keys( wpcredits_migrate_notice_records() ),
	),
	array(
		'The theme\'s update to 1.32.12 changed the saved homepage template: the "Who this is for" section is now the band of ways to take part, directly under the hero, its sponsors\' card shown while the Partners page is public. The template as it was before is kept in its revisions.',
		'The theme\'s update to 1.32.12 left the saved homepage template as it was: its "Who this is for" section was changed in the Site Editor, so the band of ways to take part was not put in its place. To show it, insert the "Ways to take part" pattern under the hero and remove the section.',
		array( 'wpcredits_migrated_1_32_0', 'wpcredits_migrated_1_32_1', 'wpcredits_migrated_1_32_12' ),
	) );

$sources = (string) @file_get_contents( WPCREDITS_THEME_DIR . 'inc/migrate-1-32-12.php' );

// The review of 935f4b5 (M1): an Administrator who let the earlier notices run out, never dismissing them, is not
// told their sentences again for another week with the 1.32.12 one: each record is told for a week after its own run.
$GLOBALS['opts']['wpcredits_migrated_1_32_0'] = array(
	'ran'       => time() - 20 * DAY_IN_SECONDS,
	'templates' => array( array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'program-index' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => 9 ) ),
);
$GLOBALS['opts']['wpcredits_migrated_1_32_1']  = array(
	'ran'       => time() - 19 * DAY_IN_SECONDS,
	'templates' => array( array( 'id' => 1654, 'slug' => 'page-education-hub', 'changed' => array( 'program-index-marked' ), 'skipped' => array(), 'kept' => 'revision', 'revision' => 10 ) ),
);
$GLOBALS['opts']['wpcredits_migrated_1_32_12'] = $record;
$with_old_records                              = $notice_for();
$GLOBALS['opts']['wpcredits_migrated_1_32_1']['ran'] = time() - 3 * DAY_IN_SECONDS;
$with_recent_1_32_1                            = $notice_for();

ck( 'each record is told for a week after its own run: with 1.32.0 and 1.32.1 records of changes 20 and 19 days old, the notice tells the 1.32.12 sentence alone; a 1.32.1 record 3 days old is told before it, the 1.32.0 one still not',
	array( $with_old_records, $with_recent_1_32_1 ),
	array(
		$changed_notice,
		wpcredits_migrate_1_32_1_summary( $GLOBALS['opts']['wpcredits_migrated_1_32_1'] ) . ' ' . $changed_notice,
	) );

ck( 'the migration\'s words name the homepage, never "Education Hub", and hold no em dash or en dash',
	array( '' !== $sources, preg_match( "/__\\( '[^']*Education Hub/", $sources ), preg_match( "/\xE2\x80\x94|\xE2\x80\x93/", $sources ) ),
	array( true, 0, 0 ) );

printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILURE(S)', $fail ) : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
