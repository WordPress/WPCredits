<?php
/**
 * The sponsor tier ladder: one table that decides what a tier's benefits are.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Five tiers, one table, one effective tier.
 *
 * `KEYS` holds the five tiers lowest first: Friend (the floor - every sponsor with no
 * recorded tier, and the fallback for anything malformed), Supporter, Backer, Champion and
 * Partner. Every later gate - the entry on the Partners page, the cap on posts, the badge, the annual program report, the events a tier is invited to, the Track Builder
 * track a Partner sponsors - is meant to ask this class rather than decide a benefit on its
 * own: `table()` is the ladder written out once, and `includes()` is the one place, apart from
 * the handful of accessors beside it, that reads it. A benefit `includes()` does not recognize
 * answers false from its own default branch, so a gate asking about something new fails closed
 * until this table is taught the benefit, never the other way round.
 *
 * A sponsor's recorded tier and its effective one can differ, so `effective()` is the one
 * function every later gate is meant to call, never a record's raw `tier` field: a term that
 * has ended still leaves the tier written down until a manager changes it or the cleaner runs.
 * The last day of a term is still the tier; the day after, `effective()` answers Friend
 * (spec section 4).
 *
 * The whole ladder is gated by two settings this class alone reads: `live()`
 * (`sponsor_tiers_live`, off by default) is the switch every sponsor-facing surface must check
 * before rendering anything the ladder decided, and `grace_until()` (`sponsor_tier_grace_until`,
 * defaulting to `GRACE_DEFAULT`) is the last day the daily run leaves alone an offer or a post
 * the tier does not cover. Both settings are in `WPCPM_Settings::defaults()`, and an absent
 * switch still reads as off and an absent or malformed date as `GRACE_DEFAULT`, so neither answer
 * depends on the defaults having been merged in.
 *
 * Pure apart from the two settings reads and `wp_date()`: no `WP_Error`, no I/O, no post read,
 * and every function that reads "today" takes an optional `$today`, so a caller never depends
 * on the clock a request happens to run on.
 *
 * `bin/test-sponsor-policy.php` scans this file, with every other sponsor file, for a sponsor
 * ID compared with `===` and for the sponsors store or Airtable named here: neither ever is.
 */
final class WPCPM_Sponsor_Tiers {

	const MENTOR    = 'mentor';
	const SUPPORTER = 'supporter';
	const BACKER    = 'backer';
	const CHAMPION  = 'champion';
	const PARTNER   = 'partner';

	/** The five tiers, lowest first. A tier's `rank()` is its position in this list. */
	const KEYS = array( self::MENTOR, self::SUPPORTER, self::BACKER, self::CHAMPION, self::PARTNER );

	/**
	 * The application's "not sure yet", a value of `tier_requested` alone and never a tier. The
	 * application's tier question offers it beside the five tiers, and approval copies the
	 * answer into the record's `tier_requested`.
	 */
	const UNSURE = 'unsure';

	/** The launch switch's setting key, off in `WPCPM_Settings::defaults()`. */
	const OPT_LIVE = 'sponsor_tiers_live';

	/** The grace date's setting key, `GRACE_DEFAULT`'s day in `WPCPM_Settings::defaults()`. */
	const OPT_GRACE = 'sponsor_tier_grace_until';

	/** `grace_until()`'s answer when the setting is absent or malformed. */
	const GRACE_DEFAULT = '2026-12-31';

	/** How many days before a term ends the card and the mail speak up. */
	const NOTICE_DAYS = 30;

	// How a tier's sponsors are listed on the Partners page: a Friend by name, every tier above it by
	// logo, name and description. The partner page lists every partner in the same format, in
	// alphabetical order rather than by level (1.123.18), so no kind ranks one tier above another.
	const LISTING_NAME = 'name';
	const LISTING_LOGO = 'logo';

	// The sections a tier's annual program report carries.
	const REPORT_PROGRAM   = 'program';
	const REPORT_COST_LINE = 'cost_line';
	const REPORT_TRACK     = 'track';

	// The events a tier is invited to.
	const EVENT_PARTNERS_SESSION = 'partners_session';
	const EVENT_CO_HOSTED        = 'co_hosted_event';
	const EVENT_LAUNCH           = 'launch_communications';

	/**
	 * Every benefit `includes()` answers for. Asking about one not listed here gets `false`
	 * from `includes()`'s own default branch, never a fatal.
	 *
	 * @var string[]
	 */
	const BENEFITS = array( 'logo', 'description', 'funded_line', 'badge', 'events', 'launch_communications', 'track', 'exclusive_offers', 'report_program', 'report_cost_line', 'report_track' );

	/**
	 * The ladder, cell by cell: every tier's label, how the Partners page lists it, its cap on posts
	 * (`null` reads as any number), whether it carries a badge, which sections of the annual program
	 * report it is named in, which events it is invited to, and whether it carries a Track Builder
	 * track.
	 *
	 * The partner page is the source of truth for the levels (the owner, 21 October 2026). Products
	 * are offered at any level, separately from the levels, so no tier caps live offers and the table
	 * holds no such cap; an Administrator reviews each offer instead (`WPCPM_Sponsor_Offer_Review`).
	 * The posts follow the page: Backer's one self-written educational post, Champion's second, and a
	 * Partner has everything in Champion.
	 *
	 * @return array<string, array> KEYS order, each row keyed label, listing, posts, badge, reports,
	 *                               events, track.
	 */
	public static function table() {
		return array(
			self::MENTOR    => array(
				'label'   => __( 'Friend', 'wpcredits-program-manager' ),
				'listing' => self::LISTING_NAME,
				'posts'   => 0,
				'badge'   => false,
				'reports' => array(),
				'events'  => array(),
				'track'   => false,
			),
			self::SUPPORTER => array(
				'label'   => __( 'Supporter', 'wpcredits-program-manager' ),
				'listing' => self::LISTING_LOGO,
				'posts'   => 0,
				'badge'   => true,
				'reports' => array( self::REPORT_PROGRAM ),
				'events'  => array(),
				'track'   => false,
			),
			self::BACKER    => array(
				'label'   => __( 'Backer', 'wpcredits-program-manager' ),
				'listing' => self::LISTING_LOGO,
				'posts'   => 1,
				'badge'   => true,
				'reports' => array( self::REPORT_PROGRAM, self::REPORT_COST_LINE ),
				'events'  => array(),
				'track'   => false,
			),
			self::CHAMPION  => array(
				'label'   => __( 'Champion', 'wpcredits-program-manager' ),
				'listing' => self::LISTING_LOGO,
				'posts'   => 2,
				'badge'   => true,
				'reports' => array( self::REPORT_PROGRAM, self::REPORT_COST_LINE ),
				'events'  => array( self::EVENT_PARTNERS_SESSION, self::EVENT_CO_HOSTED ),
				'track'   => false,
			),
			self::PARTNER   => array(
				'label'   => __( 'Partner', 'wpcredits-program-manager' ),
				'listing' => self::LISTING_LOGO,
				'posts'   => 2,
				'badge'   => true,
				'reports' => array( self::REPORT_PROGRAM, self::REPORT_COST_LINE, self::REPORT_TRACK ),
				'events'  => array( self::EVENT_PARTNERS_SESSION, self::EVENT_CO_HOSTED, self::EVENT_LAUNCH ),
				'track'   => true,
			),
		);
	}

	/**
	 * The table's row for a tier; an unknown tier is a Friend, the ladder's floor, so
	 * every gate fails closed.
	 *
	 * @param string $tier One of KEYS, or anything else.
	 * @return array
	 */
	private static function row( $tier ) {
		$table = self::table();

		return isset( $table[ $tier ] ) ? $table[ $tier ] : $table[ self::MENTOR ];
	}

	/**
	 * Every tier's label, in KEYS order.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		$out = array();

		foreach ( self::table() as $key => $row ) {
			$out[ $key ] = $row['label'];
		}

		return $out;
	}

	/**
	 * One tier's label.
	 *
	 * @param string $tier One of KEYS, or anything else.
	 * @return string
	 */
	public static function label( $tier ) {
		return self::row( (string) $tier )['label'];
	}

	/**
	 * Whether a value is one of the five tier keys, strictly.
	 *
	 * @param mixed $value Anything.
	 * @return bool
	 */
	public static function is_tier( $value ) {
		return is_string( $value ) && in_array( $value, self::KEYS, true );
	}

	/**
	 * A tier's position in the ladder: 0 for Friend up to 4 for Partner. An unknown
	 * tier is the floor.
	 *
	 * @param string $tier One of KEYS, or anything else.
	 * @return int
	 */
	public static function rank( $tier ) {
		$rank = array_search( (string) $tier, self::KEYS, true );

		return false === $rank ? 0 : (int) $rank;
	}

	/**
	 * The key one rung above a tier, or '' at the top of the ladder and for an unknown tier.
	 *
	 * @param string $tier One of KEYS, or anything else.
	 * @return string
	 */
	public static function next( $tier ) {
		if ( ! self::is_tier( $tier ) ) {
			return '';
		}

		$rank = self::rank( $tier );

		return isset( self::KEYS[ $rank + 1 ] ) ? self::KEYS[ $rank + 1 ] : '';
	}

	/**
	 * How many posts a tier may have open at once, or null for any number: a Friend's and a
	 * Supporter's 0, a Backer's 1, a Champion's and a Partner's 2. An unknown tier reads as a
	 * Friend's 0.
	 *
	 * @param string $tier One of KEYS, or anything else.
	 * @return int|null
	 */
	public static function post_cap( $tier ) {
		return self::row( (string) $tier )['posts'];
	}

	/**
	 * How the Partners page lists a tier's sponsors: one of the LISTING_* values.
	 *
	 * @param string $tier One of KEYS, or anything else.
	 * @return string
	 */
	public static function listing( $tier ) {
		return self::row( (string) $tier )['listing'];
	}

	/**
	 * The events a tier is invited to.
	 *
	 * @param string $tier One of KEYS, or anything else.
	 * @return string[] EVENT_* keys.
	 */
	public static function events( $tier ) {
		return self::row( (string) $tier )['events'];
	}

	/**
	 * One sentence describing an event, for the tier card and the mail.
	 *
	 * @param string $event One of the EVENT_* values.
	 * @return string '' for an event this class does not know.
	 */
	public static function event_label( $event ) {
		$labels = array(
			self::EVENT_PARTNERS_SESSION => __( 'An invitation to the annual education partners session', 'wpcredits-program-manager' ),
			self::EVENT_CO_HOSTED        => __( 'A webinar or event co-hosted with the education program', 'wpcredits-program-manager' ),
			self::EVENT_LAUNCH           => __( 'Named in launch communications', 'wpcredits-program-manager' ),
		);

		return isset( $labels[ $event ] ) ? $labels[ $event ] : '';
	}

	/**
	 * Whether a tier's row carries a benefit. An unknown tier or an unknown benefit is always
	 * false, so a gate asking about something new fails closed until this method is taught it.
	 *
	 * @param string $tier    One of KEYS, or anything else.
	 * @param string $benefit One of the BENEFITS values.
	 * @return bool
	 */
	public static function includes( $tier, $benefit ) {
		if ( ! self::is_tier( $tier ) ) {
			return false;
		}

		$row = self::row( $tier );

		switch ( (string) $benefit ) {
			case 'logo':
			case 'description':
				// Every tier listed by logo, Supporter and up: the Partners page prints each of them
				// in one format, a logo, the name and a description of the same length, so the
				// profile asks for a description wherever the page prints one and both read this.
				return self::LISTING_LOGO === $row['listing'];
			case 'funded_line':
				// The program names a Backer and up for the part of the program it funds, in the
				// program's communications and in its report; the Partners page prints no such line.
				return self::rank( $tier ) >= self::rank( self::BACKER );
			case 'badge':
				return (bool) $row['badge'];
			case 'events':
				return ! empty( $row['events'] );
			case 'launch_communications':
				return in_array( self::EVENT_LAUNCH, $row['events'], true );
			case 'track':
			case 'exclusive_offers':
				return (bool) $row['track'];
			case 'report_program':
				return in_array( self::REPORT_PROGRAM, $row['reports'], true );
			case 'report_cost_line':
				return in_array( self::REPORT_COST_LINE, $row['reports'], true );
			case 'report_track':
				return in_array( self::REPORT_TRACK, $row['reports'], true );
		}

		// A benefit the table does not know is not included: a gate asking for something new
		// fails closed until the table names it.
		return false;
	}

	/**
	 * The tier every gate is meant to read: a sponsor's recorded tier, unless its term has a
	 * valid end date that is before today, in which case the sponsor has fallen back to Mentor
	 * sponsor (spec section 4).
	 *
	 * @param array  $record A sponsor row, read for `tier` and `tier_until`.
	 * @param string $today  A `Y-m-d` date, or '' for `today()`.
	 * @return string One of KEYS.
	 */
	public static function effective( array $record, $today = '' ) {
		$tier = isset( $record['tier'] ) ? (string) $record['tier'] : '';

		if ( ! self::is_tier( $tier ) ) {
			return self::MENTOR;
		}

		$until = isset( $record['tier_until'] ) ? (string) $record['tier_until'] : '';

		if ( ! self::is_date( $until ) ) {
			// No term (or one the cleaner never wrote): the tier stands until a manager
			// changes it.
			return $tier;
		}

		// The last day of the term is still the tier; the day after it is a Friend
		// (spec section 4).
		return strcmp( $until, self::day( $today ) ) >= 0 ? $tier : self::MENTOR;
	}

	/**
	 * How many days are left on a sponsor's term.
	 *
	 * @param array  $record A sponsor row, read for `tier_until`.
	 * @param string $today  A `Y-m-d` date, or '' for `today()`.
	 * @return int|null Null with no valid term; 0 on the last day; negative once it has passed.
	 */
	public static function days_left( array $record, $today = '' ) {
		$until = isset( $record['tier_until'] ) ? (string) $record['tier_until'] : '';

		if ( ! self::is_date( $until ) ) {
			return null;
		}

		return (int) round( ( strtotime( $until . ' 00:00:00 UTC' ) - strtotime( self::day( $today ) . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );
	}

	/**
	 * What a tier includes, for the Sponsorship card: the partner page's bullets for the level, in
	 * its words, as the items of its checklist, and apart from them the lead the page sets above
	 * them from Supporter up, "Everything in <the level below>, plus:", which the card prints as its
	 * own line over the list, never as a checked item (1.123.18); a Friend, the first level, has
	 * none. No line offers products: the page offers them at any level, separately from the levels.
	 *
	 * @param string $tier One of KEYS, or anything else.
	 * @return array `lead`, '' for a Friend, and `items`, the lines. An unknown tier reads as a
	 *               Friend.
	 */
	public static function describe( $tier ) {
		$items = array(
			self::MENTOR    => array(
				__( 'Company name on the partner page', 'wpcredits-program-manager' ),
				__( 'Mention in the mentor feature', 'wpcredits-program-manager' ),
				__( 'Acknowledgment in the annual partner thank you post', 'wpcredits-program-manager' ),
			),
			self::SUPPORTER => array(
				__( 'Logo and description on the partner page', 'wpcredits-program-manager' ),
				__( 'A badge for your own site', 'wpcredits-program-manager' ),
				__( 'The annual program report', 'wpcredits-program-manager' ),
			),
			self::BACKER    => array(
				__( 'Named for the part of the program you fund in program communications', 'wpcredits-program-manager' ),
				__( 'A report covering that cost line', 'wpcredits-program-manager' ),
				__( 'One self-written educational post about the sponsored product', 'wpcredits-program-manager' ),
			),
			self::CHAMPION  => array(
				__( 'A second self-written post with factual, value-neutral information about additional products', 'wpcredits-program-manager' ),
				self::event_label( self::EVENT_PARTNERS_SESSION ),
				self::event_label( self::EVENT_CO_HOSTED ),
			),
			self::PARTNER   => array(
				__( 'Named as program partner on the program you fund, with the exclusive role in that program', 'wpcredits-program-manager' ),
				self::event_label( self::EVENT_LAUNCH ),
				__( 'A report specific to that program', 'wpcredits-program-manager' ),
			),
		);
		$tier  = isset( $items[ $tier ] ) ? $tier : self::MENTOR;
		$below = array_search( $tier, self::KEYS, true );

		return array(
			'lead'  => $below > 0 ? self::everything_in( self::KEYS[ $below - 1 ] ) : '',
			'items' => $items[ $tier ],
		);
	}

	/**
	 * The lead over a tier's checklist above Friend: what the level below has, as the partner page
	 * says it.
	 *
	 * @param string $below The tier one rung down.
	 * @return string
	 */
	private static function everything_in( $below ) {
		return sprintf(
			/* translators: %s: the name of the tier one rung down, for example Friend. */
			__( 'Everything in %s, plus:', 'wpcredits-program-manager' ),
			self::label( $below )
		);
	}

	/**
	 * One sentence on what the tier above adds, for the upsell line on a tier card, from the same
	 * lines `describe()` gives.
	 *
	 * @param string $tier One of KEYS, or anything else.
	 * @return string '' for Partner, the top of the ladder, and for an unknown tier.
	 */
	public static function next_line( $tier ) {
		$lines = array(
			self::MENTOR    => __( 'Supporter adds your logo and description on the partner page, a badge for your own site and the annual program report.', 'wpcredits-program-manager' ),
			self::SUPPORTER => __( 'Backer names your company for the part of the program you fund in program communications and adds a report covering that cost line and one self-written educational post about the sponsored product.', 'wpcredits-program-manager' ),
			self::BACKER    => __( 'Champion adds a second self-written post with factual, value-neutral information about additional products, an invitation to the annual education partners session and a webinar or event co-hosted with the education program.', 'wpcredits-program-manager' ),
			self::CHAMPION  => __( 'Partner adds a complete program funded end to end, with your company named as its program partner and in launch communications, the exclusive role in that program and a report specific to that program.', 'wpcredits-program-manager' ),
		);

		return isset( $lines[ $tier ] ) ? $lines[ $tier ] : '';
	}

	/**
	 * The launch switch: whether the tier ladder is live for sponsor-facing surfaces.
	 *
	 * @return bool
	 */
	public static function live() {
		return ! empty( WPCPM_Settings::get_value( self::OPT_LIVE ) );
	}

	/**
	 * The last day the daily run leaves alone an offer or a post the tier does not cover, in
	 * `Y-m-d`. An absent or malformed setting reads as `GRACE_DEFAULT`.
	 *
	 * @return string
	 */
	public static function grace_until() {
		$value = (string) WPCPM_Settings::get_value( self::OPT_GRACE );

		return self::is_date( $value ) ? $value : self::GRACE_DEFAULT;
	}

	/**
	 * Whether a date is on or before the grace date, inclusive.
	 *
	 * @param string $today A `Y-m-d` date, or '' for `today()`.
	 * @return bool
	 */
	public static function in_grace( $today = '' ) {
		return strcmp( self::day( $today ), self::grace_until() ) <= 0;
	}

	/**
	 * Today, as the calendar day the rest of this class compares dates by.
	 *
	 * @return string `Y-m-d`.
	 */
	public static function today() {
		return (string) wp_date( 'Y-m-d' );
	}

	/**
	 * A `Y-m-d` date, written out the way the site writes a calendar day: its own date format,
	 * read at noon in UTC and written in UTC, so that no timezone shifts the day into its neighbor.
	 * Written in the site's zone, noon UTC is already the next day on a site twelve or more hours
	 * ahead, and the term's last day would print as the day after it. The daily run's mail, the
	 * Sponsors screen, the Sponsorship card and the Administrator Dashboard print this same day, so
	 * every caller reads it from here rather than formatting it again: the Collaboration Agreement
	 * card's row writes the day an agreement was accepted with it too.
	 *
	 * @param string $ymd A `Y-m-d` date.
	 * @return string
	 */
	public static function format_day( $ymd ) {
		return (string) wp_date( (string) get_option( 'date_format', 'F j, Y' ), strtotime( $ymd . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * A given date, or today's when none was given.
	 *
	 * @param string $today A `Y-m-d` date, or '' to mean "today".
	 * @return string
	 */
	private static function day( $today ) {
		return '' !== (string) $today ? (string) $today : self::today();
	}

	/**
	 * Whether a value is a real calendar date in `Y-m-d` form: the right shape and a day that
	 * exists, so 29 February only passes in a leap year.
	 *
	 * @param mixed $value Anything.
	 * @return bool
	 */
	public static function is_date( $value ) {
		$value = (string) $value;

		if ( ! preg_match( '/\A\d{4}-\d{2}-\d{2}\z/', $value ) ) {
			return false;
		}

		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $value ) );

		return checkdate( $m, $d, $y );
	}
}
