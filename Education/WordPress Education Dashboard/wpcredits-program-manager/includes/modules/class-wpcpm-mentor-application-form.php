<?php
/**
 * Mentors module - the public form people apply to mentor through (1.122.31).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Loaded here as well as by the plugin's loader, as the sponsor form loads it: the suites require
// this class with a handful of others, and `require_once` is idempotent by real path.
require_once dirname( __DIR__ ) . '/class-wpcpm-form-stash.php';

/**
 * The form at /become-a-mentor/, and everything that guards it (design of 16 October 2026, section 4).
 *
 * The sponsor application's shape with the mentor's questions, which are the program's Airtable
 * form's eleven, in its order and with its labels, as the form read on 16 October 2026 (section
 * 2.3), kept as site fields, never as Airtable columns. The order the checks run in is the sponsor
 * form's, through `WPCPM_Form_Guard`, under this form's own keys: the per-actor ceiling, the nonce,
 * the switch and what the form needs to be drawn, the honeypot, the dwell token, consent, the
 * answers, requiredness, the site-wide degrade, content scoring, the duplicate flags, then the row
 * and the two emails.
 *
 * **Duplicates hold.** Unlike the sponsor form's flags, which only mark a row, a mentor application
 * that shares its address or its WordPress.org username with another application still in play, or
 * with a mentor the site knows (an account with the Mentor role or a mentor record, or a mentor the
 * last mentors sync read from Airtable), is held for an Administrator: the design's rule (section 4).
 * Nothing is ever merged, and nothing on a public request asks Airtable anything.
 *
 * **The address is confirmed by the applicant.** The acknowledgement carries a link bound to the
 * address (`WPCPM_Mentor_Application::verify_url()`); a decision that emails the applicant refuses
 * a row whose address nobody has confirmed (`WPCPM_Mentor_Application_Queue`).
 *
 * The page is deliberately **not gated**, as the other two forms' are not: see `ensure_page()`. Its
 * introduction and its thank-you are the owner's to word on the page (section 13, item 2): the page
 * starts with the Airtable form's introduction, and the block's `thanks` takes the thank-you's place.
 */
final class WPCPM_Mentor_Application_Form {

	const SHORTCODE = 'wpcpm_mentor_application';
	const BLOCK     = 'wpcpm/mentor-application';
	const OPT_PAGE  = 'wpcpm_mentor_application_page_id';
	const STYLE     = 'wpcpm-mentor-application';

	/** The page's slug. Chosen once and never renamed: a rename breaks every link anybody was sent. */
	const SLUG = 'become-a-mentor';

	/** The `admin_post_` action the form posts to, registered for logged-out visitors too. */
	const ACTION_SUBMIT = 'wpcpm_mentor_apply';

	/** The dwell token's scope: a token minted for another form is a forgery here. */
	const DWELL_SCOPE = 'wpcpm-mentor-application-dwell';

	/** The per-actor ceiling's key prefix: this form's own five an hour per source. */
	const ACTOR_PREFIX = 'mentor-apply';

	/** The site-wide ceiling's key: this form's own forty a day. */
	const SITE_KEY = 'mentor-apply-site';

	/** The mail ceiling's key. */
	const MAIL_KEY = 'mentor-apply-mail';

	/** Longest free-text answer kept, in characters. */
	const MAX_TEXT = 4000;

	/** How long a stashed failure or confirmation survives the redirect, in minutes. */
	const TRANSIENT_MINUTES = 30;

	/** Prefix for those stashes. */
	const TRANSIENT_PREFIX = 'wpcpm_mapp_';

	/** The redirect's argument when there is a stash: its id. Nothing typed is ever in the address. */
	const QUERY_STASH = 'wpcpm_mapp';

	/** The redirect's argument when there is no stash: the outcome, as itself. */
	const QUERY_OUTCOME = 'wpcpm_mapp_said';

	/** The posted array holding the answers, keyed by question key. */
	const FIELD_ANSWERS = 'wpcpm_mentor_application';

	/**
	 * The honeypot's field name. Not `website`, `url` or `profile`: a browser autofilling one of
	 * those for a person would file a genuine application as spam, and this form asks for a profile.
	 */
	const HONEYPOT = 'wpcpm_confirm_handle';

	/** The dwell token's field name. */
	const TOKEN_FIELD = 'wpcpm_mentor_application_token';

	/** The two emails the form sends, named for the mail log. */
	const MAIL_RECEIVED = 'mentor-application-received';
	const MAIL_NOTICE   = 'mentor-application-notice';

	/** The two pages the compliance declaration links, as the Airtable form's does: the license and the trademark policy. */
	const GPL_URL       = 'https://wordpress.org/about/license/';
	const TRADEMARK_URL = 'https://wordpressfoundation.org/trademark-policy/';

	/** The handbook's page about mentoring, which the page's introduction sends an applicant to first. */
	const CALL_URL = 'https://make.wordpress.org/handbook/wordpress-credits-contribution-internship-program/call-for-mentors/';

	/** Another application still in play holds this address or this WordPress.org username. */
	const SIGNAL_DUPLICATE = 'duplicate';

	/** A mentor the site knows holds this address or this WordPress.org username. */
	const SIGNAL_MENTOR = 'mentor';

	/**
	 * The honeypot was filled: the row is filed as spam and no acknowledgement goes. The slug is stored
	 * on rows, so it is a contract with them, and the queue reads it to say the acknowledgement never
	 * went (`WPCPM_Mentor_Application_Queue`).
	 */
	const SIGNAL_HONEYPOT = 'honeypot';

	/** The dwell token was missing or did not verify: the row is filed as spam and no acknowledgement goes. */
	const SIGNAL_DWELL = 'dwell';

	/** The day's limit on acknowledgements was reached: the row is kept and no acknowledgement goes. */
	const SIGNAL_MAIL_CEILING = 'mail-ceiling';

	/** The mail layer could not hand the acknowledgement off: the row is kept and the applicant has no link. */
	const SIGNAL_MAIL_FAILED = 'mail-failed';

	/**
	 * Hooks. The submit and the confirming link are registered for logged-out visitors as well,
	 * which is the whole point: an applicant has no account.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_' . WPCPM_Mentor_Application::ACTION_VERIFY, array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_nopriv_' . WPCPM_Mentor_Application::ACTION_VERIFY, array( __CLASS__, 'handle_verify' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );
	}

	/**
	 * Register the shortcode, the block and the stylesheet.
	 */
	public static function register() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, WPCPM_PLUGIN_URL . 'assets/css/application.css', array(), WPCPM_VERSION );
		}

		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script( 'wpcpm-forms', WPCPM_PLUGIN_URL . 'assets/js/forms.js', array(), WPCPM_VERSION, true );
		}

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/mentor-application';

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
	 * A page this makes starts with the Airtable form's introduction, two paragraphs the owner edits
	 * on the page, then the form's block.
	 *
	 * **Left ungated on purpose**, as the other two forms' pages are: an absent access level means
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
				'post_title'   => __( 'Become a mentor', 'wpcredits-program-manager' ),
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
	 * What a page this plugin makes starts with: the Airtable form's introduction, in two paragraph
	 * blocks, then the form's block.
	 *
	 * @return string Block markup.
	 */
	public static function starting_content() {
		$paragraphs = array(
			esc_html__( 'We are looking for dedicated mentors to guide the next generation of contributors in WordPress Credits, a flagship educational program of the WordPress Foundation.', 'wpcredits-program-manager' ),
			sprintf(
				/* translators: %s: a link to the handbook's call for mentors page, its name as its text. */
				esc_html__( 'We cannot ensure sponsorship for mentors. By applying you confirm that you have at least two hours each week for your students. Before applying, read %s.', 'wpcredits-program-manager' ),
				'<a href="' . esc_url( self::CALL_URL ) . '">' . esc_html__( 'the call for mentors page', 'wpcredits-program-manager' ) . '</a>'
			),
		);
		$blocks     = array();

		foreach ( $paragraphs as $paragraph ) {
			$blocks[] = "<!-- wp:paragraph -->\n<p>" . $paragraph . "</p>\n<!-- /wp:paragraph -->";
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
	 * The languages a mentor can mentor in: a fixed list, since the site holds none of its own. The
	 * names the program's Airtable Languages table held on 16 October 2026, each once, so the Airtable
	 * write of a later release finds its link by name.
	 *
	 * @return string[]
	 */
	public static function languages() {
		return array(
			'Bengali',
			'Catalan',
			'Croatian',
			'Egyptian Arabic',
			'English',
			'Finnish',
			'French',
			'German',
			'Gujarati',
			'Hausa',
			'Hindi',
			'Indonesian',
			'Iranian Persian',
			'Italian',
			'Japanese',
			'Javanese',
			'Korean',
			'Levantine Arabic',
			'Mandarin Chinese',
			'Marathi',
			'Modern Standard Arabic',
			'Nepali',
			'Nigerian Pidgin',
			'Polish',
			'Portuguese',
			'Russian',
			'Spanish',
			'Swahili',
			'Tagalog (Filipino)',
			'Tamil',
			'Telugu',
			'Thai',
			'Turkish',
			'Urdu',
			'Vietnamese',
			'Western Punjabi',
			'Wu Chinese (Shanghainese)',
			'Yue Chinese (Cantonese)',
		);
	}

	/**
	 * The countries the form offers: the site's own list, the one the institution form offers
	 * (`WPCPM_Countries::options()`), by name, A to Z. The name is what is stored.
	 *
	 * @return string[]
	 */
	public static function countries() {
		return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', WPCPM_Countries::options() ) ), 'strlen' ) ) );
	}

	/**
	 * The contribution teams the form offers: the site's own list, the one a student picks a team
	 * from (`WPCPM_Contribution_Teams::options()`), by name, A to Z. The name is what is stored.
	 *
	 * @return string[]
	 */
	public static function teams() {
		return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', WPCPM_Contribution_Teams::options() ) ), 'strlen' ) ) );
	}

	/**
	 * The questions, keyed by the site field each answer is kept under: the Airtable form's eleven, in
	 * its order and with its labels and help, as it read on 16 October 2026 (design, section 2.3).
	 * It does not ask the Mentors table's three sponsorship columns, and neither does this.
	 *
	 * The two boxes to tick carry a heading (`label`) and the words beside the box (`box`): the
	 * compliance declaration's are the form's statement word for word, with its two links, and the
	 * privacy policy's are the sentence the consent evidence records.
	 *
	 * @return array<string, array> Key => spec: `group`, `label`, `type`, `required`, and per type
	 *                              `maxlength`, `auto`, `help`, `min`, `max`, `box`.
	 */
	public static function fields() {
		return array(
			'name'      => array(
				'group'     => 'about',
				'label'     => __( 'Full name', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 120,
				'auto'      => 'name',
			),
			'email'     => array(
				'group'    => 'about',
				'label'    => __( 'Email', 'wpcredits-program-manager' ),
				'type'     => 'email',
				'required' => true,
				'auto'     => 'email',
				'help'     => __( 'We send the acknowledgement here, with a link to confirm the address.', 'wpcredits-program-manager' ),
			),
			'profile'   => array(
				'group'     => 'about',
				'label'     => __( 'WordPress.org profile URL', 'wpcredits-program-manager' ),
				'type'      => 'profile',
				'required'  => true,
				'maxlength' => 200,
				'help'      => __( 'Your profile\'s address, which is https://profiles.wordpress.org/ followed by your username, or just your username. If you do not have a profile yet, create one at https://login.wordpress.org/register.', 'wpcredits-program-manager' ),
			),
			'country'   => array(
				'group'    => 'about',
				'label'    => __( 'Country', 'wpcredits-program-manager' ),
				'type'     => 'country',
				'required' => true,
			),
			'teacher'   => array(
				'group'    => 'about',
				'label'    => __( 'Are you a teacher from a WordPress Credits participating institution?', 'wpcredits-program-manager' ),
				'type'     => 'yesno',
				'required' => true,
			),
			'languages' => array(
				'group'    => 'mentoring',
				'label'    => __( 'Languages in which you could mentor students', 'wpcredits-program-manager' ),
				'type'     => 'languages',
				'required' => true,
				'help'     => __( 'Add as many as apply.', 'wpcredits-program-manager' ),
			),
			'expertise' => array(
				'group'    => 'mentoring',
				'label'    => __( 'In which WordPress contribution areas do you have expertise?', 'wpcredits-program-manager' ),
				'type'     => 'teams',
				'required' => false,
				'help'     => __( 'This refers to your experience as a WordPress.org contributor. Add as many as apply. If you are new to contributing, select nothing.', 'wpcredits-program-manager' ),
			),
			'hours'     => array(
				'group'    => 'mentoring',
				'label'    => __( 'Available hours per week to mentor students', 'wpcredits-program-manager' ),
				'type'     => 'number',
				'required' => true,
				'min'      => 1,
				'max'      => 40,
				'help'     => __( 'Mentoring asks for at least two hours a week.', 'wpcredits-program-manager' ),
			),
			'gpl'       => array(
				'group'    => 'program',
				'label'    => __( 'Compliance declaration', 'wpcredits-program-manager' ),
				'type'     => 'statement',
				'required' => true,
				'box'      => sprintf(
					/* translators: 1: an opening link tag to the GPL, 2: its closing tag, 3: an opening link tag to the WordPress trademark policy, 4: its closing tag. */
					__( 'I confirm that I comply with the %1$sGPL%2$s and do not violate, endorse, or work for companies that violate the %3$sWordPress trademark%4$s, misuse the WordPress name or logo, or distribute non-GPL-compliant derivatives of WordPress.', 'wpcredits-program-manager' ),
					'<a href="' . esc_url( self::GPL_URL ) . '">',
					'</a>',
					'<a href="' . esc_url( self::TRADEMARK_URL ) . '">',
					'</a>'
				),
			),
			'anything'  => array(
				'group'    => 'more',
				'label'    => __( 'Anything else you\'d like us to know?', 'wpcredits-program-manager' ),
				'type'     => 'textarea',
				'required' => false,
			),
			'consent'   => array(
				'group'    => 'consent',
				'label'    => __( 'Privacy policy', 'wpcredits-program-manager' ),
				'type'     => 'consent',
				'required' => true,
				'box'      => __( 'I confirm I have read the privacy policy and that my answers can be kept under it while the program reviews my application.', 'wpcredits-program-manager' ),
			),
		);
	}

	/**
	 * The groups the questions are asked in, in order.
	 *
	 * @return array<string, string> Group key => heading.
	 */
	public static function groups() {
		return array(
			'about'     => __( 'About you', 'wpcredits-program-manager' ),
			'mentoring' => __( 'What you would mentor', 'wpcredits-program-manager' ),
			'program'   => __( 'You and the program', 'wpcredits-program-manager' ),
			'more'      => __( 'Tell us more', 'wpcredits-program-manager' ),
			'consent'   => __( 'Before you send this', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * Clean one posted answer, or refuse it with a reason.
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

		if ( in_array( $type, array( 'languages', 'teams' ), true ) ) {
			return self::accept( self::picked( $raw, 'languages' === $type ? self::languages() : self::teams() ) );
		}

		if ( 'profile' === $type ) {
			if ( ! is_scalar( $raw ) ) {
				return self::refuse( 'not_scalar' );
			}

			$typed = trim( mb_substr( trim( (string) $raw ), 0, (int) $spec['maxlength'] ) );

			if ( '' === $typed ) {
				return self::accept( '' );
			}

			// The username it names, or nothing: see `profile_handle()`.
			$handle = self::profile_handle( $typed );

			return '' !== $handle ? self::accept( $handle ) : self::refuse( 'bad_profile' );
		}

		if ( 'country' === $type ) {
			$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';

			if ( '' === $value ) {
				return self::accept( '' );
			}

			return in_array( $value, self::countries(), true ) ? self::accept( $value ) : self::refuse( 'bad_choice' );
		}

		if ( 'yesno' === $type ) {
			$cleaned = WPCPM_Field_Value::clean(
				$raw,
				array(
					'type'    => 'select',
					'choices' => array( 'Yes', 'No' ),
				)
			);

			return $cleaned['ok'] && null === $cleaned['value'] ? self::accept( '' ) : $cleaned;
		}

		if ( 'number' === $type ) {
			if ( ! is_scalar( $raw ) ) {
				return self::refuse( 'not_scalar' );
			}

			// A decimal, as the Airtable form's hours take one: a comma read as the point, held to
			// one place, and kept as the number it then reads, `2.5` or `3`.
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

		// The free text keeps what was typed or says why it cannot: the cleaner would take a run from
		// a "<" to a ">" out of it, as a tag, and an applicant writing about code would lose words.
		if ( 'textarea' === $type && WPCPM_Typed_Text::cleaner_loses( $raw, 'lines' ) ) {
			return self::refuse( 'markup' );
		}

		$rules = array( 'type' => 'textarea' === $type ? 'textarea' : $type );

		if ( isset( $spec['maxlength'] ) ) {
			$rules['maxlength'] = $spec['maxlength'];
		}

		if ( 'textarea' === $type ) {
			$rules['max_text'] = self::MAX_TEXT;
		}

		return WPCPM_Field_Value::clean( $raw, $rules );
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
	 * The WordPress.org username a typed profile names, or '' when it names nobody.
	 *
	 * A profile is a username, with or without one "@", or an address that carries one, in any case
	 * and with the scheme left off or not: a path on `profiles.wordpress.org` whose first segment is
	 * the username (`/ada-example/`, with `/profile/` and the like after it allowed), or
	 * `/support/users/<username>/` on `wordpress.org` or `www.wordpress.org` (a trailing slash or more
	 * path after the username allowed). Nothing else is read as one: the mentors sync's rule keeps
	 * the last part of any text, so a link to another site, a name typed in the box, or a page on
	 * WordPress.org that is nobody's profile (`/plugins/akismet/`) would otherwise pass for a
	 * username. What is kept is the username as the sync reduces it, so it is the one the sync would
	 * read from the Airtable column, and a host on its own (`wordpress.org`, `www.wordpress.org`,
	 * `profiles.wordpress.org`) or a text with no letter or digit in it (`.`, `-`, `..`), which have
	 * the shape of a username and name nobody, is refused.
	 *
	 * @param string $typed What was typed, trimmed and cut to the box's length.
	 * @return string The username, lowercased, or ''.
	 */
	private static function profile_handle( $typed ) {
		$typed = (string) $typed;

		// The hosts are written with their capitals and read in any case, by the pattern's `i`.
		if ( 1 === preg_match( '/^@?([A-Za-z0-9._-]+)$/', $typed, $found ) ) {
			$named = $found[1];
		} elseif ( 1 === preg_match( '#^(?:https?://)?profiles\.WordPress\.org/([A-Za-z0-9._-]+)(?:[/?\#]|$)#i', $typed, $found ) ) {
			// Read as the sync reads an address on that host, which leaves out the components of a
			// profile: `/profile/` on its own names nobody.
			$named = 'https://profiles.wordpress.org/' . $found[1];
		} elseif ( 1 === preg_match( '#^(?:https?://)?(?:www\.)?WordPress\.org/support/users/([A-Za-z0-9._-]+)(?:[/?\#]|$)#i', $typed, $found ) ) {
			$named = $found[1];
		} else {
			return '';
		}

		$handle = WPCPM_Mentors_Sync::wporg_username( $named );

		if ( in_array( $handle, array( 'wordpress.org', 'www.wordpress.org', 'profiles.wordpress.org' ), true ) || 1 !== preg_match( '/[a-z0-9]/', $handle ) ) {
			return '';
		}

		return $handle;
	}

	/**
	 * Clean every answer the form asks, walked from `fields()` and never from the request, so a key
	 * nobody asked about cannot become a stored answer. Consent never comes back as a value: no
	 * failure path can tick it again for the applicant.
	 *
	 * A free text or a profile that is refused has no cleaned value to hand back, so what was typed
	 * comes back beside the values as `typed`, cut to the length of its box, for the form to draw
	 * in the box it was typed in and for nothing else. It is never one of the values, since a row
	 * filed as spam stores those.
	 *
	 * @param array $posted The posted answers, unslashed, keyed by question key.
	 * @return array{values:array,problems:array,typed:array}
	 */
	private static function clean_all( array $posted ) {
		$values   = array();
		$problems = array();
		$typed    = array();

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
		}

		unset( $values['consent'] );

		return array(
			'values'   => $values,
			'problems' => $problems,
			'typed'    => $typed,
		);
	}

	/**
	 * Whether a question's refused answer is handed back as it was typed: the two a person types
	 * freely and may have to correct, the profile and the free text.
	 *
	 * @param array $spec The question's spec.
	 * @return bool
	 */
	private static function hands_back_typing( array $spec ) {
		return in_array( $spec['type'], array( 'textarea', 'profile' ), true );
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
	 * Whether the program is taking mentor applications on this site right now: a setting, because
	 * "on" means accepting writes from anybody on the internet.
	 *
	 * @return bool
	 */
	public static function is_open() {
		return (bool) WPCPM_Settings::get_value( 'mentor_applications_enabled', false );
	}

	/**
	 * Why the form cannot be drawn, or '' when it can.
	 *
	 * @return string
	 */
	private static function closed_reason() {
		if ( ! self::is_open() ) {
			return __( 'The mentor application form is switched off. Turn on "Applications from mentors" on the Students and mentors tab of WPCredits Program > Settings when the program is ready to take them.', 'wpcredits-program-manager' );
		}

		if ( '' === WPCPM_Form_Guard::policy_url() ) {
			return __( 'This form is not shown yet: applicants are asked to agree to the privacy policy, and this site has no published privacy policy page. Set one under Settings > Privacy.', 'wpcredits-program-manager' );
		}

		if ( empty( self::countries() ) ) {
			return __( 'This form is not shown yet: the countries list is empty, and every application names a country. Read the lists again on the Connection tab of WPCredits Program > Settings.', 'wpcredits-program-manager' );
		}

		if ( empty( self::teams() ) ) {
			return __( 'This form is not shown yet: the contribution teams list is empty, and every application names the teams its mentor knows. Run the mentors sync on the Mentors screen\'s Sync tab.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * Render the answer this request carries, the form, the confirmation, or nothing.
	 *
	 * The form is drawn for nobody while it cannot be (`closed_reason()`); an Administrator is told
	 * why in one sentence. The editor's preview is a static summary, because a live one would mint a
	 * nonce and a single-use token on every keystroke.
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

		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( 'wpcpm-forms' );

		$closed = self::closed_reason();

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '' !== $closed ? self::sentence( $closed ) : self::preview();
		}

		$stash   = self::read_stash();
		$outcome = isset( $stash['outcome'] ) ? (string) $stash['outcome'] : '';

		ob_start();

		if ( 'sent' === $outcome || 'sent-quiet' === $outcome ) {
			self::render_confirmation( $stash, 'sent' === $outcome, (string) $atts['thanks'] );
		} else {
			self::render_message( $outcome );
		}

		if ( '' !== $closed ) {
			if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
				printf( '<p class="wpcpm-application__notice">%s</p>', esc_html( $closed ) );
			}
		} elseif ( ! in_array( $outcome, array( 'sent', 'sent-quiet', 'busy', 'verified', 'verified-already' ), true ) ) {
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
	 * The answers a redrawn form shows: the cleaned ones, and, for a free text or a profile that was
	 * refused and so has none, what was typed, which the draw escapes as it escapes every value.
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
		$out .= '<p class="wpcpm-application__notice">' . esc_html__( 'The public mentor application form is drawn here on the front end. This is a summary, not the form: the live form carries a single-use token that the editor would spend.', 'wpcredits-program-manager' ) . '</p>';
		$out .= '<ul class="wpcpm-application__groups">';

		foreach ( self::groups() as $group => $heading ) {
			$count = count(
				array_filter(
					self::fields(),
					static function ( $spec ) use ( $group ) {
						return $group === $spec['group'];
					}
				)
			);

			$out .= sprintf(
				'<li><strong>%1$s</strong> %2$s</li>',
				esc_html( $heading ),
				esc_html(
					sprintf(
						/* translators: %s: how many questions this group asks. */
						_n( '%s question', '%s questions', $count, 'wpcredits-program-manager' ),
						number_format_i18n( $count )
					)
				)
			);
		}

		return $out . '</ul></div>';
	}

	/**
	 * The thank-you, drawn from a stash already consumed: the owner's sentence when the block or the
	 * shortcode gives one (`thanks`), the plugin's otherwise, then the reference and the address.
	 *
	 * @param array  $stash        What the handler left behind.
	 * @param bool   $acknowledged Whether the acknowledgement went: false past the day's mail ceiling, and
	 *                             when the mail layer refused it.
	 * @param string $thanks       The owner's thank-you sentence, or '' for the plugin's.
	 */
	private static function render_confirmation( array $stash, $acknowledged, $thanks = '' ) {
		$reference = isset( $stash['reference'] ) ? (string) $stash['reference'] : '';
		$email     = isset( $stash['email'] ) ? (string) $stash['email'] : '';
		$thanks    = trim( (string) $thanks );

		echo '<div class="wpcpm-application__done">';
		echo '<h3>' . esc_html( '' !== $thanks ? $thanks : __( 'Thank you for applying to mentor in the WordPress Credits Program. Your application is with us.', 'wpcredits-program-manager' ) ) . '</h3>';

		if ( '' !== $reference ) {
			printf(
				'<p class="wpcpm-application__reference">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the application reference, e.g. MAPP-2026-0007. */
						__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
						$reference
					)
				)
			);
		}

		if ( '' !== $email ) {
			printf(
				'<p>%s</p>',
				esc_html(
					$acknowledged
						? sprintf(
							/* translators: %s: the address the applicant gave. */
							__( 'We have sent an acknowledgement to %s with a link that confirms the address. Please follow it: the program writes to you about your application only once the address is confirmed. Every application is read by hand, and a reply can take several weeks.', 'wpcredits-program-manager' ),
							$email
						)
						: sprintf(
							/* translators: %s: the address the applicant gave. */
							__( 'We could not send the acknowledgement to %s today, so please keep the reference above. Your application is stored and will be read by hand; a reply can take several weeks.', 'wpcredits-program-manager' ),
							$email
						)
				)
			);
		}

		echo '</div>';
	}

	/**
	 * The outcomes a redirect can carry, and what each says. One map, so every slug the handlers
	 * stash has a sentence.
	 *
	 * @return array<string, array> Slug => `array( level, sentence )`.
	 */
	public static function outcomes() {
		return array(
			'again'            => array( 'error', __( 'Nothing has been sent yet. Please look at the answers marked below and send the form again.', 'wpcredits-program-manager' ) ),
			'consent'          => array( 'error', __( 'Nothing has been sent yet, and nothing has been stored. The program can only accept an application from somebody who confirms the privacy policy, so please tick the last box.', 'wpcredits-program-manager' ) ),
			'stale'            => array( 'error', __( 'This form had been open for a long time, so it was not sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'expired'          => array( 'error', __( 'This form had been open for a while and could not be sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'busy'             => array( 'error', __( 'Several attempts have been made from this connection in the last hour, so this one was not taken. Please try again in an hour.', 'wpcredits-program-manager' ) ),
			'lost'             => array( 'error', __( 'Something went wrong at our end and the application was not saved. Nothing has been sent. Please try once more.', 'wpcredits-program-manager' ) ),
			'closed'           => array( 'info', __( 'The program is not taking mentor applications through this form at the moment.', 'wpcredits-program-manager' ) ),
			'verified'         => array( 'success', __( 'Thank you. Your address is confirmed, and your application is with the program\'s team.', 'wpcredits-program-manager' ) ),
			'verified-already' => array( 'info', __( 'This address was already confirmed, so there is nothing more to do. Your application is with the program\'s team.', 'wpcredits-program-manager' ) ),
			'verify-failed'    => array( 'error', __( 'That confirmation link could not be read. It may have been broken across two lines by a mail program: please try the whole link again.', 'wpcredits-program-manager' ) ),
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
			esc_attr__( 'Sending your application.', 'wpcredits-program-manager' )
		);
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SUBMIT ) );
		// Written out rather than printed by `wp_nonce_field()`: the dwell token is signed with it.
		printf( '<input type="hidden" name="_wpnonce" value="%s" />', esc_attr( wp_create_nonce( self::ACTION_SUBMIT ) ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TOKEN_FIELD ), esc_attr( WPCPM_Form_Guard::token( self::DWELL_SCOPE, self::ACTION_SUBMIT ) ) );

		WPCPM_Form_Guard::render_honeypot( self::HONEYPOT );

		$fields = self::fields();

		foreach ( self::groups() as $group => $heading ) {
			printf( '<fieldset class="wpcpm-application__group"><legend>%s</legend>', esc_html( $heading ) );

			foreach ( $fields as $key => $spec ) {
				if ( $spec['group'] === $group ) {
					self::render_field( $key, $spec, isset( $values[ $key ] ) ? $values[ $key ] : null, isset( $problems[ $key ] ) ? (string) $problems[ $key ] : '' );
				}
			}

			echo '</fieldset>';
		}

		printf(
			'<p class="wpcpm-application__send"><button type="submit" class="wpcpm-button">%s</button><span class="wpcpm-application__busy" data-wpcpm-busy-status role="status"></span></p>',
			esc_html__( 'Send this application', 'wpcredits-program-manager' )
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
		$id       = 'wpcpm-mentor-application-' . $key;
		$name     = self::FIELD_ANSWERS . '[' . $key . ']';
		$type     = $spec['type'];
		$mark     = ! empty( $spec['required'] ) ? esc_html__( 'Required', 'wpcredits-program-manager' ) : '';
		$says     = array();
		$multiple = in_array( $type, array( 'languages', 'teams', 'yesno' ), true );

		if ( ! empty( $spec['help'] ) ) {
			$says[] = $id . '-help';
		}

		if ( '' !== $problem ) {
			$says[] = $id . '-problem';
		}

		// A required attribute only where one control is the whole answer: a list of boxes has none
		// that means "at least one", and the country's list is hidden behind its field once the
		// script runs, where a browser would refuse the form for a control nobody can see.
		$required    = ! empty( $spec['required'] ) && ! $multiple && 'country' !== $type ? ' required="required"' : '';
		$required_at = '' !== $mark ? ' <span class="wpcpm-field__required">' . $mark . '</span>' : '';
		$invalid     = '' !== $problem ? ' aria-invalid="true"' : '';
		$describedby = empty( $says ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $says ) ) . '"';
		$wrapper     = $multiple || 'country' === $type ? 'div' : 'p';

		printf( '<%1$s class="wpcpm-field wpcpm-field--%2$s%3$s">', esc_attr( $wrapper ), esc_attr( $type ), '' !== $problem ? ' has-problem' : '' );

		if ( in_array( $type, array( 'consent', 'statement' ), true ) ) {
			// The heading, then the box with its words: the declaration's two links are the only
			// markup those words may carry.
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

			if ( 'consent' === $type ) {
				self::render_policy_line();
			}
		} elseif ( $multiple ) {
			printf( '<span class="wpcpm-field__label" id="%1$s-label">%2$s%3$s</span>', esc_attr( $id ), esc_html( $spec['label'] ), $required_at ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.

			$choices = 'yesno' === $type
				? array(
					'Yes' => __( 'Yes', 'wpcredits-program-manager' ),
					'No'  => __( 'No', 'wpcredits-program-manager' ),
				)
				: array_combine( 'languages' === $type ? self::languages() : self::teams(), 'languages' === $type ? self::languages() : self::teams() );
			$picked  = (array) $value;

			printf( '<span class="wpcpm-field__choices" role="group" aria-labelledby="%1$s-label"%2$s>', esc_attr( $id ), $describedby ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.

			foreach ( $choices as $choice => $label ) {
				printf(
					'<label class="wpcpm-field__check"><input type="%1$s" name="%2$s" value="%3$s"%4$s /> <span>%5$s</span></label>',
					'yesno' === $type ? 'radio' : 'checkbox',
					esc_attr( 'yesno' === $type ? $name : $name . '[]' ),
					esc_attr( (string) $choice ),
					checked( in_array( (string) $choice, array_map( 'strval', $picked ), true ), true, false ),
					esc_html( $label )
				);
			}

			echo '</span>';
		} elseif ( 'country' === $type ) {
			WPCPM_Dashboards::render_combo(
				array(
					'id'          => $id,
					'name'        => $name,
					'options'     => array_merge( array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ), array_combine( self::countries(), self::countries() ) ),
					'current'     => is_scalar( $value ) ? (string) $value : '',
					'label'       => $spec['label'],
					'mark'        => $mark,
					'describedby' => implode( ' ', $says ),
					/* translators: Shown in the empty country field, which takes typing. */
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
				// A profile is a text box: a handle is not an address, and `type="url"` would refuse it.
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" maxlength="%5$d"%6$s%7$s%8$s%9$s />',
					'email' === $type ? 'email' : 'text',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					(int) ( isset( $spec['maxlength'] ) ? $spec['maxlength'] : 200 ),
					isset( $spec['auto'] ) ? ' autocomplete="' . esc_attr( $spec['auto'] ) . '"' : '',
					'profile' === $type ? ' autocapitalize="none" spellcheck="false"' : '',
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid . $describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literals and the escaped id above.
				);
			}
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
	 * The line under the consent box, linking the policy the applicant agrees to.
	 */
	private static function render_policy_line() {
		$policy = WPCPM_Form_Guard::policy_url();

		if ( '' === $policy ) {
			return;
		}

		printf(
			'<span class="wpcpm-field__hint">%s</span>',
			wp_kses(
				sprintf(
					/* translators: %1$s: opening link tag to the privacy policy, %2$s: closing link tag. */
					__( 'You can read the privacy policy %1$shere%2$s.', 'wpcredits-program-manager' ),
					'<a href="' . esc_url( $policy ) . '" rel="noopener">',
					'</a>'
				),
				array(
					'a' => array(
						'href' => array(),
						'rel'  => array(),
					),
				)
			)
		);
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

			return WPCPM_Typed_Text::loss_message( $fields['anything']['label'], 'send' );
		}

		$said = array(
			'required'     => __( 'Please fill this in.', 'wpcredits-program-manager' ),
			'unticked'     => __( 'The program can only take mentors who can say this, so please tick it.', 'wpcredits-program-manager' ),
			'bad_email'    => __( 'That does not look like an email address.', 'wpcredits-program-manager' ),
			'bad_choice'   => __( 'Please choose one of the options offered.', 'wpcredits-program-manager' ),
			'bad_profile'  => __( 'Give your WordPress.org username or your profile\'s address.', 'wpcredits-program-manager' ),
			'not_a_number' => __( 'Please give a number of hours.', 'wpcredits-program-manager' ),
			'below_min'    => __( 'Please give at least one hour.', 'wpcredits-program-manager' ),
			'above_max'    => __( 'Please give at most forty hours.', 'wpcredits-program-manager' ),
		);

		return isset( $said[ $problem ] ) ? $said[ $problem ] : __( 'That answer could not be read. Please try it again.', 'wpcredits-program-manager' );
	}

	/*
	 * The submit path
	 * --------------------------------------------------------------------
	 */

	/**
	 * Take one submission, in the sponsor form's order of checks, each numbered below.
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
		if ( ! WPCPM_Form_Guard::claim_actor( WPCPM_Form_Guard::actor_key( self::ACTOR_PREFIX ), WPCPM_Form_Guard::PER_HOUR ) ) {
			self::bounce( 'busy' );
		}

		// 2. The nonce.
		if ( ! wp_verify_nonce( $nonce, self::ACTION_SUBMIT ) ) {
			self::bounce( 'expired', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 3. The switch, the policy and the two lists, none of which a form this site drew can post past.
		if ( '' !== self::closed_reason() ) {
			self::bounce( 'closed' );
		}

		$signals = array();
		$spam    = false;

		// 4. The honeypot: stored as spam, and the sender sees the ordinary confirmation.
		if ( WPCPM_Form_Guard::honeypot_filled( self::HONEYPOT ) ) {
			$spam      = true;
			$signals[] = self::SIGNAL_HONEYPOT;
		}

		// 5. The dwell token: stale bounces, fast holds, and anything else that is not ok is spam.
		$dwell = WPCPM_Form_Guard::check_token( self::DWELL_SCOPE, WPCPM_Request::posted_text( self::TOKEN_FIELD ) );

		if ( WPCPM_Form_Guard::TOKEN_STALE === $dwell ) {
			self::bounce( 'stale', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		if ( WPCPM_Form_Guard::TOKEN_FAST === $dwell ) {
			$signals[] = 'dwell-fast';
		} elseif ( WPCPM_Form_Guard::TOKEN_OK !== $dwell ) {
			$spam      = true;
			$signals[] = self::SIGNAL_DWELL;
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

		// 8. Requiredness, once, after cleaning, skipped for a row headed for the spam pile: telling a
		// bot which answer it missed is free tuition.
		if ( ! $spam ) {
			$problems = self::add_required( $values, $problems );

			if ( ! empty( $problems ) ) {
				self::bounce(
					'again',
					array(
						'values'   => $values,
						'problems' => $problems,
						// What a refused free text and profile were typed as, for their boxes: in the
						// stash alone, since `$values` is what a row stores.
						'typed'    => $cleaned['typed'],
					)
				);
			}
		}

		// 9. The site-wide ceiling degrades rather than refusing: the row is kept and held.
		if ( ! WPCPM_Form_Guard::claim_site( self::SITE_KEY, WPCPM_Form_Guard::PER_DAY ) ) {
			$signals[] = 'site-ceiling';
		}

		$email  = isset( $values['email'] ) ? (string) $values['email'] : '';
		$handle = isset( $values['profile'] ) ? (string) $values['profile'] : '';

		// 10. Content scoring, which holds and never refuses.
		$signals = array_merge( $signals, self::score( $values ) );

		// 11. Duplicates, which hold here and are never merged.
		$signals = array_merge( $signals, self::duplicate_signals( $email, $handle ) );

		// 12. Storage.
		$state   = $spam ? WPCPM_Mentor_Application::STATE_SPAM : ( empty( $signals ) ? WPCPM_Mentor_Application::STATE_NEW : WPCPM_Mentor_Application::STATE_HELD );
		$fields  = self::fields();
		$post_id = WPCPM_Mentor_Application::store( $values, $state, $signals, WPCPM_Form_Guard::consent_evidence( (string) $fields['consent']['box'] ) );

		if ( ! $post_id ) {
			self::bounce( 'lost', array( 'values' => $values ) );
		}

		// 13. The two emails: the applicant hears for any row that is not spam, since a held row is
		// one a person reads first and never one that is over; the Administrators hear of a new
		// row, which is what holding spares them. A spam row's sender sees the same page as anybody.
		$suppressed = false;

		if ( WPCPM_Mentor_Application::STATE_SPAM !== $state ) {
			if ( WPCPM_Form_Guard::claim_mail( self::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY ) ) {
				// A hand-off the mail layer refuses leaves the applicant with no link, as the day's limit
				// does, so the row says so and the applicant is shown the quiet thank-you, which claims no
				// acknowledgement was sent; the row is stored as it would have been.
				if ( ! self::mail_applicant( $post_id ) ) {
					WPCPM_Mentor_Application::add_signal( $post_id, self::SIGNAL_MAIL_FAILED );

					$suppressed = true;
				}
			} else {
				update_post_meta( $post_id, WPCPM_Mentor_Application::META_SIGNALS, array_values( array_unique( array_merge( $signals, array( self::SIGNAL_MAIL_CEILING ) ) ) ) );

				$suppressed = true;
			}
		}

		if ( WPCPM_Mentor_Application::STATE_NEW === $state ) {
			self::mail_administrators( $post_id );
		}

		self::bounce(
			$suppressed ? 'sent-quiet' : 'sent',
			array(
				'reference' => WPCPM_Mentor_Application::reference( $post_id ),
				'email'     => $email,
			)
		);
	}

	/**
	 * What the content of a submission says about it: matched against the site's comment
	 * disallowed list, or carrying too many links. Each holds a row for a person; none refuses it.
	 *
	 * @param array $values Cleaned values.
	 * @return string[]
	 */
	private static function score( array $values ) {
		$signals = array();
		$prose   = isset( $values['anything'] ) ? (string) $values['anything'] : '';
		$name    = isset( $values['name'] ) ? (string) $values['name'] : '';
		$email   = isset( $values['email'] ) ? (string) $values['email'] : '';

		if ( function_exists( 'wp_check_comment_disallowed_list' ) && wp_check_comment_disallowed_list( $name, $email, '', $prose, WPCPM_Form_Guard::client_ip(), WPCPM_Form_Guard::user_agent() ) ) {
			$signals[] = 'disallowed';
		}

		if ( WPCPM_Form_Guard::too_many_links( $prose, WPCPM_Form_Guard::MAX_LINKS ) ) {
			$signals[] = 'links';
		}

		return $signals;
	}

	/**
	 * Whether this address or this username is already in play, read from the site's own data alone
	 * (design, section 4): another application in the queue or waiting for the course, and a mentor
	 * the site knows. Each is a flag that holds the row.
	 *
	 * @param string $email  The address given.
	 * @param string $handle The WordPress.org username given, normalized.
	 * @return string[]
	 */
	private static function duplicate_signals( $email, $handle ) {
		$signals = array();
		$hash    = WPCPM_Mentor_Application::email_hash( $email );
		$handle  = strtolower( trim( (string) $handle ) );
		$live    = array_merge( WPCPM_Mentor_Application::open_states(), array( WPCPM_Mentor_Application::STATE_SELECTED ) );

		foreach ( WPCPM_Mentor_Application::applications( $live ) as $post ) {
			$same_email  = '' !== $hash && (string) get_post_meta( (int) $post->ID, WPCPM_Mentor_Application::META_EMAIL, true ) === $hash;
			$same_handle = '' !== $handle && strtolower( WPCPM_Mentor_Application::handle_of( $post ) ) === $handle;

			if ( $same_email || $same_handle ) {
				$signals[] = self::SIGNAL_DUPLICATE;

				break;
			}
		}

		if ( self::known_mentor( $email, $handle ) ) {
			$signals[] = self::SIGNAL_MENTOR;
		}

		return $signals;
	}

	/**
	 * Whether a mentor the site knows holds this address or this username: an account of any
	 * standing, current or former, with the Mentor role or a mentor record ID, found by its address,
	 * its username (the mentors sync names a mentor's account after their WordPress.org username) or
	 * the profile the sync keeps on it; or a mentor the last mentors sync read from Airtable, found by
	 * the username alone, since the sync's list keeps no address (`sponsorship()`): a mentor row with
	 * no account on the site is not matched by its address.
	 *
	 * @param string $email  The address given.
	 * @param string $handle The WordPress.org username given, lowercased.
	 * @return bool
	 */
	private static function known_mentor( $email, $handle ) {
		$candidates = array();

		if ( '' !== $email ) {
			$candidates[] = get_user_by( 'email', $email );
		}

		if ( '' !== $handle ) {
			$candidates[] = get_user_by( 'login', $handle );
		}

		foreach ( $candidates as $user ) {
			if ( $user instanceof WP_User && self::is_mentor_account( $user ) ) {
				return true;
			}
		}

		if ( '' === $handle ) {
			return false;
		}

		foreach ( (array) get_users(
			array(
				'meta_key' => WPCPM_Mentors_Sync::META_PROFILE,
				'fields'   => 'all',
			)
		) as $user ) {
			if ( $user instanceof WP_User && WPCPM_Mentors_Sync::wporg_username( (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_PROFILE, true ) ) === $handle ) {
				return true;
			}
		}

		foreach ( WPCPM_Mentors_Sync::sponsorship() as $row ) {
			if ( is_array( $row ) && isset( $row['profile'] ) && WPCPM_Mentors_Sync::wporg_username( (string) $row['profile'] ) === $handle ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an account is a mentor's, of any standing.
	 *
	 * @param WP_User $user The account.
	 * @return bool
	 */
	private static function is_mentor_account( WP_User $user ) {
		return in_array( WPCPM_Roles::ROLE_MENTOR, (array) $user->roles, true ) || '' !== trim( (string) get_user_meta( $user->ID, WPCPM_Mentors_Sync::META_RECORD_ID, true ) );
	}

	/**
	 * The confirming link from the acknowledgement: a GET, for logged-out visitors too, answered on
	 * the form's page. One answer for an application that is not one and for a signature that does
	 * not match, so a stranger cannot walk the IDs.
	 */
	public static function handle_verify() {
		$post_id = WPCPM_Request::id( 'app' );
		$post    = $post_id > 0 ? WPCPM_Mentor_Application::application( $post_id ) : null;

		if ( null === $post || ! hash_equals( WPCPM_Mentor_Application::verify_signature( $post_id ), WPCPM_Request::text( 't' ) ) ) {
			self::bounce( 'verify-failed' );
		}

		if ( ! WPCPM_Mentor_Application::confirm( $post_id ) ) {
			self::bounce( 'verified-already' );
		}

		self::bounce( 'verified' );
	}

	/*
	 * The stash and the emails
	 * --------------------------------------------------------------------
	 */

	/**
	 * The form as `WPCPM_Form_Stash` needs it described.
	 *
	 * @return array
	 */
	private static function stash_form() {
		return array(
			'url'           => self::page_url(),
			'outcomes'      => array_keys( self::outcomes() ),
			'query_outcome' => self::QUERY_OUTCOME,
			'query_stash'   => self::QUERY_STASH,
			'prefix'        => self::TRANSIENT_PREFIX,
			'minutes'       => self::TRANSIENT_MINUTES,
			'one_shot'      => array( 'sent', 'sent-quiet', 'verified', 'verified-already', 'verify-failed' ),
		);
	}

	/**
	 * Redirect back with the outcome, anything typed behind a transient.
	 *
	 * @param string $outcome One of `outcomes()`, or `sent` / `sent-quiet`.
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

	/**
	 * The Reply-To an email to an applicant carries: the program's contact address, while Contact
	 * address in emails holds one, so an applicant who replies, as the emails ask, reaches the
	 * program and not the site's own sender.
	 *
	 * @return string[]
	 */
	public static function reply_headers() {
		$contact = WPCPM_Mail_Templates::program_contact();

		return '' !== $contact ? array( 'Reply-To: ' . $contact ) : array();
	}

	/**
	 * Acknowledge the application, with the link that confirms the address. Public, as the one way
	 * it is sent: the submit path sends it (step 13), and so does Put back in the queue on the
	 * Mentors screen, once, to a row the form's checks filed as spam, one past the day's limit or one
	 * whose hand-off failed, which never had it.
	 *
	 * @param int $post_id The application.
	 * @return bool Whether the email was handed off.
	 */
	public static function mail_applicant( $post_id ) {
		$post = WPCPM_Mentor_Application::application( $post_id );

		if ( null === $post ) {
			return false;
		}

		$email  = WPCPM_Mentor_Application::email_of( $post );
		$values = array(
			'site_name'       => WPCPM_Mail::site_name(),
			'applicant_name'  => WPCPM_Mentor_Application::name_of( $post ),
			'reference'       => WPCPM_Mentor_Application::reference_of( $post ),
			'verify_link'     => WPCPM_Mentor_Application::verify_url( $post_id ),
			'applicant_email' => $email,
		);

		$build = static function () use ( $values ) {
			return WPCPM_Mail_Templates::mail( self::MAIL_RECEIVED, $values, array( 'headers' => self::reply_headers() ) );
		};

		// No locale is named: the form asks no language, so the page's own is the applicant's.
		return WPCPM_Mail::send_to( $email, self::MAIL_RECEIVED, $build );
	}

	/**
	 * Tell the Administrators there is an application to read: who applied, their profile and the
	 * way to the queue, and none of their other answers. To the addresses the `mentor_notify`
	 * setting names, or to every Administrator when it names none.
	 *
	 * @param int $post_id The application.
	 * @return int How many were told.
	 */
	private static function mail_administrators( $post_id ) {
		$post = WPCPM_Mentor_Application::application( $post_id );

		if ( null === $post ) {
			return 0;
		}

		$values = array(
			'site_name'      => WPCPM_Mail::site_name(),
			'reference'      => WPCPM_Mentor_Application::reference_of( $post ),
			'applicant_name' => WPCPM_Mentor_Application::name_of( $post ),
			'profile_link'   => WPCPM_Mentor_Application::profile_url( WPCPM_Mentor_Application::handle_of( $post ) ),
			'queue_link'     => admin_url( 'admin.php?page=wpcpm-mentors&tab=queue' ),
		);

		$build = static function () use ( $values ) {
			return WPCPM_Mail_Templates::mail( self::MAIL_NOTICE, $values );
		};

		return WPCPM_Institutions::notify_managers( self::MAIL_NOTICE, $build, 'mentor_notify' );
	}
}
