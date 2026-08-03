<?php
/**
 * Students module — the four details a student maintains themselves.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets a student edit their own contact details, and writes them back to Airtable.
 *
 * Four fields, and only four: their WordPress.org profile, Slack, contribution
 * team and personal website. Everything else on the page — status, dates, institution,
 * tutor, mentor — is program administration and belongs to whoever runs it. These
 * four are facts about the student that only the student reliably knows, and chasing
 * them by email is how they stay out of date.
 *
 * **Airtable is the record, not WordPress.** The save goes to Airtable first and the
 * cached `wpcpm_student_program` meta is updated only if that succeeded. Writing the
 * meta first would show the student their change and then lose it on the next sync,
 * which is worse than refusing the save — the field would silently revert and nobody
 * would know which value was real.
 *
 * The three text fields are written straight through. `Main Contribution Team` is a
 * *linked-record* field, so it takes an array of record IDs from the Contribution areas
 * table rather than a name; the form offers the teams the sync has already cataloged,
 * which is why it is a select and not a text box.
 */
class WPCPM_Student_Profile {

	const ACTION_SAVE = 'wpcpm_save_student_profile';

	/**
	 * Anchor the editable rows and their messages sit on.
	 *
	 * The program table itself: the four editable values live in it, each behind its own
	 * "Edit" disclosure, so a save returns to the table rather than to a separate form.
	 */
	const ANCHOR = 'wpcpm-program';

	/** Longest Slack handle accepted, which is well past Slack's own limit. */
	const MAX_SLACK = 100;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * The editable fields, and how each one maps to Airtable.
	 *
	 * `program_key` is where the value lives in the cached program array; `field` is
	 * the Airtable column in the Students Reports table.
	 *
	 * @return array[]
	 */
	public static function fields() {
		$fields = WPCPM_Mentors_Sync::fields();

		return array(
			'profile' => array(
				'label'       => __( 'WordPress.org', 'wpcredits-program-manager' ),
				'program_key' => 'profile',
				'field'       => $fields['report_profile'],
				'type'        => 'url',
				'placeholder' => 'https://profiles.wordpress.org/your-username/',
				'help'        => __( 'Your profile page, or just your username.', 'wpcredits-program-manager' ),
			),
			'slack'   => array(
				'label'       => __( 'Slack', 'wpcredits-program-manager' ),
				'program_key' => 'slack',
				'field'       => $fields['report_slack'],
				'type'        => 'text',
				'placeholder' => '@yourname',
				'help'        => __( 'Your display name in the Making WordPress Slack.', 'wpcredits-program-manager' ),
			),
			'team'    => array(
				'label'       => __( 'Contribution team', 'wpcredits-program-manager' ),
				'program_key' => 'team',
				'field'       => $fields['report_team'],
				'type'        => 'team',
				'help'        => __( 'The team you are contributing to.', 'wpcredits-program-manager' ),
			),
			'website' => array(
				'label'       => __( 'Personal website', 'wpcredits-program-manager' ),
				'program_key' => 'website',
				'field'       => $fields['report_website'],
				'type'        => 'url',
				'placeholder' => 'https://example.com',
				'help'        => __( 'The site you built on the program, if you have one.', 'wpcredits-program-manager' ),
			),
		);
	}

	/**
	 * Whether a user may edit a student's own details.
	 *
	 * The student themselves, or a program manager. A mentor cannot — these are the
	 * student's own facts, and a mentor editing them would be filling in a form on
	 * somebody else's behalf without their knowing.
	 *
	 * @param int              $student_id Student user ID.
	 * @param int|WP_User|null $user       Optional user; defaults to the current user.
	 * @return bool
	 */
	public static function user_can_edit( $student_id, $user = null ) {
		$user = WPCPM_Roles::resolve_user( $user );

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}

		if ( user_can( $user->ID, WPCPM_Roles::CAP_MANAGE ) ) {
			return true;
		}

		return ( (int) $user->ID === (int) $student_id )
			&& WPCPM_Students_Dashboard::is_student( $user );
	}

	/**
	 * Save the form.
	 */
	public static function handle_save() {
		$student_id = isset( $_POST['student'] ) ? absint( wp_unslash( $_POST['student'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.

		check_admin_referer( self::ACTION_SAVE . '_' . $student_id );

		if ( ! self::user_can_edit( $student_id ) ) {
			wp_die( esc_html__( 'You cannot change those details.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Mentor_Calls::student_record( $student_id );

		if ( '' === $record ) {
			self::bounce( 'no-record' );
		}

		$program = WPCPM_Students_Sync::get_program( $student_id );
		$posted  = isset( $_POST['details'] ) && is_array( $_POST['details'] )
			? wp_unslash( $_POST['details'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is validated below.
			: array();

		$cells   = array();
		$changed = array();

		foreach ( self::fields() as $key => $spec ) {
			if ( ! isset( $posted[ $key ] ) ) {
				continue;
			}

			$raw = is_scalar( $posted[ $key ] ) ? (string) $posted[ $key ] : '';

			if ( 'team' === $spec['type'] ) {
				$value = self::clean_team( $raw );

				// A linked-record field takes an array of record IDs. An empty array is
				// how the link is cleared; a bare empty string would be rejected.
				$cells[ $spec['field'] ] = ( '' === $value ) ? array() : array( $value );

				$changed[ $key ] = ( '' === $value )
					? ''
					: WPCPM_Mentors_Sync::resolve_stored( $value, 'teams' );

				continue;
			}

			$value = ( 'url' === $spec['type'] )
				? self::clean_url( $raw )
				: sanitize_text_field( mb_substr( $raw, 0, self::MAX_SLACK ) );

			$cells[ $spec['field'] ] = $value;
			$changed[ $key ]         = $value;
		}

		if ( empty( $cells ) ) {
			self::bounce( 'nothing' );
		}

		$airtable = new WPCPM_Airtable( WPCPM_Settings::get() );
		$settings = WPCPM_Settings::get();

		$result = $airtable->update_records(
			$settings['reports_table'],
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			// The message names the missing scope when that is the cause, which is the
			// most common failure here — the token is usually read-only at first.
			self::bounce( 'airtable', $result->get_error_message() );
		}

		// Only now. Airtable took it, so the cache can safely agree with it until the
		// next sync rewrites the whole row anyway.
		foreach ( $changed as $key => $value ) {
			$spec = self::fields()[ $key ];

			$program[ $spec['program_key'] ] = $value;
		}

		// `username` is derived from the profile URL everywhere else, so it has to be
		// re-derived here or the page would show the new URL beside the old handle.
		if ( isset( $changed['profile'] ) ) {
			$program['username'] = WPCPM_Mentors_Sync::wporg_username( $changed['profile'] );
		}

		update_user_meta( $student_id, WPCPM_Students_Sync::META_PROGRAM, $program );

		self::bounce( 'saved' );
	}

	/**
	 * A submitted team value, if it is a team Airtable knows.
	 *
	 * The select's values are record IDs, so anything that is not one of the cataloged
	 * IDs is refused rather than passed to Airtable — a linked-record write with an
	 * unknown ID is an error at best and a link to the wrong record at worst.
	 *
	 * @param string $value Posted value.
	 * @return string Record ID, or an empty string to clear the link.
	 */
	private static function clean_team( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		return isset( WPCPM_Contribution_Teams::options()[ $value ] ) ? $value : '';
	}

	/**
	 * A submitted URL, normalized the way the rest of the plugin normalizes them.
	 *
	 * Airtable's `url` fields hold scheme-less values in practice, and a scheme-less
	 * `href` is a path on this site — so the scheme is added here rather than left for
	 * the renderer to guess. An empty submission clears the field, which is a legitimate
	 * edit and must not be turned into the string "https://".
	 *
	 * @param string $value Posted value.
	 * @return string
	 */
	private static function clean_url( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$value = WPCPM_Mentors_Dashboard::normalize_url( $value );

		return (string) esc_url_raw( $value );
	}

	/**
	 * Back to the student page.
	 *
	 * @param string $status Outcome flag.
	 * @param string $detail Optional detail, carried for error messages.
	 */
	private static function bounce( $status, $detail = '' ) {
		$page = WPCPM_Students_Dashboard::page_url();

		if ( '' === $page ) {
			$page = home_url( '/' );
		}

		// Both the outcome and the reason travel in the flash. The reason used to be
		// url-encoded into the address bar, which made for a URL a screen wide *and* left a
		// stale Airtable error on the page after every reload.
		WPCPM_Flash::set(
			'details',
			array(
				'status' => $status,
				'why'    => wp_strip_all_tags( $detail ),
			)
		);

		$args = array();

		// Keep a manager on the student they were editing. The nonce for this request was
		// verified by the handler that called us.
		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			$viewing = WPCPM_Request::posted_id( 'student' );

			if ( $viewing ) {
				$args['wpcpm_student_view'] = $viewing;
			}
		}

		wp_safe_redirect( add_query_arg( $args, $page ) . '#' . self::ANCHOR );
		exit;
	}

	/**
	 * The outcome message for the current request, or an empty array.
	 *
	 * @return array{0:string,1:string}|array
	 */
	public static function message() {
		$flash  = WPCPM_Flash::take( 'details' );
		$flash  = is_array( $flash ) ? $flash : array();
		$status = sanitize_key( isset( $flash['status'] ) ? (string) $flash['status'] : '' );

		if ( 'airtable' === $status ) {
			$why = sanitize_text_field( isset( $flash['why'] ) ? (string) $flash['why'] : '' );

			return array(
				'error',
				'' !== $why
					/* translators: %s: the error Airtable returned. */
					? sprintf( __( 'Airtable refused the change: %s', 'wpcredits-program-manager' ), $why )
					: __( 'Airtable refused the change, so nothing was saved.', 'wpcredits-program-manager' ),
			);
		}

		$messages = array(
			'saved'     => array( 'success', __( 'Saved, and written back to the program records.', 'wpcredits-program-manager' ) ),
			'nothing'   => array( 'error', __( 'Nothing was submitted, so nothing changed.', 'wpcredits-program-manager' ) ),
			'no-record' => array( 'error', __( 'Your account is not linked to a program record yet, so there is nothing to write to.', 'wpcredits-program-manager' ) ),
		);

		return isset( $messages[ $status ] ) ? $messages[ $status ] : array();
	}

	/**
	 * Print the outcome of the last save, if there was one.
	 *
	 * Called by the program section, which now owns the editable values.
	 */
	public static function render_message() {
		$message = self::message();

		if ( empty( $message ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-notes__message is-%1$s" role="status">%2$s</p>',
			esc_attr( $message[0] ),
			esc_html( $message[1] )
		);
	}

	/**
	 * Render the "Edit" control for one field, inline in the program table.
	 *
	 * One small form per field rather than one form for all four. It costs a little
	 * markup and buys two things: the value being changed is right next to the value as
	 * it stands, and a save touches only the field that was edited — `handle_save()`
	 * iterates the *posted* keys, so an untouched field is never written to Airtable at
	 * all. A single form would rewrite all four on every save, which turns a typo in one
	 * into a rewrite of the others.
	 *
	 * A native `<details>`, so it opens without JavaScript.
	 *
	 * @param string $key        Field key.
	 * @param array  $program    The student's cached program array.
	 * @param int    $student_id Whose details these are.
	 */
	public static function edit_control( $key, array $program, $student_id ) {
		$fields = self::fields();

		if ( ! isset( $fields[ $key ] ) || ! self::user_can_edit( $student_id ) ) {
			return;
		}

		$spec    = $fields[ $key ];
		$current = isset( $program[ $spec['program_key'] ] ) ? (string) $program[ $spec['program_key'] ] : '';
		$id      = 'wpcpm-edit-' . sanitize_html_class( $key );
		$teams   = ( 'team' === $spec['type'] ) ? WPCPM_Contribution_Teams::options() : array();

		echo '<details class="wpcpm-edit">';
		printf(
			'<summary class="wpcpm-edit__toggle">%1$s<span class="screen-reader-text"> %2$s</span></summary>',
			esc_html__( 'Edit', 'wpcredits-program-manager' ),
			esc_html( $spec['label'] )
		);

		echo '<div class="wpcpm-edit__body">';

		if ( 'team' === $spec['type'] && empty( $teams ) ) {
			// No catalog means no safe way to write a linked record, so the control is
			// withheld and the reason given rather than offering a select that cannot save.
			printf(
				'<p class="wpcpm-edit__note">%s</p>',
				esc_html__( 'The team list has not been read from Airtable yet. Run a sync and this becomes editable.', 'wpcredits-program-manager' )
			);
			echo '</div></details>';

			return;
		}

		printf(
			'<form class="wpcpm-edit__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving…', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE . '_' . (int) $student_id );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '" />';
		printf( '<input type="hidden" name="student" value="%d" />', (int) $student_id );

		printf(
			'<label class="screen-reader-text" for="%1$s">%2$s</label>',
			esc_attr( $id ),
			esc_html( $spec['label'] )
		);

		if ( 'team' === $spec['type'] ) {
			// Matched by *name*, because the cached value is a name — the record ID is not
			// kept on the student's row.
			$selected = '';

			foreach ( $teams as $record_id => $team_name ) {
				if ( 0 === strcasecmp( trim( $team_name ), trim( $current ) ) ) {
					$selected = $record_id;
					break;
				}
			}

			printf( '<select id="%1$s" name="details[%2$s]">', esc_attr( $id ), esc_attr( $key ) );
			printf( '<option value="">%s</option>', esc_html__( '— not set —', 'wpcredits-program-manager' ) );
			foreach ( $teams as $record_id => $team_name ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $record_id ),
					selected( $record_id, $selected, false ),
					esc_html( $team_name )
				);
			}
			echo '</select>';
		} else {
			// `type="text"` even for the URLs. `type="url"` refuses anything without a
			// scheme, and Airtable's url columns are full of scheme-less values a student
			// will reasonably retype the same way — the browser would block the save with
			// a message they cannot act on. `clean_url()` adds the scheme instead.
			printf(
				'<input type="text" id="%1$s" name="details[%2$s]" value="%3$s" placeholder="%4$s" />',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $current ),
				esc_attr( isset( $spec['placeholder'] ) ? $spec['placeholder'] : '' )
			);
		}

		printf(
			'<button type="submit" class="wpcpm-button">%s</button>',
			esc_html__( 'Save', 'wpcredits-program-manager' )
		);

		echo '</form>';

		if ( ! empty( $spec['help'] ) ) {
			printf( '<p class="wpcpm-edit__note">%s</p>', esc_html( $spec['help'] ) );
		}

		echo '</div>';
		echo '</details>';
	}
}
