<?php
/**
 * The sponsor badge: an image a sponsor puts on its own site, served by the program's site at
 * `/sponsor-badge/<slug>`, and at `/sponsor-badge/<slug>-dark` for a dark background, with the
 * Badge card on the Sponsor Dashboard that shows both and the code that embeds one. The
 * address has no extension: a host that serves static files itself answers a missing `.svg` path
 * with its own 404 before WordPress ever sees the request.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The route, the SVG and the card, in one class, because all three answer the same two
 * questions: who has a badge, and where it lives.
 *
 * Who has one: an Approved sponsor whose recorded tier includes it, Supporter and up. The
 * recorded tier and not the effective one, because a term that has ended keeps
 * its badge, naming the year that term ended (spec section 6): the image on the sponsor's site
 * then claims nothing current, and it goes on working until the program contact renews the term.
 * While a term runs, or where there is none, the year is the current one, so a term bought in
 * October does not name the next year on its first day (see `year()`). A badge that is served
 * says what it is made to say: that its company sponsors the program, and at which tier. What
 * the route keeps to itself is why a name has no badge: every refusal - a name no sponsor holds,
 * a Friend, a sponsor that is not Approved, the launch switch off - is the same 404 with
 * an empty body. A program manager is refused like everyone else: nothing about the tiers is
 * shown before the program announces them, and the badge has no preview.
 *
 * The badge's own words, the program's name above the tier, are the program's mark and are never
 * handed to the translation functions: the badge is shown on other people's sites, and a mark
 * reads the same wherever it is shown. The tier's name is the ladder's label,
 * `WPCPM_Sponsor_Tiers::label()`, so the badge and the Sponsor Dashboard name a tier the same way.
 *
 * The SVG holds no script, no external reference and no external font: its text is set in the
 * system font stack the site itself uses, so it draws in whatever the viewer has, and the layout
 * leaves room for the difference. It is served on the badge's own route alone, as
 * `image/svg+xml` named as a `.svg` file for whoever saves it, with `nosniff`, a
 * `default-src 'none'` policy, an hour of public cache and an ETag, and the route is metered per
 * address like the public feed: a visitor's browser asks for the image once an hour at most, and
 * gets a 304 with no body while the image is the one it holds, so 120 an hour is a crawler's
 * number, not a reader's. The cache was a day until 1.123.22, which left a sponsor's old level on
 * its own site for up to a day after an Administrator changed it; an hour is how long it can show
 * there now. The Sponsor Dashboard draws the badge at a versioned address (`versioned_url()`),
 * whose `v` argument names the tier and the year the badge draws, so there a change shows at once;
 * the embed code a sponsor copies keeps the plain address, so an embed pasted before keeps working.
 *
 * A badge is fetched by the visitors of other sites, so what one costs is kept small: it is
 * answered as soon as core has parsed the request, before core runs the main query, which the
 * route's one query var would otherwise leave querying the home page's posts for nothing; and its
 * sponsor is read as the one post of the slug's name, not as every sponsor the store holds.
 */
final class WPCPM_Sponsor_Badge {

	/** The query var the rewrite fills with the requested name. */
	const QUERY_VAR = 'wpcpm_badge';

	/**
	 * The badge's rewrite rule: any name without a slash, and no extension, since a host that
	 * serves static files itself answers a missing `.svg` path before WordPress sees it. Where a
	 * host passes the `.svg` form on it still reaches the badge, and so does a trailing slash; the
	 * name is handed on without either and otherwise as the request spelled it, so a slug core
	 * stored percent-encoded reaches `resolve()` that way. Also how `is_badge_request()` knows the
	 * request is the badge's: core records the rule it matched by this same string.
	 */
	const RULE = '^sponsor-badge/([^/]+?)(?:\.svg)?/?$';

	/** The rewrite version the site's stored rules were last flushed for. */
	const OPT_REWRITE = 'wpcpm_badge_rewrite';

	/**
	 * Moved when the rule changes, so `maybe_flush()` writes the rules once more on every site: 2
	 * since the address lost its extension.
	 */
	const REWRITE_VERSION = 2;

	/** The route's own name for the per-address ceiling. */
	const CEILING = 'sponsor-badge';

	/** The card's anchor on the Sponsor Dashboard, `wpcpm-sponsor-badge`. */
	const CARD = 'badge';

	/** The badge's size in pixels; the card's images and the embed code carry the same. */
	const WIDTH  = 300;
	const HEIGHT = 80;

	/** The WordPress mark, the same path the theme prints inline; on a 122.523-unit square. */
	const MARK_PATH = 'M8.708 61.26c0 20.802 12.089 38.779 29.619 47.298L13.258 39.872a52.354 52.354 0 0 0-4.55 21.388zM96.74 58.608c0-6.495-2.333-10.993-4.334-14.494-2.664-4.329-5.161-7.995-5.161-12.324 0-4.831 3.664-9.328 8.825-9.328.233 0 .454.029.681.042-9.35-8.566-21.807-13.796-35.489-13.796-18.36 0-34.513 9.42-43.91 23.688 1.233.037 2.395.063 3.382.063 5.497 0 14.006-.667 14.006-.667 2.833-.167 3.167 3.994.337 4.329 0 0-2.847.335-6.015.501l19.138 56.925L59.706 59.59l-8.188-22.434c-2.83-.166-5.511-.501-5.511-.501-2.832-.166-2.5-4.496.331-4.329 0 0 8.679.667 13.843.667 5.496 0 14.006-.667 14.006-.667 2.835-.167 3.168 3.994.337 4.329 0 0-2.853.335-6.015.501l18.992 56.494 5.242-17.517c2.272-7.269 4-12.49 4-16.989zM62.184 65.857l-15.768 45.819a52.578 52.578 0 0 0 14.832 2.143c5.81 0 11.382-1.005 16.567-2.825a4.689 4.689 0 0 1-.376-.73L62.184 65.857zm45.19-29.812c.226 1.674.354 3.471.354 5.404 0 5.333-.996 11.328-3.996 18.824L87.685 107.83c15.622-9.111 26.13-26.038 26.13-45.426.001-9.137-2.333-17.729-6.441-25.226zM61.262 0C27.483 0 0 27.481 0 61.26c0 33.783 27.483 61.263 61.262 61.263 33.778 0 61.265-27.48 61.265-61.263C122.526 27.481 95.04 0 61.262 0zm0 119.715c-32.23 0-58.453-26.223-58.453-58.455 0-32.23 26.222-58.451 58.453-58.451 32.229 0 58.45 26.221 58.45 58.451 0 32.232-26.221 58.455-58.45 58.455z';

	/** The system font stack the site's own text is set in. */
	const FONT_STACK = "-apple-system, system-ui, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif";

	/**
	 * The light version's colors, from the theme's tokens: a white ground with the card's rule
	 * (`line.card`), the tier in ink (`ink`), the eyebrow and the year in the muted gray (`ink-60`).
	 */
	const COLORS_LIGHT = array(
		'bg'      => '#ffffff',
		'rule'    => '#e4e4e4',
		'eyebrow' => '#656a71',
		'text'    => '#1e1e1e',
		'year'    => '#656a71',
	);

	/**
	 * The dark version's: an ink ground (`ink`) with the secondary ink as its rule (`ink-70`), the
	 * tier in white, the eyebrow and the year in the icon gray (`ink-50`).
	 */
	const COLORS_DARK = array(
		'bg'      => '#1e1e1e',
		'rule'    => '#40464d',
		'eyebrow' => '#979aa1',
		'text'    => '#ffffff',
		'year'    => '#979aa1',
	);

	/** The tile is the brand blue (`brand`) and the mark white on it, in both versions. */
	const COLOR_TILE = '#3858e9';
	const COLOR_MARK = '#ffffff';

	/**
	 * Hooks: the rule and the query var on every request, the flush once per site, the handler.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 11 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		// Once core has matched the rule and filled the query vars, and before it runs the main
		// query: the rule's one query var is none the main query reads, so a badge request that
		// reached it would query the home page's posts, their meta and their terms, and throw them
		// away. Ahead of redirect_canonical() too, which would otherwise answer the request first.
		add_action( 'parse_request', array( __CLASS__, 'handle' ) );
	}

	/**
	 * The one rule, at the top, so no page or post rule reads `sponsor-badge` as a slug first.
	 * Added on every request, as every rule must be: the stored rules are only what was added
	 * when they were last flushed, and a flush by anything else drops a rule nobody adds.
	 */
	public static function add_rules() {
		add_rewrite_rule( self::RULE, 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * The query var, made public so the rule's match reaches `get_query_var()`.
	 *
	 * @param array $vars The public query vars.
	 * @return array
	 */
	public static function query_vars( array $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Write the rules once per site, and again whenever REWRITE_VERSION moves: the activation hook
	 * does not fire on an in-place update, so the version option carries the flush, the way
	 * `WPCPM_Roles::maybe_upgrade()` carries the roles. Autoloaded, since every request reads it.
	 * `WPCPM_Sponsors::activate()` deletes it: the activation request flushes the rules without
	 * this one, since this plugin's init never runs in that request, and the next request then
	 * writes them again with it.
	 *
	 * A soft flush, the stored rules only: the rule's query goes to `index.php`, so core keeps it
	 * among its own rules and never writes it to the server's `.htaccess`, which a hard flush
	 * would rewrite for nothing. Core puts off a flush asked for before `wp_loaded` until then, so
	 * what is written holds every rule the request adds, not only those added before this
	 * priority; and so the version is stored then too, after core's write (`store_version()`).
	 * Stored here, at init, it would claim rules that a request ending before `wp_loaded` never
	 * wrote, and no later request would ask for them again.
	 */
	public static function maybe_flush() {
		if ( (int) get_option( self::OPT_REWRITE ) === self::REWRITE_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );

		// Asked for once wp_loaded has fired, core writes the rules at once, and the version with them.
		if ( did_action( 'wp_loaded' ) ) {
			self::store_version();
		} else {
			add_action( 'wp_loaded', array( __CLASS__, 'store_version' ), 11 );
		}
	}

	/**
	 * Record that the site's rules were written for REWRITE_VERSION: on `wp_loaded` at 11, after
	 * the flush `maybe_flush()` asked for, which core writes on that action at 10. Autoloaded, since
	 * every request reads it.
	 */
	public static function store_version() {
		update_option( self::OPT_REWRITE, self::REWRITE_VERSION, true );
	}

	/**
	 * Answer a badge request and end it, with the status, the headers and the body `serve()`
	 * decided. Every other request goes on untouched: one the badge's rule did not match, and one
	 * whose name is not a string, since a query string's `?wpcpm_badge[]=` outranks the rule's.
	 *
	 * Run on `parse_request`, before core runs the main query, so the name is read off core's
	 * request, where the rule's match has just put it: `get_query_var()` reads the main query,
	 * which does not exist yet.
	 */
	public static function handle() {
		if ( ! self::is_badge_request() ) {
			return;
		}

		$vars = is_array( $GLOBALS['wp']->query_vars ) ? $GLOBALS['wp']->query_vars : array();
		$name = isset( $vars[ self::QUERY_VAR ] ) ? $vars[ self::QUERY_VAR ] : '';

		if ( ! is_string( $name ) || '' === $name ) {
			return;
		}

		// What the visitor's browser or a cache holds of the image already, as WordPress hands the
		// request's headers over, slashed: answered 304 with no body when it is still the image.
		$held = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && is_string( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';

		$answer = self::serve( $name, '', $held );

		// Bytes a theme or a plugin left in an open buffer are not the badge's: sent along, they
		// would go out ahead of the SVG, the length would no longer be the image's own and the
		// image would not draw, so a buffer that holds any is emptied and closed. The first empty
		// buffer stops the loop, and it and every buffer outside it stay open: a page cache is a
		// buffer started before the plugin runs, empty when the badge answers, and it must see the
		// answer, the SVG and its headers, to store it; closed, no badge would ever be stored and
		// every visitor would reach PHP. A buffer that refuses to go ends the loop too, rather
		// than holding the request in it for ever.
		while ( ob_get_level() > 0 && ob_get_length() > 0 ) {
			if ( ! ob_end_clean() ) {
				break;
			}
		}

		status_header( $answer['status'] );

		foreach ( $answer['headers'] as $header ) {
			header( $header );
		}

		echo $answer['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- An SVG the class built from escaped parts, or nothing.
		exit;
	}

	/**
	 * Whether this request is the badge's: the one core matched to the badge's own rule. The query
	 * var is public, so `?wpcpm_badge=` reaches any page's request too, and answering one of those
	 * with an image would hand a cache an SVG to store under the page's address. `serve()` does not
	 * ask this; `handle()` does, before anything else.
	 *
	 * @return bool
	 */
	public static function is_badge_request() {
		return isset( $GLOBALS['wp'] ) && $GLOBALS['wp'] instanceof WP && self::RULE === $GLOBALS['wp']->matched_rule;
	}

	/**
	 * What a badge request is answered with, as data rather than sent, so that a suite can read
	 * it; `handle()` sends it.
	 *
	 * Metered first, before the switch or the store is read, the order every public route in this
	 * plugin keeps: a caller over its budget costs the site the ceiling's own read and no read of a
	 * sponsor. Then the switch, the name and whether its sponsor has a badge, each refused with
	 * the same 404. Every refusal says `no-cache`, so no cache in front of the site serves it again
	 * without asking the site first: a 404 answered before the switch went on, or before a sponsor
	 * had a badge, is not served once either has changed. The image names its own file: the address
	 * has no extension, and a save should still get a `.svg` file.
	 *
	 * The image is cached an hour, public, under an ETag that is a quoted hash of the image itself, so
	 * a new level or a new year is a new tag (1.123.22). A request whose If-None-Match names that tag,
	 * plain, weak or in a list, is answered 304 with the tag and the cache line and no body: asked
	 * only once the ceiling is claimed and the badge is found to be served at all, so a tag never
	 * answers for a badge that is gone.
	 *
	 * @param string $name          The requested name: a slug, or a slug and `-dark`.
	 * @param string $today         A `Y-m-d` date, or '' for today.
	 * @param string $if_none_match The request's If-None-Match, as cleaned, or ''.
	 * @return array `status` (int), `headers` (each a whole header line), `body` (the SVG, or '').
	 */
	public static function serve( $name, $today = '', $if_none_match = '' ) {
		if ( ! WPCPM_Ceiling::claim( WPCPM_Form_Guard::actor_key( self::CEILING ), 120, HOUR_IN_SECONDS ) ) {
			return array(
				'status'  => 429,
				'headers' => array( 'Retry-After: ' . HOUR_IN_SECONDS, self::no_cache() ),
				'body'    => '',
			);
		}

		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return self::not_found();
		}

		$found = self::resolve( $name );

		if ( null === $found || ! self::eligible( $found['row'] ) ) {
			return self::not_found();
		}

		$body  = self::svg( (string) $found['row']['tier'], self::year( $found['row'], $today ), $found['dark'] );
		$etag  = '"' . md5( $body ) . '"';
		$cache = 'Cache-Control: public, max-age=' . HOUR_IN_SECONDS;

		if ( self::holds( $if_none_match, $etag ) ) {
			return array(
				'status'  => 304,
				'headers' => array( 'ETag: ' . $etag, $cache ),
				'body'    => '',
			);
		}

		// The whole name goes through core's cleaner, not the slug alone: no quote or percent sign
		// reaches the header, and a slug that reads as an extension on its own ("zip") stays a name
		// rather than being renamed as a file of that type.
		$file = sanitize_file_name( 'wordpress-credits-' . (string) $found['row']['slug'] . ( $found['dark'] ? '-dark' : '' ) . '.svg' );

		return array(
			'status'  => 200,
			'headers' => array(
				'Content-Type: image/svg+xml; charset=utf-8',
				'Content-Disposition: inline; filename="' . $file . '"',
				$cache,
				'ETag: ' . $etag,
				'X-Content-Type-Options: nosniff',
				"Content-Security-Policy: default-src 'none'",
				'Content-Length: ' . strlen( $body ),
			),
			'body'    => $body,
		);
	}

	/**
	 * Whether a request's If-None-Match names an image's ETag: any of the tags it lists, each read
	 * trimmed and with a weak `W/` taken off, since the tag stands for the image's bytes and a weak
	 * match of those is a match.
	 *
	 * @param string $if_none_match The header, as cleaned, or ''.
	 * @param string $etag          The image's tag, quoted.
	 * @return bool
	 */
	private static function holds( $if_none_match, $etag ) {
		foreach ( explode( ',', (string) $if_none_match ) as $tag ) {
			$tag = trim( $tag );

			if ( 0 === strpos( $tag, 'W/' ) ) {
				$tag = substr( $tag, 2 );
			}

			if ( '' !== $tag && $tag === $etag ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The sponsor a requested name is the badge of, and which version.
	 *
	 * The exact slug first, so a company whose own slug ends in `-dark` gets its light badge at
	 * its own name; then, for a name ending in `-dark`, the sponsor at the slug without it, as the
	 * dark badge. Trimmed and lowercased first, as a slug is stored, so `Acme-Tools` is
	 * `acme-tools`; `-dark` alone names nobody. The name reaches here as the request spelled it,
	 * percent-encoding and all, and `WPCPM_Sponsors_Index::row_by_slug()` decodes it once and
	 * reads the one sponsor post of that name, so a slug core stored encoded is found however the
	 * request encoded it.
	 *
	 * @param string $name A slug, or a slug and `-dark`.
	 * @return array|null `row` (the sponsor's row) and `dark` (bool), or null.
	 */
	public static function resolve( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$row  = WPCPM_Sponsors_Index::row_by_slug( $name );

		if ( null !== $row ) {
			return array(
				'row'  => $row,
				'dark' => false,
			);
		}

		if ( strlen( $name ) > 5 && '-dark' === substr( $name, -5 ) ) {
			$row = WPCPM_Sponsors_Index::row_by_slug( substr( $name, 0, -5 ) );

			if ( null !== $row ) {
				return array(
					'row'  => $row,
					'dark' => true,
				);
			}
		}

		return null;
	}

	/**
	 * Whether a sponsor has a badge: Approved, with a recorded tier that includes one. The route
	 * and the card both ask this, so the card never shows an image the route would refuse.
	 *
	 * @param array $row A sponsor's row.
	 * @return bool
	 */
	public static function eligible( array $row ) {
		return WPCPM_Sponsors_Index::STATUS_APPROVED === (string) $row['status'] && WPCPM_Sponsor_Tiers::includes( (string) $row['tier'], 'badge' );
	}

	/**
	 * The year a badge names: the current year while the row's term runs or it holds none, and,
	 * once the term's last day has passed, that day's calendar year: the lapsed term's year, as
	 * spec section 6 asks. A term bought in October 2026 and running to 30 September 2027 names
	 * 2026 on its first day and 2027 from January, never a year that has not begun. The last day
	 * is still the term's, as `WPCPM_Sponsor_Tiers::effective()` counts it.
	 *
	 * @param array  $row   A sponsor's row, read for `tier_until`.
	 * @param string $today A `Y-m-d` date, or '' for today.
	 * @return string
	 */
	public static function year( array $row, $today = '' ) {
		$today = '' === (string) $today ? WPCPM_Sponsor_Tiers::today() : (string) $today;
		$until = isset( $row['tier_until'] ) ? (string) $row['tier_until'] : '';

		if ( WPCPM_Sponsor_Tiers::is_date( $until ) && strcmp( $until, $today ) < 0 ) {
			return substr( $until, 0, 4 );
		}

		return substr( $today, 0, 4 );
	}

	/**
	 * The badge as an SVG document: the WordPress mark on a brand-blue tile, the program's name in
	 * capitals, the tier's name and the year, on a light ground or, for a dark background, an ink one.
	 *
	 * Presentation attributes throughout and no `<style>` element, so the `default-src 'none'`
	 * policy it is served under leaves it drawn as it is; the one address in it is the SVG
	 * namespace, which is a name and never fetched.
	 *
	 * @param string $tier One of WPCPM_Sponsor_Tiers::KEYS.
	 * @param string $year The year the badge names.
	 * @param bool   $dark Whether this is the version for a dark background.
	 * @return string
	 */
	public static function svg( $tier, $year, $dark ) {
		$colors = $dark ? self::COLORS_DARK : self::COLORS_LIGHT;

		$parts = array(
			sprintf( '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" role="img" aria-labelledby="wpcpm-badge-title">', self::WIDTH, self::HEIGHT ),
			'<title id="wpcpm-badge-title">' . esc_html( self::name( $tier, $year ) ) . '</title>',
			// The frame, inset half a pixel so its one-pixel rule falls on whole pixels.
			sprintf( '<rect x=".5" y=".5" width="%1$d" height="%2$d" rx="8" fill="%3$s" stroke="%4$s"/>', self::WIDTH - 1, self::HEIGHT - 1, $colors['bg'], $colors['rule'] ),
			// The tile, and the mark's 122.523-unit square drawn 32 pixels wide at its center.
			sprintf( '<rect x="16" y="16" width="48" height="48" rx="10" fill="%s"/>', self::COLOR_TILE ),
			sprintf( '<g transform="translate(24 24) scale(0.26118)" fill="%1$s"><path d="%2$s"/></g>', self::COLOR_MARK, self::MARK_PATH ),
			'<g font-family="' . self::FONT_STACK . '">',
			sprintf( '<text x="80" y="33" font-size="11" font-weight="600" letter-spacing="1" fill="%s">WORDPRESS CREDITS</text>', $colors['eyebrow'] ),
			sprintf(
				'<text x="80" y="58" font-size="20" font-weight="600" fill="%1$s">%2$s <tspan font-weight="400" fill="%3$s">%4$s</tspan></text>',
				$colors['text'],
				esc_html( WPCPM_Sponsor_Tiers::label( $tier ) ),
				$colors['year'],
				esc_html( (string) $year )
			),
			'</g></svg>',
		);

		return implode( '', $parts );
	}

	/**
	 * A badge's address on this site, with no extension, as RULE reads it; the image still saves
	 * as a `.svg` file, under the name `serve()` gives it.
	 *
	 * @param string $slug The sponsor's slug.
	 * @param bool   $dark Whether it is the version for a dark background.
	 * @return string
	 */
	public static function url( $slug, $dark = false ) {
		return home_url( '/sponsor-badge/' . $slug . ( $dark ? '-dark' : '' ) );
	}

	/**
	 * A sponsor's badge address as the Sponsor Dashboard draws it (1.123.22): `url()` with a `v`
	 * argument naming the tier the badge draws and the year it names, `backer-2026` say, so a browser
	 * holding the image under the address asks for the new one as soon as either changes, rather than
	 * once its hour is up. The recorded tier, as `serve()` draws it: a term that has ended keeps its
	 * badge, so the effective tier, Friend, is not what the image says. The rule reads the path
	 * alone, and `v` is no query var of the badge's, so the argument changes nothing in routing. The
	 * embed code keeps the plain address: an embed pasted on a sponsor's site must keep working.
	 *
	 * @param array  $row   The sponsor's row, read for `slug`, `tier` and `tier_until`.
	 * @param bool   $dark  Whether it is the version for a dark background.
	 * @param string $today A `Y-m-d` date, or '' for today.
	 * @return string
	 */
	public static function versioned_url( array $row, $dark = false, $today = '' ) {
		$url = self::url( (string) $row['slug'], $dark );

		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'v=' . rawurlencode( (string) $row['tier'] . '-' . self::year( $row, $today ) );
	}

	/**
	 * The light badge's alternative text: the same words the Badge card gives its light
	 * image, made reachable so wherever else the badge appears - the Sponsor Dashboard's
	 * header (1.115.1) among them - it is described the same way as the card's own copy.
	 *
	 * @param array  $row   A sponsor's row, read for `tier`.
	 * @param string $today A `Y-m-d` date, or '' for today.
	 * @return string
	 */
	public static function light_alt( array $row, $today = '' ) {
		$name = self::name( (string) $row['tier'], self::year( $row, $today ) );

		/* translators: %s: the badge's name: the program, the tier and the year. */
		return sprintf( __( '%s badge, for a light background', 'wpcredits-program-manager' ), $name );
	}

	/**
	 * The card has no form and no handler, so it leaves no flash of its own.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function messages() {
		return array();
	}

	/**
	 * Badge: both versions on the grounds they are made for, the code that embeds the light one,
	 * linked to the Partners page, and a Copy button for it.
	 *
	 * Drawn only while the launch switch is on and the sponsor has a badge, decided as the route
	 * decides it. The embed links to the Partners page, or to the site's home while that page is
	 * not published. Today is read once, so the year and the note on a term that has ended speak of
	 * the same day. Folded until a flash names it, its row the tier and the year the badge names,
	 * and no action after the fold, since the Copy button is inside.
	 *
	 * @param string $record  Sponsor key.
	 * @param array  $context `can_manage`, `open`, `viewer`.
	 * @param string $today   A `Y-m-d` date, or '' for today; the dashboard passes none.
	 */
	public static function render( $record, array $context, $today = '' ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return;
		}

		$row = WPCPM_Sponsors_Index::row( $record );

		if ( null === $row || ! self::eligible( $row ) ) {
			return;
		}

		$today = '' === (string) $today ? WPCPM_Sponsor_Tiers::today() : (string) $today;
		$tier  = (string) $row['tier'];
		$slug  = (string) $row['slug'];
		$name  = self::name( $tier, self::year( $row, $today ) );
		$open  = isset( $context['open'] ) && self::CARD === $context['open'];

		// Behind class_exists(), as the listing and the tier card read the page, so the card still
		// draws, linking home, where the page class is not loaded.
		$page = class_exists( 'WPCPM_Sponsors_Page' ) ? WPCPM_Sponsors_Page::page_url() : '';
		$page = '' === $page ? home_url( '/' ) : $page;

		// What goes onto the sponsor's own site: the badge's own words, as the image carries them.
		$snippet = sprintf(
			'<a href="%1$s"><img src="%2$s" alt="%3$s" width="%4$d" height="%5$d"></a>',
			esc_url( $page ),
			esc_url( self::url( $slug ) ),
			esc_attr( $name ),
			self::WIDTH,
			self::HEIGHT
		);

		// The previews at the versioned address, so a new level shows on the card at once; the embed
		// above keeps the plain one (1.123.22).
		$versions = array(
			'light' => array(
				'src'     => self::versioned_url( $row, false, $today ),
				'alt'     => self::light_alt( $row, $today ),
				'caption' => __( 'On a light background', 'wpcredits-program-manager' ),
			),
			'dark'  => array(
				'src'     => self::versioned_url( $row, true, $today ),
				/* translators: %s: the badge's name: the program, the tier and the year. */
				'alt'     => sprintf( __( '%s badge, for a dark background', 'wpcredits-program-manager' ), $name ),
				'caption' => __( 'On a dark background', 'wpcredits-program-manager' ),
			),
		);

		WPCPM_Dashboard_Card::open(
			array(
				'key'   => self::CARD,
				'title' => __( 'Badge', 'wpcredits-program-manager' ),
				'open'  => $open,
				'state' => sprintf(
					/* translators: 1: the tier the badge names, 2: the year it names. */
					__( '%1$s badge for %2$s', 'wpcredits-program-manager' ),
					WPCPM_Sponsor_Tiers::label( $tier ),
					self::year( $row, $today )
				),
			)
		);
		echo '<p class="wpcpm-student__note">' . esc_html__( 'A badge for your own site, naming your tier and the current year. Both versions are served by the program\'s site, so they stay up to date: a change of tier shows here at once and on your own site within an hour.', 'wpcredits-program-manager' ) . '</p>';

		// A term that has ended keeps its badge, and the card says which year the badge names, so
		// the sponsor does not read the old year as a mistake.
		$left = WPCPM_Sponsor_Tiers::days_left( $row, $today );

		if ( null !== $left && $left < 0 ) {
			printf(
				'<p class="wpcpm-student__note">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: the tier the term was for, 2: the term's last day. */
						__( 'Your %1$s term ended on %2$s; the badge names that year until your program contact renews it.', 'wpcredits-program-manager' ),
						WPCPM_Sponsor_Tiers::label( $tier ),
						WPCPM_Sponsor_Tiers::format_day( (string) $row['tier_until'] )
					)
				)
			);
		}

		echo '<div class="wpcpm-sponsor-badge__pair">';

		foreach ( $versions as $version => $shown ) {
			printf(
				'<figure class="wpcpm-sponsor-badge__figure wpcpm-sponsor-badge__figure--%1$s"><img src="%2$s" alt="%3$s" width="%4$d" height="%5$d" /><figcaption>%6$s</figcaption></figure>',
				esc_attr( $version ),
				esc_url( $shown['src'] ),
				esc_attr( $shown['alt'] ),
				(int) self::WIDTH,
				(int) self::HEIGHT,
				esc_html( $shown['caption'] )
			);
		}

		echo '</div>';
		printf( '<label for="wpcpm-sponsor-badge-embed" class="wpcpm-sponsor-badge__label">%s</label>', esc_html__( 'Embed code', 'wpcredits-program-manager' ) );
		printf( '<textarea id="wpcpm-sponsor-badge-embed" class="wpcpm-sponsor-badge__embed" rows="3" readonly="readonly">%s</textarea>', esc_textarea( $snippet ) );
		// The button carries its own words for the script to say, so assets/js/copy.js holds none.
		printf(
			'<p class="wpcpm-sponsor-badge__actions"><button type="button" class="wpcpm-button wpcpm-button--secondary" data-wpcpm-copy="#wpcpm-sponsor-badge-embed" data-wpcpm-copied="%1$s" data-wpcpm-select-hint="%2$s">%3$s</button> <span class="wpcpm-copy__status" role="status" aria-live="polite"></span></p>',
			esc_attr__( 'Copied.', 'wpcredits-program-manager' ),
			esc_attr__( 'Selected. Copy it with your keyboard.', 'wpcredits-program-manager' ),
			esc_html__( 'Copy the embed code', 'wpcredits-program-manager' )
		);
		WPCPM_Dashboard_Card::close();
	}

	/**
	 * The badge's name, as its title and the images' alternative text read it: the program, the
	 * tier and the year. The program's name is the mark's own word and stays in English.
	 *
	 * @param string $tier One of WPCPM_Sponsor_Tiers::KEYS.
	 * @param string $year The year the badge names.
	 * @return string
	 */
	private static function name( $tier, $year ) {
		return 'WordPress Credits ' . WPCPM_Sponsor_Tiers::label( $tier ) . ' ' . $year;
	}

	/**
	 * The one refusal: a 404 with an empty body, whatever the reason.
	 *
	 * @return array As `serve()` answers.
	 */
	private static function not_found() {
		return array(
			'status'  => 404,
			'headers' => array( self::no_cache() ),
			'body'    => '',
		);
	}

	/**
	 * The header every refusal carries: a cache must ask the site again before it serves the
	 * refusal a second time, so a 404 never outlives its reason.
	 *
	 * @return string
	 */
	private static function no_cache() {
		return 'Cache-Control: no-cache, must-revalidate, max-age=0';
	}
}
