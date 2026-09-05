<?php
/**
 * The Sponsor Dashboard's sponsored-mentors card.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The mentors a sponsor funds, with their student counts, and the mentors looking for one.
 *
 * Names of mentors and numbers of students, never a student's name (design spec of
 * 4 September 2026, section 5.6 and decision 7). Read from the mentors sync's sponsorship
 * index, so a mentor who is not Active is counted and not named: their name is not on the site.
 */
final class WPCPM_Sponsor_Mentors {

	const ACTION_INTEREST_MENTOR = 'wpcpm_sponsor_interest_mentor';
	const CARD                   = 'mentors';
	/** The second card: the mentors looking for a sponsor, with the interest form. */
	const CARD_LOOKING           = 'looking';
	/** The profile photo, as the Credits Program Mentors plugin draws it: 116px, from the WordPress.org profile. */
	const PHOTO_SIZE             = 116;
	const PER_DAY                = 5;
	const CEILING                = 'sponsor-mentor-interest';
	const MAIL_CONTEXT           = 'sponsor-interest';
	const LOG_KIND               = 'sponsor_interest_mentor';

	/**
	 * The handler.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_INTEREST_MENTOR, array( __CLASS__, 'handle_interest' ) );
	}

	/**
	 * This card's outcomes.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array(
			'mentor-interest-sent'    => array( 'success', __( 'Thank you. Your program contact will get in touch about that mentor.', 'wpcredits-program-manager' ) ),
			'mentor-interest-unknown' => array( 'error', __( 'That mentor is not on the list.', 'wpcredits-program-manager' ) ),
			'mentor-interest-ceiling' => array( 'error', __( 'Five a day is the limit. Try again tomorrow.', 'wpcredits-program-manager' ) ),
			'mentor-interest-failed'  => array( 'error', __( 'That could not be sent right now. Try again later.', 'wpcredits-program-manager' ) ),
			'refused'                 => array( 'error', __( 'That is not something your account can do here.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * The mentors linked to a sponsor, resolved through the sponsorship index.
	 *
	 * @param string $record Sponsor record ID.
	 * @return array `mentors` (each `record`, `name`, `profile`, `current`, `past`), `others` (linked mentors the index does not hold: not Active) and `index_empty` (the sponsorship index has not been built yet, so `others` means nothing).
	 */
	public static function linked( $record ) {
		$row   = WPCPM_Sponsors_Index::row( $record );
		$known = WPCPM_Mentors_Sync::sponsorship();
		$out   = array(
			'mentors'     => array(),
			'others'      => 0,
			// An index the mentors sync has not written yet is not the same as every linked
			// mentor being inactive, and the card said the second (queued item A, found live on
			// 5 September 2026). Reported so the two cases can be told apart.
			'index_empty' => empty( $known ),
		);

		if ( ! is_array( $row ) ) {
			return $out;
		}

		foreach ( (array) $row['mentors'] as $mentor ) {
			if ( ! isset( $known[ $mentor ] ) ) {
				++$out['others'];
				continue;
			}

			$facts  = $known[ $mentor ];
			$counts = self::counts_for( (int) $facts['user_id'] );

			$out['mentors'][] = array(
				'record'    => $mentor,
				'name'      => (string) $facts['name'],
				'profile'   => (string) $facts['profile'],
				'user_id'   => (int) $facts['user_id'],
				'current'   => $counts['current'],
				'past'      => $counts['past'],
				'expertise' => isset( $facts['expertise'] ) ? (array) $facts['expertise'] : array(),
			);
		}

		return $out;
	}

	/**
	 * The Active mentors who said they would like a sponsor and have none, by name.
	 *
	 * @return array[] Each `record`, `name`, `profile`, `expertise`.
	 */
	public static function looking() {
		$out = array();

		foreach ( WPCPM_Mentors_Sync::sponsorship() as $record => $facts ) {
			if ( 'Active' !== $facts['status'] || empty( $facts['wants'] ) || ! empty( $facts['sponsored'] ) ) {
				continue;
			}

			// The same counts the linked list carries (owner request of 5 September 2026): a
			// sponsor choosing whom to support wants to know how many students a mentor has had.
			$counts = self::counts_for( isset( $facts['user_id'] ) ? (int) $facts['user_id'] : 0 );

			$out[] = array(
				'record'    => (string) $record,
				'name'      => (string) $facts['name'],
				'profile'   => (string) $facts['profile'],
				'expertise' => (array) $facts['expertise'],
				'user_id'   => isset( $facts['user_id'] ) ? (int) $facts['user_id'] : 0,
				'current'   => $counts['current'],
				'past'      => $counts['past'],
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $out;
	}

	/**
	 * A sponsor would like to sponsor a mentor on the list.
	 */
	public static function handle_interest() {
		check_admin_referer( self::ACTION_INTEREST_MENTOR );

		$claim = WPCPM_Sponsor_Roster::claim( WPCPM_Request::posted_text( 'wpcpm_sponsor' ), WPCPM_Sponsor_Policy::ACT_EXPRESS_INTEREST );

		if ( is_wp_error( $claim ) ) {
			self::leave( 'refused', '' );
		}

		$record = $claim['record'];
		$row    = is_array( $claim['row'] ) ? $claim['row'] : WPCPM_Sponsors_Index::empty_row();
		$viewer = wp_get_current_user();

		if ( ! WPCPM_Ceiling::claim( WPCPM_Ceiling::key( self::CEILING, (string) $viewer->ID ), self::PER_DAY, DAY_IN_SECONDS ) ) {
			self::leave( 'mentor-interest-ceiling', $record );
		}

		$asked  = WPCPM_Request::posted_text( 'wpcpm_mentor' );
		$mentor = null;

		foreach ( self::looking() as $candidate ) {
			if ( 0 === strcmp( $candidate['record'], $asked ) ) {
				$mentor = $candidate;
				break;
			}
		}

		if ( null === $mentor ) {
			self::leave( 'mentor-interest-unknown', $record );
		}

		$sponsor = '' === trim( $row['name'] ) ? $record : trim( $row['name'] );
		$build   = static function ( $user ) use ( $sponsor, $mentor ) {
			return array(
				'subject' => sprintf(
					/* translators: 1: company name, 2: mentor name. */
					__( '[WordPress Credits] %1$s would like to sponsor %2$s', 'wpcredits-program-manager' ),
					$sponsor,
					$mentor['name']
				),
				'body'    => sprintf(
					/* translators: 1: company name, 2: mentor name, 3: the mentor's WordPress.org profile. */
					__( '%1$s said on the Sponsor Dashboard that it would like to sponsor the mentor %2$s (%3$s). Please get in touch with both.', 'wpcredits-program-manager' ),
					$sponsor,
					$mentor['name'],
					$mentor['profile']
				),
			);
		};

		$mailed = WPCPM_Sponsor_Interests::mail_manager( $record, $build );

		// Logged whether or not anybody could be told: the audit row is the record that the
		// interest was ever raised, the same bargain the sponsor's own interests card
		// strikes when nobody is reachable (its `interest-unsent` flash beside this one).
		WPCPM_Institution_Audit::record_sponsor(
			array(
				'kind'     => self::LOG_KIND,
				'sponsor'  => $record,
				'subject'  => $mentor['record'],
				'actor'    => (int) $viewer->ID,
				'ground'   => $claim['decision']['ground'],
				'evidence' => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'  => sprintf(
					/* translators: 1: company name, 2: mentor name. */
					__( '%1$s would like to sponsor the mentor %2$s.', 'wpcredits-program-manager' ),
					$sponsor,
					$mentor['name']
				),
				'data'     => array(
					'mentor' => $mentor['record'],
					'mailed' => (int) $mailed,
				),
			)
		);

		self::leave( $mailed > 0 ? 'mentor-interest-sent' : 'mentor-interest-failed', $record );
	}

	/**
	 * A mentor's current and past students, counted from the site's own mentee cache; a mentor
	 * with no site account has no count to give honestly, and the card says so instead.
	 *
	 * @param int $user_id The mentor's account, or 0.
	 * @return array `current`, `past`.
	 */
	private static function counts_for( $user_id ) {
		$counts = array(
			'current' => 0,
			'past'    => 0,
		);

		if ( $user_id < 1 ) {
			return $counts;
		}

		foreach ( WPCPM_Mentors_Dashboard::get_mentees( $user_id ) as $mentee ) {
			if ( ! empty( $mentee['is_past'] ) ) {
				++$counts['past'];
			} else {
				++$counts['current'];
			}
		}

		return $counts;
	}

	/**
	 * The WordPress.org username in a profile address, for the photo: the last path segment of
	 * `https://profiles.wordpress.org/<user>/`; '' for anything else.
	 *
	 * @param string $profile The profile address.
	 * @return string
	 */
	public static function username_from_profile( $profile ) {
		$parts = wp_parse_url( trim( (string) $profile ) );

		if ( ! is_array( $parts ) || ! isset( $parts['host'], $parts['path'] ) || 'profiles.wordpress.org' !== strtolower( $parts['host'] ) ) {
			return '';
		}

		$segments = array_values( array_filter( explode( '/', $parts['path'] ), 'strlen' ) );
		$username = empty( $segments ) ? '' : end( $segments );

		return preg_match( '/^[A-Za-z0-9._-]{1,60}$/', $username ) ? $username : '';
	}

	/**
	 * The two cards: the sponsor's own mentors, and the mentors looking for a sponsor.
	 *
	 * Two disclosures rather than one card with a sub-heading, and every mentor a card in a
	 * grid, the way the Credits Program Mentors plugin shows the program's mentors (owner
	 * request of 5 September 2026): photo from the WordPress.org profile, name, expertise as
	 * chips, one line of student counts. Names of mentors and numbers of students, never a
	 * student's name (spec section 5.6).
	 *
	 * @param string $record  Sponsor record ID.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$linked  = self::linked( $record );
		$looking = self::looking();
		$open    = isset( $context['open'] ) ? (string) $context['open'] : '';

		// Your mentors.
		printf( '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-%1$s" class="wpcpm-group wpcpm-group__disclosure"%2$s>', esc_attr( self::CARD ), self::CARD === $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Your mentors', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $linked['mentors'] ) + (int) $linked['others'] ) )
		);
		echo '<div class="wpcpm-group__body">';

		if ( ! empty( $linked['index_empty'] ) ) {
			// The mentors sync has not written its index since it learned to: nothing can be
			// named yet, and "not currently active" would be a statement about people the site
			// simply has not read (found live on 5 September 2026).
			echo '<p class="wpcpm-student__note">' . esc_html__( 'The mentor list refreshes with the next program sync.', 'wpcredits-program-manager' ) . '</p>';
		} elseif ( empty( $linked['mentors'] ) && 0 === $linked['others'] ) {
			echo '<p>' . esc_html__( 'No mentor is linked to your company yet.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			if ( ! empty( $linked['mentors'] ) ) {
				echo '<div class="wpcpm-mentor-tiles">';

				foreach ( $linked['mentors'] as $mentor ) {
					self::render_mentor_card( $mentor, $record, false );
				}

				echo '</div>';
			}

			if ( $linked['others'] > 0 ) {
				printf(
					'<p class="wpcpm-student__note">%s</p>',
					esc_html(
						sprintf(
							/* translators: %d: mentors. */
							_n( 'And %d mentor who is not currently active.', 'And %d mentors who are not currently active.', $linked['others'], 'wpcredits-program-manager' ),
							$linked['others']
						)
					)
				);
			}
		}

		echo '</div></details></section>';

		// Mentors looking for a sponsor.
		printf( '<section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-%1$s" class="wpcpm-group wpcpm-group__disclosure"%2$s>', esc_attr( self::CARD_LOOKING ), self::CARD_LOOKING === $open ? ' open' : '' );
		printf(
			'<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">%1$s <span class="wpcpm-group__count">%2$s</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>',
			esc_html__( 'Mentors looking for a sponsor', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( count( $looking ) ) )
		);
		echo '<div class="wpcpm-group__body">';

		if ( empty( $looking ) ) {
			echo '<p>' . esc_html__( 'Nobody is on the list right now.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			echo '<p class="wpcpm-student__note">' . esc_html__( 'Active mentors who asked to be listed. One press tells your program contact you would like to sponsor one of them.', 'wpcredits-program-manager' ) . '</p>';
			echo '<div class="wpcpm-mentor-tiles">';

			foreach ( $looking as $mentor ) {
				self::render_mentor_card( $mentor, $record, true );
			}

			echo '</div>';
		}

		echo '</div></details></section>';
	}

	/**
	 * One mentor card: photo, name, counts, expertise chips, the profile link, and for a mentor
	 * looking for a sponsor the interest form.
	 *
	 * @param array  $mentor    `name`, `profile`, `user_id`, `current`, `past`, `expertise`, `record`.
	 * @param string $record    Sponsor record ID, for the form.
	 * @param bool   $with_form Whether to draw the interest form.
	 */
	private static function render_mentor_card( array $mentor, $record, $with_form ) {
		$name     = trim( (string) $mentor['name'] );
		$profile  = isset( $mentor['profile'] ) ? WPCPM_Field_Value::clean_url( (string) $mentor['profile'] ) : '';
		$username = self::username_from_profile( $profile );

		echo '<article class="wpcpm-mentor-tile">';
		echo '<div class="wpcpm-mentor-tile__photo">';

		if ( '' !== $username ) {
			printf(
				'<img class="wpcpm-mentor-tile__img" src="%1$s" srcset="%2$s 2x" width="%3$d" height="%3$d" alt="%4$s" loading="lazy" decoding="async" />',
				esc_url( WPCPM_Mentors_Dashboard::avatar_url( $username, '', self::PHOTO_SIZE ) ),
				esc_url( WPCPM_Mentors_Dashboard::avatar_url( $username, '', self::PHOTO_SIZE * 2 ) ),
				(int) self::PHOTO_SIZE,
				/* translators: %s: the mentor's name. */
				esc_attr( sprintf( __( 'Profile photo of %s', 'wpcredits-program-manager' ), $name ) )
			);
		} else {
			// No WordPress.org profile on record: the initials stand in, so the grid keeps its shape.
			printf( '<span class="wpcpm-mentor-tile__initials" aria-hidden="true">%s</span>', esc_html( mb_substr( $name, 0, 1 ) ) );
		}

		echo '</div>';

		if ( '' !== $profile ) {
			printf( '<h4 class="wpcpm-mentor-tile__name"><a href="%1$s" rel="external noopener">%2$s</a></h4>', esc_url( $profile ), esc_html( $name ) );
		} else {
			printf( '<h4 class="wpcpm-mentor-tile__name">%s</h4>', esc_html( $name ) );
		}

		if ( (int) $mentor['user_id'] > 0 ) {
			printf(
				'<p class="wpcpm-mentor-tile__stats">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: students now, 2: students before. */
						__( '%1$s now, %2$s before', 'wpcredits-program-manager' ),
						sprintf( /* translators: %d: students. */ _n( '%d student', '%d students', (int) $mentor['current'], 'wpcredits-program-manager' ), (int) $mentor['current'] ),
						number_format_i18n( (int) $mentor['past'] )
					)
				)
			);
		} else {
			// A mentor the sync has not matched to a site account has no count to show honestly:
			// "0 now, 0 before" reads as an inactive mentor, not as one whose account is not linked.
			echo '<p class="wpcpm-mentor-tile__stats">' . esc_html__( 'No site account yet', 'wpcredits-program-manager' ) . '</p>';
		}

		if ( ! empty( $mentor['expertise'] ) ) {
			echo '<p class="wpcpm-mentor-tile__tags">';

			foreach ( (array) $mentor['expertise'] as $area ) {
				printf( '<span class="wpcpm-mentor-tile__tag">%s</span>', esc_html( (string) $area ) );
			}

			echo '</p>';
		}

		if ( '' !== $profile ) {
			printf( '<p class="wpcpm-mentor-tile__link"><a href="%1$s" rel="external noopener">%2$s</a></p>', esc_url( $profile ), esc_html__( 'WordPress.org profile', 'wpcredits-program-manager' ) );
		}

		if ( $with_form ) {
			printf(
				'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Sending', 'wpcredits-program-manager' )
			);
			wp_nonce_field( self::ACTION_INTEREST_MENTOR );
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_INTEREST_MENTOR ) );
			printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );
			printf( '<input type="hidden" name="wpcpm_mentor" value="%s" />', esc_attr( $mentor['record'] ) );
			printf( '<button type="submit" class="wpcpm-button wpcpm-button--secondary">%s</button>', esc_html__( 'I would like to sponsor this mentor', 'wpcredits-program-manager' ) );
			echo '</form>';
		}

		echo '</article>';
	}

	/**
	 * Flash and go back to the dashboard, through the dashboard's own method (see the profile card).
	 *
	 * @param string $status A key of `messages()`.
	 * @param string $record The sponsor, or ''.
	 */
	private static function leave( $status, $record ) {
		// Every outcome of this class is the interest form's, which lives on the second card.
		call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'leave' ), $status, self::CARD_LOOKING, $record );
		exit;
	}
}
