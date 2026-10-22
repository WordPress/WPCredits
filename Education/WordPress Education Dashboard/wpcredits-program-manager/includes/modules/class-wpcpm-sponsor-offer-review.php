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
	 * read, the codes it adds (1.123.20), and the decision. Every value is the sponsor's or the
	 * member's and is escaped; a code is printed as text in `code`, never as a link.
	 *
	 * @param array  $row    One row of `waiting_all()`.
	 * @param string $back   `WPCPM_Return::DASHBOARD` to come back to the dashboard, else ''.
	 */
	public static function render_item( array $row, $back = '' ) {
		printf( '<article class="wpcpm-administrator__item wpcpm-sponsor-offer" id="wpcpm-sponsor-offer-%d">', (int) $row['id'] );
		printf(
			'<h4 class="wpcpm-administrator__item-title">%1$s <span class="wpcpm-administrator__kind">%2$s</span></h4>',
			esc_html( (string) $row['title'] ),
			esc_html( (string) $row['company'] )
		);
		echo '<p class="wpcpm-administrator__facts">';
		printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( 'new' === $row['change'] ? __( 'A new offer', 'wpcredits-program-manager' ) : __( 'Changes to a live offer', 'wpcredits-program-manager' ) ) );

		if ( (int) $row['at'] > 0 ) {
			/* translators: %s: how long ago. */
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'Sent %s ago', 'wpcredits-program-manager' ), human_time_diff( (int) $row['at'], time() ) ) ) );
		}

		if ( '' !== (string) $row['by'] ) {
			/* translators: %s: the account's name. */
			printf( '<span class="wpcpm-administrator__fact">%s</span>', esc_html( sprintf( __( 'By %s', 'wpcredits-program-manager' ), (string) $row['by'] ) ) );
		}

		echo '</p><dl class="wpcpm-offer__details">';

		foreach ( (array) $row['fields'] as $field ) {
			if ( null === $field[1] ) {
				printf( '<div class="wpcpm-offer__detail"><dt>%1$s</dt><dd>%2$s</dd></div>', esc_html( (string) $field[0] ), '' !== trim( (string) $field[2] ) ? nl2br( esc_html( (string) $field[2] ) ) : esc_html__( 'Not written yet.', 'wpcredits-program-manager' ) );
				continue;
			}

			printf(
				'<div class="wpcpm-offer__detail"><dt>%1$s</dt><dd><span class="wpcpm-offer__was">%2$s</span> <span class="wpcpm-offer__now">%3$s</span></dd></div>',
				esc_html( (string) $field[0] ),
				/* translators: %s: what students see of the offer now. */
				nl2br( esc_html( sprintf( __( 'Now: %s', 'wpcredits-program-manager' ), (string) $field[1] ) ) ),
				/* translators: %s: what the sponsor asks students to see instead. */
				nl2br( esc_html( sprintf( __( 'Asked for: %s', 'wpcredits-program-manager' ), (string) $field[2] ) ) )
			);
		}

		self::render_codes_added( self::codes_added( (int) $row['id'], (string) $row['change'] ) );
		echo '</dl>';
		self::render_decision( (int) $row['id'], $back );
		echo '</article>';
	}

	/**
	 * The Codes added line of an item: how many in all and how many are web addresses, then the codes
	 * themselves, escaped, then how many more there are. Nothing for null.
	 *
	 * @param array|null $codes What `codes_added()` answered.
	 */
	private static function render_codes_added( $codes ) {
		if ( ! is_array( $codes ) ) {
			return;
		}

		printf( '<div class="wpcpm-offer__detail wpcpm-sponsor-offer__codes-added"><dt>%s</dt><dd>', esc_html__( 'Codes added', 'wpcredits-program-manager' ) );

		$said = array(
			/* translators: %s: how many codes. */
			sprintf( _n( '%s code in all.', '%s codes in all.', (int) $codes['count'], 'wpcredits-program-manager' ), number_format_i18n( (int) $codes['count'] ) ),
			0 < (int) $codes['links']
				/* translators: %s: how many of the codes are web addresses. */
				? sprintf( _n( 'Of these, %s is a web address, listed first.', 'Of these, %s are web addresses, listed first.', (int) $codes['links'], 'wpcredits-program-manager' ), number_format_i18n( (int) $codes['links'] ) )
				: __( 'None of these is a web address.', 'wpcredits-program-manager' ),
		);

		printf( '<p>%s</p><ul class="wpcpm-sponsor-offer__codes">', esc_html( implode( ' ', $said ) ) );

		foreach ( (array) $codes['shown'] as $code ) {
			printf( '<li><code>%s</code></li>', esc_html( (string) $code ) );
		}

		echo '</ul>';

		if ( 0 < (int) $codes['more'] ) {
			/* translators: %s: how many codes the list leaves out. */
			printf( '<p>%s</p>', esc_html( sprintf( _n( '%s more code is not shown.', '%s more codes are not shown.', (int) $codes['more'], 'wpcredits-program-manager' ), number_format_i18n( (int) $codes['more'] ) ) ) );
		}

		echo '</dd></div>';
	}

	/**
	 * An Administrator's decision on what waits, in the posts' own shape: Approve, and a folded
	 * "Return with a note" whose form opens only when chosen. A refused return comes back with the
	 * note as it was typed, its fold open. Both forms carry the fingerprint of what waits as it is
	 * drawn (1.123.23).
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

		echo '<div class="wpcpm-request__decide wpcpm-sponsor-offer__decide">';
		printf( '<form class="wpcpm-sponsor-offer__form" method="post" action="%s" data-wpcpm-once>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::ACTION_APPROVE . '_' . (int) $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_APPROVE ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_SEEN ), esc_attr( $seen ) );

		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $back, self::CARD );
		}

		printf( '<button type="submit" class="button button-primary">%s</button>', esc_html__( 'Approve', 'wpcredits-program-manager' ) );
		echo '</form>';

		$note = WPCPM_Typed_Text::kept_box( WPCPM_Typed_Text::box_form( self::TYPED_RETURN, $offer['id'] ), 'note' );

		echo null === $note ? '<details class="wpcpm-sponsor-offer__return">' : '<details class="wpcpm-sponsor-offer__return" open>';
		printf( '<summary class="button">%s</summary>', esc_html__( 'Return with a note', 'wpcredits-program-manager' ) );
		$said = $new
			? __( 'The offer goes back to the sponsor\'s account as a draft, and your note is sent by email to the person who sent it for review.', 'wpcredits-program-manager' )
			: __( 'The changes go back to the sponsor\'s account, students still seeing the offer as it reads, and your note is sent by email to the person who sent them.', 'wpcredits-program-manager' );

		// Codes a member added are not kept with a returned change (1.123.20).
		if ( ! $new && (int) $offer['codes_held'] > 0 ) {
			$said .= ' ' . __( 'The codes added are taken out of the offer.', 'wpcredits-program-manager' );
		}

		printf( '<p class="wpcpm-administrator__note">%s</p>', esc_html( $said ) );
		printf( '<form class="wpcpm-sponsor-offer__form wpcpm-sponsor-offer__form--return" method="post" action="%s" data-wpcpm-once>', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::ACTION_RETURN . '_' . (int) $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_RETURN ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD_SEEN ), esc_attr( $seen ) );

		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $back, self::CARD );
		}

		printf(
			'<label class="screen-reader-text" for="wpcpm-offer-note-%1$d">%2$s</label><textarea id="wpcpm-offer-note-%1$d" name="wpcpm_note" rows="2" maxlength="%3$d" required placeholder="%4$s">%5$s</textarea>',
			(int) $offer['id'],
			esc_html( self::note_label() ),
			// Drawn empty, so the room is the limit: nothing in it is held as an entity.
			(int) WPCPM_Typed_Text::drawn_limit( '', self::MAX_NOTE ),
			esc_attr__( 'What should change before students see it', 'wpcredits-program-manager' ),
			// The parser drops one line feed right after the opening tag, so a kept note that begins
			// with a line break keeps it.
			null === $note ? '' : "\n" . esc_textarea( $note )
		);
		printf( '<button type="submit" class="button">%s</button>', esc_html__( 'Return it with this note', 'wpcredits-program-manager' ) );
		echo '</form>';
		echo '</details>';
		echo '</div>';
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
			$link  = class_exists( 'WPCPM_Sponsors_Dashboard' ) && method_exists( 'WPCPM_Sponsors_Dashboard', 'page_url' ) ? (string) WPCPM_Sponsors_Dashboard::page_url() : '';
			$link  = '' === $link ? '' : $link . '#wpcpm-sponsor-' . WPCPM_Sponsor_Offers::CARD;

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
