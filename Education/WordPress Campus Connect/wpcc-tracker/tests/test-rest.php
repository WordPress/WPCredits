<?php
/**
 * WPCCT_Rest's two pure pieces: the payload the totals route returns, and the
 * token check that guards it. The route itself needs WordPress; these do not.
 *
 * Required by run-tests.php, which must already have loaded harness.php.
 *
 * @package WPCC_Tracker
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/fake-wp/' );

require_once __DIR__ . '/../includes/class-wpcct-rest.php';

$wpcct_blob = array(
	'source_synced'     => '2026-10-26T20:36:55+00:00',
	'totals'            => array(
		'completed'           => 80,
		'completed_this_year' => 60,
		'scheduled'           => 11,
		'planning'            => 52,
		'attendees'           => 7535,
		'institutions'        => 134,
		'countries'           => 25,
		'excluded'            => 9,
	),
	'unmapped_statuses' => array(),
	'events'            => array( array( 'name' => 'must not leak' ) ),
);

$wpcct_p = WPCCT_Rest::payload( $wpcct_blob, gmmktime( 12, 0, 0, 10, 28, 2026 ) );

t_eq(
	array_keys( $wpcct_p ),
	array( 'completed', 'completed_this_year', 'scheduled', 'planning', 'attendees', 'institutions', 'countries', 'year', 'as_of', 'source_synced', 'unmapped_statuses' ),
	'rest: payload keys match the SSH reading, in order'
);
t_eq( $wpcct_p['attendees'], 7535, 'rest: totals carried through as integers' );
t_eq( $wpcct_p['as_of'], '26 October 2026', 'rest: as_of is the source sync date' );
t_eq( $wpcct_p['year'], '2026', 'rest: year comes from now, not the sync' );
t_ok( ! isset( $wpcct_p['events'] ) && ! isset( $wpcct_p['excluded'] ), 'rest: nothing beyond the headline totals is returned' );

t_eq( WPCCT_Rest::payload( array(), time() ), null, 'rest: no totals gives null, not zeros' );
t_eq( WPCCT_Rest::payload( false, time() ), null, 'rest: a missing option gives null' );

$wpcct_undated = WPCCT_Rest::payload( array( 'totals' => array( 'completed' => 1 ) ), time() );
t_eq( $wpcct_undated['as_of'], '', 'rest: no source date gives an empty as_of, not 1 January 1970' );
t_eq( $wpcct_undated['scheduled'], 0, 'rest: a missing total reads 0 once some totals exist' );

$wpcct_hash = hash( 'sha256', 'right-token' );
t_ok( WPCCT_Rest::token_matches( 'right-token', $wpcct_hash ), 'rest: the current token is accepted' );
t_ok( ! WPCCT_Rest::token_matches( 'wrong-token', $wpcct_hash ), 'rest: another token is refused' );
t_ok( ! WPCCT_Rest::token_matches( '', $wpcct_hash ), 'rest: an empty header is refused' );
t_ok( ! WPCCT_Rest::token_matches( '', '' ), 'rest: with no token set, an empty header is refused' );
t_ok( ! WPCCT_Rest::token_matches( 'anything', '' ), 'rest: with no token set, nothing is accepted' );
t_ok( ! WPCCT_Rest::token_matches( $wpcct_hash, $wpcct_hash ), 'rest: the stored hash itself is not a token' );
