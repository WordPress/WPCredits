<?php
/**
 * The Sponsor Dashboard's agreement card: the state, the upload form and the history.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a sponsor sees about its own agreement.
 *
 * The name the Sponsor Dashboard's card loop has reserved since Phase S1. It is a view and
 * nothing else: every decision, every write and every refusal lives in
 * `WPCPM_Sponsor_Agreement`, and this class holds no policy call of its own because the
 * handlers behind its two forms ask the fence for themselves.
 *
 * **Nothing about the review.** A sponsor is told that its document arrived, that it is waiting,
 * and how long the program aims to take. It is not told who is reading it, what a reviewer's
 * checklist says, or what the scan noticed: those are the program's working notes and the
 * manager's screen is where they belong. The one exception is a returned document, whose note is
 * printed in full, because that note was written to be read by the company.
 */
final class WPCPM_Sponsor_Agreement_Card {

	/** The card's anchor and flash key on the Sponsor Dashboard. */
	const CARD = 'agreement';

	/** How many events the history prints. */
	const HISTORY = 20;

	/**
	 * This card's outcomes: the agreement class's own, since it is the class that flashes them.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return WPCPM_Sponsor_Agreement::messages();
	}

	/**
	 * The card. Folded until a flash names it; its row says where the agreement stands, and while it
	 * is folded Upload follows the fold, a link to the card, whenever that state asks the company for
	 * a file. The state is read from the summary once, here, for the row, the action and the body.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 */
	public static function render( $record, array $context ) {
		$record  = trim( (string) $record );
		$summary = WPCPM_Sponsor_Agreement::summary( $record );
		$open    = isset( $context['open'] ) && self::CARD === $context['open'];
		$state   = isset( $summary['state'] ) ? (string) $summary['state'] : WPCPM_Sponsor_Agreement::SUMMARY_NONE;

		WPCPM_Dashboard_Card::open(
			array(
				'key'        => self::CARD,
				'title'      => __( 'Collaboration Agreement', 'wpcredits-program-manager' ),
				'open'       => $open,
				'state'      => self::state_line( $summary, $state ),
				'action'     => self::asks_for_file( $state ) ? array(
					'label'   => __( 'Upload', 'wpcredits-program-manager' ),
					/* translators: read after "Upload" by a screen reader alone. */
					'context' => __( 'the signed Collaboration Agreement', 'wpcredits-program-manager' ),
				) : null,
				'body_class' => 'wpcpm-agreement',
			)
		);

		self::render_state( $record, $summary, $state );
		self::render_history( $record );

		WPCPM_Dashboard_Card::close();
	}

	/**
	 * Whether the Collaboration Agreement waits on the company, and how, for the Sponsor Dashboard's
	 * At a glance strip: `returned` or `revoked` while the card asks the company for a file
	 * (`asks_for_file()`, the states whose row offers Upload), and '' for none on file, one in review,
	 * an accepted one and one the program holds. The strip counts through this, so a tile is drawn in
	 * exactly the states the card's Upload is, from the same summary the card reads.
	 *
	 * @param string $record Sponsor key.
	 * @return string `returned`, `revoked` or ''.
	 */
	public static function waiting_state( $record ) {
		$summary = WPCPM_Sponsor_Agreement::summary( $record );
		$state   = isset( $summary['state'] ) ? (string) $summary['state'] : WPCPM_Sponsor_Agreement::SUMMARY_NONE;

		return self::asks_for_file( $state ) ? $state : '';
	}

	/**
	 * One branch per summary state, in the sponsor's words.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $summary From `WPCPM_Sponsor_Agreement::summary()`.
	 * @param string $state   Its `SUMMARY_*` state, as `render()` read it.
	 */
	private static function render_state( $record, array $summary, $state ) {
		$days = max( 1, (int) WPCPM_Settings::get_value( 'agreement_review_days', 3 ) );

		printf( '<p class="wpcpm-agreement__state">%s</p>', esc_html( self::state_word( $state ) ) );

		switch ( $state ) {
			case WPCPM_Sponsor_Agreement::SUMMARY_SUBMITTED:
				printf(
					'<p class="wpcpm-agreement__lead">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: date the document arrived, 2: how many working days. */
							_n(
								'We have your signed agreement. It has been waiting for the program to read it since %1$s; we aim to answer within %2$s working day, and you will get an email either way.',
								'We have your signed agreement. It has been waiting for the program to read it since %1$s; we aim to answer within %2$s working days, and you will get an email either way.',
								$days,
								'wpcredits-program-manager'
							),
							self::post_date( (int) $summary['pending_id'] ),
							number_format_i18n( $days )
						)
					)
				);
				self::render_download( (int) $summary['pending_id'], __( 'Download the copy we hold', 'wpcredits-program-manager' ) );
				self::render_withdraw( (int) $summary['pending_id'] );
				break;

			case WPCPM_Sponsor_Agreement::SUMMARY_RETURNED:
				printf( '<p class="wpcpm-agreement__lead">%s</p>', esc_html__( 'An Administrator read your agreement and sent it back, with this note:', 'wpcredits-program-manager' ) );
				self::render_note( $record, WPCPM_Sponsor_Agreement::STATE_RETURNED );
				self::render_upload( $record, __( 'Upload the corrected agreement, signed, as a PDF.', 'wpcredits-program-manager' ) );
				break;

			case WPCPM_Sponsor_Agreement::SUMMARY_REVOKED:
				printf( '<p class="wpcpm-agreement__lead">%s</p>', esc_html__( 'The agreement we held is no longer in force, with this note from the program. Nothing about your dashboard, your offers or your codes changed.', 'wpcredits-program-manager' ) );
				self::render_note( $record, WPCPM_Sponsor_Agreement::STATE_REVOKED );
				self::render_upload( $record, __( 'Upload a new signed agreement whenever you are ready.', 'wpcredits-program-manager' ) );
				break;

			case WPCPM_Sponsor_Agreement::SUMMARY_ACCEPTED:
				printf(
					'<p class="wpcpm-agreement__lead">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: the date the agreement was accepted. */
							__( 'Your signed agreement was accepted on %s.', 'wpcredits-program-manager' ),
							(string) $summary['accepted_at']
						)
					)
				);
				self::render_download( (int) $summary['agreement_id'], __( 'Download the copy we hold', 'wpcredits-program-manager' ) );
				self::render_upload( $record, __( 'Upload a newer signed agreement to replace it.', 'wpcredits-program-manager' ) );
				break;

			case WPCPM_Sponsor_Agreement::SUMMARY_ON_FILE:
				// No download and no link: a legacy row points at a folder in the program's own
				// Drive, which is the program's and not the company's to hand out.
				printf(
					'<p class="wpcpm-agreement__lead">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: the date the program recorded it. */
							__( 'The program already holds a signed agreement with your company, recorded on %s. There is nothing for you to upload. Ask your program contact if you would like a copy.', 'wpcredits-program-manager' ),
							(string) $summary['accepted_at']
						)
					)
				);
				break;

			default:
				printf( '<p class="wpcpm-agreement__lead">%s</p>', esc_html__( 'There is no agreement on file for your company. Nothing on this dashboard depends on one: your offers, your codes and your posts work either way. If the program has asked you to sign one, upload the signed PDF here.', 'wpcredits-program-manager' ) );
				self::render_upload( $record, '' );
				break;
		}
	}

	/**
	 * The state in one short phrase, for the pill above the lead.
	 *
	 * @param string $state A `SUMMARY_*` value.
	 * @return string
	 */
	private static function state_word( $state ) {
		switch ( $state ) {
			case WPCPM_Sponsor_Agreement::SUMMARY_SUBMITTED:
				return __( 'Waiting for the program', 'wpcredits-program-manager' );
			case WPCPM_Sponsor_Agreement::SUMMARY_RETURNED:
				return __( 'Sent back to you', 'wpcredits-program-manager' );
			case WPCPM_Sponsor_Agreement::SUMMARY_REVOKED:
				return __( 'Out of force', 'wpcredits-program-manager' );
			case WPCPM_Sponsor_Agreement::SUMMARY_ACCEPTED:
				return __( 'Accepted', 'wpcredits-program-manager' );
			case WPCPM_Sponsor_Agreement::SUMMARY_ON_FILE:
				return __( 'On file with the program', 'wpcredits-program-manager' );
		}

		return __( 'Nothing on file', 'wpcredits-program-manager' );
	}

	/**
	 * The card's row: the state in the pill's own words, with the day for the two states that have
	 * one, written the way the site writes a day, as the design the owner approved on 27 September
	 * 2026 gives it ("Accepted on 3 September 2026"). The lead under the pill keeps its own words.
	 *
	 * @param array  $summary From `WPCPM_Sponsor_Agreement::summary()`.
	 * @param string $state   Its `SUMMARY_*` state, as `render()` read it.
	 * @return string
	 */
	private static function state_line( array $summary, $state ) {
		$day = isset( $summary['accepted_at'] ) ? (string) $summary['accepted_at'] : '';

		if ( WPCPM_Sponsor_Tiers::is_date( $day ) ) {
			if ( WPCPM_Sponsor_Agreement::SUMMARY_ACCEPTED === $state ) {
				/* translators: %s: the day the agreement was accepted. */
				return sprintf( __( 'Accepted on %s', 'wpcredits-program-manager' ), WPCPM_Sponsor_Tiers::format_day( $day ) );
			}

			if ( WPCPM_Sponsor_Agreement::SUMMARY_ON_FILE === $state ) {
				/* translators: %s: the day the program recorded the agreement it holds. */
				return sprintf( __( 'On file with the program since %s', 'wpcredits-program-manager' ), WPCPM_Sponsor_Tiers::format_day( $day ) );
			}
		}

		return self::state_word( $state );
	}

	/**
	 * Whether a state asks the company for a file: an agreement sent back to it, or one no longer in
	 * force. The card offers the upload form in more states than these, since a company may upload
	 * whenever it likes, but with nothing on file nothing on the dashboard depends on an agreement,
	 * and an accepted one asks for no newer copy.
	 *
	 * @param string $state A `SUMMARY_*` value.
	 * @return bool
	 */
	private static function asks_for_file( $state ) {
		return in_array( $state, array( WPCPM_Sponsor_Agreement::SUMMARY_RETURNED, WPCPM_Sponsor_Agreement::SUMMARY_REVOKED ), true );
	}

	/**
	 * The upload form: the signed PDF and the declaration, and no free-text field.
	 *
	 * A textarea on an upload form is a second place for personal data to land, in a record
	 * whose whole point is that only the document is kept. The declaration is a checkbox with a
	 * hidden `value="0"` companion in front of it, so an unticked box posts `0` rather than
	 * nothing: a field that is simply absent is indistinguishable from one a proxy dropped, and
	 * "the box was not ticked" and "the form did not arrive whole" are not the same refusal.
	 *
	 * @param string $record Sponsor key.
	 * @param string $intro  A sentence above the form, or '' for none.
	 */
	private static function render_upload( $record, $intro ) {
		$base   = 'wpcpm-sagr-' . sanitize_html_class( $record );
		$max_mb = max( 1, (int) WPCPM_Settings::get_value( 'agreement_max_mb', 10 ) );

		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form" enctype="multipart/form-data" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Uploading', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Sponsor_Agreement::ACTION_UPLOAD . '_' . $record );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Sponsor_Agreement::ACTION_UPLOAD ) );
		printf( '<input type="hidden" name="wpcpm_sponsor" value="%s" />', esc_attr( $record ) );

		if ( '' !== trim( (string) $intro ) ) {
			printf( '<p class="wpcpm-student__note">%s</p>', esc_html( $intro ) );
		}

		printf(
			'<p class="wpcpm-sponsor__field"><label for="%1$s-file">%2$s <span class="wpcpm-field__required">%4$s</span></label> <input type="file" id="%1$s-file" name="%3$s" accept="application/pdf,.pdf" required /></p>',
			esc_attr( $base ),
			esc_html(
				sprintf(
					/* translators: %s: the largest upload the site accepts, in megabytes. */
					__( 'The signed agreement, as a PDF of up to %s MB', 'wpcredits-program-manager' ),
					number_format_i18n( $max_mb )
				)
			),
			esc_attr( WPCPM_Sponsor_Agreement::FIELD_FILE ),
			esc_html__( 'Required', 'wpcredits-program-manager' )
		);

		// The companion goes first on purpose: a later field of the same name wins, so the
		// ticked box overwrites this and an unticked one leaves `0` behind.
		printf( '<input type="hidden" name="%s" value="0" />', esc_attr( WPCPM_Sponsor_Agreement::FIELD_SIGNED ) );

		printf(
			'<p class="wpcpm-sponsor__field"><label for="%1$s-signed"><input type="checkbox" id="%1$s-signed" name="%2$s" value="1" required /> %3$s <span class="wpcpm-field__required">%4$s</span></label></p>',
			esc_attr( $base ),
			esc_attr( WPCPM_Sponsor_Agreement::FIELD_SIGNED ),
			esc_html__( 'This document is signed by somebody who can commit the company.', 'wpcredits-program-manager' ),
			esc_html__( 'Required', 'wpcredits-program-manager' )
		);

		printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Upload the signed agreement', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * Withdraw, for a document nobody has read yet.
	 *
	 * @param int $post_id The submitted document.
	 */
	private static function render_withdraw( $post_id ) {
		if ( $post_id < 1 ) {
			return;
		}

		printf(
			'<form method="post" action="%1$s" class="wpcpm-sponsor__form" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-confirm="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Withdrawing', 'wpcredits-program-manager' ),
			esc_attr__( 'Withdraw the agreement you uploaded? The file is deleted from this site at once, and you can upload another whenever you are ready.', 'wpcredits-program-manager' )
		);
		wp_nonce_field( WPCPM_Sponsor_Agreement::ACTION_WITHDRAW . '_' . (int) $post_id );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Sponsor_Agreement::ACTION_WITHDRAW ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( WPCPM_Sponsor_Agreement::FIELD_POST ), (int) $post_id );
		printf( '<p><button type="submit" class="wpcpm-button">%s</button></p>', esc_html__( 'Withdraw it', 'wpcredits-program-manager' ) );
		echo '</form>';
	}

	/**
	 * The link to the copy this site holds, nonce-keyed to the document.
	 *
	 * @param int    $post_id The document.
	 * @param string $label   The link text.
	 */
	private static function render_download( $post_id, $label ) {
		if ( $post_id < 1 ) {
			return;
		}

		printf(
			'<p class="wpcpm-agreement__download"><a href="%1$s">%2$s</a></p>',
			esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action' => WPCPM_Sponsor_Agreement::ACTION_DOWNLOAD,
							'post'   => (int) $post_id,
						),
						admin_url( 'admin-post.php' )
					),
					WPCPM_Sponsor_Agreement::ACTION_DOWNLOAD . '_' . (int) $post_id
				)
			),
			esc_html( $label )
		);
	}

	/**
	 * The manager's note on the most recent document in one state.
	 *
	 * @param string $record Sponsor key.
	 * @param string $state  A `STATE_*` value.
	 */
	private static function render_note( $record, $state ) {
		foreach ( WPCPM_Sponsor_Agreement::posts_for( $record ) as $post ) {
			if ( (string) get_post_meta( $post->ID, WPCPM_Sponsor_Agreement::META_STATE, true ) !== (string) $state ) {
				continue;
			}

			$note = (string) get_post_meta( $post->ID, WPCPM_Sponsor_Agreement::META_NOTE, true );

			if ( '' !== trim( $note ) ) {
				printf( '<blockquote class="wpcpm-agreement__note">%s</blockquote>', nl2br( esc_html( $note ) ) );
			}

			return;
		}
	}

	/**
	 * Every event on this company's documents, newest first.
	 *
	 * The history is what makes "we sent that in March" answerable without asking a manager.
	 * Only the words this class's events carry, never an actor's name: whose press it was is the
	 * program's business and the audit log's.
	 *
	 * @param string $record Sponsor key.
	 */
	private static function render_history( $record ) {
		$events = array();

		foreach ( WPCPM_Sponsor_Agreement::posts_for( $record ) as $post ) {
			foreach ( (array) get_post_meta( $post->ID, WPCPM_Sponsor_Agreement::META_EVENT, false ) as $event ) {
				if ( is_array( $event ) && ! empty( $event['event'] ) ) {
					$events[] = array(
						'event' => (string) $event['event'],
						'at'    => isset( $event['at'] ) ? (int) $event['at'] : 0,
					);
				}
			}
		}

		if ( empty( $events ) ) {
			return;
		}

		usort(
			$events,
			static function ( $a, $b ) {
				return $b['at'] - $a['at'];
			}
		);

		echo '<ul class="wpcpm-agreement__history">';

		foreach ( array_slice( $events, 0, self::HISTORY ) as $event ) {
			printf(
				'<li class="wpcpm-agreement__event">%1$s <span class="wpcpm-agreement__when">%2$s</span></li>',
				esc_html( $event['event'] ),
				esc_html( $event['at'] > 0 ? wp_date( 'Y-m-d', $event['at'] ) : '' )
			);
		}

		echo '</ul>';
	}

	/**
	 * The date one document was created, Y-m-d.
	 *
	 * @param int $post_id The document.
	 * @return string
	 */
	private static function post_date( $post_id ) {
		$post = get_post( (int) $post_id );

		return $post instanceof WP_Post ? substr( (string) $post->post_date, 0, 10 ) : '';
	}
}
