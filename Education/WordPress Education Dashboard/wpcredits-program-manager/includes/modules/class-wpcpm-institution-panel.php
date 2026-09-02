<?php
/**
 * Institutions module - the locked agreement panel, and the card a settled dashboard keeps.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one thing an institution account can see while its Collaboration Agreement is not settled.
 *
 * `WPCPM_Institutions_Dashboard::render()` branches the moment it knows which institution the
 * viewer is: a member of an unsettled one gets the identity header, then this panel, and then
 * nothing. Not the comparison strip, not the filter bar, not the roster footer. An account at
 * this stage has no roster, and a "0 students" line would read as data loss rather than as a
 * gate. A program manager looking at the same institution through the switcher gets the whole
 * dashboard under a banner naming the state, because hiding is a courtesy here and the fence
 * is `WPCPM_Institution_Policy`, which refuses a member inside `ground_member()` whatever this
 * class draws.
 *
 * **Why the panel is a state machine and not a form.** The design spec's section 7.4 gives one
 * row per summary state, and the words differ in what they ask the reader to do: `none` needs
 * three numbered steps, `returned` needs the manager's note before anything else, `revoked`
 * needs the country contact's address, and `submitted` needs the reader to do nothing at all.
 * A single "upload your agreement" panel would be wrong in four of the five cases.
 *
 * **What Phase 2 does not have yet.** Generating the program's template and uploading a signed
 * PDF are Phase 3, together with the private file store that receives them. Where the spec's
 * panel offers those controls, this phase prints what the step will be and says the one thing
 * that is true today: an institution that has already signed does not have to sign again,
 * because a program manager can record the copy the program holds through the on-file route
 * below, and the account opens on the next page load.
 */
class WPCPM_Institution_Panel {

	/** The panel's element ID, so a link or a stylesheet can find it. */
	const ANCHOR = 'wpcpm-agreement';

	/**
	 * The outcomes `WPCPM_Institution_Agreement::handle_on_file()` can flash.
	 *
	 * Kept here rather than in each screen, because the on-file form is drawn in two places
	 * (this panel and the manager's institution row) and lands wherever it was pressed. Each
	 * row is `array( notice type, message )`, the shape both screens already use.
	 *
	 * `agreement-busy` is the one that says nothing happened at all. Recording an agreement
	 * on file is a transition like acceptance and takes the institution's own `add_option()`
	 * lock before it asks whether an accepted agreement already stands, so two managers
	 * pressing Record it in the same second cannot both find none and both insert one. The
	 * one that loses the race is told it lost, in the words the other refusals use, rather
	 * than being told the account is open.
	 *
	 * @return array<string, array>
	 */
	public static function messages() {
		return array(
			'agreement-on-file'     => array( 'success', __( 'The agreement is recorded as on file. The institution\'s account is open.', 'wpcredits-program-manager' ) ),
			'agreement-link'        => array( 'error', __( 'Nothing was recorded. Paste the Drive link to the folder or the file: it has to be an https address on drive.google.com or docs.google.com.', 'wpcredits-program-manager' ) ),
			'agreement-standing'    => array( 'error', __( 'Nothing was recorded. An accepted agreement already stands for this institution.', 'wpcredits-program-manager' ) ),
			'agreement-unknown'     => array( 'error', __( 'Nothing was recorded. That institution is not in the pipeline index; run a sync and try again.', 'wpcredits-program-manager' ) ),
			'agreement-busy'        => array( 'error', __( 'Nothing was recorded. Another write to this institution\'s agreement was in flight, from a sync or from somebody else pressing the same button. Try again in a moment.', 'wpcredits-program-manager' ) ),
			'agreement-all-none'    => array( 'info', __( 'Nothing to record: every Confirmed institution already has an agreement recorded.', 'wpcredits-program-manager' ) ),
			'agreement-on-file-all' => array( 'success', self::on_file_all_summary() ),
			'agreement-airtable'    => array( 'error', __( 'Airtable could not be updated, so nothing was recorded here either. The base is the record of this state, and the site does not open an account the base has not agreed to.', 'wpcredits-program-manager' ) ),
			'agreement-not-saved'   => array( 'error', __( 'Airtable was updated but the record on this site could not be written. Press Refresh on the Institutions screen: the next reconcile completes it.', 'wpcredits-program-manager' ) ),
			'agreement-later'       => array( 'info', __( 'Airtable and the site record were both written, but this institution\'s state was being rebuilt at that moment. The account opens after the next sync or Refresh.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Draw the agreement panel for one institution.
	 *
	 * Prints nothing for a settled institution: the dashboard is the answer then, and
	 * `WPCPM_Institution_Agreement_Card` names the agreement at its foot. Prints nothing for
	 * a viewer the fence refuses either, which is the same answer for a different reason and
	 * deliberately reads the same from outside.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $context   The dashboard's context. `can_manage` (bool) is read here.
	 */
	public static function render( $record_id, array $context ) {
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		// The render-from-cache pattern of design spec 5.4: decide first, then act on what
		// came back. Everything below this line is one institution's - the state of its
		// agreement, a program manager's note verbatim, its country contact's address - and
		// the dashboard shell having asked the same question before it called this method is
		// not a reason to skip it. A second caller that forgot the shell's own check is what
		// the fence is for, and it costs one array walk to keep.
		//
		// ACT_AGREEMENT because that is what this panel draws, and because it is the one
		// action the gate does not apply to: a member whose agreement is outstanding is
		// exactly who the panel exists for, and any other action would refuse every one of
		// them inside `ground_member()`.
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_institution( $record_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			// The empty state, never the refusal text: naming which state another
			// institution's agreement is in would answer a question the reader is not
			// entitled to ask, and the dashboard shell has already drawn this page's own
			// "nothing to show" notice above.
			return;
		}

		if ( WPCPM_Institution_Agreement::is_settled( $record_id ) ) {
			return;
		}

		$can_manage = ! empty( $context['can_manage'] );
		$summary    = WPCPM_Institution_Agreement::summary( $record_id );
		$row        = WPCPM_Institutions_Index::row( $record_id );
		$row        = is_array( $row ) ? $row : WPCPM_Institutions_Index::empty_row();

		printf( '<section class="wpcpm-agreement-panel" id="%s">', esc_attr( self::ANCHOR ) );
		printf(
			'<h2 class="wpcpm-agreement-panel__title">%s</h2>',
			esc_html__( 'Collaboration Agreement', 'wpcredits-program-manager' )
		);

		self::render_flash();
		self::render_state( $record_id, $summary, $row, $can_manage );

		if ( $can_manage ) {
			self::render_on_file_form( $record_id );
		}

		self::render_read_line( $record_id );

		echo '</section>';
	}

	/**
	 * Print whatever the last on-file attempt flashed, once.
	 *
	 * Public because the card calls it too: a successful recording settles the institution,
	 * so the page the manager lands on has a card and no panel, and the outcome has to be
	 * printed by whichever of the two is drawn.
	 */
	public static function render_flash() {
		$status   = sanitize_key( (string) WPCPM_Flash::take( WPCPM_Institutions::FLASH ) );
		$messages = self::messages();

		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-agreement-panel__message wpcpm-agreement-panel__message--%1$s">%2$s</p>',
			esc_attr( $messages[ $status ][0] ),
			esc_html( $messages[ $status ][1] )
		);
	}

	/**
	 * The manager's "record an agreement on file" form.
	 *
	 * Public and capability-checked here rather than at the call site, so the manager's
	 * institution row on the Institutions screen can print the same form with one call and
	 * cannot print it to the wrong person by forgetting the check.
	 *
	 * The date and the location note are optional; the Drive link is not. A recorded
	 * agreement with no link is a claim nobody can check later, which is the state the
	 * reconciliation card exists to report on rather than to create.
	 *
	 * @param string $record_id Institutions record ID.
	 */
	public static function render_on_file_form( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) || ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		$row  = WPCPM_Institutions_Index::row( $record_id );
		$name = is_array( $row ) ? trim( (string) $row['name'] ) : '';
		$name = '' !== $name ? $name : $record_id;
		$base = 'wpcpm-on-file-' . sanitize_html_class( $record_id );

		echo '<form class="wpcpm-agreement-panel__on-file" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';

		wp_nonce_field( WPCPM_Institution_Agreement::ACTION_ON_FILE . '_' . $record_id );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( WPCPM_Institution_Agreement::ACTION_ON_FILE ) );
		printf( '<input type="hidden" name="wpcpm_agreement_record" value="%s" />', esc_attr( $record_id ) );

		printf(
			'<h3 class="wpcpm-agreement-panel__on-file-title">%s</h3>',
			esc_html__( 'Record an agreement the program already holds', 'wpcredits-program-manager' )
		);
		printf(
			'<p class="wpcpm-agreement-panel__on-file-lede">%s</p>',
			esc_html__( 'For an institution that signed before this site existed. Airtable is written first; the account opens on the next page load.', 'wpcredits-program-manager' )
		);

		printf(
			'<p><label for="%1$s-drive">%2$s</label> <input type="url" class="wpcpm-agreement-panel__input" id="%1$s-drive" name="wpcpm_agreement_drive" required placeholder="%3$s" /></p>',
			esc_attr( $base ),
			esc_html__( 'Drive link to the folder or the file (required)', 'wpcredits-program-manager' ),
			esc_attr__( 'https://drive.google.com/...', 'wpcredits-program-manager' )
		);

		printf(
			'<p><label for="%1$s-signed">%2$s</label> <input type="date" class="wpcpm-agreement-panel__input" id="%1$s-signed" name="wpcpm_agreement_signed_on" /></p>',
			esc_attr( $base ),
			esc_html__( 'Date signed (optional)', 'wpcredits-program-manager' )
		);

		printf(
			'<p><label for="%1$s-where">%2$s</label> <input type="text" class="wpcpm-agreement-panel__input" id="%1$s-where" name="wpcpm_agreement_where" maxlength="%3$d" placeholder="%4$s" /></p>',
			esc_attr( $base ),
			esc_html__( 'Where it is, in one line (optional)', 'wpcredits-program-manager' ),
			(int) WPCPM_Institution_Agreement::MAX_LOCATION,
			esc_attr__( 'second folder, the 2025 copy', 'wpcredits-program-manager' )
		);

		printf(
			'<button type="submit" class="wpcpm-button" onclick="return confirm(%1$s)">%2$s</button>',
			esc_attr(
				wp_json_encode(
					sprintf(
						/* translators: %s: institution name. */
						__( 'Record a signed agreement on file for %s? This opens their account on the site and sets Agreement Status to On file in Airtable.', 'wpcredits-program-manager' ),
						$name
					)
				)
			),
			esc_html__( 'Record it', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * The words for one summary state.
	 *
	 * One branch per row of the design spec's section 7.4 table, plus the sixth case the
	 * table does not have a row for: an accepted document on this site under a base that
	 * says something else. That combination is locked by `is_settled()` and would otherwise
	 * fall through every branch and print an empty panel, which is the one outcome a locked
	 * account must never see.
	 *
	 * @param string $record_id  Institutions record ID.
	 * @param array  $summary    From `WPCPM_Institution_Agreement::summary()`.
	 * @param array  $row        The institution's index row.
	 * @param bool   $can_manage Whether the viewer holds CAP_MANAGE.
	 */
	private static function render_state( $record_id, array $summary, array $row, $can_manage ) {
		$state = isset( $summary['state'] ) ? (string) $summary['state'] : WPCPM_Institution_Agreement::SUMMARY_NONE;

		switch ( $state ) {
			case WPCPM_Institution_Agreement::SUMMARY_GENERATED:
				printf(
					'<p class="wpcpm-agreement-panel__lede">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: date the template was generated, 2: template version. */
							__( 'You generated the program\'s agreement on %1$s (template %2$s). It is not signed and uploaded yet.', 'wpcredits-program-manager' ),
							self::post_date( $summary['generated_id'] ),
							self::template_version( $summary['generated_id'] )
						)
					)
				);
				self::render_steps();
				self::render_on_file_note( $row, $can_manage );
				break;

			case WPCPM_Institution_Agreement::SUMMARY_SUBMITTED:
				printf(
					'<p class="wpcpm-agreement-panel__lede">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: date the signed agreement was received. */
							__( 'We received your signed agreement on %s. A program manager will review it and you will get an email either way.', 'wpcredits-program-manager' ),
							self::post_date( $summary['pending_id'] )
						)
					)
				);
				printf(
					'<p class="wpcpm-agreement-panel__soon">%s</p>',
					esc_html__( 'Downloading your own copy and withdrawing it arrive in the next release of this site.', 'wpcredits-program-manager' )
				);
				break;

			case WPCPM_Institution_Agreement::SUMMARY_RETURNED:
				printf(
					'<p class="wpcpm-agreement-panel__lede">%s</p>',
					esc_html__( 'A program manager returned your agreement, with this note:', 'wpcredits-program-manager' )
				);
				self::render_note( $record_id, WPCPM_Institution_Agreement::STATE_RETURNED );
				printf(
					'<p class="wpcpm-agreement-panel__soon">%s</p>',
					esc_html__( 'Uploading a corrected copy arrives in the next release of this site. Until it does, send the corrected agreement to your program contact and it can be recorded for you.', 'wpcredits-program-manager' )
				);
				self::render_contact( $row, $can_manage );
				break;

			case WPCPM_Institution_Agreement::SUMMARY_REVOKED:
				printf(
					'<p class="wpcpm-agreement-panel__lede">%s</p>',
					esc_html__( 'The program revoked this institution\'s agreement. The account reaches this page and nothing else until a new agreement is accepted. The note the program left:', 'wpcredits-program-manager' )
				);
				self::render_note( $record_id, WPCPM_Institution_Agreement::STATE_REVOKED );
				printf(
					'<p class="wpcpm-agreement-panel__soon">%s</p>',
					esc_html__( 'Uploading a new signed agreement arrives in the next release of this site.', 'wpcredits-program-manager' )
				);
				self::render_contact( $row, $can_manage );
				break;

			case WPCPM_Institution_Agreement::SUMMARY_ACCEPTED:
			case WPCPM_Institution_Agreement::SUMMARY_ON_FILE:
				// Settled institutions returned before this method was reached, so an accepted
				// document here means the base disagrees with the site. Named as the program's
				// job rather than the institution's: there is nothing the reader can do.
				printf(
					'<p class="wpcpm-agreement-panel__lede">%s</p>',
					esc_html__( 'This site holds an accepted agreement for your institution, but the program\'s own record does not agree with it yet, so the account stays closed. A program manager can see the difference and will settle it.', 'wpcredits-program-manager' )
				);
				self::render_contact( $row, $can_manage );
				break;

			default:
				printf(
					'<p class="wpcpm-agreement-panel__lede">%s</p>',
					esc_html__( 'The program has no Collaboration Agreement recorded for your institution. Until it does, this page is everything your account can reach.', 'wpcredits-program-manager' )
				);
				self::render_steps();
				self::render_on_file_note( $row, $can_manage );
				break;
		}
	}

	/**
	 * The three numbered steps.
	 *
	 * Printed for `none` and for `generated`, which is the same journey one step further
	 * along. The first step names both routes, because an institution-specific agreement is
	 * a real choice with a real cost, and a reader who learns about it after generating the
	 * template has been told too late.
	 */
	private static function render_steps() {
		echo '<ol class="wpcpm-agreement-panel__steps">';

		printf(
			'<li>%s</li>',
			esc_html__( 'Get the agreement: generate the program\'s template, or upload an agreement of your own. A program manager reads an institution-specific agreement in full, so that path takes longer.', 'wpcredits-program-manager' )
		);
		printf(
			'<li>%s</li>',
			esc_html__( 'Sign it. It needs somebody who can commit the institution.', 'wpcredits-program-manager' )
		);
		printf(
			'<li>%s</li>',
			esc_html__( 'Upload the signed PDF here. A program manager reviews it and you get an email either way.', 'wpcredits-program-manager' )
		);

		echo '</ol>';

		printf(
			'<p class="wpcpm-agreement-panel__soon">%s</p>',
			esc_html__( 'Generating and uploading arrive in the next release of this site.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The sentence that saves a long-standing partner from signing twice.
	 *
	 * Every institution the program can pilot this with signed years ago, so the first thing
	 * a locked account needs to know is that the program can record what it already holds.
	 * Not shown to a manager: the manager is the person it is asking for, and the form to do
	 * it with is a few lines further down the same panel.
	 *
	 * @param array $row        The institution's index row.
	 * @param bool  $can_manage Whether the viewer holds CAP_MANAGE.
	 */
	private static function render_on_file_note( array $row, $can_manage ) {
		if ( $can_manage ) {
			return;
		}

		printf(
			'<p class="wpcpm-agreement-panel__on-file-note">%s</p>',
			esc_html__( 'If your institution has already signed a Collaboration Agreement with the program, it does not need to sign again. A program manager can record the copy the program holds, and your account opens as soon as they do.', 'wpcredits-program-manager' )
		);

		self::render_contact( $row, $can_manage );
	}

	/**
	 * The country contact's address, when the base names one.
	 *
	 * Named, never mailed: the country contact is who an institution writes to, and adding
	 * them as a recipient of the program's own queue mail is a different decision the design
	 * spec makes elsewhere and does not make here.
	 *
	 * @param array $row        The institution's index row.
	 * @param bool  $can_manage Whether the viewer holds CAP_MANAGE.
	 */
	private static function render_contact( array $row, $can_manage ) {
		if ( $can_manage ) {
			return;
		}

		$country = isset( $row['country'] ) ? (string) $row['country'] : '';
		$routing = WPCPM_Countries::routing( $country );

		if ( null === $routing || '' === trim( (string) $routing['email'] ) ) {
			printf(
				'<p class="wpcpm-agreement-panel__contact">%s</p>',
				esc_html__( 'Write to the program manager who has been in touch with your institution.', 'wpcredits-program-manager' )
			);

			return;
		}

		printf(
			'<p class="wpcpm-agreement-panel__contact">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: country name, 2: the contact's email address. */
					__( 'Your program contact for %1$s is %2$s.', 'wpcredits-program-manager' ),
					WPCPM_Countries::name_of( $country ),
					$routing['email']
				)
			)
		);
	}

	/**
	 * A manager's note, verbatim, dated and signed.
	 *
	 * Verbatim is the point: the note is why the document came back, and a summary of it
	 * would send the institution round the same loop. Stored as plain text by the handler
	 * that wrote it, so escaping and then adding paragraphs is the whole treatment.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $state     `STATE_RETURNED` or `STATE_REVOKED`.
	 */
	private static function render_note( $record_id, $state ) {
		$post = self::latest_post( $record_id, $state );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$note = trim( (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_NOTE, true ) );
		$who  = get_userdata( (int) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_DECIDED_BY, true ) );
		$when = trim( (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_DECIDED_AT, true ) );

		echo '<blockquote class="wpcpm-agreement-panel__note">';

		if ( '' !== $note ) {
			echo wp_kses_post( wpautop( esc_html( $note ) ) );
		} else {
			printf( '<p>%s</p>', esc_html__( 'No note was left.', 'wpcredits-program-manager' ) );
		}

		printf(
			'<footer class="wpcpm-agreement-panel__note-by">%s</footer>',
			esc_html(
				sprintf(
					/* translators: 1: the program manager's name, 2: date. */
					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
					$who instanceof WP_User ? $who->display_name : __( 'A program manager', 'wpcredits-program-manager' ),
					'' !== $when ? $when : self::post_date( $post->ID )
				)
			)
		);

		echo '</blockquote>';
	}

	/**
	 * When the panel's numbers were last checked against the base.
	 *
	 * The agreement option carries its own read time, which is the one that matters here:
	 * the pipeline index can be fresh while this institution's gate was decided by a run
	 * hours earlier, and a page that borrowed the index's timestamp would say so wrongly.
	 *
	 * @param string $record_id Institutions record ID.
	 */
	private static function render_read_line( $record_id ) {
		$option  = WPCPM_Institution_Agreement::option( $record_id );
		$updated = ( is_array( $option ) && ! empty( $option['updated'] ) ) ? (int) $option['updated'] : 0;

		if ( ! $updated ) {
			printf(
				'<p class="wpcpm-agreement-panel__read">%s</p>',
				esc_html__( 'The agreement state has not been read from the program\'s records yet.', 'wpcredits-program-manager' )
			);

			return;
		}

		printf(
			'<p class="wpcpm-agreement-panel__read">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: date and time, 2: human-readable time difference. */
					__( 'Agreement state: read %1$s (%2$s ago).', 'wpcredits-program-manager' ),
					wp_date( 'Y-m-d H:i', $updated ),
					human_time_diff( $updated, time() )
				)
			)
		);
	}

	/**
	 * The newest agreement post for an institution in a given state.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $state     A `STATE_*` value.
	 * @return WP_Post|null
	 */
	private static function latest_post( $record_id, $state ) {
		foreach ( WPCPM_Institution_Agreement::posts_for( $record_id ) as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			if ( (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_STATE, true ) === (string) $state ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * A post's date as Y-m-d, or an empty string.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function post_date( $post_id ) {
		$post = get_post( (int) $post_id );

		return $post instanceof WP_Post ? substr( (string) $post->post_date, 0, 10 ) : '';
	}

	/**
	 * The template version recorded on a generated document.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function template_version( $post_id ) {
		$version = trim( (string) get_post_meta( (int) $post_id, WPCPM_Institution_Agreement::META_TEMPLATE_VERSION, true ) );

		return '' !== $version ? $version : __( 'unrecorded', 'wpcredits-program-manager' );
	}
	/**
	 * What the last bulk recording did, in one sentence, for its outcome message.
	 *
	 * @return string
	 */
	public static function on_file_all_summary() {
		$tally = WPCPM_Institution_Agreement::last_on_file_all();

		if ( ! is_array( $tally ) ) {
			return __( 'The agreements were recorded.', 'wpcredits-program-manager' );
		}

		$parts = array(
			sprintf(
				/* translators: %s: number of institutions. */
				_n( '%s institution recorded as signed and its account opened.', '%s institutions recorded as signed and their accounts opened.', (int) $tally['recorded'], 'wpcredits-program-manager' ),
				number_format_i18n( (int) $tally['recorded'] )
			),
		);

		if ( ! empty( $tally['failed'] ) ) {
			$parts[] = sprintf(
				/* translators: 1: number of institutions, 2: their record IDs. */
				_n( '%1$s could not be recorded and is left for the single form: %2$s.', '%1$s could not be recorded and are left for the single form: %2$s.', count( $tally['failed'] ), 'wpcredits-program-manager' ),
				number_format_i18n( count( $tally['failed'] ) ),
				implode( ', ', array_keys( $tally['failed'] ) )
			);
		}

		if ( ! empty( $tally['left'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: number of institutions. */
				_n( 'The run stopped after its time budget with %s still to do: press it again.', 'The run stopped after its time budget with %s still to do: press it again.', (int) $tally['left'], 'wpcredits-program-manager' ),
				number_format_i18n( (int) $tally['left'] )
			);
		}

		return implode( ' ', $parts );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- The panel and the card are one decision in two halves: an institution has exactly one of them, and which one is the same question `is_settled()` answers. Splitting them across two files would put the two branches of a single gate where a reader has to know both filenames to find the second.

/**
 * The card at the foot of a settled dashboard.
 *
 * The counterpart of the panel, and the reason the two ship in one file: an institution has
 * exactly one of them, and which one is the same question `is_settled()` answers for the
 * roster above it. The card says when the agreement was accepted and which route recorded it,
 * because those are the two things a member asks when the account opens ("is this the copy we
 * signed?") and the two a manager needs when a row is queried later.
 *
 * Downloading the accepted copy and replacing it with a newer signed one are Phase 3, with
 * the private file store that holds the bytes; the card says so rather than offering a control
 * that would 404.
 */
class WPCPM_Institution_Agreement_Card {

	/**
	 * Draw the agreement card for a settled institution.
	 *
	 * Prints nothing for an unsettled one: that dashboard has a panel instead, and a card
	 * saying "accepted" beside a panel saying "not started" is how a gate stops being
	 * believed. Prints nothing for a viewer the fence refuses either.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $context   The dashboard's context, in the shape the shell hands every card.
	 *                          Deliberately unread: the one thing here that depends on who is
	 *                          looking, the Drive link, is decided on the policy's ground and
	 *                          not on a flag the caller passed.
	 */
	public static function render( $record_id, array $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- The shape every card shares; see the docblock.
		$record_id = trim( (string) $record_id );

		if ( ! WPCPM_Mentors_Sync::is_record_id( $record_id ) ) {
			return;
		}

		// Design spec 5.4 again, and with ACT_VIEW_ROSTER rather than the panel's
		// ACT_AGREEMENT. This card is the foot of the settled dashboard, drawn under the
		// roster and the People card, and it names the date the account opened and, for a
		// manager, the program's Drive folder. So it asks what the cards beside it ask: a
		// viewer who may not see this institution's students may not see which document
		// opened its account either. The panel's ungated action would be wrong here for the
		// same reason it is right there.
		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_VIEW_ROSTER,
			WPCPM_Institution_Policy::subject_institution( $record_id )
		);

		if ( empty( $decision['allowed'] ) ) {
			// Nothing at all, not an empty box: the card is a footnote to a page the shell
			// has already refused, and a heading with no agreement under it would be a
			// second answer to the same question.
			return;
		}

		if ( ! WPCPM_Institution_Agreement::is_settled( $record_id ) ) {
			return;
		}

		// The Drive folder is the one thing here a member may not see, so it is shown on the
		// ground the decision above was allowed on and never on `$context['can_manage']`: the
		// context is whatever the caller passed, the ground is what the policy found, and a
		// stale flag handed down through the shell must not put the program's folder in front
		// of a school.
		$as_manager = isset( $decision['ground'] ) && WPCPM_Institution_Policy::GROUND_MANAGER === $decision['ground'];
		$summary    = WPCPM_Institution_Agreement::summary( $record_id );
		$accepted   = isset( $summary['accepted_at'] ) ? (string) $summary['accepted_at'] : '';

		echo '<section class="wpcpm-agreement-card">';
		printf(
			'<h2 class="wpcpm-agreement-card__title">%s</h2>',
			esc_html__( 'Collaboration Agreement', 'wpcredits-program-manager' )
		);

		WPCPM_Institution_Panel::render_flash();

		printf(
			'<p class="wpcpm-agreement-card__accepted">%s</p>',
			esc_html(
				'' !== $accepted
					? sprintf(
						/* translators: %s: date the agreement was accepted. */
						__( 'Accepted on %s.', 'wpcredits-program-manager' ),
						$accepted
					)
					: __( 'Accepted. The date was not recorded.', 'wpcredits-program-manager' )
			)
		);

		self::render_route( $summary );

		printf(
			'<p class="wpcpm-agreement-card__soon">%s</p>',
			esc_html__( 'Downloading the accepted copy, and replacing it by uploading a newer signed one, arrive in the next release of this site. The current agreement stays in force.', 'wpcredits-program-manager' )
		);

		if ( $as_manager ) {
			self::render_drive_link( $record_id );
		}

		echo '</section>';
	}

	/**
	 * Which route recorded the accepted agreement.
	 *
	 * `grid` is a manager typing `On file` and a Drive link into the base and pressing
	 * Refresh; `site` is anything a person did here, the on-file form included. Named on the
	 * card because "we never signed anything on this website" is a reasonable thing for a
	 * long-standing partner to think, and the answer is that the program recorded the copy
	 * it already had.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 */
	private static function render_route( array $summary ) {
		$route  = isset( $summary['route'] ) ? (string) $summary['route'] : '';
		$legacy = isset( $summary['kind'] ) && WPCPM_Institution_Agreement::KIND_LEGACY === (string) $summary['kind'];

		if ( 'grid' === $route ) {
			$line = $legacy
				? __( 'Recorded from the program\'s own records: the signed copy is in the program\'s Drive folder, and nothing was signed through this site.', 'wpcredits-program-manager' )
				: __( 'Recorded from the program\'s own records rather than through this site.', 'wpcredits-program-manager' );
		} elseif ( $legacy ) {
			$line = __( 'Recorded on this site by a program manager, from the copy the program holds in its Drive folder.', 'wpcredits-program-manager' );
		} else {
			$line = __( 'Signed and accepted through this site.', 'wpcredits-program-manager' );
		}

		printf( '<p class="wpcpm-agreement-card__route">%s</p>', esc_html( $line ) );
	}

	/**
	 * The Drive link, for a program manager only.
	 *
	 * The folder is the program's, not the institution's, and a member following it would
	 * meet a Google sign-in wall naming a Drive they have no business in. A manager reading
	 * the same card wants the document in one click.
	 *
	 * @param string $record_id Institutions record ID.
	 */
	private static function render_drive_link( $record_id ) {
		$option = WPCPM_Institution_Agreement::option( $record_id );
		$url    = ( is_array( $option ) && isset( $option['drive_url'] ) ) ? (string) $option['drive_url'] : '';

		if ( ! WPCPM_Institution_Agreement::is_drive_link( $url ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-agreement-card__drive"><a href="%1$s" rel="noopener noreferrer" target="_blank">%2$s</a></p>',
			esc_url( $url ),
			esc_html__( 'Open the signed copy in Drive', 'wpcredits-program-manager' )
		);
	}
}

// phpcs:enable Generic.Files.OneObjectStructurePerFile.MultipleFound
