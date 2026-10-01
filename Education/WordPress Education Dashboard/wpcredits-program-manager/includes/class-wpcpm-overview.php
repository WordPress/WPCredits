<?php
/**
 * The Overview, the program's home in wp-admin.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page the WPCredits Program menu opens: what waits for a decision, when each sync last ran and
 * runs next, whether each tool can run, and the way to Settings, a card each.
 *
 * It reads and it links, and it posts nothing: every decision has one home, the Administrator
 * Dashboard, so each count here opens where its queue is listed (`targets()`), and one link under
 * the counts opens the dashboard. The counts are the dashboard's own,
 * `WPCPM_Administrators_Cards::counts()` over its `collect()`, so a number here and the attention
 * strip's number there cannot disagree; the syncs are what the same read's `health()` says of them,
 * and each tool says what it says on the Tools screen.
 *
 * `WPCPM_Admin` registers the page and checks the capability before it calls `render()`.
 */
final class WPCPM_Overview {

	/**
	 * Draw the screen: its heading and what it holds, the warning while Airtable is not connected,
	 * then the four cards.
	 *
	 * The cards class reads every dataset once, and the syncs come with the rest (`collect()` holds
	 * `health()`), so the cards and the syncs are read once.
	 */
	public static function render() {
		$data = WPCPM_Administrators_Cards::collect();

		// Titled as the menu titles the page: the plugin is WPCredits Program on the menu, and each
		// screen under it carries its own name.
		echo '<div class="wrap wpcpm-wrap">';
		echo '<h1>' . esc_html__( 'Overview', 'wpcredits-program-manager' ) . '</h1>';
		echo '<p class="wpcpm-lede">' . esc_html__( 'What waits for a decision, when each sync last ran and runs next, whether each tool can run, and the way to Settings.', 'wpcredits-program-manager' ) . '</p>';

		if ( ! WPCPM_Settings::is_connected() ) {
			printf(
				'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html( self::connection( false ) ),
				esc_url( WPCPM_Settings_Screen::settings_url() ),
				esc_html__( 'Add a Personal Access Token', 'wpcredits-program-manager' )
			);
		}

		self::render_waiting( WPCPM_Administrators_Cards::counts( $data ) );
		self::render_syncs( isset( $data['health']['syncs'] ) ? (array) $data['health']['syncs'] : array() );
		self::render_tools( WPCPM_Tools::all() );
		self::render_settings();

		echo '</div>';
	}

	/**
	 * Where each count on the waiting list opens: the attention strip's tile key => the address of
	 * the place that lists the tile's queue, a wp-admin screen for every tile but one.
	 *
	 * The sponsor posts open their card on the Administrator Dashboard, the one place that lists them
	 * with the decision each waits for: none of the plugin's wp-admin screens lists them, since the
	 * Sponsors screen counts them for its menu bubble and draws none. The day the Sponsors screen
	 * gains a tab that lists them, this key points there instead. While the dashboard's page is
	 * missing the key is left out, so the count is listed unlinked rather than sent to a page that
	 * is not there.
	 *
	 * The institutions' counts open the Institutions screen at the tab for each, named even where
	 * it is the queue the screen opens on, so each address says where it goes: the applications,
	 * the agreements and the mentor requests at Waiting for review, the semester reports to draft
	 * and to review at Semester reports, and the locked accounts at Accounts.
	 *
	 * One map, so pointing a count at another screen, or at a tab of one, is an edit here and
	 * nowhere else. Every tile `counts()` returns has a place in it while the dashboard's page is
	 * published, which bin/test-overview.php holds the two lists to; a tile it does not place is
	 * still listed, unlinked.
	 *
	 * @return array<string, string> Tile key => full address, not escaped.
	 */
	public static function targets() {
		$queue     = admin_url( 'admin.php?page=wpcpm-institutions&tab=queue' );
		$reports   = admin_url( 'admin.php?page=wpcpm-institutions&tab=reports' );
		$accounts  = admin_url( 'admin.php?page=wpcpm-institutions&tab=accounts' );
		$sponsors  = admin_url( 'admin.php?page=wpcpm-sponsors' );
		$dashboard = WPCPM_Administrators_Dashboard::page_url();

		$targets = array(
			'applications'         => $queue,
			'agreements'           => $queue,
			'overdue_agreements'   => $queue,
			'drafts'               => $reports,
			'due'                  => $reports,
			'requests'             => $queue,
			'overdue_requests'     => $queue,
			// Institution members, whose write path the roster's refusal ceiling locked for the day.
			'locked'               => $accounts,
			// The card's own anchor, the one the strip's tile for it jumps to.
			'sponsor_posts'        => $dashboard . '#wpcpm-sponsor-posts',
			'sponsor_agreements'   => $sponsors,
			'sponsor_applications' => $sponsors,
			'offers_low'           => $sponsors,
			'duplicates'           => admin_url( 'admin.php?page=wpcpm-tool-duplicate-finder' ),
		);

		// Without the page there is no card to open: the anchor alone would point back at this screen,
		// which has no such card.
		if ( '' === $dashboard ) {
			unset( $targets['sponsor_posts'] );
		}

		return $targets;
	}

	/**
	 * Waiting for a decision: each queue with something in it, in the strip's order, as a link to
	 * where the queue is listed (`targets()`); then the way to the Administrator Dashboard, where the
	 * decisions are made.
	 *
	 * Each link is the queue's label, then its count in parentheses, in the `count` span core's list
	 * tables print their views' counts in: the label is the strip's caption for the queue, not a
	 * counted phrase, so it reads as "Agreements to review (1)" and never as "1 Agreements to review".
	 *
	 * Only the queues that hold something: the strip keeps its shape so a manager learns where to
	 * look on the page the work is done on, and here the list is what there is to do. With nothing
	 * waiting, the card says so in the words the dashboards keep for a program manager with nothing
	 * waiting (`WPCPM_Dashboards::empty_sentence()`).
	 *
	 * @param array $counts What `WPCPM_Administrators_Cards::counts()` returned: `label`, `n` and
	 *                      `card`, keyed in the strip's order.
	 */
	public static function render_waiting( array $counts ) {
		$targets = self::targets();
		$waiting = array_filter(
			$counts,
			static function ( $tile ) {
				return isset( $tile['n'] ) && (int) $tile['n'] > 0;
			}
		);

		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Waiting for a decision', 'wpcredits-program-manager' ) . '</h2>';

		if ( empty( $waiting ) ) {
			echo '<p>' . esc_html( WPCPM_Dashboards::empty_sentence( WPCPM_Administrators_Dashboard::MODULE ) ) . '</p>';
		} else {
			echo '<ul class="wpcpm-list wpcpm-waiting">';

			foreach ( $waiting as $key => $tile ) {
				if ( isset( $targets[ $key ] ) ) {
					printf(
						'<li><a href="%1$s"><span class="wpcpm-waiting__label">%2$s</span> <span class="count">(%3$s)</span></a></li>',
						esc_url( $targets[ $key ] ),
						esc_html( $tile['label'] ),
						esc_html( number_format_i18n( (int) $tile['n'] ) )
					);

					continue;
				}

				printf(
					'<li><span class="wpcpm-waiting__label">%1$s</span> <span class="count">(%2$s)</span></li>',
					esc_html( $tile['label'] ),
					esc_html( number_format_i18n( (int) $tile['n'] ) )
				);
			}

			echo '</ul>';
		}

		$page = WPCPM_Administrators_Dashboard::page_url();

		if ( '' !== $page ) {
			printf(
				'<p><a class="button button-primary" href="%1$s">%2$s</a></p>',
				esc_url( $page ),
				esc_html__( 'Decide on the Administrator Dashboard', 'wpcredits-program-manager' )
			);
		} else {
			// The dashboard class's sentence, which the Administrators screen prints too.
			echo '<p class="wpcpm-warning">' . esc_html( WPCPM_Administrators_Dashboard::page_missing() ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Syncs: a row for each audience's sync, its name opening the screen the sync is run from, then
	 * when it last ran and when it runs next, each in the site's date and time.
	 *
	 * A sync running now says so in place of its next run, since that run is the one under way, and
	 * the screen its name opens follows it.
	 *
	 * @param array $syncs What `WPCPM_Administrators_Cards::health()` returns as `syncs`: each one's
	 *                     `label`, `progress`, `last`, `next` and `screen`.
	 */
	public static function render_syncs( array $syncs ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Syncs', 'wpcredits-program-manager' ) . '</h2>';
		echo '<table class="widefat striped wpcpm-list"><thead><tr>';
		printf( '<th scope="col">%s</th>', esc_html__( 'Sync', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Last run', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Next run', 'wpcredits-program-manager' ) );
		echo '</tr></thead><tbody>';

		foreach ( $syncs as $sync ) {
			$progress = isset( $sync['progress'] ) && is_array( $sync['progress'] ) ? $sync['progress'] : array();

			if ( ! empty( $progress['running'] ) ) {
				$next = __( 'Running now', 'wpcredits-program-manager' );
			} elseif ( empty( $sync['next'] ) ) {
				$next = __( 'Not scheduled', 'wpcredits-program-manager' );
			} else {
				$next = self::when( (int) $sync['next'] );
			}

			printf(
				'<tr><th scope="row"><a href="%1$s">%2$s</a></th><td>%3$s</td><td>%4$s</td></tr>',
				esc_url( $sync['screen'] ),
				esc_html( $sync['label'] ),
				esc_html( empty( $sync['last'] ) ? __( 'Never', 'wpcredits-program-manager' ) : self::when( (int) $sync['last'] ) ),
				esc_html( $next )
			);
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Tools: a row for each tool, its name opening its screen, then its status line as the Tools
	 * screen's card prints it, the warning for a tool that cannot run included
	 * (`WPCPM_Admin::render_tool_status()`), or Ready for a tool with nothing to report.
	 *
	 * @param WPCPM_Tool[] $tools The registered tools, in the menu's order.
	 */
	public static function render_tools( array $tools ) {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Tools', 'wpcredits-program-manager' ) . '</h2>';

		if ( empty( $tools ) ) {
			echo '<p>' . esc_html__( 'No tools are registered.', 'wpcredits-program-manager' ) . '</p>';
			echo '</div>';

			return;
		}

		echo '<table class="widefat striped wpcpm-list"><thead><tr>';
		printf( '<th scope="col">%s</th>', esc_html__( 'Name', 'wpcredits-program-manager' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Status', 'wpcredits-program-manager' ) );
		echo '</tr></thead><tbody>';

		foreach ( $tools as $tool ) {
			$status = $tool->status_line();

			printf(
				'<tr><th scope="row"><a href="%1$s">%2$s</a></th><td>',
				esc_url( $tool->admin_url() ),
				esc_html( $tool->label() )
			);

			// An empty line is a ready tool with nothing to report (`WPCPM_Tool::status_line()`).
			if ( '' === $status ) {
				echo esc_html__( 'Ready', 'wpcredits-program-manager' );
			} else {
				WPCPM_Admin::render_tool_status( $tool, $status );
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Settings: whether Airtable is connected, and the way to the Settings screen.
	 */
	public static function render_settings() {
		echo '<div class="wpcpm-card">';
		echo '<h2>' . esc_html__( 'Settings', 'wpcredits-program-manager' ) . '</h2>';
		echo '<p>' . esc_html( self::connection( WPCPM_Settings::is_connected() ) ) . '</p>';
		printf(
			'<p><a class="button" href="%1$s">%2$s</a></p>',
			esc_url( WPCPM_Settings_Screen::settings_url() ),
			esc_html__( 'Open Settings', 'wpcredits-program-manager' )
		);
		echo '</div>';
	}

	/**
	 * Whether Airtable is connected, as the screen says it: in the Settings card either way, and in
	 * the warning above the cards while it is not.
	 *
	 * @param bool $connected Whether it is.
	 * @return string
	 */
	private static function connection( $connected ) {
		return $connected
			? __( 'Airtable is connected.', 'wpcredits-program-manager' )
			: __( 'Airtable is not connected yet.', 'wpcredits-program-manager' );
	}

	/**
	 * A date and time in the site's own formats, as the Administrator Dashboard's Syncs and health
	 * card prints a run.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	private static function when( $timestamp ) {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp );
	}
}
