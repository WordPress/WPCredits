<?php
/**
 * The lists the Settings screen draws its choices from.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answers each list a setting's value is chosen from, so the Settings screen can draw a select or a
 * checkbox list where a person used to type: the bases the token can open, the base's tables and
 * their columns, a single-select column's options, the statuses of the tracks the site runs, the
 * program managers, and the answer provider's models; and puts together the lists the screen draws
 * from them, where the tool screens can reach them too.
 *
 * Every reader answers an empty array when its list cannot be read, whatever the reason (no token,
 * no base, a token Airtable refuses or that lacks the `schema.bases:read` scope, a table or column
 * the base lacks, Airtable out of reach, no account holding the capability), and raises neither an
 * error nor a notice: the screen then draws the text input the setting always had, with the same
 * name and value, and `why_empty()` says in one sentence why. A list is advice about what may be
 * chosen and nothing more. `WPCPM_Settings::save()` holds every value to the rule it always had,
 * and each value a list offers is the very string the setting stores for it, so a value saved is
 * found in the list it was chosen from.
 *
 * Each list is read once a request, a failed read included: the Connection tab lists the base's
 * tables ten times and four tables' columns, and the Students and mentors tab two status columns,
 * each from one read of the schema, and asking Airtable for each would spend the base's five
 * requests a second on one page, and wait on each when Airtable does not answer. And a read of the
 * bases or of the schema that failed is remembered five minutes beyond the request
 * (`WPCPM_Airtable::FAILED_TTL`), so an Airtable that does not answer is waited on once, not on every
 * draw of the four screens that read it: the Connection, Students and mentors and Advanced tabs, and
 * the Mentor Status Checker's screen. Reading the lists again forgets it (`read_again()`).
 */
final class WPCPM_Settings_Choices {

	/**
	 * The lists read this request, by what was asked: each the list, or the error its read failed
	 * with.
	 *
	 * @var array<string, array|WP_Error>
	 */
	private static $read = array();

	/**
	 * Forget what was read this request, so the next question reads its list again.
	 */
	public static function flush() {
		self::$read = array();
	}

	/**
	 * Forget every list, the copies the Airtable client keeps of them included (the schema's for
	 * fifteen minutes, the bases' and the column options' for a day) and a read of them that failed
	 * (five minutes), so each is read from Airtable again the next time it is asked for: after a Save
	 * of the Connection tab, which can change the token and the base they are read with, and for the
	 * button that reads them again when somebody has changed the base, or Airtable answers again.
	 */
	public static function read_again() {
		if ( class_exists( 'WPCPM_Airtable' ) ) {
			WPCPM_Airtable::forget_lists();
		}

		self::flush();
	}

	/**
	 * The bases the token can open (`WPCPM_Airtable::read_bases()`).
	 *
	 * @return array<string, string> Base ID => its name.
	 */
	public static function bases() {
		$read = self::bases_read();

		return is_wp_error( $read ) ? array() : $read;
	}

	/**
	 * The tables of the base, from its schema.
	 *
	 * @return array<string, string> Table ID => its name, in the base's order.
	 */
	public static function tables() {
		$schema = self::schema_read();

		if ( is_wp_error( $schema ) ) {
			return array();
		}

		$tables = array();

		foreach ( $schema as $id => $table ) {
			$id = self::stored_form( $id );

			if ( '' !== $id ) {
				$tables[ $id ] = is_array( $table ) && isset( $table['name'] ) ? (string) $table['name'] : '';
			}
		}

		return $tables;
	}

	/**
	 * The columns of one table, from the base's schema: its primary column first, since that is the
	 * one a name column almost always names, then the rest in the base's order.
	 *
	 * @param string $table Table ID, or the table's name, since a setting may hold either.
	 * @return array<string, string> Column name as the setting stores it => its name.
	 */
	public static function columns( $table ) {
		$read = self::columns_read( (string) $table );

		return is_wp_error( $read ) ? array() : $read;
	}

	/**
	 * The options of one single-select column, such as the Mentors table's Status or the
	 * Institutions table's Current Stage (`WPCPM_Airtable::read_field_options()`), read from the
	 * request's one read of the schema when the options are not already held.
	 *
	 * @param string $table Table ID, or the table's name.
	 * @param string $field The column's name.
	 * @return array<string, string> Option as the setting stores it => its name, in the base's order.
	 */
	public static function status_options( $table, $field ) {
		$read = self::options_read( (string) $table, (string) $field );

		return is_wp_error( $read ) ? array() : $read;
	}

	/**
	 * The status of every track the site runs, with the track's name (`WPCPM_Tracks::live()`).
	 *
	 * @return array<string, string> Status, as the track holds it => the track's name.
	 */
	public static function tracks_statuses() {
		if ( ! class_exists( 'WPCPM_Tracks' ) ) {
			return array();
		}

		$statuses = array();

		foreach ( WPCPM_Tracks::live() as $status => $row ) {
			$status = (string) $status;

			if ( '' === trim( $status ) ) {
				continue;
			}

			$statuses[ $status ] = is_array( $row ) && isset( $row['label'] ) && '' !== (string) $row['label'] ? (string) $row['label'] : $status;
		}

		return $statuses;
	}

	/**
	 * The program managers a reviewer list may name: every account holding the capability that has
	 * an address, the ones mailed when a reviewer list is empty (`WPCPM_Institutions::managers()`),
	 * read once a request.
	 *
	 * One group, Administrators, the one name the screens give the people who manage the program:
	 * they hold WordPress's own Administrator role, which is where the capability comes from, and an
	 * account holding it another way, through a role a role editor gave it or granted to the account
	 * itself, manages the program all the same, so it is listed with them. With nobody to list, no
	 * group.
	 *
	 * @return array<string, array<string, string>> Group => address => display name, in name order.
	 */
	public static function managers() {
		if ( ! array_key_exists( 'managers', self::$read ) ) {
			self::$read['managers'] = self::read_managers();
		}

		return self::$read['managers'];
	}

	/**
	 * The models the answer provider may use.
	 *
	 * `WPCPM_Handbook_Answer::providers()` names each provider and no model, and Google AI Studio is
	 * reached by a model's name alone, so the one model this plugin knows to exist is the default it
	 * ships: an alias that always points at the current Gemini Flash and so cannot be retired out
	 * from under a site. A list of that one model is no choice, since a select holding it and the
	 * model saved would offer nothing to choose, so Need help?'s Model field is the text input it
	 * always was until a provider lists more than the default (`WPCPM_Handbook::render_settings_rows()`).
	 *
	 * @return array<string, string> Model => the same model; the default alone.
	 */
	public static function models() {
		$default = (string) WPCPM_Settings::defaults()['handbook_model'];

		return '' === $default ? array() : array( $default => $default );
	}

	/**
	 * A list of IDs with their names, as a select offers them: the name, then the ID it posts.
	 *
	 * @param array<string, string> $names ID => name.
	 * @return array<string, string> ID => what the option says.
	 */
	public static function named_ids( array $names ) {
		$choices = array();

		foreach ( $names as $id => $name ) {
			$id   = (string) $id;
			$name = (string) $name;

			/* translators: 1: a name, 2: the ID or the address it goes with. */
			$choices[ $id ] = '' === $name ? $id : sprintf( __( '%1$s (%2$s)', 'wpcredits-program-manager' ), $name, $id );
		}

		return $choices;
	}

	/**
	 * The options of the Mentors table's Status column, which the mentor status and the Mentor
	 * Status Checker's two statuses are chosen from.
	 *
	 * @param array $settings The settings whose Mentors table is read.
	 * @return array<string, string>
	 */
	public static function mentor_statuses( array $settings ) {
		list( $table, $field ) = self::status_column( 'mentor_statuses', $settings );

		return self::status_options( $table, $field );
	}

	/**
	 * The options of the Students Reports table's Status column, which the two status lists are
	 * chosen from beside the live tracks' statuses.
	 *
	 * @param array $settings The settings whose Students Reports table is read.
	 * @return array<string, string>
	 */
	public static function report_statuses( array $settings ) {
		list( $table, $field ) = self::status_column( 'report_statuses', $settings );

		return self::status_options( $table, $field );
	}

	/**
	 * The options of the Institutions table's Current Stage column, which the pipeline stages are
	 * chosen from.
	 *
	 * @param array $settings The settings whose Institutions table is read.
	 * @return array<string, string>
	 */
	public static function institution_stages( array $settings ) {
		list( $table, $field ) = self::status_column( 'institution_stages', $settings );

		return self::status_options( $table, $field );
	}

	/**
	 * The choices a status list is drawn from: the Students Reports table's statuses under From
	 * Airtable, and the status of every track the site runs under From the Track Builder, each status
	 * once, each as the list stores it. None when Airtable's statuses cannot be read: the live tracks'
	 * statuses alone are not the list, and boxes for them alone would leave no way to add a status
	 * Airtable holds.
	 *
	 * @param array $settings The settings whose Students Reports table is read.
	 * @return array Group => status as the list stores it => the status as its source spells it.
	 */
	public static function status_lists( array $settings ) {
		$airtable = self::report_statuses( $settings );

		if ( array() === $airtable ) {
			return array();
		}

		$tracks = array();

		foreach ( array_keys( self::tracks_statuses() ) as $status ) {
			$stored = self::stored_form( $status );

			if ( '' !== $stored ) {
				$tracks[ $stored ] = (string) $status;
			}
		}

		return array_filter(
			array(
				__( 'From Airtable', 'wpcredits-program-manager' )          => array_diff_key( $airtable, $tracks ),
				__( 'From the Track Builder', 'wpcredits-program-manager' ) => $tracks,
			)
		);
	}

	/**
	 * Why a list answered nothing, in one sentence a person can act on.
	 *
	 * @param string $reader The list, by its reader: `bases`, `tables`, `columns` (with the table),
	 *                       `mentor_statuses`, `report_statuses` or `institution_stages` (with the
	 *                       settings), or `managers`.
	 * @param mixed  ...$args The reader's own argument.
	 * @return string The sentence, which may contain <code> tags and is otherwise escaped; '' when the
	 *                list is not empty.
	 */
	public static function why_empty( $reader, ...$args ) {
		$reader = (string) $reader;
		$arg    = array() === $args ? null : $args[0];

		// Without the plugin's Airtable client nothing was asked, so no answer can be named.
		if ( 'managers' !== $reader && ! class_exists( 'WPCPM_Airtable' ) ) {
			return esc_html__( 'The list could not be read from Airtable just now.', 'wpcredits-program-manager' );
		}

		switch ( $reader ) {
			case 'bases':
				$read = self::bases_read();
				break;

			case 'tables':
				$read = self::schema_read();
				break;

			case 'columns':
				$read = self::columns_read( (string) $arg );
				break;

			case 'mentor_statuses':
			case 'report_statuses':
			case 'institution_stages':
				list( $table, $field ) = self::status_column( $reader, (array) $arg );

				$read = self::options_read( $table, $field );
				break;

			case 'managers':
				return array() === self::managers() ? esc_html__( 'No program manager account with an email address was found.', 'wpcredits-program-manager' ) : '';

			default:
				return '';
		}

		if ( ! is_wp_error( $read ) ) {
			if ( array() !== $read ) {
				return '';
			}

			// Read, and empty: only the bases can be, for a token that opens none.
			return 'bases' === $reader
				? esc_html__( 'Airtable lists no base the Personal Access Token can open.', 'wpcredits-program-manager' )
				: esc_html__( 'The list could not be read from Airtable just now.', 'wpcredits-program-manager' );
		}

		return self::cause( $reader, $read );
	}

	/**
	 * The sentence for the error a list's read failed with: each true of the one cause it names.
	 *
	 * @param string   $reader The list, as `why_empty()` names it.
	 * @param WP_Error $error  The error.
	 * @return string May contain <code> tags; otherwise escaped.
	 */
	private static function cause( $reader, WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		switch ( $error->get_error_code() ) {
			case 'wpcpm_no_token':
				return esc_html__( 'No Personal Access Token is set, so the list cannot be read from Airtable.', 'wpcredits-program-manager' );

			case 'wpcpm_no_base':
				return esc_html__( 'No Base ID is set, so the base\'s tables cannot be read from Airtable.', 'wpcredits-program-manager' );

			// A table or a column the base lacks, and one that holds no options: the client's own
			// sentence, which names them.
			case 'wpcpm_airtable_no_table':
			case 'wpcpm_airtable_no_column':
			case 'wpcpm_airtable_not_select':
				return esc_html( $error->get_error_message() );
		}

		if ( 401 === $status ) {
			return esc_html__( 'Airtable refused the Personal Access Token, so the list cannot be read.', 'wpcredits-program-manager' );
		}

		// Airtable answers 403 for a token without the scope, and for a base the token has no access
		// to; listing the bases asks for no base, so only the scope can be what is missing there.
		if ( 403 === $status ) {
			return 'bases' === $reader
				? wp_kses( __( 'Airtable refused the read: the Personal Access Token needs the <code>schema.bases:read</code> scope.', 'wpcredits-program-manager' ), array( 'code' => array() ) )
				: wp_kses( __( 'Airtable refused the read: the Personal Access Token needs the <code>schema.bases:read</code> scope and access to the base.', 'wpcredits-program-manager' ), array( 'code' => array() ) );
		}

		return esc_html__( 'The list could not be read from Airtable just now.', 'wpcredits-program-manager' );
	}

	/**
	 * The bases, or the error their read failed with, read once a request, a failure remembered five
	 * minutes (`remembered()`).
	 *
	 * @return array|WP_Error Base ID as the setting stores it => its name.
	 */
	private static function bases_read() {
		return self::from_airtable(
			'bases',
			static function () {
				$read = self::remembered(
					'bases',
					static function () {
						return ( new WPCPM_Airtable() )->read_bases();
					}
				);

				if ( is_wp_error( $read ) ) {
					return $read;
				}

				$bases = array();

				foreach ( $read as $id => $name ) {
					$id = self::stored_form( $id );

					if ( '' !== $id ) {
						$bases[ $id ] = (string) $name;
					}
				}

				return $bases;
			}
		);
	}

	/**
	 * The base's schema as `WPCPM_Airtable::cached_schema()` holds or reads it, without its age, or
	 * the error its read failed with, read once a request, a failure remembered five minutes
	 * (`remembered()`).
	 *
	 * @return array|WP_Error Table ID => the table, as `WPCPM_Airtable::fetch_schema()` answers it.
	 */
	private static function schema_read() {
		return self::from_airtable(
			'schema',
			static function () {
				$read = self::remembered(
					'schema',
					static function () {
						return ( new WPCPM_Airtable() )->cached_schema();
					}
				);

				if ( is_wp_error( $read ) ) {
					return $read;
				}

				return isset( $read['schema'] ) && is_array( $read['schema'] ) ? $read['schema'] : new WP_Error( 'wpcpm_airtable_no_schema', '' );
			}
		);
	}

	/**
	 * One table's columns, or the error: the schema's, or the table missing from it.
	 *
	 * @param string $table Table ID or name.
	 * @return array|WP_Error
	 */
	private static function columns_read( $table ) {
		$schema = self::schema_read();

		if ( is_wp_error( $schema ) || ! class_exists( 'WPCPM_Airtable' ) ) {
			return $schema;
		}

		$found = WPCPM_Airtable::table_in( $schema, $table );

		if ( is_wp_error( $found ) ) {
			return $found;
		}

		$names   = isset( $found['fields'] ) && is_array( $found['fields'] ) ? array_map( 'strval', array_keys( $found['fields'] ) ) : array();
		$primary = isset( $found['primary'] ) ? (string) $found['primary'] : '';

		if ( '' !== $primary && in_array( $primary, $names, true ) ) {
			$names = array_merge( array( $primary ), array_values( array_diff( $names, array( $primary ) ) ) );
		}

		$columns = array();

		foreach ( $names as $name ) {
			$stored = self::stored_form( $name );

			if ( '' !== $stored ) {
				$columns[ $stored ] = $name;
			}
		}

		return $columns;
	}

	/**
	 * One column's options, or the error their read failed with, read once a request, and from the
	 * request's one read of the schema when the client does not hold them already.
	 *
	 * @param string $table Table ID or name.
	 * @param string $field The column's name.
	 * @return array|WP_Error Option as the setting stores it => its name.
	 */
	private static function options_read( $table, $field ) {
		return self::from_airtable(
			'options|' . $table . '|' . $field,
			static function () use ( $table, $field ) {
				$read = ( new WPCPM_Airtable() )->read_field_options(
					$table,
					$field,
					static function () {
						return self::schema_read();
					}
				);

				if ( is_wp_error( $read ) ) {
					return $read;
				}

				$options = array();

				foreach ( $read as $option ) {
					$stored = self::stored_form( $option );

					if ( '' !== $stored ) {
						$options[ $stored ] = (string) $option;
					}
				}

				return $options;
			}
		);
	}

	/**
	 * The table setting and the column a status list is read from.
	 *
	 * @param string $reader   `mentor_statuses`, `report_statuses` or `institution_stages`.
	 * @param array  $settings The settings.
	 * @return string[] The table, then the column.
	 */
	private static function status_column( $reader, array $settings ) {
		if ( 'institution_stages' === $reader ) {
			$fields = class_exists( 'WPCPM_Institutions_Sync' ) ? WPCPM_Institutions_Sync::fields() : array();

			return array( isset( $settings['institutions_table'] ) ? (string) $settings['institutions_table'] : '', isset( $fields['stage'] ) ? (string) $fields['stage'] : 'Current Stage' );
		}

		$fields = class_exists( 'WPCPM_Mentors_Sync' ) ? WPCPM_Mentors_Sync::fields() : array();

		if ( 'mentor_statuses' === $reader ) {
			return array( isset( $settings['mentors_table'] ) ? (string) $settings['mentors_table'] : '', isset( $fields['mentor_status'] ) ? (string) $fields['mentor_status'] : 'Status' );
		}

		return array( isset( $settings['reports_table'] ) ? (string) $settings['reports_table'] : '', isset( $fields['report_status'] ) ? (string) $fields['report_status'] : 'Status' );
	}

	/**
	 * The program managers, under Administrators, read now (`managers()`).
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function read_managers() {
		$users          = class_exists( 'WPCPM_Institutions' ) ? WPCPM_Institutions::managers() : array();
		$administrators = array();

		foreach ( (array) $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$email = trim( (string) $user->user_email );

			if ( '' === $email ) {
				continue;
			}

			$name = trim( (string) $user->display_name );

			$administrators[ $email ] = '' !== $name ? $name : $email;
		}

		uasort( $administrators, 'strnatcasecmp' );

		return array_filter( array( __( 'Administrators', 'wpcredits-program-manager' ) => $administrators ) );
	}

	/**
	 * A value as a setting stores it (`WPCPM_Settings::clean_list()`), so a value a list offers is
	 * the very string a save of it keeps, and a value saved is found again in the list: a run of
	 * spaces folded into one, a "<" that opens no tag kept as `&lt;`.
	 *
	 * @param mixed $value The value as its source spells it.
	 * @return string '' for nothing.
	 */
	private static function stored_form( $value ) {
		$listed = WPCPM_Settings::clean_list( array( $value ) );

		return array() === $listed ? '' : $listed[0];
	}

	/**
	 * A read that asks Airtable, of the bases or of the schema: answered from its failure when it failed
	 * less than `WPCPM_Airtable::FAILED_TTL` seconds ago with the token and base set now, or read now,
	 * and remembered when it fails. With Airtable hanging rather than refusing, each read waits the
	 * client's twenty seconds, and each draw of a screen that lists from these two reads would wait
	 * again. The failure is kept whole, its code, words and status, so the sentence saying why a list
	 * is missing names the cause the read failed with. A read that asked Airtable nothing, for want of
	 * a token or a base, is not remembered: it cost no wait, and the setting that ends it may be saved
	 * at any moment.
	 *
	 * @param string   $what `bases` or `schema`.
	 * @param callable $read Reads it; answers an array, or the error the read failed with.
	 * @return array|WP_Error
	 */
	private static function remembered( $what, callable $read ) {
		$settings = WPCPM_Settings::get();
		$key      = $what . '|' . hash( 'sha256', ( isset( $settings['api_token'] ) ? (string) $settings['api_token'] : '' ) . '|' . ( isset( $settings['base_id'] ) ? (string) $settings['base_id'] : '' ) );
		$held     = get_transient( WPCPM_Airtable::FAILED_TRANSIENT );
		$held     = is_array( $held ) ? $held : array();

		if ( isset( $held[ $key ]['at'], $held[ $key ]['code'] ) && time() - (int) $held[ $key ]['at'] < WPCPM_Airtable::FAILED_TTL ) {
			return new WP_Error(
				(string) $held[ $key ]['code'],
				isset( $held[ $key ]['message'] ) ? (string) $held[ $key ]['message'] : '',
				isset( $held[ $key ]['data'] ) ? $held[ $key ]['data'] : null
			);
		}

		$answer = call_user_func( $read );

		if ( is_wp_error( $answer ) && ! in_array( $answer->get_error_code(), array( 'wpcpm_no_token', 'wpcpm_no_base' ), true ) ) {
			$held[ $key ] = array(
				'at'      => time(),
				'code'    => $answer->get_error_code(),
				'message' => $answer->get_error_message(),
				'data'    => $answer->get_error_data(),
			);

			set_transient( WPCPM_Airtable::FAILED_TRANSIENT, $held, WPCPM_Airtable::FAILED_TTL );
		}

		return $answer;
	}

	/**
	 * A list read from Airtable this request, or read now and kept for the rest of it, a failure
	 * included; nothing, and nothing asked, where the plugin's Airtable client is not loaded.
	 *
	 * @param string   $key  What was asked.
	 * @param callable $read Reads the list; answers an array, or the error the read failed with.
	 * @return array|WP_Error
	 */
	private static function from_airtable( $key, callable $read ) {
		if ( ! array_key_exists( $key, self::$read ) ) {
			$answer             = class_exists( 'WPCPM_Airtable' ) ? call_user_func( $read ) : array();
			self::$read[ $key ] = is_array( $answer ) || is_wp_error( $answer ) ? $answer : array();
		}

		return self::$read[ $key ];
	}
}
