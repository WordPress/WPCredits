<?php
/**
 * The layout harness's page carries what WordPress prints from the theme's theme.json (bin/ui/layout/).
 *
 * Run from the plugin root:  php bin/test-ui-layout.php
 *
 * What each block pins, and why:
 *
 * - **The fixture is there and is WordPress's**: global-styles.css, block-library.css and global-styles.json
 *   sit beside the harness, written by refresh-global-styles.mjs; the global styles hold the presets as
 *   variables on :root, the body's element styles and the theme's button and link looks; the record names a
 *   theme.json's sha256, WordPress's version and the refresh command.
 * - **The refresh only reads the copy**: global-styles.php stops on any query that is not a read and refuses a
 *   persistent object cache, and refresh-global-styles.mjs pipes it to `eval-file -` with the copy's theme left
 *   out, so the copy's own theme.json is never the one printed.
 * - **The page, for a theme folder it is given** (a stand-in theme in this run's temporary directory, with
 *   the committed fixture's sheets and a record made for its theme.json): the fixture's sheets are linked
 *   after the plugin's render sheets and before the theme's style.css, hub.css last; the body carries the
 *   Institution Dashboard page's classes and the cases sit in the template's wrappers.
 * - **A stale or missing fixture stops the page** with the refresh command in its words.
 * - **In Chrome**, a theme button in a drawing computes to theme.json's button look as the fixture prints it
 *   (background, text color, corners, padding, weight, no underline) and a link to its link color. A machine
 *   without node, Playwright or Chrome gets one `skip` line for that part.
 *
 * Nothing here touches a site, Docker or the network.
 *
 * @package WPCreditsProgramManager
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/stubs/temp-dir.php';

$root   = dirname( __DIR__ );
$layout = $root . '/bin/ui/layout';

$fail = 0;
function ck( $l, $a, $e = true ) {
	global $fail;
	$ok = $a === $e;
	if ( ! $ok ) {
		$fail++;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $l . "\n";
	if ( ! $ok ) {
		echo '       exp: ' . var_export( $e, true ) . '  got: ' . var_export( $a, true ) . "\n";
	}
}

function read_layout( $path ) {
	return is_file( $path ) ? (string) file_get_contents( $path ) : '';
}

/** A #rrggbb color as Chrome computes it, rgb(r, g, b). */
function rgb_of( $hex ) {
	$hex = ltrim( trim( (string) $hex ), '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return 6 === strlen( $hex ) ? sprintf( 'rgb(%d, %d, %d)', hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ) : '';
}

// ------------------------------------------------------------------ the fixture
echo "--- the fixture\n";
$gs     = read_layout( "$layout/global-styles.css" );
$lib    = read_layout( "$layout/block-library.css" );
$source = json_decode( read_layout( "$layout/global-styles.json" ), true );
ck( 'global-styles.css, block-library.css and global-styles.json are there', array( '' !== $gs, '' !== $lib, is_array( $source ) ), array( true, true, true ) );
$source = is_array( $source ) ? $source : array();
ck( 'the record names a theme.json sha256', 1 === preg_match( '/^[0-9a-f]{64}$/', (string) ( $source['theme_json_sha256'] ?? '' ) ) );
ck( 'and the WordPress version it was printed by', 1 === preg_match( '/^\d+\.\d+/', (string) ( $source['wordpress'] ?? '' ) ) );
ck( 'and the command that refreshes it', (string) ( $source['made_by'] ?? '' ), 'WPCPM_THEME_DIR=<theme folder> node bin/ui/layout/refresh-global-styles.mjs' );
ck( 'and the core blocks the page renders: the template parts, the main group, the post content', (array) ( $source['blocks'] ?? array() ), array( 'core/template-part', 'core/group', 'core/post-content' ) );
$var = static function ( $name ) use ( $gs ) {
	return preg_match( '/--wp--preset--' . preg_quote( $name, '/' ) . ':\s*([^;]+);/', $gs, $m ) ? trim( $m[1] ) : '';
};
$preset_color = static function ( $value ) use ( $var ) {
	return preg_match( '/^var\(--wp--preset--color--([a-z0-9-]+)\)$/', trim( (string) $value ), $m ) ? $var( 'color--' . $m[1] ) : (string) $value;
};
ck( 'the global styles put the presets on :root as variables', 1 === preg_match( '/:root\{--wp--preset--/', $gs ) && '' !== $var( 'color--brand' ) && '' !== $var( 'font-family--system' ) );
ck( 'the body\'s element styles are there: its font, color and background from presets', 1 === preg_match( '/body\{[^}]*background-color: var\(--wp--preset--color--[a-z-]+\);[^}]*color: var\(--wp--preset--color--[a-z-]+\);[^}]*font-family: var\(--wp--preset--font-family--[a-z-]+\);/', $gs ) );
$button = preg_match( '/:root :where\(\.wp-element-button, \.wp-block-button__link\)\{([^}]*)\}/', $gs, $bm ) ? $bm[1] : '';
$decl   = static function ( $rule, $prop ) {
	return preg_match( '/(?:^|;)' . preg_quote( $prop, '/' ) . ':\s*([^;]+)/', $rule, $m ) ? trim( $m[1] ) : '';
};
ck( 'the theme\'s button look is there, on .wp-element-button and .wp-block-button__link', '' !== $button && '' !== $decl( $button, 'background-color' ) );
$link = preg_match( '/(?:^|\})a:where\(:not\(\.wp-element-button\)\)\{color: ([^;]+);\}/', $gs, $lm ) ? $lm[1] : '';
ck( 'and the link color', '' !== $link );
ck( 'the block library\'s shared sheet is there, as a page loading block assets separately prints it (common.css)', 1 === preg_match( '#/\* wp-block-library: [^*]*/common(\.min)?\.css \*/#', $lib ) );
ck( 'neither sheet is edited by hand: each names its writer', array( 0 === strpos( $gs, '/* Written by bin/ui/layout/refresh-global-styles.mjs' ), 0 === strpos( $lib, '/* Written by bin/ui/layout/refresh-global-styles.mjs' ) ), array( true, true ) );

// ------------------------------------------------------------------ the refresh only reads
echo "--- the refresh only reads the copy\n";
$php     = read_layout( "$layout/global-styles.php" );
$refresh = read_layout( "$layout/refresh-global-styles.mjs" );
ck( 'global-styles.php stops on any query that is not a read', false !== strpos( $php, "preg_match( '/^\\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\\b/i', (string) \$query )" ) && false !== strpos( $php, 'exit( 3 );' ) );
ck( 'and refuses a persistent object cache', false !== strpos( $php, 'if ( wp_using_ext_object_cache() ) {' ) );
// Its code without its comments, which name the functions it does not call.
$php_code = '';
foreach ( token_get_all( $php ) as $tok ) {
	$php_code .= is_array( $tok ) ? ( in_array( $tok[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $tok[1] ) : $tok;
}
ck( 'and its code sets no transient, theme mod or option, writes no post and opens no file for writing', 0 === preg_match( '/\b(set_transient|set_site_transient|set_theme_mod|update_option|add_option|wp_insert_post|wp_update_post|file_put_contents|fopen|gutenberg_add_global_styles_for_blocks|wp_get_custom_css_post|wp_get_custom_css)\s*\(/', $php_code ) );
ck( 'refresh-global-styles.mjs pipes it to eval-file - with the copy\'s theme and every plugin but Gutenberg left out', false !== strpos( $refresh, "['eval-file', '-', '--skip-themes', '--skip-plugins=' + plugins.join(',')]" ) && false !== strpos( $refresh, "n !== 'gutenberg'" ) );
ck( 'and finds the copy\'s WP-CLI through WPCPM_DOCKER, with the home folder\'s default', false !== strpos( $refresh, "process.env.WPCPM_DOCKER || join(homedir(), 'Projects/wpcredits-docker')" ) );
ck( 'and stops on a theme with style variation partials, which it does not cover', false !== strpos( $refresh, "existsSync(join(themeDir, 'styles'))" ) );

// ------------------------------------------------------------------ every kept case names its surface
echo "--- every kept case names its surface\n";
$keep_dir = wpcpm_test_temp_dir() . 'keep/';
mkdir( $keep_dir, 0700, true );
$keep_code = "<?php\nwpcpm_layout_keep( 'plain', '<p>x</p>' );\nwpcpm_layout_keep( 'named', '<p>y</p>', 'administrator' );\n";
$kept      = array();
foreach ( array( 'test-institution-standin.php', 'test-other-standin.php' ) as $keep_file ) {
	file_put_contents( $keep_dir . $keep_file, $keep_code );
	$keep_out = $keep_dir . $keep_file . '.json';
	exec( 'WPCPM_LAYOUT_OUT=' . escapeshellarg( $keep_out ) . ' ' . escapeshellarg( PHP_BINARY ) . ' -d auto_prepend_file=' . escapeshellarg( $layout . '/dump.php' ) . ' ' . escapeshellarg( $keep_dir . $keep_file ) . ' 2>&1' );
	$kept[ $keep_file ] = json_decode( read_layout( $keep_out ), true );
}
ck( 'a case kept with a surface keeps it', $kept['test-other-standin.php']['named'] ?? null, array( 'html' => '<p>y</p>', 'surface' => 'administrator' ) );
ck( 'one kept without, by a suite whose every drawing is the Institution Dashboard\'s, is the Institution Dashboard\'s', $kept['test-institution-standin.php']['plain']['surface'] ?? null, 'institution' );
ck( 'and by any other suite, keeps none', $kept['test-other-standin.php']['plain']['surface'] ?? null, '' );
$suite_line = <<<'PHPLINE'
		if ( preg_match( '/^test-institutions?-[a-z0-9-]+\.php$/', $file ) || in_array( $file, array( 'test-semester-report.php', 'test-sponsor-event-list.php', 'test-parts-file.php' ), true ) ) {
PHPLINE;
ck( 'the suites dump.php names are the Institution Dashboard\'s own, the Semester reports card, its Events view and the agreement\'s file box', false !== strpos( read_layout( $layout . '/dump.php' ), $suite_line ) );
$node_bin = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( '' !== $node_bin ) {
	$legacy = $keep_dir . 'legacy.json';
	file_put_contents( $legacy, json_encode( array( 'old' => '<p>x</p>', 'none' => array( 'html' => '<p>y</p>', 'surface' => '' ) ) ) );
	$run_out = array();
	$run_st  = 0;
	exec( escapeshellarg( $node_bin ) . ' ' . escapeshellarg( $layout . '/run.mjs' ) . ' --in ' . escapeshellarg( $legacy ) . ' 2>&1', $run_out, $run_st );
	ck( 'run.mjs refuses a case that names no surface, before it draws anything, and says how to name one', array( $run_st, false !== strpos( implode( "\n", $run_out ), 'names no surface: keep it with wpcpm_layout_keep( $name, $html, $surface )' ) ), array( 2, true ) );
}

// ------------------------------------------------------------------ the page, in node and Chrome
echo "--- the page for a theme folder it is given\n";
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( '' === $node ) {
	echo "skip the page was not drawn: node is not on the path\n";
} else {
	// A stand-in theme: its own theme.json, the sheets the page links, and the committed fixture's two sheets in a
	// fixture folder whose record names this theme.json; a second fixture folder names another theme.json.
	$tmp   = wpcpm_test_temp_dir();
	$theme = $tmp . 'theme';
	mkdir( $theme . '/assets/css', 0700, true );
	file_put_contents( $theme . '/style.css', "/*\nTheme Name: Stand-in\nVersion: 9.9.9\nText Domain: stand-in-theme\n*/\n" );
	file_put_contents( $theme . '/theme.json', '{"version":3,"settings":{},"styles":{}}' );
	// An inset and a line on the page's root, as the theme once gave the root outside the frame: institution.css takes them off
	// the root inside the frame's main area, which only the real nesting reaches.
	// And the old page's closing line, a hairline and a 32px inset, as the theme still draws the read line on the page
	// (Gate B, B6): institution.css draws a view's read line in the parts' look inside the frame.
	file_put_contents( $theme . '/assets/css/dashboard.css', '.wpcpm-dashboard.wpcpm-institution{border-top:1px solid #000;padding:22px 0}.wpc-institution-page .wpcpm-dashboard__updated{border-top:1px solid #e6e6e6;margin:22px 0 0;padding:14px 32px 18px}' );
	file_put_contents( $theme . '/assets/css/hub.css', '' );
	$sha = hash_file( 'sha256', $theme . '/theme.json' );
	foreach ( array( 'fresh' => $sha, 'stale' => str_repeat( '0', 64 ) ) as $dir => $named ) {
		mkdir( $tmp . $dir, 0700 );
		file_put_contents( $tmp . $dir . '/global-styles.css', $gs );
		file_put_contents( $tmp . $dir . '/block-library.css', $lib );
		file_put_contents( $tmp . $dir . '/global-styles.json', json_encode( array_merge( $source, array( 'theme_json_sha256' => $named, 'theme_version' => '9.9.9' ) ) ) );
	}
	mkdir( $tmp . 'missing', 0700 );

	// Task 28 fix round 2, X1: the part's own markup of a run of inner folds as a list, two closed students, drawn by
	// WPCPM_UI in a PHP of its own (this suite loads no plugin class).
	$folds_php = $tmp . 'folds.php';
	file_put_contents(
		$folds_php,
		"<?php\ndefine( 'ABSPATH', '/' );\nfunction esc_html( \$s ) { return htmlspecialchars( (string) \$s, ENT_QUOTES ); }\nfunction esc_attr( \$s ) { return esc_html( \$s ); }\nfunction __( \$s ) { return \$s; }\nfunction esc_html__( \$s ) { return esc_html( \$s ); }\n"
		. "require '" . $root . "/includes/class-wpcpm-icons.php';\nrequire '" . $root . "/includes/class-wpcpm-form-errors.php';\nrequire '" . $root . "/includes/class-wpcpm-ui.php';\n"
		. "\$f = static function ( \$n, \$id ) { return WPCPM_UI::inner_item() . str_replace( '<details class=', '<details id=\"' . \$id . '\" class=', WPCPM_UI::inner_open( array( 'title' => \$n, 'initials' => 'A' ) ) ) . '<p>x</p>' . WPCPM_UI::inner_close() . WPCPM_UI::inner_item_close(); };\n"
		. "echo WPCPM_UI::inner_list() . \$f( 'Ada Example', 'x1-a' ) . \$f( 'Bo Example', 'x1-b' ) . WPCPM_UI::inner_list_close();\n"
	);
	$folds_markup = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $folds_php ) . ' 2>&1' );

	// Task 28 fix round 2, X10: a field's label, a choice group's legend, a tick and a file box, each with "Required" and
	// without it, as WPCPM_UI prints them, for a short label and for one whose need word wraps at main 358.
	$needs_php = $tmp . 'needs.php';
	$needs_src = <<<'NEEDS'
<?php
define( 'ABSPATH', '/' );
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return esc_html( $s ); }
function esc_attr__( $s ) { return esc_html( $s ); }
function wp_enqueue_script() {}
function wp_style_is() { return true; }
function wp_script_is() { return true; }
function wp_register_script() {}
function wp_register_style() {}
function wp_enqueue_style() {}
require 'ROOT/includes/class-wpcpm-icons.php';
require 'ROOT/includes/class-wpcpm-form-errors.php';
require 'ROOT/includes/class-wpcpm-ui.php';
$out = array();
foreach ( array( 'short' => 'Start date', 'long' => 'The day your students start their placement at' ) as $size => $label ) {
	foreach ( array( 'with' => 'required', 'without' => '' ) as $k => $need ) {
		$out[ $size ][ $k ] = array(
			'field'  => WPCPM_UI::field( array( 'id' => "n1$size$k", 'name' => 'n1', 'label' => $label, 'need' => $need ) ),
			'legend' => WPCPM_UI::choices( array( 'id' => "n2$size$k", 'name' => 'n2', 'legend' => $label, 'type' => 'radio', 'options' => array( 'a' => 'One', 'b' => 'Two' ), 'need' => $need ) ),
			'tick'   => WPCPM_UI::choices( array( 'id' => "n3$size$k", 'name' => 'n3', 'options' => array( '1' => $label ), 'need' => $need ) ),
			'file'   => WPCPM_UI::file( array( 'id' => "n4$size$k", 'name' => 'n4', 'label' => $label, 'need' => $need ) ),
		);
	}
}
echo json_encode( $out );
NEEDS;
	file_put_contents( $needs_php, str_replace( 'ROOT', $root, $needs_src ) );
	$needs_markup = json_decode( (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $needs_php ) . ' 2>&1' ), true );

	$expect = array(
		'needs'      => $needs_markup,
		'folds'      => $folds_markup,
		'background' => rgb_of( $preset_color( $decl( $button, 'background-color' ) ) ),
		'color'      => rgb_of( $preset_color( $decl( $button, 'color' ) ) ),
		'radius'     => $decl( $button, 'border-radius' ),
		'padding'    => array( $decl( $button, 'padding-top' ), $decl( $button, 'padding-right' ), $decl( $button, 'padding-bottom' ), $decl( $button, 'padding-left' ) ),
		'weight'     => $decl( $button, 'font-weight' ),
		'link'       => rgb_of( $preset_color( $link ) ),
	);
	$script = <<<'JS'
import { pathToFileURL } from 'node:url';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { homedir } from 'node:os';
const [plugin, tmp, expectJson] = process.argv.slice(2);
const expect = JSON.parse(expectJson);
const out = (ok, label, got) => console.log((ok ? 'ok   ' : 'FAIL ') + label + (ok ? '' : '\n       got: ' + JSON.stringify(got)));
const mod = await import(pathToFileURL(join(plugin, 'bin/ui/layout/page.mjs')).href);
const theme = join(tmp, 'theme');
const drawing = { name: 'Theme button', html: '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#apply">Apply</a></div><p><a class="h-link" href="#read">Read the record</a></p>' };
let html = '';
try { html = mod.page([drawing], [800], { pluginDir: plugin, themeDir: theme, fixtureDir: join(tmp, 'fresh') }); } catch (e) { html = ''; out(false, 'the page is drawn with a fresh fixture', String(e.message || e)); }
const t = pathToFileURL(theme).href;
const order = ['/report-print.css"', '/block-library.css"', '/global-styles.css"', t + '/style.css"', '/sponsor.css"', t + '/assets/css/dashboard.css"', t + '/assets/css/hub.css"'].map(f => html.indexOf(f));
out(order.every(i => i > 0), 'the page links both sheets of the fixture', order);
out(order.every((v, i) => i === 0 || v > order[i - 1]), 'in the site\'s order: the plugin\'s render sheets, the block library, the global styles, the theme\'s style.css, the dashboard sheets, hub.css last', order);
out(html.includes('<body class="page-template-page-institution-dashboard page wp-singular logged-in wp-embed-responsive wp-theme-stand-in-theme wpc-dashboard-page wpc-institution-page wpc-signed-in">'), 'the body carries the Institution Dashboard page\'s classes, the theme slug from its Text Domain', (html.match(/<body[^>]*>/) || [''])[0]);
out(/<div class="wp-site-blocks"><main id="wp--skip-link--target" class="wp-block-group wpc-main wpc-main--app wpc-main--institution [^"]*"><div class="entry-content wp-block-post-content wpc-app__content [^"]*"><div class="h-case"/.test(html), 'the cases sit in the template\'s wrappers: .wp-site-blocks, the main group, the post content', html.slice(html.indexOf('<body'), html.indexOf('<body') + 400));
let stale = '';
try { mod.page([drawing], [800], { pluginDir: plugin, themeDir: theme, fixtureDir: join(tmp, 'stale') }); } catch (e) { stale = String(e.message || e); }
out(stale.includes('stale') && stale.includes('WPCPM_THEME_DIR=<theme folder> node bin/ui/layout/refresh-global-styles.mjs'), 'a fixture made from another theme.json stops the page, with the refresh command in its words', stale);
let missing = '';
try { mod.page([drawing], [800], { pluginDir: plugin, themeDir: theme, fixtureDir: join(tmp, 'missing') }); } catch (e) { missing = String(e.message || e); }
out(missing.includes('missing') && missing.includes('refresh-global-styles.mjs'), 'and so does a missing fixture', missing);
// The real nesting: the frame, its body, the main area, the page's root, as open() and render() print them.
const fresh = { pluginDir: plugin, themeDir: theme, fixtureDir: join(tmp, 'fresh') };
const nest = (h, frame, root) => h.includes(`<div class="wpcpm-frame ${frame}" data-wpcpm-frame><div class="wpcpm-frame__body"><div class="wpcpm-frame__main" style="box-sizing:content-box;width:800px;grid-column:-2/-1;container:wpcpm-main/inline-size"><div class="${root}">`);
out(nest(html, 'wpcpm-frame--institution wpcpm-ui-on', 'wpcpm-dashboard wpcpm-institution wpcpm-ui-on'), 'a case sits in the page\'s root, inside the main area, inside the frame\'s body, inside the frame, in the site\'s order', html.slice(html.indexOf('<div class="h-case"'), html.indexOf('<div class="h-case"') + 360));
const ownRoot = '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on"><p>Drawn whole</p></div>';
let whole = '';
try { whole = mod.page([{ name: 'whole', html: ownRoot }], [800], fresh); } catch (e) { whole = String(e.message || e); }
out(1 === (whole.match(/class="wpcpm-dashboard /g) || []).length && whole.includes('container:wpcpm-main/inline-size">' + ownRoot), 'a case that is the page\'s root (a suite\'s main_html()) is that root, not wrapped in a second one', whole.slice(whole.indexOf('<div class="h-case"'), whole.indexOf('<div class="h-case"') + 360));
let admin = '';
try { admin = mod.page([{ name: 'card', html: '<p>A card</p>', surface: 'administrator' }], [800], fresh); } catch (e) { admin = String(e.message || e); }
const adminBody = (admin.match(/<body[^>]*>/) || [''])[0];
out(adminBody === '<body class="page-template-page-administrator-dashboard page wp-singular logged-in wp-embed-responsive wp-theme-stand-in-theme wpc-dashboard-page wpc-administrator-page wpc-signed-in">', 'an Administrator Dashboard case carries that page\'s body classes', adminBody);
out(admin.includes('class="wp-block-group wpc-main wpc-main--app wpc-main--administrator ') && nest(admin, 'wpcpm-frame--administrator wpcpm-ui-on', 'wpcpm-dashboard wpcpm-administrator wpcpm-administrator--overview wpcpm-ui-on'), 'and its main group, its frame and its root, on the Overview', admin.slice(admin.indexOf('<body'), admin.indexOf('<body') + 700));
out(!/institution/.test(admin.slice(admin.indexOf('<body'))), 'and not one of the Institution Dashboard\'s classes', (admin.slice(admin.indexOf('<body')).match(/[a-z-]*institution[a-z-]*/g) || []));
const aOrder = ['/administrator.css"', '/cohort-timeline.css"', '/dashboard-frame.css"', '/global-styles.css"', t + '/style.css"', '/institution.css"', t + '/assets/css/dashboard.css"'].map(f => admin.indexOf(f));
out(aOrder.every((v, i) => v > 0 && (i === 0 || v > aOrder[i - 1])) && !admin.includes('/report-print.css"'), 'and that page\'s sheets in its order: administrator.css and cohort-timeline.css before the frame\'s, institution.css among the theme\'s, no report-print.css', aOrder);
let watch = '';
try { watch = mod.page([{ name: 'w', html: '<p>x</p>', surface: 'administrator/watch' }], [800], fresh); } catch (e) { watch = String(e.message || e); }
out(watch.includes('<div class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--watch wpcpm-ui-on">'), 'a surface can name the view its root names: administrator/watch', watch.slice(0, 200));
let sponsor = '';
try { sponsor = mod.page([{ name: 's', html: '<p>x</p>', surface: 'sponsor' }], [800], fresh); } catch (e) { sponsor = String(e.message || e); }
out(nest(sponsor, 'wpcpm-frame--sponsor', 'wpcpm-dashboard wpcpm-sponsor wpcpm-sponsor--overview') && sponsor.includes('wpc-sponsor-page'), 'a Sponsor Dashboard case is drawn without the switch\'s class, which is off there (WPCPM_UI::LIVE)', sponsor.slice(sponsor.indexOf('<div class="h-case"'), sponsor.indexOf('<div class="h-case"') + 300));
const refused = (cases, label, words) => { let e = ''; try { mod.page(cases, [800], fresh); } catch (x) { e = String(x.message || x); } out(e.includes(words), label, e); };
refused([{ name: 'r', html: '<div class="wpcpm-dashboard wpcpm-institution"><p>x</p></div>', surface: 'administrator' }], 'an Institution Dashboard root drawn on the Administrator Dashboard stops the page', 'name its surface');
refused([{ name: 'a', html: '<p>x</p>', surface: 'administrator' }, { name: 'b', html: '<p>x</p>' }], 'and so do cases of two surfaces on one page', 'one page draws one surface');
refused([{ name: 'rc', html: '<p>x</p>', surface: 'student' }], 'and a Report Card, which has no frame', 'not drawn in the frame');
// Chrome: the theme button computes to theme.json's look.
let chromium = null;
try { ({ chromium } = await import(pathToFileURL(process.env.WPCPM_PLAYWRIGHT || join(homedir(), 'Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs')).href)); } catch (e) { chromium = null; }
let browser = null;
if (chromium && html) { try { browser = await chromium.launch({ channel: 'chrome', headless: true, args: ['--allow-file-access-from-files'] }); } catch (e) { browser = null; } }
if (!browser) {
	console.log('skip the theme button was not drawn: Playwright or Chrome is not there');
} else {
	try {
		const file = join(tmp, 'page.html');
		writeFileSync(file, html);
		const tab = await browser.newPage({ viewport: { width: 928, height: 600 } });
		await tab.goto(pathToFileURL(file).href, { waitUntil: 'load' });
		const got = await tab.evaluate(() => {
			const b = getComputedStyle(document.querySelector('.wp-element-button'));
			const a = getComputedStyle(document.querySelector('.h-link'));
			return { brand: getComputedStyle(document.documentElement).getPropertyValue('--wp--preset--color--brand').trim(), background: b.backgroundColor, color: b.color, radius: b.borderTopLeftRadius, padding: [b.paddingTop, b.paddingRight, b.paddingBottom, b.paddingLeft], weight: b.fontWeight, decoration: b.textDecorationLine, link: a.color };
		});
		out('' !== got.brand, 'the page carries the presets: --wp--preset--color--brand is set on the root', got.brand);
		out(got.background === expect.background && got.color === expect.color, 'a theme button computes to theme.json\'s button background and text color', [got.background, got.color, expect.background, expect.color]);
		out(got.radius === expect.radius && JSON.stringify(got.padding) === JSON.stringify(expect.padding), 'and its corners and padding', [got.radius, got.padding, expect.radius, expect.padding]);
		out(got.weight === expect.weight && 'none' === got.decoration, 'and its weight, with no underline', [got.weight, got.decoration, expect.weight]);
		out(got.link === expect.link, 'a link computes to theme.json\'s link color', [got.link, expect.link]);
		const tree = await tab.evaluate(() => {
			const root = document.querySelector('.h-case .wpcpm-dashboard');
			const main = root.parentElement;
			return { chain: [main, main.parentElement, main.parentElement.parentElement].map(e => e.className.split(' ')[0]), container: getComputedStyle(main).containerName, rootPadding: getComputedStyle(root).paddingTop, rootLine: getComputedStyle(root).borderTopWidth };
		});
		out(JSON.stringify(tree.chain) === JSON.stringify(['wpcpm-frame__main', 'wpcpm-frame__body', 'wpcpm-frame']) && 'wpcpm-main' === tree.container, 'in Chrome the root\'s parents are the main area, the frame\'s body and the frame, and the main area is the container wpcpm-main', tree);
		out('0px' === tree.rootPadding && '0px' === tree.rootLine, 'and institution.css\'s rule for the root in the frame\'s main area reaches it: the stand-in theme\'s inset and line are taken off', tree);
		// The rhythm of an in-card heading (parts design 2.11; Gate B, B2): 24px under the block above it, an inner fold
		// among them, and 8px over the block it heads, a document or an item among them; the first in a card takes no
		// room above. The Overview's Waiting for you heads its quiet line 8px over it. At three main-area widths, each in
		// the window a reader has.
		const fold = '<section class="wpcpm-ui-fold-card"><details class="wpcpm-ui-fold" open><summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title">Card</h3></summary><div class="wpcpm-ui-fold__body">';
		const inner = id => `<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="${id}"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Correct these</span></span></summary><div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro">x</p></div></details>`;
		const table = '<table class="wpcpm-ui-table wpcpm-ui-table--keyvalue" role="table"><tbody role="rowgroup"><tr role="row"><th scope="row" role="rowheader" class="wpcpm-ui-cell--text">Name</th><td class="wpcpm-ui-cell--text" role="cell">Example</td></tr></tbody></table>';
		const rhythm = [
			{ name: 'after a fold', html: fold + '<h4 class="wpcpm-ui-h" id="h-first">Contact and agreement</h4>' + table + inner('f1') + '<h4 class="wpcpm-ui-h" id="h-after-fold">Name and address</h4>' + table + '</div></details></section>' },
			{ name: 'over a document', html: fold + '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">x</p>' + inner('f2') + '<h3 class="wpcpm-ui-h" id="h-doc">The report as it prints</h3><div class="wpcpm-ui-doc"><article class="wpcpm-report-doc"><h2 class="wpcpm-report-doc__title">Report</h2></article></div></div></details></section>' },
			{ name: 'over an item', html: fold + '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted">x</p><h3 class="wpcpm-ui-h" id="h-item">The quotes your students released</h3><article class="wpcpm-ui-item"><input type="hidden" name="q" value="1"><blockquote class="wpcpm-ui-note">A quote.</blockquote></article><article class="wpcpm-ui-item"><input type="hidden" name="r" value="1"><blockquote class="wpcpm-ui-note">Another.</blockquote></article></div></details></section>' },
			{ name: 'waiting', html: '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on"><section class="wpcpm-institution__waiting"><h2 class="wpcpm-ui-h" id="h-waiting">Waiting for you</h2><p class="wpcpm-overview__quiet">Nothing waits for you.</p></section></div>' },
		];
		const gaps = {};
		for (const w of [960, 800, 358]) {
			const f = join(tmp, `rhythm-${w}.html`);
			writeFileSync(f, mod.page(rhythm, [w], fresh));
			const r = await browser.newPage({ viewport: { width: w < 800 ? w + 32 : w + 300, height: 900 } });
			await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
			gaps[w] = await r.evaluate(() => {
				const box = e => e.getBoundingClientRect();
				const inside = e => box(e).top + e.clientTop + parseFloat(getComputedStyle(e).paddingTop);
				const above = id => { const h = document.getElementById(id); return Math.round(box(h).top - (h.previousElementSibling ? box(h.previousElementSibling).bottom : inside(h.parentElement))); };
				const below = id => { const h = document.getElementById(id); let n = h.nextElementSibling; let t = box(n).top; const v = n.querySelector('blockquote'); if (v) { t = box(v).top; } return Math.round(t - box(h).bottom); };
				const item2 = document.querySelectorAll('.wpcpm-ui-item')[1];
				return { first: above('h-first'), afterFold: [above('h-after-fold'), below('h-after-fold')], doc: [above('h-doc'), below('h-doc')], item: below('h-item'), nextItem: Math.round(box(item2.querySelector('blockquote')).top - box(item2).top - item2.clientTop), waiting: below('h-waiting') };
			});
			await r.close();
		}
		// A view's head and what follows it (Gate B, B4): every view's first block stands off the head as the Overview's
		// blocks do and as the frame's main area spaces its rows, 28px, and 24px in a window of 782px or less.
		const head = '<header class="wpcpm-head"><div class="wpcpm-head__copy"><p class="wpcpm-head__eyebrow">Institution Dashboard</p><h2 class="wpcpm-head__title">Students</h2><p class="wpcpm-head__lede">Your students on the program.</p></div></header>';
		const views = [
			{ name: 'a view', html: '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' + head + '<div class="wpcpm-institution__card" data-card="roster"><section class="wpcpm-ui-block" id="v-first"><p class="wpcpm-ui-intro">Cohort</p></section></div></div>' },
			{ name: 'a view of cards', html: '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' + head + '<div class="wpcpm-institution__card" data-card="details"><section class="wpcpm-ui-fold-card" id="v-card"><details class="wpcpm-ui-fold" open><summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title">Card</h3></summary><div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro">x</p></div></details></section></div></div>' },
			{ name: 'the Overview', html: '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' + head + '<section class="wpcpm-institution__waiting" id="v-overview"><h2 class="wpcpm-ui-h">Waiting for you</h2><p class="wpcpm-overview__quiet">Nothing waits for you.</p></section></div>' },
		];
		const leads = {};
		for (const w of [960, 800, 358]) {
			const f = join(tmp, `views-${w}.html`);
			writeFileSync(f, mod.page(views, [w], fresh));
			const r = await browser.newPage({ viewport: { width: w < 800 ? w + 32 : w + 300, height: 900 } });
			await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
			leads[w] = await r.evaluate(() => ['v-first', 'v-card', 'v-overview'].map(id => { const e = document.getElementById(id); const h = e.closest('.wpcpm-institution').querySelector('.wpcpm-head'); return Math.round(e.getBoundingClientRect().top - h.getBoundingClientRect().bottom); }));
			await r.close();
		}
		out(JSON.stringify(leads) === JSON.stringify({ 358: [24, 24, 24], 800: [28, 28, 28], 960: [28, 28, 28] }), 'every view\'s first block, a card or a block, stands off the head as the Overview\'s does: 28px, 24px in a phone\'s window', leads);
		// Viewing as institution in the head (design 7; Gate B, B5; round 2, F4 and F11, ruled). In the narrow form (main
		// area under 800px, every case here) the control's line comes after the title and its text (the facts on the
		// Overview, the lede on a view) and before the head's button, at full width, 16px under the text and 16px over the
		// button; the eyebrow and the title are never parted (10px, nothing between). The name decides the wrap: the box is
		// beside the label while the name fits there, under it at full width otherwise, and never cut while the line has
		// room for it. With switcher.js, at windows of 390, 601, 700, 782 and 783 (the menu beside it, folded and open), on
		// the Overview and on a view, with a name of 41 characters and one of 63.
		const viewHead = (name, overview) => `<header class="wpcpm-head"><div class="wpcpm-head__copy"><div class="wpcpm-ui-head-row"><h1 class="wpcpm-head__eyebrow">Institution Dashboard</h1><form class="wpcpm-ui-viewing" method="get" data-wpcpm-pick-submits><label class="wpcpm-ui-viewing__label" id="sw-label" for="sw">Viewing as institution</label> <select class="wpcpm-ui-viewing__box" name="wpcpm_institution_view" id="sw" autocomplete="off"><option value="recEXA00000000001" selected="selected">${ name }</option><option value="recEXA00000000002">Example College</option></select> <div class="wpcpm-dashboard__switcher-combo" hidden><input type="text" id="sw-input" class="wpcpm-dashboard__switcher-input wpcpm-ui-viewing__box" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sw-list" autocomplete="off" spellcheck="false" placeholder="Find an institution" /><ul id="sw-list" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="sw-label" hidden></ul><span class="wpcpm-dashboard__switcher-status" role="status" data-wpcpm-count="Names in the list: %s" data-wpcpm-none="No institutions match that search."></span></div> <span class="wpcpm-ui-viewing__show" data-wpcpm-noscript><button type="submit" class="wpcpm-btn wpcpm-btn--secondary">Show</button></span></form></div><h2 class="wpcpm-head__title">${ overview ? name : 'Students' }</h2>${ overview ? '<p class="wpcpm-ui-item__facts wpcpm-head__facts"><span class="wpcpm-ui-item__run"><span class="wpcpm-ui-item__fact">Example City, Poland</span></span></p><div class="wpcpm-head__actions"><a class="wpcpm-btn wpcpm-btn--secondary" href="#e">Enroll students</a></div>' : '<p class="wpcpm-head__lede">Your students on the program, by cohort.</p>' }</div></header>`;
		const viewing = {};
		const names = { 41: 'Example School of Design and Applied Arts', 63: 'Example Institute of Applied Design, Engineering and Media Arts' };
		for (const [main, win] of [[358, 390], [569, 601], [668, 700], [750, 782], [655, 783], [463, 783]]) {
			for (const [len, name] of Object.entries(names)) {
				for (const overview of [true, false]) {
					const f = join(tmp, `viewing-${main}-${win}-${len}-${overview ? 'o' : 'v'}.html`);
					// The script as the page prints it, in the footer: it wires its fields on DOMContentLoaded.
					writeFileSync(f, mod.page([{ name: 'viewing', html: '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' + viewHead(name, overview) + '</div>' }], [main], fresh).replace('</body>', `<script src="${pathToFileURL(join(plugin, 'assets/js/switcher.js')).href}"></script></body>`));
					const r = await browser.newPage({ viewport: { width: win, height: 900 } });
					await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
					await r.waitForTimeout(150);
					viewing[`${main} in ${win}, ${len}, ${overview ? 'Overview' : 'a view'}`] = await r.evaluate(() => {
						const box = e => e.getBoundingClientRect();
						const form = document.querySelector('.wpcpm-ui-viewing');
						const copy = document.querySelector('.wpcpm-head__copy');
						const field = document.getElementById('sw-input');
						const shown = field.getClientRects().length ? field : document.getElementById('sw');
						const st = getComputedStyle(shown);
						const ctx = document.createElement('canvas').getContext('2d');
						ctx.font = `${st.fontWeight} ${st.fontSize} ${st.fontFamily}`;
						const needs = ctx.measureText(document.getElementById('sw').options[0].text).width + parseFloat(st.paddingLeft) + parseFloat(st.paddingRight) + parseFloat(st.borderLeftWidth) + parseFloat(st.borderRightWidth);
						const label = box(document.getElementById('sw-label'));
						const eyebrow = box(document.querySelector('.wpcpm-head__eyebrow'));
						const title = box(document.querySelector('.wpcpm-head__title'));
						const text = box(document.querySelector('.wpcpm-head__facts, .wpcpm-head__lede'));
						const actions = document.querySelector('.wpcpm-head__actions');
						const beside = label.right <= box(shown).left + 0.5;
						const room = box(copy).width - (beside ? label.width + 12 : 0);
						return {
							script: shown === field,
							eyebrowThenTitle: Math.round(title.top - eyebrow.bottom),
							underText: Math.round(box(form).top - text.bottom),
							overButton: actions ? Math.round(box(actions).top - box(form).bottom) : 16,
							fullWidth: Math.round(box(form).width) === Math.round(box(copy).width),
							nameWhole: box(shown).width + 0.5 >= Math.min(needs, box(copy).width),
							wrapsOnlyIfNeeded: beside || needs > box(copy).width - label.width - 12,
							labelClear: label.bottom <= box(shown).top + 0.5 || label.right <= box(shown).left + 0.5,
						};
					});
					await r.close();
				}
			}
		}
		const viewingOff = Object.entries(viewing).filter(([, v]) => !(v.script && v.eyebrowThenTitle === 10 && v.underText === 16 && v.overButton === 16 && v.fullWidth && v.nameWhole && v.wrapsOnlyIfNeeded && v.labelClear));
		out(0 === viewingOff.length, 'Viewing as institution in the narrow form: its own line after the title and its text and before the head\'s button, 16px from each, at full width, the eyebrow and the title together; beside its label while the name fits, under it otherwise, the name whole, at windows of 390, 601, 700, 782 and 783, on the Overview and a view, with a 41- and a 63-character name', viewingOff);
		// With no script, Show stands 12px beside the select it sends, or 16px under it as a button under a field does (Task 28
		// fix round 2, X11), and the label keeps its 6px.
		const noScript = {};
		for (const [main, win] of [[358, 390], [569, 601], [668, 700]]) {
			const f = join(tmp, `viewing-noscript-${main}.html`);
			writeFileSync(f, mod.page([{ name: 'viewing', html: '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' + viewHead(names[41], true) + '</div>' }], [main], fresh));
			const r = await browser.newPage({ viewport: { width: win, height: 900 } });
			await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
			noScript[`${main} in ${win}`] = await r.evaluate(() => {
				const box = e => e.getBoundingClientRect();
				const sel = box(document.getElementById('sw'));
				const show = box(document.querySelector('.wpcpm-ui-viewing__show .wpcpm-btn'));
				const label = box(document.getElementById('sw-label'));
				const under = show.top >= sel.bottom - 0.5;
				const labelOver = label.bottom <= sel.top + 0.5;
				return { under, show: Math.round(under ? show.top - sel.bottom : show.left - sel.right), label: labelOver ? Math.round(sel.top - label.bottom) : Math.round(sel.left - label.right) };
			});
			await r.close();
		}
		out(Object.values(noScript).every(v => (v.under ? 16 : 12) === v.show && [6, 12].includes(v.label)), 'with no script Show stands 12px beside the select or 16px under it, and the label 6px over the select (12px beside it)', noScript);
		// The Students view's read line, at the roster's foot and on one student's card alike (Gate B, B6): the parts' muted
		// line at the content's left edge, 24px under the card above it, with no hairline and no inset of its own.
		const foot = mod.page([{ name: 'foot', html: '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' + head + '<div class="wpcpm-institution__card" data-card="roster"><section class="wpcpm-ui-fold-card" id="v-roster"><details class="wpcpm-ui-fold" open><summary class="wpcpm-ui-fold__head"><h3 class="wpcpm-ui-fold__title">Current</h3></summary><div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro">x</p></div></details></section></div><p class="wpcpm-dashboard__updated" id="v-read">This student has not been read from the program records yet.</p></div>' }], [800], fresh);
		writeFileSync(join(tmp, 'foot.html'), foot);
		const ft = await browser.newPage({ viewport: { width: 1120, height: 900 } });
		await ft.goto(pathToFileURL(join(tmp, 'foot.html')).href, { waitUntil: 'load' });
		const read = await ft.evaluate(() => { const p = document.getElementById('v-read'); const s = getComputedStyle(p); const root = p.parentElement.getBoundingClientRect(); return { inset: Math.round(p.getBoundingClientRect().left - root.left) + Math.round(parseFloat(s.paddingLeft)), line: s.borderTopWidth, above: Math.round(p.getBoundingClientRect().top - document.getElementById('v-roster').getBoundingClientRect().bottom), size: s.fontSize, color: s.color, opacity: s.opacity }; });
		await ft.close();
		out(JSON.stringify(read) === JSON.stringify({ inset: 0, line: '0px', above: 24, size: '14px', color: 'rgb(101, 106, 113)', opacity: '1' }), 'the Students view\'s read line is the parts\' muted line at the content\'s edge, 24px under the card, no hairline and no inset', read);
		// A fold head that holds a photo (Gate B, B3, ruled): the photo and the head's own padding, the same above and below
		// what it holds, with or without a status tag, at 960, 800, 569 and 358; 68px where its words fit beside the photo.
		const chev = '<span class="wpcpm-ui-fold__chev" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20"></svg></span>';
		const portrait = (tag, id) => `<details class="wpcpm-ui-fold wpcpm-ui-fold--inner wpcpm-ui-fold--portrait" id="${id}"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__portrait"><span class="wpcpm-ui-fold__initials" aria-hidden="true">S</span></span><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Sky Example</span><span class="wpcpm-ui-fold__second"><span class="wpcpm-ui-whole">2026-02-16</span> to <span class="wpcpm-ui-whole">2026-09-30</span></span></span>${ tag ? '<span class="wpcpm-ui-fold__end"><span class="wpcpm-status wpcpm-status--green">Program</span></span>' : '' }${ chev }</summary><div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro">x</p></div></details>`;
		const photos = {};
		for (const [w, win] of [[960, 1280], [800, 1120], [569, 601], [358, 390]]) {
			const f = join(tmp, `photo-${w}.html`);
			writeFileSync(f, mod.page([{ name: 'photo', html: fold + portrait(true, 'p-tag') + portrait(false, 'p-plain') + '</div></details></section>' }], [w], fresh));
			const r = await browser.newPage({ viewport: { width: win, height: 900 } });
			await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
			photos[w] = await r.evaluate(() => ['p-tag', 'p-plain'].map(id => {
				const s = document.querySelector(`#${id} > summary`);
				const sb = s.getBoundingClientRect();
				const rs = [...s.querySelectorAll('.wpcpm-ui-fold__portrait, .wpcpm-ui-fold__title, .wpcpm-ui-fold__second, .wpcpm-status, .wpcpm-ui-fold__chev')].filter(e => e.getClientRects().length).map(e => e.getBoundingClientRect());
				return [Math.round(Math.min(...rs.map(x => x.top)) - sb.top), Math.round(sb.bottom - Math.max(...rs.map(x => x.bottom)))];
			}));
			await r.close();
		}
		out(Object.values(photos).every(p => p.every(([a, b]) => a === 12 && b === 12)), 'a fold head that holds a photo has the head\'s 12px above and below what it holds, with a status tag or without, at 960, 800, 569 and 358', photos);
		// An inner fold's second piece (Gate B round 2, F1, ruled): in the one-row form on the title's line, 12px after it
		// (the gap token, the item header's name to its reference), the head 44px; in the stacked form under the title,
		// 2px apart, 66px; a head with a photo unchanged. At windows of 1280 and 928 (main areas 960 and 800) and 390.
		const second = id => `<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" id="${id}"><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Correct these</span><span class="wpcpm-ui-fold__second">(saved at once)</span></span>${ chev }</summary><div class="wpcpm-ui-fold__body"><p class="wpcpm-ui-intro">x</p></div></details>`;
		const heads = {};
		for (const [w, win] of [[960, 1280], [800, 928], [358, 390]]) {
			const f = join(tmp, `second-${w}.html`);
			writeFileSync(f, mod.page([{ name: 'second', html: fold + second('s-one') + portrait(true, 's-photo') + '</div></details></section>' }], [w], fresh));
			const r = await browser.newPage({ viewport: { width: win, height: 900 } });
			await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
			heads[w] = await r.evaluate(() => {
				const s = document.querySelector('#s-one > summary');
				const t = s.querySelector('.wpcpm-ui-fold__title').getBoundingClientRect();
				const n = s.querySelector('.wpcpm-ui-fold__second').getBoundingClientRect();
				const under = n.top >= t.bottom - 1;
				return { head: Math.round(s.getBoundingClientRect().height), gap: under ? Math.round(n.top - t.bottom) : Math.round(n.left - t.right), under, photo: Math.round(document.querySelector('#s-photo > summary').getBoundingClientRect().height) };
			});
			await r.close();
		}
		out(JSON.stringify(heads) === JSON.stringify({ 358: { head: 66, gap: 2, under: true, photo: heads[358].photo }, 800: { head: 44, gap: 12, under: false, photo: 68 }, 960: { head: 44, gap: 12, under: false, photo: 68 } }) && heads[358].photo >= 68, 'an inner fold\'s second piece is on the title\'s line 12px after it in the one-row form (44px), under it 2px apart in the stacked form (66px), and a head with a photo is unchanged', heads);
		// A set's legend and a choice group's legend start where the fields under them start (Gate B round 2, F9): 0px.
		const sets = fold + '<form><fieldset class="wpcpm-ui-choices wpcpm-ui-choices--set" id="set-a"><legend class="wpcpm-ui-h">One student</legend><div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" for="f1">Full name</label><input type="text" class="wpcpm-ui-field__control" id="f1"></div></fieldset>'
			+ '<fieldset class="wpcpm-ui-choices wpcpm-ui-field" id="set-b"><legend class="wpcpm-ui-choices__legend">Level</legend><div class="wpcpm-ui-choices__options"><label class="wpcpm-ui-choice"><input type="radio" name="l"> <span class="wpcpm-ui-choice__words">Representative</span></label></div></fieldset></form></div></details></section>';
		const legends = {};
		for (const [w, win] of [[960, 1280], [358, 390]]) {
			const f = join(tmp, `legend-${w}.html`);
			writeFileSync(f, mod.page([{ name: 'legend', html: sets }], [w], fresh));
			const r = await browser.newPage({ viewport: { width: win, height: 900 } });
			await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
			legends[w] = await r.evaluate(() => ['set-a', 'set-b'].map(id => {
				const set = document.getElementById(id);
				const range = document.createRange();
				range.selectNodeContents(set.querySelector('legend'));
				const first = set.querySelector('label');
				return Math.round(range.getBoundingClientRect().left - first.getBoundingClientRect().left);
			}));
			await r.close();
		}
		out(JSON.stringify(legends) === JSON.stringify({ 358: [0, 0], 960: [0, 0] }), 'a set\'s legend and a choice group\'s legend start where the labels under them start, at 960 and 358', legends);
		// The Agreement view's upload form and its kind (Gate B round 2, F10): a block under an action row 16px under it; a
		// form's intro 8px over its first field (design 2.5), a muted one too (the report editor's sections); a field after
		// a choice group 14px under it, a hidden field between them or not. On the Institution Dashboard's root.
		const f10 = '<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on"><section class="wpcpm-ui-block" id="wpcpm-agreement"><p class="wpcpm-ui-intro">Signed and accepted through this site.</p><div class="wpcpm-ui-actions" id="t-actions"><a class="wpcpm-btn wpcpm-btn--secondary" href="#d">Download the accepted agreement</a></div>'
			+ '<form><input type="hidden" name="a" value="1"><p class="wpcpm-ui-intro" id="t-replace">Replace it by uploading a newer signed copy.</p><fieldset class="wpcpm-ui-choices wpcpm-ui-field" id="t-kind"><legend class="wpcpm-ui-choices__legend">Which agreement is this?</legend><div class="wpcpm-ui-choices__options"><label class="wpcpm-ui-choice"><input type="radio" class="wpcpm-ui-choice__input" name="k"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words">An agreement of our own, signed</span><span class="wpcpm-ui-choice__hint">An Administrator reads this one in full.</span></span></label></div></fieldset><input type="hidden" name="s" value="0"><div class="wpcpm-ui-choices wpcpm-ui-choices--alone" id="t-tick"><label class="wpcpm-ui-choice"><input type="checkbox" class="wpcpm-ui-choice__input" name="s" value="1"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words">This document is signed.</span></span></label></div></form>'
			+ '<h3 class="wpcpm-ui-h">What the students did</h3><p class="wpcpm-ui-intro wpcpm-ui-intro--muted" id="t-read">This section is read from the program records.</p><div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" id="t-say" for="t-ta">What you want to say here</label><textarea class="wpcpm-ui-field__control" id="t-ta"></textarea></div>'
			+ '<p class="wpcpm-ui-intro" id="t-enroll">Nothing is created until you have seen what the list was understood to say.</p><form><input type="hidden" name="b" value="1"><div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" id="t-program" for="t-p">Program</label><select class="wpcpm-ui-field__control" id="t-p"></select></div></form></section></div>';
		const rhythm10 = {};
		for (const [w, win] of [[960, 1280], [358, 390]]) {
			const f = join(tmp, `f10-${w}.html`);
			writeFileSync(f, mod.page([{ name: 'f10', html: f10 }], [w], fresh));
			const r = await browser.newPage({ viewport: { width: win, height: 900 } });
			await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
			rhythm10[w] = await r.evaluate(() => {
				const b = id => document.getElementById(id).getBoundingClientRect();
				const legend = document.querySelector('#t-kind legend').getBoundingClientRect();
				return { buttonToIntro: Math.round(b('t-replace').top - b('t-actions').bottom), introToField: Math.round(legend.top - b('t-replace').bottom), choicesToTick: Math.round(b('t-tick').top - b('t-kind').bottom), mutedToLabel: Math.round(b('t-say').top - b('t-read').bottom), enrollToLabel: Math.round(b('t-program').top - b('t-enroll').bottom) };
			});
			await r.close();
		}
		out(JSON.stringify(rhythm10) === JSON.stringify({ 358: { buttonToIntro: 16, introToField: 8, choicesToTick: 14, mutedToLabel: 8, enrollToLabel: 8 }, 960: { buttonToIntro: 16, introToField: 8, choicesToTick: 14, mutedToLabel: 8, enrollToLabel: 8 } }), 'a block under an action row is 16px under it, an intro (muted or not) 8px over its first field, and a field 14px under a choice group, a hidden field between or not, at 960 and 358', rhythm10);
		// "Required" after a label's words (Gate B round 2, F12, ruled): on the Institution Dashboard a need word that wraps
		// onto a line of its own starts at the lines' edge; beside the words its gap is today's, within 0.5px (today's is
		// drawn on the Administrator Dashboard's page, whose sheets are the same parts with no rule of this page's); a label
		// that wraps elsewhere keeps the word on its last line at today's gap. The parts' markup as WPCPM_UI prints it.
		const needAfter = ' <span class="wpcpm-ui-field__need">Required</span>';
		const needCard = () => fold
			+ `<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" id="q-field" for="q1">Name of the person we should contact${ needAfter }</label><input type="text" class="wpcpm-ui-field__control" id="q1"></div>`
			+ `<fieldset class="wpcpm-ui-choices wpcpm-ui-field"><legend class="wpcpm-ui-choices__legend" id="q-group">In which languages do your students study?${ needAfter }</legend><div class="wpcpm-ui-choices__options"><label class="wpcpm-ui-choice"><input type="checkbox" class="wpcpm-ui-choice__input"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words">English</span></span></label></div></fieldset>`
			+ `<div class="wpcpm-ui-choices wpcpm-ui-choices--alone"><label class="wpcpm-ui-choice"><input type="checkbox" class="wpcpm-ui-choice__input"><span class="wpcpm-ui-choice__text"><span class="wpcpm-ui-choice__words" id="q-tick">These students have been notified that their name and email address are shared with the WordPress Foundation for the WordPress Credits Program.${ needAfter }</span></span></label></div>`
			+ `<div class="wpcpm-ui-field wpcpm-ui-file"><label class="wpcpm-ui-field__label" id="q-file" for="q2">The signed agreement, as a PDF of up to 10 MB${ needAfter }</label><input type="file" id="q2"></div>`
			// A need word of two words, as a translation may have (round 3, R5): its own width is today's.
			+ '<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" id="q-two" for="q4">Email <span class="wpcpm-ui-field__need">Pole wymagane</span></label><input type="text" class="wpcpm-ui-field__control" id="q4"></div>'
			+ `<div class="wpcpm-ui-field"><label class="wpcpm-ui-field__label" id="q-else" for="q3">Page about WordPress Credits on your institution's website${ needAfter }</label><input type="text" class="wpcpm-ui-field__control" id="q3"></div>`
			+ '</div></details></section>';
		// `tight`: each box made as wide as its words on one line, so the need word wraps alone (as the four labels do at
		// 390 on the site); the last label made 60% as wide as its words, so it wraps there and keeps the word beside.
		const needOf = async (html, surface, w, win, tight) => {
			const f = join(tmp, `need-${surface}-${w}-${tight ? 't' : 'n'}.html`);
			writeFileSync(f, mod.page([{ name: 'need', html, surface }], [w], fresh));
			const r = await browser.newPage({ viewport: { width: win, height: 1200 } });
			await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
			await r.evaluate(t => ['q-field', 'q-group', 'q-tick', 'q-file', 'q-else'].forEach(id => {
				const host = document.getElementById(id);
				const box = host.closest('.wpcpm-ui-field, .wpcpm-ui-choices');
				const span = document.createElement('span');
				span.style.whiteSpace = 'nowrap';
				span.style.font = getComputedStyle(host).font;
				span.textContent = host.firstChild.textContent.trimEnd();
				document.body.appendChild(span);
				const one = span.getBoundingClientRect().width;
				span.remove();
				const lead = host.getBoundingClientRect().left - box.getBoundingClientRect().left;
				box.style.width = 'q-else' === id ? Math.round(one * 0.6) + 'px' : t ? Math.ceil(one + lead + 2) + 'px' : '';
			}), tight);
			const got = await r.evaluate(() => Object.fromEntries(['q-field', 'q-group', 'q-tick', 'q-file', 'q-else', 'q-two'].map(id => {
				const host = document.getElementById(id);
				const words = host.firstChild;
				const word = document.createRange();
				word.selectNodeContents(host.querySelector('.wpcpm-ui-field__need').firstChild);
				const at = word.getClientRects()[0];
				const lines = document.createRange();
				lines.setStart(words, 0);
				lines.setEnd(words, words.textContent.trimEnd().length);
				const rects = [...lines.getClientRects()];
				const last = rects[rects.length - 1];
				const width = Math.round(word.getBoundingClientRect().width * 100) / 100;
				return [id, at.top > last.bottom - 1 ? { own: Math.round((at.left - rects[0].left) * 100) / 100, width } : { gap: Math.round((at.left - last.right) * 100) / 100, lines: new Set(rects.map(x => Math.round(x.top))).size, width }];
			})));
			await r.close();
			return got;
		};
		const today = await needOf('<div class="wpcpm-dashboard wpcpm-administrator wpcpm-administrator--overview wpcpm-ui-on">' + needCard() + '</div>', 'administrator', 960, 1280, false);
		const wide = await needOf('<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' + needCard() + '</div>', 'institution', 960, 1280, false);
		const phone = await needOf('<div class="wpcpm-dashboard wpcpm-institution wpcpm-ui-on">' + needCard() + '</div>', 'institution', 358, 390, true);
		const sameGap = [ 'q-field', 'q-group', 'q-tick', 'q-file', 'q-else' ].every(id => today[id].gap !== undefined && wide[id].gap !== undefined && Math.abs(today[id].gap - wide[id].gap) <= 0.5);
		const wrapped = [ 'q-field', 'q-group', 'q-tick', 'q-file' ].every(id => phone[id].own !== undefined && Math.abs(phone[id].own) <= 0.5);
		const elsewhere = wide['q-else'].lines > 1 && Math.abs(wide['q-else'].gap - today['q-else'].gap) <= 0.5;
		const twoWords = Math.abs(wide['q-two'].width - today['q-two'].width) <= 0.5 && Math.abs(wide['q-two'].gap - today['q-two'].gap) <= 0.5;
		out(sameGap && wrapped && elsewhere && twoWords, '"Required" beside the words keeps today\'s gap within 0.5px at 1280 (a field, a choice group, a tick, a file box), starts at the lines\' edge when it wraps alone at 390 under the four labels, stays on the last line of a label that wraps elsewhere, and a need word of two words keeps its own width', { today, wide, phone });
		// The census on the parts' markup (Task 27b): a set's and a group's legends, as WPCPM_UI prints them, draw no GAP
		// and no EDGE fault through the harness's own run, at 960 and 358 (every real case of the suites is drawn by the
		// block after this one); and on the Agreement view's upload form the census reads the rhythm the checks above read
		// with their own hands. (That drawing joins pieces of three screens, so its own last pairs are not a screen's.)
		const runMod = await import(pathToFileURL(join(plugin, 'bin/ui/layout/run.mjs')).href);
		const real = await runMod.measureCases([{ name: 'f10', html: f10 }, { name: 'sets', html: sets }], [960, 358], { pluginDir: plugin, themeDir: theme, fixtureDir: join(tmp, 'fresh') });
		const spacing = real.results.filter(r => 'sets' === r.name).flatMap(r => r.faults.filter(f => /^(GAP|EDGE) /.test(f)).map(f => r.w + ' ' + f));
		out(0 === spacing.length && 3 === real.results.filter(r => 'sets' === r.name).length, 'a set\'s and a group\'s legends over their fields draw no GAP and no EDGE fault in the harness\'s run, at 960 (folded and open) and 358', spacing);
		const pair = (name, u, l) => real.results.filter(r => r.name === name).map(r => (r.census.gaps.find(g => g.u === u && g.l === l) || {}).gap);
		const reads = { introToGroup: pair('f10', 'paragraph', 'choice group'), groupToTick: pair('f10', 'choice group', 'tick'), rowToIntro: pair('f10', 'action row', 'paragraph'), mutedToField: pair('f10', 'muted line', 'field'), legendToOption: pair('sets', 'legend', 'options') };
		out(JSON.stringify(reads) === JSON.stringify({ introToGroup: [8, 8, 8], groupToTick: [14, 14, 14], rowToIntro: [16, 16, 16], mutedToField: [8, 8, 8], legendToOption: [6, 6, 6] }), 'and its census reads them as the checks above do: an intro 8px over its group, a tick 14px under it, a sentence 16px under an action row, a muted intro 8px over a field, a legend 6px over its option, at each window', reads);
		const legendEdges = real.results.flatMap(r => r.census.edges.filter(e => 'legend' === e.k));
		out(0 === legendEdges.length && real.results.every(r => r.census.zeroEdges > 0), 'and every legend there starts at its fields\' edge: no legend edge off, the edges on counted', legendEdges);
		// Gate B round 3, R4: F1's one-row second piece is the fold's own head's: a portrait fold drawn inside a plain inner
		// fold keeps its second piece under its name, at 960.
		const nestedF = join(tmp, 'nested-portrait.html');
		writeFileSync(nestedF, mod.page([{ name: 'nested', html: fold + `<details class="wpcpm-ui-fold wpcpm-ui-fold--inner" open><summary class="wpcpm-ui-fold__head"><span class="wpcpm-ui-fold__lead"><span class="wpcpm-ui-fold__title">Students</span><span class="wpcpm-ui-fold__second">(two)</span></span>${ chev }</summary><div class="wpcpm-ui-fold__body">` + portrait(false, 'r4-photo') + '</div></details></div></details></section>' }], [960], fresh));
		const nr = await browser.newPage({ viewport: { width: 1280, height: 900 } });
		await nr.goto(pathToFileURL(nestedF).href, { waitUntil: 'load' });
		const nested = await nr.evaluate(() => { const s = document.querySelector('#r4-photo > summary'); const t = s.querySelector('.wpcpm-ui-fold__title').getBoundingClientRect(); const n = s.querySelector('.wpcpm-ui-fold__second').getBoundingClientRect(); return { lead: getComputedStyle(s.querySelector('.wpcpm-ui-fold__lead')).display, under: n.top >= t.bottom - 1 }; });
		await nr.close();
		out('block' === nested.lead && nested.under, 'a portrait fold inside a plain inner fold keeps its second piece under its name (F1\'s rule reaches only the fold\'s own head)', nested);
		// Task 28 fix round 2, X14: Tracks offered with none listed: "None listed yet." stands 16px over the sentence that
		// follows it, word to word (the empty line's own 12px under its words is for a heading after it, which keeps 24px).
		const x14f = join(tmp, 'x14.html');
		writeFileSync(x14f, mod.page([{ name: 'x14', html: fold + '<h4 class="wpcpm-ui-h">Tracks offered</h4><p class="wpcpm-ui-list__empty" id="x14-empty">None listed yet.</p><p class="wpcpm-ui-intro wpcpm-ui-intro--muted" id="x14-note">The program sets the tracks your institution offers.</p><p class="wpcpm-ui-list__empty" id="x14-empty2">None listed yet.</p><h4 class="wpcpm-ui-h" id="x14-h">Next</h4><p class="wpcpm-ui-intro">x</p></div></details></section>' }], [960], fresh));
		const x14t = await browser.newPage({ viewport: { width: 1280, height: 900 } });
		await x14t.goto(pathToFileURL(x14f).href, { waitUntil: 'load' });
		const x14 = await x14t.evaluate(() => { const words = id => { const e = document.getElementById(id); const c = getComputedStyle(e); const r = e.getBoundingClientRect(); return { top: r.top + parseFloat(c.paddingTop) + parseFloat(c.borderTopWidth), bottom: r.bottom - parseFloat(c.paddingBottom) - parseFloat(c.borderBottomWidth) }; }; return { note: Math.round(words('x14-note').top - words('x14-empty').bottom), heading: Math.round(words('x14-h').top - words('x14-empty2').bottom) }; });
		await x14t.close();
		out(16 === x14.note && 24 === x14.heading, 'None listed yet. stands 16px over the sentence after it, word to word, and 24px over a heading', x14);
		// Task 28 fix round 2, X12: on this page's root at main 358, a name that fits the row on one line keeps it and its tag
		// goes under it (the tag at the row's start, 8px under the name); a 60-character name is longer than the row and
		// breaks, its tag under it too; the facts and the action keep their lines.
		const x12row = (id, name) => `<li class="wpcpm-ui-list__row" id="${ id }"><div class="wpcpm-ui-list__line"><span class="wpcpm-ui-list__name">${ name }</span><span class="wpcpm-status" title="The program writes to this address about the institution.">the institution&#039;s contact</span><span class="wpcpm-ui-list__facts"><span class="wpcpm-ui-item__fact">person@example.org</span><span class="wpcpm-ui-item__fact">Representative</span></span><div class="wpcpm-ui-list__act"><button type="button" class="wpcpm-btn wpcpm-btn--quiet">Remove</button></div></div></li>`;
		const x12f = join(tmp, 'x12.html');
		writeFileSync(x12f, mod.page([{ name: 'x12', html: fold.replace('<section class="wpcpm-ui-fold-card">', '<section class="wpcpm-ui-fold-card" id="wpcpm-people">') + '<ul class="wpcpm-ui-list wpcpm-ui-list--under">' + x12row('x12-short', 'Quinn Example') + x12row('x12-long', 'Exampleinstitutionmemberwithaverylongname Example Personname') + '</ul></div></details></section><section class="wpcpm-institution__waiting"><h2 class="wpcpm-ui-h">Waiting for you</h2><ul class="wpcpm-ui-list">' + x12row('x12-sentence', 'Your semester report for January to June 2026 is ready') + '</ul></section>' }], [358], fresh));
		const x12t = await browser.newPage({ viewport: { width: 390, height: 900 } });
		await x12t.goto(pathToFileURL(x12f).href, { waitUntil: 'load' });
		const x12 = await x12t.evaluate(() => { const o = {}; for (const id of ['x12-short', 'x12-long', 'x12-sentence']) { const row = document.getElementById(id); const b = s => row.querySelector(s).getBoundingClientRect(); const n = b('.wpcpm-ui-list__name'), t = b('.wpcpm-status'), f = b('.wpcpm-ui-list__facts'), a = b('.wpcpm-ui-list__act .wpcpm-btn'); o[id] = { nameLines: Math.round(n.height / 20), tagUnder: Math.round(t.top - n.bottom) >= 0, tagAtStart: Math.round(t.left - n.left), factsUnder: f.top >= t.bottom - 0.5, actUnder: a.top >= f.bottom - 0.5, tagAtEnd: Math.round(row.getBoundingClientRect().right - t.right) }; } return o; });
		await x12t.close();
		// Fix round 2's ruling on X12: only rows of people (the people card, the Students view's unlinked rows); an Overview row,
		// whose title is a sentence, keeps the parts' grid, its tag beside the title at the line's end.
		out(x12['x12-short'].nameLines === 1 && x12['x12-short'].tagUnder && 0 === x12['x12-short'].tagAtStart && x12['x12-long'].nameLines === 2 && x12['x12-long'].tagUnder && 0 === x12['x12-long'].tagAtStart && ['x12-short', 'x12-long'].every(k => x12[k].factsUnder && x12[k].actUnder) && !x12['x12-sentence'].tagUnder && 0 === x12['x12-sentence'].tagAtEnd, 'on the people card a name that fits the row keeps its line and its tag goes under it at main 358, a 60-character name breaks with its tag under it; an Overview row keeps its tag beside its sentence at the line\'s end', x12);
		// Task 28 fix round 2, X10: a label that carries a need word is as tall as its words: a one-line label with "Required"
		// is 20px, the same label without it 20px, at 1280 (main 960); a label whose need word wraps is 40px, its two lines
		// 20px apart, at 390 (main 358): a field, a choice group's legend, a tick and a file box.
		const x10 = {};
		for (const [w, size] of [[960, 'short'], [358, 'long']]) {
			const nf = join(tmp, 'x10-' + w + '.html');
			const pieces = Object.entries(expect.needs[size]).map(([k, v]) => Object.entries(v).map(([kind, h]) => `<div id="x10-${ k }-${ kind }">${ h }</div>`).join('')).join('');
			writeFileSync(nf, mod.page([{ name: 'x10', html: fold + pieces + '</div></details></section>' }], [w], fresh));
			const nt = await browser.newPage({ viewport: { width: w < 800 ? w + 32 : w + 320, height: 900 } });
			await nt.goto(pathToFileURL(nf).href, { waitUntil: 'load' });
			x10[w] = await nt.evaluate(() => { const o = {}; for (const kind of ['field', 'legend', 'tick', 'file']) { const need = document.querySelector('#x10-with-' + kind + ' .wpcpm-ui-field__need'); const L = need.parentElement; const bare = document.querySelector('#x10-without-' + kind + ' ' + L.tagName.toLowerCase() + '.' + L.classList[0]); const top = L.getBoundingClientRect().top; const r = document.createRange(); r.selectNodeContents(L); o[kind] = { with: Math.round(L.getBoundingClientRect().height), without: Math.round(bare.getBoundingClientRect().height), lines: [...new Set([...r.getClientRects()].filter(x => x.width > 0).map(x => Math.round(x.top - top)))].sort((a, b) => a - b) }; } return o; });
			await nt.close();
		}
		const x10ok = ['field', 'legend', 'tick', 'file'].every(k => x10[960][k].with === 20 && x10[960][k].without === 20 && x10[358][k].with === 40 && 2 === x10[358][k].lines.length && 20 === x10[358][k].lines[1] - x10[358][k].lines[0]);
		out(x10ok, 'a label with "Required" is as tall as its words: 20px on one line as without it at 1280, 40px with its two lines 20px apart where the word wraps at 390 (a field, a legend, a tick, a file box)', x10);
		// Task 28 fix round 2, X1: a run of inner folds as a list, on the part's own markup, at 960 and 358: the second fold
		// starts where the first ends with one line between them (the first's bottom hairline, the second's top none), no
		// bullet and no inset (the items and the folds at the intro's left edge), and the list 16px under the intro by its
		// own margin: in a block whose text is 10px too, where a list's default margin would be 10px.
		const x1 = {};
		for (const w of [960, 358]) {
			const xf = join(tmp, 'x1-' + w + '.html');
			writeFileSync(xf, mod.page([{ name: 'x1', html: fold + '<p class="wpcpm-ui-intro wpcpm-ui-intro--muted" id="x1-intro">On the program now.</p>' + expect.folds + '</div></details></section><section class="wpcpm-ui-block" style="font-size:10px"><p class="wpcpm-ui-intro" id="x1-intro2">In a block.</p>' + expect.folds.replace(/x1-a/g, 'x1-c').replace(/x1-b/g, 'x1-d') + '</section>' }], [w], fresh));
			const xt = await browser.newPage({ viewport: { width: w < 800 ? w + 32 : w + 300, height: 900 } });
			await xt.goto(pathToFileURL(xf).href, { waitUntil: 'load' });
			x1[w] = await xt.evaluate(() => { const r = id => document.getElementById(id).getBoundingClientRect(); const a = document.getElementById('x1-a'), b = document.getElementById('x1-b'), ul = a.closest('.wpcpm-ui-folds'), li = a.parentElement, i = r('x1-intro'); return { list: !!ul && li.matches('.wpcpm-ui-folds__item'), meet: Math.round(r('x1-b').top - r('x1-a').bottom), lines: [getComputedStyle(a).borderBottomWidth, getComputedStyle(b).borderTopWidth], bullet: getComputedStyle(li).listStyleType, inset: [Math.round(li.getBoundingClientRect().left - i.left), Math.round(r('x1-a').left - i.left), Math.round(r('x1-b').left - i.left)], under: Math.round(ul.getBoundingClientRect().top - i.bottom), firstUnder: Math.round(r('x1-a').top - i.bottom), margins: [getComputedStyle(ul).marginTop, getComputedStyle(ul).marginBottom, getComputedStyle(ul).paddingLeft], inBlock: [Math.round(document.getElementById('x1-c').closest('.wpcpm-ui-folds').getBoundingClientRect().top - r('x1-intro2').bottom), getComputedStyle(document.getElementById('x1-c').closest('.wpcpm-ui-folds')).marginTop] }; });
			await xt.close();
		}
		const x1want = { list: true, meet: 0, lines: ['1px', '0px'], bullet: 'none', inset: [0, 0, 0], under: 16, firstUnder: 16, margins: ['16px', '0px', '0px'], inBlock: [16, '16px'] };
		out([960, 358].every(w => JSON.stringify(x1[w]) === JSON.stringify(x1want)), 'a run of inner folds as a list (WPCPM_UI::inner_list()): the second fold starts where the first ends with one line between them, no bullet and no inset, the list 16px under the intro by its own margin, in a fold\'s body and in a block, and none below it, at 960 and 358', x1);
		// Task 28, W8: printing the Semester reports view prints the report. With the report's document on the page the
		// frame's bar, its side menu and the view's head have no box in print media, and the document has; on a page with
		// no document (the reports' index, any other view) the frame prints as before.
		// The account prompt is drawn inside the root above the head (WPCPM_Two_Factor::prompt(); Task 28 fix round 2, X4).
		const w8Head = '<aside class="wpcpm-2fa"><h2 class="wpcpm-2fa__title">Add a second step to your sign-in</h2><p class="wpcpm-2fa__body">A code too.</p></aside><header class="wpcpm-head"><div class="wpcpm-head__copy"><h2 class="wpcpm-head__title">Semester reports</h2></div></header>';
		const reportView = '<section class="wpcpm-ui-block" id="wpcpm-report"><h2 class="wpcpm-ui-item__title">January to June 2026</h2><div class="wpcpm-ui-doc"><article class="wpcpm-report-doc"><h2 class="wpcpm-report-doc__title">Semester report</h2><p>Body.</p></article></div></section>';
		const reportIndex = '<section class="wpcpm-ui-block" id="wpcpm-report"><ul class="wpcpm-ui-list"><li class="wpcpm-ui-list__row">January to June 2026</li></ul></section>';
		const printed = {};
		for (const [k, body] of [['report', reportView], ['index', reportIndex]]) {
			const pf = join(tmp, 'w8-' + k + '.html');
			writeFileSync(pf, mod.page([{ name: 'w8', html: w8Head + body }], [960], fresh));
			const pt = await browser.newPage({ viewport: { width: 1280, height: 900 } });
			await pt.goto(pathToFileURL(pf).href, { waitUntil: 'load' });
			await pt.evaluate(() => { const f = document.querySelector('.wpcpm-frame'); const b = f.querySelector('.wpcpm-frame__body'); const bar = document.createElement('div'); bar.className = 'wpcpm-frame__bar'; bar.textContent = 'WordPress Credits'; f.insertBefore(bar, b); const nav = document.createElement('nav'); nav.className = 'wpcpm-frame__menu'; nav.textContent = 'Overview'; b.insertBefore(nav, b.firstChild); });
			await pt.emulateMedia({ media: 'print' });
			printed[k] = await pt.evaluate(() => { const has = s => { const e = document.querySelector(s); return !!e && e.getClientRects().length > 0; }; return { bar: has('.wpcpm-frame__bar'), menu: has('.wpcpm-frame__menu'), head: has('.wpcpm-institution > .wpcpm-head'), prompt: has('.wpcpm-institution > .wpcpm-2fa'), doc: has('.wpcpm-report-doc'), list: has('#wpcpm-report .wpcpm-ui-list'), mainAtStart: 0 === Math.round(document.querySelector('.wpcpm-frame__main').getBoundingClientRect().left - document.querySelector('.wpcpm-frame__body').getBoundingClientRect().left) }; });
			await pt.close();
		}
		out(JSON.stringify(printed) === JSON.stringify({ report: { bar: false, menu: false, head: false, prompt: false, doc: true, list: false, mainAtStart: true }, index: { bar: true, menu: true, head: true, prompt: true, doc: false, list: true, mainAtStart: false } }), 'printing the Semester reports view with a report open prints the document without the frame\'s bar, its menu, the view\'s head or the account prompt, the main area across the page where the menu stood; a page with no document prints them as before', printed);
		const want = { first: 0, afterFold: [24, 8], doc: [24, 8], item: 8, nextItem: 24, waiting: 8 };
		out([960, 800, 358].every(w => JSON.stringify(gaps[w]) === JSON.stringify(want)), 'an in-card heading is 24px under the block above it (an inner fold too) and 8px over what it heads (a document, an item, the Overview\'s quiet line), the first none above, and an item\'s first block starts at its own top, at 960, 800 and 358', gaps);
	} finally {
		await browser.close();
	}
}
JS;
	$file = $tmp . 'page-check.mjs';
	file_put_contents( $file, $script );
	$lines  = array();
	$status = 0;
	exec( escapeshellarg( $node ) . ' ' . escapeshellarg( $file ) . ' ' . escapeshellarg( $root ) . ' ' . escapeshellarg( rtrim( $tmp, '/' ) ) . ' ' . escapeshellarg( (string) json_encode( $expect ) ) . ' 2>&1', $lines, $status );
	$ran = 0;
	foreach ( $lines as $line ) {
		echo $line . "\n";
		if ( 0 === strpos( $line, 'FAIL ' ) ) {
			++$fail;
			++$ran;
		} elseif ( 0 === strpos( $line, 'ok   ' ) ) {
			++$ran;
		}
	}
	ck( 'node ran the page\'s checks', $ran >= 6 );
	ck( 'and ended with a status of 0', $status, 0 );
}

// ------------------------------------------------------------------ GAP and EDGE on every suite that keeps cases
// Task 27b. The harness's normal run fails on a gap under 6px between words and a control or a button, or off the
// rhythm, on the Institution Dashboard (GAP), and on a legend off its fields' edge on any surface (EDGE): run.mjs,
// spacingFaults(). The findings the census found on 17 Nov 2026 are held here, and only here, as expected failures:
// the fix round removes each from this list as it fixes it. Each is its suite, its case, its fault (with the words
// of the two blocks) and the widths it fires at ('all': every width and window the harness draws).
// The run needs node, Playwright, Chrome and the theme folder the global styles fixture was made from
// (WPCPM_THEME_DIR); without one of them it gets one `skip` line.
echo "--- GAP and EDGE on every suite that keeps cases: the known findings, no more and no fewer\n";
$known = array(
);
$suites = array();
foreach ( glob( $root . '/bin/test-*.php' ) as $suite_file ) {
	if ( 'test-ui-layout.php' !== basename( $suite_file ) && false !== strpos( (string) file_get_contents( $suite_file ), 'wpcpm_layout_keep(' ) ) {
		$suites[] = $suite_file;
	}
}
ck( 'thirteen suites keep cases for the harness', count( $suites ), 13 );
$node_run = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( '' === $node_run ) {
	echo "skip the suites were not drawn: node is not on the path\n";
} else {
	// Every suite's cases in one file, each named `<suite> | <case>`, drawn in one run.
	$all_cases = array();
	$census_dir = wpcpm_test_temp_dir() . 'census/';
	mkdir( $census_dir, 0700, true );
	foreach ( $suites as $suite_file ) {
		$suite_name = preg_replace( '/^test-|\.php$/', '', basename( $suite_file ) );
		$suite_out  = $census_dir . $suite_name . '.json';
		exec( 'cd ' . escapeshellarg( $root ) . ' && WPCPM_LAYOUT_OUT=' . escapeshellarg( $suite_out ) . ' ' . escapeshellarg( PHP_BINARY ) . ' -d auto_prepend_file=' . escapeshellarg( $layout . '/dump.php' ) . ' ' . escapeshellarg( $suite_file ) . ' > /dev/null 2>&1' );
		foreach ( (array) json_decode( read_layout( $suite_out ), true ) as $case_name => $case ) {
			$all_cases[ $suite_name . ' | ' . $case_name ] = $case;
		}
	}
	file_put_contents( $census_dir . 'all.json', json_encode( (object) $all_cases ) );
	// Gate B round 3, R2: F10's tick rule on the real markup. The signer's tick in the Agreement's upload form, as the
	// panel's own renderer draws it (the panel suite's kept cases): 14px under the kind's choice group, a hidden field
	// between them, at main 960 and 358.
	// And round 3, B2: two of the Agreement's steps stand 16px apart where either holds a form, 6px where both are plain
	// sentences (the census does not read a list's items), in every kept panel case that draws the steps.
	$tick_cases = array();
	foreach ( $all_cases as $case_name => $case ) {
		if ( 0 === strpos( $case_name, 'institution-panel | ' ) && ( false !== strpos( (string) $case['html'], 'wpcpm-ui-choices--alone' ) || false !== strpos( (string) $case['html'], 'wpcpm-ui-checklist__item' ) ) ) {
			$tick_cases[ $case_name ] = $case;
		}
	}
	file_put_contents( $census_dir . 'ticks.json', json_encode( (object) $tick_cases ) );
	$tick_js = <<<'JS'
import { pathToFileURL } from 'node:url';
import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { homedir } from 'node:os';
const [plugin, dir] = process.argv.slice(2);
const mod = await import(pathToFileURL(join(plugin, 'bin/ui/layout/page.mjs')).href);
const { chromium } = await import(pathToFileURL(process.env.WPCPM_PLAYWRIGHT || join(homedir(), 'Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs')).href);
const cases = Object.entries(JSON.parse(readFileSync(join(dir, 'ticks.json'), 'utf8'))).map(([name, c]) => ({ name, html: c.html, surface: c.surface || 'institution' }));
const browser = await chromium.launch({ channel: 'chrome', headless: true, args: ['--allow-file-access-from-files'] });
const gaps = {};
for (const [w, win] of [[960, 1280], [358, 390]]) {
	const f = join(dir, `ticks-${w}.html`);
	writeFileSync(f, mod.page(cases, [w], { pluginDir: plugin }));
	const r = await browser.newPage({ viewport: { width: win, height: 1000 } });
	await r.goto(pathToFileURL(f).href, { waitUntil: 'load' });
	const got = await r.evaluate(() => [...document.querySelectorAll('.wpcpm-ui-choices--alone')].filter(t => t.getClientRects().length).map(t => {
		let p = t.previousElementSibling;
		while (p && 'INPUT' === p.tagName && 'hidden' === p.type) { p = p.previousElementSibling; }
		return p && p.matches('.wpcpm-ui-choices') ? Math.round(t.getBoundingClientRect().top - p.getBoundingClientRect().bottom) : null;
	}).filter(x => null !== x));
	for (const g of got) { gaps[g] = (gaps[g] || 0) + 1; }
	const steps = await r.evaluate(() => [...document.querySelectorAll('ol.wpcpm-ui-checklist > .wpcpm-ui-checklist__item + .wpcpm-ui-checklist__item')].filter(li => li.getClientRects().length).map(li => {
		const prev = li.previousElementSibling;
		const form = !!(li.querySelector(':scope > form') || prev.querySelector(':scope > form'));
		return (form ? 'with a form ' : 'two sentences ') + Math.round(li.getBoundingClientRect().top - prev.getBoundingClientRect().bottom);
	}));
	for (const g of steps) { gaps[g] = (gaps[g] || 0) + 1; }
	await r.close();
}
await browser.close();
console.log(JSON.stringify(gaps));
JS;
	file_put_contents( $census_dir . 'ticks.mjs', $tick_js );
	$tick_out = trim( (string) shell_exec( 'cd ' . escapeshellarg( $root ) . ' && ' . escapeshellarg( $node_run ) . ' ' . escapeshellarg( $census_dir . 'ticks.mjs' ) . ' ' . escapeshellarg( $root ) . ' ' . escapeshellarg( rtrim( $census_dir, '/' ) ) . ' 2>&1' ) );
	$tick_gaps = json_decode( $tick_out, true );
	if ( ! is_array( $tick_gaps ) ) {
		echo 'skip the panel\'s ticks were not drawn: ' . $tick_out . "\n";
	} else {
		$step_gaps = array_values( array_filter( array_keys( $tick_gaps ), 'is_string' ) );
		sort( $step_gaps );
		ck( 'the Agreement\'s signer tick, drawn by the panel, stands 14px under the kind\'s choice group in every kept case at 960 and 358 (' . count( $tick_cases ) . ' cases)', array_values( array_filter( array_keys( $tick_gaps ), 'is_int' ) ), array( 14 ) );
		ck( 'two of the Agreement\'s steps stand 16px apart where either holds a form', $step_gaps, array( 'with a form 16' ) );
	}
	$run_lines  = array();
	$run_status = 0;
	exec( 'cd ' . escapeshellarg( $root ) . ' && ' . escapeshellarg( $node_run ) . ' ' . escapeshellarg( $layout . '/run.mjs' ) . ' --in ' . escapeshellarg( $census_dir . 'all.json' ) . ' --out ' . escapeshellarg( $census_dir . 'result.json' ) . ' --census ' . escapeshellarg( $census_dir . 'census.json' ) . ' 2>&1', $run_lines, $run_status );
	$result = json_decode( read_layout( $census_dir . 'result.json' ), true );
	if ( 2 === $run_status || ! is_array( $result ) ) {
		echo 'skip the suites were not drawn: ' . implode( ' ', array_slice( $run_lines, -1 ) ) . "\n";
	} else {
		ck( 'every kept case was drawn (' . count( $all_cases ) . ')', count( array_unique( array_column( $result, 'name' ) ) ), count( $all_cases ) );
		$all_widths = array_values( array_unique( array_map( static function ( $r ) { return preg_replace( '/ panels open$/', '', $r['w'] ); }, $result ) ) );
		$found      = array();
		$other      = array();
		foreach ( $result as $r ) {
			foreach ( $r['faults'] as $f ) {
				if ( 1 !== preg_match( '/^(GAP|EDGE) /', $f ) ) {
					$other[] = $r['name'] . ' ' . $r['w'] . ' ' . $f;
					continue;
				}
				list( $suite_name, $case_name ) = explode( ' | ', $r['name'], 2 );
				$found[ $suite_name . "\n" . $case_name . "\n" . $f ][] = $r['w'];
			}
		}
		$got = array();
		foreach ( $found as $key => $widths ) {
			list( $suite_name, $case_name, $f ) = explode( "\n", $key, 3 );
			$got[] = array( $suite_name, $case_name, $f, $widths === $all_widths ? 'all' : implode( ', ', $widths ) );
		}
		$line = static function ( $e ) {
			return 'array( ' . implode( ', ', array_map( static function ( $v ) { return var_export( $v, true ); }, $e ) ) . ' ),';
		};
		$want_lines = array_map( $line, $known );
		$got_lines  = array_map( $line, $got );
		$new        = array_values( array_diff( $got_lines, $want_lines ) );
		$gone       = array_values( array_diff( $want_lines, $got_lines ) );
		// Gate B round 3: the ruled pairs the census reads on the rhythm but cannot fail when they go back (0px is on it), each
		// held to its value at every width and window: suite, the pair's kinds, words in either block, the value.
		$census = json_decode( read_layout( $census_dir . 'census.json' ), true );
		$ruled  = array(
			array( 'institution-panel', 'note', 'paragraph', '/agreement is not settled/', 8 ),
			array( 'institution-panel', 'muted line', 'paragraph', '/^Upload (the corrected|a new signed) agreement/', 8 ),
			array( 'institution-panel', 'line of text', 'message', '/^(Get the agreement|Upload the signed PDF)/', 16.5 ),
			array( 'institution-panel', 'line of text', 'field', '/^Get the agreement/', 8.5 ),
			array( 'institution-panel', 'line of text', 'choice group', '/^Upload the signed PDF/', 8.5 ),
			array( 'institution-panel', 'action row', 'paragraph', '/^Your program contact/', 16 ),
			array( 'institution-panel', 'action row', 'field', '/^Open that document again/', 16 ),
			array( 'institution-import-form', 'muted line', 'table', '/^Columns this import does not read/', 8 ),
			array( 'institution-import-form', 'file box', 'paragraph', '/^Download a sample list/', 6 ),
			array( 'institution-import-form', 'error summary', 'field', '/need(s)? a change/', 16 ),
			array( 'institution-student-view', 'error summary', 'field', '/need(s)? a change/', 16 ),
			array( 'institution-student-view', 'paragraph', 'field', '/^These five details/', 8 ),
			array( 'institution-roster-view', 'card fold', 'muted line', '/^Read from the program records/', 24 ),
			array( 'institution-roster-view', 'muted line', 'muted line', '/^No students in this group/', 16 ),
			array( 'institution-people', 'list', 'muted line', '/^Nobody can act for this institution/', 16 ),
		);
		$ruled_got = array();
		foreach ( $ruled as $rule ) {
			list( $suite_name, $upper, $lower, $words, $value ) = $rule;
			$seen = array();
			foreach ( (array) $census as $row ) {
				if ( 'gap' === ( $row['type'] ?? '' ) && 0 === strpos( (string) $row['case'], $suite_name . ' | ' ) && $upper === $row['u'] && $lower === $row['l'] && ( 1 === preg_match( $words, (string) $row['a'] ) || 1 === preg_match( $words, (string) $row['b'] ) ) ) {
					$seen[ (string) $row['gap'] ] = true;
				}
			}
			$ruled_got[] = $suite_name . ' ' . $upper . ' to ' . $lower . ' ' . $words . ': ' . ( $seen ? implode( ', ', array_keys( $seen ) ) : 'none drawn' );
		}
		ck( 'the ruled pairs of round 3 hold their values in every case and window that draws them', $ruled_got, array_map( static function ( $rule ) { return $rule[0] . ' ' . $rule[1] . ' to ' . $rule[2] . ' ' . $rule[3] . ': ' . $rule[4]; }, $ruled ) );
		ck( 'no OVERFLOW, CELL or H-SCROLL on any suite', $other, array() );
		ck( 'no GAP or EDGE fault that is not a known finding', $new, array() );
		ck( 'and every known finding still fires (one that no longer does is removed from the list)', $gone, array() );
		echo '     ' . count( $known ) . ' known findings, ' . count( $got ) . " found\n";
	}
}

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
