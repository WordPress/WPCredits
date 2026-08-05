<?php
/**
 * The report form a student fills in on their own Report Card.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Students Reports record, as a form the student owns.
 *
 * **The fields differ by track, and the lists are the program's, not a guess.** A student on
 * *In Sensei* files twenty-two things; one on *In Sensei 50h* files ten. The two sets come from two
 * grid views in the base built for exactly this, and they are declared here in code rather than read
 * from those views because **Airtable exposes no way to read a view's visible fields** — the
 * metadata API returns a view's id, name and type, and asking for records with `view=` returned the
 * same forty-two fields for both. So the lists have to be maintained by hand, and `bin/test-report-form.php`
 * pins them so a silent drift shows up as a failure rather than as a missing field on somebody's card.
 *
 * **Grades and hours are the student's to type.** That looked wrong and was queried: they are graded
 * elsewhere and copy the score across, so this form is a transcription rather than an assessment.
 * Nothing here is computed from anything, and nothing here decides whether they pass.
 *
 * **The current values are read live from Airtable, not from the synced row.** The sync carries a
 * dozen fields for the cards; these twenty-two are not among them, and adding them would mean a page
 * that shows "Not set" for everything until the next sync runs — the trap that hid *Field of study*
 * on every student card for two days. A short transient keeps the page quick, and saving clears it,
 * so a student always sees what they just typed.
 */
class WPCPM_Student_Report_Form {

	const ACTION_SAVE = 'wpcpm_save_report';

	/** How long a fetched record is reused, in seconds. */
	const CACHE_TTL = 300;

	/** Transient prefix for one student's record. */
	const CACHE_PREFIX = 'wpcpm_report_';

	/** Longest a free-text answer may be. */
	const MAX_TEXT = 5000;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
	}

	/*
	 * The two field sets
	 * --------------------------------------------------------------------
	 */

	/**
	 * The fields one track's report form holds, in the order they are shown.
	 *
	 * Keys are the **Airtable field names**, because those are what a write has to name and having
	 * one identifier rather than two is one fewer thing to keep in step. `Company ` carries a
	 * trailing space in the base — the same trap as `Tutor ` on the Students table, and dropping it
	 * makes the write silently do nothing.
	 *
	 * `step` mirrors the column's own precision: the grades allow two decimals, hours and the three
	 * course marks are whole numbers.
	 *
	 * @param bool $is_50h Whether the student is on the 50-hour track.
	 * @return array<string, array> Airtable field name => spec.
	 */
	public static function fields( $is_50h ) {
		$grade = array(
			'type' => 'number',
			'step' => '0.01',
			'min'  => 0,
			'max'  => 100,
		);

		$mark = array(
			'type' => 'number',
			'step' => '1',
			'min'  => 0,
			'max'  => 100,
		);

		$common = array(
			'Hours' => array(
				'label' => __( 'Hours contributed', 'wpcredits-program-manager' ),
				'type'  => 'number',
				'step'  => '1',
				'min'   => 0,
				'max'   => 10000,
			),
			'Open source basics and WordPress - final grade' => array( 'label' => __( 'Open source basics and WordPress — final grade', 'wpcredits-program-manager' ) ) + $grade,
			'How decisions are made in the WordPress project - final grade' => array( 'label' => __( 'How decisions are made in the WordPress project — final grade', 'wpcredits-program-manager' ) ) + $grade,
		);

		$sensei = array(
			'Community meeting etiquette - final grade'    => array( 'label' => __( 'Community meeting etiquette — final grade', 'wpcredits-program-manager' ) ) + $grade,
			'Writing in the WordPress voice - final grade' => array( 'label' => __( 'Writing in the WordPress voice — final grade', 'wpcredits-program-manager' ) ) + $grade,
			'Beginner WordPress User - final grade'        => array( 'label' => __( 'Beginner WordPress User — final grade', 'wpcredits-program-manager' ) ) + $grade,
			'Intermediate WordPress User - final grade'    => array( 'label' => __( 'Intermediate WordPress User — final grade', 'wpcredits-program-manager' ) ) + $grade,
			'Advance WordPress User - final grade'         => array( 'label' => __( 'Advanced WordPress User — final grade', 'wpcredits-program-manager' ) ) + $grade,
			'Beginner WordPress Developer'                 => array( 'label' => __( 'Beginner WordPress Developer', 'wpcredits-program-manager' ) ) + $mark,
			'Intermediate Theme Developer'                 => array( 'label' => __( 'Intermediate Theme Developer', 'wpcredits-program-manager' ) ) + $mark,
			'Beginner WordPress Designer'                  => array( 'label' => __( 'Beginner WordPress Designer', 'wpcredits-program-manager' ) ) + $mark,
		);

		$fifty = array(
			'Basic principles of conflict resolution - final grade' => array( 'label' => __( 'Basic principles of conflict resolution — final grade', 'wpcredits-program-manager' ) ) + $grade,
		);

		$project = array(
			'Main Contribution Team'           => array(
				'label' => __( 'Contribution teams', 'wpcredits-program-manager' ),
				'type'  => 'links',
				'map'   => 'teams',
				'help'  => __( 'The teams you are contributing to. Choose as many as apply.', 'wpcredits-program-manager' ),
			),
			'Contribution Project Description' => array(
				'label' => __( 'What you contributed', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
			),
			'Company '                         => array(
				'label' => __( 'Sponsor company', 'wpcredits-program-manager' ),
				'type'  => 'links',
				'map'   => 'companies',
				'help'  => __( 'The company sponsoring your place, if one is.', 'wpcredits-program-manager' ),
			),
			'Personal Website URL'             => array(
				'label' => __( 'Your personal website', 'wpcredits-program-manager' ),
				'type'  => 'url',
			),
		);

		$reflections = array(
			'Post Reflection: Building Your Personal Website' => array(
				'label' => __( 'Reflection: building your personal website', 'wpcredits-program-manager' ),
				'type'  => 'url',
			),
			'Slack/GitHub/Blog WordPress Community meetings/discussions' => array(
				'label' => __( 'Meetings and discussions you took part in', 'wpcredits-program-manager' ),
				'type'  => 'textarea',
				'help'  => __( 'Slack, GitHub or blog links, one per line.', 'wpcredits-program-manager' ),
			),
			'Post Reflection: Choosing Your Team and Project' => array(
				'label' => __( 'Reflection: choosing your team and project', 'wpcredits-program-manager' ),
				'type'  => 'url',
			),
			'Post Reflection: Your First Contribution' => array(
				'label' => __( 'Reflection: your first contribution', 'wpcredits-program-manager' ),
				'type'  => 'url',
			),
			'Post Reflection: Halfway Check-In'        => array(
				'label' => __( 'Reflection: halfway check-in', 'wpcredits-program-manager' ),
				'type'  => 'url',
			),
			'WP event participation URL'               => array(
				'label' => __( 'A WordPress event you took part in', 'wpcredits-program-manager' ),
				'type'  => 'url',
			),
			'Closing post URL'                         => array(
				'label' => __( 'Your closing post', 'wpcredits-program-manager' ),
				'type'  => 'url',
			),
		);

		if ( $is_50h ) {
			// Ten fields. The 50-hour track has its own grade — conflict resolution — that the
			// longer one does not, and none of the reflection posts.
			$fields = $common + $fifty + array(
				'Main Contribution Team'            => $project['Main Contribution Team'],
				'Contribution Project Description'  => $project['Contribution Project Description'],
				'Slack/GitHub/Blog WordPress Community meetings/discussions' => $reflections['Slack/GitHub/Blog WordPress Community meetings/discussions'],
				'Final Contribution Project Report' => array(
					'label' => __( 'Your final project report', 'wpcredits-program-manager' ),
					'type'  => 'richtext',
					'help'  => __( 'The write-up of what you built and contributed.', 'wpcredits-program-manager' ),
				),
				'Personal Website URL'              => $project['Personal Website URL'],
				'Company '                          => $project['Company '],
			);
		} else {
			$fields = $common + $sensei + $project + $reflections;
		}

		/**
		 * Filter the report form's fields for one track.
		 *
		 * @param array $fields Airtable field name => spec.
		 * @param bool  $is_50h Whether this is the 50-hour track.
		 */
		return (array) apply_filters( 'wpcpm_report_form_fields', $fields, $is_50h );
	}

	/*
	 * Reading
	 * --------------------------------------------------------------------
	 */

	/**
	 * One student's report record, from Airtable.
	 *
	 * Cached briefly and cleared on save. A failure returns the `WP_Error` rather than an empty
	 * array, so the form can say the record could not be read instead of showing every field blank
	 * — which a student would read as their work having been lost.
	 *
	 * @param string $record Airtable record ID.
	 * @return array|WP_Error Field name => value.
	 */
	public static function values( $record ) {
		$record = trim( (string) $record );

		if ( '' === $record ) {
			return new WP_Error( 'wpcpm_no_record', __( 'Your record could not be found in the program data.', 'wpcredits-program-manager' ) );
		}

		$key    = self::CACHE_PREFIX . md5( $record );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$result   = $airtable->get_record( $settings['reports_table'], $record );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fields = isset( $result['fields'] ) && is_array( $result['fields'] ) ? $result['fields'] : array();

		set_transient( $key, $fields, self::CACHE_TTL );

		return $fields;
	}

	/**
	 * Forget a cached record.
	 *
	 * @param string $record Airtable record ID.
	 */
	public static function forget( $record ) {
		delete_transient( self::CACHE_PREFIX . md5( trim( (string) $record ) ) );
	}

	/**
	 * Whether this person may fill in that student's form.
	 *
	 * The student themselves, or a program manager — the same rule the profile fields use. A mentor
	 * deliberately cannot: the report is the student's own account of their work, and a mentor typing
	 * it for them would make the record say something it does not mean.
	 *
	 * @param int $student_id Student user ID.
	 * @return bool
	 */
	public static function user_can_edit( $student_id ) {
		$student_id = (int) $student_id;

		if ( $student_id <= 0 || ! is_user_logged_in() ) {
			return false;
		}

		return get_current_user_id() === $student_id || current_user_can( WPCPM_Roles::CAP_MANAGE );
	}


	/*
	 * Rendering
	 * --------------------------------------------------------------------
	 */

	/**
	 * The form, as the *Report form* section's contents.
	 *
	 * One form for every field rather than a row of little ones: a student sits down once to fill
	 * this in, and twenty-two separate saves would be twenty-two page loads.
	 *
	 * @param WP_User $student The student whose report this is.
	 * @param array   $program Their cached program row, for the track.
	 */
	public static function render( WP_User $student, array $program ) {
		$is_50h = ! empty( $program['is_50h'] );
		$fields = self::fields( $is_50h );
		$record = WPCPM_Mentor_Calls::student_record( $student->ID );
		$values = self::values( $record );
		$can    = self::user_can_edit( $student->ID );

		self::render_message();

		if ( is_wp_error( $values ) ) {
			// Said out loud rather than drawn as an empty form. Twenty-two blank boxes over a
			// student's real answers is the one outcome worse than an error message: they would
			// fill it in again, press Save, and overwrite what was already there.
			printf(
				'<p class="wpcpm-student__note wpcpm-report__error">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the reason the record could not be read. */
						__( 'Your report form could not be loaded just now: %s Nothing has been lost — try reloading the page.', 'wpcredits-program-manager' ),
						$values->get_error_message()
					)
				)
			);

			return;
		}

		printf(
			'<p class="wpcpm-student__note">%s</p>',
			esc_html(
				$is_50h
					? __( 'Your report for the 50-hour course. Fill in what you have; you can come back and add the rest.', 'wpcredits-program-manager' )
					: __( 'Your report for the course. Fill in what you have; you can come back and add the rest.', 'wpcredits-program-manager' )
			)
		);

		if ( ! $can ) {
			printf(
				'<p class="wpcpm-student__note">%s</p>',
				esc_html__( 'This is the student\'s own report, so it is shown here but not editable.', 'wpcredits-program-manager' )
			);
		}

		printf(
			'<form class="wpcpm-report" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Saving…', 'wpcredits-program-manager' )
		);

		wp_nonce_field( self::ACTION_SAVE . '_' . (int) $student->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="student" value="%d" />', (int) $student->ID );

		foreach ( $fields as $name => $spec ) {
			self::render_field( $name, $spec, isset( $values[ $name ] ) ? $values[ $name ] : '', $can );
		}

		if ( $can ) {
			printf(
				'<p class="wpcpm-report__submit"><button type="submit" class="wpcpm-button">%s</button></p>',
				esc_html__( 'Save my report', 'wpcredits-program-manager' )
			);
		}

		echo '</form>';
	}

	/**
	 * One field.
	 *
	 * @param string $name  Airtable field name.
	 * @param array  $spec  Field spec.
	 * @param mixed  $value Its current value.
	 * @param bool   $can   Whether it may be edited.
	 */
	private static function render_field( $name, array $spec, $value, $can ) {
		$key  = self::key( $name );
		$id   = 'wpcpm-report-' . $key;
		$type = isset( $spec['type'] ) ? $spec['type'] : 'text';
		$dis  = $can ? '' : ' disabled="disabled"';

		printf(
			'<p class="wpcpm-field wpcpm-field--%1$s"><label for="%2$s">%3$s</label>',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_html( $spec['label'] )
		);

		if ( 'links' === $type ) {
			self::render_links_field( $key, $spec, $value, $can );
		} elseif ( 'number' === $type ) {
			printf(
				'<input type="number" id="%1$s" name="report[%2$s]" value="%3$s" step="%4$s" min="%5$s" max="%6$s" inputmode="decimal"%7$s />',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				esc_attr( isset( $spec['step'] ) ? $spec['step'] : 'any' ),
				esc_attr( isset( $spec['min'] ) ? (string) $spec['min'] : '' ),
				esc_attr( isset( $spec['max'] ) ? (string) $spec['max'] : '' ),
				$dis // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
			);
		} elseif ( 'textarea' === $type || 'richtext' === $type ) {
			printf(
				'<textarea id="%1$s" name="report[%2$s]" rows="%3$d" maxlength="%4$d"%5$s>%6$s</textarea>',
				esc_attr( $id ),
				esc_attr( $key ),
				'richtext' === $type ? 8 : 4,
				(int) self::MAX_TEXT,
				$dis, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
				esc_textarea( is_scalar( $value ) ? (string) $value : '' )
			);
		} else {
			// `type="text"` even for the URLs, for the reason the profile editor gives: `type="url"`
			// refuses a scheme-less address, and Airtable's url columns are full of them — the
			// browser would block the save with a message a student cannot act on. `clean_url()`
			// adds the scheme instead.
			printf(
				'<input type="text" id="%1$s" name="report[%2$s]" value="%3$s" inputmode="url"%4$s />',
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( is_scalar( $value ) ? (string) $value : '' ),
				$dis // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- One of two literals above.
			);
		}

		if ( ! empty( $spec['help'] ) ) {
			printf( '<span class="wpcpm-field__hint">%s</span>', esc_html( $spec['help'] ) );
		}

		echo '</p>';
	}

	/**
	 * A linked-record field, as checkboxes.
	 *
	 * Checkboxes rather than a multi-select, matching the profile editor: every option and every
	 * current answer visible at once, and operable on a phone.
	 *
	 * @param string $key   Form key.
	 * @param array  $spec  Field spec.
	 * @param mixed  $value Current value: an array of record IDs, or of `{id,name}` objects.
	 * @param bool   $can   Whether it may be edited.
	 */
	private static function render_links_field( $key, array $spec, $value, $can ) {
		$known = self::catalog( isset( $spec['map'] ) ? $spec['map'] : 'teams' );

		if ( empty( $known ) ) {
			printf(
				'<span class="wpcpm-field__hint">%s</span>',
				esc_html__( 'This list has not been read from the program records yet. Run a sync and it becomes editable.', 'wpcredits-program-manager' )
			);

			return;
		}

		// The REST API returns a linked-record cell as bare record IDs; the Airtable MCP tool returns
		// `{id,name}` objects, and a harness fed the latter would pass while the live site failed.
		// Both shapes are read here for that reason.
		$chosen = array();

		foreach ( (array) $value as $entry ) {
			if ( is_array( $entry ) && isset( $entry['id'] ) ) {
				$chosen[] = (string) $entry['id'];
			} elseif ( is_scalar( $entry ) ) {
				$chosen[] = (string) $entry;
			}
		}

		echo '<span class="wpcpm-field__group">';

		foreach ( $known as $id => $label ) {
			printf(
				'<label class="wpcpm-edit__check"><input type="checkbox" name="report[%1$s][]" value="%2$s"%3$s%4$s /> %5$s</label>',
				esc_attr( $key ),
				esc_attr( $id ),
				in_array( $id, $chosen, true ) ? ' checked="checked"' : '',
				$can ? '' : ' disabled="disabled"',
				esc_html( $label )
			);
		}

		// Unchecking everything posts nothing for this key, and the save loop skips a key it was
		// not sent — so clearing the last one would silently do nothing without this.
		printf( '<input type="hidden" name="report[%s][]" value="" />', esc_attr( $key ) );

		echo '</span>';
	}

	/**
	 * The outcome of the last save, if there is one.
	 */
	private static function render_message() {
		$message = self::message( self::status() );

		if ( empty( $message ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-calls__message wpcpm-calls__message--%1$s" role="status">%2$s</p>',
			esc_attr( $message[0] ),
			esc_html( $message[1] )
		);
	}

	/*
	 * Saving
	 * --------------------------------------------------------------------
	 */

	/**
	 * Write the submitted answers back to Airtable.
	 */
	public static function handle_save() {
		$student_id = isset( $_POST['student'] ) ? absint( wp_unslash( $_POST['student'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.

		check_admin_referer( self::ACTION_SAVE . '_' . $student_id );

		if ( ! self::user_can_edit( $student_id ) ) {
			wp_die( esc_html__( 'You cannot fill in that report form.', 'wpcredits-program-manager' ), 403 );
		}

		$record = WPCPM_Mentor_Calls::student_record( $student_id );

		if ( '' === $record ) {
			self::bounce( 'report-no-record' );
		}

		$program = WPCPM_Students_Sync::get_program( $student_id );
		$fields  = self::fields( ! empty( $program['is_50h'] ) );

		$posted = isset( $_POST['report'] ) && is_array( $_POST['report'] )
			? wp_unslash( $_POST['report'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is validated by type below.
			: array();

		$cells    = array();
		$rejected = array();

		foreach ( $fields as $name => $spec ) {
			$key = self::key( $name );

			if ( ! isset( $posted[ $key ] ) ) {
				continue;
			}

			list( $ok, $value ) = self::clean( $posted[ $key ], $spec );

			// **"Rejected" and "cleared" cannot be the same answer.** Airtable empties a number
			// column with `null`, so `null` is a legitimate value to send — and a first draft used
			// it for "could not be understood" as well, which would have made an unreadable grade
			// silently erase the stored one. Hence the pair: whether to write, and what.
			if ( $ok ) {
				$cells[ $name ] = $value;
			} else {
				$rejected[] = $spec['label'];
			}
		}

		if ( empty( $cells ) ) {
			self::bounce( empty( $rejected ) ? 'report-nothing' : 'report-rejected' );
		}

		$settings = WPCPM_Settings::get();
		$airtable = new WPCPM_Airtable( $settings );
		$result   = $airtable->update_records(
			$settings['reports_table'],
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			self::bounce( 'report-refused' );
		}

		// The cache holds what Airtable had a moment ago, which is now wrong.
		self::forget( $record );

		// Everything readable was saved; anything that was not is named rather than dropped in
		// silence, which is what makes a rejected grade findable instead of mysterious.
		self::bounce( empty( $rejected ) ? 'report-saved' : 'report-partly' );
	}

	/**
	 * A submitted value, in the shape Airtable takes, or null if it cannot be used.
	 *
	 * Every type is handled explicitly. Nothing reaches Airtable without passing through here —
	 * a number field given "twelve" is a 422 for the whole request, so one bad answer would
	 * otherwise lose the other twenty-one.
	 *
	 * @param mixed $raw  Posted value.
	 * @param array $spec Field spec.
	 * @return array{0:bool,1:mixed} Whether to write it, and what to write.
	 */
	private static function clean( $raw, array $spec ) {
		$type = isset( $spec['type'] ) ? $spec['type'] : 'text';

		if ( 'links' === $type ) {
			return array( true, self::clean_links( $raw, isset( $spec['map'] ) ? $spec['map'] : 'teams' ) );
		}

		if ( ! is_scalar( $raw ) ) {
			return array( false, null );
		}

		$raw = trim( (string) $raw );

		if ( 'number' === $type ) {
			// Emptying the box means emptying the column, and Airtable does that with `null`. An
			// empty string in a number field is a 422 for the whole request, which would lose the
			// other twenty-one answers along with this one.
			if ( '' === $raw ) {
				return array( true, null );
			}

			// A comma decimal is what half of Europe types, and Airtable will not take it.
			$raw = str_replace( ',', '.', $raw );

			if ( ! is_numeric( $raw ) ) {
				return array( false, null );
			}

			$number = ( isset( $spec['step'] ) && '1' === $spec['step'] ) ? (int) round( (float) $raw ) : round( (float) $raw, 2 );

			if ( isset( $spec['min'] ) && $number < $spec['min'] ) {
				return array( false, null );
			}

			if ( isset( $spec['max'] ) && $number > $spec['max'] ) {
				return array( false, null );
			}

			return array( true, $number );
		}

		if ( 'url' === $type ) {
			return array( true, self::clean_url( $raw ) );
		}

		// `textarea` and `richtext` both arrive as text. Rich text is Markdown in Airtable, so it
		// is stored as typed rather than being converted — a student writing a bullet list gets a
		// bullet list, and one writing prose gets prose.
		return array( true, mb_substr( sanitize_textarea_field( $raw ), 0, self::MAX_TEXT ) );
	}

	/**
	 * Validated record IDs for a linked-record field.
	 *
	 * @param mixed  $raw Posted value.
	 * @param string $map Which catalog to check against.
	 * @return string[]
	 */
	private static function clean_links( $raw, $map ) {
		$known = self::catalog( $map );
		$out   = array();

		foreach ( (array) $raw as $id ) {
			if ( ! is_scalar( $id ) ) {
				continue;
			}

			$id = trim( (string) $id );

			if ( '' !== $id && isset( $known[ $id ] ) && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}

		return $out;
	}

	/**
	 * A URL the way the rest of the plugin normalizes them.
	 *
	 * Airtable's url columns are full of scheme-less values, and a student retyping one the same
	 * way should not be told off for it.
	 *
	 * @param string $raw Posted value.
	 * @return string
	 */
	private static function clean_url( $raw ) {
		if ( '' === $raw ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $raw ) ) {
			$raw = 'https://' . ltrim( $raw, '/' );
		}

		$url = esc_url_raw( $raw, array( 'http', 'https' ) );

		return $url ? $url : '';
	}

	/**
	 * One of the record-ID catalogs the sync builds.
	 *
	 * @param string $map `teams` or `companies`.
	 * @return array<string, string> Record ID => name.
	 */
	public static function catalog( $map ) {
		$lookups = WPCPM_Mentors_Sync::lookups();
		$raw     = isset( $lookups[ $map ] ) ? (array) $lookups[ $map ] : array();
		$out     = array();

		foreach ( $raw as $id => $name ) {
			$name = trim( (string) $name );

			if ( WPCPM_Mentors_Sync::is_record_id( $id ) && '' !== $name ) {
				$out[ $id ] = $name;
			}
		}

		natcasesort( $out );

		return $out;
	}

	/**
	 * A form key for an Airtable field name.
	 *
	 * Airtable names contain spaces, slashes and colons, none of which belong in a form key — and
	 * `Company ` ends in a space, which would be lost in transit and take the field with it.
	 *
	 * @param string $name Airtable field name.
	 * @return string
	 */
	public static function key( $name ) {
		return 'f' . substr( md5( (string) $name ), 0, 12 );
	}

	/**
	 * Back to the student page with a message.
	 *
	 * @param string $status Outcome flag.
	 */
	private static function bounce( $status ) {
		WPCPM_Flash::set( 'report', $status );

		$page = WPCPM_Students_Dashboard::page_url();

		wp_safe_redirect( ( '' !== $page ? $page : home_url( '/' ) ) . '#wpcpm-report-form' );
		exit;
	}

	/**
	 * The message for an outcome flag.
	 *
	 * @param string $status Outcome flag.
	 * @return array{0:string,1:string}|array
	 */
	public static function message( $status ) {
		$messages = array(
			'report-saved'     => array( 'success', __( 'Your report form is saved, and your mentor can see it.', 'wpcredits-program-manager' ) ),
			'report-nothing'   => array( 'error', __( 'Nothing was submitted, so nothing changed.', 'wpcredits-program-manager' ) ),
			'report-no-record' => array( 'error', __( 'Your record could not be found in the program data, so there is nothing to save to.', 'wpcredits-program-manager' ) ),
			'report-refused'   => array( 'error', __( 'The program records refused the change, so nothing was saved. Please try again.', 'wpcredits-program-manager' ) ),
			'report-partly'    => array( 'error', __( 'Saved, except for one or more numbers that could not be read. Check the grades and hours: they take digits, and a grade is between 0 and 100.', 'wpcredits-program-manager' ) ),
			'report-rejected'  => array( 'error', __( 'Nothing was saved: the numbers could not be read. Grades and hours take digits, and a grade is between 0 and 100.', 'wpcredits-program-manager' ) ),
		);

		return isset( $messages[ $status ] ) ? $messages[ $status ] : array();
	}

	/**
	 * The outcome flag on this request, if any.
	 *
	 * @return string
	 */
	public static function status() {
		return sanitize_key( (string) WPCPM_Flash::take( 'report' ) );
	}
}
