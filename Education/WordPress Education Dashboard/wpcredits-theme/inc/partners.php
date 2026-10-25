<?php
/**
 * The Partners page: its stylesheets, and the header menu's two items for it, the "Partners" link
 * while the partnership levels are public and the "Partner as an institution" button.
 *
 * The page is the plugin's public sponsors page, at `/partners/` (plugin 1.123.18). WordPress draws a
 * page by its slug's template before `page.html`, so `templates/page-partners.html` draws it: the
 * program lead's words about the partnership levels (21 October 2026) in the Education Hub's look,
 * five patterns around the page's own content, which is the plugin's listing of the partners. The
 * plugin keeps the page a draft until the levels go public, and refuses it to visitors until then.
 *
 * The menu's "Partners" link leads to this page, whose own "Become a partner" section has the button
 * on to the sponsorship application (a program Administrator asked for it and the site's owner agreed,
 * 22 October 2026). The menu's button, "Partner as an institution", leads to the institution
 * application form (the site's owner, 25 October 2026); until then the menu's one item, "Become a
 * partner", led to this page, and before that to the sponsorship application.
 *
 * The levels are written once, in the Partners template, and edited in the Site Editor (theme 1.32.1,
 * the design's D5 and D6): the Partner levels block holds a Partner level block for each level and
 * draws the cards, and the Levels at a glance block draws the comparison table from the levels of the
 * Partners template in use, `wpcredits_partner_levels()`, so the cards and the table cannot disagree.
 * The plugin's sponsor application shows the same levels in its "What each level includes" section,
 * asked through its `wpcpm_partner_levels` filter, as plain text. The five levels the theme ships, in
 * a program lead's words, are `wpcredits_partner_levels_defaults()`, which the levels pattern writes
 * into the blocks.
 *
 * @package WPCredits_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Partners page's slug, the template's name after `page-`. */
const WPCREDITS_PARTNERS_SLUG = 'partners';

/**
 * The type the menu's "Partners" link is hooked under. It is not a block: core writes the block the
 * `hooked_block_{type}` filter answers, a navigation link, and looks for the type hooked, this one, in
 * a menu's `ignoredHookedBlocks`. Two hooked blocks of one type reach `hooked_block_core/navigation-link`
 * with the same arguments and cannot be told apart, and one type for both would leave a menu saved while
 * the levels were not public ignoring the Partners link for good, since it lists the button's type, the
 * only one then offered, as ignored. A menu saved in the Site Editor holds this name in that list, so it
 * stays as it is.
 */
const WPCREDITS_PARTNERS_LINK_HOOK = 'wpcredits-theme/partners-link';

/**
 * Register the Partners page's stylesheet, after the hub's, whose type, heads, cards and buttons it
 * builds on.
 */
function wpcredits_partners_register_style() {
	wp_register_style(
		'wpcredits-partners',
		get_theme_file_uri( 'assets/css/partners.css' ),
		array( 'wpcredits-hub' ),
		WPCREDITS_VERSION
	);
}
add_action( 'init', 'wpcredits_partners_register_style', 10 );

/**
 * Whether this request is the Partners page.
 *
 * @return bool
 */
function wpcredits_is_partners_page() {
	return is_singular( 'page' ) && WPCREDITS_PARTNERS_SLUG === get_post_field( 'post_name', get_queried_object_id() );
}

/**
 * Enqueue the hub's stylesheet and the Partners page's on the Partners page.
 */
function wpcredits_partners_assets() {
	if ( wpcredits_is_partners_page() ) {
		wp_enqueue_style( 'wpcredits-hub' );
		wp_enqueue_style( 'wpcredits-partners' );
	}
}
add_action( 'wp_enqueue_scripts', 'wpcredits_partners_assets', 20 );

/**
 * The Partners page's stylesheet in the editor too, as the hub's is.
 */
function wpcredits_partners_editor_style() {
	add_editor_style( 'assets/css/partners.css' );
}
add_action( 'after_setup_theme', 'wpcredits_partners_editor_style', 12 );

/**
 * Whether the partnership levels are public: the plugin's launch switch, on.
 *
 * @return bool
 */
function wpcredits_partners_live() {
	return class_exists( 'WPCPM_Sponsor_Tiers' ) && method_exists( 'WPCPM_Sponsor_Tiers', 'live' ) && WPCPM_Sponsor_Tiers::live();
}

/**
 * The header menu's last two items: the "Partners" link while the levels are public, then the button,
 * "Partner as an institution", hooked into the menu as its last children (the site's owner, 21 and 25
 * October 2026). The link leads to the Partners page, so while the levels are not public there is no
 * link, and nothing points at what visitors cannot see yet. The button leads to the institution
 * application form, which does not depend on the levels, so it is always there.
 *
 * The button is hooked as a navigation link, the type it has been since 1.31.9, and the link under a type
 * of its own (`WPCREDITS_PARTNERS_LINK_HOOK`): see the constant. A menu saved in the Site Editor lists
 * the types it was offered as ignored, so the one saved before the levels were public is still offered
 * the link once they are, and a menu saved with both holds both as links of its own, as saved.
 *
 * @param string[] $hooked   The block types hooked at this position.
 * @param string   $position Where: before, after, first_child or last_child.
 * @param string   $anchor   The anchor block's type.
 * @return string[]
 */
function wpcredits_partners_menu_item( $hooked, $position, $anchor ) {
	if ( 'last_child' === $position && 'core/navigation' === $anchor ) {
		if ( wpcredits_partners_live() ) {
			$hooked[] = WPCREDITS_PARTNERS_LINK_HOOK;
		}

		$hooked[] = 'core/navigation-link';
	}

	return $hooked;
}
add_filter( 'hooked_block_types', 'wpcredits_partners_menu_item', 10, 3 );

/**
 * A hooked menu link's attributes: its label and address, and the class that dresses it.
 *
 * The navigation block marks a link current by a page's ID, not by its address: it matches a post type
 * archive's link by address, but a custom link to a page is never marked. So on the page a link leads to
 * it is written as a link to that page, with the page's ID, and the block marks it as it does a page
 * link, adding `current-menu-item` and `aria-current="page"`. The wide bar shows the Partners link in
 * the brand blue then, as it does any link to the page being viewed, and the button as it always looks,
 * its own rules winning over the header's current-item rules; the signed-in phone panel (below 1200px)
 * colors either row in the brand blue, the button's at its own weight (style.css). On every other page
 * a link stays the custom link it was.
 *
 * @param string $label The link's text, translated.
 * @param string $url   Where it leads.
 * @param string $class The class it wears, or '' for none.
 * @return array The block's attributes.
 */
function wpcredits_partners_menu_attrs( $label, $url, $class = '' ) {
	$attrs = array(
		'label' => $label,
		'url'   => $url,
		'kind'  => 'custom',
	);

	if ( '' !== $class ) {
		$attrs['className'] = $class;
	}

	$viewed = is_singular( 'page' ) ? get_queried_object_id() : 0;

	if ( $viewed && untrailingslashit( (string) get_permalink( $viewed ) ) === untrailingslashit( $url ) ) {
		$attrs['kind'] = 'post-type';
		$attrs['type'] = 'page';
		$attrs['id']   = $viewed;
	}

	return $attrs;
}

/**
 * Whether a hooked block sits last in a navigation, where the menu's two items go.
 *
 * @param string $position Where it is hooked.
 * @param array  $anchor   The anchor block, parsed.
 * @return bool
 */
function wpcredits_partners_menu_position( $position, $anchor ) {
	return 'last_child' === $position && isset( $anchor['blockName'] ) && 'core/navigation' === $anchor['blockName'];
}

/**
 * The hooked button's label, address and class: "Partner as an institution", the Education Hub's own
 * words for it, leading to the institution application form.
 *
 * While the plugin has no published form the button falls back to the WordPress Credits handbook, as
 * every link to one of the site's own pages does (`wpcredits_hub_url()`).
 *
 * @param array|null $block    The hooked block, parsed, or null when another filter removed it.
 * @param string     $type     The hooked block's type.
 * @param string     $position Where it is hooked.
 * @param array      $anchor   The anchor block, parsed.
 * @return array|null
 */
function wpcredits_partners_menu_item_block( $block, $type, $position, $anchor ) {
	if ( null === $block || ! wpcredits_partners_menu_position( $position, $anchor ) ) {
		return $block;
	}

	$block['attrs'] = wpcredits_partners_menu_attrs(
		__( 'Partner as an institution', 'wpcredits-theme' ),
		wpcredits_hub_url( 'institution_application' ),
		'wpc-nav__partner'
	);

	return $block;
}
add_filter( 'hooked_block_core/navigation-link', 'wpcredits_partners_menu_item_block', 10, 4 );

/**
 * The hooked Partners link: a plain link to the Partners page, written as the navigation link it is
 * although it is hooked under a type of its own (`WPCREDITS_PARTNERS_LINK_HOOK`).
 *
 * The link is hooked only while the partnership levels are public, so with the plugin off there is none.
 * Should the levels be public while the Partners page is not published, which the plugin's own launch
 * does not leave, it falls back to the WordPress Credits handbook, as every link to one of the site's own
 * pages does.
 *
 * @param array|null $block    The hooked block, parsed, or null when another filter removed it.
 * @param string     $type     The hooked block's type.
 * @param string     $position Where it is hooked.
 * @param array      $anchor   The anchor block, parsed.
 * @return array|null
 */
function wpcredits_partners_link_block( $block, $type, $position, $anchor ) {
	if ( null === $block || ! wpcredits_partners_menu_position( $position, $anchor ) ) {
		return $block;
	}

	$block['blockName'] = 'core/navigation-link';
	$block['attrs']     = wpcredits_partners_menu_attrs(
		/* translators: The header menu's link to the page about partnering with the program, a plural noun. */
		__( 'Partners', 'wpcredits-theme' ),
		wpcredits_hub_url( 'partners' )
	);

	return $block;
}
add_filter( 'hooked_block_' . WPCREDITS_PARTNERS_LINK_HOOK, 'wpcredits_partners_link_block', 10, 4 );

/**
 * The five partnership levels the theme ships, in a program lead's words (21 October 2026), the lowest
 * first: what the levels pattern (`patterns/partners-levels.php`) writes into the Partner levels block,
 * and so the Partners template's levels while no copy of it is saved.
 *
 * The texts are looked up when this is called, never when the file loads, since translations are not
 * ready until `init`. The amounts are the program's own, text on the page; the site handles no
 * payment.
 *
 * @return array[] Each level, as plain text: `name`, `amount` (what it costs, as the cards say it),
 *                 `short` (the amount in the comparison table's head), `lead` (the line before its
 *                 list, or what the level funds), `items` (what it adds, a list of strings) and, for
 *                 the Partner level alone, `tag`.
 */
function wpcredits_partner_levels_defaults() {
	return array(
		array(
			'name'   => __( 'Friend', 'wpcredits-theme' ),
			'amount' => __( 'In-kind, through Five for the Future', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, short: given in kind, not paid. */
			'short'  => __( 'In-kind', 'wpcredits-theme' ),
			'lead'   => __( 'For companies sponsoring mentors through Five for the Future.', 'wpcredits-theme' ),
			'items'  => array(
				__( 'Company name on the partner page', 'wpcredits-theme' ),
				__( 'Mention in the mentor feature', 'wpcredits-theme' ),
				__( 'Acknowledgement in the annual partner thank you post', 'wpcredits-theme' ),
			),
		),
		array(
			'name'   => __( 'Supporter', 'wpcredits-theme' ),
			/* translators: A partnership level's amount, in US dollars a year. */
			'amount' => __( '$5,000 a year', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, in US dollars. */
			'short'  => __( '$5,000', 'wpcredits-theme' ),
			'lead'   => __( 'Everything in Friend, plus:', 'wpcredits-theme' ),
			'items'  => array(
				__( 'Logo and description on the partner page', 'wpcredits-theme' ),
				__( 'A badge for your own site', 'wpcredits-theme' ),
				__( 'The annual program report', 'wpcredits-theme' ),
			),
		),
		array(
			'name'   => __( 'Backer', 'wpcredits-theme' ),
			/* translators: A partnership level's amount, in US dollars a year. */
			'amount' => __( '$10,000 a year', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, in US dollars. */
			'short'  => __( '$10,000', 'wpcredits-theme' ),
			'lead'   => __( 'Everything in Supporter, plus:', 'wpcredits-theme' ),
			'items'  => array(
				__( 'Named for the part of the program you fund in program communications', 'wpcredits-theme' ),
				__( 'A report covering that cost line', 'wpcredits-theme' ),
				__( 'One self-written educational post about the sponsored product', 'wpcredits-theme' ),
			),
		),
		array(
			'name'   => __( 'Champion', 'wpcredits-theme' ),
			/* translators: A partnership level's amount, in US dollars a year. */
			'amount' => __( '$15,000 a year', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, in US dollars. */
			'short'  => __( '$15,000', 'wpcredits-theme' ),
			'lead'   => __( 'Everything in Backer, plus:', 'wpcredits-theme' ),
			'items'  => array(
				__( 'A second self-written post with factual, value-neutral information about additional products', 'wpcredits-theme' ),
				__( 'An invitation to the annual education partners session', 'wpcredits-theme' ),
				__( 'A webinar or event co-hosted with the education program', 'wpcredits-theme' ),
			),
		),
		array(
			'name'   => _x( 'Partner', 'the highest partnership level, a noun: a company that funds a named program', 'wpcredits-theme' ),
			/* translators: A partnership level's amount, in US dollars a year, the least it starts from. */
			'amount' => __( 'From $30,000 a year', 'wpcredits-theme' ),
			/* translators: A partnership level's amount in the comparison table's head, in US dollars, the least it starts from. */
			'short'  => __( 'From $30,000', 'wpcredits-theme' ),
			'tag'    => __( 'Funds a named program', 'wpcredits-theme' ),
			'lead'   => __( 'Everything in Champion, plus a complete program funded end to end, the kind we have not been able to build yet because we have always had to work within the budget available. Students translating WordPress into languages with thin coverage, students working on accessibility, students building sites for nonprofit organizations that could not otherwise commission them.', 'wpcredits-theme' ),
			'items'  => array(
				__( 'Named as program partner on the program you fund, with the exclusive role in that program', 'wpcredits-theme' ),
				__( 'Named in launch communications', 'wpcredits-theme' ),
				__( 'A report specific to that program', 'wpcredits-theme' ),
			),
		),
	);
}

/*
 * The levels as blocks (theme 1.32.1): what a Partner level block's attributes hold, how the levels of
 * the Partners template in use are read, and the block markup the levels pattern and the 1.32.1
 * migration write.
 */

/**
 * Whether an item of a level says something: it has a character that is not white space, a no-break
 * space among the white space. An item is plain text, edited one to a line in the block's settings,
 * where the editor's own trim drops an item that is only a no-break space; PHP's trim keeps it, so the
 * page, the table and the plugin's form would print an empty-looking list item and a blank row.
 *
 * @param string $item An item.
 * @return bool
 */
function wpcredits_partner_item_has_words( $item ) {
	$words = preg_replace( '/[\s\x{00A0}]+/u', '', (string) $item );

	// An item that is not valid UTF-8 cannot be read as white space: it is kept, not taken for blank.
	return null === $words || '' !== $words;
}

/**
 * A level as the Partner levels block draws it, from a Partner level block's attributes: its name,
 * amount, tag and lead as rich text keeps them, its short amount trimmed, and its items, each trimmed,
 * an empty one, or one of only white space, a no-break space among it, left out.
 *
 * @param array $attributes The block's attributes.
 * @return array{name: string, amount: string, short: string, tag: string, lead: string, items: string[]}
 */
function wpcredits_partner_level( array $attributes ) {
	return array(
		'name'   => wpcredits_hub_attribute( $attributes, 'name' ),
		'amount' => wpcredits_hub_attribute( $attributes, 'amount' ),
		'short'  => trim( wpcredits_hub_attribute( $attributes, 'short' ) ),
		'tag'    => wpcredits_hub_attribute( $attributes, 'tag' ),
		'lead'   => wpcredits_hub_attribute( $attributes, 'lead' ),
		'items'  => array_values( array_filter( wpcredits_hub_strings( $attributes, 'items' ), 'wpcredits_partner_item_has_words' ) ),
	);
}

/**
 * A level as `wpcredits_partner_levels()` lists it, from what `wpcredits_partner_level()` gives: its
 * `name`, `amount`, `short`, its `tag` only when the tag has words, its `lead` and its `items`, in the
 * order of the defaults.
 *
 * @param array $level A level, as `wpcredits_partner_level()` gives it.
 * @return array|null The level, or null for one with no name, a draft the page does not draw.
 */
function wpcredits_partner_level_listed( array $level ) {
	if ( wpcredits_hub_blank( $level['name'] ) ) {
		return null;
	}

	$listed = array(
		'name'   => $level['name'],
		'amount' => $level['amount'],
		'short'  => $level['short'],
	);

	if ( ! wpcredits_hub_blank( $level['tag'] ) ) {
		$listed['tag'] = $level['tag'];
	}

	$listed['lead']  = $level['lead'];
	$listed['items'] = $level['items'];

	return $listed;
}

/**
 * A Partner level block's attributes for a level of plain text, as the defaults and the 1.31 cards'
 * reading give one: its name, amount, tag and lead as rich text keeps them, escaped, so an ampersand or
 * an angle bracket stays a character; its short amount and its items as they are, since they are
 * edited in the block's settings as plain text. A text or a list with nothing in it is not written, as
 * the editor leaves out an attribute at its default.
 *
 * @param array $level A level: `name`, `amount`, `short`, `tag`, `lead` and `items`, any of them missing.
 * @return array The attributes.
 */
function wpcredits_partner_level_attributes( array $level ) {
	$attributes = array();

	foreach ( array( 'name', 'amount', 'short', 'tag', 'lead' ) as $key ) {
		$text = wpcredits_hub_attribute( $level, $key );

		if ( '' !== trim( $text ) ) {
			$attributes[ $key ] = 'short' === $key ? $text : wpcredits_hub_rich( $text );
		}
	}

	$items = wpcredits_hub_strings( $level, 'items' );

	if ( $items ) {
		$attributes['items'] = $items;
	}

	return $attributes;
}

/**
 * Levels of plain text as the Partner level blocks hold them, and as `wpcredits_partner_levels()` lists
 * them, a level with no name left out.
 *
 * @param array[] $levels Levels, as the defaults and the 1.31 cards' reading give them.
 * @return array[]
 */
function wpcredits_partner_levels_as_blocks( array $levels ) {
	$listed = array();

	foreach ( $levels as $level ) {
		$level = is_array( $level ) ? wpcredits_partner_level_listed( wpcredits_partner_level( wpcredits_partner_level_attributes( $level ) ) ) : null;

		if ( null !== $level ) {
			$listed[] = $level;
		}
	}

	return $listed;
}

/**
 * Whether WordPress draws a block as nothing: one an Administrator hid from its menu in the Site Editor,
 * which saves `"metadata":{"blockVisibility":false}` in its comment. Core's block visibility support
 * (WordPress 7.0 and later, `wp_render_block_visibility_support()`) then draws it, and the blocks it holds
 * with it, as nothing. A block hidden at some window sizes only (`"blockVisibility":{"viewport":...}`) is
 * drawn, and shown at the other sizes, so it is not hidden here.
 *
 * @param array $parsed A parsed block.
 * @return bool
 */
function wpcredits_partner_hidden( array $parsed ) {
	return isset( $parsed['attrs']['metadata']['blockVisibility'] ) && false === $parsed['attrs']['metadata']['blockVisibility'];
}

/**
 * The levels a Partner levels block holds, as parse_blocks() gives the block: its Partner level blocks'
 * in their order, a level with no name left out, and a level an Administrator hid
 * (`wpcredits_partner_hidden()`), which the page draws as nothing.
 *
 * @param array $parsed The Partner levels block, parsed.
 * @return array[] Each level as `wpcredits_partner_level_listed()` gives it.
 */
function wpcredits_partner_levels_of( array $parsed ) {
	$levels = array();

	foreach ( isset( $parsed['innerBlocks'] ) && is_array( $parsed['innerBlocks'] ) ? $parsed['innerBlocks'] : array() as $inner ) {
		if ( ! is_array( $inner ) || ! isset( $inner['blockName'] ) || 'wpcredits/partner-level' !== $inner['blockName'] || wpcredits_partner_hidden( $inner ) ) {
			continue;
		}

		$level = wpcredits_partner_level_listed( wpcredits_partner_level( isset( $inner['attrs'] ) && is_array( $inner['attrs'] ) ? $inner['attrs'] : array() ) );

		if ( null !== $level ) {
			$levels[] = $level;
		}
	}

	return $levels;
}

/**
 * The Partner levels block in some parsed blocks: looked for at every depth among the blocks themselves
 * first, and only when it is not among them, in the content a block that refers to other content draws
 * in its place, read as WordPress draws it, each reference in the order of the markup and its content
 * read the same way: a pattern a `core/pattern` block refers to, a synced pattern a `core/block` block
 * refers to and a template part of this theme a `core/template-part` block refers to. An Administrator
 * can turn the levels group into a synced pattern or a template part, and the saved template then holds
 * only the reference.
 *
 * So a saved Partners template whose levels block is in its own blocks is read without the template
 * parts it opens with, the WordPress.org bar and the header, being read. Where a template holds a levels
 * block in its own blocks and another in what it refers to, its own is the one read, wherever the
 * reference stands in the markup; elsewhere the first found is.
 *
 * The block is the one WordPress draws: a levels block an Administrator hid, or one in a block that is
 * hidden, a reference among them (`wpcredits_partner_hidden()`), is drawn as nothing, so it is passed
 * over for one that is drawn; when each levels block found is hidden, the answer is false, and the page
 * shows no levels.
 *
 * @param array[]  $blocks Parsed blocks.
 * @param string[] $seen   The references being read (`wpcredits_partner_reference()`), so content that
 *                         refers to itself, or to what refers to it, is read once.
 * @param bool     $hidden Whether the blocks are in a hidden block, so none of them is drawn.
 * @return array|false|null The levels block WordPress draws, parsed; false when the blocks hold levels
 *                          blocks but WordPress draws none of them; null when they hold none.
 */
function wpcredits_partner_levels_block_in( array $blocks, array $seen = array(), $hidden = false ) {
	$references = array();
	$found      = wpcredits_partner_levels_block_among( $blocks, $hidden, $references );

	foreach ( $references as $held ) {
		if ( is_array( $found ) ) {
			break;
		}

		list( $parsed, $hides ) = $held;
		$reference              = wpcredits_partner_reference( $parsed );

		if ( ! in_array( $reference, $seen, true ) ) {
			$within = wpcredits_partner_levels_block_in( wpcredits_partner_reference_blocks( $parsed ), array_merge( $seen, array( $reference ) ), $hides );
			$found  = null === $within ? $found : $within;
		}
	}

	return $found;
}

/**
 * The first Partner levels block WordPress draws among some parsed blocks and the blocks they hold, at
 * every depth, what a block refers to left unread: each block that refers to other content
 * (`wpcredits_partner_reference()`) is put on a list instead, in the order of the markup, with whether it
 * is hidden or in a hidden block, for `wpcredits_partner_levels_block_in()` to follow.
 *
 * @param array[] $blocks     Parsed blocks.
 * @param bool    $hidden     Whether the blocks are in a hidden block.
 * @param array[] $references The blocks met that refer to other content, each `array( parsed block,
 *                            hidden )`; those met here are added.
 * @return array|false|null The block, parsed; false when the blocks hold only levels blocks WordPress
 *                          does not draw; null when they hold none.
 */
function wpcredits_partner_levels_block_among( array $blocks, $hidden, array &$references ) {
	$found = null;

	foreach ( $blocks as $parsed ) {
		if ( ! is_array( $parsed ) || empty( $parsed['blockName'] ) ) {
			continue;
		}

		$hides = $hidden || wpcredits_partner_hidden( $parsed );

		if ( 'wpcredits/partner-levels' === $parsed['blockName'] ) {
			if ( ! $hides ) {
				return $parsed;
			}

			$found = false;
			continue;
		}

		if ( '' !== wpcredits_partner_reference( $parsed ) ) {
			$references[] = array( $parsed, $hides );
			continue;
		}

		$within = wpcredits_partner_levels_block_among( isset( $parsed['innerBlocks'] ) && is_array( $parsed['innerBlocks'] ) ? $parsed['innerBlocks'] : array(), $hides, $references );

		if ( is_array( $within ) ) {
			return $within;
		}

		$found = null === $within ? $found : $within;
	}

	return $found;
}

/**
 * What a block refers to, if it draws other content in its place: `pattern:` and the slug of a
 * `core/pattern` block, `block:` and the ID of a `core/block` block (a synced pattern), `part:` and the
 * slug of a `core/template-part` block. The same content is the same reference, wherever it is placed.
 *
 * @param array $parsed A parsed block.
 * @return string The reference, or '' for a block that refers to nothing, or to nothing named.
 */
function wpcredits_partner_reference( array $parsed ) {
	$attrs = isset( $parsed['attrs'] ) && is_array( $parsed['attrs'] ) ? $parsed['attrs'] : array();

	switch ( $parsed['blockName'] ) {
		case 'core/pattern':
			return isset( $attrs['slug'] ) && is_string( $attrs['slug'] ) && '' !== $attrs['slug'] ? 'pattern:' . $attrs['slug'] : '';

		case 'core/block':
			return isset( $attrs['ref'] ) && is_numeric( $attrs['ref'] ) && (int) $attrs['ref'] > 0 ? 'block:' . (int) $attrs['ref'] : '';

		case 'core/template-part':
			return isset( $attrs['slug'] ) && is_string( $attrs['slug'] ) && '' !== $attrs['slug'] ? 'part:' . $attrs['slug'] : '';
	}

	return '';
}

/**
 * The blocks a block that refers to other content draws in its place, parsed (see
 * `wpcredits_partner_reference()`).
 *
 * @param array $parsed A parsed block that refers to something.
 * @return array[] The blocks, none for content WordPress would not draw.
 */
function wpcredits_partner_reference_blocks( array $parsed ) {
	$attrs = isset( $parsed['attrs'] ) && is_array( $parsed['attrs'] ) ? $parsed['attrs'] : array();

	switch ( $parsed['blockName'] ) {
		case 'core/pattern':
			return wpcredits_partner_pattern_blocks( (string) $attrs['slug'] );

		case 'core/block':
			return wpcredits_partner_synced_blocks( (int) $attrs['ref'] );

		case 'core/template-part':
			return wpcredits_partner_part_blocks( (string) $attrs['slug'], isset( $attrs['theme'] ) && is_string( $attrs['theme'] ) ? $attrs['theme'] : '' );
	}

	return array();
}

/**
 * A registered pattern's blocks, parsed: what a `core/pattern` block that refers to it draws.
 *
 * @param string $slug The pattern's name, such as `wpcredits/partners-levels`.
 * @return array[] Its blocks, none for a pattern the site does not have.
 */
function wpcredits_partner_pattern_blocks( $slug ) {
	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		return array();
	}

	$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );

	return is_array( $pattern ) && isset( $pattern['content'] ) && is_string( $pattern['content'] ) ? parse_blocks( $pattern['content'] ) : array();
}

/**
 * A synced pattern's blocks, parsed: what a `core/block` block that refers to its post draws. As core's
 * block does, it draws a `wp_block` post that is published and has no password, and nothing else: not a
 * draft or one in the trash, not a post of another type, not an ID no post has.
 *
 * @param int $id The post's ID, above 0 (core's get_post() answers the global post for 0).
 * @return array[] Its blocks, none for a pattern WordPress would not draw.
 */
function wpcredits_partner_synced_blocks( $id ) {
	$post = get_post( $id );

	if ( ! is_object( $post ) || 'wp_block' !== $post->post_type || 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
		return array();
	}

	return parse_blocks( (string) $post->post_content );
}

/**
 * A template part's blocks, parsed: what a `core/template-part` block that refers to it draws. As
 * core's block does, it draws a part of the active theme only, the theme being this one when the block
 * names none; the part's saved copy while that is published (core asks for `publish`, though
 * get_block_template() also answers with a draft or one in the trash), else the theme's own file of
 * that name.
 *
 * @param string $slug  The part's slug.
 * @param string $theme The theme the block names, or ''.
 * @return array[] Its blocks, none for a part WordPress would not draw.
 */
function wpcredits_partner_part_blocks( $slug, $theme ) {
	if ( '' !== $theme && get_stylesheet() !== $theme ) {
		return array();
	}

	$id   = get_stylesheet() . '//' . $slug;
	$part = get_block_template( $id, 'wp_template_part' );

	if ( $part instanceof WP_Block_Template && 'custom' === $part->source && 'publish' !== $part->status ) {
		$part = get_block_file_template( $id, 'wp_template_part' );
	}

	return $part instanceof WP_Block_Template ? parse_blocks( (string) $part->content ) : array();
}

/**
 * The levels of the Partners template in use, the lowest first: what the page's table draws, and what
 * the plugin's sponsor application is handed, as plain text (`wpcredits_partners_levels_for_form()`).
 *
 * The template in use is the copy of the Partners template saved in the Site Editor, if there is one,
 * else the theme's file (the design's D6, as ruling R2 has it):
 *
 * - no saved copy, or one that is not published (in draft or in the trash: get_block_template()
 *   answers with those, but WordPress draws only a published copy): the levels the theme's file draws,
 *   the defaults its levels pattern writes;
 * - a saved copy with the Partner levels block, found at any depth among its own blocks first, then in a
 *   pattern, a synced pattern or a template part it refers to (an Administrator can turn the levels
 *   group into one of the last two; `wpcredits_partner_levels_block_in()`): the levels the block holds,
 *   none when an Administrator emptied it, and a level an Administrator hid in the Site Editor left
 *   out, as the page draws it as nothing (`wpcredits_partner_hidden()`); a level hidden at some window
 *   sizes only stays, the page showing it at the others;
 * - a saved copy whose levels block an Administrator hid, or put in a block that is hidden, with none
 *   drawn elsewhere: none, as the page shows none, whatever else the markup holds;
 * - a saved copy without the block but with the cards as theme 1.31 drew them, saved before the 1.32.1
 *   migration or from an editor opened before the update: the levels read from those cards;
 * - a saved copy with neither: none, so the page shows no levels, and the plugin's form names the
 *   Partners page instead of listing levels the page does not show.
 *
 * Read once a request: the template is read on the first call, and `wpcredits_partner_levels_reset()`
 * forgets what was read.
 *
 * @return array[] Each level as the Partner level blocks hold it, a level with no name left out: `name`,
 *                 `amount`, `short`, `tag` (only for a level that has one), `lead` and `items`; the name,
 *                 amount, tag and lead as rich text keeps them, the short amount and the items as plain
 *                 text.
 */
function wpcredits_partner_levels() {
	if ( ! isset( $GLOBALS['wpcredits_partner_levels'] ) || ! is_array( $GLOBALS['wpcredits_partner_levels'] ) ) {
		$GLOBALS['wpcredits_partner_levels'] = wpcredits_partner_levels_read();
	}

	return $GLOBALS['wpcredits_partner_levels'];
}

/**
 * Forget the levels read in this request, so the next `wpcredits_partner_levels()` reads the template
 * again.
 */
function wpcredits_partner_levels_reset() {
	unset( $GLOBALS['wpcredits_partner_levels'] );
}

/**
 * The levels of the Partners template in use, read from it now: see `wpcredits_partner_levels()`.
 *
 * @return array[]
 */
function wpcredits_partner_levels_read() {
	$template = get_block_template( get_stylesheet() . '//page-' . WPCREDITS_PARTNERS_SLUG );

	if ( ! $template instanceof WP_Block_Template || 'custom' !== $template->source || 'publish' !== $template->status ) {
		return wpcredits_partner_levels_as_blocks( wpcredits_partner_levels_defaults() );
	}

	$content = (string) $template->content;
	$block   = wpcredits_partner_levels_block_in( parse_blocks( $content ) );

	if ( is_array( $block ) ) {
		return wpcredits_partner_levels_of( $block );
	}

	// A levels block WordPress does not draw: the page shows no levels, and the 1.31 cards' reading is not
	// asked.
	if ( false === $block ) {
		return array();
	}

	return wpcredits_partner_levels_as_blocks( wpcredits_partner_levels_from_html( $content ) );
}

/**
 * The levels in markup as theme 1.31 drew them: the cards (`ul.wpc-partners-levels__grid`), as a saved
 * copy of the Partners template holds them in a Custom HTML block, and an Administrator may have edited
 * them there, and the short amounts from the comparison table's head, where the markup holds it.
 *
 * Each card gives a level: its name and its tag from its heading, its amount, its lead and its items,
 * each as plain text, white space run together. The short amount of the level in a card is the amount
 * in the table head's column for that card, the first column being the head word's, as 1.31.11 drew
 * the cards and the columns from one list. A card with no name, and an empty item, are left out.
 *
 * @param string $html The markup, such as a whole saved template.
 * @return array[] Each level as plain text, in the order of the defaults: `name`, `amount`, `short`
 *                 (where the table's head has it), `tag` (where the card has one), `lead` and `items`;
 *                 an empty list when no card can be read.
 */
function wpcredits_partner_levels_from_html( $html ) {
	$html   = (string) $html;
	$levels = array();

	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return $levels;
	}

	$document = new DOMDocument();
	$errors   = libxml_use_internal_errors( true );

	$document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $errors );

	$xpath  = new DOMXPath( $document );
	$shorts = array();

	foreach ( $xpath->query( '(//table[' . wpcredits_partners_class( 'wpc-partners-glance__table' ) . '])[1]/thead/tr[1]/th[position() > 1]' ) as $head ) {
		$shorts[] = wpcredits_partners_text( $xpath->query( './/*[' . wpcredits_partners_class( 'wpc-partners-glance__amount' ) . ']', $head )->item( 0 ) );
	}

	foreach ( $xpath->query( '(//*[' . wpcredits_partners_class( 'wpc-partners-levels__grid' ) . '])[1]/li[' . wpcredits_partners_class( 'wpc-partners-level' ) . ']' ) as $column => $card ) {
		$heading = $xpath->query( './/*[' . wpcredits_partners_class( 'wpc-partners-level__name' ) . ']', $card )->item( 0 );
		$tag     = $heading ? wpcredits_partners_text( $xpath->query( './/*[' . wpcredits_partners_class( 'wpc-partners-tag' ) . ']', $heading )->item( 0 ) ) : '';
		$name    = '';

		if ( $heading ) {
			$heading = $heading->cloneNode( true );

			foreach ( iterator_to_array( $xpath->query( './/*[' . wpcredits_partners_class( 'wpc-partners-tag' ) . ']', $heading ) ) as $tag_node ) {
				$tag_node->parentNode->removeChild( $tag_node ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- The DOM extension's own property name.
			}

			$name = wpcredits_partners_text( $heading );
		}

		if ( '' === $name ) {
			continue;
		}

		$level = array(
			'name'   => $name,
			'amount' => wpcredits_partners_text( $xpath->query( './/*[' . wpcredits_partners_class( 'wpc-partners-level__amount' ) . ']', $card )->item( 0 ) ),
		);

		if ( isset( $shorts[ $column ] ) && '' !== $shorts[ $column ] ) {
			$level['short'] = $shorts[ $column ];
		}

		if ( '' !== $tag ) {
			$level['tag'] = $tag;
		}

		$level['lead']  = wpcredits_partners_text( $xpath->query( './/*[' . wpcredits_partners_class( 'wpc-partners-level__lead' ) . ']', $card )->item( 0 ) );
		$level['items'] = array();

		foreach ( $xpath->query( './/*[' . wpcredits_partners_class( 'wpc-partners-level__items' ) . ']/li', $card ) as $item ) {
			$text = wpcredits_partners_text( $item );

			if ( '' !== $text ) {
				$level['items'][] = $text;
			}
		}

		$levels[] = $level;
	}

	return $levels;
}

/**
 * An XPath test that an element carries a class, as a whole word of its class attribute.
 *
 * @param string $name The class.
 * @return string
 */
function wpcredits_partners_class( $name ) {
	return 'contains(concat(" ", normalize-space(@class), " "), " ' . $name . ' ")';
}

/**
 * The text of a node, its entities read, white space run together and trimmed; '' for no node.
 *
 * @param DOMNode|null $node The node.
 * @return string
 */
function wpcredits_partners_text( $node ) {
	return $node ? trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) ) : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- The DOM extension's own property name.
}

/**
 * The Partner levels block holding a Partner level block for each of some levels, as the Site Editor
 * saves it: what the levels pattern writes, and the 1.32.1 migration in place of the 1.31 cards.
 *
 * @param array[] $levels     Levels of plain text, as `wpcredits_partner_levels_defaults()` and
 *                            `wpcredits_partner_levels_from_html()` give them.
 * @param array   $attributes The levels block's own attributes, none unless the block that it replaces
 *                            carried some (its `metadata`, in the 1.32.1 migration).
 * @return string Block markup; the block alone, with nothing in it, for no levels.
 */
function wpcredits_partner_levels_markup( array $levels, array $attributes = array() ) {
	$inner = array();

	foreach ( $levels as $level ) {
		$inner[] = wpcredits_hub_block_markup( 'wpcredits/partner-level', wpcredits_partner_level_attributes( (array) $level ) );
	}

	return wpcredits_hub_block_markup( 'wpcredits/partner-levels', $attributes, $inner );
}

/**
 * The Levels at a glance block as the Site Editor saves it, with no attribute: its words are the
 * translated defaults until an Administrator types others.
 *
 * @return string Block markup.
 */
function wpcredits_partner_glance_markup() {
	return wpcredits_hub_block_markup( 'wpcredits/partner-glance' );
}

/**
 * The renders of the levels a Partner levels block holds, in its order, a level with no name left out.
 *
 * @param WP_Block|null $block The levels.
 * @return string[] Each level's render, trimmed.
 */
function wpcredits_partner_levels_cards( $block ) {
	$cards = array();

	if ( ! $block instanceof WP_Block ) {
		return $cards;
	}

	foreach ( $block->inner_blocks as $inner ) {
		if ( 'wpcredits/partner-level' !== $inner->name ) {
			continue;
		}

		$html = trim( $inner->render() );

		if ( '' !== $html ) {
			$cards[] = $html;
		}
	}

	return $cards;
}

/**
 * The words of the comparison table's frame: the head cell's word and the two words its cells say, as
 * typed in the Levels at a glance block's settings, and where none is, the translated strings theme
 * 1.31.11's pattern printed.
 *
 * @param array $attributes The block's attributes.
 * @return array{label: string, yes: string, no: string} Plain text.
 */
function wpcredits_partner_glance_words( array $attributes ) {
	$label = trim( wpcredits_hub_attribute( $attributes, 'label' ) );
	$yes   = trim( wpcredits_hub_attribute( $attributes, 'yes' ) );
	$no    = trim( wpcredits_hub_attribute( $attributes, 'no' ) );

	return array(
		'label' => '' !== $label ? $label : __( 'Recognition', 'wpcredits-theme' ),
		'yes'   => '' !== $yes ? $yes : _x( 'Yes', 'a partnership level has this recognition', 'wpcredits-theme' ),
		'no'    => '' !== $no ? $no : _x( 'No', 'a partnership level does not have this recognition', 'wpcredits-theme' ),
	);
}

/**
 * The comparison table's rows: one for each item of each level, the lowest level's first, each with the
 * level that lists it, from which up the table says Yes (the design's D5).
 *
 * @param array[] $levels Levels, as `wpcredits_partner_levels()` lists them.
 * @return array[] Each row's `item`, plain text, and `from`, the position of the level that lists it.
 */
function wpcredits_partner_glance_rows( array $levels ) {
	$rows = array();

	foreach ( array_values( $levels ) as $from => $level ) {
		foreach ( $level['items'] as $item ) {
			$rows[] = array(
				'item' => $item,
				'from' => $from,
			);
		}
	}

	return $rows;
}

/**
 * A text edited as rich text, a level's name, amount, lead or tag, as the plugin's sponsor application is
 * handed it: plain text, since the plugin escapes what it prints. A line break becomes a space, the tags
 * are taken out and the entities read, and white space is run together.
 *
 * @param string $text A text, as rich text keeps it.
 * @return string
 */
function wpcredits_partner_plain( $text ) {
	$text = (string) preg_replace( '#<br\s*/?>#i', ' ', (string) $text );
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	return trim( (string) preg_replace( '/[ \t\r\n]+/', ' ', $text ) );
}

/**
 * An item of a level as the plugin's sponsor application is handed it. An item is plain text already,
 * typed in the level's settings, and the page and the table print it with esc_html(), so an angle
 * bracket in it is a character: "Up to <5 students" and "<3 mentors" are shown whole. So it is handed as
 * the page shows it, its entities read and its white space run together, and no tag is taken out, which
 * would cut the first short and leave the second out.
 *
 * @param string $item An item.
 * @return string
 */
function wpcredits_partner_plain_item( $item ) {
	$item = html_entity_decode( (string) $item, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	return trim( (string) preg_replace( '/[ \t\r\n]+/', ' ', $item ) );
}

/**
 * The levels the plugin's sponsor application shows in its "What each level includes" section: the
 * Partners page's own, the levels of the Partners template in use, so a company reading the form is
 * told what the page told it.
 *
 * The plugin defines `wpcpm_partner_levels` and asks it while the levels are public, passing an empty
 * list; what is returned replaces it, so the filter's argument is not read. The answer does not
 * depend on whether the levels are public, since the plugin only asks while they are. The contract,
 * as the plugin defines it: a list of levels in order, each with `name` (string), `amount` (string),
 * `lead` (string), `items` (a list of strings) and, optionally, `tag` (string); the plugin skips a
 * malformed entry and escapes everything it prints, so every text is handed as plain text: a name,
 * amount, lead or tag the Site Editor keeps in bold, or with an apostrophe as an entity, reaches the form
 * as its words (`wpcredits_partner_plain()`), and an item, plain text already, as the page shows it, an
 * angle bracket in it kept (`wpcredits_partner_plain_item()`). An item, or a tag, with no words left is
 * left out. The comparison table's `short` is left out too, the form has no use for it. With no levels,
 * the list is empty, and the plugin's form names the Partners page instead.
 *
 * @param array $levels What the filter received: the plugin passes an empty list. Not read.
 * @return array[] Each level: `name`, `amount`, `lead`, `items` and, for a level that has one, `tag`.
 */
function wpcredits_partners_levels_for_form( $levels ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- replaced, see the docblock.
	$for_form = array();

	foreach ( wpcredits_partner_levels() as $level ) {
		$items = array();

		foreach ( $level['items'] as $item ) {
			$item = wpcredits_partner_plain_item( $item );

			if ( '' !== $item ) {
				$items[] = $item;
			}
		}

		$entry = array(
			'name'   => wpcredits_partner_plain( $level['name'] ),
			'amount' => wpcredits_partner_plain( $level['amount'] ),
			'lead'   => wpcredits_partner_plain( $level['lead'] ),
			'items'  => $items,
		);
		$tag   = isset( $level['tag'] ) ? wpcredits_partner_plain( $level['tag'] ) : '';

		if ( '' !== $tag ) {
			$entry['tag'] = $tag;
		}

		$for_form[] = $entry;
	}

	return $for_form;
}
add_filter( 'wpcpm_partner_levels', 'wpcredits_partners_levels_for_form' );
