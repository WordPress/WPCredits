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
	// the mentor page, whose list it regroups.
	if ( ! wpcredits_is_dashboard_page() ) {
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

	wp_enqueue_style(
		'wpcredits-dashboard',
		get_theme_file_uri( 'assets/css/dashboard.css' ),
		$deps,
		WPCREDITS_VERSION
	);

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
