<?php
/**
 * The one-off seed: what release 1 reads from the site instead of Airtable, filled once.
 *
 * `wp wpcredits institutions_seed [--dry-run]` fills the areas of responsibility, the Administrators' booking
 * links, each institution's assigned Administrator and each school's tutor list, from what Airtable holds
 * today. An Administrator runs it once after the release is deployed, first with `--dry-run`, which writes
 * nothing and prints the same summary.
 *
 * - **Areas.** Every country whose contact in the Countries table has an Administrator's account (found by
 *   the contact's address) is given that Administrator as its own. The regions are left empty, because the
 *   Countries table names countries only; Administrators move to regions on the Settings screen afterwards.
 *   The areas are written only while none are set, so a second run never replaces what an Administrator
 *   set by hand.
 * - **Booking links.** An Administrator's link from the Countries table is kept while the account has none.
 *   A link that is not an https address is not kept, and the summary names the Administrator.
 * - **Assignments.** An institution goes to its country's Administrator (the areas the site holds, else the
 *   ones this run would write); an institution with no Administrator for its country goes to the person its
 *   column `Original program manager` links in the Team Members table, whose row is resolved by its Email to
 *   an account, else by its Name compared, trimmed and case-insensitive, with an Administrator's display
 *   name. An institution that already has an Administrator keeps them, one set to Nobody on the site stays
 *   so, and one nobody resolves stays unassigned, with the reason.
 * - **Tutors.** Each row of the Tutors table is listed under the schools it links, unless the address is
 *   listed there already or was removed from that list on the site, and only under a school the index holds
 *   (a link to any other record is named, not written). R1's tutor picker reads the site's list, so this
 *   cannot wait for the switch.
 * - **Nothing is replaced, nothing is mailed.** An assignee, a listed tutor and a booking link already there
 *   are left alone; assignments are made without the email that tells an Administrator, and the history rows
 *   are the site's own (actor 0, ground `system`).
 *
 * The only reads of Airtable are the three `fetch_all()` calls of `plan()`, through the client it is handed;
 * the countries come from the stored map the sync keeps.
 *
 * @package WPCredits_Program_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Works out, then writes, the one-off seed.
 */
class WPCPM_Institution_Seed {

	/** The Team Members table. */
	const TEAM_TABLE = 'tblUYWUSEcRLJ5BaR';

	/** The Institutions column holding the school's name. */
	const FIELD_NAME = 'Name';

	/** The Institutions column linking the school to its country. */
	const FIELD_COUNTRY = 'Country';

	/** The Institutions column linking the school to the Team Members row that first handled it. */
	const FIELD_ORIGINAL = 'Original program manager';

	/** The Team Members columns the resolver reads. */
	const TEAM_NAME     = 'Name';
	const TEAM_EMAIL    = 'Email';
	const TEAM_CALENDLY = 'Calendly link';

	/** The Tutors columns: the tutor's name and address, and the schools the row links. */
	const TUTOR_NAME  = 'Name';
	const TUTOR_EMAIL = 'Email';
	const TUTOR_LINK  = 'Institutions Onboarding';

	/** Why an institution stays unassigned. */
	const WHY_NO_COUNTRY       = 'no_country';
	const WHY_NO_AREA          = 'no_area';
	const WHY_NO_MATCH         = 'no_match';
	const WHY_NO_COUNTRY_MATCH = 'no_country_match';

	/** Why a Team Members row stands for nobody. */
	const TEAM_NO_ACCOUNT = 'no_account';
	const TEAM_NOT_ADMIN  = 'not_admin';
	const TEAM_AMBIGUOUS  = 'ambiguous';
	const TEAM_NO_NAME    = 'no_name';

	/**
	 * What the seed would write.
	 *
	 * Reads three tables, each once, and writes nothing.
	 *
	 * @param WPCPM_Airtable $airtable The client.
	 * @return array|WP_Error The plan, or the refusal of a read:
	 *                        - `areas`: country record ID => the Administrator's user ID (empty while the site holds areas).
	 *                        - `areas_set`: whether the site holds areas already.
	 *                        - `booking`: user ID => the booking link to keep.
	 *                        - `assign`: institution record ID => `array( user ID, 'area' or 'original' )`.
	 *                        - `unassigned`: institution record ID => the reason (`WHY_*`).
	 *                        - `names`: unassigned institution record ID => its name.
	 *                        - `team`: the Team Members rows that stand for nobody and leave schools unassigned,
	 *                          each `array( 'name', 'address', 'reason' (`TEAM_*`), 'count' )`.
	 *                        - `map`: `array( 'count', 'read' )`, the stored countries map's size and read time.
	 *                        - `kept`: how many institutions have an Administrator already.
	 *                        - `nobody`: how many were set to Nobody on the site, and stay so.
	 *                        - `tutors`: institution record ID => list of `array( 'name', 'email' )` to add.
	 *                        - `unresolved`: each thing the seed could not place, as a sentence.
	 */
	public static function plan( WPCPM_Airtable $airtable ) {
		$institutions = $airtable->fetch_all(
			(string) WPCPM_Settings::get_value( 'institutions_table', '' ),
			array( 'fields' => array( self::FIELD_NAME, self::FIELD_COUNTRY, self::FIELD_ORIGINAL ) )
		);

		if ( is_wp_error( $institutions ) ) {
			return $institutions;
		}

		$team = $airtable->fetch_all( self::TEAM_TABLE, array( 'fields' => array( self::TEAM_NAME, self::TEAM_EMAIL, self::TEAM_CALENDLY ) ) );

		if ( is_wp_error( $team ) ) {
			return $team;
		}

		$tutor_rows = $airtable->fetch_all(
			(string) WPCPM_Settings::get_value( 'tutors_table', '' ),
			array( 'fields' => array( self::TUTOR_NAME, self::TUTOR_EMAIL, self::TUTOR_LINK ) )
		);

		if ( is_wp_error( $tutor_rows ) ) {
			return $tutor_rows;
		}

		$site  = WPCPM_Institution_Areas::areas();
		$set   = array() !== $site['countries'] || array() !== $site['regions'];
		$found = self::from_countries();

		$plan = array(
			'areas'      => $set ? array() : $found['areas'],
			'areas_set'  => $set,
			'booking'    => $found['booking'],
			'assign'     => array(),
			'unassigned' => array(),
			'names'      => array(),
			'team'       => array(),
			'map'        => array(
				'count' => count( WPCPM_Countries::all() ),
				'read'  => (int) WPCPM_Countries::read()['read'],
			),
			'kept'       => 0,
			'nobody'     => 0,
			'tutors'     => array(),
			// With areas on the site, the site's areas decide, not the map: what the map's contacts could not give is no news.
			'unresolved' => $set ? array() : $found['unresolved'],
			// The Administrators whose only link is not https, while the plan is made (1.123.84).
			'http'       => $found['http'],
		);

		self::plan_assignments( $plan, $institutions, $team, $set, $found['areas'] );
		self::plan_tutors( $plan, $tutor_rows );

		// A link that is not https is never written (`WPCPM_Manager_Booking::set()` refuses one too), and the
		// Administrator is named, unless an https link was found for them after all. Whatever the areas: the
		// booking links are seeded either way.
		foreach ( array_keys( $plan['http'] ) as $user_id ) {
			if ( ! isset( $plan['booking'][ $user_id ] ) ) {
				$plan['unresolved'][] = sprintf(
					/* translators: %s: an Administrator's name. */
					__( 'The booking link of %s is not an https address, so it was not added.', 'wpcredits-program-manager' ),
					WPCPM_Institution_Assignment::label( $user_id )
				);
			}
		}

		unset( $plan['http'] );

		return $plan;
	}

	/**
	 * Write a plan, never replacing anything already there.
	 *
	 * Every part is asked again as it is written: the areas only while none are set, a booking link only while
	 * the account has none, an assignment only while the school has none and was not set to Nobody, asked under the school's lock, and a
	 * tutor only while the address is not listed. No email is sent, and the history rows are the site's own.
	 *
	 * @param array $plan What `plan()` returned.
	 * @return array What was written: `areas` (countries given an Administrator), `areas_kept` (the site held
	 *               areas, so none were written), `booking`, `assigned_area`, `assigned_original`, `tutors`,
	 *               and `failed`, a sentence for each write the site refused and each school whose lock another
	 *               request held.
	 */
	public static function apply( array $plan ) {
		$done = array(
			'areas'             => 0,
			'areas_kept'        => false,
			'booking'           => 0,
			'assigned_area'     => 0,
			'assigned_original' => 0,
			'tutors'            => 0,
			'failed'            => array(),
		);

		$site = WPCPM_Institution_Areas::areas();

		if ( array() !== $site['countries'] || array() !== $site['regions'] ) {
			$done['areas_kept'] = true;
		} elseif ( ! empty( $plan['areas'] ) ) {
			WPCPM_Settings::patch(
				array(
					'institution_areas' => array(
						'countries' => $plan['areas'],
						'regions'   => array(),
					),
				)
			);

			$done['areas'] = count( WPCPM_Institution_Areas::areas()['countries'] );

			if ( $done['areas'] < count( $plan['areas'] ) ) {
				$done['failed'][] = __( 'The site refused the areas, so none was written.', 'wpcredits-program-manager' );
			}
		}

		foreach ( ! empty( $plan['booking'] ) ? $plan['booking'] : array() as $user_id => $link ) {
			if ( '' !== WPCPM_Manager_Booking::url_for( $user_id ) ) {
				continue;
			}

			if ( WPCPM_Manager_Booking::set( $user_id, $link ) ) {
				++$done['booking'];
			} else {
				$done['failed'][] = sprintf(
					/* translators: %d: an account's ID. */
					__( 'The booking link of the account %d could not be kept.', 'wpcredits-program-manager' ),
					(int) $user_id
				);
			}
		}

		foreach ( ! empty( $plan['assign'] ) ? $plan['assign'] : array() as $record_id => $pick ) {
			// The record's answers are read and written under the school's lock, as the details card's save does.
			$lock  = WPCPM_Institution_Request::school_lock_name( $record_id );
			$token = WPCPM_Lock::take( $lock, WPCPM_Institution_Request::LOCK_TTL );

			if ( '' === $token ) {
				$done['failed'][] = sprintf(
					/* translators: %s: an institution's record ID. */
					__( 'The institution %s was not assigned: somebody else was changing its record. Run the command again.', 'wpcredits-program-manager' ),
					$record_id
				);

				continue;
			}

			$result = false;

			try {
				WPCPM_Institution_Record::forget_cached( $record_id );

				if ( 0 === WPCPM_Institution_Assignment::stored_of( $record_id ) && ! WPCPM_Institution_Assignment::has_choice( $record_id ) ) {
					$result = WPCPM_Institution_Assignment::assign( $record_id, (int) $pick[0], 0, false );
				}
			} finally {
				WPCPM_Lock::release( $lock, $token );
			}

			if ( true === $result ) {
				++$done[ 'area' === $pick[1] ? 'assigned_area' : 'assigned_original' ];
			} elseif ( is_wp_error( $result ) ) {
				$done['failed'][] = sprintf(
					/* translators: 1: an institution's record ID, 2: why it was refused. */
					__( 'The institution %1$s was not assigned: %2$s', 'wpcredits-program-manager' ),
					$record_id,
					$result->get_error_message()
				);
			}
		}

		foreach ( ! empty( $plan['tutors'] ) ? $plan['tutors'] : array() as $record_id => $list ) {
			foreach ( $list as $tutor ) {
				$result = WPCPM_Institution_Tutors::add( $record_id, $tutor['name'], $tutor['email'], 0, WPCPM_Institution_Audit::GROUND_SYSTEM );

				if ( true === $result ) {
					++$done['tutors'];
				} elseif ( is_wp_error( $result ) ) {
					$done['failed'][] = sprintf(
						/* translators: 1: an institution's record ID, 2: why it was refused. */
						__( 'A tutor of the institution %1$s was not listed: %2$s', 'wpcredits-program-manager' ),
						$record_id,
						$result->get_error_message()
					);
				}
			}
		}

		return $done;
	}

	/**
	 * The plan as the short plain sentences an Administrator reads, one fact each.
	 *
	 * @param array $plan What `plan()` returned.
	 * @return string[] The lines: the areas, the booking links, the assignments, the schools left unassigned
	 *                  and why, the tutors, and then each thing that could not be placed.
	 */
	public static function summary( array $plan ) {
		$lines  = array();
		$areas  = isset( $plan['areas'] ) ? count( $plan['areas'] ) : 0;
		$book   = isset( $plan['booking'] ) ? count( $plan['booking'] ) : 0;
		$by     = array(
			'area'     => 0,
			'original' => 0,
		);
		$why    = array(
			self::WHY_NO_COUNTRY       => 0,
			self::WHY_NO_AREA          => 0,
			self::WHY_NO_MATCH         => 0,
			self::WHY_NO_COUNTRY_MATCH => 0,
		);
		$tutors = 0;

		foreach ( isset( $plan['assign'] ) ? $plan['assign'] : array() as $pick ) {
			++$by[ $pick[1] ];
		}

		foreach ( isset( $plan['unassigned'] ) ? $plan['unassigned'] : array() as $reason ) {
			++$why[ $reason ];
		}

		foreach ( isset( $plan['tutors'] ) ? $plan['tutors'] : array() as $list ) {
			$tutors += count( $list );
		}

		if ( isset( $plan['map'] ) ) {
			$count = (int) $plan['map']['count'];

			if ( 0 === $count ) {
				$lines[] = __( 'The countries map is empty: run the institutions sync on the Institutions screen\'s Sync and storage tab first.', 'wpcredits-program-manager' );
			} elseif ( (int) $plan['map']['read'] > 0 ) {
				$lines[] = sprintf(
					/* translators: 1: how many countries, 2: a date. */
					_n( 'The countries map holds %1$d country, read on %2$s.', 'The countries map holds %1$d countries, read on %2$s.', $count, 'wpcredits-program-manager' ),
					$count,
					wp_date( get_option( 'date_format' ), (int) $plan['map']['read'] )
				);
			} else {
				/* translators: %d: how many countries. */
				$lines[] = sprintf( _n( 'The countries map holds %d country.', 'The countries map holds %d countries.', $count, 'wpcredits-program-manager' ), $count );
			}
		}

		if ( ! empty( $plan['areas_set'] ) ) {
			$lines[] = __( 'The areas are already set on the site, so they were left as they are.', 'wpcredits-program-manager' );
		} elseif ( $areas > 0 ) {
			/* translators: %d: how many countries. */
			$lines[] = sprintf( _n( '%d country is given an Administrator.', '%d countries are given an Administrator.', $areas, 'wpcredits-program-manager' ), $areas );
		} else {
			$lines[] = __( 'No country is given an Administrator.', 'wpcredits-program-manager' );
		}

		if ( $book > 0 ) {
			/* translators: %d: how many Administrators. */
			$lines[] = sprintf( _n( '%d Administrator is given a booking link.', '%d Administrators are given a booking link.', $book, 'wpcredits-program-manager' ), $book );
		} else {
			$lines[] = __( 'No booking link is added.', 'wpcredits-program-manager' );
		}

		if ( $by['area'] > 0 ) {
			/* translators: %d: how many institutions. */
			$lines[] = sprintf( _n( '%d institution is assigned to its country\'s Administrator.', '%d institutions are assigned to their countries\' Administrators.', $by['area'], 'wpcredits-program-manager' ), $by['area'] );
		}

		if ( $by['original'] > 0 ) {
			/* translators: %d: how many institutions. */
			$lines[] = sprintf( _n( '%d institution is assigned to the person who first handled it.', '%d institutions are assigned to the people who first handled them.', $by['original'], 'wpcredits-program-manager' ), $by['original'] );
		}

		if ( 0 === $by['area'] + $by['original'] ) {
			$lines[] = __( 'No institution is assigned.', 'wpcredits-program-manager' );
		}

		if ( ! empty( $plan['kept'] ) ) {
			/* translators: %d: how many institutions. */
			$lines[] = sprintf( _n( '%d institution has an Administrator already and keeps them.', '%d institutions have an Administrator already and keep them.', (int) $plan['kept'], 'wpcredits-program-manager' ), (int) $plan['kept'] );
		}

		if ( ! empty( $plan['nobody'] ) ) {
			/* translators: %d: how many institutions. */
			$lines[] = sprintf( _n( '%d institution was set to Nobody on this site and stays unassigned.', '%d institutions were set to Nobody on this site and stay unassigned.', (int) $plan['nobody'], 'wpcredits-program-manager' ), (int) $plan['nobody'] );
		}

		if ( $why[ self::WHY_NO_AREA ] > 0 ) {
			/* translators: %d: how many institutions. */
			$lines[] = sprintf( _n( '%d institution stays unassigned: no Administrator covers its country and nobody is recorded as having first handled it.', '%d institutions stay unassigned: no Administrator covers their country and nobody is recorded as having first handled them.', $why[ self::WHY_NO_AREA ], 'wpcredits-program-manager' ), $why[ self::WHY_NO_AREA ] );
		}

		if ( $why[ self::WHY_NO_MATCH ] > 0 ) {
			/* translators: %d: how many institutions. */
			$lines[] = sprintf( _n( '%d institution stays unassigned: no Administrator covers its country and the person who first handled it is not an Administrator here.', '%d institutions stay unassigned: no Administrator covers their country and the people who first handled them are not Administrators here.', $why[ self::WHY_NO_MATCH ], 'wpcredits-program-manager' ), $why[ self::WHY_NO_MATCH ] );
		}

		if ( $why[ self::WHY_NO_COUNTRY_MATCH ] > 0 ) {
			/* translators: %d: how many institutions. */
			$lines[] = sprintf( _n( '%d institution stays unassigned: it has no country and the person who first handled it is not an Administrator here.', '%d institutions stay unassigned: they have no country and the people who first handled them are not Administrators here.', $why[ self::WHY_NO_COUNTRY_MATCH ], 'wpcredits-program-manager' ), $why[ self::WHY_NO_COUNTRY_MATCH ] );
		}

		if ( $why[ self::WHY_NO_COUNTRY ] > 0 ) {
			/* translators: %d: how many institutions. */
			$lines[] = sprintf( _n( '%d institution stays unassigned: it has no country and nobody is recorded as having first handled it.', '%d institutions stay unassigned: they have no country and nobody is recorded as having first handled them.', $why[ self::WHY_NO_COUNTRY ], 'wpcredits-program-manager' ), $why[ self::WHY_NO_COUNTRY ] );
		}

		if ( $tutors > 0 ) {
			$schools = count( $plan['tutors'] );
			$lines[] = sprintf(
				/* translators: 1: how many tutors are listed, as a phrase, 2: how many institutions, as a phrase. */
				__( '%1$s, at %2$s.', 'wpcredits-program-manager' ),
				/* translators: %d: how many tutors. */
				sprintf( _n( '%d tutor is added', '%d tutors are added', $tutors, 'wpcredits-program-manager' ), $tutors ),
				/* translators: %d: how many institutions. */
				sprintf( _n( '%d institution', '%d institutions', $schools, 'wpcredits-program-manager' ), $schools )
			);
		} else {
			$lines[] = __( 'No tutor is added.', 'wpcredits-program-manager' );
		}

		foreach ( isset( $plan['unassigned'] ) ? $plan['unassigned'] : array() as $record_id => $reason ) {
			$name = isset( $plan['names'][ $record_id ] ) ? trim( (string) $plan['names'][ $record_id ] ) : '';

			$lines[] = sprintf(
				/* translators: 1: an institution's name, 2: why nobody could be found for it. */
				__( 'Not assigned: %1$s: %2$s.', 'wpcredits-program-manager' ),
				'' !== $name ? $name : $record_id,
				self::why_text( $reason )
			);
		}

		foreach ( isset( $plan['team'] ) ? $plan['team'] : array() as $row ) {
			$who = '' !== $row['address'] && '' !== $row['name'] ? $row['name'] . ' (' . $row['address'] . ')' : ( '' !== $row['name'] ? $row['name'] : $row['address'] );

			$lines[] = sprintf(
				/* translators: 1: a person's name and address, 2: why they match no Administrator, 3: how many institutions. */
				_n( 'Not resolved: %1$s: %2$s; %3$d institution stays unassigned.', 'Not resolved: %1$s: %2$s; %3$d institutions stay unassigned.', (int) $row['count'], 'wpcredits-program-manager' ),
				$who,
				self::team_text( $row['reason'] ),
				(int) $row['count']
			);
		}

		return array_merge( $lines, isset( $plan['unresolved'] ) ? array_values( $plan['unresolved'] ) : array() );
	}

	/**
	 * Why one institution stays unassigned, as the end of a sentence.
	 *
	 * @param string $reason A `WHY_*` constant.
	 * @return string
	 */
	private static function why_text( $reason ) {
		switch ( $reason ) {
			case self::WHY_NO_COUNTRY:
				return __( 'it has no country and nobody is recorded as having first handled it', 'wpcredits-program-manager' );
			case self::WHY_NO_MATCH:
				return __( 'no Administrator covers its country and the person who first handled it is not an Administrator here', 'wpcredits-program-manager' );
			case self::WHY_NO_COUNTRY_MATCH:
				return __( 'it has no country and the person who first handled it is not an Administrator here', 'wpcredits-program-manager' );
			default:
				return __( 'no Administrator covers its country and nobody is recorded as having first handled it', 'wpcredits-program-manager' );
		}
	}

	/**
	 * Why a Team Members row stands for no Administrator, in plain words.
	 *
	 * @param string $reason A `TEAM_*` constant.
	 * @return string
	 */
	private static function team_text( $reason ) {
		switch ( $reason ) {
			case self::TEAM_NO_ACCOUNT:
				return __( 'no account has this address', 'wpcredits-program-manager' );
			case self::TEAM_NOT_ADMIN:
				return __( 'the account with this address is not an Administrator', 'wpcredits-program-manager' );
			case self::TEAM_AMBIGUOUS:
				return __( 'two Administrators have this name', 'wpcredits-program-manager' );
			default:
				return __( 'no Administrator has this name', 'wpcredits-program-manager' );
		}
	}

	/**
	 * Each country's Administrator and booking link, from the stored map's contact.
	 *
	 * @return array{areas: array<string, int>, booking: array<int, string>, http: array<int, true>, unresolved: string[]}
	 *         `http` holds the Administrators whose link is not an https address, which is not kept.
	 */
	private static function from_countries() {
		$areas    = array();
		$booking  = array();
		$http     = array();
		$no_mail  = array();
		$no_acct  = array();
		$no_admin = array();

		foreach ( WPCPM_Countries::all() as $country_id => $row ) {
			$route = WPCPM_Countries::routing( $country_id );

			if ( null === $route ) {
				continue;
			}

			$name    = (string) $route['name'];
			$address = strtolower( trim( (string) $route['email'] ) );

			if ( '' === $address ) {
				$no_mail[] = $name;

				continue;
			}

			$user = get_user_by( 'email', $address );

			if ( ! $user instanceof WP_User ) {
				$no_acct[ $address ][] = $name;

				continue;
			}

			if ( ! user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
				$no_admin[ $address ][] = $name;

				continue;
			}

			$areas[ (string) $country_id ] = (int) $user->ID;
			$link                          = trim( (string) $route['calendly'] );

			if ( '' !== $link && ! isset( $booking[ $user->ID ] ) && '' === WPCPM_Manager_Booking::url_for( $user->ID ) ) {
				if ( 0 === stripos( $link, 'https://' ) ) {
					$booking[ (int) $user->ID ] = $link;
				} else {
					$http[ (int) $user->ID ] = true;
				}
			}
		}

		$unresolved = array();

		if ( array() !== $no_mail ) {
			sort( $no_mail );
			$unresolved[] = sprintf(
				/* translators: %s: country names. */
				_n( 'The Countries table gives a contact but no address for %s, so it is given no Administrator.', 'The Countries table gives a contact but no address for %s, so they are given no Administrator.', count( $no_mail ), 'wpcredits-program-manager' ),
				self::joined( $no_mail )
			);
		}

		ksort( $no_acct );

		foreach ( $no_acct as $address => $names ) {
			sort( $names );
			$unresolved[] = sprintf(
				/* translators: 1: an email address, 2: country names. */
				_n( 'The Countries table names %1$s as the contact for %2$s, but no account on this site has that address, so it is given no Administrator.', 'The Countries table names %1$s as the contact for %2$s, but no account on this site has that address, so they are given no Administrator.', count( $names ), 'wpcredits-program-manager' ),
				$address,
				self::joined( $names )
			);
		}

		ksort( $no_admin );

		foreach ( $no_admin as $address => $names ) {
			sort( $names );
			$unresolved[] = sprintf(
				/* translators: 1: an email address, 2: country names. */
				_n( 'The Countries table names %1$s as the contact for %2$s, but that account is not an Administrator, so it is given no Administrator.', 'The Countries table names %1$s as the contact for %2$s, but that account is not an Administrator, so they are given no Administrator.', count( $names ), 'wpcredits-program-manager' ),
				$address,
				self::joined( $names )
			);
		}

		return array(
			'areas'      => $areas,
			'booking'    => $booking,
			'http'       => $http,
			'unresolved' => $unresolved,
		);
	}

	/**
	 * Fill the plan's assignments.
	 *
	 * @param array $plan         The plan, changed in place.
	 * @param array $institutions The Institutions rows.
	 * @param array $team         The Team Members rows.
	 * @param bool  $set          Whether the site holds areas already.
	 * @param array $found_areas  The areas the countries' contacts give.
	 */
	private static function plan_assignments( array &$plan, array $institutions, array $team, $set, array $found_areas ) {
		$people   = array();
		$resolved = array();

		foreach ( $team as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) ) {
				$people[ (string) $row['id'] ] = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
			}
		}

		foreach ( $institutions as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['id'] ) || ! WPCPM_Mentors_Sync::is_record_id( $row['id'] ) ) {
				continue;
			}

			$record_id = (string) $row['id'];
			$cells     = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();

			if ( WPCPM_Institution_Assignment::stored_of( $record_id ) > 0 ) {
				++$plan['kept'];

				continue;
			}

			// Nobody chosen on the details card is a choice, and the seed never replaces one.
			if ( WPCPM_Institution_Assignment::has_choice( $record_id ) ) {
				++$plan['nobody'];

				continue;
			}

			$country = '';

			foreach ( WPCPM_Airtable::link_ids( isset( $cells[ self::FIELD_COUNTRY ] ) ? $cells[ self::FIELD_COUNTRY ] : null ) as $id ) {
				if ( WPCPM_Mentors_Sync::is_record_id( $id ) ) {
					$country = (string) $id;

					break;
				}
			}

			$admin = 0;

			if ( '' !== $country ) {
				$admin = $set ? WPCPM_Institution_Areas::administrator_for( $country ) : ( isset( $found_areas[ $country ] ) ? (int) $found_areas[ $country ] : 0 );
			}

			if ( $admin > 0 ) {
				$plan['assign'][ $record_id ] = array( $admin, 'area' );

				continue;
			}

			$linked = WPCPM_Airtable::link_ids( isset( $cells[ self::FIELD_ORIGINAL ] ) ? $cells[ self::FIELD_ORIGINAL ] : null );
			$by     = 0;

			foreach ( $linked as $team_id ) {
				if ( ! isset( $people[ $team_id ] ) ) {
					continue;
				}

				if ( ! isset( $resolved[ $team_id ] ) ) {
					$resolved[ $team_id ] = self::administrator_of( $people[ $team_id ] );
				}

				if ( $resolved[ $team_id ][0] > 0 ) {
					$by = $resolved[ $team_id ][0];

					// A booking link the countries map did not give and the site does not hold.
					$link = trim( WPCPM_Airtable::flatten( isset( $people[ $team_id ][ self::TEAM_CALENDLY ] ) ? $people[ $team_id ][ self::TEAM_CALENDLY ] : '' ) );

					if ( '' !== $link && ! isset( $plan['booking'][ $by ] ) && '' === WPCPM_Manager_Booking::url_for( $by ) ) {
						if ( 0 === stripos( $link, 'https://' ) ) {
							$plan['booking'][ $by ] = $link;
						} else {
							$plan['http'][ $by ] = true;
						}
					}

					break;
				}
			}

			if ( $by > 0 ) {
				$plan['assign'][ $record_id ] = array( $by, 'original' );

				continue;
			}

			$name                        = trim( WPCPM_Airtable::flatten( isset( $cells[ self::FIELD_NAME ] ) ? $cells[ self::FIELD_NAME ] : '' ) );
			$plan['names'][ $record_id ] = $name;

			if ( array() === $linked ) {
				$plan['unassigned'][ $record_id ] = '' === $country ? self::WHY_NO_COUNTRY : self::WHY_NO_AREA;

				continue;
			}

			$plan['unassigned'][ $record_id ] = '' === $country ? self::WHY_NO_COUNTRY_MATCH : self::WHY_NO_MATCH;

			foreach ( $linked as $team_id ) {
				if ( ! isset( $resolved[ $team_id ] ) ) {
					continue;
				}

				if ( ! isset( $plan['team'][ $team_id ] ) ) {
					$plan['team'][ $team_id ] = array(
						'name'    => trim( WPCPM_Airtable::flatten( isset( $people[ $team_id ][ self::TEAM_NAME ] ) ? $people[ $team_id ][ self::TEAM_NAME ] : '' ) ),
						'address' => strtolower( trim( WPCPM_Airtable::flatten( isset( $people[ $team_id ][ self::TEAM_EMAIL ] ) ? $people[ $team_id ][ self::TEAM_EMAIL ] : '' ) ) ),
						'reason'  => $resolved[ $team_id ][1],
						'count'   => 0,
					);
				}

				++$plan['team'][ $team_id ]['count'];
			}
		}

		$plan['team'] = array_values( $plan['team'] );
	}

	/**
	 * Fill the plan's tutors.
	 *
	 * @param array $plan       The plan, changed in place.
	 * @param array $tutor_rows The Tutors rows.
	 */
	private static function plan_tutors( array &$plan, array $tutor_rows ) {
		$no_address = 0;
		$no_school  = 0;
		$unknown    = array();
		$removed    = array();
		$taken_off  = 0;

		foreach ( $tutor_rows as $row ) {
			$cells   = is_array( $row ) && isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
			$name    = trim( WPCPM_Airtable::flatten( isset( $cells[ self::TUTOR_NAME ] ) ? $cells[ self::TUTOR_NAME ] : '' ) );
			$address = strtolower( trim( WPCPM_Airtable::flatten( isset( $cells[ self::TUTOR_EMAIL ] ) ? $cells[ self::TUTOR_EMAIL ] : '' ) ) );
			$schools = array_values( array_filter( WPCPM_Airtable::link_ids( isset( $cells[ self::TUTOR_LINK ] ) ? $cells[ self::TUTOR_LINK ] : null ), array( 'WPCPM_Mentors_Sync', 'is_record_id' ) ) );

			if ( '' === $name || '' === $address || ! is_email( $address ) ) {
				++$no_address;

				continue;
			}

			if ( array() === $schools ) {
				++$no_school;

				continue;
			}

			foreach ( $schools as $record_id ) {
				// A school the index does not hold is no school the site knows: named below, never written.
				if ( ! WPCPM_Institutions_Index::has( $record_id ) ) {
					$unknown[ $record_id ][ $address ] = true;

					continue;
				}

				$listed = array_column( WPCPM_Institution_Tutors::list_for( $record_id ), 'email' );
				$queued = isset( $plan['tutors'][ $record_id ] ) ? array_column( $plan['tutors'][ $record_id ], 'email' ) : array();

				if ( in_array( $address, $listed, true ) || in_array( $address, $queued, true ) ) {
					continue;
				}

				// A tutor somebody took off the school's list on the site stays off: a second run does not bring them back.
				if ( ! isset( $removed[ $record_id ] ) ) {
					$removed[ $record_id ] = WPCPM_Institution_Tutors::removed( $record_id );
				}

				if ( in_array( $address, $removed[ $record_id ], true ) ) {
					++$taken_off;

					continue;
				}

				$plan['tutors'][ $record_id ][] = array(
					'name'  => $name,
					'email' => $address,
				);
			}
		}

		if ( $no_address > 0 ) {
			$plan['unresolved'][] = sprintf(
				/* translators: %d: how many rows. */
				_n( '%d row of the Tutors table has no name or no valid address, so it is left out.', '%d rows of the Tutors table have no name or no valid address, so they are left out.', $no_address, 'wpcredits-program-manager' ),
				$no_address
			);
		}

		if ( $no_school > 0 ) {
			$plan['unresolved'][] = sprintf(
				/* translators: %d: how many rows. */
				_n( '%d row of the Tutors table links no institution, so it is left out.', '%d rows of the Tutors table link no institution, so they are left out.', $no_school, 'wpcredits-program-manager' ),
				$no_school
			);
		}

		if ( $taken_off > 0 ) {
			$plan['unresolved'][] = sprintf(
				/* translators: %d: how many tutors. */
				_n( '%d tutor of the Tutors table was removed from the school\'s list on this site, so they are not added again.', '%d tutors of the Tutors table were removed from their schools\' lists on this site, so they are not added again.', $taken_off, 'wpcredits-program-manager' ),
				$taken_off
			);
		}

		foreach ( $unknown as $record_id => $addresses ) {
			$count = count( $addresses );

			$plan['unresolved'][] = sprintf(
				/* translators: 1: how many tutors, 2: an Airtable record ID. */
				_n( 'The Tutors table links %1$d tutor to %2$s, a record the pipeline index does not hold, so the tutor is left out.', 'The Tutors table links %1$d tutors to %2$s, a record the pipeline index does not hold, so they are left out.', $count, 'wpcredits-program-manager' ),
				$count,
				$record_id
			);
		}
	}

	/**
	 * The Administrator a Team Members row stands for.
	 *
	 * Its Email resolves to an Administrator's account, else its Name, trimmed and compared case-insensitively
	 * with each Administrator's display name, names exactly one; else nobody, and the reason.
	 *
	 * @param array $cells The row's fields.
	 * @return array `array( user ID, reason )`: the ID is 0 for nobody, and the reason a `TEAM_*` constant ('' for a hit).
	 */
	private static function administrator_of( array $cells ) {
		$address = strtolower( trim( WPCPM_Airtable::flatten( isset( $cells[ self::TEAM_EMAIL ] ) ? $cells[ self::TEAM_EMAIL ] : '' ) ) );
		$reason  = '';

		if ( '' !== $address ) {
			$user = get_user_by( 'email', $address );

			if ( $user instanceof WP_User && user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
				return array( (int) $user->ID, '' );
			}

			$reason = $user instanceof WP_User ? self::TEAM_NOT_ADMIN : self::TEAM_NO_ACCOUNT;
		}

		$name = mb_strtolower( trim( WPCPM_Airtable::flatten( isset( $cells[ self::TEAM_NAME ] ) ? $cells[ self::TEAM_NAME ] : '' ) ), 'UTF-8' );

		if ( '' !== $name ) {
			$match = array();

			foreach ( WPCPM_Institution_Assignment::choices() as $user_id => $display ) {
				if ( mb_strtolower( trim( (string) $display ), 'UTF-8' ) === $name ) {
					$match[] = (int) $user_id;
				}
			}

			if ( 1 === count( $match ) ) {
				return array( $match[0], '' );
			}

			if ( count( $match ) > 1 ) {
				$reason = self::TEAM_AMBIGUOUS;
			}
		}

		return array( 0, '' !== $reason ? $reason : self::TEAM_NO_NAME );
	}

	/**
	 * Names as a sentence part: "A", "A and B", "A, B and C".
	 *
	 * @param string[] $names The names.
	 * @return string
	 */
	private static function joined( array $names ) {
		$last = array_pop( $names );

		if ( array() === $names ) {
			return (string) $last;
		}

		/* translators: 1: names separated by commas, 2: the last name. */
		return sprintf( __( '%1$s and %2$s', 'wpcredits-program-manager' ), implode( ', ', $names ), $last );
	}
}
