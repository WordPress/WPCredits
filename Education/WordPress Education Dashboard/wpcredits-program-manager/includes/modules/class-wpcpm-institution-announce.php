<?php
/**
 * The Slack message when an institution reaches Confirmed.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the program on Slack that an institution has signed up, once per institution.
 *
 * Asked for in WPCredits#282: the team only learned of a new partnership when somebody
 * mentioned it, and a confirmation is the one moment in the pipeline worth celebrating. The
 * message goes to the webhook the Mentor Status Checker already uses, so the channel is chosen
 * in Slack and never here.
 *
 * **It answers from the site, not from an automation.** The index already holds every
 * institution's stage, and the roster index already holds every institution's students, so the
 * site can see a confirmation without anything being written in Airtable for its benefit
 * (`WPCredits#259` and `#282`, decided 28 October 2026: no new Airtable automations while the
 * program is moving off it).
 *
 * **Nothing is announced the first time.** `OPT_ANNOUNCED` is seeded with every institution
 * already at Confirmed on the first run after this ships, and that run says nothing: the
 * forty-odd partnerships already signed are not news. From then on an institution missing from
 * the set is one that has just arrived.
 *
 * **One announcer at a time, under a lock of its own (1.123.57).** 1.123.56 took no lock, on the
 * claim that the sync's own lock covered this read-change-write. It does not: `finish()` lets that
 * lock go and then calls this, and its call site says as much, so two runs finishing close
 * together - an Administrator's **Sync institutions now** after an automatic run, or the follow-up
 * run a correction asks for five minutes later - could both read this set, post overlapping
 * messages and write each other's additions away. `WPCPM_Lock` serializes them instead, held
 * through the sending and the writing and let go in `finally`. A run that cannot take it says
 * nothing and records nothing: the run that holds it is reading the same index, and whatever is
 * left unannounced is announced by the next run, exactly as a refused message is.
 *
 * **A flood becomes one message.** A stage column edited in bulk, or a first run of a restored
 * index, can move many institutions at once. Up to `PER_MESSAGE` are announced one by one;
 * more than that is one message naming them, because fifty posts in a row is not news either.
 *
 * **A refused message leaves the institution unannounced**, so the next run says it again. An
 * institution is only ever recorded as announced once Slack has taken it.
 *
 * **A confirmation the program dated long ago is history, and is recorded without a word.**
 * The set only knows which records have been announced, so a record appearing at Confirmed
 * looks the same whether the program just confirmed it or a spreadsheet of old partnerships
 * was imported this morning. `Confirmed on` tells the two apart, and the program keeps that
 * field: all 55 records at Confirmed carried a date when this was written, none empty. So a
 * date inside `RECENT_DAYS` is announced and an older one is not, while an undated or
 * unreadable one is announced, because losing a real partnership to an empty field is the
 * worse failure of the two. Either way the record is remembered, so no run weighs it twice.
 */
final class WPCPM_Institution_Announce {

	/** Option: the record IDs already announced, and the schema that seeded them. */
	const OPT_ANNOUNCED = 'wpcpm_institution_announced';

	/** Bumped when the set has to be seeded again. */
	const SCHEMA_VERSION = 1;

	/** Announced one by one up to this many in a run; beyond it, one message names them all. */
	const PER_MESSAGE = 3;

	/**
	 * A confirmation dated more than this many days ago is history, not news.
	 *
	 * Thirty days is generous on purpose: it covers a sync that has been down for weeks and a
	 * record whose stage is moved some days after the agreement was signed, while an import of
	 * partnerships signed last year stays silent.
	 */
	const RECENT_DAYS = 30;

	/** The stage an institution reaches when the program has said yes. */
	const STAGE = 'Confirmed';

	/** The lock one announcer holds while it reads the set, sends and writes it back. */
	const LOCK = 'wpcpm_institution_announce_lock';

	/**
	 * How long that lock holds at most, in seconds.
	 *
	 * Three messages at `WPCPM_Slack::TIMEOUT` seconds each is the longest a run can spend
	 * waiting on Slack, and the index reads before them are cheap; two minutes leaves room
	 * without leaving a request that died mid-send holding the lock for long.
	 */
	const LOCK_TTL = 120;

	/**
	 * Say what this run has to say. Called by the institutions sync once a run is done.
	 *
	 * Silent, and writes nothing, while no webhook is saved: a webhook added later announces
	 * the institutions that follow it, not a backlog nobody asked about. The seed is written
	 * on the first run that has a webhook for the same reason.
	 *
	 * @return int How many institutions were announced; 0 for a seed, no webhook, or no news.
	 */
	public static function after_sync() {
		$webhook = WPCPM_Slack::webhook();

		if ( '' === $webhook ) {
			return 0;
		}

		// Nothing is read, sent or written while another run is announcing: see the class's own
		// note on the lock. Taken after the webhook read, so a site with no webhook saved writes
		// no lock row either.
		$token = WPCPM_Lock::take( self::LOCK, self::LOCK_TTL );

		if ( '' === $token ) {
			return 0;
		}

		$switched = switch_to_locale( get_locale() );

		try {
			return self::announce( $webhook );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}

			WPCPM_Lock::release( self::LOCK, $token );
		}
	}

	/**
	 * Say what this run has to say, inside the lock and the site's own language.
	 *
	 * @param string $webhook The webhook, already read and checked.
	 * @return int How many institutions were announced; 0 for a seed or no news.
	 */
	private static function announce( $webhook ) {
		$confirmed = self::confirmed();
		$stored    = get_option( self::OPT_ANNOUNCED );

		// The first run with a webhook records where the program already is and says nothing.
		if ( ! is_array( $stored ) || ! isset( $stored['v'] ) || self::SCHEMA_VERSION !== (int) $stored['v'] ) {
			self::remember( array_keys( $confirmed ) );

			return 0;
		}

		$known = isset( $stored['ids'] ) && is_array( $stored['ids'] ) ? $stored['ids'] : array();
		$news  = array_diff_key( $confirmed, array_flip( $known ) );

		// Arrivals the program dated long ago: remembered here, announced nowhere.
		$history = array();

		foreach ( $news as $record_id => $row ) {
			if ( ! self::is_news( is_array( $row ) ? $row : array() ) ) {
				$history[] = (string) $record_id;

				unset( $news[ $record_id ] );
			}
		}

		if ( empty( $news ) ) {
			if ( ! empty( $history ) ) {
				self::remember( array_merge( $known, $history ) );
			}

			return 0;
		}

		$total = count( $confirmed );
		$said  = array();

		if ( count( $news ) > self::PER_MESSAGE ) {
			if ( true === WPCPM_Slack::send( $webhook, self::many( $news, $total ) ) ) {
				$said = array_keys( $news );
			}
		} else {
			foreach ( $news as $record_id => $row ) {
				if ( true === WPCPM_Slack::send( $webhook, self::message( $row, $total ) ) ) {
					$said[] = $record_id;
				}
			}
		}

		if ( ! empty( $said ) || ! empty( $history ) ) {
			self::remember( array_merge( $known, $history, $said ) );
		}

		return count( $said );
	}

	/**
	 * Whether a row at Confirmed is news rather than history.
	 *
	 * Reads `confirmed_on`, which the institutions sync already stores as a date part, and
	 * answers in whole days so the verdict does not turn on the hour the sync happens to run
	 * at: a date `RECENT_DAYS` back is inside the window for the whole of that day, and the
	 * day after it is outside for the whole of that one. A date in the future counts as news,
	 * since a negative count of days is inside any window.
	 *
	 * @param array $row The index row.
	 * @return bool
	 */
	private static function is_news( array $row ) {
		$on = trim( (string) ( isset( $row['confirmed_on'] ) ? $row['confirmed_on'] : '' ) );

		if ( '' === $on ) {
			return true;
		}

		$midnight = strtotime( $on . ' 00:00:00 +0000' );

		if ( false === $midnight ) {
			return true;
		}

		return (int) floor( ( time() - $midnight ) / DAY_IN_SECONDS ) <= self::RECENT_DAYS;
	}

	/**
	 * Every real institution at Confirmed, keyed by record ID.
	 *
	 * **A test institution is not among them.** It is left out of every number the site draws,
	 * mails or answers, and a message in the program's channel is the most public of them;
	 * `WPCPM_Test_Institutions::real_keys()` is the one rule every list and count asks, so this
	 * one cannot drift from the Institutions screen's. Left out here rather than at the
	 * announcement, so the running total the message prints is over the same set, and nothing is
	 * recorded for it either: a test institution is simply invisible to the announcer, and one
	 * renamed away from TEST is news at the sync that makes it real.
	 *
	 * @return array<string, array> Index rows.
	 */
	private static function confirmed() {
		$rows = array();

		foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
			if ( ! is_array( $row ) || ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			if ( self::STAGE === trim( (string) ( isset( $row['stage'] ) ? $row['stage'] : '' ) ) ) {
				$rows[ (string) $record_id ] = $row;
			}
		}

		return WPCPM_Test_Institutions::real_keys( $rows );
	}

	/**
	 * Write the set, newest last, without duplicates.
	 *
	 * @param array $ids Record IDs.
	 */
	private static function remember( array $ids ) {
		$clean = array();

		foreach ( $ids as $id ) {
			$id = trim( (string) $id );

			if ( '' !== $id && ! isset( $clean[ $id ] ) ) {
				$clean[ $id ] = true;
			}
		}

		update_option(
			self::OPT_ANNOUNCED,
			array(
				'v'      => self::SCHEMA_VERSION,
				'ids'    => array_keys( $clean ),
				'seeded' => time(),
			),
			false
		);
	}

	/**
	 * One institution's message, in Slack's markup, in the site's language whatever the
	 * language of the request that sends it: a manager reading wp-admin in their own language
	 * shares the channel with everyone else.
	 *
	 * The place reads "City, Country", or whichever of the two the record carries, because an
	 * institution's country alone does not tell two universities in one country apart.
	 *
	 * @param array $row   The index row.
	 * @param int   $total How many institutions are at Confirmed, this one included.
	 * @return string
	 */
	public static function message( array $row, $total ) {
		$name  = trim( (string) ( isset( $row['name'] ) ? $row['name'] : '' ) );
		$name  = '' !== $name ? $name : __( 'An institution', 'wpcredits-program-manager' );
		$name  = WPCPM_Slack::escape( $name );
		$place = self::place( $row );

		$line = '' !== $place
			/* translators: 1: an institution's name, 2: its city and country. */
			? sprintf( __( ':tada: *%1$s* has joined WordPress Credits, from %2$s.', 'wpcredits-program-manager' ), $name, $place )
			/* translators: %s: an institution's name. */
			: sprintf( __( ':tada: *%s* has joined WordPress Credits.', 'wpcredits-program-manager' ), $name );

		$manager = self::manager( $row );

		if ( '' !== $manager ) {
			$line .= "\n" . sprintf(
				/* translators: %s: the name of the Administrator the institution is assigned to. */
				__( 'Their Administrator is %s.', 'wpcredits-program-manager' ),
				$manager
			);
		}

		return $line . "\n" . self::tally( (int) $total );
	}

	/**
	 * One message for several institutions at once.
	 *
	 * @param array $news  The new rows, keyed by record ID.
	 * @param int   $total How many institutions are at Confirmed.
	 * @return string
	 */
	public static function many( array $news, $total ) {
		$names = array();

		foreach ( $news as $row ) {
			$name  = trim( (string) ( isset( $row['name'] ) ? $row['name'] : '' ) );
			$place = self::place( is_array( $row ) ? $row : array() );

			// The same fallback the single message uses (1.123.57). Skipping the row left it out
			// of the message while `after_sync()` recorded its ID with the rest, so an
			// institution whose name reached Airtable later was never announced at all; and the
			// heading counted the named ones, which read one short of the batch.
			$name = '' !== $name ? $name : __( 'An institution', 'wpcredits-program-manager' );
			$name = WPCPM_Slack::escape( $name );

			/* translators: 1: an institution's name, 2: its city and country. */
			$names[] = '' !== $place ? sprintf( __( '%1$s (%2$s)', 'wpcredits-program-manager' ), $name, $place ) : $name;
		}

		$heading = sprintf(
			/* translators: %s: a number of institutions. */
			_n(
				':tada: %s institution has joined WordPress Credits:',
				':tada: %s institutions have joined WordPress Credits:',
				count( $names ),
				'wpcredits-program-manager'
			),
			number_format_i18n( count( $names ) )
		);

		$list = '';

		foreach ( $names as $name ) {
			$list .= "\n" . sprintf( '- %s', $name );
		}

		return $heading . $list . "\n" . self::tally( (int) $total );
	}

	/**
	 * The Administrator the institution is assigned to, by name, or ''.
	 *
	 * "Administrator" is the word, not "program manager": the owner settled on 29 September
	 * 2026 that the Administrators are called that in every string the plugin shows, and
	 * bin/test-admin-menu.php refuses any string that says otherwise. The issue that asked for
	 * this line (WPCredits#282) calls the person a program manager, which is what the handbook
	 * calls them; the site does not.
	 *
	 * Asked of the institution's own assignment (`WPCPM_Institution_Assignment`), which answers only
	 * for an account that still holds the manage capability, so the line is left out for an institution
	 * nobody is assigned to and for an Administrator who is no longer one, and the institution is still
	 * announced without it.
	 *
	 * @param array $row The index row.
	 * @return string
	 */
	private static function manager( array $row ) {
		$record = trim( (string) ( isset( $row['record_id'] ) ? $row['record_id'] : '' ) );

		if ( '' === $record || ! class_exists( 'WPCPM_Institution_Assignment' ) ) {
			return '';
		}

		$contact = WPCPM_Institution_Assignment::contact_of( $record );

		return null === $contact ? '' : WPCPM_Slack::escape( $contact['name'] );
	}

	/**
	 * "City, Country", or whichever the record carries, or ''.
	 *
	 * The country is the name the countries map resolved (`country_name`), never the record ID
	 * the link field holds, which would read as `recXXXXXXXXXXXXXX` in the channel.
	 *
	 * @param array $row The index row.
	 * @return string
	 */
	private static function place( array $row ) {
		$city    = WPCPM_Slack::escape( trim( (string) ( isset( $row['city'] ) ? $row['city'] : '' ) ) );
		$country = WPCPM_Slack::escape( trim( (string) ( isset( $row['country_name'] ) ? $row['country_name'] : '' ) ) );

		if ( '' !== $city && '' !== $country ) {
			/* translators: 1: a city, 2: a country. */
			return sprintf( __( '%1$s, %2$s', 'wpcredits-program-manager' ), $city, $country );
		}

		return '' !== $country ? $country : $city;
	}

	/**
	 * The running total, the sentence every message ends on.
	 *
	 * @param int $total How many institutions are at Confirmed.
	 * @return string
	 */
	private static function tally( $total ) {
		return sprintf(
			/* translators: %s: a number of institutions. */
			_n(
				'That makes %s confirmed institution.',
				'That makes %s confirmed institutions.',
				(int) $total,
				'wpcredits-program-manager'
			),
			number_format_i18n( (int) $total )
		);
	}
}
