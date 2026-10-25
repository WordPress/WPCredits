<?php
/**
 * The Sponsor Dashboard's profile card: the fields a sponsor keeps up to date about their company.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An allowlist, cleaned field by field, written to the store in one call with an audit row.
 *
 * Design spec of 4 September 2026, section 5.5. Single selects are validated byte for byte
 * against `CHOICES`, which bin/test-sponsor-cards.php pins word for word, so a stray value here
 * never diverges from what a form offers. `FIELDS` is the record's profile, whose rules the
 * Sponsors screen's Edit form cleans its own boxes by (`clean()`); `FORM` is what this card draws
 * and saves. The type of product left `FORM` in 1.123.22: the application form no longer asks it
 * and nothing public shows it, so a member is neither shown it nor asked it, and a save never reads
 * it, so it never clears it. It stays on the record, on the Sponsors screen and in what
 * Administrators read. One rejected field rejects the whole save,
 * for the same reason a form with one broken control is not half-submitted: what is refused is
 * refused as a whole. Changing `Contact Email` changes the address the program holds and
 * nothing about accounts, and the form says so.
 *
 * The offer's text, instructions and link are the primary offer's now (WPCPM_Sponsor_Offers),
 * not this card's: once a sponsor has any offer, this card says where they are edited, rather than
 * being a third place to edit the same words (final review of Phase S2, finding 2). Since 1.123.22
 * it says so in its first note, not between two fields, where a note read as the next field's.
 *
 * The description for the partner page (1.112.0) is a tier benefit rather than one of FIELDS:
 * drawn and read for every tier from Supporter up, the tiers the Partners page prints it for in
 * one format (1.123.18), and only while the tier ladder's launch switch is on. It is measured, drawn and compared by the rules
 * every box of typed text uses (`WPCPM_Typed_Text::typed_length()`, `typed_text()`,
 * `drawn_limit()` and `same_lines()`), and so are "Anything else you'd like to share.", the one
 * text box among FIELDS, and the Contact person, so what a sponsor types into any of these boxes
 * is counted and shown back the same way. Each of the three is refused, rather than saved short,
 * when the cleaner would take words from it, and comes back as typed.
 */
final class WPCPM_Sponsor_Profile {

	const ACTION_SAVE = 'wpcpm_sponsor_profile_save';
	const CARD        = 'profile';
	const MAX_TEXT    = 4000;
	const MAX_LINE    = 200;
	const LOG_KIND    = 'profile_saved';

	/** The description for the partner page, at most this many characters (1.112.0). */
	const DESCRIPTION_MAX = 300;

	/** Index key => the field's name and kind. The order is the form's. */
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
		'anything'       => array(
			'name' => "Anything else you'd like to share.",
			'kind' => 'text',
		),
	);

	/** The single selects' choices, spelled exactly as the Sponsors screen's Edit form offers them. */
	const CHOICES = array(
		'Type of product' => array( 'Hosting', 'Plugin', 'Service' ),
	);

	/**
	 * The keys of FIELDS this card draws and saves, in the form's order: every one but the type of
	 * product (1.123.22), which the Sponsors screen's Edit form keeps.
	 */
	const FORM = array( 'website', 'contact_person', 'contact_email', 'anything' );

	/**
	 * The handler.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * The labels, translated at call time: each of FORM's, and the description for the sponsor
	 * listing's, which a refusal names.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			'website'        => __( 'Website', 'wpcredits-program-manager' ),
			'contact_person' => __( 'Contact person', 'wpcredits-program-manager' ),
			'contact_email'  => __( 'Contact email', 'wpcredits-program-manager' ),
			'anything'       => __( 'Anything else you would like to share', 'wpcredits-program-manager' ),
			'description'    => __( 'Description for the partner page', 'wpcredits-program-manager' ),
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
			'profile-saved'     => array( 'success', __( 'Your profile was saved.', 'wpcredits-program-manager' ) ),
			'profile-unchanged' => array( 'info', __( 'Nothing changed.', 'wpcredits-program-manager' ) ),
			'profile-rejected'  => array( 'error', __( 'One of the values could not be accepted, so nothing was saved: check the website and the contact email.', 'wpcredits-program-manager' ) ),
			'profile-text-long' => array(
				'error',
				sprintf(
					/* translators: %d: the most characters Anything else you would like to share holds. */
					_n( 'The text for Anything else you would like to share is longer than %d character, so nothing was saved.', 'The text for Anything else you would like to share is longer than %d characters, so nothing was saved.', self::MAX_TEXT, 'wpcredits-program-manager' ),
					self::MAX_TEXT
				),
			),
			'profile-too-long'  => array(
				'error',
				sprintf(
					/* translators: %d: the most characters the description for the partner page holds. */
					_n( 'The description for the partner page is longer than %d character, so nothing was saved.', 'The description for the partner page is longer than %d characters, so nothing was saved.', self::DESCRIPTION_MAX, 'wpcredits-program-manager' ),
					self::DESCRIPTION_MAX
				),
			),
			// The sentence after it names the field and how far over it is (long_sentence()).
			'profile-long'      => array( 'error', __( 'Nothing was saved.', 'wpcredits-program-manager' ) ),
			'profile-failed'    => array( 'error', __( 'Your profile could not be saved right now. Try again later.', 'wpcredits-program-manager' ) ),
			'refused'           => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The three details the card's row counts: every one of FORM but "Anything else you'd like to
	 * share.", which is there for a sponsor with something more to say, so a sponsor with nothing
	 * else to share still has a complete profile. The type of product, which the card no longer asks
	 * (1.123.22), is not one of them, so a profile without one is complete. The form requires none.
	 *
	 * @return string[] Keys of FIELDS, in the form's order.
	 */
	public static function details() {
		return array_values( array_diff( self::FORM, array( 'anything' ) ) );
	}

	/**
	 * How many of the three details (`details()`) a row holds something in: a value of spaces holds
	 * nothing. The card's row says it, "Profile complete" once every one is filled. Neither the
	 * optional "Anything else you'd like to share." nor the description for the partner page, a
	 * tier's benefit and none of the five FIELDS, is counted, and nor is the type of product.
	 *
	 * @param array $row A sponsor's row.
	 * @return int
	 */
	public static function filled( array $row ) {
		$filled = 0;

		foreach ( self::details() as $key ) {
			if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
				++$filled;
			}
		}

		return $filled;
	}

	/**
	 * How many of the three details (`details()`) a row holds nothing in: `filled()` turned round, the
	 * figure the Sponsor Dashboard's At a glance strip counts for a company profile that is not
	 * complete, never below zero.
	 *
	 * @param array $row A sponsor's row.
	 * @return int
	 */
	public static function missing( array $row ) {
		return max( 0, count( self::details() ) - self::filled( $row ) );
	}

	/**
	 * Whether the sponsor has any offer yet, wherever its text now lives.
	 *
	 * Guarded by class_exists(): the Offers module is a later phase's file and this card is
	 * loaded on its own by bin/test-sponsor-cards.php, so an absent Offers class reads as no
	 * offer, and the note stays off.
	 *
	 * @param string $record Sponsor key.
	 * @return bool
	 */
	private static function has_offer( $record ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Offers' ) || ! method_exists( 'WPCPM_Sponsor_Offers', 'offers_of' ) ) {
			return false;
		}

		return ! empty( WPCPM_Sponsor_Offers::offers_of( $record ) );
	}

	/**
	 * Whether the description for the partner page is this sponsor's to write: every tier from
	 * Supporter up, the tiers the Partners page prints a description under (the listing's line()
	 * asks the ladder's same `description` benefit), and only while the launch switch is on, since
	 * until then nothing a sponsor sees names a tier. The effective tier, so a term that has ended
	 * takes the box away with it the day after its last day, when the sponsor reads as a Friend. A
	 * description saved before stays on the row, neither drawn nor replaced here nor printed on the
	 * page while the sponsor is a Friend.
	 *
	 * @param array $row The sponsor's row.
	 * @return bool
	 */
	private static function shows_description( array $row ) {
		return WPCPM_Sponsor_Tiers::live() && WPCPM_Sponsor_Tiers::includes( WPCPM_Sponsor_Tiers::effective( $row ), 'description' );
	}

	/**
	 * Clean one posted value by its field's kind.
	 *
	 * A typed text, one line (the Contact person) or a text area (Anything else you would like to
	 * share), is counted as the person typed it (`WPCPM_Typed_Text::typed_length()`): the entities
	 * the cleaner writes for a "<" and the quote marks and ampersands after it, and a line break's
	 * two bytes, count as the one character each stands for. One over its limit (`limit_of()`) is
	 * refused rather than cut: a name cut short is a different name, and a text cut short reaches
	 * the program missing its end.
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

		// A typed text, the Contact person or Anything else you would like to share. A text area
		// keeps its lines as the box shows them, each break one LF.
		$value = 'text' === $kind ? str_replace( array( "\r\n", "\r" ), "\n", sanitize_textarea_field( $raw ) ) : sanitize_text_field( $raw );
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
	 * `MAX_LINE` for one line, and `DESCRIPTION_MAX` for the description for the partner page.
	 *
	 * @param string $key A key of `FIELDS`, or `description`.
	 * @return int
	 */
	private static function limit_of( $key ) {
		if ( 'description' === $key ) {
			return self::DESCRIPTION_MAX;
		}

		return isset( self::FIELDS[ $key ] ) && 'text' === self::FIELDS[ $key ]['kind'] ? self::MAX_TEXT : self::MAX_LINE;
	}

	/**
	 * A posted field's kind: its own in FIELDS, and `text` for the description for the sponsor
	 * listing, a text area that is none of them.
	 *
	 * @param string $key A key of `FIELDS`, or `description`.
	 * @return string
	 */
	private static function kind_of( $key ) {
		return isset( self::FIELDS[ $key ] ) ? self::FIELDS[ $key ]['kind'] : 'text';
	}

	/**
	 * The form, as `WPCPM_Typed_Text::keep()` names it: one sponsor's profile card.
	 *
	 * @param string $record Sponsor key.
	 * @return string
	 */
	private static function typed_form( $record ) {
		return 'profile:' . $record;
	}

	/**
	 * Keep what a refused profile form held for the one time it is drawn again: every field that was
	 * posted, as typed, each up to the room its box was drawn with (`limit_of()`, and never less than
	 * the value the box was drawn with, so a text the row holds over its limit comes back whole).
	 *
	 * @param string $record Sponsor key.
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
	 * area's every break as CR LF where the row keeps LF. Read the same, it is the stored text,
	 * and is not written. A link is the stored value byte for byte, or with each line break as a
	 * space, as below. Every other kind is the stored value only byte for byte.
	 *
	 * Both sides are read trimmed, as the cleaner trims what it reads. A value brought over from the
	 * program records can end in a space or begin with a line break, and a box can hand it back
	 * without them: a text area drops a line break its text begins with, and a link's or an
	 * address's box drops the spaces at its ends. Such a box, posted back untouched, is still the
	 * stored value, and a save would only take the white space off.
	 *
	 * A one-line text or a link is drawn with each line break its stored value holds as a space
	 * (`WPCPM_Typed_Text::one_line()`), so one posted back as it was drawn is the stored value too.
	 * An address is drawn as stored, and is not asked so: it comes here as `sanitize_email()` hands
	 * it back, which holds no space. Its box shows it with each entity read, so an address stored
	 * with one, `&amp;` say, comes back as the character the entity stands for, and is the stored
	 * value when the two read the same once each entity is read (`WPCPM_Typed_Text::same_text()`).
	 * The Sponsors screen's Edit form reads these boxes by the same rules (`WPCPM_Sponsors`), so a
	 * box left as either screen drew it changes nothing on either.
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
			return WPCPM_Typed_Text::same_text( $next, $current ) || WPCPM_Typed_Text::same_text( $next, WPCPM_Typed_Text::one_line( $current ) );
		}

		if ( 'url' === $kind ) {
			return WPCPM_Typed_Text::one_line( $current ) === $next;
		}

		if ( 'email' === $kind ) {
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
	 * in the form's order, the description for the partner page last, then every other posted
	 * field cleaned, then one patch of the sponsor's row with the fields that changed, then the log.
	 * Only the fields of FORM that were posted are read, so a form missing one leaves it alone, a
	 * type of product posted by hand or from a page drawn before 1.123.22 is never read and the
	 * stored one stays, and a typed text or a link posted back as it was drawn is left as the row
	 * holds it. A save refused for a loss or a length keeps what was typed for the form's redraw, the
	 * description's among it.
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
		$typed = array();
		$drawn = array();

		// What an earlier refusal of this form kept is stale once the form is posted again.
		WPCPM_Typed_Text::forget( self::typed_form( $record ) );

		// What was typed into each field of the form that was posted, before the cleaner has read it.
		foreach ( self::FORM as $key ) {
			if ( ! isset( $_POST[ 'wpcpm_' . $key ] ) ) {
				continue;
			}

			$typed[ $key ] = WPCPM_Request::posted_raw( 'wpcpm_' . $key );
		}

		// The description for the partner page is read only while its box is drawn (1.112.0).
		if ( isset( $_POST['wpcpm_description'] ) && self::shows_description( $row ) ) {
			$typed['description'] = WPCPM_Request::posted_raw( 'wpcpm_description' );
		}

		// A typed text or a link posted back as it was drawn is the stored value, by the card's own test
		// of an untouched box (`unchanged()`), asked of what was typed. It is neither asked about losses
		// nor cleaned and written: the row can hold a tag or a percent octet the cleaner would take, or
		// a link the card would complete or refuse, brought over from the program records or saved
		// before these rules, and only what a person changed is theirs to be told about. Cleaned and
		// written back, it would lose those words without a word said.
		foreach ( $typed as $key => $value ) {
			$kind = self::kind_of( $key );

			if ( in_array( $kind, array( 'line', 'text', 'url' ), true ) && self::unchanged( $kind, $value, isset( $row[ $key ] ) ? (string) $row[ $key ] : '' ) ) {
				$drawn[ $key ] = true;
			}
		}

		// A typed text the cleaner would take words from is refused before any field is cleaned, and
		// the form gets back what was typed: saved, it would lose the words without a word said.
		foreach ( $typed as $key => $value ) {
			$kind = self::kind_of( $key );

			if ( ! isset( $drawn[ $key ] ) && in_array( $kind, array( 'line', 'text' ), true ) && WPCPM_Typed_Text::cleaner_loses( $value, 'text' === $kind ? 'lines' : 'line' ) ) {
				self::keep_typing( $record, $typed, $row );
				self::leave( 'profile-loss', $record, WPCPM_Typed_Text::loss_message( self::labels()[ $key ] ) );
			}
		}

		foreach ( self::FORM as $key ) {
			if ( ! isset( $_POST[ 'wpcpm_' . $key ] ) || isset( $drawn[ $key ] ) ) {
				continue;
			}

			$spec = self::FIELDS[ $key ];

			// A text box keeps its line breaks, and a link is read as typed: the one-line cleaner
			// removes every `%XX`, which would rewrite an address in another alphabet or a query
			// with a space in it, and clean() runs a link through clean_url(), whose esc_url_raw()
			// keeps them. Every other field is one line.
			if ( 'text' === $spec['kind'] ) {
				$posted = WPCPM_Request::posted_lines( 'wpcpm_' . $key );
			} elseif ( 'url' === $spec['kind'] ) {
				$posted = WPCPM_Request::posted_verbatim( 'wpcpm_' . $key );
			} else {
				$posted = WPCPM_Request::posted_text( 'wpcpm_' . $key );
			}

			$cleaned = self::clean( $key, $posted );
			$current = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
			$next    = null === $cleaned['value'] ? '' : (string) $cleaned['value'];

			// A typed text is drawn read back to what was typed, so it is compared as it reads
			// (`unchanged()`): posted back unedited it holds the same text once each entity is read
			// and each line break is one LF, though the cleaner may write a ">" the box held after a
			// tag-like "<" as `&gt;` where the row holds ">", and the row may hold a break as CR LF.
			// It is compared before it is measured, so only a changed text can be refused: one a row
			// kept longer than its limit from an earlier store stays as it is, and does not stop a
			// save of the other fields.
			if ( ( 'text' === $spec['kind'] || 'line' === $spec['kind'] ) && self::unchanged( $spec['kind'], $next, $current ) ) {
				continue;
			}

			// A text box is refused for its length alone, and the flash says so; a line over its
			// limit is named, with how far over it is (`long_sentence()`). Either is to be shortened,
			// so the form comes back with what was typed.
			if ( ! $cleaned['ok'] ) {
				if ( 'text' === $spec['kind'] ) {
					self::keep_typing( $record, $typed, $row );
					self::leave( 'profile-text-long', $record );
				}

				if ( isset( $cleaned['over'] ) ) {
					self::keep_typing( $record, $typed, $row );
					self::leave( 'profile-long', $record, self::long_sentence( $key, $cleaned['over'] ) );
				}

				self::leave( 'profile-rejected', $record );
			}

			if ( self::unchanged( $spec['kind'], $next, $current ) ) {
				continue;
			}

			$cells[ $spec['name'] ] = $cleaned['value'];
			$keys[]                 = $key;
		}

		// The description for the partner page is not one of FIELDS: it is read only while the
		// box is drawn, so a value posted by hand while it is not is ignored as the box is absent
		// (1.112.0), and one posted back as it was drawn is left as the row holds it. Its line breaks
		// are kept, the way the box shows them.
		$extra = array();

		if ( isset( $typed['description'] ) && ! isset( $drawn['description'] ) ) {
			// A browser sends each line break in the box as CRLF, and every break is stored as one
			// LF, as the box shows it. The text cleaner writes a "<" that opens no tag, and the quote
			// marks and the ampersand after it, as entities, so a description that fits its box can
			// be stored longer than DESCRIPTION_MAX: the limit counts what was typed, as the box
			// counts it, and a longer one refuses the save as a whole rather than being cut, which
			// would drop its last words or end inside an entity. The row is post meta, which kses
			// never reads, so the description is stored as the cleaner left it.
			$next = str_replace( array( "\r\n", "\r" ), "\n", sanitize_textarea_field( WPCPM_Request::posted_lines( 'wpcpm_description' ) ) );

			if ( WPCPM_Typed_Text::typed_length( $next ) > self::DESCRIPTION_MAX ) {
				self::keep_typing( $record, $typed, $row );
				self::leave( 'profile-too-long', $record );
			}

			// Read as the box reads it back: posted back unedited, a description holds the same
			// text once each entity is read, though the cleaner may write a ">" the box held after a
			// tag-like "<" as `&gt;` where the row holds ">", so it changes nothing.
			if ( ! WPCPM_Typed_Text::same_lines( $next, (string) $row['description'] ) ) {
				$extra['description'] = $next;
			}
		}

		if ( empty( $cells ) && empty( $extra ) ) {
			self::leave( 'profile-unchanged', $record );
		}

		$patch = array();

		foreach ( $keys as $key ) {
			$patch[ $key ] = null === $cells[ self::FIELDS[ $key ]['name'] ] ? '' : (string) $cells[ self::FIELDS[ $key ]['name'] ];
		}

		// Merged after the loop, which reads each key's value from FIELDS; the audit names the
		// description among the changed fields as it names the others.
		$patch = array_merge( $patch, $extra );
		$keys  = array_merge( $keys, array_keys( $extra ) );

		if ( ! WPCPM_Sponsors_Index::patch( $record, $patch ) ) {
			self::leave( 'profile-failed', $record );
		}

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
	 * The card, Company profile and logo. Folded until a flash names it, its row how much of the
	 * profile is filled in, and while it is folded Edit after the fold, a link to the card; its first
	 * line says that what the company saves here is the program's record of it, since the group's lead
	 * is one sentence for its three cards (the design the owner approved on 27 September 2026).
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$row    = WPCPM_Sponsors_Index::row( $record );
		$row    = is_array( $row ) ? $row : WPCPM_Sponsors_Index::empty_row();
		$labels = self::labels();
		$open   = isset( $context['open'] ) && self::CARD === $context['open'];
		$filled = self::filled( $row );
		$fields = count( self::details() );
		// A refused save: each field is drawn as it was left, in the room its box had.
		$kept = WPCPM_Typed_Text::kept( self::typed_form( $record ) );

		WPCPM_Dashboard_Card::open(
			array(
				'key'    => self::CARD,
				'title'  => __( 'Company profile and logo', 'wpcredits-program-manager' ),
				'open'   => $open,
				'state'  => $filled >= $fields
					? __( 'Profile complete', 'wpcredits-program-manager' )
					: sprintf(
						/* translators: 1: how many of the profile's details hold a value, 2: how many details the row counts, three. */
						_n( '%1$s of %2$s field filled', '%1$s of %2$s fields filled', $fields, 'wpcredits-program-manager' ),
						number_format_i18n( $filled ),
						number_format_i18n( $fields )
					),
				'action' => array(
					'label'   => __( 'Edit', 'wpcredits-program-manager' ),
					/* translators: read after "Edit" by a screen reader alone. */
					'context' => __( 'the company profile and logo', 'wpcredits-program-manager' ),
				),
			)
		);

		// One paragraph: what the program keeps, and, once the sponsor has an offer, where the offer's
		// words are edited, which stood between two fields, where it read as the next field's, until
		// 1.123.22.
		printf(
			'<p class="wpcpm-student__note">%1$s%2$s</p>',
			esc_html__( 'Keep the profile current: what you save here is kept by the program.', 'wpcredits-program-manager' ),
			self::has_offer( $record ) ? ' ' . esc_html__( "Your offer's text, instructions and link are edited on the Offers card.", 'wpcredits-program-manager' ) : ''
		);

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

		foreach ( self::FORM as $key ) {
			$spec  = self::FIELDS[ $key ];
			$id    = 'wpcpm-profile-' . $key;
			$value = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
			$typed = isset( $kept[ $key ] ) && is_string( $kept[ $key ] ) ? $kept[ $key ] : null;

			echo '<p class="wpcpm-sponsor__field">';
			printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $labels[ $key ] ) );

			if ( 'text' === $spec['kind'] ) {
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
				// back as "We all". A link and an address are not typed text the cleaner escapes. A
				// link has each "&" written `&amp;` (`WPCPM_Typed_Text::kept_attr()`), since
				// `esc_attr()` keeps an entity it finds and the box would post back the character in
				// place of a stored entity. Each line break a stored text or link holds is drawn as a
				// space (`WPCPM_Typed_Text::one_line()`), which the box would otherwise drop. An
				// address is drawn as stored, so the box shows it with each entity read, and its box
				// drops a break: a space there would be an address that is not one, which the browser
				// refuses, and the whole save with it. The Sponsors screen's Edit form draws these
				// boxes by the same rules (`WPCPM_Sponsors`). After a refused save, any of them is
				// drawn with what was typed, exactly (`WPCPM_Typed_Text::kept_attr()`), in the same
				// room.
				if ( null !== $typed ) {
					$shown = WPCPM_Typed_Text::kept_attr( $typed );
				} elseif ( 'email' === $spec['kind'] ) {
					$shown = $value;
				} elseif ( 'url' === $spec['kind'] ) {
					$shown = WPCPM_Typed_Text::one_line( WPCPM_Typed_Text::kept_attr( $value ) );
				} else {
					$shown = WPCPM_Typed_Text::one_line( WPCPM_Typed_Text::attr_text( $value ) );
				}

				$line = 'line' === $spec['kind'];

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
				echo '<span class="wpcpm-student__note">' . esc_html__( 'This changes the address the program holds for your company. It does not change who can sign in: ask your program contact for that.', 'wpcredits-program-manager' ) . '</span>';
			}

			echo '</p>';
		}

		// A Supporter's alone, while the launch switch is on: see shows_description(). The note
		// names the number DESCRIPTION_MAX holds. The stored description holds the entities the
		// text cleaner wrote, read back to what was typed before the box escapes it, by the rule
		// every box of typed text draws its own with (`WPCPM_Typed_Text`). After a refused save, it
		// is drawn with what was typed, exactly, in the same room, after a line feed of its own, as
		// the parser drops the one right after the opening tag.
		if ( self::shows_description( $row ) ) {
			$described = isset( $kept['description'] ) && is_string( $kept['description'] ) ? $kept['description'] : null;

			printf(
				'<p class="wpcpm-sponsor__field"><label for="wpcpm-profile-description">%1$s</label><textarea id="wpcpm-profile-description" name="wpcpm_description" rows="3" maxlength="%2$d">%3$s</textarea><span class="wpcpm-student__note">%4$s</span></p>',
				esc_html( $labels['description'] ),
				(int) WPCPM_Typed_Text::drawn_limit( (string) $row['description'], self::DESCRIPTION_MAX ),
				null !== $described ? "\n" . esc_textarea( $described ) : esc_textarea( WPCPM_Typed_Text::typed_text( (string) $row['description'] ) ),
				esc_html__( 'A sentence or two about your company, shown with your logo where the program lists its sponsors. 300 characters at most.', 'wpcredits-program-manager' )
			);
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
		WPCPM_Dashboard_Card::close();
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
