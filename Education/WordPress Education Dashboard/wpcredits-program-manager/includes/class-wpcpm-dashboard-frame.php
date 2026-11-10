<?php
/**
 * The dashboards' frame: the top bar, the menu down the side a reader starts from, and the main area.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One frame for the five dashboards (the version 3 concepts of 10 November 2026, the owner's "full width
 * with a left sidebar, or right depending on the local language").
 *
 * The frame is three parts. The top bar holds the site's name, the homepage and the viewer's photo with
 * Log out behind it; it is printed only when the theme says it draws the dashboard pages as a bare canvas
 * (`current_theme_supports( 'wpcpm-dashboard-frame' )`), since the theme's own header would otherwise sit
 * above it saying the same things. The menu opens with the Ask box, the Handbook Assistant's question
 * field in place of a Need help? link, then holds what the viewer is looking at, a link for each view or
 * screen with a thin-line icon and, beside what waits, a count in words, then the help for the role, and
 * Collapse menu at its foot. The main area is as wide as the window.
 *
 * Every rule the stylesheet writes is in start and end, never left and right, so the menu sits on the left
 * in a left-to-right language and on the right in Arabic, Hebrew, Persian or Urdu with no second
 * stylesheet. Above 1,100px the menu is 256px wide and can fold to its icons; from 783px to 1,100px it
 * shows its icons alone; at 782px and below, wp-admin's own breakpoint, it is a drawer behind a Menu
 * button. The script (`assets/js/dashboard-frame.js`) opens and closes the drawer, holds focus inside it
 * while it is open, and remembers a folded menu in the browser; without it the menu is a plain list of
 * links above the page on a phone.
 *
 * A page calls `open()` with its menu, prints its main area, then calls `close()`.
 */
final class WPCPM_Dashboard_Frame {

	/** The stylesheet's handle. */
	const STYLE = 'wpcpm-dashboard-frame';

	/** The script's handle. */
	const SCRIPT = 'wpcpm-dashboard-frame';

	/** What the theme declares when it draws the dashboard pages as a bare canvas for the frame. */
	const THEME_SUPPORT = 'wpcpm-dashboard-frame';

	/** The menu's id, which the Menu button controls. */
	const MENU_ID = 'wpcpm-frame-menu';

	/**
	 * Register (but do not enqueue) the stylesheet and the script.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . 'assets/css/dashboard-frame.css', array(), WPCPM_VERSION );
		}

		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script( self::SCRIPT, WPCPM_PLUGIN_URL . 'assets/js/dashboard-frame.js', array(), WPCPM_VERSION, true );
		}
	}

	/**
	 * Whether the theme draws this dashboard's page as a bare canvas, so the frame prints its own top bar
	 * and the page prints its one H1: the theme names the dashboards it does this for,
	 * `add_theme_support( 'wpcpm-dashboard-frame', array( 'administrator' ) )`.
	 *
	 * @param string $page The dashboard's key, as `open()` takes it: `administrator` and so on.
	 * @return bool
	 */
	public static function owns_page( $page ) {
		$support = get_theme_support( self::THEME_SUPPORT );

		if ( ! $support ) {
			return false;
		}

		$pages = is_array( $support ) && isset( $support[0] ) ? (array) $support[0] : array();

		return in_array( (string) $page, $pages, true );
	}

	/**
	 * The frame's opening: the top bar, the menu and the main area's opening tag.
	 *
	 * @param array $args {
	 *     The menu.
	 *
	 *     @type string $page    The page's key, after `--` in the frame's class: `administrator` and so on.
	 *     @type string $label   The menu's name for a screen reader, the dashboard's full name.
	 *     @type array  $context What the viewer is looking at, first in the menu: `label`, the small heading,
	 *                           `title`, the words, and optionally `sub`, a line under them.
	 *     @type array  $groups  Each `heading` ('' for none) and `items`.
	 *     @type array  $help    The help items, under the heading Help.
	 * }
	 */
	public static function open( array $args ) {
		$args = array_merge(
			array(
				'page'    => '',
				'label'   => '',
				'context' => array(),
				'groups'  => array(),
				'help'    => array(),
			),
			$args
		);

		self::register_assets();
		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::SCRIPT );

		printf(
			'<div class="wpcpm-frame wpcpm-frame--%1$s" data-wpcpm-frame>',
			esc_attr( sanitize_key( (string) $args['page'] ) )
		);

		// A folded menu is put back before the first paint, so it never opens and then folds.
		echo '<script>try{if(window.localStorage.getItem("wpcpm-frame-folded")==="1"){document.currentScript.parentNode.setAttribute("data-folded","")}}catch(e){}</script>';

		self::bar( (string) $args['label'], (string) $args['page'] );

		echo '<div class="wpcpm-frame__body">';

		self::menu( $args );

		echo '<div class="wpcpm-frame__main">';
	}

	/**
	 * The main area's closing tag, the body's and the frame's.
	 */
	public static function close() {
		echo '</div></div></div>';
	}

	/**
	 * One menu item.
	 *
	 * @param string $label    The words.
	 * @param string $url      Where it goes.
	 * @param string $icon     A key of `WPCPM_Icons`.
	 * @param array  $extra    Optional `current` (bool), `badge` (words beside the label), `tone` (`red`,
	 *                         `amber` or `blue`, '' for a plain count) and `external` (bool, an item that
	 *                         leaves the dashboard for wp-admin).
	 * @return array
	 */
	public static function item( $label, $url, $icon, array $extra = array() ) {
		return array_merge(
			array(
				'label'    => (string) $label,
				'url'      => (string) $url,
				'icon'     => (string) $icon,
				'current'  => false,
				'badge'    => '',
				'tone'     => '',
				'external' => false,
			),
			$extra
		);
	}

	/**
	 * The top bar, when the theme leaves the page to the frame.
	 *
	 * @param string $label The dashboard's full name.
	 * @param string $page  The dashboard's key.
	 */
	private static function bar( $label, $page ) {
		if ( ! self::owns_page( $page ) ) {
			// The theme's header carries the site's name and its own menu; the Menu button still opens the
			// dashboard's menu on a phone.
			echo '<div class="wpcpm-frame__bar wpcpm-frame__bar--slim">';
			self::menu_button( $label );
			echo '</div>';

			return;
		}

		echo '<div class="wpcpm-frame__bar">';
		self::menu_button( $label );
		printf(
			'<a class="wpcpm-frame__brand" href="%1$s"><b>%2$s</b> %3$s</a>',
			esc_url( home_url( '/' ) ),
			esc_html__( 'Education', 'wpcredits-program-manager' ),
			esc_html__( 'Directory', 'wpcredits-program-manager' )
		);
		echo '<div class="wpcpm-frame__nav">';
		printf( '<a class="wpcpm-frame__home" href="%1$s">%2$s</a>', esc_url( home_url( '/' ) ), esc_html__( 'Homepage', 'wpcredits-program-manager' ) );
		self::account();
		echo '</div></div>';
	}

	/**
	 * The Menu button, shown on a phone, which opens the menu as a drawer.
	 *
	 * @param string $label The dashboard's full name, for a screen reader.
	 */
	private static function menu_button( $label ) {
		printf(
			'<button class="wpcpm-frame__menu-button" type="button" aria-controls="%1$s" aria-expanded="false">%2$s<span>%3$s</span><span class="screen-reader-text"> %4$s</span></button>',
			esc_attr( self::MENU_ID ),
			WPCPM_Icons::svg( 'menu', 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
			esc_html__( 'Menu', 'wpcredits-program-manager' ),
			esc_html( $label )
		);
	}

	/**
	 * The Ask box: the Handbook Assistant's question, asked from any dashboard, answered on its page.
	 * Nothing when the assistant is off or the viewer may not ask it.
	 */
	private static function ask() {
		foreach ( array( 'is_available', 'user_can_ask', 'page_url' ) as $method ) {
			if ( ! class_exists( 'WPCPM_Handbook_Assistant' ) || ! method_exists( 'WPCPM_Handbook_Assistant', $method ) ) {
				return;
			}
		}

		if ( ! WPCPM_Handbook_Assistant::is_available() || ! WPCPM_Handbook_Assistant::user_can_ask() ) {
			return;
		}

		$url = (string) WPCPM_Handbook_Assistant::page_url();

		if ( '' === $url ) {
			return;
		}

		// A form sent by GET replaces its address's query, so the page's own arguments (`?page_id=42` on a
		// site with plain addresses) travel as hidden fields.
		$carried = '';
		$query   = (string) wp_parse_url( $url, PHP_URL_QUERY );

		if ( '' !== $query ) {
			$args = array();

			wp_parse_str( $query, $args );

			foreach ( $args as $name => $value ) {
				if ( is_scalar( $value ) && WPCPM_Handbook_Assistant::ARG !== $name ) {
					$carried .= sprintf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( (string) $name ), esc_attr( (string) $value ) );
				}
			}
		}

		printf(
			'<form class="wpcpm-frame__ask" role="search" method="get" action="%1$s"><label class="screen-reader-text" for="wpcpm-frame-ask">%2$s</label>%3$s<input id="wpcpm-frame-ask" type="search" name="%4$s" maxlength="%5$d" placeholder="%2$s" autocomplete="off">%7$s<button class="screen-reader-text" type="submit">%6$s</button></form>',
			esc_url( $url ),
			esc_attr__( 'Ask about the program', 'wpcredits-program-manager' ),
			WPCPM_Icons::svg( 'search', 18 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
			esc_attr( WPCPM_Handbook_Assistant::ARG ),
			(int) WPCPM_Handbook_Assistant::MAX_LENGTH,
			esc_html__( 'Ask', 'wpcredits-program-manager' ),
			$carried // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hidden fields, each value escaped above.
		);

		printf(
			'<a class="wpcpm-frame__item wpcpm-frame__ask-link" href="%1$s">%2$s<span class="wpcpm-frame__label">%3$s</span></a>',
			esc_url( $url ),
			WPCPM_Icons::svg( 'search', 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
			esc_html__( 'Ask about the program', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The viewer's photo and first name, with Log out behind them.
	 */
	private static function account() {
		$user = wp_get_current_user();

		if ( ! $user || ! $user->exists() ) {
			return;
		}

		$first = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );

		if ( '' === $first ) {
			$parts = preg_split( '/\s+/', trim( (string) $user->display_name ) );
			$first = is_array( $parts ) && isset( $parts[0] ) ? (string) $parts[0] : '';
		}

		printf(
			'<details class="wpcpm-frame__account"><summary>%1$s<span class="wpcpm-frame__account-name">%2$s</span>%3$s<span class="screen-reader-text"> %4$s</span></summary><div class="wpcpm-frame__account-menu"><a href="%5$s">%6$s</a></div></details>',
			get_avatar( $user->ID, 28, '', '', array( 'class' => 'wpcpm-frame__avatar' ) ),
			esc_html( $first ),
			WPCPM_Icons::svg( 'chevron-down', 16 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
			esc_html__( 'Your account', 'wpcredits-program-manager' ),
			esc_url( wp_logout_url( home_url( '/' ) ) ),
			esc_html__( 'Log out', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The menu.
	 *
	 * @param array $args What `open()` was given.
	 */
	private static function menu( array $args ) {
		printf( '<nav class="wpcpm-frame__menu" id="%1$s" aria-label="%2$s"><div class="wpcpm-frame__menu-in">', esc_attr( self::MENU_ID ), esc_attr( (string) $args['label'] ) );

		printf(
			'<button class="wpcpm-frame__close" type="button">%1$s<span>%2$s</span></button>',
			WPCPM_Icons::svg( 'close', 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
			esc_html__( 'Close menu', 'wpcredits-program-manager' )
		);

		$context = is_array( $args['context'] ) ? $args['context'] : array();

		if ( ! empty( $context['title'] ) ) {
			printf(
				'<div class="wpcpm-frame__context"><span class="wpcpm-frame__heading">%1$s</span><span class="wpcpm-frame__context-title">%2$s</span>%3$s</div>',
				esc_html( isset( $context['label'] ) ? (string) $context['label'] : '' ),
				esc_html( (string) $context['title'] ),
				! empty( $context['sub'] ) ? '<span class="wpcpm-frame__context-sub">' . esc_html( (string) $context['sub'] ) . '</span>' : ''
			);
		}

		$n = 0;

		foreach ( (array) $args['groups'] as $group ) {
			self::menu_list( isset( $group['heading'] ) ? (string) $group['heading'] : '', isset( $group['items'] ) ? (array) $group['items'] : array(), 'wpcpm-frame-menu-' . ( ++$n ) );
		}

		// The Ask box opens the Help group, down the menu (the owner, 10 November 2026: "Move search to the
		// sidebar", then "the search should go down the sidebar"): in the drawer on a phone, one icon in the
		// strip on a tablet. It is what replaces Need help?, so it stands with the help.
		ob_start();
		self::ask();
		$ask = (string) ob_get_clean();

		if ( ! empty( $args['help'] ) || '' !== $ask ) {
			self::menu_list( __( 'Help', 'wpcredits-program-manager' ), (array) $args['help'], 'wpcpm-frame-menu-help', 'wpcpm-frame__list--help', $ask );
		}

		printf(
			'<div class="wpcpm-frame__foot"><button class="wpcpm-frame__item wpcpm-frame__fold" type="button" aria-pressed="false" data-expand="%3$s">%1$s<span class="wpcpm-frame__label">%2$s</span></button></div>',
			WPCPM_Icons::svg( 'collapse', 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
			esc_html__( 'Collapse menu', 'wpcredits-program-manager' ),
			esc_attr__( 'Expand menu', 'wpcredits-program-manager' )
		);

		echo '</div></nav>';
	}

	/**
	 * One list of the menu, under its heading.
	 *
	 * @param string $heading The small heading, or '' for none.
	 * @param array  $items   The items, as `item()` builds them.
	 * @param string $id      The heading's id, which names the list.
	 * @param string $list_class A class the list adds, or ''.
	 * @param string $before     Markup printed under the heading, before the list (the Ask box), or ''.
	 */
	private static function menu_list( $heading, array $items, $id, $list_class = '', $before = '' ) {
		if ( empty( $items ) && '' === $before ) {
			return;
		}

		printf( '<div class="%s">', esc_attr( 'wpcpm-frame__list--help' === $list_class ? 'wpcpm-frame__group wpcpm-frame__group--help' : 'wpcpm-frame__group' ) );

		if ( '' !== $heading ) {
			printf( '<span class="wpcpm-frame__heading" id="%1$s">%2$s</span>', esc_attr( $id ), esc_html( $heading ) );
		}

		echo $before; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The Ask box's own markup, escaped where it was built.

		if ( '' !== $heading ) {
			printf( '<ul class="%1$s" aria-labelledby="%2$s">', esc_attr( trim( 'wpcpm-frame__list ' . $list_class ) ), esc_attr( $id ) );
		} else {
			printf( '<ul class="%s">', esc_attr( trim( 'wpcpm-frame__list ' . $list_class ) ) );
		}

		foreach ( $items as $item ) {
			$item = array_merge( self::item( '', '', '' ), (array) $item );

			if ( '' === $item['label'] || '' === $item['url'] ) {
				continue;
			}

			$badge = '';

			if ( '' !== (string) $item['badge'] ) {
				$tone  = in_array( (string) $item['tone'], array( 'red', 'amber', 'blue' ), true ) ? ' wpcpm-frame__badge--' . $item['tone'] : '';
				$badge = sprintf( '<span class="wpcpm-frame__badge%1$s">%2$s</span>', esc_attr( $tone ), esc_html( (string) $item['badge'] ) );
			}

			printf(
				'<li><a class="wpcpm-frame__item%1$s" href="%2$s"%3$s>%4$s<span class="wpcpm-frame__label">%5$s%6$s</span>%7$s%8$s</a></li>',
				$item['current'] ? ' is-current' : '',
				esc_url( (string) $item['url'] ),
				$item['current'] ? ' aria-current="page"' : '',
				WPCPM_Icons::svg( (string) $item['icon'], 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon from WPCPM_Icons.
				esc_html( (string) $item['label'] ),
				// The count is part of the link's name, so a screen reader hears "Overview, 2 wait".
				'' !== $badge ? '<span class="screen-reader-text">, ' . esc_html( (string) $item['badge'] ) . '</span>' : '',
				$badge ? str_replace( 'class="wpcpm-frame__badge', 'aria-hidden="true" class="wpcpm-frame__badge', $badge ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The badge's markup, escaped where it was built above.
				$item['external'] ? WPCPM_Icons::svg( 'external', 16 ) . '<span class="screen-reader-text"> ' . esc_html__( '(in wp-admin)', 'wpcredits-program-manager' ) . '</span>' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed icon and escaped words.
			);
		}

		echo '</ul></div>';
	}
}
