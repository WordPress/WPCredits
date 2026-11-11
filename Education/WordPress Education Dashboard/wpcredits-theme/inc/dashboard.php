<?php
/**
 * The mentor dashboard skin.
 *
 * The plugin renders the page and, since its 1.64.0, groups and searches it too. All this file
 * does now is load the stylesheets that dress the result - the theme's own richer treatment over
 * the baseline the plugin ships.
 *
 * It used to own the triage script and the payload behind it, which meant a theme change took the
 * grouping, the counts and the search with it. Nothing here re-renders a student, invents a field
 * or writes anything.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The Slack link used to be the theme's job, and is not any more.
 *
 * The plugin renders the handle as a link itself, to a target its own
 * `wpcpm_slack_url` filter owns. The script's `linkSlack()` had become unreachable
 * either way - it bailed when the cell already held an `<a>`, and bailed again when
 * the cell was "Not set" - so it was doing nothing but keeping a reason to call
 * `__()` against the *plugin's* text domain, which a theme should not do: the string
 * is not in the theme's own catalog, and matching a translated label to find a
 * table row breaks on any wording change.
 *
 * `wpcredits_slack_chat_url()` and its filter went with it. Filter
 * `wpcpm_slack_url` in the plugin instead.
 */





/**
 * Load the skin and the script on any dashboard page.
 *
 * Registered at priority 20 so the plugin's own stylesheet is already registered
 * and can be declared a dependency. Without that the plugin's CSS - enqueued from
 * inside its render callback, and therefore printed late - would land after this
 * one and win every tie.
 */
function wpcredits_dashboard_assets() {
	// Styles dress the shell both pages share; the script only has work to do on
	// the mentor page, whose list it regroups. The Administrator guide in the frame is no dashboard,
	// but its bare canvas is dressed by the frame's section of this sheet, and set in the same faces.
	if ( ! wpcredits_is_dashboard_page() && ! wpcredits_is_frame_page() ) {
		return;
	}

	$deps = array( 'wpcredits-style' );

	if ( wp_style_is( WPCPM_Mentors_Dashboard::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Mentors_Dashboard::STYLE;
	}

	if ( class_exists( 'WPCPM_Students_Dashboard' ) && wp_style_is( WPCPM_Students_Dashboard::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Students_Dashboard::STYLE;
	}

	if ( class_exists( 'WPCPM_Institutions_Dashboard' ) && wp_style_is( WPCPM_Institutions_Dashboard::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Institutions_Dashboard::STYLE;
	}

	if ( class_exists( 'WPCPM_Administrators_Dashboard' ) && wp_style_is( WPCPM_Administrators_Dashboard::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Administrators_Dashboard::STYLE;
	}

	if ( class_exists( 'WPCPM_Sponsors_Dashboard' ) && wp_style_is( WPCPM_Sponsors_Dashboard::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Sponsors_Dashboard::STYLE;
	}

	// The call calendar, for the same reason as the ones above - and it is the sharpest
	// case of it. The plugin enqueues this one from inside a render callback that runs
	// during `the_content`, long after this hook, so without the dependency it prints
	// *after* the theme's sheet and wins every tie. Nothing looks wrong today only
	// because every theme rule for the calendar is prefixed `.wpc-dashboard-page` and
	// outweighs the plugin's single class; one unprefixed rule added later would lose
	// silently, which is the worst way to find this out.
	if ( class_exists( 'WPCPM_Call_Calendar' ) && wp_style_is( WPCPM_Call_Calendar::STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Call_Calendar::STYLE;
	}

	// The triage sheet, for the same kind of tie: this theme's `.wpc-dash__band { margin-bottom:
	// 0 }` beats triage.css's own `margin: 0 0 1em` only by print order today, and the dependency
	// makes that a certainty rather than an accident. Every selector in triage.css is prefixed
	// `.wpc-dash`, `.wpc-triage` or `.wpc-row`, except the ones the triage script itself
	// assembles at run time (`.wpc-search*`, `.wpc-group*`) - nothing else in the plugin or the
	// theme ever prints that markup, so this costs nothing on a page the triage script never
	// runs on. The sheet now loads on every dashboard page, as the calendar's already does.
	if ( class_exists( 'WPCPM_Mentors_Dashboard' ) && wp_style_is( WPCPM_Mentors_Dashboard::TRIAGE_STYLE, 'registered' ) ) {
		$deps[] = WPCPM_Mentors_Dashboard::TRIAGE_STYLE;
	}

	wp_enqueue_style(
		'wpcredits-dashboard',
		get_theme_file_uri( 'assets/css/dashboard.css' ),
		$deps,
		WPCREDITS_VERSION
	);

	// A dashboard in the plugin's frame is set in the homepage's two typefaces, Inter and EB Garamond for
	// its head and figures (the version 3 concepts of 10 November 2026). Their faces are declared in the
	// hub's sheet, whose every other rule is scoped to the hub's own classes.
	if ( wpcredits_is_frame_page() ) {
		wp_enqueue_style( 'wpcredits-hub' );
	}

	wp_enqueue_style(
		'wpcredits-dashboard-print',
		get_theme_file_uri( 'assets/css/print.css' ),
		array( 'wpcredits-dashboard' ),
		WPCREDITS_VERSION,
		'print'
	);

	// The triage, the counts and the search used to be enqueued here. They moved into
	// wpcredits-program-manager 1.64.0, which is where the list they regroup is rendered - a
	// theme carrying that meant a theme change took the feature with it. This file dresses the
	// result; it no longer decides whether there is one.
}
add_action( 'wp_enqueue_scripts', 'wpcredits_dashboard_assets', 20 );

/**
 * Lift the administrators' "Viewing as" switcher out of the dashboard card and above it.
 *
 * The plugin prints the switcher inside its dashboard block, so it lands inside the
 * `.wpc-dash` card between the title and the page. The owner wants it outside, above the card
 * (theme 1.23.5): it is a tool for administrators, not part of the page a student or a mentor
 * sees. Done here, on the group block's rendered markup, rather than in the plugin: where the
 * card begins is this theme's arrangement, and a site that does not dress these pages keeps
 * the switcher where the plugin put it. The form is one self-contained element with no form
 * inside it, so the first `</form>` after its opening tag closes it.
 *
 * @param string $content The block's markup.
 * @param array  $block   The parsed block.
 * @return string
 */
function wpcredits_lift_switcher( $content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/group' !== $block['blockName'] ) {
		return $content;
	}

	$classes = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';

	if ( ! in_array( 'wpc-dash', preg_split( '/\s+/', $classes ), true ) || false === strpos( $content, 'wpcpm-dashboard__switcher' ) ) {
		return $content;
	}

	if ( ! preg_match( '#<form class="wpcpm-dashboard__switcher"[^>]*>.*?</form>#s', $content, $found, PREG_OFFSET_CAPTURE ) ) {
		return $content;
	}

	$form    = $found[0][0];
	$content = substr_replace( $content, '', $found[0][1], strlen( $form ) );

	return '<div class="wp-block-group alignwide wpc-dash__switcher">' . $form . '</div>' . $content;
}
add_filter( 'render_block', 'wpcredits_lift_switcher', 10, 2 );



/**
 * Whether the handbook assistant is available to whoever is reading this page.
 *
 * One question, asked of the plugin, rather than the theme reimplementing the answer. The
 * plugin's own check already covers the assistant being switched off, the handbook never
 * having been synced, the reader being logged out and the configured audience - and any of
 * those reimplemented here would be a second copy to keep in step, which is exactly how the
 * mentor-page mismatch happened.
 *
 * The guard is `method_exists`, not `class_exists`: the class has existed since the
 * assistant shipped, but a site on an older plugin has it without this method.
 *
 * @return bool
 */
function wpcredits_handbook_available() {
	if ( ! method_exists( 'WPCPM_Handbook_Assistant', 'is_available' ) ) {
		return false;
	}

	return (bool) WPCPM_Handbook_Assistant::is_available();
}

/**
 * Whether the page being viewed is one the plugin draws in its frame, on this theme's bare canvas: the
 * pages `add_theme_support( 'wpcpm-dashboard-frame' )` names in functions.php.
 *
 * @return bool
 */
function wpcredits_is_frame_page() {
	return ( wpcredits_frame_names( 'administrator' ) && wpcredits_is_administrator_page() )
		|| ( wpcredits_frame_names( 'sponsor' ) && wpcredits_is_sponsor_page() )
		|| wpcredits_is_guide_frame_page()
		|| wpcredits_is_update_frame_page();
}

/**
 * Whether functions.php names a page as one the plugin draws in its frame.
 *
 * @param string $page The page's key: `administrator`, `administrator-guide`.
 * @return bool
 */
function wpcredits_frame_names( $page ) {
	$support = get_theme_support( 'wpcpm-dashboard-frame' );
	$pages   = is_array( $support ) && isset( $support[0] ) ? (array) $support[0] : array();

	return in_array( $page, $pages, true );
}

/**
 * The frame a guide being viewed is drawn in, as functions.php names it: `administrator-guide` for the
 * Administrator guide (plugin 1.123.70), `sponsor-guide` for the Sponsor guide (plugin 1.123.78), or '' for
 * any other page. Only while a plugin that can draw it is active and draws it for this reader, so a site
 * without one, and a reader the plugin does not draw it for, keep the guide on the Documentation template
 * the page names.
 *
 * @return string
 */
function wpcredits_guide_frame() {
	if ( ! is_page() || ! class_exists( 'WPCPM_Administrator_Guide_View' ) ) {
		return '';
	}

	$post = get_queried_object();

	if ( method_exists( 'WPCPM_Administrator_Guide_View', 'frame_for' ) ) {
		$frame = (string) WPCPM_Administrator_Guide_View::frame_for( $post );
	} else {
		// A plugin before 1.123.78 draws the Administrator guide alone.
		$frame = WPCPM_Administrator_Guide_View::is_guide( $post ) ? 'administrator-guide' : '';
	}

	return '' !== $frame && wpcredits_frame_names( $frame ) ? $frame : '';
}

/**
 * Whether the page being viewed is a guide drawn in its dashboard's frame.
 *
 * @return bool
 */
function wpcredits_is_guide_frame_page() {
	return '' !== wpcredits_guide_frame();
}

/**
 * Draw a guide on its bare canvas, `page-administrator-guide` or `page-sponsor-guide`, ahead of the
 * Documentation template the page itself names. The page keeps naming it, so the day the plugin cannot draw
 * the frame the guide is a Documentation page again, with nothing to change on it.
 *
 * @param string[] $templates The page's templates, the first found wins.
 * @return string[]
 */
function wpcredits_guide_frame_template( $templates ) {
	$frame = wpcredits_guide_frame();

	if ( '' === $frame ) {
		return $templates;
	}

	// Moved, not added: the page's slug already puts `page-<slug>.php` in the list, after the stored
	// template, and core orders block templates by `array_flip()` of the list, where the last of two equal
	// entries wins. A second copy at the front would lose to the stored template.
	$canvas    = 'page-' . $frame . '.php';
	$templates = array_values( array_diff( $templates, array( $canvas ) ) );
	array_unshift( $templates, $canvas );

	return $templates;
}
add_filter( 'page_template_hierarchy', 'wpcredits_guide_frame_template' );

/**
 * Whose frame a program update being viewed is drawn in: `administrator` (plugin 1.123.71), `sponsor`
 * (plugin 1.123.78), or '' for any other post. The plugin decides, post by post and reader by reader: a
 * public update is read by everybody, and only an Administrator, or somebody who acts for a sponsor, gets a
 * frame.
 *
 * @return string
 */
function wpcredits_update_frame() {
	if ( ! is_single() || ! class_exists( 'WPCPM_Administrator_Updates_View' ) ) {
		return '';
	}

	$post = get_queried_object();

	if ( method_exists( 'WPCPM_Administrator_Updates_View', 'audience_of' ) ) {
		$audience = (string) WPCPM_Administrator_Updates_View::audience_of( $post );
	} else {
		// A plugin before 1.123.78 draws an Administrator's updates alone.
		$audience = WPCPM_Administrator_Updates_View::draws( $post ) ? 'administrator' : '';
	}

	return '' !== $audience && wpcredits_frame_names( $audience . '-updates' ) ? $audience : '';
}

/**
 * Whether the post being viewed is a program update the plugin draws in a dashboard's frame.
 *
 * @return bool
 */
function wpcredits_is_update_frame_page() {
	return '' !== wpcredits_update_frame();
}

/**
 * Draw such an update on its bare canvas, `single-administrator-update` or `single-sponsor-update`, ahead of
 * every template core would pick for the post.
 *
 * @param string[] $templates The post's templates, the first found wins.
 * @return string[]
 */
function wpcredits_update_frame_template( $templates ) {
	$audience = wpcredits_update_frame();

	if ( '' === $audience ) {
		return $templates;
	}

	$canvas    = 'single-' . $audience . '-update.php';
	$templates = array_values( array_diff( $templates, array( $canvas ) ) );
	array_unshift( $templates, $canvas );

	return $templates;
}
add_filter( 'single_template_hierarchy', 'wpcredits_update_frame_template' );
