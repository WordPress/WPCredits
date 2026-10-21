<?php
/**
 * The Reports card on the Sponsor Dashboard: a sponsor's final annual reports.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where a sponsor's members read the annual reports the program has finished for them.
 *
 * The card lists the sponsor's final reports, the newest term first, each by its title with Open,
 * the printed document in a new tab, and PDF, the file a program manager attached, when there is
 * one. It sits in the Your sponsorship group, between the tier card's two cards and the Badge card.
 * It folds until a flash names it, and its row says how many yearly reports there are, the final
 * ones, in the guide's word and the tile's, and which term the latest is for; it offers no action
 * there, since Open and PDF belong to each report.
 *
 * **Drawn only while the tiers are live and the sponsor has a final report.** A draft is the
 * program's to write, and while the launch switch is off nothing about the tiers reaches a sponsor,
 * so the card prints nothing at all rather than an empty card: a sponsor with nothing to read learns
 * nothing about reports from it. The links are the files class's own routes, which ask the same two
 * questions of every request and the policy who is asking, so the card never offers a link its
 * route would refuse to a member of this sponsor.
 *
 * **The card decides nothing.** The Sponsor Dashboard has already asked the policy whether the
 * viewer may see this sponsor, and the store answers which reports are the sponsor's, by the
 * report's own sponsor stamp. The card prints each report's title, the sponsor's name and two
 * months, and nothing of the report itself: no figure, no narrative, and never the name a PDF was
 * uploaded under, which is a manager's alone. It has no form and no hook, so it has no outcome to
 * say and nothing to start on boot.
 */
final class WPCPM_Sponsor_Report_Card {

	/** The card's anchor on the Sponsor Dashboard, `wpcpm-sponsor-reports`. */
	const CARD = 'reports';

	/**
	 * The card has no form and no handler, so it leaves no flash of its own. Listed in the
	 * dashboard's merge all the same, so the cards drawn and the cards merged stay one list.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array();
	}

	/**
	 * Reports: the sponsor's final reports, each with Open and, when a PDF is attached, PDF.
	 *
	 * Nothing while the launch switch is off, and nothing for a sponsor with no final report. Open
	 * goes to a new tab, since the printed document is a page of its own with no way back to the
	 * dashboard; the PDF is a download, which leaves the dashboard where it is. Each button carries
	 * the report's title for a screen reader, since every row has the same two words.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return;
		}

		$reports = self::finals( $record );

		if ( empty( $reports ) ) {
			return;
		}

		$open = isset( $context['open'] ) && self::CARD === $context['open'];

		WPCPM_Dashboard_Card::open(
			array(
				'key'   => self::CARD,
				'title' => __( 'Reports', 'wpcredits-program-manager' ),
				'open'  => $open,
				'state' => self::state_line( $reports ),
			)
		);
		echo '<p class="wpcpm-student__note">' . esc_html__( 'Your yearly report of the program and of your sponsorship, one for each term, once your program contact has finished it. Open shows it in a new tab, ready to print; PDF downloads the copy your program contact attached.', 'wpcredits-program-manager' ) . '</p>';
		echo '<ul class="wpcpm-sponsor-reports__list">';

		foreach ( $reports as $post ) {
			$title = (string) $post->post_title;

			printf(
				'<li class="wpcpm-sponsor-reports__item"><span class="wpcpm-sponsor-reports__title">%1$s</span> <a class="wpcpm-button wpcpm-button--secondary" href="%2$s" target="_blank" rel="noopener">%3$s<span class="screen-reader-text"> %1$s</span></a>',
				esc_html( $title ),
				esc_url( WPCPM_Sponsor_Report_Files::print_url( $post ) ),
				esc_html__( 'Open', 'wpcredits-program-manager' )
			);

			if ( null !== WPCPM_Sponsor_Report_Files::file_of( $post ) ) {
				printf(
					' <a class="wpcpm-button wpcpm-button--secondary" href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
					esc_url( WPCPM_Sponsor_Report_Files::download_url( $post ) ),
					esc_html__( 'PDF', 'wpcredits-program-manager' ),
					esc_html( $title )
				);
			}

			echo '</li>';
		}

		echo '</ul>';
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The card's row: how many yearly reports there are, the final ones, a sponsor never reading a
	 * draft, and the months the latest one's term runs from and to, as its title names them. A report
	 * whose last day cannot be read is counted and names no term, rather than a sentence with the
	 * months left blank.
	 *
	 * @param WP_Post[] $reports The final reports, the newest term first.
	 * @return string
	 */
	private static function state_line( array $reports ) {
		$count  = count( $reports );
		$period = WPCPM_Sponsor_Report::period( WPCPM_Sponsor_Report::until_of( $reports[0] ) );

		if ( '' === $period['from'] ) {
			/* translators: %s: how many yearly reports the sponsor has, the final ones. */
			return sprintf( _n( '%s yearly report', '%s yearly reports', $count, 'wpcredits-program-manager' ), number_format_i18n( $count ) );
		}

		return sprintf(
			/* translators: 1: how many yearly reports the sponsor has, the final ones, 2: the month and year the latest one's term starts in, 3: the month and year it ends in. */
			_n( '%1$s yearly report, the latest for %2$s to %3$s', '%1$s yearly reports, the latest for %2$s to %3$s', $count, 'wpcredits-program-manager' ),
			number_format_i18n( $count ),
			WPCPM_Sponsor_Report::month_of( $period['from'] ),
			WPCPM_Sponsor_Report::month_of( $period['until'] )
		);
	}

	/**
	 * The sponsor's final reports, the newest term first, in the order the store keeps them. The
	 * card's list and its row, and the At a glance tile that opens the card, all read this one list,
	 * so the tile counts exactly the reports the card lists.
	 *
	 * @param string $record Sponsor key.
	 * @return WP_Post[]
	 */
	public static function finals( $record ) {
		return array_values(
			array_filter(
				WPCPM_Sponsor_Report::reports_of( (string) $record ),
				static function ( $post ) {
					return WPCPM_Sponsor_Report::STATE_FINAL === WPCPM_Sponsor_Report::state( $post );
				}
			)
		);
	}
}
