<?php
/**
 * "Tools from our sponsors": the section on a person's own card, and who may claim.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sponsors design spec of 4 September 2026, sections 6.3 (may_claim) and 6.5 (the section).
 *
 * Drawn from three places by hand, because the dashboards have no registry: the Student Report
 * Card when the viewer is the student whose card it is, the Mentor Report Card when the viewer is
 * the mentor and `tools_mentors` is on, and the Administrator Dashboard for every manager. Never
 * on somebody else's view of a card: a manager looking at a student gets render_count_line(),
 * one muted line, because support needs the count and nobody needs the codes.
 *
 * Claiming a tool is not a sponsor action, so the sponsor policy is not asked: the claimant is
 * not a member of the sponsor. may_claim() is the one place that decides, on the claimant's own
 * account, and its clauses are tested one by one in bin/test-sponsor-tools.php.
 */
final class WPCPM_Sponsor_Tools {

	const ACTION_CLAIM   = 'wpcpm_claim_tool';
	const ACTION_PROBLEM = 'wpcpm_claim_problem';

	/** The flash channel: the viewer's own, taken once by render(). */
	const FLASH = 'tools';

	/** The section's id, and the anchor the handlers return to. */
	const ANCHOR = 'wpcpm-tools';

	const AUDIENCE_STUDENTS = 'students';
	const AUDIENCE_MENTORS  = 'mentors';
	const AUDIENCE_MANAGERS = 'managers';

	/**
	 * The handlers.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_CLAIM, array( __CLASS__, 'handle_claim' ) );
		add_action( 'admin_post_' . self::ACTION_PROBLEM, array( __CLASS__, 'handle_problem' ) );
	}

	/**
	 * This section's outcomes.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			'claimed'         => array( 'success', __( 'Your code is below. It is yours to keep, and it stays on this page.', 'wpcredits-program-manager' ) ),
			'claimed-again'   => array( 'info', __( 'You already have a code for that offer. It is below.', 'wpcredits-program-manager' ) ),
			'claim-refused'   => array( 'error', __( 'That offer is not open to your account.', 'wpcredits-program-manager' ) ),
			'claim-empty'     => array( 'error', __( 'That offer has run out of codes. The sponsor has been told.', 'wpcredits-program-manager' ) ),
			'claim-busy'      => array( 'info', __( 'Another request was going through. Reload the page to see your code.', 'wpcredits-program-manager' ) ),
			'claim-failed'    => array( 'error', __( 'That could not be done right now. Try again later.', 'wpcredits-program-manager' ) ),
			'problem-sent'    => array( 'success', __( 'Thank you. Your program contact has been told and will get back to you.', 'wpcredits-program-manager' ) ),
			'problem-refused' => array( 'error', __( 'You have not claimed that offer.', 'wpcredits-program-manager' ) ),
			'problem-limit'   => array( 'error', __( 'You have reported three problems today. Try again tomorrow, or write to your program contact.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Whether the section is switched on for an audience. There is no switch for managers:
	 * the Administrator Dashboard shows every live offer with its audience.
	 *
	 * @param string $audience One of the AUDIENCE_* constants.
	 * @return bool
	 */
	public static function enabled( $audience ) {
		switch ( (string) $audience ) {
			case self::AUDIENCE_STUDENTS:
				return (bool) WPCPM_Settings::get_value( 'tools_students', true );
			case self::AUDIENCE_MENTORS:
				return (bool) WPCPM_Settings::get_value( 'tools_mentors', false );
			case self::AUDIENCE_MANAGERS:
				return true;
		}

		return false;
	}

	/**
	 * A current student: holds the student role, and the status the sync cached is one of the
	 * program's student statuses and not one of its past ones (decision: alumni have no access
	 * to tools, spec section 14). A student the sync has not reached yet has no status, and is
	 * not current until it has.
	 *
	 * @param int|WP_User|null $user The person.
	 * @return bool
	 */
	public static function is_current_student( $user ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() || ! WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT ) ) {
			return false;
		}

		$program = WPCPM_Students_Sync::get_program( $user->ID );
		$status  = trim( (string) ( isset( $program['status'] ) ? $program['status'] : '' ) );

		if ( '' === $status ) {
			return false;
		}

		return in_array( $status, (array) WPCPM_Settings::get_value( 'student_statuses', array() ), true )
			&& ! in_array( $status, (array) WPCPM_Settings::get_value( 'past_statuses', array() ), true );
	}

	/**
	 * Which audience a person claims as. Managers first: a manager is staff whatever else the
	 * account holds. Then current students, then mentors. '' for everyone else.
	 *
	 * @param int|WP_User|null $user The person.
	 * @return string
	 */
	public static function kind_of( $user ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return '';
		}

		if ( user_can( $user, WPCPM_Roles::CAP_MANAGE ) ) {
			return self::AUDIENCE_MANAGERS;
		}

		if ( self::is_current_student( $user ) ) {
			return self::AUDIENCE_STUDENTS;
		}

		if ( WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_MENTOR ) ) {
			return self::AUDIENCE_MENTORS;
		}

		return '';
	}

	/**
	 * Whether a person may claim an offer: five clauses, each with a test.
	 *
	 * @param int|WP_User|null $user  The person.
	 * @param array            $offer The offer.
	 * @return bool
	 */
	public static function may_claim( $user, array $offer ) {
		return '' === self::may_claim_reason( $user, $offer );
	}

	/**
	 * Which clause said no, or '' when none did.
	 *
	 * Everything a claimant is told is still the one refusal, with one exception: a pool that
	 * emptied between the page being drawn and the button being pressed is not a fact about
	 * their account, and telling them "not open to your account" reads as a permissions problem
	 * they cannot fix (final review of Phase S2, finding 7). WPCPM_Sponsor_Claims::claim() asks
	 * for the reason so it can answer `empty` differently.
	 *
	 * @param int|WP_User|null $user  The person.
	 * @param array            $offer The offer.
	 * @return string '' when they may claim, else `nobody`, `closed`, `kind`, `claimed`, `empty` or `unshared`.
	 */
	public static function may_claim_reason( $user, array $offer ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return 'nobody';
		}

		// 1. The offer is live and not past its last day.
		if ( ! WPCPM_Sponsor_Offers::is_live( $offer ) ) {
			return 'closed';
		}

		// 2. The person is a current student, or a mentor when the offer opens to mentors, or a
		// manager when it opens to managers.
		$kind = self::kind_of( $user );

		if ( '' === $kind || ( self::AUDIENCE_STUDENTS !== $kind && ! in_array( $kind, (array) $offer['audience'], true ) ) ) {
			return 'kind';
		}

		// 3. The person has not claimed this offer.
		if ( WPCPM_Sponsor_Claims::has_claimed( $user->ID, $offer['id'] ) ) {
			return 'claimed';
		}

		// 4. For a pool, one code is available.
		if ( WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind'] && WPCPM_Sponsor_Codes::counts( $offer['id'] )['available'] < 1 ) {
			return 'empty';
		}

		// 5. For a shared offer, there is something to show.
		if ( WPCPM_Sponsor_Offers::KIND_SHARED === $offer['kind'] && '' === WPCPM_Sponsor_Codes::shared( $offer['id'] ) ) {
			return 'unshared';
		}

		return '';
	}

	/**
	 * The live offers an audience is shown, sorted by sponsor name then title.
	 *
	 * A viewer who is not of the audience's kind (a Graduate on the student page, say) is shown
	 * nothing: the offers are not open to them, and a list they cannot act on is the sheet
	 * shown to the wrong person. Students and mentors are not shown a pool with nothing left
	 * unless they already hold a code from it; managers see it with a warning. Managers see
	 * every live offer, whatever its audience, so the program can see what students are offered.
	 *
	 * @param string           $audience One of the AUDIENCE_* constants.
	 * @param int|WP_User|null $user     The viewer.
	 * @return array
	 */
	public static function offers_for( $audience, $user ) {
		$user = WPCPM_Roles::resolve_user( $user );
		$out  = array();

		if ( ! $user instanceof WP_User || ! $user->exists() || self::kind_of( $user ) !== (string) $audience ) {
			return $out;
		}

		foreach ( WPCPM_Sponsor_Offers::live() as $offer ) {
			if ( ! WPCPM_Sponsor_Offers::is_live( $offer ) ) {
				continue;
			}

			if ( self::AUDIENCE_MENTORS === $audience && ! in_array( self::AUDIENCE_MENTORS, (array) $offer['audience'], true ) ) {
				continue;
			}

			if ( self::AUDIENCE_MANAGERS !== $audience
				&& WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind']
				&& WPCPM_Sponsor_Codes::counts( $offer['id'] )['available'] < 1
				&& ! WPCPM_Sponsor_Claims::has_claimed( $user->ID, $offer['id'] ) ) {
				continue;
			}

			$out[] = $offer;
		}

		usort(
			$out,
			static function ( $a, $b ) {
				$by_sponsor = strcasecmp( self::sponsor_name( $a['sponsor'] ), self::sponsor_name( $b['sponsor'] ) );

				return 0 !== $by_sponsor ? $by_sponsor : strcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		return $out;
	}

	/**
	 * A sponsor's name, trimmed; '' when the index does not hold the record.
	 *
	 * @param string $record Sponsor record ID.
	 * @return string
	 */
	private static function sponsor_name( $record ) {
		$row = WPCPM_Sponsors_Index::row( $record );

		return is_array( $row ) ? trim( (string) $row['name'] ) : '';
	}

	/**
	 * The section, on a person's own card. Echoes nothing when the audience's switch is off or
	 * there is nothing to show: no live offer open to them, no claim of theirs, no flash
	 * (plan ruling 8).
	 *
	 * @param string           $audience One of the AUDIENCE_* constants: whose card this is.
	 * @param int|WP_User|null $viewer   The person whose card it is.
	 */
	public static function render( $audience, $viewer ) {
		if ( ! self::enabled( $audience ) ) {
			return;
		}

		$viewer = WPCPM_Roles::resolve_user( $viewer );

		if ( ! $viewer instanceof WP_User || ! $viewer->exists() ) {
			return;
		}

		$offers = self::offers_for( $audience, $viewer );
		$claims = WPCPM_Sponsor_Claims::claims_of( $viewer->ID );
		$flash  = WPCPM_Flash::take( self::FLASH, $viewer->ID );

		if ( empty( $offers ) && empty( $claims ) && empty( $flash ) ) {
			return;
		}

		printf( '<section class="wpcpm-student__section wpcpm-tools" id="%s">', esc_attr( self::ANCHOR ) );
		echo '<h3 class="wpcpm-student__heading">' . esc_html__( 'Tools from our sponsors', 'wpcredits-program-manager' ) . '</h3>';

		self::render_message( $flash );

		if ( empty( $offers ) ) {
			echo '<p class="wpcpm-student__note">' . esc_html__( 'No offer is open to you right now. What you already claimed is below.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			echo '<ul class="wpcpm-tools__list">';

			foreach ( $offers as $offer ) {
				self::render_offer( $offer, $viewer, $audience );
			}

			echo '</ul>';
		}

		self::render_codes( $viewer, $claims );

		echo '</section>';
	}

	/**
	 * The one muted line a manager sees on somebody else's card. Nothing when the count is 0.
	 *
	 * @param WP_User $person Whose card it is.
	 */
	public static function render_count_line( WP_User $person ) {
		$n = WPCPM_Sponsor_Claims::count_for_user( $person->ID );

		if ( $n < 1 ) {
			return;
		}

		printf(
			'<p class="wpcpm-tools__count wpcpm-student__note">%s</p>',
			/* translators: %d: how many tools the student claimed. */
			esc_html( sprintf( _n( '%d tool claimed', '%d tools claimed', $n, 'wpcredits-program-manager' ), $n ) )
		);
	}

	/**
	 * The one flash message on the section, if the channel is holding one.
	 *
	 * @param mixed $flash What the channel held.
	 */
	private static function render_message( $flash ) {
		$status   = is_array( $flash ) && isset( $flash['status'] ) ? sanitize_key( (string) $flash['status'] ) : '';
		$messages = self::messages();

		if ( '' === $status || ! isset( $messages[ $status ] ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-dashboard__message wpcpm-dashboard__message--%1$s">%2$s</p>',
			esc_attr( $messages[ $status ][0] ),
			esc_html( $messages[ $status ][1] )
		);
	}

	/**
	 * One offer card.
	 *
	 * @param array   $offer    The offer.
	 * @param WP_User $viewer   Whose card it is.
	 * @param string  $audience Whose card it is, as an audience.
	 */
	private static function render_offer( array $offer, WP_User $viewer, $audience ) {
		$row  = WPCPM_Sponsors_Index::row( $offer['sponsor'] );
		$row  = is_array( $row ) ? $row : WPCPM_Sponsors_Index::empty_row();
		$logo = WPCPM_Sponsors_Index::display_logo( $offer['sponsor'] );
		$name = '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : __( 'A sponsor', 'wpcredits-program-manager' );
		$site = WPCPM_Field_Value::clean_url( (string) $row['website'] );

		echo '<li class="wpcpm-tools__offer">';
		echo '<div class="wpcpm-tools__logo">';

		if ( is_array( $logo ) && ! empty( $logo['url'] ) ) {
			printf( '<img src="%s" alt="" loading="lazy" decoding="async" />', esc_url( $logo['url'] ) );
		} else {
			printf( '<span class="wpcpm-tools__initials" aria-hidden="true">%s</span>', esc_html( mb_substr( $name, 0, 1 ) ) );
		}

		echo '</div><div class="wpcpm-tools__body">';

		if ( '' !== $site ) {
			printf( '<p class="wpcpm-tools__sponsor"><a href="%1$s" rel="external noopener">%2$s</a></p>', esc_url( $site ), esc_html( $name ) );
		} else {
			printf( '<p class="wpcpm-tools__sponsor">%s</p>', esc_html( $name ) );
		}

		printf( '<h4 class="wpcpm-tools__title">%s</h4>', esc_html( $offer['title'] ) );

		if ( '' !== trim( (string) $offer['text'] ) ) {
			printf( '<p class="wpcpm-tools__text">%s</p>', esc_html( $offer['text'] ) );
		}

		if ( '' !== trim( (string) $offer['instructions'] ) ) {
			printf( '<p class="wpcpm-tools__instructions">%s</p>', nl2br( esc_html( $offer['instructions'] ) ) );
		}

		if ( '' !== (string) $offer['url'] ) {
			printf( '<p class="wpcpm-tools__more"><a href="%1$s" rel="external noopener">%2$s</a></p>', esc_url( $offer['url'] ), esc_html__( 'More information', 'wpcredits-program-manager' ) );
		}

		if ( self::AUDIENCE_MANAGERS === $audience ) {
			// Through a label map: the raw keys were printed untranslated, and "managers" is not
			// what the settings call that audience (final review of Phase S2, finding 11).
			$labels = array(
				self::AUDIENCE_STUDENTS => __( 'students', 'wpcredits-program-manager' ),
				self::AUDIENCE_MENTORS  => __( 'mentors', 'wpcredits-program-manager' ),
				self::AUDIENCE_MANAGERS => __( 'the program team', 'wpcredits-program-manager' ),
			);
			$open   = array( $labels[ self::AUDIENCE_STUDENTS ] );

			foreach ( (array) $offer['audience'] as $key ) {
				if ( isset( $labels[ (string) $key ] ) ) {
					$open[] = $labels[ (string) $key ];
				}
			}

			/* translators: %s: the audiences, comma-separated. */
			printf( '<p class="wpcpm-tools__audience wpcpm-student__note">%s</p>', esc_html( sprintf( __( 'Open to: %s', 'wpcredits-program-manager' ), implode( ', ', $open ) ) ) );
		}

		if ( WPCPM_Sponsor_Claims::has_claimed( $viewer->ID, $offer['id'] ) ) {
			self::render_code( $offer, $viewer );
			self::render_problem_form( $offer );
		} elseif ( self::may_claim( $viewer, $offer ) ) {
			self::render_claim_form( $offer );
		} elseif ( WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind'] && WPCPM_Sponsor_Codes::counts( $offer['id'] )['available'] < 1 ) {
			echo '<p class="wpcpm-tools__warning">' . esc_html__( 'No codes left. The sponsor has been told.', 'wpcredits-program-manager' ) . '</p>';
		}

		echo '</div></li>';
	}

	/**
	 * The claimant's own code, unsealed here and nowhere else. A code that is a link is a link;
	 * anything else is a selectable block forms.js selects on click.
	 *
	 * @param array   $offer  The offer.
	 * @param WP_User $viewer The claimant.
	 */
	private static function render_code( array $offer, WP_User $viewer ) {
		$code  = WPCPM_Sponsor_Claims::code_for( $viewer->ID, $offer );
		$claim = WPCPM_Sponsor_Claims::claims_of( $viewer->ID )[ $offer['id'] ];

		echo '<p class="wpcpm-tools__claimed">' . esc_html__( 'Your code:', 'wpcredits-program-manager' ) . ' ';

		if ( preg_match( '#^https?://#i', $code ) ) {
			printf( '<a class="wpcpm-tools__code" href="%1$s" rel="external noopener">%2$s</a>', esc_url( $code ), esc_html( $code ) );
		} else {
			printf( '<code class="wpcpm-tools__code" data-wpcpm-select tabindex="0">%s</code>', esc_html( $code ) );
		}

		/* translators: %s: the date. */
		printf( ' <span class="wpcpm-tools__when">%s</span>', esc_html( sprintf( __( 'claimed %s', 'wpcredits-program-manager' ), wp_date( 'Y-m-d', (int) $claim['at'] ) ) ) );
		echo '</p>';
	}

	/**
	 * The form that posts a claim, its nonce keyed to this offer.
	 *
	 * @param array $offer The offer.
	 */
	private static function render_claim_form( array $offer ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-tools__form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Getting your code', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_CLAIM . '_' . $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_CLAIM ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html( WPCPM_Sponsor_Offers::KIND_CODES === $offer['kind'] ? __( 'Get my code', 'wpcredits-program-manager' ) : __( 'Show me the code', 'wpcredits-program-manager' ) )
		);
		echo '</form>';
	}

	/**
	 * The form that reports a problem with a code already claimed.
	 *
	 * @param array $offer The offer.
	 */
	private static function render_problem_form( array $offer ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-tools__form wpcpm-tools__form--problem" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Sending', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_PROBLEM . '_' . $offer['id'] );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_PROBLEM ) );
		printf( '<input type="hidden" name="wpcpm_offer" value="%d" />', (int) $offer['id'] );
		printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html__( 'Report a problem with this code', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * Everything the person holds, including from offers since paused or ended: a code once
	 * given is theirs.
	 *
	 * @param WP_User $viewer The person.
	 * @param array   $claims Their claims.
	 */
	private static function render_codes( WP_User $viewer, array $claims ) {
		if ( empty( $claims ) ) {
			return;
		}

		echo '<h4 class="wpcpm-tools__subheading">' . esc_html__( 'Your codes', 'wpcredits-program-manager' ) . '</h4>';
		echo '<ul class="wpcpm-tools__codes">';

		foreach ( $claims as $offer_id => $claim ) {
			$offer = WPCPM_Sponsor_Offers::read( (int) $offer_id );

			if ( null === $offer ) {
				continue;
			}

			$code = WPCPM_Sponsor_Claims::code_for( $viewer->ID, $offer );

			echo '<li>';
			printf( '<span class="wpcpm-tools__codes-offer">%1$s: %2$s</span> ', esc_html( self::sponsor_name( $offer['sponsor'] ) ), esc_html( $offer['title'] ) );

			if ( preg_match( '#^https?://#i', $code ) ) {
				printf( '<a class="wpcpm-tools__code" href="%1$s" rel="external noopener">%2$s</a>', esc_url( $code ), esc_html( $code ) );
			} else {
				printf( '<code class="wpcpm-tools__code" data-wpcpm-select tabindex="0">%s</code>', esc_html( $code ) );
			}

			printf( ' <span class="wpcpm-tools__when">%s</span>', esc_html( wp_date( 'Y-m-d', (int) $claim['at'] ) ) );
			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Claim. The nonce is keyed to the offer; who may claim is may_claim()'s, inside
	 * WPCPM_Sponsor_Claims::claim().
	 */
	public static function handle_claim() {
		$offer_id = WPCPM_Request::posted_id( 'wpcpm_offer' );
		check_admin_referer( self::ACTION_CLAIM . '_' . $offer_id );

		if ( ! is_user_logged_in() ) {
			self::leave( 'claim-refused' );
		}

		$result = WPCPM_Sponsor_Claims::claim( $offer_id, wp_get_current_user() );

		if ( is_wp_error( $result ) ) {
			self::leave( self::status_for( $result->get_error_code() ) );
		}

		self::leave( ! empty( $result['new'] ) ? 'claimed' : 'claimed-again' );
	}

	/**
	 * Report a problem with a claimed code.
	 */
	public static function handle_problem() {
		$offer_id = WPCPM_Request::posted_id( 'wpcpm_offer' );
		check_admin_referer( self::ACTION_PROBLEM . '_' . $offer_id );

		if ( ! is_user_logged_in() ) {
			self::leave( 'problem-refused' );
		}

		$result = WPCPM_Sponsor_Claims::report_problem( $offer_id, wp_get_current_user() );

		if ( is_wp_error( $result ) ) {
			self::leave( self::status_for( $result->get_error_code() ) );
		}

		self::leave( 'problem-sent' );
	}

	/**
	 * Turn one of WPCPM_Sponsor_Claims's WP_Error codes into a key of messages().
	 *
	 * @param string $code A WP_Error code from WPCPM_Sponsor_Claims.
	 * @return string A key of messages().
	 */
	private static function status_for( $code ) {
		$map = array(
			'wpcpm_claim_refused'   => 'claim-refused',
			'wpcpm_claim_empty'     => 'claim-empty',
			'wpcpm_claim_busy'      => 'claim-busy',
			'wpcpm_problem_refused' => 'problem-refused',
			'wpcpm_problem_limit'   => 'problem-limit',
		);

		return isset( $map[ (string) $code ] ) ? $map[ (string) $code ] : 'claim-failed';
	}

	/**
	 * Flash on the viewer's own channel and go back where they came from, at the section.
	 * The referer, because three pages draw this section and the form does not say which; a
	 * missing one lands on the front page, and wp_safe_redirect() refuses a foreign host.
	 *
	 * @param string $status A key of messages().
	 */
	private static function leave( $status ) {
		WPCPM_Flash::set( self::FLASH, array( 'status' => sanitize_key( (string) $status ) ) );

		$back = wp_get_referer();
		$url  = is_string( $back ) && '' !== $back ? $back : home_url( '/' );

		wp_safe_redirect( $url . '#' . self::ANCHOR );
		exit;
	}
}
