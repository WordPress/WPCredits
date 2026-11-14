<?php
/**
 * The review of a sponsor's offers: what waits for an Administrator, Approve, and Return it with this note.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An offer a sponsor member sends for review, and a change a member makes to what students see of
 * a live one, wait for an Administrator (1.123.18), as a sponsor's post does: the partner page offers
 * products at any level, and the Foundation decides how they are shared.
 *
 * Modeled on the sponsor posts' review, in its words and its places: the Administrator Dashboard's
 * card, whose items this class draws, Approve where a post has Publish, the folded "Return with a
 * note" and "Return it with this note", a refused note given back as it was typed, and an email to
 * the member who sent it, from the template registry. The offer's state is the offers class's
 * (`WPCPM_Sponsor_Offers::approve()`, `return_with_note()`); this class reads what waits, draws the
 * decision and handles the two presses, an Administrator's alone (`ACT_REVIEW_OFFER`).
 *
 * The review is not a tier's: it applies whatever the tiers switch says, and nothing it prints
 * names a tier.
 *
 * **Codes added (1.123.20).** A code can be a whole checkout link, so an item shows the codes it
 * would put in front of students under "Codes added": the codes a member added to a visible offer,
 * held for review, or, for a new pool, the codes it holds. How many, how many are web addresses, and
 * the codes themselves, web addresses first, at most `CODES_SHOWN`, each printed as text and never
 * as a link. They are unsealed when the item is drawn, not in `waiting_all()`, which the menu
 * bubble and the counts read too and which must not open five thousand codes to count one item.
 *
 * **What the Administrator saw (1.123.23).** An item is drawn first and pressed later, and a
 * sponsor's member may add codes or change the held words in between. So the decision forms carry
 * a fingerprint of what waits and is shown (`fingerprint()`), and Approve and Return compute it
 * again before they change anything: when it differs, or a form carries none, nothing is decided,
 * a typed note is kept, and the item is drawn again with what waits now.
 */
final class WPCPM_Sponsor_Offer_Review {

	/** The Administrator Dashboard card's key, and its anchor there and on the Sponsors screen's queue tab. */
	const CARD = 'sponsor-offers-review';

	const ACTION_APPROVE = 'wpcpm_offer_approve';
	const ACTION_RETURN  = 'wpcpm_offer_return';

	/** The return note's typed box, kept through a refusal. */
	const TYPED_RETURN = 'offer-return';

	/** The email to the member who sent what was returned. */
	const MAIL_RETURNED = 'sponsor-offer-returned';

	const LOG_APPROVED = 'offer_approved';
	const LOG_RETURNED = 'offer_returned';

	/** The longest note, counted as typed, as the posts' own. */
	const MAX_NOTE = 2000;

	/** The most codes an item prints under Codes added; a line counts the rest. */
	const CODES_SHOWN = 20;

	/** The decision forms' field for what the item showed (`fingerprint()`). */
	const FIELD_SEEN = 'wpcpm_seen';

	/**
	 * The two handlers.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_APPROVE, array( __CLASS__, 'handle_approve' ) );
		add_action( 'admin_post_' . self::ACTION_RETURN, array( __CLASS__, 'handle_return' ) );
	}

	/**
	 * What waits for review, the oldest sent first, as the facts the queue prints: no offer object
	 * leaves this class.
	 *
	 * @param int $limit How many at most.
	 * @return array[] `id`, `record`, `company`, `title`, `change` (`new` or `changes`), `by` (the name
	 *                 of the member who sent it, or ''), `at` (Unix time), and `fields`, each a label,
	 *                 what students see now (null for a new offer) and what it would be.
	 */
	public static function waiting_all( $limit = 50 ) {
		$rows = array();

		foreach ( array_slice( WPCPM_Sponsor_Offers::waiting(), 0, max( 1, (int) $limit ), true ) as $offer ) {
			$record = (string) $offer['sponsor'];

			if ( ! WPCPM_Sponsors_Index::is_key( $record ) ) {
				continue;
			}

			$row    = WPCPM_Sponsors_Index::row( $record );
			$member = empty( $offer['submitted']['by'] ) ? null : get_user_by( 'id', (int) $offer['submitted']['by'] );
			$change = WPCPM_Sponsor_Offers::STATE_REVIEW === $offer['state'] ? 'new' : 'changes';
			$title  = 'changes' === $change && isset( $offer['proposed']['title'] ) ? (string) $offer['proposed']['title'] : (string) $offer['title'];

			$rows[] = array(
				'id'      => (int) $offer['id'],
				'record'  => $record,
				'company' => is_array( $row ) && '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : $record,
				'title'   => '' !== trim( $title ) ? $title : __( '(no title)', 'wpcredits-program-manager' ),
				'change'  => $change,
				'by'      => $member instanceof WP_User ? (string) $member->display_name : '',
				'at'      => isset( $offer['submitted']['at'] ) ? (int) $offer['submitted']['at'] : 0,
				'fields'  => 'new' === $change ? self::new_fields( $offer ) : self::changed_fields( $offer ),
			);
		}

		return $rows;
	}

	/**
	 * A new offer as students would read it: each line a label, null, and the value.
	 *
	 * @param array $offer The offer.
	 * @return array[]
	 */
	private static function new_fields( array $offer ) {
		$fields = array(
			array( __( 'What you get', 'wpcredits-program-manager' ), null, (string) $offer['text'] ),
			array( __( 'How to redeem it', 'wpcredits-program-manager' ), null, (string) $offer['instructions'] ),
		);

		if ( '' !== trim( (string) $offer['url'] ) ) {
			$fields[] = array( __( 'Link', 'wpcredits-program-manager' ), null, (string) $offer['url'] );
		}

		if ( WPCPM_Sponsor_Offers::KIND_SHARED === $offer['kind'] ) {
			$fields[] = array( __( 'Kind', 'wpcredits-program-manager' ), null, __( 'One code or link everyone uses', 'wpcredits-program-manager' ) );
			$fields[] = array( __( 'The shared code or link', 'wpcredits-program-manager' ), null, (string) WPCPM_Sponsor_Codes::shared( $offer['id'] ) );
		} else {
			$fields[] = array( __( 'Kind', 'wpcredits-program-manager' ), null, __( 'A pool of one-time codes, one per person', 'wpcredits-program-manager' ) );
		}

		$open = array( __( 'Current students', 'wpcredits-program-manager' ) );

		if ( in_array( 'mentors', (array) $offer['audience'], true ) ) {
			$open[] = __( 'mentors', 'wpcredits-program-manager' );
		}

		if ( in_array( 'managers', (array) $offer['audience'], true ) ) {
			$open[] = __( 'the program team', 'wpcredits-program-manager' );
		}

		$fields[] = array( __( 'Open to', 'wpcredits-program-manager' ), null, implode( ', ', $open ) );

		return $fields;
	}

	/**
	 * A change held for review: each field it changes, with what students see now and what it would be.
	 *
	 * @param array $offer The offer, as approved, with its change.
	 * @return array[]
	 */
	private static function changed_fields( array $offer ) {
		$labels = array(
			'title'        => WPCPM_Sponsor_Offers::labels()['title'],
			'text'         => __( 'What you get', 'wpcredits-program-manager' ),
			'instructions' => __( 'How to redeem it', 'wpcredits-program-manager' ),
			'url'          => __( 'Link', 'wpcredits-program-manager' ),
		);
		$fields = array();

		foreach ( $labels as $key => $label ) {
			if ( isset( $offer['proposed'][ $key ] ) && (string) $offer['proposed'][ $key ] !== (string) $offer[ $key ] ) {
				$fields[] = array( $label, (string) $offer[ $key ], (string) $offer['proposed'][ $key ] );
			}
		}

		if ( isset( $offer['proposed']['shared'] ) ) {
			$now = (string) WPCPM_Sponsor_Codes::shared( $offer['id'] );

			if ( (string) $offer['proposed']['shared'] !== $now ) {
				$fields[] = array( __( 'The shared code or link', 'wpcredits-program-manager' ), $now, (string) $offer['proposed']['shared'] );
			}
		}

		return $fields;
	}

	/**
	 * What an item shows under Codes added (1.123.20): for changes, the codes a member added that wait
	 * for review; for a new pool, the codes it holds that a person could be handed. Null for a shared
	 * offer and for nothing to show.
	 *
	 * @param int    $offer_id The offer.
	 * @param string $change   `new` or `changes`, as `waiting_all()` names it.
	 * @return array|null `count`, `links` (how many are web addresses), `shown` (the codes, web
	 *                    addresses first, then the rest, each in the order they were added, at most
	 *                    `CODES_SHOWN`) and `more` (how many are not shown).
	 */
	public static function codes_added( $offer_id, $change ) {
		$offer = WPCPM_Sponsor_Offers::read( (int) $offer_id );

		if ( null === $offer || WPCPM_Sponsor_Offers::KIND_CODES !== $offer['kind'] ) {
			return null;
		}

		$codes = 'new' === $change ? WPCPM_Sponsor_Codes::available_codes( $offer['id'] ) : WPCPM_Sponsor_Codes::held_codes( $offer['id'] );

		if ( empty( $codes ) ) {
			return null;
		}

		$links = array();
		$plain = array();

		foreach ( $codes as $code ) {
			if ( self::is_web_address( $code ) ) {
				$links[] = $code;
			} else {
				$plain[] = $code;
			}
		}

		return array(
			'count' => count( $codes ),
			'links' => count( $links ),
			'shown' => array_slice( array_merge( $links, $plain ), 0, self::CODES_SHOWN ),
			'more'  => max( 0, count( $codes ) - self::CODES_SHOWN ),
		);
	}

	/**
	 * A fingerprint of everything that waits on an offer and is shown for review (1.123.23), which
	 * the decision forms carry and both presses compute again before they change anything: the
	 * offer's state and kind, the words students see now and, for a shared offer, its code, the
	 * held change (`proposed`), the held codes' fingerprints in the order they were added and, for
	 * a new offer, who it is open to and the fingerprints of the codes its pool holds. Values, never
	 * the labels an item prints them under, so a press in another language than the page reads the
	 * same. Not who sent it or when, nor what students claimed: a claim changes nothing the item
	 * shows, and refusing on one would refuse every decision on a busy live offer. Keyed
	 * (`wp_hash()`), since it is printed in a form and a short shared code under a plain hash would
	 * be a guess away. One method, for the form and the presses alike.
	 *
	 * @param array         $offer An offer, as read.
	 * @param string[]|null $held  The held codes' fingerprints as `WPCPM_Sponsor_Codes::held_prints()`
	 *                             read them, or null to read them here.
	 * @return string
	 */
	public static function fingerprint( array $offer, $held = null ) {
		$id       = (int) $offer['id'];
		$proposed = array_map( 'strval', (array) $offer['proposed'] );

		ksort( $proposed );

		$seen = array(
			'state'    => (string) $offer['state'],
			'kind'     => (string) $offer['kind'],
			'words'    => array( (string) $offer['title'], (string) $offer['text'], (string) $offer['instructions'], (string) $offer['url'] ),
			'shared'   => WPCPM_Sponsor_Offers::KIND_SHARED === $offer['kind'] ? (string) WPCPM_Sponsor_Codes::shared( $id ) : '',
			'proposed' => $proposed,
			'held'     => array_values( array_map( 'strval', null === $held ? WPCPM_Sponsor_Codes::held_prints( $id ) : (array) $held ) ),
		);

		if ( WPCPM_Sponsor_Offers::STATE_REVIEW === $offer['state'] ) {
			$seen['audience'] = array_values( array_map( 'strval', (array) $offer['audience'] ) );
			$seen['codes']    = WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind'] ? WPCPM_Sponsor_Codes::available_prints( $id ) : array();
		}

		return wp_hash( (string) wp_json_encode( $seen ) );
	}

	/**
	 * Whether a code is a web address: a line that starts with `http://`, `https://` or `www.`,
	 * whatever its case.
	 *
	 * @param string $code A code.
	 * @return bool
	 */
	public static function is_web_address( $code ) {
		return 1 === preg_match( '#^(?:https?://|www\.)#i', (string) $code );
	}

	/**
	 * One item of the queue, as the Administrator Dashboard's card draws it: the title and the company,
	 * whether it is a new offer or changes to a live one, who sent it and when, what students would
	 * read, the codes it adds (1.123.20), and the decision, on the shared parts (`render_item_parts()`).
	 * Every value is the sponsor's or the member's and is escaped; a code is printed as text in `code`,
	 * never as a link.
	 *
	 * @param array  $row    One row of `waiting_all()`.
	 * @param string $back   `WPCPM_Return::DASHBOARD` to come back to the dashboard, else ''.
	 */
	public static function render_item( array $row, $back = '' ) {
		self::render_item_parts( $row, $back );
	}

	/**
	 * One item on the shared parts (design 2.10, 2.6, 4.1): the item
	 * header (the offer's title; then the company, whether it is a new offer or changes to a live one, "Sent ..." and
	 * "By ..." as muted facts), what students would read and the codes it adds, as today, then the decision as one
	 * Decide area. The item keeps its article and its anchor, as today.
	 *
	 * @param array  $row  One row of `waiting_all()`.
	 * @param string $back `WPCPM_Return::DASHBOARD` to come back to the dashboard, else ''.
	 */
	private static function render_item_parts( array $row, $back ) {
		$facts = array(
			(string) $row['company'],
			'new' === $row['change'] ? __( 'A new offer', 'wpcredits-program-manager' ) : __( 'Changes to a live offer', 'wpcredits-program-manager' ),
		);

		if ( (int) $row['at'] > 0 ) {
			/* translators: %s: how long ago. */
			$facts[] = sprintf( __( 'Sent %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $row['at'], time() ) );
		}

		if ( '' !== (string) $row['by'] ) {
			/* translators: %s: the account's name. */
			$facts[] = sprintf( __( 'By %s', 'wpcredits-program-manager' ), (string) $row['by'] );
		}

		$header = WPCPM_UI::item_header(
			array(
				'name'  => (string) $row['title'],
				'facts' => $facts,
			)
		);

		echo WPCPM_UI::item_open( 'wpcpm-sponsor-offer', 'wpcpm-sponsor-offer-' . (int) $row['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_open().
		echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::item_header().
		self::render_details( $row );
		self::render_decision( (int) $row['id'], $back );
		echo '</article>';
	}

	/**
	 * What students would read of an item, and the codes it adds, as the key and value table part (design 2.7; task
	 * V1, ruling 8): one row per field, its label in the label column, its value as students would read it, an empty
	 * value kept in today's words and muted; a change as what students see now and what it would be, on two lines; then
	 * Codes added. Today's words, every value escaped.
	 *
	 * @param array $row One row of `waiting_all()`.
	 */
	private static function render_details( array $row ) {
		$label = array(
			'key'    => 'label',
			'label'  => '',
			'kind'   => 'text',
			'header' => true,
		);
		$value = array(
			'key'   => 'value',
			'label' => '',
			'kind'  => 'text',
		);
		$out   = WPCPM_UI::table_open( array( $label, $value ), array( 'keyvalue' => true ) );

		foreach ( (array) $row['fields'] as $field ) {
			if ( null === $field[1] ) {
				$written = '' !== trim( (string) $field[2] );
				$html    = $written ? nl2br( esc_html( (string) $field[2] ) ) : esc_html__( 'Not written yet.', 'wpcredits-program-manager' );
				$out    .= WPCPM_UI::row_open() . WPCPM_UI::cell( $label, esc_html( (string) $field[0] ) ) . WPCPM_UI::cell( $value, $html, $written ? '' : 'empty' ) . WPCPM_UI::row_close();
				continue;
			}

			$html = nl2br(
				/* translators: %s: what students see of the offer now. */
				esc_html( sprintf( __( 'Now: %s', 'wpcredits-program-manager' ), (string) $field[1] ) )
			) . '<br>' . nl2br(
				/* translators: %s: what the sponsor asks students to see instead. */
				esc_html( sprintf( __( 'Asked for: %s', 'wpcredits-program-manager' ), (string) $field[2] ) )
			);
			$out .= WPCPM_UI::row_open() . WPCPM_UI::cell( $label, esc_html( (string) $field[0] ) ) . WPCPM_UI::cell( $value, $html ) . WPCPM_UI::row_close();
		}

		$codes = self::codes_added_html( self::codes_added( (int) $row['id'], (string) $row['change'] ) );

		if ( '' !== $codes ) {
			$out .= WPCPM_UI::row_open() . WPCPM_UI::cell( $label, esc_html__( 'Codes added', 'wpcredits-program-manager' ) ) . WPCPM_UI::cell( $value, $codes ) . WPCPM_UI::row_close();
		}

		echo $out . WPCPM_UI::table_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the table parts; each value through esc_html() above.
	}

	/**
	 * The value of an item's Codes added row: how many in all and how many are web addresses, then each code whole on
	 * a line of its own, as text and never as a link (a long checkout link wraps inside its cell), then how many more
	 * there are. '' for null.
	 *
	 * @param array|null $codes What `codes_added()` answered.
	 * @return string Escaped markup, or ''.
	 */
	private static function codes_added_html( $codes ) {
		if ( ! is_array( $codes ) ) {
			return '';
		}

		$said = array(
			/* translators: %s: how many codes. */
			sprintf( _n( '%s code in all.', '%s codes in all.', (int) $codes['count'], 'wpcredits-program-manager' ), number_format_i18n( (int) $codes['count'] ) ),
			0 < (int) $codes['links']
				/* translators: %s: how many of the codes are web addresses. */
				? sprintf( _n( 'Of these, %s is a web address, listed first.', 'Of these, %s are web addresses, listed first.', (int) $codes['links'], 'wpcredits-program-manager' ), number_format_i18n( (int) $codes['links'] ) )
				: __( 'None of these is a web address.', 'wpcredits-program-manager' ),
		);
		$html = esc_html( implode( ' ', $said ) );

		foreach ( (array) $codes['shown'] as $code ) {
			$html .= '<code class="wpcpm-ui-code">' . esc_html( (string) $code ) . '</code>';
		}

		if ( 0 < (int) $codes['more'] ) {
			/* translators: %s: how many codes the list leaves out. */
			$html .= WPCPM_UI::cell_sub( sprintf( _n( '%s more code is not shown.', '%s more codes are not shown.', (int) $codes['more'], 'wpcredits-program-manager' ), number_format_i18n( (int) $codes['more'] ) ) );
		}

		return $html;
	}

	/**
	 * An Administrator's decision on what waits, in the posts' own shape, as one Decide area
	 * (`render_decide_area()`): Approve, and "Return with a note" whose panel opens only when chosen. A
	 * refused return comes back with the note as it was typed, its panel open. Both forms carry the
	 * fingerprint of what waits as it is drawn (1.123.23).
	 *
	 * @param int    $offer_id The offer.
	 * @param string $back     `WPCPM_Return::DASHBOARD` to come back to the dashboard, else ''.
	 */
	public static function render_decision( $offer_id, $back = '' ) {
		$offer = WPCPM_Sponsor_Offers::read( (int) $offer_id );

		if ( null === $offer || ! WPCPM_Sponsor_Offers::is_waiting( $offer ) || ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$new = WPCPM_Sponsor_Offers::STATE_REVIEW === $offer['state'];

		// One reading for both forms: what the item shows as it is drawn.
		$seen = self::fingerprint( $offer );

		self::render_decide_area( $offer, $new, $seen, (string) $back );
	}

	/**
	 * The decision on what waits as one Decide area (`WPCPM_UI::decide()`, design 2.6), drawn on the Administrator
	 * Dashboard: Approve, the one primary, acting at once as today; Return with a note, a
	 * secondary opener whose panel holds the note, its label "A note for the sponsor" visible, today's sentence
	 * (for a new offer, or for changes, with the line about codes added when any are held) as its hint, and the
	 * button that keeps "Return it with this note". Nothing is red: returning removes nothing.
	 *
	 * Both forms post what `handle_approve()` and `handle_return()` read: admin-post.php under the once guard, the
	 * hidden fields under each action's nonce keyed to the offer (printed with no id, `WPCPM_UI::nonce()`), the
	 * fingerprint of what the item shows, and the box under its name, id and bound. The panel is printed open when the last press left typing for this
	 * offer's box (`handle_return()` keeps it for a note refused, for a pool another request holds and for an offer
	 * that changed since it was drawn); only a refusal of the note itself, too long or one the cleaner would take
	 * words from, ties the box to its sentence and leads the card's message to it.
	 *
	 * @param array  $offer  The offer, as read.
	 * @param bool   $is_new Whether it is a new offer sent for review, rather than changes to a live one.
	 * @param string $seen   The fingerprint of what the item shows (`fingerprint()`).
	 * @param string $back   `WPCPM_Return::DASHBOARD` to come back to the dashboard, else ''.
	 */
	private static function render_decide_area( array $offer, $is_new, $seen, $back ) {
		$offer_id = (int) $offer['id'];
		$field_id = 'wpcpm-offer-note-' . $offer_id;
		$typed    = WPCPM_Typed_Text::box_form( self::TYPED_RETURN, $offer_id );
		$note     = WPCPM_Typed_Text::kept_box( $typed, 'note' );
		$problem  = '';

		// What the press was refused for, read from the outcome it left on the Institutions screen's channel, which the
		// dashboard's message reads too (`WPCPM_Flash::take()` memoizes, so it is the sentence the card prints). The
		// outcome names no offer, and another offer's box may hold typing an earlier press kept: the refusal is this
		// box's only when this offer's form is the last one kept (`WPCPM_Typed_Text::is_last()`).
		if ( null !== $note && class_exists( 'WPCPM_Institutions' ) ) {
			$status   = sanitize_key( (string) WPCPM_Flash::take( WPCPM_Institutions::FLASH ) );
			$messages = self::messages();

			if ( in_array( $status, array( 'offer-note-long', 'offer-note-loss' ), true ) && isset( $messages[ $status ][1] ) && WPCPM_Typed_Text::is_last( $typed ) ) {
				$problem = (string) $messages[ $status ][1];
			}
		}

		if ( '' !== $problem && class_exists( 'WPCPM_Dashboard_Card' ) ) {
			WPCPM_Dashboard_Card::lead_to( $field_id );
		}

		$said = $is_new
			? __( 'The offer goes back to the sponsor\'s account as a draft, and your note is sent by email to the person who sent it for review.', 'wpcredits-program-manager' )
			: __( 'The changes go back to the sponsor\'s account, students still seeing the offer as it reads, and your note is sent by email to the person who sent them.', 'wpcredits-program-manager' );

		// Codes a member added are not kept with a returned change (1.123.20).
		if ( ! $is_new && (int) $offer['codes_held'] > 0 ) {
			$said .= ' ' . __( 'The codes added are taken out of the offer.', 'wpcredits-program-manager' );
		}

		$form   = array(
			'method'          => 'post',
			'action'          => admin_url( 'admin-post.php' ),
			'data-wpcpm-once' => true,
		);
		$hidden = static function ( $action ) use ( $offer_id, $seen, $back ) {
			return static function () use ( $action, $offer_id, $seen, $back ) {
				echo WPCPM_UI::nonce( $action . '_' . $offer_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::nonce().
				printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
				printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer_id );
				printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_SEEN ), esc_attr( $seen ) );

				if ( class_exists( 'WPCPM_Return' ) ) {
					WPCPM_Return::field( $back, self::CARD );
				}
			};
		};

		$area = WPCPM_UI::decide(
			array(
				'id'        => 'wpcpm-decide-offer-' . $offer_id,
				'level'     => 'h5',
				// Drawn on the Administrator Dashboard alone, which is never wp-admin.
				'side'      => 'front',
				'decisions' => array(
					array(
						'key'        => 'approve',
						'label'      => __( 'Approve', 'wpcredits-program-manager' ),
						'kind'       => 'primary',
						'form_attrs' => $form,
						'hidden'     => $hidden( self::ACTION_APPROVE ),
						'fields'     => '',
					),
					array(
						'key'           => 'return',
						'label'         => __( 'Return with a note', 'wpcredits-program-manager' ),
						'kind'          => 'secondary',
						'form_attrs'    => $form,
						'hidden'        => $hidden( self::ACTION_RETURN ),
						'fields'        => WPCPM_UI::field(
							array(
								'id'      => $field_id,
								'name'    => 'wpcpm_note',
								'label'   => self::note_label(),
								'hint'    => $said,
								'need'    => 'required',
								'type'    => 'textarea',
								'rows'    => 2,
								'value'   => null === $note ? '' : $note,
								'attrs'   => array(
									// Drawn empty, so the room is the limit: nothing in it is held as an entity.
									'maxlength'   => (int) WPCPM_Typed_Text::drawn_limit( '', self::MAX_NOTE ),
									'placeholder' => __( 'What should change before students see it', 'wpcredits-program-manager' ),
								),
								'problem' => $problem,
							)
						),
						'confirm_label' => __( 'Return it with this note', 'wpcredits-program-manager' ),
						'open'          => null !== $note,
					),
				),
			)
		);

		echo $area; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::decide(); the hidden inputs in the decisions' callables.
	}

	/**
	 * The decisions' outcomes, merged by the Administrator Dashboard's messages.
	 *
	 * @return array
	 */
	public static function messages() {
		return array(
			'offer-approved'      => array( 'success', __( 'Approved. Students and mentors the offer is open to see it as it now reads.', 'wpcredits-program-manager' ) ),
			'offer-returned'      => array( 'success', __( 'The offer went back to the sponsor with your note.', 'wpcredits-program-manager' ) ),
			'offer-note-missing'  => array( 'error', __( 'Write a note for the sponsor before returning the offer.', 'wpcredits-program-manager' ) ),
			/* translators: %s: the longest note allowed, in characters. */
			'offer-note-long'     => array( 'error', sprintf( __( 'The note is longer than %s characters. Shorten it and return the offer again.', 'wpcredits-program-manager' ), number_format_i18n( self::MAX_NOTE ) ) ),
			'offer-note-loss'     => array( 'error', WPCPM_Typed_Text::loss_message( self::note_label(), 'return' ) ),
			'offer-not-waiting'   => array( 'error', __( 'Only an offer or a change waiting for review can be approved or returned here.', 'wpcredits-program-manager' ) ),
			'offer-approve-empty' => array( 'error', __( 'Not approved: the offer has no code or link left to give. Return it with a note, so the sponsor adds one.', 'wpcredits-program-manager' ) ),
			'offer-review-failed' => array( 'error', __( 'The offer could not be changed right now. Try again later.', 'wpcredits-program-manager' ) ),
			'offer-changed'       => array( 'error', __( 'This offer changed after you opened it, so nothing was decided. Read it again, then decide.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The return note's box, by the words its form labels it with, so the form and the sentence that
	 * refuses a note name it alike.
	 *
	 * @return string
	 */
	private static function note_label() {
		return __( 'A note for the sponsor', 'wpcredits-program-manager' );
	}

	/**
	 * Back to the Administrator Dashboard at the card, with the outcome on that page's channel, when
	 * the press came from there, as every decision drawn there does; else to the Sponsor Dashboard's
	 * Offers card, through the dashboard's one door. Called by array callable so
	 * bin/check-references.php meets the door where it lives.
	 *
	 * @param string $status Flash key.
	 * @param string $record The sponsor, for a manager's switcher.
	 */
	private static function leave( $status, $record = '' ) {
		if ( class_exists( 'WPCPM_Return' ) && WPCPM_Return::DASHBOARD === WPCPM_Request::posted_key( WPCPM_Return::FIELD ) ) {
			WPCPM_Flash::set( WPCPM_Institutions::FLASH, $status );
			wp_safe_redirect( WPCPM_Return::url( home_url( '/' ) ) );
			exit;
		}

		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, WPCPM_Sponsor_Offers::CARD, $record, '' );
		exit;
	}

	/**
	 * The shared opening of both presses: the nonce first, then the offer, the claim, an
	 * Administrator's alone, on the offer's own sponsor, and whether anything of it waits.
	 *
	 * @param string $action `ACTION_APPROVE` or `ACTION_RETURN`.
	 * @return array `offer`, `record`, `claim`; leaves on any refusal.
	 */
	private static function begin( $action ) {
		if ( ! is_user_logged_in() ) {
			self::leave( 'refused' );
		}

		// The nonce before any lookup, as every handler in the module does: a request without one
		// learns nothing about which ids are offers.
		$offer_id = WPCPM_Request::posted_id( 'wpcpm_offer' );

		check_admin_referer( $action . '_' . $offer_id );

		$offer = $offer_id > 0 ? WPCPM_Sponsor_Offers::read( $offer_id ) : null;

		if ( null === $offer || ! WPCPM_Sponsors_Index::is_key( $offer['sponsor'] ) ) {
			self::leave( 'refused' );
		}

		$claim = WPCPM_Sponsor_Roster::claim( $offer['sponsor'], WPCPM_Sponsor_Policy::ACT_REVIEW_OFFER );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused' );
		}

		if ( ! WPCPM_Sponsor_Offers::is_waiting( $offer ) ) {
			self::leave( 'offer-not-waiting', $offer['sponsor'] );
		}

		return array(
			'offer'  => $offer,
			'record' => (string) $offer['sponsor'],
			'claim'  => $claim,
		);
	}

	/**
	 * The presses' own check, after the nonce and the policy and before anything changes (1.123.23):
	 * the fingerprint the item's form posted, against what waits now. A form drawn before a member
	 * added a code, changed the held words or moved the offer, and a form a release before 1.123.23
	 * drew, which posts none, decide nothing. The pool reads the held codes again under its lock when
	 * the decision moves them, so a code held after this check is not decided either.
	 *
	 * @param array  $opened What `begin()` answered.
	 * @param string $form   The return note's typed box (`WPCPM_Typed_Text::box_form()`), or '' for
	 *                       Approve, which has none.
	 * @param string $typed  The note as it was typed, kept for the item drawn again.
	 * @return string[] The fingerprints of the held codes the decision covers, in order.
	 */
	private static function as_shown( array $opened, $form = '', $typed = '' ) {
		$held = WPCPM_Sponsor_Codes::held_prints( $opened['offer']['id'] );
		$seen = WPCPM_Request::posted_key( self::FIELD_SEEN );

		if ( '' === $seen || ! hash_equals( self::fingerprint( $opened['offer'], $held ), $seen ) ) {
			self::changed( $opened['record'], $form, $typed );
		}

		return $held;
	}

	/**
	 * Leave without a decision on an offer that changed since its item was drawn (1.123.23): a typed
	 * note is kept as the busy refusal keeps it, and the item is drawn again with what waits now.
	 *
	 * @param string $record The sponsor.
	 * @param string $form   The return note's typed box, or '' when there is none.
	 * @param string $typed  The note as it was typed.
	 */
	private static function changed( $record, $form = '', $typed = '' ) {
		if ( '' !== $form ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
		}

		self::leave( 'offer-changed', $record );
	}

	/**
	 * A decision's audit data, with how many held codes it released or took out when it moved any
	 * (1.123.20): the count, never a code.
	 *
	 * @param array $data  The data.
	 * @param int   $codes How many codes.
	 * @return array
	 */
	private static function with_codes( array $data, $codes ) {
		if ( (int) $codes > 0 ) {
			$data['codes'] = (int) $codes;
		}

		return $data;
	}

	/**
	 * An Administrator approves: an offer sent for review goes live, a change becomes what students
	 * see, and codes a member added join the pool (1.123.20). What the item showed, and nothing a
	 * member added or changed since (1.123.23, `as_shown()`).
	 */
	public static function handle_approve() {
		$opened = self::begin( self::ACTION_APPROVE );
		$offer  = $opened['offer'];
		$held   = self::as_shown( $opened );
		$change = WPCPM_Sponsor_Offers::STATE_REVIEW === $offer['state'] ? 'new' : 'changes';
		// For the trail: the held codes the approval releases, which the pool releases only while they
		// are the ones the item showed.
		$codes = 'changes' === $change && WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind'] ? count( $held ) : 0;
		$done  = WPCPM_Sponsor_Offers::approve( $offer['id'], $held );

		if ( is_wp_error( $done ) ) {
			$statuses = array(
				'wpcpm_offer_empty'       => 'offer-approve-empty',
				'wpcpm_offer_not_waiting' => 'offer-not-waiting',
				'wpcpm_codes_changed'     => 'offer-changed',
			);
			$code     = $done->get_error_code();

			self::leave( isset( $statuses[ $code ] ) ? $statuses[ $code ] : 'offer-review-failed', $opened['record'] );
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_APPROVED,
				'sponsor'  => $opened['record'],
				'subject'  => (string) $offer['id'],
				'actor'    => get_current_user_id(),
				'ground'   => $opened['claim']['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => __( 'An offer was approved.', 'wpcredits-program-manager' ),
				'data'     => self::with_codes(
					array(
						'offer'  => (int) $offer['id'],
						'change' => $change,
					),
					$codes
				),
			)
		);

		self::leave( 'offer-approved', $opened['record'] );
	}

	/**
	 * An Administrator returns what waits with a note, kept on the offer and emailed to the member who
	 * sent it. The note is counted as typed (`WPCPM_Typed_Text::box_length()`) and refused over
	 * `MAX_NOTE` rather than cut, and refused before anything is written or mailed when the cleaner
	 * would take words from it; both refusals give the form back the note as it was typed, as the
	 * posts' own return does. A return covers what the item showed and nothing a member added or
	 * changed since (1.123.23, `as_shown()`): it is asked first, and keeps the note too.
	 */
	public static function handle_return() {
		$opened = self::begin( self::ACTION_RETURN );
		$offer  = $opened['offer'];
		$form   = WPCPM_Typed_Text::box_form( self::TYPED_RETURN, $offer['id'] );

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( $form );

		$typed = WPCPM_Request::posted_raw( 'wpcpm_note' );
		$note  = isset( $_POST['wpcpm_note'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['wpcpm_note'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- begin() verified the nonce.
		$held  = self::as_shown( $opened, $form, $typed );

		if ( WPCPM_Typed_Text::cleaner_loses( $typed, 'lines' ) ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
			self::leave( 'offer-note-loss', $opened['record'] );
		}

		if ( '' === $note ) {
			self::leave( 'offer-note-missing', $opened['record'] );
		}

		$length = WPCPM_Typed_Text::box_length( $typed );

		if ( $length > self::MAX_NOTE ) {
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
			self::leave( 'offer-note-long', $opened['record'] );
		}

		$returned = WPCPM_Sponsor_Offers::return_with_note( $offer['id'], $note, $held );

		if ( is_wp_error( $returned ) ) {
			if ( 'wpcpm_offer_not_waiting' === $returned->get_error_code() ) {
				self::leave( 'offer-not-waiting', $opened['record'] );
			}

			// A code held after the check above, seen by the pool under its lock.
			if ( 'wpcpm_codes_changed' === $returned->get_error_code() ) {
				self::changed( $opened['record'], $form, $typed );
			}

			// The pool another request holds (1.123.20) refuses the return for a moment, not the note:
			// the fold opens on it as it was typed, for the press to be made again.
			WPCPM_Typed_Text::keep_box( $form, 'note', $typed, self::MAX_NOTE );
			self::leave( 'offer-review-failed', $opened['record'] );
		}

		// A mail is plain text: the title and the note are handed over as stored and read back to what
		// was typed once, as every value is (`WPCPM_Mail_Templates::fill()`), and the subject is marked
		// plain text, so the mail layer keeps a "<" and an "&" in it as they are.
		$member = $returned['by'] > 0 ? get_user_by( 'id', (int) $returned['by'] ) : null;

		if ( $member instanceof WP_User ) {
			$title = (string) $offer['title'];
			$said  = (string) $note;
			// The Offers view, at the Offers card (1.123.78); '' while the page is not published.
			$link  = class_exists( 'WPCPM_Sponsors_Dashboard' ) && method_exists( 'WPCPM_Sponsors_Dashboard', 'card_url' ) ? (string) WPCPM_Sponsors_Dashboard::card_url( WPCPM_Sponsor_Offers::CARD ) : '';

			WPCPM_Mail::send(
				$member,
				self::MAIL_RETURNED,
				static function ( WP_User $to ) use ( $title, $said, $link ) {
					return WPCPM_Mail_Templates::mail(
						self::MAIL_RETURNED,
						array(
							'offer_title' => $title,
							'member_name' => $to->display_name,
							'note'        => $said,
							'offers_link' => $link,
						),
						array( 'plain_subject' => true )
					);
				}
			);
		}

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_RETURNED,
				'sponsor'  => $opened['record'],
				'subject'  => (string) $offer['id'],
				'actor'    => get_current_user_id(),
				'ground'   => $opened['claim']['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => __( 'An offer was returned to its sponsor with a note.', 'wpcredits-program-manager' ),
				// The note's length as the limit counted it, never the note: the audit is about the act.
				'data'     => self::with_codes(
					array(
						'offer'       => (int) $offer['id'],
						'change'      => $returned['change'],
						'note_length' => $length,
					),
					isset( $returned['codes'] ) ? (int) $returned['codes'] : 0
				),
			)
		);

		self::leave( 'offer-returned', $opened['record'] );
	}
}
