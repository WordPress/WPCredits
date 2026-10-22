<?php
/**
 * The one road a sponsor's expression of interest takes: the dated line, the mail and the log.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An interest is recorded on the site, mailed to the assigned manager and logged. The history lives
 * in the sponsor's own record, so the Sponsors screen holds it without asking anywhere else (design
 * spec of 4 September 2026, section 5.7), and each line it files is an audit row as well, which the
 * Sponsors screen's Interests tab and the Administrator Dashboard's New interests card read.
 *
 * Until 1.123.22 this class drew the Sponsor Dashboard's Offer more card too: six checkboxes, a list
 * of events and a note. Its choices were the sponsor application's before the levels, which the
 * application stopped asking in 1.123.12 and the program's partner page names none of, and its Tell
 * us said what the Sponsorship card's Talk to your program contact says, so the card left the page
 * and its form's handler with it: nothing else posted to it. What files through this class stays:
 * that button (`WPCPM_Sponsor_Tier_Card`), which files its line through `file()` and is held to
 * this class's ceiling of five a day per account, and every mail to a sponsor's manager, which takes
 * `mail_manager()`. `CHOICES` stays as the words a row's `support` list holds, which the Sponsors
 * screen's Edit form offers and keeps.
 */
final class WPCPM_Sponsor_Interests {

	const PER_DAY      = 5;
	const CEILING      = 'sponsor-interest';
	const MAIL_CONTEXT = 'sponsor-interest';
	const LOG_KIND     = 'sponsor_interest';

	/** The six choices, spelled exactly as a row's `support` list stores them: renaming one would stop it matching what rows already hold. */
	const CHOICES = array(
		'Provide financial support (for program costs)',
		'Sponsor a member of the WP Credits admin team',
		'Sponsor a mentor or multiple mentors',
		'Sponsor a scholarship for students to attend flagship events',
		'Sponsor tools or services',
		'Other (please specify)',
	);

	/**
	 * The dated line appended to the history the sponsor's row keeps in `interests`.
	 *
	 * @param string[] $choices The ticked choices.
	 * @param string[] $events  The events named.
	 * @param string   $note    The note.
	 * @param string   $who     The display name of the person.
	 * @param string   $date    `Y-m-d`.
	 * @return string
	 */
	public static function line( array $choices, array $events, $note, $who, $date ) {
		$parts = array();

		if ( ! empty( $choices ) ) {
			$parts[] = implode( '; ', $choices );
		}

		if ( ! empty( $events ) ) {
			$parts[] = 'events: ' . implode( ', ', $events );
		}

		if ( '' !== trim( (string) $note ) ) {
			$parts[] = 'note: ' . trim( (string) $note );
		}

		return $date . ' by ' . $who . ': ' . implode( '; ', $parts );
	}

	/**
	 * File one expression of interest: the dated line appended to the history the row keeps in
	 * `interests` (and the ticked choices written to `support` when there are any), the store
	 * patched, the sponsor's program manager mailed through `mail_manager()` and the audit row
	 * written.
	 *
	 * The one road every interest takes (1.112.0): the tier card's Talk to your program contact button
	 * (`WPCPM_Sponsor_Tier_Card`) validates on its own and then ends here, as the Offer more form did
	 * until it left the Sponsor Dashboard in 1.123.22, so the line, the mail and the log are written in
	 * one place. The caller has checked the nonce, claimed the sponsor, found its row, taken the
	 * ceiling and made sure there is something to say.
	 *
	 * @param string   $record  Sponsor key.
	 * @param array    $row     The sponsor's row, as the claim read it.
	 * @param string[] $choices The ticked choices; none leaves `support` as the row holds it.
	 * @param string[] $events  The events named.
	 * @param string   $note    The note, as the cleaner left it, with its line breaks.
	 * @param string   $who     The display name of the person filing it.
	 * @param string   $ground  The ground the claim decided on, for the audit row.
	 * @param string   $today   The line's date, `Y-m-d`, or '' for today (`wp_date()`).
	 * @return array `ok` (false when the store refused the write; nothing is then mailed or
	 *               logged), `mailed` (how many were sent) and `line` (the dated line, as the
	 *               history holds it).
	 */
	public static function file( $record, array $row, array $choices, array $events, $note, $who, $ground, $today = '' ) {
		// Two forms of the one dated line. The history holds one line per message, read line by
		// line, so there the note's white space is folded as the one-line cleaner folds it (each run
		// of line breaks, tabs and spaces one space), and the text is kept as the cleaner stored it,
		// as every line before it was. The mail carries the note as
		// it was typed: its lines, and the characters the cleaner wrote as entities, which the mail
		// reads back once, as it reads every value (`WPCPM_Mail_Templates::fill()`).
		$date = '' !== $today ? $today : wp_date( 'Y-m-d' );
		$line = self::line( $choices, $events, (string) preg_replace( '/[\r\n\t ]+/', ' ', (string) $note ), $who, $date );
		$said = self::line( $choices, $events, $note, $who, $date );

		// The history on the row the claim just read. The store is the only copy of it, so there
		// is no fresher one to fetch before appending to it.
		$history = trim( (string) $row['interests'] );
		$patch   = array( 'interests' => '' === $history ? $line : $history . "\n" . $line );

		// The multiple select is written only when something was ticked: unticking everything
		// is not a way to erase what the row already holds.
		if ( ! empty( $choices ) ) {
			$patch['support'] = $choices;
		}

		if ( ! WPCPM_Sponsors_Index::patch( $record, $patch ) ) {
			return array(
				'ok'     => false,
				'mailed' => 0,
				'line'   => $line,
			);
		}

		$sponsor = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
		$build   = static function () use ( $sponsor, $said ) {
			return WPCPM_Mail_Templates::mail(
				self::MAIL_CONTEXT,
				array(
					'site_name'    => WPCPM_Mail::site_name(),
					'company_name' => $sponsor,
					'interest'     => $said,
				)
			);
		};

		$mailed = self::mail_manager( $record, $build );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_KIND,
				'sponsor'  => $record,
				'subject'  => $record,
				'actor'    => get_current_user_id(),
				'ground'   => $ground,
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => $line,
				'data'     => array(
					'choices' => $choices,
					'events'  => count( $events ),
					'mailed'  => (int) $mailed,
				),
			)
		);

		return array(
			'ok'     => true,
			'mailed' => (int) $mailed,
			'line'   => $line,
		);
	}

	/**
	 * Mail the sponsor's assigned program manager, or every manager when none is assigned.
	 *
	 * @param string   $record  Sponsor key.
	 * @param callable $build   The mail builder `WPCPM_Mail::send()` takes.
	 * @param string   $context The mail log's context; an interest's own by default. Every other mail
	 *                          to a sponsor's manager routes through here under its own: the
	 *                          agreement receipt (WPCPM_Sponsor_Agreement), the request to sponsor
	 *                          a mentor (WPCPM_Sponsor_Mentors), and the low-stock and claim-problem
	 *                          mails (WPCPM_Sponsor_Claims), so each takes the one road: the
	 *                          assigned manager, else `sponsor_notify`, else every manager.
	 * @return int How many were sent.
	 */
	public static function mail_manager( $record, $build, $context = self::MAIL_CONTEXT ) {
		$manager = WPCPM_Sponsors_Index::manager_of( $record );

		if ( is_array( $manager ) && is_email( $manager['email'] ) ) {
			$user = get_user_by( 'email', $manager['email'] );

			if ( $user instanceof WP_User && $user->exists() ) {
				return WPCPM_Mail::send( $user, $context, $build ) ? 1 : 0;
			}

			return WPCPM_Mail::send_to( $manager['email'], $context, $build ) ? 1 : 0;
		}

		return (int) WPCPM_Institutions::notify_managers( $context, $build, 'sponsor_notify' );
	}
}
