<?php
/**
 * The Institutions module's one authorisation fence.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * May this user perform this action on this subject, and on what grounds.
 *
 * Every write, export, live disclosure and render in the Institutions module asks this
 * class and names the action it uses; no handler carries a second copy of any check made
 * here. That is the point of the class: this module's history of fence bugs is a history
 * of comparisons spread over handlers, one of which treated an empty stamp as "matches
 * every student whose institution is also empty". So the comparison happens in one place,
 * on a list filtered to well-formed record IDs first, and `bin/test-institution-policy.php`
 * greps the rest of the module for institution IDs compared with `===` and expects none.
 *
 * All static, no state, no HTTP, no reads of the request. The policy reads exactly two
 * things it is not handed: the acting user's memberships, and the acting institution's
 * agreement state. Everything else about the subject arrives inside the subject array,
 * built by one of the `subject_*()` methods at the level of evidence the action deserves;
 * the policy never fetches.
 */
final class WPCPM_Institution_Policy {

	/** Ground: the program manager's capability. */
	const GROUND_MANAGER = 'manager';

	/** Ground: membership of an institution the subject belongs to. */
	const GROUND_MEMBER = 'member';

	// Reserved, not built: 'project'. See section 12 of the design spec.

	/** The dashboard, its groups, the strip, the members card. */
	const ACT_VIEW_ROSTER = 'view_roster';

	/** One student's detail view, from cache. */
	const ACT_VIEW_STUDENT = 'view_student';

	/** The live Student Report Card panel and the REST report route. */
	const ACT_VIEW_REPORT = 'view_report';

	/** The allowlisted edit form. */
	const ACT_EDIT_STUDENT = 'edit_student';

	/** Graduate, withdraw. */
	const ACT_CHANGE_STATUS = 'change_status';

	/** Import: check, confirm, continue, cancel. */
	const ACT_ADD_STUDENT = 'add_student';

	/** Both CSVs. */
	const ACT_EXPORT = 'export';

	/** The semester report, read. */
	const ACT_VIEW_SEMESTER_REPORT = 'view_semester_report';

	/** Generate, save, restore, final, reopen, ask, print. */
	const ACT_EDIT_SEMESTER_REPORT = 'edit_semester_report';

	/** Invite, cancel, resend, remove. */
	const ACT_MANAGE_MEMBERS = 'manage_members';

	/** View, generate, upload, withdraw, download. The only ungated action. */
	const ACT_AGREEMENT = 'agreement';

	/** The one refusal code every refused request carries. */
	const REFUSAL_CODE = 'wpcpm_inst_unknown';

	/**
	 * Which grounds may carry each action, in the order they are tried.
	 *
	 * The full map, not a default plus exceptions. Adding a ground to an action is a visible
	 * one-line diff here and a failing assertion until the expected map in the test suite is
	 * updated in the same commit. That is how a project clause will land on two rows and on
	 * nothing else. The manager ground is first on every row so that a manager who is also a
	 * member is logged as a manager, which is what the audit log needs to read.
	 *
	 * @return array<string, string[]> Action to the grounds tried for it.
	 */
	public static function grounds() {
		return array(
			self::ACT_VIEW_ROSTER          => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_VIEW_STUDENT         => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_VIEW_REPORT          => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_EDIT_STUDENT         => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_CHANGE_STATUS        => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_ADD_STUDENT          => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_EXPORT               => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_VIEW_SEMESTER_REPORT => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_EDIT_SEMESTER_REPORT => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_MANAGE_MEMBERS       => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
			self::ACT_AGREEMENT            => array( self::GROUND_MANAGER, self::GROUND_MEMBER ),
		);
	}

	/**
	 * Actions the agreement gate does not apply to.
	 *
	 * One, by decision 6: an institution whose agreement is not settled can still see,
	 * generate, upload and withdraw the agreement itself, because that is how it becomes
	 * settled. Everything else waits.
	 *
	 * @return string[]
	 */
	public static function ungated() {
		return array( self::ACT_AGREEMENT );
	}

	/**
	 * A subject that is an institution itself: the dashboard, the members card, the export.
	 *
	 * The evidence is the pipeline index: the record is one the site has read.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return array
	 */
	public static function subject_institution( $record_id ) {
		return self::subject( 'institution', (string) $record_id, array( $record_id ), 'index' );
	}

	/**
	 * A subject that is a student's account, placed by the stamp the students sync wrote.
	 *
	 * An account with no stamp is an institution-less subject, which belongs to nobody but a
	 * manager. The stamp is deleted rather than written empty when there is none, and this
	 * builder drops an empty value anyway, so the two never meet an empty membership.
	 *
	 * @param int $user_id The student's account.
	 * @return array
	 */
	public static function subject_student_account( $user_id ) {
		$user_id = (int) $user_id;

		// The raw value, not a cast one: a hand-written array stamp would raise "Array to string
		// conversion" here and reach the filter as the id "Array". The builder drops what is not
		// a scalar, which is the promise its own docblock makes.
		$stamp = get_user_meta( $user_id, WPCPM_Students_Sync::META_INSTITUTION, true );

		return self::subject( 'student', $user_id, array( $stamp ), 'cache' );
	}

	/**
	 * A subject that is a Students row with no account, placed by the roster index.
	 *
	 * The institution is read from the row's own `institution` value, not from the record
	 * the caller named: the caller names which index to open, the row says whose it is. A
	 * row the index does not hold under that institution is institution-less. Both IDs are
	 * shape-checked before the option is read, so a pasted string opens nothing.
	 *
	 * @param string $record_id       Institutions record ID whose index holds the row.
	 * @param string $students_record Students record ID of the row.
	 * @return array
	 */
	public static function subject_index_row( $record_id, $students_record ) {
		$ids = array();

		if ( WPCPM_Mentors_Sync::is_record_id( $record_id ) && WPCPM_Mentors_Sync::is_record_id( $students_record ) ) {
			$rows = WPCPM_Roster_Index::rows( $record_id );

			if ( isset( $rows[ $students_record ]['institution'] ) ) {
				$ids[] = $rows[ $students_record ]['institution'];
			}
		}

		return self::subject( 'student', (string) $students_record, $ids, 'cache' );
	}

	/**
	 * A subject that is a post, placed by the post's own institution meta, never by the form.
	 *
	 * Every post-keyed handler (agreement download, withdraw, accept, return, revoke; batch
	 * confirm and cancel; report save and print) names its institution this way. A member
	 * of B posting A's post ID is decided against A, is not a member of A, and gets the one
	 * refusal. The meta may hold one record ID or a list; a post with no such meta is an
	 * institution-less subject.
	 *
	 * @param WP_Post $post     The post being acted on.
	 * @param string  $meta_key The post's institution meta key (`_wpcpm_agr_institution` and its siblings).
	 * @return array
	 */
	public static function subject_post( WP_Post $post, $meta_key ) {
		// The subject vocabulary names the document, not the storage: the audit log reads
		// `type` and should not have to know which post type holds what. A post type this
		// map does not know passes through as itself rather than being mislabelled.
		$types = array(
			'wpcpm_agreement'       => 'agreement',
			'wpcpm_semester_report' => 'semester_report',
			'wpcpm_import_batch'    => 'batch',
		);

		$post_type = (string) $post->post_type;
		$type      = isset( $types[ $post_type ] ) ? $types[ $post_type ] : $post_type;
		$ids       = (array) get_post_meta( (int) $post->ID, (string) $meta_key, true );

		return self::subject( $type, (int) $post->ID, $ids, 'cache' );
	}

	/**
	 * A subject placed by a live read, built by `claim()` after the Students row came back.
	 *
	 * @param string $type 'student' (a Students row) or 'report' (a Students Reports row).
	 * @param string $id   The record ID that was claimed.
	 * @param array  $ids  The Students row's `Educational Institutions` link, as record IDs.
	 * @return array
	 */
	public static function subject_live( $type, $id, array $ids ) {
		return self::subject( (string) $type, (string) $id, $ids, 'live' );
	}

	/**
	 * May this user perform this action on this subject, and on what grounds.
	 *
	 * The one fence. It never sees the request and never makes an HTTP call; evidence is the
	 * caller's job, at the level the action deserves. `fields` is null for every shipped
	 * ground and is read by every caller anyway: a field-scoped ground added later narrows
	 * every renderer and handler without touching one.
	 *
	 * The `array_merge()` is deliberate: `+` keeps the left operand and one day somebody swaps
	 * the operands (the first spec records the approval payload bug that shape produced).
	 *
	 * @param string           $action  One of the ACT_* constants.
	 * @param array            $subject A subject built by one of the `subject_*()` methods.
	 * @param int|WP_User|null $user    The acting user; null for the current one.
	 * @return array{ allowed: bool, ground: string, institution: string, fields: string[]|null, why: string }
	 *   `why` goes to the log and nowhere else; the user-facing refusal is one message.
	 */
	public static function decide( $action, array $subject, $user = null ) {
		$refused = array(
			'allowed'     => false,
			'ground'      => '',
			'institution' => '',
			'fields'      => array(),
			'why'         => '',
		);
		$grounds = self::grounds();

		// An action the map does not know is refused, not allowed. A handler with a typo in
		// its action fails its own happy-path test rather than passing every user.
		if ( ! is_string( $action ) || ! isset( $grounds[ $action ] ) ) {
			return array_merge( $refused, array( 'why' => 'unknown-action' ) );
		}

		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return array_merge( $refused, array( 'why' => 'no-user' ) );
		}

		// Shape first, on the subject's side. An empty list is an institution-less subject,
		// which belongs to nobody but a manager.
		$given = isset( $subject['institution_ids'] ) ? (array) $subject['institution_ids'] : array();
		$ids   = array_values( array_filter( $given, array( 'WPCPM_Mentors_Sync', 'is_record_id' ) ) );

		foreach ( $grounds[ $action ] as $ground ) {
			$decision = call_user_func( array( __CLASS__, 'ground_' . $ground ), $action, $subject, $ids, $user );

			if ( is_array( $decision ) ) {
				return $decision;
			}
		}

		return array_merge( $refused, array( 'why' => 'no-ground' ) );
	}

	/**
	 * CAP_MANAGE. The unconditional override every path in this plugin keeps.
	 *
	 * Not gated by the agreement: the gate limits what an institution may do for itself, and
	 * the manager is the person the gate is waiting for. The decision is logged as `manager`,
	 * so a manager's semester report for an unsettled institution is a manager's act on the
	 * record. The institution named is the subject's first well-formed ID, or '' for an
	 * institution-less subject, which is the one case a manager passes and nobody else does.
	 *
	 * @param string  $action  The action, unused: the capability covers every row of the map.
	 * @param array   $subject The subject, unused: the filtered IDs are what is needed.
	 * @param array   $ids     The subject's institution IDs, well-formed ones only.
	 * @param WP_User $user    The acting user.
	 * @return array|null A decision, or null to try the next ground.
	 */
	private static function ground_manager( $action, array $subject, array $ids, WP_User $user ) {
		if ( ! user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
			return null;
		}

		return array(
			'allowed'     => true,
			'ground'      => self::GROUND_MANAGER,
			'institution' => isset( $ids[0] ) ? $ids[0] : '',
			'fields'      => null,
			'why'         => '',
		);
	}

	/**
	 * Membership of an institution the subject belongs to.
	 *
	 * Two empties never meet: the subject's list was filtered and is tested for emptiness,
	 * and institution_of() returns '' rather than an empty-but-present stamp. The agreement
	 * gate lives here and not in the handlers, so a handler cannot forget it.
	 *
	 * @param string  $action  The action, for the gate.
	 * @param array   $subject The subject, unused: the filtered IDs are what is needed.
	 * @param array   $ids     The subject's institution IDs, well-formed ones only.
	 * @param WP_User $user    The acting user.
	 * @return array|null A decision, or null to try the next ground.
	 */
	private static function ground_member( $action, array $subject, array $ids, WP_User $user ) {
		if ( empty( $ids ) ) {
			return null;
		}

		foreach ( WPCPM_Institution_Members::memberships_of( $user ) as $mine ) {
			if ( ! in_array( $mine, $ids, true ) ) {
				continue;
			}

			if ( ! in_array( $action, self::ungated(), true ) && ! WPCPM_Institution_Agreement::is_settled( $mine ) ) {
				continue;
			}

			return array(
				'allowed'     => true,
				'ground'      => self::GROUND_MEMBER,
				'institution' => $mine,
				'fields'      => null,
				'why'         => '',
			);
		}

		return null;
	}

	/**
	 * The one refusal.
	 *
	 * "Not yours", "no such record", "not a member" and "agreement outstanding": one WP_Error,
	 * byte for byte. A form that answered differently would be a membership oracle an
	 * institution could walk to learn which record IDs are real students elsewhere.
	 *
	 * @return WP_Error
	 */
	public static function refusal() {
		return new WP_Error( self::REFUSAL_CODE, __( 'That record is not on your roster.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Narrow keyed cells, columns or rows to what the decision permits.
	 *
	 * Keys are "<table>|<field>". A `fields` of null permits everything, and is what every
	 * shipped ground returns; a list keeps only the keys it names, in the caller's order. A
	 * refusal permits nothing whatever its `fields` says, so a caller that forgot to test
	 * `allowed` first renders an empty table rather than a full one. `fields` is tested with
	 * array_key_exists() because isset() reads null as absent, which would turn the widest
	 * scope into the narrowest one.
	 *
	 * @param array $decision What decide() returned.
	 * @param array $keyed    Cells, columns or rows keyed "<table>|<field>".
	 * @return array The entries of `$keyed` the decision permits.
	 */
	public static function scope( array $decision, array $keyed ) {
		if ( empty( $decision['allowed'] ) || ! array_key_exists( 'fields', $decision ) ) {
			return array();
		}

		if ( null === $decision['fields'] ) {
			return $keyed;
		}

		$permitted = array();

		foreach ( (array) $decision['fields'] as $key ) {
			if ( is_scalar( $key ) ) {
				$permitted[ (string) $key ] = true;
			}
		}

		return array_intersect_key( $keyed, $permitted );
	}

	/**
	 * The subject shape every builder returns.
	 *
	 * The ID list is reduced to non-empty strings here and nothing more: whether each one
	 * is a well-formed record ID is decide()'s question, asked once, on the subject's side,
	 * in the one place a test can pin. A nested array or an object in the list is dropped
	 * rather than cast, because casting one to string is a warning and "Array" is not a
	 * record ID. Nothing is trimmed: the policy compares what was stored and does not
	 * repair it, so a stamp with a stray space fails closed and shows up in the log.
	 *
	 * @param string     $type     'institution', 'student', 'report', 'agreement', 'semester_report' or 'batch'.
	 * @param int|string $id       What the subject is: a record ID, a user ID or a post ID.
	 * @param array      $ids      Institution record IDs the subject belongs to.
	 * @param string     $evidence 'index', 'cache' or 'live': how the IDs were established.
	 * @return array{ type: string, id: int|string, institution_ids: string[], evidence: string }
	 */
	private static function subject( $type, $id, array $ids, $evidence ) {
		$clean = array();

		foreach ( $ids as $candidate ) {
			if ( is_scalar( $candidate ) && '' !== (string) $candidate ) {
				$clean[] = (string) $candidate;
			}
		}

		return array(
			'type'            => (string) $type,
			'id'              => $id,
			'institution_ids' => $clean,
			'evidence'        => (string) $evidence,
		);
	}
}
