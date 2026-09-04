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
