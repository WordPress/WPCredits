<?php
/**
 * The tab bar of an audience's screen.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The tab bar an audience's screen draws above the tab it shows, in the Settings screen's markup,
 * which is WordPress's own.
 *
 * Core's newer bars' markup: a navigation region named for a screen reader, holding plain links,
 * each at the screen's address with the tab's slug as `tab`, and the tab shown marked for the eye
 * (`nav-tab-active`) and for a screen reader (`aria-current`). The classes are the ones core styles,
 * so the bar needs no rule of the plugin's own. One printer for every audience's screen, so their
 * bars cannot come to differ; the Settings screen prints the same markup with a printer of its own
 * (`WPCPM_Settings_Screen::render_tab_bar()`).
 */
final class WPCPM_Screen_Tabs {

	/**
	 * Print the bar: a link a tab, in the order given, the tab shown marked.
	 *
	 * The caller knows its words and translates them; each is escaped here as it is printed.
	 *
	 * @param string                $page    The screen's page slug, which its address names as `page`.
	 * @param array<string, string> $tabs    The screen's tabs, slug => label, in the bar's order.
	 * @param string                $current The tab shown, one of the slugs; any other value marks none.
	 */
	public static function render( $page, array $tabs, $current ) {
		printf( '<nav class="nav-tab-wrapper wp-clearfix" aria-label="%s">', esc_attr__( 'Secondary menu', 'wpcredits-program-manager' ) );

		foreach ( $tabs as $slug => $label ) {
			$shown = (string) $slug === (string) $current;

			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url(
					add_query_arg(
						array(
							'page' => rawurlencode( (string) $page ),
							'tab'  => rawurlencode( (string) $slug ),
						),
						admin_url( 'admin.php' )
					)
				),
				$shown ? ' nav-tab-active' : '',
				$shown ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}

		echo '</nav>';
	}
}
