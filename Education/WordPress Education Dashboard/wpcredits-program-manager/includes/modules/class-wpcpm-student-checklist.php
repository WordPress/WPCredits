<?php
/**
 * The first-week checklist: a card on the Student Report Card for new students.
 *
 * The site ticks the items it can see by itself (a WordPress.org profile on record, a sign-in, the
 * report form started, a first call booked, a contribution team chosen). The student ticks the ones
 * the site cannot see (the Making WordPress Slack, the #wpcredits channel, enrolling in the Learn
 * course). The card gates nothing. It shows for students who are new when the switch goes on and
 * for everyone who joins after, and folds to one line once every item is done.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * The checklist's audience, items, card and tick handler.
 */
final class WPCPM_Student_Checklist {

	/**
	 * User meta: the keys of the items the student has ticked.
	 */
	const META_TICKS = 'wpcpm_checklist_ticks';

	/**
	 * Option: Unix time the switch was first saved on.
	 */
	const OPT_ON_SINCE = 'wpcpm_student_checklist_on_since';

	/**
	 * The admin-post action of a tick; its nonce is bound to the student.
	 */
	const ACTION_TICK = 'wpcpm_checklist_tick';

	/**
	 * Days before the switch went on in which a track may have started and still count as new.
	 */
	const NEW_DAYS = 28;

	/**
	 * The items a student ticks themself.
	 */
	const SELF_KEYS = array( 'slack', 'channel', 'learn' );

	/**
	 * Hang the tick handler and follow the switch.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_TICK, array( __CLASS__, 'handle_tick' ) );
		add_action( 'update_option_' . WPCPM_Settings::OPT_NAME, array( __CLASS__, 'follow_switch' ), 10, 2 );
	}

	/**
	 * Write the on-since time the first time the switch is saved on; later saves keep it.
	 *
	 * @param mixed $before The settings before the save.
	 * @param mixed $after  The settings after it.
	 */
	public static function follow_switch( $before, $after ) {
		$on = is_array( $after ) && ! empty( $after['student_checklist_enabled'] );

		if ( $on && (int) get_option( self::OPT_ON_SINCE, 0 ) <= 0 ) {
			update_option( self::OPT_ON_SINCE, time(), false );
		}
	}

	/**
	 * Whether the card is drawn for a student: the switch is on, and the account was made after the
	 * switch went on or the track started in the four weeks before it.
	 *
	 * @param WP_User $student The student.
	 * @return bool
	 */
	public static function applies( WP_User $student ) {
		if ( ! WPCPM_Settings::get_value( 'student_checklist_enabled', false ) ) {
			return false;
		}

		$since = (int) get_option( self::OPT_ON_SINCE, 0 );

		if ( $since <= 0 ) {
			return false;
		}

		$registered = ! empty( $student->user_registered ) ? strtotime( $student->user_registered . ' UTC' ) : false;

		if ( false !== $registered && (int) $registered > $since ) {
			return true;
		}

		$program = WPCPM_Students_Sync::get_program( (int) $student->ID );
		$start   = ( is_array( $program ) && isset( $program['start'] ) ) ? trim( (string) $program['start'] ) : '';
		$parsed  = '' !== $start ? strtotime( $start . ' UTC' ) : false;

		return false !== $parsed && (int) $parsed >= $since - self::NEW_DAYS * DAY_IN_SECONDS;
	}

	/**
	 * The keys the student has ticked.
	 *
	 * @param int $student_id The student.
	 * @return string[]
	 */
	private static function ticks( $student_id ) {
		$ticks = get_user_meta( (int) $student_id, self::META_TICKS, true );

		return is_array( $ticks ) ? array_values( array_filter( array_map( 'strval', $ticks ) ) ) : array();
	}

	/**
	 * The items, in the order the card lists them.
	 *
	 * @param WP_User $student The student.
	 * @return array<int, array{key:string,label:string,done:bool,by:string,link:string}>
	 */
	public static function items( WP_User $student ) {
		$id      = (int) $student->ID;
		$program = WPCPM_Students_Sync::get_program( $id );
		$program = is_array( $program ) ? $program : array();
		$ticks   = self::ticks( $id );
		// The team item links the Team Matcher page while that page is published.
		$course  = WPCPM_Program::course_url( isset( $program['program'] ) ? (string) $program['program'] : '' );

		$report = (int) get_user_meta( $id, WPCPM_Student_Activity::META_REPORT, true ) > 0
			|| (int) get_user_meta( $id, WPCPM_Student_Activity::META_EDITED, true ) > 0;
		$calls  = WPCPM_Mentor_Calls::for_student( $id, false );

		return array(
			array(
				'key'   => 'profile',
				'label' => __( 'Your WordPress.org profile is on record', 'wpcredits-program-manager' ),
				'done'  => ! empty( $program['profile'] ),
				'by'    => 'site',
				'link'  => '',
			),
			array(
				'key'   => 'signed_in',
				'label' => __( 'You signed in to this site', 'wpcredits-program-manager' ),
				'done'  => (bool) WPCPM_Sign_Ins::has_signed_in( $id ),
				'by'    => 'site',
				'link'  => '',
			),
			array(
				'key'   => 'report',
				'label' => __( 'You started your report form', 'wpcredits-program-manager' ),
				'done'  => $report,
				'by'    => 'site',
				'link'  => '',
			),
			array(
				'key'   => 'call',
				'label' => __( 'You booked your first call', 'wpcredits-program-manager' ),
				'done'  => ! empty( $calls ),
				'by'    => 'site',
				'link'  => '',
			),
			array(
				'key'   => 'team',
				'label' => __( 'You chose your contribution team', 'wpcredits-program-manager' ),
				'done'  => ! empty( $program['team'] ),
				'by'    => 'site',
				'link'  => WPCPM_Start_Pages::matcher_url(),
			),
			array(
				'key'   => 'slack',
				'label' => __( 'You joined the Making WordPress Slack', 'wpcredits-program-manager' ),
				'done'  => in_array( 'slack', $ticks, true ),
				'by'    => 'student',
				'link'  => 'https://make.wordpress.org/chat/',
			),
			array(
				'key'   => 'channel',
				'label' => __( 'You joined the #wpcredits channel', 'wpcredits-program-manager' ),
				'done'  => in_array( 'channel', $ticks, true ),
				'by'    => 'student',
				'link'  => '',
			),
			array(
				'key'   => 'learn',
				'label' => __( 'You enrolled in the Learn course', 'wpcredits-program-manager' ),
				'done'  => in_array( 'learn', $ticks, true ),
				'by'    => 'student',
				'link'  => (string) $course,
			),
		);
	}

	/**
	 * The card, or an empty string where it does not apply. Whoever may tick for this student (the
	 * student, or somebody who manages the program) gets the buttons.
	 *
	 * @param WP_User $student The student.
	 * @param WP_User $viewer  The reader.
	 * @return string
	 */
	public static function render( WP_User $student, WP_User $viewer ) {
		if ( ! self::applies( $student ) ) {
			return '';
		}

		$items = self::items( $student );
		$may   = (int) $viewer->ID === (int) $student->ID || user_can( $viewer, WPCPM_Roles::CAP_MANAGE );
		$open  = array_filter(
			$items,
			static function ( $item ) {
				return ! $item['done'];
			}
		);

		$list = '<ul class="wpcpm-checklist__list">';

		foreach ( $items as $item ) {
			$list .= self::render_item( $item, $student, $may );
		}

		$list .= '</ul>';

		$out  = '<section class="wpcpm-student__section wpcpm-checklist" id="wpcpm-checklist">';
		$out .= '<h3 class="wpcpm-student__heading">' . esc_html__( 'Your first weeks', 'wpcredits-program-manager' ) . '</h3>';

		if ( empty( $open ) ) {
			$out .= '<p class="wpcpm-checklist__summary">' . esc_html__( 'All done. You have everything on the list.', 'wpcredits-program-manager' ) . '</p>';
			$out .= '<details class="wpcpm-checklist__fold"><summary>' . esc_html__( 'Show the list', 'wpcredits-program-manager' ) . '</summary>' . $list . '</details>';
		} else {
			$out .= '<p class="wpcpm-checklist__summary">' . esc_html(
				sprintf(
					/* translators: 1: items done, 2: items in all. */
					__( '%1$d of %2$d done. The site ticks what it can see; you tick the rest.', 'wpcredits-program-manager' ),
					count( $items ) - count( $open ),
					count( $items )
				)
			) . '</p>' . $list;
		}

		return $out . '</section>';
	}

	/**
	 * One line of the list: its state in a mark and in words, its link, and for an item the student
	 * ticks, the button.
	 *
	 * @param array   $item    The item.
	 * @param WP_User $student The student.
	 * @param bool    $may     Whether the reader may tick.
	 * @return string
	 */
	private static function render_item( array $item, WP_User $student, $may ) {
		$done  = ! empty( $item['done'] );
		$label = esc_html( $item['label'] );

		if ( '' !== $item['link'] ) {
			$label = '<a href="' . esc_url( $item['link'] ) . '">' . $label . '</a>';
		}

		$state = $done ? __( 'Done', 'wpcredits-program-manager' ) : __( 'To do', 'wpcredits-program-manager' );
		$mark  = $done ? '&#10003;' : '&#9675;';
		$line  = sprintf(
			'<li class="wpcpm-checklist__item %1$s"><span class="wpcpm-checklist__mark" aria-hidden="true">%2$s</span><span class="wpcpm-checklist__label">%3$s</span> <span class="wpcpm-checklist__state">%4$s</span>',
			$done ? 'is-done' : 'is-todo',
			$mark,
			$label,
			esc_html( $state )
		);

		if ( ! $done && 'student' === $item['by'] && $may ) {
			$line .= sprintf(
				'<form class="wpcpm-checklist__tick" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s"><input type="hidden" name="_wpnonce" value="%3$s" />%4$s<input type="hidden" name="action" value="%5$s" /><input type="hidden" name="student" value="%6$d" /><input type="hidden" name="item" value="%7$s" /><button type="submit" class="wpcpm-button wpcpm-button--secondary">%8$s<span class="screen-reader-text"> %9$s</span></button></form>',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr__( 'Saving', 'wpcredits-program-manager' ),
				esc_attr( wp_create_nonce( self::ACTION_TICK . '_' . (int) $student->ID ) ),
				wp_referer_field( false ),
				esc_attr( self::ACTION_TICK ),
				(int) $student->ID,
				esc_attr( $item['key'] ),
				esc_html__( 'Mark as done', 'wpcredits-program-manager' ),
				esc_html( $item['label'] )
			);
		}

		return $line . '</li>';
	}

	/**
	 * A tick: only the three items a student ticks, for the student or somebody who manages the
	 * program, with the nonce bound to the student. Goes back to the page the press came from.
	 */
	public static function handle_tick() {
		$student_id = WPCPM_Request::posted_id( 'student' );

		check_admin_referer( self::ACTION_TICK . '_' . $student_id );

		$me = (int) get_current_user_id();

		if ( $student_id <= 0 || ! ( $me === $student_id || current_user_can( WPCPM_Roles::CAP_MANAGE ) ) ) {
			wp_die( esc_html__( 'You cannot tick this for this student.', 'wpcredits-program-manager' ), '', array( 'response' => 403 ) );
		}

		$key = WPCPM_Request::posted_key( 'item' );

		if ( in_array( $key, self::SELF_KEYS, true ) ) {
			$ticks = self::ticks( $student_id );

			if ( ! in_array( $key, $ticks, true ) ) {
				$ticks[] = $key;
				update_user_meta( $student_id, self::META_TICKS, $ticks );
			}
		}

		$back = WPCPM_Request::back( (string) WPCPM_Students_Dashboard::page_url(), home_url( '/' ) );

		wp_safe_redirect( $back );
		exit;
	}
}
