<?php
/**
 * The header's Get involved menu (theme 1.32.12, the product owner's design of 5 November 2026):
 * inc/get-involved.php, the Get involved block (blocks/get-involved) and their rules in style.css, run
 * on the WordPress of bin/stubs/hub-wp.php over the markup of bin/stubs/header-markup.php.
 *
 * One outlined button, "Get involved", takes the place of the visitor's two buttons "Become a sponsor"
 * and "Partner as an institution". It is a disclosure: a `<button>` with `aria-expanded` and
 * `aria-controls`, and a panel of role rows, mentors first, each an icon, a verb-first title and one
 * line. On a phone, and in a signed-in reader's Menu panel below 1200px, the same rows open the panel
 * under a "Get involved" heading. Checked here: the rows for each reader (the left-out rules, the
 * sponsor row only while the Partners page is public, the student row only while the plugin's form is
 * open), the markup, where the theme hooks and inserts it, the lone count and the entry count, and the
 * rules the sheet gives it. What the script does on the page is bin/test-header-js.php's to run.
 *
 * Run from the theme root:  php bin/test-get-involved.php
 *
 * @package WPCredits_Theme
 */

// phpcs:ignoreFile -- a command-line check, not theme code.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/hub-wp.php';
require_once __DIR__ . '/stubs/header-markup.php';

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) { return isset( $GLOBALS['permalinks'][ (int) $post ] ) ? $GLOBALS['permalinks'][ (int) $post ] : false; }
}

if ( ! function_exists( 'wp_is_serving_rest_request' ) ) {
	function wp_is_serving_rest_request() { return ! empty( $GLOBALS['rest_request'] ); }
}

/** The plugin, as far as the rows ask it: each answer a static a check sets. */
class WPCPM_Mentors_Dashboard {
	public static $mentor = false;
	public static function is_mentor() { return self::$mentor; }
}

class WPCPM_Roles {
	const ROLE_MENTOR = 'wpcpm_mentor';
	public static function user_has_role( $user, $role ) { return false; }
}

class WPCPM_Sponsors_Dashboard {
	public static function is_member( $user = null ) { return in_array( 'sponsor', $GLOBALS['members'], true ); }
}

class WPCPM_Institutions_Dashboard {
	public static function is_member( $user = null ) { return in_array( 'institution', $GLOBALS['members'], true ); }
}

class WPCPM_Sponsor_Tiers {
	public static $live = false;
	public static function live() { return self::$live; }
}

class WPCPM_Sponsor_Application {
	public static function page_url() { return 'https://site.example/sponsor-application/'; }
}

class WPCPM_Institution_Application {
	public static function page_url() { return 'https://site.example/institution-application/'; }
}

class WPCPM_Sponsors_Page {
	public static $url = 'https://site.example/partners/';
	public static function page_url() { return self::$url; }
}

class WPCPM_Mentor_Application_Form {
	public static $url = 'https://site.example/become-a-mentor/';
	public static function page_url() { return self::$url; }
}

class WPCPM_Student_Registration_Form {
	public static $open = false;
	public static $url  = 'https://site.example/student-registration/';
	public static function is_open() { return self::$open; }
	public static function page_url() { return self::$url; }
}

$GLOBALS['members']      = array();
$GLOBALS['rest_request'] = false;
$GLOBALS['permalinks']   = array();

require_once WPCREDITS_THEME_DIR . 'inc/icons.php';
require_once WPCREDITS_THEME_DIR . 'inc/template-tags.php';
require_once WPCREDITS_THEME_DIR . 'inc/hub-content.php';
require_once WPCREDITS_THEME_DIR . 'inc/partners.php';
require_once WPCREDITS_THEME_DIR . 'inc/header.php';

if ( is_file( WPCREDITS_THEME_DIR . 'inc/get-involved.php' ) ) {
	require_once WPCREDITS_THEME_DIR . 'inc/get-involved.php';
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
 * A theme function's answer, or null while the theme has no such function, so a check of one not
 * written yet fails rather than stopping the run.
 *
 * @param string $name    The function.
 * @param mixed  ...$args Its arguments.
 * @return mixed
 */
function theme_call( $name, ...$args ) {
	return function_exists( $name ) ? $name( ...$args ) : null;
}

function theme_file( $path ) {
	return is_file( WPCREDITS_THEME_DIR . $path ) ? (string) file_get_contents( WPCREDITS_THEME_DIR . $path ) : '';
}

function dom_of( $html ) {
	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();

	return new DOMXPath( $document );
}

function found( DOMXPath $xpath, $query, $attribute = '' ) {
	$said = array();

	foreach ( $xpath->query( $query ) as $node ) {
		$said[] = '' === $attribute ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : (string) $node->getAttribute( $attribute );
	}

	return $said;
}

function has( $class ) {
	return 'contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")';
}

/**
 * Every style rule of a sheet, comments left out, with the at-rules it sits in (as bin/test-header.php
 * reads one): `at`, `selector` and `declarations`, whitespace run together.
 *
 * @param string $path The sheet, below the theme root.
 * @return array[]
 */
function sheet_rules( $path = 'style.css' ) {
	static $read = array();

	if ( isset( $read[ $path ] ) ) {
		return $read[ $path ];
	}

	$css   = preg_replace( '#/\*.*?\*/#s', '', theme_file( $path ) );
	$rules = array();
	$stack = array();

	preg_match_all( '/\{|\}|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^{}"\']+/s', $css, $tokens );

	$prelude = '';
	$body    = null;

	foreach ( $tokens[0] as $token ) {
		if ( '{' === $token ) {
			$name = trim( preg_replace( '/\s+/', ' ', $prelude ) );

			if ( preg_match( '/^@(?:media|supports|container|layer)\b/', $name ) ) {
				$stack[] = $name;
				$prelude = '';
			} else {
				$body = '';
			}

			continue;
		}

		if ( '}' === $token ) {
			if ( null !== $body ) {
				$declarations = array();

				foreach ( explode( ';', $body ) as $declaration ) {
					$declaration = trim( preg_replace( '/\s+/', ' ', $declaration ) );

					if ( '' !== $declaration ) {
						$declarations[] = $declaration . ';';
					}
				}

				$rules[] = array(
					'at'           => implode( ' > ', $stack ),
					'selector'     => trim( preg_replace( '/\s+/', ' ', $prelude ) ),
					'declarations' => $declarations,
				);

				$body    = null;
				$prelude = '';
			} else {
				array_pop( $stack );
				$prelude = '';
			}

			continue;
		}

		if ( null !== $body ) {
			$body .= $token;
		} else {
			$prelude .= $token;
		}
	}

	return $read[ $path ] = $rules;
}

function rules_of( $selector, $at = '', $path = 'style.css' ) {
	$found = array();

	foreach ( sheet_rules( $path ) as $rule ) {
		if ( $rule['selector'] === $selector && $rule['at'] === $at ) {
			$found[] = $rule['declarations'];
		}
	}

	return $found;
}

/**
 * A reader's rows as titles, lines and addresses, for one state of the request, the globals put back.
 *
 * @param array $state `logged_in`, `live`, `mentor`, `members`, `open`, `form` (the form's address),
 *                     `rest`, each optional.
 * @return array|null Each row as `key title | line | url`; null while the theme has no rows.
 */
function rows_for( array $state = array() ) {
	$was = array( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live, WPCPM_Mentors_Dashboard::$mentor, $GLOBALS['members'], WPCPM_Student_Registration_Form::$open, WPCPM_Student_Registration_Form::$url, $GLOBALS['rest_request'] );

	$GLOBALS['logged_in']                  = $state['logged_in'] ?? false;
	WPCPM_Sponsor_Tiers::$live             = $state['live'] ?? false;
	WPCPM_Mentors_Dashboard::$mentor       = $state['mentor'] ?? false;
	$GLOBALS['members']                    = $state['members'] ?? array();
	WPCPM_Student_Registration_Form::$open = $state['open'] ?? false;
	WPCPM_Student_Registration_Form::$url  = $state['form'] ?? 'https://site.example/student-registration/';
	$GLOBALS['rest_request']               = $state['rest'] ?? false;

	$rows = theme_call( 'wpcredits_involve_rows' );

	list( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live, WPCPM_Mentors_Dashboard::$mentor, $GLOBALS['members'], WPCPM_Student_Registration_Form::$open, WPCPM_Student_Registration_Form::$url, $GLOBALS['rest_request'] ) = $was;

	return null === $rows ? null : array_map(
		static function ( $row ) {
			return $row['key'] . ' ' . $row['title'] . ' | ' . $row['line'] . ' | ' . $row['url'];
		},
		$rows
	);
}

$mentor_row      = 'mentor Mentor students | Guide a student\'s first contribution | https://site.example/become-a-mentor/';
$institution_row = 'institution Partner as an institution | A free program for your students | https://site.example/institution-application/';
$sponsor_row     = 'sponsor Become a sponsor | Fund mentors and student work | https://site.example/sponsor-application/';
$student_row     = 'student Register as a student | Join the WordPress Credits Program | https://site.example/student-registration/';

/** The types hooked at the end of a visitor's navigation with the levels live, as core asks for them. */
function hooked_types_for_pending() {
	$was = array( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live );

	$GLOBALS['logged_in']      = false;
	WPCPM_Sponsor_Tiers::$live = true;

	$types = apply_filters( 'hooked_block_types', array(), 'last_child', 'core/navigation', null );

	list( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live ) = $was;

	return $types;
}

/** The titles of the bar's menu for a visitor, for one state of the request, as rows_for() takes it. */
function involve_titles( array $state = array() ) {
	$was = array( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live );

	$GLOBALS['logged_in']      = false;
	WPCPM_Sponsor_Tiers::$live = $state['live'] ?? false;

	$html = (string) theme_call( 'wpcredits_involve_menu_html' );

	list( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live ) = $was;

	return found( dom_of( $html ), '//*[contains(concat(" ", normalize-space(@class), " "), " wpc-involve__title ")]' );
}

echo "=== The rows, and who is offered each ===\n";

ck( 'a visitor is offered, mentors first, "Mentor students" to /become-a-mentor/ and "Partner as an institution" to the institution application; "Become a sponsor" to the sponsor application only while the Partners page is public (the test the Partners link is hooked by); "Register as a student" only while the plugin\'s form is open; each a verb-first title and one line, in the order mentors, institutions, sponsors, students',
	array(
		rows_for(),
		rows_for( array( 'live' => true ) ),
		rows_for( array( 'open' => true ) ),
		rows_for( array( 'live' => true, 'open' => true ) ),
	),
	array(
		array( $mentor_row, $institution_row ),
		array( $mentor_row, $institution_row, $sponsor_row ),
		array( $mentor_row, $institution_row, $student_row ),
		array( $mentor_row, $institution_row, $sponsor_row, $student_row ),
	) );

WPCPM_Sponsors_Page::$url = '';
$pending_rows             = array( rows_for( array( 'live' => true ) ), rows_for( array( 'logged_in' => true, 'live' => true ) ) );
$pending_types            = hooked_types_for_pending();
$pending_menu             = involve_titles( array( 'live' => true ) );
WPCPM_Sponsors_Page::$url = 'https://site.example/partners/';

ck( 'with the levels live and the Partners page not published (pending, as live\'s is on 5 November 2026), no "Become a sponsor" row, for a visitor or a signed-in reader, and no Partners link before the menu; with the page published both are there, and with the levels off neither',
	array( $pending_rows, $pending_types, $pending_menu, involve_titles( array( 'live' => true ) ), involve_titles() ),
	array(
		array( array( $mentor_row, $institution_row ), array( $mentor_row, $institution_row ) ),
		array( 'wpcredits/get-involved' ),
		array( 'Mentor students', 'Partner as an institution' ),
		array( 'Mentor students', 'Partner as an institution', 'Become a sponsor' ),
		array( 'Mentor students', 'Partner as an institution' ),
	) );

ck( 'a signed-in reader who is already a mentor is not offered "Mentor students", one who acts for an institution not "Partner as an institution", one who acts for a sponsor not "Become a sponsor", as the 1.32.10 buttons left them out; a reader who is all three, with the form closed, is offered nothing',
	array(
		rows_for( array( 'logged_in' => true, 'live' => true ) ),
		rows_for( array( 'logged_in' => true, 'live' => true, 'mentor' => true ) ),
		rows_for( array( 'logged_in' => true, 'live' => true, 'members' => array( 'institution' ) ) ),
		rows_for( array( 'logged_in' => true, 'live' => true, 'members' => array( 'sponsor' ) ) ),
		rows_for( array( 'logged_in' => true, 'live' => true, 'mentor' => true, 'members' => array( 'sponsor', 'institution' ) ) ),
	),
	array(
		array( $mentor_row, $institution_row, $sponsor_row ),
		array( $institution_row, $sponsor_row ),
		array( $mentor_row, $sponsor_row ),
		array( $mentor_row, $institution_row ),
		array(),
	) );

ck( 'the student row needs the form open and its page published: open with no published page (an empty address) offers no row, and so does a closed form with a page',
	array(
		rows_for( array( 'open' => true, 'form' => '' ) ),
		rows_for( array( 'open' => false ) ),
	),
	array(
		array( $mentor_row, $institution_row ),
		array( $mentor_row, $institution_row ),
	) );

/**
 * A visitor's rows in a world of its own: a fresh PHP run with the stub and the theme's files, the plugin's
 * student registration form as given (or none), and nothing else of the plugin.
 *
 * @param string $form The class's body, or null for no class at all.
 * @return array|null The rows' keys; null when the run did not print them.
 */
function form_world( $form ) {
	$code = 'require ' . var_export( __DIR__ . '/stubs/hub-wp.php', true ) . ';';

	if ( null !== $form ) {
		$code .= ' class WPCPM_Student_Registration_Form { ' . $form . ' }';
	}

	foreach ( array( 'inc/icons.php', 'inc/template-tags.php', 'inc/hub-content.php', 'inc/partners.php', 'inc/get-involved.php' ) as $file ) {
		$code .= ' require ' . var_export( WPCREDITS_THEME_DIR . $file, true ) . ';';
	}

	$code .= ' echo json_encode( function_exists( \'wpcredits_involve_rows\' ) ? array_column( wpcredits_involve_rows(), \'key\' ) : null );';

	return json_decode( (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1' ), true );
}

ck( 'every call into the plugin\'s form is guarded, so the theme works without the plugin: no class, a class without `is_open()`, a class without `page_url()`, a closed form, each offers no student row and throws nothing; a class whose form is open with a page offers it; without the plugin the mentor row leads to /become-a-mentor/ all the same',
	array(
		form_world( null ),
		form_world( 'public static function page_url() { return "https://site.example/r/"; }' ),
		form_world( 'public static function is_open() { return true; }' ),
		form_world( 'public static function is_open() { return false; } public static function page_url() { return "https://site.example/r/"; }' ),
		form_world( 'public static function is_open() { return true; } public static function page_url() { return "https://site.example/r/"; }' ),
	),
	array(
		array( 'mentor', 'institution' ),
		array( 'mentor', 'institution' ),
		array( 'mentor', 'institution' ),
		array( 'mentor', 'institution' ),
		array( 'mentor', 'institution', 'student' ),
	) );

$was_url                            = WPCPM_Mentor_Application_Form::$url;
WPCPM_Mentor_Application_Form::$url = '';
$mentor_unpublished                 = theme_call( 'wpcredits_mentor_application_url' );
WPCPM_Mentor_Application_Form::$url = 'https://site.example/mentors/apply/';
$mentor_elsewhere                   = theme_call( 'wpcredits_mentor_application_url' );
WPCPM_Mentor_Application_Form::$url = $was_url;

ck( 'the mentor row leads to the plugin\'s mentor application page where the plugin has one published, and to /become-a-mentor/, its address, otherwise',
	array( $mentor_unpublished, $mentor_elsewhere ),
	array( 'https://site.example/become-a-mentor/', 'https://site.example/mentors/apply/' ) );

echo "\n=== The bar's menu ===\n";

/**
 * The bar's menu, or the panel's list, for one state of the request.
 *
 * @param string $function `wpcredits_involve_menu_html` or `wpcredits_involve_list_html`.
 * @param array  $state    As rows_for() takes it.
 * @return string '' while the theme has no such function.
 */
function involve_html( $function, array $state = array() ) {
	$was = array( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live, WPCPM_Mentors_Dashboard::$mentor, $GLOBALS['members'], WPCPM_Student_Registration_Form::$open );

	$GLOBALS['logged_in']                  = $state['logged_in'] ?? false;
	WPCPM_Sponsor_Tiers::$live             = $state['live'] ?? false;
	WPCPM_Mentors_Dashboard::$mentor       = $state['mentor'] ?? false;
	$GLOBALS['members']                    = $state['members'] ?? array();
	WPCPM_Student_Registration_Form::$open = $state['open'] ?? false;

	$html = (string) theme_call( $function );

	list( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live, WPCPM_Mentors_Dashboard::$mentor, $GLOBALS['members'], WPCPM_Student_Registration_Form::$open ) = $was;

	return $html;
}

$menu_html = involve_html( 'wpcredits_involve_menu_html', array( 'live' => true, 'open' => true ) );
$mx        = dom_of( $menu_html );
$toggle    = $mx->query( '//button' )->item( 0 );
$panel_id  = (string) ( found( $mx, '//*[' . has( 'wpc-involve__panel' ) . ']', 'id' )[0] ?? '' );

// The review of 935f4b5 (M2): without JavaScript the panel opens under the pointer and in focus, so a button
// printed as collapsed would be wrong; it is printed plain, and the block's script gives it `aria-expanded` and
// `aria-controls` when it takes over (bin/test-header-js.php).
ck( 'the bar\'s menu is one navigation item: a plain `<button type="button">` reading "Get involved", printed with no `aria-expanded` and no `aria-controls`, which the script adds when it takes over, a chevron after its words hidden from screen readers; the panel, with an ID of its own, is the button\'s next element, holding the rows as a list',
	array(
		found( $mx, '/html/body/*', 'class' ),
		$toggle ? array( $toggle->getAttribute( 'type' ), $toggle->getAttribute( 'class' ), $toggle->hasAttribute( 'aria-expanded' ), $toggle->hasAttribute( 'aria-controls' ), trim( $toggle->textContent ) ) : null,
		found( $mx, '//button/*[local-name()="svg"]', 'aria-hidden' ),
		found( $mx, '//button/*[local-name()="svg"]', 'focusable' ),
		'' !== $panel_id && 1 === $mx->query( '//*[@id="' . $panel_id . '"]' )->length,
		found( $mx, '//button/following-sibling::*[1]', 'id' ) === array( $panel_id ),
		found( $mx, '//*[@id="' . $panel_id . '"]', 'class' ),
		count( $mx->query( '//*[@id="' . $panel_id . '"]/ul/li' ) ),
	),
	array(
		array( 'wp-block-navigation-item wpc-involve' ),
		array( 'button', 'wpc-involve__toggle', false, false, 'Get involved' ),
		array( 'true' ),
		array( 'false' ),
		true,
		true,
		array( 'wpc-involve__panel' ),
		4,
	) );

ck( 'each row is a link holding the role\'s icon, hidden from screen readers, then its title and its line; the four rows\' titles, lines and addresses in order',
	array(
		found( $mx, '//ul[' . has( 'wpc-involve__rows' ) . ']/li/a', 'class' ),
		found( $mx, '//a[' . has( 'wpc-involve__link' ) . ']/*[local-name()="svg"]', 'aria-hidden' ),
		found( $mx, '//a[' . has( 'wpc-involve__link' ) . ']/*[local-name()="svg"]', 'class' ),
		found( $mx, '//*[' . has( 'wpc-involve__title' ) . ']' ),
		found( $mx, '//*[' . has( 'wpc-involve__line' ) . ']' ),
		found( $mx, '//a[' . has( 'wpc-involve__link' ) . ']', 'href' ),
	),
	array(
		array_fill( 0, 4, 'wpc-involve__link' ),
		array_fill( 0, 4, 'true' ),
		array( 'wpc-icon wpc-involve__icon', 'wpc-icon wpc-involve__icon', 'wpc-icon wpc-involve__icon', 'wpc-icon wpc-involve__icon' ),
		array( 'Mentor students', 'Partner as an institution', 'Become a sponsor', 'Register as a student' ),
		array( 'Guide a student\'s first contribution', 'A free program for your students', 'Fund mentors and student work', 'Join the WordPress Credits Program' ),
		array( 'https://site.example/become-a-mentor/', 'https://site.example/institution-application/', 'https://site.example/sponsor-application/', 'https://site.example/student-registration/' ),
	) );

ck( 'with every row left out there is no Get involved button, and no list in the panel',
	array(
		involve_html( 'wpcredits_involve_menu_html', array( 'logged_in' => true, 'mentor' => true, 'members' => array( 'sponsor', 'institution' ), 'live' => true ) ),
		involve_html( 'wpcredits_involve_list_html', array( 'logged_in' => true, 'mentor' => true, 'members' => array( 'sponsor', 'institution' ), 'live' => true ) ),
		'' !== involve_html( 'wpcredits_involve_menu_html' ),
	),
	array( '', '', true ) );

$GLOBALS['request']    = array( 'singular' => 'page', 'id' => 77, 'templates' => array() );
$GLOBALS['permalinks'] = array( 77 => 'https://site.example/institution-application' );
$on_form_page          = dom_of( involve_html( 'wpcredits_involve_menu_html' ) );
$GLOBALS['request']    = array( 'singular' => '', 'id' => 0, 'templates' => array() );
$GLOBALS['permalinks'] = array();

ck( 'on the institution application\'s own page its row is marked as the page being viewed, with or without the trailing slash, and no other row is',
	array( found( $on_form_page, '//a[@aria-current]', 'href' ), found( $on_form_page, '//a[@aria-current]', 'aria-current' ) ),
	array( array( 'https://site.example/institution-application/' ), array( 'page' ) ) );

echo "\n=== The panel's list, for a phone and for the signed-in Menu panel ===\n";

$list_html = involve_html( 'wpcredits_involve_list_html', array( 'live' => true ) );
$lx        = dom_of( $list_html );
$title_id  = found( $lx, '//*[' . has( 'wpc-involve-list__title' ) . ']', 'id' );

ck( 'the list is a "Get involved" heading, an `h2` a screen reader finds among the page\'s headings (the review of 935f4b5, M3), over the same rows, the list named by the heading: no button and no panel to open, so it reads as rows of the panel it sits at the top of; the heading looks as before, the theme\'s h2 size, weight and spacing taken off',
	array(
		found( $lx, '/html/body/*', 'class' ),
		array_map( static function ( $node ) { return $node->nodeName . ' ' . trim( $node->textContent ); }, iterator_to_array( $lx->query( '//*[' . has( 'wpc-involve-list__title' ) . ']' ) ) ),
		count( $title_id ) === 1 && '' !== $title_id[0] && found( $lx, '//ul', 'aria-labelledby' ) === $title_id,
		found( $lx, '//*[' . has( 'wpc-involve__title' ) . ']' ),
		$lx->query( '//button' )->length,
		rules_of( '.wpc-header .wpc-involve-list__title' ),
	),
	array(
		array( 'wpc-involve-list' ),
		array( 'h2 Get involved' ),
		true,
		array( 'Mentor students', 'Partner as an institution', 'Become a sponsor' ),
		0,
		array( array( 'color: var( --wpc-ink-60 );', 'font-size: 13px;', 'font-weight: 600;', 'letter-spacing: normal;', 'line-height: 18px;', 'margin: 0;', 'padding: 12px 0 4px;' ) ),
	) );

echo "\n=== Where the theme puts it ===\n";

/** The types hooked at the end of a navigation, for one state of the request. */
function hooked_types_for( array $state = array() ) {
	$was = array( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live, $GLOBALS['rest_request'], $GLOBALS['is_admin'] ?? false );

	$GLOBALS['logged_in']      = $state['logged_in'] ?? false;
	WPCPM_Sponsor_Tiers::$live = $state['live'] ?? false;
	$GLOBALS['rest_request']   = $state['rest'] ?? false;
	$GLOBALS['is_admin']       = $state['admin'] ?? false;

	$types = apply_filters( 'hooked_block_types', array(), $state['position'] ?? 'last_child', $state['anchor'] ?? 'core/navigation', null );

	list( $GLOBALS['logged_in'], WPCPM_Sponsor_Tiers::$live, $GLOBALS['rest_request'], $GLOBALS['is_admin'] ) = $was;

	return $types;
}

ck( 'a visitor\'s navigation ends with the Get involved block, after the Partners link while the Partners page is public; it replaces the two buttons, which are hooked no more; a signed-in reader is hooked the Partners link alone, on the front end and over the REST API, so the Site Editor is never offered the menu; nothing is hooked anywhere else',
	array(
		hooked_types_for(),
		hooked_types_for( array( 'live' => true ) ),
		hooked_types_for( array( 'live' => true, 'rest' => true ) ),
		hooked_types_for( array( 'logged_in' => true, 'live' => true ) ),
		hooked_types_for( array( 'logged_in' => true, 'live' => true, 'rest' => true ) ),
		hooked_types_for( array( 'logged_in' => true ) ),
		hooked_types_for( array( 'position' => 'first_child' ) ),
		hooked_types_for( array( 'anchor' => 'core/group' ) ),
	),
	array(
		array( 'wpcredits/get-involved' ),
		array( 'wpcredits-theme/partners-link', 'wpcredits/get-involved' ),
		array( 'wpcredits-theme/partners-link', 'wpcredits/get-involved' ),
		array( 'wpcredits-theme/partners-link' ),
		array( 'wpcredits-theme/partners-link' ),
		array(),
		array(),
		array(),
	) );

$hooks_of = static function ( $hook ) {
	return array_map(
		static function ( $hooked ) {
			return array( is_string( $hooked[0] ) ? $hooked[0] : 'closure', $hooked[1], $hooked[2] );
		},
		$GLOBALS['hooks'][ $hook ] ?? array()
	);
};

ck( 'the Get involved block is hooked after the Partners link (priority 20 to its 10), and its list is put into the navigation after the lone count (priority 11 to its 10); the two button filters of 1.32.7 are gone',
	array(
		$hooks_of( 'hooked_block_types' ),
		$hooks_of( 'render_block_core/navigation' ),
		isset( $GLOBALS['hooks']['hooked_block_core/navigation-link'] ),
		isset( $GLOBALS['hooks']['hooked_block_wpcredits-theme/sponsor-link'] ),
		defined( 'WPCREDITS_SPONSOR_LINK_HOOK' ),
	),
	array(
		array( array( 'wpcredits_partners_menu_item', 10, 3 ), array( 'wpcredits_involve_hook', 20, 3 ) ),
		array( array( 'wpcredits_lone_nav_item', 10, 1 ), array( 'wpcredits_involve_navigation', 11, 1 ) ),
		false,
		false,
		false,
	) );

$GLOBALS['logged_in'] = false;
$partners_link        = array( 'Partners', 'https://site.example/partners/' );
$involve_item         = array( 'html' => involve_html( 'wpcredits_involve_menu_html' ) );
$visitor_nav          = wpcredits_test_navigation( array( $partners_link, $involve_item ) );
$filtered_nav         = apply_filters( 'render_block_core/navigation', $visitor_nav );
$nx                   = dom_of( $filtered_nav );
$plain_nav            = wpcredits_test_navigation( array( $partners_link ) );

ck( 'a visitor\'s navigation that holds the menu gets the list too, in the navigation\'s content after its list of items (theme 1.32.20: the open menu reads as the bar does, the site\'s links first), so it is in the phone\'s panel; the menu stays the list\'s last item; a navigation without the menu is left as the lone count leaves it',
	array(
		found( $nx, '//*[' . has( 'wp-block-navigation__responsive-container-content' ) . ']/*', 'class' ),
		found( $nx, '//ul[' . has( 'wp-block-navigation__container' ) . ']/li', 'class' ),
		apply_filters( 'render_block_core/navigation', $plain_nav ) === wpcredits_lone_nav_item( $plain_nav ),
	),
	array(
		array( 'wp-block-navigation__container is-responsive items-justified-center wpc-nav wp-block-navigation', 'wpc-involve-list' ),
		array( 'wp-block-navigation-item wp-block-navigation-link', 'wp-block-navigation-item wpc-involve' ),
		true,
	) );

$lone = static function ( array $items ) {
	$nav = wpcredits_lone_nav_item( wpcredits_test_navigation( $items ) );

	return false !== strpos( $nav, 'wpc-nav--lone' );
};

ck( 'a navigation that holds the menu is never a lone item, so on a phone it is the menu panel, the rows at its top: the menu alone (live\'s visitor today, the Partners page not public), and with the Partners link; Partners alone is still lone',
	array( $lone( array( $involve_item ) ), $lone( array( $partners_link, $involve_item ) ), $lone( array( $partners_link ) ) ),
	array( false, false, true ) );

$signed_in = static function ( array $reader, array $state = array() ) {
	$was = array( WPCPM_Sponsor_Tiers::$live, WPCPM_Mentors_Dashboard::$mentor, $GLOBALS['members'] );

	WPCPM_Sponsor_Tiers::$live       = $state['live'] ?? false;
	WPCPM_Mentors_Dashboard::$mentor = $state['mentor'] ?? false;
	$GLOBALS['members']              = $state['members'] ?? array();
	$GLOBALS['logged_in']            = true;

	$html = wpcredits_header_menu( wpcredits_test_header( $reader ), array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'wpc-header' ) ) );

	$GLOBALS['logged_in'] = false;

	list( WPCPM_Sponsor_Tiers::$live, WPCPM_Mentors_Dashboard::$mentor, $GLOBALS['members'] ) = $was;

	return dom_of( $html );
};

$full      = $signed_in( array() );
$alone     = $signed_in( array( 'nav' => array(), 'help' => false, 'student' => false, 'mentor' => false ) );
$offered   = $signed_in( array( 'nav' => array(), 'help' => false, 'student' => false, 'mentor' => false ), array( 'mentor' => true, 'members' => array( 'sponsor', 'institution' ) ) );
$first_of  = static function ( DOMXPath $page ) {
	return found( $page, '//*[@id="wpc-header-menu"]/*[1]', 'class' );
};

ck( 'a signed-in reader\'s Menu panel opens with the list, the first thing in it, before the navigation, and holds no button: the rows the reader is offered under "Get involved"; Log out alone and the rows make a menu, as the 1.32.9 buttons did; a reader offered no row, with Log out alone, gets no Menu button and no list',
	array(
		$first_of( $full ),
		found( $full, '//*[@id="wpc-header-menu"]//*[' . has( 'wpc-involve__title' ) . ']' ),
		$full->query( '//*[' . has( 'wpc-involve__toggle' ) . ']' )->length,
		$alone->query( '//button[' . has( 'wpc-menu-toggle' ) . ']' )->length,
		found( $alone, '//*[@id="wpc-header-menu"]//*[' . has( 'wpc-involve__title' ) . ']' ),
		$offered->query( '//button[' . has( 'wpc-menu-toggle' ) . ']' )->length,
		$offered->query( '//*[' . has( 'wpc-involve-list' ) . ']' )->length,
	),
	array(
		array( 'wpc-involve-list' ),
		array( 'Mentor students', 'Partner as an institution' ),
		0,
		1,
		array( 'Mentor students', 'Partner as an institution' ),
		0,
		0,
	) );

echo "\n=== The block ===\n";

$block_json = json_decode( theme_file( 'blocks/get-involved/block.json' ), true );
$view_asset = is_file( WPCREDITS_THEME_DIR . 'blocks/get-involved/view.asset.php' ) ? include WPCREDITS_THEME_DIR . 'blocks/get-involved/view.asset.php' : null;

ck( 'the Get involved block is the theme\'s, registered in functions.php: a dynamic block that is never offered in the inserter, whose view script, deferred and with no dependency, opens and closes the panel, and whose render prints the bar\'s menu',
	array(
		is_array( $block_json ) ? array( $block_json['name'], $block_json['supports']['inserter'] ?? null, $block_json['viewScript'] ?? null, $block_json['render'] ?? null ) : null,
		is_array( $view_asset ) ? $view_asset['dependencies'] : null,
		false !== strpos( theme_file( 'functions.php' ), "register_block_type( get_theme_file_path( 'blocks/get-involved' ) );" ),
		false !== strpos( theme_file( 'functions.php' ), "require_once get_theme_file_path( 'inc/get-involved.php' );" ),
		false !== strpos( theme_file( 'blocks/get-involved/render.php' ), 'wpcredits_involve_menu_html()' ),
		is_file( WPCREDITS_THEME_DIR . 'blocks/get-involved/view.js' ),
	),
	array(
		array( 'wpcredits/get-involved', false, 'file:./view.js', 'file:./render.php' ),
		array(),
		true,
		true,
		true,
		true,
	) );

echo "\n=== The sheet ===\n";

$menu_at = '@media screen and ( max-width: 1199px )';

ck( 'the panel is placed against the item under the button, so opening it shifts nothing, and is shown only while the button says it is expanded; without JavaScript it opens under the pointer and while the focus is in it, so its links are reached; it is the dropdowns\' panel, white on the bar\'s rule with 8px corners and their own shadow, and no other',
	array(
		rules_of( '.wpc-header .wpc-involve__panel' ),
		rules_of( '.wpc-header .wpc-involve__toggle[aria-expanded="true"] + .wpc-involve__panel' ),
		rules_of( ':root:not( .wpc-js ) .wpc-header .wpc-involve:hover > .wpc-involve__panel, :root:not( .wpc-js ) .wpc-header .wpc-involve:focus-within > .wpc-involve__panel' ),
		rules_of( '.wpc-header .wpc-nav.wp-block-navigation .wp-block-navigation__submenu-container' )[0][3] ?? null,
	),
	array(
		array( array( 'background: var( --wpc-surface );', 'border: 1px solid var( --wpc-chrome-rule );', 'border-radius: 8px;', 'box-shadow: 0 8px 24px rgba( 0, 0, 0, .12 );', 'display: none;', 'padding: 6px;', 'position: absolute;', 'right: 0;', 'top: calc( 100% + 8px );', 'width: 320px;', 'z-index: 60;' ) ),
		array( array( 'display: block;' ) ),
		array( array( 'display: block;' ) ),
		'box-shadow: 0 8px 24px rgba( 0, 0, 0, .12 );',
	) );

ck( 'the button is outlined in the brand blue as the outline buttons were, the one filled button staying Log in, and tinted while its panel is open or under the pointer; its chevron turns over when it is open',
	array(
		rules_of( '.wpc-header .wpc-involve__toggle' ),
		rules_of( '.wpc-header .wpc-involve__toggle:hover, .wpc-header .wpc-involve__toggle:focus-visible, .wpc-header .wpc-involve__toggle[aria-expanded="true"]' ),
		rules_of( '.wpc-header .wpc-involve__toggle[aria-expanded="true"] .wpc-involve__chevron' ),
	),
	array(
		array( array( 'align-items: center;', 'background: none;', 'border: 1px solid var( --wpc-brand );', 'border-radius: var( --wpc-chrome-radius );', 'color: var( --wpc-brand );', 'cursor: pointer;', 'display: flex;', 'font: inherit;', 'font-size: 15px;', 'font-weight: 600;', 'gap: 4px;', 'padding: 7px var( --wpc-chrome-item-pad );', 'white-space: nowrap;' ) ),
		array( array( 'background: var( --wpc-brand-tint );', 'color: var( --wpc-brand-dark );' ) ),
		array( array( 'transform: rotate( 180deg );' ) ),
	) );

ck( 'the list shows only at the top of a panel: hidden in the bar, at every width and without JavaScript; shown in the phone\'s open menu, the overlay\'s whole width and read from the left, where the bar\'s menu is hidden, and in a signed-in reader\'s Menu panel below 1200px',
	array(
		rules_of( '.wpc-header .wpc-involve-list' ),
		rules_of( '.wpc-header .wpc-nav .wp-block-navigation__responsive-container.is-menu-open .wpc-involve-list', '@media ( max-width: 960px )' ),
		rules_of( '.wpc-header .wpc-nav .wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item.wpc-involve', '@media ( max-width: 960px )' ),
		rules_of( '.wpc-js .wpc-header--menu .wpc-involve-list', $menu_at ),
	),
	array(
		array( array( 'display: none;' ) ),
		array( array( 'display: block;', 'text-align: left;', 'width: 100%;' ) ),
		array( array( 'display: none;' ) ),
		array( array( 'display: block;' ) ),
	) );

$panel_only = array_values( array_filter( sheet_rules(), static function ( $rule ) { return false !== strpos( $rule['selector'], 'wpc-nav__panel-only' ); } ) );

ck( 'nothing in the sheet names `wpc-nav__panel-only` any more, which only the signed-in reader\'s two buttons wore; nothing in the theme prints it',
	array( count( $panel_only ), false !== strpos( theme_file( 'inc/partners.php' ), "'wpc-nav__panel-only" ), false !== strpos( theme_file( 'inc/header.php' ), "'wpc-nav__panel-only'" ) ),
	array( 0, false, false ) );

$open_at    = '@media ( max-width: 960px )';
$open_list  = '.wpc-header .wpc-nav .wp-block-navigation__responsive-container.is-menu-open .wpc-involve-list .wpc-involve__link';
$link_hover = '.wpc-header .wpc-nav .wp-block-navigation-item__content:hover, .wpc-header .wpc-nav .wp-block-navigation-item__content:focus, .wpc-header .wpc-nav .wp-block-navigation-submenu__toggle:hover, .wpc-header .wpc-nav .wp-block-navigation-submenu__toggle[aria-expanded="true"]';
$tinted     = array_values( array_filter( sheet_rules(), static function ( $rule ) use ( $menu_at ) { return $menu_at === $rule['at'] && false !== strpos( $rule['selector'], 'wpc-involve' ) && preg_grep( '/^background/', $rule['declarations'] ); } ) );

ck( 'in a visitor\'s open phone menu every row answers the pointer alike: a Get involved row, the whole row, takes the tint the menu\'s links take under the pointer and in focus (`--wpc-chrome-hover`), its title staying ink as theirs do; in the signed-in Menu panel, where every row answers with the brand blue and no tint, the list\'s rows keep doing so; the wide bar\'s panel keeps its own hover',
	array(
		rules_of( $open_list . ':hover, ' . $open_list . ':focus', $open_at ),
		rules_of( $open_list . ':hover .wpc-involve__title, ' . $open_list . ':focus .wpc-involve__title', $open_at ),
		rules_of( $link_hover ),
		rules_of( '.wpc-header .wpc-involve-list .wpc-involve__link:hover, .wpc-header .wpc-involve-list .wpc-involve__link:focus' ),
		count( $tinted ),
		rules_of( '.wpc-header .wpc-involve__link:hover, .wpc-header .wpc-involve__link:focus' ),
	),
	array(
		array( array( 'background: var( --wpc-chrome-hover );' ) ),
		array( array( 'color: var( --wpc-ink );' ) ),
		array( array( 'background: var( --wpc-chrome-hover );', 'color: var( --wpc-ink );' ) ),
		array( array( 'background: none;' ) ),
		0,
		array( array( 'background: var( --wpc-chrome-hover );', 'color: var( --wpc-ink );' ) ),
	) );

echo "\n=== The words ===\n";

$sources = theme_file( 'inc/get-involved.php' ) . theme_file( 'blocks/get-involved/render.php' ) . theme_file( 'blocks/get-involved/view.js' );

ck( 'the menu\'s words are the design\'s, in sentence case, without an exclamation mark, an em dash or an en dash, and never "Education Hub"',
	array(
		preg_match( "/\xE2\x80\x94|\xE2\x80\x93/", $sources ),
		false !== strpos( $sources, 'Education Hub' ),
		preg_match( "/__\\( '[^']*!/", $sources ),
	),
	array( 0, false, 0 ) );

printf( "\n%s (%d checks)\n", $fail ? $fail . ' FAILURE(S)' : 'ALL PASS', $total );

exit( $fail ? 1 : 0 );
