<?php
/**
 * The Sponsor Dashboard's profile card: the fields a sponsor may write back to Airtable.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An allowlist, cleaned field by field, written in one PATCH with an audit row.
 *
 * Design spec of 4 September 2026, section 5.5. The fields are spelled as the base spells
 * them; single selects are validated byte for byte against `CHOICES`, which
 * bin/test-sponsor-cards.php asserts against bin/fixtures/sponsors-table-fields.json, because
 * `update_records()` sends no `typecast` and any other spelling is a 422 for the whole PATCH.
 * One rejected field rejects the whole save, for the same reason: what the base would refuse
 * as a whole is refused here as a whole. Changing `Contact Email` changes the Airtable field
 * and nothing about accounts, and the form says so.
 *
 * Three of the eight fields (`OFFER_FIELDS`) are also written by the primary offer's mirror.
 * Two writers of the same base columns is one too many, and the offer is the text students
 * actually see, so once a primary offer exists this card shows those three read-only and
 * ignores a posted value for them (final review of Phase S2, finding 2).
 */
final class WPCPM_Sponsor_Profile {

	const ACTION_SAVE = 'wpcpm_sponsor_profile_save';
	const CARD        = 'profile';
	const MAX_TEXT    = 4000;
	const MAX_LINE    = 200;
	const LOG_KIND    = 'profile_saved';

	/** Index key => Airtable column, kind, label. The order is the form's. */
	const FIELDS = array(
		'website'        => array(
			'name' => 'Website',
			'kind' => 'url',
		),
		'contact_person' => array(
			'name' => 'Contact Person Full Name',
			'kind' => 'line',
		),
		'contact_email'  => array(
			'name' => 'Contact Email',
			'kind' => 'email',
		),
		'product_type'   => array(
			'name' => 'Type of product',
			'kind' => 'select',
		),
		'offer'          => array(
			'name' => 'Offer',
			'kind' => 'line',
		),
		'instructions'   => array(
			'name' => 'Brief instructions',
			'kind' => 'text',
		),
		'more_info'      => array(
			'name' => 'More info link',
			'kind' => 'url',
		),
		'anything'       => array(
			'name' => "Anything else you'd like to share.",
			'kind' => 'text',
		),
	);

	/** The three fields the primary offer's mirror owns once one exists. */
	const OFFER_FIELDS = array( 'offer', 'instructions', 'more_info' );

	/** The single selects' choices, as the base spells them. */
	const CHOICES = array(
		'Type of product' => array( 'Hosting', 'Plugin', 'Service' ),
	);

	/**
	 * The handler.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * The labels, translated at call time.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			'website'        => __( 'Website', 'wpcredits-program-manager' ),
			'contact_person' => __( 'Contact person', 'wpcredits-program-manager' ),
			'contact_email'  => __( 'Contact email', 'wpcredits-program-manager' ),
			'product_type'   => __( 'Type of product', 'wpcredits-program-manager' ),
			'offer'          => __( 'Your offer, in one line', 'wpcredits-program-manager' ),
			'instructions'   => __( 'How students use it', 'wpcredits-program-manager' ),
			'more_info'      => __( 'Link with more information', 'wpcredits-program-manager' ),
			'anything'       => __( 'Anything else you would like to share', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * This card's outcomes.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			// The sentence after it is the whole message: `WPCPM_Typed_Text::loss_message()` says that
			// nothing was saved, which box, and how to keep every word.
			'profile-loss'      => array( 'error', '' ),
			'profile-saved'     => array( 'success', __( 'Your profile was saved to the program records.', 'wpcredits-program-manager' ) ),
			'profile-unchanged' => array( 'info', __( 'Nothing changed.', 'wpcredits-program-manager' ) ),
			'profile-rejected'  => array( 'error', __( 'One of the values could not be accepted, so nothing was saved: check the links and the product type.', 'wpcredits-program-manager' ) ),
			// The sentence after it names the field and how far over it is (long_sentence()).
			'profile-long'      => array( 'error', __( 'Nothing was saved.', 'wpcredits-program-manager' ) ),
			'profile-failed'    => array( 'error', __( 'The program records could not be updated right now. Try again later.', 'wpcredits-program-manager' ) ),
			'refused'           => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Whether a primary offer already owns the three fields in `OFFER_FIELDS`.
	 *
	 * Guarded by class_exists(): the Offers module is a later phase's file and this card is
	 * loaded on its own by bin/test-sponsor-cards.php, so an absent Offers class means no
	 * offer owns anything and all eight fields stay editable.
	 *
	 * @param string $record Sponsor record ID.
	 * @return bool
	 */
	private static function owned_by_offer( $record ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Offers' ) || ! method_exists( 'WPCPM_Sponsor_Offers', 'offers_of' ) ) {
			return false;
		}

		foreach ( WPCPM_Sponsor_Offers::offers_of( $record ) as $offer ) {
			if ( ! empty( $offer['primary'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clean one posted value by its field's kind.
	 *
	 * A typed text, one line (the Contact person, the offer line) or a text area (How students
	 * use it, Anything else you would like to share), is counted as the person typed it
	 * (`WPCPM_Typed_Text::typed_length()`): the entities the cleaner writes for a "<" and the quote
	 * marks and ampersands after it, and a line break's two bytes, count as the one character each
	 * stands for. One over its limit (`limit_of()`) is refused rather than cut: a name cut short is
	 * a different name, and a text cut short reaches the program missing its end.
	 *
	 * @param string $key A key of `FIELDS`.
	 * @param string $raw The posted value.
	 * @return array `ok` (bool) and `value` (the cleaned value, or null to clear a select); for a
	 *               typed text over its limit, `over` too: how many characters, as typed.
	 */
	public static function clean( $key, $raw ) {
		if ( ! isset( self::FIELDS[ $key ] ) ) {
			return array(
				'ok'    => false,
				'value' => null,
			);
		}

		$raw  = trim( (string) $raw );
		$kind = self::FIELDS[ $key ]['kind'];

		switch ( $kind ) {
			case 'url':
				$value = WPCPM_Field_Value::clean_url( $raw );
				$parts = wp_parse_url( $value );

				// A URL carrying a `user@` (or `user:pass@`) reads as one host to a person
				// and can point the request at another; the feedback form learned this the
				// same way (design spec of 4 September 2026, section 5.5). Refused rather
				// than stripped, so the sponsor sees the save did not happen instead of a
				// silently different link being kept.
				if ( '' !== $value && ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) ) {
					return array(
						'ok'    => false,
						'value' => '',
					);
				}

				return array(
					'ok'    => '' === $raw || '' !== $value,
					'value' => $value,
				);
			case 'email':
				$value = sanitize_email( $raw );

				return array(
					'ok'    => '' === $raw || is_email( $value ),
					'value' => $value,
				);
			case 'select':
				$name = self::FIELDS[ $key ]['name'];

				if ( '' === $raw ) {
					return array(
						'ok'    => true,
						'value' => null,
					);
				}

				return array(
					'ok'    => in_array( $raw, self::CHOICES[ $name ], true ),
					'value' => $raw,
				);
		}

		$value = 'text' === $kind ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		$over  = WPCPM_Typed_Text::typed_length( $value ) - self::limit_of( $key );

		if ( $over > 0 ) {
			return array(
				'ok'    => false,
				'value' => $value,
				'over'  => $over,
			);
		}

		return array(
			'ok'    => true,
			'value' => $value,
		);
	}

	/**
	 * The longest a typed text may be, in characters as typed: `MAX_TEXT` for a text area,
	 * `MAX_LINE` for one line.
	 *
	 * @param string $key A key of `FIELDS`.
	 * @return int
	 */
	private static function limit_of( $key ) {
		return isset( self::FIELDS[ $key ] ) && 'text' === self::FIELDS[ $key ]['kind'] ? self::MAX_TEXT : self::MAX_LINE;
	}

	/**
	 * The form, as `WPCPM_Typed_Text::keep()` names it: one sponsor's profile card.
	 *
	 * @param string $record Sponsor record ID.
	 * @return string
	 */
	private static function typed_form( $record ) {
		return 'profile:' . $record;
	}

	/**
	 * Keep what a refused profile form held for the one time it is drawn again: every field that was
	 * posted, as typed, each up to the room its box was drawn with (`limit_of()`, and never less than
	 * the value the box was drawn with, so a text the base holds over its limit comes back whole).
	 *
	 * @param string $record Sponsor record ID.
	 * @param array  $typed  Each posted field, as posted and not yet cleaned.
	 * @param array  $row    The index row the form was drawn from.
	 */
	private static function keep_typing( $record, array $typed, array $row ) {
		$room = array();

		foreach ( array_keys( $typed ) as $key ) {
			$room[ $key ] = WPCPM_Typed_Text::kept_room( isset( $row[ $key ] ) ? (string) $row[ $key ] : '', self::limit_of( $key ) );
		}

		WPCPM_Typed_Text::keep( self::typed_form( $record ), $typed, $room );
	}

	/**
	 * Whether a posted value is the stored one.
	 *
	 * A typed text is drawn back by `WPCPM_Typed_Text`, so a box posted back unedited can come back
	 * in other bytes than the stored ones: with a ">" the card held as `&gt;`, say, or a text
	 * area's every break as CR LF where the base keeps LF. Read the same, it is the stored text,
	 * and is not written. Every other kind is the stored value only byte for byte.
	 *
	 * Both sides are read trimmed, as the cleaner trims what it reads. A value written in the base's
	 * grid can end in a space or begin with a line break, and a box can hand it back without them:
	 * a text area drops a line break its text begins with, and a link's or an address's box drops
	 * the spaces at its ends. Such a box, posted back untouched, is still the stored value, and a
	 * save would only take the white space off.
	 *
	 * @param string $kind    The field's kind.
	 * @param string $next    The value as posted: as typed, for the test of a box posted back as
	 *                        it was drawn, or as cleaned, for the over-limit rule and the save.
	 * @param string $current The value as stored.
	 * @return bool
	 */
	private static function unchanged( $kind, $next, $current ) {
		$next    = trim( (string) $next );
		$current = trim( (string) $current );

		if ( $next === $current ) {
			return true;
		}

		if ( 'line' === $kind ) {
			return WPCPM_Typed_Text::same_text( $next, $current );
		}

		return 'text' === $kind && WPCPM_Typed_Text::same_lines( $next, $current );
	}

	/**
	 * The sentence after `profile-long`: the field, and how far over its limit it is, so the
	 * sponsor knows how much to take out.
	 *
	 * @param string $key  A key of `FIELDS`.
	 * @param int    $over How many characters, as typed, it is over `limit_of()`.
	 * @return string
	 */
	private static function long_sentence( $key, $over ) {
		$labels = self::labels();
		$over   = max( 1, (int) $over );

		return sprintf(
			/* translators: 1: the field's label, 2: how many characters over, 3: the longest value allowed. */
			_n( '"%1$s" is %2$s character over the limit of %3$s. Shorten it and save again.', '"%1$s" is %2$s characters over the limit of %3$s. Shorten it and save again.', $over, 'wpcredits-program-manager' ),
			isset( $labels[ $key ] ) ? $labels[ $key ] : $key,
			number_format_i18n( $over ),
			number_format_i18n( self::limit_of( $key ) )
		);
	}

	/**
	 * Save the profile.
	 *
	 * The nonce, then the claim (which decides `ACT_EDIT_PROFILE` and meters a refusal), then
	 * every posted typed text a person changed asked whether the cleaner would take words from it,
	 * then every other posted field cleaned, then one PATCH of the cells that changed, then the
	 * index and the log. Only fields that were posted are read, so a form missing one leaves it
	 * alone, and a typed text or a link posted back as it was drawn is left as the base holds it. A
	 * save refused for a loss or a length keeps what was typed for the form's redraw.
	 */
	public static function handle_save() {
		check_admin_referer( self::ACTION_SAVE );

		$claim = WPCPM_Sponsor_Roster::claim( WPCPM_Request::posted_text( 'wpcpm_sponsor' ), WPCPM_Sponsor_Policy::ACT_EDIT_PROFILE );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused', '' );
		}

		$record = $claim['record'];

		// An index the site has not read cannot be written back to: every unposted field is
		// read from $row below (a form missing one leaves it alone), so an empty stand-in row
		// would read as "clear every other field" once the caller posts a full form again. A
		// member whose sponsor left the index still claims (the stamp is well-formed and
		// ungated), so this is the one place that can catch it.
		if ( ! is_array( $claim['row'] ) ) {
			self::leave( 'profile-failed', $claim['record'] );
		}

		$row   = $claim['row'];
		$cells = array();
		$keys  = array();
		$owned = self::owned_by_offer( $record );
		$typed = array();
		$drawn = array();

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( self::typed_form( $record ) );

		// What was typed into each field that was posted and that this card writes, before the
		// cleaner has read it.
		foreach ( self::FIELDS as $key => $spec ) {
			if ( ( $owned && in_array( $key, self::OFFER_FIELDS, true ) ) || ! isset( $_POST[ 'wpcpm_' . $key ] ) ) {
				continue;
			}

			$typed[ $key ] = WPCPM_Request::posted_raw( 'wpcpm_' . $key );
		}

		// A typed text or a link posted back as it was drawn is the stored value, by the card's own test
		// of an untouched box (`unchanged()`), asked of what was typed. It is neither asked about losses
		// nor cleaned and written: the base can hold a tag or a percent octet the cleaner would take,
		// or a link the card would complete or refuse, written in its grid, and only what a person
		// changed is theirs to be told about. Cleaned and written back, it would lose those words
		// without a word said.
		foreach ( $typed as $key => $value ) {
			$kind = self::FIELDS[ $key ]['kind'];

			if ( in_array( $kind, array( 'line', 'text', 'url' ), true ) && self::unchanged( $kind, $value, isset( $row[ $key ] ) ? (string) $row[ $key ] : '' ) ) {
				$drawn[ $key ] = true;
			}
		}

		// A typed text the cleaner would take words from is refused before any field is cleaned, and
		// the form gets back what was typed: saved, it would lose the words without a word said.
		foreach ( $typed as $key => $value ) {
			$kind = self::FIELDS[ $key ]['kind'];

			if ( ! isset( $drawn[ $key ] ) && in_array( $kind, array( 'line', 'text' ), true ) && WPCPM_Typed_Text::cleaner_loses( $value, 'text' === $kind ? 'lines' : 'line' ) ) {
				self::keep_typing( $record, $typed, $row );
				self::leave( 'profile-loss', $record, WPCPM_Typed_Text::loss_message( self::labels()[ $key ] ) );
			}
		}

		foreach ( self::FIELDS as $key => $spec ) {
			// The primary offer writes these three cells on every save of the Offers card, so a
			// value posted here would be overwritten by the next one and read as "it did not
			// save" (finding 2). Ignored, never written, and the card draws them read-only.
			if ( $owned && in_array( $key, self::OFFER_FIELDS, true ) ) {
				continue;
			}

			if ( ! isset( $_POST[ 'wpcpm_' . $key ] ) || isset( $drawn[ $key ] ) ) {
				continue;
			}

			// A text area is read with its line breaks, and a link as typed: the one-line cleaner
			// removes every `%XX`, which would rewrite an address in another alphabet or a query
			// with a space in it, and clean() runs a link through clean_url(), whose esc_url_raw()
			// keeps them. Every other field is read as one line.
			if ( 'text' === $spec['kind'] ) {
				$posted = WPCPM_Request::posted_lines( 'wpcpm_' . $key );
			} elseif ( 'url' === $spec['kind'] ) {
				$posted = WPCPM_Request::posted_verbatim( 'wpcpm_' . $key );
			} else {
				$posted = WPCPM_Request::posted_text( 'wpcpm_' . $key );
			}

			$cleaned = self::clean( $key, $posted );
			$current = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';

			if ( ! $cleaned['ok'] ) {
				if ( isset( $cleaned['over'] ) ) {
					// Over its limit and read as the stored text: the base holds it so, written in
					// its grid, which bounds neither column, and the box posted it back untouched.
					// A save that did not touch it is not refused for it.
					if ( self::unchanged( $spec['kind'], (string) $cleaned['value'], $current ) ) {
						continue;
					}

					// To be shortened, so the form comes back with what was typed.
					self::keep_typing( $record, $typed, $row );
					self::leave( 'profile-long', $record, self::long_sentence( $key, $cleaned['over'] ) );
				}

				self::leave( 'profile-rejected', $record );
			}

			$next = null === $cleaned['value'] ? '' : (string) $cleaned['value'];

			if ( self::unchanged( $spec['kind'], $next, $current ) ) {
				continue;
			}

			$cells[ $spec['name'] ] = $cleaned['value'];
			$keys[]                 = $key;
		}

		if ( empty( $cells ) ) {
			self::leave( 'profile-unchanged', $record );
		}

		$airtable = new WPCPM_Airtable();
		$written  = $airtable->update_records(
			(string) WPCPM_Settings::get_value( 'sponsors_table', '' ),
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		if ( is_wp_error( $written ) ) {
			self::leave( 'profile-failed', $record );
		}

		$patch = array();

		foreach ( $keys as $key ) {
			$patch[ $key ] = null === $cells[ self::FIELDS[ $key ]['name'] ] ? '' : (string) $cells[ self::FIELDS[ $key ]['name'] ];
		}

		WPCPM_Sponsors_Index::patch( $record, $patch );

		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_KIND,
				'sponsor'  => $record,
				'subject'  => $record,
				'actor'    => get_current_user_id(),
				'ground'   => $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => sprintf(
					/* translators: %s: the fields changed, comma-separated. */
					__( 'Profile fields changed: %s.', 'wpcredits-program-manager' ),
					implode( ', ', $keys )
				),
				// The field names, never the values: a contact's address is the sponsor's data,
				// and the log is not the place to copy it.
				'data'     => array( 'fields' => $keys ),
			)
		);

		self::leave( 'profile-saved', $record );
	}

	/**
	 * The card.
	 *
	 * @param string $record  Sponsor record ID.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$row    = WPCPM_Sponsors_Index::row( $record );
		$row    = is_array( $row ) ? $row : WPCPM_Sponsors_Index::empty_row();
		$labels = self::labels();
		$open   = isset( $context['open'] ) && self::CARD === $context['open'];
		$owned  = self::owned_by_offer( $record );
		$said   = false;
		// A refused save: each field is drawn as it was left, in the room its box had.
		$kept = WPCPM_Typed_Text::kept( self::typed_form( $record ) );

		printf( '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-%1$s" class="wpcpm-group wpcpm-group__disclosure"%2$s>', esc_attr( self::CARD ), $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%s</h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Your company profile and logo', 'wpcredits-program-manager' )
		);
		echo '<div class="wpcpm-group__body">';

		// Two columns (owner, 1.96.7): the company's details on the left, its logo on the right,
		// the way the Student Report Card's course section keeps its two errands side by side.
		echo '<div class="wpcpm-profile__cols"><div class="wpcpm-profile__col wpcpm-profile__col--details">';

		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );

		foreach ( self::FIELDS as $key => $spec ) {
			$id    = 'wpcpm-profile-' . $key;
			$value = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
			$typed = isset( $kept[ $key ] ) && is_string( $kept[ $key ] ) ? $kept[ $key ] : null;

			if ( $owned && in_array( $key, self::OFFER_FIELDS, true ) ) {
				// Said once, above the first of the three, rather than three times.
				if ( ! $said ) {
					echo '<p class="wpcpm-student__note">' . esc_html__( "Your offer's text, instructions and link are edited on the Offers card: that is the offer students see.", 'wpcredits-program-manager' ) . '</p>';

					$said = true;
				}

				printf(
					'<p class="wpcpm-sponsor__field wpcpm-sponsor__field--owned"><span>%1$s</span> <strong>%2$s</strong></p>',
					esc_html( $labels[ $key ] ),
					esc_html( '' !== $value ? $value : __( 'Not set', 'wpcredits-program-manager' ) )
				);

				continue;
			}

			echo '<p class="wpcpm-sponsor__field">';
			printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $labels[ $key ] ) );

			if ( 'select' === $spec['kind'] ) {
				printf( '<select id="%1$s" name="wpcpm_%2$s">', esc_attr( $id ), esc_attr( $key ) );
				$chosen = null !== $typed ? $typed : $value;
				printf( '<option value=""%s>%s</option>', selected( '', $chosen, false ), esc_html__( 'Not set', 'wpcredits-program-manager' ) );
				foreach ( self::CHOICES[ $spec['name'] ] as $choice ) {
					printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $choice ), selected( $choice, $chosen, false ) );
				}
				echo '</select>';
			} elseif ( 'text' === $spec['kind'] ) {
				// Drawn as the person typed it, with room for each ">" held as `&gt;`
				// (`WPCPM_Typed_Text`): drawn as stored, the box would show the cleaner's entities. After a
				// refused save, drawn with what was typed, exactly, in the same room.
				printf(
					'<textarea id="%1$s" name="wpcpm_%2$s" rows="4" maxlength="%3$d">%4$s</textarea>',
					esc_attr( $id ),
					esc_attr( $key ),
					(int) WPCPM_Typed_Text::drawn_limit( $value, self::MAX_TEXT ),
					// The parser drops one line feed right after the opening tag, so a kept typing,
					// which can begin with a line break, is given one of its own first.
					null !== $typed ? "\n" . esc_textarea( $typed ) : esc_textarea( WPCPM_Typed_Text::typed_text( $value ) )
				);
			} else {
				// A one-line text is drawn as the person typed it, with room for each ">" held as
				// `&gt;` (`WPCPM_Typed_Text`): drawn as stored, "We <3 our team > all" would post
				// back as "We all". A link and an address are not typed text the cleaner escapes. After a
				// refused save, any of them is drawn with what was typed, exactly
				// (`WPCPM_Typed_Text::kept_attr()`), in the same room.
				$line  = 'line' === $spec['kind'];
				$shown = null !== $typed ? WPCPM_Typed_Text::kept_attr( $typed ) : ( $line ? WPCPM_Typed_Text::attr_text( $value ) : $value );

				printf(
					'<input type="%1$s" id="%2$s" name="wpcpm_%3$s" value="%4$s" maxlength="%5$d" />',
					esc_attr( 'email' === $spec['kind'] ? 'email' : ( 'url' === $spec['kind'] ? 'url' : 'text' ) ),
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( $shown ),
					(int) ( $line ? WPCPM_Typed_Text::drawn_limit( $value, self::MAX_LINE ) : self::MAX_LINE )
				);
			}

			if ( 'contact_email' === $key ) {
				echo '<span class="wpcpm-student__note">' . esc_html__( 'This changes the address Airtable holds for your company. It does not change who can sign in: ask your program contact for that.', 'wpcredits-program-manager' ) . '</span>';
			}

			echo '</p>';
		}

		printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Save profile', 'wpcredits-program-manager' ) );
		echo '</form>';
		echo '</div>';

		// The logo, in the same card as the profile (owner, 1.96.6), in the right-hand column
		// (1.96.7): one section for what the company looks like to students and mentors, the
		// words on the left and the picture on the right.
		if ( class_exists( 'WPCPM_Sponsor_Logo' ) && method_exists( 'WPCPM_Sponsor_Logo', 'render_inner' ) ) {
			echo '<div class="wpcpm-profile__col wpcpm-profile__col--logo">';
			printf( '<h3 class="wpcpm-student__heading wpcpm-profile__logo-title">%s</h3>', esc_html__( 'Your logo', 'wpcredits-program-manager' ) );
			WPCPM_Sponsor_Logo::render_inner( $record );
			echo '</div>';
		}

		echo '</div>';
		echo '</div></details></section>';
	}

	/**
	 * Flash and go back to the dashboard. Through the dashboard's own method, by array
	 * callable: the class lands in the same release, and `bin/check-references.php` would
	 * otherwise flag a call to a method that is not declared yet.
	 *
	 * @param string $status A key of `messages()`.
	 * @param string $record The sponsor, for the manager switcher; '' to land on the page as is.
	 * @param string $detail A sentence after the status's own, or ''.
	 */
	private static function leave( $status, $record, $detail = '' ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD, $record, $detail );
		exit;
	}
}
