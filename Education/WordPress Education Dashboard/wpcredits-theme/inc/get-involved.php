<?php
/**
 * The header's Get involved menu (theme 1.32.12, the product owner's design of 5 November 2026).
 *
 * One outlined button, "Get involved", takes the place of the two buttons a visitor's menu ended with
 * since 1.32.7, "Become a sponsor" and "Partner as an institution", so the visitor's bar reads: the site's
 * name, Partners (while the Partners page is public), Get involved, and Log in, the one filled button. The
 * button opens a small panel of role rows, mentors first, each an icon, a verb-first title and one line
 * (`wpcredits_involve_rows()`).
 *
 * It is drawn in three places, from the same rows:
 *
 * - the bar, for a visitor: the Get involved block (blocks/get-involved), hooked as the navigation's last
 *   item after the Partners link (`wpcredits_involve_hook()`), prints a disclosure, a `<button>` with
 *   `aria-expanded` and `aria-controls` and the panel it names (`wpcredits_involve_menu_html()`). The
 *   block's view script opens and closes it; the stylesheet places the panel against the item, so opening
 *   it moves nothing, and without JavaScript opens it under the pointer and while the focus is in it, so
 *   its links are always reached;
 * - a phone's open menu, for a visitor: the same rows under a "Get involved" heading, put first in the
 *   navigation's content, above its items (`wpcredits_involve_navigation()`), and shown only there, where
 *   the bar's item is hidden. A navigation that holds the menu is never a lone item (inc/header.php), so
 *   on a phone it is always the menu button and this panel;
 * - a signed-in reader's Menu panel below 1200px: the same list, first in the panel
 *   (`wpcredits_header_menu()` in inc/header.php). A signed-in reader's wide bar from 1200px carries the
 *   report cards' links and stays on one line without it, as it did without the two buttons (the product
 *   owner, 25 October 2026), so a signed-in reader is hooked no block, and the list shows in the panel
 *   alone.
 *
 * The Site Editor, used signed in and reading the menu over the REST API, is never offered the block, so a
 * menu saved there never holds it or lists it as ignored.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The block the bar's menu is, hooked at the navigation's end. */
const WPCREDITS_INVOLVE_BLOCK = 'wpcredits/get-involved';

/**
 * The student registration form's address while the plugin takes registrations, '' otherwise: the plugin's
 * switch is on and its page published (`WPCPM_Student_Registration_Form::is_open()` and `page_url()`).
 * Every call is guarded, so the theme works without the plugin, and with one older than the form.
 *
 * @return string
 */
function wpcredits_student_registration_url() {
	$form = 'WPCPM_Student_Registration_Form';

	if ( ! class_exists( $form ) || ! method_exists( $form, 'is_open' ) || ! method_exists( $form, 'page_url' ) || ! call_user_func( array( $form, 'is_open' ) ) ) {
		return '';
	}

	return (string) call_user_func( array( $form, 'page_url' ) );
}

/**
 * The rows this reader is offered, in the menu's order, mentors first:
 *
 * - "Mentor students", to the mentor application, left out for a reader who is already a mentor, as the
 *   plugin's own test answers it (`wpcredits_viewer_is_mentor()`);
 * - "Partner as an institution", to the institution application, and "Become a sponsor", to the sponsor
 *   application, each left out for a reader who already acts for one, as the 1.32.10 buttons were
 *   (`wpcredits_partners_offers()`, inc/partners.php); the sponsor row only while the Partners page is
 *   published, the test the Partners link is hooked by (`wpcredits_partners_live()`; the product owner,
 *   5 November 2026);
 * - "Register as a student", to the student registration form, only while the plugin's form is open
 *   (`wpcredits_student_registration_url()`).
 *
 * An address the plugin cannot give falls back as every link to one of the site's own pages does
 * (`wpcredits_hub_url()`).
 *
 * @return array[] Each row: `key` (`mentor`, `institution`, `sponsor`, `student`), `icon` (inc/icons.php),
 *                 `title`, `line` and `url`.
 */
function wpcredits_involve_rows() {
	$rows = array();

	if ( ! wpcredits_viewer_is_mentor() ) {
		$rows[] = array(
			'key'   => 'mentor',
			'icon'  => 'people',
			'title' => __( 'Mentor students', 'wpcredits-theme' ),
			'line'  => __( 'Guide a student\'s first contribution', 'wpcredits-theme' ),
			'url'   => wpcredits_mentor_application_url(),
		);
	}

	if ( wpcredits_partners_offers( 'institution' ) ) {
		$rows[] = array(
			'key'   => 'institution',
			'icon'  => 'institution',
			'title' => __( 'Partner as an institution', 'wpcredits-theme' ),
			'line'  => __( 'A free program for your students', 'wpcredits-theme' ),
			'url'   => wpcredits_hub_url( 'institution_application' ),
		);
	}

	if ( wpcredits_partners_live() && wpcredits_partners_offers( 'sponsor' ) ) {
		$rows[] = array(
			'key'   => 'sponsor',
			'icon'  => 'star',
			'title' => __( 'Become a sponsor', 'wpcredits-theme' ),
			'line'  => __( 'Fund mentors and student work', 'wpcredits-theme' ),
			'url'   => wpcredits_hub_url( 'sponsor_application' ),
		);
	}

	$register = wpcredits_student_registration_url();

	if ( '' !== $register ) {
		$rows[] = array(
			'key'   => 'student',
			'icon'  => 'student',
			'title' => __( 'Register as a student', 'wpcredits-theme' ),
			'line'  => __( 'Join the WordPress Credits Program', 'wpcredits-theme' ),
			'url'   => $register,
		);
	}

	return $rows;
}

/**
 * The rows as a list: each a link holding the role's icon, hidden from screen readers, then its title and
 * its line. On the page a row leads to it is marked as the page being viewed, as the 1.32.7 buttons were on
 * their form's page.
 *
 * @param array[] $rows       As `wpcredits_involve_rows()` gives them.
 * @param string  $labelledby The ID of the heading that names the list, or '' for none.
 * @return string
 */
function wpcredits_involve_rows_html( array $rows, $labelledby = '' ) {
	$viewed = is_singular( 'page' ) ? untrailingslashit( (string) get_permalink( get_queried_object_id() ) ) : '';
	$items  = '';

	foreach ( $rows as $row ) {
		$items .= sprintf(
			'<li class="wpc-involve__row"><a class="wpc-involve__link" href="%1$s"%2$s>%3$s<span class="wpc-involve__text"><span class="wpc-involve__title">%4$s</span> <span class="wpc-involve__line">%5$s</span></span></a></li>',
			esc_url( $row['url'] ),
			'' !== $viewed && untrailingslashit( $row['url'] ) === $viewed ? ' aria-current="page"' : '',
			wpcredits_get_icon( $row['icon'], 20, 'wpc-involve__icon' ),
			esc_html( $row['title'] ),
			esc_html( $row['line'] )
		);
	}

	return sprintf(
		'<ul class="wpc-involve__rows"%1$s>%2$s</ul>',
		'' !== $labelledby ? ' aria-labelledby="' . esc_attr( $labelledby ) . '"' : '',
		$items
	);
}

/**
 * The bar's menu: one navigation item holding the button and the panel it opens, the button's next element.
 * The button is printed plain, with no `aria-expanded` and no `aria-controls`: without JavaScript the panel
 * opens under the pointer and while the focus is in the item (style.css), so a button that said collapsed
 * would be wrong. The block's script gives it both, collapsed and naming the panel, when it takes over. Its
 * chevron is hidden from screen readers. With no row to offer there is no menu at all.
 *
 * @return string '' when the reader is offered no row.
 */
function wpcredits_involve_menu_html() {
	$rows = wpcredits_involve_rows();

	if ( ! $rows ) {
		return '';
	}

	$panel = wp_unique_id( 'wpc-involve-' );

	return sprintf(
		'<li class="wp-block-navigation-item wpc-involve"><button type="button" class="wpc-involve__toggle">%2$s%3$s</button><div class="wpc-involve__panel" id="%1$s">%4$s</div></li>',
		esc_attr( $panel ),
		/* translators: The header's button that opens the list of ways to take part in the program. */
		esc_html__( 'Get involved', 'wpcredits-theme' ),
		wpcredits_get_icon( 'chevron-down', 16, 'wpc-involve__chevron' ),
		wpcredits_involve_rows_html( $rows )
	);
}

/**
 * The panel's list: the same rows under a "Get involved" heading that names them, an `h2` a screen reader
 * finds among the page's headings, for the top of a phone's open menu and of a signed-in reader's Menu panel. No button and nothing to open: it is rows of the panel it
 * sits in.
 *
 * @return string '' when the reader is offered no row.
 */
function wpcredits_involve_list_html() {
	$rows = wpcredits_involve_rows();

	if ( ! $rows ) {
		return '';
	}

	$title = wp_unique_id( 'wpc-involve-title-' );

	return sprintf(
		'<div class="wpc-involve-list"><h2 class="wpc-involve-list__title" id="%1$s">%2$s</h2>%3$s</div>',
		esc_attr( $title ),
		/* translators: The heading over the ways to take part in the program, at the top of the menu on a phone. */
		esc_html__( 'Get involved', 'wpcredits-theme' ),
		wpcredits_involve_rows_html( $rows, $title )
	);
}

/**
 * Hook the Get involved block as a navigation's last item, after the Partners link (inc/partners.php hooks
 * that at priority 10, this runs at 20), for a visitor offered a row. A signed-in reader gets the list in the
 * Menu panel instead (inc/header.php), and is hooked nothing here, so the Site Editor, used signed in, never
 * is.
 *
 * @param string[] $hooked   The block types hooked at this position.
 * @param string   $position Where: before, after, first_child or last_child.
 * @param string   $anchor   The anchor block's type.
 * @return string[]
 */
function wpcredits_involve_hook( $hooked, $position, $anchor ) {
	if ( 'last_child' === $position && 'core/navigation' === $anchor && ! is_user_logged_in() && wpcredits_involve_rows() ) {
		$hooked[] = WPCREDITS_INVOLVE_BLOCK;
	}

	return $hooked;
}
add_filter( 'hooked_block_types', 'wpcredits_involve_hook', 20, 3 );

/**
 * Put the panel's list first in a navigation that holds the bar's menu: in its content, before its list of
 * items, so a phone's open menu starts with the rows under their heading, in reading order. The stylesheet
 * shows the list there alone and hides the bar's item in the open menu. A navigation without the menu is
 * left as it is. After the lone count (priority 11 to its 10), which never marks such a navigation lone.
 *
 * @param string $content Rendered block HTML.
 * @return string
 */
function wpcredits_involve_navigation( $content ) {
	if ( false === strpos( (string) $content, 'wpc-involve__toggle' ) ) {
		return $content;
	}

	if ( ! preg_match( '#<ul\b[^>]*\sclass="[^"]*(?<![\w-])wp-block-navigation__container(?![\w-])[^"]*"[^>]*>#', $content, $list, PREG_OFFSET_CAPTURE ) ) {
		return $content;
	}

	$rows = wpcredits_involve_list_html();

	if ( '' === $rows ) {
		return $content;
	}

	// Since theme 1.32.20 the menu holds the site's own links (inc/site-menu.php), and the open menu
	// reads as the bar does: those links first, then Get involved. The list of items is the last list
	// the navigation holds, a submenu's being inside it, so its end is the last `</ul>`. A navigation
	// whose list does not close is left with the rows first, as they were.
	$end = strrpos( $content, '</ul>' );

	return ( false === $end || $end < $list[0][1] )
		? substr_replace( $content, $rows, $list[0][1], 0 )
		: substr_replace( $content, $rows, $end + 5, 0 );
}
add_filter( 'render_block_core/navigation', 'wpcredits_involve_navigation', 11 );
