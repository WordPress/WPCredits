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
 * **What Phase 3 added.** The controls the spec's table names are real here now: Generate and
 * Upload on `none` and `generated`, Download and Withdraw on `submitted`, Upload again on
 * `returned` and `revoked`, and the manager's review block for the queue. The sentence that
 * saves a long-standing partner from signing twice stays where it was, because the on-file
 * route is still the faster answer for the forty-odd institutions that signed years ago.
 *
 * **Every form here is drawn by whichever screen the reader is on, and gated by the same
 * fence.** The upload form appears on this panel, on the card at the foot of a settled
 * dashboard (a replacement, T10) and on the manager's institution row, and it is one method
 * that asks `WPCPM_Institution_Policy::decide()` itself rather than three copies trusting
 * three callers. The handlers on the other side ask again; a form nobody may post is not a
 * gate, it is a courtesy that saves the reader a refusal.
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
	 * Every outcome the agreement handlers can flash, in the words the reader gets.
	 *
	 * Kept here rather than in each screen, because the forms are drawn in four places (this
	 * panel, the card at the foot of a settled dashboard, the manager's institution row and
	 * the review queue) and land wherever they were pressed. Each row is
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

		return array(
			'agreement-on-file'            => array( 'success', __( 'The agreement is recorded as on file. The institution\'s account is open.', 'wpcredits-program-manager' ) ),
			'agreement-link'               => array( 'error', __( 'Nothing was recorded. Paste the Drive link to the folder or the file: it has to be an https address on drive.google.com or docs.google.com.', 'wpcredits-program-manager' ) ),
			'agreement-standing'           => array( 'error', __( 'Nothing was recorded. An accepted agreement already stands for this institution.', 'wpcredits-program-manager' ) ),
			'agreement-unknown'            => array( 'error', __( 'Nothing was recorded. That institution is not in the pipeline index; run a sync and try again.', 'wpcredits-program-manager' ) ),
			'agreement-busy'               => array( 'error', __( 'Nothing was saved. Either another write to this institution\'s agreement was in flight, from a sync or from somebody else pressing the same button, or this institution has used up the documents one day allows it to generate and upload. Try again in a moment, and tomorrow if it happens again.', 'wpcredits-program-manager' ) ),
			// Revoke and reinstate, T8 and T9. Named here with everything else the two agreement
			// forms can flash, because a slug with no row prints nothing at all: the button
			// redirects, the page looks untouched, and the manager presses it again.
			'agreement-revoked'            => array( 'success', __( 'The agreement is revoked. The institution\'s account is limited to the agreement panel from its next page load, and everybody there has been emailed your note.', 'wpcredits-program-manager' ) ),
			'agreement-revoke-note'        => array( 'error', __( 'Nothing was revoked. A note is required, between 20 and 2000 characters: it is emailed to the institution as the reason, so it has to say something.', 'wpcredits-program-manager' ) ),
			'agreement-not-accepted'       => array( 'error', __( 'Nothing was revoked. There is no accepted agreement to revoke for this institution.', 'wpcredits-program-manager' ) ),
			'agreement-not-revoked'        => array( 'error', __( 'Nothing was reinstated. There is no revoked agreement to put back for this institution.', 'wpcredits-program-manager' ) ),
			'agreement-reinstate-standing' => array( 'error', __( 'Nothing was reinstated. An accepted agreement already stands, so there is nothing for the revoked one to come back to.', 'wpcredits-program-manager' ) ),
			'agreement-reinstated'         => array( 'success', __( 'The agreement is back in force and the account is open again. Everybody at the institution has been emailed.', 'wpcredits-program-manager' ) ),
			'agreement-all-none'           => array( 'info', __( 'Nothing to record: every Confirmed institution already has an agreement recorded.', 'wpcredits-program-manager' ) ),
			'agreement-on-file-all'        => array( 'success', self::on_file_all_summary() ),
			'agreement-airtable'           => array( 'error', __( 'Airtable could not be updated, so nothing was recorded here either. The base is the record of this state, and the site does not open an account the base has not agreed to.', 'wpcredits-program-manager' ) ),
			'agreement-not-saved'          => array( 'error', __( 'Airtable was updated but the record on this site could not be written. Press Refresh on the Institutions screen: the next reconcile completes it.', 'wpcredits-program-manager' ) ),
			'agreement-later'              => array( 'info', __( 'Airtable and the site record were both written, but this institution\'s state was being rebuilt at that moment. The account opens after the next sync or Refresh.', 'wpcredits-program-manager' ) ),
			'agreement-uploaded'           => array( 'success', __( 'The signed agreement is uploaded. A program manager reviews it and you will get an email either way.', 'wpcredits-program-manager' ) ),
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
			'agreement-encrypted'          => array( 'error', __( 'Nothing was uploaded. That PDF is password protected, so a program manager could not open it. Save an unprotected copy and upload that instead.', 'wpcredits-program-manager' ) ),
			'agreement-launch'             => array( 'error', __( 'Nothing was uploaded. That PDF asks to run a program when it is opened, which a signed agreement has no reason to do. Print it to a new PDF and upload that instead.', 'wpcredits-program-manager' ) ),
			'agreement-in-review'          => array( 'error', __( 'Nothing was uploaded. A signed agreement from this institution is already waiting for review. Withdraw it first if it was the wrong file.', 'wpcredits-program-manager' ) ),
			'agreement-declare'            => array( 'error', __( 'Nothing was uploaded. Tick the box to say the document you are uploading is the signed one.', 'wpcredits-program-manager' ) ),
			'agreement-kind'               => array( 'error', __( 'Nothing was uploaded. Say whether this is the program\'s template or an agreement of your own: a program manager reads the two differently.', 'wpcredits-program-manager' ) ),
			'agreement-accepted'           => array( 'success', __( 'The agreement is accepted. The institution\'s account is open and everybody at the institution has been emailed.', 'wpcredits-program-manager' ) ),
			'agreement-returned'           => array( 'success', __( 'The agreement is returned. Everybody at the institution has been emailed your note, with your address to reply to.', 'wpcredits-program-manager' ) ),
			'agreement-withdrawn'          => array( 'success', __( 'The signed agreement is withdrawn and its file is deleted. Upload another whenever you are ready.', 'wpcredits-program-manager' ) ),
			'agreement-note'               => array( 'error', __( 'Nothing was returned. The note has to be between 20 and 2000 characters: it is the whole of what the institution is told, so it has to say what to change.', 'wpcredits-program-manager' ) ),
			'agreement-stage'              => array( 'error', __( 'Nothing was accepted. Airtable\'s Current Stage for this institution says the program is not going ahead with it. Change the stage in Airtable first: accepting is the program saying yes, and it must not say yes to a record it has said no to.', 'wpcredits-program-manager' ) ),
			'agreement-generated-later'    => array( 'info', __( 'The document was generated and downloaded, but the program\'s own record could not be updated at that moment. The next sync writes it. Nothing is lost and there is nothing to do.', 'wpcredits-program-manager' ) ),
			'agreement-name'               => array( 'error', __( 'Nothing was generated. The agreement needs the institution\'s name exactly as it should print on a document somebody signs. If you are not sure what to put, ask the program manager who has been in touch with your institution.', 'wpcredits-program-manager' ) ),
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

		$name = self::institution_name( $record_id );
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
			'<p class="wpcpm-agreement-panel__field"><label for="%1$s-drive">%2$s</label> <input type="url" class="wpcpm-agreement-panel__input" id="%1$s-drive" name="wpcpm_agreement_drive" required placeholder="%3$s" /></p>',
			esc_attr( $base ),
			esc_html__( 'Drive link to the folder or the file (required)', 'wpcredits-program-manager' ),
			esc_attr__( 'https://drive.google.com/...', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-agreement-panel__field"><label for="%1$s-signed">%2$s</label> <input type="date" class="wpcpm-agreement-panel__input" id="%1$s-signed" name="wpcpm_agreement_signed_on" /></p>',
			esc_attr( $base ),
			esc_html__( 'Date signed (optional)', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-agreement-panel__field"><label for="%1$s-where">%2$s</label> <input type="text" class="wpcpm-agreement-panel__input" id="%1$s-where" name="wpcpm_agreement_where" maxlength="%3$d" placeholder="%4$s" /></p>',
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
	private static function may_act( $record_id ) {
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
	 * `assets/js/forms.js`, registered as `wpcpm-forms`, and neither screen these forms are
	 * drawn on enqueues it today: not the institution dashboard, which enqueues its
	 * stylesheet alone, and not the Institutions screen, which enqueues `admin.js`. So the
	 * attribute is inert here and a second press posts a second time. What stops the second
	 * press is on the other side - the daily ceiling, the institution's own lock, and "one
	 * document in review at a time" - and the attribute is printed so that adding the
	 * enqueue is the whole of the work when a screen takes it on. Written down rather than
	 * promised, because a reader who believes the guard is running does not go looking for
	 * the enqueue that is missing.
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
		$url    = admin_url( 'admin-post.php' );
		$record = trim( (string) $record );

		if ( '' !== $record && current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$url = add_query_arg( array( WPCPM_Institution_Roster::ARG_VIEW => $record ), $url );
		}

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
	 * @param int $post_id Agreement post ID, a document in `submitted`.
	 */
	public static function render_review( $post_id ) {
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
		self::render_accept_form( $post_id, $name, $members );
		self::render_return_form( $post_id );

		echo '</section>';
	}

	/**
	 * Who uploaded the document, when, how big it is and which kind it claims to be.
	 *
	 * The size is here because it is the one fact a reviewer reads before opening anything:
	 * a two-page agreement that weighs nine megabytes is a photograph of every page, which
	 * is a slower read and a different conversation from a signed text PDF.
	 *
	 * @param array $facts From `WPCPM_Institution_Agreement::review_facts()`.
	 */
	private static function render_review_facts( array $facts ) {
		$kind = isset( $facts['kind'] ) ? (string) $facts['kind'] : '';

		printf(
			'<p class="wpcpm-review__facts">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: the uploader's name, 2: date, 3: file size. */
					__( 'Uploaded by %1$s on %2$s, %3$s.', 'wpcredits-program-manager' ),
					trim( (string) ( isset( $facts['uploaded_by'] ) ? $facts['uploaded_by'] : '' ) ),
					trim( (string) ( isset( $facts['uploaded_at'] ) ? $facts['uploaded_at'] : '' ) ),
					size_format( (int) ( isset( $facts['size'] ) ? $facts['size'] : 0 ) )
				)
			)
		);

		printf(
			'<p class="wpcpm-review__kind">%s</p>',
			esc_html(
				WPCPM_Institution_Agreement::KIND_OWN === $kind
					? __( 'The institution says this is an agreement of its own, not the program\'s template.', 'wpcredits-program-manager' )
					: __( 'The institution says this is the program\'s template, signed.', 'wpcredits-program-manager' )
			)
		);
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
	 */
	private static function render_checklist( array $facts, $airtable_name ) {
		$own      = WPCPM_Institution_Agreement::KIND_OWN === (string) ( isset( $facts['kind'] ) ? $facts['kind'] : '' );
		$version  = trim( (string) ( isset( $facts['template_version'] ) ? $facts['template_version'] : '' ) );
		$on_paper = trim( (string) ( isset( $facts['name_on_document'] ) ? $facts['name_on_document'] : '' ) );

		echo '<ul class="wpcpm-review__checklist">';

		if ( $own ) {
			printf( '<li>%s</li>', esc_html__( 'Read the whole document. There is no shortcut for this one.', 'wpcredits-program-manager' ) );
			printf( '<li>%s</li>', esc_html__( 'It names the WordPress Foundation and the institution.', 'wpcredits-program-manager' ) );
			printf( '<li>%s</li>', esc_html__( 'It does not commit the program to anything the program\'s own template does not.', 'wpcredits-program-manager' ) );
			echo '</ul>';

			return;
		}

		printf(
			'<li>%s</li>',
			esc_html(
				sprintf(
					/* translators: %s: template version, or a phrase saying none was recorded. */
					__( 'The footer on the signed copy names template version %s.', 'wpcredits-program-manager' ),
					'' !== $version ? $version : __( 'nothing this site recorded', 'wpcredits-program-manager' )
				)
			)
		);
		printf(
			'<li>%s</li>',
			esc_html(
				sprintf(
					/* translators: 1: the name printed on the document, 2: Airtable's Name for the institution. */
					__( 'The name on the document is %1$s. Airtable\'s Name for this institution is %2$s.', 'wpcredits-program-manager' ),
					'' !== $on_paper ? $on_paper : __( '(not recorded)', 'wpcredits-program-manager' ),
					$airtable_name
				)
			)
		);
		printf( '<li>%s</li>', esc_html__( 'The institution\'s signature block is filled in.', 'wpcredits-program-manager' ) );

		echo '</ul>';
	}

	/**
	 * What the courtesy scan noticed, and what it is worth.
	 *
	 * @param array $flags The names the scan recorded, `/JavaScript` and its kind.
	 */
	private static function render_flags( array $flags ) {
		$flags = array_values( array_filter( array_map( 'strval', $flags ) ) );

		printf(
			'<p class="wpcpm-review__flags">%s</p>',
			esc_html(
				$flags
					? sprintf(
						/* translators: %s: a comma-separated list of PDF feature names. */
						__( 'The scan noticed these in the file: %s.', 'wpcredits-program-manager' ),
						implode( ', ', $flags )
					)
					: __( 'The scan noticed none of the features it looks for in the file.', 'wpcredits-program-manager' )
			)
		);

		printf(
			'<p class="wpcpm-review__courtesy">%s</p>',
			esc_html__( 'The scan is a courtesy and not evidence: a PDF can carry things a bounded scan will not find. What protects you is that this site never opens the file in your browser, and that the download is handed to a viewer of your own choosing.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * Accept, with a confirm that names everything that leaves the building.
	 *
	 * The institution, `Confirmed`, and how many people are emailed: three consequences the
	 * reviewer cannot see from the button, one of which reaches other people's inboxes. The
	 * last sentence says it can be revoked from here, because a dialog listing three
	 * irreversible-sounding things is a dialog people dismiss without reading.
	 *
	 * @param int    $post_id Agreement post ID.
	 * @param string $name    Institution name, as printed.
	 * @param int    $members How many people are emailed.
	 */
	private static function render_accept_form( $post_id, $name, $members ) {
		self::form_start(
			'wpcpm-review__form wpcpm-review__form--accept',
			WPCPM_Institution_Agreement::ACTION_ACCEPT,
			WPCPM_Institution_Agreement::ACTION_ACCEPT . '_' . (int) $post_id,
			__( 'Accepting...', 'wpcredits-program-manager' ),
			false
		);

		printf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id );

		printf(
			'<button type="submit" class="wpcpm-button" onclick="return confirm(%1$s)">%2$s</button>',
			esc_attr(
				wp_json_encode(
					sprintf(
						/* translators: 1: institution name, 2: number of people emailed. */
						_n(
							'Accept the signed agreement from %1$s? This opens their account on the site, sets Current Stage to Confirmed in Airtable, and emails the %2$s person at the institution. You can revoke it from here later.',
							'Accept the signed agreement from %1$s? This opens their account on the site, sets Current Stage to Confirmed in Airtable, and emails the %2$s people at the institution. You can revoke it from here later.',
							(int) $members,
							'wpcredits-program-manager'
						),
						$name,
						number_format_i18n( (int) $members )
					)
				)
			),
			esc_html__( 'Accept it', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * Return, with the note that is the whole of what the institution is told.
	 *
	 * The one free-text field anywhere on this page, and the reason it is allowed where the
	 * upload form's is not: this text is written to be read by a person, is mailed verbatim,
	 * and is the difference between "your agreement was returned" and an institution that
	 * knows page 4 is unsigned.
	 *
	 * @param int $post_id Agreement post ID.
	 */
	private static function render_return_form( $post_id ) {
		$base = 'wpcpm-return-' . (int) $post_id;

		self::form_start(
			'wpcpm-review__form wpcpm-review__form--return',
			WPCPM_Institution_Agreement::ACTION_RETURN,
			WPCPM_Institution_Agreement::ACTION_RETURN . '_' . (int) $post_id,
			__( 'Returning...', 'wpcredits-program-manager' ),
			false
		);

		printf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id );

		printf(
			'<p class="wpcpm-review__note"><label for="%1$s">%2$s</label> <textarea class="wpcpm-agreement-panel__input" id="%1$s" name="wpcpm_agreement_note" rows="4" minlength="20" maxlength="2000" required></textarea></p>',
			esc_attr( $base ),
			esc_html__( 'What has to change, in your own words. This is emailed to everybody at the institution exactly as you write it, with your address to reply to.', 'wpcredits-program-manager' )
		);

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Return it with this note', 'wpcredits-program-manager' )
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
				self::render_regenerate_form( $record_id, (int) $summary['generated_id'] );
				self::render_steps( $record_id, $row );
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
				self::render_download_link(
					(int) $summary['pending_id'],
					__( 'Download the copy we hold', 'wpcredits-program-manager' )
				);
				self::render_withdraw_form( (int) $summary['pending_id'] );
				break;

			case WPCPM_Institution_Agreement::SUMMARY_RETURNED:
				printf(
					'<p class="wpcpm-agreement-panel__lede">%s</p>',
					esc_html__( 'A program manager returned your agreement, with this note:', 'wpcredits-program-manager' )
				);
				self::render_note( $record_id, WPCPM_Institution_Agreement::STATE_RETURNED );
				self::render_upload_form(
					$record_id,
					__( 'Upload the corrected agreement, signed, as a PDF.', 'wpcredits-program-manager' )
				);
				self::render_contact( $row, $can_manage );
				break;

			case WPCPM_Institution_Agreement::SUMMARY_REVOKED:
				printf(
					'<p class="wpcpm-agreement-panel__lede">%s</p>',
					esc_html__( 'The program revoked this institution\'s agreement. The account reaches this page and nothing else until a new agreement is accepted. The note the program left:', 'wpcredits-program-manager' )
				);
				self::render_note( $record_id, WPCPM_Institution_Agreement::STATE_REVOKED );
				self::render_upload_form(
					$record_id,
					__( 'Upload a new signed agreement as a PDF. A program manager reviews it the same way as the first one.', 'wpcredits-program-manager' )
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
				self::render_steps( $record_id, $row );
				self::render_on_file_note( $row, $can_manage );
				break;
		}
	}

	/**
	 * The three numbered steps, each with the control that does it.
	 *
	 * Printed for `none` and for `generated`, which is the same journey one step further
	 * along. The first step names both routes, because an institution-specific agreement is
	 * a real choice with a real cost, and a reader who learns about it after generating the
	 * template has been told too late.
	 *
	 * The forms sit inside the steps they belong to rather than under the list, so that the
	 * numbers mean something: step 1 is a control, step 2 is a thing done on paper, step 3
	 * is a control. A list of three sentences followed by two unlabelled forms is the same
	 * information with the ordering taken out of it.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $row       The institution's index row.
	 */
	private static function render_steps( $record_id, array $row ) {
		echo '<ol class="wpcpm-agreement-panel__steps">';

		echo '<li>';
		printf(
			'<p class="wpcpm-agreement-panel__step">%s</p>',
			esc_html__( 'Get the agreement: generate the program\'s template, or upload an agreement of your own. A program manager reads an institution-specific agreement in full, so that path takes longer.', 'wpcredits-program-manager' )
		);
		self::render_generate_form( $record_id, $row );
		echo '</li>';

		printf(
			'<li><p class="wpcpm-agreement-panel__step">%s</p></li>',
			esc_html__( 'Sign it. It needs somebody who can commit the institution.', 'wpcredits-program-manager' )
		);

		echo '<li>';
		printf(
			'<p class="wpcpm-agreement-panel__step">%s</p>',
			esc_html__( 'Upload the signed PDF here. A program manager reviews it and you get an email either way.', 'wpcredits-program-manager' )
		);
		self::render_upload_form( $record_id, '' );
		echo '</li>';

		echo '</ol>';
	}

	/**
	 * The form that generates the program's template as a print document.
	 *
	 * The name is editable and pre-filled from the pipeline index, which is Airtable's `Name`
	 * as the last sync read it. Editable because the base's spelling is a program manager's
	 * shorthand often enough ("Univ. Example", a trailing space, an English rendering of a
	 * name that is signed in its own language) and this string is printed twice on a document
	 * a rector signs. Pre-filled because an empty box in front of somebody who has never seen
	 * the agreement is a worse default than a name they can correct.
	 *
	 * The language select is drawn only when a second template file exists, so today's single
	 * English template is not presented as a choice nobody has.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $row       The institution's index row.
	 */
	private static function render_generate_form( $record_id, array $row ) {
		if ( ! self::may_act( $record_id ) ) {
			return;
		}

		$base      = 'wpcpm-generate-' . sanitize_html_class( $record_id );
		$name      = trim( (string) ( isset( $row['name'] ) ? $row['name'] : '' ) );
		$name      = mb_substr( $name, 0, self::MAX_NAME );
		$languages = (array) WPCPM_Agreement_Template::languages();

		// The record travels on the action URL for a manager and nowhere else. `generate()`
		// resolves the institution through `resolve_institution()` and then keys the nonce to
		// what it resolved, and since Phase 3's review that is the only way it can be told:
		// the posted field it used to read is gone from that class, so a form that sent one
		// would name an institution nothing there can hear.
		self::form_start(
			'wpcpm-agreement-panel__form wpcpm-agreement-panel__form--generate',
			WPCPM_Agreement_Generate::ACTION_GENERATE,
			WPCPM_Agreement_Generate::ACTION_GENERATE . '_' . $record_id,
			__( 'Generating...', 'wpcredits-program-manager' ),
			false,
			$record_id
		);

		printf(
			'<p class="wpcpm-agreement-panel__field"><label for="%1$s-name">%2$s</label> <input type="text" class="wpcpm-agreement-panel__input" id="%1$s-name" name="wpcpm_agreement_name" value="%3$s" maxlength="%4$d" required /></p>',
			esc_attr( $base ),
			esc_html__( 'The institution\'s name, exactly as it should print on the agreement', 'wpcredits-program-manager' ),
			esc_attr( $name ),
			(int) self::MAX_NAME
		);

		if ( count( $languages ) > 1 ) {
			printf(
				'<p class="wpcpm-agreement-panel__field"><label for="%1$s-language">%2$s</label> <select class="wpcpm-agreement-panel__input" id="%1$s-language" name="wpcpm_agreement_language">',
				esc_attr( $base ),
				esc_html__( 'Language', 'wpcredits-program-manager' )
			);

			foreach ( $languages as $language ) {
				printf(
					'<option value="%1$s">%2$s</option>',
					esc_attr( $language ),
					esc_html( $language )
				);
			}

			echo '</select></p>';
		}

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Generate the agreement', 'wpcredits-program-manager' )
		);

		printf(
			'<p class="wpcpm-agreement-panel__hint">%s</p>',
			esc_html__( 'It opens as a page your browser can save as a PDF or print. Nothing is sent to the program until you upload the signed copy.', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * "Regenerate the template as they saw it", from a generated document's own record.
	 *
	 * The name and the language the document was made with travel as hidden fields, so the
	 * same three inputs (template version, language, name) produce the same bytes: somebody
	 * who printed the agreement, lost the file and came back a week later gets the document
	 * they were reading, not a fresh one under a name a sync has since changed.
	 *
	 * The generated post's ID travels with them for the same reason, so a handler that would
	 * rather rebuild from the post than trust the form has it. The institution does not
	 * travel as a field at all: `generate()` reads no record from the form, and for a
	 * manager it comes back on the action URL, where the resolver looks.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param int    $post_id   The generated document's post ID.
	 */
	private static function render_regenerate_form( $record_id, $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id || ! self::may_act( $record_id ) ) {
			return;
		}

		self::form_start(
			'wpcpm-agreement-panel__regenerate',
			WPCPM_Agreement_Generate::ACTION_GENERATE,
			WPCPM_Agreement_Generate::ACTION_GENERATE . '_' . $record_id,
			__( 'Generating...', 'wpcredits-program-manager' ),
			false,
			$record_id
		);

		printf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id );
		printf(
			'<input type="hidden" name="wpcpm_agreement_name" value="%s" />',
			esc_attr( (string) get_post_meta( $post_id, WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT, true ) )
		);
		printf(
			'<input type="hidden" name="wpcpm_agreement_language" value="%s" />',
			esc_attr( (string) get_post_meta( $post_id, WPCPM_Institution_Agreement::META_LANGUAGE, true ) )
		);

		printf(
			'<button type="submit" class="wpcpm-link-button">%s</button>',
			esc_html__( 'Open that document again', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * The upload form: the signed PDF, which kind it is, and the declaration.
	 *
	 * Public because it is drawn in four places and must be identical in all of them: this
	 * panel on three of its five states, the card at the foot of a settled dashboard (a
	 * replacement, T10), and the manager's institution row through `render_manager_upload()`.
	 * It asks the fence itself rather than trusting each caller, for the reason `render()`
	 * gives: a later screen that forgets the check is what the fence is for.
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
	 */
	public static function render_upload_form( $record_id, $intro = '' ) {
		$record_id = trim( (string) $record_id );

		if ( ! self::may_act( $record_id ) ) {
			return;
		}

		$base   = 'wpcpm-upload-' . sanitize_html_class( $record_id );
		$max_mb = max( 1, (int) WPCPM_Settings::get_value( 'agreement_max_mb', 10 ) );

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
			esc_html(
				sprintf(
					/* translators: %s: the largest upload the site accepts, in megabytes. */
					__( 'The signed agreement, as a PDF of up to %s MB', 'wpcredits-program-manager' ),
					number_format_i18n( $max_mb )
				)
			)
		);

		echo '<fieldset class="wpcpm-agreement-panel__choices">';
		printf(
			'<legend>%s</legend>',
			esc_html__( 'Which agreement is this?', 'wpcredits-program-manager' )
		);
		printf(
			'<p><label for="%1$s-kind-template"><input type="radio" id="%1$s-kind-template" name="wpcpm_agreement_kind" value="%2$s" checked="checked" /> %3$s</label></p>',
			esc_attr( $base ),
			esc_attr( WPCPM_Institution_Agreement::KIND_TEMPLATE ),
			esc_html__( 'The program\'s template, signed', 'wpcredits-program-manager' )
		);
		printf(
			'<p><label for="%1$s-kind-own"><input type="radio" id="%1$s-kind-own" name="wpcpm_agreement_kind" value="%2$s" /> %3$s</label></p>',
			esc_attr( $base ),
			esc_attr( WPCPM_Institution_Agreement::KIND_OWN ),
			esc_html__( 'An agreement of our own, signed. A program manager reads this one in full, so it takes longer.', 'wpcredits-program-manager' )
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
			esc_html__( 'This document is signed by somebody who can commit the institution.', 'wpcredits-program-manager' )
		);

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
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
	 * because the reader is.
	 *
	 * Capability-checked *and* fenced here rather than at the call site, so the Institutions
	 * screen prints it with one call and cannot print it to the wrong person by forgetting
	 * either check. The fence is asked before the heading and not only inside the form: a
	 * viewer the policy refuses would otherwise be handed "Upload a signed agreement on the
	 * institution's behalf" with nothing under it, which reads as a broken screen rather
	 * than as a gate, and is the shape `render()` and `render_review()` are written to
	 * avoid.
	 *
	 * **It decides everything for itself, because its caller is a table.** The Institutions
	 * screen draws one of these per institution row, from a loop that already holds a name, a
	 * summary and a record ID and could hand any of them over. Nothing here is taken from the
	 * caller but the record ID, and that is checked for shape before it is used: the
	 * capability, the fence and the state below are all read here. A row rendering a form its
	 * handler would refuse is worse than a row rendering nothing, and the screen is not the
	 * place where that is known.
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
			self::render_withdraw_form( $pending );

			return;
		}

		self::render_upload_form(
			$record_id,
			__( 'For a signed copy that reached the program by email. It lands in the review queue exactly as an institution\'s own upload does, and everybody at the institution is emailed that it arrived.', 'wpcredits-program-manager' )
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
	 */
	public static function render_download_link( $post_id, $label ) {
		$post_id = (int) $post_id;

		if ( ! $post_id || ! self::may_act_on_post( $post_id ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-agreement-panel__download"><a href="%1$s" rel="nofollow">%2$s</a></p>',
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
	 * member or a manager on the member's behalf, so the control belongs on the manager's
	 * institution row as much as on the panel; what does not carry across is the wording. A
	 * manager pressing this deletes somebody else's document, and the two facts they need and
	 * a member does not are that the institution is not emailed and that nothing will be
	 * reviewed until the institution uploads again. So the confirm is written for whoever the
	 * policy says is looking, and it names the institution the way every other manager's
	 * confirm in this module does: a person acting on forty rows should not have to remember
	 * which one the button belongs to.
	 *
	 * @param int $post_id Agreement post ID.
	 */
	public static function render_withdraw_form( $post_id ) {
		$post_id  = (int) $post_id;
		$decision = $post_id ? self::post_decision( $post_id ) : null;

		if ( null === $decision ) {
			return;
		}

		$as_manager = isset( $decision['ground'] ) && WPCPM_Institution_Policy::GROUND_MANAGER === (string) $decision['ground'];

		self::form_start(
			'wpcpm-agreement-panel__form wpcpm-agreement-panel__form--withdraw',
			WPCPM_Institution_Agreement::ACTION_WITHDRAW,
			WPCPM_Institution_Agreement::ACTION_WITHDRAW . '_' . $post_id,
			__( 'Withdrawing...', 'wpcredits-program-manager' ),
			false
		);

		printf( '<input type="hidden" name="wpcpm_agreement_post" value="%d" />', (int) $post_id );

		printf(
			'<button type="submit" class="wpcpm-button" onclick="return confirm(%1$s)">%2$s</button>',
			esc_attr( wp_json_encode( self::withdraw_confirm( $post_id, $as_manager ) ) ),
			esc_html(
				$as_manager
					? __( 'Withdraw it on the institution\'s behalf', 'wpcredits-program-manager' )
					: __( 'Withdraw it', 'wpcredits-program-manager' )
			)
		);

		echo '</form>';
	}

	/**
	 * What the withdraw dialog asks, in the words of whoever is being asked.
	 *
	 * The member's copy is about their own document and their own next step. The manager's
	 * copy is about another organisation's document: it names the institution, it says that
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
	 * institution would be worse than one naming a record nobody recognises, and the index is
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
		self::render_download( $summary );
		self::render_replace( $record_id, $summary );

		if ( $as_manager ) {
			self::render_drive_link( $record_id );
		}

		echo '</section>';
	}

	/**
	 * The accepted copy itself, when this site holds one.
	 *
	 * A legacy row has no file at all: what the program holds is a copy in Drive, which the
	 * route line above has just said, and which a manager reaches through the link at the
	 * foot. So the link is drawn from the file rather than from the state, and an institution
	 * whose agreement predates this site is not offered a download that would 404.
	 *
	 * @param array $summary From `WPCPM_Institution_Agreement::summary()`.
	 */
	private static function render_download( array $summary ) {
		$post_id = isset( $summary['agreement_id'] ) ? (int) $summary['agreement_id'] : 0;
		$file    = $post_id ? get_post_meta( $post_id, WPCPM_Institution_Agreement::META_FILE, true ) : '';

		if ( ! $post_id || ! is_array( $file ) || empty( $file['path'] ) ) {
			return;
		}

		WPCPM_Institution_Panel::render_download_link(
			$post_id,
			__( 'Download the accepted agreement', 'wpcredits-program-manager' )
		);
	}

	/**
	 * Replacing the standing agreement with a newer signed one (T10).
	 *
	 * The sentence that has to be here is that the current agreement stays in force: an
	 * institution renewing under a new rector should not be able to close its own account by
	 * uploading a document nobody has read yet. Nothing about the gate changes until a
	 * program manager accepts the replacement.
	 *
	 * Only one document is in review at a time, so when a replacement is already waiting the
	 * form would refuse. The card says so instead, and offers the way back out of that state:
	 * the copy itself, and the control that withdraws it.
	 *
	 * @param string $record_id Institutions record ID.
	 * @param array  $summary   From `WPCPM_Institution_Agreement::summary()`.
	 */
	private static function render_replace( $record_id, array $summary ) {
		$pending = isset( $summary['pending_id'] ) ? (int) $summary['pending_id'] : 0;

		if ( $pending ) {
			printf(
				'<p class="wpcpm-agreement-card__replace">%s</p>',
				esc_html__( 'A newer signed agreement is waiting for review. The agreement above stays in force until a program manager accepts it.', 'wpcredits-program-manager' )
			);

			WPCPM_Institution_Panel::render_download_link(
				$pending,
				__( 'Download the copy waiting for review', 'wpcredits-program-manager' )
			);
			WPCPM_Institution_Panel::render_withdraw_form( $pending );

			return;
		}

		printf(
			'<p class="wpcpm-agreement-card__replace">%s</p>',
			esc_html__( 'Replace it by uploading a newer signed copy. The agreement above stays in force until a program manager accepts the new one.', 'wpcredits-program-manager' )
		);

		WPCPM_Institution_Panel::render_upload_form( $record_id, '' );
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
