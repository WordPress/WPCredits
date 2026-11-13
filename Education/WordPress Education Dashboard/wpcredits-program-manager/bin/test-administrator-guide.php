<?php
/**
 * The Administrator guide in the Administrator Dashboard's frame (1.123.70).
 *
 * Plain PHP with stubs: the page is wrapped only when it is the guide, drawn for an Administrator, in a
 * theme that names the page; once; the list of sections is read from the guide's own headings, their ids
 * kept as the build wrote them and given where a heading has none.
 *
 * Usage: php bin/test-administrator-guide.php
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function __( $s, $d = null ) { return $s; }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function add_action() {}
function add_filter() {}

// The request, as each check sets it.
$GLOBALS['req'] = array();
function req( $key ) { return $GLOBALS['req'][ $key ]; }
function is_admin() { return req( 'admin' ); }
function is_feed() { return req( 'feed' ); }
function doing_filter( $hook ) { return 'get_the_excerpt' === $hook && req( 'excerpt' ); }
function is_singular( $type = '' ) { return req( 'singular' ); }
function in_the_loop() { return req( 'loop' ); }
function is_main_query() { return req( 'main' ); }
function get_post() { return req( 'post' ); }
function get_queried_object_id() { return isset( $GLOBALS['req']['queried'] ) ? $GLOBALS['req']['queried'] : ( req( 'post' ) instanceof WP_Post ? req( 'post' )->ID : 0 ); }
function is_user_logged_in() { return req( 'logged_in' ); }
function current_user_can( $cap ) { return 'wpcpm_manage_program' === $cap && req( 'manager' ); }
function get_theme_support( $feature ) { return 'wpcpm-dashboard-frame' === $feature ? req( 'support' ) : false; }

$GLOBALS['styles']   = array();
$GLOBALS['scripts']  = array();
$GLOBALS['enqueued'] = array();
function wp_style_is( $h, $list = 'enqueued' ) { return isset( $GLOBALS['styles'][ $h ] ); }
function wp_register_style( $h, $src, $deps = array(), $ver = false ) { $GLOBALS['styles'][ $h ] = array( $src, $deps, $ver ); }
function wp_enqueue_style( $h ) { $GLOBALS['enqueued'][] = 'style:' . $h; }
function wp_script_is( $h, $list = 'enqueued' ) { return isset( $GLOBALS['scripts'][ $h ] ); }
function wp_register_script( $h, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['scripts'][ $h ] = array( $src, $deps, $ver, $footer ); }
function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = 'script:' . $h; }
$GLOBALS['localized'] = array();
function wp_localize_script( $h, $name, $data ) { $GLOBALS['localized'][ $h ] = array( $name, $data ); return true; }

class WP_Post {
	public $ID = 0, $post_title = '', $post_name = '', $post_type = 'page', $post_parent = 0;
}

class WPCPM_Roles {
	const CAP_MANAGE = 'wpcpm_manage_program';
}

class WPCPM_Settings_Screen {
	const GUIDE_PAGE_PATH = 'administrator-guide';
}

class WPCPM_Icons {
	public static function svg( $name, $size ) { return '<svg data-icon="' . $name . '"></svg>'; }
}

// The frame, standing in: whether the theme names the page is the real rule, read from the theme
// support; open() and close() leave a mark and record what they were given.
class WPCPM_Dashboard_Frame {
	const STYLE = 'wpcpm-dashboard-frame';
	public static $opened = array();
	public static function owns_page( $page ) {
		$support = get_theme_support( 'wpcpm-dashboard-frame' );
		return is_array( $support ) && isset( $support[0] ) && in_array( $page, (array) $support[0], true );
	}
	public static function open( array $args ) { self::$opened[] = $args; echo '[open]'; }
	public static function close() { echo '[close]'; }
}

class WPCPM_Administrators_Dashboard {
	const VIEW_GUIDE = 'guide';
	public static function menu_args( $view ) { return array( 'page' => 'administrator-guide', 'from' => 'menu_args:' . $view ); }
}

// The Sponsor Dashboard's menu and who acts for a sponsor (1.123.78), standing in: a switch for the reader.
class WPCPM_Sponsors_Dashboard {
	public static function menu_args( $view ) { return array( 'page' => 'sponsor-guide', 'from' => 'sponsor menu_args:' . $view ); }
}

class WPCPM_Sponsor_Members {
	public static function is_member( $user = null, $record = '' ) { return ! empty( $GLOBALS['req']['member'] ); }
}

require_once __DIR__ . '/../includes/modules/class-wpcpm-administrator-guide-view.php';

$fail   = 0;
$checks = 0;

function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		echo '       expected: ' . var_export( $expected, true ) . "\n";
		echo '       actual:   ' . var_export( $actual, true ) . "\n";
	}
}

function page( $name, $parent = 0, $type = 'page' ) {
	$post              = new WP_Post();
	$post->ID          = 560;
	$post->post_name   = $name;
	$post->post_title  = 'Administrator guide';
	$post->post_parent = $parent;
	$post->post_type   = $type;
	return $post;
}

// An Administrator reading the guide, in a theme that leaves the page to the frame.
function reading( array $change = array() ) {
	$GLOBALS['req'] = array_merge(
		array(
			'admin'     => false,
			'feed'      => false,
			'excerpt'   => false,
			'singular'  => true,
			'loop'      => true,
			'main'      => true,
			'post'      => page( 'administrator-guide' ),
			'logged_in' => true,
			'manager'   => true,
			'support'   => array( array( 'administrator', 'administrator-guide' ) ),
		),
		$change
	);
}

// A fresh class state for each filter check: the guide is drawn once a request.
function fresh() {
	$drawn = new ReflectionProperty( 'WPCPM_Administrator_Guide_View', 'drawn' );
	if ( PHP_VERSION_ID < 80100 ) {
		$drawn->setAccessible( true );
	}
	$drawn->setValue( null, false );
	WPCPM_Dashboard_Frame::$opened = array();
	$GLOBALS['enqueued']           = array();
}

/*
 * --------------------------------------------------------------------
 * The sections
 * --------------------------------------------------------------------
 */

$guide = '<p>Lede.</p>'
	. '<h3 class="wp-block-heading">Before any section</h3>'
	. '<h2 class="wp-block-heading" id="signing-in">Signing in</h2><p>Text.</p>'
	. '<h3 class="wp-block-heading" id="signing-in-with-a-code">Signing in with a <strong>code</strong></h3>'
	. '<h4 id="deeper">Deeper</h4>'
	. '<h3 class="wp-block-heading">Settings</h3>'
	. '<h2 class="wp-block-heading">The track&#8217;s page &amp; more</h2>'
	. '<h3 class="wp-block-heading">Settings</h3>'
	. '<h3 class="wp-block-heading">  </h3>'
	. '<h2 class="wp-block-heading">Signing in</h2>';

$found = WPCPM_Administrator_Guide_View::sections( $guide );

ck( 'the sections are the h2s and their parts the h3s under each, in the page\'s order; a part before the first section is a section of its own; h4s and empty headings are not listed', array_map( static function ( $s ) { return array( $s['text'], array_column( $s['parts'], 'text' ) ); }, $found['sections'] ), array(
	array( 'Before any section', array() ),
	array( 'Signing in', array( 'Signing in with a code', 'Settings' ) ),
	array( 'The track’s page & more', array( 'Settings' ) ),
	array( 'Signing in', array() ),
) );

ck( 'an id the build wrote is kept as it is; a heading without one gets the build\'s slug of its words, numbered past every id already on the page and every slug given before it', array_map( static function ( $s ) { return array( $s['id'], array_column( $s['parts'], 'id' ) ); }, $found['sections'] ), array(
	array( 'before-any-section', array() ),
	array( 'signing-in', array( 'signing-in-with-a-code', 'settings' ) ),
	array( 'the-track-s-page-more', array( 'settings-2' ) ),
	array( 'signing-in-2', array() ),
) );

ck( 'the ids given are written onto the headings themselves, the build\'s left untouched, so every link in the list lands', array(
	substr_count( $found['content'], '<h3 class="wp-block-heading" id="before-any-section">Before any section</h3>' ),
	substr_count( $found['content'], '<h2 class="wp-block-heading" id="signing-in">Signing in</h2>' ),
	substr_count( $found['content'], '<h3 class="wp-block-heading" id="signing-in-with-a-code">Signing in with a <strong>code</strong></h3>' ),
	substr_count( $found['content'], '<h3 class="wp-block-heading" id="settings">Settings</h3>' ),
	substr_count( $found['content'], '<h3 class="wp-block-heading" id="settings-2">Settings</h3>' ),
	substr_count( $found['content'], '<h2 class="wp-block-heading" id="signing-in-2">Signing in</h2>' ),
	substr_count( $found['content'], '<h4 id="deeper">Deeper</h4>' ),
	substr_count( $found['content'], '<h3 class="wp-block-heading">  </h3>' ),
), array( 1, 1, 1, 1, 1, 1, 1, 1 ) );

// The release's review (1.123.70): an id written with spaces around its `=`, in single quotes or in none is
// still the heading's id, and the link is to it; an id holding a `%` or a space is percent-encoded in the link.
$spelled = WPCPM_Administrator_Guide_View::sections( '<h2 id = "saved-anchor">Renamed heading</h2><h2 id=\'single\'>Two</h2><h2 id=bare class="x">Three</h2><h2>saved anchor</h2>' );
$quoted = WPCPM_Administrator_Guide_View::sections( '<h2 title="The id=account setting" id="real">Account</h2><h2 data-note="a > b">Arrow</h2><h2>Account</h2>' );
ck( 'an id= inside another attribute\'s value is not the id, and a quoted > does not end the tag (without core\'s parser, before WordPress 6.2)', array(
	array_column( $quoted['sections'], 'id' ),
	substr_count( $quoted['content'], '<h2 data-note="a > b" id="arrow">Arrow</h2>' ),
), array( array( 'real', 'arrow', 'account' ), 1 ) );
// The fix's review (1.123.70): a long attribute ran past PCRE's JIT stack and the replace returned null,
// losing the whole guide.
$long = WPCPM_Administrator_Guide_View::sections( '<h2 data-meta=' . str_repeat( 'a', 20000 ) . '>Long</h2><h2 title="' . str_repeat( 'b', 20000 ) . '">Longer</h2><p>Text.</p>' );
ck( 'a heading with a very long attribute is read, and the guide comes back whole', array( array_column( $long['sections'], 'id' ), strlen( $long['content'] ) > 40000, false !== strpos( $long['content'], '<p>Text.</p>' ) ), array( array( 'long', 'longer' ), true, true ) );
$entity = WPCPM_Administrator_Guide_View::sections( '<h2 id="a&sol;b">Slash</h2><h2>Next</h2>' );
ck( 'and an id\'s character references are read by the HTML5 rules', array_column( $entity['sections'], 'id' ), array( 'a/b', 'next' ) );
ck( 'an id however it is spelled is kept, the heading gets no second one, and a later heading does not take it', array(
	array_column( $spelled['sections'], 'id' ),
	substr_count( $spelled['content'], ' id=' ) + substr_count( $spelled['content'], ' id =' ),
), array( array( 'saved-anchor', 'single', 'bare', 'saved-anchor-2' ), 4 ) );

$odd = WPCPM_Administrator_Guide_View::render( '<h2 id="50%-complete">Half</h2><h2 id="two words">Two</h2><h2 id="signing-in-2">Plain</h2>', 'Guide' );
ck( 'a link to an id is percent-encoded, a slug left as it is', array(
	false !== strpos( $odd, '<a class="wpcpm-guide__link" href="#50%25-complete">Half</a>' ),
	false !== strpos( $odd, '<a class="wpcpm-guide__link" href="#two%20words">Two</a>' ),
	false !== strpos( $odd, '<a class="wpcpm-guide__link" href="#signing-in-2">Plain</a>' ),
), array( true, true, true ) );

/*
 * --------------------------------------------------------------------
 * When the page is wrapped
 * --------------------------------------------------------------------
 */

$cases = array(
	'an Administrator reading the guide'              => array(),
	'in wp-admin'                                     => array( 'admin' => true ),
	'in a feed'                                       => array( 'feed' => true ),
	'while an excerpt is made'                        => array( 'excerpt' => true ),
	'on a list of posts'                              => array( 'singular' => false ),
	'outside the loop'                                => array( 'loop' => false ),
	'in a secondary query'                            => array( 'main' => false ),
	'another page'                                    => array( 'post' => page( 'mentor-guide' ) ),
	'a child page at the same slug'                   => array( 'post' => page( 'administrator-guide', 12 ) ),
	'a post at the same slug'                         => array( 'post' => page( 'administrator-guide', 0, 'post' ) ),
	'the guide drawn by a Query Loop in another page' => array( 'queried' => 4242 ),
	'a visitor who is not logged in'                  => array( 'logged_in' => false ),
	'a reader who is not an Administrator'            => array( 'manager' => false ),
	'a theme that names only the dashboard'           => array( 'support' => array( array( 'administrator' ) ) ),
	'a theme that names no page for the frame'        => array( 'support' => false ),
);
$wrapped = array();

foreach ( $cases as $name => $change ) {
	fresh();
	reading( $change );
	$wrapped[ $name ] = '<p>x</p>' !== WPCPM_Administrator_Guide_View::filter_content( '<p>x</p>' );
}

ck( 'the page is wrapped only when it is the guide, the top-level page at its path, drawn in the main loop for a logged-in Administrator, in a theme that names it; never in wp-admin, a feed or an excerpt', array_keys( array_filter( $wrapped ) ), array( 'an Administrator reading the guide' ) );

fresh();
reading();
$first  = WPCPM_Administrator_Guide_View::filter_content( $guide );
$second = WPCPM_Administrator_Guide_View::filter_content( $guide );

ck( 'once a request: whatever asks for the content after the page has drawn it gets the content as it was', array( count( WPCPM_Dashboard_Frame::$opened ), $second ), array( 1, $guide ) );

ck( 'in the Administrator Dashboard\'s menu, as the dashboard builds it for the guide', WPCPM_Dashboard_Frame::$opened[0], array( 'page' => 'administrator-guide', 'from' => 'menu_args:guide' ) );

ck( 'the frame, the serif head with the page\'s title as the one H1, the list of sections, then the guide, and the frame closed', array(
	0 === strpos( $first, '[open]<div class="wpcpm-guide" data-wpcpm-table-label="Table, scrolls sideways"><header class="wpcpm-head wpcpm-guide__head"><div class="wpcpm-head__copy"><h1 class="wpcpm-head__title">Administrator guide</h1></div></header><nav class="wpcpm-guide__contents" aria-labelledby="wpcpm-guide-contents-title" data-wpcpm-guide-contents>' ),
	false !== strpos( $first, '</ul></nav><div class="wpcpm-guide__body"><p>Lede.</p><h3 class="wp-block-heading" id="before-any-section">' ),
	substr( $first, -strlen( '</h2></div></div>[close]' ) ),
	substr_count( $first, '<h1' ),
), array( true, true, '</h2></div></div>[close]', 1 ) );

ck( 'the stylesheet, on the frame\'s, and the script, in the footer, are registered and enqueued when the guide is drawn', array(
	$GLOBALS['styles']['wpcpm-administrator-guide'],
	$GLOBALS['scripts']['wpcpm-administrator-guide'],
	$GLOBALS['enqueued'],
), array(
	array( WPCPM_PLUGIN_URL . 'assets/css/administrator-guide.css', array( 'wpcpm-dashboard-frame' ), 'test' ),
	array( WPCPM_PLUGIN_URL . 'assets/js/administrator-guide.js', array(), 'test', true ),
	array( 'style:wpcpm-administrator-guide', 'script:wpcpm-administrator-guide' ),
) );

/*
 * --------------------------------------------------------------------
 * The list
 * --------------------------------------------------------------------
 */

ck( 'the list\'s heading holds the button that folds it, hidden until the script shows it on a narrow main area, and controlling the list', false !== strpos( $first, '<h2 class="wpcpm-guide__contents-title" id="wpcpm-guide-contents-title"><span class="wpcpm-guide__contents-label">On this page</span><button class="wpcpm-guide__contents-toggle" type="button" aria-expanded="false" aria-controls="wpcpm-guide-list" hidden>On this page<svg data-icon="chevron-down"></svg></button></h2><div class="wpcpm-guide__search" data-wpcpm-guide-search hidden>' ), true );

ck( 'each section a link to its heading, its parts a list inside it, the words escaped', array(
	false !== strpos( $first, '<li class="wpcpm-guide__entry"><a class="wpcpm-guide__link" href="#signing-in">Signing in</a><ul class="wpcpm-guide__parts"><li><a class="wpcpm-guide__link wpcpm-guide__link--part" href="#signing-in-with-a-code">Signing in with a code</a></li><li><a class="wpcpm-guide__link wpcpm-guide__link--part" href="#settings">Settings</a></li></ul></li>' ),
	false !== strpos( $first, '<a class="wpcpm-guide__link" href="#the-track-s-page-more">The track’s page &amp; more</a>' ),
	substr_count( $first, 'class="wpcpm-guide__entry"' ),
	substr_count( $first, 'wpcpm-guide__link--part' ),
), array( true, true, 4, 3 ) );

fresh();
reading();
$one = WPCPM_Administrator_Guide_View::filter_content( '<h2>Only</h2><h3>Part</h3><p>Text.</p>' );

ck( 'a page with one section draws no list: there is nothing to go between, and the guide alone fills the page', array( false !== strpos( $one, '<nav' ), false !== strpos( $one, '<div class="wpcpm-guide__body"><h2 id="only">Only</h2>' ) ), array( false, true ) );

fresh();
reading( array( 'post' => page( 'administrator-guide' ) ) );
$GLOBALS['req']['post']->post_title = '<b>Guide</b> & notes';
$titled = WPCPM_Administrator_Guide_View::filter_content( $guide );
ck( 'the title is escaped, and a page with none still has its one H1, the guide\'s name', array(
	false !== strpos( $titled, '<h1 class="wpcpm-head__title">&lt;b&gt;Guide&lt;/b&gt; &amp; notes</h1>' ),
	false !== strpos( WPCPM_Administrator_Guide_View::render( $guide, '  ' ), '<h1 class="wpcpm-head__title">Administrator guide</h1>' ),
), array( true, true ) );

/*
 * --------------------------------------------------------------------
 * The guides as built
 * --------------------------------------------------------------------
 */

// A guide that names a shortcode quotes it (bin/build-docs.php, 1.123.70): WordPress runs every `[name]`
// it finds on a page, and the Administrator guide drew a working Mentor Application form mid-text.
$running = array();
foreach ( glob( __DIR__ . '/../docs/build/*.html' ) as $built ) {
	$text = preg_replace( '/<!--.*?-->/s', '', (string) file_get_contents( $built ) );
	if ( preg_match_all( '/\[[a-z_][^\]\s]*/i', $text, $found ) ) {
		$running[ basename( $built ) ] = $found[0];
	}
}
ck( 'no built guide holds a bracket WordPress could read as a shortcode: each is written as &#91;, which reads the same', $running, array() );


/*
 * --------------------------------------------------------------------
 * The Sponsor guide, in the Sponsor Dashboard's frame (1.123.78)
 * --------------------------------------------------------------------
 */

$sponsor_guide = static function () {
	$post             = page( 'sponsor-guide' );
	$post->ID         = 1770;
	$post->post_title = 'Sponsor guide';
	return $post;
};
$all_frames    = array( array( 'administrator', 'administrator-guide', 'administrator-updates', 'sponsor', 'sponsor-guide', 'sponsor-updates' ) );

ck( 'each guide is known by its page\'s path, a top-level page, and frame_for() names its frame', array(
	WPCPM_Administrator_Guide_View::guide_of( page( 'administrator-guide' ) ),
	WPCPM_Administrator_Guide_View::guide_of( $sponsor_guide() ),
	WPCPM_Administrator_Guide_View::guide_of( page( 'sponsor-guide', 12 ) ),
	WPCPM_Administrator_Guide_View::guide_of( page( 'mentor-guide' ) ),
	WPCPM_Administrator_Guide_View::is_guide( $sponsor_guide() ),
), array( 'administrator-guide', 'sponsor-guide', '', '', false ) );

$sponsor_cases = array(
	'somebody who acts for a sponsor'              => array( 'manager' => false, 'member' => true ),
	'an Administrator'                             => array(),
	'a reader who acts for no sponsor'             => array( 'manager' => false ),
	'a visitor who is not logged in'               => array( 'manager' => false, 'member' => true, 'logged_in' => false ),
	'a theme that names only the Administrator\'s' => array( 'manager' => false, 'member' => true, 'support' => array( array( 'administrator', 'administrator-guide' ) ) ),
);
$sponsor_wrapped = array();
$sponsor_frames  = array();

foreach ( $sponsor_cases as $name => $change ) {
	fresh();
	reading( array_merge( array( 'post' => $sponsor_guide(), 'support' => $all_frames ), $change ) );
	$sponsor_frames[ $name ]  = WPCPM_Administrator_Guide_View::frame_for( $sponsor_guide() );
	$sponsor_wrapped[ $name ] = '<p>x</p>' !== WPCPM_Administrator_Guide_View::filter_content( '<p>x</p>' );
}

ck( 'the Sponsor guide is drawn in the frame for somebody who acts for a sponsor and for an Administrator, in a theme that names it, and the theme is told the frame for exactly those readers', array( array_keys( array_filter( $sponsor_wrapped ) ), array_keys( array_filter( $sponsor_frames ) ) ), array( array( 'somebody who acts for a sponsor', 'an Administrator' ), array( 'somebody who acts for a sponsor', 'an Administrator' ) ) );

fresh();
reading( array( 'post' => $sponsor_guide(), 'support' => $all_frames, 'manager' => false, 'member' => true ) );
$sponsor_drawn = WPCPM_Administrator_Guide_View::filter_content( $guide );

ck( 'in the Sponsor Dashboard\'s menu, with the page\'s title as the one H1 and its list of sections', array(
	WPCPM_Dashboard_Frame::$opened[0],
	false !== strpos( $sponsor_drawn, '<h1 class="wpcpm-head__title">Sponsor guide</h1>' ),
	false !== strpos( $sponsor_drawn, '<nav class="wpcpm-guide__contents"' ),
), array( array( 'page' => 'sponsor-guide', 'from' => 'sponsor menu_args:guide' ), true, true ) );

fresh();
reading( array( 'post' => page( 'administrator-guide' ), 'support' => $all_frames, 'manager' => false, 'member' => true ) );
ck( 'and somebody who acts for a sponsor never gets the Administrator guide in a frame', array( WPCPM_Administrator_Guide_View::frame_for( page( 'administrator-guide' ) ), '<p>x</p>' === WPCPM_Administrator_Guide_View::filter_content( '<p>x</p>' ) ), array( '', true ) );

/*
 * --------------------------------------------------------------------
 * The reference folds, the guide can be searched (the guides' clarity release)
 * --------------------------------------------------------------------
 */

$ref = '<h2 class="wp-block-heading wpcpm-guide-ref" id="reference">Reference</h2>'
	. '<p>What the reference holds.</p>'
	. '<h3 class="wp-block-heading" id="first-part">First <strong>part</strong></h3>'
	. '<p>One.</p><table><tbody><tr><td>Cell</td></tr></tbody></table><ul><li>Item</li></ul>'
	. '<h4 id="deeper-one">Deeper</h4><p>Deep text.</p>'
	. '<h3 id=\'second-part\' title="a > b">Second part</h3>'
	. '<p>Two.</p>'
	. '<h2 id="plain">Plain</h2><p>Plain text.</p><h3 id="plain-part">Plain part</h3><p>Stays.</p>';

$folded = WPCPM_Administrator_Guide_View::fold( $ref );

ck( 'fold(): under a reference heading each h3 and what follows it up to the next h2 or h3 is its h3 (class added, id kept) and a body div after it, nothing hidden; the text before the first part, and a plain section with its parts, are untouched', $folded,
	'<h2 class="wp-block-heading wpcpm-guide-ref" id="reference">Reference</h2>'
	. '<p>What the reference holds.</p>'
	. '<h3 class="wp-block-heading wpcpm-guide__fold-heading" id="first-part">First <strong>part</strong></h3><div class="wpcpm-guide__fold-body" id="first-part-part"><p>One.</p><table><tbody><tr><td>Cell</td></tr></tbody></table><ul><li>Item</li></ul><h4 id="deeper-one">Deeper</h4><p>Deep text.</p></div>'
	. '<h3 class="wpcpm-guide__fold-heading" id=\'second-part\' title="a > b">Second part</h3><div class="wpcpm-guide__fold-body" id="second-part-part"><p>Two.</p></div>'
	. '<h2 id="plain">Plain</h2><p>Plain text.</p><h3 id="plain-part">Plain part</h3><p>Stays.</p>' );

$plain = '<h2 id="a">A</h2><p>Text</p><h3 id="b">B</h3><table><tr><td><h3>In a cell</h3></td></tr></table>';
ck( 'fold(): content with no reference heading comes back byte for byte', array(
	WPCPM_Administrator_Guide_View::fold( $plain ),
	WPCPM_Administrator_Guide_View::fold( '' ),
	WPCPM_Administrator_Guide_View::fold( '<p>only text</p>' ),
), array( $plain, '', '<p>only text</p>' ) );

$nested = '<h2 class="wpcpm-guide-ref" id="r">R</h2><div><h3 id="x">Inside a div</h3><p>t</p></div><h3 id="y">Y</h3><p>t</p>';
$open_list = '<h2 class="wpcpm-guide-ref" id="r">R</h2><ul><li><h3 id="x">In a list</h3></li></ul><h3 id="y">Y</h3><p>t</p>';
$broken    = '<h2 class="wpcpm-guide-ref" id="r">R</h2><div><h3 id="y">Y</h3><p>t</p>';
ck( 'fold(): a heading that is not at the top level, or markup left open, leaves the content as it was rather than producing broken markup', array(
	WPCPM_Administrator_Guide_View::fold( $nested ),
	WPCPM_Administrator_Guide_View::fold( $open_list ),
	WPCPM_Administrator_Guide_View::fold( $broken ),
), array( $nested, $open_list, $broken ) );

$classed = WPCPM_Administrator_Guide_View::fold( '<h2 data-x=\'a > b\' class = "x  wpcpm-guide-ref y">R</h2><h3 id=bare>Bare</h3><p>t</p><h3 class=wp-block-heading>  </h3><p>after</p>' );
ck( 'fold(): the class is found among others in any quoting style, a part with no words is not a part, and an h3 holding a quoted > is read whole', array(
	0 === strpos( $classed, '<h2 data-x=\'a > b\' class = "x  wpcpm-guide-ref y">R</h2><h3 class="wpcpm-guide__fold-heading" id=bare>Bare</h3><div class="wpcpm-guide__fold-body" id="bare-part"><p>t</p><h3 class=wp-block-heading>  </h3><p>after</p></div>' ),
	substr_count( $classed, 'wpcpm-guide__fold-body' ),
), array( true, 1 ) );

$taken = '<h2 class="wpcpm-guide-ref" id="r">R</h2><h3 id="a">A</h3><p>t</p><p id="a-part">taken</p><h3 id="b">B</h3><p>u</p>';
ck( 'fold(): a part whose body id (the heading\'s id and -part) is already on the page is left as it was, the others still fold', WPCPM_Administrator_Guide_View::fold( $taken ),
	'<h2 class="wpcpm-guide-ref" id="r">R</h2><h3 id="a">A</h3><p>t</p><p id="a-part">taken</p><h3 class="wpcpm-guide__fold-heading" id="b">B</h3><div class="wpcpm-guide__fold-body" id="b-part"><p>u</p></div>' );

ck( 'fold(): a class that only contains the name (wpcpm-guide-reference) is not a reference heading', WPCPM_Administrator_Guide_View::fold( '<h2 class="wpcpm-guide-reference">R</h2><h3>P</h3><p>t</p>' ), '<h2 class="wpcpm-guide-reference">R</h2><h3>P</h3><p>t</p>' );

$long_attr = '<h2 class="wpcpm-guide-ref" data-meta=' . str_repeat( 'a', 20000 ) . '>R</h2><h3 title="' . str_repeat( 'b', 20000 ) . '">P</h3><p>t</p>';
$long_out  = WPCPM_Administrator_Guide_View::fold( $long_attr );
ck( 'fold(): a very long attribute does not lose the guide', array( strlen( $long_out ) >= strlen( $long_attr ), false !== strpos( $long_out, '<p>t</p>' ) ), array( true, true ) );

// render(): the Administrator guide folds; the search box is in the list's nav, hidden, with its label, status and list.
$both = '<h2 class="wpcpm-guide-ref" id="step-by-step">Reference</h2><h3 id="one">One</h3><p>Text.</p><h3 id="two">Two</h3><p>More.</p><h2 id="start">Start</h2><p>Hello.</p>';
$GLOBALS['localized'] = array();
$admin_out   = WPCPM_Administrator_Guide_View::render( $both, 'Guide' );
$sponsor_out = WPCPM_Administrator_Guide_View::render( '<h2 id="a">A</h2><h3 id="b">B</h3><p>t</p><h2 id="c">C</h2><p>u</p>', 'Guide', 'sponsor-guide' );
$search      = '<div class="wpcpm-guide__search" data-wpcpm-guide-search hidden><label class="wpcpm-guide__search-label" for="wpcpm-guide-search">Search the guide <span class="wpcpm-guide__search-hint">Press / to search</span></label><input class="wpcpm-guide__search-input" id="wpcpm-guide-search" type="search" autocomplete="off" spellcheck="false" enterkeyhint="search"><p class="wpcpm-guide__search-status" role="status"></p><ol class="wpcpm-guide__results"></ol></div>';

ck( 'render(): the search box is in the list\'s nav, above the list, drawn hidden for the script to show, on both guides', array(
	false !== strpos( $admin_out, $search . '<ul class="wpcpm-guide__list"' ),
	false !== strpos( $sponsor_out, $search . '<ul class="wpcpm-guide__list"' ),
	substr_count( $admin_out, '<input' ),
), array( true, true, 1 ) );

ck( 'render(): the Administrator guide\'s reference is folded; the Sponsor guide has no folded part', array(
	substr_count( $admin_out, 'class="wpcpm-guide__fold-body"' ),
	false !== strpos( $admin_out, '<h3 class="wpcpm-guide__fold-heading" id="one">One</h3><div class="wpcpm-guide__fold-body" id="one-part">' ),
	substr_count( $sponsor_out, 'wpcpm-guide__fold' ),
	false !== strpos( $sponsor_out, '<div class="wpcpm-guide__body"><h2 id="a">A</h2><h3 id="b">B</h3><p>t</p><h2 id="c">C</h2><p>u</p></div>' ),
), array( 2, true, 0, true ) );

ck( 'the list of sections still holds every h2 and h3 as a link to its id, folded or not', array(
	substr_count( $admin_out, 'href="#step-by-step"' ),
	substr_count( $admin_out, 'href="#one"' ),
	substr_count( $admin_out, 'href="#two"' ),
	substr_count( $admin_out, 'href="#start"' ),
	substr_count( $admin_out, 'wpcpm-guide__link--part' ),
), array( 1, 1, 1, 1, 2 ) );

$strings = isset( $GLOBALS['localized']['wpcpm-administrator-guide'] ) ? $GLOBALS['localized']['wpcpm-administrator-guide'] : array( '', array() );
ck( 'the script is given every string it shows, through wp_localize_script, as wpcpmGuideStrings', array( $strings[0], array_keys( $strings[1] ) ), array( 'wpcpmGuideStrings', array( 'found_one', 'found_many', 'none', 'cut', 'ellipsis', 'separator', 'open_all', 'close_all' ) ) );
ck( 'and they read as the brief words them', array( $strings[1]['found_one'], $strings[1]['found_many'], $strings[1]['none'], $strings[1]['cut'], $strings[1]['open_all'], $strings[1]['close_all'] ), array( '%d place found', '%d places found', 'No part of the guide has these words.', 'Showing the first %1$d of %2$d.', 'Open every part', 'Close every part' ) );

// The script, read as source.
$js  = (string) file_get_contents( __DIR__ . '/../assets/js/administrator-guide.js' );
$css = (string) file_get_contents( __DIR__ . '/../assets/css/administrator-guide.css' );
$plain_js = preg_replace( '#/\*.*?\*/|(?<![:"\'])//[^\n]*#s', '', $js );

ck( 'the script reads its strings from the page and holds no English of its own', array(
	false !== strpos( $js, 'wpcpmGuideStrings' ),
	1 === preg_match( '/Open every part|Close every part|places? found|Search the guide|No part of the guide/', $plain_js ),
), array( true, false ) );

ck( 'the script checks CSS.highlights before it marks anything', 1 === preg_match( '/CSS\.highlights/', $js ) && 1 === preg_match( '/CSS\s*&&\s*(?:window\.)?CSS\.highlights|window\.CSS\s*&&\s*window\.CSS\.highlights|typeof Highlight/', $js ), true );

ck( 'the script never sets innerHTML, outerHTML or insertAdjacentHTML: rows are built with textContent', 1 === preg_match( '/innerHTML|outerHTML|insertAdjacentHTML|document\.write/', $plain_js ), false );

ck( 'the script opens a closed part for a link into it, on load, on hashchange and on a press of an in-page link, and searches after a 150 ms pause, from 2 characters, 30 results at most', array(
	false !== strpos( $js, "'hashchange'" ),
	false !== strpos( $js, 'wpcpm-guide__fold-body' ),
	1 === preg_match( '/\b150\b/', $js ),
	1 === preg_match( '/\b30\b/', $js ),
	1 === preg_match( '/prefers-reduced-motion/', $js ),
	1 === preg_match( '/tabindex/', $js ),
), array( true, true, true, true, true, true ) );

ck( 'the script is valid JavaScript (node --check, where node is installed)', ( static function () {
	$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
	if ( '' === $node ) { return true; }
	exec( escapeshellarg( $node ) . ' --check ' . escapeshellarg( __DIR__ . '/../assets/js/administrator-guide.js' ) . ' 2>&1', $out, $code );
	return 0 === $code ? true : implode( "\n", $out );
} )(), true );

ck( 'the script folds only where onbeforematch is supported, listens for beforematch and beforeprint, and writes hidden only as until-found', array(
	1 === preg_match( "/'onbeforematch'\\s+in\\s+HTMLElement\\.prototype/", $js ),
	false !== strpos( $js, "'beforematch'" ),
	false !== strpos( $js, "'beforeprint'" ) && false !== strpos( $js, "'afterprint'" ),
	1 === preg_match( "/setAttribute\\(\\s*+'hidden'\\s*+,\\s*+(?!'until-found')/", $js ),
	1 === preg_match( '/(?:part|fold|body)\\.hidden\\s*=/', $js ),
	1 === preg_match( '/<details|summary/', $css . $js ),
	1 === preg_match( '/\\.wpcpm-guide__fold-body\\s*\\{[^}]*(display|visibility)\\s*:/', $css ),
), array( true, true, true, false, false, false, false ) );

$upper = array();
if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER ) ) {
	foreach ( $rules as $rule ) {
		if ( false !== strpos( $rule[1], '.wpcpm-guide__body' ) && false !== strpos( $rule[2], 'text-transform: uppercase' ) ) {
			$upper[] = trim( $rule[1] );
		}
	}
}
ck( 'no rule that names the guide\'s body upper-cases text: the WordPress trademark is never shown in an uppercase label', $upper, array() );

ck( 'the ellipsis around a result\'s sentence comes from the page, and a chosen result\'s marks clear when focus leaves its block', array(
	false !== strpos( $js, 'strings.ellipsis' ),
	1 === preg_match( '/\\\\u2026|\x{2026}/u', $plain_js ),
	false !== strpos( $js, "'blur'" ),
	isset( $strings[1]['ellipsis'] ),
), array( true, false, true, true ) );

ck( 'the stylesheet draws a fold, a clip, steps and the highlight, with a ::highlight rule and a reduced-motion rule, and no em or en dash', array(
	false !== strpos( $css, '.wpcpm-guide__fold' ),
	false !== strpos( $css, '.wpcpm-guide-clip' ),
	false !== strpos( $css, '::highlight(' ),
	false !== strpos( $css, 'prefers-reduced-motion' ),
	false !== strpos( $css, 'ol.wp-block-list' ),
	1 === preg_match( '/\x{2014}|\x{2013}/u', $css . $js ),
), array( true, true, true, true, true, false ) );


printf( "\n%s (%d checks)\n", $fail ? sprintf( '%d FAILED', $fail ) : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
