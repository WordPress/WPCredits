<?php
/**
 * Sponsors module - the public application form.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Loaded here as well as by the plugin's loader, the way the institution form loads the guard.
// This class's suites require it with a handful of others and nothing else, so a shared class it
// reaches for has to arrive with the file rather than with a line in every suite. `require_once`
// is idempotent by real path, so on a booted site the loader's own line has done the work and
// this one costs a stat.
require_once dirname( __DIR__ ) . '/class-wpcpm-form-stash.php';

/**
 * The form a company fills in to ask to sponsor the program, and everything that guards it.
 *
 * The institution application's shape with the sponsor's eight questions (design spec of
 * 4 September 2026, section 9.2, and decision 8: the form on the site replaces the Airtable
 * form). The order the checks run in is the design and is the institution form's: the
 * per-actor ceiling, the nonce, the switch, the honeypot, the dwell token, consent, the fields,
 * requiredness, the two logo files, content scoring and the site-wide degrade, the duplicate
 * flags, then storage and the two mails. Every check with a number lives in `WPCPM_Form_Guard`,
 * so the two public forms cannot drift apart on what "too many" means.
 *
 * **Two files, after the ceiling and before the row.** The logos are the one thing this form
 * takes that is not text. They are accepted through `WPCPM_Image_Upload` (PNG, JPEG or WebP by
 * content, at least 200 pixels wide, re-saved through the editor) only after the per-actor
 * ceiling has counted the request and requiredness has passed, and they are put in the Media
 * Library only after the application row exists, with author 0 and the title the logo card
 * gives, so that approval has only to change the author and record the IDs. One bad file
 * refuses the pair, and a spam row stores no file at all.
 *
 * **Duplicates are flagged twice and never merged.** Another open application with the same
 * address or name (the institution rule), and a sponsor the index already holds with the same
 * name or website host. Both flags stay on a `new` row: a stranger who submits first using a
 * company's published address must not be able to edit or suppress the genuine submission,
 * and a company already in the base is a fact for the manager, who rejects and provisions from
 * the Sponsors card instead of approving a second record.
 *
 * **Consent is a precondition, not an answer.** Anything but `"1"` or `"true"` refuses the
 * whole submission and stores nothing. What is stored instead is the evidence, through the
 * guard: the sentence as rendered, the policy's URL and ID and modified time, when, the
 * truncated address, the browser.
 *
 * The page is deliberately **not gated**: see `ensure_page()`.
 */
class WPCPM_Sponsor_Application {

	/**
	 * The post type.
	 *
	 * Seventeen characters: `register_post_type()` refuses a name longer than twenty and
	 * returns a `WP_Error` nothing reads, which is how the institution type first shipped
	 * unregistered. `bin/test-roles.php` measures every post type this plugin declares.
	 */
	const POST_TYPE = 'wpcpm_sponsor_app';

	const SHORTCODE = 'wpcpm_sponsor_application';
	const BLOCK     = 'wpcpm/sponsor-application';
	const OPT_PAGE  = 'wpcpm_sponsor_application_page_id';
	const STYLE     = 'wpcpm-sponsor-application';

	/** The page's slug. Chosen once and never renamed: a rename breaks every link anybody has been sent. */
	const SLUG = 'sponsor-application';

	/** The `admin_post_` action the form posts to, registered for logged-out visitors too. */
	const ACTION_SUBMIT = 'wpcpm_sponsor_apply';

	/** The dwell token's scope: part of its signature, so a token minted for the institution form is a forgery here. */
	const DWELL_SCOPE = 'wpcpm-sponsor-application-dwell';

	/** The per-actor ceiling's key prefix. Its own, so this form has its own five an hour per source. */
	const ACTOR_PREFIX = 'sponsor-apply';

	/** The site-wide ceiling's key. Its own allowance of forty a day. */
	const SITE_KEY = 'sponsor-apply-site';

	/** The mail ceiling's key. */
	const MAIL_KEY = 'sponsor-apply-mail';

	/** Longest free-text answer kept, in characters. */
	const MAX_TEXT = 4000;

	/** How long a stashed failure or confirmation survives the redirect, in minutes. */
	const TRANSIENT_MINUTES = 30;

	/** Prefix for those stashes. The rest is a random id that travels in the redirect. */
	const TRANSIENT_PREFIX = 'wpcpm_sapp_';

	/** The redirect's argument when there is a stash: its id. Nothing about a submission is in the URL. */
	const QUERY_STASH = 'wpcpm_sapp';

	/** The redirect's argument when there is no stash: the outcome, as itself. */
	const QUERY_OUTCOME = 'wpcpm_sapp_said';

	/** The argument the manager's queue opens one application with. */
	const QUERY_QUEUE = 'wpcpm_sapp_id';

	/** The posted array holding the answers, keyed by `form_key()`. */
	const FIELD_ANSWERS = 'wpcpm_sponsor_application';

	/** The two file fields. */
	const FIELD_LOGO_COLOR = 'wpcpm_sapp_logo_color';
	const FIELD_LOGO_WHITE = 'wpcpm_sapp_logo_white';

	/**
	 * The honeypot's field name.
	 *
	 * Deliberately not `website`, `url` or `company`: a password manager or a browser
	 * autofilling one of those for a human would file a genuine application as spam, and this
	 * form has a real Website question of its own.
	 */
	const HONEYPOT = 'wpcpm_confirm_site';

	/** The dwell token's field name. */
	const TOKEN_FIELD = 'wpcpm_sponsor_application_token';

	/**
	 * The eight columns, spelled as the base spells them.
	 *
	 * `COL_ANYTHING` ends with a full stop and carries an ASCII apostrophe, unlike the
	 * institution table's U+2019; both are pinned in `bin/fixtures/sponsors-table-fields.json`
	 * and asserted by `bin/test-sponsor-application.php`, because `create_records()` sends no
	 * `typecast` and a name spelled any other way is a 422 for the whole record at approval.
	 */
	const COL_NAME     = 'Company Name';
	const COL_WEBSITE  = 'Website';
	const COL_PERSON   = 'Contact Person Full Name';
	const COL_EMAIL    = 'Contact Email';
	const COL_OPTION   = 'Sponsorship options';
	const COL_LOGO     = 'Logo';
	const COL_ANYTHING = "Anything else you'd like to share.";
	const COL_CONSENT  = 'Privacy Policy Compliance';

	const STATE_NEW      = 'new';
	const STATE_HELD     = 'held';
	const STATE_SPAM     = 'spam';
	const STATE_INFO     = 'info';
	const STATE_APPROVED = 'approved';
	const STATE_REJECTED = 'rejected';

	/** Post meta: the cleaned answers, keyed by Airtable column name. Immutable after insert. */
	const META_FIELDS = '_wpcpm_sapp_fields';

	/** Post meta: one of the `STATE_*` values. */
	const META_STATE = '_wpcpm_sapp_state';

	/** Post meta: the human-readable reference, `SAPP-2026-0007`. */
	const META_REFERENCE = '_wpcpm_sapp_reference';

	/** Post meta: the consent evidence, as the guard records it. */
	const META_CONSENT = '_wpcpm_sapp_consent';

	/** Post meta: why the row was held or held as spam, as a list of slugs. */
	const META_SIGNALS = '_wpcpm_sapp_signals';

	/** Post meta: `wp_hash()` of the lowercased contact address, for duplicate flagging and nothing else. */
	const META_EMAIL = '_wpcpm_sapp_email';

	/** Post meta: `array( 'colour' => attachment ID or 0, 'white' => attachment ID or 0 )`. */
	const META_LOGOS = '_wpcpm_sapp_logos';

	/** Post meta: the Airtable record approval created. */
	const META_RECORD = '_wpcpm_sapp_record';

	/** Post meta: the account approval created. */
	const META_USER = '_wpcpm_sapp_user';

	/** Post meta, repeating: one row per event, `event`, `at`, `actor`, `note`. */
	const META_EVENT = '_wpcpm_sapp_event';

	/** Post meta: when the application was last decided, a Unix time; absent while it is open (1.98.1). */
	const META_DECIDED = '_wpcpm_sapp_decided';

	/** Option: the one-time backfill of `META_DECIDED` has run. */
	const OPT_BACKFILL = 'wpcpm_sapp_decided_backfilled';

	/** Another open application names this company or this address. */
	const SIGNAL_DUPLICATE = 'duplicate';

	/** The sponsors index already holds a company with this name or this website. */
	const SIGNAL_IN_BASE = 'in-base';

	/** The Media Library refused an accepted logo; the row stands without it. */
	const SIGNAL_LOGO_FAILED = 'logo-failed';

	/**
	 * The contact address already belongs to an account that is not this application's own.
	 *
	 * Computed fresh everywhere the queue reads it, never written to `META_SIGNALS`: an address
	 * free when the form was submitted may have an account by the time a manager looks, and the
	 * reverse holds too, once a half-done approval has stamped its own account here.
	 */
	const SIGNAL_ACCOUNT = 'account';

	/**
	 * An approval was begun on this application and stopped part-way.
	 *
	 * Computed the way `SIGNAL_ACCOUNT` is and never written to `META_SIGNALS`, because it is
	 * true only until somebody presses Approve again. Added by the S5 review, which found that
	 * an application whose Airtable record already exists looked like any other in the queue
	 * and could still be rejected: the applicant got the neutral decline while a record with
	 * Status Approved, an index row and possibly an account with a membership stood for them.
	 */
	const SIGNAL_HALF_DONE = 'half-done';

	/** The two mails this form sends, named for the mail log. */
	const MAIL_APPLIED  = 'sponsor-applied';
	const MAIL_MANAGERS = 'sponsor-application';

	/**
	 * The six decisions a manager takes, and the deletion behind them.
	 *
	 * One action apiece rather than one handler reading a posted verb: the nonce is keyed to
	 * the action and the application together, so a nonce harvested from the Reject form of one
	 * application cannot approve another. The same six the institution queue has (spec 9.2).
	 */
	const ACTION_APPROVE = 'wpcpm_sapp_approve';
	const ACTION_INFO    = 'wpcpm_sapp_info';
	const ACTION_REJECT  = 'wpcpm_sapp_reject';
	const ACTION_SPAM    = 'wpcpm_sapp_spam';
	const ACTION_REOPEN  = 'wpcpm_sapp_reopen';
	const ACTION_PURGE   = 'wpcpm_sapp_purge';

	/** The form field naming the application every decision is posted for. */
	const FIELD_APPLICATION = 'wpcpm_sapp';

	/**
	 * A manager's question or reason: long enough to be a sentence, short enough to read.
	 *
	 * The question is the whole of what an applicant is told, so an empty one is refused; the
	 * reason on a rejection is never sent anywhere, so it is optional and only the ceiling
	 * applies to it.
	 */
	const MIN_NOTE = 10;
	const MAX_NOTE = 2000;

	/** The two messages the decisions send, named for the mail log. */
	const MAIL_INFO     = 'sponsor-information';
	const MAIL_DECLINED = 'sponsor-declined';

	/** What the application's own history calls each decision. */
	const EVENT_APPROVED = 'approved';
	const EVENT_INFO     = 'information requested';
	const EVENT_REJECTED = 'rejected';
	const EVENT_SPAM     = 'marked as spam';
	const EVENT_REOPENED = 'reopened';

	/**
	 * What each deletion left behind.
	 *
	 * A reference, a state and a date, and never an address or a word of what anybody wrote: a
	 * log that survives the thing it describes must not become the copy of it that the
	 * retention rule was there to remove.
	 */
	const OPT_LOG = 'wpcpm_sponsor_application_log';

	/** How many rows that log keeps. */
	const LOG_MAX = 200;

	/** The Administrator Dashboard card a decision posted from there lands back on. */
	const RETURN_ANCHOR = 'sponsor-applications';

	/**
	 * The one-shot channel `leave()` carries a refusal's own detail on, tagged with the status
	 * it was flashed for.
	 *
	 * A channel of its own and not a shape change to `WPCPM_Sponsors::FLASH` or
	 * `WPCPM_Institutions::FLASH`: both are read elsewhere as a plain string and would show
	 * nothing at all for an array. Tagged with the status rather than trusted on its own, so a
	 * detail flashed for one outcome can never be read behind a later, unrelated one that
	 * carried none of its own.
	 */
	const FLASH_DETAIL = 'wpcpm_sapp_detail';

	/**
	 * How many rows the queue card draws.
	 *
	 * A ceiling and not a page size: the form is open to strangers, so how many rows are waiting
	 * is somebody else's decision, and a card that drew one per submission would answer a bad
	 * afternoon on the form with a screen nobody can open. The oldest fifty are the ones whose
	 * turn it is, and the card says when there are more.
	 */
	const QUEUE_MAX = 50;

	/**
	 * The daily retention run, which deletes what the three application settings say to.
	 *
	 * The institution queue's three settings, reused (spec 9.2): `application_spam_days`,
	 * `application_rejected_days`, `application_approved_days`. Its own hook, so the two queues
	 * can be scheduled an hour apart and a slow night never has both walking the posts at once.
	 */
	const CRON_PURGE = 'wpcpm_purge_sponsor_applications';

	/**
	 * Hooks.
	 *
	 * The submit handler is registered for logged-out visitors as well, which is the whole
	 * point: a company applying has no account and will not have one until a program manager
	 * approves the application.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );

		// The six decisions. `admin_post_` and never `nopriv`: every one of them needs the
		// management capability, and a logged-out request has no business reaching a handler
		// that mails an applicant.
		add_action( 'admin_post_' . self::ACTION_APPROVE, array( __CLASS__, 'handle_approve' ) );
		add_action( 'admin_post_' . self::ACTION_INFO, array( __CLASS__, 'handle_info' ) );
		add_action( 'admin_post_' . self::ACTION_REJECT, array( __CLASS__, 'handle_reject' ) );
		add_action( 'admin_post_' . self::ACTION_SPAM, array( __CLASS__, 'handle_spam' ) );
		add_action( 'admin_post_' . self::ACTION_REOPEN, array( __CLASS__, 'handle_reopen' ) );
		add_action( 'admin_post_' . self::ACTION_PURGE, array( __CLASS__, 'handle_purge' ) );

		add_action( self::CRON_PURGE, array( __CLASS__, 'purge' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_backfill_decided' ) );
	}

	/**
	 * Register the post type, the shortcode, the block and the stylesheet.
	 */
	public static function register() {
		self::register_post_type();
		self::register_assets();

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/sponsor-application';

		if ( function_exists( 'register_block_type' ) && file_exists( $block_dir . '/block.json' ) ) {
			register_block_type(
				$block_dir,
				array( 'render_callback' => array( __CLASS__, 'render_block' ) )
			);
		}
	}

	/**
	 * Register the application post type.
	 *
	 * Invisible everywhere: these rows hold a company's contact details and a stranger's free
	 * text, and the only route to one is the manager's queue, which asks the capability first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Sponsor applications', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Sponsor application', 'wpcredits-program-manager' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'author' ),
				// A capability type nothing is granted, so no role reaches these through any
				// generic post screen even if one were ever exposed.
				'capability_type'     => array( 'wpcpm_sponsor_app', 'wpcpm_sponsor_apps' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register the form's stylesheet and the submit guard.
	 *
	 * The institution form's own file, under this form's handle: the two forms share every
	 * class, and a page is only ever one of them.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style(
				self::STYLE,
				WPCPM_PLUGIN_URL . 'assets/css/application.css',
				array(),
				WPCPM_VERSION
			);
		}

		if ( ! wp_script_is( 'wpcpm-forms', 'registered' ) ) {
			wp_register_script(
				'wpcpm-forms',
				WPCPM_PLUGIN_URL . 'assets/js/forms.js',
				array(),
				WPCPM_VERSION,
				true
			);
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
	 * Create the application page if it is missing.
	 *
	 * **The page is left ungated on purpose.** Every other page this plugin provisions stamps
	 * `WPCPM_Content_Access::META_KEY`; an absent level means public, so gating this one would
	 * fail silently and the only symptom would be that nobody outside the program can reach the
	 * form nobody outside the program has any other way of finding.
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

		// A site that has the page but not the option adopts it rather than creating a second
		// one at `sponsor-application-2`.
		$existing = get_page_by_path( self::SLUG );

		if ( $existing instanceof WP_Post ) {
			update_option( self::OPT_PAGE, $existing->ID, false );

			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Sponsor the WordPress Credits Program', 'wpcredits-program-manager' ),
				'post_name'    => self::SLUG,
				'post_content' => '<!-- wp:' . self::BLOCK . ' /-->',
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
	 * The application page's permalink, if it is published.
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
	 * Keep this one page out of every cache.
	 *
	 * The form carries a nonce and a signed dwell token, and a cached copy serves both to
	 * everybody: one token handed to a thousand visitors is single-use for the first of them
	 * and refused for the rest. Only this page, because a site's caching is not this plugin's
	 * to turn off.
	 */
	public static function no_cache() {
		$page_id = (int) get_option( self::OPT_PAGE );

		if ( ! $page_id || ! is_page( $page_id ) ) {
			return;
		}

		nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The name every page cache on the market reads; a prefixed one would mean nothing to any of them.
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/*
	 * The form
	 * --------------------------------------------------------------------
	 */

	/**
	 * The eight questions, keyed by the exact Airtable column name, in the Airtable form's order.
	 *
	 * Keyed by column name because that is what the record is created from: an approval writes
	 * six of these keys straight through. The logo is two files and the consent is a
	 * precondition, and neither is ever in the stored fields; `server_held()` names them.
	 *
	 * @return array<string, array> Column name => spec.
	 */
	public static function fields() {
		return array(
			self::COL_NAME     => array(
				'group'     => 'company',
				'label'     => __( 'Company Name', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 200,
				'auto'      => 'organization',
			),
			self::COL_WEBSITE  => array(
				'group'    => 'company',
				'label'    => __( 'Website', 'wpcredits-program-manager' ),
				// `type="text"` with `inputmode="url"`, never `type="url"`: the browser refuses a
				// scheme-less address, and `example.com` is how most people write one.
				'type'     => 'url',
				'required' => true,
				'help'     => __( 'For example: example.com', 'wpcredits-program-manager' ),
			),
			self::COL_PERSON   => array(
				'group'     => 'contact',
				'label'     => __( 'Contact Person Full Name', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 120,
				'auto'      => 'name',
			),
			self::COL_EMAIL    => array(
				'group'    => 'contact',
				'label'    => __( 'Contact Email', 'wpcredits-program-manager' ),
				'type'     => 'email',
				'required' => true,
				'auto'     => 'email',
				'help'     => __( 'We send the acknowledgement here, and the account when the program says yes.', 'wpcredits-program-manager' ),
			),
			self::COL_OPTION   => array(
				'group'    => 'support',
				'label'    => __( 'How would you like to support WP Credits?', 'wpcredits-program-manager' ),
				'type'     => 'choice',
				'required' => true,
				'help'     => __( 'Sponsoring tools or services also means sponsoring at least one mentor, at two hours a week.', 'wpcredits-program-manager' ),
			),
			self::COL_LOGO     => array(
				'group'    => 'logo',
				'label'    => __( 'Company logo', 'wpcredits-program-manager' ),
				'type'     => 'logos',
				'required' => false,
				'help'     => __( 'Two versions if you have them, in color and in white for a dark background: PNG, JPEG or WebP, transparent where possible, at least 300 pixels wide.', 'wpcredits-program-manager' ),
			),
			self::COL_ANYTHING => array(
				'group'    => 'more',
				'label'    => __( "Anything else you'd like to share.", 'wpcredits-program-manager' ),
				'type'     => 'textarea',
				'required' => false,
			),
			self::COL_CONSENT  => array(
				'group'    => 'consent',
				'label'    => __( 'I confirm I have read the privacy policy and that the company\'s details can be kept and shown under it.', 'wpcredits-program-manager' ),
				'type'     => 'consent',
				'required' => true,
			),
		);
	}

	/**
	 * The six groups the questions are asked in, in order.
	 *
	 * @return array<string, string> Group key => heading.
	 */
	public static function groups() {
		return array(
			'company' => __( 'About your company', 'wpcredits-program-manager' ),
			'contact' => __( 'Who we should contact', 'wpcredits-program-manager' ),
			'support' => __( 'How you would like to support the program', 'wpcredits-program-manager' ),
			'logo'    => __( 'Your logo', 'wpcredits-program-manager' ),
			'more'    => __( 'Tell us more', 'wpcredits-program-manager' ),
			'consent' => __( 'Before you send this', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The form key one column posts under.
	 *
	 * Hashed rather than used raw because one column name ends with a full stop and carries an
	 * apostrophe, and a form field name is not the place for either.
	 *
	 * @param string $column Airtable column name.
	 * @return string
	 */
	public static function form_key( $column ) {
		return 'f' . substr( md5( (string) $column ), 0, 12 );
	}

	/**
	 * The three choices the support question offers, spelled as the column spells them.
	 *
	 * Pinned in `bin/fixtures/sponsors-table-fields.json`: `create_records()` sends no
	 * `typecast`, so a choice spelled any other way is a 422 for the whole record.
	 *
	 * @return string[]
	 */
	public static function support_choices() {
		return array(
			'Sponsor one or multiple mentors',
			'Sponsor mentors + tools/services',
			'Other (please specify)',
		);
	}

	/**
	 * The two logo halves and the file field each posts under.
	 *
	 * The keys are the index's own spelling (`'colour'`, `'white'`), so what this form records
	 * is what `WPCPM_Sponsors_Index::write_logo_record()` takes at approval.
	 *
	 * @return array<string, string>
	 */
	public static function logo_fields() {
		return array(
			'colour' => self::FIELD_LOGO_COLOR,
			'white'  => self::FIELD_LOGO_WHITE,
		);
	}

	/**
	 * Clean one posted answer into the shape Airtable takes, or refuse it with a reason.
	 *
	 * @param string $column Airtable column name.
	 * @param mixed  $raw    Posted value.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	public static function clean( $column, $raw ) {
		$fields = self::fields();
		$spec   = isset( $fields[ $column ] ) ? $fields[ $column ] : null;

		// A key nobody asked about, and the logo, which is files and not an answer. Refused
		// rather than stored: the fields meta is what an approval writes to Airtable.
		if ( null === $spec || 'logos' === $spec['type'] ) {
			return self::refuse( 'unknown_field' );
		}

		$type = $spec['type'];

		if ( 'choice' === $type ) {
			$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';

			if ( '' === $value ) {
				return self::accept( '' );
			}

			return in_array( $value, self::support_choices(), true ) ? self::accept( $value ) : self::refuse( 'bad_choice' );
		}

		if ( 'consent' === $type ) {
			// The one answer that is never stored as an answer. The guard reads a tick the
			// strict way: "yes" is not a tick, and neither is an array.
			return self::accept( WPCPM_Form_Guard::consented( $raw ) );
		}

		$rules = array( 'type' => 'text' === $type ? 'text' : $type );

		if ( isset( $spec['maxlength'] ) ) {
			$rules['maxlength'] = $spec['maxlength'];
		}

		if ( 'textarea' === $type ) {
			$rules['max_text'] = self::MAX_TEXT;
		}

		return WPCPM_Field_Value::clean( $raw, $rules );
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
	 * @param string $problem Short key, in `WPCPM_Field_Value`'s vocabulary.
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
	 * The two columns the server holds and the form never stores.
	 *
	 * @return string[]
	 */
	public static function server_held() {
		return array( self::COL_LOGO, self::COL_CONSENT );
	}

	/**
	 * Whether the program is taking sponsor applications on this site right now.
	 *
	 * A setting rather than the mere existence of the page, because "on" means accepting writes
	 * from anybody on the internet, and that is a decision somebody makes.
	 *
	 * @return bool
	 */
	public static function is_open() {
		return (bool) WPCPM_Settings::get_value( 'sponsor_applications_enabled', false );
	}

	/**
	 * The site's privacy policy, or an empty string when it has none.
	 *
	 * @return string
	 */
	public static function policy_url() {
		return WPCPM_Form_Guard::policy_url();
	}

	/**
	 * Render the answer this request carries, the form, the confirmation, or nothing.
	 *
	 * Two things stop the form being drawn, and both answer a manager with one sentence and the
	 * public with nothing: the form being switched off, and the site having no privacy policy.
	 * Neither stops a sentence, so a `closed` outcome can be read on the page it applies to.
	 * Nothing to say and nothing to draw returns the empty string, not an empty wrapper.
	 *
	 * @param array $atts Shortcode or block attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( 'wpcpm-forms' );

		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );
		$closed     = self::closed_reason();

		// The editor preview is static: a live preview would mint a nonce and a single-use
		// dwell token on every keystroke that reloads the block, and reading the stash consumes
		// it, and an editor is not the applicant.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '' !== $closed ? self::sentence( $closed ) : self::preview();
		}

		$stash   = self::read_stash();
		$outcome = isset( $stash['outcome'] ) ? (string) $stash['outcome'] : '';

		ob_start();

		if ( 'sent' === $outcome || 'sent-quiet' === $outcome ) {
			self::render_confirmation( $stash, 'sent' === $outcome );
		} else {
			self::render_message( $outcome );
		}

		if ( '' !== $closed ) {
			if ( $can_manage ) {
				printf( '<p class="wpcpm-application__notice">%s</p>', esc_html( $closed ) );
			}
		} elseif ( ! in_array( $outcome, array( 'sent', 'sent-quiet', 'busy' ), true ) ) {
			self::render_form(
				isset( $stash['values'] ) && is_array( $stash['values'] ) ? $stash['values'] : array(),
				isset( $stash['problems'] ) && is_array( $stash['problems'] ) ? $stash['problems'] : array()
			);
		}

		$body = (string) ob_get_clean();

		if ( '' === $body ) {
			return '';
		}

		$title = '' !== $atts['title']
			? '<h2 class="wpcpm-application__title">' . esc_html( $atts['title'] ) . '</h2>'
			: '';

		return '<div class="wpcpm-application">' . $title . $body . '</div>';
	}

	/**
	 * Why the form itself cannot be drawn, or an empty string when it can.
	 *
	 * @return string
	 */
	private static function closed_reason() {
		if ( ! self::is_open() ) {
			return __( 'The sponsor application form is switched off. Turn on "Applications from sponsors" in the plugin settings when the program is ready to take them.', 'wpcredits-program-manager' );
		}

		if ( '' === self::policy_url() ) {
			return __( 'This form is not shown yet: applicants are asked to agree to the privacy policy, and this site has no published privacy policy page. Set one under Settings, Privacy.', 'wpcredits-program-manager' );
		}

		return '';
	}

	/**
	 * One sentence in this page's wrapper, for a manager looking at a form nobody else can see.
	 *
	 * @param string $text What to say.
	 * @return string
	 */
	private static function sentence( $text ) {
		return '<div class="wpcpm-application"><p class="wpcpm-application__notice">' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * The static editor preview: what the form asks, without asking it.
	 *
	 * @return string
	 */
	private static function preview() {
		$out = '<div class="wpcpm-application wpcpm-application--preview">';

		$out .= '<p class="wpcpm-application__notice">' . esc_html__( 'The public sponsor application form is drawn here on the front end. This is a summary, not the form: the live form carries a single-use token that would be spent by the editor.', 'wpcredits-program-manager' ) . '</p>';

		$out .= '<ul class="wpcpm-application__groups">';

		foreach ( self::groups() as $key => $heading ) {
			$out .= sprintf(
				'<li><strong>%1$s</strong> %2$s</li>',
				esc_html( $heading ),
				esc_html(
					sprintf(
						/* translators: %d: how many questions this group asks. */
						_n( '%d question', '%d questions', self::count_in_group( $key ), 'wpcredits-program-manager' ),
						self::count_in_group( $key )
					)
				)
			);
		}

		$out .= '</ul>';

		$out .= '<p>' . esc_html__( 'The logo question takes two files, in color and in white.', 'wpcredits-program-manager' ) . '</p>';

		return $out . '</div>';
	}

	/**
	 * How many questions one group asks.
	 *
	 * @param string $group Group key.
	 * @return int
	 */
	private static function count_in_group( $group ) {
		$count = 0;

		foreach ( self::fields() as $spec ) {
			if ( $spec['group'] === $group ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * The confirmation, drawn from a stash that has already been consumed.
	 *
	 * @param array $stash        What the handler left behind.
	 * @param bool  $acknowledged Whether the acknowledgement was actually sent. False when the
	 *                            day's mail ceiling was reached: the application is stored and
	 *                            queued exactly the same, and only the message is missing.
	 */
	private static function render_confirmation( array $stash, $acknowledged = true ) {
		$reference = isset( $stash['reference'] ) ? (string) $stash['reference'] : '';
		$email     = isset( $stash['email'] ) ? (string) $stash['email'] : '';

		echo '<div class="wpcpm-application__done">';
		echo '<h3>' . esc_html__( 'Thank you for offering to sponsor the WordPress Credits Program. Your application is with us.', 'wpcredits-program-manager' ) . '</h3>';

		if ( '' !== $reference ) {
			printf(
				'<p class="wpcpm-application__reference">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the application reference, e.g. SAPP-2026-0007. */
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
							/* translators: %s: the contact email address the applicant gave. */
							__( 'We have sent an acknowledgement to %s. A program manager reads every application by hand and checks the company against the program\'s licensing and trademark rules, so a reply takes a few working days.', 'wpcredits-program-manager' ),
							$email
						)
						: sprintf(
							/* translators: %s: the contact email address the applicant gave. */
							__( 'We could not send the acknowledgement to %s today, so please keep the reference above. Your application is stored and a program manager will read it by hand; a reply takes a few working days.', 'wpcredits-program-manager' ),
							$email
						)
				)
			);
		}

		echo '</div>';
	}

	/**
	 * The outcomes a redirect can carry, and what each one says.
	 *
	 * One map so that every slug the handler stashes has a sentence, which the suite asserts.
	 *
	 * @return array<string, array> Slug => `array( level, sentence )`.
	 */
	public static function outcomes() {
		return array(
			'again'   => array( 'error', __( 'Nothing has been sent yet. Please look at the answers marked below and send the form again.', 'wpcredits-program-manager' ) ),
			'consent' => array( 'error', __( 'Nothing has been sent yet, and nothing has been stored. The program can only accept an application from a company that confirms the privacy policy, so please tick the last box.', 'wpcredits-program-manager' ) ),
			'stale'   => array( 'error', __( 'This form had been open for a long time, so it was not sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'expired' => array( 'error', __( 'This form had been open for a while and could not be sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'busy'    => array( 'error', __( 'Several applications have already been sent from here in the last hour, so this one was not. Please try again later.', 'wpcredits-program-manager' ) ),
			'lost'    => array( 'error', __( 'Something went wrong at our end and the application was not saved. Nothing has been sent. Please try once more.', 'wpcredits-program-manager' ) ),
			'closed'  => array( 'info', __( 'The program is not taking sponsor applications through this form at the moment.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * Draw the message for an outcome, if it has one.
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
	 * Draw the form itself.
	 *
	 * Multipart, because of the two files; everything else is the institution form's.
	 *
	 * @param array $values   Cleaned values from a failed attempt, keyed by column name.
	 * @param array $problems Problem slugs from that attempt, keyed by column name.
	 */
	private static function render_form( array $values, array $problems ) {
		printf(
			'<form class="wpcpm-application__form" method="post" enctype="multipart/form-data" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-status="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Sending...', 'wpcredits-program-manager' ),
			esc_attr__( 'Sending your application.', 'wpcredits-program-manager' )
		);

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SUBMIT ) );

		// The nonce is written out rather than printed by `wp_nonce_field()` because the dwell
		// token is signed with it, and both have to be the same string.
		printf( '<input type="hidden" name="_wpnonce" value="%s" />', esc_attr( wp_create_nonce( self::ACTION_SUBMIT ) ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TOKEN_FIELD ), esc_attr( self::token() ) );

		WPCPM_Form_Guard::render_honeypot( self::HONEYPOT );

		$fields = self::fields();

		foreach ( self::groups() as $group => $heading ) {
			printf(
				'<fieldset class="wpcpm-application__group"><legend>%s</legend>',
				esc_html( $heading )
			);

			foreach ( $fields as $column => $spec ) {
				if ( $spec['group'] !== $group ) {
					continue;
				}

				self::render_field(
					$column,
					$spec,
					isset( $values[ $column ] ) ? $values[ $column ] : null,
					isset( $problems[ $column ] ) ? (string) $problems[ $column ] : ''
				);
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
	 * @param string $column  Airtable column name.
	 * @param array  $spec    Its spec.
	 * @param mixed  $value   Its value from a failed attempt, or null.
	 * @param string $problem The problem slug from that attempt, or ''.
	 */
	private static function render_field( $column, array $spec, $value, $problem ) {
		$key      = self::form_key( $column );
		$id       = 'wpcpm-sponsor-application-' . $key;
		$name     = self::FIELD_ANSWERS . '[' . $key . ']';
		$type     = $spec['type'];
		$required = ! empty( $spec['required'] ) ? ' required="required"' : '';
		// The label's own mark, printed wherever $required is: the radio group gets it once
		// beside its label, because each radio carries the attribute and the group is what a
		// person reads as one question.
		$required_mark = '' !== $required ? ' <span class="wpcpm-field__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>' : '';
		$invalid       = '' !== $problem ? ' aria-invalid="true"' : '';
		$says          = array();

		if ( ! empty( $spec['help'] ) ) {
			$says[] = $id . '-help';
		}

		if ( '' !== $problem ) {
			$says[] = $id . '-problem';
		}

		$describedby = empty( $says ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $says ) ) . '"';

		// The radios and the two files carry their own wrappers; a `<p>` cannot hold a block.
		$wrapper = in_array( $type, array( 'choice', 'logos' ), true ) ? 'div' : 'p';

		printf(
			'<%1$s class="wpcpm-field wpcpm-field--%2$s%3$s">',
			esc_attr( $wrapper ),
			esc_attr( $type ),
			'' !== $problem ? ' has-problem' : ''
		);

		if ( 'consent' === $type ) {
			printf(
				'<span class="wpcpm-field__consent"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s /><label for="%1$s">%5$s%6$s</label></span>',
				esc_attr( $id ),
				esc_attr( $name ),
				$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
				$describedby, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
				esc_html( $spec['label'] ),
				$required_mark // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.
			);

			self::render_policy_line();
		} elseif ( 'choice' === $type ) {
			printf(
				'<span class="wpcpm-field__label">%1$s%2$s</span>',
				esc_html( $spec['label'] ),
				$required_mark // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.
			);

			$picked = is_scalar( $value ) ? (string) $value : '';

			foreach ( self::support_choices() as $choice ) {
				printf(
					'<label class="wpcpm-field__check"><input type="radio" name="%1$s" value="%2$s"%3$s%4$s /> <span>%5$s</span></label>',
					esc_attr( $name ),
					esc_attr( $choice ),
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					checked( $picked === $choice, true, false ),
					esc_html( $choice )
				);
			}
		} elseif ( 'logos' === $type ) {
			printf( '<span class="wpcpm-field__label">%s</span>', esc_html( $spec['label'] ) );

			// The two file controls are named for the halves and not for the hashed column key:
			// the logo question posts nothing under its own key, so the key would be a hash in
			// an id that a support message has to be able to quote. The hint and the problem
			// keep the hashed id above, which is what `aria-describedby` points at.
			self::render_logo_inputs( 'wpcpm-sponsor-application', $describedby );
		} else {
			printf(
				'<label for="%1$s">%2$s%3$s</label>',
				esc_attr( $id ),
				esc_html( $spec['label'] ),
				$required_mark // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.
			);

			if ( 'textarea' === $type ) {
				printf(
					'<textarea id="%1$s" name="%2$s" rows="4" maxlength="%3$d"%4$s%5$s%6$s>%7$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					(int) self::MAX_TEXT,
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$describedby, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
					esc_textarea( is_scalar( $value ) ? (string) $value : '' )
				);
			} else {
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" maxlength="%5$d"%6$s%7$s%8$s%9$s%10$s />',
					'email' === $type ? 'email' : 'text',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					(int) ( isset( $spec['maxlength'] ) ? $spec['maxlength'] : 200 ),
					'url' === $type ? ' inputmode="url"' : '',
					'email' === $type ? ' inputmode="email"' : '',
					isset( $spec['auto'] ) ? ' autocomplete="' . esc_attr( $spec['auto'] ) . '"' : '',
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid . $describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literals and the escaped id above.
				);
			}
		}

		if ( ! empty( $spec['help'] ) ) {
			printf(
				'<span class="wpcpm-field__hint" id="%1$s-help">%2$s</span>',
				esc_attr( $id ),
				esc_html( $spec['help'] )
			);
		}

		if ( '' !== $problem ) {
			printf(
				'<span class="wpcpm-field__problem" id="%1$s-problem">%2$s</span>',
				esc_attr( $id ),
				esc_html( self::problem_text( $problem ) )
			);
		}

		printf( '</%s>', esc_attr( $wrapper ) );
	}

	/**
	 * The two file controls, one per version, each with its own label.
	 *
	 * A file control cannot be refilled from a stash, which is why a refusal's sentence says
	 * the files have to be chosen again. `accept` narrows the picker; the bytes are what decide.
	 *
	 * @param string $id          The question's id stem.
	 * @param string $describedby The `aria-describedby` attribute, or ''.
	 */
	private static function render_logo_inputs( $id, $describedby ) {
		$labels = array(
			'color' => __( 'In color', 'wpcredits-program-manager' ),
			'white' => __( 'In white, for a dark background', 'wpcredits-program-manager' ),
		);
		$names  = array(
			'color' => self::FIELD_LOGO_COLOR,
			'white' => self::FIELD_LOGO_WHITE,
		);

		foreach ( $labels as $half => $label ) {
			printf(
				'<p class="wpcpm-field__file"><label for="%1$s-logo-%2$s">%3$s</label><input type="file" id="%1$s-logo-%2$s" name="%4$s" accept="image/png,image/jpeg,image/webp"%5$s /></p>',
				esc_attr( $id ),
				esc_attr( $half ),
				esc_html( $label ),
				esc_attr( $names[ $half ] ),
				$describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
			);
		}
	}

	/**
	 * The line under the consent box, linking the policy the applicant is agreeing to.
	 */
	private static function render_policy_line() {
		$policy = self::policy_url();

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
	 * The image problems are the handler's own error codes with `wpcpm_` taken off, so a rule
	 * added to `WPCPM_Image_Upload` shows up here as "could not be read" until it is given a
	 * sentence, never as a blank.
	 *
	 * @param string $problem Problem slug.
	 * @return string
	 */
	private static function problem_text( $problem ) {
		if ( 'required' === $problem ) {
			return __( 'Please fill this in.', 'wpcredits-program-manager' );
		}

		if ( 'bad_email' === $problem ) {
			return __( 'That does not look like an email address.', 'wpcredits-program-manager' );
		}

		if ( 'bad_choice' === $problem ) {
			return __( 'Please choose one of the options offered.', 'wpcredits-program-manager' );
		}

		$images = array(
			'image_size'       => __( 'The image is larger than the site allows.', 'wpcredits-program-manager' ),
			'image_type'       => __( 'The file is not a PNG, JPEG or WebP image.', 'wpcredits-program-manager' ),
			'image_name'       => __( 'The file\'s name says one kind of image and its bytes another.', 'wpcredits-program-manager' ),
			'image_dimensions' => sprintf(
				/* translators: 1: the least width, 2: the longest side. */
				__( 'The image must be at least %1$d pixels wide and no side may pass %2$d pixels.', 'wpcredits-program-manager' ),
				WPCPM_Image_Upload::MIN_WIDTH,
				WPCPM_Image_Upload::MAX_SIDE
			),
			'image_editor'     => __( 'This site could not process the image right now.', 'wpcredits-program-manager' ),
			'image_missing'    => __( 'That file could not be read.', 'wpcredits-program-manager' ),
			'upload_too_large' => __( 'The file is larger than the server accepts.', 'wpcredits-program-manager' ),
			'upload_partial'   => __( 'The upload did not finish.', 'wpcredits-program-manager' ),
		);

		if ( isset( $images[ $problem ] ) || 0 === strpos( $problem, 'image_' ) ) {
			$said = isset( $images[ $problem ] ) ? $images[ $problem ] : $images['image_missing'];

			return $said . ' ' . __( 'Files have to be chosen again.', 'wpcredits-program-manager' );
		}

		return __( 'That answer could not be read. Please try it again.', 'wpcredits-program-manager' );
	}

	/*
	 * Anti-spam, through the guard
	 * --------------------------------------------------------------------
	 */

	/**
	 * Mint a dwell token for the form being rendered.
	 *
	 * @return string
	 */
	public static function token() {
		return WPCPM_Form_Guard::token( self::DWELL_SCOPE, self::ACTION_SUBMIT );
	}

	/**
	 * Judge a posted dwell token.
	 *
	 * @param string $token The posted token.
	 * @return string `ok`, `spam` or `stale`.
	 */
	public static function check_token( $token ) {
		return WPCPM_Form_Guard::check_token( self::DWELL_SCOPE, $token );
	}

	/**
	 * The ceiling key for one source.
	 *
	 * @return string
	 */
	private static function actor_key() {
		return WPCPM_Form_Guard::actor_key( self::ACTOR_PREFIX );
	}

	/*
	 * The submit path
	 * --------------------------------------------------------------------
	 */

	/**
	 * Take one submission.
	 *
	 * `wp_verify_nonce()` and never `check_admin_referer()`: an expired nonce is one more
	 * failure like any other, the answers go into the stash and the form comes back with them in
	 * it, rather than a 403 screen and three paragraphs gone. The order of what follows is the
	 * design, is numbered below, and is asserted as an order by the suite.
	 */
	public static function handle_submit() {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		$posted = array();

		if ( isset( $_POST[ self::FIELD_ANSWERS ] ) && is_array( $_POST[ self::FIELD_ANSWERS ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The nonce is verified two steps down, after the one ceiling that must count a wrong one; every value is validated by type in `clean()`.
			$posted = wp_unslash( $_POST[ self::FIELD_ANSWERS ] );
		}

		// 1. The per-actor ceiling, first for a reason that has nothing to do with spam: it is
		// the only layer that counts a writer with no account, and the refusals below it hand
		// the sender's writing back through a transient, which is two rows in the options table.
		// Nothing above this line writes, and the logo bytes are read only after it.
		if ( ! WPCPM_Form_Guard::claim_actor( self::actor_key(), WPCPM_Form_Guard::PER_HOUR ) ) {
			self::bounce( 'busy' );
		}

		// 2. The nonce.
		if ( ! wp_verify_nonce( $nonce, self::ACTION_SUBMIT ) ) {
			self::bounce( 'expired', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 3. The switch and the policy, neither of which a form this site drew can post past.
		if ( ! self::is_open() || '' === self::policy_url() ) {
			self::bounce( 'closed' );
		}

		$signals = array();
		$spam    = false;

		// 4. The honeypot. Stored and held as spam rather than refused, and the sender sees the
		// ordinary confirmation.
		if ( WPCPM_Form_Guard::honeypot_filled( self::HONEYPOT ) ) {
			$spam      = true;
			$signals[] = 'honeypot';
		}

		// 5. The dwell token.
		$dwell = self::check_token( WPCPM_Request::posted_text( self::TOKEN_FIELD ) );

		if ( WPCPM_Form_Guard::TOKEN_STALE === $dwell ) {
			self::bounce( 'stale', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		if ( WPCPM_Form_Guard::TOKEN_OK !== $dwell ) {
			$spam      = true;
			$signals[] = 'dwell';
		}

		// 6. Consent, which is a precondition and not an answer. Nothing is stored without it,
		// not even as spam.
		$consent_key = self::form_key( self::COL_CONSENT );
		$agreed      = self::clean( self::COL_CONSENT, isset( $posted[ $consent_key ] ) ? $posted[ $consent_key ] : '' );

		if ( empty( $agreed['ok'] ) || true !== $agreed['value'] ) {
			self::bounce( 'consent', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 7. The answers, walked from `fields()` and never from the request: a key nobody asked
		// about cannot become a cell that way.
		$cleaned  = self::clean_all( $posted );
		$values   = $cleaned['values'];
		$problems = $cleaned['problems'];

		// 8. Requiredness, once, after cleaning. A row already headed for the spam pile skips
		// it: telling a bot which field it missed is free tuition.
		if ( ! $spam ) {
			$problems = self::add_required( $values, $problems );

			if ( ! empty( $problems ) ) {
				self::bounce(
					'again',
					array(
						'values'   => $values,
						'problems' => $problems,
					)
				);
			}
		}

		// 9. The two logo files, accepted here and stored only after the row exists. After the
		// ceiling and after requiredness, so no byte of a stranger's file is read for a
		// submission that was never going to be filed; a spam row reads none at all, because a
		// bot's files are not Media Library material. One bad file is a problem on the logo
		// question and refuses the pair.
		$accepted = array();

		if ( ! $spam ) {
			$accepted = self::accept_logos();

			if ( is_string( $accepted ) ) {
				self::bounce(
					'again',
					array(
						'values'   => $values,
						'problems' => array( self::COL_LOGO => $accepted ),
					)
				);
			}
		}

		// 10. Content scoring. Holds, never refuses.
		$signals = array_merge( $signals, self::score( $values ) );

		// 11. The site-wide ceiling degrades rather than refusing: the row is kept and held,
		// the managers are not paged, the applicant still is.
		if ( ! WPCPM_Form_Guard::claim_site( self::SITE_KEY, WPCPM_Form_Guard::PER_DAY ) ) {
			$signals[] = 'site-ceiling';
		}

		// 12. Duplicates are flagged and never merged: another open application, and a sponsor
		// the index already holds. Neither is a hold; both are for the manager.
		if ( self::has_open_duplicate( self::email_of( $values ), self::name_of( $values ) ) ) {
			$signals[] = self::SIGNAL_DUPLICATE;
		}

		if ( ! empty( self::index_matches( self::name_of( $values ), self::website_of( $values ) ) ) ) {
			$signals[] = self::SIGNAL_IN_BASE;
		}

		// 13. Storage. Everything above decided how the row is filed; this is where it lands.
		$post_id = self::store( $values, self::state_for( $spam, $signals ), $signals );

		if ( ! $post_id ) {
			self::clean_up( $accepted );
			self::bounce(
				'lost',
				array(
					'values' => $values,
				)
			);
		}

		// 14. The files, into the Media Library with author 0, now that there is a row to
		// record them on. A library that refuses does not lose the application: the row stands
		// and says so, and the company uploads the logo again after approval.
		if ( ! empty( $accepted ) ) {
			$logos = self::store_logos( $post_id, $accepted, self::name_of( $values ) );

			update_post_meta( $post_id, self::META_LOGOS, $logos['ids'] );

			if ( $logos['failed'] ) {
				self::add_signal( $post_id, self::SIGNAL_LOGO_FAILED );
			}
		}

		// 15. The two mails, gated differently on purpose. The applicant hears for any row that
		// is not spam, because holding is "a person should read this first" and never "this
		// application is over"; the managers hear about a `new` row, because sparing them is
		// what holding is for. A spam row is told nothing and sends nothing, and its sender is
		// still shown what everybody else is shown, or the confirmation page becomes an oracle.
		$state      = (string) get_post_meta( $post_id, self::META_STATE, true );
		$suppressed = false;

		if ( self::STATE_NEW === $state || self::STATE_HELD === $state ) {
			if ( WPCPM_Form_Guard::claim_mail( self::MAIL_KEY, WPCPM_Form_Guard::MAIL_PER_DAY ) ) {
				self::mail_applicant( $post_id );
			} else {
				self::add_signal( $post_id, 'mail-ceiling' );

				$suppressed = true;
			}
		}

		if ( self::STATE_NEW === $state ) {
			self::mail_managers( $post_id );
		}

		self::bounce(
			$suppressed ? 'sent-quiet' : 'sent',
			array(
				'reference' => self::reference( $post_id ),
				'email'     => self::email_of( $values ),
			)
		);
	}

	/**
	 * Add one signal to an application already stored.
	 *
	 * @param int    $post_id The application.
	 * @param string $signal  The signal to add.
	 */
	private static function add_signal( $post_id, $signal ) {
		$signals = get_post_meta( $post_id, self::META_SIGNALS, true );
		$signals = is_array( $signals ) ? $signals : array();

		$signals[] = (string) $signal;

		update_post_meta( $post_id, self::META_SIGNALS, array_values( array_unique( $signals ) ) );
	}

	/**
	 * Clean every answer.
	 *
	 * Pure: it reads, it decides, it writes nothing. **Consent never comes back**: it is dropped
	 * from the values here so that no failure path can repopulate a tick.
	 *
	 * @param array $posted The posted answers, keyed by form key.
	 * @return array{values:array,problems:array} Both keyed by Airtable column name.
	 */
	private static function clean_all( array $posted ) {
		$values   = array();
		$problems = array();

		foreach ( self::fields() as $column => $spec ) {
			if ( 'logos' === $spec['type'] ) {
				continue;
			}

			$key   = self::form_key( $column );
			$raw   = isset( $posted[ $key ] ) ? $posted[ $key ] : '';
			$clean = self::clean( $column, $raw );

			if ( empty( $clean['ok'] ) ) {
				$problems[ $column ] = $clean['problem'];

				continue;
			}

			$values[ $column ] = $clean['value'];
		}

		unset( $values[ self::COL_CONSENT ] );

		return array(
			'values'   => $values,
			'problems' => $problems,
		);
	}

	/**
	 * Add the requiredness problems, once, after everything has been cleaned.
	 *
	 * @param array $values   Cleaned values.
	 * @param array $problems Problems so far.
	 * @return array
	 */
	private static function add_required( array $values, array $problems ) {
		foreach ( self::fields() as $column => $spec ) {
			if ( in_array( $spec['type'], array( 'consent', 'logos' ), true ) || isset( $problems[ $column ] ) || empty( $spec['required'] ) ) {
				continue;
			}

			if ( self::is_blank( isset( $values[ $column ] ) ? $values[ $column ] : null ) ) {
				$problems[ $column ] = 'required';
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

	/**
	 * What the content of a submission says about it.
	 *
	 * Every one of these holds a row for a human. None refuses it, and the sender is never told
	 * which one fired: the signals are for the queue.
	 *
	 * @param array $values Cleaned values.
	 * @return string[] Signal slugs.
	 */
	private static function score( array $values ) {
		$signals = array();
		$name    = self::name_of( $values );
		$email   = self::email_of( $values );
		$prose   = self::prose( $values );

		if ( function_exists( 'wp_check_comment_disallowed_list' ) && wp_check_comment_disallowed_list( $name, $email, '', $prose, WPCPM_Form_Guard::client_ip(), WPCPM_Form_Guard::user_agent() ) ) {
			$signals[] = 'disallowed';
		}

		if ( WPCPM_Form_Guard::too_many_links( $prose, WPCPM_Form_Guard::MAX_LINKS ) ) {
			$signals[] = 'links';
		}

		// A company called the same thing as the person who contacts us is usually a form
		// filled in by a script that had one string to give.
		$person = isset( $values[ self::COL_PERSON ] ) ? trim( (string) $values[ self::COL_PERSON ] ) : '';

		if ( '' !== $name && strtolower( $name ) === strtolower( $person ) ) {
			$signals[] = 'name-is-contact';
		}

		return $signals;
	}

	/**
	 * Every free-text answer, joined, for the checks that read across all of them.
	 *
	 * @param array $values Cleaned values.
	 * @return string
	 */
	private static function prose( array $values ) {
		$parts = array();

		foreach ( self::fields() as $column => $spec ) {
			if ( 'textarea' !== $spec['type'] && 'text' !== $spec['type'] ) {
				continue;
			}

			if ( isset( $values[ $column ] ) && is_scalar( $values[ $column ] ) ) {
				$parts[] = (string) $values[ $column ];
			}
		}

		return implode( "\n", $parts );
	}

	/**
	 * The company's name from a set of cleaned values.
	 *
	 * @param array $values Cleaned values.
	 * @return string
	 */
	private static function name_of( array $values ) {
		return isset( $values[ self::COL_NAME ] ) ? trim( (string) $values[ self::COL_NAME ] ) : '';
	}

	/**
	 * The contact address from a set of cleaned values.
	 *
	 * @param array $values Cleaned values.
	 * @return string
	 */
	private static function email_of( array $values ) {
		return isset( $values[ self::COL_EMAIL ] ) ? trim( (string) $values[ self::COL_EMAIL ] ) : '';
	}

	/**
	 * The company's name as the applicant wrote it, for everything a person reads.
	 *
	 * `post_title` has been through kses (S5 review): "Smith & Jones" is stored as
	 * "Smith &amp; Jones" on the post, and printing that through `esc_html()` shows the entity
	 * itself. The stored fields hold the name as it was typed; the title is only the fallback
	 * for a row whose fields are gone.
	 *
	 * @param WP_Post|null $post The application.
	 * @return string
	 */
	private static function stored_name( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$name = self::name_of( (array) get_post_meta( $post->ID, self::META_FIELDS, true ) );

		return '' !== $name ? $name : trim( (string) $post->post_title );
	}

	/**
	 * The website from a set of cleaned values.
	 *
	 * @param array $values Cleaned values.
	 * @return string
	 */
	private static function website_of( array $values ) {
		return isset( $values[ self::COL_WEBSITE ] ) ? trim( (string) $values[ self::COL_WEBSITE ] ) : '';
	}

	/**
	 * Which state a submission is filed under.
	 *
	 * The two duplicate flags are deliberately not a hold: they say a manager should look, and
	 * holding the second row would make a company whose address a stranger used first silently
	 * worse off than one nobody targeted.
	 *
	 * @param bool  $spam    Whether a spam layer fired.
	 * @param array $signals Every signal raised.
	 * @return string
	 */
	private static function state_for( $spam, array $signals ) {
		if ( $spam ) {
			return self::STATE_SPAM;
		}

		return empty( array_diff( $signals, array( self::SIGNAL_DUPLICATE, self::SIGNAL_IN_BASE ) ) ) ? self::STATE_NEW : self::STATE_HELD;
	}

	/*
	 * The two files
	 * --------------------------------------------------------------------
	 */

	/**
	 * One uploaded file, as PHP describes it, with every member cast on the way out.
	 *
	 * The files array is the one superglobal a form field cannot be read out of with
	 * `WPCPM_Request`, so it is read here, once, for both fields. What makes the bytes safe is
	 * `WPCPM_Image_Upload`, not a filter on this array.
	 *
	 * @param string $field The field name.
	 * @return array{error: int, size: int, tmp_name: string, name: string}
	 */
	private static function uploaded( $field ) {
		$empty = array(
			'error'    => UPLOAD_ERR_NO_FILE,
			'size'     => 0,
			'tmp_name' => '',
			'name'     => '',
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- `handle_submit()` verifies the nonce before it calls this.
		if ( ! isset( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ] ) ) {
			return $empty;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above for the nonce; every member is cast or sanitized on the lines below, which is the only sanitizing an upload admits.
		$raw = wp_unslash( $_FILES[ $field ] );

		return array(
			'error'    => isset( $raw['error'] ) ? (int) $raw['error'] : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $raw['size'] ) ? (int) $raw['size'] : 0,
			'tmp_name' => isset( $raw['tmp_name'] ) ? (string) $raw['tmp_name'] : '',
			'name'     => isset( $raw['name'] ) ? substr( sanitize_file_name( (string) $raw['name'] ), 0, 200 ) : '',
		);
	}

	/**
	 * Whether this path is a file PHP received on this request.
	 *
	 * `is_uploaded_file()` is what stops a posted string naming a file on disk from being read
	 * out of the filesystem and published as somebody's logo. It answers from PHP's own list,
	 * which is empty under CLI, where the suites run.
	 *
	 * @param string $path The temporary path the upload arrived at.
	 * @return bool
	 */
	private static function arrived_by_post( $path ) {
		if ( 'cli' === PHP_SAPI ) {
			return is_readable( $path );
		}

		return is_uploaded_file( $path );
	}

	/**
	 * Accept whichever of the two logo files arrived, or say which problem the pair has.
	 *
	 * Both files are accepted before either is stored, and one bad file refuses both: a company
	 * must not end up with its color logo filed and its white one silently dropped.
	 *
	 * @return array|string Accepted halves keyed by half, `'colour'` and `'white'` (possibly
	 *                      empty), or a problem slug for the logo question.
	 */
	private static function accept_logos() {
		$accepted = array();

		foreach ( self::logo_fields() as $half => $field ) {
			$file = self::uploaded( $field );

			if ( UPLOAD_ERR_NO_FILE === $file['error'] ) {
				continue;
			}

			// PHP's own verdict first: a file the server dropped for its size, or one that did
			// not finish, is not "nothing arrived", and the company would look for the wrong fault.
			if ( in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
				self::clean_up( $accepted );

				return 'upload_too_large';
			}

			if ( UPLOAD_ERR_PARTIAL === (int) $file['error'] ) {
				self::clean_up( $accepted );

				return 'upload_partial';
			}

			if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] < 1 || '' === $file['tmp_name'] || ! self::arrived_by_post( $file['tmp_name'] ) ) {
				self::clean_up( $accepted );

				return 'image_missing';
			}

			$one = WPCPM_Image_Upload::accept( $file['tmp_name'], array( 'name' => $file['name'] ) );

			if ( is_wp_error( $one ) ) {
				self::clean_up( $accepted );

				return self::image_problem( $one->get_error_code() );
			}

			$accepted[ $half ] = $one;
		}

		return $accepted;
	}

	/**
	 * The handler's error code as a problem slug: `wpcpm_image_type` reads `image_type`.
	 *
	 * @param string $code The `WP_Error` code.
	 * @return string
	 */
	private static function image_problem( $code ) {
		$slug = sanitize_key( str_replace( 'wpcpm_', '', (string) $code ) );

		return 0 === strpos( $slug, 'image_' ) ? $slug : 'image_missing';
	}

	/**
	 * Delete the re-saved copies of files that were accepted before something else refused.
	 *
	 * @param mixed $accepted What `accept_logos()` returned, when it returned halves.
	 */
	private static function clean_up( $accepted ) {
		foreach ( (array) $accepted as $one ) {
			if ( is_array( $one ) && ! empty( $one['path'] ) ) {
				wp_delete_file( (string) $one['path'] );
			}
		}
	}

	/**
	 * Put the accepted halves in the Media Library, private, under a name nobody can guess.
	 *
	 * Author 0 until approval, when `WPCPM_Sponsor_Approval` moves the attachments to the
	 * account it creates and publishes them. The titles are the ones `WPCPM_Sponsor_Logo`
	 * gives, so the Media Library reads the same whichever way a logo arrived.
	 *
	 * **Private, and named `sapp-<24 characters>`** (S5 review). These are files a stranger
	 * uploaded through a form on the open internet, and nobody has looked at them yet: as
	 * ordinary `inherit` attachments they were served from `/wp-content/uploads/` and listed by
	 * the unauthenticated `wp/v2/media` endpoint under the title the applicant chose. The
	 * status takes the record out of that listing; the generated name is what keeps the file
	 * itself from being guessed off the company's name, because a direct URL is served whatever
	 * the status says. The company's name stays on the title, which only a manager now sees.
	 *
	 * @param int    $post_id  The application row.
	 * @param array  $accepted Accepted halves keyed by half, `'colour'` and `'white'`.
	 * @param string $company  The company's name, for the title.
	 * @return array `ids` (the two attachment IDs keyed by half, 0 for a half not stored) and `failed`.
	 */
	private static function store_logos( $post_id, array $accepted, $company ) {
		$ids    = array(
			'colour' => 0,
			'white'  => 0,
		);
		$failed = false;
		$stem   = '' !== trim( (string) $company ) ? trim( (string) $company ) : (string) $post_id;

		foreach ( $accepted as $half => $one ) {
			$stored = WPCPM_Image_Upload::store(
				$one,
				// The whole of the file's name that a person chose: the rest is random.
				'sapp',
				0,
				'colour' === $half
					? sprintf(
						/* translators: %s: company name. */
						__( '%s logo (color)', 'wpcredits-program-manager' ),
						$stem
					)
					: sprintf(
						/* translators: %s: company name. */
						__( '%s logo (white)', 'wpcredits-program-manager' ),
						$stem
					),
				array(
					'private'        => true,
					'generated_name' => true,
				)
			);

			if ( is_wp_error( $stored ) ) {
				$failed = true;

				continue;
			}

			$ids[ $half ] = (int) $stored;
		}

		return array(
			'ids'    => $ids,
			'failed' => $failed,
		);
	}

	/**
	 * The two attachment IDs an application holds, 0 for a half it does not.
	 *
	 * @param int $post_id The application.
	 * @return array The two IDs keyed `'colour'` and `'white'`, 0 for a half it does not hold.
	 */
	public static function logos_of( $post_id ) {
		$stored = get_post_meta( (int) $post_id, self::META_LOGOS, true );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'colour' => isset( $stored['colour'] ) ? (int) $stored['colour'] : 0,
			'white'  => isset( $stored['white'] ) ? (int) $stored['white'] : 0,
		);
	}

	/*
	 * Storage
	 * --------------------------------------------------------------------
	 */

	/**
	 * Store one submission and everything known about it.
	 *
	 * @param array  $values  Cleaned values, keyed by Airtable column name.
	 * @param string $state   One of the `STATE_*` values.
	 * @param array  $signals Why it is in that state.
	 * @return int The post ID, or 0.
	 */
	private static function store( array $values, $state, array $signals ) {
		$name   = self::name_of( $values );
		$email  = self::email_of( $values );
		$fields = $values;

		foreach ( self::server_held() as $column ) {
			unset( $fields[ $column ] );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				// Zero for a logged-out submission, which is every real one; a manager testing
				// the form leaves their name on the row, which is a fact worth having.
				'post_author' => 0,
				'post_title'  => '' !== $name ? $name : __( 'Application with no name', 'wpcredits-program-manager' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;
		$specs   = self::fields();

		update_post_meta( $post_id, self::META_FIELDS, $fields );
		update_post_meta( $post_id, self::META_STATE, $state );
		update_post_meta( $post_id, self::META_REFERENCE, self::reference( $post_id ) );
		update_post_meta( $post_id, self::META_CONSENT, WPCPM_Form_Guard::consent_evidence( (string) $specs[ self::COL_CONSENT ]['label'] ) );
		update_post_meta( $post_id, self::META_SIGNALS, array_values( array_unique( $signals ) ) );
		update_post_meta( $post_id, self::META_EMAIL, '' !== $email ? wp_hash( mb_strtolower( $email ) ) : '' );
		update_post_meta(
			$post_id,
			self::META_LOGOS,
			array(
				'colour' => 0,
				'white'  => 0,
			)
		);

		self::add_event( $post_id, 'submitted', 0, '' );

		return $post_id;
	}

	/**
	 * Append one row to an application's event log.
	 *
	 * Repeating meta rather than one array, so two writes in the same second cannot lose each
	 * other's row.
	 *
	 * @param int    $post_id Application post ID.
	 * @param string $event   What happened, as a short slug or phrase.
	 * @param int    $actor   Who did it; 0 for the applicant or the system.
	 * @param string $note    Free text, if the event carries any.
	 */
	public static function add_event( $post_id, $event, $actor = 0, $note = '' ) {
		add_post_meta(
			(int) $post_id,
			self::META_EVENT,
			array(
				'event' => sanitize_text_field( (string) $event ),
				'at'    => time(),
				'actor' => absint( $actor ),
				'note'  => sanitize_textarea_field( (string) $note ),
			)
		);

		// The decision time is an index over the history (clean-up 1.98.1): Recently decided
		// sorts and bounds by it in one query instead of loading every decided row. The
		// history stays the record; the retention run keeps reading it.
		if ( in_array( (string) $event, array( self::EVENT_APPROVED, self::EVENT_REJECTED, self::EVENT_SPAM ), true ) ) {
			update_post_meta( (int) $post_id, self::META_DECIDED, time() );
		} elseif ( self::EVENT_REOPENED === (string) $event ) {
			delete_post_meta( (int) $post_id, self::META_DECIDED );
		}
	}

	/**
	 * Whether an open application already names this address or this company.
	 *
	 * Open means new, held or waiting for information. Compared on the hashed address and on
	 * the trimmed, lowercased name.
	 *
	 * The name comes from `META_FIELDS` and never from `post_title` (S5 review). WordPress runs
	 * a title through kses on the way in for anybody without `unfiltered_html`, which every
	 * applicant is, so a company called "Smith & Jones" is stored as "Smith &amp; Jones" on the
	 * post and as the applicant wrote it in the meta: comparing titles would miss the duplicate
	 * of every company with an ampersand in its name.
	 *
	 * @param string $email The contact address.
	 * @param string $name  The company's name.
	 * @return bool
	 */
	private static function has_open_duplicate( $email, $name ) {
		$hash = '' !== $email ? wp_hash( mb_strtolower( $email ) ) : '';
		$key  = trim( mb_strtolower( $name ) );

		if ( '' === $hash && '' === $key ) {
			return false;
		}

		foreach ( self::applications( array( self::STATE_NEW, self::STATE_HELD, self::STATE_INFO ) ) as $application ) {
			if ( '' !== $hash && (string) get_post_meta( $application->ID, self::META_EMAIL, true ) === $hash ) {
				return true;
			}

			if ( '' !== $key && trim( mb_strtolower( self::name_of( self::fields_of( $application ) ) ) ) === $key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The index rows that already carry this company's name or website host.
	 *
	 * Against the index and not a live search (spec 9.2): the Sponsors table is thirty rows
	 * read whole on every sync run, and the name is compared the way the index compares names,
	 * trimmed and lowercased, because ten records in the base end in a space.
	 *
	 * @param string $name    The company's name.
	 * @param string $website The website, in any spelling.
	 * @return array Matching rows keyed by record ID, in the index's order.
	 */
	public static function index_matches( $name, $website ) {
		$key  = trim( mb_strtolower( (string) $name ) );
		$host = self::host_of( $website );
		$out  = array();

		if ( '' === $key && '' === $host ) {
			return $out;
		}

		foreach ( WPCPM_Sponsors_Index::rows() as $record => $row ) {
			$row_name = trim( mb_strtolower( (string) $row['name'] ) );
			$row_host = self::host_of( (string) $row['website'] );

			if ( ( '' !== $key && $row_name === $key ) || ( '' !== $host && $row_host === $host ) ) {
				$out[ $record ] = $row;
			}
		}

		return $out;
	}

	/**
	 * The host of a website, lowercased, with the scheme and a leading `www.` dropped.
	 *
	 * The base's url column is full of scheme-less values, so a website is compared as the
	 * thing a person means by it rather than as the string they typed.
	 *
	 * @param string $url A website, in any spelling.
	 * @return string The host, or '' when there is none to be had.
	 */
	public static function host_of( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		$host = strtolower( $host );

		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * The six states an application can be in.
	 *
	 * @return string[]
	 */
	public static function states() {
		return array(
			self::STATE_NEW,
			self::STATE_HELD,
			self::STATE_SPAM,
			self::STATE_INFO,
			self::STATE_APPROVED,
			self::STATE_REJECTED,
		);
	}

	/**
	 * Every application in one of the given states, oldest first.
	 *
	 * @param array $states States to include.
	 * @return WP_Post[]
	 */
	public static function applications( $states ) {
		$found = array();

		foreach ( self::query( $states, 'all' ) as $post ) {
			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		return $found;
	}

	/**
	 * The decided applications, newest decision first, bounded.
	 *
	 * @param int $limit Most rows to read.
	 * @return WP_Post[]
	 */
	public static function decided_posts( $limit ) {
		$found = array();

		foreach ( (array) get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'numberposts' => max( 1, (int) $limit ),
				'meta_key'    => self::META_DECIDED,
				'orderby'     => 'meta_value_num',
				'order'       => 'DESC',
				'meta_query'  => array(
					array(
						'key'     => self::META_STATE,
						'value'   => self::decided_states(),
						'compare' => 'IN',
					),
				),
			)
		) as $post ) {
			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		// Two decisions in one second tie on the meta, and MySQL promises nothing about the
		// order of a tie: the ID breaks it, newest first, as the list did before it was
		// bounded (Task 4 review). The window fetched is small, so the sort costs nothing;
		// the one case it cannot settle is a tie straddling the bound itself, which needs
		// two decisions in the same second at the fifty-first row.
		usort(
			$found,
			static function ( WP_Post $a, WP_Post $b ) {
				$difference = (int) get_post_meta( (int) $b->ID, self::META_DECIDED, true ) - (int) get_post_meta( (int) $a->ID, self::META_DECIDED, true );

				return 0 !== $difference ? $difference : ( (int) $b->ID - (int) $a->ID );
			}
		);

		return $found;
	}

	/**
	 * Stamp the decision time on every decided row that predates the meta, once.
	 *
	 * Runs on the first admin page after the upgrade to 1.98.1 and never again; on the live
	 * site of that day no decided row existed, but the code does not know that.
	 */
	public static function maybe_backfill_decided() {
		if ( get_option( self::OPT_BACKFILL ) ) {
			return;
		}

		foreach ( self::applications( self::decided_states() ) as $post ) {
			if ( '' === (string) get_post_meta( (int) $post->ID, self::META_DECIDED, true ) ) {
				update_post_meta( (int) $post->ID, self::META_DECIDED, self::decided_at( $post ) );
			}
		}

		update_option( self::OPT_BACKFILL, 1, false );
	}

	/**
	 * How many applications are waiting for somebody: the menu bubble's number.
	 *
	 * @param int $limit Most rows to count, or 0 for every one of them.
	 * @return int
	 */
	public static function pending_count( $limit = 0 ) {
		return count( self::query( array( self::STATE_NEW, self::STATE_HELD, self::STATE_INFO ), 'ids', $limit ) );
	}

	/**
	 * The query behind both of the above.
	 *
	 * @param array  $states States to include; anything that is not a state is dropped.
	 * @param string $fields `all` for posts, `ids` for IDs.
	 * @param int    $limit  Most rows to read, or 0 for every one of them.
	 * @return array
	 */
	private static function query( $states, $fields, $limit = 0 ) {
		$wanted = array();

		foreach ( (array) $states as $state ) {
			$state = sanitize_key( $state );

			if ( in_array( $state, self::states(), true ) && ! in_array( $state, $wanted, true ) ) {
				$wanted[] = $state;
			}
		}

		// No states means no rows, and never every row.
		if ( empty( $wanted ) ) {
			return array();
		}

		return (array) get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'numberposts' => $limit > 0 ? (int) $limit : -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
				'fields'      => 'ids' === $fields ? 'ids' : '',
				'meta_query'  => array(
					array(
						'key'     => self::META_STATE,
						'value'   => $wanted,
						'compare' => 'IN',
					),
				),
			)
		);
	}

	/**
	 * One application's reference, as it is quoted in mail and on the queue.
	 *
	 * Derived from the post ID rather than counted, because a counter needs a lock and this
	 * needs neither. Not a secret and not a key to anything.
	 *
	 * @param int $post_id Application post ID.
	 * @return string
	 */
	public static function reference( $post_id ) {
		$post = get_post( (int) $post_id );
		$date = $post instanceof WP_Post ? (string) $post->post_date : '';
		$year = '' !== $date ? substr( $date, 0, 4 ) : wp_date( 'Y' );

		return sprintf( 'SAPP-%1$s-%2$04d', $year, (int) $post_id );
	}

	/*
	 * The stash, and the redirect it travels in
	 * --------------------------------------------------------------------
	 */

	/**
	 * The form as `WPCPM_Form_Stash` needs it described (clean-up 1.98.1).
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
			'one_shot'      => array( 'sent', 'sent-quiet' ),
		);
	}

	/**
	 * Redirect back with the outcome, the stash behind a transient: `WPCPM_Form_Stash::bounce()`.
	 *
	 * @param string $outcome One of `outcomes()`, or `sent` / `sent-quiet`.
	 * @param array  $stash   What the page needs to draw: values, problems, a reference.
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
	 * Read the stash this request carries: `WPCPM_Form_Stash::read()`.
	 *
	 * @return array
	 */
	private static function read_stash() {
		return WPCPM_Form_Stash::read( self::stash_form() );
	}

	/*
	 * Mail
	 * --------------------------------------------------------------------
	 */

	/**
	 * Where the manager's queue opens one application.
	 *
	 * @param int $post_id Application post ID.
	 * @return string
	 */
	public static function queue_url( $post_id ) {
		return add_query_arg( array( self::QUERY_QUEUE => (int) $post_id ), admin_url( 'admin.php?page=wpcpm-sponsors' ) );
	}

	/**
	 * Acknowledge the application to the applicant.
	 *
	 * Through `WPCPM_Mail::send_to()`, the single exit for all plugin mail. No link to confirm
	 * the address (plan ruling 10): the manager's compliance check is the human contact.
	 *
	 * @param int $post_id Application post ID.
	 * @return bool Whether the message was handed off.
	 */
	private static function mail_applicant( $post_id ) {
		$fields = get_post_meta( $post_id, self::META_FIELDS, true );
		$fields = is_array( $fields ) ? $fields : array();
		$email  = self::email_of( $fields );

		if ( ! is_email( $email ) ) {
			return false;
		}

		$post      = get_post( $post_id );
		$name      = self::stored_name( $post );
		$reference = (string) get_post_meta( $post_id, self::META_REFERENCE, true );
		$site      = WPCPM_Mail::site_name();

		$build = function () use ( $site, $name, $reference, $email ) {
			$lines = array(
				sprintf(
					/* translators: %s: the company's name as it was given on the form. */
					__( 'Thank you for offering to sponsor the WordPress Credits Program on behalf of %s. Your application is with us.', 'wpcredits-program-manager' ),
					$name
				),
				sprintf(
					/* translators: %s: the application reference, e.g. SAPP-2026-0007. */
					__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
					$reference
				),
				__( 'What happens next: a program manager reads every application by hand and checks the company and its products against the program\'s licensing and trademark rules, so a reply takes a few working days. If the program says yes, we will set up an account on the program site and send you a link to choose a password.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: the address the message went to, 2: site name. */
					__( 'This message went to %1$s because that address was given on the sponsor application form at %2$s. If it was not you, you can ignore it: nothing happens until a program manager decides.', 'wpcredits-program-manager' ),
					$email,
					$site
				),
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: the application reference. */
					__( '[%1$s] We have your application: %2$s', 'wpcredits-program-manager' ),
					$site,
					$reference
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return WPCPM_Mail::send_to( $email, self::MAIL_APPLIED, $build );
	}

	/**
	 * Tell the program managers there is something on the queue.
	 *
	 * Facts and a link, and none of the applicant's own writing. Through the sponsors module's
	 * own setting, `sponsor_notify`, with every manager as the fallback: a new application has no
	 * assigned manager yet.
	 *
	 * @param int $post_id Application post ID.
	 * @return int How many managers were told.
	 */
	private static function mail_managers( $post_id ) {
		$fields = get_post_meta( $post_id, self::META_FIELDS, true );
		$fields = is_array( $fields ) ? $fields : array();
		$post   = get_post( $post_id );

		$name    = self::stored_name( $post );
		$website = self::website_of( $fields );
		$person  = isset( $fields[ self::COL_PERSON ] ) ? (string) $fields[ self::COL_PERSON ] : '';
		$email   = self::email_of( $fields );
		$link    = self::queue_url( $post_id );
		$site    = WPCPM_Mail::site_name();

		$build = function () use ( $site, $name, $website, $person, $email, $link ) {
			$lines = array(
				__( 'A company has applied to sponsor the program through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: company name, 2: website. */
					__( '%1$s, %2$s', 'wpcredits-program-manager' ),
					$name,
					$website
				),
				sprintf(
					/* translators: 1: contact person's name, 2: their email address. */
					__( 'Contact: %1$s, %2$s', 'wpcredits-program-manager' ),
					$person,
					$email
				),
				__( 'It is on the queue here:', 'wpcredits-program-manager' ),
				$link,
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: company name. */
					__( '[%1$s] New sponsor application from %2$s', 'wpcredits-program-manager' ),
					$site,
					$name
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return WPCPM_Institutions::notify_managers( self::MAIL_MANAGERS, $build, 'sponsor_notify' );
	}

	/*
	 * The queue: states, labels, messages
	 * --------------------------------------------------------------------
	 */

	/**
	 * The three states that put an application in front of a manager.
	 *
	 * `held` is in the list on purpose: a submission the checks degraded is one nobody was
	 * told about, so if it is not in front of a manager nobody ever sees it.
	 *
	 * @return string[]
	 */
	public static function open_states() {
		return array( self::STATE_NEW, self::STATE_HELD, self::STATE_INFO );
	}

	/**
	 * The three states somebody has decided: spam, rejected and approved.
	 *
	 * Derived from the other two lists rather than written out again, so a seventh state
	 * cannot be added to `states()` and quietly appear in neither the queue nor the list
	 * under it. The same three `purgeable_states()` names, which is not a coincidence and is
	 * not a reason to share one list: what may be deleted and what has been decided answer
	 * different questions and can part company.
	 *
	 * @return string[]
	 */
	public static function decided_states() {
		return array_values( array_diff( self::states(), self::open_states() ) );
	}

	/**
	 * The states a manager may put back to `new`.
	 *
	 * Never `approved`: approval created a record and an account, and reopening would offer
	 * Approve on a row that has already had it.
	 *
	 * @return string[]
	 */
	public static function reopen_states() {
		return array( self::STATE_HELD, self::STATE_INFO, self::STATE_SPAM, self::STATE_REJECTED );
	}

	/**
	 * The states that may be deleted, by hand or by the retention run.
	 *
	 * The same three the retention settings name, and never an open one: an application
	 * waiting for a decision is somebody's unanswered letter.
	 *
	 * @return string[]
	 */
	public static function purgeable_states() {
		return array( self::STATE_SPAM, self::STATE_REJECTED, self::STATE_APPROVED );
	}

	/**
	 * A state in the words the screens use for it.
	 *
	 * @param string $state An application state.
	 * @return string
	 */
	public static function state_label( $state ) {
		$labels = array(
			self::STATE_NEW      => __( 'new', 'wpcredits-program-manager' ),
			self::STATE_HELD     => __( 'held by the anti-spam checks', 'wpcredits-program-manager' ),
			self::STATE_INFO     => __( 'waiting on the applicant', 'wpcredits-program-manager' ),
			self::STATE_SPAM     => __( 'marked as spam', 'wpcredits-program-manager' ),
			self::STATE_REJECTED => __( 'rejected', 'wpcredits-program-manager' ),
			self::STATE_APPROVED => __( 'approved', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $state ] ) ? $labels[ $state ] : (string) $state;
	}

	/**
	 * The outcomes a decision can flash, in the manager's words.
	 *
	 * Merged into `WPCPM_Sponsors::messages()` for the Sponsors screen and into the
	 * Administrator Dashboard's own map, so one sentence serves both surfaces.
	 *
	 * Side-effect free, deliberately (S5 review): this map is built by anything that wants any
	 * of its sentences, including `WPCPM_Sponsor_Approval::status_sentence()` in the middle of
	 * a POST, and reading the one-shot detail here meant the first of those callers took it and
	 * the manager read the sentence without it. `sentence_for()` resolves the detail instead,
	 * where one status is being printed.
	 *
	 * @return array<string, array{0: string, 1: string}> Status to notice class and sentence.
	 */
	public static function manager_messages() {
		return array(
			'sapp-approved'     => array( 'success', __( 'The application is approved. The Airtable record, the account, the category, the logo and the first offer are in place, and the welcome is queued.', 'wpcredits-program-manager' ) ),
			'sapp-info'         => array( 'success', __( 'The question was sent, with your address to reply to. The application waits on the applicant.', 'wpcredits-program-manager' ) ),
			'sapp-rejected'     => array( 'success', __( 'The application is rejected. The applicant got a short acknowledgement with no reason in it; your note stays on this site.', 'wpcredits-program-manager' ) ),
			'sapp-spam'         => array( 'success', __( 'The application is marked as spam. Nothing was sent to the address on it.', 'wpcredits-program-manager' ) ),
			'sapp-reopened'     => array( 'success', __( 'The application is back in the queue.', 'wpcredits-program-manager' ) ),
			'sapp-purged'       => array( 'success', __( 'The application was deleted for good, and its logo files went with it. Only its reference and the date are kept.', 'wpcredits-program-manager' ) ),
			'sapp-purged-kept'  => array( 'success', __( 'The application was deleted for good. Its logo files stay in the Media Library as the sponsor\'s logo. Only its reference and the date are kept.', 'wpcredits-program-manager' ) ),
			'sapp-unknown'      => array( 'error', __( 'There is no sponsor application with that ID.', 'wpcredits-program-manager' ) ),
			'sapp-state'        => array( 'error', __( 'That application is not in a state this decision applies to. Reload the page to see where it got to.', 'wpcredits-program-manager' ) ),
			'sapp-half-done'    => array( 'error', __( 'This application\'s approval is half done: an Airtable record already exists for it. Press Approve again to finish, then decide what you like.', 'wpcredits-program-manager' ) ),
			'sapp-question'     => array( 'error', __( 'Nothing was sent. The question has to be at least 10 characters: it is the whole of what the applicant is told.', 'wpcredits-program-manager' ) ),
			'sapp-no-email'     => array( 'error', __( 'That application holds no address WordPress can write to.', 'wpcredits-program-manager' ) ),
			'sapp-not-sent'     => array( 'error', __( 'The message could not be handed off, so the application was left as it was. The Mail section on Settings says why.', 'wpcredits-program-manager' ) ),
			'sapp-busy'         => array( 'error', __( 'That application is being approved right now. Give it a minute, then look at it again.', 'wpcredits-program-manager' ) ),
			'sapp-incomplete'   => array( 'error', __( 'That application holds no company name or no address, so there is nothing to create a record from.', 'wpcredits-program-manager' ) ),
			'sapp-airtable'     => array( 'error', __( 'Airtable could not be written, so nothing else happened and the application is where it was. Try again once the base answers.', 'wpcredits-program-manager' ) ),
			'sapp-account'      => array( 'error', __( 'The Airtable record was created, but the account could not be made. Press Approve again once the problem is fixed.', 'wpcredits-program-manager' ) ),
			'account-conflict'  => array( 'error', __( 'Nothing was approved. The contact address already belongs to an account on this site, so approving would hand that person a sponsor\'s dashboard. Ask the company for another address, or attach the existing account on purpose from the Sponsors screen.', 'wpcredits-program-manager' ) ),
			'category-failed'   => array( 'error', __( 'The record and the account are made, but the Sponsors category could not be. Press Approve again to finish: neither the record nor the account will be made a second time.', 'wpcredits-program-manager' ) ),
			'sapp-failed'       => array( 'error', __( 'The approval did not land. What did land is stamped on the application, and pressing Approve again completes the rest.', 'wpcredits-program-manager' ) ),
			'sapp-purge-failed' => array( 'error', __( 'The application could not be deleted.', 'wpcredits-program-manager' ) ),
		);
	}

	/**
	 * One status's sentence as the reader gets it, with whatever detail its refusal carried.
	 *
	 * Called where a notice is printed and nowhere else, because the detail is one-shot: the
	 * Sponsors screen through `WPCPM_Sponsors::notice_sentence()`, the Administrator Dashboard
	 * in `render_messages()`. Before the S5 review this lived in `manager_messages()`, so every
	 * caller of that map took the flash - `WPCPM_Sponsor_Approval::status_sentence()` reads the
	 * map mid-POST to name a provisioning refusal, and took the detail of the very refusal it
	 * was naming, leaving the manager the sentence without it.
	 *
	 * A status that carries no detail gets its own sentence back untouched.
	 *
	 * @param string $status   The status being printed.
	 * @param string $sentence Its sentence from `manager_messages()`.
	 * @return string
	 */
	public static function sentence_for( $status, $sentence ) {
		$detail = self::carried_detail( (string) $status );

		if ( '' === $detail || 'sapp-account' !== (string) $status ) {
			return (string) $sentence;
		}

		return sprintf(
			/* translators: %s: why the account step failed, from WordPress or the provisioning path. */
			__( 'The Airtable record was created, but the account could not be made. The site said: %s Press Approve again once that is fixed.', 'wpcredits-program-manager' ),
			$detail
		);
	}

	/**
	 * The detail a refusal carried, if it was carried for this exact status.
	 *
	 * One shot, and tagged: `leave()` flashes a status and a detail together on the one
	 * redirect that follows a decision, and this takes them together too, so a detail meant
	 * for one outcome can never surface behind a later, unrelated status that carried none of
	 * its own. Taken whichever status is being printed, and discarded when it is not the one
	 * the detail was tagged to: that detail belonged to a notice that has already been drawn or
	 * to a redirect nobody followed, and leaving it behind would surface it later.
	 *
	 * `sentence_for()` is its one caller, so it runs once, where a notice is printed.
	 *
	 * @param string $status The status this sentence is being built for.
	 * @return string
	 */
	private static function carried_detail( $status ) {
		$carried = WPCPM_Flash::take( self::FLASH_DETAIL );

		if ( ! is_array( $carried ) || ! isset( $carried['status'], $carried['detail'] ) || (string) $status !== (string) $carried['status'] ) {
			return '';
		}

		return trim( (string) $carried['detail'] );
	}

	/**
	 * Which outcome a refused approval gets.
	 *
	 * Mapped rather than passed through: an approval's `WP_Error` message is written for the
	 * caller, and what a manager needs is the sentence that says what to do next. A code nobody
	 * can act on falls through to "it did not land".
	 *
	 * @param string $code The `WP_Error` code from `WPCPM_Sponsor_Approval::approve()`.
	 * @return string A key of `manager_messages()`.
	 */
	public static function approval_outcome( $code ) {
		$outcomes = array(
			'wpcpm_sapp_unknown'  => 'sapp-unknown',
			'wpcpm_sapp_state'    => 'sapp-state',
			'wpcpm_sapp_busy'     => 'sapp-busy',
			'wpcpm_sapp_fields'   => 'sapp-incomplete',
			'wpcpm_sapp_no_email' => 'sapp-incomplete',
			'wpcpm_sapp_name'     => 'sapp-incomplete',
			'wpcpm_sapp_conflict' => 'account-conflict',
			'wpcpm_sapp_airtable' => 'sapp-airtable',
			'wpcpm_sapp_account'  => 'sapp-account',
			'wpcpm_sapp_category' => 'category-failed',
		);

		return isset( $outcomes[ (string) $code ] ) ? $outcomes[ (string) $code ] : 'sapp-failed';
	}

	/*
	 * Reading one application
	 * --------------------------------------------------------------------
	 */

	/**
	 * The application a handler was posted for, or null when it is not one of ours.
	 *
	 * The post type is checked and not only the ID: `admin-post.php` takes whatever number is
	 * posted, and a handler that trusted it would set an application state on a page.
	 *
	 * @param int $application_id Post ID.
	 * @return WP_Post|null
	 */
	public static function application( $application_id ) {
		$post = get_post( (int) $application_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * One application's state.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function state_of( WP_Post $post ) {
		return (string) get_post_meta( (int) $post->ID, self::META_STATE, true );
	}

	/**
	 * The answers as they were stored, keyed by Airtable column name.
	 *
	 * @param WP_Post $post The application.
	 * @return array
	 */
	public static function fields_of( WP_Post $post ) {
		$fields = get_post_meta( (int) $post->ID, self::META_FIELDS, true );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * The address the applicant gave, or '' when there is not a usable one.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function email_of_post( WP_Post $post ) {
		return sanitize_email( self::email_of( self::fields_of( $post ) ) );
	}

	/**
	 * One application's reference, the stored one first.
	 *
	 * @param WP_Post $post The application.
	 * @return string
	 */
	public static function reference_of( WP_Post $post ) {
		$stored = (string) get_post_meta( (int) $post->ID, self::META_REFERENCE, true );

		return '' !== $stored ? $stored : self::reference( (int) $post->ID );
	}

	/**
	 * The signals recorded against one application.
	 *
	 * @param WP_Post $post The application.
	 * @return string[]
	 */
	public static function signals_of( WP_Post $post ) {
		$stored = get_post_meta( (int) $post->ID, self::META_SIGNALS, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'strval', $stored ), 'strlen' ) ) );
	}

	/*
	 * The six decisions
	 * --------------------------------------------------------------------
	 */

	/**
	 * What every decision does first: the capability, the nonce keyed to the application, the post.
	 *
	 * In that order and never another. The capability before the nonce, so somebody without it
	 * meets a refusal rather than a nonce screen; the nonce before the post is read, so nothing
	 * is looked up on the strength of a number anybody can post.
	 *
	 * @param string $action The decision's action, which keys the nonce.
	 * @return WP_Post The application.
	 */
	private static function begin( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$application_id = WPCPM_Request::posted_id( self::FIELD_APPLICATION );

		check_admin_referer( $action . '_' . $application_id );

		$post = self::application( $application_id );

		if ( null === $post ) {
			self::leave( 'sapp-unknown' );
		}

		return $post;
	}

	/**
	 * Approve one application: the record, the account, the category, the logo, the offer.
	 *
	 * The work lives in `WPCPM_Sponsor_Approval`, because it is ordered steps with a lock
	 * around them and the first of them talks to Airtable.
	 */
	public static function handle_approve() {
		$post = self::begin( self::ACTION_APPROVE );

		if ( ! in_array( self::state_of( $post ), self::open_states(), true ) ) {
			self::leave( 'sapp-state' );
		}

		$result = WPCPM_Sponsor_Approval::approve( (int) $post->ID, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			// The detail travels with the status, tagged to it, so `sentence_for()` can print
			// what actually happened instead of a canned sentence that may no longer describe it.
			self::leave( self::approval_outcome( $result->get_error_code() ), $result->get_error_message() );
		}

		self::leave( 'sapp-approved' );
	}

	/**
	 * Ask the applicant a question and park the application until they answer.
	 *
	 * The state moves only if the message left: `info` is this queue's word for "asked, and
	 * waiting on them", and writing it after a send that failed would put an application in
	 * front of the next manager as one somebody is already waiting on.
	 */
	public static function handle_info() {
		$post = self::begin( self::ACTION_INFO );

		if ( ! in_array( self::state_of( $post ), self::open_states(), true ) ) {
			self::leave( 'sapp-state' );
		}

		$question = self::posted_note( 'wpcpm_question' );

		if ( mb_strlen( $question ) < self::MIN_NOTE ) {
			self::leave( 'sapp-question' );
		}

		$email = self::email_of_post( $post );

		if ( ! is_email( $email ) ) {
			self::leave( 'sapp-no-email' );
		}

		$manager   = wp_get_current_user();
		$site      = WPCPM_Mail::site_name();
		$reference = self::reference_of( $post );

		$build = static function () use ( $site, $reference, $question, $manager ) {
			$lines = array(
				__( 'Thank you for offering to sponsor the WordPress Credits Program. Before a program manager can take your application further, they have one question:', 'wpcredits-program-manager' ),
				$question,
				__( 'Reply to this message and your answer reaches them directly.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: 1: site name, 2: application reference, e.g. SAPP-2026-0007. */
					__( '[%1$s] A question about your application (%2$s)', 'wpcredits-program-manager' ),
					$site,
					$reference
				),
				'body'    => implode( "\r\n\r\n", $lines ),
				'headers' => WPCPM_Mail::reply_to( $manager ),
			);
		};

		if ( ! WPCPM_Mail::send_to( $email, self::MAIL_INFO, $build ) ) {
			self::leave( 'sapp-not-sent' );
		}

		update_post_meta( (int) $post->ID, self::META_STATE, self::STATE_INFO );
		self::record_event( (int) $post->ID, self::EVENT_INFO, $question );

		self::leave( 'sapp-info' );
	}

	/**
	 * Reject one application, and tell the applicant nothing but that it was decided.
	 *
	 * The acknowledgement carries no reason (plan ruling 11): a reason written for a colleague
	 * reads differently to the person it is about, and the program has no obligation to give
	 * one. The manager's reason stays on the application's own history.
	 *
	 * A half-done approval is refused (S5 review): rejecting one would send the applicant the
	 * neutral decline while an Airtable record with Status Approved, an index row and possibly
	 * an account with a membership already stood for them.
	 */
	public static function handle_reject() {
		$post = self::begin( self::ACTION_REJECT );

		if ( ! in_array( self::state_of( $post ), self::open_states(), true ) ) {
			self::leave( 'sapp-state' );
		}

		if ( self::half_done( (int) $post->ID ) ) {
			self::leave( 'sapp-half-done' );
		}

		$reason = self::posted_note( 'wpcpm_reason' );
		$email  = self::email_of_post( $post );
		$site   = WPCPM_Mail::site_name();

		// The reason is deliberately not captured by this closure. Keeping it out of the
		// builder's scope is what stops a later edit reaching for it "just for the subject".
		$build = static function () use ( $site ) {
			$lines = array(
				__( 'Thank you for your interest in sponsoring the WordPress Credits Program, and for the time it took to write to us.', 'wpcredits-program-manager' ),
				__( 'A program manager has read your application and we are not taking it forward. We are sorry not to have better news.', 'wpcredits-program-manager' ),
				__( 'Companies apply again as their products and the program change, and an application now says nothing about a later one.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] About your application to sponsor the WordPress Credits Program', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		if ( is_email( $email ) ) {
			WPCPM_Mail::send_to( $email, self::MAIL_DECLINED, $build );
		}

		update_post_meta( (int) $post->ID, self::META_STATE, self::STATE_REJECTED );
		self::record_event( (int) $post->ID, self::EVENT_REJECTED, $reason );

		self::leave( 'sapp-rejected' );
	}

	/**
	 * Mark one application as spam, and send nothing at all.
	 *
	 * Not a quieter rejection: the address on a spam submission is forged or is somebody
	 * else's. The row stays until the retention run removes it, so the decision can be undone
	 * by a manager who disagrees.
	 *
	 * A half-done approval is refused, as it is in `handle_reject()` (S5 review): the record
	 * that already stands for the company is not something a spam mark takes back.
	 */
	public static function handle_spam() {
		$post = self::begin( self::ACTION_SPAM );

		if ( ! in_array( self::state_of( $post ), self::open_states(), true ) ) {
			self::leave( 'sapp-state' );
		}

		if ( self::half_done( (int) $post->ID ) ) {
			self::leave( 'sapp-half-done' );
		}

		update_post_meta( (int) $post->ID, self::META_STATE, self::STATE_SPAM );
		self::record_event( (int) $post->ID, self::EVENT_SPAM, '' );

		self::leave( 'sapp-spam' );
	}

	/**
	 * Put a decided application back in the queue.
	 *
	 * A half-done approval is refused (S5 review). It can only be one that was held or waiting
	 * on the applicant when Approve was pressed, since `is_half_done()` answers for an
	 * undecided row alone; putting it "back" would say the row is untouched when a record with
	 * Status Approved already stands for the company.
	 */
	public static function handle_reopen() {
		$post = self::begin( self::ACTION_REOPEN );

		if ( ! in_array( self::state_of( $post ), self::reopen_states(), true ) ) {
			self::leave( 'sapp-state' );
		}

		if ( self::half_done( (int) $post->ID ) ) {
			self::leave( 'sapp-half-done' );
		}

		update_post_meta( (int) $post->ID, self::META_STATE, self::STATE_NEW );
		self::record_event( (int) $post->ID, self::EVENT_REOPENED, '' );

		self::leave( 'sapp-reopened' );
	}

	/**
	 * Delete one decided application for good, and log that it happened.
	 *
	 * No half-done guard, and none is possible: `is_half_done()` is true only for a row in one
	 * of the states Approve accepts, and this refuses everything but the three decided ones,
	 * so the two can never describe the same application (S5 review).
	 */
	public static function handle_purge() {
		$post = self::begin( self::ACTION_PURGE );

		if ( ! in_array( self::state_of( $post ), self::purgeable_states(), true ) ) {
			self::leave( 'sapp-state' );
		}

		// Read before the row goes, because the sentence differs by it: an approved
		// application's attachments are the sponsor's logo and `forget()` leaves them where
		// they are, and a flash that said they went with it would be a lie (S5 review).
		$approved = self::STATE_APPROVED === self::state_of( $post );

		if ( ! self::forget( $post, 0, get_current_user_id() ) ) {
			self::leave( 'sapp-purge-failed' );
		}

		if ( $approved ) {
			self::leave( 'sapp-purged-kept' );
		}

		self::leave( 'sapp-purged' );
	}

	/**
	 * Delete one application, its logo files when nobody approved it, and log the deletion.
	 *
	 * `wp_delete_post( $id, true )`: no trash, because a retention rule that leaves the row in
	 * the trash has not deleted anything. The two attachments go with a spam or rejected row
	 * (plan ruling 20); an approved application's attachments are the sponsor's logo in the
	 * Media Library and stay, as spec section 11 promises the logos survive.
	 *
	 * @param WP_Post $post  The application.
	 * @param int     $days  The retention setting that removed it, or 0 for a manager's hand.
	 * @param int     $actor Who pressed it, 0 for the retention run.
	 * @return bool Whether the row is gone.
	 */
	public static function forget( WP_Post $post, $days, $actor ) {
		$state     = self::state_of( $post );
		$reference = self::reference_of( $post );
		$post_id   = (int) $post->ID;

		if ( self::STATE_APPROVED !== $state ) {
			foreach ( self::logos_of( $post_id ) as $attachment_id ) {
				if ( $attachment_id > 0 ) {
					wp_delete_attachment( $attachment_id, true );
				}
			}
		}

		if ( ! wp_delete_post( $post_id, true ) ) {
			return false;
		}

		self::log_purge( $post_id, $reference, $state, (int) $days, (int) $actor );

		return true;
	}

	/**
	 * The deletions this queue has made, newest last.
	 *
	 * @return array[]
	 */
	public static function application_log() {
		$log = get_option( self::OPT_LOG, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Record that an application was deleted.
	 *
	 * @param int    $post_id   The application that was deleted.
	 * @param string $reference Its reference.
	 * @param string $state     The state it was in.
	 * @param int    $days      The retention setting that removed it, or 0 for a manager.
	 * @param int    $actor     Who pressed it, 0 for the retention run.
	 */
	private static function log_purge( $post_id, $reference, $state, $days, $actor ) {
		$log = self::application_log();

		$log[] = array(
			'at'        => time(),
			'id'        => (int) $post_id,
			'reference' => sanitize_text_field( (string) $reference ),
			'state'     => sanitize_key( (string) $state ),
			'days'      => (int) $days,
			'actor'     => (int) $actor,
		);

		if ( count( $log ) > self::LOG_MAX ) {
			$log = array_slice( $log, -self::LOG_MAX );
		}

		update_option( self::OPT_LOG, $log, false );
	}

	/**
	 * A posted note, trimmed to the ceiling.
	 *
	 * `sanitize_textarea_field()` and not `WPCPM_Request::posted_text()`: a question to an
	 * applicant has paragraphs in it, and `sanitize_text_field()` would fold them into one line.
	 *
	 * @param string $name The posted field.
	 * @return string
	 */
	private static function posted_note( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- begin() has checked the nonce before any handler reaches here.
		if ( ! isset( $_POST[ $name ] ) || ! is_scalar( $_POST[ $name ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		$note = sanitize_textarea_field( wp_unslash( $_POST[ $name ] ) );

		return trim( mb_substr( $note, 0, self::MAX_NOTE ) );
	}

	/**
	 * Add one row to the application's history, in the manager's name.
	 *
	 * @param int    $post_id The application.
	 * @param string $event   One of the `EVENT_` constants.
	 * @param string $note    The question or the reason, or ''.
	 */
	private static function record_event( $post_id, $event, $note ) {
		self::add_event( (int) $post_id, $event, get_current_user_id(), $note );
	}

	/**
	 * Return from a decision with a one-shot outcome, and stop.
	 *
	 * A decision taken on the Administrator Dashboard goes back there, its sentence on that
	 * page's channel; every other press returns to the wp-admin Sponsors screen. The posted
	 * destination is never followed as a URL: `WPCPM_Return` maps it to one of two places.
	 *
	 * @param string $status A key of `manager_messages()`.
	 * @param string $detail A sentence for `sentence_for()` to print behind $status's own,
	 *                       tagged to it; '' when the status needs none.
	 */
	private static function leave( $status, $detail = '' ) {
		if ( '' !== $detail ) {
			WPCPM_Flash::set(
				self::FLASH_DETAIL,
				array(
					'status' => (string) $status,
					'detail' => (string) $detail,
				)
			);
		}

		if ( class_exists( 'WPCPM_Return' ) && WPCPM_Return::DASHBOARD === WPCPM_Request::posted_key( WPCPM_Return::FIELD ) ) {
			WPCPM_Flash::set( WPCPM_Institutions::FLASH, $status );
			wp_safe_redirect( WPCPM_Return::url( home_url( '/' ) ) );
			exit;
		}

		WPCPM_Flash::set( WPCPM_Sponsors::FLASH, $status );
		wp_safe_redirect( admin_url( 'admin.php?page=wpcpm-sponsors' ) );
		exit;
	}

	/*
	 * The decision forms
	 * --------------------------------------------------------------------
	 */

	/**
	 * The decisions one application offers, as forms.
	 *
	 * Drawn on the wp-admin Sponsors screen and on the Administrator Dashboard by the same
	 * method, so the two surfaces cannot offer different decisions for one row.
	 *
	 * @param WP_Post $post   The application.
	 * @param string  $state  Its state.
	 * @param string  $return `WPCPM_Return::DASHBOARD` when drawn on the Administrator Dashboard, else ''.
	 */
	public static function render_actions( WP_Post $post, $state, $return = '' ) {
		echo '<h3>' . esc_html__( 'What happens next', 'wpcredits-program-manager' ) . '</h3>';

		$name  = self::stored_name( $post );
		$name  = '' !== $name ? $name : self::reference_of( $post );
		$email = self::email_of_post( $post );

		if ( in_array( $state, self::open_states(), true ) ) {
			$confirm = sprintf(
				/* translators: 1: company name, 2: contact email address. */
				__( 'Create an Airtable record with Status Approved and a site account for %1$s, and email a password-set link to %2$s? The Airtable record cannot be removed from here.', 'wpcredits-program-manager' ),
				$name,
				'' !== $email ? $email : __( 'the address on the application', 'wpcredits-program-manager' )
			);

			// The in-base flag, said again where it matters most: approving a company the base
			// already holds creates a second record (plan ruling 17).
			if ( in_array( self::SIGNAL_IN_BASE, self::signals_of( $post ), true ) ) {
				$confirm = __( 'The base already holds a sponsor with this name or website, and approving creates a second record. If it is the same company, reject this application and use Create account on the Sponsors card instead.', 'wpcredits-program-manager' ) . ' ' . $confirm;
			}

			self::render_decision_form(
				$post,
				array(
					'return'  => (string) $return,
					'action'  => self::ACTION_APPROVE,
					'label'   => __( 'Approve', 'wpcredits-program-manager' ),
					'class'   => 'button button-primary',
					'confirm' => $confirm,
				)
			);

			self::render_decision_form(
				$post,
				array(
					'return'   => (string) $return,
					'action'   => self::ACTION_INFO,
					'label'    => __( 'Send this question', 'wpcredits-program-manager' ),
					'field'    => 'wpcpm_question',
					'prompt'   => __( 'Ask the applicant something. It is sent as it is written, with your address to reply to.', 'wpcredits-program-manager' ),
					'required' => true,
				)
			);

			self::render_decision_form(
				$post,
				array(
					'return'  => (string) $return,
					'action'  => self::ACTION_REJECT,
					'label'   => __( 'Reject', 'wpcredits-program-manager' ),
					'field'   => 'wpcpm_reason',
					'prompt'  => __( 'Why, for the next manager who reads this. It is never sent to the applicant.', 'wpcredits-program-manager' ),
					'confirm' => sprintf(
						/* translators: %s: company name. */
						__( 'Reject the application from %s? They get a short acknowledgement with no reason in it, and your note stays on this site.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);

			self::render_decision_form(
				$post,
				array(
					'return'  => (string) $return,
					'action'  => self::ACTION_SPAM,
					'label'   => __( 'Reject as spam', 'wpcredits-program-manager' ),
					'confirm' => sprintf(
						/* translators: %s: company name. */
						__( 'Mark the application from %s as spam? Nothing at all is sent to the address on it, and its logo files go with it when it is deleted.', 'wpcredits-program-manager' ),
						$name
					),
				)
			);
		}

		if ( in_array( $state, self::reopen_states(), true ) ) {
			self::render_decision_form(
				$post,
				array(
					'return' => (string) $return,
					'action' => self::ACTION_REOPEN,
					'label'  => __( 'Put back in the queue', 'wpcredits-program-manager' ),
				)
			);
		}

		if ( in_array( $state, self::purgeable_states(), true ) ) {
			self::render_decision_form(
				$post,
				array(
					'return'  => (string) $return,
					'action'  => self::ACTION_PURGE,
					'label'   => __( 'Delete for good', 'wpcredits-program-manager' ),
					// Two sentences, chosen by the state: `forget()` deletes the logo files of
					// an application nobody approved and leaves an approved one's, because
					// those are the sponsor's logo in the Media Library (S5 review).
					'confirm' => self::STATE_APPROVED === $state
						? sprintf(
							/* translators: %s: company name. */
							__( 'Delete the application from %s for good? Every answer on it goes and only its reference and the date are kept; its logo files stay in the Media Library as the sponsor\'s logo. This cannot be undone.', 'wpcredits-program-manager' ),
							$name
						)
						: sprintf(
							/* translators: %s: company name. */
							__( 'Delete the application from %s for good? Every answer on it goes and its logo files go with it; only its reference and the date are kept. This cannot be undone.', 'wpcredits-program-manager' ),
							$name
						),
				)
			);
		}
	}

	/**
	 * One decision's form.
	 *
	 * The nonce is keyed to the action and the application together. The double-submit guard
	 * is inert on wp-admin, where forms.js is not loaded, and live on the Administrator
	 * Dashboard, which enqueues it; the guard yields to a canceled confirm.
	 *
	 * @param WP_Post $post The application.
	 * @param array   $args `return`, `action`, `label`, `class`, `confirm`, `field`, `prompt`, `required`.
	 */
	private static function render_decision_form( WP_Post $post, array $args ) {
		$args = array_merge(
			array(
				'return'   => '',
				'action'   => '',
				'label'    => '',
				'class'    => 'button',
				'confirm'  => '',
				'field'    => '',
				'prompt'   => '',
				'required' => false,
			),
			$args
		);

		printf(
			'<form class="wpcpm-app-action" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%3$s"%2$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			'' !== $args['confirm'] ? ' onsubmit="return confirm(\'' . esc_js( $args['confirm'] ) . '\');"' : '',
			esc_attr__( 'Working', 'wpcredits-program-manager' )
		);
		wp_nonce_field( $args['action'] . '_' . (int) $post->ID );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $args['action'] ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::FIELD_APPLICATION ), (int) $post->ID );

		if ( class_exists( 'WPCPM_Return' ) ) {
			WPCPM_Return::field( (string) $args['return'], self::RETURN_ANCHOR );
		}

		if ( '' !== $args['field'] ) {
			$required_mark = $args['required'] ? ' <span class="wpcpm-field__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>' : '';

			printf(
				'<p class="wpcpm-app-action__note"><label for="%1$s-%2$d">%3$s%4$s</label><br /><textarea id="%1$s-%2$d" name="%1$s" rows="3" maxlength="%5$d"%6$s></textarea></p>',
				esc_attr( $args['field'] ),
				(int) $post->ID,
				esc_html( $args['prompt'] ),
				$required_mark, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.
				(int) self::MAX_NOTE,
				$args['required'] ? ' minlength="' . (int) self::MIN_NOTE . '" required="required"' : ''
			);
		}

		printf( '<button type="submit" class="%1$s">%2$s</button>', esc_attr( $args['class'] ), esc_html( $args['label'] ) );
		echo '</form>';
	}

	/*
	 * The queue, on the wp-admin Sponsors screen and the Administrator Dashboard
	 * --------------------------------------------------------------------
	 */

	/**
	 * Whether an application's contact address already belongs to an account that is not its
	 * own.
	 *
	 * Computed here rather than read from `META_SIGNALS`: unlike the duplicate flags, which are
	 * decided once at submission, an address free then may have an account by the time a manager
	 * reads the queue, and an application that has since had its own account attached must not
	 * flag as a conflict with itself. Guarded by `class_exists()` like the category step in
	 * `WPCPM_Sponsor_Approval::approve()`, which owns the stamp this reads.
	 *
	 * @param int    $application_id The application post.
	 * @param string $email          Its contact address, already resolved.
	 * @return bool
	 */
	private static function email_conflict( $application_id, $email ) {
		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}

		$found = get_user_by( 'email', $email );

		if ( ! $found ) {
			return false;
		}

		$ours = class_exists( 'WPCPM_Sponsor_Approval' ) ? WPCPM_Sponsor_Approval::stamped_user( $application_id ) : 0;

		return (int) $found->ID !== (int) $ours;
	}

	/**
	 * Whether an approval was begun on this application and left unfinished.
	 *
	 * One guarded call to `WPCPM_Sponsor_Approval::is_half_done()`, which owns the stamp and
	 * the test, so the three handlers that refuse and the two surfaces that mark all read the
	 * one answer. Guarded like `email_conflict()` above: the queue is drawn on the
	 * Administrator Dashboard as well, and a card must not fatal because a class it only
	 * reports on is not loaded.
	 *
	 * @param int $application_id The application post.
	 * @return bool
	 */
	private static function half_done( $application_id ) {
		return class_exists( 'WPCPM_Sponsor_Approval' ) && WPCPM_Sponsor_Approval::is_half_done( (int) $application_id );
	}

	/**
	 * The mark a half-done approval carries, on the queue row and on the open application.
	 *
	 * Added by the S5 review: the row looked like any other, and Reject, Reject as spam and
	 * Put back in the queue took it. They refuse it now, and this says why before anybody
	 * presses one of them.
	 */
	private static function render_half_done_mark() {
		printf(
			'<p><span class="wpcpm-inst-mark wpcpm-inst-mark--half-done" title="%1$s">%2$s</span> %3$s</p>',
			esc_attr__( 'An earlier press of Approve created the Airtable record for this application and then stopped, so a record with Status Approved already stands for the company.', 'wpcredits-program-manager' ),
			esc_html__( 'approval half done', 'wpcredits-program-manager' ),
			esc_html__( 'Press Approve again to finish. Reject, Reject as spam and Put back in the queue are refused until it is.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * The open applications as facts, for a card that must never hold the posts.
	 *
	 * @param int $limit Most rows to return.
	 * @return array[] `id`, `reference`, `company`, `website`, `person`, `email`, `at`, `state`,
	 *                 `state_label`, `signals`, `held`, `duplicate`, `in_base`, `account`,
	 *                 `half_done`, oldest first.
	 */
	public static function queue_facts( $limit = self::QUEUE_MAX ) {
		$rows = array();

		foreach ( array_slice( self::applications( self::open_states() ), 0, max( 1, (int) $limit ) ) as $post ) {
			$fields  = self::fields_of( $post );
			$email   = self::email_of( $fields );
			$signals = self::signals_of( $post );
			$state   = self::state_of( $post );
			$name    = self::stored_name( $post );

			if ( self::email_conflict( (int) $post->ID, $email ) ) {
				$signals[] = self::SIGNAL_ACCOUNT;
			}

			if ( self::half_done( (int) $post->ID ) ) {
				$signals[] = self::SIGNAL_HALF_DONE;
			}

			$rows[] = array(
				'id'          => (int) $post->ID,
				'reference'   => self::reference_of( $post ),
				'company'     => '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ),
				'website'     => self::website_of( $fields ),
				'person'      => isset( $fields[ self::COL_PERSON ] ) ? (string) $fields[ self::COL_PERSON ] : '',
				'email'       => $email,
				'at'          => (int) get_post_time( 'U', true, $post ),
				'state'       => $state,
				'state_label' => self::state_label( $state ),
				'signals'     => $signals,
				'held'        => self::STATE_HELD === $state,
				'duplicate'   => in_array( self::SIGNAL_DUPLICATE, $signals, true ),
				'in_base'     => in_array( self::SIGNAL_IN_BASE, $signals, true ),
				'account'     => in_array( self::SIGNAL_ACCOUNT, $signals, true ),
				'half_done'   => in_array( self::SIGNAL_HALF_DONE, $signals, true ),
			);
		}

		return $rows;
	}

	/**
	 * The application the screen was asked to open, or null.
	 *
	 * A render, not a handler: it reads the query string, which is view state.
	 *
	 * @return WP_Post|null
	 */
	public static function open_from_request() {
		$id = WPCPM_Request::id( self::QUERY_QUEUE );

		return $id > 0 ? self::application( $id ) : null;
	}

	/**
	 * The queue card: every open application, oldest first, with the way to open each, and
	 * `render_decided()`'s list of the decided ones under it in the same card (S5 review).
	 *
	 * @param string $screen_url The Sponsors screen's URL, which the Open links carry.
	 */
	public static function render_queue( $screen_url ) {
		$open    = self::applications( self::open_states() );
		$waiting = count( $open );
		$rows    = array_slice( $open, 0, self::QUEUE_MAX );

		echo '<div class="wpcpm-card" id="wpcpm-sponsor-applications">';
		printf(
			'<h2>%1$s <span class="wpcpm-count">%2$s</span></h2>',
			esc_html__( 'Sponsor applications', 'wpcredits-program-manager' ),
			esc_html( number_format_i18n( $waiting ) )
		);
		echo '<p class="description">' . esc_html__( 'Companies that applied through the form on the site, oldest first. Open one to read its answers, its logo files and what the base already holds, then decide it. A decision taken here is the same decision the Administrator Dashboard offers, and approving creates the Airtable record, the account, the category, the logo record and the first offer in one press.', 'wpcredits-program-manager' ) . '</p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Nothing is waiting. New applications appear here.', 'wpcredits-program-manager' ) . '</p>';
		} else {
			if ( count( $rows ) < $waiting ) {
				printf(
					'<p class="description">%s</p>',
					esc_html(
						sprintf(
							/* translators: 1: how many rows are drawn, 2: how many are waiting in total. */
							__( 'Showing the oldest %1$s of %2$s. The list stops there so that a burst of applications cannot make this screen too slow to open; decide these and the next of them take their place.', 'wpcredits-program-manager' ),
							number_format_i18n( count( $rows ) ),
							number_format_i18n( $waiting )
						)
					)
				);
			}

			echo '<ol class="wpcpm-queue">';

			foreach ( $rows as $post ) {
				self::render_queue_row( $post, $screen_url );
			}

			echo '</ol>';
		}

		self::render_decided( $screen_url );

		echo '</div>';
	}

	/**
	 * The decided applications, under the queue and in the same card: newest decision first.
	 *
	 * Built for the S5 review, which found that two of the six decisions could not be reached
	 * by pressing anything. Nothing listed spam, rejected or approved rows, so "Put back in
	 * the queue" and "Delete for good" were offered only on an application opened by a
	 * hand-typed `?wpcpm_sapp_id=`: a genuine application the honeypot or the dwell check
	 * filed as spam was seen by nobody and deleted thirty days later, and a mis-pressed
	 * "Reject as spam" had no way back. The rows are drawn by the queue's own method, so a
	 * decided application reads the same as an open one and carries the same Open link.
	 *
	 * Only here, on the wp-admin Sponsors screen: the Administrator Dashboard keeps the
	 * compact open-queue card, and spec section 10 gives the full card to Phase S6.
	 *
	 * @param string $screen_url The Sponsors screen's URL, which the Open links carry.
	 */
	private static function render_decided( $screen_url ) {
		$rows = self::decided_posts( self::QUEUE_MAX + 1 );
		$more = count( $rows ) > self::QUEUE_MAX;
		$rows = array_slice( $rows, 0, self::QUEUE_MAX );

		echo '<section class="wpcpm-sapp-decided">';
		echo '<h3>' . esc_html__( 'Recently decided', 'wpcredits-program-manager' ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No application has been decided yet.', 'wpcredits-program-manager' ) . '</p>';
			echo '</section>';

			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html(
				$more
					? sprintf(
						/* translators: 1: how many rows are drawn. */
						__( 'The %1$s most recent decided applications. Open one to put it back in the queue or to delete it for good.', 'wpcredits-program-manager' ),
						number_format_i18n( count( $rows ) )
					)
					: __( 'Every application somebody has decided, newest first. Open one to put it back in the queue or to delete it for good.', 'wpcredits-program-manager' )
			)
		);

		echo '<ol class="wpcpm-queue">';

		foreach ( $rows as $post ) {
			self::render_queue_row( $post, $screen_url );
		}

		echo '</ol>';
		echo '</section>';
	}

	/**
	 * One row of the queue.
	 *
	 * @param WP_Post $post       The application.
	 * @param string  $screen_url The Sponsors screen's URL.
	 */
	private static function render_queue_row( WP_Post $post, $screen_url ) {
		$state   = self::state_of( $post );
		$signals = self::signals_of( $post );
		$fields  = self::fields_of( $post );
		$name    = self::stored_name( $post );

		if ( self::email_conflict( (int) $post->ID, self::email_of( $fields ) ) ) {
			$signals[] = self::SIGNAL_ACCOUNT;
		}

		$held_by = array_values( array_diff( $signals, array( self::SIGNAL_DUPLICATE, self::SIGNAL_IN_BASE, self::SIGNAL_ACCOUNT ) ) );

		echo '<li class="wpcpm-queue-item">';

		printf(
			'<h3 class="wpcpm-queue-title"><span class="wpcpm-inst-name__text">%1$s</span> <span class="wpcpm-inst-muted">%2$s</span></h3>',
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			esc_html( self::reference_of( $post ) )
		);

		// The same row is drawn under "Recently decided", where "waiting since" would be a
		// lie: a decided row says when the decision was taken instead (S5 review).
		$decided = in_array( $state, self::decided_states(), true );
		$when    = $decided ? self::decided_at( $post ) : (int) get_post_time( 'U', true, $post );

		printf(
			'<p class="wpcpm-queue-age">%s</p>',
			esc_html(
				$decided
					? sprintf(
						/* translators: 1: human-readable time difference, 2: date and time. */
						__( 'Decided %1$s ago, on %2$s.', 'wpcredits-program-manager' ),
						human_time_diff( $when, time() ),
						wp_date( 'Y-m-d H:i', $when )
					)
					: sprintf(
						/* translators: 1: human-readable time difference, 2: date and time. */
						__( 'Waiting %1$s, since %2$s.', 'wpcredits-program-manager' ),
						human_time_diff( $when, time() ),
						wp_date( 'Y-m-d H:i', $when )
					)
			)
		);

		printf(
			'<p class="wpcpm-queue-country">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: website, 2: contact person, 3: contact email address. */
					__( '%1$s. Contact: %2$s, %3$s.', 'wpcredits-program-manager' ),
					self::website_of( $fields ),
					isset( $fields[ self::COL_PERSON ] ) ? (string) $fields[ self::COL_PERSON ] : '',
					self::email_of( $fields )
				)
			)
		);

		if ( self::STATE_HELD === $state ) {
			printf(
				'<p><span class="wpcpm-inst-mark wpcpm-inst-mark--held" title="%1$s">%2$s</span> %3$s</p>',
				esc_attr__( 'The anti-spam checks held this submission. Nothing was announced about it, and none of the checks refused it.', 'wpcredits-program-manager' ),
				esc_html__( 'held', 'wpcredits-program-manager' ),
				esc_html(
					empty( $held_by )
						? __( 'Held with no check recorded against it. Open it and decide it on what is on it.', 'wpcredits-program-manager' )
						: sprintf(
							/* translators: %s: how many checks held the submission. */
							_n( '%s check held it; open the application to read it.', '%s checks held it; open the application to read them.', count( $held_by ), 'wpcredits-program-manager' ),
							number_format_i18n( count( $held_by ) )
						)
				)
			);
		}

		if ( in_array( self::SIGNAL_DUPLICATE, $signals, true ) ) {
			printf(
				'<p><span class="wpcpm-inst-mark wpcpm-inst-mark--duplicate" title="%1$s">%2$s</span> %3$s</p>',
				esc_attr__( 'Another application in the queue names the same company or the same address.', 'wpcredits-program-manager' ),
				esc_html__( 'possible duplicate', 'wpcredits-program-manager' ),
				esc_html__( 'Open both and decide; nothing is ever merged.', 'wpcredits-program-manager' )
			);
		}

		if ( in_array( self::SIGNAL_IN_BASE, $signals, true ) ) {
			printf(
				'<p><span class="wpcpm-inst-mark wpcpm-inst-mark--duplicate" title="%1$s">%2$s</span> %3$s</p>',
				esc_attr__( 'The sponsors index already holds a company with this name or this website.', 'wpcredits-program-manager' ),
				esc_html__( 'already in the base', 'wpcredits-program-manager' ),
				esc_html__( 'Approving would create a second record; open it and read what the base has.', 'wpcredits-program-manager' )
			);
		}

		if ( in_array( self::SIGNAL_ACCOUNT, $signals, true ) ) {
			printf(
				'<p><span class="wpcpm-inst-mark wpcpm-inst-mark--account" title="%1$s">%2$s</span> %3$s</p>',
				esc_attr__( 'The contact address on this application already belongs to an account on this site.', 'wpcredits-program-manager' ),
				esc_html__( 'already an account', 'wpcredits-program-manager' ),
				esc_html__( 'Approving would open the Sponsor Dashboard to that account; look at it before deciding.', 'wpcredits-program-manager' )
			);
		}

		if ( self::half_done( (int) $post->ID ) ) {
			self::render_half_done_mark();
		}

		printf(
			'<p><a class="button" href="%1$s">%2$s</a> <span class="wpcpm-inst-muted">%3$s</span></p>',
			esc_url( add_query_arg( array( self::QUERY_QUEUE => (int) $post->ID ), $screen_url ) ),
			esc_html__( 'Open this application', 'wpcredits-program-manager' ),
			esc_html( self::state_label( $state ) )
		);

		echo '</li>';
	}

	/**
	 * One application, open: the checks, every answer, the logos, the base, the decisions.
	 *
	 * @param WP_Post $post       The application.
	 * @param string  $screen_url The Sponsors screen's URL, for the way back.
	 */
	public static function render_open( WP_Post $post, $screen_url ) {
		$state = self::state_of( $post );
		$name  = self::stored_name( $post );

		echo '<div class="wpcpm-card wpcpm-application" id="wpcpm-sponsor-application">';

		printf(
			'<h2>%1$s <span class="wpcpm-inst-muted">%2$s</span></h2>',
			esc_html( '' !== $name ? $name : __( '(no name)', 'wpcredits-program-manager' ) ),
			esc_html( self::reference_of( $post ) )
		);

		printf(
			'<p class="wpcpm-inst-read">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html(
				sprintf(
					/* translators: 1: application state, 2: date and time it arrived. */
					__( 'State: %1$s. Arrived %2$s.', 'wpcredits-program-manager' ),
					self::state_label( $state ),
					wp_date( 'Y-m-d H:i', (int) get_post_time( 'U', true, $post ) )
				)
			),
			esc_url( $screen_url . '#wpcpm-sponsor-applications' ),
			esc_html__( 'Back to the queue', 'wpcredits-program-manager' )
		);

		if ( self::half_done( (int) $post->ID ) ) {
			self::render_half_done_mark();
		}

		self::render_signals( $post, $state );
		self::render_answers( $post );
		self::render_logos( $post );
		self::render_base_matches( $post );
		self::render_actions( $post, $state );

		echo '</div>';
	}

	/**
	 * The application in full for a card that is not the Sponsors screen: the answers, the
	 * logo files and what the base already holds, without the heading and without the
	 * decisions, which the card draws itself (spec 10, the Administrator Dashboard's card).
	 *
	 * @param WP_Post $post The application.
	 */
	public static function render_details( WP_Post $post ) {
		self::render_answers( $post );
		self::render_logos( $post );
		self::render_base_matches( $post );
	}

	/**
	 * Every answer the applicant gave, in the order the form asked for them.
	 *
	 * The question is printed as the applicant read it, with the Airtable column under it,
	 * because the manager deciding this is the person who will look the record up in the base.
	 * Every value is printed escaped: this is a stranger's prose on an admin screen.
	 *
	 * @param WP_Post $post The application.
	 */
	public static function render_answers( WP_Post $post ) {
		$fields = self::fields_of( $post );
		$logos  = self::logos_of( (int) $post->ID );

		echo '<table class="widefat striped wpcpm-list wpcpm-app-answers"><tbody>';

		foreach ( self::fields() as $column => $spec ) {
			$column = (string) $column;
			$label  = ! empty( $spec['label'] ) ? (string) $spec['label'] : $column;

			if ( self::COL_LOGO === $column ) {
				$count = ( $logos['colour'] > 0 ? 1 : 0 ) + ( $logos['white'] > 0 ? 1 : 0 );
				$value = $count > 0
					? sprintf(
						/* translators: %s: how many logo files. */
						_n( '%s file, shown below.', '%s files, shown below.', $count, 'wpcredits-program-manager' ),
						number_format_i18n( $count )
					)
					: '';
			} elseif ( self::COL_CONSENT === $column ) {
				$value = self::consent_line( get_post_meta( (int) $post->ID, self::META_CONSENT, true ) );
			} else {
				$answer = isset( $fields[ $column ] ) ? $fields[ $column ] : '';
				$value  = is_array( $answer ) ? implode( ', ', array_map( 'strval', $answer ) ) : (string) $answer;
			}

			printf(
				'<tr><th scope="row">%1$s<br /><code class="wpcpm-inst-record">%2$s</code></th><td>%3$s</td></tr>',
				esc_html( $label ),
				esc_html( $column ),
				'' !== trim( $value )
					? esc_html( $value )
					: '<span class="wpcpm-inst-muted">' . esc_html__( 'no answer', 'wpcredits-program-manager' ) . '</span>'
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * The consent record in one sentence.
	 *
	 * @param mixed $consent The stored evidence.
	 * @return string
	 */
	private static function consent_line( $consent ) {
		if ( ! is_array( $consent ) || empty( $consent ) ) {
			return __( 'No consent record was stored with this application.', 'wpcredits-program-manager' );
		}

		$at    = isset( $consent['at'] ) ? (int) $consent['at'] : 0;
		$parts = array(
			$at > 0
				? sprintf(
					/* translators: %s: date and time. */
					__( 'Agreed %s.', 'wpcredits-program-manager' ),
					wp_date( 'Y-m-d H:i', $at )
				)
				: __( 'Agreed; no time was recorded.', 'wpcredits-program-manager' ),
		);

		if ( ! empty( $consent['sentence'] ) ) {
			$parts[] = (string) $consent['sentence'];
		}

		if ( ! empty( $consent['url'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: privacy policy address. */
				__( 'Policy: %s', 'wpcredits-program-manager' ),
				(string) $consent['url']
			);
		}

		if ( ! empty( $consent['modified'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: date the privacy policy was last changed. */
				__( 'The policy was last changed %s; anything later than the agreement above is not what they read.', 'wpcredits-program-manager' ),
				(string) $consent['modified']
			);
		}

		return implode( ' ', $parts );
	}

	/**
	 * The logo files, from the Media Library.
	 *
	 * @param WP_Post $post The application.
	 */
	private static function render_logos( WP_Post $post ) {
		$logos  = self::logos_of( (int) $post->ID );
		$labels = array(
			'colour' => __( 'In color', 'wpcredits-program-manager' ),
			'white'  => __( 'In white, for a dark background', 'wpcredits-program-manager' ),
		);

		echo '<h3>' . esc_html__( 'Logo', 'wpcredits-program-manager' ) . '</h3>';

		if ( $logos['colour'] < 1 && $logos['white'] < 1 ) {
			echo '<p>' . esc_html__( 'No logo file was sent. The company can upload one on the Sponsor Dashboard after approval, or the sponsors sync copies the one in the base.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<div class="wpcpm-sapp-logos">';

		foreach ( $labels as $half => $label ) {
			if ( $logos[ $half ] < 1 ) {
				continue;
			}

			// The half named on the figure, so the stylesheet can give the white version its dark
			// ground whether or not the color one came with it.
			printf(
				'<figure class="wpcpm-sapp-logo wpcpm-sapp-logo--%3$s">%1$s<figcaption>%2$s</figcaption></figure>',
				wp_get_attachment_image( (int) $logos[ $half ], 'medium' ),
				esc_html( $label ),
				esc_attr( sanitize_html_class( (string) $half ) )
			);
		}

		echo '</div>';
	}

	/**
	 * What the sponsors index already holds under this company's name or website.
	 *
	 * The name is the one in `META_FIELDS`, as it is in `has_open_duplicate()` and for the same
	 * reason (S5 review): `post_title` has been through kses, so "Smith & Jones" is stored
	 * there as "Smith &amp; Jones" and matches nothing in the index.
	 *
	 * @param WP_Post $post The application.
	 */
	private static function render_base_matches( WP_Post $post ) {
		$fields  = self::fields_of( $post );
		$matches = self::index_matches( self::name_of( $fields ), self::website_of( $fields ) );

		echo '<h3>' . esc_html__( 'What the base already has', 'wpcredits-program-manager' ) . '</h3>';

		if ( empty( $matches ) ) {
			echo '<p>' . esc_html__( 'No sponsor in the index carries this name or this website. Approving creates a record.', 'wpcredits-program-manager' ) . '</p>';

			return;
		}

		echo '<p>' . esc_html__( 'These sponsors match on the name or on the website host. Approving creates a second record: if this is the same company, reject the application and use Create account on the Sponsors card instead.', 'wpcredits-program-manager' ) . '</p>';
		echo '<ul class="wpcpm-app-matches">';

		foreach ( $matches as $record => $row ) {
			printf(
				'<li><span class="wpcpm-inst-name__text">%1$s</span> <code class="wpcpm-inst-record">%2$s</code> %3$s</li>',
				esc_html( '' !== trim( (string) $row['name'] ) ? trim( (string) $row['name'] ) : __( '(no name)', 'wpcredits-program-manager' ) ),
				esc_html( (string) $record ),
				esc_html( '' !== (string) $row['status'] ? (string) $row['status'] : __( 'no status', 'wpcredits-program-manager' ) )
			);
		}

		echo '</ul>';
	}

	/**
	 * What the form's checks made of this submission, in plain words.
	 *
	 * Printed for every application and not only for a held one: two of the checks hold
	 * nothing by themselves, and "nothing was flagged" is evidence too.
	 *
	 * @param WP_Post $post  The application.
	 * @param string  $state Its state.
	 */
	private static function render_signals( WP_Post $post, $state ) {
		$held    = self::STATE_HELD === (string) $state;
		$signals = self::signals_of( $post );
		$labels  = self::signal_labels();

		printf(
			'<h3>%s</h3>',
			esc_html(
				$held
					? __( 'Why this application is held', 'wpcredits-program-manager' )
					: __( 'What the checks made of it', 'wpcredits-program-manager' )
			)
		);

		if ( $held ) {
			echo '<p>' . esc_html__( 'Holding spares the managers a message and nothing more. The applicant was acknowledged exactly as any other was, and none of these checks refused anything: each one holds a row for a person to look at, which is what this is.', 'wpcredits-program-manager' ) . '</p>';
		}

		if ( empty( $signals ) ) {
			echo '<p>' . esc_html(
				$held
					? __( 'No check recorded anything against it. The state says held and nothing says why, so decide it on what is on it.', 'wpcredits-program-manager' )
					: __( 'Nothing. Every check the form makes passed.', 'wpcredits-program-manager' )
			) . '</p>';

			return;
		}

		echo '<ul class="wpcpm-app-signals">';

		foreach ( $signals as $signal ) {
			printf(
				'<li>%s</li>',
				esc_html(
					isset( $labels[ $signal ] )
						? $labels[ $signal ]
						: sprintf(
							/* translators: %s: the signal's slug. */
							__( 'A check this screen has no words for yet recorded "%s".', 'wpcredits-program-manager' ),
							$signal
						)
				)
			);
		}

		echo '</ul>';
	}

	/**
	 * Every signal the form can record, in the manager's words.
	 *
	 * @return array<string, string>
	 */
	public static function signal_labels() {
		return array(
			'honeypot'               => __( 'A field no visitor can see was filled in, which is what an automated submission does and a person cannot.', 'wpcredits-program-manager' ),
			'dwell'                  => sprintf(
				/* translators: %s: a number of seconds. */
				__( 'It arrived without the token the form hands a browser, or less than %s seconds after the page was drawn.', 'wpcredits-program-manager' ),
				number_format_i18n( WPCPM_Form_Guard::MIN_SECONDS )
			),
			'disallowed'             => __( 'Something written on it matches this site\'s comment disallowed list.', 'wpcredits-program-manager' ),
			'links'                  => sprintf(
				/* translators: %s: a number of links. */
				__( 'The written answers carry %s links or more.', 'wpcredits-program-manager' ),
				number_format_i18n( WPCPM_Form_Guard::MAX_LINKS )
			),
			'name-is-contact'        => __( 'The company and the person to contact were given the same name, which is what a script with one string to offer does.', 'wpcredits-program-manager' ),
			'site-ceiling'           => sprintf(
				/* translators: %s: how many applications a day the site accepts before it starts holding them. */
				__( 'The site had already taken %s sponsor applications that day, so this one was kept and held instead of being refused. It says nothing about the application itself.', 'wpcredits-program-manager' ),
				number_format_i18n( WPCPM_Form_Guard::PER_DAY )
			),
			'mail-ceiling'           => __( 'The day\'s limit on acknowledgements had been reached when it arrived, so no message was sent to the applicant.', 'wpcredits-program-manager' ),
			self::SIGNAL_DUPLICATE   => __( 'Another open application already named this company or this address. Nothing is ever merged: open both and decide.', 'wpcredits-program-manager' ),
			self::SIGNAL_IN_BASE     => __( 'The sponsors index already holds a sponsor with this name or website. Approving creates a second record.', 'wpcredits-program-manager' ),
			self::SIGNAL_LOGO_FAILED => __( 'A logo file was accepted but the Media Library refused to store it. The company can upload it again after approval.', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The decisions for one application, by ID, wherever a card has only the facts.
	 *
	 * @param int    $post_id The application.
	 * @param string $return  `WPCPM_Return::DASHBOARD` when drawn on the Administrator Dashboard, else ''.
	 */
	public static function render_decision( $post_id, $return = '' ) {
		$post = self::application( $post_id );

		if ( null === $post ) {
			return;
		}

		self::render_actions( $post, self::state_of( $post ), $return );
	}

	/*
	 * Retention
	 * --------------------------------------------------------------------
	 */

	/**
	 * Delete the applications the retention settings say have been kept long enough.
	 *
	 * The daily `CRON_PURGE` run. Three settings, one per decided state, each in days, and
	 * **0 means never**: an approved application is the paper trail behind a record and an
	 * account, and the default keeps it forever on purpose. The clock runs from the decision
	 * and not from the submission, so lengthening a setting gives every row the longer life.
	 * The files go with a spam or rejected row and stay with an approved one (`forget()`).
	 * The run never re-reads a state before deleting: it rests on every state change writing
	 * an event with its time (`add_event()`), so a reopen that lands during the run moves the
	 * row's last decision past the cutoff and the row is skipped.
	 *
	 * @return int How many were deleted.
	 */
	public static function purge() {
		$retention = array(
			self::STATE_SPAM     => 'application_spam_days',
			self::STATE_REJECTED => 'application_rejected_days',
			self::STATE_APPROVED => 'application_approved_days',
		);

		$purged = 0;

		foreach ( $retention as $state => $setting ) {
			$days = (int) WPCPM_Settings::get_value( $setting, 0 );

			if ( $days < 1 ) {
				continue;
			}

			$cutoff = time() - ( $days * DAY_IN_SECONDS );

			foreach ( self::applications( array( $state ) ) as $post ) {
				if ( ! $post instanceof WP_Post || self::decided_at( $post ) > $cutoff ) {
					continue;
				}

				if ( self::forget( $post, $days, 0 ) ) {
					++$purged;
				}
			}
		}

		return $purged;
	}

	/**
	 * When an application was last decided: the newest row of its own history, or when it
	 * arrived for a row with no history at all.
	 *
	 * @param WP_Post $post The application.
	 * @return int Unix time, UTC.
	 */
	private static function decided_at( WP_Post $post ) {
		$latest = 0;

		foreach ( (array) get_post_meta( (int) $post->ID, self::META_EVENT, false ) as $event ) {
			if ( is_array( $event ) && isset( $event['at'] ) && (int) $event['at'] > $latest ) {
				$latest = (int) $event['at'];
			}
		}

		return $latest > 0 ? $latest : (int) get_post_time( 'U', true, $post );
	}

	/**
	 * Delete every application. Called on uninstall.
	 *
	 * The files of an application nobody approved go with it, as they do in `forget()`; an
	 * approved application's attachments are the sponsor's logo in the Media Library and stay,
	 * which spec section 11 promises. Post meta goes with the posts. The page itself stays,
	 * as every page the plugin creates does: a page is site content.
	 */
	public static function delete_all() {
		$applications = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);

		foreach ( (array) $applications as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			if ( self::STATE_APPROVED !== self::state_of( $post ) ) {
				foreach ( self::logos_of( (int) $post->ID ) as $attachment_id ) {
					if ( $attachment_id > 0 ) {
						wp_delete_attachment( $attachment_id, true );
					}
				}
			}

			wp_delete_post( (int) $post->ID, true );
		}

		delete_option( self::OPT_PAGE );
		delete_option( self::OPT_LOG );
		delete_option( self::OPT_BACKFILL );
	}
}
