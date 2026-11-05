<?php
/**
 * Students module - the public form a student registers through (1.123.54).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Loaded here as well as by the plugin's loader, as the mentor form loads it: the suites require
// this class with a handful of others, and `require_once` is idempotent by real path.
require_once dirname( __DIR__ ) . '/class-wpcpm-form-stash.php';

/**
 * The form at /student-registration/, and everything that guards it (design of 5 November 2026,
 * sections 3, 3.1, 3.2, 3.3 and 7).
 *
 * The mentor application form's shape with the student's questions, which are the program's Airtable
 * form's fourteen, in its order and with its words (section 3). The order the checks run in is the
 * mentor form's, through `WPCPM_Form_Guard`, under this form's own keys: the per-actor ceiling (thirty
 * an hour, the product owner's call, since a whole class registers from one campus network), the
 * nonce, the switch and what the form needs to be drawn, the honeypot, the dwell token, consent, the
 * answers, requiredness, the two site-wide limits (past 200 a day a registration is held for an
 * Administrator, past 40 it waits for the hourly job), then the registration, which is handed to
 * `WPCPM_Student_Registration_Process` once the answer has gone (`process_after_answer()`).
 *
 * **The page answers first** (the product owner, 5 November 2026). The steps after the guard take a
 * different time for each kind of address, so the redirect never waits for them: the process runs
 * on `shutdown`, after the server has let the answer go, or, where it cannot, in the hourly job.
 *
 * **Every path ends on one page.** Whatever became of the registration (written, an account found, an
 * address held already, Airtable down, a held lock, caught as spam), the student is sent back to the
 * same bare `sent` answer and reads the same sentence: the page never says whether an address belongs
 * to a student. The email to the address is where the difference is told.
 *
 * **Nothing here reads Airtable.** The page is drawn from the site's copies: the institutions the
 * institutions sync holds, at the stages the setting names (never a test institution, by the site's
 * rule, `WPCPM_Test_Institutions::is_test()`, except to an Administrator), and the Languages table's
 * copy. The process that runs once a submission's answer has gone is the one place a public request reaches Airtable.
 *
 * The page is deliberately **not gated**, as the other forms' are not: see `ensure_page()`. Its
 * introduction and its thank-you are the owner's to word on the page: the page starts with the Airtable
 * form's introduction, and the block's `thanks` takes the thank-you's place.
 */
final class WPCPM_Student_Registration_Form {

	const SHORTCODE = 'wpcpm_student_registration';
	const BLOCK     = 'wpcpm/student-registration';
	const OPT_PAGE  = 'wpcpm_student_registration_page_id';

	/** The page's slug. Chosen once and never renamed: a rename breaks every link anybody was sent. */
	const SLUG = 'student-registration';

	/** The `admin_post_` action the form posts to, registered for logged-out visitors too. */
	const ACTION_SUBMIT = 'wpcpm_student_register';

	/** The dwell token's scope: a token minted for another form is a forgery here. */
	const DWELL_SCOPE = 'wpcpm-student-registration-dwell';

	/** The per-actor ceiling's key prefix. */
	const ACTOR_PREFIX = 'student-register';

	/** The site-wide ceiling's key: the guard's forty a day. */
	const SITE_KEY = 'student-register-site';

	/**
	 * The second daily limit (the product owner, 5 November 2026): past this many registrations in a
	 * day, one is still stored and thanked, but held for an Administrator, who writes it with Write it
	 * now on the Registrations tab.
	 */
	const HOLD_PER_DAY = 200;

	/** The second daily limit's key. */
	const SITE_HOLD_KEY = 'student-register-hold';

	/** How long a stashed failure survives the redirect, in minutes. */
	const TRANSIENT_MINUTES = 30;

	/** Prefix for those stashes. */
	const TRANSIENT_PREFIX = 'wpcpm_sreg_';

	/**
	 * The posts one network address may send in an hour: thirty, where the other forms take five, since
	 * a whole class registers from one campus network (the product owner, 5 November 2026).
	 */
	const PER_HOUR = 30;

	/** The redirect's argument when there is a stash: its id. Nothing typed is ever in the address. */
	const QUERY_STASH = 'wpcpm_sreg';

	/** The redirect's argument when there is no stash: the outcome, as itself. */
	const QUERY_OUTCOME = 'wpcpm_sreg_said';

	/** The posted array holding the answers, keyed by question key. */
	const FIELD_ANSWERS = 'wpcpm_student_registration';

	/**
	 * The honeypot's field name. Not a word a browser autofills for a person: a genuine registration
	 * filed as spam would be a student lost.
	 */
	const HONEYPOT = 'wpcpm_confirm_campus';

	/** The dwell token's field name. */
	const TOKEN_FIELD = 'wpcpm_student_registration_token';

	/** The program's public address, which the page's introduction gives. */
	const CONTACT = 'education@wordpressfoundation.org';

	/** The Students Interest Form, which stays on Airtable: for a student whose institution is not listed. */
	const INTEREST_URL = 'https://airtable.com/appIzQKfwTn5dyPVp/shr6Gf1mMMEAxevw4';

	/** Longest free-text answer kept, in characters. */
	const MAX_TEXT = 2000;

	/**
	 * What lets the answer go before the work after it, in place of PHP-FPM's
	 * `fastcgi_finish_request()`: a callable, false for a server with none, or null for PHP's own
	 * (`for_tests()`).
	 *
	 * @var callable|false|null
	 */
	private static $finisher = null;

	/**
	 * Replace what lets the answer go before the work after it. Test-only.
	 *
	 * The suites run under the CLI, which has no `fastcgi_finish_request()`. Nothing in the plugin
	 * calls this; bin/test-student-registration.php does, with a callable that keeps the moment the
	 * answer would have gone, or false to play a server that cannot finish an answer early.
	 *
	 * @param callable|false|null $finisher Called in place of `fastcgi_finish_request()`, false for
	 *                                      none, or null for the real thing.
	 */
	public static function for_tests( $finisher = null ) {
		self::$finisher = $finisher;
	}

	/**
	 * Hooks. The submit is registered for logged-out visitors as well, which is the whole point: a
	 * student has no account.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );
	}

	/**
	 * Register the shortcode, the block and the stylesheet (the mentor form's, under its handle).
	 */
	public static function register() {
		if ( ! wp_style_is( WPCPM_Mentor_Application_Form::STYLE, 'registered' ) ) {
			wp_register_style( WPCPM_Mentor_Application_Form::STYLE, WPCPM_PLUGIN_URL . 'assets/css/application.css', array(), WPCPM_VERSION );
		}

		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script( 'wpcpm-forms', WPCPM_PLUGIN_URL . 'assets/js/forms.js', array(), WPCPM_VERSION, true );
		}

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/student-registration';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir, array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );
		}
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return self::render( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * Create the form's page if it is missing, or adopt one at the slug, as it stands.
	 *
	 * A page this makes starts with the Airtable form's introduction, three paragraphs the owner edits
	 * on the page, then the form's block.
	 *
	 * **Left ungated on purpose**, as the other forms' pages are: an absent access level means
	 * public, and the people this page is for have no account.
	 *
	 * @return int Page ID, or 0 on failure.
	 */
	public static function ensure_page() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( $page_id ) {
			$existing = get_post( $page_id );

			if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
				return $page_id;
			}
		}

		$existing = get_page_by_path( self::SLUG );

		if ( $existing instanceof WP_Post ) {
			update_option( self::OPT_PAGE, (int) $existing->ID, false );

			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Student registration', 'wpcredits-program-manager' ),
				'post_name'    => self::SLUG,
				'post_content' => self::starting_content(),
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_option( self::OPT_PAGE, (int) $page_id, false );

		return (int) $page_id;
	}

	/**
	 * What a page this plugin makes starts with: the Airtable form's introduction, in three paragraph
	 * blocks, then the form's block.
	 *
	 * @return string Block markup.
	 */
	public static function starting_content() {
		$paragraphs = array(
			esc_html__( 'Welcome!', 'wpcredits-program-manager' ),
			esc_html__( 'Please fill out this short form to express your interest in joining the program. It only takes a few minutes and helps us better understand your background and availability.', 'wpcredits-program-manager' ),
			sprintf(
				/* translators: %s: the program's email address, as a link. */
				esc_html__( 'If you have any questions before submitting your interest, feel free to contact us at %s', 'wpcredits-program-manager' ),
				'<a href="mailto:' . esc_attr( self::CONTACT ) . '">' . esc_html( self::CONTACT ) . '</a>'
			),
		);
		$blocks     = array();

		foreach ( $paragraphs as $paragraph ) {
			$blocks[] = "<!-- wp:paragraph {\"className\":\"wpcpm-application-intro\"} -->\n<p class=\"wpcpm-application-intro\">" . $paragraph . "</p>\n<!-- /wp:paragraph -->";
		}

		$blocks[] = '<!-- wp:' . self::BLOCK . ' /-->';

		return implode( "\n\n", $blocks );
	}

	/**
	 * The form's page address, while it is published.
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}

	/**
	 * Keep this one page out of every cache: it carries a nonce and a single-use dwell token, and a
	 * cached copy would hand one token to everybody.
	 */
	public static function no_cache() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || ! is_page( $page_id ) ) {
			return;
		}

		nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The name every page cache reads.
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/*
	 * The questions
	 * --------------------------------------------------------------------
	 */

	/**
	 * The institutions the form offers: the ones the site holds from the institutions sync whose
	 * current stage is one of the stages the setting names, by Airtable record ID to name, trimmed, A to
	 * Z. A test institution (`WPCPM_Test_Institutions::is_test()`: its name starts with the word TEST in
	 * capitals, no letter or digit after it) is never offered to the public, whatever its stage.
	 *
	 * An Administrator is offered, in the same list, every test institution the institutions index
	 * holds, whatever its stage and whatever the setting says, its name followed by "(Test)": the first
	 * live test of the form uses the shared test institution. The submit checks a posted institution
	 * against this same list, so a test institution's record ID posted by anybody else is refused as an
	 * institution the form does not offer. The page is not cached for anybody, so an Administrator's
	 * list never reaches a visitor.
	 *
	 * @return array<string, string>
	 */
	public static function institutions() {
		return self::offered( current_user_can( WPCPM_Roles::CAP_MANAGE ) );
	}

	/**
	 * The institutions the form offers, with the test institutions of an Administrator's list or without
	 * them (`institutions()`).
	 *
	 * @param bool $with_tests Whether every test institution the index holds is added, marked.
	 * @return array<string, string>
	 */
	private static function offered( $with_tests ) {
		$stages = WPCPM_Settings::get_value( 'student_registration_stages', array( 'Confirmed' ) );
		$wanted = array();

		foreach ( is_array( $stages ) ? $stages : array() as $stage ) {
			if ( is_scalar( $stage ) && '' !== trim( (string) $stage ) ) {
				$wanted[] = trim( (string) $stage );
			}
		}

		$names = array();
		$shown = array();

		foreach ( WPCPM_Institutions_Index::rows() as $id => $row ) {
			if ( ! is_array( $row ) || ! isset( $row['name'], $row['stage'] ) || ! is_scalar( $row['name'] ) || ! is_scalar( $row['stage'] ) ) {
				continue;
			}

			$name = trim( (string) $row['name'] );

			if ( '' === $name ) {
				continue;
			}

			if ( WPCPM_Test_Institutions::is_test( $name ) ) {
				// Only an Administrator is offered one, at any stage.
				if ( ! $with_tests ) {
					continue;
				}

				$shown[ (string) $id ] = WPCPM_Test_Institutions::label( $name, $name );
			} elseif ( in_array( trim( (string) $row['stage'] ), $wanted, true ) ) {
				$shown[ (string) $id ] = $name;
			} else {
				continue;
			}

			$names[ (string) $id ] = $name;
		}

		// A to Z without regard to case, the record ID breaking a tie: `array_multisort()` and not a
		// `usort()` that keeps equal names in whatever order it likes.
		$ids   = array_map( 'strval', array_keys( $names ) );
		$lower = array_map( 'mb_strtolower', array_values( $names ) );

		array_multisort( $lower, SORT_ASC, SORT_STRING, $ids, SORT_ASC, SORT_STRING );

		$sorted = array();

		foreach ( $ids as $id ) {
			$sorted[ $id ] = $shown[ $id ];
		}

		return $sorted;
	}

	/**
	 * The languages the form offers: the names of the copy of Airtable's Languages table, A to Z, each
	 * once. The name is what is stored; the row's link is found by it.
	 *
	 * @return string[]
	 */
	public static function languages() {
		return array_values( array_unique( array_map( 'strval', WPCPM_Student_Airtable_Row::languages() ) ) );
	}

	/**
	 * The questions, keyed by the site field each answer is kept under: the Airtable form's fourteen, in
	 * its order and with its labels and help, as the design gives them (section 3).
	 *
	 * The two boxes to tick carry a heading (`label`) and the words beside the box (`box`): the
	 * compliance declaration's and the privacy policy's are the Airtable form's sentences, the policy's
	 * linked by its address.
	 *
	 * @return array<string, array> Key => spec: `label`, `type`, `required`, and per type `maxlength`,
	 *                              `auto`, `help`, `note`, `min`, `max`, `box`.
	 */
	public static function fields() {
		$policy = WPCPM_Form_Guard::policy_url();

		return array(
			'name'        => array(
				'label'     => __( 'Full Name', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 200,
				'auto'      => 'name',
			),
			'email'       => array(
				'label'     => __( 'Email', 'wpcredits-program-manager' ),
				'type'      => 'email',
				'required'  => true,
				'maxlength' => 254,
				'auto'      => 'email',
			),
			'institution' => array(
				'label'    => __( 'Educational Institution', 'wpcredits-program-manager' ),
				'type'     => 'institution',
				'required' => true,
				'note'     => sprintf(
					/* translators: 1: an opening link tag to the Students Interest Form, 2: its closing tag. */
					__( 'Is your institution not on the list? Ask a faculty member or your administration office to contact us, or fill in the %1$sstudent interest form%2$s.', 'wpcredits-program-manager' ),
					'<a href="' . esc_url( self::INTEREST_URL ) . '">',
					'</a>'
				),
			),
			'study'       => array(
				'label'    => __( 'Department / Area of Study', 'wpcredits-program-manager' ),
				'type'     => 'study',
				'required' => true,
			),
			'tutor'       => array(
				'label'     => __( 'Tutor Name - The professor or main contact person at your educational institution', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => false,
				'maxlength' => 120,
				'help'      => __( 'If your educational institution doesn\'t require you to indicate a tutor, you can skip this question.', 'wpcredits-program-manager' ),
			),
			'tutor_email' => array(
				'label'     => __( 'Your tutor\'s email address', 'wpcredits-program-manager' ),
				'type'      => 'email',
				'required'  => false,
				'maxlength' => 254,
			),
			'start'       => array(
				'label'    => __( 'Preferred Start Date', 'wpcredits-program-manager' ),
				'type'     => 'date',
				'required' => true,
			),
			'end'         => array(
				'label'    => __( 'Estimated End Date', 'wpcredits-program-manager' ),
				'type'     => 'date',
				'required' => true,
			),
			'hours'       => array(
				'label'    => __( 'Total Expected Hours (Approximate)', 'wpcredits-program-manager' ),
				'type'     => 'number',
				'required' => true,
				'min'      => 1,
				'max'      => 2000,
				'help'     => __( 'Format: Decimal', 'wpcredits-program-manager' ),
			),
			'languages'   => array(
				'label'    => __( 'Languages You Speak', 'wpcredits-program-manager' ),
				'type'     => 'languages',
				'required' => true,
				'help'     => __( 'Select as many as you speak.', 'wpcredits-program-manager' ),
			),
			'preferred'   => array(
				'label'    => __( 'Preferred Language', 'wpcredits-program-manager' ),
				'type'     => 'preferred',
				'required' => true,
				'help'     => __( 'Select only one.', 'wpcredits-program-manager' ),
			),
			'access'      => array(
				'label'    => __( 'Do you have any accessibility needs we should be aware of?', 'wpcredits-program-manager' ),
				'type'     => 'textarea',
				'required' => false,
				'help'     => __( 'Optional - Leave blank if none.', 'wpcredits-program-manager' ),
			),
			'english'     => array(
				'label'    => __( 'Compliance declaration', 'wpcredits-program-manager' ),
				'type'     => 'statement',
				'required' => true,
				'box'      => __( 'I understand that the course is conducted in English, requires written communication, and involves proactive, independent remote work.', 'wpcredits-program-manager' ),
			),
			'consent'     => array(
				'label'    => __( 'Privacy Policy Compliance', 'wpcredits-program-manager' ),
				'type'     => 'consent',
				'required' => true,
				'box'      => sprintf(
					/* translators: %s: the WordPress.org privacy policy's address, as a link. */
					__( 'By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (%s).', 'wpcredits-program-manager' ),
					'<a href="' . esc_url( $policy ) . '">' . esc_html( $policy ) . '</a>'
				),
			),
		);
	}

	/**
	 * Clean one posted answer, or refuse it with a reason.
	 *
	 * An empty answer is accepted as empty and left for requiredness (`add_required()`), as the mentor
	 * form leaves it. The name and the tutor are cleaned as `WPCPM_Institution_Import::clean_row()`
	 * cleans an imported student's: a control character is refused on the raw cell, before the cleaner
	 * removes it, and so is a spreadsheet formula.
	 *
	 * @param string $key The question's key.
	 * @param mixed  $raw The posted value.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	public static function clean( $key, $raw ) {
		$fields = self::fields();

		if ( ! isset( $fields[ $key ] ) ) {
			return self::refuse( 'unknown_field' );
		}

		$spec = $fields[ $key ];
		$type = $spec['type'];

		if ( in_array( $type, array( 'consent', 'statement' ), true ) ) {
			// A tick the strict way: "yes" is not one, and neither is an array.
			return self::accept( WPCPM_Form_Guard::consented( $raw ) );
		}

		if ( 'languages' === $type ) {
			return self::accept( self::picked( $raw, self::languages() ) );
		}

		if ( in_array( $type, array( 'institution', 'preferred' ), true ) ) {
			$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';

			if ( '' === $value ) {
				return self::accept( '' );
			}

			$offered = 'institution' === $type ? array_keys( self::institutions() ) : self::languages();

			return in_array( $value, array_map( 'strval', $offered ), true ) ? self::accept( $value ) : self::refuse( 'bad_choice' );
		}

		if ( 'study' === $type ) {
			return self::blank_for_null(
				WPCPM_Field_Value::clean(
					$raw,
					array(
						'type'    => 'select',
						'choices' => WPCPM_Institution_Student_Form::choices( 'field_of_study' ),
					)
				)
			);
		}

		if ( 'date' === $type ) {
			return self::blank_for_null( WPCPM_Field_Value::clean( $raw, array( 'type' => 'date' ) ) );
		}

		if ( 'email' === $type ) {
			return WPCPM_Field_Value::clean( $raw, array( 'type' => 'email' ) );
		}

		if ( 'number' === $type ) {
			return self::clean_hours( $raw, $spec );
		}

		if ( 'text' === $type ) {
			return self::clean_line( $raw, $spec, $key );
		}

		// The free text keeps what was typed or says why it cannot: the cleaner would take a run from
		// a "<" to a ">" out of it, as a tag, and a student writing about code would lose words.
		if ( WPCPM_Typed_Text::cleaner_loses( $raw, 'lines' ) ) {
			return self::refuse( 'markup' );
		}

		return WPCPM_Field_Value::clean(
			$raw,
			array(
				'type'     => 'textarea',
				'max_text' => self::MAX_TEXT,
			)
		);
	}

	/**
	 * A line of text the way the import cleans a student's name and tutor: the control characters
	 * refused on the raw cell, the cleaner's collapsing of white space applied, a spreadsheet formula
	 * refused, and the length held.
	 *
	 * @param mixed  $raw  The posted value.
	 * @param array  $spec The question's spec.
	 * @param string $key  The question's key.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	private static function clean_line( $raw, array $spec, $key ) {
		if ( ! is_scalar( $raw ) ) {
			return self::refuse( 'not_scalar' );
		}

		$raw  = (string) $raw;
		$text = WPCPM_Institution_Import::text( $raw );

		if ( '' === $text ) {
			return self::accept( '' );
		}

		if ( WPCPM_Institution_Import::has_control( $raw ) ) {
			return self::refuse( 'control' );
		}

		if ( WPCPM_Institution_Import::is_formula( $text ) ) {
			return self::refuse( 'formula' );
		}

		$length = mb_strlen( $text );

		if ( 'name' === $key && ( $length < 2 || $length > (int) $spec['maxlength'] ) ) {
			return self::refuse( 'name_length' );
		}

		if ( 'name' !== $key && $length > (int) $spec['maxlength'] ) {
			return self::refuse( 'too_long' );
		}

		return self::accept( $text );
	}

	/**
	 * The hours: a decimal, a comma read as the point, held to one place and kept as the number it
	 * then reads, `2.5` or `150.6` or `3`.
	 *
	 * @param mixed $raw  The posted value.
	 * @param array $spec The question's spec.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	private static function clean_hours( $raw, array $spec ) {
		if ( ! is_scalar( $raw ) ) {
			return self::refuse( 'not_scalar' );
		}

		$typed = str_replace( ',', '.', trim( (string) $raw ) );

		if ( '' === $typed ) {
			return self::accept( '' );
		}

		if ( ! is_numeric( $typed ) ) {
			return self::refuse( 'not_a_number' );
		}

		$hours = round( (float) $typed, 1 );

		if ( $hours < $spec['min'] ) {
			return self::refuse( 'below_min' );
		}

		if ( $hours > $spec['max'] ) {
			return self::refuse( 'above_max' );
		}

		return self::accept( rtrim( rtrim( number_format( $hours, 1, '.', '' ), '0' ), '.' ) );
	}

	/**
	 * The choices ticked, each once, in the list's own order; one the list does not offer is dropped
	 * rather than refusing the answer, since only a hand-made request can post one.
	 *
	 * @param mixed    $raw     The posted value: an array of choices, or one.
	 * @param string[] $choices The list.
	 * @return string[]
	 */
	private static function picked( $raw, array $choices ) {
		$posted = array();

		foreach ( (array) $raw as $one ) {
			if ( is_scalar( $one ) ) {
				$posted[] = trim( (string) $one );
			}
		}

		return array_values( array_intersect( $choices, $posted ) );
	}

	/**
	 * A cleaned value of `WPCPM_Field_Value` with its empty answer, which it gives as null, kept as
	 * an empty text.
	 *
	 * @param array $cleaned What the cleaner answered.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	private static function blank_for_null( array $cleaned ) {
		return $cleaned['ok'] && null === $cleaned['value'] ? self::accept( '' ) : $cleaned;
	}

	/**
	 * A cleaned value.
	 *
	 * @param mixed $value The value.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	private static function accept( $value ) {
		return array(
			'ok'      => true,
			'value'   => $value,
			'problem' => '',
		);
	}

	/**
	 * A refused value, and why.
	 *
	 * @param string $problem Short key, in `WPCPM_Field_Value`'s vocabulary or this form's own.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	private static function refuse( $problem ) {
		return array(
			'ok'      => false,
			'value'   => null,
			'problem' => (string) $problem,
		);
	}

	/**
	 * Clean every answer the form asks, walked from `fields()` and never from the request, so a key
	 * nobody asked about cannot become a stored answer. Consent never comes back as a value: no
	 * failure path can tick it again for the student.
	 *
	 * The institution's name is kept beside its ID (`institution_name`), as the row's receiving email
	 * names it. Two problems belong to a pair of answers and are found once both are cleaned: an end
	 * date before the start date, and a preferred language the student did not tick among the ones they
	 * speak. Each leaves its answer in the box.
	 *
	 * A free text, a name or a tutor that is refused has no cleaned value to hand back, so what was
	 * typed comes back beside the values as `typed`, cut to the length of its box, for the form to draw
	 * in the box it was typed in and for nothing else. It is never one of the values, since a row filed
	 * as spam stores those.
	 *
	 * @param array $posted The posted answers, unslashed, keyed by question key.
	 * @return array{values:array,problems:array,typed:array}
	 */
	private static function clean_all( array $posted ) {
		$values      = array();
		$problems    = array();
		$typed       = array();
		$institution = self::institutions();

		foreach ( self::fields() as $key => $spec ) {
			$raw   = isset( $posted[ $key ] ) ? $posted[ $key ] : '';
			$clean = self::clean( $key, $raw );

			if ( empty( $clean['ok'] ) ) {
				$problems[ $key ] = $clean['problem'];

				// An array only a crafted post sends, and text that is not valid UTF-8, are no typing
				// worth handing back.
				if ( self::hands_back_typing( $spec ) && is_scalar( $raw ) && mb_check_encoding( (string) $raw, 'UTF-8' ) ) {
					$kept = mb_substr( (string) $raw, 0, isset( $spec['maxlength'] ) ? (int) $spec['maxlength'] : self::MAX_TEXT );

					if ( '' !== $kept ) {
						$typed[ $key ] = $kept;
					}
				}

				continue;
			}

			$values[ $key ] = $clean['value'];

			if ( 'institution' === $key && '' !== $clean['value'] && isset( $institution[ $clean['value'] ] ) ) {
				// The name the index holds, not the list's text: a test institution's reads "(Test)" there.
				$row                        = WPCPM_Institutions_Index::row( $clean['value'] );
				$values['institution_name'] = null === $row ? $institution[ $clean['value'] ] : trim( (string) $row['name'] );
			}
		}

		unset( $values['consent'] );

		if ( ! isset( $problems['start'], $problems['end'] ) && ! empty( $values['start'] ) && ! empty( $values['end'] ) && (string) $values['end'] < (string) $values['start'] ) {
			$problems['end'] = 'end_before_start';
		}

		if ( ! isset( $problems['preferred'] ) && ! empty( $values['preferred'] ) && ! empty( $values['languages'] ) && ! in_array( $values['preferred'], (array) $values['languages'], true ) ) {
			$problems['preferred'] = 'preferred_not_spoken';
		}

		return array(
			'values'   => $values,
			'problems' => $problems,
			'typed'    => $typed,
		);
	}

	/**
	 * Whether a question's refused answer is handed back as it was typed: the three a person types
	 * freely and may have to correct, the name, the tutor and the accessibility needs.
	 *
	 * @param array $spec The question's spec.
	 * @return bool
	 */
	private static function hands_back_typing( array $spec ) {
		return in_array( $spec['type'], array( 'text', 'textarea' ), true );
	}

	/**
	 * Add the requiredness problems, once, after everything is cleaned: a required question left
	 * blank, and a required statement left unticked.
	 *
	 * @param array $values   Cleaned values.
	 * @param array $problems Problems so far.
	 * @return array
	 */
	private static function add_required( array $values, array $problems ) {
		foreach ( self::fields() as $key => $spec ) {
			if ( 'consent' === $spec['type'] || isset( $problems[ $key ] ) || empty( $spec['required'] ) ) {
				continue;
			}

			$value = isset( $values[ $key ] ) ? $values[ $key ] : null;

			if ( 'statement' === $spec['type'] ) {
				if ( true !== $value ) {
					$problems[ $key ] = 'unticked';
				}

				continue;
			}

			if ( self::is_blank( $value ) ) {
				$problems[ $key ] = 'required';
			}
		}

		return $problems;
	}

	/**
	 * Whether a cleaned value counts as no answer.
	 *
	 * @param mixed $value The value.
	 * @return bool
	 */
	private static function is_blank( $value ) {
		if ( null === $value || false === $value ) {
			return true;
		}

		if ( is_array( $value ) ) {
			return empty( $value );
		}

		return '' === trim( (string) $value );
	}

	/*
	 * Drawing the page
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether the program is taking student registrations on this site right now: a setting, because
	 * "on" means accepting writes from anybody on the internet.
	 *
	 * @return bool
	 */
	public static function is_open() {
		return (bool) WPCPM_Settings::get_value( 'student_registrations_enabled', false );
	}

	/**
	 * Why the form cannot be drawn, or '' when it can: the switch is off, Airtable is not connected (so
	 * no registration could be written), no institution is at a stage the setting offers, or the
	 * languages have not been read yet. Each is the site's state alone; none reads Airtable.
	 *
	 * @return string
	 */
	private static function closed_reason() {
		if ( ! self::is_open() ) {
			return __( 'The student registration form is switched off. Turn on "Registrations from students" on the Students and mentors tab of WPCredits Program > Settings when the program is ready to take them.', 'wpcredits-program-manager' );
		}

		if ( ! WPCPM_Settings::is_connected() ) {
			return __( 'This form is not shown yet: Airtable is not connected, so no registration could be written. Connect it on the Connection tab of WPCredits Program > Settings.', 'wpcredits-program-manager' );
		}

		// The public's list: a test institution offered to an Administrator does not open the form.
		if ( empty( self::offered( false ) ) ) {
			return __( 'This form is not shown yet: no institution the site holds is at a stage ticked under Institutions offered, on the Students and mentors tab of WPCredits Program > Settings. New partners arrive with the institutions sync, every three hours.', 'wpcredits-program-manager' );
		}

		if ( empty( self::languages() ) ) {
			return __( 'This form is not shown yet: the languages have not been read from Airtable yet. They are read within the hour.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * Render the answer this request carries, the form, the thank-you, or nothing.
	 *
	 * The form is drawn for nobody while it cannot be (`closed_reason()`); every visitor reads the
	 * `closed` sentence in its place, once, and an Administrator is told why in one sentence more. Not
	 * where no form would be drawn anyway, after a sent registration. The editor's preview is a static
	 * summary, because a live one would mint a nonce and a single-use token on every keystroke.
	 *
	 * @param array $atts Shortcode or block attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title'  => '',
				'thanks' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		wp_enqueue_style( WPCPM_Mentor_Application_Form::STYLE );
		wp_enqueue_script( 'wpcpm-forms' );

		$closed = self::closed_reason();

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '' !== $closed ? self::sentence( $closed ) : self::preview();
		}

		$stash   = self::read_stash();
		$outcome = isset( $stash['outcome'] ) ? (string) $stash['outcome'] : '';

		ob_start();

		if ( 'sent' === $outcome ) {
			self::render_confirmation( (string) $atts['thanks'] );
		} else {
			self::render_message( $outcome );
		}

		$draws = ! in_array( $outcome, array( 'sent', 'busy' ), true );

		if ( '' !== $closed ) {
			if ( $draws && 'closed' !== $outcome ) {
				self::render_message( 'closed' );
			}

			if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
				printf( '<p class="wpcpm-application__notice">%s</p>', esc_html( $closed ) );
			}
		} elseif ( $draws ) {
			self::render_form(
				self::values_to_draw( $stash ),
				isset( $stash['problems'] ) && is_array( $stash['problems'] ) ? $stash['problems'] : array()
			);
		}

		$body = (string) ob_get_clean();

		if ( '' === $body ) {
			return '';
		}

		$title = '' !== $atts['title'] ? '<h2 class="wpcpm-application__title">' . esc_html( $atts['title'] ) . '</h2>' : '';

		return '<div class="wpcpm-application">' . $title . $body . '</div>';
	}

	/**
	 * The answers a redrawn form shows: the cleaned ones, and, for a name, a tutor or a free text that
	 * was refused and so has none, what was typed, which the draw escapes as it escapes every value.
	 *
	 * @param array $stash What the handler left behind.
	 * @return array Answers keyed by question key.
	 */
	private static function values_to_draw( array $stash ) {
		$values = isset( $stash['values'] ) && is_array( $stash['values'] ) ? $stash['values'] : array();
		$fields = self::fields();

		if ( isset( $stash['typed'] ) && is_array( $stash['typed'] ) ) {
			foreach ( $stash['typed'] as $key => $typed ) {
				if ( isset( $fields[ $key ] ) && self::hands_back_typing( $fields[ $key ] ) && ! isset( $values[ $key ] ) && is_string( $typed ) ) {
					$values[ $key ] = $typed;
				}
			}
		}

		return $values;
	}

	/**
	 * One sentence in the page's wrapper.
	 *
	 * @param string $text What to say.
	 * @return string
	 */
	private static function sentence( $text ) {
		return '<div class="wpcpm-application"><p class="wpcpm-application__notice">' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * The editor's static preview: what the form asks, without asking it.
	 *
	 * @return string
	 */
	private static function preview() {
		$out  = '<div class="wpcpm-application wpcpm-application--preview">';
		$out .= '<p class="wpcpm-application__notice">' . esc_html__( 'The public student registration form is drawn here on the front end. This is a summary, not the form: the live form carries a single-use token that the editor would spend.', 'wpcredits-program-manager' ) . '</p>';
		$out .= '<ul class="wpcpm-application__groups">';

		foreach ( self::fields() as $spec ) {
			$out .= '<li>' . esc_html( $spec['label'] ) . '</li>';
		}

		return $out . '</ul></div>';
	}

	/**
	 * The thank-you: the owner's sentence when the block or the shortcode gives one (`thanks`), the
	 * plugin's otherwise. The same for every student, whatever became of the registration.
	 *
	 * @param string $thanks The owner's thank-you sentence, or '' for the plugin's.
	 */
	private static function render_confirmation( $thanks = '' ) {
		$thanks = trim( (string) $thanks );

		echo '<div class="wpcpm-application__done">';
		echo '<h3>' . esc_html( '' !== $thanks ? $thanks : __( 'Thank you. We have received your registration and will email you at the address you gave.', 'wpcredits-program-manager' ) ) . '</h3>';
		echo '</div>';
	}

	/**
	 * The outcomes a redirect can carry, and what each says. One map, so every slug the handlers
	 * stash has a sentence. `sent` is the page's thank-you, drawn by `render_confirmation()`.
	 *
	 * @return array<string, array> Slug => `array( level, sentence )`.
	 */
	public static function outcomes() {
		return array(
			'again'   => array( 'error', __( 'Nothing has been sent yet. Please look at the answers marked below and send the form again.', 'wpcredits-program-manager' ) ),
			'consent' => array( 'error', __( 'Nothing has been sent yet, and nothing has been stored. The program can only take a registration from somebody who agrees to the privacy policy, so please tick the last box.', 'wpcredits-program-manager' ) ),
			'stale'   => array( 'error', __( 'This form had been open for a long time, so it was not sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'expired' => array( 'error', __( 'This form had been open for a while and could not be sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'busy'    => array( 'error', __( 'Several attempts have been made from this connection in the last hour, so this one was not taken. Please try again in an hour.', 'wpcredits-program-manager' ) ),
			'lost'    => array( 'error', __( 'Something went wrong at our end and the registration was not saved. Please try once more.', 'wpcredits-program-manager' ) ),
			'closed'  => array( 'info', __( 'Student registrations through this form are closed for now.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Draw the sentence for an outcome, if it has one.
	 *
	 * @param string $outcome Outcome slug.
	 */
	private static function render_message( $outcome ) {
		$outcomes = self::outcomes();

		if ( '' === $outcome || ! isset( $outcomes[ $outcome ] ) ) {
			return;
		}

		printf(
			'<p class="wpcpm-application__message is-%1$s" role="status">%2$s</p>',
			esc_attr( $outcomes[ $outcome ][0] ),
			esc_html( $outcomes[ $outcome ][1] )
		);
	}

	/**
	 * Draw the form.
	 *
	 * @param array $values   Cleaned values from a failed attempt, keyed by question key.
	 * @param array $problems Problem slugs from that attempt, keyed by question key.
	 */
	private static function render_form( array $values, array $problems ) {
		printf(
			'<form class="wpcpm-application__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-status="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Sending...', 'wpcredits-program-manager' ),
			esc_attr__( 'Sending your registration.', 'wpcredits-program-manager' )
		);
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SUBMIT ) );
		// Written out rather than printed by `wp_nonce_field()`: the dwell token is signed with it.
		printf( '<input type="hidden" name="_wpnonce" value="%s" />', esc_attr( wp_create_nonce( self::ACTION_SUBMIT ) ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TOKEN_FIELD ), esc_attr( WPCPM_Form_Guard::token( self::DWELL_SCOPE, self::ACTION_SUBMIT ) ) );

		WPCPM_Form_Guard::render_honeypot( self::HONEYPOT );

		foreach ( self::fields() as $key => $spec ) {
			self::render_field( $key, $spec, isset( $values[ $key ] ) ? $values[ $key ] : null, isset( $problems[ $key ] ) ? (string) $problems[ $key ] : '' );
		}

		printf(
			'<p class="wpcpm-application__send wpcpm-application__send--flat"><button type="submit" class="wpcpm-button">%s</button><span class="wpcpm-application__busy" data-wpcpm-busy-status role="status"></span></p>',
			esc_html__( 'Send this registration', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * One question.
	 *
	 * @param string $key     The question's key.
	 * @param array  $spec    Its spec.
	 * @param mixed  $value   Its value from a failed attempt, or null.
	 * @param string $problem The problem slug from that attempt, or ''.
	 */
	private static function render_field( $key, array $spec, $value, $problem ) {
		$id       = 'wpcpm-student-registration-' . $key;
		$name     = self::FIELD_ANSWERS . '[' . $key . ']';
		$type     = $spec['type'];
		$mark     = ! empty( $spec['required'] ) ? esc_html__( 'Required', 'wpcredits-program-manager' ) : '';
		$says     = array();
		$combo    = in_array( $type, array( 'institution', 'preferred' ), true );
		$multiple = 'languages' === $type;

		if ( ! empty( $spec['help'] ) ) {
			$says[] = $id . '-help';
		}

		if ( ! empty( $spec['note'] ) ) {
			$says[] = $id . '-note';
		}

		if ( '' !== $problem ) {
			$says[] = $id . '-problem';
		}

		// A required attribute only where one control is the whole answer: a list of boxes has none
		// that means "at least one", and a combo's list is hidden behind its field once the script runs,
		// where a browser would refuse the form for a control nobody can see.
		$required    = ! empty( $spec['required'] ) && ! $multiple && ! $combo ? ' required="required"' : '';
		$required_at = '' !== $mark ? ' <span class="wpcpm-field__required">' . $mark . '</span>' : '';
		$invalid     = '' !== $problem ? ' aria-invalid="true"' : '';
		$describedby = empty( $says ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $says ) ) . '"';
		$wrapper     = $multiple || $combo ? 'div' : 'p';

		printf( '<%1$s class="wpcpm-field wpcpm-field--%2$s%3$s">', esc_attr( $wrapper ), esc_attr( $type ), '' !== $problem ? ' has-problem' : '' );

		if ( in_array( $type, array( 'consent', 'statement' ), true ) ) {
			// The heading, then the box with its words: the policy's link is the only markup those
			// words may carry.
			printf( '<span class="wpcpm-field__label" id="%1$s-label">%2$s%3$s</span>', esc_attr( $id ), esc_html( $spec['label'] ), $required_at ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.
			printf(
				'<span class="wpcpm-field__consent"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s%5$s /><label for="%1$s">%6$s</label></span>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( true === $value, true, false ),
				$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
				$describedby, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
				wp_kses(
					(string) $spec['box'],
					array(
						'a' => array(
							'href' => array(),
						),
					)
				)
			);
		} elseif ( $multiple ) {
			printf( '<span class="wpcpm-field__label" id="%1$s-label">%2$s%3$s</span>', esc_attr( $id ), esc_html( $spec['label'] ), $required_at ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.

			$picked = array_map( 'strval', (array) $value );

			printf( '<span class="wpcpm-field__choices" role="group" aria-labelledby="%1$s-label"%2$s>', esc_attr( $id ), $describedby ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.

			foreach ( self::languages() as $choice ) {
				printf(
					'<label class="wpcpm-field__check"><input type="checkbox" name="%1$s" value="%2$s"%3$s /> <span>%4$s</span></label>',
					esc_attr( $name . '[]' ),
					esc_attr( $choice ),
					checked( in_array( $choice, $picked, true ), true, false ),
					esc_html( $choice )
				);
			}

			echo '</span>';
		} elseif ( $combo ) {
			$institution = 'institution' === $type;
			$options     = $institution ? self::institutions() : array_combine( self::languages(), self::languages() );

			WPCPM_Dashboards::render_combo(
				array(
					'id'          => $id,
					'name'        => $name,
					'options'     => array( '' => $institution ? __( 'Choose your institution', 'wpcredits-program-manager' ) : __( 'Choose a language', 'wpcredits-program-manager' ) ) + $options,
					'current'     => is_scalar( $value ) ? (string) $value : '',
					'label'       => $spec['label'],
					'mark'        => $mark,
					'describedby' => implode( ' ', $says ),
					// A refused post marks the field a person types into, and the select behind it.
					'invalid'     => '' !== $problem,
					'find'        => $institution ? __( 'Type to find your institution', 'wpcredits-program-manager' ) : __( 'Type to find your language', 'wpcredits-program-manager' ),
					'none'        => $institution ? __( 'No institution has that name.', 'wpcredits-program-manager' ) : __( 'No language has that name.', 'wpcredits-program-manager' ),
					'count'       => $institution
						/* translators: %s: how many institutions the list shows, as a number. */
						? __( 'Institutions in the list: %s', 'wpcredits-program-manager' )
						/* translators: %s: how many languages the list shows, as a number. */
						: __( 'Languages in the list: %s', 'wpcredits-program-manager' ),
				)
			);
		} else {
			printf( '<label for="%1$s">%2$s%3$s</label>', esc_attr( $id ), esc_html( $spec['label'] ), $required_at ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.

			if ( 'textarea' === $type ) {
				printf(
					'<textarea id="%1$s" name="%2$s" rows="4" maxlength="%3$d"%4$s%5$s>%6$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					(int) self::MAX_TEXT,
					$invalid, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$describedby, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
					esc_textarea( is_scalar( $value ) ? (string) $value : '' )
				);
			} elseif ( 'study' === $type ) {
				printf( '<select id="%1$s" name="%2$s"%3$s%4$s%5$s>', esc_attr( $id ), esc_attr( $name ), $required, $invalid, $describedby ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literals and the escaped id above.
				printf( '<option value="">%s</option>', esc_html__( 'Choose an area of study', 'wpcredits-program-manager' ) );

				foreach ( WPCPM_Institution_Student_Form::choices( 'field_of_study' ) as $choice ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $choice ), selected( is_scalar( $value ) ? (string) $value : '', $choice, false ), esc_html( $choice ) );
				}

				echo '</select>';
			} elseif ( 'date' === $type ) {
				// The browser's own date field, which shows the date in the reader's own format and
				// posts it as YYYY-MM-DD.
				printf(
					'<input type="date" id="%1$s" name="%2$s" value="%3$s"%4$s%5$s%6$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
				);
			} elseif ( 'number' === $type ) {
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$d" max="%5$d" step="0.1" inputmode="decimal"%6$s%7$s%8$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					(int) $spec['min'],
					(int) $spec['max'],
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
				);
			} else {
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" maxlength="%5$d"%6$s%7$s%8$s />',
					'email' === $type ? 'email' : 'text',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					(int) $spec['maxlength'],
					isset( $spec['auto'] ) ? ' autocomplete="' . esc_attr( $spec['auto'] ) . '"' : '',
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid . $describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literals and the escaped id above.
				);
			}
		}

		if ( ! empty( $spec['note'] ) ) {
			printf(
				'<span class="wpcpm-field__hint" id="%1$s-note">%2$s</span>',
				esc_attr( $id ),
				wp_kses(
					(string) $spec['note'],
					array(
						'a' => array(
							'href' => array(),
						),
					)
				)
			);
		}

		if ( ! empty( $spec['help'] ) ) {
			printf( '<span class="wpcpm-field__hint" id="%1$s-help">%2$s</span>', esc_attr( $id ), esc_html( $spec['help'] ) );
		}

		if ( '' !== $problem ) {
			printf( '<span class="wpcpm-field__problem" id="%1$s-problem">%2$s</span>', esc_attr( $id ), esc_html( self::problem_text( $problem ) ) );
		}

		printf( '</%s>', esc_attr( $wrapper ) );
	}

	/**
	 * What to say about one refused answer.
	 *
	 * @param string $problem Problem slug.
	 * @return string
	 */
	private static function problem_text( $problem ) {
		// The free text is refused for anything the cleaner would take out of it, a "<" that opens a
		// tag and a "%" followed by two hexadecimal digits alike, and the plugin's one sentence for
		// that names both and says how to type them (`WPCPM_Typed_Text::loss_message()`).
		if ( 'markup' === $problem ) {
			$fields = self::fields();

			return WPCPM_Typed_Text::loss_message( $fields['access']['label'], 'send' );
		}

		$said = array(
			'required'             => __( 'Please fill this in.', 'wpcredits-program-manager' ),
			'formula'              => __( 'This cannot start with =, +, - or @.', 'wpcredits-program-manager' ),
			'control'              => __( 'This cannot hold a tab, a line break or a "|".', 'wpcredits-program-manager' ),
			'name_length'          => __( 'Please give your full name, from 2 to 200 characters.', 'wpcredits-program-manager' ),
			'too_long'             => __( 'Please keep this to 120 characters.', 'wpcredits-program-manager' ),
			'bad_email'            => __( 'That does not look like an email address.', 'wpcredits-program-manager' ),
			'bad_choice'           => __( 'Please choose one of the options offered.', 'wpcredits-program-manager' ),
			'bad_date'             => __( 'Please give a date.', 'wpcredits-program-manager' ),
			'end_before_start'     => __( 'The end date has to be on or after the start date.', 'wpcredits-program-manager' ),
			'not_a_number'         => __( 'Please give a number of hours.', 'wpcredits-program-manager' ),
			'below_min'            => __( 'Please give at least one hour.', 'wpcredits-program-manager' ),
			'above_max'            => __( 'Please give at most 2,000 hours.', 'wpcredits-program-manager' ),
			'preferred_not_spoken' => __( 'Your preferred language has to be one of the languages you ticked above.', 'wpcredits-program-manager' ),
			'unticked'             => __( 'The program can only take students who can say this, so please tick it.', 'wpcredits-program-manager' ),
		);

		return isset( $said[ $problem ] ) ? $said[ $problem ] : __( 'That answer could not be read. Please try it again.', 'wpcredits-program-manager' );
	}

	/*
	 * The submit path
	 * --------------------------------------------------------------------
	 */

	/**
	 * Take one submission, in the mentor form's order of checks, each numbered below.
	 *
	 * `wp_verify_nonce()` and never `check_admin_referer()`: an expired nonce is one more failure the
	 * form comes back from with the answers in it, not a 403 screen.
	 */
	public static function handle_submit() {
		$nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$posted = array();

		if ( isset( $_POST[ self::FIELD_ANSWERS ] ) && is_array( $_POST[ self::FIELD_ANSWERS ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The nonce is verified two steps down, after the ceiling that must count a wrong one; every value is cleaned by its type in `clean()`.
			$posted = wp_unslash( $_POST[ self::FIELD_ANSWERS ] );
		}

		// 1. The per-actor ceiling, before anything writes: the one layer that counts a sender with
		// no account.
		if ( ! WPCPM_Form_Guard::claim_actor( WPCPM_Form_Guard::actor_key( self::ACTOR_PREFIX ), self::PER_HOUR ) ) {
			self::bounce( 'busy' );
		}

		// 2. The nonce.
		if ( ! wp_verify_nonce( $nonce, self::ACTION_SUBMIT ) ) {
			self::bounce( 'expired', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 3. The switch and what the form needs to be drawn, none of which a form this site drew can
		// post past.
		if ( '' !== self::closed_reason() ) {
			self::bounce( 'closed' );
		}

		$signals = array();
		$spam    = false;

		// 4. The honeypot: stored as spam, and the sender sees the ordinary thank-you.
		if ( WPCPM_Form_Guard::honeypot_filled( self::HONEYPOT ) ) {
			$spam      = true;
			$signals[] = 'honeypot';
		}

		// 5. The dwell token: stale bounces, fast is marked, and anything else that is not ok is spam.
		$dwell = WPCPM_Form_Guard::check_token( self::DWELL_SCOPE, WPCPM_Request::posted_text( self::TOKEN_FIELD ) );

		if ( WPCPM_Form_Guard::TOKEN_STALE === $dwell ) {
			self::bounce( 'stale', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		if ( WPCPM_Form_Guard::TOKEN_FAST === $dwell ) {
			$signals[] = 'dwell-fast';
		} elseif ( WPCPM_Form_Guard::TOKEN_OK !== $dwell ) {
			$spam      = true;
			$signals[] = 'dwell';
		}

		// 6. Consent, a precondition: nothing is stored without it, not even as spam.
		$agreed = self::clean( 'consent', isset( $posted['consent'] ) ? $posted['consent'] : '' );

		if ( true !== $agreed['value'] ) {
			self::bounce( 'consent', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 7. The answers, walked from the questions and never from the request.
		$cleaned  = self::clean_all( $posted );
		$values   = $cleaned['values'];
		$problems = $cleaned['problems'];

		// 8. Requiredness, once, after cleaning, skipped for a registration headed for the spam pile:
		// telling a bot which answer it missed is free tuition.
		if ( ! $spam ) {
			$problems = self::add_required( $values, $problems );

			if ( ! empty( $problems ) ) {
				self::bounce(
					'again',
					array(
						'values'   => $values,
						'problems' => $problems,
						// What a refused name, tutor and free text were typed as, for their boxes: in the
						// stash alone, since `$values` is what a registration stores.
						'typed'    => $cleaned['typed'],
					)
				);
			}
		}

		// 9. The two site-wide limits degrade rather than refusing, so a busy day loses no student: the
		// registration is kept, marked and thanked. Past the day's 200, which spam does not count
		// toward, it is held for an Administrator, who writes it with Write it now; past the day's 40,
		// the hourly run writes it.
		$held = ! $spam && ! WPCPM_Form_Guard::claim_site( self::SITE_HOLD_KEY, self::HOLD_PER_DAY );

		if ( $held ) {
			$signals[] = WPCPM_Student_Registration_Process::SIGNAL_HELD;
		}

		$over = ! WPCPM_Form_Guard::claim_site( self::SITE_KEY, WPCPM_Form_Guard::PER_DAY );

		if ( $over ) {
			$signals[] = WPCPM_Student_Registration_Process::SIGNAL_SITE_CEILING;
		}

		// 10. Storage. The privacy tick is the consent's evidence and no answer.
		$fields  = self::fields();
		$state   = $spam ? WPCPM_Student_Registration::STATE_SPAM : WPCPM_Student_Registration::STATE_WAITING;
		$post_id = WPCPM_Student_Registration::store( $values, $state, $signals, WPCPM_Form_Guard::consent_evidence( wp_strip_all_tags( (string) $fields['consent']['box'] ) ) );

		if ( ! $post_id ) {
			self::bounce( 'lost', array( 'values' => $values ) );
		}

		// 11. The page answers first: the process is hung on `shutdown`, which core runs as the request
		// exits after the redirect, so the answer never waits for Airtable and takes the same time for
		// every address. No lock is held here: the process takes the address's own, and only a real
		// concurrent one comes back busy, to be settled by the hourly run. Not for spam, which goes
		// nowhere, nor past either limit, which waits.
		if ( ! $spam && ! $over && ! $held ) {
			add_action(
				'shutdown',
				static function () use ( $post_id ) {
					self::process_after_answer( $post_id );
				}
			);
		}

		self::bounce( 'sent' );
	}

	/**
	 * Settle the registration a submission stored, once its answer has gone: hung on `shutdown` by
	 * `handle_submit()` (design, section 4, "The page answers first").
	 *
	 * Where the server can let the answer go before the work after it (PHP-FPM's
	 * `fastcgi_finish_request()`), the student's browser is let go first and the process runs after.
	 * Where it cannot, nothing runs here and the registration waits for the hourly job, so the time an
	 * answer takes never depends on the address.
	 *
	 * @param int $post_id The registration.
	 */
	public static function process_after_answer( $post_id ) {
		$finish = self::finisher();

		if ( false === $finish ) {
			return;
		}

		// The browser may close its end once it has the answer: the work goes on all the same.
		ignore_user_abort( true );
		call_user_func( $finish );

		WPCPM_Student_Registration_Process::process( (int) $post_id );
	}

	/**
	 * What lets the answer go before the work after it, or false where nothing can.
	 *
	 * @return callable|false
	 */
	private static function finisher() {
		if ( null === self::$finisher ) {
			return function_exists( 'fastcgi_finish_request' ) ? 'fastcgi_finish_request' : false;
		}

		return is_callable( self::$finisher ) ? self::$finisher : false;
	}

	/*
	 * The stash
	 * --------------------------------------------------------------------
	 */

	/**
	 * The form as `WPCPM_Form_Stash` needs it described. `sent` travels bare, as an outcome of its own:
	 * the thank-you holds nothing a student typed.
	 *
	 * @return array
	 */
	private static function stash_form() {
		return array(
			'url'           => self::page_url(),
			'outcomes'      => array_merge( array_keys( self::outcomes() ), array( 'sent' ) ),
			'query_outcome' => self::QUERY_OUTCOME,
			'query_stash'   => self::QUERY_STASH,
			'prefix'        => self::TRANSIENT_PREFIX,
			'minutes'       => self::TRANSIENT_MINUTES,
			'one_shot'      => array(),
		);
	}

	/**
	 * Redirect back with the outcome, anything typed behind a transient.
	 *
	 * @param string $outcome One of `outcomes()`, or `sent`.
	 * @param array  $stash   What the page needs to draw.
	 */
	private static function bounce( $outcome, array $stash = array() ) {
		WPCPM_Form_Stash::bounce(
			self::stash_form(),
			$outcome,
			$stash,
			static function ( $value ) {
				return self::is_blank( $value );
			}
		);
	}

	/**
	 * Read the stash this request carries.
	 *
	 * @return array
	 */
	private static function read_stash() {
		return WPCPM_Form_Stash::read( self::stash_form() );
	}
}
