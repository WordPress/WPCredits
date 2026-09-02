<?php
/**
 * Institutions module - which institution is acting, and what it may reach.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The two levels of the fence's front door: `owns()` from cache, `claim()` from Airtable.
 *
 * All static. Nothing here decides anything: every question of the form "may this person
 * act on that record" is `WPCPM_Institution_Policy::decide()`'s, asked once, in one place.
 * What this class does is the work the policy refuses to do - resolve which institution the
 * viewer is acting as, gather the evidence a decision deserves, and, for anything that
 * writes or discloses a live record, read the Students row from the base first so the
 * decision is made against what Airtable says right now rather than against what the last
 * sync cached.
 *
 * Four rules are worth stating here because every bug this module has had broke one of
 * them. The shape of a record ID is checked before anything reaches the network, so a
 * pasted document cannot become an HTTP request. The cheap decision comes before the live
 * read, so a refusal costs nothing and a stranger cannot make the site fetch on demand.
 * The authoritative decision is made on the **Students** row's `Educational Institutions`
 * link and never on the reports row's own link: decision 19 of the design spec says which
 * side wins, and a report row that disagrees with its student is a data fault, not a
 * second opinion. And a live row leaves this class cut down to `disclosed_fields()`,
 * because a class that reads whole Students rows on an institution's behalf is the worst
 * place in the plugin to let `Accessibility needs` through.
 */
class WPCPM_Institution_Roster {

	/**
	 * The GET or POST argument a program manager switches institutions with.
	 *
	 * Read with `WPCPM_Request::text()` and never `key()`: `sanitize_key()` lowercases,
	 * and an Airtable record ID is case-sensitive, so `recAbC...` would arrive as
	 * `recabc...` and name nothing - silently, on a screen that would then show the
	 * manager their fallback institution as though they had asked for it.
	 */
	const ARG_VIEW = 'wpcpm_institution_view';

	/** A subject that is a Students row. */
	const TYPE_STUDENT = 'student';

	/** A subject that is a Students Reports row. */
	const TYPE_REPORT = 'report';

	/** The audit kind for the one refusal that is logged: the subject resolved live. */
	const LOG_REFUSED = 'claim_refused';

	/** The Students column naming the institution. Plural, capital I, unlike the reports one. */
	const FIELD_INSTITUTIONS = 'Educational Institutions';

	/** The Students Reports column linking a report to its Students row. Empty on all 795 rows today. */
	const FIELD_STUDENTS_LINK = 'Students';

	/** The column both tables carry, and the only join the base actually has. */
	const FIELD_EMAIL = 'Email';

	/**
	 * The `WPCPM_Mentors_Sync::fields()` key naming the one column withheld by name.
	 *
	 * A key and not the string, so that renaming the column in the base and in `fields()`
	 * keeps it withheld here without a second edit in a second file - which is the edit
	 * somebody would forget.
	 */
	const KEY_WITHHELD = 'student_access';

	/**
	 * The record types `claim()` knows how to resolve to a Students row.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array( self::TYPE_STUDENT, self::TYPE_REPORT );
	}

	/**
	 * Which `WPCPM_Mentors_Sync::fields()` keys institution-facing code may be handed.
	 *
	 * Written out one key at a time and never a loop over `fields()`, for the same reason
	 * the student card writes its cells out one at a time: a column added to the base
	 * arrives in `fields()` on somebody else's release, and a list that grew by itself would
	 * disclose it on that release without anybody deciding to. Every key here is a column the
	 * roster index already publishes to the institution (design spec 8.1), so nothing in this
	 * list is a disclosure the school could not already read from its own roster.
	 *
	 * `student_access` is the one that is missing on purpose, and `Notes` is missing because
	 * `fields()` has never named it - an allowlist built from keys is closed by construction
	 * against every column the plugin does not read at all.
	 *
	 * @return string[] Keys of `WPCPM_Mentors_Sync::fields()`.
	 */
	public static function disclosed_keys() {
		return array(
			'student_record_name',
			'student_email',
			'student_status',
			'student_institution',
			'student_start',
			'student_end',
			'student_mentor',
			'student_profile',
			'student_tutor',
			'student_tutors',
			'student_study',
			'student_import_key',
		);
	}

	/**
	 * The Students columns a claim may carry, by the name the base gives them.
	 *
	 * Names read from `WPCPM_Mentors_Sync::fields()` and never written out again here: the
	 * base's spelling is settled in one file, and a second copy of `Tutor ` with its trailing
	 * space is a bug waiting for the day somebody tidies one of them.
	 *
	 * The restriction is applied twice, on the request and on the way out, and both are
	 * wanted. On the request because a column that never crosses the wire cannot leak, and
	 * because `fetch_page()` takes a `fields` list - though only that one: `get_record()` has
	 * no such parameter, so the record-ID route cannot narrow its request at all and would be
	 * unfenced if the request were the only place. On the way out because `fields[]` is
	 * something this site asks Airtable for and not something Airtable promises: a renamed
	 * column, a value arriving under a name we did not send, or a client that one day stops
	 * forwarding the list would each put the whole row back. The outbound filter is the
	 * fence; the request-side list is the saving.
	 *
	 * It is unconditional rather than a property of the decision, so a program manager
	 * reading through this class is narrowed too. `Accessibility needs` was disclosed to the
	 * program to be accommodated, not to the school (design spec 7.5 and decision 14), and
	 * this class is the institution module's one door to a live Students row; a manager who
	 * needs the disclosure has the mentor's card and the sync, and neither of those comes
	 * through here. A fence that read the ground could be got wrong by a stale `$can_manage`,
	 * and this one has nothing to get wrong.
	 *
	 * @return string[] Airtable column names.
	 */
	public static function disclosed_fields() {
		$fields = WPCPM_Mentors_Sync::fields();

		// Subtracted by the name `fields()` gives it rather than by the key it was reached
		// through, because `fields()` runs through `wpcpm_mentors_fields`: a site that pointed
		// a disclosed key at the disclosure would otherwise hand it over under another name.
		$withheld = ( isset( $fields[ self::KEY_WITHHELD ] ) && is_string( $fields[ self::KEY_WITHHELD ] ) ) ? $fields[ self::KEY_WITHHELD ] : '';
		$names    = array();

		foreach ( self::disclosed_keys() as $key ) {
			$name = ( isset( $fields[ $key ] ) && is_string( $fields[ $key ] ) ) ? $fields[ $key ] : '';

			if ( '' !== $name && $name !== $withheld ) {
				$names[] = $name;
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Which institution this viewer is acting as, as a record ID, or ''.
	 *
	 * A record ID and not a user, because several people may act for one institution and
	 * the roster belongs to the institution rather than to whoever opened it. Three steps,
	 * in this order (design spec 5.5):
	 *
	 * 1. Under `CAP_MANAGE` only, the switcher argument, accepted when it is a well-formed
	 *    record ID **and** the pipeline index holds it. A manager may look at any institution
	 *    the site has read, and at nothing it has not.
	 * 2. The viewer's own membership.
	 * 3. For a manager alone, the first institution in index order with a live member.
	 *    `institution_of()` is '' for every manager - `attach()` refuses an administrator -
	 *    so without this step a manager's dashboard would resolve to nothing and refuse
	 *    itself. An institution with no account yet has no roster to land on, which is why
	 *    the fallback wants a member rather than merely a row.
	 *
	 * '' when nothing resolves, and a handler whose resolved institution is '' refuses
	 * outright rather than treating "no institution" as "any institution".
	 *
	 * @param int|WP_User|null $viewer     The person looking; null for the current user.
	 * @param bool             $can_manage Whether the caller has already established `CAP_MANAGE`.
	 * @return string Airtable record ID, or ''.
	 */
	public static function resolve_institution( $viewer, $can_manage ) {
		$viewer = WPCPM_Roles::resolve_user( $viewer );

		if ( ! $viewer instanceof WP_User || ! $viewer->exists() ) {
			return '';
		}

		// The caller's flag is re-checked rather than trusted. It is computed on the way in
		// and handed down through a shell, a strip and four cards, and a stale `true` passed
		// one level too far is exactly how a switcher ends up in a member's page. The
		// capability is the authority; the argument only says the caller has asked already.
		$can_manage = $can_manage && user_can( $viewer, WPCPM_Roles::CAP_MANAGE );

		if ( $can_manage ) {
			$asked = self::requested_view();

			if ( WPCPM_Mentors_Sync::is_record_id( $asked ) && WPCPM_Institutions_Index::has( $asked ) ) {
				return trim( $asked );
			}
		}

		$own = WPCPM_Institution_Members::institution_of( $viewer );

		if ( '' !== $own ) {
			return $own;
		}

		if ( ! $can_manage ) {
			return '';
		}

		return self::first_with_member();
	}

	/**
	 * The institutions the manager switcher offers, in index order.
	 *
	 * Every row the pipeline index holds, one entry each - not one per member, and not
	 * only the ones with accounts: provisioning an institution is done by looking at it
	 * first, and an institution with no member yet is precisely the one a manager needs
	 * to open. Index order is Airtable's order, so the list reads the same here as in the
	 * grid.
	 *
	 * @return array<string, string> Record ID to label.
	 */
	public static function switcher_options() {
		$options = array();

		foreach ( WPCPM_Institutions_Index::rows() as $record_id => $row ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
				continue;
			}

			$name = trim( isset( $row['name'] ) ? (string) $row['name'] : '' );

			// Two records in the base have no `Name` at all. An entry with an empty label is
			// one nobody can pick, so those fall back to the record ID: not a name, but it is
			// what the grid shows beside the row and it can be found.
			$options[ (string) $record_id ] = '' === $name ? (string) $record_id : $name;
		}

		return $options;
	}

	/**
	 * The cache-level decision, for rendering. No HTTP.
	 *
	 * A thin name over `decide()`, with the subject first because that is the order the
	 * caller has it in: it holds a subject and asks what may be done with it. Kept so that
	 * every render in the module reads the same way and so that the day a render needs to
	 * do something before or after the decision, it is done once here.
	 *
	 * @param array            $subject A subject built by one of the policy's `subject_*()` methods.
	 * @param string           $action  One of the policy's ACT_* constants.
	 * @param int|WP_User|null $user    The acting user; null for the current one.
	 * @return array The decision, as `WPCPM_Institution_Policy::decide()` returns it.
	 */
	public static function owns( array $subject, $action, $user = null ) {
		return WPCPM_Institution_Policy::decide( $action, $subject, $user );
	}

	/**
	 * What the site already holds about a record, as a subject.
	 *
	 * An account's stamp where there is an account, the roster index row where there is
	 * not, and an institution-less subject where the site holds nothing - which a member
	 * cannot pass and a manager can. That last case is the point: this is the cheap gate
	 * in front of `claim()`'s live read, and it has to fail closed for everybody who is
	 * not a manager without ever asking Airtable a question.
	 *
	 * The subject is a property of the record and not of whoever is asking, so nothing
	 * here reads the current user. Finding the index row for a Students record therefore
	 * means opening the rosters the last sync wrote, in order, until one holds it; each is
	 * an option read that the object cache serves once per request, and the walk stops at
	 * the first hit.
	 *
	 * @param string $record Airtable record ID.
	 * @param string $type   `student` (a Students row) or `report` (a Students Reports row).
	 * @return array A subject.
	 */
	public static function cached_subject( $record, $type ) {
		$record = trim( (string) $record );
		$type   = (string) $type;

		// An institution-less cached subject, built through the index-row builder with no
		// institution to open: the builder shape-checks both IDs before it reads anything,
		// so a pasted string opens no option and names no institution.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) || ! in_array( $type, self::types(), true ) ) {
			return WPCPM_Institution_Policy::subject_index_row( '', $record );
		}

		if ( self::TYPE_REPORT === $type ) {
			$student = WPCPM_Students_Sync::user_for_record( $record );

			// The account carries the stamp the students sync wrote, which is better evidence
			// than any row: it is the same key the detail view and the report route are
			// decided on, so the cheap answer and the rendered page cannot disagree.
			if ( $student instanceof WP_User && $student->exists() ) {
				return WPCPM_Institution_Policy::subject_student_account( $student->ID );
			}
		}

		$found = self::index_subject( $record, $type );

		return null === $found ? WPCPM_Institution_Policy::subject_index_row( '', $record ) : $found;
	}

	/**
	 * The live decision, for anything that writes or discloses a live record.
	 *
	 * Four steps, in this order, and the order is the whole of the security (design spec
	 * 5.3). The caller checks its nonce **before** calling: step 3 makes an HTTP request,
	 * and a cross-site POST must not be able to cause one.
	 *
	 * A `WP_Error` back is either the one refusal - byte for byte the same whether the
	 * record is somebody else's, unknown to Airtable, or on an institution whose agreement
	 * is outstanding, because four different answers would be a membership oracle - or the
	 * read failure itself, so a screen can say the base could not be reached rather than
	 * accuse the reader of reaching for somebody else's student.
	 *
	 * What comes back on a yes is **not the Students row**. It is the row cut down to
	 * `disclosed_fields()`, because this is the one call in the module that turns a record ID
	 * into live Airtable columns, and a fence that carried `Accessibility needs` and `Notes`
	 * through would be the worst place in the plugin to leak them.
	 *
	 * @param string           $record Airtable record ID being claimed.
	 * @param string           $action One of the policy's ACT_* constants.
	 * @param string           $type   `student` (a Students row) or `report` (a Students Reports row).
	 * @param int|WP_User|null $user   The acting user; null for the current one.
	 * @return array{record: array, decision: array}|WP_Error
	 */
	public static function claim( $record, $action, $type = self::TYPE_STUDENT, $user = null ) {
		$record = trim( (string) $record );
		$type   = (string) $type;

		// 1. Shape first, before anything reaches the network. A 4KB paste must not become a
		// request, and neither must a type this class cannot resolve to a Students row.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) || ! in_array( $type, self::types(), true ) ) {
			return WPCPM_Institution_Policy::refusal();
		}

		// 2. The cheap decision from what the site holds. A refusal here spends nothing, and
		// it is what keeps a stranger from making this site fetch from Airtable on demand.
		$pre = WPCPM_Institution_Policy::decide( $action, self::cached_subject( $record, $type ), $user );

		if ( empty( $pre['allowed'] ) ) {
			return WPCPM_Institution_Policy::refusal();
		}

		// 3. The live read of the Students row, resolved from a report ID when that is what
		// was given. A WP_Error is returned, never swallowed.
		$row = self::live_students_row( $record, $type );

		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$fields = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();

		// 4. The authoritative decision, on the Students row's `Educational Institutions`
		// link. The reports row's own link is not consulted, on purpose: decision 19 makes
		// the Students table the institution side's authority, and on the 758 addresses that
		// exist in both tables the two links agree, so a disagreement is a fault to be fixed
		// in the base rather than a second opinion to be honoured here.
		$decision = WPCPM_Institution_Policy::decide(
			$action,
			WPCPM_Institution_Policy::subject_live(
				$type,
				$record,
				WPCPM_Airtable::link_ids( isset( $fields[ self::FIELD_INSTITUTIONS ] ) ? $fields[ self::FIELD_INSTITUTIONS ] : array() )
			),
			$user
		);

		if ( empty( $decision['allowed'] ) ) {
			// The one refusal that is logged: the subject resolved, so there is something to
			// file the row under and somebody to answer for it. Refusals of cheap shapes stay
			// out of the log - logging those was a denial of service in an earlier design.
			self::log_live_refusal( $record, $decision, $user );

			return WPCPM_Institution_Policy::refusal();
		}

		return array(
			// Narrowed at the one door out, on top of the narrowing the readers already did.
			// Nothing wide lives inside this class today, but `claim()` is what the rest of the
			// plugin holds, and a read path added to `live_students_row()` on some later phase
			// must not be able to widen what leaves through here by forgetting one call.
			'record'   => self::disclosed_row( $row ),
			'decision' => $decision,
		);
	}

	/**
	 * The switcher argument as the request carries it, from the query string or the form.
	 *
	 * Both, because a manager-on-behalf form posts to `admin-post.php`, which sees the
	 * fields and not the query string of the screen the form was on; a handler that read
	 * only `$_GET` would not fail, it would quietly act on the manager's fallback
	 * institution instead. Neither read proves anything: the value is matched against the
	 * pipeline index before it is used, and the capability is checked before either.
	 *
	 * @return string
	 */
	private static function requested_view() {
		$asked = WPCPM_Request::text( self::ARG_VIEW );

		return '' === $asked ? WPCPM_Request::posted_text( self::ARG_VIEW ) : $asked;
	}

	/**
	 * The first institution in index order that somebody can act for.
	 *
	 * One user query rather than one per institution: asking `members_of()` down a
	 * 106-row index would be 106 meta queries on a manager's first page load. The live
	 * memberships are collected through `institution_of()`, which applies the stamp, the
	 * flag and the existence test in the one place they are defined, and the walk is then
	 * a key lookup on the index - so the case of a record ID is compared by PHP's own
	 * hashing rather than by the database's collation, which does not tell `recABC` from
	 * `recabc`.
	 *
	 * This is a landing place, not a fence: a manager may open any institution the index
	 * holds, and this only decides which one they see before they choose.
	 *
	 * @return string Airtable record ID, or ''.
	 */
	private static function first_with_member() {
		$live = array();

		$holders = get_users(
			array(
				'number'     => -1,
				'meta_key'   => WPCPM_Institution_Members::META_ACTIVE,
				'meta_value' => 1,
			)
		);

		foreach ( (array) $holders as $holder ) {
			$record = WPCPM_Institution_Members::institution_of( $holder );

			if ( '' !== $record ) {
				$live[ $record ] = true;
			}
		}

		if ( empty( $live ) ) {
			return '';
		}

		foreach ( array_keys( WPCPM_Institutions_Index::rows() ) as $record_id ) {
			if ( isset( $live[ $record_id ] ) ) {
				return (string) $record_id;
			}
		}

		return '';
	}

	/**
	 * The roster index row for a record, as a subject, or null when no roster holds it.
	 *
	 * The rosters the last sync wrote are the ones the counts option names, which is the
	 * same list `WPCPM_Roster_Index::write_all()` sweeps stale rosters from, so the two
	 * cannot drift apart. A Students record is a key lookup; a report record is a scan of
	 * each row's `reports` list, which is the only place the site holds that link.
	 *
	 * @param string $record A well-formed record ID.
	 * @param string $type   `student` or `report`.
	 * @return array|null A subject, or null.
	 */
	private static function index_subject( $record, $type ) {
		foreach ( array_keys( WPCPM_Roster_Index::counts()['institutions'] ) as $institution ) {
			if ( ! WPCPM_Mentors_Sync::is_record_id( $institution ) ) {
				continue;
			}

			$rows = WPCPM_Roster_Index::rows( $institution );

			if ( self::TYPE_STUDENT === $type ) {
				if ( isset( $rows[ $record ] ) ) {
					// The institution comes from the row's own value inside the builder, not from
					// the index this loop opened: the loop says which index to look in, the row
					// says whose student it is.
					return WPCPM_Institution_Policy::subject_index_row( $institution, $record );
				}

				continue;
			}

			foreach ( $rows as $students_record => $row ) {
				$reports = ( isset( $row['reports'] ) && is_array( $row['reports'] ) ) ? $row['reports'] : array();

				if ( in_array( $record, $reports, true ) ) {
					return WPCPM_Institution_Policy::subject_index_row( $institution, $students_record );
				}
			}
		}

		return null;
	}

	/**
	 * The Students row behind a record, read live.
	 *
	 * A Students record is read directly. A report record is resolved the way the base
	 * allows: its `Students` link when there is one, else the address, through
	 * `LOWER({Email}) = LOWER(%s)` so that a row stored as `Ann@Example.org` is still
	 * found. Zero matches and two-plus matches are both a refusal - fail closed. Two rows
	 * for one address inside one institution is a real state in the base (9 addresses),
	 * and "probably this one" is not an answer a fence may give.
	 *
	 * @param string $record A well-formed record ID.
	 * @param string $type   `student` or `report`.
	 * @return array|WP_Error `array( 'id' => string, 'fields' => array )`, or why not.
	 */
	private static function live_students_row( $record, $type ) {
		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );

		if ( self::TYPE_STUDENT === $type ) {
			return self::students_record( $airtable, $settings, $record );
		}

		$report = $airtable->get_record( $settings['reports_table'], $record );

		if ( is_wp_error( $report ) ) {
			return self::read_error( $report );
		}

		$fields = ( isset( $report['fields'] ) && is_array( $report['fields'] ) ) ? $report['fields'] : array();
		$links  = WPCPM_Airtable::link_ids( isset( $fields[ self::FIELD_STUDENTS_LINK ] ) ? $fields[ self::FIELD_STUDENTS_LINK ] : array() );

		// Both sides of this link are empty on every row of both tables today, measured on
		// 2 September 2026, so the address below is the route every claim takes. The link is
		// honoured first anyway, because the day somebody fills it in it is the better join,
		// and because a row that names two students is one nobody can resolve - a refusal,
		// for the same reason two email matches are.
		if ( count( $links ) > 1 ) {
			return WPCPM_Institution_Policy::refusal();
		}

		if ( 1 === count( $links ) && WPCPM_Mentors_Sync::is_record_id( $links[0] ) ) {
			return self::students_record( $airtable, $settings, $links[0] );
		}

		$email = trim( (string) WPCPM_Airtable::flatten( isset( $fields[ self::FIELD_EMAIL ] ) ? $fields[ self::FIELD_EMAIL ] : '' ) );

		// Three reports rows have no address at all. There is nothing to join on, and a
		// claim that cannot name its student is refused rather than guessed at.
		if ( '' === $email ) {
			return WPCPM_Institution_Policy::refusal();
		}

		return self::students_row_for_email( $airtable, $settings, $email );
	}

	/**
	 * One Students row by record ID.
	 *
	 * @param WPCPM_Airtable $airtable The client.
	 * @param array          $settings Plugin settings.
	 * @param string         $record   Students record ID.
	 * @return array|WP_Error
	 */
	private static function students_record( WPCPM_Airtable $airtable, array $settings, $record ) {
		// The whole row is asked for because `get_record()` has no `fields` parameter to ask
		// with, which is exactly why the narrowing below is not optional on this route.
		$row = $airtable->get_record( $settings['students_table'], $record );

		return is_wp_error( $row ) ? self::read_error( $row ) : self::disclosed_row( $row );
	}

	/**
	 * The one Students row for an address, or a refusal.
	 *
	 * @param WPCPM_Airtable $airtable The client.
	 * @param array          $settings Plugin settings.
	 * @param string         $email    The address from the reports row.
	 * @return array|WP_Error
	 */
	private static function students_row_for_email( WPCPM_Airtable $airtable, array $settings, $email ) {
		// The third argument is the case-insensitive comparison, right here and wrong for a
		// name: an address is ASCII by nature, and PHP's folding and Airtable's agree on it.
		$formula = $airtable->formula_in( self::FIELD_EMAIL, array( $email ), true );

		if ( '' === $formula ) {
			return WPCPM_Institution_Policy::refusal();
		}

		$page = $airtable->fetch_page(
			$settings['students_table'],
			array(
				'formula' => $formula,
				// The request-side half of the fence. It saves the disclosure a trip over the
				// wire; it does not excuse the narrowing on the way out, which is what actually
				// holds when the base answers with something else.
				'fields'  => self::disclosed_fields(),
			)
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$records = ( isset( $page['records'] ) && is_array( $page['records'] ) ) ? $page['records'] : array();

		// Zero is a report row the Students table has no student for - 19 of those exist.
		// Two or more is an address filed twice. Neither is a student this claim can name,
		// and neither is one match that came back as something other than a row: Airtable
		// does not send that, but a proxy or a truncated body can, and a fence that fatalled
		// on it would be down rather than closed.
		if ( 1 !== count( $records ) || ! is_array( $records[0] ) ) {
			return WPCPM_Institution_Policy::refusal();
		}

		return self::disclosed_row( $records[0] );
	}

	/**
	 * One Airtable row, cut down to the columns an institution may be shown.
	 *
	 * The one place a Students row becomes a value this class hands back, so that a read
	 * added later has somewhere obvious to go through and no way to be wide by accident. The
	 * shape is fixed here too - `id` a string and `fields` an array, whatever came back - so
	 * that a caller reading the row never has to guess which of the two Airtable shapes it
	 * has, and an unreadable answer arrives as an empty row rather than as a notice.
	 *
	 * @param array $row A record as the client returns it.
	 * @return array{id: string, fields: array}
	 */
	private static function disclosed_row( array $row ) {
		$fields = ( isset( $row['fields'] ) && is_array( $row['fields'] ) ) ? $row['fields'] : array();

		return array(
			'id'     => isset( $row['id'] ) ? (string) $row['id'] : '',
			'fields' => array_intersect_key( $fields, array_flip( self::disclosed_fields() ) ),
		);
	}

	/**
	 * A failed read, turned into the answer the caller should give.
	 *
	 * A record Airtable does not have reads exactly like a record that is not the
	 * caller's: 404 and "not yours" have to be the same message, or a member could walk
	 * record IDs and learn which ones are real. Every other failure - a rate limit, a
	 * missing scope, a timeout - is handed back as it came, because a screen that answered
	 * "that record is not on your roster" when the base was simply unreachable would send
	 * somebody looking for a permissions fault that does not exist.
	 *
	 * @param WP_Error $error What the client returned.
	 * @return WP_Error
	 */
	private static function read_error( WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 0;

		return 404 === $status ? WPCPM_Institution_Policy::refusal() : $error;
	}

	/**
	 * Record the one refusal that is worth a log row.
	 *
	 * Filed under the actor's **own** institution, not the record's: the row means "the
	 * index said this student was yours and the base says otherwise", which is a fact
	 * about this institution's roster and belongs in its log. Filing it under the other
	 * institution would put one school's incident in another school's history and tell
	 * that school a record ID it has no business knowing.
	 *
	 * The ground is `member` because a manager is never refused here - the manager ground
	 * carries every action and is not gated - so the only actor who can reach this line is
	 * somebody acting as a member. An actor with no membership to file the row under is
	 * not logged at all, which is better than a row filed under a guess.
	 *
	 * @param string           $record   The record that was claimed.
	 * @param array            $decision The refused decision, for its `why`.
	 * @param int|WP_User|null $user     The acting user.
	 */
	private static function log_live_refusal( $record, array $decision, $user ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		$institution = WPCPM_Institution_Members::institution_of( $user );

		if ( '' === $institution ) {
			return;
		}

		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_REFUSED,
				'institution' => $institution,
				'subject'     => $record,
				'actor'       => $user->ID,
				'ground'      => WPCPM_Institution_Audit::GROUND_MEMBER,
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_LIVE,
				'message'     => __( 'A live read placed this record with another institution than the site held for it.', 'wpcredits-program-manager' ),
				'data'        => array(
					'record' => $record,
					'why'    => isset( $decision['why'] ) ? (string) $decision['why'] : '',
				),
			)
		);
	}
}
