<?php
/**
 * The public listing of the program's sponsors, the partners of the Partners page: the Approved
 * sponsors in one list, in alphabetical order by name rather than by tier, as the `wpcpm/sponsors`
 * block, the landing strip and the Sponsors screen's handbook box draw them. The partner page lists
 * every partner in the same format, with descriptions of the same length (1.123.18): a Friend by
 * name, every tier above it by logo, name and description, with no tier heading, no prominence and
 * no line naming what a sponsor funds. The one reader of the store for the listing drawn on a page -
 * the block, the strip and the handbook box; the feed reads the store separately, for its own
 * public route.
 *
 * The full list ends with the upcoming events, the approved events not over yet of the sponsors
 * that offer them and of the program itself, as the event store's public reader gives them
 * (`WPCPM_Sponsor_Event::public_upcoming()`): nothing a joiner alone should see, and nothing at all
 * while the switch is off, a program manager's preview included. The strip and the handbook HTML
 * carry none.
 *
 * Nothing about the tiers reaches the public before launch: `visible()` answers true for the
 * public only while the launch switch is on. A signed-in program manager sees the listing
 * while it is off, under a note that says so, so the draft page and the strip can be checked
 * before the day. Page caches never store a signed-in response, so the preview stays theirs, as
 * does the way in under the upcoming events, which names a signed-in viewer's own page, while the
 * page a cache keeps for every visitor holds the button to the login screen (`way_in()`).
 * The block's editor script is a program manager's alone, so no one else's editor offers the
 * block (see `register()`).
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The listing, the block and the handbook HTML.
 */
final class WPCPM_Sponsors_Listing {

	const BLOCK = 'wpcpm/sponsors';

	const VARIANT_FULL  = 'full';
	const VARIANT_STRIP = 'strip';
	const VARIANTS      = array( self::VARIANT_FULL, self::VARIANT_STRIP );

	/**
	 * Hooks: the block, on `init`.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * The block, server-rendered, from its directory: registered for everyone, since the front
	 * end and the block renderer need its render callback, with its editor script a program
	 * manager's alone.
	 */
	public static function register() {
		$block_dir = WPCPM_PLUGIN_DIR . 'blocks/sponsors';

		if ( ! function_exists( 'register_block_type' ) || ! file_exists( $block_dir . '/block.json' ) ) {
			return;
		}

		register_block_type( $block_dir, array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );

		// The editor script is a program manager's alone, whatever the switch says: a sponsor
		// member with posting on writes in the block editor, the block's settings there name the
		// program's tiers, and a sponsor has no use for the program's listing in a sponsor post.
		// Without the script an editor never registers the block, so its inserter never lists it,
		// and no other block is touched; the block does not ask core to register it in the editor
		// by itself (`supports.autoRegister`). Not the allowed-blocks filter: to take one block out
		// of "every block" it has to name every block the server knows, which would hide any block
		// registered in JavaScript alone. The user is known here: this runs on `init` at 10, and
		// core sets the current user just before `init` fires.
		if ( current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			return;
		}

		$type = WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK );

		if ( $type instanceof WP_Block_Type ) {
			$type->editor_script_handles = array();

			// Core before 6.1, below this plugin's floor, kept the one handle in a property of its own.
			if ( property_exists( $type, 'editor_script' ) ) {
				$type->editor_script = null;
			}
		}
	}

	/**
	 * Whether the listing may be drawn for this request: the switch, or a program manager.
	 *
	 * @return bool
	 */
	public static function visible() {
		return WPCPM_Sponsor_Tiers::live() || current_user_can( WPCPM_Roles::CAP_MANAGE );
	}

	/**
	 * Whether what is drawn is a manager's preview rather than the public listing.
	 *
	 * @return bool
	 */
	public static function preview_only() {
		return ! WPCPM_Sponsor_Tiers::live() && current_user_can( WPCPM_Roles::CAP_MANAGE );
	}

	/**
	 * The Approved sponsors at or above a floor, in one list in alphabetical order by name, whatever
	 * their tier: case aside, accents aside (remove_accents() runs before the compare, so "Émile"
	 * sorts by "E" rather than after every plain ASCII letter) and digits in natural order, so "2"
	 * comes before "10". Two names that compare the same keep the order of their spelling, then of
	 * their keys, so the list never depends on the order the store read them in.
	 *
	 * A row with no name is skipped rather than printed as an empty link: nothing about it can
	 * be shown or read, so it is not a sponsor the listing has anything to say about.
	 * A test sponsor is skipped too (1.123.18), whatever its tier: it is never listed publicly.
	 *
	 * The logo records of the sponsors it prints a logo for are read in one query before the first
	 * entry (`prime_logos()`), where each entry would cost one: each record is an option of its own,
	 * not autoloaded, and the strip is drawn on the front page.
	 *
	 * @param string $floor Nominally one of WPCPM_Sponsor_Tiers::KEYS; anything else, including
	 *                      a value that is not even a string, is the ladder's floor.
	 * @param string $today `Y-m-d`, or '' for today.
	 * @return array[] Each `tier` (the effective tier), `listing` (how the tier is listed, a name or
	 *                 a logo), `name`, `slug`, `website`, `logo` (an address or '', and '' for a
	 *                 tier listed by name alone), `width`, `height` (0 without a logo), `white`
	 *                 (true when `logo` is the white attachment rather than the color one) and
	 *                 `line` (the description, or '').
	 */
	public static function sponsors( $floor = WPCPM_Sponsor_Tiers::MENTOR, $today = '' ) {
		$floor      = WPCPM_Sponsor_Tiers::is_tier( $floor ) ? $floor : WPCPM_Sponsor_Tiers::MENTOR;
		$floor_rank = WPCPM_Sponsor_Tiers::rank( $floor );
		$listed     = array();

		foreach ( WPCPM_Sponsors_Index::approved() as $record => $row ) {
			// A test sponsor is never listed publicly (1.123.18): not on the Partners page, in the
			// strip or its count, nor in the handbook listing, all of which read this list.
			if ( '' === trim( (string) $row['name'] ) || WPCPM_Sponsors_Index::is_test( $row ) ) {
				continue;
			}

			$tier = WPCPM_Sponsor_Tiers::effective( $row, $today );

			if ( WPCPM_Sponsor_Tiers::rank( $tier ) < $floor_rank ) {
				continue;
			}

			$listed[ (string) $record ] = array( $row, $tier );
		}

		self::prime_logos( $listed );

		$entries = array();

		foreach ( $listed as $record => $listing ) {
			$entries[] = array( (string) $record, self::entry( (string) $record, $listing[0], $listing[1] ) );
		}

		// usort() is not stable before PHP 8, so a tie falls back on the spelling, then on the key.
		usort(
			$entries,
			static function ( $a, $b ) {
				$by_name = strnatcasecmp( remove_accents( $a[1]['name'] ), remove_accents( $b[1]['name'] ) );

				if ( 0 !== $by_name ) {
					return $by_name;
				}

				$by_spelling = strcmp( $a[1]['name'], $b[1]['name'] );

				return 0 !== $by_spelling ? $by_spelling : strcmp( $a[0], $b[0] );
			}
		);

		return array_column( $entries, 1 );
	}

	/**
	 * Read in one query the logo records `entry()` will ask for, where it would ask one at a time:
	 * every sponsor listed but those of a tier listed by name alone, whose logo is never read.
	 *
	 * @param array $listed Sponsor key => the row and the effective tier, as `sponsors()` reads them.
	 */
	private static function prime_logos( array $listed ) {
		$options = array();

		foreach ( $listed as $record => $listing ) {
			if ( WPCPM_Sponsor_Tiers::LISTING_NAME !== WPCPM_Sponsor_Tiers::listing( $listing[1] ) ) {
				$options[] = WPCPM_Sponsors_Index::logo_option( (string) $record );
			}
		}

		if ( array() !== $options ) {
			wp_prime_option_caches( $options );
		}
	}

	/**
	 * One sponsor as the listing prints it: the public facts alone.
	 *
	 * A tier listed by name alone prints no logo, so its logo record is not read at all. Otherwise
	 * it reads the logo record itself, not display_logo() (the feed's and the cards' own reader):
	 * the color attachment first, resolved to the medium size with its dimensions so the browser
	 * reserves the space before the file arrives. With no color logo, or one whose attachment is
	 * gone, the white attachment takes its place and the entry says so (`white`), so item() can
	 * mark it for the ground the theme puts behind it. Neither attachment resolving to a real
	 * file is no logo at all - the sponsor prints as a text item, the same as one that never
	 * uploaded a logo.
	 *
	 * @param string $record The sponsor's key.
	 * @param array  $row    The row.
	 * @param string $tier   The effective tier.
	 * @return array
	 */
	private static function entry( $record, array $row, $tier ) {
		$logo   = WPCPM_Sponsor_Tiers::LISTING_NAME === WPCPM_Sponsor_Tiers::listing( $tier )
			? array(
				'colour' => 0,
				'white'  => 0,
			)
			: WPCPM_Sponsors_Index::logo_record( $record );
		$src    = '';
		$width  = 0;
		$height = 0;
		$white  = false;

		if ( (int) $logo['colour'] > 0 ) {
			$image = wp_get_attachment_image_src( (int) $logo['colour'], 'medium' );

			if ( is_array( $image ) && ! empty( $image[0] ) ) {
				$src    = (string) $image[0];
				$width  = (int) $image[1];
				$height = (int) $image[2];
			}
		}

		if ( '' === $src && (int) $logo['white'] > 0 ) {
			$image = wp_get_attachment_image_src( (int) $logo['white'], 'medium' );

			if ( is_array( $image ) && ! empty( $image[0] ) ) {
				$src    = (string) $image[0];
				$width  = (int) $image[1];
				$height = (int) $image[2];
				$white  = true;
			}
		}

		return array(
			'tier'    => (string) $tier,
			'listing' => WPCPM_Sponsor_Tiers::listing( $tier ),
			'name'    => (string) $row['name'],
			'slug'    => (string) $row['slug'],
			'website' => (string) $row['website'],
			'logo'    => $src,
			'width'   => $width,
			'height'  => $height,
			'white'   => $white,
			'line'    => self::line( $row, $tier ),
		);
	}

	/**
	 * The one line under a sponsor: its description, from Supporter up, else nothing.
	 *
	 * The description is printed where the tier ladder's `description` benefit says, the answer
	 * the profile card asks before it draws the box, so the card never asks for words this line
	 * does not print. The funded line is not printed: the program names a sponsor for what it funds
	 * in its communications and its report, and the partner page lists every partner in the same
	 * format. A Partner is named as program partner on the track it funds, on the track and on its
	 * students' Student Report Cards (`WPCPM_Track_Partners`), not here.
	 *
	 * @param array  $row  The row.
	 * @param string $tier The effective tier.
	 * @return string
	 */
	private static function line( array $row, $tier ) {
		return WPCPM_Sponsor_Tiers::includes( $tier, 'description' ) ? trim( (string) $row['description'] ) : '';
	}

	/**
	 * A website as a link target: with a scheme, or nothing.
	 *
	 * @param string $website What the record holds.
	 * @return string
	 */
	private static function href( $website ) {
		$website = trim( (string) $website );

		if ( '' === $website ) {
			return '';
		}

		return preg_match( '#^https?://#i', $website ) ? $website : 'https://' . $website;
	}

	/**
	 * The block's render callback: the listing inside the block's own wrapper.
	 *
	 * @param array $attributes `variant` and `floor`.
	 * @return string '' while nothing may be drawn.
	 */
	public static function render_block( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$html       = self::render( $attributes );

		if ( '' === $html ) {
			return '';
		}

		$variant = self::variant( $attributes );

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( array( 'class' => 'wpcpm-sponsors wpcpm-sponsors--' . $variant ) ),
			$html
		);
	}

	/**
	 * The listing's inner HTML for a variant, or '' while nothing may be drawn or there is
	 * nothing to draw.
	 *
	 * @param array  $args  `variant` (`full` or `strip`) and `floor` (a tier key).
	 * @param string $today `Y-m-d`, or '' for today.
	 * @return string
	 */
	public static function render( array $args, $today = '' ) {
		if ( ! self::visible() ) {
			return '';
		}

		$variant = self::variant( $args );
		$floor   = isset( $args['floor'] ) && WPCPM_Sponsor_Tiers::is_tier( $args['floor'] ) ? $args['floor'] : WPCPM_Sponsor_Tiers::MENTOR;
		$html    = self::VARIANT_STRIP === $variant ? self::strip( $today ) : self::full( $floor, $today );

		if ( '' === $html ) {
			return '';
		}

		if ( self::preview_only() ) {
			$html = '<p class="wpcpm-sponsors__preview">' . esc_html__( 'Shown to you as an Administrator. Visitors see this once Sponsor tiers is on in Settings.', 'wpcredits-program-manager' ) . '</p>' . $html;
		}

		return $html;
	}

	/**
	 * The variant an attribute set asks for; anything but `strip` is the full list.
	 *
	 * @param array $args The attributes.
	 * @return string
	 */
	private static function variant( array $args ) {
		return isset( $args['variant'] ) && self::VARIANT_STRIP === $args['variant'] ? self::VARIANT_STRIP : self::VARIANT_FULL;
	}

	/**
	 * The full list: every sponsor in one list, in alphabetical order, then the upcoming events
	 * (`events()`). No tier heading: the page above it says what the list is.
	 *
	 * @param string $floor The lowest tier.
	 * @param string $today `Y-m-d` or ''.
	 * @return string
	 */
	private static function full( $floor, $today ) {
		$sponsors = self::sponsors( $floor, $today );
		$out      = '';

		if ( array() !== $sponsors ) {
			$out = '<ul class="wpcpm-sponsors__list">';

			foreach ( $sponsors as $sponsor ) {
				$out .= self::item( $sponsor, true );
			}

			$out .= '</ul>';
		}

		return $out . self::events( $today );
	}

	/**
	 * The upcoming events, the full list's last section: every event the event store's public reader
	 * gives (`WPCPM_Sponsor_Event::public_upcoming()`), soonest first, under a heading of its own,
	 * then the way in for whoever is looking (`way_in()`), since the people the events are for
	 * join them on their own pages, where each is listed with its Join. Nothing a joiner alone should
	 * see: no call link, no places, nobody on an event, by name or by count. Nothing while the launch
	 * switch is off, a program manager's preview included: the events are a benefit of the tiers,
	 * which the public sees nothing of before the launch. Nothing when no event is coming. A floor
	 * drops sponsors below it and leaves the events whole: they are the program's list, not a tier's.
	 *
	 * @param string $today `Y-m-d` or '': the day a host's tier is read on, as the list reads its own.
	 * @return string
	 */
	private static function events( $today ) {
		if ( ! class_exists( 'WPCPM_Sponsor_Event' ) || ! WPCPM_Sponsor_Tiers::live() ) {
			return '';
		}

		$events = WPCPM_Sponsor_Event::public_upcoming( $today );

		if ( array() === $events ) {
			return '';
		}

		// The site's clock, named: the page is the same for every visitor, and a page cache keeps it,
		// so no visitor's own clock can be read here.
		$zone  = wp_timezone();
		$label = WPCPM_Mentor_Availability::zone_label( $zone->getName() );
		$out   = '<section class="wpcpm-sponsors__events"><h3 class="wpcpm-sponsors__heading">' . esc_html__( 'Upcoming events', 'wpcredits-program-manager' ) . '</h3><ul class="wpcpm-sponsors__list">';

		foreach ( $events as $event ) {
			$out .= self::event_item( $event, $zone, $label );
		}

		$out .= '</ul>' . self::way_in();

		return $out . '</section>';
	}

	/**
	 * The way in under the upcoming events, for whoever is looking. A visitor signed out is given the
	 * login screen, as a button. A signed-in viewer is given one link instead, to the page they join
	 * on, named in full: the login screen draws its form again for somebody signed in already, which
	 * reads as a failed login. Who they are is the event store's own answer
	 * (`WPCPM_Sponsor_Event::audience_of()`, the one `join()` asks, a program manager first), and the
	 * page is the event list's for that answer (`WPCPM_Sponsor_Event_List::page_for()`), so the link
	 * never sends anybody to a page that refuses them the Join. Anyone else signed in, a sponsor's
	 * member or a subscriber, has no page that lists the events and is given no line, nor is a person
	 * whose page is not published. The page a cache keeps is a signed-out visitor's, so it keeps the
	 * button (see the class docblock).
	 *
	 * @return string
	 */
	private static function way_in() {
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<p class="wpcpm-sponsors__join"><a class="wpcpm-button wpcpm-button--secondary" href="%1$s">%2$s</a></p>',
				esc_url( wp_login_url() ),
				esc_html__( 'Sign in to join', 'wpcredits-program-manager' )
			);
		}

		$audience = WPCPM_Sponsor_Event::audience_of( null );
		$words    = array(
			WPCPM_Sponsor_Event::AUDIENCE_STUDENTS     => __( 'Join it on your Student Report Card', 'wpcredits-program-manager' ),
			WPCPM_Sponsor_Event::AUDIENCE_MENTORS      => __( 'Join it on your Mentor Report Card', 'wpcredits-program-manager' ),
			WPCPM_Sponsor_Event::AUDIENCE_INSTITUTIONS => __( 'Join it on your Institution Dashboard', 'wpcredits-program-manager' ),
			WPCPM_Sponsor_Event::AUDIENCE_MANAGERS     => __( 'Join it on your Administrator Dashboard', 'wpcredits-program-manager' ),
		);
		$page     = isset( $words[ $audience ] ) && class_exists( 'WPCPM_Sponsor_Event_List' ) ? WPCPM_Sponsor_Event_List::page_for( $audience ) : '';

		if ( '' === $page ) {
			return '';
		}

		return sprintf(
			'<p class="wpcpm-sponsors__join"><a href="%1$s">%2$s</a></p>',
			esc_url( $page ),
			esc_html( $words[ $audience ] )
		);
	}

	/**
	 * One upcoming event as a list item: its title; its host, a sponsor's name linked to its website
	 * as its entry in the list links it (`rel="sponsored"`, with https where the record has no
	 * scheme), or the program's name alone; when it runs on the site's clock with the zone named, and
	 * for how long; and its description, when the reader left any. Every value escaped by the escape
	 * its place needs.
	 *
	 * @param array        $event One row of `WPCPM_Sponsor_Event::public_upcoming()`.
	 * @param DateTimeZone $zone  The site's clock.
	 * @param string       $label The clock's name, as a person reads it.
	 * @return string
	 */
	private static function event_item( array $event, DateTimeZone $zone, $label ) {
		$href = self::href( $event['host_url'] );
		$host = '' !== $href
			? sprintf( '<a href="%1$s" rel="sponsored noopener">%2$s</a>', esc_url( $href ), esc_html( $event['host'] ) )
			: esc_html( $event['host'] );
		$html = '<span class="wpcpm-sponsors__event-title">' . esc_html( $event['title'] ) . '</span>';

		$html .= '<span class="wpcpm-sponsors__line">' . sprintf(
			/* translators: %s: who hosts the event, a sponsor's name or the WordPress Credits Program. */
			esc_html__( 'Hosted by %s', 'wpcredits-program-manager' ),
			$host
		) . '</span>';
		$html .= '<span class="wpcpm-sponsors__line">' . esc_html(
			sprintf(
				/* translators: 1: the day and the start and end times, 2: the time zone's name, e.g. Europe / Warsaw, 3: how long it runs, e.g. 60 minutes. */
				__( '%1$s (%2$s). %3$s.', 'wpcredits-program-manager' ),
				WPCPM_Mentor_Calls::format_range( (int) $event['start'], (int) $event['end'], $zone ),
				$label,
				WPCPM_Sponsor_Event::length_line( (int) $event['minutes'] )
			)
		) . '</span>';

		if ( '' !== $event['description'] ) {
			$html .= '<span class="wpcpm-sponsors__line">' . esc_html( $event['description'] ) . '</span>';
		}

		return '<li class="wpcpm-sponsors__event">' . $html . '</li>';
	}

	/**
	 * The strip: one row from Supporter up, in the full list's alphabetical order, then the Friends
	 * as a count. The row follows the name alone, as the partner page does, a sponsor drawn by its
	 * name where it has no logo taking its place by that name too.
	 *
	 * @param string $today `Y-m-d` or ''.
	 * @return string
	 */
	private static function strip( $today ) {
		$row     = array();
		$mentors = 0;

		foreach ( self::sponsors( WPCPM_Sponsor_Tiers::MENTOR, $today ) as $sponsor ) {
			if ( WPCPM_Sponsor_Tiers::LISTING_NAME === $sponsor['listing'] ) {
				++$mentors;
				continue;
			}

			$row[] = $sponsor;
		}

		if ( empty( $row ) && 0 === $mentors ) {
			return '';
		}

		$out = '<p class="wpcpm-sponsors__eyebrow">' . esc_html__( 'Sponsored by', 'wpcredits-program-manager' ) . '</p>';

		if ( ! empty( $row ) ) {
			$out .= '<ul class="wpcpm-sponsors__list">';

			foreach ( $row as $sponsor ) {
				$out .= self::item( $sponsor, false );
			}

			$out .= '</ul>';
		}

		$more = '';

		// Each form keeps its own translators comment immediately before its _n() call: the two
		// forms differ by more than the count (one opens the sentence, one continues it after
		// the row of logos), so they read more clearly apart than folded into one ternary.
		if ( $mentors > 0 ) {
			if ( empty( $row ) ) {
				$more = sprintf(
					/* translators: %s: how many companies sponsor the program's mentors. */
					_n( '%s company sponsoring mentors', '%s companies sponsoring mentors', $mentors, 'wpcredits-program-manager' ),
					number_format_i18n( $mentors )
				);
			} else {
				$more = sprintf(
					/* translators: %s: how many companies sponsor the program's mentors. */
					_n( 'and %s company sponsoring mentors', 'and %s companies sponsoring mentors', $mentors, 'wpcredits-program-manager' ),
					number_format_i18n( $mentors )
				);
			}
		}

		// The guard keeps the listing drawing, with no link, on a checkout without the page class.
		$page = class_exists( 'WPCPM_Sponsors_Page' ) ? WPCPM_Sponsors_Page::page_url() : '';
		$link = '' !== $page ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $page ), esc_html__( 'All partners', 'wpcredits-program-manager' ) ) : '';

		if ( '' !== $more || '' !== $link ) {
			$out .= '<p class="wpcpm-sponsors__more">' . esc_html( $more ) . ( '' !== $more && '' !== $link ? ' ' : '' ) . $link . '</p>';
		}

		return $out;
	}

	/**
	 * One sponsor as a list item: the logo and the name, or the name alone, inside a link to its
	 * website, and in the full list its description under them. A Friend is its name alone, with
	 * no link: the partner page gives a Friend its company name and no more.
	 *
	 * `rel="sponsored"`: these are the links of the program's sponsors, which is what the
	 * attribute is for. The image's `alt` is empty rather than the sponsor's name: the name
	 * already sits beside it in its own span, so a name in `alt` too would read it twice. In the
	 * full list the name is printed for everyone, as the partner page lists a logo, a name and a
	 * description; in the strip, a row of logos, it is a screen reader's alone. A white logo
	 * carries its own modifier, `wpcpm-sponsors__item--white`, for the ground the theme puts
	 * behind it (the Administrator Dashboard's `wpcpm-sapp-logo--white` marks its own logos the
	 * same way).
	 *
	 * @param array $sponsor   One entry of `sponsors()`.
	 * @param bool  $with_line Whether this is the full list: the name printed for everyone and the
	 *                         description under it.
	 * @return string
	 */
	private static function item( array $sponsor, $with_line ) {
		$href = WPCPM_Sponsor_Tiers::LISTING_NAME === $sponsor['listing'] ? '' : self::href( $sponsor['website'] );

		if ( '' !== $sponsor['logo'] ) {
			$body = sprintf(
				'<img class="wpcpm-sponsors__logo" src="%1$s" alt=""%2$s loading="lazy" /><span class="wpcpm-sponsors__name%3$s">%4$s</span>',
				esc_url( $sponsor['logo'] ),
				$sponsor['width'] > 0 ? sprintf( ' width="%1$d" height="%2$d"', (int) $sponsor['width'], (int) $sponsor['height'] ) : '',
				$with_line ? '' : ' screen-reader-text',
				esc_html( $sponsor['name'] )
			);
		} else {
			$body = '<span class="wpcpm-sponsors__name">' . esc_html( $sponsor['name'] ) . '</span>';
		}

		$html = '' !== $href
			? sprintf( '<a class="wpcpm-sponsors__link" href="%1$s" rel="sponsored noopener">%2$s</a>', esc_url( $href ), $body )
			: $body;

		if ( $with_line && '' !== $sponsor['line'] ) {
			$html .= '<span class="wpcpm-sponsors__line">' . esc_html( $sponsor['line'] ) . '</span>';
		}

		$modifier = '';

		if ( '' === $sponsor['logo'] ) {
			$modifier = ' wpcpm-sponsors__item--text';
		} elseif ( ! empty( $sponsor['white'] ) ) {
			$modifier = ' wpcpm-sponsors__item--white';
		}

		return sprintf( '<li class="wpcpm-sponsors__item%1$s">%2$s</li>', $modifier, $html );
	}

	/**
	 * The listing as plain HTML for the handbook and the WordPress Foundation's page: the one list
	 * in alphabetical order, absolute addresses, no heading and no class, so it pastes into a
	 * Custom HTML block anywhere. A Friend is its name alone; every tier above it its logo and name,
	 * linked to its website, and its description after them.
	 *
	 * Manager or not, empty while the switch is off: the box that shows it is one of the
	 * surfaces the switch gates.
	 *
	 * No event, as the strip holds none: a copy pasted into another page would keep the events it
	 * was copied with long after they took place, while the Partners page lists them as they come.
	 *
	 * A white-only logo prints as a name alone, no image: item()'s `--white` modifier leans on a
	 * stylesheet for the dark ground that makes it legible, and no stylesheet travels with this
	 * HTML into whatever page it is pasted into.
	 *
	 * @param string $today `Y-m-d` or ''.
	 * @return string
	 */
	public static function plain( $today = '' ) {
		if ( ! WPCPM_Sponsor_Tiers::live() ) {
			return '';
		}

		$sponsors = self::sponsors( WPCPM_Sponsor_Tiers::MENTOR, $today );

		if ( array() === $sponsors ) {
			return '';
		}

		$out = "<ul>\n";

		foreach ( $sponsors as $sponsor ) {
			if ( WPCPM_Sponsor_Tiers::LISTING_NAME === $sponsor['listing'] ) {
				$out .= '<li><strong>' . esc_html( $sponsor['name'] ) . "</strong></li>\n";
				continue;
			}

			$href  = self::href( $sponsor['website'] );
			$image = ( '' !== $sponsor['logo'] && empty( $sponsor['white'] ) )
				? sprintf(
					'<img src="%1$s" alt=""%2$s /> ',
					esc_url( $sponsor['logo'] ),
					$sponsor['width'] > 0 ? sprintf( ' width="%1$d" height="%2$d"', (int) $sponsor['width'], (int) $sponsor['height'] ) : ''
				)
				: '';
			$name  = $image . '<strong>' . esc_html( $sponsor['name'] ) . '</strong>';
			$link  = '' !== $href ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $href ), $name ) : $name;

			$out .= '<li>' . $link . ( '' === $sponsor['line'] ? '' : ' - ' . esc_html( $sponsor['line'] ) ) . "</li>\n";
		}

		return $out . "</ul>\n";
	}
}
