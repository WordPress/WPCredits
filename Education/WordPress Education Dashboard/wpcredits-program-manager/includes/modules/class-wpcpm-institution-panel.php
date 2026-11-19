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
 * needs the assigned Administrator's address, and `submitted` needs the reader to do nothing at all.
 * A single "upload your agreement" panel would be wrong in four of the five cases.
 *
 * **What Phase 3 added.** The controls the spec's table names are real here now: Generate and
 * Upload on `none` and `generated`, Download and Withdraw on `submitted`, Upload again on
 * `returned` and `revoked`, and the manager's review block for the queue. The sentence that
 * saves a long-standing partner from signing twice stays where it was, because the on-file
 * route is still the faster answer for the forty-odd institutions that signed years ago.
 *
 * **Every form here is drawn by whichever screen the reader is on, and gated by the same
 * fence.** The upload form appears on this panel, on the card at the foot of a settled
 * dashboard (a replacement, T10) and on an institution's Manage members view in wp-admin, and
 * it is one method that asks `WPCPM_Institution_Policy::decide()` itself rather than three
 * copies trusting three callers. The handlers on the other side ask again; a form nobody may
 * post is not a gate, it is a courtesy that saves the reader a refusal.
 */
class WPCPM_Institution_Panel {

	/** The panel's element ID, so a link or a stylesheet can find it. */
	const ANCHOR = 'wpcpm-agreement';

	/**
	 * The longest name the generate form offers to print, in characters.
	 *
	 * The same cap the handler applies, applied here as well so a pre-filled box never
	 * carries more than the server will keep: a reader who types a correction after the
	 * two-hundredth character and is silently cut off has been lied to by the form.
	 */
	const MAX_NAME = 200;

	/**
	 * The decisions `render_accept_form()` and `render_return_form()` hand on while `render_decide_area()` is
	 * collecting them, or null when nothing is.
	 *
	 * @var array[]|null
	 */
	private static $decide_into = null;

	/**
	 * The outcome the view's block is drawing on the shared parts, so the form that holds the box a refusal names can
	 * describe that box with the sentence (Institution Dashboard, Task 21); '' outside `parts_block()`.
	 *
	 * @var string
	 */
	private static $said = '';

	/**
	 * What the refused forms kept for their one redraw (`WPCPM_Flash::kept()`), read by `parts_block()` for the forms it
	 * draws on the parts; empty outside it (Task 21, fix round 1).
	 *
	 * @var array<string, array>
	 */
	private static $kept = array();

	/**
	 * Every outcome the agreement handlers can flash, in the words the reader gets.
	 *
	 * Kept here rather than in each screen, because the forms are drawn in four places (this
	 * panel, the card at the foot of a settled dashboard, an institution's Manage members view
	 * on the Institutions screen and the review queue) and land wherever they were pressed. Each row is
	 * `array( notice type, message )`, the shape every screen already uses.
	 *
	 * Every refusal opens by saying that nothing happened, and then says what to do next.
	 * The reader of these is somebody who has just pressed a button and cannot see the
	 * server: "Nothing was uploaded" first, the reason second, is the order that stops a
	 * person pressing again while wondering whether the first press half worked.
	 *
	 * `agreement-busy` is the one that says nothing happened at all, and it now has two
	 * producers rather than one. A transition takes the institution's own `add_option()`
	 * lock before it asks whether an accepted agreement already stands, so two managers
	 * pressing the same button in the same second cannot both find none and both insert one;
	 * and generating and uploading are each capped per institution per day, so a runaway
	 * script cannot fill the disk. Both are "come back in a moment", so both say so, and
	 * the sentence names the daily cap rather than leaving a member who has uploaded five
	 * files today to conclude the site is broken.
	 *
	 * @return array<string, array>
	 */
	public static function messages() {
		$max_mb = max( 1, (int) WPCPM_Settings::get_value( 'agreement_max_mb', 10 ) );

		// On file at a closed stage, in site mode (Institutions off Airtable, release 2): one outcome per stage, so
		// the sentence can name it.
		$closed = static function ( $stage ) {
			return array(
				'error',
				sprintf(
					/* translators: %s: the stage the talks with the institution are at, such as Revisit Later. */
					__( 'Nothing was recorded: the talks with this institution are at %s. Move it to a stage where talks go on, then record the agreement.', 'wpcredits-program-manager' ),
					$stage
				),
			);
		};

		return array(
			'agreement-on-file'            => array( 'success', __( 'The agreement is recorded as on file. The institution\'s account is open.', 'wpcredits-program-manager' ) ),
			'agreement-link'               => array( 'error', __( 'Nothing was recorded. Paste the Drive link to the folder or the file: it has to be an https address on drive.google.com or docs.google.com.', 'wpcredits-program-manager' ) ),
			'agreement-standing'           => array( 'error', __( 'Nothing was recorded. An accepted agreement already stands for this institution.', 'wpcredits-program-manager' ) ),
			// In site mode the on-file form says agreement-on-file-unlisted instead; what still says this is the Regenerate link for a
			// document that is gone (`WPCPM_Agreement_Generate`), so the site's words say that (1.123.102).
			'agreement-unknown'            => array(
				'error',
				class_exists( 'WPCPM_Institutions_Index' ) && WPCPM_Institutions_Index::on_site()
					? __( 'Nothing was generated. That document is no longer on the site; reload the page to see the agreement as it stands.', 'wpcredits-program-manager' )
					: __( 'Nothing was recorded. That institution is not in the pipeline index; run a sync and try again.', 'wpcredits-program-manager' ),
			),
			'agreement-busy'               => array( 'error', __( 'Nothing was saved. Either another write to this institution\'s agreement was in flight, from a sync or from somebody else pressing the same button, or this institution has used up the documents one day allows it to generate and upload. Try again in a moment, and tomorrow if it happens again.', 'wpcredits-program-manager' ) ),
			// Revoke and reinstate, T8 and T9. Named here with everything else the two agreement
			// forms can flash, because a slug with no row prints nothing at all: the button
			// redirects, the page looks untouched, and the manager presses it again.
			'agreement-revoked'            => array( 'success', __( 'The agreement is revoked. From their next page load the institution\'s representatives see only the Overview and the Agreement, its tutors see no page, and everybody there has been emailed your note.', 'wpcredits-program-manager' ) ),
			'agreement-revoke-note'        => array( 'error', __( 'Nothing was revoked. A note is required, between 20 and 2000 characters: it is emailed to the institution as the reason, so it has to say something.', 'wpcredits-program-manager' ) ),
			'agreement-revoke-loss'        => array( 'error', WPCPM_Typed_Text::loss_message( self::revoke_label(), 'revoke-agreement' ) ),
			'agreement-not-accepted'       => array( 'error', __( 'Nothing was revoked. There is no accepted agreement to revoke for this institution.', 'wpcredits-program-manager' ) ),
			'agreement-not-revoked'        => array( 'error', __( 'Nothing was reinstated. There is no revoked agreement to put back for this institution.', 'wpcredits-program-manager' ) ),
			'agreement-reinstate-standing' => array( 'error', __( 'Nothing was reinstated. An accepted agreement already stands, so there is nothing for the revoked one to come back to.', 'wpcredits-program-manager' ) ),
			// A school outside the active stages (1.123.102): read on wp-admin and on the Administrator Dashboard alone.
			'agreement-reinstate-left'     => array(
				'error',
				class_exists( 'WPCPM_Institutions_Index' ) && WPCPM_Institutions_Index::on_site()
					? __( 'Nothing was reinstated. The institution\'s stage is outside the pipeline. Move it to an active stage on its Talks card first: reinstated now, the next institutions sync would close it again.', 'wpcredits-program-manager' )
					: __( 'Nothing was reinstated. The institution\'s stage is outside the pipeline. Move it to an active stage in Airtable first: reinstated now, the next institutions sync would close it again.', 'wpcredits-program-manager' ),
			),
			// A school the site's list no longer holds (round 3): the same sentence in both modes.
			'agreement-reinstate-unlisted' => array( 'error', __( 'Nothing was reinstated. That institution is not in the site\'s list of institutions any more.', 'wpcredits-program-manager' ) ),
			'agreement-reinstated'         => array( 'success', __( 'The agreement is back in force and the account is open again. Everybody at the institution has been emailed.', 'wpcredits-program-manager' ) ),
			// Once the site keeps the record, the agreement columns are still written to Airtable first (design, section 9), and
			// the sync reads the site's list, so only a second press finishes a site record not written (1.123.102).
			'agreement-airtable'           => array(
				'error',
				class_exists( 'WPCPM_Institutions_Index' ) && WPCPM_Institutions_Index::on_site()
					? __( 'Airtable could not be updated, so nothing was recorded here either. Nothing changed for the institution. Try again in a moment.', 'wpcredits-program-manager' )
					: __( 'Airtable could not be updated, so nothing was recorded here either. The base is the record of this state, and the site does not open an account the base has not agreed to.', 'wpcredits-program-manager' ),
			),
			'agreement-not-saved'          => array(
				'error',
				class_exists( 'WPCPM_Institutions_Index' ) && WPCPM_Institutions_Index::on_site()
					? __( 'Airtable was updated but the record on this site could not be written. Press Record it again: a second press completes it.', 'wpcredits-program-manager' )
					: __( 'Airtable was updated but the record on this site could not be written. Run the institutions sync on the Institutions screen\'s Sync and storage tab: the next reconcile completes it.', 'wpcredits-program-manager' ),
			),
			'agreement-later'              => array( 'info', __( 'Airtable and the site record were both written, but this institution\'s state was being rebuilt at that moment. The account opens after the next institutions sync.', 'wpcredits-program-manager' ) ),
			'agreement-uploaded'           => array( 'success', __( 'The signed agreement is uploaded. An Administrator reviews it and you will get an email either way.', 'wpcredits-program-manager' ) ),
			'agreement-too-big'            => array(
				'error',
				sprintf(
					/* translators: %s: the largest upload the site accepts, in megabytes. */
					__( 'Nothing was uploaded. The file is empty or larger than the %s MB this site accepts. A signed agreement scanned as text is well under that; a scan of every page as a photograph is not.', 'wpcredits-program-manager' ),
					number_format_i18n( $max_mb )
				),
			),
			'agreement-not-pdf'            => array( 'error', __( 'Nothing was uploaded. That file is not a PDF. Save or export the signed agreement as a PDF and upload it again.', 'wpcredits-program-manager' ) ),
			'agreement-no-file'            => array( 'error', __( 'Nothing was uploaded. No file arrived with the form. Choose the signed PDF and press the button again; if you are on a slow connection, give it a moment before pressing.', 'wpcredits-program-manager' ) ),
			'agreement-file'               => array( 'error', __( 'Nothing was uploaded. The file passed every check and this site could not store it, which is this site\'s fault and not yours. Nothing was kept. Try again, and tell your program contact if it happens twice.', 'wpcredits-program-manager' ) ),
			'agreement-gone'               => array( 'error', __( 'Nothing happened. That document is no longer waiting for review: somebody else has accepted, returned or withdrawn it since this page was drawn. Reload the page to see where it got to.', 'wpcredits-program-manager' ) ),
			'agreement-encrypted'          => array( 'error', __( 'Nothing was uploaded. That PDF is password protected, so an Administrator could not open it. Save an unprotected copy and upload that instead.', 'wpcredits-program-manager' ) ),
			'agreement-launch'             => array( 'error', __( 'Nothing was uploaded. That PDF asks to run a program when it is opened, which a signed agreement has no reason to do. Print it to a new PDF and upload that instead.', 'wpcredits-program-manager' ) ),
			'agreement-in-review'          => array( 'error', __( 'Nothing was uploaded. A signed agreement from this institution is already waiting for review. Withdraw it first if it was the wrong file.', 'wpcredits-program-manager' ) ),
			'agreement-declare'            => array( 'error', __( 'Nothing was uploaded. Tick the box to say the document you are uploading is the signed one.', 'wpcredits-program-manager' ) ),
			'agreement-kind'               => array( 'error', __( 'Nothing was uploaded. Say whether this is the program\'s template or an agreement of your own: an Administrator reads the two differently.', 'wpcredits-program-manager' ) ),
			'agreement-accepted'           => array( 'success', __( 'The agreement is accepted. The institution\'s account is open and everybody at the institution has been emailed.', 'wpcredits-program-manager' ) ),
			'agreement-returned'           => array( 'success', __( 'The agreement is returned. Everybody at the institution has been emailed your note, with your address to reply to.', 'wpcredits-program-manager' ) ),
			'agreement-withdrawn'          => array( 'success', __( 'The signed agreement is withdrawn and its file is deleted. Upload another whenever you are ready.', 'wpcredits-program-manager' ) ),
			'agreement-note'               => array( 'error', __( 'Nothing was returned. The note has to be between 20 and 2000 characters: it is the whole of what the institution is told, so it has to say what to change.', 'wpcredits-program-manager' ) ),
			'agreement-return-loss'        => array( 'error', WPCPM_Typed_Text::loss_message( self::return_label(), 'return' ) ),
			// Site mode only (release 2), and only on wp-admin and the Administrator's review: a school never reads it.
			'agreement-unlisted'           => array( 'error', __( 'This institution is not on the site\'s list any more, so the agreement was not accepted. Nothing was changed.', 'wpcredits-program-manager' ) ),
			'agreement-on-file-unlisted'   => array( 'error', __( 'This institution is not on the site\'s list any more, so the agreement was not recorded. Nothing was changed.', 'wpcredits-program-manager' ) ),
			'agreement-on-file-revisit'    => $closed( 'Revisit Later' ),
			'agreement-on-file-not-moving' => $closed( 'Not Moving Forward' ),
			'agreement-on-file-spam'       => $closed( 'SPAM' ),
			// Once the site keeps the record (release 2), the stage is the site's own, moved on the school's Talks card.
			'agreement-stage'              => array(
				'error',
				class_exists( 'WPCPM_Institutions_Index' ) && WPCPM_Institutions_Index::on_site()
					? __( 'Nothing was accepted. This institution\'s stage says the program is not going ahead with it. Change the stage on its Talks card first: accepting is the program saying yes, and it must not say yes to a record it has said no to.', 'wpcredits-program-manager' )
					: __( 'Nothing was accepted. Airtable\'s Current Stage for this institution says the program is not going ahead with it. Change the stage in Airtable first: accepting is the program saying yes, and it must not say yes to a record it has said no to.', 'wpcredits-program-manager' ),
			),
			// Once the site keeps the record its own row is written already and only Airtable's copy failed, which the sync's
			// retry writes: for the school it is the plain success of a generated document (1.123.102).
			'agreement-generated-later'    => class_exists( 'WPCPM_Institutions_Index' ) && WPCPM_Institutions_Index::on_site()
				? array( 'success', __( 'The document was generated and downloaded.', 'wpcredits-program-manager' ) )
				: array( 'info', __( 'The document was generated and downloaded, but the program\'s own record could not be updated at that moment. The next sync writes it. Nothing is lost and there is nothing to do.', 'wpcredits-program-manager' ) ),
			'agreement-name'               => array( 'error', __( 'Nothing was generated. The agreement needs the institution\'s name exactly as it should print on a document somebody signs. If you are not sure what to put, ask the Administrator who has been in touch with your institution.', 'wpcredits-program-manager' ) ),
			'agreement-name-brackets'      => array( 'error', __( 'Nothing was generated. The name cannot contain square brackets. The agreement is filled in by replacing the bracketed words in the template, so a name with brackets of its own would print as an unfilled blank on a document somebody signs. Write it without them.', 'wpcredits-program-manager' ) ),
			'agreement-generate-in-review' => array( 'error', __( 'Nothing was generated. A signed agreement from this institution is already waiting for review. Withdraw it first if you want to start again from the template.', 'wpcredits-program-manager' ) ),
			'agreement-generate-standing'  => array( 'error', __( 'Nothing was generated. An agreement already stands for this institution, so printing the template again would change nothing. Upload a newer signed copy to replace it.', 'wpcredits-program-manager' ) ),
			'agreement-generate-not-saved' => array( 'error', __( 'Nothing was generated. This site could not record the document, so it was not printed either and nothing was written anywhere. That is this site\'s fault and not yours. Try again, and tell your program contact if it happens twice.', 'wpcredits-program-manager' ) ),
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
		// agreement, an Administrator's note verbatim, its assigned Administrator's address - and
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

		// On the shared parts the panel is the Agreement view's content (Institution Dashboard, Task 21): the view's head says
		// "Collaboration Agreement", so it prints no head and no fold; its block carries the anchor.
		self::parts_block(
			$record_id,
			static function () use ( $record_id, $summary, $row, $can_manage ) {
				// For an Administrator the banner's sentences open the block, set apart from the school's own words.
				if ( $can_manage ) {
					echo self::banner_parts( $record_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::note().
				}

				self::render_state_parts( $record_id, $summary, $row, $can_manage );

				if ( $can_manage ) {
					echo self::on_file_form_parts( $record_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts.
				}

				echo WPCPM_UI::intro( '', 'muted', '', self::read_html( $record_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in read_html().
			}
		);
	}

	/**
	 * The outcome the last agreement handler flashed, taken once: both branches read it here.
	 *
	 * @return string An outcome slug, or ''.
	 */
	private static function take_status() {
		return sanitize_key( (string) WPCPM_Flash::take( WPCPM_Institutions::FLASH ) );
	}

	/**
	 * Print one outcome in its words, or nothing for a slug with none.
	 *
	 * @param string $status An outcome slug.
	 * @param string $lead   On the shared parts, the id of the box a refusal names: the sentence is then a link to it.
	 */
	private static function print_flash( $status, $lead = '' ) {
		$messages = self::messages();

		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}

		// Toned, heard and focused as every card's outcome is (1.123.58, IDB-09): a refusal as an
		// alert, anything else as a status.
		echo WPCPM_Dashboard_Card::message( $messages[ $status ][0], $messages[ $status ][1], 'wpcpm-agreement-panel__message', $lead ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPCPM_Dashboard_Card::message() escapes what it prints.
	}

	/**
	 * Whether this viewer may be offered a control for this institution's agreement.
	 *
	 * The render-from-cache pattern of design spec 5.4, in the one place every form goes
	 * through. `ACT_AGREEMENT` because that is what the forms do, and because it is the one
	 * action the gate does not apply to: a member whose agreement is outstanding is exactly
	 * who they are drawn for.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return bool
	 */
	public static function may_act( $record_id ) {
		if ( ! WPCPM_Mentors_Sync::is_record_id( trim( (string) $record_id ) ) ) {
			return false;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_institution( trim( (string) $record_id ) )
		);

		return ! empty( $decision['allowed'] );
	}

	/**
	 * The same question about one document, asked on the institution the document names.
	 *
	 * Two callers print a control against a post ID rather than against a record: the
	 * download link and the withdraw form. Both are drawn from three screens, and the
	 * subject has to come from the post's own stamp, never from whatever record the calling
	 * screen happens to be showing.
	 *
	 * @param int $post_id Agreement post ID.
	 * @return bool
	 */
	private static function may_act_on_post( $post_id ) {
		return null !== self::post_decision( $post_id );
	}

	/**
	 * The same question, with the answer kept rather than reduced to yes or no.
	 *
	 * The withdraw form needs the ground as well as the verdict, because the same control
	 * says two different things depending on who is pressing it, and the ground is what the
	 * policy found rather than a flag a screen passed down. Asked once per control: a queue
	 * drawing forty rows must not ask the fence twice for one button.
	 *
	 * @param int $post_id Agreement post ID.
	 * @return array|null The decision, or null when there is nothing to draw.
	 */
	private static function post_decision( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post instanceof WP_Post || WPCPM_Institution_Agreement::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_post( $post, WPCPM_Institution_Agreement::META_INSTITUTION )
		);

		return empty( $decision['allowed'] ) ? null : $decision;
	}

	/**
	 * Open a form, with its nonce and its action.
	 *
	 * Every form on this page posts to `admin-post.php` with a nonce keyed to the thing it
	 * acts on, carries `data-wpcpm-once` for the submit guard, and says what it is doing
	 * while it is doing it. Written once because seven copies of four attributes is seven
	 * chances for one of them to lose the guard.
	 *
	 * The enctype is always printed rather than only on the multipart forms: a form that
	 * quietly lacks it posts the filename and no bytes, which is the failure that looks like
	 * a broken server rather than like a missing attribute.
	 *
	 * **`data-wpcpm-once` is an attribute, and the guard is a script.** What reads it is
	 * `assets/js/forms.js`, registered as `wpcpm-forms`. The institution dashboard enqueues
	 * it since 1.90.0, and in wp-admin the admin class loads it on every plugin screen since
	 * 1.122.1, the Institutions screen among them, so the attribute is live on every form
	 * drawn on either. The other side still stops a second press on its own wherever the
	 * script does not run, in a browser with scripts off or for a request made by hand: the
	 * daily ceiling, the institution's own lock, and "one document in review at a time" - and
	 * since 1.90.0 the upload handler holds the lock across its read and its insert, which is
	 * what turned two presses into two documents before. Written down rather than promised,
	 * because the guard runs only on a page that enqueues it, and a reader who believes the
	 * guard is running does not go looking for the enqueue that is missing.
	 *
	 * **The switcher travels on the action URL, for a manager only.** The generate route
	 * works out which institution it is acting for from the request alone, and a POST to
	 * `admin-post.php` carries the form's own fields and none of the query string of the
	 * screen the button was pressed on. So a manager acting on behalf posts
	 * `WPCPM_Institution_Roster::ARG_VIEW` from here, which is the argument
	 * `resolve_institution()` reads, or resolves to whichever institution is their fallback
	 * and meets a nonce keyed to a different record. A member never gets it: their own
	 * membership is what places them, `resolve_institution()` does not read the argument for
	 * anybody without `CAP_MANAGE`, and a URL carrying a record for them would be a hidden
	 * field dressed up as a fence.
	 *
	 * **One mechanism per form, and never two.** Phase 3 shipped both: this argument, and a
	 * posted `wpcpm_agreement_record` that `WPCPM_Agreement_Generate` read and no generate
	 * form ever sent. The resolver won, and the read is gone from that class rather than left
	 * dormant, so the generate and Regenerate forms carry the switcher here and nothing else.
	 * The upload and on-file forms are the other way round and stay that way: their handlers
	 * read the posted record themselves, so those forms post the field and are *not* given
	 * this argument. Two transports exist in the module because two handlers read two things;
	 * what must not exist is one form feeding both, which is a second answer waiting to
	 * disagree with the first.
	 *
	 * @param string $css          Class attribute for the form.
	 * @param string $action       The `admin_post_` action name.
	 * @param string $nonce_action The nonce action, keyed to the institution or the post.
	 * @param string $busy         What the pressed control says while the request is in flight.
	 * @param bool   $multipart    Whether the form carries a file.
	 * @param string $record       The institution a manager's post has to resolve to, for the
	 *                             forms whose handler reads no record of its own. An empty
	 *                             string for the forms keyed to a document, which resolve
	 *                             from the post they name.
	 */
	private static function form_start( $css, $action, $nonce_action, $busy, $multipart, $record = '' ) {
		$url = self::form_url( $record );

		printf(
			'<form class="%1$s" method="post" action="%2$s" enctype="%3$s" data-wpcpm-once data-wpcpm-busy="%4$s">',
			esc_attr( $css ),
			esc_url( $url ),
			esc_attr( $multipart ? 'multipart/form-data' : 'application/x-www-form-urlencoded' ),
			esc_attr( $busy )
		);

		wp_nonce_field( $nonce_action );

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
	}

	/**
	 * The reviewer's block for one document waiting in the queue.
	 *
	 * Capability first, then the fence, in that order: `handle_accept()` and
	 * `handle_return()` are program managers' routes, and a member who reached this method
	 * gets nothing rather than a refusal naming another institution's document.
	 *
	 * **The checklist is read, not ticked.** Three sentences per kind, printed above the
	 * button, because a reviewer who has to tick three boxes learns to tick three boxes.
	 * What the program wants is that somebody looked, and the way to get that is to say what
	 * to look at next to the thing that opens an account.
	 *
	 * **The scan is a courtesy and is presented as one.** The flags line names what the PDF
	 * scan noticed and then says, in the same breath, that it is not evidence: a token can be
	 * hidden in ways a bounded scan will not find. What protects the reviewer is that this
	 * site never shows the file in a browser and hands it over as an attachment instead.
	 *
	 * **Two pages draw it, and one of them decides.** A decision keeps one home, the
	 * Administrator Dashboard, which passes `true` for `$decide` and gets the two, Accept and
	 * Return, each brought back to it by `$where`. The Institutions screen's queue passes `false`
	 * and gets the same block to read, down to the download link, with one line in the decisions'
	 * place: the way to this block on the Administrator Dashboard, by the `wpcpm-review-<post id>`
	 * anchor both pages print. While that page is missing the line is left out and the caller says
	 * so: the queue prints the dashboard class's sentence once above its list, where one sentence
	 * per waiting document would repeat it.
	 *
	 * @param int    $post_id Agreement post ID, a document in `submitted`.
	 * @param bool   $decide  True on the Administrator Dashboard, where the two decisions are drawn;
	 *                        false on the Institutions screen, where the block is read.
	 * @param string $where   Where a decision comes back to, as `WPCPM_Return::field()` takes it:
	 *                        WPCPM_Return::DASHBOARD from the Administrator Dashboard, so it lands on
	 *                        that page's Collaboration Agreements card, open; '' for none, and the
	 *                        handler returns where the press came from. Read only while `$decide` is
	 *                        true: a block drawn to be read holds no form.
	 */
	public static function render_review( $post_id, $decide = true, $where = '' ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) || ! $post instanceof WP_Post ) {
			return;
		}

		if ( WPCPM_Institution_Agreement::POST_TYPE !== $post->post_type ) {
			return;
		}

		// Only a document that is actually waiting: Accept and Return are transitions out of
		// `submitted` and out of nothing else, and a block offering them over a withdrawn or
		// an accepted post would be two buttons that refuse.
		if ( WPCPM_Institution_Agreement::STATE_SUBMITTED !== (string) get_post_meta( $post_id, WPCPM_Institution_Agreement::META_STATE, true ) ) {
			return;
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_post( $post, WPCPM_Institution_Agreement::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			return;
		}

		// One read of one source. `review_facts()` has already been to the index and to the
		// membership store for this document, and a queue drawing forty rows should not ask
		// either of them twice per row. `institution_name` is Airtable's `Name` as the last
		// sync read it, which is exactly what the checklist compares against.
		$facts = (array) WPCPM_Institution_Agreement::review_facts( $post_id );

		if ( ! $facts ) {
			return;
		}

		$name    = trim( (string) ( isset( $facts['institution_name'] ) ? $facts['institution_name'] : '' ) );
		$name    = '' !== $name ? $name : trim( (string) ( isset( $facts['institution'] ) ? $facts['institution'] : '' ) );
		$members = (int) ( isset( $facts['members'] ) ? $facts['members'] : 0 );

		// The block that decides, drawn by the Administrator Dashboard alone, on the shared parts (design 2.6, 2.10,
		// 4.1): the heading as the parts' h5 the Decide heading is too (under the item's h4), the facts, then one
		// Decide area. The one caller that draws it to be read, the Institutions screen's queue, is on a tab that
		// is off for this whole release, so a read block keeps its own markup below.
		if ( $decide ) {
			$heading = WPCPM_UI::heading(
				sprintf(
					/* translators: %s: institution name. */
					__( 'Review the signed agreement from %s', 'wpcredits-program-manager' ),
					$name
				),
				'h5'
			);

			// The section keeps its anchor, which the dashboard's links point at, and no class of the old block: its
			// sentences are the parts' intro lines and its checklist the checklist part (Tasks 17b and 18a).
			// It is a block of its item, 16px under the item's facts line (task V1, ruling 12).
			printf( '<section class="wpcpm-ui-item__part" id="wpcpm-review-%d">', (int) $post_id );
			echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::heading().
			self::render_review_facts( $facts, true );
			self::render_checklist( $facts, $name, true );
			self::render_flags( isset( $facts['flags'] ) ? (array) $facts['flags'] : array(), true );
			self::render_download_link( $post_id, __( 'Download the signed agreement', 'wpcredits-program-manager' ), true );
			self::render_decide_area( $post_id, $name, $members, $where );
			echo '</section>';

			return;
		}

		printf( '<section class="wpcpm-review" id="wpcpm-review-%d">', (int) $post_id );

		printf(
			'<h3 class="wpcpm-review__title">%s</h3>',
			esc_html(
				sprintf(
					/* translators: %s: institution name. */
					__( 'Review the signed agreement from %s', 'wpcredits-program-manager' ),
					$name
				)
			)
		);

		self::render_review_facts( $facts );
		self::render_checklist( $facts, $name );
		self::render_flags( isset( $facts['flags'] ) ? (array) $facts['flags'] : array() );
		self::render_download_link( $post_id, __( 'Download the signed agreement', 'wpcredits-program-manager' ) );
		self::render_decided_elsewhere( $post_id );

		echo '</section>';
	}

	/**
	 * The two decisions on one document as one Decide area (`WPCPM_UI::decide()`, design 2.6), drawn on the
	 * Administrator Dashboard: Accept it, the one primary, acting at once with the question
	 * it asks today; Return with a note, a secondary opener whose panel holds the note and keeps "Return it with
	 * this note". Nothing is red: returning removes nothing.
	 *
	 * The decisions are the ones the two forms draw, posting the same fields: each form hands its decision here
	 * instead of printing itself. The area's id and the box's id carry the document's ID, so two agreements on
	 * the card repeat none. The one panel is printed open when the last press left its typing or a refusal for
	 * this document's box; with one panel there is no other for a refusal to outrank.
	 *
	 * @param int    $post_id Agreement post ID.
	 * @param string $name    Institution name, as the confirm prints it.
	 * @param int    $members How many people Accept emails.
	 * @param string $where   Where a decision comes back to.
	 */
	private static function render_decide_area( $post_id, $name, $members, $where ) {
		self::$decide_into = array();

		try {
			self::render_accept_form( $post_id, $name, $members, $where );
			self::render_return_form( $post_id, $where );
			$decisions = self::$decide_into;
		} finally {
			self::$decide_into = null;
		}

		$area = WPCPM_UI::decide(
			array(
				'id'        => 'wpcpm-decide-agr-' . (int) $post_id,
				'level'     => 'h5',
				// Drawn on the Administrator Dashboard alone, which is never wp-admin.
				'side'      => 'front',
				'decisions' => $decisions,
			)
		);

		echo $area; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::decide(); the hidden inputs in decision()'s callable.
	}

	/**
	 * One decision as `WPCPM_UI::decide()` takes it, from what the form it stands for posts: the same address,
	 * encoding and guard as `form_start()` prints, and the same hidden fields under the same nonce, its field
	 * printed with no id (`WPCPM_UI::nonce()`), since the card holds one per document.
	 *
	 * @param int    $post_id The document.
	 * @param string $action  The `admin_post_` action, whose nonce is keyed to the document.
	 * @param string $busy    What the pressed control says while the request is in flight.
	 * @param string $where   Where the decision comes back to.
	 * @param array  $args    The rest of the decision: key, label, kind, fields, confirm_label, confirm, open.
	 * @return array
	 */
	private static function decision( $post_id, $action, $busy, $where, array $args ) {
		return array_merge(
			$args,
			array(
				'form_attrs' => array(
					'method'          => 'post',
					'action'          => admin_url( 'admin-post.php' ),
					'enctype'         => 'application/x-www-form-urlencoded',
					'data-wpcpm-once' => true,
					'data-wpcpm-busy' => $busy,
				),
				'hidden'     => static function () use ( $post_id, $action, $where ) {
					echo WPCPM_UI::nonce( $action . '_' . (int) $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::nonce().
					printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
					printf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id );

					if ( class_exists( 'WPCPM_Return' ) ) {
						WPCPM_Return::field( (string) $where, 'agreements' );
					}
				},
			)
		);
	}

	/**
	 * The sentence a refused return note says beside its box, or '' when this document's box was not refused.
	 *
	 * The press left its outcome on the Institutions screen's channel, which the dashboard's message reads too
	 * (`WPCPM_Flash::take()` memoizes, so the sentence is the one it prints), and the handler kept the typing
	 * for the document the press was made for (`WPCPM_Institution_Agreement::handle_return()`). Another
	 * document's box may hold typing an earlier press kept, which no page drew: the refusal is this box's only when
	 * this document's form is the last one kept (`WPCPM_Typed_Text::is_last()`). A refused note that kept nothing,
	 * one of white space only, names no document, so no box is said to be refused.
	 *
	 * @param string|null $kept    What the refusal kept for this document's box, or null.
	 * @param int         $post_id The document.
	 * @return string
	 */
	private static function return_problem( $kept, $post_id ) {
		if ( ! is_string( $kept ) ) {
			return '';
		}

		$status   = sanitize_key( (string) WPCPM_Flash::take( WPCPM_Institutions::FLASH ) );
		$messages = self::messages();

		if ( ! in_array( $status, array( 'agreement-note', 'agreement-return-loss' ), true ) || ! isset( $messages[ $status ][1] ) ) {
			return '';
		}

		if ( ! WPCPM_Typed_Text::is_last( WPCPM_Typed_Text::box_form( WPCPM_Institution_Agreement::TYPED_RETURN, $post_id ) ) ) {
			return '';
		}

		return (string) $messages[ $status ][1];
	}

	/**
	 * The line a block drawn to be read ends with, in the place of the two decisions: the way to the
	 * same block on the Administrator Dashboard, which makes them, printed by the one printer every
	 * screen and block that links there shares (`WPCPM_Return::render_dashboard_link()`).
	 *
	 * Nothing while the dashboard's page is missing: there is nowhere to link, and the caller says
	 * so once for its whole list rather than once in every block.
	 *
	 * @param int $post_id Agreement post ID.
	 */
	private static function render_decided_elsewhere( $post_id ) {
		WPCPM_Return::render_dashboard_link( 'agreements', 'wpcpm-review-' . (int) $post_id, 'wpcpm-review__open' );
	}

	/**
	 * Who uploaded the document, when, how big it is and which kind it claims to be.
	 *
	 * The size is here because it is the one fact a reviewer reads before opening anything:
	 * a two-page agreement that weighs nine megabytes is a photograph of every page, which
	 * is a slower read and a different conversation from a signed text PDF.
	 *
	 * The date is the site's own format (`WPCPM_Dates::day()`), as every other date on this panel
	 * is. The facts hold the stored day, `Y-m-d`, which a sentence a person reads never prints.
	 *
	 * @param array $facts From `WPCPM_Institution_Agreement::review_facts()`.
	 * @param bool  $parts True on the Administrator Dashboard's parts: each sentence the parts' intro line.
	 */
	private static function render_review_facts( array $facts, $parts = false ) {
		$kind     = isset( $facts['kind'] ) ? (string) $facts['kind'] : '';
		$uploaded = sprintf(
			/* translators: 1: the uploader's name, 2: date, 3: file size. */
			__( 'Uploaded by %1$s on %2$s, %3$s.', 'wpcredits-program-manager' ),
			trim( (string) ( isset( $facts['uploaded_by'] ) ? $facts['uploaded_by'] : '' ) ),
			WPCPM_Dates::day( trim( (string) ( isset( $facts['uploaded_at'] ) ? $facts['uploaded_at'] : '' ) ) ),
			size_format( (int) ( isset( $facts['size'] ) ? $facts['size'] : 0 ) )
		);
		$said = WPCPM_Institution_Agreement::KIND_OWN === $kind
			? __( 'The institution says this is an agreement of its own, not the program\'s template.', 'wpcredits-program-manager' )
			: __( 'The institution says this is the program\'s template, signed.', 'wpcredits-program-manager' );

		if ( $parts ) {
			echo WPCPM_UI::intro( $uploaded ) . WPCPM_UI::intro( $said ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().

			return;
		}

		printf( '<p class="wpcpm-review__facts">%s</p>', esc_html( $uploaded ) );
		printf( '<p class="wpcpm-review__kind">%s</p>', esc_html( $said ) );
	}

	/**
	 * The three things to look at, chosen by which kind the uploader declared.
	 *
	 * The template kind is a comparison and can be stated exactly: the footer carries the
	 * version, the document carries a name, and Airtable carries a name. The own kind cannot
	 * be reduced to a comparison at all, which is precisely why it is slower, and saying so
	 * is more use to a reviewer than three checks that would not catch anything.
	 *
	 * @param array  $facts         From `WPCPM_Institution_Agreement::review_facts()`.
	 * @param string $airtable_name Airtable's `Name` for the institution, as the index read it.
	 * @param bool   $parts         True on the Administrator Dashboard's parts: the sentences as `WPCPM_UI::checklist()`.
	 */
	private static function render_checklist( array $facts, $airtable_name, $parts = false ) {
		$own      = WPCPM_Institution_Agreement::KIND_OWN === (string) ( isset( $facts['kind'] ) ? $facts['kind'] : '' );
		$version  = trim( (string) ( isset( $facts['template_version'] ) ? $facts['template_version'] : '' ) );
		$on_paper = trim( (string) ( isset( $facts['name_on_document'] ) ? $facts['name_on_document'] : '' ) );

		if ( $own ) {
			$lines = array(
				__( 'Read the whole document. There is no shortcut for this one.', 'wpcredits-program-manager' ),
				__( 'It names the WordPress Foundation and the institution.', 'wpcredits-program-manager' ),
				__( 'It does not commit the program to anything the program\'s own template does not.', 'wpcredits-program-manager' ),
			);
		} else {
			$lines = array(
				sprintf(
					/* translators: %s: template version, or a phrase saying none was recorded. */
					__( 'The footer on the signed copy names template version %s.', 'wpcredits-program-manager' ),
					'' !== $version ? $version : __( 'nothing this site recorded', 'wpcredits-program-manager' )
				),
				sprintf(
					class_exists( 'WPCPM_Institutions_Index' ) && WPCPM_Institutions_Index::on_site()
						/* translators: 1: the name printed on the document, 2: the site's name for the institution. */
						? __( 'The name on the document is %1$s. The site\'s name for this institution is %2$s.', 'wpcredits-program-manager' )
						/* translators: 1: the name printed on the document, 2: Airtable's Name for the institution. */
						: __( 'The name on the document is %1$s. Airtable\'s Name for this institution is %2$s.', 'wpcredits-program-manager' ),
					'' !== $on_paper ? $on_paper : __( '(not recorded)', 'wpcredits-program-manager' ),
					$airtable_name
				),
				__( 'The institution\'s signature block is filled in.', 'wpcredits-program-manager' ),
			);
		}

		// On the Administrator Dashboard's parts the helper prints the list (Task 18a); today's list everywhere else.
		if ( $parts ) {
			echo WPCPM_UI::checklist( $lines ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::checklist().

			return;
		}

		echo '<ul class="wpcpm-review__checklist">';

		foreach ( $lines as $line ) {
			printf( '<li>%s</li>', esc_html( $line ) );
		}

		echo '</ul>';
	}

	/**
	 * What the courtesy scan noticed, and what it is worth.
	 *
	 * @param array $flags The names the scan recorded, `/JavaScript` and its kind.
	 * @param bool  $parts True on the Administrator Dashboard's parts: each sentence the parts' intro line.
	 */
	private static function render_flags( array $flags, $parts = false ) {
		$flags   = array_values( array_filter( array_map( 'strval', $flags ) ) );
		$noticed = $flags
			? sprintf(
				/* translators: %s: what the courtesy scan noticed, in words, each with the PDF's own name for it in brackets. */
				__( 'The scan noticed these in the file: %s.', 'wpcredits-program-manager' ),
				WPCPM_Pdf_Check::in_words( $flags )
			)
			: __( 'The scan noticed none of the features it looks for in the file.', 'wpcredits-program-manager' );
		$courtesy = __( 'The scan is a courtesy and not evidence: a PDF can carry things a bounded scan will not find. What protects you is that this site never opens the file in your browser, and that the download is handed to a viewer of your own choosing.', 'wpcredits-program-manager' );

		if ( $parts ) {
			echo WPCPM_UI::intro( $noticed ) . WPCPM_UI::intro( $courtesy ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in WPCPM_UI::intro().

			return;
		}

		printf( '<p class="wpcpm-review__flags">%s</p>', esc_html( $noticed ) );
		printf( '<p class="wpcpm-review__courtesy">%s</p>', esc_html( $courtesy ) );
	}

	/**
	 * Accept, handed to the Decide area `render_decide_area()` is collecting, with a confirm that names
	 * everything that leaves the building.
	 *
	 * The institution, `Confirmed`, and how many people are emailed: three consequences the
	 * reviewer cannot see from the button, one of which reaches other people's inboxes. The
	 * last sentence names where it is revoked, the Institutions screen's Agreements tab, because
	 * a dialog listing three irreversible-sounding things is a dialog people dismiss without
	 * reading.
	 *
	 * Called only while `render_decide_area()` is collecting: outside it there is no area to hand the decision to,
	 * so it hands nothing on and returns, rather than starting a list nobody prints.
	 *
	 * @param int    $post_id Agreement post ID.
	 * @param string $name    Institution name, as printed.
	 * @param int    $members How many people are emailed.
	 * @param string $where   Where the decision comes back to, as `render_review()` was told.
	 */
	private static function render_accept_form( $post_id, $name, $members, $where = '' ) {
		if ( ! is_array( self::$decide_into ) ) {
			return;
		}

		$question = sprintf(
			/* translators: 1: institution name, 2: number of people emailed. */
			_n(
				'Accept the signed agreement from %1$s? This opens their account on the site, sets Current Stage to Confirmed in Airtable, and emails the %2$s person at the institution. You can revoke it later on the Institutions screen, in the Agreements tab.',
				'Accept the signed agreement from %1$s? This opens their account on the site, sets Current Stage to Confirmed in Airtable, and emails the %2$s people at the institution. You can revoke it later on the Institutions screen, in the Agreements tab.',
				(int) $members,
				'wpcredits-program-manager'
			),
			$name,
			number_format_i18n( (int) $members )
		);

		// No note, so the decision acts at once from the row, with the question.
		self::$decide_into[] = self::decision(
			$post_id,
			WPCPM_Institution_Agreement::ACTION_ACCEPT,
			__( 'Accepting...', 'wpcredits-program-manager' ),
			$where,
			array(
				'key'     => 'accept',
				'label'   => __( 'Accept it', 'wpcredits-program-manager' ),
				'kind'    => 'primary',
				'confirm' => $question,
			)
		);
	}

	/**
	 * Return, handed to the Decide area `render_decide_area()` is collecting, with the note that is the
	 * whole of what the institution is told.
	 *
	 * The one free-text field anywhere on this page, and the reason it is allowed where the
	 * upload form's is not: this text is written to be read by a person, is mailed verbatim,
	 * and is the difference between "your agreement was returned" and an institution that
	 * knows page 4 is unsigned.
	 *
	 * A return refused for its own words comes back with the note as it was typed, in this
	 * document's box only (`WPCPM_Institution_Agreement::kept_return_note()`).
	 *
	 * Called only while `render_decide_area()` is collecting: outside it there is no area to hand the decision to,
	 * so it hands nothing on, leads no message to a box, and returns.
	 *
	 * @param int    $post_id Agreement post ID.
	 * @param string $where   Where the decision comes back to, as `render_review()` was told.
	 */
	private static function render_return_form( $post_id, $where = '' ) {
		if ( ! is_array( self::$decide_into ) ) {
			return;
		}

		$base = 'wpcpm-return-' . (int) $post_id;
		$kept = WPCPM_Institution_Agreement::kept_return_note( $post_id );

		// An opener, and the note in its panel, the label split into label and hint (design 5). Opened on load where
		// the box holds what the last press kept, or says why it was refused; a refusal leads the card's message to
		// the box.
		$problem = self::return_problem( $kept, $post_id );

		if ( '' !== $problem && class_exists( 'WPCPM_Dashboard_Card' ) ) {
			WPCPM_Dashboard_Card::lead_to( $base );
		}

		self::$decide_into[] = self::decision(
			$post_id,
			WPCPM_Institution_Agreement::ACTION_RETURN,
			__( 'Returning...', 'wpcredits-program-manager' ),
			$where,
			array(
				'key'           => 'return',
				'label'         => __( 'Return with a note', 'wpcredits-program-manager' ),
				'kind'          => 'secondary',
				'confirm_label' => __( 'Return it with this note', 'wpcredits-program-manager' ),
				'fields'        => WPCPM_UI::field(
					array(
						'id'      => $base,
						'name'    => 'wpcpm_agreement_note',
						'label'   => __( 'What has to change', 'wpcredits-program-manager' ),
						'hint'    => __( 'In your own words. It is emailed to everybody at the institution exactly as you write it, with your address to reply to.', 'wpcredits-program-manager' ),
						'need'    => 'required',
						'type'    => 'textarea',
						'rows'    => 4,
						'value'   => null === $kept ? '' : $kept,
						'attrs'   => array(
							'minlength' => WPCPM_Institution_Agreement::MIN_NOTE,
							'maxlength' => WPCPM_Institution_Agreement::MAX_NOTE,
						),
						'problem' => $problem,
					)
				),
				'open'          => null !== $kept || '' !== $problem,
			)
		);
	}

	/**
	 * The return note's box by its name, the first words of its label, so the form and the sentence
	 * that refuses a note name it alike. The label's second sentence says how the note is sent, and
	 * reads as no part of a name.
	 *
	 * @return string
	 */
	private static function return_label() {
		// On the shared parts the box's label is "What has to change" alone, its second half the hint (design 5).
		if ( class_exists( 'WPCPM_UI', false ) && WPCPM_UI::on() ) {
			/* translators: The name of the box an Administrator types why a signed agreement is returned in: its label. */
			return __( 'What has to change', 'wpcredits-program-manager' );
		}

		/* translators: The name of the box an Administrator types why a signed agreement is returned in, the first words of its label. */
		return __( 'What has to change, in your own words', 'wpcredits-program-manager' );
	}

	/**
	 * The revoke note's box by its name, the first words of the label the Institutions screen's
	 * Agreements tab gives it, so the form and the sentence that refuses a note name it alike. The
	 * label's second sentence says who receives the note, and reads as no part of a name.
	 *
	 * @return string
	 */
	public static function revoke_label() {
		/* translators: The name of the box an Administrator types why an agreement is revoked in, the first words of its label. */
		return __( 'Why it is revoked, in your own words', 'wpcredits-program-manager' );
	}

	/**
	 * The sentence `render_state()` prints for a state the school is the one to act on, and the Institution
	 * Dashboard's Overview reads as its waiting row's why: for `none` and `generated` the lede, for `returned` and
	 * `revoked` the upload form's sentence. Not escaped: a caller escapes it where it prints it.
	 *
	 * @param string $state A summary state from `WPCPM_Institution_Agreement::summary()`.
	 * @param array  $row   The agreement's summary, for `generated` (its `generated_id` names the day and the
	 *                      template); not read for any other state.
	 * @return string '' for any other state.
	 */
	public static function state_sentence( string $state, array $row = array() ): string {
		switch ( $state ) {
			case WPCPM_Institution_Agreement::SUMMARY_NONE:
				return __( 'The program has no Collaboration Agreement recorded for your institution. Until it does, its representatives reach only the Overview and the Agreement, and its tutors no page.', 'wpcredits-program-manager' );

			case WPCPM_Institution_Agreement::SUMMARY_GENERATED:
				$generated = isset( $row['generated_id'] ) ? (int) $row['generated_id'] : 0;

				return sprintf( self::generated_format(), self::post_date( $generated ), self::template_version( $generated ) );

			case WPCPM_Institution_Agreement::SUMMARY_RETURNED:
				return __( 'Upload the corrected agreement, signed, as a PDF.', 'wpcredits-program-manager' );

			case WPCPM_Institution_Agreement::SUMMARY_REVOKED:
				return __( 'Upload a new signed agreement as a PDF. An Administrator reviews it the same way as the first one.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * The upload form: the signed PDF, which kind it is, and the declaration.
	 *
	 * Public because it is drawn in four places and must be identical in all of them but for
	 * the button's classes: this panel on three of its five states, the card at the foot of a
	 * settled dashboard (a replacement, T10), and an institution's Manage members view through
	 * `render_manager_upload()`, which is drawn in wp-admin, where only core's button classes
	 * dress a button. It asks the fence itself rather than trusting each caller, for the reason
	 * `render()` gives: a later screen that forgets the check is what the fence is for.
	 *
	 * **No free-text field.** Design spec 7.4 is explicit, and the reason is worth repeating
	 * where the markup is: a textarea on an upload form is a second place for personal data
	 * to land, in a record whose whole point is that only the document is kept.
	 *
	 * The declaration is a checkbox with a hidden `value="0"` companion in front of it, so an
	 * unticked box posts `0` rather than nothing. The handler refuses everything but `1`, and
	 * a field that is simply absent is indistinguishable from a field a proxy dropped.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $intro     A sentence above the form, or an empty string for none.
	 * @param string $button    The submit button's classes: the dashboard's own, which its
	 *                          stylesheet dresses, unless the screen it is drawn on dresses others.
	 */
	public static function render_upload_form( $record_id, $intro = '', $button = 'wpcpm-button' ) {
		$record_id = trim( (string) $record_id );

		if ( ! self::may_act( $record_id ) ) {
			return;
		}

		// The manager's upload in wp-admin is this form's one caller (`render_manager_upload()`): the Institution Dashboard
		// draws its own on the parts (`upload_form_parts()`).
		$base = self::upload_base( $record_id );

		self::form_start(
			'wpcpm-agreement-panel__form wpcpm-agreement-panel__form--upload',
			WPCPM_Institution_Agreement::ACTION_UPLOAD,
			WPCPM_Institution_Agreement::ACTION_UPLOAD . '_' . $record_id,
			__( 'Uploading...', 'wpcredits-program-manager' ),
			true
		);

		printf( '<input type="hidden" name="wpcpm_agreement_record" value="%s" />', esc_attr( $record_id ) );

		if ( '' !== trim( (string) $intro ) ) {
			printf( '<p class="wpcpm-agreement-panel__step">%s</p>', esc_html( $intro ) );
		}

		printf(
			'<p class="wpcpm-agreement-panel__field"><label for="%1$s-file">%2$s</label> <input type="file" id="%1$s-file" name="wpcpm_agreement_file" accept="application/pdf,.pdf" required /></p>',
			esc_attr( $base ),
			esc_html( self::file_label() )
		);

		echo '<fieldset class="wpcpm-agreement-panel__choices">';
		printf(
			'<legend>%s</legend>',
			esc_html( self::kind_legend() )
		);
		printf(
			'<p><label for="%1$s-kind-template"><input type="radio" id="%1$s-kind-template" name="wpcpm_agreement_kind" value="%2$s" checked="checked" /> %3$s</label></p>',
			esc_attr( $base ),
			esc_attr( WPCPM_Institution_Agreement::KIND_TEMPLATE ),
			esc_html( self::template_words() )
		);
		printf(
			'<p><label for="%1$s-kind-own"><input type="radio" id="%1$s-kind-own" name="wpcpm_agreement_kind" value="%2$s" /> %3$s</label></p>',
			esc_attr( $base ),
			esc_attr( WPCPM_Institution_Agreement::KIND_OWN ),
			esc_html__( 'An agreement of our own, signed. An Administrator reads this one in full, so it takes longer.', 'wpcredits-program-manager' )
		);
		echo '</fieldset>';

		// The companion goes first on purpose: a later field of the same name wins, so the
		// ticked box overwrites this and an unticked one leaves `0` behind. Without it an
		// unticked box posts nothing at all, which reads exactly like a field lost in
		// transit, and "the box was not ticked" and "the form did not arrive whole" are not
		// the same refusal.
		echo '<input type="hidden" name="wpcpm_agreement_signed" value="0" />';

		printf(
			'<p class="wpcpm-agreement-panel__field"><label for="%1$s-signed"><input type="checkbox" id="%1$s-signed" name="wpcpm_agreement_signed" value="1" required /> %2$s</label></p>',
			esc_attr( $base ),
			esc_html( self::signed_words() )
		);

		printf(
			'<button type="submit" class="%1$s">%2$s</button>',
			esc_attr( $button ),
			esc_html__( 'Upload the signed agreement', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * The manager's upload form on an institution's row, for a copy that arrived by email.
	 *
	 * The two bespoke agreements and many of the legacy ones reach the program as an
	 * attachment, so the person holding the PDF is a program manager rather than the
	 * institution. Same form, same handler, same nonce; the sentence above it is different
	 * because the reader is, and the button is core's primary one because the screen is
	 * wp-admin.
	 *
	 * Capability-checked *and* fenced here rather than at the call site, so the Institutions
	 * screen prints it with one call and cannot print it to the wrong person by forgetting
	 * either check. The fence is asked before the heading and not only inside the form: a
	 * viewer the policy refuses would otherwise be handed "Upload a signed agreement on the
	 * institution's behalf" with nothing under it, which reads as a broken screen rather
	 * than as a gate, and is the shape `render()` and `render_review()` are written to
	 * avoid.
	 *
	 * **It decides everything for itself.** Its one caller is an institution's Manage members
	 * view on the Institutions screen, which reads the record ID from the address and the
	 * institution's name from the index, and could hand either over. Nothing here is taken from
	 * the caller but the record ID, and that is checked for shape before it is used: the
	 * capability, the fence and the state below are all read here. A view rendering a form its
	 * handler would refuse is worse than one rendering nothing, and the screen is not the place
	 * where that is known.
	 *
	 * **The state it will not draw over.** `handle_upload()` allows one document in review at
	 * a time, so an institution with a signed copy already waiting gets the sentence saying
	 * so and no form, the way the card at the foot of a settled dashboard does for a
	 * replacement. An accepted agreement is not in that set: a re-signed copy arriving by
	 * email is exactly T10, and the current one stays in force until somebody accepts the new
	 * one.
	 *
	 * @param string $record_id Institutions record ID.
	 */
	public static function render_manager_upload( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) || ! self::may_act( $record_id ) ) {
			return;
		}

		printf(
			'<h3 class="wpcpm-agreement-panel__on-file-title">%s</h3>',
			esc_html__( 'Upload a signed agreement on the institution\'s behalf', 'wpcredits-program-manager' )
		);

		$summary = WPCPM_Institution_Agreement::summary( $record_id );
		$pending = isset( $summary['pending_id'] ) ? (int) $summary['pending_id'] : 0;

		// The way back out of that state rather than a dead end: the copy that is waiting,
		// and the control that takes it back. Both are gated on the document's own
		// institution, so the row cannot borrow another institution's paperwork by passing
		// the wrong record.
		if ( $pending ) {
			printf(
				'<p class="wpcpm-agreement-panel__lede">%s</p>',
				esc_html__( 'A signed agreement from this institution is already waiting for review, and only one can be in review at a time. Accept, return or withdraw that one first.', 'wpcredits-program-manager' )
			);

			self::render_download_link(
				$pending,
				__( 'Download the copy waiting for review', 'wpcredits-program-manager' )
			);
			self::render_withdraw_form( $pending, 'button button-secondary' );

			return;
		}

		self::render_upload_form(
			$record_id,
			__( 'For a signed copy that reached the program by email. It lands in the review queue exactly as an institution\'s own upload does, and everybody at the institution is emailed that it arrived.', 'wpcredits-program-manager' ),
			'button button-primary'
		);
	}

	/**
	 * The link that fetches one document's bytes.
	 *
	 * A nonced `admin-post.php` address and never the file's own URL: the plugin does not
	 * print a private file's URL anywhere, and the only route to the bytes is the handler,
	 * which decides on the post's institution and serves the file as an attachment under a
	 * name of the server's choosing.
	 *
	 * Public and gated on the post's own institution, because the panel, the card and the
	 * review block all print it and only one of the three has decided about this post.
	 *
	 * @param int    $post_id Agreement post ID.
	 * @param string $label   The link text.
	 * @param bool   $bare    True from the review block's parts branch: the line prints no class of its own.
	 */
	public static function render_download_link( $post_id, $label, $bare = false ) {
		$post_id = (int) $post_id;

		if ( ! self::may_download( $post_id ) ) {
			return;
		}

		// Drawn by the review block that decides on the Administrator Dashboard's parts, the line carries no class: its
		// rules live in sheets that page never loads, and no script reads it (Task 17b, ruling 5). Every other caller
		// keeps it.
		printf(
			$bare
				? '<p><a href="%1$s" rel="nofollow">%2$s</a></p>'
				: '<p class="wpcpm-agreement-panel__download"><a href="%1$s" rel="nofollow">%2$s</a></p>',
			esc_url( self::download_url( $post_id ) ),
			esc_html( $label )
		);
	}

	/**
	 * The nonced address of one document's bytes.
	 *
	 * @param int $post_id Agreement post ID.
	 * @return string
	 */
	private static function download_url( $post_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => WPCPM_Institution_Agreement::ACTION_DOWNLOAD,
					'post'   => (int) $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			WPCPM_Institution_Agreement::ACTION_DOWNLOAD . '_' . (int) $post_id
		);
	}

	/**
	 * Withdraw a document that is waiting for review.
	 *
	 * The one control the `submitted` state offers, and the reason the state can offer any:
	 * the wrong file uploaded to a queue somebody reads on Monday is a thing a person wants
	 * to take back on Friday. The confirm says the file is deleted, because it is, at once.
	 *
	 * Public and gated the way the download link is: a replacement uploaded over a standing
	 * agreement (T10) is withdrawn from the card at the foot of a settled dashboard, which is
	 * a second caller with a second decision of its own.
	 *
	 * **A manager may withdraw, and is told different things.** `handle_withdraw()` takes a
	 * member or a manager on the member's behalf, so the control belongs on an institution's
	 * Manage members view as much as on the panel; what does not carry across is the wording. A
	 * manager pressing this deletes somebody else's document, and the two facts they need and
	 * a member does not are that the institution is not emailed and that nothing will be
	 * reviewed until the institution uploads again. So the confirm is written for whoever the
	 * policy says is looking, and it names the institution the way every other manager's
	 * confirm in this module does: a person acting on forty rows should not have to remember
	 * which one the button belongs to.
	 *
	 * @param int    $post_id Agreement post ID.
	 * @param string $button  The submit button's classes: the dashboard's own, which its
	 *                        stylesheet dresses, unless the screen it is drawn on dresses others.
	 */
	public static function render_withdraw_form( $post_id, $button = 'wpcpm-button' ) {
		$post_id  = (int) $post_id;
		$decision = $post_id ? self::post_decision( $post_id ) : null;

		if ( null === $decision ) {
			return;
		}

		$as_manager = self::on_manager_ground( $decision );

		self::form_start(
			'wpcpm-agreement-panel__form wpcpm-agreement-panel__form--withdraw',
			WPCPM_Institution_Agreement::ACTION_WITHDRAW,
			WPCPM_Institution_Agreement::ACTION_WITHDRAW . '_' . $post_id,
			__( 'Withdrawing...', 'wpcredits-program-manager' ),
			false
		);

		printf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id );

		printf(
			'<button type="submit" class="%1$s" data-wpcpm-confirm="%2$s">%3$s</button>',
			esc_attr( (string) $button ),
			esc_attr( self::withdraw_confirm( $post_id, $as_manager ) ),
			esc_html( self::withdraw_label( $as_manager ) )
		);

		echo '</form>';
	}

	/**
	 * What the withdraw dialog asks, in the words of whoever is being asked.
	 *
	 * The member's copy is about their own document and their own next step. The manager's
	 * copy is about another organization's document: it names the institution, it says that
	 * nobody there is emailed - the one thing a manager has to know, because telling them
	 * becomes their job the moment they press it - and it says what the institution is left
	 * with, which is a queue with nothing in it.
	 *
	 * @param int  $post_id    Agreement post ID.
	 * @param bool $as_manager Whether the policy allowed this on the manager ground.
	 * @return string
	 */
	private static function withdraw_confirm( $post_id, $as_manager ) {
		if ( ! $as_manager ) {
			return __( 'Withdraw the signed agreement waiting for review? The file is deleted from this site straight away and nothing is kept from it. You can upload another whenever you are ready.', 'wpcredits-program-manager' );
		}

		return sprintf(
			/* translators: %s: institution name. */
			__( 'Withdraw the signed agreement from %s? Their file is deleted from this site straight away and nothing is kept from it. Nobody at the institution is emailed, so tell them yourself: until somebody uploads another copy there is nothing for anyone to review.', 'wpcredits-program-manager' ),
			self::institution_name( (string) get_post_meta( (int) $post_id, WPCPM_Institution_Agreement::META_INSTITUTION, true ) )
		);
	}

	/**
	 * An institution's name as the last sync read it, falling back to its record ID.
	 *
	 * The record ID is a poor name and a true one. A manager's dialog that named the wrong
	 * institution would be worse than one naming a record nobody recognizes, and the index is
	 * a cache: an institution created between two syncs is not in it yet.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	private static function institution_name( $record_id ) {
		$record_id = trim( (string) $record_id );
		$row       = WPCPM_Institutions_Index::row( $record_id );
		$name      = is_array( $row ) ? trim( (string) $row['name'] ) : '';

		return '' !== $name ? $name : $record_id;
	}

	/*
	 * The rules and the words both drawings use (Institution Dashboard, Task 21: one helper per rule, today's markup and the
	 * shared parts' both calling it)
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether this viewer is offered the form that records an agreement the program already holds.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return bool
	 */
	private static function may_record( $record_id ) {
		return current_user_can( WPCPM_Roles::CAP_MANAGE ) && WPCPM_Mentors_Sync::is_record_id( trim( (string) $record_id ) );
	}

	/**
	 * Whether this viewer is offered one document's bytes.
	 *
	 * @param int $post_id Agreement post ID.
	 * @return bool
	 */
	private static function may_download( $post_id ) {
		return (int) $post_id > 0 && self::may_act_on_post( (int) $post_id );
	}

	/**
	 * Whether the policy allowed a decision on the manager ground: the withdraw form's words and the Drive link hang on it.
	 *
	 * @param array|null $decision A decision from `WPCPM_Institution_Policy::decide()`.
	 * @return bool
	 */
	public static function on_manager_ground( $decision ) {
		return is_array( $decision ) && isset( $decision['ground'] ) && WPCPM_Institution_Policy::GROUND_MANAGER === (string) $decision['ground'];
	}

	/**
	 * The address every agreement form posts to: `admin-post.php`, with the institution on it for a manager whose
	 * handler reads no record of its own (`form_start()` says why).
	 *
	 * @param string $record The institution, or '' for a form keyed to a document or one that posts its record.
	 * @return string
	 */
	private static function form_url( $record ) {
		$url    = admin_url( 'admin-post.php' );
		$record = trim( (string) $record );

		if ( '' !== $record && current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$url = add_query_arg( array( WPCPM_Institution_Roster::ARG_VIEW => $record ), $url );
		}

		return $url;
	}

	/**
	 * The first part of every id the upload form gives its boxes.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	private static function upload_base( $record_id ) {
		return 'wpcpm-upload-' . sanitize_html_class( $record_id );
	}

	/**
	 * The first part of every id the generate form gives its boxes.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	private static function generate_base( $record_id ) {
		return 'wpcpm-generate-' . sanitize_html_class( $record_id );
	}

	/**
	 * The first part of every id the on-file form gives its boxes.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	private static function on_file_base( $record_id ) {
		return 'wpcpm-on-file-' . sanitize_html_class( $record_id );
	}

	/**
	 * The name the generate form offers to print: the index's, trimmed, cut where the handler cuts it.
	 *
	 * @param array $row The institution's index row.
	 * @return string
	 */
	private static function generate_name( array $row ) {
		return mb_substr( trim( (string) ( isset( $row['name'] ) ? $row['name'] : '' ) ), 0, self::MAX_NAME );
	}

	/**
	 * The languages the generate form offers, or none when there is only one template to print.
	 *
	 * @return string[]
	 */
	private static function language_choices() {
		$languages = (array) WPCPM_Agreement_Template::languages();

		return count( $languages ) > 1 ? array_values( $languages ) : array();
	}

	/**
	 * What "Open that document again" posts beside its action: the document, and the name and the language it was made with.
	 *
	 * @param int $post_id The generated document's post ID.
	 * @return array<string, string>
	 */
	private static function regenerate_fields( $post_id ) {
		return array(
			'wpcpm_agreement_post'     => (string) (int) $post_id,
			'wpcpm_agreement_name'     => (string) get_post_meta( (int) $post_id, WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT, true ),
			'wpcpm_agreement_language' => (string) get_post_meta( (int) $post_id, WPCPM_Institution_Agreement::META_LANGUAGE, true ),
		);
	}

	/**
	 * Which box an outcome refuses, so its sentence can describe that box: `file`, `signed`, `kind`, `name`, `drive`, or
	 * '' for an outcome that names none (busy, in review, a success).
	 *
	 * @param string $status An outcome slug.
	 * @return string
	 */
	private static function refused_box( $status ) {
		$boxes = array(
			'agreement-too-big'       => 'file',
			'agreement-not-pdf'       => 'file',
			'agreement-no-file'       => 'file',
			'agreement-file'          => 'file',
			'agreement-encrypted'     => 'file',
			'agreement-launch'        => 'file',
			'agreement-declare'       => 'signed',
			'agreement-kind'          => 'kind',
			'agreement-name'          => 'name',
			'agreement-name-brackets' => 'name',
			'agreement-link'          => 'drive',
		);

		return isset( $boxes[ (string) $status ] ) ? $boxes[ (string) $status ] : '';
	}

	/**
	 * The id of the box an outcome refuses, on this institution's forms; '' for none. A refused kind leads to the group's
	 * first option, which is the id the browser can put the focus in.
	 *
	 * @param string $status    An outcome slug.
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	private static function refused_id( $status, $record_id ) {
		switch ( self::refused_box( $status ) ) {
			case 'file':
				return self::upload_base( $record_id ) . '-file';
			case 'signed':
				return self::upload_base( $record_id ) . '-signed';
			case 'kind':
				return WPCPM_UI::choice_id( self::upload_base( $record_id ) . '-kind', WPCPM_Institution_Agreement::KIND_TEMPLATE );
			case 'name':
				return self::generate_base( $record_id ) . '-name';
			case 'drive':
				return self::on_file_base( $record_id ) . '-drive';
		}

		return '';
	}

	/**
	 * The sentence a box is described by while the block draws the outcome that refused it, or ''.
	 *
	 * @param string $box `file`, `signed`, `kind`, `name` or `drive`.
	 * @return string
	 */
	private static function problem_for( $box ) {
		if ( '' === self::$said || self::refused_box( self::$said ) !== $box ) {
			return '';
		}

		$messages = self::messages();

		return isset( $messages[ self::$said ] ) ? (string) $messages[ self::$said ][1] : '';
	}

	/**
	 * The assigned Administrator's address and the country it is named for, or null when the panel says to write to the
	 * Administrator who has been in touch instead.
	 *
	 * @param array $row The institution's index row.
	 * @return array{country: string, email: string}|null
	 */
	private static function contact_facts( array $row ) {
		$country = isset( $row['country'] ) ? (string) $row['country'] : '';
		$contact = ( '' !== WPCPM_Countries::name_of( $country ) && isset( $row['record_id'] ) ) ? WPCPM_Institution_Assignment::contact_of( (string) $row['record_id'] ) : null;

		if ( null === $contact || '' === trim( (string) $contact['email'] ) ) {
			return null;
		}

		return array(
			'country' => WPCPM_Countries::name_of( $country ),
			'email'   => (string) $contact['email'],
		);
	}

	/**
	 * The note an Administrator left on the newest document in a state, who left it and on which day; null when there is
	 * no such document.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $state     `STATE_RETURNED` or `STATE_REVOKED`.
	 * @return array{note: string, who: string, when: string}|null
	 */
	private static function note_facts( $record_id, $state ) {
		$post = self::latest_post( $record_id, $state );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$who  = get_userdata( (int) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_DECIDED_BY, true ) );
		$when = WPCPM_Dates::day( (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_DECIDED_AT, true ) );

		return array(
			'note' => trim( (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_NOTE, true ) ),
			'who'  => $who instanceof WP_User ? (string) $who->display_name : __( 'An Administrator', 'wpcredits-program-manager' ),
			'when' => '' !== $when ? $when : self::post_date( $post->ID ),
		);
	}

	/**
	 * When the agreement's state was last read from the base, or 0 when it has not been.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return int
	 */
	private static function read_at( $record_id ) {
		$option = WPCPM_Institution_Agreement::option( $record_id );

		return ( is_array( $option ) && ! empty( $option['updated'] ) ) ? (int) $option['updated'] : 0;
	}

	/**
	 * The words both drawings print, each said once.
	 * --------------------------------------------------------------------
	 */

	/**
	 * The sentence a generated document's state says: 1 its day, 2 its template.
	 *
	 * @return string
	 */
	private static function generated_format() {
		/* translators: 1: date the template was generated, 2: template version. */
		return __( 'You generated the program\'s agreement on %1$s (template %2$s). It is not signed and uploaded yet.', 'wpcredits-program-manager' );
	}

	/**
	 * The sentence a copy waiting for review says: the day it arrived.
	 *
	 * @return string
	 */
	private static function received_format() {
		/* translators: %s: date the signed agreement was received. */
		return __( 'We received your signed agreement on %s. An Administrator will review it and you will get an email either way.', 'wpcredits-program-manager' );
	}

	/**
	 * The first line of a state that has one of its own: returned, revoked, and a base that disagrees with the site.
	 *
	 * @param string $state A summary state.
	 * @return string
	 */
	private static function lede_text( $state ) {
		switch ( $state ) {
			case WPCPM_Institution_Agreement::SUMMARY_RETURNED:
				return __( 'An Administrator returned your agreement, with this note:', 'wpcredits-program-manager' );

			case WPCPM_Institution_Agreement::SUMMARY_REVOKED:
				return __( 'The program revoked this institution\'s agreement. Until a new agreement is accepted, its representatives reach only the Overview and the Agreement, and its tutors no page. The note the program left:', 'wpcredits-program-manager' );
		}

		return __( 'This site holds an accepted agreement for your institution, but the program\'s own record does not agree with it yet, so the account stays closed. An Administrator can see the difference and will settle it.', 'wpcredits-program-manager' );
	}

	/**
	 * The three steps, in their order.
	 *
	 * @return string[]
	 */
	private static function steps() {
		return array(
			__( 'Get the agreement: generate the program\'s template, or upload an agreement of your own. An Administrator reads an institution-specific agreement in full, so that path takes longer.', 'wpcredits-program-manager' ),
			__( 'Sign it. It needs somebody who can commit the institution.', 'wpcredits-program-manager' ),
			__( 'Upload the signed PDF here. An Administrator reviews it and you get an email either way.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * What generating does, said under the form today and above it on the parts.
	 *
	 * @return string
	 */
	private static function generate_hint() {
		return __( 'It opens as a page your browser can save as a PDF or print. Nothing is sent to the program until you upload the signed copy.', 'wpcredits-program-manager' );
	}

	/**
	 * The link to a document waiting for review, as the school's panel words it.
	 *
	 * @return string
	 */
	private static function held_label() {
		return __( 'Download the copy we hold', 'wpcredits-program-manager' );
	}

	/**
	 * The file box's label: the size this site accepts.
	 *
	 * @return string
	 */
	private static function file_label() {
		return sprintf(
			/* translators: %s: the largest upload the site accepts, in megabytes. */
			__( 'The signed agreement, as a PDF of up to %s MB', 'wpcredits-program-manager' ),
			number_format_i18n( max( 1, (int) WPCPM_Settings::get_value( 'agreement_max_mb', 10 ) ) )
		);
	}

	/**
	 * The question the kind of agreement answers.
	 *
	 * @return string
	 */
	private static function kind_legend() {
		return __( 'Which agreement is this?', 'wpcredits-program-manager' );
	}

	/**
	 * The program's template, as an option.
	 *
	 * @return string
	 */
	private static function template_words() {
		return __( 'The program\'s template, signed', 'wpcredits-program-manager' );
	}

	/**
	 * The signer's box, its own question.
	 *
	 * @return string
	 */
	private static function signed_words() {
		return __( 'This document is signed by somebody who can commit the institution.', 'wpcredits-program-manager' );
	}

	/**
	 * Withdraw's words, for whoever the policy says is pressing it.
	 *
	 * @param bool $as_manager Whether the policy allowed it on the manager ground.
	 * @return string
	 */
	private static function withdraw_label( $as_manager ) {
		return $as_manager
			? __( 'Withdraw it on the institution\'s behalf', 'wpcredits-program-manager' )
			: __( 'Withdraw it', 'wpcredits-program-manager' );
	}

	/**
	 * The sentence that saves a long-standing partner from signing twice.
	 *
	 * @return string
	 */
	private static function on_file_note() {
		return __( 'If your institution has already signed a Collaboration Agreement with the program, it does not need to sign again. An Administrator can record the copy the program holds, and your account opens as soon as they do.', 'wpcredits-program-manager' );
	}

	/**
	 * The assigned Administrator's line: 1 the country, 2 the address.
	 *
	 * @return string
	 */
	private static function contact_format() {
		/* translators: 1: country name, 2: the contact's email address. */
		return __( 'Your program contact for %1$s is %2$s.', 'wpcredits-program-manager' );
	}

	/**
	 * The line for a school with no assigned Administrator's address.
	 *
	 * @return string
	 */
	private static function no_contact() {
		return __( 'Write to the Administrator who has been in touch with your institution.', 'wpcredits-program-manager' );
	}

	/**
	 * Who left a note, and when: 1 the name, 2 the day.
	 *
	 * @return string
	 */
	private static function note_by_format() {
		/* translators: 1: the Administrator's name, 2: date. */
		return __( '%1$s, %2$s', 'wpcredits-program-manager' );
	}

	/**
	 * The line for a document that came back with no note.
	 *
	 * @return string
	 */
	private static function no_note() {
		return __( 'No note was left.', 'wpcredits-program-manager' );
	}

	/**
	 * The read line: 1 the moment, 2 how long ago.
	 *
	 * @return string
	 */
	private static function read_format() {
		/* translators: 1: date and time, 2: human-readable time difference. */
		return __( 'The agreement\'s state was read from the program records on %1$s (%2$s ago).', 'wpcredits-program-manager' );
	}

	/**
	 * The read line before the state was ever read.
	 *
	 * @return string
	 */
	private static function unread() {
		return __( 'The agreement state has not been read from the program\'s records yet.', 'wpcredits-program-manager' );
	}

	/**
	 * The on-file form's heading.
	 *
	 * @return string
	 */
	private static function on_file_title() {
		return __( 'Record an agreement the program already holds', 'wpcredits-program-manager' );
	}

	/**
	 * What the on-file form is for.
	 *
	 * @return string
	 */
	private static function on_file_lede() {
		return __( 'For an institution that signed before this site existed. Airtable is written first; the account opens on the next page load.', 'wpcredits-program-manager' );
	}

	/**
	 * The Drive box's example.
	 *
	 * @return string
	 */
	private static function drive_placeholder() {
		return __( 'https://drive.google.com/...', 'wpcredits-program-manager' );
	}

	/**
	 * The location box's example.
	 *
	 * @return string
	 */
	private static function where_placeholder() {
		return __( 'second folder, the 2025 copy', 'wpcredits-program-manager' );
	}

	/**
	 * What Record it asks before it posts.
	 *
	 * @param string $name The institution's name.
	 * @return string
	 */
	private static function on_file_confirm( $name ) {
		return sprintf(
			/* translators: %s: institution name. */
			__( 'Record a signed agreement on file for %s? This opens their account on the site and sets Agreement Status to On file in Airtable.', 'wpcredits-program-manager' ),
			$name
		);
	}

	/*
	 * The shared parts' drawing (Institution Dashboard, Task 21, design 6.5)
	 * --------------------------------------------------------------------
	 */

	/**
	 * The view's one block on the parts, which the panel and the card both draw in: a section with no head carrying the
	 * anchor, the outcome first (a refusal a link to the box it refused, when the block holds that box), then the body.
	 * The outcome is taken once, before the body is drawn, so the form holding the refused box can describe it.
	 *
	 * @param string   $record_id Institutions record ID.
	 * @param callable $draw      Prints the body.
	 */
	public static function parts_block( $record_id, callable $draw ) {
		$status     = self::take_status();
		self::$said = $status;
		self::$kept = self::kept_forms( $record_id );

		ob_start();

		try {
			$draw();
		} finally {
			$body       = (string) ob_get_clean();
			self::$said = '';
			self::$kept = array();
		}

		$lead = self::refused_id( $status, $record_id );

		if ( '' === $lead || false === strpos( $body, ' id="' . $lead . '"' ) ) {
			$lead = '';
		}

		ob_start();
		self::print_flash( $status, $lead );
		$said = (string) ob_get_clean();

		// A refusal of a form the block draws is said in that form, after its own heading and intro and directly over its
		// first field (Gate B round 2, F2); every other outcome at the block's top. The marks print nothing.
		$mark = self::outcome_mark( self::outcome_form( $status ) );

		if ( '' !== $said && '' !== $mark && false !== strpos( $body, $mark ) ) {
			$body = str_replace( $mark, $said, $body );
			$said = '';
		}

		$body = (string) preg_replace( '/<!--wpcpm-outcome:[a-z-]+-->/', '', $body );

		printf( '<section class="wpcpm-ui-block" id="%s">', esc_attr( self::ANCHOR ) );
		echo $said . $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each piece is escaped where it is drawn; the outcome by WPCPM_Dashboard_Card::message().
		echo '</section>';
	}

	/**
	 * Where in a form its refusal is said: an inert mark the block replaces with the refusal, or removes (Gate B round 2,
	 * F2). '' for no form.
	 *
	 * @param string $form `upload`, `generate`, `on-file` or ''.
	 * @return string
	 */
	private static function outcome_mark( $form ) {
		return '' !== (string) $form ? '<!--wpcpm-outcome:' . sanitize_key( (string) $form ) . '-->' : '';
	}

	/**
	 * The form a refusal is about: the upload's (its file, its kind, its tick, a copy already waiting), the generate
	 * form's (its name, an agreement standing or waiting, a record not saved) or the program's on-file record (its link,
	 * an agreement standing, a school not in the records). '' for a success and for a refusal no one form on this block
	 * owns (a busy record, a write that failed, a decision made elsewhere), which the block says at its top.
	 *
	 * @param string $status An outcome slug.
	 * @return string `upload`, `generate`, `on-file` or ''.
	 */
	private static function outcome_form( $status ) {
		// Once the site keeps the record only Regenerate says agreement-unknown, so it is said at the block's top (1.123.102).
		if ( 'agreement-unknown' === (string) $status && class_exists( 'WPCPM_Institutions_Index' ) && WPCPM_Institutions_Index::on_site() ) {
			return '';
		}

		$forms = array(
			'upload'   => array( 'agreement-too-big', 'agreement-not-pdf', 'agreement-no-file', 'agreement-file', 'agreement-encrypted', 'agreement-launch', 'agreement-in-review', 'agreement-declare', 'agreement-kind' ),
			'generate' => array( 'agreement-name', 'agreement-name-brackets', 'agreement-generate-in-review', 'agreement-generate-standing', 'agreement-generate-not-saved' ),
			'on-file'  => array( 'agreement-link', 'agreement-standing', 'agreement-unknown', 'agreement-on-file-unlisted', 'agreement-on-file-revisit', 'agreement-on-file-not-moving', 'agreement-on-file-spam' ),
		);

		foreach ( $forms as $form => $statuses ) {
			if ( in_array( (string) $status, $statuses, true ) ) {
				return $form;
			}
		}

		return '';
	}

	/**
	 * What each of this institution's refused forms kept for its one redraw, taken once (`WPCPM_Flash::kept()`, the
	 * mechanism the invite form keeps its typing by): the upload's kind, the generate form's name, the on-file record's
	 * three boxes (Task 21, fix round 1).
	 *
	 * @param string $record_id Institutions record ID.
	 * @return array<string, array> The form's key to its kept values.
	 */
	private static function kept_forms( $record_id ) {
		$forms = array(
			'upload'  => WPCPM_Institution_Agreement::KEPT_UPLOAD,
			'on-file' => WPCPM_Institution_Agreement::KEPT_ON_FILE,
		);

		if ( class_exists( 'WPCPM_Agreement_Generate' ) && defined( 'WPCPM_Agreement_Generate::KEPT_GENERATE' ) ) {
			$forms['generate'] = WPCPM_Agreement_Generate::KEPT_GENERATE;
		}

		$out = array();

		foreach ( $forms as $key => $prefix ) {
			$kept        = WPCPM_Flash::kept( $prefix . $record_id );
			$out[ $key ] = $kept['values'];
		}

		return $out;
	}

	/**
	 * One kept value of a refused form, or null when the form kept none.
	 *
	 * @param string $form  upload, generate or on-file.
	 * @param string $field The posted field.
	 * @return string|null
	 */
	private static function kept_value( $form, $field ) {
		$value = self::$kept[ $form ][ $field ] ?? null;

		return is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * The banner's sentences for an Administrator, as one note set apart from the school's own words: the parts' note in
	 * its paragraph form, the part for a remark that is not the card's own text. The sentences are the ones the Overview's
	 * row reads (`WPCPM_Institutions_Dashboard::banner_sentences()`), less what this view's own sentence already says (S2,
	 * 1.123.99); none, no note.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string Escaped markup, or ''.
	 */
	private static function banner_parts( $record_id ) {
		if ( ! class_exists( 'WPCPM_Institutions_Dashboard' ) ) {
			return '';
		}

		$sentences = (array) WPCPM_Institutions_Dashboard::banner_sentences( (string) $record_id, true );

		return array() === $sentences ? '' : WPCPM_UI::note( implode( ' ', $sentences ) );
	}

	/**
	 * A sentence with its values put in: the words escaped, each value escaped and drawn whole (a day) or as it is (the sheet
	 * breaks a long name or address where it would overflow). `%s` and `%1$s` are read; nothing else is.
	 *
	 * @param string $format The sentence, plain text.
	 * @param array  $values Each `array( text, 'whole' | '' )`, in the placeholders' numbering.
	 * @return string Escaped markup.
	 */
	public static function fill( $format, array $values ) {
		$pieces = preg_split( '/(%(?:\d+\$)?s)/', (string) $format, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out    = '';
		$next   = 0;

		foreach ( (array) $pieces as $n => $piece ) {
			if ( 0 === $n % 2 ) {
				$out .= esc_html( $piece );
				continue;
			}

			$at = 1 === preg_match( '/^%(\d+)\$s$/', $piece, $m ) ? (int) $m[1] - 1 : $next;
			++$next;

			$value = isset( $values[ $at ] ) ? (array) $values[ $at ] : array( '', '' );
			$text  = (string) ( $value[0] ?? '' );
			$kind  = (string) ( $value[1] ?? '' );

			if ( 'whole' === $kind ) {
				$out .= WPCPM_UI::whole( $text );
			} else {
				$out .= esc_html( $text );
			}
		}

		return $out;
	}

	/**
	 * A state's first line: its tag leading, when it has one, then its sentence.
	 *
	 * @param string $words The tag's words, or '' for none.
	 * @param string $tone  red, amber or green.
	 * @param string $html  The sentence, escaped.
	 * @return string Escaped markup.
	 */
	public static function lede_parts( $words, $tone, $html ) {
		return WPCPM_UI::intro( '', '', '', ( '' !== $words ? WPCPM_UI::tag( $words, $tone ) . ' ' : '' ) . $html );
	}

	/**
	 * Hidden fields, each escaped.
	 *
	 * @param array<string, string> $fields Name to value.
	 * @return string
	 */
	private static function hidden( array $fields ) {
		$out = '';

		foreach ( $fields as $name => $value ) {
			$out .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}

		return $out;
	}

	/**
	 * A form's opening on the parts: what `form_start()` prints, the once guard and its busy words, with the parts' nonce
	 * (no id) and the referer.
	 *
	 * @param string $action       The `admin_post_` action name.
	 * @param string $nonce_action The nonce action.
	 * @param string $busy         What the pressed control says while the request is in flight.
	 * @param bool   $multipart    Whether the form carries a file.
	 * @param string $record       As `form_start()`.
	 * @return string Escaped markup.
	 */
	private static function form_open_parts( $action, $nonce_action, $busy, $multipart, $record = '' ) {
		return sprintf(
			'<form method="post" action="%1$s" enctype="%2$s" data-wpcpm-once data-wpcpm-busy="%3$s">',
			esc_url( self::form_url( $record ) ),
			esc_attr( $multipart ? 'multipart/form-data' : 'application/x-www-form-urlencoded' ),
			esc_attr( $busy )
		) . WPCPM_UI::nonce( $nonce_action ) . self::hidden( array( 'action' => $action ) );
	}

	/**
	 * One action row of the pieces given, or '' when none is drawn.
	 *
	 * @param string[] $pieces Buttons, links or forms holding one, escaped; '' for a piece not drawn.
	 * @return string
	 */
	public static function row_of( array $pieces ) {
		$pieces = array_values(
			array_filter(
				$pieces,
				static function ( $piece ) {
					return '' !== (string) $piece;
				}
			)
		);

		return array() === $pieces ? '' : WPCPM_UI::actions( $pieces );
	}

	/**
	 * A document's bytes as the secondary button, or '' for a viewer the policy refuses.
	 *
	 * @param int    $post_id Agreement post ID.
	 * @param string $label   The words.
	 * @return string Escaped markup.
	 */
	public static function download_button( $post_id, $label ) {
		if ( ! self::may_download( (int) $post_id ) ) {
			return '';
		}

		return WPCPM_UI::button(
			array(
				'label' => $label,
				'kind'  => 'secondary',
				'href'  => self::download_url( (int) $post_id ),
				'rel'   => 'nofollow',
				'side'  => 'front',
			)
		);
	}

	/**
	 * Withdraw on the parts, the danger button, today's question; '' for a viewer the policy refuses.
	 *
	 * @param int $post_id Agreement post ID.
	 * @return string Escaped markup.
	 */
	public static function withdraw_form_parts( $post_id ) {
		$post_id  = (int) $post_id;
		$decision = $post_id ? self::post_decision( $post_id ) : null;

		if ( null === $decision ) {
			return '';
		}

		$as_manager = self::on_manager_ground( $decision );

		return self::form_open_parts( WPCPM_Institution_Agreement::ACTION_WITHDRAW, WPCPM_Institution_Agreement::ACTION_WITHDRAW . '_' . $post_id, __( 'Withdrawing...', 'wpcredits-program-manager' ), false )
			. self::hidden( array( 'wpcpm_agreement_post' => (string) $post_id ) )
			. WPCPM_UI::button(
				array(
					'label'   => self::withdraw_label( $as_manager ),
					'kind'    => 'danger',
					'confirm' => self::withdraw_confirm( $post_id, $as_manager ),
					'side'    => 'front',
				)
			)
			. '</form>';
	}

	/**
	 * The upload form on the parts: the kind a choice group, the signer's box its own question, the file box, Upload the
	 * signed agreement. It posts what today's form posts; a refused box is described by its sentence.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $intro     A sentence above the form, or ''.
	 * @return string Escaped markup, or '' for a viewer the policy refuses.
	 */
	public static function upload_form_parts( $record_id, $intro = '' ) {
		$record_id = trim( (string) $record_id );

		if ( ! self::may_act( $record_id ) ) {
			return '';
		}

		$base = self::upload_base( $record_id );
		$out  = self::form_open_parts( WPCPM_Institution_Agreement::ACTION_UPLOAD, WPCPM_Institution_Agreement::ACTION_UPLOAD . '_' . $record_id, __( 'Uploading...', 'wpcredits-program-manager' ), true )
			. self::hidden( array( 'wpcpm_agreement_record' => $record_id ) );

		if ( '' !== trim( (string) $intro ) ) {
			$out .= WPCPM_UI::intro( (string) $intro );
		}

		$out .= self::outcome_mark( 'upload' );

		// Today's second option is one sentence; on the parts its first half is the option and its second the hint.
		$own = array(
			'label' => __( 'An agreement of our own, signed', 'wpcredits-program-manager' ),
			'hint'  => __( 'An Administrator reads this one in full, so it takes longer.', 'wpcredits-program-manager' ),
		);

		$out .= WPCPM_UI::choices(
			array(
				'id'            => $base . '-kind',
				'legend'        => self::kind_legend(),
				'name'          => 'wpcpm_agreement_kind',
				'type'          => 'radio',
				'options'       => array(
					WPCPM_Institution_Agreement::KIND_TEMPLATE => self::template_words(),
					WPCPM_Institution_Agreement::KIND_OWN => $own,
				),
				'checked'       => self::kept_kind(),
				'group_problem' => self::problem_for( 'kind' ),
			)
		);

		// The companion first, as today: the ticked box overwrites it, an unticked one leaves `0` (render_upload_form()).
		$out .= self::hidden( array( 'wpcpm_agreement_signed' => '0' ) );
		$out .= WPCPM_UI::choices(
			array(
				'id'      => $base . '-signed',
				'name'    => 'wpcpm_agreement_signed',
				'type'    => 'checkbox',
				'options' => array( '1' => self::signed_words() ),
				'need'    => 'required',
				'problem' => self::problem_for( 'signed' ),
			)
		);
		$out .= WPCPM_UI::file(
			array(
				'id'      => $base . '-file',
				'name'    => 'wpcpm_agreement_file',
				'label'   => self::file_label(),
				'accept'  => 'application/pdf,.pdf',
				'need'    => 'required',
				'problem' => self::problem_for( 'file' ),
			)
		);

		return $out . WPCPM_UI::actions(
			array(
				WPCPM_UI::button(
					array(
						'label' => __( 'Upload the signed agreement', 'wpcredits-program-manager' ),
						'side'  => 'front',
					)
				),
			)
		) . '</form>';
	}

	/**
	 * The kind a refused upload kept, when it is one a form may declare; today's default, the template, otherwise.
	 *
	 * @return string
	 */
	private static function kept_kind() {
		$kind = self::kept_value( 'upload', 'wpcpm_agreement_kind' );

		return in_array( $kind, array( WPCPM_Institution_Agreement::KIND_TEMPLATE, WPCPM_Institution_Agreement::KIND_OWN ), true ) ? (string) $kind : WPCPM_Institution_Agreement::KIND_TEMPLATE;
	}

	/**
	 * The generate form on the parts: what it does, the institution's name (its label and hint), the language when there is
	 * a choice, Generate the agreement. It posts what today's form posts, to the same address.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $row       The institution's index row.
	 * @return string Escaped markup, or '' for a viewer the policy refuses.
	 */
	private static function generate_form_parts( $record_id, array $row ) {
		if ( ! self::may_act( $record_id ) ) {
			return '';
		}

		$base      = self::generate_base( $record_id );
		$languages = self::language_choices();
		$kept      = self::kept_value( 'generate', 'wpcpm_agreement_name' );
		$out       = self::form_open_parts( WPCPM_Agreement_Generate::ACTION_GENERATE, WPCPM_Agreement_Generate::ACTION_GENERATE . '_' . $record_id, __( 'Generating...', 'wpcredits-program-manager' ), false, $record_id )
			. self::outcome_mark( 'generate' )
			. WPCPM_UI::field(
				array(
					'id'      => $base . '-name',
					'name'    => 'wpcpm_agreement_name',
					'label'   => __( 'The institution\'s name', 'wpcredits-program-manager' ),
					'hint'    => __( 'Exactly as it should print on the agreement.', 'wpcredits-program-manager' ),
					'value'   => null !== $kept ? $kept : self::generate_name( $row ),
					'need'    => 'required',
					'attrs'   => array( 'maxlength' => (string) self::MAX_NAME ),
					'problem' => self::problem_for( 'name' ),
				)
			);

		if ( array() !== $languages ) {
			$out .= WPCPM_UI::field(
				array(
					'type'    => 'select',
					'id'      => $base . '-language',
					'name'    => 'wpcpm_agreement_language',
					'label'   => __( 'Language', 'wpcredits-program-manager' ),
					'options' => array_combine( $languages, $languages ),
				)
			);
		}

		// What generating does follows its button, as today, where "It" is the agreement just asked for.
		return $out . WPCPM_UI::actions(
			array(
				WPCPM_UI::button(
					array(
						'label' => __( 'Generate the agreement', 'wpcredits-program-manager' ),
						'side'  => 'front',
					)
				),
			)
		) . WPCPM_UI::intro( self::generate_hint(), 'muted' ) . '</form>';
	}

	/**
	 * "Open that document again" on the parts, the secondary button, posting what today's does.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param int    $post_id   The generated document's post ID.
	 * @return string Escaped markup, or ''.
	 */
	private static function regenerate_form_parts( $record_id, $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id || ! self::may_act( $record_id ) ) {
			return '';
		}

		return self::form_open_parts( WPCPM_Agreement_Generate::ACTION_GENERATE, WPCPM_Agreement_Generate::ACTION_GENERATE . '_' . $record_id, __( 'Generating...', 'wpcredits-program-manager' ), false, $record_id )
			. self::hidden( self::regenerate_fields( $post_id ) )
			. WPCPM_UI::actions(
				array(
					WPCPM_UI::button(
						array(
							'label' => __( 'Open that document again', 'wpcredits-program-manager' ),
							'kind'  => 'secondary',
							'side'  => 'front',
						)
					),
				)
			)
			. '</form>';
	}

	/**
	 * Recording an agreement the program already holds, on the parts: its heading and what it is for, the Drive link
	 * (Required), the day it was signed and where it is (Optional), Record it with today's question. It posts what today's
	 * form posts.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string Escaped markup, or '' for anybody but an Administrator.
	 */
	private static function on_file_form_parts( $record_id ) {
		$record_id = trim( (string) $record_id );

		if ( ! self::may_record( $record_id ) ) {
			return '';
		}

		$base = self::on_file_base( $record_id );

		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. WPCPM_UI::nonce( WPCPM_Institution_Agreement::ACTION_ON_FILE . '_' . $record_id )
			. self::hidden(
				array(
					'action'                 => WPCPM_Institution_Agreement::ACTION_ON_FILE,
					'wpcpm_agreement_record' => $record_id,
				)
			)
			// An h3, under the view's h2: the block has no head of its own (as Semester reports' in-view headings).
			. WPCPM_UI::heading( self::on_file_title(), 'h3' )
			. WPCPM_UI::intro( self::on_file_lede() )
			. self::outcome_mark( 'on-file' )
			. WPCPM_UI::field(
				array(
					'type'    => 'url',
					'id'      => $base . '-drive',
					'name'    => 'wpcpm_agreement_drive',
					'label'   => __( 'Drive link to the folder or the file', 'wpcredits-program-manager' ),
					'value'   => (string) self::kept_value( 'on-file', 'wpcpm_agreement_drive' ),
					'need'    => 'required',
					'attrs'   => array( 'placeholder' => self::drive_placeholder() ),
					'problem' => self::problem_for( 'drive' ),
				)
			)
			. WPCPM_UI::field(
				array(
					'type'  => 'date',
					'id'    => $base . '-signed',
					'name'  => 'wpcpm_agreement_signed_on',
					'label' => __( 'Date signed', 'wpcredits-program-manager' ),
					'value' => (string) self::kept_value( 'on-file', 'wpcpm_agreement_signed_on' ),
					'need'  => 'optional',
				)
			)
			. WPCPM_UI::field(
				array(
					'id'    => $base . '-where',
					'name'  => 'wpcpm_agreement_where',
					'label' => __( 'Where it is, in one line', 'wpcredits-program-manager' ),
					'value' => (string) self::kept_value( 'on-file', 'wpcpm_agreement_where' ),
					'need'  => 'optional',
					'attrs' => array(
						'maxlength'   => (string) (int) WPCPM_Institution_Agreement::MAX_LOCATION,
						'placeholder' => self::where_placeholder(),
					),
				)
			)
			. WPCPM_UI::actions(
				array(
					WPCPM_UI::button(
						array(
							'label'   => __( 'Record it', 'wpcredits-program-manager' ),
							'confirm' => self::on_file_confirm( self::institution_name( $record_id ) ),
							'side'    => 'front',
						)
					),
				)
			)
			. '</form>';
	}

	/**
	 * The already-signed note and the assigned Administrator's line, for the school's own people; nothing for an
	 * Administrator, who is the person they point at.
	 *
	 * @param array $row        The institution's index row.
	 * @param bool  $can_manage Whether the viewer holds CAP_MANAGE.
	 * @param bool  $note       Whether the already-signed note comes first.
	 * @return string Escaped markup.
	 */
	private static function school_lines( array $row, $can_manage, $note ) {
		if ( $can_manage ) {
			return '';
		}

		$contact = self::contact_facts( $row );

		return ( $note ? WPCPM_UI::intro( self::on_file_note() ) : '' )
			. WPCPM_UI::intro(
				'',
				'',
				'',
				null === $contact ? esc_html( self::no_contact() ) : self::fill(
					self::contact_format(),
					array(
						array( $contact['country'], '' ),
						array( $contact['email'], '' ),
					)
				)
			);
	}

	/**
	 * The note an Administrator left, as a quotation, then who left it and on which day.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param string $state     `STATE_RETURNED` or `STATE_REVOKED`.
	 * @return string Escaped markup.
	 */
	private static function note_parts( $record_id, $state ) {
		$facts = self::note_facts( $record_id, $state );

		if ( null === $facts ) {
			return '';
		}

		return ( '' !== $facts['note'] ? WPCPM_UI::note( $facts['note'], 'blockquote' ) : WPCPM_UI::intro( self::no_note() ) )
			. WPCPM_UI::intro(
				'',
				'muted',
				'',
				self::fill(
					self::note_by_format(),
					array(
						array( $facts['who'], '' ),
						array( $facts['when'], 'whole' ),
					)
				)
			);
	}

	/**
	 * The read line on the parts, its moment whole.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string Escaped markup.
	 */
	private static function read_html( $record_id ) {
		$updated = self::read_at( $record_id );

		if ( ! $updated ) {
			return esc_html( self::unread() );
		}

		return self::fill(
			self::read_format(),
			array(
				array( WPCPM_Dates::moment( $updated ), 'whole' ),
				array( human_time_diff( $updated, time() ), '' ),
			)
		);
	}

	/**
	 * The three steps, each form inside the step it carries out, as today's `render_steps()` draws them: step 1 the
	 * generate form, step 2 its sentence alone, step 3 the upload form (Task 21, fix round 1).
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $row       The institution's index row.
	 * @param string $again     Open that document again, escaped, or ''.
	 * @return string Escaped markup.
	 */
	private static function steps_parts( $record_id, array $row, $again ) {
		$steps = self::steps();

		return WPCPM_UI::checklist(
			array(
				array(
					'text' => $steps[0],
					'body' => $again . self::generate_form_parts( $record_id, $row ),
				),
				$steps[1],
				array(
					'text' => $steps[2],
					'body' => self::upload_form_parts( $record_id, '' ),
				),
			),
			true
		);
	}

	/**
	 * One state's words and controls on the parts (design 6.5): the tag leading where the state has one, its sentence,
	 * the steps as a list of sentences, the forms, the note, the copy and the way to take it back.
	 *
	 * @param string $record_id  Institutions record ID.
	 * @param array  $summary    From `WPCPM_Institution_Agreement::summary()`.
	 * @param array  $row        The institution's index row.
	 * @param bool   $can_manage Whether the viewer holds CAP_MANAGE.
	 */
	private static function render_state_parts( $record_id, array $summary, array $row, $can_manage ) {
		$state = isset( $summary['state'] ) ? (string) $summary['state'] : WPCPM_Institution_Agreement::SUMMARY_NONE;

		switch ( $state ) {
			case WPCPM_Institution_Agreement::SUMMARY_GENERATED:
				$generated = (int) $summary['generated_id'];
				$out       = self::lede_parts(
					'',
					'',
					self::fill(
						self::generated_format(),
						array(
							array( self::post_date( $generated ), 'whole' ),
							array( self::template_version( $generated ), '' ),
						)
					)
				)
					// Step 1 holds Open that document again first, as today's page has it above the form, then the form.
					. self::steps_parts( $record_id, $row, self::regenerate_form_parts( $record_id, $generated ) )
					. self::school_lines( $row, $can_manage, true );
				break;

			case WPCPM_Institution_Agreement::SUMMARY_SUBMITTED:
				$pending = (int) $summary['pending_id'];
				$out     = self::lede_parts( __( 'Waiting for review', 'wpcredits-program-manager' ), 'amber', self::fill( self::received_format(), array( array( self::post_date( $pending ), 'whole' ) ) ) )
					. self::row_of( array( self::download_button( $pending, self::held_label() ), self::withdraw_form_parts( $pending ) ) );
				break;

			case WPCPM_Institution_Agreement::SUMMARY_RETURNED:
			case WPCPM_Institution_Agreement::SUMMARY_REVOKED:
				$returned = WPCPM_Institution_Agreement::SUMMARY_RETURNED === $state;
				$out      = self::lede_parts( $returned ? __( 'Returned', 'wpcredits-program-manager' ) : __( 'Revoked', 'wpcredits-program-manager' ), 'red', esc_html( self::lede_text( $state ) ) )
					. self::note_parts( $record_id, $returned ? WPCPM_Institution_Agreement::STATE_RETURNED : WPCPM_Institution_Agreement::STATE_REVOKED )
					. self::upload_form_parts( $record_id, self::state_sentence( $state ) )
					. self::school_lines( $row, $can_manage, false );
				break;

			case WPCPM_Institution_Agreement::SUMMARY_ACCEPTED:
			case WPCPM_Institution_Agreement::SUMMARY_ON_FILE:
				// The base disagrees with the site: no tag, since neither "Accepted" nor any other is true; the sentence says it.
				$out = self::lede_parts( '', '', esc_html( self::lede_text( $state ) ) ) . self::school_lines( $row, $can_manage, false );
				break;

			default:
				$out = self::lede_parts( '', '', esc_html( self::state_sentence( WPCPM_Institution_Agreement::SUMMARY_NONE ) ) )
					. self::steps_parts( $record_id, $row, '' )
					. self::school_lines( $row, $can_manage, true );
				break;
		}

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and in fill().
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
	 * The day a post was written, in the site's date format, or an empty string.
	 *
	 * Read from `post_date`, the site-local one, as the day it names: `WPCPM_Dates::day()` takes
	 * its first ten characters and moves no time zone over them.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function post_date( $post_id ) {
		$post = get_post( (int) $post_id );

		return $post instanceof WP_Post ? WPCPM_Dates::day( (string) $post->post_date ) : '';
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
 * Since Phase 3 it also carries the accepted copy and the way to replace it (T10). The
 * download is drawn from the file rather than from the state, so an institution whose
 * agreement predates this site is not offered a control that would 404: what the program
 * holds for those is a copy in Drive, which the route line says and the link at the foot
 * opens for a program manager.
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
	public static function render( $record_id, array $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- The shape every card shares; see the docblock.
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
		$as_manager = WPCPM_Institution_Panel::on_manager_ground( $decision );
		$summary    = WPCPM_Institution_Agreement::summary( $record_id );
		$accepted   = self::accepted_day( $summary );

		// On the shared parts the card is the Agreement view's content once the agreement is settled (Institution Dashboard,
		// Task 21): no head and no fold; its block carries the anchor the panel carries while it is not.
		WPCPM_Institution_Panel::parts_block(
			$record_id,
			static function () use ( $record_id, $summary, $accepted, $as_manager ) {
				self::render_parts( $record_id, $summary, $accepted, $as_manager );
			}
		);
	}

	/**
	 * How the accepted agreement was signed or recorded, in words.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 * @return string
	 */
	private static function route_sentence( array $summary ) {
		$route  = isset( $summary['route'] ) ? (string) $summary['route'] : '';
		$legacy = isset( $summary['kind'] ) && WPCPM_Institution_Agreement::KIND_LEGACY === (string) $summary['kind'];

		if ( 'grid' === $route ) {
			return $legacy
				? __( 'Recorded from the program\'s own records: the signed copy is in the program\'s Drive folder, and nothing was signed through this site.', 'wpcredits-program-manager' )
				: __( 'Recorded from the program\'s own records rather than through this site.', 'wpcredits-program-manager' );
		}

		if ( $legacy ) {
			return __( 'Recorded on this site by an Administrator, from the copy the program holds in its Drive folder.', 'wpcredits-program-manager' );
		}

		return __( 'Signed and accepted through this site.', 'wpcredits-program-manager' );
	}

	/*
	 * The rules and the words both drawings use (Institution Dashboard, Task 21)
	 * --------------------------------------------------------------------
	 */

	/**
	 * The day the agreement was accepted, in the site's format, or ''.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 * @return string
	 */
	private static function accepted_day( array $summary ) {
		return WPCPM_Dates::day( isset( $summary['accepted_at'] ) ? (string) $summary['accepted_at'] : '' );
	}

	/**
	 * The accepted copy's post when this site holds its file, or 0: a legacy row has no file, and a download would 404.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 * @return int
	 */
	private static function accepted_file( array $summary ) {
		$post_id = isset( $summary['agreement_id'] ) ? (int) $summary['agreement_id'] : 0;
		$file    = $post_id ? get_post_meta( $post_id, WPCPM_Institution_Agreement::META_FILE, true ) : '';

		return ( $post_id && is_array( $file ) && ! empty( $file['path'] ) ) ? $post_id : 0;
	}

	/**
	 * The newer copy waiting for review, or 0.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 * @return int
	 */
	private static function pending( array $summary ) {
		return isset( $summary['pending_id'] ) ? (int) $summary['pending_id'] : 0;
	}

	/**
	 * The program's Drive folder for this agreement, or '' when it holds no Drive link.
	 *
	 * @param string $record_id Institutions record ID.
	 * @return string
	 */
	private static function drive_url( $record_id ) {
		$option = WPCPM_Institution_Agreement::option( $record_id );
		$url    = ( is_array( $option ) && isset( $option['drive_url'] ) ) ? (string) $option['drive_url'] : '';

		return WPCPM_Institution_Agreement::is_drive_link( $url ) ? $url : '';
	}

	/**
	 * The acceptance with no day recorded, after the parts' Accepted tag, which says the rest (fix round 2).
	 *
	 * @return string
	 */
	private static function not_recorded() {
		return __( 'The date was not recorded.', 'wpcredits-program-manager' );
	}

	/**
	 * The accepted copy's link.
	 *
	 * @return string
	 */
	private static function accepted_label() {
		return __( 'Download the accepted agreement', 'wpcredits-program-manager' );
	}

	/**
	 * A newer copy is waiting.
	 *
	 * @return string
	 */
	private static function waiting_sentence() {
		return __( 'A newer signed agreement is waiting for review. The agreement above stays in force until an Administrator accepts it.', 'wpcredits-program-manager' );
	}

	/**
	 * The newer copy's link.
	 *
	 * @return string
	 */
	private static function waiting_label() {
		return __( 'Download the copy waiting for review', 'wpcredits-program-manager' );
	}

	/**
	 * What replacing does to the standing agreement.
	 *
	 * @return string
	 */
	private static function replace_sentence() {
		return __( 'Replace it by uploading a newer signed copy. The agreement above stays in force until an Administrator accepts the new one.', 'wpcredits-program-manager' );
	}

	/**
	 * The Drive link's words.
	 *
	 * @return string
	 */
	private static function drive_label() {
		return __( 'Open the signed copy in Drive', 'wpcredits-program-manager' );
	}

	/**
	 * The card on the parts (design 6.5): Accepted leading, the day whole, how it was signed or recorded, the accepted copy;
	 * the newer copy waiting and the way to take it back, or the upload that replaces it; the Drive link for an
	 * Administrator.
	 *
	 * @param string $record_id  Institutions record ID.
	 * @param array  $summary    From `WPCPM_Institution_Agreement::summary()`.
	 * @param string $accepted   The day it was accepted, or ''.
	 * @param bool   $as_manager Whether the policy allowed the card on the manager ground.
	 */
	private static function render_parts( $record_id, array $summary, $accepted, $as_manager ) {
		// The tag says Accepted; the day follows it as a fact, so the word is not said twice (the old branch keeps
		// "Accepted on"). With no day recorded, that is said alone after the tag.
		$out = WPCPM_Institution_Panel::lede_parts(
			__( 'Accepted', 'wpcredits-program-manager' ),
			'green',
			'' !== $accepted ? WPCPM_UI::whole( $accepted ) : esc_html( self::not_recorded() )
		)
			. WPCPM_UI::intro( self::route_sentence( $summary ) );

		$file  = self::accepted_file( $summary );
		$drive = $as_manager ? self::drive_url( $record_id ) : '';

		// The accepted copy's row: its download, and for an Administrator the Drive link, the copy the program holds.
		$out .= WPCPM_Institution_Panel::row_of(
			array(
				$file ? WPCPM_Institution_Panel::download_button( $file, self::accepted_label() ) : '',
				'' !== $drive ? WPCPM_UI::button(
					array(
						'label'  => self::drive_label(),
						'kind'   => 'quiet',
						'href'   => $drive,
						'rel'    => 'noopener noreferrer',
						'target' => '_blank',
						'side'   => 'front',
					)
				) : '',
			)
		);

		$pending = self::pending( $summary );

		if ( $pending ) {
			$out .= WPCPM_UI::intro( self::waiting_sentence() )
				. WPCPM_Institution_Panel::row_of( array( WPCPM_Institution_Panel::download_button( $pending, self::waiting_label() ), WPCPM_Institution_Panel::withdraw_form_parts( $pending ) ) );
		} elseif ( WPCPM_Institution_Panel::may_act( $record_id ) ) {
			// "Replace it ..." is the upload form's intro, so it is drawn for whoever the upload is drawn for.
			$out .= WPCPM_Institution_Panel::upload_form_parts( $record_id, self::replace_sentence() );
		}

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the parts and in fill().
	}
}

// phpcs:enable Generic.Files.OneObjectStructurePerFile.MultipleFound
