<?php
/**
 * Shared plugin settings.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the single settings option shared by all four modules.
 *
 * Airtable *field* names are not settings — they live as constants on the module
 * that consumes them (filterable via `wpcpm_mentors_fields`), because twenty text
 * inputs for a schema that changes once a year is worse than one filter.
 */
class WPCPM_Settings {

	const OPTION = 'wpcpm_settings';

	/**
	 * Default settings, pre-filled with the live WPCredits base coordinates.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_token'                 => '',
			'base_id'                   => 'appIzQKfwTn5dyPVp',
			'mentors_table'             => 'tblJmEYgBWYxVuzUw',
			'mentor_status'             => 'Active',
			'reports_table'             => 'tbljYkkVGbeoaWEtY',
			'students_table'            => 'tbla8GZg5x6NY7aWt',
			// The feedback surveys. One row per student, with a column per question
			// and a `F1`/`F2`/`F3`/`F4` prefix saying which stage asked it.
			'feedback_table'            => 'tblx3TH6fp4edQJDm',
			// Linked-record fields come back from the REST API as bare record IDs,
			// so these two tables are read purely to turn those IDs into names.
			'institutions_table'        => 'tbl4V0FEbzRP7I2w2',
			'teams_table'               => 'tblUBEXiS3QKUCXHf',
			'sponsors_table'            => 'tbluji8wknOZr55fa',
			// The column holding each table's display name. Used when the schema
			// endpoint is unavailable, since it is the schema that reports which
			// field is primary.
			'institutions_name_field'   => 'Name',
			'teams_name_field'          => 'Contribution teams or areas',
			'sponsors_name_field'       => 'Company Name',
			// Students a mentor is currently mentoring.
			'student_statuses'          => array( 'In Sensei', 'In Sensei 50h', 'Developer Track' ),
			// Students whose mentoring has finished. Shown in a separate, collapsed
			// section rather than mixed in with the current ones.
			'past_statuses'             => array( 'Graduate', 'Dropped out' ),
			// What to do when a mentor is no longer Active in Airtable. `revoke`
			// takes the Mentor role away but never deletes the account.
			'on_inactive'               => 'revoke',
			// Off by default: a first sync provisions ~90 accounts at once, and
			// nobody wants to discover that by way of ninety emails.
			'send_welcome_email'        => false,
			'auto_sync'                 => true,
			// The handbook assistant, and whether it exists at all. Off means no page, no
			// daily fetch of somebody else's site, and no question box — the stored copy is
			// kept, so switching it back on does not mean waiting for a sync.
			'handbook_enabled'          => true,
			// The handbook assistant. The source defaults to the WordPress Education
			// Handbook, which is an ordinary REST-enabled post type on make.wordpress.org.
			// Empty means answers are quoted from the handbook and nothing leaves the site.
			// Turning this on sends questions to a third party, so it is a deliberate act.
			'handbook_provider'         => '',
			'handbook_key'              => '',
			// An alias, deliberately. Google retired `gemini-2.0-flash` and then
			// `gemini-2.5-flash` while this was being written, and each time the symptom was
			// a refused request and no answer. `gemini-flash-latest` cannot be retired out
			// from under a site.
			'handbook_model'            => 'gemini-flash-latest',
			// Who may ask. `mentor` — mentors and program managers — is the default: the
			// handbook is written for the people running the program, and most of it describes
			// work students do not do. `program` widens it to students and institutions,
			// `any` to anybody logged in, `manage` to program managers alone.
			'handbook_access'           => 'mentor',
			// Generated answers per person per hour. The extractive answer is never limited.
			'handbook_limit'            => 20,
			// On by default: a mentor logging in otherwise lands on a wp-admin
			// screen that shows them nothing they can use.
			'mentor_home'               => true,
			// Students module, mirroring the mentors settings above.
			'student_home'              => true,
			'student_on_inactive'       => 'revoke',

			// Tool — Mentor Status Checker. Prefixed so the tool's settings stay
			// visibly separate from the modules' in one shared option.
			'checker_source_status'     => 'Vetted - positive',
			'checker_target_status'     => 'Active',
			'checker_course_slug'       => 'wordpress-credits-mentors-course',
			'checker_course_title'      => "WordPress Credits Mentor's Course",
			'checker_completion_phrase' => 'Completed the course',
			'checker_timeline_filter'   => 'meta',
			'checker_max_pages'         => 15,
			'checker_batch_size'        => 3,
			'checker_request_delay'     => 0,
			'checker_cache_ttl'         => 12 * HOUR_IN_SECONDS,
			// Off by default: promoting a mentor is a write to a shared base.
			'checker_cron_enabled'      => false,
			'checker_cron_promotes'     => false,
		);
	}

	/**
	 * Current settings, merged over defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get_value( $key, $fallback = null ) {
		$settings = self::get();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Persist a settings array after sanitising it.
	 *
	 * @param array $input Raw input, typically from $_POST.
	 * @return array The saved settings.
	 */
	public static function save( array $input ) {
		$current = self::get();
		$clean   = $current;

		// An empty token field means "leave the stored token alone" — the UI only
		// ever renders a masked placeholder, so blank must not wipe the secret.
		if ( isset( $input['api_token'] ) ) {
			$token = trim( wp_unslash( $input['api_token'] ) );
			if ( '' !== $token && ! self::is_mask( $token ) ) {
				$clean['api_token'] = sanitize_text_field( $token );
			}
		}

		foreach ( array( 'base_id', 'mentors_table', 'reports_table', 'students_table', 'feedback_table', 'institutions_table', 'teams_table', 'sponsors_table', 'institutions_name_field', 'teams_name_field', 'sponsors_name_field' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		if ( isset( $input['mentor_status'] ) ) {
			$clean['mentor_status'] = sanitize_text_field( wp_unslash( $input['mentor_status'] ) );
		}

		foreach ( array( 'student_statuses', 'past_statuses' ) as $list_key ) {
			if ( ! isset( $input[ $list_key ] ) ) {
				continue;
			}

			$raw      = wp_unslash( $input[ $list_key ] );
			$raw      = is_array( $raw ) ? $raw : explode( "\n", (string) $raw );
			$statuses = array();
			foreach ( $raw as $status ) {
				$status = sanitize_text_field( trim( $status ) );
				if ( '' !== $status ) {
					$statuses[] = $status;
				}
			}
			$clean[ $list_key ] = array_values( array_unique( $statuses ) );
		}

		$clean['on_inactive'] = ( isset( $input['on_inactive'] ) && 'keep' === $input['on_inactive'] ) ? 'keep' : 'revoke';

		// The key is write-only from the form's point of view, exactly like the Airtable
		// token: the screen shows a masked value, and submitting that mask must not
		// overwrite the real key with a row of dots.
		if ( isset( $input['handbook_key'] ) ) {
			$key = trim( wp_unslash( $input['handbook_key'] ) );

			if ( '' !== $key && false === strpos( $key, '•' ) ) {
				$clean['handbook_key'] = sanitize_text_field( $key );
			}
		}

		if ( isset( $input['handbook_provider'] ) ) {
			$provider = sanitize_key( $input['handbook_provider'] );

			$clean['handbook_provider'] = array_key_exists( $provider, WPCPM_Handbook_Answer::providers() ) ? $provider : '';
		}

		if ( isset( $input['handbook_model'] ) ) {
			$clean['handbook_model'] = sanitize_text_field( $input['handbook_model'] );
		}

		if ( isset( $input['handbook_access'] ) ) {
			$access = sanitize_key( $input['handbook_access'] );

			$clean['handbook_access'] = in_array( $access, array( 'mentor', 'program', 'any', 'manage' ), true ) ? $access : 'mentor';
		}

		if ( isset( $input['handbook_limit'] ) ) {
			$clean['handbook_limit'] = max( 0, min( 200, (int) $input['handbook_limit'] ) );
		}

		// Every boolean arrives from the handler on every save, because an unchecked checkbox
		// posts nothing and "absent" would otherwise be indistinguishable from "off".
		if ( array_key_exists( 'handbook_enabled', $input ) ) {
			$clean['handbook_enabled'] = ! empty( $input['handbook_enabled'] );
		}

		$clean['send_welcome_email'] = ! empty( $input['send_welcome_email'] );
		$clean['auto_sync']          = ! empty( $input['auto_sync'] );
		$clean['mentor_home']        = ! empty( $input['mentor_home'] );
		$clean['student_home']       = ! empty( $input['student_home'] );

		$clean['student_on_inactive'] = ( isset( $input['student_on_inactive'] ) && 'keep' === $input['student_on_inactive'] ) ? 'keep' : 'revoke';

		// Mentor Status Checker.
		foreach ( array( 'checker_source_status', 'checker_target_status', 'checker_course_slug', 'checker_course_title', 'checker_completion_phrase' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		if ( isset( $input['checker_timeline_filter'] ) ) {
			$clean['checker_timeline_filter'] = ( 'all' === $input['checker_timeline_filter'] ) ? 'all' : 'meta';
		}

		// Clamped rather than merely cast: a zero page cap would report every
		// mentor as unresolvable, and a zero batch size would stall the run.
		$limits = array(
			'checker_max_pages'     => array( 1, 100 ),
			'checker_batch_size'    => array( 1, 25 ),
			'checker_request_delay' => array( 0, 5000 ),
			'checker_cache_ttl'     => array( 0, MONTH_IN_SECONDS ),
		);

		foreach ( $limits as $key => $range ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = max( $range[0], min( $range[1], (int) $input[ $key ] ) );
			}
		}

		$clean['checker_cron_enabled']  = ! empty( $input['checker_cron_enabled'] );
		$clean['checker_cron_promotes'] = ! empty( $input['checker_cron_promotes'] );

		update_option( self::OPTION, $clean );

		// Keep the weekly schedule in step with the setting that governs it.
		if ( class_exists( 'WPCPM_Mentor_Checker_Runner' ) ) {
			WPCPM_Mentor_Checker_Runner::sync_cron( $clean['checker_cron_enabled'] );
		}

		return $clean;
	}

	/**
	 * Whether the Airtable connection is configured well enough to try.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		$settings = self::get();

		return ! empty( $settings['api_token'] ) && ! empty( $settings['base_id'] );
	}

	/**
	 * A masked rendering of the stored token, safe to print in the admin.
	 *
	 * @return string
	 */
	/**
	 * The handbook provider's key, masked for display.
	 *
	 * @return string
	 */
	public static function masked_handbook_key() {
		$key = (string) self::get_value( 'handbook_key', '' );

		if ( '' === $key ) {
			return '';
		}

		return str_repeat( '•', 8 ) . substr( $key, -4 );
	}

	/**
	 * The stored Airtable token, safe to print.
	 *
	 * Last four characters only, which is enough to tell one token from another when checking
	 * whether the right one is in place, and not enough to use.
	 *
	 * @return string Masked token, or an empty string if none is stored.
	 */
	public static function masked_token() {
		$token = (string) self::get_value( 'api_token', '' );

		if ( '' === $token ) {
			return '';
		}

		return str_repeat( '•', 12 ) . substr( $token, -4 );
	}

	/**
	 * Detect the masked placeholder coming back on submit.
	 *
	 * @param string $value Submitted value.
	 * @return bool
	 */
	private static function is_mask( $value ) {
		return (bool) preg_match( '/^\x{2022}+/u', $value );
	}
}
