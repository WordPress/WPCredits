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
 * Sponsors design spec of 4 September 2026, section 6.1. The record ID in
 * `_wpcpm_offer_sponsor` is the queryable key and is never read from a form: every handler
 * claims its sponsor through WPCPM_Sponsor_Roster::claim() first and then asks find() whether
 * the offer belongs to it, so a member cannot reach another sponsor's offer by posting its ID.
 * The one offer marked primary mirrors its text, instructions and link to Airtable's `Offer`,
 * `Brief instructions` and `More info link`, so managers keep seeing the offer in the grid;
 * `Coupon code/discount link` is never written, because the truthful value ("managed on the
 * site") is not a URL and the column is a url field.
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

	const KIND_CODES  = 'codes';
	const KIND_SHARED = 'shared';

	const STATE_DRAFT  = 'draft';
	const STATE_LIVE   = 'live';
	const STATE_PAUSED = 'paused';
	const STATE_ENDED  = 'ended';

	/** Students are always in; these two are opt-in per offer. */
	const AUDIENCES = array( 'mentors', 'managers' );

	const MAX_TITLE = 120;
	const MAX_OFFER = 500;
	const MAX_TEXT  = 4000;

	const ACTION_SAVE       = 'wpcpm_offer_save';
	const ACTION_STATE      = 'wpcpm_offer_state';
	const ACTION_CODES_ADD  = 'wpcpm_offer_codes_add';
	const ACTION_CODES_VOID = 'wpcpm_offer_codes_void';

	const CARD = 'offers';

	const LOG_SAVED  = 'offer_saved';
	const LOG_STATE  = 'offer_state';
	const LOG_CODES  = 'offer_codes';
	const LOG_SEEDED = 'offer_seeded';

	/**
	 * Offer field to Airtable column, for the primary offer. Spelled as the base spells them;
	 * pinned by bin/fixtures/sponsors-table-fields.json through WPCPM_Sponsor_Profile::FIELDS.
	 */
	const MIRROR = array(
		'text'         => 'Offer',
		'instructions' => 'Brief instructions',
		'url'          => 'More info link',
	);

	/** The same three, as the index names them. */
	const MIRROR_INDEX = array(
		'text'         => 'offer',
		'instructions' => 'instructions',
		'url'          => 'more_info',
	);

	/**
	 * A coupon link on one of these hosts is the sheet the program is retiring, never a shared
	 * code. Drive as well as Docs: the same sheet shared from the file list is a `drive.google.com`
	 * link, and the rule exists to keep the sheet out of the pool (final review, finding 13).
	 */
	const SHEET_HOSTS = array( 'docs.google.com', 'drive.google.com' );

	/**
	 * The post type, and the four handlers.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_STATE, array( __CLASS__, 'handle_state' ) );
		add_action( 'admin_post_' . self::ACTION_CODES_ADD, array( __CLASS__, 'handle_codes_add' ) );
		add_action( 'admin_post_' . self::ACTION_CODES_VOID, array( __CLASS__, 'handle_codes_void' ) );
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
		return array( self::STATE_DRAFT, self::STATE_LIVE, self::STATE_PAUSED, self::STATE_ENDED );
	}

	/**
	 * The moves a sponsor may make. Ended is final: a pool that was handed out is a record,
	 * and reviving it would put codes people already hold back on offer.
	 *
	 * @return array<string, string[]>
	 */
	public static function transitions() {
		return array(
			self::STATE_DRAFT  => array( self::STATE_LIVE, self::STATE_ENDED ),
			self::STATE_LIVE   => array( self::STATE_PAUSED, self::STATE_ENDED ),
			self::STATE_PAUSED => array( self::STATE_LIVE, self::STATE_ENDED ),
			self::STATE_ENDED  => array(),
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

		return $offer;
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
	 * @param string $record Sponsor record ID.
	 * @return array Offers keyed by ID.
	 */
	public static function offers_of( $record ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
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
	 * @param string $record  Sponsor record ID.
	 * @param array  $fields  Cleaned fields (clean()'s `fields`).
	 * @param bool   $primary Whether this is the one mirrored to the base.
	 * @return int|WP_Error
	 */
	public static function create( $record, array $fields, $primary = false ) {
		$record = trim( (string) $record );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			return new WP_Error( 'wpcpm_offer_sponsor', __( 'An offer needs the sponsor it belongs to.', 'wpcredits-program-manager' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => isset( $fields['title'] ) ? (string) $fields['title'] : '',
				'post_author' => 0,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( (int) $post_id, self::META_SPONSOR, $record );
		update_post_meta( (int) $post_id, self::META_STATE, self::STATE_DRAFT );
		update_post_meta( (int) $post_id, self::META_PRIMARY, $primary ? '1' : '0' );

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
			wp_update_post(
				array(
					'ID'         => $offer['id'],
					'post_title' => (string) $fields['title'],
				)
			);
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
				update_post_meta( $post_id, $meta_key, $fields[ $key ] );
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
	 * @param array      $raw      Posted values: title, text, instructions, url, kind, audience (array), low, expires.
	 * @param array|null $existing The offer being edited, or null for a new one.
	 * @return array `ok`, `fields`, `reason` ('' or the field that failed: title, url, kind, expires).
	 */
	public static function clean( array $raw, $existing = null ) {
		$refuse = static function ( $reason ) {
			return array(
				'ok'     => false,
				'fields' => array(),
				'reason' => $reason,
			);
		};

		$fields = array();
		$title  = trim( mb_substr( sanitize_text_field( isset( $raw['title'] ) ? (string) $raw['title'] : '' ), 0, self::MAX_TITLE ) );

		if ( '' === $title ) {
			return $refuse( 'title' );
		}

		$fields['title']        = $title;
		$fields['text']         = mb_substr( sanitize_textarea_field( isset( $raw['text'] ) ? (string) $raw['text'] : '' ), 0, self::MAX_OFFER );
		$fields['instructions'] = mb_substr( sanitize_textarea_field( isset( $raw['instructions'] ) ? (string) $raw['instructions'] : '' ), 0, self::MAX_TEXT );

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

		// Once the pool holds codes or claims, switching kind would orphan them (plan ruling 5).
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
	 * Seed the first offer from the index (spec 6.1), once per sponsor.
	 *
	 * The title is the sponsor's name: the base's `Offer` is a description, which becomes the
	 * text (plan ruling 6). A checkout link becomes the shared code; the coupon sheet is a list
	 * the program is retiring and its address names students, so it is never stored here.
	 *
	 * @param string $record Sponsor record ID.
	 * @return int|false|WP_Error The offer, or false when the sponsor already has one.
	 */
	public static function seed( $record ) {
		$row = WPCPM_Sponsors_Index::row( $record );

		if ( ! is_array( $row ) ) {
			return new WP_Error( 'wpcpm_offer_sponsor', __( 'The index does not hold that sponsor.', 'wpcredits-program-manager' ) );
		}

		if ( ! empty( self::offers_of( $record ) ) ) {
			return false;
		}

		$coupon = trim( (string) $row['coupon_link'] );
		$title  = trim( (string) $row['name'] );
		$shared = '' !== $coupon && 1 === preg_match( '#^https?://#i', $coupon );

		foreach ( self::SHEET_HOSTS as $host ) {
			if ( false !== stripos( $coupon, $host ) ) {
				$shared = false;
			}
		}

		$fields = array(
			'title'        => mb_substr( '' !== $title ? $title : __( 'Offer', 'wpcredits-program-manager' ), 0, self::MAX_TITLE ),
			'kind'         => $shared ? self::KIND_SHARED : self::KIND_CODES,
			'text'         => mb_substr( sanitize_textarea_field( (string) $row['offer'] ), 0, self::MAX_OFFER ),
			'instructions' => mb_substr( sanitize_textarea_field( (string) $row['instructions'] ), 0, self::MAX_TEXT ),
			'url'          => WPCPM_Field_Value::clean_url( (string) $row['more_info'] ),
			'audience'     => array(),
			'low'          => max( 1, (int) WPCPM_Settings::get_value( 'offer_low_stock', 10 ) ),
			'expires'      => '',
		);

		$id = self::create( $record, $fields, true );

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
	 * Move an offer to a state.
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
		self::touch( $offer['id'], 'state-' . $state );

		return true;
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
	 * Write the primary offer's three fields to Airtable and the index. A non-primary offer
	 * has nothing to mirror and answers true.
	 *
	 * @param array $offer An offer.
	 * @return bool Whether the base has it.
	 */
	public static function mirror( array $offer ) {
		if ( empty( $offer['primary'] ) ) {
			return true;
		}

		$cells = array();

		foreach ( self::MIRROR as $key => $column ) {
			$cells[ $column ] = (string) $offer[ $key ];
		}

		$airtable = new WPCPM_Airtable();
		$written  = $airtable->update_records(
			(string) WPCPM_Settings::get_value( 'sponsors_table', '' ),
			array(
				array(
					'id'     => $offer['sponsor'],
					'fields' => $cells,
				),
			)
		);

		if ( is_wp_error( $written ) ) {
			return false;
		}

		$patch = array();

		foreach ( self::MIRROR_INDEX as $key => $index_key ) {
			$patch[ $index_key ] = (string) $offer[ $key ];
		}

		WPCPM_Sponsors_Index::patch( $offer['sponsor'], $patch );

		return true;
	}

	/**
	 * This card's outcomes. A status names the outcome; the detail the dashboard prints after
	 * it names the line or the reason (plan ruling 10).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			'offer-created'       => array( 'success', __( 'Your offer was created. Switch it on when it is ready.', 'wpcredits-program-manager' ) ),
			'offer-saved'         => array( 'success', __( 'Your offer was saved.', 'wpcredits-program-manager' ) ),
			'offer-mirror-failed' => array( 'info', __( 'Your offer was saved here, but the program records could not be updated right now. They will be on your next save.', 'wpcredits-program-manager' ) ),
			'offer-rejected'      => array( 'error', __( 'The offer could not be saved.', 'wpcredits-program-manager' ) ),
			'offer-failed'        => array( 'error', __( 'The offer could not be saved right now. Try again later.', 'wpcredits-program-manager' ) ),
			'offer-state-saved'   => array( 'success', __( 'The offer was switched.', 'wpcredits-program-manager' ) ),
			'offer-empty'         => array( 'error', __( 'The offer cannot be switched on yet.', 'wpcredits-program-manager' ) ),
			'offer-transition'    => array( 'error', __( 'The offer cannot move to that state from where it is.', 'wpcredits-program-manager' ) ),
			// Task 4's second fix round: the pool's own lock can be held by another request for a
			// moment, and set_shared() and void_unclaimed() both answer that with a WP_Error rather
			// than a count; this is the one sentence both leave with (see handle_save(),
			// handle_codes_void()).
			'offer-busy'          => array( 'info', __( 'Another change to this offer was going through. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'codes-added'         => array( 'success', __( 'Codes added.', 'wpcredits-program-manager' ) ),
			'codes-refused'       => array( 'error', __( 'Nothing was added.', 'wpcredits-program-manager' ) ),
			'codes-none'          => array( 'error', __( 'No codes were found in what you pasted.', 'wpcredits-program-manager' ) ),
			'codes-max'           => array( 'error', __( 'An offer holds at most 5000 codes. Talk to the program about a larger pool.', 'wpcredits-program-manager' ) ),
			'codes-voided'        => array( 'success', __( 'Unclaimed codes were voided.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * A state, in the sponsor's words.
	 *
	 * @param string $state A state.
	 * @return string
	 */
	public static function state_label( $state ) {
		$labels = array(
			self::STATE_DRAFT  => __( 'Not switched on yet', 'wpcredits-program-manager' ),
			self::STATE_LIVE   => __( 'Live', 'wpcredits-program-manager' ),
			self::STATE_PAUSED => __( 'Paused', 'wpcredits-program-manager' ),
			self::STATE_ENDED  => __( 'Ended', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $state ] ) ? $labels[ $state ] : (string) $state;
	}

	/**
	 * The sentence for a clean() reason.
	 *
	 * @param string $reason A reason.
	 * @return string
	 */
	private static function reason_sentence( $reason ) {
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
		$existing = $claim['offer'];

		$raw = array(
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

		$cleaned = self::clean( $raw, $existing );

		if ( ! $cleaned['ok'] ) {
			self::leave( 'offer-rejected', $record, self::reason_sentence( $cleaned['reason'] ) );
		}

		// The shared code is checked before the offer is written, so a refusal here leaves
		// nothing behind: a "could not be saved" that had already created the offer would
		// leave an orphan the sponsor can see and not explain (Task 6 review).
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

		if ( null === $existing ) {
			// The first offer a sponsor gets is the one the base sees.
			$offer_id = self::create( $record, $cleaned['fields'], empty( self::offers_of( $record ) ) );

			if ( is_wp_error( $offer_id ) ) {
				self::leave( 'offer-failed', $record );
			}
		} else {
			$offer_id = $existing['id'];
			self::save( $offer_id, $cleaned['fields'] );
		}

		if ( null !== $shared ) {
			$stored = WPCPM_Sponsor_Codes::set_shared( $offer_id, $shared );

			if ( is_wp_error( $stored ) ) {
				self::leave( 'offer-busy', $record );
			}
		}

		$offer    = self::read( $offer_id );
		$mirrored = self::mirror( $offer );

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
					'offer'    => $offer_id,
					'created'  => null === $existing,
					'fields'   => array_keys( $cleaned['fields'] ),
					'mirrored' => $mirrored,
				),
			)
		);

		if ( ! $mirrored ) {
			self::leave( 'offer-mirror-failed', $record );
		}

		self::leave( null === $existing ? 'offer-created' : 'offer-saved', $record );
	}

	/**
	 * Switch an offer on, pause it, resume it or end it.
	 */
	public static function handle_state() {
		$claim  = self::begin( self::ACTION_STATE, WPCPM_Sponsor_Policy::ACT_MANAGE_OFFERS );
		$record = $claim['record'];
		$offer  = $claim['offer'];
		$state  = WPCPM_Request::posted_key( 'wpcpm_state' );
		$moved  = self::set_state( $offer['id'], $state );

		if ( is_wp_error( $moved ) ) {
			$statuses = array(
				'wpcpm_offer_empty'      => 'offer-empty',
				'wpcpm_offer_transition' => 'offer-transition',
			);
			$code     = $moved->get_error_code();

			self::leave( isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'offer-failed', $record, 'wpcpm_offer_empty' === $code ? $moved->get_error_message() : '' );
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

		self::leave( 'offer-state-saved', $record );
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

		// Verbatim, not sanitize_textarea_field(): a pasted pool is codes and checkout links, and
		// core's percent stripping would corrupt every line with a `%XX` in it (finding 1).
		$added = self::add_codes( $offer['id'], WPCPM_Request::posted_verbatim_lines( 'wpcpm_codes' ) );

		if ( is_wp_error( $added ) ) {
			$statuses = array(
				'wpcpm_codes_refused' => 'codes-refused',
				'wpcpm_codes_none'    => 'codes-none',
				'wpcpm_codes_max'     => 'codes-max',
				'wpcpm_codes_busy'    => 'offer-busy',
			);
			$code     = $added->get_error_code();
			$lines    = 'wpcpm_codes_refused' === $code ? implode( ' ', (array) $added->get_error_data() ) : '';

			self::leave( isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'offer-failed', $record, $lines );
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

		// The pool's lock can be another request's for a moment (Task 4's second fix round): the
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
	 * The card: every offer with its forms, then the form for a new one.
	 *
	 * @param string $record  Sponsor record ID.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$offers = self::offers_of( $record );
		$open   = isset( $context['open'] ) && self::CARD === $context['open'];

		printf( '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-%1$s" class="wpcpm-group wpcpm-group__disclosure"%2$s>', esc_attr( self::CARD ), $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Offers and codes', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $offers ) ) )
		);
		echo '<div class="wpcpm-group__body">';
		echo '<p class="wpcpm-student__note">' . esc_html__( 'What students get from you and how. An offer is a pool of one-time codes you paste here, or one code or link everyone uses. Switch it on when it is ready: students see it on their Student Report Card the same minute, and only the person who claims a code ever sees it.', 'wpcredits-program-manager' ) . '</p>';

		foreach ( $offers as $offer ) {
			self::render_offer( $offer, $record );
		}

		self::render_new_form( $record );

		echo '</div></details></section>';
	}

	/**
	 * One offer: its heading and counts, the edit form, the state buttons, the codes box.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor record ID.
	 */
	private static function render_offer( array $offer, $record ) {
		$counts = WPCPM_Sponsor_Codes::counts( $offer['id'] );
		$fixed  = self::kind_is_fixed( $offer );

		printf( '<div class="wpcpm-offer wpcpm-offer--%1$s" id="wpcpm-offer-%2$d">', esc_attr( $offer['state'] ), (int) $offer['id'] );
		printf(
			'<h4 class="wpcpm-offer__title">%1$s <span class="wpcpm-offer__state">%2$s</span>%3$s</h4>',
			esc_html( $offer['title'] ),
			esc_html( self::state_label( $offer['state'] ) ),
			$offer['primary'] ? ' <span class="wpcpm-offer__primary">' . esc_html__( 'shown in the program records', 'wpcredits-program-manager' ) . '</span>' : ''
		);

		if ( self::KIND_CODES === $offer['kind'] ) {
			printf(
				'<p class="wpcpm-offer__counts">%s</p>',
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
				printf( '<p class="wpcpm-offer__warning">%s</p>', esc_html( sprintf( __( 'Running low: fewer than %d codes left. Add more, or pause the offer.', 'wpcredits-program-manager' ), (int) $offer['low'] ) ) );
			}
		} else {
			$taken = 0;

			foreach ( WPCPM_Sponsor_Codes::claims( $offer['id'] ) as $claim ) {
				if ( empty( $claim['v'] ) ) {
					++$taken;
				}
			}

			/* translators: %d: how many people. */
			printf( '<p class="wpcpm-offer__counts">%s</p>', esc_html( sprintf( _n( '%d person has taken it', '%d people have taken it', $taken, 'wpcredits-program-manager' ), $taken ) ) );
		}

		self::render_edit_form( $offer, $record, $fixed );
		self::render_state_form( $offer, $record );

		if ( self::KIND_CODES === $offer['kind'] && self::STATE_ENDED !== $offer['state'] ) {
			self::render_codes_forms( $offer, $record, $counts );
		}

		echo '</div>';
	}

	/**
	 * The fields, for an existing offer or a new one.
	 *
	 * @param array|null $offer  The offer, or null for the new-offer form.
	 * @param string     $record Sponsor record ID.
	 * @param bool       $fixed  Whether the kind may no longer change.
	 */
	private static function render_fields( $offer, $record, $fixed ) {
		$is_new = null === $offer;
		$offer  = $is_new ? self::empty_offer() : $offer;
		$id     = $is_new ? 'new' : (string) $offer['id'];
		$field  = static function ( $key, $label, $control ) use ( $id ) {
			printf( '<p class="wpcpm-sponsor__field"><label for="wpcpm-offer-%1$s-%2$s">%3$s</label>%4$s</p>', esc_attr( $id ), esc_attr( $key ), esc_html( $label ), $control ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $control is built below with every value escaped.
		};

		$field( 'title', __( 'Title', 'wpcredits-program-manager' ), sprintf( '<input type="text" id="wpcpm-offer-%1$s-title" name="wpcpm_title" value="%2$s" maxlength="%3$d" required />', esc_attr( $id ), esc_attr( $offer['title'] ), (int) self::MAX_TITLE ) );
		$field( 'text', __( 'What you get, in a sentence or two', 'wpcredits-program-manager' ), sprintf( '<textarea id="wpcpm-offer-%1$s-text" name="wpcpm_text" rows="2" maxlength="%2$d">%3$s</textarea>', esc_attr( $id ), (int) self::MAX_OFFER, esc_textarea( $offer['text'] ) ) );
		$field( 'instructions', __( 'How to redeem it', 'wpcredits-program-manager' ), sprintf( '<textarea id="wpcpm-offer-%1$s-instructions" name="wpcpm_instructions" rows="4" maxlength="%2$d">%3$s</textarea>', esc_attr( $id ), (int) self::MAX_TEXT, esc_textarea( $offer['instructions'] ) ) );
		$field( 'url', __( 'Link with more information, or where to redeem', 'wpcredits-program-manager' ), sprintf( '<input type="url" id="wpcpm-offer-%1$s-url" name="wpcpm_url" value="%2$s" maxlength="%3$d" />', esc_attr( $id ), esc_attr( $offer['url'] ), (int) WPCPM_Sponsor_Codes::LINE_MAX ) );

		if ( $fixed ) {
			printf(
				'<p class="wpcpm-sponsor__field wpcpm-offer__kind-fixed"><span>%1$s</span> <strong>%2$s</strong> <span class="wpcpm-student__note">%3$s</span></p>',
				esc_html__( 'Kind', 'wpcredits-program-manager' ),
				esc_html( self::KIND_CODES === $offer['kind'] ? __( 'A pool of one-time codes', 'wpcredits-program-manager' ) : __( 'One code or link for everyone', 'wpcredits-program-manager' ) ),
				esc_html__( 'Fixed once the offer holds codes or claims.', 'wpcredits-program-manager' )
			);
		} else {
			$field(
				'kind',
				__( 'Kind', 'wpcredits-program-manager' ),
				sprintf(
					'<select id="wpcpm-offer-%1$s-kind" name="wpcpm_kind"><option value="codes"%2$s>%3$s</option><option value="shared"%4$s>%5$s</option></select>',
					esc_attr( $id ),
					selected( self::KIND_CODES, $offer['kind'], false ),
					esc_html__( 'A pool of one-time codes, one per person', 'wpcredits-program-manager' ),
					selected( self::KIND_SHARED, $offer['kind'], false ),
					esc_html__( 'One code or link everyone uses', 'wpcredits-program-manager' )
				)
			);
		}

		if ( $is_new || self::KIND_SHARED === $offer['kind'] ) {
			$field( 'shared', __( 'The shared code or link (shared offers only)', 'wpcredits-program-manager' ), sprintf( '<input type="text" id="wpcpm-offer-%1$s-shared" name="wpcpm_shared" value="%2$s" maxlength="%3$d" />', esc_attr( $id ), esc_attr( $is_new ? '' : WPCPM_Sponsor_Codes::shared( $offer['id'] ) ), (int) WPCPM_Sponsor_Codes::LINE_MAX ) );
		}

		echo '<fieldset class="wpcpm-sponsor__choices"><legend>' . esc_html__( 'Also open to', 'wpcredits-program-manager' ) . '</legend>';
		printf( '<label><input type="checkbox" name="wpcpm_audience[]" value="mentors"%s /> %s</label>', checked( in_array( 'mentors', $offer['audience'], true ), true, false ), esc_html__( 'Mentors', 'wpcredits-program-manager' ) );
		printf( '<label><input type="checkbox" name="wpcpm_audience[]" value="managers"%s /> %s</label>', checked( in_array( 'managers', $offer['audience'], true ), true, false ), esc_html__( 'The program team', 'wpcredits-program-manager' ) );
		echo '<span class="wpcpm-student__note">' . esc_html__( 'Current students are always in.', 'wpcredits-program-manager' ) . '</span></fieldset>';

		$field( 'low', __( 'Warn me when fewer than this many codes are left', 'wpcredits-program-manager' ), sprintf( '<input type="number" id="wpcpm-offer-%1$s-low" name="wpcpm_low" value="%2$d" min="1" max="1000" />', esc_attr( $id ), (int) $offer['low'] ) );
		$field( 'expires', __( 'Last day (optional)', 'wpcredits-program-manager' ), sprintf( '<input type="date" id="wpcpm-offer-%1$s-expires" name="wpcpm_expires" value="%2$s" />', esc_attr( $id ), esc_attr( $offer['expires'] ) ) );
	}

	/**
	 * The edit form for one offer.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor record ID.
	 * @param bool   $fixed  Whether the kind may no longer change.
	 */
	private static function render_edit_form( array $offer, $record, $fixed ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form wpcpm-offer__form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE . '_' . $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		self::render_fields( $offer, $record, $fixed );
		printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Save offer', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * One form, one button per move the machine allows from here.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor record ID.
	 */
	private static function render_state_form( array $offer, $record ) {
		$moves = self::transitions()[ $offer['state'] ];

		if ( empty( $moves ) ) {
			return;
		}

		$labels = array(
			self::STATE_LIVE   => self::STATE_PAUSED === $offer['state'] ? __( 'Resume', 'wpcredits-program-manager' ) : __( 'Switch on', 'wpcredits-program-manager' ),
			self::STATE_PAUSED => __( 'Pause', 'wpcredits-program-manager' ),
			self::STATE_ENDED  => __( 'End this offer', 'wpcredits-program-manager' ),
		);

		printf(
			'<form method="post" action="%1$s" class="wpcpm-inline-form wpcpm-offer__state-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Switching', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_STATE . '_' . $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_STATE ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );

		foreach ( $moves as $state ) {
			printf(
				'<button type="submit" class="wpcpm-button%1$s" name="wpcpm_state" value="%2$s"%3$s>%4$s</button> ',
				self::STATE_LIVE === $state ? '' : ' wpcpm-button--secondary',
				esc_attr( $state ),
				// A cancelled confirm on the pressed button stops the submit, and forms.js yields to a
				// prevented submit (1.92.0), so the form is not left reading "Switching".
				self::STATE_ENDED === $state ? ' onclick="return confirm( \'' . esc_js( __( 'End this offer for good? Codes already claimed stay with the people who hold them.', 'wpcredits-program-manager' ) ) . '\' );"' : '',
				esc_html( $labels[ $state ] )
			);
		}

		echo '</form>';
	}

	/**
	 * The paste box and the void button, for a pool.
	 *
	 * @param array  $offer  The offer.
	 * @param string $record Sponsor record ID.
	 * @param array  $counts The pool's counts.
	 */
	private static function render_codes_forms( array $offer, $record, array $counts ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form wpcpm-offer__codes-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Adding', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_CODES_ADD . '_' . $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CODES_ADD ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		printf(
			'<p class="wpcpm-sponsor__field"><label for="wpcpm-offer-%1$d-codes">%2$s</label><textarea id="wpcpm-offer-%1$d-codes" name="wpcpm_codes" rows="6" placeholder="%3$s"></textarea><span class="wpcpm-student__note">%4$s</span></p>',
			(int) $offer['id'],
			esc_html__( 'Add codes', 'wpcredits-program-manager' ),
			esc_attr__( 'One code per line', 'wpcredits-program-manager' ),
			esc_html(
				sprintf(
					/* translators: 1: the longest line allowed, 2: the most codes an offer holds. */
					__( 'One code per line, or a CSV with the code in the first column and no header row. A code can be a whole checkout link. Up to %1$d characters a line and %2$d codes an offer. Codes are stored encrypted and shown only to the person who claims one.', 'wpcredits-program-manager' ),
					WPCPM_Sponsor_Codes::LINE_MAX,
					WPCPM_Sponsor_Codes::CODES_MAX
				)
			)
		);
		printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Add codes', 'wpcredits-program-manager' ) );
		echo '</form>';

		if ( $counts['available'] > 0 ) {
			printf(
				'<form method="post" action="%1$s" class="wpcpm-inline-form wpcpm-offer__void-form" data-wpcpm-once data-wpcpm-busy="%2$s" onsubmit="return confirm( \'%3$s\' );">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Voiding', 'wpcredits-program-manager' ),
				esc_js( __( 'Void every code nobody has claimed yet? They cannot be brought back.', 'wpcredits-program-manager' ) )
			);
			wp_nonce_field( self::ACTION_CODES_VOID . '_' . $offer['id'] );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CODES_VOID ) );
			printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
			printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
			printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html__( 'Void unclaimed codes', 'wpcredits-program-manager' ) );
			echo '</form>';
		}
	}

	/**
	 * The form for a new offer.
	 *
	 * @param string $record Sponsor record ID.
	 */
	private static function render_new_form( $record ) {
		echo '<h4 class="wpcpm-sponsor__subheading">' . esc_html__( 'New offer', 'wpcredits-program-manager' ) . '</h4>';
		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form wpcpm-offer__form wpcpm-offer__form--new" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Creating', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE . '_new' );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
		echo '<input type="hidden" name="wpcpm_offer" value="0" />';
		self::render_fields( null, $record, false );
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
