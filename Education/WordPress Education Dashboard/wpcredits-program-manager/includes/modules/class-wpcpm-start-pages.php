<?php
/**
 * The Start here page and the Team Matcher page, the two public pages a new student is sent to.
 *
 * Both are made when the first-week checklist's switch (`student_checklist_enabled`) turns on, or by
 * `wp wpcredits start_pages`, and never on their own: a site that does not use the checklist gains no
 * page. Neither is gated, because the people they are for may not be signed in yet (the welcome email
 * and the registration form's thank-you link to them). The program lead's copy replaces the starting
 * content later; a page that exists is adopted and never rewritten.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Makes, finds and fills the two pages.
 */
final class WPCPM_Start_Pages {

	/**
	 * Option: the Start here page's ID.
	 */
	const OPT_START = 'wpcpm_start_here_page_id';

	/**
	 * Option: the Team Matcher page's ID.
	 */
	const OPT_MATCHER = 'wpcpm_team_matcher_page_id';

	/**
	 * The Start here page's slug.
	 */
	const SLUG_START = 'start-here';

	/**
	 * The Team Matcher page's slug.
	 */
	const SLUG_MATCHER = 'find-your-team';

	/**
	 * The Find Your Team plugin's block.
	 */
	const MATCHER_BLOCK = 'contributor-team-matcher/quiz';

	/**
	 * Follow the checklist's switch.
	 */
	public static function init() {
		add_action( 'update_option_' . WPCPM_Settings::OPT_NAME, array( __CLASS__, 'follow_switch' ), 10, 2 );
	}

	/**
	 * The switch turned on makes the pages. Runs on the settings option's update action, which fires
	 * only when the stored value changed; a switch that was already on, or goes off, does nothing.
	 *
	 * @param mixed $before The settings before the save.
	 * @param mixed $after  The settings after it.
	 */
	public static function follow_switch( $before, $after ) {
		$was = is_array( $before ) && ! empty( $before['student_checklist_enabled'] );
		$is  = is_array( $after ) && ! empty( $after['student_checklist_enabled'] );

		if ( $is && ! $was ) {
			self::ensure_pages();
		}
	}

	/**
	 * Make the pages that are missing. The Team Matcher page comes first, because the Start here page
	 * links it, and only while the Find Your Team plugin's block is registered.
	 *
	 * @return array{start:int,matcher:int} Each page's ID, 0 where there is none.
	 */
	public static function ensure_pages() {
		$matcher = self::ensure_one( self::OPT_MATCHER, self::SLUG_MATCHER, __( 'Team Matcher', 'wpcredits-program-manager' ), 'matcher', self::matcher_available() );
		$start   = self::ensure_one( self::OPT_START, self::SLUG_START, __( 'Start here', 'wpcredits-program-manager' ), 'start', true );

		return array(
			'start'   => $start,
			'matcher' => $matcher,
		);
	}

	/**
	 * Whether the Find Your Team plugin's block is registered.
	 *
	 * @return bool
	 */
	private static function matcher_available() {
		return class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( self::MATCHER_BLOCK );
	}

	/**
	 * One page: the stored one while it exists and is not in the trash, else the page at the slug
	 * (pages alone), else a new published page when `$may_make` allows. Left ungated: no access level
	 * means public.
	 *
	 * @param string $option   The option holding the ID.
	 * @param string $slug     The page's slug.
	 * @param string $title    The title of a page made here.
	 * @param string $which    `start` or `matcher`, for the starting content.
	 * @param bool   $may_make Whether a page may be made when none exists.
	 * @return int The page ID, or 0.
	 */
	private static function ensure_one( $option, $slug, $title, $which, $may_make ) {
		$stored = (int) get_option( $option );

		if ( $stored > 0 ) {
			$status = get_post_status( $stored );

			if ( $status && 'trash' !== $status ) {
				return $stored;
			}
		}

		$existing = get_page_by_path( $slug, OBJECT, array( 'page' ) );

		if ( $existing instanceof WP_Post && 'page' === $existing->post_type && 'trash' !== $existing->post_status ) {
			update_option( $option, (int) $existing->ID, false );

			return (int) $existing->ID;
		}

		if ( ! $may_make ) {
			return 0;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => self::starting_content( $which ),
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_option( $option, (int) $page_id, false );

		return (int) $page_id;
	}

	/**
	 * A page's address while it is published.
	 *
	 * @param string $option The option holding the ID.
	 * @return string
	 */
	private static function url_of( $option ) {
		$page_id = (int) get_option( $option );

		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}

	/**
	 * The Start here page's address, or '' unless it is published.
	 *
	 * @return string
	 */
	public static function start_url() {
		return self::url_of( self::OPT_START );
	}

	/**
	 * The Team Matcher page's address, or '' unless it is published.
	 *
	 * @return string
	 */
	public static function matcher_url() {
		return self::url_of( self::OPT_MATCHER );
	}

	/**
	 * What a page this plugin makes starts with, as block markup.
	 *
	 * @param string $which `start` for the Start here page, `matcher` for the Team Matcher page.
	 * @return string
	 */
	public static function starting_content( $which ) {
		return 'matcher' === $which ? self::matcher_content() : self::start_content();
	}

	/**
	 * A paragraph block.
	 *
	 * @param string $html Already escaped inner markup.
	 * @return string
	 */
	private static function paragraph( $html ) {
		return "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * A second-level heading block.
	 *
	 * @param string $text The heading.
	 * @return string
	 */
	private static function heading( $text ) {
		return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $text ) . "</h2>\n<!-- /wp:heading -->";
	}

	/**
	 * A list block.
	 *
	 * @param string[] $items   Already escaped inner markup, one entry per item.
	 * @param bool     $ordered Whether the list is numbered.
	 * @return string
	 */
	private static function list_block( array $items, $ordered ) {
		$tag    = $ordered ? 'ol' : 'ul';
		$attrs  = $ordered ? ' {"ordered":true}' : '';
		$markup = '';

		foreach ( $items as $item ) {
			$markup .= "<!-- wp:list-item -->\n<li>" . $item . "</li>\n<!-- /wp:list-item -->\n";
		}

		return '<!-- wp:list' . $attrs . " -->\n<" . $tag . " class=\"wp-block-list\">\n" . $markup . '</' . $tag . ">\n<!-- /wp:list -->";
	}

	/**
	 * A link as markup.
	 *
	 * @param string $url   The address.
	 * @param string $label The words.
	 * @return string
	 */
	private static function link( $url, $label ) {
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * The Start here page: the first week as steps, where the guide and the Student Report Card are,
	 * and who to ask. True for a visitor who is not signed in, so it points at the sign-in rather than
	 * assuming one. A link whose page does not exist is left as plain words.
	 *
	 * @return string
	 */
	private static function start_content() {
		$guides = WPCPM_Handbook_Assistant::guides();
		$guide  = isset( $guides['student']['url'] ) ? (string) $guides['student']['url'] : '';
		$card   = (string) WPCPM_Students_Dashboard::page_url();
		$match  = self::matcher_url();

		$card_words  = __( 'Student Report Card', 'wpcredits-program-manager' );
		$card_markup = '' !== $card ? self::link( $card, $card_words ) : esc_html( $card_words );
		$team_markup = '' !== $match
			? sprintf(
				/* translators: %s: a link to the Team Matcher page, reading "Team Matcher". */
				esc_html__( 'Choose your contribution team. The %s asks a few short questions and suggests teams that fit you.', 'wpcredits-program-manager' ),
				self::link( $match, __( 'Team Matcher', 'wpcredits-program-manager' ) )
			)
			: esc_html__( 'Choose your contribution team. Ask your mentor or an Administrator if you are not sure which one fits you.', 'wpcredits-program-manager' );
		$guide_words = __( 'Student guide', 'wpcredits-program-manager' );
		$guide_mark  = '' !== $guide ? self::link( $guide, $guide_words ) : esc_html( $guide_words );

		$steps = array(
			esc_html__( 'Check that your WordPress.org profile is on record. If it is missing, tell your mentor or an Administrator.', 'wpcredits-program-manager' ),
			sprintf(
				/* translators: %s: a link to the Student Report Card, reading "Student Report Card". */
				esc_html__( 'Sign in to your %s with the link in your welcome email.', 'wpcredits-program-manager' ),
				$card_markup
			),
			esc_html__( 'Start your report form. You can fill it in a little at a time.', 'wpcredits-program-manager' ),
			esc_html__( 'Book your first call with your mentor from your Student Report Card.', 'wpcredits-program-manager' ),
			$team_markup,
			sprintf(
				/* translators: %s: a link to the Making WordPress Slack, reading "Making WordPress Slack". */
				esc_html__( 'Join the %s.', 'wpcredits-program-manager' ),
				self::link( 'https://make.wordpress.org/chat/', __( 'Making WordPress Slack', 'wpcredits-program-manager' ) )
			),
			esc_html__( 'Join the #wpcredits channel in that Slack. It is where students and the program team talk.', 'wpcredits-program-manager' ),
			esc_html__( 'Enroll in the Learn course. The link is under My course on your Student Report Card.', 'wpcredits-program-manager' ),
		);

		$blocks = array(
			self::paragraph( esc_html__( 'Welcome to the WordPress Credits Program. This page is for your first week. Each step is small, and you can take them in any order.', 'wpcredits-program-manager' ) ),
			self::heading( __( 'Your first week', 'wpcredits-program-manager' ) ),
			self::list_block( $steps, true ),
			self::heading( __( 'Where things are', 'wpcredits-program-manager' ) ),
			self::list_block(
				array(
					sprintf(
						/* translators: %s: a link to the Student guide, reading "Student guide". */
						esc_html__( 'The %s explains the program step by step. It opens once you are signed in as a student.', 'wpcredits-program-manager' ),
						$guide_mark
					),
					sprintf(
						/* translators: %s: a link to the Student Report Card, reading "Student Report Card". */
						esc_html__( 'Your %s holds your report form, your course and your calls. You need to be signed in to open it.', 'wpcredits-program-manager' ),
						$card_markup
					),
				),
				false
			),
			self::heading( __( 'Who to ask', 'wpcredits-program-manager' ) ),
			self::paragraph(
				sprintf(
					/* translators: 1: a link to the program's contact address. */
					esc_html__( 'Your mentor is the first person to ask. You can book a call with your mentor from your Student Report Card. If you do not have a mentor yet, or something looks wrong, write to an Administrator at %1$s or ask in the #wpcredits channel.', 'wpcredits-program-manager' ),
					'<a href="mailto:' . esc_attr( WPCPM_Student_Registration_Form::CONTACT ) . '">' . esc_html( WPCPM_Student_Registration_Form::CONTACT ) . '</a>'
				)
			),
		);

		return implode( "\n\n", $blocks );
	}

	/**
	 * The Team Matcher page: a short introduction and the Find Your Team plugin's block.
	 *
	 * @return string
	 */
	private static function matcher_content() {
		return self::paragraph( esc_html__( 'Not sure which contribution team to join? Answer a few short questions and the Team Matcher suggests teams that fit you. You can change your mind later.', 'wpcredits-program-manager' ) )
			. "\n\n<!-- wp:" . self::MATCHER_BLOCK . ' /-->';
	}
}
