<?php
/**
 * The program's front-end dashboards, as one menu.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects the dashboards the current user can reach and puts them in the toolbar.
 *
 * Each module owns its own page, but a person can reach more than one of them -
 * an administrator reaches every page, and somebody can genuinely be both a mentor
 * and a student. Registering them from one place means an administrator gets them
 * grouped under a single menu instead of a row of unrelated top-level items, while
 * a mentor with one page still gets one direct link.
 */
class WPCPM_Dashboards {

	const NODE = 'wpcpm-dashboards';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 40 );
	}

	/**
	 * The dashboards this user can reach, in menu order.
	 *
	 * @return array[] Each entry has id, title, href and own keys.
	 */
	public static function links() {
		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );
		$links      = array();

		$student_page = WPCPM_Students_Dashboard::page_url();
		$is_student   = WPCPM_Students_Dashboard::is_student();

		if ( '' !== $student_page && ( $is_student || $can_manage ) ) {
			$links[] = array(
				'id'    => 'wpcpm-student-dashboard',
				// "My Program" only when it is theirs; an administrator looking at
				// somebody else's should not be told it is their own.
				'title' => $is_student
					? __( 'Student Report Card', 'wpcredits-program-manager' )
					: __( 'Student Dashboard', 'wpcredits-program-manager' ),
				'href'  => $student_page,
				'own'   => $is_student,
			);
		}

		$mentor_page = WPCPM_Mentors_Dashboard::page_url();
		$is_mentor   = WPCPM_Mentors_Dashboard::is_mentor();

		if ( '' !== $mentor_page && ( $is_mentor || $can_manage ) ) {
			$links[] = array(
				'id'    => 'wpcpm-mentor-dashboard',
				'title' => $is_mentor
					? __( 'Mentor Report Card', 'wpcredits-program-manager' )
					: __( 'Mentor Dashboard', 'wpcredits-program-manager' ),
				'href'  => $mentor_page,
				'own'   => $is_mentor,
			);
		}

		// Guarded rather than called outright: the institution dashboard is a later class in
		// the same module, and `links()` runs on every admin-bar render. A missing class here
		// would be a fatal on every page of the site instead of one absent menu item.
		if ( class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
			$institution_page = WPCPM_Institutions_Dashboard::page_url();
			$is_member        = WPCPM_Institutions_Dashboard::is_member();

			if ( '' !== $institution_page && ( $is_member || $can_manage ) ) {
				$links[] = array(
					'id'    => 'wpcpm-institution-dashboard',
					// Membership, never the role: an account keeps the Institution role until a
					// manager takes it away, so "My Institution" would go on telling somebody the
					// page is theirs after their access ended. A manager arriving through the
					// switcher is looking at somebody else's students and is told so.
					'title' => $is_member
						? __( 'Institution Dashboard', 'wpcredits-program-manager' )
						: __( 'Institution Dashboard', 'wpcredits-program-manager' ),
					'href'  => $institution_page,
					'own'   => $is_member,
				);
			}
		}

		// Membership, never the role, for the reason the institution entry gives; guarded
		// because the class lands with the Sponsors module's front end.
		if ( class_exists( 'WPCPM_Sponsors_Dashboard' ) ) {
			$sponsor_page = WPCPM_Sponsors_Dashboard::page_url();
			$is_sponsor   = WPCPM_Sponsors_Dashboard::is_member();

			if ( '' !== $sponsor_page && ( $is_sponsor || $can_manage ) ) {
				$links[] = array(
					'id'    => 'wpcpm-sponsor-dashboard',
					'title' => __( 'Sponsor Dashboard', 'wpcredits-program-manager' ),
					'href'  => $sponsor_page,
					'own'   => $is_sponsor,
				);
			}
		}

		// Managers only, and guarded like the institution entry: the class lands with the
		// Administrators module's front end and `links()` runs on every toolbar render.
		if ( $can_manage && class_exists( 'WPCPM_Administrators_Dashboard' ) ) {
			$administrator_page = WPCPM_Administrators_Dashboard::page_url();

			if ( '' !== $administrator_page ) {
				$links[] = array(
					'id'    => 'wpcpm-administrator-dashboard',
					'title' => __( 'Administrator Dashboard', 'wpcredits-program-manager' ),
					'href'  => $administrator_page,
					'own'   => true,
				);
			}
		}

		/**
		 * Filter the dashboards offered in the toolbar.
		 *
		 * @param array[] $links Dashboard links.
		 */
		return (array) apply_filters( 'wpcpm_dashboard_links', $links );
	}

	/**
	 * Add the dashboards to the toolbar.
	 *
	 * @param WP_Admin_Bar $admin_bar Admin bar instance.
	 */
	public static function admin_bar( $admin_bar ) {
		if ( ! $admin_bar instanceof WP_Admin_Bar ) {
			return;
		}

		$links = self::links();

		if ( empty( $links ) ) {
			return;
		}

		// One page: a direct link, because burying a mentor's only destination
		// under a menu costs them a click for nothing.
		if ( 1 === count( $links ) ) {
			$admin_bar->add_node(
				array(
					'id'    => $links[0]['id'],
					'title' => $links[0]['title'],
					'href'  => $links[0]['href'],
				)
			);

			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => self::NODE,
				'title' => __( 'Dashboards', 'wpcredits-program-manager' ),
				'href'  => $links[0]['href'],
				'meta'  => array( 'title' => __( 'The program dashboards you can reach', 'wpcredits-program-manager' ) ),
			)
		);

		foreach ( $links as $link ) {
			$admin_bar->add_node(
				array(
					'id'     => $link['id'],
					'parent' => self::NODE,
					'title'  => $link['title'],
					'href'   => $link['href'],
				)
			);
		}
	}

	/**
	 * Why a dashboard has nothing to show, phrased for who is asking.
	 *
	 * An administrator who has not synced yet used to be told they lacked a role,
	 * which is both untrue and unactionable - the accounts simply do not exist. The
	 * message has to name the real reason and point at the screen that fixes it.
	 *
	 * @param string $module     Module ID, `students`, `mentors`, `institutions`, `administrators` or `sponsors`.
	 * @param bool   $can_manage Whether the viewer manages the program.
	 * @return string HTML.
	 */
	public static function nothing_to_show( $module, $can_manage ) {
		$theirs = array(
			'students'       => __( 'This page is for program students. Your account is not linked to a student record.', 'wpcredits-program-manager' ),
			'mentors'        => __( 'This page is for program mentors. Your account does not hold the Mentor role.', 'wpcredits-program-manager' ),
			// Membership, not the role, for the reason `WPCPM_Notices::applies_to()` gives: an
			// account keeps the Institution role until a manager takes it away, so "you do not
			// hold the role" would be false for exactly the people who have just lost access.
			'institutions'   => __( 'This page is for the institutions in the program. Your account does not act for an institution.', 'wpcredits-program-manager' ),
			'administrators' => __( 'This page is for the program managers. Your account cannot manage the program.', 'wpcredits-program-manager' ),
			'sponsors'       => __( 'This page is for the program sponsors. Your account is not attached to a sponsor.', 'wpcredits-program-manager' ),
		);

		// Every audience is named - five of them now - because what an unnamed one used to get
		// was the mentor wording: the fall-through was written when `students` and `mentors`
		// were the only two, and it told an institution it did not hold the Mentor role.
		// Unknown IDs keep that old behaviour rather than inventing a sixth sentence for a
		// caller that does not exist.
		$module = isset( $theirs[ $module ] ) ? $module : 'mentors';

		if ( ! $can_manage ) {
			return esc_html( $theirs[ $module ] );
		}

		$messages = array(
			'students'       => __( 'No student accounts have been synced yet, so there is nothing to show. Run a sync on the Students screen and they will appear here.', 'wpcredits-program-manager' ),
			'mentors'        => __( 'No mentor accounts have been synced yet, so there is nothing to show. Run a sync on the Mentors screen and they will appear here.', 'wpcredits-program-manager' ),
			// Not "run a sync": the pipeline index can hold every institution in the base and
			// this page still resolve to nothing, because a manager falls back to the first
			// institution with a live member. What is missing is an account, not a read.
			'institutions'   => __( 'No institution has an account on this site yet, so there is nothing to show. Provision one on the Institutions screen and it will appear here.', 'wpcredits-program-manager' ),
			'administrators' => __( 'Nothing is waiting for a manager right now.', 'wpcredits-program-manager' ),
			'sponsors'       => __( 'No sponsor has an account yet.', 'wpcredits-program-manager' ),
		);

		$screens = array(
			'students'       => 'wpcpm-students',
			'mentors'        => 'wpcpm-mentors',
			'institutions'   => 'wpcpm-institutions',
			'administrators' => 'wpcpm-administrators',
			'sponsors'       => 'wpcpm-sponsors',
		);

		return esc_html( $messages[ $module ] ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=' . $screens[ $module ] ) ) . '">'
			. esc_html__( 'Open that screen', 'wpcredits-program-manager' ) . '</a>';
	}
}
