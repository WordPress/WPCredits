<?php
/**
 * The Education Hub's content: the six programs of the program index, and the seven student steps
 * and four institution levels of the pathway, every text translated and every link a target.
 *
 * The pathway's steps and levels are in code, as the design note's 7.4 decided: the block prints the
 * same texts with and without JavaScript, and a translator sees each one whole. **The programs are
 * edited in the Site Editor since theme 1.32.0** (the design of 22 October 2026): the program index is
 * a container of Program cards, each card's words its block's own attributes, and the list below is
 * what the hub pattern, and the migration of a template saved before 1.32.0, write into those cards,
 * translated as they are written (`wpcredits_hub_program_cards_markup()`). The texts the patterns'
 * core blocks hold (the section heads, the doors, the closing band) are the patterns' own and
 * editable in the Site Editor too.
 *
 * **A benefit no public page confirms is marked planned** (the design note's 6.11): its `planned` is
 * true. The flag comes off here, in a theme release, the day a public page confirms the item.
 *
 * **The page shows only what is offered today** (two program leads, 21 October 2026: publish only
 * what is fully available now, and keep the rest for when it is): a planned benefit, a planned line
 * of academic recognition and a program in development are kept here and not printed. They come back,
 * each planned one marked "(planned)", when `wpcredits_hub_shows_planned()` answers true: one filter,
 * `add_filter( 'wpcredits_hub_show_planned', '__return_true' )`, brings the future version back. A
 * card's benefits are its block's own since 1.32.0, written with what each program gives today, so
 * the filter brings back the cards in development and the pathway's planned items, and no planned
 * benefit on a card: those stay in the list below, for the day they are offered.
 *
 * A link's target is an outside address, an in-page anchor, or one of the site's own pages, named by
 * `institution_application`, `sponsor_application` or `partners` and read when the block renders
 * (`wpcredits_hub_url()`).
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where a link to one of the site's own pages goes while that page is not published or the plugin is off. */
const WPCREDITS_HUB_HANDBOOK = 'https://make.wordpress.org/community/handbook/education/credits/';

/**
 * One benefit: its text and whether it is only planned.
 *
 * @param string $text    The item, translated.
 * @param bool   $planned Whether no public page confirms it yet.
 * @return array{text: string, planned: bool}
 */
function wpcredits_hub_item( $text, $planned = false ) {
	return array(
		'text'    => (string) $text,
		'planned' => (bool) $planned,
	);
}

/**
 * Whether the hub prints what is only planned: the planned benefits and lines, each marked, and the
 * programs in development. False unless a filter says otherwise, so the page shows what is offered
 * today.
 *
 * @return bool
 */
function wpcredits_hub_shows_planned() {
	/**
	 * Whether the Education Hub prints its planned content, each item marked "(planned)", and the
	 * programs in development.
	 *
	 * @param bool $show False: only what is offered today.
	 */
	return (bool) apply_filters( 'wpcredits_hub_show_planned', false );
}

/**
 * The items the hub prints: every one when it shows what is planned, else the offered ones alone.
 *
 * @param array[] $items Items, as `wpcredits_hub_item()` makes them.
 * @return array[]
 */
function wpcredits_hub_shown( array $items ) {
	if ( wpcredits_hub_shows_planned() ) {
		return array_values( $items );
	}

	return array_values(
		array_filter(
			$items,
			static function ( $item ) {
				return empty( $item['planned'] );
			}
		)
	);
}

/**
 * One course of a program, for the band of its courses under the index: its name, its public page,
 * the short line above its name (its hours where its page gives them, who it is for where it does
 * not), and one line on what it asks.
 *
 * @param string $name    Its name, translated.
 * @param string $eyebrow The line above its name, translated.
 * @param string $line    What it asks, a sentence, translated.
 * @param string $url     Its public page.
 * @return array{name: string, eyebrow: string, line: string, url: string}
 */
function wpcredits_hub_course( $name, $eyebrow, $line, $url ) {
	return array(
		'name'    => (string) $name,
		'eyebrow' => (string) $eyebrow,
		'line'    => (string) $line,
		'url'     => (string) $url,
	);
}

/**
 * One link: its label and its target.
 *
 * @param string $label  The label, translated.
 * @param string $target An address, an anchor, `institution_application`, `sponsor_application` or `partners`.
 * @return array{label: string, target: string}
 */
function wpcredits_hub_link( $label, $target ) {
	return array(
		'label'  => (string) $label,
		'target' => (string) $target,
	);
}

/**
 * Where a link's target goes: the site's institution or sponsorship application, or its Partners
 * page, while that page is published and the plugin can say where it is, the WordPress Credits
 * handbook otherwise; any other target as it is.
 *
 * @param string $target A link's target.
 * @return string An address, not escaped.
 */
function wpcredits_hub_url( $target ) {
	$pages = array(
		'institution_application' => 'WPCPM_Institution_Application',
		'sponsor_application'     => 'WPCPM_Sponsor_Application',
		'partners'                => 'WPCPM_Sponsors_Page',
	);

	if ( ! isset( $pages[ $target ] ) ) {
		return (string) $target;
	}

	$class = $pages[ $target ];
	$url   = class_exists( $class ) && method_exists( $class, 'page_url' ) ? (string) call_user_func( array( $class, 'page_url' ) ) : '';

	return '' !== $url ? $url : WPCREDITS_HUB_HANDBOOK;
}

/**
 * The mentor application's address: the plugin's form page while it is published, at /become-a-mentor/
 * (the plugin's `WPCPM_Mentor_Application_Form`), and that address otherwise, so the header's Get involved
 * row and the homepage's Mentors card lead where the design says with an older plugin, or none (theme
 * 1.32.12). Not `wpcredits_hub_url()`, whose fallback is the handbook: the design names this address.
 *
 * @return string
 */
function wpcredits_mentor_application_url() {
	$url = class_exists( 'WPCPM_Mentor_Application_Form' ) && method_exists( 'WPCPM_Mentor_Application_Form', 'page_url' ) ? (string) WPCPM_Mentor_Application_Form::page_url() : '';

	return '' !== $url ? $url : home_url( '/become-a-mentor/' );
}

/**
 * The field of words a hub picture is cut from: 42 rows 12px apart, from 96px above the picture to 4px
 * under it, as svg text. Each row is the pathway's step names in the hub's order, begun three names
 * further on than the row above and set further left, so no two rows line up, and repeated until it is
 * 130 characters long, wider than the picture from any of its starting points. The mountain is cut
 * from it, and since theme 1.32.15 the students' graduation cap.
 *
 * @return string
 */
function wpcredits_hub_word_rows() {
	$names = array_column( wpcredits_hub_steps(), 'name' );
	$rows  = '';

	for ( $row = 0; $row < 42; $row++ ) {
		$from  = ( $row * 3 ) % count( $names );
		$cycle = implode( ' // ', array_merge( array_slice( $names, $from ), array_slice( $names, 0, $from ) ) );
		$line  = $cycle;

		while ( mb_strlen( $line ) < 130 ) {
			$line .= ' // ' . $cycle;
		}

		$rows .= '<text x="' . ( -14 - $row * 23 % 64 ) . '" y="' . ( -96 + 12 * $row ) . '">' . esc_html( $line ) . '</text>';
	}

	return $rows;
}

/**
 * A person in a hub picture: a head and shoulders on a round badge, as svg markup.
 *
 * @param float  $cx       The badge's center, x.
 * @param float  $cy       The badge's center, y.
 * @param float  $scale    1 is a 25px badge.
 * @param string $modifier The class that colors it: `wpc-hub-art__person--student` or `--guide`.
 * @return string
 */
function wpcredits_hub_art_person( $cx, $cy, $scale, $modifier ) {
	$head = 3.2 * $scale;
	$half = 6 * $scale;

	return '<g class="wpc-hub-art__person ' . esc_attr( $modifier ) . '"><circle class="wpc-hub-art__badge" cx="' . $cx . '" cy="' . $cy . '" r="' . round( 12.5 * $scale, 1 ) . '"/>'
		. '<circle cx="' . $cx . '" cy="' . round( $cy - 4 * $scale, 1 ) . '" r="' . round( $head, 1 ) . '"/>'
		. '<path d="M' . round( $cx - $half, 1 ) . ' ' . round( $cy + 7 * $scale, 1 ) . ' a' . round( $half, 1 ) . ' ' . round( $half, 1 ) . ' 0 0 1 ' . round( 2 * $half, 1 ) . ' 0 Z"/></g>';
}

/**
 * A label in a hub picture: a box sized from the label's length, the picture's type being monospaced.
 *
 * @param float  $x        The box's left edge.
 * @param float  $y        The box's top edge.
 * @param string $label    The words.
 * @param string $modifier A class for the box: `wpc-hub-art__tag--guide` fills it in the brand blue.
 * @return string
 */
function wpcredits_hub_art_tag( $x, $y, $label, $modifier ) {
	$width = (int) ceil( 7.3 * mb_strlen( $label ) + 20 );

	return '<g class="wpc-hub-art__tag ' . esc_attr( $modifier ) . '"><rect x="' . $x . '" y="' . $y . '" width="' . $width . '" height="26" rx="2"/><text x="' . ( $x + 10 ) . '" y="' . ( $y + 17.5 ) . '">' . esc_html( $label ) . '</text></g>';
}

/**
 * The hero's mountain of words, as an inline svg: rows of the pathway's step names, in the picture's own
 * 520 by 380px space, cut to two peaks by a mask, a switchback trail up to a flag on the higher one, and
 * its snow cap in the brand blue. The hero draws it behind its editor pieces (patterns/hub-hero.php), and
 * the Notice and 404 pages draw it alone (patterns/hub-mountain.php, theme 1.32.12), so it is written
 * once. Its IDs are fixed: a page draws it once.
 *
 * @param string $extra Svg markup to draw over the mountain, in its space, already escaped (theme
 *                      1.32.14: the mentors' page puts a guide and students on the trail); '' for
 *                      the mountain alone.
 * @return string
 */
function wpcredits_hub_mountain_svg( $extra = '' ) {
	$rows = wpcredits_hub_word_rows();

	// The mountain's shape in the same space: the higher peak above the hero's chart, the lower one above
	// its toolbar, and the notch between them in the gap over the selected block; the trail from the foot
	// to the flag, through the gaps between the pieces; the snow cap's jagged edge under the summit.
	$mountain = 'M0 384 L0 58 L110 -30 L227 64 L390 -70 L520 45 L520 384 Z';
	$trail    = 'M150 384 L330 316 L296 230 L60 186 L250 66 L330 12 L430 -14 L378 -44 L390 -70';
	$cap      = 'M330 -30 L390 -110 L450 -30 L432 -26 L418 -38 L404 -24 L390 -36 L376 -24 L362 -36 L348 -26 Z';
	$area     = 'maskUnits="userSpaceOnUse" x="-10" y="-104" width="540" height="494"';
	$gap      = '<path d="' . $trail . '" fill="none" stroke="#000" stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>';

	return '<svg class="wpc-hub-art__mountain" viewBox="-10 -104 540 494" focusable="false"><defs><g id="wpc-hub-mountain-words">' . $rows . '</g>'
		. '<mask id="wpc-hub-mountain-body" ' . $area . '><path d="' . esc_attr( $mountain ) . '" fill="#fff"/><path d="' . esc_attr( $cap ) . '" fill="#000"/>' . $gap . '</mask>'
		. '<mask id="wpc-hub-mountain-cap" ' . $area . '><path d="' . esc_attr( $mountain ) . '" fill="#fff"/><path d="M-10 -104 H530 V390 H-10 Z ' . esc_attr( $cap ) . '" fill="#000" fill-rule="evenodd"/>' . $gap . '</mask></defs>'
		. '<use href="#wpc-hub-mountain-words" class="wpc-hub-art__mountain-body" mask="url(#wpc-hub-mountain-body)"/><use href="#wpc-hub-mountain-words" class="wpc-hub-art__mountain-cap" mask="url(#wpc-hub-mountain-cap)"/>'
		. '<path class="wpc-hub-art__route" d="' . esc_attr( $trail ) . '"/><path class="wpc-hub-art__pole" d="M390 -70 V-98"/><path class="wpc-hub-art__pennant" d="M390 -98 L414 -90 L390 -82 Z"/>' . $extra . '</svg>';
}

/**
 * A guide and three students on the mountain's trail (theme 1.32.14), for the mentors' page: svg markup
 * that `wpcredits_hub_mountain_svg()` takes as its `$extra` and draws over the mountain, in its space.
 *
 * Four people on the trail's second stretch, roped together: the students as dark figures on white
 * badges, the guide ahead of them, larger, white on the brand blue, and a label beside each party. The
 * labels are the only words, and the picture is decoration all the same (the pattern hides it from
 * screen readers), so each label's box is sized from its length: the mountain's type is monospaced.
 *
 * @return string
 */
function wpcredits_hub_mountain_team_svg() {
	return '<path class="wpc-hub-art__rope" d="M72 178.5 L193.5 101.6"/>'
		. wpcredits_hub_art_person( 72, 178.5, 1.4, 'wpc-hub-art__person--student' )
		. wpcredits_hub_art_person( 107, 156, 1.4, 'wpc-hub-art__person--student' )
		. wpcredits_hub_art_person( 143, 134, 1.4, 'wpc-hub-art__person--student' )
		. wpcredits_hub_art_person( 193.5, 101.6, 2, 'wpc-hub-art__person--guide' )
		. wpcredits_hub_art_tag( 226, 100, __( 'You, the guide', 'wpcredits-theme' ), 'wpc-hub-art__tag--guide' )
		. wpcredits_hub_art_tag( 150, 164, __( 'Your students', 'wpcredits-theme' ), 'wpc-hub-art__tag--students' );
}

/**
 * The students' picture (theme 1.32.15): a graduation cap cut from the hub's field of words. The
 * board on top is set in the brand blue, the cap under it in the picture's gray, and a dashed tassel
 * runs from the board's middle to its corner and hangs down to a tuft. One label names what a student
 * earns. It is drawn in the mountain's space and with its classes, so hub.css dresses both alike; its
 * IDs are its own, so a page may hold it beside the mountain. It is decoration all the same.
 *
 * @return string
 */
function wpcredits_hub_graduation_svg() {
	$rows = wpcredits_hub_word_rows();

	// The board, a flat diamond seen from above its front corner; the cap, hanging under the board's
	// two front edges with a rounded foot; the tassel, from the button to the right corner and down.
	$board  = 'M260 20 L490 120 L260 220 L30 120 Z';
	$under  = 'M126 176 L260 234 L394 176 V272 Q260 344 126 272 Z';
	$tassel = 'M260 120 L446 132 L446 288';
	$area   = 'maskUnits="userSpaceOnUse" x="-10" y="-104" width="540" height="494"';
	$gap    = '<path d="' . $tassel . '" fill="none" stroke="#000" stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>';

	return '<svg class="wpc-hub-art__mountain wpc-hub-art__graduation" viewBox="-10 -104 540 494" focusable="false"><defs><g id="wpc-hub-graduation-words">' . $rows . '</g>'
		. '<mask id="wpc-hub-graduation-under" ' . $area . '><path d="' . esc_attr( $under ) . '" fill="#fff"/></mask>'
		. '<mask id="wpc-hub-graduation-board" ' . $area . '><path d="' . esc_attr( $board ) . '" fill="#fff"/>' . $gap . '</mask></defs>'
		. '<use href="#wpc-hub-graduation-words" class="wpc-hub-art__mountain-body" mask="url(#wpc-hub-graduation-under)"/><use href="#wpc-hub-graduation-words" class="wpc-hub-art__mountain-cap" mask="url(#wpc-hub-graduation-board)"/>'
		. '<path class="wpc-hub-art__route" d="' . esc_attr( $tassel ) . '"/><circle class="wpc-hub-art__button" cx="260" cy="120" r="6"/><path class="wpc-hub-art__pennant" d="M438 288 H454 L460 334 H432 Z"/>'
		. wpcredits_hub_art_tag( 286, 330, __( 'Academic credit', 'wpcredits-theme' ), 'wpc-hub-art__tag--credit' )
		. '</svg>';
}

/**
 * The homepage's band of role cards (theme 1.32.12, the product owner's design of 5 November 2026), as
 * block markup: what the "Ways to take part" pattern prints (patterns/hub-roles.php) and what the 1.32.12
 * migration writes in place of a saved template's "Who this is for" section (inc/migrate-1-32-12.php), so
 * the two never differ.
 *
 * A screen-reader heading over a grid of cards: students, mentors and institutions written here, every
 * word of them then the saved template's to edit, and the sponsors' card, a block the theme draws only
 * while the Partners page is published (blocks/sponsor-card), last. Each card is the doors' card, so its link
 * covers it and its focus ring is drawn around it by the doors' rules (hub.css); its icon is drawn by
 * hub.css from its class (`wpc-hub-role--students` and the others), so the card holds no Custom HTML.
 *
 * @return string Block markup.
 */
function wpcredits_hub_roles_markup() {
	$roles = array(
		array(
			'class' => 'wpc-hub-role--students',
			'title' => __( 'Students', 'wpcredits-theme' ),
			'line'  => __( 'Earn credit by contributing', 'wpcredits-theme' ),
			'link'  => __( 'Find where to start', 'wpcredits-theme' ),
			// Where the hero's button goes: the programs.
			'href'  => '#programs',
		),
		array(
			'class' => 'wpc-hub-role--mentors',
			'title' => __( 'Mentors', 'wpcredits-theme' ),
			'line'  => __( 'A few hours a week, a real impact', 'wpcredits-theme' ),
			'link'  => __( 'Become a mentor', 'wpcredits-theme' ),
			'href'  => wpcredits_mentor_application_url(),
		),
		array(
			'class' => 'wpc-hub-role--institutions',
			'title' => __( 'Institutions', 'wpcredits-theme' ),
			'line'  => __( 'Bring the program to campus', 'wpcredits-theme' ),
			'link'  => __( 'Partner with us', 'wpcredits-theme' ),
			'href'  => wpcredits_hub_url( 'institution_application' ),
		),
	);

	$cards = '';

	foreach ( $roles as $role ) {
		$class  = 'is-style-card wpc-hub-door wpc-hub-role ' . $role['class'];
		$cards .= '<!-- wp:group ' . wp_json_encode(
			array(
				'className' => $class,
				'layout'    => array( 'type' => 'default' ),
			)
		) . " -->\n"
			. '<div class="wp-block-group ' . esc_attr( $class ) . "\"><!-- wp:heading {\"level\":3} -->\n"
			. '<h3 class="wp-block-heading">' . esc_html( $role['title'] ) . "</h3>\n<!-- /wp:heading -->\n\n"
			. "<!-- wp:paragraph {\"className\":\"wpc-hub-door__text\"} -->\n"
			. '<p class="wpc-hub-door__text">' . esc_html( $role['line'] ) . "</p>\n<!-- /wp:paragraph -->\n\n"
			. "<!-- wp:paragraph {\"className\":\"wpc-hub-door__go\"} -->\n"
			. '<p class="wpc-hub-door__go"><a href="' . esc_url( $role['href'] ) . '">' . esc_html( $role['link'] ) . '<span aria-hidden="true"> →</span></a></p>' . "\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n";
	}

	return "<!-- wp:group {\"align\":\"wide\",\"className\":\"wpc-hub-roles\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"48px\",\"bottom\":\"48px\"}}},\"layout\":{\"type\":\"default\"}} -->\n"
		. "<div class=\"wp-block-group alignwide wpc-hub-roles\" style=\"padding-top:48px;padding-bottom:48px\"><!-- wp:heading {\"className\":\"screen-reader-text\"} -->\n"
		. '<h2 class="wp-block-heading screen-reader-text">' . esc_html__( 'Get involved', 'wpcredits-theme' ) . "</h2>\n<!-- /wp:heading -->\n\n"
		. "<!-- wp:group {\"className\":\"wpc-hub-roles__grid\",\"layout\":{\"type\":\"default\"}} -->\n"
		. '<div class="wp-block-group wpc-hub-roles__grid">' . $cards . "<!-- wp:wpcredits/sponsor-card /--></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->";
}

/**
 * The six programs, in the order the index lists them.
 *
 * @return array[] Each: `slug`, `name`, `audiences` (`student`, `faculty`, `career`), `status`
 *                 (`live` or `development`), `what`, `time`, `gets` (items), `courses` (the band of
 *                 its courses under the index, WordPress Credits' alone) and `links`. A card names
 *                 no step of the pathway (a program lead, 20 October 2026: a reader meets the steps
 *                 only further down the page).
 */
function wpcredits_hub_programs() {
	return array(
		array(
			'slug'      => 'campus-connect',
			'name'      => __( 'Campus Connect', 'wpcredits-theme' ),
			'audiences' => array( 'student' ),
			'status'    => 'live',
			'what'      => __( 'A half or full-day hands-on WordPress workshop on campus. No experience needed, and anyone connected to the institution can organize one.', 'wpcredits-theme' ),
			'time'      => __( 'Half or full day', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Certificate of participation', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Find or host an event', 'wpcredits-theme' ), 'https://events.wordpress.org/campusconnect/' ),
			),
		),
		array(
			'slug'      => 'wordpress-credits',
			'name'      => __( 'WordPress Credits', 'wpcredits-theme' ),
			'audiences' => array( 'student', 'career' ),
			'status'    => 'live',
			'what'      => __( 'Mentored work on the WordPress open source project, matched to your field of study. Ends with a presentation and a Foundation certificate.', 'wpcredits-theme' ),
			'time'      => __( '50h or 150h+, about a semester', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Certificate', 'wpcredits-theme' ) ),
				/* translators: A benefit a program gives, a noun: a digital badge. */
				wpcredits_hub_item( __( 'Badge', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Hours transcript', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( _x( 'Credit', 'academic credit a program gives, a noun, as in credit toward a degree', 'wpcredits-theme' ) ),
			),
			// The band of its courses under the index (a program lead and a program team member, 15 Oct 2026;
			// the owner, 16 Oct 2026): each says what the course's public Learn page says. The hours are Intro
			// to Contribution's page's, and Foundations' are the owner's (16 Oct 2026: the base course, 150
			// hours or more), what that format suits being the program page's; the tracks' pages give no
			// hours, so they say who they are for. The Translation track was added on 19 Oct 2026, from a
			// program lead's comment in the hub's content review: its page is the course's own, and its line
			// is that page's description in the band's short style.
			'courses'   => array(
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress. */
					__( 'Intro to Contribution', 'wpcredits-theme' ),
					/* translators: The line above a course's name: how long it takes. */
					__( '50 hours', 'wpcredits-theme' ),
					__( 'One guided contribution project: a first look at open source, for a shorter course or an elective.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-intro-to-contribution/'
				),
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress, its base course; not foundations as organizations. */
					__( 'Foundations', 'wpcredits-theme' ),
					/* translators: The line above a course's name: how long it takes. */
					__( '150 hours or more', 'wpcredits-theme' ),
					__( 'Your own site and a contribution that ships, for an internship, a capstone, or a full semester.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-foundations/'
				),
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress. */
					__( 'Developer track', 'wpcredits-theme' ),
					/* translators: The line above a course's name: who it is for. */
					__( 'For developers', 'wpcredits-theme' ),
					__( 'WordPress developer training and patch testing practice, toward a contribution that ships.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-developer-track/'
				),
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress. */
					__( 'Designer track', 'wpcredits-theme' ),
					/* translators: The line above a course's name: who it is for. */
					__( 'For designers', 'wpcredits-theme' ),
					__( 'A portfolio, Figma and the Site Editor\'s design tools, and a block pattern of your own.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-designer-track/'
				),
				wpcredits_hub_course(
					/* translators: The name of a WordPress Credits course on Learn WordPress. */
					__( 'Translation track', 'wpcredits-theme' ),
					/* translators: The line above a course's name: who it is for. */
					__( 'For translators', 'wpcredits-theme' ),
					__( 'Translation skills and real work with the Polyglots team: translate.wordpress.org and GlotPress, locale glossaries, software strings, and Learn WordPress lessons.', 'wpcredits-theme' ),
					'https://learn.wordpress.org/course/wordpress-credits-translation-track/'
				),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Compare the five courses', 'wpcredits-theme' ), '#wordpress-credits-courses' ),
				wpcredits_hub_link( __( 'About the program', 'wpcredits-theme' ), 'https://wordpress.org/education/credits/' ),
				wpcredits_hub_link( __( 'Impact dashboard', 'wpcredits-theme' ), 'https://wordpress.github.io/WPCredits/' ),
			),
		),
		array(
			'slug'      => 'facilitator-training',
			'name'      => __( 'Facilitator Training', 'wpcredits-theme' ),
			'audiences' => array( 'faculty' ),
			'status'    => 'live',
			'what'      => __( 'Free, self-guided courses plus step-by-step facilitation guides with timed agendas and activity sheets, so faculty can teach WordPress themselves.', 'wpcredits-theme' ),
			'time'      => __( 'Self-paced', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Ready-to-run sessions', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Facilitator listing', 'wpcredits-theme' ), true ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Facilitation guides', 'wpcredits-theme' ), 'https://make.wordpress.org/community/handbook/education/wordpress-facilitator-training-program/' ),
				wpcredits_hub_link( __( 'Courses', 'wpcredits-theme' ), 'https://learn.wordpress.org/' ),
			),
		),
		array(
			'slug'      => 'learn-wordpress',
			'name'      => __( 'Learn WordPress', 'wpcredits-theme' ),
			'audiences' => array( 'student', 'faculty' ),
			'status'    => 'live',
			'what'      => __( 'Free courses, lessons, and online workshops for every skill level, from building a first site to developing blocks.', 'wpcredits-theme' ),
			'time'      => __( 'Self-paced', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Course completions on your profile', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Browse courses', 'wpcredits-theme' ), 'https://learn.wordpress.org/' ),
			),
		),
		array(
			'slug'      => 'ai-leaders',
			'name'      => __( 'AI Leaders', 'wpcredits-theme' ),
			'audiences' => array( 'career' ),
			'status'    => 'live',
			'what'      => __( 'The first Foundation-backed micro-credential. Piloted in 2026 with students from Illinois and Louisiana.', 'wpcredits-theme' ),
			'time'      => __( 'Cohort-based', 'wpcredits-theme' ),
			'gets'      => array(
				wpcredits_hub_item( __( 'Micro-credential', 'wpcredits-theme' ) ),
				/* translators: The amount is in US dollars. */
				wpcredits_hub_item( __( '$1,000 for completers', 'wpcredits-theme' ), true ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Learn more', 'wpcredits-theme' ), 'https://ai-leaders.org/' ),
			),
		),
		array(
			'slug'      => 'wordpress-builder-certification',
			'name'      => __( 'WordPress Builder Certification', 'wpcredits-theme' ),
			'audiences' => array( 'career' ),
			'status'    => 'development',
			'what'      => __( 'A certification exam validating the skills to build and run a personal or company website on WordPress.', 'wpcredits-theme' ),
			/* translators: A program's time on its card, a noun: an exam to sit, not a course to attend. */
			'time'      => __( 'Exam', 'wpcredits-theme' ),
			// Not marked: the card's "In development" tag already says it is not offered yet.
			'gets'      => array(
				wpcredits_hub_item( __( 'Industry certification', 'wpcredits-theme' ) ),
			),
			'links'     => array(
				wpcredits_hub_link( __( 'Learn more', 'wpcredits-theme' ), 'https://wordpresscertifications.com/wordpress-builder-certification/' ),
			),
		),
	);
}

/**
 * The seven student steps, from the first workshop to the first job.
 *
 * @return array[] Each: `name`, `by` (the staircase's line), `earned` (the detail's sentence),
 *                 `items` (what it unlocks), `recognition` (an item: the line the program lead found
 *                 not offered today, on 21 October 2026, at Builder, Community Leader and
 *                 Credentialed, is planned) and `next`, a link.
 */
function wpcredits_hub_steps() {
	return array(
		array(
			'name'        => __( 'Attendee', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Attend a Campus Connect event', 'wpcredits-theme' ),
			'earned'      => __( 'Attend a Campus Connect event.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Certificate of participation', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'A seeded WordPress.org profile', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A profile badge', 'wpcredits-theme' ) ),
			),
			'recognition' => wpcredits_hub_item( __( 'Recognized co-curricular activity or event credit, where the school offers it.', 'wpcredits-theme' ) ),
			'next'        => wpcredits_hub_link( __( 'Find a Campus Connect event', 'wpcredits-theme' ), 'https://events.wordpress.org/campusconnect/' ),
		),
		array(
			'name'        => __( 'Builder', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Launch a live site with real content', 'wpcredits-theme' ),
			'earned'      => __( 'Launch a live site with real content.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Showcase eligibility', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A profile badge', 'wpcredits-theme' ), true ),
			),
			'recognition' => wpcredits_hub_item( __( 'A portfolio piece that can be assessed within an existing course.', 'wpcredits-theme' ), true ),
			'next'        => wpcredits_hub_link( __( 'Learn to build a site', 'wpcredits-theme' ), 'https://learn.wordpress.org/' ),
		),
		array(
			'name'        => __( 'Contributor', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Get your first contribution accepted', 'wpcredits-theme' ),
			'earned'      => __( 'Get your first contribution accepted.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Contribution badge', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Make team channels', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Mentor matching', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Retention support starts here', 'wpcredits-theme' ), true ),
			),
			'recognition' => wpcredits_hub_item( __( 'Open source work logged toward course or program requirements.', 'wpcredits-theme' ) ),
			'next'        => wpcredits_hub_link( __( 'Start WordPress Credits', 'wpcredits-theme' ), '#program-wordpress-credits' ),
		),
		array(
			'name'        => _x( 'Graduate', 'a step on the student pathway, a noun: someone who has completed a track', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Complete a WordPress Credits track', 'wpcredits-theme' ),
			'earned'      => __( 'Complete a WordPress Credits track.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Foundation certificate', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Digital badge', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Verified hours transcript', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Alumni directory listing', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'WordCamp ticket support', 'wpcredits-theme' ), true ),
			),
			'recognition' => wpcredits_hub_item( __( 'Credit-bearing.', 'wpcredits-theme' ) ),
			'next'        => wpcredits_hub_link( __( 'See the courses', 'wpcredits-theme' ), '#wordpress-credits-courses' ),
		),
		array(
			'name'        => __( 'Specialist', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Complete a second or third program', 'wpcredits-theme' ),
			'earned'      => __( 'Complete a second or third program.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Deeper specialization', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Scoped project work', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Priority mentor matching', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A specialist badge', 'wpcredits-theme' ), true ),
			),
			'recognition' => wpcredits_hub_item( __( 'Credit-bearing as an advanced elective, independent study, practicum, or capstone.', 'wpcredits-theme' ) ),
			'next'        => wpcredits_hub_link( __( 'Explore programs', 'wpcredits-theme' ), '#programs' ),
		),
		array(
			'name'        => __( 'Community Leader', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Facilitate, mentor, organize, or speak', 'wpcredits-theme' ),
			'earned'      => __( 'Facilitate, mentor, organize, or speak.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'Listing in the facilitator and speaker directory', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Discounted or free WordCamp access', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Priority on paid teaching leads', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Organizing support', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'A profile badge', 'wpcredits-theme' ) ),
			),
			'recognition' => wpcredits_hub_item( __( 'A role the school already records: teaching assistant, peer mentor, or student organization leader.', 'wpcredits-theme' ), true ),
			'next'        => wpcredits_hub_link( __( 'Try Facilitator Training', 'wpcredits-theme' ), '#program-facilitator-training' ),
		),
		array(
			'name'        => __( 'Credentialed', 'wpcredits-theme' ),
			/* translators: What earns a step, as the staircase prints it under the step's name. */
			'by'          => __( 'Earn a micro-credential', 'wpcredits-theme' ),
			'earned'      => __( 'Earn a micro-credential.', 'wpcredits-theme' ),
			'items'       => array(
				wpcredits_hub_item( __( 'An Open to Work listing on jobs.wordpress.net', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'Credentials shown on your profile', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Showcase eligibility', 'wpcredits-theme' ), true ),
			),
			'recognition' => wpcredits_hub_item( __( 'A stackable credential accepted toward a degree, certificate, or transcript notation.', 'wpcredits-theme' ), true ),
			'next'        => wpcredits_hub_link( __( 'Where this leads: WordPress Jobs', 'wpcredits-theme' ), 'https://jobs.wordpress.net/' ),
		),
	);
}

/**
 * The four institution partner levels.
 *
 * @return array[] Each: `name`, `by` (the staircase's line), `reached` (the detail's sentence),
 *                 `items` (what it gets) and `next`, a link.
 */
function wpcredits_hub_levels() {
	return array(
		array(
			'name'    => __( 'Participating', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Host one Campus Connect event', 'wpcredits-theme' ),
			'reached' => __( 'Host one Campus Connect event.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'A listing on the WordPress education page', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A logo kit', 'wpcredits-theme' ), true ),
			),
			'next'    => wpcredits_hub_link( __( 'Request a Campus Connect event', 'wpcredits-theme' ), 'https://events.wordpress.org/campusconnect/organize/' ),
		),
		array(
			'name'    => _x( 'Partner', 'a level on the institution pathway, a noun: an institution that has signed a partnership agreement', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Sign a partnership agreement and complete one WordPress Credits group', 'wpcredits-theme' ),
			'reached' => __( 'Sign a partnership agreement and complete one WordPress Credits group.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'A partner badge for your site', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'An Institution Dashboard', 'wpcredits-theme' ) ),
				wpcredits_hub_item( __( 'An auto-generated impact report each semester', 'wpcredits-theme' ) ),
			),
			'next'    => wpcredits_hub_link( __( 'Partner as an institution', 'wpcredits-theme' ), 'institution_application' ),
		),
		array(
			'name'    => __( 'Established', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Run recurring groups with growing student numbers, at least one trained faculty facilitator, and students sitting certification exams', 'wpcredits-theme' ),
			'reached' => __( 'Run recurring groups with growing student numbers, at least one trained faculty facilitator, and students sitting certification exams.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'Named regional support', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A WordCamp speaking slot', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Co-marketing', 'wpcredits-theme' ), true ),
			),
			'next'    => wpcredits_hub_link( __( 'Try Facilitator Training', 'wpcredits-theme' ), '#program-facilitator-training' ),
		),
		array(
			'name'    => __( 'Flagship', 'wpcredits-theme' ),
			/* translators: What reaches a level, as the staircase prints it under the level's name. */
			'by'      => __( 'Integrate WordPress into the curriculum, with faculty mentoring across institutions and sustained numbers of certified students', 'wpcredits-theme' ),
			'reached' => __( 'Integrate WordPress into the curriculum, with faculty mentoring across institutions and sustained numbers of certified students.', 'wpcredits-theme' ),
			'items'   => array(
				wpcredits_hub_item( __( 'A case study', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'A Foundation announcement', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Input on the program\'s direction', 'wpcredits-theme' ), true ),
				wpcredits_hub_item( __( 'Event hosting', 'wpcredits-theme' ), true ),
			),
			'next'    => wpcredits_hub_link( __( 'Talk to the program team', 'wpcredits-theme' ), 'institution_application' ),
		),
	);
}

/**
 * A list of the benefits the hub prints (`wpcredits_hub_shown()`), one to a line, each planned one
 * followed by "(planned)" in its own span, kept on the item's line by a no-break space so it never
 * wraps alone.
 *
 * @param array[] $items Items, as `wpcredits_hub_item()` makes them.
 * @return string Escaped markup, or '' when none of them is printed.
 */
function wpcredits_hub_items_html( array $items ) {
	$items = wpcredits_hub_shown( $items );

	if ( ! $items ) {
		return '';
	}

	$html = '<ul class="wpc-hub-items">';

	foreach ( $items as $item ) {
		$html .= '<li>' . esc_html( $item['text'] );

		if ( ! empty( $item['planned'] ) ) {
			$html .= '&nbsp;<span class="wpc-hub-planned">' . esc_html_x( '(planned)', 'a benefit not offered yet', 'wpcredits-theme' ) . '</span>';
		}

		$html .= '</li>';
	}

	return $html . '</ul>';
}

/**
 * One line the hub prints on its own, such as a step's academic recognition: its text, and
 * "(planned)" after it when it is planned and the hub shows what is planned.
 *
 * @param array $item An item, as `wpcredits_hub_item()` makes it.
 * @return string Escaped markup, or '' when the line is not printed.
 */
function wpcredits_hub_line_html( array $item ) {
	if ( ! wpcredits_hub_shown( array( $item ) ) ) {
		return '';
	}

	$html = esc_html( $item['text'] );

	if ( ! empty( $item['planned'] ) ) {
		$html .= '&nbsp;<span class="wpc-hub-planned">' . esc_html_x( '(planned)', 'a benefit not offered yet', 'wpcredits-theme' ) . '</span>';
	}

	return $html;
}

/*
 * The blocks Administrators edit in the Site Editor (theme 1.32.0): what their attributes hold, how
 * the hub reads them, and the block markup the hub pattern and the migration write.
 */

/**
 * The tags a text edited in the Site Editor keeps where the hub prints it: the editor's bold and
 * italic, and a line break. Any other tag is taken out and its words kept.
 *
 * @return array<string, array> For wp_kses().
 */
function wpcredits_hub_text_tags() {
	return array(
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);
}

/**
 * Whether a text from the Site Editor says nothing: no character but white space once its tags are
 * taken out and its entities read.
 *
 * @param string $text A text, as rich text keeps it.
 * @return bool
 */
function wpcredits_hub_blank( $text ) {
	$words = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );

	return '' === (string) preg_replace( '/[\s\x{00A0}]+/u', '', $words );
}

/**
 * A plain text as a block's rich-text attribute keeps it: escaped, as the editor escapes what is
 * typed, so an ampersand or an angle bracket in a translation stays a character.
 *
 * @param string $text The text, translated.
 * @return string
 */
function wpcredits_hub_rich( $text ) {
	return esc_html( (string) $text );
}

/**
 * A slug or an anchor as an id carries it: lower case, letters, digits, hyphens and underscores, any
 * other run of characters one hyphen, none at either end.
 *
 * @param string $value What was typed.
 * @return string
 */
function wpcredits_hub_anchor( $value ) {
	return trim( (string) preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( trim( (string) $value ) ) ), '-' );
}

/**
 * One attribute of a block as a string: '' for one that is missing or not a string.
 *
 * @param array  $attributes The block's attributes.
 * @param string $key        The attribute.
 * @return string
 */
function wpcredits_hub_attribute( array $attributes, $key ) {
	return isset( $attributes[ $key ] ) && is_scalar( $attributes[ $key ] ) ? (string) $attributes[ $key ] : '';
}

/**
 * One attribute of a block as a list of strings, each trimmed, an empty one left out.
 *
 * @param array  $attributes The block's attributes.
 * @param string $key        The attribute.
 * @return string[]
 */
function wpcredits_hub_strings( array $attributes, $key ) {
	$list = array();

	foreach ( isset( $attributes[ $key ] ) && is_array( $attributes[ $key ] ) ? $attributes[ $key ] : array() as $item ) {
		$item = is_scalar( $item ) ? trim( (string) $item ) : '';

		if ( '' !== $item ) {
			$list[] = $item;
		}
	}

	return $list;
}

/**
 * A block as parse_blocks() gives it, for a block with no inner blocks: what render_block() takes.
 *
 * @param string $name       The block's name.
 * @param array  $attributes Its attributes.
 * @return array
 */
function wpcredits_hub_parsed_block( $name, array $attributes ) {
	return array(
		'blockName'    => $name,
		'attrs'        => $attributes,
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
}

/**
 * A block as the Site Editor saves it: a void block when it holds nothing, else its inner blocks one
 * to a paragraph between its comments, as the editor writes a block whose save is its inner blocks.
 *
 * @param string   $name       The block's name.
 * @param array    $attributes Its attributes.
 * @param string[] $inner      Its inner blocks' markup.
 * @return string
 */
function wpcredits_hub_block_markup( $name, array $attributes = array(), array $inner = array() ) {
	return get_comment_delimited_block_content( $name, $attributes, $inner ? "\n" . implode( "\n\n", $inner ) . "\n" : '' );
}

/**
 * A program card as the hub draws it, from a Program card block's attributes: the slug as an id
 * carries it, the status `live` unless it is `development`, the audiences the chips know in the
 * chips' order, the benefits trimmed, and each link with both its label and its address.
 *
 * @param array $attributes The block's attributes.
 * @return array{slug: string, name: string, status: string, audiences: string[], what: string, time: string, gets: string[], links: array[]}
 */
function wpcredits_hub_card( array $attributes ) {
	$links = array();

	foreach ( isset( $attributes['links'] ) && is_array( $attributes['links'] ) ? $attributes['links'] : array() as $link ) {
		$label = is_array( $link ) ? trim( wpcredits_hub_attribute( $link, 'label' ) ) : '';
		$url   = is_array( $link ) ? trim( wpcredits_hub_attribute( $link, 'url' ) ) : '';

		if ( '' !== $label && '' !== $url ) {
			$links[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}
	}

	return array(
		'slug'      => wpcredits_hub_anchor( wpcredits_hub_attribute( $attributes, 'slug' ) ),
		'name'      => wpcredits_hub_attribute( $attributes, 'name' ),
		'status'    => 'development' === wpcredits_hub_attribute( $attributes, 'status' ) ? 'development' : 'live',
		'audiences' => array_values( array_intersect( array( 'student', 'faculty', 'career' ), wpcredits_hub_strings( $attributes, 'audiences' ) ) ),
		'what'      => wpcredits_hub_attribute( $attributes, 'what' ),
		'time'      => wpcredits_hub_attribute( $attributes, 'time' ),
		'gets'      => wpcredits_hub_strings( $attributes, 'gets' ),
		'links'     => $links,
	);
}

/**
 * Whether the hub draws a card: one with a name, live, or in development while the hub shows what is
 * planned. A card with no name is a draft, drawn in the editor only.
 *
 * @param array $card A card, as `wpcredits_hub_card()` gives it.
 * @return bool
 */
function wpcredits_hub_card_shown( array $card ) {
	return ! wpcredits_hub_blank( $card['name'] ) && ( 'live' === $card['status'] || wpcredits_hub_shows_planned() );
}

/**
 * A Program card block's attributes for one program of `wpcredits_hub_programs()`: its texts as rich
 * text keeps them, the benefits it gives today (a planned one is not written into the card), and its
 * links with their targets as the addresses. Its courses are the band's (`wpcredits_hub_course_band_markup()`).
 *
 * @param array $program A program.
 * @return array
 */
function wpcredits_hub_program_card_attributes( array $program ) {
	$gets  = array();
	$links = array();

	foreach ( $program['gets'] as $item ) {
		if ( empty( $item['planned'] ) ) {
			$gets[] = $item['text'];
		}
	}

	foreach ( $program['links'] as $link ) {
		$links[] = array(
			'label' => $link['label'],
			'url'   => $link['target'],
		);
	}

	return array(
		'slug'      => $program['slug'],
		'name'      => wpcredits_hub_rich( $program['name'] ),
		'status'    => $program['status'],
		'audiences' => $program['audiences'],
		'what'      => wpcredits_hub_rich( $program['what'] ),
		'time'      => wpcredits_hub_rich( $program['time'] ),
		'gets'      => $gets,
		'links'     => $links,
	);
}

/**
 * The Program card blocks for some programs, one to a paragraph, as the Site Editor saves them.
 *
 * @param array[] $programs Programs, as `wpcredits_hub_programs()` gives them.
 * @return string Block markup.
 */
function wpcredits_hub_program_cards_markup( array $programs ) {
	$cards = array();

	foreach ( $programs as $program ) {
		$cards[] = wpcredits_hub_block_markup( 'wpcredits/program-card', wpcredits_hub_program_card_attributes( $program ) );
	}

	return implode( "\n\n", $cards );
}

/**
 * The Program index holding a card for each of some programs, marked `editable`: the mark an index an
 * Administrator edits carries (see `wpcredits_hub_index_is_legacy()`), written first among the
 * attributes as the Site Editor writes them. An index of no programs is the marked index with no card,
 * as an Administrator who removes every card saves it.
 *
 * @param array[] $programs   Programs, as `wpcredits_hub_programs()` gives them.
 * @param array   $attributes The index's own attributes, such as a lock an Administrator set.
 * @return string Block markup.
 */
function wpcredits_hub_program_index_markup( array $programs, array $attributes = array() ) {
	return wpcredits_hub_block_markup( 'wpcredits/program-index', array( 'editable' => true ) + $attributes, $programs ? array( wpcredits_hub_program_cards_markup( $programs ) ) : array() );
}

/**
 * Whether a Program index is as theme 1.31 and earlier saved it: a block that holds no Program card and
 * is not marked `editable`. Such an index draws the theme's own programs, and the band of courses under
 * them, inside the index, where 1.31.12 drew it (`wpcredits_hub_index_cards()`,
 * blocks/program-index/render.php): an editor tab opened before the update and saved after it, or a
 * migration write that failed, leaves the page as it was.
 *
 * The mark is what tells that index from one an Administrator emptied. An Administrator who removes every
 * card saves the block with no inner block, `<!-- wp:wpcredits/program-index /-->`, the very form 1.31
 * saved; the index the Site Editor writes carries `"editable":true` (the block's default variation, the
 * pattern, the 1.32.0 migration), so the page draws nothing for it (theme 1.32.1). Only the boolean true
 * marks; only a Program card is a card.
 *
 * @param array $block The index, as `parse_blocks()` gives it, or a WP_Block's `parsed_block`.
 * @return bool
 */
function wpcredits_hub_index_is_legacy( array $block ): bool {
	if ( isset( $block['attrs']['editable'] ) && true === $block['attrs']['editable'] ) {
		return false;
	}

	foreach ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array() as $inner ) {
		if ( is_array( $inner ) && isset( $inner['blockName'] ) && 'wpcredits/program-card' === $inner['blockName'] ) {
			return false;
		}
	}

	return true;
}

/**
 * The cards a Program index draws, in its order, each with its own render: the Program card blocks it
 * holds, or, for the 1.31 index (`wpcredits_hub_index_is_legacy()`), a card for each of the theme's
 * programs. A marked index with no card draws none. A card the hub does not draw
 * (`wpcredits_hub_card_shown()`) is left out.
 *
 * @param WP_Block|null $block The index.
 * @return array[] Each `card`, as `wpcredits_hub_card()` gives it, and `html`, its render.
 */
function wpcredits_hub_index_cards( $block ) {
	$parsed = array();

	if ( ! $block instanceof WP_Block || wpcredits_hub_index_is_legacy( $block->parsed_block ) ) {
		foreach ( wpcredits_hub_programs() as $program ) {
			$parsed[] = wpcredits_hub_parsed_block( 'wpcredits/program-card', wpcredits_hub_program_card_attributes( $program ) );
		}
	} else {
		foreach ( $block->inner_blocks as $inner ) {
			if ( 'wpcredits/program-card' === $inner->name ) {
				$parsed[] = $inner->parsed_block;
			}
		}
	}

	$cards = array();

	foreach ( $parsed as $one ) {
		$card = wpcredits_hub_card( isset( $one['attrs'] ) && is_array( $one['attrs'] ) ? $one['attrs'] : array() );
		$html = wpcredits_hub_card_shown( $card ) ? trim( render_block( $one ) ) : '';

		if ( '' !== $html ) {
			$cards[] = array(
				'card' => $card,
				'html' => $html,
			);
		}
	}

	return $cards;
}

/**
 * A course card as the band draws it, from a Course card block's attributes: its name, its address
 * trimmed, the line above its name and what it asks.
 *
 * @param array $attributes The block's attributes.
 * @return array{name: string, url: string, eyebrow: string, line: string}
 */
function wpcredits_hub_course_card( array $attributes ) {
	return array(
		'name'    => wpcredits_hub_attribute( $attributes, 'name' ),
		'url'     => trim( wpcredits_hub_attribute( $attributes, 'url' ) ),
		'eyebrow' => wpcredits_hub_attribute( $attributes, 'eyebrow' ),
		'line'    => wpcredits_hub_attribute( $attributes, 'line' ),
	);
}

/**
 * A band of courses as it is drawn, from the Courses band block's attributes: its anchor as an id
 * carries it, its eyebrow, heading and lede.
 *
 * @param array $attributes The block's attributes.
 * @return array{anchor: string, eyebrow: string, heading: string, lede: string}
 */
function wpcredits_hub_band( array $attributes ) {
	return array(
		'anchor'  => wpcredits_hub_anchor( wpcredits_hub_attribute( $attributes, 'anchor' ) ),
		'eyebrow' => wpcredits_hub_attribute( $attributes, 'eyebrow' ),
		'heading' => wpcredits_hub_attribute( $attributes, 'heading' ),
		'lede'    => wpcredits_hub_attribute( $attributes, 'lede' ),
	);
}

/**
 * The renders of the course cards a band of courses holds, in its order, a course with no name left
 * out.
 *
 * @param WP_Block|null $block The band.
 * @return string[] Each card's render, trimmed.
 */
function wpcredits_hub_band_courses( $block ) {
	$courses = array();

	if ( ! $block instanceof WP_Block ) {
		return $courses;
	}

	foreach ( $block->inner_blocks as $inner ) {
		if ( 'wpcredits/course-card' !== $inner->name ) {
			continue;
		}

		$html = trim( render_block( $inner->parsed_block ) );

		if ( '' !== $html ) {
			$courses[] = $html;
		}
	}

	return $courses;
}

/**
 * The band of a program's courses, as the Site Editor saves it: anchored at the program's slug and
 * `-courses`, the address its card links to, with the programs section's head (an eyebrow, a heading,
 * a lede that says access to a course comes through the institution) and a void Course card for each
 * course, its texts as rich text keeps them.
 *
 * @param array $program A program, as `wpcredits_hub_programs()` gives it.
 * @return string Block markup, or '' for a program without courses.
 */
function wpcredits_hub_course_band_markup( array $program ) {
	if ( empty( $program['courses'] ) ) {
		return '';
	}

	$courses = array();

	foreach ( $program['courses'] as $course ) {
		$courses[] = wpcredits_hub_block_markup(
			'wpcredits/course-card',
			array(
				'name'    => wpcredits_hub_rich( $course['name'] ),
				'url'     => $course['url'],
				'eyebrow' => wpcredits_hub_rich( $course['eyebrow'] ),
				'line'    => wpcredits_hub_rich( $course['line'] ),
			)
		);
	}

	return wpcredits_hub_block_markup(
		'wpcredits/course-band',
		array(
			'anchor'  => $program['slug'] . '-courses',
			'eyebrow' => wpcredits_hub_rich( __( 'Choosing a course', 'wpcredits-theme' ) ),
			'heading' => wpcredits_hub_rich( __( 'Which WordPress Credits course?', 'wpcredits-theme' ) ),
			'lede'    => wpcredits_hub_rich( __( 'What each asks of a student, so a school and its students can choose. Access to each course comes through the student\'s institution.', 'wpcredits-theme' ) ),
		),
		$courses
	);
}

/**
 * The bands of courses of some programs, one for each program that has courses, in their order.
 *
 * @param array[] $programs Programs, as `wpcredits_hub_programs()` gives them.
 * @return string Block markup, '' when none has courses.
 */
function wpcredits_hub_course_bands_markup( array $programs ) {
	return implode( "\n\n", array_filter( array_map( 'wpcredits_hub_course_band_markup', $programs ) ) );
}

/**
 * The fields of study of "Find where you fit", in the order the flow lists them: each the field, the
 * WordPress teams it joins and what the student will have to show. Moved here from the hub pattern in
 * theme 1.32.0, as what the pattern and the migration write into the Field of study rows block.
 *
 * @return array[] Each `field`, `teams` (names) and `proof`, translated.
 */
function wpcredits_hub_fit_rows() {
	return array(
		array(
			'field' => __( 'Computer science, engineering', 'wpcredits-theme' ),
			'teams' => array(
				_x( 'Core', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'Plugins', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'Testing', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'Accessibility', 'a WordPress team a student joins', 'wpcredits-theme' ),
			),
			'proof' => __( 'Merged code and reviewed patches', 'wpcredits-theme' ),
		),
		array(
			'field' => __( 'Design, arts, UX', 'wpcredits-theme' ),
			'teams' => array(
				_x( 'Design', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'Block patterns', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'Themes', 'a WordPress team a student joins', 'wpcredits-theme' ),
			),
			'proof' => __( 'Visual work in use on real sites', 'wpcredits-theme' ),
		),
		array(
			'field' => __( 'Communications, journalism, marketing', 'wpcredits-theme' ),
			'teams' => array(
				_x( 'Documentation', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'Learn WordPress', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'Marketing', 'a WordPress team a student joins', 'wpcredits-theme' ),
			),
			'proof' => __( 'Published work with a byline', 'wpcredits-theme' ),
		),
		array(
			'field' => __( 'Translation, languages', 'wpcredits-theme' ),
			'teams' => array(
				_x( 'Polyglots', 'a WordPress team a student joins', 'wpcredits-theme' ),
			),
			'proof' => __( 'Published translations', 'wpcredits-theme' ),
		),
		array(
			'field' => __( 'Business, events, tourism', 'wpcredits-theme' ),
			'teams' => array(
				_x( 'Community', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'WordCamps', 'a WordPress team a student joins', 'wpcredits-theme' ),
			),
			'proof' => __( 'An event you ran, with attendance numbers', 'wpcredits-theme' ),
		),
		array(
			'field' => _x( 'Education', 'a field of study', 'wpcredits-theme' ),
			'teams' => array(
				_x( 'Learn WordPress lesson plans', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'Training', 'a WordPress team a student joins', 'wpcredits-theme' ),
			),
			'proof' => __( 'Course material in public use', 'wpcredits-theme' ),
		),
		array(
			'field' => __( 'Accessibility, social work', 'wpcredits-theme' ),
			'teams' => array(
				_x( 'Accessibility', 'a WordPress team a student joins', 'wpcredits-theme' ),
				_x( 'Testing', 'a WordPress team a student joins', 'wpcredits-theme' ),
			),
			'proof' => __( 'Audit work on a global platform', 'wpcredits-theme' ),
		),
	);
}

/**
 * The words of the flow's frame: the header line's three names, which the eye reads above the rows,
 * and the two labels a screen reader hears in each row before the teams and before the proof.
 *
 * @return array{fieldHeading: string, teamsHeading: string, proofHeading: string, teamsLabel: string, proofLabel: string} Translated.
 */
function wpcredits_hub_fit_labels() {
	return array(
		'fieldHeading' => __( 'Your field of study', 'wpcredits-theme' ),
		'teamsHeading' => __( 'The team you join', 'wpcredits-theme' ),
		'proofHeading' => __( 'What you\'ll have to show', 'wpcredits-theme' ),
		/* translators: Read by screen readers before the teams a field of study joins; the header line above the rows shows "The team you join" to the eye. */
		'teamsLabel'   => __( 'The team you join:', 'wpcredits-theme' ),
		/* translators: Read by screen readers before what a student will have to show for a field of study; the header line above the rows shows "What you'll have to show" to the eye. */
		'proofLabel'   => __( 'What you\'ll have to show:', 'wpcredits-theme' ),
	);
}

/**
 * A row of the flow as it is drawn, from a Field of study row block's attributes: its field, its
 * teams trimmed, and its proof.
 *
 * @param array $attributes The block's attributes.
 * @return array{field: string, teams: string[], proof: string}
 */
function wpcredits_hub_fit_row( array $attributes ) {
	return array(
		'field' => wpcredits_hub_attribute( $attributes, 'field' ),
		'teams' => wpcredits_hub_strings( $attributes, 'teams' ),
		'proof' => wpcredits_hub_attribute( $attributes, 'proof' ),
	);
}

/**
 * The renders of the rows a Field of study rows block holds, in its order, a row with no field left
 * out. Each is rendered as the block it is, so it reads the labels its list hands it as block context.
 *
 * @param WP_Block|null $block The list.
 * @return string[] Each row's render, trimmed.
 */
function wpcredits_hub_fit_list_rows( $block ) {
	$rows = array();

	if ( ! $block instanceof WP_Block ) {
		return $rows;
	}

	foreach ( $block->inner_blocks as $inner ) {
		if ( 'wpcredits/fit-row' !== $inner->name ) {
			continue;
		}

		$html = trim( $inner->render() );

		if ( '' !== $html ) {
			$rows[] = $html;
		}
	}

	return $rows;
}

/**
 * The Field of study rows block holding a row for each of some rows, as the Site Editor saves it: its
 * five labels written out (the header line's three as rich text keeps them), and a void row for each
 * row, its field and proof as rich text keeps them and its teams as they are.
 *
 * @param array[] $rows   Rows, as `wpcredits_hub_fit_rows()` gives them.
 * @param array   $labels Labels to write in place of today's, as `wpcredits_hub_fit_labels()` names them.
 * @return string Block markup.
 */
function wpcredits_hub_fit_list_markup( array $rows, array $labels = array() ) {
	$labels = array_merge( wpcredits_hub_fit_labels(), array_intersect_key( $labels, wpcredits_hub_fit_labels() ) );
	$inner  = array();

	foreach ( array( 'fieldHeading', 'teamsHeading', 'proofHeading' ) as $heading ) {
		$labels[ $heading ] = wpcredits_hub_rich( $labels[ $heading ] );
	}

	foreach ( $rows as $row ) {
		$inner[] = wpcredits_hub_block_markup(
			'wpcredits/fit-row',
			array(
				'field' => wpcredits_hub_rich( $row['field'] ),
				'teams' => array_values( array_map( 'strval', $row['teams'] ) ),
				'proof' => wpcredits_hub_rich( $row['proof'] ),
			)
		);
	}

	return wpcredits_hub_block_markup( 'wpcredits/fit-list', $labels, $inner );
}
