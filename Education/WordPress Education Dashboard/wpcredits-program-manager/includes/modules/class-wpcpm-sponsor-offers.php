<?php
/**
 * A sponsor's offers: what a student gets, how, and whether it is on.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post type `wpcpm_offer`, private and invisible, one post per offer; the codes live beside it
 * in WPCPM_Sponsor_Codes and the claims in WPCPM_Sponsor_Claims.
 *
 * Sponsors design spec of 4 September 2026, section 6.1. The sponsor's key, held in
 * `_wpcpm_offer_sponsor`, is the queryable field and is never read from a form: every handler
 * claims its sponsor through WPCPM_Sponsor_Roster::claim() first and then asks find() whether
 * the offer belongs to it, so a member cannot reach another sponsor's offer by posting its ID.
 * The one offer marked primary is the offer the profile card points to and the Sponsors screen
 * marks "(primary)"; nothing about it is written anywhere else, since 1.111.0 moved the record
 * onto the site.
 *
 * **The review (1.123.18).** What students and mentors see of an offer has an Administrator's OK,
 * as a sponsor's post does: the partner page offers products at any level, and the Foundation
 * decides how they are shared. A sponsor member's new offer is sent for review rather than switched
 * on (`submit()`, the Waiting for review state), and a member's change to the words, the link or
 * the shared code of an offer students can see, live, paused or dormant, is held beside it
 * (`propose()`) while students keep seeing the offer as it was approved. An Administrator approves
 * or returns either with a note (`WPCPM_Sponsor_Offer_Review`). Pausing, ending, a new last day, the
 * audience, the warning and the codes added to a pool take effect at once, and what the program's
 * own staff make or change needs no review. An offer carries whether its words have that OK
 * (`META_APPROVED`); one from before this release carries no such mark, and reads as approved when
 * students could already see it (`approved()`), so nothing live at the update changes.
 */
final class WPCPM_Sponsor_Offers {

	const POST_TYPE = 'wpcpm_offer';

	const META_SPONSOR      = '_wpcpm_offer_sponsor';
	const META_KIND         = '_wpcpm_offer_kind';
	const META_STATE        = '_wpcpm_offer_state';
	const META_AUDIENCE     = '_wpcpm_offer_audience';
	const META_PRIMARY      = '_wpcpm_offer_primary';
	const META_TEXT         = '_wpcpm_offer_text';
	const META_INSTRUCTIONS = '_wpcpm_offer_instructions';
	const META_URL          = '_wpcpm_offer_url';
	const META_LOW          = '_wpcpm_offer_low';
	const META_LOW_SENT     = '_wpcpm_offer_low_sent';
	const META_EXPIRES      = '_wpcpm_offer_expires';
	const META_EVENT        = '_wpcpm_offer_event';
	const META_APPROVED     = '_wpcpm_offer_approved';
	const META_PROPOSED     = '_wpcpm_offer_proposed';
	const META_SUBMITTED    = '_wpcpm_offer_submitted';
	const META_RETURN_NOTE  = '_wpcpm_offer_return_note';
	const META_RETURNED     = '_wpcpm_offer_returned';

	/** The fields of what students see that a member's change to a visible offer holds for review, the shared code beside them. */
	const REVIEWED = array( 'title', 'text', 'instructions', 'url' );

	const KIND_CODES  = 'codes';
	const KIND_SHARED = 'shared';

	const STATE_DRAFT   = 'draft';
	const STATE_REVIEW  = 'review';
	const STATE_LIVE    = 'live';
	const STATE_PAUSED  = 'paused';
	const STATE_ENDED   = 'ended';
	const STATE_DORMANT = 'dormant';

	/** Students are always in; these two are opt-in per offer. */
	const AUDIENCES = array( 'mentors', 'managers' );

	// The most characters each box holds, counted as the person typed them: WPCPM_Typed_Text::typed_length().
	// The cleaners write a "<" and the quotes and ampersands after it as longer entities, so a count of
	// the stored bytes would turn away text that fits its box.
	const MAX_TITLE = 120;
	const MAX_OFFER = 500;
	const MAX_TEXT  = 4000;

	// The most an uploaded codes file may weigh. Five thousand checkout links of two hundred
	// characters are a megabyte; a bigger file is not a code list, whatever its name says.
	const UPLOAD_MAX = 1048576;

	const ACTION_SAVE       = 'wpcpm_offer_save';
	const ACTION_STATE      = 'wpcpm_offer_state';
	const ACTION_CODES_ADD  = 'wpcpm_offer_codes_add';
	const ACTION_CODES_VOID = 'wpcpm_offer_codes_void';
	const ACTION_DISCARD    = 'wpcpm_offer_discard';

	const CARD = 'offers';

	const LOG_SAVED  = 'offer_saved';
	const LOG_STATE  = 'offer_state';
	const LOG_CODES  = 'offer_codes';
	const LOG_SEEDED = 'offer_seeded';

	/**
	 * A coupon link on one of these hosts is the sheet the program is retiring, never a shared
	 * code. Drive as well as Docs: the same sheet shared from the file list is a `drive.google.com`
	 * link, and the rule exists to keep the sheet out of the pool.
	 */
	const SHEET_HOSTS = array( 'docs.google.com', 'drive.google.com' );

	/**
	 * The post type, and the five handlers.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_STATE, array( __CLASS__, 'handle_state' ) );
		add_action( 'admin_post_' . self::ACTION_CODES_ADD, array( __CLASS__, 'handle_codes_add' ) );
		add_action( 'admin_post_' . self::ACTION_CODES_VOID, array( __CLASS__, 'handle_codes_void' ) );
		add_action( 'admin_post_' . self::ACTION_DISCARD, array( __CLASS__, 'handle_discard' ) );
	}

	/**
	 * Registered the way the audit log is: private, invisible, a capability type nothing is
	 * granted, so no role reaches an offer through any generic post screen.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Sponsor offers', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Sponsor offer', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'capability_type'     => array( 'wpcpm_offer', 'wpcpm_offers' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * The kinds an offer can be.
	 *
	 * @return string[]
	 */
	public static function kinds() {
		return array( self::KIND_CODES, self::KIND_SHARED );
	}

	/**
	 * The states an offer can be in.
	 *
	 * @return string[]
	 */
	public static function states() {
		return array( self::STATE_DRAFT, self::STATE_REVIEW, self::STATE_LIVE, self::STATE_PAUSED, self::STATE_ENDED, self::STATE_DORMANT );
	}

	/**
	 * The moves a sponsor may make. Ended is final: a pool that was handed out is a record,
	 * and reviving it would put codes people already hold back on offer.
	 *
	 * Dormant is the program's state, written by set_dormant() when a tier stops covering the
	 * offer and lifted by wake(); it is never a target here, so no sponsor can post it, and a
	 * sponsor may only end a dormant offer.
	 *
	 * Waiting for review is a member's draft sent to the program (`submit()`): the member may take it
	 * back to a draft or end it, and only an Administrator's approval puts it live
	 * (`WPCPM_Sponsor_Offers::approve()`), never a move posted here.
	 *
	 * @return array<string, string[]>
	 */
	public static function transitions() {
		return array(
			self::STATE_DRAFT   => array( self::STATE_LIVE, self::STATE_ENDED ),
			self::STATE_REVIEW  => array( self::STATE_DRAFT, self::STATE_ENDED ),
			self::STATE_LIVE    => array( self::STATE_PAUSED, self::STATE_ENDED ),
			self::STATE_PAUSED  => array( self::STATE_LIVE, self::STATE_ENDED ),
			self::STATE_ENDED   => array(),
			self::STATE_DORMANT => array( self::STATE_ENDED ),
		);
	}

	/**
	 * An offer with nothing set yet.
	 *
	 * @return array
	 */
	public static function empty_offer() {
		return array(
			'id'           => 0,
			'sponsor'      => '',
			'title'        => '',
			'kind'         => self::KIND_CODES,
			'state'        => self::STATE_DRAFT,
			'audience'     => array(),
			'primary'      => false,
			'text'         => '',
			'instructions' => '',
			'url'          => '',
			'low'          => max( 1, (int) WPCPM_Settings::get_value( 'offer_low_stock', 10 ) ),
			'low_sent'     => 0,
			'expires'      => '',
			'event'        => array(),
			'approved'     => false,
			'proposed'     => array(),
			'submitted'    => array(),
			'note'         => '',
			'returned'     => 0,
		);
	}

	/**
	 * One offer as an array, or null for a post that is not one.
	 *
	 * @param int|WP_Post $post The post or its ID.
	 * @return array|null
	 */
	public static function read( $post ) {
		$post = $post instanceof WP_Post ? $post : get_post( (int) $post );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$id    = (int) $post->ID;
		$offer = self::empty_offer();

		$offer['id']      = $id;
		$offer['title']   = (string) $post->post_title;
		$offer['sponsor'] = trim( (string) get_post_meta( $id, self::META_SPONSOR, true ) );

		$kind  = (string) get_post_meta( $id, self::META_KIND, true );
		$state = (string) get_post_meta( $id, self::META_STATE, true );

		$offer['kind']  = in_array( $kind, self::kinds(), true ) ? $kind : self::KIND_CODES;
		$offer['state'] = in_array( $state, self::states(), true ) ? $state : self::STATE_DRAFT;

		$audience          = get_post_meta( $id, self::META_AUDIENCE, true );
		$offer['audience'] = array_values( array_intersect( self::AUDIENCES, is_array( $audience ) ? array_map( 'strval', $audience ) : array() ) );

		$offer['primary']      = '1' === (string) get_post_meta( $id, self::META_PRIMARY, true );
		$offer['text']         = (string) get_post_meta( $id, self::META_TEXT, true );
		$offer['instructions'] = (string) get_post_meta( $id, self::META_INSTRUCTIONS, true );
		$offer['url']          = (string) get_post_meta( $id, self::META_URL, true );

		$low          = (int) get_post_meta( $id, self::META_LOW, true );
		$offer['low'] = $low > 0 ? $low : $offer['low'];

		$offer['low_sent'] = (int) get_post_meta( $id, self::META_LOW_SENT, true );
		$offer['expires']  = (string) get_post_meta( $id, self::META_EXPIRES, true );

		$event          = get_post_meta( $id, self::META_EVENT, true );
		$offer['event'] = is_array( $event ) ? $event : array();

		$proposed          = get_post_meta( $id, self::META_PROPOSED, true );
		$offer['proposed'] = is_array( $proposed ) ? array_map( 'strval', $proposed ) : array();

		// A held shared code is sealed at rest (`propose()`). One that no longer opens is dropped
		// from the change, so an approval keeps the code students have rather than clearing it.
		if ( isset( $offer['proposed']['shared'] ) && '' !== $offer['proposed']['shared'] ) {
			$plain = WPCPM_Secret::unseal_from_option( $offer['proposed']['shared'] );

			if ( is_wp_error( $plain ) ) {
				unset( $offer['proposed']['shared'] );
			} else {
				$offer['proposed']['shared'] = $plain;
			}
		}

		$submitted          = get_post_meta( $id, self::META_SUBMITTED, true );
		$offer['submitted'] = is_array( $submitted ) && isset( $submitted['by'], $submitted['at'] ) ? array(
			'by' => (int) $submitted['by'],
			'at' => (int) $submitted['at'],
		) : array();

		$offer['note']     = (string) get_post_meta( $id, self::META_RETURN_NOTE, true );
		$offer['returned'] = (int) get_post_meta( $id, self::META_RETURNED, true );
		$offer['approved'] = self::approved( $id, $offer['state'] );

		return $offer;
	}

	/**
	 * Whether an offer's words have an Administrator's OK: the mark the offer carries, `1` or `0`,
	 * and for an offer from before 1.123.18, which carries none, whether students could already see
	 * it, live, paused or dormant, so an offer that was live at the update stays live and needs no
	 * review. A draft from before reads as not approved: switched on, it is new to students.
	 *
	 * @param int    $offer_id Offer post ID.
	 * @param string $state    Its state, as read.
	 * @return bool
	 */
	private static function approved( $offer_id, $state ) {
		$mark = get_post_meta( (int) $offer_id, self::META_APPROVED, true );

		if ( '1' === (string) $mark ) {
			return true;
		}

		if ( '0' === (string) $mark ) {
			return false;
		}

		return in_array( $state, self::visible_states(), true );
	}

	/**
	 * The states in which students see an offer, or will again at a resume or a wake: a member's
	 * change to such an offer waits for review (`propose()`).
	 *
	 * @return string[]
	 */
	public static function visible_states() {
		return array( self::STATE_LIVE, self::STATE_PAUSED, self::STATE_DORMANT );
	}

	/**
	 * Whether an offer, or a change to one, waits for an Administrator: an offer sent for review, or a
	 * change held beside a visible offer that has not been returned.
	 *
	 * @param array $offer An offer.
	 * @return bool
	 */
	public static function is_waiting( array $offer ) {
		return self::STATE_REVIEW === $offer['state'] || ( ! empty( $offer['proposed'] ) && 0 === (int) $offer['returned'] && in_array( $offer['state'], self::visible_states(), true ) );
	}

	/**
	 * The offer as its edit form draws it: a change held for review in place of the words it holds,
	 * so the member edits what they last sent.
	 *
	 * @param array $offer An offer.
	 * @return array
	 */
	public static function as_drawn( array $offer ) {
		foreach ( self::REVIEWED as $key ) {
			if ( isset( $offer['proposed'][ $key ] ) ) {
				$offer[ $key ] = (string) $offer['proposed'][ $key ];
			}
		}

		return $offer;
	}

	/**
	 * Every offer and every change waiting for an Administrator, the oldest sent first.
	 *
	 * @return array Offers keyed by ID.
	 */
	public static function waiting() {
		$found = self::query(
			array(
				array(
					'key'   => self::META_STATE,
					'value' => self::STATE_REVIEW,
				),
			)
		) + self::query(
			array(
				array(
					'key'     => self::META_PROPOSED,
					'compare' => 'EXISTS',
				),
			)
		);

		$waiting = array_filter( $found, array( __CLASS__, 'is_waiting' ) );

		uasort(
			$waiting,
			static function ( array $a, array $b ) {
				$at_a = isset( $a['submitted']['at'] ) ? (int) $a['submitted']['at'] : 0;
				$at_b = isset( $b['submitted']['at'] ) ? (int) $b['submitted']['at'] : 0;

				return $at_a !== $at_b ? $at_a - $at_b : (int) $a['id'] - (int) $b['id'];
			}
		);

		return $waiting;
	}

	/**
	 * A member sends a draft for review: it waits, seen by nobody but its sponsor, until an
	 * Administrator approves it. The note of an earlier return goes, since what waits now is what the
	 * member changed since.
	 *
	 * @param int $offer_id Offer post ID.
	 * @param int $by       The member who sent it.
	 * @return true|WP_Error `wpcpm_offer_missing`, `wpcpm_offer_transition`, `wpcpm_offer_empty`.
	 */
	public static function submit( $offer_id, $by ) {
		$offer = self::read( (int) $offer_id );

		if ( null === $offer ) {
			return new WP_Error( 'wpcpm_offer_missing', __( 'That offer does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( self::STATE_DRAFT !== $offer['state'] ) {
			return new WP_Error( 'wpcpm_offer_transition', __( 'The offer cannot move to that state from where it is.', 'wpcredits-program-manager' ) );
		}

		$why = self::cannot_go_live( $offer );

		if ( '' !== $why ) {
			return new WP_Error( 'wpcpm_offer_empty', $why );
		}

		update_post_meta( $offer['id'], self::META_STATE, self::STATE_REVIEW );
		self::stamp_submitted( $offer['id'], $by );
		self::touch( $offer['id'], 'submitted' );

		return true;
	}

	/**
	 * A member's change to what students see of a visible offer, held for review: the words, the link
	 * and, for a shared offer, the code, as the member last sent them. Students keep seeing the offer
	 * as it was approved until an Administrator approves the change. The shared code is sealed at rest,
	 * as the one students see is (`WPCPM_Sponsor_Codes::set_shared()`), and `read()` unseals it.
	 *
	 * @param int   $offer_id Offer post ID.
	 * @param array $values   `title`, `text`, `instructions`, `url` and, for a shared offer, `shared`.
	 * @param int   $by       The member who sent it.
	 * @return true|WP_Error The seal's refusal, with nothing held.
	 */
	public static function propose( $offer_id, array $values, $by ) {
		$values = array_map( 'strval', $values );

		if ( isset( $values['shared'] ) && '' !== $values['shared'] ) {
			$sealed = WPCPM_Secret::seal_for_option( $values['shared'] );

			if ( is_wp_error( $sealed ) ) {
				return $sealed;
			}

			$values['shared'] = $sealed;
		}

		update_post_meta( (int) $offer_id, self::META_PROPOSED, wp_slash( $values ) );
		self::stamp_submitted( (int) $offer_id, $by );
		self::touch( (int) $offer_id, 'proposed' );

		return true;
	}

	/**
	 * Drop a held change, its note and who sent it: the offer is as it was approved.
	 *
	 * @param int $offer_id Offer post ID.
	 */
	public static function discard( $offer_id ) {
		foreach ( array( self::META_PROPOSED, self::META_SUBMITTED, self::META_RETURN_NOTE, self::META_RETURNED ) as $key ) {
			delete_post_meta( (int) $offer_id, $key );
		}

		self::forget_queue();
	}

	/**
	 * An Administrator approves what waits: an offer sent for review goes live, and a held change
	 * becomes what students see. Either way the offer's words carry the program's OK.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return true|WP_Error `wpcpm_offer_missing`, `wpcpm_offer_not_waiting`, `wpcpm_offer_empty`,
	 *                       `wpcpm_codes_busy`.
	 */
	public static function approve( $offer_id ) {
		$offer = self::read( (int) $offer_id );

		if ( null === $offer ) {
			return new WP_Error( 'wpcpm_offer_missing', __( 'That offer does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( ! self::is_waiting( $offer ) ) {
			return new WP_Error( 'wpcpm_offer_not_waiting', __( 'Nothing of this offer waits for review.', 'wpcredits-program-manager' ) );
		}

		if ( self::STATE_REVIEW === $offer['state'] ) {
			$why = self::cannot_go_live( $offer );

			if ( '' !== $why ) {
				return new WP_Error( 'wpcpm_offer_empty', $why );
			}

			update_post_meta( $offer['id'], self::META_STATE, self::STATE_LIVE );
		} else {
			$values = $offer['proposed'];

			if ( array_key_exists( 'shared', $values ) && self::KIND_SHARED === $offer['kind'] ) {
				$stored = WPCPM_Sponsor_Codes::set_shared( $offer['id'], (string) $values['shared'] );

				if ( is_wp_error( $stored ) ) {
					return $stored;
				}
			}

			self::save( $offer['id'], array_intersect_key( $values, array_flip( self::REVIEWED ) ), 'changes-approved' );
		}

		update_post_meta( $offer['id'], self::META_APPROVED, '1' );
		self::discard( $offer['id'] );
		self::touch( $offer['id'], 'approved' );

		return true;
	}

	/**
	 * An Administrator returns what waits, with a note kept on the offer: an offer sent for review is
	 * the member's draft again, and a held change stays held, out of the queue, for the member to mend
	 * and send again or to discard, while students keep seeing the offer as it was approved.
	 *
	 * @param int    $offer_id Offer post ID.
	 * @param string $note     The note, as the cleaner left it.
	 * @return array|WP_Error `change` (`new` or `changes`) and `by`, the member who sent it (0 when
	 *                        unknown); `wpcpm_offer_missing` or `wpcpm_offer_not_waiting`.
	 */
	public static function return_with_note( $offer_id, $note ) {
		$offer = self::read( (int) $offer_id );

		if ( null === $offer ) {
			return new WP_Error( 'wpcpm_offer_missing', __( 'That offer does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( ! self::is_waiting( $offer ) ) {
			return new WP_Error( 'wpcpm_offer_not_waiting', __( 'Nothing of this offer waits for review.', 'wpcredits-program-manager' ) );
		}

		$change = self::STATE_REVIEW === $offer['state'] ? 'new' : 'changes';

		if ( 'new' === $change ) {
			update_post_meta( $offer['id'], self::META_STATE, self::STATE_DRAFT );
			update_post_meta( $offer['id'], self::META_APPROVED, '0' );
		}

		// Slashed, because post meta unslashes what it is handed and the note is not.
		update_post_meta( $offer['id'], self::META_RETURN_NOTE, wp_slash( (string) $note ) );
		update_post_meta( $offer['id'], self::META_RETURNED, time() );
		self::touch( $offer['id'], 'returned' );
		self::forget_queue();

		return array(
			'change' => $change,
			'by'     => isset( $offer['submitted']['by'] ) ? (int) $offer['submitted']['by'] : 0,
		);
	}

	/**
	 * Who sent what waits, and when; an earlier return's note and stamp go, since what waits now is new.
	 *
	 * @param int $offer_id Offer post ID.
	 * @param int $by       The member.
	 */
	private static function stamp_submitted( $offer_id, $by ) {
		update_post_meta(
			(int) $offer_id,
			self::META_SUBMITTED,
			array(
				'by' => (int) $by,
				'at' => time(),
			)
		);
		delete_post_meta( (int) $offer_id, self::META_RETURN_NOTE );
		delete_post_meta( (int) $offer_id, self::META_RETURNED );
		self::forget_queue();
	}

	/**
	 * The Sponsors menu's bubble counts what waits: it counts again on the next admin page.
	 */
	private static function forget_queue() {
		if ( class_exists( 'WPCPM_Sponsors' ) && method_exists( 'WPCPM_Sponsors', 'forget_attention' ) ) {
			WPCPM_Sponsors::forget_attention();
		}
	}

	/**
	 * One offer, and only when it belongs to the sponsor named.
	 *
	 * @param int    $offer_id Offer post ID.
	 * @param string $record   The acting sponsor; '' skips the check (a manager's screen that
	 *                         reads the sponsor from the offer itself).
	 * @return array|null
	 */
	public static function find( $offer_id, $record = '' ) {
		$offer = self::read( (int) $offer_id );

		if ( null === $offer ) {
			return null;
		}

		$record = trim( (string) $record );

		if ( '' !== $record && $offer['sponsor'] !== $record ) {
			return null;
		}

		return $offer;
	}

	/**
	 * Run a meta query against the post type and read back every match as an offer.
	 *
	 * @param array $meta_query A meta query.
	 * @return array Offers keyed by ID, oldest first.
	 */
	private static function query( array $meta_query ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
				'meta_query'       => $meta_query,
			)
		);

		$offers = array();

		foreach ( $posts as $post ) {
			$offer = self::read( $post );

			if ( null !== $offer ) {
				$offers[ $offer['id'] ] = $offer;
			}
		}

		return $offers;
	}

	/**
	 * Every offer belonging to one sponsor.
	 *
	 * @param string $record Sponsor key.
	 * @return array Offers keyed by ID.
	 */
	public static function offers_of( $record ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
			return array();
		}

		return self::query(
			array(
				array(
					'key'   => self::META_SPONSOR,
					'value' => $record,
				),
			)
		);
	}

	/**
	 * Every offer, for the manager's screen and uninstall.
	 *
	 * @return array
	 */
	public static function all() {
		return self::query(
			array(
				array(
					'key'     => self::META_SPONSOR,
					'compare' => 'EXISTS',
				),
			)
		);
	}

	/**
	 * Every offer in state live. is_live() still has to say whether its last day has passed.
	 *
	 * @return array
	 */
	public static function live() {
		return self::query(
			array(
				array(
					'key'   => self::META_STATE,
					'value' => self::STATE_LIVE,
				),
			)
		);
	}

	/**
	 * Make an offer.
	 *
	 * The title, here and in `save()`, and the fields in `write_fields()` are written as slashed
	 * copies (`wp_slash()`): core unslashes what it is handed and the cleaned fields are not.
	 *
	 * Only the title is a post field, so only it meets kses, for a member without `unfiltered_html`:
	 * it goes to the post write through `WPCPM_Typed_Text::insert_text()`. The text and the
	 * instructions are post meta, which no filter reads, and are stored as the cleaner left them.
	 *
	 * @param string $record   Sponsor key.
	 * @param array  $fields   Cleaned fields (clean()'s `fields`).
	 * @param bool   $primary  Whether this is the sponsor's one primary offer.
	 * @param bool   $approved Whether its words have the program's OK as they are made: the
	 *                         program's own offer and a seeded one do; a member's waits for review.
	 * @return int|WP_Error
	 */
	public static function create( $record, array $fields, $primary = false, $approved = false ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
			return new WP_Error( 'wpcpm_offer_sponsor', __( 'An offer needs the sponsor it belongs to.', 'wpcredits-program-manager' ) );
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					'post_title'  => WPCPM_Typed_Text::insert_text( isset( $fields['title'] ) ? (string) $fields['title'] : '' ),
					'post_author' => 0,
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( (int) $post_id, self::META_SPONSOR, $record );
		update_post_meta( (int) $post_id, self::META_STATE, self::STATE_DRAFT );
		update_post_meta( (int) $post_id, self::META_PRIMARY, $primary ? '1' : '0' );
		update_post_meta( (int) $post_id, self::META_APPROVED, $approved ? '1' : '0' );

		unset( $fields['title'] );
		self::write_fields( (int) $post_id, $fields, 'created' );

		return (int) $post_id;
	}

	/**
	 * Save fields on an existing offer.
	 *
	 * @param int    $offer_id Offer post ID.
	 * @param array  $fields   Cleaned fields; only the keys present are written.
	 * @param string $what     The event, for the log.
	 * @return true|WP_Error
	 */
	public static function save( $offer_id, array $fields, $what = 'saved' ) {
		$offer = self::read( (int) $offer_id );

		if ( null === $offer ) {
			return new WP_Error( 'wpcpm_offer_missing', __( 'That offer does not exist.', 'wpcredits-program-manager' ) );
		}

		if ( array_key_exists( 'title', $fields ) ) {
			// The title the offer already has is not written again: a form posted back as it was
			// drawn hands over the stored title, and writing it through the post filters would
			// change a title that nobody edited.
			if ( (string) $fields['title'] !== $offer['title'] ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'         => $offer['id'],
							'post_title' => WPCPM_Typed_Text::insert_text( (string) $fields['title'] ),
						)
					)
				);
			}

			unset( $fields['title'] );
		}

		self::write_fields( $offer['id'], $fields, $what );

		return true;
	}

	/**
	 * Write whichever of the cleaned fields are present, and log the event.
	 *
	 * @param int    $post_id Offer post ID.
	 * @param array  $fields  Cleaned fields.
	 * @param string $what    The event.
	 */
	private static function write_fields( $post_id, array $fields, $what ) {
		$map = array(
			'kind'         => self::META_KIND,
			'text'         => self::META_TEXT,
			'instructions' => self::META_INSTRUCTIONS,
			'url'          => self::META_URL,
			'low'          => self::META_LOW,
			'expires'      => self::META_EXPIRES,
			'audience'     => self::META_AUDIENCE,
		);

		foreach ( $map as $key => $meta_key ) {
			if ( array_key_exists( $key, $fields ) ) {
				update_post_meta( $post_id, $meta_key, wp_slash( $fields[ $key ] ) );
			}
		}

		self::touch( $post_id, $what );
	}

	/**
	 * The last change: what, when, by whom. For the card's footer and the log.
	 *
	 * @param int    $post_id Offer post ID.
	 * @param string $what    A short key.
	 */
	public static function touch( $post_id, $what ) {
		update_post_meta(
			(int) $post_id,
			self::META_EVENT,
			array(
				'what' => sanitize_key( (string) $what ),
				'at'   => time(),
				'by'   => get_current_user_id(),
			)
		);
	}

	/**
	 * Clean a posted offer.
	 *
	 * The title, the text and the instructions are measured as the person typed them
	 * (`WPCPM_Typed_Text::typed_length()`) and refused over their limits, never cut. One that reads the
	 * same as the stored one (`WPCPM_Typed_Text::same_text()`, `same_lines()`) is the stored one, so a
	 * form posted back as it was drawn changes none of the three, whatever form the store holds them in,
	 * unless one holds an entity of an entity (`&amp;lt;`), which each unedited save reads one level
	 * further (see `WPCPM_Typed_Text::typed_text()`). The title's box draws each line break the stored
	 * title holds as a space (`WPCPM_Typed_Text::one_line()`), so a title that reads the same as the
	 * stored one so drawn is the stored one too.
	 *
	 * A box the handler found posted back as it was drawn (`$drawn`) is the stored one as it stands:
	 * neither cleaned nor measured, so a stored text that holds what the cleaner would take keeps its
	 * bytes, and one stored over its limit does not refuse a save that did not touch it.
	 *
	 * @param array      $raw      Posted values: title, text, instructions, url, kind, audience (array), low, expires.
	 * @param array|null $existing The offer being edited, or null for a new one.
	 * @param string[]   $drawn    Which of title, text and instructions were posted back as they were drawn.
	 * @return array `ok`, `fields`, `reason` ('' or the field that failed: title, title_long, text_long,
	 *               instructions_long, url, kind, expires) and `over` (how many characters a long field is over).
	 */
	public static function clean( array $raw, $existing = null, array $drawn = array() ) {
		$refuse = static function ( $reason, $over = 0 ) {
			return array(
				'ok'     => false,
				'fields' => array(),
				'reason' => $reason,
				'over'   => (int) $over,
			);
		};

		$stored = static function ( $key ) use ( $existing, $drawn ) {
			return is_array( $existing ) && in_array( $key, $drawn, true );
		};

		$fields = array();
		$title  = $stored( 'title' ) ? (string) $existing['title'] : trim( sanitize_text_field( isset( $raw['title'] ) ? (string) $raw['title'] : '' ) );

		if ( '' === $title ) {
			return $refuse( 'title' );
		}

		$text         = $stored( 'text' ) ? (string) $existing['text'] : sanitize_textarea_field( isset( $raw['text'] ) ? (string) $raw['text'] : '' );
		$instructions = $stored( 'instructions' ) ? (string) $existing['instructions'] : sanitize_textarea_field( isset( $raw['instructions'] ) ? (string) $raw['instructions'] : '' );

		if ( is_array( $existing ) ) {
			$title        = ( WPCPM_Typed_Text::same_text( $title, $existing['title'] ) || WPCPM_Typed_Text::same_text( $title, WPCPM_Typed_Text::one_line( $existing['title'] ) ) ) ? (string) $existing['title'] : $title;
			$text         = WPCPM_Typed_Text::same_lines( $text, $existing['text'] ) ? (string) $existing['text'] : $text;
			$instructions = WPCPM_Typed_Text::same_lines( $instructions, $existing['instructions'] ) ? (string) $existing['instructions'] : $instructions;
		}

		foreach ( array(
			'title'        => array( $title, self::MAX_TITLE ),
			'text'         => array( $text, self::MAX_OFFER ),
			'instructions' => array( $instructions, self::MAX_TEXT ),
		) as $key => $typed ) {
			if ( $stored( $key ) ) {
				continue;
			}

			$over = WPCPM_Typed_Text::typed_length( $typed[0] ) - $typed[1];

			if ( $over > 0 ) {
				return $refuse( $key . '_long', $over );
			}
		}

		$fields['title']        = $title;
		$fields['text']         = $text;
		$fields['instructions'] = $instructions;

		$url = trim( isset( $raw['url'] ) ? (string) $raw['url'] : '' );

		if ( '' !== $url ) {
			$clean = WPCPM_Field_Value::clean_url( $url );
			$parts = wp_parse_url( $clean );

			// The profile's refusal: a `user@` or `user:pass@` reads as one host to a person and
			// can point the link at another. Refused, not stripped, so the sponsor sees the save
			// did not happen instead of a silently different link being kept.
			if ( '' === $clean || ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
				return $refuse( 'url' );
			}

			$url = $clean;
		}

		$fields['url'] = $url;

		$kind = isset( $raw['kind'] ) ? (string) $raw['kind'] : ( is_array( $existing ) ? (string) $existing['kind'] : self::KIND_CODES );

		if ( ! in_array( $kind, self::kinds(), true ) ) {
			return $refuse( 'kind' );
		}

		// Once the pool holds codes or claims, switching kind would orphan them.
		if ( is_array( $existing ) && $kind !== $existing['kind'] && self::kind_is_fixed( $existing ) ) {
			return $refuse( 'kind' );
		}

		$fields['kind'] = $kind;

		$audience           = isset( $raw['audience'] ) && is_array( $raw['audience'] ) ? array_map( 'strval', $raw['audience'] ) : array();
		$fields['audience'] = array_values( array_intersect( self::AUDIENCES, $audience ) );

		$low           = isset( $raw['low'] ) && '' !== trim( (string) $raw['low'] ) ? (int) $raw['low'] : (int) WPCPM_Settings::get_value( 'offer_low_stock', 10 );
		$fields['low'] = max( 1, min( 1000, $low ) );

		$expires = trim( isset( $raw['expires'] ) ? (string) $raw['expires'] : '' );

		if ( '' !== $expires ) {
			// A `Y-m-d` string, compared as a string wherever it is read (the cohort rule), never
			// turned into a timestamp: the last day is the sponsor's calendar day, not a moment.
			if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $expires, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
				return $refuse( 'expires' );
			}
		}

		$fields['expires'] = $expires;

		return array(
			'ok'     => true,
			'fields' => $fields,
			'reason' => '',
		);
	}

	/**
	 * Whether the pool holds anything, after which the kind cannot change.
	 *
	 * @param array $offer An offer.
	 * @return bool
	 */
	public static function kind_is_fixed( array $offer ) {
		$pool = WPCPM_Sponsor_Codes::read( $offer['id'] );

		return ! empty( $pool['codes'] ) || ! empty( $pool['claims'] ) || '' !== $pool['shared'];
	}

	/**
	 * Seed the sponsor's first offer (spec 6.1), once per sponsor.
	 *
	 * The title is the sponsor's name, taken in the form the index holds it, and only a name
	 * longer than the title's box allows, counted as typed, is cut (`cut_as_typed()`). The caller
	 * passes what the migration held for the four legacy fields a sponsor's row used to carry
	 * (`offer`, `instructions`, `more_info`, `coupon_link`, class-wpcpm-sponsors-migration.php's
	 * seed_offer()); each is read with a default of '', so a sponsor with no legacy data simply
	 * seeds an empty draft rather than an undefined-index notice. A checkout link becomes the
	 * shared code; the coupon sheet is a list the program is retiring and its address names
	 * students, so it is never stored here.
	 *
	 * @param string $record Sponsor key.
	 * @param array  $legacy `offer`, `instructions`, `more_info`, `coupon_link`, each '' when
	 *                        absent.
	 * @return int|false|WP_Error The offer, or false when the sponsor already has one.
	 */
	public static function seed( $record, array $legacy = array() ) {
		$row = WPCPM_Sponsors_Index::row( $record );

		if ( ! is_array( $row ) ) {
			return new WP_Error( 'wpcpm_offer_sponsor', __( 'The index does not hold that sponsor.', 'wpcredits-program-manager' ) );
		}

		if ( ! empty( self::offers_of( $record ) ) ) {
			return false;
		}

		$coupon = trim( isset( $legacy['coupon_link'] ) ? (string) $legacy['coupon_link'] : '' );
		$title  = trim( (string) $row['name'] );
		$shared = '' !== $coupon && 1 === preg_match( '#^https?://#i', $coupon );

		foreach ( self::SHEET_HOSTS as $host ) {
			if ( false !== stripos( $coupon, $host ) ) {
				$shared = false;
			}
		}

		$fields = array(
			'title'        => self::cut_as_typed( '' !== $title ? $title : __( 'Offer', 'wpcredits-program-manager' ), self::MAX_TITLE ),
			'kind'         => $shared ? self::KIND_SHARED : self::KIND_CODES,
			'text'         => self::imported_text( isset( $legacy['offer'] ) ? (string) $legacy['offer'] : '', self::MAX_OFFER ),
			'instructions' => self::imported_text( isset( $legacy['instructions'] ) ? (string) $legacy['instructions'] : '', self::MAX_TEXT ),
			'url'          => WPCPM_Field_Value::clean_url( isset( $legacy['more_info'] ) ? (string) $legacy['more_info'] : '' ),
			'audience'     => array(),
			'low'          => max( 1, (int) WPCPM_Settings::get_value( 'offer_low_stock', 10 ) ),
			'expires'      => '',
		);

		// Seeded by the program, from its own records, so its words need no review: a member's change
		// to them, once the sponsor has it, does.
		$id = self::create( $record, $fields, true, true );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( $shared ) {
			$set = WPCPM_Sponsor_Codes::set_shared( $id, $coupon );

			if ( is_wp_error( $set ) ) {
				return $set;
			}
		}

		self::touch( $id, 'seeded' );

		return $id;
	}

	/**
	 * A text taken from the program records, which are not a person typing: cleaned the way a posted
	 * one is and kept in the form the records hold it, whole when a person could have typed it into
	 * its box (`WPCPM_Typed_Text::typed_length()` within the limit), whatever entities that form
	 * holds. Only a text longer than the box allows is cut, as the import always cut it; there is
	 * nobody at an import to refuse it to. The cut is `cut_as_typed()`'s.
	 *
	 * @param string $value The records' text.
	 * @param int    $limit The box's limit.
	 * @return string
	 */
	private static function imported_text( $value, $limit ) {
		return self::cut_as_typed( sanitize_textarea_field( (string) $value ), $limit );
	}

	/**
	 * A text whole when it fits its box as typed (`WPCPM_Typed_Text::typed_length()` within the
	 * limit), whatever entities the form it is held in holds, and otherwise cut to the limit as
	 * typed. An imported text and instructions are cut so, and so is the sponsor's name as a seeded
	 * title, which an application stores with a "<" written `&lt;` and the quote marks after it
	 * `&quot;`: cut on that stored form, a name that fits its box would be seeded short, or ending
	 * in a stray "&quot".
	 *
	 * The cut is on a whole character as typed: an entity (`&quot;`, `&#039;`, `&copy;`) and a line
	 * break's CR LF are each one piece, kept or left out whole, so the text never ends in a stray
	 * "&q" or a lone CR. It keeps as many pieces as fit within the limit, counted as
	 * `typed_length()` counts them; a piece that would carry it over is left out, with all after it.
	 *
	 * @param string $text  The text, in the form it is held in.
	 * @param int    $limit The box's limit.
	 * @return string
	 */
	private static function cut_as_typed( $text, $limit ) {
		$text = (string) $text;

		if ( WPCPM_Typed_Text::typed_length( $text ) <= $limit ) {
			return $text;
		}

		$kept  = '';
		$count = 0;

		preg_match_all( '/&#?[a-z0-9]+;|\r\n|./isu', $text, $pieces );

		foreach ( $pieces[0] as $piece ) {
			$count += WPCPM_Typed_Text::typed_length( $piece );

			if ( $count > $limit ) {
				break;
			}

			$kept .= $piece;
		}

		return $kept;
	}

	/**
	 * Why an offer cannot go live, or '' when it can.
	 *
	 * @param array $offer An offer.
	 * @return string
	 */
	public static function cannot_go_live( array $offer ) {
		if ( self::KIND_CODES === $offer['kind'] && WPCPM_Sponsor_Codes::counts( $offer['id'] )['available'] < 1 ) {
			return __( 'Add at least one code before switching this offer on.', 'wpcredits-program-manager' );
		}

		if ( self::KIND_SHARED === $offer['kind'] && '' === WPCPM_Sponsor_Codes::shared( $offer['id'] ) ) {
			return __( 'Enter the code or link everyone will use before switching this offer on.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * How many of a sponsor's offers is_live() says are live today, one left out. An offer past
	 * its last day holds no slot: it is shown to nobody, so it does not count against the cap.
	 *
	 * @param string $record    Sponsor key.
	 * @param int    $except_id An offer to leave out of the count, or 0 to leave none out.
	 * @param string $today     `Y-m-d`; '' for the site's today.
	 * @return int
	 */
	public static function live_count( $record, $except_id = 0, $today = '' ) {
		$count = 0;

		foreach ( self::offers_of( $record ) as $offer ) {
			if ( (int) $offer['id'] !== (int) $except_id && self::is_live( $offer, $today ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Move an offer to a state. No tier caps live offers since 1.123.18, so nothing counts how many
	 * of a sponsor's offers are live before or after a switch.
	 *
	 * @param int    $offer_id Offer post ID.
	 * @param string $state    A state.
	 * @return true|WP_Error `wpcpm_offer_missing`, `wpcpm_offer_state`, `wpcpm_offer_transition`, `wpcpm_offer_empty`.
	 */
	public static function set_state( $offer_id, $state ) {
		$offer = self::read( (int) $offer_id );

		if ( null === $offer ) {
			return new WP_Error( 'wpcpm_offer_missing', __( 'That offer does not exist.', 'wpcredits-program-manager' ) );
		}

		$state = (string) $state;

		if ( ! in_array( $state, self::states(), true ) ) {
			return new WP_Error( 'wpcpm_offer_state', __( 'That is not a state an offer can be in.', 'wpcredits-program-manager' ) );
		}

		if ( ! in_array( $state, self::transitions()[ $offer['state'] ], true ) ) {
			return new WP_Error( 'wpcpm_offer_transition', __( 'The offer cannot move to that state from where it is.', 'wpcredits-program-manager' ) );
		}

		if ( self::STATE_LIVE === $state ) {
			$why = self::cannot_go_live( $offer );

			if ( '' !== $why ) {
				return new WP_Error( 'wpcpm_offer_empty', $why );
			}
		}

		update_post_meta( $offer['id'], self::META_STATE, $state );

		// An offer leaving Waiting for review, or ending with a change held, leaves the queue.
		if ( self::is_waiting( $offer ) ) {
			self::forget_queue();
		}

		// What goes live has the program's OK: a resume, an Administrator's press, or a draft made
		// with it. A member's unapproved draft is sent for review instead (`submit()`).
		if ( self::STATE_LIVE === $state ) {
			update_post_meta( $offer['id'], self::META_APPROVED, '1' );
		}

		self::touch( $offer['id'], 'state-' . $state );

		return true;
	}

	/**
	 * Move a live offer to dormant: the program's own move, never a sponsor's, made when a
	 * tier's cap no longer covers it.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return bool Whether the offer was live and is now dormant.
	 */
	public static function set_dormant( $offer_id ) {
		$offer = self::read( (int) $offer_id );

		if ( null === $offer || self::STATE_LIVE !== $offer['state'] ) {
			return false;
		}

		update_post_meta( $offer['id'], self::META_STATE, self::STATE_DORMANT );
		self::touch( $offer['id'], 'dormant' );

		return true;
	}

	/**
	 * Move a dormant offer back to live: the program's own move, once a tier covers it again.
	 *
	 * @param int $offer_id Offer post ID.
	 * @return bool Whether the offer was dormant and is now live.
	 */
	public static function wake( $offer_id ) {
		$offer = self::read( (int) $offer_id );

		if ( null === $offer || self::STATE_DORMANT !== $offer['state'] ) {
			return false;
		}

		update_post_meta( $offer['id'], self::META_STATE, self::STATE_LIVE );
		self::touch( $offer['id'], 'woken' );

		return true;
	}

	/**
	 * The dormant sentence the card prints while the tiers are announced;
	 * `WPCPM_Sponsor_Posts::dormant_note()` is the posts' own. No tier caps live offers since
	 * 1.123.18, when the partner page offered products at any level, separately from the levels, so
	 * an offer the daily run parked under an earlier cap is only waiting on the next run,
	 * `WPCPM_Sponsor_Tiers_Run`, to wake it, and the sentence says so and names no tier.
	 *
	 * @return string
	 */
	private static function dormant_sentence() {
		return __( 'Dormant: offers are separate from the tiers now, so it resumes by itself on the next daily run. You can end it.', 'wpcredits-program-manager' );
	}

	/**
	 * Add codes through the offer rather than the pool, so the low-stock warning re-arms
	 * (spec 6.6: adding codes clears the stamp) and the change is on the offer's record.
	 *
	 * @param int    $offer_id Offer post ID.
	 * @param string $text     The paste.
	 * @return int|WP_Error How many were added.
	 */
	public static function add_codes( $offer_id, $text ) {
		$added = WPCPM_Sponsor_Codes::add( (int) $offer_id, $text );

		if ( is_wp_error( $added ) ) {
			return $added;
		}

		delete_post_meta( (int) $offer_id, self::META_LOW_SENT );
		self::touch( (int) $offer_id, 'codes-added' );

		return $added;
	}

	/**
	 * The codes a form carries: the paste box and the uploaded file, as one text.
	 *
	 * A sponsor with a spreadsheet of five hundred codes should not have to paste them, and a
	 * sponsor with three should not have to make a file. Both arrive here one code a line and
	 * go through the same parse, so a file gets exactly the paste's rules (the line ceiling,
	 * the all-or-nothing refusal that names the line) and nothing new to learn. The paste comes
	 * first, so a line number in a refusal counts the paste's lines before the file's.
	 *
	 * @return string|WP_Error The text, '' when neither was given, or the upload's refusal.
	 */
	public static function codes_text() {
		$upload = self::uploaded_codes_text();

		if ( is_wp_error( $upload ) ) {
			return $upload;
		}

		// Verbatim, not sanitize_textarea_field(): a pasted pool is codes and checkout links, and
		// core's percent stripping would corrupt every line with a `%XX` in it (finding 1).
		$pasted = WPCPM_Request::posted_verbatim_lines( 'wpcpm_codes' );

		return trim( $pasted . "\n" . $upload, "\r\n" );
	}

	/**
	 * The text of the codes file, when one came with the request.
	 *
	 * Taken only when PHP itself received it as an upload (is_uploaded_file()), it is named
	 * .txt or .csv and it weighs under UPLOAD_MAX. wp_handle_upload() is not used on purpose:
	 * the file is read once and never kept. Codes are stored sealed, and a plain-text copy in
	 * the uploads directory would undo that.
	 *
	 * @return string|WP_Error The file's text, '' when no file was chosen, or the refusal.
	 */
	public static function uploaded_codes_text() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The handler verified the nonce before reaching here.
		if ( empty( $_FILES['wpcpm_codes_file'] ) || ! is_array( $_FILES['wpcpm_codes_file'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; every member is checked below before it is used.
		$file  = $_FILES['wpcpm_codes_file'];
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return '';
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'wpcpm_codes_upload', __( 'The file did not arrive whole. Try the upload again.', 'wpcredits-program-manager' ) );
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';

		if ( ! preg_match( '/\.(txt|csv)$/i', $name ) ) {
			return new WP_Error( 'wpcpm_codes_upload', __( 'Upload a .txt or .csv file with one code per line.', 'wpcredits-program-manager' ) );
		}

		$tmp  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( $size > self::UPLOAD_MAX || ( '' !== $tmp && is_file( $tmp ) && filesize( $tmp ) > self::UPLOAD_MAX ) ) {
			return new WP_Error( 'wpcpm_codes_upload', __( 'The file is larger than a megabyte. A list of codes is far smaller than that.', 'wpcredits-program-manager' ) );
		}

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'wpcpm_codes_upload', __( 'The file could not be read. Try the upload again.', 'wpcredits-program-manager' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A form upload PHP just received, read once and never stored; WP_Filesystem is for files the site keeps.
		$bytes = file_get_contents( $tmp );

		return self::clean_upload_text( false === $bytes ? '' : $bytes );
	}

	/**
	 * An uploaded file cleaned the way a paste is (WPCPM_Request::posted_verbatim()): the
	 * byte-order mark a spreadsheet export carries, invalid UTF-8 and control characters go;
	 * percent signs and everything else a code or checkout link is made of stay (finding 1).
	 * Line breaks are kept: the parser counts them.
	 *
	 * @param string $bytes The file's bytes.
	 * @return string
	 */
	public static function clean_upload_text( $bytes ) {
		$bytes = (string) $bytes;

		if ( "\xEF\xBB\xBF" === substr( $bytes, 0, 3 ) ) {
			$bytes = substr( $bytes, 3 );
		}

		$text = wp_check_invalid_utf8( $bytes, true );

		return (string) preg_replace( '/[^\P{C}\n\r\t]+/u', '', (string) $text );
	}

	/**
	 * The words for a refused list: the lines at fault for a parse refusal, the sentence for
	 * anything else (an upload that was not a text file, a full pool, a busy lock).
	 *
	 * @param WP_Error $refusal From codes_text() or add_codes().
	 * @return string
	 */
	private static function codes_refusal_detail( WP_Error $refusal ) {
		if ( 'wpcpm_codes_refused' === $refusal->get_error_code() ) {
			return implode( ' ', (array) $refusal->get_error_data() );
		}

		return (string) $refusal->get_error_message();
	}

	/**
	 * Live, and not past its last day. The day is inclusive and the comparison is on strings
	 * (the cohort rule): no timestamp, no timezone, no midnight.
	 *
	 * @param array  $offer An offer.
	 * @param string $today `Y-m-d`; '' for the site's today.
	 * @return bool
	 */
	public static function is_live( array $offer, $today = '' ) {
		if ( self::STATE_LIVE !== $offer['state'] ) {
			return false;
		}

		if ( '' === (string) $offer['expires'] ) {
			return true;
		}

		$today = '' !== (string) $today ? (string) $today : wp_date( 'Y-m-d' );

		return strcmp( (string) $offer['expires'], $today ) >= 0;
	}

	/**
	 * This card's outcomes. A status names the outcome; the detail the dashboard prints after
	 * it names the line or the reason.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			// The sentence after it is the whole message: `WPCPM_Typed_Text::loss_message()` says that
			// nothing was saved, which box, and how to keep every word.
			'offer-loss'              => array( 'error', '' ),
			'offer-created'           => array( 'success', __( 'Your offer was created. Switch it on when it is ready.', 'wpcredits-program-manager' ) ),
			'offer-created-review'    => array( 'success', __( 'Your offer was created as a draft. Send it for review when it is ready: students and mentors see it once an Administrator approves it.', 'wpcredits-program-manager' ) ),
			'offer-sent'              => array( 'success', __( 'Sent for review. Students and mentors see the offer once an Administrator approves it.', 'wpcredits-program-manager' ) ),
			'offer-changes-waiting'   => array( 'success', __( 'Your changes were saved and wait for an Administrator\'s review. Until they are approved, students and mentors see the offer as it was.', 'wpcredits-program-manager' ) ),
			'offer-changes-discarded' => array( 'success', __( 'Your changes were discarded. The offer stays as the program approved it.', 'wpcredits-program-manager' ) ),
			'offer-no-changes'        => array( 'error', __( 'No change of this offer is waiting, so there is nothing to discard.', 'wpcredits-program-manager' ) ),
			// The offer stands and the codes do not: the detail names the line or the file's fault,
			// and the pool's own box on the card takes the corrected list (polish of 1.94.1).
			'offer-created-no-codes'  => array( 'error', __( 'Your offer was created, but the codes were not added. Add them to the offer below.', 'wpcredits-program-manager' ) ),
			'offer-saved'             => array( 'success', __( 'Your offer was saved.', 'wpcredits-program-manager' ) ),
			'offer-rejected'          => array( 'error', __( 'The offer could not be saved.', 'wpcredits-program-manager' ) ),
			'offer-failed'            => array( 'error', __( 'The offer could not be saved right now. Try again later.', 'wpcredits-program-manager' ) ),
			'offer-state-saved'       => array( 'success', __( 'The offer was switched.', 'wpcredits-program-manager' ) ),
			'offer-empty'             => array( 'error', __( 'The offer cannot be switched on yet.', 'wpcredits-program-manager' ) ),
			'offer-transition'        => array( 'error', __( 'The offer cannot move to that state from where it is.', 'wpcredits-program-manager' ) ),
			// The pool's own lock can be held by another request for a
			// moment, and set_shared() and void_unclaimed() both answer that with a WP_Error rather
			// than a count; this is the one sentence both leave with (see handle_save(),
			// handle_codes_void()).
			'offer-busy'              => array( 'info', __( 'Another change to this offer was going through. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'codes-added'             => array( 'success', __( 'Codes added.', 'wpcredits-program-manager' ) ),
			'codes-refused'           => array( 'error', __( 'Nothing was added.', 'wpcredits-program-manager' ) ),
			'codes-none'              => array( 'error', __( 'No codes were found in what you pasted or uploaded.', 'wpcredits-program-manager' ) ),
			'codes-max'               => array( 'error', __( 'An offer holds at most 5000 codes. Talk to the program about a larger pool.', 'wpcredits-program-manager' ) ),
			'codes-voided'            => array( 'success', __( 'Unclaimed codes were voided.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The words the three typed boxes' labels show, so that a refusal names a box as its form does.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			'title'        => __( 'Title', 'wpcredits-program-manager' ),
			'text'         => __( 'What you get, in a sentence or two', 'wpcredits-program-manager' ),
			'instructions' => __( 'How to redeem it', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The form a posted offer was typed into, as `WPCPM_Typed_Text::keep()` names it: a sponsor's
	 * new-offer form, or one offer's edit form.
	 *
	 * @param string $record   Sponsor record ID.
	 * @param int    $offer_id The offer being edited, or 0 for a new one.
	 * @return string
	 */
	private static function typed_form( $record, $offer_id ) {
		return 'offer:' . $record . ':' . ( (int) $offer_id > 0 ? (string) (int) $offer_id : 'new' );
	}

	/**
	 * What was typed into an offer form, read before the cleaner has: each box the person types
	 * into, by its key.
	 *
	 * @return array<string, string>
	 */
	private static function typed_post() {
		$typed = array();

		foreach ( array_keys( self::typed_boxes() ) as $key ) {
			$typed[ $key ] = WPCPM_Request::posted_raw( 'wpcpm_' . $key );
		}

		return $typed;
	}

	/**
	 * Each box of the offer form a person types into, with its limit: the three typed texts, the
	 * link, the threshold and the last day.
	 *
	 * @return array<string, int>
	 */
	private static function typed_boxes() {
		return array(
			'title'        => self::MAX_TITLE,
			'text'         => self::MAX_OFFER,
			'instructions' => self::MAX_TEXT,
			'url'          => WPCPM_Sponsor_Codes::LINE_MAX,
			'low'          => 4,
			'expires'      => 10,
		);
	}

	/**
	 * Keep what a refused offer form held for the one time it is drawn again: every box as typed,
	 * each up to the room it was drawn with, and the kind and the audience as chosen.
	 *
	 * The shared code and pasted codes are not kept. The pool holds a code sealed, and a flash would
	 * hold it in the clear; a list of codes can run to a megabyte.
	 *
	 * @param string     $form     The form, as `typed_form()` names it.
	 * @param array      $typed    What was typed, as `typed_post()` read it.
	 * @param array      $raw      The posted values `handle_save()` hands `clean()`.
	 * @param array|null $existing The offer being edited, or null for a new one.
	 */
	private static function keep_typing( $form, array $typed, array $raw, $existing ) {
		$stored = is_array( $existing ) ? $existing : self::empty_offer();
		$room   = array();

		foreach ( self::typed_boxes() as $key => $limit ) {
			$room[ $key ] = WPCPM_Typed_Text::kept_room( (string) $stored[ $key ], $limit );
		}

		if ( isset( $raw['kind'] ) && in_array( $raw['kind'], self::kinds(), true ) ) {
			$typed['kind'] = $raw['kind'];
			$room['kind']  = strlen( $raw['kind'] );
		}

		$typed['audience'] = array_values( array_intersect( self::AUDIENCES, (array) $raw['audience'] ) );

		WPCPM_Typed_Text::keep( $form, $typed, $room );
	}

	/**
	 * A state, in the sponsor's words.
	 *
	 * @param string $state A state.
	 * @return string
	 */
	public static function state_label( $state ) {
		$labels = array(
			self::STATE_DRAFT   => __( 'Not switched on yet', 'wpcredits-program-manager' ),
			self::STATE_REVIEW  => __( 'Waiting for review', 'wpcredits-program-manager' ),
			self::STATE_LIVE    => __( 'Live', 'wpcredits-program-manager' ),
			self::STATE_PAUSED  => __( 'Paused', 'wpcredits-program-manager' ),
			self::STATE_ENDED   => __( 'Ended', 'wpcredits-program-manager' ),
			self::STATE_DORMANT => __( 'Dormant', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $state ] ) ? $labels[ $state ] : (string) $state;
	}

	/**
	 * The sentence for a clean() reason. A field over its limit names the field, the limit and how far
	 * over it is, so the sponsor knows how much to take out.
	 *
	 * @param string $reason A reason.
	 * @param int    $over   How many characters a long field is over its limit.
	 * @return string
	 */
	private static function reason_sentence( $reason, $over = 0 ) {
		$over = max( 1, (int) $over );

		if ( 'title_long' === $reason ) {
			/* translators: 1: how many characters over, 2: the longest title allowed. */
			return sprintf( _n( 'The title is %1$s character over the limit of %2$s. Shorten it and save again.', 'The title is %1$s characters over the limit of %2$s. Shorten it and save again.', $over, 'wpcredits-program-manager' ), number_format_i18n( $over ), number_format_i18n( self::MAX_TITLE ) );
		}

		if ( 'text_long' === $reason ) {
			/* translators: 1: how many characters over, 2: the longest text allowed. */
			return sprintf( _n( 'What you get is %1$s character over the limit of %2$s. Shorten it and save again.', 'What you get is %1$s characters over the limit of %2$s. Shorten it and save again.', $over, 'wpcredits-program-manager' ), number_format_i18n( $over ), number_format_i18n( self::MAX_OFFER ) );
		}

		if ( 'instructions_long' === $reason ) {
			/* translators: 1: how many characters over, 2: the longest instructions allowed. */
			return sprintf( _n( 'How to redeem it is %1$s character over the limit of %2$s. Shorten it and save again.', 'How to redeem it is %1$s characters over the limit of %2$s. Shorten it and save again.', $over, 'wpcredits-program-manager' ), number_format_i18n( $over ), number_format_i18n( self::MAX_TEXT ) );
		}

		$sentences = array(
			'title'   => __( 'Give the offer a title.', 'wpcredits-program-manager' ),
			'url'     => __( 'The link could not be accepted: check it has no name or password in it.', 'wpcredits-program-manager' ),
			'kind'    => __( 'The kind cannot change once the offer holds codes or claims.', 'wpcredits-program-manager' ),
			'expires' => __( 'The last day must be a real date, written year-month-day.', 'wpcredits-program-manager' ),
		);

		return isset( $sentences[ $reason ] ) ? $sentences[ $reason ] : '';
	}

	/**
	 * The nonce, then the claim, then the offer belongs to the claimed sponsor, then the posted
	 * fields: the order every handler here keeps, so a member cannot reach another sponsor's
	 * offer and nothing posted is read before the sponsor is known.
	 *
	 * @param string $action   The handler's action constant.
	 * @param string $policy   A WPCPM_Sponsor_Policy::ACT_* constant.
	 * @param bool   $may_be_new Whether an offer ID of 0 (a new offer) is allowed.
	 * @return array `record`, `decision`, `row`, `offer` (null for a new one).
	 */
	private static function begin( $action, $policy, $may_be_new = false ) {
		$offer_id = WPCPM_Request::posted_id( 'wpcpm_offer' );
		check_admin_referer( $action . '_' . ( $offer_id > 0 ? $offer_id : 'new' ) );

		$claim = WPCPM_Sponsor_Roster::claim( WPCPM_Request::posted_text( 'wpcpm_sponsor' ), $policy );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused', '' );
		}

		if ( $offer_id < 1 ) {
			if ( ! $may_be_new ) {
				self::leave( 'refused', $claim['record'] );
			}

			$claim['offer'] = null;

			return $claim;
		}

		$offer = self::find( $offer_id, $claim['record'] );

		if ( null === $offer ) {
			self::leave( 'refused', $claim['record'] );
		}

		$claim['offer'] = $offer;

		return $claim;
	}

	/**
	 * Create or edit an offer.
	 */
	public static function handle_save() {
		$claim    = self::begin( self::ACTION_SAVE, WPCPM_Sponsor_Policy::ACT_MANAGE_OFFERS, true );
		$record   = $claim['record'];
		$staff    = self::by_staff( $claim );
		$approved = $claim['offer'];
		// The form drew a change held for review in place of the offer's own words, so what was
		// posted is read against that, and the offer as approved is kept apart for what students see.
		$existing = null === $approved ? null : self::as_drawn( $approved );
		$form     = self::typed_form( $record, null === $existing ? 0 : $existing['id'] );

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( $form );

		$typed = self::typed_post();
		$raw   = array(
			'title'        => WPCPM_Request::posted_text( 'wpcpm_title' ),
			'text'         => WPCPM_Request::posted_lines( 'wpcpm_text' ),
			'instructions' => WPCPM_Request::posted_lines( 'wpcpm_instructions' ),
			// A link is a code, not prose: core's sanitize_text_field() strips every `%XX`, which
			// silently rewrites a pre-filled checkout link (final review of Phase S2, finding 1).
			// clean() still runs this through clean_url(), whose esc_url_raw() keeps them.
			'url'          => WPCPM_Request::posted_verbatim( 'wpcpm_url' ),
			'audience'     => array(),
			'low'          => WPCPM_Request::posted_text( 'wpcpm_low' ),
			'expires'      => WPCPM_Request::posted_text( 'wpcpm_expires' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- begin() verified the nonce.
		if ( isset( $_POST['wpcpm_audience'] ) && is_array( $_POST['wpcpm_audience'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
			$raw['audience'] = array_map( 'sanitize_key', wp_unslash( $_POST['wpcpm_audience'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		if ( isset( $_POST['wpcpm_kind'] ) ) {
			$raw['kind'] = WPCPM_Request::posted_key( 'wpcpm_kind' );
		}

		// A typed box posted back as it was drawn is the stored text, by the test `clean()` reads an
		// unedited box with (`same_text()`, `same_lines()`, and for the title the stored title with
		// each line break a space, as its box draws it), asked of what was typed. It is neither asked
		// about losses nor cleaned and rewritten: a stored text can hold what the cleaner would take,
		// written by another path than this card's save, and only what a person changed is theirs to
		// be told about.
		$drawn = array();

		if ( null !== $existing ) {
			foreach ( array(
				'title'        => false,
				'text'         => true,
				'instructions' => true,
			) as $key => $lines ) {
				$stored = (string) $existing[ $key ];

				if ( $lines ? WPCPM_Typed_Text::same_lines( $typed[ $key ], $stored ) : ( WPCPM_Typed_Text::same_text( $typed[ $key ], $stored ) || WPCPM_Typed_Text::same_text( $typed[ $key ], WPCPM_Typed_Text::one_line( $stored ) ) ) ) {
					$drawn[] = $key;
				}
			}
		}

		// A typing the cleaner would take words from is refused before anything else is asked of it,
		// and the form gets back what was typed: saved, it would lose the words without a word said.
		foreach ( array(
			'title'        => 'line',
			'text'         => 'lines',
			'instructions' => 'lines',
		) as $key => $kind ) {
			if ( ! in_array( $key, $drawn, true ) && WPCPM_Typed_Text::cleaner_loses( $typed[ $key ], $kind ) ) {
				self::keep_typing( $form, $typed, $raw, $existing );
				self::leave( 'offer-loss', $record, WPCPM_Typed_Text::loss_message( self::labels()[ $key ] ) );
			}
		}

		$cleaned = self::clean( $raw, $existing, $drawn );

		if ( ! $cleaned['ok'] ) {
			// A box over its limit is to be shortened, so the form comes back with what was typed.
			if ( in_array( $cleaned['reason'], array( 'title_long', 'text_long', 'instructions_long' ), true ) ) {
				self::keep_typing( $form, $typed, $raw, $existing );
			}

			self::leave( 'offer-rejected', $record, self::reason_sentence( $cleaned['reason'], $cleaned['over'] ) );
		}

		// The shared code is checked before the offer is written, so a refusal here leaves
		// nothing behind: a "could not be saved" that had already created the offer would
		// leave an orphan the sponsor can see and not explain.
		$shared = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- begin() verified the nonce.
		if ( self::KIND_SHARED === $cleaned['fields']['kind'] && isset( $_POST['wpcpm_shared'] ) ) {
			// Read verbatim for the same reason as the link above: a shared code may be a whole
			// checkout URL, and `%20` in it is part of the code.
			$shared = WPCPM_Request::posted_verbatim( 'wpcpm_shared' );

			if ( mb_strlen( $shared ) > WPCPM_Sponsor_Codes::LINE_MAX ) {
				/* translators: %d: the longest code or link allowed. */
				self::leave( 'offer-rejected', $record, sprintf( __( 'The shared code or link is longer than %d characters.', 'wpcredits-program-manager' ), WPCPM_Sponsor_Codes::LINE_MAX ) );
			}

			// set_state() refuses going live empty; nothing refused emptying one that is already
			// live, which left students a card with no button and no explanation (finding 8).
			if ( '' === $shared && null !== $existing && self::STATE_LIVE === $existing['state'] ) {
				self::leave( 'offer-rejected', $record, __( 'Pause the offer before removing its code: students see it right now.', 'wpcredits-program-manager' ) );
			}
		}

		// What happened to the codes handed in with a new pool: how many went in, or why none did.
		$codes_note = '';
		$codes_fail = '';
		$codes_text = '';

		// A pool and its codes in one step (polish of 1.94.1), but read and parsed before the
		// offer post exists: a refused list used to leave an orphan draft behind, and a paste too
		// big to parse took the request with it after the post had been written (deep check
		// FOFFR-1). Nothing here writes; the same text is added below, to the offer it makes.
		if ( null === $existing && self::KIND_CODES === $cleaned['fields']['kind'] ) {
			$codes_text = self::codes_text();

			if ( is_wp_error( $codes_text ) ) {
				self::leave( 'offer-rejected', $record, self::codes_refusal_detail( $codes_text ) );
			}

			if ( '' !== $codes_text ) {
				$parsed = WPCPM_Sponsor_Codes::parse( $codes_text );

				if ( ! empty( $parsed['errors'] ) ) {
					self::leave( 'offer-rejected', $record, self::codes_refusal_detail( WPCPM_Sponsor_Codes::refusal( $parsed ) ) );
				}
			}
		}

		// A member's change to what students see of a visible offer waits for review: the words, the
		// link and the shared code are held beside the offer, and the rest is saved at once.
		$held = null;

		if ( null !== $existing && ! $staff && in_array( $approved['state'], self::visible_states(), true ) ) {
			$held = array_intersect_key( $cleaned['fields'], array_flip( self::REVIEWED ) );

			if ( self::KIND_SHARED === $approved['kind'] ) {
				$held['shared'] = null !== $shared ? $shared : ( isset( $approved['proposed']['shared'] ) ? $approved['proposed']['shared'] : WPCPM_Sponsor_Codes::shared( $approved['id'] ) );
			}

			$shared = null;
		}

		if ( null === $existing ) {
			// The first offer a sponsor gets is the one marked primary; a member's waits for review,
			// and one the program's own staff make has its OK as it is made.
			$offer_id = self::create( $record, $cleaned['fields'], empty( self::offers_of( $record ) ), $staff );

			if ( is_wp_error( $offer_id ) ) {
				self::leave( 'offer-failed', $record );
			}

			// The list parsed cleanly above, so what is left to go wrong here is the pool's own
			// answer: a list with no code in it at all, or a lock somebody else is holding.
			if ( '' !== $codes_text ) {
				$added = self::add_codes( $offer_id, $codes_text );

				if ( is_wp_error( $added ) ) {
					$codes_fail = self::codes_refusal_detail( $added );
				} else {
					/* translators: %s: how many codes. */
					$codes_note = sprintf( _n( '%s code added.', '%s codes added.', $added, 'wpcredits-program-manager' ), number_format_i18n( $added ) );
				}
			}
		} else {
			$offer_id = $existing['id'];

			if ( null !== $held ) {
				self::save( $offer_id, array_diff_key( $cleaned['fields'], $held ) );
			} else {
				self::save( $offer_id, $cleaned['fields'] );
			}

			if ( $staff ) {
				// What the program's own staff save is what students see, with its OK, and any change
				// a member had waiting is the one the form drew, now saved.
				update_post_meta( $offer_id, self::META_APPROVED, '1' );
				self::discard( $offer_id );
			} elseif ( null === $held && self::changes_words( $approved, $cleaned['fields'], $shared ) ) {
				// A member's draft or offer waiting for review, changed: its words need the OK again.
				update_post_meta( $offer_id, self::META_APPROVED, '0' );
			}
		}

		$waiting = false;

		if ( null !== $held ) {
			if ( self::changes_words( $approved, $held, isset( $held['shared'] ) ? $held['shared'] : null ) ) {
				if ( is_wp_error( self::propose( $offer_id, $held, get_current_user_id() ) ) ) {
					self::leave( 'offer-failed', $record );
				}

				$waiting = true;
			} elseif ( ! empty( $approved['proposed'] ) ) {
				// Typed back to what students see: nothing is left to review.
				self::discard( $offer_id );
			}
		}

		if ( null !== $shared ) {
			$stored = WPCPM_Sponsor_Codes::set_shared( $offer_id, $shared );

			if ( is_wp_error( $stored ) ) {
				self::leave( 'offer-busy', $record );
			}
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_SAVED,
				'sponsor'  => $record,
				'subject'  => (string) $offer_id,
				'actor'    => get_current_user_id(),
				'ground'   => $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => null === $existing
					? __( 'An offer was created.', 'wpcredits-program-manager' )
					/* translators: %s: the fields changed, comma-separated. */
					: sprintf( __( 'Offer fields changed: %s.', 'wpcredits-program-manager' ), implode( ', ', array_keys( $cleaned['fields'] ) ) ),
				// The field names, never the values: the offer is the sponsor's copy, and the
				// log is not the place to keep a second one.
				'data'     => array(
					'offer'   => $offer_id,
					'created' => null === $existing,
					'fields'  => array_keys( $cleaned['fields'] ),
					'codes'   => '' !== $codes_note,
					'waiting' => $waiting,
				),
			)
		);

		// The one thing here the sponsor has to act on now: the offer itself is already saved.
		if ( '' !== $codes_fail ) {
			self::leave( 'offer-created-no-codes', $record, $codes_fail );
		}

		if ( null === $existing ) {
			self::leave( $staff ? 'offer-created' : 'offer-created-review', $record, $codes_note );
		}

		self::leave( $waiting || ( null !== $held && self::is_waiting( (array) self::read( $offer_id ) ) ) ? 'offer-changes-waiting' : 'offer-saved', $record, $codes_note );
	}

	/**
	 * Whether a claim is the program's own staff acting, whose offers need no review.
	 *
	 * @param array $claim What `begin()` answered.
	 * @return bool
	 */
	private static function by_staff( array $claim ) {
		return isset( $claim['decision']['ground'] ) && WPCPM_Institution_Audit::GROUND_MANAGER === $claim['decision']['ground'];
	}

	/**
	 * Whether cleaned fields change what students would see of an offer: its words, its link or, given,
	 * its shared code.
	 *
	 * @param array       $offer  The offer, as approved.
	 * @param array       $fields Cleaned fields; the reviewed ones are compared.
	 * @param string|null $shared The shared code posted, or null when none was.
	 * @return bool
	 */
	private static function changes_words( array $offer, array $fields, $shared ) {
		foreach ( self::REVIEWED as $key ) {
			if ( array_key_exists( $key, $fields ) && (string) $fields[ $key ] !== (string) $offer[ $key ] ) {
				return true;
			}
		}

		return null !== $shared && self::KIND_SHARED === $offer['kind'] && (string) WPCPM_Sponsor_Codes::shared( $offer['id'] ) !== (string) $shared;
	}

	/**
	 * Switch an offer on, pause it, resume it or end it.
	 */
	public static function handle_state() {
		$claim  = self::begin( self::ACTION_STATE, WPCPM_Sponsor_Policy::ACT_MANAGE_OFFERS );
		$record = $claim['record'];
		$offer  = $claim['offer'];
		$state  = WPCPM_Request::posted_key( 'wpcpm_state' );
		// A member's draft without the program's OK is sent for review rather than switched on.
		$send  = self::STATE_LIVE === $state && self::STATE_DRAFT === $offer['state'] && ! $offer['approved'] && ! self::by_staff( $claim );
		$moved = $send ? self::submit( $offer['id'], get_current_user_id() ) : self::set_state( $offer['id'], $state );

		if ( $send && ! is_wp_error( $moved ) ) {
			$state = self::STATE_REVIEW;
		}

		if ( is_wp_error( $moved ) ) {
			$statuses = array(
				'wpcpm_offer_empty'      => 'offer-empty',
				'wpcpm_offer_transition' => 'offer-transition',
			);
			$code     = $moved->get_error_code();
			$detail   = 'wpcpm_offer_empty' === $code ? $moved->get_error_message() : '';

			self::leave( isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'offer-failed', $record, $detail );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_STATE,
				'sponsor'  => $record,
				'subject'  => (string) $offer['id'],
				'actor'    => get_current_user_id(),
				'ground'   => $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				/* translators: 1: the state before, 2: the state after. */
				'message'  => sprintf( __( 'An offer moved from %1$s to %2$s.', 'wpcredits-program-manager' ), $offer['state'], $state ),
				'data'     => array(
					'offer' => $offer['id'],
					'from'  => $offer['state'],
					'to'    => $state,
				),
			)
		);

		self::leave( $send ? 'offer-sent' : 'offer-state-saved', $record );
	}

	/**
	 * Discard a change held for review, waiting or returned: the offer stays as it was approved. It
	 * shows students nothing new, so it takes effect at once.
	 */
	public static function handle_discard() {
		$claim  = self::begin( self::ACTION_DISCARD, WPCPM_Sponsor_Policy::ACT_MANAGE_OFFERS );
		$record = $claim['record'];
		$offer  = $claim['offer'];

		if ( empty( $offer['proposed'] ) ) {
			self::leave( 'offer-no-changes', $record );
		}

		self::discard( $offer['id'] );
		self::touch( $offer['id'], 'changes-discarded' );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_SAVED,
				'sponsor'  => $record,
				'subject'  => (string) $offer['id'],
				'actor'    => get_current_user_id(),
				'ground'   => $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => __( 'The changes waiting on an offer were discarded.', 'wpcredits-program-manager' ),
				'data'     => array(
					'offer'     => $offer['id'],
					'discarded' => true,
				),
			)
		);

		self::leave( 'offer-changes-discarded', $record );
	}

	/**
	 * Paste codes into a pool.
	 */
	public static function handle_codes_add() {
		$claim  = self::begin( self::ACTION_CODES_ADD, WPCPM_Sponsor_Policy::ACT_MANAGE_OFFERS );
		$record = $claim['record'];
		$offer  = $claim['offer'];

		if ( self::KIND_CODES !== $offer['kind'] ) {
			self::leave( 'offer-rejected', $record, __( 'This offer has one shared code; there is no pool to add to.', 'wpcredits-program-manager' ) );
		}

		// The paste box, the uploaded file or both, read verbatim (see codes_text()).
		$text  = self::codes_text();
		$added = is_wp_error( $text ) ? $text : self::add_codes( $offer['id'], $text );

		if ( is_wp_error( $added ) ) {
			$statuses = array(
				'wpcpm_codes_refused' => 'codes-refused',
				'wpcpm_codes_upload'  => 'codes-refused',
				'wpcpm_codes_none'    => 'codes-none',
				'wpcpm_codes_max'     => 'codes-max',
				'wpcpm_codes_busy'    => 'offer-busy',
			);
			$code     = $added->get_error_code();
			$detail   = in_array( $code, array( 'wpcpm_codes_refused', 'wpcpm_codes_upload' ), true ) ? self::codes_refusal_detail( $added ) : '';

			self::leave( isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'offer-failed', $record, $detail );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_CODES,
				'sponsor'  => $record,
				'subject'  => (string) $offer['id'],
				'actor'    => get_current_user_id(),
				'ground'   => $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				/* translators: %d: how many codes. */
				'message'  => sprintf( __( '%d codes were added to an offer.', 'wpcredits-program-manager' ), $added ),
				'data'     => array(
					'offer' => $offer['id'],
					'added' => (int) $added,
				),
			)
		);

		/* translators: %d: how many codes. */
		self::leave( 'codes-added', $record, sprintf( _n( '%d code added.', '%d codes added.', $added, 'wpcredits-program-manager' ), $added ) );
	}

	/**
	 * Void every code nobody holds.
	 */
	public static function handle_codes_void() {
		$claim  = self::begin( self::ACTION_CODES_VOID, WPCPM_Sponsor_Policy::ACT_MANAGE_OFFERS );
		$record = $claim['record'];
		$offer  = $claim['offer'];
		$voided = WPCPM_Sponsor_Codes::void_unclaimed( $offer['id'] );

		// The pool's lock can be another request's for a moment: the
		// void did not happen, so nothing is touched or logged, unlike every other error this
		// handler's sibling above map by code - there is only the one code void_unclaimed() ever
		// returns.
		if ( is_wp_error( $voided ) ) {
			self::leave( 'offer-busy', $record );
		}

		if ( $voided > 0 ) {
			self::touch( $offer['id'], 'codes-voided' );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_CODES,
				'sponsor'  => $record,
				'subject'  => (string) $offer['id'],
				'actor'    => get_current_user_id(),
				'ground'   => $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				/* translators: %d: how many codes. */
				'message'  => sprintf( __( '%d unclaimed codes were voided.', 'wpcredits-program-manager' ), $voided ),
				'data'     => array(
					'offer'  => $offer['id'],
					'voided' => (int) $voided,
				),
			)
		);

		/* translators: %d: how many codes. */
		self::leave( 'codes-voided', $record, sprintf( _n( '%d code voided.', '%d codes voided.', $voided, 'wpcredits-program-manager' ), $voided ) );
	}

	/**
	 * The card: the form for a new offer, then every offer with its forms.
	 *
	 * Open whatever a flash says, since what the company offers is its live state (the design the
	 * owner approved on 27 September 2026), so the context every card is handed asks nothing of it.
	 * The row says how many offers are live today and how many claims stand this month, the two
	 * figures the At a glance tile reads from the same readers, and, while any offer or change of the
	 * sponsor's waits for an Administrator's review (1.123.18), how many between them. New offer follows the fold, a link to
	 * the card, whose body opens on the form for a new offer under its own New offer heading: drawn
	 * open, the card has it hidden by the stylesheets, which show a card's action only while the card
	 * is folded, so it shows once the sponsor folds the card.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$offers = self::offers_of( $record );
		// The program's own staff, acting for the sponsor, need no review: the card says so and offers
		// them Switch on where a member is offered Send for review.
		$staff = ! empty( $context['can_manage'] );
		$stats = WPCPM_Sponsor_Claims::stats( $record );
		$month = isset( $stats['totals']['month'] ) ? (int) $stats['totals']['month'] : 0;
		// What waits for an Administrator, offers and changes alike, so a folded card says so too.
		$queued = count( array_filter( $offers, array( __CLASS__, 'is_waiting' ) ) );

		WPCPM_Dashboard_Card::open(
			array(
				'key'    => self::CARD,
				'title'  => __( 'Offers and codes', 'wpcredits-program-manager' ),
				'count'  => count( $offers ),
				'open'   => true,
				'state'  => 0 < $queued
					? sprintf(
						/* translators: 1: how many of the sponsor's offers are live today, 2: how many of its offers, or changes to them, wait for an Administrator's review, 3: how many times they were claimed this month. */
						_n( '%1$s live, %2$s waiting for review, %3$s claim this month', '%1$s live, %2$s waiting for review, %3$s claims this month', $month, 'wpcredits-program-manager' ),
						number_format_i18n( self::live_count( $record ) ),
						number_format_i18n( $queued ),
						number_format_i18n( $month )
					)
					: sprintf(
						/* translators: 1: how many of the sponsor's offers are live today, 2: how many times they were claimed this month. */
						_n( '%1$s live, %2$s claim this month', '%1$s live, %2$s claims this month', $month, 'wpcredits-program-manager' ),
						number_format_i18n( self::live_count( $record ) ),
						number_format_i18n( $month )
					),
				'action' => array(
					'label'   => __( 'New offer', 'wpcredits-program-manager' ),
					/* translators: read after "New offer" by a screen reader alone. */
					'context' => __( 'for the program\'s students and mentors', 'wpcredits-program-manager' ),
				),
			)
		);

		// The new-offer form first and on the left, the sponsor's offers on the right, folded to
		// a title and a line of counts: the ones in play under "Active offers", the ended and
		// expired ones under their own title (owner, 1.97.1). A draft and a paused offer are in
		// play - a paused offer resumes with one press - and an ended one never is.
		$active = array();
		$past   = array();

		foreach ( $offers as $offer ) {
			if ( self::is_past( $offer ) ) {
				$past[] = $offer;
			} else {
				$active[] = $offer;
			}
		}

		echo '<div class="wpcpm-offers__cols"><div class="wpcpm-offers__col wpcpm-offers__col--new">';
		self::render_new_form( $record, $staff );
		echo '</div><div class="wpcpm-offers__col wpcpm-offers__col--list">';
		self::render_list( __( 'Active offers', 'wpcredits-program-manager' ), $active, $record, __( 'No offer yet. Create one on the left, and it appears here.', 'wpcredits-program-manager' ), $staff );

		if ( ! empty( $past ) ) {
			self::render_list( __( 'Ended and expired offers', 'wpcredits-program-manager' ), $past, $record, '', $staff );
		}

		echo '</div></div>';
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Whether an offer is over: ended by the sponsor, or past the last day it named.
	 *
	 * @param array  $offer The offer.
	 * @param string $today Y-m-d, for the suites; today by default.
	 * @return bool
	 */
	public static function is_past( array $offer, $today = '' ) {
		if ( self::STATE_ENDED === $offer['state'] ) {
			return true;
		}

		if ( '' === (string) $offer['expires'] ) {
			return false;
		}

		$today = '' !== (string) $today ? (string) $today : wp_date( 'Y-m-d' );

		return strcmp( (string) $offer['expires'], $today ) < 0;
	}

	/**
	 * One list on the right: a sub-heading, then the offers folded, or one quiet sentence.
	 *
	 * @param string $title  The list's title.
	 * @param array  $offers The offers in it.
	 * @param string $record Sponsor key.
	 * @param string $empty  The sentence for an empty list, or '' to print nothing.
	 * @param bool   $staff  Whether the program's own staff are looking, whose presses need no review.
	 */
	private static function render_list( $title, array $offers, $record, $empty, $staff = false ) {
		printf( '<h4 class="wpcpm-sponsor__subheading">%s</h4>', esc_html( $title ) );

		if ( empty( $offers ) ) {
			if ( '' !== $empty ) {
				printf( '<p class="wpcpm-student__note wpcpm-offers__empty">%s</p>', esc_html( $empty ) );
			}

			return;
		}

		foreach ( $offers as $offer ) {
			self::render_offer( $offer, $record, $staff );
		}
	}

	/**
	 * One offer: its heading and counts, the edit form, the state buttons, the codes box.
	 *
	 * The pill says the offer's state, but for an offer switched on and past its last day: that one is
	 * over, listed with the ended offers and counted live nowhere (`is_live()`), so its pill says
	 * Expired rather than Live.
	 *
	 * The details are the offer as students see it; the edit form draws a change held for review in
	 * place of those words, and between the two a note says the change waits, or was returned with the
	 * Administrator's note, with the press that discards it.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor key.
	 * @param bool   $staff  Whether the program's own staff are looking.
	 */
	private static function render_offer( array $offer, $record, $staff = false ) {
		$counts = WPCPM_Sponsor_Codes::counts( $offer['id'] );
		$fixed  = self::kind_is_fixed( $offer );
		$pill   = self::STATE_LIVE === $offer['state'] && self::is_past( $offer ) ? __( 'Expired', 'wpcredits-program-manager' ) : self::state_label( $offer['state'] );
		// A refused save of this offer's form: the offer and its form are drawn open, so the typing
		// is where the person left it.
		$kept = WPCPM_Typed_Text::kept( self::typed_form( $record, $offer['id'] ) );

		printf( '<details class="wpcpm-offer wpcpm-offer--%1$s" id="wpcpm-offer-%2$d"%3$s><summary class="wpcpm-offer__summary">', esc_attr( $offer['state'] ), (int) $offer['id'], empty( $kept ) ? '' : ' open' );
		printf(
			'<h4 class="wpcpm-offer__title">%1$s <span class="wpcpm-offer__state">%2$s</span>%3$s</h4>',
			esc_html( $offer['title'] ),
			esc_html( $pill ),
			$offer['primary'] ? ' <span class="wpcpm-offer__primary">' . esc_html__( '(primary)', 'wpcredits-program-manager' ) . '</span>' : ''
		);

		if ( self::KIND_CODES === $offer['kind'] ) {
			printf(
				'<span class="wpcpm-offer__counts">%s</span>',
				esc_html(
					sprintf(
						/* translators: 1: available, 2: claimed, 3: void. */
						__( '%1$d available, %2$d claimed, %3$d void', 'wpcredits-program-manager' ),
						$counts['available'],
						$counts['claimed'],
						$counts['void']
					)
				)
			);

			if ( self::STATE_LIVE === $offer['state'] && $counts['available'] < (int) $offer['low'] ) {
				/* translators: %d: the threshold. */
				printf( '<span class="wpcpm-offer__warning">%s</span>', esc_html( sprintf( __( 'Running low: fewer than %d codes left. Add more, or pause the offer.', 'wpcredits-program-manager' ), (int) $offer['low'] ) ) );
			}
		} else {
			$taken = 0;

			foreach ( WPCPM_Sponsor_Codes::claims( $offer['id'] ) as $claim ) {
				if ( empty( $claim['v'] ) ) {
					++$taken;
				}
			}

			/* translators: %d: how many people. */
			printf( '<span class="wpcpm-offer__counts">%s</span>', esc_html( sprintf( _n( '%d person has taken it', '%d people have taken it', $taken, 'wpcredits-program-manager' ), $taken ) ) );
		}

		echo '</summary><div class="wpcpm-offer__body">';
		// Open, an offer reads as text first (owner, 1.97.4): its details, then Edit this offer, which
		// reveals the form; the state moves under a title of their own; the codes last.
		self::render_details( $offer );
		self::render_changes( $offer, $record );
		printf( '<details class="wpcpm-offer__edit" id="wpcpm-offer-edit-%1$d"%3$s><summary class="wpcpm-button wpcpm-button--secondary wpcpm-offer__edit-toggle">%2$s</summary>', (int) $offer['id'], esc_html__( 'Edit this offer', 'wpcredits-program-manager' ), empty( $kept ) ? '' : ' open' );
		self::render_edit_form( self::as_drawn( $offer ), $record, $fixed, $kept );
		echo '</details>';
		self::render_state_block( $offer, $record, $staff );

		if ( self::KIND_CODES === $offer['kind'] && self::STATE_ENDED !== $offer['state'] ) {
			// Adding codes is another job than editing the offer: its own block under a line (owner, 1.97.2).
			echo '<div class="wpcpm-offer__codes">';
			self::render_codes_forms( $offer, $record, $counts );
			echo '</div>';
		}

		echo '</div></details>';
	}

	/**
	 * The fields, for an existing offer or a new one.
	 *
	 * @param array|null $offer  The offer, or null for the new-offer form.
	 * @param string     $record Sponsor key.
	 * @param bool       $fixed  Whether the kind may no longer change.
	 * @param array      $kept   What a refused save of this form kept (`WPCPM_Typed_Text::kept()`).
	 */
	private static function render_fields( $offer, $record, $fixed, array $kept = array() ) {
		$is_new = null === $offer;
		$offer  = $is_new ? self::empty_offer() : $offer;
		$id     = $is_new ? 'new' : (string) $offer['id'];
		$labels = self::labels();

		// After a refused save, the kind and the audience are drawn as they were chosen.
		if ( ! $fixed && isset( $kept['kind'] ) && in_array( $kept['kind'], self::kinds(), true ) ) {
			$offer['kind'] = $kept['kind'];
		}

		if ( isset( $kept['audience'] ) && is_array( $kept['audience'] ) ) {
			$offer['audience'] = array_values( array_intersect( self::AUDIENCES, $kept['audience'] ) );
		}

		// And each box what was typed into it, exactly (`WPCPM_Typed_Text::kept_attr()`), in place of
		// the stored value as the box draws it.
		$input = static function ( $key, $drawn ) use ( $kept ) {
			return isset( $kept[ $key ] ) && is_string( $kept[ $key ] ) ? esc_attr( WPCPM_Typed_Text::kept_attr( $kept[ $key ] ) ) : esc_attr( $drawn );
		};
		// The parser drops one line feed right after a text area's opening tag, so a kept typing, which
		// can begin with a line break, is given one of its own first.
		$area  = static function ( $key, $drawn ) use ( $kept ) {
			return isset( $kept[ $key ] ) && is_string( $kept[ $key ] ) ? "\n" . esc_textarea( $kept[ $key ] ) : esc_textarea( $drawn );
		};
		$field = static function ( $key, $label, $control, $required = false ) use ( $id ) {
			printf(
				'<p class="wpcpm-sponsor__field"><label for="wpcpm-offer-%1$s-%2$s">%3$s%4$s</label>%5$s</p>',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_html( $label ),
				$required ? ' <span class="wpcpm-field__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>' : '',
				$control // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller.
			);
		};

		// The boxes draw the text a person typed, not the form it is stored in: a text input through
		// `esc_attr( attr_text() )` and a text area through `esc_textarea( typed_text() )`, each with
		// the `maxlength` that leaves the store's room (`drawn_limit()`). The title's box draws each
		// line break the stored title holds as a space (`one_line()`), which the box would otherwise
		// drop, joining the words on either side. A box drawn with what a refused save kept keeps the
		// same room: it is the room the person typed in.
		$field( 'title', $labels['title'], sprintf( '<input type="text" id="wpcpm-offer-%1$s-title" name="wpcpm_title" value="%2$s" maxlength="%3$d" required />', esc_attr( $id ), $input( 'title', WPCPM_Typed_Text::one_line( WPCPM_Typed_Text::attr_text( $offer['title'] ) ) ), (int) WPCPM_Typed_Text::drawn_limit( $offer['title'], self::MAX_TITLE ) ), true );
		$field( 'text', $labels['text'], sprintf( '<textarea id="wpcpm-offer-%1$s-text" name="wpcpm_text" rows="2" maxlength="%2$d">%3$s</textarea>', esc_attr( $id ), (int) WPCPM_Typed_Text::drawn_limit( $offer['text'], self::MAX_OFFER ), $area( 'text', WPCPM_Typed_Text::typed_text( $offer['text'] ) ) ) );
		$field( 'instructions', $labels['instructions'], sprintf( '<textarea id="wpcpm-offer-%1$s-instructions" name="wpcpm_instructions" rows="4" maxlength="%2$d">%3$s</textarea>', esc_attr( $id ), (int) WPCPM_Typed_Text::drawn_limit( $offer['instructions'], self::MAX_TEXT ), $area( 'instructions', WPCPM_Typed_Text::typed_text( $offer['instructions'] ) ) ) );
		$field( 'url', __( 'Link with more information, or where to redeem', 'wpcredits-program-manager' ), sprintf( '<input type="url" id="wpcpm-offer-%1$s-url" name="wpcpm_url" value="%2$s" maxlength="%3$d" />', esc_attr( $id ), $input( 'url', $offer['url'] ), (int) WPCPM_Sponsor_Codes::LINE_MAX ) );

		if ( $fixed ) {
			// The kind shown here is a static value, not a control, so the note stays a plain
			// aside rather than an aria-describedby pairing (that pattern is for a control the
			// note describes; a <strong> has nothing for a screen reader to attach it to, and the
			// note is already read right after it).
			printf(
				'<p class="wpcpm-sponsor__field wpcpm-offer__kind-fixed"><span>%1$s</span> <strong>%2$s</strong> <span class="wpcpm-student__note">%3$s</span></p>',
				esc_html__( 'Kind', 'wpcredits-program-manager' ),
				esc_html( self::KIND_CODES === $offer['kind'] ? __( 'A pool of one-time codes', 'wpcredits-program-manager' ) : __( 'One code or link for everyone', 'wpcredits-program-manager' ) ),
				esc_html__( 'Fixed once the offer holds codes or claims.', 'wpcredits-program-manager' )
			);
		} else {
			// Two radios rather than a select: both kinds are read at once, and forms.js shows the
			// box that belongs to the one chosen (polish of 1.94.1).
			printf(
				'<fieldset class="wpcpm-sponsor__choices wpcpm-offer__kind"><legend>%1$s</legend><label><input type="radio" name="wpcpm_kind" value="codes"%2$s /> %3$s</label><label><input type="radio" name="wpcpm_kind" value="shared"%4$s /> %5$s</label></fieldset>',
				esc_html__( 'Kind', 'wpcredits-program-manager' ),
				checked( self::KIND_CODES, $offer['kind'], false ),
				esc_html__( 'A pool of one-time codes, one per person', 'wpcredits-program-manager' ),
				checked( self::KIND_SHARED, $offer['kind'], false ),
				esc_html__( 'One code or link everyone uses', 'wpcredits-program-manager' )
			);
		}

		// Each kind's own box, in a wrapper forms.js shows for that kind and hides for the other.
		// With JavaScript off both stay visible, and the handler reads only the box that belongs
		// to the kind posted, so a filled-in wrong box is ignored rather than stored.
		if ( $is_new ) {
			echo '<div class="wpcpm-offer__for" data-wpcpm-shows-for="codes">';
			self::render_codes_box( 'new', __( 'The codes', 'wpcredits-program-manager' ) );
			echo '</div>';
		}

		if ( $is_new || self::KIND_SHARED === $offer['kind'] ) {
			echo '<div class="wpcpm-offer__for" data-wpcpm-shows-for="shared">';
			$field( 'shared', __( 'The shared code or link', 'wpcredits-program-manager' ), sprintf( '<input type="text" id="wpcpm-offer-%1$s-shared" name="wpcpm_shared" value="%2$s" maxlength="%3$d" />', esc_attr( $id ), esc_attr( $is_new ? '' : WPCPM_Sponsor_Codes::shared( $offer['id'] ) ), (int) WPCPM_Sponsor_Codes::LINE_MAX ) );
			echo '</div>';
		}

		echo '<fieldset class="wpcpm-sponsor__choices"><legend>' . esc_html__( 'Also open to', 'wpcredits-program-manager' ) . '</legend>';
		printf( '<label><input type="checkbox" name="wpcpm_audience[]" value="mentors"%s /> %s</label>', checked( in_array( 'mentors', $offer['audience'], true ), true, false ), esc_html__( 'Mentors', 'wpcredits-program-manager' ) );
		printf( '<label><input type="checkbox" name="wpcpm_audience[]" value="managers"%s /> %s</label>', checked( in_array( 'managers', $offer['audience'], true ), true, false ), esc_html__( 'The program team', 'wpcredits-program-manager' ) );
		echo '<span class="wpcpm-student__note">' . esc_html__( 'Current students are always in.', 'wpcredits-program-manager' ) . '</span></fieldset>';

		$field( 'low', __( 'Warn me when fewer than this many codes are left', 'wpcredits-program-manager' ), sprintf( '<input type="number" id="wpcpm-offer-%1$s-low" name="wpcpm_low" value="%2$s" min="1" max="1000" />', esc_attr( $id ), $input( 'low', (string) (int) $offer['low'] ) ) );
		$field( 'expires', __( 'Last day (optional)', 'wpcredits-program-manager' ), sprintf( '<input type="date" id="wpcpm-offer-%1$s-expires" name="wpcpm_expires" value="%2$s" />', esc_attr( $id ), $input( 'expires', $offer['expires'] ) ) );
	}

	/**
	 * The offer as text: what a reader of an open panel sees before deciding to edit (1.97.4).
	 * Every value is the sponsor's own and is escaped; the instructions keep their line breaks.
	 *
	 * @param array $offer The offer.
	 */
	private static function render_details( array $offer ) {
		$none  = '<span class="wpcpm-student__note">' . esc_html__( 'Not written yet.', 'wpcredits-program-manager' ) . '</span>';
		$rows  = array();
		$plain = static function ( $value ) use ( $none ) {
			return '' !== trim( (string) $value ) ? nl2br( esc_html( (string) $value ) ) : $none;
		};

		$rows[] = array( __( 'What you get', 'wpcredits-program-manager' ), $plain( $offer['text'] ) );
		$rows[] = array( __( 'How to redeem it', 'wpcredits-program-manager' ), $plain( $offer['instructions'] ) );

		if ( '' !== trim( (string) $offer['url'] ) ) {
			$rows[] = array( __( 'Link', 'wpcredits-program-manager' ), sprintf( '<a href="%1$s">%2$s</a>', esc_url( (string) $offer['url'] ), esc_html( (string) $offer['url'] ) ) );
		}

		$rows[] = array( __( 'Kind', 'wpcredits-program-manager' ), esc_html( self::KIND_CODES === $offer['kind'] ? __( 'A pool of one-time codes, one per person', 'wpcredits-program-manager' ) : __( 'One code or link everyone uses', 'wpcredits-program-manager' ) ) );

		if ( self::KIND_SHARED === $offer['kind'] ) {
			$shared = (string) WPCPM_Sponsor_Codes::shared( $offer['id'] );
			$rows[] = array( __( 'The shared code or link', 'wpcredits-program-manager' ), '' !== $shared ? '<code>' . esc_html( $shared ) . '</code>' : $none );
		}

		$open = array( __( 'Current students', 'wpcredits-program-manager' ) );

		if ( in_array( 'mentors', (array) $offer['audience'], true ) ) {
			$open[] = __( 'mentors', 'wpcredits-program-manager' );
		}

		if ( in_array( 'managers', (array) $offer['audience'], true ) ) {
			$open[] = __( 'the program team', 'wpcredits-program-manager' );
		}

		$rows[] = array( __( 'Open to', 'wpcredits-program-manager' ), esc_html( implode( ', ', $open ) ) );

		if ( self::KIND_CODES === $offer['kind'] ) {
			/* translators: %d: the number of codes. */
			$rows[] = array( __( 'Warns you at', 'wpcredits-program-manager' ), esc_html( sprintf( _n( '%d code left', '%d codes left', (int) $offer['low'], 'wpcredits-program-manager' ), (int) $offer['low'] ) ) );
		}

		$rows[] = array( __( 'Last day', 'wpcredits-program-manager' ), '' !== (string) $offer['expires'] ? esc_html( (string) $offer['expires'] ) : esc_html__( 'None', 'wpcredits-program-manager' ) );

		echo '<dl class="wpcpm-offer__details">';

		foreach ( $rows as $row ) {
			printf( '<div class="wpcpm-offer__detail"><dt>%1$s</dt><dd>%2$s</dd></div>', esc_html( $row[0] ), $row[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each value is escaped where it is built above.
		}

		echo '</dl>';
	}

	/**
	 * A change of the offer's words held for review: whether it waits or was returned, with the
	 * Administrator's note, escaped, as a returned post shows its note; and the press that discards
	 * it, which takes effect at once. Nothing for an offer with no change held.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor key.
	 */
	private static function render_changes( array $offer, $record ) {
		if ( empty( $offer['proposed'] ) ) {
			return;
		}

		echo '<div class="wpcpm-offer__changes">';

		if ( $offer['returned'] > 0 ) {
			printf( '<p class="wpcpm-student__note">%s</p>', esc_html__( 'An Administrator returned your changes with a note. Students and mentors still see the offer as it reads above.', 'wpcredits-program-manager' ) );
			printf( '<p class="wpcpm-offer__note">%s</p>', esc_html( $offer['note'] ) );
		} else {
			printf( '<p class="wpcpm-student__note">%s</p>', esc_html__( 'Your changes wait for an Administrator\'s review. Until they are approved, students and mentors see the offer as it reads above.', 'wpcredits-program-manager' ) );
		}

		printf(
			'<form method="post" action="%1$s" class="wpcpm-inline-form wpcpm-offer__discard-form" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-confirm="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Discarding', 'wpcredits-program-manager' ),
			esc_attr__( 'Discard your changes? The offer stays as it reads above.', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_DISCARD . '_' . $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_DISCARD ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		printf( '<p class="wpcpm-offer__actions"><button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button></p>', esc_html__( 'Discard the changes', 'wpcredits-program-manager' ) );
		echo '</form></div>';
	}

	/**
	 * The state block: a title, one sentence on what the state means today, and the moves. A draft
	 * a member sends for review says so, and one the program returned shows the note, escaped.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor key.
	 * @param bool   $staff  Whether the program's own staff are looking.
	 */
	private static function render_state_block( array $offer, $record, $staff = false ) {
		$note = '';

		switch ( $offer['state'] ) {
			case self::STATE_REVIEW:
				$sentence = __( 'Waiting for review: nobody but you sees it until an Administrator approves it.', 'wpcredits-program-manager' );
				break;
			case self::STATE_LIVE:
				$sentence = self::is_live( $offer )
					? __( 'Live: shown on the site.', 'wpcredits-program-manager' )
					: __( 'Past its last day: shown to nobody. Change the last day to bring it back.', 'wpcredits-program-manager' );
				break;
			case self::STATE_PAUSED:
				$sentence = __( 'Paused: hidden until you resume it. Its codes stay.', 'wpcredits-program-manager' );
				break;
			case self::STATE_ENDED:
				$sentence = __( 'Ended: nothing more can be claimed, and it cannot be resumed.', 'wpcredits-program-manager' );
				break;
			case self::STATE_DORMANT:
				// Nothing about the tiers reaches a sponsor before launch, a dormant offer's own
				// reason included: a switch turned on and off again before launch must not leave
				// a sponsor reading their tier's name.
				$sentence = WPCPM_Sponsor_Tiers::live()
					? self::dormant_sentence()
					: __( 'Dormant: held back by the program. You can end it.', 'wpcredits-program-manager' );
				break;
			default:
				if ( $offer['returned'] > 0 && empty( $offer['proposed'] ) ) {
					$sentence = __( 'Returned by the program with a note: change the offer, then send it for review again.', 'wpcredits-program-manager' );
					$note     = (string) $offer['note'];
				} elseif ( $staff || $offer['approved'] ) {
					$sentence = __( 'A draft: nobody but you sees it. Switch it on when it is ready.', 'wpcredits-program-manager' );
				} else {
					$sentence = __( 'A draft: nobody but you sees it. Send it for review when it is ready: students and mentors see it once an Administrator approves it.', 'wpcredits-program-manager' );
				}
		}

		echo '<div class="wpcpm-offer__moves">';
		printf( '<h5 class="wpcpm-offer__subtitle">%s</h5>', esc_html__( 'State', 'wpcredits-program-manager' ) );
		printf( '<p class="wpcpm-student__note wpcpm-offer__state-note">%s</p>', esc_html( $sentence ) );

		if ( '' !== $note ) {
			printf( '<p class="wpcpm-offer__note">%s</p>', esc_html( $note ) );
		}

		self::render_state_form( $offer, $record, $staff );
		echo '</div>';
	}

	/**
	 * The edit form for one offer.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor key.
	 * @param bool   $fixed  Whether the kind may no longer change.
	 * @param array  $kept   What a refused save of this form kept (`WPCPM_Typed_Text::kept()`).
	 */
	private static function render_edit_form( array $offer, $record, $fixed, array $kept = array() ) {
		printf(
			'<form method="post" action="%1$s" id="wpcpm-offer-form-%3$d" class="wpcpm-sponsor__form wpcpm-offer__form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' ),
			(int) $offer['id']
		);
		wp_nonce_field( self::ACTION_SAVE . '_' . $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		self::render_fields( $offer, $record, $fixed, $kept );
		printf( '<p class="wpcpm-offer__actions"><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Save offer', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * The state moves as one row of buttons, inside the state form (1.97.4). An offer with no
	 * move left (ended) prints nothing here and no state form either.
	 *
	 * @param array $offer The offer.
	 * @param bool  $staff Whether the program's own staff are looking, who switch a draft on where a
	 *                     member sends it for review.
	 */
	private static function render_state_buttons( array $offer, $staff = false ) {
		$moves = self::transitions()[ $offer['state'] ];

		if ( empty( $moves ) ) {
			return;
		}

		if ( self::STATE_PAUSED === $offer['state'] ) {
			$on = __( 'Resume', 'wpcredits-program-manager' );
		} elseif ( $staff || $offer['approved'] ) {
			$on = __( 'Switch on', 'wpcredits-program-manager' );
		} else {
			$on = __( 'Send for review', 'wpcredits-program-manager' );
		}

		$labels = array(
			self::STATE_LIVE   => $on,
			self::STATE_DRAFT  => __( 'Take it back', 'wpcredits-program-manager' ),
			self::STATE_PAUSED => __( 'Pause', 'wpcredits-program-manager' ),
			self::STATE_ENDED  => __( 'End this offer', 'wpcredits-program-manager' ),
		);

		foreach ( $moves as $state ) {
			printf(
				'<button type="submit" class="wpcpm-button%1$s" name="wpcpm_state" value="%2$s"%3$s>%4$s</button>',
				self::STATE_LIVE === $state ? '' : ' wpcpm-button--secondary',
				esc_attr( $state ),
				// forms.js's guard yields to a prevented submit (1.92.0), so a No is not left "Switching".
				self::STATE_ENDED === $state ? ' data-wpcpm-confirm="' . esc_attr__( 'End this offer for good? Codes already claimed stay with the people who hold them.', 'wpcredits-program-manager' ) . '"' : '',
				esc_html( $labels[ $state ] )
			);
		}
	}

	/**
	 * One form, one button per move the machine allows from here.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor key.
	 * @param bool   $staff  Whether the program's own staff are looking.
	 */
	private static function render_state_form( array $offer, $record, $staff = false ) {
		$moves = self::transitions()[ $offer['state'] ];

		if ( empty( $moves ) ) {
			return;
		}

		printf(
			'<form method="post" action="%1$s" id="wpcpm-offer-state-%3$d" class="wpcpm-inline-form wpcpm-offer__state-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Switching', 'wpcredits-program-manager' ),
			(int) $offer['id']
		);
		wp_nonce_field( self::ACTION_STATE . '_' . $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_STATE ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		echo '<p class="wpcpm-offer__actions">';
		self::render_state_buttons( $offer, $staff );
		echo '</p></form>';
	}

	/**
	 * The two ways codes come in, side by side: a box to paste into and a file to upload. One
	 * routine for the new-offer form and the pool's own form, so the two never drift apart.
	 *
	 * @param string $id    'new', or the offer's ID, for the control IDs.
	 * @param string $label The paste box's label.
	 */
	private static function render_codes_box( $id, $label ) {
		printf(
			'<p class="wpcpm-sponsor__field"><label for="wpcpm-offer-%1$s-codes">%2$s</label><textarea id="wpcpm-offer-%1$s-codes" name="wpcpm_codes" rows="6" placeholder="%3$s" aria-describedby="wpcpm-offer-%1$s-codes-hint"></textarea><span class="wpcpm-student__note" id="wpcpm-offer-%1$s-codes-hint">%4$s</span></p>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr__( 'Paste codes here, one per line', 'wpcredits-program-manager' ),
			esc_html(
				sprintf(
					/* translators: 1: the longest line allowed, 2: the most codes an offer holds. */
					__( 'One code per line, or a CSV with the code in the first column and no header row. A code can be a whole checkout link. Up to %1$d characters a line and %2$d codes an offer.', 'wpcredits-program-manager' ),
					WPCPM_Sponsor_Codes::LINE_MAX,
					WPCPM_Sponsor_Codes::CODES_MAX
				)
				// Said here because the refusal for it arrives after the paste, and the counts on
				// the card read 0 available, which makes the rule look like a fault (FOFFR-4).
				. ' ' . __( 'A voided code cannot be added to the same offer again.', 'wpcredits-program-manager' )
			)
		);
		printf(
			'<p class="wpcpm-sponsor__field wpcpm-offer__file"><label for="wpcpm-offer-%1$s-codes-file">%2$s</label><input type="file" id="wpcpm-offer-%1$s-codes-file" name="wpcpm_codes_file" accept=".txt,.csv,text/plain,text/csv" aria-describedby="wpcpm-offer-%1$s-codes-file-hint" /><span class="wpcpm-student__note" id="wpcpm-offer-%1$s-codes-file-hint">%3$s</span></p>',
			esc_attr( $id ),
			esc_html__( 'Or upload a .txt or .csv file', 'wpcredits-program-manager' ),
			esc_html__( 'A .txt or .csv file, up to a megabyte. Codes are stored encrypted and shown only to the person who claims one.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The paste box, the file field and the void button, for a pool.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor key.
	 * @param array  $counts The pool's counts.
	 */
	private static function render_codes_forms( array $offer, $record, array $counts ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form wpcpm-offer__codes-form" enctype="multipart/form-data" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Adding', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_CODES_ADD . '_' . $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CODES_ADD ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		self::render_codes_box( (string) $offer['id'], __( 'Add codes', 'wpcredits-program-manager' ) );
		// Add codes and Void unclaimed codes on one line (owner, 1.97.3): the void button belongs to
		// the void form printed after this one, through its `form` attribute, as the state moves do.
		printf( '<p class="wpcpm-offer__actions"><button type="submit" class="wpcpm-button">%s</button>', esc_html__( 'Add codes', 'wpcredits-program-manager' ) );

		if ( $counts['available'] > 0 ) {
			printf( '<button type="submit" form="wpcpm-offer-void-%1$d" class="wpcpm-button wpcpm-button--secondary">%2$s</button>', (int) $offer['id'], esc_html__( 'Void unclaimed codes', 'wpcredits-program-manager' ) );
		}

		echo '</p></form>';

		if ( $counts['available'] > 0 ) {
			printf(
				'<form method="post" action="%1$s" id="wpcpm-offer-void-%4$d" class="wpcpm-inline-form wpcpm-offer__void-form" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-confirm="%3$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Voiding', 'wpcredits-program-manager' ),
				esc_attr__( 'Void every code nobody has claimed yet? They cannot be brought back.', 'wpcredits-program-manager' ),
				(int) $offer['id']
			);
			wp_nonce_field( self::ACTION_CODES_VOID . '_' . $offer['id'] );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CODES_VOID ) );
			printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
			printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
			echo '</form>';
		}
	}

	/**
	 * The form for a new offer, under the sentence that says a member's offer and changes wait for an
	 * Administrator's review, or, for the program's own staff, that theirs need none. The review is not
	 * a tier's, so it is said whatever the tiers switch says, and names no tier.
	 *
	 * @param string $record Sponsor key.
	 * @param bool   $staff  Whether the program's own staff are looking.
	 */
	private static function render_new_form( $record, $staff = false ) {
		echo '<h4 class="wpcpm-sponsor__subheading">' . esc_html__( 'New offer', 'wpcredits-program-manager' ) . '</h4>';

		printf(
			'<p class="wpcpm-student__note">%s</p>',
			$staff
				? esc_html__( 'As an Administrator, what you create or change here needs no review.', 'wpcredits-program-manager' )
				: esc_html__( 'A new offer, and every change to what students see of a live one, waits for an Administrator\'s review before students and mentors see it.', 'wpcredits-program-manager' )
		);

		// No tier caps live offers since 1.123.18: the partner page offers products at any level,
		// separately from the levels, so while the tiers are announced the note says that and names
		// no tier.
		if ( WPCPM_Sponsor_Tiers::live() ) {
			printf( '<p class="wpcpm-student__note">%s</p>', esc_html__( 'Offers are separate from the tiers: your company may have any number of live offers, whatever its tier.', 'wpcredits-program-manager' ) );
		}

		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form wpcpm-offer__form wpcpm-offer__form--new" enctype="multipart/form-data" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Creating', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE . '_new' );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		echo '<input type="hidden" name="wpcpm_offer" value="0" />';
		self::render_fields( null, $record, false, WPCPM_Typed_Text::kept( self::typed_form( $record, 0 ) ) );
		printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Create offer', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * Flash and go back to the dashboard, through the dashboard's own method by array callable
	 * (bin/check-references.php would otherwise flag a call to a method declared in another
	 * task of the same release).
	 *
	 * @param string $status A key of messages(), or 'refused'.
	 * @param string $record The sponsor, for the manager switcher; '' to land on the page as is.
	 * @param string $detail A sentence printed after the status's own, or ''.
	 */
	private static function leave( $status, $record, $detail = '' ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD, $record, $detail );
		exit;
	}

	/**
	 * Uninstall: every offer and its pool. The locks and the claims meta are
	 * WPCPM_Sponsor_Claims::delete_all()'s.
	 */
	public static function delete_all() {
		foreach ( self::all() as $offer ) {
			WPCPM_Sponsor_Codes::delete( $offer['id'] );
			wp_delete_post( $offer['id'], true );
		}
	}
}
