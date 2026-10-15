<?php
/**
 * Server render for a program figure.
 *
 * **The number on the landing page was typed in.** It said 92 mentors; the site had 88, and nothing
 * would ever have said so - a figure nobody can see going stale is the kind that gets quoted in a
 * pitch deck a year later.
 *
 * Counted from the accounts holding the program role, which is the same thing the Mentors screen in
 * wp-admin reports. The sync creates an account for every mentor Airtable lists as active and takes
 * the role away again when they stop being listed, so the count follows Airtable without this page
 * having to ask Airtable anything.
 *
 * Cached for an hour: the number only moves when a sync runs, and the landing page is the most
 * requested page on the site.
 *
 * The block also shows the program's three outcome figures and their source line, for the Education
 * Hub's hero (theme 1.24.15): `graduation`, `recommend`, `keep` and `outcomes-source`, read from the
 * plugin through `wpcredits_outcome_figures()` and drawn by `wpcredits_outcome_stat_html()`.
 *
 * @package WPCredits_Theme
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpcredits_roles = array(
	'mentors'      => 'wpcpm_mentor',
	'students'     => 'wpcpm_student',
	'institutions' => 'wpcpm_institution',
	'sponsors'     => 'wpcpm_sponsor',
);

$wpcredits_stat     = isset( $attributes['stat'] ) ? (string) $attributes['stat'] : 'mentors';
$wpcredits_fallback = isset( $attributes['fallback'] ) ? trim( (string) $attributes['fallback'] ) : '';
$wpcredits_count    = null;

// The program's outcome figures and their source line (theme 1.24.15): counted by the plugin with the
// public impact dashboard's definitions and never typed in, so `fallback` is not read for them, and a
// figure that may not be shown prints nothing at all, its label and its base with it. Every other
// stat is a count, drawn below as it always was.
$wpcredits_outcome = wpcredits_outcome_stat_html( $wpcredits_stat, isset( $attributes['label'] ) ? (string) $attributes['label'] : '' ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- WordPress hands a block's render file its attributes.

if ( null !== $wpcredits_outcome ) {
	echo $wpcredits_outcome; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.

	return;
}

if ( isset( $wpcredits_roles[ $wpcredits_stat ] ) && wpcredits_plugin_active() ) {
	$wpcredits_count = wpcredits_program_count( $wpcredits_roles[ $wpcredits_stat ] );
}

// A zero is almost always "the sync has not run here yet" rather than a program with no mentors, and
// a landing page that opens with 0 is worse than one showing the last figure somebody checked.
$wpcredits_value = ( null !== $wpcredits_count && $wpcredits_count > 0 )
	? number_format_i18n( $wpcredits_count )
	: $wpcredits_fallback;

if ( '' === $wpcredits_value ) {
	return;
}

printf(
	'<p %1$s>%2$s</p>',
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core.
	get_block_wrapper_attributes(),
	esc_html( $wpcredits_value )
);
