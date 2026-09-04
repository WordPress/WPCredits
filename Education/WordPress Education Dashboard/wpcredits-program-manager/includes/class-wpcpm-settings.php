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
 * Airtable *field* names are not settings - they live as constants on the module
 * that consumes them (filterable via `wpcpm_mentors_fields`), because twenty text
 * inputs for a schema that changes once a year is worse than one filter.
 */
class WPCPM_Settings {

	const OPT_NAME = 'wpcpm_settings';

	/** Option holding the settings schema version, so `maybe_upgrade()` runs once per change. */
	const OPT_VERSION = 'wpcpm_settings_version';

	/**
	 * Bump this when a *saved* option has to be migrated rather than merely defaulted.
	 *
	 * 2: `Paused` and `Pending graduation` joined `student_statuses`.
	 */
	const SETTINGS_VERSION = 2;

	/**
	 * Default settings, pre-filled with the live WPCredits base coordinates.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_token'                     => '',
			'base_id'                       => 'appIzQKfwTn5dyPVp',
			'mentors_table'                 => 'tblJmEYgBWYxVuzUw',
			'mentor_status'                 => 'Active',
			'reports_table'                 => 'tbljYkkVGbeoaWEtY',
			'students_table'                => 'tbla8GZg5x6NY7aWt',
			'tutors_table'                  => 'tbllW9yWhbg1UCHVy',
			// The feedback surveys. One row per student, with a column per question
			// and a `F1`/`F2`/`F3`/`F4` prefix saying which stage asked it.
			'feedback_table'                => 'tblx3TH6fp4edQJDm',
			// Linked-record fields come back from the REST API as bare record IDs,
			// so these two tables are read purely to turn those IDs into names.
			'institutions_table'            => 'tbl4V0FEbzRP7I2w2',
			'teams_table'                   => 'tblUBEXiS3QKUCXHf',
			'sponsors_table'                => 'tbluji8wknOZr55fa',
			// The column holding each table's display name. Used when the schema
			// endpoint is unavailable, since it is the schema that reports which
			// field is primary.
			'institutions_name_field'       => 'Name',
			'teams_name_field'              => 'Contribution teams or areas',
			'sponsors_name_field'           => 'Company Name',
			// Students a mentor is currently mentoring. `Paused` and `Pending graduation`
			// count as current: both syncs build their Airtable formula from this list,
			// so a status missing here is a student nobody fetches (see `maybe_upgrade()`).
			'student_statuses'              => array( 'In Sensei', 'In Sensei 50h', 'Developer Track', 'Paused', 'Pending graduation' ),
			// Students whose mentoring has finished. Shown in a separate, collapsed
			// section rather than mixed in with the current ones.
			'past_statuses'                 => array( 'Graduate', 'Dropped out' ),
			// What to do when a mentor is no longer Active in Airtable. `revoke`
			// takes the Mentor role away but never deletes the account.
			'on_inactive'                   => 'revoke',
			// Off by default: a first sync provisions ~90 accounts at once, and
			// nobody wants to discover that by way of ninety emails.
			'send_welcome_email'            => false,
			'auto_sync'                     => true,
			// The handbook assistant, and whether it exists at all. Off means no page, no
			// daily fetch of somebody else's site, and no question box - the stored copy is
			// kept, so switching it back on does not mean waiting for a sync.
			'handbook_enabled'              => true,
			// The handbook assistant. The source defaults to the WordPress Education
			// Handbook, which is an ordinary REST-enabled post type on make.wordpress.org.
			// Empty means answers are quoted from the handbook and nothing leaves the site.
			// Turning this on sends questions to a third party, so it is a deliberate act.
			'handbook_provider'             => '',
			'handbook_key'                  => '',
			// An alias, deliberately. Google retired `gemini-2.0-flash` and then
			// `gemini-2.5-flash` while this was being written, and each time the symptom was
			// a refused request and no answer. `gemini-flash-latest` cannot be retired out
			// from under a site.
			'handbook_model'                => 'gemini-flash-latest',
			// Who may ask. `mentor` - mentors and program managers - is the default: the
			// handbook is written for the people running the program, and most of it describes
			// work students do not do. `program` widens it to students and institutions,
			// `any` to anybody logged in, `manage` to program managers alone.
			'handbook_access'               => 'mentor',
			// Generated answers per person per hour. The extractive answer is never limited.
			'handbook_limit'                => 20,
			// On by default: a mentor logging in otherwise lands on a wp-admin
			// screen that shows them nothing they can use.
			'mentor_home'                   => true,
			// Students module, mirroring the mentors settings above.
			'student_home'                  => true,
			'student_on_inactive'           => 'revoke',

			// Institutions module. The countries table is read for the same reason as
			// the three lookup tables above: an institution's country arrives as a bare
			// record ID, and it is the country that says which manager looks after it.
			'countries_table'               => 'tbltB7GSRoTtSi4Ps',
			'countries_name_field'          => 'Name',
			// The `Current Stage` an approved application is created at, and the stages
			// that count as being in the pipeline. An institution whose stage leaves this
			// list is treated like a mentor who is no longer Active.
			'institution_new_stage'         => 'First Contact Made',
			'institution_active_stages'     => array( 'First Contact Made', 'Info Sent', 'Waiting on Reply', 'Under Review', 'Call Scheduled', 'Agreement Sent', 'Confirmed', 'Student' ),
			// Off by default for the same reason as the welcome email: a sync that
			// creates accounts should do so because somebody asked it to, not because
			// the files were updated.
			'institution_provision'         => false,
			'institution_on_inactive'       => 'revoke',
			'institution_home'              => true,
			// The public application form. Off until the page that hosts it exists,
			// because on means accepting submissions from anybody on the internet.
			'applications_enabled'          => false,
			// How long each kind of application is kept, in days. Spam goes quickly,
			// a rejection stays long enough to recognise the same institution applying
			// again, and approved ones are kept for ever (0): they are the audit trail
			// of who was let in.
			'application_spam_days'         => 30,
			'application_rejected_days'     => 365,
			'application_approved_days'     => 0,
			// The connecting address whose forwarded header the application form believes, or
			// empty for none. Empty is right on this host, where `REMOTE_ADDR` is the client.
			'application_trusted_proxy'     => '',
			// The signed-agreement upload. The size cap and the daily count per
			// institution are what stops a Subscriber-based account filling the disk.
			// `agreement_notify` is who hears about an upload, comma-separated; empty
			// means every account that can manage the program, so the queue is never
			// silently nobody's job. An upload nobody has looked at for
			// `agreement_review_days` is overdue and goes in the reminder; a withdrawn
			// or returned file is deleted after `agreement_discard_days`, an accepted
			// one never, because it is the agreement.
			'agreement_max_mb'              => 10,
			'agreement_uploads_per_day'     => 5,
			// Template generations per institution per day. Each one is a post, an Airtable
			// write and a document, and ten is more than getting the name right takes.
			'agreement_generations_per_day' => 10,
			'agreement_review_days'         => 3,
			'agreement_notify'              => '',
			// The semester report approval flow (design of 4 September 2026): the daily
			// drafting job, how long a semester with unfinished rows waits, who hears of a draft.
			'report_autodraft'              => true,
			'report_autodraft_grace_days'   => 45,
			'report_notify'                 => '',
			// Where the wording actually lives, for the manager screen's link and for the drift
			// check that compares the plugin's copy against it. Deliberately empty by default and
			// deliberately not in the code: the document is editable by anyone holding its link,
			// so the link belongs on the site rather than in a public repository.
			'agreement_doc_url'             => '',
			'agreement_discard_days'        => 30,
			// Roster import by institutions. Off until it has run on the pilot, since
			// every import is a write to the shared base.
			'import_enabled'                => false,
			// Days an invitation to join an institution's account is kept once it has
			// lapsed, so a manager can still see who was invited and never came.
			'invite_retention_days'         => 30,

			// Which roles must present a second factor at login, laid over the Two Factor
			// plugin. Administrators and institutions by default: those two see other people's
			// data, and both are small enough groups to help one by one. Mentors are a
			// deliberate omission until the program tells them it is coming, and students are
			// left to choose for themselves. An empty list requires it of nobody.
			'two_factor_roles'              => array( 'administrator', 'wpcpm_institution' ),

			// Tool - Mentor Status Checker. Prefixed so the tool's settings stay
			// visibly separate from the modules' in one shared option.
			'checker_source_status'         => 'Vetted - positive',
			'checker_target_status'         => 'Active',
			'checker_course_slug'           => 'wordpress-credits-mentors-course',
			'checker_course_title'          => "WordPress Credits Mentor's Course",
			'checker_completion_phrase'     => 'Completed the course',
			'checker_timeline_filter'       => 'meta',
			'checker_max_pages'             => 15,
			'checker_batch_size'            => 3,
			'checker_request_delay'         => 0,
			'checker_cache_ttl'             => 12 * HOUR_IN_SECONDS,
			// Off by default: promoting a mentor is a write to a shared base.
			'checker_cron_enabled'          => false,
			'checker_cron_promotes'         => false,
		);
	}

	/**
	 * Current settings, merged over defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPT_NAME, array() );

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

		// An empty token field means "leave the stored token alone" - the UI only
		// ever renders a masked placeholder, so blank must not wipe the secret.
		if ( isset( $input['api_token'] ) ) {
			$token = trim( wp_unslash( $input['api_token'] ) );
			if ( '' !== $token && ! self::is_mask( $token ) ) {
				$clean['api_token'] = sanitize_text_field( $token );
			}
		}

		foreach ( array( 'base_id', 'mentors_table', 'reports_table', 'students_table', 'tutors_table', 'feedback_table', 'institutions_table', 'teams_table', 'sponsors_table', 'countries_table', 'institutions_name_field', 'teams_name_field', 'sponsors_name_field', 'countries_name_field', 'institution_new_stage' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		if ( isset( $input['mentor_status'] ) ) {
			$clean['mentor_status'] = sanitize_text_field( wp_unslash( $input['mentor_status'] ) );
		}

		foreach ( array( 'student_statuses', 'past_statuses', 'institution_active_stages', 'two_factor_roles' ) as $list_key ) {
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

		// Institutions module.
		$clean['institution_on_inactive'] = ( isset( $input['institution_on_inactive'] ) && 'keep' === $input['institution_on_inactive'] ) ? 'keep' : 'revoke';

		// Who hears about an agreement upload: addresses one per line or comma-separated,
		// whichever the manager typed. Anything that is not an address is dropped rather
		// than kept, because a bad recipient here fails the one message that most needs
		// to arrive, and an empty result falls back to every program manager.
		if ( isset( $input['agreement_doc_url'] ) ) {
			$url  = esc_url_raw( trim( wp_unslash( $input['agreement_doc_url'] ) ), array( 'https' ) );
			$host = $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) : '';

			// Google only, and https only: this value is rendered as a link on an admin screen,
			// and a free-text URL field there is a redirect waiting to happen.
			$clean['agreement_doc_url'] = in_array( $host, array( 'docs.google.com', 'drive.google.com' ), true ) ? $url : '';
		}

		foreach ( array( 'agreement_notify', 'report_notify' ) as $notify_key ) {
			if ( ! isset( $input[ $notify_key ] ) ) {
				continue;
			}

			$raw       = wp_unslash( $input[ $notify_key ] );
			$raw       = is_array( $raw ) ? $raw : preg_split( '/[\s,]+/', (string) $raw );
			$addresses = array();
			foreach ( $raw as $address ) {
				if ( ! is_string( $address ) ) {
					continue;
				}

				// Lowercased before the de-duplication below: an address is one mailbox however
				// it was typed, and the notice must not reach it twice.
				$address = sanitize_email( strtolower( trim( $address ) ) );
				if ( '' !== $address && is_email( $address ) ) {
					$addresses[] = $address;
				}
			}
			$clean[ $notify_key ] = implode( ',', array_unique( $addresses ) );
		}

		// The one connecting address whose forwarded header the application form believes.
		// The form's per-address ceiling keys on the client address, and a header anybody can
		// send is not an address: only the edge itself may say who is behind it. On this host
		// `REMOTE_ADDR` is already the client (design spec, open question 9), so the default is
		// empty and no header is trusted. Anything but an IP is dropped rather than kept: the
		// value is compared with `REMOTE_ADDR`, and one that can never match is an empty
		// setting that looks set.
		if ( isset( $input['application_trusted_proxy'] ) ) {
			$proxy = is_string( $input['application_trusted_proxy'] ) ? trim( wp_unslash( $input['application_trusted_proxy'] ) ) : '';

			$clean['application_trusted_proxy'] = ( '' !== $proxy && false !== filter_var( $proxy, FILTER_VALIDATE_IP ) ) ? $proxy : '';
		}

		// Guarded like `handbook_enabled`, unlike the checkboxes above: the settings
		// screen does not render these four yet, so they are absent from every save of
		// the existing form, and reading them unconditionally would switch the home
		// redirect off and keep provisioning, applications and import off no matter
		// what a filter or a later screen set. Absent means "leave alone" - which only
		// holds while the save handler forwards booleans the form renders, not every
		// boolean in the defaults.
		foreach ( array( 'institution_provision', 'institution_home', 'applications_enabled', 'import_enabled', 'report_autodraft' ) as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$clean[ $flag ] = ! empty( $input[ $flag ] );
			}
		}

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
			'checker_max_pages'             => array( 1, 100 ),
			'checker_batch_size'            => array( 1, 25 ),
			'checker_request_delay'         => array( 0, 5000 ),
			'checker_cache_ttl'             => array( 0, MONTH_IN_SECONDS ),
			// Institutions module. The floors keep a typo from purging today's
			// applications tonight or refusing every upload; the approved-application
			// floor is 0 because 0 is the value that means "keep for ever".
			'application_spam_days'         => array( 1, 365 ),
			'application_rejected_days'     => array( 30, 3650 ),
			'application_approved_days'     => array( 0, 3650 ),
			'agreement_max_mb'              => array( 1, 50 ),
			'agreement_uploads_per_day'     => array( 1, 50 ),
			'agreement_generations_per_day' => array( 1, 100 ),
			'agreement_review_days'         => array( 1, 60 ),
			'agreement_discard_days'        => array( 7, 365 ),
			'invite_retention_days'         => array( 7, 365 ),
			// A week's floor keeps a typo from drafting every unfinished semester tonight.
			'report_autodraft_grace_days'   => array( 7, 365 ),
		);

		foreach ( $limits as $key => $range ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = max( $range[0], min( $range[1], (int) $input[ $key ] ) );
			}
		}

		$clean['checker_cron_enabled']  = ! empty( $input['checker_cron_enabled'] );
		$clean['checker_cron_promotes'] = ! empty( $input['checker_cron_promotes'] );

		// Three fields that must never be blank, because each is what a sync filters the
		// base by and `WPCPM_Airtable::formula_in()` turns an empty list into no filter at
		// all. A blank saved here would make the next run read every row of the table: an
		// account, a role and an institution stamp for every SPAM and rejected row, or, for
		// the current-student list on its own, the revocation of every current student. The
		// default goes back in and the screen says so (`render_notices()`); a blank already
		// stored before this guard is refused by both syncs' `start()`.
		$restored = array();

		foreach ( array_keys( self::never_blank() ) as $key ) {
			if ( empty( $clean[ $key ] ) ) {
				$clean[ $key ] = self::defaults()[ $key ];
				$restored[]   = $key;
			}
		}

		update_option( self::OPT_NAME, $clean );

		if ( ! empty( $restored ) ) {
			WPCPM_Flash::set( 'settings-defaults', $restored );
		}

		// A save carries the manager's current lists, so it is by definition up to date:
		// stamping here means `maybe_upgrade()` can never follow a save and put back a
		// status that was just removed on purpose.
		update_option( self::OPT_VERSION, self::SETTINGS_VERSION );

		// Keep the weekly schedule in step with the setting that governs it.
		if ( class_exists( 'WPCPM_Mentor_Checker_Runner' ) ) {
			WPCPM_Mentor_Checker_Runner::sync_cron( $clean['checker_cron_enabled'] );
		}

		return $clean;
	}

	/**
	 * Migrate a saved option when the settings schema moves, in the shape of
	 * `WPCPM_Roles::maybe_upgrade()`: it covers sites updated by dropping in new files.
	 *
	 * Defaults only reach a site that has never saved. A saved option is merged over
	 * them key by key, so a new *value* inside an existing list never arrives on its
	 * own, and for `student_statuses` that is a silent failure: both syncs build their
	 * Airtable formula from the saved list, so until it holds `Paused` and `Pending
	 * graduation` no Paused student is fetched and every line of code looks correct.
	 * The Developer Track shipped with a manual step for the same trap; this closes
	 * the gap with code, once, by appending whichever of the two is missing.
	 *
	 * The version option is what makes it once. Without it, a manager who removes a
	 * status on purpose would find it back after the next request, so the append runs
	 * only while the stored version is below 2, and both this and `save()` stamp the
	 * version afterwards. A site with no saved option inherits the new default and is
	 * stamped without writing one.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPT_VERSION ) >= self::SETTINGS_VERSION ) {
			return;
		}

		$stored = get_option( self::OPT_NAME, array() );

		if ( is_array( $stored ) && isset( $stored['student_statuses'] ) && is_array( $stored['student_statuses'] ) ) {
			$statuses = $stored['student_statuses'];

			foreach ( array( 'Paused', 'Pending graduation' ) as $status ) {
				if ( ! in_array( $status, $statuses, true ) ) {
					$statuses[] = $status;
				}
			}

			if ( $statuses !== $stored['student_statuses'] ) {
				$stored['student_statuses'] = $statuses;
				update_option( self::OPT_NAME, $stored );
			}
		}

		update_option( self::OPT_VERSION, self::SETTINGS_VERSION );
	}

	/**
	 * The settings `save()` never leaves blank, with the label each wears on the screen.
	 *
	 * @return array<string, string> Setting key => translated label.
	 */
	public static function never_blank() {
		return array(
			'mentor_status'             => __( 'Mentor status to sync', 'wpcredits-program-manager' ),
			'student_statuses'          => __( 'Currently mentoring', 'wpcredits-program-manager' ),
			'institution_active_stages' => __( 'Institution pipeline stages', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Tell the manager which fields the last save put back to their defaults.
	 *
	 * Hooked on `admin_notices` from the bootstrap. `save()` queues the list inside the
	 * admin-post request, and the handler then redirects to the settings screen, so the
	 * notice appears once, beside "Settings saved.", and is gone on the next reload.
	 */
	public static function render_notices() {
		$restored = WPCPM_Flash::take( 'settings-defaults' );

		if ( ! is_array( $restored ) || empty( $restored ) ) {
			return;
		}

		$labels   = self::never_blank();
		$defaults = self::defaults();
		$parts    = array();

		foreach ( $restored as $key ) {
			if ( ! isset( $labels[ $key ] ) ) {
				continue;
			}

			$value   = $defaults[ $key ];
			$parts[] = sprintf(
				/* translators: 1: setting label, 2: its default value. */
				__( '%1$s (now %2$s)', 'wpcredits-program-manager' ),
				$labels[ $key ],
				is_array( $value ) ? implode( ', ', $value ) : $value
			);
		}

		if ( empty( $parts ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: the settings that were reset, with their defaults. */
					__( 'These settings cannot be left blank, so their defaults were put back: %s. With nothing to filter by, a sync would read every row of the Airtable table and treat each one as current.', 'wpcredits-program-manager' ),
					implode( '; ', $parts )
				)
			)
		);
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
