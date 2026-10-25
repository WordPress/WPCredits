<?php
/**
 * Institutions module - the public application form.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Loaded here as well as by the plugin's loader, on purpose. `bin/test-institution-application.php`
// requires this file with five others and nothing else, and the design (spec section 9.1) makes
// that suite, unchanged, the proof that the extraction changed nothing: a file that needed a
// seventh `require_once` in the suite would have changed the suite. `require_once` is idempotent
// by real path, so on a booted site the loader's own line has done the work and this one costs a
// stat.
require_once dirname( __DIR__ ) . '/class-wpcpm-form-guard.php';
require_once dirname( __DIR__ ) . '/class-wpcpm-form-stash.php';
// The mentor form, for its list of languages, which the languages question offers (1.123.29): one
// list for the two forms, read where it is kept. Loaded here for the same reason as the two above,
// so every suite that requires this file has the list the form draws.
require_once __DIR__ . '/class-wpcpm-mentor-application-form.php';

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
 * the eighteen fields, requiredness once, content scoring and the site-wide degrade, then
 * storage. The two cheapest tests come first because they cost nothing and answer for most of
 * what arrives; the ceilings come next because a refusal that never reads the form cannot be
 * made expensive; consent comes before the fields because a submission without it is not
 * stored at all, so cleaning eighteen values first would be work done for a row that will
 * not exist. Everything after that is about *how* a row is filed, never whether: content
 * scoring holds a row for a human and never refuses it, because the cost of a wrongly refused
 * application is an institution that never applies again and nobody ever knowing.
 *
 * **Consent is a precondition, not an answer.** The key absent, `"0"`, `"yes"`, anything but
 * `"1"` or `"true"` refuses the whole submission and stores nothing. What is stored instead is
 * the evidence: the sentence as it was rendered and the address of the policy agreed to. "They
 * agreed" is worthless if nobody can say what they agreed to. The policy is WordPress.org's since
 * 1.123.24 (`WPCPM_Form_Guard::POLICY_URL`), so the form no longer waits on the site having a
 * privacy page; an application filed before names the site's own page, its post ID and its
 * `post_modified_gmt`, the page being editable.
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
	/**
	 * The post type.
	 *
	 * **Fourteen characters, and it matters.** `register_post_type()` refuses a name longer
	 * than twenty and returns a `WP_Error` nothing in this plugin was reading, so the type it
	 * was given first, `wpcpm_institution_app`, was never registered on any site running
	 * 1.73.0: no capability mapping, no `private` declaration, nothing. The failure is silent
	 * at every layer above it, because `get_posts()` will happily query a type that does not
	 * exist. `bin/test-roles.php` now measures every post type this plugin declares.
	 */
	const POST_TYPE = 'wpcpm_inst_app';

	const SHORTCODE = 'wpcpm_inst_application';
	const BLOCK     = 'wpcpm/institution-application';
	const OPT_PAGE  = 'wpcpm_application_page_id';
	const STYLE     = 'wpcpm-institution-application';

	/**
	 * The page's slug.
	 *
	 * Printed on slides, mailed and read aloud. Renamed once, from `apply` (`OLD_SLUG`), on the
	 * owner's decision of 22 October 2026, so the address names the institutions it is for as
	 * `sponsor-application` names the sponsors. A rename breaks every link anybody has been sent,
	 * which is why the old address still answers, through `redirect_old_address()`, and why
	 * `maybe_move_page()` moves the page an existing site already has rather than making another.
	 */
	const SLUG = 'institution-application';

	/** The page's slug before 1.123.27, which `redirect_old_address()` still answers. */
	const OLD_SLUG = 'apply';

	/**
	 * The title `ensure_page()` gave the page before 1.123.27, as a site that made it then holds it.
	 *
	 * Compared as written, untranslated, because it is the stored words being compared: the page
	 * on a site that made it in another language keeps its title, and only its slug and its content
	 * move.
	 */
	const OLD_TITLE = 'Apply to the WordPress Credits Program';

	/**
	 * Option: which revision of the one-time move of the page `maybe_move_page()` has applied.
	 *
	 * A number, as the dashboards' title revisions are, so a later move runs once as well.
	 */
	const OPT_MOVED = 'wpcpm_application_page_moved';

	/** The current revision of the move. */
	const MOVE_VERSION = 1;

	/** The `admin_post_` action the form posts to, registered for logged-out visitors too. */
	const ACTION_SUBMIT = 'wpcpm_apply';

	/** The `admin_post_` action the address-verification link in the acknowledgement lands on. */
	const ACTION_VERIFY = 'wpcpm_apply_verify';

	/** Seconds a human takes to fill this in at the very least. Below it, the row is spam. The number is the guard's since 1.97.0; the name stays because the suite reads it. */
	const MIN_SECONDS = WPCPM_Form_Guard::MIN_SECONDS;

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

	/** The posted array holding the eighteen answers, keyed by `form_key()`. */
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

	/**
	 * The dwell token's scope.
	 *
	 * Part of the token's signature since the guard was extracted (1.97.0), so a token minted for
	 * this form is a forgery on the sponsor form and the other way round. The value is what the
	 * signature said before the extraction, which is why the suite's own tokens still check.
	 */
	const DWELL_SCOPE = 'wpcpm-application-dwell';

	/** How long a dwell token stays usable. Past it the answer is `stale`, which is not spam. */
	const TOKEN_LIFETIME = WPCPM_Form_Guard::TOKEN_LIFETIME;

	/** Submissions one source may make in an hour before it is refused outright. */
	const PER_HOUR = WPCPM_Form_Guard::PER_HOUR;

	/**
	 * Submissions the whole site takes in a day before every further one is held instead.
	 *
	 * Held, and not refused, and not silent either: the managers are spared and the applicant
	 * is still acknowledged. The message that acknowledges them carries the link that confirms
	 * the address, and an unconfirmed application can never be approved.
	 */
	const PER_DAY = WPCPM_Form_Guard::PER_DAY;

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
	const MAIL_PER_DAY = WPCPM_Form_Guard::MAIL_PER_DAY;

	/**
	 * The name the day's acknowledgements are counted under, by the form and by the queue's two
	 * presses that send one again (`acknowledge_again()`), so the three share one limit.
	 */
	const MAIL_KEY = 'apply-mail';

	/** How many links across the free text before the row is held for a human. */
	const MAX_LINKS = WPCPM_Form_Guard::MAX_LINKS;

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

	/**
	 * Post meta: the cleaned answers kept on the site only, keyed by field key (1.123.29).
	 *
	 * Apart from `META_FIELDS` because approval writes every key of that one to Airtable under its
	 * own name, with no typecast, and the base has no column for any of these: one of them in it
	 * would make Airtable refuse the whole record. Immutable after insert, as the fields are.
	 */
	const META_SITE = '_wpcpm_app_site';

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
		// After `register`, and once only, as every dashboard's one-time rename runs.
		add_action( 'init', array( __CLASS__, 'maybe_move_page' ), 20 );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_' . self::ACTION_VERIFY, array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_VERIFY, array( __CLASS__, 'handle_verify' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );
		// At 9, before core's `redirect_canonical()` and `wp_old_slug_redirect()`, both at 10.
		add_action( 'template_redirect', array( __CLASS__, 'redirect_old_address' ), 9 );
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
				'capability_type'     => array( 'wpcpm_inst_app', 'wpcpm_inst_apps' ),
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
	 * `WPCPM_Content_Access::META_KEY`; copying the neighboring class and keeping that call
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
		// adopts it rather than creating a second one at `institution-application-2`. At the old
		// address too, for a backup taken before the move, which `maybe_move_page()` then makes.
		foreach ( array( self::SLUG, self::OLD_SLUG ) as $slug ) {
			$existing = get_page_by_path( $slug );

			if ( $existing instanceof WP_Post ) {
				update_option( self::OPT_PAGE, $existing->ID, false );

				return (int) $existing->ID;
			}
		}

		// The title and the content are slashed, as `wp_insert_post()` unslashes what it is given and
		// `maybe_move_page()` slashes what it hands `wp_update_post()`: a backslash in a translated
		// title or intro would otherwise be lost on the way in. An English site's words hold none, so
		// they reach the page byte for byte.
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => wp_slash( self::page_title() ),
				'post_name'    => self::SLUG,
				'post_content' => wp_slash( self::page_content() ),
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
	 * The page's title, which says who the form is for.
	 *
	 * @return string
	 */
	public static function page_title() {
		return __( 'Bring WordPress Credits to your institution', 'wpcredits-program-manager' );
	}

	/**
	 * The page's content: one intro line, then the form's block, each in its block comments.
	 *
	 * The intro is a paragraph of the page and not words the block prints, so an Administrator
	 * edits it in the editor as they edit the title, and the block draws what it always drew.
	 * Written as kses keeps it, because the one-time move can run on a visitor's request, and a
	 * save made with no `unfiltered_html` passes the content through `wp_kses_post()`.
	 *
	 * @return string
	 */
	public static function page_content() {
		return "<!-- wp:paragraph -->\n<p>"
			. esc_html__( 'For universities, colleges and schools that would like to offer WordPress Credits to their students: an Administrator reads every application and writes back, usually within a few working days.', 'wpcredits-program-manager' )
			. "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:" . self::BLOCK . ' /-->';
	}

	/**
	 * Move the page an existing site already has to the new address, title and content, once.
	 *
	 * `ensure_page()` writes the three only when it *creates* the page, so a site that made it
	 * before 1.123.27 keeps `apply`, the old title and the bare block until this runs. Each of the
	 * three moves only while it is still what the plugin wrote: the slug while it is `apply`, the
	 * title while it is `OLD_TITLE`, the content while it is the bare block. A field an
	 * Administrator changed by hand is theirs and is left alone.
	 *
	 * The slug is left as well when another page, post or attachment already holds the new one:
	 * core would give this page `institution-application-2`, or, for a post, hide it behind the
	 * page, since page addresses are matched first. The page then stays at /apply/, a real page,
	 * so `redirect_old_address()` has no 404 to answer there.
	 *
	 * The flag is written before the work, whatever the outcome, as `maybe_rename_page()` writes
	 * its own: a move that failed halfway must not be tried again on every request.
	 */
	public static function maybe_move_page() {
		if ( (int) get_option( self::OPT_MOVED ) >= self::MOVE_VERSION ) {
			return;
		}

		update_option( self::OPT_MOVED, self::MOVE_VERSION, false );

		$page_id = (int) get_option( self::OPT_PAGE );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page instanceof WP_Post ) {
			return;
		}

		$changes = array();

		if ( self::OLD_SLUG === $page->post_name && ! get_page_by_path( self::SLUG, OBJECT, array( 'page', 'post', 'attachment' ) ) ) {
			$changes['post_name'] = self::SLUG;
		}

		if ( self::OLD_TITLE === $page->post_title ) {
			$changes['post_title'] = self::page_title();
		}

		// Give or take the white space around it, which is no Administrator's edit.
		if ( '<!-- wp:' . self::BLOCK . ' /-->' === trim( (string) $page->post_content ) ) {
			$changes['post_content'] = self::page_content();
		}

		if ( ! $changes ) {
			return;
		}

		// Slashed, as `wp_insert_post()` unslashes what `wp_update_post()` hands it: a backslash in a
		// translated title or intro would otherwise be lost on the way in.
		wp_update_post( array_merge( array( 'ID' => $page->ID ), wp_slash( $changes ) ) );
	}

	/**
	 * Send a visitor who asks for /apply/, the form's address before 1.123.27, to the form.
	 *
	 * WordPress keeps no old slug for a page: `wp_check_for_changed_slugs()` records one only for
	 * a post type that is not hierarchical, and `wp_old_slug_redirect()` reads only the `name`
	 * query variable. So acknowledgements and invitations already sent, bookmarks, slides, the
	 * handbook, and any template or pattern that saved the address the theme printed when it was
	 * inserted, would all land on a 404 without this.
	 *
	 * It answers only a 404, so a real page published at /apply/ later wins, and only when the path
	 * WordPress matched is exactly the old slug, in any case (/Apply/ served the form before the
	 * move, as page addresses are matched without regard to case), and the request names it as a
	 * page or a post (`pagename`, or `name` where a missing page falls through to the post rule
	 * under /%postname%/ links). The path is asked as well because by `template_redirect` WP_Query
	 * has cut a missing page's path to its last part and copied it into `name`, and a post type's
	 * address sets `name` too, so the query variables alone would also answer /about/apply/ and
	 * /<type>/apply/. The request's query string goes with the visitor, so one landing
	 * mid-redirect at the moment of the update keeps `wpcpm_app` or `wpcpm_app_said`.
	 *
	 * A 301, because the owner decided the address and search engines should learn it. At 9, before
	 * core's `redirect_canonical()`, whose guess at a post whose slug starts with `apply` could
	 * otherwise send the visitor somewhere else, and before `wp_old_slug_redirect()`.
	 */
	public static function redirect_old_address() {
		global $wp;

		if ( ! is_404() ) {
			return;
		}

		$path = isset( $wp->request ) ? (string) $wp->request : '';

		if ( ! self::is_old_slug( $path ) ) {
			return;
		}

		if ( ! self::is_old_slug( get_query_var( 'pagename' ) ) && ! self::is_old_slug( get_query_var( 'name' ) ) ) {
			return;
		}

		$url = self::page_url();

		if ( '' === $url ) {
			return;
		}

		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			$url = esc_url_raw( $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . wp_unslash( $_SERVER['QUERY_STRING'] ) );
		}

		wp_safe_redirect( $url, 301 );

		exit;
	}

	/**
	 * Whether a path or a query variable is the old slug, whatever case the visitor typed it in.
	 *
	 * WordPress matches a page's address without regard to case, so /Apply/ served the form
	 * before the move, and the request reaches `template_redirect` as typed. Only the case is
	 * ignored: a deeper path, a longer slug or anything that is not a string is still not it.
	 *
	 * @param mixed $value A request path or a query variable.
	 * @return bool
	 */
	private static function is_old_slug( $value ) {
		return is_string( $value ) && self::OLD_SLUG === strtolower( $value );
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
	 * The eighteen questions, in the order the form asks them: thirteen keyed by the exact Airtable
	 * column name, and five kept on the site only, keyed by a name of the plugin's own.
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
	 * A question's `label` is the words an applicant reads and its key is the column it is written
	 * under, so the two can differ: the internship question asks about "practicums" over a column
	 * that says "practices". Only the label may change. The key is what a record is created from and
	 * what the form's field name is hashed from (`form_key()`), so a key that moved would be a
	 * column the base does not have and a field name an open page does not post.
	 *
	 * **Five answers never reach Airtable** (1.123.29): the contact's role, the languages the
	 * students study in, who signs the agreement, their title and the institution's page about the
	 * program. The base has no column for any of them, so each carries `'site' => true`, `store()`
	 * keeps them under `META_SITE` and never in the fields approval writes, and the check that every
	 * column exists in `bin/fixtures/institutions-table-fields.json` reads only the others. Their
	 * keys are the plugin's own and posted the same way, hashed (`form_key()`).
	 *
	 * The order of this list is the form's: `render_form()` draws group by group and, inside a group,
	 * in this order, which is what makes `site_fields()` the form's order too. A list of boxes names
	 * its own choices (`'choices'`), and one long enough to need columns says so (`'columns'`), as the
	 * mentor form draws its languages.
	 *
	 * @return array<string, array> Column name or field key => spec.
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
				'group'     => 'about',
				'label'     => __( 'Website', 'wpcredits-program-manager' ),
				// `type="text"` with `inputmode="url"`, never `type="url"`: the browser refuses
				// a scheme-less address, and `example.edu` is how most people write one.
				// `WPCPM_Field_Value::clean_url()` adds the scheme instead of arguing about it.
				'type'      => 'url',
				'required'  => true,
				// Named here rather than left to the box's default of 200, because `clean()` holds
				// the server to it: the box's `maxlength` is a courtesy a hand-made request never meets.
				'maxlength' => 200,
				'help'      => __( 'For example: example.edu', 'wpcredits-program-manager' ),
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
			'contact_role'                         => array(
				'group'     => 'contact',
				'label'     => __( 'Their role at the institution', 'wpcredits-program-manager' ),
				'type'      => 'text',
				'required'  => true,
				'maxlength' => 120,
				'help'      => __( 'For example: internship coordinator, or head of the computer science department.', 'wpcredits-program-manager' ),
				'site'      => true,
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
				'label'    => __( 'How do your internships or practicums typically work?', 'wpcredits-program-manager' ),
				'type'     => 'choices',
				'required' => true,
				'choices'  => self::internship_choices(),
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
			// The mentor form's own list, read from its class, so the two forms never offer the same
			// language under two names. Values, not words to translate: they are the names stored.
			'student_languages'                    => array(
				'group'    => 'program',
				'label'    => __( 'In which languages do your students study?', 'wpcredits-program-manager' ),
				'type'     => 'choices',
				'required' => true,
				'choices'  => WPCPM_Mentor_Application_Form::languages(),
				'columns'  => true,
				'help'     => __( 'Tick every language your students study in.', 'wpcredits-program-manager' ),
				'site'     => true,
			),
			'signer_name'                          => array(
				'group'     => 'agreement',
				'label'     => __( 'Who will sign the agreement for the institution?', 'wpcredits-program-manager' ),
				'type'      => 'text',
				// Not required on its own: asked for only when a title is given with no name beside
				// it (`add_required()`), the name being what the title is of.
				'required'  => false,
				'maxlength' => 120,
				'help'      => __( 'Somebody who can commit the institution, such as a dean. Leave it empty if you do not know yet.', 'wpcredits-program-manager' ),
				'site'      => true,
			),
			'signer_title'                         => array(
				'group'     => 'agreement',
				'label'     => __( 'Their title', 'wpcredits-program-manager' ),
				'type'      => 'text',
				// Required exactly when somebody is named to sign, which `add_required()` decides.
				'required'  => false,
				'maxlength' => 120,
				'help'      => __( 'For example: Dean of the Faculty of Engineering.', 'wpcredits-program-manager' ),
				'site'      => true,
			),
			// Cleaned as Website is: a scheme-less address gains `https://`, and one naming any other
			// scheme is refused (`clean()`).
			'credits_page'                         => array(
				'group'     => 'agreement',
				'label'     => __( 'Your page about WordPress Credits', 'wpcredits-program-manager' ),
				'type'      => 'url',
				'required'  => false,
				'maxlength' => 200,
				'help'      => __( 'The agreement asks the institution to publish a page about the program on its website. If it is up already, give its address; if not, leave this empty.', 'wpcredits-program-manager' ),
				'site'      => true,
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
				'label'    => __( 'I confirm I have read the WordPress.org privacy policy and that this institution can share students\' details with the program under it.', 'wpcredits-program-manager' ),
				'type'     => 'consent',
				'required' => true,
			),
		);
	}

	/**
	 * The six groups the questions are asked in, in order.
	 *
	 * The agreement's since 1.123.29: who signs it, their title and the institution's page about the
	 * program, which its section 2 asks for.
	 *
	 * @return array<string, string> Group key => heading.
	 */
	public static function groups() {
		return array(
			'about'     => __( 'About your institution', 'wpcredits-program-manager' ),
			'contact'   => __( 'Who we should contact', 'wpcredits-program-manager' ),
			'program'   => __( 'How your program works', 'wpcredits-program-manager' ),
			'agreement' => __( 'The agreement', 'wpcredits-program-manager' ),
			'more'      => __( 'Tell us more', 'wpcredits-program-manager' ),
			'consent'   => __( 'Before you send this', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The questions kept on the site only: the specs of `fields()` that carry `'site' => true`, in
	 * the order the form asks them.
	 *
	 * For whatever reads these answers apart from the form, for their labels and the order to give
	 * them in, so a question added to the form is read there without a second list to keep in step.
	 *
	 * @return array<string, array> Field key => spec.
	 */
	public static function site_fields() {
		return array_filter(
			self::fields(),
			static function ( $spec ) {
				return ! empty( $spec['site'] );
			}
		);
	}

	/**
	 * One application's answers kept on the site, keyed by field key, as `store()` wrote them.
	 *
	 * An application filed before 1.123.29 has none, and nor does a post that is no application:
	 * both are answered with an empty list.
	 *
	 * @param int $application_id Application post ID.
	 * @return array
	 */
	public static function site_answers( $application_id ) {
		$answers = get_post_meta( (int) $application_id, self::META_SITE, true );

		return is_array( $answers ) ? $answers : array();
	}

	/**
	 * The form key one column posts under.
	 *
	 * Hashed rather than used raw because one column name carries U+2019 and another is a
	 * whole sentence with a question mark in it, and a form field name is not the place for
	 * either. Twelve hex characters, which is enough that the eighteen cannot collide and
	 * short enough to read in a browser's inspector. A site answer's key is hashed the same way,
	 * so every name the form posts has one shape.
	 *
	 * @param string $column Airtable column name, or a site answer's key.
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
	 * record ID, a multi-select of the choices its spec names, and consent, which is a
	 * precondition rather than a value.
	 *
	 * An address is the one rule added on top of that class's: one that names a scheme of its own
	 * other than http or https, such as `javascript:` or `mailto:`, is refused rather than handed
	 * to `WPCPM_Field_Value::clean_url()`, which would put `https://` in front of it and keep it.
	 * Since 1.123.29, for the Website and the page about the program alike, as one rule for both.
	 *
	 * The second rule added is the web address's length (Institutions release 2): one longer than its
	 * `maxlength` once the scheme is on it is refused as `too_long`, for both addresses alike, since
	 * `WPCPM_Field_Value` cuts a text at its `maxlength` and takes an address whole.
	 *
	 * @param string $column Airtable column name, or a site answer's key.
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
			$known  = isset( $spec['choices'] ) ? (array) $spec['choices'] : array();
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
			// The one answer that is never stored as an answer. The guard reads a tick the
			// strict way, the value the control carries and nothing else, which is exactly the
			// rule consent needs: "yes" is not a tick, and neither is an array.
			return self::accept( WPCPM_Form_Guard::consented( $raw ) );
		}

		if ( 'url' === $type && is_scalar( $raw ) && self::names_other_scheme( (string) $raw ) ) {
			return self::refuse( 'bad_url' );
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

		$clean = WPCPM_Field_Value::clean( $raw, $rules );

		// A web address is held to its length here, because `WPCPM_Field_Value` cuts a one-line text
		// at `maxlength` but takes an address whole, and the box's own `maxlength` is a courtesy a
		// hand-made request never meets. It is refused rather than cut, since half an address is
		// another address, and measured as it will be kept: the scheme a scheme-less one gains
		// counts, so nothing longer than the box takes is ever stored.
		if ( 'url' === $type && ! empty( $clean['ok'] ) && isset( $spec['maxlength'] ) && mb_strlen( (string) $clean['value'] ) > (int) $spec['maxlength'] ) {
			return self::refuse( 'too_long' );
		}

		return $clean;
	}

	/**
	 * Whether a typed address names a scheme of its own other than http or https.
	 *
	 * A scheme is letters, digits, `+`, `-` and `.`, starting with a letter, then a colon. A colon
	 * followed by a digit is a port rather than a scheme, so `example.edu:8080/credits` is an
	 * address with no scheme, which `WPCPM_Field_Value::clean_url()` gives one.
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
	 * The privacy policy the form links and asks the applicant to agree to: WordPress.org's since
	 * 1.123.24 (`WPCPM_Form_Guard::POLICY_URL`), never empty.
	 *
	 * @return string
	 */
	public static function policy_url() {
		return WPCPM_Form_Guard::policy_url();
	}

	/**
	 * Render the answer this request carries, the form, the confirmation, or nothing.
	 *
	 * Two things stop the *form* being drawn, and both answer a manager with one sentence: the
	 * form being switched off, and the countries map being empty. The public are told the first
	 * in the `closed` sentence, in the form's place, since 1.123.26: the guide promises that a
	 * switched-off form says applications are closed, and until then a visitor who followed a
	 * link to the page was shown an empty one. Not where no form would be drawn anyway, after a
	 * sent application or a confirmed address, whose applicant is not to read that the program
	 * takes none, and once, when the post that brought them here was answered `closed` already.
	 * The second still answers the public with nothing, rather than an apology: a page that says
	 * "this form is broken" to a stranger who followed a link from a conference talk is worse
	 * than a page that says nothing, and an empty countries list is a state only somebody with
	 * the settings screen can do anything about. The site having no privacy page was a third
	 * until 1.123.24, when the form began linking the WordPress.org privacy policy instead.
	 *
	 * **Neither stops a sentence, and that is a fix rather than a nicety.** While the
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

		// Everything except the outcomes where drawing the form again would invite the same
		// refusal or contradict what has just been said: a source that has used its five
		// submissions this hour, an address that has just been confirmed and has nothing left to
		// send, and the confirmation panel itself.
		$draws = ! in_array( $outcome, array( 'sent', 'sent-quiet', 'busy', 'verified', 'verified-already' ), true );

		if ( '' !== $closed ) {
			// Switched off, every visitor reads the sentence in the form's place, once.
			if ( ! self::is_open() && $draws && 'closed' !== $outcome ) {
				self::render_message( 'closed' );
			}

			// The reason, and only for somebody who can do something about it.
			if ( $can_manage ) {
				printf( '<p class="wpcpm-application__notice">%s</p>', esc_html( $closed ) );
			}
		} elseif ( $draws ) {
			self::render_form(
				isset( $stash['values'] ) && is_array( $stash['values'] ) ? $stash['values'] : array(),
				isset( $stash['problems'] ) && is_array( $stash['problems'] ) ? $stash['problems'] : array(),
				$countries
			);
		}

		$body = (string) ob_get_clean();

		// Nothing to say and nothing to draw is nothing at all, wrapper included: an empty div
		// on a page a stranger followed a link to is the same silence with markup in it, and it
		// is what the refusals answered with before any of them could carry a sentence.
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
	 * not the form can be. Each of the two is a sentence for a program manager and silence
	 * for everybody else; `render()` decides who hears it.
	 *
	 * @param array $countries The countries map, already read.
	 * @return string
	 */
	private static function closed_reason( array $countries ) {
		if ( ! self::is_open() ) {
			return __( 'The application form is switched off. Turn on "Applications from institutions" on the Institutions tab of WPCredits Program > Settings when the program is ready to take them.', 'wpcredits-program-manager' );
		}

		if ( empty( $countries ) ) {
			return __( 'This form is not shown yet: the countries list is empty, and every application has to name a country. Run the institutions sync on the Institutions screen\'s Sync and storage tab.', 'wpcredits-program-manager' );
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
							__( 'We have sent an acknowledgement to %s with a link that confirms the address. Please follow it: we cannot take your application further until you do. An Administrator reads every application by hand, so a reply takes a few working days.', 'wpcredits-program-manager' ),
							$email
						)
						: sprintf(
							/* translators: %s: the contact email address the applicant gave. */
							__( 'We could not send the acknowledgement to %s today, so please keep the reference above. Your application is stored and an Administrator will read it by hand; a reply takes a few working days.', 'wpcredits-program-manager' ),
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
			'verified'         => array( 'success', __( 'Thank you. Your address is confirmed, and your application is with an Administrator.', 'wpcredits-program-manager' ) ),
			'verified-already' => array( 'info', __( 'This address was already confirmed, so there is nothing more to do. Your application is with an Administrator.', 'wpcredits-program-manager' ) ),
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
	 * Drawn by the guard since 1.97.0, so both public forms print one honeypot.
	 */
	private static function render_honeypot() {
		WPCPM_Form_Guard::render_honeypot( self::HONEYPOT );
	}

	/**
	 * One question.
	 *
	 * The country is the one field for a long list (`WPCPM_Dashboards::render_combo()`), as the
	 * mentor application form draws its own: a text field that takes typing, with the select it
	 * stands in for kept in the form as what is sent. That select posts a Countries record ID, as it
	 * always has, so the combobox is a way to find one and not a second answer; it carries no name.
	 *
	 * @param string $column    Airtable column name, or a site answer's key.
	 * @param array  $spec      Its spec.
	 * @param mixed  $value     Its value from a failed attempt, or null.
	 * @param string $problem   The problem slug from that attempt, or ''.
	 * @param array  $countries Record ID => country name, A to Z.
	 */
	private static function render_field( $column, array $spec, $value, $problem, array $countries ) {
		$key  = self::form_key( $column );
		$id   = 'wpcpm-application-' . $key;
		$name = self::FIELD_ANSWERS . '[' . $key . ']';
		$type = $spec['type'];
		// A required attribute only where one control is the whole answer: a checkbox array has no
		// native `required` that means "at least one", and the country's select is hidden behind
		// its field once the script runs, where a browser would refuse the form for a control
		// nobody can see. Neither carries it, and `add_required()` is the check for every question.
		$required = ! empty( $spec['required'] ) && ! in_array( $type, array( 'choices', 'country' ), true ) ? ' required="required"' : '';
		// The label's own mark, printed on every required question, the two without the attribute
		// included: the mark is what tells an applicant the answer is needed.
		$required_mark = ! empty( $spec['required'] ) ? ' <span class="wpcpm-field__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>' : '';
		$invalid       = '' !== $problem ? ' aria-invalid="true"' : '';
		$says          = array();

		if ( ! empty( $spec['help'] ) ) {
			$says[] = $id . '-help';
		}

		if ( '' !== $problem ) {
			$says[] = $id . '-problem';
		}

		$describedby = empty( $says ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $says ) ) . '"';

		// A checkbox list and the country's field hold elements a paragraph cannot - the parser
		// closes a `<p>` the moment a `<div>` or a `<fieldset>` opens in it - which is what
		// scattered a field set across a row the last time this was got wrong.
		$wrapper = in_array( $type, array( 'choices', 'country' ), true ) ? 'div' : 'p';

		// A long list of boxes is drawn in columns, by the rule the mentor form's languages are.
		printf(
			'<%1$s class="wpcpm-field wpcpm-field--%2$s%3$s%4$s">',
			esc_attr( $wrapper ),
			esc_attr( $type ),
			! empty( $spec['columns'] ) ? ' wpcpm-field--columns' : '',
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
		} elseif ( 'choices' === $type ) {
			// The heading is the group's name, so a screen reader announces the question with the
			// boxes, and the hint and the problem describe the group, as the mentor application
			// form's lists of boxes are.
			printf( '<span class="wpcpm-field__label" id="%1$s-label">%2$s%3$s</span>', esc_attr( $id ), esc_html( $spec['label'] ), $required_mark ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from a literal and esc_html__() above.

			$picked = is_array( $value ) ? $value : array();

			printf( '<span class="wpcpm-field__choices" role="group" aria-labelledby="%1$s-label"%2$s>', esc_attr( $id ), $describedby ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from the escaped id above.

			foreach ( isset( $spec['choices'] ) ? (array) $spec['choices'] : array() as $choice ) {
				printf(
					'<label class="wpcpm-field__check"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> <span>%4$s</span></label>',
					esc_attr( $name ),
					esc_attr( $choice ),
					checked( in_array( $choice, $picked, true ), true, false ),
					// Trimmed for display only. The value above keeps the leading space the
					// internship column's first choice has, because that is the string Airtable
					// matches.
					esc_html( trim( $choice ) )
				);
			}

			echo '</span>';
		} elseif ( 'country' === $type ) {
			WPCPM_Dashboards::render_combo(
				array(
					'id'          => $id,
					'name'        => $name,
					// The empty entry first, then the countries as they were given, A to Z, valued by
					// record ID: the select posts the ID and the field only helps find it.
					'options'     => array( '' => __( 'Choose a country', 'wpcredits-program-manager' ) ) + $countries,
					'current'     => is_scalar( $value ) ? (string) $value : '',
					'label'       => $spec['label'],
					'mark'        => ! empty( $spec['required'] ) ? __( 'Required', 'wpcredits-program-manager' ) : '',
					'describedby' => implode( ' ', $says ),
					// A refused post marks the field a person types into, and the select behind it.
					'invalid'     => '' !== $problem,
					/* translators: Shown in the empty country field, which takes typing. */
					'find'        => __( 'Type to find your country', 'wpcredits-program-manager' ),
					'none'        => __( 'No country has that name.', 'wpcredits-program-manager' ),
					/* translators: %s: how many countries the list shows, as a number. */
					'count'       => __( 'Countries in the list: %s', 'wpcredits-program-manager' ),
				)
			);
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
	 * application is the consent sentence and the policy's address, and a policy quoted in full
	 * here would be a second copy to keep true. The policy is WordPress.org's since 1.123.24,
	 * which the sentence names, and the link opens where the form is, as it always has.
	 *
	 * The link is one word inside the sentence rather than the sentence's last two words after
	 * a colon, so it reads as a sentence and a translator can put the word where their own
	 * grammar wants it. The tags are passed as arguments for that reason: a translation must be
	 * able to move them without being able to introduce markup, which is what the `wp_kses()`
	 * around it enforces.
	 */
	private static function render_policy_line() {
		printf(
			'<span class="wpcpm-field__hint">%s</span>',
			wp_kses(
				sprintf(
					/* translators: %1$s: opening link tag to the WordPress.org privacy policy, %2$s: closing link tag. */
					__( 'You can read the WordPress.org privacy policy %1$shere%2$s.', 'wpcredits-program-manager' ),
					'<a href="' . esc_url( self::policy_url() ) . '" rel="noopener">',
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
	 * Public since Institutions release 2, so the card where a school corrects its details says a
	 * refusal in the words the form does (`WPCPM_Institution_Details::clean()`).
	 *
	 * @param string $problem Problem slug.
	 * @param array  $spec    The field's spec, for the numbers a range or a length refusal names.
	 * @return string
	 */
	public static function problem_text( $problem, array $spec ) {
		if ( 'required' === $problem ) {
			return __( 'Please fill this in.', 'wpcredits-program-manager' );
		}

		if ( 'too_long' === $problem ) {
			return sprintf(
				/* translators: %s: the most characters the answer may have, as a number. */
				__( 'Please keep this to %s characters or fewer.', 'wpcredits-program-manager' ),
				number_format_i18n( isset( $spec['maxlength'] ) ? (int) $spec['maxlength'] : 0 )
			);
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
		return WPCPM_Form_Guard::token( self::DWELL_SCOPE, self::ACTION_SUBMIT );
	}

	/**
	 * Judge a posted dwell token.
	 *
	 * Three answers, and the difference between them matters. `spam` is a form that was posted
	 * faster than anybody can read it, a token that was never minted here, or one that has
	 * already been used: those rows are stored and held as spam, and the sender is told
	 * nothing, because a bot that learns which of its attempts was recognized writes a better
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
	 * The judgment is the guard's since 1.97.0; the name stays because `handle_submit()` and
	 * the suite read it.
	 *
	 * @param string $token The posted token.
	 * @return string `ok`, `spam` or `stale`.
	 */
	public static function check_token( $token ) {
		return WPCPM_Form_Guard::check_token( self::DWELL_SCOPE, $token );
	}

	/**
	 * The address this request came from.
	 *
	 * A forwarded header is believed only when the connecting address is the one named in the
	 * `application_trusted_proxy` setting, because anybody can send that header: trusting it
	 * unconditionally would mean the per-actor ceiling is one line of a request away from
	 * being lifted, and the truncated address stored as consent evidence would be whatever
	 * the sender fancied. Empty setting means the connecting address is the client, which is
	 * true on this host. The reading itself is the guard's since 1.97.0, chain and all.
	 *
	 * @return string An IP address, or '' when there is none to be had.
	 */
	public static function client_ip() {
		return WPCPM_Form_Guard::client_ip();
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
		return WPCPM_Form_Guard::actor_key( 'apply' );
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
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		$posted = array();

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
		if ( ! WPCPM_Form_Guard::claim_actor( self::actor_key(), self::PER_HOUR ) ) {
			self::bounce( 'busy' );
		}

		// 2. The nonce.
		if ( ! wp_verify_nonce( $nonce, self::ACTION_SUBMIT ) ) {
			self::bounce( 'expired', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		// 3. The switch is the settings screen's business rather than the applicant's, and cannot
		// be reached from a form this site drew: the form is not rendered at all while it is off.
		// A post arriving anyway is somebody replaying an old page. The site having no privacy
		// page was checked here too until 1.123.24, when the form began linking WordPress.org's.
		if ( ! self::is_open() ) {
			self::bounce( 'closed' );
		}

		$signals = array();
		$spam    = false;

		// 4. The honeypot. A filled one is stored and held as spam rather than refused, and the
		// sender sees the ordinary confirmation: a bot told which attempt was recognized comes
		// back with a better one, and a human whose browser filled it in has still applied.
		if ( WPCPM_Form_Guard::honeypot_filled( self::HONEYPOT ) ) {
			$spam      = true;
			$signals[] = 'honeypot';
		}

		// 5. The dwell token, in its four answers. `stale` bounces; `fast` holds; anything else
		// that is not `ok` is spam.
		$dwell = self::check_token( WPCPM_Request::posted_text( self::TOKEN_FIELD ) );

		if ( WPCPM_Form_Guard::TOKEN_STALE === $dwell ) {
			self::bounce( 'stale', array( 'values' => self::clean_all( $posted )['values'] ) );
		}

		if ( WPCPM_Form_Guard::TOKEN_FAST === $dwell ) {
			// A token this site signed, used once, and seconds old: the applicant fixed what a
			// bounce asked for and pressed Send while the redrawn page's token was young. Held
			// and not spam (FANON-1). It matters more here than on the sponsor form: the
			// acknowledgement a held row still gets carries the link that stamps
			// `_wpcpm_app_verified`, and without that stamp no shipped path can ever approve
			// the row, so filing it as spam ended the application for good.
			$signals[] = 'dwell-fast';
		} elseif ( WPCPM_Form_Guard::TOKEN_OK !== $dwell ) {
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

		// 7. The eighteen fields, walked from `fields()` and never from `$_POST`: a key nobody
		// asked about cannot become a cell that way.
		$cleaned  = self::clean_all( $posted );
		$values   = $cleaned['values'];
		$problems = $cleaned['problems'];

		// 8. Requiredness, once, after cleaning. A row already headed for the spam pile skips
		// it: telling a bot which of eighteen fields it missed is free tuition.
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
		if ( ! WPCPM_Form_Guard::claim_site( 'apply-site', self::PER_DAY ) ) {
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
			if ( WPCPM_Form_Guard::claim_mail( self::MAIL_KEY, self::MAIL_PER_DAY ) ) {
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
	 * Clean every one of the eighteen answers.
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
	 * @return array{values:array,problems:array} Both keyed by Airtable column name or field key.
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
	 * browser's own `required` attribute is a courtesy and this is the check. Public since
	 * Institutions release 2, with the questions to check named, so the card where a school corrects
	 * its details asks the form's rules about the group it shows and not about the whole form
	 * (`WPCPM_Institution_Details::clean()`).
	 *
	 * @param array    $values   Cleaned values.
	 * @param array    $problems Problems so far.
	 * @param string[] $only     The columns or field keys to check; every question when empty.
	 * @return array
	 */
	public static function add_required( array $values, array $problems, array $only = array() ) {
		$internships = 'How do your internships or practices typically work?';

		foreach ( self::fields() as $column => $spec ) {
			// Consent is decided before this runs and is never in the values; a field that was
			// refused for a reason of its own keeps that reason rather than being told it is
			// missing when it is not.
			if ( 'consent' === $spec['type'] || isset( $problems[ $column ] ) ) {
				continue;
			}

			if ( ! empty( $only ) && ! in_array( $column, $only, true ) ) {
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

			// The signer's pair (1.123.29), rendered always for the same reason: a title is asked
			// for exactly when somebody is named to sign, and a title given with nobody named asks
			// for the name, so a title is never kept as an answer about nobody. Neither is asked
			// for when both are empty, which is what "Leave it empty if you do not know yet" says.
			if ( 'signer_title' === $column ) {
				$required = ! self::is_blank( isset( $values['signer_name'] ) ? $values['signer_name'] : null );
			}

			if ( 'signer_name' === $column ) {
				$required = ! self::is_blank( isset( $values['signer_title'] ) ? $values['signer_title'] : null );
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

		if ( function_exists( 'wp_check_comment_disallowed_list' ) && wp_check_comment_disallowed_list( $name, $email, '', $prose, self::client_ip(), WPCPM_Form_Guard::user_agent() ) ) {
			$signals[] = 'disallowed';
		}

		if ( WPCPM_Form_Guard::too_many_links( $prose, self::MAX_LINKS ) ) {
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
	 * **Nor do the five answers kept on the site** (1.123.29). They are split off here, into
	 * `META_SITE`, keyed by field key: the base has no column for any of them, and one key of
	 * them in the fields meta would make Airtable refuse the whole record at approval. A title
	 * with no signer's name is not kept, whatever state the row is filed under.
	 *
	 * The words are written as slashed copies (`wp_slash()`): `wp_insert_post()` and post meta
	 * unslash what they are handed, and the answers, the browser's name and the country's row
	 * were read unslashed, so a backslash in them would otherwise be gone.
	 *
	 * @param array  $values  Cleaned values, keyed by Airtable column name or field key.
	 * @param string $state   One of the `STATE_*` values.
	 * @param array  $signals Why it is in that state.
	 * @return int The post ID, or 0.
	 */
	private static function store( array $values, $state, array $signals ) {
		$name    = self::name_of( $values );
		$email   = self::email_of( $values );
		$country = isset( $values['Country'] ) ? (string) $values['Country'] : '';
		$fields  = $values;
		$site    = array();

		foreach ( self::server_held() as $column ) {
			unset( $fields[ $column ] );
		}

		foreach ( array_keys( self::site_fields() ) as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				$site[ $key ] = $fields[ $key ];
			}

			unset( $fields[ $key ] );
		}

		// A title is the signer's. A submission filed as spam skips requiredness, so it can reach
		// this point with a title and no name, and approving that row once it was put back in the
		// queue would copy a title that belongs to nobody. Emptied here and not in `clean_all()`,
		// which also refills a refused form and must hand back what was typed.
		$signer = isset( $site['signer_name'] ) && is_scalar( $site['signer_name'] ) ? trim( (string) $site['signer_name'] ) : '';

		if ( '' === $signer && array_key_exists( 'signer_title', $site ) ) {
			$site['signer_title'] = '';
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					// Zero for a logged-out submission, which is every real one. WordPress
					// substitutes the current user when there is one, and a program manager testing
					// the form from their own browser leaving their name on the row is a fact worth
					// having rather than one worth hiding.
					'post_author' => 0,
					'post_title'  => '' !== $name ? $name : __( 'Application with no name', 'wpcredits-program-manager' ),
				)
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;
		$routing = '' !== $country ? WPCPM_Countries::routing( $country ) : null;

		update_post_meta( $post_id, self::META_FIELDS, wp_slash( $fields ) );
		update_post_meta( $post_id, self::META_SITE, wp_slash( $site ) );
		update_post_meta( $post_id, self::META_STATE, $state );
		update_post_meta( $post_id, self::META_REFERENCE, self::reference( $post_id ) );
		update_post_meta( $post_id, self::META_COUNTRY, $country );
		update_post_meta( $post_id, self::META_COUNTRY_NAME, '' !== $country ? wp_slash( WPCPM_Countries::name_of( $country ) ) : '' );
		// A snapshot, not a lookup: who the country routed to on the day it was sent is what a
		// queue row means by "for information", and the map is rebuilt by every sync.
		update_post_meta( $post_id, self::META_MANAGER, is_array( $routing ) ? wp_slash( $routing ) : array() );
		update_post_meta( $post_id, self::META_CONSENT, wp_slash( self::consent_record() ) );
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
	 * What was agreed to.
	 *
	 * The sentence as it was rendered and the address of the policy, WordPress.org's since
	 * 1.123.24, with no post ID and no modified time, a page on another site having neither that
	 * this site can read. An application filed before holds the site's own page's ID and its
	 * `post_modified_gmt`, and the queue reads both shapes. The record is the guard's since
	 * 1.97.0: one shape for every public form.
	 *
	 * @return array
	 */
	private static function consent_record() {
		$fields = self::fields();

		return WPCPM_Form_Guard::consent_evidence( (string) $fields['Privacy Policy Compliance']['label'] );
	}

	/**
	 * Append one row to an application's event log.
	 *
	 * Repeating meta rather than one array, so two writes in the same second cannot lose each
	 * other's row the way a read-modify-write would. Written as a slashed copy, as `store()`
	 * writes, so a note keeps a backslash.
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
			wp_slash(
				array(
					'event' => sanitize_text_field( (string) $event ),
					'at'    => time(),
					'actor' => absint( $actor ),
					'note'  => sanitize_textarea_field( (string) $note ),
				)
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
	 * @param int   $limit  Most rows to read, or 0 for every one of them.
	 * @return WP_Post[]
	 */
	public static function applications( $states, $limit = 0 ) {
		$found = array();

		foreach ( self::query( $states, 'all', $limit ) as $post ) {
			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		return $found;
	}

	/**
	 * The same rows as IDs: what a total costs when nobody is going to draw them.
	 *
	 * `get_posts()` with `fields => ids` builds no `WP_Post` and primes no meta cache, and
	 * the meta here is `META_FIELDS` - the applicant's whole submitted form. The Administrator
	 * Dashboard draws fifty rows and says how many there are; asking for the rows to count
	 * them made it load a year of rejected applications and a month of spam on every render,
	 * to throw all but fifty away (deep check FADMN-2).
	 *
	 * @param array $states States to include.
	 * @return int[]
	 */
	public static function application_ids( $states ) {
		return array_map( 'intval', self::query( $states, 'ids' ) );
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
				// Oldest first, ID breaking the tie. Two applications stored in the same second
				// are one `ORDER BY post_date` and no more, so which of them the database
				// returns first is its own preference - and since the window moved into SQL
				// that decides which of them falls inside it, so the queue can reorder itself
				// between loads and hand a manager the same row twice (the deep check's P1, on
				// this reader). A public form really does take two submissions in one second.
				'orderby'     => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
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
			'one_shot'      => array( 'sent', 'sent-quiet', 'verified', 'verified-already', 'verify-failed' ),
		);
	}

	/**
	 * Redirect back with the outcome, the stash behind a transient: `WPCPM_Form_Stash::bounce()`.
	 *
	 * @param string $outcome One of `outcomes()`, or `sent`.
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
	 * Send the acknowledgement again, at an Administrator's press, and say how it went.
	 *
	 * The way the queue sends it after the form did, or in its place: Send the confirmation link
	 * again, on an open application whose address is unconfirmed, and Put back in the queue, on a
	 * spam-filed one that never had it (`WPCPM_Institutions::handle_resend_link()` and
	 * `handle_reopen()`). Approve refuses until the address is confirmed and only this email's link
	 * confirms it, so until 1.123.26, when nothing sent it again, a row past the day's limit, a
	 * spam-filed row put back and an applicant who lost the email were stuck for good.
	 *
	 * The same email through the same sender (`mail_applicant()`), so the same template and the
	 * same link: the link is a signature over the application and its address
	 * (`verify_signature()`), not a stored secret, so this carries the link the first email
	 * carried, and that one keeps working. Nothing is replaced, and nothing needs to be: the link
	 * confirms one address for one application, and an applicant who finds the first email after
	 * the second has the same link in both.
	 *
	 * Counted against the day's limit on acknowledgements as the form's send is (`MAIL_KEY`):
	 * the limit is what keeps this path from being a mailer, and a press is one more email to an
	 * address nobody has proved. An application with no usable address is answered `failed`
	 * before the limit is asked, so it spends none of it.
	 *
	 * @param int $post_id Application post ID.
	 * @return string `sent`, `ceiling` (the day's limit is reached, nothing sent) or `failed`
	 *                (no usable address, or the mail layer refused the hand-off).
	 */
	public static function acknowledge_again( $post_id ) {
		$fields = get_post_meta( (int) $post_id, self::META_FIELDS, true );

		if ( ! is_email( self::email_of( is_array( $fields ) ? $fields : array() ) ) ) {
			return 'failed';
		}

		if ( ! WPCPM_Form_Guard::claim_mail( self::MAIL_KEY, self::MAIL_PER_DAY ) ) {
			return 'ceiling';
		}

		return self::mail_applicant( (int) $post_id ) ? 'sent' : 'failed';
	}

	/**
	 * Acknowledge the application to the applicant.
	 *
	 * Through `WPCPM_Mail::send_to()`, which is the single exit for all plugin mail: a message
	 * to somebody with no account used to go straight to `wp_mail()`, past the filter, the log
	 * and the subject sanitizing, which made the one message sent to a stranger the one message
	 * nobody could trace.
	 *
	 * The booking link is the program's own, reproduced from the Countries map. Until this form
	 * existed an Airtable automation embedded it on `formSubmitted`; no record exists at
	 * submission any more, so the automation cannot fire and the site says it instead.
	 *
	 * The wording is the template registry's (`WPCPM_Mail_Templates`, the Institutions area): one
	 * email for a country with a call to book and one for a country with none.
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
		$id        = '' !== $calendly ? 'institution-applied' : 'institution-applied-no-call';

		$build = static function () use ( $id, $site, $name, $reference, $calendly, $verify, $email ) {
			return WPCPM_Mail_Templates::mail(
				$id,
				array(
					'site_name'        => $site,
					'institution_name' => $name,
					'reference'        => $reference,
					'verify_link'      => $verify,
					'booking_link'     => $calendly,
					'applicant_email'  => $email,
				)
			);
		};

		// No locale is named. The form has no language question, so the only thing known about
		// the applicant's language is the locale the page they filled in was rendered in, which
		// is the one this request is already running in.
		return WPCPM_Mail::send_to( $email, $id, $build );
	}

	/**
	 * Tell the program managers there is something on the queue.
	 *
	 * Facts and a link, and none of the applicant's own writing: the queue shows all eighteen
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

		$build = static function () use ( $site, $name, $country, $city, $person, $email, $link ) {
			return WPCPM_Mail_Templates::mail(
				'institution-application',
				array(
					'site_name'        => $site,
					'institution_name' => $name,
					'city'             => $city,
					'country'          => $country,
					'contact_name'     => $person,
					'contact_email'    => $email,
					'queue_link'       => $link,
				)
			);
		};

		return WPCPM_Institutions::notify_managers( 'institution-application', $build );
	}
}
