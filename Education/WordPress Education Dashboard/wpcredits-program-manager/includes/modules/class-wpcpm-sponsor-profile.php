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
			'profile-saved'     => array( 'success', __( 'Your profile was saved to the program records.', 'wpcredits-program-manager' ) ),
			'profile-unchanged' => array( 'info', __( 'Nothing changed.', 'wpcredits-program-manager' ) ),
			'profile-rejected'  => array( 'error', __( 'One of the values could not be accepted, so nothing was saved: check the links and the product type.', 'wpcredits-program-manager' ) ),
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
	 * @param string $key A key of `FIELDS`.
	 * @param string $raw The posted value.
	 * @return array `ok` (bool) and `value` (the cleaned value, or null to clear a select).
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
			case 'text':
				return array(
					'ok'    => true,
					'value' => mb_substr( sanitize_textarea_field( $raw ), 0, self::MAX_TEXT ),
				);
		}

		return array(
			'ok'    => true,
			'value' => mb_substr( sanitize_text_field( $raw ), 0, self::MAX_LINE ),
		);
	}

	/**
	 * Save the profile.
	 *
	 * The nonce, then the claim (which decides `ACT_EDIT_PROFILE` and meters a refusal), then
	 * every posted field cleaned, then one PATCH of the cells that changed, then the index and
	 * the log. Only fields that were posted are read, so a form missing one leaves it alone.
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

		foreach ( self::FIELDS as $key => $spec ) {
			// The primary offer writes these three cells on every save of the Offers card, so a
			// value posted here would be overwritten by the next one and read as "it did not
			// save" (finding 2). Ignored, never written, and the card draws them read-only.
			if ( $owned && in_array( $key, self::OFFER_FIELDS, true ) ) {
				continue;
			}

			if ( ! isset( $_POST[ 'wpcpm_' . $key ] ) ) {
				continue;
			}

			$cleaned = self::clean( $key, WPCPM_Request::posted_text( 'wpcpm_' . $key ) );

			if ( ! $cleaned['ok'] ) {
				self::leave( 'profile-rejected', $record );
			}

			$current = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
			$next    = null === $cleaned['value'] ? '' : (string) $cleaned['value'];

			if ( $next === $current ) {
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

		printf( '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-%1$s" class="wpcpm-group wpcpm-group__disclosure"%2$s>', esc_attr( self::CARD ), $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%s</h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Your profile', 'wpcredits-program-manager' )
		);
		echo '<div class="wpcpm-group__body">';
		echo '<p class="wpcpm-student__note">' . esc_html__( 'What the program records hold about your company. Everything here is saved to Airtable when you press Save.', 'wpcredits-program-manager' ) . '</p>';

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
				printf( '<option value=""%s>%s</option>', selected( '', $value, false ), esc_html__( 'Not set', 'wpcredits-program-manager' ) );
				foreach ( self::CHOICES[ $spec['name'] ] as $choice ) {
					printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $choice ), selected( $choice, $value, false ) );
				}
				echo '</select>';
			} elseif ( 'text' === $spec['kind'] ) {
				printf( '<textarea id="%1$s" name="wpcpm_%2$s" rows="4" maxlength="%3$d">%4$s</textarea>', esc_attr( $id ), esc_attr( $key ), (int) self::MAX_TEXT, esc_textarea( $value ) );
			} else {
				printf(
					'<input type="%1$s" id="%2$s" name="wpcpm_%3$s" value="%4$s" maxlength="%5$d" />',
					esc_attr( 'email' === $spec['kind'] ? 'email' : ( 'url' === $spec['kind'] ? 'url' : 'text' ) ),
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( $value ),
					(int) self::MAX_LINE
				);
			}

			if ( 'contact_email' === $key ) {
				echo '<span class="wpcpm-student__note">' . esc_html__( 'This changes the address Airtable holds for your company. It does not change who can sign in: ask your program contact for that.', 'wpcredits-program-manager' ) . '</span>';
			}

			echo '</p>';
		}

		printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Save profile', 'wpcredits-program-manager' ) );
		echo '</form>';
		echo '</div></details></section>';
	}

	/**
	 * Flash and go back to the dashboard. Through the dashboard's own method, by array
	 * callable: the class lands in the same release, and `bin/check-references.php` would
	 * otherwise flag a call to a method that is not declared yet.
	 *
	 * @param string $status A key of `messages()`.
	 * @param string $record The sponsor, for the manager switcher; '' to land on the page as is.
	 */
	private static function leave( $status, $record ) {
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD, $record );
		exit;
	}
}
