<?php
/**
 * Students module - the public form a student sends their interest through (1.123.64).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Loaded here as well as by the plugin's loader, as the student registration form loads them: the
// suites require this class with a handful of others, and `require_once` is idempotent by real path.
require_once dirname( __DIR__ ) . '/class-wpcpm-form-stash.php';
require_once dirname( __DIR__ ) . '/class-wpcpm-form-errors.php';

/**
 * The student interest form, and everything that guards it.
 *
 * A student whose institution is not in the program yet says here that they would like to take
 * part, and names a representative of the institution for the program to write to. The form takes
 * the place of one the program kept on Airtable, with that form's questions in its order. An
 * Administrator reads every interest on the Students screen's Interests tab and follows it up by
 * hand.
 *
 * It is the student registration form's shape with these questions. The order the checks run in is
 * that form's, through `WPCPM_Form_Guard`, under this form's own keys: the per-actor ceiling, the
 * nonce, the switch and what the form needs to be drawn, the honeypot, the dwell token, the
 * answers, requiredness, the two boxes to tick (nothing is stored without both, not even as spam),
 * what the content says (which holds an interest for a person and never refuses it), the site-wide
 * limit, the duplicate mark, storage, then the two emails.
 *
 * **Every path ends on one page.** Whatever became of the interest (stored as new, held, filed as
 * spam), the student is sent back to the same bare `sent` answer and reads the same sentence.
 *
 * **Nothing here reads or writes Airtable.** The country list is the site's copy of the Countries
 * table (`WPCPM_Countries::options()`), as the institution application offers it, and an interest
 * is kept on the site alone (`WPCPM_Student_Interest`).
 *
 * **The representative is never emailed by the site.** The student is sent an acknowledgement and
 * Administrators a notice; what is written to the representative, an Administrator writes.
 *
 * **The form makes no page of its own.** An editor places the block or the shortcode on a page,
 * and `track_page()` keeps that page's ID in `OPT_PAGE` as pages are saved, so the redirects, the
 * no-cache rule and the registration form's hint know where the form is.
 */
final class WPCPM_Student_Interest_Form {

	const SHORTCODE = 'wpcpm_student_interest';
	const BLOCK     = 'wpcpm/student-interest';
	const OPT_PAGE  = 'wpcpm_student_interest_page_id';

	/** The `admin_post_` action the form posts to, registered for logged-out visitors too. */
	const ACTION_SUBMIT = 'wpcpm_student_interest';

	/** The dwell token's scope: a token minted for another form is a forgery here. */
	const DWELL_SCOPE = 'wpcpm-student-interest-dwell';

	/**
	 * The id of the form's own box, which an answer comes back to: the form may sit far down a
	 * longer page, under a hero and other sections, and a sender who lands at the page's top after
	 * sending would not see the thank-you, or what to correct.
	 */
	const ANCHOR = 'wpcpm-student-interest';

	/** The per-actor ceiling's key prefix. */
	const ACTOR_PREFIX = 'student-interest';

	/** The site-wide ceiling's key: past the guard's forty a day, an interest is held. */
	const SITE_KEY = 'student-interest-site';

	/** The daily ceiling's key for the acknowledgement. */
	const MAIL_KEY = 'student-interest-mail';

	/** The acknowledgement to the student. */
	const MAIL_RECEIVED = 'student-interest-received';

	/** The notice to Administrators. */
	const MAIL_NOTICE = 'student-interest-notice';

	/** The setting that names who is told of a new interest; empty means every Administrator. */
	const SETTING_NOTIFY = 'student_interest_notify';

	/** The setting that switches the form on; on unless an Administrator switches it off. */
	const SETTING_OPEN = 'student_interest_open';

	/** How long a stashed failure survives the redirect, in minutes. */
	const TRANSIENT_MINUTES = 30;

	/** Prefix for those stashes. */
	const TRANSIENT_PREFIX = 'wpcpm_sint_';

	/** The posts one network address may send in an hour: the guard's five. */
	const PER_HOUR = WPCPM_Form_Guard::PER_HOUR;

	/** The redirect's argument when there is a stash: its id. Nothing typed is ever in the address. */
	const QUERY_STASH = 'wpcpm_sint';

	/** The redirect's argument when there is no stash: the outcome, as itself. */
	const QUERY_OUTCOME = 'wpcpm_sint_said';

	/** The posted array holding the answers, keyed by question key. */
	const FIELD_ANSWERS = 'wpcpm_student_interest';

	/**
	 * The honeypot's field name. Not a word a browser autofills for a person: a genuine interest
	 * filed as spam would be a student lost.
	 */
	const HONEYPOT = 'wpcpm_confirm_faculty';

	/** The dwell token's field name. */
	const TOKEN_FIELD = 'wpcpm_student_interest_token';

	/** Longest free-text answer kept, in characters: the student registration form's. */
	const MAX_TEXT = 2000;

	/** A signal: the site's daily ceiling was spent, so the interest is held for a person. */
	const SIGNAL_SITE_CEILING = 'site-ceiling';

	/** A signal: the day's limit on acknowledgements was spent, so the student was sent none. */
	const SIGNAL_MAIL_CEILING = 'mail-ceiling';

	/** A signal: the mail layer refused the acknowledgement's hand-off. */
	const SIGNAL_MAIL_FAILED = 'mail-failed';

	/**
	 * The key of the dwell token this request claimed, while its press has stored no interest:
	 * every bounce forgets the token's record (`WPCPM_Form_Guard::bounced()`) and the stored
	 * interest takes its place (`landed()`), so a second press of the token knows what to answer.
	 *
	 * @var string
	 */
	private static $dwell = '';

	/**
	 * The boxes this request ticked, by question key. A bounce that draws the form again keeps each
	 * tick with the other answers: the person ticked it, and a box drawn empty again is refused by
	 * the browser itself on the next Send. The privacy tick is never one of the values a row stores;
	 * `bounce()` adds it to the stash alone.
	 *
	 * @var array<string, bool>
	 */
	private static $ticked = array();

	/**
	 * Hooks. The submit is registered for logged-out visitors as well, which is the whole point: a
	 * student has no account.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );
		add_action( 'save_post_page', array( __CLASS__, 'track_page' ), 10, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'forget_page' ) );
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

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/student-interest';

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

	/*
	 * The page
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether a page's content holds the form: its block or its shortcode.
	 *
	 * @param string $content The page's content.
	 * @return bool
	 */
	public static function content_holds_form( $content ) {
		$content = (string) $content;

		if ( false !== strpos( $content, '<!-- wp:' . self::BLOCK . ' ' ) ) {
			return true;
		}

		// A shortcode a page shows in a code or a pre element is one it writes about, as the
		// Administrator guide does: that page is not the form's (1.123.65).
		$content = (string) preg_replace( '#<(code|pre)\b[^>]*>.*?</\1>#is', '', $content );

		// The shortcode as WordPress would run it: a whole tag, and not one written in doubled
		// brackets, which is how a page shows the shortcode as an example without running it.
		if ( preg_match_all( '/(\[?)\[' . preg_quote( self::SHORTCODE, '/' ) . '(?![\w-])[^\]]*\](\]?)/', $content, $tags, PREG_SET_ORDER ) ) {
			foreach ( $tags as $tag ) {
				if ( '[' !== $tag[1] || ']' !== $tag[2] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Keep `OPT_PAGE` up to date as a page is saved (`save_post_page`).
	 *
	 * The form makes no page: an editor places it. A page whose content holds the block or the
	 * shortcode becomes the form's page while the option is empty or names that page already; a
	 * second page holding the form does not take the first one's place, unless it is published and
	 * the first is not: a draft never keeps the published page from being found. The page the option
	 * names stops being the form's page when it no longer holds the form, or is trashed.
	 *
	 * @param int          $post_id The page saved.
	 * @param WP_Post|null $post    The page, as saved.
	 */
	public static function track_page( $post_id, $post = null ) {
		$post_id = (int) $post_id;
		$post    = $post instanceof WP_Post ? $post : get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return;
		}

		$current = self::tracked_page();
		$holds   = 'trash' !== $post->post_status && self::content_holds_form( (string) $post->post_content );

		$takes   = $holds && 0 !== $current && $current !== $post_id && 'publish' === $post->post_status && 'publish' !== get_post_status( $current );

		if ( $holds && ( 0 === $current || $current === $post_id || $takes ) ) {
			if ( $current !== $post_id ) {
				update_option( self::OPT_PAGE, $post_id, false );
			}

			return;
		}

		if ( ! $holds && $current === $post_id ) {
			delete_option( self::OPT_PAGE );
		}
	}

	/**
	 * The page `OPT_PAGE` names, or 0 when it names none, or one that is no longer there: a page
	 * deleted for good never passes through `save_post_page`, and an option left naming it would
	 * keep every later page from becoming the form's.
	 *
	 * @return int
	 */
	private static function tracked_page() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( 0 === $page_id ) {
			return 0;
		}

		$page = get_post( $page_id );

		return ( $page instanceof WP_Post && 'page' === $page->post_type ) ? $page_id : 0;
	}

	/**
	 * Forget the form's page when it is deleted for good (`deleted_post`).
	 *
	 * @param int $post_id The post deleted.
	 */
	public static function forget_page( $post_id ) {
		if ( (int) $post_id > 0 && (int) get_option( self::OPT_PAGE ) === (int) $post_id ) {
			delete_option( self::OPT_PAGE );
		}
	}

	/**
	 * The form's page address, while it is published, or '' otherwise.
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
	 * The address the student registration form's hint links: the form's page while it is published
	 * and the form is open, or '' otherwise, when the hint says what it said before.
	 *
	 * @return string
	 */
	public static function hint_url() {
		return self::is_open() ? self::page_url() : '';
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

		self::send_no_cache();
	}

	/**
	 * Keep the response that draws this form out of every cache: the headers that say so, and the
	 * constant every page cache reads. Sent from `no_cache()` on the form's own page and from every
	 * draw of the form, the block's and the shortcode's on any page, since a cached copy would hand
	 * one visitor another's token, answers or confirmation. `nocache_headers()` sends nothing once
	 * the headers have gone, so a draw late in a page costs nothing.
	 */
	private static function send_no_cache() {
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
	 * The countries the form offers: the site's copy of the Countries table, record ID to name, A to
	 * Z, as the institution application offers it. Read from the site alone.
	 *
	 * @return array<string, string>
	 */
	public static function countries() {
		return class_exists( 'WPCPM_Countries' ) ? (array) WPCPM_Countries::options() : array();
	}

	/**
	 * The groups the questions are drawn in, each a fieldset under its heading, in the form's order:
	 * who is asking, the institution, the person to write to there, how the institution works, and
	 * the two boxes a sender ticks last. They are how the form is laid out, not what is asked: the
	 * questions and their order are the old form's (`fields()`), and a group never reorders them.
	 *
	 * @return array<string, string> Group key => heading.
	 */
	public static function groups() {
		return array(
			'about'          => __( 'About you', 'wpcredits-program-manager' ),
			'institution'    => __( 'Your institution', 'wpcredits-program-manager' ),
			'representative' => __( 'The representative', 'wpcredits-program-manager' ),
			'more'           => __( 'More about your institution', 'wpcredits-program-manager' ),
			'last'           => __( 'Before you send this', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The questions, keyed by the key each answer is kept under: the questions of the form the
	 * program kept on Airtable, in its order and with its labels and help.
	 *
	 * The two boxes to tick carry a heading (`label`) and the words beside the box (`box`): the
	 * first says the representative knows and agreed, the second is the registration form's privacy
	 * sentence, the policy linked by its address.
	 *
	 * Each question names the group it is drawn in (`group`, a key of `groups()`). A question marked
	 * `wide` takes a whole row of its group; a list of boxes, a long text and a tick always do.
	 *
	 * @return array<string, array> Key => spec: `label`, `type`, `required`, `group`, and per type
	 *                              `maxlength`, `auto`, `help`, `choices`, `box`, `wide`.
	 */
	public static function fields() {
		$policy = WPCPM_Form_Guard::policy_url();

		return array(
			'name'           => array(
				'group'     => 'about',
				'label'     => __( 'Your Name', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 200,
				'auto'      => 'name',
				'help'      => __( 'Your name and surname.', 'wpcredits-program-manager' ),
			),
			'email'          => array(
				'group'     => 'about',
				'label'     => __( 'Your Email', 'wpcredits-program-manager' ),
				'type'      => 'email',
				'required'  => true,
				'maxlength' => 254,
				'auto'      => 'email',
			),
			'institution'    => array(
				'group'     => 'institution',
				// The one short answer that takes a whole row: the pairs under it then fall two by two.
				'wide'      => true,
				'label'     => __( 'Institution Name', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 200,
				'auto'      => 'organization',
			),
			'country'        => array(
				'group'     => 'institution',
				'label'    => __( 'Country', 'wpcredits-program-manager' ),
				'type'     => 'country',
				'required' => true,
			),
			'city'           => array(
				'group'     => 'institution',
				'label'     => __( 'City', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 120,
				'auto'      => 'address-level2',
			),
			'website'        => array(
				'group'     => 'institution',
				'label'     => __( 'Institution Website', 'wpcredits-program-manager' ),
				// `type="text"` with `inputmode="url"`, never `type="url"`: the browser refuses a
				// scheme-less address, and `example.edu` is how most people write one.
				'type'      => 'url',
				'required'  => true,
				'maxlength' => 255,
			),
			'department'     => array(
				'group'     => 'institution',
				'label'     => __( 'Department', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => false,
				'maxlength' => 200,
			),
			'representative' => array(
				'group'     => 'representative',
				'label'     => __( 'Institution Representative', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 200,
				'help'      => __( 'The name of a representative from your institution. Make sure they know about this request and have agreed to be contacted.', 'wpcredits-program-manager' ),
			),
			'contact_email'  => array(
				'group'     => 'representative',
				'label'     => __( 'Contact Email', 'wpcredits-program-manager' ),
				'type'      => 'email',
				'required'  => true,
				'maxlength' => 254,
				'help'      => __( 'The representative\'s email address.', 'wpcredits-program-manager' ),
			),
			'internships'    => array(
				'group'     => 'more',
				'label'    => __( 'How do your internships or practices typically work?', 'wpcredits-program-manager' ),
				'type'     => 'choices',
				'required' => true,
				'choices'  => WPCPM_Institution_Application::internship_choices(),
			),
			'anything'       => array(
				'group'     => 'more',
				'label'    => __( 'Anything else you\'d like us to know?', 'wpcredits-program-manager' ),
				'type'     => 'textarea',
				'required' => false,
			),
			'told'           => array(
				'group'     => 'last',
				'label'    => __( 'The representative\'s permission', 'wpcredits-program-manager' ),
				'type'     => 'statement',
				'required' => true,
				'box'      => __( 'I have told the representative about this request, and they agreed that I share their name and email address with the WordPress Credits program.', 'wpcredits-program-manager' ),
			),
			'consent'        => array(
				'group'     => 'last',
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
	 * The two sentences above the form: who it is for, and what to do before sending it.
	 *
	 * @return string[]
	 */
	public static function introduction() {
		return array(
			__( 'If you are a student who would like to join WordPress Credits and your institution is not yet part of the program, tell us about it here and we will get in touch with the representative you name.', 'wpcredits-program-manager' ),
			__( 'Before you send this form, tell the representative and get their permission to share their contact details with us.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Clean one posted answer, or refuse it with a reason.
	 *
	 * An empty answer is accepted as empty and left for requiredness (`add_required()`), as the
	 * registration form leaves it. A line of text is cleaned as that form cleans a name: a control
	 * character is refused on the raw value, before the cleaner removes it, and so is a spreadsheet
	 * formula. The website goes through `WPCPM_Field_Value`, as the institution and the sponsor
	 * forms' do, with the institution form's two rules added: an address naming a scheme other than
	 * http or https is refused rather than given `https://` and kept, and one longer than its box
	 * once the scheme is on it is refused rather than cut.
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

		if ( 'choices' === $type ) {
			return self::accept( self::picked( $raw, (array) $spec['choices'] ) );
		}

		if ( 'country' === $type ) {
			$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';

			if ( '' === $value ) {
				return self::accept( '' );
			}

			// Checked against the list the field was drawn from, never against the shape of an ID.
			return isset( self::countries()[ $value ] ) ? self::accept( $value ) : self::refuse( 'bad_choice' );
		}

		if ( 'email' === $type ) {
			return WPCPM_Field_Value::clean( $raw, array( 'type' => 'email' ) );
		}

		if ( 'url' === $type ) {
			if ( is_scalar( $raw ) && self::names_other_scheme( (string) $raw ) ) {
				return self::refuse( 'bad_url' );
			}

			$clean = WPCPM_Field_Value::clean( $raw, array( 'type' => 'url' ) );

			if ( ! empty( $clean['ok'] ) && mb_strlen( (string) $clean['value'] ) > (int) $spec['maxlength'] ) {
				return self::refuse( 'too_long' );
			}

			return $clean;
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
	 * Whether a typed address names a scheme of its own other than http or https, such as
	 * `javascript:` or `mailto:`. A colon followed by a digit is a port, not a scheme.
	 *
	 * @param string $typed The address as posted.
	 * @return bool
	 */
	private static function names_other_scheme( $typed ) {
		if ( ! preg_match( '/^([a-z][a-z0-9+.\-]*):(?!\d)/i', trim( $typed ), $scheme ) ) {
			return false;
		}

		return ! in_array( strtolower( $scheme[1] ), array( 'http', 'https' ), true );
	}

	/**
	 * A line of text the way the registration form cleans a student's name: the control characters
	 * refused on the raw value, the cleaner's collapsing of white space applied, a spreadsheet
	 * formula refused, and the length held.
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
				// Compared trimmed: the list's first choice begins with a space, which a browser keeps
				// and a hand-made request may not.
				$posted[] = trim( (string) $one );
			}
		}

		$picked = array();

		foreach ( $choices as $choice ) {
			if ( in_array( trim( (string) $choice ), $posted, true ) ) {
				$picked[] = trim( (string) $choice );
			}
		}

		return $picked;
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
	 * nobody asked about cannot become a stored answer. Neither box is one of the values: a bounce
	 * keeps the student's own ticks in the stash alone (`bounce()`), and nothing ticks a box the
	 * student did not tick.
	 *
	 * The country's name is kept beside its ID (`country_name`), as the Interests tab and the notice
	 * name it.
	 *
	 * A refused answer has no cleaned value to hand back, so what was typed comes back beside the
	 * values as `typed`, cut to the length of its box, for the form to draw in the box it was typed
	 * in and for nothing else. It is never one of the values, since a row filed as spam stores those.
	 *
	 * @param array $posted The posted answers, unslashed, keyed by question key.
	 * @return array{values:array,problems:array,typed:array}
	 */
	private static function clean_all( array $posted ) {
		$values    = array();
		$problems  = array();
		$typed     = array();
		$countries = self::countries();

		foreach ( self::fields() as $key => $spec ) {
			if ( in_array( $spec['type'], array( 'consent', 'statement' ), true ) ) {
				continue;
			}

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

			if ( 'country' === $key && '' !== $clean['value'] && isset( $countries[ $clean['value'] ] ) ) {
				$values['country_name'] = trim( (string) $countries[ $clean['value'] ] );
			}
		}

		return array(
			'values'   => $values,
			'problems' => $problems,
			'typed'    => $typed,
		);
	}

	/**
	 * Whether a question's refused answer is handed back as it was typed: every one a person types
	 * into, so an address the browser takes and the form does not comes back to be corrected.
	 *
	 * @param array $spec The question's spec.
	 * @return bool
	 */
	private static function hands_back_typing( array $spec ) {
		return in_array( $spec['type'], array( 'text', 'textarea', 'email', 'url' ), true );
	}

	/**
	 * Everything typed into the boxes a person types in, as posted: what the per-connection
	 * ceiling's refusal keeps beside the cleaned values, measured whole by
	 * `WPCPM_Form_Stash::fits()`, so a post too long to keep is not cut to fit and kept.
	 *
	 * @param array $posted The posted answers, unslashed.
	 * @return array Question key => what was typed.
	 */
	private static function typing( array $posted ) {
		$typed = array();

		foreach ( self::fields() as $key => $spec ) {
			if ( self::hands_back_typing( $spec ) && isset( $posted[ $key ] ) && is_string( $posted[ $key ] ) && '' !== $posted[ $key ] && mb_check_encoding( $posted[ $key ], 'UTF-8' ) ) {
				$typed[ $key ] = $posted[ $key ];
			}
		}

		return $typed;
	}

	/**
	 * Add the requiredness problems, once, after everything is cleaned: a required question left
	 * blank. The two boxes are a precondition and are checked apart (`handle_submit()`, step 8).
	 *
	 * @param array $values   Cleaned values.
	 * @param array $problems Problems so far.
	 * @return array
	 */
	private static function add_required( array $values, array $problems ) {
		foreach ( self::fields() as $key => $spec ) {
			if ( in_array( $spec['type'], array( 'consent', 'statement' ), true ) || isset( $problems[ $key ] ) || empty( $spec['required'] ) ) {
				continue;
			}

			if ( self::is_blank( isset( $values[ $key ] ) ? $values[ $key ] : null ) ) {
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
	 * Whether the program is taking interest from students through the form right now: a setting,
	 * on unless an Administrator switches it off.
	 *
	 * @return bool
	 */
	public static function is_open() {
		return (bool) WPCPM_Settings::get_value( self::SETTING_OPEN, true );
	}

	/**
	 * Why the form cannot be drawn, or '' when it can: the switch is off, or the site holds no
	 * country to offer yet. Each is the site's state alone; none reads Airtable.
	 *
	 * @return string
	 */
	private static function closed_reason() {
		if ( ! self::is_open() ) {
			return __( 'The student interest form is switched off. Turn on "Student interest form" on the Students and mentors tab of WPCredits Program > Settings when the program is ready to take it.', 'wpcredits-program-manager' );
		}

		if ( empty( self::countries() ) ) {
			return __( 'This form is not shown yet: the countries list is empty, and every interest has to name a country. Run the institutions sync on the Institutions screen\'s Sync and storage tab.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * Render the answer this request carries, the form, the thank-you, or nothing.
	 *
	 * The form is drawn for nobody while it cannot be (`closed_reason()`); every visitor reads the
	 * `closed` sentence in its place, once, and an Administrator is told why in one sentence more. Not
	 * where no form would be drawn anyway, after a sent interest. The editor's preview is a static
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

		// The draw is the page's, wherever the block or the shortcode stands: never cached.
		self::send_no_cache();

		$stash   = self::read_stash();
		$outcome = isset( $stash['outcome'] ) ? (string) $stash['outcome'] : '';

		ob_start();

		if ( 'sent' === $outcome ) {
			self::render_confirmation( (string) $atts['thanks'] );
		} else {
			self::render_message( $outcome );
		}

		$draws = ! in_array( $outcome, array( 'sent', 'busy', 'busy-unkept' ), true );

		if ( '' !== $closed ) {
			if ( $draws && 'closed' !== $outcome ) {
				self::render_message( 'closed' );
			}

			if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
				printf( '<p class="wpcpm-application__notice">%s</p>', esc_html( $closed ) );
			}
		} elseif ( $draws ) {
			foreach ( self::introduction() as $paragraph ) {
				printf( '<p class="wpcpm-application-intro">%s</p>', esc_html( $paragraph ) );
			}

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

		return '<div class="wpcpm-application" id="' . esc_attr( self::ANCHOR ) . '">' . $title . $body . '</div>';
	}

	/**
	 * The answers a redrawn form shows: the cleaned ones, and, for an answer that was refused and so
	 * has none, what was typed, which the draw escapes as it escapes every value.
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
		$out .= '<p class="wpcpm-application__notice">' . esc_html__( 'The public student interest form is drawn here on the front end. This is a summary, not the form: the live form carries a single-use token that the editor would spend.', 'wpcredits-program-manager' ) . '</p>';
		$out .= '<ul class="wpcpm-application__groups">';

		foreach ( self::fields() as $spec ) {
			$out .= '<li>' . esc_html( $spec['label'] ) . '</li>';
		}

		return $out . '</ul></div>';
	}

	/**
	 * The thank-you: the owner's sentence when the block or the shortcode gives one (`thanks`), the
	 * plugin's otherwise. The same for every student, whatever became of the interest.
	 *
	 * @param string $thanks The owner's thank-you sentence, or '' for the plugin's.
	 */
	private static function render_confirmation( $thanks = '' ) {
		$thanks = trim( (string) $thanks );

		echo '<div class="wpcpm-application__done">';
		echo '<h3>' . esc_html( '' !== $thanks ? $thanks : __( 'Thank you. We have received your interest and will get in touch with the representative you named.', 'wpcredits-program-manager' ) ) . '</h3>';
		echo '<p>' . esc_html__( 'Nothing else is needed from you now.', 'wpcredits-program-manager' ) . '</p>';
		echo '</div>';
	}

	/**
	 * The outcomes a redirect can carry, and what each says. One map, so every slug the handlers
	 * stash has a sentence. `sent` is the page's thank-you, drawn by `render_confirmation()`. A
	 * refused send says no sentence of its own: the form opens with the summary of its problems
	 * (`WPCPM_Form_Errors::summary()`), an unticked box among them. The per-connection ceiling's
	 * three and `stash-gone` are every public form's (`WPCPM_Form_Stash::outcomes()`).
	 *
	 * @return array<string, array> Slug => `array( level, sentence )`.
	 */
	public static function outcomes() {
		return array_merge(
			WPCPM_Form_Stash::outcomes(),
			array(
				'stale'   => array( 'error', __( 'This form had been open for a long time, so it was not sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
				'expired' => array( 'error', __( 'This form had been open for a while and could not be sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
				'lost'    => array( 'error', __( 'Something went wrong at our end and your interest was not saved. Try once more.', 'wpcredits-program-manager' ) ),
				'closed'  => array( 'info', __( 'Interest from students through this form is closed for now.', 'wpcredits-program-manager' ) ),
			)
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
			esc_attr__( 'Sending your interest.', 'wpcredits-program-manager' )
		);

		// The summary of a refused send, the form's first child: a heading counting the problems and
		// a link to each field, which assets/js/forms.js moves the focus to.
		echo self::summary( $problems ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WPCPM_Form_Errors::summary() escapes every value it prints.

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SUBMIT ) );
		// Written out rather than printed by `wp_nonce_field()`: the dwell token is signed with it.
		printf( '<input type="hidden" name="_wpnonce" value="%s" />', esc_attr( wp_create_nonce( self::ACTION_SUBMIT ) ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TOKEN_FIELD ), esc_attr( WPCPM_Form_Guard::token( self::DWELL_SCOPE, self::ACTION_SUBMIT ) ) );

		WPCPM_Form_Guard::render_honeypot( self::HONEYPOT );

		$fields = self::fields();

		// A fieldset for each group, and inside it a box that sets the short answers two to a row
		// where there is room (application.css): a form on a wide page fills its width with pairs of
		// questions, never with one answer stretched across it.
		foreach ( self::groups() as $group => $heading ) {
			printf( '<fieldset class="wpcpm-application__group"><legend>%s</legend><div class="wpcpm-application__columns">', esc_html( $heading ) );

			foreach ( $fields as $key => $spec ) {
				if ( $spec['group'] === $group ) {
					self::render_field( $key, $spec, isset( $values[ $key ] ) ? $values[ $key ] : null, isset( $problems[ $key ] ) ? (string) $problems[ $key ] : '' );
				}
			}

			echo '</div></fieldset>';
		}

		printf(
			'<p class="wpcpm-application__send"><button type="submit" class="wpcpm-button">%s</button><span class="wpcpm-application__busy" data-wpcpm-busy-status role="status"></span></p>',
			esc_html__( 'Send this form', 'wpcredits-program-manager' )
		);

		echo '</form>';
	}

	/**
	 * The summary a refused send opens with: each problem linked to its question by the question's
	 * label, in the form's order, or '' when nothing was refused.
	 *
	 * @param array $problems Problem slugs, keyed by question key.
	 * @return string
	 */
	private static function summary( array $problems ) {
		$fields    = array();
		$sentences = array();

		foreach ( self::fields() as $key => $spec ) {
			$fields[ $key ] = array(
				'id'    => self::field_id( $key ),
				'label' => (string) $spec['label'],
			);

			if ( isset( $problems[ $key ] ) ) {
				$sentences[ $key ] = self::problem_text( (string) $problems[ $key ], $spec );
			}
		}

		return WPCPM_Form_Errors::summary( $sentences, $fields );
	}

	/**
	 * The id a question's control is drawn with, which its problem's id and the summary's link are
	 * made from.
	 *
	 * @param string $key The question's key.
	 * @return string
	 */
	private static function field_id( $key ) {
		return 'wpcpm-student-interest-' . str_replace( '_', '-', (string) $key );
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
		$id       = self::field_id( $key );
		$name     = self::FIELD_ANSWERS . '[' . $key . ']';
		$type     = $spec['type'];
		$mark     = ! empty( $spec['required'] ) ? esc_html__( 'Required', 'wpcredits-program-manager' ) : '';
		$says     = array();
		$combo    = 'country' === $type;
		$multiple = 'choices' === $type;

		if ( ! empty( $spec['help'] ) ) {
			$says[] = $id . '-help';
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

		printf( '<%1$s class="wpcpm-field wpcpm-field--%2$s%3$s%4$s">', esc_attr( $wrapper ), esc_attr( $type ), ! empty( $spec['wide'] ) ? ' wpcpm-field--wide' : '', '' !== $problem ? ' has-problem' : '' );

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
				$invalid . $describedby, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal and the escaped id above.
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

			$picked = array_map( 'trim', array_map( 'strval', (array) $value ) );

			// The group carries the question's id, so the summary's link to it lands on its boxes.
			printf( '<span class="wpcpm-field__choices" id="%1$s" role="group" aria-labelledby="%1$s-label"%2$s>', esc_attr( $id ), $describedby ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.

			foreach ( (array) $spec['choices'] as $choice ) {
				// Trimmed: the list's first choice begins with a space, which is Airtable's and no
				// part of what a student reads or this form keeps.
				$choice = trim( (string) $choice );

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
			WPCPM_Dashboards::render_combo(
				array(
					'id'          => $id,
					'name'        => $name,
					// The empty entry first, then the countries as they were given, A to Z, valued by
					// record ID: the select posts the ID and the field only helps find it.
					'options'     => array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ) + self::countries(),
					'current'     => is_scalar( $value ) ? (string) $value : '',
					'label'       => $spec['label'],
					'mark'        => $mark,
					'describedby' => implode( ' ', $says ),
					// A refused post marks the field a person types into, and the select behind it.
					'invalid'     => '' !== $problem,
					'find'        => __( 'Type to find your country', 'wpcredits-program-manager' ),
					'none'        => __( 'No country has that name.', 'wpcredits-program-manager' ),
					/* translators: %s: how many countries the list shows, as a number. */
					'count'       => __( 'Countries in the list: %s', 'wpcredits-program-manager' ),
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
			} else {
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" maxlength="%5$d"%6$s%7$s%8$s%9$s />',
					'email' === $type ? 'email' : 'text',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					(int) $spec['maxlength'],
					'url' === $type ? ' inputmode="url"' : '',
					isset( $spec['auto'] ) ? ' autocomplete="' . esc_attr( $spec['auto'] ) . '"' : '',
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid . $describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literals and the escaped id above.
				);
			}
		}

		if ( ! empty( $spec['help'] ) ) {
			printf( '<span class="wpcpm-field__hint" id="%1$s-help">%2$s</span>', esc_attr( $id ), esc_html( $spec['help'] ) );
		}

		if ( '' !== $problem ) {
			printf( '<span class="wpcpm-field__problem" id="%1$s-problem">%2$s</span>', esc_attr( $id ), esc_html( self::problem_text( $problem, $spec ) ) );
		}

		printf( '</%s>', esc_attr( $wrapper ) );
	}

	/**
	 * What to say about one refused answer.
	 *
	 * @param string $problem Problem slug.
	 * @param array  $spec    The question's spec, for its label and the length a refusal names.
	 * @return string
	 */
	public static function problem_text( $problem, array $spec ) {
		// The free text is refused for anything the cleaner would take out of it, and the plugin's one
		// sentence for that says how to type it (`WPCPM_Typed_Text::loss_message()`).
		if ( 'markup' === $problem ) {
			return WPCPM_Typed_Text::loss_message( isset( $spec['label'] ) ? (string) $spec['label'] : '', 'send' );
		}

		// A web address the form refuses: one sentence for every form (`WPCPM_Field_Value`).
		if ( 'bad_url' === $problem ) {
			return WPCPM_Field_Value::bad_url_text();
		}

		if ( 'too_long' === $problem ) {
			return sprintf(
				/* translators: %s: the most characters the answer may have, as a number. */
				__( 'Keep this to %s characters or fewer.', 'wpcredits-program-manager' ),
				number_format_i18n( isset( $spec['maxlength'] ) ? (int) $spec['maxlength'] : 0 )
			);
		}

		if ( 'required' === $problem && isset( $spec['type'] ) && 'choices' === $spec['type'] ) {
			return __( 'Tick at least one.', 'wpcredits-program-manager' );
		}

		$said = array(
			'required'    => __( 'Fill this in.', 'wpcredits-program-manager' ),
			'formula'     => __( 'This cannot start with =, +, - or @.', 'wpcredits-program-manager' ),
			'control'     => __( 'This cannot hold a tab, a line break or a "|".', 'wpcredits-program-manager' ),
			'name_length' => __( 'Give your full name, from 2 to 200 characters.', 'wpcredits-program-manager' ),
			'bad_email'   => __( 'That does not look like an email address.', 'wpcredits-program-manager' ),
			'bad_choice'  => __( 'Choose one of the options offered.', 'wpcredits-program-manager' ),
			'unticked'    => __( 'Tick this box to send the form.', 'wpcredits-program-manager' ),
			'consent'     => __( 'Tick this box to send the form.', 'wpcredits-program-manager' ),
		);

		return isset( $said[ $problem ] ) ? $said[ $problem ] : __( 'That answer could not be read. Write it again.', 'wpcredits-program-manager' );
	}

	/*
	 * The submit path
	 * --------------------------------------------------------------------
	 */

	/**
	 * Take one submission, in the registration form's order of checks, each numbered below.
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

		// The two ticks, which every bounce that draws the form again keeps (`bounce()`).
		self::$ticked = self::ticked( $posted );

		self::$dwell = '';

		// 1. The per-actor ceiling, before anything writes: the one layer that counts a sender with
		// no account. Its refusal keeps the answers only for a post whose nonce holds, and only when
		// they fit: a flood with no nonce, or with a body of any size, stays as cheap as the ceiling
		// makes it, and writes nothing.
		if ( ! WPCPM_Form_Guard::claim_actor( WPCPM_Form_Guard::actor_key( self::ACTOR_PREFIX ), self::PER_HOUR ) ) {
			if ( ! wp_verify_nonce( $nonce, self::ACTION_SUBMIT ) ) {
				self::bounce( 'busy' );
			}

			$kept = array(
				'values' => self::clean_all( $posted )['values'],
				'typed'  => self::typing( $posted ),
			);

			if ( ! WPCPM_Form_Stash::fits( $kept ) ) {
				self::bounce( 'busy-unkept' );
			}

			self::bounce( 'busy-kept', $kept );
		}

		// 2. The nonce.
		if ( ! wp_verify_nonce( $nonce, self::ACTION_SUBMIT ) ) {
			$cleaned = self::clean_all( $posted );

			self::bounce(
				'expired',
				array(
					'values' => $cleaned['values'],
					'typed'  => $cleaned['typed'],
				)
			);
		}

		// 3. The switch and what the form needs to be drawn, neither of which a form this site drew
		// can post past.
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

		// 5. The dwell token: stale bounces, fast holds, used waits for the first press's interest,
		// and anything else that is not ok is spam.
		$token = WPCPM_Request::posted_text( self::TOKEN_FIELD );
		$dwell = WPCPM_Form_Guard::check_token( self::DWELL_SCOPE, $token );

		if ( WPCPM_Form_Guard::TOKEN_STALE === $dwell ) {
			$cleaned = self::clean_all( $posted );

			self::bounce(
				'stale',
				array(
					'values' => $cleaned['values'],
					'typed'  => $cleaned['typed'],
				)
			);
		}

		if ( WPCPM_Form_Guard::TOKEN_USED === $dwell ) {
			self::answer_second_press( WPCPM_Form_Guard::token_key( self::DWELL_SCOPE, $token ), $posted );
		}

		if ( WPCPM_Form_Guard::TOKEN_OK === $dwell || WPCPM_Form_Guard::TOKEN_FAST === $dwell ) {
			// This press claimed the token: from here every bounce forgets its record, and the
			// interest replaces it.
			self::$dwell = WPCPM_Form_Guard::token_key( self::DWELL_SCOPE, $token );
		}

		if ( WPCPM_Form_Guard::TOKEN_FAST === $dwell ) {
			$signals[] = 'dwell-fast';
		} elseif ( WPCPM_Form_Guard::TOKEN_OK !== $dwell ) {
			$spam      = true;
			$signals[] = 'dwell';
		}

		// 6. The answers, walked from the questions and never from the request.
		$cleaned  = self::clean_all( $posted );
		$values   = $cleaned['values'];
		$problems = $cleaned['problems'];

		// 7. Requiredness, once, after cleaning, skipped for an interest headed for the spam pile:
		// telling a bot which answer it missed is free tuition. A spam interest is stored with what
		// cleaned.
		$problems = $spam ? array() : self::add_required( $values, $problems );

		// 8. The two boxes, a precondition, in the same pass as the answers: one bounce marks each
		// with the other answers. Nothing is stored without both, not even as spam: the first is the
		// only word the site has that the representative knows their name and address are here.
		if ( empty( self::$ticked['told'] ) ) {
			$problems['told'] = 'unticked';
		}

		if ( empty( self::$ticked['consent'] ) ) {
			$problems['consent'] = 'consent';
		}

		if ( ! empty( $problems ) ) {
			self::bounce(
				'again',
				array(
					'values'   => $values,
					'problems' => $problems,
					// What every refused answer was typed as, for its box: in the stash alone, since
					// `$values` is what an interest stores.
					'typed'    => $cleaned['typed'],
				)
			);
		}

		// 9. What the content says. Holds, never refuses: a wrongly refused interest is a student who
		// never writes again and nobody ever knowing.
		if ( ! $spam ) {
			$signals = array_merge( $signals, self::score( $values ) );
		}

		// 10. The site-wide ceiling degrades rather than refusing, so a flood cannot close the form
		// to the one real student that afternoon. The interest is kept and held, and Administrators
		// are not told of each one.
		if ( ! $spam && ! WPCPM_Form_Guard::claim_site( self::SITE_KEY, WPCPM_Form_Guard::PER_DAY ) ) {
			$signals[] = self::SIGNAL_SITE_CEILING;
		}

		// 11. A second interest from the same student for the same institution within thirty days is
		// marked and never merged or refused. Both rows stand and a person decides.
		if ( WPCPM_Student_Interest::has_recent( WPCPM_Student_Interest::answer( $values, 'email' ), WPCPM_Student_Interest::answer( $values, 'institution' ) ) ) {
			$signals[] = WPCPM_Student_Interest::SIGNAL_DUPLICATE;
		}

		// 12. Storage. The first tick is kept as an answer the student gave, the privacy tick is the
		// consent's evidence, and both sentences are kept as they were drawn.
		$fields         = self::fields();
		$values['told'] = true;
		$consent        = array_merge(
			WPCPM_Form_Guard::consent_evidence( wp_strip_all_tags( (string) $fields['consent']['box'] ) ),
			array( 'told' => wp_strip_all_tags( (string) $fields['told']['box'] ) )
		);
		$state          = self::state_for( $spam, $signals );
		$post_id        = WPCPM_Student_Interest::store( $values, $state, $signals, $consent );

		if ( ! $post_id ) {
			unset( $values['told'] );

			self::bounce( 'lost', array( 'values' => $values ) );
		}

		// 13. The two emails, gated apart. The student is written to for a new or a held interest: it
		// is the only word they get that the form arrived, and a held one is a person's to judge, not
		// over. Spam is told nothing. Administrators hear of a new one alone, which is what holding
		// spares them. The representative is never written to by the site.
		if ( WPCPM_Student_Interest::STATE_SPAM !== $state ) {
			self::mail_student( (int) $post_id );
		}

		if ( WPCPM_Student_Interest::STATE_NEW === $state ) {
			self::mail_administrators( (int) $post_id );
		}

		// The token's record is the interest now, for a second press of it to answer with.
		WPCPM_Form_Guard::landed( self::$dwell, (int) $post_id );

		self::$dwell = '';

		self::bounce( 'sent' );
	}

	/**
	 * What the content of a submission says about it: matched against the site's comment disallowed
	 * list, or carrying too many links. Each holds an interest for a person; none refuses it, and
	 * the sender is never told which one fired.
	 *
	 * @param array $values Cleaned values.
	 * @return string[]
	 */
	private static function score( array $values ) {
		$signals = array();
		$prose   = array();

		foreach ( self::fields() as $key => $spec ) {
			if ( in_array( $spec['type'], array( 'text', 'textarea' ), true ) && 'name' !== $key && isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ) {
				$prose[] = (string) $values[ $key ];
			}
		}

		$prose = implode( "\n", $prose );
		$name  = WPCPM_Student_Interest::answer( $values, 'name' );
		$email = WPCPM_Student_Interest::answer( $values, 'email' );

		if ( function_exists( 'wp_check_comment_disallowed_list' ) && wp_check_comment_disallowed_list( $name, $email, WPCPM_Student_Interest::answer( $values, 'website' ), $prose, WPCPM_Form_Guard::client_ip(), WPCPM_Form_Guard::user_agent() ) ) {
			$signals[] = 'disallowed';
		}

		if ( WPCPM_Form_Guard::too_many_links( $prose, WPCPM_Form_Guard::MAX_LINKS ) ) {
			$signals[] = 'links';
		}

		return $signals;
	}

	/**
	 * The state an interest is filed under: spam when a spam layer fired, held when anything else
	 * was raised but the duplicate mark, and new otherwise. A duplicate alone does not hold: the
	 * second row may be the genuine one.
	 *
	 * @param bool  $spam    Whether a spam layer fired.
	 * @param array $signals Every signal raised.
	 * @return string
	 */
	private static function state_for( $spam, array $signals ) {
		if ( $spam ) {
			return WPCPM_Student_Interest::STATE_SPAM;
		}

		return empty( array_diff( $signals, array( WPCPM_Student_Interest::SIGNAL_DUPLICATE ) ) ) ? WPCPM_Student_Interest::STATE_NEW : WPCPM_Student_Interest::STATE_HELD;
	}

	/**
	 * Send the student the acknowledgement, under the day's limit on them.
	 *
	 * The ceiling before the send, not after: a claim that fails leaves the interest as it is and
	 * marks it, so the Interests tab shows that this student was sent nothing. So does a hand-off
	 * the mail layer refuses.
	 *
	 * @param int $post_id The interest.
	 * @return bool Whether the email was handed off.
	 */
	private static function mail_student( $post_id ) {
		$post = WPCPM_Student_Interest::interest( $post_id );

		if ( null === $post ) {
			return false;
		}

		$fields = WPCPM_Student_Interest::fields_of( $post );
		$email  = WPCPM_Student_Interest::answer( $fields, 'email' );

		if ( ! is_email( $email ) ) {
			return false;
		}

		if ( ! WPCPM_Form_Guard::claim_mail( self::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY ) ) {
			WPCPM_Student_Interest::add_signal( $post_id, self::SIGNAL_MAIL_CEILING );

			return false;
		}

		$values  = array(
			'site_name'        => WPCPM_Mail::site_name(),
			'student_name'     => WPCPM_Student_Interest::answer( $fields, 'name' ),
			'institution_name' => WPCPM_Student_Interest::answer( $fields, 'institution' ),
		);
		$contact = WPCPM_Mail_Templates::program_contact();
		$build   = static function () use ( $values, $contact ) {
			return WPCPM_Mail_Templates::mail( self::MAIL_RECEIVED, $values, array( 'headers' => '' !== $contact ? array( 'Reply-To: ' . $contact ) : array() ) );
		};

		// No locale is named: the form asks no language, so the page's own is the student's.
		if ( WPCPM_Mail::send_to( $email, self::MAIL_RECEIVED, $build ) ) {
			return true;
		}

		WPCPM_Student_Interest::add_signal( $post_id, self::SIGNAL_MAIL_FAILED );

		return false;
	}

	/**
	 * Tell Administrators there is an interest to read: the institution's name and country and the
	 * way to the Interests tab, and none of the answers, which are read on the tab. To the
	 * addresses the `student_interest_notify` setting names, or to every Administrator when it
	 * names none.
	 *
	 * @param int $post_id The interest.
	 * @return int How many were told.
	 */
	private static function mail_administrators( $post_id ) {
		$post = WPCPM_Student_Interest::interest( $post_id );

		if ( null === $post ) {
			return 0;
		}

		$fields = WPCPM_Student_Interest::fields_of( $post );
		$values = array(
			'site_name'        => WPCPM_Mail::site_name(),
			'reference'        => WPCPM_Student_Interest::reference_of( $post ),
			'institution_name' => WPCPM_Student_Interest::answer( $fields, 'institution' ),
			'country'          => WPCPM_Student_Interest::answer( $fields, 'country_name' ),
			'interests_link'   => WPCPM_Student_Interests_Tab::view_url(),
		);

		$build = static function () use ( $values ) {
			return WPCPM_Mail_Templates::mail( self::MAIL_NOTICE, $values );
		};

		return (int) WPCPM_Institutions::notify_managers( self::MAIL_NOTICE, $build, self::SETTING_NOTIFY );
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
			'url'           => self::return_url(),
			'outcomes'      => array_merge( array_keys( self::outcomes() ), array( 'sent' ) ),
			'query_outcome' => self::QUERY_OUTCOME,
			'query_stash'   => self::QUERY_STASH,
			'prefix'        => self::TRANSIENT_PREFIX,
			'minutes'       => self::TRANSIENT_MINUTES,
			'one_shot'      => array(),
			// The answer lands on the form's own box (`ANCHOR`), wherever the form sits on its page.
			'fragment'      => self::ANCHOR,
		);
	}

	/**
	 * Where a submission goes back to: the form's page, or, while the option names none (the form
	 * drawn by a shortcode in a template, say), the page the form was posted from, when it is this
	 * site's. With neither, `WPCPM_Form_Stash::bounce()` goes to the home page.
	 *
	 * @return string
	 */
	private static function return_url() {
		$url = self::page_url();

		if ( '' !== $url ) {
			return $url;
		}

		$referer = function_exists( 'wp_get_referer' ) ? wp_get_referer() : false;

		if ( ! $referer ) {
			return '';
		}

		// Without this form's two arguments, so an answer never rides on the one before it.
		return (string) remove_query_arg( array( self::QUERY_STASH, self::QUERY_OUTCOME ), wp_validate_redirect( $referer, '' ) );
	}

	/**
	 * Which of the two boxes the posted answers tick, each read the strict way `clean()` reads it.
	 *
	 * @param array $posted The posted answers, unslashed.
	 * @return array<string, bool> `told` and `consent`.
	 */
	private static function ticked( array $posted ) {
		$ticked = array();

		foreach ( array( 'told', 'consent' ) as $key ) {
			$ticked[ $key ] = true === self::clean( $key, isset( $posted[ $key ] ) ? $posted[ $key ] : '' )['value'];
		}

		return $ticked;
	}

	/**
	 * Redirect back with the outcome, anything typed behind a transient.
	 *
	 * @param string $outcome One of `outcomes()`, or `sent`.
	 * @param array  $stash   What the page needs to draw.
	 */
	private static function bounce( $outcome, array $stash = array() ) {
		// A press that claimed its token and stored nothing forgets the token's record, so a second
		// press of it answers `stale` with its own answers rather than waiting for an interest.
		if ( '' !== self::$dwell ) {
			WPCPM_Form_Guard::bounced( self::$dwell );

			self::$dwell = '';
		}

		// A form drawn again keeps the ticks its sender gave, with the other answers. Only a stash
		// carrying answers draws the form.
		if ( array_key_exists( 'values', $stash ) && is_array( $stash['values'] ) ) {
			foreach ( self::$ticked as $key => $ticked ) {
				if ( $ticked ) {
					$stash['values'][ $key ] = true;
				}
			}
		}

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
	 * Answer the second press of a token: wait for the interest the first press stores and answer
	 * as the first press was answered, the one bare `sent` every interest ends on; with none, the
	 * first press bounced, and this one is `stale`, with its answers. Nothing is stored for it.
	 *
	 * @param string $key    The token's key.
	 * @param array  $posted The posted answers, unslashed.
	 */
	private static function answer_second_press( $key, array $posted ) {
		$row = WPCPM_Form_Guard::wait_for_row( $key );

		if ( $row && null !== WPCPM_Student_Interest::interest( (int) $row ) ) {
			self::bounce( 'sent' );
		}

		$cleaned = self::clean_all( $posted );

		self::bounce(
			'stale',
			array(
				'values' => $cleaned['values'],
				'typed'  => $cleaned['typed'],
			)
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
