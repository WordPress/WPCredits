<?php
/**
 * The dashboards' card shell: the section, the disclosure, the summary row and the body, the
 * group a page draws its cards in, and the script that opens a card a link lands on.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one shell every card on the Sponsor Dashboard and the Administrator Dashboard is drawn in.
 *
 * A card is a section around a disclosure: the section carries the card rhythm the theme draws
 * (the rule above it, the room), and the disclosure only folds. On the Sponsor Dashboard the
 * section is `<section class="wpcpm-sponsor__card">` and the disclosure's id is the card's anchor,
 * `wpcpm-sponsor-<key>`, where `WPCPM_Sponsors_Dashboard::leave()`, the mails and the
 * Administrator Dashboard land. On the Administrator Dashboard the anchor is `wpcpm-<key>`, on the
 * section, and the section and the disclosure carry that page's classes: its mails, the wp-admin
 * screens and every decision's way back (`WPCPM_Return`) land on those anchors, and its stylesheets
 * key on those classes, as they did while that page drew its cards with a helper of its own. So
 * what differs between the two pages is asked of this one shell, never drawn by a second one: what
 * the anchor starts with, the element that carries it, and a class for the section and for the
 * disclosure. Every card on both pages drew its own copy of the markup until each drew it here, so
 * the row a closed card shows can grow in one place for all of them: a count beside the title, one
 * phrase saying what the card holds, and an action. The phrase's and the action's classes keep the
 * names the Sponsor Dashboard gave them, on both pages, since the theme's rules for them are
 * written against those names.
 *
 * The action sits after the fold, inside the section, never in the summary: a control inside the
 * summary would be a second interactive element inside the toggle, and anything else inside a
 * closed disclosure is hidden. It links to the card's own anchor unless it names an address of its
 * own, and `assets/js/dashboard-open.js` opens the card a link lands on, on either page. It is
 * printed for every card that has one, drawn open or folded, and shown only while its card is
 * folded. One rule in each page's stylesheets hides it once the card's disclosure is open, whatever
 * opened it, a flash or a press in the browser: in the plugin's `sponsor.css` on the Sponsor
 * Dashboard and its `administrator.css` on the Administrator Dashboard, whose actions lead to the
 * card's home in wp-admin, and, on both pages, in the theme's. An open card's body is on screen,
 * holding what the card holds, and an action after the body would come last in the reading and
 * focus order while drawn beside the title; hidden, it is out of both. Words saying what the link
 * does, naming its card or where it goes, may follow its label inside the link, visually hidden, so
 * a link read out of its row, in a screen reader's list of links, stands alone.
 *
 * A card calls `open()`, prints its body, then calls `close()`, which prints the action of the
 * card it closes. A page draws its cards in groups through `group()`, one shape for both pages,
 * and asks `enqueue_opener()` for the script that opens a card a link lands on.
 */
final class WPCPM_Dashboard_Card {

	/** The handle the dashboards' opener, `assets/js/dashboard-open.js`, is registered under. */
	const OPENER = 'wpcpm-dashboard-open';

	/**
	 * What each card opened and not yet closed asked for, the innermost last, so `close()` prints
	 * the action of the card it closes.
	 *
	 * @var array[]
	 */
	private static $opened = array();

	/**
	 * The opener, for a page that draws its cards in this shell: a browser that follows a link to a
	 * closed card leaves it closed, and the opener opens the card a tile, a row's action, a link in a
	 * mail or a decision's way back lands on, on either page. Registered once, in the footer and
	 * depending on nothing, unless something registered the handle first, whose registration is the
	 * one enqueued.
	 */
	public static function enqueue_opener() {
		if ( ! wp_script_is( self::OPENER, 'registered' ) ) {
			wp_register_script( self::OPENER, WPCPM_PLUGIN_URL . 'assets/js/dashboard-open.js', array(), WPCPM_VERSION, true );
		}

		wp_enqueue_script( self::OPENER );
	}

	/**
	 * One group of cards: a heading and one sentence saying what the group's cards hold, over the
	 * cards, in a wrapper of its own, the one shape both dashboards draw their groups in. The cards
	 * are drawn first, into a buffer, and a group whose cards print nothing, or nothing but white
	 * space, is not printed at all, rather than a heading and a sentence over nothing. The heading
	 * wears the eyebrow the Resources and Updates headings wear (the shared `wpcpm-student__heading`
	 * class) and the sentence the note voice (`wpcpm-student__note`); what differs between the two
	 * pages is the classes after those, asked of this one printer as `open()` asks for a card's, and
	 * the wrapper's class names the group after `--`.
	 *
	 * @param array $args {
	 *     The group.
	 *
	 *     @type string   $id          The group's id, after `--` in the wrapper's class.
	 *     @type string   $title       The heading, as text.
	 *     @type string   $lead        The one sentence, as text.
	 *     @type callable $cards       What prints the group's cards.
	 *     @type string   $group_class The wrapper's class: `wpcpm-sponsor__group`, the default, the
	 *                                 Sponsor Dashboard's, or the Administrator Dashboard's
	 *                                 `wpcpm-administrator__group`.
	 *     @type string   $title_class The heading's class after the shared one:
	 *                                 `wpcpm-sponsor__group-title`, the default, or the
	 *                                 Administrator Dashboard's `wpcpm-administrator__group-title`.
	 *     @type string   $lead_class  The sentence's class after the shared one:
	 *                                 `wpcpm-sponsor__group-lead`, the default, or the
	 *                                 Administrator Dashboard's `wpcpm-administrator__group-lead`.
	 * }
	 */
	public static function group( array $args ) {
		$args = array_merge(
			array(
				'id'          => '',
				'title'       => '',
				'lead'        => '',
				'cards'       => null,
				'group_class' => 'wpcpm-sponsor__group',
				'title_class' => 'wpcpm-sponsor__group-title',
				'lead_class'  => 'wpcpm-sponsor__group-lead',
			),
			$args
		);

		ob_start();

		if ( is_callable( $args['cards'] ) ) {
			call_user_func( $args['cards'] );
		}

		$cards = (string) ob_get_clean();

		if ( '' === trim( $cards ) ) {
			return;
		}

		printf(
			'<div class="%1$s %1$s--%2$s"><h2 class="wpcpm-student__heading %3$s">%4$s</h2>',
			esc_attr( (string) $args['group_class'] ),
			esc_attr( (string) $args['id'] ),
			esc_attr( (string) $args['title_class'] ),
			esc_html( (string) $args['title'] )
		);
		printf( '<p class="wpcpm-student__note %1$s">%2$s</p>', esc_attr( (string) $args['lead_class'] ), esc_html( (string) $args['lead'] ) );
		echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The cards' own markup, each escaping what it prints, held until the group knew it had any.
		echo '</div>';
	}

	/**
	 * The section, the disclosure, the summary row and the body's opening tag.
	 *
	 * @param array $args {
	 *     What the card asks of the shell.
	 *
	 *     @type string     $key           The card's key: its anchor is the prefix and the key.
	 *                                     Defaults to '', which every card overrides with a class
	 *                                     constant or a literal (bin/test-dashboard-card.php reads
	 *                                     each call for it).
	 *     @type string     $title         The title, as text. Defaults to '', which every card
	 *                                     overrides with a literal.
	 *     @type int|null   $count         How many things the card holds, beside the title; null for
	 *                                     none. A zero is a count and is printed.
	 *     @type string     $shown         The words printed for the count when they are not the
	 *                                     number as it stands, such as a count past a ceiling as
	 *                                     the ceiling with a plus sign; '' for the number. Printed
	 *                                     only where there is a count.
	 *     @type bool       $open          Whether the card is drawn open.
	 *     @type string     $state         One phrase for the summary, after the title; '' for none.
	 *     @type array|null $action        `label`, optionally `href`, which defaults to the card's
	 *                                     own anchor, and optionally `context`, words naming what the
	 *                                     link does that follow the label for a screen reader alone;
	 *                                     null for none. An action with no label is none. It is
	 *                                     printed whether the card is drawn open or folded; the
	 *                                     stylesheets show it only while the card is folded.
	 *     @type string     $body_class    A class the card adds to the body after the shared one,
	 *                                     or ''.
	 *     @type string     $prefix        What the anchor starts with: `wpcpm-sponsor-`, the default,
	 *                                     the Sponsor Dashboard's, or the Administrator Dashboard's
	 *                                     `wpcpm-`.
	 *     @type string     $section_class The section's class: `wpcpm-sponsor__card`, the default, or
	 *                                     the Administrator Dashboard's `wpcpm-administrator__card`.
	 *     @type string     $details_class A class the disclosure carries before the shared two:
	 *                                     '', the default, for none, or the Administrator
	 *                                     Dashboard's `wpcpm-administrator__disclosure`. It comes
	 *                                     first so the attribute still ends in
	 *                                     `wpcpm-group__disclosure`, which the suites and the
	 *                                     theme's rules key on.
	 *     @type string     $id_on         The element the anchor is on: 'details', the default, the
	 *                                     disclosure, as the Sponsor Dashboard has it, or 'section',
	 *                                     as the Administrator Dashboard has it. Anything else is
	 *                                     'details'.
	 *     @type bool       $title_id      Whether the title's words carry an id of their own, the
	 *                                     anchor and `-title`, in a span inside the heading: false,
	 *                                     the default, or true for a card whose table's scroll box
	 *                                     is a region named by them (the Administrator Dashboard's
	 *                                     cards, 1.122.35, and the Sponsor Dashboard's Usage card).
	 *                                     The words and not the whole heading, which holds the count
	 *                                     too. The heading looks the same either way.
	 * }
	 */
	public static function open( array $args ) {
		$args = array_merge(
			array(
				'key'           => '',
				'title'         => '',
				'count'         => null,
				'shown'         => '',
				'open'          => false,
				'state'         => '',
				'action'        => null,
				'body_class'    => '',
				'prefix'        => 'wpcpm-sponsor-',
				'section_class' => 'wpcpm-sponsor__card',
				'details_class' => '',
				'id_on'         => 'details',
				'title_id'      => false,
			),
			$args
		);

		$anchor = (string) $args['prefix'] . (string) $args['key'];

		self::$opened[] = array(
			'anchor' => $anchor,
			'action' => is_array( $args['action'] ) ? $args['action'] : null,
		);

		// The anchor stays on the element each page has always put it on, so every link, mail and
		// redirect that lands on a card still lands where it did.
		if ( 'section' === $args['id_on'] ) {
			printf( '<section class="%1$s" id="%2$s"><details', esc_attr( (string) $args['section_class'] ), esc_attr( $anchor ) );
		} else {
			printf( '<section class="%1$s"><details id="%2$s"', esc_attr( (string) $args['section_class'] ), esc_attr( $anchor ) );
		}

		printf(
			' class="%1$s"%2$s>',
			esc_attr( trim( (string) $args['details_class'] . ' wpcpm-group wpcpm-group__disclosure' ) ),
			$args['open'] ? ' open' : ''
		);
		if ( $args['title_id'] ) {
			printf( '<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title"><span id="%1$s">%2$s</span>', esc_attr( self::title_id( $anchor ) ), esc_html( (string) $args['title'] ) );
		} else {
			echo '<summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">' . esc_html( (string) $args['title'] );
		}

		if ( null !== $args['count'] ) {
			printf( ' <span class="wpcpm-group__count">%s</span>', esc_html( '' !== (string) $args['shown'] ? (string) $args['shown'] : number_format_i18n( (int) $args['count'] ) ) );
		}

		echo '</h3>';

		// A phrase, not a paragraph: the summary holds phrasing content only, as an offer's line of
		// counts sits in its own summary on the Offers card.
		if ( '' !== (string) $args['state'] ) {
			printf( '<span class="wpcpm-sponsor__state">%s</span>', esc_html( (string) $args['state'] ) );
		}

		echo '<span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>';
		printf( '<div class="%s">', esc_attr( trim( 'wpcpm-group__body ' . (string) $args['body_class'] ) ) );
	}

	/**
	 * The id a card's title words carry when the card asks for one (`title_id`): its anchor and
	 * `-title`, as `wpcpm-reports-title` on the Administrator Dashboard and
	 * `wpcpm-sponsor-usage-title` on the Sponsor Dashboard. A scroll box in the card names itself by
	 * it (`aria-labelledby`).
	 *
	 * @param string $anchor The card's anchor, its prefix and its key.
	 * @return string
	 */
	public static function title_id( $anchor ) {
		return (string) $anchor . '-title';
	}

	/**
	 * The body's closing tag, the disclosure's, the action of the card this closes, and the
	 * section's. With nothing open it still ends the markup, with no action. The action is printed
	 * whatever the card's state at render, since a card opens and folds in the browser too; which
	 * of the two it is, is the stylesheets' to read, from the disclosure itself.
	 */
	public static function close() {
		$card   = array_pop( self::$opened );
		$action = is_array( $card ) && is_array( $card['action'] ) ? $card['action'] : array();
		$label  = isset( $action['label'] ) ? (string) $action['label'] : '';

		echo '</div></details>';

		if ( '' !== $label ) {
			$href    = isset( $action['href'] ) && '' !== (string) $action['href'] ? (string) $action['href'] : '#' . $card['anchor'];
			$context = isset( $action['context'] ) ? trim( (string) $action['context'] ) : '';

			// The words that say what the link does are the link's own, inside it, so a screen reader
			// reads the label and them as one name; the space before them is inside the hidden span, as
			// the Reports card's buttons carry the report's title.
			printf(
				'<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="%1$s">%2$s%3$s</a></p>',
				esc_url( $href ),
				esc_html( $label ),
				'' === $context ? '' : '<span class="screen-reader-text"> ' . esc_html( $context ) . '</span>'
			);
		}

		echo '</section>';
	}
}
