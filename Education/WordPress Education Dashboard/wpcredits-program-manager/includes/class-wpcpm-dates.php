<?php
/**
 * A date or a moment, printed in the site's own format.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one way a page prints a date: the Date Format and Time Format the site's Settings hold.
 *
 * A sentence a school reads shows a date the way the site's Settings say and never as the
 * year-month-day a database stores, so the Institution Dashboard's sentences and the dates of an
 * institution's invitation, in its email and on the page its link opens, take their dates from here.
 *
 * Two kinds of value come in, and they are read differently on purpose. A stored day has no
 * time zone (`2026-09-03`, or the first ten characters of a post's `post_date`), so it is the
 * day it names wherever the site sits: turned into a time at midnight and shown on a clock
 * west of UTC it would be the day before. A Unix time is a moment, and a moment is shown on
 * the site's clock, which can be a different day from the one it falls on in UTC.
 *
 * All static, no state, beside `WPCPM_Cohort`.
 */
class WPCPM_Dates {

	/** WordPress's own default for `date_format`, for a site whose option is gone. */
	const DEFAULT_DATE_FORMAT = 'F j, Y';

	/** WordPress's own default for `time_format`. */
	const DEFAULT_TIME_FORMAT = 'g:i a';

	/**
	 * A day, in the site's date format.
	 *
	 * A string is read as `WPCPM_Mentors_Dashboard::format_dates()` reads one: the calendar day
	 * it names, set at UTC midnight and handed to `date_i18n()`, which puts the same wall clock
	 * on the site's zone, so no time zone moves it. It has to be a day that exists, so
	 * `2026-02-31` is nothing rather than March. An integer is a Unix time and is shown on the
	 * site's clock with `wp_date()`.
	 *
	 * @param mixed $value A `Y-m-d` string, a longer one whose first ten characters are one (a
	 *                     post's `post_date`), or a Unix time.
	 * @return string The day, or an empty string when the value is empty or unreadable.
	 */
	public static function day( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? (string) wp_date( self::date_format(), $value ) : '';
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?!\d)/', trim( $value ), $m ) ) {
			return '';
		}

		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}

		return (string) date_i18n( self::date_format(), gmmktime( 0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] ) );
	}

	/**
	 * A moment, in the site's date and time formats joined by a space, on the site's clock.
	 *
	 * @param mixed $time A Unix time.
	 * @return string The moment, or an empty string for zero or anything that is not a time.
	 */
	public static function moment( $time ) {
		$time = is_numeric( $time ) ? (int) $time : 0;

		if ( $time <= 0 ) {
			return '';
		}

		return (string) wp_date( self::date_format() . ' ' . self::time_format(), $time );
	}

	/**
	 * The site's date format.
	 *
	 * @return string
	 */
	private static function date_format() {
		$format = get_option( 'date_format' );

		return ( is_string( $format ) && '' !== $format ) ? $format : self::DEFAULT_DATE_FORMAT;
	}

	/**
	 * The site's time format.
	 *
	 * @return string
	 */
	private static function time_format() {
		$format = get_option( 'time_format' );

		return ( is_string( $format ) && '' !== $format ) ? $format : self::DEFAULT_TIME_FORMAT;
	}
}
