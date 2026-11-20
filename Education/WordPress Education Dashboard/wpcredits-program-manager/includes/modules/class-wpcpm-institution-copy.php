<?php
/**
 * Institutions module - the copy of each institution the site writes to Airtable once the site keeps
 * the record (Institutions release 2), and the hourly retry of what Airtable did not take.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Airtable's Institutions table, kept as a copy of the site's record.
 *
 * **The site first, the copy after.** In site mode a change to an institution is made on the site
 * (the index row, the institution's record) and stands there whatever Airtable says. `send()` then
 * writes the changed columns to the base, so the students, mentors and reports that still link to an
 * institution there read the same school the site does. A copy Airtable refuses, or one that times
 * out, never undoes the site's change: the school stays marked in `OPT_PENDING`.
 *
 * **Marked before it is sent.** The keys go into the school's mark before the PATCH (no try counted,
 * no error), and a PATCH Airtable takes clears them. A request that dies while its PATCH is out leaves
 * the mark, which the hourly retry sends; a refusal adds the try and the error.
 *
 * **Keys, never values.** A mark holds which columns are owed (`keys`), since when, the last error,
 * how many writes were tried and when the last one was. The values are derived when a write is made
 * (`cells_for()`), from the index row and the record as they are then, so the hourly retry writes the
 * newest value of each column and never the one that failed, as the registrations' hourly retry
 * derives its row.
 *
 * **The hourly retry backs off.** It takes the marks never tried first, then the least recently tried,
 * at most `BATCH`, and passes over a school whose last try is younger than 2 to the power of its tries
 * in hours, at most 24, so a school Airtable keeps refusing cannot hold the others back.
 *
 * **What is read under which lock.** One school's write holds that school's lock (`LOCK`, `_` and the
 * record ID) from its mark to its settling, so two PATCHes of one school never race to be the last one
 * Airtable keeps; a send that finds it held only marks the keys. Every change of the pending list (the
 * mark before the PATCH, a failure, the settling after it) is a read and a write of the option under
 * `LOCK`, the read from the table and never from a copy this request cached. `pending()`, which the
 * retry's ordering and the notice use, reads without the lock: what it decides is only which school to
 * try, and each school's mark is read again under the locks before it is sent or changed. The holder,
 * before it clears a key it wrote, reads the site's value again: a value that changed while the PATCH
 * was out stays owed.
 *
 * **Not a lead, not outside site mode.** A lead is never written to Airtable, and outside site mode
 * the details card writes the base itself, before the site.
 */
final class WPCPM_Institution_Copy {

	/**
	 * The columns of the copy: the key the site keeps a value under => the Institutions column. The
	 * first eight are `WPCPM_Institutions_Sync::fields()`'s spelling; the name column follows the
	 * `institutions_name_field` setting, as `WPCPM_Institution_Details::write()` writes it.
	 */
	const COLUMNS = array(
		'name'               => 'Name',
		'stage'              => 'Current Stage',
		'country'            => 'Country',
		'city'               => 'City',
		'website'            => 'Website',
		'contact_person'     => 'Contact Person',
		'contact_email'      => 'Contact Email',
		'confirmed_on'       => 'Confirmed on',
		'next_step'          => 'Next Step',
		'follow_up'          => 'Follow-Up Date',
		'last_contact'       => 'Last Contact Date',
		// Two values Airtable has a column for (the outside review of release 2, O9): a whole number, and text.
		'estimated_students' => 'Estimated number of students who may be interested',
		'help_desk'          => 'FreeScout',
	);

	/**
	 * Record ID => `array( 'keys' => string[], 'since' => int, 'error' => string, 'tries' => int,
	 * 'tried' => int, 'missing' => int )`: the columns owed, when the mark was made (Unix), the last
	 * error, how many writes failed, when the last of them was (0 for never), and how many tries found
	 * the school missing from the index.
	 */
	const OPT_PENDING = 'wpcpm_institution_copy_pending';

	/** The hourly retry. */
	const CRON_HOURLY = 'wpcpm_institution_copy_hourly';

	/**
	 * The lock the pending option is read and written under; with `_` and a record ID after it, the
	 * lock one school's write holds. Uninstall sweeps both by this prefix.
	 */
	const LOCK = 'wpcpm_institution_copy_lock';

	/** How long the pending option's lock holds at most, in seconds. */
	const LOCK_TTL = 5;

	/** How many times a mark asks for the pending option's lock: past its lifetime, so a dead holder's runs out. */
	const LOCK_TRIES = 60;

	/** The pause between those asks, in microseconds. */
	const LOCK_PAUSE = 100000;

	/** How long one school's write holds its lock at most, in seconds: one PATCH and its answer. */
	const SEND_TTL = 120;

	/** The most schools one hourly run writes. */
	const BATCH = 50;

	/** The longest the retry waits after a failed try, in hours. */
	const BACKOFF_MAX_HOURS = 24;

	/** How long a mark with no try may stand before the notice counts it: a copy still in flight. */
	const IN_FLIGHT = 300;

	/** How many tries may find a school missing from the index before its mark is dropped. */
	const MISSING_TRIES = 24;

	/** How many schools the notice names. */
	const NOTICE_MAX = 10;

	/** The keys the site keeps in the institution's record rather than in the index row. */
	const RECORD_KEYS = array( 'next_step', 'follow_up', 'last_contact', 'estimated_students', 'help_desk' );

	/** The columns that are dates: an empty one is written as null. */
	const DATE_KEYS = array( 'confirmed_on', 'follow_up', 'last_contact' );

	/**
	 * Hang the retry on its hook, and see that it is on the clock.
	 */
	public static function init() {
		add_action( self::CRON_HOURLY, array( __CLASS__, 'run_hourly' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 20 );
	}

	/**
	 * In site mode, put the hourly retry on the clock, five minutes out, when it is not there; outside it, take the
	 * event off the clock, so the setting going off removes it at the next page load, as
	 * `WPCPM_Institution_Talks_Weekly::sync_schedule()` does its own (the whole-branch wave of release 2, W3).
	 */
	public static function schedule() {
		$next = wp_next_scheduled( self::CRON_HOURLY );

		if ( WPCPM_Institutions_Index::on_site() ) {
			if ( ! $next ) {
				wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOURLY );
			}

			return;
		}

		if ( $next ) {
			self::unschedule();
		}
	}

	/**
	 * Take the hourly retry off the clock.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOURLY );
	}

	/**
	 * The hook's callback: the retry, its count left to the cron.
	 */
	public static function run_hourly() {
		self::retry();
	}

	/**
	 * The cells of one school's copy, from the index row and the institution's record as they are now.
	 *
	 * Column => value, in the order of `COLUMNS`, for the keys asked that are columns. The country is a
	 * list of one record ID, or an empty list; an empty date (or one not in `Y-m-d`) and an empty stage
	 * are null, which clears the cell; estimated students is a whole number, null when empty, and an empty
	 * help desk link is null; an empty name is left out, since a school is never written
	 * without one; other text is as the site keeps it. A school the index does not hold has no cells at
	 * all, so no write can blank its columns in the base.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param array  $keys      Keys of `COLUMNS`.
	 * @return array
	 */
	public static function cells_for( $record_id, array $keys ) {
		$row = WPCPM_Institutions_Index::row( $record_id );

		if ( null === $row ) {
			return array();
		}

		$keys    = self::clean_keys( $keys );
		$columns = self::column_names();
		$kept    = array_intersect( $keys, self::RECORD_KEYS ) ? WPCPM_Institution_Record::get( $record_id ) : array();
		$out     = array();

		foreach ( $keys as $key ) {
			$source = in_array( $key, self::RECORD_KEYS, true ) ? $kept : $row;
			$value  = isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ? (string) $source[ $key ] : '';

			if ( 'name' === $key && '' === trim( $value ) ) {
				continue;
			}

			if ( 'country' === $key ) {
				$value = trim( $value );
				$value = '' === $value ? array() : array( $value );
			} elseif ( in_array( $key, self::DATE_KEYS, true ) ) {
				$value = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( $value ) ) ? trim( $value ) : null;
			} elseif ( 'stage' === $key && '' === trim( $value ) ) {
				$value = null;
			} elseif ( 'estimated_students' === $key ) {
				// Airtable's field is a number with no decimals: a whole number, or null when there is none.
				$value = 1 === preg_match( '/^\d+$/', trim( $value ) ) ? (int) trim( $value ) : null;
			} elseif ( 'help_desk' === $key && '' === trim( $value ) ) {
				$value = null;
			}

			$out[ $columns[ $key ] ] = $value;
		}

		return $out;
	}

	/**
	 * Write the copy of one school's changed columns to Airtable.
	 *
	 * The keys are marked first, under the pending list's lock, and the columns asked for with any the
	 * school still owes go in one PATCH, with the site's values now. Taken, the keys whose value is still
	 * the one written are cleared from the mark; refused, timed out or answered with nothing, the mark
	 * keeps every key and gains the error and one more try. A school another request is writing is
	 * marked, not sent, with no try counted. A mark the pending list's lock kept from being written is
	 * logged, and the PATCH is still made.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param array  $keys      Keys of `COLUMNS` that changed.
	 * @return bool True when Airtable took the copy. False when it did not (the keys are pending then),
	 *              and when nothing was sent: outside site mode, for a lead's key or anything else that
	 *              is no record ID, with no key that is a column, and for a school the index does not
	 *              hold.
	 */
	public static function send( $record_id, array $keys ) {
		if ( ! WPCPM_Institutions_Index::on_site() || ! WPCPM_Airtable::is_record_id( $record_id ) ) {
			return false;
		}

		$record_id = trim( (string) $record_id );
		$keys      = self::clean_keys( $keys );

		if ( empty( $keys ) || ! WPCPM_Institutions_Index::has( $record_id ) ) {
			return false;
		}

		$lock  = self::send_lock( $record_id );
		$token = WPCPM_Lock::take( $lock, self::SEND_TTL );

		if ( '' === $token ) {
			if ( false === self::owe( $record_id, $keys ) ) {
				self::log( $record_id, 'could not be marked while another copy of it was being written' );
			}

			return false;
		}

		try {
			$owed = self::owe( $record_id, $keys );

			if ( false === $owed ) {
				self::log( $record_id, 'could not be marked before its copy was sent' );
			} else {
				$keys = $owed;
			}

			return in_array( self::write( $record_id, $keys ), array( 'cleared', 'written' ), true );
		} finally {
			WPCPM_Lock::release( $lock, $token );
		}
	}

	/**
	 * Mark keys of a school as owed to the copy, and send nothing: the write-ahead mark alone, no try counted.
	 *
	 * For a caller that holds a lock no request may leave under (the agreement path paying a change owed to its
	 * index row, under the school's agreement lock or the Talks card's): the caller's own later `send()` carries
	 * the keys with its own, and the hourly retry carries them otherwise. A mark the list's lock kept from being
	 * written is logged.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param array  $keys      Keys of `COLUMNS`.
	 * @return bool Whether the keys are marked. False outside site mode, for anything that is no record ID, with
	 *              no key that is a column, for a school the index does not hold, and when the mark could not be
	 *              written.
	 */
	public static function mark( $record_id, array $keys ) {
		if ( ! WPCPM_Institutions_Index::on_site() || ! WPCPM_Airtable::is_record_id( $record_id ) ) {
			return false;
		}

		$record_id = trim( (string) $record_id );
		$keys      = self::clean_keys( $keys );

		if ( empty( $keys ) || ! WPCPM_Institutions_Index::has( $record_id ) ) {
			return false;
		}

		if ( false === self::owe( $record_id, $keys ) ) {
			self::log( $record_id, 'could not be marked as owed' );

			return false;
		}

		return true;
	}

	/**
	 * Write the pending schools again: never tried first, then the least recently tried, at most `BATCH`.
	 *
	 * A school whose last try is younger than its backoff (`backoff()`) is passed over and does not take
	 * a place in the batch. The cells are derived now, so each school's newest values go. A school whose
	 * write another request holds is left for the next run. A school the index no longer holds keeps its
	 * mark and counts a try, so it backs off as a refusal does; after `MISSING_TRIES` such tries, and
	 * only while the index holds rows (an empty index is the state the sync's empty-index guard stops
	 * on), its mark is dropped. Nothing is done outside site mode.
	 *
	 * @return int How many schools were written and cleared.
	 */
	public static function retry() {
		if ( ! WPCPM_Institutions_Index::on_site() ) {
			return 0;
		}

		$now = time();
		$due = array();

		self::forget_pending();

		foreach ( self::pending() as $record_id => $entry ) {
			if ( $entry['tried'] > 0 && $now - $entry['tried'] < self::backoff( $entry['tries'] ) ) {
				continue;
			}

			$due[ $record_id ] = $entry['tried'];
		}

		// Never tried (0) first, then the least recently tried; PHP's sort keeps the list's order on a tie.
		asort( $due, SORT_NUMERIC );

		$written = 0;
		$places  = 0;

		foreach ( array_keys( $due ) as $record_id ) {
			if ( $places >= self::BATCH ) {
				break;
			}

			$record_id = (string) $record_id;
			$lock      = self::send_lock( $record_id );
			$token     = WPCPM_Lock::take( $lock, self::SEND_TTL );

			// A school another request is writing takes no place in the batch.
			if ( '' === $token ) {
				continue;
			}

			try {
				self::forget_pending();

				$owed = self::pending();

				if ( ! isset( $owed[ $record_id ] ) ) {
					continue;
				}

				++$places;

				if ( 'cleared' === self::write( $record_id, $owed[ $record_id ]['keys'] ) ) {
					++$written;
				}
			} finally {
				WPCPM_Lock::release( $lock, $token );
			}
		}

		return $written;
	}

	/**
	 * Empty the list of copies owed to Airtable, under its own lock (the trial's round of release 2, T1), as
	 * `WPCPM_Institution_Agreement::forget_index_debts()` empties the index changes owed. Called by the one way the
	 * setting `institutions_on_site` changes (`WPCPM_Institutions_Switch::change()`), in either direction: a mark kept
	 * across the switch would be sent by a later hourly run over what Airtable came to hold meanwhile. Going back, the
	 * warning names those schools first and their changes are left to be written in Airtable by hand; turning on, any
	 * mark still there is from an earlier time in site mode. When the lock cannot be taken the list is deleted without
	 * it, and the site's error log says so.
	 *
	 * @return bool Whether the lock was taken.
	 */
	public static function forget_pending_marks() {
		$locked = self::with_pending(
			static function () {
				return array();
			}
		);

		if ( ! $locked ) {
			delete_option( self::OPT_PENDING );
			self::forget_pending();

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- A list emptied without its lock has no other place to be said.
			error_log( 'WPCredits: the list of copies owed to Airtable was emptied without its lock at a change of the institutions\' record: another request held the lock.' );
		}

		return $locked;
	}

	/**
	 * What is owed to Airtable: record ID => `keys` (in the order of `COLUMNS`), `since`, `error`,
	 * `tries`, `tried` and `missing` (as `OPT_PENDING` says), oldest mark first.
	 *
	 * @return array<string, array{keys:string[],since:int,error:string,tries:int,tried:int,missing:int}>
	 */
	public static function pending() {
		$stored = get_option( self::OPT_PENDING, array() );
		$out    = array();

		foreach ( is_array( $stored ) ? $stored : array() as $record_id => $entry ) {
			if ( ! is_array( $entry ) || ! WPCPM_Airtable::is_record_id( $record_id ) ) {
				continue;
			}

			$entry = self::shape( $entry );

			if ( empty( $entry['keys'] ) ) {
				continue;
			}

			$out[ (string) $record_id ] = $entry;
		}

		uasort(
			$out,
			static function ( $one, $two ) {
				return $one['since'] <=> $two['since'];
			}
		);

		return $out;
	}

	/**
	 * The names of the schools whose copy is still owed to Airtable (`pending()`), oldest mark first: the index row's
	 * name trimmed, or the record ID for a school with none. What the settings screen's warning names when the setting
	 * goes off before Airtable has received everything (the whole-branch wave of release 2, W4).
	 *
	 * @return string[]
	 */
	public static function pending_names() {
		$names = array();

		foreach ( array_keys( self::pending() ) as $record_id ) {
			$row     = WPCPM_Institutions_Index::row( (string) $record_id );
			$name    = is_array( $row ) && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
			$names[] = '' !== $name ? $name : (string) $record_id;
		}

		return $names;
	}

	/**
	 * The Institutions screen's notice of what is not in Airtable yet: each school, since when and the
	 * last error, oldest first, at most `NOTICE_MAX` of them and a line counting the rest.
	 *
	 * A school is counted once its mark has a failed try, or once the mark is older than `IN_FLIGHT`
	 * seconds with none (the request that made it died with its copy out); a copy still in flight is not
	 * announced as a failure. Nothing at all when no school is counted, and outside site mode. A wp-admin
	 * notice in WordPress's own markup, drawn under the not-connected notice.
	 */
	public static function render_pending_notice() {
		if ( ! WPCPM_Institutions_Index::on_site() ) {
			return;
		}

		$now   = time();
		$owed  = array_filter(
			self::pending(),
			static function ( $entry ) use ( $now ) {
				return $entry['tries'] > 0 || $now - $entry['since'] >= self::IN_FLIGHT;
			}
		);
		$total = count( $owed );

		if ( 0 === $total ) {
			return;
		}

		// How to read this, a plain link at the end of the sentence, to the guide's job (1.123.104, G5); none without a guide.
		$help = WPCPM_Guide_Jobs::link( 'airtable-copy', '', __( 'How to read this', 'wpcredits-program-manager' ) );

		echo '<div class="notice notice-warning wpcpm-copy-pending"><p>' . esc_html__( 'These institutions\' changes are not in Airtable yet. The site tries again by itself: after an hour or two at first, then less often, and at least once a day.', 'wpcredits-program-manager' ) . ( '' === $help ? '' : ' ' . $help ) . '</p><ul class="ul-disc">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in esc_html__() and WPCPM_Guide_Jobs::link().

		foreach ( array_slice( $owed, 0, self::NOTICE_MAX, true ) as $record_id => $entry ) {
			$row  = WPCPM_Institutions_Index::row( $record_id );
			$name = null !== $row && isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
			$name = '' !== $name ? $name : (string) $record_id;
			$when = WPCPM_Dates::moment( $entry['since'] );

			$line = '' !== $entry['error']
				? sprintf(
					/* translators: 1: an institution's name, 2: the date and time its copy was first owed, 3: Airtable's error. */
					__( '%1$s, waiting since %2$s: %3$s', 'wpcredits-program-manager' ),
					$name,
					$when,
					$entry['error']
				)
				: sprintf(
					/* translators: 1: an institution's name, 2: the date and time its copy was first owed. */
					__( '%1$s, waiting since %2$s.', 'wpcredits-program-manager' ),
					$name,
					$when
				);

			echo '<li>' . esc_html( $line ) . '</li>';
		}

		$more = $total - min( $total, self::NOTICE_MAX );

		if ( $more > 0 ) {
			echo '<li>' . esc_html(
				sprintf(
					/* translators: %s: how many more institutions are waiting than the list shows. */
					_n( 'and %s more', 'and %s more', $more, 'wpcredits-program-manager' ),
					number_format_i18n( $more )
				)
			) . '</li>';
		}

		echo '</ul></div>';
	}

	/**
	 * Make the Airtable record of a lead that is promoted to an institution (Task 14).
	 *
	 * One POST of the seven columns a school starts with, the empty ones left out. Refused before
	 * anything is sent: no name, the Lead stage (a lead is never written to Airtable), a country that is
	 * not a record ID.
	 *
	 * @param array $row `name`, `stage`, `country` (a Countries record ID), `city`, `website`,
	 *                   `contact_person`, `contact_email`.
	 * @return string|WP_Error The new record ID, or the error.
	 */
	public static function create( array $row ) {
		$value = static function ( $key ) use ( $row ) {
			return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
		};

		if ( '' === $value( 'name' ) ) {
			return new WP_Error( 'wpcpm_copy_create', __( 'The institution needs a name before it can be written to Airtable.', 'wpcredits-program-manager' ) );
		}

		if ( WPCPM_Institution_Talks::STAGE_LEAD === $value( 'stage' ) ) {
			return new WP_Error( 'wpcpm_copy_create', __( 'A lead is not written to Airtable: choose the stage the talks are at.', 'wpcredits-program-manager' ) );
		}

		if ( '' !== $value( 'country' ) && ! WPCPM_Airtable::is_record_id( $value( 'country' ) ) ) {
			return new WP_Error( 'wpcpm_copy_create', __( 'That country is not one of the program\'s, so the institution was not written to Airtable.', 'wpcredits-program-manager' ) );
		}

		$columns = self::column_names();
		$fields  = array();

		foreach ( array( 'name', 'stage', 'country', 'city', 'website', 'contact_person', 'contact_email' ) as $key ) {
			if ( '' === $value( $key ) ) {
				continue;
			}

			$fields[ $columns[ $key ] ] = 'country' === $key ? array( $value( $key ) ) : $value( $key );
		}

		$settings = WPCPM_Settings::get();
		$created  = ( new WPCPM_Airtable( $settings ) )->create_records(
			isset( $settings['institutions_table'] ) ? (string) $settings['institutions_table'] : '',
			array( array( 'fields' => $fields ) )
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created ) || ! is_array( $created ) || ! WPCPM_Airtable::is_record_id( reset( $created ) ) ) {
			return new WP_Error( 'wpcpm_copy_create', __( 'Airtable did not make the record.', 'wpcredits-program-manager' ) );
		}

		return (string) reset( $created );
	}

	/**
	 * How long the retry waits after a school's last failed try: 2 to the power of its tries, in hours,
	 * at most `BACKOFF_MAX_HOURS`.
	 *
	 * @param int $tries Failed tries.
	 * @return int Seconds.
	 */
	private static function backoff( $tries ) {
		$tries = max( 0, (int) $tries );
		$hours = $tries >= 5 ? self::BACKOFF_MAX_HOURS : min( self::BACKOFF_MAX_HOURS, 2 ** $tries );

		return $hours * HOUR_IN_SECONDS;
	}

	/**
	 * One write of one school, its lock held by the caller: the PATCH, then the mark settled.
	 *
	 * @param string   $record_id The institution's Airtable record ID.
	 * @param string[] $keys      Clean keys.
	 * @return string `cleared` (taken, and nothing of the school is pending now), `written` (taken, a
	 *                value that changed meanwhile still pending), `failed` (pending, with the error),
	 *                or `missing` (the index does not hold the school).
	 */
	private static function write( $record_id, array $keys ) {
		self::forget_cached( $record_id );

		if ( ! WPCPM_Institutions_Index::has( $record_id ) ) {
			self::missing( $record_id );

			return 'missing';
		}

		$cells = self::cells_for( $record_id, $keys );

		if ( empty( $cells ) ) {
			// Only an empty name was owed, which is never sent: the debt is settled as written.
			self::settle( $record_id, $cells, $keys );

			return 'cleared';
		}

		$settings = WPCPM_Settings::get();
		$answer   = ( new WPCPM_Airtable( $settings ) )->update_records(
			isset( $settings['institutions_table'] ) ? (string) $settings['institutions_table'] : '',
			array(
				array(
					'id'     => $record_id,
					'fields' => $cells,
				),
			)
		);

		if ( is_wp_error( $answer ) || empty( $answer ) ) {
			if ( false === self::fail( $record_id, $keys, is_wp_error( $answer ) ? $answer->get_error_message() : __( 'Airtable did not take the change.', 'wpcredits-program-manager' ) ) ) {
				self::log( $record_id, 'was refused by Airtable and the refusal could not be marked' );
			}

			return 'failed';
		}

		return self::settle( $record_id, $cells, $keys ) ? 'cleared' : 'written';
	}

	/**
	 * Settle a school's mark after its PATCH was taken.
	 *
	 * Each key written is read again from the site: one whose value is still the one written is cleared,
	 * one that changed while the PATCH was out stays, and the mark then starts again with no try and no
	 * error (nothing failed), so the next run sends it first. A key written but left out of the cells
	 * (an empty name) is cleared.
	 *
	 * @param string   $record_id The institution's Airtable record ID.
	 * @param array    $cells     What was written, column => value.
	 * @param string[] $keys      The keys written.
	 * @return bool Whether nothing of the school is pending now.
	 */
	private static function settle( $record_id, array $cells, array $keys ) {
		$cleared = false;

		$done = self::with_pending(
			static function ( array $pending ) use ( $record_id, $cells, $keys, &$cleared ) {
				if ( ! isset( $pending[ $record_id ] ) || ! is_array( $pending[ $record_id ] ) ) {
					$cleared = true;

					return $pending;
				}

				self::forget_cached( $record_id );

				$entry   = self::shape( $pending[ $record_id ] );
				$now     = self::cells_for( $record_id, $keys );
				$columns = self::column_names();
				$left    = array();

				foreach ( $entry['keys'] as $key ) {
					if ( in_array( $key, $keys, true ) ) {
						$column = $columns[ $key ];
						$sent   = array_key_exists( $column, $cells );

						if ( ! $sent || ( array_key_exists( $column, $now ) && $now[ $column ] === $cells[ $column ] ) ) {
							continue;
						}
					}

					$left[] = $key;
				}

				if ( empty( $left ) ) {
					unset( $pending[ $record_id ] );
					$cleared = true;

					return $pending;
				}

				// The row was found, so the count of tries that found it missing starts again.
				$entry['missing'] = 0;

				// A key left that was not sent (the write-ahead mark could not be made, so only the keys
				// asked for went) keeps the mark's tries, time and error: nothing was tried for it.
				if ( array() !== array_diff( $left, $keys ) ) {
					$pending[ $record_id ] = array_merge( $entry, array( 'keys' => $left ) );

					return $pending;
				}

				$pending[ $record_id ] = array_merge(
					$entry,
					array(
						'keys'  => $left,
						'since' => time(),
						'error' => '',
						'tries' => 0,
						'tried' => 0,
					)
				);

				return $pending;
			}
		);

		if ( ! $done ) {
			self::log( $record_id, 'was written to Airtable, and its mark could not be cleared' );
		}

		return $cleared;
	}

	/**
	 * Mark keys of a school as owed, with no try counted and no error: before a PATCH, or when another
	 * request holds the school's write. A mark that stands keeps its time, tries and error.
	 *
	 * @param string   $record_id The institution's Airtable record ID.
	 * @param string[] $keys      Keys of `COLUMNS`.
	 * @return string[]|false Every key the school owes now, or false when the lock kept the mark from
	 *                        being written or the table does not hold it after the write (O6).
	 */
	private static function owe( $record_id, array $keys ) {
		$owed = $keys;

		$done = self::with_pending(
			static function ( array $pending ) use ( $record_id, $keys, &$owed ) {
				$entry         = isset( $pending[ $record_id ] ) && is_array( $pending[ $record_id ] ) ? self::shape( $pending[ $record_id ] ) : self::shape( array( 'since' => time() ) );
				$entry['keys'] = self::clean_keys( array_merge( $entry['keys'], $keys ) );
				$owed          = $entry['keys'];

				$pending[ $record_id ] = $entry;

				return $pending;
			}
		);

		return $done ? $owed : false;
	}

	/**
	 * A failed write: the keys owed, the error, one more try and when it was.
	 *
	 * @param string   $record_id The institution's Airtable record ID.
	 * @param string[] $keys      Keys of `COLUMNS`.
	 * @param string   $error     Why the write did not go.
	 * @return bool Whether the lock let the mark be written.
	 */
	private static function fail( $record_id, array $keys, $error ) {
		return self::with_pending(
			static function ( array $pending ) use ( $record_id, $keys, $error ) {
				$entry = isset( $pending[ $record_id ] ) && is_array( $pending[ $record_id ] ) ? self::shape( $pending[ $record_id ] ) : self::shape( array( 'since' => time() ) );

				$entry['keys']  = self::clean_keys( array_merge( $entry['keys'], $keys ) );
				$entry['error'] = function_exists( 'mb_substr' ) ? mb_substr( (string) $error, 0, 300 ) : substr( (string) $error, 0, 300 );
				$entry['tries'] = $entry['tries'] + 1;
				$entry['tried'] = time();

				// The row was found, so the count of tries that found it missing starts again.
				$entry['missing'] = 0;

				$pending[ $record_id ] = $entry;

				return $pending;
			}
		);
	}

	/**
	 * A try that found the school missing from the index: counted as a failed try, so it backs off, and
	 * dropped after `MISSING_TRIES` of them while the index holds rows.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 */
	private static function missing( $record_id ) {
		$index_empty = array() === WPCPM_Institutions_Index::rows();

		$done = self::with_pending(
			static function ( array $pending ) use ( $record_id, $index_empty ) {
				if ( ! isset( $pending[ $record_id ] ) || ! is_array( $pending[ $record_id ] ) ) {
					return $pending;
				}

				$entry = self::shape( $pending[ $record_id ] );

				// Missing counts only while the index holds rows: an empty index says nothing of one school.
				if ( ! $index_empty ) {
					++$entry['missing'];
				}

				if ( ! $index_empty && $entry['missing'] >= self::MISSING_TRIES ) {
					unset( $pending[ $record_id ] );

					return $pending;
				}

				$entry['error'] = __( 'The site\'s list of institutions does not hold this school.', 'wpcredits-program-manager' );
				$entry['tries'] = $entry['tries'] + 1;
				$entry['tried'] = time();

				$pending[ $record_id ] = $entry;

				return $pending;
			}
		);

		if ( ! $done ) {
			self::log( $record_id, 'was missing from the index and that could not be marked' );
		}
	}

	/**
	 * One mark in its shape, every key typed.
	 *
	 * @param array $entry A stored mark.
	 * @return array
	 */
	private static function shape( array $entry ) {
		return array(
			'keys'    => self::clean_keys( isset( $entry['keys'] ) && is_array( $entry['keys'] ) ? $entry['keys'] : array() ),
			'since'   => isset( $entry['since'] ) ? (int) $entry['since'] : 0,
			'error'   => isset( $entry['error'] ) && is_scalar( $entry['error'] ) ? (string) $entry['error'] : '',
			'tries'   => isset( $entry['tries'] ) ? max( 0, (int) $entry['tries'] ) : 0,
			'tried'   => isset( $entry['tried'] ) ? max( 0, (int) $entry['tried'] ) : 0,
			'missing' => isset( $entry['missing'] ) ? max( 0, (int) $entry['missing'] ) : 0,
		);
	}

	/**
	 * Read the pending option, change it and write it back, under `LOCK`.
	 *
	 * The lock is asked for past its lifetime (`LOCK_TRIES` asks, `LOCK_PAUSE` apart), so a request that
	 * died holding it does not keep a mark from being written. Once it is held, every copy of the option
	 * this request may have cached is dropped (`forget_pending()`), so the read is the table's and the
	 * write that follows compares against what the table holds.
	 *
	 * **Stored, not answered** (the outside review of release 2, O6). After a write the list is read again from the
	 * table, never from this request's copy, and this answers false when what is stored is not what was written: the
	 * answers of `update_option()` and `delete_option()` are not trusted, since `update_option()` answers false for a
	 * value it already holds as well as for a write refused. `owe()` and `mark()` pass that on, so the agreement path
	 * keeps its change owed when its mark could not be stored.
	 *
	 * @param callable $change Takes the stored list and answers the list to store.
	 * @return bool Whether the lock was taken and the list the change answered is the one the table holds.
	 */
	private static function with_pending( callable $change ) {
		$token = '';

		for ( $tries = 0; $tries < self::LOCK_TRIES; ++$tries ) {
			$token = WPCPM_Lock::take( self::LOCK, self::LOCK_TTL );

			if ( '' !== $token ) {
				break;
			}

			usleep( self::LOCK_PAUSE );
		}

		if ( '' === $token ) {
			return false;
		}

		$kept = true;

		try {
			self::forget_pending();

			$stored  = get_option( self::OPT_PENDING, array() );
			$stored  = is_array( $stored ) ? $stored : array();
			$pending = $change( $stored );

			if ( $pending !== $stored ) {
				if ( empty( $pending ) ) {
					delete_option( self::OPT_PENDING );
				} else {
					update_option( self::OPT_PENDING, $pending, false );
				}

				self::forget_pending();

				$now  = get_option( self::OPT_PENDING, array() );
				$kept = ( is_array( $now ) ? $now : array() ) === ( empty( $pending ) ? array() : $pending );
			}
		} finally {
			WPCPM_Lock::release( self::LOCK, $token );
		}

		return $kept;
	}

	/**
	 * Drop every copy of the pending option this request may hold: its own key, and its entry in
	 * WordPress's lists of options known absent (`notoptions`) and of autoloaded ones (`alloptions`).
	 *
	 * A request that read the option while there was none keeps it in `notoptions`; read again from that
	 * cache it is an empty list, and `update_option()` would then add the option over a row another
	 * request wrote meanwhile. Dropped here, the read and the write both see the table.
	 */
	private static function forget_pending() {
		wp_cache_delete( self::OPT_PENDING, 'options' );

		foreach ( array( 'notoptions', 'alloptions' ) as $list ) {
			$cached = wp_cache_get( $list, 'options' );

			if ( is_array( $cached ) && array_key_exists( self::OPT_PENDING, $cached ) ) {
				unset( $cached[ self::OPT_PENDING ] );
				wp_cache_set( $list, $cached, 'options' );
			}
		}
	}

	/**
	 * Say in the site's error log what could not be kept, so a lost mark is never silent. The school is
	 * named by its record ID alone: the log is a server's, and no value of the school goes in it.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @param string $what      What happened, after "The Airtable copy of <ID> ".
	 */
	private static function log( $record_id, $what ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- A copy owed to Airtable that could not be marked has no other place to be said.
		error_log( sprintf( 'WPCredits: the Airtable copy of %1$s %2$s.', (string) $record_id, (string) $what ) );
	}

	/**
	 * Drop this request's copies of a school's values, so the next read is the tables'.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 */
	private static function forget_cached( $record_id ) {
		WPCPM_Institutions_Index::forget_cached();
		WPCPM_Institution_Record::forget_cached( $record_id );
	}

	/**
	 * The keys given that are columns, once each, in the order of `COLUMNS`.
	 *
	 * @param array $keys Keys.
	 * @return string[]
	 */
	private static function clean_keys( array $keys ) {
		$keys = array_map( 'strval', array_filter( $keys, 'is_scalar' ) );

		return array_values( array_intersect( array_keys( self::COLUMNS ), $keys ) );
	}

	/**
	 * `COLUMNS`, the name column as the setting names it.
	 *
	 * @return array<string, string>
	 */
	private static function column_names() {
		$columns = self::COLUMNS;
		$name    = WPCPM_Settings::get_value( 'institutions_name_field', '' );

		if ( is_string( $name ) && '' !== trim( $name ) ) {
			$columns['name'] = $name;
		}

		return $columns;
	}

	/**
	 * The lock one school's write holds.
	 *
	 * @param string $record_id The institution's Airtable record ID.
	 * @return string
	 */
	private static function send_lock( $record_id ) {
		return self::LOCK . '_' . $record_id;
	}
}
