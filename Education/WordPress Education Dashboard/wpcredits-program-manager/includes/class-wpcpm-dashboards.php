<?php
/**
 * The program's front-end dashboards, as one menu, and the pieces they share.
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
 *
 * It also draws what every dashboard says or offers the same way: why a page has nothing
 * to show, and the "Viewing as" switcher.
 */
class WPCPM_Dashboards {

	const NODE = 'wpcpm-dashboards';

	/** The script that puts one field to find and pick a name in a switcher's list's place. */
	const SWITCHER_SCRIPT = 'wpcpm-switcher';

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
				// The page's own name for everyone, a manager included, as the mentor link below:
				// the page is titled Student Report Card whoever opens it. The toolbar draws nothing
				// from `own`; it is there for callers of `links()` and of the `wpcpm_dashboard_links`
				// filter, and on the page the switcher says whose card it is.
				'title' => __( 'Student Report Card', 'wpcredits-program-manager' ),
				'href'  => $student_page,
				'own'   => $is_student,
			);
		}

		$mentor_page = WPCPM_Mentors_Dashboard::page_url();
		$is_mentor   = WPCPM_Mentors_Dashboard::is_mentor();

		if ( '' !== $mentor_page && ( $is_mentor || $can_manage ) ) {
			$links[] = array(
				'id'    => 'wpcpm-mentor-dashboard',
				'title' => __( 'Mentor Report Card', 'wpcredits-program-manager' ),
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
					// Membership, never the role, decides the link and its `own` flag: an account
					// keeps the Institution role after its access has ended, until an administrator
					// takes it away. The title is the page's own name for everyone, as the two above.
					'title' => __( 'Institution Dashboard', 'wpcredits-program-manager' ),
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

		// The Students and Mentors screens at their Sync tab, where the sync the sentence asks for runs,
		// rather than the Accounts tab each opens on; the Institutions screen at its Accounts tab,
		// where the accounts the sentence asks for are made, rather than the queue it opens on; the
		// Sponsors screen at its Accounts tab, where the accounts the sentence asks for are made; every
		// other screen at its own address.
		$screens = array(
			'students'       => 'admin.php?page=wpcpm-students&tab=sync',
			'mentors'        => 'admin.php?page=wpcpm-mentors&tab=sync',
			'institutions'   => 'admin.php?page=wpcpm-institutions&tab=accounts',
			'administrators' => 'admin.php?page=wpcpm-administrators',
			'sponsors'       => 'admin.php?page=wpcpm-sponsors&tab=accounts',
		);

		return esc_html( self::empty_sentence( $module ) ) . ' <a href="' . esc_url( admin_url( $screens[ $module ] ) ) . '">'
			. esc_html__( 'Open that screen', 'wpcredits-program-manager' ) . '</a>';
	}

	/**
	 * What a program manager is told when an audience's dashboard has nothing to show, before the
	 * link `nothing_to_show()` adds to the screen that fixes it.
	 *
	 * Public, because the Overview says the administrators' sentence when nothing waits for a
	 * decision, and a sentence written out twice is two sentences the day one of them is reworded.
	 *
	 * @param string $module Module ID, `students`, `mentors`, `institutions`, `administrators` or `sponsors`.
	 * @return string The sentence, translated and not escaped; '' for an ID no audience has.
	 */
	public static function empty_sentence( $module ) {
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

		return isset( $messages[ $module ] ) ? $messages[ $module ] : '';
	}

	/**
	 * The "Viewing as" switcher an Administrator uses to open a dashboard as somebody else.
	 *
	 * The institution, mentor, student and sponsor dashboards each drew their own copy of this
	 * form, and four copies listed their entries four ways. They are one form now, drawn here; a
	 * dashboard decides only what is listed and in whose words.
	 *
	 * The list is in A to Z order (`sort_switcher_options()`). Beside it, drawn hidden, is the one
	 * field that stands in for it once assets/js/switcher.js runs: a text field in the combobox role,
	 * an empty listbox the script fills from the list's options, and a status line for how many
	 * names the list shows and for the sentence when none match. The script hides the list and shows
	 * the field, which drops down, takes typing and narrows as the person types; picking a name sets
	 * the list's value, so Show sends what was picked. The list stays in the form as what it sends,
	 * and without the script it is the switcher, with nothing beside it that does nothing. The field
	 * carries no name, so the GET form posts the same field it posted before. All of it sits inside
	 * the form, because the WordPress Credits theme lifts the form above the dashboard card by its
	 * opening tag. The script is enqueued here, so only a page that draws a switcher loads it.
	 *
	 * Everything after the page ID is one block of fields, which the stylesheet lays out as a grid
	 * on one row: the label, the list or the field in its place, Show and the note. The note is the
	 * same on every dashboard, so it is written here, and so is the count's sentence.
	 *
	 * One entry is not a choice, and a select with a single option is a control that cannot do
	 * anything, so nothing is drawn for fewer than two.
	 *
	 * @param array $args {
	 *     The switcher.
	 *
	 *     @type string     $id      The list's ID; the field takes it with `-input` added, its list of
	 *                              names with `-list` and the label with `-label`.
	 *     @type string     $name    The query argument the list posts: the one the dashboard's resolver reads.
	 *     @type array      $options Value to label, in any order.
	 *     @type string|int $current The value being viewed, selected in the list.
	 *     @type string     $label   The list's label.
	 *     @type string     $find    The field's placeholder, which it shows while it is empty.
	 *     @type string     $none    What the field's list says when no name matches what was typed.
	 * }
	 */
	public static function render_switcher( array $args ) {
		$args = array_merge(
			array(
				'id'      => '',
				'name'    => '',
				'options' => array(),
				'current' => '',
				'label'   => '',
				'find'    => '',
				'none'    => '',
			),
			$args
		);

		$options = self::sort_switcher_options( (array) $args['options'] );

		if ( count( $options ) < 2 ) {
			return;
		}

		// Registered here the first time a switcher is drawn, in the footer, which a block's render
		// still reaches: the four dashboards share the handle and whichever draws first wins.
		if ( ! wp_script_is( self::SWITCHER_SCRIPT, 'registered' ) ) {
			wp_register_script( self::SWITCHER_SCRIPT, WPCPM_PLUGIN_URL . 'assets/js/switcher.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( self::SWITCHER_SCRIPT );

		$id    = (string) $args['id'];
		$field = $id . '-input';
		$list  = $id . '-list';
		$named = $id . '-label';

		echo '<form class="wpcpm-dashboard__switcher" method="get">';

		// Without pretty permalinks the page is addressed by query string, which a GET form
		// would otherwise discard - resubmitting to the site root.
		if ( ! get_option( 'permalink_structure' ) ) {
			$queried = get_queried_object_id();

			if ( $queried ) {
				printf( '<input type="hidden" name="page_id" value="%d" />', (int) $queried );
			}
		}

		echo '<div class="wpcpm-dashboard__switcher-fields">';
		printf( '<label for="%1$s" id="%2$s">%3$s</label> ', esc_attr( $id ), esc_attr( $named ), esc_html( $args['label'] ) );
		// Not put back as it was left: a browser that restores a form after Back would set the hidden
		// list on the name picked last while the field reads the page's own, and Show would open
		// somebody else's page.
		printf( '<select name="%1$s" id="%2$s" autocomplete="off">', esc_attr( $args['name'] ), esc_attr( $id ) );

		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $value, $args['current'], false ),
				esc_html( $label )
			);
		}

		echo '</select> ';

		// The field, hidden until the script shows it in the list's place. `spellcheck` is off because
		// a name is not a word a dictionary holds, and the browser's own suggestions are off because
		// the field brings its list.
		echo '<div class="wpcpm-dashboard__switcher-combo" hidden>';
		printf(
			'<input type="text" id="%1$s" class="wpcpm-dashboard__switcher-input" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="%2$s" autocomplete="off" spellcheck="false" placeholder="%3$s" />',
			esc_attr( $field ),
			esc_attr( $list ),
			esc_attr( $args['find'] )
		);
		printf( '<ul id="%1$s" class="wpcpm-dashboard__switcher-list" role="listbox" aria-labelledby="%2$s" hidden></ul>', esc_attr( $list ), esc_attr( $named ) );
		// Empty until the list opens: a status region is read out when its text changes. Both
		// sentences are the markup's, so that they are translated with the rest of the page; the
		// count's needs no plural, since the script fills in the number.
		printf(
			'<span class="wpcpm-dashboard__switcher-status" role="status" data-wpcpm-count="%1$s" data-wpcpm-none="%2$s"></span>',
			/* translators: %s: how many names the Viewing as list shows, as a number. */
			esc_attr( __( 'Names in the list: %s', 'wpcredits-program-manager' ) ),
			esc_attr( $args['none'] )
		);
		echo '</div> ';
		printf( '<button type="submit" class="wpcpm-button">%s</button>', esc_html__( 'Show', 'wpcredits-program-manager' ) );
		printf( '<span class="wpcpm-dashboard__switcher-note">%s</span>', esc_html__( 'Only Administrators see this control.', 'wpcredits-program-manager' ) );
		echo '</div>';
		echo '</form>';
	}

	/**
	 * A switcher's entries in A to Z order, as a reader of the list expects names
	 * (`compare_names()`).
	 *
	 * Two names alike in that reading are settled by their values, read as numbers where they are
	 * numbers (`strnatcmp()`), so the list does not reshuffle between two reads of the same
	 * entries. The sources hand them over in their own orders: Airtable's for the institutions and
	 * the sponsors, the database's for the students.
	 *
	 * @param array $options Value to label.
	 * @return array The same pairs, sorted by label.
	 */
	private static function sort_switcher_options( array $options ) {
		$entries = array();

		foreach ( $options as $value => $label ) {
			$entries[] = array( (string) $value, (string) $label );
		}

		usort(
			$entries,
			static function ( $a, $b ) {
				$order = self::compare_names( $a[1], $b[1] );

				return 0 !== $order ? $order : strnatcmp( $a[0], $b[0] );
			}
		);

		$sorted = array();

		foreach ( $entries as $entry ) {
			$sorted[ $entry[0] ] = $entry[1];
		}

		return $sorted;
	}

	/**
	 * Two names in the order the Viewing as switchers list people and organizations in, which is
	 * also the order of the students the reconciliation names on the Institutions screen. The
	 * accounts lists in wp-admin keep a comparison of their own (`WPCPM_Accounts_Table`).
	 *
	 * Without regard to accents, through `remove_accents()`, so Álvaro sorts among the A's and
	 * Łukasz among the L's rather than after Z, where a comparison byte by byte puts every name
	 * that opens on an accented letter; without regard to case in any script, through
	 * `mb_strtolower()`, because `strnatcasecmp()` folds only the Latin capitals and would set Анна
	 * apart from анна, every Cyrillic capital before every small letter; and with numbers read as
	 * numbers (`strnatcasecmp()`), so Student 2 comes before Student 10. The space between two
	 * words comes before any letter or digit, as in a dictionary, so Teo Polytechnic comes before
	 * Teodora School: `strnatcasecmp()` alone skips white space and would read TeoPolytechnic.
	 * Two names alike in that reading compare as one, and each caller settles them by something
	 * of its own that does not change between two reads.
	 *
	 * @param string $a One name.
	 * @param string $b The other.
	 * @return int Below 0 when the first comes first, above 0 when the second does, 0 when alike.
	 */
	public static function compare_names( $a, $b ) {
		return strnatcasecmp( self::name_key( $a ), self::name_key( $b ) );
	}

	/**
	 * A name as `compare_names()` reads it: accents taken away, lowercased in any script, and each
	 * run of white space, a non-breaking space among it, made one character that sorts before every
	 * letter and digit and that `strnatcasecmp()` does not skip, with none at the ends.
	 *
	 * Kept for the rest of the request once worked out, because a sort compares each name many
	 * times over, about ten times each in a list of a thousand, and `remove_accents()` is the costly
	 * part of a comparison.
	 *
	 * @param string $name A name.
	 * @return string
	 */
	private static function name_key( $name ) {
		static $keys = array();

		$name = (string) $name;

		if ( ! isset( $keys[ $name ] ) ) {
			$key = remove_accents( $name );

			// Not every PHP carries mbstring, and WordPress does not stand in for this one; without it
			// the Latin capitals are still folded by the comparison.
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $key, 'UTF-8' ) : $key;

			// A name that is not valid UTF-8 keeps its spaces, which the comparison then skips.
			$spaced = preg_replace( array( '/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '/[\s\x{00A0}]+/u' ), array( '', "\x01" ), $key );

			$keys[ $name ] = null === $spaced ? $key : $spaced;
		}

		return $keys[ $name ];
	}
}
