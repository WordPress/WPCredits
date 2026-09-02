<?php
/**
 * Institutions module - the public application form.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The form an institution fills in to ask to join the program, and everything that guards it.
 *
 * This is the plugin's first logged-out write path. Nothing else here accepts a request from
 * somebody with no account, so none of the checks below had a pattern to copy and every one
 * of them is written out rather than borrowed. `grep -rn nopriv includes/` returned nothing
 * before this file existed, which is worth knowing when reading it: there is no house
 * convention for this, and the order the checks run in *is* the design.
 *
 * **The order, and why it is that order.** Honeypot, dwell token, per-actor ceiling, consent,
 * the thirteen fields, requiredness once, content scoring and the site-wide degrade, then
 * storage. The two cheapest tests come first because they cost nothing and answer for most of
 * what arrives; the ceilings come next because a refusal that never reads the form cannot be
 * made expensive; consent comes before the fields because a submission without it is not
 * stored at all, so cleaning twenty-two values first would be work done for a row that will
 * not exist. Everything after that is about *how* a row is filed, never whether: content
 * scoring holds a row for a human and never refuses it, because the cost of a wrongly refused
 * application is an institution that never applies again and nobody ever knowing.
 *
 * **Consent is a precondition, not an answer.** The key absent, `"0"`, `"yes"`, anything but
 * `"1"` or `"true"` refuses the whole submission and stores nothing. What is stored instead is
 * the evidence: the sentence as it was rendered, the policy's URL and post ID, and the
 * policy's own `post_modified_gmt`. "They agreed" is worthless if nobody can say what they
 * agreed to, and the policy page is editable. If the site has no privacy policy at all the
 * form refuses to render, because there is then nothing to agree to.
 *
 * **Why the anti-spam is here at all.** Not to protect consent: the base's own numbers say the
 * Airtable form never dropped a tick (design spec section 3). It is spam control on a page
 * anybody on the internet can post to, and every layer is a nuisance control rather than an
 * entitlement. The per-actor ceiling refuses at five an hour; the site-wide one *degrades* at
 * forty a day, accepting the submission and holding it, because a flood must never close the
 * form to the one real institution applying that afternoon.
 *
 * **Holding is not silence towards the applicant.** A held row is one a person should look at
 * before anybody is paged; it is not an application that is over. The managers are spared and
 * the applicant is still acknowledged, because that message carries the link that confirms the
 * address, and `WPCPM_Institution_Approval` refuses to approve a row whose address nobody has
 * claimed. Step 10 of `handle_submit()` names the bug that rule was written against.
 *
 * **Duplicates are flagged and never merged.** A stranger who submits first using an
 * institution's published address must not be able to edit or suppress the genuine
 * submission that follows. Both rows are kept, the second carries a signal, and a human
 * decides.
 *
 * The page is deliberately **not gated**: see `ensure_page()`.
 */
class WPCPM_Institution_Application {

	/** The post type one submission is stored as. Private, invisible, no admin UI. */
	const POST_TYPE = 'wpcpm_institution_app';

	const SHORTCODE = 'wpcpm_institution_application';
	const BLOCK     = 'wpcpm/institution-application';
	const OPT_PAGE  = 'wpcpm_application_page_id';
	const STYLE     = 'wpcpm-institution-application';

	/**
	 * The page's slug.
	 *
	 * Short because it is printed on slides, mailed and read aloud. Chosen once and never
	 * renamed, for the reason the dashboard pages give: a rename breaks every link anybody
	 * has been sent.
	 */
	const SLUG = 'apply';

	/** The `admin_post_` action the form posts to, registered for logged-out visitors too. */
	const ACTION_SUBMIT = 'wpcpm_apply';

	/** The `admin_post_` action the address-verification link in the acknowledgement lands on. */
	const ACTION_VERIFY = 'wpcpm_apply_verify';

	/** Seconds a human takes to fill this in at the very least. Below it, the row is spam. */
	const MIN_SECONDS = 6;

	/** Longest free-text answer kept, in characters. */
	const MAX_TEXT = 4000;

	/** How long a stashed failure or confirmation survives the redirect, in minutes. */
	const TRANSIENT_MINUTES = 30;

	/** Prefix for those stashes. The rest is a random id that travels in the redirect. */
	const TRANSIENT_PREFIX = 'wpcpm_app_';

	/** The redirect's argument when there is a stash: its id. Nothing about a submission is in the URL. */
	const QUERY_STASH = 'wpcpm_app';

	/**
	 * The redirect's argument when there is no stash: the outcome, as itself.
	 *
	 * `busy`, `closed` and the three answers the confirmation link gives carry no values, no
	 * problems and no reference, so a transient for one of them would be two rows in the
	 * options table written by a stranger, once per request, to say a sentence that is the
	 * same sentence for everybody. The slug says nothing about who sent what, which is what
	 * makes it safe in an address bar where the submission itself is not.
	 */
	const QUERY_OUTCOME = 'wpcpm_app_said';

	/** The argument the manager's queue opens one application with. */
	const QUERY_QUEUE = 'wpcpm_app_id';

	/** The posted array holding the thirteen answers, keyed by `form_key()`. */
	const FIELD_ANSWERS = 'wpcpm_application';

	/**
	 * The honeypot's field name.
	 *
	 * Deliberately not `website`, `url` or `company`: a password manager or a browser
	 * autofilling one of those for a human would file a genuine application as spam. Nothing
	 * fills a field called this, and the form has a real `Website` question of its own.
	 */
	const HONEYPOT = 'wpcpm_confirm_url';

	/** The dwell token's field name. */
	const TOKEN_FIELD = 'wpcpm_application_token';

	/** How long a dwell token stays usable. Past it the answer is `stale`, which is not spam. */
	const TOKEN_LIFETIME = 12 * HOUR_IN_SECONDS;

	/** Submissions one source may make in an hour before it is refused outright. */
	const PER_HOUR = 5;

	/**
	 * Submissions the whole site takes in a day before every further one is held instead.
	 *
	 * Held, and not refused, and not silent either: the managers are spared and the applicant
	 * is still acknowledged. The message that acknowledges them carries the link that confirms
	 * the address, and an unconfirmed application can never be approved.
	 */
	const PER_DAY = 40;

	/**
	 * Acknowledgements this form will send in a day, site-wide, to addresses nobody has proved.
	 *
	 * Held rows are acknowledged, which is what stops a real institution being stranded by a
	 * busy afternoon, and that is right. But it also means the site-wide degrade is no longer
	 * the cap on outbound mail that it used to be when only `new` rows were written to, and
	 * without one this form is a mailer: an address of the sender's choosing, carrying text of
	 * the sender's choosing, once per submission, from a page that needs no account.
	 *
	 * So the mail has a ceiling of its own. Past it the application is still stored, still
	 * queued, still visible to a manager with `mail-ceiling` against it; only the message is
	 * not sent, and the applicant is told plainly that it was not rather than being promised
	 * one that will never arrive.
	 *
	 * **Deliberately far above `PER_DAY`, and not equal to it.** The two ceilings do different
	 * jobs: the degrade at forty is what stops managers being paged, and held rows begin at
	 * forty-one, so a mail ceiling of forty would silence precisely the rows this send exists
	 * for and put back the defect it was written to fix. This one is a backstop against the
	 * path becoming a mailer, nothing else. A program that receives two hundred genuine
	 * applications in a day has a different problem and will hear about it from the queue.
	 */
	const MAIL_PER_DAY = 200;

	/** How many links across the free text before the row is held for a human. */
	const MAX_LINKS = 3;

	/** Shorter than this, the "why are you interested" answer is a signal rather than an answer. */
	const MIN_REASON = 30;

	const STATE_NEW      = 'new';
	const STATE_HELD     = 'held';
	const STATE_SPAM     = 'spam';
	const STATE_INFO     = 'info';
	const STATE_APPROVED = 'approved';
	const STATE_REJECTED = 'rejected';

	/** Post meta: the cleaned answers, keyed by Airtable column name. Immutable after insert. */
	const META_FIELDS = '_wpcpm_app_fields';

	/** Post meta: one of the `STATE_*` values. */
	const META_STATE = '_wpcpm_app_state';

	/** Post meta: the human-readable reference, `APP-2026-0007`. */
	const META_REFERENCE = '_wpcpm_app_reference';

	/** Post meta: the Countries record ID the applicant chose. Server-held, never in the fields. */
	const META_COUNTRY = '_wpcpm_app_country';

	/** Post meta: that country's name as it read when the form was sent. */
	const META_COUNTRY_NAME = '_wpcpm_app_country_name';

	/** Post meta: a snapshot of `WPCPM_Countries::routing()` at submission. */
	const META_MANAGER = '_wpcpm_app_manager';

	/** Post meta: the consent evidence. See the class docblock. */
	const META_CONSENT = '_wpcpm_app_consent';

	/** Post meta: why the row was held or held as spam, as a list of slugs. */
	const META_SIGNALS = '_wpcpm_app_signals';

	/**
	 * Post meta: `wp_hash()` of the lowercased contact address.
	 *
	 * For duplicate flagging and nothing else. The address itself is in the fields; this is
	 * a key that can be compared without a query reading anybody's address.
	 */
	const META_EMAIL = '_wpcpm_app_email';

	/** Post meta: when the applicant confirmed the address by following the link. */
	const META_VERIFIED = '_wpcpm_app_verified';

	/** Post meta: the Airtable record approval created or adopted. */
	const META_RECORD = '_wpcpm_app_record';

	/** Post meta: the first member account approval created. */
	const META_USER = '_wpcpm_app_user';

	/** Post meta, repeating: one row per event, `event`, `at`, `actor`, `note`. */
	const META_EVENT = '_wpcpm_app_event';

	/**
	 * Hooks.
	 *
	 * Both handlers are registered for logged-out visitors as well, which is the whole point
	 * of this module: an institution applying has no account and will not have one until a
	 * program manager approves the application.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_' . self::ACTION_VERIFY, array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_VERIFY, array( __CLASS__, 'handle_verify' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );
	}

	/**
	 * Register the post type, the shortcode, the block and the stylesheet.
	 */
	public static function register() {
		self::register_post_type();
		self::register_assets();

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );

		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/institution-application';

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
	 * Invisible everywhere, like the agreement type: these rows hold an institution's contact
	 * details and a stranger's free text, and the only route to one is the manager's queue,
	 * which asks the capability first.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Institution applications', 'wpcredits-program-manager' ),
					'singular_name' => __( 'Institution application', 'wpcredits-program-manager' ),
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
				'capability_type'     => array( 'wpcpm_institution_app', 'wpcpm_institution_apps' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register the form's stylesheet.
	 *
	 * Its own file and no dependency on the dashboard stylesheets: this page is read by
	 * strangers on a theme's own front end, and loading the mentor dashboard's shell to draw
	 * a form would be three pages' worth of rules for a set of fields.
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

		// The shared submit guard, so a second press of Send cannot make a second application
		// while the first is in flight. Nothing here is a control: with JavaScript off the form
		// posts exactly as it would have.
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
	 * **The page is left ungated on purpose, and this is the line that does it.** Every other
	 * page this plugin provisions calls a `gate_page()` helper here that stamps
	 * `WPCPM_Content_Access::META_KEY`; copying the neighbouring class and keeping that call
	 * is the obvious mistake, and it fails silently - an absent access level means public, so
	 * the only symptom of gating this one would be that nobody outside the program can reach
	 * the form nobody outside the program has any other way of finding. An application form
	 * only the people who are already in can see is not a form.
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

		// A site that has the page but not the option - restored from a backup, or migrated -
		// adopts it rather than creating a second one at `apply-2`.
		$existing = get_page_by_path( self::SLUG );

		if ( $existing instanceof WP_Post ) {
			update_option( self::OPT_PAGE, $existing->ID, false );

			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Apply to the WordPress Credits Program', 'wpcredits-program-manager' ),
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
	 * everybody: the nonce belongs to whoever the page was rendered for, and one token handed
	 * to a thousand visitors is single-use for the first of them and refused for the rest.
	 * Only this page, because a site's caching is not this plugin's to turn off.
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
	 * The thirteen questions, keyed by the exact Airtable column name.
	 *
	 * Keyed by column name because that is what the record is created from: an approval writes
	 * these keys straight through, so a name that drifts from the base is a 422 for the whole
	 * record rather than a field that quietly stops saving. Two of the names look like typos
	 * and are not - `Anything else you’d like us to know?` uses U+2019, and the first
	 * internship choice begins with a space - and both are pinned byte for byte in
	 * `bin/fixtures/institutions-table-fields.json` and asserted by
	 * `bin/test-institution-application.php`.
	 *
	 * `Country` and `Privacy Policy Compliance` are questions here because they are asked, and
	 * are never in the stored fields because the server holds them: the country as a record ID
	 * on its own meta key, consent as evidence on another. `Current Stage` is not asked at all.
	 *
	 * @return array<string, array> Column name => spec.
	 */
	public static function fields() {
		return array(
			'Name'                                 => array(
				'group'     => 'about',
				'label'     => __( 'Name of your institution', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 200,
				'auto'      => 'organization',
			),
			'Country'                              => array(
				'group'    => 'about',
				'label'    => __( 'Country', 'wpcredits-program-manager' ),
				'type'     => 'country',
				'required' => true,
			),
			'City'                                 => array(
				'group'     => 'about',
				'label'     => __( 'City', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 120,
				'auto'      => 'address-level2',
			),
			'Website'                              => array(
				'group'    => 'about',
				'label'    => __( 'Website', 'wpcredits-program-manager' ),
				// `type="text"` with `inputmode="url"`, never `type="url"`: the browser refuses
				// a scheme-less address, and `example.edu` is how most people write one.
				// `WPCPM_Field_Value::clean_url()` adds the scheme instead of arguing about it.
				'type'     => 'url',
				'required' => true,
				'help'     => __( 'For example: example.edu', 'wpcredits-program-manager' ),
			),
			'Contact Person'                       => array(
				'group'     => 'contact',
				'label'     => __( 'Name of the person we should contact', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 120,
				'auto'      => 'name',
			),
			'Contact Email'                        => array(
				'group'    => 'contact',
				'label'    => __( 'Their email address', 'wpcredits-program-manager' ),
				'type'     => 'email',
				'required' => true,
				'auto'     => 'email',
				'help'     => __( 'We send the acknowledgement here, and the account when the program says yes.', 'wpcredits-program-manager' ),
			),
			'Department'                           => array(
				'group'     => 'contact',
				'label'     => __( 'Department or faculty', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 150,
			),
			'How do your internships or practices typically work?' => array(
				'group'    => 'program',
				'label'    => __( 'How do your internships or practices typically work?', 'wpcredits-program-manager' ),
				'type'     => 'choices',
				'required' => true,
				'help'     => __( 'Tick everything that applies.', 'wpcredits-program-manager' ),
			),
			'Comments'                             => array(
				'group'    => 'program',
				'label'    => __( 'If you ticked "Other", please tell us how', 'wpcredits-program-manager' ),
				'type'     => 'textarea',
				// Required only when "Other" is ticked, which `problems()` decides. Rendered
				// always, because a field that appears when a box is ticked needs JavaScript to
				// appear at all, and this form works without any.
				'required' => false,
			),
			'Estimated number of students who may be interested' => array(
				'group'    => 'program',
				'label'    => __( 'Roughly how many students may be interested?', 'wpcredits-program-manager' ),
				'type'     => 'number',
				'required' => true,
				'min'      => 1,
				'max'      => 10000,
				'help'     => __( 'A rough number is fine. Nobody is held to it.', 'wpcredits-program-manager' ),
			),
			'Why are you interested in offering WordPress Credits to your students?' => array(
				'group'    => 'more',
				'label'    => __( 'Why are you interested in offering WordPress Credits to your students?', 'wpcredits-program-manager' ),
				'type'     => 'textarea',
				'required' => true,
			),
			'Anything else you’d like us to know?' => array(
				'group'    => 'more',
				'label'    => __( 'Anything else you’d like us to know?', 'wpcredits-program-manager' ),
				'type'     => 'textarea',
				'required' => false,
			),
			'Privacy Policy Compliance'            => array(
				'group'    => 'consent',
				'label'    => __( 'I confirm I have read the privacy policy and that this institution can share students\' details with the program under it.', 'wpcredits-program-manager' ),
				'type'     => 'consent',
				'required' => true,
			),
		);
	}

	/**
	 * The five groups the questions are asked in, in order.
	 *
	 * @return array<string, string> Group key => heading.
	 */
	public static function groups() {
		return array(
			'about'   => __( 'About your institution', 'wpcredits-program-manager' ),
			'contact' => __( 'Who we should contact', 'wpcredits-program-manager' ),
			'program' => __( 'How your program works', 'wpcredits-program-manager' ),
			'more'    => __( 'Tell us more', 'wpcredits-program-manager' ),
			'consent' => __( 'Before you send this', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The form key one column posts under.
	 *
	 * Hashed rather than used raw because one column name carries U+2019 and another is a
	 * whole sentence with a question mark in it, and a form field name is not the place for
	 * either. Twelve hex characters, which is enough that the thirteen cannot collide and
	 * short enough to read in a browser's inspector.
	 *
	 * @param string $column Airtable column name.
	 * @return string
	 */
	public static function form_key( $column ) {
		return 'f' . substr( md5( (string) $column ), 0, 12 );
	}

	/**
	 * The choices the internship question offers.
	 *
	 * Declared here and pinned in `bin/fixtures/institutions-table-fields.json`, the way
	 * `WPCPM_Institution_Agreement::STAGE_ORDER` is: `create_records()` sends no `typecast`,
	 * so a choice spelled any other way is a 422 for the whole record, and a test that reads
	 * the fixture by column name catches that before a release rather than at an applicant's
	 * expense. **The first choice begins with a space.** It is not a typo, it is what the
	 * column says, and trimming it would break every record this form creates.
	 *
	 * @return string[]
	 */
	public static function internship_choices() {
		return array(
			' Based on required hours (e.g. 150 hours)',
			'Fixed duration (e.g. semester-based)',
			'Flexible duration or year-round',
			'Other (please specify)',
		);
	}

	/**
	 * The choice that makes the free-text follow-up required.
	 *
	 * @return string
	 */
	public static function other_choice() {
		return 'Other (please specify)';
	}

	/**
	 * Clean one posted answer into the shape Airtable takes, or refuse it with a reason.
	 *
	 * Everything with an equivalent in `WPCPM_Field_Value` goes through it rather than
	 * growing a second set of rules here: that class exists because the report form and the
	 * feedback forms each grew their own copy and then disagreed in small ways. The three
	 * types it has no equivalent for are the ones this form invented - a country that is a
	 * record ID, a multi-select of pinned choices, and consent, which is a precondition
	 * rather than a value.
	 *
	 * @param string $column Airtable column name.
	 * @param mixed  $raw    Posted value.
	 * @return array{ok:bool,value:mixed,problem:string}
	 */
	public static function clean( $column, $raw ) {
		$fields = self::fields();
		$spec   = isset( $fields[ $column ] ) ? $fields[ $column ] : null;

		if ( null === $spec ) {
			// A key nobody asked about. Refused rather than stored: the fields meta is what an
			// approval writes to Airtable, and a column this form does not offer is either a
			// hand-made request or a name that has drifted.
			return array(
				'ok'      => false,
				'value'   => null,
				'problem' => 'unknown_field',
			);
		}

		$type = $spec['type'];

		if ( 'country' === $type ) {
			$id = is_scalar( $raw ) ? trim( (string) $raw ) : '';

			if ( '' === $id ) {
				return self::accept( '' );
			}

			// Validated against the map the select was drawn from, not against the shape of a
			// record ID: a well-formed ID for a record in another table would otherwise be
			// stored as this institution's country and fail at the create.
			$options = WPCPM_Countries::options();

			return isset( $options[ $id ] ) ? self::accept( $id ) : self::refuse( 'bad_choice' );
		}

		if ( 'choices' === $type ) {
			$known  = self::internship_choices();
			$picked = array();

			foreach ( (array) $raw as $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}

				$value = (string) $value;

				// Unknown values are dropped rather than refusing the answer, for the reason
				// the team field gives: the list ticked from is the plugin's own, and a stale
				// one is the plugin's fault and not the applicant's. Duplicates collapse.
				if ( in_array( $value, $known, true ) && ! in_array( $value, $picked, true ) ) {
					$picked[] = $value;
				}
			}

			return self::accept( $picked );
		}

		if ( 'consent' === $type ) {
			// The one answer that is never stored as an answer. `WPCPM_Field_Value` reads a tick
			// the strict way - the value the control carries, and nothing else - which is
			// exactly the rule consent needs: "yes" is not a tick.
			return WPCPM_Field_Value::clean( $raw, array( 'type' => 'checkbox' ) );
		}

		$rules = array( 'type' => 'text' === $type ? 'text' : $type );

		foreach ( array( 'maxlength', 'min', 'max' ) as $rule ) {
			if ( isset( $spec[ $rule ] ) ) {
				$rules[ $rule ] = $spec[ $rule ];
			}
		}

		if ( 'number' === $type ) {
			// Whole students. `step` of 1 is what makes `WPCPM_Field_Value` round to an integer,
			// and the column is `number, precision 0`.
			$rules['step'] = '1';
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
	 * Whether the program is taking applications on this site right now.
	 *
	 * A setting rather than the mere existence of the page, because "on" means accepting
	 * writes from anybody on the internet, and that is a decision somebody makes rather than
	 * a side effect of installing an update.
	 *
	 * @return bool
	 */
	public static function is_open() {
		return (bool) WPCPM_Settings::get_value( 'applications_enabled', false );
	}

	/**
	 * The site's privacy policy, or an empty string when it has none.
	 *
	 * @return string
	 */
	public static function policy_url() {
		return function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
	}

	/**
	 * Render the answer this request carries, the form, the confirmation, or nothing.
	 *
	 * Three things stop the *form* being drawn, and all three answer a manager with one
	 * sentence and the public with nothing: the form being switched off, the site having no
	 * privacy policy, and the countries map being empty. Nothing, rather than an apology,
	 * because a page that says "this form is broken" to a stranger who followed a link from a
	 * conference talk is worse than a page that says nothing, and because two of the three are
	 * states only somebody with the settings screen can do anything about.
	 *
	 * **None of the three stops a sentence, and that is a fix rather than a nicety.** While the
	 * refusals returned before `render_message()` ran, two things were true and both were
	 * wrong. `closed` could never be read by anybody: `handle_submit()` stashes it only when
	 * the form is switched off, which is exactly the state the first refusal returned in, so
	 * the one outcome about the form being off was invisible whenever it applied. And the
	 * confirmation link is a different handler with different rules - it keeps working after a
	 * manager switches the form off, because the row it stamps is what makes an application
	 * approvable - so somebody following it out of their mail that afternoon was shown an empty
	 * page instead of being told their address is confirmed.
	 *
	 * The wrapper follows the same rule: nothing to say and nothing to draw returns the empty
	 * string, not an empty `div`, because that is the same silence with markup in it.
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
		$countries  = WPCPM_Countries::options();
		$closed     = self::closed_reason( $countries );

		// The editor preview is static, and deliberately. A live preview would render a real
		// form in the editor, which means minting a nonce and a single-use dwell token on
		// every keystroke that reloads the block - tokens nobody will ever post, each one
		// claiming a row the sweep then has to clear. It answers before the stash is read for
		// a second reason: reading one consumes it, and an editor is not the applicant.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '' !== $closed ? self::sentence( $closed ) : self::preview( $countries );
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
			// One sentence, and only for somebody who can do something about it.
			if ( $can_manage ) {
				printf( '<p class="wpcpm-application__notice">%s</p>', esc_html( $closed ) );
			}
		} elseif ( ! in_array( $outcome, array( 'sent', 'sent-quiet', 'busy', 'verified', 'verified-already' ), true ) ) {
			// Everything except the outcomes where drawing the form again would invite the same
			// refusal or contradict what has just been said: a source that has used its five
			// submissions this hour, an address that has just been confirmed and has nothing
			// left to send, and the confirmation panel itself.
			self::render_form(
				isset( $stash['values'] ) && is_array( $stash['values'] ) ? $stash['values'] : array(),
				isset( $stash['problems'] ) && is_array( $stash['problems'] ) ? $stash['problems'] : array(),
				$countries
			);
		}

		$body = (string) ob_get_clean();

		// Nothing to say and nothing to draw is nothing at all, wrapper included: an empty div
		// on a page a stranger followed a link to is the same silence with markup in it, and it
		// is what the three refusals answered with before any of them could carry a sentence.
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
	 * Lifted out of `render()` so that the answer a redirect carries can be drawn whether or
	 * not the form can be. Each of the three is a sentence for a program manager and silence
	 * for everybody else; `render()` decides who hears it.
	 *
	 * @param array $countries The countries map, already read.
	 * @return string
	 */
	private static function closed_reason( array $countries ) {
		if ( ! self::is_open() ) {
			return __( 'The application form is switched off. Turn on "Accept institution applications" in the plugin settings when the program is ready to take them.', 'wpcredits-program-manager' );
		}

		// The privacy gate. On the live site the policy page is still a draft, so this branch
		// is the one that runs, and it must not print a form asking somebody to agree to a
		// document that does not exist.
		if ( '' === self::policy_url() ) {
			return __( 'This form is not shown yet: applicants are asked to agree to the privacy policy, and this site has no published privacy policy page. Set one under Settings, Privacy.', 'wpcredits-program-manager' );
		}

		if ( empty( $countries ) ) {
			return __( 'This form is not shown yet: the countries list is empty, and every application has to name a country. Press Refresh countries on the Institutions screen.', 'wpcredits-program-manager' );
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
	 * @param array $countries The countries map, already read.
	 * @return string
	 */
	private static function preview( array $countries ) {
		$out = '<div class="wpcpm-application wpcpm-application--preview">';

		$out .= '<p class="wpcpm-application__notice">' . esc_html__( 'The public application form is drawn here on the front end. This is a summary, not the form: the live form carries a single-use token that would be spent by the editor.', 'wpcredits-program-manager' ) . '</p>';

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

		$out .= '<p>' . esc_html(
			sprintf(
				/* translators: %s: how many countries the select offers. */
				_n( 'The country list offers %s country.', 'The country list offers %s countries.', count( $countries ), 'wpcredits-program-manager' ),
				number_format_i18n( count( $countries ) )
			)
		) . '</p>';

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
	 * @param array $stash    What the handler left behind.
	 * @param bool  $acknowledged Whether the acknowledgement was actually sent. False when the
	 *                            day's mail ceiling was reached, in which case the application
	 *                            is stored and queued exactly the same and only the message is
	 *                            missing: promising one that will not arrive is the one thing
	 *                            this page must not do.
	 */
	private static function render_confirmation( array $stash, $acknowledged = true ) {
		$reference = isset( $stash['reference'] ) ? (string) $stash['reference'] : '';
		$email     = isset( $stash['email'] ) ? (string) $stash['email'] : '';

		echo '<div class="wpcpm-application__done">';
		echo '<h3>' . esc_html__( 'Thank you. Your application is with us.', 'wpcredits-program-manager' ) . '</h3>';

		if ( '' !== $reference ) {
			printf(
				'<p class="wpcpm-application__reference">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: the application reference, e.g. APP-2026-0007. */
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
							__( 'We have sent an acknowledgement to %s with a link that confirms the address. A program manager reads every application by hand, so a reply takes a few working days.', 'wpcredits-program-manager' ),
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
	 * One map so that every slug the handlers stash has a sentence, which
	 * `bin/test-institution-application.php` asserts: an outcome with no wording would land a
	 * stranger on a page that had visibly done something and said nothing about it.
	 *
	 * @return array<string, array> Slug => `array( level, sentence )`.
	 */
	public static function outcomes() {
		return array(
			'again'            => array( 'error', __( 'Nothing has been sent yet. Please look at the answers marked below and send the form again.', 'wpcredits-program-manager' ) ),
			'consent'          => array( 'error', __( 'Nothing has been sent yet, and nothing has been stored. The program can only accept an application from an institution that confirms the privacy policy, so please tick the last box.', 'wpcredits-program-manager' ) ),
			'stale'            => array( 'error', __( 'This form had been open for a long time, so it was not sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'expired'          => array( 'error', __( 'This form had been open for a while and could not be sent. Your answers are still here: please send it again.', 'wpcredits-program-manager' ) ),
			'busy'             => array( 'error', __( 'Several applications have already been sent from here in the last hour, so this one was not. Please try again later.', 'wpcredits-program-manager' ) ),
			'lost'             => array( 'error', __( 'Something went wrong at our end and the application was not saved. Nothing has been sent. Please try once more.', 'wpcredits-program-manager' ) ),
			'closed'           => array( 'info', __( 'The program is not taking applications through this form at the moment.', 'wpcredits-program-manager' ) ),
			'verified'         => array( 'success', __( 'Thank you. Your address is confirmed, and your application is with a program manager.', 'wpcredits-program-manager' ) ),
			'verified-already' => array( 'info', __( 'This address was already confirmed, so there is nothing more to do. Your application is with a program manager.', 'wpcredits-program-manager' ) ),
			'verify-failed'    => array( 'error', __( 'That confirmation link could not be read. It may have been broken across two lines by a mail program: please try the whole link again.', 'wpcredits-program-manager' ) ),
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
	 * @param array $values    Cleaned values from a failed attempt, keyed by column name.
	 * @param array $problems  Problem slugs from that attempt, keyed by column name.
	 * @param array $countries Record ID => country name.
	 */
	private static function render_form( array $values, array $problems, array $countries ) {
		printf(
			'<form class="wpcpm-application__form" method="post" action="%1$s" data-wpcpm-once data-wpcpm-busy="%2$s" data-wpcpm-status="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Sending...', 'wpcredits-program-manager' ),
			esc_attr__( 'Sending your application.', 'wpcredits-program-manager' )
		);

		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SUBMIT ) );

		// The nonce is written out rather than printed by `wp_nonce_field()` because the dwell
		// token is signed with it, and both have to be the same string. They are: a nonce is
		// derived from the action, the user and the tick, so `token()` asking for one of its
		// own gets this one back.
		printf( '<input type="hidden" name="_wpnonce" value="%s" />', esc_attr( wp_create_nonce( self::ACTION_SUBMIT ) ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::TOKEN_FIELD ), esc_attr( self::token() ) );

		self::render_honeypot();

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
					isset( $problems[ $column ] ) ? (string) $problems[ $column ] : '',
					$countries
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
	 * The honeypot.
	 *
	 * Hidden with a class rather than `type="hidden"`, because a hidden input is not something
	 * a form-filling script mistakes for a question, and `display: none` in the stylesheet is
	 * what it does mistake for one. `aria-hidden` and `tabindex="-1"` keep it away from anybody
	 * reading the form with a screen reader or a keyboard, and the label says what to do with
	 * it for the one person whose stylesheet never loaded.
	 */
	private static function render_honeypot() {
		printf(
			'<div class="wpcpm-application__confirm" aria-hidden="true"><label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%1$s" value="" tabindex="-1" autocomplete="off" /></div>',
			esc_attr( self::HONEYPOT ),
			esc_html__( 'Leave this field empty.', 'wpcredits-program-manager' )
		);
	}

	/**
	 * One question.
	 *
	 * @param string $column    Airtable column name.
	 * @param array  $spec      Its spec.
	 * @param mixed  $value     Its value from a failed attempt, or null.
	 * @param string $problem   The problem slug from that attempt, or ''.
	 * @param array  $countries Record ID => country name.
	 */
	private static function render_field( $column, array $spec, $value, $problem, array $countries ) {
		$key      = self::form_key( $column );
		$id       = 'wpcpm-application-' . $key;
		$name     = self::FIELD_ANSWERS . '[' . $key . ']';
		$type     = $spec['type'];
		$required = ! empty( $spec['required'] ) ? ' required="required"' : '';
		$invalid  = '' !== $problem ? ' aria-invalid="true"' : '';
		$says     = array();

		if ( ! empty( $spec['help'] ) ) {
			$says[] = $id . '-help';
		}

		if ( '' !== $problem ) {
			$says[] = $id . '-problem';
		}

		$describedby = empty( $says ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $says ) ) . '"';

		// A checkbox list and the consent box read as controls with a label after them, so they
		// carry their own wrappers; a `<p>` cannot hold a `<fieldset>` at all - the parser
		// closes the paragraph the moment one opens - which is what scattered a field set
		// across a row the last time this was got wrong.
		$wrapper = 'choices' === $type ? 'div' : 'p';

		printf(
			'<%1$s class="wpcpm-field wpcpm-field--%2$s%3$s">',
			esc_attr( $wrapper ),
			esc_attr( $type ),
			'' !== $problem ? ' has-problem' : ''
		);

		if ( 'consent' === $type ) {
			printf(
				'<span class="wpcpm-field__consent"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s /><label for="%1$s">%5$s</label></span>',
				esc_attr( $id ),
				esc_attr( $name ),
				$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
				$describedby, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
				esc_html( $spec['label'] )
			);

			self::render_policy_line();
		} elseif ( 'choices' === $type ) {
			printf( '<span class="wpcpm-field__label">%s</span>', esc_html( $spec['label'] ) );

			$picked = is_array( $value ) ? $value : array();

			foreach ( self::internship_choices() as $choice ) {
				printf(
					'<label class="wpcpm-field__check"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> <span>%4$s</span></label>',
					esc_attr( $name ),
					esc_attr( $choice ),
					checked( in_array( $choice, $picked, true ), true, false ),
					// Trimmed for display only. The value above keeps the leading space the
					// column has, because that is the string Airtable matches.
					esc_html( trim( $choice ) )
				);
			}
		} else {
			printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $spec['label'] ) );

			if ( 'country' === $type ) {
				printf(
					'<select id="%1$s" name="%2$s"%3$s%4$s%5$s>',
					esc_attr( $id ),
					esc_attr( $name ),
					$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$invalid, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A literal above.
					$describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.
				);

				printf( '<option value="">%s</option>', esc_html__( 'Choose a country', 'wpcredits-program-manager' ) );

				foreach ( $countries as $record_id => $country ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $record_id ),
						selected( (string) $value, (string) $record_id, false ),
						esc_html( $country )
					);
				}

				echo '</select>';
			} elseif ( 'textarea' === $type ) {
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
			} elseif ( 'number' === $type ) {
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$d" max="%5$d" step="1" inputmode="numeric"%6$s%7$s%8$s />',
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
				// Everything else is a text box, the URL included: `type="url"` refuses the
				// scheme-less addresses this base is full of, and the browser's refusal is a
				// message an applicant cannot act on.
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
				esc_html( self::problem_text( $problem, $spec ) )
			);
		}

		printf( '</%s>', esc_attr( $wrapper ) );
	}

	/**
	 * The line under the consent box, linking the policy the applicant is agreeing to.
	 *
	 * Rendered as a link to the policy and not as its text: the wording stored on the
	 * application is this sentence plus the policy's own modified date, and a policy quoted in
	 * full here would be a second copy to keep true.
	 */
	private static function render_policy_line() {
		$policy = self::policy_url();

		if ( '' === $policy ) {
			return;
		}

		printf(
			'<span class="wpcpm-field__hint">%1$s <a href="%2$s" rel="noopener">%3$s</a></span>',
			esc_html__( 'The policy is here:', 'wpcredits-program-manager' ),
			esc_url( $policy ),
			esc_html__( 'privacy policy', 'wpcredits-program-manager' )
		);
	}

	/**
	 * What to say about one refused answer.
	 *
	 * @param string $problem Problem slug.
	 * @param array  $spec    The field's spec, for the numbers a range refusal names.
	 * @return string
	 */
	private static function problem_text( $problem, array $spec ) {
		if ( 'required' === $problem ) {
			return __( 'Please fill this in.', 'wpcredits-program-manager' );
		}

		if ( 'bad_email' === $problem ) {
			return __( 'That does not look like an email address.', 'wpcredits-program-manager' );
		}

		if ( 'not_a_number' === $problem ) {
			return __( 'Please give a number.', 'wpcredits-program-manager' );
		}

		if ( 'below_min' === $problem || 'above_max' === $problem ) {
			return sprintf(
				/* translators: 1: smallest number accepted, 2: largest number accepted. */
				__( 'Please give a number between %1$s and %2$s.', 'wpcredits-program-manager' ),
				number_format_i18n( isset( $spec['min'] ) ? (int) $spec['min'] : 0 ),
				number_format_i18n( isset( $spec['max'] ) ? (int) $spec['max'] : 0 )
			);
		}

		if ( 'bad_choice' === $problem ) {
			return __( 'Please choose one of the options offered.', 'wpcredits-program-manager' );
		}

		return __( 'That answer could not be read. Please try it again.', 'wpcredits-program-manager' );
	}

	/*
	 * Anti-spam
	 * --------------------------------------------------------------------
	 */

	/**
	 * Mint a dwell token for the form being rendered.
	 *
	 * Signed with `wp_hash()` so it cannot be forged, bound to the form's nonce so a token
	 * harvested from one page cannot be posted with another page's nonce, and carrying the
	 * time it was minted so the two ends of the window can be judged. It is not secret and
	 * does not need to be: it says when this form was drawn, and nothing else.
	 *
	 * @return string
	 */
	public static function token() {
		$issued = time();

		return $issued . '.' . self::sign_token( $issued, wp_create_nonce( self::ACTION_SUBMIT ) );
	}

	/**
	 * The signature half of a token.
	 *
	 * @param int    $issued When the token was minted.
	 * @param string $nonce  The form's nonce, which the token is bound to.
	 * @return string
	 */
	private static function sign_token( $issued, $nonce ) {
		return substr( wp_hash( 'wpcpm-application-dwell|' . (int) $issued . '|' . $nonce ), 0, 32 );
	}

	/**
	 * Judge a posted dwell token.
	 *
	 * Three answers, and the difference between them matters. `spam` is a form that was posted
	 * faster than anybody can read it, a token that was never minted here, or one that has
	 * already been used: those rows are stored and held as spam, and the sender is told
	 * nothing, because a bot that learns which of its attempts was recognised writes a better
	 * one. `stale` is a token older than half a day, which is an ordinary human with a tab
	 * left open overnight and is not spam: they are asked to send it again, with everything
	 * they typed still in the boxes. `ok` is everything else.
	 *
	 * Single use through `WPCPM_Ceiling::claim()` with a limit of one, which is the only
	 * ceiling in the plugin that has to be exact: `add_option()` is one INSERT that reports
	 * failure when the row exists, so two uses of a harvested token cannot both be the first
	 * however close together they arrive. The window is the token's own lifetime, so a claim
	 * outlives the token it stands for; a token minted just before a bucket boundary can be
	 * replayed once into the next bucket, which is the price of a bucketed counter and is
	 * still one replay rather than a thousand.
	 *
	 * @param string $token The posted token.
	 * @return string `ok`, `spam` or `stale`.
	 */
	public static function check_token( $token ) {
		$token = trim( (string) $token );
		$parts = explode( '.', $token );

		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return 'spam';
		}

		// The nonce this token was signed against. Read here rather than passed in so that a
		// caller cannot check a token against a nonce other than the one that was posted with
		// it; `handle_submit()` has already verified it by the time this runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This *is* the nonce, read to re-derive the signature; it is verified in the handler before this is called.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! hash_equals( self::sign_token( (int) $parts[0], $nonce ), $parts[1] ) ) {
			return 'spam';
		}

		$age = time() - (int) $parts[0];

		if ( $age < self::MIN_SECONDS ) {
			return 'spam';
		}

		if ( $age > self::TOKEN_LIFETIME ) {
			return 'stale';
		}

		// Hashed rather than used raw, the way `WPCPM_Ceiling` asks: nothing about a claim is
		// readable in the options table.
		if ( ! WPCPM_Ceiling::claim( 'dwell:' . wp_hash( $token ), 1, self::TOKEN_LIFETIME ) ) {
			return 'spam';
		}

		return 'ok';
	}

	/**
	 * The address this request came from.
	 *
	 * A forwarded header is believed only when the connecting address is the one named in the
	 * `application_trusted_proxy` setting, because anybody can send that header: trusting it
	 * unconditionally would mean the per-actor ceiling is one line of a request away from
	 * being lifted, and the truncated address stored as consent evidence would be whatever
	 * the sender fancied. Empty setting means the connecting address is the client, which is
	 * true on this host.
	 *
	 * @return string An IP address, or '' when there is none to be had.
	 */
	public static function client_ip() {
		$remote = '';

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
		$proxy  = trim( (string) WPCPM_Settings::get_value( 'application_trusted_proxy', '' ) );

		if ( '' === $proxy || '' === $remote || $proxy !== $remote ) {
			return $remote;
		}

		$forwarded = '';

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		}

		foreach ( explode( ',', $forwarded ) as $candidate ) {
			$candidate = trim( $candidate );

			// The leftmost entry: the address the trusted edge saw the request arrive from.
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return $remote;
	}

	/**
	 * The ceiling key for one source.
	 *
	 * Hashed, so the options table holds no addresses. A request with no address at all shares
	 * one key with every other, which is the safe direction: they are counted together rather
	 * than being uncounted.
	 *
	 * @return string
	 */
	private static function actor_key() {
		// The whole address, hashed, and deliberately not the truncated one. Truncating would
		// bound the rows this unauthenticated path can create, which is worth something, but it
		// buckets a whole network together: one campus behind a single NAT is exactly the
		// population this form is for, and five an hour shared between all of its staff would
		// refuse the second real applicant of the afternoon to slow down an attacker who can
		// change address anyway. The rows are one per source per hour and the daily sweep is
		// what removes them; that is the bound this design accepts.
		return 'apply:' . wp_hash( self::client_ip() );
	}

	/**
	 * An address as it is kept on the consent record: recognisable, not identifying.
	 *
	 * The last octet of an IPv4 address goes, and everything after the first four groups of an
	 * IPv6 one. Enough to say two applications came from the same building; not enough to be a
	 * record of where somebody was.
	 *
	 * @param string $ip The address.
	 * @return string
	 */
	private static function truncate_ip( $ip ) {
		$ip = trim( (string) $ip );

		if ( '' === $ip ) {
			return '';
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';

			return implode( '.', $parts );
		}

		$groups = explode( ':', $ip );

		return implode( ':', array_slice( $groups, 0, 4 ) ) . '::';
	}

	/*
	 * The submit path
	 * --------------------------------------------------------------------
	 */

	/**
	 * Take one submission.
	 *
	 * `wp_verify_nonce()` and never `check_admin_referer()`. The difference is what happens to
	 * somebody who left the form open overnight: `check_admin_referer()` dies with a 403 screen
	 * and three paragraphs of their writing gone, and they do not apply again. Here an expired
	 * nonce is one more failure like any other - the answers go into the stash and the form
	 * comes back with them in it, and it costs one of that address's five submissions an hour
	 * rather than nothing at all, which is what the per-actor ceiling standing above it means.
	 *
	 * The order of what follows is the design, is numbered in the steps below, is set out in
	 * the class docblock and is asserted as an order by `bin/test-institution-application.php`.
	 */
	public static function handle_submit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This *is* the nonce, read to verify it on the next lines.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		$posted = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below; the values are cleaned one by one by `clean()` before any of them is used.
		if ( isset( $_POST[ self::FIELD_ANSWERS ] ) && is_array( $_POST[ self::FIELD_ANSWERS ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; every value is validated by type in `clean()`.
			$posted = wp_unslash( $_POST[ self::FIELD_ANSWERS ] );
		}

		// 1. The per-actor ceiling, and it is first for a reason that has nothing to do with
		// spam. A refusal, not a hold: five in an hour from one address is nobody's genuine
		// application, and this is the one layer that stores nothing at all. It runs before the
		// nonce because it is the only thing on this path that counts a writer with no account,
		// and the refusals below it hand the sender's writing back through a transient, which
		// is two rows in the options table. With the nonce checked first, a POST with a wrong
		// nonce - which anybody can send, in a loop, for ever - wrote those two rows every time
		// and was never counted by anything. Nothing above this line writes; the row this line
		// writes is one per address per window and the daily sweep takes it away.
		if ( ! WPCPM_Ceiling::claim( self::actor_key(), self::PER_HOUR, HOUR_IN_SECONDS ) ) {
			self::bounce( 'busy' );
		}

		// 2. The nonce.
		if ( ! wp_verify_nonce( $nonce, self::ACTION_SUBMIT ) ) {
			self::bounce( 'expired', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 3. Both of these are the settings screen's business rather than the applicant's, and
		// neither can be reached from a form this site drew: the form is not rendered at all
		// when either is true. A post arriving anyway is somebody replaying an old page.
		if ( ! self::is_open() || '' === self::policy_url() ) {
			self::bounce( 'closed' );
		}

		$signals = array();
		$spam    = false;

		// 4. The honeypot. A filled one is stored and held as spam rather than refused, and the
		// sender sees the ordinary confirmation: a bot told which attempt was recognised comes
		// back with a better one, and a human whose browser filled it in has still applied.
		if ( '' !== WPCPM_Request::posted_text( self::HONEYPOT ) ) {
			$spam      = true;
			$signals[] = 'honeypot';
		}

		// 5. The dwell token.
		$dwell = self::check_token( WPCPM_Request::posted_text( self::TOKEN_FIELD ) );

		if ( 'stale' === $dwell ) {
			self::bounce( 'stale', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		if ( 'ok' !== $dwell ) {
			$spam      = true;
			$signals[] = 'dwell';
		}

		// 6. Consent, which is a precondition and not an answer. Nothing is stored without it,
		// not even as spam.
		$consent_key = self::form_key( 'Privacy Policy Compliance' );
		$agreed      = self::clean( 'Privacy Policy Compliance', isset( $posted[ $consent_key ] ) ? $posted[ $consent_key ] : '' );

		if ( empty( $agreed['ok'] ) || true !== $agreed['value'] ) {
			// The values are cleaned here, on the way out, rather than before this decision:
			// cleaning is pure and stores nothing, and this is the one refusal that has to hand
			// back what was typed without having taken the fields step yet.
			self::bounce( 'consent', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 7. The thirteen fields, walked from `fields()` and never from `$_POST`: a key nobody
		// asked about cannot become a cell that way.
		$cleaned  = self::clean_all( $posted );
		$values   = $cleaned['values'];
		$problems = $cleaned['problems'];

		// 8. Requiredness, once, after cleaning. A row already headed for the spam pile skips
		// it: telling a bot which of thirteen fields it missed is free tuition.
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

		// 9. Content scoring. Holds, never refuses: a wrongly refused application is an
		// institution that never applies again and nobody ever knowing.
		$signals = array_merge( $signals, self::score( $values ) );

		// 10. The site-wide ceiling degrades rather than refusing, so a flood cannot close the
		// form to the one real institution applying that afternoon. The row is kept and held,
		// and the managers are not paged about it. The applicant still is: see step 13.
		if ( ! WPCPM_Ceiling::claim( 'apply-site', self::PER_DAY, DAY_IN_SECONDS ) ) {
			$signals[] = 'site-ceiling';
		}

		// 11. Duplicates are flagged and never merged: a stranger submitting first with an
		// institution's published address must not be able to edit or suppress the genuine
		// submission that follows. Both rows stand and a human decides.
		if ( self::has_open_duplicate( self::email_of( $values ), self::name_of( $values ) ) ) {
			$signals[] = 'duplicate';
		}

		// 12. Storage. Everything above decided how the row is filed; this is where it lands.
		$post_id = self::store( $values, self::state_for( $spam, $signals ), $signals );

		if ( ! $post_id ) {
			self::bounce(
				'lost',
				array(
					'values' => $values,
				)
			);
		}

		// 13. The two mails, gated differently on purpose, which is the correction of a bug
		// worth naming.
		//
		// Both used to be gated on `new` together, and the result was an application no shipped
		// path could ever approve. `WPCPM_Institution_Approval::approve()` accepts `new`, `held`
		// and `info`, and then refuses outright unless `_wpcpm_app_verified` is stamped. The
		// only thing that stamps it is the link in the applicant's own message. So a row the
		// content scoring held, or one the site-wide degrade held on a busy afternoon, never got
		// the link, could never be confirmed, and was refused for ever by the one button meant
		// to rescue it. Holding is how this form says "a person should read this before anybody
		// is paged"; it was never meant to say "this application is over".
		//
		// So the applicant is written to for any row that is not spam. That message is also the
		// only thing telling them the submission arrived at all, and stranding a real institution
		// is a far worse failure than acknowledging a row a manager will later reject.
		//
		// A spam row is told nothing and sends nothing: the address on it is forged or a probe,
		// and either way a reply is wrong (design spec 7.2, the Reject as spam row).
		//
		// The managers keep the strict gate, because sparing them is what holding was for. A
		// held row is on the queue with its signals against it, which is where they will read
		// it, and a flood must not become forty messages an hour to five people.
		$state = (string) get_post_meta( $post_id, self::META_STATE, true );

		// Only ever true for a row this form would have written to and could not. A spam row is
		// silent by design and must still be told exactly what everybody else is told, or the
		// confirmation page becomes a spam oracle: the sender learns which of their attempts
		// the site believed.
		$suppressed = false;

		if ( self::STATE_NEW === $state || self::STATE_HELD === $state ) {
			// The ceiling before the send, not after: see `MAIL_PER_DAY`. A claim that fails
			// leaves the row exactly as it is and records why, so the queue shows a manager
			// that this applicant is waiting on a message the site declined to send.
			if ( WPCPM_Ceiling::claim( 'apply-mail', self::MAIL_PER_DAY, DAY_IN_SECONDS ) ) {
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
	 * The signals are written once at storage; this is for the few that can only be known
	 * afterwards, when the row exists and something about handling it did not go to plan.
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
	 * Clean every one of the thirteen answers.
	 *
	 * Pure: it reads, it decides, it writes nothing. That is what lets the refusal paths call
	 * it on their way out to fill the form back in.
	 *
	 * **Consent never comes back.** It is dropped from the values here, in the one place, so
	 * that no failure path can repopulate a tick: an applicant who is shown a form with the
	 * consent box already ticked has not agreed to anything, and the evidence stored against
	 * the application would say they had.
	 *
	 * @param array $posted The posted answers, keyed by form key.
	 * @return array{values:array,problems:array} Both keyed by Airtable column name.
	 */
	private static function clean_all( array $posted ) {
		$values   = array();
		$problems = array();

		foreach ( self::fields() as $column => $spec ) {
			$key = self::form_key( $column );
			$raw = isset( $posted[ $key ] ) ? $posted[ $key ] : ( 'choices' === $spec['type'] ? array() : '' );

			$clean = self::clean( $column, $raw );

			if ( empty( $clean['ok'] ) ) {
				$problems[ $column ] = $clean['problem'];

				continue;
			}

			$values[ $column ] = $clean['value'];
		}

		unset( $values['Privacy Policy Compliance'] );

		return array(
			'values'   => $values,
			'problems' => $problems,
		);
	}

	/**
	 * Add the requiredness problems, once, after everything has been cleaned.
	 *
	 * Once, because a rule applied in two places is a rule that will disagree with itself: the
	 * browser's own `required` attribute is a courtesy and this is the check.
	 *
	 * @param array $values   Cleaned values.
	 * @param array $problems Problems so far.
	 * @return array
	 */
	private static function add_required( array $values, array $problems ) {
		$internships = 'How do your internships or practices typically work?';

		foreach ( self::fields() as $column => $spec ) {
			// Consent is decided before this runs and is never in the values; a field that was
			// refused for a reason of its own keeps that reason rather than being told it is
			// missing when it is not.
			if ( 'consent' === $spec['type'] || isset( $problems[ $column ] ) ) {
				continue;
			}

			$required = ! empty( $spec['required'] );

			// The one conditional question: "please tell us how" is required exactly when
			// "Other" is ticked, and is rendered whether or not it is, because a field that
			// appears when a box is ticked needs JavaScript to appear at all.
			if ( 'Comments' === $column ) {
				$picked   = isset( $values[ $internships ] ) ? (array) $values[ $internships ] : array();
				$required = in_array( self::other_choice(), $picked, true );
			}

			if ( ! $required ) {
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
	 * Every one of these holds a row for a human to look at. None of them refuses it, and the
	 * sender is never told which one fired: the signals are for the queue, not for the person
	 * who tripped them.
	 *
	 * @param array $values Cleaned values.
	 * @return string[] Signal slugs.
	 */
	private static function score( array $values ) {
		$signals = array();
		$name    = self::name_of( $values );
		$email   = self::email_of( $values );
		$prose   = self::prose( $values );

		if ( function_exists( 'wp_check_comment_disallowed_list' ) && wp_check_comment_disallowed_list( $name, $email, '', $prose, self::client_ip(), self::user_agent() ) ) {
			$signals[] = 'disallowed';
		}

		if ( preg_match_all( '#https?://#i', $prose ) >= self::MAX_LINKS ) {
			$signals[] = 'links';
		}

		$answers = array();

		foreach ( array( 'Why are you interested in offering WordPress Credits to your students?', 'Anything else you’d like us to know?', 'Comments' ) as $column ) {
			$answer = isset( $values[ $column ] ) ? trim( strtolower( (string) $values[ $column ] ) ) : '';

			if ( '' !== $answer ) {
				$answers[] = $answer;
			}
		}

		// The same paragraph pasted into two boxes is what a form filler does and what a person
		// writing about their own students does not.
		if ( count( $answers ) !== count( array_unique( $answers ) ) ) {
			$signals[] = 'identical';
		}

		$reason = isset( $values['Why are you interested in offering WordPress Credits to your students?'] ) ? trim( (string) $values['Why are you interested in offering WordPress Credits to your students?'] ) : '';

		if ( '' !== $reason && mb_strlen( $reason ) < self::MIN_REASON ) {
			$signals[] = 'short';
		}

		if ( '' !== $email && ! self::domain_takes_mail( $email ) ) {
			$signals[] = 'no-mx';
		}

		// An institution called the same thing as the person who contacts us is usually a form
		// filled in by a script that had one string to give.
		if ( '' !== $name && strtolower( $name ) === strtolower( trim( (string) ( isset( $values['Contact Person'] ) ? $values['Contact Person'] : '' ) ) ) ) {
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
	 * Whether anything at that domain could receive mail.
	 *
	 * An MX record, or an A record, which is what a mail server falls back to. Guarded because
	 * `checkdnsrr()` is not present everywhere and a host without it must not hold every
	 * application on the site.
	 *
	 * @param string $email The address.
	 * @return bool
	 */
	private static function domain_takes_mail( $email ) {
		$at = strrpos( $email, '@' );

		if ( false === $at ) {
			return true;
		}

		$domain = substr( $email, $at + 1 );

		/**
		 * Short-circuit the deliverability lookup for one domain.
		 *
		 * This is the only check on the submit path that leaves the server, and a host with no
		 * resolver, or one behind a network that answers slowly, would hold every application
		 * on the site for a reason that has nothing to do with the applicant. Answer with a
		 * boolean to decide it without a lookup.
		 *
		 * @param bool|null $takes_mail True or false to decide, null to look it up.
		 * @param string    $domain     The domain half of the address.
		 */
		$takes_mail = apply_filters( 'wpcpm_application_domain_takes_mail', null, $domain );

		if ( is_bool( $takes_mail ) ) {
			return $takes_mail;
		}

		if ( ! function_exists( 'checkdnsrr' ) ) {
			return true;
		}

		// An A record counts: a mail server falls back to it when there is no MX, and a
		// university that runs its own mail on the bare domain is not a signal.
		return checkdnsrr( $domain, 'MX' ) || checkdnsrr( $domain, 'A' );
	}

	/**
	 * The submitting browser's description, truncated, for the consent record.
	 *
	 * @return string
	 */
	private static function user_agent() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 200 );
	}

	/**
	 * The institution's name from a set of cleaned values.
	 *
	 * @param array $values Cleaned values.
	 * @return string
	 */
	private static function name_of( array $values ) {
		return isset( $values['Name'] ) ? trim( (string) $values['Name'] ) : '';
	}

	/**
	 * The contact address from a set of cleaned values.
	 *
	 * @param array $values Cleaned values.
	 * @return string
	 */
	private static function email_of( array $values ) {
		return isset( $values['Contact Email'] ) ? trim( (string) $values['Contact Email'] ) : '';
	}

	/**
	 * Which state a submission is filed under.
	 *
	 * The duplicate flag is deliberately not a hold. It says two rows may be about one
	 * institution, which is a thing for the queue to show and a manager to judge, and holding
	 * the second would mean an institution whose address a stranger used first is silently
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

		return empty( array_diff( $signals, array( 'duplicate' ) ) ) ? self::STATE_NEW : self::STATE_HELD;
	}

	/*
	 * Storage
	 * --------------------------------------------------------------------
	 */

	/**
	 * Store one submission and everything known about it.
	 *
	 * **Three columns never reach the stored fields.** `Country` is held as a record ID on its
	 * own key, `Privacy Policy Compliance` is held as evidence rather than as an answer, and
	 * `Current Stage` is the program's word about an institution and is set at approval. The
	 * fields meta is what an approval writes to Airtable unchanged, so anything in it is a cell
	 * the site is claiming an applicant typed.
	 *
	 * @param array  $values  Cleaned values, keyed by Airtable column name.
	 * @param string $state   One of the `STATE_*` values.
	 * @param array  $signals Why it is in that state.
	 * @return int The post ID, or 0.
	 */
	private static function store( array $values, $state, array $signals ) {
		$name    = self::name_of( $values );
		$email   = self::email_of( $values );
		$country = isset( $values['Country'] ) ? (string) $values['Country'] : '';
		$fields  = $values;

		foreach ( self::server_held() as $column ) {
			unset( $fields[ $column ] );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				// Zero for a logged-out submission, which is every real one. WordPress
				// substitutes the current user when there is one, and a program manager testing
				// the form from their own browser leaving their name on the row is a fact worth
				// having rather than one worth hiding.
				'post_author' => 0,
				'post_title'  => '' !== $name ? $name : __( 'Application with no name', 'wpcredits-program-manager' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;
		$routing = '' !== $country ? WPCPM_Countries::routing( $country ) : null;

		update_post_meta( $post_id, self::META_FIELDS, $fields );
		update_post_meta( $post_id, self::META_STATE, $state );
		update_post_meta( $post_id, self::META_REFERENCE, self::reference( $post_id ) );
		update_post_meta( $post_id, self::META_COUNTRY, $country );
		update_post_meta( $post_id, self::META_COUNTRY_NAME, '' !== $country ? WPCPM_Countries::name_of( $country ) : '' );
		// A snapshot, not a lookup: who the country routed to on the day it was sent is what a
		// queue row means by "for information", and the map is rebuilt by every sync.
		update_post_meta( $post_id, self::META_MANAGER, is_array( $routing ) ? $routing : array() );
		update_post_meta( $post_id, self::META_CONSENT, self::consent_record() );
		update_post_meta( $post_id, self::META_SIGNALS, array_values( array_unique( $signals ) ) );
		update_post_meta( $post_id, self::META_EMAIL, '' !== $email ? wp_hash( mb_strtolower( $email ) ) : '' );

		self::add_event( $post_id, 'submitted', 0, '' );

		return $post_id;
	}

	/**
	 * The three columns the server holds and the form never stores.
	 *
	 * @return string[]
	 */
	public static function server_held() {
		return array( 'Country', 'Current Stage', 'Privacy Policy Compliance' );
	}

	/**
	 * What was agreed to, and to which version of it.
	 *
	 * The sentence as it was rendered, the policy's address and post ID, and the policy's own
	 * `post_modified_gmt`. The last one is the point: "they agreed" is worth nothing if nobody
	 * can say what the document said that day, and a privacy policy is an ordinary page
	 * somebody can edit.
	 *
	 * @return array
	 */
	private static function consent_record() {
		$fields    = self::fields();
		$policy_id = (int) get_option( 'wp_page_for_privacy_policy' );
		$policy    = $policy_id ? get_post( $policy_id ) : null;

		return array(
			'sentence' => (string) $fields['Privacy Policy Compliance']['label'],
			'url'      => self::policy_url(),
			'policy'   => $policy_id,
			'modified' => $policy instanceof WP_Post ? (string) $policy->post_modified_gmt : '',
			'at'       => time(),
			'ip'       => self::truncate_ip( self::client_ip() ),
			'agent'    => self::user_agent(),
		);
	}

	/**
	 * Append one row to an application's event log.
	 *
	 * Repeating meta rather than one array, so two writes in the same second cannot lose each
	 * other's row the way a read-modify-write would.
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
	}

	/**
	 * Whether an open application already names this address or this institution.
	 *
	 * Open means new, held or waiting for information: a rejected or approved row is not
	 * something a new submission duplicates. Compared on the hashed address and on the
	 * trimmed, lowercased name, which is the comparison the live duplicate search on the queue
	 * makes against Airtable, so the two agree about what "the same institution" means.
	 *
	 * @param string $email The contact address.
	 * @param string $name  The institution's name.
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

			if ( '' !== $key && trim( mb_strtolower( (string) $application->post_title ) ) === $key ) {
				return true;
			}
		}

		return false;
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
	 * Oldest first because this is a queue: the row that has waited longest is the one that
	 * matters, and the age beside it is the whole point of the card it is drawn on.
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
	 * How many applications are waiting for somebody: the menu bubble's number.
	 *
	 * @param int $limit Most rows to count, or 0 for every one of them.
	 * @return int
	 */
	public static function pending_count( $limit = 0 ) {
		// Bounded on request, because the one caller that matters runs on every wp-admin page
		// load for every manager. Counting the whole table there means a flood of submissions
		// slows down every screen in the site, which is a worse outcome than a bubble that
		// stops counting: the caller asks for one more than it can display, which is all it
		// needs to know whether to print a number or "200+".
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

		// No states means no rows, and never every row: a caller that asked for nothing must not
		// be handed the whole table.
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
	 * needs neither: two applications a second apart must not be handed one reference, and the
	 * post ID is already unique and already stable. It is not a secret and is not a key to
	 * anything - the confirmation page is drawn from a one-shot stash, not from a reference in
	 * the address bar - so nothing rests on it being unguessable.
	 *
	 * @param int $post_id Application post ID.
	 * @return string
	 */
	public static function reference( $post_id ) {
		$post = get_post( (int) $post_id );
		$date = $post instanceof WP_Post ? (string) $post->post_date : '';
		$year = '' !== $date ? substr( $date, 0, 4 ) : wp_date( 'Y' );

		return sprintf( 'APP-%1$s-%2$04d', $year, (int) $post_id );
	}

	/**
	 * Delete every application. Called on uninstall.
	 *
	 * Post meta goes with the posts, so there is no `delete_metadata()` line for it.
	 */
	public static function delete_all() {
		$applications = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $applications as $application_id ) {
			wp_delete_post( $application_id, true );
		}
	}

	/*
	 * The stash, and the redirect it travels in
	 * --------------------------------------------------------------------
	 */

	/**
	 * Say what happened and go back to the form.
	 *
	 * Nothing a sender typed travels in the URL. `WPCPM_Flash` is the plugin's usual way
	 * of carrying a message across a redirect and cannot be used here, because it is stored per
	 * user and an applicant has no account; a query argument saying `?sent=APP-2026-0007` would
	 * make the reference a bearer token for somebody else's address and would say it again on
	 * every reload. So a random id goes in the URL and everything else goes in a transient
	 * behind it.
	 *
	 * **An outcome with nothing behind it writes nothing at all.** Every handler here is
	 * reachable by anybody on the internet with no account, and a transient is two rows in the
	 * options table: while `busy`, `closed` and the three answers the confirmation link gives
	 * each opened one, a stranger could make the site write two rows per request for ever, for
	 * a sentence that is the same sentence for everybody. Those travel as their own slug
	 * instead. Only a slug `outcomes()` knows may, which is also what keeps `sent` on the
	 * stash it must have: `sent` is drawn as a panel and has no entry in that map.
	 *
	 * @param string $outcome One of `outcomes()`, or `sent`.
	 * @param array  $stash   What the page needs to draw: values, problems, a reference.
	 */
	private static function bounce( $outcome, array $stash = array() ) {
		$page  = self::page_url();
		$url   = '' !== $page ? $page : home_url( '/' );
		$stash = self::worth_keeping( $stash );

		if ( empty( $stash ) && isset( self::outcomes()[ $outcome ] ) ) {
			wp_safe_redirect( add_query_arg( self::QUERY_OUTCOME, (string) $outcome, $url ) );

			exit;
		}

		$stash['outcome'] = (string) $outcome;

		wp_safe_redirect( add_query_arg( self::QUERY_STASH, self::stash( $stash ), $url ) );

		exit;
	}

	/**
	 * What of a stash is worth a transient, or an empty array when none of it is.
	 *
	 * Blank answers go first. A form redrawn from a stash treats an absent value and an empty
	 * one identically, so keeping the empty ones buys nothing and costs the difference between
	 * "this sender typed something" and "this request was a POST with no body in it" - which is
	 * the difference that decides whether two rows are written at all. A hand-made request that
	 * says nothing gets its answer in the address bar and leaves nothing behind; a person whose
	 * nonce expired with three paragraphs in the boxes still gets every word of it back.
	 *
	 * @param array $stash What the handler wants to hand back.
	 * @return array The same, minus the blanks.
	 */
	private static function worth_keeping( array $stash ) {
		if ( isset( $stash['values'] ) && is_array( $stash['values'] ) ) {
			foreach ( $stash['values'] as $column => $value ) {
				if ( self::is_blank( $value ) ) {
					unset( $stash['values'][ $column ] );
				}
			}
		}

		foreach ( $stash as $key => $value ) {
			if ( is_array( $value ) ? empty( $value ) : self::is_blank( $value ) ) {
				unset( $stash[ $key ] );
			}
		}

		return $stash;
	}

	/**
	 * Put one stash behind a random id and answer with the id.
	 *
	 * The id is not derived from anything: not the address, not the reference, not the session.
	 * It is the only thing that travels, so anything readable in it would be readable by
	 * whoever the link is forwarded to.
	 *
	 * @param array $stash What to keep.
	 * @return string The id.
	 */
	private static function stash( array $stash ) {
		// Lowercased because it is read back through `WPCPM_Request::key()`, which lowercases:
		// an id that came back different from the one that went out would silently lose every
		// message. Thirty-two characters of it either way.
		$id = strtolower( wp_generate_password( 32, false, false ) );

		set_transient( self::TRANSIENT_PREFIX . $id, $stash, self::TRANSIENT_MINUTES * MINUTE_IN_SECONDS );

		return $id;
	}

	/**
	 * Read the stash this request carries, if it carries one.
	 *
	 * The confirmation is one-shot: it is deleted as it is read, so a reload or a forwarded link
	 * shows the plain form rather than repeating "your application is with us", with a reference
	 * and an address on it, to whoever opens it. A failed attempt is not, because it holds only
	 * what the sender themselves typed and losing it on a reload would lose their writing, which
	 * is the thing this whole path exists to avoid.
	 *
	 * The other half of `bounce()` comes last: an outcome that had nothing behind it travelled
	 * as itself rather than as two rows in the options table. It is not one-shot and does not
	 * need to be - none of those five sentences names anybody or stops being true on a reload -
	 * and only a slug `outcomes()` knows is answered, so the argument chooses between ten fixed
	 * sentences and is never a way to put text on the page.
	 *
	 * @return array
	 */
	private static function read_stash() {
		$id = WPCPM_Request::key( self::QUERY_STASH );

		if ( '' !== $id ) {
			$stash = get_transient( self::TRANSIENT_PREFIX . $id );

			if ( is_array( $stash ) ) {
				$outcome = isset( $stash['outcome'] ) ? (string) $stash['outcome'] : '';

				if ( in_array( $outcome, array( 'sent', 'sent-quiet', 'verified', 'verified-already', 'verify-failed' ), true ) ) {
					delete_transient( self::TRANSIENT_PREFIX . $id );
				}

				return $stash;
			}
		}

		$said = WPCPM_Request::key( self::QUERY_OUTCOME );

		return isset( self::outcomes()[ $said ] ) ? array( 'outcome' => $said ) : array();
	}

	/*
	 * Mail, and the address that has to be confirmed
	 * --------------------------------------------------------------------
	 */

	/**
	 * The link in the acknowledgement that confirms the address.
	 *
	 * Signed with `wp_hash()` and bound to the stored hash of the address, so a link cannot be
	 * made for an application without the site's salts and stops working if the address on the
	 * application is ever corrected. Single use is the stamp itself: a second visit says the
	 * address is already confirmed, which is the truth and is also what somebody who followed
	 * the link twice needs to hear.
	 *
	 * @param int $post_id Application post ID.
	 * @return string
	 */
	public static function verify_url( $post_id ) {
		return add_query_arg(
			array(
				'action' => self::ACTION_VERIFY,
				'app'    => (int) $post_id,
				't'      => self::verify_signature( $post_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The signature half of a verification link.
	 *
	 * @param int $post_id Application post ID.
	 * @return string
	 */
	private static function verify_signature( $post_id ) {
		$hash = (string) get_post_meta( (int) $post_id, self::META_EMAIL, true );

		return substr( wp_hash( 'wpcpm-application-verify|' . (int) $post_id . '|' . $hash ), 0, 32 );
	}

	/**
	 * Where the manager's queue opens one application.
	 *
	 * @param int $post_id Application post ID.
	 * @return string
	 */
	public static function queue_url( $post_id ) {
		return add_query_arg( self::QUERY_QUEUE, (int) $post_id, admin_url( 'admin.php?page=wpcpm-institutions' ) );
	}

	/**
	 * Handle the confirmation link.
	 *
	 * A GET, and registered for logged-out visitors, because the person following it has no
	 * account and will not have one until a program manager says so. It answers on the
	 * application page rather than with `wp_die()`: this link is in a mail to a stranger, and
	 * a black WordPress error screen is not what the program looks like.
	 */
	public static function handle_verify() {
		$post_id = WPCPM_Request::id( 'app' );
		$post    = $post_id ? get_post( $post_id ) : null;

		// One answer for three different failures - not our post type, no such application, a
		// signature that does not match - because telling them apart would let a stranger walk
		// the IDs and learn which institutions have applied.
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || ! hash_equals( self::verify_signature( $post_id ), WPCPM_Request::text( 't' ) ) ) {
			self::bounce( 'verify-failed' );
		}

		if ( '' !== (string) get_post_meta( $post_id, self::META_VERIFIED, true ) ) {
			self::bounce( 'verified-already' );
		}

		update_post_meta( $post_id, self::META_VERIFIED, time() );
		self::add_event( $post_id, 'address confirmed', 0, '' );

		self::bounce( 'verified' );
	}

	/**
	 * Acknowledge the application to the applicant.
	 *
	 * Through `WPCPM_Mail::send_to()`, which is the single exit for all plugin mail: a message
	 * to somebody with no account used to go straight to `wp_mail()`, past the filter, the log
	 * and the subject sanitising, which made the one message sent to a stranger the one message
	 * nobody could trace.
	 *
	 * The booking link is the program's own, reproduced from the Countries map. Until this form
	 * existed an Airtable automation embedded it on `formSubmitted`; no record exists at
	 * submission any more, so the automation cannot fire and the site says it instead.
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
		$name      = $post instanceof WP_Post ? (string) $post->post_title : '';
		$reference = (string) get_post_meta( $post_id, self::META_REFERENCE, true );
		$routing   = get_post_meta( $post_id, self::META_MANAGER, true );
		$calendly  = ( is_array( $routing ) && ! empty( $routing['calendly'] ) ) ? (string) $routing['calendly'] : '';
		$verify    = self::verify_url( $post_id );
		$site      = WPCPM_Mail::site_name();

		$build = function () use ( $site, $name, $reference, $calendly, $verify, $email ) {
			$lines = array(
				sprintf(
					/* translators: %s: the institution's name as it was given on the form. */
					__( 'Thank you for asking about the WordPress Credits Program for %s. Your application is with us.', 'wpcredits-program-manager' ),
					$name
				),
				sprintf(
					/* translators: %s: the application reference, e.g. APP-2026-0007. */
					__( 'Your reference is %s. Please quote it if you write to us about this application.', 'wpcredits-program-manager' ),
					$reference
				),
				__( 'What happens next: a program manager reads every application by hand and writes back, usually within a few working days. If the program can take your institution, we will set up an account on the program site and send you the Collaboration Agreement to sign.', 'wpcredits-program-manager' ),
				__( 'Please confirm this address by following this link. It tells us we are writing to a real address, and it does nothing else:', 'wpcredits-program-manager' ),
				$verify,
			);

			if ( '' !== $calendly ) {
				$lines[] = __( 'You can also book a call with the program manager who looks after your country:', 'wpcredits-program-manager' );
				$lines[] = $calendly;
			}

			$lines[] = sprintf(
				/* translators: 1: the address the message went to, 2: site name. */
				__( 'This message went to %1$s because that address was given on the application form at %2$s. If it was not you, you can ignore it: nothing happens until the address is confirmed.', 'wpcredits-program-manager' ),
				$email,
				$site
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

		// No locale is named. The form has no language question, so the only thing known about
		// the applicant's language is the locale the page they filled in was rendered in, which
		// is the one this request is already running in.
		return WPCPM_Mail::send_to( $email, 'institution-applied', $build );
	}

	/**
	 * Tell the program managers there is something on the queue.
	 *
	 * Facts and a link, and none of the applicant's own writing: the queue shows all thirteen
	 * answers to somebody who has signed in, and a mail is a copy of them in an inbox that
	 * nothing on this site can withdraw.
	 *
	 * @param int $post_id Application post ID.
	 * @return int How many managers were told.
	 */
	private static function mail_managers( $post_id ) {
		$fields = get_post_meta( $post_id, self::META_FIELDS, true );
		$fields = is_array( $fields ) ? $fields : array();
		$post   = get_post( $post_id );

		$name    = $post instanceof WP_Post ? (string) $post->post_title : '';
		$country = (string) get_post_meta( $post_id, self::META_COUNTRY_NAME, true );
		$city    = isset( $fields['City'] ) ? (string) $fields['City'] : '';
		$person  = isset( $fields['Contact Person'] ) ? (string) $fields['Contact Person'] : '';
		$email   = self::email_of( $fields );
		$link    = self::queue_url( $post_id );
		$site    = WPCPM_Mail::site_name();

		$build = function () use ( $site, $name, $country, $city, $person, $email, $link ) {
			$lines = array(
				__( 'An institution has applied to the program through the form on the site, and the application passed every check.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: institution name, 2: city, 3: country. */
					__( '%1$s, %2$s, %3$s', 'wpcredits-program-manager' ),
					$name,
					$city,
					$country
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
					/* translators: 1: site name, 2: institution name. */
					__( '[%1$s] New institution application from %2$s', 'wpcredits-program-manager' ),
					$site,
					$name
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		return WPCPM_Institutions::notify_managers( 'institution-application', $build );
	}
}
