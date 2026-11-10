<?php
/**
 * Which Airtable token a sync uses.
 *
 * The plugin's own token, when one is saved. Otherwise, on a site that also runs the WPCredits
 * Program Manager, that plugin's token: both read the same base, so the site keeps one token to
 * look after. The borrowed token is read when a sync needs it and is never copied into this
 * plugin's settings.
 *
 * @package WPCredits_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A token out of a settings option, or '' when the option or the key is not a usable string.
 *
 * @param string $option The option's name.
 * @param string $key    The key that holds the token.
 * @return string
 */
function wpct_token_from_option( $option, $key ) {
	$settings = get_option( $option, array() );
	if ( ! is_array( $settings ) || ! isset( $settings[ $key ] ) || ! is_string( $settings[ $key ] ) ) {
		return '';
	}
	return trim( $settings[ $key ] );
}

/**
 * Where the token comes from: 'own', 'manager' (the WPCredits Program Manager's), or '' for none.
 *
 * @return string
 */
function wpct_airtable_token_source() {
	if ( '' !== wpct_token_from_option( WPCT_OPT_SETTINGS, 'airtable_pat' ) ) {
		return 'own';
	}
	if ( '' !== wpct_token_from_option( 'wpcpm_settings', 'api_token' ) ) {
		return 'manager';
	}
	return '';
}

/**
 * The token a sync sends to Airtable, or '' when there is none.
 *
 * @return string
 */
function wpct_airtable_token() {
	$source = wpct_airtable_token_source();
	if ( 'own' === $source ) {
		$token = wpct_token_from_option( WPCT_OPT_SETTINGS, 'airtable_pat' );
	} elseif ( 'manager' === $source ) {
		$token = wpct_token_from_option( 'wpcpm_settings', 'api_token' );
	} else {
		$token = '';
	}
	$token = apply_filters( 'wpct_airtable_token', $token, $source );
	return is_string( $token ) ? $token : '';
}
