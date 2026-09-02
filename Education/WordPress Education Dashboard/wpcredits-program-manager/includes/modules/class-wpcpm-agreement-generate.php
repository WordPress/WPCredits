<?php
/**
 * Institutions module - generating the Collaboration Agreement as a print document.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The program's agreement, filled in with the institution's name, as a page it can print.
 *
 * Step one of the gate. An institution whose agreement is not settled sees the panel and
 * nothing else, and the first of its three numbered steps is this: generate the program's
 * template with the institution's name on it, sign it, upload the signed copy. What comes
 * back is not a rendered file. It is a standalone HTML document with an A4 print stylesheet
 * inlined, echoed straight out of `admin-post.php`, which the institution's own browser
 * turns into a PDF through Save as PDF.
 *
 * **Why no renderer.** The design's earlier shape vendored a PDF library and a Unicode font
 * to draw this one page, which bought a font cache written on the generate path, a refusal
 * of names the font had no glyphs for, and a megabyte of third-party code in a plugin whose
 * only other output is HTML. The browser already has a typesetter, and it has the right
 * fonts for whatever the institution is called. What the reviewer needs from the document is
 * not a page count or a logo: it is the footer, which names the template version, the
 * language, the name it was generated for and the day, on every sheet.
 *
 * **Why `document()` is pure.** Given a merged template, a name and a date it returns the
 * same bytes every time, reading nothing but the shipped stylesheet. That is what makes
 * "Regenerate the template as they saw it" a true statement rather than a hopeful one: the
 * reviewer holding a signed copy presses it, gets the same document back from the generated
 * post's own meta, and can compare the two. It is also why the tests live on it. The
 * handler around it does the parts that cannot be pure, in the order the design fixes:
 * logged in, resolve the institution, nonce, `decide()`, the ceiling, T2's From set, the
 * name, the merge, the post, Airtable, and only then the document.
 *
 * **Three refusals that must not print a placeholder, and they are not one refusal.** An
 * empty name, a name with a square bracket in it, and a template carrying a bracketed token
 * the merge cannot fill would each produce a document somebody signs with something other
 * than their institution's name on it. The first two are the reader's own typing and are
 * answered on the form. The third is nobody's fault at the institution and nothing they can
 * do anything about, so it mails the program managers as well. Telling the second from the
 * third is why the name is read for a bracket here rather than left to `merge()`, which
 * refuses both of them with the same error code.
 *
 * **What a generate may be asked over.** T2's `From` set is `none`, `generated`, `returned`
 * and `revoked`: every state in which the institution still owes the program a signed
 * agreement. The panel only draws the form on those, and the three it leaves out are refused
 * here, because the panel is a courtesy and this is the fence. The reason is on the base
 * rather than on this site: the write below sets `Agreement Kind` to `Program template`, and
 * doing that over an institution whose accepted document is its own paper or a legacy copy
 * would leave the base saying it signed something it did not.
 *
 * **The one failure that must not stop the download.** Airtable is the program's record of
 * this state, and every transition that settles a gate writes it first and refuses when it
 * cannot (T5, T7, T8, T9). Generating settles nothing: it produces a piece of paper. So T2
 * writes the base after the post, and a failed write stamps `_wpcpm_agr_airtable_pending`
 * for the sync to retry and lets the institution have its document, because a base that is
 * one column behind for an hour is a much smaller problem than a partner who cannot get the
 * agreement they were asked to sign.
 */
class WPCPM_Agreement_Generate {

	/** The generate route. `admin_post_` only: there is nothing here for a stranger. */
	const ACTION_GENERATE = 'wpcpm_agreement_generate';

	/**
	 * Documents one institution may generate in a day, when the setting says nothing.
	 *
	 * A nuisance control, not an entitlement: generating is cheap, deterministic and
	 * repeatable, and the only reason to bound it at all is that each one inserts a post and
	 * patches a row in the base. Ten is more than any institution needs and few enough that
	 * a loop is stopped the same day.
	 */
	const CEILING_PER_DAY = 10;

	/** The ceiling key prefix, one bucket per institution per day. */
	const CEILING_KEY = 'agreement-generate:';

	/** The print script's handle. Registered so the document names one URL, not two. */
	const SCRIPT = 'wpcpm-agreement-print';

	/** The print script, relative to the plugin directory. */
	const SCRIPT_FILE = 'assets/js/agreement-print.js';

	/** The print stylesheet, relative to the plugin directory. Inlined, never linked. */
	const STYLE_FILE = 'assets/css/agreement-print.css';

	/** The name to print, as posted by the panel's form. */
	const FIELD_NAME = 'wpcpm_agreement_name';

	/** The template language, as posted by the panel's form. */
	const FIELD_LANGUAGE = 'wpcpm_agreement_language';

	/** A generated post to reproduce, as carried by the Regenerate link. */
	const FIELD_POST = 'wpcpm_agreement_post';

	/**
	 * The institution the form was drawn for, as the panel's forms carry it.
	 *
	 * The same field name the upload and on-file forms post, because it answers the same
	 * question and a manager acting on behalf should not have to remember which of two
	 * spellings a given form wants. Read only for a manager; see `record_for_request()`.
	 */
	const FIELD_RECORD = 'wpcpm_agreement_record';

	/** How much of a posted name is kept. Characters, not bytes. */
	const MAX_NAME = 200;

	/** The language used when nothing else resolves, and the one template that exists. */
	const DEFAULT_LANGUAGE = 'en';

	/** `Agreement Status` after a generate, and the one status it may overwrite. */
	const AIRTABLE_STATUS = 'Template generated';

	/** The two statuses a generate may write over. Anything else is further along. */
	const AIRTABLE_STATUS_OPEN = array( '', 'Not started' );

	/**
	 * The document states a generate may be asked from: T2's `From` column.
	 *
	 * An allowlist rather than a list of the three refusals, so a state nobody has thought
	 * about yet refuses rather than prints. What is left out is `submitted`, `accepted` and
	 * `on_file`: a signed copy waiting for review, an accepted agreement, and one the
	 * program holds on file. In all three a document already stands for the institution and
	 * generating another would rewrite the base's `Agreement Kind` to say it signed the
	 * program's template.
	 */
	const FROM_STATES = array(
		WPCPM_Institution_Agreement::SUMMARY_NONE,
		WPCPM_Institution_Agreement::SUMMARY_GENERATED,
		WPCPM_Institution_Agreement::SUMMARY_RETURNED,
		WPCPM_Institution_Agreement::SUMMARY_REVOKED,
	);

	/** `Agreement Kind` for a document produced from the program's template. */
	const AIRTABLE_KIND = 'Program template';

	/** The event row a generated post carries. */
	const EVENT_GENERATED = 'template generated';

	/** The audit log's kind for a generate. */
	const LOG_GENERATED = 'agreement_generated';

	/** The mail context for a template the merge refused. */
	const NOTIFY_TEMPLATE = 'agreement-template';

	/**
	 * Hooks.
	 *
	 * The handler is registered beside the method it names, the way the agreement class
	 * registers its own. The script is registered on `init` rather than on an enqueue hook
	 * because the only page that uses it is echoed from `admin-post.php`, where no enqueue
	 * pass runs; the registration exists so that the URL the document prints and the URL the
	 * site would enqueue are the same string in one place.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'admin_post_' . self::ACTION_GENERATE, array( __CLASS__, 'handle_generate' ) );
	}

	/**
	 * Register the print script.
	 */
	public static function register_assets() {
		if ( wp_script_is( self::SCRIPT, 'registered' ) ) {
			return;
		}

		wp_register_script( self::SCRIPT, WPCPM_PLUGIN_URL . self::SCRIPT_FILE, array(), WPCPM_VERSION, true );
	}

	/**
	 * The URL the generated document loads its print script from.
	 *
	 * Built rather than read back out of the script registry, because the document is echoed
	 * from a request that may never have reached `init` in the ordinary way, and a URL that
	 * is sometimes there and sometimes empty is worse than one that is always the same. It
	 * is the same file and the same version `register_assets()` registers, which is what
	 * `bin/test-agreement-generate.php` compares. The version is on the query string for the
	 * reason `wp_register_script()` puts one there: a cached copy of last release's script
	 * must not be run against this release's document.
	 *
	 * @return string
	 */
	public static function script_url() {
		return WPCPM_PLUGIN_URL . self::SCRIPT_FILE . '?ver=' . rawurlencode( WPCPM_VERSION );
	}

	/**
	 * Generate the agreement, record that it was generated, and print it.
	 *
	 * The order is the design's, and each step is here because the one before it is not
	 * enough on its own:
	 *
	 * 1. logged in. There is no public route to this document; it names an institution.
	 * 2. the institution, resolved the way section 5.5 resolves it. A member's own
	 *    membership is what places them, so a posted record ID cannot move a member to
	 *    another institution; a manager's switcher is honoured, which is what lets a
	 *    manager look at what an institution would get, and it arrives in the form's own
	 *    hidden field because a POST to `admin-post.php` carries no query string.
	 * 3. the nonce, keyed to that institution, so a form drawn for one record cannot be
	 *    replayed against another.
	 * 4. `decide()`, the fence every act in this module goes through even when the steps
	 *    above have already answered. `ACT_AGREEMENT` is the one action the gate does not
	 *    apply to: an institution whose agreement is outstanding is exactly who is here.
	 * 5. the ceiling, **before the merge**, so a loop is stopped before it reads a template
	 *    and inserts a post.
	 * 6. T2's From set, on a fresh generate only. The Regenerate link is a copy of a
	 *    document that already exists and is decided on that document instead, which is
	 *    what lets a reviewer reproduce the template of an institution that has since
	 *    signed it.
	 * 7. the name and the language, cleaned. An empty name refuses: the whole point of the
	 *    document is that it names the institution twice. A name with a bracket in it
	 *    refuses too, and says so as a name, because it is the one thing the merge would
	 *    refuse that the reader typed.
	 * 8. the merge, which refuses any bracket that survives it. Anything it refuses from
	 *    here is the template's own state, and the people who can fix it are mailed.
	 * 9. the post, then Airtable per T2, then the document.
	 *
	 * The work is in `generate()` and the sending is here, for one reason worth the extra
	 * name: a method that ends in `exit` cannot be watched by anything that has to keep
	 * running afterwards, and everything above is worth watching. The route is these two
	 * lines; the checks are testable on their own.
	 */
	public static function handle_generate() {
		self::send( self::generate() );
	}

	/**
	 * Everything the generate route does except put the document on the wire.
	 *
	 * Returns the document to print. It does not return any other way: every refusal in here
	 * either redirects with a message for the panel or dies, so a caller has a document or
	 * has already stopped.
	 *
	 * @return string
	 */
	public static function generate() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in to generate the Collaboration Agreement.', 'wpcredits-program-manager' ), 403 );
		}

		$record = self::record_for_request();

		// An unresolved institution is refused outright rather than treated as "any
		// institution", which is the rule `resolve_institution()`'s own docblock states.
		if ( ! WPCPM_Mentors_Sync::is_record_id( $record ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		check_admin_referer( self::ACTION_GENERATE . '_' . $record );

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_institution( $record )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$limit = (int) WPCPM_Settings::get_value( 'agreement_generations_per_day', self::CEILING_PER_DAY );

		if ( ! WPCPM_Ceiling::claim( self::CEILING_KEY . $record, $limit, DAY_IN_SECONDS ) ) {
			self::bounce( 'agreement-busy' );
		}

		// The Regenerate link: the same document from a post that already exists, so the
		// reviewer and the institution can look at one thing. Its own subject, built from
		// the post's institution meta and not from the form, so a member of one institution
		// asking for another's document meets the one refusal.
		$asked = self::requested_post();

		if ( $asked > 0 ) {
			return self::print_again( $asked );
		}

		// T2's From set. Nothing above has asked what state this institution's agreement is
		// in: the fence decides who may act, not what is left to do. A generate over a
		// signed copy in review or over an agreement that stands would insert a second
		// `generated` post and rewrite `Agreement Kind` to `Program template` over the
		// institution's own paper or its legacy copy. Refused by name, because a member who
		// uploaded yesterday and a member whose agreement was accepted last year have
		// different things to do next and "not allowed" is neither of them.
		$state = self::current_state( $record );

		if ( ! in_array( $state, self::FROM_STATES, true ) ) {
			self::bounce( self::from_refusal( $state ) );
		}

		$name = self::clean_name( WPCPM_Request::posted_text( self::FIELD_NAME ) );

		if ( '' === $name ) {
			self::bounce( 'agreement-name' );
		}

		// The one refusal `merge()` makes that is the reader's own typing, read here so it
		// can be answered as such. `merge()` gives a bracketed name and a template with a
		// token it cannot fill the same error code, and after the merge a bracket from the
		// one is indistinguishable from a bracket from the other. Sending both to the
		// template refusal tells somebody who typed `Universidad Ejemplo [Sede Norte]` that
		// nothing is wrong at their end, over a 500, and mails every program manager a false
		// alarm about a template that is fine.
		if ( preg_match( '/[\[\]]/', $name ) ) {
			self::bounce( 'agreement-name-brackets' );
		}

		$language = self::clean_language( WPCPM_Request::posted_key( self::FIELD_LANGUAGE ) );
		$merged   = WPCPM_Agreement_Template::merge( WPCPM_Agreement_Template::load( $language ), $name );

		if ( is_wp_error( $merged ) ) {
			self::template_needs_attention( $record, $name, $language, $merged );
		}

		$version = WPCPM_Agreement_Template::version( $merged );
		$today   = wp_date( 'Y-m-d' );
		$post_id = self::insert_post( $record, $name, $language, $version, $decision );

		// Nothing has been written anywhere at this point: the base is patched below, after
		// the post, because T2 is the one transition allowed to fail. So this is not the
		// on-file route's `agreement-not-saved`, which tells the reader Airtable was updated
		// and a Refresh will finish the job. Both halves of that would be false here.
		if ( ! $post_id ) {
			self::bounce( 'agreement-generate-not-saved' );
		}

		// T2, and the one write in this module that is allowed to fail. See the class
		// docblock: generating settles nothing, and an institution that cannot get the
		// document it was asked to sign is a worse outcome than a column the sync fixes.
		if ( ! self::write_airtable( $record, $version ) ) {
			update_post_meta( $post_id, WPCPM_Institution_Agreement::META_AIRTABLE_PENDING, 1 );
			WPCPM_Flash::set( WPCPM_Institutions::FLASH, 'agreement-generated-later' );
		}

		return self::document( $merged, $name, $today );
	}

	/**
	 * The agreement as a standalone document. Pure.
	 *
	 * Nothing is read here but the arguments and the plugin's own stylesheet: no post, no
	 * option, no request, no clock. Two callers produce a document, the generate handler and
	 * `regenerate()`, and they must produce the same bytes from the same three values or
	 * "the template as they saw it" means nothing.
	 *
	 * Every string that came from the template is printed through `esc_html()`, which leaves
	 * the curly quotes the program's wording uses exactly as they are: they are not HTML
	 * special characters, and a straight quote in a signed agreement is a difference from the
	 * Doc that the fixture's checksum would not catch on this side.
	 *
	 * @param array  $template A template already through `merge()`, so it carries the name.
	 * @param string $name     The name as it prints, for the title and the footer.
	 * @param string $date     The day it was generated, `Y-m-d`.
	 * @return string A complete HTML document.
	 */
	public static function document( array $template, $name, $date ) {
		$name     = trim( (string) $name );
		$date     = trim( (string) $date );
		$language = isset( $template['language'] ) ? (string) $template['language'] : self::DEFAULT_LANGUAGE;
		$version  = WPCPM_Agreement_Template::version( $template );

		$html  = '<!DOCTYPE html>' . "\n";
		$html .= '<html lang="' . esc_attr( $language ) . '">' . "\n";
		$html .= '<head>' . "\n";
		$html .= '<meta charset="utf-8" />' . "\n";
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n";

		// The document names one institution and is served to one reader. Nothing about it
		// belongs in an index, and the URL it came from is a POST anyway.
		$html .= '<meta name="robots" content="noindex, nofollow" />' . "\n";

		// The browser proposes the title as the PDF's filename, which is why it is the
		// filename and not a sentence.
		$html .= '<title>' . esc_html( self::filename( $name ) ) . '</title>' . "\n";
		$html .= '<style>' . "\n" . self::stylesheet() . "\n" . '</style>' . "\n";
		$html .= '</head>' . "\n";
		$html .= '<body class="wpcpm-agreement-print">' . "\n";
		$html .= '<main class="wpcpm-agreement">' . "\n";
		$html .= self::blocks( $template );
		$html .= '</main>' . "\n";
		$html .= '<footer class="wpcpm-agreement__footer">' . esc_html( self::footer( $version, $language, $name, $date ) ) . '</footer>' . "\n";
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- This document is echoed from admin-post.php with no theme, no `wp_head()` and no `wp_footer()`: there is no enqueue pass to print a handle. The handle is registered all the same, and `script_url()` is the same URL it was registered under.
		$html .= '<script src="' . esc_url( self::script_url() ) . '"></script>' . "\n";
		$html .= '</body>' . "\n";
		$html .= '</html>' . "\n";

		return $html;
	}

	/**
	 * The document a generated post stands for, rebuilt from its own meta.
	 *
	 * What the manager reviewing a signed copy presses: the institution signed something,
	 * and this is what it was. The name, the language and the version all come off the post,
	 * and the date off the post's own timestamp, so the bytes are the ones that were printed
	 * rather than today's.
	 *
	 * It refuses when the plugin's copy of the template has moved on since, because the
	 * words in the file are then not the words on the paper, and a footer claiming the old
	 * version over the new text would be worse than a refusal that says so. Superseded posts
	 * are reproduced too: a generated document becomes superseded the moment the signed copy
	 * is uploaded, which is precisely when the reviewer wants to see it.
	 *
	 * No capability check. This is a producer, and every route to it decides first.
	 *
	 * @param int $post_id A `wpcpm_agreement` post of kind `template`.
	 * @return string|WP_Error The document, or why it cannot be reproduced.
	 */
	public static function regenerate( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post instanceof WP_Post || WPCPM_Institution_Agreement::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'wpcpm_agreement_unknown', __( 'That agreement document is not on this site.', 'wpcredits-program-manager' ) );
		}

		if ( WPCPM_Institution_Agreement::KIND_TEMPLATE !== (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_KIND, true ) ) {
			return new WP_Error( 'wpcpm_agreement_kind', __( 'Only a document generated from the program\'s template can be reproduced. This one was uploaded, and the file itself is the record of it.', 'wpcredits-program-manager' ) );
		}

		$name     = trim( (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT, true ) );
		$language = self::clean_language( (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_LANGUAGE, true ) );
		$stamped  = trim( (string) get_post_meta( $post->ID, WPCPM_Institution_Agreement::META_TEMPLATE_VERSION, true ) );
		$template = WPCPM_Agreement_Template::load( $language );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$current = WPCPM_Agreement_Template::version( $template );

		if ( '' !== $stamped && $stamped !== $current ) {
			return new WP_Error(
				'wpcpm_agreement_version',
				sprintf(
					/* translators: 1: the template version the document was generated from, 2: the version the plugin holds now. */
					__( 'This document was generated from template %1$s and the plugin now holds %2$s, so what the institution saw cannot be printed from the current copy. The version on their signed pages is the one to compare.', 'wpcredits-program-manager' ),
					$stamped,
					$current
				)
			);
		}

		$merged = WPCPM_Agreement_Template::merge( $template, $name );

		if ( is_wp_error( $merged ) ) {
			return $merged;
		}

		return self::document( $merged, $name, self::date_of( $post ) );
	}

	/**
	 * The filename the browser proposes for the PDF.
	 *
	 * @param string $name The institution's name as it prints.
	 * @return string
	 */
	public static function filename( $name ) {
		$slug = sanitize_title( (string) $name );

		// A name of nothing but punctuation slugs to an empty string, and a filename ending
		// in a hyphen looks like a truncation rather than a name nobody could slug.
		return 'Collaboration-Agreement-WordPress-Credits' . ( '' === $slug ? '' : '-' . $slug );
	}

	/**
	 * The footer that repeats on every sheet.
	 *
	 * The one line the reviewer reads off a signed copy without opening anything else: which
	 * template it came from, in which language, for whom, and when. A page number would say
	 * less, which is why the design does not ask for one.
	 *
	 * @param string $version  Template version.
	 * @param string $language Template language code.
	 * @param string $name     The name it was generated for.
	 * @param string $date     The day it was generated, `Y-m-d`.
	 * @return string
	 */
	public static function footer( $version, $language, $name, $date ) {
		return sprintf(
			/* translators: 1: template version, 2: language code, 3: institution name, 4: date the document was generated. */
			__( 'WordPress Credits Program - Collaboration Agreement - template %1$s (%2$s) - generated for %3$s on %4$s', 'wpcredits-program-manager' ),
			(string) $version,
			(string) $language,
			(string) $name,
			(string) $date
		);
	}

	/**
	 * The template's blocks as the body of the document.
	 *
	 * @param array $template A merged template.
	 * @return string
	 */
	private static function blocks( array $template ) {
		$blocks = isset( $template['blocks'] ) && is_array( $template['blocks'] ) ? $template['blocks'] : array();
		$html   = '';

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['type'] ) ) {
				continue;
			}

			$text = isset( $block['text'] ) ? (string) $block['text'] : '';

			switch ( $block['type'] ) {
				case 'h1':
					$html .= '<h1 class="wpcpm-agreement__h1">' . esc_html( $text ) . '</h1>' . "\n";
					break;
				case 'h2':
					$html .= '<h2 class="wpcpm-agreement__h2">' . esc_html( $text ) . '</h2>' . "\n";
					break;
				case 'h3':
					$html .= '<h3 class="wpcpm-agreement__h3">' . esc_html( $text ) . '</h3>' . "\n";
					break;
				case 'p':
					$html .= '<p class="wpcpm-agreement__p">' . esc_html( $text ) . '</p>' . "\n";
					break;

				// A lead-in to the list under it, in bold. Not a heading: "Students:" is not
				// a section of the agreement, it is the first half of the sentence the
				// bullets finish.
				case 'label':
					$html .= '<p class="wpcpm-agreement__label">' . esc_html( $text ) . '</p>' . "\n";
					break;
				case 'ul':
					$html .= self::list_block( $block );
					break;
				case 'signatures':
					$html .= self::signatures( $block );
					break;
			}
		}

		return $html;
	}

	/**
	 * One bulleted list.
	 *
	 * @param array $block A `ul` block.
	 * @return string
	 */
	private static function list_block( array $block ) {
		$items = isset( $block['items'] ) && is_array( $block['items'] ) ? $block['items'] : array();

		if ( ! $items ) {
			return '';
		}

		$html = '<ul class="wpcpm-agreement__list">' . "\n";

		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( (string) $item ) . '</li>' . "\n";
		}

		return $html . '</ul>' . "\n";
	}

	/**
	 * The signature blocks: one column per party, one ruled line per thing to fill in.
	 *
	 * Two columns because the two parties sign the same sheet, and the template's own shape
	 * says which they are. Each line is a small label above a rule rather than a run of
	 * underscores, so it is the same length in every browser and there is room to write on
	 * it.
	 *
	 * @param array $block A `signatures` block.
	 * @return string
	 */
	private static function signatures( array $block ) {
		$parties = isset( $block['parties'] ) && is_array( $block['parties'] ) ? $block['parties'] : array();

		if ( ! $parties ) {
			return '';
		}

		$html = '<div class="wpcpm-agreement__signatures">' . "\n";

		foreach ( $parties as $party ) {
			if ( ! is_array( $party ) ) {
				continue;
			}

			$lines = isset( $party['lines'] ) && is_array( $party['lines'] ) ? $party['lines'] : array();

			$html .= '<div class="wpcpm-agreement__party">' . "\n";
			$html .= '<p class="wpcpm-agreement__party-name">' . esc_html( isset( $party['party'] ) ? (string) $party['party'] : '' ) . '</p>' . "\n";

			foreach ( $lines as $line ) {
				$html .= '<p class="wpcpm-agreement__line">'
					. '<span class="wpcpm-agreement__line-label">' . esc_html( (string) $line ) . '</span>'
					. '<span class="wpcpm-agreement__line-rule"></span>'
					. '</p>' . "\n";
			}

			$html .= '</div>' . "\n";
		}

		return $html . '</div>' . "\n";
	}

	/**
	 * The print stylesheet, ready to inline.
	 *
	 * Read off disk rather than held as a string so it can be edited as CSS, and dropped
	 * whole on either of two things it must never carry into the document:
	 *
	 * - a square bracket. `merge()` guarantees the agreement's text has none, because a
	 *   bracket on a page a rector signs reads as a placeholder the generator failed to
	 *   fill in. The stylesheet is the one part of the document that has not been through
	 *   that check, and an attribute selector would put a bracket back after the merge had
	 *   refused one. This is where that guarantee is kept for the rest of the page.
	 * - the two characters that end a tag, in that order, which would close the style
	 *   element early and print the remainder of the file as text.
	 *
	 * Dropping is the right failure in both cases: an unstyled agreement is still the whole
	 * agreement, and a document that broke halfway down is not.
	 *
	 * @return string
	 */
	private static function stylesheet() {
		$path = WPCPM_PLUGIN_DIR . self::STYLE_FILE;

		if ( ! is_readable( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The plugin's own stylesheet, read by an absolute path inside its own directory; WP_Filesystem would ask for credentials on a request that has none to give.
		$css = (string) file_get_contents( $path );

		if ( false !== strpos( $css, '</' ) || preg_match( '/[\[\]]/', $css ) ) {
			return '';
		}

		return trim( $css );
	}

	/**
	 * A document that already exists, for the Regenerate link.
	 *
	 * Its own decision, on the post rather than on the institution the form named: a member
	 * of one institution asking for another's document is decided against that document's
	 * institution and gets the one refusal, which is the rule for every post-keyed route in
	 * this module.
	 *
	 * It spends a claim from the same daily budget as a fresh generate, because from the
	 * reader's side it is the same act: another copy of the document they were asked to
	 * sign. It writes nothing, so nothing about it needs undoing when the budget runs out.
	 *
	 * @param int $post_id A generated agreement post.
	 * @return string The document. Refusals do not return.
	 */
	private static function print_again( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post instanceof WP_Post || WPCPM_Institution_Agreement::POST_TYPE !== $post->post_type ) {
			self::bounce( 'agreement-unknown' );
		}

		$decision = WPCPM_Institution_Policy::decide(
			WPCPM_Institution_Policy::ACT_AGREEMENT,
			WPCPM_Institution_Policy::subject_post( $post, WPCPM_Institution_Agreement::META_INSTITUTION )
		);

		if ( empty( $decision['allowed'] ) ) {
			wp_die( esc_html( WPCPM_Institution_Policy::refusal()->get_error_message() ), 403 );
		}

		$document = self::regenerate( (int) $post->ID );

		if ( is_wp_error( $document ) ) {
			wp_die( esc_html( $document->get_error_message() ), 409 );
		}

		return $document;
	}

	/**
	 * Send one document and stop.
	 *
	 * Never cached. The page carries an institution's name and is built for one reader, and
	 * a shared cache holding it would serve one institution's agreement to the next.
	 *
	 * @param string $document A complete HTML document.
	 */
	private static function send( $document ) {
		nocache_headers();

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
		}

		echo $document; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A whole HTML document built by document(), which escapes every value it interpolates; escaping it again would print its own markup.
		exit;
	}

	/**
	 * The generated post, with everything a reviewer will ask about it later.
	 *
	 * @param string $record   Institutions record ID.
	 * @param string $name     The name printed on the document.
	 * @param string $language Template language code.
	 * @param string $version  Template version.
	 * @param array  $decision The decision that allowed this, for the log's ground.
	 * @return int The post ID, or 0 when it could not be written.
	 */
	private static function insert_post( $record, $name, $language, $version, array $decision ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => WPCPM_Institution_Agreement::POST_TYPE,
				'post_status' => WPCPM_Institution_Agreement::POST_STATUS,
				'post_author' => get_current_user_id(),
				'post_title'  => sprintf(
					/* translators: %s: Airtable record ID of the institution. */
					__( 'Collaboration Agreement generated (%s)', 'wpcredits-program-manager' ),
					$record
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;

		update_post_meta( $post_id, WPCPM_Institution_Agreement::META_INSTITUTION, $record );
		update_post_meta( $post_id, WPCPM_Institution_Agreement::META_STATE, WPCPM_Institution_Agreement::STATE_GENERATED );
		update_post_meta( $post_id, WPCPM_Institution_Agreement::META_KIND, WPCPM_Institution_Agreement::KIND_TEMPLATE );
		update_post_meta( $post_id, WPCPM_Institution_Agreement::META_LANGUAGE, $language );
		update_post_meta( $post_id, WPCPM_Institution_Agreement::META_TEMPLATE_VERSION, $version );
		update_post_meta( $post_id, WPCPM_Institution_Agreement::META_NAME_ON_DOCUMENT, $name );
		update_post_meta( $post_id, WPCPM_Institution_Agreement::META_DECIDED_BY, get_current_user_id() );

		add_post_meta(
			$post_id,
			WPCPM_Institution_Agreement::META_EVENT,
			array(
				'event' => self::EVENT_GENERATED,
				'at'    => time(),
				'actor' => get_current_user_id(),
			)
		);

		// Design spec 5.6: every act on an institution's agreement leaves a row carrying the
		// ground it was allowed on. Generating is the first step of the gate and the one a
		// member takes alone, so the log is where a manager reads that they took it, and
		// under which name and template version.
		WPCPM_Institution_Audit::record(
			array(
				'kind'        => self::LOG_GENERATED,
				'institution' => $record,
				'subject'     => (string) $post_id,
				'actor'       => get_current_user_id(),
				'ground'      => isset( $decision['ground'] ) ? (string) $decision['ground'] : '',
				'evidence'    => WPCPM_Institution_Audit::EVIDENCE_INDEX,
				'message'     => __( 'The program\'s Collaboration Agreement template was generated for signature.', 'wpcredits-program-manager' ),
				'data'        => array(
					'kind'             => WPCPM_Institution_Agreement::KIND_TEMPLATE,
					'language'         => $language,
					'template_version' => $version,
					'name'             => $name,
				),
			)
		);

		return $post_id;
	}

	/**
	 * T2's Airtable write: the kind, the version, and the status when it is still open.
	 *
	 * No stage write, deliberately: generating a template is not the program saying yes to
	 * anything, and the pipeline stage is the program's to move. The status is only written
	 * over an empty one or `Not started`, so an institution that generates a second copy
	 * while its first is in review does not push the row backwards.
	 *
	 * @param string $record  Institutions record ID.
	 * @param string $version Template version.
	 * @return bool Whether the base took the write.
	 */
	private static function write_airtable( $record, $version ) {
		$settings = WPCPM_Settings::get();
		$fields   = WPCPM_Institutions_Sync::fields();

		// Named through the sync's field map, so the base's spelling is asserted in one
		// place and against one fixture. `update_records()` sends no `typecast`, so a choice
		// spelled any other way is a 422 for the whole record.
		$cells = array(
			$fields['agr_kind']     => self::AIRTABLE_KIND,
			$fields['agr_template'] => $version,
		);

		if ( in_array( self::airtable_status( $record ), self::AIRTABLE_STATUS_OPEN, true ) ) {
			$cells[ $fields['agr_status'] ] = self::AIRTABLE_STATUS;
		}

		$airtable = new WPCPM_Airtable( $settings );
		$written  = $airtable->update_records(
			$settings['institutions_table'],
			array(
				array(
					'id'     => $record,
					'fields' => $cells,
				),
			)
		);

		// An empty result is a failure too: `update_records()` drops a record it cannot send
		// and answers with the ones it did, so "nothing was updated" must not read as done.
		return ! is_wp_error( $written ) && ! empty( $written );
	}

	/**
	 * What the base's `Agreement Status` said the last time this site read it.
	 *
	 * From the option first, which every site transition rewrites as well as every sync, and
	 * from the pipeline index when there is no readable option. Never a live read: T2 is the
	 * one transition allowed to fail, and an HTTP call to decide whether to make an HTTP call
	 * is two chances to keep an institution waiting for a document.
	 *
	 * @param string $record Institutions record ID.
	 * @return string
	 */
	private static function airtable_status( $record ) {
		$option = WPCPM_Institution_Agreement::option( $record );

		if ( is_array( $option ) && isset( $option['airtable_status'] ) ) {
			return trim( (string) $option['airtable_status'] );
		}

		$row = WPCPM_Institutions_Index::row( $record );

		return is_array( $row ) && isset( $row['agreement']['status'] ) ? trim( (string) $row['agreement']['status'] ) : '';
	}

	/**
	 * Which institution this request generates for.
	 *
	 * `WPCPM_Institution_Roster::resolve_institution()` is the module's answer and stays the
	 * answer: a member's own membership is what places them and no posted value can move
	 * them, and a manager's switcher is honoured. The one thing it cannot see from here is
	 * the switcher, which lives in the dashboard's query string; this form posts to
	 * `admin-post.php`, and a POST carries no query string. The panel puts the record it drew
	 * the form for in a hidden field for that reason, and this is where the field is read.
	 *
	 * Read only for a user who holds `CAP_MANAGE`, asked of the capability here rather than
	 * taken from the form, and only for a record the pipeline index holds. It is the field
	 * the module's other on-behalf forms already post, read in the precedence
	 * `resolve_institution()` itself uses for a manager - the switcher before their own
	 * membership - so that the handler acts on the institution the panel drew the form for
	 * and a manager who is also a member of somewhere else is not sent to their own row.
	 *
	 * Nothing downstream is weakened by it: the nonce is keyed to whatever this returns, so
	 * a form drawn for one institution cannot be replayed at another, and `decide()` is asked
	 * about it either way.
	 *
	 * @return string Institutions record ID, or ''.
	 */
	private static function record_for_request() {
		$viewer     = wp_get_current_user();
		$can_manage = current_user_can( WPCPM_Roles::CAP_MANAGE );

		if ( $can_manage ) {
			$asked = WPCPM_Request::posted_text( self::FIELD_RECORD );

			if ( WPCPM_Institutions_Index::has( $asked ) ) {
				return trim( $asked );
			}
		}

		return WPCPM_Institution_Roster::resolve_institution( $viewer, $can_manage );
	}

	/**
	 * What state this institution's agreement is in, as the panel and the queue name it.
	 *
	 * The site half of `summary()`, computed from the posts. Deliberately not the base's
	 * `Agreement Status`: the posts are what say whether a document stands here, the base is
	 * a second opinion the reconcile is responsible for, and `write_airtable()` already
	 * refuses to push that column backwards.
	 *
	 * @param string $record Institutions record ID.
	 * @return string A `WPCPM_Institution_Agreement::SUMMARY_*` value, or ''.
	 */
	private static function current_state( $record ) {
		$summary = WPCPM_Institution_Agreement::summary( $record );

		return isset( $summary['state'] ) ? (string) $summary['state'] : '';
	}

	/**
	 * How a state outside T2's From set is refused, in the reader's terms.
	 *
	 * Two answers rather than one, because they ask for different things. A signed copy
	 * waiting for review is something the institution can withdraw if it uploaded the wrong
	 * file; an agreement that stands, whether accepted here or held on file by the program,
	 * is replaced by uploading a new signed copy and not by printing another template.
	 *
	 * Two is all there is to write. The summary is a closed list of seven, four of them are
	 * the From set, and of the three left `accepted` and `on_file` are the same news to the
	 * reader: something they signed is in force.
	 *
	 * @param string $state The state the institution is in.
	 * @return string An outcome slug `WPCPM_Institution_Panel::messages()` names.
	 */
	private static function from_refusal( $state ) {
		if ( WPCPM_Institution_Agreement::SUMMARY_SUBMITTED === $state ) {
			return 'agreement-generate-in-review';
		}

		return 'agreement-generate-standing';
	}

	/**
	 * The post the Regenerate link named, from the query string or a form.
	 *
	 * The link is a `wp_nonce_url()` on `admin-post.php`, which dispatches GET as readily as
	 * POST, and the panel may equally post it from a button. Both are read; neither proves
	 * anything, and the caller has already checked the nonce.
	 *
	 * @return int
	 */
	private static function requested_post() {
		$post_id = WPCPM_Request::id( self::FIELD_POST );

		return $post_id > 0 ? $post_id : WPCPM_Request::posted_id( self::FIELD_POST );
	}

	/**
	 * The name to print, as the form gave it.
	 *
	 * Pre-filled by the panel from the index row's `Name`, and editable, because the base's
	 * name is an operational label ("Krakow - UJ") often enough that a document generated
	 * from it would have to be thrown away. Capped in characters and not bytes: the cap
	 * exists so a name fits on a line, and a byte cap would cut a multi-byte name in half.
	 *
	 * @param string $raw The posted value, already through `sanitize_text_field()`.
	 * @return string
	 */
	private static function clean_name( $raw ) {
		return trim( mb_substr( (string) $raw, 0, self::MAX_NAME ) );
	}

	/**
	 * The language to load, validated against the templates that exist.
	 *
	 * Anything the directory does not offer falls back to English when English is there, and
	 * to the first template otherwise. A refusal would be the wrong answer: the picker only
	 * appears when there is more than one language, so an unknown code is a stale form or a
	 * hand-typed URL, and the institution still needs an agreement.
	 *
	 * @param string $raw The posted code.
	 * @return string
	 */
	private static function clean_language( $raw ) {
		$raw       = strtolower( trim( (string) $raw ) );
		$languages = WPCPM_Agreement_Template::languages();

		if ( in_array( $raw, $languages, true ) ) {
			return $raw;
		}

		if ( in_array( self::DEFAULT_LANGUAGE, $languages, true ) || ! $languages ) {
			return self::DEFAULT_LANGUAGE;
		}

		return (string) $languages[0];
	}

	/**
	 * The day a generated post was printed, from the post itself.
	 *
	 * @param WP_Post $post A generated agreement post.
	 * @return string `Y-m-d`.
	 */
	private static function date_of( WP_Post $post ) {
		$date = substr( (string) $post->post_date, 0, 10 );

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : wp_date( 'Y-m-d' );
	}

	/**
	 * Refuse a template the merge would not fill in, and tell the program managers.
	 *
	 * The one refusal that is nobody's fault at the institution. `merge()` fails when a
	 * bracketed token survives it, which means somebody edited the template file and added a
	 * placeholder the generator does not know how to fill. Printing it anyway would put
	 * `[Signatory Title]` on a page a rector signs, and nobody would notice until the signed
	 * copy came back. So the institution is told the truth in one sentence, and the people
	 * who can fix it are mailed, because the reader here cannot.
	 *
	 * Only reached for a failure of the template. The two refusals the reader can do
	 * something about - an empty name and a name with a bracket in it - are answered on the
	 * form before the merge is asked, so nothing that arrives here is a typo, and a manager
	 * reading this mail can trust that the template is what needs looking at.
	 *
	 * Never returns: it ends in `wp_die()`.
	 *
	 * @param string   $record   Institutions record ID.
	 * @param string   $name     The name that was asked for.
	 * @param string   $language The language that was asked for.
	 * @param WP_Error $error    What `load()` or `merge()` refused with.
	 */
	private static function template_needs_attention( $record, $name, $language, WP_Error $error ) {
		$site   = WPCPM_Mail::site_name();
		$reason = $error->get_error_message();

		$build = function () use ( $site, $record, $name, $language, $reason ) {
			$lines = array(
				__( 'An institution asked the site to generate the Collaboration Agreement and the template could not be used, so nothing was generated and they were told the template needs attention.', 'wpcredits-program-manager' ),
				sprintf(
					/* translators: 1: what the template refused with, 2: language code, 3: institution record ID, 4: the name that was asked for. */
					__( 'The reason given was: %1$s (template language %2$s, institution %3$s, name asked for "%4$s").', 'wpcredits-program-manager' ),
					$reason,
					$language,
					$record,
					$name
				),
				__( 'The template lives in the plugin, in includes/templates/. A bracketed token other than the institution name is refused on purpose: it would otherwise print on a document somebody signs. Nothing can be generated in this language until it is fixed.', 'wpcredits-program-manager' ),
			);

			return array(
				'subject' => sprintf(
					/* translators: %s: site name. */
					__( '[%s] The Collaboration Agreement template needs attention', 'wpcredits-program-manager' ),
					$site
				),
				'body'    => implode( "\r\n\r\n", $lines ),
			);
		};

		WPCPM_Institutions::notify_managers( self::NOTIFY_TEMPLATE, $build );

		wp_die( esc_html__( 'The agreement template needs attention; a program manager has been told. Nothing was generated, and nothing is wrong at your end.', 'wpcredits-program-manager' ), 500 );
	}

	/**
	 * Say what happened and go back where the form was.
	 *
	 * The generate form is on the institution's own dashboard, so the destination is where
	 * the request came from; `wp_safe_redirect()` keeps that to this site, and a request with
	 * no referer lands on the dashboard page. The words for each outcome live in
	 * `WPCPM_Institution_Panel::messages()`, once, because the panel and the manager's row
	 * both print them.
	 *
	 * Never returns: the redirect is followed by `exit`. Every caller is written on that,
	 * and reads as a refusal that ends the request.
	 *
	 * @param string $status Outcome slug.
	 */
	private static function bounce( $status ) {
		WPCPM_Flash::set( WPCPM_Institutions::FLASH, $status );

		$back = wp_get_referer();

		if ( ! $back ) {
			$back = WPCPM_Institutions_Dashboard::page_url();
		}

		wp_safe_redirect( $back ? $back : home_url( '/' ) );
		exit;
	}
}
